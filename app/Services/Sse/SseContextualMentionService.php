<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Support\SseAnalyticalCatalog;

/**
 * Générateur contextuel de mentions : détecte la situation du dossier
 * et propose les textes / codes de bibliothèque adaptés.
 */
final class SseContextualMentionService
{
    /**
     * @param array<string,mixed> $case
     * @param list<array<string,mixed>> $people
     * @param list<array<string,mixed>> $assessments
     * @param list<array<string,mixed>> $gaps
     * @param list<array<string,mixed>> $links
     * @param list<array<string,mixed>> $libraryEntries  entries with code/title/content
     * @return list<array{code:string,title:string,reason:string,severity:string,urgency:string}>
     */
    public function suggestForCase(
        array $case,
        array $people,
        array $assessments,
        array $gaps,
        array $links,
        array $libraryEntries = [],
        string $documentBody = '',
        string $documentType = ''
    ): array {
        $byCode = [];
        foreach ($libraryEntries as $row) {
            $code = (string) ($row['code'] ?? '');
            if ($code !== '') {
                $byCode[$code] = $row;
            }
        }

        $hits = [];

        $uncertainPeople = 0;
        foreach ($people as $p) {
            $status = strtolower((string) ($p['identity_status'] ?? $p['status'] ?? ''));
            $name = trim((string) ($p['full_name'] ?? $p['display_name'] ?? ''));
            if ($name === '' || str_contains($status, 'inconnu') || str_contains($status, 'suppos')
                || str_contains($status, 'provis')) {
                $uncertainPeople++;
            }
        }
        if ($uncertainPeople > 0 || preg_match('/identit[ée]\s+(non\s+confirm|incertaine|suppos)/iu', $documentBody)) {
            $hits[] = $this->hit('PERS-01', 'Identité à confirmer', 'Personne sans identité certaine', 'high', $byCode);
            $hits[] = $this->hit('ALERT-01', 'Identité non consolidée', 'Personne sans identité certaine', 'high', $byCode);
        }

        $singleSource = false;
        foreach ($assessments as $a) {
            if (($a['status'] ?? '') !== 'active') {
                continue;
            }
            $corr = trim((string) ($a['corroboration_text'] ?? ''));
            if ($corr === '' || preg_match('/source\s+unique|aucun\s+recoup/iu', $corr . ' ' . ($a['assessment_text'] ?? ''))) {
                $singleSource = true;
            }
            if (!empty($a['divergence_code'])) {
                $hits[] = $this->hit('EXP-04', 'Contradiction non résolue', 'Divergence signalée sur une appréciation', 'high', $byCode);
                $hits[] = $this->hit('ALERT-04', 'Contradiction non résolue', 'Divergence signalée sur une appréciation', 'high', $byCode);
            }
            if (($a['confidence'] ?? '') === 'faible') {
                $hits[] = $this->hit('METH-05', 'Degré de confiance', 'Confiance faible — justification requise', 'medium', $byCode);
                $hits[] = $this->hit('FRAG-03', 'Sous réserve', 'Confiance faible', 'medium', $byCode);
            }
        }
        if ($singleSource || preg_match('/source\s+unique/iu', $documentBody)) {
            $hits[] = $this->hit('SRC-02', 'Source unique — recoupement requis', 'Une seule source', 'high', $byCode);
            $hits[] = $this->hit('ALERT-02', 'Source unique', 'Une seule source', 'high', $byCode);
        }

        foreach ($gaps as $g) {
            if (!in_array(($g['status'] ?? ''), ['ouvert', 'en_cours'], true)) {
                continue;
            }
            if (($g['kind'] ?? '') === 'lacune') {
                $hits[] = $this->hit('NOTE-04', 'Absence de renseignement', 'Lacune ouverte au dossier', 'medium', $byCode);
                $hits[] = $this->hit('CHRON-04', 'Lacune assumée', 'Lacune ouverte au dossier', 'low', $byCode);
            }
            if (($g['kind'] ?? '') === 'besoin' && in_array(($g['priority'] ?? ''), ['prioritaire', 'critique'], true)) {
                $hits[] = $this->hit('COORD-02', 'Demande de recherche', 'Besoin prioritaire ouvert', 'high', $byCode);
                $hits[] = $this->hit('URG-01', 'Exploitation prioritaire', 'Besoin prioritaire ouvert', 'high', $byCode);
            }
        }

        foreach ($links as $l) {
            $type = (string) ($l['relation_type'] ?? '');
            if ($type === 'derive' || $type === 'source') {
                $hits[] = $this->hit('PIECE-03', 'Élément dérivé', 'Pièce ou dossier provenant d’un autre dossier', 'medium', $byCode);
            }
            if ($type === 'doublon_potentiel') {
                $hits[] = $this->hit('DECON-01', 'Déconfliction', 'Individu ou site déjà suivi ailleurs', 'high', $byCode);
            }
        }

        $updatedAt = strtotime((string) ($case['updated_at'] ?? $case['created_at'] ?? '')) ?: 0;
        if ($updatedAt > 0 && (time() - $updatedAt) > 14 * 86400) {
            $hits[] = $this->hit('ALERT-09', 'Réexamen analytique requis', 'Dossier sans activité depuis plus de 14 jours', 'medium', $byCode);
            $hits[] = $this->hit('NOTE-02', 'État de l’analyse', 'Réexamen analytique requis', 'low', $byCode);
        }

        if (preg_match('/contradict|diverg|incoh[ée]ren/iu', $documentBody)) {
            $hits[] = $this->hit('EXP-04', 'Contradiction non résolue', 'Texte du document', 'high', $byCode);
        }
        if (preg_match('/hypoth[èe]se/iu', $documentBody)) {
            $hits[] = $this->hit('RENS-04', 'Hypothèse analytique', 'Texte du document', 'medium', $byCode);
            $hits[] = $this->hit('METH-02', 'Hypothèses concurrentes', 'Texte du document', 'medium', $byCode);
        }

        $typeDefaults = match ($documentType) {
            'flash' => [['EXP-01', 'Exploitation en cours'], ['ALERT-03', 'Non recoupé'], ['FRAG-01', 'À ce stade']],
            'note_analyse' => [['NOTE-02', 'État de l’analyse'], ['RENS-04', 'Hypothèse'], ['METH-01', 'Limites']],
            'synthese' => [['METH-01', 'Limites'], ['NOTE-04', 'Lacunes'], ['FRAG-04', 'Plusieurs éléments concordants']],
            'diffusion' => [['DIFF-02', 'Version de diffusion'], ['CAV-01', 'Caviardage'], ['DIFF-03', 'Reconstitution interdite']],
            default => [],
        };
        foreach ($typeDefaults as [$code, $title]) {
            $hits[] = $this->hit($code, $title, 'Type de document', 'low', $byCode);
        }

        // Déduplication en conservant la plus haute urgence.
        $rank = ['high' => 3, 'medium' => 2, 'low' => 1];
        $best = [];
        foreach ($hits as $hit) {
            if ($hit === null) {
                continue;
            }
            $code = $hit['code'];
            if (!isset($best[$code]) || ($rank[$hit['urgency']] ?? 0) > ($rank[$best[$code]['urgency']] ?? 0)) {
                $best[$code] = $hit;
            }
        }

        usort($best, static function (array $a, array $b) use ($rank): int {
            return ($rank[$b['urgency']] ?? 0) <=> ($rank[$a['urgency']] ?? 0);
        });

        return array_values(array_slice($best, 0, 8));
    }

    /**
     * @param array<string,array<string,mixed>> $byCode
     * @return array{code:string,title:string,reason:string,severity:string,urgency:string}|null
     */
    private function hit(string $code, string $fallbackTitle, string $reason, string $urgency, array $byCode): ?array
    {
        $row = $byCode[$code] ?? null;
        $title = $row ? (string) ($row['title'] ?? $fallbackTitle) : $fallbackTitle;
        $content = $row ? (string) ($row['content'] ?? '') : '';

        return [
            'code' => $code,
            'title' => $title,
            'reason' => $reason,
            'urgency' => $urgency,
            'snippet' => $content !== ''
                ? mb_substr(preg_replace('/\s+/u', ' ', $content) ?? '', 0, 160)
                : $fallbackTitle,
        ];
    }

    /** @return list<array{code:string,label:string,content:string}> */
    public static function presetGapMentions(): array
    {
        return [
            [
                'code' => 'GAP-LACUNE',
                'label' => 'Lacune type',
                'content' => 'LACUNE IDENTIFIÉE — Aucun élément ne permet actuellement d’identifier [ÉLÉMENT MANQUANT].',
            ],
            [
                'code' => 'GAP-BESOIN',
                'label' => 'Besoin prioritaire',
                'content' => 'BESOIN PRIORITAIRE — Déterminer si [ÉLÉMENT] constitue un moyen permanent du réseau ou un moyen occasionnel.',
            ],
            [
                'code' => 'GAP-CRITERE',
                'label' => 'Critère de confirmation',
                'content' => 'CRITÈRE DE CONFIRMATION — L’hypothèse sera considérée comme consolidée après obtention d’un second élément indépendant établissant le rattachement.',
            ],
        ];
    }

    /** Variables conditionnelles simples selon classification / confiance. */
    public static function resolveConditionalPhrase(string $template, array $ctx): string
    {
        // {{si.classification.secret:TEXTE}} / {{si.confiance.faible:TEXTE}}
        return (string) preg_replace_callback(
            '/\{\{si\.([a-z_]+)\.([a-z0-9_]+):([^}]+)\}\}/u',
            static function (array $m) use ($ctx): string {
                $axis = $m[1];
                $expect = $m[2];
                $text = $m[3];
                $actual = strtolower((string) ($ctx[$axis] ?? ''));

                return $actual === strtolower($expect) ? $text : '';
            },
            $template
        );
    }
}
