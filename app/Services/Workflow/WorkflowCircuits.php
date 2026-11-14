<?php

declare(strict_types=1);

namespace App\Services\Workflow;

use App\Core\Container;
use App\Repositories\WorkflowCircuitRepository;
use App\Services\Platform\FeatureGateService;

/**
 * Étapes d'avis de chaque type de demande, avant la décision des responsables.
 *
 * Par défaut : avis du chef d'unité pour les élévations et les mobilités, aucun avis pour le reste. Avec le module de
 * formule « workflow_circuits », une communauté compose son propre circuit (jusqu'à quatre étapes) : chef de l'unité
 * du membre, échelon supérieur de la chaîne de commandement, ou une personne désignée (S1, chef du bureau…). Les
 * étapes donnent un avis ; la décision et les droits restent ceux des écrans existants.
 */
class WorkflowCircuits
{
    public const FEATURE = 'workflow_circuits';
    public const MAX_STEPS = 4;

    public const KIND_COMMANDER = 'commander';
    public const KIND_UPPER = 'upper';
    public const KIND_USER = 'user';

    /** Types de demande dont le circuit peut être composé. */
    public const SUBJECTS = [
        WorkflowEngine::SUBJECT_ELEVATION,
        WorkflowEngine::SUBJECT_MOBILITY,
        WorkflowEngine::SUBJECT_CORRECTION,
        WorkflowEngine::SUBJECT_AAR,
    ];

    /** Types qui ont l'avis du chef d'unité sans module. */
    private const DEFAULT_OPINION = [WorkflowEngine::SUBJECT_ELEVATION, WorkflowEngine::SUBJECT_MOBILITY];

    /** @var (callable(int): bool)|null */
    private $planAllows;

    /** @var array<int, array<string, list<array<string, mixed>>>> */
    private array $cache = [];

    /** @param (callable(int): bool)|null $planAllows */
    public function __construct(private WorkflowCircuitRepository $repository, ?callable $planAllows = null)
    {
        $this->planAllows = $planAllows;
    }

    public function allowedByPlan(int $tenantId): bool
    {
        if ($this->planAllows !== null) {
            return ($this->planAllows)($tenantId);
        }
        try {
            return Container::get(FeatureGateService::class)->allowsWithCatalogDefault($tenantId, self::FEATURE);
        } catch (\Throwable) {
            return false;
        }
    }

    /** @return list<array{kind: string, user_id?: int}> */
    public static function defaultSteps(string $subjectType): array
    {
        return in_array($subjectType, self::DEFAULT_OPINION, true) ? [['kind' => self::KIND_COMMANDER]] : [];
    }

    /**
     * Étapes en vigueur : celles de la communauté si elle a le module et en a enregistré, sinon le circuit par défaut.
     *
     * @return list<array{kind: string, user_id?: int}>
     */
    public function stepsFor(int $tenantId, string $subjectType): array
    {
        $custom = $this->customFor($tenantId);
        if (array_key_exists($subjectType, $custom) && $this->allowedByPlan($tenantId)) {
            return $custom[$subjectType];
        }

        return self::defaultSteps($subjectType);
    }

    /** @return array<string, list<array{kind: string, user_id?: int}>> */
    public function customFor(int $tenantId): array
    {
        if (!isset($this->cache[$tenantId])) {
            $clean = [];
            try {
                foreach ($this->repository->allForTenant($tenantId) as $subject => $steps) {
                    if (in_array($subject, self::SUBJECTS, true)) {
                        $clean[$subject] = self::normalize($steps);
                    }
                }
            } catch (\Throwable $e) {
                error_log('Moteur de circuits : circuits de la communauté illisibles : ' . $e->getMessage());
            }
            $this->cache[$tenantId] = $clean;
        }

        return $this->cache[$tenantId];
    }

    /**
     * Garde les étapes valides, dans l'ordre, sans doublon ni personne désignée deux fois.
     *
     * @param list<mixed> $steps
     * @return list<array{kind: string, user_id?: int}>
     */
    public static function normalize(array $steps): array
    {
        $out = [];
        $seenUsers = [];
        foreach ($steps as $step) {
            if (!is_array($step)) {
                continue;
            }
            $kind = (string) ($step['kind'] ?? '');
            if ($kind === self::KIND_USER) {
                $uid = (int) ($step['user_id'] ?? 0);
                if ($uid < 1 || isset($seenUsers[$uid])) {
                    continue;
                }
                $seenUsers[$uid] = true;
                $out[] = ['kind' => self::KIND_USER, 'user_id' => $uid];
            } elseif ($kind === self::KIND_COMMANDER || $kind === self::KIND_UPPER) {
                $out[] = ['kind' => $kind];
            }
            if (count($out) >= self::MAX_STEPS) {
                break;
            }
        }

        return $out;
    }

    /** @param list<array<string, mixed>> $steps */
    public function save(int $tenantId, string $subjectType, array $steps, int $userId, ?string $now = null): void
    {
        $this->repository->save($tenantId, $subjectType, self::normalize($steps), $userId, $now ?? date('Y-m-d H:i:s'));
        unset($this->cache[$tenantId]);
    }

    public function reset(int $tenantId, string $subjectType): void
    {
        $this->repository->reset($tenantId, $subjectType);
        unset($this->cache[$tenantId]);
    }

    public function ready(): bool
    {
        return $this->repository->tableReady();
    }

    public static function kindLabel(string $kind, bool $fr = true): string
    {
        return match ($kind) {
            self::KIND_COMMANDER => $fr ? 'Chef de l’unité du membre' : 'Member’s unit commander',
            self::KIND_UPPER => $fr ? 'Échelon supérieur' : 'Next echelon up',
            self::KIND_USER => $fr ? 'Personne désignée' : 'Named person',
            default => $kind,
        };
    }
}
