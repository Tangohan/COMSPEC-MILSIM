<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\Security\FileRateLimiter;
use App\Services\Sse\SseAccessCodeService;
use App\Services\Sse\SseAccessPolicy;
use App\Services\Sse\SseFieldNoteTagService;
use App\Support\SseAccessCatalog;
use App\Support\SseFieldNoteTagCatalog;

/**
 * Bureau SSE › Production › « Gestion des fiches » (atak/sse/fiches/gestion) : référentiel des fiches de renseignement de la
 * communauté de la session (étiquettes, catégories, types de fiche, trames, règles, import et export).
 *
 * Lecture : toute personne qui lit les fiches (section « terrain »). Écriture : droit « gérer » de la même section, jamais en
 * session invitée. La communauté est toujours celle de la session ; chaque écriture vérifie le jeton, le droit, un débit
 * limité, et s'inscrit au journal SSE. Une étiquette se désigne par sa clé dans la communauté : aucune clé d'une autre
 * communauté n'est atteignable.
 */
final class SseFieldNoteAdminController
{
    public const PATH = 'atak/sse/fiches/gestion';

    public const WRITES_PER_HOUR = 240;

    /** Onglets de la page (ancre => libellé). @var array<string, string> */
    public const TABS = [
        'etiquettes' => 'Étiquettes',
        'categories' => 'Catégories',
        'types' => 'Types de fiche',
        'modeles' => 'Modèles',
        'regles' => 'Règles',
        'echanges' => 'Import / export',
    ];

    private SseFieldNoteTagService $tags;

    /** @var \Closure(): array{tenant: int, user: int, guest: bool, read: bool, manage: bool, label: string} */
    private \Closure $rights;

    /** @var \Closure(string, int, int): bool */
    private \Closure $tooMany;

    /**
     * @param (\Closure(): array{tenant: int, user: int, guest: bool, read: bool, manage: bool, label: string})|null $rights droits de la session (tests)
     * @param (\Closure(string, int, int): bool)|null $tooMany limiteur de débit (tests)
     */
    public function __construct(?SseFieldNoteTagService $tags = null, ?\Closure $rights = null, ?\Closure $tooMany = null)
    {
        $this->tags = $tags ?? new SseFieldNoteTagService();
        $this->rights = $rights ?? static fn (): array => self::sessionRights();
        $this->tooMany = $tooMany ?? static fn (string $key, int $max, int $window): bool => (new FileRateLimiter())->tooManyAttempts($key, $max, $window);
    }

    /** @return array{tenant: int, user: int, guest: bool, read: bool, manage: bool, label: string} */
    public static function sessionRights(): array
    {
        $access = new SseAccessCodeService();
        $guest = $access->isGuest();
        $tenant = $access->tenantId() > 0 ? $access->tenantId() : (int) Session::get('tenant_id');
        $context = ['guest' => $guest, 'tenant_id' => $tenant];
        $label = '';
        foreach (['callsign', 'display_name'] as $key) {
            $label = $label !== '' ? $label : trim((string) (Session::get($key) ?? ''));
        }

        return [
            'tenant' => $tenant,
            'user' => (int) Session::get('user_id'),
            'guest' => $guest,
            'read' => SseAccessPolicy::sessionAllows('terrain', SseAccessCatalog::LEVEL_READ, $context),
            'manage' => !$guest && SseAccessPolicy::sessionAllows('terrain', SseAccessCatalog::LEVEL_MANAGE, $context),
            'label' => $label,
        ];
    }

    public function index(Request $request, array $params = []): Response
    {
        $rights = ($this->rights)();
        if ($rights['tenant'] < 1 || !$rights['read']) {
            Session::flash('error', 'Vos droits ne permettent pas de lire le référentiel des fiches.');

            return Response::redirect(url('atak/sse/accueil'));
        }
        $tenant = $rights['tenant'];
        $lang = function_exists('locale') && locale() === 'en' ? 'en' : 'fr';
        $usage = $this->tags->usage($tenant);
        $tags = [];
        foreach ($this->tags->referential($tenant) as $key => $tag) {
            $tag['usage'] = (int) ($usage[$key] ?? 0);
            $tags[] = $tag;
        }

        return Response::view('atak.sse.field_note_admin', [
            'title' => 'Gestion des fiches',
            'activeNav' => 'fiches-gestion',
            'tabs' => self::TABS,
            'tags' => $tags,
            'stats' => $this->tags->stats($tenant),
            'kinds' => $this->tags->kinds($tenant),
            'rules' => $this->tags->rules($tenant),
            'ready' => $this->tags->ready(),
            'canManageRef' => $rights['manage'] && $this->tags->ready(),
            'isManager' => $rights['manage'],
            'lang' => $lang,
            'isGuest' => $rights['guest'],
            'clearanceUntil' => (int) Session::get(SseAccessCodeService::SESSION_UNTIL, 0),
            'guestLabel' => (string) Session::get('sse_guest_label', ''),
            'sseTheme' => function_exists('sse_ui_theme') ? sse_ui_theme() : 'bureau',
            'canManage' => $rights['manage'],
            'canGrant' => false,
            'canWrite' => $rights['manage'],
        ]);
    }

    /** Écritures du référentiel : POST atak/sse/fiches/gestion/{action}. */
    public function save(Request $request, array $params = []): Response
    {
        $action = (string) ($params['action'] ?? '');
        $rights = ($this->rights)();
        $tab = [
            'etiquette' => 'etiquettes', 'etat' => 'etiquettes', 'fusion' => 'etiquettes', 'supprimer' => 'etiquettes', 'retablir' => 'etiquettes',
            'type' => 'types', 'modele' => 'modeles', 'regles' => 'regles', 'requis' => 'regles', 'import' => 'echanges',
        ][$action] ?? 'etiquettes';
        if ($tab === 'etiquettes' && (string) $request->input('tab', '') === 'categories') {
            $tab = 'categories';
        }
        $back = url(self::PATH) . '#' . $tab;
        if ($rights['tenant'] < 1 || !$rights['manage'] || $rights['guest'] || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Action non autorisée ou session expirée : la gestion des fiches demande le droit « gérer » des fiches de renseignement.');

            return Response::redirect($back);
        }
        if (($this->tooMany)('sse_fiches_ref|' . $rights['tenant'] . '|' . $rights['user'], self::WRITES_PER_HOUR, 3600)) {
            Session::flash('error', 'Trop de changements en peu de temps : réessayez dans quelques minutes.');

            return Response::redirect($back);
        }
        $tenant = $rights['tenant'];
        $actor = ['user_id' => $rights['user'] > 0 ? $rights['user'] : null, 'label' => $rights['label']];
        $list = static fn (mixed $v): array => is_array($v) ? array_values(array_map('strval', array_filter($v, 'is_scalar'))) : [];

        switch ($action) {
            case 'etiquette':
                $result = $this->tags->saveTag($tenant, [
                    'key' => (string) $request->input('key', ''),
                    'code' => (string) $request->input('code', ''),
                    'family' => (string) $request->input('family', ''),
                    'label_fr' => (string) $request->input('label_fr', ''),
                    'label_en' => (string) $request->input('label_en', ''),
                    'desc_fr' => (string) $request->input('desc_fr', ''),
                    'desc_en' => (string) $request->input('desc_en', ''),
                    'color' => (string) $request->input('color', ''),
                    'sort' => (int) $request->input('sort', 0),
                    'qualifier' => (string) $request->input('qualifier', ''),
                ], $actor);
                $done = (string) $request->input('key', '') === '' ? 'Étiquette créée.' : 'Étiquette enregistrée.';
                break;
            case 'etat':
                $status = (string) $request->input('status', '');
                $result = $this->tags->setStatus($tenant, (string) $request->input('key', ''), $status, $actor);
                $done = ['active' => 'Étiquette activée.', 'disabled' => 'Étiquette désactivée : elle n’est plus proposée, les fiches qui la portent la gardent.', 'archived' => 'Étiquette archivée : elle n’est plus proposée, les fiches qui la portent la gardent.'][$status] ?? 'État enregistré.';
                break;
            case 'fusion':
                $result = $this->tags->merge($tenant, (string) $request->input('from', ''), (string) $request->input('into', ''), $actor);
                $done = 'Fusion faite : ' . (int) ($result['moved'] ?? 0) . ' fiche(s) reprises par l’étiquette gardée.';
                break;
            case 'supprimer':
                $result = $this->tags->deleteTag($tenant, (string) $request->input('key', ''), $actor);
                $done = 'Étiquette supprimée.';
                break;
            case 'retablir':
                $result = $this->tags->resetFamily($tenant, (string) $request->input('family', ''), $actor);
                $done = 'Catalogue livré rétabli pour cette famille : ' . (int) ($result['reset'] ?? 0) . ' réglage(s) retirés.';
                break;
            case 'type':
                $result = $this->tags->saveKind($tenant, strtoupper((string) $request->input('kind', '')), [
                    'label' => (string) $request->input('label', ''),
                    'enabled' => (string) $request->input('enabled', '') === '1',
                    'sort' => (int) $request->input('sort', 0),
                    'suggested' => $list($request->input('suggested', [])),
                    'required_fields' => $list($request->input('required_fields', [])),
                ], $actor);
                $done = 'Type de fiche enregistré.';
                break;
            case 'modele':
                $result = $this->tags->saveKind($tenant, strtoupper((string) $request->input('kind', '')), ['template' => (string) $request->input('template', '')], $actor);
                $done = 'Trame enregistrée.';
                break;
            case 'requis':
                $result = $this->tags->saveKind($tenant, strtoupper((string) $request->input('kind', '')), [
                    'required_tags' => array_merge($list($request->input('families', [])), $list($request->input('required', []))),
                ], $actor);
                $done = 'Étiquettes obligatoires enregistrées.';
                break;
            case 'regles':
                $precedence = $request->input('precedence', []);
                $result = $this->tags->saveRules($tenant, [
                    'default_validity' => (string) $request->input('default_validity', ''),
                    'precedence' => is_array($precedence) ? $precedence : [],
                    'rel_to' => (string) $request->input('rel_to', ''),
                ], $actor);
                $done = 'Règles enregistrées.';
                break;
            case 'import':
                $json = (string) $request->input('json', '');
                $file = $_FILES['fichier'] ?? null;
                if (is_array($file) && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK && is_uploaded_file((string) $file['tmp_name']) && (int) $file['size'] <= 400_000) {
                    $json = (string) file_get_contents((string) $file['tmp_name']);
                }
                $result = trim($json) === ''
                    ? ['ok' => false, 'errors' => ['Choisissez un fichier JSON ou collez son contenu.']]
                    : $this->tags->import($tenant, $json, $actor);
                $done = 'Référentiel importé : ' . (int) ($result['created'] ?? 0) . ' étiquette(s) créées, ' . (int) ($result['updated'] ?? 0) . ' réglées.';
                break;
            default:
                $result = ['ok' => false, 'errors' => ['Action inconnue.']];
                $done = '';
        }

        if ($result['ok']) {
            Session::flash('success', $done);
        } else {
            Session::flash('error', implode(' ', $result['errors']));
        }

        return Response::redirect($back);
    }

    /** Export JSON du référentiel de la communauté (droit « gérer »). */
    public function export(Request $request, array $params = []): Response
    {
        $rights = ($this->rights)();
        if ($rights['tenant'] < 1 || !$rights['manage'] || $rights['guest']) {
            Session::flash('error', 'L’export du référentiel demande le droit « gérer » des fiches de renseignement.');

            return Response::redirect(url(self::PATH) . '#echanges');
        }
        $data = $this->tags->export($rights['tenant']) + ['exported_at' => date('c')];
        $json = (string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return (new Response())
            ->header('Content-Type', 'application/json; charset=utf-8')
            ->header('Content-Disposition', 'attachment; filename="referentiel-fiches-' . date('Ymd') . '.json"')
            ->header('X-Content-Type-Options', 'nosniff')
            ->setBody($json);
    }

    /** Familles proposées dans la page (les deux familles déduites de la fiche sont décrites à part). @return list<string> */
    public static function tagFamilies(): array
    {
        return array_values(array_filter(SseFieldNoteTagCatalog::pickFamilies(), static fn (string $f): bool => $f !== 'category'));
    }
}
