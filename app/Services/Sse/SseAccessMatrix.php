<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Support\SseAccessCatalog as C;

/**
 * Lecture des droits d'accès SSE pour l'écran « Droits d'accès » : grille sections × niveaux, règles d'une section,
 * droits effectifs d'un membre (avec l'origine de chaque droit), « qui a accès » et simulation d'un changement.
 *
 * Ne décide rien : chaque réponse vient de SseAccessPolicy::decide(), évaluée avec la porte « droit d'entrer » (la
 * confidentialité acceptée est propre à chaque session et n'est pas connue d'ici). Aucun contenu SSE n'est lu :
 * l'écran parle de droits, jamais d'identités ni de couvertures.
 */
class SseAccessMatrix
{
    /** Membres évalués au plus par « qui a accès » et par simulation. */
    public const MEMBER_CAP = 300;

    public function __construct(
        private ?SseAccessPolicy $policy = null,
        private ?SseAccessDirectory $directory = null,
    ) {
    }

    private function policy(): SseAccessPolicy
    {
        return $this->policy ??= SseAccessPolicy::forSession();
    }

    private function directory(): SseAccessDirectory
    {
        return $this->directory ??= new SseAccessDirectory();
    }

    /** @return array<string, mixed> */
    private function ctx(int $tenantId, array $extra = []): array
    {
        return ['tenant_id' => $tenantId, 'door_mode' => SseAccessPolicy::DOOR_ENTRY, 'guest' => false] + $extra;
    }

    /**
     * Grille : pour chaque section et chaque niveau, la garde d'origine et le nombre de règles actives (accord, refus).
     *
     * @return list<array{key: string, label: string, hint: string, cells: array<string, array{applies: bool, profile: string, profile_label: string, allow: int, deny: int, roles: int, units: int, users: int}>}>
     */
    public function grid(int $tenantId): array
    {
        $live = $this->policy()->rules()->liveRules($tenantId);
        $now = $this->policy()->now();
        $count = [];
        $byType = [];
        foreach ($live as $r) {
            if (!self::active($r, $now)) {
                continue;
            }
            $k = $r['section'] . '|' . $r['level'] . '|' . $r['effect'];
            $count[$k] = ($count[$k] ?? 0) + 1;
            $kind = in_array($r['subject_type'], [C::SUBJECT_UNIT, C::SUBJECT_UNIT_TREE], true) ? 'unit' : (string) $r['subject_type'];
            $kt = $r['section'] . '|' . $r['level'] . '|' . $kind;
            $byType[$kt] = ($byType[$kt] ?? 0) + 1;
        }
        $out = [];
        foreach (C::SECTIONS as $key => $def) {
            $cells = [];
            foreach (C::levelKeys() as $lv) {
                $profile = C::legacyProfile($key, $lv);
                $cells[$lv] = [
                    'applies' => $profile !== 'none',
                    'profile' => $profile,
                    'profile_label' => C::PROFILES[$profile]['label'],
                    'allow' => $count[$key . '|' . $lv . '|' . C::EFFECT_ALLOW] ?? 0,
                    'deny' => $count[$key . '|' . $lv . '|' . C::EFFECT_DENY] ?? 0,
                    'roles' => $byType[$key . '|' . $lv . '|' . C::SUBJECT_ROLE] ?? 0,
                    'units' => $byType[$key . '|' . $lv . '|unit'] ?? 0,
                    'users' => $byType[$key . '|' . $lv . '|' . C::SUBJECT_USER] ?? 0,
                ];
            }
            $out[] = ['key' => $key, 'label' => $def['label'], 'hint' => $def['hint'], 'cells' => $cells];
        }

        return $out;
    }

    /**
     * Règles non retirées d'une section, rangées par niveau, avec le libellé du bénéficiaire et l'état (en cours, à venir, échue).
     *
     * @return array<string, list<array<string, mixed>>>
     */
    public function rulesOfSection(int $tenantId, string $section): array
    {
        $out = [];
        if (!C::hasSection($section)) {
            return $out;
        }
        $now = $this->policy()->now();
        $subjects = $this->policy()->subjects();
        foreach ($this->policy()->rules()->liveRules($tenantId) as $r) {
            if ($r['section'] !== $section) {
                continue;
            }
            $label = $subjects->label($tenantId, (string) $r['subject_type'], (int) $r['subject_id']);
            // Un membre masqué ou anonymisé par l'annuaire n'est jamais nommé ici.
            if ($r['subject_type'] === C::SUBJECT_USER && $label !== null && $this->directory()->member($tenantId, (int) $r['subject_id']) === null) {
                $label = 'Membre protégé par l’annuaire';
            }
            $r['subject_label'] = $label ?? 'Bénéficiaire supprimé';
            $r['subject_type_label'] = C::SUBJECT_TYPES[$r['subject_type']] ?? $r['subject_type'];
            $r['state'] = self::state($r, $now);
            $out[(string) $r['level']][] = $r;
        }

        return $out;
    }

    /**
     * « Que peut faire ce membre ? » : pour chaque section et niveau, la réponse et son origine.
     *
     * @return array{entry: bool, sections: list<array{key: string, label: string, cells: array<string, array{applies: bool, allowed: bool, source: string, reason: string, rule_ids: list<int>}>}>}
     */
    public function effectiveForMember(int $tenantId, int $userId): array
    {
        $entry = $this->policy()->hasEntryRight($userId, $tenantId);
        $sections = [];
        foreach (C::SECTIONS as $key => $def) {
            $cells = [];
            foreach (C::levelKeys() as $lv) {
                if (!C::applies($key, $lv)) {
                    $cells[$lv] = ['applies' => false, 'allowed' => false, 'source' => SseAccessDecision::INVALID, 'reason' => 'Sans objet pour cette section.', 'rule_ids' => []];
                    continue;
                }
                $d = $this->policy()->decide($userId, $key, $lv, $this->ctx($tenantId));
                $allowed = $d->allowed && $entry;
                $cells[$lv] = [
                    'applies' => true,
                    'allowed' => $allowed,
                    'source' => $d->source,
                    'reason' => $this->reason($d, $key, $lv, $entry),
                    'rule_ids' => $d->ruleIds,
                ];
            }
            $sections[] = ['key' => $key, 'label' => $def['label'], 'cells' => $cells];
        }

        return ['entry' => $entry, 'sections' => $sections];
    }

    /** Texte « d'où vient ce droit ». */
    private function reason(SseAccessDecision $d, string $section, string $level, bool $entry): string
    {
        if (!$entry) {
            return 'Pas de droit d’entrer au bureau SSE.';
        }
        $profile = C::PROFILES[C::legacyProfile($section, $level)]['label'];
        $ids = implode(', ', array_map(static fn (int $i): string => '#' . $i, $d->ruleIds));

        return match ($d->source) {
            SseAccessDecision::LEGACY => 'Droit d’origine : ' . $profile . '.',
            SseAccessDecision::ALLOW_RULE => $ids !== '' ? 'Accordé par la règle ' . $ids . '.' : 'Accordé par une règle.',
            SseAccessDecision::DENY_RULE => $d->legacyAllowed
                ? ($ids !== '' ? 'Refusé par la règle ' . $ids . ' : le refus l’emporte sur le droit d’origine.' : 'Refusé par une règle : le refus l’emporte sur le droit d’origine.')
                : ($ids !== '' ? 'Refusé par la règle ' . $ids . ' : le refus l’emporte.' : 'Refusé par une règle : le refus l’emporte.'),
            SseAccessDecision::DOOR_CLOSED => 'Une règle l’accorderait, mais sans la porte du bureau elle n’ouvre rien.',
            SseAccessDecision::PLATFORM => $d->allowed ? 'Administrateur de la plateforme : jamais soumis aux règles.' : 'Administrateur de la plateforme.',
            default => 'Aucun droit d’origine ni règle : refusé par défaut. Droit d’origine : ' . $profile . '.',
        };
    }

    /**
     * « Qui a accès à cette section ? » : membres nommés de l'annuaire, et nombre de membres anonymisés (jamais nommés).
     *
     * @return array{members: list<array{user_id: int, label: string, unit: string, source: string, reason: string}>, anonymized: int, truncated: bool, evaluated: int}
     */
    public function whoHas(int $tenantId, string $section, string $level): array
    {
        $empty = ['members' => [], 'anonymized' => 0, 'truncated' => false, 'evaluated' => 0];
        if (!C::hasSection($section) || !C::hasLevel($level) || !C::applies($section, $level)) {
            return $empty;
        }
        $dir = $this->directory()->visible($tenantId, '', self::MEMBER_CAP);
        $members = [];
        foreach ($dir['named'] as $m) {
            if (!$this->policy()->hasEntryRight($m['user_id'], $tenantId)) {
                continue;
            }
            $d = $this->policy()->decide($m['user_id'], $section, $level, $this->ctx($tenantId));
            if ($d->allowed) {
                $members[] = $m + ['source' => $d->source, 'reason' => $this->reason($d, $section, $level, true)];
            }
        }
        $anonymized = 0;
        foreach ($dir['anonymized_ids'] as $uid) {
            if ($this->policy()->hasEntryRight($uid, $tenantId) && $this->policy()->allows($uid, $section, $level, $this->ctx($tenantId))) {
                ++$anonymized;
            }
        }

        return [
            'members' => $members,
            'anonymized' => $anonymized,
            'truncated' => count($dir['named']) + $dir['anonymized'] >= self::MEMBER_CAP,
            'evaluated' => count($dir['named']) + $dir['anonymized'],
        ];
    }

    /**
     * Simulation : ce que changeraient des règles à créer (ou à retirer) pour les membres qu'elles concernent.
     *
     * @param list<array<string, mixed>> $planned règles qui seraient créées
     * @param list<int> $dropIds règles qui seraient retirées
     * @return array{gained: int, lost: int, concerned: int, sample_gained: list<string>, sample_lost: list<string>, protected: int, truncated: bool}
     */
    public function simulate(int $tenantId, array $planned, array $dropIds = []): array
    {
        $res = ['gained' => 0, 'lost' => 0, 'concerned' => 0, 'sample_gained' => [], 'sample_lost' => [], 'protected' => 0, 'truncated' => false];
        $subjects = $this->policy()->subjects();
        $targets = [];
        $cells = [];
        foreach ($planned as $r) {
            foreach ($subjects->membersOf($tenantId, (string) $r['subject_type'], (int) $r['subject_id'], self::MEMBER_CAP + 1) as $uid) {
                $targets[$uid] = true;
            }
            foreach (C::deniedBy((string) $r['level']) as $lv) {
                if (C::applies((string) $r['section'], $lv)) {
                    $cells[$r['section'] . '|' . $lv] = [(string) $r['section'], $lv];
                }
            }
            if ($r['effect'] === C::EFFECT_ALLOW && $r['level'] === C::LEVEL_EXISTS) {
                $cells[$r['section'] . '|exists'] = [(string) $r['section'], 'exists'];
            }
        }
        foreach ($dropIds as $id) {
            $old = $this->policy()->rules()->find($tenantId, (int) $id);
            if ($old === null) {
                continue;
            }
            foreach ($subjects->membersOf($tenantId, (string) $old['subject_type'], (int) $old['subject_id'], self::MEMBER_CAP + 1) as $uid) {
                $targets[$uid] = true;
            }
            foreach (C::deniedBy((string) $old['level']) as $lv) {
                if (C::applies((string) $old['section'], $lv)) {
                    $cells[$old['section'] . '|' . $lv] = [(string) $old['section'], $lv];
                }
            }
        }
        $ids = array_map('intval', array_keys($targets));
        if (count($ids) > self::MEMBER_CAP) {
            $res['truncated'] = true;
            $ids = array_slice($ids, 0, self::MEMBER_CAP);
        }
        $visible = [];
        foreach ($this->directory()->visible($tenantId, '', self::MEMBER_CAP)['named'] as $m) {
            $visible[$m['user_id']] = $m['label'];
        }
        foreach ($ids as $uid) {
            if (!$this->policy()->hasEntryRight($uid, $tenantId)) {
                continue;
            }
            ++$res['concerned'];
            $gain = false;
            $loss = false;
            foreach ($cells as [$section, $lv]) {
                $before = $this->policy()->allows($uid, $section, $lv, $this->ctx($tenantId));
                $after = $this->policy()->allows($uid, $section, $lv, $this->ctx($tenantId, ['extra_rules' => $planned, 'drop_rules' => $dropIds]));
                $gain = $gain || (!$before && $after);
                $loss = $loss || ($before && !$after);
            }
            $name = $visible[$uid] ?? null;
            if ($gain) {
                ++$res['gained'];
                if ($name !== null && count($res['sample_gained']) < 12) {
                    $res['sample_gained'][] = $name;
                } elseif ($name === null) {
                    ++$res['protected'];
                }
            }
            if ($loss) {
                ++$res['lost'];
                if ($name !== null && count($res['sample_lost']) < 12) {
                    $res['sample_lost'][] = $name;
                } elseif ($name === null) {
                    ++$res['protected'];
                }
            }
        }

        return $res;
    }

    /** @param array<string, mixed> $rule */
    public static function active(array $rule, string $now): bool
    {
        return self::state($rule, $now) === 'active';
    }

    /**
     * @param array<string, mixed> $rule
     * @return string « active », « scheduled » (pas commencée) ou « expired » (échue)
     */
    public static function state(array $rule, string $now): string
    {
        $start = (string) ($rule['starts_at'] ?? '');
        $end = (string) ($rule['ends_at'] ?? '');
        if ($start !== '' && $start > $now) {
            return 'scheduled';
        }
        if ($end !== '' && $end <= $now) {
            return 'expired';
        }

        return 'active';
    }
}
