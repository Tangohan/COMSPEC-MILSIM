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
        self::assertStringContainsString("<?php if (\$assignmentRole !== '' && \$roleEcho): ?>", $v);
        // Le rôle masqué reste lisible au survol et modifiable.
        self::assertStringContainsString('déjà indiqué par l’unité ou l’emploi', $v);
    }
}
