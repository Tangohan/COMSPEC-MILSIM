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
        $attempts = 0;
        while ($attempts < 50) {
            ++$attempts;
            $seq = $this->configRepo->consumeNextSequence($tenantId);
            if ($seq === null) {
                return ['ok' => false, 'error' => 'Impossible de consommer la séquence.'];
            }
            $candidate = $this->format(
                (string) ($config['pattern'] ?? '{PREFIX}-{NUMBER:4}'),
                (string) ($config['prefix'] ?? ''),
                $seq,
                $slug
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
        $user = $this->users->findById($userId, $tenantId);
        if ($user === null) {
            return null;
        }
        $v = trim((string) ($user['tenant_member_number'] ?? ''));

        return $v !== '' ? $v : null;
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
        $old = trim((string) ($user['tenant_member_number'] ?? ''));
        $old = $old !== '' ? $old : null;
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
