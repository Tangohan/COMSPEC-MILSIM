<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Organization\EchelonStandards;
use App\Services\Organization\GradeLevel;
use App\Services\Organization\OrgAdequacyEngine;
use App\Services\Organization\OrgAdequacySettings;
use PHPUnit\Framework\TestCase;

/**
 * Moteur d’adéquation de l’organisation : chaque type d’écart, grades US et FR, adjoint, place, réglages.
 */
final class OrgAdequacyEngineTest extends TestCase
{
    /** Grade (code OTAN) → personne. */
    private static function person(string $label, ?string $nato, string $gradeLabel = ''): array
    {
        return [
            'label' => $label,
            'has_grade' => $nato !== null,
            'grade_label' => $gradeLabel !== '' ? $gradeLabel : (string) $nato,
            'grade_level' => $nato !== null ? GradeLevel::fromCode($nato) : null,
        ];
    }

    /** @param array<string, mixed> $extra */
    private static function evalOrg(array $extra, string $profile = EchelonStandards::PROFILE_US, array $settings = []): array
    {
        return OrgAdequacyEngine::evaluate(array_replace([
            'settings' => $settings,
            'profile' => $profile,
            'units' => [],
            'members_by_unit' => [],
            'primary_unit' => [],
            'deputies' => [],
            'people' => [],
            'grade_labels_by_level' => [5 => ['Sergeant'], 6 => ['Staff Sergeant'], 4 => ['Corporal']],
        ], $extra));
    }

    /** @return list<string> */
    private static function codes(array $result, string $kind = '', int $id = 0): array
    {
        $issues = $result['issues'];
        if ($kind === 'unit') {
            $issues = array_map(static fn (int $i): array => $result['issues'][$i], $result['units'][$id]['issues']);
        } elseif ($kind === 'person') {
            $issues = array_map(static fn (int $i): array => $result['issues'][$i], $result['people'][$id]['issues']);
        }

        return array_values(array_map(static fn (array $i): string => (string) $i['code'], $issues));
    }

    /** Squad US de 9 : chef + deux fire teams de 4 (team leaders désignés). */
    private static function usSquad(string $leaderGrade): array
    {
        $people = [1 => self::person('Squad Lead', $leaderGrade)];
        $people[2] = self::person('Alpha TL', 'OR-5');
        $people[3] = self::person('Bravo TL', 'OR-5');
        foreach ([11, 12, 13, 21, 22, 23] as $id) {
            $people[$id] = self::person('Rifleman ' . $id, 'OR-3');
        }
        $people[30] = self::person('Staff Sergeant spare', 'OR-6', 'Staff Sergeant');

        return [
            'units' => [
                ['id' => 10, 'parent_id' => 0, 'name' => '1st Squad', 'commander_user_id' => 1, 'echelon' => 'groupe'],
                ['id' => 20, 'parent_id' => 10, 'name' => 'Alpha', 'commander_user_id' => 2, 'echelon' => 'equipe'],
                ['id' => 30, 'parent_id' => 10, 'name' => 'Bravo', 'commander_user_id' => 3, 'echelon' => 'equipe'],
            ],
            'members_by_unit' => [10 => [1], 20 => [2, 11, 12, 13], 30 => [3, 21, 22, 23]],
            'primary_unit' => [1 => 10, 2 => 20, 11 => 20, 12 => 20, 13 => 20, 3 => 30, 21 => 30, 22 => 30, 23 => 30],
            'people' => $people,
        ];
    }

    public function testGradeLevelsAreComparableAcrossUsAndFrenchSystems(): void
    {
        self::assertSame(5, GradeLevel::fromCode('OR-5'));
        self::assertSame(5, GradeLevel::fromCode('E-5'));
        self::assertSame(10, GradeLevel::fromCode('O-1'));
        self::assertSame(10, GradeLevel::fromCode('O-2'));
        self::assertSame(11, GradeLevel::fromCode('O-3'));
        self::assertSame(11, GradeLevel::fromCode('OF-2'));
        self::assertSame(13, GradeLevel::fromCode('O-5'));
        self::assertNull(GradeLevel::fromCode(''));
        self::assertNull(GradeLevel::fromCode('XYZ'));
        self::assertSame('OF-2', GradeLevel::code(11));
        self::assertSame('Sergent / Sergent-chef (OR-5–OR-6)', GradeLevel::rangeLabel(5, 6, [5 => ['Sergent'], 6 => ['Sergent-chef']]));
        self::assertSame('OR-5', GradeLevel::rangeLabel(5, 5));
    }

    public function testReferentialDefaultsFollowPublicDoctrine(): void
    {
        $us = EchelonStandards::all(EchelonStandards::PROFILE_US);
        self::assertSame([5, 5], $us['equipe']['leader_grade'], 'TL = SGT (E-5)');
        self::assertSame([6, 6], $us['groupe']['leader_grade'], 'SL = SSG (E-6)');
        self::assertSame([10, 10], $us['section']['leader_grade'], 'PL = 2LT/1LT (OF-1)');
        self::assertSame([7, 7], $us['section']['deputy_grade'], 'PSG = SFC (E-7)');
        self::assertSame([11, 11], $us['compagnie']['leader_grade'], 'CO = CPT');
        self::assertSame([13, 13], $us['bataillon']['leader_grade'], 'BN = LTC');
        self::assertSame([8, 9], $us['groupe']['strength']);
        self::assertSame([30, 42], $us['section']['strength']);
        $fr = EchelonStandards::all(EchelonStandards::PROFILE_FR);
        self::assertSame([4, 5], $fr['equipe']['leader_grade'], 'chef d’équipe = caporal-chef / sergent');
        self::assertSame([5, 6], $fr['groupe']['leader_grade'], 'chef de groupe = sergent / sergent-chef');
        self::assertSame([7, 10], $fr['section']['leader_grade'], 'chef de section = adjudant à lieutenant');
        self::assertSame([11, 11], $fr['compagnie']['leader_grade'], 'commandant d’unité = capitaine');
        foreach ([$us, $fr] as $set) {
            foreach ($set as $def) {
                self::assertNotSame([], $def['sources'], $def['key'] . ' : source documentée');
            }
        }
        self::assertSame('section', EchelonStandards::standardKey('peloton'));
        self::assertSame('compagnie', EchelonStandards::standardKey('escadron'));
        self::assertNull(EchelonStandards::standardKey('division'));
    }

    public function testUsSquadAtStrengthWithStaffSergeantHasNoGap(): void
    {
        $r = self::evalOrg(self::usSquad('OR-6'));
        self::assertSame([], self::codes($r, 'unit', 10));
        self::assertSame(OrgAdequacyEngine::STATUS_OK, $r['people'][1]['status']);
        // Adjoint de fait : le team leader le plus ancien d’une fire team.
        self::assertSame('subunit', $r['units'][10]['deputy_source']);
        self::assertSame(2, $r['units'][10]['deputy_id']);
        self::assertSame(OrgAdequacyEngine::PLACE_LEADER, $r['people'][2]['place']);
        self::assertSame(1, $r['people'][2]['chief_id']);
        self::assertSame(2, $r['people'][11]['chief_id']);
        self::assertSame(OrgAdequacyEngine::PLACE_MEMBER, $r['people'][11]['place']);
    }

    public function testUsSquadLedByCorporalIsCommandUnderCapacityWithCandidates(): void
    {
        $r = self::evalOrg(self::usSquad('OR-4'));
        self::assertContains('leader_grade_below', self::codes($r, 'unit', 10));
        self::assertSame(OrgAdequacyEngine::STATUS_UNDER, $r['people'][1]['status']);
        $issue = $r['issues'][$r['people'][1]['issues'][0]];
        self::assertSame('warn', $issue['tone']);
        self::assertSame('Sous-capacité de commandement', $issue['title']);
        self::assertSame('Squad leader : poste tenu par Squad Lead (OR-4) ; Staff Sergeant (OR-6) attendu pour commander 8–9 personnes.', $issue['text']);
        self::assertSame([30], $issue['candidates'], 'le staff sergeant disponible est proposé');
        self::assertSame(['advancement', 'reassign'], array_column($issue['actions'], 'key'));
    }

    public function testFrenchGroupLedByCorporalAndSergeant(): void
    {
        $base = self::usSquad('OR-3');
        $r = self::evalOrg($base, EchelonStandards::PROFILE_FR);
        self::assertContains('leader_grade_below', self::codes($r, 'unit', 10));
        self::assertStringStartsWith('Chef de groupe : poste tenu par', $r['issues'][$r['people'][1]['issues'][0]]['text']);
        $base['people'][1] = self::person('Sergent Martin', 'OR-5', 'Sergent');
        $r = self::evalOrg($base, EchelonStandards::PROFILE_FR);
        self::assertNotContains('leader_grade_below', self::codes($r, 'unit', 10));
        // Chef d’équipe FR : caporal-chef ou sergent (OR-4 à OR-5) ; sergent accepté.
        self::assertSame([], array_values(array_intersect(['leader_grade_below', 'leader_overqualified'], self::codes($r, 'unit', 20))));
    }

    public function testCaptainLeadingAFireTeamIsOverqualified(): void
    {
        $base = self::usSquad('OR-6');
        $base['people'][2] = self::person('Captain Doe', 'OF-2', 'Captain');
        $base['people'][12] = self::person('Major Roe', 'OF-3', 'Major');
        $r = self::evalOrg($base);
        self::assertContains('leader_overqualified', self::codes($r, 'person', 2));
        self::assertSame(OrgAdequacyEngine::STATUS_OVER, $r['people'][2]['status']);
        self::assertContains('member_overqualified', self::codes($r, 'person', 12));
        $text = $r['issues'][$r['people'][12]['issues'][0]]['text'];
        self::assertStringContainsString('Membre de Alpha : Major Roe (Major)', $text);
        self::assertStringContainsString('Sergeant (OR-5)', $text);
    }

    public function testPlatoonWithoutPlatoonSergeantMissesItsDeputy(): void
    {
        $data = [
            'units' => [['id' => 1, 'parent_id' => 0, 'name' => '1st Platoon', 'commander_user_id' => 5, 'echelon' => 'section']],
            'members_by_unit' => [1 => [5]],
            'primary_unit' => [5 => 1],
            'people' => [5 => self::person('LT Smith', 'OF-1'), 6 => self::person('SFC Jones', 'OR-7')],
        ];
        $r = self::evalOrg($data);
        self::assertContains('deputy_missing', self::codes($r, 'unit', 1));
        $issue = $r['issues'][array_search('deputy_missing', self::codes($r), true)];
        self::assertSame([6], $issue['candidates']);
        self::assertSame('deputy', $issue['actions'][0]['key']);

        $data['deputies'] = [1 => [6]];
        $r = self::evalOrg($data);
        self::assertNotContains('deputy_missing', self::codes($r, 'unit', 1));
        self::assertSame(6, $r['units'][1]['deputy_id']);
        self::assertSame(OrgAdequacyEngine::PLACE_DEPUTY, $r['people'][6]['place']);
        self::assertSame(6, $r['people'][5]['deputy_id'], 'le chef voit son adjoint');
        // Adjoint sous-gradé : sous-capacité de l’adjoint.
        $data['people'][6] = self::person('SGT Low', 'OR-5');
        $r = self::evalOrg($data);
        self::assertContains('deputy_grade_below', self::codes($r, 'person', 6));
    }

    public function testPeopleWithoutPlaceOrChief(): void
    {
        $data = [
            'units' => [['id' => 1, 'parent_id' => 0, 'name' => 'Groupe Bleu', 'commander_user_id' => 0, 'echelon' => 'groupe']],
            'members_by_unit' => [1 => [7]],
            'primary_unit' => [7 => 1],
            'people' => [7 => self::person('Membre A', 'OR-3'), 8 => self::person('Nouveau', null)],
        ];
        $r = self::evalOrg($data, EchelonStandards::PROFILE_FR);
        self::assertSame(OrgAdequacyEngine::PLACE_NONE, $r['people'][8]['place']);
        self::assertSame(['without_place'], self::codes($r, 'person', 8));
        self::assertSame(OrgAdequacyEngine::STATUS_NO_PLACE, $r['people'][8]['status']);
        self::assertSame(['without_chief'], self::codes($r, 'person', 7));
        self::assertContains('leader_vacant', self::codes($r, 'unit', 1));
        self::assertSame(1, $r['summary']['no_place']);
        self::assertSame(1, $r['summary']['no_chief']);
        self::assertSame(1, $r['summary']['vacant']);
    }

    public function testStrengthAndMissingSubUnits(): void
    {
        $data = [
            'units' => [
                ['id' => 1, 'parent_id' => 0, 'name' => 'Squad', 'commander_user_id' => 1, 'echelon' => 'groupe'],
                ['id' => 2, 'parent_id' => 0, 'name' => 'Team', 'commander_user_id' => 2, 'echelon' => 'equipe'],
            ],
            'members_by_unit' => [1 => [1, 3, 4], 2 => [2, 5, 6, 7, 8, 9]],
            'primary_unit' => [1 => 1, 3 => 1, 4 => 1, 2 => 2, 5 => 2, 6 => 2, 7 => 2, 8 => 2, 9 => 2],
            'people' => array_fill_keys(range(1, 9), self::person('X', 'OR-6')),
        ];
        $data['people'][2] = self::person('TL', 'OR-5');
        $r = self::evalOrg($data);
        self::assertContains('under_strength', self::codes($r, 'unit', 1));
        self::assertContains('subunits_missing', self::codes($r, 'unit', 1));
        self::assertContains('over_strength', self::codes($r, 'unit', 2));
        $texts = array_column($r['issues'], 'text');
        self::assertContains('Effectif : 3 pour un échelon Squad, 8–9 attendus ; l’unité reste utilisable avec moins de capacité.', $texts);
        self::assertContains('Sous-unités : 0 sur 2 attendues (Fire team).', $texts);
    }

    public function testLeaderWithoutGradeAndUnknownEchelon(): void
    {
        $data = [
            'units' => [
                ['id' => 1, 'parent_id' => 0, 'name' => 'PC', 'commander_user_id' => 1, 'echelon' => ''],
                ['id' => 2, 'parent_id' => 1, 'name' => 'Équipe 1', 'commander_user_id' => 2, 'echelon' => 'equipe'],
            ],
            'members_by_unit' => [1 => [1], 2 => [2, 3, 4]],
            'primary_unit' => [1 => 1, 2 => 2, 3 => 2, 4 => 2],
            'people' => [1 => self::person('Chef', 'OF-2'), 2 => self::person('Sans grade', null), 3 => self::person('A', 'OR-2'), 4 => self::person('B', 'OR-2')],
        ];
        $r = self::evalOrg($data, EchelonStandards::PROFILE_FR);
        self::assertContains('echelon_unknown', self::codes($r, 'unit', 1));
        self::assertContains('leader_grade_unknown', self::codes($r, 'person', 2));
        self::assertSame(OrgAdequacyEngine::STATUS_UNKNOWN, $r['people'][2]['status']);
    }

    public function testCommunitySettingsOverrideDefaultsAndDisableRules(): void
    {
        $base = self::usSquad('OR-4');
        // Communauté qui accepte un caporal comme squad leader.
        $r = self::evalOrg($base, EchelonStandards::PROFILE_US, ['echelons' => ['groupe' => ['leader_grade_min' => 'OR-4']]]);
        self::assertNotContains('leader_grade_below', self::codes($r, 'unit', 10));
        // Règle désactivée.
        $r = self::evalOrg($base, EchelonStandards::PROFILE_US, ['rules' => ['grade_below' => false]]);
        self::assertNotContains('leader_grade_below', self::codes($r));
        // Échelon désactivé : plus aucune attente pour les squads.
        $r = self::evalOrg($base, EchelonStandards::PROFILE_US, ['echelons' => ['groupe' => ['enabled' => '0']]]);
        self::assertSame([], self::codes($r, 'unit', 10));
        // Contrôle désactivé.
        $r = self::evalOrg($base, EchelonStandards::PROFILE_US, ['enabled' => false]);
        self::assertSame([], $r['issues']);
        self::assertFalse($r['enabled']);
        // Effectif réglé.
        $r = self::evalOrg($base, EchelonStandards::PROFILE_US, ['echelons' => ['groupe' => ['strength_min' => '10', 'strength_max' => '12']]]);
        self::assertContains('under_strength', self::codes($r, 'unit', 10));
    }

    public function testCommandQualificationsAndExpectedJob(): void
    {
        $base = self::usSquad('OR-6');
        $base['qualification_labels'] = [41 => 'ALC', 42 => 'BOLC'];
        $base['job_role_labels'] = [7 => 'Squad leader'];
        $base['qualifications'] = [2 => [42]];
        $settings = ['echelons' => [
            'groupe' => ['qualification_ids' => [41], 'job_role_ids' => [7]],
            'section' => ['qualification_ids' => [42]],
        ]];
        $r = self::evalOrg($base, EchelonStandards::PROFILE_US, $settings);
        $codes = self::codes($r, 'person', 1);
        self::assertContains('leader_qualification_missing', $codes);
        self::assertContains('leader_function_missing', $codes);
        // Le team leader Alpha détient la qualification d’un chef de platoon : deux échelons au-dessus.
        self::assertContains('qualified_above', self::codes($r, 'person', 2));
        $base['qualifications'][1] = [41];
        $base['job_roles'] = [1 => [7]];
        $r = self::evalOrg($base, EchelonStandards::PROFILE_US, $settings);
        self::assertSame([], array_values(array_intersect(['leader_qualification_missing', 'leader_function_missing'], self::codes($r, 'person', 1))));
    }

    public function testEvaluationIsDeterministic(): void
    {
        $a = self::evalOrg(self::usSquad('OR-4'));
        $b = self::evalOrg(self::usSquad('OR-4'));
        self::assertSame($a, $b);
    }

    public function testSettingsSanitizeKeepsOnlyKnownValues(): void
    {
        $s = OrgAdequacySettings::sanitize([
            'profile' => 'xx',
            'overqualification_margin' => 99,
            'rules' => ['strength' => '0', 'bogus' => '1'],
            'echelons' => ['groupe' => ['leader_grade_min' => 'E-5', 'strength_min' => 'abc', 'qualification_ids' => ['3', '0', 'x']], 'division' => ['enabled' => '0']],
        ]);
        self::assertSame('auto', $s['profile']);
        self::assertSame(6, $s['overqualification_margin']);
        self::assertFalse($s['rules']['strength']);
        self::assertArrayNotHasKey('bogus', $s['rules']);
        self::assertSame(['leader_grade_min' => 'OR-5', 'qualification_ids' => [3]], $s['echelons']['groupe']);
        self::assertArrayNotHasKey('division', $s['echelons']);
        self::assertSame('us', OrgAdequacySettings::resolveProfile($s, 'us'));
        self::assertSame('fr', OrgAdequacySettings::resolveProfile($s, 'fr'));
        self::assertSame('us', OrgAdequacySettings::resolveProfile($s, 'both', 'US_CLASSIC'));
        self::assertSame('fr', OrgAdequacySettings::resolveProfile(['profile' => 'fr'], 'us'));
    }
}
