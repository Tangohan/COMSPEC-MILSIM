<?php

declare(strict_types=1);

namespace App\Services\Profile;

use App\Core\Request;

/**
 * Structure JSON des profils de candidature (recruitment_presets.payload) + fusion vers enlistments.
 *
 * @phpstan-type ScheduleSlot array{dow:int,start:string,end:string}
 */
final class RecruitmentPresetPayloadService
{
    public const PAYLOAD_VERSION = 2;

    public const MAX_IMAGE_BYTES = 2 * 1024 * 1024;

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

    /** @var list<string> */
    private const ALLOWED_IMAGE_MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    /**
     * Normalise un payload décodé (rétrocompatibilité v1 : chaîne availability seule).
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
     * Complète personnel_profiles à partir d’un preset normalisé (sans écraser une valeur déjà saisie).
     *
     * @param array<string, mixed> $normalizedPayload payload complet normalisé
     * @return array<string, string> colonnes à passer à PersonnelProfileRepository::update
     */
    public function personnelAutoFillPatchFromPayload(array $normalizedPayload): array
    {
        $rp = is_array($normalizedPayload['rp'] ?? null) ? $normalizedPayload['rp'] : [];
        $patch = [];
        // Pas d’écriture silencieuse de character_name : le dossier se remplit
        // explicitement sur la fiche personnel, pour éviter un faux « autre membre ».
        $nat = trim((string) ($rp['nationality'] ?? ''));
        if ($nat !== '') {
            $patch['nationality'] = function_exists('mb_substr') ? mb_substr($nat, 0, 100) : substr($nat, 0, 100);
        }

        return $patch;
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
     * Construit le tableau payload à enregistrer (hors upload fichier — fait par le contrôleur).
     *
     * @param array<string, mixed> $existingNormalized
     * @return array<string, mixed>
     */
    public function buildPayloadFromRequest(Request $request, array $existingNormalized, bool $removeImage): array
    {
        $p = $this->normalizeDecodedPayload($existingNormalized);

        $p['callsign'] = trim((string) $request->input('callsign'));
        $p['age'] = trim((string) $request->input('age'));
        if ($p['age'] !== '' && !ctype_digit($p['age'])) {
            $p['age'] = '';
        }
        $p['timezone'] = trim((string) $request->input('timezone'));
        $p['system_config'] = trim((string) $request->input('system_config'));
        $p['microphone_quality'] = trim((string) $request->input('microphone_quality'));
        $p['past_milsim_experience'] = trim((string) $request->input('past_milsim_experience'));
        $p['ace_acre_level'] = trim((string) $request->input('ace_acre_level'));
        $p['motivation_why_join'] = trim((string) $request->input('motivation_why_join'));
        $p['motivation_accountability'] = trim((string) $request->input('motivation_accountability'));
        $p['commitment_effort'] = trim((string) $request->input('commitment_effort'));
        $p['availability_wed_sat'] = trim((string) $request->input('availability_wed_sat'));

        $p['admin_notes'] = trim((string) $request->input('admin_notes'));

        if (!isset($p['rp']) || !is_array($p['rp'])) {
            $p['rp'] = [];
        }
        $p['rp']['character_name'] = trim((string) $request->input('rp_character_name'));
        $p['rp']['first_name'] = trim((string) $request->input('rp_first_name'));
        $p['rp']['last_name'] = trim((string) $request->input('rp_last_name'));
        $p['rp']['birth_date'] = self::normalizeRpBirthDate((string) $request->input('rp_birth_date'));
        $p['rp']['nationality'] = trim((string) $request->input('rp_nationality'));
        $p['rp']['bio'] = trim((string) $request->input('rp_bio'));
        $p['rp']['cv'] = trim((string) $request->input('rp_cv'));
        $p['rp']['image_external_url'] = trim((string) $request->input('rp_image_external_url'));
        if ($removeImage) {
            $p['rp']['image_url'] = '';
        }

        if (!isset($p['availability']) || !is_array($p['availability'])) {
            $p['availability'] = [];
        }
        $p['availability']['timezone_label'] = trim((string) $request->input('availability_timezone_label'));
        $p['availability']['free_text'] = trim((string) $request->input('availability_free_text'));

        $dows = $request->input('slot_dow', []);
        $starts = $request->input('slot_start', []);
        $ends = $request->input('slot_end', []);
        $schedule = [];
        if (is_array($dows) && is_array($starts) && is_array($ends)) {
            $n = max(count($dows), count($starts), count($ends));
            for ($i = 0; $i < $n; $i++) {
                $schedule[] = [
                    'dow' => isset($dows[$i]) ? (int) $dows[$i] : 0,
                    'start' => isset($starts[$i]) ? (string) $starts[$i] : '',
                    'end' => isset($ends[$i]) ? (string) $ends[$i] : '',
                ];
            }
        }
        $p['availability']['schedule'] = array_values($this->normalizeScheduleSlots($schedule));

        return $this->normalizeDecodedPayload($p);
    }

    /**
     * Clés de partage RP (cases formulaire) — alignées sur buildRpSnapshotForEnlistment.
     *
     * @return list<string>
     */
    public static function rpShareSelectionKeys(): array
    {
        return [
            'identity',
            'character_name',
            'bio',
            'cv',
            'image_url',
            'image_external_url',
            'admin_notes',
            'availability',
        ];
    }

    /**
     * @param array<string, bool|int|string> $raw
     * @return array<string, bool>
     */
    public function normalizeRpShareSelections(array $raw): array
    {
        $out = [];
        foreach (self::rpShareSelectionKeys() as $k) {
            $out[$k] = !empty($raw[$k]);
        }

        return $out;
    }

    /**
     * Fusionne un preset normalisé dans le payload d'enlistment (POST / preset).
     *
     * @param array<string, mixed> $presetPayload
     * @param array<string, mixed> $enlistmentPayload
     * @param array<string, mixed>|null $shareOptions null = comportement historique (tout fusionner)
     */
    public function mergePresetIntoEnlistmentPayload(array $presetPayload, array &$enlistmentPayload, ?array $shareOptions = null): void
    {
        $p = $this->normalizeDecodedPayload($presetPayload);
        $derived = $this->deriveAvailabilityStrings($p);

        $includeMilsim = $shareOptions === null ? true : (bool) ($shareOptions['include_milsim_from_preset'] ?? true);
        /** @var array<string, bool>|null $rpShares null = tout inclure dans les notes RP */
        $rpShares = $shareOptions['rp_shares'] ?? null;

        if ($includeMilsim) {
            $stringKeys = [
                'timezone', 'system_config', 'microphone_quality', 'past_milsim_experience', 'ace_acre_level',
                'motivation_why_join', 'motivation_accountability', 'commitment_effort', 'availability_wed_sat',
            ];
            foreach ($stringKeys as $k) {
                if (isset($p[$k]) && trim((string) $p[$k]) !== '') {
                    $enlistmentPayload[$k] = trim((string) $p[$k]);
                }
            }

            if (!empty($p['age']) && ctype_digit((string) $p['age'])) {
                $enlistmentPayload['age'] = (int) $p['age'];
            }

            if ($derived['availability'] !== '') {
                $enlistmentPayload['availability'] = $derived['availability'];
            }
            if ($derived['weekly_availability'] !== '') {
                $enlistmentPayload['weekly_availability'] = $derived['weekly_availability'];
            }
        }

        $notesParts = [];
        $rp = is_array($p['rp'] ?? null) ? $p['rp'] : [];
        $scene = trim((string) ($rp['character_name'] ?? ''));
        $rfn = trim((string) ($rp['first_name'] ?? ''));
        $rln = trim((string) ($rp['last_name'] ?? ''));
        $rbd = trim((string) ($rp['birth_date'] ?? ''));
        $rnat = trim((string) ($rp['nationality'] ?? ''));
        $bio = trim((string) ($rp['bio'] ?? ''));
        $cv = trim((string) ($rp['cv'] ?? ''));
        $img = trim((string) ($rp['image_url'] ?? ''));
        $ext = trim((string) ($rp['image_external_url'] ?? ''));
        $adm = trim((string) ($p['admin_notes'] ?? ''));

        $allowIdentity = $rpShares === null || !empty($rpShares['identity']);
        $allowChar = $rpShares === null || !empty($rpShares['character_name']);
        $allowBio = $rpShares === null || !empty($rpShares['bio']);
        $allowCv = $rpShares === null || !empty($rpShares['cv']);
        $allowImg = $rpShares === null || !empty($rpShares['image_url']);
        $allowExt = $rpShares === null || !empty($rpShares['image_external_url']);
        $allowAdm = $rpShares === null || !empty($rpShares['admin_notes']);

        $idLines = [];
        if ($allowIdentity) {
            if ($rfn !== '') {
                $idLines[] = 'Prénom (personnage) : ' . $rfn;
            }
            if ($rln !== '') {
                $idLines[] = 'Nom (personnage) : ' . $rln;
            }
            if ($rbd !== '') {
                $idLines[] = 'Date de naissance (personnage) : ' . $rbd;
            }
            if ($rnat !== '') {
                $idLines[] = 'Nationalité (personnage) : ' . $rnat;
            }
        }
        if ($allowChar && $scene !== '') {
            $idLines[] = 'Nom de scène (optionnel) : ' . $scene;
        }
        if ($idLines !== []) {
            $notesParts[] = "— Personnage RP —\n" . implode("\n", $idLines);
        }
        if ($allowBio && $bio !== '') {
            $notesParts[] = "Bio :\n" . $bio;
        }
        if ($allowCv && $cv !== '') {
            $notesParts[] = "CV / historique :\n" . $cv;
        }
        if ($allowImg && $img !== '') {
            $notesParts[] = 'Portrait (fichier) : ' . $img;
        }
        if ($allowExt && $ext !== '') {
            $notesParts[] = 'Portrait (lien) : ' . $ext;
        }
        if ($allowAdm && $adm !== '') {
            $notesParts[] = "— Notes (candidat) —\n" . $adm;
        }
        if ($notesParts !== []) {
            $extra = implode("\n\n", $notesParts);
            $prev = trim((string) ($enlistmentPayload['notes'] ?? ''));
            $enlistmentPayload['notes'] = $prev !== '' ? $prev . "\n\n" . $extra : $extra;
        }
    }

    /**
     * Snapshot RP + dispo pour colonne enlistments.recruitment_rp_json (figé au dépôt).
     *
     * @param array<string, mixed> $presetPayload
     * @return array<string, mixed>
     */
    public function buildRpSnapshotForEnlistment(array $presetPayload, ?array $rpShares = null): array
    {
        $p = $this->normalizeDecodedPayload($presetPayload);
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

        if ($rpShares === null) {
            return $snap;
        }

        return $this->filterRpSnapshotByShares($snap, $rpShares);
    }

    /**
     * @param array<string, mixed> $snapshot
     * @param array<string, bool> $rpShares
     * @return array<string, mixed>
     */
    public function filterRpSnapshotByShares(array $snapshot, array $rpShares): array
    {
        $out = $snapshot;
        if (empty($rpShares['identity'])) {
            $out['rp_first_name'] = '';
            $out['rp_last_name'] = '';
            $out['rp_birth_date'] = '';
            $out['rp_nationality'] = '';
        }
        if (empty($rpShares['character_name'])) {
            $out['rp_scene_name'] = '';
        }
        if (empty($rpShares['bio'])) {
            $out['bio'] = '';
        }
        if (empty($rpShares['cv'])) {
            $out['cv'] = '';
        }
        if (empty($rpShares['image_url'])) {
            $out['image_url'] = '';
        }
        if (empty($rpShares['image_external_url'])) {
            $out['image_external_url'] = '';
        }
        if (empty($rpShares['admin_notes'])) {
            $out['admin_notes'] = '';
        }
        if (empty($rpShares['availability'])) {
            $out['availability'] = [];
            $out['derived_availability'] = ['availability' => '', 'weekly_availability' => ''];
        }

        $out['character_name'] = self::deriveOperatorDisplayName([
            'character_name' => (string) ($out['rp_scene_name'] ?? ''),
            'first_name' => (string) ($out['rp_first_name'] ?? ''),
            'last_name' => (string) ($out['rp_last_name'] ?? ''),
        ]);

        return $out;
    }

    /**
     * @param array<string, bool> $rpShares
     */
    public function snapshotHasAnyRpContent(array $snapshot, array $rpShares): bool
    {
        foreach (self::rpShareSelectionKeys() as $k) {
            if (empty($rpShares[$k])) {
                continue;
            }
            if ($k === 'availability') {
                $d = $snapshot['derived_availability'] ?? [];
                if (is_array($d) && (trim((string) ($d['availability'] ?? '')) !== '' || trim((string) ($d['weekly_availability'] ?? '')) !== '')) {
                    return true;
                }
                $av = $snapshot['availability'] ?? [];
                if (is_array($av) && $av !== []) {
                    return true;
                }

                continue;
            }
            if ($k === 'identity') {
                foreach (['rp_first_name', 'rp_last_name', 'rp_birth_date', 'rp_nationality'] as $ik) {
                    if (trim((string) ($snapshot[$ik] ?? '')) !== '') {
                        return true;
                    }
                }

                continue;
            }
            if ($k === 'character_name') {
                if (trim((string) ($snapshot['rp_scene_name'] ?? '')) !== '') {
                    return true;
                }

                continue;
            }
            if (trim((string) ($snapshot[$k] ?? '')) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * Indique si le snapshot contient au moins une donnée RP affichable (après filtrage éventuel).
     *
     * @param array<string, mixed> $snapshot
     */
    public function snapshotHasVisibleRpContent(array $snapshot): bool
    {
        $allOn = [];
        foreach (self::rpShareSelectionKeys() as $k) {
            $allOn[$k] = true;
        }

        return $this->snapshotHasAnyRpContent($snapshot, $allOn);
    }

    /**
     * @return array{ok: bool, path?: string, error?: string}
     */
    public function saveCharacterImage(int $userId, ?array $file): array
    {
        if (!$file || ($file['error'] ?? 0) !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'error' => 'Fichier manquant ou erreur d’envoi.'];
        }
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? finfo_file($finfo, $file['tmp_name']) : false;
        if ($finfo) {
            finfo_close($finfo);
        }
        if (!is_string($mime) || !in_array($mime, self::ALLOWED_IMAGE_MIMES, true) || ($file['size'] ?? 0) > self::MAX_IMAGE_BYTES) {
            return ['ok' => false, 'error' => 'Image JPG, PNG ou WebP, max 2 Mo.'];
        }
        $dir = base_path('public/uploads/recruitment-presets/' . $userId);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $ext = match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };
        $name = 'ch_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $pathFs = $dir . DIRECTORY_SEPARATOR . $name;
        if (!move_uploaded_file($file['tmp_name'], $pathFs)) {
            return ['ok' => false, 'error' => 'Enregistrement du fichier impossible.'];
        }

        return ['ok' => true, 'path' => 'uploads/recruitment-presets/' . $userId . '/' . $name];
    }

    public function deleteCharacterImageFile(?string $relativePath): void
    {
        if ($relativePath === null || $relativePath === '') {
            return;
        }
        $relativePath = str_replace(['..', '\\'], '', $relativePath);
        if (!str_starts_with($relativePath, 'uploads/recruitment-presets/')) {
            return;
        }
        $full = base_path('public/' . $relativePath);
        if (is_file($full)) {
            @unlink($full);
        }
    }
}
