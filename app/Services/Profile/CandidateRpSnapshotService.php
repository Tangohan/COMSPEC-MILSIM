<?php

declare(strict_types=1);

namespace App\Services\Profile;

/**
 * Identité RP du candidat figée au dépôt d’une candidature (colonne enlistments.recruitment_rp_json)
 * et contrôle du dossier personnage sur la fiche.
 *
 * Les anciens « profils de candidature » enregistrés côté compte ont été retirés : seule la
 * structure du snapshot reste, pour les candidatures déposées et leur affichage au recrutement.
 *
 * @phpstan-type ScheduleSlot array{dow:int,start:string,end:string}
 */
final class CandidateRpSnapshotService
{
    public const PAYLOAD_VERSION = 2;

    /** Lundi = 1 … Dimanche = 7 (ISO-8601) */
    private const DAY_LABELS = [
        1 => 'Lun',
        2 => 'Mar',
        3 => 'Mer',
        4 => 'Jeu',
        5 => 'Ven',
        6 => 'Sam',
        7 => 'Dim',
    ];

    /**
     * Normalise un payload RP (rétrocompatibilité v1 : chaîne availability seule).
     *
     * @param array<string, mixed> $raw
     * @return array<string, mixed>
     */
    public function normalizeDecodedPayload(array $raw): array
    {
        $out = $raw;
        $out['payload_version'] = (int) ($out['payload_version'] ?? 1);

        $legacyAvailabilityString = '';
        if (isset($raw['availability']) && is_string($raw['availability']) && trim($raw['availability']) !== '') {
            $legacyAvailabilityString = trim($raw['availability']);
        }

        if (!isset($out['rp']) || !is_array($out['rp'])) {
            $out['rp'] = [];
        }
        $rp = &$out['rp'];
        $rp['character_name'] = isset($rp['character_name']) ? trim((string) $rp['character_name']) : '';
        $rp['first_name'] = isset($rp['first_name']) ? trim((string) $rp['first_name']) : '';
        $rp['last_name'] = isset($rp['last_name']) ? trim((string) $rp['last_name']) : '';
        if (function_exists('mb_substr')) {
            $rp['first_name'] = mb_substr($rp['first_name'], 0, 100);
            $rp['last_name'] = mb_substr($rp['last_name'], 0, 100);
        } else {
            $rp['first_name'] = substr($rp['first_name'], 0, 100);
            $rp['last_name'] = substr($rp['last_name'], 0, 100);
        }
        $rp['birth_date'] = self::normalizeRpBirthDate(isset($rp['birth_date']) ? (string) $rp['birth_date'] : '');
        $rp['nationality'] = isset($rp['nationality']) ? trim((string) $rp['nationality']) : '';
        if (function_exists('mb_substr')) {
            $rp['nationality'] = mb_substr($rp['nationality'], 0, 100);
        } else {
            $rp['nationality'] = substr($rp['nationality'], 0, 100);
        }
        $rp['bio'] = isset($rp['bio']) ? trim((string) $rp['bio']) : '';
        $rp['cv'] = isset($rp['cv']) ? trim((string) $rp['cv']) : '';
        $rp['image_url'] = isset($rp['image_url']) ? trim((string) $rp['image_url']) : '';
        $rp['image_external_url'] = isset($rp['image_external_url']) ? trim((string) $rp['image_external_url']) : '';

        $out['admin_notes'] = isset($out['admin_notes']) ? trim((string) $out['admin_notes']) : '';

        if (!isset($out['availability']) || !is_array($out['availability'])) {
            $out['availability'] = [];
        }
        $av = &$out['availability'];
        if (!isset($av['schedule']) || !is_array($av['schedule'])) {
            $av['schedule'] = [];
        }
        $av['timezone_label'] = isset($av['timezone_label']) ? trim((string) $av['timezone_label']) : '';
        $av['free_text'] = isset($av['free_text']) ? trim((string) $av['free_text']) : '';

        if ($legacyAvailabilityString !== '') {
            $av['free_text'] = $av['free_text'] !== '' ? $av['free_text'] : $legacyAvailabilityString;
        }

        // Champs MilSim (racine)
        foreach ([
            'callsign', 'timezone', 'weekly_availability', 'system_config', 'microphone_quality',
            'past_milsim_experience', 'ace_acre_level', 'motivation_why_join', 'motivation_accountability',
            'commitment_effort', 'availability_wed_sat',
        ] as $k) {
            if (!array_key_exists($k, $out)) {
                $out[$k] = '';
            } else {
                $out[$k] = trim((string) $out[$k]);
            }
        }
        if (!isset($out['age']) || $out['age'] === '' || $out['age'] === null) {
            $out['age'] = '';
        } else {
            $out['age'] = (string) (int) $out['age'];
        }

        $out['payload_version'] = self::PAYLOAD_VERSION;

        return $out;
    }

    /**
     * @param array<string, mixed> $normalizedPayload
     * @return array{availability: string, weekly_availability: string}
     */
    public function deriveAvailabilityStrings(array $normalizedPayload): array
    {
        $av = $normalizedPayload['availability'] ?? [];
        if (!is_array($av)) {
            $av = [];
        }
        $schedule = $av['schedule'] ?? [];
        if (!is_array($schedule)) {
            $schedule = [];
        }
        $slots = $this->normalizeScheduleSlots($schedule);
        $tz = isset($av['timezone_label']) ? trim((string) $av['timezone_label']) : '';
        $free = isset($av['free_text']) ? trim((string) $av['free_text']) : '';

        $lines = [];
        foreach ($slots as $s) {
            $dow = (int) ($s['dow'] ?? 0);
            $start = (string) ($s['start'] ?? '');
            $end = (string) ($s['end'] ?? '');
            if ($dow < 1 || $dow > 7 || $start === '' || $end === '') {
                continue;
            }
            $label = self::DAY_LABELS[$dow] ?? (string) $dow;
            $lines[] = $label . ' ' . $start . '–' . $end;
        }
        $scheduleText = $lines !== [] ? implode(' ; ', $lines) : '';
        $parts = [];
        if ($scheduleText !== '') {
            $parts[] = $scheduleText;
        }
        if ($tz !== '') {
            $parts[] = 'Fuseau / réf. : ' . $tz;
        }
        if ($free !== '') {
            $parts[] = $free;
        }
        $combined = implode("\n", $parts);

        return [
            'availability' => $combined !== '' ? $combined : $free,
            'weekly_availability' => $scheduleText !== '' ? $scheduleText : $free,
        ];
    }

    /**
     * @param list<array<string, mixed>> $schedule
     * @return list<ScheduleSlot>
     */
    public function normalizeScheduleSlots(array $schedule): array
    {
        $out = [];
        foreach ($schedule as $row) {
            if (!is_array($row)) {
                continue;
            }
            $dow = (int) ($row['dow'] ?? 0);
            $start = $this->normalizeTime((string) ($row['start'] ?? ''));
            $end = $this->normalizeTime((string) ($row['end'] ?? ''));
            if ($dow < 1 || $dow > 7 || $start === null || $end === null) {
                continue;
            }
            $out[] = ['dow' => $dow, 'start' => $start, 'end' => $end];
        }

        return $out;
    }

    private function normalizeTime(string $t): ?string
    {
        $t = trim($t);
        if ($t === '') {
            return null;
        }
        if (preg_match('/^(\d{1,2}):(\d{2})$/', $t, $m)) {
            $h = (int) $m[1];
            $min = (int) $m[2];
            if ($h >= 0 && $h <= 23 && $min >= 0 && $min <= 59) {
                return sprintf('%02d:%02d', $h, $min);
            }
        }

        return null;
    }

    /**
     * Date de naissance personnage (YYYY-MM-DD) ou chaîne vide.
     */
    public static function normalizeRpBirthDate(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }
        $d = \DateTimeImmutable::createFromFormat('Y-m-d', $raw);

        return $d && $d->format('Y-m-d') === $raw ? $raw : '';
    }

    /**
     * Libellé unique affiché sur dossiers / snapshot : nom de scène optionnel prioritaire, sinon « prénom nom ».
     *
     * @param array<string, mixed> $rp
     */
    public static function deriveOperatorDisplayName(array $rp): string
    {
        $scene = trim((string) ($rp['character_name'] ?? ''));
        if ($scene !== '') {
            return function_exists('mb_substr') ? mb_substr($scene, 0, 150) : substr($scene, 0, 150);
        }
        $fn = trim((string) ($rp['first_name'] ?? ''));
        $ln = trim((string) ($rp['last_name'] ?? ''));
        $joined = trim($fn . ' ' . $ln);

        return $joined !== '' ? (function_exists('mb_substr') ? mb_substr($joined, 0, 150) : substr($joined, 0, 150)) : '';
    }

    /**
     * Indique si le titulaire doit être invité à compléter l’identité personnage sur la fiche (nom affiché dossier ou nationalité RP).
     *
     * @param array<string, mixed> $personnelProfile
     */
    public static function personnelRpDossierNeedsAttention(array $personnelProfile): bool
    {
        $cn = trim((string) ($personnelProfile['character_name'] ?? ''));
        if ($cn !== '') {
            return false;
        }
        $nat = trim((string) ($personnelProfile['nationality'] ?? ''));

        return $nat === '';
    }

    /**
     * Snapshot RP + dispo pour colonne enlistments.recruitment_rp_json (figé au dépôt).
     *
     * @param array<string, mixed> $rpPayload identité RP saisie dans le formulaire (rp, admin_notes, availability)
     * @return array<string, mixed>
     */
    public function buildRpSnapshotForEnlistment(array $rpPayload): array
    {
        $p = $this->normalizeDecodedPayload($rpPayload);
        $rp = is_array($p['rp'] ?? null) ? $p['rp'] : [];

        $scene = trim((string) ($rp['character_name'] ?? ''));
        $rfn = trim((string) ($rp['first_name'] ?? ''));
        $rln = trim((string) ($rp['last_name'] ?? ''));
        $rbd = trim((string) ($rp['birth_date'] ?? ''));
        $rnat = trim((string) ($rp['nationality'] ?? ''));

        $snap = [
            'payload_version' => $p['payload_version'] ?? self::PAYLOAD_VERSION,
            'rp_scene_name' => $scene,
            'rp_first_name' => $rfn,
            'rp_last_name' => $rln,
            'rp_birth_date' => $rbd,
            'rp_nationality' => $rnat,
            'character_name' => self::deriveOperatorDisplayName($rp),
            'bio' => $rp['bio'] ?? '',
            'cv' => $rp['cv'] ?? '',
            'image_url' => $rp['image_url'] ?? '',
            'image_external_url' => $rp['image_external_url'] ?? '',
            'admin_notes' => $p['admin_notes'] ?? '',
            'availability' => $p['availability'] ?? [],
            'derived_availability' => $this->deriveAvailabilityStrings($p),
        ];

        return $snap;
    }
}
