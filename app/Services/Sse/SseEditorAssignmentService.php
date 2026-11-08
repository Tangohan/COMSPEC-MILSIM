<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Repositories\SseEditorItemRepository;
use App\Repositories\SseIdCountryRepository;
use App\Repositories\SseIdCoverLinkRepository;
use App\Repositories\SseIdDocumentRepository;
use App\Repositories\SseIdentityRepository;
use App\Repositories\SseIdKitRepository;
use App\Repositories\SseIdOperationRepository;

/**
 * Attribue à un joueur une identité, un papier ou un kit de la bibliothèque de l'éditeur SSE.
 *
 * Même chemin qu'un kit type (SseIdKitAssignmentService) : identité d'emprunt rattachée au compte, papiers émis, kit de
 * mission et brief de la portée choisie. Le serveur de jeu les reçoit avec le paquet (fn_idKit, fn_idLegend). L'identité
 * reste du genre alias, cover ou forged : sans habilitation Confidentiel, ni elle ni ses papiers n'apparaissent
 * (MemberIdentityPapersService::SECRET), et le joueur n'y voit jamais la nature de ce qu'il porte.
 * Déjà attribué au même joueur et pour la même portée : pas de nouvelle identité, le brief suit le dernier texte.
 */
final class SseEditorAssignmentService
{
    public const TYPES = ['identity', 'paper', 'kit'];

    /** Mêmes valeurs et même ordre que le mod (fn_editor.sqf, fn_editorServer.sqf). */
    private const PAPER_TYPES = ['id_card', 'passport', 'driving_license', 'military_id'];

    public function __construct(
        private ?SseEditorItemRepository $items = null,
        private ?SseIdCoverLinkRepository $links = null,
        private ?SseIdentityRepository $identities = null,
        private ?SseIdDocumentRepository $documents = null,
        private ?SseIdCountryRepository $countries = null,
        private ?SseIdKitRepository $kits = null,
        private ?SseIdOperationRepository $operations = null,
        private ?SseIdentityGeneratorService $generator = null,
        private ?SseIdWorkshopService $workshop = null,
    ) {
        $this->items ??= new SseEditorItemRepository();
        $this->links ??= new SseIdCoverLinkRepository();
        $this->identities ??= new SseIdentityRepository();
        $this->documents ??= new SseIdDocumentRepository();
        $this->countries ??= new SseIdCountryRepository();
        $this->kits ??= new SseIdKitRepository();
        $this->operations ??= new SseIdOperationRepository();
        $this->generator ??= new SseIdentityGeneratorService();
        $this->workshop ??= new SseIdWorkshopService($this->identities, $this->documents, $this->countries);
    }

    /**
     * @return array{ok: bool, error?: string, again?: bool, documents?: int}
     */
    public function assign(int $tenantId, string $itemUid, int $userId, string $countryCode, ?int $contextId, ?string $opKey, ?int $actorId): array
    {
        $item = $this->items->find($tenantId, $itemUid);
        if ($item === null || !in_array((string) $item['item_type'], self::TYPES, true)) {
            return ['ok' => false, 'error' => 'Seuls une identité, un papier ou un kit s’attribuent à un joueur.'];
        }
        [$first, $last] = self::nameOf((string) $item['title']);
        if ($last === '') {
            return ['ok' => false, 'error' => 'Le titre du contenu doit donner le prénom et le nom de l’identité.'];
        }
        $contextId = $contextId !== null && $contextId > 0 ? $contextId : null;
        $opKey = $opKey !== null && trim($opKey) !== '' ? mb_substr(trim($opKey), 0, 64) : null;
        $fields = SseIdOperationRepository::legendFields((string) $item['body']);
        $previous = $this->links->findAssignment($tenantId, (int) $item['id'], $userId, SseIdCoverLinkRepository::scopeKey($contextId, $opKey));
        $again = $previous !== null;
        if ($previous !== null) {
            $identityId = (int) $previous['identity_id'];
            $decoded = json_decode((string) $previous['document_ids'], true);
            $docIds = is_array($decoded) ? array_values(array_map('intval', $decoded)) : [];
        } else {
            $country = $this->countries->findByCode($tenantId, strtoupper(trim($countryCode)));
            if ($country === null || !$country['active']) {
                return ['ok' => false, 'error' => 'Choisissez un pays émetteur actif.'];
            }
            $types = array_values(array_intersect(self::docTypes($item), $country['doc_types']));
            if ($types === []) {
                return ['ok' => false, 'error' => 'Ce pays ne délivre pas ce type de papier.'];
            }
            $nature = (int) ($item['nature'] ?? 0);
            $g = $this->generator->generate($country);
            $identityId = $this->identities->create($tenantId, [
                // « Vraie » reste un alias : le nom imprimé n'est pas celui du compte, il ne doit pas le trahir.
                'kind' => ['alias', 'cover', 'forged'][$nature] ?? 'alias',
                'last_name' => $last,
                'first_name' => $first,
                'sex' => $g['sex'],
                'birth_date' => $g['birth_date'],
                'birth_place' => $g['birth_place'],
                'nationality' => $country['code'],
                'profession' => ($fields['profession'] ?? '') !== '' ? $fields['profession'] : $g['profession'],
                'seed' => $g['seed'],
                'user_id' => $userId,
                'notes' => 'Bibliothèque de l’éditeur : SSE-' . (string) $item['item_uid'],
            ], $actorId);
            $docIds = [];
            foreach ($types as $type) {
                $docId = $this->documents->create($tenantId, [
                    'identity_id' => $identityId,
                    'doc_type' => $type,
                    'country_code' => $country['code'],
                    'doc_number' => $this->workshop->freeNumber($tenantId, $country, $type, SseIdentityGeneratorService::hash((int) $g['seed'], $type . $userId)),
                    'issued_on' => gmdate('Y-m-d'),
                    'expires_on' => gmdate('Y-m-d', (int) strtotime('+' . SseIdCountryRepository::validityFor($country, $type) . ' years')),
                    'authority' => (string) $country['authority'],
                    'context_id' => $contextId,
                    'op_key' => $opKey,
                    'st_origin' => match ($nature) {
                        1 => 'cover',
                        2 => 'forged',
                        default => 'official',
                    },
                    'forgery_kind' => $nature === 2 ? 'counterfeit' : null,
                    'forgery_quality' => $nature === 2 ? 50 : null,
                    'notes' => 'Bibliothèque de l’éditeur : SSE-' . (string) $item['item_uid'],
                ], $actorId);
                $this->documents->transition($docId, $tenantId, 'validated', $actorId);
                $this->documents->transition($docId, $tenantId, 'issued', $actorId);
                $docIds[] = $docId;
            }
        }
        $this->kits->replace($tenantId, $userId, $contextId, $opKey, $docIds, $actorId);
        $this->operations->saveBrief($tenantId, $userId, $contextId, $opKey, [
            'title' => (string) $item['title'],
            'legend' => (string) $item['body'],
            'phone' => $fields['phone'] ?? '',
            'address' => $fields['address'] ?? '',
            // Identité sur le brief = infiltration ouverte en jeu (fn_idCover « auto ») : pour une couverture seulement.
            'identity_id' => (int) ($item['nature'] ?? 0) === 1 ? $identityId : 0,
        ], $actorId);
        $this->links->saveAssignment($tenantId, (int) $item['id'], $userId, [
            'context_id' => $contextId, 'op_key' => $opKey, 'identity_id' => $identityId, 'document_ids' => $docIds, 'item_version' => (int) ($item['version'] ?? 1),
        ], $actorId);
        SseIdMassGeneratorService::audit($tenantId, $actorId, 'editor_item.assign', 'user', $userId, [
            'item' => (string) $item['item_uid'], 'identity' => $identityId, 'documents' => $docIds, 'map' => $contextId, 'op' => $opKey, 'again' => $again,
        ]);

        return ['ok' => true, 'again' => $again, 'documents' => count($docIds)];
    }

    /**
     * Même lecture que le mod : premier mot = prénom, le reste = nom.
     *
     * @return array{0: string, 1: string}
     */
    public static function nameOf(string $title): array
    {
        $parts = preg_split('/\s+/u', trim($title)) ?: [];

        return [(string) ($parts[0] ?? ''), trim(implode(' ', array_slice($parts, 1, 9)))];
    }

    /**
     * @param array<string, mixed> $item
     * @return list<string>
     */
    private static function docTypes(array $item): array
    {
        return match ((string) $item['item_type']) {
            'paper' => [self::PAPER_TYPES[(int) ($item['paper'] ?? 0)] ?? 'id_card'],
            'kit' => ['id_card', 'passport'],
            default => ['id_card'],
        };
    }
}
