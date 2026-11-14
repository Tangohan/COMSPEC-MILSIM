<?php

declare(strict_types=1);

namespace App\Services\Jnet;

use App\Core\Database;
use App\Services\Media\ImageCompressionService;
use App\Support\TerrainUploadedImage;
use PDO;

/**
 * Espace dédié de chaque unité dans le JNET (table jnet_unit_spaces) : thème, couleur, devise, mot d'accueil, libellé
 * d'accès, insigne, bannière et écran d'entrée. Sans ligne (ou table absente), l'espace garde l'apparence par défaut,
 * avec la couleur, l'insigne et la devise de l'ORBAT.
 *
 * Personnalisent : le chef de l'unité ou d'une unité au-dessus (units.commander_user_id), et l'organisation (accès au
 * back-office, gestion de l'ORBAT).
 */
final class JnetUnitSpaceSettings
{
    /** Thèmes : fond, panneau, texte, accent par défaut. Clé courte, stockée en base. */
    public const THEMES = [
        'athena' => ['label' => 'Athena (noir)', 'bg' => '#050505', 'panel' => '#0f1412', 'ink' => '#f4f4f0', 'accent' => '#34d399'],
        'operations' => ['label' => 'Opérations', 'bg' => '#06110c', 'panel' => '#0c1b14', 'ink' => '#e6f2ec', 'accent' => '#2fd08f'],
        'nuit' => ['label' => 'Nuit', 'bg' => '#050a14', 'panel' => '#0b1526', 'ink' => '#e5edf8', 'accent' => '#5aa9ff'],
        'desert' => ['label' => 'Désert', 'bg' => '#151009', 'panel' => '#231b10', 'ink' => '#f4eadb', 'accent' => '#e0a64a'],
        'marine' => ['label' => 'Marine', 'bg' => '#03111b', 'panel' => '#082233', 'ink' => '#e2f1f8', 'accent' => '#3fc1d9'],
        'acier' => ['label' => 'Acier', 'bg' => '#0e1012', 'panel' => '#181b1f', 'ink' => '#eceef0', 'accent' => '#c3cad2'],
        'garde' => ['label' => 'Garde', 'bg' => '#130707', 'panel' => '#220d0d', 'ink' => '#f6e9e9', 'accent' => '#e25555'],
    ];

    public const DEFAULT_THEME = 'athena';

    public const TAGLINE_MAX = 140;
    public const WELCOME_MAX = 600;
    public const ACCESS_LABEL_MAX = 40;
    public const DEFAULT_ACCESS_LABEL = 'Accès restreint';

    private const UPLOAD_PREFIX = 'uploads/jnet-espaces/';
    private const UPLOAD_MAX_BYTES = 8 * 1024 * 1024;

    private ?bool $ready = null;
    private ?bool $tablesReady = null;

    /** @var array<string, array<string, mixed>> */
    private array $rulesCache = [];

    public function __construct(private ?PDO $pdo = null)
    {
    }

    /**
     * Réglages par défaut d'un espace (rien de personnalisé).
     *
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'custom' => false,
            'theme' => self::DEFAULT_THEME,
            'accent_color' => '',
            'tagline' => '',
            'welcome_text' => '',
            'access_label' => '',
            'emblem_path' => '',
            'banner_path' => '',
            'entry_screen' => true,
            'hidden_modules' => [],
            'require_ack' => false,
            'auto_relay' => false,
            'post_min' => 'member',
        ];
    }

    /**
     * Valeurs du formulaire, nettoyées (sans les images, traitées à part).
     *
     * @param array<string, mixed> $in
     * @return array{theme: string, accent_color: string, tagline: string, welcome_text: string, access_label: string, entry_screen: bool}
     */
    public static function normalize(array $in): array
    {
        $theme = (string) ($in['theme'] ?? '');

        return [
            'theme' => isset(self::THEMES[$theme]) ? $theme : self::DEFAULT_THEME,
            'accent_color' => !empty($in['accent_custom']) ? self::color((string) ($in['accent_color'] ?? '')) : '',
            'tagline' => self::line((string) ($in['tagline'] ?? ''), self::TAGLINE_MAX),
            'welcome_text' => self::text((string) ($in['welcome_text'] ?? ''), self::WELCOME_MAX),
            'access_label' => self::line((string) ($in['access_label'] ?? ''), self::ACCESS_LABEL_MAX),
            'entry_screen' => !empty($in['entry_screen']),
        ];
    }

    /** Couleur « #rrggbb » en minuscules, ou chaîne vide. */
    public static function color(string $raw): string
    {
        $raw = strtolower(trim($raw));
        if (preg_match('/^#?([0-9a-f]{3})$/', $raw, $m) === 1) {
            $raw = '#' . $m[1][0] . $m[1][0] . $m[1][1] . $m[1][1] . $m[1][2] . $m[1][2];
        }

        return preg_match('/^#?[0-9a-f]{6}$/', $raw) === 1 ? '#' . ltrim($raw, '#') : '';
    }

    /**
     * Apparence calculée d'un espace : thème, couleur (réglage, sinon ORBAT, sinon thème), devise (réglage, sinon ORBAT),
     * images en URL publiques.
     *
     * @param array<string, mixed> $settings ligne de find() ou defaults()
     * @param array<string, mixed> $space nœud de l'espace (accent, badge, motto, label)
     * @return array<string, mixed>
     */
    public static function look(array $settings, array $space): array
    {
        $themeKey = (string) ($settings['theme'] ?? self::DEFAULT_THEME);
        $theme = self::THEMES[$themeKey] ?? self::THEMES[self::DEFAULT_THEME];
        $accent = self::color((string) ($settings['accent_color'] ?? ''));
        if ($accent === '') {
            $accent = self::color((string) ($space['accent'] ?? ''));
        }
        if ($accent === '') {
            $accent = $theme['accent'];
        }
        $tagline = trim((string) ($settings['tagline'] ?? ''));
        if ($tagline === '') {
            $tagline = trim((string) ($space['motto'] ?? ''));
        }
        $emblem = self::publicUrl((string) ($settings['emblem_path'] ?? ''));
        if ($emblem === null) {
            $badge = $space['badge'] ?? null;
            $emblem = is_string($badge) && $badge !== '' ? $badge : null;
        }
        $label = (string) ($space['label'] ?? 'Unité');
        $access = trim((string) ($settings['access_label'] ?? ''));

        return [
            'theme' => isset(self::THEMES[$themeKey]) ? $themeKey : self::DEFAULT_THEME,
            'bg' => $theme['bg'],
            'panel' => $theme['panel'],
            'ink' => $theme['ink'],
            'accent' => $accent,
            'tagline' => $tagline,
            'welcome' => trim((string) ($settings['welcome_text'] ?? '')),
            'access_label' => $access !== '' ? $access : self::DEFAULT_ACCESS_LABEL,
            'emblem' => $emblem,
            'banner' => self::publicUrl((string) ($settings['banner_path'] ?? '')),
            'monogram' => mb_strtoupper(mb_substr(preg_replace('/[^\p{L}\p{N}]/u', '', $label) ?: 'U', 0, 2)),
            'entry_screen' => (bool) ($settings['entry_screen'] ?? true),
            'custom' => (bool) ($settings['custom'] ?? false),
        ];
    }

    /** Style en ligne des variables du thème (valeurs déjà validées : couleurs #rrggbb du catalogue ou nettoyées). */
    public static function cssVars(array $look): string
    {
        return '--jn-unit:' . $look['accent'] . ';--jn-space-bg:' . $look['bg'] . ';--jn-space-panel:' . $look['panel']
            . ';--jn-space-ink:' . $look['ink'];
    }

    public function ready(): bool
    {
        if ($this->ready === null) {
            try {
                $this->ready = $this->db()->query("SHOW TABLES LIKE 'jnet_unit_spaces'")->fetchColumn() !== false;
            } catch (\Throwable) {
                $this->ready = false;
            }
        }

        return $this->ready;
    }

    /** @return array<string, mixed> */
    public function find(int $tenantId, int $unitId): array
    {
        if ($unitId <= 0 || !$this->ready()) {
            return self::defaults();
        }
        try {
            $stmt = $this->db()->prepare('SELECT * FROM jnet_unit_spaces WHERE tenant_id = ? AND unit_id = ? LIMIT 1');
            $stmt->execute([$tenantId, $unitId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return self::defaults();
        }
        if (!is_array($row)) {
            return self::defaults();
        }

        return [
            'custom' => true,
            'theme' => isset(self::THEMES[(string) $row['theme']]) ? (string) $row['theme'] : self::DEFAULT_THEME,
            'accent_color' => self::color((string) ($row['accent_color'] ?? '')),
            'tagline' => (string) ($row['tagline'] ?? ''),
            'welcome_text' => (string) ($row['welcome_text'] ?? ''),
            'access_label' => (string) ($row['access_label'] ?? ''),
            'emblem_path' => (string) ($row['emblem_path'] ?? ''),
            'banner_path' => (string) ($row['banner_path'] ?? ''),
            'entry_screen' => (int) ($row['entry_screen'] ?? 1) === 1,
            'hidden_modules' => JnetUnitWorkspaceService::parseHidden($row['hidden_modules'] ?? ''),
            'require_ack' => (int) ($row['require_ack'] ?? 0) === 1,
            'auto_relay' => (int) ($row['auto_relay'] ?? 0) === 1,
            'post_min' => JnetSpaceRules::postMin($row['post_min'] ?? 'member'),
        ];
    }

    /**
     * Réglages de tous les espaces de la communauté, en une requête (page d'accueil des membres).
     *
     * @return array<int, array<string, mixed>> unité => réglages au format de find()
     */
    public function findAll(int $tenantId): array
    {
        if (!$this->ready()) {
            return [];
        }
        try {
            $stmt = $this->db()->prepare('SELECT * FROM jnet_unit_spaces WHERE tenant_id = ?');
            $stmt->execute([$tenantId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable) {
            return [];
        }
        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row['unit_id']] = [
                'custom' => true,
                'theme' => isset(self::THEMES[(string) $row['theme']]) ? (string) $row['theme'] : self::DEFAULT_THEME,
                'accent_color' => self::color((string) ($row['accent_color'] ?? '')),
                'tagline' => (string) ($row['tagline'] ?? ''),
                'welcome_text' => (string) ($row['welcome_text'] ?? ''),
                'access_label' => (string) ($row['access_label'] ?? ''),
                'emblem_path' => (string) ($row['emblem_path'] ?? ''),
                'banner_path' => (string) ($row['banner_path'] ?? ''),
                'entry_screen' => (int) ($row['entry_screen'] ?? 1) === 1,
                'hidden_modules' => JnetUnitWorkspaceService::parseHidden($row['hidden_modules'] ?? ''),
                'require_ack' => (int) ($row['require_ack'] ?? 0) === 1,
                'auto_relay' => (int) ($row['auto_relay'] ?? 0) === 1,
                'post_min' => JnetSpaceRules::postMin($row['post_min'] ?? 'member'),
            ];
        }

        return $out;
    }

    /** La colonne hidden_modules (étape jnet_unit_workspace) est-elle en place ? */
    public function modulesReady(): bool
    {
        if (!$this->ready()) {
            return false;
        }
        try {
            $this->db()->query('SELECT hidden_modules FROM jnet_unit_spaces LIMIT 0');

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Modules masqués par le chef (« taches,pages »). Écrit seulement si la colonne existe ; crée la ligne si besoin.
     */
    public function saveHiddenModules(int $tenantId, int $unitId, string $hiddenCsv, int $userId): void
    {
        if (!$this->modulesReady()) {
            return;
        }
        $this->db()->prepare(
            'INSERT INTO jnet_unit_spaces (tenant_id, unit_id, theme, hidden_modules, updated_by, updated_at) VALUES (?, ?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE hidden_modules = VALUES(hidden_modules), updated_by = IF(VALUES(updated_by) IS NULL, updated_by, VALUES(updated_by)), updated_at = NOW()'
        )->execute([$tenantId, $unitId, self::DEFAULT_THEME, $hiddenCsv !== '' ? $hiddenCsv : null, $userId > 0 ? $userId : null]);
    }

    /**
     * Unités que le lecteur commande (commandant désigné dans l'ORBAT).
     *
     * @return list<int>
     */
    public function commandedUnitIds(int $tenantId, int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }
        try {
            $stmt = $this->db()->prepare('SELECT id FROM units WHERE tenant_id = ? AND commander_user_id = ?');
            $stmt->execute([$tenantId, $userId]);

            return array_values(array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []));
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @param array{theme: string, accent_color: string, tagline: string, welcome_text: string, access_label: string, entry_screen: bool} $values
     */
    public function save(int $tenantId, int $unitId, array $values, string $emblemPath, string $bannerPath, int $userId): void
    {
        $stmt = $this->db()->prepare(
            'INSERT INTO jnet_unit_spaces (tenant_id, unit_id, theme, accent_color, tagline, welcome_text, access_label, emblem_path, banner_path, entry_screen, updated_by, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE theme = VALUES(theme), accent_color = VALUES(accent_color), tagline = VALUES(tagline),
               welcome_text = VALUES(welcome_text), access_label = VALUES(access_label), emblem_path = VALUES(emblem_path),
               banner_path = VALUES(banner_path), entry_screen = VALUES(entry_screen), updated_by = VALUES(updated_by), updated_at = NOW()'
        );
        $stmt->execute([
            $tenantId,
            $unitId,
            $values['theme'],
            $values['accent_color'] !== '' ? $values['accent_color'] : null,
            $values['tagline'] !== '' ? $values['tagline'] : null,
            $values['welcome_text'] !== '' ? $values['welcome_text'] : null,
            $values['access_label'] !== '' ? $values['access_label'] : null,
            $emblemPath !== '' ? $emblemPath : null,
            $bannerPath !== '' ? $bannerPath : null,
            $values['entry_screen'] ? 1 : 0,
            $userId > 0 ? $userId : null,
        ]);
    }

    /**
     * Rétablit l'apparence par défaut. Les rubriques masquées et les réglages d'ordres (prise en compte obligatoire,
     * relais automatique, publication réservée) restent : un retour à l'apparence ne lève pas une restriction.
     */
    public function reset(int $tenantId, int $unitId): void
    {
        $row = $this->find($tenantId, $unitId);
        $this->db()->prepare('DELETE FROM jnet_unit_spaces WHERE tenant_id = ? AND unit_id = ?')->execute([$tenantId, $unitId]);
        self::deleteImage((string) ($row['emblem_path'] ?? ''), $tenantId);
        self::deleteImage((string) ($row['banner_path'] ?? ''), $tenantId);
        $hidden = implode(',', (array) ($row['hidden_modules'] ?? []));
        if ($hidden !== '') {
            $this->saveHiddenModules($tenantId, $unitId, $hidden, 0);
        }
        if ($this->rulesColumnsReady() && (!empty($row['require_ack']) || !empty($row['auto_relay']) || ($row['post_min'] ?? 'member') !== 'member')) {
            $this->saveRules($tenantId, $unitId, !empty($row['require_ack']), !empty($row['auto_relay']), (string) $row['post_min'], 0);
        }
        unset($this->rulesCache[$tenantId . ':' . $unitId]);
    }

    // ——— Réglages d'ordres, accès par unité, gestionnaires (étape jnet_unit_space_rules) ———

    /** Les colonnes require_ack, auto_relay et post_min de jnet_unit_spaces sont-elles en place ? */
    public function rulesColumnsReady(): bool
    {
        if (!$this->ready()) {
            return false;
        }
        try {
            $this->db()->query('SELECT require_ack, auto_relay, post_min FROM jnet_unit_spaces LIMIT 0');

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /** Les tables d'accès par unité et de gestionnaires sont-elles en place ? */
    public function rulesTablesReady(): bool
    {
        if ($this->tablesReady === null) {
            try {
                $this->db()->query('SELECT 1 FROM jnet_unit_space_access LIMIT 0');
                $this->db()->query('SELECT 1 FROM jnet_unit_space_managers LIMIT 0');
                $this->tablesReady = true;
            } catch (\Throwable) {
                $this->tablesReady = false;
            }
        }

        return $this->tablesReady;
    }

    /**
     * Tous les réglages de règles d'un espace, au format de JnetSpaceRules::defaults() : les trois réglages, les unités
     * autorisées avec leur droit, les gestionnaires. Sans l'étape de migration, les valeurs par défaut (rien de restreint).
     *
     * @return array{require_ack: bool, auto_relay: bool, post_min: string, access: array<int, string>, managers: list<int>}
     */
    public function rules(int $tenantId, int $unitId): array
    {
        $rules = JnetSpaceRules::defaults();
        if ($unitId <= 0 || !$this->ready()) {
            return $rules;
        }
        $key = $tenantId . ':' . $unitId;
        if (isset($this->rulesCache[$key])) {
            return $this->rulesCache[$key];
        }
        $row = $this->find($tenantId, $unitId);
        $rules['require_ack'] = !empty($row['require_ack']);
        $rules['auto_relay'] = !empty($row['auto_relay']);
        $rules['post_min'] = JnetSpaceRules::postMin($row['post_min'] ?? 'member');
        if ($this->rulesTablesReady()) {
            try {
                $st = $this->db()->prepare('SELECT grantee_unit_id, level FROM jnet_unit_space_access WHERE tenant_id = ? AND unit_id = ? ORDER BY grantee_unit_id');
                $st->execute([$tenantId, $unitId]);
                foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
                    if (isset(JnetSpaceRules::ACCESS_LEVELS[(string) $r['level']])) {
                        $rules['access'][(int) $r['grantee_unit_id']] = (string) $r['level'];
                    }
                }
                $st = $this->db()->prepare('SELECT user_id FROM jnet_unit_space_managers WHERE tenant_id = ? AND unit_id = ? ORDER BY user_id');
                $st->execute([$tenantId, $unitId]);
                $rules['managers'] = array_values(array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN) ?: []));
            } catch (\Throwable) {
            }
        }

        return $this->rulesCache[$key] = $rules;
    }

    /** Écrit les trois réglages ; crée la ligne de l'espace si besoin. Sans les colonnes, ne fait rien. */
    public function saveRules(int $tenantId, int $unitId, bool $requireAck, bool $autoRelay, string $postMin, int $userId): void
    {
        if (!$this->rulesColumnsReady()) {
            return;
        }
        $this->db()->prepare(
            'INSERT INTO jnet_unit_spaces (tenant_id, unit_id, theme, require_ack, auto_relay, post_min, updated_by, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE require_ack = VALUES(require_ack), auto_relay = VALUES(auto_relay), post_min = VALUES(post_min),
               updated_by = IF(VALUES(updated_by) IS NULL, updated_by, VALUES(updated_by)), updated_at = NOW()'
        )->execute([$tenantId, $unitId, self::DEFAULT_THEME, $requireAck ? 1 : 0, $autoRelay ? 1 : 0, JnetSpaceRules::postMin($postMin), $userId > 0 ? $userId : null]);
        unset($this->rulesCache[$tenantId . ':' . $unitId]);
    }

    /**
     * Remplace la liste des unités autorisées (déjà nettoyée par JnetSpaceRules::normalizeAccess).
     *
     * @param array<int, string> $rows unité => droit
     */
    public function saveAccess(int $tenantId, int $unitId, array $rows, int $userId): void
    {
        if (!$this->rulesTablesReady()) {
            return;
        }
        $pdo = $this->db();
        $pdo->beginTransaction();
        try {
            $pdo->prepare('DELETE FROM jnet_unit_space_access WHERE tenant_id = ? AND unit_id = ?')->execute([$tenantId, $unitId]);
            $ins = $pdo->prepare('INSERT INTO jnet_unit_space_access (tenant_id, unit_id, grantee_unit_id, level, created_by) VALUES (?, ?, ?, ?, ?)');
            foreach ($rows as $grantee => $level) {
                $ins->execute([$tenantId, $unitId, (int) $grantee, (string) $level, $userId > 0 ? $userId : null]);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        unset($this->rulesCache[$tenantId . ':' . $unitId]);
    }

    /**
     * Réserve le relais automatique d'un échange pour cet espace : vrai la première fois seulement (clé primaire du
     * journal), ce qui garantit qu'un ordre n'est relayé qu'une fois par espace, même en cas de nouvel essai.
     */
    public function claimAutoRelay(int $tenantId, int $unitId, int $exchangeId): bool
    {
        if (!$this->rulesTablesReady()) {
            return false;
        }
        try {
            $st = $this->db()->prepare('INSERT IGNORE INTO jnet_unit_auto_relays (tenant_id, unit_id, exchange_id) VALUES (?, ?, ?)');
            $st->execute([$tenantId, $unitId, $exchangeId]);

            return $st->rowCount() === 1;
        } catch (\Throwable) {
            return false;
        }
    }

    public function noteAutoRelay(int $tenantId, int $unitId, int $exchangeId, int $relayExchangeId): void
    {
        try {
            $this->db()->prepare('UPDATE jnet_unit_auto_relays SET relay_exchange_id = ? WHERE tenant_id = ? AND unit_id = ? AND exchange_id = ?')
                ->execute([$relayExchangeId, $tenantId, $unitId, $exchangeId]);
        } catch (\Throwable) {
        }
    }

    /** @param list<int> $userIds */
    public function saveManagers(int $tenantId, int $unitId, array $userIds, int $userId): void
    {
        if (!$this->rulesTablesReady()) {
            return;
        }
        $pdo = $this->db();
        $pdo->beginTransaction();
        try {
            $pdo->prepare('DELETE FROM jnet_unit_space_managers WHERE tenant_id = ? AND unit_id = ?')->execute([$tenantId, $unitId]);
            $ins = $pdo->prepare('INSERT INTO jnet_unit_space_managers (tenant_id, unit_id, user_id, created_by) VALUES (?, ?, ?, ?)');
            foreach (array_values(array_unique(array_map('intval', $userIds))) as $uid) {
                if ($uid > 0) {
                    $ins->execute([$tenantId, $unitId, $uid, $userId > 0 ? $userId : null]);
                }
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        unset($this->rulesCache[$tenantId . ':' . $unitId]);
    }

    /**
     * Indicatifs de personnes (pour désigner des gestionnaires et les afficher).
     *
     * @param list<int> $userIds
     * @return array<int, string> identifiant => indicatif (vide s'il n'en a pas)
     */
    public function callsigns(array $userIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $userIds), static fn (int $i): bool => $i > 0)));
        if ($ids === []) {
            return [];
        }
        try {
            $st = $this->db()->prepare('SELECT id, callsign FROM users WHERE id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')');
            $st->execute($ids);
        } catch (\Throwable) {
            return [];
        }
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
            $out[(int) $r['id']] = trim((string) ($r['callsign'] ?? ''));
        }

        return $out;
    }

    /**
     * Le lecteur commande-t-il cette unité ou une unité au-dessus ? ($chain : de l'organisation à l'unité.)
     *
     * @param list<int> $chain
     */
    public function commandsWithin(int $tenantId, int $userId, array $chain): bool
    {
        $ids = array_values(array_filter(array_map('intval', $chain), static fn (int $id): bool => $id > 0));
        if ($userId <= 0 || $ids === []) {
            return false;
        }
        try {
            $in = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $this->db()->prepare('SELECT 1 FROM units WHERE tenant_id = ? AND commander_user_id = ? AND id IN (' . $in . ') LIMIT 1');
            $stmt->execute(array_merge([$tenantId, $userId], $ids));

            return $stmt->fetchColumn() !== false;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Image envoyée (insigne ou bannière) : chemin relatif sous public/uploads/jnet-espaces/<communauté>/, ou erreur.
     * Aucun fichier : path et error vides.
     *
     * @param array<string, mixed>|null $file entrée de $_FILES
     * @return array{path: string, error: string}
     */
    public static function storeImage(int $tenantId, ?array $file, string $kind): array
    {
        $err = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($file === null || $err === UPLOAD_ERR_NO_FILE) {
            return ['path' => '', 'error' => ''];
        }
        if ($err !== UPLOAD_ERR_OK) {
            return ['path' => '', 'error' => $err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE
                ? 'Image trop lourde (8 Mo maximum).'
                : 'L’envoi de l’image a échoué. Réessayez.'];
        }
        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_file($tmp) || (int) ($file['size'] ?? 0) > self::UPLOAD_MAX_BYTES) {
            return ['path' => '', 'error' => 'Image trop lourde (8 Mo maximum).'];
        }
        $ext = TerrainUploadedImage::detectExtension($tmp, (string) ($file['name'] ?? ''));
        if ($ext === null) {
            return ['path' => '', 'error' => 'Formats acceptés : JPG, PNG ou WebP.'];
        }
        $dirRel = self::UPLOAD_PREFIX . $tenantId;
        $dirAbs = base_path('public/' . $dirRel);
        if (!is_dir($dirAbs) && !@mkdir($dirAbs, 0775, true) && !is_dir($dirAbs)) {
            return ['path' => '', 'error' => 'Stockage des images indisponible pour le moment.'];
        }
        $prefix = $kind === 'banner' ? 'banniere' : 'insigne';
        $stored = (new ImageCompressionService())->storeUpload(
            $file,
            $dirAbs,
            $dirRel,
            $prefix,
            $kind === 'banner' ? 1_500_000 : 400_000,
            self::UPLOAD_MAX_BYTES,
            $kind === 'banner' ? 2400 : 512
        );
        $path = ($stored['ok'] ?? false) ? trim((string) ($stored['relative'] ?? '')) : '';
        if ($path !== '') {
            return ['path' => $path, 'error' => ''];
        }
        $name = $prefix . '-' . bin2hex(random_bytes(8)) . '.' . $ext;
        if (!TerrainUploadedImage::move($tmp, $dirAbs . DIRECTORY_SEPARATOR . $name)) {
            return ['path' => '', 'error' => 'Impossible d’enregistrer l’image.'];
        }

        return ['path' => $dirRel . '/' . $name, 'error' => ''];
    }

    /** Chemin relatif d'une image de cette communauté (rien d'autre n'est jamais servi ni effacé). */
    public static function isOwnImage(string $path, int $tenantId): bool
    {
        $path = str_replace('\\', '/', trim($path));

        return $path !== '' && !str_contains($path, '..')
            && preg_match('#^' . preg_quote(self::UPLOAD_PREFIX . $tenantId . '/', '#') . '[A-Za-z0-9._-]+$#', $path) === 1;
    }

    public static function deleteImage(string $path, int $tenantId): void
    {
        if (self::isOwnImage($path, $tenantId) && function_exists('base_path')) {
            $abs = base_path('public/' . $path);
            if (is_file($abs)) {
                @unlink($abs);
            }
        }
    }

    public static function publicUrl(string $path): ?string
    {
        $path = str_replace('\\', '/', trim($path));
        if ($path === '' || str_contains($path, '..') || !str_starts_with($path, self::UPLOAD_PREFIX)) {
            return null;
        }

        return function_exists('user_media_public_url') ? user_media_public_url($path) : $path;
    }

    private static function line(string $raw, int $max): string
    {
        $raw = trim(preg_replace('/\s+/u', ' ', strip_tags($raw)) ?? '');

        return mb_substr($raw, 0, $max);
    }

    private static function text(string $raw, int $max): string
    {
        $raw = str_replace(["\r\n", "\r"], "\n", strip_tags($raw));
        $raw = preg_replace("/\n{3,}/", "\n\n", $raw) ?? '';

        return mb_substr(trim($raw), 0, $max);
    }

    private function db(): PDO
    {
        return $this->pdo ??= Database::getPdo();
    }
}
