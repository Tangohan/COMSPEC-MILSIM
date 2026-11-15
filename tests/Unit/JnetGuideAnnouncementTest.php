<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\I18n\UntranslatedFrenchScanner;
use App\Support\JnetAnnouncement;
use PHPUnit\Framework\TestCase;

/**
 * Guide de l'extranet JNET, fenêtre « JNET arrive » et bandeau du tableau de bord : contenu du guide et liens, règles
 * d'affichage de l'annonce (période, une seule fois, accès à JNET), accessibilité de la fenêtre, masquage du bandeau,
 * textes anglais, et vues qui importent les classes qu'elles appellent (une classe sans « use » a déjà fait une erreur 500).
 */
final class JnetGuideAnnouncementTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 2);
    }

    private function read(string $rel): string
    {
        $path = $this->root() . '/' . $rel;
        self::assertFileExists($path);

        return (string) file_get_contents($path);
    }

    private const NEW_VIEWS = [
        'views/jnet/guide.php',
        'views/partials/jnet_announce_modal.php',
        'views/partials/jnet_dashboard_banner.php',
    ];

    // —— Guide ——

    public function testGuideHasTheNineChaptersWithTheirAnchors(): void
    {
        $view = $this->read('views/jnet/guide.php');
        $chapters = ['cest-quoi', 'premiers-pas', 'espace-unite', 'personnaliser', 'liaisons', 'outils', 'securite', 'faq', 'glossaire'];
        foreach ($chapters as $id) {
            self::assertStringContainsString("'" . $id . "' =>", $view, 'sommaire : ' . $id);
            self::assertStringContainsString('<section class="jg-sec" id="' . $id . '"', $view, 'ancre : ' . $id);
        }
        self::assertSame(count($chapters), substr_count($view, '<section class="jg-sec"'));
        // Sous-rubriques de l'espace d'unité : un onglet du sommaire, une ancre dans la page.
        foreach (['accueil', 'echanges', 'effectif', 'taches', 'pages', 'photos', 'documents', 'operations'] as $tab) {
            self::assertStringContainsString("'onglet-" . $tab . "' =>", $view);
            self::assertStringContainsString('id="onglet-' . $tab . '"', $view);
        }
        // Le fond du guide : les notions que les membres doivent retrouver.
        foreach (['Une unité. Un espace.', 'Accuser réception', 'Relayer aux sous-unités', 'Faire remonter', 'Prise en compte obligatoire',
            'Relais automatique aux sous-unités', 'Publication réservée à', 'Lecture seule', 'Chef de l’unité', 'Demander l’affectation',
            'Rattachement temporaire', 'Lier à…', 'Ctrl + K', 'engagement de confidentialité', 'journalisé', 'Glossaire', 'ORBAT'] as $needle) {
            self::assertStringContainsString($needle, $view, $needle);
        }
        // Chaque ancre du sommaire existe dans la page.
        preg_match_all('~data-jg-toc="<\?= \$h\(\$(?:id|tabId)\) \?>"~', $view, $m);
        self::assertGreaterThan(0, count($m[0]));
    }

    public function testGuideDoesNotPromiseUnitsOrAddressesThatAreNotInProduction(): void
    {
        $view = $this->read('views/jnet/guide.php');
        // Les rubriques personnelles du hub sont nommées sans adresse : aucune adresse « ma-situation » ni « jnet/moi ».
        self::assertStringNotContainsString('ma-situation', $view);
        self::assertStringNotContainsString('jnet/moi', $view);
        foreach (['Mon profil', 'Mon ATAK', 'Mes données', 'Mon service'] as $rubric) {
            self::assertStringContainsString('<dt>' . $rubric . '</dt>', $view);
        }
    }

    public function testEveryGuideLinkPointsToARealRoute(): void
    {
        $view = $this->read('views/jnet/guide.php');
        $routes = $this->read('routes/web.php');
        preg_match_all('~\$u\(\'([^\']+)\'\)~', $view, $m);
        self::assertNotEmpty($m[1]);
        $paths = [];
        foreach ($routes === '' ? [] : explode("\n", $routes) as $line) {
            if (preg_match('~\$router->get\(\'(/[^\']*)\'~', $line, $r) === 1) {
                $paths[] = $r[1];
            }
        }
        foreach (array_unique($m[1]) as $link) {
            $path = '/' . trim((string) preg_replace('~[?#].*$~', '', $link), '/');
            $found = false;
            foreach ($paths as $pattern) {
                $re = '~^' . preg_replace('~\\\\\{[a-z]+\\\\\}~i', '[^/]+', preg_quote($pattern, '~')) . '$~';
                if (preg_match($re, $path) === 1) {
                    $found = true;
                    break;
                }
            }
            self::assertTrue($found, 'lien du guide sans route : ' . $link);
        }
        self::assertStringContainsString("'/jnet/guide', [JnetPortalController::class, 'guide']", $routes);
        $controller = $this->read('app/Controllers/Web/JnetPortalController.php');
        self::assertStringContainsString('public function guide(', $controller);
        self::assertStringContainsString("'jnet_guide.css'", $controller);
    }

    public function testGuideIsReachableFromTheHubTheShellAndTheSiteGuide(): void
    {
        self::assertStringContainsString("url('jnet/guide')", $this->read('views/jnet/hub.php'));
        self::assertStringContainsString("url('jnet/guide')", $this->read('views/jnet/_page.php'));
        self::assertStringContainsString("url('jnet/guide')", $this->read('views/jnet/unit_space.php'));
        self::assertStringContainsString("url('jnet/guide')", $this->read('views/documentation/site/index.php'));
    }

    public function testGuideScriptFiltersAndHoldsNoFrenchText(): void
    {
        $js = $this->read('public/assets/js/jnet_guide.js');
        self::assertStringContainsString('data-jg-filter', $js);
        self::assertStringContainsString('IntersectionObserver', $js);
        self::assertStringContainsString("normalize('NFD')", $js);
        self::assertSame([], UntranslatedFrenchScanner::missingInFile($this->root() . '/public/assets/js/jnet_guide.js'));
    }

    // —— Fenêtre « JNET arrive » ——

    public function testModalPeriodHasABoundedStartAndEnd(): void
    {
        $start = strtotime(JnetAnnouncement::MODAL_STARTS_AT);
        $end = strtotime(JnetAnnouncement::MODAL_ENDS_AT);
        self::assertNotFalse($start);
        self::assertNotFalse($end);
        self::assertLessThan($end, $start);
        self::assertFalse(JnetAnnouncement::isModalPeriod($start - 1));
        self::assertTrue(JnetAnnouncement::isModalPeriod($start));
        self::assertTrue(JnetAnnouncement::isModalPeriod($end));
        self::assertFalse(JnetAnnouncement::isModalPeriod($end + 1));
        // Dates illisibles : jamais affichée.
        self::assertFalse(JnetAnnouncement::isModalPeriod(time(), 'pas une date', '2030-01-01'));
    }

    public function testModalShowsOnlyOnceToThoseWhoHaveJnet(): void
    {
        $in = strtotime(JnetAnnouncement::MODAL_STARTS_AT) + 3600;
        $base = ['loggedIn' => true, 'hasJnet' => true, 'seen' => false, 'now' => $in, 'path' => 'dashboard'];
        self::assertTrue(JnetAnnouncement::shouldShowModal($base));
        // Déjà vue : plus jamais.
        self::assertFalse(JnetAnnouncement::shouldShowModal(array_merge($base, ['seen' => true])));
        // Sans accès à JNET (sans organisation, ou profil qui le retire) : jamais.
        self::assertFalse(JnetAnnouncement::shouldShowModal(array_merge($base, ['hasJnet' => false])));
        // Visiteur non connecté.
        self::assertFalse(JnetAnnouncement::shouldShowModal(array_merge($base, ['loggedIn' => false])));
        // Une autre fenêtre passe d'abord ; écrans d'administration : rien.
        self::assertFalse(JnetAnnouncement::shouldShowModal(array_merge($base, ['otherModal' => true])));
        self::assertFalse(JnetAnnouncement::shouldShowModal(array_merge($base, ['shell' => true])));
        // Hors période.
        self::assertFalse(JnetAnnouncement::shouldShowModal(array_merge($base, ['now' => strtotime(JnetAnnouncement::MODAL_STARTS_AT) - 10])));
        self::assertFalse(JnetAnnouncement::shouldShowModal(array_merge($base, ['now' => strtotime(JnetAnnouncement::MODAL_ENDS_AT) + 10])));
    }

    public function testModalNeverShowsOnLoginSecurityApiOrJnetPages(): void
    {
        foreach (['login', 'login/otp', 'logout', 'register', 'forgot-password', 'reset-password', 'verify-email', 'login/choisir-espace',
            'account/security', 'account/security/devices', 'account/password', 'account/mail', 'account/bienvenue',
            'api/ui-tours/dismiss', 'api/jnet/annonce/fenetre/vu', 'jnet', 'jnet/guide', 'jnet/u/4', '/public/login', '/public/api/x'] as $path) {
            self::assertTrue(JnetAnnouncement::isExcludedPath($path), $path);
        }
        foreach (['dashboard', 'documentation', 'account', 'personnel/me', 'public/dashboard', 'formations', 'evenements'] as $path) {
            self::assertFalse(JnetAnnouncement::isExcludedPath($path), $path);
        }
        // « plugin » ou « jnetwork » ne sont pas « api » ni « jnet ».
        self::assertFalse(JnetAnnouncement::isExcludedPath('apiary/list'));
        self::assertFalse(JnetAnnouncement::isExcludedPath('jnetwork'));
    }

    public function testModalMarkupIsAnAccessibleDialogWithFocusTrapAndEscape(): void
    {
        $view = $this->read('views/partials/jnet_announce_modal.php');
        $js = $this->read('public/assets/js/jnet-announce.js');
        self::assertStringContainsString('role="dialog"', $view);
        self::assertStringContainsString('aria-modal="true"', $view);
        self::assertStringContainsString('aria-labelledby="jna-title"', $view);
        self::assertStringContainsString('aria-describedby="jna-lead"', $view);
        self::assertStringContainsString('id="jna-title"', $view);
        self::assertStringContainsString('id="jna-lead"', $view);
        // Cachée tant que le script ne l'ouvre pas : sans JavaScript, la page n'est jamais bloquée.
        self::assertMatchesRegularExpression('~id="jnet-announce" hidden~', $view);
        // Trois puces, deux accès, un report.
        self::assertSame(3, substr_count($view, 'class="jna__n"'));
        self::assertStringContainsString("url('jnet')", $view);
        self::assertStringContainsString("url('jnet/guide')", $view);
        foreach (['Découvrir JNET', 'Lire le guide', 'Plus tard'] as $label) {
            self::assertStringContainsString($label, $view);
        }
        self::assertStringContainsString('aria-label="Fermer"', $view);
        // Pas de sur-titre au-dessus du titre.
        self::assertDoesNotMatchRegularExpression('~<p[^>]*kicker~i', $view);
        // Piège à focus, Échap, retour du focus, pas d'ouverture dans une fenêtre intégrée.
        self::assertStringContainsString("e.key === 'Escape'", $js);
        self::assertStringContainsString("e.key !== 'Tab'", $js);
        self::assertStringContainsString('e.shiftKey', $js);
        self::assertStringContainsString('opener.focus', $js);
        self::assertStringContainsString('window.self !== window.top', $js);
        self::assertStringContainsString('data-jna-close', $js);
        // Mémoire : serveur d'abord, localStorage en secours.
        self::assertStringContainsString('fetch(url', $js);
        self::assertStringContainsString('localStorage', $js);
    }

    public function testModalIsWiredIntoThePortalLayoutAndTheDashboardAfterOtherModals(): void
    {
        $layout = $this->read('views/layout/main.php');
        $dash = $this->read('views/dashboard.php');
        self::assertStringContainsString('jnet_announce_modal.php', $layout);
        self::assertStringContainsString('jnet_announce_modal.php', $dash);
        // Après la rétrospective (elle pose $yrpPlayer) : l'annonce lui cède la place.
        self::assertGreaterThan(strpos($layout, 'year_review_modal.php'), strpos($layout, 'jnet_announce_modal.php'));
        $view = $this->read('views/partials/jnet_announce_modal.php');
        foreach (['$planGiftNotice', '$modReplyItems', '$yrpPlayer'] as $other) {
            self::assertStringContainsString($other, $view);
        }
        self::assertStringContainsString('JnetAnnouncement::viewerHasJnet()', $view);
        self::assertStringContainsString('JnetAnnouncement::MODAL_KEY', $view);
        self::assertStringContainsString('SESSION_MODAL_SEEN', $view);
    }

    public function testNoticeEndpointsRememberPerUserAndOnlyForKnownItems(): void
    {
        $routes = $this->read('routes/web.php');
        self::assertStringContainsString("'/api/jnet/annonce/{item}/vu', [\\App\\Controllers\\Api\\JnetNoticeController::class, 'dismiss'], [AuthMiddleware::class]", $routes);
        self::assertStringContainsString("'/api/jnet/annonce/{item}/rappel', [\\App\\Controllers\\Api\\JnetNoticeController::class, 'restore'], [AuthMiddleware::class]", $routes);
        $ctrl = $this->read('app/Controllers/Api/JnetNoticeController.php');
        self::assertStringContainsString("'fenetre' => JnetAnnouncement::MODAL_KEY", $ctrl);
        self::assertStringContainsString("'bandeau' => JnetAnnouncement::BANNER_KEY", $ctrl);
        self::assertStringContainsString('Csrf::validate', $ctrl);
        self::assertStringContainsString('Annonce inconnue', $ctrl);
        self::assertStringContainsString('->restore($userId, $key)', $ctrl);
        // Les clés tiennent dans la colonne tour_key (64 caractères) et ne heurtent pas le guide du tableau de bord.
        self::assertLessThanOrEqual(64, strlen(JnetAnnouncement::MODAL_KEY));
        self::assertLessThanOrEqual(64, strlen(JnetAnnouncement::BANNER_KEY));
        self::assertNotSame(JnetAnnouncement::MODAL_KEY, JnetAnnouncement::BANNER_KEY);
        self::assertStringContainsString('public function restore(', $this->read('app/Repositories/UserUiTourRepository.php'));
    }

    // —— Bandeau du tableau de bord ——

    public function testBannerModeFollowsAccessAndDismissal(): void
    {
        self::assertSame('none', JnetAnnouncement::bannerMode(false, false));
        self::assertSame('none', JnetAnnouncement::bannerMode(false, true));
        self::assertSame('full', JnetAnnouncement::bannerMode(true, false));
        self::assertSame('collapsed', JnetAnnouncement::bannerMode(true, true));
    }

    public function testBannerMarkupHasTheMessageButtonsAndADiscreetWayBack(): void
    {
        $view = $this->read('views/partials/jnet_dashboard_banner.php');
        self::assertStringContainsString('Extranet JNET : ton espace d’unité, tes ordres et ton profil', $view);
        self::assertStringContainsString('Ouvrir mon extranet', $view);
        self::assertStringContainsString("url('jnet')", $view);
        self::assertStringContainsString("url('jnet/guide')", $view);
        self::assertStringContainsString('>Guide</a>', $view);
        self::assertStringContainsString('data-jnb-hide', $view);
        self::assertStringContainsString('aria-label="Masquer ce bandeau"', $view);
        self::assertStringContainsString('Afficher le bandeau JNET', $view);
        self::assertStringContainsString('JnetAnnouncement::viewerHasJnet()', $view);
        self::assertStringContainsString('JnetAnnouncement::bannerMode(', $view);
        self::assertStringContainsString("api/jnet/annonce/bandeau/vu", $view);
        self::assertStringContainsString("api/jnet/annonce/bandeau/rappel", $view);
        self::assertDoesNotMatchRegularExpression('~<p[^>]*kicker~i', $view);
        // Au tableau de bord, avant le briefing du jour.
        $cc = $this->read('views/partials/dashboard_command_center.php');
        self::assertStringContainsString('jnet_dashboard_banner.php', $cc);
        self::assertLessThan(strpos($cc, 'id="dash-tour-hero"'), strpos($cc, 'jnet_dashboard_banner.php'));
        $js = $this->read('public/assets/js/jnet-announce.js');
        self::assertStringContainsString('data-jnb-show', $js);
        self::assertStringContainsString('window.__jnetAnnounce', $js);
    }

    public function testAccessConditionIsTheSameAsTheMemberLanding(): void
    {
        $class = $this->read('app/Support/JnetAnnouncement.php');
        self::assertStringContainsString('PortalAccessChoice::isNoOrganizationContext()', $class);
        self::assertStringContainsString('PortalAccessChoice::jnetOfferedTo(', $class);
        $portal = $this->read('app/Support/PortalAccessChoice.php');
        self::assertStringContainsString('self::jnetOfferedTo($type)', $portal);
    }

    // —— Textes anglais et classes importées ——

    public function testEveryFrenchTextOfTheNewViewsHasAnEnglishEntry(): void
    {
        foreach (array_merge(self::NEW_VIEWS, ['views/jnet/_page.php', 'views/jnet/hub.php']) as $rel) {
            self::assertSame([], array_slice(UntranslatedFrenchScanner::missingInFile($this->root() . '/' . $rel), 0, 5), $rel);
        }
        self::assertSame([], UntranslatedFrenchScanner::missingInFile($this->root() . '/public/assets/js/jnet-announce.js'));
        $dict = $this->read('lang/en/site_auto.php');
        foreach ([
            "'Extranet JNET : ton espace d’unité, tes ordres et ton profil' =>",
            "'JNET, l’extranet de ton unité, est arrivé.' =>",
            "'Découvrir JNET' =>",
            "'Lire le guide' =>",
            "'Ouvrir mon extranet' =>",
            "'Afficher le bandeau JNET' =>",
            "'1. C’est quoi JNET ?' =>",
            "'Guide de l’extranet JNET' =>",
        ] as $needle) {
            self::assertStringContainsString($needle, $dict);
        }
    }

    public function testNewViewsImportEveryClassTheyCall(): void
    {
        foreach (self::NEW_VIEWS as $rel) {
            $src = $this->read($rel);
            $code = (string) preg_replace(['~/\*.*?\*/~s', '~^\s*//.*$~m'], '', $src);
            // Appels statiques « Classe:: » sans barre oblique inverse devant : il faut un « use » (ou un nom PHP interne).
            preg_match_all('~(?<![\\\\\w$>:])([A-Z][A-Za-z0-9_]+)::~', $code, $calls);
            foreach (array_unique($calls[1]) as $class) {
                if (in_array($class, ['self', 'static', 'parent'], true)) {
                    continue;
                }
                self::assertMatchesRegularExpression('~^use [\\\\\w]+\\\\' . $class . ';~m', $src, $rel . ' : « use » manquant pour ' . $class);
            }
        }
        $ctrl = $this->read('app/Controllers/Api/JnetNoticeController.php');
        foreach (['Csrf', 'Request', 'Response', 'Session', 'UserUiTourRepository', 'JnetAnnouncement'] as $class) {
            self::assertMatchesRegularExpression('~^use [\\\\\w]+\\\\' . $class . ';~m', $ctrl, $class);
        }
    }
}
