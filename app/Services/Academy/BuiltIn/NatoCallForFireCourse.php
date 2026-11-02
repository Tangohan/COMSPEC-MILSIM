<?php

declare(strict_types=1);

namespace App\Services\Academy\BuiltIn;

use App\Services\Academy\BuiltIn\AcademyCourseKit as K;

/**
 * Appel de feu (Call for Fire) côté observateur avancé / JFO : localisation de l’objectif, trois transmissions,
 * message to observer, réglage, tir d’efficacité, fin de mission et sécurité. Inspiré de l’ATP 3-09.30 Observed
 * Fires (US Army, diffusion publique), qui renvoie au STANAG 2934 (AArtyP-1). Formation de jeu Arma 3 (mortiers et
 * artillerie servis par des joueurs ou ACE, MGRS dans l’ATAK, radio ACRE), sans valeur réelle.
 */
final class NatoCallForFireCourse
{
    public const KEY = 'otan-appel-de-feu-jfo';
    public const VERSION = '1.0.0';

    /** @return array<string, mixed> */
    public static function course(): array
    {
        return K::course(self::KEY, self::VERSION,
            K::text('Appel de feu indirect — observateur avancé (JFO)', 'Indirect call for fire — forward observer (JFO)'),
            K::text(
                'Formation de jeu (Arma 3) : le rôle de l’observateur, la préparation et la localisation de l’objectif (grid, polar, shift), l’appel de feu en trois transmissions et le message to observer, puis le réglage, le tir d’efficacité, la fin de mission et les règles de sécurité (danger close, check firing). Mortiers et artillerie servis par des joueurs avec ACE, coordonnées dans l’ATAK, radio ACRE. Inspirée de l’ATP 3-09.30 Observed Fires.',
                'In-game training (Arma 3): the observer’s role, preparing and locating the target (grid, polar, shift), the call for fire in three transmissions and the message to observer, then adjustment, fire for effect, end of mission and safety rules (danger close, check firing). Mortars and artillery crewed by players with ACE, coordinates in the ATAK, ACRE radio. Inspired by ATP 3-09.30 Observed Fires.'
            ),
            135,
            [self::chapterOne(), self::chapterTwo(), self::chapterThree()],
            [
                'code' => 'ACAD-OTAN-CFF',
                'license_code' => 'CFF',
                'name' => K::text('Appel de feu indirect — observateur avancé (jeu)', 'Indirect call for fire — forward observer (in-game)'),
                'short_name' => 'APPEL DE FEU',
                'description' => K::text(
                    'Le titulaire sait, en mission Arma 3, localiser un objectif (grid, polar, shift), transmettre un appel de feu en trois transmissions sur ACRE, collationner le message to observer, régler le tir jusqu’au tir d’efficacité et clore la mission avec un compte rendu d’effets, en appliquant danger close et check firing. Qualification de jeu inspirée de l’ATP 3-09.30 Observed Fires, sans valeur réelle.',
                    'In Arma 3 missions, the holder can locate a target (grid, polar, shift), send a call for fire in three transmissions over ACRE, read back the message to observer, adjust fire through to fire for effect and close the mission with an effects report, applying danger close and check firing. In-game qualification inspired by ATP 3-09.30 Observed Fires, with no real-world value.'
                ),
                'reference' => K::text(
                    'Inspirée de l’ATP 3-09.30 Observed Fires (US Army, 2017, publication approuvée pour diffusion publique), qui renvoie pour les procédures alliées au STANAG 2934 (AArtyP-1, Artillery Procedures).',
                    'Inspired by ATP 3-09.30 Observed Fires (US Army, 2017, approved for public release), which refers to STANAG 2934 (AArtyP-1, Artillery Procedures) for allied procedures.'
                ),
                'validity_months' => null,
            ]);
    }

    // ------------------------------------------------------------------ Chapitre 1 — Rôle, préparation et localisation

    /** @return array<string, mixed> */
    private static function chapterOne(): array
    {
        $theoryFr = <<<'FR'
OBJECTIF DE LA FORMATION
Ce cours forme au rôle d’observateur avancé, ou JFO (Joint Fires Observer), dans nos missions Arma 3 : le joueur qui voit l’objectif, demande un tir indirect de mortier ou d’artillerie servi par d’autres joueurs avec ACE, puis le règle. Trois chapitres : rôle, préparation et localisation ; appel de feu en trois transmissions et message to observer ; réglage, tir d’efficacité, fin de mission et sécurité. Les termes OTAN restent en anglais à la radio, comme dans le cours JTAC. Il s’agit d’une formation de jeu : la qualification Athena Academy délivrée est interne à la communauté et reste sans valeur de qualification réelle.

01 — Qui fait quoi
L’observateur est les yeux des pièces : il localise et décrit l’objectif, puis corrige les impacts. La FDC (Fire Direction Center), tenue par les joueurs de la section mortier ou de la batterie, reçoit l’appel, calcule les éléments de tir avec les tables ACE et décide comment traiter l’objectif ; elle l’annonce par le message to observer (MTO). Les pièces tirent. L’observateur demande, la FDC décide : il peut souhaiter une munition ou un nombre de coups, la FDC tranche selon les moyens et les consignes.

02 — Préparer avant la mission
Avant de partir : canal ACRE du réseau feux et indicatifs de l’observateur et de la FDC ; unité d’angle convenue (mils sur 6400, ou degrés si l’équipe le décide, toujours annoncée) ; position exacte de l’observateur, lue dans l’ATAK ou le MicroDAGR ; points connus et cibles enregistrées, avec leur numéro, partagés sur la carte ou l’ATAK ; position des amis, limites de secteur et zones interdites de tir annoncées au briefing. Sans ces éléments, l’appel sera lent ou dangereux.

03 — Le cycle de l’observateur
L’ATP 3-09.30 décrit un cycle continu : observer son secteur, rendre compte de la situation, coordonner avec le chef et le JTAC s’il y en a un, préparer les données de l’objectif, exécuter l’appel de feu, évaluer les effets. Un bon observateur prépare l’objectif suivant pendant que le premier est traité.

04 — Localiser par grid
La grille est la méthode standard ; elle n’est pas annoncée dans le warning order. Six chiffres au minimum (précision de 100 mètres), huit chiffres (10 mètres) quand l’ATAK ou le télémètre le permettent ; l’altitude suit la grille. La direction observateur-objectif (direction OT) n’est pas nécessaire à la FDC pour localiser : on l’envoie après l’appel initial, en collationnant le MTO.

05 — Localiser par polar
Avec « POLAR » dans le warning order, l’objectif est donné par rapport à l’observateur : direction, puis distance, et si besoin un écart vertical (UP ou DOWN). La FDC doit connaître la position exacte de l’observateur. C’est la méthode naturelle avec des jumelles télémètres ACE.

06 — Localiser par shift from a known point
Avec « SHIFT » suivi du point connu ou du numéro de cible, l’objectif est donné par rapport à un point connu de l’observateur et de la FDC : direction OT, puis écart latéral LEFT ou RIGHT en mètres, écart en distance ADD ou DROP arrondi à 100 mètres, et écart vertical UP ou DOWN s’il est important. C’est rapide quand un carrefour ou une cible enregistrée est près de l’objectif.

07 — Ce qu’il faut retenir
Préparer le réseau, l’unité d’angle, sa position, les points connus et la position des amis. Choisir la méthode : grid par défaut, polar avec un télémètre et une position connue, shift près d’un point connu. Puis passer à l’appel de feu.
FR;
        $theoryEn = <<<'EN'
TRAINING OBJECTIVE
This course trains the forward observer, or JFO (Joint Fires Observer), role in our Arma 3 missions: the player who sees the target, requests indirect mortar or artillery fire crewed by other players with ACE, then adjusts it. Three chapters: role, preparation and target location; the call for fire in three transmissions and the message to observer; adjustment, fire for effect, end of mission and safety. NATO terms stay in English on the radio, as in the JTAC course. This is in-game training: the Athena Academy qualification awarded is internal to the community and has no real-world qualification value.

01 — Who does what
The observer is the guns’ eyes: they locate and describe the target, then correct the impacts. The FDC (Fire Direction Center), run by the players of the mortar section or battery, receives the call, computes firing data with the ACE tables and decides how to engage the target; it states this in the message to observer (MTO). The guns fire. The observer requests, the FDC decides: the observer may ask for a munition or number of rounds, the FDC rules according to assets and orders.

02 — Preparing before the mission
Before leaving: the ACRE fires net channel and the observer’s and FDC’s callsigns; the agreed angle unit (mils on 6400, or degrees if the team decides so, always stated); the observer’s exact position, read from the ATAK or MicroDAGR; known points and recorded targets, with their numbers, shared on the map or ATAK; friendly positions, sector limits and no-fire areas given at the briefing. Without these, the call will be slow or dangerous.

03 — The observer’s cycle
ATP 3-09.30 describes a continuous cycle: observe the sector, report the situation, coordinate with the leader and the JTAC if there is one, prepare the target data, execute the call for fire, assess the effects. A good observer prepares the next target while the first is being engaged.

04 — Locating by grid
Grid is the standard method; it is not announced in the warning order. Six digits minimum (100-metre precision), eight digits (10 metres) when the ATAK or rangefinder allows; altitude follows the grid. The observer-target (OT) direction is not needed by the FDC to locate the target: send it after the initial call, when reading back the MTO.

05 — Locating by polar
With “POLAR” in the warning order, the target is given relative to the observer: direction, then distance, and if needed a vertical shift (UP or DOWN). The FDC must know the observer’s exact position. This is the natural method with ACE rangefinder binoculars.

06 — Locating by shift from a known point
With “SHIFT” followed by the known point or target number, the target is given relative to a point known to both observer and FDC: OT direction, then lateral shift LEFT or RIGHT in metres, range shift ADD or DROP rounded to 100 metres, and vertical shift UP or DOWN if significant. It is quick when a crossroads or recorded target lies near the target.

07 — Key points
Prepare the net, angle unit, own position, known points and friendly positions. Choose the method: grid by default, polar with a rangefinder and a known position, shift near a known point. Then move on to the call for fire.
EN;
        $demoFr = <<<'FR'
DÉMONSTRATION COMMENTÉE — HIBOU 3 SUR LA CRÊTE
Situation de jeu : l’observateur « Hibou 3 » et son binôme tiennent une crête. La section mortier « Marteau » est en batterie à trois kilomètres en arrière. Au briefing, le carrefour au pied de la colline a été enregistré comme cible AB1001. Unité d’angle convenue : mils.

Hibou 3 : « Groupe ennemi au nord-est, ils creusent près de la ferme. Je regarde où ils sont par rapport au carrefour. »
Commentaire : l’observateur commence par repérer l’objectif et un point connu proche.

Binôme : « Le carrefour AB1001 est à 300 mètres de la ferme. »
Hibou 3 : « Direction 0800 mils vers l’objectif. La ferme est 200 mètres à droite et 300 mètres au-delà du carrefour. Je peux faire un shift. »
Commentaire : direction OT mesurée, puis écart latéral et en distance par rapport au point connu, arrondi à 100 mètres.

Hibou 3 : « Je vérifie aussi au télémètre : 1 400 mètres. Et l’ATAK me donne la grille 052 131. »
Commentaire : trois méthodes possibles, polar, shift ou grid. Elles servent aussi à se contrôler mutuellement : si elles ne concordent pas, une erreur s’est glissée.

Hibou 3 : « Les amis les plus proches, Viking 2, sont à 1 200 mètres à l’ouest de l’objectif, hors de tout danger. J’appelle par grid, c’est le plus simple pour Marteau. »
Commentaire : avant de transmettre, l’observateur vérifie la position des amis sur l’ATAK. Le choix de la méthode dépend de ce que la FDC traitera le plus vite et le plus sûrement.

À RETENIR
Un point connu partagé au briefing, une unité d’angle convenue et une position exacte permettent de localiser un objectif de plusieurs façons, et donc de vérifier sa localisation avant d’appeler.
FR;
        $demoEn = <<<'EN'
ANNOTATED DEMONSTRATION — HIBOU 3 ON THE RIDGE
Game situation: observer “Hibou 3” and their teammate hold a ridge. The mortar section “Marteau” is in position three kilometres to the rear. At the briefing, the crossroads at the foot of the hill was recorded as target AB1001. Agreed angle unit: mils.

Hibou 3: “Enemy group north-east, they’re digging in near the farm. Let me see where they are relative to the crossroads.”
Comment: the observer starts by spotting the target and a nearby known point.

Teammate: “Crossroads AB1001 is 300 metres from the farm.”
Hibou 3: “Direction 0800 mils to the target. The farm is 200 metres right and 300 metres beyond the crossroads. I can do a shift.”
Comment: OT direction measured, then lateral and range shift from the known point, rounded to 100 metres.

Hibou 3: “Let me check with the rangefinder as well: 1,400 metres. And the ATAK gives me grid 052 131.”
Comment: three possible methods, polar, shift or grid. They also check one another: if they do not agree, an error has crept in.

Hibou 3: “The nearest friendlies, Viking 2, are 1,200 metres west of the target, well clear. I’ll call by grid, it’s simplest for Marteau.”
Comment: before transmitting, the observer checks friendly positions on the ATAK. The choice of method depends on what the FDC will process fastest and most reliably.

KEY POINT
A known point shared at the briefing, an agreed angle unit and an exact position make it possible to locate a target in several ways, and therefore to check the location before calling.
EN;
        $guided = K::stage(
            K::question(
                'Quelle deuxième transmission envoyez-vous après « ADJUST FIRE, SHIFT AB1002, OVER » ?',
                'Which second transmission do you send after “ADJUST FIRE, SHIFT AB1002, OVER”?',
                [
                    K::text('« LEFT 200, DROP 400, DIRECTION 4800, OVER. »', '“LEFT 200, DROP 400, DIRECTION 4800, OVER.”'),
                    K::text('« DIRECTION 4800, RIGHT 200, ADD 400, OVER. »', '“DIRECTION 4800, RIGHT 200, ADD 400, OVER.”'),
                    K::text('« DIRECTION 4800, DISTANCE 400, OVER. »', '“DIRECTION 4800, DISTANCE 400, OVER.”'),
                ],
                1,
                'Dans un shift, la direction OT vient d’abord, puis l’écart latéral (RIGHT, car l’objectif est à droite du point), puis l’écart en distance (ADD, car il est au-delà). Une simple distance correspondrait à un polar, qui part de l’observateur et non du point connu.',
                'In a shift, the OT direction comes first, then the lateral shift (RIGHT, because the target is right of the point), then the range shift (ADD, because it is beyond). A plain distance would be a polar, which starts from the observer, not from the known point.'
            ),
            K::text(
                "CAS GUIDÉ — SHIFT DEPUIS LE PONT\nVous êtes Hibou 3. Le pont AB1002 a été enregistré au briefing ; Marteau en connaît la position. Un véhicule ennemi s’arrête 200 mètres à droite du pont et 400 mètres au-delà, vu de votre poste. Votre direction vers l’objectif est 4800 mils. Vous avez déjà envoyé la première transmission : « Marteau, ici Hibou 3, ADJUST FIRE, SHIFT AB1002, OVER. »\n\nTravaillez dans cet ordre :\n1. Rappelez l’ordre des éléments d’un shift.\n2. Déterminez le sens de l’écart latéral (LEFT ou RIGHT).\n3. Déterminez le sens de l’écart en distance (ADD ou DROP).\n4. Choisissez la transmission correcte.",
                "GUIDED CASE — SHIFT FROM THE BRIDGE\nYou are Hibou 3. Bridge AB1002 was recorded at the briefing; Marteau knows its position. An enemy vehicle stops 200 metres right of the bridge and 400 metres beyond it, as seen from your post. Your direction to the target is 4800 mils. You have already sent the first transmission: “Marteau, this is Hibou 3, ADJUST FIRE, SHIFT AB1002, OVER.”\n\nWork in this order:\n1. Recall the order of the elements in a shift.\n2. Work out the direction of the lateral shift (LEFT or RIGHT).\n3. Work out the direction of the range shift (ADD or DROP).\n4. Choose the correct transmission."
            ),
            K::text(
                'Relisez la section 06 : direction OT, puis LEFT/RIGHT, puis ADD/DROP, puis UP/DOWN si besoin. ADD = plus loin que le point connu.',
                'Reread section 06: OT direction, then LEFT/RIGHT, then ADD/DROP, then UP/DOWN if needed. ADD = farther than the known point.'
            )
        );
        $simulation = K::stage(
            K::question(
                'Quelle méthode de localisation et quelle transmission choisissez-vous ?',
                'Which location method and transmission do you choose?',
                [
                    K::text('Grid, en estimant la grille à vue sur la carte papier, sans altitude.', 'Grid, estimating the grid by eye on the paper map, without altitude.'),
                    K::text('Shift depuis AB1001, situé à quatre kilomètres de l’objectif.', 'Shift from AB1001, four kilometres from the target.'),
                    K::text('Polar : « ADJUST FIRE, POLAR, OVER », puis « DIRECTION 2150, DISTANCE 1600, OVER ».', 'Polar: “ADJUST FIRE, POLAR, OVER”, then “DIRECTION 2150, DISTANCE 1600, OVER”.'),
                ],
                2,
                'Marteau connaît votre position exacte et vous disposez d’une direction et d’une distance mesurées : le polar est la méthode la plus juste. Une grille estimée à vue est peu précise, et un shift depuis un point aussi lointain multiplie les erreurs.',
                'Marteau knows your exact position and you have a measured direction and distance: polar is the most accurate method. A grid estimated by eye is imprecise, and a shift from such a distant point multiplies errors.'
            ),
            K::text(
                "SIMULATION AUTONOME — OBSERVATOIRE DANS LE CLOCHER\nVous êtes Hibou 3, posté dans un clocher dont Marteau a reçu la grille exacte au début de la mission. Vos jumelles télémètres ACE donnent une direction de 2150 mils et une distance de 1 600 mètres sur une pièce antichar ennemie. Le seul point connu, AB1001, est à quatre kilomètres de l’objectif. Votre ATAK ne fonctionne plus et vous n’avez qu’une carte papier.\n\nVotre objectif : choisir la méthode de localisation la plus sûre. Aucune aide n’est donnée pendant cette étape ; vous pourrez revoir le cours après une tentative.",
                "INDEPENDENT SIMULATION — OBSERVATION POST IN THE BELL TOWER\nYou are Hibou 3, posted in a bell tower whose exact grid Marteau received at the start of the mission. Your ACE rangefinder binoculars give a direction of 2150 mils and a distance of 1,600 metres to an enemy anti-tank gun. The only known point, AB1001, is four kilometres from the target. Your ATAK has stopped working and you only have a paper map.\n\nYour objective: choose the safest location method. No hint is given during this stage; you may review the course after an attempt."
            )
        );
        $questions = [
            K::question(
                'Qui décide finalement de la manière de traiter l’objectif (unité qui tire, nombre de coups) ?',
                'Who finally decides how the target is engaged (firing unit, number of rounds)?',
                [
                    K::text('La FDC, qui l’annonce dans le message to observer.', 'The FDC, which states it in the message to observer.'),
                    K::text('L’observateur, seul.', 'The observer, alone.'),
                    K::text('Le premier joueur qui répond à la radio.', 'The first player to answer on the radio.'),
                ],
                0,
                'L’observateur peut demander une munition ou un volume de tir ; la FDC tranche selon les moyens et les consignes, puis l’annonce par le MTO.',
                'The observer may request a munition or volume of fire; the FDC decides according to assets and orders, then states it in the MTO.'
            ),
            K::question(
                'Faut-il annoncer « GRID » dans le warning order ?',
                'Should “GRID” be announced in the warning order?',
                [
                    K::text('Oui, toujours, avant le type de mission.', 'Yes, always, before the type of mission.'),
                    K::text('Non : la grille est la méthode standard et n’est pas annoncée ; POLAR et SHIFT le sont.', 'No: grid is the standard method and is not announced; POLAR and SHIFT are.'),
                    K::text('Seulement de nuit.', 'Only at night.'),
                ],
                1,
                'Le warning order annonce la méthode de localisation seulement si elle n’est pas la grille : « ADJUST FIRE, POLAR » ou « ADJUST FIRE, SHIFT AB1001 ».',
                'The warning order states the location method only when it is not grid: “ADJUST FIRE, POLAR” or “ADJUST FIRE, SHIFT AB1001”.'
            ),
            K::question(
                'Quelle précision minimale de grille pour un objectif repéré sur la carte ?',
                'What is the minimum grid precision for a target spotted on the map?',
                [
                    K::text('Quatre chiffres.', 'Four digits.'),
                    K::text('Dix chiffres obligatoires.', 'Ten digits, mandatory.'),
                    K::text('Six chiffres, et huit quand l’ATAK ou le télémètre le permettent.', 'Six digits, and eight when the ATAK or rangefinder allows.'),
                ],
                2,
                'Six chiffres donnent une précision de 100 mètres, le minimum acceptable ; huit chiffres (10 mètres) améliorent la précision du premier coup.',
                'Six digits give 100-metre precision, the minimum acceptable; eight digits (10 metres) improve first-round accuracy.'
            ),
            K::question(
                'Qu’exige la méthode polar ?',
                'What does the polar method require?',
                [
                    K::text('Un point connu à moins de 500 mètres de l’objectif.', 'A known point within 500 metres of the target.'),
                    K::text('Que la FDC connaisse la position exacte de l’observateur.', 'That the FDC knows the observer’s exact position.'),
                    K::text('Que l’objectif soit immobile depuis une heure.', 'That the target has been stationary for an hour.'),
                ],
                1,
                'En polar, l’objectif est situé depuis l’observateur (direction et distance). Sans sa position exacte, la FDC ne peut pas calculer.',
                'In polar, the target is located from the observer (direction and distance). Without the observer’s exact position, the FDC cannot compute.'
            ),
            K::question(
                'Dans un shift, comment est arrondi l’écart en distance (ADD ou DROP) ?',
                'In a shift, how is the range shift (ADD or DROP) rounded?',
                [
                    K::text('Au mètre près.', 'To the nearest metre.'),
                    K::text('Aux 100 mètres près.', 'To the nearest 100 metres.'),
                    K::text('Au kilomètre près.', 'To the nearest kilometre.'),
                ],
                1,
                'L’ATP 3-09.30 arrondit l’écart en distance à 100 mètres ; l’écart latéral est donné en mètres et l’écart vertical seulement s’il est important.',
                'ATP 3-09.30 rounds the range shift to 100 metres; the lateral shift is given in metres and the vertical shift only if significant.'
            ),
            K::question(
                'Que devez-vous avoir réglé avant la mission pour appeler un tir en sécurité ?',
                'What must you have settled before the mission to call fire safely?',
                [
                    K::text('Uniquement la couleur des fumigènes.', 'Only the smoke colours.'),
                    K::text('Rien : tout se règle à la radio pendant le tir.', 'Nothing: everything is sorted out on the radio during the fire mission.'),
                    K::text('Le canal ACRE et les indicatifs, l’unité d’angle, sa propre position, les points connus et la position des amis avec les zones interdites de tir.', 'The ACRE channel and callsigns, the angle unit, own position, known points and friendly positions with no-fire areas.'),
                ],
                2,
                'Ces éléments rendent l’appel rapide et évitent les tirs fratricides. Une unité d’angle non convenue suffit à envoyer les coups au mauvais endroit.',
                'These items make the call quick and prevent fratricide. An angle unit that has not been agreed is enough to send rounds to the wrong place.'
            ),
        ];

        return K::chapter(
            K::text('Chapitre 1 — Rôle du JFO, préparation et localisation de l’objectif', 'Chapter 1 — JFO role, preparation and target location'),
            K::text($theoryFr, $theoryEn),
            K::text($demoFr, $demoEn),
            $guided,
            $simulation,
            $questions,
            K::text(
                "Rédigez un retour personnel sur le cas du clocher et votre évaluation :\n1. La méthode de localisation choisie et pourquoi.\n2. Ce qui aurait rendu les autres méthodes plus sûres.\n3. Les éléments que vous préparez avant chaque mission comme observateur.\n4. Comment vous vérifiez une localisation avant de l’envoyer.\n5. Une amélioration concrète à tester lors de votre prochaine mission.",
                "Write a personal reflection on the bell tower case and your assessment:\n1. The location method chosen and why.\n2. What would have made the other methods safer.\n3. The items you prepare before each mission as an observer.\n4. How you check a location before sending it.\n5. One concrete improvement to test in your next mission."
            ),
            [
                K::text('Les rôles de l’observateur et de la FDC sont distingués.', 'The roles of the observer and the FDC are distinguished.'),
                K::text('Les trois méthodes de localisation sont expliquées avec leurs conditions.', 'The three location methods are explained with their conditions.'),
                K::text('La préparation avant mission est concrète (réseau, angles, points, amis).', 'Pre-mission preparation is concrete (net, angles, points, friendlies).'),
                K::text('Amélioration concrète et vérifiable en mission.', 'Concrete improvement that can be checked in a mission.'),
            ],
            [
                K::text('L’évaluation atteint au moins 80 % dans le délai prévu.', 'The assessment reaches at least 80% within the time limit.'),
                K::text('Le stagiaire localise un objectif de jeu par grid, polar et shift.', 'The learner locates a game target by grid, polar and shift.'),
                K::text('L’unité d’angle et la position des amis sont vérifiées avant l’appel.', 'The angle unit and friendly positions are checked before the call.'),
                K::text('Le stagiaire explique que la FDC décide et l’annonce dans le MTO.', 'The learner explains that the FDC decides and states it in the MTO.'),
            ],
            [
                K::sheet(
                    K::text('Fiche mémo — Préparation et localisation', 'Memo card — Preparation and location'),
                    K::text(
                        "FORMATION DE JEU (ARMA 3) — sans valeur de qualification réelle.\n\nAVANT LA MISSION\nCanal ACRE du réseau feux · indicatifs observateur et FDC · unité d’angle (mils 6400 ou degrés, annoncée) · position exacte (ATAK / MicroDAGR) · points connus et cibles enregistrées (numéros) · amis, limites de secteur, zones interdites de tir.\n\nRÔLES\nObservateur = localise, décrit, corrige. FDC = calcule (tables ACE), décide, annonce le MTO. Pièces = tirent.\n\nGRID (standard, non annoncé)\n6 chiffres minimum, 8 si possible · altitude après la grille · direction OT envoyée avec le collationnement du MTO.\n\nPOLAR (annoncé « POLAR »)\nDirection · distance · UP/DOWN si besoin. La FDC connaît la position de l’observateur.\n\nSHIFT (annoncé « SHIFT » + point)\nDirection OT · LEFT/RIGHT en mètres · ADD/DROP aux 100 m · UP/DOWN si important.\n\nCYCLE : observer · rendre compte · coordonner · préparer · exécuter · évaluer.",
                        "IN-GAME TRAINING (ARMA 3) — no real-world qualification value.\n\nBEFORE THE MISSION\nACRE fires net channel · observer and FDC callsigns · angle unit (mils 6400 or degrees, stated) · exact position (ATAK / MicroDAGR) · known points and recorded targets (numbers) · friendlies, sector limits, no-fire areas.\n\nROLES\nObserver = locates, describes, corrects. FDC = computes (ACE tables), decides, sends the MTO. Guns = fire.\n\nGRID (standard, not announced)\n6 digits minimum, 8 if possible · altitude after the grid · OT direction sent with the MTO readback.\n\nPOLAR (announced “POLAR”)\nDirection · distance · UP/DOWN if needed. The FDC knows the observer’s position.\n\nSHIFT (announced “SHIFT” + point)\nOT direction · LEFT/RIGHT in metres · ADD/DROP to 100 m · UP/DOWN if significant.\n\nCYCLE: observe · report · coordinate · prepare · execute · assess."
                    )
                ),
            ]
        );
    }

    // ------------------------------------------------------------------ Chapitre 2 — Trois transmissions et MTO

    /** @return array<string, mixed> */
    private static function chapterTwo(): array
    {
        $theoryFr = <<<'FR'
01 — Six éléments, trois transmissions
L’appel de feu contient six éléments : identification de l’observateur, warning order, localisation de l’objectif, description de l’objectif, méthode d’engagement, méthode de tir et de contrôle. Ils sont envoyés en trois transmissions, chacune terminée par « OVER » et collationnée par la FDC avant la suivante : 1) identification et warning order ; 2) localisation ; 3) description, méthode d’engagement, méthode de tir et de contrôle. Envoyez chaque transmission dès qu’elle est prête : la FDC commence à travailler pendant que vous finissez.

02 — Première transmission : identification et warning order
« Marteau, ici Hibou 3, ADJUST FIRE, OVER. » Le warning order libère le réseau et donne le type de mission :
- ADJUST FIRE : la localisation est incertaine, un réglage est nécessaire.
- FIRE FOR EFFECT : vous êtes sûr de la localisation, le premier tir doit produire l’effet. Visez-le chaque fois que c’est possible.
- SUPPRESS, suivi du numéro de cible : tir rapide sur une cible préparée, souvent avec une durée.
- IMMEDIATE SUPPRESSION ou IMMEDIATE SMOKE : des amis sont pris sous le feu ; l’appel tient alors en une seule transmission, avec la localisation.
Le warning order ajoute POLAR ou SHIFT si la méthode n’est pas la grille.

03 — Deuxième transmission : localisation
Grille et altitude, ou direction et distance (polar), ou direction et écarts (shift), selon le chapitre 1.

04 — Troisième transmission : description
Assez précise pour que la FDC choisisse munition et volume : ce qu’est l’objectif (infanterie, véhicules, mortier), ce qu’il fait (s’enterre, se regroupe), combien, sa protection (à découvert, retranché), et sa taille ou forme si elle compte. Soyez bref mais exact.

05 — Méthodes d’engagement, de tir et de contrôle
Méthode d’engagement : DANGER CLOSE si les impacts peuvent tomber à moins de 600 mètres d’amis (voir chapitre 3), MARK pour un coup de repérage, trajectoire (les mortiers ne tirent qu’en tir courbe), munition souhaitée parmi celles de la mission (explosif, fumigène, éclairant). Méthode de contrôle : sans mention, la FDC tire dès qu’elle est prête (fire when ready). AT MY COMMAND : la FDC annonce « READY » et attend votre « FIRE ». TIME ON TARGET : impact à l’heure donnée, montres synchronisées. CANNOT OBSERVE : vous ne verrez pas les impacts.

06 — Le message to observer (MTO)
La FDC répond par le MTO : unité qui tire, changements apportés à votre demande, nombre de coups par pièce en tir d’efficacité, numéro de cible. Collationnez-le en entier ; dans une mission par grille, ajoutez « BREAK, DIRECTION » et la direction OT. Si vous ou la FDC vous trompez, annoncez « CORRECTION » et renvoyez l’élément exact, avec tout ce qui en dépend.

07 — Ce qu’il faut retenir
Trois transmissions, chacune collationnée ; un type de mission adapté ; une description utile ; DANGER CLOSE dès que nécessaire ; un MTO collationné en entier, suivi de la direction OT.
FR;
        $theoryEn = <<<'EN'
01 — Six elements, three transmissions
The call for fire has six elements: observer identification, warning order, target location, target description, method of engagement, method of fire and control. They are sent in three transmissions, each ending with “OVER” and read back by the FDC before the next: 1) identification and warning order; 2) location; 3) description, method of engagement, method of fire and control. Send each transmission as soon as it is ready: the FDC starts work while you finish.

02 — First transmission: identification and warning order
“Marteau, this is Hibou 3, ADJUST FIRE, OVER.” The warning order clears the net and gives the type of mission:
- ADJUST FIRE: the location is uncertain, adjustment is needed.
- FIRE FOR EFFECT: you are sure of the location, the first volley should achieve the effect. Aim for it whenever possible.
- SUPPRESS, followed by the target number: quick fire on a planned target, often with a duration.
- IMMEDIATE SUPPRESSION or IMMEDIATE SMOKE: friendlies are under fire; the call is then a single transmission, including the location.
The warning order adds POLAR or SHIFT if the method is not grid.

03 — Second transmission: location
Grid and altitude, or direction and distance (polar), or direction and shifts (shift), as in chapter 1.

04 — Third transmission: description
Precise enough for the FDC to choose munition and volume: what the target is (infantry, vehicles, mortar), what it is doing (digging in, assembling), how many, its protection (in the open, dug in), and its size or shape if it matters. Be brief but accurate.

05 — Methods of engagement, fire and control
Method of engagement: DANGER CLOSE if impacts may fall within 600 metres of friendlies (see chapter 3), MARK for a marking round, trajectory (mortars only fire high angle), munition requested from those in the mission (high explosive, smoke, illumination). Method of control: with no mention, the FDC fires when ready. AT MY COMMAND: the FDC reports “READY” and waits for your “FIRE”. TIME ON TARGET: impact at the stated time, watches synchronised. CANNOT OBSERVE: you will not see the impacts.

06 — The message to observer (MTO)
The FDC replies with the MTO: unit to fire, changes to your request, number of rounds per gun in fire for effect, target number. Read it back in full; in a grid mission, add “BREAK, DIRECTION” and the OT direction. If you or the FDC make a mistake, say “CORRECTION” and resend the correct element, with everything that depends on it.

07 — Key points
Three transmissions, each read back; a suitable mission type; a useful description; DANGER CLOSE whenever needed; an MTO read back in full, followed by the OT direction.
EN;
        $demoFr = <<<'FR'
DÉMONSTRATION COMMENTÉE — APPEL DE FEU SUR LA FERME
Situation de jeu : suite du chapitre 1. Hibou 3 appelle Marteau sur le canal ACRE du réseau feux. Objectif : une section ennemie qui s’enterre près de la ferme, grille 052 131, altitude 85.

Hibou 3 : « Marteau, ici Hibou 3, ADJUST FIRE, OVER. »
Marteau : « Hibou 3, ici Marteau, ADJUST FIRE, OUT. »
Commentaire : première transmission. La grille n’est pas annoncée, c’est la méthode standard. Hibou 3 règle, car sa grille ATAK n’a pas été confirmée au télémètre.

Hibou 3 : « GRID 052 131, ALTITUDE 85, OVER. »
Marteau : « GRID 052 131, ALTITUDE 85, OUT. »
Commentaire : deuxième transmission, collationnée mot pour mot.

Hibou 3 : « Infanterie, une section, s’enterre, à découvert. HE. OVER. »
Marteau : « Infanterie, une section, s’enterre, à découvert, HE, OUT. »
Commentaire : troisième transmission. Pas de mention de contrôle : tir dès que prêt. Pas de DANGER CLOSE : les amis sont à 1 200 mètres.

Marteau : « MTO : MARTEAU, 3 ROUNDS, TARGET AB1003, OVER. »
Hibou 3 : « MARTEAU, 3 ROUNDS, TARGET AB1003, BREAK, DIRECTION 0800, OVER. »
Marteau : « DIRECTION 0800, OUT. »
Commentaire : Hibou 3 collationne le MTO en entier et ajoute la direction OT, dont la FDC aura besoin pour appliquer ses corrections.

À RETENIR
Trois transmissions courtes, chacune collationnée, puis un MTO collationné avec la direction OT. Le premier coup de réglage peut partir.
FR;
        $demoEn = <<<'EN'
ANNOTATED DEMONSTRATION — CALL FOR FIRE ON THE FARM
Game situation: continuation of chapter 1. Hibou 3 calls Marteau on the ACRE fires net channel. Target: an enemy section digging in near the farm, grid 052 131, altitude 85.

Hibou 3: “Marteau, this is Hibou 3, ADJUST FIRE, OVER.”
Marteau: “Hibou 3, this is Marteau, ADJUST FIRE, OUT.”
Comment: first transmission. Grid is not announced, it is the standard method. Hibou 3 adjusts because the ATAK grid has not been confirmed with the rangefinder.

Hibou 3: “GRID 052 131, ALTITUDE 85, OVER.”
Marteau: “GRID 052 131, ALTITUDE 85, OUT.”
Comment: second transmission, read back word for word.

Hibou 3: “Infantry, one section, digging in, in the open. HE. OVER.”
Marteau: “Infantry, one section, digging in, in the open, HE, OUT.”
Comment: third transmission. No control stated: fire when ready. No DANGER CLOSE: friendlies are 1,200 metres away.

Marteau: “MTO: MARTEAU, 3 ROUNDS, TARGET AB1003, OVER.”
Hibou 3: “MARTEAU, 3 ROUNDS, TARGET AB1003, BREAK, DIRECTION 0800, OVER.”
Marteau: “DIRECTION 0800, OUT.”
Comment: Hibou 3 reads back the full MTO and adds the OT direction, which the FDC needs to apply corrections.

KEY POINT
Three short transmissions, each read back, then an MTO read back with the OT direction. The first adjusting round can be fired.
EN;
        $guided = K::stage(
            K::question(
                'Quel appel envoyez-vous ?',
                'Which call do you send?',
                [
                    K::text('« Marteau, ici Hibou 3, ADJUST FIRE, OVER », puis les deux autres transmissions.', '“Marteau, this is Hibou 3, ADJUST FIRE, OVER”, then the other two transmissions.'),
                    K::text('« Marteau, ici Hibou 3, IMMEDIATE SUPPRESSION, GRID 061 140, OVER », en une seule transmission.', '“Marteau, this is Hibou 3, IMMEDIATE SUPPRESSION, GRID 061 140, OVER”, in a single transmission.'),
                    K::text('« Marteau, ici Hibou 3, SUPPRESS AB1001, OVER. »', '“Marteau, this is Hibou 3, SUPPRESS AB1001, OVER.”'),
                ],
                1,
                'Des amis sont pris sous le feu : c’est le cas de l’IMMEDIATE SUPPRESSION, envoyée en une seule transmission avec la localisation. Un réglage complet serait trop lent ; SUPPRESS AB1001 viserait le carrefour, pas la mitrailleuse.',
                'Friendlies are under fire: this is the case for IMMEDIATE SUPPRESSION, sent in a single transmission with the location. A full adjustment would be too slow; SUPPRESS AB1001 would target the crossroads, not the machine gun.'
            ),
            K::text(
                "CAS GUIDÉ — VIKING 2 CLOUÉ AU SOL\nVous êtes Hibou 3. Le groupe Viking 2 traverse un champ quand une mitrailleuse ennemie ouvre le feu depuis une haie, grille 061 140 lue dans l’ATAK. Viking 2 est cloué au sol à 800 mètres de la haie et demande un tir tout de suite. La haie n’est pas une cible enregistrée ; la seule cible enregistrée, AB1001, est le carrefour, à un kilomètre de là.\n\nTravaillez dans cet ordre :\n1. Rappelez les quatre types de mission du warning order.\n2. Identifiez celui qui correspond à des amis pris sous le feu.\n3. Rappelez combien de transmissions il comporte.\n4. Choisissez l’appel correct.",
                "GUIDED CASE — VIKING 2 PINNED DOWN\nYou are Hibou 3. Squad Viking 2 is crossing a field when an enemy machine gun opens fire from a hedgerow, grid 061 140 read from the ATAK. Viking 2 is pinned down 800 metres from the hedgerow and asks for fire right now. The hedgerow is not a recorded target; the only recorded target, AB1001, is the crossroads, a kilometre away.\n\nWork in this order:\n1. Recall the four mission types of the warning order.\n2. Identify the one that matches friendlies under fire.\n3. Recall how many transmissions it involves.\n4. Choose the correct call."
            ),
            K::text(
                'Relisez la section 02 : IMMEDIATE SUPPRESSION ou IMMEDIATE SMOKE quand des amis sont pris sous le feu, en une seule transmission.',
                'Reread section 02: IMMEDIATE SUPPRESSION or IMMEDIATE SMOKE when friendlies are under fire, in a single transmission.'
            )
        );
        $simulation = K::stage(
            K::question(
                'Que répondez-vous au MTO ?',
                'How do you answer the MTO?',
                [
                    K::text('« FIRE, OVER. »', '“FIRE, OVER.”'),
                    K::text('« Reçu, OUT. »', '“Copy, OUT.”'),
                    K::text('« MARTEAU, 2 ROUNDS, TARGET AB1004, BREAK, DIRECTION 5400, OVER. »', '“MARTEAU, 2 ROUNDS, TARGET AB1004, BREAK, DIRECTION 5400, OVER.”'),
                ],
                2,
                'Le MTO se collationne en entier et, dans une mission par grille, on ajoute la direction OT. « FIRE » ne sert qu’après « READY » dans une mission AT MY COMMAND ; un simple « reçu » ne permet pas de détecter une erreur.',
                'The MTO is read back in full and, in a grid mission, the OT direction is added. “FIRE” is only used after “READY” in an AT MY COMMAND mission; a plain “copy” does not allow an error to be caught.'
            ),
            K::text(
                "SIMULATION AUTONOME — LE CAMION AU GUÉ\nVous êtes Hibou 3. Vous avez envoyé un appel de feu complet par grille sur un camion ennemi arrêté à un gué, sans demander AT MY COMMAND. Votre direction vers l’objectif est 5400 mils. Marteau répond : « MTO : MARTEAU, 2 ROUNDS, TARGET AB1004, OVER. »\n\nVotre objectif : répondre correctement au MTO. Aucune aide n’est donnée pendant cette étape ; vous pourrez revoir le cours après une tentative.",
                "INDEPENDENT SIMULATION — THE TRUCK AT THE FORD\nYou are Hibou 3. You have sent a complete grid call for fire on an enemy truck stopped at a ford, without requesting AT MY COMMAND. Your direction to the target is 5400 mils. Marteau replies: “MTO: MARTEAU, 2 ROUNDS, TARGET AB1004, OVER.”\n\nYour objective: answer the MTO correctly. No hint is given during this stage; you may review the course after an attempt."
            )
        );
        $questions = [
            K::question(
                'Quelles sont les trois transmissions de l’appel de feu, dans l’ordre ?',
                'What are the three transmissions of the call for fire, in order?',
                [
                    K::text('Identification et warning order ; localisation ; description, méthode d’engagement, méthode de tir et de contrôle.', 'Identification and warning order; location; description, method of engagement, method of fire and control.'),
                    K::text('Localisation ; identification ; MTO.', 'Location; identification; MTO.'),
                    K::text('Description ; warning order ; fin de mission.', 'Description; warning order; end of mission.'),
                ],
                0,
                'Chaque transmission est collationnée par la FDC avant la suivante. Le MTO est la réponse de la FDC, pas une transmission de l’observateur.',
                'Each transmission is read back by the FDC before the next. The MTO is the FDC’s reply, not one of the observer’s transmissions.'
            ),
            K::question(
                'Quand annoncer FIRE FOR EFFECT plutôt qu’ADJUST FIRE ?',
                'When should you announce FIRE FOR EFFECT rather than ADJUST FIRE?',
                [
                    K::text('Toujours, pour gagner du temps.', 'Always, to save time.'),
                    K::text('Quand vous êtes sûr de la localisation et que le premier tir doit produire l’effet recherché.', 'When you are sure of the location and the first volley should achieve the desired effect.'),
                    K::text('Seulement quand la FDC le demande.', 'Only when the FDC asks for it.'),
                ],
                1,
                'L’observateur vise le tir d’efficacité au premier coup quand sa localisation est sûre ; sinon il règle, car un tir d’efficacité mal placé gaspille des munitions et peut toucher des amis.',
                'The observer aims for first-round fire for effect when the location is reliable; otherwise they adjust, because a misplaced fire for effect wastes ammunition and may hit friendlies.'
            ),
            K::question(
                'Comment une IMMEDIATE SUPPRESSION est-elle transmise ?',
                'How is IMMEDIATE SUPPRESSION sent?',
                [
                    K::text('En trois transmissions, comme un réglage normal.', 'In three transmissions, like a normal adjustment.'),
                    K::text('Par écrit dans l’ATAK uniquement.', 'In writing in the ATAK only.'),
                    K::text('En une seule transmission, avec la localisation de l’objectif.', 'In a single transmission, with the target location.'),
                ],
                2,
                'Quand des amis sont pris sous le feu, l’appel est raccourci : type de mission et localisation dans la même transmission.',
                'When friendlies are under fire, the call is shortened: mission type and location in the same transmission.'
            ),
            K::question(
                'Que doit contenir la description de l’objectif ?',
                'What should the target description contain?',
                [
                    K::text('Seulement « ennemis ».', 'Only “enemy”.'),
                    K::text('La grille répétée trois fois.', 'The grid repeated three times.'),
                    K::text('La nature, l’activité, le nombre, la protection et, si utile, la taille ou la forme.', 'Its nature, activity, number, protection and, if useful, size or shape.'),
                ],
                2,
                'La FDC choisit la munition et le volume de tir d’après la description : une section retranchée ne se traite pas comme un camion à découvert.',
                'The FDC chooses the munition and volume of fire from the description: a dug-in section is not engaged like a truck in the open.'
            ),
            K::question(
                'Dans une mission AT MY COMMAND, que se passe-t-il ?',
                'In an AT MY COMMAND mission, what happens?',
                [
                    K::text('La FDC tire dès que les pièces sont prêtes.', 'The FDC fires as soon as the guns are ready.'),
                    K::text('La FDC annonce « READY » et attend que l’observateur dise « FIRE ».', 'The FDC reports “READY” and waits for the observer to say “FIRE”.'),
                    K::text('L’observateur tire lui-même.', 'The observer fires the gun themselves.'),
                ],
                1,
                'AT MY COMMAND laisse à l’observateur le choix du moment ; il reste en vigueur jusqu’à « CANCEL AT MY COMMAND ». Sans mention, c’est fire when ready.',
                'AT MY COMMAND leaves the timing to the observer; it stays in force until “CANCEL AT MY COMMAND”. With no mention, it is fire when ready.'
            ),
            K::question(
                'Quels éléments contient le message to observer ?',
                'What does the message to observer contain?',
                [
                    K::text('La position des amis et la météo.', 'Friendly positions and the weather.'),
                    K::text('La description de l’objectif répétée.', 'The target description repeated.'),
                    K::text('L’unité qui tire, les changements à la demande, le nombre de coups, le numéro de cible.', 'Unit to fire, changes to the request, number of rounds, target number.'),
                ],
                2,
                'Le MTO annonce la décision de la FDC. L’observateur le collationne en entier et ajoute la direction OT dans une mission par grille.',
                'The MTO states the FDC’s decision. The observer reads it back in full and adds the OT direction in a grid mission.'
            ),
            K::question(
                'Vous vous apercevez que vous avez envoyé « DROP 400 » au lieu de « ADD 400 » dans « LEFT 200, DROP 400 ». Que transmettez-vous ?',
                'You realise you sent “DROP 400” instead of “ADD 400” in “LEFT 200, DROP 400”. What do you send?',
                [
                    K::text('« CORRECTION, LEFT 200, ADD 400, OVER. »', '“CORRECTION, LEFT 200, ADD 400, OVER.”'),
                    K::text('« CORRECTION, ADD 400, OVER. »', '“CORRECTION, ADD 400, OVER.”'),
                    K::text('Rien, vous corrigerez au coup suivant.', 'Nothing, you will correct on the next round.'),
                ],
                0,
                'L’ATP 3-09.30 demande de renvoyer tout l’élément concerné : un « CORRECTION, ADD 400 » seul annulerait le LEFT 200.',
                'ATP 3-09.30 requires the whole affected element to be resent: “CORRECTION, ADD 400” alone would cancel the LEFT 200.'
            ),
        ];

        return K::chapter(
            K::text('Chapitre 2 — L’appel de feu en trois transmissions et le MTO', 'Chapter 2 — The call for fire in three transmissions and the MTO'),
            K::text($theoryFr, $theoryEn),
            K::text($demoFr, $demoEn),
            $guided,
            $simulation,
            $questions,
            K::text(
                "Rédigez un retour personnel sur les cas Viking 2 et du gué, et votre évaluation :\n1. L’appel de feu complet que vous auriez envoyé pour le camion au gué.\n2. Le type de mission choisi dans chaque cas et pourquoi.\n3. La façon dont vous collationnez le MTO.\n4. Une erreur radio que vous risquez de commettre et comment la corriger.\n5. Une amélioration concrète à tester lors de votre prochaine mission.",
                "Write a personal reflection on the Viking 2 and ford cases, and your assessment:\n1. The full call for fire you would have sent for the truck at the ford.\n2. The mission type chosen in each case and why.\n3. How you read back the MTO.\n4. A radio error you are likely to make and how to correct it.\n5. One concrete improvement to test in your next mission."
            ),
            [
                K::text('Les six éléments et les trois transmissions sont restitués dans l’ordre.', 'The six elements and three transmissions are restated in order.'),
                K::text('Le choix du type de mission est justifié par la situation.', 'The choice of mission type is justified by the situation.'),
                K::text('Le collationnement du MTO et la procédure « CORRECTION » sont expliqués.', 'The MTO readback and the “CORRECTION” procedure are explained.'),
                K::text('Amélioration concrète et vérifiable en mission.', 'Concrete improvement that can be checked in a mission.'),
            ],
            [
                K::text('L’évaluation atteint au moins 80 % dans le délai prévu.', 'The assessment reaches at least 80% within the time limit.'),
                K::text('Le stagiaire transmet un appel de feu complet en trois transmissions sur ACRE.', 'The learner sends a complete call for fire in three transmissions over ACRE.'),
                K::text('Le stagiaire emploie IMMEDIATE SUPPRESSION à bon escient, en une transmission.', 'The learner uses IMMEDIATE SUPPRESSION appropriately, in one transmission.'),
                K::text('Le MTO est collationné en entier avec la direction OT.', 'The MTO is read back in full with the OT direction.'),
            ],
            [
                K::sheet(
                    K::text('Carte appel de feu', 'Call for fire card'),
                    K::text(
                        "FORMATION DE JEU (ARMA 3) — sans valeur de qualification réelle.\n\nTRANSMISSION 1 — IDENTIFICATION + WARNING ORDER\n« [FDC], ici [observateur], ADJUST FIRE / FIRE FOR EFFECT / SUPPRESS [n° cible] (+ POLAR / SHIFT [point]), OVER. »\nIMMEDIATE SUPPRESSION / IMMEDIATE SMOKE = une seule transmission avec la localisation.\n\nTRANSMISSION 2 — LOCALISATION\nGrid + altitude · ou direction + distance (polar) · ou direction + LEFT/RIGHT + ADD/DROP (+ UP/DOWN) (shift).\n\nTRANSMISSION 3 — DESCRIPTION + ENGAGEMENT + TIR ET CONTRÔLE\nQuoi · que fait-il · combien · protection · taille/forme.\nDANGER CLOSE (< 600 m d’amis) · MARK · munition · (mortier = tir courbe).\nFire when ready (par défaut) · AT MY COMMAND (READY → FIRE) · TIME ON TARGET · CANNOT OBSERVE.\n\nMTO (FDC)\nUnité qui tire · changements · nombre de coups · numéro de cible.\nCollationner en entier + « BREAK, DIRECTION [OT] » (mission par grille).\n\nERREUR : « CORRECTION » + élément exact et tout ce qui en dépend.",
                        "IN-GAME TRAINING (ARMA 3) — no real-world qualification value.\n\nTRANSMISSION 1 — IDENTIFICATION + WARNING ORDER\n“[FDC], this is [observer], ADJUST FIRE / FIRE FOR EFFECT / SUPPRESS [target no.] (+ POLAR / SHIFT [point]), OVER.”\nIMMEDIATE SUPPRESSION / IMMEDIATE SMOKE = single transmission with the location.\n\nTRANSMISSION 2 — LOCATION\nGrid + altitude · or direction + distance (polar) · or direction + LEFT/RIGHT + ADD/DROP (+ UP/DOWN) (shift).\n\nTRANSMISSION 3 — DESCRIPTION + ENGAGEMENT + FIRE AND CONTROL\nWhat · doing what · how many · protection · size/shape.\nDANGER CLOSE (< 600 m from friendlies) · MARK · munition · (mortar = high angle).\nFire when ready (default) · AT MY COMMAND (READY → FIRE) · TIME ON TARGET · CANNOT OBSERVE.\n\nMTO (FDC)\nUnit to fire · changes · number of rounds · target number.\nRead back in full + “BREAK, DIRECTION [OT]” (grid mission).\n\nERROR: “CORRECTION” + correct element and everything that depends on it."
                    )
                ),
            ]
        );
    }

    // ------------------------------------------------------------------ Chapitre 3 — Réglage, tir d’efficacité, fin de mission et sécurité

    /** @return array<string, mixed> */
    private static function chapterThree(): array
    {
        $theoryFr = <<<'FR'
01 — Shot, splash, rounds complete
La FDC annonce « SHOT » après chaque coup tiré en réglage et après la première salve d’efficacité ; l’observateur répond « SHOT, OUT ». « SPLASH » est annoncé cinq secondes avant l’impact : systématiquement en tir courbe (donc au mortier), et à la demande ; l’observateur répond « SPLASH, OUT » et regarde la zone. « ROUNDS COMPLETE » signale que tous les coups d’efficacité sont partis.

02 — Observer l’impact (spotting)
L’observateur situe l’impact par rapport au point de réglage, le long de la ligne observateur-objectif. D’abord la distance : OVER (au-delà), SHORT (en deçà), TARGET (sur l’objectif), DOUBTFUL (vu mais impossible à classer), LOST (ni vu ni entendu). Puis la déviation : LINE (sur la ligne), LEFT ou RIGHT, mesurée en mils aux jumelles. Une fumée qui dérive aide parfois à trancher.

03 — Corriger
Les corrections sont données en mètres, à l’inverse de l’observation, dans l’ordre : direction OT si elle a changé de plus de 100 mils, puis déviation, puis distance. Déviation : mils observés multipliés par la distance observateur-objectif en kilomètres ; 20 mils à droite à 3 km donnent « LEFT 60 ». Une déviation de moins de 30 mètres ne s’envoie pas. Distance : un coup SHORT appelle « ADD », un coup OVER appelle « DROP ». Si l’équipe travaille en degrés, estimez l’écart latéral en mètres sur la carte ou l’ATAK.

04 — Encadrer (fourchette)
Fourchette successive : obtenir un coup au-delà et un coup en deçà, avec des bonds de 800, 400, 200 puis 100 mètres, puis couper la fourchette en deux à chaque coup. Quand la fourchette de 100 mètres est coupée (« ADD 50 » ou « DROP 50 »), demandez « FIRE FOR EFFECT ». Fourchette rapide : la première fourchette sert d’étalon pour corriger directement sur l’objectif et tirer pour l’effet. Avec un télémètre, un seul coup de réglage peut suffire. Chaque coup de réglage prévient l’ennemi : soyez aussi bref que la précision le permet.

05 — Tir d’efficacité et fin de mission
Après le tir d’efficacité, observez. Effet suffisant : « END OF MISSION » suivi des effets observés, par exemple « section ennemie dispersée, deux véhicules détruits ». Effet insuffisant : corrections puis « REPEAT ». « RECORD AS TARGET » enregistre l’objectif pour plus tard. Un coup perdu (LOST) impose de vérifier les données, puis de demander un coup fumigène ou de reprendre prudemment.

06 — Sécurité
DANGER CLOSE figure dans la méthode d’engagement quand l’objectif ou les impacts sont à moins de 600 mètres d’amis, pour mortiers et artillerie. Réglez alors en rampant (creeping fire) : partez d’un point plus éloigné des amis et rapprochez les coups par corrections de 100 mètres au plus, à partir de l’impact le plus proche des amis ; annoncez « CANCEL DANGER CLOSE » si le tir s’en éloigne. « CHECK FIRING » arrête immédiatement le tir : quiconque voit un danger l’annonce. Respectez les limites de secteur, les zones interdites de tir et les lignes de coordination du briefing ; vérifiez les amis sur l’ATAK et prévenez le JTAC ou les pilotes si des aéronefs passent près des trajectoires.

07 — Ce qu’il faut retenir
Shot, splash, observation, correction inverse en mètres, fourchette coupée à 100 mètres, tir d’efficacité, END OF MISSION avec effets. Danger close à moins de 600 mètres, réglage en rampant, check firing sans hésiter.
FR;
        $theoryEn = <<<'EN'
01 — Shot, splash, rounds complete
The FDC reports “SHOT” after each round fired in adjustment and after the first fire for effect volley; the observer answers “SHOT, OUT”. “SPLASH” is reported five seconds before impact: always in high-angle fire (so with mortars), and on request; the observer answers “SPLASH, OUT” and watches the area. “ROUNDS COMPLETE” means all fire for effect rounds have been fired.

02 — Spotting the impact
The observer places the impact relative to the adjusting point, along the observer-target line. Range first: OVER (beyond), SHORT (this side), TARGET (on the target), DOUBTFUL (seen but cannot be classed), LOST (neither seen nor heard). Then deviation: LINE (on the line), LEFT or RIGHT, measured in mils with binoculars. Drifting smoke sometimes helps decide.

03 — Correcting
Corrections are given in metres, opposite to the spotting, in this order: OT direction if it has changed by more than 100 mils, then deviation, then range. Deviation: mils observed multiplied by the observer-target distance in kilometres; 20 mils right at 3 km gives “LEFT 60”. A deviation of less than 30 metres is not sent. Range: a SHORT round calls for “ADD”, an OVER round for “DROP”. If the team works in degrees, estimate the lateral offset in metres on the map or ATAK.

04 — Bracketing
Successive bracketing: get one round over and one short, with bounds of 800, 400, 200 then 100 metres, then split the bracket in half with each round. When the 100-metre bracket is split (“ADD 50” or “DROP 50”), call “FIRE FOR EFFECT”. Hasty bracketing: the first bracket serves as a yardstick to correct straight onto the target and fire for effect. With a rangefinder, a single adjusting round may be enough. Every adjusting round warns the enemy: be as brief as accuracy allows.

05 — Fire for effect and end of mission
After the fire for effect, observe. Sufficient effect: “END OF MISSION” followed by the observed effects, for example “enemy section dispersed, two vehicles destroyed”. Insufficient effect: corrections then “REPEAT”. “RECORD AS TARGET” records the target for later. A lost round requires checking the data, then asking for a smoke round or resuming carefully.

06 — Safety
DANGER CLOSE is included in the method of engagement when the target or impacts are within 600 metres of friendlies, for mortars and artillery. Then adjust by creeping fire: start from a point farther from the friendlies and bring the rounds closer with corrections of 100 metres or less, from the impact closest to the friendlies; say “CANCEL DANGER CLOSE” if fire moves away. “CHECK FIRING” stops fire immediately: anyone who sees a danger calls it. Respect sector limits, no-fire areas and coordination lines from the briefing; check friendlies on the ATAK and warn the JTAC or pilots if aircraft pass near the trajectories.

07 — Key points
Shot, splash, spotting, opposite correction in metres, bracket split at 100 metres, fire for effect, END OF MISSION with effects. Danger close within 600 metres, creeping adjustment, check firing without hesitation.
EN;
        $demoFr = <<<'FR'
DÉMONSTRATION COMMENTÉE — RÉGLAGE SUR AB1003
Situation de jeu : suite du chapitre 2. Hibou 3 est à 1 300 mètres de l’objectif (facteur observateur-objectif : 1). Marteau tire au mortier, donc en tir courbe.

Marteau : « SHOT, OVER. » Hibou 3 : « SHOT, OUT. »
Marteau : « SPLASH, OVER. » Hibou 3 : « SPLASH, OUT. »
Commentaire : au mortier, le splash est toujours annoncé ; Hibou 3 regarde la zone cinq secondes avant l’impact.

Hibou 3 : « LEFT 30, DROP 400, OVER. »
Commentaire : le coup est tombé au-delà et 30 mils à droite. 30 mils × 1 = 30 mètres, à corriger vers la gauche ; DROP 400 pour obtenir un coup en deçà.

Hibou 3 : « ADD 200, OVER. »
Commentaire : deuxième coup en deçà, sur la ligne. La fourchette de 400 mètres est établie ; on la coupe en deux.

Hibou 3 : « DROP 100, OVER. »
Commentaire : troisième coup au-delà : fourchette de 200 mètres, coupée à nouveau.

Hibou 3 : « ADD 50, FIRE FOR EFFECT, OVER. »
Commentaire : quatrième coup en deçà : la fourchette de 100 mètres est coupée, on passe au tir d’efficacité.

Marteau : « SHOT… ROUNDS COMPLETE, OVER. » Hibou 3 : « ROUNDS COMPLETE, OUT. »
Hibou 3 : « END OF MISSION, section ennemie dispersée, trois ennemis neutralisés, OVER. »
Commentaire : la fin de mission comprend toujours les effets observés : la FDC et le chef savent si l’objectif est traité.

À RETENIR
Une fourchette se construit avec des bonds de 400, 200, 100 mètres puis 50 ; le tir d’efficacité part quand la fourchette de 100 mètres est coupée. La mission se clôt par les effets observés.
FR;
        $demoEn = <<<'EN'
ANNOTATED DEMONSTRATION — ADJUSTING ON AB1003
Game situation: continuation of chapter 2. Hibou 3 is 1,300 metres from the target (observer-target factor: 1). Marteau fires mortars, so high angle.

Marteau: “SHOT, OVER.” Hibou 3: “SHOT, OUT.”
Marteau: “SPLASH, OVER.” Hibou 3: “SPLASH, OUT.”
Comment: with mortars, splash is always reported; Hibou 3 watches the area five seconds before impact.

Hibou 3: “LEFT 30, DROP 400, OVER.”
Comment: the round landed beyond and 30 mils right. 30 mils × 1 = 30 metres, to correct to the left; DROP 400 to get a round short.

Hibou 3: “ADD 200, OVER.”
Comment: second round short, on the line. The 400-metre bracket is established; split it in half.

Hibou 3: “DROP 100, OVER.”
Comment: third round over: 200-metre bracket, split again.

Hibou 3: “ADD 50, FIRE FOR EFFECT, OVER.”
Comment: fourth round short: the 100-metre bracket is split, move to fire for effect.

Marteau: “SHOT… ROUNDS COMPLETE, OVER.” Hibou 3: “ROUNDS COMPLETE, OUT.”
Hibou 3: “END OF MISSION, enemy section dispersed, three enemy neutralised, OVER.”
Comment: end of mission always includes the observed effects: the FDC and the leader know whether the target has been dealt with.

KEY POINT
A bracket is built with bounds of 400, 200, 100 metres then 50; fire for effect is called when the 100-metre bracket is split. The mission closes with the observed effects.
EN;
        $guided = K::stage(
            K::question(
                'Quelle correction envoyez-vous maintenant ?',
                'Which correction do you send now?',
                [
                    K::text('« ADD 400, OVER. »', '“ADD 400, OVER.”'),
                    K::text('« FIRE FOR EFFECT, OVER. »', '“FIRE FOR EFFECT, OVER.”'),
                    K::text('« ADD 200, OVER. »', '“ADD 200, OVER.”'),
                ],
                2,
                'Vous avez une fourchette de 400 mètres (un coup au-delà, un en deçà) : on la coupe en deux avec ADD 200. ADD 400 ramènerait au premier point d’impact ; le tir d’efficacité attend que la fourchette de 100 mètres soit coupée.',
                'You have a 400-metre bracket (one round over, one short): split it in half with ADD 200. ADD 400 would return to the first impact point; fire for effect waits until the 100-metre bracket is split.'
            ),
            K::text(
                "CAS GUIDÉ — LA FOURCHETTE\nVous êtes Hibou 3 et réglez un mortier sur un poste ennemi. Le premier coup est tombé au-delà de l’objectif, sur la ligne. Vous avez envoyé « DROP 400 ». Le deuxième coup tombe en deçà, toujours sur la ligne.\n\nTravaillez dans cet ordre :\n1. Rappelez ce qu’est une fourchette.\n2. Déterminez la taille de la fourchette obtenue.\n3. Rappelez ce que l’on fait d’une fourchette à chaque nouveau coup.\n4. Choisissez la correction correcte.",
                "GUIDED CASE — THE BRACKET\nYou are Hibou 3, adjusting a mortar onto an enemy post. The first round landed beyond the target, on the line. You sent “DROP 400”. The second round lands short, still on the line.\n\nWork in this order:\n1. Recall what a bracket is.\n2. Work out the size of the bracket you now have.\n3. Recall what you do with a bracket on each new round.\n4. Choose the correct correction."
            ),
            K::text(
                'Relisez la section 04 : un coup au-delà et un en deçà forment une fourchette, que l’on coupe en deux à chaque coup jusqu’à 100 mètres.',
                'Reread section 04: one round over and one short form a bracket, which is split in half with each round down to 100 metres.'
            )
        );
        $simulation = K::stage(
            K::question(
                'Comment conduisez-vous cette mission ?',
                'How do you conduct this mission?',
                [
                    K::text('Vous annoncez DANGER CLOSE dans la méthode d’engagement et réglez en rampant, par corrections de 100 mètres au plus depuis l’impact le plus proche de Viking 2.', 'You state DANGER CLOSE in the method of engagement and adjust by creeping fire, with corrections of 100 metres or less from the impact closest to Viking 2.'),
                    K::text('Rien de particulier : avec des mortiers, danger close ne s’applique pas.', 'Nothing special: with mortars, danger close does not apply.'),
                    K::text('Vous demandez directement un tir d’efficacité sur la grille, pour finir vite.', 'You call fire for effect straight away on the grid, to finish quickly.'),
                ],
                0,
                'À 450 mètres d’amis, on est sous les 600 mètres prévus pour mortiers et artillerie : DANGER CLOSE est obligatoire et le réglage se fait en rampant, en éloignant les premiers coups des amis puis en les rapprochant par petites corrections. Un tir d’efficacité direct sur une grille non vérifiée est le scénario du tir fratricide.',
                'At 450 metres from friendlies, you are inside the 600 metres set for mortars and artillery: DANGER CLOSE is mandatory and adjustment is by creeping fire, keeping the first rounds away from friendlies then bringing them closer with small corrections. A direct fire for effect on an unchecked grid is the fratricide scenario.'
            ),
            K::text(
                "SIMULATION AUTONOME — VIKING 2 À 450 MÈTRES\nVous êtes Hibou 3. Le groupe Viking 2 tient une ruine à 450 mètres au sud d’une position ennemie retranchée qui le harcèle. Marteau (mortier) est prêt à tirer. Votre grille d’objectif provient de l’ATAK et n’a pas été confirmée au télémètre. Le chef de Viking 2 vous demande d’en finir rapidement.\n\nVotre objectif : conduire la mission en sécurité. Aucune aide n’est donnée pendant cette étape ; vous pourrez revoir le cours après une tentative.",
                "INDEPENDENT SIMULATION — VIKING 2 AT 450 METRES\nYou are Hibou 3. Squad Viking 2 holds a ruin 450 metres south of a dug-in enemy position that keeps harassing it. Marteau (mortar) is ready to fire. Your target grid comes from the ATAK and has not been confirmed with the rangefinder. The Viking 2 leader asks you to finish it quickly.\n\nYour objective: conduct the mission safely. No hint is given during this stage; you may review the course after an attempt."
            )
        );
        $questions = [
            K::question(
                'Que signifie « SPLASH » annoncé par la FDC ?',
                'What does “SPLASH” from the FDC mean?',
                [
                    K::text('Le coup vient de partir.', 'The round has just been fired.'),
                    K::text('Le coup tombera dans cinq secondes : regardez la zone.', 'The round will land in five seconds: watch the area.'),
                    K::text('Tous les coups d’efficacité sont partis.', 'All fire for effect rounds have been fired.'),
                ],
                1,
                'SHOT = coup parti ; SPLASH = impact dans cinq secondes ; ROUNDS COMPLETE = efficacité terminée.',
                'SHOT = round fired; SPLASH = impact in five seconds; ROUNDS COMPLETE = fire for effect finished.'
            ),
            K::question(
                'Un coup est observé SHORT. Quelle correction de distance ?',
                'A round is spotted SHORT. Which range correction?',
                [
                    K::text('DROP.', 'DROP.'),
                    K::text('LEFT.', 'LEFT.'),
                    K::text('ADD.', 'ADD.'),
                ],
                2,
                'La correction est l’inverse de l’observation : en deçà, on allonge (ADD) ; au-delà, on raccourcit (DROP).',
                'The correction is the opposite of the spotting: short, you lengthen (ADD); over, you shorten (DROP).'
            ),
            K::question(
                'À 3 000 mètres de l’objectif, le coup tombe 20 mils à droite. Quelle correction de déviation ?',
                'At 3,000 metres from the target, the round lands 20 mils right. Which deviation correction?',
                [
                    K::text('RIGHT 60.', 'RIGHT 60.'),
                    K::text('LEFT 60.', 'LEFT 60.'),
                    K::text('LEFT 20.', 'LEFT 20.'),
                ],
                1,
                'Mils × distance en kilomètres : 20 × 3 = 60 mètres, à corriger à l’opposé de l’observation, donc vers la gauche.',
                'Mils × distance in kilometres: 20 × 3 = 60 metres, corrected opposite to the spotting, so to the left.'
            ),
            K::question(
                'En fourchette successive, quand demande-t-on le tir d’efficacité ?',
                'In successive bracketing, when is fire for effect called?',
                [
                    K::text('Dès le premier coup au-delà.', 'As soon as the first round is over.'),
                    K::text('Quand la fourchette de 100 mètres est coupée.', 'When the 100-metre bracket is split.'),
                    K::text('Après dix coups de réglage, quoi qu’il arrive.', 'After ten adjusting rounds, whatever happens.'),
                ],
                1,
                'Couper la fourchette de 100 mètres (ADD ou DROP 50) place le centre des impacts à 50 mètres au plus du point de réglage, selon l’ATP 3-09.30.',
                'Splitting the 100-metre bracket (ADD or DROP 50) puts the centre of impact within 50 metres of the adjusting point, according to ATP 3-09.30.'
            ),
            K::question(
                'À quelle distance d’amis un tir de mortier ou d’artillerie devient-il DANGER CLOSE ?',
                'Within what distance of friendlies does mortar or artillery fire become DANGER CLOSE?',
                [
                    K::text('100 mètres.', '100 metres.'),
                    K::text('2 000 mètres.', '2,000 metres.'),
                    K::text('600 mètres.', '600 metres.'),
                ],
                2,
                'L’ATP 3-09.30 fixe 600 mètres pour mortiers et artillerie. Le réglage se fait alors en rampant, par corrections de 100 mètres au plus.',
                'ATP 3-09.30 sets 600 metres for mortars and artillery. Adjustment is then by creeping fire, with corrections of 100 metres or less.'
            ),
            K::question(
                'Un joueur voit des amis entrer dans la zone d’impact pendant le tir. Que doit-il annoncer ?',
                'A player sees friendlies entering the impact area during the fire mission. What should they call?',
                [
                    K::text('« CHECK FIRING » : arrêt immédiat du tir.', '“CHECK FIRING”: immediate halt of fire.'),
                    K::text('« REPEAT ».', '“REPEAT”.'),
                    K::text('« END OF MISSION » après la salve.', '“END OF MISSION” after the volley.'),
                ],
                0,
                'CHECK FIRING arrête immédiatement le tir pour raison de sécurité ; tout joueur qui voit un danger l’annonce sans attendre.',
                'CHECK FIRING stops fire immediately for safety reasons; any player who sees a danger calls it without waiting.'
            ),
            K::question(
                'Le tir d’efficacité a produit l’effet recherché. Quelle transmission ?',
                'The fire for effect has achieved the desired effect. Which transmission?',
                [
                    K::text('« Merci, terminé. »', '“Thanks, done.”'),
                    K::text('« END OF MISSION », suivi des effets observés.', '“END OF MISSION”, followed by the observed effects.'),
                    K::text('« ROUNDS COMPLETE, OVER. »', '“ROUNDS COMPLETE, OVER.”'),
                ],
                1,
                'La fin de mission comprend toujours le compte rendu des effets (surveillance) : il dit à la FDC et au chef si l’objectif est traité. ROUNDS COMPLETE est annoncé par la FDC.',
                'End of mission always includes the effects report (surveillance): it tells the FDC and the leader whether the target has been dealt with. ROUNDS COMPLETE is reported by the FDC.'
            ),
        ];

        return K::chapter(
            K::text('Chapitre 3 — Réglage, tir d’efficacité, fin de mission et sécurité', 'Chapter 3 — Adjustment, fire for effect, end of mission and safety'),
            K::text($theoryFr, $theoryEn),
            K::text($demoFr, $demoEn),
            $guided,
            $simulation,
            $questions,
            K::text(
                "Rédigez un retour personnel sur le cas Viking 2 à 450 mètres et votre évaluation :\n1. La façon dont vous avez appliqué DANGER CLOSE et le réglage en rampant.\n2. La pression du chef de groupe : comment y répondre sans prendre de risque.\n3. Une séquence de réglage complète, du premier coup au tir d’efficacité.\n4. Le compte rendu de fin de mission que vous auriez transmis.\n5. Une amélioration concrète à tester lors de votre prochaine mission.",
                "Write a personal reflection on the Viking 2 at 450 metres case and your assessment:\n1. How you applied DANGER CLOSE and creeping adjustment.\n2. Pressure from the squad leader: how to respond without taking risks.\n3. A complete adjustment sequence, from the first round to fire for effect.\n4. The end of mission report you would have sent.\n5. One concrete improvement to test in your next mission."
            ),
            [
                K::text('Les annonces SHOT, SPLASH et ROUNDS COMPLETE sont expliquées.', 'The SHOT, SPLASH and ROUNDS COMPLETE calls are explained.'),
                K::text('Les corrections (sens, ordre, calcul de déviation) et la fourchette sont restituées.', 'Corrections (direction, order, deviation calculation) and bracketing are restated.'),
                K::text('DANGER CLOSE, réglage en rampant et CHECK FIRING sont justifiés.', 'DANGER CLOSE, creeping adjustment and CHECK FIRING are justified.'),
                K::text('Amélioration concrète et vérifiable en mission.', 'Concrete improvement that can be checked in a mission.'),
            ],
            [
                K::text('L’évaluation atteint au moins 80 % dans le délai prévu.', 'The assessment reaches at least 80% within the time limit.'),
                K::text('Le stagiaire règle un tir de jeu jusqu’au tir d’efficacité par fourchette.', 'The learner adjusts game fire through to fire for effect by bracketing.'),
                K::text('La mission se clôt par END OF MISSION avec les effets observés.', 'The mission closes with END OF MISSION and the observed effects.'),
                K::text('DANGER CLOSE est annoncé sous 600 mètres et CHECK FIRING employé sans hésiter.', 'DANGER CLOSE is called within 600 metres and CHECK FIRING used without hesitation.'),
            ],
            [
                K::sheet(
                    K::text('Fiche mémo — Réglage, fin de mission et sécurité', 'Memo card — Adjustment, end of mission and safety'),
                    K::text(
                        "FORMATION DE JEU (ARMA 3) — sans valeur de qualification réelle.\n\nANNONCES FDC\nSHOT = coup parti → « SHOT, OUT » · SPLASH = impact dans 5 s (toujours au mortier) → « SPLASH, OUT » · ROUNDS COMPLETE = efficacité terminée.\n\nOBSERVATION\nDistance : OVER · SHORT · TARGET · DOUBTFUL · LOST. Déviation : LINE · LEFT · RIGHT (mils).\n\nCORRECTIONS (en mètres, à l’inverse)\nOrdre : direction OT (si > 100 mils de changement) · déviation · distance.\nDéviation = mils × distance OT en km (< 30 m : ne pas envoyer).\nSHORT → ADD · OVER → DROP.\n\nFOURCHETTE\nBonds 800 / 400 / 200 / 100 · couper en deux · fourchette de 100 m coupée (± 50) → FIRE FOR EFFECT.\nRapide : corriger directement sur l’objectif. Télémètre : un coup peut suffire.\n\nFIN DE MISSION\nEND OF MISSION + effets observés · ou corrections + REPEAT · RECORD AS TARGET si utile.\n\nSÉCURITÉ\nDANGER CLOSE < 600 m d’amis (mortier, artillerie) → réglage en rampant, ≤ 100 m depuis l’impact le plus proche des amis · CANCEL DANGER CLOSE si on s’éloigne · CHECK FIRING = arrêt immédiat, annoncé par quiconque voit un danger · limites de secteur, zones interdites de tir, lignes de coordination du briefing · amis vérifiés sur l’ATAK · aéronefs signalés au JTAC.",
                        "IN-GAME TRAINING (ARMA 3) — no real-world qualification value.\n\nFDC CALLS\nSHOT = round fired → “SHOT, OUT” · SPLASH = impact in 5 s (always with mortars) → “SPLASH, OUT” · ROUNDS COMPLETE = fire for effect finished.\n\nSPOTTING\nRange: OVER · SHORT · TARGET · DOUBTFUL · LOST. Deviation: LINE · LEFT · RIGHT (mils).\n\nCORRECTIONS (in metres, opposite)\nOrder: OT direction (if changed > 100 mils) · deviation · range.\nDeviation = mils × OT distance in km (< 30 m: do not send).\nSHORT → ADD · OVER → DROP.\n\nBRACKETING\nBounds 800 / 400 / 200 / 100 · split in half · 100 m bracket split (± 50) → FIRE FOR EFFECT.\nHasty: correct straight onto the target. Rangefinder: one round may be enough.\n\nEND OF MISSION\nEND OF MISSION + observed effects · or corrections + REPEAT · RECORD AS TARGET if useful.\n\nSAFETY\nDANGER CLOSE < 600 m from friendlies (mortar, artillery) → creeping adjustment, ≤ 100 m from the impact closest to friendlies · CANCEL DANGER CLOSE when moving away · CHECK FIRING = immediate halt, called by anyone who sees a danger · sector limits, no-fire areas, coordination lines from the briefing · friendlies checked on the ATAK · aircraft reported to the JTAC."
                    )
                ),
                K::sheet(
                    K::text('Sources publiques', 'Public sources'),
                    K::text(
                        "FORMATION DE JEU (ARMA 3) — sans valeur de qualification réelle.\nContenu reformulé à partir de sources publiques ; rien de classifié.\n\nATP 3-09.30 Observed Fires, US Army, 28 septembre 2017, approuvée pour diffusion publique (appel de feu, MTO, réglage, danger close, rôle du JFO) :\nhttps://armypubs.army.mil/ProductMaps/PubForm/Details.aspx?PUB_ID=1003069\nhttps://armypubs.army.mil/epubs/DR_pubs/DR_a/pdf/web/ARN5011_ATP%203-09x30%20FINAL%20WEB.pdf\nWikipédia — Fire direction center (rôle de la FDC, réglage et tir d’efficacité) :\nhttps://en.wikipedia.org/wiki/Fire_direction_center\nWikipédia — Artillery observer (rôle de l’observateur) :\nhttps://en.wikipedia.org/wiki/Artillery_observer\n\nRéférence citée par nom seulement : STANAG 2934, AArtyP-1 (Artillery Procedures), à laquelle renvoie l’ATP 3-09.30.",
                        "IN-GAME TRAINING (ARMA 3) — no real-world qualification value.\nContent reworded from public sources; nothing classified.\n\nATP 3-09.30 Observed Fires, US Army, 28 September 2017, approved for public release (call for fire, MTO, adjustment, danger close, JFO role):\nhttps://armypubs.army.mil/ProductMaps/PubForm/Details.aspx?PUB_ID=1003069\nhttps://armypubs.army.mil/epubs/DR_pubs/DR_a/pdf/web/ARN5011_ATP%203-09x30%20FINAL%20WEB.pdf\nWikipedia — Fire direction center (FDC role, adjustment and fire for effect):\nhttps://en.wikipedia.org/wiki/Fire_direction_center\nWikipedia — Artillery observer (observer role):\nhttps://en.wikipedia.org/wiki/Artillery_observer\n\nReference cited by name only: STANAG 2934, AArtyP-1 (Artillery Procedures), referred to by ATP 3-09.30."
                    )
                ),
            ]
        );
    }
}
