<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Account\ProfileWelcomeService as W;
use PHPUnit\Framework\TestCase;

/** Accueil du profil joueur : étapes, saut, reprise, complétion et enregistrement de la progression. */
final class ProfileWelcomeServiceTest extends TestCase
{
    private const ROOT = __DIR__ . '/../..';

    /** @return array<string, bool> */
    private function facts(array $overrides = []): array
    {
        return $overrides + [
            'callsign' => false,
            'discord_available' => true,
            'discord_linked' => false,
            'steam_linked' => false,
            'portrait' => false,
            'portrait_locked' => false,
            'banner_available' => true,
            'banner' => false,
            'second_factor' => false,
            'public_page' => false,
        ];
    }

    /** @return array{started_at: ?string, current: ?string, skipped: list<string>, seen: list<string>, finished_at: ?string} */
    private function fresh(): array
    {
        return W::normalizeState(null);
    }

    /** @param list<array{key: string, status: string}> $steps @return array<string, string> */
    private function statuses(array $steps): array
    {
        return array_column($steps, 'status', 'key');
    }

    public function testAllSevenStepsInOrderForANewPlayer(): void
    {
        $steps = W::steps($this->facts(), $this->fresh());

        self::assertSame(W::ORDER, array_column($steps, 'key'));
        self::assertSame([1, 2, 3, 4, 5, 6, 7], array_column($steps, 'number'));
        self::assertSame(['todo'], array_values(array_unique(array_column($steps, 'status'))));
        self::assertSame(W::STEP_IDENTITY, W::resumeKey($steps, $this->fresh()));
        self::assertSame(0, W::progress($steps)['percent']);
    }

    public function testStepsThatDoNotApplyAreLeftOut(): void
    {
        $steps = W::steps($this->facts([
            'discord_available' => false,
            'banner_available' => false,
            'portrait_locked' => true,
        ]), $this->fresh());
        $keys = array_column($steps, 'key');

        self::assertNotContains(W::STEP_DISCORD, $keys, 'Discord non réglé sur le site : étape absente.');
        self::assertNotContains(W::STEP_BANNER, $keys, 'Couverture indisponible : étape absente.');
        self::assertNotContains(W::STEP_PORTRAIT, $keys, 'Portrait verrouillé sans photo : rien à faire pour le joueur.');
        self::assertSame([1, 2, 3, 4], array_column($steps, 'number'));

        // Déjà relié à Discord alors que la liaison a été fermée depuis : l'étape reste, faite.
        $linked = W::steps($this->facts(['discord_available' => false, 'discord_linked' => true]), $this->fresh());
        self::assertSame('done', $this->statuses($linked)[W::STEP_DISCORD]);
    }

    public function testStepsAreDoneFromTheRealAccountNotFromClicks(): void
    {
        $steps = W::steps($this->facts([
            'callsign' => true,
            'steam_linked' => true,
            'second_factor' => true,
        ]), $this->fresh());
        $status = $this->statuses($steps);

        self::assertSame('done', $status[W::STEP_IDENTITY]);
        self::assertSame('done', $status[W::STEP_STEAM]);
        self::assertSame('done', $status[W::STEP_SECURITY]);
        self::assertSame('todo', $status[W::STEP_PORTRAIT]);
        self::assertSame(['done' => 3, 'skipped' => 0, 'todo' => 4, 'total' => 7, 'percent' => 43], W::progress($steps));
        self::assertSame(W::STEP_DISCORD, W::resumeKey($steps, $this->fresh()));
    }

    public function testSkippingMovesOnAndKeepsTheStepForLater(): void
    {
        $state = W::moveTo($this->fresh(), W::STEP_DISCORD, '2026-11-01 10:00:00');
        $steps = W::steps($this->facts(), $state);
        $next = W::nextKey($steps, W::STEP_DISCORD);
        self::assertSame(W::STEP_STEAM, $next);

        $state = W::skip($state, W::STEP_DISCORD, $next);
        $steps = W::steps($this->facts(), $state);
        self::assertSame('skipped', $this->statuses($steps)[W::STEP_DISCORD]);
        self::assertSame(W::STEP_STEAM, $state['current']);
        self::assertFalse(W::isFinished($steps, $state), 'Une étape passée n’est pas une étape faite.');

        // Skipper deux fois ne duplique rien.
        self::assertSame([W::STEP_DISCORD], W::skip($state, W::STEP_DISCORD, $next)['skipped']);

        // Une étape passée puis faite ailleurs (Profil et préférences) compte comme faite.
        $later = W::steps($this->facts(['discord_linked' => true]), $state);
        self::assertSame('done', $this->statuses($later)[W::STEP_DISCORD]);
    }

    public function testNextPrefersStepsStillToDoThenSkippedOnesThenTheEnd(): void
    {
        $state = $this->fresh();
        $state['skipped'] = [W::STEP_BANNER];
        $facts = $this->facts(['callsign' => true, 'discord_linked' => true, 'steam_linked' => true, 'portrait' => true]);
        $steps = W::steps($facts, $state);

        self::assertSame(W::STEP_SECURITY, W::nextKey($steps, W::STEP_PORTRAIT), 'La bannière passée est sautée au profit d’une étape à faire.');
        self::assertSame(W::END, W::nextKey($steps, W::STEP_PUBLIC_PAGE));

        $allButBanner = W::steps(array_merge($facts, ['second_factor' => true]), W::markSeen($state, W::STEP_PUBLIC_PAGE));
        self::assertSame(W::STEP_BANNER, W::nextKey($allButBanner, W::STEP_IDENTITY), 'Plus rien à faire : on repropose l’étape passée.');
    }

    public function testResumeReopensTheStepLeftInProgress(): void
    {
        $state = W::moveTo($this->fresh(), W::STEP_PORTRAIT, '2026-11-01 10:00:00');
        $steps = W::steps($this->facts(['callsign' => true]), $state);
        self::assertSame(W::STEP_PORTRAIT, W::resumeKey($steps, $state));

        // L'étape en cours a été faite entre-temps : reprise à la première étape encore à faire.
        $steps = W::steps($this->facts(['callsign' => true, 'portrait' => true]), $state);
        self::assertSame(W::STEP_DISCORD, W::resumeKey($steps, $state));

        // Tout est fait ou passé : la reprise ramène d'abord aux étapes passées, puis au récapitulatif.
        $state['skipped'] = [W::STEP_SECURITY];
        $almost = $this->facts(['callsign' => true, 'discord_linked' => true, 'steam_linked' => true, 'portrait' => true, 'banner' => true, 'public_page' => true]);
        self::assertSame(W::STEP_SECURITY, W::resumeKey(W::steps($almost, $state), $state));
        $complete = array_merge($almost, ['second_factor' => true]);
        self::assertSame(W::END, W::resumeKey(W::steps($complete, $state), $state));
    }

    public function testProgressSurvivesBetweenSessionsThroughStoredRows(): void
    {
        $state = W::moveTo($this->fresh(), W::STEP_BANNER, '2026-11-01 09:30:00');
        $state = W::skip($state, W::STEP_DISCORD, W::STEP_BANNER);
        $state = W::markSeen($state, W::STEP_PUBLIC_PAGE);

        $rows = W::stateToRows($state);
        self::assertArrayHasKey('profile_welcome', $rows);
        self::assertArrayHasKey('profile_welcome.skip.discord', $rows);
        self::assertArrayHasKey('profile_welcome.seen.page-publique', $rows);
        self::assertArrayHasKey('profile_welcome.at.banniere', $rows);
        foreach (array_keys($rows) as $key) {
            self::assertLessThanOrEqual(64, strlen($key), 'Clé trop longue pour user_ui_tours.tour_key.');
        }

        // Relecture telle que la base la rend (created_at rempli par MySQL pour les lignes secondaires).
        $stored = [];
        foreach ($rows as $key => $dates) {
            $stored[$key] = ['created_at' => $dates['created_at'] ?? '2026-11-01 09:30:01', 'dismissed_at' => '2026-11-01 09:30:01', 'completed_at' => $dates['completed_at']];
        }
        self::assertSame($state, W::stateFromRows($stored));

        // Rien d'enregistré pour un parcours jamais ouvert.
        self::assertSame([], W::stateToRows($this->fresh()));
        self::assertSame($this->fresh(), W::stateFromRows([]));
    }

    public function testStoredStateIsCleanedOfUnknownValues(): void
    {
        $state = W::normalizeState([
            'started_at' => 'hier',
            'current' => 'admin',
            'skipped' => ['discord', 'discord', 'inconnue', 3],
            'seen' => 'page-publique',
            'finished_at' => '2026-11-01 12:00:00',
        ]);

        self::assertNull($state['started_at']);
        self::assertNull($state['current']);
        self::assertSame([W::STEP_DISCORD], $state['skipped']);
        self::assertSame([], $state['seen']);
        self::assertSame('2026-11-01 12:00:00', $state['finished_at']);
        self::assertSame(W::normalizeState(null), W::stateFromRows(['profile_welcome.skip.inconnue' => [], 'autre.cle' => []]));
    }

    public function testCompletionWhenEverythingIsDoneOrWhenThePlayerFinishes(): void
    {
        $all = $this->facts([
            'callsign' => true, 'discord_linked' => true, 'steam_linked' => true, 'portrait' => true,
            'banner' => true, 'second_factor' => true, 'public_page' => true,
        ]);
        $steps = W::steps($all, $this->fresh());
        self::assertTrue(W::isFinished($steps, $this->fresh()));
        self::assertSame(100, W::progress($steps)['percent']);

        // Fin choisie avec des étapes passées : le rappel disparaît, l'état reste exact.
        $state = W::skip($this->fresh(), W::STEP_SECURITY, W::END);
        $partial = W::steps($this->facts(['callsign' => true]), $state);
        self::assertFalse(W::isFinished($partial, $state));
        $finished = W::finish($state, '2026-11-01 11:00:00');
        self::assertTrue(W::isFinished($partial, $finished));
        self::assertSame(W::END, $finished['current']);
        self::assertSame('2026-11-01 11:00:00', $finished['finished_at']);

        // « Reprendre » depuis Mon compte rouvre le parcours sans perdre les étapes passées.
        $reopened = W::reopen($finished);
        self::assertNull($reopened['finished_at']);
        self::assertSame([W::STEP_SECURITY], $reopened['skipped']);
        self::assertFalse(W::isFinished($partial, $reopened));
    }

    public function testPublicPageCountsOnceLookedAtEvenIfLeftClosed(): void
    {
        $state = W::skip($this->fresh(), W::STEP_PUBLIC_PAGE, W::END);
        $state = W::markSeen($state, W::STEP_PUBLIC_PAGE);

        self::assertSame('done', $this->statuses(W::steps($this->facts(), $state))[W::STEP_PUBLIC_PAGE]);
        self::assertSame([], $state['skipped']);
    }

    public function testOpensByItselfOnlyOnceAndOnlyForNewMembers(): void
    {
        $steps = W::steps($this->facts(), $this->fresh());
        self::assertTrue(W::shouldAutoOpen($steps, $this->fresh(), true));
        self::assertFalse(W::shouldAutoOpen($steps, $this->fresh(), false), 'Membre déjà inscrit : jamais redirigé.');
        self::assertFalse(W::shouldAutoOpen($steps, W::moveTo($this->fresh(), W::STEP_IDENTITY), true), 'Déjà ouvert une fois.');

        $all = W::steps($this->facts([
            'callsign' => true, 'discord_linked' => true, 'steam_linked' => true, 'portrait' => true,
            'banner' => true, 'second_factor' => true, 'public_page' => true,
        ]), $this->fresh());
        self::assertFalse(W::shouldAutoOpen($all, $this->fresh(), true), 'Profil déjà complet : rien à proposer.');
    }

    public function testRoutesUploadsAndReturnsAreWired(): void
    {
        $routes = (string) file_get_contents(self::ROOT . '/routes/web.php');
        foreach ([
            "get('/account/bienvenue'", "post('/account/bienvenue/identite'", "post('/account/bienvenue/continuer'",
            "post('/account/bienvenue/passer'", "post('/account/bienvenue/terminer'", "post('/account/bienvenue/reprendre'",
            "get('/account/bienvenue/aller/{etape}'",
        ] as $route) {
            self::assertStringContainsString($route, $routes);
        }

        $view = (string) file_get_contents(self::ROOT . '/views/account/welcome.php');
        self::assertStringContainsString("action=\"<?= \$h(url('account/portrait')) ?>\"", $view, 'Le portrait passe par le téléversement existant.');
        self::assertStringContainsString("action=\"<?= \$h(url('account/banner')) ?>\"", $view, 'La bannière réutilise la couverture du profil.');
        self::assertSame(2, substr_count($view, 'name="retour" value="bienvenue"'));

        $account = (string) file_get_contents(self::ROOT . '/app/Controllers/Web/AccountController.php');
        self::assertSame(2, substr_count($account, "\$request->input('retour') === 'bienvenue'"));
        self::assertStringContainsString('ProfileWelcomeService::STEP_STEAM', $account, 'Retour de Steam vers l’étape.');
        $discord = (string) file_get_contents(self::ROOT . '/app/Controllers/Web/DiscordLinkedRolesController.php');
        self::assertStringContainsString('ProfileWelcomeService::STEP_DISCORD', $discord, 'Retour de Discord vers l’étape.');

        $index = (string) file_get_contents(self::ROOT . '/views/account/index.php');
        self::assertStringContainsString('Complétez votre profil', $index);
    }
}
