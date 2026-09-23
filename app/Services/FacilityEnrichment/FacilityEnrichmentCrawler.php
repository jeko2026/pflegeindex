<?php

namespace App\Services\FacilityEnrichment;

use App\Models\Facility;
use App\Support\HttpUrl;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

final class FacilityEnrichmentCrawler
{
    private const PATTERNS = [
        'accessibility.barrier_free' => '/\b(barrierefrei|barrierearme)\b/ui', 'accessibility.elevator' => '/\b(aufzug|fahrstuhl)\b/ui',
        'outdoor.garden' => '/\b(garten|gartenanlage)\b/ui', 'outdoor.balcony_terrace' => '/\b(balkon|terrasse)\b/ui',
        'room.single_room' => '/\b(einzelzimmer)\b/ui', 'room.double_room' => '/\b(doppelzimmer)\b/ui',
        'room.private_bathroom' => '/\b(eigenes? (bad|badezimmer)|privatbad)\b/ui', 'room.own_furniture_allowed' => '/\b(eigene möbel|möblierung.*selbst)\b/ui',
        'services.hairdresser' => '/\b(friseur)\b/ui', 'services.foot_care' => '/\b(fußpflege|fusspflege|podologie)\b/ui',
        'services.physiotherapy' => '/\b(physiotherapie)\b/ui', 'services.occupational_therapy' => '/\b(ergotherapie)\b/ui', 'services.speech_therapy' => '/\b(logopädie)\b/ui',
        'care.dementia' => '/\b(demenz)\b/ui', 'care.palliative' => '/\b(palliativ)\b/ui', 'care.intensive' => '/\b(intensivpflege)\b/ui', 'care.young_care' => '/\b(junge pflege)\b/ui', 'care.gerontopsychiatric' => '/\b(gerontopsychiatr)\b/ui',
        'activities.events' => '/\b(veranstaltungen|feste)\b/ui', 'activities.excursions' => '/\b(ausflüge|ausfluege)\b/ui', 'activities.gymnastics' => '/\b(gymnastik)\b/ui', 'activities.memory_training' => '/\b(gedächtnistraining|gedaechtnistraining)\b/ui', 'activities.music' => '/\b(musik)\b/ui', 'activities.creative' => '/\b(kreativ|basteln|malen)\b/ui',
        'safety.emergency_call' => '/\b(notruf)\b/ui', 'contact.contact_form' => '/\b(kontaktformular)\b/ui', 'media.youtube' => '/(?:youtube\.com|youtu\.be)/ui', 'media.gallery' => '/\b(galerie|bildergalerie)\b/ui', 'media.virtual_tour' => '/\b(virtuell(?:er|e)? (rundgang|besichtigung))\b/ui',
    ];

    public function discover(Facility $facility, int $maxPages = 5): array
    {
        if (! filter_var($facility->website, FILTER_VALIDATE_URL)) {
            return [];
        }
        $urls = [$facility->website];
        $seen = [];
        $results = [];
        while ($urls !== [] && count($seen) < $maxPages) {
            $url = array_shift($urls);
            if (isset($seen[$url])) {
                continue;
            } $seen[$url] = true;
            try {
                $response = Http::timeout(12)->withHeaders(['Accept' => 'text/html,application/xhtml+xml'])->get($url);
            } catch (ConnectionException) {
                continue;
            }
            if (! $response->successful() || ! str_contains((string) $response->header('Content-Type'), 'text/html')) {
                continue;
            }
            $html = (string) $response->body();
            $text = $this->text($html);
            $title = $this->title($html);
            foreach (self::PATTERNS as $key => $pattern) {
                if (preg_match($pattern, $text, $match, PREG_OFFSET_CAPTURE)) {
                    $excerpt = $this->excerpt($text, $match[0][1], strlen($match[0][0]));
                    if ($this->meaningful($excerpt)) {
                        $results[] = ['attribute_key' => $key, 'attribute_value' => $match[0][0], 'normalized_value' => Str::slug($key), 'source_url' => $url, 'source_type' => 'official_website', 'source_title' => $title, 'source_excerpt' => $excerpt, 'source_context' => $excerpt, 'source_text' => $text];
                    }
                }
            }
            foreach ($this->links($html, $url) as $link) {
                if (count($urls) < $maxPages * 3 && ! isset($seen[$link])) {
                    $urls[] = $link;
                }
            }
        }

        return $results;
    }

    private function text(string $html): string
    {
        return trim(preg_replace('/\s+/u', ' ', strip_tags(preg_replace('/<(script|style|nav|footer)\b[^>]*>.*?<\/\1>/si', ' ', $html))) ?? '');
    }

    private function title(string $html): ?string
    {
        return preg_match('/<title[^>]*>(.*?)<\/title>/si', $html, $m) ? trim(strip_tags($m[1])) : null;
    }

    private function excerpt(string $text, int $offset, int $length): string
    {
        return trim(Str::limit(substr($text, max(0, $offset - 150), $length + 300), 420, ''));
    }

    private function meaningful(string $excerpt): bool
    {
        return mb_strlen($excerpt) >= 45 && ! preg_match('/^(start|kontakt|impressum)(\s|$)/ui', $excerpt);
    }

    private function links(string $html, string $base): array
    {
        preg_match_all('/href=["\']([^"\']+)["\']/i', $html, $matches);
        $baseParts = parse_url($base);
        $host = $baseParts['host'] ?? null;
        $scheme = $baseParts['scheme'] ?? 'https';
        $basePath = $baseParts['path'] ?? '/';
        $directory = rtrim(str_contains($basePath, '/') ? substr($basePath, 0, strrpos($basePath, '/') + 1) : '/', '/');

        return collect($matches[1])->map(function (string $href) use ($host, $scheme, $directory): ?string {
            $href = trim(html_entity_decode($href, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if ($href === '' || str_starts_with($href, '#') || preg_match('/^(?:mailto:|tel:|javascript:)/i', $href)) {
                return null;
            }
            $url = str_starts_with($href, 'http') ? $href : (str_starts_with($href, '/') ? "{$scheme}://{$host}{$href}" : "{$scheme}://{$host}{$directory}/{$href}");
            if (parse_url($url, PHP_URL_HOST) !== $host || preg_match('/\.(?:css|js|map|png|jpe?g|gif|svg|webp|ico|woff2?)(?:\?|$)/i', $url)) {
                return null;
            }

            return HttpUrl::normalize($url, true);
        })->filter()->unique()->values()->all();
    }
}
