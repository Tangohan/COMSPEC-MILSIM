<?php

declare(strict_types=1);

namespace App\Controllers\Admin\System;

use App\Core\Container;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\NameFilterRepository;
use App\Repositories\TenantRepository;
use App\Services\Audit\AuditAction;
use App\Services\Audit\AuditService;
use App\Services\Moderation\NameFilter;
use App\Services\Moderation\NameModerationService;

/** Administration du site : mots interdits dans les noms (FR + EN) et alertes de noms signalés. */
final class SystemNameFilterController
{
    private const LANGS = ['fr', 'en', 'all'];

    public function __construct(
        private NameFilterRepository $repo,
        private NameModerationService $moderation,
        private AuditService $audit,
    ) {}

    public function index(Request $request, array $params = []): Response
    {
        unset($params);
        $tab = (string) $request->query('vue', '') === 'mots' ? 'mots' : 'alertes';
        $showAll = (string) $request->query('tout', '') === '1';
        $ready = true;
        $words = [];
        $alerts = [];
        $openCount = 0;
        try {
            $words = $this->repo->listWords();
            $alerts = $this->repo->listAlerts(!$showAll);
            $openCount = $this->repo->countOpenAlerts();
        } catch (\Throwable) {
            $ready = false;
        }

        return Response::view('layout.main', [
            'title' => 'Noms inappropriés',
            'content' => 'admin.system.name_filter',
            'isPlatformAdminShell' => true,
            'backOfficePageCss' => ['platform-admin.css'],
            'nfReady' => $ready,
            'nfTab' => $tab,
            'nfShowAll' => $showAll,
            'nfWords' => $words,
            'nfAlerts' => $alerts,
            'nfOpenCount' => $openCount,
        ]);
    }

    public function addWord(Request $request, array $params = []): Response
    {
        unset($params);
        if ($bad = $this->rejectCsrf($request, 'mots')) {
            return $bad;
        }
        $word = NameFilter::normalizeWord((string) $request->input('word', ''));
        $lang = (string) $request->input('lang', 'all');
        $mode = (string) $request->input('mode', NameFilter::MODE_WORD) === NameFilter::MODE_PARTIAL
            ? NameFilter::MODE_PARTIAL
            : NameFilter::MODE_WORD;
        if (!in_array($lang, self::LANGS, true)) {
            $lang = 'all';
        }
        if ($word === '' || mb_strlen($word) < 2 || mb_strlen($word) > 120) {
            Session::flash('error', 'Saisissez un mot de 2 à 120 lettres.');

            return $this->back('mots');
        }
        if (!$this->repo->addWord($word, $lang, $mode, $this->userId())) {
            Session::flash('error', 'Ce mot est déjà dans la liste.');

            return $this->back('mots');
        }
        $this->changed('add:' . $word);
        Session::flash('success', 'Mot ajouté. Il est flouté dès maintenant sur les pages publiques.');

        return $this->back('mots');
    }

    public function updateWord(Request $request, array $params = []): Response
    {
        if ($bad = $this->rejectCsrf($request, 'mots')) {
            return $bad;
        }
        $id = (int) ($params['id'] ?? 0);
        $word = NameFilter::normalizeWord((string) $request->input('word', ''));
        $lang = (string) $request->input('lang', 'all');
        $mode = (string) $request->input('mode', NameFilter::MODE_WORD) === NameFilter::MODE_PARTIAL
            ? NameFilter::MODE_PARTIAL
            : NameFilter::MODE_WORD;
        if (!in_array($lang, self::LANGS, true)) {
            $lang = 'all';
        }
        if ($word === '' || mb_strlen($word) < 2 || mb_strlen($word) > 120) {
            Session::flash('error', 'Saisissez un mot de 2 à 120 lettres.');

            return $this->back('mots');
        }
        $result = $id > 0 ? $this->repo->updateWord($id, $word, $lang, $mode) : 'missing';
        if ($result !== 'ok') {
            Session::flash('error', $result === 'duplicate' ? 'Ce mot est déjà dans la liste.' : 'Ce mot est introuvable.');

            return $this->back('mots');
        }
        $this->changed('update:' . $id . ':' . $word);
        Session::flash('success', 'Mot modifié.');

        return $this->back('mots');
    }

    /** Renomme la communauté ou l’unité d’une alerte ; l’alerte se ferme si le nouveau nom est propre. */
    public function renameSubject(Request $request, array $params = []): Response
    {
        if ($bad = $this->rejectCsrf($request, 'alertes')) {
            return $bad;
        }
        $id = (int) ($params['id'] ?? 0);
        $alert = $id > 0 ? $this->repo->findAlert($id) : null;
        if ($alert === null) {
            Session::flash('error', 'Cette alerte est introuvable.');

            return $this->back('alertes');
        }
        $name = trim(preg_replace('/\s+/u', ' ', (string) $request->input('name', '')) ?? '');
        if (mb_strlen($name) < 2 || mb_strlen($name) > 120) {
            Session::flash('error', 'Le nouveau nom doit faire entre 2 et 120 caractères.');

            return $this->back('alertes');
        }
        $subjectId = (int) ($alert['subject_id'] ?? 0);
        $tenantId = (int) ($alert['tenant_id'] ?? 0);
        $isTenant = ($alert['subject_type'] ?? '') === NameModerationService::SUBJECT_TENANT;
        try {
            if ($isTenant) {
                /** @var TenantRepository $tenants */
                $tenants = Container::get(TenantRepository::class);
                if ($subjectId < 1 || $tenants->findById($subjectId) === null) {
                    Session::flash('error', 'Cette communauté n’existe plus.');

                    return $this->back('alertes');
                }
                $tenants->updateName($subjectId, $name);
            } else {
                $st = Database::getPdo()->prepare('UPDATE units SET name = ? WHERE id = ?');
                $st->execute([$name, $subjectId]);
                if ($st->rowCount() < 1) {
                    Session::flash('error', 'Cette unité n’existe plus ou porte déjà ce nom.');

                    return $this->back('alertes');
                }
                NameModerationService::afterSave(NameModerationService::SUBJECT_UNIT, $subjectId, $tenantId > 0 ? $tenantId : null, $name);
            }
        } catch (\Throwable) {
            Session::flash('error', 'Le nom n’a pas pu être enregistré.');

            return $this->back('alertes');
        }
        $this->audit->log(
            AuditAction::PLATFORM_NAME_ALERT_RESOLVED,
            $tenantId > 0 ? $tenantId : null,
            $this->userId(),
            'name_filter_alert',
            $id,
            (string) $alert['name'],
            'renamed:' . $name
        );
        $stillOpen = $this->repo->findAlert($id);
        if (($stillOpen['status'] ?? '') === 'open') {
            Session::flash('error', 'Nom changé, mais le nouveau nom contient encore un mot de la liste : l’alerte reste ouverte.');
        } else {
            Session::flash('success', ($isTenant ? 'Communauté' : 'Unité') . ' renommée « ' . $name . ' ». L’alerte est fermée.');
        }

        return $this->back('alertes');
    }

    public function deleteWord(Request $request, array $params = []): Response
    {
        if ($bad = $this->rejectCsrf($request, 'mots')) {
            return $bad;
        }
        $id = (int) ($params['id'] ?? 0);
        if ($id < 1 || !$this->repo->deleteWord($id)) {
            Session::flash('error', 'Ce mot est introuvable.');

            return $this->back('mots');
        }
        $this->changed('delete:' . $id);
        Session::flash('success', 'Mot retiré de la liste.');

        return $this->back('mots');
    }

    public function restoreDefaults(Request $request, array $params = []): Response
    {
        unset($params);
        if ($bad = $this->rejectCsrf($request, 'mots')) {
            return $bad;
        }
        $added = $this->repo->restoreDefaults();
        $this->changed('restore_defaults');
        Session::flash('success', $added > 0
            ? $added . ' mot(s) de la liste d’origine remis.'
            : 'La liste contient déjà tous les mots d’origine.');

        return $this->back('mots');
    }

    public function scan(Request $request, array $params = []): Response
    {
        unset($params);
        if ($bad = $this->rejectCsrf($request, 'alertes')) {
            return $bad;
        }
        $opened = $this->moderation->scanExisting();
        Session::flash('success', $opened > 0
            ? $opened . ' nom(s) existant(s) signalé(s).'
            : 'Aucun nouveau nom à signaler parmi les communautés et unités existantes.');

        return $this->back('alertes');
    }

    public function resolveAlert(Request $request, array $params = []): Response
    {
        if ($bad = $this->rejectCsrf($request, 'alertes')) {
            return $bad;
        }
        $id = (int) ($params['id'] ?? 0);
        $alert = $id > 0 ? $this->repo->findAlert($id) : null;
        if ($alert === null) {
            Session::flash('error', 'Cette alerte est introuvable.');

            return $this->back('alertes');
        }
        $allow = (string) $request->input('decision', '') === 'allow';
        if ($allow) {
            $this->repo->allowName(NameFilter::normalizeWord((string) $alert['name']), $this->userId());
            NameModerationService::resetCache();
        }
        $this->repo->resolveAlert($id, $allow ? 'allowed' : 'handled', $this->userId());
        $tenantId = (int) ($alert['tenant_id'] ?? 0);
        $this->audit->log(
            AuditAction::PLATFORM_NAME_ALERT_RESOLVED,
            $tenantId > 0 ? $tenantId : null,
            $this->userId(),
            'name_filter_alert',
            $id,
            (string) $alert['name'],
            $allow ? 'allowed' : 'handled'
        );
        Session::flash('success', $allow
            ? 'Nom autorisé : il n’est plus flouté.'
            : 'Alerte marquée comme traitée. Le nom reste flouté tant qu’il n’est pas changé.');

        return $this->back('alertes');
    }

    private function changed(string $detail): void
    {
        NameModerationService::resetCache();
        $this->audit->log(AuditAction::PLATFORM_NAME_FILTER_UPDATED, null, $this->userId(), 'name_filter', null, null, $detail);
    }

    private function rejectCsrf(Request $request, string $tab): ?Response
    {
        if (Csrf::validate((string) $request->input('_csrf_token'))) {
            return null;
        }
        Session::flash('error', 'La session a expiré. Recommencez.');

        return $this->back($tab);
    }

    private function back(string $tab): Response
    {
        return Response::redirect(url('admin/system/noms-inappropries') . ($tab === 'mots' ? '?vue=mots' : ''));
    }

    private function userId(): ?int
    {
        $id = (int) Session::get('user_id');

        return $id > 0 ? $id : null;
    }
}
