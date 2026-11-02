<?php

declare(strict_types=1);

namespace App\Services\Academy\BuiltIn;

use App\Services\Academy\BuiltIn\AcademyCourseKit as K;

/**
 * Cours intégré « Ordre en cinq paragraphes (OPORD) » : procédures de commandement et ordre préparatoire (WARNO),
 * ordre d’opérations en cinq paragraphes (Situation, Mission, Execution, Service Support, Command and Signal ;
 * SMEAC), intention du chef, FRAGO, backbrief et répétitions. Inspiré du format public d’ordre utilisé par les armées
 * alliées et décrit dans les manuels publics de l’US Army (FM 5-0, ATP 3-21.8) ; formation de jeu Arma 3 (carte,
 * ATAK, plan de transmissions ACRE) sans valeur de qualification réelle.
 */
final class NatoFiveParagraphOrderCourse
{
    public const KEY = 'otan-ordre-cinq-paragraphes';
    public const VERSION = '1.0.0';

    /** @return array<string, mixed> */
    public static function course(): array
    {
        return K::course(
            self::KEY,
            self::VERSION,
            K::text('Ordre en cinq paragraphes (OPORD), WARNO et FRAGO', 'Five-Paragraph Order (OPORD), WARNO and FRAGO'),
            K::text(
                'Préparer et donner un ordre de mission clair dans nos missions Arma 3 : procédures de commandement et ordre préparatoire (WARNO), ordre d’opérations en cinq paragraphes (Situation, Mission, Execution, Service Support, Command and Signal), mission en « qui, quoi, quand, où, pourquoi », intention du chef, plan de transmissions ACRE, FRAGO, backbrief et répétitions, avec la carte et ATAK. Formation de jeu inspirée du format d’ordre allié public, sans valeur de qualification réelle.',
                'Prepare and deliver a clear mission order in our Arma 3 missions: troop leading procedures and the warning order (WARNO), the five-paragraph operation order (Situation, Mission, Execution, Service Support, Command and Signal), the mission as “who, what, when, where, why”, commander’s intent, the ACRE signal plan, FRAGO, backbrief and rehearsals, using the map and ATAK. In-game training inspired by the public allied order format, with no real-world qualification value.'
            ),
            120,
            [self::chapterOne(), self::chapterTwo(), self::chapterThree()],
            [
                'code' => 'ACAD-OTAN-OPORD',
                'license_code' => 'OPORD',
                'name' => K::text('Qualification Athena Academy — Ordres et briefing de mission (OPORD)', 'Athena Academy qualification — Orders and mission briefing (OPORD)'),
                'short_name' => 'OPORD',
                'description' => K::text(
                    'Le titulaire sait, en jeu, donner un ordre préparatoire (WARNO) dès réception d’une mission, puis un ordre en cinq paragraphes complet au niveau section, avec une mission en « qui, quoi, quand, où, pourquoi », une intention du chef et un plan de transmissions ACRE. Il conduit le backbrief et la répétition, puis modifie l’ordre par FRAGO en cours de mission. Qualification de jeu inspirée du format d’ordre allié public en cinq paragraphes, sans valeur réelle.',
                    'In the game, the holder can give a warning order (WARNO) as soon as a mission is received, then a complete five-paragraph order at section level, with a mission stated as “who, what, when, where, why”, a commander’s intent and an ACRE signal plan. They conduct the backbrief and rehearsal, then amend the order by FRAGO during the mission. In-game qualification inspired by the public allied five-paragraph order format, with no real-world value.'
                ),
                'reference' => K::text(
                    'Inspirée du format public d’ordre d’opérations en cinq paragraphes (SMEAC) employé par les armées alliées et décrit dans les manuels publics de l’US Army (FM 5-0, ATP 3-21.8)',
                    'Inspired by the public five-paragraph operation order format (SMEAC) used by allied armed forces and described in the public US Army manuals (FM 5-0, ATP 3-21.8)'
                ),
                'validity_months' => null,
            ]
        );
    }

    // ------------------------------------------------------------------ Chapitre 1 — TLP et WARNO

    /** @return array<string, mixed> */
    private static function chapterOne(): array
    {
        $theoryFr = <<<'FR'
OBJECTIF DE LA FORMATION
Ce cours apprend à préparer et à donner un ordre de mission dans nos missions Arma 3, au niveau du groupe et de la section. Il s’inspire du format public d’ordre d’opérations en cinq paragraphes employé, avec des variantes, par les armées alliées : 1 Situation, 2 Mission, 3 Execution, 4 Service Support (ou Sustainment), 5 Command and Signal. Trois chapitres : procédures de commandement et ordre préparatoire, les trois premiers paragraphes, puis le soutien, les transmissions, le FRAGO et la conduite du briefing en jeu. Les termes OTAN restent en anglais. Il s’agit d’une formation de jeu : la qualification Athena Academy délivrée atteste un parcours interne, sans valeur de qualification réelle, militaire ou autre.

01 — Pourquoi un format fixe
Avant le départ, vingt à quarante joueurs attendent des consignes et le temps manque. Un format fixe évite d’oublier une information et permet à chacun de savoir où la trouver. Les variantes publiques se ressemblent : l’acronyme SMEAC (Situation, Mission, Execution, Administration and Logistics, Command and Signal) dans le Corps des Marines américain ; Service Support ou Sustainment dans l’US Army ; au Royaume-Uni, des préliminaires et une présentation du terrain précèdent les paragraphes, et l’ordre se termine par les questions.

02 — Les procédures de commandement (troop leading procedures)
Les manuels publics de l’US Army décrivent huit étapes pour le chef de petite unité :
1. Recevoir la mission.
2. Donner un ordre préparatoire (WARNO).
3. Établir un plan provisoire.
4. Lancer le mouvement nécessaire.
5. Reconnaître.
6. Compléter le plan.
7. Donner l’ordre.
8. Superviser et affiner.
Elles se chevauchent : on peut reconnaître pendant que les équipes se préparent.

03 — Analyser vite : METT-TC
Pour le plan provisoire, le chef passe en revue : Mission, Enemy (ennemi), Terrain and weather (terrain et météo), Troops and support available (moyens), Time available (temps), Civil considerations (population). En jeu, il s’appuie sur la carte, le briefing de la mission et ce que l’échelon supérieur a transmis.

04 — Le temps : un tiers, deux tiers
Règle publique : le chef garde au plus un tiers du temps disponible pour sa propre préparation et laisse deux tiers à ses subordonnés. Si la section part dans trente minutes, l’ordre de section doit être donné au plus tard dix minutes après réception de la mission.

05 — L’ordre préparatoire (WARNO)
Le WARNO est donné dès la réception de la mission, pour que les subordonnés commencent à se préparer sans attendre le plan complet. Il contient ce que l’on sait déjà :
- la situation en bref et la nature probable de la mission ;
- qui participe ;
- l’horaire : départ au plus tôt, heure et lieu de l’ordre ;
- les consignes particulières : équipement, munitions, radios, véhicules ;
- les tâches de préparation : inspections, test radio, répétitions.
Plusieurs WARNO peuvent suivre si l’information arrive par morceaux.

06 — En jeu
Le WARNO se donne en quelques phrases à la radio ACRE ou au regroupement. Le chef pose sur ATAK un repère de la zone d’opération et du lieu de l’ordre. Pendant ce temps, les chefs d’équipe font compléter l’équipement (arsenal, kits médicaux ACE) et vérifient les radios.

07 — Ce qu’il faut retenir
Recevoir, prévenir (WARNO), planifier, bouger, reconnaître, compléter, ordonner, superviser. Le WARNO part tôt, même incomplet. Deux tiers du temps reviennent aux subordonnés.
FR;
        $theoryEn = <<<'EN'
TRAINING OBJECTIVE
This course teaches how to prepare and deliver a mission order in our Arma 3 missions, at group and section level. It is inspired by the public five-paragraph operation order format used, with variations, by allied armed forces: 1 Situation, 2 Mission, 3 Execution, 4 Service Support (or Sustainment), 5 Command and Signal. Three chapters: troop leading procedures and the warning order, the first three paragraphs, then support, signals, the FRAGO and running the briefing in the game. NATO terms stay in English. This is in-game training: the Athena Academy qualification awarded records an internal pathway and has no real-world qualification value, military or otherwise.

01 — Why a fixed format
Before departure, twenty to forty players are waiting for instructions and time is short. A fixed format prevents information from being forgotten and lets everyone know where to find it. The public variants are similar: the acronym SMEAC (Situation, Mission, Execution, Administration and Logistics, Command and Signal) in the US Marine Corps; Service Support or Sustainment in the US Army; in the United Kingdom, preliminaries and a description of the ground come before the paragraphs, and the order ends with questions.

02 — Troop leading procedures
Public US Army manuals describe eight steps for the small-unit leader:
1. Receive the mission.
2. Issue a warning order (WARNO).
3. Make a tentative plan.
4. Initiate necessary movement.
5. Conduct reconnaissance.
6. Complete the plan.
7. Issue the order.
8. Supervise and refine.
They overlap: you can reconnoitre while the teams are preparing.

03 — Quick analysis: METT-TC
For the tentative plan, the leader reviews: Mission, Enemy, Terrain and weather, Troops and support available, Time available, Civil considerations. In the game, they rely on the map, the mission briefing and what higher has passed on.

04 — Time: one third, two thirds
Public rule: the leader keeps at most one third of the available time for their own preparation and leaves two thirds to their subordinates. If the section leaves in thirty minutes, the section order must be given no later than ten minutes after the mission is received.

05 — The warning order (WARNO)
The WARNO is given as soon as the mission is received, so that subordinates start preparing without waiting for the complete plan. It contains what is already known:
- the situation in brief and the probable nature of the mission;
- who is taking part;
- the timings: earliest departure, time and place of the order;
- special instructions: equipment, ammunition, radios, vehicles;
- preparation tasks: inspections, radio check, rehearsals.
Several WARNOs may follow if information arrives piecemeal.

06 — In the game
The WARNO is given in a few sentences on the ACRE radio or at the rally point. The leader places an ATAK marker for the area of operations and the place of the order. Meanwhile, team leaders have equipment completed (arsenal, ACE medical kits) and check the radios.

07 — Key points
Receive, warn (WARNO), plan, move, reconnoitre, complete, order, supervise. The WARNO goes out early, even if incomplete. Two thirds of the time belong to subordinates.
EN;
        $demoFr = <<<'FR'
DÉMONSTRATION COMMENTÉE — LE WARNO DE SABRE
Situation de jeu : à 20 h 10 (heure du jeu), le chef de la section « Sabre » reçoit de la compagnie « Citadel » la mission de s’emparer d’un poste radar ennemi. Départ prévu à 20 h 40. Il réunit ses trois chefs d’équipe sur le net de section ACRE.

Sabre : « Sabre 1, Sabre 2, Sabre 3, ici Sabre. WARNO. »
Commentaire : l’annonce du type d’ordre prépare l’écoute.

Sabre : « Situation : un poste radar ennemi, environ une équipe, sur la colline au nord de la vallée. Repère ATAK RADAR. »
Commentaire : la situation tient en une phrase et renvoie à un repère commun.

Sabre : « Mission probable : la section s’empare du poste radar ce soir. Toute la section participe, plus un véhicule de transport. »
Commentaire : la nature de la mission et les participants sont connus ; le plan, lui, n’existe pas encore.

Sabre : « Horaire : départ au plus tôt 20 h 40. Ordre à 20 h 20, au véhicule. »
Commentaire : 30 minutes disponibles ; le chef se réserve 10 minutes, soit un tiers.

Sabre : « Consignes : JVN pour tous, une charge de démolition par équipe, radios courtes portées réglées sur les canaux de la section. Chefs d’équipe : inspection et test radio avant 20 h 20. »
Commentaire : les subordonnés savent quoi préparer immédiatement.

Sabre 1 : « Sabre 1, reçu WARNO, ordre à 20 h 20 au véhicule. »
Commentaire : collationnement des points-clés : horaire et lieu.

À RETENIR
En moins d’une minute, toute la section commence à se préparer. Le plan sera présenté à 20 h 20, avec vingt minutes laissées aux équipes.
FR;
        $demoEn = <<<'EN'
ANNOTATED DEMONSTRATION — SABRE’S WARNO
Game situation: at 20:10 (game time), the leader of section “Sabre” receives from company “Citadel” the mission to seize an enemy radar post. Departure is planned for 20:40. They call their three team leaders on the ACRE section net.

Sabre: “Sabre 1, Sabre 2, Sabre 3, this is Sabre. WARNO.”
Comment: announcing the type of order prepares the listeners.

Sabre: “Situation: an enemy radar post, about one team, on the hill north of the valley. ATAK marker RADAR.”
Comment: the situation fits in one sentence and refers to a common marker.

Sabre: “Probable mission: the section seizes the radar post tonight. The whole section takes part, plus one transport vehicle.”
Comment: the nature of the mission and the participants are known; the plan itself does not exist yet.

Sabre: “Timings: earliest departure 20:40. Orders at 20:20, at the vehicle.”
Comment: 30 minutes available; the leader keeps 10 minutes, one third.

Sabre: “Instructions: NVGs for everyone, one demolition charge per team, short-range radios set to the section channels. Team leaders: inspection and radio check before 20:20.”
Comment: subordinates know what to prepare immediately.

Sabre 1: “Sabre 1, copy WARNO, orders at 20:20 at the vehicle.”
Comment: read-back of the key points: time and place.

KEY POINT
In less than a minute, the whole section starts preparing. The plan will be presented at 20:20, with twenty minutes left to the teams.
EN;
        $guided = K::stage(
            K::question(
                'Quelle organisation du temps choisissez-vous ?',
                'Which time plan do you choose?',
                [
                    K::text('Vous préparez un plan complet et donnez l’ordre à 21 h 25, pour qu’il soit parfait.', 'You prepare a complete plan and give the order at 21:25, so that it is perfect.'),
                    K::text('WARNO immédiat, vers 21 h 02, puis ordre complet au plus tard vers 21 h 15, en laissant environ deux tiers du temps aux équipes.', 'Immediate WARNO, around 21:02, then the complete order no later than about 21:15, leaving about two thirds of the time to the teams.'),
                    K::text('Pas de WARNO : vous donnez l’ordre à 21 h 20, une seule fois, pour ne pas répéter.', 'No WARNO: you give the order at 21:20, once only, to avoid repetition.'),
                ],
                1,
                'Quarante minutes sont disponibles : le chef s’en réserve environ un tiers (13 minutes). Le WARNO immédiat lance la préparation ; un ordre « parfait » donné à cinq minutes du départ ne laisse aucun temps aux équipes.',
                'Forty minutes are available: the leader keeps about one third (13 minutes). The immediate WARNO starts preparation; a “perfect” order given five minutes before departure leaves the teams no time.'
            ),
            K::text(
                "CAS GUIDÉ — QUARANTE MINUTES\nVous êtes chef de la section Sabre. À 21 h 00 (heure du jeu), Citadel vous ordonne de tenir un pont sur la rivière. Départ obligatoire à 21 h 40. Vous ne connaissez pas encore la position exacte de l’ennemi ; un drone ami doit vous la transmettre vers 21 h 10.\n\nTravaillez dans cet ordre :\n1. Calculez le temps disponible.\n2. Appliquez la règle un tiers / deux tiers.\n3. Décidez quand partir le WARNO, même incomplet.\n4. Choisissez la réponse qui respecte ces principes.",
                "GUIDED CASE — FORTY MINUTES\nYou are the leader of section Sabre. At 21:00 (game time), Citadel orders you to hold a bridge over the river. Departure is mandatory at 21:40. You do not yet know the exact enemy position; a friendly drone is to send it to you around 21:10.\n\nWork in this order:\n1. Calculate the time available.\n2. Apply the one-third / two-thirds rule.\n3. Decide when to send the WARNO, even incomplete.\n4. Choose the answer that respects these principles."
            ),
            K::text(
                'Relisez les sections 04 et 05 : le WARNO n’attend pas le renseignement complet.',
                'Reread sections 04 and 05: the WARNO does not wait for complete intelligence.'
            )
        );
        $simulation = K::stage(
            K::question(
                'Quel WARNO envoyez-vous ?',
                'Which WARNO do you send?',
                [
                    K::text('« WARNO. Situation : convoi ennemi attendu sur la route est, repère ATAK EMBUSCADE. Mission probable : embuscade du convoi par la section. Départ au plus tôt 18 h 30, ordre à 18 h 10 au point de rassemblement. Lance-roquettes antichars pour Sabre 2, test radio avant l’ordre. »', '“WARNO. Situation: enemy convoy expected on the eastern road, ATAK marker AMBUSH. Probable mission: section ambush of the convoy. Earliest departure 18:30, orders at 18:10 at the assembly point. Anti-tank launchers for Sabre 2, radio check before orders.”'),
                    K::text('« Préparez-vous, on bouge bientôt. Je vous en dis plus tout à l’heure. »', '“Get ready, we are moving soon. I’ll tell you more later.”'),
                    K::text('Le détail complet de l’embuscade, équipe par équipe, avec les secteurs de tir, avant même d’avoir regardé la carte.', 'The full detail of the ambush, team by team, with sectors of fire, before you have even looked at the map.'),
                ],
                0,
                'Le WARNO donne ce qui est connu et utile pour se préparer : situation, mission probable, horaire et lieu de l’ordre, consignes particulières. « Préparez-vous » ne permet rien ; un plan détaillé improvisé avant l’analyse sera probablement faux et retarde l’ordre.',
                'The WARNO gives what is known and useful for preparation: situation, probable mission, timings and place of the order, special instructions. “Get ready” enables nothing; a detailed plan improvised before analysis will probably be wrong and delays the order.'
            ),
            K::text(
                "SIMULATION AUTONOME — L’EMBUSCADE DU CONVOI\nVous êtes chef de la section Sabre. À 18 h 00, Citadel vous informe qu’un convoi ennemi devrait emprunter la route est dans l’heure et vous demande de l’intercepter. Le départ au plus tôt est 18 h 30. Sabre 2 est l’équipe antichar. Vous n’avez pas encore étudié la carte.\n\nVotre objectif : lancer la préparation de toute la section en une minute. Aucune aide n’est donnée pendant cette étape ; vous pourrez revoir le cours après une tentative.",
                "INDEPENDENT SIMULATION — THE CONVOY AMBUSH\nYou are the leader of section Sabre. At 18:00, Citadel tells you that an enemy convoy should use the eastern road within the hour and asks you to intercept it. Earliest departure is 18:30. Sabre 2 is the anti-tank team. You have not yet studied the map.\n\nYour objective: start the whole section’s preparation within one minute. No hint is given during this stage; you may review the course after an attempt."
            )
        );
        $questions = [
            K::question(
                'Quelles sont les deux premières étapes des procédures de commandement ?',
                'What are the first two troop leading procedure steps?',
                [
                    K::text('Recevoir la mission, puis donner un ordre préparatoire (WARNO).', 'Receive the mission, then issue a warning order (WARNO).'),
                    K::text('Reconnaître, puis donner l’ordre.', 'Reconnoitre, then issue the order.'),
                    K::text('Compléter le plan, puis superviser.', 'Complete the plan, then supervise.'),
                ],
                0,
                'Le WARNO suit immédiatement la réception de la mission, pour lancer la préparation des subordonnés.',
                'The WARNO immediately follows receipt of the mission, to start subordinates’ preparation.'
            ),
            K::question(
                'À quoi sert l’ordre préparatoire (WARNO) ?',
                'What is the warning order (WARNO) for?',
                [
                    K::text('À remplacer l’ordre d’opérations.', 'To replace the operation order.'),
                    K::text('À modifier un ordre déjà donné.', 'To amend an order already given.'),
                    K::text('À permettre aux subordonnés de commencer à se préparer avant le plan complet.', 'To let subordinates start preparing before the complete plan.'),
                ],
                2,
                'Le WARNO annonce ce qui est connu ; l’ordre complet suit. La modification d’un ordre donné est le rôle du FRAGO.',
                'The WARNO announces what is known; the complete order follows. Amending an order already given is the role of the FRAGO.'
            ),
            K::question(
                'La section part dans 45 minutes. Selon la règle un tiers / deux tiers, combien de temps le chef se réserve-t-il au plus ?',
                'The section leaves in 45 minutes. Under the one-third / two-thirds rule, how much time does the leader keep at most?',
                [
                    K::text('30 minutes.', '30 minutes.'),
                    K::text('15 minutes.', '15 minutes.'),
                    K::text('5 minutes.', '5 minutes.'),
                ],
                1,
                'Un tiers de 45 minutes, soit 15 minutes ; les 30 autres reviennent aux équipes.',
                'One third of 45 minutes, that is 15 minutes; the other 30 belong to the teams.'
            ),
            K::question(
                'Dans METT-TC, que signifie le second « T » ?',
                'In METT-TC, what does the second “T” stand for?',
                [
                    K::text('Time available — le temps disponible.', 'Time available.'),
                    K::text('Terrain.', 'Terrain.'),
                    K::text('Tactics — la tactique.', 'Tactics.'),
                ],
                0,
                'Mission, Enemy, Terrain and weather, Troops and support available, Time available, Civil considerations.',
                'Mission, Enemy, Terrain and weather, Troops and support available, Time available, Civil considerations.'
            ),
            K::question(
                'Que n’a-t-on PAS besoin d’avoir pour donner un WARNO ?',
                'What do you NOT need in order to give a WARNO?',
                [
                    K::text('L’heure et le lieu de l’ordre.', 'The time and place of the order.'),
                    K::text('La nature probable de la mission.', 'The probable nature of the mission.'),
                    K::text('Le plan complet, équipe par équipe.', 'The complete plan, team by team.'),
                ],
                2,
                'Le WARNO part avec l’information disponible. Le plan complet est l’objet de l’ordre d’opérations.',
                'The WARNO goes out with the information available. The complete plan is the subject of the operation order.'
            ),
            K::question(
                'Dans la variante britannique publique, qu’ajoute-t-on autour des paragraphes ?',
                'In the public British variant, what is added around the paragraphs?',
                [
                    K::text('Un paragraphe sur la météo spatiale.', 'A paragraph on space weather.'),
                    K::text('Des préliminaires et une présentation du terrain au début, des questions à la fin.', 'Preliminaries and a description of the ground at the start, questions at the end.'),
                    K::text('Un sixième paragraphe consacré au renseignement.', 'A sixth paragraph devoted to intelligence.'),
                ],
                1,
                'Le cœur reste Situation, Mission, Execution, Service Support, Command and Signal ; les préliminaires, le terrain et les questions l’encadrent.',
                'The core remains Situation, Mission, Execution, Service Support, Command and Signal; preliminaries, ground and questions frame it.'
            ),
            K::question(
                'Que fait le chef à la dernière étape, « superviser et affiner » ?',
                'What does the leader do at the last step, “supervise and refine”?',
                [
                    K::text('Il vérifie la préparation (inspections, répétitions, radios) et ajuste le plan si besoin.', 'They check preparation (inspections, rehearsals, radios) and adjust the plan if needed.'),
                    K::text('Il laisse les équipes seules jusqu’au départ.', 'They leave the teams alone until departure.'),
                    K::text('Il rédige le compte rendu de fin de mission.', 'They write the end-of-mission report.'),
                ],
                0,
                'Donner l’ordre ne suffit pas : le chef vérifie qu’il est compris et que la préparation suit.',
                'Giving the order is not enough: the leader checks that it is understood and that preparation follows.'
            ),
        ];

        return K::chapter(
            K::text('Chapitre 1 — Procédures de commandement et ordre préparatoire (WARNO)', 'Chapter 1 — Troop leading procedures and the warning order (WARNO)'),
            K::text($theoryFr, $theoryEn),
            K::text($demoFr, $demoEn),
            $guided,
            $simulation,
            $questions,
            K::text(
                "Rédigez un retour personnel sur le cas de l’embuscade et votre évaluation :\n1. Le WARNO que vous auriez donné, mot pour mot.\n2. Le calcul de temps que vous avez fait.\n3. Les informations que vous n’aviez pas encore et comment vous les avez traitées.\n4. Ce que les chefs d’équipe peuvent préparer grâce à votre WARNO.\n5. Une amélioration à tester lors de votre prochaine mission comme chef.",
                "Write a personal reflection on the ambush case and your assessment:\n1. The WARNO you would have given, word for word.\n2. The time calculation you made.\n3. The information you did not yet have and how you handled it.\n4. What team leaders can prepare thanks to your WARNO.\n5. One improvement to test in your next mission as a leader."
            ),
            [
                K::text('Les huit procédures de commandement sont restituées dans l’ordre.', 'The eight troop leading procedures are restated in order.'),
                K::text('La règle un tiers / deux tiers est appliquée à un cas chiffré.', 'The one-third / two-thirds rule is applied to a worked case.'),
                K::text('Le WARNO proposé contient situation, mission probable, horaire et lieu de l’ordre, consignes.', 'The proposed WARNO contains situation, probable mission, timings and place of the order, instructions.'),
                K::text('L’amélioration proposée est concrète et vérifiable en mission.', 'The proposed improvement is concrete and can be checked in a mission.'),
            ],
            [
                K::text('L’évaluation atteint au moins 80 % dans le délai prévu.', 'The assessment reaches at least 80% within the time limit.'),
                K::text('Le stagiaire donne un WARNO complet à la radio en moins d’une minute.', 'The learner gives a complete WARNO on the radio in under a minute.'),
                K::text('Il fixe l’heure de l’ordre en laissant environ deux tiers du temps aux subordonnés.', 'They set the time of the order leaving about two thirds of the time to subordinates.'),
                K::text('Il ne retarde pas le WARNO pour attendre un renseignement manquant.', 'They do not delay the WARNO to wait for missing intelligence.'),
            ],
            [
                K::sheet(
                    K::text('Fiche mémo — Procédures de commandement et WARNO', 'Memo card — Troop leading procedures and WARNO'),
                    K::text(
                        "FORMATION DE JEU (ARMA 3) — sans valeur de qualification réelle.\n\nPROCÉDURES DE COMMANDEMENT (TLP)\n1. Recevoir la mission · 2. WARNO · 3. Plan provisoire · 4. Lancer le mouvement · 5. Reconnaître · 6. Compléter le plan · 7. Donner l’ordre · 8. Superviser et affiner.\n\nMETT-TC\nMission · Enemy · Terrain and weather · Troops and support available · Time available · Civil considerations.\n\nTEMPS\nChef : 1/3 au plus. Subordonnés : 2/3.\nExemple : départ dans 30 min → ordre au plus tard 10 min après réception.\n\nWARNO (dès réception de la mission)\n« [Indicatifs], ici [chef]. WARNO. »\nSituation en bref (repère ATAK) · mission probable · qui participe · départ au plus tôt · heure et lieu de l’ordre · consignes (équipement, munitions, radios, véhicules) · tâches de préparation (inspection, test radio, répétition).\nCollationnement : heure et lieu de l’ordre.",
                        "IN-GAME TRAINING (ARMA 3) — no real-world qualification value.\n\nTROOP LEADING PROCEDURES (TLP)\n1. Receive the mission · 2. WARNO · 3. Tentative plan · 4. Initiate movement · 5. Reconnoitre · 6. Complete the plan · 7. Issue the order · 8. Supervise and refine.\n\nMETT-TC\nMission · Enemy · Terrain and weather · Troops and support available · Time available · Civil considerations.\n\nTIME\nLeader: 1/3 at most. Subordinates: 2/3.\nExample: departure in 30 min → order no later than 10 min after receipt.\n\nWARNO (as soon as the mission is received)\n“[Callsigns], this is [leader]. WARNO.”\nSituation in brief (ATAK marker) · probable mission · who takes part · earliest departure · time and place of the order · instructions (equipment, ammunition, radios, vehicles) · preparation tasks (inspection, radio check, rehearsal).\nRead-back: time and place of the order."
                    )
                ),
            ]
        );
    }

    // ------------------------------------------------------------------ Chapitre 2 — Situation, Mission, Execution

    /** @return array<string, mixed> */
    private static function chapterTwo(): array
    {
        $theoryFr = <<<'FR'
01 — L’orientation
Avant le paragraphe 1, le chef oriente les joueurs sur la carte : le nord, la zone d’opération, les grands repères (villages, routes, rivières), l’objectif. En jeu, il ouvre la carte du briefing ou partage l’écran ATAK, et tout le monde regarde les mêmes repères. Les manuels américains placent aussi en tête l’organisation de la force (qui est avec qui).

02 — Paragraphe 1 : Situation
a) Ennemi : composition, dispositif, effectifs ; capacités (armes, véhicules, JVN) ; mode d’action le plus probable et le plus dangereux.
b) Amis : mission et intention de l’échelon supérieur ; unités voisines ; appuis disponibles (véhicules, mortier, aviation).
c) Rattachements et détachements : qui rejoint ou quitte la section.
d) Terrain, météo, civils : heures du jour et de la nuit, lune, pluie, population présente.
Séparez les faits des suppositions : « signalé », « observé », « supposé ».

03 — Paragraphe 2 : Mission
Une seule phrase qui répond aux cinq questions : qui, quoi (la tâche), quand, où, pourquoi (le but). Exemple : « La section Sabre s’empare du poste radar sur la colline RADAR à 21 h 00, afin de priver l’ennemi d’alerte avant l’attaque de la compagnie. » On la lit deux fois, lentement.

04 — Paragraphe 3 : Execution
a) Intention du chef : le but, les tâches clés, l’état final recherché.
b) Concept des opérations : la manœuvre, par phases (approche, appui, assaut, réorganisation), et les feux.
c) Missions des subordonnés : pour chaque équipe, une tâche et son but.
d) Instructions de coordination : horaires, itinéraires, lignes de phase, points de ralliement, règles d’ouverture du feu, conduite au contact, mesures contre les tirs fratricides (secteurs, marquages), heure de la répétition.

05 — L’intention du chef
Dans les publications publiques, l’intention décrit l’état final recherché, le but et les tâches clés. Elle doit être comprise deux niveaux en dessous. En jeu, quand le scénario change (pont détruit, renfort ennemi, chef inconscient), les chefs d’équipe agissent selon l’intention au lieu d’attendre des ordres. Exemple : « Je veux que le radar soit hors d’usage et la section réorganisée sur la colline, prête à couvrir l’arrivée de la compagnie. »

06 — Les erreurs courantes
- Situation trop longue, sans tri entre faits et suppositions.
- Mission sans « pourquoi » : les subordonnés ne peuvent plus s’adapter.
- Tâches sans but pour les équipes.
- Coordination oubliée : pas d’heure de départ, pas de point de ralliement, secteurs non donnés.

07 — Ce qu’il faut retenir
Orienter, puis Situation (ennemi, amis, rattachements, terrain), Mission (qui, quoi, quand, où, pourquoi, lue deux fois), Execution (intention, concept, missions, coordination).
FR;
        $theoryEn = <<<'EN'
01 — Orientation
Before paragraph 1, the leader orients the players on the map: north, the area of operations, major references (villages, roads, rivers), the objective. In the game, they open the briefing map or share the ATAK screen, and everyone looks at the same references. US manuals also start with the task organisation (who is with whom).

02 — Paragraph 1: Situation
a) Enemy: composition, disposition, strength; capabilities (weapons, vehicles, NVGs); most likely and most dangerous course of action.
b) Friendly: higher’s mission and intent; adjacent units; available support (vehicles, mortar, aviation).
c) Attachments and detachments: who joins or leaves the section.
d) Terrain, weather, civilians: hours of daylight and darkness, moon, rain, population present.
Separate facts from assumptions: “reported”, “observed”, “assessed”.

03 — Paragraph 2: Mission
A single sentence answering the five questions: who, what (the task), when, where, why (the purpose). Example: “Section Sabre seizes the radar post on hill RADAR at 21:00, in order to deny the enemy early warning before the company attack.” It is read twice, slowly.

04 — Paragraph 3: Execution
a) Commander’s intent: purpose, key tasks, desired end state.
b) Concept of operations: the manoeuvre, by phases (approach, support, assault, reorganisation), and fires.
c) Tasks to subordinate units: for each team, a task and its purpose.
d) Coordinating instructions: timings, routes, phase lines, rally points, rules for opening fire, actions on contact, fratricide prevention measures (sectors, markings), rehearsal time.

05 — Commander’s intent
In public publications, the intent describes the desired end state, the purpose and the key tasks. It must be understood two levels down. In the game, when the scenario changes (bridge destroyed, enemy reinforcements, leader unconscious), team leaders act on the intent instead of waiting for orders. Example: “I want the radar out of action and the section reorganised on the hill, ready to cover the company’s arrival.”

06 — Common mistakes
- Situation too long, with no sorting of facts from assumptions.
- Mission without a “why”: subordinates can no longer adapt.
- Tasks without a purpose for the teams.
- Coordination forgotten: no departure time, no rally point, sectors not given.

07 — Key points
Orient, then Situation (enemy, friendly, attachments, terrain), Mission (who, what, when, where, why, read twice), Execution (intent, concept, tasks, coordination).
EN;
        $demoFr = <<<'FR'
DÉMONSTRATION COMMENTÉE — LES TROIS PREMIERS PARAGRAPHES DE SABRE
Situation de jeu : 20 h 20, au véhicule. Le chef de Sabre partage son écran ATAK. Les chefs d’équipe Sabre 1, 2 et 3 sont présents.

Sabre : « Orientation : le nord en haut. Nous sommes au sud de la vallée. La route traverse le village VERT. Le poste radar est sur la colline au nord, repère RADAR. »
Commentaire : tout le monde regarde les mêmes repères avant le premier paragraphe.

Sabre : « 1. Situation. Ennemi : une équipe de quatre à six, armes légères, une mitrailleuse observée au sommet. Ils ont été vus avec des JVN. Mode d’action probable : défendre le sommet ; le plus dangereux : appeler un renfort motorisé depuis le village VERT. Amis : la compagnie attaque le village VERT à 21 h 30 et veut que l’ennemi n’ait pas d’alerte. Sabre est seule sur cet axe. Pas de rattachement. Nuit sans lune. »
Commentaire : faits observés, capacités, deux modes d’action, intention de l’échelon supérieur. La nuit sans lune justifie les JVN.

Sabre : « 2. Mission. La section Sabre s’empare du poste radar sur la colline RADAR à 21 h 00, afin de priver l’ennemi d’alerte avant l’attaque de la compagnie. Je répète : … »
Commentaire : qui, quoi, quand, où, pourquoi, lu deux fois.

Sabre : « 3. Execution. Intention : radar hors d’usage, section réorganisée sur la colline, prête à couvrir la compagnie. Concept : approche par le ravin ouest, Sabre 1 en appui depuis la crête ouest, Sabre 2 et 3 à l’assaut depuis le sud. Sabre 1 : appuyer l’assaut afin de fixer la mitrailleuse. Sabre 2 : s’emparer du sommet afin de détruire le radar. Sabre 3 : bloquer la piste du village afin d’arrêter un renfort. Coordination : départ 20 h 40, PL ROUGE au pied de la colline, point de ralliement au ravin, Sabre 1 lève ses tirs quand Sabre 2 annonce PL ROUGE, répétition à 20 h 30. »
Commentaire : chaque équipe a une tâche et un but. Les mesures contre les tirs fratricides figurent dans la coordination.

À RETENIR
Orientation, faits triés, mission en une phrase lue deux fois, intention qui guidera les équipes si le plan casse.
FR;
        $demoEn = <<<'EN'
ANNOTATED DEMONSTRATION — SABRE’S FIRST THREE PARAGRAPHS
Game situation: 20:20, at the vehicle. Sabre’s leader shares their ATAK screen. Team leaders Sabre 1, 2 and 3 are present.

Sabre: “Orientation: north is up. We are south of the valley. The road runs through village GREEN. The radar post is on the hill to the north, marker RADAR.”
Comment: everyone looks at the same references before the first paragraph.

Sabre: “1. Situation. Enemy: one team of four to six, small arms, one machine gun observed on the summit. They have been seen with NVGs. Most likely course of action: defend the summit; most dangerous: call a motorised reinforcement from village GREEN. Friendly: the company attacks village GREEN at 21:30 and wants the enemy to have no warning. Sabre is alone on this axis. No attachments. Moonless night.”
Comment: observed facts, capabilities, two courses of action, higher’s intent. The moonless night justifies NVGs.

Sabre: “2. Mission. Section Sabre seizes the radar post on hill RADAR at 21:00, in order to deny the enemy early warning before the company attack. I say again: …”
Comment: who, what, when, where, why, read twice.

Sabre: “3. Execution. Intent: radar out of action, section reorganised on the hill, ready to cover the company. Concept: approach via the western ravine, Sabre 1 in support from the western ridge, Sabre 2 and 3 assaulting from the south. Sabre 1: support the assault in order to fix the machine gun. Sabre 2: seize the summit in order to destroy the radar. Sabre 3: block the village track in order to stop a reinforcement. Coordination: departure 20:40, PL RED at the foot of the hill, rally point at the ravine, Sabre 1 lifts fire when Sabre 2 reports PL RED, rehearsal at 20:30.”
Comment: each team has a task and a purpose. Fratricide prevention measures are part of the coordination.

KEY POINT
Orientation, sorted facts, a one-sentence mission read twice, an intent that will guide the teams if the plan breaks.
EN;
        $guided = K::stage(
            K::question(
                'Quelle phrase de mission est complète ?',
                'Which mission statement is complete?',
                [
                    K::text('« Sabre attaque le pont. »', '“Sabre attacks the bridge.”'),
                    K::text('« Sabre progresse par le bois, puis Sabre 1 s’installe en appui à gauche, Sabre 2 traverse, Sabre 3 couvre la droite… »', '“Sabre moves through the wood, then Sabre 1 sets up in support on the left, Sabre 2 crosses, Sabre 3 covers the right…”'),
                    K::text('« La section Sabre tient le pont de la rivière NORD à partir de 22 h 00, afin d’empêcher l’ennemi de couper la route de ravitaillement de la compagnie. »', '“Section Sabre holds the bridge over river NORTH from 22:00, in order to prevent the enemy from cutting the company’s supply route.”'),
                ],
                2,
                'La mission répond à qui, quoi, quand, où et pourquoi en une phrase. La première ne dit ni quand ni pourquoi ; la deuxième décrit la manœuvre, qui appartient au concept des opérations du paragraphe 3.',
                'The mission answers who, what, when, where and why in one sentence. The first says neither when nor why; the second describes the manoeuvre, which belongs to the concept of operations in paragraph 3.'
            ),
            K::text(
                "CAS GUIDÉ — LA MISSION DU PONT\nVous êtes chef de la section Sabre. Citadel vous a confié la tenue du pont sur la rivière NORD à partir de 22 h 00, pour protéger la route de ravitaillement de la compagnie. Vous relisez trois brouillons de phrase de mission avant de donner l’ordre.\n\nTravaillez dans cet ordre :\n1. Pour chaque brouillon, cherchez qui, quoi, quand, où, pourquoi.\n2. Repérez le brouillon qui décrit la manœuvre au lieu de la mission.\n3. Vérifiez que le but est exprimé (« afin de… »).\n4. Choisissez la phrase complète.",
                "GUIDED CASE — THE BRIDGE MISSION\nYou are the leader of section Sabre. Citadel has tasked you to hold the bridge over river NORTH from 22:00, to protect the company’s supply route. You review three draft mission statements before giving the order.\n\nWork in this order:\n1. For each draft, look for who, what, when, where, why.\n2. Spot the draft that describes the manoeuvre instead of the mission.\n3. Check that the purpose is stated (“in order to…”).\n4. Choose the complete statement."
            ),
            K::text(
                'Relisez la section 03 : une phrase, cinq questions, et le « pourquoi » à la fin.',
                'Reread section 03: one sentence, five questions, and the “why” at the end.'
            )
        );
        $simulation = K::stage(
            K::question(
                'Que faites-vous ?',
                'What do you do?',
                [
                    K::text('Vous mettez l’équipe à couvert et attendez que le chef de section reprenne connaissance.', 'You put the team in cover and wait for the section leader to regain consciousness.'),
                    K::text('Vous agissez selon l’intention : vous rendez compte à Citadel, prenez le commandement de l’assaut et cherchez à mettre le radar hors d’usage par un autre moyen, puis réorganisez la section sur la colline.', 'You act on the intent: you report to Citadel, take command of the assault and seek to put the radar out of action by other means, then reorganise the section on the hill.'),
                    K::text('Vous ramenez la section au véhicule : le plan n’est plus applicable.', 'You bring the section back to the vehicle: the plan no longer applies.'),
                ],
                1,
                'L’intention (radar hors d’usage, section réorganisée sur la colline) reste valable quand le plan casse. Elle permet d’agir sans attendre, en rendant compte. Attendre ou renoncer laisse l’ennemi donner l’alerte avant l’attaque de la compagnie.',
                'The intent (radar out of action, section reorganised on the hill) remains valid when the plan breaks. It allows action without waiting, while reporting. Waiting or giving up lets the enemy raise the alarm before the company attack.'
            ),
            K::text(
                "SIMULATION AUTONOME — LE PLAN CASSE\nVous êtes chef de l’équipe Sabre 2 dans l’attaque du poste radar (voir la démonstration). Au pied de la colline, le chef de section est inconscient (médical ACE) et la charge de démolition de votre équipe a été perdue. Il est 20 h 58. L’intention donnée était : « radar hors d’usage, section réorganisée sur la colline, prête à couvrir la compagnie ».\n\nVotre objectif : décider sans ordre nouveau, en restant fidèle au but de la mission. Aucune aide n’est donnée pendant cette étape ; vous pourrez revoir le cours après une tentative.",
                "INDEPENDENT SIMULATION — THE PLAN BREAKS\nYou are the leader of team Sabre 2 in the radar post attack (see the demonstration). At the foot of the hill, the section leader is unconscious (ACE medical) and your team’s demolition charge has been lost. It is 20:58. The intent given was: “radar out of action, section reorganised on the hill, ready to cover the company”.\n\nYour objective: decide without new orders, staying true to the purpose of the mission. No hint is given during this stage; you may review the course after an attempt."
            )
        );
        $questions = [
            K::question(
                'Dans quel ordre se suivent les cinq paragraphes ?',
                'In what order do the five paragraphs come?',
                [
                    K::text('Mission, Situation, Execution, Command and Signal, Service Support.', 'Mission, Situation, Execution, Command and Signal, Service Support.'),
                    K::text('Situation, Mission, Execution, Service Support, Command and Signal.', 'Situation, Mission, Execution, Service Support, Command and Signal.'),
                    K::text('Situation, Execution, Mission, Service Support, Command and Signal.', 'Situation, Execution, Mission, Service Support, Command and Signal.'),
                ],
                1,
                'SMEAC : Situation, Mission, Execution, Administration and Logistics (Service Support), Command and Signal.',
                'SMEAC: Situation, Mission, Execution, Administration and Logistics (Service Support), Command and Signal.'
            ),
            K::question(
                'À quelles questions répond la phrase de mission ?',
                'Which questions does the mission statement answer?',
                [
                    K::text('Qui, quoi, quand, où, pourquoi.', 'Who, what, when, where, why.'),
                    K::text('Combien, avec quoi, par où.', 'How many, with what, which way.'),
                    K::text('Qui commande, sur quelle fréquence.', 'Who commands, on which frequency.'),
                ],
                0,
                'Les cinq « W » anglais (who, what, when, where, why) ; le « comment » relève du paragraphe 3.',
                'The five Ws (who, what, when, where, why); the “how” belongs to paragraph 3.'
            ),
            K::question(
                'Que contient l’intention du chef ?',
                'What does the commander’s intent contain?',
                [
                    K::text('La liste des fréquences radio.', 'The list of radio frequencies.'),
                    K::text('L’effectif ennemi détaillé.', 'The detailed enemy strength.'),
                    K::text('Le but, les tâches clés et l’état final recherché.', 'The purpose, the key tasks and the desired end state.'),
                ],
                2,
                'L’intention dit ce qui doit être obtenu et pourquoi, pour guider l’initiative des subordonnés quand le plan ne s’applique plus.',
                'The intent says what must be achieved and why, to guide subordinates’ initiative when the plan no longer applies.'
            ),
            K::question(
                'Dans quel sous-paragraphe place-t-on les points de ralliement, les horaires et les mesures contre les tirs fratricides ?',
                'In which sub-paragraph do rally points, timings and fratricide prevention measures go?',
                [
                    K::text('Les instructions de coordination, au paragraphe 3 (Execution).', 'Coordinating instructions, in paragraph 3 (Execution).'),
                    K::text('Le paragraphe 1, situation amie.', 'Paragraph 1, friendly situation.'),
                    K::text('Le paragraphe 2, mission.', 'Paragraph 2, mission.'),
                ],
                0,
                'Les instructions de coordination concernent plusieurs équipes à la fois : horaires, itinéraires, lignes de phase, ralliement, ouverture du feu, secteurs et marquages.',
                'Coordinating instructions concern several teams at once: timings, routes, phase lines, rally points, opening fire, sectors and markings.'
            ),
            K::question(
                'Où annonce-t-on le mode d’action ennemi le plus dangereux ?',
                'Where is the enemy’s most dangerous course of action stated?',
                [
                    K::text('Au paragraphe 5, Command and Signal.', 'In paragraph 5, Command and Signal.'),
                    K::text('Au paragraphe 1, Situation, partie ennemi.', 'In paragraph 1, Situation, enemy part.'),
                    K::text('Il ne s’annonce jamais, pour ne pas inquiéter les joueurs.', 'It is never stated, so as not to worry the players.'),
                ],
                1,
                'La partie ennemi présente composition, dispositif, capacités, puis les modes d’action le plus probable et le plus dangereux.',
                'The enemy part presents composition, disposition, capabilities, then the most likely and most dangerous courses of action.'
            ),
            K::question(
                'Où figurent la mission et l’intention de l’échelon supérieur ?',
                'Where do higher’s mission and intent appear?',
                [
                    K::text('Dans le paragraphe 4, Service Support.', 'In paragraph 4, Service Support.'),
                    K::text('Dans la phrase de mission de la section.', 'In the section’s mission statement.'),
                    K::text('Au paragraphe 1, Situation, partie amis.', 'In paragraph 1, Situation, friendly part.'),
                ],
                2,
                'La situation amie commence par ce que veut l’échelon supérieur : la mission de la section s’y rattache.',
                'The friendly situation starts with what higher wants: the section’s mission is linked to it.'
            ),
            K::question(
                'Pourquoi chaque mission d’équipe contient-elle un but (« afin de… ») ?',
                'Why does each team task include a purpose (“in order to…”)?',
                [
                    K::text('Pour que l’équipe puisse adapter son action si la tâche devient impossible.', 'So that the team can adapt its action if the task becomes impossible.'),
                    K::text('Pour rallonger l’ordre.', 'To lengthen the order.'),
                    K::text('Parce que le format l’impose, sans autre utilité.', 'Because the format requires it, with no other use.'),
                ],
                0,
                'Connaître le but permet de trouver un autre moyen d’obtenir le même effet, comme l’intention au niveau de la section.',
                'Knowing the purpose allows another way to achieve the same effect to be found, like the intent at section level.'
            ),
        ];

        return K::chapter(
            K::text('Chapitre 2 — Les paragraphes Situation, Mission et Execution', 'Chapter 2 — The Situation, Mission and Execution paragraphs'),
            K::text($theoryFr, $theoryEn),
            K::text($demoFr, $demoEn),
            $guided,
            $simulation,
            $questions,
            K::text(
                "Rédigez un retour personnel sur le cas « le plan casse » et votre évaluation :\n1. La décision prise et l’élément de l’intention qui l’a guidée.\n2. Le compte rendu que vous avez fait à Citadel.\n3. La phrase de mission que vous écririez pour une prochaine mission de votre unité.\n4. L’instruction de coordination que vous oubliez le plus souvent.\n5. Une amélioration à tester dans votre prochain ordre.",
                "Write a personal reflection on the “plan breaks” case and your assessment:\n1. The decision taken and the part of the intent that guided it.\n2. The report you made to Citadel.\n3. The mission statement you would write for your unit’s next mission.\n4. The coordinating instruction you most often forget.\n5. One improvement to test in your next order."
            ),
            [
                K::text('Le lien entre la décision et l’intention du chef est explicite.', 'The link between the decision and the commander’s intent is explicit.'),
                K::text('La phrase de mission proposée répond aux cinq questions.', 'The proposed mission statement answers the five questions.'),
                K::text('Les sous-paragraphes de Situation et d’Execution sont correctement placés.', 'The Situation and Execution sub-paragraphs are correctly placed.'),
                K::text('L’amélioration proposée est concrète et vérifiable.', 'The proposed improvement is concrete and checkable.'),
            ],
            [
                K::text('L’évaluation atteint au moins 80 % dans le délai prévu.', 'The assessment reaches at least 80% within the time limit.'),
                K::text('Le stagiaire donne une orientation puis les paragraphes 1 à 3 dans l’ordre, sur la carte ou ATAK.', 'The learner gives an orientation then paragraphs 1 to 3 in order, on the map or ATAK.'),
                K::text('Sa mission tient en une phrase complète, lue deux fois.', 'Their mission fits in one complete sentence, read twice.'),
                K::text('Chaque équipe reçoit une tâche et un but ; la coordination comprend horaires, ralliement et mesures anti-fratricides.', 'Each team receives a task and a purpose; coordination includes timings, rally points and fratricide prevention measures.'),
            ],
            [
                K::sheet(
                    K::text('Fiche mémo — Paragraphes 1 à 3', 'Memo card — Paragraphs 1 to 3'),
                    K::text(
                        "FORMATION DE JEU (ARMA 3) — sans valeur de qualification réelle.\n\nORIENTATION\nNord · zone d’opération · grands repères · objectif (carte du briefing ou écran ATAK). Organisation de la force.\n\n1. SITUATION\na) Ennemi : composition, dispositif, effectifs · capacités · mode d’action le plus probable / le plus dangereux.\nb) Amis : mission et intention de l’échelon supérieur · voisins · appuis.\nc) Rattachements / détachements.\nd) Terrain, météo, civils (jour, nuit, lune).\nFaits ≠ suppositions : observé · signalé · supposé.\n\n2. MISSION (lue deux fois)\nQui · quoi · quand · où · pourquoi (« afin de… »).\n\n3. EXECUTION\na) Intention : but · tâches clés · état final recherché.\nb) Concept : manœuvre par phases · feux.\nc) Missions des équipes : tâche + but.\nd) Coordination : horaires · itinéraires · PL · ralliement · ouverture du feu · conduite au contact · secteurs et marquages · répétition.",
                        "IN-GAME TRAINING (ARMA 3) — no real-world qualification value.\n\nORIENTATION\nNorth · area of operations · major references · objective (briefing map or ATAK screen). Task organisation.\n\n1. SITUATION\na) Enemy: composition, disposition, strength · capabilities · most likely / most dangerous course of action.\nb) Friendly: higher’s mission and intent · adjacent units · support.\nc) Attachments / detachments.\nd) Terrain, weather, civilians (daylight, darkness, moon).\nFacts ≠ assumptions: observed · reported · assessed.\n\n2. MISSION (read twice)\nWho · what · when · where · why (“in order to…”).\n\n3. EXECUTION\na) Intent: purpose · key tasks · desired end state.\nb) Concept: manoeuvre by phases · fires.\nc) Team tasks: task + purpose.\nd) Coordination: timings · routes · PL · rally points · opening fire · actions on contact · sectors and markings · rehearsal."
                    )
                ),
            ]
        );
    }

    // ------------------------------------------------------------------ Chapitre 3 — Service support, Command & Signal, FRAGO, briefing

    /** @return array<string, mixed> */
    private static function chapterThree(): array
    {
        $theoryFr = <<<'FR'
01 — Paragraphe 4 : Service Support (Sustainment)
Le Corps des Marines américain l’appelle Administration and Logistics. Il répond à : de quoi la section vit-elle pendant la mission ?
- Logistique : munitions et matériel à emporter, ravitaillement prévu (caisses, véhicule, point de ravitaillement), transport.
- Médical : priorités de soins, poste de regroupement des blessés (CCP), qui sont les infirmiers, comment demander une évacuation. En jeu, le médical ACE impose de prévoir bandages, garrots et transfusions.
- Prisonniers : où les regrouper et à qui les remettre.

02 — Paragraphe 5 : Command and Signal
- Commandement : où se trouvent le chef de section et l’échelon supérieur ; la chaîne de succession (qui prend le commandement si le chef tombe, puis le suivant). En jeu, un chef inconscient ne commande plus : la succession évite le silence.
- Transmissions (plan ACRE) : radios, canaux et nets (par exemple radio individuelle courte portée pour l’équipe, radio portative pour le net de section, radio à dos pour la compagnie ou l’aviation), indicatifs, mots-code (« objectif atteint », « ralliement »), signaux visuels et pyrotechniques.
- Plan PACE : Primary, Alternate, Contingency, Emergency — le moyen principal, le moyen de remplacement, le moyen de secours, le moyen d’urgence (par exemple net de section, net de compagnie, message ATAK, fusée éclairante).

03 — Après l’ordre : questions et confirmation
L’ordre se termine par les questions et la synchronisation de l’heure (heure du jeu). Ensuite :
- Confirmation brief : chaque chef d’équipe reformule la mission, l’intention et sa tâche, juste après l’ordre.
- Backbrief : chaque chef d’équipe explique comment il va exécuter sa tâche ; le chef vérifie que les plans s’emboîtent.

04 — La répétition (rehearsal)
Les manuels publics décrivent plusieurs méthodes, de la plus légère à la plus complète : sur carte, sur croquis, sur maquette au sol, par le réseau radio, jusqu’à la répétition à pied sur un terrain semblable. En jeu : déroulé sur la carte ou ATAK, test radio sur chaque net ACRE, répétition d’un drill (réaction au contact, évacuation d’un blessé) à la base avant le départ.

05 — Le FRAGO
Quand la situation change avant ou pendant l’opération, le chef ne redonne pas tout l’ordre : il émet un FRAGO (fragmentary order). Il suit le format de l’ordre de base mais ne donne que ce qui change, et précise « sans changement » pour le reste. Les subordonnés collationnent les points modifiés.

06 — Conduire le briefing en jeu
Choisissez un endroit sûr, hors du tir. Ouvrez la carte du briefing ou ATAK ; posez les repères avant de parler. Un seul orateur, les chefs d’équipe devant, les autres joueurs écoutent sur le net ou à côté. Visez dix à quinze minutes pour une section. Écrivez l’essentiel (mission, horaires, canaux) dans une note ATAK. Terminez par les questions, le confirmation brief et l’heure de la répétition.

07 — Ce qu’il faut retenir
Paragraphe 4 : logistique, médical, prisonniers. Paragraphe 5 : chef, succession, plan ACRE, PACE. Puis questions, confirmation, backbrief, répétition. En cours de mission : FRAGO, seulement ce qui change.
FR;
        $theoryEn = <<<'EN'
01 — Paragraph 4: Service Support (Sustainment)
The US Marine Corps calls it Administration and Logistics. It answers: what does the section live on during the mission?
- Logistics: ammunition and equipment to carry, planned resupply (crates, vehicle, resupply point), transport.
- Medical: treatment priorities, casualty collection point (CCP), who the medics are, how to request an evacuation. In the game, ACE medical requires planning bandages, tourniquets and transfusions.
- Prisoners: where to gather them and whom to hand them to.

02 — Paragraph 5: Command and Signal
- Command: where the section leader and higher are located; the chain of succession (who takes command if the leader falls, then the next one). In the game, an unconscious leader no longer commands: succession avoids silence.
- Signal (ACRE plan): radios, channels and nets (for example a short-range personal radio for the team, a handheld radio for the section net, a manpack radio for the company or aviation), callsigns, code words (“objective secured”, “rally”), visual and pyrotechnic signals.
- PACE plan: Primary, Alternate, Contingency, Emergency (for example section net, company net, ATAK message, flare).

03 — After the order: questions and confirmation
The order ends with questions and a time check (game time). Then:
- Confirmation brief: each team leader restates the mission, the intent and their task, right after the order.
- Backbrief: each team leader explains how they will carry out their task; the leader checks that the plans fit together.

04 — The rehearsal
Public manuals describe several methods, from the lightest to the most complete: map, sketch map, terrain model on the ground, radio network, up to a full rehearsal on foot on similar ground. In the game: a walk-through on the map or ATAK, a radio check on every ACRE net, a drill rehearsal (reaction to contact, casualty evacuation) at base before departure.

05 — The FRAGO
When the situation changes before or during the operation, the leader does not reissue the whole order: they issue a FRAGO (fragmentary order). It follows the base order format but only gives what changes, and states “no change” for the rest. Subordinates read back the amended points.

06 — Running the briefing in the game
Choose a safe place, out of fire. Open the briefing map or ATAK; place the markers before speaking. One speaker, team leaders at the front, other players listen on the net or nearby. Aim for ten to fifteen minutes for a section. Write the essentials (mission, timings, channels) in an ATAK note. Finish with questions, the confirmation brief and the rehearsal time.

07 — Key points
Paragraph 4: logistics, medical, prisoners. Paragraph 5: leader, succession, ACRE plan, PACE. Then questions, confirmation, backbrief, rehearsal. During the mission: FRAGO, only what changes.
EN;
        $demoFr = <<<'FR'
DÉMONSTRATION COMMENTÉE — FRAGO EN COURS DE MISSION
Situation de jeu : 20 h 52, la section Sabre est au ravin, en route vers le poste radar. Citadel signale qu’un véhicule blindé ennemi est arrivé au pied sud de la colline. L’approche prévue de Sabre 2 et 3 par le sud n’est plus possible.

Citadel : « Sabre, Citadel. Blindé ennemi observé au pied sud de la colline RADAR. Mission sans changement. Adaptez votre approche. »
Commentaire : l’échelon supérieur donne le changement de situation et confirme que la mission reste la même.

Sabre (net de section) : « Sabre 1, 2, 3, ici Sabre. FRAGO 1. »
Commentaire : le FRAGO est numéroté et annoncé comme tel.

Sabre : « Situation : un blindé ennemi au pied sud de la colline, repère ATAK BLINDÉ. Mission : sans changement. »
Commentaire : seul l’élément nouveau de la situation est donné.

Sabre : « Execution : Sabre 2 et 3 donnent l’assaut par l’est, nouvel itinéraire sur ATAK. Sabre 3 : bloquer le blindé avec le lance-roquettes depuis le ravin, afin de protéger l’assaut. PL ROUGE déplacée sur la piste est. Sabre 1 lève ses tirs quand Sabre 2 annonce PL ROUGE. Le reste sans changement. »
Commentaire : nouvelle tâche avec son but, nouvelle ligne de phase, mesure anti-fratricide redonnée. « Le reste sans changement » évite toute ambiguïté.

Sabre 2 : « Sabre 2, FRAGO 1 reçu. Assaut par l’est, PL ROUGE sur la piste est. »
Sabre 3 : « Sabre 3, FRAGO 1 reçu. Bloquer le blindé depuis le ravin. »
Commentaire : chacun collationne ce qui le concerne.

À RETENIR
En moins d’une minute, le plan est adapté sans redonner tout l’ordre. La mission et l’intention, inchangées, gardent la section orientée vers le même but.
FR;
        $demoEn = <<<'EN'
ANNOTATED DEMONSTRATION — FRAGO DURING THE MISSION
Game situation: 20:52, section Sabre is at the ravine, on the way to the radar post. Citadel reports that an enemy armoured vehicle has arrived at the southern foot of the hill. The planned approach of Sabre 2 and 3 from the south is no longer possible.

Citadel: “Sabre, Citadel. Enemy armour observed at the southern foot of hill RADAR. Mission unchanged. Adapt your approach.”
Comment: higher gives the change of situation and confirms that the mission remains the same.

Sabre (section net): “Sabre 1, 2, 3, this is Sabre. FRAGO 1.”
Comment: the FRAGO is numbered and announced as such.

Sabre: “Situation: one enemy armoured vehicle at the southern foot of the hill, ATAK marker ARMOUR. Mission: no change.”
Comment: only the new element of the situation is given.

Sabre: “Execution: Sabre 2 and 3 assault from the east, new route on ATAK. Sabre 3: block the armour with the rocket launcher from the ravine, in order to protect the assault. PL RED moved to the eastern track. Sabre 1 lifts fire when Sabre 2 reports PL RED. Everything else no change.”
Comment: new task with its purpose, new phase line, fratricide prevention measure reissued. “Everything else no change” avoids any ambiguity.

Sabre 2: “Sabre 2, copy FRAGO 1. Assault from the east, PL RED on the eastern track.”
Sabre 3: “Sabre 3, copy FRAGO 1. Block the armour from the ravine.”
Comment: each one reads back what concerns them.

KEY POINT
In under a minute, the plan is adapted without reissuing the whole order. The unchanged mission and intent keep the section aimed at the same purpose.
EN;
        $guided = K::stage(
            K::question(
                'Qu’ajoutez-vous en priorité au paragraphe 5 ?',
                'What do you add first to paragraph 5?',
                [
                    K::text('La chaîne de succession (qui commande si vous tombez, puis le suivant) et un plan PACE pour les transmissions.', 'The chain of succession (who commands if you fall, then the next one) and a PACE plan for communications.'),
                    K::text('La liste des munitions de chaque joueur.', 'The list of each player’s ammunition.'),
                    K::text('La description détaillée de l’ennemi.', 'The detailed description of the enemy.'),
                ],
                0,
                'Le paragraphe 5 dit qui commande et comment on communique, y compris si le chef ou le net principal disparaît. Les munitions relèvent du paragraphe 4, l’ennemi du paragraphe 1.',
                'Paragraph 5 says who commands and how to communicate, including if the leader or the primary net is lost. Ammunition belongs to paragraph 4, the enemy to paragraph 1.'
            ),
            K::text(
                "CAS GUIDÉ — LE PARAGRAPHE 5 INCOMPLET\nVous relisez votre ordre avant de le donner. Votre paragraphe 5 indique seulement : « Je suis avec Sabre 2. Net de section sur le canal 1. » Lors de la dernière mission, le chef de section est tombé inconscient et personne n’a repris le commandement pendant plusieurs minutes ; le net de section a aussi été saturé.\n\nTravaillez dans cet ordre :\n1. Rappelez les deux parties du paragraphe 5 : commandement et transmissions.\n2. Identifiez ce qui a manqué lors de la dernière mission.\n3. Vérifiez à quel paragraphe appartient chaque proposition.\n4. Choisissez l’ajout prioritaire.",
                "GUIDED CASE — THE INCOMPLETE PARAGRAPH 5\nYou are reviewing your order before delivering it. Your paragraph 5 only says: “I am with Sabre 2. Section net on channel 1.” In the last mission, the section leader fell unconscious and nobody took command for several minutes; the section net was also saturated.\n\nWork in this order:\n1. Recall the two parts of paragraph 5: command and signal.\n2. Identify what was missing in the last mission.\n3. Check which paragraph each option belongs to.\n4. Choose the priority addition."
            ),
            K::text(
                'Relisez la section 02 : succession et PACE répondent exactement aux deux problèmes rencontrés.',
                'Reread section 02: succession and PACE answer exactly the two problems encountered.'
            )
        );
        $simulation = K::stage(
            K::question(
                'Quel message envoyez-vous ?',
                'Which message do you send?',
                [
                    K::text('Vous redonnez l’ordre complet, paragraphe par paragraphe, sur le net de section.', 'You reissue the complete order, paragraph by paragraph, on the section net.'),
                    K::text('« Changement de plan, on va au moulin. » sans autre précision.', '“Change of plan, we’re going to the mill.” with no further detail.'),
                    K::text('« FRAGO 2. Situation : pont détruit. Mission : sans changement. Execution : franchissement par le gué au nord du moulin, repère ATAK GUÉ ; Sabre 1 appuie depuis le moulin afin de couvrir le passage. Ralliement déplacé au moulin. Le reste sans changement. » Puis collationnement des chefs d’équipe.', '“FRAGO 2. Situation: bridge destroyed. Mission: no change. Execution: crossing at the ford north of the mill, ATAK marker FORD; Sabre 1 supports from the mill in order to cover the crossing. Rally point moved to the mill. Everything else no change.” Then team leaders read back.'),
                ],
                2,
                'Le FRAGO suit le format de l’ordre de base et ne donne que ce qui change, avec « sans changement » pour le reste et un collationnement. Redonner tout l’ordre fait perdre du temps sous le feu ; un message vague laisse chaque équipe interpréter.',
                'The FRAGO follows the base order format and only gives what changes, with “no change” for the rest and a read-back. Reissuing the whole order wastes time under fire; a vague message leaves each team to interpret.'
            ),
            K::text(
                "SIMULATION AUTONOME — LE PONT DÉTRUIT\nVous êtes chef de la section Sabre, en route pour tenir une position au-delà de la rivière. Le pont prévu vient d’être détruit. Un gué praticable existe au nord du moulin, à 400 m. Votre point de ralliement initial était sur le pont. La mission, confirmée par Citadel, ne change pas.\n\nVotre objectif : adapter le plan de toute la section en un seul message radio. Aucune aide n’est donnée pendant cette étape ; vous pourrez revoir le cours après une tentative.",
                "INDEPENDENT SIMULATION — THE DESTROYED BRIDGE\nYou are the leader of section Sabre, on the way to hold a position beyond the river. The planned bridge has just been destroyed. A usable ford exists north of the mill, 400 m away. Your original rally point was on the bridge. The mission, confirmed by Citadel, does not change.\n\nYour objective: adapt the whole section’s plan in a single radio message. No hint is given during this stage; you may review the course after an attempt."
            )
        );
        $questions = [
            K::question(
                'Que trouve-t-on au paragraphe 4 (Service Support) ?',
                'What is found in paragraph 4 (Service Support)?',
                [
                    K::text('L’intention du chef et le concept des opérations.', 'The commander’s intent and the concept of operations.'),
                    K::text('La logistique, le soutien médical et le traitement des prisonniers.', 'Logistics, medical support and the handling of prisoners.'),
                    K::text('Les indicatifs et les fréquences.', 'Callsigns and frequencies.'),
                ],
                1,
                'Le paragraphe 4 dit de quoi la section vit : munitions, ravitaillement, médical (CCP, évacuation), prisonniers.',
                'Paragraph 4 says what the section lives on: ammunition, resupply, medical (CCP, evacuation), prisoners.'
            ),
            K::question(
                'Pourquoi annoncer une chaîne de succession au paragraphe 5 ?',
                'Why state a chain of succession in paragraph 5?',
                [
                    K::text('Pour que quelqu’un reprenne immédiatement le commandement si le chef tombe.', 'So that someone immediately takes command if the leader falls.'),
                    K::text('Pour désigner le joueur qui portera la radio à dos.', 'To designate the player who will carry the manpack radio.'),
                    K::text('Pour fixer l’ordre de marche.', 'To set the order of march.'),
                ],
                0,
                'Un chef inconscient (médical ACE) ne commande plus ; la succession évite que la section reste sans ordre.',
                'An unconscious leader (ACE medical) no longer commands; succession prevents the section from being left without orders.'
            ),
            K::question(
                'Que signifie PACE dans un plan de transmissions ?',
                'What does PACE stand for in a signal plan?',
                [
                    K::text('Patrol, Assault, Cover, Evacuate.', 'Patrol, Assault, Cover, Evacuate.'),
                    K::text('Position, Altitude, Course, ETA.', 'Position, Altitude, Course, ETA.'),
                    K::text('Primary, Alternate, Contingency, Emergency.', 'Primary, Alternate, Contingency, Emergency.'),
                ],
                2,
                'Quatre moyens classés : principal, de remplacement, de secours, d’urgence, pour ne jamais perdre la liaison.',
                'Four ranked means: primary, alternate, contingency, emergency, so that communication is never lost.'
            ),
            K::question(
                'Qu’est-ce qu’un FRAGO ?',
                'What is a FRAGO?',
                [
                    K::text('Un ordre préparatoire donné avant le plan.', 'A warning order given before the plan.'),
                    K::text('Un ordre qui modifie l’ordre de base et ne donne que ce qui change.', 'An order that amends the base order and only gives what changes.'),
                    K::text('Un compte rendu de fin de mission.', 'An end-of-mission report.'),
                ],
                1,
                'Le FRAGO reprend le format de l’ordre de base mais ne contient que les modifications ; le WARNO, lui, précède l’ordre.',
                'The FRAGO uses the base order format but only contains the amendments; the WARNO comes before the order.'
            ),
            K::question(
                'Quelle est la différence entre confirmation brief et backbrief ?',
                'What is the difference between a confirmation brief and a backbrief?',
                [
                    K::text('Le confirmation brief reformule mission, intention et tâche juste après l’ordre ; le backbrief explique comment le subordonné va exécuter sa tâche.', 'The confirmation brief restates mission, intent and task right after the order; the backbrief explains how the subordinate will carry out their task.'),
                    K::text('Ce sont deux noms pour la même chose.', 'They are two names for the same thing.'),
                    K::text('Le backbrief se fait après la mission, le confirmation brief avant.', 'The backbrief is done after the mission, the confirmation brief before.'),
                ],
                0,
                'Le premier vérifie la compréhension, le second vérifie que les plans des équipes s’emboîtent ; les deux ont lieu avant l’exécution.',
                'The first checks understanding, the second checks that the teams’ plans fit together; both take place before execution.'
            ),
            K::question(
                'En jeu, à quoi correspond une répétition « par le réseau radio » ?',
                'In the game, what does a “radio network” rehearsal correspond to?',
                [
                    K::text('Écouter la radio de l’ennemi.', 'Listening to the enemy’s radio.'),
                    K::text('Changer de canal à chaque phase.', 'Changing channel at every phase.'),
                    K::text('Dérouler les messages-clés et vérifier chaque net ACRE avant le départ.', 'Running through the key messages and checking every ACRE net before departure.'),
                ],
                2,
                'On vérifie que chaque chef entend et se fait entendre sur les canaux prévus, et que les mots-code sont connus.',
                'You check that each leader hears and is heard on the planned channels, and that the code words are known.'
            ),
            K::question(
                'Quand émet-on un FRAGO ?',
                'When is a FRAGO issued?',
                [
                    K::text('Uniquement avant le départ.', 'Only before departure.'),
                    K::text('Quand la situation change, avant ou pendant l’opération, et que l’ordre de base doit être modifié.', 'When the situation changes, before or during the operation, and the base order must be amended.'),
                    K::text('Uniquement à la fin de la mission.', 'Only at the end of the mission.'),
                ],
                1,
                'Le FRAGO adapte l’ordre à tout moment où la situation l’exige, sans le redonner en entier.',
                'The FRAGO adapts the order whenever the situation requires it, without reissuing it in full.'
            ),
        ];

        return K::chapter(
            K::text('Chapitre 3 — Service Support, Command and Signal, FRAGO et briefing en jeu', 'Chapter 3 — Service Support, Command and Signal, FRAGO and the in-game briefing'),
            K::text($theoryFr, $theoryEn),
            K::text($demoFr, $demoEn),
            $guided,
            $simulation,
            $questions,
            K::text(
                "Rédigez un retour personnel sur le cas du pont détruit et votre évaluation :\n1. Le FRAGO que vous avez choisi et les éléments qu’il modifie.\n2. Comment vous vérifiez que chaque équipe l’a compris.\n3. Le plan PACE que vous donneriez à votre section.\n4. La méthode de répétition la plus utile en jeu selon vous, et pourquoi.\n5. Une amélioration concrète pour votre prochain briefing de mission.",
                "Write a personal reflection on the destroyed bridge case and your assessment:\n1. The FRAGO you chose and the elements it amends.\n2. How you check that each team has understood it.\n3. The PACE plan you would give your section.\n4. The rehearsal method you find most useful in the game, and why.\n5. One concrete improvement for your next mission briefing."
            ),
            [
                K::text('Le FRAGO proposé ne contient que les changements et mentionne « sans changement ».', 'The proposed FRAGO only contains the changes and states “no change”.'),
                K::text('Le plan PACE comprend quatre moyens réalistes en jeu.', 'The PACE plan includes four means that are realistic in the game.'),
                K::text('Confirmation brief, backbrief et répétition sont distingués.', 'Confirmation brief, backbrief and rehearsal are distinguished.'),
                K::text('L’amélioration proposée est concrète et vérifiable.', 'The proposed improvement is concrete and checkable.'),
            ],
            [
                K::text('L’évaluation atteint au moins 80 % dans le délai prévu.', 'The assessment reaches at least 80% within the time limit.'),
                K::text('Le stagiaire donne un ordre complet en cinq paragraphes, en jeu, en quinze minutes au plus pour une section.', 'The learner delivers a complete five-paragraph order, in the game, in fifteen minutes at most for a section.'),
                K::text('Le paragraphe 5 comprend succession, plan ACRE et PACE.', 'Paragraph 5 includes succession, the ACRE plan and PACE.'),
                K::text('Il conduit un confirmation brief ou un backbrief et émet un FRAGO collationné en cours de mission.', 'They conduct a confirmation brief or backbrief and issue a read-back FRAGO during the mission.'),
            ],
            [
                K::sheet(
                    K::text('Fiche mémo — Paragraphes 4 et 5, FRAGO et briefing', 'Memo card — Paragraphs 4 and 5, FRAGO and briefing'),
                    K::text(
                        "FORMATION DE JEU (ARMA 3) — sans valeur de qualification réelle.\n\n4. SERVICE SUPPORT (Sustainment / Administration and Logistics)\nLogistique : munitions, matériel, ravitaillement, transport.\nMédical : priorités, CCP, infirmiers, demande d’évacuation (médical ACE : bandages, garrots, transfusions).\nPrisonniers : regroupement, remise.\n\n5. COMMAND AND SIGNAL\nCommandement : position du chef et de l’échelon supérieur · succession (1, 2, 3).\nTransmissions : radios ACRE, canaux et nets, indicatifs, mots-code, signaux visuels et pyrotechniques.\nPACE : Primary · Alternate · Contingency · Emergency.\n\nAPRÈS L’ORDRE\nQuestions · heure (jeu) · confirmation brief · backbrief · répétition (carte, croquis, maquette, radio, à pied) · test radio sur chaque net.\n\nFRAGO\n« [Indicatifs], ici [chef]. FRAGO [n°]. » Situation : nouveauté · Mission : changée ou sans changement · Execution : tâches, itinéraires, PL, ralliement modifiés · « Le reste sans changement. » · Collationnement.\n\nBRIEFING EN JEU\nLieu sûr · carte ou ATAK prêts · un orateur · 10 à 15 min · note ATAK (mission, horaires, canaux).",
                        "IN-GAME TRAINING (ARMA 3) — no real-world qualification value.\n\n4. SERVICE SUPPORT (Sustainment / Administration and Logistics)\nLogistics: ammunition, equipment, resupply, transport.\nMedical: priorities, CCP, medics, evacuation request (ACE medical: bandages, tourniquets, transfusions).\nPrisoners: gathering, handover.\n\n5. COMMAND AND SIGNAL\nCommand: location of the leader and higher · succession (1, 2, 3).\nSignal: ACRE radios, channels and nets, callsigns, code words, visual and pyrotechnic signals.\nPACE: Primary · Alternate · Contingency · Emergency.\n\nAFTER THE ORDER\nQuestions · time check (game) · confirmation brief · backbrief · rehearsal (map, sketch, terrain model, radio, on foot) · radio check on every net.\n\nFRAGO\n“[Callsigns], this is [leader]. FRAGO [no.].” Situation: what is new · Mission: changed or no change · Execution: amended tasks, routes, PL, rally points · “Everything else no change.” · Read-back.\n\nIN-GAME BRIEFING\nSafe place · map or ATAK ready · one speaker · 10 to 15 min · ATAK note (mission, timings, channels)."
                    )
                ),
                K::sheet(
                    K::text('Sources publiques', 'Public sources'),
                    K::text(
                        "FORMATION DE JEU (ARMA 3) — sans valeur de qualification réelle.\n\nCe cours est rédigé à partir de sources publiques, reformulées et adaptées au jeu. Aucune donnée classifiée n’est utilisée. Les indicatifs, horaires, canaux et mots-code cités sont fictifs.\n\nWikipédia — Five paragraph order (SMEAC, variantes US et britannique, procédures de commandement)\nhttps://en.wikipedia.org/wiki/Five_paragraph_order\nWikipédia — Operations order\nhttps://en.wikipedia.org/wiki/Operations_order\nWikipédia — Warning order\nhttps://en.wikipedia.org/wiki/Warning_order\nWikipédia — Fragmentary order\nhttps://en.wikipedia.org/wiki/Fragmentary_order\nWikipédia — Commander’s intent\nhttps://en.wikipedia.org/wiki/Commander%27s_intent\nUS Army — FM 5-0 Planning and Orders Production et ATP 3-21.8 Infantry Platoon and Squad (publications publiques)\nhttps://armypubs.army.mil/\nACRE2 — documentation du mod radio\nhttps://acre2.idi-systems.com/",
                        "IN-GAME TRAINING (ARMA 3) — no real-world qualification value.\n\nThis course is written from public sources, rephrased and adapted to the game. No classified data is used. The callsigns, timings, channels and code words quoted are fictitious.\n\nWikipedia — Five paragraph order (SMEAC, US and British variants, troop leading procedures)\nhttps://en.wikipedia.org/wiki/Five_paragraph_order\nWikipedia — Operations order\nhttps://en.wikipedia.org/wiki/Operations_order\nWikipedia — Warning order\nhttps://en.wikipedia.org/wiki/Warning_order\nWikipedia — Fragmentary order\nhttps://en.wikipedia.org/wiki/Fragmentary_order\nWikipedia — Commander’s intent\nhttps://en.wikipedia.org/wiki/Commander%27s_intent\nUS Army — FM 5-0 Planning and Orders Production and ATP 3-21.8 Infantry Platoon and Squad (public publications)\nhttps://armypubs.army.mil/\nACRE2 — radio mod documentation\nhttps://acre2.idi-systems.com/"
                    )
                ),
            ]
        );
    }
}
