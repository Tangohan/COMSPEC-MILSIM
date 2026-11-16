<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Core\Database;
use App\Repositories\SseCaseRepository;
use App\Support\SseAccessCatalog as C;
use App\Support\SseSheetCatalog;

/**
 * Centre de gestion des accès au renseignement (back-office › Accès renseignement) : lecture seule, rien n'est décidé ni
 * écrit ici. Chaque réponse vient des services existants : SseAccessPolicy (droit d'entrer, droits par section),
 * SseMemberClearance (habilitation et son origine), l'annuaire SseAccessDirectory (un membre masqué ou anonymisé n'apparaît
 * jamais nommé), le journal des codes et SseSheetVisibility (niveau de lecture des rubriques de chaque modèle de document).
 *
 * Les écritures (habilitation, dérogations, retraits) passent par SseAccessMemberRights, depuis le contrôleur.
 */
final class SseAccessHub
{
    /** Filtres de la liste des personnels (?etat=…). */
    public const STATES = ['sans-acces', 'restreint', 'par-code', 'echeance', 'echue', 'sans-habilitation'];

    /** Échéance proche : sous sept jours. */
    public const SOON_DAYS = 7;

    /**
     * @param \Closure(int): list<array<string, mixed>>|null $entries entrées au portail de la communauté (SseAccessCodeRepository::listMemberEntries)
     * @param \Closure(int): list<array<string, mixed>>|null $roles niveaux d'accès qui portent un droit SSE : id, name, slugs, members
     */
    public function __construct(
        private ?SseAccessPolicy $policy = null,
        private ?SseAccessDirectory $directory = null,
        private ?SseMemberClearance $clearance = null,
        private ?\Closure $entries = null,
        private ?\Closure $roles = null,
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

    private function clearance(): SseMemberClearance
    {
        return $this->clearance ??= new SseMemberClearance();
    }

    /** @return array<string, mixed> */
    private function ctx(int $tenantId): array
    {
        return ['tenant_id' => $tenantId, 'guest' => false, 'door_mode' => SseAccessPolicy::DOOR_ENTRY];
    }

    /**
     * Membres de la communauté que l'annuaire montre nommément à la personne, avec leur accès au renseignement.
     *
     * @param list<array<string, mixed>> $codes codes de la communauté (SseAccessCodeRepository::listActiveForTenant)
     * @return array{rows: list<array<string, mixed>>, anonymized: int, sections: int}
     */
    public function people(int $tenantId, int $actorId, string $actorCeiling, array $codes = []): array
    {
        if ($tenantId < 1 || $actorId < 1) {
            return ['rows' => [], 'anonymized' => 0, 'sections' => 0];
        }
        $dir = $this->directory()->visible($tenantId);
        $now = $this->policy()->now();
        $soon = date('Y-m-d H:i:s', (int) strtotime($now) + self::SOON_DAYS * 86400);
        $ctx = $this->ctx($tenantId);

        // Sections que la personne voit elle-même : le résumé des droits n'en dit pas plus qu'elle n'en sait.
        $sections = [];
        foreach (array_keys(C::SECTIONS) as $key) {
            if ($this->policy()->allows($actorId, $key, C::LEVEL_EXISTS, $ctx)) {
                $sections[] = $key;
            }
        }

        $activeCodes = [];
        foreach ($codes as $c) {
            if (!empty($c['active'])) {
                $activeCodes[(int) ($c['id'] ?? 0)] = true;
            }
        }
        $lastEntry = [];
        $byCode = [];
        foreach ($this->entriesOf($tenantId) as $e) {
            $uid = (int) ($e['user_id'] ?? 0);
            if ($uid < 1) {
                continue;
            }
            if (!isset($lastEntry[$uid])) {
                $lastEntry[$uid] = (string) ($e['created_at'] ?? '');
            }
            if (($e['event_type'] ?? '') === 'redeem' && isset($activeCodes[(int) ($e['code_id'] ?? 0)])) {
                $byCode[$uid] = true;
            }
        }

        $own = [];
        foreach ($this->policy()->rules()->liveRules($tenantId) as $r) {
            if ($r['subject_type'] === C::SUBJECT_USER && in_array((string) $r['section'], $sections, true)) {
                $own[(int) $r['subject_id']][] = $r;
            }
        }

        $rows = [];
        foreach ($dir['named'] as $m) {
            $uid = (int) $m['user_id'];
            $entry = $this->policy()->hasEntryRight($uid, $tenantId);
            $command = $this->policy()->legacyAllows($uid, $tenantId, 'grant', false, SseAccessPolicy::DOOR_ENTRY);
            $code = isset($byCode[$uid]);
            $why = $command ? 'command' : ($entry ? 'role' : ($code ? 'code' : 'none'));

            $view = $this->clearance()->view($tenantId, $uid);

            $rights = ['voir' => 0, 'ecrire' => 0, 'gerer' => 0];
            if ($entry) {
                foreach ($sections as $key) {
                    foreach (C::SIMPLE_ACCESS as $access => $def) {
                        if (C::applies($key, $def['shown']) && $this->policy()->allows($uid, $key, $def['shown'], $ctx)) {
                            $rights[$access]++;
                        }
                    }
                }
            }

            $denied = false;
            $soonCount = 0;
            $expired = 0;
            $nextEnd = null;
            foreach ($own[$uid] ?? [] as $r) {
                $state = SseAccessMatrix::state($r, $now);
                if ($state === 'expired') {
                    $expired++;
                    continue;
                }
                if ($state === 'active' && $r['effect'] === C::EFFECT_DENY) {
                    $denied = true;
                }
                $end = (string) ($r['ends_at'] ?? '');
                if ($end !== '' && $state === 'active') {
                    if ($end <= $soon) {
                        $soonCount++;
                    }
                    if ($nextEnd === null || $end < $nextEnd) {
                        $nextEnd = $end;
                    }
                }
            }

            $state = !$entry && !$code ? 'none' : ($denied ? 'restricted' : 'active');
            $flags = [];
            if ($state === 'none') {
                $flags[] = 'sans-acces';
            }
            if ($denied) {
                $flags[] = 'restreint';
            }
            if ($code) {
                $flags[] = 'par-code';
            }
            if ($soonCount > 0) {
                $flags[] = 'echeance';
            }
            if ($expired > 0) {
                $flags[] = 'echue';
            }
            if ($view['level'] === SseCaseRepository::CLASS_INTERNAL) {
                $flags[] = 'sans-habilitation';
            }

            $rows[] = [
                'user_id' => $uid,
                'label' => (string) $m['label'],
                'unit' => (string) ($m['unit'] ?? ''),
                'self' => $uid === $actorId,
                'entry' => $entry,
                'why' => $why,
                'by_code' => $code,
                'level' => (string) $view['level'],
                'level_source' => (string) $view['source'],
                'profile_level' => $view['profile_level'],
                'may_change' => $uid !== $actorId && $this->clearance()->mayChange($uid, $actorCeiling),
                'rights' => $rights,
                'rules' => count($own[$uid] ?? []) - $expired,
                'expired' => $expired,
                'soon' => $soonCount,
                'next_end' => $nextEnd,
                'last_entry' => $lastEntry[$uid] ?? null,
                'state' => $state,
                'flags' => $flags,
            ];
        }
        usort($rows, static fn (array $a, array $b): int => [$a['unit'] === '', $a['unit'], $a['label']] <=> [$b['unit'] === '', $b['unit'], $b['label']]);

        return ['rows' => $rows, 'anonymized' => (int) $dir['anonymized'], 'sections' => count($sections)];
    }

    /** Une section existe-t-elle pour la personne (elle ne règle et ne voit que celles-là) ? */
    public function mayView(int $tenantId, int $actorId, string $section): bool
    {
        return C::hasSection($section) && $this->policy()->allows($actorId, $section, C::LEVEL_EXISTS, $this->ctx($tenantId));
    }

    /** @return list<array{key: string, label: string, sealed: bool}> sections que la personne voit */
    public function sections(int $tenantId, int $actorId): array
    {
        $out = [];
        foreach (C::SECTIONS as $key => $def) {
            if ($this->mayView($tenantId, $actorId, $key)) {
                $out[] = ['key' => $key, 'label' => $def['label'], 'sealed' => !empty($def['sealed'])];
            }
        }

        return $out;
    }

    /** « Qui peut quoi » de l'onglet SSE (SseAccessMatrix::board), limité aux sections que la personne voit. @return list<array<string, mixed>> */
    public function board(int $tenantId, int $actorId): array
    {
        return (new SseAccessMatrix($this->policy(), $this->directory()))->board($tenantId, fn (string $key): bool => $this->mayView($tenantId, $actorId, $key));
    }

    /** Historique des dérogations individuelles sur les sections que la personne voit. @return list<array<string, mixed>> */
    public function memberRuleHistory(int $tenantId, int $actorId, int $limit = 120): array
    {
        return array_values(array_filter(
            $this->policy()->rules()->history($tenantId, $limit),
            fn (array $h): bool => $h['subject_type'] === C::SUBJECT_USER && $this->mayView($tenantId, $actorId, (string) $h['section'])
        ));
    }

    /**
     * Filtre la liste : unité, niveau d'habilitation, état (STATES) et recherche (nom, unité).
     *
     * @param list<array<string, mixed>> $rows
     * @param array{unite?: string, niveau?: string, etat?: string, q?: string} $f
     * @return list<array<string, mixed>>
     */
    public static function filter(array $rows, array $f): array
    {
        $unit = trim((string) ($f['unite'] ?? ''));
        $level = (string) ($f['niveau'] ?? '');
        $state = (string) ($f['etat'] ?? '');
        $q = mb_strtolower(trim((string) ($f['q'] ?? '')));

        return array_values(array_filter($rows, static function (array $r) use ($unit, $level, $state, $q): bool {
            if ($unit !== '' && $r['unit'] !== $unit) {
                return false;
            }
            if ($level !== '' && isset(SseRedactionService::LEVELS[$level]) && $r['level'] !== $level) {
                return false;
            }
            if ($state !== '' && in_array($state, self::STATES, true) && !in_array($state, $r['flags'], true)) {
                return false;
            }

            return $q === '' || str_contains(mb_strtolower($r['label'] . ' ' . $r['unit']), $q);
        }));
    }

    /**
     * Chiffres clés et alertes de la vue d'ensemble.
     *
     * @param list<array<string, mixed>>|null $people null : la personne ne voit pas la liste des personnels
     * @param list<array<string, mixed>> $codes
     * @param list<array<string, mixed>> $log journal des codes (SseAccessCodeRepository::listLogForTenant)
     * @return array<string, mixed>
     */
    public static function overview(?array $people, array $codes, array $log, int $nowTs): array
    {
        $levels = array_fill_keys(array_keys(SseRedactionService::LEVELS), 0);
        $with = 0;
        $without = 0;
        $restricted = 0;
        $soon = 0;
        $soonMembers = 0;
        $expired = 0;
        $codeNoClearance = 0;
        foreach ($people ?? [] as $p) {
            $levels[$p['level']] = ($levels[$p['level']] ?? 0) + 1;
            if ($p['state'] === 'none') {
                $without++;
            } else {
                $with++;
            }
            if ($p['state'] === 'restricted') {
                $restricted++;
            }
            $soon += (int) $p['soon'];
            $soonMembers += (int) $p['soon'] > 0 ? 1 : 0;
            $expired += (int) $p['expired'] > 0 ? 1 : 0;
            if ($p['by_code'] && $p['level'] === SseCaseRepository::CLASS_INTERNAL) {
                $codeNoClearance++;
            }
        }
        $valid = 0;
        $validSoon = 0;
        $unused = 0;
        foreach ($codes as $c) {
            if (empty($c['active'])) {
                continue;
            }
            $valid++;
            $exp = strtotime((string) ($c['expires_at'] ?? '') . ' UTC');
            if ($exp !== false && $exp - $nowTs < self::SOON_DAYS * 86400) {
                $validSoon++;
            }
            if ((int) ($c['uses_count'] ?? 0) === 0) {
                $unused++;
            }
        }
        $recent = 0;
        foreach ($log as $row) {
            $ts = (int) ($row['ts'] ?? 0);
            if ($ts > 0 && $nowTs - $ts < self::SOON_DAYS * 86400) {
                $recent++;
            }
        }

        $alerts = [];
        if ($people !== null) {
            if ($soonMembers > 0) {
                $alerts[] = ['key' => 'echeance', 'tone' => 'warn', 'n' => $soonMembers, 'tab' => 'personnels'];
            }
            if ($codeNoClearance > 0) {
                $alerts[] = ['key' => 'code-sans-habilitation', 'tone' => 'danger', 'n' => $codeNoClearance, 'tab' => 'personnels'];
            }
            if ($restricted > 0) {
                $alerts[] = ['key' => 'restreint', 'tone' => 'info', 'n' => $restricted, 'tab' => 'personnels'];
            }
            if ($expired > 0) {
                $alerts[] = ['key' => 'echue', 'tone' => 'info', 'n' => $expired, 'tab' => 'personnels'];
            }
        }
        if ($unused > 0) {
            $alerts[] = ['key' => 'code-inutilise', 'tone' => 'info', 'n' => $unused, 'tab' => 'codes'];
        }

        return [
            'members' => $people !== null ? count($people) : null,
            'with' => $with,
            'without' => $without,
            'restricted' => $restricted,
            'levels' => $levels,
            'codes_valid' => $valid,
            'codes_soon' => $validSoon,
            'rules_soon' => $soon,
            'members_soon' => $soonMembers,
            'recent' => $recent,
            'alerts' => $alerts,
        ];
    }

    /**
     * Modèles de document × catégories : niveau à partir duquel chaque rubrique se lit en clair (réglage de la communauté
     * par modèle, PR « Visibilité par niveau » ; niveaux livrés à défaut) et droits nécessaires pour l'émettre.
     *
     * @return list<array{model: string, label: string, hint: string, levels: array<string, string>, needs: list<array{section: string, level: string}>}>
     */
    public static function documents(int $tenantId, ?SseSheetVisibility $visibility = null, string $lang = 'fr'): array
    {
        $visibility ??= new SseSheetVisibility();
        $i = $lang === 'en' ? 1 : 0;
        $out = [];
        foreach (SseSheetCatalog::MODELS as $model => $def) {
            try {
                $levels = $tenantId > 0 ? $visibility->forModel($tenantId, $model) : SseSheetVisibility::defaults();
            } catch (\Throwable) {
                $levels = SseSheetVisibility::defaults();
            }
            $needs = [];
            foreach ($def['needs'] as [$section, $level]) {
                $needs[] = ['section' => C::sectionLabel($section), 'level' => C::levelLabel($level)];
            }
            $out[] = ['model' => $model, 'label' => $def['label'][$i], 'hint' => $def['hint'][$i], 'levels' => $levels, 'needs' => $needs];
        }

        return $out;
    }

    /**
     * Niveaux d'accès (rôles) de la communauté qui portent un droit SSE, avec l'habilitation qu'ils reportent.
     *
     * @return list<array{id: int, name: string, level: string, slugs: list<string>, members: int}>
     */
    public function clearanceRoles(int $tenantId): array
    {
        if ($tenantId < 1) {
            return [];
        }
        try {
            $rows = $this->roles !== null ? ($this->roles)($tenantId) : self::readRoles($tenantId);
        } catch (\Throwable) {
            return [];
        }
        $out = [];
        foreach ($rows as $r) {
            $slugs = array_values(array_map('strval', (array) ($r['slugs'] ?? [])));
            $level = SseClearanceService::resolve(static fn (string $s): bool => in_array($s, $slugs, true), null)['level'];
            $out[] = ['id' => (int) $r['id'], 'name' => (string) $r['name'], 'level' => $level, 'slugs' => $slugs, 'members' => (int) ($r['members'] ?? 0)];
        }
        usort($out, static fn (array $a, array $b): int => [-SseRedactionService::levelRank($a['level']), $a['name']] <=> [-SseRedactionService::levelRank($b['level']), $b['name']]);

        return $out;
    }

    /** Droits SSE qui ouvrent l'entrée ou reportent une habilitation (SseClearanceService::resolve, SseAccessPolicy::hasEntryRight). */
    public const ROLE_SLUGS = [
        'atak.sse.clearance.encadrement', 'atak.sse.clearance.confidentiel', 'atak.sse.clearance.tres_restreint',
        'atak.sse.grant', 'atak.sse.case.manage', 'atak.sse.access', 'atak.sse.cases',
    ];

    /** @return list<array{id: int, name: string, slugs: list<string>, members: int}> */
    private static function readRoles(int $tenantId): array
    {
        $db = Database::getInstance();
        $ph = implode(',', array_fill(0, count(self::ROLE_SLUGS), '?'));
        $rows = $db->fetchAll(
            'SELECT r.id, r.name, p.slug FROM roles r
               INNER JOIN role_permissions rp ON rp.role_id = r.id
               INNER JOIN permissions p ON p.id = rp.permission_id
              WHERE r.tenant_id = ? AND p.slug IN (' . $ph . ')
              ORDER BY r.name ASC',
            array_merge([$tenantId], self::ROLE_SLUGS)
        );
        $byRole = [];
        foreach ($rows as $row) {
            $id = (int) $row['id'];
            $byRole[$id] ??= ['id' => $id, 'name' => (string) $row['name'], 'slugs' => [], 'members' => 0];
            $byRole[$id]['slugs'][] = (string) $row['slug'];
        }
        if ($byRole !== []) {
            try {
                $counts = (new \App\Repositories\RoleRepository())->countMembersByRoleIds($tenantId, array_keys($byRole));
                foreach ($counts as $rid => $n) {
                    if (isset($byRole[(int) $rid])) {
                        $byRole[(int) $rid]['members'] = (int) $n;
                    }
                }
            } catch (\Throwable) {
                // Effectif inconnu : le rôle reste affiché sans compte.
            }
        }

        return array_values($byRole);
    }

    /** @return list<array<string, mixed>> */
    private function entriesOf(int $tenantId): array
    {
        try {
            return $this->entries !== null ? ($this->entries)($tenantId) : (new \App\Repositories\SseAccessCodeRepository())->listMemberEntries($tenantId);
        } catch (\Throwable) {
            return [];
        }
    }
}
