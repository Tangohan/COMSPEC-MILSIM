<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\RoleRepository;
use App\Repositories\TenantEmailCampaignRepository;
use App\Repositories\TenantEmailRecipientGroupRepository;
use App\Repositories\TenantEmailTemplateRepository;
use App\Repositories\UnitRepository;
use App\Repositories\UserRepository;
use App\Services\Communications\TenantEmailDispatchService;
use App\Services\Communications\TenantEmailRenderService;
use App\Support\TenantEmailKind;

final class TenantCommunicationsController
{
    public function __construct(
        private TenantEmailTemplateRepository $templateRepository,
        private TenantEmailRecipientGroupRepository $recipientGroupRepository,
        private TenantEmailCampaignRepository $campaignRepository,
        private TenantEmailDispatchService $dispatchService,
        private TenantEmailRenderService $renderService,
        private UnitRepository $unitRepository,
        private UserRepository $userRepository,
        private RoleRepository $roleRepository
    ) {}

    public function compose(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        if (!$tenantId || !$userId) {
            return Response::redirect(url('login'));
        }
        $this->templateRepository->ensurePrefabsForTenant($tenantId, $userId);
        $templates = $this->templateRepository->listForTenant($tenantId);
        $templatesJs = [];
        foreach ($templates as $t) {
            $templatesJs[] = [
                'id' => (int) ($t['id'] ?? 0),
                'kind' => (string) ($t['kind'] ?? ''),
                'subject' => (string) ($t['subject'] ?? ''),
                'body_html' => (string) ($t['body_html'] ?? ''),
            ];
        }

        return Response::view('layout.main', [
            'content' => 'admin.organization.communications.compose',
            'title' => 'E-mails aux membres',
            'kinds' => $this->kindsVisibleForGate(),
            'templates' => $templates,
            'templates_js' => $templatesJs,
            'groups' => $this->recipientGroupRepository->listForTenant($tenantId),
            'units_flat' => $this->unitRepository->listFlatForStructure($tenantId),
            'org_roles' => $this->roleRepository->forTenantOrganization($tenantId),
            'members' => $this->userRepository->allForTenant($tenantId),
        ]);
    }

    public function send(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        if (!$tenantId || !$userId) {
            return Response::redirect(url('login'));
        }
        if (!$request->isPost() || !Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');
            return Response::redirect(url('back-office/communications'));
        }
        $kind = trim((string) $request->input('kind'));
        $subject = trim((string) $request->input('subject'));
        $html = (string) $request->input('body_html');
        $text = trim((string) $request->input('body_text'));
        $templateId = (int) $request->input('template_id');
        $groupId = (int) $request->input('group_id');

        $definition = $this->buildDefinitionFromRequest($request, $tenantId, $groupId);
        if ($definition === null) {
            Session::flash('error', 'Choisissez un groupe enregistré ou au moins un critère de destinataires.');
            return Response::redirect(url('back-office/communications'));
        }
        if ($subject === '' || trim(strip_tags($html)) === '') {
            Session::flash('error', 'Le sujet et le message sont requis.');
            return Response::redirect(url('back-office/communications'));
        }

        $result = $this->dispatchService->dispatch(
            $tenantId,
            $userId,
            $kind,
            $subject,
            $html,
            $text !== '' ? $text : null,
            $definition,
            $templateId > 0 ? $templateId : null
        );
        Session::flash($result['ok'] ? 'success' : 'error', $result['message']);

        return Response::redirect(url('back-office/communications'));
    }

    public function preview(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        if (!$tenantId || !$userId) {
            return Response::json(['ok' => false, 'error' => 'Non authentifié'], 401);
        }
        if (!$request->isPost() || !Csrf::validate($request->input('_csrf_token'))) {
            return Response::json(['ok' => false, 'error' => 'Jeton de sécurité invalide.'], 403);
        }
        $sampleUserId = (int) $request->input('sample_user_id');
        if ($sampleUserId < 1) {
            return Response::json(['ok' => false, 'error' => 'Choisissez un membre pour l’aperçu.'], 422);
        }
        $u = $this->userRepository->findById($sampleUserId, $tenantId);
        if (!$u) {
            return Response::json(['ok' => false, 'error' => 'Membre introuvable.'], 404);
        }
        $subject = trim((string) $request->input('subject'));
        $html = (string) $request->input('body_html');
        $text = trim((string) $request->input('body_text'));
        if ($subject === '') {
            return Response::json(['ok' => false, 'error' => 'Sujet requis pour l’aperçu.'], 422);
        }
        $out = $this->renderService->renderForUser(
            $tenantId,
            $sampleUserId,
            $subject,
            $html,
            $text !== '' ? $text : null
        );

        return Response::json([
            'ok' => true,
            'subject' => $out['subject'],
            'html' => $out['html'],
            'text' => $out['text'],
        ]);
    }

    public function templates(Request $request, array $params = []): Response
    {
        if (!Gate::getInstance()->allows('comms.email_templates.manage')) {
            Session::flash('error', 'Accès réservé aux habilitations « modèles d’e-mail ».');
            return Response::redirect(url('back-office/communications'));
        }
        $tenantId = (int) Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }

        return Response::view('layout.main', [
            'content' => 'admin.organization.communications.templates',
            'title' => 'Modèles d’e-mail',
            'templates' => $this->templateRepository->listForTenant($tenantId),
            'kinds' => TenantEmailKind::all(),
        ]);
    }

    public function templateCreate(Request $request, array $params = []): Response
    {
        if (!Gate::getInstance()->allows('comms.email_templates.manage')) {
            Session::flash('error', 'Accès réservé.');
            return Response::redirect(url('back-office/communications'));
        }

        return Response::view('layout.main', [
            'content' => 'admin.organization.communications.template_form',
            'title' => 'Nouveau modèle',
            'template' => null,
            'kinds' => TenantEmailKind::all(),
        ]);
    }

    public function templateStore(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        if (!$tenantId || !$userId || !Gate::getInstance()->allows('comms.email_templates.manage')) {
            return Response::redirect(url('back-office/communications'));
        }
        if (!$request->isPost() || !Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');
            return Response::redirect(url('back-office/communications/templates/create'));
        }
        $kind = trim((string) $request->input('kind'));
        if (!TenantEmailKind::isValid($kind)) {
            Session::flash('error', 'Type de modèle invalide.');
            return Response::redirect(url('back-office/communications/templates/create'));
        }
        $name = trim((string) $request->input('name'));
        $subject = trim((string) $request->input('subject'));
        $html = (string) $request->input('body_html');
        if ($name === '' || $subject === '' || trim(strip_tags($html)) === '') {
            Session::flash('error', 'Nom, sujet et message sont requis.');
            return Response::redirect(url('back-office/communications/templates/create'));
        }
        $this->templateRepository->create($tenantId, $userId, [
            'kind' => $kind,
            'name' => $name,
            'subject' => $subject,
            'body_html' => $html,
            'body_text' => trim((string) $request->input('body_text')) ?: null,
            'is_prefab' => false,
        ]);
        Session::flash('success', 'Modèle enregistré.');

        return Response::redirect(url('back-office/communications/templates'));
    }

    public function templateEdit(Request $request, array $params = []): Response
    {
        if (!Gate::getInstance()->allows('comms.email_templates.manage')) {
            Session::flash('error', 'Accès réservé.');
            return Response::redirect(url('back-office/communications'));
        }
        $tenantId = (int) Session::get('tenant_id');
        $id = (int) ($params['id'] ?? 0);
        $tpl = $id ? $this->templateRepository->findById($id, $tenantId) : null;
        if (!$tpl) {
            Session::flash('error', 'Modèle introuvable.');
            return Response::redirect(url('back-office/communications/templates'));
        }
        if (!empty($tpl['is_prefab'])) {
            Session::flash('error', 'Les textes d’aide fournis par le site ne peuvent pas être modifiés ici ; créez une copie en dupliquant le contenu dans un nouveau modèle.');
            return Response::redirect(url('back-office/communications/templates'));
        }

        return Response::view('layout.main', [
            'content' => 'admin.organization.communications.template_form',
            'title' => 'Modifier le modèle',
            'template' => $tpl,
            'kinds' => TenantEmailKind::all(),
        ]);
    }

    public function templateUpdate(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        $id = (int) ($params['id'] ?? 0);
        if (!$tenantId || !$userId || !Gate::getInstance()->allows('comms.email_templates.manage')) {
            return Response::redirect(url('back-office/communications'));
        }
        if (!$request->isPost() || !Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');
            return Response::redirect(url('back-office/communications/templates'));
        }
        $tpl = $this->templateRepository->findById($id, $tenantId);
        if (!$tpl || !empty($tpl['is_prefab'])) {
            Session::flash('error', 'Modification impossible.');
            return Response::redirect(url('back-office/communications/templates'));
        }
        $kind = trim((string) $request->input('kind'));
        if (!TenantEmailKind::isValid($kind)) {
            Session::flash('error', 'Type invalide.');
            return Response::redirect(url('back-office/communications/templates/' . $id . '/edit'));
        }
        $name = trim((string) $request->input('name'));
        $subject = trim((string) $request->input('subject'));
        $html = (string) $request->input('body_html');
        if ($name === '' || $subject === '' || trim(strip_tags($html)) === '') {
            Session::flash('error', 'Champs requis manquants.');
            return Response::redirect(url('back-office/communications/templates/' . $id . '/edit'));
        }
        $this->templateRepository->update($id, $tenantId, [
            'kind' => $kind,
            'name' => $name,
            'subject' => $subject,
            'body_html' => $html,
            'body_text' => trim((string) $request->input('body_text')) ?: null,
        ]);
        Session::flash('success', 'Modèle mis à jour.');

        return Response::redirect(url('back-office/communications/templates'));
    }

    public function templateDelete(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $id = (int) ($params['id'] ?? 0);
        if (!$tenantId || !Gate::getInstance()->allows('comms.email_templates.manage')) {
            return Response::redirect(url('back-office/communications'));
        }
        if (!$request->isPost() || !Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');
            return Response::redirect(url('back-office/communications/templates'));
        }
        if ($this->templateRepository->delete($id, $tenantId)) {
            Session::flash('success', 'Modèle supprimé.');
        } else {
            Session::flash('error', 'Suppression impossible (modèle système ou introuvable).');
        }

        return Response::redirect(url('back-office/communications/templates'));
    }

    public function groups(Request $request, array $params = []): Response
    {
        if (!Gate::getInstance()->allows('comms.email.send.orbat')
            && !Gate::getInstance()->allows('comms.email.send.mission')
            && !Gate::getInstance()->allows('comms.email.send.activity')
            && !Gate::getInstance()->allows('comms.email.send.custom')
            && !Gate::getInstance()->allows('comms.email.broadcast')) {
            Session::flash('error', 'Aucune habilitation d’envoi.');
            return Response::redirect(url('dashboard'));
        }
        $tenantId = (int) Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }

        return Response::view('layout.main', [
            'content' => 'admin.organization.communications.groups',
            'title' => 'Groupes de destinataires',
            'groups' => $this->recipientGroupRepository->listForTenant($tenantId),
        ]);
    }

    public function groupCreate(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }

        return Response::view('layout.main', [
            'content' => 'admin.organization.communications.group_form',
            'title' => 'Nouveau groupe',
            'group' => null,
            'definition' => [],
            'units_flat' => $this->unitRepository->listFlatForStructure($tenantId),
            'org_roles' => $this->roleRepository->forTenantOrganization($tenantId),
            'members' => $this->userRepository->allForTenant($tenantId),
        ]);
    }

    public function groupStore(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        if (!$tenantId || !$userId) {
            return Response::redirect(url('login'));
        }
        if (!$request->isPost() || !Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');
            return Response::redirect(url('back-office/communications/groups/create'));
        }
        $name = trim((string) $request->input('name'));
        if ($name === '') {
            Session::flash('error', 'Le nom du groupe est requis.');
            return Response::redirect(url('back-office/communications/groups/create'));
        }
        $def = $this->definitionFromGroupForm($request);
        if ($def === null) {
            Session::flash('error', 'Indiquez au moins un critère (tout le monde, unités, rôles ou membres nommés).');
            return Response::redirect(url('back-office/communications/groups/create'));
        }
        $this->recipientGroupRepository->create($tenantId, $userId, [
            'name' => $name,
            'description' => trim((string) $request->input('description')) ?: null,
            'definition' => $def,
        ]);
        Session::flash('success', 'Groupe enregistré.');

        return Response::redirect(url('back-office/communications/groups'));
    }

    public function groupEdit(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $id = (int) ($params['id'] ?? 0);
        $row = $id ? $this->recipientGroupRepository->findById($id, $tenantId) : null;
        if (!$tenantId || !$row) {
            Session::flash('error', 'Groupe introuvable.');
            return Response::redirect(url('back-office/communications/groups'));
        }
        $def = [];
        if (!empty($row['definition_json'])) {
            $decoded = json_decode((string) $row['definition_json'], true);
            $def = is_array($decoded) ? $decoded : [];
        }

        return Response::view('layout.main', [
            'content' => 'admin.organization.communications.group_form',
            'title' => 'Modifier le groupe',
            'group' => $row,
            'definition' => $def,
            'units_flat' => $this->unitRepository->listFlatForStructure($tenantId),
            'org_roles' => $this->roleRepository->forTenantOrganization($tenantId),
            'members' => $this->userRepository->allForTenant($tenantId),
        ]);
    }

    public function groupUpdate(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $id = (int) ($params['id'] ?? 0);
        if (!$tenantId || !$request->isPost() || !Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');
            return Response::redirect(url('back-office/communications/groups'));
        }
        $name = trim((string) $request->input('name'));
        if ($name === '') {
            Session::flash('error', 'Le nom est requis.');
            return Response::redirect(url('back-office/communications/groups/' . $id . '/edit'));
        }
        $def = $this->definitionFromGroupForm($request);
        if ($def === null) {
            Session::flash('error', 'Indiquez au moins un critère.');
            return Response::redirect(url('back-office/communications/groups/' . $id . '/edit'));
        }
        $this->recipientGroupRepository->update($id, $tenantId, [
            'name' => $name,
            'description' => trim((string) $request->input('description')) ?: null,
            'definition' => $def,
        ]);
        Session::flash('success', 'Groupe mis à jour.');

        return Response::redirect(url('back-office/communications/groups'));
    }

    public function groupDelete(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $id = (int) ($params['id'] ?? 0);
        if (!$tenantId || !$request->isPost() || !Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');
            return Response::redirect(url('back-office/communications/groups'));
        }
        if ($this->recipientGroupRepository->delete($id, $tenantId)) {
            Session::flash('success', 'Groupe supprimé.');
        } else {
            Session::flash('error', 'Suppression impossible.');
        }

        return Response::redirect(url('back-office/communications/groups'));
    }

    public function history(Request $request, array $params = []): Response
    {
        if (!Gate::getInstance()->allows('comms.notifications.history.view')) {
            Session::flash('error', 'Accès réservé à l’historique des envois.');
            return Response::redirect(url('back-office/communications'));
        }
        $tenantId = (int) Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }

        return Response::view('layout.main', [
            'content' => 'admin.organization.communications.history',
            'title' => 'Historique des envois',
            'campaigns' => $this->campaignRepository->listRecent($tenantId, 60),
        ]);
    }

    /** @return list<array{kind: string, label: string}> */
    private function kindsVisibleForGate(): array
    {
        $g = Gate::getInstance();
        $out = [];
        foreach (TenantEmailKind::all() as $k) {
            if ($g->allows(TenantEmailKind::permissionForKind($k))) {
                $out[] = ['kind' => $k, 'label' => TenantEmailKind::label($k)];
            }
        }

        return $out;
    }

    /** @return array<string, mixed>|null */
    private function buildDefinitionFromRequest(Request $request, int $tenantId, int $groupId): ?array
    {
        if ($groupId > 0) {
            $g = $this->recipientGroupRepository->findById($groupId, $tenantId);
            if ($g && !empty($g['definition_json'])) {
                $decoded = json_decode((string) $g['definition_json'], true);

                return is_array($decoded) ? $decoded : null;
            }
        }

        return $this->definitionFromGroupForm($request);
    }

    /** @return array<string, mixed>|null */
    private function definitionFromGroupForm(Request $request): ?array
    {
        $allMembers = $request->input('all_members') ? true : false;
        $unitIds = array_map('intval', (array) $request->input('unit_ids'));
        $unitIds = array_values(array_filter($unitIds, static fn (int $x): bool => $x > 0));
        $includeDesc = $request->input('include_descendants') ? true : false;
        $roleSlugs = [];
        foreach ((array) $request->input('role_slugs') as $rs) {
            $s = trim((string) $rs);
            if ($s !== '') {
                $roleSlugs[] = $s;
            }
        }
        $roleSlugs = array_values(array_unique($roleSlugs));
        $extra = array_map('intval', (array) $request->input('extra_user_ids'));
        $extra = array_values(array_filter($extra, static fn (int $x): bool => $x > 0));

        if (!$allMembers && $unitIds === [] && $roleSlugs === [] && $extra === []) {
            return null;
        }

        return [
            'all_members' => $allMembers,
            'unit_ids' => $unitIds,
            'include_descendants' => $includeDesc,
            'role_slugs' => $roleSlugs,
            'extra_user_ids' => $extra,
        ];
    }
}
