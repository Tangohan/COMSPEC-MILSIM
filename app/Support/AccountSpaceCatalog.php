<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Plan de l'espace « Mon compte » : pages de la navigation latérale et réglages trouvables par la recherche.
 *
 * Une seule source pour le menu du compte (views/partials/account/shell_open.php) et pour l'index de recherche
 * (views/partials/account/search.php). Chaque entrée pointe vers une page existante, et vers l'ancre exacte du
 * réglage quand il en a une. Les mots-clés mêlent français et anglais : un membre qui tape « password »,
 * « dark mode » ou « supprimer » doit tomber au bon endroit, quelle que soit la langue du site.
 *
 * Le test AccountSpaceCatalogTest vérifie que chaque adresse est une route et que chaque ancre existe dans la vue.
 */
final class AccountSpaceCatalog
{
    /**
     * Pages de l'espace compte, dans l'ordre du menu. 'nav' => false : trouvable par la recherche, hors menu.
     *
     * @return list<array{key: string, group: string, path: string, label: string, hint: string, icon: string, view: string, nav: bool, keywords: list<string>}>
     */
    public static function pages(): array
    {
        return [
            [
                'key' => 'overview', 'group' => 'Compte', 'path' => 'account', 'view' => 'views/account/index.php', 'nav' => true,
                'label' => 'Vue d’ensemble', 'hint' => 'État du compte, activité',
                'icon' => '<path d="M3 12l9-8 9 8"/><path d="M5 10v10h14V10"/>',
                'keywords' => ['accueil', 'résumé', 'tableau', 'overview', 'home', 'summary', 'account'],
            ],
            [
                'key' => 'welcome', 'group' => 'Compte', 'path' => 'account/bienvenue', 'view' => 'views/account/welcome.php', 'nav' => false,
                'label' => 'Compléter mon profil', 'hint' => 'Indicatif, Discord, portrait, bannière, sécurité',
                'icon' => '<path d="M4 12l5 5L20 6"/>',
                'keywords' => ['bienvenue', 'premiers pas', 'compléter', 'démarrer', 'welcome', 'getting started', 'onboarding', 'setup'],
            ],
            [
                'key' => 'preferences', 'group' => 'Compte', 'path' => 'account/preferences', 'view' => 'views/account/preferences.php', 'nav' => true,
                'label' => 'Profil et préférences', 'hint' => 'Nom, langue, thème, e-mails',
                'icon' => '<circle cx="12" cy="8" r="4"/><path d="M4 21c1.5-4 4.5-6 8-6s6.5 2 8 6"/>',
                'keywords' => ['réglages', 'paramètres', 'profil', 'settings', 'preferences', 'profile'],
            ],
            [
                'key' => 'mail', 'group' => 'Sécurité', 'path' => 'account/mail', 'view' => 'views/account/mail.php', 'nav' => true,
                'label' => 'Adresse e-mail', 'hint' => 'Adresse de connexion',
                'icon' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/>',
                'keywords' => ['courriel', 'mail', 'email', 'identifiant', 'login', 'address'],
            ],
            [
                'key' => 'password', 'group' => 'Sécurité', 'path' => 'account/password', 'view' => 'views/account/password.php', 'nav' => true,
                'label' => 'Mot de passe', 'hint' => 'Changer le secret d’accès',
                'icon' => '<rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 018 0v3"/>',
                'keywords' => ['mdp', 'secret', 'changer', 'password', 'change password', 'passphrase'],
            ],
            [
                'key' => 'security', 'group' => 'Sécurité', 'path' => 'account/security', 'view' => 'views/account/security.php', 'nav' => true,
                'label' => 'Double vérification', 'hint' => 'Code par e-mail ou application',
                'icon' => '<path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z"/><path d="M9 12l2 2 4-4"/>',
                'keywords' => ['2fa', 'a2f', 'mfa', 'otp', 'sécurité', 'two-factor', 'security', 'authentication'],
            ],
            [
                'key' => 'devices', 'group' => 'Sécurité', 'path' => 'account/security/devices', 'view' => 'views/account/devices.php', 'nav' => true,
                'label' => 'Appareils ATAK', 'hint' => 'Téléphones COMSPEC liés',
                'icon' => '<rect x="7" y="2" width="10" height="20" rx="2"/><path d="M11 18h2"/>',
                'keywords' => ['téléphone', 'mobile', 'terminal', 'atak', 'comspec', 'phone', 'device', 'revoke', 'retirer'],
            ],
            [
                'key' => 'portrait', 'group' => 'Apparence', 'path' => 'account/portrait', 'view' => 'views/account/portrait.php', 'nav' => true,
                'label' => 'Portrait', 'hint' => 'Votre photo d’opérateur',
                'icon' => '<rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="12" cy="10" r="3"/><path d="M6 19c1.2-2.4 3.4-3.5 6-3.5s4.8 1.1 6 3.5"/>',
                'keywords' => ['photo', 'avatar', 'image', 'visage', 'picture', 'portrait'],
            ],
            [
                'key' => 'banner', 'group' => 'Apparence', 'path' => 'account/banner', 'view' => 'views/account/banner.php', 'nav' => true,
                'label' => 'Couverture du menu', 'hint' => 'Bandeau du menu session',
                'icon' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 15l5-4 4 3 3-2 6 4"/>',
                'keywords' => ['bandeau', 'bannière', 'couverture', 'fond', 'banner', 'cover', 'background'],
            ],
            [
                'key' => 'access', 'group' => 'Communauté', 'path' => 'account/acces', 'view' => 'views/account/access.php', 'nav' => true,
                'label' => 'Mes accès', 'hint' => 'Rôle et départ de la communauté',
                'icon' => '<circle cx="8" cy="15" r="4"/><path d="M11 12l9-9M17 6l3 3"/>',
                'keywords' => ['rôle', 'grade', 'droits', 'permissions', 'role', 'rank', 'access'],
            ],
            [
                'key' => 'public_cv', 'group' => 'Communauté', 'path' => 'account/page-publique', 'view' => 'views/account/public_cv.php', 'nav' => true,
                'label' => 'Ma page publique', 'hint' => 'C.V. de joueur à partager',
                'icon' => '<rect x="3" y="3" width="18" height="18" rx="4"/><circle cx="12" cy="10" r="3"/><path d="M6.5 18c1.2-2.4 3.2-3.5 5.5-3.5s4.3 1.1 5.5 3.5"/>',
                'keywords' => ['cv', 'c.v.', 'partager', 'lien', 'public page', 'resume', 'share'],
            ],
            [
                'key' => 'charter', 'group' => 'Communauté', 'path' => 'account/charte-formations', 'view' => 'views/rh/charter.php', 'nav' => false,
                'label' => 'Charte des formations', 'hint' => 'Règles des formations à lire et accepter',
                'icon' => '<path d="M6 3h9l3 3v15H6z"/><path d="M9 10h6M9 14h6"/>',
                'keywords' => ['charte', 'règlement', 'formation', 'accepter', 'charter', 'training', 'rules'],
            ],
            [
                'key' => 'donnees', 'group' => 'Confidentialité', 'path' => 'account/donnees', 'view' => 'views/account/privacy.php', 'nav' => true,
                'label' => 'Mes données', 'hint' => 'Export et suppression du compte',
                'icon' => '<path d="M4 16v3a2 2 0 002 2h12a2 2 0 002-2v-3"/><path d="M12 3v12M7 10l5 5 5-5"/>',
                'keywords' => ['rgpd', 'gdpr', 'vie privée', 'confidentialité', 'privacy', 'data', 'personal data'],
            ],
        ];
    }

    /**
     * Réglages et sections précis, rattachés à une page. 'anchor' : identifiant présent dans la vue de la page.
     *
     * @return list<array{page: string, anchor: string, title: string, desc: string, keywords: list<string>}>
     */
    public static function sections(): array
    {
        return [
            // Vue d'ensemble
            ['page' => 'overview', 'anchor' => 'etat-du-compte', 'title' => 'État du compte', 'desc' => 'E-mail confirmé, double vérification, Steam et portrait en un coup d’œil.', 'keywords' => ['protection', 'santé', 'checklist', 'status', 'health']],
            ['page' => 'overview', 'anchor' => 'activite', 'title' => 'Activité récente', 'desc' => 'Connexions et changements de sécurité de ce compte.', 'keywords' => ['journal', 'historique', 'connexions', 'log', 'history', 'activity', 'ip']],
            ['page' => 'overview', 'anchor' => 'sessions', 'title' => 'Déconnecter mes autres sessions', 'desc' => 'Fermer le compte sur tous les autres navigateurs.', 'keywords' => ['session', 'déconnexion', 'navigateur', 'perdu', 'volé', 'logout', 'sign out', 'other sessions']],

            // Profil et préférences
            ['page' => 'preferences', 'anchor' => 'section-profil', 'title' => 'Prénom et nom', 'desc' => 'Votre identité, reprise sur le dossier et l’annuaire.', 'keywords' => ['nom', 'prénom', 'identité', 'first name', 'last name', 'name', 'identity']],
            ['page' => 'preferences', 'anchor' => 'public_name', 'title' => 'Pseudo public', 'desc' => 'Nom affiché sur les pages publiques du site.', 'keywords' => ['pseudo', 'surnom', 'nickname', 'public name', 'display name']],
            ['page' => 'preferences', 'anchor' => 'callsign', 'title' => 'Indicatif principal', 'desc' => 'Utilisé sur le portail et les outils cartographiques.', 'keywords' => ['indicatif', 'callsign', 'radio', 'call sign']],
            ['page' => 'preferences', 'anchor' => 'indicatifs-supplementaires', 'title' => 'Indicatifs supplémentaires', 'desc' => 'Alias radio et variantes d’unité.', 'keywords' => ['alias', 'secondaire', 'callsigns', 'aliases']],
            ['page' => 'preferences', 'anchor' => 'profile_slug', 'title' => 'Adresse courte de votre fiche', 'desc' => 'Le nom court qui apparaît dans le lien de votre fiche.', 'keywords' => ['lien', 'adresse', 'raccourci', 'short link', 'profile link', 'url']],
            ['page' => 'preferences', 'anchor' => 'section-liaisons', 'title' => 'Liaison Steam', 'desc' => 'Être reconnu en jeu et par COMSPEC ATAK.', 'keywords' => ['steam', 'arma', 'jeu', 'steamid', 'game', 'link']],
            ['page' => 'preferences', 'anchor' => 'section-discord', 'title' => 'Rôles liés Discord', 'desc' => 'Relier votre compte Discord au serveur d’Athena.', 'keywords' => ['discord', 'rôles liés', 'linked roles', 'serveur']],
            ['page' => 'preferences', 'anchor' => 'language', 'title' => 'Langue', 'desc' => 'Langue des pages et des e-mails.', 'keywords' => ['langue', 'français', 'anglais', 'language', 'english', 'french', 'locale']],
            ['page' => 'preferences', 'anchor' => 'timezone', 'title' => 'Fuseau horaire', 'desc' => 'Heures des événements et de l’activité.', 'keywords' => ['fuseau', 'heure', 'horaire', 'timezone', 'time zone', 'utc']],
            ['page' => 'preferences', 'anchor' => 'section-interface', 'title' => 'Thème clair ou sombre', 'desc' => 'Mode nuit de l’espace compte et du back-office.', 'keywords' => ['thème', 'sombre', 'nuit', 'clair', 'dark mode', 'light mode', 'theme', 'night']],
            ['page' => 'preferences', 'anchor' => 'ui_density', 'title' => 'Densité des listes', 'desc' => 'Listes confortables ou compactes dans ATAK web.', 'keywords' => ['densité', 'compact', 'confortable', 'density', 'spacing']],
            ['page' => 'preferences', 'anchor' => 'notifications-email', 'title' => 'Notifications par e-mail', 'desc' => 'Choisir les messages que vous recevez.', 'keywords' => ['notifications', 'courriels', 'alertes', 'newsletter', 'emails', 'unsubscribe', 'désabonner']],

            // Adresse e-mail
            ['page' => 'mail', 'anchor' => 'mail-change-heading', 'title' => 'Modifier l’adresse e-mail', 'desc' => 'Changer l’adresse qui sert à se connecter.', 'keywords' => ['changer', 'nouvelle adresse', 'change email', 'update email']],

            // Mot de passe
            ['page' => 'password', 'anchor' => 'current_password', 'title' => 'Changer le mot de passe', 'desc' => 'Ferme aussi vos autres sessions.', 'keywords' => ['modifier', 'nouveau', 'reset', 'change password', 'new password']],

            // Double vérification
            ['page' => 'security', 'anchor' => 'email-otp', 'title' => 'Code par e-mail', 'desc' => 'Recevoir un code à chaque connexion.', 'keywords' => ['code', 'e-mail', 'otp', 'email code', 'one-time']],
            ['page' => 'security', 'anchor' => 'authenticator', 'title' => 'Application d’authentification', 'desc' => 'Google Authenticator, Authy, 2FAS…', 'keywords' => ['totp', 'google authenticator', 'authy', 'application', 'authenticator app', 'qr']],
            ['page' => 'security', 'anchor' => 'codes-secours', 'title' => 'Codes de secours', 'desc' => 'Se connecter sans téléphone ni e-mail.', 'keywords' => ['secours', 'récupération', 'backup codes', 'recovery codes']],
            ['page' => 'security', 'anchor' => 'appareils-confiance', 'title' => 'Appareils de confiance', 'desc' => 'Navigateurs dispensés du code pendant un temps.', 'keywords' => ['confiance', 'navigateur', 'mémoriser', 'trusted devices', 'remember']],

            // Apparence
            ['page' => 'portrait', 'anchor' => 'portrait', 'title' => 'Changer de portrait', 'desc' => 'La photo affichée sur le portail et la fiche.', 'keywords' => ['photo', 'upload', 'changer', 'change photo']],
            ['page' => 'banner', 'anchor' => 'banner', 'title' => 'Changer la couverture', 'desc' => 'L’image en haut du menu de session.', 'keywords' => ['image', 'fond', 'upload', 'change banner']],

            // Communauté
            ['page' => 'access', 'anchor' => 'access-role-heading', 'title' => 'Rôle et grade', 'desc' => 'Votre rôle et votre grade dans la communauté.', 'keywords' => ['rôle', 'grade', 'role', 'rank']],
            ['page' => 'access', 'anchor' => 'access-elevation-heading', 'title' => 'Demandes d’accès', 'desc' => 'Suivre les demandes de droits supplémentaires.', 'keywords' => ['demande', 'élévation', 'droits', 'promotion', 'request', 'elevation']],
            ['page' => 'access', 'anchor' => 'quitter', 'title' => 'Quitter la communauté', 'desc' => 'Mettre fin à votre accès à la communauté.', 'keywords' => ['quitter', 'partir', 'départ', 'leave', 'leave community', 'exit']],
            ['page' => 'public_cv', 'anchor' => 'pcv-open-heading', 'title' => 'Ouvrir ma page publique', 'desc' => 'Activer le lien et choisir son adresse.', 'keywords' => ['activer', 'publier', 'lien', 'enable', 'publish']],
            ['page' => 'public_cv', 'anchor' => 'pcv-sections-heading', 'title' => 'Ce que la page montre', 'desc' => 'Choisir les rubriques visibles.', 'keywords' => ['rubriques', 'sections', 'visibilité', 'visibility', 'show']],

            // Confidentialité
            ['page' => 'donnees', 'anchor' => 'privacy-export-heading', 'title' => 'Exporter mes données', 'desc' => 'Télécharger une copie de vos informations.', 'keywords' => ['export', 'télécharger', 'copie', 'download', 'portabilité', 'export data']],
            ['page' => 'donnees', 'anchor' => 'privacy-delete-heading', 'title' => 'Supprimer mon compte', 'desc' => 'Programmer la suppression définitive.', 'keywords' => ['supprimer', 'effacer', 'fermer', 'delete', 'delete account', 'close account', 'remove']],
            ['page' => 'donnees', 'anchor' => 'privacy-more-heading', 'title' => 'Rectification, effacement, opposition', 'desc' => 'Vos autres droits sur vos données.', 'keywords' => ['droits', 'rectifier', 'opposition', 'rights', 'rectification']],
        ];
    }

    /**
     * Menu latéral, groupé.
     *
     * @return list<array{title: string, items: list<array{key: string, href: string, label: string, hint: string, icon: string}>}>
     */
    public static function navGroups(): array
    {
        $groups = [];
        foreach (self::pages() as $page) {
            if (!$page['nav']) {
                continue;
            }
            $groups[$page['group']][] = [
                'key' => $page['key'],
                'href' => url($page['path']),
                'label' => $page['label'],
                'hint' => $page['hint'],
                'icon' => $page['icon'],
            ];
        }
        $out = [];
        foreach ($groups as $title => $items) {
            $out[] = ['title' => $title, 'items' => $items];
        }

        return $out;
    }

    /**
     * Index de recherche : chaque page puis ses réglages.
     *
     * @param list<string> $hiddenAnchors pages (clé) ou ancres absentes pour ce compte (ex. Discord non configuré)
     * @return list<array{id: string, kind: string, page: string, group: string, title: string, desc: string, href: string, icon: string, keywords: string}>
     */
    public static function searchEntries(array $hiddenAnchors = []): array
    {
        $pages = [];
        foreach (self::pages() as $page) {
            $pages[$page['key']] = $page;
        }
        $out = [];
        foreach ($pages as $page) {
            if (in_array($page['key'], $hiddenAnchors, true)) {
                continue;
            }
            $out[] = [
                'id' => 'page-' . $page['key'],
                'kind' => 'page',
                'page' => $page['label'],
                'group' => $page['group'],
                'title' => $page['label'],
                'desc' => $page['hint'],
                'href' => url($page['path']),
                'icon' => $page['icon'],
                'keywords' => implode(' ', $page['keywords']),
            ];
            foreach (self::sections() as $section) {
                if ($section['page'] !== $page['key'] || in_array($section['anchor'], $hiddenAnchors, true)) {
                    continue;
                }
                $out[] = [
                    'id' => 'section-' . $page['key'] . '-' . $section['anchor'],
                    'kind' => 'section',
                    'page' => $page['label'],
                    'group' => $page['group'],
                    'title' => $section['title'],
                    'desc' => $section['desc'],
                    'href' => url($page['path']) . '#' . $section['anchor'],
                    'icon' => $page['icon'],
                    'keywords' => implode(' ', array_merge($section['keywords'], $page['keywords'])),
                ];
            }
        }

        return $out;
    }
}
