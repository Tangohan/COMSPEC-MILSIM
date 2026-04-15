<?php

declare(strict_types=1);

namespace App\Services\Platform;

/**
 * Résolution d'accès aux modules/version/features selon canal de déploiement
 * + communautés de test (transverses, indépendantes des tenants).
 */
final class ModuleReleaseAccessResolver
{
    /**
     * @param array<string, mixed> $module
     * @param array<string, array<string, mixed>> $releasesByChannel clé = code canal (DEV/TEST/…)
     * @param list<array<string, mixed>> $moduleRules module_access_rules
     * @param list<string> $communityCodes communautés de validation (codes) de l'utilisateur
     * @param list<int> $communityIds communautés de validation (IDs) de l'utilisateur
     * @param array<string, array{default_state: bool, rules: list<array<string, mixed>>}> $featureFlags
     * @param array<string, mixed> $context ex: ['target_channel' => 'PROD', 'user_id' => 42, 'override_channel' => 'TEST']
     *
     * @return array{
     *   allowed: bool,
     *   reason: string,
     *   channel: string,
     *   release: array<string, mixed>|null,
     *   feature_flags: array<string, bool>,
     *   matched_module_rule: array<string, mixed>|null,
     *   decision_trace: list<string>
     * }
     */
    public function resolve(
        array $module,
        array $releasesByChannel,
        array $moduleRules,
        array $communityCodes,
        array $featureFlags,
        array $context = []
    ): array {
        $trace = [];

        if (empty($module['is_active'])) {
            return $this->deny('module_inactive', 'NONE', null, [], null, ['Module inactif']);
        }
        $trace[] = 'Module actif';

        $targetChannel = $this->normalizeChannel((string) ($context['override_channel'] ?? $context['target_channel'] ?? 'PROD'));
        $trace[] = 'Canal ciblé=' . $targetChannel;

        $release = $releasesByChannel[$targetChannel] ?? null;
        if (!is_array($release)) {
            return $this->deny('release_not_found', $targetChannel, null, [], null, [...$trace, 'Aucune version publiée pour ce canal']);
        }
        $trace[] = 'Version résolue=' . (string) ($release['version'] ?? 'n/a');

        $communityIds = array_values(array_map('intval', (array) ($context['community_ids'] ?? [])));
        $moduleDecision = $this->resolveModuleVisibility($module, $release, $moduleRules, $communityCodes, $communityIds, $targetChannel);
        $trace = [...$trace, ...$moduleDecision['trace']];

        if (!$moduleDecision['allowed']) {
            return $this->deny(
                (string) $moduleDecision['reason'],
                $targetChannel,
                $release,
                [],
                $moduleDecision['matched_rule'],
                $trace
            );
        }

        $flags = $this->resolveFeatureFlags($featureFlags, $communityCodes, $communityIds, (int) ($context['user_id'] ?? 0));
        $trace[] = 'Feature flags calculés=' . count($flags);

        return [
            'allowed' => true,
            'reason' => 'granted',
            'channel' => $targetChannel,
            'release' => $release,
            'feature_flags' => $flags,
            'matched_module_rule' => $moduleDecision['matched_rule'],
            'decision_trace' => $trace,
        ];
    }

    /**
     * @param array<string, mixed> $module
     * @param array<string, mixed> $release
     * @param list<array<string, mixed>> $rules
     * @param list<string> $communityCodes
     * @param list<int> $communityIds
     * @return array{allowed: bool, reason: string, matched_rule: array<string, mixed>|null, trace: list<string>}
     */
    private function resolveModuleVisibility(
        array $module,
        array $release,
        array $rules,
        array $communityCodes,
        array $communityIds,
        string $targetChannel
    ): array
    {
        $trace = [];
        $sorted = $this->sortRulesByPriority($rules);
        $releaseId = (int) ($release['module_version_id'] ?? $release['id'] ?? 0);

        foreach ($sorted as $rule) {
            if (empty($rule['is_active'])) {
                continue;
            }
            if (!$this->ruleMatchesScope($rule, $targetChannel, $releaseId)) {
                continue;
            }

            $type = (string) ($rule['rule_type'] ?? '');
            $communityCode = strtoupper(trim((string) ($rule['community_code'] ?? '')));
            $communityId = (int) ($rule['community_id'] ?? 0);

            if ($type === 'deny_all') {
                $trace[] = 'Règle deny_all prioritaire';
                return ['allowed' => false, 'reason' => 'module_denied_all', 'matched_rule' => $rule, 'trace' => $trace];
            }

            if ($type === 'public') {
                $trace[] = 'Règle public prioritaire';
                return ['allowed' => true, 'reason' => 'module_public', 'matched_rule' => $rule, 'trace' => $trace];
            }

            if ($type === 'allow_community') {
                if ($this->matchesCommunity($communityCode, $communityId, $communityCodes, $communityIds)) {
                    $trace[] = 'Règle allow_community';
                    return ['allowed' => true, 'reason' => 'module_allowed_community', 'matched_rule' => $rule, 'trace' => $trace];
                }
                continue;
            }

            if ($type === 'deny_community') {
                if ($this->matchesCommunity($communityCode, $communityId, $communityCodes, $communityIds)) {
                    $trace[] = 'Règle deny_community';
                    return ['allowed' => false, 'reason' => 'module_denied_community', 'matched_rule' => $rule, 'trace' => $trace];
                }
            }
        }

        if (!empty($module['is_public'])) {
            $trace[] = 'Fallback module public';
            return ['allowed' => true, 'reason' => 'module_public_default', 'matched_rule' => null, 'trace' => $trace];
        }

        $trace[] = 'Fallback module privé sans règle allow';
        return ['allowed' => false, 'reason' => 'module_private_default', 'matched_rule' => null, 'trace' => $trace];
    }

    /**
     * @param array<string, array{default_state: bool, rules: list<array<string, mixed>>}> $featureFlags
     * @param list<string> $communityCodes
     * @param list<int> $communityIds
     * @return array<string, bool>
     */
    private function resolveFeatureFlags(array $featureFlags, array $communityCodes, array $communityIds, int $userId): array
    {
        $resolved = [];

        foreach ($featureFlags as $flagCode => $flag) {
            $state = (bool) ($flag['default_state'] ?? false);
            $rules = $this->sortRulesByPriority((array) ($flag['rules'] ?? []));

            foreach ($rules as $rule) {
                if (empty($rule['is_active'])) {
                    continue;
                }

                $type = (string) ($rule['rule_type'] ?? '');
                $ruleState = (bool) ($rule['state'] ?? false);
                $ruleCommunityCode = strtoupper(trim((string) ($rule['community_code'] ?? '')));
                $ruleCommunityId = (int) ($rule['community_id'] ?? 0);
                $ruleUserId = (int) ($rule['user_id'] ?? 0);

                if ($type === 'allow_community' && $this->matchesCommunity($ruleCommunityCode, $ruleCommunityId, $communityCodes, $communityIds)) {
                    $state = $ruleState;
                    break;
                }
                if ($type === 'deny_community' && $this->matchesCommunity($ruleCommunityCode, $ruleCommunityId, $communityCodes, $communityIds)) {
                    $state = $ruleState;
                    break;
                }
                if ($type === 'allow_user' && $ruleUserId > 0 && $ruleUserId === $userId) {
                    $state = $ruleState;
                    break;
                }
                if ($type === 'deny_user' && $ruleUserId > 0 && $ruleUserId === $userId) {
                    $state = $ruleState;
                    break;
                }
            }

            $resolved[$flagCode] = $state;
        }

        return $resolved;
    }

    /** @param list<array<string, mixed>> $rules @return list<array<string, mixed>> */
    private function sortRulesByPriority(array $rules): array
    {
        usort($rules, static function (array $a, array $b): int {
            $pa = (int) ($a['priority'] ?? 0);
            $pb = (int) ($b['priority'] ?? 0);
            if ($pa !== $pb) {
                return $pb <=> $pa;
            }
            $ia = (int) ($a['id'] ?? 0);
            $ib = (int) ($b['id'] ?? 0);
            return $ib <=> $ia;
        });

        return $rules;
    }

    /** @param array<string, mixed> $rule */
    private function ruleMatchesScope(array $rule, string $targetChannel, int $releaseId): bool
    {
        $ruleChannel = $this->normalizeChannel((string) ($rule['environment_channel_code'] ?? $rule['environment_channel'] ?? ''));
        if ($ruleChannel !== '' && $ruleChannel !== $targetChannel) {
            return false;
        }

        if (!empty($rule['environment_channel_id']) && !empty($rule['channel_map']) && is_array($rule['channel_map'])) {
            $channelMap = $rule['channel_map'];
            $ruleChannelId = (int) $rule['environment_channel_id'];
            $expectedChannel = strtoupper(trim((string) ($channelMap[$ruleChannelId] ?? '')));
            if ($expectedChannel !== '' && $expectedChannel !== $targetChannel) {
                return false;
            }
        }

        $ruleVersionId = (int) ($rule['applies_to_version_id'] ?? 0);
        if ($ruleVersionId > 0 && $ruleVersionId !== $releaseId) {
            return false;
        }

        return true;
    }

    private function normalizeChannel(string $channel): string
    {
        $normalized = strtoupper(trim($channel));
        if ($normalized === '') {
            return 'PROD';
        }

        return $normalized;
    }

    /**
     * @param list<string> $communityCodes
     * @param list<int> $communityIds
     */
    private function matchesCommunity(string $ruleCode, int $ruleId, array $communityCodes, array $communityIds): bool
    {
        if ($ruleCode !== '' && in_array($ruleCode, $communityCodes, true)) {
            return true;
        }
        if ($ruleId > 0 && in_array($ruleId, $communityIds, true)) {
            return true;
        }

        return false;
    }

    /**
     * @param array<string, mixed>|null $release
     * @param array<string, bool> $flags
     * @param array<string, mixed>|null $matchedRule
     * @param list<string> $trace
     * @return array{allowed: bool, reason: string, channel: string, release: array<string, mixed>|null, feature_flags: array<string, bool>, matched_module_rule: array<string, mixed>|null, decision_trace: list<string>}
     */
    private function deny(
        string $reason,
        string $channel,
        ?array $release,
        array $flags,
        ?array $matchedRule,
        array $trace
    ): array {
        return [
            'allowed' => false,
            'reason' => $reason,
            'channel' => $channel,
            'release' => $release,
            'feature_flags' => $flags,
            'matched_module_rule' => $matchedRule,
            'decision_trace' => $trace,
        ];
    }
}
