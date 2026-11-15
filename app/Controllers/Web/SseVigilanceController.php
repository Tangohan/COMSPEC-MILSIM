<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\Sse\SseAccessCodeService;
use App\Services\Sse\SseAlertService;
use App\Services\Sse\SseClearanceService;
use App\Services\Sse\SseSheetAccess;
use App\Services\Sse\SseVigilanceFollow;

/**
 * Portail SSE : suivi d'une fiche de vigilance émise, renouveler (nouvelle fiche, la précédente est levée) et lever avec motif.
 *
 * Droits : renouveler = ceux d'émettre (écrire « Fichiers et sujets », exporter « Papiers ») ; lever = gérer « Fichiers et
 * sujets » ou être l'émetteur de la fiche. Chaque écriture exige le jeton CSRF ; la communauté est toujours celle de la session
 * (une fiche d'une autre communauté est introuvable) ; chaque action est au journal d'audit (SseAlertService). Le refus dit quel
 * droit manque.
 */
final class SseVigilanceController
{
    public function __construct(
        private ?SseAccessCodeService $access = null,
        private ?SseClearanceService $clearance = null,
        private ?SseAlertService $alerts = null,
        private ?SseSheetAccess $rights = null,
    ) {
        $this->access ??= new SseAccessCodeService();
        $this->clearance ??= new SseClearanceService($this->access);
        $this->alerts ??= new SseAlertService();
    }

    /** Renouvelle une fiche en cours ou expirée : nouvelle référence et échéance, mêmes niveau, consignes et service. */
    public function renew(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $r = $this->r();
        $back = $this->back($request, $id);
        if (!Csrf::verify($request)) {
            return $this->flash('error', 'Votre page est restée ouverte trop longtemps ou la session a changé : rechargez la page puis recommencez.', $back);
        }
        $denial = $r->denial('vigilance');
        if ($denial !== null) {
            return $this->flash('error', 'Renouvellement impossible. ' . $denial, $back);
        }
        $days = (int) $request->inputString('days', '0');
        $days = in_array($days, SseVigilanceFollow::SHORTCUT_DAYS, true) ? $days : 0;
        $res = $this->alerts->renewVigilance($r->tenantId(), $id, $days, $r);
        if (!$res['ok']) {
            return $this->flash('error', $res['errors'] !== [] ? implode(' ', $res['errors']) : 'La fiche de vigilance n’a pas pu être renouvelée.', $back);
        }

        return $this->flash('success', 'Fiche de vigilance ' . $res['ref'] . ' émise jusqu’au ' . SseVigilanceFollow::dateLabel($res['until']) . '. La précédente est levée.', $back);
    }

    /** Lève une fiche en cours : motif obligatoire. */
    public function lift(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $r = $this->r();
        $back = $this->back($request, $id);
        if (!Csrf::verify($request)) {
            return $this->flash('error', 'Votre page est restée ouverte trop longtemps ou la session a changé : rechargez la page puis recommencez.', $back);
        }
        $res = $this->alerts->liftVigilance($r->tenantId(), $id, $request->inputString('reason'), $r);
        if (!$res['ok']) {
            return $this->flash('error', $res['errors'] !== [] ? implode(' ', $res['errors']) : 'La fiche de vigilance n’a pas pu être levée.', $back);
        }

        return $this->flash('success', 'Fiche de vigilance ' . (string) ($res['view']['ref'] ?? '') . ' levée.', $back);
    }

    /** Retour à la fiche d'où vient l'action : la personne, sinon l'identité du registre ; la fiche d'une autre communauté n'est jamais lue. */
    private function back(Request $request, int $id): string
    {
        $view = null;
        try {
            $view = $id > 0 ? $this->alerts->findVigilance($this->r()->tenantId(), $id) : null;
        } catch (\Throwable) {
            $view = null;
        }
        if ($view === null) {
            return url('atak/sse/identites');
        }
        $from = $request->inputString('from');
        $person = $view['person_id'] !== null ? url('atak/sse/identites/' . (int) $view['person_id'] . '?fiche=personne') : null;
        $identity = $view['identity_id'] !== null ? url('atak/sse/papiers/identites/' . (int) $view['identity_id']) : null;
        $target = $from === 'identite' ? ($identity ?? $person) : ($person ?? $identity);

        return ($target ?? url('atak/sse/identites')) . '#documents-emis';
    }

    private function r(): SseSheetAccess
    {
        return $this->rights ??= SseSheetAccess::fromSession($this->access, $this->clearance);
    }

    private function flash(string $type, string $message, string $to): Response
    {
        Session::flash($type, $message);

        return Response::redirect($to);
    }
}
