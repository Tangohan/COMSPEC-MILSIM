<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\BackOfficeNavPrefsRepository;
use App\Repositories\BoSheetRepository;
use App\Services\BackOffice\Home\HomeLayout;
use App\Services\BackOffice\Home\SheetData;

/**
 * Accueil personnalisable du back-office et ses tableaux.
 *
 * Routes sous /back-office (AuthMiddleware + OrganizationAdminMiddleware : gestionnaires et assistance de la
 * communauté, comme le tableau de bord). Chaque tableau est lu dans la communauté de la session ; seul son auteur
 * l'écrit ; il n'est lisible par les autres que s'il est partagé, et seul un gestionnaire peut le partager.
 * Écritures : jeton CSRF vérifié ici. Cellule : requête en arrière-plan avec détection de conflit (valeur vue par la
 * personne) ; le reste répond en JSON pour back-office-sheet.js ou par redirection sans JavaScript.
 */
final class BackOfficeHomeController
{
    private const CSV_MAX_BYTES = 262144;

    public function __construct(
        private BoSheetRepository $sheets,
        private BackOfficeNavPrefsRepository $prefs,
    ) {
    }

    public static function role(): string
    {
        $gate = Gate::getInstance();

        return $gate->allows('admin.organization') || $gate->allows('admin.access') ? HomeLayout::ROLE_MANAGER : HomeLayout::ROLE_SUPPORT;
    }

    public static function canShare(): bool
    {
        return self::role() === HomeLayout::ROLE_MANAGER;
    }

    /**
     * Disposition de la personne, nettoyée (tableaux encore lisibles uniquement).
     *
     * @return array{items: list<array{id: string, hidden: bool, size: string}>, readable: list<array{id: int, title: string, shared: bool, mine: bool, updated_at: string}>}
     */
    public function layoutFor(int $tenantId, int $userId): array
    {
        $readable = $this->sheets->listReadable($tenantId, $userId);
        $raw = $this->prefs->get($tenantId, $userId)[BackOfficeNavPrefsRepository::COL_HOME];

        return [
            'items' => HomeLayout::normalize($raw, self::role(), array_column($readable, 'id')),
            'readable' => $readable,
        ];
    }

    /** POST /back-office/accueil/disposition */
    public function saveLayout(Request $request, array $params = []): Response
    {
        [$tenantId, $userId] = self::ids();
        if ($tenantId < 1 || !Csrf::verify($request)) {
            return self::back(false, 'Session expirée. Rechargez la page.', 'back-office?personnaliser=1');
        }
        if ($request->inputString('reset') !== '') {
            $this->prefs->put($tenantId, $userId, BackOfficeNavPrefsRepository::COL_HOME, null);

            return self::back(true, 'Disposition par défaut rétablie.', 'back-office?personnaliser=1');
        }
        $readable = array_column($this->sheets->listReadable($tenantId, $userId), 'id');
        $stored = HomeLayout::fromForm(
            self::list($request->input('order')),
            self::list($request->input('visible')),
            is_array($request->input('size')) ? $request->input('size') : [],
            self::role(),
            $readable
        );
        $move = explode('|', $request->inputString('move'), 2);
        $stay = false;
        if (count($move) === 2 && in_array($move[0], ['up', 'down'], true)) {
            $stored = HomeLayout::stored(HomeLayout::move($stored['items'], $move[1], $move[0] === 'up' ? -1 : 1));
            $stay = true;
        }
        if (!$this->prefs->put($tenantId, $userId, BackOfficeNavPrefsRepository::COL_HOME, $stored)) {
            return self::back(false, 'Disposition indisponible pour le moment (mise à jour de la base en attente).', 'back-office?personnaliser=1');
        }
        if (self::wantsJson($request)) {
            return Response::json(['ok' => true, 'message' => 'Disposition enregistrée.']);
        }

        return self::back(true, 'Disposition enregistrée.', $stay || $request->inputString('stay') !== '' ? 'back-office?personnaliser=1' : 'back-office');
    }

    /** POST /back-office/tableaux — crée un tableau vide et l'ajoute en tête de l'accueil. */
    public function create(Request $request, array $params = []): Response
    {
        [$tenantId, $userId] = self::ids();
        if ($tenantId < 1 || !Csrf::verify($request)) {
            return self::back(false, 'Session expirée. Rechargez la page.', 'back-office');
        }
        try {
            if ($this->sheets->countOwned($tenantId, $userId) >= BoSheetRepository::MAX_PER_OWNER) {
                return self::back(false, sprintf('%d tableaux au plus. Supprimez-en un avant d’en créer.', BoSheetRepository::MAX_PER_OWNER), 'back-office?personnaliser=1');
            }
            $id = $this->sheets->create($tenantId, $userId, $request->inputString('title'), SheetData::blank());
        } catch (\Throwable $e) {
            error_log('[bo-sheet] create: ' . $e->getMessage());

            return self::back(false, 'Tableaux indisponibles pour le moment (mise à jour de la base en attente).', 'back-office');
        }
        $layout = $this->layoutFor($tenantId, $userId);
        $this->prefs->put($tenantId, $userId, BackOfficeNavPrefsRepository::COL_HOME, HomeLayout::stored(HomeLayout::withSheet($layout['items'], $id)));

        return self::back(true, 'Tableau créé. Cliquez dans une case pour la remplir.', 'back-office#tableau-' . $id);
    }

    /** GET /back-office/tableaux/{id} — le tableau en pleine page. */
    public function show(Request $request, array $params = []): Response
    {
        [$tenantId, $userId] = self::ids();
        $sheet = $this->readable($tenantId, $userId, (int) ($params['id'] ?? 0));
        if ($sheet === null) {
            return self::back(false, 'Tableau introuvable.', 'back-office');
        }

        return Response::view('layout.main', [
            'content' => 'admin.organization.sheet_page',
            'title' => $sheet['title'],
            'isBackOfficeShell' => true,
            'boPageGroup' => 'Pilotage',
            'boPageTitle' => $sheet['title'],
            'boPageKicker' => 'PILOTAGE · TABLEAU',
            'boPageSubtitle' => $sheet['owner_user_id'] === $userId
                ? 'Votre tableau. Les cases s’enregistrent dès que vous les quittez.'
                : 'Tableau partagé par un gestionnaire de la communauté, en lecture seule.',
            'backOfficePageCss' => ['pilotage-dashboard.css', 'back-office-home.css'],
            'sheetView' => self::view($sheet, $userId),
        ]);
    }

    /** POST /back-office/tableaux/{id}/cellule — une case, sans rechargement. */
    public function cell(Request $request, array $params = []): Response
    {
        [$tenantId, $userId] = self::ids();
        if (!Csrf::verify($request)) {
            return Response::json(['ok' => false, 'message' => 'Session expirée. Rechargez la page.', 'reload' => true], 419);
        }
        $id = (int) ($params['id'] ?? 0);
        $row = (int) $request->inputString('r', '-1');
        $col = (int) $request->inputString('c', '-1');
        $value = $request->inputString('value');
        $seen = $request->input('seen');
        $seen = is_string($seen) ? $seen : null;

        // Deux essais : si une autre case a été écrite entre la lecture et l'écriture, on relit et on réapplique.
        for ($attempt = 0; $attempt < 2; $attempt++) {
            $sheet = $this->sheets->find($tenantId, $id);
            if ($sheet === null || $sheet['owner_user_id'] !== $userId) {
                return Response::json(['ok' => false, 'message' => 'Ce tableau n’est pas modifiable.'], 403);
            }
            [$data, $result, $current] = SheetData::setCell($sheet['data'], $row, $col, $value, $seen);
            if ($result === 'bounds') {
                return Response::json(['ok' => false, 'message' => 'Case introuvable : le tableau a changé, rechargez-le.', 'reload' => true], 409);
            }
            if ($result === 'conflict') {
                return Response::json([
                    'ok' => false,
                    'conflict' => true,
                    'message' => 'Cette case a été modifiée ailleurs entre-temps. Sa valeur actuelle est affichée.',
                    'value' => $current,
                    'version' => $sheet['version'],
                ], 409);
            }
            if ($this->sheets->saveData($tenantId, $id, $userId, $data, $sheet['version'])) {
                return Response::json([
                    'ok' => true,
                    'value' => $current,
                    'version' => $sheet['version'] + 1,
                    'computed' => self::formulaValues($data),
                ]);
            }
        }

        return Response::json(['ok' => false, 'message' => 'Le tableau change trop vite : rechargez-le.', 'reload' => true], 409);
    }

    /** POST /back-office/tableaux/{id} — enregistrement de toutes les cases (formulaire sans JavaScript). */
    public function saveAll(Request $request, array $params = []): Response
    {
        [$tenantId, $userId] = self::ids();
        $id = (int) ($params['id'] ?? 0);
        $sheet = $this->editable($tenantId, $userId, $id);
        if ($sheet === null || !Csrf::verify($request)) {
            return self::back(false, 'Ce tableau n’est pas modifiable.', 'back-office');
        }
        $cells = $request->input('cells');
        $cols = $request->input('cols');
        $data = $sheet['data'];
        foreach ($data['cols'] as $c => $col) {
            if (is_array($cols) && is_string($cols[$c] ?? null)) {
                $data['cols'][$c]['name'] = SheetData::clean($cols[$c], SheetData::MAX_HEADER);
            }
        }
        foreach ($data['rows'] as $r => $row) {
            foreach ($row as $c => $v) {
                $new = is_array($cells) && is_array($cells[$r] ?? null) ? ($cells[$r][$c] ?? null) : null;
                if (is_string($new)) {
                    $data['rows'][$r][$c] = SheetData::clean($new, SheetData::MAX_CELL);
                }
            }
        }
        if (!$this->sheets->saveData($tenantId, $id, $userId, $data, (int) $request->inputString('version', '0'))) {
            return self::back(false, 'Le tableau a été modifié ailleurs entre-temps : rien n’a été écrasé. Rechargez-le et recommencez.', 'back-office/tableaux/' . $id);
        }

        return self::back(true, 'Tableau enregistré.', 'back-office/tableaux/' . $id);
    }

    /** POST /back-office/tableaux/{id}/structure — lignes, colonnes, en-têtes. */
    public function structure(Request $request, array $params = []): Response
    {
        [$tenantId, $userId] = self::ids();
        $id = (int) ($params['id'] ?? 0);
        $sheet = $this->editable($tenantId, $userId, $id);
        $back = self::returnPath($request, $id);
        if ($sheet === null || !Csrf::verify($request)) {
            return $this->reply($request, false, 'Ce tableau n’est pas modifiable.', null, $back, 403);
        }
        $version = (int) $request->inputString('version', '0');
        if ($version !== $sheet['version']) {
            return $this->reply($request, false, 'Le tableau a changé entre-temps : il a été rechargé, recommencez.', $sheet, $back, 409);
        }
        // « del_row:3 » : un bouton de formulaire ne porte qu'une valeur, l'index voyage avec l'opération.
        $op = $request->inputString('op');
        $index = (int) $request->inputString('index', '-1');
        if (str_contains($op, ':')) {
            [$op, $rawIndex] = explode(':', $op, 2);
            $index = (int) $rawIndex;
        }
        [$data, $error] = SheetData::structure($sheet['data'], $op, $index, $request->inputString('name'));
        if ($error !== '') {
            return $this->reply($request, false, $error, $sheet, $back, 422);
        }
        if (!$this->sheets->saveData($tenantId, $id, $userId, $data, $version)) {
            return $this->reply($request, false, 'Le tableau a changé entre-temps : rechargez-le.', $this->sheets->find($tenantId, $id), $back, 409);
        }

        return $this->reply($request, true, 'Tableau mis à jour.', $this->sheets->find($tenantId, $id), $back, 200);
    }

    /** POST /back-office/tableaux/{id}/reglages — titre et partage. */
    public function settings(Request $request, array $params = []): Response
    {
        [$tenantId, $userId] = self::ids();
        $id = (int) ($params['id'] ?? 0);
        $sheet = $this->editable($tenantId, $userId, $id);
        $back = self::returnPath($request, $id);
        if ($sheet === null || !Csrf::verify($request)) {
            return self::back(false, 'Ce tableau n’est pas modifiable.', $back);
        }
        $shared = $request->inputString('shared') === '1';
        if ($shared && !self::canShare()) {
            return self::back(false, 'Seul un gestionnaire de la communauté peut partager un tableau.', $back);
        }
        $title = $request->inputString('title');
        $this->sheets->saveMeta($tenantId, $id, $userId, $title !== '' ? $title : $sheet['title'], $shared);

        return self::back(true, $shared ? 'Tableau enregistré et partagé en lecture avec la communauté.' : 'Tableau enregistré.', $back);
    }

    /** POST /back-office/tableaux/{id}/import — remplace le contenu par un fichier CSV. */
    public function import(Request $request, array $params = []): Response
    {
        [$tenantId, $userId] = self::ids();
        $id = (int) ($params['id'] ?? 0);
        $sheet = $this->editable($tenantId, $userId, $id);
        $back = self::returnPath($request, $id);
        if ($sheet === null || !Csrf::verify($request)) {
            return self::back(false, 'Ce tableau n’est pas modifiable.', $back);
        }
        $file = $_FILES['csv'] ?? null;
        if (!is_array($file) || (int) ($file['error'] ?? 1) !== UPLOAD_ERR_OK || !is_uploaded_file((string) $file['tmp_name'])) {
            return self::back(false, 'Choisissez un fichier CSV.', $back);
        }
        if ((int) $file['size'] > self::CSV_MAX_BYTES) {
            return self::back(false, 'Fichier trop lourd (256 Ko au plus).', $back);
        }
        [$data, $warning] = SheetData::fromCsv((string) file_get_contents((string) $file['tmp_name']));
        if (!$this->sheets->saveData($tenantId, $id, $userId, $data, $sheet['version'])) {
            return self::back(false, 'Le tableau a changé entre-temps : rechargez-le et recommencez.', $back);
        }

        return self::back(true, 'Fichier importé.' . ($warning !== '' ? ' ' . $warning : ''), $back);
    }

    /** GET /back-office/tableaux/{id}/export.csv */
    public function export(Request $request, array $params = []): Response
    {
        [$tenantId, $userId] = self::ids();
        $sheet = $this->readable($tenantId, $userId, (int) ($params['id'] ?? 0));
        if ($sheet === null) {
            return self::back(false, 'Tableau introuvable.', 'back-office');
        }
        $name = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower((string) iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $sheet['title']))), '-');

        return (new Response())
            ->setBody(SheetData::toCsv($sheet['data']))
            ->header('Content-Type', 'text/csv; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="' . ($name !== '' ? $name : 'tableau') . '.csv"')
            ->header('X-Content-Type-Options', 'nosniff')
            ->header('Cache-Control', 'private, no-store');
    }

    /** POST /back-office/tableaux/{id}/supprimer */
    public function delete(Request $request, array $params = []): Response
    {
        [$tenantId, $userId] = self::ids();
        $id = (int) ($params['id'] ?? 0);
        if ($this->editable($tenantId, $userId, $id) === null || !Csrf::verify($request)) {
            return self::back(false, 'Ce tableau n’est pas modifiable.', 'back-office');
        }
        $this->sheets->delete($tenantId, $id, $userId);

        return self::back(true, 'Tableau supprimé.', 'back-office');
    }

    /**
     * Données d'affichage d'un tableau pour la vue (partial admin/organization/_sheet).
     *
     * @param array{id: int, owner_user_id: int, title: string, data: array{cols: list<array{name: string}>, rows: list<list<string>>}, shared: bool, version: int} $sheet
     * @return array<string, mixed>
     */
    public static function view(array $sheet, int $userId): array
    {
        return [
            'id' => $sheet['id'],
            'title' => $sheet['title'],
            'data' => $sheet['data'],
            'computed' => SheetData::computed($sheet['data']),
            'shared' => $sheet['shared'],
            'version' => $sheet['version'],
            'editable' => $sheet['owner_user_id'] === $userId,
            'can_share' => self::canShare(),
        ];
    }

    /**
     * @param array{cols: list<array{name: string}>, rows: list<list<string>>} $data
     * @return array<string, string> « ligne:colonne » → valeur calculée, pour les cases à formule
     */
    private static function formulaValues(array $data): array
    {
        $out = [];
        $computed = SheetData::computed($data);
        foreach ($data['rows'] as $r => $row) {
            foreach ($row as $c => $v) {
                if (str_starts_with($v, '=')) {
                    $out[$r . ':' . $c] = $computed[$r][$c];
                }
            }
        }

        return $out;
    }

    /** @return array{id: int, tenant_id: int, owner_user_id: int, title: string, data: array{cols: list<array{name: string}>, rows: list<list<string>>}, shared: bool, version: int, updated_at: string}|null */
    private function readable(int $tenantId, int $userId, int $id): ?array
    {
        try {
            $sheet = $this->sheets->find($tenantId, $id);
        } catch (\Throwable) {
            return null;
        }

        return $sheet !== null && ($sheet['owner_user_id'] === $userId || $sheet['shared']) ? $sheet : null;
    }

    /** @return array{id: int, tenant_id: int, owner_user_id: int, title: string, data: array{cols: list<array{name: string}>, rows: list<list<string>>}, shared: bool, version: int, updated_at: string}|null */
    private function editable(int $tenantId, int $userId, int $id): ?array
    {
        $sheet = $this->readable($tenantId, $userId, $id);

        return $sheet !== null && $sheet['owner_user_id'] === $userId ? $sheet : null;
    }

    /** @param array{id: int, owner_user_id: int, title: string, data: array{cols: list<array{name: string}>, rows: list<list<string>>}, shared: bool, version: int}|null $sheet */
    private function reply(Request $request, bool $ok, string $message, ?array $sheet, string $back, int $status): Response
    {
        if (!self::wantsJson($request)) {
            return self::back($ok, $message, $back);
        }
        $out = ['ok' => $ok, 'message' => $message];
        if ($sheet !== null) {
            [, $userId] = self::ids();
            $sheetView = self::view($sheet, $userId);
            $sheetCompact = $request->inputString('compact') === '1';
            ob_start();
            require base_path('views/admin/organization/_sheet.php');
            $out['html'] = (string) ob_get_clean();
            $out['version'] = $sheet['version'];
        }

        return Response::json($out, $status);
    }

    private static function returnPath(Request $request, int $id): string
    {
        return $request->inputString('return') === 'accueil' ? 'back-office#tableau-' . $id : 'back-office/tableaux/' . $id;
    }

    /** @return array{0: int, 1: int} */
    private static function ids(): array
    {
        return [(int) Session::get('tenant_id'), (int) Session::get('user_id')];
    }

    /** @return list<mixed> */
    private static function list(mixed $v): array
    {
        return is_array($v) ? array_values(array_slice($v, 0, 60)) : [];
    }

    private static function back(bool $ok, string $message, string $path): Response
    {
        Session::flash($ok ? 'success' : 'error', $message);

        return Response::redirect(url($path));
    }

    private static function wantsJson(Request $request): bool
    {
        return str_contains(strtolower((string) $request->header('Accept')), 'application/json')
            || strtolower((string) $request->header('X-Requested-With')) === 'xmlhttprequest';
    }
}
