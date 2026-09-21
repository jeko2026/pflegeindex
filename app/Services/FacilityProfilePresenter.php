<?php

namespace App\Services;

use App\Models\Facility;
use Carbon\CarbonImmutable;

class FacilityProfilePresenter
{
    private const DAYS = ['Monday' => 'Montag', 'Tuesday' => 'Dienstag', 'Wednesday' => 'Mittwoch', 'Thursday' => 'Donnerstag', 'Friday' => 'Freitag', 'Saturday' => 'Samstag', 'Sunday' => 'Sonntag'];

    private const SOCIAL = ['facebook' => 'Facebook', 'instagram' => 'Instagram', 'linkedin' => 'LinkedIn', 'youtube' => 'YouTube', 'tiktok' => 'TikTok', 'twitter' => 'X / Twitter', 'x' => 'X / Twitter'];

    public function for(Facility $facility): array
    {
        return ['openingHours' => $this->openingHours((string) optional($facility->openingHours)->hours_text), 'socialLinks' => $this->socialLinks($facility)];
    }

    public function openingHours(string $raw): array
    {
        if (trim($raw) === '') {
            return [];
        } preg_match_all('/(Monday|Tuesday|Wednesday|Thursday|Friday|Saturday|Sunday)(?:\([^)]*\))?\s*:\s*(.*?)(?=,\s*(?:Monday|Tuesday|Wednesday|Thursday|Friday|Saturday|Sunday)(?:\(|\s*:)|$)/i', $raw, $m, PREG_SET_ORDER);
        $today = CarbonImmutable::now('Europe/Berlin')->englishDayOfWeek;

        return collect($m)->map(function ($x) use ($today) {
            $d = ucfirst(strtolower($x[1]));
            $v = trim($x[2], " []\t\n\r\0\x0B");

            return isset(self::DAYS[$d]) && $v !== '' ? ['day' => self::DAYS[$d], 'value' => $this->formatHours($v), 'today' => $d === $today] : null;
        })->filter()->values()->all();
    }

    public function socialLinks(Facility $facility): array
    {
        return $facility->socialLinks->map(function ($link) {
            $p = strtolower(trim((string) $link->platform));
            $u = $this->safeUrl((string) $link->url);

            return isset(self::SOCIAL[$p]) && $u ? ['platform' => $p, 'label' => self::SOCIAL[$p], 'url' => $u] : null;
        })->filter()->values()->all();
    }

    private function formatHours(string $v): string
    {
        if (strcasecmp($v, 'Open 24 hours') === 0) {
            return '24 Stunden geöffnet';
        }if (strcasecmp($v, 'Closed') === 0) {
            return 'Geschlossen';
        }

return collect(preg_split('/\s*(?:,|;)\s*/', $v) ?: [])->map(function ($p) {
            $r = preg_split('/\s*[–-]\s*/u', trim($p));

            return count($r) === 2 ? $this->time($r[0]).'–'.$this->time($r[1]) : trim($p);
        })->implode(', ');
    }

    private function time(string $v): string
    {
        $v = trim($v);
        if (! preg_match('/^(\d{1,2})(?::(\d{2}))?\s*(AM|PM)$/i', $v, $m)) {
            return $v;
        }$h = (int) $m[1];
        if (strtoupper($m[3]) === 'PM' && $h !== 12) {
            $h += 12;
        }if (strtoupper($m[3]) === 'AM' && $h === 12) {
            $h = 0;
        }

return sprintf('%02d:%02d', $h, (int) ($m[2] ?? 0));
    }

    private function safeUrl(string $u): ?string
    {
        $p = parse_url(trim($u));
        if (! is_array($p) || ! in_array($p['scheme'] ?? '', ['http', 'https'], true) || empty($p['host'])) {
            return null;
        }$roots = ['facebook.com', 'www.facebook.com', 'instagram.com', 'www.instagram.com', 'linkedin.com', 'www.linkedin.com', 'youtube.com', 'www.youtube.com', 'tiktok.com', 'www.tiktok.com', 'twitter.com', 'www.twitter.com', 'x.com', 'www.x.com'];
        if (in_array(strtolower($p['host']), $roots, true) && empty(trim($p['path'] ?? '', '/'))) {
            return null;
        }

return $p['scheme'].'://'.$p['host'].($p['path'] ?? '').(isset($p['fragment']) ? '#'.$p['fragment'] : '');
    }
}
