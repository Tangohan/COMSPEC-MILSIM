<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Repositories\SseIdCountryRepository;
use App\Repositories\SseIdDocumentRepository;
use App\Repositories\SseIdentityRepository;
use App\Repositories\SsePersonRepository;
use App\Repositories\SseWatchlistRepository;

/**
 * Pack d'identité lu par le serveur de jeu (COMSPEC Link GetIdentityPack → GET /api/sse/identity/pack).
 *
 * Contient la vérité de jeu (nature des identités, faux, titulaires) : il ne sert qu'au serveur, qui la garde pour
 * lui. Seuls les papiers émis partent ; au plus 500 par carte, paginés (offset / limit).
 */
final class SseIdentityPackService
{
    public const VERSION = 1;
    public const DEFAULT_LIMIT = 200;
    public const MAX_WATCH = 300;

    public function __construct(
        private ?SseIdDocumentRepository $documents = null,
        private ?SseIdentityRepository $identities = null,
        private ?SseIdCountryRepository $countries = null,
        private ?SseWatchlistRepository $watchlist = null,
        private ?SsePersonRepository $persons = null,
    ) {
        $this->documents ??= new SseIdDocumentRepository();
        $this->identities ??= new SseIdentityRepository();
        $this->countries ??= new SseIdCountryRepository();
        $this->watchlist ??= new SseWatchlistRepository();
        $this->persons ??= new SsePersonRepository();
    }

    /**
     * @return array<string, mixed>
     */
    public function build(int $tenantId, int $mapId, ?string $opKey = null, ?string $since = null, int $offset = 0, int $limit = self::DEFAULT_LIMIT): array
    {
        $opKey = $opKey !== null && trim($opKey) !== '' ? mb_substr(trim($opKey), 0, 64) : null;
        $since = self::normalizeSince($since);
        $limit = max(1, min(SseIdDocumentRepository::PACK_MAX_PER_MAP, $limit));
        $offset = max(0, $offset);

        $page = $this->documents->listForPack($tenantId, $mapId, $opKey, $since, $offset, $limit);
        $docs = $page['rows'];
        $identityRows = $this->identities->findMany($tenantId, array_map(static fn (array $d): int => (int) $d['identity_id'], $docs));

        $countries = [];
        foreach ($this->countries->listForTenant($tenantId) as $c) {
            $countries[] = [
                'code' => $c['code'],
                'name' => $c['name'],
                'mrz_code' => $c['mrz_code'],
                'number_format' => $c['number_format'],
                'fictional' => $c['fictional'],
            ];
        }

        $identities = [];
        foreach ($identityRows as $i) {
            $identities[] = [
                'id' => $i['id'],
                'person_id' => $i['person_id'],
                'kind' => $i['kind'],
                'last_name' => $i['last_name'],
                'first_name' => $i['first_name'],
                'sex' => $i['sex'],
                'birth_date' => $i['birth_date'],
                'birth_place' => $i['birth_place'],
                'nationality' => $i['nationality'],
                'arma_key' => $i['arma_key'],
                'profession' => $i['profession'],
            ];
        }

        $documents = [];
        foreach ($docs as $d) {
            $documents[] = [
                'id' => $d['id'],
                'identity_id' => $d['identity_id'],
                'holder_person_id' => $d['holder_person_id'] ?? $d['identity']['person_id'],
                'type' => $d['doc_type'],
                'country' => $d['country_code'],
                'number' => $d['doc_number'],
                'issued_on' => $d['issued_on'],
                'expires_on' => $d['expires_on'],
                'authority' => $d['authority'],
                'chip' => $d['chip_status'],
                'origin' => $d['st_origin'],
                'integrity' => $d['st_integrity'],
                'validity' => $d['st_validity'],
                'report' => $d['st_report'],
                'forgery_kind' => $d['forgery_kind'],
                'forgery_quality' => $d['forgery_quality'],
            ];
        }

        $next = $offset + count($docs);
        $pack = [
            'version' => self::VERSION,
            'generated_at' => gmdate('Y-m-d\TH:i:s\Z'),
            'map' => $mapId,
            'op' => $opKey,
            'countries' => $countries,
            'identities' => $identities,
            'documents' => $documents,
            'watch' => $offset === 0 ? $this->watch($tenantId, $mapId) : [],
            'paging' => [
                'offset' => $offset,
                'limit' => $limit,
                'total' => $page['total'],
                'next_offset' => $next < $page['total'] && $docs !== [] ? $next : null,
            ],
        ];
        if ($since !== null) {
            $pack['since'] = $since;
            $pack['revoked'] = $offset === 0 ? $this->documents->revokedSince($tenantId, $mapId, $since) : [];
        }

        return $pack;
    }

    /**
     * Listes de surveillance actives + fiches personnes prioritaires (niveau « hvt »).
     *
     * @return list<array<string, mixed>>
     */
    private function watch(int $tenantId, int $mapId): array
    {
        $out = [];
        foreach ($this->watchlist->listActive($tenantId) as $e) {
            $out[] = [
                'ref' => 'WL-' . (int) $e['id'],
                'last_name' => (string) $e['last_name'],
                'first_name' => (string) $e['first_name'],
                'alias' => (string) ($e['alias'] ?? ''),
                'level' => $e['threat_level'] === 'prioritaire' ? 'prioritaire' : 'surveillance',
                'person_id' => null,
            ];
            if (count($out) >= self::MAX_WATCH) {
                return $out;
            }
        }
        foreach ($this->persons->listForContext($tenantId, $mapId, ['status' => SsePersonRepository::STATUS_PRIORITAIRE, 'limit' => self::MAX_WATCH]) as $p) {
            $out[] = [
                'ref' => 'IDN-' . str_pad((string) (int) $p['id'], 5, '0', STR_PAD_LEFT),
                'last_name' => (string) ($p['last_name'] ?? ''),
                'first_name' => (string) ($p['first_name'] ?? ''),
                'alias' => (string) ($p['alias'] ?? ''),
                'level' => 'hvt',
                'person_id' => (int) $p['id'],
            ];
            if (count($out) >= self::MAX_WATCH) {
                break;
            }
        }

        return $out;
    }

    /** « since » accepté en ISO 8601 ou AAAA-MM-JJ HH:MM:SS, ramené en UTC base de données ; sinon ignoré. */
    public static function normalizeSince(?string $since): ?string
    {
        $since = trim((string) $since);
        if ($since === '' || strlen($since) > 40) {
            return null;
        }
        try {
            $dt = new \DateTimeImmutable($since, new \DateTimeZone('UTC'));
        } catch (\Exception) {
            return null;
        }

        return $dt->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
}
