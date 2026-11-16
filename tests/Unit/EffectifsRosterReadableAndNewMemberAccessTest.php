<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Database;
use App\Core\Gate;
use App\Core\Session;
use App\Services\Effectifs\NewMemberAccessStatus;
use App\Services\Personnel\PersonnelDutyPositionService;
use App\Support\AdministrativePositionCatalog;
use App\Support\RosterEffectiveRole;
use App\Support\RosterMemberColumns;
use PHPUnit\Framework\TestCase;
use Tests\Support\DossierFixture;

/**
 * Tableur des effectifs lisible (colonnes d'identité séparées, rôle hérité de l'emploi) et formulaire « Ajouter un
 * membre » avec habilitation et position de service.
 */
final class EffectifsRosterReadableAndNewMemberAccessTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $session = [];

    private bool $sessionStarted = false;

    protected function setUp(): void
    {
        $this->sessionStarted = (bool) (new \ReflectionProperty(Session::class, 'started'))->getValue();
        $this->session = $_SESSION ?? [];
        $_SESSION = ['tenant_id' => 1, 'user_id' => 4, '_csrf_token' => str_repeat('a', 64)];
    }

    protected function tearDown(): void
    {
        $_SESSION = $this->session;
        (new \ReflectionProperty(Session::class, 'started'))->setValue(null, $this->sessionStarted);
        (new \ReflectionProperty(Database::class, 'pdo'))->setValue(null, null);
        Gate::reset();
    }

    private static function root(): string
    {
        return dirname(__DIR__, 2);
    }

    private static function read(string $path): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents(self::root() . '/' . $path));
    }

    // ------------------------------------------------------------------ Rôle effectif

    public function testRoleEnteredOnTheMemberWins(): void
    {
        $r = RosterEffectiveRole::resolve('Troop CO (SL)', ['B SQN'], [['label' => 'Squad Leader / Troop CO', 'primary' => true]]);
        self::assertSame(['label' => 'Troop CO (SL)', 'source' => RosterEffectiveRole::SOURCE_MEMBER, 'raw' => 'Troop CO (SL)', 'hint' => ''], $r);
    }

    public function testEmptyOrDefaultRoleIsInheritedFromThePrimaryJob(): void
    {
        foreach (['', 'Membre', 'member'] as $raw) {
            $r = RosterEffectiveRole::resolve($raw, ['B SQN, TRP A - BRAVO ASSAULT'], [['label' => 'Rifleman', 'primary' => true]]);
            self::assertSame('Rifleman', $r['label'], $raw);
            self::assertSame(RosterEffectiveRole::SOURCE_JOB, $r['source'], $raw);
        }
        // L'emploi principal, pas le premier de la liste ; sans le détail « — … ».
        $r = RosterEffectiveRole::resolve('', [], [['label' => 'Combat Medic', 'primary' => false], ['label' => 'Pararescue Jumper — équipe Gold', 'primary' => true]]);
        self::assertSame('Pararescue Jumper', $r['label']);
    }

    public function testRoleThatOnlyRepeatsTheUnitNameGivesWayToTheJobRole(): void
    {
        $unit = '24th STS Gold Team PJ (Pararescue Jumper)';
        $r = RosterEffectiveRole::resolve($unit, [$unit, '24th STS Gold Team PJ'], [['label' => 'Pararescue Jumper', 'primary' => true]]);
        self::assertSame('Pararescue Jumper', $r['label']);
        self::assertSame(RosterEffectiveRole::SOURCE_JOB, $r['source']);
        self::assertSame($unit, $r['hint'], 'la valeur inscrite reste lisible au survol');

        $none = RosterEffectiveRole::resolve('', ['Groupe 1'], []);
        self::assertSame(RosterEffectiveRole::SOURCE_NONE, $none['source']);
        self::assertSame('', $none['label']);
    }

    // ------------------------------------------------------------------ Colonnes d'identité

    public function testIdentityIsSplitIntoSeparateColumns(): void
    {
        $c = RosterMemberColumns::split(['callsign' => 'VIPER', 'first_name' => 'Jean', 'last_name' => 'de la Tour', 'display_name' => 'Jean T.'], '03-202108', 'JML4W2U2L', ['V2', '']);
        self::assertSame('VIPER', $c['callsign']);
        self::assertSame(['V2'], $c['extra_callsigns']);
        self::assertSame('03-202108', $c['matricule']);
        self::assertSame('Matricule communauté 03-202108 · Matricule Athena JML4W2U2L', $c['matricule_title']);
        self::assertSame('Jean', $c['first']);
        self::assertSame('de la TOUR', $c['last']);
        self::assertFalse($c['masked']);

        $athenaOnly = RosterMemberColumns::split(['display_name' => 'Recrue'], '', 'ABC123');
        self::assertSame('ABC123', $athenaOnly['matricule']);
        self::assertSame('Recrue', $athenaOnly['fallback'], 'sans indicatif, le pseudo garde le lien vers la fiche');

        $masked = RosterMemberColumns::split(['anonymized' => true, 'callsign' => 'GHOST', 'first_name' => 'X', 'last_name' => 'Y', 'anonymized_label' => 'Opérateur protégé'], '1', '');
        self::assertTrue($masked['masked']);
        self::assertSame('', $masked['callsign']);
        self::assertSame('', $masked['first']);
        self::assertSame('Opérateur protégé', $masked['last']);
    }

    /** @param list<array<string, mixed>> $rows */
    private function renderRoster(array $rows, array $jobs, string $view = 'general'): string
    {
        $_SERVER['HTTP_HOST'] ??= 'athena-comspec.eu';
        (new \ReflectionProperty(Session::class, 'started'))->setValue(null, true);
        (new \ReflectionProperty(Database::class, 'pdo'))->setValue(null, DossierFixture::pdo());
        $vars = [
            'rosterRows' => $rows, 'rosterTotal' => count($rows), 'rosterPage' => 1, 'rosterTotalPages' => 1,
            'rosterFilters' => ['tri' => 'nom', 'vue' => $view], 'rosterView' => $view, 'rosterGroup' => 'unite',
            'rosterJobsByUserId' => $jobs, 'rosterJobPickerGroups' => [['label' => 'Emplois', 'items' => []]],
            'rosterIdentities' => [21 => ['first_name' => 'Andrew', 'last_name' => 'Abernathy', 'trigram' => '']],
            'canManageAssignments' => true, 'canManageStatus' => true, 'canCheckAvailability' => true,
            'rosterSessionRules' => ['min_minutes' => 60], 'csrfToken' => 'tok',
        ];
        $file = self::root() . '/views/admin/effectifs_workspace/roster.php';
        $render = static function (string $__file, array $__vars): string {
            extract($__vars);
            ob_start();
            try {
                require $__file;
            } finally {
                $out = (string) ob_get_clean();
            }

            return $out;
        };

        return $render($file, $vars);
    }

    public function testRosterShowsSeparateIdentityColumnsAndTheInheritedRole(): void
    {
        $row = ['id' => 21, 'callsign' => 'AAN', 'display_name' => 'Andrew A.', 'status' => 'active', 'grade_short' => 'PFC', 'grade_long' => 'Private First Class',
            'unit_id' => 5, 'unit_name' => 'Bravo Assault', 'assignment_path' => 'B SQN / Bravo Assault', 'assignment_role_name' => 'Membre',
            'tenant_member_number' => '02-202201', 'athena_identifier' => 'JS4HFERNX', 'presence_score' => 50, 'availability_score' => 100, 'completion_score' => 70];
        $html = $this->renderRoster([$row], [21 => [['id' => 9, 'label' => 'Rifleman', 'primary' => true, 'decoration' => null]]]);

        // En-têtes dans l'ordre : indicatif, matricule, prénom, nom, puis le service.
        self::assertMatchesRegularExpression('/>Indicatif<\/th>\s*<th[^>]*data-col="matricule">Matricule<\/th>\s*<th[^>]*data-col="prenom">Prénom<\/th>\s*<th[^>]*data-col="nom">Nom<\/th>/u', $html);
        self::assertMatchesRegularExpression('/data-col="indicatif".*?>AAN<\/a>/s', $html);
        self::assertMatchesRegularExpression('/data-col="matricule"[^>]*>\s*<span class="eff-rl-mono"[^>]*>02-202201<\/span>/', $html);
        self::assertMatchesRegularExpression('/data-col="prenom"[^>]*>\s*<span translate="no">Andrew<\/span>/', $html);
        self::assertMatchesRegularExpression('/data-col="nom"[^>]*>\s*<span class="eff-rl-last__v" translate="no">ABERNATHY<\/span>/', $html);
        // Rôle « Membre » posé d'office : le rôle lié à l'emploi s'affiche, marqué hérité, avec son info-bulle.
        self::assertMatchesRegularExpression('/<span class="eff-rl-role is-inherited" title="Rôle lié à l’emploi">Rifleman<span class="sr-only">/u', $html);
        // Le crayon garde la valeur réellement inscrite.
        self::assertStringContainsString('data-eff-edit="role"', $html);
        self::assertStringContainsString('data-eff-role="Membre"', $html);
        // Menu « Colonnes » et vérification de disponibilité (PR #1815) toujours là.
        self::assertStringContainsString('data-eff-cols-key="eff-roster-cols:4:general"', $html);
        self::assertStringContainsString('data-avc-ids="21"', $html);
        self::assertStringContainsString('eff-roster-columns.js', $html);
    }

    public function testOtherViewsKeepTheirColumnsWithTheSameIdentityColumns(): void
    {
        $row = ['id' => 21, 'callsign' => 'AAN', 'status' => 'active', 'unit_id' => 0, 'blood_type' => 'O+', 'weight_kg' => 80, 'height_cm' => 180];
        $html = $this->renderRoster([$row], [], 'medical');
        self::assertStringContainsString('>Groupe sanguin</th>', $html);
        self::assertStringContainsString('data-col="sang" data-label="Groupe sanguin"', $html);
        self::assertStringContainsString('data-col="matricule"', $html);
        self::assertStringNotContainsString('data-col="role"', $html);
    }

    // ------------------------------------------------------------------ Ajouter un membre : habilitation et position

    /** @param list<array<string, mixed>> $calls */
    private function service(bool $canSse, string $ceiling, int $trainingDays, array &$calls, string $positionResult = PersonnelDutyPositionService::RESULT_CHANGED): NewMemberAccessStatus
    {
        return new NewMemberAccessStatus(
            static fn (int $t, int $a): bool => $canSse,
            static fn (): string => $ceiling,
            static function (int $t, int $a, string $label, int $u, string $level, string $reason) use (&$calls): array {
                $calls[] = ['clearance', $t, $a, $u, $level, $reason];

                return ['ok' => true, 'message' => 'Habilitation de la fiche enregistrée.'];
            },
            static fn (int $t): int => $trainingDays,
            static function (int $t, int $u, string $slug, int $a) use (&$calls, $positionResult): string {
                $calls[] = ['position', $t, $u, $slug, $a];

                return $positionResult;
            },
            static function (int $t, int $a, int $u, string $value) use (&$calls): void {
                $calls[] = ['audit', $t, $a, $u, $value];
            },
            static fn (int $t): array => AdministrativePositionCatalog::builtIn(),
        );
    }

    public function testClearanceChoicesStopAtTheOperatorsOwnClearance(): void
    {
        $calls = [];
        $form = $this->service(true, 'confidentiel', 14, $calls)->form(1, 4, true);
        self::assertTrue($form['clearance']['can']);
        self::assertSame(['', 'encadrement', 'confidentiel'], array_keys($form['clearance']['options']), 'jamais au-dessus de sa propre habilitation');
        self::assertSame('', $form['clearance']['default'], 'par défaut, le niveau le plus bas');
        self::assertNotNull($this->service(true, 'confidentiel', 14, $calls)->validate($form, 'tres_restreint', ''), 'un niveau plus haut est refusé');
        self::assertNull($this->service(true, 'confidentiel', 14, $calls)->validate($form, 'confidentiel', ''));

        $low = $this->service(true, 'interne', 14, $calls)->form(1, 4, true);
        self::assertSame([''], array_keys($low['clearance']['options']));
    }

    public function testWithoutTheRightBothFieldsAreLockedAndIgnored(): void
    {
        $calls = [];
        $svc = $this->service(false, 'tres_restreint', 14, $calls);
        $form = $svc->form(1, 4, false);
        self::assertFalse($form['clearance']['can']);
        self::assertSame([''], array_keys($form['clearance']['options']));
        self::assertFalse($form['position']['can']);
        self::assertNull($svc->validate($form, 'tres_restreint', AdministrativePositionCatalog::REINFORCEMENT), 'champ verrouillé : ignoré, pas refusé');
        self::assertSame([], $svc->apply(1, 4, 'Cpt Delorme', 30, 'tres_restreint', AdministrativePositionCatalog::REINFORCEMENT, $form));
        self::assertSame([], $calls, 'rien n’est écrit sans le droit');

        $html = $this->renderNewMember($form);
        self::assertStringNotContainsString('name="clearance_level"', $html);
        self::assertStringNotContainsString('name="duty_position"', $html);
        self::assertStringContainsString('Seul qui gère les droits SSE peut accorder une habilitation', $html);
        self::assertStringContainsString('Seul qui gère le statut des comptes peut en choisir une autre.', $html);
    }

    public function testPositionFollowsTheCommunityRulesAndDefault(): void
    {
        $calls = [];
        $svc = $this->service(true, 'encadrement', 14, $calls);
        $form = $svc->form(1, 4, true);
        self::assertSame(AdministrativePositionCatalog::TRAINING, $form['position']['default']);
        $active = array_values(array_filter($form['position']['options'], static fn (array $o): bool => $o['slug'] === AdministrativePositionCatalog::ACTIVE))[0];
        self::assertTrue($active['disabled'], 'pas de service actif direct pendant la durée minimale de formation');
        self::assertNotNull($svc->validate($form, '', AdministrativePositionCatalog::ACTIVE));
        self::assertNotNull($svc->validate($form, '', 'status_inconnu'));

        $free = $this->service(true, 'encadrement', 0, $calls)->form(1, 4, true);
        self::assertNull($this->service(true, 'encadrement', 0, $calls)->validate($free, '', AdministrativePositionCatalog::ACTIVE));
    }

    public function testChosenValuesAreWrittenThroughTheExistingServicesAndJournaled(): void
    {
        $calls = [];
        $svc = $this->service(true, 'confidentiel', 14, $calls);
        $form = $svc->form(1, 4, true);
        $warnings = $svc->apply(1, 4, 'Cpt Delorme', 30, 'Confidentiel', AdministrativePositionCatalog::REINFORCEMENT, $form);
        self::assertSame([], $warnings);
        self::assertSame([
            ['clearance', 1, 4, 30, 'confidentiel', NewMemberAccessStatus::REASON],
            ['position', 1, 30, AdministrativePositionCatalog::REINFORCEMENT, 4],
            ['audit', 1, 4, 30, 'En service statutaire de renforcement'],
        ], $calls);

        // Position d'arrivée et aucune habilitation : rien à écrire de plus (la position d'arrivée est déjà posée).
        $calls = [];
        self::assertSame([], $svc->apply(1, 4, 'Cpt Delorme', 31, '', AdministrativePositionCatalog::TRAINING, $form));
        self::assertSame([], $calls);

        // Un refus du service remonte un avertissement lisible, la fiche reste créée.
        $calls = [];
        $refused = $this->service(true, 'confidentiel', 0, $calls, PersonnelDutyPositionService::RESULT_TRAINING_MINIMUM);
        $warn = $refused->apply(1, 4, 'Cpt Delorme', 32, '', AdministrativePositionCatalog::ACTIVE, $refused->form(1, 4, true));
        self::assertCount(1, $warn);
        self::assertStringContainsString('Position et statut', $warn[0]);
    }

    /** @param array<string, mixed> $form */
    private function renderNewMember(array $form): string
    {
        $_SERVER['HTTP_HOST'] ??= 'athena-comspec.eu';
        (new \ReflectionProperty(Session::class, 'started'))->setValue(null, true);
        $vars = ['nmAccessStatus' => $form, 'nmAccessRoles' => [], 'nmCanRoles' => false, 'nmCanAssign' => false, 'nmCanGrades' => false, 'nmCanProfiles' => false, 'nmInvitesReady' => false, 'csrfToken' => 'tok'];
        $file = self::root() . '/views/admin/effectifs_workspace/new_member.php';
        $render = static function (string $__file, array $__vars): string {
            extract($__vars);
            ob_start();
            try {
                require $__file;
            } finally {
                $out = (string) ob_get_clean();
            }

            return $out;
        };

        return $render($file, $vars);
    }

    public function testFormStepPreviewAndStoreWiring(): void
    {
        $calls = [];
        $form = $this->service(true, 'confidentiel', 14, $calls)->form(1, 4, true);
        $html = $this->renderNewMember($form);
        self::assertStringContainsString('data-nm-step="acces"', $html);
        self::assertStringContainsString('>Accès et statut</h2>', $html);
        self::assertMatchesRegularExpression('/<select name="clearance_level" data-nm-clearance>.*value="confidentiel"/s', $html);
        self::assertStringNotContainsString('value="tres_restreint"', $html);
        self::assertMatchesRegularExpression('/value="status_in_training"[^>]*selected/', $html);
        self::assertMatchesRegularExpression('/value="status_active_duty"[^>]*disabled/', $html);
        self::assertStringContainsString('data-pv="clearance"', $html);
        self::assertStringContainsString('data-pv="position"', $html);

        $js = self::read('public/assets/js/eff-new-member.js');
        self::assertStringContainsString("['Habilitation', clearanceText() || '—']", $js);
        self::assertStringContainsString("['Position', positionText() || '—']", $js);

        // Le store transmet les deux valeurs au service, après validation, dans les deux parcours (compte ou fiche sans compte).
        $store = self::read('app/Controllers/Admin/Organization/UserAdminController.php');
        self::assertStringContainsString('$accessStatus->validate($accessStatusForm, (string) $request->input(\'clearance_level\', \'\'), (string) $request->input(\'duty_position\', \'\'))', $store);
        self::assertSame(2, substr_count($store, "->apply(\$tenantId, \$actorUserId, \$this->actorLabel(), \$userId, (string) \$request->input('clearance_level', ''), (string) \$request->input('duty_position', ''), \$accessStatusForm)"));
        self::assertStringContainsString("'access_role_id', 'clearance_level', 'duty_position'", $store);
        self::assertStringContainsString("'nmAccessStatus' => (new \\App\\Services\\Effectifs\\NewMemberAccessStatus())->form(", self::read('app/Controllers/Admin/EffectifsWorkspaceController.php'));
    }
}
