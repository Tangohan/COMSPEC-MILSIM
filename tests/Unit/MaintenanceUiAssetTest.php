<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\MaintenanceMarkdown;
use App\Support\MaintenanceMessages;
use PHPUnit\Framework\TestCase;

final class MaintenanceUiAssetTest extends TestCase
{
    public function testPublicMaintenancePageUsesCalmAthenaLayoutAndHumanCopy(): void
    {
        $root = dirname(__DIR__, 2);
        $view = (string) file_get_contents($root . '/views/errors/maintenance.php');

        $html = $this->render(['lang' => 'fr']);
        self::assertStringContainsString('Nous revenons bientôt', $html);
        self::assertStringContainsString('Une maintenance est en cours sur Athena.', $html);
        self::assertStringContainsString('Vos données restent en sécurité', $html);
        self::assertStringContainsString('Réessayer', $html);
        self::assertStringContainsString('Athena', $view);
        self::assertStringContainsString('hero-1-1920.webp', $html);
        self::assertStringContainsString('MaintenanceMarkdown', $view);
        self::assertStringContainsString('linear-gradient(180deg, transparent 0%', $view);
        self::assertStringNotContainsString('Instrument Serif', $view);
        self::assertStringNotContainsString('class="brand"', $view);
        self::assertStringNotContainsString('Athena<em>', $view);
        self::assertStringNotContainsString('bar__mark', $view);
        self::assertStringNotContainsString('letter-spacing: 0.28em', $view);
        self::assertStringNotContainsString('Space Mono', $view);
        self::assertStringNotContainsString('PORTAIL OPÉRATIONNEL', $view);
        self::assertStringNotContainsString('classification', $view);
        self::assertStringNotContainsString('ATH-', $view);
        self::assertStringNotContainsString('Code maintenance', $view);
        self::assertStringNotContainsString('endpoint', $view);
        self::assertStringNotContainsString('JSON', $view);
        self::assertStringNotContainsString('les API', $view);
        self::assertStringNotContainsString('slug', strtolower($view));
    }

    public function testStandalonePageLocalizesCopyActionsAndStatusWithoutLocaleMiddleware(): void
    {
        $html = $this->render(['lang' => 'en'], [
            'uiVariant' => 'status',
            'endsAt' => '2026-11-17 18:00:00',
            'loggedOut' => true,
            'adminLoginUrl' => '/login',
        ]);

        self::assertStringContainsString('<html lang="en">', $html);
        self::assertStringContainsString('We’ll be back soon', $html);
        self::assertStringContainsString('Athena is undergoing maintenance.', $html);
        self::assertStringContainsString('Try again', $html);
        self::assertStringContainsString('No action is needed on your part.', $html);
        self::assertStringContainsString('Estimated return: 17 Nov 2026, 18:00.', $html);
        self::assertStringContainsString('Service status', $html);
        self::assertStringContainsString('Administrator sign-in', $html);
        self::assertStringContainsString('You have been signed out', $html);
        self::assertStringNotContainsString('Réessayer', $html);
    }

    public function testEveryPresetCanBeSavedInEitherLanguageAndRenderedInTheOther(): void
    {
        $fr = MaintenanceMessages::presets('fr');
        $en = MaintenanceMessages::presets('en');
        self::assertCount(6, $fr);
        self::assertSame(array_keys($fr), array_keys($en));
        foreach ($fr as $key => $preset) {
            self::assertSame(
                ['title' => $en[$key]['title'], 'message' => $en[$key]['message']],
                MaintenanceMessages::resolve($preset['title'], $preset['message'], 'en'),
                $key
            );
            self::assertSame(
                ['title' => $preset['title'], 'message' => $preset['message']],
                MaintenanceMessages::resolve($en[$key]['title'], $en[$key]['message'], 'fr'),
                $key
            );
        }
    }

    public function testExistingStandardMessageIsReplacedWithoutChangingCustomNotices(): void
    {
        $oldMessage = "Athena est momentanément fermé le temps d’une mise à jour. Nous simplifions la gestion des communautés : un accès plus clair par membre, des rôles mieux alignés, des dossiers plus simples à suivre.\n\nVos données restent en sécurité. Vous n’avez rien à faire de votre côté. Pendant cette période, le site et les outils associés restent inaccessibles.\n\nMerci de votre patience, et de la confiance que vous accordez à Athena.";
        $html = $this->render(['lang' => 'en'], ['message' => $oldMessage]);
        self::assertStringContainsString('Athena is undergoing maintenance.', $html);
        self::assertStringNotContainsString('gestion des communautés', $html);

        $custom = "FR\nIntervention sur **les dossiers**.\n\n---\n\nEN\nMaintenance on **records**. <script>alert(1)</script>";
        $resolved = MaintenanceMessages::resolve('Information importante', $custom, 'en');
        self::assertSame(['title' => 'Information importante', 'message' => $custom], $resolved);
        $html = $this->render(['lang' => 'en'], $resolved);
        self::assertStringContainsString('<strong>records</strong>', $html);
        self::assertStringNotContainsString('<script>alert(1)</script>', $html);
    }

    public function testLanguagePreferenceAlsoWorksBeforeApplicationBoot(): void
    {
        self::assertStringContainsString('<html lang="en">', $this->render([], [], ['athena_locale' => 'en']));
        self::assertStringContainsString('<html lang="fr">', $this->render(['lang' => 'fr'], [], ['athena_locale' => 'en']));
        self::assertStringContainsString('<html lang="en">', $this->render([], [], [], 'en-GB,en;q=0.9'));
    }

    public function testPhotosStayStaticWhenAnimationsAreDisabledAndAbsentFromOtherThemes(): void
    {
        $html = $this->render(['lang' => 'fr'], ['uiAnimation' => false]);
        self::assertStringContainsString('hero-1-1920.webp', $html);
        self::assertStringNotContainsString('<script src=', $html);
        self::assertStringNotContainsString('data-background-toggle', $html);

        foreach (['minimal', 'neon'] as $variant) {
            $html = $this->render(['lang' => 'fr'], ['uiVariant' => $variant]);
            self::assertStringNotContainsString('data-maintenance-backgrounds', $html);
            self::assertStringNotContainsString('<script src=', $html);
        }
    }

    private function render(array $query, array $data = [], array $cookies = [], string $acceptLanguage = ''): string
    {
        $oldGet = $_GET;
        $oldCookies = $_COOKIE;
        $oldSession = $_SESSION ?? null;
        $oldAcceptLanguage = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? null;
        $oldLocale = $GLOBALS['__app_locale'] ?? null;
        $oldSiteLang = $GLOBALS['__app_site_lang'] ?? null;
        $_GET = $query;
        $_COOKIE = $cookies;
        $_SESSION = [];
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = $acceptLanguage;
        unset($GLOBALS['__app_locale'], $GLOBALS['__app_site_lang']);
        extract($data, EXTR_SKIP);
        ob_start();
        try {
            require dirname(__DIR__, 2) . '/views/errors/maintenance.php';

            return (string) ob_get_contents();
        } finally {
            ob_end_clean();
            $_GET = $oldGet;
            $_COOKIE = $oldCookies;
            foreach (['__app_locale' => $oldLocale, '__app_site_lang' => $oldSiteLang] as $key => $value) {
                if ($value === null) {
                    unset($GLOBALS[$key]);
                } else {
                    $GLOBALS[$key] = $value;
                }
            }
            if ($oldSession === null) {
                unset($_SESSION);
            } else {
                $_SESSION = $oldSession;
            }
            if ($oldAcceptLanguage === null) {
                unset($_SERVER['HTTP_ACCEPT_LANGUAGE']);
            } else {
                $_SERVER['HTTP_ACCEPT_LANGUAGE'] = $oldAcceptLanguage;
            }
        }
    }

    public function testMaintenanceMarkdownRendersHorizontalRuleAndLanguageLabels(): void
    {
        $html = MaintenanceMarkdown::toHtml("FR\nBonjour le portail.\n\n---\n\nEN\nHello portal.");

        self::assertStringContainsString('<p class="lang">FR</p>', $html);
        self::assertStringContainsString('<p class="lang">EN</p>', $html);
        self::assertStringContainsString('<hr>', $html);
        self::assertStringContainsString('Bonjour le portail.', $html);
        self::assertStringContainsString('Hello portal.', $html);
        self::assertStringNotContainsString('---', $html);
    }

    public function testMaintenanceMarkdownEscapesHtml(): void
    {
        $html = MaintenanceMarkdown::toHtml('Hello <script>alert(1)</script> **world**');

        self::assertStringNotContainsString('<script>', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
        self::assertStringContainsString('<strong>world</strong>', $html);
    }
}
