<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\DataCampaignBlocks;
use App\Support\DataCampaignLoginPrompt;
use App\Support\DataCampaignPage;
use PHPUnit\Framework\TestCase;

/**
 * Page de fiabilisation du membre : ordre des blocs, progression, libellés d'état et d'action, échéance, action principale,
 * masque des identifiants, résumés, et présence des textes dans le catalogue anglais. Fonctions pures, sans base ni mock.
 */
final class DataCampaignPageTest extends TestCase
{
    private const ROOT = __DIR__ . '/../..';

    /**
     * Six blocs comme dans une vraie campagne, avec l'état voulu.
     *
     * @param array<string, string> $states clé de bloc => 'ok' | 'flag' | '' ; 'empty' vide le bloc
     * @return array<string, array<string, mixed>>
     */
    private static function blocks(array $states): array
    {
        $out = [];
        foreach (DataCampaignBlocks::keys() as $key) {
            $state = $states[$key] ?? '';
            $out[$key] = [
                'key' => $key,
                'label' => DataCampaignBlocks::label($key),
                'state' => $state === 'empty' ? '' : $state,
                'portrait' => null,
                'rows' => $state === 'empty' ? [] : [['label' => 'Grade', 'value' => 'Caporal-chef']],
            ];
        }

        return $out;
    }

    public function testBlocksToDoComeFirstThenFollowUpThenDone(): void
    {
        $blocks = self::blocks([
            'identity' => 'ok',
            'identifiers' => '',
            'assignment' => 'flag',
            'qualifications' => 'empty',
            'hours' => 'ok',
            'contact' => '',
        ]);
        $order = array_map(static fn (array $r): string => $r['key'] . ':' . $r['status'], DataCampaignPage::ordered($blocks));
        self::assertSame([
            'qualifications:incomplete',
            'identifiers:to_confirm',
            'contact:to_confirm',
            'assignment:flagged',
            'identity:ok',
            'hours:ok',
        ], $order);
    }

    public function testEqualStatusKeepsTheCatalogOrder(): void
    {
        $order = array_column(DataCampaignPage::ordered(self::blocks([])), 'key');
        self::assertSame(DataCampaignBlocks::keys(), $order);
    }

    public function testGroupsSplitWhatTheMemberMustDoFromWhatIsDone(): void
    {
        $groups = DataCampaignPage::groups(self::blocks(['identity' => 'ok', 'assignment' => 'flag', 'qualifications' => 'empty']));
        self::assertSame(['qualifications', 'identifiers', 'hours', 'contact'], array_column($groups['todo'], 'key'));
        self::assertSame(['assignment', 'identity'], array_column($groups['done'], 'key'));
        self::assertTrue(DataCampaignPage::isTodo('incomplete'));
        self::assertTrue(DataCampaignPage::isTodo('to_confirm'));
        self::assertFalse(DataCampaignPage::isTodo('flagged'));
        self::assertFalse(DataCampaignPage::isTodo('ok'));
    }

    public function testProgressCountsOnlyExactBlocksAsConfirmed(): void
    {
        $p = DataCampaignPage::progress(self::blocks(['identity' => 'ok', 'identifiers' => 'ok', 'assignment' => 'flag', 'qualifications' => 'empty']));
        self::assertSame(6, $p['total']);
        self::assertSame(2, $p['confirmed']);
        self::assertSame(1, $p['flagged']);
        self::assertSame(3, $p['remaining']);
        self::assertSame(33, $p['percent']);
        self::assertSame(16, $p['flaggedPercent']);
        // Avancement du parcours (inchangé) : les blocs signalés comptent comme répondus.
        self::assertSame(50, DataCampaignLoginPrompt::summarize(self::blocks(['identity' => 'ok', 'identifiers' => 'ok', 'assignment' => 'flag']))['percent']);
    }

    public function testProgressOfAnEmptyCampaignIsZero(): void
    {
        $p = DataCampaignPage::progress([]);
        self::assertSame(0, $p['total']);
        self::assertSame(0, $p['percent']);
        self::assertSame(0, $p['remaining']);
    }

    public function testLabelsFollowTheCounts(): void
    {
        self::assertSame('4 sur 7 blocs confirmés', DataCampaignPage::progressLabel(4, 7));
        self::assertSame('1 sur 1 bloc confirmé', DataCampaignPage::progressLabel(1, 1));
        self::assertSame('Il reste 1 bloc à traiter', DataCampaignPage::remainingLabel(1, 0));
        self::assertSame('Il reste 3 blocs à traiter', DataCampaignPage::remainingLabel(3, 2));
        self::assertSame('Rien d’autre à faire de votre côté', DataCampaignPage::remainingLabel(0, 1));
        self::assertSame('Tout est vérifié', DataCampaignPage::remainingLabel(0, 0));
        self::assertSame('1 correction en cours d’examen', DataCampaignPage::flaggedLabel(1));
        self::assertSame('2 corrections en cours d’examen', DataCampaignPage::flaggedLabel(2));
    }

    public function testEachStatusHasOneBadgeAndOneAction(): void
    {
        $expected = [
            'ok' => ['Exact', 'Revoir'],
            'to_confirm' => ['À confirmer', 'Confirmer'],
            'incomplete' => ['À compléter', 'Compléter'],
            'flagged' => ['Correction demandée', 'Corriger'],
        ];
        foreach ($expected as $status => [$badge, $action]) {
            self::assertSame($badge, DataCampaignPage::statusLabel($status));
            self::assertSame($action, DataCampaignPage::actionLabel($status));
        }
    }

    public function testDueDateTonesAtThirtyThreeAndZeroDaysAndWhenOverdue(): void
    {
        $today = '2026-11-15';
        $far = DataCampaignPage::due('2026-12-15', $today, false);
        self::assertSame(['tone' => 'calm', 'date' => 'Avant le 15/12/2026', 'delay' => '30 jours restants'], $far);
        $soon = DataCampaignPage::due('2026-11-18', $today, false);
        self::assertSame('soon', $soon['tone']);
        self::assertSame('3 jours restants', $soon['delay']);
        self::assertSame('1 jour restant', DataCampaignPage::due('2026-11-16', $today, false)['delay']);
        $last = DataCampaignPage::due($today, $today, false);
        self::assertSame('late', $last['tone']);
        self::assertSame('dernier jour aujourd’hui', $last['delay']);
        $late = DataCampaignPage::due('2026-11-09', $today, false);
        self::assertSame(['tone' => 'late', 'date' => 'Date limite : 09/11/2026', 'delay' => 'dépassée depuis 6 jours'], $late);
        self::assertSame('dépassée depuis 1 jour', DataCampaignPage::due('2026-11-14', $today, false)['delay']);
    }

    public function testNoDueDateOnceConfirmedOrWithoutDeadline(): void
    {
        self::assertSame('none', DataCampaignPage::due('2026-12-15', '2026-11-15', true)['tone']);
        self::assertSame('none', DataCampaignPage::due('', '2026-11-15', false)['tone']);
    }

    public function testPrimaryActionIsConfirmAllOnlyWhenNothingIsMissingOrFlagged(): void
    {
        $ready = self::blocks(['identity' => 'ok']);
        self::assertSame(['kind' => 'confirm_all', 'label' => 'Tout confirmer', 'step' => ''], DataCampaignPage::primaryAction($ready, false));

        $missing = self::blocks(['identity' => 'ok', 'hours' => 'empty']);
        $go = DataCampaignPage::primaryAction($missing, false);
        self::assertSame('go', $go['kind']);
        self::assertSame('Continuer', $go['label']);
        self::assertSame('hours', $go['step'], 'le premier bloc à traiter, pas le premier du parcours');

        $flaggedOnly = array_fill_keys(DataCampaignBlocks::keys(), 'ok');
        $flaggedOnly['contact'] = 'flag';
        $recap = DataCampaignPage::primaryAction(self::blocks($flaggedOnly), false);
        self::assertSame(['kind' => 'go', 'label' => 'Continuer', 'step' => 'recap'], $recap);

        self::assertSame('none', DataCampaignPage::primaryAction($ready, true)['kind']);
    }

    public function testIdentifiersAndEmailAreMaskedInSummaries(): void
    {
        self::assertSame('••••3116', DataCampaignPage::maskId('2024-0873116'));
        self::assertSame('••••', DataCampaignPage::maskId('AB12'));
        self::assertSame('', DataCampaignPage::maskId(''));
        self::assertSame('Non renseigné', DataCampaignPage::maskId('Non renseigné'));
        self::assertSame('m•••••@example.fr', DataCampaignPage::maskEmail('mathieu.delacroix@example.fr'));

        $block = ['rows' => [
            ['label' => 'Matricule de votre organisation', 'value' => 'M-004217'],
            ['label' => 'Matricule de service', 'value' => '2024-0873116'],
            ['label' => 'Trigramme', 'value' => 'DLC'],
            ['label' => 'Indicatif radio', 'value' => 'RAVEN-2'],
            ['label' => 'Identifiant Athena', 'value' => 'ATH-7F3K-92QD'],
        ]];
        $text = json_encode(DataCampaignPage::summary(DataCampaignBlocks::IDENTIFIERS, $block), JSON_UNESCAPED_UNICODE);
        self::assertStringNotContainsString('0873116', (string) $text);
        self::assertStringNotContainsString('004217', (string) $text);
        self::assertStringNotContainsString('ATH-7F3K', (string) $text);
        self::assertStringContainsString('••••3116', (string) $text);

        $mail = DataCampaignPage::summary(DataCampaignBlocks::CONTACT, ['rows' => [['label' => 'Adresse e-mail du compte', 'value' => 'mathieu.delacroix@example.fr']]]);
        self::assertSame('m•••••@example.fr', $mail[0]['value']);
    }

    public function testSummaryFlagsMissingValuesAndHandlesNamelessMembers(): void
    {
        $nameless = DataCampaignPage::summary(DataCampaignBlocks::IDENTITY, ['rows' => [
            ['label' => 'Prénom', 'value' => 'Non renseigné'],
            ['label' => 'Nom', 'value' => 'Non renseigné'],
            ['label' => 'Indicatif', 'value' => 'RAVEN-2'],
            ['label' => 'Surnom principal', 'value' => 'Non renseigné'],
        ]]);
        self::assertTrue($nameless[0]['missing'], 'ni prénom ni nom : le nom est à compléter');
        self::assertSame('RAVEN-2', $nameless[1]['value']);
        self::assertFalse($nameless[1]['missing']);

        $none = DataCampaignPage::summary(DataCampaignBlocks::QUALIFICATIONS, ['rows' => []]);
        self::assertSame([['label' => 'Inscrites', 'value' => 'Aucune', 'missing' => true]], $none);

        $quals = DataCampaignPage::summary(DataCampaignBlocks::QUALIFICATIONS, ['rows' => [
            ['label' => 'Secourisme — Niveau 2', 'value' => 'Obtenue, le 12/05/2023'],
            ['label' => 'Pilote — Brevet', 'value' => 'En formation'],
        ]]);
        self::assertSame('2', $quals[0]['value']);
        self::assertSame('1', $quals[1]['value']);
    }

    public function testSeveralUnitsAreSummarisedByTheFirstOne(): void
    {
        $items = DataCampaignPage::summary(DataCampaignBlocks::ASSIGNMENT, ['rows' => [
            ['label' => 'Grade', 'value' => 'Caporal-chef'],
            ['label' => 'Unités', 'value' => '1re Compagnie · Section Alpha'],
            ['label' => 'Fonctions', 'value' => 'Tireur d’élite'],
            ['label' => 'Date d’engagement', 'value' => '14/03/2022'],
        ]]);
        self::assertSame('1re Compagnie +1', $items[1]['value']);
        self::assertSame('Tireur d’élite', $items[2]['value']);
        self::assertCount(4, $items);
    }

    public function testEveryBlockHasAnIconAndTheTitleIsShort(): void
    {
        foreach (DataCampaignBlocks::keys() as $key) {
            self::assertStringContainsString('<', DataCampaignPage::iconPath($key));
        }
        self::assertLessThanOrEqual(30, mb_strlen(DataCampaignPage::pageTitle()));
        self::assertStringContainsString('2 minutes', DataCampaignPage::pageLead());
    }

    public function testEveryPageTextHasAnEnglishTranslation(): void
    {
        $en = require self::ROOT . '/lang/en/site_auto.php';
        $texts = [DataCampaignPage::pageTitle(), DataCampaignPage::pageLead(), DataCampaignPage::progressLabel(1, 1), 'Tout confirmer', 'Continuer'];
        foreach (['ok', 'to_confirm', 'incomplete', 'flagged'] as $status) {
            $texts[] = DataCampaignPage::statusLabel($status);
            $texts[] = DataCampaignPage::actionLabel($status);
        }
        foreach ([0, 1, 2, 3, 12] as $n) {
            $texts[] = DataCampaignPage::remainingLabel($n, 0);
            $texts[] = DataCampaignPage::flaggedLabel($n);
        }
        $texts[] = DataCampaignPage::remainingLabel(0, 1);
        foreach ([['2026-12-15', 30], ['2026-11-16', 1], ['2026-11-15', 0], ['2026-11-14', -1], ['2026-11-09', -6]] as [$due]) {
            $d = DataCampaignPage::due($due, '2026-11-15', false);
            $texts[] = (string) preg_replace('~\d{2}/\d{2}/\d{4}~', '%1', $d['date']);
            $texts[] = $d['delay'];
        }
        $texts[] = DataCampaignPage::progressLabel(4, 7);
        foreach ($texts as $text) {
            $candidates = [$text];
            $n = 0;
            $candidates[] = (string) preg_replace_callback('~\d+~', static function () use (&$n): string {
                return '%' . ++$n;
            }, $text);
            $n = 0;
            $candidates[] = (string) preg_replace_callback('~\d+~', static function (array $m) use (&$n): string {
                return ++$n === 1 ? '%1' : $m[0];
            }, $text);
            $known = array_filter($candidates, static fn (string $c): bool => array_key_exists($c, $en));
            self::assertTrue($known !== [], 'traduction anglaise manquante : ' . $text);
        }
    }

    public function testTheMemberViewKeepsNoEyebrowAndLoadsItsOwnStyles(): void
    {
        $view = (string) file_get_contents(self::ROOT . '/views/admin/member_situation/data_campaign.php');
        self::assertStringContainsString('DataCampaignPage::', $view);
        self::assertDoesNotMatchRegularExpression('/kicker|eyebrow|surtitre/i', $view);
        $css = (string) file_get_contents(self::ROOT . '/public/assets/css/data-campaign-member.css');
        self::assertDoesNotMatchRegularExpression('/font-size:\s*(?:[0-9]|1[01])(?:\.\d+)?px/', $css, 'aucun texte sous 12 px');
        self::assertDoesNotMatchRegularExpression('/uppercase/', $css, 'pas de petits libellés en capitales');
        $controller = (string) file_get_contents(self::ROOT . '/app/Controllers/Web/MemberDataCampaignController.php');
        self::assertStringContainsString("'data-campaign-member.css'", $controller);
    }
}
