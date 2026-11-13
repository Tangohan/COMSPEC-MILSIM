<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\ArsenalWardrobeRepository;
use App\Repositories\EquipmentConfiguratorSocialRepository;
use App\Repositories\GameItemSpecRepository;
use App\Services\GameEquipment\Configurator\ConfiguratorCatalog;
use App\Services\GameEquipment\Configurator\ConfiguratorKinds;
use App\Services\GameEquipment\Configurator\EquipmentAudit;
use App\Services\GameEquipment\Configurator\EquipmentTrends;
use App\Services\GameEquipment\Configurator\PrekitDocument;
use App\Services\GameEquipment\Configurator\ItemCatalogImport;
use App\Services\GameEquipment\Configurator\LoadoutDocument;
use App\Services\GameEquipment\Configurator\LoadoutValidator;
use App\Services\GameEquipment\Configurator\VanillaCatalogLoader;
use App\Services\GameEquipment\KitsAccess;
use App\Services\GameEquipment\PlayerKitService;
use App\Services\GameEquipment\RoleKitLoadout;
use App\Services\GameEquipment\RoleKitService;
use App\Services\Platform\FeatureGateService;
use App\Support\PlanFeatureDenial;

/**
 * Configurateur d'équipement de la page Équipement (/equipment/configurateur) : armes et accessoires, tenues et
 * conteneurs, glisser-déposer, copie d'une tenue à l'autre. Les tenues sont enregistrées dans les systèmes existants :
 * tenues d'arsenal (arsenal_wardrobes, retrouvées en jeu par le panneau « Athena · Tenues » de COMSPEC Link) et kits
 * de départ (game_role_kits via RoleKitService, proposés aux membres, publiés en arsenal, et livrés à la caisse
 * « Prendre mon kit » du mod SSE par le paquet Athena).
 *
 * Droits : tout membre de la communauté (formule « equipment ») compose et enregistre ses propres tenues ; les kits et
 * les modèles de la communauté demandent la gestion des kits (KitsAccess + droits) ; l'import du catalogue demande la
 * gestion du catalogue. Toute écriture : jeton CSRF, charge utile validée côté serveur (LoadoutDocument +
 * LoadoutValidator), journal d'audit.
 */
final class EquipmentConfiguratorController
{
    public const MODELS_SLUG = 'modeles-equipement';

    public const MODELS_NAME = 'Modèles d’équipement';

    private const TENUE_SLUG_PREFIX = 'atelier-';

    public function __construct(
        private ?ArsenalWardrobeRepository $wardrobes = null,
        private ?ConfiguratorCatalog $catalog = null,
        private ?GameItemSpecRepository $specs = null,
        private ?VanillaCatalogLoader $vanilla = null,
        private ?EquipmentConfiguratorSocialRepository $social = null,
    ) {
        $this->wardrobes ??= new ArsenalWardrobeRepository();
        $this->catalog ??= new ConfiguratorCatalog();
        $this->specs ??= new GameItemSpecRepository();
        $this->vanilla ??= new VanillaCatalogLoader($this->specs);
        $this->social ??= new EquipmentConfiguratorSocialRepository();
    }

    public function index(Request $request, array $params = []): Response
    {
        $gate = $this->gate();
        if ($gate instanceof Response) {
            return $gate;
        }
        [$tenantId, $userId] = $gate;
        $kitsAllowed = KitsAccess::allows($tenantId);
        $canKits = $kitsAllowed && $this->canManageKits();
        $service = $canKits ? $this->kitService() : null;
        $summary = $this->specs->summary($tenantId);

        return Response::view('layout.main', [
            'content' => 'equipment.configurator',
            'title' => 'Configurateur d’équipement',
            'equipmentHubPage' => true,
            'csrfToken' => Csrf::token(),
            'cfgBoot' => [
                'urls' => [
                    'catalogue' => url('equipment/configurateur/catalogue'),
                    'sources' => url('equipment/configurateur/sources'),
                    'source' => url('equipment/configurateur/source'),
                    'analyse' => url('equipment/configurateur/analyser'),
                    'save' => url('equipment/configurateur/enregistrer'),
                    'versions' => url('equipment/configurateur/kits/'),
                    'import' => url('equipment/configurateur/catalogue/import'),
                    'base' => url('equipment/configurateur/catalogue/base'),
                    'script' => url('equipment/configurateur/script-export.sqf'),
                    'icons' => url('equipment/icones/'),
                    'hub' => url('equipment'),
                    'social' => url('equipment/configurateur/membre'),
                    'favorite' => url('equipment/configurateur/favori'),
                    'prekits' => url('equipment/configurateur/pre-kits'),
                    'wardrobeFavorite' => url('equipment/tenues/'),
                ],
                'csrf' => Csrf::token(),
                // Guide déjà vu : mémorisé par membre (clé du navigateur suffixée par le compte).
                'user' => $userId,
                'pick' => $request->queryString('recuperer') === '1',
                'open' => preg_match('/^(w|k|p):[A-Za-z0-9]{1,32}$|^me$/', $request->queryString('ouvrir')) === 1 ? $request->queryString('ouvrir') : '',
                // Objet envoyé depuis l'espace Matériel (?objet=CLASSE) : montré en tête de la palette, rien n'est posé d'office.
                'item' => preg_match(ItemCatalogImport::CLASS_RE, $request->queryString('objet')) === 1 ? $request->queryString('objet') : '',
                'can' => [
                    'kits' => $canKits,
                    'catalog' => $this->canManageCatalog(),
                    'members' => $canKits,
                    // Pré-kit partagé à la communauté : responsables des kits (un pré-kit personnel : tout membre).
                    'sharePrekits' => $this->canManageKits(),
                ],
                'kitsAllowed' => $kitsAllowed,
                'specs' => $summary,
                'base' => $this->vanillaStatus($tenantId),
                'audiences' => $service !== null ? $this->audienceChoices($tenantId, $service) : [],
            ],
            'flash_success' => Session::getFlash('success'),
            'flash_error' => Session::getFlash('error'),
        ]);
    }

    /** Catalogue complet (JSON compact, mis en cache par le navigateur tant qu'il ne change pas). */
    public function catalogue(Request $request, array $params = []): Response
    {
        $gate = $this->gate(true);
        if ($gate instanceof Response) {
            return $gate;
        }
        [$tenantId] = $gate;
        $payload = $this->catalog->browserPayload($tenantId);
        $body = (string) json_encode(['ok' => true] + $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        $etag = '"' . substr(hash('sha256', $body), 0, 32) . '"';
        $response = new Response();
        $response->header('Cache-Control', 'private, max-age=0, must-revalidate');
        $response->header('ETag', $etag);
        $response->header('X-Content-Type-Options', 'nosniff');
        if (trim((string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag) {
            return $response->setStatusCode(304);
        }
        $response->header('Content-Type', 'application/json; charset=UTF-8');

        return $response->setBody($body);
    }

    /** Tenues que l'on peut récupérer dans le configurateur : les siennes, la communauté, les kits, les modèles, le jeu. */
    public function sources(Request $request, array $params = []): Response
    {
        $gate = $this->gate(true);
        if ($gate instanceof Response) {
            return $gate;
        }
        [$tenantId, $userId] = $gate;
        $out = [];
        if ($this->wardrobes->tablesReady()) {
            $favIds = array_flip($this->wardrobes->favoriteIds($tenantId, $userId));
            foreach ($this->wardrobes->listAccessibleWardrobes($tenantId, $userId) as $w) {
                $source = (string) ($w['source'] ?? '');
                if ($source === 'role_kit') {
                    continue;
                }
                $group = $source === 'template' ? 'models' : (!empty($w['mine']) ? 'mine' : 'community');
                $id = (int) $w['id'];
                $out[] = [
                    'ref' => 'w:' . $id,
                    'group' => $group,
                    'name' => (string) ($w['display_name'] ?? $w['name'] ?? ''),
                    'owner' => (string) ($w['owner_label'] ?? ''),
                    'mine' => !empty($w['mine']),
                    'fav' => !empty($w['mine']) ? !empty($w['is_favorite']) : isset($favIds[$id]),
                    'collection' => (string) ($w['collection_name'] ?? ''),
                    'side' => self::sideOf((string) ($w['payload_text'] ?? '')),
                    'updated' => (string) ($w['updated_at'] ?? ''),
                ];
            }
        }
        if (KitsAccess::allows($tenantId)) {
            try {
                $service = $this->kitService();
                $kits = $this->canManageKits() ? $service->listForTenant($tenantId) : $service->listForMember($tenantId, $userId);
                foreach ($kits as $k) {
                    $out[] = [
                        'ref' => 'k:' . (int) $k['id'],
                        'group' => 'kits',
                        'name' => (string) $k['name'],
                        'owner' => (string) ($k['member_name'] ?? $k['qualification_name'] ?? $k['role_name'] ?? $k['unit_name'] ?? ''),
                        'audience' => RoleKitService::audienceValue($k),
                        'description' => (string) ($k['description'] ?? ''),
                        'role' => (string) ($k['role_name'] ?? $k['qualification_name'] ?? $k['unit_name'] ?? ''),
                        'side' => self::sideOf((string) ($k['payload_text'] ?? '')),
                        'mine' => false,
                        'updated' => (string) ($k['updated_at'] ?? ''),
                    ];
                }
            } catch (\Throwable) {
                // Kits indisponibles : les autres sources restent.
            }
        }
        $players = $this->playerKits();
        if ($players !== null) {
            $me = $players->forUser($tenantId, $userId);
            if ($me !== null) {
                $out[] = ['ref' => 'me', 'group' => 'game', 'name' => 'Mon kit porté en jeu', 'owner' => (string) ($me['role'] ?? ''), 'mine' => true, 'updated' => (string) ($me['last_seen_at'] ?? '')];
            }
            if ($this->canManageKits()) {
                foreach ($players->recent($tenantId, 30, 80) as $p) {
                    $out[] = [
                        'ref' => 'p:' . (string) $p['steam_id'],
                        'group' => 'game',
                        'name' => 'Porté en jeu · ' . (string) $p['player_name'],
                        'owner' => (string) ($p['role'] ?? ''),
                        'mine' => false,
                        'updated' => (string) ($p['last_seen_at'] ?? ''),
                    ];
                }
            }
        }

        return Response::json(['ok' => true, 'sources' => $out, 'sides' => ConfiguratorKinds::SIDES]);
    }

    /**
     * Données du membre pour le configurateur : objets favoris, pré-kits visibles (les siens et ceux de la
     * communauté), tendances de la communauté sur 30 jours (nombres de membres seulement).
     */
    public function memberData(Request $request, array $params = []): Response
    {
        $gate = $this->gate(true);
        if ($gate instanceof Response) {
            return $gate;
        }
        [$tenantId, $userId] = $gate;
        $manager = $this->canManageKits();
        $prekits = [];
        foreach ($this->social->prekits($tenantId, $userId) as $p) {
            $doc = PrekitDocument::normalize($p['items_json']);
            if (!$doc['ok']) {
                continue;
            }
            $mine = $p['user_id'] === $userId;
            $prekits[] = [
                'id' => $p['id'],
                'name' => $p['name'],
                'description' => $p['description'],
                'items' => $doc['items'],
                'shared' => $p['shared'],
                'mine' => $mine,
                'editable' => $mine || ($p['shared'] && $manager),
                'updated' => $p['updated_at'],
            ];
        }
        try {
            $trends = $this->social->trends($tenantId);
        } catch (\Throwable $e) {
            error_log('[equipment-configurator] trends failed: ' . $e->getMessage());
            $trends = ['days' => EquipmentTrends::DAYS, 'worn' => [], 'added' => [], 'favored' => [], 'wardrobes' => []];
        }

        return Response::json([
            'ok' => true,
            'ready' => ['favorites' => $this->social->favoritesReady(), 'prekits' => $this->social->prekitsReady()],
            'favorites' => $this->social->favoriteClasses($tenantId, $userId),
            'prekits' => $prekits,
            'trends' => [
                'days' => $trends['days'],
                'min' => EquipmentTrends::MIN_MEMBERS,
                'items' => EquipmentTrends::byClass($trends['worn'], $trends['added'], $trends['favored']),
                'wardrobes' => $trends['wardrobes'],
            ],
        ]);
    }

    /** Met un objet du catalogue en favori, ou l'en retire (par membre et par communauté). */
    public function favorite(Request $request, array $params = []): Response
    {
        $gate = $this->gate(true);
        if ($gate instanceof Response) {
            return $gate;
        }
        [$tenantId, $userId] = $gate;
        if (!Csrf::validate($request->inputString('_csrf_token'))) {
            return Response::json(['ok' => false, 'error' => 'Session expirée : rechargez la page.'], 419);
        }
        if (!$this->social->favoritesReady()) {
            return Response::json(['ok' => false, 'error' => 'Favoris pas encore disponibles : la mise à jour de la base doit être appliquée.'], 503);
        }
        $class = trim($request->inputString('class'));
        if (preg_match(ItemCatalogImport::CLASS_RE, $class) !== 1 || $this->catalog->lookup($tenantId, [$class]) === []) {
            return Response::json(['ok' => false, 'error' => 'Objet absent du catalogue de la communauté.'], 422);
        }
        $on = $request->inputString('on', '1') === '1';
        $state = $this->social->setFavorite($tenantId, $userId, $class, $on);
        if ($state === null) {
            return Response::json(['ok' => false, 'error' => 'Trop de favoris (' . EquipmentConfiguratorSocialRepository::MAX_FAVORITES . ' au plus) : retirez-en avant d’en ajouter.'], 422);
        }

        return Response::json(['ok' => true, 'class' => $class, 'favorite' => $state]);
    }

    /**
     * Crée ou met à jour un pré-kit. Personnel pour tout membre ; partagé à la communauté seulement par un
     * responsable des kits. Un pré-kit partagé se modifie par son auteur ou un responsable des kits.
     */
    public function savePrekit(Request $request, array $params = []): Response
    {
        $gate = $this->gate(true);
        if ($gate instanceof Response) {
            return $gate;
        }
        [$tenantId, $userId] = $gate;
        if (!Csrf::validate($request->inputString('_csrf_token'))) {
            return Response::json(['ok' => false, 'error' => 'Session expirée : rechargez la page.'], 419);
        }
        if (!$this->social->prekitsReady()) {
            return Response::json(['ok' => false, 'error' => 'Pré-kits pas encore disponibles : la mise à jour de la base doit être appliquée.'], 503);
        }
        $name = trim((string) preg_replace('/\s+/u', ' ', $request->inputString('name')));
        if ($name === '' || mb_strlen($name) > PrekitDocument::NAME_MAX) {
            return Response::json(['ok' => false, 'error' => 'Donnez un nom au pré-kit (' . PrekitDocument::NAME_MAX . ' caractères au plus).'], 422);
        }
        $description = trim($request->inputString('description'));
        if (mb_strlen($description) > PrekitDocument::DESCRIPTION_MAX) {
            return Response::json(['ok' => false, 'error' => 'Description trop longue (' . PrekitDocument::DESCRIPTION_MAX . ' caractères au plus).'], 422);
        }
        $doc = PrekitDocument::normalize($request->inputString('items'));
        if (!$doc['ok']) {
            return Response::json(['ok' => false, 'error' => (string) $doc['error']], 422);
        }
        $catalog = $this->catalog->lookup($tenantId, array_map(static fn (array $i): string => $i[0], $doc['items']));
        $problems = PrekitDocument::problems($doc['items'], $catalog);
        if ($problems !== []) {
            return Response::json(['ok' => false, 'error' => 'Le pré-kit contient des objets refusés.', 'problems' => $problems], 422);
        }
        $manager = $this->canManageKits();
        $shared = $request->inputString('shared') === '1';
        if ($shared && !$manager) {
            return Response::json(['ok' => false, 'error' => 'Seul un responsable des kits partage un pré-kit avec la communauté. Enregistrez-le en personnel.'], 403);
        }
        $id = null;
        if (preg_match('/^\d{1,18}$/', $request->inputString('id')) === 1) {
            $existing = $this->social->findPrekit($tenantId, (int) $request->inputString('id'));
            if ($existing === null) {
                return Response::json(['ok' => false, 'error' => 'Ce pré-kit n’existe plus.'], 404);
            }
            if ($existing['user_id'] !== $userId && !($existing['shared'] && $manager)) {
                return Response::json(['ok' => false, 'error' => 'Vous ne pouvez modifier que vos pré-kits.'], 403);
            }
            $id = $existing['id'];
        } elseif ($this->social->countOwnPrekits($tenantId, $userId) >= EquipmentConfiguratorSocialRepository::MAX_PREKITS_PER_MEMBER) {
            return Response::json(['ok' => false, 'error' => 'Trop de pré-kits (' . EquipmentConfiguratorSocialRepository::MAX_PREKITS_PER_MEMBER . ' au plus) : supprimez-en un.'], 422);
        }
        try {
            $saved = $this->social->savePrekit($tenantId, $userId, $id, $name, $description, $doc['items'], $shared);
        } catch (\Throwable $e) {
            error_log('[equipment-configurator] prekit save failed: ' . $e->getMessage());

            return Response::json(['ok' => false, 'error' => 'Enregistrement impossible.'], 500);
        }
        EquipmentAudit::log('equipment.prekit.save', $tenantId, $userId, $saved, ['name' => $name, 'lines' => count($doc['items']), 'shared' => $shared, 'update' => $id !== null]);
        $mass = PrekitDocument::mass($doc['items'], $catalog);

        return Response::json([
            'ok' => true,
            'id' => $saved,
            'items' => $doc['items'],
            'mass' => $mass,
            'message' => $shared ? 'Pré-kit enregistré et partagé : toute la communauté peut l’insérer.' : 'Pré-kit enregistré dans vos pré-kits.',
        ]);
    }

    public function deletePrekit(Request $request, array $params = []): Response
    {
        $gate = $this->gate(true);
        if ($gate instanceof Response) {
            return $gate;
        }
        [$tenantId, $userId] = $gate;
        if (!Csrf::validate($request->inputString('_csrf_token'))) {
            return Response::json(['ok' => false, 'error' => 'Session expirée : rechargez la page.'], 419);
        }
        $existing = $this->social->findPrekit($tenantId, (int) ($params['id'] ?? 0));
        if ($existing === null) {
            return Response::json(['ok' => false, 'error' => 'Ce pré-kit n’existe plus.'], 404);
        }
        if ($existing['user_id'] !== $userId && !($existing['shared'] && $this->canManageKits())) {
            return Response::json(['ok' => false, 'error' => 'Vous ne pouvez supprimer que vos pré-kits.'], 403);
        }
        $this->social->deletePrekit($tenantId, $existing['id']);
        EquipmentAudit::log('equipment.prekit.delete', $tenantId, $userId, $existing['id'], ['shared' => $existing['shared']]);

        return Response::json(['ok' => true, 'message' => 'Pré-kit supprimé.']);
    }

    /** Camp de la tenue d'après son uniforme (ConfiguratorKinds::sideFromUniform), '' si inconnu. */
    private static function sideOf(string $payload): string
    {
        if ($payload === '') {
            return '';
        }
        $doc = LoadoutDocument::fromText($payload);
        $uniform = $doc['ok'] && is_array($doc['loadout'][3] ?? null) && $doc['loadout'][3] !== [] ? (string) $doc['loadout'][3][0] : '';

        return ConfiguratorKinds::sideFromUniform($uniform);
    }

    /** Une tenue récupérée (normalisée), avec le compte rendu de validation contre le catalogue. */
    public function source(Request $request, array $params = []): Response
    {
        $gate = $this->gate(true);
        if ($gate instanceof Response) {
            return $gate;
        }
        [$tenantId, $userId] = $gate;
        $ref = $request->queryString('ref');
        $found = $this->resolveSource($tenantId, $userId, $ref);
        if ($found === null) {
            return Response::json(['ok' => false, 'error' => 'Cette tenue n’existe pas ou ne vous est pas accessible.'], 404);
        }
        $doc = LoadoutDocument::fromText($found['payload']);
        if (!$doc['ok']) {
            return Response::json(['ok' => false, 'error' => (string) $doc['error']], 422);
        }

        return Response::json(['ok' => true, 'ref' => $ref, 'name' => $found['name'], 'meta' => $found['meta'], 'loadout' => $doc['loadout'], 'report' => $this->report($tenantId, $doc['loadout'], false)]);
    }

    /** Analyse d'un texte collé (JSON du configurateur, getUnitLoadout, export ACE, export « Copier ma tenue » du jeu). */
    public function analyse(Request $request, array $params = []): Response
    {
        $gate = $this->gate(true);
        if ($gate instanceof Response) {
            return $gate;
        }
        [$tenantId] = $gate;
        if (!Csrf::validate($request->inputString('_csrf_token'))) {
            return Response::json(['ok' => false, 'error' => 'Session expirée : rechargez la page.'], 419);
        }
        $doc = LoadoutDocument::fromText($request->inputString('text'));
        if (!$doc['ok']) {
            return Response::json(['ok' => false, 'error' => (string) $doc['error']], 422);
        }

        return Response::json(['ok' => true, 'loadout' => $doc['loadout'], 'report' => $this->report($tenantId, $doc['loadout'], false)]);
    }

    /**
     * Enregistre la tenue : cible « tenue » (mes tenues), « modele » (modèle de la communauté) ou « kit » (kit de
     * départ, avec destinataire). Une erreur de validation renvoie 422 et la liste des problèmes, rien n'est écrit.
     */
    public function save(Request $request, array $params = []): Response
    {
        $gate = $this->gate(true);
        if ($gate instanceof Response) {
            return $gate;
        }
        [$tenantId, $userId] = $gate;
        if (!Csrf::validate($request->inputString('_csrf_token'))) {
            return Response::json(['ok' => false, 'error' => 'Session expirée : rechargez la page.'], 419);
        }
        $target = $request->inputString('target', 'tenue');
        if (!in_array($target, ['tenue', 'modele', 'kit'], true)) {
            return Response::json(['ok' => false, 'error' => 'Choisissez où enregistrer : mes tenues, un modèle ou un kit.'], 422);
        }
        $name = trim((string) preg_replace('/\s+/u', ' ', $request->inputString('name')));
        if ($name === '' || mb_strlen($name) > 90) {
            return Response::json(['ok' => false, 'error' => 'Donnez un nom (90 caractères au plus).'], 422);
        }
        $description = trim($request->inputString('description'));
        if (mb_strlen($description) > 1000) {
            return Response::json(['ok' => false, 'error' => 'Description trop longue (1 000 caractères au plus).'], 422);
        }
        $doc = LoadoutDocument::fromText($request->inputString('loadout'));
        if (!$doc['ok']) {
            return Response::json(['ok' => false, 'error' => (string) $doc['error']], 422);
        }
        $lo = $doc['loadout'];
        if (LoadoutDocument::classes($lo) === []) {
            return Response::json(['ok' => false, 'error' => 'La tenue est vide : ajoutez au moins un objet.'], 422);
        }
        $report = $this->report($tenantId, $lo, true);
        if (!$report['ok']) {
            return Response::json(['ok' => false, 'error' => 'La tenue n’est pas valide : corrigez les points signalés.', 'report' => $report], 422);
        }
        $payload = LoadoutDocument::toJson($lo);
        $ref = $request->inputString('ref');

        if ($target === 'kit') {
            if (!KitsAccess::allows($tenantId)) {
                return Response::json(['ok' => false, 'error' => 'Les kits de départ sont réservés aux formules Pro et plus.'], 403);
            }
            if (!$this->canManageKits()) {
                return Response::json(['ok' => false, 'error' => 'Droits insuffisants pour enregistrer un kit de la communauté.'], 403);
            }
            $kitId = preg_match('/^k:(\d{1,10})$/', $ref, $m) === 1 ? (int) $m[1] : null;
            $slots = RoleKitLoadout::fromPayload($payload);
            $result = $this->kitService()->save($tenantId, $userId, $kitId, [
                'name' => $name,
                'description' => $description,
                'audience' => $request->inputString('audience', 'all'),
                'kit' => $slots ?? [],
                'payload' => $payload,
                'source' => 'configurator',
                'version_note' => mb_substr(trim($request->inputString('note')), 0, 190),
            ]);
            if (!$result['ok']) {
                return Response::json(['ok' => false, 'error' => (string) ($result['error'] ?? 'Enregistrement impossible.')], 422);
            }
            $id = (int) $result['id'];
            EquipmentAudit::log('equipment.kit.save', $tenantId, $userId, $id, ['name' => $name, 'audience' => $request->inputString('audience', 'all'), 'classes' => count(LoadoutDocument::classes($lo)), 'update' => $kitId !== null]);

            return Response::json([
                'ok' => true,
                'ref' => 'k:' . $id,
                'message' => $kitId !== null ? 'Kit mis à jour : une nouvelle version est dans l’historique.' : 'Kit créé : les membres concernés le voient dans « Kits de départ » et le prennent en jeu.',
                'warnings' => array_merge($report['warnings'], $result['warnings'] ?? []),
            ]);
        }

        if (!$this->wardrobes->tablesReady()) {
            return Response::json(['ok' => false, 'error' => 'Les tenues ne sont pas encore disponibles sur cette instance.'], 503);
        }
        $collectionId = null;
        if ($target === 'modele') {
            if (!KitsAccess::allows($tenantId) || !$this->canManageKits()) {
                return Response::json(['ok' => false, 'error' => 'Droits insuffisants pour enregistrer un modèle de la communauté.'], 403);
            }
            $collectionId = $this->wardrobes->ensureTenantCollection($tenantId, self::MODELS_SLUG, self::MODELS_NAME, 'Tenues modèles composées au configurateur : point de départ des tenues et des kits.', ['modeles']);
        }
        $existing = null;
        if (preg_match('/^w:(\d{1,10})$/', $ref, $m) === 1) {
            $existing = $this->wardrobes->findWardrobe($tenantId, (int) $m[1], $userId);
            if ($existing !== null && ($target === 'modele') !== ((string) ($existing['source'] ?? '') === 'template')) {
                // Tenue enregistrée comme modèle (ou l'inverse) : nouvelle tenue, l'ancienne reste.
                $existing = null;
            }
        }
        $slug = $existing !== null ? (string) $existing['slug'] : $this->freeSlug($tenantId, $userId, ($target === 'modele' ? 'modele-' : self::TENUE_SLUG_PREFIX) . ArsenalWardrobeRepository::slugify($name));
        try {
            $w = $this->wardrobes->upsertWardrobe($tenantId, $userId, [
                'name' => $name,
                'slug' => $slug,
                'payload_text' => $payload,
                'payload_format' => 'arma_loadout_str',
                'source' => $target === 'modele' ? 'template' : 'configurator',
                'collection_id' => $collectionId,
                'notes' => 'Composée au configurateur d’équipement',
            ]);
        } catch (\Throwable $e) {
            error_log('[equipment-configurator] save failed: ' . $e->getMessage());

            return Response::json(['ok' => false, 'error' => 'Enregistrement impossible.'], 500);
        }
        $id = (int) ($w['id'] ?? 0);
        if ($id < 1) {
            return Response::json(['ok' => false, 'error' => 'Enregistrement impossible.'], 500);
        }
        if ($existing === null) {
            $this->wardrobes->updateWardrobeVisibility($tenantId, $userId, $id, $target === 'modele' ? 'tenant' : 'personal');
        }
        if ($collectionId !== null && $collectionId > 0) {
            $this->wardrobes->assignWardrobeCollection($tenantId, $userId, $id, $collectionId);
        }
        if ($description !== '') {
            try {
                $this->wardrobes->updateWardrobeDescription($tenantId, $userId, $id, $description);
            } catch (\Throwable) {
                // Colonne absente sur une base ancienne : la tenue reste enregistrée.
            }
        }
        EquipmentAudit::log($target === 'modele' ? 'equipment.model.save' : 'equipment.wardrobe.save', $tenantId, $userId, $id, ['name' => $name, 'classes' => count(LoadoutDocument::classes($lo)), 'update' => $existing !== null]);

        return Response::json([
            'ok' => true,
            'ref' => 'w:' . $id,
            'message' => $target === 'modele'
                ? 'Modèle enregistré : toute la communauté peut le récupérer dans le configurateur.'
                : 'Tenue enregistrée dans vos tenues. En jeu : arsenal ACE, panneau « Athena · Tenues », Enfiler.',
            'warnings' => $report['warnings'],
        ]);
    }

    /** Historique des versions d'un kit (responsables des kits). */
    public function versions(Request $request, array $params = []): Response
    {
        $gate = $this->gate(true);
        if ($gate instanceof Response) {
            return $gate;
        }
        [$tenantId] = $gate;
        if (!KitsAccess::allows($tenantId) || !$this->canManageKits()) {
            return Response::json(['ok' => false, 'error' => 'Droits insuffisants.'], 403);
        }
        $kitId = (int) ($params['id'] ?? 0);

        return Response::json(['ok' => true, 'versions' => $this->kitService()->versions($tenantId, $kitId)]);
    }

    /** Ouvre une version dans le configurateur (sans rien restaurer). */
    public function version(Request $request, array $params = []): Response
    {
        $gate = $this->gate(true);
        if ($gate instanceof Response) {
            return $gate;
        }
        [$tenantId] = $gate;
        if (!KitsAccess::allows($tenantId) || !$this->canManageKits()) {
            return Response::json(['ok' => false, 'error' => 'Droits insuffisants.'], 403);
        }
        $v = $this->kitService()->versionPayload($tenantId, (int) ($params['id'] ?? 0), (int) ($params['version'] ?? 0));
        $doc = $v !== null ? LoadoutDocument::fromText($v['payload_text']) : ['ok' => false];
        if (!$doc['ok']) {
            return Response::json(['ok' => false, 'error' => 'Cette version n’existe plus ou est illisible.'], 404);
        }

        return Response::json(['ok' => true, 'name' => $v['name'], 'loadout' => $doc['loadout'], 'report' => $this->report($tenantId, $doc['loadout'], false)]);
    }

    public function restore(Request $request, array $params = []): Response
    {
        $gate = $this->gate(true);
        if ($gate instanceof Response) {
            return $gate;
        }
        [$tenantId, $userId] = $gate;
        if (!Csrf::validate($request->inputString('_csrf_token'))) {
            return Response::json(['ok' => false, 'error' => 'Session expirée : rechargez la page.'], 419);
        }
        if (!KitsAccess::allows($tenantId) || !$this->canManageKits()) {
            return Response::json(['ok' => false, 'error' => 'Droits insuffisants.'], 403);
        }
        $kitId = (int) ($params['id'] ?? 0);
        $versionNo = (int) ($params['version'] ?? 0);
        $result = $this->kitService()->restoreVersion($tenantId, $userId, $kitId, $versionNo);
        if (!$result['ok']) {
            return Response::json(['ok' => false, 'error' => (string) ($result['error'] ?? 'Restauration impossible.')], 422);
        }
        EquipmentAudit::log('equipment.kit.restore', $tenantId, $userId, $kitId, ['version' => $versionNo]);

        return Response::json(['ok' => true, 'ref' => 'k:' . $kitId, 'message' => 'Version ' . $versionNo . ' restaurée : elle devient la version en cours (l’historique est gardé).']);
    }

    /** Import de la configuration des objets (export fait en jeu, hors ligne). */
    public function importCatalog(Request $request, array $params = []): Response
    {
        $gate = $this->gate(true);
        if ($gate instanceof Response) {
            return $gate;
        }
        [$tenantId, $userId] = $gate;
        if (!Csrf::validate($request->inputString('_csrf_token'))) {
            return Response::json(['ok' => false, 'error' => 'Session expirée : rechargez la page.'], 419);
        }
        if (!$this->canManageCatalog()) {
            return Response::json(['ok' => false, 'error' => 'Droits insuffisants pour importer le catalogue.'], 403);
        }
        if (!$this->specs->tableReady()) {
            return Response::json(['ok' => false, 'error' => 'Appliquez d’abord la mise à jour de la base (étape « Configurateur d’équipement »).'], 503);
        }
        $text = $request->inputString('text');
        $file = $_FILES['file'] ?? null;
        if ($text === '' && is_array($file) && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK && is_uploaded_file((string) $file['tmp_name'])) {
            if ((int) ($file['size'] ?? 0) > ItemCatalogImport::MAX_BYTES) {
                return Response::json(['ok' => false, 'error' => 'Fichier trop gros (48 Mo au plus).'], 413);
            }
            $text = (string) file_get_contents((string) $file['tmp_name']);
        }
        $parsed = ItemCatalogImport::parse($text);
        if (!$parsed['ok']) {
            return Response::json(['ok' => false, 'error' => (string) $parsed['error']], 422);
        }
        if ($parsed['rows'] === []) {
            return Response::json(['ok' => false, 'error' => 'Aucun objet lisible dans cet export.'], 422);
        }
        $replace = $request->inputString('replace') === '1';
        try {
            $written = $this->specs->import($tenantId, $userId, $parsed['rows'], $replace);
        } catch (\Throwable $e) {
            error_log('[equipment-configurator] catalog import failed: ' . $e->getMessage());

            return Response::json(['ok' => false, 'error' => 'Import impossible : rien n’a été modifié.'], 500);
        }
        EquipmentAudit::log('equipment.catalog.import', $tenantId, $userId, 0, ['items' => $written, 'skipped' => $parsed['skipped'], 'replace' => $replace, 'game' => $parsed['version'], 'mods' => $parsed['mods']]);

        return Response::json([
            'ok' => true,
            'imported' => $written,
            'skipped' => $parsed['skipped'],
            'message' => $written . ' objets importés' . ($parsed['skipped'] > 0 ? ', ' . $parsed['skipped'] . ' lignes écartées (illisibles)' : '') . '.',
        ]);
    }

    /**
     * Catalogue de base d'Arma 3 : un lot par appel (le navigateur enchaîne et montre l'avancement). Tout membre de
     * la communauté déclenche le premier chargement (données livrées avec le site, idempotent, jamais par-dessus une
     * fiche importée) ; tout recharger depuis le début demande la gestion du catalogue.
     */
    public function baseCatalog(Request $request, array $params = []): Response
    {
        $gate = $this->gate(true);
        if ($gate instanceof Response) {
            return $gate;
        }
        [$tenantId, $userId] = $gate;
        if (!Csrf::validate($request->inputString('_csrf_token'))) {
            return Response::json(['ok' => false, 'error' => 'Session expirée : rechargez la page.'], 419);
        }
        $restart = $request->inputString('restart') === '1';
        if ($restart && !$this->canManageCatalog()) {
            return Response::json(['ok' => false, 'error' => 'Droits insuffisants pour recharger le catalogue de base.'], 403);
        }
        try {
            $result = $this->vanilla->step($tenantId, $restart);
        } catch (\Throwable $e) {
            error_log('[equipment-configurator] base catalog failed: ' . $e->getMessage());

            return Response::json(['ok' => false, 'error' => 'Chargement du catalogue de base impossible : réessayez plus tard.'], 500);
        }
        if (!$result['ok']) {
            return Response::json($result, 503);
        }
        if ($restart || $result['finished']) {
            EquipmentAudit::log('equipment.catalog.base', $tenantId, $userId, 0, ['restart' => $restart, 'done' => $result['done'], 'total' => $result['total'], 'version' => $result['version']]);
        }

        return Response::json($result);
    }

    /**
     * @return array<string, mixed>
     */
    private function vanillaStatus(int $tenantId): array
    {
        try {
            return $this->vanilla->status($tenantId);
        } catch (\Throwable) {
            return ['available' => false, 'needed' => false, 'version' => '', 'total' => 0, 'done' => 0, 'finished' => false, 'families' => []];
        }
    }

    /** Script d'export autonome à coller dans la console de débogage d'Arma (texte brut, téléchargé). */
    public function exportScript(Request $request, array $params = []): Response
    {
        $gate = $this->gate();
        if ($gate instanceof Response) {
            return $gate;
        }
        $path = base_path('tools/arma/export_catalogue_equipement.sqf');
        $response = new Response();
        if (!is_file($path)) {
            return $response->setStatusCode(404)->setBody('');
        }
        $response->header('Content-Type', 'text/plain; charset=UTF-8');
        $response->header('X-Content-Type-Options', 'nosniff');
        $response->header('Content-Disposition', ($request->queryString('inline') === '1' ? 'inline' : 'attachment') . '; filename="export_catalogue_equipement.sqf"');

        return $response->setBody((string) file_get_contents($path));
    }

    /**
     * @param list<mixed> $lo
     * @return array<string, mixed>
     */
    private function report(int $tenantId, array $lo, bool $strict): array
    {
        $classes = LoadoutDocument::classes($lo);

        return LoadoutValidator::validate($lo, $this->catalog->lookup($tenantId, $classes), $strict);
    }

    /**
     * @return array{name: string, payload: string, meta: array<string, mixed>}|null
     */
    private function resolveSource(int $tenantId, int $userId, string $ref): ?array
    {
        if ($ref === 'me') {
            $me = $this->playerKits()?->forUser($tenantId, $userId);

            return $me !== null ? ['name' => 'Mon kit porté en jeu', 'payload' => (string) $me['payload'], 'meta' => ['kind' => 'game']] : null;
        }
        if (preg_match('/^w:(\d{1,10})$/', $ref, $m) === 1 && $this->wardrobes->tablesReady()) {
            $row = $this->wardrobes->findWardrobe($tenantId, (int) $m[1]);
            if ($row === null || !ArsenalWardrobeRepository::canView($row, $userId)) {
                return null;
            }
            $mine = (int) ($row['user_id'] ?? 0) === $userId;

            return [
                'name' => (string) ($row['name'] ?? ''),
                'payload' => (string) ($row['payload_text'] ?? ''),
                'meta' => ['kind' => (string) ($row['source'] ?? '') === 'template' ? 'model' : 'wardrobe', 'mine' => $mine, 'description' => (string) ($row['description'] ?? '')],
            ];
        }
        if (preg_match('/^k:(\d{1,10})$/', $ref, $m) === 1 && KitsAccess::allows($tenantId)) {
            $service = $this->kitService();
            $kitId = (int) $m[1];
            $manager = $this->canManageKits();
            $rows = $manager ? $service->listForTenant($tenantId) : $service->listForMember($tenantId, $userId);
            foreach ($rows as $k) {
                if ((int) $k['id'] === $kitId) {
                    return [
                        'name' => (string) $k['name'],
                        'payload' => (string) $k['payload_text'],
                        'meta' => ['kind' => 'kit', 'audience' => RoleKitService::audienceValue($k), 'description' => (string) ($k['description'] ?? ''), 'editable' => $manager],
                    ];
                }
            }

            return null;
        }
        if (preg_match('/^p:(\d{5,20})$/', $ref, $m) === 1 && $this->canManageKits()) {
            foreach ($this->playerKits()?->recent($tenantId, 90, 300) ?? [] as $p) {
                if ((string) $p['steam_id'] === $m[1]) {
                    return ['name' => 'Kit en jeu · ' . (string) $p['player_name'], 'payload' => (string) $p['payload'], 'meta' => ['kind' => 'game']];
                }
            }
        }

        return null;
    }

    private function freeSlug(int $tenantId, int $userId, string $base): string
    {
        $taken = [];
        foreach ($this->wardrobes->listWardrobesForUser($tenantId, $userId) as $w) {
            $taken[(string) ($w['slug'] ?? '')] = true;
        }
        $base = substr($base, 0, 110);
        $slug = $base;
        for ($n = 2; isset($taken[$slug]) && $n < 500; $n++) {
            $slug = $base . '-' . $n;
        }

        return $slug;
    }

    /**
     * Destinataires possibles d'un kit, groupés (liste du formulaire d'enregistrement).
     *
     * @return list<array{label: string, options: list<array{value: string, label: string}>}>
     */
    private function audienceChoices(int $tenantId, RoleKitService $service): array
    {
        $groups = [['label' => '', 'options' => [['value' => 'all', 'label' => 'Tous les membres']]]];
        $add = static function (string $label, string $prefix, array $opts) use (&$groups): void {
            if ($opts === []) {
                return;
            }
            $list = [];
            foreach ($opts as $id => $name) {
                $list[] = ['value' => $prefix . (int) $id, 'label' => (string) $name];
            }
            $groups[] = ['label' => $label, 'options' => $list];
        };
        if ($service->unitsReady()) {
            $add('Une unité', 'unit:', $service->unitOptions($tenantId));
        }
        $add('Un rôle', 'role:', $service->roleOptions($tenantId));
        $add('Une qualification', 'qualification:', $service->qualificationOptions($tenantId));
        $add('Un membre (kit personnel)', 'member:', $service->memberOptions($tenantId));

        return $groups;
    }

    /**
     * @return array{0: int, 1: int}|Response
     */
    private function gate(bool $json = false): array|Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        if ($tenantId < 1 || $userId < 1) {
            return $json ? Response::json(['ok' => false, 'error' => 'Connectez-vous.'], 401) : Response::redirect(url('login'));
        }
        try {
            $allowed = \App\Core\Container::get(FeatureGateService::class)->allows($tenantId, 'equipment');
        } catch (\Throwable) {
            $allowed = false;
        }
        if (!$allowed) {
            return $json ? Response::json(['ok' => false, 'error' => 'La page Équipement n’est pas comprise dans la formule.'], 403) : PlanFeatureDenial::upgradeView('equipment', 'Gratuit');
        }

        return [$tenantId, $userId];
    }

    private function kitService(): RoleKitService
    {
        return \App\Core\Container::get(RoleKitService::class);
    }

    private function playerKits(): ?PlayerKitService
    {
        try {
            return \App\Core\Container::get(PlayerKitService::class);
        } catch (\Throwable) {
            return null;
        }
    }

    /** Responsables des kits : mêmes droits que la page Équipement (ArsenalWardrobeController::canManageKits). */
    private function canManageKits(): bool
    {
        $gate = Gate::getInstance();

        return $gate->allows('admin.organization') || $gate->allows('admin.access') || $gate->allows('personnel.equipment.manage');
    }

    private function canManageCatalog(): bool
    {
        $gate = Gate::getInstance();

        return $gate->allows('admin.organization') || $gate->allows('admin.access') || $gate->allows('personnel.equipment.manage') || $gate->allows('site.support');
    }
}
