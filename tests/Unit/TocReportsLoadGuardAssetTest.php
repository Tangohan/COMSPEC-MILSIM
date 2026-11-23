<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Poste TOC web, panneau Rapports : renderPanel() rappelle load() à chaque redessin tant que les rapports ne sont pas
 * arrivés, et load() redessinait le panneau : boucle sans fin, « Maximum call stack size exceeded » dans familyCount.
 * Le premier chargement est maintenant verrouillé (busy) jusqu'à la réponse ou l'échec du réseau.
 */
final class TocReportsLoadGuardAssetTest extends TestCase
{
    public function testFirstLoadIsLockedUntilTheAnswerComes(): void
    {
        $js = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/toc/toc-reports.js');

        self::assertStringContainsString('var busy = false;', $js);
        self::assertStringContainsString('if (busy && !silent) return Promise.resolve();', $js);
        self::assertSame(2, substr_count($js, 'busy = false;'), 'le verrou est levé à la réponse et sur l’échec réseau');
        // Le verrou est posé avant le redessin, sinon renderPanel() relancerait load() depuis refresh().
        self::assertLessThan(
            strpos($js, 'if (st.loading) refresh();'),
            strpos($js, 'busy = !silent && !st.loaded;')
        );
    }
}
