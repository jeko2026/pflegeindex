<?php

namespace App\Console\Commands;

use App\Models\Facility;
use App\Support\HttpUrl;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

final class FacilitiesExportOutsourcingCommand extends Command
{
    protected $signature = 'facilities:export-outsourcing {--dry-run : Report counts without creating files}';

    protected $description = 'Export incomplete facility contacts for an external read-only review';

    private const CURRENT_HEADERS = [
        'facility_id', 'current_name', 'current_address', 'current_postal_code', 'current_city',
        'current_phone', 'current_email', 'current_website', 'current_contact_source',
        'current_verified_status', 'current_official_website_absent', 'current_official_email_absent',
    ];

    private const REVIEW_HEADERS = [
        'verified_name', 'verified_address', 'verified_postal_code', 'verified_city', 'verified_phone',
        'verified_email', 'official_website', 'official_website_absent', 'official_email_absent',
        'phone_source_url', 'email_source_url', 'address_source_url', 'website_source_url',
        'verification_status', 'comment', 'checked_at',
    ];

    public function handle(): int
    {
        $facilities = Facility::query()->with('city')->orderBy('id')->get();
        $metrics = [
            'facilities_total' => $facilities->count(),
            'without_phone' => $facilities->filter(fn (Facility $f): bool => blank($f->phone))->count(),
            'without_email' => $facilities->filter(fn (Facility $f): bool => blank($f->email))->count(),
            'official_email_absent' => $facilities->where('official_email_absent', true)->count(),
            'without_website' => $facilities->filter(fn (Facility $f): bool => blank($f->website))->count(),
            'official_website_absent' => $facilities->where('official_website_absent', true)->count(),
        ];
        $selected = $facilities->filter(fn (Facility $facility): bool => $this->needsReview($facility))
            ->sortBy(fn (Facility $f): string => mb_strtolower((string) ($f->city?->name ?? '')).'|'.mb_strtolower((string) $f->name).'|'.$f->id)
            ->values();
        $metrics['requires_review'] = $selected->count();
        $metrics['export_rows'] = $selected->count();

        if ($this->option('dry-run')) {
            $this->renderMetrics($metrics);
            return self::SUCCESS;
        }

        $directory = storage_path('app/exports');
        File::ensureDirectoryExists($directory);
        $timestamp = now()->format('Y-m-d_His');
        $csvPath = $directory.'/facilities_outsourcing_'.$timestamp.'.csv';
        $this->writeCsv($csvPath, $selected);
        $readmePath = $directory.'/facilities_outsourcing_README.txt';
        File::put($readmePath, $this->readme());
        $this->renderMetrics($metrics);
        $this->info('CSV: '.$csvPath);
        $this->info('README: '.$readmePath);

        return self::SUCCESS;
    }

    private function needsReview(Facility $facility): bool
    {
        return blank($facility->phone)
            || (blank($facility->email) && ! $facility->official_email_absent)
            || (blank($facility->website) && ! $facility->official_website_absent)
            || $facility->contact_status !== 'verified'
            || blank($facility->contact_source)
            || $facility->contact_checked_at === null
            || (filled($facility->contact_source) && ! HttpUrl::isValid($facility->contact_source));
    }

    private function renderMetrics(array $metrics): void
    {
        foreach ($metrics as $key => $value) {
            $this->line(str_replace('_', ' ', $key).': '.$value);
        }
    }

    /** @param Collection<int, Facility> $facilities */
    private function writeCsv(string $path, Collection $facilities): void
    {
        $handle = fopen($path, 'wb');
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, [...self::CURRENT_HEADERS, ...self::REVIEW_HEADERS], ';');
        foreach ($facilities as $facility) {
            $row = [
                $facility->id, $facility->name, $facility->address, $facility->postal_code, $facility->city?->name,
                $facility->phone, $facility->email, $facility->website, $facility->contact_source,
                $facility->contact_status, $this->flag($facility->official_website_absent), $this->flag($facility->official_email_absent),
            ];
            fputcsv($handle, [...$row, ...array_fill(0, count(self::REVIEW_HEADERS), '')], ';');
        }
        fclose($handle);
    }

    private function flag(?bool $value): string
    {
        if ($value === null) {
            return 'unknown';
        }

        return $value ? 'yes' : 'no';
    }

    private function readme(): string
    {
        return "PflegeIndex – Outsourcing-Kontaktprüfung\n\n".
            "Die current_*-Spalten sind unveränderliche Ausgangsdaten. Der externe Prüfer füllt ausschließlich die Spalten verified_*, official_*, *_source_url, verification_status, comment und checked_at aus.\n\n".
            "Zulässige verification_status-Werte: verified, partially_verified, needs_review, closed_or_moved, not_found. Bei Unsicherheit bitte needs_review verwenden.\n\n".
            "Für jedes gefundene oder geänderte Feld ist eine überprüfbare HTTP/HTTPS-Quell-URL erforderlich. E-Mail-Adressen dürfen nicht erraten werden. Google Maps, BKK Pflegefinder, Gelbe Seiten und andere Verzeichnisse gelten nicht als offizielle Website. Eine Träger-Website ist nur zulässig, wenn die konkrete Einrichtung eindeutig identifiziert wird.\n\n".
            "Die Datei enthält keine Benutzerkonten, Passwörter, Tokens, Admin-URLs, internen Notizen oder sonstige technische Verwaltungsdaten. Es gibt keinen Import in diese Exportfunktion.\n";
    }
}
