<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\GamePhoneIdentityRepository;
use App\Support\ModuleFeatureAccess;

/**
 * Parc de terminaux, volet téléphones en jeu : type de numéro, numéro, IMEI et MAC gardés dans Athena.
 * Le jeu relit ces valeurs (profil jeu, cellules 26 à 29 de GetAuthState) à la synchronisation suivante.
 */
final class AdminAtakPhonesController
{
    private const BACK = 'back-office/atak/realisme#telephones';

    public function __construct(private ?GamePhoneIdentityRepository $phones = null)
    {
    }

    public function save(Request $request, array $params = []): Response
    {
        $guard = $this->guard($request);
        if ($guard instanceof Response) {
            return $guard;
        }
        $userId = (int) ($params['userId'] ?? 0);
        $result = $this->repo()->saveSettings(
            $guard,
            $userId,
            (string) $request->input('phone_format', ''),
            (string) $request->input('phone_number', ''),
            (string) $request->input('imei', ''),
            (string) $request->input('mac', '')
        );
        if (!$result['ok']) {
            Session::flash('error', (string) ($result['error'] ?? 'Enregistrement impossible.'));
        } else {
            $number = (string) ($result['identity']['number'] ?? '');
            Session::flash('success', 'Téléphone enregistré' . ($number !== '' ? ' (' . $number . ')' : '') . '. Le jeu le reprend à sa prochaine synchronisation, en moins d’une minute.');
        }

        return Response::redirect(url(self::BACK));
    }

    public function regenerate(Request $request, array $params = []): Response
    {
        $guard = $this->guard($request);
        if ($guard instanceof Response) {
            return $guard;
        }
        $userId = (int) ($params['userId'] ?? 0);
        $format = trim((string) $request->input('phone_format', ''));
        $result = $this->repo()->regenerateNumber($guard, $userId, $format !== '' ? $format : null);
        if (!$result['ok']) {
            Session::flash('error', (string) ($result['error'] ?? 'Régénération impossible.'));
        } else {
            Session::flash('success', 'Nouveau numéro attribué : ' . (string) ($result['number'] ?? '') . '.');
        }

        return Response::redirect(url(self::BACK));
    }

    /** Tenant courant si l'écriture est permise, sinon la réponse à renvoyer. */
    private function guard(Request $request): int|Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            return Response::redirect(url('dashboard'));
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url(self::BACK));
        }
        $forbidden = ModuleFeatureAccess::guardAtak('manage');
        if ($forbidden instanceof Response) {
            return $forbidden;
        }

        return $tenantId;
    }

    private function repo(): GamePhoneIdentityRepository
    {
        return $this->phones ??= new GamePhoneIdentityRepository();
    }
}
