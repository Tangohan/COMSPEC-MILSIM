<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Core\Database;
use App\Repositories\SseWantedNoticeRepository;

/**
 * Sujet de chaque élément de « À trier » : QUI ou QUOI est concerné (dossier, personnes rattachées, identité), avec le masquage dû.
 *
 * Un élément porte subject_type (case, identity) et subject_id ; le service ajoute « subject » : libellé, personnes, lien, et les
 * indices dont la priorité et les règles ont besoin (identité de couverture, avis de recherche actif, classification).
 *
 * Masquage (jamais de nom sans autorisation) :
 *  - dossier tenu à un niveau supérieur à l'habilitation de la session (SseClearanceService::caseAboveClearance) : ni titre ni personnes ;
 *  - identité rattachée à un compte de membre (images TRÈS SECRET) : nommée seulement si SseMemberImageGate la laisse voir à la
 *    session ; sinon « identité de membre » sans nom, y compris parmi les personnes d'un dossier ;
 *  - toute erreur de lecture du droit : masqué (échec fermé).
 */
final class SseTriageSubjects
{
    public const MASKED_CASE = 'Dossier d’un niveau supérieur à votre habilitation';
    public const MASKED_MEMBER = 'Identité de membre (accès restreint)';
    public const PEOPLE_MAX = 3;

    /**
     * @param (\Closure(int, int): bool)|null $memberVisible (communauté, compte du membre) : la session peut-elle nommer ce membre ?
     * @param (\Closure(array<string, mixed>): bool)|null $caseAbove le dossier dépasse-t-il l'habilitation de la session ?
     * @param (\Closure(int, list<int>): array<int, bool>)|null $wanted identités sous avis de recherche actif
     */
    public function __construct(
        private ?Database $db = null,
        private ?\Closure $memberVisible = null,
        private ?\Closure $caseAbove = null,
        private ?\Closure $wanted = null,
    ) {
        $this->db ??= Database::getInstance();
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return list<array<string, mixed>>
     */
    public function enrich(int $tenantId, array $items): array
    {
        $caseIds = [];
        $identityIds = [];
        foreach ($items as $it) {
            $id = (int) ($it['subject_id'] ?? 0);
            if ($id < 1) {
                continue;
            }
            if (($it['subject_type'] ?? '') === 'case') {
                $caseIds[$id] = $id;
            } elseif (($it['subject_type'] ?? '') === 'identity') {
                $identityIds[$id] = $id;
            }
        }
        if ($caseIds === [] && $identityIds === []) {
            return $items;
        }
        $cases = $this->cases($tenantId, array_values($caseIds));
        $people = $this->peopleByCase($tenantId, array_values($caseIds));
        $ids = $identityIds;
        foreach ($people as $list) {
            foreach ($list as $p) {
                if ($p['identity_id'] > 0) {
                    $ids[$p['identity_id']] = $p['identity_id'];
                }
            }
        }
        $wanted = $this->wantedSet($tenantId, array_values($ids));
        $identities = $this->identities($tenantId, array_values($identityIds));

        foreach ($items as &$it) {
            $id = (int) ($it['subject_id'] ?? 0);
            if ($id < 1) {
                continue;
            }
            if (($it['subject_type'] ?? '') === 'case') {
                $it['subject'] = $this->caseSubject($tenantId, $id, $cases[$id] ?? null, $people[$id] ?? [], $wanted);
            } elseif (($it['subject_type'] ?? '') === 'identity') {
                $it['subject'] = $this->identitySubject($tenantId, $id, $identities[$id] ?? null, $it, $wanted);
            }
        }

        return $items;
    }

    /**
     * @param array<string, mixed>|null $case
     * @param list<array{name: string, identity_id: int, kind: string, member: int}> $people
     * @param array<int, bool> $wanted
     * @return array<string, mixed>
     */
    private function caseSubject(int $tenantId, int $id, ?array $case, array $people, array $wanted): array
    {
        $href = function_exists('url') ? (string) url('atak/sse/dossiers/' . $id) : 'atak/sse/dossiers/' . $id;
        $base = ['kind' => 'case', 'id' => $id, 'key' => 'case:' . $id, 'href' => $href, 'people' => [], 'masked' => false, 'cover' => false, 'wanted' => false, 'classification' => '', 'noun' => 'dossier'];
        if ($case === null) {
            return ['label' => 'Dossier #' . $id] + $base;
        }
        $base['classification'] = (string) ($case['classification'] ?? '');
        $base['cover'] = false;
        foreach ($people as $p) {
            if ($p['kind'] === 'cover') {
                $base['cover'] = true;
            }
            if ($p['identity_id'] > 0 && ($wanted[$p['identity_id']] ?? false)) {
                $base['wanted'] = true;
            }
        }
        if ($this->aboveClearance($case)) {
            return ['label' => self::MASKED_CASE, 'masked' => true, 'href' => ''] + $base;
        }
        $names = [];
        $hiddenMember = false;
        foreach ($people as $p) {
            if ($p['member'] > 0 && !$this->canNameMember($tenantId, $p['member'])) {
                $hiddenMember = true;
                continue;
            }
            if ($p['name'] !== '') {
                $names[] = $p['name'];
            }
        }
        $shown = array_slice(array_values(array_unique($names)), 0, self::PEOPLE_MAX);
        $extra = count(array_unique($names)) - count($shown);
        if ($extra > 0) {
            $shown[] = '+' . $extra;
        }
        if ($hiddenMember) {
            $shown[] = self::MASKED_MEMBER;
        }
        $ref = trim((string) ($case['reference_code'] ?? ''));
        $title = trim((string) ($case['title'] ?? ''));

        return ['label' => trim($ref . ($ref !== '' && $title !== '' ? ' · ' : '') . $title) ?: 'Dossier #' . $id, 'people' => $shown, 'masked' => $hiddenMember && $names === []] + $base;
    }

    /**
     * @param array<string, mixed>|null $identity
     * @param array<string, mixed> $item
     * @param array<int, bool> $wanted
     * @return array<string, mixed>
     */
    private function identitySubject(int $tenantId, int $id, ?array $identity, array $item, array $wanted): array
    {
        $href = function_exists('url') ? (string) url('atak/sse/papiers/identites/' . $id) : 'atak/sse/papiers/identites/' . $id;
        $member = (int) ($identity['user_id'] ?? 0);
        $name = $identity !== null ? trim(trim((string) ($identity['first_name'] ?? '')) . ' ' . trim((string) ($identity['last_name'] ?? ''))) : '';
        if ($name === '') {
            $name = trim((string) ($item['subject_name'] ?? ''));
        }
        $masked = $member > 0 && !$this->canNameMember($tenantId, $member);

        return [
            'kind' => 'identity', 'id' => $id, 'key' => 'identity:' . $id, 'noun' => 'identité',
            'label' => $masked ? self::MASKED_MEMBER : ($name !== '' ? $name : 'Identité #' . $id),
            'people' => [], 'href' => $masked ? '' : $href, 'masked' => $masked,
            'cover' => (string) ($identity['kind'] ?? '') === 'cover', 'wanted' => $wanted[$id] ?? false, 'classification' => $member > 0 ? 'tres_restreint' : '',
        ];
    }

    private function canNameMember(int $tenantId, int $owner): bool
    {
        try {
            return $this->memberVisible !== null
                ? (bool) ($this->memberVisible)($tenantId, $owner)
                : SseMemberImageGate::forSession()->forMember($tenantId, $owner)['allowed'];
        } catch (\Throwable) {
            return false;
        }
    }

    /** @param array<string, mixed> $case */
    private function aboveClearance(array $case): bool
    {
        try {
            return $this->caseAbove !== null ? (bool) ($this->caseAbove)($case) : (new SseClearanceService())->caseAboveClearance($case);
        } catch (\Throwable) {
            return true;
        }
    }

    /**
     * @param list<int> $ids
     * @return array<int, bool>
     */
    private function wantedSet(int $tenantId, array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        try {
            if ($this->wanted !== null) {
                return ($this->wanted)($tenantId, $ids);
            }
            if (!SseWantedNoticeRepository::ready()) {
                return [];
            }
            $out = [];
            foreach (array_keys((new SseWantedNoticeRepository($this->db))->activeForIdentities($tenantId, $ids)) as $identityId) {
                $out[(int) $identityId] = true;
            }

            return $out;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @param list<int> $ids
     * @return array<int, array<string, mixed>>
     */
    private function cases(int $tenantId, array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        try {
            $out = [];
            foreach ($this->db->fetchAll('SELECT id, reference_code, title, classification, status FROM sse_cases WHERE tenant_id = :t AND id IN (' . implode(',', array_map('intval', $ids)) . ')', ['t' => $tenantId]) as $row) {
                $out[(int) $row['id']] = $row;
            }

            return $out;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @param list<int> $caseIds
     * @return array<int, list<array{name: string, identity_id: int, kind: string, member: int}>>
     */
    private function peopleByCase(int $tenantId, array $caseIds): array
    {
        if ($caseIds === []) {
            return [];
        }
        try {
            $in = implode(',', array_map('intval', $caseIds));
            $links = $this->db->fetchAll('SELECT case_id, person_id FROM sse_case_persons WHERE tenant_id = :t AND case_id IN (' . $in . ') ORDER BY id ASC', ['t' => $tenantId]);
            $personIds = array_values(array_unique(array_map(static fn (array $l): int => (int) $l['person_id'], $links)));
            if ($personIds === []) {
                return [];
            }
            $pin = implode(',', $personIds);
            $persons = [];
            foreach ($this->db->fetchAll('SELECT id, last_name, first_name, alias FROM sse_persons WHERE tenant_id = :t AND id IN (' . $pin . ')', ['t' => $tenantId]) as $p) {
                $persons[(int) $p['id']] = $p;
            }
            $identityOf = [];
            try {
                foreach ($this->db->fetchAll('SELECT id, person_id, kind, user_id FROM sse_identities WHERE tenant_id = :t AND person_id IN (' . $pin . ') ORDER BY id ASC', ['t' => $tenantId]) as $i) {
                    $identityOf[(int) $i['person_id']][] = $i;
                }
            } catch (\Throwable) {
                // Registre des identités absent : les personnes restent nommées, sans indice de couverture ni de membre.
            }
            $out = [];
            foreach ($links as $l) {
                $p = $persons[(int) $l['person_id']] ?? null;
                if ($p === null) {
                    continue;
                }
                $name = trim(trim((string) ($p['first_name'] ?? '')) . ' ' . trim((string) ($p['last_name'] ?? '')));
                if ($name === '') {
                    $name = trim((string) ($p['alias'] ?? ''));
                }
                $idents = $identityOf[(int) $l['person_id']] ?? [[]];
                foreach ($idents as $i) {
                    $out[(int) $l['case_id']][] = [
                        'name' => $name, 'identity_id' => (int) ($i['id'] ?? 0), 'kind' => (string) ($i['kind'] ?? ''), 'member' => (int) ($i['user_id'] ?? 0),
                    ];
                }
            }

            return $out;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @param list<int> $ids
     * @return array<int, array<string, mixed>>
     */
    private function identities(int $tenantId, array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        try {
            $out = [];
            foreach ($this->db->fetchAll('SELECT id, kind, user_id, first_name, last_name FROM sse_identities WHERE tenant_id = :t AND id IN (' . implode(',', array_map('intval', $ids)) . ')', ['t' => $tenantId]) as $row) {
                $out[(int) $row['id']] = $row;
            }

            return $out;
        } catch (\Throwable) {
            return [];
        }
    }
}
