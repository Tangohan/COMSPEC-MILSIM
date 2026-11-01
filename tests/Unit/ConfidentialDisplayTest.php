<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Organization\OrgVisibilityService;
use App\Services\Personnel\UnitReadinessService;
use App\Support\ConfidentialDisplay;
use App\Support\OrbatRosterPayload;
use App\Support\OrgVisibilityCapabilities;
use App\Support\VisibilityLevel;
use PHPUnit\Framework\TestCase;

/**
 * Rendu commun des éléments masqués / anonymisés / restreints, et son application
 * (ORBAT, pages publiques, disponibilité des unités).
 */
final class ConfidentialDisplayTest extends TestCase
{
    public function testEveryKindHasLabelHintAndTone(): void
    {
        foreach (ConfidentialDisplay::kinds() as $kind) {
            self::assertNotSame('', ConfidentialDisplay::label($kind), $kind);
            self::assertGreaterThan(20, mb_strlen(ConfidentialDisplay::hint($kind)), $kind);
            self::assertContains(ConfidentialDisplay::tone($kind), ['hidden', 'anonymized', 'restricted', 'deleted'], $kind);
        }
    }

    public function testCanonicalLabels(): void
    {
        self::assertSame('Unité anonymisée', ConfidentialDisplay::label(ConfidentialDisplay::UNIT_ANONYMIZED));
        self::assertSame('Structure non affichée', ConfidentialDisplay::label(ConfidentialDisplay::UNIT_HIDDEN));
        self::assertSame('Personnel anonymisé', ConfidentialDisplay::label(ConfidentialDisplay::PERSON_ANONYMIZED));
        self::assertSame('Affectation restreinte', ConfidentialDisplay::label(ConfidentialDisplay::ASSIGNMENT_RESTRICTED));
        self::assertSame('Compte anonymisé', ConfidentialDisplay::label(ConfidentialDisplay::ACCOUNT_DELETED));
    }

    public function testPersonLabelPrefersCustomLabel(): void
    {
        self::assertSame('Opérateur', ConfidentialDisplay::personLabel('  Opérateur '));
        self::assertSame('Personnel anonymisé', ConfidentialDisplay::personLabel(''));
        self::assertSame('Personnel anonymisé', ConfidentialDisplay::personLabel(null));
    }

    public function testKindFromLegacyText(): void
    {
        self::assertSame(ConfidentialDisplay::ASSIGNMENT_RESTRICTED, ConfidentialDisplay::kindFromText('Restreinte'));
        self::assertSame(ConfidentialDisplay::UNIT_ANONYMIZED, ConfidentialDisplay::kindFromText('Unité restreinte'));
        self::assertSame(ConfidentialDisplay::ACCOUNT_DELETED, ConfidentialDisplay::kindFromText('Compte supprimé'));
        self::assertSame(ConfidentialDisplay::PERSON_ANONYMIZED, ConfidentialDisplay::kindFromText('PERSONNEL ANONYMISÉ'));
        self::assertNull(ConfidentialDisplay::kindFromText('1re Compagnie'));
        self::assertNull(ConfidentialDisplay::kindFromText(''));
    }

    public function testKindForLevel(): void
    {
        self::assertNull(ConfidentialDisplay::kindForLevel(VisibilityLevel::NORMAL));
        self::assertSame(ConfidentialDisplay::UNIT_HIDDEN, ConfidentialDisplay::kindForLevel('masquée'));
        self::assertSame(ConfidentialDisplay::UNIT_ANONYMIZED, ConfidentialDisplay::kindForLevel(VisibilityLevel::ANONYMIZED));
        self::assertSame(ConfidentialDisplay::UNIT_RESTRICTED, ConfidentialDisplay::kindForLevel(VisibilityLevel::RESTRICTED));
        self::assertSame(ConfidentialDisplay::PERSON_ANONYMIZED, ConfidentialDisplay::kindForLevel(VisibilityLevel::ANONYMIZED, 'personnel'));
        self::assertSame(ConfidentialDisplay::ASSIGNMENT_RESTRICTED, ConfidentialDisplay::kindForLevel(VisibilityLevel::HIDDEN, 'assignment'));
    }

    public function testBadgeHtmlIsAccessibleAndEscaped(): void
    {
        $html = ConfidentialDisplay::badge(ConfidentialDisplay::PERSON_ANONYMIZED, '<b>Op</b>');
        self::assertStringContainsString('class="ath-confid ath-confid--anonymized"', $html);
        self::assertStringContainsString('data-confidential="person_anonymized"', $html);
        self::assertStringContainsString('title="Ce membre occupe', $html);
        self::assertStringContainsString('tabindex="0"', $html);
        self::assertStringContainsString('class="sr-only"', $html);
        self::assertStringContainsString('aria-hidden="true"', $html);
        self::assertStringContainsString('&lt;b&gt;Op&lt;/b&gt;', $html);
        self::assertStringNotContainsString('<b>Op</b>', $html);

        self::assertStringContainsString('ath-confid--sm', ConfidentialDisplay::badge(ConfidentialDisplay::UNIT_HIDDEN, null, true));
    }

    public function testTextRendersBadgeOnlyForPlaceholders(): void
    {
        self::assertStringContainsString('Affectation restreinte', ConfidentialDisplay::text('Restreinte'));
        self::assertStringContainsString('ath-confid', ConfidentialDisplay::text('Restreinte'));
        self::assertSame('Section &lt;A&gt;', ConfidentialDisplay::text('Section <A>'));
        self::assertSame('—', ConfidentialDisplay::text(null));
        self::assertSame('Non affecté', ConfidentialDisplay::text('', 'Non affecté'));
    }

    public function testStaffPayloadExplainsWhyItIsVisible(): void
    {
        self::assertNull(ConfidentialDisplay::staffPayload(VisibilityLevel::NORMAL));
        $p = ConfidentialDisplay::staffPayload(VisibilityLevel::HIDDEN);
        self::assertNotNull($p);
        self::assertSame('Masquée', $p['label']);
        self::assertSame('hidden', $p['tone']);
        self::assertStringStartsWith('Visible pour vous', $p['hint']);
        self::assertSame('', ConfidentialDisplay::levelBadge(VisibilityLevel::NORMAL));
        self::assertStringContainsString('ath-confid--restricted', ConfidentialDisplay::levelBadge(VisibilityLevel::RESTRICTED));
    }

    public function testCatalogMatchesPayloads(): void
    {
        $catalog = ConfidentialDisplay::catalog();
        self::assertSame(ConfidentialDisplay::kinds(), array_keys($catalog));
        self::assertSame(ConfidentialDisplay::payload(ConfidentialDisplay::UNIT_HIDDEN), $catalog[ConfidentialDisplay::UNIT_HIDDEN]);
    }

    public function testEffectiveUnitLevelsPropagateFromParent(): void
    {
        $levels = OrgVisibilityService::effectiveUnitLevels([
            ['id' => 1, 'parent_id' => null, 'visibility_level' => 'hidden'],
            ['id' => 2, 'parent_id' => 1, 'visibility_level' => 'normal', 'visibility_propagate' => 0],
            ['id' => 3, 'parent_id' => null, 'visibility_level' => 'restricted'],
            ['id' => 4, 'parent_id' => 3, 'visibility_level' => 'normal'],
            ['id' => 5, 'parent_id' => 3, 'visibility_level' => 'normal', 'visibility_propagate' => 0],
            ['id' => 6, 'parent_id' => null, 'orbat_mask_mode' => 'anonymize'],
        ]);
        self::assertSame(VisibilityLevel::HIDDEN, $levels[2], 'un parent masqué s’impose toujours');
        self::assertSame(VisibilityLevel::RESTRICTED, $levels[4]);
        self::assertSame(VisibilityLevel::NORMAL, $levels[5], 'dérogation explicite');
        self::assertSame(VisibilityLevel::ANONYMIZED, $levels[6]);
    }

    public function testPublicUnitRow(): void
    {
        self::assertNull(OrgVisibilityService::publicUnitRow(['id' => 1, 'name' => 'Cellule'], VisibilityLevel::HIDDEN));

        $anon = OrgVisibilityService::publicUnitRow([
            'id' => 2, 'name' => 'Détachement X', 'code' => 'DX', 'slug' => 'detachement-x',
            'public_blurb' => 'Texte', 'commander_user_id' => 9, 'badge_media_path' => 'b.png',
        ], VisibilityLevel::ANONYMIZED);
        self::assertNotNull($anon);
        self::assertSame('Unité anonymisée', $anon['name']);
        self::assertSame('', $anon['code']);
        self::assertSame('', $anon['slug']);
        self::assertSame('', $anon['public_blurb']);
        self::assertSame(0, $anon['commander_user_id']);
        self::assertNull($anon['badge_media_path']);

        $restricted = OrgVisibilityService::publicUnitRow(['id' => 3, 'name' => 'Section R', 'commander_user_id' => 4], VisibilityLevel::RESTRICTED);
        self::assertNotNull($restricted);
        self::assertSame('Section R', $restricted['name']);
        self::assertSame(0, $restricted['commander_user_id']);
        self::assertSame('unit_restricted', $restricted['confidential']['kind']);
    }

    public function testRosterForViewer(): void
    {
        $rows = [['user_id' => 1, 'label' => 'Alice'], ['user_id' => 2, 'label' => 'Bob'], ['user_id' => 3, 'label' => 'Chloé']];
        $vis = [
            2 => ['visibility_level' => 'hidden'],
            3 => ['visibility_level' => 'anonymized', 'anonymized_label' => 'Opérateur'],
        ];
        $public = new OrgVisibilityCapabilities();

        $normal = OrgVisibilityService::rosterForViewer($rows, $public, VisibilityLevel::NORMAL, $vis);
        self::assertCount(2, $normal);
        self::assertSame('Alice', $normal[0]['label']);
        self::assertSame('Opérateur', $normal[1]['label']);
        self::assertSame(0, $normal[1]['user_id']);

        $restricted = OrgVisibilityService::rosterForViewer($rows, $public, VisibilityLevel::RESTRICTED, $vis);
        self::assertSame(['Personnel anonymisé', 'Opérateur'], array_column($restricted, 'label'));
        self::assertSame([0, 0], array_column($restricted, 'user_id'));

        $staff = new OrgVisibilityCapabilities();
        $staff->bypassAll = true;
        self::assertCount(3, OrgVisibilityService::rosterForViewer($rows, $staff, VisibilityLevel::RESTRICTED, $vis));
    }

    public function testRedactPeopleRemovesBilletHoldersOnRedactedUnit(): void
    {
        $node = [
            'unitId' => 4,
            'label' => 'Section R',
            'leader' => 'Cne Martin',
            'commanderUserId' => 0,
            'viewerNamesRedacted' => true,
            'isRestrictedUnit' => true,
            'billets' => [['id' => 1, 'holders' => [['id' => 7, 'user_id' => 12, 'label' => 'Cne Martin']]]],
            'derivedCommand' => [['user_id' => 12, 'label' => 'Cne Martin']],
            'commander' => ['userId' => 12, 'label' => 'Cne Martin', 'billetId' => 1],
            'deputy' => null,
            'children' => [],
        ];
        $out = OrbatRosterPayload::redactPeopleForViewer($node, new OrgVisibilityCapabilities(), []);
        $holder = $out['billets'][0]['holders'][0];
        self::assertSame(0, $holder['user_id']);
        self::assertSame('Personnel anonymisé', $holder['label']);
        self::assertSame(0, $out['commander']['userId']);
        self::assertSame('Personnel anonymisé', $out['leader']);
        self::assertSame('Personnel anonymisé', $out['derivedCommand'][0]['label']);
        self::assertStringNotContainsString('Martin', (string) json_encode($out, JSON_UNESCAPED_UNICODE));
    }

    public function testRedactPeopleAnonymizesPersonOnNormalUnit(): void
    {
        $node = [
            'unitId' => 2,
            'label' => '1re Compagnie',
            'leader' => 'Lt Durand',
            'commanderUserId' => 5,
            'billets' => [['id' => 1, 'holders' => [['id' => 3, 'user_id' => 5, 'label' => 'Lt Durand'], ['id' => 4, 'user_id' => 6, 'label' => 'Sgt Petit']]]],
            'children' => [],
        ];
        $vis = [5 => ['visibility_level' => 'anonymized', 'anonymized_label' => 'Chef de section']];
        $out = OrbatRosterPayload::redactPeopleForViewer($node, new OrgVisibilityCapabilities(), $vis);
        self::assertSame('Chef de section', $out['leader']);
        self::assertSame(0, $out['commanderUserId']);
        self::assertSame('Chef de section', $out['billets'][0]['holders'][0]['label']);
        self::assertSame('Sgt Petit', $out['billets'][0]['holders'][1]['label']);
        self::assertSame(6, $out['billets'][0]['holders'][1]['user_id']);

        $staff = new OrgVisibilityCapabilities();
        $staff->viewRestrictedPersonnel = true;
        $seen = OrbatRosterPayload::redactPeopleForViewer($node, $staff, $vis);
        self::assertSame('Lt Durand', $seen['leader']);
    }

    public function testReadinessVisibility(): void
    {
        $units = [
            ['id' => 1, 'name' => 'Régiment', 'parent_id' => null, 'visibility_level' => 'normal'],
            ['id' => 2, 'name' => 'Cellule noire', 'parent_id' => 1, 'visibility_level' => 'hidden'],
            ['id' => 3, 'name' => 'Sous-cellule', 'parent_id' => 2, 'visibility_level' => 'normal'],
            ['id' => 4, 'name' => 'Détachement X', 'parent_id' => 1, 'visibility_level' => 'anonymized'],
        ];
        $scores = array_map(static fn (array $u): array => ['unit_id' => $u['id'], 'name' => $u['name'], 'score' => 50], $units);

        $member = UnitReadinessService::applyVisibility($scores, $units, new OrgVisibilityCapabilities());
        self::assertSame([1, 4], array_column($member, 'unit_id'));
        self::assertSame('Unité anonymisée', $member[1]['name']);
        self::assertTrue($member[1]['anonymized']);
        self::assertSame('', $member[1]['parent_name']);

        $staff = new OrgVisibilityCapabilities();
        $staff->viewHiddenUnits = true;
        $all = UnitReadinessService::applyVisibility($scores, $units, $staff);
        self::assertCount(4, $all);
        self::assertSame('Cellule noire', $all[2]['parent_name']);
        self::assertSame('Masquée', $all[1]['confidential']['label']);
    }
}
