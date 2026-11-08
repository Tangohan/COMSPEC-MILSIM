<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\GameEquipmentRepository;
use App\Repositories\GameItemIconRepository;
use App\Services\GameEquipment\DotationOverviewService;
use App\Services\GameEquipment\GameItemKinds;
use App\Services\Tactical\AtakCommunityBriefService;
use App\Support\ArsenalLoadoutItems;
use App\Support\AtakCommunityBrief;

/**
 * Consignes et kit ATAK (/back-office/ressources/atak-consignes) : ce que lit l'app Communauté du téléphone.
 * Droits : TenantResourceAdminMiddleware sur chaque route, comme Configuration ATAK. Chaque écriture vérifie le jeton
 * CSRF et ne touche qu'à la communauté de la session. Réponse JSON pour un envoi en arrière-plan
 * (back-office-atak-brief.js), sinon message et retour à la page : les formulaires marchent sans JavaScript.
 */
final class AdminAtakBriefController
{
    private const BASE = 'back-office/ressources/atak-consignes';

    /** Période du classement des objets les plus portés. */
    private const CATALOG_DAYS = 90;

    private const SEARCH_LIMIT = 30;

    public function __construct(
        private AtakCommunityBriefService $brief,
        private GameEquipmentRepository $equipment,
        private DotationOverviewService $dotation,
    ) {
    }

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $json = self::wantsJson($request);
        if ($tenantId < 1) {
            return $json ? Response::json(['ok' => false, 'message' => 'Aucune communauté active.', 'reload' => true], 403) : Response::redirect(url('login'));
        }
        $genre = self::genre($request->queryString('genre'));
        if ($json && $request->queryString('fragment') === '1') {
            return Response::json(['ok' => true, 'html' => $this->fragment($tenantId, $genre)]);
        }
        $q = mb_substr(trim($request->queryString('q')), 0, 80);

        return Response::view('layout.main', [
            'content' => 'admin.atak-brief.index',
            'title' => 'Consignes et kit ATAK',
            'backOfficePageCss' => ['effectifs-kit.css', 'back-office-atak-brief.css'],
            'boPageTitle' => 'Consignes et kit ATAK',
            'boPageKicker' => 'RESSOURCES · TACTIQUE',
            'boPageSubtitle' => 'Consignes, ordres, tips et kit obligatoire que les joueurs lisent dans l’app Communauté du téléphone.',
            'form' => $this->viewData($tenantId, $genre) + ['q' => $q, 'picks' => $this->search($tenantId, $q)],
        ]);
    }

    /** Recherche dans le catalogue de la communauté (sélecteur du kit). */
    public function catalogue(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            return Response::json(['ok' => false, 'message' => 'Aucune communauté active.', 'reload' => true], 403);
        }

        return Response::json(['ok' => true, 'items' => $this->search($tenantId, mb_substr(trim($request->queryString('q')), 0, 80))]);
    }

    public function saveNote(Request $request, array $params = []): Response
    {
        $index = self::index_($request);

        return $this->write($request, AtakCommunityBriefService::NOTES, static function (array $notes) use ($request, $index): array {
            $note = AtakCommunityBrief::validNote($request->inputString('kind'), $request->inputString('note_title'), $request->inputString('note_body'));

            return AtakCommunityBriefService::put($notes, $index, $note, AtakCommunityBrief::MAX_NOTES, sprintf('Plafond atteint : %d notes au plus. Retirez-en une avant d’en ajouter.', AtakCommunityBrief::MAX_NOTES));
        }, $index === null ? 'Note ajoutée.' : 'Note modifiée.', 'consignes');
    }

    public function deleteNote(Request $request, array $params = []): Response
    {
        $index = self::index_($request) ?? -1;

        return $this->write($request, AtakCommunityBriefService::NOTES, static fn (array $notes): array => AtakCommunityBriefService::remove($notes, $index), 'Note supprimée.', 'consignes');
    }

    public function moveNote(Request $request, array $params = []): Response
    {
        $index = self::index_($request) ?? -1;
        $step = $request->inputString('direction') === 'up' ? -1 : 1;
        $kind = self::genre($request->inputString('genre'));
        $moved = -1;
        $reply = $this->write($request, AtakCommunityBriefService::NOTES, static function (array $notes) use ($index, $step, $kind, &$moved): array {
            [$notes, $moved] = AtakCommunityBriefService::move($notes, $index, $step, $kind);

            return $notes;
        }, 'Note déplacée.', 'consignes', static function () use (&$moved): array {
            return ['focus' => 'bo-brief-note-' . $moved];
        });

        return $reply;
    }

    public function saveKit(Request $request, array $params = []): Response
    {
        $index = self::index_($request);

        return $this->write($request, AtakCommunityBriefService::KIT, static function (array $kit) use ($request, $index): array {
            $entry = AtakCommunityBrief::validKitEntry(
                self::classesFrom($request),
                self::countFrom($request->inputString('count', '1')),
                $request->inputString('label'),
            );
            AtakCommunityBriefService::assertNoDuplicate($kit, $entry['classes'], $index);

            return AtakCommunityBriefService::put($kit, $index, $entry, AtakCommunityBrief::MAX_KIT, sprintf('Plafond atteint : %d objets au plus. Retirez-en un avant d’en ajouter.', AtakCommunityBrief::MAX_KIT));
        }, $index === null ? 'Objet ajouté au kit.' : 'Objet du kit modifié.', 'kit');
    }

    public function deleteKit(Request $request, array $params = []): Response
    {
        $index = self::index_($request) ?? -1;

        return $this->write($request, AtakCommunityBriefService::KIT, static fn (array $kit): array => AtakCommunityBriefService::remove($kit, $index), 'Objet retiré du kit.', 'kit');
    }

    /**
     * Objet demandé (saisie avancée, sinon choix dans le catalogue), puis variantes cochées et variantes saisies.
     *
     * @return list<string>
     */
    public static function classesFrom(Request $request): array
    {
        $manual = trim($request->inputString('class_manual'));
        $primary = $manual !== '' ? $manual : trim($request->inputString('class_pick'));
        $picked = $request->input('variants', []);
        $variants = is_array($picked) ? array_values(array_filter($picked, 'is_string')) : [];
        $typed = preg_split('/[\s,;\/]+/', $request->inputString('variants_manual')) ?: [];

        return array_values(array_filter(
            array_map('trim', array_merge([$primary], $variants, $typed)),
            static fn (string $c): bool => $c !== '',
        ));
    }

    private static function countFrom(string $raw): int
    {
        $raw = trim($raw);

        return ctype_digit($raw) && strlen($raw) < 6 ? (int) $raw : 0;
    }

    /**
     * @param callable(list<array<string, mixed>>): list<array<string, mixed>> $change
     * @param (callable(): array<string, mixed>)|null $extra
     */
    private function write(Request $request, string $field, callable $change, string $done, string $anchor, ?callable $extra = null): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            return $this->reply($request, 0, ['ok' => false, 'status' => 403, 'message' => 'Aucune communauté active.', 'reload' => true], $anchor);
        }
        if (!Csrf::verify($request)) {
            return $this->reply($request, 0, ['ok' => false, 'status' => 419, 'message' => 'Session expirée. Rechargez la page, puis réessayez.', 'reload' => true], $anchor);
        }
        if (!$this->brief->ready()) {
            return $this->reply($request, 0, ['ok' => false, 'status' => 503, 'message' => 'Impossible d’enregistrer : la base de données n’est pas à jour. Contactez le support plateforme.'], $anchor);
        }
        try {
            $result = $this->brief->change($tenantId, $field, $request->inputString('rev'), $change);
        } catch (\Throwable $e) {
            error_log('[atak-brief] ' . $e->getMessage());
            $result = ['ok' => false, 'status' => 500, 'message' => 'L’enregistrement a échoué. Réessayez dans un instant.'];
        }
        if ($result['ok']) {
            $result['message'] = $done;
            if ($extra !== null) {
                $result += $extra();
            }
        }

        return $this->reply($request, $tenantId, $result, $anchor);
    }

    /**
     * @param array<string, mixed> $result ok, status, message et champs JSON en plus
     */
    private function reply(Request $request, int $tenantId, array $result, string $anchor): Response
    {
        $ok = (bool) $result['ok'];
        $status = (int) $result['status'];
        $message = (string) $result['message'];
        $genre = self::genre($request->inputString('genre'));
        if (!self::wantsJson($request)) {
            Session::flash($ok ? 'success' : 'error', $message);
            $query = $genre !== '' ? '?genre=' . $genre : '';

            return Response::redirect(url(self::BASE) . $query . '#' . $anchor);
        }
        unset($result['status']);
        // Déjà écrit : un affichage impossible à recalculer n'est pas un échec de l'enregistrement.
        if ($tenantId > 0 && ($ok || !empty($result['conflict']))) {
            try {
                $result['html'] = $this->fragment($tenantId, $genre);
            } catch (\Throwable $e) {
                error_log('[atak-brief] fragment: ' . $e->getMessage());
            }
        }

        return Response::json($result, $status);
    }

    private function fragment(int $tenantId, string $genre): string
    {
        $html = Response::view('admin.atak-brief._live', ['form' => $this->viewData($tenantId, $genre) + ['q' => '', 'picks' => []]])->body();
        if (function_exists('locale') && locale() === 'en') {
            $html = \App\Support\HtmlTranslator::translate($html);
        }

        return $html;
    }

    /**
     * Passé sous la clé « form » : EmailPrivacy n'y retire pas les adresses e-mail. Une consigne qui en contient une
     * (admin de communauté) reviendrait sinon amputée dans son formulaire, et serait enregistrée ainsi.
     *
     * @return array<string, mixed>
     */
    private function viewData(int $tenantId, string $genre): array
    {
        $state = $this->brief->state($tenantId);
        $classes = [];
        foreach ($state['kit'] as $entry) {
            array_push($classes, ...$entry['classes']);
        }
        $known = [];
        try {
            $known = $this->equipment->catalogueFor($tenantId, $classes);
            $catalogSize = $this->equipment->catalogueCount($tenantId);
        } catch (\Throwable $e) {
            error_log('[atak-brief] catalogue: ' . $e->getMessage());
            $catalogSize = 0;
        }
        $kitRows = [];
        foreach ($state['kit'] as $i => $entry) {
            $kitRows[$i] = $entry + ['items' => array_map(fn (string $class): array => $this->describe($class, $known[strtolower($class)] ?? null), $entry['classes'])];
        }

        return [
            'ready' => $this->brief->ready(),
            'genre' => $genre,
            'notes' => $state['notes'],
            'kit' => $kitRows,
            'notesRev' => $state['notes_rev'],
            'kitRev' => $state['kit_rev'],
            'updatedAt' => $state['updated_at'],
            'guide' => $state['guide'],
            'unread' => $state['unread'],
            'fit' => $state['fit'],
            'catalogSize' => $catalogSize,
        ];
    }

    /**
     * @param array<string, mixed>|null $known ligne du catalogue, null si l'objet n'a pas encore été vu en jeu
     * @return array{class: string, name: string, kind: string, kindLabel: string, mod: string, icon: string, known: bool}
     */
    private function describe(string $class, ?array $known): array
    {
        $name = trim((string) ($known['name'] ?? ''));
        $kind = GameItemKinds::normalize($known['kind'] ?? 'item');

        return [
            'class' => $class,
            'name' => $name !== '' ? $name : ArsenalLoadoutItems::displayName($class),
            'kind' => $kind,
            'kindLabel' => GameItemKinds::label($kind),
            'mod' => (string) ($known['mod'] ?? ''),
            'icon' => (string) (GameItemIconRepository::urlFor($class) ?? ''),
            'known' => $known !== null,
        ];
    }

    /**
     * @return list<array{class: string, name: string, kind: string, kindLabel: string, mod: string, icon: string, known: bool}>
     */
    private function search(int $tenantId, string $q): array
    {
        return array_map(
            fn (array $row): array => $this->describe((string) $row['class'], $row),
            $this->dotation->catalogue($tenantId, self::CATALOG_DAYS, '', $q, self::SEARCH_LIMIT),
        );
    }

    /** Place dans la liste. Champ vide : nouvel élément. Valeur illisible : -1, refusée ensuite (jamais un ajout par erreur). */
    private static function index_(Request $request): ?int
    {
        $raw = $request->inputString('index');
        if ($raw === '') {
            return null;
        }

        return ctype_digit($raw) && strlen($raw) < 6 ? (int) $raw : -1;
    }

    private static function genre(string $raw): string
    {
        return in_array($raw, AtakCommunityBrief::KINDS, true) ? $raw : '';
    }

    private static function wantsJson(Request $request): bool
    {
        return str_contains(strtolower((string) $request->header('Accept')), 'application/json')
            || strtolower((string) $request->header('X-Requested-With')) === 'xmlhttprequest';
    }
}
