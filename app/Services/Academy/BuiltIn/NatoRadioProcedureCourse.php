<?php

declare(strict_types=1);

namespace App\Services\Academy\BuiltIn;

use App\Services\Academy\BuiltIn\AcademyCourseKit as K;

/**
 * Procédures radiotéléphoniques alliées et alphabet phonétique OTAN, inspirées de l’ACP 125(G)
 * (Communications Instructions — Radiotelephone Procedures, publication alliée non classifiée du CCEB, utilisée
 * aussi au sein de l’OTAN). Formation de jeu Arma 3 appliquée aux radios ACRE ; qualification Athena Academy interne.
 */
final class NatoRadioProcedureCourse
{
    public const KEY = 'otan-procedures-radio-acp125';
    public const VERSION = '1.0.0';

    /** @return array<string, mixed> */
    public static function course(): array
    {
        return K::course(
            self::KEY,
            self::VERSION,
            K::text('Procédures radio alliées et alphabet OTAN (ACP 125)', 'Allied radio procedures and the NATO alphabet (ACP 125)'),
            K::text(
                'Parler juste et court à la radio en mission : alphabet phonétique, chiffres et heures, prowords, structure d’un appel, collationnement, discipline radio, vérification de liaison et utilisation des réseaux ACRE (section, compagnie). Formation de jeu inspirée de l’ACP 125(G), publication alliée non classifiée.',
                'Speaking accurately and briefly on the radio during missions: phonetic alphabet, figures and times, prowords, call structure, read-back, radio discipline, radio checks and use of ACRE nets (section, company). In-game training inspired by ACP 125(G), an unclassified Allied publication.'
            ),
            120,
            [self::chapterAlphabet(), self::chapterProwords(), self::chapterNet()],
            [
                'code' => 'ACAD-OTAN-RADIO',
                'license_code' => 'RADIO',
                'name' => K::text('Opérateur radio — procédures alliées (Athena Academy)', 'Radio operator — Allied procedures (Athena Academy)'),
                'short_name' => 'RADIO ACP',
                'description' => K::text(
                    'Le titulaire épelle, transmet des chiffres, des grilles et des heures sans ambiguïté, emploie les prowords dans un appel complet et collationne une information critique. Il tient sa place sur un réseau ACRE de section ou de compagnie : discipline, vérification de liaison, précédence. Qualification de jeu inspirée de l’ACP 125(G), sans valeur réelle.',
                    'The holder spells, passes figures, grid references and times without ambiguity, uses prowords in a complete call and reads back critical information. They hold their place on an ACRE section or company net: discipline, radio checks, precedence. In-game qualification inspired by ACP 125(G), with no real-world value.'
                ),
                'reference' => K::text(
                    'Inspirée de l’ACP 125(G), « Communications Instructions — Radiotelephone Procedures » (CCEB, 2016, publication alliée non classifiée), et de l’alphabet phonétique OTAN.',
                    'Inspired by ACP 125(G), “Communications Instructions — Radiotelephone Procedures” (CCEB, 2016, unclassified Allied publication), and the NATO phonetic alphabet.'
                ),
                'validity_months' => null,
            ]
        );
    }

    // ------------------------------------------------------------------ Chapitre 1 — Alphabet, chiffres, heures

    /** @return array<string, mixed> */
    private static function chapterAlphabet(): array
    {
        $theoryFr = <<<'FR'
OBJECTIF DE LA FORMATION
Ce cours forme à la procédure radio de nos missions Arma 3 : épeler, transmettre des chiffres, structurer un appel, collationner et tenir sa place sur un réseau ACRE. Il s’inspire de l’ACP 125(G), « Communications Instructions — Radiotelephone Procedures », publication alliée non classifiée du CCEB (Australie, Canada, Nouvelle-Zélande, Royaume-Uni, États-Unis) que l’OTAN applique aussi. Les prowords restent en anglais, comme sur nos réseaux. Il s’agit d’une formation de jeu : la qualification Athena Academy atteste un parcours interne, sans valeur de qualification réelle, militaire ou civile.

01 — Pourquoi une procédure normalisée
Une procédure commune rend les échanges plus rapides, plus exacts et plus sûrs. Sur nos serveurs, les joueurs ont des accents et des micros différents, et ACRE simule l’affaiblissement du signal et le brouillage quand deux stations parlent en même temps. Chaque mot inutile prolonge l’occupation du canal ; chaque mot mal compris coûte une répétition.

02 — L’alphabet phonétique
Chaque lettre est remplacée par un mot unique : ALFA, BRAVO, CHARLIE, DELTA, ECHO, FOXTROT, GOLF, HOTEL, INDIA, JULIETT, KILO, LIMA, MIKE, NOVEMBER, OSCAR, PAPA, QUEBEC, ROMEO, SIERRA, TANGO, UNIFORM, VICTOR, WHISKEY, XRAY, YANKEE, ZULU. L’OTAN l’a adopté en 1956. ALFA et JULIETT s’écrivent ainsi pour être prononcés correctement par des locuteurs non anglophones. QUEBEC se dit « KÉ-BEK ».

03 — Épeler : I SPELL
On épelle un mot difficile à saisir ou imprononçable, surtout en mauvaise réception. Le proword I SPELL annonce l’épellation. Si le mot se prononce, on le dit d’abord, puis on l’épelle : « Kavala, I SPELL, KILO ALFA VICTOR ALFA LIMA ALFA ». Les abréviations courantes se disent telles quelles quand elles sont plus rapides qu’épelées.

04 — Les chiffres
Prononcés un par un, les chiffres ont une forme radio : WUN (1), TOO (2), TREE (3), FOW-ER (4), FIFE (5), SIX (6), SEV-EN (7), AIT (8), NINE-ER (9), ZE-RO (0). La voix porte mieux avec un rythme régulier, un volume de conversation (crier sature le son) et une hauteur légèrement plus aiguë qu’à l’ordinaire.

05 — Les nombres et le proword FIGURES
En bonne réception, un nombre peut se dire normalement. En réception difficile, ou quand une erreur aurait des conséquences, on annonce FIGURES puis les chiffres un par un : « FIGURES WUN FOW-ER » pour 14. Certaines données se disent toujours chiffre par chiffre, sans FIGURES : indicatifs, références de grille, indications d’objectif (sauf les centaines et milliers exacts, dits « three hundred », « two thousand »), authentification et groupes date-heure.

06 — L’heure
L’heure se donne sur quatre chiffres, précédée du proword TIME : « TIME WUN SIX TREE ZE-RO ». Pour un top horaire, l’ACP 125 utilise par défaut l’heure Zulu (GMT) et la phrase « Request time check ». En mission, l’ordre précise la référence (heure de la mission en jeu le plus souvent) : annoncez-la une fois et tenez-vous-y.

07 — Ce qu’il faut retenir
Alphabet phonétique pour les lettres, I SPELL pour épeler, chiffres prononcés sous leur forme radio, FIGURES en réception difficile, grilles et indicatifs toujours chiffre par chiffre, heure sur quatre chiffres avec TIME. Parlez calmement, sans crier.
FR;
        $theoryEn = <<<'EN'
TRAINING OBJECTIVE
This course trains the radio procedure used in our Arma 3 missions: spelling, passing figures, structuring a call, reading back and holding your place on an ACRE net. It is inspired by ACP 125(G), “Communications Instructions — Radiotelephone Procedures”, an unclassified Allied publication of the CCEB (Australia, Canada, New Zealand, United Kingdom, United States) that NATO also applies. Prowords stay in English, as on our nets. This is in-game training: the Athena Academy qualification records an internal pathway and has no real-world qualification value, military or civilian.

01 — Why a standard procedure
A common procedure makes exchanges faster, more accurate and more secure. On our servers, players have different accents and microphones, and ACRE simulates signal loss and interference when two stations talk at once. Every unnecessary word keeps the channel busy for longer; every misunderstood word costs a repetition.

02 — The phonetic alphabet
Each letter is replaced by a unique word: ALFA, BRAVO, CHARLIE, DELTA, ECHO, FOXTROT, GOLF, HOTEL, INDIA, JULIETT, KILO, LIMA, MIKE, NOVEMBER, OSCAR, PAPA, QUEBEC, ROMEO, SIERRA, TANGO, UNIFORM, VICTOR, WHISKEY, XRAY, YANKEE, ZULU. NATO adopted it in 1956. ALFA and JULIETT are spelt this way so that non-English speakers pronounce them correctly. QUEBEC is said “KEH-BECK”.

03 — Spelling: I SPELL
A word is spelt when it is hard to catch or unpronounceable, especially in poor reception. The proword I SPELL announces the spelling. If the word can be pronounced, say it first, then spell it: “Kavala, I SPELL, KILO ALFA VICTOR ALFA LIMA ALFA”. Common abbreviations are spoken as such when that is quicker than spelling them.

04 — Figures
Spoken one at a time, figures have a radio form: WUN (1), TOO (2), TREE (3), FOW-ER (4), FIFE (5), SIX (6), SEV-EN (7), AIT (8), NINE-ER (9), ZE-RO (0). The voice carries better with a steady rhythm, a conversational volume (shouting distorts the sound) and a pitch slightly higher than usual.

05 — Numbers and the proword FIGURES
In good reception, a number may be spoken normally. In difficult reception, or when an error would matter, announce FIGURES and then the digits one by one: “FIGURES WUN FOW-ER” for 14. Some data are always spoken digit by digit, without FIGURES: callsigns, grid references, target indications (except exact hundreds and thousands, spoken “three hundred”, “two thousand”), authentication and date-time groups.

06 — Time
Time is given in four digits, preceded by the proword TIME: “TIME WUN SIX TREE ZE-RO”. For a time check, ACP 125 uses Zulu time (GMT) by default and the phrase “Request time check”. In missions, the orders state the reference (usually the in-game mission time): announce it once and stick to it.

07 — Key points
Phonetic alphabet for letters, I SPELL to spell, figures in their radio form, FIGURES in difficult reception, grid references and callsigns always digit by digit, time in four digits with TIME. Speak calmly, without shouting.
EN;
        $demoFr = <<<'FR'
DÉMONSTRATION COMMENTÉE — UN POINT DE RENDEZ-VOUS DANS LE BRUIT
Situation de jeu : la section « Griffon 1 » attend un véhicule de ravitaillement. Le chef d’équipe « Griffon 1-2 » est en limite de portée et la réception est mauvaise. Indicatifs fictifs.

Griffon 1 : « Griffon 1-2, THIS IS Griffon 1, rendez-vous au hameau Neri, I SPELL, NOVEMBER ECHO ROMEO INDIA, OVER. »
Commentaire : le nom de lieu est dit puis épelé, car il est peu courant et la réception est mauvaise.

Griffon 1 : « GRID ZE-RO FOW-ER SEV-EN… TREE WUN TOO. TIME WUN SIX TREE ZE-RO. FIGURES WUN FOW-ER caisses de munitions, OVER. »
Commentaire : la grille est dite chiffre par chiffre, sans FIGURES, avec une pause entre les deux groupes. L’heure est sur quatre chiffres avec TIME. Le nombre de caisses, lui, est précédé de FIGURES parce que la réception est difficile.

Griffon 1-2 : « Griffon 1, THIS IS Griffon 1-2, ROGER, OUT. »
Commentaire : la réception est confirmée en une phrase. Les informations critiques (grille, heure) pourraient aussi être collationnées : ce sera l’objet du chapitre 2.

À RETENIR
Ce qui ne doit pas être mal compris (lieu, grille, heure, quantités) est donné sous sa forme radio. Chaque élément est séparé par une courte pause plutôt que par des mots de remplissage.
FR;
        $demoEn = <<<'EN'
ANNOTATED DEMONSTRATION — A RENDEZVOUS IN THE NOISE
Game situation: the section “Griffon 1” is waiting for a resupply vehicle. Team leader “Griffon 1-2” is at the edge of range and reception is poor. Fictional callsigns.

Griffon 1: “Griffon 1-2, THIS IS Griffon 1, rendezvous at the hamlet of Neri, I SPELL, NOVEMBER ECHO ROMEO INDIA, OVER.”
Comment: the place name is said and then spelt, because it is uncommon and reception is poor.

Griffon 1: “GRID ZE-RO FOW-ER SEV-EN… TREE WUN TOO. TIME WUN SIX TREE ZE-RO. FIGURES WUN FOW-ER ammunition crates, OVER.”
Comment: the grid reference is spoken digit by digit, without FIGURES, with a pause between the two groups. The time is in four digits with TIME. The number of crates is preceded by FIGURES because reception is difficult.

Griffon 1-2: “Griffon 1, THIS IS Griffon 1-2, ROGER, OUT.”
Comment: receipt is confirmed in one sentence. The critical items (grid, time) could also be read back: that is the subject of chapter 2.

KEY POINT
Anything that must not be misunderstood (place, grid reference, time, quantities) is given in its radio form. Each item is separated by a short pause rather than by filler words.
EN;
        $guided = K::stage(
            K::question(
                'Réception difficile : comment transmettez-vous la grille 047 312 ?',
                'Difficult reception: how do you pass grid reference 047 312?',
                [
                    K::text('« GRID ZE-RO FOW-ER SEV-EN… TREE WUN TOO. »', '“GRID ZE-RO FOW-ER SEV-EN… TREE WUN TOO.”'),
                    K::text('« FIGURES forty-seven, three hundred and twelve. »', '“FIGURES forty-seven, three hundred and twelve.”'),
                    K::text('« I SPELL zero forty-seven three twelve. »', '“I SPELL zero forty-seven three twelve.”'),
                ],
                0,
                'Une référence de grille se dit toujours chiffre par chiffre, sans FIGURES, précédée de GRID, avec une pause entre le groupe des abscisses et celui des ordonnées. I SPELL sert aux lettres, pas aux nombres.',
                'A grid reference is always spoken digit by digit, without FIGURES, preceded by GRID, with a pause between the easting and northing groups. I SPELL is for letters, not numbers.'
            ),
            K::text(
                "CAS GUIDÉ — LA GRILLE DU POINT DE REGROUPEMENT\nVous êtes Griffon 1-1, chef d’équipe. Votre chef de section, à 450 m derrière une crête, vous entend mal sur l’AN/PRC-343. Il vous demande la position du point de regroupement, au carré 047 312 de la carte.\n\nTravaillez dans cet ordre :\n1. Identifiez le type de donnée : une référence de grille.\n2. Rappelez la règle de la section 05 pour ce type de donnée.\n3. Rappelez la prononciation radio de chaque chiffre.\n4. Choisissez la réponse qui respecte les deux règles.",
                "GUIDED CASE — THE RALLY POINT GRID\nYou are Griffon 1-1, team leader. Your section leader, 450 m away behind a ridge, hears you poorly on the AN/PRC-343. He asks for the position of the rally point, in map square 047 312.\n\nWork in this order:\n1. Identify the type of data: a grid reference.\n2. Recall the rule in section 05 for this type of data.\n3. Recall the radio pronunciation of each figure.\n4. Choose the answer that follows both rules."
            ),
            K::text(
                'Relisez la section 05 : les grilles font partie des exceptions qui se disent toujours chiffre par chiffre.',
                'Reread section 05: grid references are among the exceptions that are always spoken digit by digit.'
            )
        );
        $simulation = K::stage(
            K::question(
                'Quel message transmettez-vous ?',
                'Which message do you send?',
                [
                    K::text('« Quatorze véhicules à Kore vers six heures moins le quart. »', '“Fourteen vehicles at Kore at about quarter to six.”'),
                    K::text('« FIGURES fourteen vehicles at KILO OSCAR ROMEO ECHO, TIME five forty-five. »', '“FIGURES fourteen vehicles at KILO OSCAR ROMEO ECHO, TIME five forty-five.”'),
                    K::text('« Kore, I SPELL, KILO OSCAR ROMEO ECHO. FIGURES WUN FOW-ER véhicules. TIME ZE-RO FIFE FOW-ER FIFE. »', '“Kore, I SPELL, KILO OSCAR ROMEO ECHO. FIGURES WUN FOW-ER vehicles. TIME ZE-RO FIFE FOW-ER FIFE.”'),
                ],
                2,
                'Le nom est dit puis épelé après I SPELL, le nombre est annoncé par FIGURES puis donné chiffre par chiffre, l’heure sur quatre chiffres après TIME. La deuxième réponse annonce FIGURES mais dit « fourteen » d’un bloc et donne l’heure en langage courant.',
                'The name is said and then spelt after I SPELL, the number is announced by FIGURES and then given digit by digit, the time in four digits after TIME. The second answer announces FIGURES but says “fourteen” in one block and gives the time in everyday language.'
            ),
            K::text(
                "SIMULATION AUTONOME — LE CONVOI DE KORE\nVous êtes observateur sur une colline, à la limite de portée de votre poste. Vous avez compté quatorze véhicules ennemis entrant dans le village de Kore à 05 h 45, heure de la mission. Votre chef de section vous a prévenu que la réception était mauvaise et qu’aucun chiffre ne devait être mal compris.\n\nVotre objectif : choisir la transmission conforme aux règles d’épellation, de nombres et d’heure. Aucune aide n’est donnée pendant cette étape ; vous pourrez revoir le cours après une tentative.",
                "INDEPENDENT SIMULATION — THE KORE CONVOY\nYou are an observer on a hill, at the edge of your radio’s range. You counted fourteen enemy vehicles entering the village of Kore at 05:45, mission time. Your section leader has warned you that reception is poor and that no figure may be misunderstood.\n\nYour objective: choose the transmission that follows the rules for spelling, numbers and time. No hint is given during this stage; you may review the course after an attempt."
            )
        );
        $questions = [
            K::question(
                'Quel mot de l’alphabet phonétique correspond à la lettre J ?',
                'Which phonetic alphabet word stands for the letter J?',
                [
                    K::text('JULIET, écrit avec un seul T comme le prénom.', 'JULIET, spelt with a single T like the name.'),
                    K::text('JAGUAR.', 'JAGUAR.'),
                    K::text('JULIETT, écrit avec deux T.', 'JULIETT, spelt with two Ts.'),
                ],
                2,
                'L’orthographe officielle est JULIETT (et ALFA pour A) : elle guide la prononciation des locuteurs non anglophones.',
                'The official spelling is JULIETT (and ALFA for A): it guides pronunciation for non-English speakers.'
            ),
            K::question(
                'Comment se prononce le chiffre 9 à la radio ?',
                'How is the figure 9 pronounced on the radio?',
                [
                    K::text('NINE-ER.', 'NINE-ER.'),
                    K::text('NOVEMBER.', 'NOVEMBER.'),
                    K::text('NINE, comme en anglais courant.', 'NINE, as in everyday English.'),
                ],
                0,
                'L’ACP 125 donne NINE-ER pour 9, comme TREE pour 3, FIFE pour 5 et AIT pour 8. NOVEMBER est la lettre N.',
                'ACP 125 gives NINE-ER for 9, just as TREE for 3, FIFE for 5 and AIT for 8. NOVEMBER is the letter N.'
            ),
            K::question(
                'Que signifie le proword FIGURES ?',
                'What does the proword FIGURES mean?',
                [
                    K::text('Le message qui suit est chiffré.', 'The following message is encrypted.'),
                    K::text('Des chiffres suivent immédiatement, transmis un par un.', 'Figures follow immediately, sent one by one.'),
                    K::text('L’émetteur demande la répétition des nombres.', 'The sender is asking for the numbers to be repeated.'),
                ],
                1,
                'FIGURES prévient que des chiffres suivent immédiatement, pour les distinguer de mots qui se prononcent de façon voisine. On l’emploie en réception difficile ou quand l’erreur aurait des conséquences.',
                'FIGURES warns that figures follow immediately, to distinguish them from words that sound alike. It is used in difficult reception or when an error would matter.'
            ),
            K::question(
                'Laquelle de ces données se dit TOUJOURS chiffre par chiffre, sans le proword FIGURES ?',
                'Which of these data is ALWAYS spoken digit by digit, without the proword FIGURES?',
                [
                    K::text('Un nombre de caisses de munitions.', 'A number of ammunition crates.'),
                    K::text('Un effectif de blessés.', 'A casualty count.'),
                    K::text('Une référence de grille.', 'A grid reference.'),
                ],
                2,
                'Indicatifs, grilles, indications d’objectif, authentification et groupes date-heure se disent toujours chiffre par chiffre. Les quantités ordinaires prennent FIGURES en réception difficile.',
                'Callsigns, grid references, target indications, authentication and date-time groups are always spoken digit by digit. Ordinary quantities take FIGURES in difficult reception.'
            ),
            K::question(
                'Dans une indication d’objectif, comment dit-on une distance de 300 m ?',
                'In a target indication, how do you say a distance of 300 m?',
                [
                    K::text('« TREE ZE-RO ZE-RO ».', '“TREE ZE-RO ZE-RO”.'),
                    K::text('« Three hundred » : les centaines exactes se disent comme telles.', '“Three hundred”: exact hundreds are spoken as such.'),
                    K::text('« FIGURES three zero zero ».', '“FIGURES three zero zero”.'),
                ],
                1,
                'Les indications d’objectif se disent chiffre par chiffre, sauf les multiples exacts de cent ou de mille, prononcés « three hundred », « one thousand ».',
                'Target indications are spoken digit by digit, except exact multiples of a hundred or a thousand, pronounced “three hundred”, “one thousand”.'
            ),
            K::question(
                'Quand utilise-t-on le proword I SPELL ?',
                'When is the proword I SPELL used?',
                [
                    K::text('Avant chaque indicatif.', 'Before every callsign.'),
                    K::text('Pour annoncer une grille.', 'To announce a grid reference.'),
                    K::text('Avant d’épeler phonétiquement un mot difficile ou imprononçable.', 'Before spelling out phonetically a difficult or unpronounceable word.'),
                ],
                2,
                'I SPELL annonce une épellation phonétique. Si le mot se prononce, on le dit d’abord, puis on l’épelle. Une grille est annoncée par GRID.',
                'I SPELL announces a phonetic spelling. If the word can be pronounced, say it first, then spell it. A grid reference is announced by GRID.'
            ),
            K::question(
                'Comment donne-t-on l’heure 16 h 30 en réception difficile ?',
                'How do you give the time 16:30 in difficult reception?',
                [
                    K::text('« TIME WUN SIX TREE ZE-RO ».', '“TIME WUN SIX TREE ZE-RO”.'),
                    K::text('« Half past four in the afternoon ».', '“Half past four in the afternoon”.'),
                    K::text('« Sixteen thirty, more or less ».', '“Sixteen thirty, more or less”.'),
                ],
                0,
                'L’heure se donne sur quatre chiffres, précédée de TIME, chiffre par chiffre quand la réception est difficile.',
                'Time is given in four digits, preceded by TIME, digit by digit when reception is difficult.'
            ),
        ];

        return K::chapter(
            K::text('Chapitre 1 — Alphabet phonétique, chiffres, nombres et heure', 'Chapter 1 — Phonetic alphabet, figures, numbers and time'),
            K::text($theoryFr, $theoryEn),
            K::text($demoFr, $demoEn),
            $guided,
            $simulation,
            $questions,
            K::text(
                "Rédigez un retour personnel sur le cas du convoi de Kore et votre évaluation :\n1. Les lettres ou chiffres que vous prononcez encore mal sous pression.\n2. Les données que vous dites désormais toujours chiffre par chiffre.\n3. Un nom de lieu de nos cartes qu’il faut épeler, et comment vous le transmettriez.\n4. La référence horaire utilisée dans votre dernière mission.\n5. Un exercice concret pour la prochaine session (par exemple épeler son indicatif et une grille à voix haute).",
                "Write a personal reflection on the Kore convoy case and your assessment:\n1. The letters or figures you still pronounce poorly under pressure.\n2. The data you now always speak digit by digit.\n3. A place name on our maps that needs spelling, and how you would pass it.\n4. The time reference used in your last mission.\n5. A concrete exercise for the next session (for example spelling your callsign and a grid reference aloud)."
            ),
            [
                K::text('Alphabet et chiffres radio restitués sans erreur.', 'Alphabet and radio figures restated without error.'),
                K::text('Différence entre FIGURES et les données toujours dites chiffre par chiffre expliquée.', 'Difference between FIGURES and data always spoken digit by digit explained.'),
                K::text('Exemple d’épellation avec I SPELL correct.', 'Correct I SPELL example.'),
                K::text('Exercice concret et vérifiable proposé.', 'Concrete, checkable exercise proposed.'),
            ],
            [
                K::text('L’évaluation atteint au moins 80 % dans le délai prévu.', 'The assessment reaches at least 80% within the time limit.'),
                K::text('Le stagiaire épelle à la radio un nom de lieu et son indicatif sans hésitation.', 'The learner spells a place name and their callsign on the radio without hesitation.'),
                K::text('Une grille et une heure sont transmises chiffre par chiffre, sous leur forme radio.', 'A grid reference and a time are passed digit by digit, in their radio form.'),
                K::text('Le débriefing identifie un point faible personnel et un exercice pour le corriger.', 'The debrief identifies a personal weak point and an exercise to correct it.'),
            ],
            [
                K::sheet(
                    K::text('Fiche mémo — Alphabet, chiffres et heure', 'Memo card — Alphabet, figures and time'),
                    K::text(
                        "FORMATION DE JEU (ARMA 3) — sans valeur de qualification réelle.\n\nALPHABET\nA ALFA · B BRAVO · C CHARLIE · D DELTA · E ECHO · F FOXTROT · G GOLF · H HOTEL · I INDIA · J JULIETT · K KILO · L LIMA · M MIKE\nN NOVEMBER · O OSCAR · P PAPA · Q QUEBEC (KÉ-BEK) · R ROMEO · S SIERRA · T TANGO · U UNIFORM · V VICTOR · W WHISKEY · X XRAY · Y YANKEE · Z ZULU\n\nCHIFFRES\n1 WUN · 2 TOO · 3 TREE · 4 FOW-ER · 5 FIFE · 6 SIX · 7 SEV-EN · 8 AIT · 9 NINE-ER · 0 ZE-RO\n\nRÈGLES\n- I SPELL : dire le mot, puis l’épeler.\n- FIGURES : chiffres un par un, en réception difficile.\n- Toujours chiffre par chiffre, sans FIGURES : indicatifs, grilles (GRID + pause entre les deux groupes), indications d’objectif (sauf centaines et milliers exacts), authentification, groupes date-heure.\n- TIME + quatre chiffres. Référence : celle de l’ordre de mission (Zulu par défaut dans l’ACP 125).\n- Voix : rythme régulier, volume normal, ne pas crier.",
                        "IN-GAME TRAINING (ARMA 3) — no real-world qualification value.\n\nALPHABET\nA ALFA · B BRAVO · C CHARLIE · D DELTA · E ECHO · F FOXTROT · G GOLF · H HOTEL · I INDIA · J JULIETT · K KILO · L LIMA · M MIKE\nN NOVEMBER · O OSCAR · P PAPA · Q QUEBEC (KEH-BECK) · R ROMEO · S SIERRA · T TANGO · U UNIFORM · V VICTOR · W WHISKEY · X XRAY · Y YANKEE · Z ZULU\n\nFIGURES\n1 WUN · 2 TOO · 3 TREE · 4 FOW-ER · 5 FIFE · 6 SIX · 7 SEV-EN · 8 AIT · 9 NINE-ER · 0 ZE-RO\n\nRULES\n- I SPELL: say the word, then spell it.\n- FIGURES: digits one by one, in difficult reception.\n- Always digit by digit, without FIGURES: callsigns, grid references (GRID + pause between the two groups), target indications (except exact hundreds and thousands), authentication, date-time groups.\n- TIME + four digits. Reference: the one in the mission orders (Zulu by default in ACP 125).\n- Voice: steady rhythm, normal volume, do not shout."
                    )
                ),
            ]
        );
    }

    // ------------------------------------------------------------------ Chapitre 2 — Prowords, appel, collationnement

    /** @return array<string, mixed> */
    private static function chapterProwords(): array
    {
        $theoryFr = <<<'FR'
01 — Les prowords
Un proword (procedure word) est un mot ou une courte phrase au sens fixé, qui remplace une phrase entière. L’ACP 125 en donne la liste complète ; nos réseaux en utilisent un noyau : THIS IS, OVER, OUT, ROGER, WILCO, SEND, SAY AGAIN, I SAY AGAIN, WAIT, WAIT OUT, BREAK, CORRECTION, WRONG, CORRECT, READ BACK, I READ BACK, I SPELL, FIGURES, SPEAK SLOWER, WORDS TWICE, DISREGARD THIS TRANSMISSION – OUT, SILENCE. Un proword ne se traduit pas et ne se remplace pas par un équivalent « maison ».

02 — OVER et OUT
Chaque transmission se termine par OVER (j’ai fini, j’attends votre réponse) ou par OUT (j’ai fini, aucune réponse n’est attendue). Les deux sens s’excluent : « over and out » n’existe pas. Entre deux stations en échange continu, sans risque de confusion, OVER peut être omis.

03 — ROGER, WILCO, SEND
ROGER : j’ai bien reçu votre dernière transmission. WILCO : j’ai reçu, compris et j’exécuterai ; seul le destinataire d’un ordre l’emploie. WILCO contient déjà ROGER : « roger wilco » est redondant. SEND invite la station appelante à passer son message.

04 — Structure d’un appel
Un appel suit l’ordre : indicatif appelé, THIS IS, indicatif appelant, texte, OVER. Exemple : « Griffon 1, THIS IS Griffon 1-1, … OVER ». Les indicatifs complets servent à l’ouverture du réseau et à l’entrée dans un réseau déjà ouvert. Ensuite, deux stations en échange continu peuvent abréger, voire omettre les indicatifs si aucune confusion n’est possible. Pour s’assurer qu’une station est prête à recevoir un long message, on fait d’abord un appel préliminaire. L’ACP 125 définit aussi des titres de fonction : SUNRAY désigne le chef de la station ; FETCH SUNRAY demande qu’il prenne le combiné, SUNRAY SPEAKING annonce qu’il est en ligne. Sur nos réseaux, on n’emploie jamais le pseudonyme d’un joueur : on dit l’indicatif ou le titre de fonction.

05 — Faire répéter
Un mot manqué se fait répéter avant d’accuser réception. SAY AGAIN s’emploie seul ou précisé : ALL AFTER (tout après), ALL BEFORE (tout avant), WORD AFTER, WORD BEFORE, FROM… TO… (de… à…). L’émetteur répond par I SAY AGAIN suivi de la seule partie demandée. Demander précisément évite de tout répéter.

06 — Corriger et collationner
Une erreur pendant l’émission se corrige par CORRECTION, suivi du dernier mot correct, puis la transmission reprend. Pour une information critique (grille, heure, code), l’émetteur demande READ BACK, éventuellement précisé (READ BACK GRID). Le destinataire répond par I READ BACK et répète. L’émetteur confirme par CORRECT ou signale l’erreur par WRONG suivi de la bonne version. DISREGARD THIS TRANSMISSION – OUT annule une transmission en cours ; un message entièrement transmis ne s’annule que par un autre message.

07 — WAIT, WAIT OUT et ce qu’il faut retenir
WAIT demande une courte pause (jusqu’à cinq secondes environ) pendant laquelle personne n’interrompt ; l’émetteur relâche l’alternat. Après trois WAIT successifs, on passe à WAIT OUT : « je vous rappellerai par un nouvel appel ». À retenir : appelé, THIS IS, appelant, texte, OVER ou OUT ; WILCO seul pour un ordre ; SAY AGAIN précis ; READ BACK pour le critique.
FR;
        $theoryEn = <<<'EN'
01 — Prowords
A proword (procedure word) is a word or short phrase with a fixed meaning that replaces a whole sentence. ACP 125 gives the full list; our nets use a core set: THIS IS, OVER, OUT, ROGER, WILCO, SEND, SAY AGAIN, I SAY AGAIN, WAIT, WAIT OUT, BREAK, CORRECTION, WRONG, CORRECT, READ BACK, I READ BACK, I SPELL, FIGURES, SPEAK SLOWER, WORDS TWICE, DISREGARD THIS TRANSMISSION – OUT, SILENCE. A proword is not translated and is not replaced by a home-made equivalent.

02 — OVER and OUT
Every transmission ends with OVER (I have finished, I expect your reply) or OUT (I have finished, no reply is expected). The two meanings exclude each other: “over and out” does not exist. Between two stations in continuous exchange, with no risk of confusion, OVER may be omitted.

03 — ROGER, WILCO, SEND
ROGER: I have received your last transmission satisfactorily. WILCO: I have received and understood, and I will comply; only the addressee of an order uses it. WILCO already includes ROGER: “roger wilco” is redundant. SEND invites the calling station to pass its message.

04 — Structure of a call
A call follows the order: called callsign, THIS IS, calling callsign, text, OVER. Example: “Griffon 1, THIS IS Griffon 1-1, … OVER”. Full callsigns are used when opening the net and when joining a net already open. After that, two stations in continuous exchange may abbreviate, or even drop, callsigns if no confusion is possible. To make sure a station is ready to receive a long message, a preliminary call is made first. ACP 125 also defines appointment titles: SUNRAY designates the commander at a station; FETCH SUNRAY asks for them to come to the handset, SUNRAY SPEAKING announces that they are on. On our nets, a player’s username is never used: say the callsign or the appointment title.

05 — Asking for repetition
A missed word is asked for again before giving a receipt. SAY AGAIN is used alone or narrowed down: ALL AFTER, ALL BEFORE, WORD AFTER, WORD BEFORE, FROM… TO…. The sender answers with I SAY AGAIN followed by the requested part only. Asking precisely avoids repeating everything.

06 — Correcting and reading back
An error during transmission is corrected with CORRECTION, followed by the last correct word, then transmission resumes. For critical information (grid reference, time, code), the sender asks for READ BACK, possibly narrowed (READ BACK GRID). The addressee answers with I READ BACK and repeats. The sender confirms with CORRECT or points out the error with WRONG followed by the correct version. DISREGARD THIS TRANSMISSION – OUT cancels a transmission in progress; a message that has been completely sent can only be cancelled by another message.

07 — WAIT, WAIT OUT and key points
WAIT asks for a short pause (up to about five seconds) during which nobody interrupts; the sender releases the press-to-talk. After three consecutive WAITs, switch to WAIT OUT: “I will call you back with a new call”. Key points: called, THIS IS, caller, text, OVER or OUT; WILCO alone for an order; precise SAY AGAIN; READ BACK for critical items.
EN;
        $demoFr = <<<'FR'
DÉMONSTRATION COMMENTÉE — UN ORDRE DE BOND AVEC COLLATIONNEMENT
Situation de jeu : le chef de section « Griffon 1 » donne un nouveau point d’arrêt au chef d’équipe « Griffon 1-1 ». Indicatifs fictifs.

Griffon 1 : « Griffon 1-1, THIS IS Griffon 1, READ BACK GRID, bondissez jusqu’au mur en GRID ZE-RO FIFE TREE… WUN WUN AIT, en place TIME WUN FOW-ER WUN FIFE, OVER. »
Commentaire : appel complet, READ BACK annoncé d’emblée parce que la grille est critique.

Griffon 1-1 : « Griffon 1, THIS IS Griffon 1-1, SAY AGAIN WORD AFTER mur, OVER. »
Commentaire : un seul mot manqué : la demande est précise, avant tout accusé de réception.

Griffon 1 : « I SAY AGAIN, WORD AFTER mur, GRID, OVER. »
Griffon 1-1 : « I READ BACK, mur en GRID ZE-RO FIFE TREE… WUN WUN SIX, TIME WUN FOW-ER WUN FIFE, OVER. »
Commentaire : le collationnement reprend la grille et l’heure. Le dernier chiffre est faux.

Griffon 1 : « WRONG, GRID ZE-RO FIFE TREE… WUN WUN AIT, OVER. »
Griffon 1-1 : « GRID ZE-RO FIFE TREE… WUN WUN AIT, OVER. »
Griffon 1 : « CORRECT, OUT. »
Commentaire : WRONG suivi de la seule partie fausse, nouvelle répétition, CORRECT et fin d’échange par OUT. Griffon 1-1 n’a pas besoin d’ajouter WILCO : le collationnement correct vaut réception.

À RETENIR
Une grille collationnée de travers est découverte à la radio, pas sur le terrain. Chaque proword a un sens unique : aucune phrase de politesse n’est nécessaire.
FR;
        $demoEn = <<<'EN'
ANNOTATED DEMONSTRATION — A BOUND ORDER WITH READ-BACK
Game situation: section leader “Griffon 1” gives team leader “Griffon 1-1” a new halt point. Fictional callsigns.

Griffon 1: “Griffon 1-1, THIS IS Griffon 1, READ BACK GRID, bound to the wall at GRID ZE-RO FIFE TREE… WUN WUN AIT, in position TIME WUN FOW-ER WUN FIFE, OVER.”
Comment: full call, READ BACK announced up front because the grid reference is critical.

Griffon 1-1: “Griffon 1, THIS IS Griffon 1-1, SAY AGAIN WORD AFTER wall, OVER.”
Comment: only one word was missed: the request is precise, before any receipt.

Griffon 1: “I SAY AGAIN, WORD AFTER wall, GRID, OVER.”
Griffon 1-1: “I READ BACK, wall at GRID ZE-RO FIFE TREE… WUN WUN SIX, TIME WUN FOW-ER WUN FIFE, OVER.”
Comment: the read-back repeats the grid and the time. The last digit is wrong.

Griffon 1: “WRONG, GRID ZE-RO FIFE TREE… WUN WUN AIT, OVER.”
Griffon 1-1: “GRID ZE-RO FIFE TREE… WUN WUN AIT, OVER.”
Griffon 1: “CORRECT, OUT.”
Comment: WRONG followed by the wrong part only, another repetition, CORRECT and the exchange closed with OUT. Griffon 1-1 does not need to add WILCO: the correct read-back serves as receipt.

KEY POINT
A grid reference read back wrongly is caught on the radio, not on the ground. Each proword has a single meaning: no courtesy phrases are needed.
EN;
        $guided = K::stage(
            K::question(
                'Que corrigez-vous dans la réponse de la recrue ?',
                'What do you correct in the recruit’s reply?',
                [
                    K::text('Rien : la réponse est complète et polie.', 'Nothing: the reply is complete and polite.'),
                    K::text('« Roger wilco » est redondant et « over and out » est contradictoire : il faut « Griffon 1, THIS IS Griffon 1-3, WILCO, OUT ».', '“Roger wilco” is redundant and “over and out” is contradictory: it should be “Griffon 1, THIS IS Griffon 1-3, WILCO, OUT”.'),
                    K::text('Il manque seulement le pseudonyme du joueur, pour savoir qui parle.', 'Only the player’s username is missing, to know who is speaking.'),
                ],
                1,
                'WILCO contient ROGER et OVER exclut OUT. Le destinataire d’un ordre répond WILCO et, si aucune réponse n’est attendue, termine par OUT. On s’identifie par l’indicatif, jamais par le pseudonyme.',
                'WILCO includes ROGER, and OVER excludes OUT. The addressee of an order replies WILCO and, if no reply is expected, ends with OUT. Stations identify themselves by callsign, never by username.'
            ),
            K::text(
                "CAS GUIDÉ — LA RÉPONSE DE LA RECRUE\nVous êtes moniteur pendant une session d’entraînement. Griffon 1 ordonne à l’équipe Griffon 1-3 de tenir le carrefour. La recrue qui tient la radio répond : « Ouais, roger wilco, c’est noté, over and out. »\n\nTravaillez dans cet ordre :\n1. Relevez les prowords employés.\n2. Vérifiez leur sens dans les sections 02 et 03.\n3. Vérifiez la structure de l’appel (section 04).\n4. Choisissez la correction complète.",
                "GUIDED CASE — THE RECRUIT’S REPLY\nYou are a coach during a training session. Griffon 1 orders team Griffon 1-3 to hold the crossroads. The recruit on the radio answers: “Yeah, roger wilco, got it, over and out.”\n\nWork in this order:\n1. List the prowords used.\n2. Check their meaning in sections 02 and 03.\n3. Check the call structure (section 04).\n4. Choose the complete correction."
            ),
            K::text(
                'Relisez les sections 02 et 03 : deux paires de prowords ne s’emploient jamais ensemble.',
                'Reread sections 02 and 03: two pairs of prowords are never used together.'
            )
        );
        $simulation = K::stage(
            K::question(
                'Que transmettez-vous ?',
                'What do you transmit?',
                [
                    K::text('« Griffon 1, THIS IS Griffon 2, ROGER, OUT », puis vous cherchez le blindé sur la carte.', '“Griffon 1, THIS IS Griffon 2, ROGER, OUT”, then you look for the armoured vehicle on the map.'),
                    K::text('« Griffon 1, THIS IS Griffon 2, SAY AGAIN, OVER », pour faire répéter tout le message.', '“Griffon 1, THIS IS Griffon 2, SAY AGAIN, OVER”, to have the whole message repeated.'),
                    K::text('« Griffon 1, THIS IS Griffon 2, SAY AGAIN ALL AFTER blindé, OVER. »', '“Griffon 1, THIS IS Griffon 2, SAY AGAIN ALL AFTER armoured vehicle, OVER.”'),
                ],
                2,
                'La partie manquante est la fin du message, après « blindé » : SAY AGAIN ALL AFTER la récupère sans tout répéter, et la demande vient avant tout accusé de réception. Répondre ROGER reviendrait à confirmer un message incomplet.',
                'The missing part is the end of the message, after “armoured vehicle”: SAY AGAIN ALL AFTER recovers it without repeating everything, and the request comes before any receipt. Answering ROGER would confirm an incomplete message.'
            ),
            K::text(
                "SIMULATION AUTONOME — LE MESSAGE COUPÉ\nVous êtes Griffon 2, chef de la deuxième section, sur le réseau compagnie. Griffon 1 transmet : « Griffon 2, THIS IS Griffon 1, blindé ennemi signalé… » puis une explosion couvre la suite : vous n’entendez ni la position ni la direction de marche. Griffon 1 termine par « OVER ».\n\nVotre objectif : récupérer exactement ce qui manque, avec le moins de temps d’antenne possible. Aucune aide n’est donnée pendant cette étape ; vous pourrez revoir le cours après une tentative.",
                "INDEPENDENT SIMULATION — THE CUT-OFF MESSAGE\nYou are Griffon 2, leader of the second section, on the company net. Griffon 1 transmits: “Griffon 2, THIS IS Griffon 1, enemy armoured vehicle reported…” then an explosion covers the rest: you hear neither the position nor the direction of travel. Griffon 1 ends with “OVER”.\n\nYour objective: recover exactly what is missing, with as little airtime as possible. No hint is given during this stage; you may review the course after an attempt."
            )
        );
        $questions = [
            K::question(
                'Dans quel ordre se construit un appel ?',
                'In which order is a call built?',
                [
                    K::text('Indicatif appelant, indicatif appelé, texte, OUT.', 'Calling callsign, called callsign, text, OUT.'),
                    K::text('Indicatif appelé, THIS IS, indicatif appelant, texte, OVER.', 'Called callsign, THIS IS, calling callsign, text, OVER.'),
                    K::text('Texte, puis les deux indicatifs, puis ROGER.', 'Text, then both callsigns, then ROGER.'),
                ],
                1,
                'On nomme d’abord la station appelée, pour qu’elle tende l’oreille, puis THIS IS et son propre indicatif. La transmission se termine par OVER si une réponse est attendue.',
                'The called station is named first so that it listens, then THIS IS and your own callsign. The transmission ends with OVER if a reply is expected.'
            ),
            K::question(
                'Que signifie WILCO ?',
                'What does WILCO mean?',
                [
                    K::text('J’ai reçu, compris et j’exécuterai.', 'I have received and understood, and I will comply.'),
                    K::text('J’ai reçu votre transmission, sans plus.', 'I have received your transmission, nothing more.'),
                    K::text('Je vous rappelle plus tard.', 'I will call you back later.'),
                ],
                0,
                'WILCO inclut le sens de ROGER et ajoute l’engagement d’exécuter. Seul le destinataire de l’ordre l’emploie. « Je vous rappelle » correspond à WAIT OUT.',
                'WILCO includes the meaning of ROGER and adds the commitment to comply. Only the addressee of the order uses it. “I will call you back” is WAIT OUT.'
            ),
            K::question(
                'Pourquoi ne dit-on jamais « over and out » ?',
                'Why is “over and out” never said?',
                [
                    K::text('Parce que c’est trop long.', 'Because it is too long.'),
                    K::text('Parce que OUT est réservé au poste de contrôle.', 'Because OUT is reserved for the control station.'),
                    K::text('Parce que OVER attend une réponse et OUT n’en attend aucune : les deux sens s’excluent.', 'Because OVER expects a reply and OUT expects none: the two meanings exclude each other.'),
                ],
                2,
                'Chaque transmission se termine par l’un ou l’autre, jamais les deux. Toute station peut terminer par OUT.',
                'Every transmission ends with one or the other, never both. Any station may end with OUT.'
            ),
            K::question(
                'Vous vous trompez de chiffre en pleine transmission. Que dites-vous ?',
                'You say the wrong figure in the middle of a transmission. What do you say?',
                [
                    K::text('CORRECTION, puis le dernier mot correct, et vous continuez.', 'CORRECTION, then the last correct word, and you carry on.'),
                    K::text('WRONG, puis vous recommencez tout le message.', 'WRONG, then you start the whole message again.'),
                    K::text('Rien : le destinataire corrigera de lui-même.', 'Nothing: the addressee will correct it themselves.'),
                ],
                0,
                'CORRECTION signale une erreur de l’émetteur ; la transmission reprend au dernier mot correct. WRONG sert à l’émetteur pour signaler une erreur dans le collationnement du destinataire.',
                'CORRECTION flags the sender’s own error; transmission resumes from the last correct word. WRONG is used by the sender to flag an error in the addressee’s read-back.'
            ),
            K::question(
                'Le destinataire collationne une grille sans erreur. Que répond l’émetteur ?',
                'The addressee reads back a grid reference without error. What does the sender answer?',
                [
                    K::text('« ROGER WILCO, OVER. »', '“ROGER WILCO, OVER.”'),
                    K::text('« CORRECT, OUT. »', '“CORRECT, OUT.”'),
                    K::text('« I READ BACK, OVER. »', '“I READ BACK, OVER.”'),
                ],
                1,
                'CORRECT confirme un collationnement exact ; I READ BACK est la réponse du destinataire, pas de l’émetteur.',
                'CORRECT confirms an accurate read-back; I READ BACK is the addressee’s answer, not the sender’s.'
            ),
            K::question(
                'Vous avez besoin de dix secondes pour lire votre carte avant de répondre. Que faites-vous ?',
                'You need ten seconds to read your map before answering. What do you do?',
                [
                    K::text('Vous gardez l’alternat enfoncé en réfléchissant à voix haute.', 'You keep the press-to-talk held down while thinking aloud.'),
                    K::text('Vous ne dites rien : l’autre station patientera.', 'You say nothing: the other station will wait.'),
                    K::text('« WAIT », vous relâchez l’alternat, puis vous répondez ; si cela dure, « WAIT OUT » et vous rappelez.', '“WAIT”, you release the press-to-talk, then you answer; if it takes longer, “WAIT OUT” and you call back.'),
                ],
                2,
                'WAIT demande une courte pause sans interruption ; WAIT OUT annonce un nouvel appel plus tard. Garder l’alternat bloque le canal pour tout le réseau.',
                'WAIT asks for a short pause without interruption; WAIT OUT announces a new call later. Holding the press-to-talk blocks the channel for the whole net.'
            ),
            K::question(
                'Que signifie FETCH SUNRAY ?',
                'What does FETCH SUNRAY mean?',
                [
                    K::text('Envoyez-moi une fusée éclairante.', 'Send me an illumination flare.'),
                    K::text('Demandez au chef de la station de prendre le combiné.', 'Ask the commander at the station to come to the handset.'),
                    K::text('Passez sur le réseau de secours.', 'Switch to the alternate net.'),
                ],
                1,
                'SUNRAY est le titre de fonction du chef d’une station. FETCH SUNRAY demande qu’il vienne au combiné ; il répond SUNRAY SPEAKING.',
                'SUNRAY is the appointment title of the commander at a station. FETCH SUNRAY asks for them to come to the handset; they answer SUNRAY SPEAKING.'
            ),
        ];

        return K::chapter(
            K::text('Chapitre 2 — Prowords, structure d’un appel et collationnement', 'Chapter 2 — Prowords, call structure and read-back'),
            K::text($theoryFr, $theoryEn),
            K::text($demoFr, $demoEn),
            $guided,
            $simulation,
            $questions,
            K::text(
                "Rédigez un retour personnel sur le cas du message coupé et votre évaluation :\n1. La formule de SAY AGAIN que vous avez choisie et pourquoi.\n2. Les prowords que vous mélangiez avant ce chapitre.\n3. Une information de vos missions qui mérite toujours un READ BACK.\n4. Comment vous gérez une pause pour réfléchir sans bloquer le canal.\n5. Une habitude radio à corriger lors de votre prochaine mission.",
                "Write a personal reflection on the cut-off message case and your assessment:\n1. The SAY AGAIN form you chose and why.\n2. The prowords you used to mix up before this chapter.\n3. An item in your missions that always deserves a READ BACK.\n4. How you handle a pause to think without blocking the channel.\n5. One radio habit to correct in your next mission."
            ),
            [
                K::text('Structure d’appel restituée : appelé, THIS IS, appelant, texte, OVER/OUT.', 'Call structure restated: called, THIS IS, caller, text, OVER/OUT.'),
                K::text('Différences ROGER / WILCO et OVER / OUT expliquées.', 'Differences between ROGER / WILCO and OVER / OUT explained.'),
                K::text('Demande de répétition précise justifiée.', 'Precise repetition request justified.'),
                K::text('Information critique à collationner identifiée.', 'Critical item requiring read-back identified.'),
            ],
            [
                K::text('L’évaluation atteint au moins 80 % dans le délai prévu.', 'The assessment reaches at least 80% within the time limit.'),
                K::text('Le stagiaire passe un appel complet et termine correctement par OVER ou OUT.', 'The learner makes a complete call and ends it correctly with OVER or OUT.'),
                K::text('Le stagiaire collationne une grille et une heure à la demande, et réagit à WRONG.', 'The learner reads back a grid reference and a time on request, and reacts to WRONG.'),
                K::text('Aucun « roger wilco », « over and out » ni pseudonyme n’est employé.', 'No “roger wilco”, “over and out” or username is used.'),
            ],
            [
                K::sheet(
                    K::text('Fiche mémo — Prowords essentiels', 'Memo card — Essential prowords'),
                    K::text(
                        "FORMATION DE JEU (ARMA 3) — sans valeur de qualification réelle.\n\nAPPEL\n[Appelé] THIS IS [appelant] … OVER / OUT\nSEND — passez votre message.\n\nFIN DE TRANSMISSION\nOVER — j’attends une réponse.\nOUT — aucune réponse attendue. Jamais « over and out ».\n\nRÉCEPTION\nROGER — reçu.\nWILCO — reçu, compris, j’exécuterai (destinataire de l’ordre uniquement). Jamais « roger wilco ».\n\nRÉPÉTITION\nSAY AGAIN (ALL AFTER / ALL BEFORE / WORD AFTER / WORD BEFORE / FROM… TO…)\nI SAY AGAIN — je répète la partie demandée.\nSPEAK SLOWER — parlez moins vite.\nWORDS TWICE — conditions difficiles, chaque phrase deux fois.\n\nERREURS ET COLLATIONNEMENT\nCORRECTION — je me suis trompé, je reprends au dernier mot correct.\nREAD BACK (GRID, TIME…) — répétez-moi ce message.\nI READ BACK — je répète.\nCORRECT — collationnement exact.\nWRONG — erreur, voici la bonne version.\nDISREGARD THIS TRANSMISSION – OUT — ignorez la transmission en cours.\n\nPAUSE\nWAIT — courte pause (environ 5 s), personne n’interrompt ; trois au plus.\nWAIT OUT — je vous rappellerai par un nouvel appel.\n\nTITRES\nSUNRAY — le chef de la station · FETCH SUNRAY · SUNRAY SPEAKING.",
                        "IN-GAME TRAINING (ARMA 3) — no real-world qualification value.\n\nCALL\n[Called] THIS IS [caller] … OVER / OUT\nSEND — pass your message.\n\nENDING A TRANSMISSION\nOVER — I expect a reply.\nOUT — no reply expected. Never “over and out”.\n\nRECEIPT\nROGER — received.\nWILCO — received, understood, will comply (addressee of the order only). Never “roger wilco”.\n\nREPETITION\nSAY AGAIN (ALL AFTER / ALL BEFORE / WORD AFTER / WORD BEFORE / FROM… TO…)\nI SAY AGAIN — I repeat the requested part.\nSPEAK SLOWER — slow down.\nWORDS TWICE — difficult conditions, each phrase twice.\n\nERRORS AND READ-BACK\nCORRECTION — I made an error, resuming from the last correct word.\nREAD BACK (GRID, TIME…) — repeat this message back to me.\nI READ BACK — I am repeating.\nCORRECT — accurate read-back.\nWRONG — error, here is the correct version.\nDISREGARD THIS TRANSMISSION – OUT — ignore the transmission in progress.\n\nPAUSE\nWAIT — short pause (about 5 s), nobody interrupts; three at most.\nWAIT OUT — I will call you back with a new call.\n\nTITLES\nSUNRAY — the commander at the station · FETCH SUNRAY · SUNRAY SPEAKING."
                    )
                ),
            ]
        );
    }

    // ------------------------------------------------------------------ Chapitre 3 — Discipline, liaison, réseau ACRE

    /** @return array<string, mixed> */
    private static function chapterNet(): array
    {
        $theoryFr = <<<'FR'
01 — La discipline radio
L’ACP 125 fixe des règles obligatoires sur tout réseau. Toujours : employer la procédure correcte, assurer une veille permanente (quelqu’un écoute en permanence), vérifier qu’on est sur le bon canal, répondre aux appels dans l’ordre et sans délai, écouter avant d’émettre, relâcher l’alternat dès la fin de la phrase. Jamais : rompre un silence radio, faire des transmissions inutiles ou trop longues, bavarder, désigner quelqu’un par son nom (chez nous : son pseudonyme), parler plus vite que ne peut recevoir la station la plus mal placée, s’emporter ou être grossier.

02 — Le poste de contrôle du réseau
Chaque réseau a un poste de contrôle (NCS), en principe celui de l’échelon le plus élevé ; le chef est responsable de la discipline et l’exerce par ce poste. Sur un réseau libre, les stations s’appellent directement. Sur un réseau dirigé, elles demandent l’autorisation au poste de contrôle avant de passer leur message. SILENCE répété trois fois ordonne de cesser toute émission jusqu’à SILENCE LIFTED.

03 — La vérification de liaison
Une liaison est réputée bonne sauf avis contraire. La demande est RADIO CHECK (beaucoup de joueurs disent « how do you read » ; sur nos réseaux, employez RADIO CHECK). La réponse ROGER seule signifie « fort et clair ». Sinon, on décrit la force (LOUD, GOOD, WEAK, VERY WEAK, FADING) et la lisibilité (CLEAR, READABLE, UNREADABLE, DISTORTED, WITH INTERFERENCE, INTERMITTENT), par exemple « WEAK BUT READABLE ». L’ACP 125 proscrit les cotes du type « five by five ». NOTHING HEARD signale qu’une station appelée n’a pas répondu.

04 — Précédence et interruption
Les messages ont quatre niveaux de précédence, du plus bas au plus haut : ROUTINE, PRIORITY, IMMEDIATE, FLASH. La précédence répétée trois fois (« IMMEDIATE IMMEDIATE IMMEDIATE ») ordonne aux autres de cesser d’émettre jusqu’au passage du message. FLASH interrompt immédiatement. IMMEDIATE peut interrompre ; sur un réseau dirigé, le poste de contrôle autorise d’abord. PRIORITY n’interrompt qu’un long message ROUTINE. L’ACP 125 réserve l’interruption d’un message tactique au compte rendu de contact ennemi. Convention de jeu : la plupart des messages restent sans précédence annoncée ; IMMEDIATE sert au contact et au blessé urgent ; FLASH reste exceptionnel (tir ami en cours, par exemple).

05 — Les radios ACRE en mission
L’AN/PRC-343 est le poste individuel de section : portée d’environ 500 m dans ACRE, 16 blocs de 16 canaux. Le canal 13 du bloc 1 n’est pas le canal 13 du bloc 2 : vérifiez les deux. L’AN/PRC-152, poste portatif de réseau de combat, porte plus loin ; sous ACRE, son bouton supérieur sélectionne les six premiers canaux préréglés. Il relie les chefs au réseau de section ou de compagnie. Des postes plus puissants (AN/PRC-117F) servent aux liaisons lointaines.

06 — Ce qui coupe la liaison dans ACRE
ACRE tient compte du relief entre les postes, de la distance, de la puissance et de l’orientation de l’antenne, qui suit le dos du joueur : un joueur couché et un joueur debout peuvent mal s’entendre. L’alignement de l’antenne se change par le menu d’interaction ACE. Deux stations qui émettent en même temps se brouillent : écoutez avant d’émettre. En limite de portée, prenez de la hauteur, sortez du creux, changez d’antenne ou faites relayer.

07 — Plan de transmissions et ce qu’il faut retenir
L’ordre de mission donne le plan : indicatifs, réseau de chaque équipe et de la section (canal et bloc), réseau compagnie, poste de contrôle et canal de secours. Avant le départ, chaque station vérifie canal, bloc et volume ; le poste de contrôle fait un RADIO CHECK. À retenir : discipline, RADIO CHECK et ROGER, réception décrite en mots, précédence répétée trois fois pour interrompre.
FR;
        $theoryEn = <<<'EN'
01 — Radio discipline
ACP 125 sets mandatory rules on every net. Always: use correct procedure, keep a constant listening watch (someone is always listening), check you are on the right channel, answer calls in order and without delay, listen before transmitting, release the press-to-talk as soon as the sentence ends. Never: break radio silence, make unnecessary or overlong transmissions, chat, identify someone by name (for us: by username), speak faster than the worst-placed station can receive, lose your temper or swear.

02 — The net control station
Every net has a control station (NCS), normally that of the senior echelon; the commander is responsible for discipline and exercises it through that station. On a free net, stations call each other directly. On a directed net, they ask the control station for permission before passing their message. SILENCE spoken three times orders all transmissions to cease until SILENCE LIFTED.

03 — Radio checks
A link is assumed good unless reported otherwise. The request is RADIO CHECK (many players say “how do you read”; on our nets, use RADIO CHECK). The reply ROGER on its own means “loud and clear”. Otherwise, describe strength (LOUD, GOOD, WEAK, VERY WEAK, FADING) and readability (CLEAR, READABLE, UNREADABLE, DISTORTED, WITH INTERFERENCE, INTERMITTENT), for example “WEAK BUT READABLE”. ACP 125 rules out ratings such as “five by five”. NOTHING HEARD reports that a called station has not answered.

04 — Precedence and break-in
Messages have four precedence levels, from lowest to highest: ROUTINE, PRIORITY, IMMEDIATE, FLASH. The precedence spoken three times (“IMMEDIATE IMMEDIATE IMMEDIATE”) orders the others to stop transmitting until the message has been passed. FLASH breaks in at once. IMMEDIATE may break in; on a directed net, the control station authorises first. PRIORITY only interrupts a long ROUTINE message. ACP 125 reserves breaking into a tactical message for reporting enemy contact. Game convention: most messages carry no announced precedence; IMMEDIATE is used for contact and urgent casualties; FLASH stays exceptional (friendly fire in progress, for example).

05 — ACRE radios in missions
The AN/PRC-343 is the section’s personal role radio: range of about 500 m in ACRE, 16 blocks of 16 channels. Channel 13 on block 1 is not channel 13 on block 2: check both. The AN/PRC-152, a handheld combat-net radio, reaches further; in ACRE, its top knob selects the first six preset channels. It links leaders to the section or company net. More powerful sets (AN/PRC-117F) are used for long-distance links.

06 — What breaks the link in ACRE
ACRE takes into account the terrain between the radios, distance, power and antenna orientation, which follows the player’s back: a prone player and a standing player may hear each other poorly. Antenna alignment is changed through the ACE interaction menu. Two stations transmitting at once interfere with each other: listen before transmitting. At the edge of range, gain height, leave the dip, change antenna alignment or have the message relayed.

07 — Signal plan and key points
The mission orders give the plan: callsigns, the net for each team and for the section (channel and block), company net, control station and alternate channel. Before stepping off, every station checks channel, block and volume; the control station runs a RADIO CHECK. Key points: discipline, RADIO CHECK and ROGER, reception described in words, precedence spoken three times to break in.
EN;
        $demoFr = <<<'FR'
DÉMONSTRATION COMMENTÉE — OUVERTURE DU RÉSEAU COMPAGNIE ET INTERRUPTION
Situation de jeu : avant le départ, le poste de commandement de compagnie « Griffon » (poste de contrôle) vérifie le réseau compagnie sur AN/PRC-152. Indicatifs fictifs.

Griffon : « Toutes stations Griffon, THIS IS Griffon, RADIO CHECK, OVER. »
Griffon 1 : « Griffon, THIS IS Griffon 1, ROGER, OVER. »
Commentaire : ROGER seul vaut « fort et clair » : inutile d’en dire plus.

Griffon 2 : « Griffon, THIS IS Griffon 2, WEAK BUT READABLE, OVER. »
Commentaire : la réception est décrite par des mots de l’ACP, pas par une cote chiffrée.

Griffon : « Griffon 3, THIS IS Griffon, RADIO CHECK, OVER. » (silence) « THIS IS Griffon, Griffon 3 NOTHING HEARD, OUT. »
Commentaire : le poste de contrôle constate l’absence de réponse ; Griffon 3 vérifiera son canal préréglé.

Plus tard, Griffon 1 passe un long compte rendu logistique en ROUTINE. Griffon 2 est pris à partie.
Griffon 2 : « IMMEDIATE IMMEDIATE IMMEDIATE — Griffon, THIS IS Griffon 2, IMMEDIATE, OVER. »
Griffon : « Griffon 2, THIS IS Griffon, SEND, OVER. »
Commentaire : la précédence répétée trois fois fait taire le réseau ; le réseau étant dirigé, Griffon 2 attend l’autorisation du poste de contrôle. Griffon 1 reprendra son message ensuite.

À RETENIR
Un RADIO CHECK bref au départ, des mots normalisés pour décrire la réception, l’interruption réservée à ce qui ne peut attendre.
FR;
        $demoEn = <<<'EN'
ANNOTATED DEMONSTRATION — OPENING THE COMPANY NET AND BREAKING IN
Game situation: before stepping off, company HQ “Griffon” (control station) checks the company net on the AN/PRC-152. Fictional callsigns.

Griffon: “All stations Griffon, THIS IS Griffon, RADIO CHECK, OVER.”
Griffon 1: “Griffon, THIS IS Griffon 1, ROGER, OVER.”
Comment: ROGER on its own means “loud and clear”: nothing more is needed.

Griffon 2: “Griffon, THIS IS Griffon 2, WEAK BUT READABLE, OVER.”
Comment: reception is described with ACP words, not with a numeric rating.

Griffon: “Griffon 3, THIS IS Griffon, RADIO CHECK, OVER.” (silence) “THIS IS Griffon, Griffon 3 NOTHING HEARD, OUT.”
Comment: the control station records the lack of answer; Griffon 3 will check its preset channel.

Later, Griffon 1 is passing a long ROUTINE logistics report. Griffon 2 comes under fire.
Griffon 2: “IMMEDIATE IMMEDIATE IMMEDIATE — Griffon, THIS IS Griffon 2, IMMEDIATE, OVER.”
Griffon: “Griffon 2, THIS IS Griffon, SEND, OVER.”
Comment: the precedence spoken three times silences the net; as the net is directed, Griffon 2 waits for the control station’s authorisation. Griffon 1 will resume its message afterwards.

KEY POINT
A brief RADIO CHECK before stepping off, standard words to describe reception, breaking in kept for what cannot wait.
EN;
        $guided = K::stage(
            K::question(
                'Que répondez-vous au RADIO CHECK ?',
                'How do you answer the RADIO CHECK?',
                [
                    K::text('« Griffon 1, THIS IS Griffon 1-2, five by five, OVER. »', '“Griffon 1, THIS IS Griffon 1-2, five by five, OVER.”'),
                    K::text('« Griffon 1, THIS IS Griffon 1-2, WEAK BUT READABLE, OVER. »', '“Griffon 1, THIS IS Griffon 1-2, WEAK BUT READABLE, OVER.”'),
                    K::text('« Griffon 1, THIS IS Griffon 1-2, ROGER, OVER. »', '“Griffon 1, THIS IS Griffon 1-2, ROGER, OVER.”'),
                ],
                1,
                'ROGER seul signifierait « fort et clair », ce qui est faux ici. La réception faible mais compréhensible se décrit avec les mots de l’ACP : WEAK BUT READABLE. Les cotes « five by five » ne s’emploient pas.',
                'ROGER on its own would mean “loud and clear”, which is wrong here. Weak but understandable reception is described with ACP words: WEAK BUT READABLE. Ratings such as “five by five” are not used.'
            ),
            K::text(
                "CAS GUIDÉ — LE RADIO CHECK EN LIMITE DE PORTÉE\nVous êtes Griffon 1-2, en tête d’une patrouille, à environ 450 m de votre chef de section Griffon 1, sur l’AN/PRC-343. Griffon 1 lance : « Griffon 1-2, THIS IS Griffon 1, RADIO CHECK, OVER. » Vous l’entendez faiblement, mais chaque mot est compréhensible.\n\nTravaillez dans cet ordre :\n1. Rappelez ce que signifie ROGER en réponse à un RADIO CHECK.\n2. Choisissez le mot de force qui décrit votre réception.\n3. Choisissez le mot de lisibilité.\n4. Écartez toute cote chiffrée.",
                "GUIDED CASE — THE RADIO CHECK AT THE EDGE OF RANGE\nYou are Griffon 1-2, leading a patrol about 450 m from your section leader Griffon 1, on the AN/PRC-343. Griffon 1 calls: “Griffon 1-2, THIS IS Griffon 1, RADIO CHECK, OVER.” You hear him faintly, but every word is understandable.\n\nWork in this order:\n1. Recall what ROGER means in reply to a RADIO CHECK.\n2. Choose the strength word that describes your reception.\n3. Choose the readability word.\n4. Rule out any numeric rating."
            ),
            K::text(
                'Relisez la section 03 : ROGER seul a un sens précis en réponse à un RADIO CHECK.',
                'Reread section 03: ROGER on its own has a precise meaning in reply to a RADIO CHECK.'
            )
        );
        $simulation = K::stage(
            K::question(
                'Que faites-vous en premier ?',
                'What do you do first?',
                [
                    K::text('Vous vérifiez sur votre AN/PRC-343 le bloc et le canal donnés à l’ordre, puis vous écoutez avant de lancer un RADIO CHECK vers Griffon 1.', 'You check the block and channel given in the orders on your AN/PRC-343, then you listen before calling a RADIO CHECK to Griffon 1.'),
                    K::text('Vous passez sur le réseau compagnie avec le AN/PRC-152 d’un camarade pour demander où est votre section.', 'You switch to the company net with a teammate’s AN/PRC-152 to ask where your section is.'),
                    K::text('Vous criez « IMMEDIATE IMMEDIATE IMMEDIATE » sur votre canal pour forcer une réponse.', 'You shout “IMMEDIATE IMMEDIATE IMMEDIATE” on your channel to force an answer.'),
                ],
                0,
                'Un canal juste sur le mauvais bloc donne un réseau vide : on vérifie d’abord son propre poste, puis on écoute et on fait un RADIO CHECK. Encombrer le réseau compagnie ou abuser de la précédence pour un problème de réglage rompt la discipline radio.',
                'The right channel on the wrong block gives an empty net: check your own radio first, then listen and make a RADIO CHECK. Cluttering the company net or misusing precedence for a settings problem breaks radio discipline.'
            ),
            K::text(
                "SIMULATION AUTONOME — LE RÉSEAU MUET\nVous êtes Griffon 1-3, chef d’équipe. L’ordre a donné à la section le bloc 1, canal 3 de l’AN/PRC-343. Après un respawn, vous n’entendez plus rien sur votre poste depuis deux minutes, alors que votre chef de section, Griffon 1, est à 200 m et que l’équipe voisine semble recevoir des ordres. La situation est calme.\n\nVotre objectif : rétablir la liaison en respectant la discipline radio. Aucune aide n’est donnée pendant cette étape ; vous pourrez revoir le cours après une tentative.",
                "INDEPENDENT SIMULATION — THE SILENT NET\nYou are Griffon 1-3, team leader. The orders gave the section block 1, channel 3 on the AN/PRC-343. After a respawn, you have heard nothing on your radio for two minutes, although your section leader, Griffon 1, is 200 m away and the neighbouring team seems to be receiving orders. The situation is quiet.\n\nYour objective: restore the link while respecting radio discipline. No hint is given during this stage; you may review the course after an attempt."
            )
        );
        $questions = [
            K::question(
                'En réponse à un RADIO CHECK, que signifie ROGER seul ?',
                'In reply to a RADIO CHECK, what does ROGER on its own mean?',
                [
                    K::text('Je vous reçois mal, répétez.', 'I hear you badly, repeat.'),
                    K::text('Je vous reçois fort et clair.', 'I hear you loud and clear.'),
                    K::text('J’exécuterai votre ordre.', 'I will comply with your order.'),
                ],
                1,
                'Selon l’ACP 125, l’absence de commentaire sur la force et la lisibilité signifie une réception forte et claire. Toute autre réception se décrit avec les mots prévus.',
                'Under ACP 125, the absence of any comment on strength and readability means loud and clear reception. Any other reception is described with the set words.'
            ),
            K::question(
                'Laquelle de ces réponses de vérification de liaison est conforme à l’ACP 125 ?',
                'Which of these radio check replies complies with ACP 125?',
                [
                    K::text('« Four by four. »', '“Four by four.”'),
                    K::text('« Ça passe à peu près. »', '“It sort of works.”'),
                    K::text('« LOUD BUT DISTORTED. »', '“LOUD BUT DISTORTED.”'),
                ],
                2,
                'La réception se décrit par la force (LOUD, GOOD, WEAK, VERY WEAK, FADING) et la lisibilité (CLEAR, READABLE, UNREADABLE, DISTORTED, WITH INTERFERENCE, INTERMITTENT). Les cotes chiffrées sont proscrites.',
                'Reception is described by strength (LOUD, GOOD, WEAK, VERY WEAK, FADING) and readability (CLEAR, READABLE, UNREADABLE, DISTORTED, WITH INTERFERENCE, INTERMITTENT). Numeric ratings are ruled out.'
            ),
            K::question(
                'Quel est l’ordre des précédences, du plus bas au plus haut ?',
                'What is the order of precedence, from lowest to highest?',
                [
                    K::text('ROUTINE, PRIORITY, IMMEDIATE, FLASH.', 'ROUTINE, PRIORITY, IMMEDIATE, FLASH.'),
                    K::text('PRIORITY, ROUTINE, FLASH, IMMEDIATE.', 'PRIORITY, ROUTINE, FLASH, IMMEDIATE.'),
                    K::text('FLASH, IMMEDIATE, PRIORITY, ROUTINE.', 'FLASH, IMMEDIATE, PRIORITY, ROUTINE.'),
                ],
                0,
                'FLASH est la précédence la plus haute et ROUTINE la plus basse. La troisième réponse donne l’ordre inverse.',
                'FLASH is the highest precedence and ROUTINE the lowest. The third answer gives the reverse order.'
            ),
            K::question(
                'Que signifie une précédence répétée trois fois, par exemple « IMMEDIATE IMMEDIATE IMMEDIATE » ?',
                'What does a precedence spoken three times mean, for example “IMMEDIATE IMMEDIATE IMMEDIATE”?',
                [
                    K::text('Que le message doit être répété trois fois.', 'That the message must be repeated three times.'),
                    K::text('Cessez d’émettre : silence jusqu’à ce que la station qui interrompt ait passé son message.', 'Stop transmitting: silence until the station breaking in has passed its message.'),
                    K::text('Que le réseau passe en silence radio jusqu’à la fin de la mission.', 'That the net goes into radio silence until the end of the mission.'),
                ],
                1,
                'C’est la procédure d’interruption : les autres stations se taisent le temps du message. Sur un réseau dirigé, IMMEDIATE attend l’autorisation du poste de contrôle.',
                'This is the break-in procedure: other stations stay silent while the message is passed. On a directed net, IMMEDIATE waits for the control station’s authorisation.'
            ),
            K::question(
                'Votre AN/PRC-343 est sur le canal 3 mais rien ne passe. Quelle vérification ACRE faites-vous en premier ?',
                'Your AN/PRC-343 is on channel 3 but nothing comes through. Which ACRE check do you make first?',
                [
                    K::text('Le volume du jeu dans les options graphiques.', 'The game volume in the graphics options.'),
                    K::text('La météo de la mission.', 'The mission weather.'),
                    K::text('Le bloc : le canal 3 du bloc 2 n’est pas le canal 3 du bloc 1.', 'The block: channel 3 on block 2 is not channel 3 on block 1.'),
                ],
                2,
                'L’AN/PRC-343 offre 16 blocs de 16 canaux ; changer de bloc garde le numéro de canal mais change de réseau. Vérifiez bloc et canal donnés à l’ordre.',
                'The AN/PRC-343 offers 16 blocks of 16 channels; changing block keeps the channel number but changes the net. Check the block and channel given in the orders.'
            ),
            K::question(
                'Un camarade couché vous entend mal alors que vous êtes debout à 300 m. Quelle cause ACRE est plausible ?',
                'A prone teammate hears you poorly while you are standing 300 m away. Which ACRE cause is plausible?',
                [
                    K::text('L’orientation des antennes, qui suit le dos du joueur ; on peut la changer par l’interaction ACE.', 'Antenna orientation, which follows the player’s back; it can be changed through ACE interaction.'),
                    K::text('ACRE coupe toujours les joueurs couchés.', 'ACRE always cuts off prone players.'),
                    K::text('L’AN/PRC-343 ne porte pas au-delà de 100 m.', 'The AN/PRC-343 does not reach beyond 100 m.'),
                ],
                0,
                'ACRE modélise l’alignement des antennes par rapport au dos du joueur : couché, l’antenne n’est plus orientée de la même façon. Le relief entre les deux postes compte aussi. L’AN/PRC-343 porte à environ 500 m.',
                'ACRE models antenna alignment relative to the player’s back: when prone, the antenna is oriented differently. The terrain between the radios matters too. The AN/PRC-343 reaches about 500 m.'
            ),
            K::question(
                'Laquelle de ces pratiques respecte la discipline radio ?',
                'Which of these practices respects radio discipline?',
                [
                    K::text('Appeler un joueur par son pseudonyme pour aller plus vite.', 'Calling a player by their username to save time.'),
                    K::text('Écouter que le canal est libre avant d’émettre et relâcher l’alternat dès la fin de la phrase.', 'Listening that the channel is clear before transmitting and releasing the press-to-talk as soon as the sentence ends.'),
                    K::text('Commenter l’action sur le réseau compagnie pendant un calme.', 'Commenting on the action on the company net during a lull.'),
                ],
                1,
                'Écouter avant d’émettre et relâcher l’alternat sont des règles « toujours » de l’ACP 125. Désigner quelqu’un par son nom et bavarder font partie des « jamais ».',
                'Listening before transmitting and releasing the press-to-talk are “always” rules of ACP 125. Identifying someone by name and chatting are among the “nevers”.'
            ),
        ];

        return K::chapter(
            K::text('Chapitre 3 — Discipline radio, vérification de liaison et réseau ACRE en mission', 'Chapter 3 — Radio discipline, radio checks and the ACRE net in missions'),
            K::text($theoryFr, $theoryEn),
            K::text($demoFr, $demoEn),
            $guided,
            $simulation,
            $questions,
            K::text(
                "Rédigez un retour personnel sur le cas du réseau muet et votre évaluation :\n1. L’ordre de vos vérifications et pourquoi.\n2. Les mots de force et de lisibilité que vous emploierez désormais.\n3. Un cas de vos missions qui justifiait (ou non) une interruption IMMEDIATE.\n4. Une règle de discipline radio souvent oubliée sur nos serveurs.\n5. Votre routine radio avant le départ lors de la prochaine mission.",
                "Write a personal reflection on the silent net case and your assessment:\n1. The order of your checks and why.\n2. The strength and readability words you will now use.\n3. A case from your missions that did (or did not) justify an IMMEDIATE break-in.\n4. A radio discipline rule often forgotten on our servers.\n5. Your radio routine before stepping off in the next mission."
            ),
            [
                K::text('Vérification bloc, canal, volume et RADIO CHECK dans le bon ordre.', 'Block, channel, volume check and RADIO CHECK in the right order.'),
                K::text('Réponses de liaison en mots ACP, sans cote chiffrée.', 'Radio check replies in ACP words, without numeric ratings.'),
                K::text('Usage de la précédence justifié et mesuré.', 'Use of precedence justified and measured.'),
                K::text('Routine radio de départ concrète.', 'Concrete pre-departure radio routine.'),
            ],
            [
                K::text('L’évaluation atteint au moins 80 % dans le délai prévu.', 'The assessment reaches at least 80% within the time limit.'),
                K::text('Le stagiaire règle un AN/PRC-343 et un AN/PRC-152 selon un plan de transmissions donné.', 'The learner sets up an AN/PRC-343 and an AN/PRC-152 according to a given signal plan.'),
                K::text('Le stagiaire répond correctement à un RADIO CHECK, y compris en réception dégradée.', 'The learner answers a RADIO CHECK correctly, including in degraded reception.'),
                K::text('Le stagiaire tient une session sur réseau dirigé sans rompre la discipline radio.', 'The learner holds a session on a directed net without breaking radio discipline.'),
            ],
            [
                K::sheet(
                    K::text('Fiche mémo — Réseau, liaison et précédence', 'Memo card — Net, radio checks and precedence'),
                    K::text(
                        "FORMATION DE JEU (ARMA 3) — sans valeur de qualification réelle.\n\nAVANT LE DÉPART\nIndicatif · réseau d’équipe et de section (AN/PRC-343 : bloc + canal) · réseau compagnie (AN/PRC-152 : canal préréglé) · poste de contrôle · canal de secours · volume. Puis RADIO CHECK.\n\nVÉRIFICATION DE LIAISON\nRADIO CHECK → ROGER (= fort et clair)\nForce : LOUD · GOOD · WEAK · VERY WEAK · FADING\nLisibilité : CLEAR · READABLE · UNREADABLE · DISTORTED · WITH INTERFERENCE · INTERMITTENT\nPas de « five by five ». NOTHING HEARD = pas de réponse.\n\nPRÉCÉDENCE (bas → haut)\nROUTINE · PRIORITY · IMMEDIATE · FLASH\nPrécédence × 3 = silence jusqu’au passage du message. Réseau dirigé : attendre « SEND ».\n\nDISCIPLINE\nToujours : bonne procédure, veille, bon canal, écouter avant d’émettre, relâcher l’alternat.\nJamais : rompre le silence, bavarder, pseudonymes, transmissions longues, parler trop vite, s’emporter.\nSILENCE ×3 → silence jusqu’à SILENCE LIFTED.\n\nLIAISON DIFFICILE (ACRE)\nPrendre de la hauteur · sortir du creux · changer l’alignement d’antenne (interaction ACE) · faire relayer · WORDS TWICE.",
                        "IN-GAME TRAINING (ARMA 3) — no real-world qualification value.\n\nBEFORE STEPPING OFF\nCallsign · team and section net (AN/PRC-343: block + channel) · company net (AN/PRC-152: preset channel) · control station · alternate channel · volume. Then RADIO CHECK.\n\nRADIO CHECK\nRADIO CHECK → ROGER (= loud and clear)\nStrength: LOUD · GOOD · WEAK · VERY WEAK · FADING\nReadability: CLEAR · READABLE · UNREADABLE · DISTORTED · WITH INTERFERENCE · INTERMITTENT\nNo “five by five”. NOTHING HEARD = no answer.\n\nPRECEDENCE (low → high)\nROUTINE · PRIORITY · IMMEDIATE · FLASH\nPrecedence × 3 = silence until the message is passed. Directed net: wait for “SEND”.\n\nDISCIPLINE\nAlways: correct procedure, listening watch, right channel, listen before transmitting, release the press-to-talk.\nNever: break silence, chat, usernames, long transmissions, speaking too fast, losing your temper.\nSILENCE ×3 → silence until SILENCE LIFTED.\n\nDIFFICULT LINK (ACRE)\nGain height · leave the dip · change antenna alignment (ACE interaction) · have it relayed · WORDS TWICE."
                    )
                ),
                K::sheet(
                    K::text('Sources publiques', 'Public sources'),
                    K::text(
                        "FORMATION DE JEU (ARMA 3) — sans valeur de qualification réelle.\n\nRéférences publiques et non classifiées dont ce cours s’inspire (contenu reformulé et adapté au jeu) :\nACP 125(G), Communications Instructions — Radiotelephone Procedures, CCEB, 28 novembre 2016, UNCLASSIFIED (copie publique) : https://orwg.cap.gov/media/cms/ACP125GRadioTelephoneProceduresNOV2_EFFE1A51BA783.pdf\nOTAN — NATO phonetic alphabet, codes and signals : https://www.nato.int/en/news-and-events/articles/news/2017/12/21/nato-phonetic-alphabet-codes-and-signals\nWikipédia — NATO phonetic alphabet : https://en.wikipedia.org/wiki/NATO_phonetic_alphabet\nWikipédia — Procedure word : https://en.wikipedia.org/wiki/Procedure_word\nDocumentation ACRE2 — AN/PRC-343 : https://acre2.idi-systems.com/wiki/radios/an-prc-343\nDocumentation ACRE2 — Radio Signal Loss : https://acre2.idi-systems.com/wiki/user/radio-signal-loss\n\nLes conventions propres à nos missions (indicatifs, emploi de la précédence, plan de transmissions) sont des conventions de jeu de la communauté.",
                        "IN-GAME TRAINING (ARMA 3) — no real-world qualification value.\n\nPublic, unclassified references this course is inspired by (content reworded and adapted to the game):\nACP 125(G), Communications Instructions — Radiotelephone Procedures, CCEB, 28 November 2016, UNCLASSIFIED (public copy): https://orwg.cap.gov/media/cms/ACP125GRadioTelephoneProceduresNOV2_EFFE1A51BA783.pdf\nNATO — NATO phonetic alphabet, codes and signals: https://www.nato.int/en/news-and-events/articles/news/2017/12/21/nato-phonetic-alphabet-codes-and-signals\nWikipedia — NATO phonetic alphabet: https://en.wikipedia.org/wiki/NATO_phonetic_alphabet\nWikipedia — Procedure word: https://en.wikipedia.org/wiki/Procedure_word\nACRE2 documentation — AN/PRC-343: https://acre2.idi-systems.com/wiki/radios/an-prc-343\nACRE2 documentation — Radio Signal Loss: https://acre2.idi-systems.com/wiki/user/radio-signal-loss\n\nConventions specific to our missions (callsigns, use of precedence, signal plan) are community game conventions."
                    )
                ),
            ]
        );
    }
}
