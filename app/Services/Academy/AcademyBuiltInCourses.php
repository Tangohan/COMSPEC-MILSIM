<?php

declare(strict_types=1);

namespace App\Services\Academy;

/**
 * Published, versioned Academy curricula shipped with the application.
 *
 * JTAC 2.0 follows the community's in-game CAS procedure in five phases (initial contact, situation update,
 * game plan, 9-line CAS brief, terminal attack and BDA) plus a brevity chapter. Learners keep the snapshot
 * they enrolled with; database/steps/3280_academy_jtac_v2_refresh.php only refreshes untouched enrolments.
 */
final class AcademyBuiltInCourses
{
    public const JTAC_KEY = 'jtac-licence-ath-academy';
    public const JTAC_VERSION = '2.0';

    /** @return array<string, array<string, mixed>> */
    public static function all(): array
    {
        $course = self::jtac();

        return [self::JTAC_KEY => $course];
    }

    public static function find(string $key): ?array
    {
        return $key === self::JTAC_KEY ? self::jtac() : null;
    }

    private static function text(string $fr, string $en): array
    {
        return ['fr' => $fr, 'en' => $en];
    }

    /** Each answer and its explanation remain in the server-side course snapshot. */
    private static function question(string $fr, string $en, array $options, int $answer, string $feedbackFr, string $feedbackEn): array
    {
        return [
            'question' => self::text($fr, $en),
            'options' => $options,
            'answer' => $answer,
            'feedback' => self::text($feedbackFr, $feedbackEn),
        ];
    }

    private static function jtac(): array
    {
        return [
            'key' => self::JTAC_KEY,
            'title' => self::text('JTAC — Licence ATH-ACADEMY', 'JTAC — ATH-ACADEMY Licence'),
            'summary' => self::text(
                'Formation JTAC de jeu (Arma 3) selon la procédure de la communauté : contact initial, situation update, game plan, CAS brief 9-line, attaque terminale et BDA, puis le vocabulaire brevity. Six chapitres de sept étapes, des échanges radio commentés, des cas guidés et autonomes, une évaluation chronométrée par chapitre et une validation instructeur.',
                'In-game JTAC training (Arma 3) following the community procedure: initial contact, situation update, game plan, 9-line CAS brief, terminal attack and BDA, then brevity words. Six chapters of seven stages, annotated radio exchanges, guided and independent cases, a timed assessment per chapter and instructor validation.'
            ),
            'license' => self::text('Licence de pratique interne ATH-ACADEMY · formation de jeu (Arma 3) · sans valeur de qualification réelle', 'Internal ATH-ACADEMY practice licence · in-game training (Arma 3) · no real-world qualification'),
            'version' => self::JTAC_VERSION,
            'duration_minutes' => 180,
            'published' => true,
            'built_in' => true,
            'chapters' => [
                self::initialContact(),
                self::situationUpdate(),
                self::gamePlan(),
                self::nineLine(),
                self::terminalAttack(),
                self::brevity(),
            ],
        ];
    }

    /**
     * Assemble the seven stages of a chapter. The guided case also fills the legacy chapter question so that the
     * standard course editor can still inspect the chapter.
     *
     * @param list<array<string, mixed>> $questions
     * @param list<array{fr:string,en:string}> $debriefCriteria
     * @param list<array{fr:string,en:string}> $validationCriteria
     */
    private static function chapter(array $title, array $theory, array $demonstration, array $guided, array $simulation, array $questions, array $debriefPrompt, array $debriefCriteria, array $validationCriteria): array
    {
        $count = count($questions);
        $needed = (int) ceil($count * 0.8);

        return [
            'title' => $title,
            'theory' => $theory,
            'demonstration' => $demonstration,
            'question' => $guided['question'],
            'options' => $guided['options'],
            'answer' => $guided['answer'],
            'feedback' => $guided['feedback'],
            'guided' => $guided,
            'simulation' => $simulation,
            'assessment' => [
                'instructions' => self::text(
                    "Répondez aux {$count} questions sans aide. Vous disposez de dix minutes ; au moins {$needed} bonnes réponses (80 %) sont nécessaires. La note est calculée par le site et chaque tentative est conservée. Un dépassement du temps compte comme une tentative non validée. Après réussite, rédigez votre débriefing ; le chapitre est ensuite validé par un instructeur distinct du stagiaire.",
                    "Answer the {$count} questions without assistance. You have ten minutes; at least {$needed} correct answers (80%) are required. The site calculates the score and keeps every attempt. Running out of time counts as an unsuccessful attempt. After passing, write your debrief; an instructor other than the learner then validates the chapter."
                ),
                'seconds' => 600,
                'pass_percent' => 80,
                'questions' => $questions,
            ],
            'debrief' => [
                'prompt' => $debriefPrompt,
                'criteria' => $debriefCriteria,
            ],
            'validation' => [
                'brief' => self::text(
                    'La validation confirme uniquement les acquis de ce chapitre de formation de jeu. Un instructeur habilité, distinct du stagiaire, examine les tentatives et le débriefing selon les critères ci-dessous ; il peut aussi faire répéter la phrase radio en session. Il laisse une justification écrite. Une réussite au questionnaire ne remplace pas cette revue. Si un critère n’est pas démontré, l’instructeur organise une reprise avant de valider. La licence ATH-ACADEMY n’a aucune valeur de qualification réelle.',
                    'Validation confirms learning within this in-game training chapter only. An authorised instructor other than the learner reviews the attempts and debrief against the criteria below; they may also have the learner repeat the radio call during a session. They record a written justification. Passing the quiz does not replace this review. If a criterion has not been demonstrated, the instructor arranges further practice before validating. The ATH-ACADEMY licence has no real-world qualification value.'
                ),
                'criteria' => $validationCriteria,
            ],
            'resources' => self::resources(),
        ];
    }

    // ------------------------------------------------------------------ Phase 1 — Initial contact

    private static function initialContact(): array
    {
        $theoryFr = <<<'FR'
OBJECTIF DE LA FORMATION
Ce cours forme au rôle de JTAC dans nos missions Arma 3 : le joueur au sol qui coordonne un appui aérien rapproché (CAS) piloté par d’autres joueurs. Il reprend la procédure de la communauté, en cinq phases : contact initial, situation update, game plan, CAS brief (9-line), puis attaque terminale et BDA. Un dernier chapitre rassemble le vocabulaire brevity. Les termes OTAN restent en anglais, comme à la radio en jeu. Il s’agit d’une formation de jeu : la licence ATH-ACADEMY atteste un parcours interne et n’a aucune valeur de qualification réelle, militaire ou aéronautique.

01 — Le rôle du contact initial
Le contact initial est le premier échange radio entre le JTAC et la patrouille aérienne qui arrive sur zone. Il sert à prendre en charge l’appareil, à lui signaler les dangers et à connaître ses moyens. Aucune cible n’est encore désignée : tant que ce contact n’est pas terminé, aucune attaque ne se prépare.

02 — Prise en charge de la patrouille
Le JTAC répond à l’appel de la patrouille avec son propre indicatif et annonce qu’il la prend en charge. À partir de ce moment, la patrouille travaille pour lui sur cette fréquence. Exemple : « Voodoo 1, ici Bastion, je vous prends en compte. » Les deux indicatifs sont toujours prononcés pour éviter qu’un autre joueur réponde à la place du bon appareil.

03 — Menaces immédiates
Le JTAC annonce ensuite ce qui peut mettre l’appareil en danger : défense sol-air observée ou signalée, tirs vers le ciel, zone à éviter. Une menace annoncée tôt laisse au pilote le temps d’adapter son altitude et son approche. Si aucune menace n’est connue, le JTAC le dit aussi, en précisant que c’est ce qu’il sait à cet instant.

04 — Trafic aérien
Le JTAC signale les autres appareils présents ou attendus dans le secteur : hélicoptères, drones, autre patrouille. Sur un serveur, plusieurs pilotes partagent le même ciel ; cette information évite les collisions et les confusions d’indicatifs.

05 — Check-in de la patrouille
La patrouille présente ses moyens :
- Armement : types et quantités de munitions emportées. Si l’armement est guidé laser, le code laser est donné et confirmé : la munition ne suit que le spot émis sur son propre code.
- Senseurs : ce que l’appareil peut voir ou marquer, notamment ses pods de désignation et de visée.
- Rover : retour de la vidéo de l’appareil vers le JTAC, lorsque la mission le permet.
Le JTAC note ces informations : elles conditionnent le type de contrôle, la méthode d’attaque et la munition qu’il choisira dans le game plan.

06 — Le code abort
Le JTAC communique un code abort : un mot convenu qui permet d’annuler l’attaque à tout moment. Lorsqu’il est prononcé, la patrouille cesse immédiatement l’action. Le pilote le répète pour montrer qu’il l’a noté. Choisissez un mot simple et distinct du vocabulaire radio habituel. Le code abort est rappelé dans les remarques du 9-line et chaque mission a son propre code.

07 — Ce qu’il faut retenir
Ordre de la phase : prise en charge, menaces immédiates, trafic aérien, check-in (armement et code laser, senseurs, pods, rover), code abort. À la fin du contact initial, le JTAC sait ce que la patrouille emporte et comment arrêter une attaque. Il passe alors à la situation update.
FR;
        $theoryEn = <<<'EN'
TRAINING OBJECTIVE
This course trains the JTAC role in our Arma 3 missions: the player on the ground who coordinates close air support (CAS) flown by other players. It follows the community procedure in five phases: initial contact, situation update, game plan, CAS brief (9-line), then terminal attack and BDA. A final chapter gathers the brevity words. NATO terms stay in English, as on the in-game radio. This is in-game training: the ATH-ACADEMY licence records an internal pathway and has no real-world military or aviation qualification value.

01 — The purpose of initial contact
Initial contact is the first radio exchange between the JTAC and the aircraft flight arriving in the area. It is used to take control of the aircraft, warn it of dangers and learn what it carries. No target is designated yet: until this contact is complete, no attack is prepared.

02 — Taking control of the flight
The JTAC answers the flight’s call with their own callsign and states that they are taking control. From that moment, the flight works for the JTAC on this frequency. Example: “Voodoo 1, this is Bastion, I have you.” Both callsigns are always spoken so that another player does not answer instead of the right aircraft.

03 — Immediate threats
The JTAC then announces anything that could endanger the aircraft: observed or reported air defence, fire towards the sky, areas to avoid. A threat announced early gives the pilot time to adjust altitude and approach. If no threat is known, the JTAC says so too, making clear that this is what they know at this moment.

04 — Air traffic
The JTAC reports other aircraft present or expected in the area: helicopters, drones, another flight. On a server, several pilots share the same sky; this information prevents collisions and callsign confusion.

05 — The flight’s check-in
The flight presents its assets:
- Weapons: types and quantities carried. If the weapons are laser-guided, the laser code is given and confirmed: the weapon only follows a spot emitted on its own code.
- Sensors: what the aircraft can see or mark, in particular its targeting pods.
- Rover: the aircraft’s video feed sent to the JTAC, when the mission allows it.
The JTAC notes this information: it determines the type of control, attack method and weapon they will choose in the game plan.

06 — The abort code
The JTAC passes an abort code: an agreed word that cancels the attack at any time. When it is spoken, the flight immediately stops the action. The pilot reads it back to show it has been noted. Choose a simple word that is distinct from usual radio vocabulary. The abort code is repeated in the 9-line remarks and each mission has its own code.

07 — Key points
Order of the phase: taking control, immediate threats, air traffic, check-in (weapons and laser code, sensors, pods, rover), abort code. At the end of initial contact, the JTAC knows what the flight carries and how to stop an attack. They then move on to the situation update.
EN;
        $demoFr = <<<'FR'
DÉMONSTRATION COMMENTÉE — CHECK-IN DE VOODOO 1
Situation de jeu : une section progresse vers un village tenu par l’ennemi. Le JTAC « Bastion » attend une patrouille de deux avions, indicatif « Voodoo 1 ».

Voodoo 1 : « Bastion, Voodoo 1, en approche de votre secteur, prêt pour check-in. »
Bastion : « Voodoo 1, Bastion, je vous prends en compte. »
Commentaire : prise en charge explicite, avec les deux indicatifs.

Bastion : « Menaces : armes légères et une mitrailleuse lourde signalées au nord du village. Pas de défense sol-air observée à cette heure. »
Commentaire : les menaces viennent en premier. L’absence de défense sol-air est donnée comme une observation du moment, pas comme une certitude.

Bastion : « Trafic : un hélicoptère de transport en attente au sud, à basse altitude. »
Commentaire : le pilote sait qu’il n’est pas seul dans le ciel.

Voodoo 1 : « Voodoo 1, deux appareils. Quatre GBU-12 guidées laser, code laser 1688. Deux GBU-38. Canon. Pod de désignation disponible, rover disponible. »
Commentaire : armement complet ; le code laser est donné parce que des munitions sont guidées laser ; senseurs et rover sont annoncés.

Bastion : « Reçu, code laser 1688. Code abort : CITRON. »
Voodoo 1 : « Code abort CITRON, Voodoo 1. »
Commentaire : le JTAC reprend le code laser pour régler son désignateur sur le même code, puis donne le code abort, que le pilote répète.

À RETENIR
Bastion n’a encore parlé d’aucune cible. Il connaît les menaces signalées, le trafic, les moyens de la patrouille et le mot qui arrête toute attaque. Il peut passer à la situation update.
FR;
        $demoEn = <<<'EN'
ANNOTATED DEMONSTRATION — VOODOO 1 CHECK-IN
Game situation: a section is advancing towards a village held by the enemy. The JTAC “Bastion” is expecting a flight of two aircraft, callsign “Voodoo 1”.

Voodoo 1: “Bastion, Voodoo 1, approaching your area, ready for check-in.”
Bastion: “Voodoo 1, Bastion, I have you.”
Comment: explicit control, with both callsigns.

Bastion: “Threats: small arms and one heavy machine gun reported north of the village. No air defence observed at this time.”
Comment: threats come first. The absence of air defence is given as a current observation, not as a certainty.

Bastion: “Traffic: one transport helicopter holding to the south, low altitude.”
Comment: the pilot knows they are not alone in the sky.

Voodoo 1: “Voodoo 1, two aircraft. Four laser-guided GBU-12, laser code 1688. Two GBU-38. Gun. Targeting pod available, rover available.”
Comment: full weapons list; the laser code is given because some weapons are laser-guided; sensors and rover are announced.

Bastion: “Copy, laser code 1688. Abort code: LEMON.”
Voodoo 1: “Abort code LEMON, Voodoo 1.”
Comment: the JTAC repeats the laser code to set their designator to the same code, then gives the abort code, which the pilot reads back.

KEY POINT
Bastion has not mentioned any target yet. They know the reported threats, the traffic, the flight’s assets and the word that stops any attack. They can move on to the situation update.
EN;
        $guided = self::question(
            'Voodoo 2 annonce seulement : « Voodoo 2, deux GBU-12, prêt. » Que manque-t-il avant de quitter le contact initial ?',
            'Voodoo 2 only says: “Voodoo 2, two GBU-12, ready.” What is missing before leaving initial contact?',
            [
                self::text('Le code laser des GBU-12, les senseurs disponibles (pod, rover) et le code abort.', 'The GBU-12 laser code, the available sensors (pod, rover) and the abort code.'),
                self::text('Rien : l’armement est connu, la cible peut être annoncée tout de suite.', 'Nothing: the weapons are known, so the target can be announced straight away.'),
                self::text('La description de la cible et l’axe d’attaque.', 'The target description and the attack heading.'),
            ],
            0,
            'Une munition guidée laser appelle toujours un code laser, les senseurs orientent la suite et aucune attaque ne se prépare sans code abort. La cible et l’axe d’attaque viennent plus tard, dans le game plan et le 9-line.',
            'A laser-guided weapon always requires a laser code, sensors shape what follows and no attack is prepared without an abort code. The target and attack heading come later, in the game plan and the 9-line.'
        ) + [
            'brief' => self::text(
                "CAS GUIDÉ — VOODOO 2\nVous êtes Bastion. Une deuxième patrouille, Voodoo 2, rejoint le secteur. Vous l’avez prise en compte, annoncé les menaces (mitrailleuse au nord du village) et le trafic (hélicoptère au sud). Voodoo 2 fait un check-in très court.\n\nTravaillez dans cet ordre :\n1. Relisez ce qu’un check-in doit contenir : armement, code laser si armement guidé laser, senseurs, pods, rover.\n2. Comparez avec ce que Voodoo 2 a dit.\n3. Vérifiez si un code abort a été donné.\n4. Choisissez la réponse qui liste tout ce qui manque.",
                "GUIDED CASE — VOODOO 2\nYou are Bastion. A second flight, Voodoo 2, joins the area. You have taken control, announced threats (machine gun north of the village) and traffic (helicopter to the south). Voodoo 2 gives a very short check-in.\n\nWork in this order:\n1. Recall what a check-in must contain: weapons, laser code if weapons are laser-guided, sensors, pods, rover.\n2. Compare with what Voodoo 2 said.\n3. Check whether an abort code has been given.\n4. Choose the answer that lists everything missing."
            ),
            'hint' => self::text(
                'Relisez les sections 05 et 06 du cours : la GBU-12 est guidée laser, et le code abort clôt le contact initial.',
                'Reread sections 05 and 06 of the course: the GBU-12 is laser-guided, and the abort code closes initial contact.'
            ),
        ];
        $simulation = self::question(
            'Quel premier message envoyez-vous à Hawk 1 ?',
            'Which first message do you send to Hawk 1?',
            [
                self::text('« Hawk 1, Bastion. Cible : le camion au carrefour, Clear hot. »', '“Hawk 1, Bastion. Target: the truck at the crossroads, clear hot.”'),
                self::text('« Hawk 1, Bastion, je vous prends en compte. Menaces : tir sol-air signalé à l’est, non confirmé. Trafic : drone allié au-dessus du secteur. Annoncez votre check-in. »', '“Hawk 1, Bastion, I have you. Threats: air-defence fire reported to the east, unconfirmed. Traffic: friendly drone over the area. Send your check-in.”'),
                self::text('« Hawk 1, Bastion, code abort ORAGE. Nous verrons les menaces après l’attaque. »', '“Hawk 1, Bastion, abort code STORM. We will cover threats after the attack.”'),
            ],
            1,
            'Le contact initial suit un ordre : prise en charge, menaces immédiates (y compris non confirmées, signalées comme telles), trafic aérien, puis check-in et code abort. Désigner une cible ou autoriser un tir à ce stade saute toutes les phases ; reporter les menaces met l’appareil en danger.',
            'Initial contact follows an order: taking control, immediate threats (including unconfirmed ones, stated as such), air traffic, then check-in and abort code. Designating a target or clearing fire at this point skips every phase; postponing threats puts the aircraft at risk.'
        ) + [
            'brief' => self::text(
                "SIMULATION AUTONOME — HAWK 1\nVous êtes Bastion. Un avion isolé, Hawk 1, appelle sur votre fréquence. Un joueur de votre groupe a signalé un tir sol-air possible à l’est, sans confirmation. Un drone allié tourne au-dessus du secteur. Vous avez déjà repéré un camion ennemi au carrefour et votre chef de groupe vous presse de le faire traiter.\n\nVotre objectif : ouvrir correctement le contact initial malgré la pression. Aucune aide n’est donnée pendant cette étape ; vous pourrez revoir le cours après une tentative.",
                "INDEPENDENT SIMULATION — HAWK 1\nYou are Bastion. A single aircraft, Hawk 1, calls on your frequency. A player in your group has reported possible air-defence fire to the east, unconfirmed. A friendly drone is circling over the area. You have already spotted an enemy truck at the crossroads and your squad leader is pressing you to have it engaged.\n\nYour objective: open initial contact correctly despite the pressure. No hint is given during this stage; you may review the course after an attempt."
            ),
        ];
        $questions = [
            self::question(
                'Dans quel ordre se déroule le contact initial ?',
                'In which order does initial contact take place?',
                [
                    self::text('Check-in, désignation de la cible, menaces.', 'Check-in, target designation, threats.'),
                    self::text('Prise en charge, menaces immédiates, trafic aérien, check-in, code abort.', 'Taking control, immediate threats, air traffic, check-in, abort code.'),
                    self::text('Code abort, 9-line, prise en charge.', 'Abort code, 9-line, taking control.'),
                ],
                1,
                'La procédure commence par la prise en charge, puis la sécurité de l’appareil (menaces, trafic), ensuite ses moyens (check-in) et enfin le code abort.',
                'The procedure starts with taking control, then the aircraft’s safety (threats, traffic), then its assets (check-in) and finally the abort code.'
            ),
            self::question(
                'Pourquoi le code laser est-il donné au check-in lorsque l’armement est guidé laser ?',
                'Why is the laser code given at check-in when the weapons are laser-guided?',
                [
                    self::text('Parce que le désignateur et la munition doivent utiliser le même code.', 'Because the designator and the weapon must use the same code.'),
                    self::text('Parce qu’il remplace l’indicatif de la patrouille.', 'Because it replaces the flight’s callsign.'),
                    self::text('Parce qu’il sert aussi de code abort.', 'Because it also serves as the abort code.'),
                ],
                0,
                'Une munition guidée laser ne suit que le spot émis sur son code. Le JTAC règle son désignateur sur le code annoncé.',
                'A laser-guided weapon only follows a spot emitted on its code. The JTAC sets their designator to the announced code.'
            ),
            self::question(
                'À quoi sert le code abort ?',
                'What is the abort code for?',
                [
                    self::text('À autoriser le largage de l’armement.', 'To clear weapons release.'),
                    self::text('À déclarer la cible détruite.', 'To declare the target destroyed.'),
                    self::text('À annuler l’attaque à tout moment : la patrouille cesse immédiatement l’action.', 'To cancel the attack at any time: the flight immediately stops the action.'),
                ],
                2,
                'Le code abort est le mot convenu qui arrête l’attaque. Le largage s’autorise par « Clear hot » et l’état de la cible se constate au BDA.',
                'The abort code is the agreed word that stops the attack. Weapons release is cleared with “Clear hot” and the target state is assessed at BDA.'
            ),
            self::question(
                'Quand le JTAC annonce-t-il les menaces immédiates ?',
                'When does the JTAC announce immediate threats?',
                [
                    self::text('Dès la prise en charge, avant le check-in de la patrouille.', 'Right after taking control, before the flight’s check-in.'),
                    self::text('Après le BDA, pour le compte rendu.', 'After BDA, for the report.'),
                    self::text('Seulement si le pilote le demande.', 'Only if the pilot asks.'),
                ],
                0,
                'Les menaces passent en premier : le pilote doit pouvoir adapter son vol avant tout le reste.',
                'Threats come first: the pilot must be able to adjust their flight before anything else.'
            ),
            self::question(
                'Au check-in, que désigne le mot « rover » ?',
                'At check-in, what does the word “rover” refer to?',
                [
                    self::text('Un véhicule terrestre allié.', 'A friendly ground vehicle.'),
                    self::text('Le retour de la vidéo de l’appareil vers le JTAC, lorsque la mission le permet.', 'The aircraft’s video feed sent to the JTAC, when the mission allows it.'),
                    self::text('Le second avion de la patrouille.', 'The second aircraft of the flight.'),
                ],
                1,
                'Avec les pods et les autres senseurs, le rover fait partie des moyens annoncés au check-in.',
                'Along with pods and other sensors, the rover is one of the assets announced at check-in.'
            ),
            self::question(
                'Pendant le contact initial, un hélicoptère allié entre dans le secteur. Que faites-vous ?',
                'During initial contact, a friendly helicopter enters the area. What do you do?',
                [
                    self::text('Rien : il n’est pas sous votre contrôle.', 'Nothing: it is not under your control.'),
                    self::text('Vous attendez le BDA pour en parler.', 'You wait for BDA to mention it.'),
                    self::text('Vous le signalez à la patrouille comme trafic aérien.', 'You report it to the flight as air traffic.'),
                ],
                2,
                'Le trafic aérien fait partie du contact initial : il évite les collisions et les confusions d’indicatifs entre joueurs.',
                'Air traffic is part of initial contact: it prevents collisions and callsign confusion between players.'
            ),
        ];

        return self::chapter(
            self::text('Phase 1 — Contact initial (check-in)', 'Phase 1 — Initial contact (check-in)'),
            self::text($theoryFr, $theoryEn),
            self::text($demoFr, $demoEn),
            $guided,
            $simulation,
            $questions,
            self::text(
                "Rédigez un retour personnel sur le cas Hawk 1 et votre évaluation :\n1. L’ordre que vous avez suivi et pourquoi.\n2. L’information de check-in que vous risquiez d’oublier.\n3. Comment vous avez traité une menace non confirmée.\n4. La pression du chef de groupe : comment y répondre sans sauter une étape.\n5. Une amélioration concrète à tester lors de votre prochaine mission comme JTAC.",
                "Write a personal reflection on the Hawk 1 case and your assessment:\n1. The order you followed and why.\n2. The check-in item you were most likely to forget.\n3. How you handled an unconfirmed threat.\n4. Pressure from the squad leader: how to respond without skipping a step.\n5. One concrete improvement to test in your next mission as JTAC."
            ),
            [
                self::text('Ordre de la phase restitué : prise en charge, menaces, trafic, check-in, code abort.', 'Order of the phase restated: taking control, threats, traffic, check-in, abort code.'),
                self::text('Code laser et code abort expliqués.', 'Laser code and abort code explained.'),
                self::text('Menace non confirmée annoncée comme telle.', 'Unconfirmed threat announced as such.'),
                self::text('Amélioration concrète et vérifiable en mission.', 'Concrete improvement that can be checked in a mission.'),
            ],
            [
                self::text('L’évaluation atteint au moins 80 % dans le délai prévu.', 'The assessment reaches at least 80% within the time limit.'),
                self::text('Le stagiaire énonce un contact initial complet dans le bon ordre, à l’écrit ou à la radio.', 'The learner delivers a complete initial contact in the right order, in writing or on the radio.'),
                self::text('Le code laser et le code abort sont donnés et collationnés.', 'The laser code and abort code are given and read back.'),
                self::text('Aucune cible n’est désignée avant la fin du contact initial.', 'No target is designated before initial contact is complete.'),
            ]
        );
    }

    // ------------------------------------------------------------------ Phase 2 — Situation update

    private static function situationUpdate(): array
    {
        $theoryFr = <<<'FR'
01 — Le rôle de la situation update
Après le contact initial, le JTAC partage avec la patrouille l’image de la situation au sol. La procédure de la communauté nomme cette phase sans en fixer le format : retenez son but, donner au pilote une vision commune avant le game plan. Le contenu ci-dessous est un guide de jeu ; l’instructeur peut le préciser en session.

02 — Ce que le JTAC transmet
- Les amis : où se trouvent, de façon générale, les éléments alliés. Leur position par rapport à la cible sera précisée à la ligne 8 du 9-line.
- L’ennemi : ce qui est observé ou signalé dans la zone, et ce qui a bougé.
- Les menaces : rappel ou mise à jour de celles annoncées au contact initial.
- Les changements depuis le check-in.

03 — Séparer les faits des suppositions
« Une mitrailleuse observée dans le bâtiment au toit rouge » est un fait. « Il y a sûrement d’autres ennemis » est une supposition. Le JTAC dit d’où vient une information (« signalé par le groupe Alpha », « non confirmé ») pour que le pilote sache quel crédit lui accorder.

04 — Rester court et visible du ciel
Le pilote retient ces informations en vol. Utilisez des phrases brèves et des repères visibles depuis l’avion : village, route, rivière, carrefour, bâtiment remarquable. Reprenez les mêmes noms que sur la carte de la mission.

05 — Vérifier la compréhension
Le pilote confirme ce qu’il a reçu. S’il ne voit pas un repère, il le dit ; le JTAC reformule avec un autre repère au lieu de répéter la même phrase.

06 — Mettre à jour en continu
La situation évolue pendant la mission. Toute nouvelle menace, tout déplacement des amis près de la zone d’attaque est annoncé immédiatement, même au milieu d’une autre phase.

07 — Ce qu’il faut retenir
Amis en général, ennemi, menaces, changements ; faits séparés des suppositions ; phrases courtes et repères visibles ; confirmation du pilote. Le JTAC passe ensuite au game plan.
FR;
        $theoryEn = <<<'EN'
01 — The purpose of the situation update
After initial contact, the JTAC shares the ground picture with the flight. The community procedure names this phase without fixing a format: remember its purpose, giving the pilot a shared picture before the game plan. The content below is in-game guidance; the instructor may refine it during a session.

02 — What the JTAC passes on
- Friendlies: where friendly elements are, in general terms. Their position relative to the target will be given in line 8 of the 9-line.
- Enemy: what is observed or reported in the area, and what has moved.
- Threats: a reminder or update of those announced at initial contact.
- Changes since check-in.

03 — Separating facts from assumptions
“A machine gun observed in the red-roofed building” is a fact. “There are surely more enemies” is an assumption. The JTAC states where information comes from (“reported by Alpha team”, “unconfirmed”) so the pilot knows how much weight to give it.

04 — Short and visible from the air
The pilot has to remember this information while flying. Use short sentences and landmarks visible from the aircraft: village, road, river, crossroads, distinctive building. Use the same names as on the mission map.

05 — Checking understanding
The pilot confirms what they received. If they cannot see a landmark, they say so; the JTAC rephrases using another landmark instead of repeating the same sentence.

06 — Updating continuously
The situation changes during the mission. Any new threat, or any movement of friendlies near the attack area, is announced immediately, even in the middle of another phase.

07 — Key points
Friendlies in general, enemy, threats, changes; facts separated from assumptions; short sentences and visible landmarks; pilot confirmation. The JTAC then moves on to the game plan.
EN;
        $demoFr = <<<'FR'
DÉMONSTRATION COMMENTÉE — SITUATION UPDATE POUR VOODOO 1
Suite du check-in : Bastion a pris en compte Voodoo 1, donné les menaces, le trafic et le code abort CITRON.

Bastion : « Voodoo 1, situation update. Amis : ma section est en lisière du bois, au sud du village. Ennemis : une mitrailleuse lourde observée dans le bâtiment au toit rouge, à l’entrée nord du village ; infanterie en nombre inconnu autour. Menaces : sans changement, pas de sol-air observé. »
Commentaire : amis en général, ennemi observé, menaces mises à jour. Le nombre inconnu de fantassins est dit tel quel, sans l’inventer.

Voodoo 1 : « Voodoo 1, reçu : amis lisière sud, mitrailleuse toit rouge entrée nord. Je ne distingue pas de toit rouge. »
Commentaire : le pilote confirme et signale franchement ce qu’il ne voit pas.

Bastion : « Le bâtiment est le premier à gauche de la route qui entre dans le village par le nord. »
Voodoo 1 : « Vu, premier bâtiment à gauche de la route nord. »
Commentaire : Bastion change de repère au lieu de répéter « toit rouge ». La route est visible du ciel.

À RETENIR
La patrouille et le JTAC partagent maintenant la même image : où sont les amis, où est l’ennemi, ce qui menace l’avion. Le game plan peut commencer.
FR;
        $demoEn = <<<'EN'
ANNOTATED DEMONSTRATION — SITUATION UPDATE FOR VOODOO 1
Following the check-in: Bastion has taken control of Voodoo 1 and given threats, traffic and the abort code LEMON.

Bastion: “Voodoo 1, situation update. Friendlies: my section is at the edge of the wood, south of the village. Enemy: one heavy machine gun observed in the red-roofed building at the northern entrance of the village; infantry around it, number unknown. Threats: no change, no air defence observed.”
Comment: friendlies in general, observed enemy, updated threats. The unknown number of infantry is stated as such, without inventing a figure.

Voodoo 1: “Voodoo 1, copy: friendlies south edge, machine gun red roof north entrance. I cannot make out a red roof.”
Comment: the pilot confirms and clearly reports what they cannot see.

Bastion: “The building is the first one on the left of the road entering the village from the north.”
Voodoo 1: “Visual on it, first building left of the north road.”
Comment: Bastion changes landmark instead of repeating “red roof”. The road is visible from the air.

KEY POINT
The flight and the JTAC now share the same picture: where friendlies are, where the enemy is, what threatens the aircraft. The game plan can begin.
EN;
        $guided = self::question(
            'Quel message transmettez-vous à Voodoo 1 ?',
            'Which message do you send to Voodoo 1?',
            [
                self::text('Rien : la situation a déjà été donnée, inutile de surcharger la radio.', 'Nothing: the situation was already given, there is no need to overload the radio.'),
                self::text('« Voodoo 1, Clear hot sur le village. »', '“Voodoo 1, clear hot on the village.”'),
                self::text('« Voodoo 1, mise à jour : amis maintenant au carrefour sud du village, 300 mètres plus au nord qu’annoncé. Mitrailleuse toujours observée au premier bâtiment de la route nord. »', '“Voodoo 1, update: friendlies now at the southern crossroads of the village, 300 metres further north than announced. Machine gun still observed in the first building on the north road.”'),
            ],
            2,
            'Les amis se sont rapprochés de la zone d’attaque : c’est un changement à annoncer immédiatement, avec un repère visible du ciel. Se taire laisse au pilote une image fausse ; autoriser un tir saute le game plan et le 9-line.',
            'Friendlies have moved closer to the attack area: this is a change to announce immediately, with a landmark visible from the air. Staying silent leaves the pilot with a wrong picture; clearing fire skips the game plan and the 9-line.'
        ) + [
            'brief' => self::text(
                "CAS GUIDÉ — LA SECTION AVANCE\nVous avez donné votre situation update à Voodoo 1 : amis en lisière du bois au sud du village. Pendant que le pilote fait un tour d’attente, votre section avance de 300 mètres et s’arrête au carrefour sud du village. La mitrailleuse est toujours observée.\n\nTravaillez dans cet ordre :\n1. Qu’est-ce qui a changé depuis votre dernière annonce ?\n2. Ce changement concerne-t-il la sécurité des amis ?\n3. Quel repère le pilote peut-il voir ?\n4. Choisissez le message qui met à jour l’image commune.",
                "GUIDED CASE — THE SECTION ADVANCES\nYou have given your situation update to Voodoo 1: friendlies at the edge of the wood south of the village. While the pilot holds, your section advances 300 metres and stops at the southern crossroads of the village. The machine gun is still observed.\n\nWork in this order:\n1. What has changed since your last announcement?\n2. Does this change concern friendly safety?\n3. Which landmark can the pilot see?\n4. Choose the message that updates the shared picture."
            ),
            'hint' => self::text(
                'Relisez la section 06 : un déplacement des amis près de la zone d’attaque s’annonce tout de suite.',
                'Reread section 06: friendlies moving near the attack area must be announced straight away.'
            ),
        ];
        $simulation = self::question(
            'Quelle situation update transmettez-vous à Hawk 1 ?',
            'Which situation update do you send to Hawk 1?',
            [
                self::text('« Hawk 1, situation update. Amis : groupe Alpha sur la colline à l’ouest du pont. Ennemis : deux véhicules observés près du pont, côté est. Char signalé par un joueur au sud du pont, non confirmé. Menaces : sans changement. »', '“Hawk 1, situation update. Friendlies: Alpha team on the hill west of the bridge. Enemy: two vehicles observed near the bridge, east side. Tank reported by a player south of the bridge, unconfirmed. Threats: no change.”'),
                self::text('« Hawk 1, situation update. Un char et plusieurs véhicules ennemis près du pont, forces nombreuses. »', '“Hawk 1, situation update. One tank and several enemy vehicles near the bridge, large force.”'),
                self::text('« Hawk 1, rien de nouveau, game plan dans cinq minutes. »', '“Hawk 1, nothing new, game plan in five minutes.”'),
            ],
            0,
            'La bonne réponse donne les amis, l’ennemi observé et sépare clairement l’information non confirmée. Présenter le char comme certain et inventer des « forces nombreuses » trompe le pilote ; ne rien dire le laisse sans image de la situation.',
            'The correct answer gives friendlies, the observed enemy and clearly separates the unconfirmed report. Presenting the tank as certain and inventing a “large force” misleads the pilot; saying nothing leaves them without a picture.'
        ) + [
            'brief' => self::text(
                "SIMULATION AUTONOME — LE PONT\nVous êtes Bastion et vous avez pris en compte Hawk 1. Votre groupe Alpha tient la colline à l’ouest d’un pont. Vous observez vous-même deux véhicules ennemis près du pont, côté est. Un joueur affirme sur la radio de groupe qu’« il y a sûrement un char » au sud du pont, mais personne ne l’a vu. Les menaces annoncées au contact initial n’ont pas changé.\n\nVotre objectif : donner une situation update utile et honnête. Aucune aide n’est donnée pendant cette étape.",
                "INDEPENDENT SIMULATION — THE BRIDGE\nYou are Bastion and you have taken control of Hawk 1. Your Alpha team holds the hill west of a bridge. You personally observe two enemy vehicles near the bridge, east side. A player claims on the team radio that “there is surely a tank” south of the bridge, but nobody has seen it. The threats announced at initial contact have not changed.\n\nYour objective: give a useful and honest situation update. No hint is given during this stage."
            ),
        ];
        $questions = [
            self::question(
                'Quel est le but de la situation update ?',
                'What is the purpose of the situation update?',
                [
                    self::text('Donner au pilote une image commune de la situation au sol avant le game plan.', 'Give the pilot a shared picture of the ground situation before the game plan.'),
                    self::text('Autoriser le largage de l’armement.', 'Clear weapons release.'),
                    self::text('Libérer la patrouille.', 'Release the flight.'),
                ],
                0,
                'La situation update relie le contact initial au game plan : le pilote sait où sont les amis, l’ennemi et les menaces.',
                'The situation update links initial contact to the game plan: the pilot knows where friendlies, the enemy and threats are.'
            ),
            self::question(
                'Où la position des amis par rapport à la cible est-elle précisée ?',
                'Where is the position of friendlies relative to the target specified?',
                [
                    self::text('À la ligne 1 du 9-line.', 'In line 1 of the 9-line.'),
                    self::text('À la ligne 8 du 9-line (Friendlies).', 'In line 8 of the 9-line (Friendlies).'),
                    self::text('Au BDA uniquement.', 'At BDA only.'),
                ],
                1,
                'La situation update donne la position générale des amis ; la ligne 8 du 9-line donne leur distance et leur direction par rapport à la cible.',
                'The situation update gives the general position of friendlies; line 8 of the 9-line gives their distance and direction from the target.'
            ),
            self::question(
                'Le pilote dit ne pas voir le repère que vous avez donné. Que faites-vous ?',
                'The pilot says they cannot see the landmark you gave. What do you do?',
                [
                    self::text('Vous répétez exactement la même phrase.', 'You repeat exactly the same sentence.'),
                    self::text('Vous passez au 9-line sans repère.', 'You move on to the 9-line without a landmark.'),
                    self::text('Vous reformulez avec un autre repère visible du ciel.', 'You rephrase using another landmark visible from the air.'),
                ],
                2,
                'Répéter un repère invisible ne règle rien. Un autre repère (route, carrefour, rivière) permet au pilote de se situer.',
                'Repeating an invisible landmark solves nothing. Another landmark (road, crossroads, river) lets the pilot find their bearings.'
            ),
            self::question(
                'Un joueur pense qu’un char est caché derrière la ferme, sans l’avoir vu. Comment le transmettez-vous ?',
                'A player thinks a tank is hidden behind the farm, without having seen it. How do you pass it on?',
                [
                    self::text('Comme un fait : « char derrière la ferme ».', 'As a fact: “tank behind the farm”.'),
                    self::text('Comme une information non confirmée, en citant sa source.', 'As unconfirmed information, citing its source.'),
                    self::text('Vous ne le transmettez jamais.', 'You never pass it on.'),
                ],
                1,
                'L’information peut être utile, mais le pilote doit savoir qu’elle n’est pas confirmée pour lui accorder le bon crédit.',
                'The information may be useful, but the pilot must know it is unconfirmed to give it the right weight.'
            ),
            self::question(
                'Une nouvelle menace sol-air apparaît pendant que vous préparez le 9-line. Que faites-vous ?',
                'A new air-defence threat appears while you are preparing the 9-line. What do you do?',
                [
                    self::text('Vous l’annoncez immédiatement à la patrouille.', 'You announce it to the flight immediately.'),
                    self::text('Vous attendez la fin de l’attaque.', 'You wait until the attack is over.'),
                    self::text('Vous la notez pour le débriefing.', 'You note it for the debrief.'),
                ],
                0,
                'La situation se met à jour en continu : une menace pour l’avion s’annonce tout de suite, quelle que soit la phase en cours.',
                'The situation is updated continuously: a threat to the aircraft is announced straight away, whatever the current phase.'
            ),
            self::question(
                'Pourquoi utiliser des repères visibles depuis l’avion ?',
                'Why use landmarks visible from the aircraft?',
                [
                    self::text('Parce qu’ils raccourcissent le 9-line.', 'Because they shorten the 9-line.'),
                    self::text('Parce que le pilote doit retrouver en vol ce que le JTAC décrit au sol.', 'Because the pilot must find in flight what the JTAC describes on the ground.'),
                    self::text('Parce qu’ils remplacent le code abort.', 'Because they replace the abort code.'),
                ],
                1,
                'Le pilote ne voit pas ce que voit le JTAC depuis le sol : des repères visibles du ciel rendent la description utilisable.',
                'The pilot does not see what the JTAC sees from the ground: landmarks visible from the air make the description usable.'
            ),
        ];

        return self::chapter(
            self::text('Phase 2 — Situation update', 'Phase 2 — Situation update'),
            self::text($theoryFr, $theoryEn),
            self::text($demoFr, $demoEn),
            $guided,
            $simulation,
            $questions,
            self::text(
                "Rédigez un retour personnel sur le cas du pont et votre évaluation :\n1. Les éléments que vous avez transmis (amis, ennemi, menaces, changements).\n2. Comment vous avez séparé le fait observé de l’information non confirmée.\n3. Les repères que vous avez choisis et pourquoi le pilote peut les voir.\n4. Une autre réponse possible et son défaut.\n5. Une amélioration concrète pour votre prochaine mission.",
                "Write a personal reflection on the bridge case and your assessment:\n1. The items you passed on (friendlies, enemy, threats, changes).\n2. How you separated the observed fact from the unconfirmed report.\n3. The landmarks you chose and why the pilot can see them.\n4. Another possible answer and its weakness.\n5. One concrete improvement for your next mission."
            ),
            [
                self::text('Amis, ennemi, menaces et changements mentionnés.', 'Friendlies, enemy, threats and changes mentioned.'),
                self::text('Faits et informations non confirmées distingués.', 'Facts and unconfirmed information distinguished.'),
                self::text('Repères visibles du ciel justifiés.', 'Landmarks visible from the air justified.'),
                self::text('Amélioration concrète et vérifiable en mission.', 'Concrete improvement that can be checked in a mission.'),
            ],
            [
                self::text('L’évaluation atteint au moins 80 % dans le délai prévu.', 'The assessment reaches at least 80% within the time limit.'),
                self::text('Le stagiaire donne une situation update courte, avec amis, ennemi et menaces.', 'The learner gives a short situation update with friendlies, enemy and threats.'),
                self::text('Une information non confirmée n’est jamais présentée comme un fait.', 'Unconfirmed information is never presented as a fact.'),
                self::text('Un changement près de la zone d’attaque est annoncé sans attendre.', 'A change near the attack area is announced without delay.'),
            ]
        );
    }

    // ------------------------------------------------------------------ Phase 3 — Game plan

    private static function gamePlan(): array
    {
        $theoryFr = <<<'FR'
01 — Le rôle du game plan
Le game plan annonce à la patrouille comment l’attaque sera contrôlée et conduite, avant le 9-line. Le type de contrôle y est obligatoire. Le game plan se termine par la demande « retour radio si prêt pour 9-Line ».

02 — Type 1
Le JTAC a le visuel sur la cible ET sur l’attaquant, c’est-à-dire l’avion allié. Une seule attaque.

03 — Type 2
Le JTAC a le visuel sur la cible OU sur l’attaquant. Une seule attaque. Une bombe guidée par coordonnées de type JDAM, comme la GBU-38, impose forcément un type 2.

04 — Type 3
Le JTAC a le visuel sur la cible ; attaque multiple. Ce type sert pour des cibles multiples : l’avion peut réaliser d’initiative le nombre de passes souhaité pour détruire ou neutraliser la ou les cibles. Le JTAC l’autorise par « Cleared to engage » et la patrouille reprend contact à l’issue.

05 — Une seule attaque en types 1 et 2
En types 1 et 2, chaque autorisation vaut pour une seule attaque. Relancer une attaque est à l’initiative du JTAC : le pilote ne repasse pas de lui-même.

06 — Méthode d’acquisition
- Bomb on target : la cible est acquise par un marquage ou un senseur — MI, laser, strobe IR…
- Bomb on coordinate : l’attaque se fait uniquement sur des coordonnées.

07 — Préciser la munition
Le JTAC précise toujours le type de munition. En bomb on coordinate, il donne aussi le nombre de bombes, impact unique ou impact simultané si plusieurs appareils attaquent, et le format de la coordonnée (6 ou 8 chiffres).

08 — Exemples de phrases radio
« Voodoo 1, Type 1, bomb on target, retour radio si prêt pour 9-Line. »
« Voodoo 1, Type 2, bomb on coordinate, une GBU-38, impact unique, coordonnée 8 chiffres, retour radio si prêt pour 9-Line. »
« Voodoo 1, Type 3, bomb on target, besoin que tout véhicule et toute infanterie soient détruits, retour radio si prêt pour 9-Line. »

09 — Ce qu’il faut retenir
Type de contrôle (obligatoire), méthode (bomb on target ou bomb on coordinate), munition, et pour une attaque sur coordonnées : nombre, impact unique ou simultané, coordonnée 6 ou 8 chiffres. JDAM = type 2. Types 1 et 2 = une attaque ; type 3 = plusieurs passes jusqu’à ce que tout soit traité.
FR;
        $theoryEn = <<<'EN'
01 — The purpose of the game plan
The game plan tells the flight how the attack will be controlled and conducted, before the 9-line. The type of control is mandatory. The game plan ends with the request “call when ready for 9-Line”.

02 — Type 1
The JTAC has eyes on the target AND on the attacker, meaning the friendly aircraft. A single attack.

03 — Type 2
The JTAC has eyes on the target OR on the attacker. A single attack. A coordinate-guided JDAM-type bomb, such as the GBU-38, always means Type 2.

04 — Type 3
The JTAC has eyes on the target; multiple attacks. This type is used for multiple targets: the aircraft may, on its own initiative, make as many passes as it needs to destroy or neutralise the target or targets. The JTAC authorises this with “Cleared to engage” and the flight checks back in when finished.

05 — A single attack in Types 1 and 2
In Types 1 and 2, each clearance is valid for one attack only. Launching another attack is the JTAC’s decision: the pilot does not make another pass on their own.

06 — Acquisition method
- Bomb on target: the target is acquired with a mark or a sensor — MI, laser, IR strobe…
- Bomb on coordinate: the attack is made on coordinates only.

07 — Specifying the weapon
The JTAC always specifies the type of weapon. For bomb on coordinate, they also give the number of bombs, single impact or simultaneous impact if several aircraft attack, and the coordinate format (6 or 8 digits).

08 — Sample radio calls
“Voodoo 1, Type 1, bomb on target, call when ready for 9-Line.”
“Voodoo 1, Type 2, bomb on coordinate, one GBU-38, single impact, 8-digit coordinate, call when ready for 9-Line.”
“Voodoo 1, Type 3, bomb on target, need all vehicles and all infantry destroyed, call when ready for 9-Line.”

09 — Key points
Type of control (mandatory), method (bomb on target or bomb on coordinate), weapon, and for a coordinate attack: number, single or simultaneous impact, 6- or 8-digit coordinate. JDAM = Type 2. Types 1 and 2 = one attack; Type 3 = several passes until everything is dealt with.
EN;
        $demoFr = <<<'FR'
DÉMONSTRATION COMMENTÉE — GAME PLAN POUR VOODOO 1
Suite de la situation update : la mitrailleuse est dans le premier bâtiment à gauche de la route nord. Bastion connaît sa coordonnée à 8 chiffres. L’avion vole haut et Bastion ne le voit pas depuis sa position.

Bastion : « Voodoo 1, game plan. Type 2, bomb on coordinate, une GBU-38, impact unique, coordonnée 8 chiffres. Retour radio si prêt pour 9-Line. »
Commentaire : la GBU-38 est une JDAM guidée par coordonnées : le type 2 est imposé. Bastion voit la cible mais pas l’avion, ce qui correspond aussi au type 2. Munition, nombre, impact et format de coordonnée sont précisés.

Voodoo 1 : « Voodoo 1, Type 2, bomb on coordinate, une GBU-38, impact unique. Prêt pour 9-Line. »
Commentaire : le pilote reprend le game plan et se déclare prêt. Une seule attaque est prévue ; une seconde passe dépendra de Bastion.

VARIANTES
Si Bastion voyait la cible et l’avion et désignait au laser pour une GBU-12 : « Voodoo 1, Type 1, bomb on target, retour radio si prêt pour 9-Line. »
Si plusieurs véhicules et de l’infanterie devaient être traités en plusieurs passes : « Voodoo 1, Type 3, bomb on target, besoin que tout véhicule et toute infanterie soient détruits, retour radio si prêt pour 9-Line. » L’autorisation sera alors « Cleared to engage ».

À RETENIR
Le type de contrôle découle de ce que voit le JTAC et de la munition choisie. Le game plan annonce le cadre ; le 9-line donnera le détail.
FR;
        $demoEn = <<<'EN'
ANNOTATED DEMONSTRATION — GAME PLAN FOR VOODOO 1
Following the situation update: the machine gun is in the first building on the left of the north road. Bastion knows its 8-digit coordinate. The aircraft is flying high and Bastion cannot see it from his position.

Bastion: “Voodoo 1, game plan. Type 2, bomb on coordinate, one GBU-38, single impact, 8-digit coordinate. Call when ready for 9-Line.”
Comment: the GBU-38 is a coordinate-guided JDAM, so Type 2 is required. Bastion sees the target but not the aircraft, which also matches Type 2. Weapon, number, impact and coordinate format are specified.

Voodoo 1: “Voodoo 1, Type 2, bomb on coordinate, one GBU-38, single impact. Ready for 9-Line.”
Comment: the pilot reads back the game plan and reports ready. Only one attack is planned; a second pass will be Bastion’s decision.

VARIANTS
If Bastion could see the target and the aircraft and was lasing for a GBU-12: “Voodoo 1, Type 1, bomb on target, call when ready for 9-Line.”
If several vehicles and infantry had to be engaged over several passes: “Voodoo 1, Type 3, bomb on target, need all vehicles and all infantry destroyed, call when ready for 9-Line.” The clearance would then be “Cleared to engage”.

KEY POINT
The type of control follows from what the JTAC can see and from the chosen weapon. The game plan sets the frame; the 9-line will give the details.
EN;
        $guided = self::question(
            'Quel game plan annoncez-vous ?',
            'Which game plan do you announce?',
            [
                self::text('« Voodoo 1, Type 3, bomb on target, Cleared to engage. »', '“Voodoo 1, Type 3, bomb on target, cleared to engage.”'),
                self::text('« Voodoo 1, Type 1, bomb on target, retour radio si prêt pour 9-Line. »', '“Voodoo 1, Type 1, bomb on target, call when ready for 9-Line.”'),
                self::text('« Voodoo 1, Type 2, bomb on coordinate, une GBU-12, retour radio si prêt pour 9-Line. »', '“Voodoo 1, Type 2, bomb on coordinate, one GBU-12, call when ready for 9-Line.”'),
            ],
            1,
            'Bastion voit la cible ET l’avion, la cible est unique et sera marquée au laser : type 1, bomb on target. Le type 3 sert aux cibles multiples et « Cleared to engage » n’arrive qu’au moment de l’attaque ; une GBU-12 guidée laser ne correspond pas à une attaque sur coordonnées.',
            'Bastion sees the target AND the aircraft, the target is single and will be lased: Type 1, bomb on target. Type 3 is for multiple targets and “Cleared to engage” only comes at attack time; a laser-guided GBU-12 does not match a coordinate attack.'
        ) + [
            'brief' => self::text(
                "CAS GUIDÉ — LE BLINDÉ ISOLÉ\nUn véhicule blindé ennemi est arrêté seul près d’une ferme. Bastion le voit clairement. Voodoo 1 vole bas et Bastion le suit des yeux pendant toute son approche. Voodoo 1 emporte des GBU-12 guidées laser (code 1688) et Bastion dispose d’un désignateur laser.\n\nTravaillez dans cet ordre :\n1. Bastion voit-il la cible ? L’attaquant ? Les deux ?\n2. Y a-t-il une cible ou plusieurs ?\n3. Comment la cible sera-t-elle acquise : marquage laser ou coordonnées ?\n4. Choisissez le game plan correspondant.",
                "GUIDED CASE — THE ISOLATED ARMOURED VEHICLE\nAn enemy armoured vehicle is stopped alone near a farm. Bastion can see it clearly. Voodoo 1 is flying low and Bastion can watch it throughout its approach. Voodoo 1 carries laser-guided GBU-12s (code 1688) and Bastion has a laser designator.\n\nWork in this order:\n1. Can Bastion see the target? The attacker? Both?\n2. Is there one target or several?\n3. How will the target be acquired: laser mark or coordinates?\n4. Choose the matching game plan."
            ),
            'hint' => self::text(
                'Type 1 = visuel cible ET attaquant ; le marquage laser correspond à bomb on target.',
                'Type 1 = eyes on target AND attacker; laser marking means bomb on target.'
            ),
        ];
        $simulation = self::question(
            'Quel game plan annoncez-vous à Hawk 1 ?',
            'Which game plan do you announce to Hawk 1?',
            [
                self::text('« Hawk 1, Type 1, bomb on target, retour radio si prêt pour 9-Line. »', '“Hawk 1, Type 1, bomb on target, call when ready for 9-Line.”'),
                self::text('« Hawk 1, Type 2, bomb on coordinate, une GBU-38, impact unique, coordonnée 6 chiffres, retour radio si prêt pour 9-Line. »', '“Hawk 1, Type 2, bomb on coordinate, one GBU-38, single impact, 6-digit coordinate, call when ready for 9-Line.”'),
                self::text('« Hawk 1, Type 3, bomb on target, besoin que tout véhicule et toute infanterie soient détruits, retour radio si prêt pour 9-Line. »', '“Hawk 1, Type 3, bomb on target, need all vehicles and all infantry destroyed, call when ready for 9-Line.”'),
            ],
            2,
            'Plusieurs cibles, à traiter en plusieurs passes, avec le visuel du JTAC sur les cibles : c’est le type 3. Le type 1 exige de voir l’avion et ne permet qu’une attaque ; une seule GBU-38 en type 2 ne traite pas un convoi entier.',
            'Several targets, to engage over several passes, with the JTAC’s eyes on the targets: this is Type 3. Type 1 requires seeing the aircraft and allows only one attack; a single GBU-38 in Type 2 does not deal with a whole convoy.'
        ) + [
            'brief' => self::text(
                "SIMULATION AUTONOME — LE CONVOI\nUn convoi ennemi de quatre véhicules s’est arrêté sur la route de la vallée ; des fantassins débarquent autour. Bastion voit tout le convoi depuis une crête. Hawk 1 vole très haut et Bastion ne le voit pas. Le chef de section veut que tout le convoi et l’infanterie soient neutralisés. Hawk 1 dispose d’un pod de désignation, de bombes guidées laser et de son canon.\n\nVotre objectif : choisir le type de contrôle, la méthode et formuler le game plan. Aucune aide n’est donnée pendant cette étape.",
                "INDEPENDENT SIMULATION — THE CONVOY\nAn enemy convoy of four vehicles has stopped on the valley road; infantry are dismounting around it. Bastion can see the whole convoy from a ridge. Hawk 1 is flying very high and Bastion cannot see it. The section leader wants the whole convoy and the infantry neutralised. Hawk 1 has a targeting pod, laser-guided bombs and its gun.\n\nYour objective: choose the type of control and the method, and word the game plan. No hint is given during this stage."
            ),
        ];
        $questions = [
            self::question(
                'Que signifie un contrôle de type 1 ?',
                'What does Type 1 control mean?',
                [
                    self::text('Visuel sur la cible ET sur l’attaquant ; une seule attaque.', 'Eyes on the target AND the attacker; a single attack.'),
                    self::text('Visuel sur la cible OU sur l’attaquant ; plusieurs attaques.', 'Eyes on the target OR the attacker; several attacks.'),
                    self::text('Aucun visuel ; attaque sur coordonnées.', 'No visual; coordinate attack.'),
                ],
                0,
                'Le type 1 est le plus exigeant : le JTAC voit à la fois la cible et l’avion allié. Il ne vaut que pour une attaque.',
                'Type 1 is the most demanding: the JTAC sees both the target and the friendly aircraft. It covers one attack only.'
            ),
            self::question(
                'Que signifie un contrôle de type 2 ?',
                'What does Type 2 control mean?',
                [
                    self::text('Visuel sur la cible uniquement ; attaques multiples.', 'Eyes on the target only; multiple attacks.'),
                    self::text('Visuel sur la cible OU sur l’attaquant ; une seule attaque.', 'Eyes on the target OR the attacker; a single attack.'),
                    self::text('Visuel sur la cible ET l’attaquant ; plusieurs passes libres.', 'Eyes on the target AND the attacker; free passes.'),
                ],
                1,
                'En type 2, le JTAC voit l’un des deux, la cible ou l’avion. Comme en type 1, l’autorisation vaut pour une seule attaque.',
                'In Type 2, the JTAC sees one of the two, the target or the aircraft. As in Type 1, the clearance covers one attack only.'
            ),
            self::question(
                'Que signifie un contrôle de type 3 ?',
                'What does Type 3 control mean?',
                [
                    self::text('Une seule attaque sur coordonnées.', 'A single coordinate attack.'),
                    self::text('Une attaque interdite de tir.', 'An attack with no weapons release.'),
                    self::text('Visuel sur la cible ; cibles multiples, l’avion fait d’initiative les passes nécessaires puis reprend contact.', 'Eyes on the target; multiple targets, the aircraft makes the passes it needs on its own initiative, then checks back in.'),
                ],
                2,
                'Le type 3 sert aux cibles multiples. Il est autorisé par « Cleared to engage » et la patrouille recontacte le JTAC à l’issue.',
                'Type 3 is for multiple targets. It is authorised with “Cleared to engage” and the flight contacts the JTAC again when finished.'
            ),
            self::question(
                'Une attaque avec une JDAM (par exemple une GBU-38) sur coordonnées est de quel type ?',
                'An attack with a JDAM (for example a GBU-38) on coordinates is which type?',
                [
                    self::text('Toujours de type 2.', 'Always Type 2.'),
                    self::text('Toujours de type 1.', 'Always Type 1.'),
                    self::text('Toujours de type 3.', 'Always Type 3.'),
                ],
                0,
                'Selon la procédure de la communauté, une JDAM impose forcément un type 2.',
                'Under the community procedure, a JDAM always means Type 2.'
            ),
            self::question(
                'Après une attaque de type 1, qui décide d’une nouvelle attaque ?',
                'After a Type 1 attack, who decides on another attack?',
                [
                    self::text('Le pilote, d’initiative.', 'The pilot, on their own initiative.'),
                    self::text('Le JTAC.', 'The JTAC.'),
                    self::text('Personne : une nouvelle attaque est interdite.', 'Nobody: another attack is forbidden.'),
                ],
                1,
                'En types 1 et 2, chaque autorisation vaut pour une attaque. Relancer est à l’initiative du JTAC.',
                'In Types 1 and 2, each clearance covers one attack. Launching another is the JTAC’s decision.'
            ),
            self::question(
                'Que signifie « bomb on coordinate » ?',
                'What does “bomb on coordinate” mean?',
                [
                    self::text('La cible est marquée au laser.', 'The target is lased.'),
                    self::text('La cible est marquée par un strobe IR.', 'The target is marked with an IR strobe.'),
                    self::text('L’attaque se fait uniquement sur des coordonnées.', 'The attack is made on coordinates only.'),
                ],
                2,
                'Le laser, le strobe IR ou le marquage MI relèvent de « bomb on target ». « Bomb on coordinate » n’utilise que des coordonnées.',
                'Laser, IR strobe or MI marking belong to “bomb on target”. “Bomb on coordinate” uses coordinates only.'
            ),
            self::question(
                'Par quelle demande se termine le game plan ?',
                'What request ends the game plan?',
                [
                    self::text('« Retour radio si prêt pour 9-Line. »', '“Call when ready for 9-Line.”'),
                    self::text('« Clear hot. »', '“Clear hot.”'),
                    self::text('« Winchester. »', '“Winchester.”'),
                ],
                0,
                'Le game plan prépare le 9-line : le pilote se déclare prêt à le recevoir. « Clear hot » et « Winchester » appartiennent à d’autres moments.',
                'The game plan prepares the 9-line: the pilot reports ready to receive it. “Clear hot” and “Winchester” belong to other moments.'
            ),
        ];

        return self::chapter(
            self::text('Phase 3 — Game plan', 'Phase 3 — Game plan'),
            self::text($theoryFr, $theoryEn),
            self::text($demoFr, $demoEn),
            $guided,
            $simulation,
            $questions,
            self::text(
                "Rédigez un retour personnel sur le cas du convoi et votre évaluation :\n1. Le type de contrôle choisi et ce que voyait le JTAC.\n2. La méthode d’acquisition retenue et pourquoi.\n3. Pourquoi les autres types ne convenaient pas.\n4. Une erreur ou une hésitation pendant l’évaluation et sa correction.\n5. Une phrase de game plan que vous vous entraînerez à dire à la radio.",
                "Write a personal reflection on the convoy case and your assessment:\n1. The type of control chosen and what the JTAC could see.\n2. The acquisition method chosen and why.\n3. Why the other types did not fit.\n4. A mistake or hesitation during the assessment and how you corrected it.\n5. A game plan call you will practise saying on the radio."
            ),
            [
                self::text('Type de contrôle justifié par ce que voit le JTAC.', 'Type of control justified by what the JTAC can see.'),
                self::text('Méthode (bomb on target ou on coordinate) et munition précisées.', 'Method (bomb on target or on coordinate) and weapon specified.'),
                self::text('Autres types examinés et écartés avec une raison.', 'Other types examined and ruled out with a reason.'),
                self::text('Phrase radio d’entraînement complète.', 'Complete radio call for practice.'),
            ],
            [
                self::text('L’évaluation atteint au moins 80 % dans le délai prévu.', 'The assessment reaches at least 80% within the time limit.'),
                self::text('Le stagiaire distingue sans hésiter les types 1, 2 et 3.', 'The learner distinguishes Types 1, 2 and 3 without hesitation.'),
                self::text('Il sait qu’une JDAM impose un type 2 et que seul le type 3 permet plusieurs passes d’initiative.', 'They know that a JDAM means Type 2 and that only Type 3 allows several passes on the aircraft’s initiative.'),
                self::text('Il formule un game plan complet qui se termine par « retour radio si prêt pour 9-Line ».', 'They deliver a complete game plan ending with “call when ready for 9-Line”.'),
            ]
        );
    }

    // ------------------------------------------------------------------ Phase 4 — CAS brief (9-line)

    private static function nineLine(): array
    {
        $theoryFr = <<<'FR'
01 — Le rôle du CAS brief
Le CAS brief, appelé 9-line, donne au pilote tout ce qu’il faut pour exécuter l’attaque annoncée dans le game plan. Les neuf lignes sont toujours données dans le même ordre ; le pilote sait ainsi ce qui arrive et peut noter chaque ligne à sa place.

02 — Ligne 1 : IP / BP
Marqueur, coordonnées ou point naturel visible servant de point d’entrée de l’axe d’attaque du support aérien. L’IP est choisie à au moins 4 km de la cible.

03 — Ligne 2 : Heading / Offset
Axe d’attaque depuis l’IP vers la cible, en degrés, avec un décalage Left ou Right par rapport à cet axe.

04 — Ligne 3 : Distance
Distance entre l’IP et la cible, selon la convention en vigueur dans l’unité de jeu (à rappeler au briefing de mission).

05 — Ligne 4 : Target Elevation
Altitude de la cible par rapport au niveau de la mer.

06 — Ligne 5 : Target Description
Description concise de la cible : type, nombre, disposition, orientation.

07 — Ligne 6 : Target Location
Position exacte de la cible : coordonnées, repère terrain ou combinaison des deux.

08 — Ligne 7 : Mark Type
Moyen de marquage de la cible : laser, fumigène, traceur, IR, ou « none » s’il n’y a pas de marquage.

09 — Ligne 8 : Friendlies
Position des amis par rapport à la cible : distance et direction.

10 — Ligne 9 : Egress
Direction ou point de sortie de l’avion après l’attaque.

11 — Remarques et restrictions
Après les neuf lignes, le JTAC ajoute les remarques et restrictions : danger close lorsque des amis sont proches de la cible, et le code abort. Chaque mission doit avoir un code abort unique : on ne réutilise pas celui d’une mission précédente.

12 — Collationnement et « Continue »
À la fin du brief, le pilote collationne, c’est-à-dire qu’il relit le brief. Si tout est correct, le JTAC répond « Continue ». Si une ligne est fausse, le JTAC la corrige et attend un nouveau collationnement avant de dire « Continue ».

13 — Ce qu’il faut retenir
1 IP/BP (au moins 4 km) · 2 Heading/Offset · 3 Distance · 4 Target Elevation · 5 Target Description · 6 Target Location · 7 Mark Type · 8 Friendlies · 9 Egress · Remarques (danger close, code abort unique) · collationnement · « Continue ».
FR;
        $theoryEn = <<<'EN'
01 — The purpose of the CAS brief
The CAS brief, called the 9-line, gives the pilot everything needed to carry out the attack announced in the game plan. The nine lines are always given in the same order; the pilot therefore knows what is coming and can note each line in its place.

02 — Line 1: IP / BP
A marker, coordinates or visible natural feature used as the entry point of the air support’s attack run. The IP is chosen at least 4 km from the target.

03 — Line 2: Heading / Offset
The attack heading from the IP to the target, in degrees, with an offset Left or Right of that heading.

04 — Line 3: Distance
The distance between the IP and the target, using the convention in force in the game unit (to be stated at the mission briefing).

05 — Line 4: Target Elevation
The target’s elevation above sea level.

06 — Line 5: Target Description
A concise description of the target: type, number, layout, orientation.

07 — Line 6: Target Location
The exact position of the target: coordinates, a terrain feature or a combination of both.

08 — Line 7: Mark Type
How the target is marked: laser, smoke, tracer, IR, or “none” if there is no mark.

09 — Line 8: Friendlies
The position of friendlies relative to the target: distance and direction.

10 — Line 9: Egress
The aircraft’s exit direction or exit point after the attack.

11 — Remarks and restrictions
After the nine lines, the JTAC adds remarks and restrictions: danger close when friendlies are close to the target, and the abort code. Each mission must have a unique abort code: do not reuse one from a previous mission.

12 — Readback and “Continue”
At the end of the brief, the pilot reads it back. If everything is correct, the JTAC answers “Continue”. If a line is wrong, the JTAC corrects it and waits for a new readback before saying “Continue”.

13 — Key points
1 IP/BP (at least 4 km) · 2 Heading/Offset · 3 Distance · 4 Target Elevation · 5 Target Description · 6 Target Location · 7 Mark Type · 8 Friendlies · 9 Egress · Remarks (danger close, unique abort code) · readback · “Continue”.
EN;
        $demoFr = <<<'FR'
DÉMONSTRATION COMMENTÉE — 9-LINE POUR VOODOO 1
Suite du game plan : Type 1, bomb on target, une GBU-12 guidée laser, code 1688. Voodoo 1 s’est déclaré prêt pour 9-Line.

Bastion : « Voodoo 1, 9-Line, prêt à copier ? »
Voodoo 1 : « Prêt à copier. »

Bastion :
« Ligne 1 : IP CARRIÈRE. »
Commentaire : la carrière est un point naturel bien visible du ciel, à 6 km de la cible, donc au-delà du minimum de 4 km.
« Ligne 2 : cap 015, offset left. »
Commentaire : axe d’attaque de l’IP vers la cible, avec un décalage à gauche de cet axe.
« Ligne 3 : 6 kilomètres. »
Commentaire : distance IP-cible, dans l’unité convenue au briefing.
« Ligne 4 : 85 mètres. »
Commentaire : altitude de la cible au-dessus du niveau de la mer.
« Ligne 5 : une mitrailleuse lourde dans un bâtiment, orientée vers le sud. »
« Ligne 6 : coordonnée 0452 1238, premier bâtiment à gauche de la route nord du village. »
Commentaire : coordonnée et repère terrain combinés.
« Ligne 7 : laser, code 1688. »
« Ligne 8 : amis 600 mètres au sud. »
« Ligne 9 : sortie à gauche, vers l’ouest. »
« Remarques : pas de danger close. Code abort CITRON. »

Voodoo 1 collationne : « IP CARRIÈRE, cap 015 offset left, 6 kilomètres, 85 mètres, mitrailleuse lourde dans un bâtiment, 0452 1238, laser 1688, amis 600 mètres sud, sortie gauche vers l’ouest, abort CITRON. »
Bastion : « Collationnement correct. Continue. »
Commentaire : le pilote a relu, tout est exact : Bastion dit « Continue ». L’autorisation de tir viendra plus tard, pendant l’attaque terminale.

À RETENIR
Neuf lignes dans l’ordre, puis les remarques, puis le collationnement. « Continue » n’autorise pas le tir : il autorise la poursuite de la manœuvre.
FR;
        $demoEn = <<<'EN'
ANNOTATED DEMONSTRATION — 9-LINE FOR VOODOO 1
Following the game plan: Type 1, bomb on target, one laser-guided GBU-12, code 1688. Voodoo 1 has reported ready for 9-Line.

Bastion: “Voodoo 1, 9-Line, ready to copy?”
Voodoo 1: “Ready to copy.”

Bastion:
“Line 1: IP QUARRY.”
Comment: the quarry is a natural feature clearly visible from the air, 6 km from the target, beyond the 4 km minimum.
“Line 2: heading 015, offset left.”
Comment: attack heading from the IP to the target, offset to the left of that heading.
“Line 3: 6 kilometres.”
Comment: IP-to-target distance, in the unit agreed at the briefing.
“Line 4: 85 metres.”
Comment: target elevation above sea level.
“Line 5: one heavy machine gun inside a building, facing south.”
“Line 6: coordinate 0452 1238, first building on the left of the village’s north road.”
Comment: coordinate and terrain feature combined.
“Line 7: laser, code 1688.”
“Line 8: friendlies 600 metres south.”
“Line 9: egress left, to the west.”
“Remarks: no danger close. Abort code LEMON.”

Voodoo 1 reads back: “IP QUARRY, heading 015 offset left, 6 kilometres, 85 metres, heavy machine gun in a building, 0452 1238, laser 1688, friendlies 600 metres south, egress left to the west, abort LEMON.”
Bastion: “Readback correct. Continue.”
Comment: the pilot has read back and everything is correct, so Bastion says “Continue”. Clearance to fire will come later, during the terminal attack.

KEY POINT
Nine lines in order, then remarks, then the readback. “Continue” does not clear fire: it clears the manoeuvre to proceed.
EN;
        $guided = self::question(
            'Le château d’eau est à 2 km de la cible. Que faites-vous pour la ligne 1 ?',
            'The water tower is 2 km from the target. What do you do for line 1?',
            [
                self::text('Vous gardez le château d’eau : il est très visible.', 'You keep the water tower: it is very visible.'),
                self::text('Vous supprimez la ligne 1 : le pilote trouvera seul son entrée.', 'You drop line 1: the pilot will find their own entry.'),
                self::text('Vous choisissez un autre point visible, comme le pont, situé à au moins 4 km de la cible.', 'You choose another visible point, such as the bridge, at least 4 km from the target.'),
            ],
            2,
            'L’IP doit être à au moins 4 km de la cible. Un point très visible mais trop proche ne convient pas, et la ligne 1 n’est jamais omise : elle fixe l’entrée de l’axe d’attaque.',
            'The IP must be at least 4 km from the target. A very visible but too close point is not suitable, and line 1 is never omitted: it sets the entry of the attack run.'
        ) + [
            'brief' => self::text(
                "CAS GUIDÉ — CHOISIR L’IP\nVous préparez le 9-line pour Voodoo 1. Deux points sont visibles du ciel : un château d’eau à 2 km de la cible et un pont à 5 km. Un équipier propose le château d’eau « parce qu’on le voit de loin ».\n\nTravaillez dans cet ordre :\n1. Rappelez ce qu’est l’IP (ligne 1).\n2. Rappelez la distance minimale entre l’IP et la cible.\n3. Comparez les deux points.\n4. Choisissez la bonne action pour la ligne 1.",
                "GUIDED CASE — CHOOSING THE IP\nYou are preparing the 9-line for Voodoo 1. Two points are visible from the air: a water tower 2 km from the target and a bridge 5 km away. A teammate suggests the water tower “because you can see it from far away”.\n\nWork in this order:\n1. Recall what the IP is (line 1).\n2. Recall the minimum distance between the IP and the target.\n3. Compare the two points.\n4. Choose the right action for line 1."
            ),
            'hint' => self::text(
                'Relisez la section 02 : l’IP est un point visible, à au moins 4 km de la cible.',
                'Reread section 02: the IP is a visible point at least 4 km from the target.'
            ),
        ];
        $simulation = self::question(
            'Le collationnement de Hawk 1 contient une erreur. Que répondez-vous ?',
            'Hawk 1’s readback contains an error. What do you answer?',
            [
                self::text('« Continue » : le reste est correct.', '“Continue”: the rest is correct.'),
                self::text('« Correction ligne 6 : coordonnée 3184 7720. Collationnez la ligne 6. » Puis « Continue » après un collationnement correct.', '“Correction line 6: coordinate 3184 7720. Read back line 6.” Then “Continue” after a correct readback.'),
                self::text('« Clear hot » pour gagner du temps.', '“Clear hot” to save time.'),
            ],
            1,
            'Une coordonnée de cible fausse peut mener l’attaque au mauvais endroit. Le JTAC corrige la ligne concernée et n’annonce « Continue » qu’après un collationnement correct. « Clear hot » saute toute la procédure.',
            'A wrong target coordinate can send the attack to the wrong place. The JTAC corrects the line and only says “Continue” after a correct readback. “Clear hot” skips the whole procedure.'
        ) + [
            'brief' => self::text(
                "SIMULATION AUTONOME — LE COLLATIONNEMENT\nVous avez transmis un 9-line complet à Hawk 1 : IP PONT (5 km), cap 270 offset right, 5 kilomètres, 40 mètres, deux camions à l’arrêt côte à côte, coordonnée 3184 7720, mark fumigène rouge, amis 800 mètres au nord, sortie à droite vers le nord, remarques : pas de danger close, code abort FALAISE.\nHawk 1 collationne : « IP PONT, cap 270 offset right, 5 kilomètres, 40 mètres, deux camions, coordonnée 3148 7720, fumigène rouge, amis 800 nord, sortie droite nord, abort FALAISE. »\n\nVotre objectif : vérifier le collationnement ligne par ligne et répondre. Aucune aide n’est donnée pendant cette étape.",
                "INDEPENDENT SIMULATION — THE READBACK\nYou have sent a complete 9-line to Hawk 1: IP BRIDGE (5 km), heading 270 offset right, 5 kilometres, 40 metres, two trucks parked side by side, coordinate 3184 7720, mark red smoke, friendlies 800 metres north, egress right to the north, remarks: no danger close, abort code CLIFF.\nHawk 1 reads back: “IP BRIDGE, heading 270 offset right, 5 kilometres, 40 metres, two trucks, coordinate 3148 7720, red smoke, friendlies 800 north, egress right north, abort CLIFF.”\n\nYour objective: check the readback line by line and answer. No hint is given during this stage."
            ),
        ];
        $questions = [
            self::question(
                'Que contient la ligne 1 du 9-line ?',
                'What does line 1 of the 9-line contain?',
                [
                    self::text('L’IP / BP : point d’entrée de l’axe d’attaque (marqueur, coordonnées ou point naturel visible).', 'The IP / BP: entry point of the attack run (marker, coordinates or visible natural feature).'),
                    self::text('La description de la cible.', 'The target description.'),
                    self::text('La position des amis.', 'The friendlies’ position.'),
                ],
                0,
                'La ligne 1 fixe le point d’où part l’axe d’attaque.',
                'Line 1 sets the point from which the attack run starts.'
            ),
            self::question(
                'À quelle distance minimale de la cible l’IP doit-elle se trouver ?',
                'What is the minimum distance between the IP and the target?',
                [
                    self::text('500 mètres.', '500 metres.'),
                    self::text('4 km.', '4 km.'),
                    self::text('Aucune distance n’est imposée.', 'No distance is required.'),
                ],
                1,
                'La procédure impose une IP à au moins 4 km de la cible.',
                'The procedure requires an IP at least 4 km from the target.'
            ),
            self::question(
                'Quel est l’ordre des lignes 4, 5 et 6 ?',
                'What is the order of lines 4, 5 and 6?',
                [
                    self::text('Target Location, Target Elevation, Target Description.', 'Target Location, Target Elevation, Target Description.'),
                    self::text('Target Description, Friendlies, Egress.', 'Target Description, Friendlies, Egress.'),
                    self::text('Target Elevation, Target Description, Target Location.', 'Target Elevation, Target Description, Target Location.'),
                ],
                2,
                'Ligne 4 : altitude de la cible ; ligne 5 : description ; ligne 6 : position exacte.',
                'Line 4: target elevation; line 5: description; line 6: exact location.'
            ),
            self::question(
                'Que donne la ligne 2 (Heading / Offset) ?',
                'What does line 2 (Heading / Offset) give?',
                [
                    self::text('L’axe d’attaque de l’IP vers la cible, en degrés, avec un décalage Left ou Right.', 'The attack heading from the IP to the target, in degrees, with a Left or Right offset.'),
                    self::text('Le cap de sortie après l’attaque.', 'The exit heading after the attack.'),
                    self::text('La direction des amis par rapport à la cible.', 'The direction of friendlies from the target.'),
                ],
                0,
                'La sortie est la ligne 9 et les amis la ligne 8. La ligne 2 donne l’axe d’attaque et son décalage.',
                'Egress is line 9 and friendlies line 8. Line 2 gives the attack heading and its offset.'
            ),
            self::question(
                'Quelles valeurs peut prendre la ligne 7 (Mark Type) ?',
                'Which values can line 7 (Mark Type) take?',
                [
                    self::text('Type 1, 2 ou 3.', 'Type 1, 2 or 3.'),
                    self::text('Laser, fumigène, traceur, IR, ou none.', 'Laser, smoke, tracer, IR, or none.'),
                    self::text('Clear hot ou Continue dry.', 'Clear hot or Continue dry.'),
                ],
                1,
                'La ligne 7 décrit le moyen de marquage. Les types de contrôle relèvent du game plan et les autorisations de l’attaque terminale.',
                'Line 7 describes the marking method. Types of control belong to the game plan and clearances to the terminal attack.'
            ),
            self::question(
                'Que contient la ligne 8 (Friendlies) ?',
                'What does line 8 (Friendlies) contain?',
                [
                    self::text('Le nombre d’avions de la patrouille.', 'The number of aircraft in the flight.'),
                    self::text('L’indicatif du JTAC.', 'The JTAC’s callsign.'),
                    self::text('La position des amis par rapport à la cible : distance et direction.', 'The position of friendlies relative to the target: distance and direction.'),
                ],
                2,
                'La ligne 8 permet au pilote de savoir où sont les amis au moment de l’attaque.',
                'Line 8 lets the pilot know where friendlies are at the time of the attack.'
            ),
            self::question(
                'Que mettre dans les remarques et restrictions ?',
                'What goes in the remarks and restrictions?',
                [
                    self::text('Le danger close éventuel et le code abort, unique pour chaque mission.', 'Any danger close and the abort code, unique for each mission.'),
                    self::text('Le résultat du BDA.', 'The BDA result.'),
                    self::text('Le même code abort que la mission précédente, pour simplifier.', 'The same abort code as the previous mission, to keep it simple.'),
                ],
                0,
                'Les remarques signalent le danger close et rappellent le code abort. Chaque mission doit avoir son propre code abort.',
                'Remarks flag danger close and restate the abort code. Each mission must have its own abort code.'
            ),
            self::question(
                'Le pilote collationne le 9-line sans erreur. Que répond le JTAC ?',
                'The pilot reads back the 9-line without error. What does the JTAC answer?',
                [
                    self::text('« Clear hot. »', '“Clear hot.”'),
                    self::text('« Continue. »', '“Continue.”'),
                    self::text('« Winchester. »', '“Winchester.”'),
                ],
                1,
                'Un collationnement correct est confirmé par « Continue » : la manœuvre se poursuit. Le tir sera autorisé plus tard, pendant l’attaque terminale.',
                'A correct readback is confirmed with “Continue”: the manoeuvre proceeds. Fire will be cleared later, during the terminal attack.'
            ),
        ];

        return self::chapter(
            self::text('Phase 4 — CAS brief (9-line)', 'Phase 4 — CAS brief (9-line)'),
            self::text($theoryFr, $theoryEn),
            self::text($demoFr, $demoEn),
            $guided,
            $simulation,
            $questions,
            self::text(
                "Rédigez un retour personnel sur le cas du collationnement et votre évaluation :\n1. Les neuf lignes dans l’ordre, de mémoire.\n2. L’erreur repérée dans le collationnement et ses conséquences possibles en jeu.\n3. Comment vous choisissez une IP (visibilité, distance d’au moins 4 km).\n4. Le code abort de la mission et pourquoi il doit être unique.\n5. Une amélioration concrète pour votre prochain 9-line à la radio.",
                "Write a personal reflection on the readback case and your assessment:\n1. The nine lines in order, from memory.\n2. The error spotted in the readback and its possible consequences in the game.\n3. How you choose an IP (visibility, distance of at least 4 km).\n4. The mission abort code and why it must be unique.\n5. One concrete improvement for your next 9-line on the radio."
            ),
            [
                self::text('Neuf lignes restituées dans l’ordre.', 'Nine lines restated in order.'),
                self::text('Erreur de collationnement identifiée et corrigée avant « Continue ».', 'Readback error identified and corrected before “Continue”.'),
                self::text('Choix de l’IP justifié (visible, au moins 4 km).', 'IP choice justified (visible, at least 4 km).'),
                self::text('Code abort unique et remarques expliqués.', 'Unique abort code and remarks explained.'),
            ],
            [
                self::text('L’évaluation atteint au moins 80 % dans le délai prévu.', 'The assessment reaches at least 80% within the time limit.'),
                self::text('Le stagiaire transmet un 9-line complet, dans l’ordre, avec remarques et code abort.', 'The learner sends a complete 9-line, in order, with remarks and abort code.'),
                self::text('L’IP choisie est à au moins 4 km de la cible.', 'The chosen IP is at least 4 km from the target.'),
                self::text('Il vérifie le collationnement et ne dit « Continue » que s’il est exact.', 'They check the readback and only say “Continue” if it is correct.'),
            ]
        );
    }

    // ------------------------------------------------------------------ Phase 5 — Terminal attack and BDA

    private static function terminalAttack(): array
    {
        $theoryFr = <<<'FR'
01 — Le rôle de l’attaque terminale
Après le 9-line collationné, l’attaque terminale est la passe de l’avion vers la cible. Le JTAC garde le contrôle par des ordres brefs, toujours les mêmes, que le pilote reconnaît sans ambiguïté.

02 — Abort
« Abort » : cessez l’action. L’appareil interrompt immédiatement l’attaque. Le code abort de la mission a le même effet. Le JTAC l’utilise dès qu’un doute apparaît : amis trop proches, mauvaise cible, avion mal aligné.

03 — Continue
« Continue » : poursuivez la manœuvre. Exemple : l’avion arrive à l’IP et le signale ; le JTAC répond « Continue » ; le pilote confirme « Prêt » avant la passe d’attaque.

04 — Continue dry
« Continue dry » (ou « Cleared dry ») : poursuivez la manœuvre, mais tir interdit. L’avion fait la passe sans larguer d’armement, par exemple pour vérifier qu’il a bien identifié la cible.

05 — Clear hot
« Clear hot » : autorisé à larguer l’armement. En types 1 et 2, l’autorisation vaut pour cette attaque uniquement. Le pilote annonce « Paveway » lorsqu’il a effectivement utilisé la munition.

06 — Cleared to engage
« Cleared to engage » : autorisé à engager, en type 3, jusqu’à ce que tout soit traité. L’avion choisit lui-même ses passes, puis reprend contact avec le JTAC à l’issue.

07 — Le BDA
Après l’impact, le JTAC observe le résultat (BDA : évaluation des dégâts) et décide de la suite :
- Cible non détruite : on réattaque.
- Cible détruite mais encore du travail sur la même zone : talk-on, puis réattaque. Le talk-on consiste à guider le regard du pilote, de repère en repère, jusqu’à la nouvelle cible.
- Cible détruite mais travail hors secteur : nouvelle 9-Line.
- Cible détruite et fin du travail : le JTAC libère la patrouille.

08 — Ce qu’il faut retenir
Abort = stop. Continue = poursuivre. Continue dry = poursuivre sans tirer. Clear hot = larguer (une attaque). Cleared to engage = engager en type 3 jusqu’à ce que tout soit traité. Après chaque impact, un BDA et l’une des quatre issues.
FR;
        $theoryEn = <<<'EN'
01 — The purpose of the terminal attack
After the 9-line has been read back, the terminal attack is the aircraft’s run onto the target. The JTAC keeps control with short, standard calls that the pilot recognises without ambiguity.

02 — Abort
“Abort”: stop the action. The aircraft immediately breaks off the attack. The mission abort code has the same effect. The JTAC uses it as soon as a doubt appears: friendlies too close, wrong target, aircraft misaligned.

03 — Continue
“Continue”: carry on with the manoeuvre. Example: the aircraft reaches the IP and reports it; the JTAC answers “Continue”; the pilot confirms “Ready” before the attack run.

04 — Continue dry
“Continue dry” (or “Cleared dry”): carry on with the manoeuvre, but no weapons release. The aircraft makes the run without releasing weapons, for example to check it has correctly identified the target.

05 — Clear hot
“Clear hot”: cleared to release weapons. In Types 1 and 2, the clearance covers this attack only. The pilot calls “Paveway” when they have actually released the weapon.

06 — Cleared to engage
“Cleared to engage”: cleared to engage, in Type 3, until everything has been dealt with. The aircraft chooses its own passes, then contacts the JTAC again when finished.

07 — BDA
After impact, the JTAC observes the result (BDA: battle damage assessment) and decides what comes next:
- Target not destroyed: re-attack.
- Target destroyed but more work in the same area: talk-on, then re-attack. A talk-on means guiding the pilot’s eyes, from landmark to landmark, onto the new target.
- Target destroyed but the work is outside the area: new 9-Line.
- Target destroyed and the work is complete: the JTAC releases the flight.

08 — Key points
Abort = stop. Continue = carry on. Continue dry = carry on without firing. Clear hot = release (one attack). Cleared to engage = engage in Type 3 until everything is dealt with. After each impact, a BDA and one of the four outcomes.
EN;
        $demoFr = <<<'FR'
DÉMONSTRATION COMMENTÉE — ATTAQUE DE VOODOO 1 ET BDA
Suite du 9-line : Type 1, bomb on target, GBU-12 laser code 1688, IP CARRIÈRE, code abort CITRON. Collationnement correct, Bastion a dit « Continue ».

Voodoo 1 : « Voodoo 1, IP. »
Bastion : « Continue. »
Voodoo 1 : « Voodoo 1, prêt. »
Commentaire : l’avion signale l’IP, le JTAC l’autorise à poursuivre, le pilote confirme qu’il est prêt pour la passe.

Bastion : « Laser en marche, code 1688. »
Voodoo 1 : « Spot. Captured. »
Commentaire : le pilote voit le spot laser, puis son pod l’acquiert.

Bastion : « Je vous ai en visuel, aligné sur la cible. Clear hot. »
Voodoo 1 : « Paveway. »
Commentaire : type 1, Bastion voit l’avion et la cible. « Clear hot » autorise cette attaque ; « Paveway » annonce la munition larguée.

Impact. Bastion observe.
Bastion : « Mitrailleuse détruite. Infanterie encore active à 100 mètres à l’est du bâtiment, même zone. »
Commentaire : BDA. La cible est détruite mais il reste du travail sur la même zone : talk-on et réattaque.

Bastion : « Depuis le bâtiment détruit, suivez la route vers l’est jusqu’au muret. Infanterie derrière le muret. »
Voodoo 1 : « Tally. »
Commentaire : talk-on de repère en repère ; « Tally » signifie que le pilote voit l’ennemi. Une nouvelle attaque est à l’initiative de Bastion, puisque le type 1 ne vaut que pour une attaque.

Après la seconde attaque :
Bastion : « Cible détruite. Fin du travail. Voodoo 1, vous êtes libéré, merci. »
Commentaire : quatrième issue du BDA, la patrouille est libérée.
FR;
        $demoEn = <<<'EN'
ANNOTATED DEMONSTRATION — VOODOO 1 ATTACK AND BDA
Following the 9-line: Type 1, bomb on target, GBU-12 laser code 1688, IP QUARRY, abort code LEMON. Readback correct, Bastion has said “Continue”.

Voodoo 1: “Voodoo 1, IP.”
Bastion: “Continue.”
Voodoo 1: “Voodoo 1, ready.”
Comment: the aircraft reports the IP, the JTAC clears it to carry on, the pilot confirms ready for the run.

Bastion: “Laser on, code 1688.”
Voodoo 1: “Spot. Captured.”
Comment: the pilot sees the laser spot, then their pod acquires it.

Bastion: “I have you visual, aligned on the target. Clear hot.”
Voodoo 1: “Paveway.”
Comment: Type 1, Bastion sees the aircraft and the target. “Clear hot” clears this attack; “Paveway” reports the weapon released.

Impact. Bastion observes.
Bastion: “Machine gun destroyed. Infantry still active 100 metres east of the building, same area.”
Comment: BDA. The target is destroyed but there is more work in the same area: talk-on and re-attack.

Bastion: “From the destroyed building, follow the road east to the low wall. Infantry behind the wall.”
Voodoo 1: “Tally.”
Comment: talk-on from landmark to landmark; “Tally” means the pilot sees the enemy. Another attack is Bastion’s decision, since Type 1 covers one attack only.

After the second attack:
Bastion: “Target destroyed. Work complete. Voodoo 1, you are released, thank you.”
Comment: fourth BDA outcome, the flight is released.
EN;
        $guided = self::question(
            'Voodoo 1 annonce : « Voodoo 1, IP. » Que répondez-vous ?',
            'Voodoo 1 calls: “Voodoo 1, IP.” What do you answer?',
            [
                self::text('« Continue. »', '“Continue.”'),
                self::text('« Clear hot. »', '“Clear hot.”'),
                self::text('« Cleared to engage. »', '“Cleared to engage.”'),
            ],
            0,
            'À l’IP, le JTAC autorise la poursuite de la manœuvre par « Continue » ; le pilote confirme « Prêt » avant la passe. « Clear hot » vient ensuite, quand le JTAC est sûr de l’alignement ; « Cleared to engage » est réservé au type 3.',
            'At the IP, the JTAC clears the manoeuvre to proceed with “Continue”; the pilot confirms “Ready” before the run. “Clear hot” comes afterwards, when the JTAC is sure of the alignment; “Cleared to engage” is reserved for Type 3.'
        ) + [
            'brief' => self::text(
                "CAS GUIDÉ — ARRIVÉE À L’IP\nLe 9-line de type 1 a été collationné sans erreur et vous avez dit « Continue ». Voodoo 1 arrive maintenant à l’IP et le signale. Vous ne l’avez pas encore vu s’aligner sur l’axe d’attaque.\n\nTravaillez dans cet ordre :\n1. Où en est la manœuvre ?\n2. Le pilote est-il déjà prêt pour la passe ?\n3. Avez-vous assez d’éléments pour autoriser le tir ?\n4. Choisissez l’ordre adapté à ce moment.",
                "GUIDED CASE — ARRIVING AT THE IP\nThe Type 1 9-line was read back without error and you said “Continue”. Voodoo 1 now reaches the IP and reports it. You have not yet seen it line up on the attack heading.\n\nWork in this order:\n1. Where is the manoeuvre at?\n2. Is the pilot already ready for the run?\n3. Do you have enough to clear fire?\n4. Choose the call that fits this moment."
            ),
            'hint' => self::text(
                'Relisez la section 03 : IP → « Continue » → « Prêt ».',
                'Reread section 03: IP → “Continue” → “Ready”.'
            ),
        ];
        $simulation = self::question(
            'Que dites-vous immédiatement ?',
            'What do you say immediately?',
            [
                self::text('« Clear hot » : l’avion est déjà en passe.', '“Clear hot”: the aircraft is already on its run.'),
                self::text('« Abort » (ou le code abort FALAISE) : l’attaque cesse.', '“Abort” (or the abort code CLIFF): the attack stops.'),
                self::text('Rien : vous ferez une nouvelle 9-Line après l’impact.', 'Nothing: you will send a new 9-Line after impact.'),
            ],
            1,
            'Des amis apparaissent près de la cible : le JTAC arrête l’attaque tout de suite avec « Abort » ou le code abort. Autoriser le tir ou attendre l’impact mettrait les amis en danger. La situation sera mise à jour avant toute nouvelle attaque.',
            'Friendlies appear near the target: the JTAC stops the attack at once with “Abort” or the abort code. Clearing fire or waiting for impact would endanger friendlies. The situation will be updated before any new attack.'
        ) + [
            'brief' => self::text(
                "SIMULATION AUTONOME — LES AMIS IMPRÉVUS\nHawk 1 a collationné le 9-line (code abort FALAISE), annoncé l’IP, reçu « Continue », confirmé « Prêt » et commence sa passe sur deux camions. Sur la radio de groupe, une équipe amie annonce soudain qu’elle s’est réfugiée dans le bâtiment voisin des camions, à 50 mètres de la cible.\n\nVotre objectif : prendre la bonne décision dans l’instant. Aucune aide n’est donnée pendant cette étape.",
                "INDEPENDENT SIMULATION — UNEXPECTED FRIENDLIES\nHawk 1 has read back the 9-line (abort code CLIFF), reported the IP, received “Continue”, confirmed “Ready” and is starting its run on two trucks. On the team radio, a friendly team suddenly reports it has taken cover in the building next to the trucks, 50 metres from the target.\n\nYour objective: make the right decision on the spot. No hint is given during this stage."
            ),
        ];
        $questions = [
            self::question(
                'Que signifie « Abort » ?',
                'What does “Abort” mean?',
                [
                    self::text('Poursuivez la manœuvre.', 'Carry on with the manoeuvre.'),
                    self::text('Larguez l’armement.', 'Release weapons.'),
                    self::text('Cessez l’action : l’attaque est interrompue immédiatement.', 'Stop the action: the attack is broken off immediately.'),
                ],
                2,
                '« Abort », comme le code abort de la mission, arrête l’attaque.',
                '“Abort”, like the mission abort code, stops the attack.'
            ),
            self::question(
                'Que signifie « Continue dry » ?',
                'What does “Continue dry” mean?',
                [
                    self::text('Poursuivez la manœuvre, mais tir interdit.', 'Carry on with the manoeuvre, but no weapons release.'),
                    self::text('Larguez toutes les munitions restantes.', 'Release all remaining weapons.'),
                    self::text('Rentrez à la base.', 'Return to base.'),
                ],
                0,
                '« Continue dry » (ou « Cleared dry ») autorise la passe sans largage, par exemple pour vérifier l’identification de la cible.',
                '“Continue dry” (or “Cleared dry”) clears the run without release, for example to check target identification.'
            ),
            self::question(
                'Quelle est la différence entre « Clear hot » et « Cleared to engage » ?',
                'What is the difference between “Clear hot” and “Cleared to engage”?',
                [
                    self::text('Aucune : ce sont deux façons de dire la même chose.', 'None: they are two ways of saying the same thing.'),
                    self::text('« Clear hot » autorise le largage pour une attaque ; « Cleared to engage » autorise, en type 3, à engager jusqu’à ce que tout soit traité.', '“Clear hot” clears release for one attack; “Cleared to engage” clears, in Type 3, engagement until everything is dealt with.'),
                    self::text('« Cleared to engage » interdit le tir ; « Clear hot » l’autorise.', '“Cleared to engage” forbids fire; “Clear hot” allows it.'),
                ],
                1,
                '« Clear hot » vaut pour l’attaque en cours (types 1 et 2). « Cleared to engage » laisse l’avion faire ses passes en type 3, puis reprendre contact.',
                '“Clear hot” covers the current attack (Types 1 and 2). “Cleared to engage” lets the aircraft make its passes in Type 3, then check back in.'
            ),
            self::question(
                'Dans quel ordre se déroule l’arrivée à l’IP ?',
                'In which order does arrival at the IP happen?',
                [
                    self::text('Le pilote dit « Prêt », le JTAC dit « Clear hot », puis le pilote annonce l’IP.', 'The pilot says “Ready”, the JTAC says “Clear hot”, then the pilot reports the IP.'),
                    self::text('Le JTAC dit « Abort », puis « Continue ».', 'The JTAC says “Abort”, then “Continue”.'),
                    self::text('Le pilote annonce l’IP, le JTAC répond « Continue », le pilote confirme « Prêt » avant la passe.', 'The pilot reports the IP, the JTAC answers “Continue”, the pilot confirms “Ready” before the run.'),
                ],
                2,
                'C’est l’exemple donné par la procédure : IP, « Continue », « Prêt », puis la passe d’attaque.',
                'This is the example given by the procedure: IP, “Continue”, “Ready”, then the attack run.'
            ),
            self::question(
                'Au BDA, la cible n’est pas détruite. Quelle est la suite ?',
                'At BDA, the target is not destroyed. What comes next?',
                [
                    self::text('On réattaque.', 'Re-attack.'),
                    self::text('On libère la patrouille.', 'Release the flight.'),
                    self::text('On annonce « Winchester ».', 'Call “Winchester”.'),
                ],
                0,
                'Une cible non détruite appelle une réattaque, décidée par le JTAC.',
                'A target not destroyed calls for a re-attack, decided by the JTAC.'
            ),
            self::question(
                'Au BDA, la cible est détruite mais il reste du travail sur la même zone. Quelle est la suite ?',
                'At BDA, the target is destroyed but there is more work in the same area. What comes next?',
                [
                    self::text('Une nouvelle 9-Line complète.', 'A complete new 9-Line.'),
                    self::text('Un talk-on, puis une réattaque.', 'A talk-on, then a re-attack.'),
                    self::text('La libération de la patrouille.', 'Releasing the flight.'),
                ],
                1,
                'Sur la même zone, le JTAC guide le pilote vers la nouvelle cible (talk-on) et réattaque.',
                'In the same area, the JTAC guides the pilot onto the new target (talk-on) and re-attacks.'
            ),
            self::question(
                'Au BDA, la cible est détruite mais le travail suivant est hors secteur. Quelle est la suite ?',
                'At BDA, the target is destroyed but the next work is outside the area. What comes next?',
                [
                    self::text('Un talk-on depuis la cible détruite.', 'A talk-on from the destroyed target.'),
                    self::text('« Cleared to engage » sur le nouveau secteur.', '“Cleared to engage” on the new area.'),
                    self::text('Une nouvelle 9-Line.', 'A new 9-Line.'),
                ],
                2,
                'Hors secteur, l’IP, l’axe et les amis changent : une nouvelle 9-Line est nécessaire.',
                'Outside the area, the IP, heading and friendlies change: a new 9-Line is needed.'
            ),
            self::question(
                'Au BDA, la cible est détruite et le travail est terminé. Que fait le JTAC ?',
                'At BDA, the target is destroyed and the work is complete. What does the JTAC do?',
                [
                    self::text('Il libère la patrouille.', 'They release the flight.'),
                    self::text('Il demande une nouvelle 9-Line.', 'They request a new 9-Line.'),
                    self::text('Il annonce « Continue dry ».', 'They call “Continue dry”.'),
                ],
                0,
                'Fin du travail : le JTAC libère la patrouille, qui peut quitter sa fréquence.',
                'Work complete: the JTAC releases the flight, which can leave the frequency.'
            ),
        ];

        return self::chapter(
            self::text('Phase 5 — Attaque terminale et BDA', 'Phase 5 — Terminal attack and BDA'),
            self::text($theoryFr, $theoryEn),
            self::text($demoFr, $demoEn),
            $guided,
            $simulation,
            $questions,
            self::text(
                "Rédigez un retour personnel sur le cas des amis imprévus et votre évaluation :\n1. Le moment de l’attaque où l’alerte est arrivée.\n2. L’ordre que vous avez donné et pourquoi il ne pouvait pas attendre.\n3. La différence entre Clear hot et Cleared to engage, avec un exemple de mission.\n4. Les quatre issues du BDA et celle que vous auriez choisie après l’annulation.\n5. Une amélioration concrète pour votre prochaine attaque terminale.",
                "Write a personal reflection on the unexpected friendlies case and your assessment:\n1. The moment in the attack when the warning arrived.\n2. The call you made and why it could not wait.\n3. The difference between Clear hot and Cleared to engage, with a mission example.\n4. The four BDA outcomes and the one you would have chosen after the abort.\n5. One concrete improvement for your next terminal attack."
            ),
            [
                self::text('Décision d’annulation justifiée par la sécurité des amis.', 'Abort decision justified by friendly safety.'),
                self::text('Ordres de l’attaque terminale correctement distingués.', 'Terminal attack calls correctly distinguished.'),
                self::text('Quatre issues du BDA restituées.', 'Four BDA outcomes restated.'),
                self::text('Amélioration concrète et vérifiable en mission.', 'Concrete improvement that can be checked in a mission.'),
            ],
            [
                self::text('L’évaluation atteint au moins 80 % dans le délai prévu.', 'The assessment reaches at least 80% within the time limit.'),
                self::text('Le stagiaire enchaîne IP, Continue, Prêt et Clear hot (ou Cleared to engage en type 3) au bon moment.', 'The learner chains IP, Continue, Ready and Clear hot (or Cleared to engage in Type 3) at the right moment.'),
                self::text('Il annule sans hésiter dès qu’un doute concerne les amis ou la cible.', 'They abort without hesitation as soon as there is doubt about friendlies or the target.'),
                self::text('Il choisit la bonne issue du BDA et libère la patrouille en fin de travail.', 'They choose the right BDA outcome and release the flight when the work is complete.'),
            ]
        );
    }

    // ------------------------------------------------------------------ Brevity

    private static function brevity(): array
    {
        $theoryFr = <<<'FR'
01 — Le rôle du brevity
Le brevity est un vocabulaire de mots courts, toujours les mêmes, qui remplacent une phrase entière à la radio. Il fait gagner du temps et évite les malentendus entre le JTAC et les pilotes. Ces mots restent en anglais.

02 — Visual / Blind
« Visual » : je vois un ami (l’avion allié, un élément au sol ami). « Blind » : je ne vois pas cet ami.

03 — Tally / No Joy
« Tally » : je vois l’ennemi ou la cible. « No Joy » : je ne vois pas l’ennemi. Retenez la différence : Visual et Blind parlent des amis, Tally et No Joy de l’ennemi.

04 — Contact
« Contact » : j’ai le visuel sur un marquage au sol, par exemple un fumigène ou un panneau.

05 — Spot
« Spot » : je vois le spot du code laser.

06 — Captured
« Captured » : j’ai acquis la cible ou le marquage avec un senseur, par exemple le pod qui accroche le marqueur laser.

07 — Winchester
« Winchester » : l’appareil rentre à la base, il n’a plus de munitions ou plus de carburant. Le JTAC en tient compte pour la suite de la mission.

08 — Paveway
« Paveway » : le pilote annonce l’utilisation effective de la munition, après « Clear hot ».

09 — Évacuation sanitaire : « Dust Off »
L’hélicoptère d’évacuation sanitaire (Medevac) a pour indicatif « Dust Off ». Le JTAC l’emploie pour l’appeler ou le désigner à la radio.

10 — Ce qu’il faut retenir
Visual / Blind = ami vu / pas vu. Tally / No Joy = ennemi vu / pas vu. Contact = marquage au sol vu. Spot = spot laser vu. Captured = acquis au senseur. Winchester = retour base. Paveway = munition larguée après Clear hot. Dust Off = Medevac.
FR;
        $theoryEn = <<<'EN'
01 — The purpose of brevity
Brevity is a vocabulary of short, standard words that replace a whole sentence on the radio. It saves time and prevents misunderstandings between the JTAC and pilots. These words stay in English.

02 — Visual / Blind
“Visual”: I can see a friendly (the friendly aircraft, a friendly ground element). “Blind”: I cannot see that friendly.

03 — Tally / No Joy
“Tally”: I can see the enemy or the target. “No Joy”: I cannot see the enemy. Remember the difference: Visual and Blind are about friendlies, Tally and No Joy about the enemy.

04 — Contact
“Contact”: I have eyes on a ground mark, for example smoke or a panel.

05 — Spot
“Spot”: I can see the laser code spot.

06 — Captured
“Captured”: I have acquired the target or the mark with a sensor, for example the pod locking onto the laser marker.

07 — Winchester
“Winchester”: the aircraft is returning to base; it has no weapons or no fuel left. The JTAC takes this into account for the rest of the mission.

08 — Paveway
“Paveway”: the pilot reports actual release of the weapon, after “Clear hot”.

09 — Medical evacuation: “Dust Off”
The medical evacuation (Medevac) helicopter uses the callsign “Dust Off”. The JTAC uses it to call or refer to it on the radio.

10 — Key points
Visual / Blind = friendly seen / not seen. Tally / No Joy = enemy seen / not seen. Contact = ground mark seen. Spot = laser spot seen. Captured = acquired by sensor. Winchester = returning to base. Paveway = weapon released after Clear hot. Dust Off = Medevac.
EN;
        $demoFr = <<<'FR'
DÉMONSTRATION COMMENTÉE — LE BREVITY EN SITUATION
Situation de jeu : Bastion guide Voodoo 1 vers un véhicule ennemi au carrefour nord. La section amie est au sud.

Bastion : « Voodoo 1, fumigène vert sur ma position. »
Voodoo 1 : « Contact fumigène vert. Visual. »
Commentaire : « Contact » = le pilote voit le marquage au sol ; « Visual » = il voit les amis.

Bastion : « Cible : véhicule au carrefour nord, 400 mètres au nord du fumigène. »
Voodoo 1 : « No Joy. »
Commentaire : le pilote ne voit pas l’ennemi. Bastion fait un talk-on.

Bastion : « Depuis le fumigène, suivez la route vers le nord jusqu’au carrefour. Le véhicule est à l’arrêt sur le bas-côté est. »
Voodoo 1 : « Tally. »
Commentaire : le pilote voit maintenant la cible.

Bastion : « Laser en marche. »
Voodoo 1 : « Spot. Captured. »
Commentaire : « Spot » = il voit le spot laser ; « Captured » = son pod l’a acquis.

Bastion : « Clear hot. »
Voodoo 1 : « Paveway. »
Commentaire : la munition est effectivement larguée.

Plus tard :
Voodoo 1 : « Voodoo 1, Winchester. »
Commentaire : plus de munitions ou de carburant, l’avion rentre à la base. Bastion doit compter sans lui.

Un blessé grave est signalé dans la section :
Bastion : « Dust Off, Bastion. »
Commentaire : « Dust Off » est l’indicatif de l’hélicoptère d’évacuation sanitaire.
FR;
        $demoEn = <<<'EN'
ANNOTATED DEMONSTRATION — BREVITY IN ACTION
Game situation: Bastion guides Voodoo 1 onto an enemy vehicle at the northern crossroads. The friendly section is to the south.

Bastion: “Voodoo 1, green smoke on my position.”
Voodoo 1: “Contact green smoke. Visual.”
Comment: “Contact” = the pilot sees the ground mark; “Visual” = they see the friendlies.

Bastion: “Target: vehicle at the northern crossroads, 400 metres north of the smoke.”
Voodoo 1: “No Joy.”
Comment: the pilot cannot see the enemy. Bastion does a talk-on.

Bastion: “From the smoke, follow the road north to the crossroads. The vehicle is stopped on the east verge.”
Voodoo 1: “Tally.”
Comment: the pilot can now see the target.

Bastion: “Laser on.”
Voodoo 1: “Spot. Captured.”
Comment: “Spot” = they see the laser spot; “Captured” = their pod has acquired it.

Bastion: “Clear hot.”
Voodoo 1: “Paveway.”
Comment: the weapon has actually been released.

Later:
Voodoo 1: “Voodoo 1, Winchester.”
Comment: no weapons or fuel left, the aircraft returns to base. Bastion must plan without it.

A serious casualty is reported in the section:
Bastion: “Dust Off, Bastion.”
Comment: “Dust Off” is the callsign of the medical evacuation helicopter.
EN;
        $guided = self::question(
            'Le pilote voit maintenant le véhicule ennemi. Que dit-il ?',
            'The pilot can now see the enemy vehicle. What do they say?',
            [
                self::text('« Visual. »', '“Visual.”'),
                self::text('« Contact. »', '“Contact.”'),
                self::text('« Tally. »', '“Tally.”'),
            ],
            2,
            '« Tally » = je vois l’ennemi. « Visual » concerne un ami et « Contact » un marquage au sol.',
            '“Tally” = I see the enemy. “Visual” is about a friendly and “Contact” about a ground mark.'
        ) + [
            'brief' => self::text(
                "CAS GUIDÉ — AMI, ENNEMI OU MARQUAGE ?\nVous suivez un échange entre Bastion et Voodoo 1. Le pilote a déjà vu le fumigène vert (il a dit « Contact ») et la section amie (il a dit « Visual »). Après un talk-on, il repère enfin le véhicule ennemi au carrefour.\n\nTravaillez dans cet ordre :\n1. Ce que le pilote voit est-il un ami, un ennemi ou un marquage ?\n2. Quel mot correspond à « je vois » pour cette catégorie ?\n3. Choisissez le mot.",
                "GUIDED CASE — FRIENDLY, ENEMY OR MARK?\nYou are following an exchange between Bastion and Voodoo 1. The pilot has already seen the green smoke (they said “Contact”) and the friendly section (they said “Visual”). After a talk-on, they finally spot the enemy vehicle at the crossroads.\n\nWork in this order:\n1. Is what the pilot sees a friendly, an enemy or a mark?\n2. Which word means “I see” for that category?\n3. Choose the word."
            ),
            'hint' => self::text(
                'Visual / Blind pour les amis, Tally / No Joy pour l’ennemi, Contact pour un marquage au sol.',
                'Visual / Blind for friendlies, Tally / No Joy for the enemy, Contact for a ground mark.'
            ),
        ];
        $simulation = self::question(
            'Hawk 1 répond « Blind » à votre demande. Que faites-vous ?',
            'Hawk 1 answers “Blind” to your request. What do you do?',
            [
                self::text('Vous annoncez « Clear hot » : le pilote voit la cible, cela suffit.', 'You call “Clear hot”: the pilot sees the target, that is enough.'),
                self::text('Vous comprenez que le pilote ne voit pas les amis : vous marquez leur position (par exemple un fumigène) et attendez « Contact » puis « Visual » avant de poursuivre.', 'You understand the pilot cannot see the friendlies: you mark their position (for example with smoke) and wait for “Contact” then “Visual” before carrying on.'),
                self::text('Vous comprenez que l’avion n’a plus de munitions et le libérez.', 'You understand the aircraft has no weapons left and release it.'),
            ],
            1,
            '« Blind » signifie que le pilote ne voit pas l’ami. Avec des amis proches, le JTAC aide à les situer avant d’aller plus loin. Le manque de munitions s’annonce par « Winchester ».',
            '“Blind” means the pilot cannot see the friendly. With friendlies nearby, the JTAC helps locate them before going further. Running out of weapons is reported with “Winchester”.'
        ) + [
            'brief' => self::text(
                "SIMULATION AUTONOME — HAWK 1 NE VOIT PAS\nVos amis sont à 300 mètres au sud de la cible, dans un bois. Hawk 1 a annoncé « Tally » sur la cible. Avant de poursuivre, vous lui demandez s’il voit la position amie. Il répond : « Hawk 1, Blind. »\n\nVotre objectif : comprendre le mot et réagir. Aucune aide n’est donnée pendant cette étape.",
                "INDEPENDENT SIMULATION — HAWK 1 CANNOT SEE\nYour friendlies are 300 metres south of the target, in a wood. Hawk 1 has called “Tally” on the target. Before carrying on, you ask whether they can see the friendly position. They answer: “Hawk 1, Blind.”\n\nYour objective: understand the word and react. No hint is given during this stage."
            ),
        ];
        $questions = [
            self::question(
                'Quelle est la différence entre « Tally » et « Visual » ?',
                'What is the difference between “Tally” and “Visual”?',
                [
                    self::text('« Tally » : je vois l’ennemi ; « Visual » : je vois un ami.', '“Tally”: I see the enemy; “Visual”: I see a friendly.'),
                    self::text('« Tally » : je vois un ami ; « Visual » : je vois l’ennemi.', '“Tally”: I see a friendly; “Visual”: I see the enemy.'),
                    self::text('Ce sont deux synonymes.', 'They are synonyms.'),
                ],
                0,
                'Tally / No Joy parlent de l’ennemi, Visual / Blind des amis.',
                'Tally / No Joy are about the enemy, Visual / Blind about friendlies.'
            ),
            self::question(
                'Que signifie « No Joy » ?',
                'What does “No Joy” mean?',
                [
                    self::text('Je ne vois pas l’ami.', 'I cannot see the friendly.'),
                    self::text('Je ne vois pas l’ennemi.', 'I cannot see the enemy.'),
                    self::text('Je n’ai plus de munitions.', 'I have no weapons left.'),
                ],
                1,
                '« No Joy » est le contraire de « Tally ». Ne pas voir l’ami se dit « Blind » ; plus de munitions, « Winchester ».',
                '“No Joy” is the opposite of “Tally”. Not seeing the friendly is “Blind”; no weapons left is “Winchester”.'
            ),
            self::question(
                'Que signifie « Contact » ?',
                'What does “Contact” mean?',
                [
                    self::text('J’ai acquis la cible au senseur.', 'I have acquired the target with a sensor.'),
                    self::text('Je vois l’ennemi.', 'I see the enemy.'),
                    self::text('J’ai le visuel sur un marquage au sol.', 'I have eyes on a ground mark.'),
                ],
                2,
                '« Contact » concerne un marquage au sol : fumigène, panneau… L’acquisition au senseur se dit « Captured ».',
                '“Contact” is about a ground mark: smoke, panel… Sensor acquisition is “Captured”.'
            ),
            self::question(
                'Quelle est la différence entre « Spot » et « Captured » ?',
                'What is the difference between “Spot” and “Captured”?',
                [
                    self::text('« Spot » : je vois le spot laser ; « Captured » : j’ai acquis la cible avec un senseur.', '“Spot”: I see the laser spot; “Captured”: I have acquired the target with a sensor.'),
                    self::text('« Spot » : munition larguée ; « Captured » : cible détruite.', '“Spot”: weapon released; “Captured”: target destroyed.'),
                    self::text('Aucune différence.', 'No difference.'),
                ],
                0,
                'Le pilote voit d’abord le spot du code laser, puis son senseur l’acquiert.',
                'The pilot first sees the laser code spot, then their sensor acquires it.'
            ),
            self::question(
                'Que signifie « Winchester » ?',
                'What does “Winchester” mean?',
                [
                    self::text('L’appareil est prêt pour le 9-line.', 'The aircraft is ready for the 9-line.'),
                    self::text('L’appareil rentre à la base : plus de munitions ou plus de carburant.', 'The aircraft is returning to base: no weapons or no fuel left.'),
                    self::text('La munition a été larguée.', 'The weapon has been released.'),
                ],
                1,
                'Après « Winchester », l’avion ne peut plus soutenir la mission : le JTAC doit en tenir compte.',
                'After “Winchester”, the aircraft can no longer support the mission: the JTAC must take this into account.'
            ),
            self::question(
                'Quand le pilote annonce-t-il « Paveway » ?',
                'When does the pilot call “Paveway”?',
                [
                    self::text('À l’arrivée à l’IP.', 'On reaching the IP.'),
                    self::text('Quand il ne voit pas la cible.', 'When they cannot see the target.'),
                    self::text('Quand il a effectivement utilisé la munition, après « Clear hot ».', 'When they have actually released the weapon, after “Clear hot”.'),
                ],
                2,
                '« Paveway » confirme au JTAC que la munition est partie ; le JTAC prépare alors le BDA.',
                '“Paveway” confirms to the JTAC that the weapon is away; the JTAC then prepares the BDA.'
            ),
            self::question(
                'Quel est l’indicatif de l’évacuation sanitaire (Medevac) ?',
                'What is the medical evacuation (Medevac) callsign?',
                [
                    self::text('« Dust Off ».', '“Dust Off”.'),
                    self::text('« Winchester ».', '“Winchester”.'),
                    self::text('« Voodoo ».', '“Voodoo”.'),
                ],
                0,
                'Dans la procédure de la communauté, le Medevac répond à l’indicatif « Dust Off ».',
                'In the community procedure, the Medevac answers to the callsign “Dust Off”.'
            ),
            self::question(
                'Que signifie « Blind » ?',
                'What does “Blind” mean?',
                [
                    self::text('Je ne vois pas l’ennemi.', 'I cannot see the enemy.'),
                    self::text('Je ne vois pas l’ami.', 'I cannot see the friendly.'),
                    self::text('Le laser est éteint.', 'The laser is off.'),
                ],
                1,
                '« Blind » est le contraire de « Visual » : il concerne un ami. Ne pas voir l’ennemi se dit « No Joy ».',
                '“Blind” is the opposite of “Visual”: it is about a friendly. Not seeing the enemy is “No Joy”.'
            ),
        ];

        return self::chapter(
            self::text('Brevity — Le vocabulaire radio', 'Brevity — Radio vocabulary'),
            self::text($theoryFr, $theoryEn),
            self::text($demoFr, $demoEn),
            $guided,
            $simulation,
            $questions,
            self::text(
                "Rédigez un retour personnel sur le cas « Hawk 1 ne voit pas » et votre évaluation :\n1. Le sens de « Blind » et la réaction choisie.\n2. Les deux paires que vous risquez de confondre (Visual / Tally, Blind / No Joy…) et votre moyen de les retenir.\n3. Le moment où l’on entend « Contact », « Spot », « Captured » puis « Paveway » dans une attaque.\n4. Ce que change « Winchester » pour le JTAC.\n5. Une amélioration concrète pour votre prochaine mission à la radio.",
                "Write a personal reflection on the “Hawk 1 cannot see” case and your assessment:\n1. The meaning of “Blind” and the reaction you chose.\n2. The two pairs you are most likely to confuse (Visual / Tally, Blind / No Joy…) and how you will remember them.\n3. When you hear “Contact”, “Spot”, “Captured” and then “Paveway” in an attack.\n4. What “Winchester” changes for the JTAC.\n5. One concrete improvement for your next mission on the radio."
            ),
            [
                self::text('Sens de « Blind » expliqué et réaction justifiée.', 'Meaning of “Blind” explained and reaction justified.'),
                self::text('Mots concernant les amis, l’ennemi et les marquages distingués.', 'Words about friendlies, enemy and marks distinguished.'),
                self::text('Enchaînement Contact, Spot, Captured, Paveway situé dans l’attaque.', 'Sequence Contact, Spot, Captured, Paveway placed in the attack.'),
                self::text('Amélioration concrète et vérifiable en mission.', 'Concrete improvement that can be checked in a mission.'),
            ],
            [
                self::text('L’évaluation atteint au moins 80 % dans le délai prévu.', 'The assessment reaches at least 80% within the time limit.'),
                self::text('Le stagiaire emploie Visual / Blind et Tally / No Joy sans confusion.', 'The learner uses Visual / Blind and Tally / No Joy without confusion.'),
                self::text('Il connaît Contact, Spot, Captured, Winchester et Paveway.', 'They know Contact, Spot, Captured, Winchester and Paveway.'),
                self::text('Il sait appeler le Medevac par son indicatif « Dust Off ».', 'They know how to call the Medevac by its callsign “Dust Off”.'),
            ]
        );
    }

    // ------------------------------------------------------------------ Shared reference sheets

    private static function resources(): array
    {
        return [
            [
                'title' => self::text('Fiche mémo — Les cinq phases du JTAC', 'Memo card — The five JTAC phases'),
                'type' => 'document',
                'body' => self::text(
                    "FORMATION DE JEU (ARMA 3) — sans valeur de qualification réelle.\n\n1. CONTACT INITIAL\nPrise en charge de la patrouille · menaces immédiates · trafic aérien · check-in (armement ; code laser si armement guidé laser ; senseurs, pods, rover) · code abort.\n\n2. SITUATION UPDATE\nAmis (en général) · ennemi observé ou signalé · menaces · changements. Faits séparés des suppositions, repères visibles du ciel.\n\n3. GAME PLAN\nType de contrôle obligatoire :\n- Type 1 = visuel cible ET attaquant, une attaque.\n- Type 2 = visuel cible OU attaquant, une attaque. JDAM (GBU-38) = forcément type 2.\n- Type 3 = visuel cible, attaques multiples (« Cleared to engage », recontact à l’issue).\nMéthode : bomb on target (MI, laser, strobe IR…) ou bomb on coordinate. Préciser la munition. Fin : « retour radio si prêt pour 9-Line ».\n\n4. CAS BRIEF (9-LINE)\nNeuf lignes, remarques (danger close, code abort unique), collationnement, « Continue ».\n\n5. ATTAQUE TERMINALE ET BDA\nAbort · Continue · Continue dry · Clear hot · Cleared to engage.\nBDA : non détruite → réattaque · détruite, même zone → talk-on et réattaque · détruite, hors secteur → nouvelle 9-Line · détruite, fin du travail → libération de la patrouille.",
                    "IN-GAME TRAINING (ARMA 3) — no real-world qualification value.\n\n1. INITIAL CONTACT\nTaking control of the flight · immediate threats · air traffic · check-in (weapons; laser code if laser-guided weapons; sensors, pods, rover) · abort code.\n\n2. SITUATION UPDATE\nFriendlies (general) · observed or reported enemy · threats · changes. Facts separated from assumptions, landmarks visible from the air.\n\n3. GAME PLAN\nType of control is mandatory:\n- Type 1 = eyes on target AND attacker, one attack.\n- Type 2 = eyes on target OR attacker, one attack. JDAM (GBU-38) = always Type 2.\n- Type 3 = eyes on target, multiple attacks (“Cleared to engage”, check back in when finished).\nMethod: bomb on target (MI, laser, IR strobe…) or bomb on coordinate. Specify the weapon. End: “call when ready for 9-Line”.\n\n4. CAS BRIEF (9-LINE)\nNine lines, remarks (danger close, unique abort code), readback, “Continue”.\n\n5. TERMINAL ATTACK AND BDA\nAbort · Continue · Continue dry · Clear hot · Cleared to engage.\nBDA: not destroyed → re-attack · destroyed, same area → talk-on and re-attack · destroyed, outside the area → new 9-Line · destroyed, work complete → release the flight."
                ),
            ],
            [
                'title' => self::text('Carte 9-Line', '9-Line card'),
                'type' => 'document',
                'body' => self::text(
                    "1 — IP / BP : marqueur, coordonnées ou point naturel visible, entrée de l’axe d’attaque, au moins 4 km de la cible.\n2 — Heading / Offset : axe d’attaque de l’IP vers la cible, en degrés, décalage Left ou Right.\n3 — Distance : IP → cible, selon la convention en vigueur.\n4 — Target Elevation : altitude de la cible au-dessus du niveau de la mer.\n5 — Target Description : type, nombre, disposition, orientation.\n6 — Target Location : coordonnées, repère terrain ou combinaison.\n7 — Mark Type : laser, fumigène, traceur, IR, none.\n8 — Friendlies : position des amis par rapport à la cible, distance et direction.\n9 — Egress : direction ou point de sortie après l’attaque.\nRemarques / restrictions : danger close, code abort (unique pour chaque mission).\nFin : le pilote collationne ; si tout est correct : « CONTINUE ».",
                    "1 — IP / BP: marker, coordinates or visible natural feature, entry of the attack run, at least 4 km from the target.\n2 — Heading / Offset: attack heading from the IP to the target, in degrees, offset Left or Right.\n3 — Distance: IP → target, using the convention in force.\n4 — Target Elevation: target elevation above sea level.\n5 — Target Description: type, number, layout, orientation.\n6 — Target Location: coordinates, terrain feature or combination.\n7 — Mark Type: laser, smoke, tracer, IR, none.\n8 — Friendlies: position of friendlies relative to the target, distance and direction.\n9 — Egress: exit direction or exit point after the attack.\nRemarks / restrictions: danger close, abort code (unique for each mission).\nEnd: the pilot reads back; if everything is correct: “CONTINUE”."
                ),
            ],
            [
                'title' => self::text('Fiche brevity', 'Brevity card'),
                'type' => 'document',
                'body' => self::text(
                    "Visual / Blind — je vois / je ne vois pas un ami.\nTally / No Joy — je vois / je ne vois pas l’ennemi.\nContact — visuel sur un marquage au sol.\nSpot — je vois le spot du code laser.\nCaptured — acquisition par un senseur (marqueur laser par exemple).\nWinchester — retour à la base (plus de munitions, plus de carburant).\nPaveway — munition effectivement utilisée après « Clear hot ».\n\nATTAQUE TERMINALE\nAbort — cessez l’action.\nContinue — poursuivez la manœuvre.\nContinue dry / Cleared dry — poursuivez, tir interdit.\nClear hot — largage autorisé.\nCleared to engage — engagement autorisé (type 3) jusqu’à ce que tout soit traité.\n\nMEDEVAC\nIndicatif « Dust Off ».",
                    "Visual / Blind — I see / I do not see a friendly.\nTally / No Joy — I see / I do not see the enemy.\nContact — eyes on a ground mark.\nSpot — I see the laser code spot.\nCaptured — acquisition by a sensor (laser marker for example).\nWinchester — returning to base (no weapons, no fuel left).\nPaveway — weapon actually released after “Clear hot”.\n\nTERMINAL ATTACK\nAbort — stop the action.\nContinue — carry on with the manoeuvre.\nContinue dry / Cleared dry — carry on, no weapons release.\nClear hot — release cleared.\nCleared to engage — engagement cleared (Type 3) until everything is dealt with.\n\nMEDEVAC\nCallsign “Dust Off”."
                ),
            ],
            [
                'title' => self::text('Grille instructeur — Licence ATH-ACADEMY JTAC', 'Instructor rubric — ATH-ACADEMY JTAC Licence'),
                'type' => 'document',
                'body' => self::text(
                    "OBJET DE LA REVUE\nConfirmer, chapitre par chapitre, que le stagiaire applique la procédure JTAC de la communauté dans nos missions Arma 3. La grille ne qualifie aucune compétence réelle.\n\nCE QUE L’INSTRUCTEUR VÉRIFIE\n- L’évaluation du chapitre est réussie (au moins 80 %) dans le délai ; les tentatives sont lues.\n- Le débriefing relie le cas, le choix effectué et une amélioration testable.\n- Le stagiaire sait dire la phrase radio du chapitre, dans l’ordre, avec les termes OTAN en anglais. L’instructeur peut la faire répéter en session ou en mission d’entraînement.\n\nJUSTIFICATION ATTENDUE\n« J’ai examiné [tentative et débriefing]. Les critères [liste] sont démontrés par [éléments précis]. L’amélioration à tester est [action]. Je valide ce chapitre de formation de jeu. »\n\nSI UN CRITÈRE MANQUE\nOrganiser une reprise ciblée (par exemple un 9-line répété à la radio) avant validation. Ne pas valider uniquement pour clôturer une inscription. L’instructeur ne valide jamais sa propre inscription.",
                    "REVIEW PURPOSE\nConfirm, chapter by chapter, that the learner applies the community JTAC procedure in our Arma 3 missions. The rubric does not certify any real-world competence.\n\nWHAT THE INSTRUCTOR CHECKS\n- The chapter assessment is passed (at least 80%) within the time limit; attempts are read.\n- The debrief links the case, the choice made and a testable improvement.\n- The learner can deliver the chapter’s radio call, in order, with NATO terms in English. The instructor may have it repeated during a session or a training mission.\n\nEXPECTED JUSTIFICATION\n“I reviewed [attempt and debrief]. Criteria [list] are demonstrated by [specific evidence]. The improvement to test is [action]. I validate this in-game training chapter.”\n\nIF A CRITERION IS MISSING\nArrange focused further practice (for example a 9-line repeated on the radio) before validation. Do not validate simply to close an enrolment. The instructor never validates their own enrolment."
                ),
            ],
        ];
    }
}
