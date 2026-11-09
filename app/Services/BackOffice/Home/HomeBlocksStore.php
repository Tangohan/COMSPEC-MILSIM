<?php

declare(strict_types=1);

namespace App\Services\BackOffice\Home;

use App\Repositories\BoSheetRepository;
use App\Repositories\TenantRepository;

/**
 * Lecture et écriture du contenu du bloc personnalisé de l'accueil : un contenu par communauté, rangé dans les
 * réglages de la communauté (settings.bo_home_blocks). Le tenant vient toujours de la session, jamais de la requête.
 */
final class HomeBlocksStore
{
    public const SETTINGS_KEY = 'bo_home_blocks';
    /** Lignes et colonnes d'un tableau de données affichées dans l'accueil. */
    public const SHEET_ROWS = 12;
    public const SHEET_COLS = 8;

    public function __construct(
        private TenantRepository $tenants,
        private BoSheetRepository $sheets,
    ) {
    }

    /** @return array{version: int, blocks: list<array<string, mixed>>} */
    public function load(int $tenantId): array
    {
        if ($tenantId < 1) {
            return HomeBlocks::normalize([]);
        }
        $settings = $this->tenants->getSettings($tenantId);
        $raw = $settings[self::SETTINGS_KEY] ?? null;

        return HomeBlocks::normalize(is_array($raw) ? $raw : []);
    }

    /**
     * Enregistre le contenu reçu après normalisation ; null si le contenu dépasse la taille permise.
     *
     * @param array<string, mixed> $raw
     * @return array{version: int, blocks: list<array<string, mixed>>}|null
     */
    public function save(int $tenantId, array $raw, int $userId): ?array
    {
        if ($tenantId < 1) {
            return null;
        }
        $layout = HomeBlocks::normalize($raw);
        if (strlen((string) json_encode($layout, JSON_UNESCAPED_UNICODE)) > HomeBlocks::MAX_BYTES) {
            return null;
        }
        $this->tenants->mergeSettings($tenantId, [self::SETTINGS_KEY => $layout + ['updated_at' => gmdate('c'), 'updated_by' => $userId]]);

        return $layout;
    }

    /**
     * Données des blocs « tableau de données » que cette personne peut lire (les siens ou partagés, de SA communauté).
     *
     * @param array{blocks: list<array<string, mixed>>} $layout
     * @return array<int, array{title: string, cols: list<string>, rows: list<list<string>>, total: int, url: string}>
     */
    public function sheetsFor(array $layout, int $tenantId, int $userId): array
    {
        $out = [];
        foreach ($layout['blocks'] as $b) {
            $sid = $b['type'] === 'sheet' ? (int) ($b['props']['sheet_id'] ?? 0) : 0;
            if ($sid < 1 || isset($out[$sid])) {
                continue;
            }
            try {
                $sheet = $this->sheets->find($tenantId, $sid);
            } catch (\Throwable) {
                $sheet = null;
            }
            if ($sheet === null || ($sheet['owner_user_id'] !== $userId && !$sheet['shared'])) {
                continue;
            }
            $computed = SheetData::computed($sheet['data']);
            $cols = array_map(static fn (array $c): string => (string) $c['name'], array_slice($sheet['data']['cols'], 0, self::SHEET_COLS));
            $rows = [];
            foreach (array_slice($computed, 0, self::SHEET_ROWS) as $row) {
                $rows[] = array_map('strval', array_slice(array_values($row), 0, self::SHEET_COLS));
            }
            $out[$sid] = [
                'title' => $sheet['title'],
                'cols' => $cols,
                'rows' => $rows,
                'total' => count($computed),
                'url' => function_exists('url') ? url('back-office/tableaux/' . $sid) : '/back-office/tableaux/' . $sid,
            ];
        }

        return $out;
    }

    /**
     * Tableaux proposés dans le panneau du bloc.
     *
     * @return list<array{id: int, title: string, shared: bool}>
     */
    public function choices(int $tenantId, int $userId): array
    {
        $out = [];
        foreach ($this->sheets->listReadable($tenantId, $userId) as $r) {
            $out[] = ['id' => $r['id'], 'title' => $r['title'], 'shared' => $r['shared']];
        }

        return $out;
    }
}
