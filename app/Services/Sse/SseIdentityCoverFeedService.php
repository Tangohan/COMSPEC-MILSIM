<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Repositories\OperatorHubRepository;
use App\Repositories\SseIdCountryRepository;
use App\Repositories\SseIdDocumentRepository;
use App\Repositories\SseIdentityRepository;
use App\Repositories\SseIdentityStoryRepository;
use App\Repositories\SseIdKitRepository;
use App\Services\Operator\OperatorIdentityService;

/**
 * Identités du membre servies au téléphone du jeu par les points d'accès hérités des couvertures (/api/atak/covers et
 * relais « ~covers » du paquet d'identité), une fois l'étape 4450 passée.
 *
 * Le mod publié (SSE 1.9.1) attend des fichiers « comspec-cover » v1 : chaque identité non archivée du membre (celle de
 * son personnage et ses identités d'emprunt) est donc présentée dans ce contrat, sans clé nouvelle :
 *  - référence « CVR-XXXXXXXX » stable par identité (SseCoverExportService::identityRef) : pas de doublon d'un appel à l'autre ;
 *  - « cover » : état civil de l'identité, ses papiers émis et valables (eligibleDocs), son histoire si le membre en a
 *    écrit une, le reste tiré de l'identité (coverFromIdentity, comme avant) ; jamais de photo ;
 *  - fichier daté du premier jour du mois (UTC) : contenu et signature stables dans le mois, jamais expiré (90 jours) ;
 *  - « active » : l'identité « Active en jeu » (celle dont les papiers partent en mission).
 * Rien n'est stocké : le fichier est rebâti et signé à chaque appel. Lecture seule, bornée au membre et à sa communauté.
 */
final class SseIdentityCoverFeedService
{
    public function __construct(
        private ?SseCoverExportService $service = null,
        private ?SseIdentityRepository $identities = null,
        private ?SseIdDocumentRepository $documents = null,
        private ?SseIdKitRepository $kits = null,
        private ?SseIdCountryRepository $countries = null,
        private ?SseIdentityStoryRepository $stories = null,
        private ?OperatorHubRepository $states = null,
    ) {
        $this->service ??= new SseCoverExportService();
        $this->identities ??= new SseIdentityRepository();
        $this->documents ??= new SseIdDocumentRepository();
        $this->kits ??= new SseIdKitRepository();
        $this->countries ??= new SseIdCountryRepository();
        $this->stories ??= new SseIdentityStoryRepository();
    }

    /**
     * Fichiers des identités du membre, l'identité active d'abord, puis la plus récente d'abord.
     *
     * @return list<array{identity_id: int, ref: string, json: string, sha: string, file: array<string, mixed>, cover: array<string, mixed>, active: bool, synced_at: ?string, updated_at: string}>
     */
    public function build(int $tenantId, int $userId, ?\DateTimeImmutable $now = null): array
    {
        $now ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $stamp = $now->setTimezone(new \DateTimeZone('UTC'))->modify('first day of this month')->setTime(0, 0);
        $rows = $this->identities->listForUser($tenantId, $userId);
        if ($rows === []) {
            return [];
        }
        $archived = [];
        try {
            $this->states ??= new OperatorHubRepository();
            foreach ($this->states->states($tenantId, $userId, OperatorHubRepository::KIND_IDENTITY) as $id => $s) {
                if ($s['archived_at'] !== null) {
                    $archived[$id] = true;
                }
            }
        } catch (\Throwable) {
            $archived = [];
        }
        $docs = $this->documents->list($tenantId, ['user_id' => $userId, 'limit' => 200]);
        $activeId = OperatorIdentityService::activeIdentityId($rows, $docs, $this->kits->listForUser($tenantId, $userId));
        $stories = $this->stories->forIdentities($tenantId, array_map(static fn (array $i): int => (int) $i['id'], $rows));
        $fallback = null;
        $out = [];
        foreach ($rows as $i) {
            $id = (int) $i['id'];
            if (isset($archived[$id]) || trim((string) $i['last_name']) === '' || trim((string) $i['first_name']) === '') {
                continue;
            }
            $country = trim((string) ($i['nationality'] ?? '')) !== '' ? $this->countries->findByCode($tenantId, (string) $i['nationality']) : null;
            $country ??= $fallback ??= ($this->countries->listForTenant($tenantId, true)[0] ?? null);
            if ($country === null) {
                continue;
            }
            $eligible = array_values(array_slice($this->service->eligibleDocs($tenantId, $userId, $id, $now, $this->documents), 0, 10, true));
            $legend = [];
            $history = self::historyLines((string) ($stories[$id]['story'] ?? ''));
            if ($history !== []) {
                $legend['history'] = $history;
            }
            try {
                $cover = $this->service->coverFromIdentity($tenantId, $i, $country, $eligible, 'fr', $stamp, $legend);
                $ref = $this->service->identityRef($tenantId, $id);
                $built = $this->service->buildFile($cover, $tenantId, $userId, $ref, $stamp);
            } catch (\Throwable) {
                continue;
            }
            // Même contrôle que le mod : une identité qui ne tient pas dans le contrat n'est pas proposée.
            $parsed = SseCoverExportService::parse($built['json'], new \DateTimeImmutable('@0'));
            if (!$parsed['ok']) {
                continue;
            }
            $out[] = [
                'identity_id' => $id,
                'ref' => $ref,
                'json' => $built['json'],
                'sha' => $built['sha'],
                'file' => $parsed['file'],
                'cover' => $parsed['cover'],
                'active' => $activeId === $id,
                'synced_at' => $stories[$id]['game_synced_at'] ?? null,
                'updated_at' => (string) ($i['updated_at'] ?? $i['created_at'] ?? ''),
            ];
        }
        usort($out, static fn (array $a, array $b): int => [(int) $b['active'], $b['identity_id']] <=> [(int) $a['active'], $a['identity_id']]);

        return $out;
    }

    /**
     * Histoire écrite par le membre → lignes « history » du contrat : 8 lignes de 255 caractères au plus, sans caractère
     * de contrôle. Histoire vide : aucune ligne (celles tirées de l'identité sont gardées).
     *
     * @return list<string>
     */
    public static function historyLines(string $story): array
    {
        $out = [];
        foreach (preg_split('/\R/u', $story) ?: [] as $line) {
            $line = trim((string) preg_replace('/[\x00-\x1F\x7F]/u', ' ', $line));
            if ($line !== '') {
                $out[] = mb_substr($line, 0, 255);
            }
            if (count($out) === 8) {
                break;
            }
        }

        return $out;
    }

    /**
     * Liste au format de SseCoverSyncService::listFor() (mêmes clés).
     *
     * @return list<array<string, mixed>>
     */
    public function listFor(int $tenantId, int $userId, ?\DateTimeImmutable $now = null): array
    {
        $out = [];
        foreach ($this->build($tenantId, $userId, $now) as $e) {
            $cover = $e['cover'];
            $summary = implode(' · ', array_filter([(string) $cover['profession'], (string) $cover['city']], static fn (string $v): bool => $v !== ''));
            $updated = preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2}/', $e['updated_at']) === 1 ? $e['updated_at'] : (string) $e['file']['generated_at'];
            $out[] = [
                'ref' => $e['ref'],
                'name' => (string) $cover['name'],
                'summary' => mb_substr($summary, 0, 160),
                'country' => (string) $cover['country'],
                'country_code' => (string) $cover['country_code'],
                'status' => 'valid',
                'has_photo' => false,
                'generated_at' => (string) $e['file']['generated_at'],
                'expires_at' => (string) $e['file']['expires_at'],
                'updated_at' => str_replace(' ', 'T', substr($updated, 0, 19)) . 'Z',
                'size' => strlen($e['json']),
                'sha' => $e['sha'],
                'by_manager' => false,
                'downloaded' => $e['synced_at'] !== null,
                'active' => $e['active'],
            ];
        }

        return $out;
    }

    /**
     * Fichiers demandés au format de SseCoverSyncService::filesFor(). Référence inconnue, d'un autre membre ou d'une
     * autre communauté : « not_found ».
     *
     * @param list<string> $refs
     * @return array{files: list<array<string, mixed>>, errors: list<array{ref: string, error: string}>}
     */
    public function filesFor(int $tenantId, int $userId, array $refs, ?\DateTimeImmutable $now = null): array
    {
        $now ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $byRef = [];
        foreach ($this->build($tenantId, $userId, $now) as $e) {
            $byRef[$e['ref']] = $e;
        }
        $files = [];
        $errors = [];
        foreach ($refs as $ref) {
            if (!isset($byRef[$ref])) {
                $errors[] = ['ref' => $ref, 'error' => 'not_found'];
                continue;
            }
            $files[] = $byRef[$ref]['file'];
            try {
                $this->stories->markSynced($tenantId, $byRef[$ref]['identity_id'], $now->format('Y-m-d H:i:s'));
            } catch (\Throwable $e) {
                error_log('Identités du téléphone : date d’envoi non notée : ' . $e->getMessage());
            }
        }

        return ['files' => $files, 'errors' => $errors];
    }
}
