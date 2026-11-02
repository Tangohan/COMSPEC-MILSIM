<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Container;
use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\Audit\AuditService;
use App\Services\Auth\AuthService;
use App\Services\Personnel\MemberNameService;
use App\Services\Personnel\MemberNameSettings;
use App\Support\MemberName;

/**
 * Back-office › Organisation › Noms et trigrammes : format d’affichage des noms de la communauté
 * et registre des trigrammes (attribution automatique, saisie à la main, recalcul sur demande).
 */
final class OrganizationMemberNamesController
{
    private const PATH = 'back-office/organisation/noms';

    public function __construct(
        private ?AuthService $authService = null,
        private ?MemberNameService $names = null,
        private ?Gate $gate = null,
    ) {
        $this->authService ??= Container::get(AuthService::class);
        $this->names ??= new MemberNameService();
        $this->gate ??= Gate::getInstance();
    }

    public function index(Request $request, array $params = []): Response
    {
        if (!$this->authService->check()) {
            return Response::redirect(url('login'));
        }
        if (!$this->canManage()) {
            Session::flash('error', 'Vous n’avez pas l’habilitation pour régler l’affichage des noms de la communauté.');

            return Response::redirect(url('back-office'));
        }
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            return Response::redirect(url('dashboard'));
        }
        $schemaReady = $this->names->schemaReady();
        $members = [];
        if ($schemaReady) {
            try {
                $members = $this->names->registry($tenantId);
            } catch (\Throwable $e) {
                error_log('[noms] registre indisponible : ' . $e->getMessage());
            }
        }
        $filter = $request->queryString('filtre') === 'sans' ? 'sans' : 'tous';

        return Response::view('layout.main', [
            'title' => 'Noms et trigrammes',
            'content' => 'admin.organization.member_names',
            'isBackOfficeShell' => true,
            'boPageGroup' => 'Organisation',
            'boPageTitle' => 'Noms et trigrammes',
            'boPageKicker' => 'PERSONNEL · CONFIGURATION',
            'boPageSubtitle' => 'Comment les noms des membres s’affichent sur le site, et le trigramme de chacun.',
            'memberNamesSchemaReady' => $schemaReady,
            'memberNamesSettings' => MemberNameSettings::forTenant($tenantId),
            'memberNamesMembers' => $members,
            'memberNamesFilter' => $filter,
            'memberNamesCsrf' => Csrf::token(),
            'showPortalFooter' => false,
        ]);
    }

    public function save(Request $request, array $params = []): Response
    {
        $guard = $this->guard($request, false);
        if ($guard instanceof Response) {
            return $guard;
        }
        $tenantId = (int) Session::get('tenant_id');
        MemberNameSettings::save($tenantId, [
            'list_format' => $request->inputString('list_format', MemberName::SHORT),
            'card_format' => $request->inputString('card_format', MemberName::FULL),
            'show_grade' => $request->inputString('show_grade') === '1',
            'trigrams_enabled' => $request->inputString('trigrams_enabled') === '1',
        ]);
        $this->audit('member_names.settings', $tenantId, []);
        Session::flash('success', 'Affichage des noms enregistré.');

        return $this->back();
    }

    public function assignMissing(Request $request, array $params = []): Response
    {
        $guard = $this->guard($request, true);
        if ($guard instanceof Response) {
            return $guard;
        }
        $r = $this->names->assignMissing((int) Session::get('tenant_id'));
        Session::flash('success', $r['failed'] > 0
            ? sprintf('%d trigramme(s) attribué(s), %d membre(s) sans prénom ni nom exploitable : saisissez leur trigramme à la main.', $r['assigned'], $r['failed'])
            : sprintf('%d trigramme(s) attribué(s).', $r['assigned']));

        return $this->back();
    }

    /** Trigramme d’un membre : saisi à la main, ou recalculé depuis son nom actuel (« recalculer »). */
    public function updateMember(Request $request, array $params = []): Response
    {
        $guard = $this->guard($request, true);
        if ($guard instanceof Response) {
            return $guard;
        }
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) $request->inputString('user_id');
        if ($userId < 1 || !in_array($userId, $this->names->memberIds($tenantId), true)) {
            Session::flash('error', 'Membre introuvable dans la communauté.');

            return $this->back();
        }
        $before = $this->names->trigramFor($tenantId, $userId);
        if ($request->inputString('action') === 'recalculer') {
            $t = $this->names->regenerate($tenantId, $userId);
            if ($t === null) {
                Session::flash('error', 'Aucun trigramme libre ne se déduit du nom de ce membre : saisissez-le à la main.');

                return $this->back();
            }
            $this->audit('member_names.trigram', $tenantId, ['user_id' => $userId, 'before' => $before, 'after' => $t, 'source' => 'auto']);
            Session::flash('success', 'Trigramme recalculé : ' . $t . '.');

            return $this->back();
        }
        $r = $this->names->setTrigram($tenantId, $userId, $request->inputString('trigram'));
        if (!$r['ok']) {
            Session::flash('error', (string) ($r['error'] ?? 'Trigramme refusé.'));

            return $this->back();
        }
        $this->audit('member_names.trigram', $tenantId, ['user_id' => $userId, 'before' => $before, 'after' => (string) $r['trigram'], 'source' => 'manual']);
        Session::flash('success', 'Trigramme enregistré : ' . (string) $r['trigram'] . '.');

        return $this->back();
    }

    private function guard(Request $request, bool $needsSchema): ?Response
    {
        if (!$this->authService->check() || !$this->canManage()) {
            return Response::redirect(url('login'));
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return $this->back();
        }
        if ((int) Session::get('tenant_id') < 1 || ($needsSchema && !$this->names->schemaReady())) {
            Session::flash('error', 'Les trigrammes seront disponibles après la prochaine mise à jour de la plateforme.');

            return $this->back();
        }

        return null;
    }

    /** @param array<string, mixed> $details */
    private function audit(string $action, int $tenantId, array $details): void
    {
        try {
            Container::get(AuditService::class)->logChange($action, $tenantId, (int) Session::get('user_id') ?: null, 'member_names', null, [], $details);
        } catch (\Throwable $e) {
            error_log('[audit] ' . $action . ' : ' . $e->getMessage());
        }
    }

    private function back(): Response
    {
        return Response::redirect(url(self::PATH));
    }

    public function canManage(): bool
    {
        return $this->gate->allows('admin.organization')
            || $this->gate->allows('admin.members.manage')
            || $this->gate->allows('personnel.member_number.manage');
    }
}
