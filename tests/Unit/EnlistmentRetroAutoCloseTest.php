<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\EnlistmentRetroAutoClose as R;
use App\Support\EnlistmentStatusCatalog;
use PHPUnit\Framework\TestCase;

/**
 * Bilan 30 jours : clôture automatique « néant » validée par Athena quand le bilan est attendu depuis 45 jours.
 * Logique pure testée directement ; câblage (tâche planifiée, service, écrans, migration) vérifié sur les sources.
 */
final class EnlistmentRetroAutoCloseTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../';

    private function read(string $path): string
    {
        return (string) file_get_contents(self::ROOT . $path);
    }

    private function now(): int
    {
        return (int) strtotime('2026-11-14 12:00:00');
    }

    /** Candidature acceptée il y a $days jours calendaires. */
    private function acceptedDaysAgo(int $days, string $status = 'reviewed'): array
    {
        return ['id' => 7, 'status' => $status, 'reviewed_at' => date('Y-m-d 08:30:00', strtotime('-' . $days . ' days', $this->now())), 'created_at' => '2026-01-01 10:00:00'];
    }

    public function testThresholdLivesInOnePlace(): void
    {
        self::assertSame(45, R::AUTO_CLOSE_LATE_DAYS);
        self::assertSame(30, EnlistmentStatusCatalog::RETRO_DELAY_DAYS);
        self::assertSame(75, R::acceptanceAgeDays());
        $src = $this->read('app/Services/Recruitment/EnlistmentRetroAutoCloseService.php')
            . $this->read('app/Services/Cron/Jobs/RecruitmentRetroAutoCloseCronJob.php')
            . $this->read('app/Repositories/EnlistmentRecruitmentEngagementRepository.php');
        self::assertSame(0, preg_match('/\b45\b|\b75\b/', preg_replace('#/\*.*?\*/|//[^\n]*#s', '', $src) ?? ''), 'le seuil n’est écrit nulle part ailleurs');
    }

    public function testExactThresholdAtDay44And45And46(): void
    {
        $now = $this->now();
        // 74 j depuis l'acceptation = attendu depuis 44 j : pas encore.
        self::assertSame(44, R::lateDays($this->acceptedDaysAgo(74), $now));
        self::assertFalse(R::isDue($this->acceptedDaysAgo(74), false, $now));
        // 75 j = attendu depuis 45 j : le néant est dû.
        self::assertSame(45, R::lateDays($this->acceptedDaysAgo(75), $now));
        self::assertTrue(R::isDue($this->acceptedDaysAgo(75), false, $now));
        // 76 j = 46 j : dû aussi (rattrapage).
        self::assertSame(46, R::lateDays($this->acceptedDaysAgo(76), $now));
        self::assertTrue(R::isDue($this->acceptedDaysAgo(76), false, $now));
    }

    public function testTheTwoDossiersOfTheScreenshotAreCaughtUp(): void
    {
        // Reçus le 29/08/2026 et le 30/08/2026 : « Attendu depuis 47 j » et « 46 j » le 14/11/2026.
        $now = $this->now();
        $a = ['status' => 'reviewed', 'reviewed_at' => '2026-08-29 09:00:00', 'created_at' => '2026-08-29 09:00:00'];
        $b = ['status' => 'reviewed', 'reviewed_at' => '2026-08-30 09:00:00', 'created_at' => '2026-08-30 09:00:00'];
        self::assertSame(47, R::lateDays($a, $now));
        self::assertSame(46, R::lateDays($b, $now));
        self::assertTrue(R::isDue($a, false, $now));
        self::assertTrue(R::isDue($b, false, $now));
        // Le texte affiché par la colonne est bien celui du catalogue (même calcul).
        self::assertSame('Attendu depuis 47 j', EnlistmentStatusCatalog::retro($a, null, true, $now)['meta']);
    }

    public function testNothingIsDueForOtherStatusesRecentDossiersOrExistingBilans(): void
    {
        $now = $this->now();
        foreach (['submitted', 'rejected', 'cancelled', 'blocked', 'on_hold', ''] as $status) {
            self::assertFalse(R::isDue($this->acceptedDaysAgo(120, $status), false, $now), 'statut « ' . $status . ' »');
            self::assertNull(R::lateDays($this->acceptedDaysAgo(120, $status), $now), 'statut « ' . $status . ' »');
        }
        // Dossier récent ou à peine échu.
        self::assertFalse(R::isDue($this->acceptedDaysAgo(0), false, $now));
        self::assertFalse(R::isDue($this->acceptedDaysAgo(30), false, $now));
        self::assertFalse(R::isDue($this->acceptedDaysAgo(60), false, $now));
        // Bilan déjà rédigé (ou néant déjà posé) : jamais deux fois, jamais écrasé.
        self::assertFalse(R::isDue($this->acceptedDaysAgo(200), true, $now));
        // Sans date d'acceptation, on retombe sur le dépôt (comme le catalogue).
        $legacy = ['status' => 'reviewed', 'reviewed_at' => null, 'created_at' => '2026-08-01 10:00:00'];
        self::assertTrue(R::isDue($legacy, false, $now));
        self::assertFalse(R::isDue(['status' => 'reviewed', 'reviewed_at' => null, 'created_at' => ''], false, $now));
    }

    public function testNeantIsDistinctFromAWrittenBilanAndLightensTheColumn(): void
    {
        $now = $this->now();
        $row = $this->acceptedDaysAgo(80);
        $due = EnlistmentStatusCatalog::retro($row, null, true, $now);
        self::assertSame('due', $due['key']);
        self::assertTrue($due['actionable']);

        $doneAt = '2026-11-14 10:00:00';
        $human = EnlistmentStatusCatalog::retro($row, $doneAt, true, $now);
        $auto = EnlistmentStatusCatalog::retro($row, $doneAt, true, $now, true);
        // Le dossier n'est plus « en retard » dans les deux cas…
        self::assertSame('done', $human['key']);
        self::assertSame('done', $auto['key']);
        self::assertFalse($auto['actionable']);
        // … mais le néant se présente autrement et ne passe pas pour un bilan humain.
        self::assertSame('Fait', $human['label']);
        self::assertArrayNotHasKey('auto', $human);
        self::assertSame('Clos par Athena', $auto['label']);
        self::assertTrue($auto['auto']);

        self::assertTrue(R::isAutoClosed(['auto_closed_at' => '2026-11-14 10:00:00']));
        self::assertFalse(R::isAutoClosed(['auto_closed_at' => null]));
        self::assertFalse(R::isAutoClosed(['rating' => 4]));
        self::assertFalse(R::isAutoClosed(null));
        self::assertTrue(R::countsAsWrittenBilan(['rating' => 4, 'auto_closed_at' => null]));
        self::assertFalse(R::countsAsWrittenBilan(['rating' => null, 'auto_closed_at' => '2026-11-14 10:00:00']));
        self::assertFalse(R::countsAsWrittenBilan(null));
        self::assertStringContainsString('Bilan non renseigné dans les délais — clos automatiquement (néant)', R::COMMENT);
    }

    public function testJobIsWiredAndScheduled(): void
    {
        $container = $this->read('app/Core/Container.php');
        self::assertStringContainsString('RecruitmentRetroAutoCloseCronJob::class', $container);
        // Enregistré dans le conteneur et inscrit au CronRunner.
        self::assertGreaterThanOrEqual(2, substr_count($container, 'RecruitmentRetroAutoCloseCronJob::class'));
        self::assertStringContainsString('EnlistmentRetroAutoCloseService::class', $container);
        self::assertStringContainsString("'recruitment_retro_auto_close' =>", $this->read('app/Services/Cron/CronSchedule.php'));
        $job = $this->read('app/Services/Cron/Jobs/RecruitmentRetroAutoCloseCronJob.php');
        self::assertStringContainsString("return 'recruitment_retro_auto_close';", $job);
        self::assertStringContainsString('implements CronJobInterface', $job);
    }

    public function testServiceIsIdempotentBoundedPerTenantAndNeverAbortsOnOneDossier(): void
    {
        $svc = $this->read('app/Services/Recruitment/EnlistmentRetroAutoCloseService.php');
        // Borné, par communauté, avec simulation sans écriture.
        self::assertStringContainsString('BATCH_PER_TENANT', $svc);
        self::assertStringContainsString('BATCH_TOTAL', $svc);
        self::assertStringContainsString('foreach ($this->tenants->listBasicAll()', $svc);
        self::assertStringContainsString('$dryRun', $svc);
        self::assertStringContainsString('EnlistmentRetroAutoClose::isDue(', $svc);
        // Un dossier en erreur est consigné puis sauté.
        self::assertGreaterThanOrEqual(2, substr_count($svc, 'catch (Throwable $e)'));
        self::assertStringContainsString("error_log('[cron recruitment_retro_auto_close]", $svc);
        // Colonne absente : rien n'est écrit.
        self::assertStringContainsString('autoCloseColumnExists()', $svc);
        // Journal du dossier sans acteur humain, marqué système Athena ; notification à l'instructeur.
        self::assertStringContainsString("'validated_by' => 'athena'", $svc);
        self::assertStringContainsString("'system'", $svc);
        self::assertStringContainsString("(int) (\$row['reviewed_by'] ?? 0)", $svc);

        $repo = $this->read('app/Repositories/EnlistmentRecruitmentEngagementRepository.php');
        $start = (int) strpos($repo, 'public function autoCloseStaffRetro');
        $auto = substr($repo, $start, (int) strpos($repo, 'public function mapStaffRetroAutoClosedIds') - $start);
        // Un seul néant par dossier, jamais écrasé ; sans auteur ni note.
        self::assertStringContainsString('ON DUPLICATE KEY UPDATE id = id', $auto);
        self::assertStringContainsString('NULL, NULL,', $auto);
        self::assertStringNotContainsString('UPDATE comment', $auto);
        // Candidats : acceptées seulement, sans bilan d'équipe, ancienneté du seuil, limite bornée.
        $cand = substr($repo, (int) strpos($repo, 'public function listStaffRetroAutoCloseCandidates'), 1800);
        self::assertStringContainsString("e.status = 'reviewed'", $cand);
        self::assertStringContainsString('r.id IS NULL', $cand);
        self::assertStringContainsString('EnlistmentRetroAutoClose::acceptanceAgeDays()', $cand);
        self::assertStringContainsString('min(200, $limit)', $cand);
        // Un bilan rédigé remplace le néant, la colonne reste facultative pour le code.
        self::assertStringContainsString('auto_closed_at = NULL', $repo);
        self::assertStringContainsString('autoCloseColumnExists()', $repo);
    }

    public function testNoWebRouteWritesTheNeantAndNoDisciplinaryConsequence(): void
    {
        $routes = '';
        foreach (glob(self::ROOT . 'routes/*.php') ?: [] as $file) {
            $routes .= (string) file_get_contents($file);
        }
        self::assertStringNotContainsString('auto-close', $routes);
        self::assertStringNotContainsString('autoCloseStaffRetro', $routes);
        foreach (glob(self::ROOT . 'app/Controllers/*/*.php') ?: [] as $file) {
            self::assertStringNotContainsString('autoCloseStaffRetro', (string) file_get_contents($file), basename($file));
        }
        $svc = $this->read('app/Services/Recruitment/EnlistmentRetroAutoCloseService.php');
        foreach (['Sanction', 'Moderation', 'analytics', 'Analytics', 'ENLISTMENT_STAFF_RETRO_SUBMIT'] as $needle) {
            self::assertStringNotContainsString($needle, $svc, $needle);
        }
    }

    public function testScreensShowTheAthenaBadgeAndAWrittenBilanReplacesTheNeant(): void
    {
        $show = $this->read('views/admin/recruitments/show.php');
        self::assertStringContainsString('$staffRetroIsAuto', $show);
        self::assertStringContainsString('EnlistmentRetroAutoClose::BADGE_LABEL', $show);
        self::assertStringContainsString('remplace le néant', $show);
        // Le formulaire reste proposé (avec son jeton CSRF) tant que le bilan est un néant.
        self::assertStringContainsString('<?= \App\Core\Csrf::field() ?>', $show);
        self::assertStringContainsString('$staffRetroFeedback && !$staffRetroIsAuto', $show);
        $index = $this->read('views/admin/recruitments/index.php');
        self::assertStringContainsString("empty(\$retro['auto'])", $index);
        self::assertStringContainsString('staff_retro_auto', $index);
        self::assertStringContainsString('mapStaffRetroAutoClosedIds', $this->read('app/Services/Recruitment/EnlistmentStaffRowEnricher.php'));
        $ctl = $this->read('app/Controllers/Admin/AdminRecruitmentsController.php');
        self::assertStringContainsString('EnlistmentRetroAutoClose::isAutoClosed($existingStaffRetro)', $ctl);
        self::assertStringContainsString('Ce bilan remplace le néant clos automatiquement par Athena.', $ctl);
        self::assertStringContainsString('EnlistmentRetroAutoClose::NOTIFICATION_TYPE', $this->read('app/Services/Notifications/ActivityHubPresentationService.php'));
    }

    public function testMigrationIsIdempotentReversibleAndNotAnArchiveScan(): void
    {
        $step = $this->read('database/steps/4550_enlistment_retro_auto_close.php');
        self::assertStringContainsString("'auto_closed_at'", $step);
        self::assertStringContainsString('addColumnIfMissing', $step);
        self::assertStringContainsString('tableExists', $step);
        self::assertStringNotContainsString('DROP ', $step);
        self::assertStringNotContainsString('DELETE', $step);
        $down = $this->read('migrations/rollback/enlistment_retro_auto_close_down.sql');
        self::assertStringContainsString('DROP COLUMN auto_closed_at', $down);
        // Les néants sont supprimés avant la colonne : sinon ils passeraient pour des bilans rédigés.
        self::assertLessThan(strpos($down, 'DROP COLUMN'), strpos($down, 'DELETE FROM enlistment_retro_feedbacks WHERE auto_closed_at IS NOT NULL'));
        $registry = $this->read('database/steps/registry.php');
        self::assertStringContainsString("'id' => 'enlistment_retro_auto_close'", $registry);
        self::assertStringContainsString('4550_enlistment_retro_auto_close.php', $registry);
        preg_match_all("/'id' => '([a-z0-9_]+)'/", $registry, $m);
        self::assertSame(count($m[1]), count(array_unique($m[1])));
        // Mode simulation documenté dans le script en ligne de commande.
        $cli = $this->read('recruitment-retro-auto-close.php');
        self::assertStringContainsString('--dry-run', $cli);
        self::assertStringContainsString("PHP_SAPI !== 'cli'", $cli);
    }
}
