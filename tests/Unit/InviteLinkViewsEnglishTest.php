<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\HtmlTranslator;
use App\Support\I18n\UntranslatedFrenchScanner;
use PHPUnit\Framework\TestCase;

/**
 * Liens d'invitation : chaque page (back-office et page publique), rendue puis traduite comme en anglais, ne doit
 * plus contenir de texte français.
 */
final class InviteLinkViewsEnglishTest extends TestCase
{
    private const ROOT = __DIR__ . '/../..';

    private bool $sessionWasStarted = false;

    private mixed $translator = null;

    protected function setUp(): void
    {
        $started = new \ReflectionProperty(\App\Core\Session::class, 'started');
        $this->sessionWasStarted = (bool) $started->getValue();
        $started->setValue(null, true);
        $_SESSION['_csrf_token'] ??= 'test';
        $this->translator = $GLOBALS['__app_translator'] ?? null;
        $en = new \App\Services\I18n\Translator();
        $en->setLocale('en');
        $GLOBALS['__app_translator'] = $en;
        HtmlTranslator::useDictionary(require self::ROOT . '/lang/en/site_auto.php');
    }

    protected function tearDown(): void
    {
        (new \ReflectionProperty(\App\Core\Session::class, 'started'))->setValue(null, $this->sessionWasStarted);
        $GLOBALS['__app_translator'] = $this->translator;
        HtmlTranslator::reset();
        UntranslatedFrenchScanner::useDictionary(null);
    }

    /** @param array<string, mixed> $vars */
    private function render(string $view, array $vars): string
    {
        return (static function (string $__file, array $__vars): string {
            extract($__vars);
            ob_start();
            require $__file;

            return (string) ob_get_clean();
        })(self::ROOT . '/views/' . $view . '.php', $vars);
    }

    /** @return array<string, mixed> */
    private static function link(string $action): array
    {
        return [
            'id' => 7, 'label' => 'Discord', 'public_code' => 'AbCd1234', 'action' => $action, 'link_action' => $action,
            'status' => 'active', 'is_active' => 1, 'uses_count' => 3, 'max_uses' => 10, 'requires_approval' => 1,
            'expires_at' => '2030-01-01 12:00:00', 'created_at' => '2026-11-01 10:00:00', 'revoked_at' => null,
            'metadata' => ['unit_id' => 5, 'grade_id' => 2, 'registration_mode' => 'standard'],
        ];
    }

    /** @return iterable<string, array{string, array<string, mixed>}> */
    public static function pages(): iterable
    {
        $stats = ['click' => 4, 'signup' => 2, 'application' => 1, 'join' => 1, 'request' => 1];
        $common = [
            'inviteLinkUnits' => [['id' => 5, 'name' => 'Alpha']], 'inviteLinkGrades' => [['id' => 2, 'name' => 'Recrue']],
            'inviteLinkPriorityCodes' => [], 'recruitmentOpenings' => [], 'inviteLinkCanDirectJoin' => true, 'inviteLinkCustomSlug' => true,
        ];
        foreach (['apply', 'join'] as $a) {
            yield "index $a" => ['admin/organization/recruitment_invite_links/index', [
                'inviteLinks' => [self::link($a)], 'inviteLinkStats' => [7 => $stats], 'inviteLinkQuota' => ['max' => 2, 'active' => 1, 'can_create' => true],
            ]];
            yield "show $a" => ['admin/organization/recruitment_invite_links/show', [
                'inviteLink' => self::link($a), 'inviteLinkStats' => $stats, 'inviteLinkSummary' => [],
                'inviteLinkUses' => [['id' => 1, 'event' => 'click', 'created_at' => '2026-11-01 10:00:00', 'user_id' => null]],
            ]];
            yield "edit $a" => ['admin/organization/recruitment_invite_links/edit', ['inviteLink' => self::link($a)] + $common];
            foreach ([false, true] as $logged) {
                yield "landing $a " . ($logged ? 'connecté' : 'visiteur') => ['invite_link/landing', [
                    'actionUrl' => '/i/AbCd1234/rejoindre', 'formUrl' => '/c/tf/candidature', 'loginUrl' => '/connexion', 'registerUrl' => '/inscription',
                    'link' => self::link($a), 'isApply' => $a === 'apply', 'loggedIn' => $logged, 'memberState' => null,
                    'community' => ['name' => 'Task Force', 'slug' => 'tf', 'logo' => null, 'banner' => null, 'game' => 'Arma 3', 'tagline' => '', 'members' => 12, 'public_url' => '/c/tf'],
                ]];
            }
        }
        yield 'create' => ['admin/organization/recruitment_invite_links/create', ['inviteLinkOld' => []] + $common];
        foreach (['expired', 'exhausted', 'revoked', 'deleted', 'unknown'] as $st) {
            yield "landing $st" => ['invite_link/landing', [
                'unusableMessage' => \App\Services\Recruitment\InviteLinkService::unusableMessage($st), 'community' => null, 'link' => [],
                'formUrl' => '', 'loginUrl' => '/connexion', 'registerUrl' => '/inscription', 'actionUrl' => '',
            ]];
        }
        yield 'bandeau candidature' => ['enlistment/_invite_link', ['inviteLinkBanner' => ['community' => 'Task Force', 'label' => 'Discord', 'unit' => 'Alpha', 'code' => 'PRIO']]];
    }

    public function testPublicPageUsesTheHomeTopbarAndFooterAndKeepsTheInvitationInSignInLinks(): void
    {
        $html = $this->render('invite_link/landing', [
            'link' => self::link('apply'), 'isApply' => true, 'loggedIn' => false, 'memberState' => null,
            'actionUrl' => '', 'formUrl' => '/c/tf/candidature',
            'loginUrl' => '/login?redirect=i%2Fabcd1234', 'registerUrl' => '/register?invite=abcd1234',
            'community' => ['name' => 'Task Force', 'slug' => 'tf', 'logo' => '', 'banner' => '', 'game' => '', 'tagline' => '', 'members' => 0, 'public_url' => ''],
        ]);
        self::assertStringContainsString('class="hi-topbar"', $html, 'barre de la page d’accueil');
        self::assertStringContainsString('id="navDrawer"', $html, 'menu latéral de la page d’accueil');
        self::assertStringContainsString('class="psf"', $html, 'pied de page du site public');
        $header = substr($html, 0, (int) strpos($html, '<main'));
        self::assertStringContainsString('href="/login?redirect=i%2Fabcd1234"', $header);
        self::assertStringContainsString('href="/register?invite=abcd1234"', $header);
        self::assertStringNotContainsString('public_portal_auth_frame', (string) file_get_contents(self::ROOT . '/views/invite_link/landing.php'));
        self::assertStringContainsString("require base_path('views/partials/home_topbar.php')", (string) file_get_contents(self::ROOT . '/views/home/index.php'), 'même barre que l’accueil');
    }

    /**
     * @dataProvider pages
     *
     * @param array<string, mixed> $vars
     */
    public function testRenderedPageHasNoFrenchLeftInEnglish(string $view, array $vars): void
    {
        $html = HtmlTranslator::translate($this->render($view, $vars));
        // Sélecteurs de langue (barre et pied de page) : chaque langue garde son propre nom (« Français »), voulu ;
        // la langue de session reste le français dans ce test.
        $html = (string) preg_replace(['#<a[^>]*\blang="fr"[^>]*>.*?</a>#s', '#<details class="psf-lang">.*?</details>#s'], '', $html);
        $left = array_values(array_filter(
            UntranslatedFrenchScanner::viewSegments($html),
            static fn (string $s): bool => !UntranslatedFrenchScanner::isTranslated($s),
        ));
        self::assertSame([], $left, $view);
    }
}
