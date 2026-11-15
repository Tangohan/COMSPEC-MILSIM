<?php

declare(strict_types=1);

namespace App\Services\Effectifs\Dossier;

use App\Core\Database;
use App\Services\Effectifs\Dossier\Adapters\CareerStepsAdapter;
use App\Services\Effectifs\Dossier\Adapters\DepartureStepsAdapter;
use App\Services\Effectifs\Dossier\Adapters\FormationStepsAdapter;
use App\Services\Effectifs\Dossier\Adapters\IntegrationStepsAdapter;
use App\Services\Effectifs\Dossier\Adapters\RecruitmentStepsAdapter;
use App\Services\Effectifs\Dossier\Adapters\ReliabilityStepsAdapter;
use PDO;
use Throwable;

/**
 * Fil d'étapes unifié du dossier d'un membre (LECTURE SEULE).
 *
 * Réunit, par adaptateur de parcours, les étapes que les services existants portent déjà (recrutement, intégration,
 * formation, documents, carrière, fiabilisation, départ), ajoute les étapes personnalisées et les compléments de
 * member_dossier_steps, calcule les statuts (en retard, bloquée…) et la liste des gestes permis sur chaque étape.
 * Ne modifie rien : les gestes passent par MemberDossierStepActions, qui rappelle les services d'origine.
 */
final class MemberDossierTimeline
{
    public const CUSTOM = 'custom';

    /** @var list<DossierStepAdapter> */
    private array $adapters;

    /** @var \Closure(int, list<int>): array<int, string> */
    private \Closure $names;

    /**
     * @param list<DossierStepAdapter>|null $adapters
     * @param (\Closure(int, list<int>): array<int, string>)|null $nameResolver libellé des responsables par identifiant
     */
    public function __construct(private PDO $pdo, private DossierStore $store, ?array $adapters = null, ?\Closure $nameResolver = null)
    {
        $this->adapters = $adapters ?? [
            new RecruitmentStepsAdapter($pdo),
            new IntegrationStepsAdapter($pdo),
            new FormationStepsAdapter($pdo),
            new ReliabilityStepsAdapter($pdo),
            new CareerStepsAdapter($pdo),
            new DepartureStepsAdapter($pdo),
        ];
        $this->names = $nameResolver ?? static function (int $tenantId, array $ids): array {
            try {
                return (new \App\Services\Personnel\MemberNameService())->labelsFor($tenantId, $ids, 'list');
            } catch (Throwable) {
                return [];
            }
        };
    }

    public static function make(): self
    {
        $pdo = Database::getPdo();

        return new self($pdo, new DossierStore($pdo));
    }

    public function store(): DossierStore
    {
        return $this->store;
    }

    /** Sans la migration, l'onglet affiche les étapes des parcours existants, sans étape personnalisée ni geste. */
    public function degraded(): bool
    {
        return !$this->store->available();
    }

    /**
     * Dossier complet d'un membre : étapes, résumé, regroupement par parcours.
     *
     * @return array{steps: list<array<string, mixed>>, summary: array<string, mixed>, tracks: array<string, array<string, mixed>>, degraded: bool}
     */
    public function forUser(int $tenantId, int $userId, ?string $today = null): array
    {
        $today ??= date('Y-m-d');
        $steps = $this->forUsers($tenantId, [$userId], $today)[$userId] ?? [];
        $steps = $this->withOwnerLabels($tenantId, $steps, [$userId => true]);
        $tracks = [];
        foreach (DossierCatalog::trackLabels() as $track => $label) {
            $tracks[$track] = ['key' => $track, 'label' => $label, 'hint' => DossierCatalog::trackHints()[$track], 'steps' => [], 'done' => 0, 'total' => 0];
        }
        foreach ($steps as $s) {
            $t = $tracks[$s['track']] ?? null;
            if ($t === null) {
                continue;
            }
            $tracks[$s['track']]['steps'][] = $s;
            if ($s['status'] !== DossierCatalog::STATUS_SKIPPED) {
                $tracks[$s['track']]['total']++;
            }
            if ($s['status'] === DossierCatalog::STATUS_DONE) {
                $tracks[$s['track']]['done']++;
            }
        }

        return [
            'steps' => $steps,
            'summary' => self::summarize($steps),
            'tracks' => array_filter($tracks, static fn (array $t): bool => $t['steps'] !== []),
            'degraded' => $this->degraded(),
        ];
    }

    /**
     * Étapes de plusieurs membres (vue équipe), sans libellé de responsable ni regroupement.
     *
     * @param list<int> $userIds
     * @return array<int, list<array<string, mixed>>>
     */
    public function forUsers(int $tenantId, array $userIds, ?string $today = null): array
    {
        $today ??= date('Y-m-d');
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds), static fn (int $i): bool => $i > 0)));
        $out = array_fill_keys($userIds, []);
        if ($tenantId < 1 || $userIds === []) {
            return $out;
        }
        $overlays = [];
        $custom = [];
        foreach ($this->store->rowsForUsers($tenantId, $userIds) as $uid => $rows) {
            foreach ($rows as $row) {
                if (($row['source_type'] ?? null) === null || $row['source_type'] === '') {
                    $custom[$uid][] = $row;
                } else {
                    $overlays[$uid][$row['source_type'] . ':' . $row['source_id']] = $row;
                }
            }
        }
        $storeOk = $this->store->available();
        foreach ($this->adapters as $adapter) {
            foreach ($adapter->stepsFor($tenantId, $userIds) as $uid => $steps) {
                foreach ($steps as $step) {
                    $out[(int) $uid][] = $step;
                }
            }
        }
        foreach ($custom as $uid => $rows) {
            foreach ($rows as $row) {
                $out[(int) $uid][] = $this->customStep($row, (int) $uid);
            }
        }
        $trackOrder = array_flip(DossierCatalog::tracks());
        foreach ($out as $uid => $steps) {
            foreach ($steps as $i => $step) {
                $steps[$i] = $this->finish($step, (int) $uid, $overlays[$uid][$step['key']] ?? null, $storeOk, $today);
            }
            usort($steps, static function (array $a, array $b) use ($trackOrder): int {
                return [$trackOrder[$a['track']] ?? 99, $a['pos'], (string) ($a['opened_on'] ?? '9999'), $a['key']]
                    <=> [$trackOrder[$b['track']] ?? 99, $b['pos'], (string) ($b['opened_on'] ?? '9999'), $b['key']];
            });
            $out[$uid] = array_values($steps);
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function customStep(array $row, int $userId): array
    {
        $owner = (int) ($row['owner_user_id'] ?? 0);
        $state = match ((string) $row['status']) {
            'done' => DossierCatalog::STATE_DONE,
            'skipped' => DossierCatalog::STATE_SKIPPED,
            'blocked' => DossierCatalog::STATE_BLOCKED,
            'in_progress' => DossierCatalog::STATE_IN_PROGRESS,
            default => DossierCatalog::STATE_TODO,
        };
        $track = in_array((string) $row['track'], DossierCatalog::tracks(), true) ? (string) $row['track'] : DossierCatalog::TRACK_INTEGRATION;

        return [
            'key' => self::CUSTOM . ':' . (int) $row['id'],
            'source_type' => self::CUSTOM,
            'source_id' => (string) (int) $row['id'],
            'track' => $track,
            'title' => (string) $row['title'],
            'description' => '',
            'state' => $state,
            'status' => '',
            'late' => false,
            'optional' => true,
            'owner_kind' => $owner === 0 ? DossierCatalog::OWNER_RH : ($owner === $userId ? DossierCatalog::OWNER_MEMBER : DossierCatalog::OWNER_USER),
            'owner_user_id' => $owner,
            'owner_label' => '',
            'opened_on' => DossierCatalog::day($row['created_at'] ?? null),
            'due_on' => DossierCatalog::day($row['due_on'] ?? null),
            'done_at' => DossierCatalog::stamp($row['done_at'] ?? null),
            'updated_at' => DossierCatalog::stamp($row['updated_at'] ?? null),
            'source_label' => 'Étape ajoutée à la main',
            'source_url' => '',
            'note' => (string) ($row['note'] ?? ''),
            'overridden' => ['due' => false, 'owner' => false],
            'original_due_on' => null,
            'editable' => false,
            'actions' => [],
            'locked_reason' => '',
            'pos' => 900,
        ];
    }

    /**
     * Applique le complément éventuel, calcule le statut, le retard et les gestes permis.
     *
     * @param array<string, mixed> $step
     * @param array<string, mixed>|null $overlay
     * @return array<string, mixed>
     */
    private function finish(array $step, int $userId, ?array $overlay, bool $storeOk, string $today): array
    {
        if ($overlay !== null && $step['source_type'] !== self::CUSTOM && $step['source_type'] !== IntegrationStepsAdapter::SOURCE) {
            $due = DossierCatalog::day($overlay['due_on'] ?? null);
            if ($due !== null) {
                $step['original_due_on'] = $step['due_on'];
                $step['due_on'] = $due;
                $step['overridden']['due'] = true;
            }
            $owner = (int) ($overlay['owner_user_id'] ?? 0);
            if ($owner > 0) {
                $step['owner_user_id'] = $owner;
                $step['owner_kind'] = $owner === $userId ? DossierCatalog::OWNER_MEMBER : DossierCatalog::OWNER_USER;
                $step['overridden']['owner'] = true;
            }
            $step['note'] = (string) ($overlay['note'] ?? '');
            $step['updated_at'] = max((string) $step['updated_at'], (string) DossierCatalog::stamp($overlay['updated_at'] ?? null)) ?: $step['updated_at'];
        }
        $step['late'] = DossierCatalog::isLate((string) $step['state'], $step['due_on'], $today);
        $step['status'] = DossierCatalog::status((string) $step['state'], $step['due_on'], $today);
        $step['actions'] = $storeOk ? self::actionsFor($step) : [];
        $step['editable'] = array_diff($step['actions'], [DossierCatalog::ACTION_NOTE]) !== [];
        $step['locked_reason'] = '';
        if ($step['source_type'] !== self::CUSTOM && $step['source_type'] !== IntegrationStepsAdapter::SOURCE && DossierCatalog::isOpenState((string) $step['state'])) {
            $step['locked_reason'] = 'Cette étape se termine dans son écran d’origine : le suivi ne peut pas la valider à la place du service qui la porte.';
        }

        return $step;
    }

    /**
     * Gestes permis sur une étape. Le contrôle est refait à l'exécution par MemberDossierStepActions.
     *
     * @param array<string, mixed> $step
     * @return list<string>
     */
    public static function actionsFor(array $step): array
    {
        $state = (string) $step['state'];
        $type = (string) $step['source_type'];
        $open = DossierCatalog::isOpenState($state);
        $actions = [DossierCatalog::ACTION_NOTE];
        $memberOwned = $step['owner_kind'] === DossierCatalog::OWNER_MEMBER;
        if ($type === self::CUSTOM) {
            if ($open) {
                array_push($actions, DossierCatalog::ACTION_COMPLETE, DossierCatalog::ACTION_POSTPONE, DossierCatalog::ACTION_REASSIGN, DossierCatalog::ACTION_SKIP, DossierCatalog::ACTION_REMOVE);
            } elseif ($state === DossierCatalog::STATE_DONE) {
                $actions[] = DossierCatalog::ACTION_REOPEN;
            } else {
                array_push($actions, DossierCatalog::ACTION_UNSKIP, DossierCatalog::ACTION_REMOVE);
            }
        } elseif ($type === IntegrationStepsAdapter::SOURCE) {
            if ($open) {
                array_push($actions, DossierCatalog::ACTION_COMPLETE, DossierCatalog::ACTION_POSTPONE, DossierCatalog::ACTION_REASSIGN);
                if (!empty($step['optional'])) {
                    $actions[] = DossierCatalog::ACTION_SKIP;
                }
            } elseif ($state === DossierCatalog::STATE_DONE) {
                $actions[] = DossierCatalog::ACTION_REOPEN;
            } elseif (($step['integration_status_raw'] ?? '') === 'skipped') {
                $actions[] = DossierCatalog::ACTION_UNSKIP;
            }
        } elseif ($open) {
            array_push($actions, DossierCatalog::ACTION_POSTPONE, DossierCatalog::ACTION_REASSIGN);
        }
        if ($open && $memberOwned) {
            $actions[] = DossierCatalog::ACTION_REMIND;
        }

        return $actions;
    }

    /**
     * @param list<array<string, mixed>> $steps
     * @param array<int, mixed> $extraIds identifiants de membres dont le nom est aussi utile
     * @return list<array<string, mixed>>
     */
    public function withOwnerLabels(int $tenantId, array $steps, array $extraIds = []): array
    {
        $ids = array_keys($extraIds);
        foreach ($steps as $s) {
            if ((int) $s['owner_user_id'] > 0) {
                $ids[] = (int) $s['owner_user_id'];
            }
        }
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $names = $ids === [] ? [] : ($this->names)($tenantId, $ids);
        foreach ($steps as $i => $s) {
            $uid = (int) $s['owner_user_id'];
            $steps[$i]['owner_label'] = $uid > 0 && trim((string) ($names[$uid] ?? '')) !== ''
                ? (string) $names[$uid]
                : DossierCatalog::ownerLabels()[$s['owner_kind']];
        }

        return $steps;
    }

    /**
     * Résumé d'un dossier : progression, prochaine action et retards.
     *
     * @param list<array<string, mixed>> $steps
     * @return array{total: int, done: int, percent: int, late: int, blocked: int, open: int, next: ?array<string, mixed>, last_update: ?string}
     */
    public static function summarize(array $steps): array
    {
        $counted = array_values(array_filter($steps, static fn (array $s): bool => $s['status'] !== DossierCatalog::STATUS_SKIPPED));
        $done = count(array_filter($counted, static fn (array $s): bool => $s['status'] === DossierCatalog::STATUS_DONE));
        $open = array_values(array_filter($counted, static fn (array $s): bool => DossierCatalog::isOpenState((string) $s['state'])));
        usort($open, static function (array $a, array $b): int {
            return [$a['late'] ? 0 : 1, $a['due_on'] ?? '9999-99-99', $a['pos'], $a['key']]
                <=> [$b['late'] ? 0 : 1, $b['due_on'] ?? '9999-99-99', $b['pos'], $b['key']];
        });
        $last = null;
        foreach ($steps as $s) {
            $u = (string) ($s['updated_at'] ?? '');
            if ($u !== '' && ($last === null || $u > $last)) {
                $last = $u;
            }
        }

        return [
            'total' => count($counted),
            'done' => $done,
            'percent' => $counted === [] ? 0 : (int) round(100 * $done / count($counted)),
            'late' => count(array_filter($open, static fn (array $s): bool => $s['late'])),
            'blocked' => count(array_filter($open, static fn (array $s): bool => $s['status'] === DossierCatalog::STATUS_BLOCKED)),
            'open' => count($open),
            'next' => $open[0] ?? null,
            'last_update' => $last,
        ];
    }

    /**
     * @param list<array<string, mixed>> $steps
     * @return list<array<string, mixed>>
     */
    public static function filter(array $steps, string $track = '', string $status = '', string $query = ''): array
    {
        $needle = mb_strtolower(trim($query));

        return array_values(array_filter($steps, static function (array $s) use ($track, $status, $needle): bool {
            if ($track !== '' && $s['track'] !== $track) {
                return false;
            }
            if ($status === 'open') {
                if (!DossierCatalog::isOpenState((string) $s['state'])) {
                    return false;
                }
            } elseif ($status !== '' && $s['status'] !== $status && !($status === DossierCatalog::STATUS_OVERDUE && $s['late'])) {
                return false;
            }

            return $needle === '' || str_contains(mb_strtolower($s['title'] . ' ' . $s['owner_label'] . ' ' . $s['description']), $needle);
        }));
    }

    /**
     * Export CSV (séparateur point-virgule, BOM UTF-8 pour Excel). Une cellule qui commencerait par = + - @ est neutralisée.
     *
     * @param list<array<string, mixed>> $steps
     */
    public static function csv(array $steps): string
    {
        $cell = static function (mixed $v): string {
            $t = str_replace(["\r", "\n"], ' ', trim((string) $v));
            if ($t !== '' && in_array($t[0], ['=', '+', '-', '@', "\t"], true)) {
                $t = "'" . $t;
            }

            return '"' . str_replace('"', '""', $t) . '"';
        };
        $tracks = DossierCatalog::trackLabels();
        $statuses = DossierCatalog::statusLabels();
        $lines = [implode(';', array_map($cell, ['Parcours', 'Étape', 'Statut', 'Responsable', 'Ouverture', 'Échéance', 'Réalisation', 'Source', 'Dernière modification']))];
        foreach ($steps as $s) {
            $lines[] = implode(';', array_map($cell, [
                $tracks[$s['track']] ?? $s['track'], $s['title'], $statuses[$s['status']] ?? $s['status'], $s['owner_label'],
                $s['opened_on'] ?? '', $s['due_on'] ?? '', $s['done_at'] ?? '', $s['source_label'], $s['updated_at'] ?? '',
            ]));
        }

        return "\xEF\xBB\xBF" . implode("\r\n", $lines) . "\r\n";
    }
}
