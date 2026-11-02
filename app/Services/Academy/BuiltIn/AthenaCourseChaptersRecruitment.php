<?php

declare(strict_types=1);

namespace App\Services\Academy\BuiltIn;

use App\Services\Academy\BuiltIn\AthenaPlatformCourse as C;

/**
 * Formation ATHENA : le circuit réel du recrutement, de la page publique à l’intégration dans l’unité
 * (EnlistmentController, EnlistmentStatusCatalog, écran d’intégration, parcours d’accueil, tâches planifiées).
 * Adresses entre accents graves : vérifiées par tests/Unit/AcademyAthenaCourseTest.php.
 */
final class AthenaCourseChaptersRecruitment
{
    private const PASS = ['L’évaluation atteint au moins 80 % dans le délai prévu.', 'The assessment reaches at least 80% within the time limit.'];

    // ------------------------------------------------------------------ Chapitre 10 — De la vitrine à la décision

    /** @return array<string, mixed> */
    public static function pipeline(): array
    {
        $fr = <<<'FR'
## Le circuit en un coup d’œil
:::etapes
Le visiteur trouve la communauté (annuaire, vitrine, offre, ou code communauté).
Il dépose sa candidature ; elle arrive « À traiter », étape **Réception**.
Un recruteur se désigne, instruit, propose éventuellement un entretien, puis **décide**.
Acceptée, la candidature passe « Intégration à finaliser » : le chapitre 11 prend le relais.
:::

## 01 — Côté public : comment on arrive
- **Annuaire** `/communities` (« Communautés & unités ») et **vitrine** de chaque communauté `/c/…` ; le bouton s’appelle **Candidater** (ou Rejoindre selon le public visé). Une **offre** publiée a sa page `/c/…/avis/…`.
- **Recrutement sur Athena** (`/recrutement`) explique comment postuler.
- **Rejoindre avec un code** (`/join`) : le visiteur saisit le **code communauté** › Continuer ; sans compte, il est envoyé vers la création de compte.

## 02 — Le formulaire
La communauté choisit son **Type de candidature** dans **COMMUNAUTÉ › Vitrine et portail › Paramètres**, écran inscription (`/back-office/community/inscription`), bloc « Arrivée des candidats » :
| Type | Effet |
|---|---|
| **Dossier MilSim complet** | identité et contact, matériel et expérience (PC, micro, ACE / ACRE), motivation, engagement |
| **Formulaire court** | l’essentiel seulement |
| **Recrutement via Discord** | lien d’invitation Discord et questions réglées dans `/back-office/recruitments/discord-questions` |

Le même écran règle le **rôle donné aux nouveaux arrivants**. Le candidat peut saisir un **code d’invitation prioritaire** (gérés dans `/back-office/recruitments/codes-invitation`) ; un code peut rattacher le dossier à une offre, ou accepter automatiquement la candidature. Après « Envoyer ma candidature », il voit « Candidature enregistrée » et reçoit le lien de son **portail de suivi** (« Votre dossier ») : fil de messages, pièces jointes, et à la fin son ressenti sur le processus.

---

## 03 — Côté recruteur
**EFFECTIFS › Recrutement › Candidatures** (`/back-office/recruitments`) : la file des candidatures, avec *Mes dossiers*, *Sans recruteur*, *Entretiens*, *En retard*. **Me désigner**, puis **Consulter le dossier**. Les offres se publient dans **Offres publiées** (`/back-office/recruitment/offers`), les modèles de réponse dans `/back-office/recruitments/messages-prefaits`, les statistiques dans `/back-office/ressources/recrutement/analyses`.

| Statut | Signification |
|---|---|
| **À traiter** | en cours (réception, instruction, entretien planifié, mis en attente) |
| **Acceptée** | décision positive, intégration à finaliser |
| **Refusée** | refus motivé |
| **Non admis** | refus ferme |
| **Annulée** | dossier retiré |

Bloc **Décision à enregistrer** : *Accepter et prévenir*, *Mettre en attente et prévenir*, *Proposer l’entretien et prévenir* (avec un créneau proposé), *Refuser et prévenir*, *Marquer non admis et prévenir*. Chaque décision envoie un e-mail au candidat et s’ajoute au fil de son portail. Pour un recrutement Discord, une fiche d’entretien note la date, les notes et une grille (motivation, communication, disponibilité, comportement, adéquation technique).

:::attention Erreurs fréquentes
- Répondre au candidat en dehors du site : la décision n’est ni tracée ni visible sur son portail.
- Laisser des dossiers **Sans recruteur** : ils glissent dans *En retard*.
- Accepter quand le quota de membres de la formule est atteint : l’acceptation est bloquée.
:::

:::astuce À retenir
- Entrées : `/communities`, vitrine, offre, `/join` avec code.
- Le type de candidature et le rôle des nouveaux arrivants se règlent dans l’écran inscription.
- **Me désigner**, instruire, puis une décision « … et prévenir ».
:::
FR;
        $en = <<<'EN'
## The pipeline at a glance
:::steps
The visitor finds the community (directory, showcase, offer, or community code).
They submit an application; it arrives as “To handle”, stage **Received**.
A recruiter assigns themself, reviews, possibly offers an interview, then **decides**.
Once accepted, the application moves to “Onboarding to finalise”: chapter 11 takes over.
:::

## 01 — Public side: how people arrive
- The **directory** `/communities` (“Communities & units”) and each community’s **showcase** `/c/…`; the button reads **Apply** (or Join, depending on the audience). A published **offer** has its own page `/c/…/avis/…`.
- **Recruitment on Athena** (`/recrutement`) explains how to apply.
- **Join with a code** (`/join`): the visitor types the **community code** › Continue; without an account, they are sent to sign-up.

## 02 — The form
The community picks its **Application type** under **COMMUNITY › Showcase and portal › Settings**, sign-up screen (`/back-office/community/inscription`), “Candidate arrival” block:
| Type | Effect |
|---|---|
| **Full MilSim file** | identity and contact, kit and experience (PC, microphone, ACE / ACRE), motivation, commitment |
| **Short form** | the essentials only |
| **Recruitment via Discord** | Discord invite link and questions set in `/back-office/recruitments/discord-questions` |

The same screen sets the **role given to newcomers**. Applicants may enter a **priority invitation code** (managed in `/back-office/recruitments/codes-invitation`); a code can tie the file to an offer, or accept the application automatically. After “Submit my application” they see “Application saved” and receive the link to their **tracking portal** (“Your application”): message thread, attachments and, at the end, feedback on the process.

---

## 03 — Recruiter side
**PERSONNEL › Recruitment › Applications** (`/back-office/recruitments`): the application queue, with *My files*, *No recruiter*, *Interviews*, *Overdue*. **Assign me**, then **View file**. Offers are published in **Published offers** (`/back-office/recruitment/offers`), reply templates live in `/back-office/recruitments/messages-prefaits`, statistics in `/back-office/ressources/recrutement/analyses`.

| Status | Meaning |
|---|---|
| **To handle** | in progress (received, under review, interview scheduled, on hold) |
| **Accepted** | positive decision, onboarding to finalise |
| **Declined** | reasoned refusal |
| **Not admitted** | firm refusal |
| **Cancelled** | withdrawn file |

The **Decision to record** block: *Accept and notify*, *Put on hold and notify*, *Propose the interview and notify* (with a proposed time slot), *Reject and notify*, *Mark as not admitted and notify*. Each decision e-mails the applicant and is added to their portal thread. For Discord recruitment, an interview sheet records the date, notes and a scoring grid (motivation, communication, availability, behaviour, technical fit).

:::warning Common mistakes
- Answering the applicant outside the site: the decision is neither recorded nor visible on their portal.
- Leaving files **No recruiter**: they slide into *Overdue*.
- Accepting when the plan’s member quota is reached: acceptance is blocked.
:::

:::tip Key points
- Ways in: `/communities`, showcase, offer, `/join` with a code.
- Application type and newcomer role are set on the sign-up screen.
- **Assign me**, review, then an “… and notify” decision.
:::
EN;
        $demoFr = <<<'FR'
## Démonstration — La candidature de « Kilo »
:::etapes
Kilo trouve la 2e compagnie dans `/communities`, ouvre sa vitrine et clique **Candidater**. Il remplit le dossier MilSim et clique « Envoyer ma candidature ».
Le recruteur Lima ouvre **EFFECTIFS › Recrutement › Candidatures** : le dossier est dans *Sans recruteur*. **Me désigner**, puis **Consulter le dossier**.
Dans **Décision à enregistrer** : *Proposer l’entretien et prévenir*, créneau « jeudi 21 h », texte tiré d’un modèle. Le dossier passe « Entretien planifié ».
Après l’entretien vocal, Lima choisit **Accepter et prévenir**. Kilo reçoit l’e-mail ; son portail affiche le message.
:::
:::analyse Ce qui a été tracé
Chaque étape — désignation, entretien proposé, acceptation — figure dans la chronologie du dossier et dans le fil du portail du candidat. Rien ne s’est passé « hors site ».
:::
FR;
        $demoEn = <<<'EN'
## Demonstration — “Kilo”’s application
:::steps
Kilo finds 2nd Company in `/communities`, opens its showcase and clicks **Apply**. He fills in the MilSim file and clicks “Submit my application”.
Recruiter Lima opens **PERSONNEL › Recruitment › Applications**: the file sits in *No recruiter*. **Assign me**, then **View file**.
In **Decision to record**: *Propose the interview and notify*, slot “Thursday 21:00”, text taken from a template. The file moves to “Interview scheduled”.
After the voice interview, Lima chooses **Accept and notify**. Kilo gets the e-mail; his portal shows the message.
:::
:::analysis What was recorded
Every step — assignment, interview offer, acceptance — appears in the file’s timeline and in the applicant’s portal thread. Nothing happened “off-site”.
:::
EN;

        return C::chapter(
            ['Chapitre 10 — Recrutement : de la vitrine à la décision', 'Chapter 10 — Recruitment: from showcase to decision'],
            [$fr, $en],
            [$demoFr, $demoEn],
            C::stage(
                C::q('Vous voulez rencontrer un candidat avant de décider. Quelle décision enregistrer ?', 'You want to meet an applicant before deciding. Which decision do you record?',
                    [C::o('Accepter et prévenir.', 'Accept and notify.'), C::o('Mettre en attente sans rien dire.', 'Put on hold without saying anything.'), C::o('Proposer l’entretien et prévenir, avec un créneau.', 'Propose the interview and notify, with a time slot.')], 2,
                    'La candidature reste « À traiter », passe à l’étape **Entretien planifié**, et le candidat reçoit le créneau par e-mail et sur son portail.',
                    'The application stays “To handle”, moves to the **Interview scheduled** stage, and the applicant receives the slot by e-mail and on their portal.'),
                ['Dossier MilSim prometteur, mais l’expérience ACRE est floue.', 'A promising MilSim file, but the ACRE experience is unclear.'],
                ['Chaque décision « … et prévenir » informe le candidat.', 'Every “… and notify” decision informs the applicant.']
            ),
            C::stage(
                C::q('Votre unité recrute surtout par Discord. Où le régler ?', 'Your unit mostly recruits through Discord. Where do you set this?',
                    [C::o('Type de candidature « Recrutement via Discord », écran inscription de la communauté.', 'Application type “Recruitment via Discord”, the community sign-up screen.'), C::o('Dans les codes d’invitation.', 'In invitation codes.'), C::o('Dans le Studio Academy.', 'In the Academy Studio.')], 0,
                    'Le **Type de candidature** se choisit dans `/back-office/community/inscription` ; les questions Discord se règlent à part.',
                    'The **Application type** is chosen in `/back-office/community/inscription`; Discord questions are set separately.'),
                ['Le serveur Discord de l’unité est déjà très actif.', 'The unit’s Discord server is already very active.']
            ),
            [
                C::q('Où un visiteur saisit-il un code communauté ?', 'Where does a visitor type a community code?', [C::o('/join', '/join'), C::o('/lier', '/lier'), C::o('/account', '/account')], 0,
                    '**Rejoindre avec un code** (`/join`) ; `/lier` sert à lier le jeu.', '**Join with a code** (`/join`); `/lier` is for linking the game.'),
                C::q('Que signifie le statut « Non admis » ?', 'What does the “Not admitted” status mean?', [C::o('Dossier en attente.', 'File on hold.'), C::o('Refus ferme, dossier clos.', 'Firm refusal, file closed.'), C::o('Entretien proposé.', 'Interview offered.')], 1,
                    '« Non admis » clôt le dossier, comme « Refusée » et « Annulée ».', '“Not admitted” closes the file, like “Declined” and “Cancelled”.'),
                C::q('Un dossier n’a pas de recruteur. Quel est le premier geste ?', 'A file has no recruiter. What is the first step?', [C::o('Le refuser.', 'Decline it.'), C::o('Attendre une semaine.', 'Wait a week.'), C::o('Me désigner.', 'Assign me.')], 2,
                    '**Me désigner** attribue le dossier ; il quitte *Sans recruteur* et entre dans *Mes dossiers*.', '**Assign me** takes the file; it leaves *No recruiter* and enters *My files*.'),
                C::q('Où le candidat suit-il son dossier ?', 'Where does the applicant follow their file?', [C::o('Sur son portail de suivi « Votre dossier ».', 'On their tracking portal “Your application”.'), C::o('Dans l’ORBAT.', 'In the ORBAT.'), C::o('Nulle part.', 'Nowhere.')], 0,
                    'Le portail de suivi affiche le fil de messages, les pièces jointes et les décisions.', 'The tracking portal shows the message thread, attachments and decisions.'),
                C::q('Où publier une offre pour un poste précis ?', 'Where do you publish an offer for a specific position?', [C::o('Dans Mes documents.', 'In My documents.'), C::o('Offres publiées (/back-office/recruitment/offers).', 'Published offers (/back-office/recruitment/offers).'), C::o('Dans l’Espace aérien.', 'In Airspace.')], 1,
                    'Une offre précise l’unité et l’emploi ; elle apparaît sur la vitrine et préremplit l’intégration.', 'An offer specifies the unit and job; it appears on the showcase and pre-fills onboarding.'),
            ],
            ['Décrivez le circuit de recrutement de votre communauté : type de candidature, qui se désigne, quand vous proposez un entretien et quelle décision vous enregistrez dans quel cas.',
             'Describe your community’s recruitment pipeline: application type, who assigns themselves, when you offer an interview and which decision you record in which case.'],
            [['Type de candidature justifié.', 'Application type justified.'], ['Rôle du recruteur décrit.', 'Recruiter’s role described.'], ['Décisions et leurs effets.', 'Decisions and their effects.']],
            [self::PASS, ['Le stagiaire traite un dossier de test jusqu’à une décision « … et prévenir ».', 'The learner handles a test file through to an “… and notify” decision.'], ['Il cite les cinq statuts et leur sens.', 'They name the five statuses and their meaning.']],
            ['Fiche mémo — Recrutement (1)', 'Memo card — Recruitment (1)',
             "Public : /communities · /c/… (Candidater) · /recrutement · /join (code communauté)\nRéglages : /back-office/community/inscription (type de candidature, rôle des nouveaux arrivants)\nCodes prioritaires : /back-office/recruitments/codes-invitation\nCandidatures : /back-office/recruitments (Me désigner, Consulter le dossier)\nStatuts : À traiter · Acceptée · Refusée · Non admis · Annulée",
             "Public: /communities · /c/… (Apply) · /recrutement · /join (community code)\nSettings: /back-office/community/inscription (application type, newcomer role)\nPriority codes: /back-office/recruitments/codes-invitation\nApplications: /back-office/recruitments (Assign me, View file)\nStatuses: To handle · Accepted · Declined · Not admitted · Cancelled"]
        );
    }

    // ------------------------------------------------------------------ Chapitre 11 — Intégration

    /** @return array<string, mixed> */
    public static function integration(): array
    {
        $fr = <<<'FR'
## Après « Accepter » : l’écran d’intégration
Accepter ne suffit pas : le site ouvre **Intégrer …** (`/back-office/recruitments/…/onboarding`). Tant qu’il n’est pas finalisé, le dossier reste « Intégration à finaliser ».

| Onglet | À remplir |
|---|---|
| **Identité** | identité du personnage (prénom, nom, indicatif radio, surnom), état civil, **date d’engagement** (point de départ de l’ancienneté) |
| **Steam & Arma** | profil Steam |
| **Accès** | **niveau d’accès** (« Membre » par défaut) et **position de service** : *En formation* (recommandé) ou *En service actif* |
| **Affectation** | **unité** (préremplie depuis l’offre, ou « Choisir plus tard »), **grade d’entrée** (choisi dans la liste, ou « Aucun grade » ; lien « Gérer les grades »), **emploi** (qui n’ouvre aucun droit), **référent d’intégration** |

Puis **Finaliser l’intégration**. Le site crée ou rattache le compte (un nouveau compte reçoit un lien pour choisir son mot de passe, valable 72 h), applique niveau d’accès, grade et affectation, **attribue le matricule** selon les règles de `/back-office/organisation/matricules`, calcule l’ancienneté. Le dossier passe **Intégré**.

> Le grade d’entrée n’est jamais automatique : c’est une décision de l’encadrement, prise sur cet écran.

---

## Les premières semaines
- **Parcours d’accueil** : s’il est activé (« Ouvrir un parcours d’accueil à la création du compte », dans **Réglages RH** `/back-office/ressources/effectifs/reglages`), un parcours s’ouvre. L’encadrement le suit dans **Intégration des recrues** (`/back-office/integration-membres`, modèles dans `/back-office/integration-membres/modeles`) ; la recrue le voit dans **Mon intégration** (`/mon-integration`). Étapes possibles : tâche, vérification du dossier, entretien, invitation à un événement, document à lire, **formation facultative**, validation manuelle…
- **Formation initiale** : aucune inscription Academy n’est automatique à l’acceptation. Pour l’imposer, ajoutez au modèle de parcours une étape **Formation facultative** liée au cours (la recrue y est alors inscrite), ou inscrivez-la depuis l’**espace instructeur** de l’Academy.
- **Période d’essai** : réglée dans le suivi roleplay (`/back-office/roleplay-followup?onglet=immersion`, parcours d’immersion) — durée de référence (60 jours par défaut), alerte, libellé du bilan d’essai. Elle court depuis la date d’engagement ; c’est un repère et une alerte, sans changement de statut automatique.
- **Lien pour compléter son profil** : depuis la fiche du membre, l’encadrement crée un lien valable 24 ou 48 h (liste dans `/back-office/ressources/effectifs/invitations`) ; la recrue l’ouvre (« Compléter mon profil », « Activer mon compte »).

## Rappels automatiques
- **Bilan à 30 jours** : un rappel unique au candidat et à l’équipe, un mois après l’acceptation.
- **Intégration** : les étapes du parcours en retard sont relancées chaque jour.
- **Résumé hebdomadaire RH** : dossiers incomplets, membres sans unité ou sans rôle.
- **Petites communautés** : une communauté restée à un ou deux membres reçoit un rappel à 3 mois puis 4 mois.

Il n’existe pas de relance automatique des comptes inactifs : suivez-les par la **Disponibilité** (activité récente) et les **Départs**.

:::astuce À retenir
- Accepter › **Intégrer** (identité, Steam, accès, affectation) › **Finaliser l’intégration**.
- Grade d’entrée, unité et emploi se choisissent à l’intégration ; le matricule suit les règles.
- Formation initiale = étape « Formation facultative » du parcours d’accueil, ou inscription par un instructeur.
:::
FR;
        $en = <<<'EN'
## After “Accept”: the onboarding screen
Accepting is not enough: the site opens **Onboard …** (`/back-office/recruitments/…/onboarding`). Until it is completed, the file stays “Onboarding to finalise”.

| Tab | What to fill in |
|---|---|
| **Identity** | character identity (first name, last name, radio callsign, nickname), civil details, **enlistment date** (the starting point for seniority) |
| **Steam & Arma** | Steam profile |
| **Access** | **access level** (“Member” by default) and **duty position**: *In training* (recommended) or *On active duty* |
| **Assignment** | **unit** (pre-filled from the offer, or “Choose later”), **entry rank** (picked from the list, or “No rank”; “Manage ranks” link), **job** (which grants no rights), **integration mentor** |

Then **Complete onboarding**. The site creates or links the account (a new account gets a link to set its password, valid for 72 hours), applies access level, rank and assignment, **assigns the service number** following the rules in `/back-office/organisation/matricules`, and works out seniority. The file becomes **Onboarded**.

> The entry rank is never automatic: it is a staff decision, made on this screen.

---

## The first weeks
- **Welcome path**: if it is switched on (“Open an onboarding pathway at account creation”, under **HR settings** `/back-office/ressources/effectifs/reglages`), a pathway opens. Staff follow it in **Recruit onboarding** (`/back-office/integration-membres`, templates in `/back-office/integration-membres/modeles`); the recruit sees it in **My onboarding** (`/mon-integration`). Possible steps: task, file check, interview, event invitation, document to read, **optional course**, manual validation…
- **Initial training**: no Academy enrolment happens automatically on acceptance. To require one, add an **optional course** step linked to the course in the pathway template (the recruit is then enrolled), or enrol them from the Academy **instructor space**.
- **Probation period**: set in roleplay tracking (`/back-office/roleplay-followup?onglet=immersion`, immersion path) — reference duration (60 days by default), alert, probation review label. It runs from the enlistment date; it is a marker and an alert, with no automatic status change.
- **Complete-your-profile link**: from the member file, staff create a link valid for 24 or 48 hours (listed in `/back-office/ressources/effectifs/invitations`); the recruit opens it (“Complete my profile”, “Activate my account”).

## Automatic reminders
- **30-day review**: a single reminder to the applicant and the team, one month after acceptance.
- **Onboarding**: overdue pathway steps are chased every day.
- **Weekly HR digest**: incomplete files, members without a unit or role.
- **Small communities**: a community still at one or two members gets a reminder at 3 months, then 4 months.

There is no automatic reminder for inactive accounts: track them through **Availability** (recent activity) and **Departures**.

:::tip Key points
- Accept › **Onboard** (identity, Steam, access, assignment) › **Complete onboarding**.
- Entry rank, unit and job are chosen at onboarding; the service number follows the rules.
- Initial training = an “optional course” step in the welcome path, or enrolment by an instructor.
:::
EN;
        $demoFr = <<<'FR'
## Démonstration — Intégrer Kilo
:::etapes
Après « Accepter et prévenir », le site ouvre **Intégrer Kilo**. Onglet **Identité** : prénom et nom du personnage, indicatif « Kilo 2-3 », date d’engagement du jour.
Onglet **Steam & Arma** : son profil Steam. Onglet **Accès** : niveau « Membre », position **En formation**.
Onglet **Affectation** : unité « 3e section » (venue de l’offre), grade **Soldat**, emploi « Fusilier », référent le caporal Lima › **Finaliser l’intégration**.
Kilo reçoit le lien pour choisir son mot de passe ; son matricule « 2RIMA-0142 » est attribué. Le parcours d’accueil s’ouvre : lire le règlement, entretien avec Lima, et l’étape **Formation facultative** « ATHENA — Utiliser et administrer la plateforme ».
Un mois plus tard, le rappel du bilan à 30 jours arrive à Kilo et à l’équipe.
:::
:::analyse Ce qu’on vérifie ensuite
Dans `/orbat`, Kilo occupe un poste de la 3e section ; dans **Ma fiche**, il voit grade, unité et matricule ; dans **Mon intégration**, ses étapes.
:::
FR;
        $demoEn = <<<'EN'
## Demonstration — Onboarding Kilo
:::steps
After “Accept and notify”, the site opens **Onboard Kilo**. **Identity** tab: character first and last name, callsign “Kilo 2-3”, today’s enlistment date.
**Steam & Arma** tab: his Steam profile. **Access** tab: level “Member”, position **In training**.
**Assignment** tab: unit “3rd Platoon” (from the offer), rank **Private**, job “Rifleman”, mentor Corporal Lima › **Complete onboarding**.
Kilo receives the link to set his password; his service number “2RIMA-0142” is assigned. The welcome path opens: read the rules, interview with Lima, and the **optional course** step “ATHENA — Using and running the platform”.
A month later, the 30-day review reminder reaches Kilo and the team.
:::
:::analysis What to check next
In `/orbat`, Kilo holds a 3rd Platoon position; in **My file**, he sees rank, unit and service number; in **My onboarding**, his steps.
:::
EN;

        return C::chapter(
            ['Chapitre 11 — Recrutement : intégration, période d’essai et suivi', 'Chapter 11 — Recruitment: onboarding, probation and follow-up'],
            [$fr, $en],
            [$demoFr, $demoEn],
            C::stage(
                C::q('Où choisit-on le grade d’entrée d’une recrue ?', 'Where is a recruit’s entry rank chosen?',
                    [C::o('Il est calculé automatiquement.', 'It is calculated automatically.'), C::o('Onglet Affectation de l’écran d’intégration, liste Grade.', 'Assignment tab of the onboarding screen, Rank list.'), C::o('Dans le formulaire de candidature.', 'In the application form.')], 1,
                    'Le grade d’entrée est une décision de l’encadrement, prise sur l’écran d’intégration (« Aucun grade » possible).',
                    'The entry rank is a staff decision made on the onboarding screen (“No rank” is possible).'),
                ['Kilo vient d’être accepté.', 'Kilo has just been accepted.'],
                ['Unité, grade et emploi sont dans le même onglet.', 'Unit, rank and job are on the same tab.']
            ),
            C::stage(
                C::q('Vous voulez que chaque recrue suive la formation ATHENA dès son arrivée. Comment faire ?', 'You want every recruit to take the ATHENA course on arrival. How?',
                    [C::o('Rien à faire : c’est automatique à l’acceptation.', 'Nothing: it is automatic on acceptance.'), C::o('L’indiquer dans le texte de l’e-mail.', 'Mention it in the e-mail text.'), C::o('Ajouter une étape Formation facultative liée au cours dans le modèle de parcours d’accueil.', 'Add an optional course step linked to the course in the welcome path template.')], 2,
                    'Aucune inscription n’est automatique à l’acceptation ; l’étape **Formation facultative** du parcours d’accueil inscrit la recrue au cours lié.',
                    'No enrolment happens automatically on acceptance; the welcome path’s **optional course** step enrols the recruit in the linked course.'),
                ['Le commandement veut une formation initiale commune.', 'Command wants a shared initial training.']
            ),
            [
                C::q('Quel bouton termine l’intégration ?', 'Which button completes onboarding?', [C::o('Finaliser l’intégration.', 'Complete onboarding.'), C::o('Accepter et prévenir.', 'Accept and notify.'), C::o('Publier.', 'Publish.')], 0,
                    '**Finaliser l’intégration** crée ou rattache le compte et applique accès, grade, unité et matricule.', '**Complete onboarding** creates or links the account and applies access, rank, unit and service number.'),
                C::q('Quelle position de service est recommandée pour une recrue ?', 'Which duty position is recommended for a recruit?', [C::o('En service actif.', 'On active duty.'), C::o('Aucune.', 'None.'), C::o('En formation.', 'In training.')], 2,
                    'L’écran marque **En formation** comme recommandé pour un nouvel arrivant.', 'The screen marks **In training** as recommended for a newcomer.'),
                C::q('La période d’essai change-t-elle seule le statut du membre ?', 'Does the probation period change the member’s status by itself?', [C::o('Non : c’est un repère et une alerte, réglés dans le suivi roleplay.', 'No: it is a marker and an alert, set in roleplay tracking.'), C::o('Oui, après 60 jours.', 'Yes, after 60 days.'), C::o('Oui, elle supprime le compte.', 'Yes, it deletes the account.')], 0,
                    'La période d’essai (60 jours par défaut) alerte l’encadrement ; la décision reste humaine.', 'The probation period (60 days by default) alerts staff; the decision remains a human one.'),
                C::q('Combien de temps reste valable un lien « compléter son profil » ?', 'How long is a “complete your profile” link valid?', [C::o('Une heure.', 'One hour.'), C::o('24 ou 48 heures.', '24 or 48 hours.'), C::o('Un an.', 'One year.')], 1,
                    'Le lien est créé pour 24 ou 48 h, et peut être prolongé, régénéré ou révoqué.', 'The link is created for 24 or 48 hours and can be extended, regenerated or revoked.'),
                C::q('Comment suivre les comptes devenus inactifs ?', 'How do you follow accounts that have gone inactive?', [C::o('Une relance automatique part chaque semaine.', 'An automatic reminder goes out weekly.'), C::o('Ils sont supprimés automatiquement.', 'They are deleted automatically.'), C::o('Par la Disponibilité (activité récente) et les Départs : il n’y a pas de relance automatique.', 'Through Availability (recent activity) and Departures: there is no automatic reminder.')], 2,
                    'Le site relance le bilan à 30 jours et les étapes d’intégration, pas les comptes inactifs : l’encadrement les repère lui-même.', 'The site chases the 30-day review and onboarding steps, not inactive accounts: staff spot them themselves.'),
            ],
            ['Décrivez l’intégration d’une recrue dans votre communauté : ce que vous remplissez dans chaque onglet, le grade d’entrée choisi et pourquoi, et le contenu de son parcours d’accueil.',
             'Describe how you would onboard a recruit in your community: what you fill in on each tab, the entry rank and why, and the contents of their welcome path.'],
            [['Quatre onglets couverts.', 'All four tabs covered.'], ['Grade d’entrée justifié.', 'Entry rank justified.'], ['Parcours d’accueil avec formation initiale.', 'Welcome path with initial training.']],
            [self::PASS, ['Le stagiaire finalise l’intégration d’un dossier de test (ou la décrit pas à pas devant l’instructeur).', 'The learner completes onboarding of a test file (or walks the instructor through it step by step).'], ['Il explique comment imposer une formation initiale.', 'They explain how to require an initial training course.']],
            ['Fiche mémo — Recrutement (2)', 'Memo card — Recruitment (2)',
             "Intégration : /back-office/recruitments/…/onboarding (Identité · Steam & Arma · Accès · Affectation) › Finaliser l’intégration\nParcours d’accueil : /back-office/integration-membres · recrue : /mon-integration\nActivation : Réglages RH /back-office/ressources/effectifs/reglages\nPériode d’essai : /back-office/roleplay-followup?onglet=immersion\nLiens profil : /back-office/ressources/effectifs/invitations (24 ou 48 h)",
             "Onboarding: /back-office/recruitments/…/onboarding (Identity · Steam & Arma · Access · Assignment) › Complete onboarding\nWelcome path: /back-office/integration-membres · recruit: /mon-integration\nSwitch on: HR settings /back-office/ressources/effectifs/reglages\nProbation: /back-office/roleplay-followup?onglet=immersion\nProfile links: /back-office/ressources/effectifs/invitations (24 or 48 h)"]
        );
    }
}
