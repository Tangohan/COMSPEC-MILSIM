<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Session;
use App\Support\AccountSpaceCatalog;
use PHPUnit\Framework\TestCase;

/**
 * Rendu réel des pages « Mon compte » et « Profil et préférences » (vue + bandeau + recherche),
 * avec des données de compte fictives et sans base de données.
 */
final class AccountPagesRenderTest extends TestCase
{
    private ?bool $sessionStarted = null;
    /** @var array<string, mixed> */
    private array $savedSession = [];

    protected function setUp(): void
    {
        $prop = new \ReflectionProperty(Session::class, 'started');
        $this->sessionStarted = $prop->getValue();
        $prop->setValue(null, true);
        $this->savedSession = $_SESSION ?? [];
        $_SESSION = ['_csrf_token' => str_repeat('a', 64)];
        $GLOBALS['__app_locale'] = 'fr';
    }

    protected function tearDown(): void
    {
        (new \ReflectionProperty(Session::class, 'started'))->setValue(null, $this->sessionStarted);
        $_SESSION = $this->savedSession;
        unset($GLOBALS['__app_locale']);
    }

    /** @param array<string, mixed> $vars */
    private function render(string $view, array $vars): string
    {
        $file = base_path('views/' . $view . '.php');
        $render = static function (string $__file, array $__vars): string {
            extract($__vars, EXTR_SKIP);
            ob_start();
            try {
                require $__file;
            } catch (\Throwable $e) {
                ob_end_clean();
                throw $e;
            }

            return (string) ob_get_clean();
        };

        return $render($file, $vars);
    }

    /** @return list<array{key: string, label: string, hint: string, group: string}> */
    private function notifCatalog(): array
    {
        return [
            ['key' => 'new_device_login', 'label' => 'Nouvel appareil ou navigateur', 'hint' => 'Connexion depuis un équipement inconnu.', 'group' => 'Sécurité'],
            ['key' => 'multiple_login_attempts', 'label' => 'Tentatives de connexion', 'hint' => 'Plusieurs échecs.', 'group' => 'Sécurité'],
            ['key' => 'attendance_reminder', 'label' => 'Rappel de présence', 'hint' => 'Avant un événement.', 'group' => 'Événements'],
        ];
    }

    /** @param array<string, mixed> $overrides */
    private function preferencesVars(array $overrides = []): array
    {
        return array_merge([
            'user' => ['id' => 0, 'tenant_id' => 0, 'callsign' => 'Ghost', 'steam_id' => '76561198000000001', 'profile_slug' => 'jean-dupont'],
            'publicName' => 'Ghosty',
            'profile' => ['first_name' => 'Jean', 'last_name' => 'Dupont', 'timezone' => 'Europe/Brussels', 'language' => 'en'],
            'uiPrefs' => ['theme' => 'dark', 'density' => 'compact', 'sidebar_collapsed' => false],
            'extraCallsigns' => ['Ghost-2', '', '', '', ''],
            'extraCallsignSlots' => 5,
            'displaySettings' => ['site_photo_priority' => 'operator'],
            'notifEmailCatalog' => $this->notifCatalog(),
            'notifEmailState' => ['new_device_login' => true, 'multiple_login_attempts' => false, 'attendance_reminder' => true],
            'uiThemeSync' => null,
            'timezoneSuggestions' => ['UTC', 'Europe/Paris'],
            'errors' => [],
            'old' => [],
            'success' => null,
            'error' => null,
            'steamWebConfigured' => false,
            'steamSyncReport' => null,
        ], $overrides);
    }

    public function testOverviewRendersHealthShortcutsActivityAndSearch(): void
    {
        $html = $this->render('account/index', [
            'accountUser' => ['id' => 0, 'tenant_id' => 0, 'callsign' => 'Ghost', 'created_at' => '2025-03-04 10:00:00'],
            'accountProfile' => [],
            'accountSnapshot' => ['email_masked' => 'je•••@example.com', 'email_verified' => true, 'last_login_label' => '01/11/2026 09:12 (Europe/Paris)'],
            'onboardingSnapshot' => [],
            'accountHasPortrait' => false,
            'accountSecurity' => ['second_factor' => 'totp', 'steam_linked' => true, 'devices_active' => 1, 'devices_total' => 2],
            'accountActivity' => [['label' => 'Connexion réussie', 'device' => 'Firefox · Windows', 'ip' => '192.168.x.x', 'when' => 'il y a 2 h', 'tone' => 'ok']],
            'accountDeletionScheduledAt' => null,
            'accountMemberSince' => '2025-03-04 10:00:00',
            'accountYearReview' => null,
            'success' => 'Mot de passe modifié.',
            'error' => null,
        ]);

        foreach (['id="etat-du-compte"', 'id="activite"', 'id="sessions"', 'Réglages fréquents', 'Activité récente', 'Connexion réussie', 'Déconnecter mes autres sessions', '3<small>/4</small>', 'Presque complet', '1 actif sur 2', 'Mot de passe modifié.'] as $needle) {
            self::assertStringContainsString($needle, $html);
        }
        self::assertStringContainsString('aria-current="page"', $html);

        // Recherche : champ, raccourci et index complet (pages + réglages), sans les entrées masquées.
        self::assertStringContainsString('id="account-search-input"', $html);
        self::assertStringContainsString('role="combobox"', $html);
        self::assertStringContainsString('account-search.js', $html);
        foreach (AccountSpaceCatalog::searchEntries(['section-discord', 'charter']) as $entry) {
            self::assertStringContainsString('id="ahs-' . $entry['id'] . '"', $html, $entry['title']);
        }
        self::assertStringNotContainsString('ahs-page-charter', $html, 'Formations indisponibles : charte absente de la recherche.');
        self::assertStringNotContainsString('recruitment-presets', $html);
    }

    public function testPreferencesRenderEveryExistingSetting(): void
    {
        $html = $this->render('account/preferences', $this->preferencesVars());

        // Chaque champ enregistré par le contrôleur est présent dans le formulaire.
        foreach (['first_name', 'last_name', 'public_name', 'callsign', 'profile_slug', 'steam_id', 'timezone'] as $field) {
            self::assertMatchesRegularExpression('/<input[^>]+name="' . $field . '"/', $html, $field);
        }
        self::assertMatchesRegularExpression('/<select[^>]+name="language"/', $html);
        self::assertSame(5, substr_count($html, 'name="extra_callsigns[]"'));
        self::assertSame(4, substr_count($html, 'name="ui_theme"'));
        self::assertSame(2, substr_count($html, 'name="ui_density"'));
        foreach ($this->notifCatalog() as $item) {
            self::assertStringContainsString('name="notif_email[' . $item['key'] . ']"', $html);
        }
        self::assertStringContainsString('name="_csrf_token"', $html);
        self::assertStringNotContainsString('name="ui_sidebar_collapsed"', $html);

        // Valeurs actuelles reprises.
        self::assertStringContainsString('value="Jean"', $html);
        self::assertStringContainsString('value="Ghost-2"', $html);
        self::assertStringContainsString('value="76561198000000001"', $html);
        self::assertMatchesRegularExpression('/value="dark"\s+checked/', $html);
        self::assertMatchesRegularExpression('/value="compact"\s+checked/', $html);
        self::assertMatchesRegularExpression('/value="en"\s+selected/', $html);
        self::assertMatchesRegularExpression('/id="notif_new_device_login" value="1" checked/', $html);
        self::assertDoesNotMatchRegularExpression('/id="notif_multiple_login_attempts" value="1" checked/', $html);
        self::assertStringContainsString('2 sur 3 activées', $html);

        // Sections et ancres de la recherche, barre d'enregistrement.
        foreach (['section-profil', 'section-liaisons', 'section-locale', 'section-interface', 'notifications-email', 'indicatifs-supplementaires', 'ui_density'] as $id) {
            self::assertStringContainsString('id="' . $id . '"', $html);
        }
        self::assertStringContainsString('data-save-state="clean"', $html);
        self::assertStringContainsString('Enregistrer les préférences', $html);
        self::assertStringContainsString('account-preferences.js', $html);
    }

    public function testPreferencesShowSavedAndErrorStatesInTheSaveBar(): void
    {
        $saved = $this->render('account/preferences', $this->preferencesVars(['success' => 'Préférences enregistrées.', 'uiThemeSync' => 'dark']));
        self::assertStringContainsString('data-save-state="saved"', $saved);
        self::assertStringContainsString('athena.bo.theme', $saved);
        self::assertSame(1, substr_count($saved, 'Préférences enregistrées.</span>'), 'Le message apparaît dans la barre, pas en double en haut.');

        $failed = $this->render('account/preferences', $this->preferencesVars([
            'errors' => ['first_name' => ['Le champ prénom est obligatoire.']],
            'old' => ['first_name' => '', 'last_name' => 'Martin', 'callsign' => 'Viper', 'ui_theme' => 'light', 'notif_email' => ['attendance_reminder' => '1']],
        ]));
        self::assertStringContainsString('data-save-state="error"', $failed);
        self::assertStringContainsString('Rien n’a été enregistré', $failed);
        self::assertStringContainsString('href="#first_name"', $failed);
        self::assertStringContainsString('aria-invalid="true"', $failed);
        // Ce qui vient d'être tapé est conservé.
        self::assertStringContainsString('value="Martin"', $failed);
        self::assertStringContainsString('value="Viper"', $failed);
        self::assertMatchesRegularExpression('/value="light"\s+checked/', $failed);
        self::assertDoesNotMatchRegularExpression('/id="notif_new_device_login" value="1" checked/', $failed);
        self::assertMatchesRegularExpression('/id="notif_attendance_reminder" value="1" checked/', $failed);
    }
}
