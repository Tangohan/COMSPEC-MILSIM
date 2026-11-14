<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** « Continuer avec Discord » : garde-fous vérifiés dans les sources (état, PKCE, chemins, clé unique, journaux). */
final class DiscordOAuthLoginTest extends TestCase
{
    private function src(string $rel): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/' . $rel);
    }

    public function testCallbackChecksStateAndUsesPkce(): void
    {
        $c = $this->src('app/Controllers/Web/DiscordLoginController.php');
        self::assertStringContainsString('hash_equals((string) $pending[\'state\'], $state)', $c);
        self::assertStringContainsString("expires_at", $c);
        self::assertStringContainsString('Session::forget(self::SESSION)', $c);
        self::assertStringContainsString("hash('sha256', \$verifier, true)", $c);

        $s = $this->src('app/Services/Integrations/DiscordLinkedRoles.php');
        self::assertStringContainsString("'code_challenge_method'] = 'S256'", $s);
        self::assertStringContainsString("'code_verifier' => \$codeVerifier", $s);
        self::assertStringContainsString("'identify email'", $s);
    }

    public function testRedirectGoesThroughTheSameSitePathWhitelist(): void
    {
        $c = $this->src('app/Controllers/Web/DiscordLoginController.php');
        self::assertStringContainsString('LoginIntendedDestination::suffixFromRedirect', $c);
        self::assertStringContainsString('LoginIntendedDestination::rememberSuffix', $c);
        self::assertStringNotContainsString("redirect(\$request->query", $c);
        self::assertNull(\App\Support\LoginIntendedDestination::suffixFromRedirect('//evil.example/x'));
        self::assertNull(\App\Support\LoginIntendedDestination::suffixFromRedirect('../../etc/passwd'));
    }

    public function testLoginReusesThePasswordCompletionAndNeverLogsInByEmail(): void
    {
        $c = $this->src('app/Controllers/Web/DiscordLoginController.php');
        self::assertStringContainsString('completeVerifiedLogin', $c);
        self::assertStringContainsString('findUserIdByDiscordId', $c);
        self::assertStringNotContainsString('findByEmail', $c);
        self::assertStringNotContainsString('loginUser(', $c);
        // L'e-mail Discord ne sert qu'à préremplir, et seulement s'il est vérifié.
        self::assertStringContainsString("\$me['verified']", $c);

        $a = $this->src('app/Controllers/Auth/AuthController.php');
        $body = substr($a, (int) strpos($a, 'function completeVerifiedLogin'));
        $body = substr($body, 0, (int) strpos($body, 'private function redirectToDashboardAfterLogin'));
        self::assertStringContainsString('loginAllowedForTenantAndClient', $body);
        self::assertStringContainsString('needsChallengeNow', $body);
        self::assertStringContainsString("'active'", $body);
        self::assertStringContainsString('redirectToDashboardAfterLogin', $body);
    }

    public function testMigrationDedupesThenMakesDiscordIdUnique(): void
    {
        $sql = $this->src('migrations/20261114000010_user_discord_links_unique_discord_id.sql');
        self::assertLessThan(strpos($sql, 'ADD UNIQUE KEY'), strpos($sql, 'DELETE older'));
        self::assertStringContainsString('linked_at', $sql);
        self::assertStringContainsString('(discord_user_id)', $sql);
    }

    public function testNoCodeOrTokenIsLogged(): void
    {
        foreach (['app/Controllers/Web/DiscordLoginController.php', 'app/Services/Integrations/DiscordLinkedRoles.php'] as $f) {
            self::assertDoesNotMatchRegularExpression('/error_log|var_dump|print_r|Logger/', $this->src($f), $f);
        }
    }

    public function testRoutesAndButtonsAreWired(): void
    {
        $r = $this->src('routes/web.php');
        self::assertStringContainsString("'/discord/connexion', [\\App\\Controllers\\Web\\DiscordLoginController::class, 'start']", $r);
        self::assertStringContainsString("'/discord/connexion/retour', [\\App\\Controllers\\Web\\DiscordLoginController::class, 'callback']", $r);
        self::assertStringContainsString('discord_button.php', $this->src('views/auth/login.php'));
        self::assertStringContainsString('discord_button.php', $this->src('views/enlistment/_account_gate.php'));
        self::assertStringContainsString('configured()', $this->src('views/partials/auth/discord_button.php'));
    }
}
