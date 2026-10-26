<?php

declare(strict_types=1);

namespace App\Controllers\Admin\System;

use App\Controllers\Web\ContributorController;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\SiteContributorRepository;
use App\Services\I18n\CommunityLanguages;
use App\Services\I18n\SiteTranslationService;

/**
 * Contributeurs et langues du site côté administration (/admin/system/contributeurs) : demandes de rôle à valider
 * ou refuser, rôles actifs à retirer (avec le pseudo Discord pour donner le rôle sur le serveur), langues de la
 * traduction communautaire (création, nom, publication dans le sélecteur de langue, recompilation).
 */
final class SystemContributorsController
{
    private const BASE = 'admin/system/contributeurs';

    public function __construct(private ?SiteTranslationService $service = null)
    {
    }

    public function index(Request $request, array $params = []): Response
    {
        $ready = $this->ready();

        return Response::view('layout.main', [
            'title' => 'Contributeurs et traduction du site',
            'content' => 'admin.system.contributors',
            'contribReady' => $ready,
            'contribRows' => $ready ? $this->repo()->allRoles() : [],
            'contribLanguages' => $ready ? $this->service()->languages() : [],
            'contribLeaders' => $ready ? $this->repo()->translationLeaders(12) : [],
            'contribDiscordRoles' => ContributorController::DISCORD_ROLES,
            'contribBase' => self::BASE,
            'isPlatformAdminShell' => true,
            'backOfficePageCss' => ['platform-admin.css', 'admin-registry.css'],
        ]);
    }

    /** POST …/role : action accept | reject | revoke. accept peut cocher plusieurs rôles (roles[]) pour la personne. */
    public function role(Request $request, array $params = []): Response
    {
        $adminId = (int) (ContributorController::user()['id'] ?? 0);
        if (!Csrf::verify($request) || !$this->ready() || $adminId < 1) {
            Session::flash('error', 'Session expirée, rechargez la page.');

            return Response::redirect(url(self::BASE));
        }
        $action = (string) $request->input('action', '');
        if ($action === 'accept' && is_array($request->input('roles'))) {
            return $this->grantMany($request, $adminId);
        }
        $row = $this->repo()->findRole((int) $request->input('id', 0));
        if ($row === null || !in_array($action, ['accept', 'reject', 'revoke'], true)) {
            Session::flash('error', 'Demande introuvable.');

            return Response::redirect(url(self::BASE));
        }
        $status = match ($action) {
            'accept' => SiteContributorRepository::STATUS_ACTIVE,
            'reject' => SiteContributorRepository::STATUS_REJECTED,
            default => SiteContributorRepository::STATUS_REVOKED,
        };
        $languages = null;
        if ((string) $row['role'] === 'translator' && $request->input('languages') !== null) {
            $languages = implode(',', SiteContributorRepository::splitLanguages((string) $request->input('languages')));
        }
        $this->repo()->decide((int) $row['id'], $status, $adminId, trim((string) $request->input('note', '')), $languages);
        // Traducteur accepté : ses langues encore inconnues sont créées en brouillon (nom à corriger ensuite).
        if ($status === SiteContributorRepository::STATUS_ACTIVE && (string) $row['role'] === 'translator') {
            foreach (SiteContributorRepository::splitLanguages($languages ?? (string) $row['languages']) as $code) {
                if ($code !== CommunityLanguages::ENGLISH && $this->repo()->language($code) === null) {
                    $this->repo()->createLanguage($code, strtoupper($code), $adminId);
                }
            }
        }
        $discord = trim((string) $row['discord_handle']);
        $label = $this->roleLabel((string) $row['role']);
        $discordName = ContributorController::DISCORD_ROLES[(string) $row['role']] ?? '';
        $msg = match ($status) {
            SiteContributorRepository::STATUS_ACTIVE => 'Rôle accordé : ' . $label . '.' . ($discord !== '' && $discordName !== ''
                ? ' Pensez à donner le rôle Discord « ' . $discordName . ' » à ' . $discord . '.'
                : ''),
            SiteContributorRepository::STATUS_REJECTED => 'Demande refusée : ' . $label . '. Les autres rôles de cette personne restent en place.',
            default => 'Rôle retiré : ' . $label . '. Les autres rôles restent en place.' . ($discord !== '' && $discordName !== ''
                ? ' Retirez aussi le rôle Discord « ' . $discordName . ' » de ' . $discord . '.'
                : ''),
        };
        Session::flash('success', $msg);

        return Response::redirect(url(self::BASE) . '#u' . (int) $row['user_id']);
    }

    /**
     * Accorde les rôles cochés. Ne retire rien : un rôle décoché, déjà actif ou non, reste tel quel.
     */
    private function grantMany(Request $request, int $adminId): Response
    {
        $userId = (int) $request->input('user_id', 0);
        $anchor = url(self::BASE) . ($userId > 0 ? '#u' . $userId : '');
        $picked = [];
        foreach ((array) $request->input('roles', []) as $role) {
            $role = (string) $role;
            if (in_array($role, SiteContributorRepository::ROLES, true) && !in_array($role, $picked, true)) {
                $picked[] = $role;
            }
        }
        if ($picked === []) {
            Session::flash('error', 'Cochez au moins un rôle à accorder.');

            return Response::redirect($anchor);
        }
        if ($userId < 1 || $this->repo()->rolesOfUser($userId) === []) {
            Session::flash('error', 'Demande introuvable.');

            return Response::redirect(url(self::BASE));
        }
        $note = trim((string) $request->input('note', ''));
        $languages = null;
        if (in_array('translator', $picked, true) && $request->input('languages') !== null) {
            $languages = implode(',', SiteContributorRepository::splitLanguages((string) $request->input('languages')));
        }
        $activated = [];
        $kept = [];
        foreach ($picked as $role) {
            $result = $this->repo()->grantRole(
                $userId,
                $role,
                $adminId,
                $note,
                $role === 'translator' ? $languages : null
            );
            if ($result === 'kept') {
                $kept[] = $this->roleLabel($role);
            } else {
                $activated[] = $role;
            }
        }
        if (in_array('translator', $picked, true)) {
            $codes = SiteContributorRepository::splitLanguages((string) ($languages ?? ''));
            if ($codes === []) {
                foreach ($this->repo()->rolesOfUser($userId) as $row) {
                    if ((string) $row['role'] === 'translator') {
                        $codes = SiteContributorRepository::splitLanguages((string) $row['languages']);
                    }
                }
            }
            foreach ($codes as $code) {
                if ($code !== CommunityLanguages::ENGLISH && $this->repo()->language($code) === null) {
                    $this->repo()->createLanguage($code, strtoupper($code), $adminId);
                }
            }
        }
        $discord = '';
        foreach ($this->repo()->rolesOfUser($userId) as $row) {
            $handle = trim((string) ($row['discord_handle'] ?? ''));
            if ($handle !== '') {
                $discord = $handle;
                break;
            }
        }
        if ($activated === []) {
            $msg = 'Ces rôles sont déjà actifs. Aucun autre rôle n’a été retiré.';
        } else {
            $labels = array_map($this->roleLabel(...), $activated);
            $msg = 'Rôles accordés : ' . implode(', ', $labels) . '.';
            if ($kept !== []) {
                $msg .= ' Déjà en place : ' . implode(', ', $kept) . '.';
            }
            $msg .= ' Les rôles non cochés n’ont pas été modifiés.';
            if ($discord !== '') {
                $discordNames = [];
                foreach ($activated as $role) {
                    $discordName = ContributorController::DISCORD_ROLES[$role] ?? '';
                    if ($discordName !== '') {
                        $discordNames[] = '« ' . $discordName . ' »';
                    }
                }
                if ($discordNames !== []) {
                    $msg .= ' Pensez à donner sur Discord ' . implode(', ', $discordNames) . ' à ' . $discord . '.';
                }
            }
        }
        Session::flash('success', $msg);

        return Response::redirect($anchor);
    }

    private function roleLabel(string $role): string
    {
        return match ($role) {
            'translator' => 'Traducteur',
            'tester' => 'Testeur',
            'content' => 'Créateur de contenu',
            'developer' => 'Développeur',
            'support' => 'Support',
            default => 'Contributeur',
        };
    }

    /** POST …/langue : action create | save | recompile ; code, name, published. */
    public function language(Request $request, array $params = []): Response
    {
        $adminId = (int) (ContributorController::user()['id'] ?? 0);
        if (!Csrf::verify($request) || !$this->ready() || $adminId < 1) {
            Session::flash('error', 'Session expirée, rechargez la page.');

            return Response::redirect(url(self::BASE));
        }
        $code = strtolower(trim((string) $request->input('code', '')));
        $name = trim((string) $request->input('name', ''));
        $action = (string) $request->input('action', '');
        if (!CommunityLanguages::validCode($code)) {
            Session::flash('error', 'Code de langue invalide (deux lettres, par exemple « es », ou « pt-br »).');

            return Response::redirect(url(self::BASE) . '#langues');
        }
        if ($action === 'create') {
            if ($code === CommunityLanguages::ENGLISH) {
                Session::flash('error', 'L’anglais existe déjà (corrections de l’anglais).');

                return Response::redirect(url(self::BASE) . '#langues');
            }
            $this->repo()->createLanguage($code, $name !== '' ? $name : strtoupper($code), $adminId);
            Session::flash('success', 'Langue « ' . $code . ' » créée en brouillon.');
        } elseif ($action === 'save' && $code !== CommunityLanguages::ENGLISH && $this->repo()->language($code) !== null) {
            $published = (string) $request->input('published', '') === '1';
            $this->repo()->updateLanguage($code, $name !== '' ? $name : strtoupper($code), $published);
            $this->service()->recompile($code);
            $this->service()->syncPublished();
            Session::flash('success', $published ? 'Langue publiée : elle apparaît dans le sélecteur de langue du site.' : 'Langue enregistrée (non publiée).');
        } elseif ($action === 'recompile') {
            $n = $this->service()->recompile($code);
            $this->service()->syncPublished();
            Session::flash('success', $n . ' textes compilés pour « ' . $code . ' ».');
        }

        return Response::redirect(url(self::BASE) . '#langues');
    }

    private function ready(): bool
    {
        try {
            return $this->repo()->schemaReady();
        } catch (\Throwable) {
            return false;
        }
    }

    private function repo(): SiteContributorRepository
    {
        return $this->service()->repo();
    }

    private function service(): SiteTranslationService
    {
        return $this->service ??= new SiteTranslationService();
    }
}
