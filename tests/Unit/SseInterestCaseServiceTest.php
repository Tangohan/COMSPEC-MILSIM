<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Sse\SseInterestCaseException;
use App\Support\SseInterestCaseCatalog as Catalog;
use PHPUnit\Framework\TestCase;
use Tests\Support\SseInterestFixture;

/**
 * Dossiers d'intérêt : création, modification, états, verrou optimiste, historique, corbeille, suppression définitive,
 * droits, habilitation, accès nominatifs, communautés, liens, filtres. Base SQLite en mémoire, vrai service, vrais dépôts.
 */
final class SseInterestCaseServiceTest extends TestCase
{
    private SseInterestFixture $f;

    protected function setUp(): void
    {
        $this->f = new SseInterestFixture();
    }

    protected function tearDown(): void
    {
        $this->f->close();
    }

    private function expectKind(string $kind, callable $fn): SseInterestCaseException
    {
        try {
            $fn();
        } catch (SseInterestCaseException $e) {
            self::assertSame($kind, $e->kind, $e->getMessage());

            return $e;
        }
        self::fail('SseInterestCaseException (' . $kind . ') attendue');
    }

    // ------------------------------------------------------------------ Création

    public function testCreateWritesCaseHistoryAuditAndWatcher(): void
    {
        $res = $this->f->service->create($this->f->actor(11), [
            'temporary_designation' => '  Convoi   nord  ', 'opening_reason' => 'Trois véhicules observés à l’aube.', 'interest_level' => 'critique',
            'case_type' => 'vehicule', 'classification' => 'encadrement', 'tags' => 'Convoi, nord, convoi, véhicule', 'assignee_user_id' => '13',
            'due_at' => '2026-12-01', 'digital_signature' => '1',
        ]);
        self::assertMatchesRegularExpression('/^DI-\d{4}-000001$/', $res['reference']);
        $row = $this->f->row($res['id']);
        self::assertSame('Convoi nord', $row['temporary_designation']);
        self::assertSame('signalement_recu', $row['status']);
        self::assertSame(1, (int) $row['version']);
        self::assertSame('vehicule', $row['case_type']);
        self::assertSame('Convoi,nord,véhicule', $row['tags'], 'doublons retirés sans tenir compte de la casse');
        self::assertSame('2026-12-01 23:59:00', $row['due_at']);
        self::assertSame('13', (string) $row['assignee_user_id']);
        self::assertSame('1', (string) $row['tenant_id']);
        $hist = $this->f->pdo->query('SELECT action, actor_user_id FROM sse_interest_case_history WHERE interest_case_id = ' . $res['id'])->fetchAll(\PDO::FETCH_ASSOC);
        self::assertSame([['action' => 'create', 'actor_user_id' => 11]], $hist);
        self::assertSame('interest_case.create', $this->f->audit[0]['action']);
        self::assertSame('1', (string) $this->f->pdo->query('SELECT COUNT(*) FROM sse_interest_case_watchers WHERE user_id = 11')->fetchColumn());
    }

    public function testReferencesNeverCollide(): void
    {
        $a = $this->f->open(11);
        $b = $this->f->open(11);
        self::assertNotSame($this->f->row($a)['reference_code'], $this->f->row($b)['reference_code']);
    }

    public function testCreateValidationReportsEveryField(): void
    {
        $e = $this->expectKind(SseInterestCaseException::VALIDATION, fn () => $this->f->service->create($this->f->actor(11), [
            'temporary_designation' => 'ab', 'opening_reason' => 'court', 'interest_level' => 'urgent', 'case_type' => 'inconnu',
            'classification' => 'tres_restreint', 'assignee_user_id' => '20', 'due_at' => '2020-01-01', 'confidence_level' => 'x',
        ]));
        foreach (['temporary_designation', 'opening_reason', 'interest_level', 'case_type', 'classification', 'assignee_user_id', 'due_at', 'confidence_level', 'digital_signature'] as $field) {
            self::assertArrayHasKey($field, $e->fields, $field);
        }
        self::assertStringContainsString('habilitation', $e->fields['classification']);
    }

    public function testCreateLengthLimits(): void
    {
        $e = $this->expectKind(SseInterestCaseException::VALIDATION, fn () => $this->f->service->create($this->f->actor(11), [
            'temporary_designation' => str_repeat('a', 121), 'opening_reason' => str_repeat('b', 4001), 'description' => str_repeat('c', 8001),
            'suspected_alias' => str_repeat('d', 161), 'digital_signature' => '1',
        ]));
        foreach (['temporary_designation', 'opening_reason', 'description', 'suspected_alias'] as $field) {
            self::assertArrayHasKey($field, $e->fields, $field);
        }
        self::assertSame(0, (int) $this->f->pdo->query('SELECT COUNT(*) FROM sse_interest_cases')->fetchColumn());
    }

    public function testDraftOnlyNeedsATitle(): void
    {
        $res = $this->f->service->create($this->f->actor(11), ['temporary_designation' => 'À compléter', 'draft' => '1']);
        self::assertSame('brouillon', $this->f->row($res['id'])['status']);
        $this->expectKind(SseInterestCaseException::VALIDATION, fn () => $this->f->service->create($this->f->actor(11), ['draft' => '1', 'temporary_designation' => '']));
    }

    public function testReaderCannotCreate(): void
    {
        $this->expectKind(SseInterestCaseException::FORBIDDEN, fn () => $this->f->service->create($this->f->actor(12), ['temporary_designation' => 'Test', 'opening_reason' => 'Un motif suffisant.', 'digital_signature' => '1']));
    }

    public function testCreateLinksPendingElementsAndSkipsInvalidOnes(): void
    {
        $res = $this->f->service->create($this->f->actor(11), [
            'temporary_designation' => 'Avec liens', 'opening_reason' => 'Motif assez long.', 'digital_signature' => '1',
            'pending_links' => ['person:5', 'site:3', 'identity:8', 'vehicle:AB-123-CD', 'person:999'],
        ]);
        self::assertSame(2, $res['links_failed'], 'identité masquée et personne inconnue refusées');
        self::assertSame('3', (string) $this->f->pdo->query('SELECT COUNT(*) FROM sse_interest_case_links WHERE interest_case_id = ' . $res['id'])->fetchColumn());
    }

    // ------------------------------------------------------------------ Modification, verrou optimiste, historique

    public function testUpdateRecordsBeforeAndAfterPerField(): void
    {
        $id = $this->f->open(11);
        $res = $this->f->service->update($this->f->actor(11), $id, ['temporary_designation' => 'Nouveau titre', 'interest_level' => 'courant', 'tags' => 'a, b', 'assignee_user_id' => '13'], 1);
        self::assertSame(2, $res['version']);
        self::assertSame(['temporary_designation', 'interest_level', 'assignee_user_id', 'tags'], $res['changed']);
        self::assertSame(2, (int) $this->f->row($id)['version']);
        $rows = $this->f->pdo->query("SELECT field_key, old_value, new_value, version_after FROM sse_interest_case_history WHERE action = 'update' ORDER BY id")->fetchAll(\PDO::FETCH_ASSOC);
        self::assertSame('Homme au manteau gris', $rows[0]['old_value']);
        self::assertSame('Nouveau titre', $rows[0]['new_value']);
        self::assertSame('prioritaire', $rows[1]['old_value']);
        self::assertSame('courant', $rows[1]['new_value']);
        self::assertSame('2', (string) $rows[0]['version_after']);
        $detail = $this->f->service->detail($this->f->actor(11), $id);
        $labels = array_column(array_filter($detail['history'], static fn (array $h): bool => $h['action'] === 'update'), 'new', 'field_label');
        self::assertSame('P4 · Courant', $labels['Priorité']);
        self::assertSame('Second analyste', $labels['Responsable']);
    }

    public function testUnchangedUpdateDoesNotBumpTheVersion(): void
    {
        $id = $this->f->open(11);
        $res = $this->f->service->update($this->f->actor(11), $id, ['temporary_designation' => 'Homme au manteau gris'], 1);
        self::assertSame([], $res['changed']);
        self::assertSame(1, (int) $this->f->row($id)['version']);
    }

    public function testConcurrentUpdateIsRefusedNotOverwritten(): void
    {
        $id = $this->f->open(11);
        $this->f->service->update($this->f->actor(13), $id, ['temporary_designation' => 'Version de 13'], 1);
        $e = $this->expectKind(SseInterestCaseException::CONFLICT, fn () => $this->f->service->update($this->f->actor(11), $id, ['temporary_designation' => 'Version de 11'], 1));
        self::assertStringContainsString('Opérateur 13', $e->getMessage());
        self::assertStringContainsString('Rien n’a été écrasé', $e->getMessage());
        self::assertSame('Version de 13', $this->f->row($id)['temporary_designation']);
        self::assertSame(2, (int) $this->f->row($id)['version']);
    }

    public function testUpdateWithoutVersionIsRefused(): void
    {
        $id = $this->f->open(11);
        $e = $this->expectKind(SseInterestCaseException::VALIDATION, fn () => $this->f->service->update($this->f->actor(11), $id, ['temporary_designation' => 'Autre'], null));
        self::assertArrayHasKey('version', $e->fields);
    }

    public function testUpdateValidationLeavesTheCaseUntouched(): void
    {
        $id = $this->f->open(11);
        $this->expectKind(SseInterestCaseException::VALIDATION, fn () => $this->f->service->update($this->f->actor(11), $id, ['temporary_designation' => 'x', 'interest_level' => 'nope'], 1));
        self::assertSame('Homme au manteau gris', $this->f->row($id)['temporary_designation']);
        self::assertSame(1, (int) $this->f->row($id)['version']);
    }

    public function testClassificationIsCappedAndLoweringNeedsAReason(): void
    {
        $id = $this->f->open(10, ['classification' => 'tres_restreint']);
        // L'analyste (plafond « confidentiel ») ne voit même plus ce dossier.
        $this->expectKind(SseInterestCaseException::NOT_FOUND, fn () => $this->f->service->update($this->f->actor(11), $id, ['classification' => 'interne'], 1));
        $id2 = $this->f->open(11, ['classification' => 'encadrement']);
        $e = $this->expectKind(SseInterestCaseException::VALIDATION, fn () => $this->f->service->update($this->f->actor(11), $id2, ['classification' => 'tres_restreint'], 1));
        self::assertArrayHasKey('classification', $e->fields);
        $e = $this->expectKind(SseInterestCaseException::VALIDATION, fn () => $this->f->service->update($this->f->actor(11), $id2, ['classification' => 'interne'], 1));
        self::assertArrayHasKey('reason', $e->fields, 'abaisser demande un motif');
        $this->f->service->update($this->f->actor(11), $id2, ['classification' => 'confidentiel'], 1);
        $this->f->service->update($this->f->actor(11), $id2, ['classification' => 'interne', 'reason' => 'Information publique'], 2);
        $hist = $this->f->pdo->query("SELECT old_value, new_value, reason FROM sse_interest_case_history WHERE action = 'classification' ORDER BY id")->fetchAll(\PDO::FETCH_ASSOC);
        self::assertSame(['encadrement', 'confidentiel', null], array_values($hist[0]));
        self::assertSame(['confidentiel', 'interne', 'Information publique'], array_values($hist[1]));
    }

    public function testReaderCannotUpdate(): void
    {
        $id = $this->f->open(11);
        $this->expectKind(SseInterestCaseException::FORBIDDEN, fn () => $this->f->service->update($this->f->actor(12), $id, ['temporary_designation' => 'Piraté'], 1));
        self::assertSame('Homme au manteau gris', $this->f->row($id)['temporary_designation']);
    }

    // ------------------------------------------------------------------ États

    public function testTransitionTableIsCoherent(): void
    {
        foreach (array_keys(\App\Repositories\SseInterestCaseRepository::STATUSES) as $status) {
            self::assertArrayHasKey($status, Catalog::TRANSITIONS, 'état sans ligne : ' . $status);
        }
        foreach (Catalog::TRANSITIONS as $from => $targets) {
            foreach ($targets as $to) {
                self::assertTrue(Catalog::knownStatus($to), $from . ' -> ' . $to);
                self::assertTrue($from !== $to, 'pas de boucle : ' . $from);
            }
        }
        self::assertSame([], Catalog::TRANSITIONS['archive'], 'on sort des archives par la restauration, pas par un état');
        // Chaque état actif peut aboutir à une clôture (aucune impasse).
        foreach (['brouillon', 'signalement_recu', 'a_qualifier', 'en_collecte', 'en_analyse', 'rapprochements_detectes', 'en_attente', 'en_validation', 'correspondance_probable'] as $from) {
            self::assertTrue(in_array('sans_suite', Catalog::TRANSITIONS[$from], true) || in_array('identite_consolidee', Catalog::TRANSITIONS[$from], true) || in_array('en_analyse', Catalog::TRANSITIONS[$from], true), $from);
        }
        foreach (Catalog::TABS as $tab => $meta) {
            foreach ($meta['statuses'] as $s) {
                self::assertSame($tab, Catalog::tabOf($s));
            }
        }
        self::assertSame(count(\App\Repositories\SseInterestCaseRepository::STATUSES), array_sum(array_map(static fn (array $m): int => count($m['statuses']), Catalog::TABS)), 'chaque état est dans un onglet et un seul');
    }

    public function testValidTransitionsAndInvalidOnes(): void
    {
        $id = $this->f->open(11);
        $this->f->service->transition($this->f->actor(11), $id, 'a_qualifier', '', 1);
        $this->f->service->transition($this->f->actor(11), $id, 'en_collecte', '', 2);
        self::assertSame('en_collecte', $this->f->row($id)['status']);
        $e = $this->expectKind(SseInterestCaseException::RULE, fn () => $this->f->service->transition($this->f->actor(11), $id, 'en_validation', '', 3));
        self::assertStringContainsString('Passage impossible', $e->getMessage());
        self::assertStringContainsString('« En analyse »', $e->getMessage(), 'le message liste les états possibles');
        $this->expectKind(SseInterestCaseException::RULE, fn () => $this->f->service->transition($this->f->actor(11), $id, 'en_collecte', '', 3));
        $this->expectKind(SseInterestCaseException::VALIDATION, fn () => $this->f->service->transition($this->f->actor(11), $id, 'inconnu', '', 3));
        self::assertSame('en_collecte', $this->f->row($id)['status']);
    }

    public function testClosingNeedsAReasonAndValidationNeedsAManager(): void
    {
        $id = $this->f->open(10);
        $svc = $this->f->service;
        $m = $this->f->actor(10);
        $svc->transition($m, $id, 'a_qualifier', '', 1);
        $svc->transition($m, $id, 'en_analyse', '', 2);
        $svc->transition($m, $id, 'en_validation', '', 3);
        $e = $this->expectKind(SseInterestCaseException::FORBIDDEN, fn () => $svc->transition($this->f->actor(11), $id, 'identite_consolidee', 'Confirmé', 4));
        self::assertStringContainsString('gestionnaires', $e->getMessage());
        $e = $this->expectKind(SseInterestCaseException::VALIDATION, fn () => $svc->transition($m, $id, 'identite_infirme', '', 4));
        self::assertArrayHasKey('reason', $e->fields);
        $svc->transition($m, $id, 'identite_infirme', 'Photo non concordante', 4);
        self::assertSame('identite_infirme', $this->f->row($id)['status']);
        $h = $this->f->pdo->query("SELECT old_value, new_value, reason FROM sse_interest_case_history WHERE action = 'status' ORDER BY id DESC LIMIT 1")->fetch(\PDO::FETCH_ASSOC);
        self::assertSame(['en_validation', 'identite_infirme', 'Photo non concordante'], array_values($h));
        // réouverture d'un dossier clos : motif obligatoire
        $this->expectKind(SseInterestCaseException::VALIDATION, fn () => $svc->transition($m, $id, 'en_analyse', '', 5));
        $svc->transition($m, $id, 'en_analyse', 'Nouvel élément', 5);
    }

    public function testValidationDecisionStampsTheValidator(): void
    {
        $id = $this->f->open(10);
        $m = $this->f->actor(10);
        foreach (['a_qualifier', 'en_analyse', 'en_validation'] as $i => $to) {
            $this->f->service->transition($m, $id, $to, '', $i + 1);
        }
        $this->f->service->transition($m, $id, 'identite_consolidee', 'Concordance biométrique', 4);
        $row = $this->f->row($id);
        self::assertSame('10', (string) $row['validated_by']);
        self::assertNotNull($row['validated_at']);
    }

    public function testArchiveNeedsDeleteRightAndCanBeUndone(): void
    {
        $id = $this->f->open(13);
        $this->expectKind(SseInterestCaseException::FORBIDDEN, fn () => $this->f->service->transition($this->f->actor(11), $id, 'archive', 'Rangé', 1));
        $this->f->service->transition($this->f->actor(13), $id, 'a_qualifier', '', 1);
        $this->f->service->transition($this->f->actor(13), $id, 'archive', 'Rangé', 2);
        $row = $this->f->row($id);
        self::assertSame('archive', $row['status']);
        self::assertNotNull($row['archived_at']);
        self::assertSame('a_qualifier', $row['archive_from_status']);
        $this->expectKind(SseInterestCaseException::RULE, fn () => $this->f->service->transition($this->f->actor(13), $id, 'a_qualifier', '', 3));
        $res = $this->f->service->unarchive($this->f->actor(13), $id, 'Besoin de reprendre', 3);
        self::assertSame('a_qualifier', $res['to']);
        self::assertNull($this->f->row($id)['archived_at']);
    }

    public function testStatusChangeWithStaleVersionIsRefused(): void
    {
        $id = $this->f->open(11);
        $this->f->service->update($this->f->actor(13), $id, ['tags' => 'urgent'], 1);
        $this->expectKind(SseInterestCaseException::CONFLICT, fn () => $this->f->service->transition($this->f->actor(11), $id, 'a_qualifier', '', 1));
        self::assertSame('signalement_recu', $this->f->row($id)['status']);
    }

    // ------------------------------------------------------------------ Suppression

    public function testTrashNeedsDeleteRightAndAReason(): void
    {
        $id = $this->f->open(11);
        $this->expectKind(SseInterestCaseException::FORBIDDEN, fn () => $this->f->service->trash($this->f->actor(11), $id, 'Doublon', 1));
        $e = $this->expectKind(SseInterestCaseException::VALIDATION, fn () => $this->f->service->trash($this->f->actor(13), $id, '', 1));
        self::assertArrayHasKey('reason', $e->fields);
        $this->f->service->trash($this->f->actor(13), $id, 'Doublon', 1);
        $row = $this->f->row($id);
        self::assertSame($this->f->now, $row['deleted_at']);
        self::assertSame('Doublon', $row['delete_reason']);
        self::assertSame('interest_case.trash', end($this->f->audit)['action']);
    }

    public function testTrashedCaseLeavesTheQueueAndShowsInTheTrashOnlyForThoseWhoMayRestore(): void
    {
        $id = $this->f->open(13);
        $this->f->service->trash($this->f->actor(13), $id, 'Doublon', 1);
        $queue = $this->f->service->page($this->f->actor(13), ['onglet' => 'tous']);
        self::assertSame(0, $queue['total']);
        $trash = $this->f->service->page($this->f->actor(13), ['onglet' => 'corbeille']);
        self::assertSame(1, $trash['total']);
        self::assertSame(1, $trash['counts']['corbeille']);
        $this->expectKind(SseInterestCaseException::NOT_FOUND, fn () => $this->f->service->find($this->f->actor(11), $id));
        $analystTrash = $this->f->service->page($this->f->actor(11), ['onglet' => 'corbeille']);
        self::assertSame(0, $analystTrash['total'], 'un analyste sans droit de supprimer ne voit pas la corbeille');
        $this->expectKind(SseInterestCaseException::RULE, fn () => $this->f->service->update($this->f->actor(13), $id, ['tags' => 'x'], 2));
    }

    public function testRestoreWithinThirtyDaysOnly(): void
    {
        $id = $this->f->open(13);
        $this->f->service->trash($this->f->actor(13), $id, 'Doublon', 1);
        $this->f->service->restore($this->f->actor(13), $id);
        self::assertNull($this->f->row($id)['deleted_at']);
        self::assertSame(1, $this->f->service->page($this->f->actor(13), ['onglet' => 'tous'])['total']);
        $this->f->service->trash($this->f->actor(13), $id, 'Encore', 3);
        $this->f->now = '2026-12-20 12:00:00';
        $e = $this->expectKind(SseInterestCaseException::RULE, fn () => $this->f->service->restore($this->f->actor(13), $id));
        self::assertStringContainsString('30 jours', $e->getMessage());
    }

    public function testSweepPurgesExpiredTrashAndArchivesBlockedOnes(): void
    {
        $a = $this->f->open(10);
        $b = $this->f->open(10);
        $this->f->service->trash($this->f->actor(10), $a, 'Doublon', 1);
        $this->f->service->trash($this->f->actor(10), $b, 'Doublon', 1);
        $this->f->now = '2026-11-20 12:00:00';
        self::assertSame(['purged' => 0, 'archived' => 0], $this->f->service->sweepTrash(1));
        $this->f->now = '2026-12-20 12:00:00';
        $this->f->blockers = [];
        $this->f->pdo->exec('UPDATE sse_interest_cases SET status = \'identite_consolidee\' WHERE id = ' . $b);
        $res = $this->f->service->sweepTrash(1);
        self::assertSame(['purged' => 1, 'archived' => 1], $res);
        self::assertSame([], $this->f->row($a));
        self::assertSame('archive', $this->f->row($b)['status']);
        self::assertNull($this->f->row($b)['deleted_at']);
    }

    public function testPurgeIsForManagersOnTrashedCasesWithConfirmation(): void
    {
        $id = $this->f->open(10);
        $m = $this->f->actor(10);
        $this->expectKind(SseInterestCaseException::RULE, fn () => $this->f->service->purge($m, $id, 'Test', 'x'));
        $this->f->service->trash($m, $id, 'Doublon', 1);
        $this->expectKind(SseInterestCaseException::FORBIDDEN, fn () => $this->f->service->purge($this->f->actor(13), $id, 'Test', $this->f->row($id)['reference_code']));
        $e = $this->expectKind(SseInterestCaseException::VALIDATION, fn () => $this->f->service->purge($m, $id, '', 'DI-faux'));
        self::assertArrayHasKey('reason', $e->fields);
        self::assertArrayHasKey('confirm', $e->fields);
        self::assertSame(1, (int) $this->f->pdo->query('SELECT COUNT(*) FROM sse_interest_cases')->fetchColumn());
    }

    public function testPurgeDeletesEverythingAndLeavesAnAuditTrace(): void
    {
        $id = $this->f->open(10);
        $m = $this->f->actor(10);
        $this->f->service->addNote($m, $id, 'Une note');
        $this->f->service->addLink($m, $id, 'person', '5', '', '');
        $this->f->service->trash($m, $id, 'Doublon', 1);
        $ref = $this->f->row($id)['reference_code'];
        $this->f->service->purge($m, $id, 'Demande du chef de bureau', strtolower($ref));
        self::assertSame([], $this->f->row($id));
        foreach (['sse_interest_case_history', 'sse_interest_case_links', 'sse_interest_case_watchers', 'sse_interest_case_updates'] as $table) {
            self::assertSame('0', (string) $this->f->pdo->query('SELECT COUNT(*) FROM ' . $table . ' WHERE interest_case_id = ' . $id)->fetchColumn(), $table);
        }
        $last = end($this->f->audit);
        self::assertSame('interest_case.purge', $last['action']);
        self::assertSame('Demande du chef de bureau', $last['reason']);
        self::assertSame($ref, $last['before']['reference']);
    }

    public function testPurgeIsImpossibleWithValidatedOrCitedCases(): void
    {
        $m = $this->f->actor(10);
        $id = $this->f->open(10);
        $this->f->service->trash($m, $id, 'Doublon', 1);
        $ref = $this->f->row($id)['reference_code'];
        $this->f->blockers = ['il est cité dans 2 compte(s) rendu(s) JNET'];
        $e = $this->expectKind(SseInterestCaseException::RULE, fn () => $this->f->service->purge($m, $id, 'Test', $ref));
        self::assertStringContainsString('JNET', $e->getMessage());
        self::assertStringContainsString('archivez', $e->getMessage());
        $this->f->blockers = [];
        $this->f->pdo->exec("UPDATE sse_interest_cases SET status = 'identite_consolidee' WHERE id = " . $id);
        $e = $this->expectKind(SseInterestCaseException::RULE, fn () => $this->f->service->purge($m, $id, 'Test', $ref));
        self::assertStringContainsString('validation', $e->getMessage());
        self::assertNotSame([], $this->f->row($id));
    }

    // ------------------------------------------------------------------ Droits, habilitation, communautés

    public function testOtherCommunityCannotSeeOrTouchACase(): void
    {
        $id = $this->f->open(11);
        $stranger = $this->f->actor(20);
        $this->expectKind(SseInterestCaseException::NOT_FOUND, fn () => $this->f->service->find($stranger, $id));
        $this->expectKind(SseInterestCaseException::NOT_FOUND, fn () => $this->f->service->update($stranger, $id, ['tags' => 'x'], 1));
        $this->expectKind(SseInterestCaseException::NOT_FOUND, fn () => $this->f->service->transition($stranger, $id, 'a_qualifier', '', 1));
        $this->expectKind(SseInterestCaseException::NOT_FOUND, fn () => $this->f->service->trash($stranger, $id, 'Piratage', 1));
        $this->expectKind(SseInterestCaseException::NOT_FOUND, fn () => $this->f->service->preview($stranger, $id));
        $this->expectKind(SseInterestCaseException::NOT_FOUND, fn () => $this->f->service->addLink($stranger, $id, 'person', '5', '', ''));
        self::assertSame(0, $this->f->service->page($stranger, ['onglet' => 'tous'])['total']);
        self::assertSame(1, $this->f->service->page($this->f->actor(11), ['onglet' => 'tous'])['total']);
    }

    public function testClearanceFiltersListCountsPreviewAndPdfSource(): void
    {
        $low = $this->f->open(10, ['classification' => 'interne', 'temporary_designation' => 'Public']);
        $high = $this->f->open(10, ['classification' => 'tres_restreint', 'temporary_designation' => 'Très secret']);
        $reader = $this->f->actor(12);
        $page = $this->f->service->page($reader, ['onglet' => 'tous']);
        self::assertSame(1, $page['total']);
        self::assertSame('Public', $page['rows'][0]['temporary_designation']);
        self::assertSame(1, $page['counts']['ouverts'], 'les compteurs ne révèlent pas les dossiers hors habilitation');
        $this->expectKind(SseInterestCaseException::NOT_FOUND, fn () => $this->f->service->find($reader, $high));
        $this->expectKind(SseInterestCaseException::NOT_FOUND, fn () => $this->f->service->preview($reader, $high));
        $this->expectKind(SseInterestCaseException::NOT_FOUND, fn () => $this->f->service->detail($reader, $high));
        self::assertSame('Public', $this->f->service->detail($reader, $low)['case']['temporary_designation']);
        self::assertSame(2, $this->f->service->page($this->f->actor(10), ['onglet' => 'tous'])['total']);
        $search = $this->f->service->page($reader, ['onglet' => 'tous', 'q' => 'secret']);
        self::assertSame(0, $search['total'], 'la recherche non plus');
    }

    public function testNamedAccessRulesRestrictTheCase(): void
    {
        $id = $this->f->open(11);
        $repo = $this->f->service->repository();
        $repo->replaceAcl(1, $id, [13], [], 10);
        $this->f->service->find($this->f->actor(11), $id); // le créateur reste admis
        $this->f->service->find($this->f->actor(13), $id);
        $this->expectKind(SseInterestCaseException::NOT_FOUND, fn () => $this->f->service->find($this->f->actor(12), $id));
        self::assertSame(0, $this->f->service->page($this->f->actor(12), ['onglet' => 'tous'])['total']);
        $this->f->service->find($this->f->actor(10), $id); // un gestionnaire contourne
        $repo->replaceAcl(1, $id, [], [13], 10);
        $this->expectKind(SseInterestCaseException::NOT_FOUND, fn () => $this->f->service->find($this->f->actor(13), $id));
        $this->f->service->find($this->f->actor(12), $id);
    }

    // ------------------------------------------------------------------ Liens

    public function testLinksAreCheckedPerCommunityAndMasking(): void
    {
        $id = $this->f->open(11);
        $a = $this->f->actor(11);
        $this->f->service->addLink($a, $id, 'person', '5', 'ignoré : le serveur donne le libellé', 'Vu au dépôt');
        $links = $this->f->service->presentLinks($a, (new \App\Repositories\SseInterestCaseLinkRepository())->listForCase(1, $id));
        self::assertSame('DUPONT Jean', $links[0]['label']);
        $e = $this->expectKind(SseInterestCaseException::VALIDATION, fn () => $this->f->service->addLink($a, $id, 'person', '5', '', ''));
        self::assertStringContainsString('déjà lié', $e->fields['target']);
        $this->expectKind(SseInterestCaseException::VALIDATION, fn () => $this->f->service->addLink($a, $id, 'identity', '8', '', ''));
        $this->expectKind(SseInterestCaseException::VALIDATION, fn () => $this->f->service->addLink($a, $id, 'person', '999', '', ''));
        $this->expectKind(SseInterestCaseException::VALIDATION, fn () => $this->f->service->addLink($a, $id, 'person', 'abc', '', ''));
        $this->expectKind(SseInterestCaseException::VALIDATION, fn () => $this->f->service->addLink($a, $id, 'nimporte', '1', '', ''));
        $v = $this->f->service->addLink($a, $id, 'vehicle', '', 'AB-123-CD pick-up gris', '');
        self::assertSame('AB-123-CD pick-up gris', $v['label']);
        $this->expectKind(SseInterestCaseException::VALIDATION, fn () => $this->f->service->addLink($a, $id, 'vehicle', '', 'x', ''));
    }

    public function testLinkedItemTurnsMaskedAndNeverShowsTheRememberedTitle(): void
    {
        $id = $this->f->open(11);
        $this->f->service->addLink($this->f->actor(11), $id, 'person', '5', '', '');
        $masked = $this->f->service->presentLinks($this->f->actor(12), [['id' => 1, 'link_type' => 'identity', 'target_id' => '8', 'label' => 'Titre mémorisé', 'note' => null, 'created_at' => '2026-11-15 12:00:00']]);
        self::assertTrue($masked[0]['masked']);
        self::assertNull($masked[0]['href']);
        self::assertStringNotContainsString('Titre mémorisé', $masked[0]['label'], 'le titre mémorisé n’est jamais affiché pour un lecteur sans droit');
        $gone = $this->f->service->presentLinks($this->f->actor(11), [['id' => 2, 'link_type' => 'person', 'target_id' => '404', 'label' => 'Titre mémorisé', 'note' => null, 'created_at' => '2026-11-15 12:00:00']]);
        self::assertTrue($gone[0]['missing']);
        self::assertStringNotContainsString('Titre mémorisé', $gone[0]['label']);
    }

    public function testRemoveLinkNeedsAReasonAndIsJournaled(): void
    {
        $id = $this->f->open(11);
        $a = $this->f->actor(11);
        $this->f->service->addLink($a, $id, 'site', '3', '', '');
        $linkId = (int) $this->f->pdo->query('SELECT id FROM sse_interest_case_links')->fetchColumn();
        $this->expectKind(SseInterestCaseException::VALIDATION, fn () => $this->f->service->removeLink($a, $id, $linkId, ''));
        $this->f->service->removeLink($a, $id, $linkId, 'Lien erroné');
        self::assertSame('0', (string) $this->f->pdo->query('SELECT COUNT(*) FROM sse_interest_case_links')->fetchColumn());
        $h = $this->f->pdo->query("SELECT action, old_value, reason FROM sse_interest_case_history WHERE action = 'link_remove'")->fetch(\PDO::FETCH_ASSOC);
        self::assertSame(['link_remove', 'Dépôt nord', 'Lien erroné'], array_values($h));
        $this->expectKind(SseInterestCaseException::RULE, fn () => $this->f->service->removeLink($a, $id, $linkId, 'Encore'));
    }

    public function testLinkCannotBeRemovedThroughAnotherCase(): void
    {
        $id = $this->f->open(11);
        $other = $this->f->open(11, ['temporary_designation' => 'Autre dossier']);
        $a = $this->f->actor(11);
        $this->f->service->addLink($a, $id, 'site', '3', '', '');
        $linkId = (int) $this->f->pdo->query('SELECT id FROM sse_interest_case_links')->fetchColumn();
        $this->expectKind(SseInterestCaseException::RULE, fn () => $this->f->service->removeLink($a, $other, $linkId, 'Tentative IDOR'));
        self::assertSame('1', (string) $this->f->pdo->query('SELECT COUNT(*) FROM sse_interest_case_links')->fetchColumn());
    }

    public function testLinkSearchOnlyReturnsTheCommunityAndIsEmptyWhenTooShort(): void
    {
        $a = $this->f->actor(11);
        self::assertSame([['id' => '5', 'label' => 'DUPONT Jean', 'hint' => '']], $this->f->service->searchLinks($a, 'person', 'dupo'));
        self::assertSame([], $this->f->service->searchLinks($a, 'person', 'd'));
        self::assertSame([['id' => '5', 'label' => 'AUTRE COMMUNAUTÉ', 'hint' => '']], $this->f->service->searchLinks($this->f->actor(20), 'person', 'autre'));
        self::assertSame([], $this->f->service->searchLinks($a, 'person', 'autre'));
        self::assertSame([], $this->f->service->searchLinks($a, 'identity', 'prot'), 'identités protégées absentes de la recherche');
        $this->expectKind(SseInterestCaseException::FORBIDDEN, fn () => $this->f->service->searchLinks($this->f->actor(12), 'person', 'dupo'));
    }

    // ------------------------------------------------------------------ File : onglets, recherche, filtres, tri, pagination

    private function seedQueue(): void
    {
        $m = $this->f->actor(10);
        $specs = [
            ['Convoi nord', 'critique', 'vehicule', 'encadrement', 'a_qualifier', 'convoi,nord'],
            ['Dépôt abandonné', 'courant', 'personne', 'interne', 'en_validation', 'depot'],
            ['Chef de village', 'a_surveiller', 'personne', 'interne', 'en_analyse', ''],
            ['Passeur', 'prioritaire', 'autre', 'confidentiel', 'sans_suite', 'frontière'],
        ];
        foreach ($specs as $i => [$title, $prio, $type, $class, $status, $tags]) {
            $id = $this->f->open(10, ['temporary_designation' => $title, 'interest_level' => $prio, 'case_type' => $type, 'classification' => $class, 'tags' => $tags, 'suspected_alias' => $i === 3 ? 'Le Corbeau' : '']);
            $this->f->pdo->exec("UPDATE sse_interest_cases SET status = '" . $status . "', updated_at = '2026-11-1" . $i . " 10:00:00' WHERE id = " . $id);
        }
        $this->f->pdo->exec("UPDATE sse_interest_cases SET due_at = '2026-11-01 23:59:00', assignee_user_id = 13 WHERE temporary_designation = 'Convoi nord'");
        unset($m);
    }

    public function testTabsCountersAndTabFiltering(): void
    {
        $this->seedQueue();
        $a = $this->f->actor(10);
        $p = $this->f->service->page($a, ['onglet' => 'ouverts']);
        self::assertSame(['ouverts' => 2, 'a_valider' => 1, 'en_attente' => 0, 'clos' => 1, 'archives' => 0, 'corbeille' => 0], $p['counts']);
        self::assertSame(2, $p['total']);
        self::assertSame(1, $this->f->service->page($a, ['onglet' => 'a_valider'])['total']);
        self::assertSame(4, $this->f->service->page($a, ['onglet' => 'tous'])['total']);
        self::assertSame(2, $this->f->service->page($a, ['onglet' => 'inconnu'])['total'], 'onglet inconnu : retour aux ouverts');
    }

    public function testSearchFullTextAcrossFieldsAndEscapesWildcards(): void
    {
        $this->seedQueue();
        $a = $this->f->actor(10);
        self::assertSame(1, $this->f->service->page($a, ['onglet' => 'tous', 'q' => 'corbeau'])['total'], 'alias');
        self::assertSame(1, $this->f->service->page($a, ['onglet' => 'tous', 'q' => 'frontière'])['total'], 'étiquette');
        self::assertSame(1, $this->f->service->page($a, ['onglet' => 'tous', 'q' => 'convoi nord'])['total'], 'tous les mots, dans l’ordre qu’on veut');
        self::assertSame(1, $this->f->service->page($a, ['onglet' => 'tous', 'q' => 'nord convoi'])['total']);
        self::assertSame(0, $this->f->service->page($a, ['onglet' => 'tous', 'q' => '%'])['total'], '« % » n’est pas un joker');
        self::assertSame(0, $this->f->service->page($a, ['onglet' => 'tous', 'q' => "' OR 1=1 --"])['total']);
        self::assertSame(4, $this->f->service->page($a, ['onglet' => 'tous', 'q' => 'DI-'])['total'], 'référence');
    }

    public function testFiltersAndSorts(): void
    {
        $this->seedQueue();
        $a = $this->f->actor(10);
        $titles = static fn (array $p): array => array_column($p['rows'], 'temporary_designation');
        self::assertSame(['Convoi nord'], $titles($this->f->service->page($a, ['onglet' => 'tous', 'priorite' => 'critique'])));
        self::assertSame(['Chef de village', 'Dépôt abandonné'], $titles($this->f->service->page($a, ['onglet' => 'tous', 'type' => 'personne', 'tri' => 'titre'])));
        self::assertSame(['Convoi nord'], $titles($this->f->service->page($a, ['onglet' => 'tous', 'responsable' => '13'])));
        self::assertSame(3, $this->f->service->page($a, ['onglet' => 'tous', 'responsable' => 'aucun'])['total']);
        self::assertSame(['Dépôt abandonné', 'Chef de village'], $titles($this->f->service->page($a, ['onglet' => 'tous', 'classification' => 'interne', 'tri' => 'reference'])));
        self::assertSame(['Convoi nord'], $titles($this->f->service->page($a, ['onglet' => 'tous', 'en_retard' => '1'])), 'échéance dépassée, dossier non clos');
        self::assertSame(['Convoi nord', 'Passeur', 'Chef de village', 'Dépôt abandonné'], $titles($this->f->service->page($a, ['onglet' => 'tous', 'tri' => 'priorite'])));
        self::assertSame('Convoi nord', $titles($this->f->service->page($a, ['onglet' => 'tous', 'tri' => 'echeance']))[0]);
        self::assertSame('Passeur', $titles($this->f->service->page($a, ['onglet' => 'tous', 'tri' => 'recent']))[0], 'plus récemment mis à jour d’abord');
        self::assertSame(['Passeur'], $titles($this->f->service->page($a, ['onglet' => 'tous', 'etat' => 'sans_suite'])));
    }

    public function testPaginationClampsAndKeepsCounters(): void
    {
        for ($i = 1; $i <= 12; $i++) {
            $this->f->open(10, ['temporary_designation' => 'Dossier ' . str_pad((string) $i, 2, '0', STR_PAD_LEFT)]);
        }
        $a = $this->f->actor(10);
        $p1 = $this->f->service->page($a, ['onglet' => 'ouverts', 'par_page' => '5', 'tri' => 'titre']);
        self::assertSame(5, count($p1['rows']));
        self::assertSame(12, $p1['total']);
        self::assertSame(3, $p1['pages']);
        $p3 = $this->f->service->page($a, ['onglet' => 'ouverts', 'par_page' => '5', 'tri' => 'titre', 'page' => '3']);
        self::assertSame(['Dossier 11', 'Dossier 12'], array_column($p3['rows'], 'temporary_designation'));
        $over = $this->f->service->page($a, ['onglet' => 'ouverts', 'par_page' => '5', 'page' => '99']);
        self::assertSame(3, $over['page']);
        self::assertSame(12, $over['counts']['ouverts']);
    }

    public function testQueueSanitizesHostileQueryValues(): void
    {
        $this->seedQueue();
        $n = $this->f->service->normalizeQuery($this->f->actor(10), ['onglet' => '<script>', 'priorite' => "x' OR 1=1", 'type' => '../etc', 'classification' => 'secret_defense', 'tri' => 'DROP TABLE', 'page' => '-4', 'par_page' => '99999', 'q' => str_repeat('a', 500)]);
        self::assertSame('ouverts', $n['filters']['tab']);
        self::assertSame('', $n['filters']['priority']);
        self::assertSame('', $n['filters']['type']);
        self::assertSame('', $n['filters']['classification']);
        self::assertSame('recent', $n['sort']);
        self::assertSame(1, $n['page']);
        self::assertSame(100, $n['per_page']);
        self::assertSame(120, mb_strlen($n['filters']['q']));
    }

    // ------------------------------------------------------------------ Notifications

    public function testAssigneeIsNotifiedOnceAndWithoutTheTitle(): void
    {
        $id = $this->f->open(11, ['assignee_user_id' => '13']);
        self::assertCount(1, $this->f->notices);
        [$tenant, $user, $payload] = $this->f->notices[0];
        self::assertSame([1, 13], [$tenant, $user]);
        self::assertSame('atak/sse/interet/' . $id, $payload['path']);
        self::assertStringNotContainsString('Homme au manteau gris', json_encode($payload), 'ni titre ni contenu dans la notification');
        $this->f->notices = [];
        $this->f->service->update($this->f->actor(11), $id, ['assignee_user_id' => '12', 'tags' => 'x'], 1);
        $to = array_map(static fn (array $n): int => $n[1], $this->f->notices);
        self::assertSame([12], $to, 'le nouveau responsable est prévenu une seule fois ; ni l’auteur ni l’ancien responsable');
    }

    public function testWatchersAreNotifiedButNotTheAuthor(): void
    {
        $id = $this->f->open(11);
        $this->f->service->watch($this->f->actor(13), $id, true);
        $this->f->notices = [];
        $this->f->service->transition($this->f->actor(11), $id, 'a_qualifier', '', 1);
        $recipients = array_map(static fn (array $n): int => $n[1], $this->f->notices);
        self::assertSame([13], $recipients);
        $this->f->notices = [];
        $this->f->service->watch($this->f->actor(13), $id, false);
        $this->f->service->transition($this->f->actor(11), $id, 'en_collecte', '', 2);
        self::assertSame([], $this->f->notices);
    }

    public function testRecipientWithoutAccessIsNotNotified(): void
    {
        $id = $this->f->open(11);
        $this->f->service->repository()->replaceAcl(1, $id, [], [13], 10);
        $this->f->service->watch($this->f->actor(12), $id, true);
        $this->f->pdo->exec("INSERT INTO sse_interest_case_watchers (tenant_id, interest_case_id, user_id) VALUES (1, $id, 13)");
        $this->f->notices = [];
        $this->f->service->addNote($this->f->actor(11), $id, 'Une note');
        self::assertSame([12], array_map(static fn (array $n): int => $n[1], $this->f->notices), 'le membre interdit nominativement n’est pas prévenu');
    }

    // ------------------------------------------------------------------ Aperçu, audit, abonnement

    public function testPreviewIsSanitizedHtmlAndCarriesNoRawMarkup(): void
    {
        $id = $this->f->open(11, ['opening_reason' => 'Motif <script>alert(1)</script> avec **gras**', 'description' => '- un' . "\n" . '- deux']);
        $p = $this->f->service->preview($this->f->actor(11), $id);
        self::assertStringNotContainsString('<script>', $p['reason_html']);
        self::assertStringContainsString('&lt;script&gt;', $p['reason_html']);
        self::assertStringContainsString('<strong>gras</strong>', $p['reason_html']);
        self::assertStringContainsString('<ul><li>un</li><li>deux</li></ul>', $p['description_html']);
        self::assertSame('P2', $p['priority_code']);
    }

    public function testEveryActionIsAudited(): void
    {
        $m = $this->f->actor(10);
        $id = $this->f->open(10);
        $this->f->service->update($m, $id, ['tags' => 'x'], 1);
        $this->f->service->transition($m, $id, 'a_qualifier', '', 2);
        $this->f->service->addLink($m, $id, 'site', '3', '', '');
        $this->f->service->addNote($m, $id, 'Une note');
        $this->f->service->recordExport($m, $id);
        $this->f->service->trash($m, $id, 'Doublon', 3);
        $this->f->service->restore($m, $id);
        $actions = array_column($this->f->audit, 'action');
        foreach (['create', 'update', 'status', 'link_add', 'note', 'export', 'trash', 'restore'] as $a) {
            self::assertContains('interest_case.' . $a, $actions, $a);
        }
        foreach ($this->f->audit as $row) {
            self::assertSame(1, $row['tenant_id']);
            self::assertSame('interest_case', $row['object_type']);
            self::assertSame($id, $row['object_id']);
        }
    }

    // ------------------------------------------------------------------ Mode dégradé (étape de migration non lancée)

    public function testDegradedModeStillCreatesListsAndChangesState(): void
    {
        $this->f->close();
        $this->f = new SseInterestFixture(false);
        $a = $this->f->actor(11);
        $res = $this->f->service->create($a, ['temporary_designation' => 'Sans migration', 'opening_reason' => 'Un motif suffisant.', 'interest_level' => 'critique', 'digital_signature' => '1', 'classification' => 'tres_restreint']);
        $page = $this->f->service->page($a, ['onglet' => 'tous']);
        self::assertTrue($page['degraded']);
        self::assertSame(1, $page['total']);
        self::assertSame('P1', $page['rows'][0]['priority_code']);
        self::assertSame('interne', $page['rows'][0]['classification'], 'sans colonne : classification par défaut');
        $this->f->service->update($a, $res['id'], ['temporary_designation' => 'Renommé', 'tags' => 'ignoré'], null, $this->f->row($res['id'])['updated_at']);
        self::assertSame('Renommé', $this->f->row($res['id'])['temporary_designation']);
        $this->f->service->transition($a, $res['id'], 'a_qualifier', '', null, $this->f->row($res['id'])['updated_at']);
        self::assertSame('a_qualifier', $this->f->row($res['id'])['status']);
        $journal = $this->f->service->repository()->listUpdates(1, $res['id']);
        self::assertStringContainsString('À qualifier', $journal[0]['body'], 'sans historique, le changement d’état reste lisible dans la discussion');
        $e = $this->expectKind(SseInterestCaseException::DEGRADED, fn () => $this->f->service->trash($this->f->actor(10), $res['id'], 'Doublon', 1));
        self::assertStringContainsString('mise à jour de la base', $e->getMessage());
        $this->expectKind(SseInterestCaseException::DEGRADED, fn () => $this->f->service->addLink($this->f->actor(10), $res['id'], 'site', '3', '', ''));
    }

    public function testDegradedModeStillRefusesAStaleUpdate(): void
    {
        $this->f->close();
        $this->f = new SseInterestFixture(false);
        $a = $this->f->actor(11);
        $id = $this->f->service->create($a, ['temporary_designation' => 'Sans migration', 'opening_reason' => 'Un motif suffisant.', 'digital_signature' => '1'])['id'];
        $this->f->service->update($a, $id, ['temporary_designation' => 'Premier'], null, $this->f->row($id)['updated_at']);
        $this->f->pdo->exec("UPDATE sse_interest_cases SET updated_at = '2026-11-16 00:00:00' WHERE id = " . $id);
        $this->expectKind(SseInterestCaseException::CONFLICT, fn () => $this->f->service->update($a, $id, ['temporary_designation' => 'Second'], null, '2026-11-15 12:00:00'));
        self::assertSame('Premier', $this->f->row($id)['temporary_designation']);
    }
}
