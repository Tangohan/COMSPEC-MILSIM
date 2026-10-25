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
            'contribDiscordRoles' => ContributorController::DISCORD_ROLES,
            'contribBase' => self::BASE,
            'isPlatformAdminShell' => true,
            'backOfficePageCss' => ['platform-admin.css', 'admin-registry.css'],
        ]);
    }

    /** POST …/role : action accept | reject | revoke, note, langues (traducteur). */
    public function role(Request $request, array $params = []): Response
    {
        $adminId = (int) (ContributorController::user()['id'] ?? 0);
        if (!Csrf::verify($request) || !$this->ready() || $adminId < 1) {
            Session::flash('error', 'Session expirée, rechargez la page.');

            return Response::redirect(url(self::BASE));
        }
        $row = $this->repo()->findRole((int) $request->input('id', 0));
        $action = (string) $request->input('action', '');
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
        $msg = match ($status) {
            SiteContributorRepository::STATUS_ACTIVE => 'Rôle accordé.' . ($discord !== ''
                ? ' Pensez à donner le rôle Discord « ' . (ContributorController::DISCORD_ROLES[(string) $row['role']] ?? '?') . ' » à ' . $discord . '.'
                : ''),
            SiteContributorRepository::STATUS_REJECTED => 'Demande refusée.',
            default => 'Rôle retiré.' . ($discord !== '' ? ' Retirez aussi le rôle Discord de ' . $discord . '.' : ''),
        };
        Session::flash('success', $msg);

        return Response::redirect(url(self::BASE) . '#r' . (int) $row['id']);
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
