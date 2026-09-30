<?php

declare(strict_types=1);

namespace App\Services\Personnel\Pass;

use App\Repositories\PersonnelPassRepository;

final class PassService
{
    public function __construct(
        private PersonnelPassRepository $passes,
        private PassConditionEngine $engine,
        private PassFactsLoader $facts,
    ) {}

    /**
     * @return array{eligible: bool, logic: string, pass: ?array<string, mixed>, items: list<array{passed: bool, label: string, current: string, target: string, reason: string, type: string}>}
     */
    public function evaluatePassForUser(int $tenantId, int $passId, int $userId): array
    {
        $pass = $this->passes->find($tenantId, $passId);
        if ($pass === null || empty($pass['is_active'])) {
            return ['eligible' => true, 'logic' => 'all', 'pass' => null, 'items' => []];
        }
        $conditions = $this->passes->listConditions($tenantId, $passId);
        if ($conditions === []) {
            return [
                'eligible' => true,
                'logic' => (string) ($pass['logic'] ?? 'all'),
                'pass' => $pass,
                'items' => [],
            ];
        }

        $items = [];
        $logic = ((string) ($pass['logic'] ?? 'all')) === 'any' ? 'any' : 'all';
        foreach ($conditions as $condition) {
            $ctx = $this->facts->load($tenantId, $userId, $condition, $pass);
            $items[] = $this->engine->evaluateOne($condition, $ctx);
        }
        $eligible = $logic === 'any'
            ? (bool) array_filter($items, static fn (array $i): bool => !empty($i['passed']))
            : !array_filter($items, static fn (array $i): bool => empty($i['passed']));

        return [
            'eligible' => $eligible,
            'logic' => $logic,
            'pass' => $pass,
            'items' => $items,
        ];
    }

    /**
     * @param list<array<string, mixed>> $rawConditions
     * @return list<array<string, mixed>>
     */
    public function sanitizeConditions(array $rawConditions): array
    {
        $allowed = array_column(PassConditionEngine::catalog(), 'type');
        $avisAllowed = array_column(PassConditionEngine::avisKinds(), 'value');
        $bilanAllowed = array_column(PassConditionEngine::bilanKinds(), 'value');
        $out = [];
        foreach ($rawConditions as $c) {
            if (!is_array($c)) {
                continue;
            }
            $type = trim((string) ($c['condition_type'] ?? ''));
            if ($type === '' || !in_array($type, $allowed, true)) {
                continue;
            }
            $avis = trim((string) ($c['avis_kind'] ?? ''));
            if ($avis !== '' && !in_array($avis, $avisAllowed, true)) {
                $avis = PassConditionEngine::AVIS_CHEF_N1;
            }
            $bilan = trim((string) ($c['bilan_kind'] ?? ''));
            if ($bilan !== '' && !in_array($bilan, $bilanAllowed, true)) {
                $bilan = 'commandement';
            }
            $out[] = [
                'condition_type' => $type,
                'qualification_id' => (int) ($c['qualification_id'] ?? 0) ?: null,
                'training_module_id' => (int) ($c['training_module_id'] ?? 0) ?: null,
                'grade_id' => (int) ($c['grade_id'] ?? 0) ?: null,
                'threshold_value' => (float) ($c['threshold_value'] ?? 0),
                'window_days' => (int) ($c['window_days'] ?? 0) ?: null,
                'require_validity' => !empty($c['require_validity']) ? 1 : 0,
                'hour_category' => trim((string) ($c['hour_category'] ?? '')) ?: null,
                'session_kind' => trim((string) ($c['session_kind'] ?? '')) ?: null,
                'avis_kind' => $avis !== '' ? $avis : null,
                'bilan_kind' => $bilan !== '' ? $bilan : null,
            ];
        }

        return $out;
    }
}
