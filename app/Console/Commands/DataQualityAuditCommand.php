<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Facility;
use App\Models\City;
use App\Services\DataQuality\FacilityDataAuditor;

final class DataQualityAuditCommand extends Command
{
    protected $signature = 'data-quality:audit 
                            {--format=console : The output format (console, json, csv)} 
                            {--city= : Filter audit by city slug}';

    protected $description = 'Perform a read-only audit of the facility database contacts';

    public function handle(): int
    {
        $format = $this->option('format');
        if (!in_array($format, ['console', 'json', 'csv'], true)) {
            $this->error("Invalid format '{$format}'. Allowed formats: console, json, csv.");
            return 1;
        }

        $citySlug = $this->option('city');
        $city = null;
        if ($citySlug) {
            $city = City::where('slug', $citySlug)->first();
            if (!$city) {
                $this->error("City with slug '{$citySlug}' not found.");
                return 1;
            }
        }

        $query = Facility::query();
        if ($city) {
            $query->where('city_id', $city->id);
        }
        $facilities = $query->get();

        $metrics = [
            'total' => $facilities->count(),
            'verified' => 0,
            'unverified' => 0,
            'missing_phone' => 0,
            'missing_email' => 0,
            'missing_website' => 0,
            'no_contacts' => 0,
            'invalid_emails' => 0,
            'suspicious_emails' => 0,
            'manual_review_emails' => 0,
            'invalid_websites' => 0,
            'suspicious_websites_http' => 0,
            'suspicious_phones' => 0,
        ];

        $issuesList = [];

        foreach ($facilities as $f) {
            if ($f->contact_status === 'verified') {
                $metrics['verified']++;
            } else {
                $metrics['unverified']++;
            }

            $emailAudit = FacilityDataAuditor::auditEmail($f->email);
            $webAudit = FacilityDataAuditor::auditWebsite($f->website);
            $phoneAudit = FacilityDataAuditor::auditPhone($f->phone);

            $hasPhone = ($f->phone !== null && trim($f->phone) !== '');
            $hasEmail = ($f->email !== null && trim($f->email) !== '');
            $hasWeb = ($f->website !== null && trim($f->website) !== '');

            if (!$hasPhone && !$hasEmail && !$hasWeb) {
                $metrics['no_contacts']++;
            }

            if ($emailAudit === 'missing') $metrics['missing_email']++;
            if ($webAudit === 'missing') $metrics['missing_website']++;
            if ($phoneAudit === 'missing') $metrics['missing_phone']++;

            $cityName = $f->city?->name ?? 'Unknown';

            if ($emailAudit === 'invalid') {
                $metrics['invalid_emails']++;
                $issuesList[] = [
                    'id' => $f->id,
                    'name' => $f->name,
                    'city' => $cityName,
                    'field' => 'email',
                    'value' => $f->email,
                    'issue_category' => 'invalid',
                    'severity' => 'error',
                ];
            } elseif ($emailAudit === 'manual_review') {
                $metrics['manual_review_emails']++;
                $issuesList[] = [
                    'id' => $f->id,
                    'name' => $f->name,
                    'city' => $cityName,
                    'field' => 'email',
                    'value' => $f->email,
                    'issue_category' => 'manual_review',
                    'severity' => 'warning',
                ];
            }

            if ($webAudit === 'invalid') {
                $metrics['invalid_websites']++;
                $issuesList[] = [
                    'id' => $f->id,
                    'name' => $f->name,
                    'city' => $cityName,
                    'field' => 'website',
                    'value' => $f->website,
                    'issue_category' => 'invalid',
                    'severity' => 'error',
                ];
            } elseif ($webAudit === 'suspicious') {
                $metrics['suspicious_websites_http']++;
                $issuesList[] = [
                    'id' => $f->id,
                    'name' => $f->name,
                    'city' => $cityName,
                    'field' => 'website',
                    'value' => $f->website,
                    'issue_category' => 'suspicious',
                    'severity' => 'warning',
                ];
            }

            if ($phoneAudit === 'suspicious') {
                $metrics['suspicious_phones']++;
                $issuesList[] = [
                    'id' => $f->id,
                    'name' => $f->name,
                    'city' => $cityName,
                    'field' => 'phone',
                    'value' => $f->phone,
                    'issue_category' => 'suspicious',
                    'severity' => 'warning',
                ];
            }
        }

        // Global duplicate check
        $duplicates = FacilityDataAuditor::detectDuplicateCandidates();

        if ($format === 'json') {
            $outputData = [
                'metrics' => $metrics,
                'issues' => $issuesList,
                'duplicates' => $duplicates,
            ];
            $this->output->write(json_encode($outputData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            return 0;
        }

        if ($format === 'csv') {
            $out = fopen('php://temp', 'r+');
            fputcsv($out, ['id', 'name', 'city', 'field', 'value', 'issue_category', 'severity']);
            foreach ($issuesList as $issue) {
                fputcsv($out, [
                    $issue['id'],
                    $issue['name'],
                    $issue['city'],
                    $issue['field'],
                    $issue['value'],
                    $issue['issue_category'],
                    $issue['severity'],
                ]);
            }
            rewind($out);
            $csvContent = stream_get_contents($out);
            fclose($out);
            $this->output->write($csvContent);
            return 0;
        }

        // Default: Console output
        $this->info("=== PflegeIndex Data Quality Audit ===");

        $this->info("Summary Metrics:");
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Facilities', $metrics['total']],
                ['Verified Facilities', $metrics['verified']],
                ['Unverified Facilities', $metrics['unverified']],
                ['Facilities without any contacts', $metrics['no_contacts']],
                ['Missing Phones', $metrics['missing_phone']],
                ['Missing Emails', $metrics['missing_email']],
                ['Missing Websites', $metrics['missing_website']],
                ['Invalid Emails', $metrics['invalid_emails']],
                ['Emails for manual review', $metrics['manual_review_emails']],
                ['Invalid Websites', $metrics['invalid_websites']],
                ['HTTP Websites (suspicious)', $metrics['suspicious_websites_http']],
                ['Suspicious Phones', $metrics['suspicious_phones']],
            ]
        );

        if (!empty($issuesList)) {
            $this->info("\nContact Issues:");
            $issuesRows = array_map(fn ($i) => [
                $i['id'],
                $i['name'],
                $i['city'],
                $i['field'],
                $i['value'],
                $i['issue_category'],
                $i['severity']
            ], $issuesList);
            $this->table(['ID', 'Name', 'City', 'Field', 'Value', 'Category', 'Severity'], $issuesRows);
        }

        $this->info("\nDuplicate Candidates (Status: duplicate_candidate):");
        
        $hasDupes = false;
        foreach ($duplicates as $type => $list) {
            if (empty($list)) continue;
            $hasDupes = true;
            $this->comment("Type: {$type}");
            if ($type === 'same_name_same_city') {
                $rows = array_map(fn ($item) => [$item['name'], $item['city'], implode(', ', $item['ids'])], $list);
                $this->table(['Name', 'City', 'IDs'], $rows);
            } elseif ($type === 'same_address_same_city') {
                $rows = array_map(fn ($item) => [$item['address'], $item['city'], implode(', ', $item['ids'])], $list);
                $this->table(['Address', 'City', 'IDs'], $rows);
            } elseif ($type === 'shared_phone') {
                $rows = array_map(fn ($item) => [
                    $item['phone'],
                    implode("\n", array_map(fn ($f) => "ID {$f['id']}: {$f['name']} ({$f['city']})", $item['facilities']))
                ], $list);
                $this->table(['Phone', 'Facilities'], $rows);
            } elseif ($type === 'shared_website') {
                $rows = array_map(fn ($item) => [
                    $item['website'],
                    implode("\n", array_map(fn ($f) => "ID {$f['id']}: {$f['name']} ({$f['city']})", $item['facilities']))
                ], $list);
                $this->table(['Website', 'Facilities'], $rows);
            }
        }

        if (!$hasDupes) {
            $this->line("No duplicate candidates found.");
        }

        return 0;
    }
}
