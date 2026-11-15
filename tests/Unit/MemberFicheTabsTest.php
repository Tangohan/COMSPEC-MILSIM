<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Gate;
use App\Services\Effectifs\MemberChronology;
use App\Support\EffectifsLmsAccess;
use App\Support\MemberFicheTabs;
use PHPUnit\Framework\TestCase;

/**
 * Fiche membre du back-office à onglets (audit 2.4) : onglets, anciennes ancres, droits, chronologie en lecture.
 */
final class MemberFicheTabsTest extends TestCase
{
    /** Ancres de la fiche avant les onglets : toutes doivent rester valides. */
    private const LEGACY_ANCHORS = [
        'identite', 'situation', 'modifier-dossier', 'langues', 'invitations',
        'poste', 'unite', 'equipe', 'fonctions', 'acces',
        'carriere', 'grade', 'anciennete', 'qualifications', 'elevation', 'statut', 'depart',
        'suivi-rh', 'presence', 'absences', 'sanctions', 'documents-rh', 'mobilite',
        'papiers', 'historique', 'historique-rh', 'edit-portrait',
    ];

    private static function read(string $path): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/' . $path);
    }

    /** Texte de la vue entre l'ouverture d'un panneau et l'ouverture du suivant. */
    private static function panelChunk(string $view, string $slug): string
    {
        $needle = "\$panelOpen('" . $slug . "')";
        $start = strpos($view, $needle);
        self::assertNotFalse($start, 'panneau ' . $slug);
        $next = strpos($view, "\$panelOpen('", $start + strlen($needle));
        $end = $next === false ? strpos($view, '<aside class="eff-fm-rail"', $start) : $next;

        return substr($view, (int) $start, (int) $end - (int) $start);
    }

    public function testTabsAreOrderedAndEveryAnchorBelongsToOneTab(): void
    {
        $slugs = MemberFicheTabs::slugs();
        self::assertSame(array_values(array_unique($slugs)), $slugs);
        self::assertSame('identite', $slugs[0]);
        $seen = [];
        foreach ($slugs as $slug) {
            self::assertNotSame('', MemberFicheTabs::label($slug));
            foreach (MemberFicheTabs::anchors($slug) as $anchor) {
                self::assertFalse(isset($seen[$anchor]), 'ancre en double : ' . $anchor);
                $seen[$anchor] = $slug;
                self::assertSame($slug, MemberFicheTabs::tabForAnchor('#' . $anchor));
            }
        }
        foreach (['carriere', 'qualifications', 'statut', 'suivi-rh', 'historique', 'acces', 'poste'] as $slug) {
            self::assertContains($slug, $slugs);
        }
        self::assertNull(MemberFicheTabs::tabForAnchor('#inconnue'));
    }

    public function testEveryLegacyAnchorOfTheFicheIsStillInTheViewInsideItsTab(): void
    {
        $view = self::read('views/admin/effectifs_workspace/member.php');
        $partials = self::read('views/admin/effectifs_workspace/partials/member_papers.php')
            . self::read('views/admin/effectifs_workspace/partials/member_invites.php');
        foreach (self::LEGACY_ANCHORS as $anchor) {
            self::assertTrue(
                str_contains($view, 'id="' . $anchor . '"') || str_contains($partials, 'id="' . $anchor . '"'),
                'ancre disparue : #' . $anchor
            );
        }
        // Les cartes du corps de la fiche sont rangées dans l'onglet que MemberFicheTabs annonce.
        foreach (MemberFicheTabs::slugs() as $slug) {
            $chunk = self::panelChunk($view, $slug);
            foreach (MemberFicheTabs::anchors($slug) as $anchor) {
                if (in_array($anchor, ['papiers', 'invitations', 'disponibilite'], true)) {
                    continue; // insérées par un partiel qui garde son ancre
                }
                self::assertStringContainsString('id="' . $anchor . '"', $chunk, '#' . $anchor . ' hors de l’onglet ' . $slug);
            }
        }
    }

    public function testPapersTabExistsOnlyForReadersWithPapersAccess(): void
    {
        $without = MemberFicheTabs::visible(['papers' => false]);
        $with = MemberFicheTabs::visible(['papers' => true]);
        self::assertNotContains('papiers', $without);
        self::assertContains('papiers', $with);
        self::assertSame($without, array_values(array_diff($with, ['papiers'])));
        // Onglet demandé inconnu ou non visible : retour au premier onglet visible, jamais d'onglet interdit.
        self::assertSame('carriere', MemberFicheTabs::resolve('carriere', $without));
        self::assertSame('identite', MemberFicheTabs::resolve('papiers', $without));
        self::assertSame('identite', MemberFicheTabs::resolve('n’importe quoi', $with));
        self::assertSame('papiers', MemberFicheTabs::resolve('papiers', $with));
        self::assertSame('identite', MemberFicheTabs::resolve(null, $with));
        $view = self::read('views/admin/effectifs_workspace/member.php');
        self::assertStringContainsString("is_array(\$memberPapers ?? null)", $view);
        self::assertStringContainsString('MemberFicheTabs::visible([\'papers\' => is_array($memberPapers)])', self::read('app/Controllers/Admin/EffectifsWorkspaceController.php'));
    }

    public function testWithoutJavaScriptEveryPanelIsDisplayed(): void
    {
        $view = self::read('views/admin/effectifs_workspace/member.php');
        $css = self::read('public/assets/css/back-office-effectifs-fiche.css');
        $js = self::read('public/assets/js/eff-member.js');

        // Le serveur ne masque jamais un panneau : seul le style de la classe posée par un script le fait.
        self::assertStringNotContainsString('hidden', substr($view, (int) strpos($view, '$panelOpen = '), 400));
        self::assertMatchesRegularExpression('/\.eff-fm--tabs\s+\.eff-fm-panel:not\(\.is-active\)\s*\{\s*display:\s*none;/', $css);
        self::assertStringNotContainsString("\n.eff-fm-panel:not(.is-active)", $css);
        self::assertStringContainsString("classList.add('eff-fm--tabs')", $view);
        self::assertStringContainsString("root.classList.add('eff-fm--tabs')", $js);
        // Les onglets sont de vrais liens (?onglet=) : ils fonctionnent même si le script ne se charge pas.
        self::assertStringContainsString("'?onglet=' . \$tabSlug . '#onglet-' . \$tabSlug", $view);
    }

    public function testTabsAreKeyboardAccessibleAndOldAnchorsOpenTheirTab(): void
    {
        $js = self::read('public/assets/js/eff-member.js');
        foreach (["'tablist'", "'tab'", "'tabpanel'", "'aria-selected'", "'ArrowRight'", "'ArrowLeft'", "'Home'", "'End'", 'tab.tabIndex = on ? 0 : -1', "addEventListener('hashchange', revealAnchor)", "closest('[data-eff-fm-panel]')", 'details.open = true', "searchParams.set('onglet', slug)"] as $needle) {
            self::assertStringContainsString($needle, $js, $needle);
        }
        // Les fenêtres (#eff-dlg-…) gardent leur propre ouverture.
        self::assertStringContainsString('/^eff-dlg-/i', $js);
        $css = self::read('public/assets/css/back-office-effectifs-fiche.css');
        self::assertStringContainsString('.eff-fm-tabs a:focus-visible', $css);
    }

    public function testNoEyebrowHeadersAndNoPopupInTheNewBlocks(): void
    {
        $view = self::read('views/admin/effectifs_workspace/member.php');
        $chunks = self::panelChunk($view, 'qualifications') . self::panelChunk($view, 'historique');
        self::assertStringNotContainsString('kicker', strtolower($chunks));
        self::assertStringNotContainsString('eyebrow', strtolower($chunks));
        self::assertStringNotContainsString('systemChat', $chunks);
        self::assertStringNotContainsString('toast', strtolower($chunks));
    }

    public function testHrDocumentsFollowTheirOwnRightInViewAndController(): void
    {
        $view = self::read('views/admin/effectifs_workspace/member.php');
        $controller = self::read('app/Controllers/Admin/EffectifsWorkspaceController.php');
        // Le coffre RH n'est affiché (carte, lien, raccourci) que pour canViewHrDocuments, comme la page elle-même.
        self::assertMatchesRegularExpression('/<\?php if \(\$canViewHrDocuments\): \?>\s*<article class="eff-fm-card" id="documents-rh">/', $view);
        self::assertStringContainsString("<?php if (\$canViewHrDocuments): ?><li><a class=\"eff-fm-link\"", $view);
        self::assertStringContainsString("'canViewHrDocuments' => EffectifsLmsAccess::canViewHrDocuments(\$gate)", $controller);
        self::assertStringContainsString('canViewHrDocuments(Gate::getInstance())', self::read('app/Controllers/Admin/RhDossierWorkspaceController.php'));
        // La chronologie ne reçoit les documents RH que de la liste déjà filtrée par le droit.
        self::assertStringContainsString("'hr_documents' => \$hrDocuments", $controller);
        self::assertMatchesRegularExpression('/\$hrDocuments = [^;]*canViewHrDocuments[^;]*\[\];/s', $controller);
    }

    public function testRoleInUnitFormReusesTheExistingRouteAndRight(): void
    {
        $view = self::read('views/admin/effectifs_workspace/member.php');
        $routes = self::read('routes/web.php');
        $controller = self::read('app/Controllers/Admin/EffectifsWorkspaceController.php');
        self::assertStringContainsString("effectifs_workspace_url('membres/' . \$id . '/role-unite')", $view);
        self::assertStringContainsString('name="role_name" maxlength="100" required', $view);
        self::assertMatchesRegularExpression('/<\?php if \(\$canManageAssignments && \$assignments !== \[\]\): \?>\s*<\?php \$primaryRoleName/', $view);
        self::assertStringContainsString("'/back-office/ressources/effectifs/membres/{id}/role-unite'", $routes);
        $action = substr($controller, (int) strpos($controller, 'public function updateMemberAssignmentRole('), 900);
        self::assertStringContainsString('EffectifsLmsAccess::canManageAssignments(Gate::getInstance())', $action);
        self::assertStringContainsString('Csrf::validate', $action);
    }

    public function testAwardingADecorationReusesTheGrantRouteAndChecksTheRightOnTheServer(): void
    {
        $view = self::read('views/admin/effectifs_workspace/member.php');
        $award = self::read('app/Controllers/Admin/Organization/AwardReferentielController.php');
        $middleware = self::read('app/Middleware/OrganizationAdminMiddleware.php');
        self::assertStringContainsString("url('back-office/referentiels/decorations/attribuer')", $view);
        self::assertStringContainsString('name="return_to" value="member"', $view);
        self::assertMatchesRegularExpression('/<\?php if \(\$canAwardDecorations\): \?>\s*<\?php if \(\$awardDefinitions === \[\]\)/', $view);

        // Droit vérifié dans l'action elle-même, avant toute lecture ou écriture.
        $grant = substr($award, (int) strpos($award, 'public function grant('));
        self::assertLessThan(
            (int) strpos($grant, '$this->awards->create'),
            (int) strpos($grant, 'EffectifsLmsAccess::canAwardDecorations(Gate::getInstance())')
        );
        self::assertLessThan(
            (int) strpos($grant, 'findById($personnelId, $tenantId)'),
            (int) strpos($grant, 'EffectifsLmsAccess::canAwardDecorations(Gate::getInstance())')
        );
        self::assertStringContainsString("'?onglet=qualifications#distinctions'", $award);

        // Le droit est exactement celui du middleware sur ce préfixe : aucun droit nouveau.
        self::assertStringContainsString("str_starts_with(\$path, '/back-office/referentiels/decorations') && (\n                \$gate->allows('personnel.awards.manage')\n                || \$gate->allows('personnel.badges.manage')", str_replace("\r\n", "\n", $middleware));
    }

    public function testAwardRightMatchesTheDecorationsScreenRights(): void
    {
        $gate = Gate::getInstance();
        try {
            foreach (['personnel.awards.manage', 'personnel.badges.manage', 'admin.organization', 'admin.access'] as $permission) {
                $gate->setPermissions([$permission]);
                self::assertTrue(EffectifsLmsAccess::canAwardDecorations($gate), $permission);
            }
            // Gérer les grades, les statuts ou les profils ne permet pas de décerner.
            foreach (['personnel.grades.manage', 'personnel.status.manage', 'personnel.profile.update', 'personnel.assignments.manage', 'personnel.profile.view'] as $permission) {
                $gate->setPermissions([$permission]);
                self::assertFalse(EffectifsLmsAccess::canAwardDecorations($gate), $permission);
            }
            // site.support est réservé à l'administration de la plateforme : il passe par setPlatformAdmin.
            $gate->setPermissions([]);
            $gate->setPlatformAdmin(true);
            self::assertTrue(EffectifsLmsAccess::canAwardDecorations($gate));
        } finally {
            $gate->setPermissions([]);
        }
    }

    public function testChronologyMergesSourcesNewestFirstAndFilters(): void
    {
        $events = MemberChronology::build([
            'grades' => [['date' => '2025-03-01 10:00:00', 'to_label' => 'Sergent', 'from_label' => 'Caporal', 'from_known' => true, 'source' => 'elevation', 'actor_label' => 'Cdt Martin']],
            'org_history' => [['created_at' => '2024-01-10 08:00:00', 'summary' => 'Affectation : Non renseigné → 1re Section']],
            'qualifications' => [['definition_name' => 'Secourisme', 'level_name' => 'Niveau 2', 'obtained_at' => '2024-06-01', 'expires_at' => '2099-01-01']],
            'awards' => [['definition_name' => 'Croix du mérite', 'awarded_at' => '2025-05-05', 'authority' => 'Chef de corps']],
            'absences' => [['starts_on' => '2025-01-02', 'ends_on' => '2025-01-09', 'reason' => 'travail', 'status' => 'active']],
            'absence_reasons' => ['travail' => 'Travail'],
        ]);
        self::assertSame(['2025-05-05', '2025-03-01', '2025-01-02', '2024-06-01', '2024-01-10'], array_column($events, 'date'));
        self::assertSame(['decoration', 'grade', 'absence', 'qualification', 'organisation'], array_column($events, 'type'));
        self::assertSame('Sergent', $events[1]['title']);
        self::assertStringContainsString('Précédemment : Caporal', $events[1]['detail']);
        self::assertStringContainsString('Élévation validée par Cdt Martin', $events[1]['detail']);
        self::assertSame('distinctions', $events[0]['anchor']);
        self::assertSame('Absence : travail', $events[2]['title']);
        self::assertSame(['grade' => 1, 'organisation' => 1, 'qualification' => 1, 'decoration' => 1, 'absence' => 1], MemberChronology::counts($events));
        self::assertCount(1, MemberChronology::filter($events, 'grade'));
        self::assertCount(5, MemberChronology::filter($events, ''));
        self::assertSame('', MemberChronology::sanitizeType('n’importe quoi'));
        self::assertSame('grade', MemberChronology::sanitizeType(' grade '));
    }

    public function testChronologyIgnoresEmptyDatesAndDuplicatesAndDoubleServiceRows(): void
    {
        $events = MemberChronology::build([
            'grades' => [['date' => '', 'to_label' => 'Sans date']],
            'org_history' => [
                ['created_at' => '2024-02-02', 'summary' => 'Unité : A → B'],
                ['created_at' => '2024-02-02 17:00:00', 'summary' => 'Unité : A → B'],
                ['created_at' => '0000-00-00 00:00:00', 'summary' => 'Date nulle'],
            ],
            'awards' => [['definition_name' => 'Médaille', 'awarded_at' => '2025-05-05']],
            'service_history' => [
                ['event_type' => 'award', 'title' => 'Décoration : Médaille', 'event_date' => '2025-05-05'],
                ['event_type' => 'note', 'title' => 'Note de service', 'event_date' => '2025-05-05'],
            ],
        ]);
        self::assertSame(['decoration', 'service', 'organisation'], array_column($events, 'type'));
        self::assertSame('Note de service', $events[1]['title']);
        self::assertTrue($events[1]['stored']);
    }

    public function testChronologyShowsOnlyWhatTheCallerGivesIt(): void
    {
        // Sans source (droit absent côté contrôleur) rien n'apparaît : départ, sanctions et documents compris.
        self::assertSame([], MemberChronology::build(['departure' => null, 'sanctions' => [], 'hr_documents' => []]));
        $withDeparture = MemberChronology::build([
            'departure' => ['departed_at' => '2025-02-01', 'reason' => 'pause', 'access_revoked' => 1],
            'departure_reasons' => ['pause' => 'Pause'],
        ]);
        self::assertSame('Départ : pause', $withDeparture[0]['title']);
        self::assertSame('Accès retirés', $withDeparture[0]['detail']);

        $controller = self::read('app/Controllers/Admin/EffectifsWorkspaceController.php');
        self::assertStringContainsString("'departure' => \$canManageStatus ? \$latestDeparture : null", $controller);
        self::assertStringContainsString("'sanctions' => \$canViewSanctions && is_array(", $controller);
    }

    public function testChronologyIsReadOnly(): void
    {
        $source = self::read('app/Services/Effectifs/MemberChronology.php');
        foreach (['INSERT', 'UPDATE ', 'DELETE', 'PDO', 'Database::', 'Session::flash', '->execute('] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $source, $forbidden);
        }
        $view = self::read('views/admin/effectifs_workspace/member.php');
        $chunk = self::panelChunk($view, 'historique');
        self::assertStringNotContainsString('<form', $chunk);
        self::assertStringNotContainsString('method="post"', $chunk);
        // Filtre par type : de simples liens, calculés côté serveur.
        self::assertStringContainsString("'?onglet=historique' . (\$type !== '' ? '&type=' . rawurlencode(\$type) : '') . '#chronologie'", $chunk);
    }

    public function testChronologyDoesNotMakeTheFicheSlowerThanOneQuery(): void
    {
        // Seules les décorations sont lues en plus ; tout le reste est fusionné en mémoire depuis la fiche.
        $controller = self::read('app/Controllers/Admin/EffectifsWorkspaceController.php');
        $block = substr($controller, (int) strpos($controller, '// Décorations : liste du membre'), 2500);
        self::assertSame(1, substr_count($block, 'PersonnelAwardRepository())->listForPersonnel('));
        self::assertStringContainsString('if ($canAwardDecorations) {', $block);
        self::assertStringContainsString('new \App\Repositories\AwardDefinitionRepository()', $block);
    }

    public function testAnchorLinksAndOldPageAddressesStillWork(): void
    {
        $routes = self::read('routes/web.php');
        self::assertStringContainsString("'/back-office/ressources/effectifs/membres/{id}', [EffectifsWorkspaceController::class, 'member']", $routes);
        self::assertStringContainsString("'/back-office/ressources/effectifs/membres/{id}/modifier', [EffectifsWorkspaceController::class, 'member']", $routes);
        $view = self::read('views/admin/effectifs_workspace/member.php');
        // Les liens internes de la fiche (hero, « À traiter », carte décorations) visent des ancres rangées dans un onglet.
        preg_match_all('/(?:href="|\x27)#([a-z][a-z-]*)(?:"|\x27)/', $view, $found);
        $internal = array_filter(array_unique($found[1]), static fn (string $a): bool => !str_starts_with($a, 'eff-dlg-') && !str_starts_with($a, 'onglet') && $a !== 'postes'); // « #postes » est l'ancre de la fiche d'unité, pas de cette page
        self::assertContains('grade', $internal);
        self::assertContains('decerner', $internal);
        foreach ($internal as $anchor) {
            self::assertNotNull(MemberFicheTabs::tabForAnchor($anchor), '#' . $anchor . ' ne mène à aucun onglet');
        }
        // Les liens de la chronologie aussi : chaque type renvoie à une carte existante.
        $anchors = (new \ReflectionClass(MemberChronology::class))->getConstant('ANCHORS');
        self::assertSame(array_keys(MemberChronology::TYPES), array_keys($anchors));
        foreach ($anchors as $type => $anchor) {
            self::assertNotNull(MemberFicheTabs::tabForAnchor($anchor), $type . ' → #' . $anchor);
        }
    }
}
