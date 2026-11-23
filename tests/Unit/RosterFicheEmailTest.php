<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\RosterFicheEmailRepository;
use App\Repositories\RosterFicheEventRepository;
use App\Services\Effectifs\NoAccountDirectory;
use App\Services\Effectifs\RosterFicheEmailService;
use App\Services\Effectifs\RosterOnlyMemberService;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Fiches sans compte : e-mail rattachable plus tard, statuts en trois étapes, tableur (export, modèle, import) et reprise
 * de la fiche par le parcours d'inscription.
 */
final class RosterFicheEmailTest extends TestCase
{
    private static function root(): string
    {
        return dirname(__DIR__, 2);
    }

    private function pdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('CREATE TABLE roster_fiche_emails (
            user_id INTEGER NOT NULL PRIMARY KEY, tenant_id INTEGER NOT NULL, email TEXT NOT NULL,
            updated_by INTEGER NULL, updated_at TEXT NOT NULL
        )');
        $pdo->exec('CREATE UNIQUE INDEX uq_rfe_mail ON roster_fiche_emails (tenant_id, email)');

        return $pdo;
    }

    /** @param list<string> $accounts adresses déjà portées par un compte */
    private function service(PDO $pdo, array &$journal, array $accounts = []): RosterFicheEmailService
    {
        $events = new class ($journal) extends RosterFicheEventRepository {
            /** @param list<string> $log */
            public function __construct(private array &$log)
            {
            }

            public function record(int $tenantId, int $userId, ?int $actorId, string $event, string $source = '', string $detail = ''): void
            {
                $this->log[] = $tenantId . '|' . $userId . '|' . $event . '|' . $detail;
            }
        };

        return new RosterFicheEmailService(new RosterFicheEmailRepository($pdo), static fn (string $e): bool => in_array($e, $accounts, true), $events);
    }

    public function testAttachChangeAndRemoveAreJournaled(): void
    {
        $pdo = $this->pdo();
        $log = [];
        $svc = $this->service($pdo, $log);

        $r = $svc->apply(7, 11, 1, '  Jean.Martin@Exemple.FR ');
        self::assertTrue($r['ok']);
        self::assertTrue($r['changed']);
        self::assertSame('jean.martin@exemple.fr', (new RosterFicheEmailRepository($pdo))->find(7, 11));

        self::assertFalse($svc->apply(7, 11, 1, 'jean.martin@exemple.fr')['changed'], 'même adresse : rien ne bouge');
        self::assertTrue($svc->apply(7, 11, 1, 'autre@exemple.fr')['changed']);
        self::assertTrue($svc->apply(7, 11, 1, '')['changed']);
        self::assertSame('', (new RosterFicheEmailRepository($pdo))->find(7, 11));
        self::assertSame(3, count($log));
        self::assertStringContainsString('e-mail rattaché', $log[0]);
        self::assertStringContainsString('→', $log[1]);
        self::assertStringContainsString('e-mail retiré', $log[2]);
    }

    public function testRefusals(): void
    {
        $pdo = $this->pdo();
        $log = [];
        $svc = $this->service($pdo, $log, ['compte@exemple.fr']);

        self::assertFalse($svc->apply(7, 11, 1, 'pas-un-mail')['ok']);
        self::assertFalse($svc->apply(7, 11, 1, 'fiche-ab@sans-compte.invalid')['ok'], 'adresse réservée');
        $acc = $svc->apply(7, 11, 1, 'Compte@exemple.fr');
        self::assertFalse($acc['ok']);
        self::assertStringContainsString('Rattacher à un compte existant', $acc['message']);

        self::assertTrue($svc->apply(7, 11, 1, 'x@exemple.fr')['ok']);
        $dup = $svc->apply(7, 12, 1, 'X@exemple.fr');
        self::assertFalse($dup['ok'], 'une adresse par fiche et par communauté');
        self::assertTrue($svc->apply(8, 12, 1, 'x@exemple.fr')['ok'], 'une autre communauté est indépendante');
        self::assertSame('', (new RosterFicheEmailRepository($pdo))->find(7, 12));
        self::assertSame('', (new RosterFicheEmailRepository($pdo))->find(9, 11), 'lecture bornée à la communauté');
    }

    public function testWithoutTableNothingBreaks(): void
    {
        $repo = new RosterFicheEmailRepository(new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]));
        self::assertFalse($repo->available());
        self::assertSame([], $repo->mapForTenant(7));
        self::assertSame('', $repo->find(7, 1));
        self::assertFalse($repo->set(7, 1, 'a@b.fr', null, '2026-01-01 00:00:00'));
    }

    public function testSheetCellsAreProtectedAgainstFormulas(): void
    {
        foreach (['=1+1', '+33', '-2', '@SUM(A1)', "\tx"] as $bad) {
            self::assertSame("'" . $bad, RosterOnlyMemberService::safeCell($bad));
        }
        self::assertSame('Martin', RosterOnlyMemberService::safeCell('Martin'));
        self::assertSame('', RosterOnlyMemberService::safeCell(''));
    }

    public function testTemplateHasBomSemicolonAndEmailColumn(): void
    {
        $csv = RosterOnlyMemberService::templateCsv();
        self::assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $lines = explode("\n", trim(substr($csv, 3)));
        self::assertSame(2, count($lines), 'en-têtes + une ligne d’exemple');
        self::assertSame('Prénom;Nom;Indicatif;E-mail;Grade;Affectation;"Steam ID"', trim($lines[0]));
        // Le modèle se relit tel quel avec l'import.
        $parsed = RosterOnlyMemberService::parseCsv($csv);
        self::assertNull($parsed['error']);
        self::assertContains('email', $parsed['columns']);
        self::assertSame('jean.martin@exemple.fr', $parsed['rows'][0]['email']);
    }

    public function testImportRecognisesEmailHeaderVariants(): void
    {
        foreach (['E-mail', 'Courriel', 'Adresse e-mail', 'Mail'] as $label) {
            $parsed = RosterOnlyMemberService::parseCsv("Nom;" . $label . "\nDupont;d@exemple.fr\n");
            self::assertSame('d@exemple.fr', $parsed['rows'][0]['email'] ?? null, $label);
        }
    }

    public function testThreeStepsAndFilters(): void
    {
        $rows = [
            ['id' => 1, 'name' => 'Zoé', 'callsign' => '', 'email' => 'z@exemple.fr', 'status' => 'fiche', 'source' => 'manual', 'last_activity' => '2026-11-01 10:00:00'],
            ['id' => 2, 'name' => 'Adam', 'callsign' => '', 'email' => '', 'status' => 'invitee', 'source' => 'import', 'last_activity' => '2026-11-05 10:00:00'],
            ['id' => 3, 'name' => 'Léa', 'callsign' => '', 'email' => 'lea@exemple.fr', 'status' => 'compte', 'source' => 'import', 'last_activity' => '2026-10-01 10:00:00'],
        ];
        self::assertSame([2, 1], array_column(NoAccountDirectory::filter($rows, []), 'id'), 'les comptes actifs ne sont plus « sans compte »');
        self::assertSame([3], array_column(NoAccountDirectory::filter($rows, ['status' => 'compte']), 'id'));
        self::assertSame([3], array_column(NoAccountDirectory::filter($rows, ['status' => 'compte', 'q' => 'LEA@']), 'id'), 'la recherche porte aussi sur l’e-mail');
        self::assertSame('Sans compte', NoAccountDirectory::STATUSES['fiche']);
        self::assertSame('Invitation envoyée', NoAccountDirectory::STATUSES['invitee']);
        self::assertSame('Compte actif', NoAccountDirectory::STATUSES['compte']);
        // Une fiche avec e-mail rattaché reste « sans compte » : seule l'adresse réservée compte pour classify().
        self::assertSame('fiche', NoAccountDirectory::classify(['email' => 'fiche-x@sans-compte.invalid', 'status' => 'active']));
        self::assertNull(NoAccountDirectory::classify(['email' => 'a@b.fr', 'status' => 'active', 'last_login_at' => '2026-01-01 00:00:00']));
    }

    public function testInscriptionTakesOverTheExistingFicheWithoutDuplicate(): void
    {
        $src = (string) file_get_contents(self::root() . '/app/Controllers/Web/ProfileInviteController.php');
        // Le lien met à jour la ligne users de la fiche (même identifiant) : ni création, ni fusion.
        self::assertStringContainsString("\$this->users()->update((int) \$user['id'], \$tenantId, \$updates)", $src);
        self::assertStringNotContainsString('->create(', $src);
        self::assertStringContainsString("'account_created'", $src);
        self::assertStringContainsString('emailExistsInTenant', $src, 'une adresse déjà portée par un compte est refusée, la fiche reste intacte');
        self::assertStringContainsString('hash_equals($attached, $typedEmail)', $src, 'l’adresse rattachée doit être confirmée par la personne');
        self::assertStringContainsString('(new RosterFicheEmailRepository())->remove(', $src);
    }

    public function testMigrationRegisteredOnceAndSafe(): void
    {
        $registry = (string) file_get_contents(self::root() . '/database/steps/registry.php');
        self::assertMatchesRegularExpression('/\'file\' => \'5040_roster_fiche_emails\.php\', \'rerun\' => true\]/', $registry);
        preg_match_all('/\'file\' => \'(\d+)_/', $registry, $m);
        self::assertSame(count($m[1]), count(array_unique($m[1])), 'aucun numéro d’étape en double');
        self::assertStringNotContainsString('require', strtolower('5040_roster_fiche_emails.php'));
        $step = (string) file_get_contents(self::root() . '/database/steps/5040_roster_fiche_emails.php');
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS roster_fiche_emails', $step);
        self::assertStringContainsString('tenant_id INT UNSIGNED NOT NULL', $step);
        self::assertStringContainsString('UNIQUE KEY uq_rfe_mail (tenant_id, email)', $step);
    }

    public function testExportAndRelanceKeepSamePageRightsAndTenant(): void
    {
        $routes = (string) file_get_contents(self::root() . '/routes/web.php');
        self::assertStringContainsString("sans-comptes/export.csv', [EffectifsWorkspaceController::class, 'sansComptesExport']", $routes);
        self::assertStringContainsString("sans-comptes/relancer'", $routes);
        $ctl = (string) file_get_contents(self::root() . '/app/Controllers/Admin/EffectifsWorkspaceController.php');
        self::assertMatchesRegularExpression('/function sansComptesExport\(.*?denyUnlessAccess\(\).*?Session::get\(\'tenant_id\'\)/s', $ctl);
        $inv = (string) file_get_contents(self::root() . '/app/Controllers/Admin/EffectifsInvitesController.php');
        self::assertStringContainsString("'sans-comptes' && \\App\\Support\\RosterOnlyMember::isRosterEmail", $inv, 'l’e-mail n’est exigé qu’à l’invitation depuis Sans compte');
    }
}
