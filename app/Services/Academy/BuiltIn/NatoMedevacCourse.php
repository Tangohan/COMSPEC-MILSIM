<?php

declare(strict_types=1);

namespace App\Services\Academy\BuiltIn;

use App\Services\Academy\BuiltIn\AcademyCourseKit as K;

/**
 * Demande d’évacuation sanitaire 9-line MEDEVAC, compte rendu MIST/ATMIST et TCCC niveau joueur, adaptés au
 * système médical ACE d’Arma 3. Inspiré de l’ATP 4-02.2 Medical Evacuation (US Army, diffusion publique, qui met
 * en œuvre les STANAG 2087 et 3204) et des lignes directrices publiques TCCC. Formation de jeu, sans valeur réelle.
 */
final class NatoMedevacCourse
{
    public const KEY = 'otan-medevac-9-line-casevac';
    public const VERSION = '1.0.0';

    /** @return array<string, mixed> */
    public static function course(): array
    {
        return K::course(self::KEY, self::VERSION,
            K::text('MEDEVAC 9-line, CASEVAC et prise en charge des blessés', '9-line MEDEVAC, CASEVAC and casualty handling'),
            K::text(
                'Formation de jeu (Arma 3, médical ACE) : les phases TCCC au niveau joueur et le triage, la demande d’évacuation sanitaire 9-line ligne par ligne, puis la préparation et le marquage de la HLZ, le compte rendu MIST/ATMIST et la passation au « Dust Off ». Inspirée de l’ATP 4-02.2 et des lignes directrices TCCC publiques ; aucun contenu ne constitue un conseil médical réel.',
                'In-game training (Arma 3, ACE medical): player-level TCCC phases and triage, the 9-line medical evacuation request line by line, then preparing and marking the HLZ, the MIST/ATMIST report and the handover to “Dust Off”. Inspired by ATP 4-02.2 and the public TCCC guidelines; nothing here is real-world medical advice.'
            ),
            120,
            [self::chapterOne(), self::chapterTwo(), self::chapterThree()],
            [
                'code' => 'ACAD-OTAN-MEDEVAC',
                'license_code' => 'MEDEVAC',
                'name' => K::text('Demande MEDEVAC 9-line et prise en charge des blessés (jeu)', '9-line MEDEVAC request and casualty handling (in-game)'),
                'short_name' => 'MEDEVAC 9-LINE',
                'description' => K::text(
                    'Le titulaire sait, en mission Arma 3, appliquer l’ordre TCCC au niveau joueur avec le médical ACE, trier les blessés par précédence, transmettre une demande 9-line complète sur ACRE, préparer et marquer une HLZ et passer un compte rendu MIST/ATMIST à l’équipage « Dust Off ». Qualification de jeu inspirée de l’ATP 4-02.2 et des lignes directrices TCCC publiques, sans valeur réelle.',
                    'In Arma 3 missions, the holder can apply the player-level TCCC order with ACE medical, sort casualties by precedence, send a complete 9-line request over ACRE, prepare and mark an HLZ and give a MIST/ATMIST report to the “Dust Off” crew. In-game qualification inspired by ATP 4-02.2 and the public TCCC guidelines, with no real-world value.'
                ),
                'reference' => K::text(
                    'Inspirée de l’ATP 4-02.2 Medical Evacuation (US Army, 2019, publication approuvée pour diffusion publique, qui met en œuvre les STANAG 2087 et 3204) et des lignes directrices TCCC publiques (Committee on Tactical Combat Casualty Care).',
                    'Inspired by ATP 4-02.2 Medical Evacuation (US Army, 2019, approved for public release, implementing STANAG 2087 and 3204) and the public TCCC guidelines (Committee on Tactical Combat Casualty Care).'
                ),
                'validity_months' => null,
            ]);
    }

    // ------------------------------------------------------------------ Chapitre 1 — TCCC niveau joueur et triage

    /** @return array<string, mixed> */
    private static function chapterOne(): array
    {
        $theoryFr = <<<'FR'
OBJECTIF DE LA FORMATION
Ce cours forme les joueurs à gérer des blessés dans nos missions Arma 3, du premier tir jusqu’à l’embarquement dans l’hélicoptère d’évacuation sanitaire. Les termes OTAN restent en anglais à la radio, comme dans le cours JTAC. Tous les soins décrits sont ceux du système médical ACE en jeu : rien ici n’est un conseil médical réel ni une formation au secourisme. Dans la vie réelle, appelez les secours (15 ou 112). Il s’agit d’une formation de jeu : la qualification Athena Academy délivrée est interne à la communauté et reste sans valeur de qualification réelle.

01 — Les trois phases du TCCC
Le Tactical Combat Casualty Care (TCCC) découpe la prise en charge en trois phases publiques : Care Under Fire (sous le feu), Tactical Field Care (soins dans la zone tactique, une fois à couvert) et Tactical Evacuation Care (TACEVAC, pendant l’évacuation). La phase dépend de la situation tactique, pas de la gravité de la blessure : un même blessé passe d’une phase à l’autre quand le feu ennemi cesse ou quand il est embarqué.

02 — Care Under Fire : le feu d’abord
Sous le feu effectif, la meilleure protection du blessé est la supériorité du feu. L’équipe riposte, se met à couvert et évite de créer d’autres blessés. Le seul soin en jeu à ce stade est l’arrêt d’une hémorragie massive d’un membre (garrot ACE), puis on traîne ou porte le blessé à couvert avec les actions ACE.

03 — Tactical Field Care : l’ordre MARCH
À couvert, on examine le blessé dans l’ordre MARCH : Massive hemorrhage (hémorragies graves), Airway (voies aériennes), Respiration, Circulation, Hypothermia / Head (refroidissement, tête). En jeu, l’ordre sert de liste de vérification dans le menu médical ACE : saignements d’abord, puis état de conscience et respiration, pouls et tension, puis le reste. Selon les réglages du serveur, certaines étapes n’ont pas d’action ACE dédiée : on les vérifie quand même et on les rapporte.

04 — CASEVAC, MEDEVAC et TACEVAC
Le MEDEVAC utilise un moyen dédié, marqué et équipé, avec du personnel médical qui soigne pendant le trajet. Le CASEVAC utilise un moyen non dédié, souvent un hélicoptère ou un véhicule « d’opportunité », qui peut ne pas offrir de soins en route. TACEVAC désigne en TCCC l’évacuation depuis la zone tactique, qu’elle soit MEDEVAC ou CASEVAC. Sur notre serveur, un hélicoptère de transport sans médecin à bord fait du CASEVAC ; l’appareil prévu au briefing avec un médecin joueur, indicatif « Dust Off », fait du MEDEVAC.

05 — Les catégories de précédence
La demande 9-line classe les blessés : A — URGENT (évacuation au plus vite pour sauver la vie, un membre ou la vue), B — URGENT-SURGICAL (même urgence, mais une chirurgie avancée est nécessaire), C — PRIORITY (évacuation rapide, l’état peut devenir urgent), D — ROUTINE (état stable), E — CONVENIENCE (convenance, pas de nécessité médicale). L’ATP 4-02.2 de 2019 donne au plus une heure pour A et B, quatre heures pour C et 24 heures pour D ; d’autres documents publics donnent des délais différents et le STANAG 3204 a supprimé la catégorie E. En jeu, retenez surtout l’ordre de priorité.

06 — Qui trie et qui transmet
Le médecin, ou à défaut le joueur le plus expérimenté présent, détermine les catégories ; le chef d’équipe transmet. Le piège classique est la sur-catégorisation : déclarer URGENT un blessé stable mobilise l’hélicoptère au détriment d’un blessé plus grave.

07 — Ce qu’il faut retenir
Le feu d’abord, puis l’hémorragie massive, puis le blessé à couvert. À couvert : MARCH dans le menu ACE. Ensuite : trier honnêtement (A à E) et rendre compte au chef, qui prépare la demande 9-line du chapitre suivant.
FR;
        $theoryEn = <<<'EN'
TRAINING OBJECTIVE
This course trains players to manage casualties in our Arma 3 missions, from the first shot to boarding the medical evacuation helicopter. NATO terms stay in English on the radio, as in the JTAC course. All care described is that of the in-game ACE medical system: nothing here is real-world medical advice or first-aid training. In real life, call the emergency services (112 or your local number). This is in-game training: the Athena Academy qualification awarded is internal to the community and has no real-world qualification value.

01 — The three phases of TCCC
Tactical Combat Casualty Care (TCCC) divides casualty care into three public phases: Care Under Fire, Tactical Field Care (care in the tactical area, once in cover) and Tactical Evacuation Care (TACEVAC, during evacuation). The phase depends on the tactical situation, not on how serious the wound is: the same casualty moves from one phase to the next when enemy fire stops or when they are loaded.

02 — Care Under Fire: fire first
Under effective fire, the casualty’s best protection is fire superiority. The team returns fire, takes cover and avoids creating more casualties. The only in-game care at this stage is stopping massive limb bleeding (ACE tourniquet); the casualty is then dragged or carried into cover with the ACE actions.

03 — Tactical Field Care: the MARCH order
In cover, the casualty is checked in the MARCH order: Massive haemorrhage, Airway, Respiration, Circulation, Hypothermia / Head. In game, the order is a checklist in the ACE medical menu: bleeding first, then consciousness and breathing, pulse and blood pressure, then the rest. Depending on server settings, some steps have no dedicated ACE action: check them anyway and report them.

04 — CASEVAC, MEDEVAC and TACEVAC
MEDEVAC uses a dedicated, marked and equipped asset with medical personnel who treat en route. CASEVAC uses a non-dedicated asset, often a helicopter or vehicle “of opportunity”, which may not provide en route care. In TCCC, TACEVAC is evacuation from the tactical area, whether MEDEVAC or CASEVAC. On our server, a transport helicopter without a medic on board performs CASEVAC; the aircraft planned at the briefing with a player medic, callsign “Dust Off”, performs MEDEVAC.

05 — Precedence categories
The 9-line request sorts casualties: A — URGENT (evacuate as soon as possible to save life, limb or eyesight), B — URGENT-SURGICAL (same urgency, but advanced surgery is needed), C — PRIORITY (prompt evacuation, the condition may become urgent), D — ROUTINE (stable condition), E — CONVENIENCE (not a medical necessity). ATP 4-02.2 (2019) gives a maximum of one hour for A and B, four hours for C and 24 hours for D; other public documents give different times and STANAG 3204 has deleted category E. In game, remember the order of priority above all.

06 — Who sorts and who transmits
The medic, or failing that the most experienced player present, decides the categories; the team leader transmits. The classic trap is over-categorisation: declaring a stable casualty URGENT ties up the helicopter at the expense of a more serious casualty.

07 — Key points
Fire first, then massive bleeding, then the casualty into cover. In cover: MARCH in the ACE menu. Then sort honestly (A to E) and report to the leader, who prepares the 9-line request of the next chapter.
EN;
        $demoFr = <<<'FR'
DÉMONSTRATION COMMENTÉE — EMBUSCADE SUR LA PISTE
Situation de jeu : le groupe « Viking 2-1 » est pris sous le feu d’une mitrailleuse ennemie en lisière de bois. Deux joueurs tombent. Le médecin du groupe a pour indicatif « Toubib ».

Chef Viking 2-1 : « Contact, mitrailleuse à l’est, 200 mètres ! Base de feu sur la lisière, tout le monde à couvert ! »
Commentaire : Care Under Fire. La première action est de gagner la supériorité du feu, pas de soigner.

Toubib : « Hammer saigne fort à la jambe, il est dans le fossé, je peux l’atteindre à couvert. Je pose un garrot et je le tire derrière le talus. »
Commentaire : seule action médicale sous le feu, l’hémorragie massive d’un membre (garrot ACE), puis déplacement à couvert. Le second blessé, à découvert, attend que le feu soit maîtrisé.

Chef Viking 2-1 : « Mitrailleuse neutralisée. Toubib, à toi. »
Commentaire : le feu ennemi a cessé, on passe en Tactical Field Care.

Toubib : « Hammer : garrot tenu, plus d’autre saignement, conscient, respire, pouls rapide. Ranger : inconscient, plusieurs plaies au torse, je le traite en premier. »
Commentaire : ordre MARCH appliqué dans le menu ACE ; Toubib commence par le blessé le plus grave.

Toubib : « Chef, deux blessés. Ranger : URGENT. Hammer : PRIORITY, il pourra devenir urgent si le garrot reste longtemps. »
Commentaire : le médecin trie et justifie, le chef rend compte et prépare la demande. Hammer n’est pas déclaré URGENT « par sécurité » : ce serait de la sur-catégorisation.

À RETENIR
Le feu, puis l’hémorragie massive, puis le couvert ; ensuite MARCH et un tri honnête. Le chef dispose maintenant des informations des lignes 3 et 5 de la demande 9-line.
FR;
        $demoEn = <<<'EN'
ANNOTATED DEMONSTRATION — AMBUSH ON THE TRACK
Game situation: squad “Viking 2-1” comes under fire from an enemy machine gun at the edge of a wood. Two players go down. The squad medic’s callsign is “Doc”.

Viking 2-1 lead: “Contact, machine gun east, 200 metres! Base of fire on the treeline, everyone into cover!”
Comment: Care Under Fire. The first action is to win fire superiority, not to treat.

Doc: “Hammer is bleeding heavily from the leg, he’s in the ditch, I can reach him in cover. Tourniquet, then I’ll drag him behind the bank.”
Comment: the only medical action under fire is massive limb bleeding (ACE tourniquet), then moving to cover. The second casualty, in the open, waits until fire is under control.

Viking 2-1 lead: “Machine gun neutralised. Doc, your turn.”
Comment: enemy fire has stopped, we move to Tactical Field Care.

Doc: “Hammer: tourniquet holding, no other bleeding, conscious, breathing, fast pulse. Ranger: unconscious, several chest wounds, I’m treating him first.”
Comment: the MARCH order applied in the ACE menu; Doc starts with the most serious casualty.

Doc: “Lead, two casualties. Ranger: URGENT. Hammer: PRIORITY, he may become urgent if the tourniquet stays on a long time.”
Comment: the medic sorts and justifies, the leader reports and prepares the request. Hammer is not declared URGENT “just in case”: that would be over-categorisation.

KEY POINT
Fire, then massive bleeding, then cover; then MARCH and honest triage. The leader now has the information for lines 3 and 5 of the 9-line request.
EN;
        $guided = K::stage(
            K::question(
                'Toubib annonce trois blessés : Ranger, URGENT ; Hammer, PRIORITY ; Fox, ROUTINE. Comment le chef doit-il transmettre la ligne 3 ?',
                'Doc reports three casualties: Ranger, URGENT; Hammer, PRIORITY; Fox, ROUTINE. How should the leader send line 3?',
                [
                    K::text('« Ligne 3 : trois URGENT », pour être sûr que l’hélicoptère décolle.', '“Line 3: three URGENT”, to make sure the helicopter launches.'),
                    K::text('« Ligne 3 : un ALPHA, BREAK, un CHARLIE, BREAK, un DELTA. »', '“Line 3: one ALPHA, BREAK, one CHARLIE, BREAK, one DELTA.”'),
                    K::text('« Ligne 3 : trois blessés », sans catégorie, le médecin de l’hélicoptère triera.', '“Line 3: three casualties”, without category, the helicopter medic will sort them.'),
                ],
                1,
                'La ligne 3 donne le nombre de blessés par précédence, avec « BREAK » entre les catégories : A = URGENT, C = PRIORITY, D = ROUTINE. Tout déclarer URGENT est de la sur-catégorisation ; ne rien préciser empêche de hiérarchiser les missions.',
                'Line 3 gives the number of casualties by precedence, with “BREAK” between categories: A = URGENT, C = PRIORITY, D = ROUTINE. Declaring everything URGENT is over-categorisation; giving no category prevents missions from being prioritised.'
            ),
            K::text(
                "CAS GUIDÉ — TROIS BLESSÉS À VIKING 2-1\nVous êtes chef de Viking 2-1. Le feu ennemi a cessé. Toubib a terminé le premier passage MARCH dans le menu ACE et vous annonce : Ranger, URGENT ; Hammer, PRIORITY ; Fox, une éraflure au bras, ROUTINE. Vous préparez la ligne 3 de la demande.\n\nTravaillez dans cet ordre :\n1. Rappelez la lettre de chaque catégorie (A à E).\n2. Comptez les blessés de chaque catégorie annoncée par le médecin.\n3. Rappelez le mot qui sépare deux catégories dans la même ligne.\n4. Choisissez la formulation fidèle au tri du médecin.",
                "GUIDED CASE — THREE CASUALTIES AT VIKING 2-1\nYou are the Viking 2-1 leader. Enemy fire has stopped. Doc has finished the first MARCH pass in the ACE menu and reports: Ranger, URGENT; Hammer, PRIORITY; Fox, a graze on the arm, ROUTINE. You are preparing line 3 of the request.\n\nWork in this order:\n1. Recall the letter for each category (A to E).\n2. Count the casualties in each category reported by the medic.\n3. Recall the word that separates two categories in the same line.\n4. Choose the wording that matches the medic’s triage."
            ),
            K::text(
                'Relisez les sections 05 et 06 : A URGENT, B URGENT-SURGICAL, C PRIORITY, D ROUTINE, E CONVENIENCE ; le médecin trie, le chef transmet fidèlement.',
                'Reread sections 05 and 06: A URGENT, B URGENT-SURGICAL, C PRIORITY, D ROUTINE, E CONVENIENCE; the medic sorts, the leader transmits faithfully.'
            )
        );
        $simulation = K::stage(
            K::question(
                'Que faites-vous en premier ?',
                'What do you do first?',
                [
                    K::text('Vous courez vers Jester pour faire son examen MARCH complet sur place.', 'You run to Jester to carry out his full MARCH check where he lies.'),
                    K::text('Vous demandez tout de suite la 9-line à la radio, avant de riposter.', 'You send the 9-line on the radio straight away, before returning fire.'),
                    K::text('Vous ripostez avec le groupe pour reprendre l’ascendant, puis vous mettez Jester à couvert et ne traitez sur place qu’une hémorragie massive si vous pouvez l’atteindre.', 'You return fire with the squad to regain the upper hand, then move Jester into cover, treating on the spot only massive bleeding if you can reach him.'),
                ],
                2,
                'Sous le feu effectif, la supériorité du feu protège le mieux le blessé et le reste du groupe. Le seul soin sous le feu est l’hémorragie massive ; l’examen MARCH complet attend le couvert. La 9-line se prépare après le tri, pas pendant l’échange de tirs.',
                'Under effective fire, fire superiority best protects the casualty and the rest of the squad. The only care under fire is massive bleeding; the full MARCH check waits until cover. The 9-line is prepared after triage, not during the firefight.'
            ),
            K::text(
                "SIMULATION AUTONOME — JESTER À DÉCOUVERT\nVous êtes médecin d’un groupe qui traverse un champ. Un tireur ennemi ouvre le feu depuis une ferme à 300 mètres. Jester tombe à découvert, à quinze mètres de vous ; il bouge encore. Le reste du groupe est à plat ventre dans un fossé et commence à riposter de manière désordonnée.\n\nVotre objectif : appliquer la bonne phase TCCC. Aucune aide n’est donnée pendant cette étape ; vous pourrez revoir le cours après une tentative.",
                "INDEPENDENT SIMULATION — JESTER IN THE OPEN\nYou are the medic of a squad crossing a field. An enemy shooter opens fire from a farm 300 metres away. Jester goes down in the open, fifteen metres from you; he is still moving. The rest of the squad is lying in a ditch and starting to return fire in a disorganised way.\n\nYour objective: apply the right TCCC phase. No hint is given during this stage; you may review the course after an attempt."
            )
        );
        $questions = [
            K::question(
                'Quelles sont les trois phases du TCCC ?',
                'What are the three phases of TCCC?',
                [
                    K::text('Care Under Fire, Tactical Field Care, Tactical Evacuation Care.', 'Care Under Fire, Tactical Field Care, Tactical Evacuation Care.'),
                    K::text('Triage, 9-line, MIST.', 'Triage, 9-line, MIST.'),
                    K::text('Urgent, Priority, Routine.', 'Urgent, Priority, Routine.'),
                ],
                0,
                'Les phases suivent la situation tactique : sous le feu, à couvert dans la zone, puis pendant l’évacuation. Urgent, Priority et Routine sont des catégories de précédence ; 9-line et MIST sont des comptes rendus.',
                'The phases follow the tactical situation: under fire, in cover in the area, then during evacuation. Urgent, Priority and Routine are precedence categories; 9-line and MIST are reports.'
            ),
            K::question(
                'Que signifie l’ordre MARCH ?',
                'What does the MARCH order stand for?',
                [
                    K::text('Mouvement, Abri, Radio, Couvert, Hélicoptère.', 'Movement, Shelter, Radio, Cover, Helicopter.'),
                    K::text('Massive hemorrhage, Airway, Respiration, Circulation, Hypothermia / Head.', 'Massive haemorrhage, Airway, Respiration, Circulation, Hypothermia / Head.'),
                    K::text('MEDEVAC, Ambulance, Relève, CASEVAC, HLZ.', 'MEDEVAC, Ambulance, Relief, CASEVAC, HLZ.'),
                ],
                1,
                'MARCH fixe l’ordre de vérification en Tactical Field Care ; en jeu, il guide l’examen dans le menu médical ACE, saignements d’abord.',
                'MARCH sets the checking order in Tactical Field Care; in game, it guides the check in the ACE medical menu, bleeding first.'
            ),
            K::question(
                'Sous le feu effectif, quel est le seul soin à réaliser en jeu ?',
                'Under effective fire, what is the only care to give in game?',
                [
                    K::text('Un examen complet du blessé dans le menu ACE.', 'A full check of the casualty in the ACE menu.'),
                    K::text('Une perfusion pour stabiliser le blessé.', 'An IV to stabilise the casualty.'),
                    K::text('L’arrêt d’une hémorragie massive d’un membre (garrot ACE), avant de mettre le blessé à couvert.', 'Stopping massive limb bleeding (ACE tourniquet), before moving the casualty into cover.'),
                ],
                2,
                'Sous le feu, on riposte et on se met à couvert ; seule l’hémorragie massive est traitée sur place. Le reste attend la phase Tactical Field Care.',
                'Under fire, you return fire and take cover; only massive bleeding is treated on the spot. Everything else waits for Tactical Field Care.'
            ),
            K::question(
                'Quelle différence entre MEDEVAC et CASEVAC ?',
                'What is the difference between MEDEVAC and CASEVAC?',
                [
                    K::text('Le MEDEVAC se fait par la route, le CASEVAC par les airs.', 'MEDEVAC is by road, CASEVAC by air.'),
                    K::text('Le MEDEVAC utilise un moyen dédié avec soins en route ; le CASEVAC un moyen non dédié, qui peut ne pas offrir de soins.', 'MEDEVAC uses a dedicated asset with en route care; CASEVAC a non-dedicated asset, which may not provide care.'),
                    K::text('Aucune : ce sont deux noms du même hélicoptère.', 'None: they are two names for the same helicopter.'),
                ],
                1,
                'La différence tient au moyen, pas au milieu : un MEDEVAC peut être terrestre ou aérien. Sur notre serveur, un transport sans médecin à bord fait du CASEVAC.',
                'The difference lies in the asset, not the medium: MEDEVAC can be by ground or air. On our server, a transport without a medic on board performs CASEVAC.'
            ),
            K::question(
                'Que désigne la catégorie B dans la demande 9-line ?',
                'What does category B mean in the 9-line request?',
                [
                    K::text('ROUTINE : état stable.', 'ROUTINE: stable condition.'),
                    K::text('URGENT-SURGICAL : urgence vitale nécessitant une chirurgie avancée.', 'URGENT-SURGICAL: life-threatening emergency requiring advanced surgery.'),
                    K::text('CONVENIENCE : évacuation de convenance.', 'CONVENIENCE: evacuation for convenience.'),
                ],
                1,
                'A URGENT, B URGENT-SURGICAL, C PRIORITY, D ROUTINE, E CONVENIENCE. A et B ont le même degré d’urgence ; B précise qu’une chirurgie est nécessaire.',
                'A URGENT, B URGENT-SURGICAL, C PRIORITY, D ROUTINE, E CONVENIENCE. A and B share the same urgency; B states that surgery is needed.'
            ),
            K::question(
                'Un joueur conseille de déclarer URGENT tous les blessés « pour que l’hélico vienne plus vite ». Pourquoi est-ce une erreur ?',
                'A player suggests declaring every casualty URGENT “so the helicopter comes faster”. Why is that a mistake?',
                [
                    K::text('Parce qu’une demande URGENT doit être envoyée par écrit.', 'Because an URGENT request must be sent in writing.'),
                    K::text('Parce que la catégorie URGENT n’existe pas dans la 9-line.', 'Because the URGENT category does not exist in the 9-line.'),
                    K::text('C’est de la sur-catégorisation : elle mobilise l’appareil au détriment des blessés réellement les plus graves.', 'It is over-categorisation: it ties up the aircraft at the expense of the casualties who are really most serious.'),
                ],
                2,
                'Le tri doit refléter l’état réel. La sur-catégorisation, signalée comme un problème récurrent dans les supports TCCC publics, fausse la priorité entre les missions.',
                'Triage must reflect the real condition. Over-categorisation, flagged as a recurring problem in public TCCC material, distorts priorities between missions.'
            ),
        ];

        return K::chapter(
            K::text('Chapitre 1 — TCCC niveau joueur, triage et précédence', 'Chapter 1 — Player-level TCCC, triage and precedence'),
            K::text($theoryFr, $theoryEn),
            K::text($demoFr, $demoEn),
            $guided,
            $simulation,
            $questions,
            K::text(
                "Rédigez un retour personnel sur le cas Jester et votre évaluation :\n1. La phase TCCC dans laquelle vous étiez et ce qui la déterminait.\n2. Ce que vous avez fait sous le feu, et ce que vous avez volontairement reporté.\n3. L’ordre MARCH tel que vous l’appliquez dans le menu ACE.\n4. Comment vous éviteriez la sur-catégorisation.\n5. Une amélioration concrète à tester lors de votre prochaine mission.",
                "Write a personal reflection on the Jester case and your assessment:\n1. The TCCC phase you were in and what determined it.\n2. What you did under fire, and what you deliberately postponed.\n3. The MARCH order as you apply it in the ACE menu.\n4. How you would avoid over-categorisation.\n5. One concrete improvement to test in your next mission."
            ),
            [
                K::text('Les trois phases TCCC sont nommées et reliées à la situation tactique.', 'The three TCCC phases are named and linked to the tactical situation.'),
                K::text('La priorité du feu et l’hémorragie massive sous le feu sont expliquées.', 'The priority of fire and massive bleeding under fire are explained.'),
                K::text('Les catégories A à E sont restituées et la sur-catégorisation identifiée.', 'Categories A to E are restated and over-categorisation is identified.'),
                K::text('Amélioration concrète et vérifiable en mission.', 'Concrete improvement that can be checked in a mission.'),
            ],
            [
                K::text('L’évaluation atteint au moins 80 % dans le délai prévu.', 'The assessment reaches at least 80% within the time limit.'),
                K::text('Le stagiaire distingue Care Under Fire et Tactical Field Care sur un cas de jeu.', 'The learner distinguishes Care Under Fire and Tactical Field Care in a game case.'),
                K::text('Le stagiaire énonce l’ordre MARCH et l’applique dans le menu médical ACE.', 'The learner states the MARCH order and applies it in the ACE medical menu.'),
                K::text('Le stagiaire classe des blessés de jeu en catégories A à E sans sur-catégoriser.', 'The learner sorts game casualties into categories A to E without over-categorising.'),
                K::text('Le stagiaire rappelle qu’il s’agit du système médical du jeu et non d’un conseil médical réel.', 'The learner states that this is the game’s medical system and not real-world medical advice.'),
            ],
            [
                K::sheet(
                    K::text('Fiche mémo — TCCC niveau joueur et triage', 'Memo card — Player-level TCCC and triage'),
                    K::text(
                        "FORMATION DE JEU (ARMA 3) — sans valeur de qualification réelle.\nSoins = système médical ACE en jeu. Aucun conseil médical réel. Urgence réelle : 15 ou 112.\n\nTROIS PHASES\n1. Care Under Fire : riposter, se mettre à couvert, pas de nouveau blessé. Seul soin : hémorragie massive d’un membre (garrot ACE). Traîner / porter à couvert.\n2. Tactical Field Care : à couvert, examen MARCH dans le menu ACE.\n3. TACEVAC : évacuation (MEDEVAC ou CASEVAC), soins poursuivis en route si possible.\n\nMARCH\nM — hémorragies graves · A — voies aériennes · R — respiration · C — circulation (pouls, tension) · H — refroidissement / tête.\n\nPRÉCÉDENCE (ligne 3)\nA URGENT · B URGENT-SURGICAL · C PRIORITY · D ROUTINE · E CONVENIENCE.\nLe médecin trie, le chef transmet. Pas de sur-catégorisation.\n\nMEDEVAC = moyen dédié, soins en route, « Dust Off ». CASEVAC = moyen non dédié.",
                        "IN-GAME TRAINING (ARMA 3) — no real-world qualification value.\nCare = in-game ACE medical system. No real-world medical advice. Real emergency: 112 or your local number.\n\nTHREE PHASES\n1. Care Under Fire: return fire, take cover, no new casualties. Only care: massive limb bleeding (ACE tourniquet). Drag / carry into cover.\n2. Tactical Field Care: in cover, MARCH check in the ACE menu.\n3. TACEVAC: evacuation (MEDEVAC or CASEVAC), care continued en route where possible.\n\nMARCH\nM — massive haemorrhage · A — airway · R — respiration · C — circulation (pulse, blood pressure) · H — hypothermia / head.\n\nPRECEDENCE (line 3)\nA URGENT · B URGENT-SURGICAL · C PRIORITY · D ROUTINE · E CONVENIENCE.\nThe medic sorts, the leader transmits. No over-categorisation.\n\nMEDEVAC = dedicated asset, en route care, “Dust Off”. CASEVAC = non-dedicated asset."
                    )
                ),
            ]
        );
    }

    // ------------------------------------------------------------------ Chapitre 2 — La demande 9-line

    /** @return array<string, mixed> */
    private static function chapterTwo(): array
    {
        $theoryFr = <<<'FR'
01 — Rôle de la demande 9-line
La demande 9-line est un format standard qui permet de lancer une évacuation sanitaire sans dialogue médical : elle dit où, qui contacter, combien de blessés, quoi emporter et dans quelles conditions se poser. Elle n’est pas une consultation avec un médecin. Rassemblez toutes les informations avant d’appuyer sur l’alternat, transmettez ligne par ligne en annonçant le numéro (« Line 1… »), et utilisez le canal ACRE prévu au briefing. Les lignes 1 à 5 suffisent à faire décoller l’appareil : si la situation presse, envoyez-les d’abord et complétez ensuite.

02 — Ligne 1 : position de la zone de poser ; ligne 2 : fréquence et indicatif
Ligne 1 : coordonnées MGRS du point de ramassage, lettres de zone comprises, au moins six chiffres. Lisez-les sur la carte ou dans l’ATAK en jeu, et vérifiez-les : une erreur envoie l’hélicoptère au mauvais endroit. Ligne 2 : la fréquence (ou le canal ACRE) et l’indicatif de la station au point de ramassage, pas ceux d’un relais. Si la demande passe par le poste de commandement, celui-ci retransmet exactement la ligne 2 du demandeur ; il peut ajouter son propre contact en complément.

03 — Ligne 3 : blessés par précédence ; ligne 4 : équipement spécial
Ligne 3 : nombre de blessés par catégorie, A URGENT, B URGENT-SURGICAL, C PRIORITY, D ROUTINE, E CONVENIENCE, avec « BREAK » entre deux catégories. Ligne 4 : A aucun, B treuil (hoist), C matériel de désincarcération, D ventilateur. En jeu, le treuil n’existe que si la mission le prévoit : demandez-le seulement si l’appareil ne peut pas se poser.

04 — Ligne 5 : blessés couchés et assis
Ligne 5 : L suivi du nombre de blessés couchés (litter), A suivi du nombre de blessés assis (ambulatory), avec « BREAK » entre les deux. Dans ACE, un blessé inconscient ou qui ne peut pas marcher est un blessé couché : il faut le porter et le charger. L’équipage en déduit la place et le nombre d’appareils.

05 — Ligne 6 : sécurité ; ligne 7 : marquage
Ligne 6, en temps de guerre : N pas d’ennemi dans la zone, P ennemi possible (approche prudente), E ennemi dans la zone (approche prudente), X ennemi dans la zone, escorte armée nécessaire. En temps de paix, la ligne 6 donne le nombre et le type de blessures. Ligne 7 : A panneaux, B signal pyrotechnique, C fumigène, D aucun, E autre (par exemple strobe IR). La couleur du fumigène n’est jamais annoncée dans la demande : elle sera identifiée par l’équipage à l’arrivée.

06 — Ligne 8 : nationalité et statut ; ligne 9 : NBC
Ligne 8, dans le format public : A militaire des États-Unis, B civil des États-Unis, C militaire d’un autre pays, D civil d’un autre pays, E prisonnier ennemi. Convention de jeu de la communauté, sauf consigne contraire au briefing : A pour les militaires de notre camp, B pour les civils de notre camp, C pour les militaires alliés d’une autre nation, D pour les autres civils, E pour les prisonniers. Ligne 9, en temps de guerre : C chimique, B biologique, R radiologique, N nucléaire, seulement si applicable ; en temps de paix, description du terrain autour de la zone de poser (relief, obstacles, repère visible).

07 — Ce qu’il faut retenir
Préparer, puis transmettre ligne par ligne ; lignes 1 à 5 en priorité ; ligne 2 = demandeur, jamais le relais ; « BREAK » entre catégories ; pas de couleur de fumigène à la radio. Le destinataire collationne : corrigez immédiatement toute erreur avec « CORRECTION ».
FR;
        $theoryEn = <<<'EN'
01 — Purpose of the 9-line request
The 9-line request is a standard format that launches a medical evacuation without a medical conversation: it says where, whom to contact, how many casualties, what to bring and under what conditions to land. It is not a consultation with a doctor. Gather all the information before keying the radio, send line by line announcing the number (“Line 1…”), and use the ACRE channel set at the briefing. Lines 1 to 5 are enough to launch the aircraft: if the situation is pressing, send them first and complete the rest afterwards.

02 — Line 1: pickup site location; line 2: frequency and callsign
Line 1: MGRS grid of the pickup site, including the grid zone letters, at least six digits. Read it from the map or the in-game ATAK, and check it: an error sends the helicopter to the wrong place. Line 2: the frequency (or ACRE channel) and callsign of the station at the pickup site, not those of a relay. If the request goes through the command post, the command post relays the requester’s line 2 exactly; it may add its own contact as additional information.

03 — Line 3: casualties by precedence; line 4: special equipment
Line 3: number of casualties per category, A URGENT, B URGENT-SURGICAL, C PRIORITY, D ROUTINE, E CONVENIENCE, with “BREAK” between two categories. Line 4: A none, B hoist, C extraction equipment, D ventilator. In game, the hoist only exists if the mission provides it: ask for it only if the aircraft cannot land.

04 — Line 5: litter and ambulatory casualties
Line 5: L followed by the number of litter casualties, A followed by the number of ambulatory (walking) casualties, with “BREAK” between the two. In ACE, an unconscious casualty or one who cannot walk is a litter casualty: they must be carried and loaded. The crew uses this to plan space and the number of aircraft.

05 — Line 6: security; line 7: marking
Line 6, in wartime: N no enemy in the area, P possible enemy (approach with caution), E enemy in the area (approach with caution), X enemy in the area, armed escort required. In peacetime, line 6 gives the number and type of wounds. Line 7: A panels, B pyrotechnic signal, C smoke, D none, E other (for example IR strobe). The smoke colour is never given in the request: the crew will identify it on arrival.

06 — Line 8: nationality and status; line 9: CBRN
Line 8, in the public format: A US military, B US civilian, C non-US military, D non-US civilian, E enemy prisoner of war. Community game convention, unless the briefing says otherwise: A for our side’s military, B for our side’s civilians, C for allied military of another nation, D for other civilians, E for prisoners. Line 9, in wartime: C chemical, B biological, R radiological, N nuclear, only if applicable; in peacetime, a description of the terrain around the landing site (relief, obstacles, visible landmark).

07 — Key points
Prepare, then send line by line; lines 1 to 5 first; line 2 = the requester, never the relay; “BREAK” between categories; no smoke colour on the radio. The receiving station reads back: correct any error immediately with “CORRECTION”.
EN;
        $demoFr = <<<'FR'
DÉMONSTRATION COMMENTÉE — DEMANDE 9-LINE DE VIKING 2-1
Situation de jeu : suite de l’embuscade du chapitre 1. Ranger est URGENT et inconscient, Hammer est PRIORITY et peut marcher avec aide. Le poste de commandement « Rempart » relaie vers l’hélicoptère « Dust Off 1 ». Ennemi neutralisé, mais un autre groupe a été signalé à un kilomètre.

Viking 2-1 : « Rempart, Viking 2-1, demande MEDEVAC, 9-line, prêt à copier ? »
Rempart : « Viking 2-1, Rempart, prêt à copier. »
Commentaire : on annonce la nature du message et on attend que le destinataire soit prêt.

Viking 2-1 : « Line 1 : grid 37T DK 412 785. Line 2 : canal 4, Viking 2-1. Line 3 : one ALPHA, BREAK, one CHARLIE. Line 4 : ALPHA. Line 5 : LIMA one, BREAK, ALPHA one. »
Commentaire : les lignes 1 à 5 suffisent à lancer l’appareil. La ligne 2 est le canal de Viking 2-1, au point de ramassage. Ranger, inconscient, est couché (L) ; Hammer est assis (A).

Viking 2-1 : « Line 6 : PAPA. Line 7 : CHARLIE. Line 8 : ALPHA two. Line 9 : none. Over. »
Commentaire : P, car un ennemi a été signalé à proximité ; C, fumigène, sans couleur ; A, deux militaires de notre camp ; pas de contamination.

Rempart : « Copié : grid 37T DK 412 785, canal 4, Viking 2-1, one ALPHA, one CHARLIE, ALPHA, LIMA one, ALPHA one, PAPA, CHARLIE, ALPHA two, none. »
Viking 2-1 : « Collationnement correct. »
Commentaire : le collationnement complet permet de détecter une erreur de coordonnées avant le décollage.

Rempart : « Dust Off 1, Rempart, mission MEDEVAC, contact au sol : Viking 2-1, canal 4. »
Commentaire : le relais retransmet le canal et l’indicatif du demandeur, pas les siens.

À RETENIR
Une demande préparée tient en moins d’une minute. L’hélicoptère sait où aller, qui appeler, combien de places préparer, et qu’il faut approcher prudemment.
FR;
        $demoEn = <<<'EN'
ANNOTATED DEMONSTRATION — VIKING 2-1 9-LINE REQUEST
Game situation: continuation of the chapter 1 ambush. Ranger is URGENT and unconscious, Hammer is PRIORITY and can walk with help. The command post “Rempart” relays to the helicopter “Dust Off 1”. The enemy has been neutralised, but another group has been reported one kilometre away.

Viking 2-1: “Rempart, Viking 2-1, MEDEVAC request, 9-line, ready to copy?”
Rempart: “Viking 2-1, Rempart, ready to copy.”
Comment: announce the type of message and wait until the receiver is ready.

Viking 2-1: “Line 1: grid 37T DK 412 785. Line 2: channel 4, Viking 2-1. Line 3: one ALPHA, BREAK, one CHARLIE. Line 4: ALPHA. Line 5: LIMA one, BREAK, ALPHA one.”
Comment: lines 1 to 5 are enough to launch the aircraft. Line 2 is Viking 2-1’s channel, at the pickup site. Ranger, unconscious, is litter (L); Hammer is ambulatory (A).

Viking 2-1: “Line 6: PAPA. Line 7: CHARLIE. Line 8: ALPHA two. Line 9: none. Over.”
Comment: P, because an enemy group has been reported nearby; C, smoke, with no colour; A, two of our side’s military; no contamination.

Rempart: “Copied: grid 37T DK 412 785, channel 4, Viking 2-1, one ALPHA, one CHARLIE, ALPHA, LIMA one, ALPHA one, PAPA, CHARLIE, ALPHA two, none.”
Viking 2-1: “Readback correct.”
Comment: a full readback catches a grid error before take-off.

Rempart: “Dust Off 1, Rempart, MEDEVAC mission, ground contact: Viking 2-1, channel 4.”
Comment: the relay passes on the requester’s channel and callsign, not its own.

KEY POINT
A prepared request takes less than a minute. The helicopter knows where to go, whom to call, how many places to prepare, and that it must approach with caution.
EN;
        $guided = K::stage(
            K::question(
                'Que doit contenir la ligne 2 transmise par Rempart à Dust Off 1 ?',
                'What should line 2 contain when Rempart passes it to Dust Off 1?',
                [
                    K::text('Le canal 7 et l’indicatif Rempart, puisque c’est Rempart qui transmet.', 'Channel 7 and the callsign Rempart, since Rempart is transmitting.'),
                    K::text('Rien : la ligne 2 est inutile quand il y a un relais.', 'Nothing: line 2 is pointless when there is a relay.'),
                    K::text('Le canal 3 et l’indicatif Lynx 1-2, ceux de la station au point de ramassage ; Rempart peut ajouter son contact en complément.', 'Channel 3 and the callsign Lynx 1-2, those of the station at the pickup site; Rempart may add its own contact as extra information.'),
                ],
                2,
                'La ligne 2 sert à l’équipage pour contacter le point de ramassage en route. Le relais retransmet exactement les informations du demandeur ; son propre contact n’est qu’une information supplémentaire.',
                'Line 2 lets the crew contact the pickup site en route. The relay passes on the requester’s information exactly; its own contact is only additional information.'
            ),
            K::text(
                "CAS GUIDÉ — RELAIS PAR REMPART\nLe groupe « Lynx 1-2 », au point de ramassage, émet sur le canal ACRE 3. Il ne peut pas joindre directement l’hélicoptère et passe sa demande 9-line au poste de commandement « Rempart », qui travaille sur le canal 7 et retransmet à « Dust Off 1 ».\n\nTravaillez dans cet ordre :\n1. Rappelez à quoi sert la ligne 2 pour l’équipage.\n2. Identifiez la station qui sera au point de ramassage quand l’hélicoptère arrivera.\n3. Rappelez ce qu’un relais a le droit d’ajouter.\n4. Choisissez la réponse correcte.",
                "GUIDED CASE — RELAY THROUGH BASTILLE\nThe group “Lynx 1-2”, at the pickup site, transmits on ACRE channel 3. It cannot reach the helicopter directly and passes its 9-line request to the command post “Rempart”, which works on channel 7 and relays to “Dust Off 1”.\n\nWork in this order:\n1. Recall what line 2 is for, from the crew’s point of view.\n2. Identify the station that will be at the pickup site when the helicopter arrives.\n3. Recall what a relay is allowed to add.\n4. Choose the correct answer."
            ),
            K::text(
                'Relisez la section 02 : la ligne 2 donne la fréquence et l’indicatif au point de ramassage, pas ceux du relais.',
                'Reread section 02: line 2 gives the frequency and callsign at the pickup site, not those of the relay.'
            )
        );
        $simulation = K::stage(
            K::question(
                'Quelle série de lignes 3 à 7 est correcte ?',
                'Which set of lines 3 to 7 is correct?',
                [
                    K::text('« Line 3 : two ALPHA, BREAK, one DELTA. Line 4 : ALPHA. Line 5 : LIMA two, BREAK, ALPHA one. Line 6 : X-RAY. Line 7 : CHARLIE. »', '“Line 3: two ALPHA, BREAK, one DELTA. Line 4: ALPHA. Line 5: LIMA two, BREAK, ALPHA one. Line 6: X-RAY. Line 7: CHARLIE.”'),
                    K::text('« Line 3 : three ALPHA. Line 4 : BRAVO. Line 5 : ALPHA three. Line 6 : NOVEMBER. Line 7 : fumée verte. »', '“Line 3: three ALPHA. Line 4: BRAVO. Line 5: ALPHA three. Line 6: NOVEMBER. Line 7: green smoke.”'),
                    K::text('« Line 3 : two ALPHA, one DELTA. Line 4 : DELTA. Line 5 : LIMA three. Line 6 : PAPA. Line 7 : DELTA. »', '“Line 3: two ALPHA, one DELTA. Line 4: DELTA. Line 5: LIMA three. Line 6: PAPA. Line 7: DELTA.”'),
                ],
                0,
                'Deux URGENT et un ROUTINE donnent two ALPHA, BREAK, one DELTA. Le terrain dégagé ne demande aucun équipement (A). Les deux inconscients sont couchés, le troisième marche. Un ennemi encore présent qui impose une escorte, c’est X. Le marquage est un fumigène (C), sans annoncer la couleur.',
                'Two URGENT and one ROUTINE give two ALPHA, BREAK, one DELTA. Open ground needs no equipment (A). The two unconscious casualties are litter, the third walks. An enemy still present requiring an escort is X. The marking is smoke (C), without stating the colour.'
            ),
            K::text(
                "SIMULATION AUTONOME — CONVOI TOUCHÉ\nUn véhicule de votre convoi a sauté sur un engin explosif. Le médecin a trié : deux blessés inconscients, URGENT ; un conducteur légèrement touché qui marche, ROUTINE. La zone de poser, un champ dégagé à côté de la route, permet à l’hélicoptère de se poser. Des tirs ennemis continuent depuis un village proche et le chef estime qu’une escorte armée est nécessaire. Vous marquerez la zone avec un fumigène.\n\nVotre objectif : rédiger les lignes 3 à 7. Aucune aide n’est donnée pendant cette étape ; vous pourrez revoir le cours après une tentative.",
                "INDEPENDENT SIMULATION — CONVOY HIT\nA vehicle in your convoy has struck an explosive device. The medic has sorted: two unconscious casualties, URGENT; one lightly injured driver who can walk, ROUTINE. The landing site, an open field beside the road, allows the helicopter to land. Enemy fire continues from a nearby village and the leader judges that an armed escort is needed. You will mark the site with smoke.\n\nYour objective: draft lines 3 to 7. No hint is given during this stage; you may review the course after an attempt."
            )
        );
        $questions = [
            K::question(
                'Quelles lignes suffisent à lancer l’appareil d’évacuation ?',
                'Which lines are enough to launch the evacuation aircraft?',
                [
                    K::text('Les lignes 1 à 5.', 'Lines 1 to 5.'),
                    K::text('Seulement la ligne 9.', 'Only line 9.'),
                    K::text('Les lignes 6 à 9.', 'Lines 6 to 9.'),
                ],
                0,
                'Position, contact, nombre et précédence des blessés, équipement et type de blessés suffisent pour décoller ; les lignes 6 à 9 complètent la préparation de l’approche.',
                'Location, contact, number and precedence of casualties, equipment and type of casualties are enough to take off; lines 6 to 9 complete the approach planning.'
            ),
            K::question(
                'Que contient la ligne 1 ?',
                'What does line 1 contain?',
                [
                    K::text('L’indicatif du médecin.', 'The medic’s callsign.'),
                    K::text('Les coordonnées MGRS du point de ramassage, lettres de zone comprises.', 'The MGRS grid of the pickup site, including the grid zone letters.'),
                    K::text('La position de l’ennemi.', 'The enemy position.'),
                ],
                1,
                'La ligne 1 dit à l’équipage où se poser. On la lit sur la carte ou l’ATAK en jeu et on la vérifie avant de la transmettre.',
                'Line 1 tells the crew where to land. Read it from the map or the in-game ATAK and check it before sending.'
            ),
            K::question(
                'En ligne 5, que signifie « LIMA two, BREAK, ALPHA one » ?',
                'On line 5, what does “LIMA two, BREAK, ALPHA one” mean?',
                [
                    K::text('Deux blessés URGENT et un blessé assis.', 'Two URGENT casualties and one ambulatory casualty.'),
                    K::text('Deux appareils demandés, un seul nécessaire.', 'Two aircraft requested, only one needed.'),
                    K::text('Deux blessés couchés et un blessé assis.', 'Two litter casualties and one ambulatory casualty.'),
                ],
                2,
                'L = litter (couché), A = ambulatory (assis, qui marche). La ligne 5 sert à prévoir la place à bord ; la précédence est en ligne 3.',
                'L = litter, A = ambulatory (able to walk). Line 5 is used to plan space on board; precedence is on line 3.'
            ),
            K::question(
                'En temps de guerre, que signifie « X » en ligne 6 ?',
                'In wartime, what does “X” mean on line 6?',
                [
                    K::text('Aucun ennemi dans la zone.', 'No enemy in the area.'),
                    K::text('Ennemi dans la zone, escorte armée nécessaire.', 'Enemy in the area, armed escort required.'),
                    K::text('Zone contaminée.', 'Contaminated area.'),
                ],
                1,
                'N, aucun ennemi ; P, ennemi possible ; E, ennemi présent, approche prudente ; X, ennemi présent, escorte armée nécessaire. La contamination est en ligne 9.',
                'N, no enemy; P, possible enemy; E, enemy present, approach with caution; X, enemy present, armed escort required. Contamination is on line 9.'
            ),
            K::question(
                'Pourquoi n’annonce-t-on pas la couleur du fumigène dans la demande ?',
                'Why is the smoke colour not stated in the request?',
                [
                    K::text('Pour qu’un ennemi à l’écoute ne puisse pas imiter le marquage : l’équipage annonce la couleur qu’il voit et le sol confirme.', 'So that a listening enemy cannot imitate the marking: the crew states the colour it sees and the ground confirms.'),
                    K::text('Parce que la couleur ne sert à rien.', 'Because the colour serves no purpose.'),
                    K::text('Parce que seuls les fumigènes rouges sont autorisés.', 'Because only red smoke is allowed.'),
                ],
                0,
                'L’ATP 4-02.2 précise que la couleur n’est pas transmise avant l’arrivée de l’appareil ; l’équipage l’identifie et l’unité au sol la vérifie.',
                'ATP 4-02.2 states that the colour is not transmitted before the aircraft arrives; the crew identifies it and the ground unit verifies it.'
            ),
            K::question(
                'En temps de paix (exercice, secours à des civils dans la mission), que remplacent les lignes 6 et 9 ?',
                'In peacetime (exercise, helping civilians in the mission), what replace lines 6 and 9?',
                [
                    K::text('Ligne 6 : la météo ; ligne 9 : l’heure d’arrivée.', 'Line 6: the weather; line 9: the arrival time.'),
                    K::text('Elles sont supprimées.', 'They are removed.'),
                    K::text('Ligne 6 : nombre et type de blessures ; ligne 9 : description du terrain de la zone de poser.', 'Line 6: number and type of wounds; line 9: description of the landing site terrain.'),
                ],
                2,
                'Sans menace ennemie ni NBC, ces lignes servent à mieux préparer l’équipage : nature des blessures et terrain autour de la zone de poser.',
                'With no enemy or CBRN threat, these lines help the crew prepare: nature of the wounds and terrain around the landing site.'
            ),
            K::question(
                'Pendant le collationnement, vous entendez une erreur dans la ligne 1. Que faites-vous ?',
                'During the readback, you hear an error in line 1. What do you do?',
                [
                    K::text('Rien, l’équipage verra bien la fumée.', 'Nothing, the crew will see the smoke anyway.'),
                    K::text('Vous annoncez « CORRECTION » et retransmettez la ligne 1 correcte.', 'You say “CORRECTION” and resend the correct line 1.'),
                    K::text('Vous recommencez une nouvelle demande complète plus tard.', 'You start a whole new request later.'),
                ],
                1,
                'Une erreur de position envoie l’appareil au mauvais endroit et retarde l’évacuation. On la corrige aussitôt, sans recommencer toute la demande.',
                'A location error sends the aircraft to the wrong place and delays the evacuation. Correct it straight away, without restarting the whole request.'
            ),
        ];

        return K::chapter(
            K::text('Chapitre 2 — La demande 9-line, ligne par ligne', 'Chapter 2 — The 9-line request, line by line'),
            K::text($theoryFr, $theoryEn),
            K::text($demoFr, $demoEn),
            $guided,
            $simulation,
            $questions,
            K::text(
                "Rédigez un retour personnel sur le cas du convoi touché et votre évaluation :\n1. La demande 9-line complète que vous auriez transmise.\n2. La ligne que vous avez eu le plus de mal à remplir et pourquoi.\n3. Comment vous vérifiez la ligne 1 dans l’ATAK ou sur la carte.\n4. Ce qu’un relais doit retransmettre sans le modifier.\n5. Une amélioration concrète à tester lors de votre prochaine mission.",
                "Write a personal reflection on the convoy case and your assessment:\n1. The full 9-line request you would have sent.\n2. The line you found hardest to fill in and why.\n3. How you check line 1 in the ATAK or on the map.\n4. What a relay must pass on without changing it.\n5. One concrete improvement to test in your next mission."
            ),
            [
                K::text('Les neuf lignes sont restituées dans l’ordre avec leurs codes.', 'The nine lines are restated in order with their codes.'),
                K::text('Le rôle des lignes 1 à 5 et la ligne 2 en cas de relais sont expliqués.', 'The role of lines 1 to 5 and line 2 with a relay are explained.'),
                K::text('La règle de la couleur du fumigène est justifiée.', 'The smoke colour rule is justified.'),
                K::text('Amélioration concrète et vérifiable en mission.', 'Concrete improvement that can be checked in a mission.'),
            ],
            [
                K::text('L’évaluation atteint au moins 80 % dans le délai prévu.', 'The assessment reaches at least 80% within the time limit.'),
                K::text('Le stagiaire transmet une 9-line complète à la radio ACRE en moins d’une minute.', 'The learner sends a complete 9-line on the ACRE radio in under a minute.'),
                K::text('Les catégories et types sont séparés par « BREAK » et la couleur du fumigène n’est pas annoncée.', 'Categories and types are separated by “BREAK” and the smoke colour is not announced.'),
                K::text('Le collationnement est vérifié et toute erreur corrigée par « CORRECTION ».', 'The readback is checked and any error corrected with “CORRECTION”.'),
            ],
            [
                K::sheet(
                    K::text('Carte 9-line MEDEVAC', '9-line MEDEVAC card'),
                    K::text(
                        "FORMATION DE JEU (ARMA 3) — sans valeur de qualification réelle.\n\n1 — Position de la zone de poser : MGRS avec lettres de zone, 6 chiffres minimum (carte / ATAK).\n2 — Fréquence ou canal ACRE + indicatif AU POINT DE RAMASSAGE (jamais le relais).\n3 — Blessés par précédence : A URGENT · B URGENT-SURGICAL · C PRIORITY · D ROUTINE · E CONVENIENCE. « BREAK » entre catégories.\n4 — Équipement spécial : A aucun · B treuil · C désincarcération · D ventilateur.\n5 — Blessés : L+nombre couchés · A+nombre assis. « BREAK » entre les deux.\n6 — Sécurité (guerre) : N aucun ennemi · P ennemi possible · E ennemi, approche prudente · X ennemi, escorte armée. (Paix : nombre et type de blessures.)\n7 — Marquage : A panneaux · B pyrotechnie · C fumigène · D aucun · E autre (strobe IR). Pas de couleur à la radio.\n8 — Nationalité / statut (convention de jeu) : A militaires de notre camp · B civils de notre camp · C militaires alliés · D autres civils · E prisonniers.\n9 — NBC (guerre) : C · B · R · N, si applicable. (Paix : terrain de la zone de poser.)\n\nLignes 1 à 5 = lancement. Collationnement, « CORRECTION » si erreur. Indicatif de l’appareil : « Dust Off ».",
                        "IN-GAME TRAINING (ARMA 3) — no real-world qualification value.\n\n1 — Pickup site location: MGRS with grid zone letters, 6 digits minimum (map / ATAK).\n2 — Frequency or ACRE channel + callsign AT THE PICKUP SITE (never the relay).\n3 — Casualties by precedence: A URGENT · B URGENT-SURGICAL · C PRIORITY · D ROUTINE · E CONVENIENCE. “BREAK” between categories.\n4 — Special equipment: A none · B hoist · C extraction equipment · D ventilator.\n5 — Casualties: L+number litter · A+number ambulatory. “BREAK” between the two.\n6 — Security (wartime): N no enemy · P possible enemy · E enemy, approach with caution · X enemy, armed escort. (Peacetime: number and type of wounds.)\n7 — Marking: A panels · B pyrotechnic · C smoke · D none · E other (IR strobe). No colour on the radio.\n8 — Nationality / status (game convention): A our side’s military · B our side’s civilians · C allied military · D other civilians · E prisoners.\n9 — CBRN (wartime): C · B · R · N, if applicable. (Peacetime: landing site terrain.)\n\nLines 1 to 5 = launch. Readback, “CORRECTION” if wrong. Aircraft callsign: “Dust Off”."
                    )
                ),
            ]
        );
    }

    // ------------------------------------------------------------------ Chapitre 3 — HLZ, marquage, MIST/ATMIST et passation

    /** @return array<string, mixed> */
    private static function chapterThree(): array
    {
        $theoryFr = <<<'FR'
01 — Choisir la zone de poser (HLZ)
La zone de poser d’hélicoptère (HLZ) doit être assez grande et plane pour l’appareil prévu, dégagée de câbles, d’arbres et de poteaux sur les axes d’approche, et à l’abri du feu direct de l’ennemi. Elle est proche du point de regroupement des blessés, sans être dessus : le souffle du rotor soulève poussière et objets. Évitez les ravins étroits, les routes où circulent des véhicules et les zones encombrées. Dans Arma 3, vérifiez la pente et les obstacles sur place, pas seulement sur la carte.

02 — Préparer et sécuriser
Avant l’arrivée : retirer ou arrimer les objets légers, regrouper les blessés à l’abri au bord de la zone, désigner des porteurs pour les blessés couchés, placer des joueurs en sûreté sur le pourtour. La ligne 6 annoncée doit rester vraie : si la situation change (tirs, ennemi aperçu), prévenez l’équipage immédiatement. Notez l’origine du vent, par exemple avec la fumée : l’hélicoptère se pose de préférence face au vent.

03 — Marquer de jour
Le marquage indique la zone exacte. De jour, le fumigène est le plus courant. Lancez-le seulement quand l’équipage le demande ou à l’approche, pour ne pas révéler la zone trop tôt. La procédure : l’équipage annonce la couleur qu’il voit, le sol confirme ou infirme. Si deux fumées sont visibles, l’équipage ne se pose pas avant confirmation. Les panneaux de signalisation et un guide au sol complètent si la mission les fournit.

04 — Marquer de nuit
De nuit, utilisez un marquage visible aux jumelles de vision nocturne : strobe IR, bâtons lumineux, selon les objets ACE disponibles dans la mission. Annoncez le type de marquage en ligne 7 (E, autre, par exemple « strobe IR »). La même règle de confirmation s’applique : l’équipage décrit ce qu’il voit, le sol confirme. Ajoutez un repère sur l’ATAK en jeu si l’équipage y a accès, sans remplacer le marquage visuel.

05 — Le compte rendu MIST et ATMIST
Après la 9-line, et sans retarder la mission, on transmet un compte rendu médical court par blessé. MIST : Mechanism (cause de la blessure : balle, éclat, explosion), Injuries (blessures constatées), Signs (signes observés dans le menu ACE : conscience, pouls, tension, saignement), Treatment (soins ACE donnés, avec l’heure de pose du garrot). La variante ATMIST, répandue notamment chez les Britanniques, ajoute Age et Time (heure de la blessure). En jeu, l’âge n’apporte rien : on retient surtout l’heure de la blessure et l’heure du garrot.

06 — Accueillir « Dust Off » et passer le relais
Comme dans le cours JTAC, l’hélicoptère d’évacuation sanitaire a pour indicatif « Dust Off ». À son approche : contact radio sur le canal de la ligne 2, marquage, confirmation, puis le sol se tait sauf danger. Une fois posé, chargez les blessés dans l’ordre de priorité, URGENT d’abord. Le médecin au sol fait la passation au médecin à bord : le MIST de chaque blessé, de vive voix, et la carte de triage ACE renseignée. La passation est brève ; l’appareil ne doit pas rester au sol plus que nécessaire.

07 — Ce qu’il faut retenir
HLZ dégagée et sécurisée, vent noté, blessés prêts au bord ; marquage lancé à la demande, couleur annoncée par l’équipage et confirmée par le sol ; MIST par blessé ; chargement URGENT d’abord ; passation courte.
FR;
        $theoryEn = <<<'EN'
01 — Choosing the helicopter landing zone (HLZ)
The HLZ must be large and level enough for the expected aircraft, clear of wires, trees and poles on the approach paths, and out of the enemy’s direct fire. It is close to the casualty collection point, but not on top of it: rotor wash lifts dust and objects. Avoid narrow gullies, roads used by vehicles and cluttered areas. In Arma 3, check the slope and obstacles on the ground, not only on the map.

02 — Preparing and securing
Before arrival: remove or secure light objects, gather the casualties under cover at the edge of the zone, assign carriers for litter casualties, post players on security around the perimeter. The line 6 you sent must remain true: if the situation changes (fire, enemy sighted), warn the crew immediately. Note the wind direction, for example with smoke: the helicopter prefers to land into the wind.

03 — Marking by day
Marking shows the exact zone. By day, smoke is the most common. Throw it only when the crew asks or on approach, so as not to reveal the zone too early. The procedure: the crew states the colour it sees, the ground confirms or denies. If two smokes are visible, the crew does not land before confirmation. Marker panels and a ground guide complement this if the mission provides them.

04 — Marking at night
At night, use marking visible through night vision: IR strobe, chemical lights, depending on the ACE items available in the mission. State the marking type on line 7 (E, other, for example “IR strobe”). The same confirmation rule applies: the crew describes what it sees, the ground confirms. Add a marker on the in-game ATAK if the crew has access to it, without replacing the visual marking.

05 — The MIST and ATMIST report
After the 9-line, and without delaying the mission, a short medical report is sent for each casualty. MIST: Mechanism (cause of injury: bullet, fragment, blast), Injuries (wounds found), Signs (what the ACE menu shows: consciousness, pulse, blood pressure, bleeding), Treatment (ACE care given, with the time the tourniquet went on). The ATMIST variant, widely used notably by British forces, adds Age and Time (time of injury). In game, age adds nothing: remember above all the time of injury and the tourniquet time.

06 — Receiving “Dust Off” and handing over
As in the JTAC course, the medical evacuation helicopter’s callsign is “Dust Off”. On its approach: radio contact on the line 2 channel, marking, confirmation, then the ground stays quiet unless there is danger. Once it has landed, load the casualties in order of priority, URGENT first. The ground medic hands over to the medic on board: the MIST for each casualty, spoken, and the completed ACE triage card. The handover is brief; the aircraft must not stay on the ground longer than necessary.

07 — Key points
HLZ clear and secured, wind noted, casualties ready at the edge; marking thrown on request, colour stated by the crew and confirmed by the ground; MIST for each casualty; URGENT loaded first; short handover.
EN;
        $demoFr = <<<'FR'
DÉMONSTRATION COMMENTÉE — ARRIVÉE DE DUST OFF 1
Situation de jeu : Viking 2-1 a préparé une HLZ dans un pré à 80 mètres du fossé où sont les blessés. Deux joueurs tiennent la lisière, deux porteurs sont prêts pour Ranger.

Dust Off 1 : « Viking 2-1, Dust Off 1, deux minutes, marquez la zone. »
Viking 2-1 : « Dust Off 1, Viking 2-1, fumée lancée. Vent d’ouest, faible. Pas de nouveau contact. »
Commentaire : la fumée est lancée à la demande. Le sol confirme que la ligne 6 n’a pas changé et donne le vent.

Dust Off 1 : « Je vois fumée violette. »
Viking 2-1 : « Violette confirmée. »
Commentaire : l’équipage annonce la couleur, le sol confirme. Personne n’a donné la couleur à la radio avant.

Toubib, au médecin de bord : « Ranger, URGENT. Mécanisme : balles, rafale de mitrailleuse. Blessures : plusieurs plaies au torse. Signes : inconscient, pouls rapide et faible, tension basse. Traitement : pansements, perfusion en cours. Blessé à 14 h 05. »
Commentaire : MIST complet, avec l’heure de la blessure (le T de l’ATMIST). Les signes sont ceux affichés par le menu ACE.

Toubib : « Hammer, PRIORITY. Mécanisme : balle. Blessure : cuisse gauche. Signes : conscient, pouls rapide. Traitement : garrot posé à 14 h 06. Il marche avec aide. »
Commentaire : l’heure de pose du garrot est indispensable à la suite des soins en jeu.

Dust Off 1 : « Deux à bord, décollage. »
Commentaire : Ranger, URGENT, a été chargé en premier ; la passation a duré moins d’une minute.

À RETENIR
Marquage à la demande, couleur par l’équipage et confirmation par le sol, MIST court par blessé, chargement par priorité. L’hélicoptère n’est resté au sol que le temps nécessaire.
FR;
        $demoEn = <<<'EN'
ANNOTATED DEMONSTRATION — DUST OFF 1 ARRIVES
Game situation: Viking 2-1 has prepared an HLZ in a meadow 80 metres from the ditch where the casualties lie. Two players hold the treeline, two carriers are ready for Ranger.

Dust Off 1: “Viking 2-1, Dust Off 1, two minutes, mark the zone.”
Viking 2-1: “Dust Off 1, Viking 2-1, smoke out. Wind from the west, light. No new contact.”
Comment: smoke is thrown on request. The ground confirms that line 6 has not changed and gives the wind.

Dust Off 1: “I see violet smoke.”
Viking 2-1: “Violet confirmed.”
Comment: the crew states the colour, the ground confirms. Nobody gave the colour on the radio beforehand.

Doc, to the onboard medic: “Ranger, URGENT. Mechanism: gunshot, machine-gun burst. Injuries: several chest wounds. Signs: unconscious, fast weak pulse, low blood pressure. Treatment: dressings, IV running. Wounded at 14:05.”
Comment: full MIST, with the time of injury (the T in ATMIST). The signs are those shown in the ACE menu.

Doc: “Hammer, PRIORITY. Mechanism: gunshot. Injury: left thigh. Signs: conscious, fast pulse. Treatment: tourniquet on at 14:06. He can walk with help.”
Comment: the tourniquet time is essential for continued care in game.

Dust Off 1: “Two on board, lifting.”
Comment: Ranger, URGENT, was loaded first; the handover took less than a minute.

KEY POINT
Marking on request, colour by the crew and confirmation by the ground, a short MIST for each casualty, loading by priority. The helicopter stayed on the ground only as long as necessary.
EN;
        $guided = K::stage(
            K::question(
                'Que répondez-vous à Dust Off 1 ?',
                'How do you answer Dust Off 1?',
                [
                    K::text('« Négatif, notre fumée est verte. Attendez, je relance un marquage. » Vous n’autorisez pas le poser sur la fumée jaune.', '“Negative, our smoke is green. Wait, I’m marking again.” You do not clear a landing on the yellow smoke.'),
                    K::text('« Jaune confirmée », pour ne pas retarder l’évacuation.', '“Yellow confirmed”, so as not to delay the evacuation.'),
                    K::text('Rien : l’équipage finira par voir la bonne fumée.', 'Nothing: the crew will eventually see the right smoke.'),
                ],
                0,
                'La confirmation sert précisément à détecter un marquage ennemi ou erroné. Une couleur différente de la vôtre doit être infirmée, et l’appareil ne se pose pas avant une confirmation correcte.',
                'Confirmation exists precisely to catch an enemy or wrong marking. A colour different from yours must be denied, and the aircraft does not land before a correct confirmation.'
            ),
            K::text(
                "CAS GUIDÉ — DEUX FUMÉES\nVous êtes chef de Viking 2-1. Vous avez lancé une fumée verte sur votre HLZ à la demande de Dust Off 1. L’équipage annonce : « Je vois fumée jaune. » Un groupe ennemi a été signalé au sud et pourrait écouter le réseau.\n\nTravaillez dans cet ordre :\n1. Comparez la couleur annoncée avec celle que vous avez lancée.\n2. Rappelez qui annonce la couleur et qui confirme.\n3. Demandez-vous ce que pourrait être une fumée que vous n’avez pas lancée.\n4. Choisissez la réponse qui protège l’équipage.",
                "GUIDED CASE — TWO SMOKES\nYou are the Viking 2-1 leader. You threw green smoke on your HLZ when Dust Off 1 asked. The crew reports: “I see yellow smoke.” An enemy group has been reported to the south and could be listening to the net.\n\nWork in this order:\n1. Compare the colour reported with the one you threw.\n2. Recall who states the colour and who confirms.\n3. Ask yourself what a smoke you did not throw could be.\n4. Choose the answer that protects the crew."
            ),
            K::text(
                'Relisez la section 03 : l’équipage annonce ce qu’il voit, le sol confirme ou infirme ; pas de poser sans confirmation.',
                'Reread section 03: the crew states what it sees, the ground confirms or denies; no landing without confirmation.'
            )
        );
        $simulation = K::stage(
            K::question(
                'Quel compte rendu de passation donnez-vous au médecin de bord ?',
                'Which handover report do you give the onboard medic?',
                [
                    K::text('« Il est blessé, il faut faire vite, je vous laisse voir. »', '“He’s hurt, you need to be quick, I’ll let you look.”'),
                    K::text('« Fox, PRIORITY. Mécanisme : éclat d’explosion. Blessure : avant-bras droit. Signes : conscient, pouls rapide. Traitement : garrot à 02 h 10, pansement. Blessé à 02 h 08. »', '“Fox, PRIORITY. Mechanism: blast fragment. Injury: right forearm. Signs: conscious, fast pulse. Treatment: tourniquet at 02:10, dressing. Wounded at 02:08.”'),
                    K::text('Vous répétez toute la demande 9-line au médecin de bord.', 'You repeat the whole 9-line request to the onboard medic.'),
                ],
                1,
                'La passation suit MIST (ou ATMIST avec l’heure de la blessure) : mécanisme, blessures, signes ACE, traitement avec l’heure du garrot. La 9-line a déjà servi à lancer la mission ; une passation vague fait perdre des informations utiles.',
                'The handover follows MIST (or ATMIST with the time of injury): mechanism, injuries, ACE signs, treatment with the tourniquet time. The 9-line has already served to launch the mission; a vague handover loses useful information.'
            ),
            K::text(
                "SIMULATION AUTONOME — FOX DE NUIT\nIl est 2 h 15 dans la mission. Fox a été touché à 2 h 08 par un éclat de grenade à l’avant-bras droit. Vous lui avez posé un garrot ACE à 2 h 10 puis un pansement ; le menu ACE indique qu’il est conscient, pouls rapide. Le médecin l’a classé PRIORITY. Dust Off 2 vient de se poser sur votre HLZ marquée par strobe IR, et son médecin vous demande la passation.\n\nVotre objectif : donner une passation utile et brève. Aucune aide n’est donnée pendant cette étape ; vous pourrez revoir le cours après une tentative.",
                "INDEPENDENT SIMULATION — FOX AT NIGHT\nIt is 02:15 in the mission. Fox was hit at 02:08 by a grenade fragment in the right forearm. You applied an ACE tourniquet at 02:10 then a dressing; the ACE menu shows he is conscious, fast pulse. The medic classed him PRIORITY. Dust Off 2 has just landed on your HLZ marked by IR strobe, and its medic asks you for the handover.\n\nYour objective: give a useful, brief handover. No hint is given during this stage; you may review the course after an attempt."
            )
        );
        $questions = [
            K::question(
                'Quelle zone convient le mieux comme HLZ ?',
                'Which area is best suited as an HLZ?',
                [
                    K::text('La route principale, plate et facile à trouver.', 'The main road, flat and easy to find.'),
                    K::text('Un ravin étroit, bien abrité des tirs.', 'A narrow gully, well sheltered from fire.'),
                    K::text('Un pré plat et dégagé, hors du feu direct ennemi, proche des blessés sans être sur eux.', 'A flat, open meadow, out of direct enemy fire, close to the casualties without being on top of them.'),
                ],
                2,
                'Une HLZ doit être plane, dégagée d’obstacles sur les axes d’approche et hors du feu direct. Les routes fréquentées et les zones confinées sont des erreurs classiques.',
                'An HLZ must be level, clear of obstacles on the approach paths and out of direct fire. Busy roads and confined areas are classic mistakes.'
            ),
            K::question(
                'Quand lance-t-on le fumigène de marquage ?',
                'When is the marking smoke thrown?',
                [
                    K::text('Dès l’envoi de la 9-line, pour gagner du temps.', 'As soon as the 9-line is sent, to save time.'),
                    K::text('À la demande de l’équipage ou à son approche.', 'When the crew asks, or on its approach.'),
                    K::text('Jamais : le fumigène est interdit en MEDEVAC.', 'Never: smoke is forbidden for MEDEVAC.'),
                ],
                1,
                'Une fumée trop précoce révèle la zone à l’ennemi et peut s’être dissipée à l’arrivée. On marque quand l’appareil est proche.',
                'Smoke thrown too early reveals the zone to the enemy and may have cleared by arrival. Mark when the aircraft is close.'
            ),
            K::question(
                'De nuit, quel marquage choisir et comment l’annoncer en ligne 7 ?',
                'At night, which marking should you use and how is it given on line 7?',
                [
                    K::text('Aucun marquage : l’ATAK suffit.', 'No marking: the ATAK is enough.'),
                    K::text('Un fumigène, annoncé en « C », car il se voit aussi bien la nuit.', 'Smoke, given as “C”, because it is just as visible at night.'),
                    K::text('Un strobe IR ou des bâtons lumineux, annoncés en « E », autre, avec leur nature.', 'An IR strobe or chemical lights, given as “E”, other, with their type.'),
                ],
                2,
                'La nuit, l’équipage cherche un marquage visible sous vision nocturne. L’ATAK complète mais ne remplace pas le marquage visuel.',
                'At night, the crew looks for marking visible through night vision. The ATAK complements but does not replace visual marking.'
            ),
            K::question(
                'Que signifie MIST ?',
                'What does MIST stand for?',
                [
                    K::text('Mission, Indicatif, Sécurité, Terrain.', 'Mission, Identity, Security, Terrain.'),
                    K::text('MEDEVAC, Itinéraire, Signal, Treuil.', 'MEDEVAC, Itinerary, Signal, Tether.'),
                    K::text('Mechanism, Injuries, Signs, Treatment.', 'Mechanism, Injuries, Signs, Treatment.'),
                ],
                2,
                'MIST décrit chaque blessé : cause, blessures, signes observés et soins donnés. Il est envoyé après la 9-line, sans retarder la mission.',
                'MIST describes each casualty: cause, injuries, signs observed and care given. It is sent after the 9-line, without delaying the mission.'
            ),
            K::question(
                'Que l’ATMIST ajoute-t-il au MIST ?',
                'What does ATMIST add to MIST?',
                [
                    K::text('L’altitude et la température.', 'Altitude and temperature.'),
                    K::text('L’âge et l’heure de la blessure.', 'Age and time of injury.'),
                    K::text('L’armement et le type d’appareil.', 'Armament and aircraft type.'),
                ],
                1,
                'ATMIST = Age, Time, Mechanism, Injuries, Signs, Treatment. En jeu, l’heure de la blessure et celle du garrot sont les informations les plus utiles.',
                'ATMIST = Age, Time, Mechanism, Injuries, Signs, Treatment. In game, the time of injury and the tourniquet time are the most useful items.'
            ),
            K::question(
                'Dust Off 1 est posé avec trois blessés à embarquer : un URGENT, un PRIORITY, un ROUTINE. Dans quel ordre les chargez-vous ?',
                'Dust Off 1 has landed with three casualties to load: one URGENT, one PRIORITY, one ROUTINE. In what order do you load them?',
                [
                    K::text('URGENT, puis PRIORITY, puis ROUTINE.', 'URGENT, then PRIORITY, then ROUTINE.'),
                    K::text('ROUTINE d’abord, car il marche et libère la place.', 'ROUTINE first, because he walks and frees up space.'),
                    K::text('Dans l’ordre où les porteurs arrivent.', 'In the order the carriers arrive.'),
                ],
                0,
                'Le chargement suit la précédence : l’URGENT doit partir quoi qu’il arrive, même si l’appareil doit décoller avant d’avoir tout embarqué.',
                'Loading follows precedence: the URGENT casualty must leave whatever happens, even if the aircraft has to lift before everyone is on board.'
            ),
        ];

        return K::chapter(
            K::text('Chapitre 3 — HLZ, marquage, MIST/ATMIST et passation', 'Chapter 3 — HLZ, marking, MIST/ATMIST and handover'),
            K::text($theoryFr, $theoryEn),
            K::text($demoFr, $demoEn),
            $guided,
            $simulation,
            $questions,
            K::text(
                "Rédigez un retour personnel sur le cas Fox et votre évaluation :\n1. Comment vous auriez choisi et préparé la HLZ de nuit.\n2. Le marquage choisi et la procédure de confirmation.\n3. Votre passation MIST ou ATMIST complète pour Fox.\n4. Ce qui pourrait retenir l’hélicoptère au sol trop longtemps, et comment l’éviter.\n5. Une amélioration concrète à tester lors de votre prochaine mission.",
                "Write a personal reflection on the Fox case and your assessment:\n1. How you would have chosen and prepared the HLZ at night.\n2. The marking chosen and the confirmation procedure.\n3. Your complete MIST or ATMIST handover for Fox.\n4. What could keep the helicopter on the ground too long, and how to avoid it.\n5. One concrete improvement to test in your next mission."
            ),
            [
                K::text('Les critères de choix et de préparation de la HLZ sont restitués.', 'The HLZ selection and preparation criteria are restated.'),
                K::text('La règle « l’équipage annonce, le sol confirme » est expliquée.', 'The “crew states, ground confirms” rule is explained.'),
                K::text('Le MIST ou l’ATMIST est complet, avec les heures utiles.', 'The MIST or ATMIST is complete, with the useful times.'),
                K::text('Amélioration concrète et vérifiable en mission.', 'Concrete improvement that can be checked in a mission.'),
            ],
            [
                K::text('L’évaluation atteint au moins 80 % dans le délai prévu.', 'The assessment reaches at least 80% within the time limit.'),
                K::text('Le stagiaire choisit et prépare une HLZ cohérente sur le terrain de jeu.', 'The learner selects and prepares a sound HLZ on the game terrain.'),
                K::text('Le marquage est lancé au bon moment et confirmé selon la procédure.', 'The marking is thrown at the right time and confirmed according to the procedure.'),
                K::text('La passation MIST/ATMIST au « Dust Off » est brève et complète.', 'The MIST/ATMIST handover to “Dust Off” is brief and complete.'),
            ],
            [
                K::sheet(
                    K::text('Fiche mémo — HLZ, marquage et passation', 'Memo card — HLZ, marking and handover'),
                    K::text(
                        "FORMATION DE JEU (ARMA 3) — sans valeur de qualification réelle.\nSoins = système médical ACE en jeu. Aucun conseil médical réel.\n\nHLZ\nPlane, dégagée (câbles, arbres, poteaux), hors du feu direct, proche des blessés sans être dessus. Pas de route fréquentée, pas de ravin étroit. Objets légers retirés, sûreté sur le pourtour, porteurs désignés, vent noté.\n\nMARQUAGE\nJour : fumigène à la demande ou à l’approche. Nuit : strobe IR / bâtons lumineux (ligne 7 = E, autre). L’ÉQUIPAGE ANNONCE la couleur ou ce qu’il voit, LE SOL CONFIRME. Doute = pas de poser.\n\nMIST (par blessé, après la 9-line)\nMechanism · Injuries · Signs (menu ACE) · Treatment (heure du garrot).\nATMIST = + Age + Time (heure de la blessure).\n\nPASSATION À « DUST OFF »\nContact sur le canal de la ligne 2 · marquage · confirmation · silence radio sauf danger · chargement URGENT d’abord · MIST de vive voix + carte de triage ACE · moins d’une minute au sol si possible.",
                        "IN-GAME TRAINING (ARMA 3) — no real-world qualification value.\nCare = in-game ACE medical system. No real-world medical advice.\n\nHLZ\nLevel, clear (wires, trees, poles), out of direct fire, close to the casualties without being on top of them. No busy road, no narrow gully. Light objects removed, perimeter security, carriers assigned, wind noted.\n\nMARKING\nDay: smoke on request or on approach. Night: IR strobe / chemical lights (line 7 = E, other). THE CREW STATES the colour or what it sees, THE GROUND CONFIRMS. Doubt = no landing.\n\nMIST (per casualty, after the 9-line)\nMechanism · Injuries · Signs (ACE menu) · Treatment (tourniquet time).\nATMIST = + Age + Time (time of injury).\n\nHANDOVER TO “DUST OFF”\nContact on the line 2 channel · marking · confirmation · radio silence unless danger · URGENT loaded first · spoken MIST + ACE triage card · under a minute on the ground if possible."
                    )
                ),
                K::sheet(
                    K::text('Sources publiques', 'Public sources'),
                    K::text(
                        "FORMATION DE JEU (ARMA 3) — sans valeur de qualification réelle.\nContenu reformulé à partir de sources publiques ; rien de classifié.\n\nATP 4-02.2 Medical Evacuation, US Army, 12 juillet 2019, approuvée pour diffusion publique (format 9-line, précédences, MIST, marquage) :\nhttps://armypubs.army.mil/ProductMaps/PubForm/Details.aspx?PUB_ID=1007289\nhttps://armypubs.army.mil/epubs/DR_pubs/DR_a/pdf/web/ARN17834_ATP%204-02x2%20FINAL%20WEB.pdf\nTCCC — Combat Lifesaver, module 19 « Pre-evacuation procedures » (phases TCCC, 9-line, MIST, sur-catégorisation), copie publique :\nhttps://tccc.org.ua/files/downloads/module-19-pre-evacuation-procedures-comms-and-doc-en.pdf\nWikipédia — Tactical combat casualty care (phases, MARCH, CoTCCC, Deployed Medicine) :\nhttps://en.wikipedia.org/wiki/Tactical_combat_casualty_care\nWikipédia — Casualty evacuation (CASEVAC et MEDEVAC) :\nhttps://en.wikipedia.org/wiki/Casualty_evacuation\nWikipédia — Dustoff (origine de l’indicatif « Dust Off ») :\nhttps://en.wikipedia.org/wiki/Dustoff\nCentre d’excellence OTAN pour la médecine militaire (NATO MILMED COE), Budapest :\nhttps://www.coemed.org/\n\nRéférences citées par nom seulement : STANAG 2087 (Forward Aeromedical Evacuation) et STANAG 3204 (Aeromedical Evacuation, AAMedP-1.1), mis en œuvre par l’ATP 4-02.2.",
                        "IN-GAME TRAINING (ARMA 3) — no real-world qualification value.\nContent reworded from public sources; nothing classified.\n\nATP 4-02.2 Medical Evacuation, US Army, 12 July 2019, approved for public release (9-line format, precedence, MIST, marking):\nhttps://armypubs.army.mil/ProductMaps/PubForm/Details.aspx?PUB_ID=1007289\nhttps://armypubs.army.mil/epubs/DR_pubs/DR_a/pdf/web/ARN17834_ATP%204-02x2%20FINAL%20WEB.pdf\nTCCC — Combat Lifesaver, module 19 “Pre-evacuation procedures” (TCCC phases, 9-line, MIST, over-categorisation), public copy:\nhttps://tccc.org.ua/files/downloads/module-19-pre-evacuation-procedures-comms-and-doc-en.pdf\nWikipedia — Tactical combat casualty care (phases, MARCH, CoTCCC, Deployed Medicine):\nhttps://en.wikipedia.org/wiki/Tactical_combat_casualty_care\nWikipedia — Casualty evacuation (CASEVAC and MEDEVAC):\nhttps://en.wikipedia.org/wiki/Casualty_evacuation\nWikipedia — Dustoff (origin of the “Dust Off” callsign):\nhttps://en.wikipedia.org/wiki/Dustoff\nNATO Centre of Excellence for Military Medicine (NATO MILMED COE), Budapest:\nhttps://www.coemed.org/\n\nReferences cited by name only: STANAG 2087 (Forward Aeromedical Evacuation) and STANAG 3204 (Aeromedical Evacuation, AAMedP-1.1), implemented by ATP 4-02.2."
                    )
                ),
            ]
        );
    }
}
