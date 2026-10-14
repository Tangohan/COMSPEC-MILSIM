<?php

declare(strict_types=1);

namespace App\Services\Jnet;

use App\Repositories\JnetExchangeRepository;
use App\Repositories\PersonnelProfileRepository;
use App\Repositories\UnitRepository;
use App\Services\Tactical\AtakBridgeModulesService;
use App\Support\AtakIcemanReportCatalog;
use App\Support\ReconImageStorage;
use App\Support\ReconNoteCatalog;

/**
 * Relais terrain → JNET : chaque reco, photo ou rapport envoyé depuis l'ATAK en jeu devient un échange
 * de l'unité de l'opérateur, remonté vers son échelon supérieur.
 *
 * - L'opérateur est retrouvé par son compte (clé API du jeu ou Steam vérifié de la session), puis son unité
 *   par ses affectations. Sans compte ou sans unité, rien n'est relayé (aucune diffusion à l'aveugle).
 * - Un FRAGO descend vers les sous-unités ; tout le reste remonte vers l'échelon supérieur.
 * - Une photo confidentielle ou liée au bureau SSE n'est jamais montrée en vignette : seul le lien vers le
 *   lecteur du site (qui contrôle les droits) est publié, comme pour Discord.
 * - Un rapport à diffusion restreinte (visibility ≠ ALL) n'est pas relayé.
 * - Désactivable par organisation : module « jnet_bridge » des modules ATAK.
 *
 * Ne lève jamais d'exception vers le jeu : un échec du relais n'empêche pas l'enregistrement de la donnée.
 */
final class JnetFieldReportBridge
{
    public const SOURCE_NOTE = 'recon_note';
    public const SOURCE_IMAGE = 'recon_image';
    public const SOURCE_REPORT = 'atak_report';

    public const SOURCE_LABELS = [
        self::SOURCE_NOTE => 'Reco terrain',
        self::SOURCE_IMAGE => 'Photo terrain',
        self::SOURCE_REPORT => 'Rapport ATAK',
    ];

    private const TITLE_MAX = 160;
    private const BODY_MAX = 4000;

    public function __construct(
        private ?JnetExchangeRepository $exchanges = null,
        private ?UnitRepository $units = null,
        private ?PersonnelProfileRepository $profiles = null,
        private ?AtakBridgeModulesService $modules = null,
    ) {
        $this->exchanges ??= new JnetExchangeRepository();
        $this->units ??= new UnitRepository();
        $this->profiles ??= new PersonnelProfileRepository();
        $this->modules ??= new AtakBridgeModulesService();
    }

    /** @param array<string, mixed> $note ligne recon_notes */
    public function relayReconNote(int $tenantId, ?int $userId, array $note): int
    {
        $sourceRef = trim((string) ($note['source_id'] ?? ''));
        if ($sourceRef === '') {
            return 0;
        }

        return $this->relay($tenantId, $userId, self::SOURCE_NOTE, $sourceRef, self::buildReconNote($note));
    }

    /** @param array<string, mixed> $image ligne recon_images (avec id) */
    public function relayReconImage(int $tenantId, ?int $userId, array $image): int
    {
        $id = (int) ($image['id'] ?? 0);
        if ($id <= 0) {
            return 0;
        }
        $isPrivate = !empty($image['is_confidential']) || !empty($image['is_sse'])
            || ReconImageStorage::isPrivatePath((string) ($image['image_path'] ?? ''));
        $thumb = $isPrivate ? null : ReconImageStorage::urlForRow($image);

        return $this->relay($tenantId, $userId, self::SOURCE_IMAGE, (string) $id, self::buildReconImage($image, $thumb));
    }

    /** @param array<string, mixed> $report données du rapport enregistré (report_type, summary, structured_data…) */
    public function relayTacticalReport(int $tenantId, ?int $userId, int $reportId, array $report): int
    {
        if ($reportId <= 0 || !self::reportIsShareable($report)) {
            return 0;
        }

        return $this->relay($tenantId, $userId, self::SOURCE_REPORT, (string) $reportId, self::buildReport($report));
    }

    /**
     * @param array{kind:string,title:string,body:string,link_url:?string,image_url:?string,direction:string} $built
     */
    private function relay(int $tenantId, ?int $userId, string $sourceKind, string $sourceRef, array $built): int
    {
        try {
            if ($tenantId <= 0 || $userId === null || $userId <= 0 || !$this->enabled($tenantId)) {
                return 0;
            }
            $unit = $this->primaryUnit($tenantId, $userId);
            if ($unit === null) {
                return 0;
            }
            $unitId = (int) $unit['id'];
            $parentId = (int) ($unit['parent_id'] ?? 0);
            $childIds = [];
            if ($built['direction'] === 'down') {
                foreach ($this->units->childrenForTenant($tenantId, $unitId) as $child) {
                    $childIds[] = (int) ($child['id'] ?? 0);
                }
            }

            return $this->exchanges->upsertFromSource($tenantId, $sourceKind, $sourceRef, [
                'from_unit_id' => $unitId,
                'author_user_id' => $userId,
                'kind' => $built['kind'],
                'title' => $built['title'],
                'body' => $built['body'],
                'link_url' => $built['link_url'],
                'image_url' => $built['image_url'],
                'requires_ack' => $built['kind'] === 'ordre',
                'targets' => self::targetsFor($built['direction'], $unitId, $parentId, $childIds),
            ]);
        } catch (\Throwable $e) {
            error_log('[jnet/field-bridge] ' . $sourceKind . ':' . $sourceRef . ' ' . $e->getMessage());

            return 0;
        }
    }

    private function enabled(int $tenantId): bool
    {
        try {
            return ($this->modules->get($tenantId)['modules']['jnet_bridge'] ?? true) === true;
        } catch (\Throwable) {
            return true;
        }
    }

    /** @return array<string, mixed>|null unité principale de l'opérateur (même règle que les photos de reco) */
    private function primaryUnit(int $tenantId, int $userId): ?array
    {
        $ids = $this->units->unitIdsForUser($tenantId, $userId);
        $unitId = (int) ($ids[0] ?? 0);
        if ($unitId <= 0) {
            $profile = $this->profiles->getByUserId($userId, $tenantId) ?? [];
            $unitId = (int) ($profile['primary_unit_id'] ?? 0);
        }
        if ($unitId <= 0) {
            return null;
        }
        $unit = $this->units->findById($unitId, $tenantId);

        return is_array($unit) ? $unit : null;
    }

    // ——— Règles de conversion (pures) ———

    /**
     * Remonter : vers l'échelon supérieur (l'unité seule si elle est en tête d'arbre).
     * Descendre : vers les sous-unités directes (l'unité seule si elle n'en a pas).
     *
     * @param list<int> $childIds
     * @return list<int>
     */
    public static function targetsFor(string $direction, int $unitId, int $parentId, array $childIds): array
    {
        if ($direction === 'down') {
            $children = array_values(array_unique(array_filter($childIds, static fn (int $id): bool => $id > 0 && $id !== $unitId)));

            return $children !== [] ? $children : [$unitId];
        }

        return $parentId > 0 && $parentId !== $unitId ? [$parentId] : [$unitId];
    }

    /**
     * @param array<string, mixed> $note
     * @return array{kind:string,title:string,body:string,link_url:?string,image_url:?string,direction:string}
     */
    public static function buildReconNote(array $note): array
    {
        $tag = (string) ($note['tag'] ?? '');
        $text = self::oneLine((string) ($note['text'] ?? ''));
        $tagLabel = $tag !== '' ? ReconNoteCatalog::tagLabel($tag) : '';
        $head = 'Reco' . ($tagLabel !== '' ? ' · ' . $tagLabel : '');
        $title = $text !== '' ? $head . ' : ' . $text : $head;

        $lines = [];
        $confidence = ReconNoteCatalog::confidenceLabel((string) ($note['confidence'] ?? ''));
        if ($confidence !== '') {
            $lines[] = 'Fiabilité : ' . mb_strtolower($confidence);
        }
        $mission = trim((string) ($note['mission_id'] ?? ''));
        if ($mission !== '') {
            $lines[] = 'Mission : ' . $mission;
        }
        $lines[] = 'Marqueur visible sur la carte ATAK pendant 40 minutes.';

        return [
            'kind' => 'renseignement',
            'title' => self::cut($title, self::TITLE_MAX),
            'body' => self::cut(implode("\n", $lines), self::BODY_MAX),
            'link_url' => 'atak',
            'image_url' => null,
            'direction' => 'up',
        ];
    }

    /**
     * @param array<string, mixed> $image
     * @return array{kind:string,title:string,body:string,link_url:?string,image_url:?string,direction:string}
     */
    public static function buildReconImage(array $image, ?string $thumbUrl): array
    {
        $isPrivate = $thumbUrl === null;
        $label = self::oneLine((string) ($image['title'] ?? ''));
        if ($label === '') {
            $label = self::oneLine((string) ($image['caption'] ?? ''));
        }
        $title = ($isPrivate && !empty($image['is_confidential']) ? 'Photo de reco confidentielle' : 'Photo de reco')
            . ($label !== '' && empty($image['is_confidential']) ? ' · ' . $label : '');

        $device = match (strtoupper((string) ($image['device_type'] ?? 'CTAB'))) {
            'HELMET' => 'caméra casque',
            'UAV' => 'drone',
            default => 'téléphone',
        };
        $facts = ['Prise au ' . $device];
        $grid = trim((string) ($image['grid_ref'] ?? ''));
        if ($grid !== '') {
            $facts[] = 'grille ' . $grid;
        }
        if (isset($image['heading']) && $image['heading'] !== null && $image['heading'] !== '') {
            $facts[] = 'AZ ' . (int) round((float) $image['heading']) . '°';
        }
        $lines = [implode(' · ', $facts)];
        $desc = trim((string) ($image['description'] ?? ''));
        if ($desc !== '' && empty($image['is_confidential'])) {
            $lines[] = $desc;
        }
        if ($isPrivate) {
            $lines[] = !empty($image['is_confidential'])
                ? 'Cliché réservé : ouvrez-le sur Athena (accès contrôlé).'
                : 'Cliché rattaché au bureau SSE : ouvrez-le sur Athena.';
        }

        return [
            'kind' => 'renseignement',
            'title' => self::cut($title, self::TITLE_MAX),
            'body' => self::cut(implode("\n", $lines), self::BODY_MAX),
            'link_url' => 'recon/images/' . (int) ($image['id'] ?? 0),
            'image_url' => $thumbUrl,
            'direction' => 'up',
        ];
    }

    /**
     * @param array<string, mixed> $report
     * @return array{kind:string,title:string,body:string,link_url:?string,image_url:?string,direction:string}
     */
    public static function buildReport(array $report): array
    {
        $type = AtakIcemanReportCatalog::normalizeType((string) ($report['report_type'] ?? 'OTHER'));
        $label = AtakIcemanReportCatalog::labelFr($type);
        $kind = match ($type) {
            'FRAGO' => 'ordre',
            'BDA', 'SITREP', 'PATROLREP', 'LOGREP', 'EAGLE_DOWN' => 'compte_rendu',
            default => 'renseignement',
        };
        $summary = self::oneLine((string) ($report['summary'] ?? ''));
        $priority = strtoupper((string) ($report['priority'] ?? ''));
        $urgent = in_array($priority, ['FLASH', 'IMMEDIATE', 'URGENT', 'PRIORITY'], true) || $type === 'EAGLE_DOWN';
        $title = ($urgent ? 'URGENT · ' : '') . $label . ($summary !== '' ? ' : ' . $summary : '');

        $lines = [];
        $fields = is_array($report['structured_data'] ?? null) ? $report['structured_data'] : [];
        foreach ($fields as $key => $value) {
            if (!is_scalar($value) || trim((string) $value) === '' || in_array((string) $key, ['send_to'], true)) {
                continue;
            }
            $lines[] = AtakIcemanReportCatalog::fieldLabelFr($type, (string) $key) . ' : ' . self::oneLine((string) $value);
        }
        if ($lines === []) {
            $details = trim((string) ($report['details'] ?? ''));
            if ($details !== '') {
                $lines[] = $details;
            }
        }
        $grid = trim((string) ($report['grid_reference'] ?? ''));
        if ($grid !== '' && !self::mentions($lines, $grid)) {
            $lines[] = 'Grille : ' . $grid;
        }
        $number = trim((string) ($report['report_number'] ?? ''));
        if ($number !== '') {
            $lines[] = 'Réf. ' . $number;
        }

        return [
            'kind' => $kind,
            'title' => self::cut($title, self::TITLE_MAX),
            'body' => self::cut(implode("\n", $lines), self::BODY_MAX),
            'link_url' => 'atak',
            'image_url' => null,
            'direction' => $type === 'FRAGO' ? 'down' : 'up',
        ];
    }

    /** @param array<string, mixed> $report */
    public static function reportIsShareable(array $report): bool
    {
        $visibility = strtoupper(trim((string) ($report['visibility'] ?? 'ALL')));

        return $visibility === '' || $visibility === 'ALL';
    }

    private static function oneLine(string $s): string
    {
        return trim(preg_replace('/\s+/u', ' ', $s) ?? '');
    }

    private static function cut(string $s, int $max): string
    {
        return mb_strlen($s) > $max ? rtrim(mb_substr($s, 0, $max - 1)) . '…' : $s;
    }

    /** @param list<string> $lines */
    private static function mentions(array $lines, string $needle): bool
    {
        foreach ($lines as $l) {
            if (str_contains($l, $needle)) {
                return true;
            }
        }

        return false;
    }
}
