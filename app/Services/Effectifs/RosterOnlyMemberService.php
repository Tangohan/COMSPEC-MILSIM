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
        'steam' => 'steam_id', 'steam id' => 'steam_id', 'steamid' => 'steam_id', 'steamid64' => 'steam_id', 'steam id64' => 'steam_id',
    ];

    public function __construct(
        private UserRepository $userRepository,
        private UserProfileRepository $userProfileRepository,
        private PersonnelProfileRepository $personnelProfileRepository,
        private PersonnelAssignmentRepository $personnelAssignmentRepository,
        private GradeRepository $gradeRepository,
        private UnitRepository $unitRepository,
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

    /**
     * Importe les lignes lues par parseCsv(). Les fiches déjà présentes (même indicatif ou même nom)
     * sont laissées telles quelles.
     *
     * @param list<array<string, string>> $rows
     * @return array{created: int, skipped: list<string>, unknown_grades: list<string>, unknown_units: list<string>}
     */
    public function import(int $tenantId, int $actorUserId, array $rows): array
    {
        $out = ['created' => 0, 'skipped' => [], 'unknown_grades' => [], 'unknown_units' => []];
        if (count($rows) > self::IMPORT_MAX_ROWS) {
            $out['skipped'][] = 'Au-delà de ' . self::IMPORT_MAX_ROWS . ' lignes, le reste n’a pas été lu : importez le fichier en plusieurs fois.';
            $rows = array_slice($rows, 0, self::IMPORT_MAX_ROWS);
        }
        [$knownNames, $knownCallsigns] = $this->existingKeys($tenantId);
        $grades = $this->gradeIndex($tenantId);
        $units = $this->unitIndex($tenantId);

        foreach ($rows as $n => $r) {
            $lineNo = $n + 2;
            $display = trim((string) ($r['display_name'] ?? ''));
            $first = trim((string) ($r['first_name'] ?? ''));
            $last = trim((string) ($r['last_name'] ?? ''));
            $callsign = trim((string) ($r['callsign'] ?? ''));
            if ($display === '') {
                $display = trim($first . ' ' . $last);
            }
            if ($display === '') {
                $display = $callsign;
            }
            if ($display === '') {
                $out['skipped'][] = 'Ligne ' . $lineNo . ' : pas de nom.';
                continue;
            }
            if ($first === '' && $last === '' && str_contains($display, ' ')) {
                [$first, $last] = explode(' ', $display, 2);
            }
            $nameKey = mb_strtolower($display, 'UTF-8');
            $callKey = mb_strtolower($callsign, 'UTF-8');
            if (isset($knownNames[$nameKey]) || ($callKey !== '' && isset($knownCallsigns[$callKey]))) {
                $out['skipped'][] = 'Ligne ' . $lineNo . ' : « ' . $display . ' » existe déjà.';
                continue;
            }
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
            try {
                $this->create($tenantId, $actorUserId, [
                    'first_name' => $first,
                    'last_name' => $last,
                    'display_name' => $display,
                    'callsign' => $callsign,
                    'grade_id' => $gradeId,
                    'unit_id' => $unitId,
                    'steam_id' => (string) ($r['steam_id'] ?? ''),
                ]);
            } catch (\Throwable $e) {
                $out['skipped'][] = 'Ligne ' . $lineNo . ' : ' . $e->getMessage();
                continue;
            }
            $knownNames[$nameKey] = true;
            if ($callKey !== '') {
                $knownCallsigns[$callKey] = true;
            }
            $out['created']++;
        }

        return $out;
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
