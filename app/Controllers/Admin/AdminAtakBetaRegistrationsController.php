<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\AtakBetaRegistrationRepository;
use App\Repositories\BlockedIndicatorRepository;
use App\Services\Auth\AuthService;
use App\Services\Moderation\IndicatorBlocklistService;
use App\Support\SteamId;

/**
 * Journal des accès anticipés (bêta) remontés par le pack Overwatch.
 */
final class AdminAtakBetaRegistrationsController
{
    private const REDIRECT = 'admin/atak-beta';

    public function __construct(
        private AtakBetaRegistrationRepository $betaRegistrationRepository,
        private AuthService $authService,
        private IndicatorBlocklistService $indicatorBlocklistService,
        private BlockedIndicatorRepository $blockedIndicatorRepository,
    ) {}

    public function index(Request $request, array $params = []): Response
    {
        $rows = $this->betaRegistrationRepository->listRecent(250);
        $total = $this->betaRegistrationRepository->countAll();
        $tenantId = (int) Session::get('tenant_id');
        $blockedSteamHashes = $this->activeSteamBlockHashes($tenantId);

        foreach ($rows as &$row) {
            $steam = SteamId::normalize(isset($row['steam_uid']) ? (string) $row['steam_uid'] : null);
            $row['steam_restricted'] = $steam !== null
                && isset($blockedSteamHashes[BlockedIndicatorRepository::hashSteam($steam)]);
        }
        unset($row);

        return Response::view('layout.main', [
            'title' => 'Accès anticipé Overwatch',
            'content' => 'admin.atak-beta-registrations.index',
            'rows' => $rows,
            'total' => $total,
        ]);
    }

    public function clearAcknowledgement(Request $request, array $params = []): Response
    {
        if (!$this->guardPost($request)) {
            return Response::redirect(url(self::REDIRECT));
        }

        $id = (int) $request->input('registration_id');
        $row = $this->betaRegistrationRepository->findById($id);
        if ($row === null) {
            Session::flash('error', 'Inscription introuvable.');

            return Response::redirect(url(self::REDIRECT));
        }

        if ($this->betaRegistrationRepository->clearAcknowledged($id)) {
            Session::flash(
                'success',
                'Marquage « Accepté » retiré pour cette inscription. Cela ne coupe pas l’accès au mod : utilisez une restriction Steam si besoin.'
            );
        } else {
            Session::flash('error', 'Cette inscription n’avait pas de marquage « Accepté ».');
        }

        return Response::redirect(url(self::REDIRECT));
    }

    public function restrictSteam(Request $request, array $params = []): Response
    {
        if (!$this->guardPost($request)) {
            return Response::redirect(url(self::REDIRECT));
        }

        $actor = $this->authService->user();
        if (!$actor) {
            return Response::redirect(url('login'));
        }
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            Session::flash('error', 'Communauté introuvable.');

            return Response::redirect(url(self::REDIRECT));
        }

        $id = (int) $request->input('registration_id');
        $row = $this->betaRegistrationRepository->findById($id);
        if ($row === null) {
            Session::flash('error', 'Inscription introuvable.');

            return Response::redirect(url(self::REDIRECT));
        }

        $steam = SteamId::normalize(isset($row['steam_uid']) ? (string) $row['steam_uid'] : null);
        if ($steam === null) {
            Session::flash('error', 'Aucun identifiant Steam exploitable sur cette inscription.');

            return Response::redirect(url(self::REDIRECT));
        }

        if ($this->indicatorBlocklistService->isSteamBlockedForContext($tenantId, $steam)) {
            Session::flash('error', 'Cet identifiant Steam est déjà restreint pour le mod.');

            return Response::redirect(url(self::REDIRECT));
        }

        $playerName = trim((string) ($row['player_name'] ?? ''));
        $reason = $playerName !== ''
            ? 'Accès anticipé Overwatch — ' . $playerName
            : 'Accès anticipé Overwatch — restriction depuis le journal';

        try {
            $this->indicatorBlocklistService->addSteamBlock(
                (int) $actor['id'],
                'tenant',
                $tenantId,
                $steam,
                $reason,
                null
            );
            Session::flash(
                'success',
                'Restriction Steam enregistrée. Le pack Overwatch refusera désormais cet accès pour votre communauté.'
            );
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }

        return Response::redirect(url(self::REDIRECT));
    }

    public function delete(Request $request, array $params = []): Response
    {
        if (!$this->guardPost($request)) {
            return Response::redirect(url(self::REDIRECT));
        }

        $id = (int) $request->input('registration_id');
        if ($this->betaRegistrationRepository->deleteById($id)) {
            Session::flash('success', 'Inscription retirée du journal.');
        } else {
            Session::flash('error', 'Inscription introuvable ou déjà supprimée.');
        }

        return Response::redirect(url(self::REDIRECT));
    }

    public function bulk(Request $request, array $params = []): Response
    {
        if (!$this->guardPost($request)) {
            return Response::redirect(url(self::REDIRECT));
        }

        $action = trim((string) $request->input('bulk_action'));
        $rawIds = $request->input('registration_ids');
        $ids = [];
        if (is_array($rawIds)) {
            foreach ($rawIds as $raw) {
                $id = (int) $raw;
                if ($id > 0) {
                    $ids[] = $id;
                }
            }
        }
        $ids = array_values(array_unique($ids));
        if ($ids === []) {
            Session::flash('error', 'Sélectionnez au moins une inscription.');

            return Response::redirect(url(self::REDIRECT));
        }

        return match ($action) {
            'clear_acknowledgement' => $this->bulkClearAcknowledgement($ids),
            'restrict_steam' => $this->bulkRestrictSteam($ids),
            'delete' => $this->bulkDelete($ids),
            default => $this->flashAndRedirect('error', 'Choisissez une action à appliquer.'),
        };
    }

    /**
     * @param list<int> $ids
     */
    private function bulkClearAcknowledgement(array $ids): Response
    {
        $n = $this->betaRegistrationRepository->clearAcknowledgedMany($ids);
        if ($n > 0) {
            Session::flash(
                'success',
                $n === 1
                    ? 'Marquage « Accepté » retiré pour 1 inscription.'
                    : 'Marquage « Accepté » retiré pour ' . $n . ' inscriptions.'
            );
        } else {
            Session::flash('error', 'Aucune des inscriptions sélectionnées n’avait de marquage « Accepté ».');
        }

        return Response::redirect(url(self::REDIRECT));
    }

    /**
     * @param list<int> $ids
     */
    private function bulkRestrictSteam(array $ids): Response
    {
        $actor = $this->authService->user();
        if (!$actor) {
            return Response::redirect(url('login'));
        }
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            Session::flash('error', 'Communauté introuvable.');

            return Response::redirect(url(self::REDIRECT));
        }

        $rows = $this->betaRegistrationRepository->findByIds($ids);
        $added = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($rows as $row) {
            $steam = SteamId::normalize(isset($row['steam_uid']) ? (string) $row['steam_uid'] : null);
            if ($steam === null) {
                $skipped++;
                continue;
            }
            if ($this->indicatorBlocklistService->isSteamBlockedForContext($tenantId, $steam)) {
                $skipped++;
                continue;
            }
            $playerName = trim((string) ($row['player_name'] ?? ''));
            $reason = $playerName !== ''
                ? 'Accès anticipé Overwatch — ' . $playerName
                : 'Accès anticipé Overwatch — restriction groupée';
            try {
                $this->indicatorBlocklistService->addSteamBlock(
                    (int) $actor['id'],
                    'tenant',
                    $tenantId,
                    $steam,
                    $reason,
                    null
                );
                $added++;
            } catch (\Throwable) {
                $errors++;
            }
        }

        if ($added > 0) {
            $msg = $added === 1
                ? '1 restriction Steam enregistrée.'
                : $added . ' restrictions Steam enregistrées.';
            if ($skipped > 0 || $errors > 0) {
                $msg .= ' Certaines lignes ont été ignorées (déjà restreintes ou sans Steam).';
            }
            Session::flash('success', $msg);
        } elseif ($errors > 0) {
            Session::flash('error', 'Impossible d’enregistrer les restrictions. Réessayez ou passez par la page des restrictions d’accès.');
        } else {
            Session::flash('error', 'Aucune restriction ajoutée : Steam manquant ou déjà restreint.');
        }

        return Response::redirect(url(self::REDIRECT));
    }

    /**
     * @param list<int> $ids
     */
    private function bulkDelete(array $ids): Response
    {
        $n = $this->betaRegistrationRepository->deleteByIds($ids);
        if ($n > 0) {
            Session::flash(
                'success',
                $n === 1
                    ? '1 inscription retirée du journal.'
                    : $n . ' inscriptions retirées du journal.'
            );
        } else {
            Session::flash('error', 'Aucune inscription n’a pu être supprimée.');
        }

        return Response::redirect(url(self::REDIRECT));
    }

    private function guardPost(Request $request): bool
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return false;
        }

        return true;
    }

    /**
     * @return array<string, true>
     */
    private function activeSteamBlockHashes(int $tenantId): array
    {
        if ($tenantId < 1) {
            return [];
        }
        $hashes = [];
        foreach ($this->blockedIndicatorRepository->listActiveModBlocksForTenant($tenantId, 500) as $block) {
            if (($block['indicator_type'] ?? '') !== 'steam') {
                continue;
            }
            $hash = trim((string) ($block['value_hash'] ?? ''));
            if ($hash !== '') {
                $hashes[$hash] = true;
            }
        }

        return $hashes;
    }

    private function flashAndRedirect(string $type, string $message): Response
    {
        Session::flash($type, $message);

        return Response::redirect(url(self::REDIRECT));
    }
}
