<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Repositories\SseFileRepository;
use App\Repositories\SseIdentityRepository;
use App\Repositories\SseWantedNoticeRepository;
use App\Support\SseAccessCatalog;
use App\Services\Sse\MemberIdentityPapersService;

/**
 * Passerelle entre la fiche personne (sse_persons, adresse /atak/sse/identites/{id}) et l'identité du registre
 * (sse_identities, adresse /atak/sse/papiers/identites/{id}) qui porte l'avis de recherche, la notice PDF, l'historique, les
 * détections et les images du sujet. Le lien est sse_identities.person_id.
 *
 * Une identité de couverture, d'emprunt ou falsifiée ne se révèle qu'avec l'habilitation Confidentiel (même règle que la
 * fiche du registre) : sans elle, elle n'est ni proposée ni ouverte.
 */
final class SsePersonRegistryLinkService
{
    public const DENY_GUEST = 'Une session invitée ne peut pas créer d’identité du registre : connectez-vous avec votre compte.';
    public const DENY_RIGHT = 'Droit manquant : écrire dans « Papiers et couvertures » (gestion des dossiers SSE). Demandez-le à un gestionnaire des accès SSE.';

    public function __construct(private ?SseIdentityRepository $identities = null)
    {
        $this->identities ??= new SseIdentityRepository();
    }

    /**
     * Identités du registre rattachées à la personne et lisibles par le demandeur, la plus naturelle d'abord (civile, puis
     * générée, puis les autres par ancienneté).
     *
     * @return list<array<string, mixed>>
     */
    public function identitiesOf(int $tenantId, int $personId, bool $truth): array
    {
        if ($tenantId < 1 || $personId < 1) {
            return [];
        }
        $rows = array_values(array_filter(
            $this->identities->listForPerson($tenantId, $personId),
            static fn (array $i): bool => $truth || !in_array((string) $i['kind'], MemberIdentityPapersService::SECRET, true)
        ));
        $rank = static fn (array $i): int => match ((string) $i['kind']) {
            'civil' => 0,
            'generated' => 1,
            default => 2,
        };
        usort($rows, static fn (array $a, array $b): int => [$rank($a), (int) $a['id']] <=> [$rank($b), (int) $b['id']]);

        return $rows;
    }

    /** L'identité à ouvrir pour cette personne, ou null si aucune n'est rattachée (ou lisible). */
    public function target(int $tenantId, int $personId, bool $truth): ?array
    {
        return $this->identitiesOf($tenantId, $personId, $truth)[0] ?? null;
    }

    /** Une identité (même cachée) est-elle déjà rattachée à la personne ? Sert à ne pas en créer une seconde. */
    public function hasAny(int $tenantId, int $personId): bool
    {
        return $tenantId > 0 && $personId > 0 && $this->identities->listForPerson($tenantId, $personId) !== [];
    }

    /**
     * Le demandeur peut-il créer une identité du registre ? Garde unique : la même décide d'afficher le bouton de la fiche
     * personne et d'autoriser l'action (SsePersonRegistryController::create). Écrire dans « papiers », comme la création d'une
     * identité depuis le registre ; une session invitée n'écrit jamais.
     *
     * @return string|null null si c'est permis, sinon la raison du refus (texte à montrer)
     */
    public function createDenial(int $tenantId, bool $guest): ?string
    {
        if ($guest) {
            return self::DENY_GUEST;
        }
        try {
            $allowed = $tenantId > 0
                && SseAccessPolicy::sessionAllows('papiers', SseAccessCatalog::LEVEL_WRITE, ['guest' => $guest, 'tenant_id' => $tenantId]);
        } catch (\Throwable) {
            $allowed = false;
        }

        return $allowed ? null : self::DENY_RIGHT;
    }

    /**
     * Ce que la fiche personne affiche du registre : l'identité rattachée, son avis actif, les clichés SEEK II et les mentions
     * (jamais pour une identité cachée au demandeur), et les droits utiles aux boutons.
     *
     * @param array<string, mixed> $person
     * @return array{identity: ?array<string, mixed>, hidden: bool, notice: ?array<string, mixed>, seekImages: list<array<string, mixed>>, fileMentions: list<array<string, mixed>>, canCreate: bool, createDenial: ?string, canWanted: bool, canPdf: bool, lang: string}
     */
    public function pageData(int $tenantId, array $person, SseWantedAccess $access, bool $guest): array
    {
        $personId = (int) ($person['id'] ?? 0);
        $identity = $this->target($tenantId, $personId, $access->canSeeTruth());
        $out = [
            'identity' => $identity,
            'hidden' => $identity === null && $this->hasAny($tenantId, $personId),
            'notice' => null,
            'seekImages' => [],
            'fileMentions' => [],
            'canCreate' => false,
            'createDenial' => null,
            'canWanted' => $access->canManage(),
            'canPdf' => $access->canGeneratePdf(),
            'lang' => !function_exists('site_lang') || site_lang() === 'fr' ? 'fr' : 'en',
        ];
        if ($identity === null && !$out['hidden']) {
            $out['createDenial'] = $this->createDenial($tenantId, $guest);
            $out['canCreate'] = $out['createDenial'] === null;
        }
        if ($identity === null) {
            return $out;
        }
        $identityId = (int) $identity['id'];
        try {
            if ($access->canRead() && SseWantedNoticeRepository::ready()) {
                $out['notice'] = (new SseWantedNoticeRepository())->activeForIdentity($tenantId, $identityId);
            }
        } catch (\Throwable) {
        }
        try {
            $out['seekImages'] = (new SseSeekPhotoService())->forIdentity($tenantId, $identityId, $identity['bio_key'] ?? null);
            $out['fileMentions'] = (new SseFileRepository())->activeMentionsFor($tenantId, [$identityId], []);
        } catch (\Throwable) {
        }

        return $out;
    }

    /**
     * Crée l'identité du registre d'une personne qui n'en a pas (nom, prénom, sexe, naissance, nationalité si c'est un code
     * de pays à trois lettres). Jamais deux : si une identité est déjà rattachée, rien n'est créé.
     *
     * @param array<string, mixed> $person ligne de la fiche personne (SsePersonRepository::findById)
     * @return int|null identifiant de l'identité créée, null si la personne est incomplète ou déjà rattachée
     */
    public function createFor(int $tenantId, array $person, ?int $userId): ?int
    {
        $personId = (int) ($person['id'] ?? 0);
        $last = trim((string) ($person['last_name'] ?? ''));
        $first = trim((string) ($person['first_name'] ?? ''));
        if ($tenantId < 1 || $personId < 1 || ($last === '' && $first === '') || $this->hasAny($tenantId, $personId)) {
            return null;
        }
        $nat = strtoupper(trim((string) ($person['nationality'] ?? '')));
        $sex = strtoupper(substr(trim((string) ($person['sex_apparent'] ?? $person['sex'] ?? '')), 0, 1));

        $created = $this->identities->create($tenantId, [
            'kind' => 'civil',
            'last_name' => $last !== '' ? $last : $first,
            'first_name' => $last !== '' ? $first : '',
            'sex' => in_array($sex, ['M', 'F'], true) ? $sex : 'X',
            'birth_date' => (string) ($person['birth_date'] ?? ''),
            'birth_place' => (string) ($person['birth_place'] ?? ''),
            'nationality' => preg_match('/^[A-Z]{3}$/', $nat) === 1 ? $nat : '',
            'alias' => (string) ($person['alias'] ?? ''),
            'person_id' => $personId,
            'notes' => 'Identité du registre créée depuis la fiche personne IDN-' . str_pad((string) $personId, 5, '0', STR_PAD_LEFT) . '.',
        ], $userId);
        if ($created > 0 && $userId !== null && $userId > 0) {
            SseProvenance::manual($tenantId, ['identity', $created], ['person', $personId], 'created_from', 'Identité du registre créée depuis la fiche personne IDN-' . str_pad((string) $personId, 5, '0', STR_PAD_LEFT) . '.', $userId, 'manual.identity_from_person');
        }

        return $created;
    }
}
