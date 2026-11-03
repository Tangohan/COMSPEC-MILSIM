<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Repositories\SseIdCountryRepository;
use App\Repositories\SseIdDocumentRepository;
use App\Repositories\SseIdentityRepository;
use App\Repositories\SseIdKitRepository;
use App\Repositories\SseIdOperationRepository;

/**
 * Kits documentaires types (COMSPEC IDENTITY, Lot 6) : affecter un kit type à un ou plusieurs membres.
 *
 * Pour chaque membre : l'identité qui portera les papiers (identité principale, nouvel alias ou nouvelle couverture),
 * un papier par type du kit (modèle d'impression et champs pré-remplis du kit), émis si demandé ; puis le kit de
 * mission (sse_id_kits) de la portée choisie remplacé par ces papiers, et le brief de couverture (biographie,
 * légende, contacts, consignes, téléphone et adresse simulés) du joueur pour cette portée.
 */
final class SseIdKitAssignmentService
{
    public function __construct(
        private ?SseIdentityRepository $identities = null,
        private ?SseIdDocumentRepository $documents = null,
        private ?SseIdCountryRepository $countries = null,
        private ?SseIdKitRepository $kits = null,
        private ?SseIdOperationRepository $operations = null,
        private ?SseIdentityGeneratorService $generator = null,
        private ?SseIdWorkshopService $workshop = null,
    ) {
        $this->identities ??= new SseIdentityRepository();
        $this->documents ??= new SseIdDocumentRepository();
        $this->countries ??= new SseIdCountryRepository();
        $this->kits ??= new SseIdKitRepository();
        $this->operations ??= new SseIdOperationRepository();
        $this->generator ??= new SseIdentityGeneratorService();
        $this->workshop ??= new SseIdWorkshopService($this->identities, $this->documents, $this->countries);
    }

    /**
     * @param array<string, mixed> $template SseIdKitTemplateRepository::hydrate()
     * @param list<int> $userIds membres de la communauté (déjà vérifiés par l'appelant)
     * @return array{ok: bool, error?: string, members: int, documents: int, skipped: list<int>}
     */
    public function assign(int $tenantId, array $template, array $userIds, ?int $contextId, ?string $opKey, bool $issue, bool $truth, ?int $actorId, ?int $seed = null): array
    {
        $mode = (string) $template['identity_mode'];
        if ($mode === 'cover' && !$truth) {
            return ['ok' => false, 'error' => 'Une identité de couverture demande l’habilitation Confidentiel.', 'members' => 0, 'documents' => 0, 'skipped' => []];
        }
        $country = $this->countries->findByCode($tenantId, (string) ($template['country_code'] ?? ''));
        if ($country === null) {
            $country = $this->countries->listForTenant($tenantId)[0] ?? null;
        }
        if ($country === null || !$country['active']) {
            return ['ok' => false, 'error' => 'Le pays émetteur du kit est introuvable ou archivé.', 'members' => 0, 'documents' => 0, 'skipped' => []];
        }
        $types = array_values(array_intersect($template['doc_types'], $country['doc_types']));
        if ($types === []) {
            return ['ok' => false, 'error' => 'Ce pays ne délivre aucun des types de papiers du kit.', 'members' => 0, 'documents' => 0, 'skipped' => []];
        }
        $print = $template['print'];
        foreach (['phone' => 'phone', 'address' => 'address'] as $from => $to) {
            if (($template[$from] ?? '') !== '' && !isset($print[$to])) {
                $print[$to] = (string) $template[$from];
            }
        }
        $members = 0;
        $docCount = 0;
        $skipped = [];
        foreach (array_values(array_unique(array_map('intval', $userIds))) as $userId) {
            if ($userId < 1) {
                continue;
            }
            $identity = $this->identityFor($tenantId, $userId, $mode, $country, $template, $actorId, $seed);
            if ($identity === null) {
                $skipped[] = $userId;
                continue;
            }
            $docIds = [];
            foreach ($types as $type) {
                $docId = $this->documents->create($tenantId, [
                    'identity_id' => (int) $identity['id'],
                    'doc_type' => $type,
                    'country_code' => $country['code'],
                    'doc_number' => $this->workshop->freeNumber($tenantId, $country, $type, SseIdentityGeneratorService::hash((int) ($identity['seed'] ?? 0) ?: SseIdentityGeneratorService::randomSeed(), $type . $userId)),
                    'issued_on' => gmdate('Y-m-d'),
                    'expires_on' => gmdate('Y-m-d', (int) strtotime('+' . SseIdCountryRepository::validityFor($country, $type) . ' years')),
                    'authority' => (string) $country['authority'],
                    'context_id' => $contextId,
                    'op_key' => $opKey,
                    'holder_person_id' => $identity['person_id'] ?? null,
                    'st_origin' => $identity['kind'] === 'cover' ? 'cover' : 'official',
                    'layout' => $template['layout'],
                    'print' => $print,
                    'notes' => 'Kit type ' . $template['ref'] . ' — ' . $template['name'],
                ], $actorId);
                if ($issue) {
                    $this->documents->transition($docId, $tenantId, 'validated', $actorId);
                    $this->documents->transition($docId, $tenantId, 'issued', $actorId);
                }
                $docIds[] = $docId;
            }
            $this->kits->replace($tenantId, $userId, $contextId, $opKey, $docIds, $actorId);
            $this->operations->saveBrief($tenantId, $userId, $contextId, $opKey, [
                'title' => $template['name'],
                'biography' => $template['biography'],
                'legend' => $template['legend'],
                'contacts' => $template['contacts'],
                'instructions' => $template['instructions'],
                'phone' => $template['phone'],
                'address' => $template['address'],
                'identity_id' => (int) $identity['id'],
                'kit_template_id' => (int) $template['id'],
            ], $actorId);
            SseIdMassGeneratorService::audit($tenantId, $actorId, 'id_kit_template.assign', 'user', $userId, [
                'template' => (int) $template['id'], 'identity' => (int) $identity['id'], 'documents' => $docIds, 'map' => $contextId, 'op' => $opKey, 'issued' => $issue,
            ]);
            $members++;
            $docCount += count($docIds);
        }

        return ['ok' => $members > 0, 'members' => $members, 'documents' => $docCount, 'skipped' => $skipped]
            + ($members > 0 ? [] : ['error' => 'Aucun membre n’a reçu le kit (identité principale manquante ?).']);
    }

    /**
     * @param array<string, mixed> $country
     * @param array<string, mixed> $template
     * @return array<string, mixed>|null
     */
    private function identityFor(int $tenantId, int $userId, string $mode, array $country, array $template, ?int $actorId, ?int $seed): ?array
    {
        if ($mode === 'primary') {
            foreach ($this->identities->listForUser($tenantId, $userId) as $i) {
                if (!empty($i['is_primary'])) {
                    return $i;
                }
            }

            return null;
        }
        $s = $seed !== null ? SseIdentityGeneratorService::hash($seed, 'u' . $userId) % (SseIdentityGeneratorService::MAX_SEED + 1) : null;
        $g = $this->generator->generate($country, $s);
        $id = $this->identities->create($tenantId, [
            'kind' => $mode === 'cover' ? 'cover' : 'alias',
            'last_name' => $g['last_name'],
            'first_name' => $g['first_name'],
            'sex' => $g['sex'],
            'birth_date' => $g['birth_date'],
            'birth_place' => $g['birth_place'],
            'nationality' => $country['code'],
            'profession' => ($template['profession'] ?? '') !== '' ? $template['profession'] : $g['profession'],
            'seed' => $g['seed'],
            'user_id' => $userId,
            'notes' => 'Créée par le kit type ' . $template['ref'],
        ], $actorId);

        return $this->identities->find($id, $tenantId);
    }
}
