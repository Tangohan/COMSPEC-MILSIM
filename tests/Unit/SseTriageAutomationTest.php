<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\SseSuggestionQueueRepository;
use App\Repositories\SseTriageEventRepository;
use App\Repositories\SseTriageRuleRepository;
use App\Repositories\SseTriageStateRepository;
use App\Services\Sse\SseTriageActions;
use App\Services\Sse\SseTriageInbox;
use App\Services\Sse\SseTriageRules;
use App\Services\Sse\SseTriageSubjects;
use App\Support\ShortCache;
use App\Support\SseTriageBadges;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\SseTriageSqlite;

/**
 * « À trier » avec ses états : compteurs hors acquittés et hors veille, dédoublonnage des signaux, mode dégradé, acquitter, veille,
 * rappeler, assigner, annuler, règles Cerbère-Tri (conditions, aperçu = nombre réel, journal, annulation). Vraie base sqlite, vraies
 * tables de l'étape 4740, vrais dépôts ; les files sont celles du bureau (signaux du moteur) ou des chargeurs de test.
 */
final class SseTriageAutomationTest extends TestCase
{
    private const PHOTO = 'Lacune / besoin proposé : Photographie manquante';
    private const BIO = 'Lacune / besoin proposé : Biométrie manquante';

    private PDO $pdo;
    private int $now;

    protected function setUp(): void
    {
        ShortCache::setEnabled(false);
        $this->pdo = SseTriageSqlite::open(true);
        $this->now = strtotime('2026-11-15 12:00:00 UTC');
    }

    protected function tearDown(): void
    {
        ShortCache::setEnabled(null);
        SseTriageSqlite::close();
    }

    private function clock(): string
    {
        return gmdate('Y-m-d H:i:s', $this->now);
    }

    private function signal(int $case, string $type, string $title, string $rule, string $at, string $severity = 'medium', string $status = 'open'): int
    {
        $this->pdo->prepare("INSERT INTO sse_engine_signals (tenant_id, case_id, signal_type, severity, title, detail, status, rule_key, run_id, created_at, updated_at) VALUES (1, ?, ?, ?, ?, 'BESOIN — détail du manque', ?, ?, 'r', ?, ?)")
            ->execute([$case, $type, $severity, $title, $status, $rule, $at, $at]);

        return (int) $this->pdo->lastInsertId();
    }

    /** Un signal par nuit pendant $nights nuits, pour chaque dossier : le bruit observé en production. */
    private function noise(int $cases, int $nights): void
    {
        for ($c = 1; $c <= $cases; $c++) {
            SseTriageSqlite::addCase($this->pdo, $c, 'DOS-' . str_pad((string) $c, 4, '0', STR_PAD_LEFT), 'Dossier ' . $c);
            for ($n = $nights; $n >= 1; $n--) {
                $at = gmdate('Y-m-d H:i:s', $this->now - $n * 86400);
                $this->signal($c, 'gap_auto', self::PHOTO, 'auto_gap', $at);
                $this->signal($c, 'gap_auto', self::BIO, 'auto_gap', $at);
                $this->signal($c, 'completeness', 'Complétude faible (' . (18 + $n % 5) . '/100)', 'low_completeness', $at);
            }
        }
    }

    private function inbox(?SseTriageSubjects $subjects = null): SseTriageInbox
    {
        $defs = array_values(array_filter(SseTriageInbox::defaultDefinitions(7), static fn (array $d): bool => $d['key'] === 'anomalies'));
        $clock = fn (): string => $this->clock();

        return new SseTriageInbox(
            $defs,
            fn (): bool => true,
            fn (): int => $this->now,
            new SseTriageStateRepository(null, $clock),
            new SseTriageRuleRepository(null, $clock),
            new SseTriageEventRepository(null, $clock),
            $subjects ?? new SseTriageSubjects(null, fn (): bool => true, fn (): bool => false, fn (): array => []),
        );
    }

    /** @return array<string, mixed> */
    private function snap(?SseTriageInbox $inbox = null, int $user = 7): array
    {
        ShortCache::bump(SseTriageInbox::NS);

        return ($inbox ?? $this->inbox())->snapshot(1, $user, ['anomalies']);
    }

    private function actions(): SseTriageActions
    {
        $clock = fn (): string => $this->clock();

        return new SseTriageActions(new SseTriageStateRepository(null, $clock), new SseTriageEventRepository(null, $clock), new SseTriageRuleRepository(null, $clock), static fn (array $row): null => null, $clock);
    }

    public function testThirtyNightsOfDuplicatesShowAsOneItemPerConditionAndAreCountedOnce(): void
    {
        $this->noise(4, 30);
        self::assertSame(4 * 3 * 30, (int) $this->pdo->query('SELECT COUNT(*) FROM sse_engine_signals')->fetchColumn(), 'base : 360 lignes');
        $snap = $this->snap();

        self::assertSame(12, $snap['total'], '4 dossiers × 3 conditions, pas 360');
        self::assertSame(12, $snap['sources']['anomalies']['count']);
        self::assertSame(12, $snap['counts']['open']);
        self::assertSame(30, $snap['items'][0]['merged'] + 1, 'chaque ligne dit combien d’observations elle regroupe');
    }

    public function testAcknowledgedAndSnoozedItemsLeaveTheCounts(): void
    {
        $this->noise(3, 2);
        $snap = $this->snap();
        self::assertSame(9, $snap['total']);
        $keys = array_map(static fn (array $i): string => $i['key'], $snap['items']);
        $this->actions()->apply(1, 'acknowledge', array_slice($keys, 0, 2), 7, 'Analyste', ['note' => 'connu']);
        $this->actions()->apply(1, 'snooze', array_slice($keys, 2, 3), 7, 'Analyste', ['days' => 7]);
        $snap = $this->snap();

        self::assertSame(4, $snap['total'], '9 − 2 acquittés − 3 en veille');
        self::assertSame(4, $snap['sources']['anomalies']['count']);
        self::assertSame(['open' => 4, 'mine' => 0, 'snoozed' => 3, 'acknowledged' => 2], $snap['counts']);
        self::assertSame(9, count($snap['items']), 'les éléments traités restent consultables');
        $badges = SseTriageBadges::fromSnapshot([['id' => 'x', 'items' => [['id' => 'anomalies']]]], $snap);
        self::assertSame(4, $badges['total'], 'la pastille et le total du bouton');
        self::assertSame(4, $badges['entries']['anomalies']['count']);
        self::assertSame(4, $badges['sections']['x']['count']);
    }

    public function testASnoozeEndsByItselfAndTheItemComesBack(): void
    {
        $this->noise(1, 1);
        $key = $this->snap()['items'][0]['key'];
        $this->actions()->apply(1, 'snooze', [$key], 7, 'Analyste', ['days' => 7]);
        self::assertSame(2, $this->snap()['total']);

        $this->now += 6 * 86400;
        self::assertSame(2, $this->snap()['total'], '6 jours : toujours en veille');
        $this->now += 2 * 86400;
        self::assertSame(3, $this->snap()['total'], 'au-delà de 7 jours l’élément revient seul');
    }

    public function testAnAcknowledgedItemOnlyComesBackWhenItsConditionReappearsAfterBeingResolved(): void
    {
        $this->noise(1, 1);
        $q = new SseSuggestionQueueRepository(null, fn (): string => $this->clock());
        $snap = $this->snap();
        $photo = array_values(array_filter($snap['items'], static fn (array $i): bool => str_contains((string) $i['title'], 'Photographie')))[0];
        $this->actions()->apply(1, 'acknowledge', [$photo['key']], 7, 'Analyste');

        // Le moteur continue d'observer la lacune : le signal est mis à jour, reste acquitté.
        $q->addSignal(1, ['case_id' => 1, 'signal_type' => 'gap_auto', 'severity' => 'medium', 'title' => self::PHOTO, 'detail' => 'BESOIN — détail du manque', 'rule_key' => 'auto_gap', 'run_id' => 'n2']);
        self::assertSame(2, $this->snap()['total'], 'toujours acquitté tant que la condition dure');

        // Photo versée : signal résolu. Elle disparaît puis réapparaît : nouveau signal, donc nouvel élément à trier.
        $q->resolveUnobserved(1, 1, 'n3');
        $q->addSignal(1, ['case_id' => 1, 'signal_type' => 'gap_auto', 'severity' => 'medium', 'title' => self::PHOTO, 'detail' => 'BESOIN — détail du manque', 'rule_key' => 'auto_gap', 'run_id' => 'n4']);
        $snap = $this->snap();

        self::assertSame(1, $snap['total'], 'la complétude et la biométrie ont été résolues aussi ; seule la photo réapparue est de nouveau à trier');
        self::assertNotSame($photo['key'], array_values(array_filter($snap['items'], static fn (array $i): bool => $i['triage']['status'] === 'open' && str_contains((string) $i['title'], 'Photographie')))[0]['key']);
    }

    public function testRecallPutsAnItemBackAndAssignmentFeedsMyItems(): void
    {
        $this->noise(1, 1);
        $keys = array_map(static fn (array $i): string => $i['key'], $this->snap()['items']);
        $this->actions()->apply(1, 'acknowledge', [$keys[0]], 7, 'Analyste');
        $this->actions()->apply(1, 'recall', [$keys[0]], 7, 'Analyste');
        $this->actions()->apply(1, 'assign', [$keys[1]], 7, 'Analyste', ['assignee' => 7]);
        $this->actions()->apply(1, 'assign', [$keys[2]], 7, 'Analyste', ['assignee' => 9]);
        $snap = $this->snap(null, 7);

        self::assertSame(3, $snap['total']);
        self::assertSame(1, $snap['counts']['mine'], 'seul l’élément assigné à moi');
        self::assertSame(1, $this->snap(null, 9)['counts']['mine']);
        $assigned = array_values(array_filter($snap['items'], static fn (array $i): bool => $i['key'] === $keys[1]))[0];
        self::assertSame(7, $assigned['triage']['assigned_to']);
        // Assigner ne change pas le statut, et acquitter garde l'assignation.
        $this->actions()->apply(1, 'acknowledge', [$keys[1]], 7, 'Analyste');
        $again = array_values(array_filter($this->snap()['items'], static fn (array $i): bool => $i['key'] === $keys[1]))[0];
        self::assertSame('acknowledged', $again['triage']['status']);
        self::assertSame(7, $again['triage']['assigned_to']);
    }

    public function testUndoRestoresTheStateBeforeAndOnlyOnce(): void
    {
        $this->noise(1, 1);
        $keys = array_map(static fn (array $i): string => $i['key'], $this->snap()['items']);
        $a = $this->actions()->apply(1, 'acknowledge', $keys, 7, 'Analyste');
        self::assertSame(0, $this->snap()['total']);

        self::assertFalse($this->actions()->undoBatch(1, $a['batch'], 8, 'Autre')['ok'], 'seul l’auteur annule son lot');
        $undo = $this->actions()->undoBatch(1, $a['batch'], 7, 'Analyste');
        self::assertTrue($undo['ok']);
        self::assertSame(3, $undo['restored']);
        self::assertSame(3, $this->snap()['total']);
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM sse_triage_state')->fetchColumn(), 'l’état d’avant (aucun) est rétabli');
        self::assertFalse($this->actions()->undoBatch(1, $a['batch'], 7, 'Analyste')['ok'], 'une seule fois');
    }

    public function testEveryActionIsJournaledAndAudited(): void
    {
        $this->noise(1, 1);
        $rows = [];
        $clock = fn (): string => $this->clock();
        $actions = new SseTriageActions(new SseTriageStateRepository(null, $clock), new SseTriageEventRepository(null, $clock), null, function (array $row) use (&$rows): void {
            $rows[] = $row;
        }, $clock);
        $key = $this->snap()['items'][0]['key'];
        $r = $actions->apply(1, 'snooze', [$key], 7, 'Alice', ['days' => 30, 'note' => 'attente de la photo']);

        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM sse_triage_events')->fetchColumn());
        $event = $this->pdo->query('SELECT * FROM sse_triage_events')->fetch(PDO::FETCH_ASSOC);
        self::assertSame('snooze', $event['action']);
        self::assertSame($r['batch'], $event['batch_id']);
        self::assertSame('Alice', $event['actor_label']);
        self::assertSame('attente de la photo', $event['note']);
        self::assertCount(1, $rows);
        self::assertSame('sse.triage.snooze', $rows[0]['action']);
        self::assertSame(1, $rows[0]['tenant_id']);
        self::assertSame(30, $rows[0]['after']['days']);
        $state = $this->pdo->query('SELECT * FROM sse_triage_state')->fetch(PDO::FETCH_ASSOC);
        self::assertSame('2026-12-15 12:00:00', $state['snoozed_until']);
        self::assertSame('attente de la photo', $state['note']);
    }

    public function testActionsRefuseBadInput(): void
    {
        self::assertFalse($this->actions()->apply(1, 'delete', ['signal:1'], 7, 'A')['ok']);
        self::assertFalse($this->actions()->apply(1, 'acknowledge', [], 7, 'A')['ok']);
        self::assertFalse($this->actions()->apply(1, 'snooze', ['signal:1'], 7, 'A', ['days' => 0])['ok']);
        self::assertFalse($this->actions()->apply(1, 'snooze', ['signal:1'], 7, 'A', ['days' => 4000])['ok']);
        self::assertFalse($this->actions()->apply(0, 'acknowledge', ['signal:1'], 7, 'A')['ok']);
        self::assertFalse($this->actions()->apply(1, 'acknowledge', ['signal:1'], 0, 'A')['ok']);
    }

    public function testStatesAreKeptPerCommunity(): void
    {
        $this->noise(1, 1);
        $this->actions()->apply(1, 'acknowledge', ['signal:1'], 7, 'A');
        $repo = new SseTriageStateRepository(null, fn (): string => $this->clock());

        self::assertNotNull($repo->find(1, 'signal:1'));
        self::assertNull($repo->find(2, 'signal:1'), 'la communauté 2 ne voit pas l’état de la 1');
        $this->actions()->apply(2, 'snooze', ['signal:1'], 8, 'B', ['days' => 7]);
        self::assertSame('acknowledged', $repo->find(1, 'signal:1')['status']);
        self::assertSame('snoozed', $repo->find(2, 'signal:1')['status']);
    }

    public function testTheUniqueKeyIsInTheCreateTableAndNeverDuplicates(): void
    {
        $sql = (string) file_get_contents(dirname(__DIR__, 2) . '/migrations/2026_11_18_100_sse_triage_automatisms.sql');
        self::assertMatchesRegularExpression('/CREATE TABLE IF NOT EXISTS sse_triage_state \(.*UNIQUE KEY uq_sse_triage_item \(tenant_id, item_type, item_key\)/s', $sql);
        self::assertStringNotContainsString('ADD UNIQUE', $sql);
        $this->pdo->exec("INSERT INTO sse_triage_state (tenant_id, item_type, item_key, status, updated_at) VALUES (1, 'signal', '5', 'open', 'x')");
        $this->expectException(\PDOException::class);
        $this->pdo->exec("INSERT INTO sse_triage_state (tenant_id, item_type, item_key, status, updated_at) VALUES (1, 'signal', '5', 'open', 'x')");
    }

    public function testWithoutTheTablesTheListStaysReadableAndSaysSo(): void
    {
        SseTriageSqlite::close();
        $this->pdo = SseTriageSqlite::open(false);
        $this->noise(2, 3);
        $snap = $this->snap();

        self::assertTrue($snap['degraded']);
        self::assertSame(6, $snap['total'], 'dédoublonné même sans l’étape');
        self::assertSame(6, count($snap['items']));
        self::assertSame('open', $snap['items'][0]['triage']['status']);
    }

    public function testRulesPreviewIsExactlyTheNumberOfItemsTheyTreat(): void
    {
        $this->noise(5, 40);
        $snap = $this->snap();
        $rule = ['id' => 0, 'source_key' => 'anomalies', 'type_key' => 'signal:completeness', 'keyword' => '', 'min_age_days' => 30, 'min_severity' => null, 'max_severity' => null, 'scope' => 'all', 'action' => 'acknowledge'];
        $preview = count(SseTriageRules::affected($rule, $snap['items'], $this->now));

        self::assertSame(5, $preview, 'cinq complétudes faibles de plus de 30 jours');
        $repo = new SseTriageRuleRepository(null, fn (): string => $this->clock());
        $id = $repo->save(1, null, ['name' => 'Complétudes anciennes', 'enabled' => 1] + $rule + ['action_value' => 0], 7);
        $after = $this->snap();

        self::assertSame($snap['total'] - $preview, $after['total'], 'la liste perd exactement l’aperçu');
        $row = $repo->find(1, $id);
        self::assertSame($preview, (int) $row['fired_count'], 'le compteur de la règle');
        self::assertSame('2026-11-15 12:00:00', $row['last_fired_at']);
        self::assertSame(5, $after['counts']['acknowledged']);
    }

    public function testAnAutomaticActionIsJournaledAsCerbereTriAndCanBeUndoneForGood(): void
    {
        $this->noise(2, 40);
        $repo = new SseTriageRuleRepository(null, fn (): string => $this->clock());
        $id = $repo->save(1, null, ['name' => 'Complétudes anciennes', 'enabled' => 1, 'source_key' => 'anomalies', 'type_key' => 'signal:completeness', 'min_age_days' => 30, 'action' => 'acknowledge'], 7);
        $this->snap();
        $events = (new SseTriageEventRepository(null, fn (): string => $this->clock()))->recent(1);

        self::assertCount(2, $events);
        self::assertSame('auto_acknowledge', $events[0]['action']);
        self::assertSame('Cerbère-Tri', $events[0]['actor_label']);
        self::assertSame($id, (int) $events[0]['rule_id']);
        self::assertSame('Complétudes anciennes', $events[0]['note']);
        self::assertNull($events[0]['actor_user_id']);

        $undo = $this->actions()->undoEvent(1, (int) $events[0]['id'], 7, 'Gestionnaire', true);
        self::assertTrue($undo['ok']);
        self::assertFalse($this->actions()->undoEvent(1, (int) $events[0]['id'], 7, 'Gestionnaire', true)['ok'], 'une seule fois');
        self::assertSame(1, (int) $repo->find(1, $id)['fired_count'], 'le compteur redescend');
        $snap = $this->snap();
        $undone = array_values(array_filter($snap['items'], static fn (array $i): bool => $i['key'] === $events[0]['key']))[0];
        self::assertSame('open', $undone['triage']['status'], 'l’élément est de retour');
        self::assertSame('open', (string) (new SseTriageStateRepository(null, fn (): string => $this->clock()))->find(1, $events[0]['key'])['status'], 'la ligne reste : la règle ne le reprend pas');
        $this->snap();
        self::assertCount(2, array_filter((new SseTriageEventRepository(null, fn (): string => $this->clock()))->recent(1), static fn (array $e): bool => str_starts_with((string) $e['action'], 'auto_')), 'pas de nouveau déclenchement sur l’élément annulé');
    }

    public function testOnlyAManagerUndoesAnAutomaticActionAndOnlyTheAuthorUndoesAHumanOne(): void
    {
        $this->noise(1, 40);
        (new SseTriageRuleRepository(null, fn (): string => $this->clock()))->save(1, null, ['name' => 'R', 'enabled' => 1, 'type_key' => 'signal:completeness', 'action' => 'acknowledge'], 7);
        $this->snap();
        $auto = (new SseTriageEventRepository(null, fn (): string => $this->clock()))->recent(1)[0];
        self::assertFalse($this->actions()->undoEvent(1, (int) $auto['id'], 8, 'Analyste', false)['ok']);

        $this->actions()->apply(1, 'acknowledge', ['signal:1'], 7, 'Alice');
        $human = (new SseTriageEventRepository(null, fn (): string => $this->clock()))->recent(1)[0];
        self::assertFalse($this->actions()->undoEvent(1, (int) $human['id'], 8, 'Bob', true)['ok'], 'même un gestionnaire n’annule pas l’action d’un autre analyste');
        self::assertTrue($this->actions()->undoEvent(1, (int) $human['id'], 7, 'Alice', false)['ok']);
    }

    public function testRulesRespectConditionsSeverityAgeSourceAndNeverTouchAnItemWithAState(): void
    {
        $now = $this->now;
        $item = static fn (string $key, string $source, string $type, string $sev, int $days, bool $exists = false, bool $cover = false): array => [
            'key' => $key, 'source' => $source, 'type_key' => $type, 'type_label' => $type, 'title' => 'Photographie manquante', 'severity' => $sev,
            'at' => gmdate('Y-m-d H:i:s', $now - $days * 86400), 'priority' => 0, 'subject' => ['cover' => $cover], 'triage' => ['exists' => $exists],
        ];
        $rule = ['source_key' => 'anomalies', 'type_key' => 'signal:gap_auto', 'keyword' => 'photo', 'min_age_days' => 10, 'min_severity' => 'medium', 'max_severity' => 'high', 'scope' => 'all'];

        self::assertTrue(SseTriageRules::matches($rule, $item('a', 'anomalies', 'signal:gap_auto', 'medium', 12), $now));
        self::assertFalse(SseTriageRules::matches($rule, $item('a', 'suggestions', 'signal:gap_auto', 'medium', 12), $now), 'autre file');
        self::assertFalse(SseTriageRules::matches($rule, $item('a', 'anomalies', 'signal:anomaly', 'medium', 12), $now), 'autre type');
        self::assertFalse(SseTriageRules::matches($rule, $item('a', 'anomalies', 'signal:gap_auto', 'medium', 9), $now), 'trop récent');
        self::assertFalse(SseTriageRules::matches($rule, $item('a', 'anomalies', 'signal:gap_auto', 'info', 12), $now), 'gravité trop basse');
        self::assertFalse(SseTriageRules::matches($rule, $item('a', 'anomalies', 'signal:gap_auto', 'critical', 12), $now), 'gravité trop haute');
        self::assertTrue(SseTriageRules::matches(['scope' => 'cover'] + $rule, $item('a', 'anomalies', 'signal:gap_auto', 'high', 12, false, true), $now));
        self::assertFalse(SseTriageRules::matches(['scope' => 'cover'] + $rule, $item('a', 'anomalies', 'signal:gap_auto', 'high', 12, false, false), $now), 'pas une identité de couverture');
        self::assertSame([], SseTriageRules::affected($rule, [$item('a', 'anomalies', 'signal:gap_auto', 'medium', 12, true)], $now), 'un élément qui a déjà un état n’est jamais repris');
    }

    public function testTheMinimumSeverityPresetIsAnAcknowledgeOfInformationItems(): void
    {
        $this->noise(1, 1);
        $q = new SseSuggestionQueueRepository(null, fn (): string => $this->clock());
        $q->addSignal(1, ['case_id' => 1, 'signal_type' => 'digest', 'severity' => 'info', 'title' => 'Évolutions depuis le dernier calcul', 'detail' => 'x', 'rule_key' => 'case_digest', 'run_id' => 'r']);
        $preset = \App\Support\SseTriagePresets::all()['min_severity'];
        (new SseTriageRuleRepository(null, fn (): string => $this->clock()))->save(1, null, ['name' => $preset['name'], 'preset_key' => 'min_severity', 'enabled' => 1] + $preset['rule'], 7);
        $snap = $this->snap();

        self::assertSame(3, $snap['total'], 'les 3 signaux de gravité moyenne restent');
        self::assertSame(1, $snap['counts']['acknowledged'], 'le signal d’information est acquitté par Cerbère-Tri');
    }

    public function testDisabledRulesAndTheEngineAutoResolveRuleNeverFireOnItems(): void
    {
        $this->noise(1, 40);
        $repo = new SseTriageRuleRepository(null, fn (): string => $this->clock());
        $repo->save(1, null, ['name' => 'Coupée', 'enabled' => 0, 'type_key' => 'signal:completeness', 'action' => 'acknowledge'], 7);
        $repo->save(1, null, ['name' => 'Résolution', 'preset_key' => 'auto_resolve', 'enabled' => 1, 'action' => 'acknowledge'], 7);

        self::assertSame(3, $this->snap()['total']);
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM sse_triage_events')->fetchColumn());
        self::assertTrue($repo->presetEnabled(1, 'auto_resolve', false));
        self::assertTrue($repo->presetEnabled(1, 'inconnu', true), 'sans ligne : la valeur par défaut');
    }

    public function testSnoozeAssignAndPriorityRulesApplyTheirAction(): void
    {
        $this->noise(1, 1);
        $repo = new SseTriageRuleRepository(null, fn (): string => $this->clock());
        $repo->save(1, null, ['name' => 'Veille photo', 'enabled' => 1, 'keyword' => 'Photographie', 'action' => 'snooze', 'action_value' => 14], 7);
        $repo->save(1, null, ['name' => 'Assigner bio', 'enabled' => 1, 'keyword' => 'Biométrie', 'action' => 'assign', 'action_value' => 9], 7);
        $repo->save(1, null, ['name' => 'Priorité complétude', 'enabled' => 1, 'type_key' => 'signal:completeness', 'action' => 'priority', 'action_value' => 15], 7);
        $snap = $this->snap();
        $byTitle = [];
        foreach ($snap['items'] as $i) {
            $byTitle[SseTriageInbox::reasonOf($i['title'])] = $i;
        }

        self::assertSame('snoozed', $byTitle[self::PHOTO]['triage']['status']);
        self::assertSame('2026-11-29 12:00:00', $byTitle[self::PHOTO]['triage']['snoozed_until']);
        self::assertSame(9, $byTitle[self::BIO]['triage']['assigned_to']);
        self::assertSame('open', $byTitle[self::BIO]['triage']['status']);
        $completeness = array_values(array_filter($snap['items'], static fn (array $i): bool => $i['type_key'] === 'signal:completeness'))[0];
        self::assertSame(15, $completeness['triage']['boost']);
        self::assertContains('Majoration d’une règle Cerbère-Tri', array_column($completeness['why'], 'label'));
    }
}
