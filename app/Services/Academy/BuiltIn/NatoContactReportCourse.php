<?php

declare(strict_types=1);

namespace App\Services\Academy\BuiltIn;

use App\Services\Academy\BuiltIn\AcademyCourseKit as K;

/**
 * Comptes rendus de contact et d’observation (contact report, SALUTE, SALT), désignation d’objectif simple et
 * localisation MGRS, inspirés de publications alliées publiques : procédure radio de l’ACP 125(G) (CCEB, non
 * classifiée) et format SALUTE des manuels publics de l’US Army (TC 3-21.76). Formation de jeu Arma 3 appliquée
 * à ACRE et au terminal ATAK en jeu ; qualification Athena Academy interne.
 */
final class NatoContactReportCourse
{
    public const KEY = 'otan-compte-rendu-salute';
    public const VERSION = '1.0.0';

    /** @return array<string, mixed> */
    public static function course(): array
    {
        return K::course(
            self::KEY,
            self::VERSION,
            K::text('Comptes rendus de contact et SALUTE', 'Contact reports and SALUTE'),
            K::text(
                'Rendre compte vite et juste de ce que l’on voit : contact initial (« CONTACT, WAIT OUT »), compte rendu de contact, SALUTE et SALT, désignation d’objectif (direction, distance, description), localisation MGRS, transmission par radio ACRE et dans ATAK. Formation de jeu inspirée de publications alliées publiques (ACP 125, manuels de l’US Army).',
                'Reporting what you see quickly and accurately: initial contact (“CONTACT, WAIT OUT”), contact report, SALUTE and SALT, target indication (direction, distance, description), MGRS location, transmission on ACRE radio and in ATAK. In-game training inspired by public Allied publications (ACP 125, US Army manuals).'
            ),
            120,
            [self::chapterContact(), self::chapterSalute(), self::chapterLocation()],
            [
                'code' => 'ACAD-OTAN-SALUTE',
                'license_code' => 'SALUTE',
                'name' => K::text('Comptes rendus de contact SALUTE (Athena Academy)', 'SALUTE contact reporting (Athena Academy)'),
                'short_name' => 'SALUTE',
                'description' => K::text(
                    'Le titulaire alerte son réseau d’un contact en une phrase, désigne un objectif à son équipe, puis rédige et transmet un compte rendu SALUTE ou SALT factuel, localisé par grille ou par rapport à un repère, à la radio ACRE et dans ATAK. Qualification de jeu inspirée de l’ACP 125(G) et des formats publics de l’US Army, sans valeur réelle.',
                    'The holder alerts their net to a contact in one sentence, indicates a target to their team, then writes and sends a factual SALUTE or SALT report, located by grid reference or from a reference point, on ACRE radio and in ATAK. In-game qualification inspired by ACP 125(G) and public US Army formats, with no real-world value.'
                ),
                'reference' => K::text(
                    'Inspirée de l’ACP 125(G), « Communications Instructions — Radiotelephone Procedures » (CCEB, publication alliée non classifiée), et du format SALUTE décrit dans des manuels publics de l’US Army (TC 3-21.76, Ranger Handbook).',
                    'Inspired by ACP 125(G), “Communications Instructions — Radiotelephone Procedures” (CCEB, unclassified Allied publication), and the SALUTE format described in public US Army manuals (TC 3-21.76, Ranger Handbook).'
                ),
                'validity_months' => null,
            ]
        );
    }

    // ------------------------------------------------------------------ Chapitre 1 — Contact initial et réaction

    /** @return array<string, mixed> */
    private static function chapterContact(): array
    {
        $theoryFr = <<<'FR'
OBJECTIF DE LA FORMATION
Ce cours forme à rendre compte d’un contact et d’une observation dans nos missions Arma 3 : alerter le réseau, désigner l’objectif à son équipe, rédiger un SALUTE ou un SALT, localiser l’ennemi et transmettre par radio ACRE et dans ATAK. Il s’inspire de publications alliées publiques : l’ACP 125(G) pour la procédure radio, les manuels publics de l’US Army pour le format SALUTE. Les prowords restent en anglais. Il s’agit d’une formation de jeu : la qualification Athena Academy atteste un parcours interne, sans valeur de qualification réelle, militaire ou civile.

01 — Qu’est-ce qu’un contact
Il y a contact dès que votre élément voit, entend ou reçoit le feu d’un ennemi. Un contact change la situation de tout le réseau : les autres équipes doivent le savoir avant les détails. Une simple observation d’un ennemi qui ne vous a pas vu se traite plutôt par un SALUTE (chapitre 2), sans couper le réseau.

02 — L’ordre de réaction
Sous le feu, l’ordre est toujours le même : se mettre à couvert et riposter selon les règles d’engagement de la mission, alerter son équipe à la voix, alerter le réseau par un compte rendu initial très court, puis, dès que le feu est maîtrisé, envoyer le compte rendu de contact détaillé. Le compte rendu ne doit jamais retarder la mise à l’abri.

03 — Le compte rendu initial : « CONTACT, WAIT OUT »
La première transmission tient en une phrase : « Griffon 1, THIS IS Griffon 1-1, CONTACT, WAIT OUT. » Elle prévient le réseau qu’un élément est engagé et qu’il rappellera. WAIT OUT a ici son sens ACP : « je vous rappellerai par un nouvel appel ». Personne ne répond par une question : le réseau se tait et attend. L’ACP 125 réserve l’interruption d’un message tactique en cours au compte rendu de contact ennemi ; si le réseau est occupé, annoncez la précédence IMMEDIATE trois fois.

04 — Le compte rendu de contact
Dès que possible, l’élément rappelle avec les détails, dans un ordre fixe : où (position de l’ennemi : grille, ou direction et distance depuis votre position), quoi (nombre et type), ce qu’il fait (tire, se déplace, vers où), ce que vous faites (vous ripostez, vous tenez, vous décrochez) et ce dont vous avez besoin. Ce compte rendu est court ; le SALUTE complet viendra ensuite si la situation le permet.

05 — Alerter l’équipe : désignation d’objectif simple
À la voix, dans l’équipe, on désigne l’objectif en trois éléments : direction, distance, description. Exemple : « Contact, front, deux cents, deux tireurs derrière le muret ! » La direction se donne par rapport à l’axe de marche ou en points cardinaux, la distance en mètres arrondis, la description par ce qui se voit. Chacun doit pouvoir trouver l’objectif sans chercher.

06 — Les erreurs fréquentes
Transmettre un long compte rendu en plein échange de tirs ; oublier son propre indicatif ; donner sa position au lieu de celle de l’ennemi sans le préciser ; supposer (« c’est sûrement une section ») au lieu de décrire ; garder l’alternat enfoncé ; appeler les joueurs par leur pseudonyme. L’alerte « Contact » d’ATAK complète la radio mais ne la remplace pas : le réseau l’entend avant de la lire.

07 — Ce qu’il faut retenir
Couvert et riposte, alerte à la voix (direction, distance, description), « CONTACT, WAIT OUT », puis compte rendu de contact : où, quoi, ce qu’il fait, ce que vous faites, ce dont vous avez besoin.
FR;
        $theoryEn = <<<'EN'
TRAINING OBJECTIVE
This course trains reporting a contact and an observation in our Arma 3 missions: alerting the net, indicating the target to your team, writing a SALUTE or a SALT, locating the enemy and passing it on ACRE radio and in ATAK. It is inspired by public Allied publications: ACP 125(G) for radio procedure, public US Army manuals for the SALUTE format. Prowords stay in English. This is in-game training: the Athena Academy qualification records an internal pathway and has no real-world qualification value, military or civilian.

01 — What a contact is
A contact exists as soon as your element sees, hears or receives fire from an enemy. A contact changes the situation for the whole net: the other teams must know about it before the details. Simply observing an enemy who has not seen you is better handled with a SALUTE (chapter 2), without cutting into the net.

02 — The order of reaction
Under fire, the order is always the same: take cover and return fire according to the mission’s rules of engagement, alert your team by voice, alert the net with a very short initial report, then, once the fire is under control, send the detailed contact report. Reporting must never delay getting into cover.

03 — The initial report: “CONTACT, WAIT OUT”
The first transmission fits into one sentence: “Griffon 1, THIS IS Griffon 1-1, CONTACT, WAIT OUT.” It warns the net that an element is engaged and will call back. WAIT OUT has its ACP meaning here: “I will call you back with a new call”. Nobody answers with a question: the net goes quiet and waits. ACP 125 reserves breaking into a tactical message in progress for reporting enemy contact; if the net is busy, announce IMMEDIATE precedence three times.

04 — The contact report
As soon as possible, the element calls back with the details, in a fixed order: where (enemy position: grid reference, or direction and distance from your position), what (number and type), what it is doing (firing, moving, which way), what you are doing (returning fire, holding, breaking contact) and what you need. This report is short; the full SALUTE comes later if the situation allows.

05 — Alerting the team: simple target indication
By voice, within the team, the target is indicated in three elements: direction, distance, description. Example: “Contact, front, two hundred, two riflemen behind the low wall!” Direction is given relative to the axis of advance or in cardinal points, distance in rounded metres, description by what can be seen. Everyone must be able to find the target without searching.

06 — Common mistakes
Sending a long report in the middle of a firefight; forgetting your own callsign; giving your own position instead of the enemy’s without saying so; assuming (“it must be a platoon”) instead of describing; holding the press-to-talk down; calling players by their username. The ATAK “Contact” alert supplements the radio but does not replace it: the net hears before it reads.

07 — Key points
Cover and return fire, voice alert (direction, distance, description), “CONTACT, WAIT OUT”, then contact report: where, what, what it is doing, what you are doing, what you need.
EN;
        $demoFr = <<<'FR'
DÉMONSTRATION COMMENTÉE — PRISE À PARTIE À LA LISIÈRE
Situation de jeu : l’équipe « Griffon 1-1 » longe une lisière quand elle reçoit des tirs depuis un hameau. Le chef de section « Griffon 1 » est 300 m en arrière, sur l’AN/PRC-343. Indicatifs fictifs.

Chef d’équipe (à la voix) : « Contact, gauche, trois cents, tireurs dans la maison au toit rouge ! »
Commentaire : direction, distance, description : l’équipe sait où riposter. La radio attend que chacun soit à couvert.

Griffon 1-1 : « Griffon 1, THIS IS Griffon 1-1, CONTACT, WAIT OUT. »
Commentaire : une phrase. Griffon 1 ne répond pas et ne pose aucune question ; le réseau se tait.

Trente secondes plus tard, le feu est maîtrisé.
Griffon 1-1 : « Griffon 1, THIS IS Griffon 1-1, compte rendu de contact. Ennemi GRID ZE-RO FIFE TREE… WUN WUN AIT, maison au toit rouge. Trois tireurs, une mitrailleuse. Ils tirent sur nous depuis l’étage. Nous ripostons depuis la lisière, pas de blessé. Demande appui de la deuxième équipe par la droite, OVER. »
Commentaire : où, quoi, ce qu’il fait, ce que nous faisons, ce dont nous avons besoin. La position donnée est celle de l’ennemi, et elle est annoncée par GRID.

Griffon 1 : « Griffon 1-1, THIS IS Griffon 1, ROGER, Griffon 1-2 vient par la droite, OUT. »
Commentaire : réception, décision, fin d’échange.

À RETENIR
Le premier message ne contient rien d’autre que le contact. Les détails arrivent quand l’équipe peut les donner sans se mettre en danger.
FR;
        $demoEn = <<<'EN'
ANNOTATED DEMONSTRATION — ENGAGED AT THE TREE LINE
Game situation: team “Griffon 1-1” is moving along a tree line when it takes fire from a hamlet. Section leader “Griffon 1” is 300 m behind, on the AN/PRC-343. Fictional callsigns.

Team leader (by voice): “Contact, left, three hundred, riflemen in the red-roofed house!”
Comment: direction, distance, description: the team knows where to return fire. The radio waits until everyone is in cover.

Griffon 1-1: “Griffon 1, THIS IS Griffon 1-1, CONTACT, WAIT OUT.”
Comment: one sentence. Griffon 1 does not answer or ask any question; the net goes quiet.

Thirty seconds later, the fire is under control.
Griffon 1-1: “Griffon 1, THIS IS Griffon 1-1, contact report. Enemy GRID ZE-RO FIFE TREE… WUN WUN AIT, red-roofed house. Three riflemen, one machine gun. Firing at us from the upper floor. We are returning fire from the tree line, no casualties. Request second team support from the right, OVER.”
Comment: where, what, what it is doing, what we are doing, what we need. The position given is the enemy’s, announced by GRID.

Griffon 1: “Griffon 1-1, THIS IS Griffon 1, ROGER, Griffon 1-2 coming from the right, OUT.”
Comment: receipt, decision, end of exchange.

KEY POINT
The first message contains nothing but the contact. The details come when the team can give them without putting itself at risk.
EN;
        $guided = K::stage(
            K::question(
                'Quelle est votre première transmission sur le réseau ?',
                'What is your first transmission on the net?',
                [
                    K::text('« Griffon 1, THIS IS Griffon 1-2, CONTACT, WAIT OUT. »', '“Griffon 1, THIS IS Griffon 1-2, CONTACT, WAIT OUT.”'),
                    K::text('Un SALUTE complet avec la grille de chaque tireur, avant de vous mettre à couvert.', 'A full SALUTE with each rifleman’s grid reference, before getting into cover.'),
                    K::text('« Griffon 1, on se fait tirer dessus, tu nous entends ? Réponds ! »', '“Griffon 1, we’re getting shot at, can you hear us? Answer!”'),
                ],
                0,
                'Le compte rendu initial tient en une phrase et ne demande pas de réponse : WAIT OUT annonce un rappel. Les détails viennent une fois l’équipe à couvert ; une question ouverte encombre le réseau au pire moment.',
                'The initial report fits into one sentence and asks for no reply: WAIT OUT announces a call back. The details come once the team is in cover; an open question clutters the net at the worst moment.'
            ),
            K::text(
                "CAS GUIDÉ — LES TIRS DEPUIS LA CRÊTE\nVous êtes Griffon 1-2, chef d’équipe. Votre équipe traverse un champ ; des tirs arrivent de la crête à votre droite. Vos hommes se jettent derrière un talus et vous avez désigné l’objectif à la voix. Votre chef de section, Griffon 1, ne sait encore rien.\n\nTravaillez dans cet ordre :\n1. Vérifiez que l’équipe est à couvert et que l’objectif est désigné.\n2. Rappelez le but du compte rendu initial (section 03).\n3. Rappelez ce que WAIT OUT annonce.\n4. Choisissez la transmission la plus courte qui remplit ce but.",
                "GUIDED CASE — FIRE FROM THE RIDGE\nYou are Griffon 1-2, team leader. Your team is crossing a field; fire comes from the ridge to your right. Your soldiers dive behind a bank and you have indicated the target by voice. Your section leader, Griffon 1, knows nothing yet.\n\nWork in this order:\n1. Check that the team is in cover and the target indicated.\n2. Recall the purpose of the initial report (section 03).\n3. Recall what WAIT OUT announces.\n4. Choose the shortest transmission that achieves that purpose."
            ),
            K::text(
                'Relisez les sections 02 et 03 : le premier message ne contient que le contact et l’annonce d’un rappel.',
                'Reread sections 02 and 03: the first message contains only the contact and the announcement of a call back.'
            )
        );
        $simulation = K::stage(
            K::question(
                'Quel compte rendu de contact envoyez-vous ?',
                'Which contact report do you send?',
                [
                    K::text('« Griffon 1, THIS IS Griffon 1-3, on est à la ferme, ça tire de partout, je crois que c’est une section, OVER. »', '“Griffon 1, THIS IS Griffon 1-3, we’re at the farm, fire from everywhere, I think it’s a platoon, OVER.”'),
                    K::text('« Griffon 1, THIS IS Griffon 1-3, compte rendu de contact : notre position GRID TREE TOO SIX… AIT FOW-ER SEV-EN, tout va bien, OUT. »', '“Griffon 1, THIS IS Griffon 1-3, contact report: our position GRID TREE TOO SIX… AIT FOW-ER SEV-EN, all fine, OUT.”'),
                    K::text('« Griffon 1, THIS IS Griffon 1-3, compte rendu de contact. Ennemi au nord de la ferme, quatre cents, lisière du bois. Deux tireurs et un véhicule léger. Ils tirent puis se replient vers l’est. Nous tenons la ferme, un blessé léger. Pas de besoin immédiat, OVER. »', '“Griffon 1, THIS IS Griffon 1-3, contact report. Enemy north of the farm, four hundred, edge of the wood. Two riflemen and one light vehicle. Firing then withdrawing east. We are holding the farm, one minor casualty. No immediate need, OVER.”'),
                ],
                2,
                'La troisième réponse suit l’ordre : où (direction et distance depuis la ferme), quoi, ce qu’il fait, ce que vous faites, ce dont vous avez besoin. La première suppose au lieu de décrire ; la deuxième donne votre position sans l’ennemi et ferme l’échange par OUT alors que la section doit pouvoir réagir.',
                'The third answer follows the order: where (direction and distance from the farm), what, what it is doing, what you are doing, what you need. The first assumes instead of describing; the second gives your position without the enemy and closes the exchange with OUT although the section must be able to react.'
            ),
            K::text(
                "SIMULATION AUTONOME — LA FERME\nVous êtes Griffon 1-3. Vous avez déjà transmis « CONTACT, WAIT OUT ». Votre équipe tient une ferme. Au nord, à environ 400 m, deux tireurs et un véhicule léger ont ouvert le feu depuis la lisière d’un bois, puis se replient vers l’est. Un de vos équipiers est blessé légèrement et soigné avec ACE. Le feu est maîtrisé.\n\nVotre objectif : choisir le compte rendu de contact complet et factuel. Aucune aide n’est donnée pendant cette étape ; vous pourrez revoir le cours après une tentative.",
                "INDEPENDENT SIMULATION — THE FARM\nYou are Griffon 1-3. You have already sent “CONTACT, WAIT OUT”. Your team is holding a farm. To the north, about 400 m away, two riflemen and a light vehicle opened fire from the edge of a wood, then withdrew east. One of your team members is lightly wounded and treated with ACE. The fire is under control.\n\nYour objective: choose the complete, factual contact report. No hint is given during this stage; you may review the course after an attempt."
            )
        );
        $questions = [
            K::question(
                'Sous le feu, que faites-vous avant toute transmission radio ?',
                'Under fire, what do you do before any radio transmission?',
                [
                    K::text('Vous rédigez le SALUTE dans ATAK.', 'You write the SALUTE in ATAK.'),
                    K::text('Vous vous mettez à couvert, ripostez selon les règles d’engagement et alertez l’équipe à la voix.', 'You take cover, return fire according to the rules of engagement and alert the team by voice.'),
                    K::text('Vous demandez au chef de section s’il entend les tirs.', 'You ask the section leader whether he can hear the shots.'),
                ],
                1,
                'Le compte rendu ne doit jamais retarder la mise à l’abri. La radio vient juste après, par un message très court.',
                'Reporting must never delay getting into cover. The radio comes right after, with a very short message.'
            ),
            K::question(
                'Que signifie WAIT OUT dans « CONTACT, WAIT OUT » ?',
                'What does WAIT OUT mean in “CONTACT, WAIT OUT”?',
                [
                    K::text('Je vous rappellerai par un nouvel appel ; ne répondez pas.', 'I will call you back with a new call; do not answer.'),
                    K::text('Attendez cinq secondes, je continue.', 'Wait five seconds, I am continuing.'),
                    K::text('Je quitte le réseau.', 'I am leaving the net.'),
                ],
                0,
                'WAIT OUT annonce un nouvel appel plus tard. La pause courte de quelques secondes correspond à WAIT seul.',
                'WAIT OUT announces a new call later. The short pause of a few seconds is WAIT on its own.'
            ),
            K::question(
                'Dans quel ordre se donne le compte rendu de contact ?',
                'In which order is the contact report given?',
                [
                    K::text('Ce dont vous avez besoin, puis votre nom, puis l’heure.', 'What you need, then your name, then the time.'),
                    K::text('L’équipement, puis l’uniforme, puis la taille.', 'Equipment, then uniform, then size.'),
                    K::text('Où, quoi, ce qu’il fait, ce que vous faites, ce dont vous avez besoin.', 'Where, what, what it is doing, what you are doing, what you need.'),
                ],
                2,
                'L’ordre fixe permet au chef de suivre sans relire. On ne donne jamais de nom, seulement l’indicatif.',
                'A fixed order lets the leader follow without rereading. No name is ever given, only the callsign.'
            ),
            K::question(
                'Quels sont les trois éléments d’une désignation d’objectif simple à la voix ?',
                'What are the three elements of a simple voice target indication?',
                [
                    K::text('Taille, activité, heure.', 'Size, activity, time.'),
                    K::text('Direction, distance, description.', 'Direction, distance, description.'),
                    K::text('Indicatif, précédence, grille.', 'Callsign, precedence, grid reference.'),
                ],
                1,
                'Direction, distance et description permettent à chacun de trouver l’objectif sans chercher. Taille, activité et heure appartiennent au SALUTE.',
                'Direction, distance and description let everyone find the target without searching. Size, activity and time belong to the SALUTE.'
            ),
            K::question(
                'Le réseau est occupé par un long message ROUTINE quand votre équipe est prise à partie. Que faites-vous ?',
                'The net is busy with a long ROUTINE message when your team comes under fire. What do you do?',
                [
                    K::text('Vous interrompez : « IMMEDIATE IMMEDIATE IMMEDIATE », puis votre compte rendu de contact.', 'You break in: “IMMEDIATE IMMEDIATE IMMEDIATE”, then your contact report.'),
                    K::text('Vous attendez la fin du message, quelle que soit sa durée.', 'You wait for the message to end, however long it takes.'),
                    K::text('Vous passez votre compte rendu par-dessus, sans prévenir.', 'You send your report over it without warning.'),
                ],
                0,
                'L’ACP 125 réserve l’interruption d’un message tactique au compte rendu de contact ennemi. La précédence répétée trois fois fait taire le réseau ; sur un réseau dirigé, attendez l’autorisation du poste de contrôle. Parler par-dessus brouille les deux messages.',
                'ACP 125 reserves breaking into a tactical message for reporting enemy contact. The precedence spoken three times silences the net; on a directed net, wait for the control station’s authorisation. Talking over it jams both messages.'
            ),
            K::question(
                'Lequel de ces éléments est une erreur dans un compte rendu de contact ?',
                'Which of these is a mistake in a contact report?',
                [
                    K::text('Donner la direction et la distance de l’ennemi depuis votre position.', 'Giving the enemy’s direction and distance from your position.'),
                    K::text('Annoncer ce que fait votre équipe.', 'Stating what your team is doing.'),
                    K::text('Dire « c’est sûrement une compagnie » sans l’avoir vue.', 'Saying “it must be a company” without having seen it.'),
                ],
                2,
                'Un compte rendu décrit ce qui est vu ou entendu. Une supposition, si elle est utile, s’annonce comme telle et ne remplace pas la description.',
                'A report describes what is seen or heard. An assumption, if useful, is stated as such and does not replace the description.'
            ),
            K::question(
                'Quel est le rôle de l’alerte « Contact » d’ATAK lors d’une prise à partie ?',
                'What is the role of the ATAK “Contact” alert during an engagement?',
                [
                    K::text('Elle remplace la radio : plus besoin de parler.', 'It replaces the radio: no need to talk any more.'),
                    K::text('Elle complète la radio et garde une trace, mais l’alerte radio reste la première.', 'It supplements the radio and keeps a record, but the radio alert comes first.'),
                    K::text('Elle sert uniquement après la mission, pour le débriefing.', 'It is only used after the mission, for the debrief.'),
                ],
                1,
                'Le réseau entend avant de lire : la radio prévient tout le monde immédiatement. ATAK conserve l’information et la rend visible au poste.',
                'The net hears before it reads: the radio warns everyone immediately. ATAK keeps the information and makes it visible at the command post.'
            ),
        ];

        return K::chapter(
            K::text('Chapitre 1 — Contact initial et compte rendu de contact', 'Chapter 1 — Initial contact and contact report'),
            K::text($theoryFr, $theoryEn),
            K::text($demoFr, $demoEn),
            $guided,
            $simulation,
            $questions,
            K::text(
                "Rédigez un retour personnel sur le cas de la ferme et votre évaluation :\n1. L’ordre de réaction que vous suivez sous le feu.\n2. Ce que vous mettiez de trop dans votre premier message avant ce chapitre.\n3. La différence entre la position de l’ennemi et la vôtre dans un compte rendu.\n4. Une supposition que vous avez déjà transmise comme un fait, et comment la formuler.\n5. Un exercice concret pour votre prochaine mission (par exemple faire désigner l’objectif à la voix par chaque équipier).",
                "Write a personal reflection on the farm case and your assessment:\n1. The order of reaction you follow under fire.\n2. What you used to put too much of in your first message before this chapter.\n3. The difference between the enemy’s position and yours in a report.\n4. An assumption you once passed as a fact, and how to word it.\n5. A concrete exercise for your next mission (for example having each team member indicate the target by voice)."
            ),
            [
                K::text('Ordre de réaction restitué : couvert, riposte, alerte voix, CONTACT WAIT OUT, compte rendu.', 'Order of reaction restated: cover, return fire, voice alert, CONTACT WAIT OUT, report.'),
                K::text('Sens de WAIT OUT expliqué.', 'Meaning of WAIT OUT explained.'),
                K::text('Faits séparés des suppositions.', 'Facts separated from assumptions.'),
                K::text('Exercice concret et vérifiable proposé.', 'Concrete, checkable exercise proposed.'),
            ],
            [
                K::text('L’évaluation atteint au moins 80 % dans le délai prévu.', 'The assessment reaches at least 80% within the time limit.'),
                K::text('Le stagiaire transmet « CONTACT, WAIT OUT » sans délai ni ajout, à la radio ou en exercice.', 'The learner sends “CONTACT, WAIT OUT” without delay or additions, on the radio or in an exercise.'),
                K::text('Le compte rendu de contact suit l’ordre où, quoi, ce qu’il fait, ce que nous faisons, besoins.', 'The contact report follows the order where, what, what it is doing, what we are doing, needs.'),
                K::text('La désignation d’objectif à la voix contient direction, distance et description.', 'The voice target indication contains direction, distance and description.'),
            ],
            [
                K::sheet(
                    K::text('Fiche mémo — Réagir à un contact', 'Memo card — Reacting to contact'),
                    K::text(
                        "FORMATION DE JEU (ARMA 3) — sans valeur de qualification réelle.\n\n1. COUVERT ET RIPOSTE (règles d’engagement de la mission)\n2. ALERTE À LA VOIX — direction · distance · description\n   « Contact, front, deux cents, deux tireurs derrière le muret ! »\n3. COMPTE RENDU INITIAL (radio)\n   « [Appelé], THIS IS [appelant], CONTACT, WAIT OUT. »\n   Réseau occupé : « IMMEDIATE IMMEDIATE IMMEDIATE » puis le message.\n4. COMPTE RENDU DE CONTACT (dès que le feu est maîtrisé)\n   Où — position de l’ennemi (GRID, ou direction et distance depuis vous / un repère)\n   Quoi — nombre et type\n   Ce qu’il fait — tire, se déplace, direction\n   Ce que nous faisons — riposte, tenons, décrochons, blessés\n   Besoins — appui, CASEVAC, munitions… puis OVER\n5. SALUTE COMPLET si la situation le permet ; alerte et compte rendu dans ATAK.\n\nÀ ÉVITER : long message sous le feu · position confondue · suppositions · alternat bloqué · pseudonymes.",
                        "IN-GAME TRAINING (ARMA 3) — no real-world qualification value.\n\n1. COVER AND RETURN FIRE (mission rules of engagement)\n2. VOICE ALERT — direction · distance · description\n   “Contact, front, two hundred, two riflemen behind the low wall!”\n3. INITIAL REPORT (radio)\n   “[Called], THIS IS [caller], CONTACT, WAIT OUT.”\n   Busy net: “IMMEDIATE IMMEDIATE IMMEDIATE” then the message.\n4. CONTACT REPORT (once the fire is under control)\n   Where — enemy position (GRID, or direction and distance from you / a reference point)\n   What — number and type\n   What it is doing — firing, moving, direction\n   What we are doing — returning fire, holding, breaking contact, casualties\n   Needs — support, CASEVAC, ammunition… then OVER\n5. FULL SALUTE if the situation allows; alert and report in ATAK.\n\nAVOID: long message under fire · positions mixed up · assumptions · stuck press-to-talk · usernames."
                    )
                ),
            ]
        );
    }

    // ------------------------------------------------------------------ Chapitre 2 — SALUTE et SALT

    /** @return array<string, mixed> */
    private static function chapterSalute(): array
    {
        $theoryFr = <<<'FR'
01 — À quoi sert le SALUTE
Le SALUTE est un format de compte rendu d’observation décrit dans des manuels publics de l’US Army : six rubriques, toujours dans le même ordre, pour que le chef reçoive des faits comparables d’une équipe à l’autre. On l’envoie après un contact, une fois le feu maîtrisé, ou pour une observation faite sans être vu. Chaque rubrique décrit ce qui a été vu ou entendu ; ce qui n’est pas connu se dit « inconnu ».

02 — S : Size (taille)
Le nombre de personnels et de véhicules observés : « six personnels, deux véhicules ». Un nombre estimé s’annonce comme tel : « environ dix ». On ne traduit pas ce que l’on voit en échelon (« une section ») : on compte.

03 — A : Activity (activité)
Ce que fait l’ennemi au moment de l’observation : il patrouille, s’installe, creuse, tire, se déplace et dans quelle direction, à quelle allure. « Se déplacent à pied vers le sud, lentement. »

04 — L : Location (localisation)
La position de l’ennemi, pas celle de l’observateur : une grille (GRID, chiffre par chiffre), ou une direction et une distance depuis un repère connu. Si vous donnez aussi votre position, dites-le clairement. Le chapitre 3 détaille la grille et les repères.

05 — U : Unit / Uniform (unité, tenue)
Ce qui permet d’identifier l’ennemi : tenue (couleur, camouflage), insignes, marquages des véhicules, brassards. On décrit ce que l’on voit ; on ne nomme une unité que si elle est connue avec certitude par l’ordre ou par un marquage. Le formulaire SALUTE d’ATAK intitule ce champ « Unit/Uniform ».

06 — T : Time (heure) et E : Equipment (équipement)
T est l’heure de l’observation, pas celle du compte rendu, sur quatre chiffres avec TIME, dans la référence horaire de la mission. E décrit les armes et le matériel : armes individuelles, mitrailleuses, lance-roquettes, véhicules (type, armement monté), radios ou antennes, matériel particulier.

07 — Le SALT et les règles communes
Le SALT (Size, Activity, Location, Time) est une version courte, employée quand il faut aller vite ou quand l’unité et l’équipement ne sont pas identifiés ; le SALUTE complet suit si possible. Règles communes : l’ordre ne change jamais ; un fait par rubrique ; une supposition s’annonce comme telle et ne remplace pas une description ; « inconnu » vaut mieux qu’une invention. À la radio, le SALUTE se passe rubrique par rubrique, en nommant chaque lettre.
FR;
        $theoryEn = <<<'EN'
01 — What SALUTE is for
SALUTE is an observation report format described in public US Army manuals: six items, always in the same order, so that the leader receives facts that can be compared from one team to another. It is sent after a contact, once the fire is under control, or for an observation made without being seen. Each item describes what was seen or heard; anything not known is reported as “unknown”.

02 — S: Size
The number of personnel and vehicles observed: “six personnel, two vehicles”. An estimated number is stated as such: “about ten”. Do not translate what you see into an echelon (“a platoon”): count.

03 — A: Activity
What the enemy is doing at the time of observation: patrolling, setting up, digging in, firing, moving and in which direction, at what pace. “Moving on foot to the south, slowly.”

04 — L: Location
The enemy’s position, not the observer’s: a grid reference (GRID, digit by digit), or a direction and distance from a known reference point. If you also give your own position, say so clearly. Chapter 3 covers grid references and reference points in detail.

05 — U: Unit / Uniform
Anything that identifies the enemy: clothing (colour, camouflage), insignia, vehicle markings, armbands. Describe what you see; name a unit only if it is known with certainty from the orders or a marking. The ATAK SALUTE form labels this field “Unit/Uniform”.

06 — T: Time and E: Equipment
T is the time of observation, not the time of the report, in four digits with TIME, using the mission’s time reference. E describes weapons and equipment: individual weapons, machine guns, rocket launchers, vehicles (type, mounted weapons), radios or antennas, any particular equipment.

07 — SALT and common rules
SALT (Size, Activity, Location, Time) is a short version, used when speed matters or when unit and equipment are not identified; the full SALUTE follows if possible. Common rules: the order never changes; one fact per item; an assumption is stated as such and does not replace a description; “unknown” is better than an invention. On the radio, SALUTE is passed item by item, naming each letter.
EN;
        $demoFr = <<<'FR'
DÉMONSTRATION COMMENTÉE — LE POSTE D’OBSERVATION « GRIFFON 3-1 »
Situation de jeu : un binôme d’observation, « Griffon 3-1 », surveille une route depuis une colline sans être vu. À 14 h 05, heure de la mission, un groupe ennemi s’installe à un carrefour. Il transmet au chef de section « Griffon 3 ». Indicatifs fictifs.

Griffon 3-1 : « Griffon 3, THIS IS Griffon 3-1, SALUTE, OVER. »
Griffon 3 : « Griffon 3-1, THIS IS Griffon 3, SEND, OVER. »
Commentaire : appel préliminaire : le message est long, le chef se prépare à noter.

Griffon 3-1 : « SIERRA : huit personnels, un véhicule. ALFA : ils s’installent au carrefour, deux creusent, les autres sécurisent vers l’ouest. LIMA : GRID TOO SIX AIT… WUN FOW-ER ZE-RO. OVER. »
Commentaire : on compte au lieu d’annoncer « une section » ; l’activité précise l’orientation ; la grille est celle de l’ennemi. Une pause (OVER) laisse le chef suivre.

Griffon 3 : « ROGER, continuez, OVER. »
Griffon 3-1 : « UNIFORM : tenue verte unie, brassards blancs. TANGO : TIME WUN FOW-ER ZE-RO FIFE. ECHO : fusils, une mitrailleuse sur trépied, un pick-up avec mitrailleuse montée, une antenne radio haute. OVER. »
Commentaire : l’unité n’est pas connue : on décrit la tenue. L’heure est celle de l’observation, pas celle du message.

Griffon 3 : « ROGER, SALUTE ensuite dans ATAK, OUT. »

À RETENIR
Huit faits vérifiables valent mieux qu’un « gros groupe en face ». Le chef peut les comparer, les reporter sur la carte et les transmettre plus haut.
FR;
        $demoEn = <<<'EN'
ANNOTATED DEMONSTRATION — OBSERVATION POST “GRIFFON 3-1”
Game situation: an observation pair, “Griffon 3-1”, is watching a road from a hill without being seen. At 14:05, mission time, an enemy group sets up at a crossroads. It reports to section leader “Griffon 3”. Fictional callsigns.

Griffon 3-1: “Griffon 3, THIS IS Griffon 3-1, SALUTE, OVER.”
Griffon 3: “Griffon 3-1, THIS IS Griffon 3, SEND, OVER.”
Comment: preliminary call: the message is long, so the leader gets ready to write.

Griffon 3-1: “SIERRA: eight personnel, one vehicle. ALFA: setting up at the crossroads, two digging, the others securing to the west. LIMA: GRID TOO SIX AIT… WUN FOW-ER ZE-RO. OVER.”
Comment: count instead of announcing “a platoon”; the activity gives the orientation; the grid reference is the enemy’s. A pause (OVER) lets the leader keep up.

Griffon 3: “ROGER, continue, OVER.”
Griffon 3-1: “UNIFORM: plain green clothing, white armbands. TANGO: TIME WUN FOW-ER ZE-RO FIFE. ECHO: rifles, one tripod-mounted machine gun, one pick-up with a mounted machine gun, one tall radio antenna. OVER.”
Comment: the unit is not known, so the clothing is described. The time is the time of observation, not of the message.

Griffon 3: “ROGER, then SALUTE in ATAK, OUT.”

KEY POINT
Eight verifiable facts are worth more than “a big group out there”. The leader can compare them, plot them on the map and pass them up.
EN;
        $guided = K::stage(
            K::question(
                'Quelles rubriques de ce SALUTE sont fausses ?',
                'Which items in this SALUTE are wrong?',
                [
                    K::text('S et E : il fallait donner une unité et un calibre précis.', 'S and E: a unit and an exact calibre should have been given.'),
                    K::text('A et U : l’activité et la tenue sont superflues.', 'A and U: activity and clothing are unnecessary.'),
                    K::text('L et T : la localisation est celle de l’observateur et l’heure est celle du compte rendu, pas de l’observation.', 'L and T: the location is the observer’s and the time is that of the report, not of the observation.'),
                ],
                2,
                'La rubrique L donne la position de l’ennemi et la rubrique T l’heure de l’observation. S et E sont corrects : on compte et on décrit sans inventer de calibre ; A et U font partie du format.',
                'Item L gives the enemy’s position and item T the time of observation. S and E are correct: count and describe without inventing a calibre; A and U are part of the format.'
            ),
            K::text(
                "CAS GUIDÉ — LE SALUTE DE LA RECRUE\nUne recrue a observé une patrouille ennemie à 09 h 40, à environ 300 m au nord de sa position. Elle envoie son SALUTE à 09 h 52 :\nS : quatre personnels.\nA : patrouillent vers l’est.\nL : GRID de sa propre position.\nU : tenue désert, sans insigne visible.\nT : TIME ZE-RO NINE-ER FIFE TOO.\nE : fusils, un lance-roquettes.\n\nTravaillez dans cet ordre :\n1. Pour chaque lettre, rappelez ce qu’elle doit contenir.\n2. Comparez avec ce que la recrue a écrit.\n3. Repérez les rubriques qui décrivent autre chose que l’ennemi au moment de l’observation.\n4. Choisissez la réponse qui les nomme.",
                "GUIDED CASE — THE RECRUIT’S SALUTE\nA recruit observed an enemy patrol at 09:40, about 300 m north of their position. They send their SALUTE at 09:52:\nS: four personnel.\nA: patrolling east.\nL: GRID of their own position.\nU: desert clothing, no visible insignia.\nT: TIME ZE-RO NINE-ER FIFE TOO.\nE: rifles, one rocket launcher.\n\nWork in this order:\n1. For each letter, recall what it must contain.\n2. Compare with what the recruit wrote.\n3. Spot the items that describe something other than the enemy at the time of observation.\n4. Choose the answer that names them."
            ),
            K::text(
                'Relisez les sections 04 et 06 : deux rubriques portent sur l’ennemi au moment où il a été vu.',
                'Reread sections 04 and 06: two items concern the enemy at the moment it was seen.'
            )
        );
        $simulation = K::stage(
            K::question(
                'Quel compte rendu envoyez-vous ?',
                'Which report do you send?',
                [
                    K::text('« SALT. SIERRA : deux véhicules, environ six personnels. ALFA : se déplacent vers le sud sur la route, rapidement. LIMA : GRID ZE-RO SEV-EN TOO… TREE ZE-RO NINE-ER. TANGO : TIME WUN SIX WUN ZE-RO. »', '“SALT. SIERRA: two vehicles, about six personnel. ALFA: moving south on the road, fast. LIMA: GRID ZE-RO SEV-EN TOO… TREE ZE-RO NINE-ER. TANGO: TIME WUN SIX WUN ZE-RO.”'),
                    K::text('« SALUTE. Une section mécanisée ennemie, sûrement leur compagnie de reconnaissance, armée de canons, part vers le sud. »', '“SALUTE. One enemy mechanised platoon, probably their recce company, armed with cannons, heading south.”'),
                    K::text('« SALT. ALFA : vers le sud. SIERRA : beaucoup. TANGO : maintenant. LIMA : pas loin de nous. »', '“SALT. ALFA: south. SIERRA: lots. TANGO: now. LIMA: not far from us.”'),
                ],
                0,
                'Le temps presse et ni l’unité ni l’équipement ne sont identifiés : le SALT convient. Il respecte l’ordre, compte (avec « environ » pour l’estimation), localise l’ennemi par une grille et donne l’heure d’observation. La deuxième réponse invente une unité et un armement ; la troisième change l’ordre et ne contient aucun fait exploitable.',
                'Time is short and neither unit nor equipment is identified: SALT is appropriate. It keeps the order, counts (with “about” for the estimate), locates the enemy by grid reference and gives the time of observation. The second answer invents a unit and weapons; the third changes the order and contains no usable fact.'
            ),
            K::text(
                "SIMULATION AUTONOME — LES VÉHICULES DANS LA POUSSIÈRE\nVous êtes en tête de patrouille. À 16 h 10, heure de la mission, deux véhicules passent rapidement vers le sud sur la route, au carré 072 309 ; la poussière empêche de voir leur type, leurs marquages et leur armement. Vous estimez six personnels à bord. Votre chef doit prévenir la section qui tient le pont au sud dans les deux minutes.\n\nVotre objectif : choisir le compte rendu adapté à l’urgence et aux informations réellement disponibles. Aucune aide n’est donnée pendant cette étape ; vous pourrez revoir le cours après une tentative.",
                "INDEPENDENT SIMULATION — VEHICLES IN THE DUST\nYou are leading a patrol. At 16:10, mission time, two vehicles pass quickly heading south on the road, in map square 072 309; the dust prevents you from seeing their type, markings and weapons. You estimate six personnel on board. Your leader must warn the section holding the bridge to the south within two minutes.\n\nYour objective: choose the report suited to the urgency and to the information actually available. No hint is given during this stage; you may review the course after an attempt."
            )
        );
        $questions = [
            K::question(
                'Que signifient les lettres du SALUTE, dans l’ordre ?',
                'What do the letters of SALUTE stand for, in order?',
                [
                    K::text('Size, Activity, Location, Unit/Uniform, Time, Equipment.', 'Size, Activity, Location, Unit/Uniform, Time, Equipment.'),
                    K::text('Situation, Action, Logistics, Unit, Terrain, Enemy.', 'Situation, Action, Logistics, Unit, Terrain, Enemy.'),
                    K::text('Size, Ammunition, Location, Uniform, Target, Egress.', 'Size, Ammunition, Location, Uniform, Target, Egress.'),
                ],
                0,
                'Taille, activité, localisation, unité ou tenue, heure, équipement : six rubriques dans un ordre fixe.',
                'Size, activity, location, unit or uniform, time, equipment: six items in a fixed order.'
            ),
            K::question(
                'Que contient la rubrique T (Time) ?',
                'What does item T (Time) contain?',
                [
                    K::text('L’heure d’envoi du compte rendu.', 'The time the report is sent.'),
                    K::text('L’heure de l’observation, sur quatre chiffres.', 'The time of the observation, in four digits.'),
                    K::text('La durée de la mission restante.', 'The remaining mission time.'),
                ],
                1,
                'L’heure utile est celle où l’ennemi a été vu : elle dit au chef depuis combien de temps l’information existe.',
                'The useful time is when the enemy was seen: it tells the leader how old the information is.'
            ),
            K::question(
                'Vous comptez sept personnels. Que mettez-vous dans la rubrique S ?',
                'You count seven personnel. What do you put in item S?',
                [
                    K::text('« Une section. »', '“One platoon.”'),
                    K::text('« Beaucoup. »', '“Lots.”'),
                    K::text('« Sept personnels. »', '“Seven personnel.”'),
                ],
                2,
                'On compte et on donne le nombre ; un échelon est une interprétation. Un nombre estimé s’annonce « environ ».',
                'Count and give the number; an echelon is an interpretation. An estimated number is stated as “about”.'
            ),
            K::question(
                'Vous ne savez pas à quelle unité appartiennent les ennemis. Que mettez-vous dans la rubrique U ?',
                'You do not know which unit the enemies belong to. What do you put in item U?',
                [
                    K::text('Le nom de l’unité ennemie la plus probable d’après le briefing.', 'The name of the most likely enemy unit according to the briefing.'),
                    K::text('La description de la tenue, des insignes et des marquages visibles.', 'A description of the clothing, insignia and visible markings.'),
                    K::text('Rien : la rubrique est supprimée.', 'Nothing: the item is dropped.'),
                ],
                1,
                'U signifie Unit/Uniform : à défaut d’unité connue, on décrit ce qui se voit. Le nom d’une unité n’est donné que s’il est certain.',
                'U stands for Unit/Uniform: if the unit is unknown, describe what can be seen. A unit name is given only if it is certain.'
            ),
            K::question(
                'Quand le SALT est-il préférable au SALUTE ?',
                'When is SALT preferable to SALUTE?',
                [
                    K::text('Quand il faut aller vite ou que l’unité et l’équipement ne sont pas identifiés.', 'When speed matters or unit and equipment are not identified.'),
                    K::text('Jamais : le SALT est réservé aux véhicules.', 'Never: SALT is reserved for vehicles.'),
                    K::text('Uniquement pour les observations de nuit.', 'Only for night observations.'),
                ],
                0,
                'Le SALT garde taille, activité, localisation et heure. Le SALUTE complet suit dès que possible.',
                'SALT keeps size, activity, location and time. The full SALUTE follows as soon as possible.'
            ),
            K::question(
                'Que contient la rubrique L (Location) ?',
                'What does item L (Location) contain?',
                [
                    K::text('La position de l’observateur, toujours.', 'The observer’s position, always.'),
                    K::text('Le nom du village le plus proche, sans plus.', 'The name of the nearest village, nothing more.'),
                    K::text('La position de l’ennemi : grille, ou direction et distance depuis un repère connu.', 'The enemy’s position: grid reference, or direction and distance from a known reference point.'),
                ],
                2,
                'Le chef doit pouvoir reporter l’ennemi sur la carte. Si vous ajoutez votre propre position, annoncez-la comme telle.',
                'The leader must be able to plot the enemy on the map. If you add your own position, state it as such.'
            ),
            K::question(
                'Vous pensez qu’un véhicule bâché transporte un mortier, sans l’avoir vu. Comment le signalez-vous ?',
                'You think a covered vehicle is carrying a mortar, without having seen it. How do you report it?',
                [
                    K::text('« E : un mortier. »', '“E: one mortar.”'),
                    K::text('« E : un camion bâché, chargement non vu ; possible mortier, non confirmé. »', '“E: one covered truck, load not seen; possible mortar, unconfirmed.”'),
                    K::text('Vous ne parlez pas du véhicule.', 'You do not mention the vehicle.'),
                ],
                1,
                'Le fait (camion bâché) est donné ; la supposition est annoncée comme telle. Taire le véhicule prive le chef d’un fait réel ; affirmer un mortier invente.',
                'The fact (covered truck) is given; the assumption is stated as such. Leaving the vehicle out deprives the leader of a real fact; asserting a mortar is invention.'
            ),
        ];

        return K::chapter(
            K::text('Chapitre 2 — SALUTE et SALT', 'Chapter 2 — SALUTE and SALT'),
            K::text($theoryFr, $theoryEn),
            K::text($demoFr, $demoEn),
            $guided,
            $simulation,
            $questions,
            K::text(
                "Rédigez un retour personnel sur le cas des véhicules dans la poussière et votre évaluation :\n1. Pourquoi vous avez choisi le SALT ou le SALUTE.\n2. La rubrique que vous remplissiez mal avant ce chapitre.\n3. Comment vous séparez un fait d’une supposition dans un compte rendu.\n4. L’heure que vous mettez en T et pourquoi.\n5. Un SALUTE que vous rédigerez lors de votre prochaine mission, et à qui vous le ferez relire.",
                "Write a personal reflection on the vehicles in the dust case and your assessment:\n1. Why you chose SALT or SALUTE.\n2. The item you used to fill in poorly before this chapter.\n3. How you separate a fact from an assumption in a report.\n4. The time you put in T and why.\n5. A SALUTE you will write in your next mission, and who will review it."
            ),
            [
                K::text('Les six rubriques restituées dans l’ordre, avec leur contenu.', 'The six items restated in order, with their content.'),
                K::text('Choix SALT / SALUTE justifié par l’urgence et l’information disponible.', 'SALT / SALUTE choice justified by urgency and available information.'),
                K::text('Faits, estimations et suppositions distingués.', 'Facts, estimates and assumptions distinguished.'),
                K::text('Heure d’observation et position de l’ennemi correctement placées.', 'Time of observation and enemy position correctly placed.'),
            ],
            [
                K::text('L’évaluation atteint au moins 80 % dans le délai prévu.', 'The assessment reaches at least 80% within the time limit.'),
                K::text('Le stagiaire rédige un SALUTE complet à partir d’une observation en jeu.', 'The learner writes a complete SALUTE from an in-game observation.'),
                K::text('Le stagiaire transmet un SALT à la radio en moins de trente secondes, dans l’ordre.', 'The learner passes a SALT on the radio in under thirty seconds, in order.'),
                K::text('Aucune unité, aucun armement n’est inventé : « inconnu » ou « non confirmé » est employé quand il le faut.', 'No unit or weapon is invented: “unknown” or “unconfirmed” is used where needed.'),
            ],
            [
                K::sheet(
                    K::text('Carte SALUTE / SALT', 'SALUTE / SALT card'),
                    K::text(
                        "FORMATION DE JEU (ARMA 3) — sans valeur de qualification réelle.\n\nS — SIZE : nombre de personnels et de véhicules (compter ; « environ » si estimé).\nA — ACTIVITY : ce qu’ils font, direction et allure.\nL — LOCATION : position de l’ennemi (GRID chiffre par chiffre, ou direction + distance depuis un repère).\nU — UNIT / UNIFORM : unité si certaine, sinon tenue, insignes, marquages.\nT — TIME : heure de l’observation, quatre chiffres, référence de la mission.\nE — EQUIPMENT : armes, véhicules (type, armement monté), radios, matériel particulier.\n\nSALT (urgence, informations partielles) : SIZE · ACTIVITY · LOCATION · TIME, puis SALUTE complet dès que possible.\n\nRÈGLES : ordre fixe · un fait par rubrique · « inconnu » plutôt qu’inventer · supposition annoncée comme telle · à la radio, nommer chaque lettre (SIERRA, ALFA, LIMA, UNIFORM, TANGO, ECHO) et marquer des pauses (OVER).",
                        "IN-GAME TRAINING (ARMA 3) — no real-world qualification value.\n\nS — SIZE: number of personnel and vehicles (count; “about” if estimated).\nA — ACTIVITY: what they are doing, direction and pace.\nL — LOCATION: enemy position (GRID digit by digit, or direction + distance from a reference point).\nU — UNIT / UNIFORM: unit if certain, otherwise clothing, insignia, markings.\nT — TIME: time of observation, four digits, mission time reference.\nE — EQUIPMENT: weapons, vehicles (type, mounted weapons), radios, particular equipment.\n\nSALT (urgent, partial information): SIZE · ACTIVITY · LOCATION · TIME, then full SALUTE as soon as possible.\n\nRULES: fixed order · one fact per item · “unknown” rather than invention · assumption stated as such · on the radio, name each letter (SIERRA, ALFA, LIMA, UNIFORM, TANGO, ECHO) and pause (OVER)."
                    )
                ),
            ]
        );
    }

    // ------------------------------------------------------------------ Chapitre 3 — Localisation, ATAK et radio

    /** @return array<string, mixed> */
    private static function chapterLocation(): array
    {
        $theoryFr = <<<'FR'
01 — La grille MGRS
Le système MGRS (Military Grid Reference System) découpe la carte en carrés. Une référence complète comprend la zone de grille, l’identifiant du carré de 100 km, puis les chiffres : d’abord l’abscisse (vers l’est), ensuite l’ordonnée (vers le nord) ; « à droite, puis en haut ». Le nombre de chiffres, toujours pair, fixe la précision : 4 chiffres, carré de 1 km ; 6 chiffres, 100 m ; 8 chiffres, 10 m. Pour réduire la précision, on tronque, on n’arrondit pas : le petit carré reste dans le grand.

02 — La grille sur nos cartes
Convention de jeu : sur les cartes d’Arma 3, la zone et le carré de 100 km sont omis, on ne transmet que les chiffres. La grille à 6 chiffres (carré de 100 m) est la norme ; donnez 8 chiffres si vos outils ACE le permettent et si la précision compte. À la radio, la grille est précédée de GRID, dite chiffre par chiffre, avec une pause entre les deux groupes : « GRID ZE-RO FOW-ER SEV-EN… TREE WUN TOO ».

03 — La direction
Trois façons, du plus précis au plus rapide : l’azimut en degrés lu à la boussole ACE (« azimut deux-sept-zéro ») ; les points cardinaux (« au nord-est ») ; la méthode horaire dans l’équipe, où midi est l’axe de marche convenu (« à deux heures »). Dans un compte rendu au chef, préférez l’azimut ou les points cardinaux : la méthode horaire dépend de l’orientation de celui qui parle.

04 — La distance
On donne la distance en mètres, arrondie à une valeur utile (50 ou 100 m). Elle s’estime à vue, se mesure sur la carte ou avec un télémètre quand on en dispose. Les centaines et milliers exacts se disent comme tels (« three hundred ») ; les autres valeurs chiffre par chiffre.

05 — Les repères terrain
Un repère est un point que l’émetteur et le destinataire voient ou trouvent sur la carte : église, château d’eau, carrefour, pylône, maison au toit rouge. On localise alors l’ennemi « depuis le repère, direction, distance » : « depuis l’église, trois cents à l’est ». Les repères nommés à l’ordre de mission évitent de longues descriptions. Un repère que l’autre ne voit pas ne sert à rien.

06 — Transmettre dans ATAK
Le terminal ATAK en jeu propose une alerte « Contact » et un formulaire SALUTE dont les champs suivent l’ordre S, A, L, U (Unit/Uniform), T, E, avec la grille dans Location. Le compte rendu part dans le canal de messages de l’unité et dans son journal ; au poste Overwatch, il apparaît comme une observation à confirmer par un opérateur, distincte des positions alliées. ATAK garde la trace et évite les erreurs de copie ; la radio prévient tout le monde, tout de suite.

07 — Radio et ATAK ensemble, et ce qu’il faut retenir
L’ordre recommandé : l’alerte radio d’abord (contact ou SALT), puis le SALUTE dans ATAK, et une phrase radio pour l’annoncer. Une grille critique transmise à la radio se fait collationner (READ BACK GRID). À retenir : grille à droite puis en haut, tronquée et non arrondie ; direction en azimut ou cardinaux ; distance arrondie ; repère visible des deux ; radio d’abord, ATAK ensuite.
FR;
        $theoryEn = <<<'EN'
01 — The MGRS grid
MGRS (Military Grid Reference System) divides the map into squares. A full reference includes the grid zone, the 100 km square identifier, then the digits: easting first (towards the east), then northing (towards the north); “right, then up”. The number of digits, always even, sets the precision: 4 digits, 1 km square; 6 digits, 100 m; 8 digits, 10 m. To reduce precision, truncate, do not round: the small square stays inside the large one.

02 — Grid references on our maps
Game convention: on Arma 3 maps, the zone and 100 km square are omitted and only the digits are passed. The 6-digit grid reference (100 m square) is the norm; give 8 digits if your ACE tools allow it and precision matters. On the radio, the grid is preceded by GRID, spoken digit by digit, with a pause between the two groups: “GRID ZE-RO FOW-ER SEV-EN… TREE WUN TOO”.

03 — Direction
Three ways, from most precise to quickest: the bearing in degrees read on the ACE compass (“bearing two-seven-zero”); cardinal points (“to the north-east”); the clock method within the team, where twelve o’clock is the agreed axis of advance (“at two o’clock”). In a report to the leader, prefer the bearing or cardinal points: the clock method depends on the speaker’s orientation.

04 — Distance
Give the distance in metres, rounded to a useful value (50 or 100 m). It is estimated by eye, measured on the map or with a rangefinder when one is available. Exact hundreds and thousands are spoken as such (“three hundred”); other values digit by digit.

05 — Reference points
A reference point is something both the sender and the receiver can see or find on the map: church, water tower, crossroads, pylon, red-roofed house. The enemy is then located “from the reference point, direction, distance”: “from the church, three hundred east”. Reference points named in the mission orders avoid long descriptions. A reference point the other station cannot see is useless.

06 — Sending in ATAK
The in-game ATAK terminal offers a “Contact” alert and a SALUTE form whose fields follow the order S, A, L, U (Unit/Uniform), T, E, with the grid reference in Location. The report goes to the unit’s message channel and its log; at the Overwatch post, it appears as an observation awaiting confirmation by an operator, separate from friendly positions. ATAK keeps the record and avoids copying errors; the radio warns everyone, straight away.

07 — Radio and ATAK together, and key points
The recommended order: the radio alert first (contact or SALT), then the SALUTE in ATAK, and one radio sentence to announce it. A critical grid reference passed on the radio is read back (READ BACK GRID). Key points: grid right then up, truncated not rounded; direction as a bearing or cardinal points; rounded distance; a reference point both can see; radio first, ATAK next.
EN;
        $demoFr = <<<'FR'
DÉMONSTRATION COMMENTÉE — DU REPÈRE À LA GRILLE
Situation de jeu : le chef d’équipe « Griffon 2-1 » observe un mortier ennemi derrière une ferme, à 300 m à l’est du château d’eau, repère nommé à l’ordre. Le château d’eau est au carré 045 312. Indicatifs fictifs.

Calcul : 300 m vers l’est ajoutent 3 à l’abscisse (carrés de 100 m) : 045 devient 048 ; l’ordonnée ne change pas. Grille de l’ennemi : 048 312.
Commentaire : l’abscisse se lit d’abord ; l’est augmente l’abscisse, le nord augmente l’ordonnée.

Griffon 2-1 : « Griffon 2, THIS IS Griffon 2-1, SALT. SIERRA : trois personnels, un mortier. ALFA : en batterie derrière la ferme, tirent vers le sud. LIMA : depuis le château d’eau, trois cents à l’est, GRID ZE-RO FOW-ER AIT… TREE WUN TOO. TANGO : TIME WUN WUN TOO ZE-RO. READ BACK GRID, OVER. »
Commentaire : repère et grille se confirment l’un l’autre ; la grille est critique, d’où le READ BACK.

Griffon 2 : « I READ BACK, GRID ZE-RO FOW-ER AIT… TREE WUN TOO, OVER. »
Griffon 2-1 : « CORRECT. SALUTE complet dans ATAK, OVER. »
Griffon 2 : « ROGER, OUT. »
Commentaire : Griffon 2-1 remplit le formulaire SALUTE d’ATAK (avec la tenue et l’équipement observés, grille 048 312 dans Location) ; le poste Overwatch voit apparaître l’observation et la confirme.

À RETENIR
Le repère parle à celui qui voit le terrain, la grille à celui qui lit la carte. La radio prévient, ATAK conserve.
FR;
        $demoEn = <<<'EN'
ANNOTATED DEMONSTRATION — FROM REFERENCE POINT TO GRID
Game situation: team leader “Griffon 2-1” observes an enemy mortar behind a farm, 300 m east of the water tower, a reference point named in the orders. The water tower is in map square 045 312. Fictional callsigns.

Calculation: 300 m east adds 3 to the easting (100 m squares): 045 becomes 048; the northing does not change. Enemy grid: 048 312.
Comment: easting is read first; east increases the easting, north increases the northing.

Griffon 2-1: “Griffon 2, THIS IS Griffon 2-1, SALT. SIERRA: three personnel, one mortar. ALFA: set up behind the farm, firing south. LIMA: from the water tower, three hundred east, GRID ZE-RO FOW-ER AIT… TREE WUN TOO. TANGO: TIME WUN WUN TOO ZE-RO. READ BACK GRID, OVER.”
Comment: reference point and grid confirm each other; the grid is critical, hence the READ BACK.

Griffon 2: “I READ BACK, GRID ZE-RO FOW-ER AIT… TREE WUN TOO, OVER.”
Griffon 2-1: “CORRECT. Full SALUTE in ATAK, OVER.”
Griffon 2: “ROGER, OUT.”
Comment: Griffon 2-1 fills in the ATAK SALUTE form (with the clothing and equipment observed, grid 048 312 in Location); the Overwatch post sees the observation appear and confirms it.

KEY POINT
The reference point speaks to whoever sees the ground, the grid to whoever reads the map. The radio warns, ATAK keeps.
EN;
        $guided = K::stage(
            K::question(
                'Quelle est la grille à 6 chiffres de l’ennemi ?',
                'What is the enemy’s 6-digit grid reference?',
                [
                    K::text('132 671.', '132 671.'),
                    K::text('130 673.', '130 673.'),
                    K::text('128 671.', '128 671.'),
                ],
                1,
                'L’abscisse (130) ne change pas : on se déplace vers le nord. 200 m vers le nord ajoutent 2 à l’ordonnée : 671 devient 673. La première réponse a déplacé l’ennemi vers l’est, la troisième vers l’ouest.',
                'The easting (130) does not change: the movement is north. 200 m north adds 2 to the northing: 671 becomes 673. The first answer moved the enemy east, the third west.'
            ),
            K::text(
                "CAS GUIDÉ — LE PONT ET LA MITRAILLEUSE\nVous êtes Griffon 1-1. Une mitrailleuse ennemie est installée 200 m au nord du pont, repère nommé à l’ordre. Sur la carte, le pont est au carré 130 671 (grille à 6 chiffres, carrés de 100 m).\n\nTravaillez dans cet ordre :\n1. Rappelez l’ordre de lecture : abscisse puis ordonnée.\n2. Déterminez quel groupe change quand on se déplace vers le nord.\n3. Convertissez 200 m en nombre de carrés de 100 m.\n4. Choisissez la grille correspondante.",
                "GUIDED CASE — THE BRIDGE AND THE MACHINE GUN\nYou are Griffon 1-1. An enemy machine gun is set up 200 m north of the bridge, a reference point named in the orders. On the map, the bridge is in square 130 671 (6-digit grid reference, 100 m squares).\n\nWork in this order:\n1. Recall the reading order: easting then northing.\n2. Work out which group changes when moving north.\n3. Convert 200 m into a number of 100 m squares.\n4. Choose the corresponding grid reference."
            ),
            K::text(
                'Relisez la section 01 : le premier groupe va vers l’est, le second vers le nord ; un carré de 6 chiffres mesure 100 m.',
                'Reread section 01: the first group runs east, the second north; a 6-digit square measures 100 m.'
            )
        );
        $simulation = K::stage(
            K::question(
                'Comment transmettez-vous cette observation ?',
                'How do you pass this observation on?',
                [
                    K::text('Vous remplissez le SALUTE dans ATAK sans rien dire à la radio : le poste le verra.', 'You fill in the SALUTE in ATAK without saying anything on the radio: the post will see it.'),
                    K::text('Vous dites à la radio : « blindé à deux heures, assez loin », puis vous continuez la patrouille.', 'You say on the radio: “armour at two o’clock, quite far”, then carry on with the patrol.'),
                    K::text('SALT à la radio avec « depuis le pylône, quatre cents au sud-ouest » et la grille, READ BACK GRID, puis SALUTE complet dans ATAK, annoncé à la radio.', 'SALT on the radio with “from the pylon, four hundred south-west” and the grid, READ BACK GRID, then full SALUTE in ATAK, announced on the radio.'),
                ],
                2,
                'La radio prévient d’abord, avec un repère visible et une grille collationnée ; ATAK conserve ensuite le SALUTE complet. ATAK seul laisse la section voisine dans l’ignorance ; la méthode horaire et « assez loin » ne localisent rien pour un chef qui n’a pas votre orientation.',
                'The radio warns first, with a visible reference point and a read-back grid; ATAK then keeps the full SALUTE. ATAK alone leaves the neighbouring section unaware; the clock method and “quite far” locate nothing for a leader who does not share your orientation.'
            ),
            K::text(
                "SIMULATION AUTONOME — LE BLINDÉ PRÈS DU PYLÔNE\nVous êtes Griffon 2-2, en reconnaissance. À environ 400 m au sud-ouest d’un pylône électrique visible de toute la section, un véhicule blindé ennemi est arrêté, moteur tournant. Vous avez calculé sa grille sur la carte. Vous n’êtes pas repéré. La section voisine doit passer à proximité dans quelques minutes.\n\nVotre objectif : choisir la transmission qui prévient vite, localise sans ambiguïté et garde une trace. Aucune aide n’est donnée pendant cette étape ; vous pourrez revoir le cours après une tentative.",
                "INDEPENDENT SIMULATION — THE ARMOUR BY THE PYLON\nYou are Griffon 2-2, on reconnaissance. About 400 m south-west of a power pylon visible to the whole section, an enemy armoured vehicle is stopped, engine running. You have worked out its grid reference on the map. You have not been spotted. The neighbouring section is due to pass nearby in a few minutes.\n\nYour objective: choose the transmission that warns quickly, locates without ambiguity and keeps a record. No hint is given during this stage; you may review the course after an attempt."
            )
        );
        $questions = [
            K::question(
                'Dans une grille MGRS, quel groupe se lit en premier ?',
                'In an MGRS grid reference, which group is read first?',
                [
                    K::text('L’ordonnée (vers le nord).', 'The northing (towards the north).'),
                    K::text('L’abscisse (vers l’est) : « à droite, puis en haut ».', 'The easting (towards the east): “right, then up”.'),
                    K::text('Celui que l’on préfère, si on le précise.', 'Whichever you prefer, if you say so.'),
                ],
                1,
                'On lit toujours l’abscisse d’abord, puis l’ordonnée. Inverser les groupes déplace l’ennemi de plusieurs kilomètres.',
                'The easting is always read first, then the northing. Swapping the groups moves the enemy by several kilometres.'
            ),
            K::question(
                'Quelle précision donne une grille à 6 chiffres ?',
                'What precision does a 6-digit grid reference give?',
                [
                    K::text('Un carré de 100 m.', 'A 100 m square.'),
                    K::text('Un carré de 1 km.', 'A 1 km square.'),
                    K::text('Un carré de 10 m.', 'A 10 m square.'),
                ],
                0,
                '4 chiffres : 1 km ; 6 chiffres : 100 m ; 8 chiffres : 10 m.',
                '4 digits: 1 km; 6 digits: 100 m; 8 digits: 10 m.'
            ),
            K::question(
                'Vous réduisez la grille 0487 3125 (8 chiffres) à 6 chiffres. Quel résultat est juste ?',
                'You reduce grid reference 0487 3125 (8 digits) to 6 digits. Which result is correct?',
                [
                    K::text('049 313, en arrondissant.', '049 313, rounding.'),
                    K::text('487 125, en gardant la fin.', '487 125, keeping the end.'),
                    K::text('048 312, en tronquant chaque groupe.', '048 312, truncating each group.'),
                ],
                2,
                'On tronque chaque groupe sans arrondir : le carré de 100 m obtenu contient le point. Arrondir peut placer le point dans le carré voisin.',
                'Each group is truncated without rounding: the resulting 100 m square contains the point. Rounding can place the point in the neighbouring square.'
            ),
            K::question(
                'Comment se transmet une grille à la radio ?',
                'How is a grid reference passed on the radio?',
                [
                    K::text('« GRID » puis les chiffres un par un, avec une pause entre les deux groupes.', '“GRID” then the digits one by one, with a pause between the two groups.'),
                    K::text('« FIGURES » puis le nombre en langage courant.', '“FIGURES” then the number in everyday language.'),
                    K::text('Uniquement par écrit dans ATAK.', 'Only in writing in ATAK.'),
                ],
                0,
                'L’ACP 125 fait précéder la grille de GRID, chiffre par chiffre, avec une pause entre abscisse et ordonnée. ATAK la complète mais ne remplace pas la radio pour l’urgence.',
                'ACP 125 precedes the grid with GRID, digit by digit, with a pause between easting and northing. ATAK supplements it but does not replace the radio when it is urgent.'
            ),
            K::question(
                'Pourquoi éviter la méthode horaire dans un compte rendu au chef de section ?',
                'Why avoid the clock method in a report to the section leader?',
                [
                    K::text('Parce qu’elle est interdite par l’ACP 125.', 'Because ACP 125 forbids it.'),
                    K::text('Parce qu’elle dépend de l’orientation de celui qui parle, que le chef ne partage pas.', 'Because it depends on the speaker’s orientation, which the leader does not share.'),
                    K::text('Parce qu’elle est trop précise.', 'Because it is too precise.'),
                ],
                1,
                'La méthode horaire marche dans une équipe qui partage un axe de marche. Pour un chef éloigné, l’azimut ou les points cardinaux ne dépendent de personne.',
                'The clock method works within a team sharing an axis of advance. For a distant leader, a bearing or cardinal points depend on no one.'
            ),
            K::question(
                'Qu’est-ce qu’un bon repère terrain pour localiser l’ennemi ?',
                'What makes a good reference point for locating the enemy?',
                [
                    K::text('Un buisson que vous seul voyez.', 'A bush only you can see.'),
                    K::text('Un point décrit vaguement (« là-bas »).', 'A vaguely described point (“over there”).'),
                    K::text('Un point visible ou repérable sur la carte par l’émetteur et le destinataire, idéalement nommé à l’ordre.', 'A point both sender and receiver can see or find on the map, ideally named in the orders.'),
                ],
                2,
                'Un repère ne sert que s’il est partagé. Les repères nommés à l’ordre évitent les descriptions longues.',
                'A reference point is only useful if it is shared. Reference points named in the orders avoid long descriptions.'
            ),
            K::question(
                'Dans quel ordre combinez-vous radio et ATAK pour une observation urgente ?',
                'In which order do you combine radio and ATAK for an urgent observation?',
                [
                    K::text('ATAK d’abord, la radio seulement si le poste ne réagit pas.', 'ATAK first, the radio only if the post does not react.'),
                    K::text('Alerte radio (contact ou SALT), puis SALUTE complet dans ATAK, annoncé à la radio.', 'Radio alert (contact or SALT), then full SALUTE in ATAK, announced on the radio.'),
                    K::text('L’un ou l’autre, jamais les deux.', 'One or the other, never both.'),
                ],
                1,
                'La radio prévient tout le monde immédiatement ; ATAK conserve le détail, évite les erreurs de copie et l’affiche au poste Overwatch pour confirmation.',
                'The radio warns everyone immediately; ATAK keeps the detail, avoids copying errors and shows it at the Overwatch post for confirmation.'
            ),
        ];

        return K::chapter(
            K::text('Chapitre 3 — Localisation (MGRS, direction, distance, repères) et transmission ATAK et radio', 'Chapter 3 — Location (MGRS, direction, distance, reference points) and ATAK and radio transmission'),
            K::text($theoryFr, $theoryEn),
            K::text($demoFr, $demoEn),
            $guided,
            $simulation,
            $questions,
            K::text(
                "Rédigez un retour personnel sur le cas du blindé près du pylône et votre évaluation :\n1. La façon dont vous avez localisé l’ennemi (repère, grille, ou les deux) et pourquoi.\n2. Une erreur de grille que vous avez déjà faite ou vue (ordre des groupes, arrondi).\n3. La méthode de direction que vous emploierez avec votre équipe, puis avec votre chef.\n4. Ce que vous envoyez à la radio et ce que vous envoyez dans ATAK.\n5. Un exercice pour la prochaine session (par exemple calculer trois grilles depuis un repère et les faire collationner).",
                "Write a personal reflection on the armour by the pylon case and your assessment:\n1. How you located the enemy (reference point, grid reference, or both) and why.\n2. A grid error you have made or seen (group order, rounding).\n3. The direction method you will use with your team, then with your leader.\n4. What you send on the radio and what you send in ATAK.\n5. An exercise for the next session (for example working out three grid references from a reference point and having them read back)."
            ),
            [
                K::text('Lecture de grille correcte : abscisse puis ordonnée, troncature.', 'Correct grid reading: easting then northing, truncation.'),
                K::text('Méthode de direction adaptée au destinataire.', 'Direction method suited to the receiver.'),
                K::text('Rôles de la radio et d’ATAK distingués.', 'Roles of radio and ATAK distinguished.'),
                K::text('Exercice concret et vérifiable proposé.', 'Concrete, checkable exercise proposed.'),
            ],
            [
                K::text('L’évaluation atteint au moins 80 % dans le délai prévu.', 'The assessment reaches at least 80% within the time limit.'),
                K::text('Le stagiaire calcule une grille à 6 chiffres à partir d’un repère, d’une direction et d’une distance.', 'The learner works out a 6-digit grid reference from a reference point, a direction and a distance.'),
                K::text('Le stagiaire transmet une grille à la radio avec GRID et la fait collationner.', 'The learner passes a grid reference on the radio with GRID and has it read back.'),
                K::text('Le stagiaire envoie un SALUTE complet dans ATAK, annoncé à la radio, en session ou en mission d’entraînement.', 'The learner sends a full SALUTE in ATAK, announced on the radio, during a session or a training mission.'),
            ],
            [
                K::sheet(
                    K::text('Fiche mémo — Localiser et transmettre', 'Memo card — Locating and passing it on'),
                    K::text(
                        "FORMATION DE JEU (ARMA 3) — sans valeur de qualification réelle.\n\nGRILLE MGRS\nAbscisse (est) puis ordonnée (nord) : « à droite, puis en haut ».\n4 chiffres = 1 km · 6 chiffres = 100 m · 8 chiffres = 10 m.\nRéduire : tronquer, ne pas arrondir.\nNos cartes (convention de jeu) : chiffres seuls ; 6 chiffres par défaut.\nRadio : GRID + chiffres un par un + pause entre les groupes ; READ BACK GRID si critique.\n\nDEPUIS UN REPÈRE (carrés de 100 m)\nEst : + à l’abscisse · Ouest : − à l’abscisse · Nord : + à l’ordonnée · Sud : − à l’ordonnée.\n300 m = 3 carrés.\n\nDIRECTION\nAzimut (boussole ACE) · points cardinaux · méthode horaire dans l’équipe seulement.\n\nDISTANCE\nMètres arrondis (50 / 100) ; centaines exactes dites « three hundred ».\n\nRADIO PUIS ATAK\n1. Radio : CONTACT, WAIT OUT ou SALT.\n2. ATAK : alerte « Contact » ou formulaire SALUTE (S, A, L, U, T, E ; grille dans Location).\n3. Radio : « SALUTE dans ATAK ».\n4. Au poste Overwatch, l’observation attend la confirmation d’un opérateur.",
                        "IN-GAME TRAINING (ARMA 3) — no real-world qualification value.\n\nMGRS GRID\nEasting (east) then northing (north): “right, then up”.\n4 digits = 1 km · 6 digits = 100 m · 8 digits = 10 m.\nReducing: truncate, do not round.\nOur maps (game convention): digits only; 6 digits by default.\nRadio: GRID + digits one by one + pause between the groups; READ BACK GRID if critical.\n\nFROM A REFERENCE POINT (100 m squares)\nEast: + to easting · West: − to easting · North: + to northing · South: − to northing.\n300 m = 3 squares.\n\nDIRECTION\nBearing (ACE compass) · cardinal points · clock method within the team only.\n\nDISTANCE\nRounded metres (50 / 100); exact hundreds spoken “three hundred”.\n\nRADIO THEN ATAK\n1. Radio: CONTACT, WAIT OUT or SALT.\n2. ATAK: “Contact” alert or SALUTE form (S, A, L, U, T, E; grid in Location).\n3. Radio: “SALUTE in ATAK”.\n4. At the Overwatch post, the observation awaits an operator’s confirmation."
                    )
                ),
                K::sheet(
                    K::text('Sources publiques', 'Public sources'),
                    K::text(
                        "FORMATION DE JEU (ARMA 3) — sans valeur de qualification réelle.\n\nRéférences publiques et non classifiées dont ce cours s’inspire (contenu reformulé et adapté au jeu) :\nACP 125(G), Communications Instructions — Radiotelephone Procedures, CCEB, 2016, UNCLASSIFIED (WAIT OUT, interruption pour contact ennemi, transmission des grilles, READ BACK) : https://orwg.cap.gov/media/cms/ACP125GRadioTelephoneProceduresNOV2_EFFE1A51BA783.pdf\nUS Army, TC 3-21.76 Ranger Handbook (format SALUTE), copie publique : https://irp.fas.org/doddir/army/tc3-21-76.pdf\nUS Army, FM 3-25.26 Map Reading and Land Navigation (lecture de grille), copie publique : https://irp.fas.org/doddir/army/fm3-25-26.pdf\nWikipédia — Military Grid Reference System : https://en.wikipedia.org/wiki/Military_Grid_Reference_System\n\nLes conventions propres à nos missions (indicatifs, grilles sans zone, emploi d’ATAK et du poste Overwatch) sont des conventions de jeu de la communauté.",
                        "IN-GAME TRAINING (ARMA 3) — no real-world qualification value.\n\nPublic, unclassified references this course is inspired by (content reworded and adapted to the game):\nACP 125(G), Communications Instructions — Radiotelephone Procedures, CCEB, 2016, UNCLASSIFIED (WAIT OUT, break-in for enemy contact, passing grid references, READ BACK): https://orwg.cap.gov/media/cms/ACP125GRadioTelephoneProceduresNOV2_EFFE1A51BA783.pdf\nUS Army, TC 3-21.76 Ranger Handbook (SALUTE format), public copy: https://irp.fas.org/doddir/army/tc3-21-76.pdf\nUS Army, FM 3-25.26 Map Reading and Land Navigation (grid reading), public copy: https://irp.fas.org/doddir/army/fm3-25-26.pdf\nWikipedia — Military Grid Reference System: https://en.wikipedia.org/wiki/Military_Grid_Reference_System\n\nConventions specific to our missions (callsigns, grid references without zone, use of ATAK and the Overwatch post) are community game conventions."
                    )
                ),
            ]
        );
    }
}
