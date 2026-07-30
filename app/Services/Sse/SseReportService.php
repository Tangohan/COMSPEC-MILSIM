<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Repositories\SseCaseRepository;
use App\Repositories\SsePersonRepository;
use App\Repositories\SseSiteRepository;

/**
 * Comptes rendus SSE.
 *
 * Un compte rendu d'exploitation n'est pas un inventaire : il restitue ce qui a été
 * exploité et ce que cela apporte à la mission. Le générateur cinq lignes des sites
 * reste un procès-verbal de saisie, utile à la clôture d'un site ; celui-ci travaille
 * à l'échelle du dossier et produit un document d'échelon.
 *
 * Deux produits :
 *   - FLASH   — quelques lignes, envoyées dès une découverte marquante
 *   - INITIAL — le compte rendu structuré, alimenté par les événements enregistrés
 *
 * Les intitulés sont en français métier, conformément à la règle du produit : aucun
 * code technique ni valeur brute de base de données à l'écran.
 */
final class SseReportService
{
    public function __construct(
        private ?SseCaseRepository $cases = null,
        private ?SsePersonRepository $persons = null,
        private ?SseSiteRepository $sites = null,
        private ?SseCrossMatchService $cross = null,
    ) {
        $this->cases ??= new SseCaseRepository();
        $this->persons ??= new SsePersonRepository();
        $this->sites ??= new SseSiteRepository();
        $this->cross ??= new SseCrossMatchService();
    }

    /**
     * Rassemble tout ce qu'un dossier porte : personnes, sites, saisies, verdicts.
     *
     * @return array<string, mixed>|null
     */
    public function gather(int $caseId, int $tenantId): ?array
    {
        $case = $this->cases->findById($caseId, $tenantId);
        if ($case === null) {
            return null;
        }

        $people = [];
        foreach ($this->cases->listLinkedPersonIds($caseId, $tenantId) as $link) {
            $person = $this->persons->findById((int) ($link['person_id'] ?? 0), $tenantId);
            if ($person === null) {
                continue;
            }
            $person['biometric_samples'] = $this->persons->listBiometricSamples((int) $person['id'], $tenantId);
            $person['watchlist'] = $this->cross->matchOne($person, $tenantId);
            $people[] = $person;
        }

        $sites = $this->sites->listForCase($caseId, $tenantId);
        foreach ($sites as $i => $site) {
            $sites[$i]['rooms'] = $this->sites->listRooms((int) $site['id'], $tenantId);
            $sites[$i]['seizures'] = $this->sites->listSeizures((int) $site['id'], $tenantId);
        }

        return ['case' => $case, 'people' => $people, 'sites' => $sites];
    }

    /**
     * Compte rendu initial d'exploitation, à l'échelle du dossier.
     */
    public function buildInitialReport(int $caseId, int $tenantId): string
    {
        $data = $this->gather($caseId, $tenantId);
        if ($data === null) {
            return '';
        }
        ['case' => $case, 'people' => $people, 'sites' => $sites] = $data;

        $lines = [];
        $lines[] = sprintf('COMPTE RENDU INITIAL SSE // %s', mb_strtoupper((string) ($case['title'] ?? 'SANS OBJET')));
        $lines[] = sprintf('RÉFÉRENCE : %s', (string) ($case['reference_code'] ?? ''));
        $lines[] = sprintf('DTG : %s', $this->dtg((string) ($case['created_at'] ?? '')));
        $lines[] = sprintf('GRILLE : %s', $this->firstGrid($sites, $people));
        $lines[] = '';

        // --- SITUATION ---
        $lines[] = 'SITUATION';
        $lines[] = $this->situation($case, $sites, $people);
        $lines[] = '';

        // --- EXPLOITATION DU SITE ---
        $lines[] = 'EXPLOITATION DU SITE';
        if ($sites === []) {
            $lines[] = 'Aucun site ouvert sur ce dossier.';
        } else {
            foreach ($sites as $site) {
                $rooms = is_array($site['rooms'] ?? null) ? $site['rooms'] : [];
                $done = count(array_filter($rooms, static fn (array $r): bool => !empty($r['checked'])));
                $lines[] = sprintf(
                    '%s — %s : %d pièce(s) fouillée(s) sur %d.%s',
                    (string) ($site['reference_code'] ?? ''),
                    (string) ($site['site_type_label'] ?? ''),
                    $done,
                    count($rooms),
                    ($site['status'] ?? '') === SseSiteRepository::STATUS_CLOSED ? ' Exploitation close.' : ' Exploitation en cours.'
                );
                $pending = array_values(array_filter(
                    $rooms,
                    static fn (array $r): bool => empty($r['checked'])
                ));
                if ($pending !== []) {
                    $labels = array_map(static fn (array $r): string => (string) ($r['label'] ?? ''), $pending);
                    $lines[] = sprintf('  Non traité : %s.', implode(', ', array_slice($labels, 0, 6)));
                }
            }
        }
        $lines[] = '';

        // --- PERSONNEL ---
        $lines[] = 'PERSONNEL';
        if ($people === []) {
            $lines[] = 'Aucune personne rattachée au dossier.';
        } else {
            $lines[] = sprintf('%s personne(s) exploitée(s).', $this->twoDigits(count($people)));
            foreach ($people as $i => $person) {
                $lines[] = '  ' . $this->personLine($i + 1, $person);
            }
        }
        $lines[] = '';

        // --- MATÉRIEL ---
        $lines[] = 'MATÉRIEL';
        $material = $this->materialTotals($sites);
        if ($material === []) {
            $lines[] = 'Aucune saisie versée.';
        } else {
            foreach ($material as $label => $qty) {
                $lines[] = sprintf('  %s %s.', $this->twoDigits($qty), $label);
            }
        }
        $lines[] = '';

        // --- FAITS MARQUANTS ---
        $lines[] = 'FAITS MARQUANTS';
        $findings = $this->keyFindings($people, $sites);
        if ($findings === []) {
            $lines[] = 'Aucun élément saillant à ce stade.';
        } else {
            foreach ($findings as $f) {
                $lines[] = '  ' . $f;
            }
        }
        $lines[] = '';

        // --- APPRÉCIATION ---
        $lines[] = 'APPRÉCIATION';
        $lines[] = $this->assessment($people, $sites);
        $lines[] = '';

        // --- SUITES À DONNER ---
        $lines[] = 'SUITES À DONNER';
        $follow = $this->followOn($people, $sites);
        if ($follow === []) {
            $lines[] = 'Aucune suite identifiée.';
        } else {
            foreach ($follow as $f) {
                $lines[] = '  ' . $f;
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Compte rendu flash : ce qui justifie d'interrompre le TOC, rien de plus.
     */
    public function buildFlashReport(int $caseId, int $tenantId): string
    {
        $data = $this->gather($caseId, $tenantId);
        if ($data === null) {
            return '';
        }
        ['case' => $case, 'people' => $people, 'sites' => $sites] = $data;

        $confirmed = 0;
        $watchlist = 0;
        foreach ($people as $p) {
            if ((string) (($p['identity_query']['result'] ?? '')) === 'confirmed') {
                $confirmed++;
            }
            if (is_array($p['watchlist'] ?? null) && $p['watchlist'] !== []) {
                $watchlist++;
            }
        }
        $seizures = 0;
        foreach ($sites as $s) {
            $seizures += count(is_array($s['seizures'] ?? null) ? $s['seizures'] : []);
        }

        $lines = [
            sprintf('FLASH SSE // %s', (string) ($case['reference_code'] ?? '')),
            sprintf('SITE(S) : %s', $this->twoDigits(count($sites))),
            sprintf('SUJETS : %s', $this->twoDigits(count($people))),
            sprintf('CORRESPONDANCES CONFIRMÉES : %s', $this->twoDigits($confirmed)),
            sprintf('SIGNALEMENTS SURVEILLANCE : %s', $this->twoDigits($watchlist)),
            sprintf('SAISIES : %s', $this->twoDigits($seizures)),
            sprintf('ÉTAT : %s', $this->exploitationState($sites)),
        ];

        return implode("\n", $lines);
    }

    // ------------------------------------------------------------------
    // Rédaction
    // ------------------------------------------------------------------

    /**
     * @param array<string, mixed> $case
     * @param list<array<string, mixed>> $sites
     * @param list<array<string, mixed>> $people
     */
    private function situation(array $case, array $sites, array $people): string
    {
        $opened = $this->dtg((string) ($case['created_at'] ?? ''));
        $parts = [sprintf('Dossier ouvert le %s.', $opened)];
        if ($sites !== []) {
            $parts[] = sprintf(
                '%s site(s) exploité(s), %s personne(s) enrôlée(s).',
                $this->twoDigits(count($sites)),
                $this->twoDigits(count($people))
            );
        } else {
            $parts[] = sprintf('%s personne(s) enrôlée(s), hors exploitation de site.', $this->twoDigits(count($people)));
        }

        return implode(' ', $parts);
    }

    /**
     * @param array<string, mixed> $person
     */
    private function personLine(int $index, array $person): string
    {
        $ref = sprintf('P%02d', $index);
        $name = trim((string) ($person['display_name'] ?? '')) ?: 'identité non établie';

        $samples = is_array($person['biometric_samples'] ?? null) ? $person['biometric_samples'] : [];
        $bio = $samples === []
            ? 'aucun relevé biométrique'
            : sprintf('relevés %s', implode(' / ', array_map(
                static fn (array $s): string => mb_strtolower((string) ($s['kind_label'] ?? '')),
                $samples
            )));

        $query = is_array($person['identity_query'] ?? null) ? $person['identity_query'] : [];
        $verdict = match ((string) ($query['result'] ?? '')) {
            'confirmed' => sprintf('CORRESPONDANCE CONFIRMÉE (%s %%)', $query['confidence'] ?? '—'),
            'possible' => sprintf('correspondance possible (%s %%)', $query['confidence'] ?? '—'),
            'none' => 'aucune correspondance',
            default => 'identité en attente d’exploitation',
        };

        $line = sprintf('%s — %s — %s — %s.', $ref, $name, $bio, $verdict);

        $hits = is_array($person['watchlist'] ?? null) ? $person['watchlist'] : [];
        if ($hits !== []) {
            $line .= sprintf(' Rapprochement liste de surveillance : %d piste(s).', count($hits));
        }

        return $line;
    }

    /**
     * @param list<array<string, mixed>> $sites
     * @return array<string, int>
     */
    private function materialTotals(array $sites): array
    {
        $totals = [];
        foreach ($sites as $site) {
            foreach ((is_array($site['seizures'] ?? null) ? $site['seizures'] : []) as $s) {
                $label = mb_strtolower((string) ($s['category_label'] ?? 'autre'));
                $totals[$label] = ($totals[$label] ?? 0) + max(1, (int) ($s['quantity'] ?? 1));
            }
        }
        arsort($totals);

        return $totals;
    }

    /**
     * @param list<array<string, mixed>> $people
     * @param list<array<string, mixed>> $sites
     * @return list<string>
     */
    private function keyFindings(array $people, array $sites): array
    {
        $out = [];
        foreach ($people as $i => $p) {
            $ref = sprintf('P%02d', $i + 1);
            if ((string) (($p['identity_query']['result'] ?? '')) === 'confirmed') {
                $out[] = sprintf(
                    '%s identifié avec un niveau de confiance élevé (dossier %s).',
                    $ref,
                    (string) ($p['identity_query']['record_ref'] ?? 'non référencé')
                );
            }
            $hits = is_array($p['watchlist'] ?? null) ? $p['watchlist'] : [];
            if ($hits !== []) {
                $top = $hits[0];
                $out[] = sprintf(
                    '%s rapproché d’une entrée surveillée (score %d %%, %s).',
                    $ref,
                    (int) ($top['score'] ?? 0),
                    (string) ($top['reason'] ?? '')
                );
            }
        }

        foreach ($sites as $site) {
            $seizures = is_array($site['seizures'] ?? null) ? $site['seizures'] : [];
            foreach ($seizures as $s) {
                if (in_array((string) ($s['category'] ?? ''), ['document', 'numerique'], true)) {
                    $out[] = sprintf(
                        '%s : %s recueilli sur le site — exploitation différée nécessaire.',
                        (string) ($site['reference_code'] ?? ''),
                        (string) ($s['label'] ?? 'support')
                    );
                }
            }
        }

        return array_slice($out, 0, 8);
    }

    /**
     * @param list<array<string, mixed>> $people
     * @param list<array<string, mixed>> $sites
     */
    private function assessment(array $people, array $sites): string
    {
        $confirmed = 0;
        $pending = 0;
        foreach ($people as $p) {
            $r = (string) (($p['identity_query']['result'] ?? ''));
            if ($r === 'confirmed') {
                $confirmed++;
            }
            if ($r === '' || $r === 'possible') {
                $pending++;
            }
        }

        if ($confirmed > 0) {
            return 'Les éléments recueillis présentent un intérêt de renseignement avéré et justifient une exploitation approfondie.';
        }
        if ($pending > 0 || $sites !== []) {
            return 'Les éléments recueillis présentent un intérêt potentiel ; une exploitation complémentaire est nécessaire pour conclure.';
        }

        return 'Aucun élément d’intérêt à ce stade.';
    }

    /**
     * @param list<array<string, mixed>> $people
     * @param list<array<string, mixed>> $sites
     * @return list<string>
     */
    private function followOn(array $people, array $sites): array
    {
        $out = [];
        foreach ($people as $i => $p) {
            $ref = sprintf('P%02d', $i + 1);
            $samples = is_array($p['biometric_samples'] ?? null) ? $p['biometric_samples'] : [];
            if ($samples === []) {
                $out[] = sprintf('Compléter le relevé biométrique de %s.', $ref);
            }
            if (!is_array($p['identity_query'] ?? null) || ($p['identity_query'] ?? []) === []) {
                $out[] = sprintf('Exploiter l’identité de %s.', $ref);
            }
        }

        foreach ($sites as $site) {
            $rooms = is_array($site['rooms'] ?? null) ? $site['rooms'] : [];
            $pending = array_filter($rooms, static fn (array $r): bool => empty($r['checked']));
            if ($pending !== []) {
                $out[] = sprintf(
                    'Achever la fouille de %s (%d pièce(s) non traitée(s)).',
                    (string) ($site['reference_code'] ?? ''),
                    count($pending)
                );
            }
            foreach ((is_array($site['seizures'] ?? null) ? $site['seizures'] : []) as $s) {
                if ((string) ($s['category'] ?? '') === 'numerique') {
                    $out[] = 'Demander l’exploitation numérique des supports recueillis.';
                    break;
                }
            }
        }

        return array_values(array_unique(array_slice($out, 0, 10)));
    }

    /**
     * @param list<array<string, mixed>> $sites
     */
    private function exploitationState(array $sites): string
    {
        if ($sites === []) {
            return 'AUCUNE EXPLOITATION DE SITE';
        }
        foreach ($sites as $s) {
            if ((string) ($s['status'] ?? '') !== SseSiteRepository::STATUS_CLOSED) {
                return 'EXPLOITATION EN COURS';
            }
        }

        return 'EXPLOITATION CLOSE';
    }

    /**
     * Groupe date-heure militaire : 301445Z JUL 26.
     */
    private function dtg(string $sqlDate): string
    {
        $ts = $sqlDate !== '' ? strtotime($sqlDate) : false;
        if ($ts === false) {
            $ts = time();
        }
        $months = ['JAN', 'FEB', 'MAR', 'AVR', 'MAI', 'JUN', 'JUL', 'AOU', 'SEP', 'OCT', 'NOV', 'DEC'];

        return sprintf(
            '%s%sZ %s %s',
            gmdate('d', $ts),
            gmdate('Hi', $ts),
            $months[(int) gmdate('n', $ts) - 1],
            gmdate('y', $ts)
        );
    }

    /**
     * @param list<array<string, mixed>> $sites
     * @param list<array<string, mixed>> $people
     */
    private function firstGrid(array $sites, array $people): string
    {
        foreach ($sites as $s) {
            if (!empty($s['grid_reference'])) {
                return (string) $s['grid_reference'];
            }
        }
        foreach ($people as $p) {
            if (!empty($p['grid_reference'])) {
                return (string) $p['grid_reference'];
            }
        }

        return 'non relevée';
    }

    private function twoDigits(int $n): string
    {
        return str_pad((string) max(0, $n), 2, '0', STR_PAD_LEFT);
    }
}
