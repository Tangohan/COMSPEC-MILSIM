<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Pages du back-office enregistrées sans rechargement (ath-quiet-form.js) : chaque formulaire discret vit dans une
 * zone remplaçable, et aucun n'attend un confirm() (celui-ci empêcherait l'envoi discret).
 */
final class BackOfficeQuietFormsAssetTest extends TestCase
{
    /** @return iterable<string, array{string, int}> */
    public static function pages(): iterable
    {
        yield 'événement' => ['views/admin/organization/event_show.php', 2];
        yield 'session' => ['views/admin/organization/roleplay_game_session_show.php', 1];
        yield 'corrections' => ['views/personnel/corrections_queue.php', 1];
        yield 'qualification' => ['views/admin/organization/qualifications/show.php', 1];
        yield 'recrutement' => ['views/admin/recruitments/show.php', 2];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('pages')]
    public function testQuietFormsHaveAScopeAndNoConfirm(string $view, int $scopes): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 2) . '/' . $view);

        self::assertSame($scopes, substr_count($src, 'data-ath-quiet-scope='));
        preg_match_all('/<form\b[^>]*data-ath-quiet\b[^>]*>/', $src, $forms);
        self::assertNotEmpty($forms[0]);
        foreach ($forms[0] as $form) {
            self::assertStringNotContainsString('confirm(', $form);
            self::assertStringContainsStringIgnoringCase('method="post"', $form);
        }
    }

    public function testCorrectionsScriptSurvivesTheSwap(): void
    {
        $js = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/rh-corrections.js');

        self::assertStringContainsString("'ath:quiet-swap'", $js);
    }
}
