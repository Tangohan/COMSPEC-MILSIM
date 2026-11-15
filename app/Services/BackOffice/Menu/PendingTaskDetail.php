<?php

declare(strict_types=1);

namespace App\Services\BackOffice\Menu;

/**
 * Explication d'une tâche du bloc « Tâches en attente » du tableau de bord, écrite pour quelqu'un qui ne connaît pas le
 * vocabulaire interne : ce qui se passe, ce qui arrive si on ne fait rien, quoi faire, par où commencer, et qui peut le
 * faire.
 *
 * $localized vaut true quand les textes sont déjà dans la langue de la personne (fournisseur qui les compose lui-même,
 * comme la formule) : la vue les protège alors de la traduction automatique de la page. Sinon ce sont des textes
 * français de config/back_office_menu.php, traduits à l'affichage par lang/en/site_auto.php.
 */
final class PendingTaskDetail
{
    public function __construct(
        /** Titre à la place de celui de la pastille (« Votre formule « Pro » se termine le … »), '' pour garder le sien. */
        public readonly string $title,
        /** Quoi, pour qui, quand. */
        public readonly string $explain,
        /** Ce qui arrive si rien n'est fait. */
        public readonly string $consequence,
        /** Ce qu'il faut faire, en une phrase. */
        public readonly string $action,
        /** Intitulé du bouton, un verbe d'action. */
        public readonly string $button,
        /** Page du bouton, relative à la racine du site (avec son éventuelle requête). */
        public readonly string $path,
        /** Qui peut le faire, quand ce n'est pas forcément la personne qui lit. '' si inutile. */
        public readonly string $who = '',
        /** Aide dépliable « Qu'est-ce que c'est ? ». '' si la tâche se passe d'explication. */
        public readonly string $help = '',
        public readonly bool $localized = false,
        /** Code de langue des textes composés (« fr », « en »), utilisé seulement si $localized. */
        public readonly string $lang = 'fr',
    ) {
    }
}
