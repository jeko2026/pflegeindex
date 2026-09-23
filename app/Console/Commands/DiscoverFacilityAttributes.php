<?php

namespace App\Console\Commands;

use App\Models\Facility;
use App\Services\FacilityEnrichment\FacilityAttributeUpserter;
use App\Services\FacilityEnrichment\FacilityEnrichmentCrawler;
use App\Services\FacilityEnrichment\FacilityRelationEvaluator;
use Illuminate\Console\Command;

final class DiscoverFacilityAttributes extends Command
{
    protected $signature = 'pflegeindex:enrichment-discover {facility? : Facility ID} {--limit=1 : Maximum facilities} {--persist : Store candidates (never use against production)}';

    protected $description = 'Find evidence-backed facility attributes on official websites; dry-run by default.';

    public function handle(FacilityEnrichmentCrawler $crawler, FacilityRelationEvaluator $relation, FacilityAttributeUpserter $upserter): int
    {
        if ($this->option('persist') && app()->environment('production')) {
            $this->error('Persisting enrichment candidates is blocked in production.');

            return self::FAILURE;
        }
        $facilities = Facility::query()->with('city')->when($this->argument('facility'), fn ($q, $id) => $q->whereKey($id))->whereNotNull('website')->limit((int) $this->option('limit'))->get();
        $count = 0;
        foreach ($facilities as $facility) {
            foreach ($crawler->discover($facility) as $candidate) {
                $match = $relation->evaluate($facility, $candidate);
                $candidate['confidence'] = $match['confidence'];
                $candidate['relation_reason'] = $match['relation_reason'];
                unset($candidate['source_text']);
                if ($this->option('persist')) {
                    $upserter->store($facility, $candidate);
                } $count++;
            }
        }
        $this->info("{$count} evidence-backed candidates ".($this->option('persist') ? 'stored.' : 'found (dry-run).'));

        return self::SUCCESS;
    }
}
