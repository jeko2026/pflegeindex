<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Facility;
use App\Models\City;

final class DataQualityCoverageCommand extends Command
{
    protected $signature = 'data-quality:coverage 
                            {--limit=20 : Limit the number of cities shown} 
                            {--city= : Filter coverage by city slug}';

    protected $description = 'Display contact coverage statistics for facilities';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $citySlug = $this->option('city');
        $cityFilter = null;

        if ($citySlug) {
            $cityFilter = City::where('slug', $citySlug)->first();
            if (!$cityFilter) {
                $this->error("City with slug '{$citySlug}' not found.");
                return 1;
            }
        }

        $query = Facility::query();
        if ($cityFilter) {
            $query->where('city_id', $cityFilter->id);
        }
        $facilities = $query->get();
        $total = $facilities->count();

        if ($total === 0) {
            $this->warn("No facilities found matching the filters.");
            return 0;
        }

        $verified = 0;
        $unverified = 0;
        $hasPhone = 0;
        $hasEmail = 0;
        $hasWebsite = 0;
        $noContacts = 0;

        $cityStats = [];

        foreach ($facilities as $f) {
            if ($f->contact_status === 'verified') {
                $verified++;
            } else {
                $unverified++;
            }

            $phoneFilled = ($f->phone !== null && trim($f->phone) !== '');
            $emailFilled = ($f->email !== null && trim($f->email) !== '');
            $websiteFilled = ($f->website !== null && trim($f->website) !== '');

            if ($phoneFilled) $hasPhone++;
            if ($emailFilled) $hasEmail++;
            if ($websiteFilled) $hasWebsite++;

            $cid = $f->city_id;
            if (!isset($cityStats[$cid])) {
                $cityStats[$cid] = [
                    'total' => 0,
                    'no_contacts' => 0,
                ];
            }

            $cityStats[$cid]['total']++;

            if (!$phoneFilled && !$emailFilled && !$websiteFilled) {
                $noContacts++;
                $cityStats[$cid]['no_contacts']++;
            }
        }

        // Calculate percentages
        $pctPhone = ($hasPhone / $total) * 100;
        $pctEmail = ($hasEmail / $total) * 100;
        $pctWebsite = ($hasWebsite / $total) * 100;
        $pctNoContacts = ($noContacts / $total) * 100;

        $this->info("=== Contact Coverage Report ===");

        $this->info("General Stats:");
        $this->table(
            ['Metric', 'Count', 'Percentage'],
            [
                ['Total Facilities', $total, '100%'],
                ['Verified', $verified, number_format(($verified / $total) * 100, 1) . '%'],
                ['Unverified', $unverified, number_format(($unverified / $total) * 100, 1) . '%'],
                ['Has Phone', $hasPhone, number_format($pctPhone, 1) . '%'],
                ['Has Email', $hasEmail, number_format($pctEmail, 1) . '%'],
                ['Has Website', $hasWebsite, number_format($pctWebsite, 1) . '%'],
                ['No Contacts At All', $noContacts, number_format($pctNoContacts, 1) . '%'],
            ]
        );

        // Sort cities by missing contacts count DESC
        uasort($cityStats, fn ($a, $b) => $b['no_contacts'] <=> $a['no_contacts']);

        $this->info("\nTop Cities with Most Facilities Lacking Contacts:");
        $rows = [];
        $i = 0;
        foreach ($cityStats as $cid => $stats) {
            if ($stats['no_contacts'] === 0) continue;
            if ($i++ >= $limit) break;

            $cityName = City::where('id', $cid)->value('name') ?? 'Unknown';
            $rows[] = [
                $cityName,
                $stats['total'],
                $stats['no_contacts'],
                number_format(($stats['no_contacts'] / $stats['total']) * 100, 1) . '%',
            ];
        }

        if (empty($rows)) {
            $this->line("All cities have 100% contact coverage!");
        } else {
            $this->table(['City', 'Total Facilities', 'Lacking Contacts', 'Lack Percentage'], $rows);
        }

        return 0;
    }
}
