<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Repositories\SseAuditLogRepository;
use App\Repositories\SseCaseRepository;
use App\Repositories\SseTagRepository;
use App\Support\SseSheetCatalog as D;
use App\Support\SseUsClassification;

/**
 * Visibilité par niveau des rubriques d'une fiche PDF : pour chaque catégorie de SseRedactionService (identité, lieu, biométrie,
 * source, horodatage), le niveau de diffusion à partir duquel elle est lisible en clair.
 *
 * Trois couches, de la plus générale à la plus précise : les niveaux livrés (SseRedactionService::CATEGORIES), le réglage de la
 * communauté (« * » : tous les modèles) puis celui du modèle, enfin la surcharge posée à l'émission d'un document précis. Chaque
 * couche est assainie : catégorie ou niveau inconnus ignorés, et jamais un niveau INFÉRIEUR au plancher de sécurité de la
 * catégorie (FLOORS, défini ici dans le code : on ne peut le relever, jamais l'abaisser).
 *
 * Les réglages de la communauté vivent dans sse_field_note_settings (clé « sheet_visibility », JSON) : aucune étape de base de
 * données. Sans la table, seuls les niveaux livrés et la surcharge d'émission s'appliquent.
 */
final class SseSheetVisibility
{
    public const SETTING = 'sheet_visibility';

    /** Portée du réglage de la communauté (tous les modèles). */
    public const ALL = '*';

    /**
     * Plancher de sécurité par catégorie : niveau le plus bas auquel elle peut devenir lisible.
     *
     * @var array<string, string>
     */
    public const FLOORS = [
        'identite' => SseCaseRepository::CLASS_CONFIDENTIAL,
        'biometrie' => SseCaseRepository::CLASS_CONFIDENTIAL,
        'source' => SseCaseRepository::CLASS_RESTRICTED,
        'lieu' => SseCaseRepository::CLASS_INTERNAL,
        'horodatage' => SseCaseRepository::CLASS_INTERNAL,
    ];

    /** Rubriques de chaque catégorie, pour la modale : clé => [français, anglais]. */
    public const COVERS = [
        'identite' => ['Nom, prénom, alias, naissance, nationalité, pièce, personnes liées', 'Name, aliases, birth data, nationality, ID document, linked persons'],
        'lieu' => ['Lieux, adresses, zone, références de grille', 'Places, addresses, area, grid references'],
        'biometrie' => ['Empreintes, iris, ADN, niveau d’identité', 'Fingerprints, iris, DNA, identity tier'],
        'source' => ['Opérateur ou contrôleur à l’origine de la donnée', 'Operator or checker who collected the data'],
        'horodatage' => ['Dates et heures précises de recueil', 'Precise collection dates and times'],
    ];

    public function __construct(private ?SseTagRepository $store = null)
    {
        $this->store ??= new SseTagRepository();
    }

    /** Niveaux livrés. @return array<string, string> */
    public static function defaults(): array
    {
        $out = [];
        foreach (SseRedactionService::CATEGORIES as $key => $meta) {
            $out[$key] = SseRedactionService::requiredLevel($key);
            unset($meta);
        }

        return $out;
    }

    public static function floor(string $category): string
    {
        return self::FLOORS[$category] ?? SseRedactionService::CATEGORIES[$category]['level'] ?? SseCaseRepository::CLASS_COMMAND;
    }

    /**
     * Assainit une couche de réglages : catégories connues, niveaux connus, jamais sous le plancher (ramené au plancher).
     *
     * @param mixed $raw
     * @return array<string, string>
     */
    public static function clean(mixed $raw): array
    {
        $out = [];
        if (!is_array($raw)) {
            return $out;
        }
        foreach (SseRedactionService::CATEGORIES as $key => $meta) {
            $level = $raw[$key] ?? null;
            if (!is_string($level) || !isset(SseRedactionService::LEVELS[$level])) {
                continue;
            }
            $floor = self::floor($key);
            $out[$key] = SseRedactionService::levelRank($level) < SseRedactionService::levelRank($floor) ? $floor : $level;
            unset($meta);
        }

        return $out;
    }

    /**
     * Niveaux requis effectifs : les niveaux livrés, recouverts par chaque couche (la dernière l'emporte).
     *
     * @param array<string, string> ...$layers
     * @return array<string, string>
     */
    public static function effective(array ...$layers): array
    {
        $out = self::defaults();
        foreach ($layers as $layer) {
            $out = self::clean($layer) + $out;
        }

        return $out;
    }

    /** Réglages enregistrés de la communauté : portée (« * » ou modèle) => couche. @return array<string, array<string, string>> */
    public function stored(int $tenantId): array
    {
        $raw = [];
        try {
            $raw = $this->store->settings($tenantId)[self::SETTING] ?? [];
        } catch (\Throwable) {
            $raw = [];
        }
        $out = [];
        foreach (is_array($raw) ? $raw : [] as $scope => $layer) {
            if ($scope === self::ALL || (D::exists((string) $scope) && (string) $scope !== 'notice')) {
                $out[(string) $scope] = self::clean($layer);
            }
        }

        return $out;
    }

    /** Niveaux requis d'un modèle dans la communauté (avant la surcharge d'émission). @return array<string, string> */
    public function forModel(int $tenantId, string $model): array
    {
        $stored = $this->stored($tenantId);

        return self::effective($stored[self::ALL] ?? [], $stored[$model] ?? []);
    }

    /**
     * Niveaux requis d'un document : modèle de la communauté, puis surcharge d'émission (assainie, jamais sous le plancher).
     *
     * @param array<string, mixed> $override
     * @return array<string, string>
     */
    public function resolve(int $tenantId, string $model, array $override = []): array
    {
        return self::effective($this->forModel($tenantId, $model), $override);
    }

    /** Peut-on enregistrer les réglages de la communauté (table des réglages présente) ? */
    public static function canStore(): bool
    {
        return SseTagRepository::ready();
    }

    /**
     * Enregistre le réglage d'une portée (« * » ou un modèle) : seuls les niveaux qui diffèrent des niveaux livrés sont gardés.
     * Écrit au journal d'audit. Faux sans la table des réglages ou pour une portée inconnue.
     *
     * @param array<string, mixed> $levels
     */
    public function save(int $tenantId, string $scope, array $levels, SseSheetAccess|int|null $actor, string $label = ''): bool
    {
        if ($tenantId < 1 || !self::canStore() || ($scope !== self::ALL && (!D::exists($scope) || $scope === 'notice'))) {
            return false;
        }
        $userId = $actor instanceof SseSheetAccess ? $actor->userId() : $actor;
        $stored = $this->stored($tenantId);
        $before = $stored[$scope] ?? [];
        $defaults = $scope === self::ALL ? self::defaults() : self::effective($stored[self::ALL] ?? []);
        $diff = [];
        foreach (self::clean($levels) as $key => $level) {
            if ($level !== $defaults[$key]) {
                $diff[$key] = $level;
            }
        }
        $stored[$scope] = $diff;
        $stored = array_filter($stored, static fn (array $l): bool => $l !== []);
        if (!$this->store->saveSetting($tenantId, self::SETTING, $stored, $userId)) {
            return false;
        }
        try {
            (new SseAuditLogRepository())->write([
                'tenant_id' => $tenantId, 'actor_user_id' => $userId, 'actor_label' => $label !== '' ? $label : ($actor instanceof SseSheetAccess && $actor->actorLabel() !== '' ? $actor->actorLabel() : 'Fiches PDF'),
                'action' => 'document.visibility_saved', 'object_type' => 'sse_sheet_visibility', 'object_uuid' => mb_substr($scope === self::ALL ? 'all' : $scope, 0, 36), 'reason' => '',
                'before' => $before, 'after' => $diff,
            ]);
        } catch (\Throwable $e) {
            error_log('Fiches PDF : audit de la visibilité indisponible : ' . $e->getMessage());
        }

        return true;
    }

    /**
     * Pour la modale : une ligne par catégorie (libellés, plancher, niveau requis) et la liste des niveaux avec leur sigle.
     *
     * @param array<string, string> $effective
     * @param array<string, string>|null $baseline niveaux auxquels « rétablir » ramène (ceux du modèle), sinon les niveaux livrés
     * @return array{rows: list<array<string, mixed>>, levels: list<array{code: string, abbr: string, label_fr: string, label_en: string}>}
     */
    public static function table(array $effective, ?array $baseline = null): array
    {
        $levels = [];
        foreach (array_keys(SseRedactionService::LEVELS) as $code) {
            $levels[] = ['code' => $code, 'abbr' => SseUsClassification::abbr($code), 'label_fr' => SseUsClassification::label($code, 'fr'), 'label_en' => SseUsClassification::label($code, 'en')];
        }
        $rows = [];
        foreach (SseRedactionService::CATEGORIES as $key => $meta) {
            $rows[] = [
                'key' => $key, 'label_fr' => $meta['label'], 'label_en' => \App\Support\SseRedactionExplain::label($key, 'en'),
                'covers_fr' => self::COVERS[$key][0] ?? '', 'covers_en' => self::COVERS[$key][1] ?? '',
                'floor' => self::floor($key), 'level' => $effective[$key] ?? self::floor($key), 'default' => $baseline[$key] ?? SseRedactionService::requiredLevel($key),
            ];
        }

        return ['rows' => $rows, 'levels' => $levels];
    }
}
