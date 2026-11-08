<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Core\Database;
use App\Repositories\SseIdentityRepository;
use App\Support\Migrations\SchemaInspector;
use App\Support\SseIdentityCatalog;

/**
 * COMSPEC IDENTITY — recoupement des contrôles par clé biométrique (sse_id_checks.subject_bio_key).
 *
 * Un contrôle se rattache tout seul à la personne ou à l'identité qui porte la même clé, sans jamais toucher au papier
 * présenté (un papier volé reste celui de son titulaire) et sans jamais fusionner deux fiches : plusieurs candidats
 * restent des suggestions, à trancher par un analyste habilité.
 *
 * La clé d'une fiche s'apprend sur un rattachement sûr : fiche désignée par le mod ou par l'unité de jeu ; pour une
 * identité, papier présenté par le joueur qui en est réellement le titulaire (Steam ID). Une clé « S… » ne vaut que pour
 * une partie, « P… » pour une mission : le rattachement à une personne reste donc limité à la même carte.
 */
final class SseIdSubjectMatchService
{
    private const MAX_CANDIDATES = 6;

    private const MAX_SUGGESTIONS = 20;

    private Database $db;

    private ?bool $personsReady = null;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
        SseIdentityRepository::ensureSchema();
    }

    /**
     * Rattachement du contrôle à enregistrer. Ce qui est déjà connu (papier, fiche désignée) n'est jamais remplacé.
     *
     * @return array{person_id: ?int, identity_id: ?int}
     */
    public function resolve(int $tenantId, int $mapId, ?string $bioKey, ?int $personId, ?int $documentIdentityId, ?string $presentedLast): array
    {
        $out = ['person_id' => $personId, 'identity_id' => $documentIdentityId];
        if ($bioKey === null || $bioKey === '') {
            return $out;
        }
        if ($documentIdentityId === null) {
            $identity = $this->identityForKey($tenantId, $bioKey, $presentedLast);
            if ($identity !== null) {
                $out['identity_id'] = (int) $identity['id'];
                if ($personId === null && (int) ($identity['person_id'] ?? 0) > 0) {
                    $out['person_id'] = (int) $identity['person_id'];
                }
            }
        }
        if ($out['person_id'] === null) {
            $out['person_id'] = $this->personForKey($tenantId, $mapId, $bioKey);
        }

        return $out;
    }

    /**
     * Après l'enregistrement : mémorise la clé sur la fiche et rattache les contrôles précédents de la même clé.
     */
    public function remember(int $tenantId, int $mapId, ?string $bioKey, ?int $personId, ?int $documentIdentityId, ?string $subjectSteam): void
    {
        if ($bioKey === null || $bioKey === '') {
            return;
        }
        if ($personId !== null) {
            $this->rememberPerson($tenantId, $mapId, $bioKey, $personId);
        }
        if ($documentIdentityId !== null && $subjectSteam !== null && $subjectSteam !== '') {
            $this->rememberIdentity($tenantId, $mapId, $bioKey, $documentIdentityId, $subjectSteam);
        }
    }

    /**
     * « Possiblement la même personne » : autres fiches et identités qui partagent une clé avec cette personne.
     * Vérité de jeu : à ne servir qu'avec l'habilitation Confidentiel.
     *
     * @return list<array{type: string, id: int, name: string, ref: string}>
     */
    public function suggestionsForPerson(int $tenantId, int $personId): array
    {
        if ($tenantId < 1 || $personId < 1 || !$this->personsReady()) {
            return [];
        }
        $keys = $this->keysOfPerson($tenantId, $personId);
        if ($keys === []) {
            return [];
        }
        [$own, $ownParams] = self::keyClause($keys, 'a');
        [$seen, $seenParams] = self::keyClause($keys, 'b');
        $params = $ownParams + $seenParams + ['t' => $tenantId, 'p' => $personId, 't2' => $tenantId];
        $out = [];
        foreach ($this->db->fetchAll(
            'SELECT id, last_name, first_name FROM sse_persons WHERE tenant_id = :t AND id <> :p AND (bio_key IN (' . $own . ')
                OR id IN (SELECT c.person_id FROM sse_id_checks c WHERE c.tenant_id = :t2 AND c.subject_bio_key IN (' . $seen . ')))
             ORDER BY id ASC LIMIT ' . self::MAX_SUGGESTIONS,
            $params
        ) as $r) {
            $out[] = [
                'type' => 'person',
                'id' => (int) $r['id'],
                'name' => trim(mb_strtoupper((string) ($r['last_name'] ?? '')) . ' ' . (string) ($r['first_name'] ?? '')),
                'ref' => 'IDN-' . str_pad((string) (int) $r['id'], 5, '0', STR_PAD_LEFT),
            ];
        }
        foreach ($this->db->fetchAll(
            'SELECT id, last_name, first_name FROM sse_identities WHERE tenant_id = :t AND COALESCE(person_id, 0) <> :p AND (bio_key IN (' . $own . ')
                OR id IN (SELECT c.identity_id FROM sse_id_checks c WHERE c.tenant_id = :t2 AND c.subject_bio_key IN (' . $seen . ')))
             ORDER BY id ASC LIMIT ' . self::MAX_SUGGESTIONS,
            $params
        ) as $r) {
            $out[] = [
                'type' => 'identity',
                'id' => (int) $r['id'],
                'name' => trim(mb_strtoupper((string) ($r['last_name'] ?? '')) . ' ' . (string) ($r['first_name'] ?? '')),
                'ref' => SseIdentityCatalog::identityRef((int) $r['id']),
            ];
        }

        return $out;
    }

    /** @return array<string, mixed>|null identité unique de cette clé ; à plusieurs, celle du nom présenté ; sinon aucune */
    private function identityForKey(int $tenantId, string $bioKey, ?string $presentedLast): ?array
    {
        $rows = $this->db->fetchAll(
            'SELECT id, person_id, last_name FROM sse_identities WHERE tenant_id = :t AND bio_key = :k ORDER BY id ASC LIMIT ' . self::MAX_CANDIDATES,
            ['t' => $tenantId, 'k' => $bioKey]
        );
        if (!isset($rows[1])) {
            return $rows[0] ?? null;
        }
        $wanted = mb_strtoupper(trim((string) $presentedLast));
        $same = array_values(array_filter($rows, static fn (array $r): bool => $wanted !== '' && mb_strtoupper(trim((string) $r['last_name'])) === $wanted));

        return isset($same[0]) && !isset($same[1]) ? $same[0] : null;
    }

    /** Fiche unique de cette clé sur la carte ; plusieurs candidates restent des suggestions. */
    private function personForKey(int $tenantId, int $mapId, string $bioKey): ?int
    {
        if (!$this->personsReady()) {
            return null;
        }
        $rows = $this->db->fetchAll(
            'SELECT id FROM sse_persons WHERE tenant_id = :t AND context_id = :m AND bio_key = :k ORDER BY id ASC LIMIT ' . self::MAX_CANDIDATES,
            ['t' => $tenantId, 'm' => $mapId, 'k' => $bioKey]
        );

        return isset($rows[0]) && !isset($rows[1]) ? (int) $rows[0]['id'] : null;
    }

    private function rememberPerson(int $tenantId, int $mapId, string $bioKey, int $personId): void
    {
        if (!$this->personsReady()) {
            return;
        }
        $holder = $this->db->fetchOne(
            'SELECT id FROM sse_persons WHERE tenant_id = :t AND context_id = :m AND bio_key = :k AND id <> :p LIMIT 1',
            ['t' => $tenantId, 'm' => $mapId, 'k' => $bioKey, 'p' => $personId]
        );
        if ($holder !== null) {
            return;
        }
        $this->db->execute(
            "UPDATE sse_persons SET bio_key = :k WHERE id = :p AND tenant_id = :t AND (bio_key IS NULL OR bio_key = '')",
            ['k' => $bioKey, 'p' => $personId, 't' => $tenantId]
        );
        $this->db->execute(
            'UPDATE sse_id_checks SET person_id = :p WHERE tenant_id = :t AND context_id = :m AND subject_bio_key = :k AND person_id IS NULL',
            ['p' => $personId, 't' => $tenantId, 'm' => $mapId, 'k' => $bioKey]
        );
    }

    private function rememberIdentity(int $tenantId, int $mapId, string $bioKey, int $identityId, string $subjectSteam): void
    {
        $identity = $this->db->fetchOne(
            'SELECT i.id, i.last_name FROM sse_identities i JOIN users u ON u.id = i.user_id
             WHERE i.id = :i AND i.tenant_id = :t AND u.steam_id = :s',
            ['i' => $identityId, 't' => $tenantId, 's' => $subjectSteam]
        );
        if ($identity === null) {
            return;
        }
        $this->db->execute('UPDATE sse_identities SET bio_key = :k WHERE id = :i AND tenant_id = :t', ['k' => $bioKey, 'i' => $identityId, 't' => $tenantId]);
        // Contrôles précédents sans papier du registre : seulement ceux présentés sous le nom de cette identité.
        $this->db->execute(
            'UPDATE sse_id_checks SET identity_id = :i WHERE tenant_id = :t AND context_id = :m AND subject_bio_key = :k
                AND identity_id IS NULL AND document_id IS NULL AND presented_last = :l',
            ['i' => $identityId, 't' => $tenantId, 'm' => $mapId, 'k' => $bioKey, 'l' => (string) $identity['last_name']]
        );
    }

    /** @return list<string> */
    private function keysOfPerson(int $tenantId, int $personId): array
    {
        $keys = [];
        $own = $this->db->fetchOne('SELECT bio_key FROM sse_persons WHERE id = :p AND tenant_id = :t', ['p' => $personId, 't' => $tenantId]);
        if ($own !== null && (string) ($own['bio_key'] ?? '') !== '') {
            $keys[] = (string) $own['bio_key'];
        }
        foreach ($this->db->fetchAll(
            'SELECT DISTINCT subject_bio_key AS k FROM sse_id_checks WHERE tenant_id = :t AND person_id = :p AND subject_bio_key IS NOT NULL LIMIT ' . self::MAX_SUGGESTIONS,
            ['t' => $tenantId, 'p' => $personId]
        ) as $r) {
            if ((string) $r['k'] !== '') {
                $keys[] = (string) $r['k'];
            }
        }

        return array_values(array_unique($keys));
    }

    /**
     * @param list<string> $keys
     * @return array{0: string, 1: array<string, string>}
     */
    private static function keyClause(array $keys, string $prefix): array
    {
        $marks = [];
        $params = [];
        foreach ($keys as $i => $k) {
            $marks[] = ':' . $prefix . $i;
            $params[$prefix . $i] = $k;
        }

        return [implode(', ', $marks), $params];
    }

    private function personsReady(): bool
    {
        if ($this->personsReady === null) {
            try {
                $this->personsReady = (new SchemaInspector(Database::getPdo()))->columnExists('sse_persons', 'bio_key');
            } catch (\Throwable) {
                $this->personsReady = false;
            }
        }

        return $this->personsReady;
    }
}
