<?php

declare(strict_types=1);

namespace App\Services\Academy\BuiltIn;

use App\Services\Academy\BuiltIn\AthenaPlatformCourse as C;

/**
 * Formation ATHENA : le back-office expliqué (aside, organisation, effectifs, qualifications et avancement,
 * doctrine, Academy, opérations et formule, droits d’accès). Libellés de menu FR tels qu’affichés, et leur
 * équivalent anglais (lang/en/nav.php, puis lang/en/site_auto.php).
 * Adresses entre accents graves : vérifiées par tests/Unit/AcademyAthenaCourseTest.php.
 */
final class AthenaCourseChaptersBackOffice
{
    /** Critère de validation commun : l’évaluation réussie dans le délai. */
    private const PASS = ['L’évaluation atteint au moins 80 % dans le délai prévu.', 'The assessment reaches at least 80% within the time limit.'];

    // ------------------------------------------------------------------ Chapitre 2 — L’aside du back-office

    /** @return array<string, mixed> */
    public static function aside(): array
    {
        $fr = <<<'FR'
## Le back-office en une page
Le back-office (BO) s’ouvre par **Gestion** dans la barre du haut, ou `/back-office`. À gauche, l’**aside** range tous les écrans de gestion par grandes sections. Il est **personnalisé** : chaque lien n’apparaît que si le type de votre communauté l’utilise et si votre niveau d’accès le permet.

> Un membre sans droit de gestion voit un aside réduit : **Pilotage**, **Ma situation**, **Terrain** (Overwatch, Ma liaison ATAK, Événements) et **Administratif** (Mes démarches, Boîte de réception). C’est normal.

## 01 — Les sections, dans l’ordre
| Section | Ce qu’on y trouve |
|---|---|
| **PILOTAGE** | Tableau de bord `/back-office`, Tableau opérationnel, **Indicateurs** (usage, métriques joueurs, heures de jeu `/back-office/atak/heures`, qualité des données) |
| **EFFECTIFS** | **Effectifs** (liste des membres `/back-office/ressources/effectifs`, affectations, chaîne de commandement, qualifications des membres, sanctions & absences, départs, comptes en double), **Recrutement** (`/back-office/recruitments`), **Dossiers RH** (à traiter, corrections, documents RH, carrière, réglages RH) |
| **ORGANISATION** | **Organisation** (organigramme `/orbat`, groupes, équipes, catalogue, indicatifs radio, matricules, PASS RH, disponibilité), **Référentiels** (grades, décorations, qualifications, compétences, espace aérien, cursus pilote, dotation) |
| **ACCÈS** | **Rôles et droits** (rôles, fonctions et emplois, accès au renseignement), Sécurité et blocages, Journal d’audit `/back-office/audit` |
| **OPÉRATIONS** | Événements, Missions, Carte tactique, Comptes rendus, Coopérations inter-unités, Extranet d’unité `/jnet` |
| **ROLEPLAY** | Suivi roleplay (bureau, échéances, sessions Arma), mode roleplay ATAK |
| **ATAK** | Overwatch, poste de situation `/back-office/atak`, contrôle de mission, terminaux, matériel, configuration et mods |
| **ACADEMY** | **Formations** (`/formation`), **Doctrine et documents** (doctrine et SOP `/back-office/doctrine`, référentiel doctrinal, fichiers partagés) |
| **COMMUNAUTÉ** | Vitrine et portail (paramètres `/back-office/community`, page d’accueil publique), Communication (annonces, mini-articles, e-mails), Modération, Musique, Configuration initiale |
| **SYSTÈME** | Configuration `/back-office/configuration`, Intégrations, Mises à jour, Traçabilité du courrier |

---

## 02 — Trouver un écran sans connaître le menu
La **recherche** du back-office connaît environ 150 pages et leurs synonymes métier : tapez « orbat » ou « organigramme », « droit » ou « accès ». Elle respecte vos droits : un écran interdit n’y apparaît pas. Elle retrouve aussi des pages hors aside, comme les codes d’invitation prioritaires.

## 03 — Lire un écran du BO
- Les **onglets** sont dans l’adresse (`?onglet=…`, `?vue=…`) : un lien copié rouvre le même onglet.
- Le **Tableau de bord** du BO (`/back-office`) résume ce qui attend une action ; partez de là chaque jour.
- Toute modification sensible est tracée dans le **Journal d’audit**.

:::astuce À retenir
- **Organisation** = la structure (unités, ORBAT). **Effectifs** = les personnes (qui est où, qui fait quoi). **Référentiels** = les listes de référence (grades, qualifications).
- Un menu absent = un droit ou un type de communauté, pas une panne.
- La recherche du BO est le chemin le plus court.
:::
FR;
        $en = <<<'EN'
## The back office on one page
The back office (BO) opens from **Management** in the top bar, or `/back-office`. On the left, the **sidebar** sorts every management screen into main sections. It is **tailored to you**: a link only appears if your community type uses it and your access level allows it.

> A member without management rights sees a reduced sidebar: **Command**, **My situation**, **Field** (Overwatch, My ATAK link, Events) and **Administration** (My tasks, Inbox). That is expected.

## 01 — The sections, in order
| Section | What you find there |
|---|---|
| **COMMAND** | Dashboard `/back-office`, Operations board, **Indicators** (usage, player metrics, play hours `/back-office/atak/heures`, data quality) |
| **PERSONNEL** | **Personnel** (member list `/back-office/ressources/effectifs`, assignments, chain of command, member qualifications, sanctions & absences, departures, duplicate accounts), **Recruitment** (`/back-office/recruitments`), **HR files** (to do, record corrections, HR documents, career, HR settings) |
| **ORGANIZATION** | **Organization** (org chart `/orbat`, groups, teams, catalogue, radio callsigns, service numbers, HR PASS, availability), **Frameworks** (ranks, decorations, qualifications, skills, airspace, pilot syllabus, issued gear) |
| **ACCESS** | **Roles and permissions** (roles, functions and jobs, intelligence access), Security and blocks, Audit log `/back-office/audit` |
| **OPERATIONS** | Events, Missions, Tactical map, After-action reports, Inter-unit cooperation, Unit extranet `/jnet` |
| **ROLEPLAY** | Roleplay tracking (desk, deadlines, Arma sessions), ATAK roleplay mode |
| **ATAK** | Overwatch, situation desk `/back-office/atak`, mission control, devices, equipment, settings and mods |
| **ACADEMY** | **Training** (`/formation`), **Doctrine and documents** (doctrine and SOPs `/back-office/doctrine`, doctrine repository, shared files) |
| **COMMUNITY** | Showcase and portal (settings `/back-office/community`, public home page), Communication (announcements, mini-articles, e-mails), Moderation, Music, Initial setup |
| **SYSTEM** | Settings `/back-office/configuration`, Integrations, Updates, Mail traceability |

---

## 02 — Finding a screen without knowing the menu
The back-office **search** knows about 150 pages and their everyday synonyms: type “orbat” or “org chart”, “rights” or “access”. It respects your rights: a forbidden screen never shows up. It also finds pages that are not in the sidebar, such as priority invitation codes.

## 03 — Reading a BO screen
- **Tabs** live in the address (`?onglet=…`, `?vue=…`): a copied link reopens the same tab.
- The BO **Dashboard** (`/back-office`) sums up what needs action; start there every day.
- Every sensitive change is recorded in the **Audit log**.

:::tip Key points
- **Organization** = the structure (units, ORBAT). **Personnel** = the people (who is where, who does what). **Frameworks** = the reference lists (ranks, qualifications).
- A missing menu = a right or a community type, not a fault.
- BO search is the shortest way in.
:::
EN;
        $demoFr = <<<'FR'
## Démonstration — Une semaine d’adjudant d’unité
:::etapes
Lundi, il ouvre `/back-office` : le tableau de bord signale deux candidatures et un dossier RH incomplet.
Il suit **EFFECTIFS › Recrutement** pour les candidatures (chapitre 10), puis **EFFECTIFS › Dossiers RH › Suivi des dossiers › À traiter et alertes**.
Mercredi, il cherche « matricule » dans la recherche du BO : elle ouvre **ORGANISATION › Organisation › Identification › Matricules**.
Vendredi, il vérifie dans le **Journal d’audit** (`/back-office/audit`) qui a modifié la chaîne de commandement.
:::
:::analyse Ce qu’il faut remarquer
Il ne parcourt pas tout l’aside : il part du tableau de bord, puis utilise la recherche. Les sections servent à **comprendre où vit une donnée**.
:::
FR;
        $demoEn = <<<'EN'
## Demonstration — A week as unit sergeant major
:::steps
On Monday he opens `/back-office`: the dashboard flags two applications and an incomplete HR file.
He follows **PERSONNEL › Recruitment** for the applications (chapter 10), then **PERSONNEL › HR files › File follow-up › To do and alerts**.
On Wednesday he types “service number” into BO search: it opens **ORGANIZATION › Organization › Identification › Service numbers**.
On Friday he checks in the **Audit log** (`/back-office/audit`) who changed the chain of command.
:::
:::analysis What to notice
He does not browse the whole sidebar: he starts from the dashboard, then uses search. The sections are there to **understand where a piece of data lives**.
:::
EN;

        return C::chapter(
            ['Chapitre 2 — Le back-office : lire l’aside', 'Chapter 2 — The back office: reading the sidebar'],
            [$fr, $en],
            [$demoFr, $demoEn],
            C::stage(
                C::q('Dans quelle section de l’aside se trouvent les grades et les qualifications de référence ?', 'Which sidebar section holds the reference ranks and qualifications?',
                    [C::o('EFFECTIFS', 'PERSONNEL'), C::o('ORGANISATION › Référentiels', 'ORGANIZATION › Frameworks'), C::o('SYSTÈME', 'SYSTEM')], 1,
                    'Les **Référentiels** (grades, décorations, qualifications, compétences, espace aérien, dotation) sont sous **ORGANISATION**. EFFECTIFS montre qui détient quoi, pas la liste de référence.',
                    '**Frameworks** (ranks, decorations, qualifications, skills, airspace, issued gear) sit under **ORGANIZATION**. PERSONNEL shows who holds what, not the reference list.'),
                ['Vous devez ajouter un nouveau grade à la liste de la communauté.', 'You need to add a new rank to the community’s list.'],
                ['Une liste de référence n’est pas une personne.', 'A reference list is not a person.']
            ),
            C::stage(
                C::q('Un sergent ne voit pas la section ACCÈS dans son aside. Que conclure ?', 'A sergeant cannot see the ACCESS section in their sidebar. What do you conclude?',
                    [C::o('Son niveau d’accès ne lui donne pas la gestion des rôles.', 'Their access level does not include managing roles.'), C::o('Le site est en panne.', 'The site is down.'), C::o('Il doit vider le cache du navigateur.', 'They must clear their browser cache.')], 0,
                    'L’aside est filtré par les droits et par le type de communauté. Une section absente signale un droit manquant.',
                    'The sidebar is filtered by rights and by community type. A missing section points to a missing right.'),
                ['Un sous-officier vous signale « il manque un menu ».', 'An NCO reports that “a menu is missing”.']
            ),
            [
                C::q('Quelle adresse ouvre le tableau de bord du back-office ?', 'Which address opens the back-office dashboard?', [C::o('/back-office', '/back-office'), C::o('/dashboard', '/dashboard'), C::o('/orbat', '/orbat')], 0,
                    '`/back-office` est le tableau de bord du BO ; `/dashboard` est celui du membre.', '`/back-office` is the BO dashboard; `/dashboard` is the member’s.'),
                C::q('Où se trouve la liste des candidatures ?', 'Where is the list of applications?', [C::o('COMMUNAUTÉ › Vitrine', 'COMMUNITY › Showcase'), C::o('ACADEMY › Formations', 'ACADEMY › Training'), C::o('EFFECTIFS › Recrutement', 'PERSONNEL › Recruitment')], 2,
                    'Le recrutement est un sous-menu d’**EFFECTIFS** (`/back-office/recruitments`).', 'Recruitment is a sub-menu of **PERSONNEL** (`/back-office/recruitments`).'),
                C::q('La doctrine et les SOP sont rangées sous…', 'Doctrine and SOPs are under…', [C::o('SYSTÈME', 'SYSTEM'), C::o('ACADEMY › Doctrine et documents', 'ACADEMY › Doctrine and documents'), C::o('ROLEPLAY', 'ROLEPLAY')], 1,
                    '**ACADEMY › Doctrine et documents** regroupe doctrine et SOP, référentiel doctrinal et fichiers partagés.', '**ACADEMY › Doctrine and documents** groups doctrine and SOPs, the doctrine repository and shared files.'),
                C::q('Comment retrouver vite un écran dont on ignore la place ?', 'How do you quickly find a screen when you do not know where it is?', [C::o('Avec la recherche du back-office (synonymes métier).', 'With back-office search (everyday synonyms).'), C::o('En devinant l’adresse.', 'By guessing the address.'), C::o('En demandant un accès administrateur.', 'By asking for administrator access.')], 0,
                    'La recherche connaît les pages et leurs synonymes, et ne montre que ce que vous pouvez ouvrir.', 'Search knows the pages and their synonyms, and only shows what you can open.'),
                C::q('Où vérifier qui a modifié une donnée sensible ?', 'Where do you check who changed sensitive data?', [C::o('Dans Mes documents.', 'In My documents.'), C::o('Dans les Indicateurs d’usage.', 'In Usage indicators.'), C::o('Dans le Journal d’audit (/back-office/audit).', 'In the Audit log (/back-office/audit).')], 2,
                    'Le **Journal d’audit** trace les modifications sensibles avec leur auteur.', 'The **Audit log** records sensitive changes with their author.'),
            ],
            ['Décrivez votre propre aside : les sections que vous voyez, une que vous ne voyez pas et pourquoi, et l’écran du BO que vous ouvrirez le plus souvent.',
             'Describe your own sidebar: the sections you see, one you do not and why, and the BO screen you will open most often.'],
            [['Sections réellement visibles listées.', 'Visible sections actually listed.'], ['Absence expliquée par un droit ou le type de communauté.', 'Absence explained by a right or the community type.'], ['Écran principal justifié.', 'Main screen justified.']],
            [self::PASS, ['Le stagiaire retrouve trois écrans demandés par la recherche ou l’aside.', 'The learner finds three requested screens through search or the sidebar.'], ['Il distingue Organisation, Effectifs et Référentiels.', 'They tell Organization, Personnel and Frameworks apart.']],
            ['Fiche mémo — Aside du back-office', 'Memo card — Back-office sidebar',
             "PILOTAGE · EFFECTIFS · ORGANISATION · ACCÈS · OPÉRATIONS · ROLEPLAY · ATAK · ACADEMY · COMMUNAUTÉ · SYSTÈME\nTableau de bord : /back-office\nEffectifs : /back-office/ressources/effectifs · Recrutement : /back-office/recruitments\nORBAT : /orbat · Grades : /back-office/referentiels/grades\nDoctrine : /back-office/doctrine · Audit : /back-office/audit",
             "COMMAND · PERSONNEL · ORGANIZATION · ACCESS · OPERATIONS · ROLEPLAY · ATAK · ACADEMY · COMMUNITY · SYSTEM\nDashboard: /back-office\nPersonnel: /back-office/ressources/effectifs · Recruitment: /back-office/recruitments\nORBAT: /orbat · Ranks: /back-office/referentiels/grades\nDoctrine: /back-office/doctrine · Audit: /back-office/audit"]
        );
    }

    // ------------------------------------------------------------------ Chapitre 3 — Organisation

    /** @return array<string, mixed> */
    public static function organisation(): array
    {
        $fr = <<<'FR'
## À quoi sert l’Organisation
**ORGANISATION › Organisation** décrit la **structure** de la communauté : unités, postes, regroupements, identifiants. Elle est tenue par le commandement et les gestionnaires ; tous les membres la consultent dans l’ORBAT, selon la confidentialité de chaque unité.

## 01 — L’organigramme (ORBAT)
`/orbat` affiche la structure en **Organigramme**, **Annuaire** ou **Tableur**. En haut : **Nouvelle unité**, **Inviter un membre**, **Exporter PDF**. Un clic droit sur une unité ouvre son menu : *Modifier…*, *Symbole militaire…*, *Chef, adjoint et postes…*, *Statut…*, *Confidentialité…*, *Créer une sous-unité…*, *Rattacher à…*, *Nouvelle équipe ici…*, *Affecter un titulaire…*, *Libérer le poste*. « Ouvrir la fiche complète » mène à `/back-office/organisation/unites/…`.

> **Voir comme** (Gestionnaire, Membre standard, Cadre, Commandement) montre l’ORBAT tel qu’un profil le voit : utilisez-le après avoir réglé la confidentialité d’une unité.

## 02 — Les autres écrans de la structure
| Écran | Adresse | Rôle |
|---|---|---|
| Groupes | `/back-office/groups` | regroupements transverses (filtres : à compléter, sans responsable…) |
| Équipes | `/back-office/teams` | petites cellules à l’intérieur d’une unité |
| Catalogue de l’organisation | `/back-office/organisation/catalogue` | modèles d’unités et de postes |
| Indicatifs radio | `/back-office/organisation/indicatifs` | indicatifs attribués |
| Matricules | `/back-office/organisation/matricules` | règles (préfixe, modèle, séquence), « Attribuer le prochain », « Attribuer à tous les membres sans matricule », import depuis un tableur |
| Disponibilité | `/back-office/organisation/disponibilite` | note sur 100 : postes pourvus (45 %), qualifications à jour (40 %), activité récente (15 %) ; 75 et plus = prête, sous 50 = critique |
| Espace aérien | `/back-office/espace-aerien` | ligne de vol, vols à valider (valider, ajourner, contresigner, refuser), pilotes, carnet de vol |

L’**adéquation grade / rôle par échelon** (`/back-office/ressources/effectifs/chaine/adequation`) compare chaque poste aux règles de son échelon (grade minimum et maximum, adjoint exigé, effectif, qualifications) et signale sous-capacité et surqualification.

---

:::attention Erreurs fréquentes
- Créer une unité **sans chef ni postes** : la disponibilité reste critique et la chaîne de commandement vide.
- Renuméroter les matricules sans passer par « Voir l’aperçu » : faites toujours l’aperçu avant « Appliquer la renumérotation ».
- Confondre **groupe** (transverse) et **équipe** (dans une unité).
:::

:::astuce À retenir
- La structure se construit dans `/orbat`, les personnes s’y placent ensuite (chapitre 4).
- Matricules : réglez les règles **avant** les premières intégrations ; l’intégration les attribue alors automatiquement.
- La disponibilité s’améliore en pourvoyant les postes et en tenant les qualifications à jour.
:::
FR;
        $en = <<<'EN'
## What Organization is for
**ORGANIZATION › Organization** describes the community’s **structure**: units, positions, groupings, identifiers. Command and managers maintain it; every member browses it in the ORBAT, subject to each unit’s privacy settings.

## 01 — The org chart (ORBAT)
`/orbat` shows the structure as **Chart**, **Directory** or **Spreadsheet**. At the top: **New unit**, **Invite a member**, **Export PDF**. Right-clicking a unit opens its menu: *Edit…*, *Military symbol…*, *Leader, deputy and positions…*, *Status…*, *Privacy…*, *Create a sub-unit…*, *Attach to…*, *New team here…*, *Assign a holder…*, *Vacate the position*. “Open the full record” leads to `/back-office/organisation/unites/…`.

> **View as** (Manager, Standard member, Officer, Command) shows the ORBAT the way that profile sees it: use it after setting a unit’s privacy.

## 02 — The other structure screens
| Screen | Address | Purpose |
|---|---|---|
| Groups | `/back-office/groups` | cross-cutting groupings (filters: incomplete, no leader…) |
| Teams | `/back-office/teams` | small cells inside a unit |
| Organisation catalogue | `/back-office/organisation/catalogue` | unit and position templates |
| Radio callsigns | `/back-office/organisation/indicatifs` | allocated callsigns |
| Service numbers | `/back-office/organisation/matricules` | rules (prefix, template, sequence), “Assign the next one”, assign to every member without one, import from a spreadsheet |
| Availability | `/back-office/organisation/disponibilite` | score out of 100: filled positions (45%), qualifications up to date (40%), recent activity (15%); 75 and above = ready, below 50 = critical |
| Airspace | `/back-office/espace-aerien` | flight line, flights to validate (validate, defer, countersign, reject), pilots, logbook |

**Rank / role fit by echelon** (`/back-office/ressources/effectifs/chaine/adequation`) compares each position with its echelon’s rules (minimum and maximum rank, deputy required, headcount, qualifications) and flags under-strength and over-qualified posts.

---

:::warning Common mistakes
- Creating a unit **with no leader or positions**: availability stays critical and the chain of command stays empty.
- Renumbering service numbers without “Preview” first: always preview before applying the renumbering.
- Mixing up a **group** (cross-cutting) and a **team** (inside a unit).
:::

:::tip Key points
- Build the structure in `/orbat`, then place people in it (chapter 4).
- Service numbers: set the rules **before** the first onboardings; onboarding then assigns them automatically.
- Availability improves by filling positions and keeping qualifications up to date.
:::
EN;
        $demoFr = <<<'FR'
## Démonstration — Créer la 3e section
:::etapes
**ORGANISATION › Organisation › Organigramme** (`/orbat`) › **Nouvelle unité** : onglet Identité, nom « 3e section », unité parente « 1re compagnie » › **Créer l’unité**.
Clic droit sur la 3e section › *Symbole militaire…* : section d’infanterie.
Clic droit › *Chef, adjoint et postes…* : un poste de chef, un adjoint, trois chefs de groupe.
Clic droit › *Confidentialité…*, puis **Voir comme › Membre standard** pour vérifier ce que voit un membre.
Dans **Disponibilité** (`/back-office/organisation/disponibilite`), la section apparaît « critique » : aucun poste n’est encore pourvu. C’est attendu jusqu’aux affectations.
:::
:::analyse Lien avec les autres modules
Les postes créés ici seront **pourvus** dans Effectifs › Affectations, et l’adéquation par échelon vérifiera les grades. Les membres intégrés par le recrutement pourront y être affectés directement.
:::
FR;
        $demoEn = <<<'EN'
## Demonstration — Creating 3rd Platoon
:::steps
**ORGANIZATION › Organization › Org chart** (`/orbat`) › **New unit**: Identity tab, name “3rd Platoon”, parent unit “1st Company” › **Create unit**.
Right-click 3rd Platoon › *Military symbol…*: infantry platoon.
Right-click › *Leader, deputy and positions…*: one leader, one deputy, three section leaders.
Right-click › *Privacy…*, then **View as › Standard member** to check what a member sees.
In **Availability** (`/back-office/organisation/disponibilite`), the platoon shows as “critical”: no position is filled yet. That is expected until assignments are made.
:::
:::analysis How it connects
The positions created here are **filled** in Personnel › Assignments, and the echelon fit check reviews ranks. Members onboarded through recruitment can be assigned to them directly.
:::
EN;

        return C::chapter(
            ['Chapitre 3 — Organisation : unités, ORBAT et identifiants', 'Chapter 3 — Organization: units, ORBAT and identifiers'],
            [$fr, $en],
            [$demoFr, $demoEn],
            C::stage(
                C::q('Vous venez de rendre une unité confidentielle. Comment vérifier ce qu’un membre ordinaire en voit ?', 'You have just made a unit private. How do you check what an ordinary member sees?',
                    [C::o('Me déconnecter et créer un compte de test.', 'Sign out and create a test account.'), C::o('Exporter le PDF.', 'Export the PDF.'), C::o('Utiliser « Voir comme › Membre standard » dans l’ORBAT.', 'Use “View as › Standard member” in the ORBAT.')], 2,
                    '**Voir comme** affiche l’ORBAT selon un profil (gestionnaire, membre standard, cadre, commandement) sans changer de compte.',
                    '**View as** displays the ORBAT for a profile (manager, standard member, officer, command) without switching accounts.'),
                ['Le renseignement ne doit pas apparaître pour toute la communauté.', 'The intelligence cell must not be visible to the whole community.'],
                ['L’ORBAT propose un aperçu par profil.', 'The ORBAT offers a preview per profile.']
            ),
            C::stage(
                C::q('Vous changez le modèle des matricules. Quelle est la bonne séquence ?', 'You change the service number template. What is the right sequence?',
                    [C::o('Enregistrer les règles, Renuméroter selon le modèle actuel, Voir l’aperçu, puis Appliquer.', 'Save the rules, renumber with the current template, preview, then apply.'), C::o('Modifier chaque fiche à la main.', 'Edit every file by hand.'), C::o('Supprimer les matricules puis attendre.', 'Delete the numbers and wait.')], 0,
                    'La renumérotation passe toujours par un **aperçu** avant application : on voit chaque ancien et nouveau matricule.',
                    'Renumbering always goes through a **preview** before it is applied: you see every old and new number.'),
                ['L’unité adopte un nouveau préfixe « 2RIMA- ».', 'The unit adopts a new prefix “2RIMA-”.']
            ),
            [
                C::q('Où crée-t-on une unité ?', 'Where do you create a unit?', [C::o('Dans /orbat, bouton Nouvelle unité.', 'In /orbat, New unit button.'), C::o('Dans Mes démarches.', 'In My tasks.'), C::o('Dans le Studio Academy.', 'In the Academy Studio.')], 0,
                    'Les unités se créent dans l’organigramme `/orbat` (**Nouvelle unité**, ou *Créer une sous-unité…* au clic droit).', 'Units are created in the org chart `/orbat` (**New unit**, or *Create a sub-unit…* on right-click).'),
                C::q('Une unité dont aucun poste n’est pourvu apparaît comment dans Disponibilité ?', 'How does a unit with no filled position appear in Availability?', [C::o('Prête', 'Ready'), C::o('À surveiller', 'To watch'), C::o('Critique', 'Critical')], 2,
                    'Les postes pourvus pèsent 45 % de la note : sans titulaire, la note tombe sous 50, donc **critique**.', 'Filled positions weigh 45% of the score: with no holders it drops below 50, so **critical**.'),
                C::q('Quelle différence entre un groupe et une équipe ?', 'What is the difference between a group and a team?', [C::o('Aucune.', 'None.'), C::o('Le groupe est transverse, l’équipe vit dans une unité.', 'A group is cross-cutting; a team lives inside a unit.'), C::o('L’équipe est réservée aux pilotes.', 'Teams are for pilots only.')], 1,
                    'Les **groupes** (`/back-office/groups`) traversent la structure ; les **équipes** (`/back-office/teams`) sont des cellules d’une unité.', '**Groups** (`/back-office/groups`) cut across the structure; **teams** (`/back-office/teams`) are cells of a unit.'),
                C::q('Où valide-t-on un vol déclaré par un pilote ?', 'Where do you validate a flight a pilot has logged?', [C::o('Espace aérien › À valider.', 'Airspace › To validate.'), C::o('Matricules.', 'Service numbers.'), C::o('Journal d’audit.', 'Audit log.')], 0,
                    'L’**Espace aérien** (`/back-office/espace-aerien`) propose Valider, Ajourner, Contresigner ou Refuser.', '**Airspace** (`/back-office/espace-aerien`) offers Validate, Defer, Countersign or Reject.'),
                C::q('Que contrôle l’adéquation par échelon ?', 'What does the echelon fit check?', [C::o('Les paiements.', 'Payments.'), C::o('Les heures de jeu.', 'Game hours.'), C::o('Grades, adjoint, effectif et qualifications de chaque poste.', 'Rank, deputy, headcount and qualifications of each position.')], 2,
                    'Elle compare chaque poste aux règles de son échelon et signale sous-capacité et surqualification.', 'It compares each position with its echelon’s rules and flags under-strength and over-qualified posts.'),
            ],
            ['Décrivez une unité que vous avez créée ou vérifiée : son parent, ses postes, sa confidentialité, et ce que la Disponibilité en dit.',
             'Describe a unit you created or checked: its parent, its positions, its privacy and what Availability says about it.'],
            [['Structure et postes décrits.', 'Structure and positions described.'], ['Confidentialité vérifiée avec Voir comme.', 'Privacy checked with View as.'], ['Lecture de la disponibilité.', 'Availability reading.']],
            [self::PASS, ['Le stagiaire crée (ou modifie) une unité avec chef et postes.', 'The learner creates (or edits) a unit with a leader and positions.'], ['Il explique les règles de matricule de sa communauté.', 'They explain their community’s service number rules.']],
            ['Fiche mémo — Organisation', 'Memo card — Organization',
             "ORBAT : /orbat (Nouvelle unité, clic droit sur une unité, Voir comme)\nGroupes : /back-office/groups · Équipes : /back-office/teams\nMatricules : /back-office/organisation/matricules (aperçu avant renumérotation)\nDisponibilité : /back-office/organisation/disponibilite\nEspace aérien : /back-office/espace-aerien\nAdéquation : /back-office/ressources/effectifs/chaine/adequation",
             "ORBAT: /orbat (New unit, right-click a unit, View as)\nGroups: /back-office/groups · Teams: /back-office/teams\nService numbers: /back-office/organisation/matricules (preview before renumbering)\nAvailability: /back-office/organisation/disponibilite\nAirspace: /back-office/espace-aerien\nEchelon fit: /back-office/ressources/effectifs/chaine/adequation"]
        );
    }

    // ------------------------------------------------------------------ Chapitre 4 — Effectifs : chaîne, affectations, fonctions

    /** @return array<string, mixed> */
    public static function personnelChain(): array
    {
        $fr = <<<'FR'
## À quoi servent les Effectifs
**EFFECTIFS › Effectifs** gère les **personnes** : où elles sont affectées, qui commande qui, quel emploi elles tiennent. C’est l’outil du chef du personnel et des chefs d’unité.

## 01 — Liste des membres et fiche
**Liste des membres** (`/back-office/ressources/effectifs`) ouvre la fiche de chaque membre (`/back-office/ressources/effectifs/membres/…`). Sur la fiche : **Changer d’unité** › Enregistrer l’affectation, **Attribuer l’accès** / Changer le niveau, grade, ancienneté, fonctions, et l’envoi d’un lien pour compléter son profil.

## 02 — Affectations et chaîne de commandement
- **Affectations** (`/back-office/ressources/effectifs/affectations`) : vue par unité ou par membre ; **Muter vers une autre unité…** ouvre la fenêtre Mutation (depuis, vers, motif) › **Confirmer la mutation**. Le bloc « Codes des unités » y règle le code de la communauté et de chaque unité.
- **Chaîne de commandement** (`/back-office/ressources/effectifs/chaine`) : tableau « Chefs d’unité » › **Enregistrer les chefs**, et « Qui relève de qui ».

## 03 — Fonctions et emplois
**ACCÈS › Rôles et droits › Fonctions et emplois** (`/back-office/ressources/effectifs/fonctions`) tient le référentiel des emplois : onglets *Emplois et catégories*, *Fonctions du site*, *Qui tient quel emploi*, *Historique et archives*, *Réglages*.
- **Chercher dans le catalogue** propose des emplois réels par pays (États-Unis, France, Allemagne, Chine, Russie, OTAN) avec leurs brevets, formations et doctrines typiques, que vous pouvez **rattacher à la fonction** comme prérequis.
- **Créer l’emploi** ou un sous-emploi, ou archiver un emploi devenu inutile.

> **Un emploi n’ouvre aucun droit d’accès.** Être « chef de groupe » sur sa fiche ne donne pas la gestion du BO : les droits se donnent par le niveau d’accès (chapitre 9).

## 04 — Le reste du sous-menu
Sanctions & absences, **Départs**, **Comptes en double** (deux comptes pour une même personne), et dans **Dossiers RH** : À traiter et alertes, Corrections de fiches, Demandes d’accès élevé, Documents RH, Carrière (avancement, ancienneté, mobilité, relève et succession), Réglages RH (`/back-office/ressources/effectifs/reglages`).

---

:::attention Erreurs fréquentes
- Muter un membre en modifiant seulement son emploi : l’affectation reste l’ancienne. Passez par **Muter** ou **Changer d’unité**.
- Donner un emploi en pensant donner des droits.
- Oublier « Enregistrer les chefs » après un changement de commandement.
:::

:::astuce À retenir
- Affectation = **où** ; emploi = **quoi** ; niveau d’accès = **ce qu’il peut faire sur le site**.
- La chaîne de commandement se déduit des chefs d’unité enregistrés.
- Les emplois du catalogue pays apportent leurs prérequis réels.
:::
FR;
        $en = <<<'EN'
## What Personnel is for
**PERSONNEL › Personnel** manages **people**: where they are assigned, who commands whom, which job they hold. It is the tool of the personnel officer and unit leaders.

## 01 — Member list and member file
**Member list** (`/back-office/ressources/effectifs`) opens each member’s file (`/back-office/ressources/effectifs/membres/…`). On the file: **Change unit** › Save assignment, **Grant access** / Change level, rank, seniority, functions, and sending a link to complete their profile.

## 02 — Assignments and chain of command
- **Assignments** (`/back-office/ressources/effectifs/affectations`): view by unit or by member; **Transfer to another unit…** opens the Transfer window (from, to, reason) › **Confirm the transfer**. The “Unit codes” block there sets the community code and each unit’s code.
- **Chain of command** (`/back-office/ressources/effectifs/chaine`): the “Unit leaders” table › **Save leaders**, and “Who reports to whom”.

## 03 — Functions and jobs
**ACCESS › Roles and permissions › Functions and jobs** (`/back-office/ressources/effectifs/fonctions`) holds the jobs framework: tabs *Jobs and categories*, *Site functions*, *Who holds which job*, *History and archives*, *Settings*.
- **Search the catalog** suggests real jobs by country (United States, France, Germany, China, Russia, NATO) with their typical badges, courses and doctrine, which you can **attach to the function** as prerequisites.
- **Create the job** or a sub-job, or archive a job you no longer need.

> **A job grants no access rights.** Being “section leader” on your file does not give you BO management: rights come from the access level (chapter 9).

## 04 — The rest of the sub-menu
Sanctions & absences, **Departures**, **Duplicate accounts** (two accounts for the same person), and under **HR files**: To do and alerts, Record corrections, Elevated access requests, HR documents, Career (promotion, seniority, mobility, handover and succession), HR settings (`/back-office/ressources/effectifs/reglages`).

---

:::warning Common mistakes
- Moving a member by only changing their job: the assignment stays the old one. Use **Transfer** or **Change unit**.
- Giving a job and assuming it gives rights.
- Forgetting “Save leaders” after a change of command.
:::

:::tip Key points
- Assignment = **where**; job = **what**; access level = **what they can do on the site**.
- The chain of command derives from the recorded unit leaders.
- Jobs from the country catalogues bring their real prerequisites.
:::
EN;
        $demoFr = <<<'FR'
## Démonstration — Une mutation et un nouveau chef
:::etapes
**EFFECTIFS › Effectifs › Affectations**, vue par unité : sur la carte du caporal Lima, **Muter vers une autre unité…** › vers « 3e section », motif « renfort » › **Confirmer la mutation**.
**Chaîne de commandement** : dans « Chefs d’unité », le sergent Mike devient chef de la 3e section › **Enregistrer les chefs**.
**Fonctions et emplois** › **Chercher dans le catalogue** : « chef de groupe d’infanterie » (France), **Rattacher à la fonction** la qualification typique proposée › **Créer l’emploi**.
Sur la fiche de Mike, l’emploi est ajouté. Ses droits sur le site, eux, n’ont pas changé.
:::
:::analyse Pourquoi c’est juste
Chaque information est rangée à sa place : l’unité dans l’affectation, le commandement dans la chaîne, le métier dans l’emploi. Le niveau d’accès de Mike se traite à part, s’il doit gérer le BO.
:::
FR;
        $demoEn = <<<'EN'
## Demonstration — A transfer and a new leader
:::steps
**PERSONNEL › Personnel › Assignments**, by unit: on Corporal Lima’s card, **Transfer to another unit…** › to “3rd Platoon”, reason “reinforcement” › **Confirm the transfer**.
**Chain of command**: in “Unit leaders”, Sergeant Mike becomes leader of 3rd Platoon › **Save leaders**.
**Functions and jobs** › **Search the catalog**: “infantry section leader” (France), **attach to the function** the typical qualification offered › **Create the job**.
On Mike’s file the job now appears. His site rights, however, have not changed.
:::
:::analysis Why this is right
Each fact sits where it belongs: the unit in the assignment, command in the chain, the trade in the job. Mike’s access level is handled separately, if he needs to run the BO.
:::
EN;

        return C::chapter(
            ['Chapitre 4 — Effectifs : affectations, chaîne et emplois', 'Chapter 4 — Personnel: assignments, chain of command and jobs'],
            [$fr, $en],
            [$demoFr, $demoEn],
            C::stage(
                C::q('Un membre change de section. Quel geste enregistre vraiment son changement d’unité ?', 'A member changes platoon. Which action actually records the change of unit?',
                    [C::o('Modifier son emploi.', 'Change their job.'), C::o('Muter vers une autre unité… puis Confirmer la mutation.', 'Transfer to another unit… then Confirm the transfer.'), C::o('Changer son niveau d’accès.', 'Change their access level.')], 1,
                    'L’unité est portée par l’**affectation** : **Muter** (ou Changer d’unité sur la fiche) la modifie et garde l’historique des mouvements.',
                    'The unit is held by the **assignment**: **Transfer** (or Change unit on the file) updates it and keeps the movement history.'),
                ['Le caporal Lima part en renfort à la 3e section.', 'Corporal Lima goes to reinforce 3rd Platoon.'],
                ['Où, quoi, droits : trois informations distinctes.', 'Where, what, rights: three separate facts.']
            ),
            C::stage(
                C::q('Mike reçoit l’emploi « chef de section ». Peut-il désormais gérer les candidatures ?', 'Mike is given the job “platoon leader”. Can he now handle applications?',
                    [C::o('Non : un emploi n’ouvre aucun droit ; il faut un niveau d’accès adapté.', 'No: a job grants no rights; he needs a suitable access level.'), C::o('Oui, automatiquement.', 'Yes, automatically.'), C::o('Oui, après 24 heures.', 'Yes, after 24 hours.')], 0,
                    'L’écran des emplois le précise : l’emploi décrit le métier. Les droits viennent du **niveau d’accès** attribué sur la fiche.',
                    'The jobs screen says so: a job describes the trade. Rights come from the **access level** granted on the file.'),
                ['Après sa nomination, Mike demande pourquoi il ne voit pas le recrutement.', 'After his appointment, Mike asks why he cannot see recruitment.']
            ),
            [
                C::q('Où désigne-t-on les chefs d’unité ?', 'Where are unit leaders designated?', [C::o('Chaîne de commandement, puis Enregistrer les chefs.', 'Chain of command, then Save leaders.'), C::o('Dans Mes documents.', 'In My documents.'), C::o('Dans le catalogue pays.', 'In the country catalogue.')], 0,
                    'La **Chaîne de commandement** (`/back-office/ressources/effectifs/chaine`) liste les chefs d’unité et en déduit qui relève de qui.', 'The **Chain of command** (`/back-office/ressources/effectifs/chaine`) lists unit leaders and derives who reports to whom.'),
                C::q('Que propose « Chercher dans le catalogue » dans Fonctions et emplois ?', 'What does “Search the catalog” offer in Functions and jobs?', [C::o('Des membres à recruter.', 'Members to recruit.'), C::o('Des grades.', 'Ranks.'), C::o('Des emplois réels par pays avec leurs prérequis typiques.', 'Real jobs by country with their typical prerequisites.')], 2,
                    'Le catalogue couvre États-Unis, France, Allemagne, Chine, Russie et OTAN ; chaque brevet, formation ou doctrine typique peut être rattaché à la fonction.', 'The catalogue covers the US, France, Germany, China, Russia and NATO; each typical badge, course or doctrine can be attached to the function.'),
                C::q('Deux comptes existent pour la même personne. Où le traiter ?', 'Two accounts exist for the same person. Where do you deal with it?', [C::o('Départs.', 'Departures.'), C::o('Comptes en double.', 'Duplicate accounts.'), C::o('Mobilité.', 'Mobility.')], 1,
                    '**Comptes en double** (sous Effectifs) regroupe ces cas.', '**Duplicate accounts** (under Personnel) gathers these cases.'),
                C::q('Où sont les codes des unités ?', 'Where are the unit codes?', [C::o('Dans Affectations, bloc Codes des unités.', 'In Assignments, Unit codes block.'), C::o('Dans Paramètres du compte.', 'In account Settings.'), C::o('Dans l’Academy.', 'In the Academy.')], 0,
                    'Le bloc « Codes des unités » de la page Affectations règle le code de la communauté et celui de chaque unité.', 'The “Unit codes” block on the Assignments page sets the community code and each unit’s code.'),
                C::q('Un membre affiché dans la mauvaise unité : quelle est la cause la plus probable ?', 'A member shows in the wrong unit: what is the most likely cause?', [C::o('Une langue mal réglée.', 'A wrong language setting.'), C::o('Un matricule manquant.', 'A missing service number.'), C::o('Son affectation n’a pas été mutée, seul son emploi a changé.', 'Their assignment was not transferred; only their job changed.')], 2,
                    'L’emploi et l’affectation sont distincts : changer l’un ne change pas l’autre.', 'Job and assignment are separate: changing one does not change the other.'),
            ],
            ['Racontez une mutation ou une nomination que vous avez enregistrée : écrans utilisés, ordre des gestes, et ce que vous avez vérifié ensuite.',
             'Describe a transfer or appointment you recorded: screens used, order of actions and what you checked afterwards.'],
            [['Écrans exacts cités.', 'Exact screens named.'], ['Affectation, emploi et droits distingués.', 'Assignment, job and rights distinguished.'], ['Vérification finale.', 'Final check.']],
            [self::PASS, ['Le stagiaire mute un membre et met à jour la chaîne de commandement.', 'The learner transfers a member and updates the chain of command.'], ['Il crée un emploi depuis le catalogue avec un prérequis rattaché.', 'They create a job from the catalogue with an attached prerequisite.']],
            ['Fiche mémo — Effectifs', 'Memo card — Personnel',
             "Membres : /back-office/ressources/effectifs\nAffectations : /back-office/ressources/effectifs/affectations (Muter, codes des unités)\nChaîne : /back-office/ressources/effectifs/chaine (Enregistrer les chefs)\nFonctions et emplois : /back-office/ressources/effectifs/fonctions\nRègle : affectation = où · emploi = quoi · niveau d’accès = droits",
             "Members: /back-office/ressources/effectifs\nAssignments: /back-office/ressources/effectifs/affectations (Transfer, unit codes)\nChain: /back-office/ressources/effectifs/chaine (Save leaders)\nFunctions and jobs: /back-office/ressources/effectifs/fonctions\nRule: assignment = where · job = what · access level = rights"]
        );
    }

    // ------------------------------------------------------------------ Chapitre 5 — Qualifications, avancement, documents RH

    /** @return array<string, mixed> */
    public static function qualifications(): array
    {
        $fr = <<<'FR'
## Qualifications : la liste de référence et les titulaires
Deux écrans à ne pas confondre :
- **ORGANISATION › Référentiels › Compétences › Qualifications** (`/back-office/referentiels/qualifications`) : la **liste** des qualifications de la communauté ;
- **EFFECTIFS › Effectifs › Qualifications des membres** (`/back-office/ressources/effectifs/qualifications`) : **qui** les détient.

## 01 — Créer et délivrer
Barre d’outils du référentiel : **Nouvelle qualification**, Matrice des effectifs, **Attribuer à un membre**, Ouvrir l’Academy, **Importer depuis le catalogue**, **Émetteurs**, Cursus pilote, ainsi que catégories, types et **brevets PDF**.
- Une qualification se règle par sa portée, sa catégorie, son **émetteur**, sa validité et son **mode d’obtention** : à la main par un instructeur, par une **formation Academy** réussie, ou **automatiquement** sur heures de jeu, heures de vol par appareil, missions, qualifications préalables.
- Les **émetteurs** (`/back-office/referentiels/qualifications/emetteurs`) sont les écoles ou cellules qui délivrent : **Créer l’émetteur**.
- **Importer depuis le catalogue** (`/back-office/referentiels/qualifications/catalogue`) reprend des qualifications de référence nationales › **Importer la sélection**.
- **Attribuer à un membre** (`/back-office/referentiels/qualifications/attribuer`) › **Enregistrer l’attribution** ; le brevet PDF se génère ensuite et le membre le télécharge dans **Mes qualifications**.

## 02 — Avancement et grades
**EFFECTIFS › Dossiers RH › Carrière › Avancement** (`/back-office/rh/avancement`) : **Nouvelle campagne** ouvre un assistant en six étapes — Grades, Période, Critères, Places, Commission, Vérifier — puis **Ouvrir la campagne**. La commission statue, la campagne se clôture puis se publie. La liste des grades est dans `/back-office/referentiels/grades`, l’ancienneté dans `/back-office/organisation/anciennete`.

## 03 — Documents RH
**Dossiers RH › Suivi des dossiers › Documents RH** (`/back-office/ressources/effectifs/documents-rh`) : déposer un fichier, établir une pièce et l’ajouter au dossier. Le membre retrouve ses pièces dans **Mon coffre**.

---

:::attention Erreurs fréquentes
- Créer une qualification **sans émetteur** : le brevet n’a pas de signataire clair.
- Attribuer à la main une qualification réglée en mode automatique : laissez la règle faire, ou choisissez un mode mixte.
- Oublier qu’une qualification masquée sur le profil n’est visible que de l’encadrement et des titulaires.
:::

:::astuce À retenir
- **Référentiel** = la liste ; **Qualifications des membres** = les titulaires.
- Trois modes d’obtention : à la main, par l’Academy, automatique (heures, vols, missions).
- L’avancement se fait par **campagnes**, pas grade par grade sur chaque fiche.
:::
FR;
        $en = <<<'EN'
## Qualifications: the reference list and the holders
Two screens not to confuse:
- **ORGANIZATION › Frameworks › Skills › Qualifications** (`/back-office/referentiels/qualifications`): the community’s **list** of qualifications;
- **PERSONNEL › Personnel › Member qualifications** (`/back-office/ressources/effectifs/qualifications`): **who** holds them.

## 01 — Creating and awarding
The framework toolbar: **New qualification**, Personnel matrix, **Award to a member**, Open the Academy, **Import from the catalogue**, **Issuers**, Pilot syllabus, plus categories, types and **PDF certificates**.
- A qualification is defined by its scope, category, **issuer**, validity and **award mode**: by hand by an instructor, through a passed **Academy course**, or **automatically** from game hours, flight hours per aircraft, missions or prior qualifications.
- **Issuers** (`/back-office/referentiels/qualifications/emetteurs`) are the schools or cells that award them: **Create the issuer**.
- **Import from the catalogue** (`/back-office/referentiels/qualifications/catalogue`) brings in national reference qualifications › **Import selection**.
- **Award to a member** (`/back-office/referentiels/qualifications/attribuer`) › **Save the award**; the PDF certificate can then be generated, and the member downloads it from **My qualifications**.

## 02 — Promotion and ranks
**PERSONNEL › HR files › Career › Promotion** (`/back-office/rh/avancement`): **New campaign** opens a six-step wizard — Ranks, Period, Criteria, Places, Board, Review — then **Open the campaign**. The board decides, then the campaign is closed and published. The rank list is in `/back-office/referentiels/grades`, seniority in `/back-office/organisation/anciennete`.

## 03 — HR documents
**HR files › File follow-up › HR documents** (`/back-office/ressources/effectifs/documents-rh`): upload a file, draw up a document and add it to the file. Members find their documents in **My locker**.

---

:::warning Common mistakes
- Creating a qualification **without an issuer**: the certificate has no clear signatory.
- Awarding by hand a qualification set to automatic: let the rule work, or choose a mixed mode.
- Forgetting that a qualification hidden from profiles is visible only to staff and holders.
:::

:::tip Key points
- **Framework** = the list; **Member qualifications** = the holders.
- Three award modes: by hand, through the Academy, automatic (hours, flights, missions).
- Promotion runs through **campaigns**, not rank by rank on each file.
:::
EN;
        $demoFr = <<<'FR'
## Démonstration — La qualification « Tireur de précision »
:::etapes
**Référentiels › Qualifications** › **Émetteurs** › **Créer l’émetteur** : « Cellule tir ».
**Nouvelle qualification** : nom « Tireur de précision », émetteur « Cellule tir », validité 12 mois, mode « à la main par un instructeur » › **Créer la qualification**.
Après l’épreuve, **Attribuer à un membre** : sergent Mike, date de l’épreuve › **Enregistrer l’attribution**, puis génération du brevet PDF.
Mike voit la qualification et télécharge son brevet dans **Ma situation › Mes qualifications**.
:::
:::analyse Et si on voulait l’automatiser ?
Avec un cours du Studio Academy « Tir de précision », on choisirait le mode **Formation Academy** : réussir le cours délivrerait la qualification sans geste manuel (chapitre 7).
:::
FR;
        $demoEn = <<<'EN'
## Demonstration — The “Marksman” qualification
:::steps
**Frameworks › Qualifications** › **Issuers** › **Create the issuer**: “Marksmanship cell”.
**New qualification**: name “Marksman”, issuer “Marksmanship cell”, valid 12 months, mode “by hand by an instructor” › **Create the qualification**.
After the test, **Award to a member**: Sergeant Mike, test date › **Save the award**, then generate the PDF certificate.
Mike sees the qualification and downloads his certificate from **My situation › My qualifications**.
:::
:::analysis What if we automated it?
With an Academy Studio course “Precision shooting”, we would pick the **Academy course** mode: passing the course would award the qualification with no manual step (chapter 7).
:::
EN;

        return C::chapter(
            ['Chapitre 5 — Qualifications, avancement et documents RH', 'Chapter 5 — Qualifications, promotion and HR documents'],
            [$fr, $en],
            [$demoFr, $demoEn],
            C::stage(
                C::q('Vous voulez que la qualification « Pilote confirmé » soit délivrée dès 50 heures de vol sur l’appareil. Quel mode choisir ?', 'You want “Senior pilot” awarded as soon as someone reaches 50 flight hours on the aircraft. Which mode?',
                    [C::o('À la main uniquement.', 'By hand only.'), C::o('Formation Academy.', 'Academy course.'), C::o('Automatique sur heures de vol par appareil.', 'Automatic on flight hours per aircraft.')], 2,
                    'Le mode **automatique** évalue les heures remontées du jeu et contresignées ; il délivre la qualification quand le seuil est atteint.',
                    'The **automatic** mode reads the hours reported from the game and countersigned; it awards the qualification once the threshold is reached.'),
                ['La cellule aérienne veut récompenser l’expérience réelle de vol en jeu.', 'The air cell wants to reward actual in-game flying experience.'],
                ['Le jeu remonte les heures de vol (chapitre 12).', 'The game reports flight hours (chapter 12).']
            ),
            C::stage(
                C::q('Vous devez promouvoir six caporaux au même moment, selon des critères. Quel outil ?', 'You must promote six corporals at once, based on criteria. Which tool?',
                    [C::o('Une campagne d’avancement (Nouvelle campagne).', 'A promotion campaign (New campaign).'), C::o('Modifier les six fiches une par une.', 'Edit all six files one by one.'), C::o('Le référentiel des qualifications.', 'The qualifications framework.')], 0,
                    'La **campagne** fixe grades, période, critères, places et commission, puis publie les décisions d’un coup.',
                    'A **campaign** sets ranks, period, criteria, places and board, then publishes all decisions at once.'),
                ['Fin de trimestre, le commandement veut une commission d’avancement.', 'End of quarter, command wants a promotion board.']
            ),
            [
                C::q('Quel écran montre qui détient chaque qualification ?', 'Which screen shows who holds each qualification?', [C::o('Qualifications des membres.', 'Member qualifications.'), C::o('Émetteurs.', 'Issuers.'), C::o('Grades.', 'Ranks.')], 0,
                    '**Qualifications des membres** (`/back-office/ressources/effectifs/qualifications`) liste les titulaires ; le référentiel liste les qualifications.', '**Member qualifications** (`/back-office/ressources/effectifs/qualifications`) lists holders; the framework lists qualifications.'),
                C::q('À quoi sert un émetteur ?', 'What is an issuer for?', [C::o('À envoyer les e-mails.', 'Sending e-mails.'), C::o('À désigner l’école ou la cellule qui délivre la qualification.', 'Naming the school or cell that awards the qualification.'), C::o('À créer des unités.', 'Creating units.')], 1,
                    'L’émetteur délivre et signe le brevet ; il peut avoir une unité, un signataire et un logo.', 'The issuer awards and signs the certificate; it can have a unit, a signatory and a logo.'),
                C::q('Combien d’étapes compte l’assistant de nouvelle campagne d’avancement ?', 'How many steps does the new promotion campaign wizard have?', [C::o('Deux', 'Two'), C::o('Dix', 'Ten'), C::o('Six : grades, période, critères, places, commission, vérifier', 'Six: ranks, period, criteria, places, board, review')], 2,
                    'L’assistant compte six étapes et se termine par **Ouvrir la campagne**.', 'The wizard has six steps and ends with **Open the campaign**.'),
                C::q('Où un membre télécharge-t-il son brevet PDF ?', 'Where does a member download their PDF certificate?', [C::o('Ma situation › Mes qualifications.', 'My situation › My qualifications.'), C::o('Mes démarches.', 'My tasks.'), C::o('Indicateurs.', 'Indicators.')], 0,
                    'Chaque qualification de **Mes qualifications** propose son brevet.', 'Each qualification under **My qualifications** offers its certificate.'),
                C::q('Comment reprendre une qualification nationale de référence sans la ressaisir ?', 'How do you reuse a national reference qualification without retyping it?', [C::o('La copier depuis un autre site.', 'Copy it from another website.'), C::o('Importer depuis le catalogue, puis Importer la sélection.', 'Import from the catalogue, then Import selection.'), C::o('La créer dans Mes documents.', 'Create it in My documents.')], 1,
                    'Le catalogue de référence (`/back-office/referentiels/qualifications/catalogue`) importe les qualifications choisies dans votre référentiel.', 'The reference catalogue (`/back-office/referentiels/qualifications/catalogue`) imports the chosen qualifications into your framework.'),
            ],
            ['Décrivez une qualification de votre communauté : émetteur, mode d’obtention choisi et pourquoi, et comment un membre l’obtient concrètement.',
             'Describe one qualification in your community: issuer, chosen award mode and why, and how a member actually obtains it.'],
            [['Émetteur cité.', 'Issuer named.'], ['Mode d’obtention justifié.', 'Award mode justified.'], ['Parcours du membre décrit.', 'Member’s path described.']],
            [self::PASS, ['Le stagiaire crée une qualification avec émetteur et l’attribue.', 'The learner creates a qualification with an issuer and awards it.'], ['Il explique le déroulé d’une campagne d’avancement.', 'They explain how a promotion campaign runs.']],
            ['Fiche mémo — Qualifications et avancement', 'Memo card — Qualifications and promotion',
             "Référentiel : /back-office/referentiels/qualifications\nTitulaires : /back-office/ressources/effectifs/qualifications\nÉmetteurs : /back-office/referentiels/qualifications/emetteurs\nAttribuer : /back-office/referentiels/qualifications/attribuer\nAvancement : /back-office/rh/avancement (6 étapes)\nGrades : /back-office/referentiels/grades\nDocuments RH : /back-office/ressources/effectifs/documents-rh",
             "Framework: /back-office/referentiels/qualifications\nHolders: /back-office/ressources/effectifs/qualifications\nIssuers: /back-office/referentiels/qualifications/emetteurs\nAward: /back-office/referentiels/qualifications/attribuer\nPromotion: /back-office/rh/avancement (6 steps)\nRanks: /back-office/referentiels/grades\nHR documents: /back-office/ressources/effectifs/documents-rh"]
        );
    }

    // ------------------------------------------------------------------ Chapitre 6 — Doctrine

    /** @return array<string, mixed> */
    public static function doctrine(): array
    {
        $fr = <<<'FR'
## À quoi sert la Doctrine
**ACADEMY › Doctrine et documents** diffuse les textes de référence de la communauté — doctrine, SOP, notes — et **prouve qui les a lus**. Rédacteurs et chefs d’unité l’utilisent ; les membres lisent et signent dans **Mes documents**.

## 01 — Doctrine et SOP
`/back-office/doctrine` a ses onglets : *Vue d’ensemble*, **Publier**, **Suivi de diffusion** (`?onglet=conformite`), *Types*, *Nomenclature*, **Référentiel doctrinal**. Pour chaque document : modifier le brouillon, publier, **nouvelle version**, relancer les retardataires, **voir comme un lecteur**, exporter les lectures en tableur.

## 02 — Publier un document
Le formulaire de publication (`/back-office/documents/publier`) a quatre parties :
1. **Le texte** ;
2. **Le classement** (type, nomenclature) ;
3. **La lecture attendue** (simple information ou lecture à confirmer) ;
4. **Les destinataires** (unités, groupes, rôles).

Puis **Enregistrer**, **Soumettre à validation** ou **Publier**.

## 03 — Suivi de diffusion
Vue *par document* ou *par personne* : qui a confirmé, qui ne l’a pas fait. **Relancer les non-confirmés** › **Envoyer les rappels**. Après une modification de fond, **Publier une nouvelle version** : la lecture est redemandée.

## 04 — Référentiel doctrinal
`/back-office/doctrine/referentiel` liste les références (publications réelles ou maison) par catégorie : **Ajouter une référence**, créer un document à partir d’une référence, rattacher un document existant, masquer ou archiver.

---

:::attention Erreurs fréquentes
- Corriger un document publié au lieu de publier une **nouvelle version** : les lecteurs ne sont pas prévenus du changement.
- Publier sans choisir de destinataires : personne n’est tenu de lire.
- Relancer à la main sur Discord au lieu d’**Envoyer les rappels** : le suivi n’en garde pas trace.
:::

:::astuce À retenir
- Texte, classement, lecture attendue, destinataires : quatre parties, dans cet ordre.
- Le **suivi de diffusion** est la preuve de lecture.
- Changement de fond = nouvelle version.
:::
FR;
        $en = <<<'EN'
## What Doctrine is for
**ACADEMY › Doctrine and documents** distributes the community’s reference texts — doctrine, SOPs, notes — and **proves who has read them**. Authors and unit leaders use it; members read and acknowledge in **My documents**.

## 01 — Doctrine and SOPs
`/back-office/doctrine` has its tabs: *Overview*, **Publish**, **Distribution tracking** (`?onglet=conformite`), *Types*, *Naming*, **Doctrine repository**. For each document: edit the draft, publish, **new version**, remind late readers, **view as a reader**, export readings to a spreadsheet.

## 02 — Publishing a document
The publishing form (`/back-office/documents/publier`) has four parts:
1. **The text**;
2. **Classification** (type, naming scheme);
3. **Expected reading** (information only, or reading to be confirmed);
4. **Recipients** (units, groups, roles).

Then **Save**, **Submit for approval** or **Publish**.

## 03 — Distribution tracking
View *by document* or *by person*: who has confirmed and who has not. **Remind unconfirmed recipients** › **Send reminders**. After a substantive change, **Publish a new version**: reading is requested again.

## 04 — Doctrine repository
`/back-office/doctrine/referentiel` lists references (real publications or in-house ones) by category: **Add a reference**, create a document from a reference, attach an existing document, hide or archive.

---

:::warning Common mistakes
- Fixing a published document instead of publishing a **new version**: readers are not told about the change.
- Publishing without recipients: nobody is required to read.
- Chasing people by hand on Discord instead of **Send reminders**: tracking keeps no record of it.
:::

:::tip Key points
- Text, classification, expected reading, recipients: four parts, in that order.
- **Distribution tracking** is your proof of reading.
- Substantive change = new version.
:::
EN;
        $demoFr = <<<'FR'
## Démonstration — Diffuser la SOP « Évacuation sanitaire »
:::etapes
**ACADEMY › Doctrine et documents › Doctrine et SOP** › **Nouveau document**.
Partie 1, le texte ; partie 2, type « SOP » ; partie 3, lecture **à confirmer** ; partie 4, destinataires « 1re compagnie » › **Publier**.
Trois jours plus tard, **Suivi de diffusion**, par document : 18 confirmations sur 25. **Relancer les non-confirmés** › **Envoyer les rappels**.
Le médecin-chef modifie la procédure de triage : **Publier une nouvelle version**. Les 25 destinataires doivent relire.
:::
:::analyse Le lien avec l’Academy
Une SOP s’enseigne aussi : un cours du Studio peut s’appuyer dessus, et un emploi peut l’exiger comme prérequis (Fonctions et emplois).
:::
FR;
        $demoEn = <<<'EN'
## Demonstration — Distributing the “Casualty evacuation” SOP
:::steps
**ACADEMY › Doctrine and documents › Doctrine and SOPs** › **New document**.
Part 1, the text; part 2, type “SOP”; part 3, reading **to be confirmed**; part 4, recipients “1st Company” › **Publish**.
Three days later, **Distribution tracking**, by document: 18 confirmations out of 25. **Remind unconfirmed recipients** › **Send reminders**.
The chief medic changes the triage procedure: **Publish a new version**. All 25 recipients must read it again.
:::
:::analysis How it links to the Academy
An SOP can be taught too: a Studio course can build on it, and a job can require it as a prerequisite (Functions and jobs).
:::
EN;

        return C::chapter(
            ['Chapitre 6 — Doctrine : SOP, référentiel et suivi de diffusion', 'Chapter 6 — Doctrine: SOPs, repository and distribution tracking'],
            [$fr, $en],
            [$demoFr, $demoEn],
            C::stage(
                C::q('Vous modifiez sur le fond une SOP déjà publiée. Que faire ?', 'You make a substantive change to an SOP already published. What do you do?',
                    [C::o('Corriger le texte en silence.', 'Fix the text quietly.'), C::o('Publier une nouvelle version.', 'Publish a new version.'), C::o('Supprimer la SOP et la recréer.', 'Delete the SOP and recreate it.')], 1,
                    'La **nouvelle version** garde l’historique et redemande la lecture aux destinataires.',
                    'A **new version** keeps the history and asks recipients to read again.'),
                ['La procédure de triage change avant la grande opération.', 'The triage procedure changes before the big operation.'],
                ['Les lecteurs doivent savoir que le texte a changé.', 'Readers must know the text has changed.']
            ),
            C::stage(
                C::q('Sept membres n’ont pas confirmé la lecture. Quel geste laisse une trace ?', 'Seven members have not confirmed reading. Which action leaves a record?',
                    [C::o('Relancer les non-confirmés, puis Envoyer les rappels.', 'Remind unconfirmed recipients, then Send reminders.'), C::o('Un message privé à chacun.', 'A private message to each.'), C::o('Rien, ce n’est pas grave.', 'Nothing, it does not matter.')], 0,
                    'Les rappels envoyés depuis le **suivi de diffusion** sont tracés avec le document.',
                    'Reminders sent from **distribution tracking** are recorded against the document.'),
                ['Veille d’opération, la lecture est obligatoire.', 'Eve of an operation, reading is mandatory.']
            ),
            [
                C::q('Combien de parties compte le formulaire de publication ?', 'How many parts does the publishing form have?', [C::o('Une', 'One'), C::o('Quatre : texte, classement, lecture attendue, destinataires', 'Four: text, classification, expected reading, recipients'), C::o('Sept', 'Seven')], 1,
                    'Les quatre parties : le texte, le classement, la lecture attendue et les destinataires.', 'The four parts: text, classification, expected reading and recipients.'),
                C::q('Où voit-on qui a lu un document ?', 'Where do you see who has read a document?', [C::o('Suivi de diffusion.', 'Distribution tracking.'), C::o('Matricules.', 'Service numbers.'), C::o('Indicatifs radio.', 'Radio callsigns.')], 0,
                    'Le **suivi de diffusion** (`/back-office/doctrine?onglet=conformite`) montre les confirmations par document ou par personne.', '**Distribution tracking** (`/back-office/doctrine?onglet=conformite`) shows confirmations by document or by person.'),
                C::q('Où le membre confirme-t-il sa lecture ?', 'Where does the member confirm reading?', [C::o('Dans le Journal d’audit.', 'In the Audit log.'), C::o('Dans l’ORBAT.', 'In the ORBAT.'), C::o('Dans le document, depuis Mes documents.', 'In the document, from My documents.')], 2,
                    '**Mes documents** (`/documents/mes-documents`) liste ses lectures obligatoires ; la confirmation se fait sur le document.', '**My documents** (`/documents/mes-documents`) lists required reading; confirmation happens on the document.'),
                C::q('À quoi sert le référentiel doctrinal ?', 'What is the doctrine repository for?', [C::o('Lister les références et y rattacher des documents.', 'Listing references and attaching documents to them.'), C::o('Payer la formule.', 'Paying for the plan.'), C::o('Lier le jeu.', 'Linking the game.')], 0,
                    'Le référentiel (`/back-office/doctrine/referentiel`) range les références par catégorie et permet d’en tirer un document.', 'The repository (`/back-office/doctrine/referentiel`) sorts references by category and lets you build a document from one.'),
                C::q('Comment vérifier la mise en page vue par un lecteur ?', 'How do you check the layout a reader sees?', [C::o('Demander à un membre une capture.', 'Ask a member for a screenshot.'), C::o('Exporter en tableur.', 'Export to a spreadsheet.'), C::o('Voir comme un lecteur.', 'View as a reader.')], 2,
                    '**Voir comme un lecteur** affiche le document tel que le destinataire le reçoit.', '**View as a reader** shows the document as the recipient receives it.'),
            ],
            ['Décrivez un document que vous diffuseriez : les quatre parties remplies, le suivi que vous feriez et le moment où vous publieriez une nouvelle version.',
             'Describe a document you would distribute: the four parts filled in, the tracking you would do and when you would publish a new version.'],
            [['Quatre parties remplies.', 'Four parts filled in.'], ['Suivi et relance prévus.', 'Tracking and reminders planned.'], ['Critère de nouvelle version.', 'New-version criterion.']],
            [self::PASS, ['Le stagiaire publie un document avec lecture à confirmer.', 'The learner publishes a document with reading to be confirmed.'], ['Il lit le suivi de diffusion et envoie les rappels.', 'They read distribution tracking and send reminders.']],
            ['Fiche mémo — Doctrine', 'Memo card — Doctrine',
             "Doctrine et SOP : /back-office/doctrine\nPublier : /back-office/documents/publier (texte · classement · lecture attendue · destinataires)\nSuivi de diffusion : /back-office/doctrine?onglet=conformite\nRéférentiel : /back-office/doctrine/referentiel\nCôté membre : /documents/mes-documents",
             "Doctrine and SOPs: /back-office/doctrine\nPublish: /back-office/documents/publier (text · classification · expected reading · recipients)\nDistribution tracking: /back-office/doctrine?onglet=conformite\nRepository: /back-office/doctrine/referentiel\nMember side: /documents/mes-documents"]
        );
    }

    // ------------------------------------------------------------------ Chapitre 7 — Academy

    /** @return array<string, mixed> */
    public static function academy(): array
    {
        $fr = <<<'FR'
## À quoi sert l’Academy
L’Academy (`/academy`) réunit **tout ce qu’un membre peut suivre ou obtenir** : formations, brevets et licences, qualifications, parcours, compétences. Elle demande une formule payante active pour la communauté.

## 01 — Côté membre
| Espace | Adresse | Usage |
|---|---|---|
| Tableau de bord | `/academy/dashboard` | où j’en suis, à faire maintenant, mes brevets |
| Catalogue | `/academy/catalogue` | tout ce qu’on peut obtenir, avec filtres et état pour moi |
| Mes formations | `/academy/courses` | mes inscriptions |
| Salle de cours | `/academy/classroom` | les sept étapes d’un chapitre (cours, démonstration, cas guidé, cas autonome, évaluation, débriefing, validation) |

## 02 — Le Studio
`/academy/studio` : **Nouveau** propose une **formation**, une **qualification** ou un **brevet**. Une formation s’écrit en FR et EN, chapitre par chapitre, avec un texte riche (titres, gras, listes, encadrés, tableaux, images). **Enregistrer**, **Aperçu**, **Publier** ; pour modifier un cours déjà suivi, **Créer une nouvelle version** (les stagiaires gardent la leur). **Partager** invite un collaborateur comme lecteur, éditeur ou **instructeur**. Dans les réglages d’un cours, **Délivré à la réussite** rattache une qualification ou un brevet.

## 03 — Droits et instructeurs
`/academy/droits` règle sept droits : créer des cours, modifier tous les cours, publier/archiver/partager, inscrire des membres, valider et débriefer, délivrer les attestations, voir les statistiques. Chaque droit : **Modifier** › Enregistrer ce droit. **Voir comme…** › Afficher ses droits montre ce qu’un membre précis peut faire.

L’**espace instructeur** (`/academy/instructor`) liste ce qui attend : **Lire et valider** les débriefings (un instructeur ne valide jamais son propre parcours), et **Inscrire un membre à un cours**.

---

:::attention Erreurs fréquentes
- Modifier un cours publié au lieu d’en **créer une nouvelle version** : vous perturbez les stagiaires en cours.
- Donner le droit « valider et débriefer » sans former l’instructeur aux critères.
- Créer une qualification à la main alors que le cours peut la **délivrer à la réussite**.
:::

:::astuce À retenir
- Membre : **catalogue**, **mes formations**, **salle de cours**.
- Auteur : **Studio** (formation, qualification, brevet ; nouvelle version pour un cours déjà suivi).
- Encadrement : **droits** et **espace instructeur** ; vérifier avec Voir comme….
:::
FR;
        $en = <<<'EN'
## What the Academy is for
The Academy (`/academy`) brings together **everything a member can take or earn**: courses, badges and licences, qualifications, pathways, skills. It requires an active paid plan for the community.

## 01 — Member side
| Space | Address | Use |
|---|---|---|
| Dashboard | `/academy/dashboard` | where I stand, what to do now, my certificates |
| Catalogue | `/academy/catalogue` | everything you can earn, with filters and your own status |
| My training | `/academy/courses` | my enrolments |
| Classroom | `/academy/classroom` | the seven stages of a chapter (lesson, demonstration, guided case, independent case, assessment, debrief, validation) |

## 02 — The Studio
`/academy/studio`: **New** offers a **course**, a **qualification** or a **badge**. A course is written in French and English, chapter by chapter, with rich text (headings, bold, lists, callouts, tables, images). **Save**, **Preview**, **Publish**; to change a course people are already taking, **Create a new version** (learners keep theirs). **Share** invites a collaborator as reader, editor or **instructor**. In a course’s settings, **Awarded on success** links a qualification or badge.

## 03 — Rights and instructors
`/academy/droits` sets seven rights: create courses, edit all courses, publish/archive/share, enrol members, validate and debrief, issue certificates, see statistics. For each right: **Edit** › save this right. **View as…** › Show their rights displays what a given member can do.

The **instructor space** (`/academy/instructor`) lists what is waiting: **Read and validate** debriefs (an instructor never validates their own course), and **Enrol a member in a course**.

---

:::warning Common mistakes
- Editing a published course instead of **creating a new version**: you disrupt learners mid-course.
- Granting “validate and debrief” without briefing the instructor on the criteria.
- Creating a qualification by hand when the course can **award it on success**.
:::

:::tip Key points
- Member: **catalogue**, **my training**, **classroom**.
- Author: **Studio** (course, qualification, badge; new version for a course already in use).
- Staff: **rights** and **instructor space**; check with View as….
:::
EN;
        $demoFr = <<<'FR'
## Démonstration — Un cours « Tir de précision » qui délivre sa qualification
:::etapes
`/academy/studio` › **Nouveau** › Formation : titre FR/EN, trois chapitres, un quiz par chapitre › **Enregistrer** puis **Aperçu**.
Réglages du cours › **Délivré à la réussite** : ajouter la qualification « Tireur de précision » › **Publier**.
**Partager** › le sergent Mike, rôle **Instructeur** › Inviter.
Dans `/academy/droits`, **Voir comme…** › Mike › Afficher ses droits : il peut valider les stagiaires de ce cours.
Un stagiaire termine : Mike fait **Lire et valider** dans l’espace instructeur ; la qualification apparaît dans **Mes qualifications** du stagiaire.
:::
:::analyse Pourquoi c’est efficace
La qualification se délivre seule à la validation du dernier chapitre : aucun oubli, et le référentiel montre quel cours y mène.
:::
FR;
        $demoEn = <<<'EN'
## Demonstration — A “Precision shooting” course that awards its qualification
:::steps
`/academy/studio` › **New** › Course: FR/EN title, three chapters, one quiz per chapter › **Save** then **Preview**.
Course settings › **Awarded on success**: add the “Marksman” qualification › **Publish**.
**Share** › Sergeant Mike, role **Instructor** › Invite.
In `/academy/droits`, **View as…** › Mike › Show their rights: he can validate this course’s learners.
A learner finishes: Mike uses **Read and validate** in the instructor space; the qualification appears in the learner’s **My qualifications**.
:::
:::analysis Why it works well
The qualification is awarded automatically when the last chapter is validated: nothing is forgotten, and the framework shows which course leads to it.
:::
EN;

        return C::chapter(
            ['Chapitre 7 — Academy : catalogue, Studio, instructeurs et brevets', 'Chapter 7 — Academy: catalogue, Studio, instructors and badges'],
            [$fr, $en],
            [$demoFr, $demoEn],
            C::stage(
                C::q('Douze stagiaires suivent votre cours ; vous voulez réécrire un chapitre. Que faire ?', 'Twelve learners are taking your course; you want to rewrite a chapter. What do you do?',
                    [C::o('Modifier directement le cours publié.', 'Edit the published course directly.'), C::o('Supprimer le cours.', 'Delete the course.'), C::o('Créer une nouvelle version dans le Studio.', 'Create a new version in the Studio.')], 2,
                    'Chaque stagiaire garde la copie de la version à laquelle il s’est inscrit ; la **nouvelle version** s’applique aux nouveaux inscrits et garde le lien avec la qualification délivrée.',
                    'Each learner keeps a copy of the version they enrolled in; the **new version** applies to new enrolments and keeps its link with the awarded qualification.'),
                ['Le retour des stagiaires montre qu’un chapitre est confus.', 'Learner feedback shows a chapter is confusing.'],
                ['Ne pas casser la progression en cours.', 'Do not break progress already under way.']
            ),
            C::stage(
                C::q('Comment savoir si Mike pourra valider vos stagiaires, sans lui demander ?', 'How do you check whether Mike will be able to validate your learners, without asking him?',
                    [C::o('Voir comme… › Mike › Afficher ses droits, dans /academy/droits.', 'View as… › Mike › Show their rights, in /academy/droits.'), C::o('Regarder son grade.', 'Look at his rank.'), C::o('Lui faire passer le cours.', 'Make him take the course.')], 0,
                    '**Voir comme…** affiche, cours par cours, ce qu’un membre voit, modifie et peut valider.',
                    '**View as…** shows, course by course, what a member sees, edits and can validate.'),
                ['Vous venez de partager le cours avec Mike en instructeur.', 'You have just shared the course with Mike as instructor.']
            ),
            [
                C::q('Que propose le bouton Nouveau du Studio ?', 'What does the Studio’s New button offer?', [C::o('Une formation, une qualification ou un brevet.', 'A course, a qualification or a badge.'), C::o('Une unité.', 'A unit.'), C::o('Une candidature.', 'An application.')], 0,
                    'Le Studio crée formations, qualifications et brevets ; les fiches sont celles du référentiel.', 'The Studio creates courses, qualifications and badges; the records are the framework’s own.'),
                C::q('Qui peut valider un chapitre d’un stagiaire ?', 'Who can validate a learner’s chapter?', [C::o('Le stagiaire lui-même.', 'The learner themself.'), C::o('Un instructeur habilité, distinct du stagiaire.', 'An authorised instructor other than the learner.'), C::o('N’importe quel membre.', 'Any member.')], 1,
                    'La validation exige le droit « valider et débriefer » et un instructeur différent du stagiaire.', 'Validation requires the “validate and debrief” right and an instructor other than the learner.'),
                C::q('Combien de droits règle /academy/droits ?', 'How many rights does /academy/droits set?', [C::o('Deux', 'Two'), C::o('Quatre', 'Four'), C::o('Sept', 'Seven')], 2,
                    'Créer, modifier tous les cours, publier/archiver/partager, inscrire, valider et débriefer, délivrer les attestations, voir les statistiques.', 'Create, edit all courses, publish/archive/share, enrol, validate and debrief, issue certificates, see statistics.'),
                C::q('Comment un cours délivre-t-il automatiquement une qualification ?', 'How does a course award a qualification automatically?', [C::o('Par le réglage Délivré à la réussite.', 'Through the Awarded on success setting.'), C::o('Par un e-mail.', 'By e-mail.'), C::o('Ce n’est pas possible.', 'It is not possible.')], 0,
                    '**Délivré à la réussite** rattache la qualification ou le brevet ; elle est attribuée à la validation finale.', '**Awarded on success** links the qualification or badge; it is awarded at final validation.'),
                C::q('Où un instructeur inscrit-il un membre à un cours ?', 'Where does an instructor enrol a member in a course?', [C::o('Dans Mes documents.', 'In My documents.'), C::o('Espace instructeur › Inscrire un membre à un cours.', 'Instructor space › Enrol a member in a course.'), C::o('Dans l’ORBAT.', 'In the ORBAT.')], 1,
                    'L’**espace instructeur** (`/academy/instructor`) propose l’inscription et la file des validations.', 'The **instructor space** (`/academy/instructor`) offers enrolment and the validation queue.'),
            ],
            ['Décrivez un cours que vous créeriez : sa qualification délivrée, ses instructeurs, et comment vous vérifieriez leurs droits.',
             'Describe a course you would create: its awarded qualification, its instructors and how you would check their rights.'],
            [['Qualification rattachée.', 'Qualification linked.'], ['Instructeurs et partage.', 'Instructors and sharing.'], ['Vérification avec Voir comme….', 'Check with View as….']],
            [self::PASS, ['Le stagiaire montre un cours dans le Studio avec « Délivré à la réussite » réglé.', 'The learner shows a Studio course with “Awarded on success” set.'], ['Il explique pourquoi créer une nouvelle version plutôt que modifier.', 'They explain why to create a new version rather than edit.']],
            ['Fiche mémo — Academy', 'Memo card — Academy',
             "Membre : /academy/dashboard · /academy/catalogue · /academy/courses\nStudio : /academy/studio (Nouveau : formation, qualification, brevet ; nouvelle version)\nDroits : /academy/droits (sept droits, Voir comme…)\nInstructeur : /academy/instructor (Lire et valider, inscrire)",
             "Member: /academy/dashboard · /academy/catalogue · /academy/courses\nStudio: /academy/studio (New: course, qualification, badge; new version)\nRights: /academy/droits (seven rights, View as…)\nInstructor: /academy/instructor (Read and validate, enrol)"]
        );
    }

    // ------------------------------------------------------------------ Chapitre 8 — Opérations, sessions, formule

    /** @return array<string, mixed> */
    public static function operations(): array
    {
        $fr = <<<'FR'
## Opérations : du créneau au compte rendu
La section **OPÉRATIONS** et la section **ROLEPLAY** organisent la vie en jeu : annoncer, inscrire, pointer, débriefer.

## 01 — Événements
**OPÉRATIONS › Événements › Inscriptions en cours** (`/back-office/events`), onglets À venir, Passés, Annulés, et un **Calendrier** (`/back-office/events?vue=calendrier`).
- **Nouveau créneau** : titre, début, type (Opération, Événement, Formation, Autre) › **Publier le créneau**.
- Sur l’événement (`/back-office/events/…`) : enregistrer les détails, **Postes de mission** › Ajouter le poste, ajouter un membre, **Pointer présence**, réponses nominatives, télécharger la feuille, annuler le créneau.
- **Bilan de participation** : `/back-office/events/insights`.

Les membres voient et répondent dans **Manœuvres** (`/evenements`).

## 02 — Missions, carte et comptes rendus
- **Missions** : portail `/back-office/missions` (planification), cycle de mission, diapositives de briefing.
- **Carte tactique** : `/back-office/operations/carte-tactique`.
- **Comptes rendus** : post-op (`/back-office/atak/comptes-rendus`) et **Remontées des joueurs** (`/back-office/remontees`).
- **Coopérations inter-unités** : `/back-office/cooperation/missions`.

## 03 — Roleplay et sessions Arma
**ROLEPLAY › Suivi roleplay** : bureau de suivi (`/back-office/roleplay-followup`, onglets dossiers, immersion, phases), échéances, et **Sessions Arma** (`/back-office/roleplay/sessions`) : **Nouvelle session** › Créer la session, puis Démarrer et Clôturer ; « Règles de comptage » règle ce qui compte comme présence.

---

## 04 — Formule et paiements (administrateur de communauté)
- **Offres et formules** (`/platform/upgrade`) : choisir la formule (Essentiel, Équipe, Complet) › **Souscrire maintenant** ; le paiement se fait sur une page dédiée avec sa référence. Seuls les responsables de la communauté peuvent souscrire.
- La formule ouvre des modules : l’Academy, par exemple, demande une formule payante active.
- **Soutenir le projet** par un don : `/soutenir-atak`.

:::attention Erreurs fréquentes
- Publier un créneau sans **postes de mission** : les membres s’inscrivent sans rôle.
- Oublier de **pointer la présence** : le bilan de participation et certaines qualifications automatiques restent faux.
- Confondre l’événement (l’annonce) et la session Arma (la soirée réellement jouée).
:::

:::astuce À retenir
- Créneau › postes › inscriptions › présence › compte rendu.
- La session Arma compte la présence réelle en jeu.
- La formule se gère dans Offres et formules, par les responsables de la communauté.
:::
FR;
        $en = <<<'EN'
## Operations: from time slot to after-action report
The **OPERATIONS** and **ROLEPLAY** sections organise life in game: announce, sign up, take attendance, debrief.

## 01 — Events
**OPERATIONS › Events › Current enrolments** (`/back-office/events`), tabs Upcoming, Past, Cancelled, and a **Calendar** (`/back-office/events?vue=calendrier`).
- **New slot**: title, start, type (Operation, Event, Training, Other) › **Publish the slot**.
- On the event (`/back-office/events/…`): save details, **Mission positions** › Add the position, add a member, **Check in attendance**, individual replies, download the sheet, cancel the slot.
- **Attendance review**: `/back-office/events/insights`.

Members see and reply in **Exercises** (`/evenements`).

## 02 — Missions, map and reports
- **Missions**: portal `/back-office/missions` (planning), mission cycle, briefing slides.
- **Tactical map**: `/back-office/operations/carte-tactique`.
- **After-action reports**: post-op (`/back-office/atak/comptes-rendus`) and **Player reports** (`/back-office/remontees`).
- **Inter-unit cooperation**: `/back-office/cooperation/missions`.

## 03 — Roleplay and Arma sessions
**ROLEPLAY › Roleplay tracking**: follow-up desk (`/back-office/roleplay-followup`, tabs files, immersion, phases), deadlines, and **Arma sessions** (`/back-office/roleplay/sessions`): **New session** › Create the session, then Start and Close; “Counting rules” sets what counts as attendance.

---

## 04 — Plan and payments (community administrator)
- **Offers and plans** (`/platform/upgrade`): choose the plan (Essential, Team, Complete) › **Subscribe now**; payment happens on a dedicated page with its reference. Only the community’s leaders can subscribe.
- The plan unlocks modules: the Academy, for instance, needs an active paid plan.
- **Support the project** with a donation: `/soutenir-atak`.

:::warning Common mistakes
- Publishing a slot with no **mission positions**: members sign up without a role.
- Forgetting to **check in attendance**: the attendance review and some automatic qualifications stay wrong.
- Mixing up the event (the announcement) and the Arma session (the evening actually played).
:::

:::tip Key points
- Slot › positions › sign-ups › attendance › report.
- The Arma session counts actual in-game attendance.
- The plan is managed in Offers and plans, by the community’s leaders.
:::
EN;
        $demoFr = <<<'FR'
## Démonstration — L’opération « Aube rouge »
:::etapes
**OPÉRATIONS › Événements** › **Nouveau créneau** : « Aube rouge », samedi 21 h, type Opération › **Publier le créneau**.
Sur l’événement, **Postes de mission** : chef de section, deux chefs de groupe, un infirmier, un JTAC › Ajouter le poste.
Le soir même, **Agenda et sessions de jeu › Sessions de jeu** › **Nouvelle session** › Créer la session, puis Démarrer ; à la fin, Clôturer.
Sur l’événement, **Pointer présence**. Le lendemain, le compte rendu post-op est rédigé et les remontées des joueurs sont lues.
:::
:::analyse Ce que la présence alimente
La présence pointée nourrit le **bilan de participation**, l’activité récente de la **Disponibilité** (chapitre 3) et les critères « missions » des qualifications automatiques (chapitre 5).
:::
FR;
        $demoEn = <<<'EN'
## Demonstration — Operation “Red Dawn”
:::steps
**OPERATIONS › Events** › **New slot**: “Red Dawn”, Saturday 21:00, type Operation › **Publish the slot**.
On the event, **Mission positions**: platoon leader, two section leaders, a medic, a JTAC › Add the position.
That evening, **ROLEPLAY › Arma sessions** › **New session** › Create the session, then Start; at the end, Close.
On the event, **Check in attendance**. The next day, the post-op report is written and player reports are read.
:::
:::analysis What attendance feeds
Recorded attendance feeds the **attendance review**, the recent-activity part of **Availability** (chapter 3) and the “missions” criteria of automatic qualifications (chapter 5).
:::
EN;

        return C::chapter(
            ['Chapitre 8 — Opérations, sessions et formule', 'Chapter 8 — Operations, sessions and plan'],
            [$fr, $en],
            [$demoFr, $demoEn],
            C::stage(
                C::q('Les membres s’inscrivent à l’opération mais personne ne sait qui est infirmier. Qu’a-t-on oublié ?', 'Members sign up for the operation but nobody knows who the medic is. What was forgotten?',
                    [C::o('La formule payante.', 'The paid plan.'), C::o('Les postes de mission sur l’événement.', 'The mission positions on the event.'), C::o('La double vérification.', 'Two-step verification.')], 1,
                    'Les **postes de mission** attachent un rôle à chaque inscription.',
                    '**Mission positions** attach a role to each sign-up.'),
                ['Vendredi soir, le chef de section demande la répartition des rôles.', 'Friday evening, the platoon leader asks for the role split.'],
                ['Un créneau sans poste n’est qu’une date.', 'A slot without positions is just a date.']
            ),
            C::stage(
                C::q('Un membre a joué toute l’opération mais son activité reste nulle. Cause probable ?', 'A member played the whole operation but shows no activity. Likely cause?',
                    [C::o('La présence n’a pas été pointée.', 'Attendance was not checked in.'), C::o('Il n’a pas de matricule.', 'He has no service number.'), C::o('Il est en anglais.', 'His language is English.')], 0,
                    '**Pointer présence** (et la session Arma) enregistrent la participation réelle.',
                    '**Check in attendance** (and the Arma session) record actual participation.'),
                ['Lundi, un membre conteste son bilan de participation.', 'On Monday, a member disputes their attendance review.']
            ),
            [
                C::q('Quel bouton crée un événement ?', 'Which button creates an event?', [C::o('Nouveau créneau.', 'New slot.'), C::o('Nouvelle unité.', 'New unit.'), C::o('Nouvelle campagne.', 'New campaign.')], 0,
                    '**Nouveau créneau** puis **Publier le créneau**, dans `/back-office/events`.', '**New slot** then **Publish the slot**, in `/back-office/events`.'),
                C::q('Où les membres répondent-ils aux manœuvres ?', 'Where do members reply to exercises?', [C::o('/back-office/audit', '/back-office/audit'), C::o('/evenements', '/evenements'), C::o('/orbat', '/orbat')], 1,
                    'Les membres voient les manœuvres et répondent dans `/evenements`.', 'Members see exercises and reply in `/evenements`.'),
                C::q('Que fait « Règles de comptage » dans Sessions Arma ?', 'What do “Counting rules” do in Arma sessions?', [C::o('Calcule les paiements.', 'Calculates payments.'), C::o('Compte les matricules.', 'Counts service numbers.'), C::o('Définit ce qui compte comme présence.', 'Defines what counts as attendance.')], 2,
                    'Les règles de comptage fixent quand un joueur est compté présent dans une session.', 'Counting rules set when a player counts as present in a session.'),
                C::q('Qui peut souscrire une formule pour la communauté ?', 'Who can subscribe to a plan for the community?', [C::o('Tout membre.', 'Any member.'), C::o('Les responsables de la communauté.', 'The community’s leaders.'), C::o('Les candidats.', 'Applicants.')], 1,
                    '**Offres et formules** (`/platform/upgrade`) n’autorise la souscription qu’aux responsables de la communauté.', '**Offers and plans** (`/platform/upgrade`) only lets the community’s leaders subscribe.'),
                C::q('Où lit-on les remontées des joueurs après une opération ?', 'Where do you read player reports after an operation?', [C::o('Dans /back-office/remontees.', 'In /back-office/remontees.'), C::o('Dans Mon coffre.', 'In My locker.'), C::o('Dans les Matricules.', 'In Service numbers.')], 0,
                    '**Comptes rendus › Remontées des joueurs** (`/back-office/remontees`).', '**After-action reports › Player reports** (`/back-office/remontees`).'),
            ],
            ['Décrivez la préparation d’une opération de votre unité, du créneau au compte rendu, et ce que la présence pointée alimente ensuite.',
             'Describe how your unit prepares an operation, from slot to report, and what recorded attendance feeds afterwards.'],
            [['Créneau et postes.', 'Slot and positions.'], ['Session et présence.', 'Session and attendance.'], ['Compte rendu et effets.', 'Report and effects.']],
            [self::PASS, ['Le stagiaire publie un créneau avec postes de mission.', 'The learner publishes a slot with mission positions.'], ['Il explique la différence entre événement et session Arma.', 'They explain the difference between an event and an Arma session.']],
            ['Fiche mémo — Opérations', 'Memo card — Operations',
             "Événements : /back-office/events (Nouveau créneau, postes, Pointer présence)\nCôté membre : /evenements\nSessions Arma : /back-office/roleplay/sessions\nComptes rendus : /back-office/atak/comptes-rendus · Remontées : /back-office/remontees\nFormule : /platform/upgrade · Don : /soutenir-atak",
             "Events: /back-office/events (New slot, positions, Check in attendance)\nMember side: /evenements\nArma sessions: /back-office/roleplay/sessions\nReports: /back-office/atak/comptes-rendus · Player reports: /back-office/remontees\nPlan: /platform/upgrade · Donation: /soutenir-atak"]
        );
    }

    // ------------------------------------------------------------------ Chapitre 9 — Droits d’accès et vérification

    /** @return array<string, mixed> */
    public static function rights(): array
    {
        $fr = <<<'FR'
## Qui peut faire quoi
Sur ATHENA, **ce qu’un membre peut faire dans le site** dépend de son **niveau d’accès**, pas de son grade ni de son emploi. Les niveaux se règlent dans **ACCÈS › Rôles et droits › Rôles** (`/back-office/ressources/effectifs/roles`), écran « Niveaux d’accès ».

## 01 — Régler un niveau d’accès
- **Nouveau niveau** : un nom, et « Partir du modèle » pour ne pas tout cocher à la main › Créer.
- Cochez les droits par module (« Tout cocher » existe), puis **Enregistrer ce niveau**.
- **Voir les titulaires** liste qui a ce niveau ; « Rétablir le modèle de départ » annule vos changements ; un niveau peut être masqué ou supprimé.

## 02 — Donner un niveau à un membre
Sur la fiche du membre (`/back-office/ressources/effectifs/membres/…`) : **Attribuer l’accès** (ou Changer le niveau) › **Enregistrer l’accès**. Le recrutement donne aussi un niveau à l’intégration (par défaut « Membre »), et la communauté règle le rôle donné aux nouveaux arrivants.

> **Grade ≠ emploi ≠ niveau d’accès.** Un capitaine peut n’avoir aucun droit de gestion ; un caporal secrétaire peut gérer les effectifs.

---

## 03 — Vérifier avant de se tromper
ATHENA n’a pas d’écran unique de simulation des droits ; on vérifie avec les outils prévus pour chaque domaine :
| Ce que vous voulez vérifier | Outil |
|---|---|
| Qui détient un niveau | **Voir les titulaires** (Rôles) |
| Ce qu’un membre précis peut faire dans l’Academy | `/academy/droits` › **Voir comme…** › Afficher ses droits |
| Ce qu’un profil voit dans l’ORBAT | `/orbat` › **Voir comme** |
| Ce qu’un lecteur voit d’un document | **Voir comme un lecteur** (Doctrine) |
| Qui a changé un droit | **Journal d’audit** (`/back-office/audit`) |

Les demandes de droits élevés passent par **Dossiers RH › Demandes d’accès élevé** ; **Sécurité et blocages** (`/back-office/security-indicators`) montre les comptes bloqués et alertes de sécurité ; **Accès au renseignement** (`/back-office/renseignement/acces`) gère à part l’accès aux contenus de renseignement.

:::attention Erreurs fréquentes
- Tout donner « pour aller vite » : un niveau large expose les données des membres.
- Créer un niveau par personne : faites des niveaux par **fonction de gestion** (recruteur, secrétaire, instructeur).
- Ne pas vérifier : après un changement, contrôlez avec Voir les titulaires ou Voir comme….
:::

:::astuce À retenir
- Droits = **niveau d’accès**, réglé dans Rôles et attribué sur la fiche.
- Partir d’un **modèle**, donner le minimum utile, retirer le niveau quand la fonction cesse.
- Vérifier avec Voir les titulaires, Voir comme… et le Journal d’audit.
:::
FR;
        $en = <<<'EN'
## Who can do what
On ATHENA, **what a member can do on the site** depends on their **access level**, not on their rank or job. Levels are set in **ACCESS › Roles and permissions › Roles** (`/back-office/ressources/effectifs/roles`), the “Access levels” screen.

## 01 — Setting up an access level
- **New level**: a name, and “Start from template” so you do not tick everything by hand › Create.
- Tick the rights per module (“Tick all” exists), then **Save this level**.
- **View holders** lists who has the level; “Restore the starting template” undoes your changes; a level can be hidden or deleted.

## 02 — Giving a member a level
On the member’s file (`/back-office/ressources/effectifs/membres/…`): **Grant access** (or Change level) › **Save access**. Recruitment also assigns a level at onboarding (“Member” by default), and the community sets the role given to newcomers.

> **Rank ≠ job ≠ access level.** A captain may have no management rights at all; a corporal clerk may manage personnel.

---

## 03 — Checking before you get it wrong
ATHENA has no single rights-simulation screen; you check with the tool built for each area:
| What you want to check | Tool |
|---|---|
| Who holds a level | **View holders** (Roles) |
| What a given member can do in the Academy | `/academy/droits` › **View as…** › Show their rights |
| What a profile sees in the ORBAT | `/orbat` › **View as** |
| What a reader sees of a document | **View as a reader** (Doctrine) |
| Who changed a right | **Audit log** (`/back-office/audit`) |

Requests for higher rights go through **HR files › Elevated access requests**; **Security and blocks** (`/back-office/security-indicators`) shows blocked accounts and security alerts; **Intelligence access** (`/back-office/renseignement/acces`) separately controls access to intelligence content.

:::warning Common mistakes
- Granting everything “to save time”: a broad level exposes members’ data.
- One level per person: build levels per **management role** (recruiter, clerk, instructor).
- Not checking: after a change, verify with View holders or View as….
:::

:::tip Key points
- Rights = **access level**, set in Roles and granted on the member file.
- Start from a **template**, grant the minimum needed, remove the level when the role ends.
- Check with View holders, View as… and the Audit log.
:::
EN;
        $demoFr = <<<'FR'
## Démonstration — Un niveau « Recruteur »
:::etapes
**ACCÈS › Rôles et droits › Rôles** › **Nouveau niveau** : « Recruteur », à partir du modèle le plus proche › Créer.
Cochez la gestion du recrutement, laissez décochés la configuration et les paiements › **Enregistrer ce niveau**.
Sur la fiche du caporal Lima, **Attribuer l’accès** › « Recruteur » › **Enregistrer l’accès**.
**Voir les titulaires** : Lima apparaît. Lima se reconnecte : **EFFECTIFS › Recrutement** apparaît dans son aside, pas la section SYSTÈME.
:::
:::analyse Le bon réflexe
Lima garde son grade et son emploi ; seul son niveau d’accès change. Quand il quitte la cellule recrutement, on lui rend son niveau précédent par le même écran. Le Journal d’audit garde la trace de l’attribution.
:::
FR;
        $demoEn = <<<'EN'
## Demonstration — A “Recruiter” level
:::steps
**ACCESS › Roles and permissions › Roles** › **New level**: “Recruiter”, from the closest template › Create.
Tick recruitment management, leave settings and payments unticked › **Save this level**.
On Corporal Lima’s file, **Grant access** › “Recruiter” › **Save access**.
**View holders**: Lima is listed. Lima signs in again: **PERSONNEL › Recruitment** appears in his sidebar, the SYSTEM section does not.
:::
:::analysis The right habit
Lima keeps his rank and job; only his access level changes. When he leaves the recruitment cell, the same screen gives him back his previous level. The Audit log keeps a record of the grant.
:::
EN;

        return C::chapter(
            ['Chapitre 9 — Droits d’accès et vérification', 'Chapter 9 — Access rights and checking them'],
            [$fr, $en],
            [$demoFr, $demoEn],
            C::stage(
                C::q('Un capitaine nouvellement promu demande pourquoi il ne voit pas la configuration. Quelle réponse est juste ?', 'A newly promoted captain asks why he cannot see settings. Which answer is right?',
                    [C::o('Le grade ne donne pas de droits ; il faut un niveau d’accès qui l’inclut.', 'Rank gives no rights; he needs an access level that includes it.'), C::o('Il faut attendre la fin de la campagne.', 'He must wait until the campaign ends.'), C::o('Il doit changer de langue.', 'He must change language.')], 0,
                    'Les droits viennent du **niveau d’accès** réglé dans Rôles et attribué sur la fiche, indépendamment du grade.',
                    'Rights come from the **access level** set in Roles and granted on the file, regardless of rank.'),
                ['Lendemain de la commission d’avancement.', 'The day after the promotion board.'],
                ['Grade, emploi, niveau d’accès : trois choses distinctes.', 'Rank, job, access level: three separate things.']
            ),
            C::stage(
                C::q('Vous voulez confirmer qui a reçu le niveau « Recruteur » le mois dernier, et par qui. Où regarder ?', 'You want to confirm who was given the “Recruiter” level last month, and by whom. Where do you look?',
                    [C::o('Mes documents.', 'My documents.'), C::o('Indicateurs d’usage.', 'Usage indicators.'), C::o('Voir les titulaires, puis le Journal d’audit.', 'View holders, then the Audit log.')], 2,
                    '**Voir les titulaires** donne la liste actuelle ; le **Journal d’audit** dit qui a fait l’attribution et quand.',
                    '**View holders** gives the current list; the **Audit log** says who made the grant and when.'),
                ['Contrôle trimestriel des accès.', 'Quarterly access review.']
            ),
            [
                C::q('Où règle-t-on les niveaux d’accès ?', 'Where are access levels set up?', [C::o('Dans Paramètres du compte.', 'In account Settings.'), C::o('ACCÈS › Rôles et droits › Rôles.', 'ACCESS › Roles and permissions › Roles.'), C::o('Dans l’ORBAT.', 'In the ORBAT.')], 1,
                    'L’écran « Niveaux d’accès » est `/back-office/ressources/effectifs/roles`.', 'The “Access levels” screen is `/back-office/ressources/effectifs/roles`.'),
                C::q('Vous avez modifié un niveau et voulez annuler tous vos changements. Quel bouton ?', 'You edited a level and want to undo all your changes. Which button?', [C::o('Rétablir le modèle de départ.', 'Restore the starting template.'), C::o('Supprimer ce niveau.', 'Delete this level.'), C::o('Tout cocher.', 'Tick all.')], 0,
                    '**Rétablir le modèle de départ** remet le niveau dans l’état de son modèle ; le supprimer retirerait l’accès à tous ses titulaires.', '**Restore the starting template** puts the level back to its template; deleting it would remove access from all its holders.'),
                C::q('Comment savoir ce qu’un membre précis peut faire dans l’Academy ?', 'How do you know what a given member can do in the Academy?', [C::o('Lui demander.', 'Ask them.'), C::o('Regarder son emploi.', 'Look at their job.'), C::o('/academy/droits › Voir comme… › Afficher ses droits.', '/academy/droits › View as… › Show their rights.')], 2,
                    '**Voir comme…** affiche ses droits cours par cours.', '**View as…** shows their rights course by course.'),
                C::q('Quelle bonne pratique pour créer les niveaux ?', 'What is good practice when creating levels?', [C::o('Un niveau par personne.', 'One level per person.'), C::o('Un niveau par fonction de gestion, à partir d’un modèle.', 'One level per management role, starting from a template.'), C::o('Un seul niveau qui donne tout.', 'A single level that grants everything.')], 1,
                    'Des niveaux par fonction (recruteur, secrétaire, instructeur), partis d’un modèle, restent lisibles et minimaux.', 'Levels per role (recruiter, clerk, instructor), built from a template, stay readable and minimal.'),
                C::q('Où voit-on les comptes bloqués et les alertes de sécurité ?', 'Where do you see blocked accounts and security alerts?', [C::o('Sécurité et blocages.', 'Security and blocks.'), C::o('Matricules.', 'Service numbers.'), C::o('Calendrier.', 'Calendar.')], 0,
                    '**Sécurité et blocages** (`/back-office/security-indicators`), section ACCÈS.', '**Security and blocks** (`/back-office/security-indicators`), ACCESS section.'),
            ],
            ['Décrivez les niveaux d’accès dont votre communauté a besoin (au moins trois), ce que chacun ouvre et comment vous vérifieriez une attribution.',
             'Describe the access levels your community needs (at least three), what each one opens and how you would check a grant.'],
            [['Au moins trois niveaux par fonction.', 'At least three role-based levels.'], ['Principe du minimum utile.', 'Least-privilege principle.'], ['Outil de vérification cité.', 'Checking tool named.']],
            [self::PASS, ['Le stagiaire crée un niveau à partir d’un modèle et l’attribue à un membre.', 'The learner creates a level from a template and grants it to a member.'], ['Il vérifie avec Voir les titulaires ou Voir comme….', 'They check with View holders or View as….']],
            ['Fiche mémo — Droits d’accès', 'Memo card — Access rights',
             "Niveaux : /back-office/ressources/effectifs/roles (Nouveau niveau, Enregistrer ce niveau, Voir les titulaires)\nAttribuer : fiche du membre › Attribuer l’accès › Enregistrer l’accès\nVérifier : Voir les titulaires · /academy/droits (Voir comme…) · /orbat (Voir comme) · /back-office/audit\nSécurité : /back-office/security-indicators",
             "Levels: /back-office/ressources/effectifs/roles (New level, Save this level, View holders)\nGrant: member file › Grant access › Save access\nCheck: View holders · /academy/droits (View as…) · /orbat (View as) · /back-office/audit\nSecurity: /back-office/security-indicators"]
        );
    }
}
