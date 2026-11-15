<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Personnel\MemberNameSettings;
use App\Support\MemberIdentity;
use App\Support\MemberName;
use PHPUnit\Framework\TestCase;

/**
 * Règle unique d'affichage d'un membre : état civil, puis indicatif, puis nom du compte, puis « Membre ».
 * Le nom du personnage (character_name) ne compte plus.
 */
final class MemberIdentityTest extends TestCase
{
    /** @return array{list_format: string, card_format: string, show_grade: bool, trigrams_enabled: bool} */
    private static function settings(array $override = []): array
    {
        return MemberNameSettings::normalize($override + MemberNameSettings::DEFAULTS);
    }

    /**
     * @param array<string, mixed> $row
     * @return array{primary: string, secondary: string, callsign: string, trigram: string, source: string}
     */
    private static function show(array $row, string $context = 'list', array $settings = []): array
    {
        return MemberIdentity::display($row, $context, 0, true, self::settings($settings));
    }

    /** Dix profils types : (ligne, nom en liste, nom en fiche, nom en C2, origine). */
    public function testPriorityOrderOnTenTypicalProfiles(): void
    {
        $profiles = [
            'état civil complet' => [
                ['first_name' => 'Damian', 'last_name' => 'Stones', 'callsign' => 'YB1', 'display_name' => 'dami#4242', 'character_name' => 'Perso RP'],
                'Damian S.', 'Damian STONES', 'YB1', 'civil',
            ],
            'prénom seul' => [['first_name' => 'Damian', 'callsign' => 'YB1', 'display_name' => 'dami'], 'Damian', 'Damian', 'YB1', 'civil'],
            'nom seul' => [['last_name' => 'Stones', 'callsign' => 'YB1'], 'STONES', 'STONES', 'YB1', 'civil'],
            'nom du personnage seul' => [['character_name' => 'Perso RP'], 'Membre', 'Membre', 'Membre', 'fallback'],
            'indicatif seul' => [['callsign' => 'YB1'], 'YB1', 'YB1', 'YB1', 'callsign'],
            'nom du compte seul' => [['display_name' => 'dami#4242'], 'dami#4242', 'dami#4242', 'dami#4242', 'account'],
            'indicatif et compte' => [['callsign' => 'YB1', 'display_name' => 'dami#4242'], 'YB1', 'YB1', 'YB1', 'callsign'],
            'compte = e-mail' => [['display_name' => 'jean@example.org'], 'Membre', 'Membre', 'Membre', 'fallback'],
            'espaces et tirets' => [['first_name' => ' ', 'last_name' => '—', 'callsign' => ' Viper 2-1 '], 'Viper 2-1', 'Viper 2-1', 'Viper 2-1', 'callsign'],
            'rien' => [[], 'Membre', 'Membre', 'Membre', 'fallback'],
        ];
        foreach ($profiles as $label => [$row, $list, $card, $c2, $source]) {
            self::assertSame($list, self::show($row, 'list')['primary'], $label . ' (liste)');
            self::assertSame($card, self::show($row, 'card')['primary'], $label . ' (fiche)');
            self::assertSame($c2, self::show($row, 'c2')['primary'], $label . ' (C2)');
            self::assertSame($source, self::show($row, 'list')['source'], $label . ' (origine)');
        }
    }

    public function testCharacterNameIsNeverUsed(): void
    {
        $row = ['character_name' => 'Capitaine Fantôme', 'callsign' => '', 'display_name' => ''];
        foreach (['list', 'card', 'c2'] as $ctx) {
            self::assertSame(MemberName::FALLBACK, self::show($row, $ctx)['primary']);
        }
        $row['callsign'] = 'YB1';
        self::assertSame('YB1', self::show($row)['primary']);
    }

    public function testCommunityFormatsApplyToListAndCardOnly(): void
    {
        $row = ['first_name' => 'Charles', 'last_name' => 'de Gaulle', 'callsign' => 'Général', 'trigram' => 'CDG'];
        self::assertSame('Charles G.', self::show($row, 'list')['primary']);
        self::assertSame('Charles de GAULLE', self::show($row, 'card')['primary']);
        self::assertSame('Général', self::show($row, 'c2')['primary']);

        $swap = ['list_format' => MemberName::FULL, 'card_format' => MemberName::CALLSIGN];
        self::assertSame('Charles de GAULLE', self::show($row, 'list', $swap)['primary']);
        self::assertSame('Général', self::show($row, 'card', $swap)['primary']);
        self::assertSame('Général', self::show($row, 'c2', $swap)['primary']);
    }

    public function testTrigramEnabledOrDisabled(): void
    {
        $row = ['first_name' => 'Jean', 'last_name' => 'Dupont', 'trigram' => 'jdu'];
        $on = self::show($row, 'list', ['list_format' => MemberName::TRIGRAM]);
        self::assertSame('JDU', $on['primary']);
        self::assertSame('JDU', $on['trigram']);

        $off = self::show($row, 'list', ['list_format' => MemberName::TRIGRAM, 'trigrams_enabled' => false]);
        self::assertSame('Jean D.', $off['primary']);
        self::assertSame('', $off['trigram']);
        self::assertSame('', self::show($row, 'card', ['trigrams_enabled' => false])['trigram']);
    }

    public function testGradeFollowsTheCommunitySetting(): void
    {
        $row = ['first_name' => 'Jean', 'last_name' => 'Dupont', 'grade_short' => 'Sgt'];
        self::assertSame('Jean D.', self::show($row)['primary']);
        self::assertSame('Sgt Jean D.', self::show($row, 'list', ['show_grade' => true])['primary']);
        self::assertSame('Jean D.', MemberIdentity::display($row, 'list', 0, false, self::settings(['show_grade' => true]))['primary']);
        // Le grade déjà écrit dans le nom n'est pas répété en ligne secondaire.
        self::assertSame('', self::show($row, 'list', ['show_grade' => true])['secondary']);
        self::assertSame('Sgt', self::show($row)['secondary']);
    }

    public function testSecondaryLineNeverRepeatsTheName(): void
    {
        $civil = ['first_name' => 'Damian', 'last_name' => 'Stones', 'callsign' => 'YB1', 'display_name' => 'dami#4242', 'trigram' => 'DST'];
        self::assertSame('YB1 · DST', self::show($civil, 'list')['secondary']);
        self::assertSame('YB1 · DST · dami#4242', self::show($civil, 'card')['secondary']);

        // Sans état civil, l'indicatif est le nom : la ligne secondaire ne le répète pas.
        $cs = ['callsign' => 'YB1', 'display_name' => 'dami#4242'];
        self::assertSame('', self::show($cs, 'list')['secondary']);
        self::assertSame('dami#4242', self::show($cs, 'card')['secondary']);

        // Compte identique au nom d'état civil : écrit une seule fois.
        $same = ['first_name' => 'Damian', 'last_name' => 'Stones', 'display_name' => 'damian stones'];
        self::assertSame('', self::show($same, 'card')['secondary']);

        // Un e-mail n'est jamais affiché.
        $mail = ['first_name' => 'Damian', 'last_name' => 'Stones', 'display_name' => 'damian@example.org'];
        self::assertSame('', self::show($mail, 'card')['secondary']);

        // C2 : pas de ligne secondaire (l'état civil n'y est pas montré).
        self::assertSame('', self::show($civil, 'c2')['secondary']);
    }

    public function testMaskedMemberOnlyShowsTheAnonymizedLabel(): void
    {
        $masked = [
            'anonymized' => true, 'first_name' => 'Damian', 'last_name' => 'Stones', 'callsign' => 'YB1',
            'trigram' => 'DST', 'grade_short' => 'Sgt',
        ];
        foreach (['list', 'card', 'c2'] as $ctx) {
            $out = self::show($masked, $ctx, ['show_grade' => true]);
            self::assertSame('Personnel anonymisé', $out['primary'], $ctx);
            self::assertSame('', $out['secondary']);
            self::assertSame('', $out['callsign']);
            self::assertSame('', $out['trigram']);
            self::assertSame('masked', $out['source']);
        }
        self::assertSame('Cible protégée', self::show($masked + ['label' => 'Cible protégée'])['primary']);
    }

    public function testReturnsPlainTextAndLeavesEscapingToTheView(): void
    {
        $row = ['first_name' => '<script>alert(1)</script>', 'last_name' => "O'Neil & Fils", 'callsign' => '"><img src=x>'];
        $card = self::show($row, 'card');
        self::assertSame('<script>alert(1)</script> O’NEIL & FILS', str_replace("'", '’', $card['primary']));
        self::assertStringContainsString('<img src=x>', self::show(['callsign' => '"><img src=x>'])['primary']);
        self::assertStringNotContainsString('&lt;', $card['primary'] . $card['secondary']);
        self::assertStringNotContainsString('&amp;', $card['primary'] . $card['secondary']);
    }

    public function testHelperFunctionAndNameShortcutAgree(): void
    {
        $row = ['first_name' => 'Damian', 'last_name' => 'Stones', 'callsign' => 'YB1'];
        self::assertSame(self::show($row, 'card'), \member_identity($row, 'card', 0));
        self::assertSame('Damian S.', MemberIdentity::name($row, 'list', 0));
    }
}
