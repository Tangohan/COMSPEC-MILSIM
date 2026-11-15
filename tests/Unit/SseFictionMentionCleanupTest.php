<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Sse\SseFictionMentionCleanup;
use App\Support\SseDocumentChromeCatalog;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Rattrapage des mentions de jeu enregistrées (tools/sse-strip-fiction-mentions.php) : simulation par défaut, seule la valeur
 * exacte livrée par défaut est retirée, un texte écrit par une communauté n'est jamais touché, idempotent, borné à une
 * communauté sur demande, table absente ignorée. Base sqlite en mémoire, vraies requêtes.
 */
final class SseFictionMentionCleanupTest extends TestCase
{
    private const CUSTOM_FOOTER = 'Exemplaire du bureau Orion — usage RP interne, ne pas reproduire.';

    private PDO $pdo;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('CREATE TABLE sse_document_prefabs (id INTEGER PRIMARY KEY, tenant_id INTEGER, code TEXT, footer TEXT NOT NULL, description TEXT)');
        $this->pdo->exec('CREATE TABLE sse_wanted_settings (tenant_id INTEGER PRIMARY KEY, settings_json TEXT)');
        $this->pdo->exec('CREATE TABLE sse_wanted_notices (id INTEGER PRIMARY KEY, tenant_id INTEGER, contact_label TEXT, authority TEXT)');
        $this->pdo->exec('CREATE TABLE sse_wanted_notice_meta (notice_id INTEGER PRIMARY KEY, tenant_id INTEGER, service_json TEXT)');

        $prefab = $this->pdo->prepare('INSERT INTO sse_document_prefabs (id, tenant_id, code, footer, description) VALUES (?, ?, ?, ?, ?)');
        $id = 0;
        foreach ([1, 2] as $tenant) {
            foreach (SseDocumentChromeCatalog::LEGACY_FOOTERS as $n => $legacy) {
                $prefab->execute([++$id, $tenant, 'livre_' . $n, $legacy, $n === 0 ? 'Présentation actuelle du dossier : bandeau rouge, feuille crème, mention RP.' : 'Description']);
            }
        }
        $prefab->execute([++$id, 1, 'custom_a', self::CUSTOM_FOOTER, 'Modèle de la communauté, ton RP assumé.']);
        $prefab->execute([++$id, 1, 'custom_b', '', null]);

        $settings = $this->pdo->prepare('INSERT INTO sse_wanted_settings (tenant_id, settings_json) VALUES (?, ?)');
        $settings->execute([1, json_encode(['authority_fr' => 'Agence interservices du renseignement — RP', 'authority_en' => 'Interservice Intelligence Agency — RP', 'contact_label' => 'Cellule de coordination des recherches', 'ref_format' => 'A-{N}/{Y}'], JSON_UNESCAPED_UNICODE)]);
        $settings->execute([2, json_encode(['authority_fr' => 'Bureau Orion — RP', 'authority_en' => 'Orion Desk', 'ref_format' => 'WN-{N}'], JSON_UNESCAPED_UNICODE)]);

        $notice = $this->pdo->prepare('INSERT INTO sse_wanted_notices (id, tenant_id, contact_label, authority) VALUES (?, ?, ?, ?)');
        $notice->execute([1, 1, 'Cellule de contre-terrorisme interarmées (fictive)', 'Cellule de contre-terrorisme interarmées (fictive)']);
        $notice->execute([2, 1, 'Cellule des opérations du Détroit (fictive)', 'Agence interservices du renseignement — RP']);
        $notice->execute([3, 2, 'JCTC — Joint Counter-Terrorism Cell (fictional)', null]);

        $meta = $this->pdo->prepare('INSERT INTO sse_wanted_notice_meta (notice_id, tenant_id, service_json) VALUES (?, ?, ?)');
        $meta->execute([1, 1, json_encode(['code' => 'SVC-CTC', 'fr' => 'Cellule de contre-terrorisme interarmées (fictive)', 'en' => 'Joint Counter-Terrorism Cell (fictional)', 'ac' => 'JCTC', 'mail' => 'veille@jctc.invalid/x'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
        $meta->execute([2, 1, json_encode(['code' => 'T1-DET', 'fr' => 'Cellule des opérations du Détroit (fictive)', 'en' => 'Strait Operations Cell (fictional)'], JSON_UNESCAPED_UNICODE)]);
        $meta->execute([3, 2, null]);
    }

    /** @return list<array<string, mixed>> */
    private function all(string $table): array
    {
        return $this->pdo->query('SELECT * FROM ' . $table . ' ORDER BY 1')->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<string, list<array<string, mixed>>> */
    private function snapshot(): array
    {
        $out = [];
        foreach (['sse_document_prefabs', 'sse_wanted_settings', 'sse_wanted_notices', 'sse_wanted_notice_meta'] as $table) {
            $out[$table] = $this->all($table);
        }

        return $out;
    }

    public function testSimulationListsEveryDeliveredMentionAndWritesNothing(): void
    {
        $before = $this->snapshot();
        $report = (new SseFictionMentionCleanup($this->pdo))->run();
        self::assertFalse($report['applied']);
        self::assertSame(0, $report['written']);
        // 10 pieds livrés et 2 descriptions ; 1 réglage ; 3 valeurs d'avis ; 1 instantané.
        self::assertSame(['sse_document_prefabs' => 12, 'sse_wanted_settings' => 1, 'sse_wanted_notices' => 3, 'sse_wanted_notice_meta' => 1], $report['by_table']);
        self::assertSame(17, $report['found']);
        self::assertCount(17, $report['changes']);
        self::assertSame($before, $this->snapshot(), 'la simulation n’écrit rien');
    }

    public function testApplyRemovesOnlyTheExactDeliveredMentions(): void
    {
        $report = (new SseFictionMentionCleanup($this->pdo))->run(true);
        self::assertTrue($report['applied']);
        self::assertSame(17, $report['written']);
        self::assertSame(0, $report['failed']);

        $prefabs = array_column($this->all('sse_document_prefabs'), null, 'id');
        foreach (range(1, 10) as $id) {
            self::assertSame('', $prefabs[$id]['footer'], 'pied livré vidé : modèle ' . $id);
        }
        self::assertSame('Présentation actuelle du dossier : bandeau rouge, feuille crème.', $prefabs[1]['description']);
        self::assertSame('Description', $prefabs[2]['description']);
        // Modèle de la communauté : pied et description intacts, même s'ils parlent de RP.
        self::assertSame(self::CUSTOM_FOOTER, $prefabs[11]['footer']);
        self::assertSame('Modèle de la communauté, ton RP assumé.', $prefabs[11]['description']);
        self::assertCount(12, $prefabs, 'aucune ligne supprimée');

        $settings = array_column($this->all('sse_wanted_settings'), 'settings_json', 'tenant_id');
        $one = json_decode((string) $settings[1], true);
        self::assertSame('Agence interservices du renseignement', $one['authority_fr']);
        self::assertSame('Interservice Intelligence Agency', $one['authority_en']);
        self::assertSame('A-{N}/{Y}', $one['ref_format'], 'les autres réglages restent');
        self::assertSame('Bureau Orion — RP', json_decode((string) $settings[2], true)['authority_fr'], 'autorité écrite par la communauté : intacte');

        $notices = array_column($this->all('sse_wanted_notices'), null, 'id');
        self::assertSame('Cellule de contre-terrorisme interarmées', $notices[1]['contact_label']);
        self::assertSame('Cellule de contre-terrorisme interarmées', $notices[1]['authority']);
        self::assertSame('Cellule des opérations du Détroit (fictive)', $notices[2]['contact_label'], 'service de la communauté : intact');
        self::assertSame('Agence interservices du renseignement', $notices[2]['authority']);
        self::assertSame('JCTC — Joint Counter-Terrorism Cell (fictional)', $notices[3]['contact_label'], 'texte retouché : intact');

        $meta = array_column($this->all('sse_wanted_notice_meta'), 'service_json', 'notice_id');
        $snap = json_decode((string) $meta[1], true);
        self::assertSame('Cellule de contre-terrorisme interarmées', $snap['fr']);
        self::assertSame('Joint Counter-Terrorism Cell', $snap['en']);
        self::assertSame('veille@jctc.invalid/x', $snap['mail']);
        self::assertStringContainsString('invalid/x', (string) $meta[1], 'barres obliques non échappées, comme à l’enregistrement');
        self::assertSame('Cellule des opérations du Détroit (fictive)', json_decode((string) $meta[2], true)['fr'], 'service de la communauté : intact');
    }

    public function testRunningTwiceChangesNothingMore(): void
    {
        $cleanup = new SseFictionMentionCleanup($this->pdo);
        $cleanup->run(true);
        $after = $this->snapshot();
        $again = $cleanup->run(true);
        self::assertSame(0, $again['found']);
        self::assertSame(0, $again['written']);
        self::assertSame($after, $this->snapshot());
        self::assertSame(0, $cleanup->run()['found'], 'la simulation ne trouve plus rien');
    }

    public function testATenantFilterLeavesTheOtherCommunitiesAlone(): void
    {
        $before = $this->snapshot();
        $report = (new SseFictionMentionCleanup($this->pdo))->run(true, 2);
        // Communauté 2 : ses cinq pieds livrés et la description livrée du premier modèle.
        self::assertSame(['sse_document_prefabs' => 6], $report['by_table']);
        foreach (['sse_wanted_settings', 'sse_wanted_notices', 'sse_wanted_notice_meta'] as $table) {
            self::assertSame($before[$table], $this->all($table), $table);
        }
        foreach ($this->all('sse_document_prefabs') as $row) {
            if ((int) $row['tenant_id'] === 1 && (int) $row['id'] <= 5) {
                self::assertNotSame('', $row['footer'], 'communauté 1 non traitée');
            }
            if ((int) $row['tenant_id'] === 2) {
                self::assertSame('', $row['footer']);
            }
        }
    }

    public function testMissingTablesAreIgnored(): void
    {
        $this->pdo->exec('DROP TABLE sse_wanted_notice_meta');
        $this->pdo->exec('DROP TABLE sse_document_prefabs');
        $report = (new SseFictionMentionCleanup($this->pdo))->run(true);
        self::assertSame(0, $report['failed']);
        self::assertSame(['sse_wanted_settings' => 1, 'sse_wanted_notices' => 3], $report['by_table']);
    }

    public function testTheToolIsADryRunByDefaultAndCliOnly(): void
    {
        $tool = (string) file_get_contents(dirname(__DIR__, 2) . '/tools/sse-strip-fiction-mentions.php');
        self::assertStringContainsString("PHP_SAPI !== 'cli'", $tool);
        self::assertStringContainsString("\$apply = isset(\$opts['apply']);", $tool);
        self::assertStringContainsString('->run($apply, $tenant)', $tool);
        self::assertStringContainsString('SIMULATION (aucune écriture)', $tool);
    }
}
