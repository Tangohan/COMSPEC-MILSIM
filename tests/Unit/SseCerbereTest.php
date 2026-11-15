<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Sse\SseAutomationService;
use App\Services\Sse\SseCerbereReport;
use App\Services\Sse\SseProvenance;
use App\Support\SseAccessCatalog;
use App\Support\SseCerbere;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\SseLinksFixture;
use Tests\Support\SseLinksSqlite;

/**
 * « Cerbères » : le nom de ce que le système fait tout seul dans le bureau SSE. Table de correspondance codes stables → noms
 * affichés, acteur « Cerbère » des liens de provenance, renommage d'AFFICHAGE seulement (aucun code, route, colonne ni droit ne
 * change) et page de lecture seule qui liste les traitements, leur état et leurs derniers événements.
 */
final class SseCerbereTest extends TestCase
{
    private static function read(string $relative): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/' . $relative);
    }

    public function testFiveTreatmentsAreNamedCerbereDashTheirJob(): void
    {
        self::assertSame(['liaison', 'validation', 'correlation', 'detection', 'creation'], array_keys(SseCerbere::TREATMENTS));
        self::assertSame('Cerbère-Liaison', SseCerbere::TREATMENTS['liaison']['label']);
        self::assertSame('Cerbère-Validation', SseCerbere::TREATMENTS['validation']['label']);
        self::assertSame('Cerbère-Corrélation', SseCerbere::TREATMENTS['correlation']['label']);
        self::assertSame('Cerbère-Détection', SseCerbere::TREATMENTS['detection']['label']);
        self::assertSame('Cerbère-Création', SseCerbere::TREATMENTS['creation']['label']);
        foreach (SseCerbere::TREATMENTS as $t) {
            self::assertStringStartsWith('Cerbère-', $t['label']);
            self::assertNotSame('', $t['does']);
            self::assertStringEndsWith('.', $t['does'], 'une phrase');
        }
    }

    public function testEveryRuleHasAStablePrefixedCodeAnExistingTreatmentAndAReason(): void
    {
        foreach (SseCerbere::RULES as $code => $rule) {
            self::assertMatchesRegularExpression('/^cerbere\.(link|create|correlate|validate|detect|backfill)\.[a-z_]+$/', $code);
            self::assertArrayHasKey($rule['treatment'], SseCerbere::TREATMENTS, $code);
            self::assertNotSame('', $rule['label']);
            self::assertStringEndsWith('.', $rule['reason'], $code);
            self::assertSame($rule['treatment'], SseCerbere::treatmentOf($code));
            self::assertContains($code, SseCerbere::rulesOf($rule['treatment']));
        }
        // Les deux exemples de la demande : un code de règle stable par traitement.
        self::assertArrayHasKey('cerbere.link.person_identity', SseCerbere::RULES);
        self::assertSame('validation', SseCerbere::treatmentOf('cerbere.validate.engine_suggestion'));
    }

    public function testTheReadableActorAndSentenceNameTheTreatmentAndTheRule(): void
    {
        self::assertSame('Cerbère-Validation (règle cerbere.validate.engine_suggestion)', SseCerbere::actorLabel('cerbere.validate.engine_suggestion'));
        self::assertSame('Validé automatiquement par Cerbère-Validation (règle cerbere.validate.engine_suggestion)', SseCerbere::sentence('cerbere.validate.engine_suggestion', 'Validé'));
        self::assertSame('Cerbère', SseCerbere::treatmentLabel('cerbere.inconnue'), 'règle inconnue : le nom générique');
        self::assertSame('Cerbère', SseCerbere::treatmentLabel(null));
        self::assertTrue(SseCerbere::isCerbereRule('cerbere.link.check_biokey'));
        self::assertFalse(SseCerbere::isCerbereRule(SseCerbere::RULE_MANUAL), 'un lien manuel n’est jamais un Cerbère');
        self::assertFalse(SseCerbere::isCerbereRule(null));
    }

    public function testLegacyLabelsAreMappedAndNoLongerShownWhereTheyWereRenamed(): void
    {
        self::assertSame('Propositions de Cerbère-Validation', SseCerbere::LEGACY_LABELS['Suggestions du moteur']);
        self::assertSame('Cerbère', SseCerbere::LEGACY_LABELS['Automatisme']);
        $renamed = [
            'app/Support/SsePortalNav.php' => ['Suggestions du moteur', "'label' => 'Croisements'"],
            'views/atak/sse/suggestions.php' => ['Rapprochements moteur', 'Un passage moteur'],
            'views/atak/sse/cross.php' => ['<h1>Croisements</h1>'],
            'views/atak/sse/case_correlations.php' => ['Liens automatiques'],
            'views/atak/sse/partials/case_engine.php' => ['Moteur — propositions', 'Le moteur propose'],
            'app/Controllers/Web/SsePortalController.php' => ["'title' => 'Rapprochements moteur'", 'Passage moteur terminé'],
            'app/Support/SseCaseSectionHelp.php' => ['Moteur — propositions'],
        ];
        foreach ($renamed as $file => $old) {
            $src = self::read($file);
            foreach ($old as $label) {
                self::assertStringNotContainsString($label, $src, $file . ' : « ' . $label . ' » est devenu Cerbère');
            }
        }
        self::assertStringContainsString("'label' => 'Propositions de Cerbère'", self::read('app/Support/SsePortalNav.php'));
        self::assertStringContainsString("'label' => 'Cerbères'", self::read('app/Support/SsePortalNav.php'));
    }

    public function testTheRenamingIsDisplayOnlyAndEveryStableCodeIsUnchanged(): void
    {
        // Adresses, droits et codes stockés d'avant.
        $routes = self::read('routes/web.php');
        foreach (['/atak/sse/rapprochements', '/atak/sse/croisements', '/atak/sse/moteur/executer', '/atak/sse/validation'] as $route) {
            self::assertStringContainsString("'" . $route, $routes, $route);
        }
        self::assertSame('Automatisme', SseAutomationService::RULE_AUTHOR, 'valeur stockée comme auteur des relations : inchangée');
        self::assertStringContainsString("'SSE_AUTO'", self::read('app/Services/Sse/SseAutomationService.php'));
        self::assertStringContainsString("'rule_key'", self::read('app/Services/Sse/SseAnalyticalEngineService.php'));
        self::assertSame('analyse', SseAccessCatalog::sectionForPath('atak/sse/cerberes'), 'la page Cerbères suit la section Analyse : mêmes droits que les croisements');
        self::assertSame('analyse', SseAccessCatalog::sectionForPath('atak/sse/rapprochements'));
        // Les noms n'écrasent aucune clé de menu : l'identifiant technique « rapprochements » est le même.
        self::assertStringContainsString("['id' => 'rapprochements', 'label' => 'Propositions de Cerbère'", self::read('app/Support/SsePortalNav.php'));
    }

    public function testThePageListsTheTreatmentsTheirStateAndTheirLatestEvents(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        $pdo = SseLinksSqlite::open();
        try {
            $ids = SseLinksFixture::seed($pdo);
            $pdo->exec('DELETE FROM sse_entity_links');
            SseProvenance::cerbere(1, ['check', 5], ['identity', $ids['identity']], 'identified_as', 'cerbere.link.check_biokey');
            SseProvenance::cerbere(1, ['check', 6], ['identity', $ids['identity']], 'identified_as', 'cerbere.link.check_document');
            SseProvenance::cerbere(1, ['person', $ids['person']], ['case', $ids['case']], 'filed_in', 'cerbere.create.case_daily');
            SseProvenance::manual(1, ['person', $ids['person']], ['site', $ids['site']], 'related', 'Vu ensemble au port.', 7);
            SseProvenance::cerbere(2, ['check', 5], ['identity', 9], 'identified_as', 'cerbere.link.check_biokey');
            $report = (new SseCerbereReport())->build(1);
            self::assertTrue($report['provenance']);
            $byKey = array_column($report['treatments'], null, 'key');
            self::assertCount(5, $byKey);
            self::assertSame(2, $byKey['liaison']['total'], 'les liens de l’autre communauté et le lien manuel ne comptent pas');
            self::assertSame(1, $byKey['creation']['total']);
            self::assertSame(0, $byKey['detection']['total']);
            self::assertTrue($byKey['liaison']['active']);
            self::assertTrue($byKey['correlation']['active']);
            self::assertFalse($byKey['validation']['active'], 'la file des propositions n’existe pas dans cette base : dit « à activer »');
            self::assertNotSame('', $byKey['validation']['why']);
            self::assertCount(3, $report['recent'], 'seulement les traitements, jamais un lien manuel');
            self::assertSame('Cerbère-Création', $report['recent'][0]['treatment']);
            foreach ($report['recent'] as $e) {
                self::assertStringStartsWith('cerbere.', $e['rule']);
            }
        } finally {
            SseLinksSqlite::close();
        }
    }

    public function testWithoutTheTableThePageSaysThatTheExplanationIsNotYetActive(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        SseLinksSqlite::open(false);
        try {
            $report = (new SseCerbereReport())->build(1);
            self::assertFalse($report['provenance']);
            self::assertSame([], $report['recent']);
            self::assertCount(5, $report['treatments']);
        } finally {
            SseLinksSqlite::close();
        }
        $view = self::read('views/atak/sse/cerberes.php');
        self::assertStringContainsString('n’est pas encore activée', $view);
        self::assertStringNotContainsString('<form', $view, 'lecture seule : aucun formulaire, aucune écriture');
        self::assertStringNotContainsString('method="post"', $view);
    }
}
