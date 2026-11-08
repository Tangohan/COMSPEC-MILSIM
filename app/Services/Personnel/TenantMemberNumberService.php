<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Repositories\TenantMemberNumberConfigRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UserRepository;
use App\Services\Admin\AdminAuditService;

/**
 * Matricule d’organisation — indépendant du matricule plateforme (athena_identifier).
 * Toutes les opérations sont scopées tenant_id + user_id / tenant_member_number.
 */
final class TenantMemberNumberService
{
    public const MODES = ['free', 'automatic', 'assisted'];

    public const DEFAULT_LABEL = "Matricule d'organisation";

    public const MAX_LENGTH = 100;

    /** Caractères autorisés : alphanumériques, tiret, slash, underscore, point. */
    private const ALLOWED_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9\/\-_\.]*$/';

    /**
     * Modèles prêts à choisir : nom lisible, explication courte et format du matricule.
     * Le même catalogue sert à la communauté (règles) et à chaque unité.
     *
     * @return array<string, array{label: string, help: string, pattern: string}>
     */
    public static function templates(): array
    {
        return [
            'simple' => ['label' => 'Préfixe et numéro', 'help' => 'Le plus courant : un préfixe de votre choix puis un numéro sur 4 chiffres.', 'pattern' => '{PREFIX}-{NUMBER:4}'],
            'annee' => ['label' => 'Préfixe, année et numéro', 'help' => 'Ajoute l’année d’attribution, pratique pour repérer l’ancienneté.', 'pattern' => '{PREFIX}-{YEAR}-{NUMBER:3}'],
            'annee_courte' => ['label' => 'Année courte et numéro', 'help' => 'Deux chiffres d’année puis le numéro, sans préfixe.', 'pattern' => '{YEAR:2}-{NUMBER:4}'],
            'unite' => ['label' => 'Code d’unité et numéro', 'help' => 'Le code de l’unité du membre ouvre le matricule. Sans unité ou sans code, il est simplement omis.', 'pattern' => '{UNIT}-{NUMBER:3}'],
            'unite_grade' => ['label' => 'Unité, grade et numéro', 'help' => 'Code d’unité et grade du membre, puis le numéro.', 'pattern' => '{UNIT}-{GRADE}-{NUMBER:3}'],
            'communaute_unite' => ['label' => 'Communauté, unité et numéro', 'help' => 'Nom court de la communauté, code d’unité, puis le numéro.', 'pattern' => '{TENANT}/{UNIT}/{NUMBER:3}'],
            'communaute' => ['label' => 'Communauté et numéro', 'help' => 'Nom court de la communauté puis le numéro.', 'pattern' => '{TENANT}/{NUMBER:4}'],
            'numero' => ['label' => 'Numéro seul', 'help' => 'Uniquement un numéro sur 5 chiffres.', 'pattern' => '{NUMBER:5}'],
        ];
    }

    /** Clé du modèle prêt à choisir qui correspond à ce format, ou null s’il est personnalisé. */
    public static function templateKeyFor(string $pattern): ?string
    {
        foreach (self::templates() as $key => $t) {
            if ($t['pattern'] === $pattern) {
                return $key;
            }
        }

        return null;
    }

    /** Contrôle un format avant de l’enregistrer pour une unité : message lisible, ou null s’il est utilisable. */
    public static function checkPattern(string $pattern): ?string
    {
        $pattern = trim($pattern);
        if ($pattern === '') {
            return 'Le format est vide.';
        }
        if (strlen($pattern) > 120) {
            return 'Le format est trop long (120 caractères au plus).';
        }
        if (!self::patternHasNumber($pattern)) {
            return 'Le format doit contenir le numéro ({NUMBER} ou {NUMBER:4}) : sinon tous les matricules de l’unité seraient identiques.';
        }
        if (!preg_match(self::ALLOWED_PATTERN, self::formatPattern($pattern, 'ABC', 7, 'COMM', 'UNIT', 'SGT'))) {
            return 'Le format contient un élément inconnu ou un caractère non autorisé. Utilisez les boutons d’éléments, des lettres, chiffres, - / _ et point.';
        }

        return null;
    }

    /** @var array<int, array<int, string>> */
    private array $unitTemplateMemo = [];

    /**
     * Modèle choisi pour chaque unité de la communauté ; une unité absente suit le modèle général.
     *
     * @return array<int, string> identifiant d’unité => modèle
     */
    public function unitTemplates(int $tenantId): array
    {
        if ($tenantId < 1) {
            return [];
        }
        if (!isset($this->unitTemplateMemo[$tenantId])) {
            try {
                $this->unitTemplateMemo[$tenantId] = $this->configRepo->listUnitTemplates($tenantId);
            } catch (\Throwable $e) {
                error_log('[matricules] modèles par unité indisponibles : ' . $e->getMessage());
                $this->unitTemplateMemo[$tenantId] = [];
            }
        }

        return $this->unitTemplateMemo[$tenantId];
    }

    public function unitTemplatesReady(): bool
    {
        try {
            return $this->configRepo->unitTemplatesReady();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Choisit le modèle d’une unité (l’appelant vérifie que l’unité appartient à la communauté).
     * Un modèle vide remet l’unité sur le modèle général.
     *
     * @return array{ok: bool, error?: string}
     */
    public function saveUnitTemplate(int $tenantId, int $unitId, ?string $pattern): array
    {
        if (!$this->schemaReady() || $tenantId < 1 || $unitId < 1 || !$this->unitTemplatesReady()) {
            return ['ok' => false, 'error' => 'Les modèles par unité ne sont pas encore disponibles. Réessayez après la prochaine mise à jour de la plateforme.'];
        }
        $pattern = trim((string) $pattern);
        if ($pattern !== '') {
            $error = self::checkPattern($pattern);
            if ($error !== null) {
                return ['ok' => false, 'error' => $error];
            }
        }
        if (!$this->configRepo->saveUnitTemplate($tenantId, $unitId, $pattern)) {
            return ['ok' => false, 'error' => 'Échec de l’enregistrement du modèle de l’unité.'];
        }
        unset($this->unitTemplateMemo[$tenantId]);

        return ['ok' => true];
    }

    /**
     * Modèle applicable à un membre : celui de son unité s’il existe, sinon le modèle général.
     *
     * @param array<int, string> $unitTemplates
     * @param array<string, mixed>|null $ctx
     */
    private function patternFor(string $defaultPattern, array $unitTemplates, ?array $ctx): string
    {
        $unitId = (int) ($ctx['unit_id'] ?? 0);

        return $unitId > 0 && isset($unitTemplates[$unitId]) ? $unitTemplates[$unitId] : $defaultPattern;
    }

    /** Exemple de matricule pour un format, un code d’unité et un grade donnés (numéro = prochaine séquence). */
    public function example(int $tenantId, string $pattern, string $unitCode = '', string $gradeCode = ''): string
    {
        $config = $this->getConfig($tenantId);

        return $this->format(
            $pattern,
            (string) ($config['prefix'] ?? ''),
            max(1, (int) ($config['next_sequence'] ?? 1)),
            $this->resolveTenantSlug($tenantId),
            self::contextToken($unitCode),
            self::contextToken($gradeCode)
        );
    }

    public function __construct(
        private TenantMemberNumberConfigRepository $configRepo,
        private UserRepository $users,
        private TenantRepository $tenants,
        private ?AdminAuditService $adminAudit = null,
    ) {}

    public function schemaReady(): bool
    {
        return $this->configRepo->schemaReady() && $this->users->hasTenantMemberNumberColumn();
    }

    /** @return array<string, mixed> */
    public function getConfig(int $tenantId): array
    {
        return $this->configRepo->getOrCreate($tenantId);
    }

    /**
     * @param array<string, mixed> $data
     * @return array{ok: bool, error?: string, config?: array<string, mixed>}
     */
    public function saveConfig(int $tenantId, array $data): array
    {
        if (!$this->schemaReady() || $tenantId < 1) {
            return ['ok' => false, 'error' => 'Module matricule d’organisation indisponible.'];
        }
        $mode = strtolower(trim((string) ($data['mode'] ?? 'free')));
        if (!in_array($mode, self::MODES, true)) {
            $mode = 'free';
        }
        $label = trim((string) ($data['label'] ?? self::DEFAULT_LABEL));
        if ($label === '') {
            $label = self::DEFAULT_LABEL;
        }
        if (function_exists('mb_substr')) {
            $label = mb_substr($label, 0, 80);
        } else {
            $label = substr($label, 0, 80);
        }
        $pattern = trim((string) ($data['pattern'] ?? '{PREFIX}-{NUMBER:4}'));
        if ($pattern === '') {
            $pattern = '{PREFIX}-{NUMBER:4}';
        }
        if ($mode !== 'free' && !self::patternHasNumber($pattern)) {
            return ['ok' => false, 'error' => 'Le format doit contenir le numéro de séquence ({NUMBER} ou {NUMBER:4}) pour que chaque matricule généré soit différent.'];
        }
        $prefix = trim((string) ($data['prefix'] ?? ''));
        if (function_exists('mb_substr')) {
            $prefix = mb_substr($prefix, 0, 40);
            $pattern = mb_substr($pattern, 0, 120);
        } else {
            $prefix = substr($prefix, 0, 40);
            $pattern = substr($pattern, 0, 120);
        }

        $ok = $this->configRepo->updateConfig($tenantId, [
            'enabled' => !empty($data['enabled']),
            'label' => $label,
            'mode' => $mode,
            'pattern' => $pattern,
            'prefix' => $prefix,
            'next_sequence' => max(1, (int) ($data['next_sequence'] ?? 1)),
            'unique_required' => array_key_exists('unique_required', $data)
                ? !empty($data['unique_required'])
                : true,
            'required' => !empty($data['required']),
        ]);

        if (!$ok) {
            return ['ok' => false, 'error' => 'Échec de l’enregistrement de la configuration.'];
        }

        return ['ok' => true, 'config' => $this->getConfig($tenantId)];
    }

    public function format(string $pattern, string $prefix, int $number, ?string $tenantSlug = null, ?string $unitCode = null, ?string $gradeCode = null): string
    {
        return self::formatPattern($pattern, $prefix, $number, $tenantSlug, $unitCode, $gradeCode);
    }

    public static function formatPattern(string $pattern, string $prefix, int $number, ?string $tenantSlug = null, ?string $unitCode = null, ?string $gradeCode = null): string
    {
        $year = (int) date('Y');
        $month = date('m');
        $out = $pattern;

        $out = str_replace(['{PREFIX}', '{prefix}'], $prefix, $out);
        $out = str_replace(['{YEAR:2}', '{year:2}'], substr((string) $year, -2), $out);
        $out = str_replace(['{YEAR}', '{year}'], (string) $year, $out);
        $out = str_replace(['{MONTH}', '{month}'], $month, $out);
        $out = str_replace(['{TENANT}', '{tenant}'], (string) ($tenantSlug ?? ''), $out);
        $out = str_replace(['{UNIT}', '{unit}'], (string) ($unitCode ?? ''), $out);
        $out = str_replace(['{GRADE}', '{grade}'], (string) ($gradeCode ?? ''), $out);

        if (preg_match('/\{NUMBER:(\d+)\}/i', $out, $m)) {
            $pad = max(1, min(8, (int) $m[1]));
            $out = preg_replace('/\{NUMBER:\d+\}/i', str_pad((string) $number, $pad, '0', STR_PAD_LEFT), $out) ?? $out;
        } else {
            $out = str_replace(['{NUMBER}', '{number}'], (string) $number, $out);
        }

        // Nettoyage des séparateurs orphelins si UNIT/GRADE absents.
        $out = preg_replace('/-{2,}/', '-', $out) ?? $out;
        $out = preg_replace('/\/{2,}/', '/', $out) ?? $out;
        $out = trim($out, '-/');

        return $out;
    }

    public static function patternHasNumber(string $pattern): bool
    {
        return preg_match('/\{NUMBER(:\d+)?\}/i', $pattern) === 1;
    }

    /**
     * Contrôle du registre de la communauté (doublons, trous, format) pour la page Matricules.
     *
     * @param list<array{id: int, tenant_member_number?: string|null}> $members
     * @return array<string, mixed>
     */
    public function analyzeRegistry(int $tenantId, array $members): array
    {
        $config = $this->schemaReady() ? $this->getConfig($tenantId) : [];

        return \App\Support\MemberNumberAnalysis::analyze(
            $members,
            (string) ($config['pattern'] ?? '{PREFIX}-{NUMBER:4}'),
            (string) ($config['prefix'] ?? ''),
            $this->resolveTenantSlug($tenantId),
            (int) ($config['next_sequence'] ?? 1),
            array_values(array_unique($this->unitTemplates($tenantId)))
        );
    }

    /** Valeur du jeton {TENANT} pour l’aperçu du format. */
    public function tenantToken(int $tenantId): string
    {
        return (string) ($this->resolveTenantSlug($tenantId) ?? '');
    }

    /**
     * Attribue le prochain matricule à chaque membre listé qui n’en a pas encore.
     *
     * @param list<int> $userIds
     * @return array{assigned: int, failed: int, errors: list<string>}
     */
    public function assignNextToMembersWithout(int $tenantId, array $userIds, ?int $actorUserId, int $limit = 200): array
    {
        $assigned = 0;
        $failed = 0;
        $errors = [];
        foreach (array_slice(array_values(array_unique(array_map('intval', $userIds))), 0, max(1, $limit)) as $uid) {
            if ($uid < 1 || $this->getForUser($tenantId, $uid) !== null) {
                continue;
            }
            $r = $this->assignNext($tenantId, $uid, $actorUserId, 'Attribution groupée (back-office)', 'bulk');
            if (!empty($r['ok'])) {
                ++$assigned;
            } else {
                ++$failed;
                $errors[] = (string) ($r['error'] ?? 'échec');
                if (!$this->schemaReady() || empty($this->getConfig($tenantId)['enabled'])) {
                    break;
                }
            }
        }

        return ['assigned' => $assigned, 'failed' => $failed, 'errors' => array_values(array_unique($errors))];
    }

    /** Valeur d’un élément {UNIT} / {GRADE} : lettres et chiffres seulement, en capitales (20 au plus). */
    public static function contextToken(string $raw): string
    {
        $v = preg_replace('/[^A-Za-z0-9]/', '', $raw) ?? '';

        return strtoupper(substr($v, 0, 20));
    }

    public static function patternUsesContext(string $pattern): bool
    {
        return stripos($pattern, '{UNIT}') !== false || stripos($pattern, '{GRADE}') !== false;
    }

    /**
     * Code d’unité et grade de chaque membre, prêts pour le modèle (vides si le modèle ne les utilise pas
     * ou si l’information manque : la génération ne doit jamais échouer pour autant).
     *
     * @param list<int> $userIds
     * @return array<int, array{unit: string, grade: string}>
     */
    public function contextsFor(int $tenantId, array $userIds, string $pattern): array
    {
        if ($userIds === [] || (!self::patternUsesContext($pattern) && $this->unitTemplates($tenantId) === [])) {
            return [];
        }
        try {
            $raw = $this->users->memberNumberContextsForTenant($tenantId, $userIds);
        } catch (\Throwable $e) {
            error_log('[matricules] unité / grade indisponibles : ' . $e->getMessage());

            return [];
        }
        $out = [];
        foreach ($raw as $uid => $c) {
            $out[(int) $uid] = [
                'unit' => self::contextToken((string) ($c['unit'] ?? '')),
                'grade' => self::contextToken((string) ($c['grade'] ?? '')),
                'unit_id' => (int) ($c['unit_id'] ?? 0),
            ];
        }

        return $out;
    }

    /**
     * Contrôle d’une valeur sans rien écrire (aperçu d’import) : message d’erreur, ou null si elle passerait.
     */
    public function checkManual(int $tenantId, int $userId, ?string $value): ?string
    {
        if (!$this->schemaReady() || $tenantId < 1 || $userId < 1) {
            return 'Module indisponible.';
        }
        $config = $this->getConfig($tenantId);
        $normalized = $this->normalizeValue($value);
        if ($normalized === null) {
            return !empty($config['required']) ? 'Le matricule d’organisation est obligatoire.' : null;
        }

        return $this->validateValue($tenantId, $normalized, $userId, $config);
    }

    /**
     * Rétablit l’ancienne valeur d’une ligne de l’historique (matricule retiré ou remplacé par erreur).
     *
     * @return array{ok: bool, value?: string|null, error?: string}
     */
    public function restoreFromAudit(int $tenantId, int $auditId, ?int $actorUserId): array
    {
        if (!$this->schemaReady() || $tenantId < 1 || $auditId < 1) {
            return ['ok' => false, 'error' => 'Module indisponible.'];
        }
        $row = $this->configRepo->findAudit($tenantId, $auditId);
        if ($row === null) {
            return ['ok' => false, 'error' => 'Ce changement est introuvable dans l’historique.'];
        }
        $old = trim((string) ($row['old_value'] ?? ''));
        if ($old === '') {
            return ['ok' => false, 'error' => 'Il n’y avait pas de matricule avant ce changement : rien à rétablir.'];
        }

        return $this->assignManual($tenantId, (int) $row['user_id'], $old, $actorUserId, 'Rétabli depuis l’historique', 'restore');
    }

    /**
     * Aperçu d’une renumérotation selon le modèle actuel (aucune écriture).
     *
     * $scope : 'off' (seulement les matricules hors modèle) ou 'all' (tous les matricules existants).
     * $numbering : 'keep' (garder le numéro déjà présent à la fin du matricule) ou 'next' (numéroter à la suite
     * de la séquence). Une ligne en conflit (même nouveau matricule qu’un autre membre) reste inchangée.
     *
     * @param list<array{id: int, tenant_member_number?: string|null}> $members
     * @return array{error?: string, rows: list<array{id: int, old: string, new: string, status: string, reason: string}>, changes: int, conflicts: int, next_after: int, hash: string}
     */
    public function planRenumber(int $tenantId, array $members, string $scope, string $numbering): array
    {
        $empty = ['rows' => [], 'changes' => 0, 'conflicts' => 0, 'next_after' => 1, 'hash' => ''];
        if (!$this->schemaReady() || $tenantId < 1) {
            return ['error' => 'Module indisponible.'] + $empty;
        }
        $config = $this->getConfig($tenantId);
        $pattern = (string) ($config['pattern'] ?? '{PREFIX}-{NUMBER:4}');
        if (!self::patternHasNumber($pattern)) {
            return ['error' => 'Le modèle actuel n’a pas de numéro : la renumérotation donnerait le même matricule à tout le monde.'] + $empty;
        }
        $prefix = (string) ($config['prefix'] ?? '');
        $slug = $this->resolveTenantSlug($tenantId);
        $next = max(1, (int) ($config['next_sequence'] ?? 1));
        $unique = !array_key_exists('unique_required', $config) || !empty($config['unique_required']);
        $scope = $scope === 'all' ? 'all' : 'off';
        $numbering = $numbering === 'next' ? 'next' : 'keep';
        $unitTemplates = $this->unitTemplates($tenantId);
        $withNumber = [];
        foreach ($members as $m) {
            if (trim((string) ($m['tenant_member_number'] ?? '')) !== '') {
                $withNumber[] = (int) $m['id'];
            }
        }
        $ctx = $this->contextsFor($tenantId, $withNumber, $pattern);
        // Chaque membre est comparé au modèle de son unité (ou au modèle général) ; expressions calculées une fois par modèle.
        $regexCache = [];
        $regexesFor = function (string $p) use (&$regexCache, $prefix, $slug): array {
            return $regexCache[$p] ??= \App\Support\MemberNumberAnalysis::patternRegexes($p, $prefix, $slug);
        };

        $inScope = [];
        $fixed = [];
        $maxFixed = 0;
        usort($members, static fn (array $a, array $b): int => (int) $a['id'] <=> (int) $b['id']);
        foreach ($members as $m) {
            $old = trim((string) ($m['tenant_member_number'] ?? ''));
            if ($old === '') {
                continue;
            }
            $seqOf = \App\Support\MemberNumberAnalysis::matchSequence($old, $regexesFor($this->patternFor($pattern, $unitTemplates, $ctx[(int) $m['id']] ?? null)));
            if ($scope === 'all' || $seqOf === null) {
                $inScope[] = ['id' => (int) $m['id'], 'old' => $old];
            } else {
                $fixed[mb_strtolower($old)] = true;
                $maxFixed = max($maxFixed, $seqOf);
            }
        }

        // Numéro gardé : les chiffres de fin du matricule actuel. Sans chiffres, on numérote après le plus grand
        // numéro déjà présent, pour ne pas retomber sur un numéro gardé.
        $kept = [];
        foreach ($inScope as $r) {
            $kept[$r['id']] = $numbering === 'keep' && preg_match_all('/\d+/', $r['old'], $mm) > 0 ? (int) end($mm[0]) : 0;
        }
        $rows = [];
        $seq = $numbering === 'keep' ? max($next, ($kept === [] ? 0 : max($kept)) + 1, $maxFixed + 1) : $next;
        $maxUsed = 0;
        foreach ($inScope as $r) {
            $n = $kept[$r['id']];
            if ($n < 1) {
                $n = $seq++;
            }
            $maxUsed = max($maxUsed, $n);
            $c = $ctx[$r['id']] ?? ['unit' => '', 'grade' => '', 'unit_id' => 0];
            $new = $this->format($this->patternFor($pattern, $unitTemplates, $c), $prefix, $n, $slug, $c['unit'], $c['grade']);
            $status = $new === $r['old'] ? 'same' : 'change';
            $reason = '';
            $len = function_exists('mb_strlen') ? mb_strlen($new) : strlen($new);
            if ($status === 'change' && ($len < 1 || $len > self::MAX_LENGTH || !preg_match(self::ALLOWED_PATTERN, $new))) {
                $status = 'conflict';
                $reason = 'Le nouveau matricule contient des caractères non autorisés.';
            }
            $rows[] = ['id' => $r['id'], 'old' => $r['old'], 'new' => $new, 'status' => $status, 'reason' => $reason];
        }

        // Conflits : un nouveau matricule déjà porté par un membre hors renumérotation, par une ligne
        // qui garde son ancienne valeur, ou proposé deux fois. Répété jusqu’à stabilité.
        if ($unique) {
            do {
                $changed = false;
                $taken = $fixed;
                $counts = [];
                foreach ($rows as $r) {
                    if ($r['status'] === 'conflict') {
                        $taken[mb_strtolower($r['old'])] = true;
                    } else {
                        $k = mb_strtolower($r['new']);
                        $counts[$k] = ($counts[$k] ?? 0) + 1;
                    }
                }
                foreach ($rows as $i => $r) {
                    if ($r['status'] !== 'change') {
                        continue;
                    }
                    $k = mb_strtolower($r['new']);
                    if (isset($taken[$k]) || ($counts[$k] ?? 0) > 1) {
                        $rows[$i]['status'] = 'conflict';
                        $rows[$i]['reason'] = isset($taken[$k])
                            ? 'Ce matricule est déjà porté par un autre membre.'
                            : 'Ce matricule serait donné à plusieurs membres.';
                        $changed = true;
                    }
                }
            } while ($changed);
        }

        $changes = count(array_filter($rows, static fn (array $r): bool => $r['status'] === 'change'));
        $conflicts = count(array_filter($rows, static fn (array $r): bool => $r['status'] === 'conflict'));
        $nextAfter = max($seq, $maxUsed + 1, $next);
        $hash = sha1((string) json_encode([$scope, $numbering, $pattern, $unitTemplates, $prefix, $nextAfter, array_map(
            static fn (array $r): array => [$r['id'], $r['old'], $r['new'], $r['status']],
            $rows
        )]));

        return ['rows' => $rows, 'changes' => $changes, 'conflicts' => $conflicts, 'next_after' => $nextAfter, 'hash' => $hash];
    }

    /**
     * Applique une renumérotation vue en aperçu. Refusée si le registre a changé depuis (empreinte différente).
     * Les matricules concernés sont d’abord libérés, puis réattribués : un échange de numéros entre deux
     * membres ne bute donc pas sur l’unicité.
     *
     * @param list<array{id: int, tenant_member_number?: string|null}> $members
     * @return array{ok: bool, error?: string, changed?: int, failed?: int, conflicts?: int}
     */
    public function applyRenumber(int $tenantId, array $members, string $scope, string $numbering, string $hash, ?int $actorUserId): array
    {
        $plan = $this->planRenumber($tenantId, $members, $scope, $numbering);
        if (isset($plan['error'])) {
            return ['ok' => false, 'error' => $plan['error']];
        }
        if ($hash === '' || !hash_equals($plan['hash'], $hash)) {
            return ['ok' => false, 'error' => 'Le registre a changé depuis l’aperçu : vérifiez de nouveau avant d’appliquer.'];
        }
        $todo = array_values(array_filter($plan['rows'], static fn (array $r): bool => $r['status'] === 'change'));
        if ($todo === []) {
            return ['ok' => true, 'changed' => 0, 'failed' => 0, 'conflicts' => $plan['conflicts']];
        }
        foreach ($todo as $r) {
            $this->users->updateTenantMemberNumber($r['id'], $tenantId, null);
        }
        $config = $this->getConfig($tenantId);
        $changed = 0;
        $failed = 0;
        foreach ($todo as $r) {
            $error = $this->validateValue($tenantId, $r['new'], $r['id'], $config);
            if ($error === null && $this->users->updateTenantMemberNumber($r['id'], $tenantId, $r['new'])) {
                ++$changed;
                $this->configRepo->appendAudit($tenantId, $r['id'], $r['old'], $r['new'], $actorUserId, 'Renumérotation selon le modèle', 'renumber');
                if ($this->adminAudit !== null) {
                    $this->adminAudit->logMemberNumberChanged($tenantId, $actorUserId ?? 0, $r['id'], $r['old'], $r['new'], 'Renumérotation selon le modèle');
                }
            } else {
                ++$failed;
                $this->users->updateTenantMemberNumber($r['id'], $tenantId, $r['old']);
            }
        }
        if ($plan['next_after'] > max(1, (int) ($config['next_sequence'] ?? 1))) {
            $this->setNextSequence($tenantId, $plan['next_after']);
        }

        return ['ok' => true, 'changed' => $changed, 'failed' => $failed, 'conflicts' => $plan['conflicts']];
    }

    /** Recale la prochaine séquence (jamais en dessous de 1). */
    public function setNextSequence(int $tenantId, int $next): bool
    {
        if (!$this->schemaReady() || $tenantId < 1) {
            return false;
        }
        $config = $this->getConfig($tenantId);
        $config['next_sequence'] = max(1, $next);

        return $this->configRepo->updateConfig($tenantId, $config);
    }

    public function previewNext(int $tenantId): ?string
    {
        if (!$this->schemaReady()) {
            return null;
        }
        $config = $this->getConfig($tenantId);
        if (empty($config['enabled'])) {
            return null;
        }
        $mode = (string) ($config['mode'] ?? 'free');
        if ($mode === 'free') {
            return null;
        }
        $seq = max(1, (int) ($config['next_sequence'] ?? 1));
        $slug = $this->resolveTenantSlug($tenantId);

        return $this->format(
            (string) ($config['pattern'] ?? '{PREFIX}-{NUMBER:4}'),
            (string) ($config['prefix'] ?? ''),
            $seq,
            $slug
        );
    }

    /**
     * @return array{ok: bool, value?: string|null, error?: string}
     */
    public function assignManual(
        int $tenantId,
        int $userId,
        ?string $value,
        ?int $actorUserId,
        ?string $reason = null,
        string $source = 'manual'
    ): array {
        if (!$this->schemaReady() || $tenantId < 1 || $userId < 1) {
            return ['ok' => false, 'error' => 'Module indisponible.'];
        }
        $config = $this->getConfig($tenantId);
        $normalized = $this->normalizeValue($value);
        if ($normalized === null) {
            if (!empty($config['required'])) {
                return ['ok' => false, 'error' => 'Le matricule d’organisation est obligatoire.'];
            }

            return $this->applyValue($tenantId, $userId, null, $actorUserId, $reason, $source);
        }

        $validation = $this->validateValue($tenantId, $normalized, $userId, $config);
        if ($validation !== null) {
            return ['ok' => false, 'error' => $validation];
        }

        return $this->applyValue($tenantId, $userId, $normalized, $actorUserId, $reason, $source);
    }

    /**
     * Génère et attribue le prochain matricule (modes automatic / assisted / régénération).
     *
     * @return array{ok: bool, value?: string, error?: string}
     */
    public function assignNext(int $tenantId, int $userId, ?int $actorUserId, ?string $reason = null, string $source = 'automatic'): array
    {
        if (!$this->schemaReady() || $tenantId < 1 || $userId < 1) {
            return ['ok' => false, 'error' => 'Module indisponible.'];
        }
        $config = $this->getConfig($tenantId);
        if (empty($config['enabled'])) {
            return ['ok' => false, 'error' => 'Les matricules d’organisation sont désactivés.'];
        }
        $slug = $this->resolveTenantSlug($tenantId);
        $pattern = (string) ($config['pattern'] ?? '{PREFIX}-{NUMBER:4}');
        $ctx = $this->contextsFor($tenantId, [$userId], $pattern)[$userId] ?? ['unit' => '', 'grade' => '', 'unit_id' => 0];
        $pattern = $this->patternFor($pattern, $this->unitTemplates($tenantId), $ctx);
        $attempts = 0;
        while ($attempts < 50) {
            ++$attempts;
            $seq = $this->configRepo->consumeNextSequence($tenantId);
            if ($seq === null) {
                return ['ok' => false, 'error' => 'Impossible de consommer la séquence.'];
            }
            $candidate = $this->format(
                $pattern,
                (string) ($config['prefix'] ?? ''),
                $seq,
                $slug,
                $ctx['unit'],
                $ctx['grade']
            );
            $validation = $this->validateValue($tenantId, $candidate, $userId, $config);
            if ($validation !== null) {
                continue;
            }

            return $this->applyValue($tenantId, $userId, $candidate, $actorUserId, $reason, $source);
        }

        return ['ok' => false, 'error' => 'Aucun matricule libre après plusieurs tentatives.'];
    }

    /**
     * Attribution selon le mode configuré (création / intégration).
     *
     * @return array{ok: bool, value?: string|null, error?: string, skipped?: bool}
     */
    public function assignAccordingToMode(
        int $tenantId,
        int $userId,
        ?string $manualValue,
        ?int $actorUserId,
        ?string $reason = null
    ): array {
        if (!$this->schemaReady()) {
            return ['ok' => true, 'skipped' => true];
        }
        $config = $this->getConfig($tenantId);
        if (empty($config['enabled'])) {
            return ['ok' => true, 'skipped' => true];
        }
        $mode = (string) ($config['mode'] ?? 'free');
        $manual = $this->normalizeValue($manualValue);

        if ($mode === 'automatic') {
            if ($manual !== null) {
                return $this->assignManual($tenantId, $userId, $manual, $actorUserId, $reason, 'manual_override');
            }

            return $this->assignNext($tenantId, $userId, $actorUserId, $reason, 'automatic');
        }

        if ($mode === 'assisted') {
            if ($manual !== null) {
                return $this->assignManual($tenantId, $userId, $manual, $actorUserId, $reason, 'assisted');
            }

            return $this->assignNext($tenantId, $userId, $actorUserId, $reason, 'assisted');
        }

        // free
        if ($manual === null && empty($config['required'])) {
            return ['ok' => true, 'value' => null];
        }

        return $this->assignManual($tenantId, $userId, $manual, $actorUserId, $reason, 'manual');
    }

    /**
     * Import CSV : valide et applique un matricule organisation pour un membre du tenant.
     *
     * @return array{ok: bool, value?: string|null, error?: string}
     */
    public function importForUser(int $tenantId, int $userId, string $rawValue, ?int $actorUserId): array
    {
        return $this->assignManual($tenantId, $userId, $rawValue, $actorUserId, 'Import CSV', 'import');
    }

    public function getForUser(int $tenantId, int $userId): ?string
    {
        if (!$this->schemaReady() || $tenantId < 1 || $userId < 1) {
            return null;
        }
        return $this->users->tenantMemberNumberFor($userId, $tenantId);
    }

    /**
     * Payload API : platform_number, tenant_member_number, display_number.
     *
     * @param array<string, mixed> $userRow
     * @return array{platform_number: ?string, tenant_member_number: ?string, display_number: ?string}
     */
    public static function identityPayload(array $userRow): array
    {
        $platform = trim((string) ($userRow['athena_identifier'] ?? ''));
        $org = trim((string) ($userRow['tenant_member_number'] ?? ''));
        $platform = $platform !== '' ? $platform : null;
        $org = $org !== '' ? $org : null;

        return [
            'platform_number' => $platform,
            'tenant_member_number' => $org,
            'display_number' => $org ?? $platform,
        ];
    }

    public static function displayNumber(?string $tenantMemberNumber, ?string $platformNumber): ?string
    {
        $org = trim((string) $tenantMemberNumber);
        if ($org !== '') {
            return $org;
        }
        $plat = trim((string) $platformNumber);

        return $plat !== '' ? $plat : null;
    }

    /** @param array<string, mixed> $config */
    private function validateValue(int $tenantId, string $value, int $excludeUserId, array $config): ?string
    {
        $len = function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
        if ($len < 1 || $len > self::MAX_LENGTH) {
            return 'Longueur invalide (1–' . self::MAX_LENGTH . ' caractères).';
        }
        if (!preg_match(self::ALLOWED_PATTERN, $value)) {
            return 'Caractères non autorisés. Utilisez lettres, chiffres, - / _ .';
        }
        $unique = !array_key_exists('unique_required', $config) || !empty($config['unique_required']);
        if ($unique && $this->users->tenantMemberNumberExists($tenantId, $value, $excludeUserId)) {
            return 'Ce matricule d’organisation est déjà attribué dans cette communauté.';
        }

        return null;
    }

    private function normalizeValue(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $v = trim($value);
        if ($v === '') {
            return null;
        }
        if (function_exists('mb_substr')) {
            $v = mb_substr($v, 0, self::MAX_LENGTH);
        } else {
            $v = substr($v, 0, self::MAX_LENGTH);
        }

        return $v;
    }

    /**
     * @return array{ok: bool, value?: string|null, error?: string}
     */
    private function applyValue(
        int $tenantId,
        int $userId,
        ?string $value,
        ?int $actorUserId,
        ?string $reason,
        string $source
    ): array {
        $user = $this->users->findById($userId, $tenantId);
        if ($user === null) {
            return ['ok' => false, 'error' => 'Membre introuvable dans cette communauté.'];
        }
        $old = $this->users->tenantMemberNumberFor($userId, $tenantId);
        if ($old === $value) {
            return ['ok' => true, 'value' => $value];
        }

        $ok = $this->users->updateTenantMemberNumber($userId, $tenantId, $value);
        if (!$ok) {
            return ['ok' => false, 'error' => 'Échec de mise à jour du matricule.'];
        }

        $this->configRepo->appendAudit($tenantId, $userId, $old, $value, $actorUserId, $reason, $source);
        if ($this->adminAudit !== null) {
            $this->adminAudit->logMemberNumberChanged(
                $tenantId,
                $actorUserId ?? 0,
                $userId,
                $old,
                $value,
                $reason
            );
        }

        return ['ok' => true, 'value' => $value];
    }

    private function resolveTenantSlug(int $tenantId): ?string
    {
        try {
            $tenant = $this->tenants->findById($tenantId);
            if (!is_array($tenant)) {
                return null;
            }
            $slug = trim((string) ($tenant['slug'] ?? ''));
            if ($slug !== '') {
                return strtoupper($slug);
            }
            $name = trim((string) ($tenant['name'] ?? ''));

            return $name !== '' ? strtoupper(preg_replace('/\s+/', '', $name) ?? $name) : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
