<?php

declare(strict_types=1);

namespace App\Services\Effectifs;

/**
 * Chronologie unique d'un dossier membre, en lecture seule.
 *
 * Fusionne des historiques que la fiche charge déjà (grades, organisation, service, bilans, qualifications,
 * sanctions, absences, élévations, mobilité, documents RH, départ) avec les décorations attribuées. Elle n'écrit rien
 * et ne relit rien en base : l'appelant ne fournit que des sources que le lecteur a déjà le droit de voir sur la fiche
 * (une source absente ou vide est simplement ignorée).
 */
final class MemberChronology
{
    /** Types filtrables, dans l'ordre d'affichage du filtre. */
    public const TYPES = [
        'grade' => 'Grades',
        'organisation' => 'Organisation',
        'service' => 'Service',
        'bilan' => 'Bilans',
        'qualification' => 'Qualifications',
        'decoration' => 'Décorations',
        'elevation' => 'Élévations',
        'sanction' => 'Sanctions',
        'absence' => 'Absences',
        'mobilite' => 'Mobilité',
        'document' => 'Documents RH',
        'depart' => 'Départs',
    ];

    /** Carte de la fiche qui détaille chaque type (ancre conservée de la fiche d'origine). */
    private const ANCHORS = [
        'grade' => 'grade',
        'organisation' => 'unite',
        'service' => 'historique-rh',
        'bilan' => 'historique-rh',
        'qualification' => 'qualifications',
        'decoration' => 'distinctions',
        'elevation' => 'elevation',
        'sanction' => 'sanctions',
        'absence' => 'absences',
        'mobilite' => 'mobilite',
        'document' => 'documents-rh',
        'depart' => 'depart',
    ];

    /**
     * Une ligne du journal de service reprend souvent un fait déjà fourni par sa source propre (promotion = grade,
     * décoration = distinction…) : elle est écartée quand cette source a un événement le même jour.
     */
    private const SERVICE_DUPLICATES = [
        'promotion' => 'grade',
        'award' => 'decoration',
        'qualification' => 'qualification',
        'discipline' => 'sanction',
    ];

    private const SERVICE_TYPE_LABELS = [
        'assignment' => 'Affectation',
        'promotion' => 'Promotion',
        'qualification' => 'Qualification',
        'deployment' => 'Déploiement',
        'award' => 'Décoration',
        'discipline' => 'Discipline',
        'note' => 'Note',
    ];

    private const MOBILITY_STATUS = ['pending' => 'En attente', 'approved' => 'Acceptée', 'rejected' => 'Refusée', 'cancelled' => 'Annulée', 'applied' => 'Appliquée'];

    private const ELEVATION_STATUS = ['pending' => 'En attente', 'in_review' => 'En cours d’examen', 'approved' => 'Acceptée', 'rejected' => 'Refusée'];

    /**
     * @param array<string, mixed> $sources clés : grades, org_history, service_history, stage_bilans, qualifications,
     *        awards, elevations, sanctions, absences, mobility, hr_documents (listes de lignes), departure (une ligne
     *        ou null) et les libellés absence_reasons, mobility_types, document_types, departure_reasons
     * @return list<array{date: string, type: string, type_label: string, title: string, detail: string, anchor: string, stored: bool}>
     */
    public static function build(array $sources, int $limit = 200): array
    {
        $events = [];
        // $stored : titre enregistré tel quel en français (à traduire par HrHistoryLabel sur une page anglaise).
        $add = static function (string $type, string $date, string $title, string $detail = '', bool $stored = false) use (&$events): void {
            $day = self::day($date);
            $title = trim($title);
            if ($day === '' || $title === '') {
                return;
            }
            $events[] = [
                'date' => $day,
                'type' => $type,
                'type_label' => self::TYPES[$type],
                'title' => $title,
                'detail' => trim($detail),
                'anchor' => self::ANCHORS[$type],
                'stored' => $stored,
            ];
        };
        $rows = static fn (string $key): array => is_array($sources[$key] ?? null) ? $sources[$key] : [];
        $labels = static fn (string $key): array => is_array($sources[$key] ?? null) ? $sources[$key] : [];

        foreach ($rows('grades') as $g) {
            $detail = !empty($g['from_known']) ? 'Précédemment : ' . trim((string) ($g['from_label'] ?? '')) : '';
            $by = trim((string) ($g['actor_label'] ?? ''));
            $how = match ((string) ($g['source'] ?? '')) {
                'elevation' => 'Élévation validée',
                'correction' => 'Correction de dossier validée',
                default => 'Changement de grade',
            };
            $add('grade', (string) ($g['date'] ?? ''), (string) ($g['to_label'] ?? ''), ($detail !== '' ? $detail . ' · ' : '') . $how . ($by !== '' ? ' par ' . $by : ''));
        }
        foreach ($rows('org_history') as $o) {
            $add('organisation', (string) ($o['created_at'] ?? ''), (string) ($o['summary'] ?? ''), '', true);
        }
        foreach ($rows('stage_bilans') as $b) {
            $add(
                'bilan',
                (string) ($b['event_date'] ?? $b['created_at'] ?? ''),
                (string) ($b['title'] ?? $b['stage_label'] ?? $b['summary'] ?? 'Bilan de stage'),
                '',
                true
            );
        }
        foreach ($rows('qualifications') as $q) {
            $name = trim((string) ($q['definition_name'] ?? $q['qualification_name'] ?? $q['name'] ?? 'Qualification'));
            $level = trim((string) ($q['level_name'] ?? ''));
            $add('qualification', (string) ($q['obtained_at'] ?? $q['created_at'] ?? ''), $name . ($level !== '' ? ' — ' . $level : ''), 'Qualification obtenue');
            $exp = self::day((string) ($q['expires_at'] ?? ''));
            if ($exp !== '' && $exp <= date('Y-m-d')) {
                $add('qualification', $exp, $name, 'Échéance atteinte');
            }
        }
        foreach ($rows('awards') as $a) {
            $authority = trim((string) ($a['authority'] ?? ''));
            $add('decoration', (string) ($a['awarded_at'] ?? ''), (string) ($a['definition_name'] ?? 'Décoration'), $authority !== '' ? 'Décernée par ' . $authority : 'Décoration décernée');
        }
        foreach ($rows('elevations') as $e) {
            $status = (string) ($e['status'] ?? 'pending');
            $by = trim((string) ($e['requester_display_name'] ?? '')) ?: trim((string) ($e['requester_email'] ?? ''));
            $add('elevation', (string) ($e['created_at'] ?? ''), 'Demande d’élévation : ' . mb_strtolower(self::ELEVATION_STATUS[$status] ?? $status, 'UTF-8'), $by !== '' ? 'Demandée par ' . $by : '');
        }
        foreach ($rows('sanctions') as $s) {
            $reason = trim((string) ($s['reason'] ?? ''));
            $add('sanction', (string) ($s['date'] ?? ''), (string) ($s['label'] ?? 'Mesure disciplinaire'), trim((string) ($s['status_label'] ?? '')) . ($reason !== '' ? ' · ' . $reason : ''));
            if (($s['status'] ?? '') === 'lifted') {
                $add('sanction', (string) ($s['lifted_at'] ?? ''), (string) ($s['label'] ?? 'Mesure disciplinaire'), 'Mesure levée');
            }
        }
        $reasons = $labels('absence_reasons');
        foreach ($rows('absences') as $a) {
            $reason = (string) ($a['reason'] ?? 'autre');
            $end = self::day((string) ($a['ends_on'] ?? ''));
            $detail = $end !== '' ? 'Jusqu’au ' . self::fr($end) : 'Sans date de fin';
            if (($a['status'] ?? 'active') === 'cancelled') {
                $detail .= ' · annulée';
            }
            $add('absence', (string) ($a['starts_on'] ?? ''), 'Absence : ' . mb_strtolower((string) ($reasons[$reason] ?? $reason), 'UTF-8'), $detail);
        }
        $mobilityTypes = $labels('mobility_types');
        foreach ($rows('mobility') as $m) {
            $type = (string) ($m['request_type'] ?? 'career_wish');
            $status = trim((string) ($m['status'] ?? 'pending'));
            $target = trim((string) ($m['target_label'] ?? ''));
            $add('mobilite', (string) ($m['created_at'] ?? ''), (string) ($mobilityTypes[$type] ?? $type), ($target !== '' ? $target . ' · ' : '') . (self::MOBILITY_STATUS[$status] ?? $status));
        }
        $documentTypes = $labels('document_types');
        foreach ($rows('hr_documents') as $d) {
            $type = (string) ($d['doc_type'] ?? 'autre');
            $title = trim((string) ($d['title'] ?? '')) ?: (string) ($documentTypes[$type] ?? 'Document RH');
            $add('document', (string) ($d['created_at'] ?? ''), $title, (string) ($documentTypes[$type] ?? ''));
        }
        if (is_array($sources['departure'] ?? null)) {
            $dep = $sources['departure'];
            $reason = (string) ($dep['reason'] ?? 'other');
            $departureReasons = $labels('departure_reasons');
            $add('depart', (string) ($dep['departed_at'] ?? ''), 'Départ : ' . mb_strtolower((string) ($departureReasons[$reason] ?? $reason), 'UTF-8'), !empty($dep['access_revoked']) ? 'Accès retirés' : '');
        }

        // Journal de service : en dernier, pour écarter ce que la source propre a déjà fourni le même jour.
        $daysByType = [];
        foreach ($events as $e) {
            $daysByType[$e['type']][$e['date']] = true;
        }
        foreach ($rows('service_history') as $s) {
            $date = (string) ($s['event_date'] ?? $s['created_at'] ?? '');
            $kind = (string) ($s['event_type'] ?? '');
            $twin = self::SERVICE_DUPLICATES[$kind] ?? null;
            if ($twin !== null && isset($daysByType[$twin][self::day($date)])) {
                continue;
            }
            $add('service', $date, (string) ($s['title'] ?? $s['summary'] ?? ''), self::SERVICE_TYPE_LABELS[$kind] ?? '', true);
        }

        // Un même fait lu deux fois (même jour, même type, même texte) n'est montré qu'une fois.
        $seen = [];
        $unique = [];
        foreach ($events as $e) {
            $key = $e['date'] . '|' . $e['type'] . '|' . mb_strtolower($e['title'], 'UTF-8');
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $unique[] = $e;
        }
        // Plus récent d'abord ; à date égale l'ordre des sources est conservé (tri stable depuis PHP 8).
        usort($unique, static fn (array $a, array $b): int => strcmp($b['date'], $a['date']));

        return array_slice($unique, 0, max(1, $limit));
    }

    /**
     * @param list<array{type: string}> $events
     * @return array<string, int> nombre d'événements par type présent, dans l'ordre du filtre
     */
    public static function counts(array $events): array
    {
        $counts = [];
        foreach (array_keys(self::TYPES) as $type) {
            $n = count(array_filter($events, static fn (array $e): bool => $e['type'] === $type));
            if ($n > 0) {
                $counts[$type] = $n;
            }
        }

        return $counts;
    }

    /** Type de filtre valide, ou chaîne vide pour « tout ». */
    public static function sanitizeType(?string $type): string
    {
        $type = trim((string) $type);

        return isset(self::TYPES[$type]) ? $type : '';
    }

    /**
     * @template T of array{type: string}
     * @param list<T> $events
     * @return list<T>
     */
    public static function filter(array $events, string $type): array
    {
        if ($type === '') {
            return $events;
        }

        return array_values(array_filter($events, static fn (array $e): bool => $e['type'] === $type));
    }

    private static function day(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '' || str_starts_with($raw, '0000-00-00')) {
            return '';
        }
        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $raw, $m) === 1) {
            return $m[1];
        }
        $ts = strtotime($raw);

        return $ts !== false ? date('Y-m-d', $ts) : '';
    }

    private static function fr(string $ymd): string
    {
        return date('d/m/Y', (int) strtotime($ymd));
    }
}
