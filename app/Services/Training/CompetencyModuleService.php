<?php

declare(strict_types=1);

namespace App\Services\Training;

use App\Repositories\CompetencyModuleRepository;
use App\Repositories\UserRepository;

/**
 * Gestion des modules compétences d'une organisation et validation de la progression des membres.
 * Les règles de saisie sont statiques (testables sans base).
 */
final class CompetencyModuleService
{
    public const PHASE_LABELS = [
        'ALPHA' => ['label' => 'ALPHA', 'subtitle' => 'Doctrine / cadre légal'],
        'BRAVO' => ['label' => 'BRAVO', 'subtitle' => 'Application pratique'],
        'CHARLIE' => ['label' => 'CHARLIE', 'subtitle' => 'Simulation scénarisée'],
        'DELTA' => ['label' => 'DELTA', 'subtitle' => 'Validation instructeur'],
    ];

    public const DELIVERY_LABELS = [
        'INITIAL' => 'Parcours initial',
        'RENFORCE' => 'Renforcement',
        'RECYCLAGE' => 'Recyclage',
        'CRITIQUE' => 'Critique',
    ];

    public const STATUS_LABELS = [
        'NOT_STARTED' => 'Pas commencé',
        'IN_PROGRESS' => 'En cours',
        'COMPLETED' => 'Validé',
        'FAILED' => 'Non validé',
        'EXPIRED' => 'À renouveler',
    ];

    public function __construct(
        private ?CompetencyModuleRepository $modules = null,
        private ?UserRepository $users = null,
    ) {
        $this->modules ??= new CompetencyModuleRepository();
        $this->users ??= \App\Core\Container::get(UserRepository::class);
    }

    public function schemaReady(): bool
    {
        return $this->modules->schemaReady();
    }

    /** @return list<array<string, mixed>> */
    public function modules(int $tenantId): array
    {
        return $this->modules->listForTenant($tenantId);
    }

    /** @return array<string, mixed>|null */
    public function module(int $tenantId, int $moduleId): ?array
    {
        return $this->modules->find($tenantId, $moduleId);
    }

    /**
     * Valide et enregistre. Retourne [id, null] ou [null, message d'erreur].
     *
     * @param array<string, mixed> $input
     * @return array{0: ?int, 1: ?string}
     */
    public function save(int $tenantId, int $actorUserId, ?int $moduleId, array $input): array
    {
        if (!$this->schemaReady()) {
            return [null, 'Le cadre compétences n’est pas encore installé sur cet environnement.'];
        }
        $existing = $this->modules->listForTenant($tenantId);
        $known = [];
        foreach ($existing as $m) {
            $known[$m['id']] = $m;
        }
        if ($moduleId !== null && !isset($known[$moduleId])) {
            return [null, 'Module introuvable.'];
        }
        [$data, $error] = self::normalizeInput($input, array_keys($known), $moduleId);
        if ($error !== null) {
            return [null, $error];
        }
        if ($this->modules->codeTaken($tenantId, $data['code'], $moduleId ?? 0)) {
            return [null, 'Le code « ' . $data['code'] . ' » est déjà utilisé par un autre module.'];
        }
        $graph = [];
        foreach ($known as $id => $m) {
            $graph[$id] = $m['prereq_ids'];
        }
        if ($moduleId !== null && self::createsCycle($graph, $moduleId, $data['prereq_ids'])) {
            return [null, 'Ces prérequis créeraient une boucle (un module se retrouverait prérequis de lui-même).'];
        }
        try {
            return [$this->modules->save($tenantId, $actorUserId, $moduleId, $data), null];
        } catch (\Throwable) {
            return [null, 'Le module n’a pas pu être enregistré. Réessayez.'];
        }
    }

    public function setActive(int $tenantId, int $moduleId, bool $active): bool
    {
        return $this->schemaReady() && $this->modules->setActive($tenantId, $moduleId, $active);
    }

    /**
     * Tableau de suivi d'un module : chaque membre actif avec son statut et l'état de ses prérequis.
     *
     * @param array<string, mixed> $module
     * @return list<array<string, mixed>>
     */
    public function tracking(int $tenantId, array $module): array
    {
        $progress = $this->modules->progressForModule($tenantId, (int) $module['id']);
        $prereqIds = array_map('intval', (array) ($module['prereq_ids'] ?? []));
        $prereqStatus = $this->modules->statusesForModules($tenantId, $prereqIds);
        try {
            $members = $this->users->listForTenant($tenantId, null, 'active', null, 500, 0);
        } catch (\Throwable) {
            $members = [];
        }
        $rows = [];
        foreach ($members as $u) {
            $uid = (int) ($u['id'] ?? 0);
            if ($uid <= 0) {
                continue;
            }
            $p = $progress[$uid] ?? null;
            $missing = 0;
            foreach ($prereqIds as $req) {
                if (($prereqStatus[$uid][$req] ?? '') !== 'COMPLETED') {
                    $missing++;
                }
            }
            $callsign = trim((string) ($u['callsign'] ?? ''));
            $display = trim((string) ($u['display_name'] ?? ''));
            $status = $p !== null ? (string) $p['effective_status'] : 'NOT_STARTED';
            $validator = $p !== null ? (trim((string) ($p['validator_callsign'] ?? '')) ?: trim((string) ($p['validator_name'] ?? ''))) : '';
            $rows[] = [
                'user_id' => $uid,
                'name' => $callsign !== '' ? $callsign : ($display !== '' ? $display : 'Membre #' . $uid),
                'sub' => $callsign !== '' && $display !== '' && strcasecmp($callsign, $display) !== 0 ? $display : '',
                'status' => $status,
                'status_label' => self::STATUS_LABELS[$status] ?? $status,
                'validated_at' => $p !== null ? self::date($p['validated_at'] ?? null) : '',
                'expires_at' => $p !== null ? self::date($p['expires_at'] ?? null) : '',
                'validator' => $validator,
                'attempts' => $p !== null ? (int) $p['attempts'] : 0,
                'missing_prereqs' => $missing,
            ];
        }
        $order = ['EXPIRED' => 0, 'IN_PROGRESS' => 1, 'FAILED' => 2, 'NOT_STARTED' => 3, 'COMPLETED' => 4];
        usort($rows, static fn (array $a, array $b): int => [$order[$a['status']] ?? 9, $a['name']] <=> [$order[$b['status']] ?? 9, $b['name']]);

        return $rows;
    }

    /**
     * Enregistre le statut de plusieurs membres. Retourne le nombre de lignes modifiées, ou -1 si erreur.
     *
     * @param array<string, mixed> $module
     * @param list<int> $userIds
     */
    public function record(int $tenantId, array $module, int $actorUserId, array $userIds, string $status): int
    {
        if (!in_array($status, CompetencyModuleRepository::STATUSES, true)) {
            return -1;
        }
        $memberIds = [];
        try {
            foreach ($this->users->listForTenant($tenantId, null, 'active', null, 500, 0) as $u) {
                $memberIds[(int) ($u['id'] ?? 0)] = true;
            }
        } catch (\Throwable) {
            return -1;
        }
        $expires = self::expiryFor($status, $module['recurrence_days'] ?? null, time());
        $n = 0;
        foreach (array_values(array_unique(array_map('intval', $userIds))) as $uid) {
            if (!isset($memberIds[$uid])) {
                continue;
            }
            try {
                $this->modules->setProgress($tenantId, (int) $module['id'], $uid, $status, $actorUserId, $expires, null);
                $n++;
            } catch (\Throwable) {
                return -1;
            }
        }

        return $n;
    }

    /**
     * Règles de saisie d'un module.
     *
     * @param array<string, mixed> $in
     * @param list<int> $tenantModuleIds modules existants de l'organisation (prérequis autorisés)
     * @return array{0: array<string, mixed>, 1: ?string}
     */
    public static function normalizeInput(array $in, array $tenantModuleIds, ?int $selfId): array
    {
        $name = trim(preg_replace('/\s+/u', ' ', (string) ($in['name'] ?? '')) ?? '');
        $code = strtoupper(trim((string) ($in['code'] ?? '')));
        $code = preg_replace('/[^A-Z0-9_.-]+/', '-', $code) ?? '';
        $code = trim($code, '-');
        $phase = strtoupper((string) ($in['module_type'] ?? ''));
        $mode = strtoupper((string) ($in['delivery_mode'] ?? 'INITIAL'));
        $duration = trim((string) ($in['duration_min'] ?? ''));
        $days = trim((string) ($in['recurrence_days'] ?? ''));
        $order = trim((string) ($in['custom_order'] ?? ''));
        $prereqs = array_values(array_unique(array_filter(
            array_map('intval', (array) ($in['prereq_ids'] ?? [])),
            static fn (int $id): bool => $id > 0 && $id !== $selfId && in_array($id, $tenantModuleIds, true)
        )));

        $data = [
            'code' => $code,
            'name' => $name,
            'module_type' => $phase,
            'delivery_mode' => $mode,
            'description' => trim((string) ($in['description'] ?? '')),
            'duration_min' => $duration !== '' ? (int) $duration : null,
            'is_active' => !empty($in['is_active']),
            'is_mandatory' => !empty($in['is_mandatory']),
            'custom_order' => $order !== '' ? (int) $order : null,
            'recurrence_days' => $days !== '' ? (int) $days : null,
            'prereq_ids' => $prereqs,
        ];

        $error = match (true) {
            $name === '' => 'Donnez un nom au module.',
            mb_strlen($name) > 180 => 'Le nom dépasse 180 caractères.',
            $code === '' => 'Donnez un code court au module (ex. TIR-01).',
            strlen($code) > 80 => 'Le code dépasse 80 caractères.',
            !in_array($phase, CompetencyModuleRepository::PHASES, true) => 'Choisissez une phase : ALPHA, BRAVO, CHARLIE ou DELTA.',
            !in_array($mode, CompetencyModuleRepository::DELIVERY_MODES, true) => 'Mode de formation inconnu.',
            mb_strlen($data['description']) > 5000 => 'La description dépasse 5000 caractères.',
            $data['duration_min'] !== null && ($data['duration_min'] < 1 || $data['duration_min'] > 100000) => 'Durée invalide (en minutes).',
            $data['recurrence_days'] !== null && ($data['recurrence_days'] < 1 || $data['recurrence_days'] > 3650) => 'Renouvellement invalide : entre 1 et 3650 jours, ou vide.',
            default => null,
        };

        return [$data, $error];
    }

    /**
     * Vrai si donner $newPrereqs à $moduleId crée une boucle dans le graphe des prérequis.
     *
     * @param array<int, list<int>> $graph module => prérequis
     * @param list<int> $newPrereqs
     */
    public static function createsCycle(array $graph, int $moduleId, array $newPrereqs): bool
    {
        $graph[$moduleId] = $newPrereqs;
        $stack = $newPrereqs;
        $seen = [];
        while ($stack !== []) {
            $cur = array_pop($stack);
            if ($cur === $moduleId) {
                return true;
            }
            if (isset($seen[$cur])) {
                continue;
            }
            $seen[$cur] = true;
            foreach ($graph[$cur] ?? [] as $next) {
                $stack[] = $next;
            }
        }

        return false;
    }

    /** Échéance d'une validation : maintenant + renouvellement, sinon aucune. */
    public static function expiryFor(string $status, mixed $recurrenceDays, int $now): ?string
    {
        if ($status !== 'COMPLETED' || !is_int($recurrenceDays) || $recurrenceDays <= 0) {
            return null;
        }

        return date('Y-m-d H:i:s', $now + $recurrenceDays * 86400);
    }

    private static function date(mixed $raw): string
    {
        $ts = is_string($raw) && $raw !== '' ? strtotime($raw) : false;

        return $ts ? date('d/m/Y', $ts) : '';
    }
}
