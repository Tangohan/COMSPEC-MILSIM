<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\CooperationAnnouncementTemplateRepository;
use App\Services\Cooperation\CooperationAnnouncementEvents;
use App\Services\Cooperation\CooperationAnnouncementRenderer;

/**
 * Surcharge communautaire des gabarits d’annonces coopération.
 */
final class CooperationAnnouncementsWebController
{
    public function __construct(
        private CooperationAnnouncementTemplateRepository $templates
    ) {}

    public function index(Request $request, array $params = []): Response
    {
        if (! $this->assertAccess()) {
            return Response::redirect(url('dashboard'));
        }
        $tid = (int) Session::get('tenant_id');
        $featureGate = \App\Core\Container::get(\App\Services\Platform\FeatureGateService::class);
        if (!$featureGate->allows($tid, 'cooperation')) {
            return \App\Support\PlanFeatureDenial::upgradeView('cooperation', 'Pro');
        }
        if (! $this->templates->tableExists()) {
            Session::flash('error', 'Fonction indisponible sur cette installation.');

            return Response::redirect(url('back-office/cooperation/missions'));
        }
        $matrix = [];
        foreach (CooperationAnnouncementEvents::allKeys() as $ek) {
            foreach (array_keys(CooperationAnnouncementEvents::channelLabels()) as $ch) {
                $resolved = $this->templates->findResolved($tid, $ek, $ch);
                $matrix[] = [
                    'event_key' => $ek,
                    'channel' => $ch,
                    'has_local' => $this->templates->findExact($tid, $ek, $ch) !== null,
                    'is_live' => $resolved !== null,
                ];
            }
        }

        return Response::view('layout.main', [
            'content' => 'back_office.cooperation.announcements_index',
            'title' => 'Messages types — annonces coopération',
            'cooperationAnnouncementMatrix' => $matrix,
            'cooperationAnnouncementEventLabels' => CooperationAnnouncementEvents::labels(),
            'cooperationAnnouncementChannelLabels' => CooperationAnnouncementEvents::channelLabels(),
            'csrfToken' => Csrf::token(),
        ]);
    }

    public function edit(Request $request, array $params = []): Response
    {
        if (! $this->assertAccess()) {
            return Response::redirect(url('dashboard'));
        }
        $tid = (int) Session::get('tenant_id');
        $eventKey = trim((string) $request->input('event', ''));
        $channel = trim((string) $request->input('channel', ''));
        if (! CooperationAnnouncementEvents::isKnown($eventKey) || ! isset(CooperationAnnouncementEvents::channelLabels()[$channel])) {
            Session::flash('error', 'Sélection invalide.');

            return Response::redirect(url('back-office/cooperation/announcements'));
        }
        $row = $this->templates->findForForm($tid, $eventKey, $channel);
        $hasLocal = $this->templates->findExact($tid, $eventKey, $channel) !== null;

        return Response::view('layout.main', [
            'content' => 'back_office.cooperation.announcements_form',
            'title' => 'Personnaliser le message — ' . (CooperationAnnouncementEvents::labels()[$eventKey] ?? ''),
            'cooperationTplRow' => $row,
            'cooperationTplEventKey' => $eventKey,
            'cooperationTplChannel' => $channel,
            'cooperationTplScopeTenant' => $tid,
            'cooperationTplHasLocal' => $hasLocal,
            'cooperationPlaceholderLabels' => CooperationAnnouncementRenderer::placeholderLabelsFr(),
            'csrfToken' => Csrf::token(),
        ]);
    }

    public function save(Request $request, array $params = []): Response
    {
        if (! $this->assertAccess()) {
            return Response::redirect(url('dashboard'));
        }
        if (! Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Jeton de sécurité invalide.');

            return Response::redirect(url('back-office/cooperation/announcements'));
        }
        $tid = (int) Session::get('tenant_id');
        $eventKey = trim((string) $request->input('event_key', ''));
        $channel = trim((string) $request->input('channel', ''));
        if (! CooperationAnnouncementEvents::isKnown($eventKey) || ! isset(CooperationAnnouncementEvents::channelLabels()[$channel])) {
            Session::flash('error', 'Enregistrement refusé.');

            return Response::redirect(url('back-office/cooperation/announcements'));
        }
        $body = trim((string) $request->input('body', ''));
        $subject = trim((string) $request->input('subject', ''));
        $wantActive = (bool) $request->input('is_active');
        if ($wantActive && $body === '') {
            Session::flash('error', 'Pour activer ce gabarit, renseignez le corps du message.');

            return Response::redirect(url('back-office/cooperation/announcements/edit?event=' . rawurlencode($eventKey) . '&channel=' . rawurlencode($channel)));
        }
        if ($channel === 'email' && $subject === '') {
            Session::flash('error', 'Pour le courriel, indiquez l’objet du message.');

            return Response::redirect(url('back-office/cooperation/announcements/edit?event=' . rawurlencode($eventKey) . '&channel=' . rawurlencode($channel)));
        }
        $forumJson = null;
        if ($channel === 'forum') {
            $forumJson = json_encode([
                'topic_id' => max(0, (int) $request->input('forum_topic_id', 0)),
                'as_draft' => (bool) $request->input('forum_as_draft'),
            ], JSON_UNESCAPED_UNICODE);
        }
        $this->templates->upsert($tid, $eventKey, $channel, [
            'subject' => $channel === 'email' ? $subject : ($subject !== '' ? $subject : null),
            'body' => $body,
            'forum_settings_json' => $forumJson,
            'min_interval_hours' => max(0, min(168, (int) $request->input('min_interval_hours', 24))),
            'is_active' => $request->input('is_active') ? 1 : 0,
        ]);
        Session::flash('success', 'Vos réglages ont été enregistrés pour cette communauté.');

        return Response::redirect(url('back-office/cooperation/announcements'));
    }

    public function revert(Request $request, array $params = []): Response
    {
        if (! $this->assertAccess()) {
            return Response::redirect(url('dashboard'));
        }
        if (! Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Jeton de sécurité invalide.');

            return Response::redirect(url('back-office/cooperation/announcements'));
        }
        $tid = (int) Session::get('tenant_id');
        $eventKey = trim((string) $request->input('event_key', ''));
        $channel = trim((string) $request->input('channel', ''));
        if (! CooperationAnnouncementEvents::isKnown($eventKey) || ! isset(CooperationAnnouncementEvents::channelLabels()[$channel])) {
            return Response::redirect(url('back-office/cooperation/announcements'));
        }
        $this->templates->delete($tid, $eventKey, $channel);
        Session::flash('success', 'La personnalisation locale a été retirée : les valeurs par défaut du site s’appliquent à nouveau.');

        return Response::redirect(url('back-office/cooperation/announcements'));
    }

    private function assertAccess(): bool
    {
        if (! Session::get('user_id')) {
            Session::flash('error', 'Connectez-vous pour continuer.');

            return false;
        }
        if (! function_exists('can') || ! can('cooperation.announcements.manage')) {
            Session::flash('error', 'Action réservée aux personnes habilitées à gérer les annonces coopération.');

            return false;
        }

        return true;
    }
}
