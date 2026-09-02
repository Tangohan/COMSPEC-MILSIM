<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

/**
 * Sélecteur de publication (« Publier ») : point d’entrée unique depuis le dashboard qui
 * redirige vers le bon flux existant (annonce, forum, ordre tactique, événement) selon
 * les droits de l’utilisateur — pas de fusion de données, chaque flux garde son propre
 * contrôleur/table.
 */
final class PublicationLauncherController
{
    public function index(Request $request, array $params = []): Response
    {
        $tenantId = (int) (Session::get('tenant_id') ?? 0);
        if ($tenantId < 1) {
            return Response::redirect(url('login'));
        }

        $canAlerts = can('admin.organization') || can('admin.access') || can('site.support');
        $canForumTopic = can('forum.create_topic');
        $canBriefing = can('admin.system') || can('admin.organization') || can('admin.access');
        $canEvents = can('admin.organization') || can('admin.access') || can('site.support');

        $options = [
            [
                'key' => 'alert',
                'label' => 'Annonce (dashboard)',
                'description' => 'Message affiché aux membres sur le tableau de bord, avec bandeau ou popup selon l’importance.',
                'href' => url('back-office/alerts/create'),
                'enabled' => $canAlerts,
            ],
            [
                'key' => 'forum',
                'label' => 'Sujet de forum',
                'description' => 'Ouvrir un nouveau sujet dans une catégorie du forum de la communauté.',
                'href' => url('forum/new-topic'),
                'enabled' => $canForumTopic,
            ],
            [
                'key' => 'briefing',
                'label' => 'Ordre tactique (ATAK)',
                'description' => 'Diapositive de briefing consultable en jeu et sur l’application ATAK.',
                'href' => url('back-office/atak/briefing-slides'),
                'enabled' => $canBriefing,
            ],
            [
                'key' => 'event',
                'label' => 'Événement communauté',
                'description' => 'Créneau ou événement avec inscriptions, RSVP et présence.',
                'href' => url('back-office/events'),
                'enabled' => $canEvents,
            ],
        ];

        return Response::view('layout.main', [
            'title' => 'Publier',
            'content' => 'publish.launcher',
            'publishOptions' => $options,
        ]);
    }
}
