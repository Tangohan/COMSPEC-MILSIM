<?php

declare(strict_types=1);

namespace App\Services\Effectifs;

use App\Core\Database;
use App\Repositories\GradeRepository;
use App\Repositories\PersonnelAssignmentRepository;
use App\Repositories\PersonnelProfileRepository;
use App\Repositories\UnitRepository;
use App\Repositories\UserProfileRepository;
use App\Repositories\UserRepository;
use App\Support\RosterOnlyMember;

/**
 * Profil « Bureau des effectifs » : fiches tenues par l’état-major, sans compte pour le membre
 * (voir RosterOnlyMember), saisies une à une ou importées depuis un tableur (CSV).
 */
final class RosterOnlyMemberService
{
    public const IMPORT_MAX_ROWS = 500;

    /** En-têtes reconnus (minuscules, sans accents) => champ. */
    private const HEADER_ALIASES = [
        'prenom' => 'first_name', 'first name' => 'first_name', 'firstname' => 'first_name',
        'nom' => 'last_name', 'last name' => 'last_name', 'lastname' => 'last_name', 'nom de famille' => 'last_name',
        'nom affiche' => 'display_name', 'nom complet' => 'display_name', 'display name' => 'display_name',
        'name' => 'display_name', 'membre' => 'display_name', 'personnage' => 'display_name',
        'indicatif' => 'callsign', 'callsign' => 'callsign', 'call sign' => 'callsign', 'pseudo' => 'callsign',
        'grade' => 'grade', 'rank' => 'grade',
        'affectation' => 'unit', 'assignment' => 'unit', 'unite' => 'unit', 'unit' => 'unit', 'section' => 'unit', 'groupe' => 'unit',
        'email' => 'email', 'e mail' => 'email', 'mail' => 'email', 'courriel' => 'email', 'adresse e mail' => 'email', 'adresse email' => 'email', 'adresse mail' => 'email',
        'steam' => 'steam_id', 'steam id' => 'steam_id', 'steamid' => 'steam_id', 'steamid64' => 'steam_id', 'steam id64' => 'steam_id',
    ];

    public function __construct(
        private UserRepository $userRepository,
        private UserProfileRepository $userProfileRepository,
        private PersonnelProfileRepository $personnelProfileRepository,
        private PersonnelAssignmentRepository $personnelAssignmentRepository,
        private GradeRepository $gradeRepository,
        private UnitRepository $unitRepository,
        private ?RosterFicheEmailService $emails = null,
    ) {}

    /**
     * Crée une fiche sans compte. Le membre reçoit le niveau « Membre » comme tout le monde.
     *
     * @param array{first_name?: string, last_name?: string, display_name?: string, callsign?: string, grade_id?: ?int, unit_id?: ?int, steam_id?: ?string} $row
     */
    public function create(int $tenantId, int $actorUserId, array $row): int
    {
        $first = trim((string) ($row['first_name'] ?? ''));
        $last = trim((string) ($row['last_name'] ?? ''));
        $display = trim((string) ($row['display_name'] ?? ''));
        if ($display === '') {
            $display = trim($first . ' ' . $last);
        }
        if ($display === '') {
            throw new \InvalidArgumentException('Un nom est requis pour créer la fiche.');
        }
        $callsign = trim((string) ($row['callsign'] ?? ''));
        $gradeId = isset($row['grade_id']) && (int) $row['grade_id'] > 0 ? (int) $row['grade_id'] : null;
        $unitId = isset($row['unit_id']) && (int) $row['unit_id'] > 0 ? (int) $row['unit_id'] : null;

        $userId = $this->userRepository->create($tenantId, [
            'email' => RosterOnlyMember::syntheticEmail(),
            'password_hash' => password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT),
            'display_name' => mb_substr($display, 0, 100, 'UTF-8'),
            'callsign' => $callsign !== '' ? mb_substr($callsign, 0, 50, 'UTF-8') : null,
            'grade_id' => $gradeId,
            'status' => 'active',
        ]);
        if ($first !== '' || $last !== '') {
            $this->userProfileRepository->upsert($userId, ['first_name' => $first, 'last_name' => $last]);
        }
        try {
            $this->personnelProfileRepository->ensureRecord($userId, $tenantId);
            $this->personnelProfileRepository->update($userId, array_filter([
                'character_name' => $display,
                'primary_unit_id' => $unitId,
            ], static fn ($v): bool => $v !== null));
            if ($unitId !== null) {
                $this->personnelAssignmentRepository->syncPrimaryAssignmentFromDossier($userId, $unitId, 'Membre', 'Fiche créée par l’état-major');
            }
        } catch (\Throwable) {
        }
        $this->userRepository->syncOrganizationRoles($userId, $tenantId, [], $actorUserId > 0 ? $actorUserId : null);
        \App\Services\Personnel\NewMemberNumbers::assign($userId, $tenantId);
        $steam = preg_replace('/\D+/', '', (string) ($row['steam_id'] ?? '')) ?? '';
        if (preg_match('/^7656119\d{10}$/', $steam) === 1) {
            $this->userRepository->update($userId, $tenantId, ['steam_id' => $steam]);
        }

        return $userId;
    }

    /**
     * Lit un tableur CSV (séparateur ; , ou tabulation, première ligne = en-têtes).
     *
     * @return array{rows: list<array<string, string>>, columns: list<string>, error: ?string}
     */
    public static function parseCsv(string $content): array
    {
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? $content;
        if (!mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
        }
        $content = str_replace(["\r\n", "\r"], "\n", $content);
        $firstLine = strtok($content, "\n") ?: '';
        $sep = ';';
        $best = substr_count($firstLine, ';');
        foreach ([',', "\t"] as $cand) {
            if (substr_count($firstLine, $cand) > $best) {
                $best = substr_count($firstLine, $cand);
                $sep = $cand;
            }
        }
        $fh = fopen('php://temp', 'r+');
        if ($fh === false) {
            return ['rows' => [], 'columns' => [], 'error' => 'Lecture du fichier impossible.'];
        }
        fwrite($fh, $content);
        rewind($fh);
        $header = fgetcsv($fh, 0, $sep, '"', '\\');
        if (!is_array($header)) {
            fclose($fh);

            return ['rows' => [], 'columns' => [], 'error' => 'Le fichier est vide.'];
        }
        $map = [];
        foreach ($header as $i => $label) {
            $key = self::headerKey((string) $label);
            if ($key !== null && !in_array($key, $map, true)) {
                $map[$i] = $key;
            }
        }
        if (!in_array('display_name', $map, true) && !in_array('last_name', $map, true) && !in_array('first_name', $map, true) && !in_array('callsign', $map, true)) {
            fclose($fh);

            return ['rows' => [], 'columns' => [], 'error' => 'Colonne de nom introuvable : la première ligne doit contenir au moins « Nom » ou « Indicatif ».'];
        }
        $rows = [];
        while (($line = fgetcsv($fh, 0, $sep, '"', '\\')) !== false) {
            if (!is_array($line) || count(array_filter($line, static fn ($v): bool => trim((string) $v) !== '')) === 0) {
                continue;
            }
            $row = [];
            foreach ($map as $i => $key) {
                $row[$key] = trim((string) ($line[$i] ?? ''));
            }
            $rows[] = $row;
            if (count($rows) > self::IMPORT_MAX_ROWS) {
                break;
            }
        }
        fclose($fh);

        return ['rows' => $rows, 'columns' => array_values($map), 'error' => null];
    }

    private static function headerKey(string $label): ?string
    {
        $k = mb_strtolower(trim($label), 'UTF-8');
        $k = strtr($k, ['é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'à' => 'a', 'â' => 'a', 'î' => 'i', 'ï' => 'i', 'ô' => 'o', 'û' => 'u', 'ù' => 'u', 'ç' => 'c', '_' => ' ', '-' => ' ']);
        $k = trim(preg_replace('/\s+/', ' ', $k) ?? $k);

        return self::HEADER_ALIASES[$k] ?? null;
    }

    /** Colonnes du tableur (export, modèle, import) dans l'ordre. */
    public const SHEET_COLUMNS = ['Prénom', 'Nom', 'Indicatif', 'E-mail', 'Grade', 'Affectation', 'Steam ID'];

    /**
     * Importe les lignes lues par parseCsv() et rapporte chaque ligne. Une ligne qui correspond à une fiche sans compte
     * de la communauté (même indicatif, sinon même nom) la met à jour : seules les cellules remplies changent. Une ligne
     * qui correspond à un compte existant est refusée, jamais modifiée.
     *
     * @param list<array<string, string>> $rows
     * @param (callable(int, string): void)|null $onCreated appelé avec l’identifiant et le nom de chaque fiche créée
     * @param (callable(int, string): void)|null $onUpdated appelé avec l’identifiant et la liste des changements de chaque fiche mise à jour
     * @return array{created: int, updated: int, skipped: list<string>, unknown_grades: list<string>, unknown_units: list<string>, lines: list<array{line: int, name: string, result: string, detail: string}>}
     */
    public function import(int $tenantId, int $actorUserId, array $rows, ?callable $onCreated = null, ?callable $onUpdated = null): array
    {
        $out = ['created' => 0, 'updated' => 0, 'skipped' => [], 'unknown_grades' => [], 'unknown_units' => [], 'lines' => []];
        if (count($rows) > self::IMPORT_MAX_ROWS) {
            $out['skipped'][] = 'Au-delà de ' . self::IMPORT_MAX_ROWS . ' lignes, le reste n’a pas été lu : importez le fichier en plusieurs fois.';
            $rows = array_slice($rows, 0, self::IMPORT_MAX_ROWS);
        }
        [$knownNames, $knownCallsigns] = $this->existingKeys($tenantId);
        [$ficheByName, $ficheByCall] = $this->ficheKeys($tenantId);
        $grades = $this->gradeIndex($tenantId);
        $units = $this->unitIndex($tenantId);
        $refuse = static function (array &$out, int $lineNo, string $name, string $why): void {
            $out['skipped'][] = 'Ligne ' . $lineNo . ' : ' . $why;
            $out['lines'][] = ['line' => $lineNo, 'name' => $name, 'result' => 'refused', 'detail' => $why];
        };

        foreach ($rows as $n => $r) {
            $lineNo = $n + 2;
            $display = trim((string) ($r['display_name'] ?? ''));
            $first = trim((string) ($r['first_name'] ?? ''));
            $last = trim((string) ($r['last_name'] ?? ''));
            $callsign = trim((string) ($r['callsign'] ?? ''));
            $emailRaw = trim((string) ($r['email'] ?? ''));
            $namedInRow = $display !== '' || $first !== '' || $last !== '';
            if ($display === '') {
                $display = trim($first . ' ' . $last);
            }
            if ($display === '') {
                $display = $callsign;
            }
            if ($display === '') {
                $refuse($out, $lineNo, '', 'pas de nom.');
                continue;
            }
            if ($first === '' && $last === '' && str_contains($display, ' ')) {
                [$first, $last] = explode(' ', $display, 2);
            }
            $nameKey = mb_strtolower($display, 'UTF-8');
            $callKey = mb_strtolower($callsign, 'UTF-8');
            $gradeId = null;
            $gradeLabel = trim((string) ($r['grade'] ?? ''));
            if ($gradeLabel !== '') {
                $gradeId = $grades[mb_strtolower($gradeLabel, 'UTF-8')] ?? null;
                if ($gradeId === null && !in_array($gradeLabel, $out['unknown_grades'], true)) {
                    $out['unknown_grades'][] = $gradeLabel;
                }
            }
            $unitId = null;
            $unitLabel = trim((string) ($r['unit'] ?? ''));
            if ($unitLabel !== '') {
                $unitId = $units[mb_strtolower($unitLabel, 'UTF-8')] ?? null;
                if ($unitId === null && !in_array($unitLabel, $out['unknown_units'], true)) {
                    $out['unknown_units'][] = $unitLabel;
                }
            }
            $steam = preg_replace('/\D+/', '', (string) ($r['steam_id'] ?? '')) ?? '';
            $steam = preg_match('/^7656119\d{10}$/', $steam) === 1 ? $steam : '';

            $ficheId = ($callKey !== '' ? ($ficheByCall[$callKey] ?? null) : null) ?? ($ficheByName[$nameKey] ?? null);
            if ($ficheId !== null) {
                try {
                    $changes = $this->updateFiche($tenantId, $actorUserId, (int) $ficheId, [
                        'first' => $namedInRow ? $first : '', 'last' => $namedInRow ? $last : '', 'callsign' => $callsign, 'display' => $namedInRow ? $display : '',
                        'grade_id' => $gradeId, 'unit_id' => $unitId, 'steam' => $steam, 'email' => $emailRaw,
                    ]);
                } catch (\InvalidArgumentException $e) {
                    $refuse($out, $lineNo, $display, $e->getMessage());
                    continue;
                } catch (\Throwable) {
                    $refuse($out, $lineNo, $display, 'mise à jour impossible.');
                    continue;
                }
                if ($changes === []) {
                    $out['lines'][] = ['line' => $lineNo, 'name' => $display, 'result' => 'unchanged', 'detail' => 'fiche déjà à jour.'];
                    continue;
                }
                $out['updated']++;
                $out['lines'][] = ['line' => $lineNo, 'name' => $display, 'result' => 'updated', 'detail' => implode(', ', $changes) . '.'];
                if ($onUpdated !== null) {
                    try {
                        $onUpdated((int) $ficheId, implode(' ; ', $changes));
                    } catch (\Throwable) {
                    }
                }
                continue;
            }
            if (isset($knownNames[$nameKey]) || ($callKey !== '' && isset($knownCallsigns[$callKey]))) {
                $refuse($out, $lineNo, $display, '« ' . $display . ' » correspond à un compte existant : non modifié.');
                continue;
            }
            if ($emailRaw !== '' && $this->emails !== null) {
                $check = $this->emails->check($tenantId, 0, $emailRaw);
                if (!$check['ok']) {
                    $refuse($out, $lineNo, $display, $check['message']);
                    continue;
                }
            }
            try {
                $newId = $this->create($tenantId, $actorUserId, [
                    'first_name' => $first,
                    'last_name' => $last,
                    'display_name' => $display,
                    'callsign' => $callsign,
                    'grade_id' => $gradeId,
                    'unit_id' => $unitId,
                    'steam_id' => $steam,
                ]);
            } catch (\Throwable $e) {
                $refuse($out, $lineNo, $display, $e->getMessage());
                continue;
            }
            $detail = 'fiche créée.';
            if ($emailRaw !== '') {
                $att = $this->emails?->apply($tenantId, $newId, $actorUserId, $emailRaw);
                $detail = $att === null ? 'fiche créée, e-mail ignoré.' : ($att['ok'] ? 'fiche créée avec son e-mail.' : 'fiche créée, e-mail non rattaché : ' . $att['message']);
            }
            if ($onCreated !== null) {
                try {
                    $onCreated($newId, $display);
                } catch (\Throwable) {
                }
            }
            $knownNames[$nameKey] = true;
            $ficheByName[$nameKey] = $newId;
            if ($callKey !== '') {
                $knownCallsigns[$callKey] = true;
                $ficheByCall[$callKey] = $newId;
            }
            $out['created']++;
            $out['lines'][] = ['line' => $lineNo, 'name' => $display, 'result' => 'created', 'detail' => $detail];
        }

        return $out;
    }

    /**
     * Met à jour une fiche sans compte depuis une ligne du tableur : seules les valeurs remplies et différentes changent.
     *
     * @param array{first: string, last: string, callsign: string, display: string, grade_id: ?int, unit_id: ?int, steam: string, email: string} $v
     * @return list<string> libellés des champs changés
     */
    private function updateFiche(int $tenantId, int $actorUserId, int $ficheId, array $v): array
    {
        $row = $this->userRepository->findById($ficheId, $tenantId);
        if ($row === null || (int) ($row['tenant_id'] ?? 0) !== $tenantId || !RosterOnlyMember::isRosterEmail((string) ($row['email'] ?? ''))) {
            throw new \InvalidArgumentException('cette personne n’est plus une fiche sans compte.');
        }
        $profile = $this->userProfileRepository->getByUserId($ficheId) ?? [];
        $row['first_name'] = (string) ($profile['first_name'] ?? '');
        $row['last_name'] = (string) ($profile['last_name'] ?? '');
        $changes = [];
        // Adresse d'abord : si elle est refusée, rien d'autre n'est modifié pour cette ligne.
        if ($v['email'] !== '' && $this->emails !== null) {
            $check = $this->emails->check($tenantId, $ficheId, $v['email']);
            if (!$check['ok']) {
                throw new \InvalidArgumentException($check['message']);
            }
        }
        if ($v['callsign'] !== '' && $v['callsign'] !== (string) ($row['callsign'] ?? '')
            && $this->userRepository->callsignExistsInTenant($tenantId, $v['callsign'], $ficheId)) {
            throw new \InvalidArgumentException('l’indicatif « ' . $v['callsign'] . ' » est déjà porté dans la communauté.');
        }
        $data = [];
        if ($v['callsign'] !== '' && $v['callsign'] !== (string) ($row['callsign'] ?? '')) {
            $data['callsign'] = mb_substr($v['callsign'], 0, 50, 'UTF-8');
            $changes[] = 'indicatif';
        }
        if ($v['display'] !== '' && $v['display'] !== (string) ($row['display_name'] ?? '')) {
            $data['display_name'] = mb_substr($v['display'], 0, 100, 'UTF-8');
            $changes[] = 'nom';
        }
        if ($v['grade_id'] !== null && $v['grade_id'] !== (int) ($row['grade_id'] ?? 0)) {
            $data['grade_id'] = $v['grade_id'];
            $changes[] = 'grade';
        }
        if ($v['steam'] !== '' && $v['steam'] !== (string) ($row['steam_id'] ?? '')) {
            $data['steam_id'] = $v['steam'];
            $changes[] = 'Steam ID';
        }
        if ($data !== []) {
            $this->userRepository->update($ficheId, $tenantId, $data);
        }
        if (($v['first'] !== '' && $v['first'] !== (string) ($row['first_name'] ?? '')) || ($v['last'] !== '' && $v['last'] !== (string) ($row['last_name'] ?? ''))) {
            $this->userProfileRepository->upsert($ficheId, [
                'first_name' => $v['first'] !== '' ? $v['first'] : (string) ($row['first_name'] ?? ''),
                'last_name' => $v['last'] !== '' ? $v['last'] : (string) ($row['last_name'] ?? ''),
            ]);
            $changes[] = 'prénom/nom';
        }
        if ($v['unit_id'] !== null) {
            $profile = $this->personnelProfileRepository->getByUserId($ficheId, $tenantId);
            if ((int) ($profile['primary_unit_id'] ?? 0) !== $v['unit_id']) {
                $this->personnelProfileRepository->ensureRecord($ficheId, $tenantId);
                $this->personnelProfileRepository->update($ficheId, ['primary_unit_id' => $v['unit_id']]);
                $this->personnelAssignmentRepository->syncPrimaryAssignmentFromDossier($ficheId, $v['unit_id'], 'Membre', 'Tableur importé par l’état-major');
                $changes[] = 'affectation';
            }
        }
        if ($v['email'] !== '' && $this->emails !== null) {
            $res = $this->emails->apply($tenantId, $ficheId, $actorUserId, $v['email']);
            if (!$res['ok']) {
                throw new \InvalidArgumentException($res['message']);
            }
            if ($res['changed']) {
                $changes[] = 'e-mail';
            }
        }

        return $changes;
    }

    /**
     * Tableur du tableau affiché : mêmes colonnes que l'import (UTF-8 avec BOM, séparateur « ; »). Toute cellule qui
     * commence par = + - @ (ou une tabulation) est préfixée d'une apostrophe : Excel ne l'exécute pas comme une formule.
     *
     * @param list<int> $ids personnes exportées, dans l'ordre voulu
     * @param array<int, string> $emails adresse rattachée par fiche
     */
    public function exportCsv(int $tenantId, array $ids, array $emails = []): string
    {
        $want = array_flip($ids);
        $users = [];
        foreach ($this->userRepository->allForTenant($tenantId) as $u) {
            $id = (int) ($u['id'] ?? 0);
            if (isset($want[$id]) && (int) ($u['tenant_id'] ?? 0) === $tenantId) {
                $users[$id] = $u;
            }
        }
        $gradeLabels = [];
        foreach ($this->gradeRepository->listActiveForDoctrinePicker($tenantId) as $g) {
            $gradeLabels[(int) ($g['id'] ?? 0)] = trim((string) ($g['label_short'] ?? '')) !== '' ? (string) $g['label_short'] : (string) ($g['label_long'] ?? '');
        }
        $meta = $this->unitRepository->hierarchyMetaByUnitId($tenantId);
        $unitLabels = [];
        foreach ($this->unitRepository->allForTenant($tenantId) as $u) {
            $uid = (int) ($u['id'] ?? 0);
            $unitLabels[$uid] = trim((string) ($meta[$uid]['path'] ?? '')) !== '' ? (string) $meta[$uid]['path'] : (string) ($u['name'] ?? '');
        }
        $unitOf = $this->primaryUnits(array_keys($users));
        $fh = fopen('php://temp', 'r+');
        fputcsv($fh, self::SHEET_COLUMNS, ';', '"', '\\');
        foreach ($ids as $id) {
            $u = $users[$id] ?? null;
            if ($u === null) {
                continue;
            }
            $real = RosterOnlyMember::isRosterEmail((string) ($u['email'] ?? '')) ? '' : (string) ($u['email'] ?? '');
            fputcsv($fh, array_map([self::class, 'safeCell'], [
                (string) ($u['first_name'] ?? ''),
                (string) ($u['last_name'] ?? ''),
                (string) ($u['callsign'] ?? ''),
                (string) ($emails[$id] ?? $real),
                (string) ($gradeLabels[(int) ($u['grade_id'] ?? 0)] ?? ''),
                (string) ($unitLabels[$unitOf[$id] ?? 0] ?? ''),
                (string) ($u['steam_id'] ?? ''),
            ]), ';', '"', '\\');
        }
        rewind($fh);
        $csv = "\xEF\xBB\xBF" . (stream_get_contents($fh) ?: '');
        fclose($fh);

        return $csv;
    }

    /** Modèle vierge : en-têtes et une ligne d'exemple (UTF-8 avec BOM, séparateur « ; »). */
    public static function templateCsv(): string
    {
        $fh = fopen('php://temp', 'r+');
        fputcsv($fh, self::SHEET_COLUMNS, ';', '"', '\\');
        fputcsv($fh, ['Jean', 'Martin', 'Alpha 1-1', 'jean.martin@exemple.fr', 'SGT', '1re Section', ''], ';', '"', '\\');
        rewind($fh);
        $csv = "\xEF\xBB\xBF" . (stream_get_contents($fh) ?: '');
        fclose($fh);

        return $csv;
    }

    /** Protection contre l'injection de formule : apostrophe devant une cellule qui commence par = + - @ (ou tabulation / retour chariot). */
    public static function safeCell(string $value): string
    {
        return $value !== '' && strpbrk($value[0], "=+-@\t\r") !== false ? "'" . $value : $value;
    }

    /**
     * @param list<int> $userIds
     * @return array<int, int> identifiant => unité principale
     */
    private function primaryUnits(array $userIds): array
    {
        $out = [];
        if ($userIds === []) {
            return $out;
        }
        try {
            foreach (array_chunk($userIds, 500) as $chunk) {
                $st = Database::getPdo()->prepare('SELECT user_id, primary_unit_id FROM personnel_profiles WHERE user_id IN (' . implode(',', array_fill(0, count($chunk), '?')) . ')');
                $st->execute(array_values($chunk));
                foreach ($st->fetchAll(\PDO::FETCH_ASSOC) ?: [] as $r) {
                    $out[(int) $r['user_id']] = (int) ($r['primary_unit_id'] ?? 0);
                }
            }
        } catch (\Throwable) {
        }

        return $out;
    }

    /**
     * Fiches sans compte de la communauté, par nom puis par indicatif (minuscules).
     *
     * @return array{0: array<string, int>, 1: array<string, int>}
     */
    private function ficheKeys(int $tenantId): array
    {
        $names = [];
        $calls = [];
        try {
            $stmt = Database::getPdo()->prepare(
                "SELECT u.id, u.display_name, u.callsign FROM users u WHERE u.tenant_id = ? AND u.email LIKE ? AND u.status NOT IN ('merged', 'deleted', 'left', 'banned')"
            );
            $stmt->execute([$tenantId, '%@' . RosterOnlyMember::EMAIL_DOMAIN]);
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $u) {
                $dn = mb_strtolower(trim((string) ($u['display_name'] ?? '')), 'UTF-8');
                $cs = mb_strtolower(trim((string) ($u['callsign'] ?? '')), 'UTF-8');
                if ($dn !== '') {
                    $names[$dn] ??= (int) $u['id'];
                }
                if ($cs !== '') {
                    $calls[$cs] ??= (int) $u['id'];
                }
            }
        } catch (\Throwable) {
        }

        return [$names, $calls];
    }

    /** @return array{0: array<string, true>, 1: array<string, true>} */
    private function existingKeys(int $tenantId): array
    {
        $names = [];
        $calls = [];
        try {
            $stmt = Database::getPdo()->prepare(
                'SELECT u.display_name, u.callsign FROM users u WHERE ' . $this->userRepository->sqlMemberOfTenantPredicate('u', $tenantId)
            );
            $stmt->execute();
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $u) {
                $dn = mb_strtolower(trim((string) ($u['display_name'] ?? '')), 'UTF-8');
                $cs = mb_strtolower(trim((string) ($u['callsign'] ?? '')), 'UTF-8');
                if ($dn !== '') {
                    $names[$dn] = true;
                }
                if ($cs !== '') {
                    $calls[$cs] = true;
                }
            }
        } catch (\Throwable) {
        }

        return [$names, $calls];
    }

    /** @return array<string, int> libellé court ou long (minuscules) => id */
    private function gradeIndex(int $tenantId): array
    {
        $idx = [];
        foreach ($this->gradeRepository->listActiveForDoctrinePicker($tenantId) as $g) {
            $id = (int) ($g['id'] ?? 0);
            foreach (['label_short', 'label_long', 'catalog_label_short', 'catalog_label_long', 'code', 'nato_code'] as $k) {
                $v = mb_strtolower(trim((string) ($g[$k] ?? '')), 'UTF-8');
                if ($id > 0 && $v !== '' && !isset($idx[$v])) {
                    $idx[$v] = $id;
                }
            }
        }

        return $idx;
    }

    /** @return array<string, int> nom ou chemin complet de l’unité (minuscules) => id */
    private function unitIndex(int $tenantId): array
    {
        $idx = [];
        $meta = $this->unitRepository->hierarchyMetaByUnitId($tenantId);
        foreach ($this->unitRepository->allForTenant($tenantId) as $u) {
            $id = (int) ($u['id'] ?? 0);
            foreach ([(string) ($u['name'] ?? ''), (string) ($u['short_label'] ?? ''), (string) ($meta[$id]['path'] ?? '')] as $v) {
                $v = mb_strtolower(trim($v), 'UTF-8');
                if ($id > 0 && $v !== '' && !isset($idx[$v])) {
                    $idx[$v] = $id;
                }
            }
        }

        return $idx;
    }
}
