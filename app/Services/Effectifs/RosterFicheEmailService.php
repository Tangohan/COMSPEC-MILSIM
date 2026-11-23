<?php

declare(strict_types=1);

namespace App\Services\Effectifs;

use App\Repositories\RosterFicheEmailRepository;
use App\Repositories\RosterFicheEventRepository;
use App\Repositories\UserRepository;
use App\Support\RosterOnlyMember;

/**
 * Rattache (ou retire) l'adresse e-mail d'une fiche sans compte. Règles : adresse valide, jamais l'adresse réservée,
 * une seule fiche par adresse dans la communauté, jamais l'adresse d'un compte existant (il faut alors « Rattacher à un
 * compte existant »). Chaque changement est journalisé sur la fiche.
 */
final class RosterFicheEmailService
{
    /** @param \Closure(string): bool $accountExists vrai si l'adresse appartient déjà à un compte */
    public function __construct(
        private RosterFicheEmailRepository $repo,
        private \Closure $accountExists,
        private ?RosterFicheEventRepository $events = null,
    ) {
        $this->events ??= new RosterFicheEventRepository();
    }

    public static function forUsers(UserRepository $users): self
    {
        return new self(new RosterFicheEmailRepository(), static fn (string $email): bool => $users->findFirstByEmailGlobal($email) !== null);
    }

    /**
     * Contrôle sans rien écrire. email vide = retrait demandé.
     *
     * @return array{ok: bool, email: string, message: string}
     */
    public function check(int $tenantId, int $ficheId, string $raw): array
    {
        $email = RosterFicheEmailRepository::normalize($raw);
        if ($email === '') {
            return ['ok' => true, 'email' => '', 'message' => ''];
        }
        if (mb_strlen($email, 'UTF-8') > 190 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'email' => $email, 'message' => 'Adresse e-mail invalide : « ' . mb_substr($email, 0, 60, 'UTF-8') . ' ».'];
        }
        if (RosterOnlyMember::isRosterEmail($email)) {
            return ['ok' => false, 'email' => $email, 'message' => 'Cette adresse est réservée aux fiches : indiquez l’adresse réelle de la personne.'];
        }
        $owner = $this->repo->ownerOf($tenantId, $email);
        if ($owner > 0 && $owner !== $ficheId) {
            return ['ok' => false, 'email' => $email, 'message' => 'L’adresse ' . $email . ' est déjà rattachée à une autre fiche de la communauté.'];
        }
        if (($this->accountExists)($email)) {
            return ['ok' => false, 'email' => $email, 'message' => 'L’adresse ' . $email . ' appartient déjà à un compte : utilisez « Rattacher à un compte existant ».'];
        }

        return ['ok' => true, 'email' => $email, 'message' => ''];
    }

    /**
     * Rattache, remplace ou retire l'adresse (journalisé). changed = vrai si quelque chose a bougé.
     *
     * @return array{ok: bool, changed: bool, email: string, message: string}
     */
    public function apply(int $tenantId, int $ficheId, ?int $actorId, string $raw): array
    {
        $check = $this->check($tenantId, $ficheId, $raw);
        if (!$check['ok']) {
            return ['ok' => false, 'changed' => false, 'email' => $check['email'], 'message' => $check['message']];
        }
        if (!$this->repo->available()) {
            return ['ok' => false, 'changed' => false, 'email' => $check['email'], 'message' => 'L’adresse e-mail des fiches sera disponible après la prochaine mise à jour de la base.'];
        }
        $before = $this->repo->find($tenantId, $ficheId);
        $email = $check['email'];
        if ($before === $email) {
            return ['ok' => true, 'changed' => false, 'email' => $email, 'message' => ''];
        }
        if ($email === '') {
            $this->repo->remove($tenantId, $ficheId);
            $this->events->record($tenantId, $ficheId, $actorId, 'edited', '', 'e-mail retiré (' . $before . ')');

            return ['ok' => true, 'changed' => true, 'email' => '', 'message' => ''];
        }
        if (!$this->repo->set($tenantId, $ficheId, $email, $actorId, date('Y-m-d H:i:s'))) {
            return ['ok' => false, 'changed' => false, 'email' => $email, 'message' => 'Impossible d’enregistrer l’adresse e-mail pour le moment.'];
        }
        $this->events->record($tenantId, $ficheId, $actorId, 'edited', '', $before === '' ? 'e-mail rattaché : ' . $email : 'e-mail ' . $before . ' → ' . $email);

        return ['ok' => true, 'changed' => true, 'email' => $email, 'message' => ''];
    }
}
