<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Effectifs\RhActionInboxService;
use App\Support\I18n\UntranslatedFrenchScanner;
use PHPUnit\Framework\TestCase;

/**
 * « À traiter » : les alertes RH rangées par urgence, un compteur par carte. Données fictives uniquement.
 */
final class RhActionInboxServiceTest extends TestCase
{
    public function testEveryCardBelongsToAKnownSectionAndHasItsTexts(): void
    {
        foreach (RhActionInboxService::CARDS as $id => $card) {
            self::assertArrayHasKey($card['section'], RhActionInboxService::SECTIONS, $id);
            self::assertNotSame('', $card['title'], $id);
            self::assertNotSame('', $card['text'], $id);
            self::assertContains($card['tone'], ['danger', 'warn', 'info'], $id);
        }
        self::assertSame(['now', 'watch', 'info'], array_keys(RhActionInboxService::SECTIONS));
    }

    public function testTheRequestedAlertsAreAllGrouped(): void
    {
        $required = [
            'corrections', 'elevations', 'mobility', 'qualif_urgent', 'qualif_soon', 'vacant_critical', 'vacant',
            'absence_long', 'inactive', 'incomplete', 'missing_command', 'career',
        ];
        foreach ($required as $id) {
            self::assertArrayHasKey($id, RhActionInboxService::CARDS, $id);
        }
        // Alertes déjà calculées ailleurs dans le bureau effectifs et ajoutées ici.
        foreach (['duplicates', 'phase_gates', 'phase_errors', 'roleplay_due', 'integration', 'no_leader', 'adequacy', 'no_unit', 'no_role', 'pending_accounts'] as $id) {
            self::assertArrayHasKey($id, RhActionInboxService::CARDS, $id);
        }
    }

    public function testAssembleSortsBySectionAndHidesEmptyCards(): void
    {
        $out = RhActionInboxService::assemble([
            'corrections' => ['count' => 4, 'href' => '/c', 'items' => [['label' => 'Alpha'], ['label' => 'Bravo']]],
            'qualif_soon' => ['count' => 3],
            'career' => ['count' => 7],
            'mobility' => ['count' => 0],
        ]);

        self::assertSame(4, $out['total'], 'la pastille ne compte que « à traiter maintenant »');
        self::assertSame(7, $out['attention']);
        self::assertSame(4, $out['sections']['now']['count']);
        self::assertSame(3, $out['sections']['watch']['count']);
        self::assertSame(7, $out['sections']['info']['count']);

        $now = $out['sections']['now']['cards'];
        self::assertCount(1, $now);
        self::assertSame('corrections', $now[0]['id']);
        self::assertSame(4, $now[0]['count']);
        self::assertSame(2, $now[0]['more'], 'deux lignes de détail sur quatre : il en reste deux');
        self::assertContains('Demandes de mobilité', $out['sections']['now']['clear']);
        self::assertSame(['qualif_soon'], array_column($out['sections']['watch']['cards'], 'id'));
        self::assertSame(['career'], array_column($out['sections']['info']['cards'], 'id'));
    }

    public function testEmptyFactsGiveEmptySections(): void
    {
        $out = RhActionInboxService::assemble([]);
        self::assertSame(0, $out['total']);
        foreach ($out['sections'] as $section) {
            self::assertSame([], $section['cards']);
            self::assertSame(0, $section['count']);
        }
    }

    public function testNoAbsenceCardMakesADossierIncomplete(): void
    {
        // Une absence est déclarative : « Dossiers incomplets » ne parle jamais d'absence, et les absences
        // prolongées sont une carte de suivi à part.
        self::assertStringNotContainsStringIgnoringCase('absence', RhActionInboxService::CARDS['incomplete']['text']);
        self::assertSame('watch', RhActionInboxService::CARDS['absence_long']['section']);
        $source = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Services/Effectifs/RhActionInboxService.php');
        self::assertStringNotContainsString("'absence' =>", $source);
    }

    public function testUrgencyDecidesTheSection(): void
    {
        self::assertSame('now', RhActionInboxService::CARDS['corrections']['section']);
        self::assertSame('now', RhActionInboxService::CARDS['qualif_urgent']['section']);
        self::assertSame('watch', RhActionInboxService::CARDS['qualif_soon']['section']);
        self::assertSame('info', RhActionInboxService::CARDS['career']['section']);
        self::assertSame('now', RhActionInboxService::CARDS['vacant_critical']['section']);
        self::assertSame('info', RhActionInboxService::CARDS['vacant']['section']);
    }

    public function testEveryFixedTextHasItsEnglishTranslation(): void
    {
        $texts = [];
        foreach (RhActionInboxService::SECTIONS as $section) {
            $texts[] = $section['label'];
            $texts[] = $section['lead'];
        }
        foreach (RhActionInboxService::CARDS as $card) {
            $texts[] = $card['title'];
            $texts[] = $card['text'];
            if ($card['cta'] !== '') {
                $texts[] = $card['cta'];
            }
        }
        $missing = array_values(array_filter($texts, static fn (string $t): bool => !UntranslatedFrenchScanner::isTranslated($t)));
        self::assertSame([], $missing);
    }

    public function testNoEmailAndNoDangerousSqlInTheInboxFiles(): void
    {
        $root = dirname(__DIR__, 2);
        foreach ([
            'app/Services/Effectifs/RhActionInboxService.php',
            'views/admin/effectifs_workspace/rh_inbox.php',
            'views/admin/effectifs_workspace/rh_identity_papers.php',
            'views/personnel/corrections_queue.php',
        ] as $rel) {
            $src = (string) file_get_contents($root . '/' . $rel);
            self::assertDoesNotMatchRegularExpression('/\[\'(user_|target_|requester_)?email\'\]|u\.email|->email\b/', $src, $rel);
            self::assertStringContainsString('declare(strict_types=1);', $src, $rel);
        }
        // ONLY_FULL_GROUP_BY (MariaDB, MySQL 8) : plus d'alias d'agrégat dans une expression de tri.
        $agg = (string) file_get_contents($root . '/app/Services/Effectifs/RhAlertAggregatorService.php');
        self::assertStringNotContainsString('HAVING held', $agg);
        self::assertStringNotContainsString('- held)', $agg);
    }
}
