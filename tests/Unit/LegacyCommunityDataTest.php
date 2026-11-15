<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\BackOffice\Menu\PendingTaskDetail;
use App\Services\Jnet\JnetDashboardService;
use App\Services\LegacyData\LegacyDataCounter;
use App\Services\LegacyData\LegacyDataNotice;
use App\Services\LegacyData\LegacyIdentityImport;
use App\Services\LegacyData\LegacyIdentityNotice;
use App\Support\EnlistmentRetroAutoClose as R;
use PDO;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Tests\Support\LegacyTenantFixture as F;

require_once dirname(__DIR__, 2) . '/bootstrap/migration_runner.php';

/**
 * Très anciennes communautés : une base d'avant les migrations d'octobre 2026 (membres sans profil, sans unité, sans grade,
 * sans prénom ni nom, anciens matricules, dossiers acceptés il y a deux ans sans bilan) traverse les services et les
 * écrans récents sans exception, sans suppression, sans écrasement ; les membres restent listés et l'avis apparaît.
 */
final class LegacyCommunityDataTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../';

    private function read(string $path): string
    {
        return (string) file_get_contents(self::ROOT . $path);
    }

    /** @return array<string, array<int, array<string, mixed>>> instantané de toutes les lignes, par table */
    private static function snapshot(PDO $pdo, array $tables): array
    {
        $out = [];
        foreach ($tables as $table) {
            $out[$table] = $pdo->query('SELECT * FROM ' . $table . ' ORDER BY 1')->fetchAll(PDO::FETCH_ASSOC);
        }

        return $out;
    }

    public function testCounterReadsAnOldBaseWithoutMissingTablesFailing(): void
    {
        $pdo = F::create(false);
        $c = (new LegacyDataCounter($pdo))->count(F::TENANT, F::now());
        // 5 membres actifs : le compte technique, le supprimé, le suspendu et l'autre communauté ne comptent pas.
        self::assertSame(5, $c['members']);
        // Viper, Hawk et Ghost (fiche vide) n'ont ni prénom ni nom ; « Marie » a un prénom seul : elle en a un.
        self::assertSame(3, $c['no_civil_name']);
        self::assertSame(2, $c['importable_names']);
        self::assertSame(1, $c['no_profile']);
        self::assertSame(4, $c['no_unit']);
        self::assertSame(2, $c['no_grade']);
        self::assertSame(4, $c['no_portrait']);
        self::assertSame(1, $c['stale_dossiers']);
        self::assertSame(1, $c['legacy_role_labels']);
        // L'autre communauté n'est pas mélangée à la première.
        $other = (new LegacyDataCounter($pdo))->count(F::OTHER_TENANT, F::now());
        self::assertSame(1, $other['members']);
        self::assertSame(1, $other['stale_dossiers']);
        // Une communauté inconnue ne lève rien.
        self::assertSame(0, (new LegacyDataCounter($pdo))->count(999, F::now())['members']);
        self::assertSame(0, (new LegacyDataCounter($pdo))->count(0, F::now())['members']);
    }

    public function testCounterNeverFailsWhenWholeTablesAreAbsent(): void
    {
        $bare = new \Tests\Support\MysqlDdlSqlitePdo();
        $bare->exec('CREATE TABLE users (id int NOT NULL PRIMARY KEY, tenant_id int, email varchar(190))');
        $bare->prepare('INSERT INTO users (id, tenant_id, email) VALUES (1, 7, ?)')->execute(['a@example.test']);
        $c = (new LegacyDataCounter($bare))->count(7, F::now());
        self::assertSame(1, $c['members']);
        self::assertSame(0, $c['no_civil_name']);
        self::assertSame(0, $c['stale_dossiers']);
        self::assertSame(0, $c['no_unit']);
    }

    public function testNoticeSaysWhatIsIncompleteWithTheNumbersInBothLanguages(): void
    {
        $counts = (new LegacyDataCounter(F::create(false)))->count(F::TENANT, F::now());
        $lines = LegacyDataNotice::lines($counts);
        $byKey = [];
        foreach ($lines as $l) {
            $byKey[$l['key']] = $l;
        }
        self::assertSame(['no_civil_name', 'stale_dossiers', 'profile_gaps'], array_keys($byKey));
        self::assertStringContainsString('3 membres n’ont pas d’état civil renseigné', $byKey['no_civil_name']['text']);
        self::assertStringContainsString('leur nom s’affiche sous leur indicatif', $byKey['no_civil_name']['text']);
        self::assertStringContainsString('1 dossier de recrutement ancien est resté sans bilan', $byKey['stale_dossiers']['text']);
        self::assertStringContainsString('4 sans unité', $byKey['profile_gaps']['text']);
        self::assertStringContainsString('4 sans portrait', $byKey['profile_gaps']['text']);
        self::assertStringNotContainsString('sans grade', $byKey['profile_gaps']['text'], 'deux membres seulement : sous le seuil');

        $fr = LegacyDataNotice::compose($counts, 'fr');
        self::assertTrue($fr instanceof PendingTaskDetail);
        self::assertSame('Vérifier les données anciennes', $fr->button);
        self::assertStringContainsString('qualite-donnees#dq-donnees-anciennes', $fr->path);
        self::assertTrue($fr->localized);
        self::assertStringContainsString('Rien n’est supprimé ni écrasé', $fr->consequence);
        self::assertSame('fr', $fr->lang);
        $en = LegacyDataNotice::compose($counts, 'en');
        self::assertTrue($en instanceof PendingTaskDetail);
        self::assertStringContainsString('3 members have no civil identity filled in', $en->explain);
        self::assertSame('Check old data', $en->button);
        self::assertSame('en', $en->lang);

        // Rien à signaler : pas de tâche.
        self::assertNull(LegacyDataNotice::compose(array_fill_keys(LegacyDataCounter::KEYS, 0)));
        // Un cas isolé de portrait manquant n'est pas un avis (seuil).
        self::assertSame([], LegacyDataNotice::lines(['no_portrait' => 2, 'no_unit' => 1]));
        self::assertSame('1 membre n’a pas d’état civil renseigné (ni prénom ni nom) : son nom s’affiche sous son indicatif.', LegacyIdentityNotice::message(1));
        self::assertSame('', LegacyIdentityNotice::message(0));
    }

    public function testNoticeIsWiredAsATaskThatNeverBlocksAndIsCachedPerTenant(): void
    {
        $cfg = require self::ROOT . 'config/back_office_menu.php';
        $def = $cfg['badges']['donnees_anciennes'];
        self::assertSame(['admin.organization', 'admin.access'], $def['any_permissions']);
        self::assertSame('back-office/organisation/qualite-donnees', $def['path']);
        self::assertSame('notif', $def['tone']);
        $notice = $this->read('app/Services/LegacyData/LegacyDataNotice.php');
        self::assertSame(600, \App\Services\LegacyData\LegacyDataNotice::CACHE_SECONDS);
        self::assertStringContainsString('ShortCache::remember(', $notice);
        self::assertStringContainsString("'legacy_data_notice:' . \$tenantId", $notice);
        self::assertStringContainsString('catch (Throwable $e)', $notice);
        $badge = $this->read('app/Services/BackOffice/Menu/Badges/LegacyDataBadge.php');
        self::assertStringContainsString("'important' => false", $badge);
        self::assertStringContainsString('implements BadgeProvider, PendingTaskDetailProvider', $badge);
    }

    public function testOldDirectoryRowsAreAllNamedAndNobodyDisappears(): void
    {
        $rows = F::directoryRows();
        $service = (new ReflectionClass(JnetDashboardService::class))->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod(JnetDashboardService::class, 'normalizePersonCard');
        $cards = [];
        foreach ($rows as $row) {
            $card = $method->invoke($service, $row);
            self::assertIsArray($card);
            self::assertSame((int) $row['id'], $card['id']);
            self::assertNotSame('', trim((string) $card['name']), 'membre n° ' . $row['id'] . ' sans nom affiché');
            self::assertStringContainsString('jnet/personnel/' . $row['id'], (string) $card['href']);
            $cards[] = $card;
        }
        // Une ligne par membre : ni disparue, ni dédoublée.
        self::assertCount(count($rows), $cards);
    }

    public function testPersonnelDirectoryIsReadByPagesSoNobodyBeyondThe120thVanishes(): void
    {
        $src = $this->read('app/Services/Jnet/JnetDashboardService.php');
        self::assertStringNotContainsString("'active', null, 120, 0", $src);
        self::assertStringContainsString('PERSONNEL_PAGE', $src);
        self::assertStringContainsString('PERSONNEL_DIRECTORY_MAX', $src);
        // Le dépôt plafonne une page à 200 lignes : la page lue ne dépasse jamais ce plafond.
        self::assertLessThanOrEqual(200, (new \ReflectionClassConstant(JnetDashboardService::class, 'PERSONNEL_PAGE'))->getValue());
    }

    public function testRecruitmentWindowProtectsAnOldCommunityFromACatchUp(): void
    {
        $now = F::now();
        $row = static fn (int $daysAgo): array => ['id' => 1, 'status' => 'reviewed', 'reviewed_at' => F::daysAgo($daysAgo), 'created_at' => F::daysAgo($daysAgo + 2)];
        // Accepté il y a 2 ans : ancien, jamais clos ni notifié, seulement signalé.
        self::assertFalse(R::isDue($row(730), false, $now));
        self::assertTrue(R::isStale($row(730), false, $now));
        // Il y a 100 jours : dans la fenêtre, clos comme avant.
        self::assertTrue(R::isDue($row(100), false, $now));
        self::assertFalse(R::isStale($row(100), false, $now));
        // Il y a 20 jours : pas encore dû, pas ancien.
        self::assertFalse(R::isDue($row(20), false, $now));
        self::assertFalse(R::isStale($row(20), false, $now));
        // Les bornes de la fenêtre : 210 jours d'acceptation = 180 jours de retard, dernier jour clos ; 211 = ancien.
        self::assertTrue(R::isDue($row(R::acceptanceMaxAgeDays()), false, $now));
        self::assertFalse(R::isStale($row(R::acceptanceMaxAgeDays()), false, $now));
        self::assertFalse(R::isDue($row(R::acceptanceMaxAgeDays() + 1), false, $now));
        self::assertTrue(R::isStale($row(R::acceptanceMaxAgeDays() + 1), false, $now));
        // Un dossier qui a déjà un bilan n'est ni dû ni ancien.
        self::assertFalse(R::isStale($row(730), true, $now));
        // Autres statuts et dates absentes : rien, sans exception.
        self::assertFalse(R::isStale(['status' => 'rejected', 'reviewed_at' => F::daysAgo(900)], false, $now));
        self::assertFalse(R::isStale(['status' => 'reviewed', 'reviewed_at' => null, 'created_at' => null], false, $now));
        self::assertFalse(R::isDue(['status' => 'reviewed'], false, $now));
        self::assertSame(210, R::acceptanceMaxAgeDays());
    }

    public function testCommunityCanSwitchTheAutomaticClosureOffAndItIsOnByDefault(): void
    {
        self::assertTrue(R::enabledFor([]));
        self::assertTrue(R::enabledFor(['retro_auto_close' => []]));
        self::assertTrue(R::enabledFor(['retro_auto_close' => ['enabled' => true]]));
        self::assertTrue(R::enabledFor(['retro_auto_close' => ['enabled' => '1']]));
        self::assertFalse(R::enabledFor(['retro_auto_close' => ['enabled' => false]]));
        self::assertFalse(R::enabledFor(['retro_auto_close' => ['enabled' => '0']]));
    }

    public function testServiceNeverWritesStaleDossiersAndGroupsItsNotifications(): void
    {
        $svc = $this->read('app/Services/Recruitment/EnlistmentRetroAutoCloseService.php');
        self::assertStringContainsString('countStaleWithoutStaffRetro($tenantId)', $svc);
        self::assertStringContainsString('EnlistmentRetroAutoClose::enabledFor(', $svc);
        self::assertStringContainsString('MAX_NOTIFICATIONS_PER_RUN', $svc);
        // Une seule notification par instructeur et par passage : la création est hors de la boucle des dossiers.
        self::assertSame(1, substr_count($svc, '$this->notifications->create('));
        self::assertStringNotContainsString('notifications->create', substr($svc, (int) strpos($svc, 'private function closeOne')));
        $repo = $this->read('app/Repositories/EnlistmentRecruitmentEngagementRepository.php');
        $cand = substr($repo, (int) strpos($repo, 'public function listStaffRetroAutoCloseCandidates'), 2600);
        self::assertStringContainsString('acceptanceMaxAgeDays()', $cand);
        self::assertStringContainsString('>= DATE_SUB(CURDATE(), INTERVAL {$maxAge} DAY)', $cand);
        // La simulation existe et n'écrit rien.
        $cli = $this->read('recruitment-retro-auto-close.php');
        self::assertStringContainsString("'--dry-run'", $cli);
        self::assertStringContainsString("\$r['stale']", $cli);
    }

    public function testOldNamesAreOnlyProposedWhenFirstAndLastNameAreBothEmpty(): void
    {
        $pdo = F::create();
        $plan = (new LegacyIdentityImport($pdo))->simulate(F::TENANT);
        self::assertTrue($plan['available']);
        $byUser = [];
        foreach ($plan['candidates'] as $cand) {
            $byUser[$cand['user_id']] = $cand;
        }
        // Hawk (pas de fiche d'état civil) et Ghost (fiche vide, nom d'un seul mot) ; jamais Marie (prénom déjà saisi) ni Alice.
        self::assertSame([3, 4], array_keys($byUser));
        self::assertSame('Jean', $byUser[3]['first_name']);
        self::assertSame('Dupont', $byUser[3]['last_name']);
        self::assertNull($byUser[4]['first_name']);
        self::assertSame('Ghost', $byUser[4]['last_name']);
        self::assertSame(2, $plan['total']);
        // La simulation n'écrit rien.
        self::assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM legacy_identity_import_log')->fetchColumn());
        self::assertSame(3, (int) $pdo->query('SELECT COUNT(*) FROM user_legal_identities')->fetchColumn());
    }

    public function testImportNeverOverwritesAndEveryNameIsJournalledThenReversible(): void
    {
        $pdo = F::create();
        $import = new LegacyIdentityImport($pdo);
        $before = self::snapshot($pdo, ['users', 'personnel_profiles', 'personnel_extras', 'enlistments', 'roles']);
        $alice = $pdo->query('SELECT * FROM user_legal_identities WHERE user_id = 1')->fetch(PDO::FETCH_ASSOC);
        $marie = $pdo->query('SELECT * FROM user_legal_identities WHERE user_id = 5')->fetch(PDO::FETCH_ASSOC);

        $r = $import->apply(F::TENANT, 1, 200, F::now());
        self::assertTrue($r['ok']);
        self::assertSame(2, $r['imported']);
        self::assertSame(0, $r['skipped']);
        self::assertNotSame('', $r['batch_id']);

        // Rien d'autre n'a bougé (ni comptes, ni dossiers, ni noms de personnage, ni matricules, ni libellés de rôle).
        self::assertSame($before, self::snapshot($pdo, ['users', 'personnel_profiles', 'personnel_extras', 'enlistments', 'roles']));
        // Les valeurs existantes sont intactes.
        self::assertSame($alice, $pdo->query('SELECT * FROM user_legal_identities WHERE user_id = 1')->fetch(PDO::FETCH_ASSOC));
        self::assertSame($marie, $pdo->query('SELECT * FROM user_legal_identities WHERE user_id = 5')->fetch(PDO::FETCH_ASSOC));
        // Les deux membres repris ont leur prénom et nom, et le journal garde la valeur d'origine.
        $hawk = $pdo->query('SELECT first_name, last_name FROM user_legal_identities WHERE user_id = 3')->fetch(PDO::FETCH_ASSOC);
        self::assertSame(['first_name' => 'Jean', 'last_name' => 'Dupont'], $hawk);
        $ghost = $pdo->query('SELECT first_name, last_name FROM user_legal_identities WHERE user_id = 4')->fetch(PDO::FETCH_ASSOC);
        self::assertSame(['first_name' => null, 'last_name' => 'Ghost'], $ghost);
        $log = $pdo->query('SELECT user_id, source_character_name, identity_row_created, actor_user_id FROM legacy_identity_import_log ORDER BY user_id')->fetchAll(PDO::FETCH_ASSOC);
        self::assertCount(2, $log);
        self::assertSame('Jean Dupont', $log[0]['source_character_name']);
        self::assertSame(1, (int) $log[0]['identity_row_created']);
        self::assertSame(0, (int) $log[1]['identity_row_created']);
        self::assertSame(1, (int) $log[0]['actor_user_id']);
        self::assertSame([3, 4], $import->markedUserIds(F::TENANT));
        self::assertSame([], $import->markedUserIds(F::OTHER_TENANT));

        // Idempotent : un second passage ne reprend plus personne.
        self::assertSame(0, $import->apply(F::TENANT, 1, 200, F::now())['imported']);
        self::assertSame(2, (int) $pdo->query('SELECT COUNT(*) FROM legacy_identity_import_log')->fetchColumn());
        // L'avis le constate : il ne reste que Viper sans prénom ni nom.
        self::assertSame(1, (new LegacyDataCounter($pdo))->count(F::TENANT, F::now())['no_civil_name']);

        // Retour arrière du lot : la fiche créée disparaît, la fiche vide redevient vide, le reste est intact.
        $back = $import->revert(F::TENANT, $r['batch_id'], F::now());
        self::assertTrue($back['ok']);
        self::assertSame(2, $back['reverted']);
        self::assertSame(0, $back['kept']);
        self::assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM user_legal_identities WHERE user_id = 3')->fetchColumn());
        self::assertSame(['first_name' => null, 'last_name' => null], $pdo->query('SELECT first_name, last_name FROM user_legal_identities WHERE user_id = 4')->fetch(PDO::FETCH_ASSOC));
        self::assertSame($alice, $pdo->query('SELECT * FROM user_legal_identities WHERE user_id = 1')->fetch(PDO::FETCH_ASSOC));
        self::assertSame($marie, $pdo->query('SELECT * FROM user_legal_identities WHERE user_id = 5')->fetch(PDO::FETCH_ASSOC));
        self::assertSame([], $import->markedUserIds(F::TENANT));
        self::assertSame($before, self::snapshot($pdo, ['users', 'personnel_profiles', 'personnel_extras', 'enlistments', 'roles']));
    }

    public function testRevertKeepsAnythingTheMemberEditedAfterTheImport(): void
    {
        $pdo = F::create();
        $import = new LegacyIdentityImport($pdo);
        $r = $import->apply(F::TENANT, 1, 200, F::now());
        $pdo->exec("UPDATE user_legal_identities SET first_name = 'Jeanne' WHERE user_id = 3");
        $back = $import->revert(F::TENANT, $r['batch_id'], F::now());
        self::assertSame(1, $back['reverted']);
        self::assertSame(1, $back['kept']);
        self::assertSame('Jeanne', $pdo->query('SELECT first_name FROM user_legal_identities WHERE user_id = 3')->fetchColumn());
        // Un lot d'une autre communauté ou inconnu ne retire rien.
        self::assertSame(0, $import->revert(F::OTHER_TENANT, $r['batch_id'], F::now())['reverted']);
        self::assertSame(0, $import->revert(F::TENANT, 'inconnu', F::now())['reverted']);
    }

    public function testImportDoesNothingWithoutItsJournalOrOnAnOtherCommunity(): void
    {
        $noLog = F::create(false);
        $r = (new LegacyIdentityImport($noLog))->apply(F::TENANT, 1, 200, F::now());
        self::assertFalse($r['ok']);
        self::assertStringContainsString('Migration à passer', $r['reason']);
        self::assertSame(3, (int) $noLog->query('SELECT COUNT(*) FROM user_legal_identities')->fetchColumn());

        $pdo = F::create();
        self::assertSame([], (new LegacyIdentityImport($pdo))->simulate(F::OTHER_TENANT)['candidates']);
        self::assertSame(0, (new LegacyIdentityImport($pdo))->apply(F::OTHER_TENANT, 1, 200, F::now())['imported']);
    }

    public function testNameSplitIsPredictableAndNeverEmpty(): void
    {
        self::assertSame(['first' => 'Jean', 'last' => 'Dupont'], LegacyIdentityImport::split('  Jean   Dupont '));
        self::assertSame(['first' => 'Jean', 'last' => 'de la Fontaine'], LegacyIdentityImport::split('Jean de la Fontaine'));
        self::assertSame(['first' => null, 'last' => 'Ghost'], LegacyIdentityImport::split('Ghost'));
        self::assertNull(LegacyIdentityImport::split('   '));
        self::assertNull(LegacyIdentityImport::split(''));
        self::assertSame(100, mb_strlen((string) LegacyIdentityImport::split(str_repeat('é', 150))['last']));
    }

    public function testMigrationStepIsIdempotentAndHasItsRollback(): void
    {
        $root = dirname(__DIR__, 2);
        $migrationFlush = static function (): void {
        };
        $migrationEnsurePdo = static function (): void {
        };
        $pdo = new \Tests\Support\MysqlDdlSqlitePdo();
        require $root . '/database/steps/_context.php';
        $step = \App\Support\Migrations\MigrationRegistry::find($root, 'legacy_identity_import_log');
        self::assertNotNull($step);
        self::assertSame('4585_legacy_identity_import_log.php', $step['file']);
        $run = static function () use ($step, $pdo, $root): string {
            ob_start();
            try {
                require $step['path'];
            } finally {
                $out = (string) ob_get_clean();
            }

            return $out;
        };
        self::assertStringContainsString('Table créée', $run());
        $changes = $pdo->changes;
        self::assertStringContainsString('déjà présent', $run());
        self::assertSame($changes, $pdo->changes, 'une relance ne change rien');
        $sql = $this->read('migrations/2026_11_15_100_legacy_identity_import_log.sql');
        self::assertStringNotContainsString('FOREIGN KEY', $sql);
        self::assertStringNotContainsString('DELETE', $sql);
        self::assertStringContainsString('DROP TABLE IF EXISTS legacy_identity_import_log', $this->read('migrations/rollback/legacy_identity_import_log_down.sql'));
    }

    public function testAuditToolReportsEachCommunityAndOnlyReads(): void
    {
        require_once self::ROOT . 'tools/lib/audit-report-kit.php';
        require_once self::ROOT . 'tools/audit-report-anciennes-communautes.php';
        $pdo = F::create();
        $db = new class ($pdo) extends \AuditReadOnlyDb {
            public function hasTable(string $table): bool
            {
                return $this->pdo->query("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = " . $this->pdo->quote($table))->fetchColumn() !== false;
            }

            public function hasColumn(string $table, string $column): bool
            {
                foreach ($this->pdo->query('PRAGMA table_info("' . $table . '")')->fetchAll(PDO::FETCH_ASSOC) as $col) {
                    if ($col['name'] === $column) {
                        return true;
                    }
                }

                return false;
            }
        };
        $kit = new \AuditReportKit('audit-report-anciennes-communautes', 'test', 'test');
        $kit->capture = true;
        $before = self::snapshot($pdo, ['users', 'user_legal_identities', 'personnel_profiles', 'enlistments', 'enlistment_retro_feedbacks', 'roles']);
        $code = audit_report_anciennes_communautes($kit, $db, $pdo);
        self::assertSame(3, $code, 'des points à vérifier : code de sortie 3');
        self::assertSame($before, self::snapshot($pdo, ['users', 'user_legal_identities', 'personnel_profiles', 'enlistments', 'enlistment_retro_feedbacks', 'roles']));
        $sections = [];
        foreach ($kit->captured as $s) {
            $sections[substr($s['title'], 0, 2)] = $s;
        }
        self::assertCount(4, $sections);
        // Tables absentes de la base d'essai : signalées, sans exception.
        self::assertGreaterThan(0, $sections['2.']['total']);
        $byTenant = [];
        foreach ($sections['3.']['rows'] as $r) {
            $byTenant[$r['tenant_id']] = $r;
        }
        self::assertSame([7, 8], array_keys($byTenant));
        self::assertSame(5, $byTenant[7]['members']);
        self::assertSame('3 (60 %)', $byTenant[7]['no_civil_name']);
        self::assertSame(1, $byTenant[7]['operateur']);
        self::assertSame(0, $byTenant[8]['operateur']);
        $bilans = [];
        foreach ($sections['4.']['rows'] as $r) {
            $bilans[$r['tenant_id']] = $r;
        }
        self::assertSame(3, $bilans[7]['total']);
        self::assertSame(1, $bilans[7]['due']);
        self::assertSame(1, $bilans[7]['stale']);
        self::assertSame(1, $bilans[8]['stale']);

        // Garde-fou : aucune écriture possible, aucune option d'écriture, uniquement des SELECT.
        $tool = $this->read('tools/audit-report-anciennes-communautes.php');
        self::assertSame(0, preg_match('/->(exec|prepare)\(\s*[\'"]\s*(INSERT|UPDATE|DELETE|ALTER|DROP)/i', $tool));
        self::assertStringContainsString('audit-report-kit.php', $tool);
        self::assertStringContainsString('LECTURE SEULE', $tool);
        $docs = $this->read('docs/anciennes-communautes.md');
        self::assertStringContainsString('php tools/audit-report-anciennes-communautes.php --tenant=ID', $docs);
    }

    public function testLegacyScreenSectionRendersWithAnOldCommunityAndHasNoFreeFunctionCalls(): void
    {
        $view = $this->read('views/admin/organization/partials/legacy_data.php');
        // Leçon d'une erreur 500 : toute classe appelée par une vue est écrite en entier (aucun « use » possible dans une vue incluse).
        $code = (string) preg_replace('#/\*.*?\*/#s', '', $view);
        self::assertSame(0, preg_match('/(?<![\\\\\w])(?:EnlistmentRetroAutoClose|LegacyDataNotice|LegacyDataScreen)::/', $code));
        self::assertStringContainsString('\App\Support\EnlistmentRetroAutoClose::', $view);
        self::assertStringContainsString('id="dq-donnees-anciennes"', $view);
        self::assertStringContainsString('name="_csrf_token"', $view);

        $counts = (new LegacyDataCounter(F::create(false)))->count(F::TENANT, F::now());
        $legacy = [
            'counts' => $counts,
            'lines' => LegacyDataNotice::lines($counts),
            'simulation' => (new LegacyIdentityImport(F::create()))->simulate(F::TENANT),
            'batches' => [['batch_id' => 'abcdef0123456789', 'at' => F::daysAgo(1), 'imported' => 2, 'active' => 2]],
            'importAvailable' => true,
            'importReason' => '',
            'retroEnabled' => true,
            'retroWindowDays' => 180,
            'retroThresholdDays' => 45,
        ];
        $h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $icon = static fn (string $name): string => '<svg data-icon="' . $name . '"></svg>';
        $csrfToken = 'tok';
        ob_start();
        try {
            require self::ROOT . 'views/admin/organization/partials/legacy_data.php';
        } finally {
            $html = (string) ob_get_clean();
        }
        self::assertStringContainsString('3 membres n’ont pas d’état civil renseigné', $html);
        self::assertStringContainsString('1 dossier de recrutement ancien est resté sans bilan', $html);
        self::assertStringContainsString('Jean Dupont', $html);
        self::assertStringContainsString('Reprendre ces 2 nom(s)', $html);
        self::assertStringContainsString('Annuler cette reprise', $html);
        self::assertStringContainsString('Désactiver', $html);
        self::assertStringNotContainsString('Warning', $html);
    }
}
