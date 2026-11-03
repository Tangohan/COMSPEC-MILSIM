<?php

declare(strict_types=1);

namespace App\Support\QualificationSuggestion;

use App\Support\JobCatalog\JobCatalog;

/**
 * Nature d’une unité de l’ORBAT (forces spéciales, Special Tactics, aéroporté, aviation, génie, santé…) lue dans les
 * textes de l’unité et de ses parents (nom, code, type, étiquettes, unité réelle du catalogue de modèles) : mots-clés
 * français, anglais et allemands, chacun pondéré par la proximité du texte (l’unité elle-même compte plus que sa
 * brigade). Sert aux qualifications suggérées d’un membre : une nature désigne des catégories du catalogue de
 * qualifications et se compare aussi aux unités citées par les emplois du catalogue d’emplois.
 */
final class UnitNatureDetector
{
    /** Score minimal pour retenir une nature. */
    public const MIN_SCORE = 0.35;

    /**
     * Natures : libellé (« courant dans les … »), motif sur le texte normalisé (JobCatalog::normalize), catégories du
     * catalogue de qualifications, pays suggéré (unité propre à un pays) ou ''.
     */
    public const NATURES = [
        'special_tactics' => ['label' => 'unités Special Tactics', 'pattern' => '/\b(special tactics|sts|stg|combat control\w*|pararescue|tacp|air commandos?)\b/u', 'categories' => ['air_support', 'sof', 'personnel_recovery', 'airborne'], 'country' => 'US'],
        'sof' => ['label' => 'unités des forces spéciales', 'pattern' => '/\b(special forces|forces speciales|special operations?|operations speciales|sof|socom|ussocom|jsoc|afsoc|usasoc|marsoc|nsw|sow|sos|cos|commandos?|rpima|cpa ?10|cpa ?30|gcp|ksk|kss|sas|sbs|sfsg|seals?|seal team|green berets?|rangers?|spetsnaz|ksso|pasukan khas|ggk|pasukan gerak khas)\b/u', 'categories' => ['sof', 'selection'], 'country' => ''],
        'airborne' => ['label' => 'unités aéroportées', 'pattern' => '/\b(airborne|aeroporte\w*|parachut\w*|paras?|fallschirm\w*|vdv|rpima|rcp|rep|pathfinders?|brigade parachutiste)\b/u', 'categories' => ['airborne'], 'country' => ''],
        'air_support' => ['label' => 'unités de contrôle aérien avancé', 'pattern' => '/\b(jtac|tacp|fac|forward air|appui aerien|close air support|asos|air support operations)\b/u', 'categories' => ['air_support'], 'country' => ''],
        'aviation' => ['label' => 'unités d’aviation', 'pattern' => '/\b(aviation|alat|helico\w*|helicopter\w*|soar|escadrille|aircrew|pilot\w*|hubschrauber\w*|rhc|rhfs|gam|night stalkers)\b/u', 'categories' => ['aviation'], 'country' => ''],
        'engineer' => ['label' => 'unités du génie', 'pattern' => '/\b(genie|engineers?|engineering|sappers?|sapeurs?|pionier\w*|eod|nedex|deminage|demining|demineurs?|combat engineers?|breachers?|explosi\w*|sprengs?\w*)\b/u', 'categories' => ['cbrn_eod'], 'country' => ''],
        'medical' => ['label' => 'unités de santé', 'pattern' => '/\b(sante|medical|medics?|medecins?|sanitaires?|infirmiers?|sanitat\w*|sanitaets\w*|pararescue|csar|evasan|medevac|cma)\b/u', 'categories' => ['medical', 'personnel_recovery'], 'country' => ''],
        'signals' => ['label' => 'unités de transmissions', 'pattern' => '/\b(transmissions?|signals?|sic|communications?|comms|fernmelde\w*|cyber\w*)\b/u', 'categories' => ['communications'], 'country' => ''],
        'intelligence' => ['label' => 'unités de renseignement', 'pattern' => '/\b(renseignement|intelligence|isr|reco|recon\w*|reconnaissance|surveillance|sigint|humint|aufklaer\w*|aufklar\w*|rdp)\b/u', 'categories' => ['intelligence'], 'country' => ''],
        'maritime' => ['label' => 'unités maritimes et de plongée', 'pattern' => '/\b(naval|navy|marine nationale|commandos? marine|plong\w*|divers?|dive|diving|combat swimmers?|kampfschwimmer\w*|boats?|sbt|swcc|nageurs?|amphib\w*|paskal)\b/u', 'categories' => ['maritime'], 'country' => ''],
        'fires' => ['label' => 'unités d’appui-feux', 'pattern' => '/\b(artillerie|artillery|fires|fire support|mortiers?|mortars?|jfo|batteries|batterie|battery|artiller\w*)\b/u', 'categories' => ['fires', 'air_support'], 'country' => ''],
        'cbrn' => ['label' => 'unités NRBC', 'pattern' => '/\b(nrbc|cbrn|abc abwehr\w*|rkhbz|chemical)\b/u', 'categories' => ['cbrn_eod'], 'country' => ''],
        'marksmanship' => ['label' => 'équipes de tireurs d’élite', 'pattern' => '/\b(snipers?|tireurs? d elite|tireurs?|scharfschu\w*|marksm\w*)\b/u', 'categories' => ['marksmanship'], 'country' => ''],
        'mountain' => ['label' => 'unités de montagne', 'pattern' => '/\b(montagne|mountain|alpin\w*|gebirgs\w*|arctic|arctique|grand froid)\b/u', 'categories' => ['environment'], 'country' => ''],
        'command' => ['label' => 'états-majors', 'pattern' => '/\b(etat major|headquarters|hq|qg|staff|stab)\b/u', 'categories' => ['c2_staff'], 'country' => ''],
    ];

    /** Poids d’un texte selon sa distance à l’unité du membre (0 : l’unité elle-même). */
    public static function weight(int $depth): float
    {
        return match (true) {
            $depth <= 0 => 1.0,
            $depth === 1 => 0.6,
            $depth === 2 => 0.45,
            default => 0.3,
        };
    }

    /**
     * Natures lues dans des textes pondérés : la meilleure occurrence compte, plus un peu pour chaque autre texte qui
     * la confirme (plafonné à 1).
     *
     * @param list<array{text: string, weight: float}> $texts
     * @return array<string, float> nature => score (0..1), du plus fort au plus faible, sous MIN_SCORE exclu
     */
    public static function detect(array $texts): array
    {
        $out = [];
        foreach (self::NATURES as $key => $nature) {
            $best = 0.0;
            $extra = 0.0;
            foreach ($texts as $t) {
                $norm = JobCatalog::normalize((string) ($t['text'] ?? ''));
                if ($norm === '' || preg_match($nature['pattern'], $norm) !== 1) {
                    continue;
                }
                $w = max(0.0, min(1.0, (float) ($t['weight'] ?? 1.0)));
                if ($w > $best) {
                    $extra += $best * 0.25;
                    $best = $w;
                } else {
                    $extra += $w * 0.25;
                }
            }
            $score = min(1.0, $best + $extra);
            if ($score >= self::MIN_SCORE) {
                $out[$key] = round($score, 2);
            }
        }
        arsort($out);

        return $out;
    }

    public static function matches(string $nature, string $text): bool
    {
        $pattern = self::NATURES[$nature]['pattern'] ?? null;

        return $pattern !== null && preg_match($pattern, JobCatalog::normalize($text)) === 1;
    }

    public static function label(string $nature): string
    {
        return self::NATURES[$nature]['label'] ?? '';
    }

    /** @return list<string> */
    public static function categories(string $nature): array
    {
        return self::NATURES[$nature]['categories'] ?? [];
    }

    /** Pays propre à la nature (« Special Tactics » : États-Unis), '' sinon. */
    public static function country(string $nature): string
    {
        return self::NATURES[$nature]['country'] ?? '';
    }
}
