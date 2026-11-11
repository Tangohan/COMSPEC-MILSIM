<?php
/**
 * Banc d'essai de l'éditeur de plans : petit serveur (routeur du serveur intégré de PHP) qui sert la vraie vue et fait
 * tourner les vrais services du site (PlanService, AtakPlanRepository, PlanValidator, PlanInterop, PlanTimeline) sur
 * une base sqlite jetable. Pas de session ni de droits du site : le « membre » vient du témoin huser
 * (1 = auteur, 2 = second éditeur, 9 = lecture seule). Tout avertissement PHP est une erreur 500.
 *
 *   php -S 127.0.0.1:8790 -t <racine du dépôt> tests/Frontend/fixtures/plan-backend.php
 *   http://127.0.0.1:8790/harness/plan/1                     éditeur (?user=2|9, ?lang=en, ?nodem=1, ?nolink=1, ?world=autre)
 *   http://127.0.0.1:8790/__harness/reset?fixture=demo|empty|big   remet la base à neuf
 *
 * N'est jamais servi par le site (hors de public/). Scénarios : tests/Frontend/scenarios/.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli-server') {
    http_response_code(404);
    exit;
}
$root = dirname(__DIR__, 3);
$path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$isApi = (bool) preg_match('~^/(public/atak/toc/(api/|plans/\d+)|harness/|__harness/)~', $path);
if (!$isApi) {
    return false; // fichier statique
}

set_error_handler(static function (int $no, string $msg, string $file, int $line): bool {
    throw new ErrorException($msg, 0, $no, $file, $line);
});
spl_autoload_register(static function (string $class) use ($root): void {
    if (str_starts_with($class, 'App\\')) {
        $f = $root . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
        if (is_file($f)) {
            require $f;
        }
    }
});
if (!function_exists('base_path')) {
    function base_path(string $p = ''): string
    {
        return dirname(__DIR__, 3) . ($p !== '' ? '/' . ltrim($p, '/') : '');
    }
}
if (!function_exists('url')) {
    function url(string $p = ''): string
    {
        return '/public/' . ltrim($p, '/');
    }
}
if (!function_exists('asset_url')) {
    function asset_url(string $p): string
    {
        $f = dirname(__DIR__, 3) . '/public/' . ltrim($p, '/');

        return '/public/' . ltrim($p, '/') . '?v=' . (is_file($f) ? filemtime($f) : 0);
    }
}

use App\Repositories\AtakPlanRepository;
use App\Services\AtakToc\PlanCatalog;
use App\Services\AtakToc\PlanInterop;
use App\Services\AtakToc\PlanService;
use App\Services\AtakToc\PlanValidator;

const H_TENANT = 1;
$dbFile = sys_get_temp_dir() . '/athena-plan-harness-' . (int) ($_SERVER['SERVER_PORT'] ?? 0) . '.sqlite';
$json = static function (mixed $data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
};
$open = static function () use ($dbFile, $root): PDO {
    // Les tables sont créées une fois, à la remise à zéro : pas à chaque requête.
    $fresh = !is_file($dbFile) || filesize($dbFile) === 0;
    $pdo = new PDO('sqlite:' . $dbFile, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec('PRAGMA busy_timeout = 4000');
    if ($fresh) {
        $migrate = require $root . '/bootstrap/atak_toc_plans_migration.php';
        $migrate($pdo);
    }

    return $pdo;
};

try {
    $user = (int) ($_GET['user'] ?? ($_COOKIE['huser'] ?? 1));
    $user = in_array($user, [1, 2, 9], true) ? $user : 1;
    $labels = [1 => 'TTRD', 2 => 'VIKING', 9 => 'LECTEUR'];
    $body = [];
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && str_contains((string) ($_SERVER['CONTENT_TYPE'] ?? ''), 'json')) {
        $body = json_decode((string) file_get_contents('php://input'), true) ?: [];
    }

    if ($path === '/__harness/reset') {
        if (is_file($dbFile)) {
            unlink($dbFile);
        }
        $pdo = $open();
        $repo = new AtakPlanRepository($pdo);
        $svc = new PlanService($repo, static fn (): ?int => 1);
        $fixture = preg_replace('/[^a-z]/', '', (string) ($_GET['fixture'] ?? 'demo'));
        $doc = ['title' => 'Plan vide', 'world' => 'essai', 'meta' => [], 'phases' => [], 'elements' => []];
        if ($fixture !== 'empty') {
            $doc = json_decode((string) file_get_contents(__DIR__ . '/plan-demo.json'), true);
        }
        if ($fixture === 'big') {
            // 500 éléments : la démonstration, puis une grille d'unités et de zones.
            $n = count($doc['elements']);
            for ($i = 0; $n < 500; $i++, $n++) {
                $x = 800 + ($i % 25) * 270;
                $y = 800 + intdiv($i, 25) * 330;
                $doc['elements'][] = $i % 3 === 0
                    ? ['uid' => 'big' . $i, 'kind' => 'nai', 'geom' => 'circle', 'pts' => [[$x, $y]], 'props' => ['radius' => 90, 'color' => '#fae04d', 'fill' => 0.08, 'label' => 'N' . $i]]
                    : ['uid' => 'big' . $i, 'kind' => 'unit', 'geom' => 'point', 'pts' => [[$x, $y]], 'props' => ['affil' => $i % 2 ? 'F' : 'H', 'unitType' => 'infantry', 'echelon' => 'squad', 'callsign' => 'U' . $i]];
            }
            $doc['title'] = 'Grand plan (500 éléments)';
        }
        $id = $repo->create(H_TENANT, 1, (string) $doc['title'], 1, 'essai', 'community', null, PlanValidator::meta($doc['meta'] ?? []));
        $plan = $repo->find(H_TENANT, $id);
        $svc->applyDocument($plan, ['title' => $doc['title'], 'world' => 'essai', 'meta' => PlanValidator::meta($doc['meta'] ?? []), 'phases' => $doc['phases'] ?? [], 'elements' => $doc['elements'] ?? [], 'rejected' => 0], 1, true);
        // Second plan, vide, pour le copier-coller d'un plan à l'autre.
        $repo->create(H_TENANT, 1, 'Second plan', 1, 'essai', 'community', null, PlanValidator::meta([]));
        $json(['ok' => true, 'id' => $id, 'elements' => $repo->countElements($id)]);

        return true;
    }

    $pdo = $open();
    $repo = new AtakPlanRepository($pdo);
    $sent = [];
    $svc = new PlanService($repo, static function (int $t, int $m, string $cmd, string $ref) use (&$sent): ?int {
        $sent[] = $cmd . ' ' . $ref;

        return count($sent);
    });

    if (preg_match('~^/(?:harness/plan|public/atak/toc/plans)/(\d+)$~', $path, $m)) {
        setcookie('huser', (string) $user, ['path' => '/']);
        $plan = $repo->find(H_TENANT, (int) $m[1]);
        if ($plan === null) {
            http_response_code(404);
            echo 'Plan introuvable : /__harness/reset d’abord.';

            return true;
        }
        $title = $plan['title'];
        $tocPlanId = (int) $plan['id'];
        $tocReadOnlyLink = false;
        $tocSharedDoc = null;
        $tocTerrains = [[
            'id' => 1, 'slug' => 'essai', 'label' => 'Terrain d’essai', 'world' => 'essai',
            'config' => [
                'worldSize' => 8192, 'offsetX' => 0, 'offsetY' => 0, 'center' => [4096, 4096], 'defaultZoom' => 4, 'minZoom' => 0, 'maxZoom' => 6,
                'tileSize' => 212, 'tilePattern' => '/tests/Frontend/fixtures/none/{z}/{x}/{y}.png', 'crs' => ['factorx' => 0.006839, 'factory' => 0.006836, 'tileWidth' => 212],
                'fondLayers' => [], 'attribution' => 'Relief synthétique',
            ],
        ]];
        $tocDefaultMap = 1;
        $tocOperator = $labels[$user];
        $tocCanManage = $user === 1;
        $tocCsrf = 'essai';
        $tocCatalog = PlanCatalog::forClient();
        ob_start();
        require $root . '/views/atak/toc/plan_editor.php';
        $html = (string) ob_get_clean();
        $inject = '<script src="/tests/Frontend/fixtures/plan-dem-fixture.js"></script><script src="/tests/Frontend/fixtures/plan-editor-harness.js"></script>';
        $html = preg_replace('~<script>window\.TOC_BOOT~', $inject . '<script>window.TOC_BOOT', $html, 1) ?? $html;
        if (isset($_GET['nohelp'])) {
            // Sans le tutoriel ni l'aide : scénarios qui éprouvent l'éditeur seul.
            $html = preg_replace('~<script[^>]+plan-help\.js[^>]*></script>~', '', $html) ?? $html;
        }
        if (($_GET['lang'] ?? '') === 'en') {
            $html = str_replace('<html lang="fr"', '<html lang="en"', $html);
            $html = str_replace('</head>', '<script src="/public/assets/js/i18n_en_dict.js" defer></script><script src="/public/assets/js/i18n_dom.js" defer></script></head>', $html);
        }
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store');
        echo $html;

        return true;
    }

    if ($path === '/public/atak/toc/api/plans' && $body !== []) {
        $title = PlanValidator::text($body['title'] ?? '', 120) ?: 'Plan';
        $id = $repo->create(H_TENANT, $user, $title, 1, 'essai', 'private', null, PlanValidator::meta(['layers' => [['uid' => 'base', 'name' => 'Plan', 'visible' => true]]]));
        $json(['ok' => true, 'id' => $id], 201);

        return true;
    }
    if ($path === '/public/atak/toc/api/plans') {
        $json(['ok' => true, 'plans' => [], 'templates' => []]);

        return true;
    }
    if (!preg_match('~^/public/atak/toc/(?:api/)?plans/(\d+)(?:/(.*))?$~', $path, $m)) {
        $json(['ok' => false, 'error' => 'not_found', 'path' => $path], 404);

        return true;
    }
    $plan = $repo->find(H_TENANT, (int) $m[1]);
    $sub = $m[2] ?? '';
    if ($plan === null) {
        $json(['ok' => false, 'error' => 'not_found'], 404);

        return true;
    }
    $planId = (int) $plan['id'];
    $canEdit = $user !== 9 && !($plan['locked'] && $user !== 1);
    $write = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
    if ($write && !str_starts_with($sub, 'export') && ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '') !== 'essai') {
        $json(['ok' => false, 'error' => 'csrf'], 419);

        return true;
    }
    if ($write && !$canEdit && !in_array($sub, ['comments', 'export.pdf'], true)) {
        $json(['ok' => false, 'error' => 'read_only'], 403);

        return true;
    }
    $doc = static fn (): array => $svc->document($repo->find(H_TENANT, $planId));

    switch ($sub) {
        case '':
            if ($write) {
                $fields = [];
                if (isset($body['title']) && PlanValidator::text($body['title'], 120) !== '') {
                    $fields['title'] = PlanValidator::text($body['title'], 120);
                }
                if (isset($body['meta'])) {
                    $fields['meta'] = PlanValidator::meta($body['meta']);
                }
                if (isset($body['sharing']) && in_array($body['sharing'], PlanValidator::SHARING, true)) {
                    $fields['sharing'] = $body['sharing'];
                }
                if (array_key_exists('locked', $body)) {
                    $fields['locked'] = !empty($body['locked']);
                }
                $repo->updatePlan(H_TENANT, $planId, $fields);
                $json(['ok' => true, 'rev' => $repo->bumpRev(H_TENANT, $planId)]);
                break;
            }
            $since = max(0, (int) ($_GET['since'] ?? 0));
            $presence = $repo->touchPresence($planId, $user, $labels[$user], substr((string) preg_replace('/[^a-z_]/', '', (string) ($_GET['tool'] ?? '')), 0, 24));
            $last = $repo->lastChangeMs($planId);
            $json([
                'ok' => true,
                'plan' => ['id' => $planId, 'title' => $plan['title'], 'world' => $plan['world'], 'map_id' => $plan['map_id'], 'sharing' => $plan['sharing'], 'locked' => $plan['locked'], 'rev' => $plan['rev'], 'meta' => $plan['meta'], 'share_token' => $plan['share_token']],
                'can_edit' => $canEdit, 'can_admin' => $user === 1,
                'elements' => $repo->elements($planId, $since, $since > 0), 'phases' => $repo->phases($planId), 'presence' => $presence,
                'server_ms' => max($last, $since), 'full' => $since === 0,
            ]);
            break;
        case 'elements':
            $json($svc->saveElements($plan, is_array($body['upserts'] ?? null) ? $body['upserts'] : [], is_array($body['deletes'] ?? null) ? $body['deletes'] : [], $user, is_array($body['base'] ?? null) ? $body['base'] : [], $user === 1));
            break;
        case 'phases':
            $svc->savePhases($plan, is_array($body['phases'] ?? null) ? $body['phases'] : []);
            $json(['ok' => true, 'rev' => $repo->bumpRev(H_TENANT, $planId), 'phases' => $repo->phases($planId)]);
            break;
        case 'versions':
            if ($write) {
                $json(['ok' => true, 'id' => $svc->saveVersion($plan, PlanValidator::text($body['label'] ?? '', 120), $user)]);
                break;
            }
            $json(['ok' => true, 'versions' => $repo->versions($planId)]);
            break;
        case 'comments':
            if ($write && isset($body['resolve'])) {
                $repo->resolveComment($planId, (int) $body['resolve'], !empty($body['resolved']));
            } elseif ($write) {
                $repo->addComment(H_TENANT, $planId, $user, $labels[$user], PlanValidator::text($body['body'] ?? '', 2000), PlanValidator::uid($body['element_uid'] ?? null));
            }
            $json(['ok' => true, 'comments' => $repo->comments($planId)]);
            break;
        case 'publish':
            $json($svc->publish($plan, (string) ($body['side'] ?? ''), PlanValidator::uid($body['phase'] ?? null), $user, $labels[$user]) + ['harness_sent' => $sent]);
            break;
        case 'game-import':
            $json(['ok' => true, 'elements' => [], 'updated_at' => null]);
            break;
        case 'import':
            $format = (string) ($body['format'] ?? '');
            $content = (string) ($body['content'] ?? '');
            $phaseUids = array_values(array_map('strval', array_column($repo->phases($planId), 'uid')));
            $fit = is_array($body['fit'] ?? null) ? $body['fit'] : [0, 0, 8192, 8192];
            if (!in_array($format, ['geojson', 'kml', 'athena'], true) || trim($content) === '') {
                $json(['ok' => false, 'error' => 'import_format'], 422);
                break;
            }
            [$els, $rejected, $why] = match ($format) {
                'geojson' => PlanInterop::fromGeoJson($content, $fit, $phaseUids),
                'kml' => PlanInterop::fromKml($content, $fit, $phaseUids),
                default => PlanInterop::fromAthena($content, $phaseUids),
            };
            $json($why !== '' ? ['ok' => false, 'error' => $why] : ['ok' => true, 'elements' => $els, 'rejected' => $rejected], $why !== '' ? 422 : 200);
            break;
        case 'share-link':
            $token = !empty($body['create']) ? bin2hex(random_bytes(16)) : null;
            $repo->setShareToken(H_TENANT, $planId, $token);
            $json(['ok' => true, 'token' => $token, 'url' => $token ? url('atak/toc/plans/lien/' . $token) : '']);
            break;
        case 'duplicate':
            $json(['ok' => true, 'id' => $svc->duplicate($plan, $user, PlanValidator::text($body['title'] ?? '', 120), !empty($body['as_template']))]);
            break;
        case 'export.json':
            header('Content-Disposition: attachment; filename="plan.json"');
            $json($doc());
            break;
        case 'export.geojson':
            header('Content-Disposition: attachment; filename="plan.geojson"');
            $json(PlanInterop::toGeoJson($doc()));
            break;
        case 'export.kml':
            header('Content-Type: application/vnd.google-earth.kml+xml');
            header('Content-Disposition: attachment; filename="plan.kml"');
            echo PlanInterop::toKml($doc());
            break;
        case 'export.pdf':
            // La fiche PDF demande TCPDF et la base du site : ici, seul le chemin de l'éditeur est exercé.
            header('Content-Type: application/pdf');
            echo "%PDF-1.4\n% banc d'essai\n";
            break;
        default:
            if (preg_match('~^versions/(\d+)/restore$~', $sub, $v)) {
                $json(['ok' => $svc->restore($plan, (int) $v[1], $user)]);
                break;
            }
            $json(['ok' => false, 'error' => 'not_found', 'path' => $path], 404);
    }
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'harness_exception', 'message' => $e->getMessage(), 'at' => basename($e->getFile()) . ':' . $e->getLine()]);
}

return true;
