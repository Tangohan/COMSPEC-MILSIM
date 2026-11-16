<?php

declare(strict_types=1);

namespace App\Services\Sse\Detection;

use App\Services\Intel\IntelIntakeAdvisor;
use App\Services\Intel\IntelIntakePresenter;
use App\Support\SseFieldNoteCatalog;

/**
 * L'ANCIENNE lecture des remontées (jusqu'au lexique 2026-11-16), gardée pour la seule re-détection (tools/sse-redetect.php) :
 * elle dit quelles propositions une remontée en attente affichait avant, pour retirer avec une trace celles que le nouveau
 * moteur ne justifie plus. Correspondance par SOUS-CHAÎNE : « otage » était trouvé dans « sabotage ». Ne sert plus à
 * proposer quoi que ce soit.
 */
final class SseLegacyKeywordDetector
{
    private const KIND_WORDS = [
        'FRT' => ['vehicule', 'pick-up', 'pickup', 'camion', 'arme', 'munition', 'lance-roquette', 'materiel', 'immatricul', 'plaque', 'marquage', 'antenne', 'engin', 'caisse', 'drone'],
        'FRC' => ['entretien', 'discute', 'discussion', 'rencontre', 'chef de village', 'informateur', 'interlocuteur', 'nous a dit', 'a declare', 'temoin'],
        'FRA' => ['population', 'ambiance', 'tension', 'hostile', 'accueil', 'habitants', 'rumeur', 'climat', 'manifestation'],
    ];

    private const THEME_WORDS = [
        'TERROR' => ['attentat', 'engin explosif', 'ied', 'piege', 'kamikaze', 'otage'],
        'CBRNE' => ['chimique', 'radiolog', 'nrbc', 'gaz', 'biologique'],
        'ARMEMENT' => ['arme', 'munition', 'lance-roquette', 'fusil', 'mortier', 'explosif', 'grenade'],
        'PERSON' => ['individu', 'homme arme', 'hommes armes', 'chef', 'suspect', 'cible'],
        'LOGIST' => ['depot', 'caisse', 'carburant', 'ravitaillement', 'stock', 'entrepos', 'hangar', 'convoi'],
        'COMMS' => ['radio', 'antenne', 'frequence', 'telephone', 'relais'],
        'INFRA' => ['pont', 'route', 'batiment', 'centrale', 'barrage', 'piste'],
        'MOUV' => ['deplacement', 'convoi', 'colonne', 'se dirige', 'patrouille', 'franchi'],
        'SECUR' => ['garde', 'surveill', 'sentinelle', 'checkpoint', 'barrage routier', 'poste de controle'],
        'CIVIL' => ['population', 'habitants', 'civil', 'village', 'marche'],
    ];

    private const URGENT_WORDS = [
        'critical' => ['imminent', 'en cours', 'tir nourri', 'embuscade', 'otage', 'attentat'],
        'high' => ['engin explosif', 'ied', 'colis suspect', 'blesse', 'hommes armes', 'homme arme', 'arme', 'menace', 'explosif'],
    ];

    /**
     * Ce que l'ancienne lecture proposait : liste de « cible, valeur, mots ».
     *
     * @param array<string, mixed> $item remontée lue par IntelIntakeService::find
     * @return list<array{target: string, value: string, words: list<string>}>
     */
    public static function proposals(array $item): array
    {
        $source = (string) ($item['source'] ?? '');
        $row = is_array($item['row'] ?? null) ? $item['row'] : [];
        $parts = [];
        foreach (['title', 'body', 'summary', 'details', 'remarks', 'place_label', 'location_description'] as $col) {
            $parts[] = (string) ($row[$col] ?? '');
        }
        $text = IntelIntakeAdvisor::fold(implode(' ', $parts));
        $hits = static fn (array $words): array => array_values(array_filter($words, static fn (string $w): bool => str_contains($text, $w)));
        $out = [];
        if ($source === 'fiche') {
            $best = '';
            $bestHits = [];
            foreach (self::KIND_WORDS as $kind => $words) {
                $found = $hits($words);
                if (count($found) > count($bestHits)) {
                    $best = $kind;
                    $bestHits = $found;
                }
            }
            if ($best !== '' && count($bestHits) >= 2 && $best !== (string) ($item['type'] ?? '')) {
                $out[] = ['target' => 'kind', 'value' => $best, 'words' => $bestHits];
            }
            $current = SseFieldNoteCatalog::normalizeThemes($row['themes'] ?? '');
            $room = SseFieldNoteCatalog::THEMES_MAX - count($current);
            foreach (self::THEME_WORDS as $code => $words) {
                $found = $hits($words);
                if ($found !== [] && !in_array($code, $current, true) && $room > 0) {
                    $out[] = ['target' => 'theme', 'value' => $code, 'words' => $found];
                    $room--;
                }
            }
        }
        $level = IntelIntakePresenter::urgencyLevel((string) ($item['priority'] ?? ''));
        $rank = ['low' => 0, 'medium' => 1, 'high' => 2, 'critical' => 3];
        foreach (self::URGENT_WORDS as $wanted => $words) {
            $found = $hits($words);
            if ($found !== [] && $rank[$wanted] > $rank[$level]) {
                $out[] = ['target' => 'urgency', 'value' => $wanted === 'critical' ? 'critique' : 'urgent', 'words' => $found];
                break;
            }
        }

        return $out;
    }
}
