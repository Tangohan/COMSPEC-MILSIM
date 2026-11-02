<?php

declare(strict_types=1);

namespace App\Services\Academy\BuiltIn;

use App\Services\Academy\BuiltIn\AcademyCourseKit as K;

/**
 * Cours intégré « Prévention des tirs fratricides et identification au combat (CID) » : causes des tirs amis,
 * identification positive (PID) avant le tir, marquage des amis de jour et de nuit, mesures de coordination simples
 * et réaction à un tir ami. Inspiré des principes alliés publics d’identification au combat (Combat Identification,
 * exercices multinationaux Bold Quest) et des manuels publics de l’US Army ; formation de jeu Arma 3 (ACE, ACRE, ATAK)
 * sans valeur de qualification réelle.
 */
final class NatoFratricidePreventionCourse
{
    public const KEY = 'otan-prevention-tirs-fratricides';
    public const VERSION = '1.0.0';

    /** @return array<string, mixed> */
    public static function course(): array
    {
        return K::course(
            self::KEY,
            self::VERSION,
            K::text('Prévention des tirs fratricides et identification au combat (CID)', 'Fratricide Prevention and Combat Identification (CID)'),
            K::text(
                'Comprendre pourquoi les tirs fratricides surviennent dans nos missions Arma 3 et les prévenir : identification positive avant tout tir, marquage des amis de jour comme de nuit (fumigènes, chemlights, strobes IR, panneaux, ATAK et BFT), mesures de coordination simples, secteurs de tir et réaction immédiate à un tir ami (« CHECK FIRE »). Formation de jeu inspirée des principes alliés publics d’identification au combat, sans valeur de qualification réelle.',
                'Understand why fratricide happens in our Arma 3 missions and prevent it: positive identification before any shot, marking friendlies by day and by night (smoke, chemlights, IR strobes, panels, ATAK and BFT), simple coordination measures, sectors of fire and the immediate reaction to friendly fire (“CHECK FIRE”). In-game training inspired by public allied combat identification principles, with no real-world qualification value.'
            ),
            120,
            [self::chapterOne(), self::chapterTwo(), self::chapterThree()],
            [
                'code' => 'ACAD-OTAN-CID',
                'license_code' => 'CID',
                'name' => K::text('Qualification Athena Academy — Prévention des tirs fratricides et identification au combat', 'Athena Academy qualification — Fratricide prevention and combat identification'),
                'short_name' => 'CID',
                'description' => K::text(
                    'Le titulaire identifie positivement une cible avant de tirer, sait marquer sa position et celle de son groupe de jour comme de nuit (fumigènes, chemlights, strobe IR, ATAK, BFT) et applique les secteurs de tir et les mesures de coordination de l’ordre. En cas de tir ami, il lance ou relaie immédiatement un « CHECK FIRE » et rend compte. Qualification de jeu inspirée des principes alliés publics d’identification au combat, sans valeur réelle.',
                    'The holder positively identifies a target before firing, can mark their own and their group’s position by day and by night (smoke, chemlights, IR strobe, ATAK, BFT) and applies the sectors of fire and coordination measures given in the order. In the event of friendly fire, they immediately call or relay a “CHECK FIRE” and report. In-game qualification inspired by public allied combat identification principles, with no real-world value.'
                ),
                'reference' => K::text(
                    'Inspirée des principes publics de l’identification au combat (Combat Identification) testés par l’OTAN et ses alliés lors des exercices Bold Quest, et des manuels publics de l’US Army sur les signaux visuels (TC 3-21.60) et la section d’infanterie (ATP 3-21.8)',
                    'Inspired by the public principles of combat identification tested by NATO and its allies in the Bold Quest exercises, and by the public US Army manuals on visual signals (TC 3-21.60) and the infantry platoon and squad (ATP 3-21.8)'
                ),
                'validity_months' => null,
            ]
        );
    }

    // ------------------------------------------------------------------ Chapitre 1 — Causes et PID

    /** @return array<string, mixed> */
    private static function chapterOne(): array
    {
        $theoryFr = <<<'FR'
OBJECTIF DE LA FORMATION
Ce cours apprend à éviter les tirs fratricides dans nos missions Arma 3 : tirer sur un joueur ou une IA de son propre camp. Il s’inspire des principes alliés publics d’identification au combat (Combat Identification, CID), que l’OTAN et ses partenaires testent ensemble depuis les années 2000 lors des exercices multinationaux Bold Quest. Trois chapitres : causes et identification positive, marquage des amis, coordination et réaction à un tir ami. Les termes OTAN restent en anglais à la radio. Il s’agit d’une formation de jeu : la qualification Athena Academy délivrée atteste un parcours interne, sans valeur de qualification réelle, militaire ou autre.

01 — Qu’est-ce qu’un tir fratricide ?
Un tir fratricide (« blue on blue », « friendly fire ») est un tir, de n’importe quelle arme, qui atteint des forces amies alors qu’il visait l’ennemi. En jeu, il peut venir d’un fusil, d’un véhicule, d’un mortier, d’un hélicoptère ou d’un avion piloté par un joueur. Il coûte des vies, ralentit la mission et casse la confiance entre groupes : après un incident, plus personne n’ose tirer.

02 — Les trois grandes causes
- Erreur de position : on croit les amis ailleurs. Navigation approximative, groupe qui a dépassé sa limite, position annoncée fausse ou périmée.
- Erreur d’identification : on prend un ami pour un ennemi. Silhouette au crépuscule, tenue proche de celle de l’adversaire, vue partielle derrière un mur.
- Défaut de coordination : ordre flou, secteurs de tir qui se croisent, groupe qui change de route sans prévenir, radio saturée.
Des facteurs aggravent tout : stress du contact, nuit, fumée, zone bâtie, fatigue en fin de mission.

03 — L’identification au combat (CID)
Dans les principes alliés publics, l’identification au combat associe deux choses : savoir où sont les amis (connaissance de la situation) et identifier ce que l’on voit avant de décider de tirer. Les exercices Bold Quest servent justement à vérifier que les systèmes d’identification des différents pays fonctionnent ensemble, au sol comme dans les airs. En jeu, notre « système » est fait de l’ordre, de la radio ACRE, de la carte, d’ATAK et des marquages convenus.

04 — L’identification positive (PID)
Avant tout tir, le tireur doit avoir l’identification positive (PID) : une certitude raisonnable que la cible est un ennemi. Indices utiles : arme portée ou employée contre vous, tenue et équipement, comportement hostile, position par rapport aux lignes amies, cohérence avec ce que l’ordre annonce. Une silhouette, un mouvement ou un bruit seuls ne suffisent pas.

05 — Les quatre questions avant d’appuyer sur la détente
1. Où sont mes amis ? (ordre, radio, ATAK, BFT)
2. Ai-je la PID ?
3. La cible est-elle dans mon secteur ?
4. Qu’y a-t-il derrière et autour de la cible, sur la ligne de tir ?
Une seule réponse incertaine : on ne tire pas, on observe, on signale, on demande.

06 — L’absence de preuve n’est pas une preuve
Un joueur sans marqueur ATAK n’est pas forcément ennemi : il peut avoir un terminal éteint, être déconnecté ou appartenir à un groupe que vous ne voyez pas. Inversement, un repère ami sur la carte peut être ancien. Les civils et les neutres présents dans certaines missions relèvent de la même règle : pas de PID, pas de tir.

07 — Ce qu’il faut retenir
Trois causes : position, identification, coordination. Avant le tir : amis localisés, PID, secteur, ligne de tir. Le doute impose de ne pas tirer et de rendre compte.
FR;
        $theoryEn = <<<'EN'
TRAINING OBJECTIVE
This course teaches how to avoid fratricide in our Arma 3 missions: firing on a player or AI from your own side. It is inspired by public allied combat identification (CID) principles, which NATO and its partners have tested together since the 2000s in the multinational Bold Quest exercises. Three chapters: causes and positive identification, marking friendlies, coordination and reaction to friendly fire. NATO terms stay in English on the radio. This is in-game training: the Athena Academy qualification awarded records an internal pathway and has no real-world qualification value, military or otherwise.

01 — What is fratricide?
Fratricide (“blue on blue”, “friendly fire”) is fire from any weapon that hits friendly forces while aimed at the enemy. In the game it may come from a rifle, a vehicle, a mortar, a helicopter or a jet flown by a player. It costs lives, slows the mission and breaks trust between groups: after an incident, nobody dares to fire.

02 — The three main causes
- Position error: friendlies are believed to be elsewhere. Rough navigation, a group that has gone beyond its limit, a wrong or out-of-date reported position.
- Identification error: a friendly is taken for an enemy. A silhouette at dusk, clothing similar to the opponent’s, a partial view behind a wall.
- Coordination failure: a vague order, overlapping sectors of fire, a group changing route without warning, a saturated radio net.
Some factors make everything worse: the stress of contact, darkness, smoke, built-up areas, fatigue at the end of a mission.

03 — Combat identification (CID)
In public allied principles, combat identification combines two things: knowing where friendlies are (situational awareness) and identifying what you see before deciding to fire. The Bold Quest exercises exist precisely to check that the identification systems of different nations work together, on the ground and in the air. In the game, our “system” is made of the order, the ACRE radio, the map, ATAK and the agreed markings.

04 — Positive identification (PID)
Before any shot, the shooter must have positive identification (PID): reasonable certainty that the target is an enemy. Useful indicators: a weapon carried or used against you, clothing and equipment, hostile behaviour, position relative to friendly lines, consistency with what the order states. A silhouette, a movement or a noise alone is not enough.

05 — The four questions before pulling the trigger
1. Where are my friendlies? (order, radio, ATAK, BFT)
2. Do I have PID?
3. Is the target in my sector?
4. What is behind and around the target, along the line of fire?
If any answer is uncertain: do not fire, observe, report, ask.

06 — Absence of evidence is not evidence
A player with no ATAK marker is not necessarily an enemy: their terminal may be off, they may have disconnected or belong to a group you cannot see. Conversely, a friendly marker on the map may be old. Civilians and neutrals present in some missions fall under the same rule: no PID, no shot.

07 — Key points
Three causes: position, identification, coordination. Before firing: friendlies located, PID, sector, line of fire. Doubt means you do not fire and you report.
EN;
        $demoFr = <<<'FR'
DÉMONSTRATION COMMENTÉE — SILHOUETTES DANS LA HAIE
Situation de jeu : au crépuscule, le groupe « Kodiak 2 » tient la lisière nord d’un village. Le groupe voisin « Kodiak 3 » doit contourner le village par l’est. Le mitrailleur de Kodiak 2 aperçoit des silhouettes dans une haie, à 250 m au nord-est.

Mitrailleur : « Chef, mouvement dans la haie, nord-est, 250. Quatre personnels. Je ne vois pas d’armes. »
Commentaire : il signale au lieu de tirer. Il donne direction, distance, nombre, et dit ce qu’il ne voit pas.

Chef Kodiak 2 (consulte ATAK) : « Le dernier repère de Kodiak 3 date de cinq minutes, à 200 m de cette haie. Personne ne tire. »
Commentaire : la position amie est proche et ancienne : le doute est réel. L’ordre de ne pas tirer est clair et immédiat.

Chef Kodiak 2 (radio ACRE, net de section) : « Kodiak 3, Kodiak 2. Confirmez votre position. J’observe quatre personnels dans la haie au nord-est du village. »
Kodiak 3 : « Kodiak 2, Kodiak 3. Nous sommes dans la haie au nord-est du village, en progression vers l’est. »
Commentaire : la question est posée sur le net partagé, avec un repère que les deux groupes voient. La réponse lève le doute.

Chef Kodiak 2 : « Reçu. Kodiak 2 identifie Kodiak 3 dans la haie. Mitrailleur, secteur limité à gauche de la haie. »
Commentaire : le chef ajuste le secteur de tir pour que l’arme ne pointe plus vers les amis.

À RETENIR
Le mitrailleur n’avait pas la PID. Le chef a croisé ATAK, la radio et la carte avant toute décision. Dix secondes de vérification ont évité un tir fratricide.
FR;
        $demoEn = <<<'EN'
ANNOTATED DEMONSTRATION — FIGURES IN THE HEDGEROW
Game situation: at dusk, group “Kodiak 2” holds the northern edge of a village. The neighbouring group “Kodiak 3” is to bypass the village to the east. Kodiak 2’s machine gunner spots figures in a hedgerow, 250 m to the north-east.

Gunner: “Boss, movement in the hedgerow, north-east, 250. Four personnel. I can’t see weapons.”
Comment: they report instead of firing. They give direction, distance, number, and say what they cannot see.

Kodiak 2 lead (checks ATAK): “Kodiak 3’s last marker is five minutes old, 200 m from that hedgerow. Nobody fires.”
Comment: the friendly position is close and old: the doubt is real. The order not to fire is clear and immediate.

Kodiak 2 lead (ACRE radio, section net): “Kodiak 3, Kodiak 2. Confirm your position. I see four personnel in the hedgerow north-east of the village.”
Kodiak 3: “Kodiak 2, Kodiak 3. We are in the hedgerow north-east of the village, moving east.”
Comment: the question is asked on the shared net, with a reference both groups can see. The answer removes the doubt.

Kodiak 2 lead: “Copy. Kodiak 2 identifies Kodiak 3 in the hedgerow. Gunner, sector limited to left of the hedgerow.”
Comment: the leader adjusts the sector of fire so that the weapon no longer points at friendlies.

KEY POINT
The gunner did not have PID. The leader cross-checked ATAK, the radio and the map before any decision. Ten seconds of checking prevented a fratricide.
EN;
        $guided = K::stage(
            K::question(
                'Que faites-vous face à ce camion ?',
                'What do you do about this truck?',
                [
                    K::text('Vous ouvrez le feu : il vient de la direction de l’ennemi.', 'You open fire: it comes from the enemy’s direction.'),
                    K::text('Vous tirez des coups de semonce pour le faire s’arrêter.', 'You fire warning shots to make it stop.'),
                    K::text('Vous ne tirez pas : vous gardez le camion en observation, vous signalez au chef et vous demandez sur le net si un véhicule ami est attendu sur cette route.', 'You do not fire: you keep the truck under observation, report to your leader and ask on the net whether a friendly vehicle is expected on that road.'),
                ],
                2,
                'Une direction ne donne pas la PID : un véhicule ami peut revenir d’une mission ou s’être trompé de route. Observer, signaler et demander lève le doute ; les coups de semonce ne l’identifient pas et peuvent déclencher un échange de tirs entre amis.',
                'A direction does not give PID: a friendly vehicle may be returning from a task or have taken the wrong road. Observing, reporting and asking remove the doubt; warning shots do not identify it and may start an exchange of fire between friendlies.'
            ),
            K::text(
                "CAS GUIDÉ — LE CAMION SANS MARQUEUR\nVous êtes tireur dans le groupe Kodiak 2, en position défensive au carrefour. Un camion arrive par la route du nord, la direction de l’ennemi, feux éteints, à 400 m. Il n’apparaît pas sur ATAK. Vous ne voyez pas encore d’arme ni d’insigne. Votre chef a rappelé que des moyens logistiques amis circulent dans le secteur.\n\nTravaillez dans cet ordre :\n1. Reprenez les quatre questions avant le tir.\n2. Demandez-vous si l’absence de marqueur ATAK prouve que le camion est ennemi.\n3. Identifiez qui peut lever le doute et par quel moyen.\n4. Choisissez la réponse qui respecte la PID.",
                "GUIDED CASE — THE TRUCK WITH NO MARKER\nYou are a rifleman in group Kodiak 2, in a defensive position at the crossroads. A truck is approaching on the northern road, the enemy’s direction, lights off, 400 m away. It does not appear on ATAK. You cannot yet see a weapon or insignia. Your leader reminded you that friendly logistics vehicles are moving in the area.\n\nWork in this order:\n1. Go through the four questions before firing.\n2. Ask yourself whether the absence of an ATAK marker proves the truck is an enemy.\n3. Identify who can remove the doubt and by what means.\n4. Choose the answer that respects PID."
            ),
            K::text(
                'Relisez les sections 04 et 06 : une direction et l’absence de marqueur ne sont pas des preuves.',
                'Reread sections 04 and 06: a direction and a missing marker are not evidence.'
            )
        );
        $simulation = K::stage(
            K::question(
                'Quelle est la bonne réaction ?',
                'What is the right reaction?',
                [
                    K::text('« Personnel armé dans la cour, nord, 30 mètres, tenue proche de la nôtre. Personne ne tire. » Puis vous demandez sur le net qui est dans la ferme, en gardant l’individu en visée.', '“Armed person in the courtyard, north, 30 metres, clothing similar to ours. Nobody fires.” Then you ask on the net who is in the farm, keeping the individual in your sights.'),
                    K::text('Vous tirez : il est armé, la PID est acquise.', 'You fire: they are armed, so PID is established.'),
                    K::text('Vous lancez une grenade dans la cour pour ne prendre aucun risque.', 'You throw a grenade into the courtyard to take no chances.'),
                ],
                0,
                'Une arme seule ne suffit pas quand les amis portent une tenue voisine et peuvent être proches. On gèle les tirs, on signale précisément et on lève le doute par la radio, tout en restant prêt à réagir si l’individu ouvre le feu. La grenade ne fait qu’aggraver le risque.',
                'A weapon alone is not enough when friendlies wear similar clothing and may be close by. You freeze fire, report precisely and remove the doubt by radio, while staying ready to react if the individual opens fire. The grenade only makes the risk worse.'
            ),
            K::text(
                "SIMULATION AUTONOME — LA COUR DE LA FERME\nVous êtes chef d’équipe dans Kodiak 2, en nettoyage d’une ferme de nuit, JVN abaissées. Dans cette mission, l’adversaire porte une tenue proche de la vôtre. Kodiak 1 nettoie le bâtiment voisin, mais personne n’a annoncé sa position depuis plusieurs minutes. Un individu armé apparaît dans la cour, à 30 m, l’arme basse, et ne vous a pas vus.\n\nVotre objectif : décider sans tirer sur un ami ni exposer votre équipe. Aucune aide n’est donnée pendant cette étape ; vous pourrez revoir le cours après une tentative.",
                "INDEPENDENT SIMULATION — THE FARM COURTYARD\nYou are a team leader in Kodiak 2, clearing a farm at night with NVGs down. In this mission the opponent wears clothing similar to yours. Kodiak 1 is clearing the neighbouring building, but nobody has reported its position for several minutes. An armed individual appears in the courtyard, 30 m away, weapon lowered, and has not seen you.\n\nYour objective: decide without firing on a friendly or exposing your team. No hint is given during this stage; you may review the course after an attempt."
            )
        );
        $questions = [
            K::question(
                'Quelles sont les trois grandes causes de tirs fratricides ?',
                'What are the three main causes of fratricide?',
                [
                    K::text('Erreurs de position, erreurs d’identification, défauts de coordination.', 'Position errors, identification errors, coordination failures.'),
                    K::text('Munitions défectueuses, météo, latence du serveur.', 'Faulty ammunition, weather, server lag.'),
                    K::text('Manque de munitions, manque de radios, manque de véhicules.', 'Lack of ammunition, lack of radios, lack of vehicles.'),
                ],
                0,
                'On croit les amis ailleurs, on les prend pour l’ennemi, ou l’ordre et la coordination sont défaillants. La météo et le stress aggravent, mais ne sont pas les causes de fond.',
                'Friendlies are believed to be elsewhere, they are mistaken for the enemy, or the order and coordination fail. Weather and stress make it worse, but are not the root causes.'
            ),
            K::question(
                'Que signifie avoir la PID sur une cible ?',
                'What does having PID on a target mean?',
                [
                    K::text('Avoir la cible dans son viseur.', 'Having the target in your sights.'),
                    K::text('Avoir une certitude raisonnable que la cible est un ennemi.', 'Having reasonable certainty that the target is an enemy.'),
                    K::text('Avoir reçu sa position par ATAK.', 'Having received its position via ATAK.'),
                ],
                1,
                'L’identification positive est une certitude raisonnable fondée sur plusieurs indices (arme, tenue, comportement, position), pas un simple contact visuel.',
                'Positive identification is reasonable certainty based on several indicators (weapon, clothing, behaviour, position), not mere visual contact.'
            ),
            K::question(
                'Un joueur n’apparaît pas sur ATAK. Qu’en déduisez-vous ?',
                'A player does not appear on ATAK. What do you conclude?',
                [
                    K::text('Qu’il est forcément ennemi.', 'That they must be an enemy.'),
                    K::text('Qu’il est forcément civil.', 'That they must be a civilian.'),
                    K::text('Rien de sûr : son terminal peut être éteint ou il peut appartenir à un groupe que vous ne voyez pas.', 'Nothing certain: their terminal may be off or they may belong to a group you cannot see.'),
                ],
                2,
                'L’absence de marqueur n’est pas une preuve. Seule la PID autorise le tir.',
                'A missing marker is not evidence. Only PID allows you to fire.'
            ),
            K::question(
                'Pourquoi regarder derrière la cible avant de tirer ?',
                'Why look behind the target before firing?',
                [
                    K::text('Parce que des amis peuvent se trouver sur la ligne de tir, au-delà de la cible.', 'Because friendlies may be on the line of fire, beyond the target.'),
                    K::text('Pour choisir la meilleure munition.', 'To choose the best ammunition.'),
                    K::text('Pour évaluer la distance de la cible.', 'To estimate the range to the target.'),
                ],
                0,
                'Un tir manqué ou traversant continue sa course. La ligne de tir fait partie des quatre questions.',
                'A missed or penetrating round keeps travelling. The line of fire is one of the four questions.'
            ),
            K::question(
                'Vous avez un doute sur l’identité d’un groupe en mouvement. Que faites-vous ?',
                'You are unsure who a moving group is. What do you do?',
                [
                    K::text('Vous tirez une courte rafale pour voir s’il riposte.', 'You fire a short burst to see whether it returns fire.'),
                    K::text('Vous ne tirez pas, vous l’observez, vous le signalez et vous demandez à la radio.', 'You do not fire, you observe it, report it and ask on the radio.'),
                    K::text('Vous attendez la fin de la mission pour en parler.', 'You wait until the end of the mission to mention it.'),
                ],
                1,
                'Le doute impose de ne pas tirer et de chercher l’information. Une rafale « pour voir » est précisément la manière de déclencher un tir fratricide.',
                'Doubt means not firing and seeking information. A burst “to see” is exactly how fratricide starts.'
            ),
            K::question(
                'Selon les principes alliés publics, l’identification au combat associe :',
                'According to public allied principles, combat identification combines:',
                [
                    K::text('La puissance de feu et la vitesse.', 'Firepower and speed.'),
                    K::text('Le camouflage et la discrétion radio.', 'Camouflage and radio silence.'),
                    K::text('La connaissance de la position des amis et l’identification de ce que l’on voit avant de tirer.', 'Knowing where friendlies are and identifying what you see before firing.'),
                ],
                2,
                'Savoir où sont les amis et identifier la cible : ces deux éléments, ensemble, permettent de décider d’engager sans erreur.',
                'Knowing where friendlies are and identifying the target: together, these two elements allow a decision to engage without error.'
            ),
            K::question(
                'À quoi servent les exercices multinationaux Bold Quest ?',
                'What are the multinational Bold Quest exercises for?',
                [
                    K::text('À vérifier que les systèmes d’identification au combat de différents pays fonctionnent ensemble, pour réduire les tirs fratricides.', 'To check that the combat identification systems of different nations work together, in order to reduce fratricide.'),
                    K::text('À former les pilotes au vol de nuit.', 'To train pilots in night flying.'),
                    K::text('À tester de nouvelles munitions.', 'To test new ammunition.'),
                ],
                0,
                'Bold Quest est une évaluation multinationale de l’interopérabilité, centrée sur l’identification au combat, au sol comme dans les airs.',
                'Bold Quest is a multinational interoperability assessment focused on combat identification, on the ground and in the air.'
            ),
        ];

        return K::chapter(
            K::text('Chapitre 1 — Causes des tirs fratricides et identification positive (PID)', 'Chapter 1 — Causes of fratricide and positive identification (PID)'),
            K::text($theoryFr, $theoryEn),
            K::text($demoFr, $demoEn),
            $guided,
            $simulation,
            $questions,
            K::text(
                "Rédigez un retour personnel sur le cas de la ferme et votre évaluation :\n1. Les indices dont vous disposiez et ceux qui manquaient pour la PID.\n2. L’ordre exact que vous avez donné à votre équipe.\n3. Le moyen choisi pour lever le doute et pourquoi.\n4. Une situation de vos dernières missions où un tir fratricide a été évité ou non.\n5. Une habitude concrète à adopter avant chaque tir lors de votre prochaine mission.",
                "Write a personal reflection on the farm case and your assessment:\n1. The indicators you had and those missing for PID.\n2. The exact order you gave your team.\n3. The means chosen to remove the doubt and why.\n4. A situation from your recent missions where fratricide was or was not avoided.\n5. One concrete habit to adopt before every shot in your next mission."
            ),
            [
                K::text('Les trois causes (position, identification, coordination) sont citées et reliées au cas.', 'The three causes (position, identification, coordination) are named and linked to the case.'),
                K::text('La PID est définie comme une certitude raisonnable fondée sur plusieurs indices.', 'PID is defined as reasonable certainty based on several indicators.'),
                K::text('Le moyen de lever le doute (radio, ATAK, observation) est précis.', 'The means of removing the doubt (radio, ATAK, observation) is specific.'),
                K::text('L’habitude proposée est concrète et vérifiable en mission.', 'The proposed habit is concrete and can be checked in a mission.'),
            ],
            [
                K::text('L’évaluation atteint au moins 80 % dans le délai prévu.', 'The assessment reaches at least 80% within the time limit.'),
                K::text('Le stagiaire énonce les quatre questions avant le tir, dans l’ordre.', 'The learner states the four questions before firing, in order.'),
                K::text('Face à un doute, il gèle le tir et rend compte au lieu d’engager.', 'Faced with doubt, they freeze fire and report instead of engaging.'),
                K::text('Il ne traite jamais l’absence de marqueur ATAK ou BFT comme une identification ennemie.', 'They never treat a missing ATAK or BFT marker as enemy identification.'),
            ],
            [
                K::sheet(
                    K::text('Fiche mémo — Avant le tir : PID', 'Memo card — Before firing: PID'),
                    K::text(
                        "FORMATION DE JEU (ARMA 3) — sans valeur de qualification réelle.\n\nTROIS CAUSES DE TIR FRATRICIDE\nPosition (amis ailleurs que prévu) · Identification (ami pris pour ennemi) · Coordination (ordre flou, secteurs croisés, radio).\n\nQUATRE QUESTIONS AVANT LE TIR\n1. Où sont mes amis ? (ordre, radio ACRE, ATAK, BFT)\n2. Ai-je la PID ? (arme employée, tenue, comportement, position, cohérence avec l’ordre)\n3. La cible est-elle dans mon secteur ?\n4. Qu’y a-t-il derrière et autour, sur la ligne de tir ?\n\nUN DOUTE = PAS DE TIR\nObserver · signaler (direction, distance, nombre, ce que je ne vois pas) · demander sur le net.\nPas de marqueur ATAK ≠ ennemi. Repère ami ancien ≠ position actuelle.",
                        "IN-GAME TRAINING (ARMA 3) — no real-world qualification value.\n\nTHREE CAUSES OF FRATRICIDE\nPosition (friendlies elsewhere than expected) · Identification (friendly taken for enemy) · Coordination (vague order, overlapping sectors, radio).\n\nFOUR QUESTIONS BEFORE FIRING\n1. Where are my friendlies? (order, ACRE radio, ATAK, BFT)\n2. Do I have PID? (weapon used, clothing, behaviour, position, consistency with the order)\n3. Is the target in my sector?\n4. What is behind and around it, along the line of fire?\n\nANY DOUBT = NO SHOT\nObserve · report (direction, distance, number, what I cannot see) · ask on the net.\nNo ATAK marker ≠ enemy. Old friendly marker ≠ current position."
                    )
                ),
            ]
        );
    }

    // ------------------------------------------------------------------ Chapitre 2 — Marquage

    /** @return array<string, mixed> */
    private static function chapterTwo(): array
    {
        $theoryFr = <<<'FR'
01 — Pourquoi se marquer
Se marquer, c’est se rendre identifiable pour ceux qui peuvent tirer : groupes voisins, véhicules, mortiers, hélicoptères, avions. Un marquage n’a de valeur que s’il a été convenu à l’avance, dans l’ordre (instructions de coordination, commandement et transmissions) : un marquage improvisé peut être pris pour un signal ennemi ou une cible.

02 — De jour : fumigènes
Arma 3 et ACE offrent plusieurs couleurs de fumigènes (blanc, rouge, vert, jaune, violet, bleu, orange). Ils servent à signaler une position, à marquer une zone de poser ou une cible, ou à masquer. Règle essentielle : quand un ami marque sa position, c’est l’observateur (pilote, groupe voisin) qui annonce la couleur qu’il voit, et celui qui a lancé le fumigène confirme. On n’annonce pas la couleur avant : l’ennemi peut lancer la même fumée et l’imiter.

03 — De jour : panneaux de signalisation
Les manuels publics de signaux visuels décrivent des panneaux de couleur fluorescente (comme le VS-17) posés au sol pour marquer une position, un flanc ou une zone de poser vers des aéronefs. Dans nos missions, on les emploie si la mission en fournit un objet équivalent ; sinon, un véhicule ou un repère ATAK annoncé le remplace.

04 — De nuit : lumière visible et infrarouge
- Chemlights ACE (blanc, rouge, vert, jaune, orange, bleu, et versions « Hi » plus brillantes mais de courte durée) : marquer un point de passage, un poste de secours, un véhicule, selon le code fixé par l’ordre.
- Strobe IR et grenade IR : visibles aux jumelles de vision nocturne (JVN). Avec ACE, on peut fixer un strobe IR, une grenade IR ou une chemlight sur sa tenue ou sur un véhicule (interaction ACE « Attacher »).
Attention : tout porteur de JVN, ennemi compris, voit l’infrarouge. On allume quand l’ordre ou le chef le décide, pas en permanence.

05 — Marquage numérique : ATAK et BFT
ATAK en jeu partage les positions et les repères (objectifs, points de jonction, positions amies). Le traceur de forces amies d’ACE (Blue Force Tracking, si la mission l’active) marque la position des chefs de groupe sur la carte, pas celle de chaque joueur. Limites : un repère manuel vieillit, un terminal peut être éteint, un groupe peut s’étirer loin de son chef. Datez vos repères, supprimez-les quand ils sont dépassés.

06 — Signaux de reconnaissance et mots convenus
Pour une jonction (link-up) ou un retour dans les lignes, l’ordre fixe :
- un signal de reconnaissance lointain (visuel : strobe IR, chemlight, fumigène, geste) ;
- un signal proche, verbal : mot d’appel et mot de réponse (challenge et password). Celui qui garde la position prononce le mot d’appel, l’arrivant répond.
Le premier qui doute s’arrête, se met à couvert et appelle à la radio.

07 — Ce qu’il faut retenir
Un marquage convenu, annoncé, vérifié. La couleur du fumigène est annoncée par l’observateur. L’IR se voit aussi chez l’ennemi. ATAK et BFT aident sans remplacer la PID.
FR;
        $theoryEn = <<<'EN'
01 — Why mark yourself
Marking yourself means making yourself identifiable to those who can fire: neighbouring groups, vehicles, mortars, helicopters, jets. A mark only has value if it was agreed in advance, in the order (coordinating instructions, command and signal): an improvised mark may be taken for an enemy signal or a target.

02 — By day: smoke
Arma 3 and ACE offer several smoke colours (white, red, green, yellow, purple, blue, orange). They are used to signal a position, mark a landing zone or a target, or screen. Essential rule: when a friendly marks their position, it is the observer (pilot, neighbouring group) who announces the colour they see, and whoever threw the smoke confirms. The colour is not announced beforehand: the enemy can throw the same smoke and imitate it.

03 — By day: marker panels
Public visual signals manuals describe fluorescent-coloured panels (such as the VS-17) laid on the ground to mark a position, a flank or a landing zone for aircraft. In our missions they are used if the mission provides an equivalent object; otherwise an announced vehicle or ATAK marker replaces them.

04 — By night: visible and infrared light
- ACE chemlights (white, red, green, yellow, orange, blue, and brighter but short-lived “Hi” versions): mark a crossing point, an aid post, a vehicle, according to the code set by the order.
- IR strobe and IR grenade: visible through night-vision goggles (NVGs). With ACE, an IR strobe, IR grenade or chemlight can be attached to your uniform or to a vehicle (ACE “Attach” interaction).
Caution: anyone wearing NVGs, including the enemy, sees infrared. Switch it on when the order or the leader decides, not permanently.

05 — Digital marking: ATAK and BFT
In-game ATAK shares positions and markers (objectives, link-up points, friendly positions). ACE’s Blue Force Tracking, if enabled by the mission, marks the position of group leaders on the map, not every player. Limits: a manual marker ages, a terminal may be off, a group may stretch far from its leader. Time-stamp your markers and delete them once out of date.

06 — Recognition signals and agreed words
For a link-up or a return through friendly lines, the order sets:
- a far recognition signal (visual: IR strobe, chemlight, smoke, gesture);
- a near, verbal signal: challenge and password. Whoever holds the position speaks the challenge, the arriving party replies.
The first one in doubt stops, takes cover and calls on the radio.

07 — Key points
A mark that is agreed, announced, checked. The smoke colour is announced by the observer. Infrared is visible to the enemy too. ATAK and BFT help without replacing PID.
EN;
        $demoFr = <<<'FR'
DÉMONSTRATION COMMENTÉE — JONCTION DE NUIT
Situation de jeu : de nuit, l’équipe de reconnaissance « Spectre » rejoint la section « Kodiak » tenant une ferme. L’ordre a fixé : signal lointain = strobe IR allumé cinq secondes au point de jonction ; mot d’appel « ROCHER », mot de réponse « FALAISE ».

Spectre (radio ACRE) : « Kodiak, Spectre. 200 mètres au sud du point de jonction, prêt pour la jonction. »
Kodiak : « Spectre, Kodiak. Reçu, envoyez le signal lointain. »
Commentaire : on annonce son approche avant d’entrer dans la zone de tir de la position amie.

Spectre : « Signal en cours. »
Kodiak (sentinelle, JVN) : « Kodiak, je vois un strobe IR au sud, au point de jonction. »
Commentaire : le signal est attendu, au bon endroit, et décrit par celui qui l’observe.

Kodiak : « Spectre, Kodiak. Signal reçu. Avancez, un seul homme. »
Sentinelle (à voix haute, quand l’homme est à 15 mètres) : « ROCHER ! »
Spectre 1 : « FALAISE. »
Commentaire : le mot d’appel vient de la position, la réponse de l’arrivant. Un seul homme s’avance pour limiter le risque.

Kodiak : « Jonction effectuée. Spectre, entrez par le chemin est. Je mets à jour ATAK. »
Commentaire : le chef met à jour le repère : tout le monde sait que Spectre est désormais dans la ferme.

À RETENIR
Signal lointain convenu, annonce radio, mots convenus, mise à jour ATAK. Aucune étape ne se fait « au jugé ».
FR;
        $demoEn = <<<'EN'
ANNOTATED DEMONSTRATION — NIGHT LINK-UP
Game situation: at night, the reconnaissance team “Spectre” joins section “Kodiak” holding a farm. The order set: far signal = IR strobe switched on for five seconds at the link-up point; challenge “ROCK”, password “CLIFF”.

Spectre (ACRE radio): “Kodiak, Spectre. 200 metres south of the link-up point, ready for link-up.”
Kodiak: “Spectre, Kodiak. Copy, send the far signal.”
Comment: you announce your approach before entering the friendly position’s field of fire.

Spectre: “Signal now.”
Kodiak (sentry, NVGs): “Kodiak, I see an IR strobe to the south, at the link-up point.”
Comment: the signal is expected, in the right place, and described by the person observing it.

Kodiak: “Spectre, Kodiak. Signal received. Come forward, one man.”
Sentry (aloud, when the man is 15 metres away): “ROCK!”
Spectre 1: “CLIFF.”
Comment: the challenge comes from the position, the password from the arriving party. Only one man comes forward to limit the risk.

Kodiak: “Link-up complete. Spectre, come in by the eastern track. I am updating ATAK.”
Comment: the leader updates the marker: everyone knows Spectre is now inside the farm.

KEY POINT
Agreed far signal, radio announcement, agreed words, ATAK update. No step is done “by eye”.
EN;
        $guided = K::stage(
            K::question(
                'Quel échange est correct pour marquer votre position ?',
                'Which exchange is correct for marking your position?',
                [
                    K::text('« Tusker 1, Kodiak 2, fumée lancée. Annoncez la couleur. » — « Je vois violet. » — « Violet confirmé, c’est nous. »', '“Tusker 1, Kodiak 2, smoke out. Call the colour.” — “I see purple.” — “Purple confirmed, that’s us.”'),
                    K::text('« Tusker 1, Kodiak 2, fumée violette lancée, posez-vous à côté de la violette. »', '“Tusker 1, Kodiak 2, purple smoke out, land next to the purple.”'),
                    K::text('Pas de fumée : vous donnez seulement une coordonnée approximative.', 'No smoke: you only give an approximate grid.'),
                ],
                0,
                'L’observateur annonce la couleur qu’il voit et l’ami confirme. Annoncer la couleur d’abord permet à l’ennemi, qui écoute ou observe, de lancer la même fumée. Une coordonnée seule, approximative, n’identifie pas le groupe pour le pilote.',
                'The observer calls the colour they see and the friendly confirms. Announcing the colour first lets the enemy, who may be listening or watching, throw the same smoke. An approximate grid alone does not identify the group for the pilot.'
            ),
            K::text(
                "CAS GUIDÉ — FUMÉE POUR TUSKER 1\nVous êtes chef du groupe Kodiak 2. L’hélicoptère « Tusker 1 » vient vous récupérer en bordure de forêt. Le pilote demande que vous marquiez votre position. Des tirs ennemis ont été signalés à 600 m. Vous avez un fumigène violet en main.\n\nTravaillez dans cet ordre :\n1. Relisez la règle des fumigènes (section 02).\n2. Demandez-vous qui doit annoncer la couleur et pourquoi.\n3. Vérifiez que l’échange se termine par une confirmation.\n4. Choisissez l’échange correct.",
                "GUIDED CASE — SMOKE FOR TUSKER 1\nYou are the leader of group Kodiak 2. Helicopter “Tusker 1” is coming to pick you up at the edge of a forest. The pilot asks you to mark your position. Enemy fire has been reported 600 m away. You are holding a purple smoke grenade.\n\nWork in this order:\n1. Reread the smoke rule (section 02).\n2. Ask yourself who must announce the colour and why.\n3. Check that the exchange ends with a confirmation.\n4. Choose the correct exchange."
            ),
            K::text(
                'C’est celui qui regarde, pas celui qui lance, qui dit la couleur.',
                'It is the one who looks, not the one who throws, who calls the colour.'
            )
        );
        $simulation = K::stage(
            K::question(
                'Quel marquage décidez-vous ?',
                'Which marking do you decide on?',
                [
                    K::text('Tous les joueurs allument leur strobe IR en continu pendant toute la nuit.', 'All players switch their IR strobe on continuously all night.'),
                    K::text('Des chemlights visibles sur chaque fenêtre de la ferme, pour que Viper 1 voie tout.', 'Visible chemlights on every window of the farm, so that Viper 1 sees everything.'),
                    K::text('Le marquage prévu par l’ordre : un strobe IR sur le toit du bâtiment nord, allumé à la demande de Viper 1, avec la position annoncée par rapport au repère ATAK de la ferme ; Viper 1 décrit ce qu’il voit et vous confirmez.', 'The marking set by the order: one IR strobe on the roof of the northern building, switched on at Viper 1’s request, with the position given relative to the farm’s ATAK marker; Viper 1 describes what it sees and you confirm.'),
                ],
                2,
                'Le marquage doit être convenu, limité et vérifié. Des strobes permanents ou des chemlights partout signalent la position à un ennemi équipé de JVN et créent une confusion de lumières pour le pilote.',
                'The marking must be agreed, limited and checked. Permanent strobes or chemlights everywhere reveal the position to an enemy with NVGs and create a confusing pattern of lights for the pilot.'
            ),
            K::text(
                "SIMULATION AUTONOME — VIPER 1 DE NUIT\nVous êtes chef de la section Kodiak, retranchée de nuit dans une ferme de trois bâtiments. L’hélicoptère d’attaque « Viper 1 » arrive en appui. Le renseignement indique que l’ennemi dispose de quelques JVN. L’ordre prévoyait un strobe IR sur le toit du bâtiment nord comme marquage de la section vers l’aviation.\n\nVotre objectif : permettre à Viper 1 de vous identifier sans vous exposer inutilement. Aucune aide n’est donnée pendant cette étape ; vous pourrez revoir le cours après une tentative.",
                "INDEPENDENT SIMULATION — VIPER 1 AT NIGHT\nYou are the leader of section Kodiak, dug in for the night in a three-building farm. Attack helicopter “Viper 1” is arriving in support. Intelligence indicates that the enemy has a few NVGs. The order planned one IR strobe on the roof of the northern building as the section’s marking for aviation.\n\nYour objective: let Viper 1 identify you without exposing yourselves needlessly. No hint is given during this stage; you may review the course after an attempt."
            )
        );
        $questions = [
            K::question(
                'Qui annonce la couleur d’un fumigène de marquage de position ?',
                'Who announces the colour of a position-marking smoke?',
                [
                    K::text('Celui qui lance le fumigène, avant de le lancer.', 'Whoever throws the smoke, before throwing it.'),
                    K::text('L’observateur (pilote ou groupe voisin), puis l’ami confirme.', 'The observer (pilot or neighbouring group), then the friendly confirms.'),
                    K::text('Personne : la couleur n’a pas d’importance.', 'Nobody: the colour does not matter.'),
                ],
                1,
                'Faire annoncer la couleur par l’observateur empêche l’ennemi d’imiter le marquage avec la même fumée.',
                'Having the observer call the colour prevents the enemy from imitating the mark with the same smoke.'
            ),
            K::question(
                'Qui peut voir un strobe IR allumé ?',
                'Who can see a switched-on IR strobe?',
                [
                    K::text('Seulement les joueurs de votre camp.', 'Only players on your side.'),
                    K::text('Seulement les aéronefs.', 'Only aircraft.'),
                    K::text('Tout porteur de JVN, y compris l’ennemi.', 'Anyone wearing NVGs, including the enemy.'),
                ],
                2,
                'L’infrarouge n’est pas réservé aux amis. On l’allume quand l’ordre ou le chef le décide.',
                'Infrared is not reserved for friendlies. It is switched on when the order or the leader decides.'
            ),
            K::question(
                'Que montre le Blue Force Tracking d’ACE lorsqu’il est activé ?',
                'What does ACE Blue Force Tracking show when enabled?',
                [
                    K::text('La position des chefs de groupe de votre camp, pas celle de chaque joueur.', 'The position of your side’s group leaders, not every player.'),
                    K::text('La position exacte de chaque joueur ami et ennemi.', 'The exact position of every friendly and enemy player.'),
                    K::text('Uniquement les véhicules.', 'Vehicles only.'),
                ],
                0,
                'Le BFT d’ACE marque les chefs de groupe. Un groupe étiré peut avoir des joueurs loin de ce repère : il aide sans remplacer la PID.',
                'ACE BFT marks group leaders. A stretched-out group may have players far from that marker: it helps without replacing PID.'
            ),
            K::question(
                'Lors d’une jonction, qui prononce le mot d’appel ?',
                'During a link-up, who speaks the challenge?',
                [
                    K::text('L’élément qui arrive.', 'The arriving element.'),
                    K::text('Celui qui garde la position ; l’arrivant donne le mot de réponse.', 'Whoever holds the position; the arriving party gives the password.'),
                    K::text('Le chef de section, à la radio, en clair.', 'The section leader, on the radio, in clear.'),
                ],
                1,
                'La position interpelle, l’arrivant répond. Les mots sont fixés par l’ordre et ne se donnent pas en clair sur un net qui peut être écouté.',
                'The position challenges, the arriving party replies. The words are set by the order and are not given in clear on a net that may be monitored.'
            ),
            K::question(
                'Que faire d’un repère ATAK « position amie » qui n’est plus à jour ?',
                'What do you do with an out-of-date “friendly position” ATAK marker?',
                [
                    K::text('Le supprimer ou le mettre à jour, car un repère périmé trompe les autres groupes.', 'Delete or update it, because an out-of-date marker misleads other groups.'),
                    K::text('Le laisser : il montre où le groupe est passé.', 'Leave it: it shows where the group has been.'),
                    K::text('Le copier pour en avoir deux.', 'Copy it to have two.'),
                ],
                0,
                'Un repère ami ancien conduit à une erreur de position, première cause de tir fratricide.',
                'An old friendly marker leads to a position error, the first cause of fratricide.'
            ),
            K::question(
                'À quoi sert un panneau de signalisation fluorescent de type VS-17 ?',
                'What is a VS-17 type fluorescent marker panel used for?',
                [
                    K::text('À camoufler un véhicule.', 'To camouflage a vehicle.'),
                    K::text('À marquer une cible pour un tir de mortier.', 'To mark a target for mortar fire.'),
                    K::text('À marquer au sol une position, un flanc ou une zone de poser, visible des aéronefs.', 'To mark a position, a flank or a landing zone on the ground, visible from aircraft.'),
                ],
                2,
                'Les manuels publics de signaux visuels décrivent ces panneaux pour marquer des positions et identifier des unités vers l’aviation.',
                'Public visual signals manuals describe these panels for marking positions and identifying units to aircraft.'
            ),
            K::question(
                'Pourquoi un marquage doit-il être prévu dans l’ordre ?',
                'Why must a marking be set in the order?',
                [
                    K::text('Pour remplir le paragraphe Service support.', 'To fill in the Service support paragraph.'),
                    K::text('Parce qu’un marquage improvisé peut être pris pour un signal ennemi ou une cible.', 'Because an improvised mark may be taken for an enemy signal or a target.'),
                    K::text('Parce que l’ordre fixe la couleur des tenues.', 'Because the order sets the colour of uniforms.'),
                ],
                1,
                'Tous ceux qui peuvent tirer doivent savoir à l’avance ce que signifie chaque marquage ; sinon, il crée le doute au lieu de le lever.',
                'Everyone who can fire must know in advance what each mark means; otherwise it creates doubt instead of removing it.'
            ),
        ];

        return K::chapter(
            K::text('Chapitre 2 — Marquage des amis et des positions (jour, nuit, IR, ATAK, BFT)', 'Chapter 2 — Marking friendlies and positions (day, night, IR, ATAK, BFT)'),
            K::text($theoryFr, $theoryEn),
            K::text($demoFr, $demoEn),
            $guided,
            $simulation,
            $questions,
            K::text(
                "Rédigez un retour personnel sur le cas Viper 1 et votre évaluation :\n1. Le marquage choisi et ce qu’il révèle à l’ennemi.\n2. L’échange radio qui permet à Viper 1 de vous identifier.\n3. Les limites d’ATAK et du BFT dans cette situation.\n4. Le marquage que vous ajouteriez à l’ordre de votre prochaine mission.\n5. Une erreur de marquage déjà vue en mission et comment l’éviter.",
                "Write a personal reflection on the Viper 1 case and your assessment:\n1. The marking chosen and what it reveals to the enemy.\n2. The radio exchange that lets Viper 1 identify you.\n3. The limits of ATAK and BFT in this situation.\n4. The marking you would add to the order of your next mission.\n5. A marking error you have seen in a mission and how to avoid it."
            ),
            [
                K::text('La règle « l’observateur annonce la couleur » est expliquée.', 'The “observer calls the colour” rule is explained.'),
                K::text('Le risque de l’infrarouge face à un ennemi équipé de JVN est pris en compte.', 'The risk of infrared against an enemy with NVGs is taken into account.'),
                K::text('Les limites d’ATAK et du BFT sont identifiées.', 'The limits of ATAK and BFT are identified.'),
                K::text('Le marquage proposé est convenu, limité et vérifiable.', 'The proposed marking is agreed, limited and checkable.'),
            ],
            [
                K::text('L’évaluation atteint au moins 80 % dans le délai prévu.', 'The assessment reaches at least 80% within the time limit.'),
                K::text('Le stagiaire réalise un marquage de position par fumigène avec annonce de la couleur par l’observateur.', 'The learner performs a smoke position mark with the colour called by the observer.'),
                K::text('Il conduit une jonction avec signal lointain et mots convenus.', 'They conduct a link-up with a far signal and agreed words.'),
                K::text('Il tient à jour ou supprime ses repères ATAK de position amie.', 'They keep their friendly-position ATAK markers up to date or delete them.'),
            ],
            [
                K::sheet(
                    K::text('Fiche mémo — Marquages jour et nuit', 'Memo card — Day and night marking'),
                    K::text(
                        "FORMATION DE JEU (ARMA 3) — sans valeur de qualification réelle.\n\nPRINCIPE\nUn marquage convenu dans l’ordre · annoncé à la radio · vérifié par celui qui observe.\n\nJOUR\nFumigène : « Fumée lancée, annoncez la couleur » → l’observateur annonce → l’ami confirme.\nPanneau fluorescent (type VS-17) si la mission en fournit : position, flanc, zone de poser.\n\nNUIT\nChemlights ACE (blanc, rouge, vert, jaune, orange, bleu ; « Hi » = plus brillant, courte durée) selon le code de l’ordre.\nStrobe IR / grenade IR : visibles aux JVN, ennemi compris. Fixation ACE : interaction « Attacher » (tenue ou véhicule).\n\nNUMÉRIQUE\nATAK : repères datés, supprimés quand dépassés.\nBFT ACE (si activé) : position des chefs de groupe seulement.\n\nJONCTION\nSignal lointain (visuel) · mot d’appel donné par la position · mot de réponse par l’arrivant · mise à jour ATAK.",
                        "IN-GAME TRAINING (ARMA 3) — no real-world qualification value.\n\nPRINCIPLE\nA mark agreed in the order · announced on the radio · checked by the observer.\n\nDAY\nSmoke: “Smoke out, call the colour” → the observer calls it → the friendly confirms.\nFluorescent panel (VS-17 type) if the mission provides one: position, flank, landing zone.\n\nNIGHT\nACE chemlights (white, red, green, yellow, orange, blue; “Hi” = brighter, short-lived) according to the order’s code.\nIR strobe / IR grenade: visible through NVGs, enemy included. ACE fitting: “Attach” interaction (uniform or vehicle).\n\nDIGITAL\nATAK: time-stamped markers, deleted when out of date.\nACE BFT (if enabled): group leaders’ positions only.\n\nLINK-UP\nFar signal (visual) · challenge given by the position · password by the arriving party · ATAK update."
                    )
                ),
            ]
        );
    }

    // ------------------------------------------------------------------ Chapitre 3 — Coordination et CHECK FIRE

    /** @return array<string, mixed> */
    private static function chapterThree(): array
    {
        $theoryFr = <<<'FR'
01 — Les secteurs de tir
Chaque arme et chaque équipe reçoit un secteur : une limite gauche et une limite droite, données sur des repères visibles (« de la ferme rouge au pylône »). Les secteurs voisins se recouvrent légèrement pour ne laisser aucun trou, mais aucun ne doit englober une position amie. Quand un groupe bouge, les secteurs des autres changent : le chef les redonne.

02 — Lignes et zones de coordination simples
Les manuels publics alliés utilisent des mesures que nous reprenons en jeu, tracées sur la carte et dans ATAK et nommées dans l’ordre :
- Ligne de phase (Phase Line, PL) : repère linéaire nommé, pour coordonner la progression et les comptes rendus (« Kodiak 2 sur PL ROUGE »).
- Limite d’avance (Limit of Advance, LOA) : ligne qu’aucun élément ne dépasse sans ordre.
- Limites entre unités (boundaries) : chacun tire et manœuvre dans sa zone ; on ne tire pas au-delà sans coordination.
- Ligne de tir restrictive (Restrictive Fire Line, RFL) : entre deux forces qui convergent, ligne au-delà de laquelle on ne tire pas sans coordination.
- Zone d’interdiction de tir (No-Fire Area, NFA) : zone où l’on ne tire pas, par exemple autour d’une patrouille de reconnaissance amie.

03 — Discipline de tir
On tire dans son secteur, sur ordre ou selon les règles d’ouverture du feu de la mission. On annonce ses mouvements (« je bouge », « je passe devant ») et on ne traverse jamais la ligne de tir d’une arme sans être vu de son servant. On vérifie la zone arrière d’une arme antichar avant le tir.

04 — « CHECK FIRE » et « CEASE FIRE » dans nos missions
Dans nos missions, inspirées des usages alliés en appui feu :
- « CHECK FIRE » (répété deux fois) : arrêt immédiat de tous les tirs de l’élément désigné, ou de tout le net si aucun élément n’est désigné. N’importe quel joueur l’annonce dès qu’il voit un danger pour des amis. Les tirs ne reprennent que sur ordre du chef, une fois le doute levé.
- « CEASE FIRE » : cessez le feu ; on ne tire plus et on n’ouvre pas le feu jusqu’à nouvel ordre, par exemple à la fin d’un engagement.
On ne discute pas un « CHECK FIRE » : on arrête d’abord, on vérifie ensuite.

05 — Sous un tir ami : la réaction
1. Se mettre à couvert ; ne pas riposter.
2. Annoncer sur le net : « CHECK FIRE, CHECK FIRE, tirs amis sur Kodiak 2, lisière nord du village. »
3. S’identifier avec le signal convenu (fumigène, strobe IR, panneau) et à la voix si l’on est proche.
4. Rendre compte au chef ou à l’échelon supérieur.
5. Soigner les blessés (médical ACE) et rendre compte des pertes.
6. Reprendre la manœuvre sur ordre.

06 — Si l’on pense avoir tiré sur des amis
Cesser immédiatement, annoncer « CHECK FIRE » et dire ce que l’on a engagé. Ne rien cacher : l’incident est rapporté au débriefing pour comprendre la cause (position, identification, coordination), pas pour chercher un coupable.

07 — Ce qu’il faut retenir
Secteurs sur repères, lignes nommées dans l’ordre et dans ATAK. « CHECK FIRE » : tout le monde peut l’annoncer, tout le monde l’applique. Sous tir ami : couvert, pas de riposte, annonce, signal, compte rendu.
FR;
        $theoryEn = <<<'EN'
01 — Sectors of fire
Each weapon and each team is given a sector: a left limit and a right limit, set on visible references (“from the red farm to the pylon”). Neighbouring sectors overlap slightly to leave no gap, but none may include a friendly position. When a group moves, the others’ sectors change: the leader reissues them.

02 — Simple coordination lines and areas
Public allied manuals use measures that we adopt in the game, drawn on the map and in ATAK and named in the order:
- Phase Line (PL): a named linear reference, to coordinate movement and reports (“Kodiak 2 on PL RED”).
- Limit of Advance (LOA): a line no element crosses without orders.
- Boundaries: each unit fires and manoeuvres in its own area; nobody fires beyond it without coordination.
- Restrictive Fire Line (RFL): between two converging forces, a line beyond which nobody fires without coordination.
- No-Fire Area (NFA): an area where nobody fires, for example around a friendly reconnaissance patrol.

03 — Fire discipline
You fire within your sector, on orders or according to the mission’s rules for opening fire. You announce your movements (“moving”, “passing in front”) and never cross a weapon’s line of fire without being seen by its gunner. You check the danger area behind an anti-tank weapon before firing.

04 — “CHECK FIRE” and “CEASE FIRE” in our missions
In our missions, inspired by allied fire-support practice:
- “CHECK FIRE” (said twice): immediate halt of all fire by the named element, or by the whole net if no element is named. Any player calls it as soon as they see a danger to friendlies. Fire only resumes on the leader’s order, once the doubt is removed.
- “CEASE FIRE”: stop firing; nobody fires or opens fire until further orders, for example at the end of an engagement.
A “CHECK FIRE” is not debated: stop first, check afterwards.

05 — Under friendly fire: the reaction
1. Take cover; do not return fire.
2. Announce on the net: “CHECK FIRE, CHECK FIRE, friendly fire on Kodiak 2, northern edge of the village.”
3. Identify yourself with the agreed signal (smoke, IR strobe, panel) and by voice if close.
4. Report to the leader or higher.
5. Treat the wounded (ACE medical) and report casualties.
6. Resume the manoeuvre on orders.

06 — If you think you have fired on friendlies
Stop immediately, call “CHECK FIRE” and say what you engaged. Hide nothing: the incident is reported at the debrief to understand the cause (position, identification, coordination), not to find someone to blame.

07 — Key points
Sectors on references, lines named in the order and in ATAK. “CHECK FIRE”: anyone can call it, everyone applies it. Under friendly fire: cover, no return fire, announcement, signal, report.
EN;
        $demoFr = <<<'FR'
DÉMONSTRATION COMMENTÉE — TIRS AMIS SUR KODIAK 2
Situation de jeu : Kodiak 2 a dépassé la ligne de phase BLEUE plus tôt que prévu pour atteindre une haie. Le véhicule blindé « Ours 1 », en appui depuis l’arrière, ouvre le feu sur la haie.

Chef Kodiak 2 (radio, net de section) : « CHECK FIRE, CHECK FIRE ! Ours 1, tirs amis sur Kodiak 2, haie à 100 mètres au nord de PL BLEUE ! »
Commentaire : arrêt demandé deux fois, élément désigné, victime et position annoncées avec un repère commun.

Ours 1 : « Ours 1, check fire. Tirs arrêtés. »
Commentaire : l’élément qui tire collationne et confirme l’arrêt.

Chef Kodiak 2 : « Kodiak 2, fumée lancée sur notre position. Annoncez la couleur. »
Ours 1 : « Je vois vert, à la haie. »
Chef Kodiak 2 : « Vert confirmé, c’est Kodiak 2. »
Commentaire : l’identification visuelle confirme la position, selon la règle du chapitre 2.

Chef Kodiak 2 : « Kodiak, Kodiak 2. Un blessé léger, pris en charge. Nous sommes 100 mètres au-delà de PL BLEUE. »
Commentaire : compte rendu à l’échelon supérieur : pertes, position réelle. La cause, une ligne de phase dépassée sans compte rendu, apparaît déjà.

Chef de section : « Kodiak 2, tenez la haie. Ours 1, secteur limité au sud de PL BLEUE. Reprise des tirs sur mon ordre. »
Commentaire : le chef de section rétablit la coordination et redonne les secteurs avant toute reprise.

À RETENIR
Arrêter, s’identifier, rendre compte, recoordonner. Au débriefing : Kodiak 2 aurait dû annoncer le franchissement de PL BLEUE.
FR;
        $demoEn = <<<'EN'
ANNOTATED DEMONSTRATION — FRIENDLY FIRE ON KODIAK 2
Game situation: Kodiak 2 crossed Phase Line BLUE earlier than planned to reach a hedgerow. The armoured vehicle “Bear 1”, supporting from the rear, opens fire on the hedgerow.

Kodiak 2 lead (radio, section net): “CHECK FIRE, CHECK FIRE! Bear 1, friendly fire on Kodiak 2, hedgerow 100 metres north of PL BLUE!”
Comment: halt called twice, element named, victim and position given with a common reference.

Bear 1: “Bear 1, check fire. Firing stopped.”
Comment: the firing element reads back and confirms the halt.

Kodiak 2 lead: “Kodiak 2, smoke out on our position. Call the colour.”
Bear 1: “I see green, at the hedgerow.”
Kodiak 2 lead: “Green confirmed, that’s Kodiak 2.”
Comment: visual identification confirms the position, following the chapter 2 rule.

Kodiak 2 lead: “Kodiak, Kodiak 2. One lightly wounded, being treated. We are 100 metres beyond PL BLUE.”
Comment: report to higher: casualties, actual position. The cause, a phase line crossed without a report, is already visible.

Section leader: “Kodiak 2, hold the hedgerow. Bear 1, sector limited to south of PL BLUE. Resume fire on my order.”
Comment: the section leader restores coordination and reissues sectors before any resumption.

KEY POINT
Stop, identify, report, re-coordinate. At the debrief: Kodiak 2 should have reported crossing PL BLUE.
EN;
        $guided = K::stage(
            K::question(
                'Que faites-vous immédiatement ?',
                'What do you do immediately?',
                [
                    K::text('Vous attendez que Kodiak 3 signale lui-même le problème.', 'You wait for Kodiak 3 to report the problem itself.'),
                    K::text('Vous tirez sur Ours 1 pour le faire cesser.', 'You fire on Bear 1 to make it stop.'),
                    K::text('« CHECK FIRE, CHECK FIRE ! Ours 1, tirs sur Kodiak 3 au sud du carrefour ! » sur le net, puis compte rendu au chef.', '“CHECK FIRE, CHECK FIRE! Bear 1, fire on Kodiak 3 south of the crossroads!” on the net, then report to the leader.'),
                ],
                2,
                'N’importe quel joueur qui voit le danger annonce le « CHECK FIRE », en désignant l’élément qui tire et la position touchée. Attendre laisse le tir continuer ; riposter sur des amis double l’incident.',
                'Any player who sees the danger calls the “CHECK FIRE”, naming the firing element and the position being hit. Waiting lets the fire continue; firing back at friendlies doubles the incident.'
            ),
            K::text(
                "CAS GUIDÉ — LES TRAÇANTES SUR KODIAK 3\nVous êtes tireur dans Kodiak 2, en appui sur une butte. Vous voyez des traçantes venant de la position du blindé ami « Ours 1 » frapper un bâtiment au sud du carrefour, où Kodiak 3 vient d’entrer selon ATAK. Kodiak 3 ne dit rien sur le net.\n\nTravaillez dans cet ordre :\n1. Demandez-vous qui a le droit d’annoncer un arrêt des tirs.\n2. Identifiez l’élément qui tire et l’ami touché.\n3. Formulez l’annonce avec un repère commun.\n4. Choisissez la réponse correcte.",
                "GUIDED CASE — TRACERS ON KODIAK 3\nYou are a rifleman in Kodiak 2, in support on a mound. You see tracers from the position of the friendly armoured vehicle “Bear 1” hitting a building south of the crossroads, which Kodiak 3 has just entered according to ATAK. Kodiak 3 says nothing on the net.\n\nWork in this order:\n1. Ask yourself who is allowed to call a halt to firing.\n2. Identify the firing element and the friendly being hit.\n3. Phrase the call with a common reference.\n4. Choose the correct answer."
            ),
            K::text(
                'Relisez la section 04 : « n’importe quel joueur l’annonce dès qu’il voit un danger pour des amis ».',
                'Reread section 04: “any player calls it as soon as they see a danger to friendlies”.'
            )
        );
        $simulation = K::stage(
            K::question(
                'Quelle coordination donnez-vous dans votre ordre ?',
                'Which coordination do you give in your order?',
                [
                    K::text('Une ligne de tir restrictive (RFL) tracée sur ATAK entre les deux axes, une ligne de phase pour les comptes rendus et la règle : Kodiak 1 lève ses tirs sur l’objectif quand Kodiak 2 annonce la PL d’assaut.', 'A Restrictive Fire Line (RFL) drawn on ATAK between the two axes, a phase line for reports and the rule: Kodiak 1 lifts its fire on the objective when Kodiak 2 reports the assault PL.'),
                    K::text('Chaque groupe tire librement sur l’objectif jusqu’à ce qu’il soit pris.', 'Each group fires freely on the objective until it is taken.'),
                    K::text('Aucune mesure : le BFT suffit pour savoir où est chacun.', 'No measures: BFT is enough to know where everyone is.'),
                ],
                0,
                'Deux forces qui convergent se retrouvent face à face sur l’objectif : la RFL et la ligne de phase fixent qui tire où et quand le tir se lève. Le BFT ne montre que les chefs de groupe et ne coordonne rien.',
                'Two converging forces end up facing each other on the objective: the RFL and the phase line set who fires where and when fire lifts. BFT only shows group leaders and coordinates nothing.'
            ),
            K::text(
                "SIMULATION AUTONOME — DEUX AXES SUR LE HAMEAU\nVous êtes chef de la section Kodiak et préparez l’attaque d’un hameau. Kodiak 1 appuie depuis une colline à l’ouest. Kodiak 2 donne l’assaut par le sud et finira sur l’objectif, face à Kodiak 1. Le mortier n’est pas engagé. Vous devez compléter les instructions de coordination de votre ordre.\n\nVotre objectif : empêcher Kodiak 1 et Kodiak 2 de se tirer dessus pendant l’assaut. Aucune aide n’est donnée pendant cette étape ; vous pourrez revoir le cours après une tentative.",
                "INDEPENDENT SIMULATION — TWO AXES ON THE HAMLET\nYou are the leader of section Kodiak and are preparing the attack on a hamlet. Kodiak 1 supports from a hill to the west. Kodiak 2 assaults from the south and will finish on the objective, facing Kodiak 1. The mortar is not involved. You must complete the coordinating instructions of your order.\n\nYour objective: prevent Kodiak 1 and Kodiak 2 from firing on each other during the assault. No hint is given during this stage; you may review the course after an attempt."
            )
        );
        $questions = [
            K::question(
                'Comment donne-t-on un secteur de tir ?',
                'How is a sector of fire given?',
                [
                    K::text('Par un azimut unique.', 'With a single bearing.'),
                    K::text('Par une limite gauche et une limite droite sur des repères visibles, sans englober de position amie.', 'With a left and a right limit on visible references, without including any friendly position.'),
                    K::text('Par la distance maximale de l’arme.', 'With the weapon’s maximum range.'),
                ],
                1,
                'Deux limites sur des repères que le tireur voit ; les secteurs voisins se recouvrent légèrement mais excluent les amis.',
                'Two limits on references the shooter can see; neighbouring sectors overlap slightly but exclude friendlies.'
            ),
            K::question(
                'À quoi sert une ligne de tir restrictive (RFL) ?',
                'What is a Restrictive Fire Line (RFL) for?',
                [
                    K::text('À marquer la zone de poser des hélicoptères.', 'To mark the helicopter landing zone.'),
                    K::text('À fixer la limite d’avance de la section.', 'To set the section’s limit of advance.'),
                    K::text('À empêcher deux forces qui convergent de tirer au-delà d’une ligne sans coordination.', 'To prevent two converging forces from firing beyond a line without coordination.'),
                ],
                2,
                'La RFL protège des forces qui se rapprochent l’une de l’autre ; la limite d’avance (LOA) est une autre mesure, qui borne la progression.',
                'The RFL protects forces closing on each other; the Limit of Advance (LOA) is a different measure, which bounds movement.'
            ),
            K::question(
                'Qui peut annoncer « CHECK FIRE » dans nos missions ?',
                'Who can call “CHECK FIRE” in our missions?',
                [
                    K::text('N’importe quel joueur qui voit un danger pour des amis.', 'Any player who sees a danger to friendlies.'),
                    K::text('Seulement le chef de section.', 'Only the section leader.'),
                    K::text('Seulement le servant de l’arme qui tire.', 'Only the gunner of the firing weapon.'),
                ],
                0,
                'Le danger est souvent vu par quelqu’un d’autre que le chef ou le tireur. Tout le monde l’annonce, tout le monde l’applique ; seul le chef fait reprendre les tirs.',
                'The danger is often seen by someone other than the leader or the shooter. Everyone calls it, everyone applies it; only the leader resumes fire.'
            ),
            K::question(
                'Votre groupe est sous un tir ami. Quelle est la première action ?',
                'Your group is under friendly fire. What is the first action?',
                [
                    K::text('Riposter pour faire cesser le tir.', 'Return fire to make it stop.'),
                    K::text('Se mettre à couvert, sans riposter, puis annoncer « CHECK FIRE » sur le net.', 'Take cover, without returning fire, then call “CHECK FIRE” on the net.'),
                    K::text('Changer de fréquence pour ne pas encombrer le net.', 'Change frequency so as not to clutter the net.'),
                ],
                1,
                'Couvert, pas de riposte, annonce, signal convenu, compte rendu : riposter transforme un incident en échange de tirs entre amis.',
                'Cover, no return fire, call, agreed signal, report: returning fire turns an incident into an exchange of fire between friendlies.'
            ),
            K::question(
                'Quelle est la différence entre « CHECK FIRE » et « CEASE FIRE » dans nos missions ?',
                'What is the difference between “CHECK FIRE” and “CEASE FIRE” in our missions?',
                [
                    K::text('Aucune : les deux s’emploient indifféremment.', 'None: both are used interchangeably.'),
                    K::text('« CHECK FIRE » est réservé à l’artillerie, « CEASE FIRE » à l’infanterie.', '“CHECK FIRE” is reserved for artillery, “CEASE FIRE” for infantry.'),
                    K::text('« CHECK FIRE » arrête immédiatement les tirs face à un danger ou un doute ; « CEASE FIRE » met fin aux tirs jusqu’à nouvel ordre.', '“CHECK FIRE” halts fire immediately in the face of danger or doubt; “CEASE FIRE” ends firing until further orders.'),
                ],
                2,
                'Le premier est une urgence de sécurité, levée par le chef une fois le doute dissipé ; le second est un ordre de fin de tir.',
                'The first is a safety emergency, lifted by the leader once the doubt is cleared; the second is an order to stop firing.'
            ),
            K::question(
                'À quoi sert une ligne de phase (PL) ?',
                'What is a Phase Line (PL) for?',
                [
                    K::text('À coordonner la progression et les comptes rendus grâce à un repère linéaire nommé.', 'To coordinate movement and reports with a named linear reference.'),
                    K::text('À interdire tout tir dans une zone.', 'To forbid all fire in an area.'),
                    K::text('À indiquer la fréquence radio à utiliser.', 'To indicate the radio frequency to use.'),
                ],
                0,
                'Annoncer le franchissement d’une PL dit à tous où est le groupe ; ne pas l’annoncer est une cause fréquente d’erreur de position.',
                'Reporting a PL crossing tells everyone where the group is; failing to report it is a common cause of position errors.'
            ),
            K::question(
                'Après un tir fratricide, que fait-on au débriefing ?',
                'After a fratricide, what is done at the debrief?',
                [
                    K::text('On n’en parle pas pour ne pas gêner le joueur concerné.', 'It is not mentioned so as not to embarrass the player involved.'),
                    K::text('On recherche la cause (position, identification, coordination) pour l’éviter la prochaine fois.', 'The cause (position, identification, coordination) is identified to avoid it next time.'),
                    K::text('On exclut le tireur de la mission suivante.', 'The shooter is excluded from the next mission.'),
                ],
                1,
                'Le but est d’apprendre : comprendre la cause permet de corriger l’ordre, le marquage ou la discipline de tir.',
                'The aim is to learn: understanding the cause allows the order, the marking or fire discipline to be corrected.'
            ),
        ];

        return K::chapter(
            K::text('Chapitre 3 — Coordination, secteurs, « CHECK FIRE » et réaction à un tir ami', 'Chapter 3 — Coordination, sectors, “CHECK FIRE” and reaction to friendly fire'),
            K::text($theoryFr, $theoryEn),
            K::text($demoFr, $demoEn),
            $guided,
            $simulation,
            $questions,
            K::text(
                "Rédigez un retour personnel sur le cas du hameau et votre évaluation :\n1. Les mesures de coordination retenues et ce que chacune empêche.\n2. Le moment exact où Kodiak 1 lève ses tirs et qui le décide.\n3. La phrase « CHECK FIRE » que vous donneriez si Kodiak 2 était touché.\n4. Comment vous traceriez ces mesures sur ATAK et la carte.\n5. Une amélioration à tester dans les instructions de coordination de votre prochain ordre.",
                "Write a personal reflection on the hamlet case and your assessment:\n1. The coordination measures chosen and what each one prevents.\n2. The exact moment Kodiak 1 lifts its fire and who decides it.\n3. The “CHECK FIRE” call you would give if Kodiak 2 were hit.\n4. How you would draw these measures on ATAK and the map.\n5. One improvement to test in the coordinating instructions of your next order."
            ),
            [
                K::text('Les mesures choisies (secteurs, PL, RFL, LOA ou NFA) sont justifiées.', 'The measures chosen (sectors, PL, RFL, LOA or NFA) are justified.'),
                K::text('Le moment de levée des tirs est lié à un compte rendu précis.', 'The moment fire lifts is linked to a specific report.'),
                K::text('La phrase « CHECK FIRE » désigne l’élément qui tire et la position touchée.', 'The “CHECK FIRE” call names the firing element and the position being hit.'),
                K::text('L’amélioration proposée est concrète et vérifiable.', 'The proposed improvement is concrete and checkable.'),
            ],
            [
                K::text('L’évaluation atteint au moins 80 % dans le délai prévu.', 'The assessment reaches at least 80% within the time limit.'),
                K::text('Le stagiaire donne un secteur de tir sur repères, sans position amie incluse.', 'The learner gives a sector of fire on references, with no friendly position included.'),
                K::text('Il annonce un « CHECK FIRE » complet à la radio et applique la séquence de réaction à un tir ami.', 'They make a complete “CHECK FIRE” call on the radio and apply the reaction sequence to friendly fire.'),
                K::text('Il explique la différence entre « CHECK FIRE » et « CEASE FIRE » telle que définie dans nos missions.', 'They explain the difference between “CHECK FIRE” and “CEASE FIRE” as defined in our missions.'),
            ],
            [
                K::sheet(
                    K::text('Fiche mémo — Coordination et CHECK FIRE', 'Memo card — Coordination and CHECK FIRE'),
                    K::text(
                        "FORMATION DE JEU (ARMA 3) — sans valeur de qualification réelle.\n\nSECTEURS\nLimite gauche · limite droite · sur repères visibles · aucun ami dedans · redonnés à chaque mouvement.\n\nMESURES (dans l’ordre et sur ATAK)\nPL — ligne de phase : coordonner progression et comptes rendus.\nLOA — limite d’avance : pas au-delà sans ordre.\nBoundary — limite entre unités : on ne tire pas chez le voisin sans coordination.\nRFL — ligne de tir restrictive : entre forces qui convergent.\nNFA — zone d’interdiction de tir.\n\nCHECK FIRE (nos missions)\n« CHECK FIRE, CHECK FIRE, [élément qui tire], tirs amis sur [victime], [position] ! »\nTout le monde l’annonce, tout le monde l’applique, le chef fait reprendre.\nCEASE FIRE : fin des tirs jusqu’à nouvel ordre.\n\nSOUS TIR AMI\n1. Couvert, pas de riposte · 2. CHECK FIRE · 3. Signal convenu · 4. Compte rendu · 5. Blessés (ACE) · 6. Reprise sur ordre.",
                        "IN-GAME TRAINING (ARMA 3) — no real-world qualification value.\n\nSECTORS\nLeft limit · right limit · on visible references · no friendlies inside · reissued after every move.\n\nMEASURES (in the order and on ATAK)\nPL — Phase Line: coordinate movement and reports.\nLOA — Limit of Advance: not beyond without orders.\nBoundary — no fire into the neighbour’s area without coordination.\nRFL — Restrictive Fire Line: between converging forces.\nNFA — No-Fire Area.\n\nCHECK FIRE (our missions)\n“CHECK FIRE, CHECK FIRE, [firing element], friendly fire on [victim], [position]!”\nEveryone calls it, everyone applies it, the leader resumes fire.\nCEASE FIRE: firing ends until further orders.\n\nUNDER FRIENDLY FIRE\n1. Cover, no return fire · 2. CHECK FIRE · 3. Agreed signal · 4. Report · 5. Wounded (ACE) · 6. Resume on orders."
                    )
                ),
                K::sheet(
                    K::text('Sources publiques', 'Public sources'),
                    K::text(
                        "FORMATION DE JEU (ARMA 3) — sans valeur de qualification réelle.\n\nCe cours est rédigé à partir de sources publiques, reformulées et adaptées au jeu. Aucune donnée classifiée n’est utilisée. Les conventions « CHECK FIRE » / « CEASE FIRE », les mots convenus et les codes de marquage cités sont des conventions de nos missions.\n\nArmy.mil — NATO soldiers learn to work as one during Bold Quest (2011)\nhttps://www.army.mil/article/65482/nato_soldiers_learn_to_work_as_one_during_bold_quest\nNCI Agency (OTAN) — NCI Agency participates in exercise Bold Quest\nhttps://www.ncia.nato.int/about-us/newsroom/nci-agency-participates-in-exercise-bold-quest.aspx\nWikipédia — Friendly fire\nhttps://en.wikipedia.org/wiki/Friendly_fire\nWikipédia — Combat Identification Panel\nhttps://en.wikipedia.org/wiki/Combat_Identification_Panel\nWikipédia — Blue force tracking\nhttps://en.wikipedia.org/wiki/Blue_force_tracking\nUS Army — TC 3-21.60 Visual Signals et ATP 3-21.8 Infantry Platoon and Squad (publications publiques)\nhttps://armypubs.army.mil/\nACE3 — Map (Blue Force Tracking), Attach, Chemlights\nhttps://ace3.acemod.org/wiki/feature/map\nhttps://ace3.acemod.org/wiki/feature/attach\nhttps://ace3.acemod.org/wiki/feature/chemlights",
                        "IN-GAME TRAINING (ARMA 3) — no real-world qualification value.\n\nThis course is written from public sources, rephrased and adapted to the game. No classified data is used. The “CHECK FIRE” / “CEASE FIRE” conventions, the agreed words and the marking codes quoted are conventions of our missions.\n\nArmy.mil — NATO soldiers learn to work as one during Bold Quest (2011)\nhttps://www.army.mil/article/65482/nato_soldiers_learn_to_work_as_one_during_bold_quest\nNCI Agency (NATO) — NCI Agency participates in exercise Bold Quest\nhttps://www.ncia.nato.int/about-us/newsroom/nci-agency-participates-in-exercise-bold-quest.aspx\nWikipedia — Friendly fire\nhttps://en.wikipedia.org/wiki/Friendly_fire\nWikipedia — Combat Identification Panel\nhttps://en.wikipedia.org/wiki/Combat_Identification_Panel\nWikipedia — Blue force tracking\nhttps://en.wikipedia.org/wiki/Blue_force_tracking\nUS Army — TC 3-21.60 Visual Signals and ATP 3-21.8 Infantry Platoon and Squad (public publications)\nhttps://armypubs.army.mil/\nACE3 — Map (Blue Force Tracking), Attach, Chemlights\nhttps://ace3.acemod.org/wiki/feature/map\nhttps://ace3.acemod.org/wiki/feature/attach\nhttps://ace3.acemod.org/wiki/feature/chemlights"
                    )
                ),
            ]
        );
    }
}
