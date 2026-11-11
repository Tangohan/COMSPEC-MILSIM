<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Core\Database;
use App\Repositories\SseFileRecordRepository;
use App\Repositories\SseFileRepository;
use App\Repositories\SseIdCheckRepository;
use App\Repositories\SseIdDocumentRepository;
use App\Repositories\SseIdentityRepository;
use App\Repositories\SseLoreRepository;
use App\Repositories\SsePersonRepository;
use App\Support\SseFileCatalog;
use App\Support\SseFileSubjectCatalog as S;
use App\Support\SseFileText;
use App\Support\SseFileVisibility as V;
use App\Support\SseRecordsCountryPacks;
use App\Support\SseRecordsFormat;

/**
 * Fichiers SSE — fiche sujet complète, telle qu'un terminal de police la rendrait : identité et alias, signalement,
 * photos, papiers délivrés, adresses, véhicules, téléphones, personnes liées, mentions (recherché, mandat, BOLO, HVT,
 * surveillance, prudence), contacts antérieurs (contrôles et consultations), recoupements, doublons possibles,
 * extractions de téléphones, annotations et message brut façon fichier.
 *
 * Vue opérateur : seules les données connues des fichiers et déjà révélées (SseFileVisibility). Vue MJ : tout, avec
 * le réglage de visibilité de chaque donnée.
 */
final class SseFileRecordService
{
    private Database $db;

    public function __construct(
        private ?SseFileRecordRepository $records = null,
        private ?SseIdentityRepository $identities = null,
        private ?SseIdDocumentRepository $documents = null,
        private ?SseFileRepository $files = null,
        private ?SseIdCheckRepository $checks = null,
        private ?SseLoreRepository $lore = null,
        private ?SseFileXrefService $xref = null,
        private ?string $now = null,
    ) {
        $this->records ??= new SseFileRecordRepository();
        $this->identities ??= new SseIdentityRepository();
        $this->documents ??= new SseIdDocumentRepository();
        $this->files ??= new SseFileRepository();
        $this->checks ??= new SseIdCheckRepository();
        $this->lore ??= new SseLoreRepository();
        $this->xref ??= new SseFileXrefService($this->records);
        $this->db = $this->records->db();
        $this->now ??= gmdate('Y-m-d H:i:s');
    }

    /**
     * @param array{gm?: bool, can_see?: \Closure(string): bool, terminal_country?: string} $viewer
     * @return array<string, mixed>|null null : fiche absente, falsifiée, ou hors fichiers pour un opérateur
     */
    public function record(int $tenantId, int $identityId, array $viewer = []): ?array
    {
        $gm = !empty($viewer['gm']);
        $canSee = $viewer['can_see'] ?? static fn (string $scope): bool => $scope === 'interne';
        $identity = $this->identities->find($identityId, $tenantId);
        if ($identity === null || $identity['kind'] === 'forged') {
            return null;
        }
        $rawProfile = $this->records->profile($tenantId, $identityId);
        if (!$gm && !V::inFiles($rawProfile)) {
            return null;
        }
        $phases = $this->lore->phases($tenantId);
        $phase = $gm ? V::NO_SESSION_PHASE : V::phaseOf($rawProfile, $phases);
        $profile = $gm ? $rawProfile : V::operatorProfile($rawProfile, $phase);
        $ident = $gm ? $identity : V::operatorIdentity($identity, $rawProfile, $phase);
        $keepRow = static fn (array $r): bool => $gm || V::row($r, $phase);

        $aliases = array_values(array_filter($this->records->aliasesFor($tenantId, [$identityId])[$identityId] ?? [], static fn (array $a): bool => $a['kind'] !== 'legal' && $keepRow($a)));
        $links = array_values(array_filter($this->records->linksFor($tenantId, [$identityId])[$identityId] ?? [], $keepRow));
        $photoAllowed = $gm || V::field($rawProfile, 'photo', $phase);
        $photos = $photoAllowed ? array_values(array_filter($this->records->photosFor($tenantId, [$identityId])[$identityId] ?? [], $keepRow)) : [];
        $byKind = ['address' => [], 'vehicle' => [], 'phone' => [], 'email' => [], 'associate' => []];
        foreach ($links as $l) {
            $byKind[$l['kind']][] = $l;
        }
        $associateIds = array_values(array_filter(array_map(static fn (array $l): int => (int) $l['linked_identity_id'], $byKind['associate'])));
        foreach ($this->records->backLinks($tenantId, $identityId) as $bl) {
            if ($keepRow($bl) && !in_array($bl['identity_id'], $associateIds, true)) {
                $byKind['associate'][] = $bl + ['reverse' => true];
                $associateIds[] = $bl['identity_id'];
            }
        }
        $associates = $this->identities->findMany($tenantId, $associateIds);

        // Papiers délivrés (jamais un faux), mentions, contrôles, fiches SEEK II, consultations.
        $docs = [];
        if ($gm || V::field($rawProfile, 'documents', $phase)) {
            foreach ($this->documents->list($tenantId, ['identity_id' => $identityId, 'limit' => 20]) as $d) {
                if (in_array($d['workflow'] ?? '', ['issued', 'revoked'], true) && ($d['st_origin'] ?? '') !== 'forged') {
                    $state = SseFileQueryService::docState($d);
                    $docs[] = $d + ['state' => $state, 'state_label' => SseFileCatalog::DOC_STATES[$state]];
                }
            }
        }
        $docIds = array_map(static fn (array $d): int => (int) $d['id'], $docs);
        $mentions = $this->safe(fn (): array => $this->files->activeMentionsFor($tenantId, [$identityId], $docIds));
        $checks = $this->safe(fn (): array => $this->checks->listForSubject($tenantId, [$identityId], $docIds, '', 100));
        $seek = $this->safe(fn (): array => $this->files->seekFor($tenantId, ['identity_ids' => [$identityId], 'limit' => 10]));
        $queries = $this->safe(fn (): array => $this->db->fetchAll(
            'SELECT * FROM sse_file_queries WHERE tenant_id = :t AND identity_id = :i ORDER BY id DESC LIMIT 50',
            ['t' => $tenantId, 'i' => $identityId]
        ));
        $queries = array_map([SseFileRepository::class, 'hydrateQuery'], $queries);

        $flags = $this->flags($tenantId, $identity, $profile, $mentions, $byKind['vehicle'], $docs);
        $extractions = $this->extractionsFor($tenantId, $identityId, array_map(static fn (array $l): string => (string) $l['value_norm'], $byKind['phone']));

        $country = SseRecordsCountryPacks::normalize($profile['country'] ?? $identity['nationality'] ?? ($viewer['terminal_country'] ?? null));
        $terminal = SseRecordsCountryPacks::normalize($viewer['terminal_country'] ?? null);

        $record = [
            'identity' => $ident,
            'profile' => $profile,
            'raw_profile' => $gm ? $rawProfile : null,
            'phase' => $phase,
            'aliases' => $aliases,
            'links' => $byKind,
            'associates' => $associates,
            'photos' => $photos,
            'documents' => $docs,
            'mentions' => $mentions,
            'flags' => $flags,
            'checks' => $checks,
            'seek' => $seek,
            'queries' => $queries,
            'contacts' => $this->priorContacts($checks, $queries, $seek),
            'notes' => $this->records->notesFor($tenantId, $identityId),
            'extractions' => $extractions,
            'country' => $country,
            'terminal_country' => $terminal,
            'response' => $this->responseCode($terminal, $flags),
            'similar' => $this->similar($tenantId, $identityId, $ident, $aliases, $byKind, $gm, $phases),
        ];
        $record['xref'] = $this->xref->findFor($tenantId, $identityId, [
            'last' => (string) $ident['last_name'] !== 'INCONNU' ? (string) $ident['last_name'] : '',
            'first' => (string) $ident['first_name'],
            'aliases' => array_map(static fn (array $a): string => (string) $a['alias'], $aliases),
            'phones' => array_map(static fn (array $l): string => (string) $l['value'], $byKind['phone']),
            'numbers' => array_merge(
                array_map(static fn (array $d): string => (string) $d['doc_number'], $docs),
                array_map(static fn (array $l): string => (string) $l['value'], $byKind['vehicle'])
            ),
        ], $canSee, ['skip_identity' => $identityId, 'skip_extractions' => array_map(static fn (array $e): int => (int) $e['id'], array_filter($extractions, static fn (array $e): bool => (int) ($e['identity_id'] ?? 0) === $identityId))]);
        $record['raw'] = $this->rawMessage($record);

        return $record;
    }

    /**
     * Mentions de la fiche, de la plus grave à la moins grave : mandat et recherche (mentions), HVT (fiche personne
     * prioritaire), liste de surveillance, codes de prudence, véhicule volé, papier volé ou perdu.
     *
     * @param array<string, mixed> $identity
     * @param array<string, mixed>|null $profile
     * @param list<array<string, mixed>> $mentions
     * @param list<array<string, mixed>> $vehicles
     * @param list<array<string, mixed>> $docs
     * @return list<array{code: string, label: string, tone: string}>
     */
    private function flags(int $tenantId, array $identity, ?array $profile, array $mentions, array $vehicles, array $docs): array
    {
        $out = [];
        $add = static function (string $code, string $label, string $tone) use (&$out): void {
            foreach ($out as $f) {
                if ($f['code'] === $code) {
                    return;
                }
            }
            $out[] = ['code' => $code, 'label' => $label, 'tone' => $tone];
        };
        foreach ($mentions as $m) {
            $code = (string) $m['mention'];
            $add($code, SseFileCatalog::label(SseFileCatalog::MENTIONS, $code), in_array($code, SseFileCatalog::MENTIONS_WANTED, true) ? 'red' : ($code === 'caution' ? 'amber' : 'blue'));
        }
        if (!in_array($identity['kind'], ['cover', 'forged'], true) && (int) ($identity['person_id'] ?? 0) > 0) {
            try {
                $p = (new SsePersonRepository())->findById((int) $identity['person_id'], $tenantId);
                if (is_array($p) && SsePersonRepository::normalizeStatus((string) ($p['status'] ?? '')) === SsePersonRepository::STATUS_PRIORITAIRE) {
                    $add('hvt', 'Cible prioritaire (HVT)', 'red');
                }
            } catch (\Throwable) {
                // Fiche personne indisponible : pas de mention HVT.
            }
        }
        try {
            $hits = (new SseCrossMatchService())->matchOne(['last_name' => $identity['last_name'], 'first_name' => $identity['first_name'], 'display_name' => trim($identity['first_name'] . ' ' . $identity['last_name'])], $tenantId);
            if (is_array($hits[0]['entry'] ?? null)) {
                $add('watchlist', ($hits[0]['entry']['threat_level'] ?? '') === 'prioritaire' ? 'Liste de surveillance (prioritaire)' : 'Liste de surveillance', 'blue');
            }
        } catch (\Throwable) {
            // Liste de surveillance indisponible.
        }
        foreach ($profile['caution_codes'] ?? [] as $c) {
            $add('caution_' . $c, S::label(S::CAUTIONS, $c), in_array($c, ['armed', 'violent', 'explosives', 'officer_safety'], true) ? 'red' : 'amber');
        }
        foreach ($vehicles as $v) {
            if ($v['flag'] === 'stolen') {
                $add('vehicle_stolen', 'Véhicule volé · ' . $v['value'], 'red');
            } elseif ($v['flag'] !== null) {
                $add('vehicle_' . $v['flag'], 'Véhicule ' . mb_strtolower((string) $v['flag_label']) . ' · ' . $v['value'], 'amber');
            }
        }
        foreach ($docs as $d) {
            if (in_array($d['state'], ['stolen', 'lost'], true)) {
                $add('doc_' . $d['state'], 'Papier ' . mb_strtolower($d['state_label']) . ' · ' . $d['doc_number'], 'amber');
            }
        }
        $rank = ['red' => 0, 'amber' => 1, 'blue' => 2];
        usort($out, static fn (array $a, array $b): int => $rank[$a['tone']] <=> $rank[$b['tone']]);

        return $out;
    }

    /**
     * Code de réponse du terminal (vocabulaire du pays du terminal) : HIT si une mention de recherche est active.
     *
     * @param list<array{code: string, label: string, tone: string}> $flags
     * @return array{code: string, text: string, tone: string, system: string}
     */
    public function responseCode(string $terminalCountry, array $flags): array
    {
        $pack = SseRecordsCountryPacks::get($terminalCountry);
        $wanted = array_filter($flags, static fn (array $f): bool => $f['tone'] === 'red');

        return $wanted !== []
            ? ['code' => 'hit', 'text' => (string) $pack['records']['hit'], 'tone' => 'red', 'system' => (string) $pack['records']['system']]
            : ['code' => 'record', 'text' => 'RECORD ON FILE', 'tone' => 'green', 'system' => (string) $pack['records']['system']];
    }

    /**
     * Contacts antérieurs du sujet (contrôles, consultations, SEEK II), du plus récent au plus ancien, avec l'ancienneté
     * lisible : « il y a 3 jours ».
     *
     * @param list<array<string, mixed>> $checks
     * @param list<array<string, mixed>> $queries
     * @param list<array<string, mixed>> $seek
     * @return array{total: int, last: ?array<string, mixed>, list: list<array<string, mixed>>}
     */
    public function priorContacts(array $checks, array $queries, array $seek): array
    {
        $list = [];
        foreach ($checks as $c) {
            $list[] = [
                'kind' => 'check', 'label' => 'Contrôle d’identité', 'ref' => (string) $c['ref'], 'at' => (string) $c['created_at'],
                'where' => (string) ($c['grid'] ?? ''), 'who' => (string) ($c['controller_callsign'] ?? ''), 'outcome' => (string) ($c['verdict_label'] ?? ''),
                'game_time' => (string) ($c['game_time'] ?? ''),
            ];
        }
        foreach ($seek as $s) {
            $list[] = [
                'kind' => 'seek', 'label' => 'Relevé SEEK II', 'ref' => (string) $s['rec'], 'at' => (string) $s['created_at'],
                'where' => (string) ($s['grid'] ?? ''), 'who' => (string) ($s['controller_callsign'] ?? ''), 'outcome' => (string) ($s['verdict_label'] ?? ''),
                'game_time' => (string) ($s['game_time'] ?? ''),
            ];
        }
        foreach ($queries as $q) {
            if (($q['source'] ?? '') === 'watch') {
                continue;
            }
            $list[] = [
                'kind' => 'query', 'label' => 'Consultation des fichiers', 'ref' => (string) $q['qrn'], 'at' => (string) $q['created_at'],
                'where' => (string) ($q['grid'] ?? ''), 'who' => (string) ($q['operator_label'] ?? ''), 'outcome' => $q['source_label'] ?? '',
                'game_time' => (string) ($q['game_time'] ?? ''),
            ];
        }
        usort($list, static fn (array $a, array $b): int => strcmp($b['at'], $a['at']));
        foreach ($list as $k => $c) {
            $list[$k]['ago'] = self::ago($c['at'], (string) $this->now);
        }
        $lastCheck = null;
        foreach ($list as $c) {
            if ($c['kind'] !== 'query') {
                $lastCheck = $c;
                break;
            }
        }

        return ['total' => count(array_filter($list, static fn (array $c): bool => $c['kind'] !== 'query')), 'last' => $lastCheck, 'list' => array_slice($list, 0, 60)];
    }

    /** Ancienneté lisible d'une date UTC : « à l'instant », « il y a 12 min », « il y a 3 h », « il y a 3 jours »… */
    public static function ago(string $at, string $now): string
    {
        $t = strtotime($at . (preg_match('/[zZ]|[+\-]\d\d:?\d\d$/', $at) === 1 ? '' : ' UTC'));
        $n = strtotime($now . ' UTC');
        if ($t === false || $n === false) {
            return '';
        }
        $d = max(0, $n - $t);

        return match (true) {
            $d < 60 => 'à l’instant',
            $d < 3600 => 'il y a ' . intdiv($d, 60) . ' min',
            $d < 86400 => 'il y a ' . intdiv($d, 3600) . ' h',
            $d < 86400 * 2 => 'hier',
            $d < 86400 * 31 => 'il y a ' . intdiv($d, 86400) . ' jours',
            $d < 86400 * 365 => 'il y a ' . intdiv($d, 86400 * 30) . ' mois',
            default => 'il y a ' . intdiv($d, 86400 * 365) . ' an' . (intdiv($d, 86400 * 365) > 1 ? 's' : ''),
        };
    }

    /**
     * Fiches proches : même téléphone, même adresse, même véhicule, alias proche, et doublons possibles (même nom à la
     * prononciation et même date de naissance, ou même papier).
     *
     * @param array<string, mixed> $ident
     * @param list<array<string, mixed>> $aliases
     * @param array<string, list<array<string, mixed>>> $byKind
     * @param array<int, int> $phases
     * @return list<array{identity_id: int, name: string, reason: string, confidence: string, duplicate: bool}>
     */
    private function similar(int $tenantId, int $identityId, array $ident, array $aliases, array $byKind, bool $gm, array $phases): array
    {
        $out = [];
        $add = static function (int $iid, string $reason, string $confidence, bool $dup) use (&$out, $identityId): void {
            if ($iid === $identityId || $iid < 1) {
                return;
            }
            if (!isset($out[$iid]) || ($dup && !$out[$iid]['duplicate'])) {
                $out[$iid] = ['identity_id' => $iid, 'reason' => $reason, 'confidence' => $confidence, 'duplicate' => $dup];
            }
        };
        foreach (['phone' => 'Même téléphone', 'address' => 'Même adresse', 'vehicle' => 'Même véhicule', 'email' => 'Même courriel'] as $kind => $label) {
            foreach ($byKind[$kind] as $l) {
                foreach ($this->records->linksByValue($tenantId, $kind, (string) $l['value_norm']) as $o) {
                    $add($o['identity_id'], $label . ' (' . $l['value'] . ')', 'exact', false);
                }
            }
        }
        // Alias proche (prononciation) d'un alias d'une autre fiche.
        foreach (array_slice($aliases, 0, 8) as $a) {
            foreach ($this->records->aliasCandidates($tenantId, [], [(string) $a['phonetic']], 50) as $o) {
                if ($o['kind'] !== 'legal' && SseFileText::similarity((string) $a['norm'], (string) $o['norm']) >= 78) {
                    $add($o['identity_id'], 'Alias proche : « ' . $o['alias'] . ' »', 'probable', false);
                }
            }
        }
        // Doublons : même nom (prononciation) et même date de naissance.
        $legal = SseFileText::norm(trim($ident['last_name'] . ' ' . $ident['first_name']));
        if ($legal !== '' && $ident['last_name'] !== 'INCONNU') {
            foreach ($this->records->aliasCandidates($tenantId, [], [SseFileText::phonetic((string) $ident['last_name'])], 200) as $o) {
                if ($o['kind'] !== 'legal' || (int) $o['identity_id'] === $identityId) {
                    continue;
                }
                $sim = SseFileText::similarity($legal, (string) $o['norm']);
                if ($sim < 78) {
                    continue;
                }
                $other = $this->identities->find((int) $o['identity_id'], $tenantId);
                if ($other === null || $other['kind'] === 'forged') {
                    continue;
                }
                $sameDob = $ident['birth_date'] !== null && $ident['birth_date'] === $other['birth_date'];
                if ($sameDob || $sim >= 95) {
                    $add((int) $other['id'], $sameDob ? 'Même nom et même date de naissance' : 'Même nom', $sameDob ? 'exact' : 'probable', $sameDob);
                }
            }
        }
        if ($out === []) {
            return [];
        }
        $others = $this->identities->findMany($tenantId, array_keys($out));
        $profiles = $this->records->profiles($tenantId, array_keys($out));
        $list = [];
        foreach ($out as $iid => $s) {
            $o = $others[$iid] ?? null;
            if ($o === null || $o['kind'] === 'forged' || (!$gm && !V::inFiles($profiles[$iid] ?? null))) {
                continue;
            }
            $o = $gm ? $o : V::operatorIdentity($o, $profiles[$iid] ?? null, V::phaseOf($profiles[$iid] ?? null, $phases));
            $list[] = $s + ['name' => (string) $o['display_name'], 'ref' => (string) $o['ref'], 'birth_date' => $o['birth_date']];
        }

        return array_slice($list, 0, 20);
    }

    /**
     * Extractions rattachées au sujet, ou dont le téléphone de l'appareil porte un numéro du sujet.
     *
     * @param list<string> $phoneNorms
     * @return list<array<string, mixed>>
     */
    private function extractionsFor(int $tenantId, int $identityId, array $phoneNorms): array
    {
        return $this->safe(function () use ($tenantId, $identityId, $phoneNorms): array {
            $rows = $this->db->fetchAll(
                "SELECT id, ref, device_label, owner_label, identity_id, created_at, counts_json FROM sse_phone_extractions
                 WHERE tenant_id = :t AND status = 'visible' AND identity_id = :i ORDER BY id DESC LIMIT 20",
                ['t' => $tenantId, 'i' => $identityId]
            );
            $phoneNorms = array_values(array_filter($phoneNorms));
            if ($phoneNorms !== []) {
                [$in, $params] = SseFileRecordRepository::in('p', $phoneNorms);
                $params['t'] = $tenantId;
                $seen = array_column($rows, 'id');
                foreach ($this->db->fetchAll(
                    "SELECT DISTINCT e.id, e.ref, e.device_label, e.owner_label, e.identity_id, e.created_at, e.counts_json FROM sse_phone_extraction_items it
                     INNER JOIN sse_phone_extractions e ON e.id = it.extraction_id AND e.tenant_id = it.tenant_id
                     WHERE it.tenant_id = :t AND e.status = 'visible' AND it.number_norm IN ({$in}) ORDER BY e.id DESC LIMIT 20",
                    $params
                ) as $r) {
                    if (!in_array($r['id'], $seen, false)) {
                        $r['via_number'] = true;
                        $rows[] = $r;
                    }
                }
            }

            return $rows;
        });
    }

    /**
     * Message brut du terminal (copiable) : en-tête, avertissements, signalement aux formats du pays du sujet, alias,
     * mentions ; vocabulaire du pays du terminal.
     *
     * @param array<string, mixed> $r
     */
    public function rawMessage(array $r): string
    {
        $i = $r['identity'];
        $p = $r['profile'] ?? [];
        $pack = SseRecordsCountryPacks::get((string) $r['terminal_country']);
        $c = (string) $r['country'];
        $lines = [];
        $lines[] = strtoupper((string) $pack['records']['system']) . '.QW.' . $i['ref'] . '.NAM/' . strtoupper((string) $i['last_name']) . ',' . strtoupper((string) $i['first_name'])
            . '.SEX/' . ($i['sex'] ?? 'X') . ($i['birth_date'] ? '.DOB/' . str_replace('-', '', (string) $i['birth_date']) : '');
        $lines[] = '';
        foreach ($r['flags'] as $f) {
            if ($f['tone'] === 'red') {
                $lines[] = '*** ' . mb_strtoupper($f['label']) . ' ***';
            }
        }
        $lines[] = 'NAM/' . strtoupper((string) $i['last_name']) . ', ' . strtoupper((string) $i['first_name']) . '  SEX/' . ($i['sex'] ?? 'X')
            . ($i['nationality'] ? '  NAT/' . $i['nationality'] : '');
        $phys = [];
        if ($i['birth_date']) {
            $phys[] = 'DOB/' . SseRecordsFormat::date((string) $i['birth_date'], $c);
        }
        if (($p['height_cm'] ?? null) !== null) {
            $phys[] = 'HGT/' . SseRecordsFormat::height((int) $p['height_cm'], $c);
        }
        if (($p['weight_kg'] ?? null) !== null) {
            $phys[] = 'WGT/' . SseRecordsFormat::weight((int) $p['weight_kg'], $c);
        }
        if (($p['eyes'] ?? null) !== null) {
            $phys[] = 'EYE/' . (S::EYE_CODES[$p['eyes']] ?? strtoupper((string) $p['eyes']));
        }
        if (($p['hair'] ?? null) !== null) {
            $phys[] = 'HAI/' . (S::HAIR_CODES[$p['hair']] ?? strtoupper((string) $p['hair']));
        }
        if ($phys !== []) {
            $lines[] = implode('  ', $phys);
        }
        if ($r['aliases'] !== []) {
            $lines[] = 'AKA/' . mb_strtoupper(implode('; ', array_map(static fn (array $a): string => (string) $a['alias'], $r['aliases'])));
        }
        if (($p['marks'] ?? null) !== null) {
            $lines[] = 'SMT/' . mb_strtoupper((string) $p['marks']);
        }
        foreach ($r['mentions'] as $m) {
            $lines[] = 'MIS/' . mb_strtoupper((string) $m['mention_label']) . (($m['reference'] ?? '') !== '' ? ' · ' . $m['reference'] : '');
        }
        if ($r['contacts']['last'] !== null) {
            $lc = $r['contacts']['last'];
            $lines[] = 'LAST CONTACT/' . substr((string) $lc['at'], 0, 16) . 'Z' . ($lc['where'] !== '' ? ' ' . $lc['where'] : '') . ($lc['who'] !== '' ? ' BY ' . mb_strtoupper($lc['who']) : '');
        }
        $lines[] = '';
        $lines[] = $r['response']['code'] === 'hit' ? (string) $pack['records']['hit'] : 'CONFIRM WITH ORIGINATING AGENCY BEFORE ANY ACTION.';
        $lines[] = 'SIMULATION — FICTIONAL DATA';

        return implode("\n", $lines);
    }

    /** @return list<array<string, mixed>> */
    private function safe(callable $fn): array
    {
        try {
            $r = $fn();

            return is_array($r) ? array_values($r) : [];
        } catch (\PDOException $e) {
            error_log('Fichiers SSE : lecture partielle de la fiche : ' . $e->getMessage());

            return [];
        }
    }
}
