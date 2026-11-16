<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\Intel\IntelIntakeDesk;
use App\Services\Intel\IntelIntakePresenter;
use App\Services\Intel\IntelIntakeService;
use App\Services\Sse\SseAccessCodeService;
use App\Services\Sse\SseRedactionService;
use App\Services\Sse\SseAccessPolicy;
use App\Support\SseAccessCatalog;

/**
 * Anciennes adresses du back-office « Remontées » : toutes les pages vivent désormais dans le bureau SSE
 * (App\Controllers\Web\SseIntakeController, /atak/sse/remontees/*). Chaque adresse renvoie vers son équivalent :
 *   GET  /back-office/remontees                         → 302 /atak/sse/remontees (filtres conservés)
 *   GET  /back-office/remontees/joueurs                 → 302 /atak/sse/remontees/joueurs
 *   GET  /back-office/remontees/joueurs/{key}           → 302 /atak/sse/remontees/joueurs/{key}
 *   GET  /back-office/remontees/{source}/{id}           → 302 /atak/sse/remontees/{source}/{id} (même identifiant)
 *   GET  /back-office/remontees/{source}/{id}/suggestions → 302 même chemin au bureau
 *   POST /back-office/remontees/{source}/{id}/{action}  → 307 même chemin au bureau (corps et noms de champs conservés)
 * 302 et non 301 : la redirection dépend de la session (sans accès au bureau SSE, une page l'explique au lieu de
 * renvoyer vers le sas) et ne doit pas rester en cache dans le navigateur.
 *
 * Restent ici les règles partagées : l'ancien droit d'accès (allowed), l'accès aux fiches des membres, le badge du
 * tableau de bord et les listes des fenêtres (suggest), utilisés par le bureau.
 */
final class AdminIntelIntakeController
{
    public const SSE_BASE = 'atak/sse/remontees';

    /**
     * @param (\Closure(): bool)|null $sseReachable la session peut-elle entrer au bureau SSE ? (tests)
     */
    public function __construct(private ?\Closure $sseReachable = null)
    {
    }

    public static function allowed(): bool
    {
        if (!function_exists('can')) {
            return false;
        }
        $legacy = false;
        foreach (['admin.system', 'admin.organization', 'admin.access', 'atak.sse.case.manage', 'atak.sse.grant'] as $perm) {
            if (can($perm)) {
                $legacy = true;
                break;
            }
        }

        // Remontées du renseignement : section Dossiers d'enquête, niveau « écrire » (SseAccessPolicy), garde d'origine ci-dessus.
        return SseAccessPolicy::sessionAllows('dossiers', SseAccessCatalog::LEVEL_WRITE, ['guest' => false, 'legacy' => $legacy, 'door_mode' => SseAccessPolicy::DOOR_ENTRY]);
    }

    /** Lien vers la fiche du membre et identifiant Steam : réservés aux administrateurs de la communauté. */
    public static function canSeeMembers(): bool
    {
        return function_exists('can') && (can('admin.organization') || can('admin.access') || can('admin.system'));
    }

    public function index(Request $request, array $params = []): Response
    {
        return $this->guardAndRedirect($request, self::SSE_BASE);
    }

    public function players(Request $request, array $params = []): Response
    {
        return $this->guardAndRedirect($request, self::SSE_BASE . '/joueurs');
    }

    public function player(Request $request, array $params = []): Response
    {
        return $this->guardAndRedirect($request, self::SSE_BASE . '/joueurs/' . rawurlencode((string) ($params['key'] ?? '')));
    }

    public function show(Request $request, array $params = []): Response
    {
        return $this->guardAndRedirect($request, self::itemPath($params));
    }

    public function suggestions(Request $request, array $params = []): Response
    {
        return $this->guardAndRedirect($request, self::itemPath($params) . '/suggestions');
    }

    /** Les formulaires restés ouverts sur l'ancienne page : 307, le navigateur renvoie le même corps au bureau. */
    public function action(Request $request, array $params = []): Response
    {
        return $this->guardAndRedirect($request, self::itemPath($params) . '/' . rawurlencode((string) ($params['action'] ?? '')), 307);
    }

    /** @param array<string, mixed> $params */
    private static function itemPath(array $params): string
    {
        return self::SSE_BASE . '/' . rawurlencode((string) ($params['source'] ?? '')) . '/' . (int) ($params['id'] ?? 0);
    }

    /** Équivalent au bureau, filtres de l'adresse conservés (valeurs simples seulement). */
    public static function target(string $path, array $query = []): string
    {
        $query = array_filter($query, static fn ($v, $k): bool => is_string($k) && is_scalar($v) && (string) $v !== '', ARRAY_FILTER_USE_BOTH);

        return url($path) . ($query !== [] ? '?' . http_build_query($query) : '');
    }

    private function guardAndRedirect(Request $request, string $path, int $status = 302): Response
    {
        if (!Session::get('user_id')) {
            return Response::redirect(url('login'));
        }
        $target = self::target($path, $status === 307 ? [] : $request->queryParams());
        if (!$this->reachable()) {
            // Ancien droit du back-office sans entrée au bureau : une page claire plutôt que le sas ou une page vide.
            return Response::view('layout.main', [
                'content' => 'admin.intel.intake_moved',
                'title' => 'Remontées',
                'pageTitle' => 'Remontées',
                'movedTarget' => $target,
                'movedHadAccess' => self::allowed(),
            ])->setStatusCode(403);
        }

        return Response::redirect($target, $status);
    }

    private function reachable(): bool
    {
        if ($this->sseReachable !== null) {
            return ($this->sseReachable)();
        }
        try {
            $svc = new SseAccessCodeService();

            return $svc->hasActiveClearance() || $svc->canEnterWithoutCode();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return list<array{value: string, label: string, kind: string}>
     */
    public static function suggest(IntelIntakeDesk $desk, int $tenantId, string $source, int $id, string $kind, string $query, bool $canLink): array
    {
        $query = mb_substr(trim($query), 0, 80);
        $item = $desk->intake()->schemaReady() ? $desk->intake()->find($tenantId, $source, $id) : null;
        if ($item === null) {
            return [];
        }
        if ($kind === 'transmettre') {
            return $desk->advisor()->forwardTargets($tenantId, $query);
        }
        if ($kind === 'lien') {
            return $canLink && $source === 'fiche' ? $desk->linkSearch($query) : [];
        }
        if ($kind !== 'doublon' || mb_strlen($query) < 2) {
            return [];
        }
        $out = [];
        foreach ($desk->intake()->listItems($tenantId, ['state' => 'all', 'q' => $query], 0, 9) as $it) {
            if ($it['source'] === $source && $it['id'] === $id) {
                continue;
            }
            // Référence et type seulement : le titre peut porter un passage caviardé.
            $out[] = ['value' => $it['source'] . '/' . $it['id'], 'label' => $it['ref'] . ' — ' . $it['type_label'] . ' · ' . IntelIntakePresenter::when((string) $it['at']), 'kind' => IntelIntakeService::SOURCES[$it['source']]];
        }

        return $out;
    }

    /**
     * Recherche sur indicatif (courant ou vu), compte, Steam ou clé.
     *
     * @param list<array<string, mixed>> $players
     * @return list<array<string, mixed>>
     */
    public static function filterPlayers(array $players, string $q): array
    {
        $q = mb_strtolower(trim($q));
        if ($q === '') {
            return $players;
        }

        return array_values(array_filter($players, static function (array $p) use ($q): bool {
            $hay = mb_strtolower(implode(' ', array_merge(
                [(string) $p['callsign'], (string) $p['account'], (string) $p['steam'], (string) $p['key']],
                array_map('strval', (array) ($p['callsigns_seen'] ?? []))
            )));

            return str_contains($hay, $q);
        }));
    }

    /**
     * @param list<array<string, mixed>> $players
     * @return list<array<string, mixed>>
     */
    public static function sortPlayers(array $players, string $sort): array
    {
        $name = static fn (array $p): string => mb_strtolower((string) ($p['callsign'] !== '' ? $p['callsign'] : $p['account']));
        usort($players, static function (array $a, array $b) use ($sort, $name): int {
            $cmp = match (true) {
                $sort === 'name' => strcmp($name($a), $name($b)),
                $sort === 'last' => strcmp((string) $b['last'], (string) $a['last']),
                $sort === 'total' => $b['total'] <=> $a['total'],
                default => ($b['counts'][$sort] ?? 0) <=> ($a['counts'][$sort] ?? 0),
            };

            return $cmp !== 0 ? $cmp : (($b['total'] <=> $a['total']) ?: strcmp($name($a), $name($b)));
        });

        return $players;
    }

    /** Badge du tableau de bord : nombre de remontées « À traiter » (null si zéro, inaccessible ou en erreur). */
    public static function asideBadge(): ?string
    {
        try {
            $tenantId = (int) Session::get('tenant_id');
            if ($tenantId < 1 || !self::allowed()) {
                return null;
            }
            $n = (new IntelIntakeService())->countToTreat($tenantId);

            return $n > 0 ? ($n > 99 ? '99+' : (string) $n) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** Libellé d'un niveau, pour les vues. */
    public static function levelLabel(string $level): string
    {
        return SseRedactionService::levelLabel($level);
    }
}
