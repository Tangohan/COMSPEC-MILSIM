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
            ];
        }

        return $out;
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

    public function reset(int $tenantId, int $unitId): void
    {
        $row = $this->find($tenantId, $unitId);
        $this->db()->prepare('DELETE FROM jnet_unit_spaces WHERE tenant_id = ? AND unit_id = ?')->execute([$tenantId, $unitId]);
        self::deleteImage((string) $row['emblem_path'], $tenantId);
        self::deleteImage((string) $row['banner_path'], $tenantId);
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
