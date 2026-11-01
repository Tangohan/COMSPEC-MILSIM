<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Web\ProfileInviteController;
use App\Core\Gate;
use App\Services\Effectifs\NewMemberDossierService;
use App\Support\EffectifsLmsAccess;
use App\Support\RosterOnlyMember;
use PHPUnit\Framework\TestCase;

/** Ajouter un membre + lien d'invitation : droits, parcours du destinataire, aucun e-mail automatique. */
final class MemberInviteFlowTest extends TestCase
{
    private static function read(string $path): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/' . $path);
    }

    protected function tearDown(): void
    {
        Gate::reset();
    }

    public function testRights(): void
    {
        $gate = Gate::getInstance();
        $gate->setPermissions(['personnel.grades.manage']);
        self::assertTrue(EffectifsLmsAccess::allows($gate));
        self::assertFalse(EffectifsLmsAccess::canCreateMembers($gate), 'gérer les grades ne suffit pas pour créer des comptes');
        self::assertFalse(EffectifsLmsAccess::canInviteMembers($gate));

        $gate->setPermissions(['personnel.status.manage']);
        self::assertTrue(EffectifsLmsAccess::canCreateMembers($gate));
        self::assertTrue(EffectifsLmsAccess::canInviteMembers($gate));
        self::assertFalse(EffectifsLmsAccess::canManageRoles($gate), 'le niveau d’accès reste réservé');

        $gate->setPermissions(['personnel.profile.update']);
        self::assertTrue(EffectifsLmsAccess::canInviteMembers($gate));

        $controller = self::read('app/Controllers/Admin/EffectifsInvitesController.php');
        self::assertStringContainsString('EffectifsLmsAccess::canInviteMembers', $controller);
        self::assertStringContainsString('Csrf::validate', $controller);
        $store = self::read('app/Controllers/Admin/Organization/UserAdminController.php');
        self::assertStringContainsString('EffectifsLmsAccess::canCreateMembers', $store);
        self::assertStringContainsString('EffectifsLmsAccess::canManageGrades', $store);
        self::assertStringContainsString('EffectifsLmsAccess::canManageRoles', $store);
        self::assertStringContainsString("'admin.organization'", self::read('app/Controllers/Admin/Organization/OrganizationIntegrationsController.php'));
    }

    public function testNoAutomaticEmail(): void
    {
        $store = self::read('app/Controllers/Admin/Organization/UserAdminController.php');
        $start = (int) strpos($store, 'public function store(');
        $body = substr($store, $start, (int) strpos($store, 'private function rosterOnlyMembers') - $start);
        self::assertStringNotContainsString('sendTenantUserSetupInvite', $body, 'plus d’e-mail d’office à la création');
        self::assertStringContainsString('requestedChannels($request)', $body);

        $_POST = [];
        self::assertSame([], \App\Controllers\Admin\EffectifsInvitesController::requestedChannels(new \App\Core\Request()));
        $_POST = ['send_email' => '1', 'send_discord' => '1'];
        self::assertSame(['discord', 'email'], \App\Controllers\Admin\EffectifsInvitesController::requestedChannels(new \App\Core\Request()));
        $_POST = [];

        foreach (['app/Services/Effectifs/MemberProfileInviteService.php', 'app/Controllers/Web/ProfileInviteController.php', 'app/Services/Effectifs/MemberProfileInviteBoard.php'] as $path) {
            self::assertStringNotContainsString('EmailService', self::read($path), $path);
        }
        // L'e-mail ne part que depuis l'action « email » (bouton) ou la case cochée.
        $sender = self::read('app/Services/Effectifs/MemberProfileInviteSender.php');
        self::assertSame(1, substr_count($sender, 'sendTenantUserSetupInvite('));
        self::assertStringContainsString("'profile_invite_email|i'", $sender);
    }

    public function testWhoActivatesThroughTheLink(): void
    {
        self::assertTrue(ProfileInviteController::needsActivation(['status' => 'pending_verification', 'email' => 'a@b.fr']));
        self::assertTrue(ProfileInviteController::needsActivation(['status' => 'active', 'email' => RosterOnlyMember::syntheticEmail(), 'last_login_at' => '2026-01-01']));
        self::assertTrue(ProfileInviteController::needsActivation(['status' => 'active', 'email' => 'a@b.fr', 'last_login_at' => null]));
        self::assertFalse(ProfileInviteController::needsActivation(['status' => 'active', 'email' => 'a@b.fr', 'last_login_at' => '2026-10-01 10:00:00']), 'compte déjà utilisé : connexion obligatoire');
        self::assertFalse(ProfileInviteController::needsActivation(['status' => 'inactive', 'email' => 'a@b.fr']));

        $public = self::read('app/Controllers/Web/ProfileInviteController.php');
        self::assertStringContainsString("'Referrer-Policy', 'no-referrer'", $public);
        self::assertStringContainsString("Response::redirect(url('invitation/profil'), 303)", $public, 'le jeton quitte l’adresse');
        self::assertStringContainsString('hash_equals($currentEmail, $typedEmail)', $public);
        self::assertStringContainsString("url('account/bienvenue')", $public, 'parcours d’accueil existant réutilisé');
        self::assertStringContainsString('tooManyAttempts', $public);

        $routes = self::read('routes/web.php');
        self::assertStringContainsString("'/invitation/profil/{token}', [\\App\\Controllers\\Web\\ProfileInviteController::class, 'open']", $routes);
        self::assertStringContainsString("'/back-office/ressources/effectifs/nouveau', [EffectifsWorkspaceController::class, 'newMember']", $routes);
        self::assertStringContainsString("'/back-office/ressources/effectifs/invitations', [EffectifsWorkspaceController::class, 'invitations']", $routes);
    }

    public function testNewMemberFormCoversTheRosterFields(): void
    {
        $view = self::read('views/admin/effectifs_workspace/new_member.php');
        foreach (['callsign', 'nickname', 'first_name', 'last_name', 'email', 'email_unknown', 'discord_handle', 'steam_id', 'unit_id', 'unit_role', 'job_role_ids[]', 'grade_id', 'access_role_id', 'enlistment_date', 'primary_lang', 'other_langs[]', 'notes', 'duration_hours', 'single_use', 'send_discord', 'send_email'] as $field) {
            self::assertStringContainsString('name="' . $field . '"', $view, $field);
        }
        self::assertStringContainsString('data-nm-check="callsign"', $view);
        self::assertStringContainsString('Aperçu', $view);

        self::assertSame('2026-01-15', NewMemberDossierService::date('2026-01-15'));
        self::assertNull(NewMemberDossierService::date('2026-02-30'));
        self::assertNull(NewMemberDossierService::date('15/01/2026'));
        self::assertNull(NewMemberDossierService::date('1970-01-01'));
    }

    public function testSchemaStepIsRegistered(): void
    {
        $registry = require dirname(__DIR__, 2) . '/database/steps/registry.php';
        $ids = array_column($registry, 'id');
        self::assertContains('member_profile_invites', $ids);
        $step = self::read('database/steps/3250_member_profile_invites.php');
        self::assertStringContainsString('2026_11_01_010_member_profile_invites.sql', $step);
        $sql = self::read('migrations/2026_11_01_010_member_profile_invites.sql');
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS `member_profile_invites`', $sql);
        self::assertStringContainsString('`token_hash` char(64)', $sql);
        self::assertStringNotContainsString('`token` ', $sql, 'aucune colonne de jeton en clair');
    }
}
