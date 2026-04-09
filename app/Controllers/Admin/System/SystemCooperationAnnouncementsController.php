<?php

declare(strict_types=1);

namespace App\Controllers\Admin\System;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\CooperationAnnouncementTemplateRepository;
use App\Services\Cooperation\CooperationAnnouncementEvents;
use App\Services\Cooperation\CooperationAnnouncementRenderer;

/**
 * Gabarits d’annonces coopération par défaut (tenant_id = 0).
 */
final class SystemCooperationAnnouncementsController
{
    public function __construct(
        private CooperationAnnouncementTemplateRepository $templates
    ) {}

    public function index(Request $request, array $params = []): Response
    {
        if (! $this->templates->tableExists()) {
            Session::flash('error', 'Les tables des gabarits ne sont pas encore installées.');

            return Response::redirect(url('admin'));
        }

        return Response::view('layout.main', [
            'content' => 'admin.system.cooperation_announcements_index',
            'title' => 'Messages types — coopération inter-unités (défauts site)',
            'cooperationAnnouncementRows' => $this->templates->listForTenantScope(0),
            'cooperationAnnouncementEventLabels' => CooperationAnnouncementEvents::labels(),
            'cooperationAnnouncementChannelLabels' => CooperationAnnouncementEvents::channelLabels(),
        ]);
    }

    public function edit(Request $request, array $params = []): Response
    {
        if (! $this->templates->tableExists()) {
            return Response::redirect(url('admin'));
        }
        $eventKey = trim((string) $request->input('event', (string) ($params['event'] ?? '')));
        $channel = trim((string) $request->input('channel', (string) ($params['channel'] ?? '')));
        if (! CooperationAnnouncementEvents::isKnown($eventKey) || ! isset(CooperationAnnouncementEvents::channelLabels()[$channel])) {
            Session::flash('error', 'Sélection de gabarit invalide.');

            return Response::redirect(url('admin/system/cooperation/announcements'));
        }
        $row = $this->templates->findForForm(0, $eventKey, $channel);

        return Response::view('layout.main', [
            'content' => 'admin.system.cooperation_announcements_form',
            'title' => 'Gabarit — ' . (CooperationAnnouncementEvents::labels()[$eventKey] ?? '') . ' (' . (CooperationAnnouncementEvents::channelLabels()[$channel] ?? $channel) . ')',
            'cooperationTplRow' => $row,
            'cooperationTplEventKey' => $eventKey,
            'cooperationTplChannel' => $channel,
            'cooperationPlaceholderLabels' => CooperationAnnouncementRenderer::placeholderLabelsFr(),
        ]);
    }

    public function save(Request $request, array $params = []): Response
    {
        if (! Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('admin/system/cooperation/announcements'));
        }
        $eventKey = trim((string) $request->input('event_key', ''));
        $channel = trim((string) $request->input('channel', ''));
        if (! CooperationAnnouncementEvents::isKnown($eventKey) || ! isset(CooperationAnnouncementEvents::channelLabels()[$channel])) {
            Session::flash('error', 'Enregistrement refusé : paramètres invalides.');

            return Response::redirect(url('admin/system/cooperation/announcements'));
        }
        $body = trim((string) $request->input('body', ''));
        $subject = trim((string) $request->input('subject', ''));
        $wantActive = (bool) $request->input('is_active');
        if ($wantActive && $body === '') {
            Session::flash('error', 'Pour activer un gabarit, renseignez le corps du message.');

            return Response::redirect(url('admin/system/cooperation/announcements/edit?event=' . rawurlencode($eventKey) . '&channel=' . rawurlencode($channel)));
        }
        if ($channel === 'email' && $subject === '') {
            Session::flash('error', 'Pour le courriel, renseignez au minimum l’objet du message.');

            return Response::redirect(url('admin/system/cooperation/announcements/edit?event=' . rawurlencode($eventKey) . '&channel=' . rawurlencode($channel)));
        }
        $forumJson = null;
        if ($channel === 'forum') {
            $forumJson = json_encode([
                'topic_id' => max(0, (int) $request->input('forum_topic_id', 0)),
                'as_draft' => (bool) $request->input('forum_as_draft'),
            ], JSON_UNESCAPED_UNICODE);
        }
        $this->templates->upsert(0, $eventKey, $channel, [
            'subject' => $channel === 'email' ? $subject : ($subject !== '' ? $subject : null),
            'body' => $body,
            'forum_settings_json' => $forumJson,
            'min_interval_hours' => max(0, min(168, (int) $request->input('min_interval_hours', 24))),
            'is_active' => $request->input('is_active') ? 1 : 0,
        ]);
        Session::flash('success', 'Gabarit enregistré.');

        return Response::redirect(url('admin/system/cooperation/announcements'));
    }
}
