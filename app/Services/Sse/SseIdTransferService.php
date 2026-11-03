<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Repositories\SseIdCountryRepository;
use App\Repositories\SseIdDocumentRepository;
use App\Repositories\SseIdentityRepository;
use App\Support\SseIdentityCatalog;

/**
 * Import et export des identités et des papiers (COMSPEC IDENTITY, Lot 6), en CSV (une ligne par papier, ou par
 * identité sans papier) ou en JSON.
 *
 * Export : la vérité de jeu (nature, origine, falsification) n'est écrite qu'avec l'habilitation Confidentiel.
 * Import : tout arrive en brouillon (rien ne part en jeu sans validation) ; pays inconnu, numéro hors format ou déjà
 * attribué = ligne écartée ; couverture et faux seulement avec l'habilitation Confidentiel.
 */
final class SseIdTransferService
{
    public const MAX_ROWS = 1000;

    /** Colonnes du CSV, dans l'ordre. */
    public const COLUMNS = [
        'identity_ref', 'kind', 'last_name', 'first_name', 'sex', 'birth_date', 'birth_place', 'nationality', 'profession', 'alias',
        'category', 'side', 'faction', 'language', 'zone', 'document_ref', 'doc_type', 'country', 'number', 'issued_on', 'expires_on',
        'authority', 'workflow', 'origin', 'integrity', 'validity', 'report', 'chip', 'forgery_kind', 'forgery_quality', 'map', 'op',
    ];

    /** Colonnes réservées à l'habilitation Confidentiel. */
    private const TRUTH_COLUMNS = ['kind', 'origin', 'forgery_kind', 'forgery_quality'];

    public function __construct(
        private ?SseIdentityRepository $identities = null,
        private ?SseIdDocumentRepository $documents = null,
        private ?SseIdCountryRepository $countries = null,
    ) {
        $this->identities ??= new SseIdentityRepository();
        $this->documents ??= new SseIdDocumentRepository();
        $this->countries ??= new SseIdCountryRepository();
    }

    /**
     * Lignes à exporter (une par papier ; une identité sans papier donne une ligne sans papier).
     *
     * @param array{op?: string, map?: int} $filters
     * @return list<array<string, string>>
     */
    public function rows(int $tenantId, bool $truth, array $filters = []): array
    {
        $op = trim((string) ($filters['op'] ?? ''));
        $map = (int) ($filters['map'] ?? 0);
        $out = [];
        $seen = [];
        $identities = $this->identities->list($tenantId, ['limit' => 500]);
        $byId = [];
        foreach ($identities as $i) {
            $byId[$i['id']] = $i;
        }
        foreach ($this->documents->list($tenantId, ['limit' => 500]) as $d) {
            if (($op !== '' && $d['op_key'] !== $op) || ($map > 0 && $d['context_id'] !== $map)) {
                continue;
            }
            $i = $byId[$d['identity_id']] ?? $this->identities->find((int) $d['identity_id'], $tenantId);
            if ($i === null || (!$truth && MemberIdentityPapersService::docIsSecret($d))) {
                continue;
            }
            $seen[$i['id']] = true;
            $out[] = $this->row($i, $d, $truth);
        }
        foreach ($identities as $i) {
            if (isset($seen[$i['id']]) || ($op !== '' && $i['op_key'] !== $op) || ($map > 0 && $i['context_id'] !== $map)) {
                continue;
            }
            if (!$truth && in_array($i['kind'], MemberIdentityPapersService::SECRET, true)) {
                continue;
            }
            $out[] = $this->row($i, null, $truth);
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $i
     * @param array<string, mixed>|null $d
     * @return array<string, string>
     */
    private function row(array $i, ?array $d, bool $truth): array
    {
        $r = [
            'identity_ref' => $i['ref'], 'kind' => $i['kind'], 'last_name' => $i['last_name'], 'first_name' => $i['first_name'],
            'sex' => $i['sex'], 'birth_date' => $i['birth_date'], 'birth_place' => $i['birth_place'], 'nationality' => $i['nationality'],
            'profession' => $i['profession'], 'alias' => $i['alias'] ?? '', 'category' => $i['category'] ?? '', 'side' => $i['side'] ?? '',
            'faction' => $i['faction'] ?? '', 'language' => $i['language'] ?? '', 'zone' => $i['zone'] ?? '',
            'document_ref' => $d['ref'] ?? '', 'doc_type' => $d['doc_type'] ?? '', 'country' => $d['country_code'] ?? '',
            'number' => $d['doc_number'] ?? '', 'issued_on' => $d['issued_on'] ?? '', 'expires_on' => $d['expires_on'] ?? '',
            'authority' => $d['authority'] ?? '', 'workflow' => $d['workflow'] ?? '', 'origin' => $d['st_origin'] ?? '',
            'integrity' => $d['st_integrity'] ?? '', 'validity' => $d['st_validity'] ?? '', 'report' => $d['st_report'] ?? '',
            'chip' => $d['chip_status'] ?? '', 'forgery_kind' => $d['forgery_kind'] ?? '', 'forgery_quality' => $d['forgery_quality'] ?? '',
            'map' => $d['context_id'] ?? $i['context_id'] ?? '', 'op' => $d['op_key'] ?? $i['op_key'] ?? '',
        ];
        if (!$truth) {
            foreach (self::TRUTH_COLUMNS as $c) {
                $r[$c] = '';
            }
        }

        return array_map(static fn (mixed $v): string => $v === null ? '' : (string) $v, $r);
    }

    /** @param list<array<string, string>> $rows */
    public static function toCsv(array $rows): string
    {
        $fh = fopen('php://temp', 'r+');
        if ($fh === false) {
            return '';
        }
        fputcsv($fh, self::COLUMNS, ';', '"', '');
        foreach ($rows as $r) {
            // Pas de formule interprétée par un tableur.
            fputcsv($fh, array_map(static fn (string $c): string => preg_match('/^[=+\-@]/', $c) === 1 ? "'" . $c : $c, array_map(static fn (string $c): string => $r[$c] ?? '', self::COLUMNS)), ';', '"', '');
        }
        rewind($fh);
        $csv = (string) stream_get_contents($fh);
        fclose($fh);

        return "\u{FEFF}" . $csv;
    }

    /** @param list<array<string, string>> $rows */
    public static function toJson(array $rows): string
    {
        return (string) json_encode([
            'format' => 'comspec-identity',
            'version' => 1,
            'simulation' => true,
            'exported_at' => gmdate('Y-m-d\TH:i:s\Z'),
            'rows' => $rows,
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    /**
     * Lit un fichier CSV (« ; » ou « , ») ou JSON (export de l'atelier, ou tableau de lignes).
     *
     * @return list<array<string, string>>|null null = fichier illisible
     */
    public static function parse(string $content, string $filename): ?array
    {
        $content = (string) preg_replace('/^\xEF\xBB\xBF/', '', $content);
        if (str_ends_with(strtolower($filename), '.json') || in_array(ltrim($content)[0] ?? '', ['{', '['], true)) {
            $data = json_decode($content, true);
            if (!is_array($data)) {
                return null;
            }
            $rows = isset($data['rows']) && is_array($data['rows']) ? $data['rows'] : $data;
            $out = [];
            foreach ($rows as $r) {
                if (is_array($r)) {
                    $out[] = array_map(static fn (mixed $v): string => is_scalar($v) ? (string) $v : '', $r);
                }
            }

            return $out;
        }
        $lines = preg_split('/\r\n|\n|\r/', $content) ?: [];
        $lines = array_values(array_filter($lines, static fn (string $l): bool => trim($l) !== ''));
        if ($lines === []) {
            return [];
        }
        $sep = substr_count($lines[0], ';') >= substr_count($lines[0], ',') ? ';' : ',';
        $head = array_map(static fn (?string $h): string => strtolower(trim((string) $h)), str_getcsv($lines[0], $sep, '"', ''));
        $out = [];
        foreach (array_slice($lines, 1) as $line) {
            $cells = str_getcsv($line, $sep, '"', '');
            $row = [];
            foreach ($head as $k => $col) {
                $row[$col] = ltrim(trim((string) ($cells[$k] ?? '')), "'");
            }
            $out[] = $row;
        }

        return $out;
    }

    /**
     * Importe des lignes : identités (regroupées par identity_ref, sinon nom + prénom + naissance) et papiers en
     * brouillon.
     *
     * @param list<array<string, string>> $rows
     * @return array{identities: int, documents: int, skipped: int, errors: list<string>}
     */
    public function import(int $tenantId, array $rows, bool $truth, ?int $actorId): array
    {
        $res = ['identities' => 0, 'documents' => 0, 'skipped' => 0, 'errors' => []];
        $countries = [];
        foreach ($this->countries->listForTenant($tenantId) as $c) {
            $countries[$c['code']] = $c;
        }
        $made = [];
        foreach (array_slice($rows, 0, self::MAX_ROWS) as $n => $r) {
            $line = $n + 2;
            $last = trim((string) ($r['last_name'] ?? ''));
            $first = trim((string) ($r['first_name'] ?? ''));
            if ($last === '' || $first === '') {
                $res['skipped']++;
                $res['errors'][] = 'Ligne ' . $line . ' : nom ou prénom manquant.';
                continue;
            }
            $kind = SseIdentityCatalog::pick(SseIdentityCatalog::KINDS, $r['kind'] ?? null, 'civil');
            if (!$truth && in_array($kind, MemberIdentityPapersService::SECRET, true)) {
                $kind = 'civil';
            }
            $key = ($r['identity_ref'] ?? '') !== '' ? 'r' . $r['identity_ref'] : 'n' . mb_strtolower($last . '|' . $first . '|' . ($r['birth_date'] ?? ''));
            if (!isset($made[$key])) {
                $made[$key] = $this->identities->create($tenantId, [
                    'kind' => $kind, 'last_name' => $last, 'first_name' => $first, 'sex' => $r['sex'] ?? 'X',
                    'birth_date' => $r['birth_date'] ?? '', 'birth_place' => $r['birth_place'] ?? '', 'nationality' => $r['nationality'] ?? '',
                    'profession' => $r['profession'] ?? '', 'alias' => $r['alias'] ?? '', 'category' => $r['category'] ?? '',
                    'side' => $r['side'] ?? '', 'faction' => $r['faction'] ?? '', 'language' => $r['language'] ?? '', 'zone' => $r['zone'] ?? '',
                    'context_id' => (int) ($r['map'] ?? 0), 'op_key' => $r['op'] ?? '',
                    'notes' => 'Importée',
                ], $actorId);
                $res['identities']++;
            }
            $code = strtoupper(trim((string) ($r['country'] ?? '')));
            if ($code === '' && trim((string) ($r['number'] ?? '')) === '') {
                continue;
            }
            $country = $countries[$code] ?? null;
            $type = SseIdentityCatalog::pick(SseIdentityCatalog::DOC_TYPES, $r['doc_type'] ?? null, 'id_card');
            $number = SseIdDocumentRepository::normalizeNumber((string) ($r['number'] ?? ''));
            if ($country === null || !in_array($type, $country['doc_types'], true)) {
                $res['skipped']++;
                $res['errors'][] = 'Ligne ' . $line . ' : pays inconnu ou type de papier non délivré.';
                continue;
            }
            if ($number === '' || !SseIdentityGeneratorService::matchesFormat($number, (string) $country['number_format']) || $this->documents->numberExists($tenantId, $code, $type, $number)) {
                $res['skipped']++;
                $res['errors'][] = 'Ligne ' . $line . ' : numéro hors format ou déjà attribué.';
                continue;
            }
            $origin = SseIdentityCatalog::pick(SseIdentityCatalog::ORIGINS, $r['origin'] ?? null, 'official');
            if (!$truth && in_array($origin, MemberIdentityPapersService::SECRET, true)) {
                $origin = 'official';
            }
            $this->documents->create($tenantId, [
                'identity_id' => $made[$key], 'doc_type' => $type, 'country_code' => $code, 'doc_number' => $number,
                'issued_on' => $r['issued_on'] ?? '', 'expires_on' => $r['expires_on'] ?? '', 'authority' => $r['authority'] ?? '',
                'st_origin' => $origin, 'st_integrity' => $r['integrity'] ?? '', 'st_validity' => $r['validity'] ?? '',
                'st_report' => $r['report'] ?? '', 'chip_status' => $r['chip'] ?? '',
                'forgery_kind' => $truth ? ($r['forgery_kind'] ?? '') : '', 'forgery_quality' => $truth ? ($r['forgery_quality'] ?? '') : '',
                'context_id' => (int) ($r['map'] ?? 0), 'op_key' => $r['op'] ?? '',
            ], $actorId);
            $res['documents']++;
        }
        if (count($rows) > self::MAX_ROWS) {
            $res['errors'][] = 'Seules les ' . self::MAX_ROWS . ' premières lignes ont été lues.';
        }
        SseIdMassGeneratorService::audit($tenantId, $actorId, 'id_transfer.import', 'sse_identity', 0, ['identities' => $res['identities'], 'documents' => $res['documents'], 'skipped' => $res['skipped']]);
        $res['errors'] = array_slice($res['errors'], 0, 20);

        return $res;
    }
}
