<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Core\Database;
use App\Repositories\SseAuditLogRepository;
use App\Repositories\SseFileRecordRepository;
use App\Repositories\SseFileRepository;
use App\Repositories\SseIdentityRepository;
use App\Repositories\SseLoreRepository;
use App\Support\SseFileSubjectCatalog as S;
use App\Support\SseFileVisibility as V;

/**
 * Lore de session (préparation MJ) : personnes d'une mission remontées du jeu ou créées sur le web, ce qui est « connu
 * des fichiers » ou « caché (MJ) », phases de révélation, verrou, import / export, historique, et redescente vers le jeu
 * (ICD-SSE-LORE-001). Même modèle que les fichiers : une personne = une identité du registre + sa fiche.
 */
final class SseLoreService
{
    public const KIND_PUSH = 'lore_push';
    public const KIND_PULL = 'lore_pull';
    public const KINDS = [self::KIND_PUSH, self::KIND_PULL];

    public const MAX_BATCH = 25;
    public const MAX_PERSONS = 500;
    /** Une page de redescente, avec la marge pour « OK|id|ref » (8 000 octets au plus côté DLL). */
    public const PAGE_BYTES = 7400;

    private Database $db;

    /** @var \Closure(int, int): bool */
    private \Closure $canSeeHidden;

    /**
     * @param (\Closure(int, int): bool)|null $canSeeHidden le compte relié en jeu peut-il lire le caché MJ ? (communauté, compte)
     */
    public function __construct(
        private ?SseLoreRepository $lore = null,
        private ?SseFileRecordRepository $records = null,
        private ?SseIdentityRepository $identities = null,
        private ?SseFileRepository $files = null,
        ?\Closure $canSeeHidden = null,
    ) {
        $this->lore ??= new SseLoreRepository();
        $this->records ??= new SseFileRecordRepository();
        $this->identities ??= new SseIdentityRepository();
        $this->files ??= new SseFileRepository();
        $this->db = $this->records->db();
        $this->canSeeHidden = $canSeeHidden ?? static fn (int $t, int $u): bool => (new \App\Services\Atak\AtakGameAdminService())->can($t, $u, ['atak.sse.lore.manage', 'admin.access']);
    }

    public function lore(): SseLoreRepository
    {
        return $this->lore;
    }

    // ------------------------------------------------------------ Jeu → web

    /**
     * Lot de personnes remontées du jeu. Personne nouvelle : créée ; connue (même uid) : seules les données vides sont
     * complétées. Session verrouillée : rien ne change.
     *
     * @param array<string, mixed> $body
     * @return array{id: int, ref: string}|array{error: string, status: int}
     */
    public function push(int $tenantId, int $mapId, array $body, ?int $userId): array
    {
        $op = trim(is_scalar($body['op'] ?? null) ? (string) $body['op'] : '');
        if (!SseLoreRepository::validOp($op)) {
            return ['error' => 'invalid_op', 'status' => 422];
        }
        $persons = $body['persons'] ?? null;
        if (!is_array($persons) || $persons === [] || count($persons) > self::MAX_BATCH) {
            return ['error' => is_array($persons) && count($persons) > self::MAX_BATCH ? 'too_large' : 'invalid_persons', 'status' => 422];
        }
        $mission = is_array($body['mission'] ?? null) ? $body['mission'] : [];
        $session = $this->lore->findByOp($tenantId, $op);
        if ($session === null) {
            $id = $this->lore->create($tenantId, [
                'op_key' => $op,
                'title' => self::str($mission['title'] ?? null, 160) ?? ('Mission ' . $op),
                'country' => strtoupper((string) self::str($mission['country'] ?? null, 3)),
                'context_id' => $mapId,
            ], $userId);
            $session = $this->lore->find($tenantId, $id);
        }
        if ($session === null) {
            return ['error' => 'unavailable', 'status' => 503];
        }
        $sid = (int) $session['id'];
        if ($session['locked']) {
            $this->lore->markPush($tenantId, $sid, $this->lore->membersCount($tenantId, $sid));

            return ['id' => $sid, 'ref' => 'ok;0;0;' . count($persons) . ';1'];
        }
        $count = $this->lore->membersCount($tenantId, $sid);
        [$created, $updated, $skipped] = [0, 0, 0];
        $defaultCountry = strtoupper((string) (self::str($mission['country'] ?? null, 3) ?? $session['country'] ?? ''));
        foreach ($persons as $p) {
            if (!is_array($p)) {
                $skipped++;
                continue;
            }
            $uid = self::uid($p['uid'] ?? null);
            if ($uid === null) {
                $skipped++;
                continue;
            }
            $existing = $this->records->identityIdByAiUid($tenantId, $uid);
            if ($existing === null && $count >= self::MAX_PERSONS) {
                $skipped++;
                continue;
            }
            $res = $this->upsertPerson($tenantId, $sid, $p + ['country' => $p['country'] ?? $defaultCountry], $existing, false, $userId, 'game');
            if ($res === null) {
                $skipped++;
            } elseif ($res['created']) {
                $created++;
                $count++;
            } else {
                $updated++;
            }
        }
        $this->resolvePendingLinks($tenantId, $sid);
        $this->lore->markPush($tenantId, $sid, $count);

        return ['id' => $sid, 'ref' => 'ok;' . $created . ';' . $updated . ';' . $skipped . ';0'];
    }

    /**
     * Crée ou complète (ou remplace si $overwrite) une personne de la session à partir du format de remontée.
     *
     * @param array<string, mixed> $p
     * @return array{identity_id: int, created: bool}|null
     */
    public function upsertPerson(int $tenantId, int $sessionId, array $p, ?int $identityId, bool $overwrite, ?int $userId, string $origin): ?array
    {
        $uid = self::uid($p['uid'] ?? null);
        $last = self::str($p['last'] ?? null, 120);
        $first = self::str($p['first'] ?? null, 120);
        if ($identityId === null && $last === null && $first === null) {
            return null;
        }
        $dob = self::str($p['dob'] ?? null, 10);
        $identityData = array_filter([
            'last_name' => $last,
            'first_name' => $first,
            'sex' => in_array(strtoupper((string) ($p['sex'] ?? '')), ['M', 'F'], true) ? strtoupper((string) $p['sex']) : null,
            'birth_date' => $dob !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob) === 1 ? $dob : null,
            'birth_place' => self::str($p['birth_place'] ?? null, 120),
            'nationality' => ($n = strtoupper((string) self::str($p['nationality'] ?? ($p['country'] ?? null), 3))) !== '' ? $n : null,
            'profession' => self::str($p['profession'] ?? null, 120),
            'faction' => self::str($p['faction'] ?? null, 64),
        ], static fn (mixed $v): bool => $v !== null);
        $created = false;
        if ($identityId === null) {
            $identityId = $this->identities->create($tenantId, $identityData + ['kind' => 'civil', 'arma_key' => $uid, 'op_key' => $this->lore->find($tenantId, $sessionId)['op_key'] ?? null], $userId);
            $created = true;
            $current = null;
        } else {
            $current = $this->identities->find($identityId, $tenantId);
            if ($current === null) {
                return null;
            }
            $patch = [];
            foreach ($identityData as $k => $v) {
                $cur = $current[$k] ?? null;
                if ($overwrite || $cur === null || $cur === '' || ($k === 'sex' && $cur === 'X')) {
                    $patch[$k] = $v;
                }
            }
            if ($patch !== []) {
                // update() réécrit toute la ligne : on repart de l'identité actuelle.
                $this->identities->update($identityId, $tenantId, array_merge($current, $patch));
            }
        }

        $profile = $this->records->profile($tenantId, $identityId);
        $status = is_array($p['status'] ?? null) ? $p['status'] : [];
        $cautions = array_values(array_filter([
            !empty($status['armed']) ? 'armed' : null,
        ]));
        $affiliation = trim(implode(' · ', array_filter([self::str($p['side'] ?? null, 20), self::str($p['faction'] ?? null, 64), self::str($p['group'] ?? null, 60)])));
        $profileData = array_filter([
            'country' => ($c = strtoupper((string) self::str($p['country'] ?? null, 3))) !== '' ? $c : null,
            'height_cm' => is_numeric($p['height_cm'] ?? null) ? (int) $p['height_cm'] : null,
            'weight_kg' => is_numeric($p['weight_kg'] ?? null) ? (int) $p['weight_kg'] : null,
            'build' => S::pick(S::BUILDS, $p['build'] ?? null),
            'hair' => S::pick(S::HAIR, $p['hair'] ?? null),
            'eyes' => S::pick(S::EYES, $p['eyes'] ?? null),
            'marks' => self::str($p['marks'] ?? null, 1000),
            'caution_codes' => $cautions !== [] ? $cautions : null,
            'affiliation' => $affiliation !== '' ? $affiliation : null,
            'cover_note' => self::str($p['cover'] ?? null, 1000),
            'knowledge' => self::str($p['knowledge'] ?? null, 8000),
            'ai_answers' => is_array($p['answers'] ?? null) ? $p['answers'] : null,
        ], static fn (mixed $v): bool => $v !== null);
        if (!$overwrite && $profile !== null) {
            foreach ($profileData as $k => $v) {
                $cur = $k === 'ai_answers' ? ($profile['ai_answers'] ?? []) : ($profile[$k] ?? null);
                if ($cur !== null && $cur !== '' && $cur !== []) {
                    unset($profileData[$k]);
                }
            }
        }
        $profileData['lore_session_id'] = $sessionId;
        if ($uid !== null) {
            $profileData['ai_uid'] = $uid;
        }
        if ($profile === null && !array_key_exists('visibility', $profileData)) {
            // Par défaut : ce que la personne sait reste MJ ; le reste est connu des fichiers.
            $profileData['visibility'] = [];
        }
        $this->records->saveProfile($tenantId, $identityId, $profileData, $userId);

        $phase = 0;
        foreach (is_array($p['aliases'] ?? null) ? array_slice($p['aliases'], 0, 20) : [] as $a) {
            if (is_scalar($a)) {
                $this->records->addAlias($tenantId, $identityId, (string) $a, 'aka', 'known', $phase, $userId);
            }
        }
        $existingLinks = $this->records->linksFor($tenantId, [$identityId])[$identityId] ?? [];
        $hasLink = static function (string $kind, string $norm) use ($existingLinks): bool {
            foreach ($existingLinks as $l) {
                if ($l['kind'] === $kind && $l['value_norm'] === $norm) {
                    return true;
                }
            }

            return false;
        };
        foreach (['addresses' => 'address', 'phones' => 'phone'] as $key => $kind) {
            foreach (is_array($p[$key] ?? null) ? array_slice($p[$key], 0, 10) : [] as $v) {
                $v = self::str($v, 255);
                if ($v !== null && !$hasLink($kind, SseFileRecordRepository::linkNorm($kind, $v))) {
                    $this->records->addLink($tenantId, $identityId, ['kind' => $kind, 'value' => $v], $userId);
                }
            }
        }
        foreach (is_array($p['vehicles'] ?? null) ? array_slice($p['vehicles'], 0, 10) : [] as $v) {
            $plate = is_array($v) ? self::str($v['plate'] ?? null, 40) : self::str($v, 40);
            if ($plate !== null && !$hasLink('vehicle', SseFileRecordRepository::linkNorm('vehicle', $plate))) {
                $this->records->addLink($tenantId, $identityId, [
                    'kind' => 'vehicle', 'value' => $plate,
                    'detail' => is_array($v) ? ['make' => $v['make'] ?? null, 'model' => $v['model'] ?? null, 'color' => $v['color'] ?? null] : [],
                ], $userId);
            }
        }
        foreach (is_array($p['links'] ?? null) ? array_slice($p['links'], 0, 20) : [] as $l) {
            $other = is_array($l) ? self::uid($l['uid'] ?? null) : null;
            if ($other === null) {
                continue;
            }
            $otherId = $this->records->identityIdByAiUid($tenantId, $other);
            $value = 'uid:' . $other;
            if (!$hasLink('associate', SseFileRecordRepository::linkNorm('associate', $value))) {
                $this->records->addLink($tenantId, $identityId, ['kind' => 'associate', 'value' => $value, 'linked_identity_id' => $otherId, 'relation' => $l['relation'] ?? 'associate'], $userId);
            }
        }
        // Statut du jeu → mentions aux fichiers (une seule fois chacune).
        $mentionMap = ['wanted' => 'wanted', 'bolo' => 'flagged', 'watchlist' => 'flagged', 'hvt' => 'wanted'];
        if ($created || $overwrite) {
            $active = array_column($this->files->activeMentionsFor($tenantId, [$identityId], []), 'mention');
            foreach ($mentionMap as $k => $mention) {
                if (!empty($status[$k]) && !in_array($mention, $active, true)) {
                    $this->files->createMention($tenantId, ['mention' => $mention, 'identity_id' => $identityId, 'reference' => strtoupper($k), 'note' => 'Lore de session'], $userId);
                    $active[] = $mention;
                }
            }
            if (!empty($status['armed']) && !in_array('armed_dangerous', $active, true)) {
                $this->files->createMention($tenantId, ['mention' => 'armed_dangerous', 'identity_id' => $identityId, 'note' => 'Lore de session'], $userId);
            }
        }
        $this->lore->addMember($tenantId, $sessionId, $identityId, $uid, $origin);
        $this->audit($tenantId, $userId, $origin === 'game' ? 'Synchronisation du jeu' : 'Lore de session', $created ? 'lore.person.create' : 'lore.person.update', $identityId, $created ? 'Personne créée (' . $origin . ')' : 'Données complétées (' . $origin . ')');

        return ['identity_id' => $identityId, 'created' => $created];
    }

    /** Liens « personne liée » dont l'autre personne est arrivée depuis (même uid). */
    private function resolvePendingLinks(int $tenantId, int $sessionId): void
    {
        $rows = $this->db->fetchAll(
            "SELECT l.id, l.value FROM sse_file_links l INNER JOIN sse_lore_members m ON m.identity_id = l.identity_id AND m.tenant_id = l.tenant_id
             WHERE l.tenant_id = :t AND m.session_id = :s AND l.kind = 'associate' AND l.linked_identity_id IS NULL AND l.value LIKE 'uid:%' LIMIT 500",
            ['t' => $tenantId, 's' => $sessionId]
        );
        foreach ($rows as $r) {
            $other = $this->records->identityIdByAiUid($tenantId, substr((string) $r['value'], 4));
            if ($other !== null) {
                $this->db->execute('UPDATE sse_file_links SET linked_identity_id = :o WHERE id = :id AND tenant_id = :t', ['o' => $other, 'id' => (int) $r['id'], 't' => $tenantId]);
            }
        }
    }

    // ------------------------------------------------------------ Web → jeu

    /**
     * Page de redescente au format parseSimpleArray (voir ICD-SSE-LORE-001).
     *
     * @param array<string, mixed> $body
     * @return array{id: int, ref: string}|array{error: string, status: int}
     */
    public function pull(int $tenantId, array $body, ?int $userId): array
    {
        $op = trim(is_scalar($body['op'] ?? null) ? (string) $body['op'] : '');
        if (!SseLoreRepository::validOp($op)) {
            return ['error' => 'invalid_op', 'status' => 422];
        }
        $session = $this->lore->findByOp($tenantId, $op);
        if ($session === null) {
            return ['error' => 'unknown_session', 'status' => 404];
        }
        $sid = (int) $session['id'];
        $page = max(0, (int) ($body['page'] ?? 0));
        $hidden = $userId !== null && $userId > 0 && ($this->canSeeHidden)($tenantId, $userId);
        $members = $this->lore->members($tenantId, $sid, self::MAX_PERSONS);
        $head = self::sqf([$sid, (int) $session['phase'], (bool) $session['locked'], gmdate('Y-m-d\TH:i:s\Z'), (string) ($session['content_updated_at'] ?? ''), (int) $session['auto_refresh_s'], (string) $session['title']]);

        // Découpage en pages de PAGE_BYTES : on saute les pages précédentes en recalculant leur taille.
        $pages = [[]];
        $size = strlen($head) + 16;
        foreach ($members as $m) {
            $entry = $this->pullEntry($tenantId, $m, (int) $session['phase'], $hidden);
            if ($entry === null) {
                continue;
            }
            $len = strlen($entry) + 1;
            if ($size + $len > self::PAGE_BYTES && $pages[count($pages) - 1] !== []) {
                $pages[] = [];
                $size = strlen($head) + 16;
            }
            if ($len + strlen($head) + 16 > self::PAGE_BYTES) {
                continue;
            }
            $pages[count($pages) - 1][] = $entry;
            $size += $len;
        }
        $page = min($page, count($pages) - 1);
        $next = $page + 1 < count($pages) ? $page + 1 : 0;
        if ($page === 0) {
            $this->lore->markPull($tenantId, $sid);
        }

        return ['id' => $sid, 'ref' => '[' . $head . ',[' . implode(',', $pages[$page]) . '],' . $next . ']'];
    }

    /** Une personne au format de redescente, ou null si la fiche a disparu. */
    private function pullEntry(int $tenantId, array $member, int $phase, bool $withHidden): ?string
    {
        $iid = (int) $member['identity_id'];
        $identity = $this->identities->find($iid, $tenantId);
        if ($identity === null) {
            return null;
        }
        $profile = $this->records->profile($tenantId, $iid);
        $aliases = $this->records->aliasesFor($tenantId, [$iid])[$iid] ?? [];
        $links = $this->records->linksFor($tenantId, [$iid])[$iid] ?? [];
        $mentions = array_column($this->files->activeMentionsFor($tenantId, [$iid], []), 'mention');
        $known = [];
        $secret = [];
        $put = static function (bool $visible, string $k, mixed $v) use (&$known, &$secret): void {
            if ($v === null || $v === '' || $v === []) {
                return;
            }
            if ($visible) {
                $known[] = [$k, $v];
            } else {
                $secret[] = [$k, $v];
            }
        };
        $f = static fn (string $field): bool => V::field($profile, $field, $phase);
        $put(true, 'in_files', V::inFiles($profile));
        $put($f('identity'), 'last', (string) $identity['last_name']);
        $put($f('identity'), 'first', (string) $identity['first_name']);
        $put($f('identity'), 'sex', (string) $identity['sex']);
        $put($f('identity'), 'dob', (string) ($identity['birth_date'] ?? ''));
        $put($f('birth'), 'birth_place', (string) ($identity['birth_place'] ?? ''));
        $put($f('birth'), 'nationality', (string) ($identity['nationality'] ?? ''));
        $put($f('profession'), 'profession', (string) ($identity['profession'] ?? ''));
        $put(true, 'country', (string) ($profile['country'] ?? ''));
        foreach (['height_cm', 'weight_kg', 'build', 'hair', 'eyes'] as $k) {
            $put($f('physical'), $k, $profile[$k] ?? null);
        }
        $put($f('marks'), 'marks', $profile['marks'] ?? null);
        $put($f('affiliation'), 'affiliation', $profile['affiliation'] ?? null);
        $put($f('cover'), 'cover', $profile['cover_note'] ?? null);
        $put($f('cautions'), 'cautions', $profile['caution_codes'] ?? []);
        $put(true, 'flags', array_values(array_unique($mentions)));
        $grouped = ['aliases' => [[], []], 'addresses' => [[], []], 'vehicles' => [[], []], 'phones' => [[], []]];
        foreach ($aliases as $a) {
            if ($a['kind'] !== 'legal' && $a['kind'] !== 'registry') {
                $grouped['aliases'][V::row($a, $phase) ? 0 : 1][] = (string) $a['alias'];
            }
        }
        foreach ($links as $l) {
            $key = ['address' => 'addresses', 'vehicle' => 'vehicles', 'phone' => 'phones'][$l['kind']] ?? null;
            if ($key !== null) {
                $grouped[$key][V::row($l, $phase) ? 0 : 1][] = $key === 'vehicles' ? trim($l['value'] . ' ' . implode(' ', array_filter([(string) ($l['detail']['make'] ?? ''), (string) ($l['detail']['model'] ?? ''), (string) ($l['detail']['color'] ?? '')]))) : (string) $l['value'];
            }
        }
        foreach ($grouped as $k => [$vis, $hid]) {
            $put(true, $k, $vis);
            $put(false, $k, $hid);
        }
        $put(false, 'knowledge', $profile['knowledge'] ?? null);
        $answers = [];
        foreach (($profile['ai_answers'] ?? []) as $k => $v) {
            $answers[] = [$k, $k === 'evoke' ? implode(' ', (array) $v) : $v];
        }

        return self::sqf([(string) ($member['ai_uid'] ?? $identity['arma_key'] ?? ''), (string) $identity['ref'], $known, $answers, $withHidden ? $secret : []]);
    }

    /**
     * Encodage parseSimpleArray (SQF) : textes entre guillemets doublés, \n pour un retour, \p pour |.
     */
    public static function sqf(mixed $v): string
    {
        return match (true) {
            is_bool($v) => $v ? 'true' : 'false',
            is_int($v) || is_float($v) => (string) $v,
            is_array($v) => '[' . implode(',', array_map([self::class, 'sqf'], array_values($v))) . ']',
            $v === null => '""',
            default => '"' . str_replace(['"', "\r", "\n", '|', "\t"], ['""', '', '\n', '\p', ' '], (string) $v) . '"',
        };
    }

    // ------------------------------------------------------------ Web (MJ)

    /**
     * Export d'une session au format de remontée (JSON) : même format qu'un import, visibilité comprise.
     *
     * @return array<string, mixed>
     */
    public function export(int $tenantId, int $sessionId): array
    {
        $session = $this->lore->find($tenantId, $sessionId);
        $persons = [];
        foreach ($this->lore->members($tenantId, $sessionId, self::MAX_PERSONS) as $m) {
            $iid = (int) $m['identity_id'];
            $i = $this->identities->find($iid, $tenantId);
            if ($i === null) {
                continue;
            }
            $p = $this->records->profile($tenantId, $iid) ?? [];
            $links = $this->records->linksFor($tenantId, [$iid])[$iid] ?? [];
            $by = static fn (string $kind): array => array_values(array_map(static fn (array $l): string => (string) $l['value'], array_filter($links, static fn (array $l): bool => $l['kind'] === $kind)));
            $persons[] = array_filter([
                'uid' => $m['ai_uid'] ?? $i['arma_key'],
                'ref' => $i['ref'],
                'country' => $p['country'] ?? null,
                'last' => $i['last_name'], 'first' => $i['first_name'], 'sex' => $i['sex'], 'dob' => $i['birth_date'],
                'birth_place' => $i['birth_place'], 'nationality' => $i['nationality'], 'profession' => $i['profession'],
                'aliases' => array_values(array_map(static fn (array $a): string => (string) $a['alias'], array_filter($this->records->aliasesFor($tenantId, [$iid])[$iid] ?? [], static fn (array $a): bool => !in_array($a['kind'], ['legal', 'registry'], true)))),
                'height_cm' => $p['height_cm'] ?? null, 'weight_kg' => $p['weight_kg'] ?? null, 'build' => $p['build'] ?? null,
                'hair' => $p['hair'] ?? null, 'eyes' => $p['eyes'] ?? null, 'marks' => $p['marks'] ?? null,
                'addresses' => $by('address'), 'phones' => $by('phone'),
                'vehicles' => array_values(array_map(static fn (array $l): array => ['plate' => $l['value']] + $l['detail'], array_filter($links, static fn (array $l): bool => $l['kind'] === 'vehicle'))),
                'cautions' => $p['caution_codes'] ?? [],
                'affiliation' => $p['affiliation'] ?? null, 'cover' => $p['cover_note'] ?? null, 'knowledge' => $p['knowledge'] ?? null,
                'answers' => $p['ai_answers'] ?? [], 'visibility' => $p['visibility'] ?? [], 'in_files' => $p['in_files'] ?? true,
            ], static fn (mixed $v): bool => $v !== null && $v !== [] && $v !== '');
        }

        return ['format' => 'athena-sse-lore', 'version' => 1, 'session' => $session === null ? null : [
            'op' => $session['op_key'], 'title' => $session['title'], 'summary' => $session['summary'], 'country' => $session['country'], 'phase' => $session['phase'],
        ], 'persons' => $persons, 'exported_at' => gmdate('Y-m-d\TH:i:s\Z')];
    }

    /**
     * Import JSON (format d'export) ou CSV (en-têtes : uid,last,first,sex,dob,country,nationality,profession,aliases,
     * height_cm,weight_kg,build,hair,eyes,marks,phones,addresses) : les personnes du fichier remplacent les données web.
     *
     * @return array{created: int, updated: int, skipped: int, error?: string}
     */
    public function import(int $tenantId, int $sessionId, string $content, string $format, ?int $userId): array
    {
        $session = $this->lore->find($tenantId, $sessionId);
        if ($session === null) {
            return ['created' => 0, 'updated' => 0, 'skipped' => 0, 'error' => 'Session introuvable.'];
        }
        if ($session['locked']) {
            return ['created' => 0, 'updated' => 0, 'skipped' => 0, 'error' => 'Session verrouillée : déverrouillez-la avant d’importer.'];
        }
        if (strlen($content) > 2_000_000) {
            return ['created' => 0, 'updated' => 0, 'skipped' => 0, 'error' => 'Fichier trop gros (2 Mo au plus).'];
        }
        $persons = $format === 'csv' ? self::parseCsv($content) : (json_decode($content, true)['persons'] ?? null);
        if (!is_array($persons)) {
            return ['created' => 0, 'updated' => 0, 'skipped' => 0, 'error' => 'Fichier illisible : JSON exporté par Athena ou CSV avec une ligne d’en-tête.'];
        }
        $out = ['created' => 0, 'updated' => 0, 'skipped' => 0];
        foreach (array_slice($persons, 0, self::MAX_PERSONS) as $p) {
            if (!is_array($p)) {
                $out['skipped']++;
                continue;
            }
            $uid = self::uid($p['uid'] ?? null) ?? ('web-' . bin2hex(random_bytes(5)));
            $p['uid'] = $uid;
            $existing = $this->records->identityIdByAiUid($tenantId, $uid);
            $res = $this->upsertPerson($tenantId, $sessionId, $p, $existing, true, $userId, 'web');
            if ($res === null) {
                $out['skipped']++;
                continue;
            }
            $extra = [];
            if (isset($p['visibility'])) {
                $extra['visibility'] = $p['visibility'];
            }
            if (isset($p['in_files'])) {
                $extra['in_files'] = $p['in_files'] ? 1 : 0;
            }
            if (isset($p['cautions'])) {
                $extra['caution_codes'] = $p['cautions'];
            }
            if ($extra !== []) {
                $this->records->saveProfile($tenantId, $res['identity_id'], $extra, $userId);
            }
            $out[$res['created'] ? 'created' : 'updated']++;
        }
        $this->resolvePendingLinks($tenantId, $sessionId);
        $this->lore->touch($tenantId, $sessionId);

        return $out;
    }

    /** @return list<array<string, mixed>> */
    public static function parseCsv(string $content): array
    {
        $lines = preg_split('/\R/u', trim($content)) ?: [];
        if (count($lines) < 2) {
            return [];
        }
        $sep = substr_count($lines[0], ';') > substr_count($lines[0], ',') ? ';' : ',';
        $head = array_map(static fn (string $h): string => strtolower(trim($h)), str_getcsv(ltrim($lines[0], "\xEF\xBB\xBF"), $sep, '"', ''));
        $out = [];
        foreach (array_slice($lines, 1) as $line) {
            if (trim($line) === '') {
                continue;
            }
            $cells = str_getcsv($line, $sep, '"', '');
            $row = [];
            foreach ($head as $i => $h) {
                $v = trim((string) ($cells[$i] ?? ''));
                if ($v === '') {
                    continue;
                }
                $row[$h] = in_array($h, ['aliases', 'phones', 'addresses'], true) ? array_values(array_filter(array_map('trim', explode('|', $v)))) : $v;
            }
            if ($row !== []) {
                $out[] = $row;
            }
        }

        return $out;
    }

    /** CSV d'export (une personne par ligne). */
    public function exportCsv(int $tenantId, int $sessionId): string
    {
        $cols = ['uid', 'ref', 'last', 'first', 'sex', 'dob', 'country', 'nationality', 'profession', 'aliases', 'height_cm', 'weight_kg', 'build', 'hair', 'eyes', 'marks', 'phones', 'addresses'];
        $fh = fopen('php://temp', 'w+');
        if ($fh === false) {
            return '';
        }
        fwrite($fh, "\xEF\xBB\xBF");
        fputcsv($fh, $cols, ';', '"', '');
        foreach ($this->export($tenantId, $sessionId)['persons'] as $p) {
            $row = [];
            foreach ($cols as $c) {
                $v = $p[$c] ?? '';
                $row[] = is_array($v) ? implode('|', array_map('strval', $v)) : (string) $v;
            }
            fputcsv($fh, $row, ';', '"', '');
        }
        rewind($fh);

        return (string) stream_get_contents($fh);
    }

    /** @return list<array<string, mixed>> historique des modifications des personnes de la session */
    public function history(int $tenantId, int $sessionId, int $limit = 80): array
    {
        $ids = array_map(static fn (array $m): int => (int) $m['identity_id'], $this->lore->members($tenantId, $sessionId, self::MAX_PERSONS));
        $rows = [];
        try {
            $audit = new SseAuditLogRepository($this->db);
            foreach ($audit->listForTenant($tenantId, ['object_type' => 'lore_session', 'object_id' => $sessionId, 'limit' => $limit]) as $r) {
                $rows[] = $r;
            }
            if ($ids !== []) {
                [$in, $params] = SseFileRecordRepository::in('i', array_slice($ids, 0, 500));
                $params['t'] = $tenantId;
                foreach ($this->db->fetchAll("SELECT id, actor_label, action, object_id, reason, created_at FROM sse_audit_log WHERE tenant_id = :t AND object_type = 'sse_identity' AND object_id IN ({$in}) ORDER BY id DESC LIMIT " . max(1, min(200, $limit)), $params) as $r) {
                    $rows[] = $r;
                }
            }
        } catch (\Throwable $e) {
            error_log('Lore de session : historique indisponible : ' . $e->getMessage());
        }
        usort($rows, static fn (array $a, array $b): int => strcmp((string) $b['created_at'], (string) $a['created_at']));

        return array_slice($rows, 0, $limit);
    }

    public function audit(int $tenantId, ?int $userId, string $label, string $action, int $identityId, string $reason): void
    {
        try {
            (new SseAuditLogRepository($this->db))->write([
                'tenant_id' => $tenantId, 'actor_user_id' => $userId, 'actor_label' => $label, 'action' => $action,
                'object_type' => 'sse_identity', 'object_id' => $identityId, 'reason' => mb_substr($reason, 0, 500),
            ]);
        } catch (\Throwable $e) {
            error_log('Lore de session : audit indisponible : ' . $e->getMessage());
        }
    }

    private static function uid(mixed $v): ?string
    {
        $s = trim(is_scalar($v) ? (string) $v : '');

        return preg_match('/^[A-Za-z0-9_.:\-]{3,64}$/', $s) === 1 ? $s : null;
    }

    private static function str(mixed $v, int $max): ?string
    {
        $s = trim(str_replace(["\r", "\t"], ' ', is_scalar($v) ? (string) $v : ''));

        return $s === '' ? null : mb_substr($s, 0, $max);
    }
}
