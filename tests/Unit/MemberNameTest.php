<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Personnel\MemberNameSettings;
use App\Support\MemberName;
use PHPUnit\Framework\TestCase;

/**
 * Nom affiché des membres : « Prénom NOM », « Prénom N. », trigramme d’état-major, indicatif, grade, repli et masquage.
 */
final class MemberNameTest extends TestCase
{
    public function testFullAndShortPutFirstNameFirst(): void
    {
        $row = ['first_name' => 'Jean', 'last_name' => 'Dupont', 'display_name' => 'Jeanjean', 'callsign' => 'Viper 2-1'];

        self::assertSame('Jean DUPONT', MemberName::full($row));
        self::assertSame('Jean D.', MemberName::short($row));
        self::assertSame('Jean D.', MemberName::format($row));
        self::assertSame('Jean DUPONT', MemberName::format($row, MemberName::FULL));
        self::assertSame('Viper 2-1', MemberName::format($row, MemberName::CALLSIGN));
        self::assertSame('Viper 2-1 · Jeanjean', MemberName::secondary($row, 'Jean D.'));
    }

    public function testParticlesAndCompoundNames(): void
    {
        self::assertSame('Charles de GAULLE', MemberName::full(['first_name' => 'Charles', 'last_name' => 'de Gaulle']));
        self::assertSame('Charles G.', MemberName::short(['first_name' => 'Charles', 'last_name' => 'de Gaulle']));
        self::assertSame('Jan van der BERG', MemberName::full(['first_name' => 'Jan', 'last_name' => 'van der Berg']));
        self::assertSame('Jan B.', MemberName::short(['first_name' => 'Jan', 'last_name' => 'van der Berg']));
        self::assertSame('Jean-Pierre M.-D.', MemberName::short(['first_name' => 'Jean-Pierre', 'last_name' => 'Martin-Dupont']));
        self::assertSame('Anne d’ARTAGNAN', MemberName::full(['first_name' => 'Anne', 'last_name' => 'd’Artagnan']));
        self::assertSame('Anne A.', MemberName::short(['first_name' => 'Anne', 'last_name' => 'd’Artagnan']));
        // Un nom de famille qui n’est qu’une « particule » reste un nom.
        self::assertSame('Thi LE', MemberName::full(['first_name' => 'Thi', 'last_name' => 'Le']));
    }

    public function testTrigramFollowsStaffConventionWithoutAccentsOrParticles(): void
    {
        self::assertSame('JDU', MemberName::trigramCandidates('Jean', 'Dupont')[0]);
        self::assertSame('EEB', MemberName::trigramCandidates('Élodie', 'Ébène')[0]);
        self::assertSame('CGA', MemberName::trigramCandidates('Charles', 'de Gaulle')[0]);
        self::assertSame('JBE', MemberName::trigramCandidates('Jan', 'van der Berg')[0]);
        self::assertSame('AAR', MemberName::trigramCandidates('Anne', 'd’Artagnan')[0]);
        self::assertSame('FMU', MemberName::trigramCandidates('François', 'Müller')[0]);
        $compound = MemberName::trigramCandidates('Jean-Pierre', 'Martin-Dupont');
        self::assertSame('JMA', $compound[0]);
        self::assertContains('JPM', $compound);
        self::assertContains('JMD', $compound);
        foreach ($compound as $c) {
            self::assertMatchesRegularExpression('/^[A-Z]{3}$/', $c);
        }
        self::assertSame(count($compound), count(array_unique($compound)));
    }

    public function testCollisionsTakeTheNextVariantInOrder(): void
    {
        $row = ['first_name' => 'Jean', 'last_name' => 'Dupont'];
        self::assertSame(['JDU', 'JDP', 'JDT', 'JED'], array_slice(MemberName::trigramCandidates('Jean', 'Dupont'), 0, 4));
        self::assertSame('JDU', MemberName::pickTrigram($row, []));
        self::assertSame('JDP', MemberName::pickTrigram($row, ['JDU' => true]));
        self::assertSame('JDT', MemberName::pickTrigram($row, ['JDU' => true, 'JDP' => true]));
        self::assertSame('JED', MemberName::pickTrigram($row, ['JDU' => true, 'JDP' => true, 'JDT' => true]));
        // Même les variantes du nom épuisées, un trigramme libre est toujours trouvé.
        $taken = array_fill_keys(array_slice(MemberName::trigramCandidates('Jean', 'Dupont'), 0, 30), true);
        $next = MemberName::pickTrigram($row, $taken);
        self::assertNotNull($next);
        self::assertArrayNotHasKey($next, $taken);
    }

    public function testFallbackToPseudoWhenNamesAreMissing(): void
    {
        $pseudo = ['first_name' => '', 'last_name' => '', 'display_name' => 'Ghost', 'callsign' => 'Reaper'];
        self::assertSame('Ghost', MemberName::full($pseudo));
        self::assertSame('Ghost', MemberName::short($pseudo));
        self::assertSame('GHO', MemberName::pickTrigram($pseudo, []));
        self::assertSame('Reaper', MemberName::short(['callsign' => 'Reaper']));
        self::assertSame(MemberName::FALLBACK, MemberName::short([]));
        self::assertSame('DUPONT', MemberName::short(['last_name' => 'Dupont']));
        // Trigramme demandé mais pas encore attribué : le nom abrégé s’affiche.
        self::assertSame('Jean D.', MemberName::format(['first_name' => 'Jean', 'last_name' => 'Dupont'], MemberName::TRIGRAM));
        self::assertSame('JDU', MemberName::format(['first_name' => 'Jean', 'last_name' => 'Dupont', 'trigram' => 'jdu'], MemberName::TRIGRAM));
        self::assertNull(MemberName::pickTrigram([], []));
    }

    public function testGradeVariantAndManualTrigramValidation(): void
    {
        $row = ['first_name' => 'Jean', 'last_name' => 'Dupont', 'grade_short' => 'Sgt'];
        self::assertSame('Sgt Jean D.', MemberName::format($row, MemberName::SHORT, true));
        self::assertSame('Sgt Jean DUPONT', MemberName::format($row, MemberName::FULL, true));
        self::assertSame('Sgt Jean D.', MemberName::withGrade($row, 'Sgt Jean D.'));
        self::assertSame('JDU', MemberName::normalizeTrigram(' jdu '));
        self::assertSame('EAE', MemberName::normalizeTrigram('éàè'));
        self::assertNull(MemberName::normalizeTrigram('JD'));
        self::assertNull(MemberName::normalizeTrigram('JDUP'));
        self::assertNull(MemberName::normalizeTrigram('J1U'));
    }

    public function testMaskedMemberNeverRevealsNameOrTrigram(): void
    {
        $masked = [
            'anonymized' => true, 'display_name' => 'Personnel anonymisé', 'first_name' => 'Jean', 'last_name' => 'Dupont',
            'callsign' => 'Viper', 'trigram' => 'JDU', 'grade_short' => 'Sgt',
        ];
        foreach (MemberName::FORMATS as $f) {
            self::assertSame('Personnel anonymisé', MemberName::format($masked, $f, true));
        }
        self::assertSame('Personnel anonymisé', MemberName::full($masked));
        self::assertSame('Personnel anonymisé', MemberName::c2($masked));
        self::assertSame('', MemberName::trigram($masked));
        self::assertSame('', MemberName::callsign($masked));
        self::assertSame('', MemberName::secondary($masked));
        // L’identité chargée à part n’est jamais réinjectée dans une ligne masquée.
        $merged = MemberName::withIdentity($masked, ['first_name' => 'Jean', 'last_name' => 'Dupont', 'trigram' => 'JDU']);
        self::assertArrayNotHasKey('first_name', $merged);
        self::assertArrayNotHasKey('trigram', $merged);
        self::assertSame('Jean D.', MemberName::short(MemberName::withIdentity(['display_name' => 'Jeanjean'], ['first_name' => 'Jean', 'last_name' => 'Dupont'])));
    }

    public function testC2KeepsTheCallsignFirst(): void
    {
        self::assertSame('Viper 2-1', MemberName::c2(['first_name' => 'Jean', 'last_name' => 'Dupont', 'callsign' => 'Viper 2-1']));
        self::assertSame('Jean D.', MemberName::c2(['first_name' => 'Jean', 'last_name' => 'Dupont']));
    }

    public function testSettingsDefaultsAndNormalization(): void
    {
        self::assertSame(MemberName::SHORT, MemberNameSettings::DEFAULTS['list_format']);
        self::assertSame(MemberName::FULL, MemberNameSettings::DEFAULTS['card_format']);
        $s = MemberNameSettings::normalize(['list_format' => 'trigram', 'card_format' => 'n_importe_quoi', 'trigrams_enabled' => false]);
        self::assertSame(MemberName::SHORT, $s['list_format']);
        self::assertSame(MemberName::FULL, $s['card_format']);
        self::assertFalse($s['trigrams_enabled']);
        self::assertSame(MemberName::FULL, MemberNameSettings::formatFor(MemberNameSettings::DEFAULTS, 'card'));
        self::assertSame(MemberName::SHORT, MemberNameSettings::formatFor(MemberNameSettings::DEFAULTS, 'list'));
        self::assertSame(MemberName::CALLSIGN, MemberNameSettings::formatFor(MemberNameSettings::DEFAULTS, 'callsign'));
    }
}
