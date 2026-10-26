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
use App\Support\UserPublicName;

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
        $leaders = [];
        if ($ready) {
            try {
                if ($user !== null) {
                    foreach ($this->repo()->rolesOfUser((int) $user['id']) as $row) {
                        $mine[(string) $row['role']] = $row;
                    }
                }
                $credits = $this->repo()->publicCredits();
                $leaders = $this->repo()->translationLeaders(8);
            } catch (\Throwable) {
                $ready = false;
            }
        }
        $want = (string) $request->query('role', '');
        $publicName = '';
        if ($user !== null) {
            try {
                $publicName = UserPublicName::get(\App\Core\Database::getPdo(), (int) $user['id']);
            } catch (\Throwable) {
            }
        }

        return Response::view('layout.marketing', [
            'content' => 'site.contribute',
            'title' => __('site.contrib_meta_title'),
            'meta_description' => __('site.contrib_meta_description'),
            'marketingActive' => 'contribuer',
            'marketingStyles' => [asset_url('assets/css/site-contribute.css')],
            'contribUser' => $user,
            'contribPublicName' => $publicName,
            'contribCallsign' => trim((string) ($user['callsign'] ?? '')),
            'contribReady' => $ready,
            'contribMine' => $mine,
            'contribCredits' => $credits,
            'contribLeaders' => $leaders,
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
        $wanted = [];
        $rawRoles = $request->input('roles', null);
        $candidates = is_array($rawRoles) ? $rawRoles : [(string) $request->input('role', '')];
        foreach ($candidates as $candidate) {
            $candidate = (string) $candidate;
            if (in_array($candidate, SiteContributorRepository::ROLES, true) && !in_array($candidate, $wanted, true)) {
                $wanted[] = $candidate;
            }
        }
        $existing = [];
        foreach ($this->repo()->rolesOfUser((int) $user['id']) as $row) {
            $existing[(string) $row['role']] = (string) $row['status'];
        }
        $send = [];
        $alreadyActive = [];
        $alreadyPending = [];
        foreach ($wanted as $role) {
            $status = $existing[$role] ?? '';
            if ($status === SiteContributorRepository::STATUS_ACTIVE) {
                $alreadyActive[] = $role;
                continue;
            }
            if ($status === SiteContributorRepository::STATUS_PENDING) {
                $alreadyPending[] = $role;
                continue;
            }
            $send[] = $role;
        }
        $langs = SiteContributorRepository::splitLanguages((string) $request->input('languages', ''));
        if (in_array('translator', $send, true) && $langs === []) {
            Session::flash('error', __('site.contrib_flash_languages'));

            return Response::redirect(url('contribuer?role=translator') . '#demande');
        }
        // Pseudo public : le nom cité dans la liste des contributeurs (à la place de l'indicatif).
        $publicName = UserPublicName::normalize((string) $request->input('public_name', ''));
        $nameError = UserPublicName::validationError($publicName);
        if ($nameError !== null) {
            Session::flash('error', __($nameError));

            return Response::redirect(url('contribuer') . '#demande');
        }
        try {
            UserPublicName::save(\App\Core\Database::getPdo(), (int) $user['id'], $publicName);
        } catch (\Throwable) {
        }
        if ($wanted === []) {
            Session::flash('error', __('site.contrib_flash_pick'));

            return Response::redirect(url('contribuer') . '#demande');
        }
        $already = [];
        if ($alreadyActive !== []) {
            $already[] = __('site.contrib_flash_already_active', ['roles' => $this->joinLabels($alreadyActive)]);
        }
        if ($alreadyPending !== []) {
            $already[] = __('site.contrib_flash_already_pending', ['roles' => $this->joinLabels($alreadyPending)]);
        }
        if ($send === []) {
            Session::flash('success', $already !== [] ? implode(' ', $already) : __('site.contrib_flash_nothing_new'));

            return Response::redirect(url('contribuer') . '#mes-roles');
        }
        $discord = trim((string) preg_replace('/[\x00-\x1F]+/', '', (string) $request->input('discord', '')));
        $motivation = trim((string) $request->input('motivation', ''));
        $credit = (string) $request->input('public_credit', '') === '1';
        foreach ($send as $role) {
            $this->repo()->apply(
                (int) $user['id'],
                $role,
                $role === 'translator' ? implode(',', $langs) : '',
                mb_substr($motivation, 0, 1500),
                mb_substr($discord, 0, 64),
                $credit,
            );
        }
        $sentKey = count($send) > 1 ? 'site.contrib_flash_sent_multi' : 'site.contrib_flash_sent_one';
        $parts = [__($sentKey, ['roles' => $this->joinLabels($send)])];
        Session::flash('success', implode(' ', array_merge($parts, $already)));

        return Response::redirect(url('contribuer') . '#mes-roles');
    }

    private function joinLabels(array $roles): string
    {
        $labels = [];
        foreach ($roles as $role) {
            $label = __('site.contrib_role_' . $role);
            $labels[] = $label === 'site.contrib_role_' . $role ? __('site.contrib_role_unknown') : $label;
        }
        if (count($labels) < 2) {
            return $labels[0] ?? '';
        }
        $last = array_pop($labels);

        return implode(', ', $labels) . ' ' . __('site.contrib_and') . ' ' . $last;
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
