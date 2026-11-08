<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Repositories\SseIdCheckRepository;
use App\Repositories\SseIdDocumentRepository;
use App\Repositories\SsePersonRepository;
use App\Support\SseIdentityCatalog;

/**
 * Enregistrement d'un contrôle d'identité remonté du terrain (COMSPEC Link SubmitIdCheck → POST /api/sse/identity/checks).
 *
 * Idempotent par clé. Rattachement : papier du registre (identifiant de pack ou pays + type + numéro), puis personne
 * (identifiant de fiche fourni par le mod, sinon fiche de la même unité de jeu sur la carte), puis personne ou identité
 * de même clé biométrique (SseIdSubjectMatchService). Jamais de fusion de fiches.
 */
final class SseIdCheckService
{
    public const MAX_RESULTS_BYTES = 8000;

    public function __construct(
        private ?SseIdCheckRepository $checks = null,
        private ?SseIdDocumentRepository $documents = null,
        private ?SsePersonRepository $persons = null,
        private ?SseIdSubjectMatchService $matches = null,
    ) {
        $this->checks ??= new SseIdCheckRepository();
        $this->documents ??= new SseIdDocumentRepository();
        $this->persons ??= new SsePersonRepository();
        $this->matches ??= new SseIdSubjectMatchService();
    }

    /** Clé d'idempotence acceptée : 4 à 80 caractères, lettres, chiffres et « _ . : - ». */
    public static function validKey(string $key): bool
    {
        return preg_match('/^[A-Za-z0-9_.:\-]{4,80}$/', $key) === 1;
    }

    /** Niveau « N0 » à « N4 » ; le mod envoie aussi le numéro seul (0 à 4). */
    public static function levelCode(mixed $level): ?string
    {
        if (!is_scalar($level)) {
            return null;
        }
        $l = strtoupper(trim((string) $level));
        if (preg_match('/^[0-4]$/', $l) === 1) {
            $l = 'N' . $l;
        }

        return isset(SseIdentityCatalog::LEVELS[$l]) ? $l : null;
    }

    /**
     * @param array<string, mixed> $body
     * @return array{id: int, ref: string, duplicate: bool}|array{error: string}
     */
    public function store(int $tenantId, int $mapId, array $body): array
    {
        $key = trim((string) ($body['idempotency_key'] ?? ''));
        if (!self::validKey($key)) {
            return ['error' => 'idempotency_key_required'];
        }
        $existing = $this->checks->findByKey($tenantId, $key);
        if ($existing !== null) {
            return ['id' => $existing['id'], 'ref' => $existing['ref'], 'duplicate' => true];
        }

        $str = static function (mixed $v, int $max): ?string {
            $s = trim(is_scalar($v) ? (string) $v : '');
            $s = str_replace(["\r", "\n", "\t"], ' ', $s);

            return $s === '' ? null : mb_substr($s, 0, $max);
        };
        $arr = static fn (mixed $v): array => is_array($v) ? $v : [];

        $document = $arr($body['document'] ?? null);
        $presented = $arr($body['presented_name'] ?? null);
        $subject = $arr($body['subject'] ?? null);
        $controller = $arr($body['controller'] ?? null);

        $type = SseIdentityCatalog::pick(SseIdentityCatalog::DOC_TYPES, $document['type'] ?? null, '');
        $country = strtoupper((string) $str($document['country'] ?? null, 3));
        $number = SseIdDocumentRepository::normalizeNumber((string) ($document['number'] ?? ''));

        // Papier du registre.
        $doc = null;
        $docId = (int) ($document['id'] ?? 0);
        if ($docId > 0) {
            $doc = $this->documents->find($docId, $tenantId);
        }
        if ($doc === null && $country !== '' && $number !== '') {
            foreach ($this->documents->list($tenantId, ['country_code' => $country, 'doc_type' => $type, 'q' => $number, 'limit' => 5]) as $candidate) {
                if ($candidate['doc_number'] === $number) {
                    $doc = $candidate;
                    break;
                }
            }
        }

        // Personne contrôlée.
        $personId = null;
        $pid = (int) ($subject['person_id'] ?? 0);
        if ($pid > 0 && $this->persons->findById($pid, $tenantId) !== null) {
            $personId = $pid;
        }
        $netid = $str($subject['netid'] ?? null, 64);
        if ($personId === null && $netid !== null) {
            $byNet = $this->persons->findLikelyDuplicate($tenantId, $mapId, ['target_unit_netid' => $netid]);
            $personId = is_array($byNet) && (int) ($byNet['id'] ?? 0) > 0 ? (int) $byNet['id'] : null;
        }

        $bioKey = $str($subject['bio_key'] ?? null, 64);
        $subjectSteam = ($s = preg_replace('/\D/', '', (string) ($subject['steam_id'] ?? ''))) !== '' ? substr((string) $s, 0, 32) : null;
        $documentIdentity = isset($doc['identity_id']) ? (int) $doc['identity_id'] : null;
        $link = $this->matches->resolve($tenantId, $mapId, $bioKey, $personId, $documentIdentity, $str($presented['last'] ?? null, 120));

        $levels = [];
        foreach (is_array($body['levels'] ?? null) ? $body['levels'] : [] as $l) {
            $l = self::levelCode($l);
            if ($l !== null && !in_array($l, $levels, true)) {
                $levels[] = $l;
            }
        }
        $results = [];
        foreach ($arr($body['results'] ?? null) as $lvl => $res) {
            $lvl = self::levelCode($lvl);
            if ($lvl !== null) {
                $results[$lvl] = $res;
                // Niveau détaillé dans results sans figurer dans levels : passé quand même.
                if (!in_array($lvl, $levels, true)) {
                    $levels[] = $lvl;
                }
            }
        }
        sort($levels);
        $resultsJson = json_encode($results, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
        if (!is_string($resultsJson) || strlen($resultsJson) > self::MAX_RESULTS_BYTES) {
            $resultsJson = '{}';
        }
        $pos = is_array($body['pos'] ?? null) ? array_values($body['pos']) : [];
        $num = static fn (mixed $v): ?float => is_numeric($v) ? (float) $v : null;
        $verdict = SseIdentityCatalog::pick(SseIdentityCatalog::VERDICTS, $body['verdict'] ?? null, '');
        $op = $str($body['op'] ?? null, 64);

        $stored = $this->checks->store($tenantId, [
            'context_id' => $mapId,
            'op_key' => $op,
            'idempotency_key' => $key,
            'document_id' => $doc['id'] ?? null,
            'identity_id' => $link['identity_id'],
            'person_id' => $link['person_id'],
            'doc_type' => $type !== '' ? $type : null,
            'country_code' => $country !== '' ? $country : null,
            'doc_number' => $number !== '' ? $number : null,
            'presented_last' => $str($presented['last'] ?? null, 120),
            'presented_first' => $str($presented['first'] ?? null, 120),
            'presented_dob' => $str($presented['dob'] ?? null, 16),
            'subject_netid' => $netid,
            'subject_bio_key' => $bioKey,
            'subject_steam_id' => $subjectSteam,
            'controller_callsign' => $str($controller['callsign'] ?? null, 80),
            'controller_steam_id' => ($s = preg_replace('/\D/', '', (string) ($controller['steam_id'] ?? ''))) !== '' ? substr((string) $s, 0, 32) : null,
            'levels' => $levels !== [] ? implode(',', $levels) : null,
            'results_json' => $resultsJson,
            'verdict' => $verdict !== '' ? $verdict : null,
            'grid' => $str($body['grid'] ?? null, 32),
            'pos_x' => $num($pos[0] ?? null),
            'pos_y' => $num($pos[1] ?? null),
            'pos_z' => $num($pos[2] ?? null),
            'game_time' => $str($body['game_time'] ?? null, 32),
            'case_ref' => $str($body['case'] ?? null, 64),
        ]);
        if (!$stored['duplicate']) {
            $this->matches->remember($tenantId, $mapId, $bioKey, $link['person_id'], $documentIdentity, $subjectSteam);
        }

        return $stored;
    }
}
