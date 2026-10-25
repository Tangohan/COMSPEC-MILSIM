<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Container;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\SiteContributorRepository;
use App\Services\Auth\AuthService;

/**
 * Rôles de contributeur (/contribuer) : présentation des rôles (traducteur, testeur, créateur de contenu,
 * développeur, support) et de leur rôle Discord, demande et suivi par le membre connecté, liste publique des
 * contributeurs qui acceptent d'être cités. L'administration valide dans /admin/system/contributeurs.
 */
final class ContributorController
{
    /** Rôle Discord équivalent, donné à la main par l'équipe (pseudo Discord demandé dans le formulaire). */
    public const DISCORD_ROLES = [
        'translator' => 'Translator',
        'tester' => 'Tester',
        'content' => 'Content creator',
        'developer' => 'Dev',
        'support' => 'Support',
    ];

    public function __construct(private ?SiteContributorRepository $repo = null)
    {
    }

    public function index(Request $request, array $params = []): Response
    {
        $user = self::user();
        $ready = $this->ready();
        $mine = [];
        $credits = [];
        if ($ready) {
            try {
                if ($user !== null) {
                    foreach ($this->repo()->rolesOfUser((int) $user['id']) as $row) {
                        $mine[(string) $row['role']] = $row;
                    }
                }
                $credits = $this->repo()->publicCredits();
            } catch (\Throwable) {
                $ready = false;
            }
        }
        $want = (string) $request->query('role', '');

        return Response::view('layout.marketing', [
            'content' => 'site.contribute',
            'title' => __('site.contrib_meta_title'),
            'meta_description' => __('site.contrib_meta_description'),
            'marketingActive' => 'contribuer',
            'marketingStyles' => [asset_url('assets/css/site-contribute.css')],
            'contribUser' => $user,
            'contribReady' => $ready,
            'contribMine' => $mine,
            'contribCredits' => $credits,
            'contribWant' => in_array($want, SiteContributorRepository::ROLES, true) ? $want : '',
            'contribLang' => preg_replace('/[^a-z,\- ]/', '', strtolower((string) $request->query('lang', ''))) ?? '',
        ]);
    }

    public function apply(Request $request, array $params = []): Response
    {
        $user = self::user();
        if ($user === null) {
            return Response::redirect(url('login'));
        }
        if (!Csrf::verify($request) || !$this->ready()) {
            Session::flash('error', __('site.contrib_flash_expired'));

            return Response::redirect(url('contribuer'));
        }
        $role = (string) $request->input('role', '');
        if (!in_array($role, SiteContributorRepository::ROLES, true)) {
            return Response::redirect(url('contribuer'));
        }
        $langs = SiteContributorRepository::splitLanguages((string) $request->input('languages', ''));
        if ($role === 'translator' && $langs === []) {
            Session::flash('error', __('site.contrib_flash_languages'));

            return Response::redirect(url('contribuer?role=translator') . '#demande');
        }
        $discord = trim((string) preg_replace('/[\x00-\x1F]+/', '', (string) $request->input('discord', '')));
        $motivation = trim((string) $request->input('motivation', ''));
        $this->repo()->apply(
            (int) $user['id'],
            $role,
            implode(',', $langs),
            mb_substr($motivation, 0, 1500),
            mb_substr($discord, 0, 64),
            (string) $request->input('public_credit', '') === '1',
        );
        Session::flash('success', __('site.contrib_flash_sent'));

        return Response::redirect(url('contribuer') . '#mes-roles');
    }

    public function withdraw(Request $request, array $params = []): Response
    {
        $user = self::user();
        if ($user === null) {
            return Response::redirect(url('login'));
        }
        $role = (string) $request->input('role', '');
        if (Csrf::verify($request) && $this->ready() && in_array($role, SiteContributorRepository::ROLES, true)) {
            $this->repo()->withdraw((int) $user['id'], $role);
            Session::flash('success', __('site.contrib_flash_withdrawn'));
        }

        return Response::redirect(url('contribuer') . '#mes-roles');
    }

    /** @return array<string, mixed>|null */
    public static function user(): ?array
    {
        try {
            $u = Container::get(AuthService::class)->user();

            return is_array($u) && (int) ($u['id'] ?? 0) > 0 ? $u : null;
        } catch (\Throwable) {
            return null;
        }
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
        return $this->repo ??= new SiteContributorRepository();
    }
}
