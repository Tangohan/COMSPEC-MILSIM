<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Repositories\CallsignSequenceRepository;
use App\Repositories\PersonnelCareerEventRepository;
use App\Repositories\PersonnelProfileRepository;
use App\Repositories\UserRepository;

/**
 * Générateur d’indicatifs transactionnel par organisation (pas de MAX+1).
 */
final class CallsignSequenceService
{
    public const MODES = ['NUMERIC', 'PREFIX_NUMERIC', 'CUSTOM_PATTERN', 'MANUAL'];

    public function __construct(
        private CallsignSequenceRepository $sequences,
        private UserRepository $users,
        private PersonnelProfileRepository $profiles,
        private PersonnelCareerEventRepository $careerEvents,
    ) {}

    public function schemaReady(): bool
    {
        return $this->sequences->schemaReady();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listSequences(int $tenantId): array
    {
        return $this->sequences->listForTenant($tenantId);
    }

    /**
     * @param array<string, mixed> $data
     * @param list<array{range_start?: int, range_end?: int, label?: string, purpose?: string}> $ranges
     */
    public function createSequence(int $tenantId, array $data, array $ranges = []): ?int
    {
        $normalized = $this->normalizeSequencePayload($data);
        if ($normalized === null) {
            return null;
        }
        if (!empty($normalized['is_default'])) {
            $this->sequences->clearDefaultFlags($tenantId);
        }
        $id = $this->sequences->insert($tenantId, $normalized);
        if ($id !== null && $ranges !== []) {
            $this->sequences->replaceReservedRanges($tenantId, $id, $ranges);
        }

        return $id;
    }

    /**
     * @param array<string, mixed> $data
     * @param list<array{range_start?: int, range_end?: int, label?: string, purpose?: string}>|null $ranges
     */
    public function updateSequence(int $tenantId, int $sequenceId, array $data, ?array $ranges = null): bool
    {
        $existing = $this->sequences->findById($tenantId, $sequenceId);
        if ($existing === null) {
            return false;
        }
        $normalized = $this->normalizeSequencePayload($data, $existing);
        if ($normalized === null) {
            return false;
        }
        if (!empty($normalized['is_default'])) {
            $this->sequences->clearDefaultFlags($tenantId, $sequenceId);
        }
        $ok = $this->sequences->update($tenantId, $sequenceId, $normalized);
        if ($ok && $ranges !== null) {
            $this->sequences->replaceReservedRanges($tenantId, $sequenceId, $ranges);
        }

        return $ok;
    }

    public function formatFromSequence(array $sequence, int $number): string
    {
        $mode = (string) ($sequence['mode'] ?? 'PREFIX_NUMERIC');
        $prefix = (string) ($sequence['prefix'] ?? '');
        $suffix = (string) ($sequence['suffix'] ?? '');
        $padding = max(0, min(8, (int) ($sequence['padding'] ?? 2)));
        $pattern = (string) ($sequence['pattern'] ?? '{PREFIX}-{NUMBER:02}');
        $num = $padding > 0 ? str_pad((string) $number, $padding, '0', STR_PAD_LEFT) : (string) $number;

        return match ($mode) {
            'NUMERIC' => $num . $suffix,
            'MANUAL' => '',
            'CUSTOM_PATTERN' => $this->applyPattern($pattern, $prefix, $suffix, $number, $padding),
            default => trim($prefix) !== ''
                ? $prefix . (str_ends_with($prefix, '-') ? '' : '-') . $num . $suffix
                : $num . $suffix,
        };
    }

    public function previewNext(int $tenantId, int $sequenceId): ?string
    {
        $seq = $this->sequences->findById($tenantId, $sequenceId);
        if ($seq === null || empty($seq['is_active'])) {
            return null;
        }
        if ((string) ($seq['mode'] ?? '') === 'MANUAL') {
            return null;
        }
        $candidate = max((int) ($seq['start_number'] ?? 1), (int) ($seq['current_number'] ?? 1));
        $increment = max(1, (int) ($seq['increment_by'] ?? 1));
        $ranges = $this->sequences->listReservedRanges($tenantId, $sequenceId);
        $guard = 0;
        while ($guard < 1000) {
            ++$guard;
            $inReserved = false;
            foreach ($ranges as $range) {
                if ($candidate >= $range['range_start'] && $candidate <= $range['range_end']) {
                    $inReserved = true;
                    break;
                }
            }
            if (!$inReserved) {
                break;
            }
            $candidate += $increment;
        }

        return $this->formatFromSequence($seq, $candidate);
    }

    /**
     * Attribue le prochain indicatif de la séquence à l’utilisateur (transactionnel).
     *
     * @return array{ok: bool, callsign?: string, error?: string}
     */
    public function allocateNextForUser(
        int $tenantId,
        int $userId,
        ?int $sequenceId,
        ?int $actorUserId,
        string $reason = 'Attribution automatique d’indicatif'
    ): array {
        if (!$this->sequences->schemaReady()) {
            return ['ok' => false, 'error' => 'Module indicatifs indisponible.'];
        }
        $seq = $sequenceId !== null && $sequenceId > 0
            ? $this->sequences->findById($tenantId, $sequenceId)
            : $this->sequences->findDefault($tenantId);
        if ($seq === null) {
            return ['ok' => false, 'error' => 'Aucune séquence d’indicatif active.'];
        }
        if ((string) ($seq['mode'] ?? '') === 'MANUAL') {
            return ['ok' => false, 'error' => 'Cette séquence est en mode manuel.'];
        }

        $attempts = 0;
        while ($attempts < 50) {
            ++$attempts;
            $consumed = $this->sequences->consumeNextNumber($tenantId, (int) $seq['id']);
            if ($consumed === null) {
                return ['ok' => false, 'error' => 'Impossible de consommer un numéro de séquence.'];
            }
            $callsign = $this->formatFromSequence($consumed['sequence'], $consumed['number']);
            if ($callsign === '' || $this->sequences->isForbidden($tenantId, $callsign)) {
                continue;
            }
            if ($this->users->callsignExistsInTenant($tenantId, $callsign, $userId)) {
                continue;
            }

            return $this->applyCallsign($tenantId, $userId, (int) $seq['id'], $callsign, $actorUserId, $reason, [
                'source' => 'sequence_allocate',
                'sequence_id' => (int) $seq['id'],
                'number' => $consumed['number'],
            ]);
        }

        return ['ok' => false, 'error' => 'Aucun indicatif libre trouvé après plusieurs tentatives.'];
    }

    /**
     * Attribution / modification manuelle (jamais silencieuse — historique obligatoire).
     *
     * @return array{ok: bool, callsign?: string, error?: string}
     */
    public function assignManual(
        int $tenantId,
        int $userId,
        string $callsign,
        ?int $actorUserId,
        string $reason,
        ?int $sequenceId = null
    ): array {
        $callsign = trim($callsign);
        if ($callsign === '') {
            return ['ok' => false, 'error' => 'Indicatif vide.'];
        }
        if ($this->sequences->schemaReady() && $this->sequences->isForbidden($tenantId, $callsign)) {
            return ['ok' => false, 'error' => 'Cet indicatif est interdit.'];
        }
        if ($this->users->callsignExistsInTenant($tenantId, $callsign, $userId)) {
            return ['ok' => false, 'error' => 'Cet indicatif est déjà utilisé dans la communauté.'];
        }
        if ($sequenceId !== null && $sequenceId > 0) {
            $seq = $this->sequences->findById($tenantId, $sequenceId);
            if ($seq !== null && empty($seq['allow_manual_override'])) {
                return ['ok' => false, 'error' => 'La modification manuelle est désactivée pour cette séquence.'];
            }
        }

        return $this->applyCallsign($tenantId, $userId, $sequenceId, $callsign, $actorUserId, $reason, [
            'source' => 'manual',
        ]);
    }

    /**
     * @param array<string, mixed> $meta
     * @return array{ok: bool, callsign?: string, error?: string}
     */
    private function applyCallsign(
        int $tenantId,
        int $userId,
        ?int $sequenceId,
        string $callsign,
        ?int $actorUserId,
        string $reason,
        array $meta
    ): array {
        $user = $this->users->findById($userId, $tenantId);
        if ($user === null) {
            return ['ok' => false, 'error' => 'Membre introuvable.'];
        }
        $old = trim((string) ($user['callsign'] ?? ''));
        if ($old === $callsign) {
            return ['ok' => true, 'callsign' => $callsign];
        }

        $okUser = $this->users->update($userId, $tenantId, ['callsign' => $callsign]);
        $this->profiles->ensureRecord($userId);
        $this->profiles->update($userId, ['callsign' => $callsign]);
        if (!$okUser) {
            return ['ok' => false, 'error' => 'Échec de mise à jour du compte.'];
        }

        if ($this->sequences->schemaReady()) {
            $this->sequences->appendHistory(
                $tenantId,
                $userId,
                $sequenceId,
                $old !== '' ? $old : null,
                $callsign,
                $reason,
                $actorUserId,
                json_encode($meta, JSON_UNESCAPED_UNICODE) ?: null
            );
        }
        $this->careerEvents->record($tenantId, $userId, $old === '' ? 'CALLSIGN_ASSIGNED' : 'CALLSIGN_CHANGED', $actorUserId, [
            'old' => $old !== '' ? $old : null,
            'new' => $callsign,
            'reason' => $reason,
            'sequence_id' => $sequenceId,
        ] + $meta);

        return ['ok' => true, 'callsign' => $callsign];
    }

    private function applyPattern(string $pattern, string $prefix, string $suffix, int $number, int $padding): string
    {
        $out = $pattern;
        $out = str_replace(['{PREFIX}', '{prefix}'], $prefix, $out);
        $out = str_replace(['{SUFFIX}', '{suffix}'], $suffix, $out);
        if (preg_match('/\{NUMBER:(\d+)\}/i', $out, $m)) {
            $pad = (int) $m[1];
            $out = preg_replace('/\{NUMBER:\d+\}/i', str_pad((string) $number, $pad, '0', STR_PAD_LEFT), $out) ?? $out;
        } else {
            $num = $padding > 0 ? str_pad((string) $number, $padding, '0', STR_PAD_LEFT) : (string) $number;
            $out = str_replace(['{NUMBER}', '{number}'], $num, $out);
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed>|null $existing
     * @return array<string, mixed>|null
     */
    private function normalizeSequencePayload(array $data, ?array $existing = null): ?array
    {
        $name = trim((string) ($data['name'] ?? ($existing['name'] ?? '')));
        $code = trim((string) ($data['code'] ?? ($existing['code'] ?? '')));
        if ($name === '' || $code === '') {
            return null;
        }
        $code = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $code) ?? $code;
        $mode = strtoupper(trim((string) ($data['mode'] ?? ($existing['mode'] ?? 'PREFIX_NUMERIC'))));
        if (!in_array($mode, self::MODES, true)) {
            $mode = 'PREFIX_NUMERIC';
        }
        $policy = (string) ($data['unit_change_policy'] ?? ($existing['unit_change_policy'] ?? 'keep'));
        if (!in_array($policy, ['keep', 'regenerate', 'ask', 'none'], true)) {
            $policy = 'keep';
        }

        return [
            'name' => $name,
            'code' => $code,
            'mode' => $mode,
            'prefix' => trim((string) ($data['prefix'] ?? ($existing['prefix'] ?? ''))),
            'suffix' => trim((string) ($data['suffix'] ?? ($existing['suffix'] ?? ''))),
            'pattern' => trim((string) ($data['pattern'] ?? ($existing['pattern'] ?? '{PREFIX}-{NUMBER:02}'))) ?: '{PREFIX}-{NUMBER:02}',
            'start_number' => max(1, (int) ($data['start_number'] ?? ($existing['start_number'] ?? 1))),
            'current_number' => max(1, (int) ($data['current_number'] ?? ($existing['current_number'] ?? ($data['start_number'] ?? 1)))),
            'increment_by' => max(1, (int) ($data['increment_by'] ?? ($existing['increment_by'] ?? 1))),
            'padding' => max(0, min(8, (int) ($data['padding'] ?? ($existing['padding'] ?? 2)))),
            'reuse_released' => !empty($data['reuse_released'] ?? ($existing['reuse_released'] ?? false)),
            'allow_manual_override' => array_key_exists('allow_manual_override', $data)
                ? !empty($data['allow_manual_override'])
                : (bool) ($existing['allow_manual_override'] ?? true),
            'unit_change_policy' => $policy,
            'unit_id' => isset($data['unit_id']) ? (int) $data['unit_id'] : ($existing['unit_id'] ?? null),
            'is_default' => !empty($data['is_default'] ?? ($existing['is_default'] ?? false)),
            'is_active' => array_key_exists('is_active', $data)
                ? !empty($data['is_active'])
                : (bool) ($existing['is_active'] ?? true),
        ];
    }
}
