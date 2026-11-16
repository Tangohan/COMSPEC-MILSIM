<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Repositories\SseSheetRepository;
use App\Support\SseRedactionExplain;
use App\Support\SseUsClassification;

/**
 * Lignes de « Documents émis » prêtes à afficher : explication du caviardage (catégories, niveaux, nombre de passages), niveau de
 * diffusion en pastille, réémission possible ou niveau à demander, lien entre un document et sa réémission. Ne porte aucune valeur
 * masquée : seulement des catégories, des niveaux et des nombres.
 */
final class SseSheetIssueView
{
    /**
     * @param list<array<string, mixed>> $issues
     * @return list<array<string, mixed>>
     */
    public static function rows(array $issues, SseSheetAccess $access): array
    {
        $replacedBy = [];
        foreach ($issues as $i) {
            $of = (int) ($i['snapshot']['reissue']['of'] ?? 0);
            if ($of > 0) {
                $replacedBy[$of] ??= (string) $i['ref'];
            }
        }
        $ready = SseSheetRepository::ready();
        $levels = new SseSheetLevelService();
        $out = [];
        foreach ($issues as $i) {
            $level = SseUsClassification::normalize((string) $i['classification']);
            $x = SseRedactionExplain::forIssue($i);
            $open = SseRedactionService::levelRank($level) <= SseRedactionService::levelRank($access->level());
            $req = is_array($i['snapshot']['data']['visibility'] ?? null) ? SseSheetVisibility::effective($i['snapshot']['data']['visibility']) : null;
            $needed = SseSheetReissueService::levelNeeded($x['by'], $req);
            $above = SseSheetReissueService::levelsAbove($level, $access);
            $can = $ready && $x['total'] > 0 && $open && $i['model'] !== 'notice' && $access->canDraft() && $access->denial((string) $i['model']) === null && $above !== [];
            $rgb = SseUsClassification::rgb($level);
            $out[] = $i + [
                'x' => $x, 'level' => $level, 'abbr' => SseUsClassification::abbr($level), 'rgb' => $rgb,
                'ink' => (0.299 * $rgb[0] + 0.587 * $rgb[1] + 0.114 * $rgb[2]) > 150 ? '#000000' : '#ffffff',
                'open' => $open, 'can_reissue' => $can, 'levels_above' => $above, 'needed' => $needed,
                'needed_abbr' => $needed !== null ? SseUsClassification::abbr($needed) : '', 'req' => $req,
                'level_menu' => $ready && $open && $access->canDraft() ? $levels->options($i, $access) : [],
                'replaced_by' => $replacedBy[(int) $i['id']] ?? '', 'reissue_of' => (string) ($i['snapshot']['reissue']['ref'] ?? ''),
            ];
        }

        return $out;
    }
}
