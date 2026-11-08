<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** Une page du portail n'a pas de .bo-shell-row : sa fenêtre restait blanche si tout le contenu de <main> était masqué. */
final class ModalFramePortalPagesTest extends TestCase
{
    public function testOnlyBackOfficePagesHideTheChildrenOfMain(): void
    {
        $css = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/ath-modal-frame.css');

        self::assertStringContainsString('html.ath-in-modal main:has(> .bo-shell-row) > *:not(.bo-shell-row)', $css);
        self::assertStringNotContainsString('html.ath-in-modal main > *', $css);
    }
}
