<?php

declare(strict_types=1);

namespace App\Services\Effectifs;

use App\Repositories\UserRepository;
use App\Support\SteamId;

/**
 * Vérifications du formulaire « Ajouter un membre » : indicatif, e-mail et Steam déjà pris dans la communauté.
 * Sans appel réseau (une adresse de profil Steam personnalisée est vérifiée à l'enregistrement).
 */
final class NewMemberChecks
{
    public function __construct(private UserRepository $users)
    {
    }

    /**
     * @param array{callsign?:string, email?:string, steam_id?:string} $input
     * @return array<string, array{state:string, message:string}> state : ok | taken | invalid | info
     */
    public function run(int $tenantId, array $input): array
    {
        $out = [];
        $callsign = trim((string) ($input['callsign'] ?? ''));
        if ($callsign !== '') {
            $out['callsign'] = $this->users->callsignExistsInTenant($tenantId, $callsign)
                ? ['state' => 'taken', 'message' => 'Cet indicatif est déjà porté dans la communauté. Choisissez-en un autre.']
                : ['state' => 'ok', 'message' => 'Indicatif libre.'];
        }
        $email = strtolower(trim((string) ($input['email'] ?? '')));
        if ($email !== '') {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $out['email'] = ['state' => 'invalid', 'message' => 'Adresse e-mail incomplète.'];
            } elseif ($this->users->emailExistsInTenant($tenantId, $email)) {
                $out['email'] = ['state' => 'taken', 'message' => 'Cette adresse est déjà celle d’un membre de la communauté.'];
            } elseif ($this->users->findFirstByEmailGlobal($email) !== null) {
                $out['email'] = ['state' => 'info', 'message' => 'Cette personne a déjà un compte Athena : elle sera ajoutée à la communauté et se connectera avec son mot de passe habituel.'];
            } else {
                $out['email'] = ['state' => 'ok', 'message' => 'Adresse libre.'];
            }
        }
        $steamRaw = trim((string) ($input['steam_id'] ?? ''));
        if ($steamRaw !== '') {
            $steam = SteamId::normalize($steamRaw);
            if ($steam === null) {
                $out['steam_id'] = preg_match('#steamcommunity\.com/id/#i', $steamRaw) === 1
                    ? ['state' => 'info', 'message' => 'Adresse de profil personnalisée : elle sera vérifiée à l’enregistrement.']
                    : ['state' => 'invalid', 'message' => 'Identifiant Steam non reconnu. Collez le numéro à 17 chiffres ou l’adresse du profil.'];
            } elseif ($this->users->findBySteamIdForTenant($tenantId, $steam) !== null) {
                $out['steam_id'] = ['state' => 'taken', 'message' => 'Ce compte Steam est déjà rattaché à un autre membre.'];
            } else {
                $out['steam_id'] = ['state' => 'ok', 'message' => 'Compte Steam libre.'];
            }
        }

        return $out;
    }
}
