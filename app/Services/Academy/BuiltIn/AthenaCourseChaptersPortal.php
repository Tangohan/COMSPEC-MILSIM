<?php

declare(strict_types=1);

namespace App\Services\Academy\BuiltIn;

use App\Services\Academy\BuiltIn\AthenaPlatformCourse as C;

/**
 * Formation ATHENA : chapitres côté membre (prise en main du portail, liaison du mod ATAK).
 * Adresses entre accents graves : vérifiées par tests/Unit/AcademyAthenaCourseTest.php.
 */
final class AthenaCourseChaptersPortal
{
    /** @return array<string, mixed> */
    public static function gettingStarted(): array
    {
        $theoryFr = <<<'FR'
## Bienvenue dans ATHENA
ATHENA est le portail de votre communauté : effectifs et ORBAT, formations, manœuvres, documents, recrutement et carte ATAK partagée. Cette formation suit **le site tel qu’il est** : chaque écran est cité avec son chemin de menu et son adresse. Les premiers chapitres concernent tout le monde ; la suite explique le back-office (BO) à l’encadrement.

> Un écran cité ici n’apparaît que si votre rôle y donne accès. Si un menu vous manque, ce n’est pas une panne : c’est un droit (chapitre 9).

---

## 01 — Le tableau de bord
Après connexion, vous arrivez sur **Dashboard** (`/dashboard`).
- **Sans communauté**, l’écran propose « Trois façons d’utiliser Athena » : **Rejoindre une communauté** (« Entrer un code » → `/join`, ou le registre `/communities`), **Créer ma communauté** (`/communities/create`, avec le guide `/communities/create/guide`) ou **Utiliser Athena seul** (compte simple : votre dossier et la liaison du jeu restent à vous).
- **Membre d’une unité**, vous voyez votre nom, votre matricule et votre « Temps en mission », puis **Alertes & annonces** (`/alertes`), les tuiles **Votre espace** (Ma fiche, Mes démarches, Agenda, Qualifications, Avancement, Messagerie), **Actions du jour** (par exemple « Connexion Steam » si Steam n’est pas lié) et **Votre activité** avec vos formations prioritaires.

## 02 — La barre du haut
| Menu | Ce qu’il ouvre |
|---|---|
| Dashboard · Hub · Academy | `/dashboard`, `/hub`, `/academy` |
| Effectifs | l’annuaire des profils `/personnel` |
| Espaces | Documents `/documents`, Manœuvres `/evenements`, ORBAT `/orbat`, ATAK `/atak`, Mes formations `/academy/courses` |
| Gestion / Mon espace | le back-office `/back-office` |
| Menu de session | Ma fiche `/personnel/me`, Paramètres `/account`, Déconnexion |

La recherche (**Ctrl+K**) retrouve un écran par son nom. La cloche ouvre le centre d’alerte.

## 03 — Profil, compte et sécurité
Le menu de session › **Paramètres** ouvre `/account` (« Vue d’ensemble »). Les pages utiles :
- **Profil et préférences** `/account/preferences` : langue, notifications, jeu et comptes liés ;
- **Double vérification** `/account/security` : code par e-mail, application d’authentification, codes de secours ;
- **Portrait** `/account/portrait` et **Ma page publique** `/account/page-publique` (votre C.V. de joueur, visible à `/joueur/…`).

## 04 — Ma situation
Dans le back-office, la section **Ma situation** regroupe votre dossier : **Ma fiche** (`/back-office/ma-situation/ma-fiche`), **Mon unité**, **Mes qualifications** (avec le brevet PDF), **Mon carnet de vol**, **Mon avancement**, **Dossier de carrière**, **Ma dotation**, **Mon coffre** (vos documents RH) et **Mes démarches**. C’est ici que vous vérifiez ce que l’encadrement a enregistré sur vous.

## 05 — Langue, notifications, documents
- **Langue** : le sélecteur FR / EN du haut de page la change aussitôt et l’enregistre sur votre profil ; elle se règle aussi dans `/account/preferences`. Les formations intégrées de l’Academy suivent cette langue (repli en français si une traduction manque).
- **Notifications** : `/alertes` liste les messages en cours, à venir et l’historique ; les e-mails se règlent dans **Notifications par e-mail** de `/account/preferences` (préréglages « Sécurité seulement », « Usage courant », « Tout recevoir », « Tout couper »).
- **Documents** : `/documents` est la bibliothèque ; **Mes documents** (`/documents/mes-documents`) regroupe vos lectures obligatoires, vos accusés de lecture et leur historique.

:::astuce À retenir
- **Dashboard** pour le quotidien, **Ma situation** pour votre dossier, **Paramètres** pour le compte.
- Activez la **double vérification** dès le premier jour.
- Une lecture obligatoire se signe dans **Mes documents** : l’encadrement voit qui l’a lue.
:::
FR;
        $theoryEn = <<<'EN'
## Welcome to ATHENA
ATHENA is your community’s portal: personnel and ORBAT, training, exercises, documents, recruitment and the shared ATAK map. This course follows **the site as it actually is**: every screen comes with its menu path and address. The first chapters are for everyone; the rest explains the back office (BO) to staff.

> A screen listed here only appears if your role grants access to it. If a menu is missing, nothing is broken: it is a matter of rights (chapter 9).

---

## 01 — The dashboard
After signing in you land on **Dashboard** (`/dashboard`).
- **Without a community**, the page offers “Three ways to use Athena”: **Join a community** (“Enter a code” → `/join`, or the registry `/communities`), **Create my community** (`/communities/create`, with the guide `/communities/create/guide`) or **Use Athena on your own** (a simple account: your file and your game link stay yours).
- **As a unit member**, you see your name, service number and “Mission time”, then **Alerts & announcements** (`/alertes`), the **Your space** tiles (My file, My tasks, Schedule, Qualifications, Progression, Messaging), **Today’s actions** (for instance “Steam login” if Steam is not linked yet) and **Your activity** with your priority training.

## 02 — The top bar
| Menu | What it opens |
|---|---|
| Dashboard · Hub · Academy | `/dashboard`, `/hub`, `/academy` |
| Roster | the profile directory `/personnel` |
| Spaces | Documents `/documents`, Exercises `/evenements`, ORBAT `/orbat`, ATAK `/atak`, My training `/academy/courses` |
| Management / My space | the back office `/back-office` |
| Session menu | My file `/personnel/me`, Settings `/account`, Sign out |

Search (**Ctrl+K**) finds a screen by name. The bell opens the alert centre.

## 03 — Profile, account and security
Session menu › **Settings** opens `/account` (“Overview”). The pages you will use:
- **Profile and preferences** `/account/preferences`: language, notifications, game and linked accounts;
- **Two-step verification** `/account/security`: e-mail code, authenticator app, backup codes;
- **Portrait** `/account/portrait` and **My public page** `/account/page-publique` (your player CV, visible at `/joueur/…`).

## 04 — My situation
In the back office, the **My situation** section gathers your file: **My file** (`/back-office/ma-situation/ma-fiche`), **My unit**, **My qualifications** (with the PDF certificate), **My flight logbook**, **My progression**, **Career file**, **My equipment**, **My locker** (your HR documents) and **My tasks**. This is where you check what staff have recorded about you.

## 05 — Language, notifications, documents
- **Language**: the FR / EN switch at the top of the page changes it immediately and saves it to your profile; you can also set it in `/account/preferences`. Built-in Academy courses follow this language (falling back to French if a translation is missing).
- **Notifications**: `/alertes` lists current, upcoming and past messages; e-mails are set under **Email notifications** in `/account/preferences` (presets “Security only”, “Everyday use”, “Receive all”, “Turn all off”).
- **Documents**: `/documents` is the library; **My documents** (`/documents/mes-documents`) gathers your required reading, your read receipts and their history.

:::tip Key points
- **Dashboard** for day-to-day use, **My situation** for your file, **Settings** for your account.
- Turn on **two-step verification** on day one.
- Required reading is acknowledged in **My documents**: staff can see who has read it.
:::
EN;
        $demoFr = <<<'FR'
## Démonstration — Premier passage d’une recrue
:::note Situation
Tango vient d’être intégré à la 2e section. Il se connecte pour la première fois.
:::
:::etapes
Il ouvre **Dashboard** (`/dashboard`) : la tuile « Connexion Steam » apparaît dans Actions du jour ; il la suit (`/account/steam/connect`).
Menu de session › **Paramètres** › **Double vérification** (`/account/security`) : il active l’application d’authentification et range ses codes de secours.
Dans **Profil et préférences** (`/account/preferences`), il choisit **English** pour les pages et les e-mails, puis le préréglage « Usage courant ».
Il ouvre **Ma situation › Ma fiche** (`/back-office/ma-situation/ma-fiche`) : unité, grade et emploi sont ceux saisis à l’intégration. Une erreur ? Il le signale par **Mes démarches**.
Dans **Mes documents** (`/documents/mes-documents`), il lit le règlement intérieur et signe l’accusé de lecture.
:::
:::analyse Pourquoi cet ordre
Steam et la double vérification d’abord : ils protègent le compte et préparent la liaison du jeu (chapitre 12). La fiche ensuite, pour corriger tôt une erreur d’intégration.
:::
FR;
        $demoEn = <<<'EN'
## Demonstration — A recruit’s first visit
:::note Situation
Tango has just joined 2nd Platoon. He signs in for the first time.
:::
:::steps
He opens **Dashboard** (`/dashboard`): the “Steam login” tile appears under Today’s actions; he follows it (`/account/steam/connect`).
Session menu › **Settings** › **Two-step verification** (`/account/security`): he turns on the authenticator app and stores his backup codes.
In **Profile and preferences** (`/account/preferences`), he picks **English** for pages and e-mails, then the “Everyday use” preset.
He opens **My situation › My file** (`/back-office/ma-situation/ma-fiche`): unit, rank and job are the ones entered at onboarding. Something wrong? He reports it through **My tasks**.
In **My documents** (`/documents/mes-documents`), he reads the unit rules and signs the read receipt.
:::
:::analysis Why this order
Steam and two-step verification first: they protect the account and prepare the game link (chapter 12). The file next, so an onboarding mistake is caught early.
:::
EN;

        return C::chapter(
            ['Chapitre 1 — Prise en main du portail', 'Chapter 1 — Getting started with the portal'],
            [$theoryFr, $theoryEn],
            [$demoFr, $demoEn],
            C::stage(
                C::q(
                    'Un membre veut recevoir les pages et les e-mails en anglais. Où le règle-t-il de façon durable ?',
                    'A member wants pages and e-mails in English. Where do they set this permanently?',
                    [
                        C::o('Dans **Profil et préférences** (`/account/preferences`), rubrique Langue.', 'In **Profile and preferences** (`/account/preferences`), Language section.'),
                        C::o('En demandant à l’administrateur de changer la langue de la communauté.', 'By asking the administrator to change the community’s language.'),
                        C::o('Dans **Ma fiche**, champ Nationalité.', 'In **My file**, Nationality field.'),
                    ],
                    0,
                    'La langue est une **préférence personnelle** : elle s’enregistre sur le profil depuis `/account/preferences` (ou le sélecteur FR / EN, qui la sauvegarde aussi). La nationalité du personnage n’a aucun effet sur la langue.',
                    'Language is a **personal preference**: it is saved to your profile from `/account/preferences` (or the FR / EN switch, which saves it too). The character’s nationality has no effect on language.'
                ),
                ['Un équipier anglophone rejoint l’unité et ne comprend pas les e-mails reçus.', 'An English-speaking teammate joins the unit and cannot read the e-mails he receives.'],
                ['La langue suit la personne, pas la communauté.', 'Language follows the person, not the community.']
            ),
            C::stage(
                C::q(
                    'Où un membre retrouve-t-il les lectures obligatoires qu’il doit encore signer ?',
                    'Where does a member find the required reading they still have to acknowledge?',
                    [
                        C::o('Dans **Alertes & annonces** uniquement.', 'In **Alerts & announcements** only.'),
                        C::o('Dans **Mes documents** (`/documents/mes-documents`).', 'In **My documents** (`/documents/mes-documents`).'),
                        C::o('Dans **Mon carnet de vol**.', 'In **My flight logbook**.'),
                    ],
                    1,
                    '**Mes documents** regroupe les documents qui vous concernent : lectures obligatoires, accusés et historique. Les alertes signalent une nouveauté, mais l’accusé se signe dans le document.',
                    '**My documents** gathers the documents that concern you: required reading, receipts and history. Alerts flag something new, but the receipt is signed in the document itself.'
                ),
                ['Votre chef de section vous dit qu’une SOP est à lire avant samedi.', 'Your platoon leader tells you an SOP must be read before Saturday.']
            ),
            [
                C::q('Quelle adresse ouvre le tableau de bord ?', 'Which address opens the dashboard?', [C::o('`/dashboard`', '`/dashboard`'), C::o('`/hub`', '`/hub`'), C::o('`/account`', '`/account`')], 0,
                    'Le tableau de bord est `/dashboard`. `/hub` est le hub de la communauté et `/account` la vue d’ensemble du compte.',
                    'The dashboard is `/dashboard`. `/hub` is the community hub and `/account` the account overview.'),
                C::q('Un compte sans communauté voit « Trois façons d’utiliser Athena ». Laquelle n’en fait pas partie ?', 'An account without a community sees “Three ways to use Athena”. Which one is not among them?',
                    [C::o('Rejoindre une communauté.', 'Join a community.'), C::o('Créer ma communauté.', 'Create my community.'), C::o('Devenir administrateur de la plateforme.', 'Become a platform administrator.')], 2,
                    'Les trois choix sont **rejoindre**, **créer** ou **utiliser Athena seul**. « Admin » désigne l’administration de toute la plateforme : ce n’est pas un choix proposé aux membres.',
                    'The three choices are **join**, **create** or **use Athena on your own**. “Admin” means administering the whole platform: it is not something members can pick.'),
                C::q('Où activer l’application d’authentification ?', 'Where do you turn on the authenticator app?',
                    [C::o('`/account/password`', '`/account/password`'), C::o('`/account/security` (Double vérification)', '`/account/security` (Two-step verification)'), C::o('`/back-office/ma-situation/coffre`', '`/back-office/ma-situation/coffre`')], 1,
                    '**Double vérification** (`/account/security`) propose le code par e-mail, l’application d’authentification, les codes de secours et les appareils de confiance.',
                    '**Two-step verification** (`/account/security`) offers the e-mail code, the authenticator app, backup codes and trusted devices.'),
                C::q('Votre grade affiché est faux. Où le constatez-vous et comment réagir ?', 'Your displayed rank is wrong. Where do you see it and what do you do?',
                    [C::o('Dans **Ma situation › Ma fiche**, puis signalement par **Mes démarches**.', 'In **My situation › My file**, then report it through **My tasks**.'), C::o('Je le modifie moi-même dans Paramètres.', 'I change it myself in Settings.'), C::o('Je recrée un compte.', 'I create a new account.')], 0,
                    'Le grade est saisi par l’encadrement. **Ma fiche** montre ce qui est enregistré ; **Mes démarches** sert à le signaler. Un membre ne s’attribue pas un grade.',
                    'Rank is entered by staff. **My file** shows what is recorded; **My tasks** is how you report it. Members do not assign themselves a rank.'),
                C::q('Vous ne voulez plus que des e-mails de sécurité. Que faites-vous ?', 'You only want security e-mails from now on. What do you do?',
                    [C::o('Je désactive mon adresse e-mail.', 'I remove my e-mail address.'), C::o('Je supprime les alertes une par une.', 'I delete the alerts one by one.'), C::o('Préréglage « Sécurité seulement » dans Notifications par e-mail (`/account/preferences`).', '“Security only” preset under Email notifications (`/account/preferences`).')], 2,
                    'Les préréglages de **Notifications par e-mail** règlent toutes les catégories d’un coup ; « Sécurité seulement » garde les alertes de connexion et de compte.',
                    'The **Email notifications** presets set every category at once; “Security only” keeps sign-in and account alerts.'),
                C::q('Que montre **Ma page publique** ?', 'What does **My public page** show?',
                    [C::o('Le dossier RH complet.', 'The full HR file.'), C::o('Un C.V. de joueur à partager, que vous réglez vous-même.', 'A shareable player CV that you control.'), C::o('Les candidatures de l’unité.', 'The unit’s applications.')], 1,
                    '**Ma page publique** (`/account/page-publique`) publie un C.V. de joueur visible à `/joueur/…`. Le dossier RH reste dans le back-office.',
                    '**My public page** (`/account/page-publique`) publishes a player CV visible at `/joueur/…`. The HR file stays in the back office.'),
            ],
            ['Rédigez votre bilan de prise en main : (1) ce que vous avez réglé dans votre compte (langue, double vérification, notifications), (2) une information de **Ma fiche** que vous avez vérifiée, (3) un écran du tableau de bord que vous utiliserez chaque semaine et pourquoi.',
             'Write up your getting-started review: (1) what you set in your account (language, two-step verification, notifications), (2) one item in **My file** you checked, (3) one dashboard screen you will use every week and why.'],
            [
                ['Réglages du compte réellement effectués et cités.', 'Account settings actually made and listed.'],
                ['Vérification d’une donnée de la fiche.', 'One file item checked.'],
                ['Usage hebdomadaire concret.', 'Concrete weekly use.'],
            ],
            [
                ['L’évaluation atteint au moins 80 % dans le délai prévu.', 'The assessment reaches at least 80% within the time limit.'],
                ['Le stagiaire montre la double vérification active sur son compte.', 'The learner shows two-step verification turned on for their account.'],
                ['Il retrouve sans aide Ma fiche, Mes documents et les préférences de langue.', 'They find My file, My documents and language preferences without help.'],
            ],
            ['Fiche mémo — Portail ATHENA', 'Memo card — ATHENA portal',
             "Tableau de bord : /dashboard\nCompte : /account · Préférences : /account/preferences · Sécurité : /account/security\nMa situation : /back-office/ma-situation/ma-fiche\nAlertes : /alertes · Documents : /documents · Mes documents : /documents/mes-documents\nMes formations : /academy/courses\nRecherche : Ctrl+K",
             "Dashboard: /dashboard\nAccount: /account · Preferences: /account/preferences · Security: /account/security\nMy situation: /back-office/ma-situation/ma-fiche\nAlerts: /alertes · Documents: /documents · My documents: /documents/mes-documents\nMy training: /academy/courses\nSearch: Ctrl+K"]
        );
    }

    /** @return array<string, mixed> */
    public static function atak(): array
    {
        $theoryFr = <<<'FR'
## Relier le jeu à son compte
Le mod **ATAK natif** (téléphone tactique dans Arma 3, avec le module Overwatch connect) parle au site. Tant que le jeu n’est pas lié à votre compte, rien de ce que vous faites en mission ne remonte dans ATHENA.

## 01 — Trois façons de lier
| Méthode | Où | Ce qu’il faut faire |
|---|---|---|
| **Code affiché dans le jeu** | téléphone ATAK : app Athena, onglet CODE | ouvrir `/lier` sur le site, saisir le code, **Vérifier ce code**, puis autoriser. Code valable **10 minutes**. |
| **Code généré sur le site** | carte `/atak` › Appairer › Lier Arma (Overwatch) › **Générer un code** | dans le jeu, « Connexion Athena » › « Lier le jeu (code Appairer) », coller le code. Usage unique, **30 minutes**. |
| **Steam** | `/account/steam/connect` | le jeu reconnaît votre compte Steam ; si un même Steam est sur deux comptes, choisir sur `/account/steam-doublon`. |

> Ne confondez pas : le code « Liaison téléphone » de `/atak/connect` sert à ouvrir la carte sur un **vrai téléphone**, pas à lier Arma.

Le guide pas à pas est sur `/atak/premiere-liaison` ; vos terminaux liés (« En liaison » = signe de vie depuis moins de 3 minutes) sont dans **Ma situation › Ma liaison ATAK** (`/back-office/ma-situation/liaison-atak`) et dans `/account/security/devices`.

---

## 02 — Ce que le jeu remonte
- **Positions (suivi des forces amies)** : visibles en direct sur la carte `/atak` et dans « Effectifs en liaison » du tableau de bord.
- **Heures de jeu** : le « Temps en mission » du tableau de bord et de votre fiche ; l’encadrement les consulte dans `/back-office/atak/heures`.
- **Heures de vol et distances** : votre **carnet de vol** (`/back-office/ma-situation/carnet-de-vol`), où vous complétez un vol (nuit, atterrissages) ; il compte quand un instructeur le contresigne. Elles alimentent le cursus pilote et certaines qualifications automatiques (chapitre 5).
- **Tenues ACE Arsenal** : synchronisées vers **Équipement** (`/equipment`).
- **Marqueurs, dessins, messages et alertes médicales** : sur la carte `/atak`.

:::attention Dotation ≠ jeu
**Ma dotation** liste le matériel attribué par l’encadrement (avec numéros de série). Ce n’est pas ce que vous portez en jeu : cela vient des tenues synchronisées.
:::

:::astuce À retenir
- Pas de liaison, pas d’heures, pas de positions : liez le jeu dès la première mission.
- Code du jeu → `/lier` ; code du site → à coller dans le jeu.
- Vérifiez « En liaison » dans **Ma liaison ATAK** avant une manœuvre.
:::
FR;
        $theoryEn = <<<'EN'
## Linking the game to your account
The **native ATAK** mod (a tactical phone inside Arma 3, with the Overwatch connect module) talks to the site. Until the game is linked to your account, nothing you do on missions reaches ATHENA.

## 01 — Three ways to link
| Method | Where | What to do |
|---|---|---|
| **Code shown in the game** | ATAK phone: Athena app, CODE tab | open `/lier` on the site, type the code, **Check this code**, then authorise. Valid for **10 minutes**. |
| **Code generated on the site** | map `/atak` › Pair › Link Arma (Overwatch) › **Generate a code** | in game, “Athena sign-in” › “Link the game (Pair code)”, paste the code. Single use, **30 minutes**. |
| **Steam** | `/account/steam/connect` | the game recognises your Steam account; if the same Steam account is on two accounts, choose on `/account/steam-doublon`. |

> Do not mix them up: the “Phone link” code on `/atak/connect` opens the map on a **real phone**; it does not link Arma.

The step-by-step guide is at `/atak/premiere-liaison`; your linked devices (“Linked” = signal received within the last 3 minutes) are under **My situation › My ATAK link** (`/back-office/ma-situation/liaison-atak`) and in `/account/security/devices`.

---

## 02 — What the game reports
- **Positions (blue force tracking)**: live on the `/atak` map and under “Operators on the net” on the dashboard.
- **Game hours**: the “Mission time” shown on the dashboard and your file; staff see them in `/back-office/atak/heures`.
- **Flight hours and distances**: your **flight logbook** (`/back-office/ma-situation/carnet-de-vol`), where you complete a flight (night, landings); it counts once an instructor countersigns it. They feed the pilot track and some automatic qualifications (chapter 5).
- **ACE Arsenal loadouts**: synced to **Equipment** (`/equipment`).
- **Markers, drawings, messages and medical alerts**: on the `/atak` map.

:::warning Issued equipment ≠ game
**My equipment** lists the kit issued by staff (with serial numbers). It is not what you wear in game: that comes from synced loadouts.
:::

:::tip Key points
- No link, no hours, no positions: link the game before your first mission.
- Code from the game → `/lier`; code from the site → paste it in the game.
- Check “Linked” in **My ATAK link** before an exercise.
:::
EN;
        $demoFr = <<<'FR'
## Démonstration — Lier son jeu avant la manœuvre
:::etapes
Dans Arma, Tango sort le téléphone ATAK (Ctrl+U), ouvre l’app **Athena**, onglet **CODE** : un code s’affiche.
Sur son PC, il ouvre `/lier`, saisit le code et clique **Vérifier ce code**, puis autorise ce jeu.
Le téléphone confirme la liaison. Sur le site, **Ma liaison ATAK** (`/back-office/ma-situation/liaison-atak`) montre le terminal « En liaison ».
Après la mission, son « Temps en mission » augmente ; le vol en hélicoptère apparaît dans **Mon carnet de vol**, à compléter puis faire contresigner.
:::
:::analyse Erreur fréquente
Tango avait d’abord saisi le code de `/atak/connect` dans le jeu : c’est le code du **vrai téléphone**, refusé pour Arma. Chaque code a son sens : du jeu vers `/lier`, ou du site vers le jeu.
:::
FR;
        $demoEn = <<<'EN'
## Demonstration — Linking the game before an exercise
:::steps
In Arma, Tango takes out the ATAK phone (Ctrl+U), opens the **Athena** app, **CODE** tab: a code appears.
On his PC he opens `/lier`, types the code, clicks **Check this code**, then authorises the game.
The phone confirms the link. On the site, **My ATAK link** (`/back-office/ma-situation/liaison-atak`) shows the device as “Linked”.
After the mission his “Mission time” goes up; the helicopter flight appears in **My flight logbook**, to be completed and countersigned.
:::
:::analysis Common mistake
Tango first typed the `/atak/connect` code into the game: that code is for a **real phone** and Arma rejects it. Each code goes one way: from the game to `/lier`, or from the site into the game.
:::
EN;

        return C::chapter(
            ['Chapitre 12 — Mod ATAK et Overwatch connect', 'Chapter 12 — ATAK mod and Overwatch connect'],
            [$theoryFr, $theoryEn],
            [$demoFr, $demoEn],
            C::stage(
                C::q('Le téléphone ATAK affiche un code dans l’onglet CODE. Où le saisir ?', 'The ATAK phone shows a code in the CODE tab. Where do you enter it?',
                    [C::o('Sur `/atak/connect`.', 'On `/atak/connect`.'), C::o('Sur `/lier`, puis **Vérifier ce code**.', 'On `/lier`, then **Check this code**.'), C::o('Dans **Ma dotation**.', 'In **My equipment**.')], 1,
                    'Un code **affiché par le jeu** s’autorise sur le site, page `/lier`. `/atak/connect` sert au vrai téléphone.',
                    'A code **shown by the game** is authorised on the site, page `/lier`. `/atak/connect` is for a real phone.'),
                ['Première mission de la recrue, briefing dans 10 minutes.', 'The recruit’s first mission, briefing in 10 minutes.'],
                ['Le code du jeu va vers le site ; le code du site va vers le jeu.', 'A game code goes to the site; a site code goes into the game.']
            ),
            C::stage(
                C::q('Un pilote ne voit pas son vol d’hier compter pour sa qualification. Pourquoi, le plus souvent ?', 'A pilot’s flight from yesterday does not count towards their qualification. Most likely why?',
                    [C::o('Le vol n’a pas encore été contresigné par un instructeur dans le carnet de vol.', 'The flight has not yet been countersigned by an instructor in the logbook.'), C::o('Les heures de vol ne remontent jamais du jeu.', 'Flight hours never come from the game.'), C::o('Il faut l’inscrire dans Ma dotation.', 'It must be entered in My equipment.')], 0,
                    'Les vols enregistrés en jeu arrivent dans le **carnet de vol** ; ils comptent quand un instructeur les **contresigne**.',
                    'Flights recorded in game arrive in the **flight logbook**; they count once an instructor **countersigns** them.'),
                ['Un pilote prépare sa qualification hélicoptère.', 'A pilot is working towards a helicopter qualification.']
            ),
            [
                C::q('Combien de temps reste valable un code généré sur le site (Générer un code) ?', 'How long is a site-generated code (Generate a code) valid?', [C::o('5 minutes', '5 minutes'), C::o('24 heures', '24 hours'), C::o('30 minutes, usage unique', '30 minutes, single use')], 2,
                    'Le code « Appairer » généré sur `/atak` est valable 30 minutes et ne sert qu’une fois. Le code affiché par le jeu, lui, dure 10 minutes.',
                    'The “Pair” code generated on `/atak` is valid for 30 minutes and can be used once. The code shown by the game lasts 10 minutes.'),
                C::q('Où voir si son terminal est « En liaison » ?', 'Where do you see whether your device is “Linked”?', [C::o('**Ma situation › Ma liaison ATAK**', '**My situation › My ATAK link**'), C::o('**Mes documents**', '**My documents**'), C::o('`/communities`', '`/communities`')], 0,
                    '**Ma liaison ATAK** liste les terminaux liés, leur dernier signe de vie, l’historique et les terminaux retirés.',
                    '**My ATAK link** lists linked devices, their last signal, the history and removed devices.'),
                C::q('Le même compte Steam est sur deux comptes ATHENA. Que se passe-t-il ?', 'The same Steam account is on two ATHENA accounts. What happens?', [C::o('Le site fusionne les deux comptes.', 'The site merges both accounts.'), C::o('Le joueur choisit son compte sur `/account/steam-doublon`.', 'The player picks their account on `/account/steam-doublon`.'), C::o('Les positions sont envoyées aux deux comptes.', 'Positions are sent to both accounts.')], 1,
                    'La liaison Steam est refusée tant que le doublon n’est pas résolu : le joueur choisit sur `/account/steam-doublon`. Rien n’est fusionné automatiquement.',
                    'Steam linking is refused until the duplicate is resolved: the player chooses on `/account/steam-doublon`. Nothing is merged automatically.'),
                C::q('Quelle donnée ne vient **pas** du jeu ?', 'Which data does **not** come from the game?', [C::o('Les heures de jeu.', 'Game hours.'), C::o('Les positions sur la carte.', 'Positions on the map.'), C::o('Le matériel de **Ma dotation**.', 'The kit in **My equipment**.')], 2,
                    '**Ma dotation** est saisie par l’encadrement (matériel attribué nominativement). Heures et positions remontent du mod.',
                    '**My equipment** is entered by staff (kit issued by name). Hours and positions come from the mod.'),
                C::q('Où l’encadrement consulte-t-il les heures de jeu des membres ?', 'Where do staff look up members’ game hours?', [C::o('`/back-office/atak/heures`', '`/back-office/atak/heures`'), C::o('`/lier`', '`/lier`'), C::o('`/documents`', '`/documents`')], 0,
                    'Les heures de jeu sont réunies dans `/back-office/atak/heures`, exportables en tableur ; elles servent aussi aux qualifications automatiques.',
                    'Game hours are gathered in `/back-office/atak/heures`, exportable as a spreadsheet; they also feed automatic qualifications.'),
            ],
            ['Racontez votre liaison : méthode utilisée, ce que vous avez vérifié après (terminal en liaison, heures, carnet de vol) et un piège à éviter pour un nouveau membre.',
             'Describe your link: the method you used, what you checked afterwards (device connected, hours, logbook) and one pitfall a new member should avoid.'],
            [
                ['Méthode de liaison exacte.', 'Exact linking method.'],
                ['Vérification après liaison.', 'Check after linking.'],
                ['Piège identifié.', 'Pitfall identified.'],
            ],
            [
                ['L’évaluation atteint au moins 80 % dans le délai prévu.', 'The assessment reaches at least 80% within the time limit.'],
                ['Le terminal du stagiaire apparaît « En liaison » ou dans l’historique.', 'The learner’s device appears as “Linked” or in the history.'],
                ['Il distingue code du jeu, code du site et code du vrai téléphone.', 'They tell apart the game code, the site code and the real-phone code.'],
            ],
            ['Fiche mémo — Liaison ATAK', 'Memo card — ATAK link',
             "Code du jeu (10 min) → /lier\nCode du site (30 min, usage unique) : /atak › Appairer › Générer un code → à coller dans le jeu\nSteam : /account/steam/connect · doublon : /account/steam-doublon\nGuide : /atak/premiere-liaison\nMes terminaux : /back-office/ma-situation/liaison-atak\nCarnet de vol : /back-office/ma-situation/carnet-de-vol",
             "Game code (10 min) → /lier\nSite code (30 min, single use): /atak › Pair › Generate a code → paste it in the game\nSteam: /account/steam/connect · duplicate: /account/steam-doublon\nGuide: /atak/premiere-liaison\nMy devices: /back-office/ma-situation/liaison-atak\nFlight logbook: /back-office/ma-situation/carnet-de-vol"]
        );
    }
}
