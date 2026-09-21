<?php

namespace App\Services;

use App\Models\City;
use Illuminate\Support\Collection;

class SeoActionContent
{
    public function service(City $city, array $category, Collection $facilities): array
    {
        $slug = $category['slug'];
        $count = $facilities->count();
        $heading = $category['label'].' in '.$city->name;
        $focus = config('seo_action.service_profiles.'.$city->slug.'/'.$slug, '');
        $title = match ($slug) {
            'ambulante-pflegedienste' => 'Pflegedienste in '.$city->name.' – Auswahl & Kontakt | PflegeIndex',
            'pflegeheime' => 'Pflegeheime in '.$city->name.' – Angebote vergleichen | PflegeIndex',
            default => 'Tagespflege in '.$city->name.' – Angebote & Kontakt | PflegeIndex',
        };
        if (mb_strlen($title) > 78) {
            $title = $heading.' | PflegeIndex';
        }
        $sentenceLabel = $slug === 'ambulante-pflegedienste' ? 'ambulante Pflegedienste' : $category['label'];
        $intro = 'Diese Übersicht enthält '.$count.' '.($count === 1 ? 'zugeordneten Eintrag' : 'zugeordnete Einträge').' für '.$sentenceLabel.' in '.$city->name.'. Die Profile zeigen Anschriften und vorhandene Kontakthinweise. Öffnen Sie mehrere passende Einträge und stellen Sie den Anbietern dieselben Fragen. Die Reihenfolge ist alphabetisch und stellt keine Bewertung dar. Ob ein Angebot den persönlichen Bedarf abdeckt, muss im direkten Gespräch geklärt werden. Eine Aufnahme oder bestimmte Einsatzzeit lässt sich aus dem Verzeichniseintrag nicht ableiten.';
        $blocks = match ($slug) {
            'ambulante-pflegedienste' => [
                ['title' => 'Welche Unterstützung wird benötigt?', 'body' => 'Schreiben Sie auf, welche Aufgaben zu Hause anfallen und wann Hilfe benötigt wird. Fragen Sie den Dienst, welche dieser Aufgaben er übernehmen kann und wie die Abstimmung mit Angehörigen erfolgt. Ärztlich verordnete Maßnahmen sollten Sie im Gespräch gesondert ansprechen.'],
                ['title' => 'Kosten und Leistungsplan vergleichen', 'body' => 'Bitten Sie um ein schriftliches Angebot für die konkret gewünschten Einsätze. Lassen Sie erklären, welche Leistungen enthalten sind und welche Kosten selbst zu tragen wären. Fragen zur Finanzierung gehören auch zur Pflegekasse oder einer Pflegeberatung; hier werden keine pauschalen Besuchspreise angegeben.'],
            ],
            'tagespflege' => [
                ['title' => 'Tagesablauf und Beförderung abstimmen', 'body' => 'Fragen Sie nach Betreuungstagen, Ankunfts- und Rückkehrzeiten sowie dem Ablauf eines typischen Tages. Klären Sie für die konkrete Wohnadresse, ob und wie eine Beförderung organisiert werden kann. Besprechen Sie persönliche Gewohnheiten und benötigte Unterstützung vor dem ersten Besuch.'],
                ['title' => 'Was kostet die Tagespflege?', 'body' => 'Fordern Sie ein auf die gewünschten Tage bezogenes Angebot an. Lassen Sie Betreuung, Verpflegung, Beförderung und weitere ausgewiesene Kosten einzeln erläutern. Welche Finanzierung im eigenen Fall infrage kommt, sollten Einrichtung und Pflegekasse vor Beginn gemeinsam mit Ihnen klären.'],
            ],
            default => [
                ['title' => 'Besichtigung und persönlicher Bedarf', 'body' => 'Besprechen Sie den Unterstützungsbedarf und fragen Sie nach einem Besuchstermin. Notieren Sie Fragen zu Ansprechpartnern, Tagesablauf und persönlichen Gewohnheiten. Prüfen Sie, welche Vereinbarungen tatsächlich im angebotenen Vertrag stehen; der Name einer Einrichtung ersetzt diese Prüfung nicht.'],
                ['title' => 'Pflegeheimkosten nachvollziehen', 'body' => 'Bitten Sie um eine vollständige Kostenaufstellung für die geplante Aufnahme. Pflegebezogene Kosten, Unterkunft und Verpflegung sowie Investitionskosten sollten erkennbar getrennt sein. Zusatzleistungen und bereits berücksichtigte Zuschüsse müssen eindeutig beschrieben werden. Ein Durchschnittswert ersetzt kein individuelles Angebot.'],
            ],
        };
        $topic = match ($slug) {
            'ambulante-pflegedienste' => 'ambulante-pflege', 'tagespflege' => 'tagespflege', default => 'pflegeheim'
        };
        $faq = [
            ['question' => 'Wie viele Angebote sind hier für '.$city->name.' zugeordnet?', 'answer' => 'Die Übersicht enthält '.$count.' '.($count === 1 ? 'Eintrag' : 'Einträge').'. Berücksichtigt werden die hinterlegte Pflegeart und bei zusammengefassten Arten eindeutige Zuordnungshinweise. Die Liste ist keine vollständige Erhebung aller Anbieter.'],
            ['question' => 'Wie kann ich passende Angebote vergleichen?', 'answer' => 'Öffnen Sie die Profile, notieren Sie Ihre Anforderungen und fragen Sie mehrere Anbieter nach denselben Punkten. Anschrift und vorhandene Kontaktdaten helfen bei der Kontaktaufnahme; eine Qualitätsrangfolge wird nicht vergeben.'],
            ['question' => 'Zeigt PflegeIndex, ob eine Aufnahme möglich ist?', 'answer' => 'Nein. Ob eine Einrichtung aufnehmen oder ein Dienst Einsätze übernehmen kann, muss direkt erfragt werden. Das Verzeichnis enthält keine laufend bestätigte Belegung oder Terminverfügbarkeit.'],
            ['question' => 'Was muss ich zur Finanzierung klären?', 'answer' => 'Lassen Sie sich ein schriftliches Angebot erklären und fragen Sie bei der Pflegekasse nach der persönlichen Finanzierung. Halten Sie fest, welche Zuschüsse bereits berücksichtigt sind und welche Beträge selbst zu zahlen wären.'],
        ];

        return compact('heading', 'title', 'intro', 'focus', 'blocks', 'faq', 'topic') + [
            'description' => $heading.': '.$count.' '.($count === 1 ? 'Eintrag' : 'Einträge').' mit Anschriften und Kontakthinweisen. Auswahl, Kostenfragen und nächste Schritte verständlich erklärt.',
            'reviewed_at' => config('seo_action.reviewed_at'),
        ];
    }

    public function city(City $city, int $count): ?array
    {
        $focus = config('seo_action.city_profiles.'.$city->slug);
        if (! $focus) {
            return null;
        }

        return [
            'title' => 'Pflege in '.$city->name.' – Einrichtungen & Pflegearten | PflegeIndex',
            'description' => $count.' Pflegeeinrichtungen in '.$city->name.'. Pflegearten unterscheiden, Profile ansehen und Fragen für den direkten Kontakt vorbereiten.',
            'intro' => 'Hier finden Sie '.$count.' hinterlegte Pflegeeinrichtungen in '.$city->name.'. Die Stadtübersicht verbindet unterschiedliche Angebote; nicht jeder Eintrag erfüllt denselben Zweck. Vergleichen Sie deshalb zuerst die angegebene Pflegeart und dann die Profile mit Anschrift und verfügbaren Kontakthinweisen. Die alphabetische Liste ist keine Qualitätsbewertung. Angaben zum Umfang der Betreuung oder zur persönlichen Finanzierung sollten Sie direkt bestätigen lassen. Ein Eintrag bedeutet nicht, dass eine Aufnahme oder Versorgung aktuell zugesagt werden kann.',
            'focus' => $focus,
            'reviewed_at' => config('seo_action.reviewed_at'),
        ];
    }
}
