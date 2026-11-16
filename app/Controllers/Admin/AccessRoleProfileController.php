<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\RoleRepository;
use App\Services\Audit\AuditAction;
use App\Services\Rbac\AccessRoleColor;
use App\Services\Rbac\AccessRoleLogoStore;
use App\Services\Rbac\AccessRoleProfileForm;
use App\Services\Rbac\AccessRoleProfileService;
use App\Services\Rbac\AccessRoleProfileView;
use App\Services\Rbac\CommunityAccessProfiles;
use App\Support\EffectifsLmsAccess;

/**
 * Effectifs > Accès : fiche d'un niveau (fenêtre « Modifier la fiche »), archivage et logo servi.
 *
 * Mêmes gardes que les autres écritures de la page (EffectifsWorkspaceController::denyAccessWrite) : session, droit de voir
 * les effectifs, droit de gérer les accès, jeton CSRF. La communauté vient de la session ; le niveau doit appartenir à cette
 * communauté. La fiche ne touche ni aux droits du niveau ni à ses titulaires. Chaque écriture va au journal d'audit.
 */
final class AccessRoleProfileController
{
    public function __construct(
        private ?RoleRepository $roles = null,
        private ?AccessRoleProfileForm $form = null,
        private ?AccessRoleLogoStore $logos = null,
    ) {
        $this->roles ??= new RoleRepository();
        $this->logos ??= new AccessRoleLogoStore();
        $this->form ??= new AccessRoleProfileForm(null, $this->logos);
    }

    private static function page(int $roleId = 0, string $extra = ''): string
    {
        return effectifs_workspace_url('roles') . ($roleId > 0 ? '?role=' . $roleId . $extra : '');
    }

    private function denyWrite(Request $request, string $deniedMessage): ?Response
    {
        if (!(int) Session::get('user_id') || !(int) Session::get('tenant_id')) {
            return Response::redirect(url('login'));
        }
        $gate = Gate::getInstance();
        if (!EffectifsLmsAccess::allows($gate)) {
            Session::flash('error', 'Accès réservé au pilotage des effectifs.');

            return Response::redirect(url('dashboard'));
        }
        if (!EffectifsLmsAccess::canManageRoles($gate)) {
            Session::flash('error', $deniedMessage);

            return Response::redirect(self::page());
        }
        if (!Csrf::verify($request)) {
            Session::flash('error', 'Session expirée. Rechargez la page puis recommencez : rien n’a été enregistré.');

            return Response::redirect(self::page());
        }

        return null;
    }

    /** Enregistre la fenêtre « Fiche du niveau » : nom, description courte, couleur, puis la fiche et le logo. */
    public function save(Request $request, array $params = []): Response
    {
        unset($params);
        $denied = $this->denyWrite($request, 'Vous n’êtes pas habilité à modifier les niveaux d’accès.');
        if ($denied !== null) {
            return $denied;
        }
        $tenantId = (int) Session::get('tenant_id');
        $roleId = (int) $request->input('role_id', 0);
        if (!$this->roles->canAssignInTenantAdminContext($roleId, $tenantId)) {
            Session::flash('error', 'Ce niveau d’accès n’existe pas dans votre communauté ou ne peut pas être modifié.');

            return Response::redirect(self::page());
        }
        $reopen = self::page($roleId, '&fiche=1#rol-edit');
        $input = AccessRoleProfileView::oldInput($request);
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            return $this->refuse('Le nom du niveau ne peut pas être vide : rien n’a été enregistré.', $input, $reopen);
        }
        foreach ($this->roles->forTenantAccessProfiles($tenantId, true) as $row) {
            if ((int) ($row['id'] ?? 0) !== $roleId && mb_strtolower(trim((string) ($row['name'] ?? ''))) === mb_strtolower($name)) {
                return $this->refuse('Un autre niveau d’accès porte déjà ce nom : rien n’a été enregistré.', $input, $reopen);
            }
        }
        $color = AccessRoleColor::normalize((string) ($input['role_color'] ?? ''));
        if ($color === null) {
            return $this->refuse('Cette couleur n’est pas valide (choisissez une pastille ou un code #RRGGBB) : rien n’a été enregistré.', $input, $reopen);
        }
        $check = $this->form->check($tenantId, $roleId, $input);
        if ($check['error'] !== null) {
            return $this->refuse($check['error'] . ' Rien n’a été enregistré.', $input, $reopen);
        }
        $role = $this->roles->findById($roleId, $tenantId) ?? [];
        $changed = [];
        $description = trim((string) ($input['description'] ?? ''));
        if ($name !== trim((string) ($role['name'] ?? '')) || $description !== trim((string) ($role['description'] ?? ''))) {
            $this->roles->updateAccessRoleLabel($tenantId, $roleId, $name, $description);
            $changed[] = 'name_description';
        }
        $current = [];
        if (is_string($role['badge_style'] ?? null) && $role['badge_style'] !== '') {
            $decoded = json_decode((string) $role['badge_style'], true);
            $current = is_array($decoded) ? $decoded : [];
        }
        $before = AccessRoleColor::inputFor($role['badge_style'] ?? null);
        unset($current['color'], $current['hex']);
        $style = $current + $color;
        if ($before !== (string) ($input['role_color'] ?? '')) {
            $this->roles->updateOrganizationRoleBadgeStyle($tenantId, $roleId, $style === [] ? null : $style);
            $changed[] = 'color';
        }
        $userId = (int) Session::get('user_id');
        $applied = $this->form->apply($tenantId, $roleId, $check['data'], $input, self::file(), $userId);
        $changed = array_merge($changed, $applied['changed']);
        if ($changed !== []) {
            self::log($tenantId, $roleId, AuditAction::ACCESS_ROLE_PROFILE, ['event' => 'profile', 'fields' => $changed]);
        }
        if (!$applied['ok']) {
            Session::flash('error', 'Fiche enregistrée, mais le logo n’a pas été pris : ' . (string) $applied['error']);

            return Response::redirect($reopen);
        }
        Session::flash('success', $changed === [] ? 'Fiche du niveau enregistrée (rien n’a changé).' : 'Fiche du niveau enregistrée.');

        return Response::redirect(self::page($roleId));
    }

    /** Archive (masque de la liste sans supprimer) ou désarchive un niveau : ses droits et ses titulaires ne changent pas. */
    public function archive(Request $request, array $params = []): Response
    {
        unset($params);
        $denied = $this->denyWrite($request, 'Vous n’êtes pas habilité à archiver un niveau d’accès.');
        if ($denied !== null) {
            return $denied;
        }
        $tenantId = (int) Session::get('tenant_id');
        $roleId = (int) $request->input('role_id', 0);
        $role = $this->roles->findById($roleId, $tenantId);
        if (!$role || !CommunityAccessProfiles::isAssignableAccessSlug((string) ($role['slug'] ?? ''))) {
            Session::flash('error', 'Ce niveau d’accès n’existe pas dans votre communauté.');

            return Response::redirect(self::page());
        }
        $archive = (string) $request->input('archive', '1') === '1';
        if ($archive && strtolower((string) ($role['slug'] ?? '')) === CommunityAccessProfiles::SLUG_MEMBER) {
            Session::flash('error', 'Membre ne peut pas être archivé : c’est le socle que tout le monde porte.');

            return Response::redirect(self::page($roleId));
        }
        if (!$this->form->profiles()->ready()) {
            Session::flash('error', AccessRoleProfileService::NOT_READY);

            return Response::redirect(self::page($roleId));
        }
        $this->form->profiles()->setArchived($tenantId, $roleId, $archive, (int) Session::get('user_id'));
        self::log($tenantId, $roleId, AuditAction::ACCESS_ROLE_ARCHIVED, ['event' => $archive ? 'archive' : 'unarchive']);
        Session::flash('success', $archive
            ? 'Niveau archivé : il est rangé hors de la liste. Ses droits et ses titulaires ne changent pas.'
            : 'Niveau désarchivé : il revient dans la liste.');

        return Response::redirect(self::page($roleId));
    }

    /** Logo d'un niveau de la communauté de la session, par la route contrôlée (jamais une adresse de fichier). */
    public function logo(Request $request, array $params = []): Response
    {
        $notFound = static fn (): Response => (new Response())
            ->setStatusCode(404)
            ->header('Content-Type', 'text/plain; charset=utf-8')
            ->header('Cache-Control', 'no-store')
            ->setBody('Not found');
        $tenantId = (int) Session::get('tenant_id');
        if (!(int) Session::get('user_id') || $tenantId < 1 || !EffectifsLmsAccess::allows(Gate::getInstance())) {
            return $notFound();
        }
        $roleId = (int) ($params['id'] ?? 0);
        $meta = $this->form->profiles()->logoOf($tenantId, $roleId);
        $file = $meta !== null ? $this->logos->resolve($tenantId, $meta['file']) : null;
        if ($file === null) {
            return $notFound();
        }
        $abs = $file['path'];
        $response = (new Response())
            ->header('Content-Type', $file['mime'])
            ->header('Content-Length', (string) filesize($abs))
            ->header('Cache-Control', $request->queryString('v') !== '' ? 'private, max-age=300' : 'private, no-store')
            ->header('X-Content-Type-Options', 'nosniff')
            ->header('Referrer-Policy', 'no-referrer')
            ->header('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'; sandbox");

        return $response->setBodyStream(static function () use ($abs): void {
            $h = fopen($abs, 'rb');
            if ($h !== false) {
                fpassthru($h);
                fclose($h);
            }
        });
    }

    /** Adresse contrôlée du logo d'un niveau (la version évite une copie périmée du navigateur). */
    public static function logoUrl(int $roleId, int $version): string
    {
        return effectifs_workspace_url('roles/' . $roleId . '/logo') . '?v=' . max(1, $version);
    }

    /**
     * Saisie à reprendre dans la fenêtre après un refus (ni jeton ni fichier).
     *
     * @param array<string, mixed> $input
     */
    private function refuse(string $message, array $input, string $back): Response
    {
        Session::flash('error', $message);
        unset($input['_csrf_token']);
        Session::flash('rol_old', $input);

        return Response::redirect($back);
    }

    /** @return array<string, mixed>|null */
    private static function file(): ?array
    {
        $file = $_FILES['logo_file'] ?? null;

        return is_array($file) ? $file : null;
    }

    /** @param array<string, mixed> $extra */
    private static function log(int $tenantId, int $roleId, string $action, array $extra): void
    {
        $actorId = (int) Session::get('user_id');
        if ($actorId < 1) {
            return;
        }
        try {
            $payload = json_encode($extra + ['scope' => 'access_roles'], JSON_UNESCAPED_UNICODE);
            (new \App\Services\Audit\AuditService())->log($action, $tenantId, $actorId, 'role', $roleId, null, $payload !== false ? $payload : null);
        } catch (\Throwable $e) {
            error_log('[acces] journal : ' . $e->getMessage());
        }
    }
}
