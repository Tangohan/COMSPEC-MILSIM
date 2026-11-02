<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Response;
use App\Services\Academy\AcademyCurriculum;
use App\Services\Academy\AcademyOffer;
use PHPUnit\Framework\TestCase;

/** Catalogue et tableau de bord Academy : tout ce qu'on peut obtenir, avec l'état du membre. */
final class AcademyOfferTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = ['_csrf_token' => str_repeat('a', 64), 'user_id' => 7];
        $_SERVER['REQUEST_URI'] = '/academy/catalogue';
        $GLOBALS['__app_locale'] = 'fr';
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['__app_locale']);
    }

    /** @return array<string, mixed> */
    private function source(bool $staff = false): array
    {
        $course = AcademyCurriculum::demo();
        $course['built_in'] = true;
        $course['license'] = ['fr' => 'Licence interne', 'en' => 'Internal licence'];
        $rule = static fn (array $items): string => (string) json_encode(['op' => 'all', 'groups' => [['op' => 'all', 'items' => $items]]]);

        return [
            'tenant_name' => 'Task Force',
            'staff' => $staff,
            'academy_courses' => [$course],
            'states' => [$course['key'] => ['chapter' => 0, 'stage' => 4, 'snapshot' => $course], '__notes' => ['text' => 'x']],
            'legacy_courses' => [
                ['id' => 11, 'slug' => 'secourisme', 'title' => 'Secourisme', 'short_description' => '<b>Garrot</b>', 'estimated_minutes' => 90, 'category' => 'Santé', 'level' => 'initiation', 'lms_scope' => 'tenant', 'tenant_id' => 3, 'enrollment' => ['status' => 'completed']],
                ['id' => 12, 'slug' => 'navigation', 'title' => 'Navigation', 'lms_scope' => 'platform', 'tenant_id' => null],
            ],
            'legacy_enrollments' => [],
            'definitions' => [
                1 => ['id' => 1, 'name' => 'Brevet parachutiste', 'type_name' => 'Brevet', 'is_permanent' => 1, 'default_issuer_id' => 5, 'category_name' => 'Aéroporté'],
                2 => ['id' => 2, 'name' => 'Pilote MH-6', 'type_name' => 'Qualification', 'default_validity_months' => 6, 'category_name' => 'Aviation'],
                3 => ['id' => 3, 'name' => 'Qualification interne', 'type_name' => 'Qualification'],
                4 => ['id' => 4, 'name' => 'Chef de groupe', 'type_name' => 'Qualification'],
            ],
            'settings' => [
                2 => ['allow_manual' => 1, 'auto_enabled' => 1, 'rule_json' => $rule([['type' => 'metric', 'metric' => 'flight_hours', 'min' => 20], ['type' => 'academy', 'course_id' => 11]]), 'show_on_profile' => 1],
                3 => ['allow_manual' => 1, 'auto_enabled' => 0, 'show_on_profile' => 0],
            ],
            'pilot' => [],
            'prereqs' => [4 => [1]],
            'issuers' => [['id' => 5, 'name' => 'Centre d’instruction'], ['id' => 6, 'name' => 'Ancien centre', 'archived_at' => '2026-01-01']],
            'evaluations' => [
                1 => ['state' => 'held', 'pct' => 0, 'temporal' => 'valid', 'award' => ['id' => 91, 'certificate_document_path' => 'b.pdf', 'obtained_at' => '2026-03-01'], 'prereq' => ['ok' => true, 'missing' => []], 'eval' => null],
                2 => ['state' => 'progress', 'pct' => 40, 'temporal' => '', 'award' => null, 'prereq' => ['ok' => true, 'missing' => []], 'eval' => ['groups' => [['items' => [['met' => false, 'current' => 8.0, 'target' => 20.0, 'pct' => 40], ['met' => true, 'current' => 1.0, 'target' => 1.0, 'pct' => 100]]]]]],
                4 => ['state' => 'progress', 'pct' => 0, 'temporal' => '', 'award' => null, 'prereq' => ['ok' => false, 'missing' => ['Grade minimum « Sergent »']], 'eval' => null],
            ],
            'names' => ['courses' => [11 => 'Secourisme'], 'definitions' => [], 'scopes' => []],
            'pathways' => [['id' => 9, 'name' => 'Devenir pilote', 'steps' => [['title' => 'Secourisme', 'ref_type' => 'training_course', 'ref' => '11'], ['title' => 'Pilote MH-6', 'ref_type' => 'qualification', 'ref' => '2']]]],
            'competencies' => [['id' => 3, 'label' => 'Appui aérien', 'palier' => 'Spécialiste', 'links' => []]],
            'catalog' => [],
        ];
    }

    /** @param list<array<string, mixed>> $items */
    private function item(array $items, string $id): array
    {
        $it = AcademyOffer::find($items, $id);
        self::assertNotNull($it, $id);

        return $it;
    }

    public function testAssemblesEverythingAMemberCanObtainWithTheirState(): void
    {
        $offer = AcademyOffer::assemble($this->source());
        $items = $offer['items'];

        $jtac = $this->item($items, 'a-' . AcademyCurriculum::demo()['key']);
        self::assertSame(['formations', 'brevets'], $jtac['tabs'], 'une formation à licence est aussi proposée parmi les brevets');
        self::assertSame('progress', $jtac['state']);
        self::assertSame('assessment', $jtac['next']['stage']);
        self::assertSame('athena', $jtac['source']);

        $first = $this->item($items, 'n-11');
        self::assertSame('held', $first['state']);
        self::assertSame('Garrot', $first['summary']);
        self::assertSame('community', $first['source']);
        self::assertSame('athena', $this->item($items, 'n-12')['source']);

        $para = $this->item($items, 'q-1');
        self::assertSame(['brevets', 'qualifications'], $para['tabs']);
        self::assertSame('held', $para['state']);
        self::assertSame('Centre d’instruction', $para['issuer']);
        self::assertSame(['permanent'], $para['validity']);

        $pilot = $this->item($items, 'q-2');
        self::assertSame(['manual', 'academy', 'auto'], $pilot['modes']);
        self::assertSame(['metric', 'course', 'manual'], array_column($pilot['how'], 'type'));
        self::assertTrue($pilot['how'][1]['met']);
        self::assertStringEndsWith('academy/formations/secourisme', $pilot['how'][1]['url']);
        self::assertSame(40, $pilot['pct']);
        self::assertSame(['months' => 6], $pilot['validity']);

        $chef = $this->item($items, 'q-4');
        self::assertSame('missing', $chef['state']);
        self::assertSame(['Grade minimum « Sergent »'], $chef['missing']);
        self::assertSame('q-1', $chef['prereqs'][0]['detail']);

        $path = $this->item($items, 'p-9');
        self::assertSame(50, $path['pct'], 'une étape sur deux est acquise (formation terminée)');
        self::assertTrue($path['steps'][0]['done']);

        self::assertNull(AcademyOffer::find($items, 'e-6'), 'un émetteur archivé n’est pas proposé');
        self::assertSame(1, $this->item($items, 'e-5')['count']);
        self::assertSame(['tout' => count($items), 'formations' => 3, 'brevets' => 2, 'qualifications' => 3, 'parcours' => 1, 'competences' => 1, 'emetteurs' => 1], $offer['totals']);
    }

    public function testHiddenQualificationsOnlyShowToStaff(): void
    {
        self::assertNull(AcademyOffer::find(AcademyOffer::assemble($this->source())['items'], 'q-3'));
        self::assertNotNull(AcademyOffer::find(AcademyOffer::assemble($this->source(true))['items'], 'q-3'));
    }

    public function testFiltersComeFromTheAddressAndAreSanitised(): void
    {
        $f = AcademyOffer::filters(['onglet' => 'qualifications', 'etat' => 'en-cours', 'mode' => 'auto', 'tri' => 'nom', 'source' => 'x', 'q' => ['array']]);
        self::assertSame('qualifications', $f['onglet']);
        self::assertSame('en-cours', $f['etat']);
        self::assertSame('auto', $f['mode']);
        self::assertSame('nom', $f['tri']);
        self::assertSame('', $f['source']);
        self::assertSame('', $f['q']);
        self::assertSame('tout', AcademyOffer::filters(['onglet' => '<script>'])['onglet']);

        $items = AcademyOffer::assemble($this->source())['items'];
        self::assertSame(['q-2'], array_column(AcademyOffer::filter($items, $f), 'id'));
        self::assertSame(['q-1'], array_column(AcademyOffer::filter($items, AcademyOffer::filters(['onglet' => 'brevets', 'emetteur' => '5'])), 'id'));
        self::assertSame(['n-12'], array_column(AcademyOffer::filter($items, AcademyOffer::filters(['onglet' => 'formations', 'source' => 'athena', 'etat' => 'disponible'])), 'id'));
        self::assertSame(['p-9', 'q-2'], array_column(AcademyOffer::filter($items, AcademyOffer::filters(['q' => 'pilote'])), 'id'));
        self::assertSame([], AcademyOffer::filter($items, AcademyOffer::filters(['q' => 'introuvable'])));
    }

    public function testCatalogueDashboardAndDetailRenderInBothLanguages(): void
    {
        $offer = AcademyOffer::assemble($this->source());
        $course = AcademyCurriculum::demo();
        $base = ['staff' => false, 'states' => [], 'courses' => [$course], 'selected' => null, 'state' => null, 'learners' => [], 'notice' => '', 'offer' => $offer, 'account' => ['display_name' => 'Viper']];
        foreach (['fr', 'en'] as $lang) {
            $GLOBALS['__app_locale'] = $lang;
            foreach ([['dashboard', [], ''], ['catalogue', [], ''], ['catalogue', ['onglet' => 'qualifications', 'etat' => 'en-cours'], ''], ['catalogue', [], 'q-2'], ['catalogue', [], 'p-9'], ['catalogue', [], 'e-5'], ['catalogue', [], 'inconnu']] as [$space, $query, $detail]) {
                $body = Response::view('academy.space', $base + ['space' => $space, 'offerFilters' => AcademyOffer::filters($query), 'offerDetail' => $detail])->body();
                self::assertStringContainsString('class="acx', $body, $space . ' ' . $detail);
                self::assertStringNotContainsString('Warning', $body);
            }
        }
        $GLOBALS['__app_locale'] = 'fr';
        $catalogue = Response::view('academy.space', $base + ['space' => 'catalogue', 'offerFilters' => AcademyOffer::filters([]), 'offerDetail' => ''])->body();
        self::assertStringContainsString('Brevets et licences', $catalogue);
        self::assertStringContainsString('aria-current="page"', $catalogue);
        self::assertStringContainsString('back-office/ma-situation/qualifications/91/brevet', $catalogue);
        $dashboard = Response::view('academy.space', $base + ['space' => 'dashboard', 'offerFilters' => AcademyOffer::filters([]), 'offerDetail' => ''])->body();
        self::assertStringContainsString('Où j’en suis', $dashboard);
        self::assertStringContainsString('Passer l’évaluation', $dashboard);
        self::assertStringContainsString('Ma progression vers…', $dashboard);
        $detail = Response::view('academy.space', $base + ['space' => 'catalogue', 'offerFilters' => AcademyOffer::filters([]), 'offerDetail' => 'q-4'])->body();
        self::assertStringContainsString('Prérequis', $detail);
        self::assertStringContainsString('Grade minimum « Sergent »', $detail);
    }

    public function testNewcomerSeesWhereToStart(): void
    {
        $src = $this->source();
        $src['states'] = [];
        $src['legacy_courses'][0]['enrollment'] = null;
        $src['evaluations'] = [];
        $body = Response::view('academy.space', ['space' => 'dashboard', 'staff' => false, 'states' => [], 'courses' => [], 'selected' => null, 'state' => null, 'learners' => [], 'notice' => '', 'offer' => AcademyOffer::assemble($src), 'offerFilters' => AcademyOffer::filters([]), 'offerDetail' => ''])->body();
        self::assertStringContainsString('Commencez par…', $body);
        self::assertStringNotContainsString('Où j’en suis', $body);
    }
}
