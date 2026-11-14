<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Container;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\PersonnelProfileRepository;
use App\Repositories\UserRepository;
use App\Services\Auth\AuthService;

/**
 * Création et modification du personnage : /account/personnage.
 * Réutilise personnel_profiles (character_name, callsign, motto) et users.callsign, comme l'étape « Identité »
 * de l'accueil du profil. Tout est lu et écrit pour la communauté active uniquement.
 */
final class AccountCharacterController
{
    public const NAME_MAX = 120;
    public const CALLSIGN_MAX = 50;
    public const MOTTO_MAX = 255;

    /** GET|POST /account/personnage */
    public function edit(Request $request, array $params = []): Response
    {
        $user = Container::get(AuthService::class)->user();
        if (!is_array($user) || (int) ($user['id'] ?? 0) < 1) {
            return Response::redirect(url('login'));
        }
        $uid = (int) $user['id'];
        $tenantId = (int) ($user['tenant_id'] ?? 0);
        $sessionTenant = (int) (Session::get('tenant_id') ?? 0);
        $repo = Container::get(PersonnelProfileRepository::class);
        $profile = $tenantId > 0 ? ($repo->getByUserId($uid, $tenantId) ?? []) : [];
        if ($profile !== [] && isset($profile['tenant_id']) && (int) $profile['tenant_id'] !== $tenantId) {
            $profile = [];
        }
        $values = [
            'first_name' => '',
            'last_name' => '',
            'callsign' => trim((string) ($profile['callsign'] ?? ($user['callsign'] ?? ''))),
            'motto' => trim((string) ($profile['motto'] ?? '')),
        ];
        [$values['first_name'], $values['last_name']] = self::splitName((string) ($profile['character_name'] ?? ''));
        $error = null;

        if ($request->method() === 'POST') {
            $values = [
                'first_name' => self::clean((string) $request->input('first_name', '')),
                'last_name' => self::clean((string) $request->input('last_name', '')),
                'callsign' => self::clean((string) $request->input('callsign', '')),
                'motto' => self::clean((string) $request->input('motto', '')),
            ];
            if (!Csrf::validate($request->input('_csrf_token'))) {
                $error = 'Session expirée. Merci de réessayer.';
            } elseif ($tenantId < 1 || ($sessionTenant > 0 && $sessionTenant !== $tenantId)) {
                $error = 'Choisissez d’abord une communauté active pour créer votre personnage.';
            } else {
                $error = self::validate($values);
            }
            if ($error === null) {
                $name = self::fullName($values['first_name'], $values['last_name']);
                Container::get(UserRepository::class)->update($uid, $tenantId, ['callsign' => $values['callsign']]);
                $repo->ensureRecord($uid, $tenantId);
                $repo->update($uid, [
                    'character_name' => $name,
                    'callsign' => $values['callsign'],
                    'motto' => $values['motto'] !== '' ? $values['motto'] : null,
                ]);
                Session::set('callsign', $values['callsign']);
                Session::flash('success', 'Personnage enregistré : ' . $name . '.');

                return Response::redirect(url('account'));
            }
        }

        $portraitUrl = !empty($profile['character_portrait_path']) ? personnel_operator_portrait_url($profile) : null;

        return Response::view('layout.main', [
            'content' => 'account.character',
            'title' => 'Mon personnage',
            'accountHubPage' => true,
            'accountPublicCvUrl' => PublicPlayerCvController::activeUrlForUser($uid),
            'user' => $user,
            'characterValues' => $values,
            'characterExists' => trim((string) ($profile['character_name'] ?? '')) !== '',
            'characterPortraitUrl' => $portraitUrl,
            'error' => $error,
        ]);
    }

    private static function clean(string $v): string
    {
        return preg_replace('/\s+/u', ' ', trim($v)) ?? '';
    }

    public static function fullName(string $first, string $last): string
    {
        return trim($first . ' ' . $last);
    }

    /** @return array{0: string, 1: string} */
    public static function splitName(string $name): array
    {
        $name = trim($name);
        if ($name === '') {
            return ['', ''];
        }
        $parts = explode(' ', $name, 2);

        return [$parts[0], $parts[1] ?? ''];
    }

    /** @param array{first_name: string, last_name: string, callsign: string, motto: string} $v */
    public static function validate(array $v): ?string
    {
        if ($v['first_name'] === '' || $v['last_name'] === '') {
            return 'Indiquez le prénom et le nom de votre personnage.';
        }
        if (mb_strlen(self::fullName($v['first_name'], $v['last_name'])) > self::NAME_MAX) {
            return 'Le nom du personnage tient en ' . self::NAME_MAX . ' caractères au plus.';
        }
        if ($v['callsign'] === '') {
            return 'Indiquez l’indicatif de votre personnage.';
        }
        if (mb_strlen($v['callsign']) > self::CALLSIGN_MAX) {
            return 'L’indicatif tient en ' . self::CALLSIGN_MAX . ' caractères au plus.';
        }
        if (mb_strlen($v['motto']) > self::MOTTO_MAX) {
            return 'La devise tient en ' . self::MOTTO_MAX . ' caractères au plus.';
        }

        return null;
    }
}
