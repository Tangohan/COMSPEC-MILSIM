<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\OrganizationCatalog\RealUnitKitDefinitions;
use App\Services\Personnel\MemberQualificationSuggester as Sug;
use App\Services\Personnel\MemberQualificationSuggestionService;
use App\Services\Personnel\QualificationAwardRuleService;
use App\Support\QualificationSuggestion\UnitNatureDetector;
use PHPUnit\Framework\TestCase;
use Tests\Support\MysqlDdlSqlitePdo;

/**
 * Qualifications et brevets suggérés dans la fiche membre : fonction (catalogue d’emplois), unité de l’ORBAT et sa
 * chaîne (nature, unité réelle), grade, qualifications détenues et prérequis, suggestions ignorées, câblage.
 */
final class MemberQualificationSuggestionTest extends TestCase
{
    private const ROOT = __DIR__ . '/../..';

    /** @return list<array{text: string, weight: float}> unité « 24 STS » saisie à la main, reconnue dans l’arbre des unités réelles */
    private static function stsTexts(): array
    {
        $texts = [['text' => '24 STS', 'weight' => 1.0], ['text' => 'Détachement air', 'weight' => 0.6]];
        $node = (string) RealUnitKitDefinitions::findNode('24-sts', '24 STS');
        foreach (RealUnitKitDefinitions::lineage($node) as $i => $line) {
            $texts[] = ['text' => $line, 'weight' => UnitNatureDetector::weight($i)];
        }

        return $texts;
    }

    /** @param array<string, mixed> $in @return array<string, mixed> */
    private static function suggestFor(array $in, ?callable $evaluate = null, ?callable $measure = null): array
    {
        return Sug::suggest($in + ['lang' => 'fr'], $evaluate ?? static fn (int $id): array => ['state' => 'ready', 'missing' => [], 'allow_manual' => true, 'course_keys' => []], $measure ?? static fn (array $c): ?float => 0.0);
    }

    /** @param array<string, mixed> $r @return array<string, array<string, mixed>> */
    private static function byKey(array $r): array
    {
        return array_column($r['items'], null, 'key');
    }

    public function testRealUnitIsFoundByNameAndGivesItsLineageAndCountry(): void
    {
        self::assertSame('us-24sts', RealUnitKitDefinitions::findNode('', '24th STS'));
        self::assertSame('us-24sts', RealUnitKitDefinitions::findNode('mon-unite', '24 STS'));
        self::assertSame('us-24sts', RealUnitKitDefinitions::findNode('us-24sts', 'Escadron'));
        self::assertNull(RealUnitKitDefinitions::findNode('alpha', 'Section Alpha'));
        $lineage = implode(' | ', RealUnitKitDefinitions::lineage('us-24sts'));
        self::assertStringContainsString('724th Special Tactics Group', $lineage);
        self::assertStringContainsString('Air Force Special Operations Command', $lineage);
        self::assertSame('US', RealUnitKitDefinitions::countryOf('us-24sts'));

        $natures = UnitNatureDetector::detect(self::stsTexts());
        self::assertSame('special_tactics', array_key_first($natures));
        self::assertSame(1.0, $natures['special_tactics']);
        self::assertArrayHasKey('sof', $natures);
        self::assertArrayHasKey('engineer', UnitNatureDetector::detect([['text' => '2e Régiment étranger de génie', 'weight' => 1.0]]));
        self::assertSame([], UnitNatureDetector::detect([['text' => 'Section Alpha', 'weight' => 1.0]]));
    }

    public function testSofTacpAt24thStsGetsJtacAndSpecialTacticsQualifications(): void
    {
        $r = self::suggestFor(['jobs' => [['role_id' => 3, 'name' => 'SOF TACP']], 'unit_texts' => self::stsTexts(), 'grade_nato' => 'OR-5']);
        $items = self::byKey($r);
        self::assertSame('US', $r['country'], 'pays déduit de l’unité réelle (Special Tactics)');
        self::assertSame('TACP', $r['jobs'][0]['label'], 'emploi TACP reconnu dans « SOF TACP »');
        foreach (['q:us-jtac', 'q:us-af-tacp-icc', 'q:us-af-tacp-apprentice', 'q:us-af-zulu', 'q:us-basic-airborne'] as $key) {
            self::assertArrayHasKey($key, $items, $key . ' proposé');
        }
        $reasons = static fn (string $key): string => implode(' ; ', array_map(static fn (array $x): string => $x['text'] . ' ' . $x['subject'], $items[$key]['reasons']));
        self::assertStringContainsString('Recommandée pour l’emploi TACP', $reasons('q:us-af-tacp-icc'));
        self::assertStringContainsString('Courante dans les unités Special Tactics', $reasons('q:us-jtac'));
        self::assertStringContainsString('Prérequis de', $reasons('q:us-af-tacp-apprentice'));
        foreach ($r['items'] as $it) {
            self::assertContains($it['country'], ['US', 'NATO'], $it['key'] . ' : catalogue du pays de l’unité');
            self::assertSame(Sug::STATE_NOT_IMPORTED, $it['state']);
        }
        // Classement par pertinence.
        $points = array_column($r['items'], 'points');
        $sorted = $points;
        rsort($sorted);
        self::assertSame($sorted, $points);
        self::assertLessThanOrEqual(Sug::MAX, count($r['items']));
    }

    public function testEngineerUnitGetsEngineeringQualifications(): void
    {
        $r = self::suggestFor([
            'unit_texts' => [['text' => 'Section génie', 'weight' => 1.0], ['text' => '2e Régiment étranger de génie', 'weight' => 0.6]],
            'definitions' => [5 => ['id' => 5, 'code' => 'BSC', 'name' => 'Brevet de sapeur de combat'], 6 => ['id' => 6, 'code' => 'PIL', 'name' => 'Pilote d’hélicoptère']],
        ]);
        self::assertSame(['engineer'], array_keys($r['natures']));
        $items = self::byKey($r);
        self::assertArrayHasKey('d:5', $items, 'fiche de la communauté qui se rapporte au génie');
        self::assertArrayNotHasKey('d:6', $items);
        self::assertSame(Sug::STATE_READY, $items['d:5']['state']);
        foreach ($r['items'] as $it) {
            if ($it['key'] !== 'd:5') {
                self::assertSame('NRBC, EOD et lutte contre les engins explosifs', $it['category'], $it['key']);
            }
        }
        // Pays inconnu : standards OTAN parmi les propositions.
        self::assertContains('NATO', array_column($r['items'], 'country'));

        $fr = self::suggestFor(['unit_texts' => [['text' => 'Compagnie du génie', 'weight' => 1.0]], 'country' => 'FR']);
        self::assertArrayHasKey('q:fr-nedex-air', self::byKey($fr));
        foreach ($fr['items'] as $it) {
            self::assertSame('FR', $it['country']);
        }
    }

    public function testHeldQualificationIsNotSuggestedAgainAndMissingPrerequisitesAreShown(): void
    {
        $in = ['jobs' => [['role_id' => 3, 'name' => 'SOF TACP']], 'unit_texts' => self::stsTexts(),
            'definitions' => [10 => ['id' => 10, 'code' => 'US-JTAC', 'name' => 'JTAC'], 11 => ['id' => 11, 'code' => 'US-AF-TACPICC', 'name' => 'TACP ICC'], 12 => ['id' => 12, 'code' => 'US-AF-ZULU', 'name' => 'Zulu']],
            'held' => [10 => 'held', 12 => 'pending']];
        $evaluate = static fn (int $id): array => $id === 11
            ? ['state' => 'missing', 'missing' => [['text' => 'Il manque', 'subject' => '', 'criterion' => ['type' => 'metric', 'metric' => 'halo_jumps', 'scope' => '', 'min' => 5.0, 'window_days' => 0]]], 'allow_manual' => true, 'course_keys' => []]
            : ['state' => 'ready', 'missing' => [], 'allow_manual' => true, 'course_keys' => []];
        $r = self::suggestFor($in, $evaluate, static fn (array $c): ?float => ($c['metric'] ?? '') === 'halo_jumps' ? 2.0 : null);
        $items = self::byKey($r);
        self::assertArrayNotHasKey('q:us-jtac', $items, 'JTAC déjà détenue : pas reproposée');
        self::assertGreaterThanOrEqual(1, $r['held']);
        self::assertSame(11, $items['q:us-af-tacp-icc']['definition_id']);
        self::assertSame(Sug::STATE_MISSING, $items['q:us-af-tacp-icc']['state']);
        self::assertSame(5.0, $items['q:us-af-tacp-icc']['missing'][0]['criterion']['min']);
        self::assertSame(Sug::STATE_PENDING, $items['q:us-af-zulu']['state'], 'en cours de formation');
        // Pas encore importée : prérequis du catalogue comparés au membre (2 sauts sur 5 : il en manque 3).
        $airborne = $items['q:us-basic-airborne'];
        self::assertSame(Sug::STATE_NOT_IMPORTED, $airborne['state']);
        self::assertSame('Il manque', $airborne['missing'][0]['text']);
        self::assertSame(3.0, $airborne['missing'][0]['criterion']['min']);
        // Carte : prérequis avec leur état, valeur du membre et source ; domaine, émetteur, drapeau.
        $jumps = array_values(array_filter($airborne['checks'], static fn (array $c): bool => $c['source'] === 'game'))[0];
        self::assertFalse($jumps['ok']);
        self::assertSame([2.0, 5.0], [$jumps['have'], $jumps['need']]);
        self::assertSame('airborne', $airborne['domain']);
        self::assertSame('US', $airborne['country']);
        self::assertNotSame('', $airborne['issuer']);
        // Qualification exigée non détenue citée comme manquante.
        $icc = Sug::catalogPrerequisiteRefs(\App\Support\QualificationCatalog\QualificationCatalog::findRaw('us-af-tacp-icc') ?? []);
        self::assertContains('us-af-tacp-apprentice', $icc);
    }

    public function testJobRequirementAcademyCourseGradeAndDismissal(): void
    {
        $in = ['jobs' => [['role_id' => 7, 'name' => 'Chef de groupe']], 'job_requirements' => [7 => [20]],
            'definitions' => [20 => ['id' => 20, 'code' => 'CDG', 'name' => 'Brevet de chef de groupe']],
            'courses' => ['course-abc' => 'Chef de groupe — module 1'], 'enrolled' => ['course-abc' => 'enrolled']];
        $evaluate = static fn (int $id): array => ['state' => 'ready', 'missing' => [], 'allow_manual' => true, 'course_keys' => ['course-abc']];
        $items = self::byKey(self::suggestFor($in, $evaluate));
        self::assertSame('Exigée par la fonction', $items['d:20']['reasons'][0]['text']);
        self::assertSame('Chef de groupe', $items['d:20']['reasons'][0]['subject']);
        self::assertSame(Sug::STATE_ACADEMY, $items['d:20']['state'], 'inscrit au cours Academy qui y mène');
        self::assertSame('course-abc', $items['d:20']['course']['key']);

        $r = self::suggestFor($in + ['dismissed' => ['d:20' => true]], $evaluate);
        self::assertArrayNotHasKey('d:20', self::byKey($r));
        self::assertSame('d:20', $r['dismissed'][0]['key']);

        self::assertLessThan(Sug::natoRank('OR-6'), Sug::natoRank('OR-4'));
        self::assertGreaterThan(Sug::natoRank('OR-9'), Sug::natoRank('OF-1'));
        self::assertSame(0, Sug::natoRank(''));
    }

    public function testDismissalsAreStoredPerMemberAndTheMigrationIsIdempotent(): void
    {
        $pdo = new MysqlDdlSqlitePdo();
        $run = static function () use ($pdo): string {
            ob_start();
            require self::ROOT . '/database/steps/3895_member_qualification_suggestion_dismissals.php';

            return (string) ob_get_clean();
        };
        self::assertStringContainsString('créée', $run());
        self::assertStringContainsString('déjà présente', $run());
        $service = new MemberQualificationSuggestionService($this->createMock(QualificationAwardRuleService::class), $pdo);
        self::assertTrue($service->dismissReady());
        self::assertTrue($service->dismiss(1, 5, 'q:us-jtac', 9));
        self::assertTrue($service->dismiss(1, 5, 'q:us-jtac', 9), 'relance sans doublon');
        self::assertFalse($service->dismiss(1, 5, 'q:<script>', 9));
        self::assertSame(['q:us-jtac' => true], $service->dismissedKeys(1, 5));
        self::assertSame([], $service->dismissedKeys(2, 5), 'propre à la communauté');
        self::assertTrue($service->dismiss(1, 5, 'q:us-jtac', 9, true));
        self::assertSame([], $service->dismissedKeys(1, 5));
    }

    public function testRoutesContainerAndMigrationAreWired(): void
    {
        $routes = (string) file_get_contents(self::ROOT . '/routes/web.php');
        self::assertStringContainsString("\$router->post('/back-office/ressources/effectifs/membres/{id}/qualifications-suggerees', [\\App\\Controllers\\Admin\\MemberQualificationSuggestionsController::class, 'dismiss']", $routes);
        $container = (string) file_get_contents(self::ROOT . '/app/Core/Container.php');
        self::assertStringContainsString('\App\Services\Personnel\MemberQualificationSuggestionService::class => new', $container);
        $registry = require self::ROOT . '/database/steps/registry.php';
        $ids = array_column($registry, 'file', 'id');
        self::assertSame('3895_member_qualification_suggestion_dismissals.php', $ids['member_qualification_suggestion_dismissals'] ?? null);
        self::assertSame(1, count(array_filter(array_column($registry, 'file'), static fn (string $f): bool => str_starts_with($f, '3895_'))));
        $controller = (string) file_get_contents(self::ROOT . '/app/Controllers/Admin/MemberQualificationSuggestionsController.php');
        self::assertStringContainsString("Csrf::validate((string) \$request->input('_csrf_token', ''))", $controller);
        self::assertStringContainsString('isMemberOfTenant($userId, $tenantId)', $controller);
    }

    public function testMemberSheetAndMySituationShowTheSuggestions(): void
    {
        $member = (string) file_get_contents(self::ROOT . '/views/admin/effectifs_workspace/member.php');
        self::assertStringContainsString("views/partials/qualification_suggestions.php", $member);
        $partial = (string) file_get_contents(self::ROOT . '/views/partials/qualification_suggestions.php');
        foreach (['id="qualifs-suggerees"', 'data-qsg-bar', '<dialog class="effk-md qsg-md" id="qsg-md"', 'href="#qsg-md" data-qsg-open', 'data-qsg-hide', 'data-qsg-filter="ready"', 'data-qsg-filter="notimp"', 'Comment sont calculées ces suggestions ?', 'data-qsg-action', 'qualification-suggestions.js', 'CatalogCountry::flag(', 'Visual::icon('] as $needle) {
            self::assertStringContainsString($needle, $partial, $needle);
        }
        self::assertStringNotContainsString('<h4 class="eff-fm-sub" id="qualifs-suggerees">', $member, 'plus de gros bloc : info-barre');
        $js = (string) file_get_contents(self::ROOT . '/public/assets/js/qualification-suggestions.js');
        foreach (['showModal()', 'sessionStorage', "Accept: 'application/json", 'new DOMParser()', 'data-qsg-body'] as $needle) {
            self::assertStringContainsString($needle, $js, $needle);
        }
        $ctrl = (string) file_get_contents(self::ROOT . '/app/Controllers/Admin/EffectifsWorkspaceController.php');
        self::assertStringContainsString("'qualificationSuggestions' => \$this->memberQualificationSuggestions(\$tenantId, \$id)", $ctrl);
        self::assertStringContainsString("->forMember(\$tenantId, \$userId, false,", $ctrl);
        $situation = (string) file_get_contents(self::ROOT . '/app/Controllers/Admin/Organization/MemberSituationController.php');
        self::assertStringContainsString("->forMember(\$tenantId, \$userId, true,", $situation, 'lecture par le membre : unités masquées ignorées');
        self::assertStringContainsString("'qualificationSuggestionRights' => []", $situation, 'aucune action dans Ma situation');
        self::assertStringContainsString('qualification_suggestions.php', (string) file_get_contents(self::ROOT . '/views/admin/member_situation/qualifications.php'));
    }

    public function testQuickActionsReuseExistingFormsAndComeBackToTheMemberSheet(): void
    {
        $view = (string) file_get_contents(self::ROOT . '/views/partials/qualification_suggestions.php');
        foreach (["url('back-office/referentiels/qualifications/catalogue/importer')", 'name="return_member"', "url('academy/action/assign')", 'name="return" value="membre"',
            "url('back-office/referentiels/qualifications/matrice/case')", 'name="return_to" value="member"', '\App\Core\Csrf::field()'] as $needle) {
            self::assertStringContainsString($needle, $view);
        }
        self::assertStringContainsString("\$request->input('return_member', 0)", (string) file_get_contents(self::ROOT . '/app/Controllers/Admin/Organization/QualificationReferentielController.php'));
        $academy = (string) file_get_contents(self::ROOT . '/app/Controllers/Web/AcademyController.php');
        self::assertStringContainsString("\$request->inputString('return') === 'membre'", $academy);
        // L’inscription garde ses contrôles : droit « inscrire » et public du cours.
        self::assertMatchesRegularExpression("/if \\(!\\\$rights->can\\('assign'\\)\\).*\\\$fromMember/s", $academy);
        $en = (string) file_get_contents(self::ROOT . '/lang/en/site_auto.php');
        foreach (['Qualifications et brevets suggérés', 'Recommandée pour l’emploi', 'Courante dans les', 'Prérequis de', 'Pas encore dans le référentiel', 'Ignorer pour ce membre', 'unités Special Tactics', 'unités du génie'] as $fr) {
            self::assertStringContainsString("'" . $fr . "' =>", $en, $fr);
        }
    }

    public function testDismissAnswersJsonToTheModalAndKeepsItsChecks(): void
    {
        $controller = (string) file_get_contents(self::ROOT . '/app/Controllers/Admin/MemberQualificationSuggestionsController.php');
        self::assertStringContainsString("str_contains(strtolower((string) \$request->header('Accept')), 'application/json')", $controller);
        self::assertStringContainsString("Response::json(['ok' => \$ok, 'message' => \$message], \$ok ? 200 : 422)", $controller);
        $csrf = strpos($controller, 'Csrf::validate');
        $rights = strpos($controller, 'self::canDismiss(');
        $member = strpos($controller, 'isMemberOfTenant(');
        $write = strpos($controller, '->dismiss($tenantId');
        self::assertTrue($csrf < $rights && $rights < $member && $member < $write, 'CSRF, droits et membre vérifiés avant l’écriture');
    }

    public function testVisualDomainAndIcon(): void
    {
        $V = \App\Support\QualificationCatalog\QualificationVisual::class;
        self::assertSame('airborne', $V::domain('Brevet de parachutiste'));
        self::assertSame('medical', $V::domain('x', 'medical'));
        self::assertSame('brevet', $V::kind('Brevet de parachutiste'));
        self::assertStringContainsString('aria-hidden="true"', $V::icon('aviation'));
    }
}
