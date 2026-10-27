<?php

declare(strict_types=1);

namespace App\Services\Atak;

use App\Support\LazyDatabaseConnection;
use App\Support\SteamId;
use PDO;

/**
 * Rôles d'équipe de feu du téléphone ATAK natif (app Groupe > ÉQUIPES) partagés avec Athena.
 *
 * - Liste des rôles envoyée au jeu : fonctions de la communauté (personnel_job_roles, clé « A<id> ») et rôles créés
 *   en jeu (atak_custom_roles, clé « C_<NOM> »), avec une icône prise dans le jeu de rôles du mod.
 * - Rôle mémorisé d'un joueur (atak_role_prefs, par Steam ID) et rôle par défaut tiré de sa fonction principale.
 *
 * Transport sans nouvelle commande DLL (COMSPEC Link 2.0.63) :
 * - lecture : GetFireTeams [tenant, "roles:<steam>"] → GET /api/atak/fire-teams?kind=ephemeral&mapId=roles:<steam>,
 *   réponse au format « fire_teams » que la DLL sait déjà aplatir (lignes M : clé, icône~abrégé~origine, libellé) ;
 * - écriture : Squad.Sync (custom_roles + role_pref de chaque membre) → POST /api/atak/squads/sync.
 */
final class AtakRoleService
{
    use LazyDatabaseConnection;

    /** Rôles du mod dont l'icône peut servir à un rôle Athena ou créé en jeu. */
    public const ICONS = ['CDE', 'FUS', 'GRE', 'FM', 'TE', 'AT', 'MED', 'RAD', 'ENG', 'EOD', 'JTAC', 'DRN', 'PIL', 'DRV'];

    public const MAX_ROLES = 60;

    /** Mots-clés (sans accents, minuscules) → icône. Ordre important : « télépilote » avant « pilote ». */
    private const KEYWORDS = [
        'DRN' => ['drone', 'uav', 'telepilote', 'tele-pilote'],
        'JTAC' => ['jtac', 'appui feu', 'appui-feu', 'observateur', 'artill', 'mortier', 'fac '],
        'MED' => ['medic', 'medecin', 'sanitaire', 'infirm', 'sante', 'secour'],
        'RAD' => ['radio', 'transmission', 'transmetteur', 'rto', 'signal'],
        'PIL' => ['pilote', 'aviat', 'helico', 'aero'],
        'DRV' => ['conduct', 'chauffeur', 'blinde', 'equipage'],
        'TE' => ['tireur', 'sniper', 'precision', 'elite'],
        'EOD' => ['demin', 'eod', 'explosi', 'nedex'],
        'ENG' => ['sapeur', 'genie', 'engineer'],
        'AT' => ['antichar', 'anti-char', 'roquette', 'lance-'],
        'FM' => ['mitrail', 'minimi'],
        'GRE' => ['grenad'],
        'CDE' => ['chef', 'commandant', 'leader', 'officier', 'sergent', 'adjoint'],
    ];

    private const STOP_WORDS = ['de', 'du', 'des', 'la', 'le', 'les', 'd', 'l', 'et', 'a', 'au', 'aux', 'en'];

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo;
    }

    /**
     * mapId de GetFireTeams : « roles » ou « roles:<steam> » demande la liste des rôles. Null sinon.
     *
     * @return array{steam: ?string}|null
     */
    public static function parseRolesQuery(mixed $mapId): ?array
    {
        if (!is_string($mapId)) {
            return null;
        }
        $mapId = trim($mapId);
        if (strncasecmp($mapId, 'roles', 5) !== 0) {
            return null;
        }
        $rest = ltrim(substr($mapId, 5), ':');

        return ['steam' => $rest !== '' ? SteamId::normalize($rest) : null];
    }

    public static function fold(string $s): string
    {
        $s = mb_strtolower($s);
        $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
        if (is_string($t) && $t !== '') {
            $s = $t;
        }

        return strtolower(preg_replace('/[^a-z0-9 \'-]/', '', $s) ?? '');
    }

    /** Icône du mod la plus proche d'un libellé de fonction (« Infirmier de combat » → MED), FUS sinon. */
    public static function guessIcon(string $label): string
    {
        $f = ' ' . self::fold($label) . ' ';
        if (preg_match('/\b(fm|lmg|mg)\b/', $f)) {
            return 'FM';
        }
        foreach (self::KEYWORDS as $icon => $words) {
            foreach ($words as $w) {
                if (str_contains($f, $w)) {
                    return $icon;
                }
            }
        }
        if (preg_match('/\bat\b/', $f)) {
            return 'AT';
        }

        return 'FUS';
    }

    /** Abrégé affiché devant les noms (2 à 4 lettres) : sigle des mots utiles, sinon début du mot. */
    public static function shortLabel(string $label): string
    {
        $f = strtoupper(self::fold($label));
        $words = array_values(array_filter(
            preg_split('/[\s\'-]+/', $f) ?: [],
            static fn (string $w): bool => $w !== '' && !in_array(strtolower($w), self::STOP_WORDS, true)
        ));
        if ($words === []) {
            return '';
        }
        if (count($words) === 1) {
            $w = preg_replace('/[^A-Z0-9]/', '', $words[0]) ?? '';

            return strlen($w) <= 4 ? $w : substr($w, 0, 3);
        }
        $s = '';
        foreach ($words as $w) {
            $s .= substr(preg_replace('/[^A-Z0-9]/', '', $w) ?? '', 0, 1);
        }

        return substr($s, 0, 4);
    }

    /** Texte sûr pour la DLL et le SQF (ni tabulation, ni retour, ni | ~ ; « " »). */
    public static function clean(mixed $v, int $max): string
    {
        if (!is_scalar($v)) {
            return '';
        }
        $s = preg_replace('/[\x00-\x1F\x7F|~;"]+/u', ' ', (string) $v) ?? '';
        $s = trim(preg_replace('/\s+/u', ' ', $s) ?? '');

        return mb_substr($s, 0, $max);
    }

    public static function validKey(string $key): bool
    {
        return (bool) preg_match('/^(A[0-9]{1,9}|C_[A-Z0-9]{1,12}[0-9]?)$/', $key);
    }

    /**
     * Rôles créés en jeu, tels qu'envoyés dans Squad.Sync (custom_roles). Entrées invalides ignorées.
     *
     * @param array<string, mixed> $body
     * @return list<array{key: string, label: string, short: string, icon: string, by: ?string}>
     */
    public static function normalizeCustomRoles(array $body): array
    {
        $out = [];
        foreach (array_slice(is_array($body['custom_roles'] ?? null) ? $body['custom_roles'] : [], 0, self::MAX_ROLES) as $r) {
            if (!is_array($r)) {
                continue;
            }
            $key = strtoupper(self::clean($r['key'] ?? '', 16));
            $label = self::clean($r['label'] ?? '', 40);
            if (!str_starts_with($key, 'C_') || !self::validKey($key) || $label === '') {
                continue;
            }
            $icon = strtoupper(self::clean($r['icon'] ?? '', 8));
            $short = strtoupper(self::clean($r['short'] ?? '', 5));
            $out[$key] = [
                'key' => $key,
                'label' => $label,
                'short' => $short !== '' ? $short : self::shortLabel($label),
                'icon' => in_array($icon, self::ICONS, true) ? $icon : self::guessIcon($label),
                'by' => SteamId::normalize((string) ($r['by'] ?? '')),
            ];
        }

        return array_values($out);
    }

    /**
     * Rôles à mémoriser par joueur d'après la photo d'escouade normalisée (SquadSyncService::normalizePayload) :
     * le rôle mémorisé côté jeu (role_pref), sinon le rôle tenu. Jamais « chef d'équipe » (il dépend de l'équipe).
     *
     * @param array{teams?: list<array<string, mixed>>, unassigned?: list<array<string, mixed>>} $snap
     * @return array<string, array{key: string, label: string}> par Steam ID
     */
    public static function extractPrefs(array $snap): array
    {
        $members = $snap['unassigned'] ?? [];
        foreach ($snap['teams'] ?? [] as $t) {
            foreach ($t['members'] ?? [] as $m) {
                $members[] = $m;
            }
        }
        $out = [];
        foreach ($members as $m) {
            $uid = $m['uid'] ?? null;
            if (!is_string($uid) || $uid === '') {
                continue;
            }
            $pref = strtoupper((string) ($m['role_pref'] ?? ''));
            $role = strtoupper((string) ($m['role'] ?? ''));
            $key = $pref !== '' ? $pref : $role;
            if ($key === '' || $key === 'CDE' || !preg_match('/^[A-Z0-9_]{1,16}$/', $key)) {
                continue;
            }
            $out[$uid] = ['key' => $key, 'label' => $key === $role ? self::clean($m['role_label'] ?? '', 64) : ''];
        }

        return $out;
    }

    /**
     * Rôle Athena (fonction de la communauté) → entrée de la liste du jeu.
     *
     * @return array{key: string, label: string, short: string, icon: string, origin: string}|null
     */
    public static function jobRoleEntry(int $id, string $name): ?array
    {
        $label = self::clean($name, 40);
        if ($id < 1 || $label === '') {
            return null;
        }

        return ['key' => 'A' . $id, 'label' => $label, 'short' => self::shortLabel($label), 'icon' => self::guessIcon($label), 'origin' => 'ATHENA'];
    }

    /**
     * Réponse « fire_teams » lue par la DLL (SimplifyFireTeamsJson) : équipe 1 = liste des rôles,
     * équipe 2 = rôle mémorisé (PREF), rôle de la fonction principale (JOB) et indicatif de la fiche (CS) du joueur.
     *
     * @param list<array{key: string, label: string, short: string, icon: string, origin: string}> $roles
     * @param array{key: string, label: string}|null $pref
     * @param array{key: string, label: string}|null $job
     * @return array<string, mixed>
     */
    public static function catalogPayload(array $roles, ?array $pref, ?array $job, string $callsign = ''): array
    {
        $members = [];
        $seen = [];
        foreach ($roles as $r) {
            if (isset($seen[$r['key']]) || count($members) >= self::MAX_ROLES) {
                continue;
            }
            $seen[$r['key']] = true;
            $members[] = [
                'callsign' => $r['key'],
                'role' => $r['icon'] . '~' . ($r['short'] !== '' ? $r['short'] : 'ROL') . '~' . $r['origin'],
                'display_name' => $r['label'],
            ];
        }
        $prefs = [];
        foreach (['PREF' => $pref, 'JOB' => $job] as $tag => $p) {
            if ($p !== null && $p['key'] !== '') {
                $prefs[] = ['callsign' => $tag, 'role' => $p['key'], 'display_name' => $p['label'] !== '' ? $p['label'] : $p['key']];
            }
        }
        $callsign = self::cleanCallsign($callsign);
        if ($callsign !== '') {
            $prefs[] = ['callsign' => 'CS', 'role' => $callsign, 'display_name' => 'ATHENA'];
        }

        return [
            'ok' => true,
            'roles' => true,
            'fire_teams' => [
                ['id' => 1, 'label' => 'ROLES', 'color' => '', 'map_id' => null, 'kind' => 'role_catalog', 'member_count' => count($members), 'members' => $members],
                ['id' => 2, 'label' => 'PREF', 'color' => '', 'map_id' => null, 'kind' => 'role_pref', 'member_count' => count($prefs), 'members' => $prefs],
            ],
        ];
    }

    public function schemaReady(): bool
    {
        return $this->tableExists('atak_custom_roles') && $this->tableExists('atak_role_prefs');
    }

    /** Liste des rôles et préférences d'un joueur, prête pour Response::json. */
    public function catalogFor(int $tenantId, ?string $steam): array
    {
        $roles = [];
        $job = null;
        $pref = null;
        $callsign = '';
        try {
            if ($this->tableExists('personnel_job_roles')) {
                $st = $this->pdo()->prepare('SELECT id, name FROM personnel_job_roles WHERE tenant_id = ? ORDER BY sort_order ASC, name ASC LIMIT 40');
                $st->execute([$tenantId]);
                foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                    $e = self::jobRoleEntry((int) $row['id'], (string) $row['name']);
                    if ($e !== null) {
                        $roles[] = $e;
                    }
                }
            }
            if ($this->tableExists('atak_custom_roles')) {
                $st = $this->pdo()->prepare('SELECT role_key, label, short_label, icon_key FROM atak_custom_roles WHERE tenant_id = ? ORDER BY label ASC LIMIT ' . self::MAX_ROLES);
                $st->execute([$tenantId]);
                foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                    $roles[] = [
                        'key' => (string) $row['role_key'], 'label' => self::clean($row['label'], 40), 'short' => (string) $row['short_label'],
                        'icon' => in_array($row['icon_key'], self::ICONS, true) ? (string) $row['icon_key'] : 'FUS', 'origin' => 'CUSTOM',
                    ];
                }
            }
            if ($steam !== null) {
                if ($this->tableExists('atak_role_prefs')) {
                    $st = $this->pdo()->prepare('SELECT role_key, role_label FROM atak_role_prefs WHERE tenant_id = ? AND steam_id = ? LIMIT 1');
                    $st->execute([$tenantId, $steam]);
                    $row = $st->fetch(PDO::FETCH_ASSOC);
                    if ($row) {
                        $pref = ['key' => (string) $row['role_key'], 'label' => self::clean($row['role_label'] ?? '', 40)];
                    }
                }
                if ($this->tableExists('personnel_profile_job_roles')) {
                    $st = $this->pdo()->prepare(
                        'SELECT r.id, r.name FROM users u
                         INNER JOIN personnel_profile_job_roles pj ON pj.tenant_id = u.tenant_id AND pj.user_id = u.id
                         INNER JOIN personnel_job_roles r ON r.id = pj.personnel_job_role_id AND r.tenant_id = pj.tenant_id
                         WHERE u.tenant_id = ? AND u.steam_id = ?
                         ORDER BY pj.is_primary DESC, pj.sort_order ASC, pj.id ASC LIMIT 1'
                    );
                    $st->execute([$tenantId, $steam]);
                    $row = $st->fetch(PDO::FETCH_ASSOC);
                    $e = $row ? self::jobRoleEntry((int) $row['id'], (string) $row['name']) : null;
                    if ($e !== null) {
                        $job = ['key' => $e['key'], 'label' => $e['label']];
                        if (!in_array($e['key'], array_column($roles, 'key'), true)) {
                            $roles[] = $e;
                        }
                    }
                }
                $callsign = $this->webCallsign($tenantId, $steam);
            }
        } catch (\Throwable) {
            // Liste partielle plutôt qu'une erreur : le jeu garde ses rôles intégrés.
        }

        return self::catalogPayload($roles, $pref, $job, $callsign);
    }

    /**
     * Enregistre ce que Squad.Sync rapporte : rôles créés en jeu et rôle mémorisé de chaque joueur.
     *
     * @param array<string, mixed> $body corps brut (custom_roles)
     * @param array<string, mixed> $snap photo normalisée
     * @return array{custom_roles: int, prefs: int}
     */
    public function recordFromSync(int $tenantId, array $body, array $snap): array
    {
        if ($tenantId < 1 || !$this->schemaReady()) {
            return ['custom_roles' => 0, 'prefs' => 0];
        }
        $pdo = $this->pdo();
        $custom = self::normalizeCustomRoles($body);
        if ($custom !== []) {
            $st = $pdo->prepare(
                'INSERT INTO atak_custom_roles (tenant_id, role_key, label, short_label, icon_key, created_by_steam)
                 VALUES (?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE label = VALUES(label), short_label = VALUES(short_label), icon_key = VALUES(icon_key)'
            );
            foreach ($custom as $r) {
                $st->execute([$tenantId, $r['key'], $r['label'], mb_substr($r['short'], 0, 8), $r['icon'], $r['by']]);
            }
        }
        $prefs = self::extractPrefs($snap);
        if ($prefs !== []) {
            $st = $pdo->prepare(
                'INSERT INTO atak_role_prefs (tenant_id, steam_id, role_key, role_label) VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                    role_label = IF(VALUES(role_label) IS NULL AND role_key = VALUES(role_key), role_label, VALUES(role_label)),
                    role_key = VALUES(role_key), updated_at = NOW()'
            );
            foreach ($prefs as $steam => $p) {
                $st->execute([$tenantId, $steam, $p['key'], $p['label'] !== '' ? $p['label'] : null]);
            }
        }

        $callsigns = 0;
        foreach (self::extractCallsigns($snap) as $steam => $cs) {
            $callsigns += $this->applyGameCallsign($tenantId, (string) $steam, $cs) ? 1 : 0;
        }

        return ['custom_roles' => count($custom), 'prefs' => count($prefs), 'callsigns' => $callsigns];
    }

    /**
     * Indicatifs choisis par les joueurs dans le téléphone (callsign_custom), par SteamID.
     *
     * @param array<string, mixed> $snap photo normalisée
     * @return array<string, string>
     */
    public static function extractCallsigns(array $snap): array
    {
        $members = $snap['unassigned'] ?? [];
        foreach ($snap['teams'] ?? [] as $t) {
            foreach ($t['members'] ?? [] as $m) {
                $members[] = $m;
            }
        }
        $out = [];
        foreach ($members as $m) {
            $uid = $m['uid'] ?? null;
            $cs = self::cleanCallsign((string) ($m['callsign'] ?? ''));
            if (!is_string($uid) || $uid === '' || empty($m['callsign_custom']) || $cs === '') {
                continue;
            }
            $out[$uid] = $cs;
        }

        return $out;
    }

    /** Indicatif lisible en jeu et sur le site : 2 à 24 caractères, sans balise ni séparateur du protocole DLL. */
    public static function cleanCallsign(string $cs): string
    {
        $cs = trim((string) preg_replace('/[<>&|"\x00-\x1F\x7F]/u', '', $cs));
        $cs = mb_substr($cs, 0, 24);

        return mb_strlen($cs) >= 2 ? $cs : '';
    }

    /** Indicatif de la fiche du compte lié à ce SteamID (fiche personnel, sinon compte). */
    private function webCallsign(int $tenantId, string $steam): string
    {
        $st = $this->pdo()->prepare(
            'SELECT u.callsign AS ucs' . ($this->tableExists('personnel_profiles') ? ', pp.callsign AS pcs' : ', NULL AS pcs') . ' FROM users u'
            . ($this->tableExists('personnel_profiles') ? ' LEFT JOIN personnel_profiles pp ON pp.user_id = u.id' : '')
            . ' WHERE u.tenant_id = ? AND u.steam_id = ? LIMIT 1'
        );
        $st->execute([$tenantId, $steam]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return '';
        }
        $p = self::cleanCallsign((string) ($row['pcs'] ?? ''));

        return $p !== '' ? $p : self::cleanCallsign((string) ($row['ucs'] ?? ''));
    }

    /** Indicatif choisi en jeu : écrit sur le compte et la fiche personnel du joueur lié, s'il a changé. */
    private function applyGameCallsign(int $tenantId, string $steam, string $cs): bool
    {
        try {
            $st = $this->pdo()->prepare('SELECT id, callsign FROM users WHERE tenant_id = ? AND steam_id = ? LIMIT 1');
            $st->execute([$tenantId, $steam]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if (!$row || $this->webCallsign($tenantId, $steam) === $cs) {
                return false;
            }
            $userId = (int) $row['id'];
            $this->pdo()->prepare('UPDATE users SET callsign = ? WHERE id = ? AND tenant_id = ?')->execute([$cs, $userId, $tenantId]);
            if ($this->tableExists('personnel_profiles')) {
                $this->pdo()->prepare('UPDATE personnel_profiles SET callsign = ? WHERE user_id = ?')->execute([$cs, $userId]);
            }

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function tableExists(string $table): bool
    {
        static $cache = [];
        if (isset($cache[$table])) {
            return $cache[$table];
        }
        try {
            $st = $this->pdo()->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1');
            $st->execute([$table]);

            return $cache[$table] = (bool) $st->fetchColumn();
        } catch (\Throwable) {
            return false;
        }
    }
}
