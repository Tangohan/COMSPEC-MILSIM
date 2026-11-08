<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\ArsenalWardrobeRepository;
use App\Repositories\EquipmentClassRepository;
use App\Repositories\EquipmentItemDefinitionRepository;
use App\Repositories\GameEquipmentRepository;
use App\Repositories\PersonnelJobRoleRepository;
use App\Services\GameEquipment\KitsAccess;
use App\Services\GameEquipment\ObservedLoadouts;
use App\Services\GameEquipment\OutfitAssistant;
use App\Services\GameEquipment\RoleKitService;
use App\Services\Platform\FeatureGateService;
use App\Support\ArsenalLoadoutItems;
use App\Support\EquipmentCoverStorage;
use App\Support\EquipmentSpaces;
use App\Support\PlanFeatureDenial;

class ArsenalWardrobeController
{
    public function __construct(
        private ?ArsenalWardrobeRepository $repo = null,
        private ?FeatureGateService $featureGate = null,
        private ?EquipmentClassRepository $classes = null,
        private ?EquipmentItemDefinitionRepository $definitions = null,
    ) {
        $this->repo ??= new ArsenalWardrobeRepository();
        $this->featureGate ??= \App\Core\Container::get(FeatureGateService::class);
        $this->classes ??= new EquipmentClassRepository();
        $this->definitions ??= new EquipmentItemDefinitionRepository();
    }

    public function index(Request $request, array $params = []): Response
    {
        $gate = $this->gate();
        if ($gate instanceof Response) {
            return $gate;
        }
        [$tenantId, $userId] = $gate;
        if (!$this->repo->tablesReady()) {
            return $this->hubView(true, [], [], [], [], [], null, false, []);
        }
        $nav = EquipmentSpaces::resolve($request->queryParams());
        $wardrobes = $this->attachPreviews($tenantId, $this->repo->listAccessibleWardrobes($tenantId, $userId));
        $favoriteIds = array_flip($this->repo->favoriteIds($tenantId, $userId));
        foreach ($wardrobes as &$w) {
            // Favori du membre : ses propres tenues par leur marque, celles des autres par sa liste de favoris.
            $w['is_favorite_me'] = !empty($w['mine']) ? !empty($w['is_favorite']) : isset($favoriteIds[(int) ($w['id'] ?? 0)]);
        }
        unset($w);
        $collections = $this->repo->listCollections($tenantId, $userId);
        $collections = $this->attachCollectionMosaics($collections, $wardrobes);
        $classes = [];
        try {
            $classes = $this->classes->listForTenant($tenantId);
        } catch (\Throwable) {
            $classes = [];
        }
        $dotation = [];
        try {
            $dotation = $this->definitions->listForTenant($tenantId, false);
        } catch (\Throwable) {
            $dotation = [];
        }

        $kitsAllowed = KitsAccess::allows($tenantId);
        $space = $nav['espace'];
        $kits = $kitsAllowed && $space === 'kits' ? $this->kitsForPage($tenantId, $userId, $wardrobes) : [];
        // Compteur de l'onglet Kits : sans charger le contenu des kits (seul l'espace Kits en a besoin).
        $kitCount = 0;
        if ($kitsAllowed) {
            try {
                $service = $this->kitService();
                $kitCount = count($this->canManageKits() ? $service->listForTenant($tenantId) : $service->listForMember($tenantId, $userId));
            } catch (\Throwable) {
                $kitCount = 0;
            }
        }
        $mine = array_values(array_filter($wardrobes, static fn (array $w): bool => !empty($w['mine'])));
        $community = array_values(array_filter(
            $wardrobes,
            static fn (array $w): bool => empty($w['mine']) && !($kitsAllowed && (string) ($w['source'] ?? '') === 'role_kit')
        ));
        $favorites = array_values(array_filter($wardrobes, static fn (array $w): bool => !empty($w['is_favorite_me'])));

        $q = trim((string) $request->query('q', ''));
        $type = trim((string) $request->query('type', ''));
        $sort = trim((string) $request->query('tri', 'name'));
        $page = (int) $request->query('page', 1);
        $collectionId = $nav['collection'];
        $collection = null;
        foreach ($collections as $c) {
            if ((int) ($c['id'] ?? 0) === $collectionId) {
                $collection = $c;
                break;
            }
        }
        $list = [];
        if ($space === 'mes-tenues' || $space === 'favoris' || $space === 'communaute') {
            $list = $space === 'mes-tenues' ? $mine : ($space === 'favoris' ? $favorites : $community);
        } elseif ($space === 'collections' && $collection !== null) {
            $list = array_values(array_filter($wardrobes, static fn (array $w): bool => (int) ($w['collection_id'] ?? 0) === $collectionId));
        }
        $paged = null;
        if ($space === 'collections' && $collection === null) {
            $needle = EquipmentSpaces::fold($q);
            $found = array_values(array_filter($collections, static fn (array $c): bool => $needle === ''
                || str_contains(EquipmentSpaces::fold((string) ($c['name'] ?? '') . ' ' . (string) ($c['description'] ?? '')), $needle)));
            $paged = EquipmentSpaces::paginate($found, $page);
        } elseif ($space === 'kits') {
            $needle = EquipmentSpaces::fold($q);
            $found = array_values(array_filter($kits, static fn (array $k): bool => $needle === ''
                || str_contains(EquipmentSpaces::fold(implode(' ', [(string) ($k['name'] ?? ''), (string) ($k['description'] ?? ''), (string) ($k['unit_name'] ?? ''), (string) ($k['role_name'] ?? '')])), $needle)));
            $paged = EquipmentSpaces::paginate($found, $page, EquipmentSpaces::PER_PAGE_KITS);
        } elseif ($space !== 'materiel') {
            $paged = EquipmentSpaces::paginate(
                EquipmentSpaces::sortWardrobes(EquipmentSpaces::filterWardrobes($list, $q, $type), $sort),
                $page
            );
        }

        $nav += [
            'q' => $q,
            'type' => $type,
            'sort' => in_array($sort, EquipmentSpaces::SORTS, true) ? $sort : 'name',
            'paged' => $paged,
            'collection_row' => $collection,
            'counts' => [
                'mes-tenues' => count($mine),
                'favoris' => count($favorites),
                'collections' => count($collections),
                'communaute' => count($community),
                'kits' => $kitsAllowed ? $kitCount : null,
                'materiel' => count($classes) + count($dotation),
            ],
            'detail' => $this->detailFor($request, $tenantId, $userId),
            'creer' => (string) $request->query('creer', '') === 'kit',
        ];

        return $this->hubView(
            false,
            $wardrobes,
            $collections,
            $classes,
            $dotation,
            $kits,
            $this->playerKits()?->forUser($tenantId, $userId),
            $kitsAllowed,
            $nav,
        );
    }

    /**
     * Repli sans JavaScript de la fenêtre d'une tenue : ?tenue=ID affiche la tenue en tête de page.
     *
     * @return array<string, mixed>|null
     */
    private function detailFor(Request $request, int $tenantId, int $userId): ?array
    {
        $id = (int) $request->query('tenue', 0);
        if ($id < 1) {
            return null;
        }
        $row = $this->repo->findWardrobe($tenantId, $id);
        if ($row === null || !ArsenalWardrobeRepository::canView($row, $userId)) {
            return null;
        }
        $payload = (string) ($row['payload_text'] ?? '');
        $sections = ArsenalLoadoutItems::grouped(
            $payload,
            $this->playerKits()?->catalogFor($tenantId, ArsenalLoadoutItems::classes($payload)) ?? [],
        );

        return [
            'id' => (int) $row['id'],
            'name' => (string) ($row['display_name'] ?? $row['name'] ?? ''),
            'description' => (string) ($row['description'] ?? ''),
            'owner' => (string) ($row['owner_label'] ?? ''),
            'mine' => (int) ($row['user_id'] ?? 0) === $userId,
            'sections' => $sections,
            'ace' => ArsenalLoadoutItems::aceExport($payload) !== null,
        ];
    }

    /**
     * Ancienne adresse des kits : la section « Kits de départ » de la page Équipement.
     */
    public function redirectKits(Request $request, array $params = []): Response
    {
        return Response::redirect(url('equipment') . '?espace=kits');
    }

    /**
     * Met une tenue en favori (ou l'en retire). Ouvert à toutes les formules : c'est une aide à la consultation.
     */
    public function toggleFavorite(Request $request, array $params = []): Response
    {
        $gate = $this->gate();
        if ($gate instanceof Response) {
            return $gate;
        }
        [$tenantId, $userId] = $gate;
        // Retour à l'espace d'où l'on vient (adresse interne à /equipment seulement), sinon aux favoris.
        $return = (string) $request->input('_return', '');
        $back = preg_match('#^/equipment(\?[A-Za-z0-9_=&%.\-]*)?$#', $return) === 1 ? url(ltrim($return, '/')) : url('equipment') . '?espace=favoris';
        $json = $this->wantsJson($request);
        if (!Csrf::validate((string) $request->input('_csrf_token', ''))) {
            if ($json) {
                return Response::json(['ok' => false, 'error' => 'csrf'], 419);
            }
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect($back);
        }
        $id = (int) ($params['id'] ?? 0);
        $on = (string) $request->input('on', '1') === '1';
        $ok = $id > 0 && $this->repo->setFavorite($tenantId, $userId, $id, $on);
        if ($json) {
            return Response::json(['ok' => $ok, 'favorite' => $ok ? $on : null], $ok ? 200 : 422);
        }
        if (!$ok) {
            Session::flash('error', 'Cette tenue ne peut pas être mise en favori.');
        }

        return Response::redirect($back);
    }

    /**
     * Kits à montrer sur la page : tous ceux de la communauté pour un responsable des kits, sinon ceux proposés au
     * membre (ses fonctions, ses unités, tous). Chacun avec son aperçu et son contenu, noms vus en jeu.
     *
     * @param list<array<string, mixed>> $wardrobes
     * @return list<array<string, mixed>>
     */
    private function kitsForPage(int $tenantId, int $userId, array $wardrobes): array
    {
        try {
            $service = $this->kitService();
            $rows = $this->canManageKits() ? $service->listForTenant($tenantId) : $service->listForMember($tenantId, $userId);
        } catch (\Throwable) {
            return [];
        }
        $classes = [];
        foreach ($rows as $row) {
            foreach (ArsenalLoadoutItems::classes((string) ($row['payload_text'] ?? '')) as $c) {
                $classes[strtolower($c)] = $c;
            }
        }
        $catalog = $classes === [] ? [] : ($this->playerKits()?->catalogFor($tenantId, array_values($classes)) ?? []);
        $copies = [];
        foreach ($wardrobes as $w) {
            if (!empty($w['mine']) && str_starts_with((string) ($w['slug'] ?? ''), RoleKitService::COPY_SLUG_PREFIX)) {
                $copies[(string) $w['slug']] = (int) $w['id'];
            }
        }
        foreach ($rows as &$row) {
            $payload = (string) ($row['payload_text'] ?? '');
            $row['sections'] = ArsenalLoadoutItems::grouped($payload, $catalog);
            $row['preview'] = ArsenalLoadoutItems::previewItems(ArsenalLoadoutItems::previewClasses($payload), $catalog);
            $row['copy_wardrobe_id'] = $copies[RoleKitService::COPY_SLUG_PREFIX . (int) $row['id']] ?? 0;
        }
        unset($row);

        return $rows;
    }

    private function kitService(): RoleKitService
    {
        return \App\Core\Container::get(RoleKitService::class);
    }

    /** Message commun quand la formule ne comprend pas les kits de départ et l'assistant. */
    private function kitsLockedRedirect(string $to): Response
    {
        Session::flash('error', 'Les kits de départ et l’assistant de création sont réservés aux formules Pro et plus. Consulter et créer vos propres tenues reste ouvert à tous.');

        return Response::redirect($to);
    }

    /**
     * Transforme une tenue remontée du jeu en kit de départ et le propose à une unité, à un rôle ou à tous.
     */
    public function storeKitFromWardrobe(Request $request, array $params = []): Response
    {
        $gate = $this->gate();
        if ($gate instanceof Response) {
            return $gate;
        }
        [$tenantId, $userId] = $gate;
        $back = url('equipment') . '?espace=kits';
        if (!Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect($back);
        }
        if (!KitsAccess::allows($tenantId)) {
            return $this->kitsLockedRedirect($back);
        }
        if (!$this->canManageKits()) {
            Session::flash('error', 'Droits insuffisants pour créer un kit de départ.');

            return Response::redirect($back);
        }
        $result = $this->kitService()->saveFromWardrobe(
            $tenantId,
            $userId,
            (int) $request->input('wardrobe_id', 0),
            trim((string) $request->input('name', '')),
            trim((string) $request->input('description', '')),
            $request->input('audience', 'all'),
        );
        if (!$result['ok']) {
            Session::flash('error', (string) ($result['error'] ?? 'Enregistrement impossible.'));

            return Response::redirect($back);
        }
        Session::flash('success', 'Kit de départ créé : les membres concernés le voient dans leur section et peuvent le copier.');
        if (!empty($result['warnings'])) {
            Session::flash('error', implode(' ', $result['warnings']));
        }

        return Response::redirect($back);
    }

    /**
     * Copie un kit de départ proposé au membre dans ses propres tenues.
     */
    public function copyKit(Request $request, array $params = []): Response
    {
        $gate = $this->gate();
        if ($gate instanceof Response) {
            return $gate;
        }
        [$tenantId, $userId] = $gate;
        $back = url('equipment') . '?espace=kits';
        if (!Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect($back);
        }
        if (!KitsAccess::allows($tenantId)) {
            return $this->kitsLockedRedirect($back);
        }
        $result = $this->kitService()->copyToMember($tenantId, $userId, (int) ($params['id'] ?? 0), $this->canManageKits());
        if (!$result['ok']) {
            Session::flash('error', (string) ($result['error'] ?? 'Copie impossible.'));

            return Response::redirect($back);
        }
        Session::flash('success', !empty($result['created'])
            ? 'Kit copié dans vos tenues. Il est personnel : vous seul le voyez, et vous pouvez le modifier.'
            : 'Votre copie de ce kit est à jour.');

        return Response::redirect(url('equipment') . '?tenue=' . (int) $result['id']);
    }

    /**
     * Change l'unité ou le rôle à qui un kit est proposé.
     */
    public function updateKitAudience(Request $request, array $params = []): Response
    {
        $gate = $this->gate();
        if ($gate instanceof Response) {
            return $gate;
        }
        [$tenantId, $userId] = $gate;
        $back = url('equipment') . '?espace=kits';
        if (!Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect($back);
        }
        if (!KitsAccess::allows($tenantId)) {
            return $this->kitsLockedRedirect($back);
        }
        if (!$this->canManageKits()) {
            Session::flash('error', 'Droits insuffisants pour modifier un kit de départ.');

            return Response::redirect($back);
        }
        $result = $this->kitService()->updateAudience($tenantId, $userId, (int) ($params['id'] ?? 0), $request->input('audience', 'all'));
        Session::flash($result['ok'] ? 'success' : 'error', $result['ok'] ? 'Destinataire du kit mis à jour.' : (string) ($result['error'] ?? 'Mise à jour impossible.'));

        return Response::redirect($back);
    }

    /**
     * Supprime un kit de départ. Les copies déjà faites par les membres restent dans leurs tenues.
     */
    public function destroyKit(Request $request, array $params = []): Response
    {
        $gate = $this->gate();
        if ($gate instanceof Response) {
            return $gate;
        }
        [$tenantId] = $gate;
        $back = url('equipment') . '?espace=kits';
        if (!Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect($back);
        }
        if (!$this->canManageKits()) {
            Session::flash('error', 'Droits insuffisants pour supprimer un kit de départ.');

            return Response::redirect($back);
        }
        // Suppression permise même si la formule a baissé : on ne garde pas un kit que l'on ne peut plus gérer.
        $ok = $this->kitService()->delete($tenantId, (int) ($params['id'] ?? 0));
        Session::flash($ok ? 'success' : 'error', $ok ? 'Kit supprimé. Les copies des membres restent dans leurs tenues.' : 'Ce kit n’existe plus.');

        return Response::redirect($back);
    }

    /**
     * Assistant de création de tenue : part d'un emploi, propose des objets que la communauté a réellement remontés du
     * jeu, signale ce qui manque. Réservé aux formules avec `equipment_kits` ; sinon présentation de l'offre.
     */
    public function assistant(Request $request, array $params = []): Response
    {
        $gate = $this->gate();
        if ($gate instanceof Response) {
            return $gate;
        }
        [$tenantId] = $gate;
        $view = [
            'title' => 'Assistant de création de tenue',
            'content' => 'equipment.assistant',
            'equipmentHubPage' => true,
            'csrfToken' => Csrf::token(),
            'flash_success' => Session::getFlash('success'),
            'flash_error' => Session::getFlash('error'),
            'assistantLocked' => !KitsAccess::allows($tenantId),
            'canManageKits' => $this->canManageKits(),
            'wantsKit' => $this->canManageKits() && (string) $request->query('cible', '') === 'kit',
            'upgradeUrl' => url('platform/upgrade') . '?from=equipment_kits&feature=equipment_kits',
            'catalogCount' => 0,
            'roleChoices' => [],
            'profileOptions' => OutfitAssistant::profileOptions(),
            'selectedRole' => 0,
            'profile' => '',
            'proposal' => null,
            'preview' => [],
            'unitOptions' => [],
            'roleOptions' => [],
        ];
        if ($view['assistantLocked']) {
            return Response::view('layout.main', $view);
        }

        try {
            /** @var GameEquipmentRepository $equipment */
            $equipment = \App\Core\Container::get(GameEquipmentRepository::class);
            $catalogue = $equipment->catalogue($tenantId, 6000);
        } catch (\Throwable) {
            $catalogue = [];
        }
        $view['catalogCount'] = count($catalogue);
        $roleChoices = [];
        try {
            /** @var PersonnelJobRoleRepository $jobs */
            $jobs = \App\Core\Container::get(PersonnelJobRoleRepository::class);
            foreach ($jobs->listRolesWithCategory($tenantId) as $r) {
                $name = trim((string) ($r['name'] ?? ''));
                if ((int) ($r['id'] ?? 0) > 0 && $name !== '') {
                    $roleChoices[] = [
                        'id' => (int) $r['id'],
                        'name' => $name,
                        'abbreviation' => trim((string) ($r['abbreviation'] ?? '')),
                        'category' => trim((string) ($r['category_name'] ?? '')),
                    ];
                }
            }
        } catch (\Throwable) {
            $roleChoices = [];
        }
        $view['roleChoices'] = $roleChoices;
        $roleId = (int) $request->query('role', 0);
        $role = null;
        foreach ($roleChoices as $rc) {
            if ($rc['id'] === $roleId) {
                $role = $rc;
                break;
            }
        }
        $profile = trim((string) $request->query('profil', ''));
        if ($profile === '' && $role !== null) {
            $profile = OutfitAssistant::profileForRole($role['name'], $role['abbreviation'], $role['category']);
        }
        $view['selectedRole'] = $role['id'] ?? 0;
        $view['profile'] = isset(OutfitAssistant::PROFILES[$profile]) ? $profile : '';
        $view['detectedFromRole'] = $role !== null && trim((string) $request->query('profil', '')) === '';
        $view['unitOptions'] = $view['canManageKits'] ? $this->kitService()->unitOptions($tenantId) : [];
        $view['roleOptions'] = $view['canManageKits'] ? $this->kitService()->roleOptions($tenantId) : [];
        $view['unitsReady'] = $this->kitService()->unitsReady();
        if ($view['profile'] !== '' && $catalogue !== []) {
            $proposal = (new OutfitAssistant())->propose(
                $view['profile'],
                $catalogue,
                (new ObservedLoadouts())->weaponSetups($tenantId),
                $this->assistantChoices($request->query('slot'), $request->query('extra')),
            );
            $view['proposal'] = $this->withIcons($tenantId, $proposal);
            $picks = $this->assistantPicks($request->query('slot'));
            $expanded = $picks === [] ? ['combos' => [[]], 'truncated' => false] : OutfitAssistant::expandVariants($picks);
            $view['variantCount'] = count($expanded['combos']);
            $view['variantsTruncated'] = $expanded['truncated'];
            $view['preview'] = $proposal['payload'] !== '' ? ArsenalLoadoutItems::grouped($proposal['payload'], $proposal['names']) : [];
            $view['suggestedName'] = trim((string) $request->query('nom', '')) !== ''
                ? trim((string) $request->query('nom', ''))
                : ($role !== null ? $role['name'] . ' · ' : '') . $proposal['profile_label'];
        }

        return Response::view('layout.main', $view);
    }

    /**
     * Enregistre la proposition de l'assistant : en tenue personnelle (tout membre) ou en kit de départ (responsable).
     * Le contenu est recalculé ici d'après le catalogue de la communauté : rien du formulaire n'est pris tel quel.
     */
    public function assistantSave(Request $request, array $params = []): Response
    {
        $gate = $this->gate();
        if ($gate instanceof Response) {
            return $gate;
        }
        [$tenantId, $userId] = $gate;
        $profile = OutfitAssistant::normalizeProfile($request->input('profil', ''));
        $roleId = (int) $request->input('role', 0);
        $back = url('equipment/assistant') . '?profil=' . rawurlencode($profile) . ($roleId > 0 ? '&role=' . $roleId : '');
        if (!Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect($back);
        }
        if (!KitsAccess::allows($tenantId)) {
            return $this->kitsLockedRedirect(url('equipment'));
        }
        $name = trim(preg_replace('/\s+/u', ' ', (string) $request->input('nom', '')) ?? '');
        if ($name === '') {
            Session::flash('error', 'Donnez un nom à la tenue.');

            return Response::redirect($back);
        }
        try {
            /** @var GameEquipmentRepository $equipment */
            $equipment = \App\Core\Container::get(GameEquipmentRepository::class);
            $catalogue = $equipment->catalogue($tenantId, 6000);
        } catch (\Throwable) {
            $catalogue = [];
        }
        if ($catalogue === []) {
            Session::flash('error', 'Aucun objet n’a encore été remonté du jeu : l’assistant n’a rien à proposer.');

            return Response::redirect($back);
        }
        $assistant = new OutfitAssistant();
        $setups = (new ObservedLoadouts())->weaponSetups($tenantId);
        $choices = $this->assistantChoices($request->input('slot'), $request->input('extra'));
        // Plusieurs objets choisis pour un emplacement : une tenue par combinaison (au plus OutfitAssistant::MAX_VARIANTS).
        $picks = $this->assistantPicks($request->input('slot'));
        $expanded = $picks === [] ? ['combos' => [[]], 'truncated' => false] : OutfitAssistant::expandVariants($picks);
        $names = [];
        foreach ($catalogue as $it) {
            $names[strtolower((string) ($it['class'] ?? ''))] = trim((string) ($it['name'] ?? ''));
        }
        $variants = [];
        foreach ($expanded['combos'] as $combo) {
            $proposal = $assistant->propose($profile, $catalogue, $setups, $combo === [] ? $choices : ['slot' => $combo] + $choices);
            if ($proposal['payload'] === '' || !$proposal['ace_ok']) {
                Session::flash('error', 'Choisissez au moins un objet : la tenue est vide ou illisible par l’arsenal ACE.');

                return Response::redirect($back);
            }
            $parts = [];
            if (count($expanded['combos']) > 1) {
                foreach ($picks as $slotKey => $list) {
                    $class = (string) ($combo[$slotKey] ?? '');
                    if (count($list) > 1 && $class !== '') {
                        $parts[] = ($names[strtolower($class)] ?? '') !== '' ? $names[strtolower($class)] : $class;
                    }
                }
            }
            $variants[] = ['proposal' => $proposal, 'suffix' => implode(' + ', $parts)];
        }
        $fullName = static fn (string $suffix, int $max): string => mb_substr($suffix === '' ? $name : $name . ' — ' . $suffix, 0, $max);
        $cut = $expanded['truncated'] ? ' Seules les ' . count($variants) . ' premières tenues ont été créées.' : '';

        if ((string) $request->input('cible', 'tenue') === 'kit') {
            if (!$this->canManageKits()) {
                Session::flash('error', 'Droits insuffisants pour créer un kit de départ.');

                return Response::redirect($back);
            }
            $made = 0;
            foreach ($variants as $variant) {
                $result = $this->kitService()->save($tenantId, $userId, null, [
                    'name' => $fullName($variant['suffix'], 90),
                    'description' => trim((string) $request->input('description', '')),
                    'audience' => $request->input('audience', 'all'),
                    'kit' => $variant['proposal']['kit'],
                    'payload' => $variant['proposal']['payload'],
                    'source' => 'assistant',
                ]);
                if (!$result['ok']) {
                    Session::flash('error', (string) ($result['error'] ?? 'Enregistrement impossible.') . ($made > 0 ? ' ' . $made . ' kit' . ($made > 1 ? 's' : '') . ' déjà créé' . ($made > 1 ? 's' : '') . '.' : ''));

                    return Response::redirect($back);
                }
                ++$made;
            }
            Session::flash('success', ($made > 1 ? $made . ' kits de départ créés' : 'Kit de départ créé') . ' : les membres concernés le voient dans leur section et peuvent le copier.' . $cut);

            return Response::redirect(url('equipment') . '?espace=kits');
        }

        $taken = [];
        foreach ($this->repo->listWardrobesForUser($tenantId, $userId) as $w) {
            if ((string) ($w['source'] ?? '') !== 'assistant') {
                $taken[(string) ($w['slug'] ?? '')] = true;
            }
        }
        $ids = [];
        foreach ($variants as $variant) {
            $title = $fullName($variant['suffix'], 120);
            $slug = 'assistant-' . ArsenalWardrobeRepository::slugify($title);
            $base = $slug;
            for ($n = 2; isset($taken[$slug]); $n++) {
                $slug = $base . '-' . $n;
            }
            $taken[$slug] = true;
            try {
                $wardrobe = $this->repo->upsertWardrobe($tenantId, $userId, [
                    'name' => $title,
                    'slug' => $slug,
                    'payload_text' => $variant['proposal']['payload'],
                    'payload_format' => 'arma_loadout_str',
                    'source' => 'assistant',
                    'notes' => mb_substr('Créée avec l’assistant · ' . $variant['proposal']['profile_label'], 0, 255),
                ]);
            } catch (\Throwable $e) {
                error_log('[equipment-assistant] save failed: ' . $e->getMessage());
                Session::flash('error', 'Enregistrement impossible.');

                return Response::redirect($back);
            }
            $id = (int) ($wardrobe['id'] ?? 0);
            if ($id < 1) {
                Session::flash('error', 'Enregistrement impossible.');

                return Response::redirect($back);
            }
            $ids[] = $id;
        }
        if (count($ids) > 1) {
            Session::flash('success', count($ids) . ' tenues enregistrées dans vos tenues, une par choix. Elles sont personnelles tant que vous ne les partagez pas.' . $cut);

            return Response::redirect(url('equipment'));
        }
        Session::flash('success', 'Tenue enregistrée dans vos tenues. Elle est personnelle tant que vous ne la partagez pas.');

        return Response::redirect(url('equipment') . '?tenue=' . $ids[0]);
    }

    /**
     * Choix de l'utilisateur envoyés par le formulaire de l'assistant ; vide = proposition par défaut.
     *
     * @return array{slot?: array<string, string>, extra?: array<string, bool>}
     */
    private function assistantChoices(mixed $slot, mixed $extra): array
    {
        if (!is_array($slot)) {
            return [];
        }
        $slots = [];
        foreach ($slot as $key => $value) {
            if (!is_string($key) || !isset(OutfitAssistant::SLOTS[$key])) {
                continue;
            }
            // slot[emplacement] = un objet (ancienne adresse) ou slot[emplacement][] = plusieurs objets (variantes).
            if (is_string($value)) {
                $slots[$key] = trim($value);
            } elseif (is_array($value)) {
                $slots[$key] = array_values(array_filter(array_map(static fn (mixed $v): string => is_string($v) ? mb_substr(trim($v), 0, 160) : '', $value), static fn (string $v): bool => $v !== ''));
            }
        }
        $extras = [];
        if (is_array($extra)) {
            foreach ($extra as $key => $value) {
                if (is_string($key) && preg_match('/^[a-z_]{1,32}$/', $key) === 1 && !empty($value)) {
                    $extras[$key] = true;
                }
            }
        }

        return ['slot' => $slots, 'extra' => $extras];
    }

    /**
     * Objets choisis par emplacement quand il y en a plusieurs (chaque objet de plus donne une variante de la tenue).
     *
     * @return array<string, list<string>>
     */
    private function assistantPicks(mixed $slot): array
    {
        if (!is_array($slot)) {
            return [];
        }
        $out = [];
        foreach ($this->assistantChoices($slot, [])['slot'] ?? [] as $key => $value) {
            $out[$key] = is_array($value) ? $value : ($value === '' ? [] : [$value]);
        }

        return $out;
    }

    /**
     * Ajoute à chaque objet proposé son icône (vignette) quand la communauté en a une.
     *
     * @param array<string, mixed> $proposal
     * @return array<string, mixed>
     */
    private function withIcons(int $tenantId, array $proposal): array
    {
        $classes = [];
        foreach ((array) ($proposal['slots'] ?? []) as $slot) {
            foreach ((array) ($slot['candidates'] ?? []) as $c) {
                $classes[] = (string) ($c['class'] ?? '');
            }
        }
        try {
            $icons = (new \App\Repositories\GameItemIconRepository())->iconUrls($tenantId, $classes);
        } catch (\Throwable) {
            $icons = [];
        }
        foreach ($proposal['slots'] as $key => $slot) {
            foreach ($slot['candidates'] as $i => $c) {
                $proposal['slots'][$key]['candidates'][$i]['icon'] = $icons[(string) $c['class']] ?? '';
            }
        }

        return $proposal;
    }

    /**
     * « Mon kit en jeu » → tenue personnelle (mise à jour à chaque enregistrement, jamais dupliquée).
     */
    public function saveMyGameKit(Request $request, array $params = []): Response
    {
        $gate = $this->gate();
        if ($gate instanceof Response) {
            return $gate;
        }
        [$tenantId, $userId] = $gate;
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Rechargez la page et recommencez.');

            return Response::redirect(url('equipment'));
        }
        $result = $this->playerKits()?->saveAsWardrobe($tenantId, $userId, '', false)
            ?? ['ok' => false, 'error' => 'Enregistrement impossible.'];
        if (!$result['ok']) {
            Session::flash('error', (string) ($result['error'] ?? 'Enregistrement impossible.'));

            return Response::redirect(url('equipment'));
        }
        Session::flash('success', 'Votre kit en jeu est enregistré dans vos tenues.');

        return Response::redirect(url('equipment') . '?tenue=' . (int) $result['id']);
    }

    private function playerKits(): ?\App\Services\GameEquipment\PlayerKitService
    {
        try {
            return \App\Core\Container::get(\App\Services\GameEquipment\PlayerKitService::class);
        } catch (\Throwable) {
            return null;
        }
    }

    public function redirectHub(Request $request, array $params = []): Response
    {
        return Response::redirect(url('equipment'));
    }

    public function streamCover(Request $request, array $params = []): Response
    {
        $gate = $this->gate();
        if ($gate instanceof Response) {
            return $gate;
        }
        [$tenantId] = $gate;
        $coverTenantId = (int) ($params['tenantId'] ?? 0);
        $file = basename(rawurldecode((string) ($params['file'] ?? '')));

        return EquipmentCoverStorage::streamCover($tenantId, $coverTenantId, $file);
    }

    public function showCollection(Request $request, array $params = []): Response
    {
        $gate = $this->gate();
        if ($gate instanceof Response) {
            return $gate;
        }
        [$tenantId, $userId] = $gate;
        $id = (int) ($params['id'] ?? 0);
        $collection = $id > 0 ? $this->repo->findCollection($tenantId, $id) : null;
        if ($collection === null) {
            Session::flash('error', 'Cette collection n’existe pas.');

            return Response::redirect(url('equipment'));
        }
        $ownerId = (int) ($collection['owner_user_id'] ?? 0);
        $visibility = (string) ($collection['visibility'] ?? 'personal');
        if ($ownerId !== $userId && $visibility === 'personal') {
            Session::flash('error', 'Cette collection n’est pas partagée.');

            return Response::redirect(url('equipment'));
        }

        if ($this->wantsJson($request)) {
            $all = $this->repo->listAccessibleWardrobes($tenantId, $userId);
            $selectedIds = [];
            foreach ($all as $w) {
                if ((int) ($w['collection_id'] ?? 0) === $id && !empty($w['mine'])) {
                    $selectedIds[] = (int) $w['id'];
                }
            }
            $mine = array_values(array_filter($all, static fn (array $w): bool => !empty($w['mine'])));

            return Response::json([
                'ok' => true,
                'collection' => [
                    'id' => (int) ($collection['id'] ?? 0),
                    'name' => (string) ($collection['name'] ?? ''),
                    'description' => (string) ($collection['description'] ?? ''),
                    'visibility' => (string) ($collection['visibility'] ?? 'personal'),
                    'cover_url' => $collection['cover_url'] ?? null,
                    'mine' => $ownerId === $userId,
                    'selected_ids' => $selectedIds,
                ],
                'mine_wardrobes' => array_map(static function (array $w): array {
                    return [
                        'id' => (int) ($w['id'] ?? 0),
                        'name' => (string) ($w['name'] ?? ''),
                        'display_name' => (string) ($w['display_name'] ?? ''),
                        'cover_url' => $w['cover_url'] ?? null,
                        'kinds' => array_values(is_array($w['kinds'] ?? null) ? $w['kinds'] : []),
                    ];
                }, $mine),
                'csrf' => Csrf::token(),
                'urls' => [
                    'update' => url('equipment/collections/' . $id),
                    'delete' => url('equipment/collections/' . $id . '/delete'),
                ],
            ]);
        }

        $editQs = ($ownerId === $userId && $request->query('edit') === '1') ? '&edit_collection=1' : '';

        return Response::redirect(url('equipment') . '?collection=' . $id . $editQs);
    }

    /**
     * Export pour l’arsenal ACE (texte SQF) : ?tenue=ID → une tenue (Importer, mannequin) ; sinon la liste
     * [[nom, tenue], …] des tenues de la communauté, ou d’une collection (?collection=ID), pour « Mes équipements ».
     * ?inline=1 affiche le texte au lieu de le télécharger.
     */
    public function aceExport(Request $request, array $params = []): Response
    {
        $gate = $this->gate();
        if ($gate instanceof Response) {
            return $gate;
        }
        [$tenantId, $userId] = $gate;
        if (!$this->repo->tablesReady()) {
            return Response::redirect(url('equipment'));
        }
        $tenueId = (int) $request->query('tenue', 0);
        $collectionId = (int) $request->query('collection', 0);
        $label = 'tenues-athena';
        if ($tenueId > 0) {
            $row = $this->repo->findWardrobe($tenantId, $tenueId);
            if ($row !== null && !ArsenalWardrobeRepository::canView($row, $userId)) {
                $row = null;
            }
            $text = $row !== null ? ArsenalLoadoutItems::aceExport((string) ($row['payload_text'] ?? '')) : null;
            if ($text === null) {
                Session::flash('error', 'Cette tenue ne peut pas être exportée pour ACE.');

                return Response::redirect(url('equipment'));
            }
            $label = 'tenue-' . ArsenalWardrobeRepository::slugify((string) ($row['name'] ?? 'tenue'));
        } else {
            if ($collectionId > 0) {
                $collection = $this->repo->findCollection($tenantId, $collectionId);
                $ownerId = (int) ($collection['owner_user_id'] ?? 0);
                if ($collection === null || ($ownerId > 0 && $ownerId !== $userId && (string) ($collection['visibility'] ?? 'personal') === 'personal')) {
                    Session::flash('error', 'Cette collection n’existe pas ou n’est pas partagée.');

                    return Response::redirect(url('equipment'));
                }
                $label = 'collection-' . ArsenalWardrobeRepository::slugify((string) ($collection['name'] ?? 'collection'));
            }
            $text = ArsenalLoadoutItems::aceExportList($this->repo->exportPayloads($tenantId, $collectionId > 0 ? $collectionId : null, $userId));
        }

        $inline = $request->query('inline') === '1';
        $response = new Response();
        $response->header('Content-Type', 'text/plain; charset=UTF-8');
        $response->header('Cache-Control', 'no-store');
        $response->header('X-Content-Type-Options', 'nosniff');
        $response->header('Content-Disposition', ($inline ? 'inline' : 'attachment') . '; filename="ace-' . $label . '.txt"');
        $response->setBody($text);

        return $response;
    }

    public function showWardrobe(Request $request, array $params = []): Response
    {
        $gate = $this->gate();
        if ($gate instanceof Response) {
            return $gate;
        }
        [$tenantId, $userId] = $gate;
        $id = (int) ($params['id'] ?? 0);
        $row = $id > 0 ? $this->repo->findWardrobe($tenantId, $id) : null;
        if ($row !== null && !ArsenalWardrobeRepository::canView($row, $userId)) {
            $row = null;
        }
        if ($row === null) {
            if ($this->wantsJson($request)) {
                return Response::json(['ok' => false, 'error' => 'not_found'], 404);
            }
            Session::flash('error', 'Cette tenue n’existe pas.');

            return Response::redirect(url('equipment'));
        }
        $row['mine'] = (int) ($row['user_id'] ?? 0) === $userId;
        $payloadText = (string) ($row['payload_text'] ?? '');
        $loadoutItems = ArsenalLoadoutItems::grouped(
            $payloadText,
            $this->playerKits()?->catalogFor($tenantId, ArsenalLoadoutItems::classes($payloadText)) ?? [],
        );
        $kinds = ArsenalLoadoutItems::presentKinds((string) ($row['payload_text'] ?? ''));
        $aceExport = ArsenalLoadoutItems::aceExport($payloadText);
        unset($row['payload_text']);

        if ($this->wantsJson($request)) {
            $collections = $row['mine'] ? $this->repo->listCollections($tenantId, $userId) : [];
            $gallery = [];
            if (!empty($row['cover_url'])) {
                $gallery[] = (string) $row['cover_url'];
            }
            foreach ($row['gallery_urls'] ?? [] as $url) {
                if (is_string($url) && $url !== '' && !in_array($url, $gallery, true)) {
                    $gallery[] = $url;
                }
            }

            return Response::json([
                'ok' => true,
                'wardrobe' => [
                    'id' => (int) ($row['id'] ?? 0),
                    'name' => (string) ($row['name'] ?? ''),
                    'display_name' => (string) ($row['display_name'] ?? ArsenalLoadoutItems::formatWardrobeTitle((string) ($row['name'] ?? ''))),
                    'description' => (string) ($row['description'] ?? ''),
                    'cover_url' => $row['cover_url'] ?? null,
                    'gallery' => $gallery,
                    'gallery_paths' => array_values(is_array($row['gallery_paths'] ?? null) ? $row['gallery_paths'] : []),
                    'collection_id' => $row['collection_id'] ?? null,
                    'collection_name' => $row['collection_name'] ?? null,
                    'notes' => $row['mine'] ? (string) ($row['notes'] ?? '') : '',
                    'visibility' => (string) ($row['visibility'] ?? 'tenant'),
                    'owner_label' => (string) ($row['owner_label'] ?? ''),
                    'mine' => !empty($row['mine']),
                    'can_edit' => !empty($row['mine']),
                    'kinds' => $kinds,
                    'loadout_items' => $loadoutItems,
                    'ace_export' => $aceExport,
                    'ace_export_url' => $aceExport !== null ? url('equipment/ace-export') . '?tenue=' . $id : null,
                    'usage_hint' => 'En jeu : arsenal ACE, bandeau « Athena · Tenues » en haut de l’écran, ou « Copier pour ACE » puis Importer dans l’arsenal (une tenue à la fois).',
                ],
                'collections' => array_map(static function (array $c): array {
                    return [
                        'id' => (int) ($c['id'] ?? 0),
                        'name' => (string) ($c['name'] ?? ''),
                    ];
                }, $collections),
                'csrf' => Csrf::token(),
                'urls' => [
                    'update' => url('equipment/tenues/' . $id),
                    'delete' => url('equipment/tenues/' . $id . '/delete'),
                ],
            ]);
        }

        // Lien direct → catalogue avec quick-view (préserve la logique hub).
        return Response::redirect(url('equipment') . '?tenue=' . $id);
    }

    public function storeCollection(Request $request, array $params = []): Response
    {
        $gate = $this->gate();
        if ($gate instanceof Response) {
            return $gate;
        }
        [$tenantId, $userId] = $gate;
        if (!Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url('equipment'));
        }
        $name = trim((string) $request->input('name', ''));
        if ($name === '') {
            Session::flash('error', 'Donnez un nom à la collection.');

            return Response::redirect(url('equipment'));
        }
        try {
            $ids = $request->input('wardrobe_ids', []);
            if (!is_array($ids)) {
                $ids = [];
            }
            $created = $this->repo->upsertCollection($tenantId, $userId, [
                'name' => $name,
                'description' => trim((string) $request->input('description', '')),
                'visibility' => (string) $request->input('visibility', 'personal'),
                'wardrobe_ids' => $ids,
            ]);
            $cover = $this->maybeStoreCover($tenantId, 'collection', $_FILES['cover'] ?? []);
            if ($cover['error'] !== null) {
                Session::flash('error', $cover['error']);
            } elseif ($cover['path'] !== null && (int) ($created['id'] ?? 0) > 0) {
                $this->repo->setCollectionCover($tenantId, $userId, (int) $created['id'], $cover['path']);
            }
            Session::flash('success', 'Collection créée.');
            if ((int) ($created['id'] ?? 0) > 0) {
                return Response::redirect(url('equipment') . '?collection=' . (int) $created['id']);
            }
        } catch (\Throwable) {
            Session::flash('error', 'Impossible de créer la collection.');
        }

        return Response::redirect(url('equipment'));
    }

    public function updateCollection(Request $request, array $params = []): Response
    {
        $gate = $this->gate();
        if ($gate instanceof Response) {
            return $gate;
        }
        [$tenantId, $userId] = $gate;
        $id = (int) ($params['id'] ?? 0);
        if (!Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url('equipment') . '?collection=' . $id);
        }
        $existing = $this->repo->findCollection($tenantId, $id);
        if ($existing === null || (int) ($existing['owner_user_id'] ?? 0) !== $userId) {
            Session::flash('error', 'Vous ne pouvez pas modifier cette collection.');

            return Response::redirect(url('equipment'));
        }
        $ids = $request->input('wardrobe_ids', []);
        if (!is_array($ids)) {
            $ids = [];
        }
        try {
            $this->repo->upsertCollection($tenantId, $userId, [
                'id' => $id,
                'name' => trim((string) $request->input('name', $existing['name'] ?? '')),
                'description' => trim((string) $request->input('description', '')),
                'visibility' => (string) $request->input('visibility', $existing['visibility'] ?? 'personal'),
                'wardrobe_ids' => $ids,
            ]);
            $cover = $this->maybeStoreCover($tenantId, 'collection', $_FILES['cover'] ?? []);
            if ($cover['error'] !== null) {
                Session::flash('error', $cover['error']);
            } elseif ($cover['path'] !== null) {
                $this->repo->setCollectionCover($tenantId, $userId, $id, $cover['path']);
            }
            Session::flash('success', 'Collection mise à jour.');
        } catch (\Throwable) {
            Session::flash('error', 'Impossible d’enregistrer la collection.');
        }

        return Response::redirect(url('equipment') . '?collection=' . $id);
    }

    public function updateWardrobe(Request $request, array $params = []): Response
    {
        $gate = $this->gate();
        if ($gate instanceof Response) {
            return $gate;
        }
        [$tenantId, $userId] = $gate;
        $id = (int) ($params['id'] ?? 0);
        if (!Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return $this->wardrobeMutateRedirect($request, $id);
        }
        $row = $this->repo->findWardrobe($tenantId, $id, $userId);
        if ($row === null) {
            Session::flash('error', 'Vous ne pouvez modifier que vos propres tenues.');

            return Response::redirect(url('equipment'));
        }
        $newName = $request->input('name');
        if (is_string($newName)) {
            $renamed = $this->repo->renameWardrobe($tenantId, $userId, $id, $newName);
            if ($renamed === 'empty' || $renamed === 'duplicate') {
                Session::flash('error', $renamed === 'empty'
                    ? 'Donnez un nom à la tenue.'
                    : 'Vous avez déjà une tenue portant ce nom. Choisissez-en un autre.');

                return $this->wardrobeMutateRedirect($request, $id);
            }
        }
        $visibility = $request->input('visibility');
        if (is_string($visibility) && in_array($visibility, ArsenalWardrobeRepository::WARDROBE_VISIBILITIES, true)) {
            $this->repo->updateWardrobeVisibility($tenantId, $userId, $id, $visibility);
        }
        $collectionId = (int) $request->input('collection_id', 0);
        $this->repo->assignWardrobeCollection($tenantId, $userId, $id, $collectionId > 0 ? $collectionId : null);
        $this->repo->updateWardrobeNotes($tenantId, $userId, $id, trim((string) $request->input('notes', '')));
        $this->repo->updateWardrobeDescription($tenantId, $userId, $id, trim((string) $request->input('description', '')));

        $cover = $this->maybeStoreCover($tenantId, 'wardrobe', $_FILES['cover'] ?? []);
        if ($cover['error'] !== null) {
            Session::flash('error', $cover['error']);

            return $this->wardrobeMutateRedirect($request, $id);
        }
        if ($cover['path'] !== null) {
            $this->repo->setWardrobeCover($tenantId, $userId, $id, $cover['path']);
        }

        $galleryPaths = array_values(is_array($row['gallery_paths'] ?? null) ? $row['gallery_paths'] : []);
        $remove = $request->input('remove_gallery', []);
        if (!is_array($remove)) {
            $remove = [];
        }
        $removeSet = [];
        foreach ($remove as $p) {
            $removeSet[trim((string) $p)] = true;
        }
        $kept = [];
        foreach ($galleryPaths as $path) {
            if (!isset($removeSet[$path])) {
                $kept[] = $path;
            } else {
                EquipmentCoverStorage::delete($path);
            }
        }
        foreach ($this->normalizeUploadList($_FILES['gallery'] ?? null) as $file) {
            if (count($kept) >= 5) {
                break;
            }
            $stored = $this->maybeStoreCover($tenantId, 'wardrobe', $file);
            if ($stored['error'] !== null) {
                Session::flash('error', $stored['error']);
                break;
            }
            if ($stored['path'] !== null) {
                $kept[] = $stored['path'];
            }
        }
        $this->repo->setWardrobeGallery($tenantId, $userId, $id, $kept);
        Session::flash('success', 'Tenue mise à jour.');

        return $this->wardrobeMutateRedirect($request, $id);
    }

    public function destroyWardrobe(Request $request, array $params = []): Response
    {
        $gate = $this->gate();
        if ($gate instanceof Response) {
            return $gate;
        }
        [$tenantId, $userId] = $gate;
        if (!Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url('equipment'));
        }
        $id = (int) ($params['id'] ?? 0);
        $row = $id > 0 ? $this->repo->findWardrobe($tenantId, $id, $userId) : null;
        if ($row !== null) {
            EquipmentCoverStorage::delete(isset($row['cover_image_path']) ? (string) $row['cover_image_path'] : null);
            foreach ($row['gallery_paths'] ?? [] as $path) {
                EquipmentCoverStorage::delete(is_string($path) ? $path : null);
            }
            $this->repo->deleteWardrobe($tenantId, $userId, $id);
            Session::flash('success', 'Tenue supprimée.');
        } else {
            Session::flash('error', 'Vous ne pouvez supprimer que vos propres tenues.');
        }

        return Response::redirect(url('equipment'));
    }

    public function destroyCollection(Request $request, array $params = []): Response
    {
        $gate = $this->gate();
        if ($gate instanceof Response) {
            return $gate;
        }
        [$tenantId, $userId] = $gate;
        if (!Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url('equipment'));
        }
        $id = (int) ($params['id'] ?? 0);
        $row = $id > 0 ? $this->repo->findCollection($tenantId, $id) : null;
        if ($row !== null && (int) ($row['owner_user_id'] ?? 0) === $userId) {
            EquipmentCoverStorage::delete(isset($row['cover_image_path']) ? (string) $row['cover_image_path'] : null);
            $this->repo->deleteCollection($tenantId, $userId, $id);
            Session::flash('success', 'Collection retirée.');
        }

        return Response::redirect(url('equipment'));
    }

    /**
     * @return array{0:int,1:int}|Response
     */
    private function gate(): array|Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        if ($tenantId < 1 || $userId < 1) {
            return Response::redirect(url('login'));
        }
        if (!$this->featureGate->allows($tenantId, 'equipment')) {
            return PlanFeatureDenial::upgradeView('equipment', 'Gratuit');
        }

        return [$tenantId, $userId];
    }

    public function storeFiche(Request $request, array $params = []): Response
    {
        $gate = $this->gate();
        if ($gate instanceof Response) {
            return $gate;
        }
        [$tenantId] = $gate;
        if (!$this->canManageCatalog()) {
            Session::flash('error', 'Droits insuffisants pour créer une fiche matériel.');

            return Response::redirect(url('equipment') . '?tab=fiches');
        }
        if (!Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url('equipment') . '?tab=fiches');
        }
        $name = trim((string) $request->input('name', ''));
        if ($name === '') {
            Session::flash('error', 'Donnez un nom à la fiche.');

            return Response::redirect(url('equipment') . '?tab=fiches');
        }
        $slug = $this->classes->slugify($name);
        $base = $slug;
        $n = 2;
        while ($this->classes->slugExists($tenantId, $slug)) {
            $slug = $base . '-' . $n;
            $n++;
        }
        $id = $this->classes->create([
            'tenant_id' => $tenantId,
            'name' => $name,
            'slug' => $slug,
            'category' => trim((string) $request->input('category', '')) ?: null,
            'description' => trim((string) $request->input('description', '')) ?: null,
        ]);
        $cover = $this->maybeStoreCover($tenantId, 'figure', $_FILES['cover'] ?? []);
        if ($cover['path'] !== null) {
            $this->classes->setCover($id, $tenantId, $cover['path']);
        } elseif ($cover['error'] !== null) {
            Session::flash('error', $cover['error']);
        }
        Session::flash('success', 'Fiche matériel créée.');

        return Response::redirect(url('equipment') . '?tab=fiches&fiche=' . $id);
    }

    public function updateFiche(Request $request, array $params = []): Response
    {
        $gate = $this->gate();
        if ($gate instanceof Response) {
            return $gate;
        }
        [$tenantId] = $gate;
        $id = (int) ($params['id'] ?? 0);
        if (!$this->canManageCatalog()) {
            Session::flash('error', 'Droits insuffisants.');

            return Response::redirect(url('equipment') . '?tab=fiches');
        }
        if (!Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url('equipment') . '?tab=fiches&fiche=' . $id);
        }
        $existing = $this->classes->findById($id, $tenantId);
        if ($existing === null) {
            Session::flash('error', 'Fiche introuvable.');

            return Response::redirect(url('equipment') . '?tab=fiches');
        }
        $name = trim((string) $request->input('name', $existing['name'] ?? ''));
        $slug = $this->classes->slugify($name !== '' ? $name : (string) ($existing['name'] ?? 'equipment'));
        if ($this->classes->slugExists($tenantId, $slug, $id)) {
            $slug = (string) ($existing['slug'] ?? $slug);
        }
        $this->classes->update($id, $tenantId, [
            'name' => $name,
            'slug' => $slug,
            'category' => trim((string) $request->input('category', '')) ?: null,
            'description' => trim((string) $request->input('description', '')) ?: null,
        ]);
        $cover = $this->maybeStoreCover($tenantId, 'figure', $_FILES['cover'] ?? []);
        if ($cover['error'] !== null) {
            Session::flash('error', $cover['error']);
        } elseif ($cover['path'] !== null) {
            $this->classes->setCover($id, $tenantId, $cover['path']);
        }
        Session::flash('success', 'Fiche mise à jour.');

        return Response::redirect(url('equipment') . '?tab=fiches&fiche=' . $id);
    }

    public function showFiche(Request $request, array $params = []): Response
    {
        $gate = $this->gate();
        if ($gate instanceof Response) {
            return $gate;
        }
        [$tenantId] = $gate;
        $id = (int) ($params['id'] ?? 0);
        $row = $id > 0 ? $this->classes->findById($id, $tenantId) : null;
        if ($row === null) {
            return Response::json(['ok' => false, 'error' => 'not_found'], 404);
        }

        return Response::json([
            'ok' => true,
            'fiche' => [
                'id' => (int) ($row['id'] ?? 0),
                'name' => (string) ($row['name'] ?? ''),
                'slug' => (string) ($row['slug'] ?? ''),
                'category' => (string) ($row['category'] ?? ''),
                'description' => (string) ($row['description'] ?? ''),
                'cover_url' => $row['cover_url'] ?? null,
                'can_edit' => $this->canManageCatalog(),
            ],
            'csrf' => Csrf::token(),
            'urls' => [
                'update' => url('equipment/fiches/' . $id),
                'page' => url('equipment/' . ($row['slug'] ?? '')),
            ],
        ]);
    }

    public function storeDotationItem(Request $request, array $params = []): Response
    {
        $gate = $this->gate();
        if ($gate instanceof Response) {
            return $gate;
        }
        [$tenantId] = $gate;
        if (!$this->canManageDotation()) {
            Session::flash('error', 'Droits insuffisants pour créer un article de dotation.');

            return Response::redirect(url('equipment') . '?tab=dotation');
        }
        if (!Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url('equipment') . '?tab=dotation');
        }
        $code = strtoupper(trim((string) $request->input('code', '')));
        $name = trim((string) $request->input('name', ''));
        if ($code === '' || $name === '') {
            Session::flash('error', 'Code et nom obligatoires.');

            return Response::redirect(url('equipment') . '?tab=dotation');
        }
        $id = $this->definitions->create($tenantId, [
            'code' => $code,
            'name' => $name,
            'category' => $request->input('category'),
            'description' => $request->input('description'),
        ], (int) Session::get('user_id'));
        $cover = $this->maybeStoreCover($tenantId, 'figure', $_FILES['cover'] ?? []);
        if ($cover['path'] !== null) {
            $this->definitions->setCover($tenantId, $id, $cover['path']);
        } elseif ($cover['error'] !== null) {
            Session::flash('error', $cover['error']);
        }
        Session::flash('success', 'Article de dotation créé.');

        return Response::redirect(url('equipment') . '?tab=dotation&dotation=' . $id);
    }

    public function updateDotationItem(Request $request, array $params = []): Response
    {
        $gate = $this->gate();
        if ($gate instanceof Response) {
            return $gate;
        }
        [$tenantId] = $gate;
        $id = (int) ($params['id'] ?? 0);
        if (!$this->canManageDotation()) {
            Session::flash('error', 'Droits insuffisants.');

            return Response::redirect(url('equipment') . '?tab=dotation');
        }
        if (!Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url('equipment') . '?tab=dotation&dotation=' . $id);
        }
        $existing = $this->definitions->find($tenantId, $id);
        if ($existing === null) {
            Session::flash('error', 'Article introuvable.');

            return Response::redirect(url('equipment') . '?tab=dotation');
        }
        $this->definitions->update($tenantId, $id, [
            'code' => $request->input('code', $existing['code'] ?? ''),
            'name' => $request->input('name', $existing['name'] ?? ''),
            'category' => $request->input('category', $existing['category'] ?? ''),
            'description' => $request->input('description', $existing['description'] ?? ''),
        ]);
        $cover = $this->maybeStoreCover($tenantId, 'figure', $_FILES['cover'] ?? []);
        if ($cover['error'] !== null) {
            Session::flash('error', $cover['error']);
        } elseif ($cover['path'] !== null) {
            $this->definitions->setCover($tenantId, $id, $cover['path']);
        }
        Session::flash('success', 'Article mis à jour.');

        return Response::redirect(url('equipment') . '?tab=dotation&dotation=' . $id);
    }

    public function showDotationItem(Request $request, array $params = []): Response
    {
        $gate = $this->gate();
        if ($gate instanceof Response) {
            return $gate;
        }
        [$tenantId] = $gate;
        $id = (int) ($params['id'] ?? 0);
        $row = $id > 0 ? $this->definitions->find($tenantId, $id) : null;
        if ($row === null) {
            return Response::json(['ok' => false, 'error' => 'not_found'], 404);
        }

        return Response::json([
            'ok' => true,
            'item' => [
                'id' => (int) ($row['id'] ?? 0),
                'code' => (string) ($row['code'] ?? ''),
                'name' => (string) ($row['name'] ?? ''),
                'category' => (string) ($row['category'] ?? ''),
                'description' => (string) ($row['description'] ?? ''),
                'cover_url' => $row['cover_url'] ?? null,
                'can_edit' => $this->canManageDotation(),
                'admin_url' => url('back-office/referentiels/dotation/' . $id),
            ],
            'csrf' => Csrf::token(),
            'urls' => [
                'update' => url('equipment/dotation/' . $id),
            ],
        ]);
    }

    /**
     * @param list<array<string, mixed>> $wardrobes
     * @param list<array<string, mixed>> $collections
     * @param list<array<string, mixed>> $classes
     * @param list<array<string, mixed>> $dotation
     * @param list<array<string, mixed>> $kits kits de départ visibles (vide si la formule ne les comprend pas)
     * @param array<string, mixed>|null $myGameKit dernier kit porté en jeu (PlayerKitService::forUser)
     */
    private function hubView(
        bool $migrationMissing,
        array $wardrobes,
        array $collections,
        array $classes,
        array $dotation = [],
        array $kits = [],
        ?array $myGameKit = null,
        bool $kitsAllowed = false,
        array $nav = [],
    ): Response {
        $mine = array_values(array_filter($wardrobes, static fn (array $w): bool => !empty($w['mine'])));
        // Tenues remontées du jeu qu'un responsable peut transformer en kit (les copies de kits n'en font pas partie).
        $gameWardrobes = array_values(array_filter(
            $wardrobes,
            static fn (array $w): bool => in_array((string) ($w['source'] ?? ''), ['ace_arsenal', 'game_loadout'], true)
        ));
        $canManageKits = $kitsAllowed && $this->canManageKits();
        $catalogCount = 0;
        $kitUnits = [];
        $kitRoles = [];
        $unitsReady = false;
        if (!$migrationMissing) {
            try {
                $catalogCount = \App\Core\Container::get(GameEquipmentRepository::class)->catalogueCount((int) Session::get('tenant_id'));
            } catch (\Throwable) {
                $catalogCount = 0;
            }
            if ($canManageKits) {
                $service = $this->kitService();
                $kitUnits = $service->unitOptions((int) Session::get('tenant_id'));
                $kitRoles = $service->roleOptions((int) Session::get('tenant_id'));
                $unitsReady = $service->unitsReady();
            }
        }

        return Response::view('layout.main', [
            'content' => 'equipment.hub',
            'title' => 'Équipement',
            'equipmentHubPage' => true,
            'migrationMissing' => $migrationMissing,
            'wardrobes' => $wardrobes,
            'mineWardrobes' => $mine,
            'gameWardrobes' => $gameWardrobes,
            'collections' => $collections,
            'equipmentClasses' => $classes,
            'dotationItems' => $dotation,
            'kits' => $kits,
            'kitsAllowed' => $kitsAllowed,
            'canManageKits' => $canManageKits,
            'kitUnitOptions' => $kitUnits,
            'kitRoleOptions' => $kitRoles,
            'kitUnitsReady' => $unitsReady,
            'catalogCount' => $catalogCount,
            'upgradeUrl' => url('platform/upgrade') . '?from=equipment_kits&feature=equipment_kits',
            'myGameKit' => $myGameKit,
            'nav' => $nav + ['espace' => 'mes-tenues', 'collection' => 0, 'tab' => 'fiches', 'q' => '', 'type' => '', 'sort' => 'name', 'paged' => null, 'collection_row' => null, 'counts' => [], 'detail' => null, 'creer' => false],
            'canManageCatalog' => $this->canManageCatalog(),
            'canManageDotation' => $this->canManageDotation(),
            'equipmentKindLabels' => ArsenalLoadoutItems::kindLabels(),
            'csrfToken' => Csrf::token(),
            'flash_success' => Session::getFlash('success'),
            'flash_error' => Session::getFlash('error'),
            'coverHint' => EquipmentCoverStorage::hintText(),
        ]);
    }

    /** Responsables des kits de départ : organisation, accès, ou gestion de l'équipement (droit déjà utilisé par les fiches). */
    private function canManageKits(): bool
    {
        $gate = Gate::getInstance();

        return $gate->allows('admin.organization')
            || $gate->allows('admin.access')
            || $gate->allows('personnel.equipment.manage');
    }

    /**
     * Pièces principales de chaque tenue (nom vu en jeu, icône) pour sa carte : une seule lecture du catalogue.
     *
     * @param list<array<string, mixed>> $wardrobes
     * @return list<array<string, mixed>>
     */
    private function attachPreviews(int $tenantId, array $wardrobes): array
    {
        $all = [];
        foreach ($wardrobes as $w) {
            foreach ($w['preview_classes'] ?? [] as $c) {
                $all[strtolower((string) $c)] = (string) $c;
            }
        }
        $catalog = $all === [] ? [] : ($this->playerKits()?->catalogFor($tenantId, array_values($all)) ?? []);
        foreach ($wardrobes as &$w) {
            $w['preview'] = ArsenalLoadoutItems::previewItems(array_values($w['preview_classes'] ?? []), $catalog);
            unset($w['preview_classes']);
        }
        unset($w);

        return $wardrobes;
    }

    /**
     * @param list<array<string, mixed>> $collections
     * @param list<array<string, mixed>> $wardrobes
     * @return list<array<string, mixed>>
     */
    private function attachCollectionMosaics(array $collections, array $wardrobes): array
    {
        $byCollection = [];
        foreach ($wardrobes as $w) {
            $cid = (int) ($w['collection_id'] ?? 0);
            if ($cid < 1) {
                continue;
            }
            $url = trim((string) ($w['cover_url'] ?? ''));
            if ($url === '') {
                continue;
            }
            if (!isset($byCollection[$cid])) {
                $byCollection[$cid] = [];
            }
            if (count($byCollection[$cid]) < 4) {
                $byCollection[$cid][] = $url;
            }
        }
        foreach ($collections as &$c) {
            $id = (int) ($c['id'] ?? 0);
            $c['mosaic'] = $byCollection[$id] ?? [];
        }
        unset($c);

        return $collections;
    }

    private function wantsJson(Request $request): bool
    {
        $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
        $xhr = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
        $format = strtolower(trim((string) $request->query('format', '')));

        return $format === 'json'
            || str_contains($accept, 'application/json')
            || $xhr === 'xmlhttprequest';
    }

    private function wardrobeMutateRedirect(Request $request, int $id): Response
    {
        $back = trim((string) $request->input('_return', ''));
        if ($back === 'hub' || $back === 'catalog') {
            return Response::redirect(url('equipment') . '?tenue=' . $id);
        }

        return Response::redirect(url('equipment') . '?tenue=' . $id);
    }

    /**
     * @param array<string, mixed> $file
     * @return array{path:?string, error:?string}
     */
    private function maybeStoreCover(int $tenantId, string $kind, mixed $file): array
    {
        if (!is_array($file)) {
            return ['path' => null, 'error' => null];
        }

        return EquipmentCoverStorage::storeFromUpload($tenantId, $kind, $file);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function normalizeUploadList(mixed $files): array
    {
        if (!is_array($files) || !isset($files['name'])) {
            return [];
        }
        if (!is_array($files['name'])) {
            return [$files];
        }
        $out = [];
        foreach ($files['name'] as $i => $name) {
            $out[] = [
                'name' => $name,
                'type' => $files['type'][$i] ?? '',
                'tmp_name' => $files['tmp_name'][$i] ?? '',
                'error' => $files['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                'size' => $files['size'][$i] ?? 0,
            ];
        }

        return $out;
    }

    private function canManageCatalog(): bool
    {
        $gate = Gate::getInstance();

        return $gate->allows('admin.organization')
            || $gate->allows('admin.access')
            || $gate->allows('personnel.equipment.manage')
            || $gate->allows('site.support');
    }

    private function canManageDotation(): bool
    {
        $gate = Gate::getInstance();

        return $gate->allows('personnel.equipment.manage')
            || $gate->allows('personnel.assignments.manage')
            || $gate->allows('admin.organization')
            || $gate->allows('admin.access');
    }
}
