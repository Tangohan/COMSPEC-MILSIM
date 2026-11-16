<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** Tableur des effectifs rangé par unité : l'unité n'est dite qu'une fois (en-tête du groupe), pas à chaque ligne. */
final class EffectifsRosterNoRepeatAssetTest extends TestCase
{
    public function testUnitIsNotRepeatedOnEachRowWhenGroupedByUnit(): void
    {
        $v = str_replace("\r\n", "\n", (string) file_get_contents(dirname(__DIR__, 2) . '/views/admin/effectifs_workspace/roster.php'));
        self::assertStringContainsString("<?php if (\$assignmentPath !== '' && \$groupMode === 'unite'): ?>", $v);
        self::assertStringContainsString('array_pop($gPathParts);', $v);
        // Un rôle qui ne fait que reprendre le nom de l'unité cède la place au rôle lié à l'emploi (RosterEffectiveRole),
        // la valeur inscrite reste lisible au survol et modifiable.
        self::assertStringContainsString('\App\Support\RosterEffectiveRole::resolve(', $v);
        self::assertStringContainsString("'Rôle inscrit : %1'", $v);
        self::assertStringContainsString('data-eff-role="<?= htmlspecialchars($assignmentRole', $v);
    }
}
