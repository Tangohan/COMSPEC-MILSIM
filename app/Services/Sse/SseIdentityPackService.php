<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Repositories\SseIdCountryRepository;
use App\Repositories\SseIdDocumentRepository;
use App\Repositories\SseIdentityRepository;
use App\Repositories\SseIdKitRepository;
use App\Repositories\SseIdOperationRepository;
use App\Repositories\SsePersonRepository;
use App\Repositories\SseWatchlistRepository;

/**
 * Pack d'identité lu par le serveur de jeu (COMSPEC Link GetIdentityPack → GET /api/sse/identity/pack).
 *
 * Contient la vérité de jeu (nature des identités, faux, titulaires) : il ne sert qu'au serveur, qui la garde pour
 * lui. Seuls les papiers émis partent ; au plus 500 par carte, paginés (offset / limit).
 *
 * Attribution d'un papier à un joueur : kits[] (première page seulement), un élément par joueur
 * {uid, user_id, scope, documents[]} ; uid = Steam ID du compte Athena, comparé par le serveur à getPlayerUID.
 * Les identifiants de documents[] renvoient à documents[].id (toutes pages confondues).
 *
 * Lot 6 (rétrocompatible, première page seulement) : population[] (identités générées pour la carte et l'opération,
 * à donner aux IA par camp, catégorie et zone au lieu d'en générer), wanted[] (recherchés et HVT de l'opération, camps
 * qui y ont accès) et briefs[] (fiche de couverture de chaque joueur, uid = Steam ID).
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
        private ?SseIdKitRepository $kits = null,
        private ?SseIdOperationRepository $operations = null,
    ) {
        $this->documents ??= new SseIdDocumentRepository();
        $this->identities ??= new SseIdentityRepository();
        $this->countries ??= new SseIdCountryRepository();
        $this->watchlist ??= new SseWatchlistRepository();
        $this->persons ??= new SsePersonRepository();
        $this->kits ??= new SseIdKitRepository();
        $this->operations ??= new SseIdOperationRepository();
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
        // Pays archivés compris : leurs papiers déjà émis restent en jeu.
        foreach ($this->countries->listForTenant($tenantId, false) as $c) {
            $countries[] = [
                'code' => $c['code'],
                'name' => $c['name'],
                'mrz_code' => $c['mrz_code'],
                'number_format' => $c['number_format'],
                'fictional' => $c['fictional'],
                'name_en' => $c['name_en'],
                'authority' => $c['authority'],
                'theme' => $c['theme'],
                'languages' => $c['languages'],
                'doc_types' => $c['doc_types'],
                'colors' => array_values(array_filter([$c['color_primary'], $c['color_secondary']])),
                'active' => $c['active'],
                // Ajouts rétrocompatibles : pays réel stylisé (univers réaliste-simulation), zone de style du gabarit
                // générique (europe|americas|other, vide pour un pays fictif), nom natif, années de validité par type.
                'real_sim' => $c['real_sim'],
                'zone' => $c['zone'],
                'name_native' => $c['real_sim'] ? (string) (\App\Support\SseIdRealCountryCatalog::get($c['code'])['name_native'] ?? '') : '',
                'validity' => $c['validity'],
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
            'kits' => $offset === 0 ? $this->kits->forPack($tenantId, $mapId, $opKey) : [],
            'population' => $offset === 0 ? $this->operations->populationForPack($tenantId, $mapId, $opKey) : [],
            'wanted' => $offset === 0 ? $this->wanted($tenantId, $mapId, $opKey) : [],
            'briefs' => $offset === 0 ? $this->operations->briefsForPack($tenantId, $mapId, $opKey) : [],
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
            $level = (string) ($e['threat_level'] ?? '');
            // Champs du contrat, puis (lots 4 et 5, ignorés par un ancien mod) l'origine en jeu : référence, visage,
            // papiers connus, étape de la chasse.
            $out[] = [
                'ref' => 'WL-' . (int) $e['id'],
                'last_name' => (string) $e['last_name'],
                'first_name' => (string) $e['first_name'],
                'alias' => (string) ($e['alias'] ?? ''),
                'level' => in_array($level, ['prioritaire', 'hvt'], true) ? $level : 'surveillance',
                'person_id' => null,
                'game_ref' => (string) ($e['game_ref'] ?? ''),
                'bio_key' => (string) ($e['bio_key'] ?? ''),
                'known_docs' => array_values($e['known_docs'] ?? []),
                'status' => (string) ($e['game_status'] ?? ''),
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
                'game_ref' => '',
                'bio_key' => '',
                'known_docs' => [],
                'status' => '',
            ];
            if (count($out) >= self::MAX_WATCH) {
                break;
            }
        }

        return $out;
    }

    /**
     * Recherchés et HVT de l'opération : sides = camps qui y ont accès (vide = tous), documents = papiers émis connus.
     *
     * @return list<array<string, mixed>>
     */
    private function wanted(int $tenantId, int $mapId, ?string $opKey): array
    {
        $out = [];
        foreach ($this->operations->wantedForPack($tenantId, $mapId, $opKey) as $w) {
            $out[] = [
                'ref' => $w['ref'],
                'identity_id' => $w['identity_id'],
                'person_id' => $w['person_id'],
                'last_name' => $w['last_name'],
                'first_name' => $w['first_name'],
                'alias' => $w['alias'],
                'level' => $w['level'],
                'sides' => $w['sides'],
                'zone' => $w['zone'],
                'documents' => $w['documents'],
                'has_photo' => $w['photo_path'] !== null,
            ];
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
