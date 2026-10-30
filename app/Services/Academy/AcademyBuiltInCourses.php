<?php

declare(strict_types=1);

namespace App\Services\Academy;

/** Published, versioned Academy curricula shipped with the application. */
final class AcademyBuiltInCourses
{
    public const JTAC_KEY = 'jtac-licence-ath-academy';

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
        $theoryFr = <<<'FR'
OBJECTIF DU MODULE
Vous tenez le rôle de coordinateur pédagogique dans des scènes MILSIM entièrement fictives. À la fin du module, vous devez distinguer un fait d’une hypothèse, expliciter une incertitude, demander une clarification, suspendre un exercice lorsque sa poursuite n’est plus maîtrisée et produire un débriefing vérifiable. Le nom JTAC désigne ici le thème de la formation. Cette licence ATH-ACADEMY atteste un parcours interne de simulation ; elle ne constitue ni qualification professionnelle, ni habilitation aéronautique ou militaire.

01 — Comprendre le cadre et ses limites
Un scénario constitue un contrat d’apprentissage : objectif annoncé, participants identifiés, environnement connu et possibilité d’interruption. Avant de commencer, reformulez ce qui sera évalué. Dans ce module, nous évaluons la qualité du raisonnement et de la communication. L’intensité d’une scène et sa ressemblance avec une situation réelle ne prouvent aucune compétence. Les supports, les personnages et les lieux restent fictifs. Les procédures opérationnelles, le guidage d’armement et la désignation d’objectifs ne font pas partie de ce cursus. Une question hors périmètre doit être remise à l’instructeur et ne devient pas une consigne improvisée.

02 — Distinguer observation, interprétation et information reçue
Une observation rapporte un élément accessible : « Le panneau affiche exercice suspendu. » Une interprétation explique cet élément : « Le logiciel a probablement perdu la liaison. » Une information reçue cite sa source : « L’organisateur annonce une vérification technique. » Ces trois catégories sont utiles, mais elles ne doivent pas être confondues. Décrivez d’abord le fait, puis attribuez l’information et enfin marquez l’hypothèse comme telle. Une image arrêtée ne prouve ni l’absence de personnes ni l’état actuel d’un lieu. Conserver cette séparation permet à un autre participant de comprendre précisément ce qui reste à vérifier.

03 — Qualifier une incertitude sans la masquer
Une incertitude n’est pas une faute ; la dissimuler peut le devenir. Indiquez ce qui manque, pourquoi ce manque compte et qui peut le lever. « Je n’ai pas reçu la confirmation de présence des participants » est plus utile que « Tout semble bon ». Ne remplacez pas un renseignement absent par une valeur plausible et ne transformez pas une répétition en preuve. Deux messages copiés d’une même capture ne constituent pas deux sources indépendantes. Si une information décisive reste inconnue, gardez l’exercice en attente et demandez une confirmation explicite à l’organisateur.

04 — Reconnaître les facteurs humains
La fatigue, le stress et la pression du groupe réduisent l’attention. La familiarité crée aussi des automatismes : « Nous faisons toujours comme cela » n’est pas une vérification. Le biais de confirmation consiste à privilégier les éléments qui confortent une première impression. Pour le limiter, cherchez une explication alternative, relisez la consigne et demandez à un autre participant de reformuler le point incertain. Une pause courte et annoncée vaut mieux qu’une accélération confuse. Signaler une surcharge ou demander un remplacement est une action de maîtrise de l’exercice, pas un échec personnel.

05 — Construire un message pédagogique lisible
Un message utile permet de comprendre le contexte, le fait observé, la limite de connaissance et la demande attendue. Exemple : « Exercice Aster, atelier Hangar A : le panneau est arrêté depuis deux minutes. Je ne peux pas confirmer l’état actuel. Merci à l’organisateur de vérifier avant la reprise. » Utilisez des phrases courtes et des mots partagés par le groupe. Évitez les abréviations locales non expliquées et les pronoms ambigus comme « ils » lorsque plusieurs équipes sont présentes. Un message clair peut être complet sans être long ; supprimer l’incertitude pour gagner une ligne le rend moins fiable.

06 — Vérifier la compréhension
Entendre un message n’implique pas le comprendre. Une reformulation reprend le sens essentiel, notamment la condition de reprise et la responsabilité de la confirmation. Après « Attendez la confirmation de l’organisateur », une réponse appropriée est : « Je garde l’atelier en attente ; la reprise dépend de la confirmation de l’organisateur. » Un silence, un pictogramme de lecture ou une formule vague ne remplace pas cette compréhension. Si deux consignes se contredisent, citez-les et sollicitez leur clarification. N’inventez pas une priorité entre des messages dont l’autorité ou la date n’est pas connue.

07 — Respecter les personnes et la représentation des civils
Les participants doivent connaître le thème, les limites et la possibilité de se retirer. Aucun objectif d’immersion ne justifie l’humiliation, la discrimination ou la diffusion d’une donnée personnelle. Dans une scène comportant des personnages civils, leur présence ne doit pas être effacée par une hypothèse commode. Une identification incertaine reste incertaine ; un vêtement, un véhicule ou une proximité ne suffit pas à attribuer une intention. Au niveau pédagogique, la bonne décision peut être de suspendre la scène et de demander une clarification. Respecter ce doute fait partie de la compétence évaluée.

08 — Réagir à un incident technique
Une vidéo figée, une interface désynchronisée ou une connexion coupée altère la fiabilité des informations. Distinguez un incident de l’outil d’un événement de la fiction. Signalez le symptôme observable et son impact : « La capture ne se met plus à jour ; je ne peux pas confirmer la situation actuelle. » L’organisateur vérifie le système et annonce la reprise. Une réouverture de la page ne prouve pas que l’information est redevenue actuelle. Conservez les traces utiles, sans collecter les identifiants ou les conversations privées d’autres participants. Un incident réel de sécurité des personnes met fin à l’exercice au profit de l’assistance appropriée.

09 — Prendre et tracer une décision réversible
Dans ce module, décider signifie choisir de poursuivre un atelier connu, de demander une clarification ou de maintenir une pause. Fondez votre choix sur les éléments disponibles et les limites annoncées. Une reprise requiert une confirmation nouvelle qui répond au problème initial ; le simple passage du temps ne supprime pas ce problème. Notez la décision, sa raison et la personne chargée de confirmer la condition de reprise. Distinguez l’heure de votre note de celle du document reçu. Cette traçabilité rend la décision examinable et aide à corriger une erreur sans réécrire l’historique.

10 — Débriefer et démontrer les acquis
Le débriefing met en relation l’objectif, les faits observés, les choix effectués et leurs conséquences dans l’exercice. Commencez par une chronologie courte. Identifiez ensuite un point maîtrisé, une difficulté et une amélioration testable lors de la prochaine séance. « Je serai plus attentif » reste trop vague ; « Je noterai la source et la limite de chaque information avant de la transmettre » décrit une action observable. Le score du questionnaire apporte un indice de compréhension, mais il ne remplace pas le jugement de l’instructeur sur votre justification. La validation finale nécessite un examen réussi, un débriefing et une revue distincte par un instructeur habilité.

PARCOURS ET PREUVES ATTENDUES
Lisez le cours, examinez la démonstration, résolvez le cas guidé puis un cas différent sans aide. L’évaluation comporte dix questions, quinze minutes et un seuil de réussite de 80 %. Une tentative insuffisante reste dans l’historique et peut être suivie d’une nouvelle tentative. Remettez ensuite un débriefing personnel. L’instructeur vérifie les critères publiés avant de valider les acquis ; le temps passé ou l’abonnement ne produit aucune validation automatique.
FR;
        $theoryEn = <<<'EN'
MODULE OBJECTIVE
You act as a learning coordinator in entirely fictional MILSIM scenes. By the end of the module, you should distinguish facts from assumptions, state uncertainty, request clarification, pause an exercise when its continuation is no longer controlled and produce a reviewable debrief. JTAC names the theme of this course. The ATH-ACADEMY licence records an internal simulation learning pathway; it is neither a professional qualification nor an aviation or military authorisation.

01 — Understand the framework and its limits
A scenario is a learning agreement: a stated objective, identified participants, a known setting and an opportunity to stop. Before starting, restate what will be assessed. This module assesses reasoning and communication. The intensity of a scene and its resemblance to reality do not demonstrate competence. Materials, characters and places remain fictional. Operational procedures, weapon guidance and target designation are outside this curriculum. Refer questions outside this scope to the instructor; they must not become improvised instructions.

02 — Separate observation, interpretation and received information
An observation reports something accessible: “The panel says exercise paused.” An interpretation explains it: “The software has probably lost its connection.” Received information identifies its source: “The organiser reports a technical check.” All three categories are useful, but they must not be mixed. Describe the fact first, attribute received information and mark an assumption as an assumption. A still image proves neither the absence of people nor the current condition of a place. Maintaining this distinction allows another participant to understand exactly what still needs checking.

03 — State uncertainty instead of concealing it
Uncertainty is not a mistake; concealing it can become one. State what is missing, why it matters and who can resolve it. “I have not received confirmation that the participants are present” is more useful than “Everything looks fine.” Do not replace missing information with a plausible value or treat repetition as evidence. Two messages copied from the same screenshot are not two independent sources. If a decisive fact remains unknown, keep the exercise on hold and request explicit confirmation from the organiser.

04 — Recognise human factors
Fatigue, stress and group pressure reduce attention. Familiarity can also produce automatic behaviour: “We always do it this way” is not a check. Confirmation bias means favouring evidence that supports an initial impression. Reduce it by considering another explanation, rereading the instruction and asking another participant to restate the uncertain point. A brief announced pause is better than confused acceleration. Reporting overload or asking someone to take over helps maintain control of the exercise; it is not a personal failure.

05 — Write a readable learning message
A useful message conveys the context, observed fact, limit of knowledge and requested response. For example: “Aster exercise, Hangar A workshop: the panel has been frozen for two minutes. I cannot confirm the current state. Please have the organiser check before resuming.” Use short sentences and words shared by the group. Avoid unexplained local abbreviations and ambiguous pronouns such as “they” when several teams are involved. A clear message can be complete without being long; omitting uncertainty to save a line makes it less reliable.

06 — Check understanding
Hearing a message does not mean understanding it. A restatement captures the essential meaning, particularly the condition for resuming and responsibility for confirmation. After “Wait for the organiser’s confirmation,” an appropriate reply is: “I am keeping the workshop on hold; resuming depends on the organiser’s confirmation.” Silence, a read icon or a vague acknowledgement does not replace understanding. If two instructions contradict each other, quote them and request clarification. Do not invent a priority between messages whose authority or time is unknown.

07 — Respect people and the representation of civilians
Participants must know the theme, boundaries and right to withdraw. No immersion objective justifies humiliation, discrimination or disclosure of personal information. When a scene includes civilian characters, their presence must not be dismissed through a convenient assumption. An uncertain identification remains uncertain; clothing, a vehicle or proximity alone does not establish intent. In a learning setting, the appropriate decision can be to pause the scene and request clarification. Respecting this uncertainty is part of the assessed competence.

08 — Respond to a technical incident
Frozen video, an unsynchronised interface or a lost connection affects information reliability. Separate a tool malfunction from an event in the fiction. Report the observable symptom and its impact: “The image is no longer updating; I cannot confirm the current situation.” The organiser checks the system and announces resumption. Reopening a page does not prove that the information is current again. Keep useful evidence without collecting other participants’ credentials or private conversations. A real incident affecting people’s safety ends the exercise in favour of appropriate assistance.

09 — Make and record a reversible decision
In this module, deciding means continuing a known workshop, requesting clarification or keeping it paused. Base the choice on available evidence and stated limits. Resuming requires new confirmation that addresses the original issue; the passage of time alone does not remove it. Record the decision, the reason and the person responsible for confirming the resumption condition. Distinguish the time of your note from the time of the document received. This record makes the decision reviewable and helps correct a mistake without rewriting history.

10 — Debrief and demonstrate learning
A debrief connects the objective, observed facts, decisions and their consequences within the exercise. Start with a brief chronology. Identify something done well, a difficulty and an improvement that can be tested in the next session. “I will pay more attention” is too vague; “I will record the source and limit of each item of information before passing it on” describes an observable action. A quiz score indicates understanding but does not replace the instructor’s review of your reasoning. Final validation requires a passed assessment, a debrief and a separate review by an authorised instructor.

PATHWAY AND EXPECTED EVIDENCE
Read the course, examine the demonstration, solve the guided case and then a different case without assistance. The assessment has ten questions, a fifteen-minute limit and an 80% passing threshold. An unsuccessful attempt remains in the history and may be followed by another attempt. Then submit your personal debrief. The instructor checks the published criteria before validating learning; time spent and a subscription do not create automatic validation.
EN;
        $demonstrationFr = <<<'FR'
DÉMONSTRATION COMMENTÉE — EXERCICE ASTER / HANGAR A
Cadre fictif : un atelier de communication prépare l’accueil de figurants. Aucun déplacement réel ni aucune action opérationnelle n’est demandé. Camille tient le journal ; Noor organise l’exercice. L’objectif est de transmettre une information limitée sans la transformer en certitude.

Événement 1 — La capture
Camille reçoit une capture sur laquelle la porte du hangar semble fermée. Le document porte la mention « archive ». Un autre participant écrit : « Personne n’est présent, nous pouvons reprendre. »
Analyse : la capture montre une porte à un moment passé. Elle ne prouve pas la présence actuelle des figurants. La conclusion du participant est une interprétation, pas un fait vérifié.

Événement 2 — Le message de clarification
Camille répond : « Exercice Aster, atelier Hangar A. La capture reçue est marquée archive. Je ne peux pas confirmer la présence actuelle des participants. Noor, pouvez-vous vérifier leur situation et annoncer si l’atelier peut reprendre ? Je le garde en attente. »
Analyse : le contexte, la source, la limite et la demande sont explicites. Camille n’invente pas une information pour satisfaire la pression du groupe.

Événement 3 — La reformulation
Noor répond : « Compris : la capture est ancienne. L’atelier reste en attente pendant que je vérifie la présence des participants. Je communiquerai une confirmation nouvelle. »
Analyse : la reformulation porte sur la condition de reprise. Un simple « reçu » aurait laissé cette condition implicite.

Événement 4 — Une confirmation recevable
Noor annonce ensuite : « Vérification des participants terminée pour cet atelier. L’exercice peut reprendre dans le cadre prévu. » Camille consigne la nouvelle source et la décision, sans modifier le premier message archivé.
Analyse : la reprise est liée à une vérification qui traite le manque identifié. Elle n’est déduite ni du silence, ni du temps écoulé, ni de la réouverture de l’écran.

EXEMPLE DE JOURNAL
Fait : capture marquée archive. Information manquante : présence actuelle des participants. Décision : atelier en attente. Demande : vérification par Noor. Condition de reprise : confirmation nouvelle de l’organisateur. Résultat : confirmation reçue ; reprise annoncée. Point à améliorer : demander la date du document dès sa réception.

CE QUI EST DÉMONTRÉ
La compétence n’est pas d’avoir deviné correctement la situation. Elle consiste à rendre les limites visibles, obtenir une compréhension partagée et documenter une décision réversible. Le même raisonnement s’applique à une panne d’interface ou à deux versions contradictoires d’une consigne.
FR;
        $demonstrationEn = <<<'EN'
WORKED DEMONSTRATION — ASTER EXERCISE / HANGAR A
Fictional setting: a communication workshop is preparing to welcome role players. No real movement or operational action is requested. Camille keeps the log; Noor organises the exercise. The objective is to transmit limited information without turning it into certainty.

Event 1 — The screenshot
Camille receives a screenshot in which the hangar door appears closed. The document is labelled “archive.” Another participant writes: “Nobody is present; we can resume.”
Analysis: the screenshot shows a door at a previous moment. It does not establish the role players’ current presence. The participant’s conclusion is an interpretation, not a verified fact.

Event 2 — The clarification message
Camille replies: “Aster exercise, Hangar A workshop. The screenshot received is marked archive. I cannot confirm the participants’ current presence. Noor, could you check their situation and announce whether the workshop may resume? I am keeping it on hold.”
Analysis: context, source, limit and request are explicit. Camille does not invent information to satisfy group pressure.

Event 3 — The restatement
Noor replies: “Understood: the screenshot is old. The workshop remains on hold while I check that the participants are present. I will provide a new confirmation.”
Analysis: the restatement concerns the condition for resuming. A simple “received” would have left that condition implicit.

Event 4 — An adequate confirmation
Noor later announces: “The participant check for this workshop is complete. The exercise may resume within the agreed framework.” Camille records the new source and decision without altering the first archived message.
Analysis: resumption follows a check that addresses the identified gap. It is not inferred from silence, elapsed time or reopening the screen.

SAMPLE LOG
Fact: screenshot marked archive. Missing information: participants’ current presence. Decision: workshop on hold. Request: Noor to check. Resumption condition: new confirmation from the organiser. Result: confirmation received; resumption announced. Improvement: request the document date on receipt.

WHAT THIS DEMONSTRATES
The competence is not guessing the situation correctly. It is making limits visible, establishing shared understanding and documenting a reversible decision. The same reasoning applies to an interface malfunction or contradictory versions of an instruction.
EN;
        $guided = self::question(
            'Quel message transmettez-vous avant de poursuivre l’atelier Boréal ?',
            'Which message do you send before continuing the Boreal workshop?',
            [
                self::text('« Nous pouvons continuer : la capture précédente ne montrait personne. »', '“We can continue: the previous screenshot did not show anyone.”'),
                self::text('« Atelier Boréal : deux versions de la consigne circulent et leur date manque. Je garde l’atelier en attente ; l’organisateur peut-il confirmer la version applicable et la condition de reprise ? »', '“Boreal workshop: two versions of the instruction are circulating and their dates are missing. I am keeping the workshop on hold; can the organiser confirm the applicable version and condition for resuming?”'),
                self::text('« Je choisis la version la plus courte ; elle sera plus facile à suivre. »', '“I will choose the shorter version; it will be easier to follow.”'),
            ],
            1,
            'Le deuxième message décrit la contradiction, conserve l’incertitude et demande une confirmation à la personne responsable. La longueur d’un document et une ancienne capture ne permettent pas de choisir une version.',
            'The second message describes the contradiction, preserves uncertainty and requests confirmation from the responsible person. Document length and an old screenshot do not establish which version applies.'
        );
        $guided['brief'] = self::text(
            "CAS GUIDÉ — ATELIER BORÉAL\nDeux consignes décrivent le même atelier fictif. L’une demande une pause, l’autre annonce une reprise. Les copies reçues ne montrent ni leur date ni leur auteur. Un participant souhaite choisir immédiatement la plus courte.\n\nTravaillez dans cet ordre :\n1. Relevez les faits : deux versions contradictoires, date et auteur absents.\n2. Nommez l’incertitude : version actuellement applicable inconnue.\n3. Identifiez la personne capable de clarifier : l’organisateur.\n4. Gardez l’atelier en attente pendant la vérification.\n5. Choisissez le message qui rend ces éléments compréhensibles.",
            "GUIDED CASE — BOREAL WORKSHOP\nTwo instructions describe the same fictional workshop. One requests a pause; the other announces resumption. The received copies show neither date nor author. A participant wants to choose the shorter one immediately.\n\nWork in this order:\n1. Identify the facts: two contradictory versions, with no date or author.\n2. Name the uncertainty: the currently applicable version is unknown.\n3. Identify who can clarify it: the organiser.\n4. Keep the workshop on hold during the check.\n5. Choose the message that makes these points understandable."
        );
        $guided['hint'] = self::text(
            'Utilisez quatre repères : contexte, fait, limite, demande. Une reformulation doit permettre à l’organisateur de reconnaître exactement la vérification attendue.',
            'Use four anchors: context, fact, limit and request. A restatement should allow the organiser to identify exactly what needs checking.'
        );
        $simulation = self::question(
            'Quelle décision et quelle trace retenez-vous pour l’exercice Canopée ?',
            'Which decision and record do you choose for the Canopy exercise?',
            [
                self::text('Maintenir la pause, signaler que l’état actuel est inconnu et demander à l’organisateur une vérification nouvelle. Noter le document reçu, sa limite et la condition de reprise.', 'Keep the pause, report that the current state is unknown and ask the organiser for a new check. Record the document received, its limitation and the condition for resuming.'),
                self::text('Reprendre puisque plusieurs personnes ont partagé la même capture ; noter que plusieurs sources concordent.', 'Resume because several people shared the same screenshot; record that several sources agree.'),
                self::text('Modifier la date de la capture pour simplifier le journal, puis attendre que quelqu’un conteste la reprise.', 'Change the screenshot date to simplify the log, then wait for someone to object to resuming.'),
            ],
            0,
            'La capture partagée reste une source ancienne unique. Une confirmation nouvelle doit répondre au manque identifié. Le journal garde les documents et leurs limites sans réécrire l’historique.',
            'The shared screenshot remains a single old source. New confirmation must address the identified gap. The log preserves documents and their limitations without rewriting history.'
        );
        $simulation['brief'] = self::text(
            "SIMULATION AUTONOME — CANOPÉE\nVous coordonnez un atelier fictif à distance. Le panneau de suivi est figé. Trois participants transmettent la même capture et affirment que tous les figurants sont prêts. La capture est ancienne et aucun message récent de l’organisateur n’est disponible. Une personne indique aussi être fatiguée et ne plus comprendre quelle consigne est valable.\n\nVotre objectif : décider de l’état de l’atelier et choisir la trace à conserver. Le résultat attendu est un choix justifié par les informations disponibles. Vous ne disposez d’aucune aide pendant cette étape ; vous pourrez revenir au cours après une tentative si nécessaire.",
            "INDEPENDENT SIMULATION — CANOPY\nYou coordinate a fictional remote workshop. The monitoring panel is frozen. Three participants forward the same screenshot and claim all role players are ready. The screenshot is old and no recent message from the organiser is available. One person also reports being tired and no longer understanding which instruction applies.\n\nYour objective is to decide the workshop’s status and select the record to keep. The expected result is a choice justified by available information. No hint is provided during this stage; you may return to the course after an attempt if necessary."
        );

        return [
            'key' => self::JTAC_KEY,
            'title' => self::text('JTAC — Licence ATH-ACADEMY', 'JTAC — ATH-ACADEMY Licence'),
            'summary' => self::text(
                'Premier module JTAC de pratique MILSIM : observation, communication, gestion de l’incertitude et débriefing. Sept étapes, des cas fictifs distincts, dix questions chronométrées et une validation instructeur.',
                'First JTAC MILSIM practice module: observation, communication, uncertainty management and debriefing. Seven stages, distinct fictional cases, ten timed questions and instructor validation.'
            ),
            'license' => self::text('Licence de pratique interne ATH-ACADEMY · simulation fictive · sans qualification officielle', 'Internal ATH-ACADEMY practice licence · fictional simulation · no official qualification'),
            'version' => '1.0',
            'duration_minutes' => 90,
            'published' => true,
            'built_in' => true,
            'chapters' => [[
                'title' => self::text('Fondamentaux de coordination et de jugement en simulation', 'Foundations of coordination and judgement in simulation'),
                'theory' => self::text($theoryFr, $theoryEn),
                'demonstration' => self::text($demonstrationFr, $demonstrationEn),
                // Compatibility fields also allow the standard course editor to inspect this chapter.
                'question' => $guided['question'],
                'options' => $guided['options'],
                'answer' => $guided['answer'],
                'feedback' => $guided['feedback'],
                'guided' => $guided,
                'simulation' => $simulation,
                'assessment' => [
                    'instructions' => self::text(
                        'Répondez aux dix situations sans aide. Vous disposez de quinze minutes ; huit réponses correctes sont nécessaires. Le serveur calcule la note et conserve chaque tentative. Une expiration compte comme une tentative non validée. Après réussite, rédigez votre débriefing ; la licence reste soumise à la revue d’un instructeur distinct du stagiaire.',
                        'Answer the ten situations without assistance. You have fifteen minutes; eight correct answers are required. The server calculates the score and records every attempt. Expiry counts as an unsuccessful attempt. After passing, write your debrief; the licence still requires review by an instructor other than the learner.'
                    ),
                    'seconds' => 900,
                    'pass_percent' => 80,
                    'questions' => self::assessmentQuestions(),
                ],
                'debrief' => [
                    'prompt' => self::text(
                        "Rédigez un retour personnel sur le cas Canopée et votre évaluation. Structurez votre texte avec six éléments :\n1. L’objectif de l’atelier et les limites de votre rôle.\n2. Deux faits observés, puis une hypothèse identifiée comme telle.\n3. L’incertitude décisive et la personne capable de la lever.\n4. Votre décision, sa justification et sa condition de réexamen.\n5. Un point maîtrisé et une difficulté rencontrée.\n6. Une amélioration observable à tester lors d’une prochaine séance.\n\nExaminez au moins une autre réponse possible et expliquez son défaut. Ne recopiez pas simplement la correction : montrez le lien entre les preuves, votre choix et l’amélioration proposée.",
                        "Write a personal reflection on the Canopy case and your assessment. Structure the text around six elements:\n1. The workshop objective and limits of your role.\n2. Two observed facts, followed by an explicitly labelled assumption.\n3. The decisive uncertainty and who can resolve it.\n4. Your decision, reasoning and condition for reconsidering it.\n5. Something done well and a difficulty encountered.\n6. An observable improvement to test in a future session.\n\nConsider at least one alternative answer and explain its weakness. Do not simply copy the feedback: show the connection between evidence, your choice and the proposed improvement."
                    ),
                    'criteria' => self::debriefCriteria(),
                ],
                'validation' => [
                    'brief' => self::text(
                        'La validation confirme uniquement les acquis de ce module de simulation. Un instructeur habilité, distinct du stagiaire, examine les tentatives et le débriefing selon les critères ci-dessous, puis laisse une justification écrite. Une réussite au questionnaire ne remplace pas cette revue. Si un critère n’est pas démontré, l’instructeur organise une remédiation avant de valider ; il ne doit pas attester une compétence non observée.',
                        'Validation confirms learning within this simulation module only. An authorised instructor other than the learner reviews attempts and the debrief against the criteria below, then records a written justification. Passing the quiz does not replace this review. If a criterion has not been demonstrated, the instructor arranges further learning before validating; they must not attest to an unobserved competence.'
                    ),
                    'criteria' => self::validationCriteria(),
                ],
                'resources' => self::resources(),
            ]],
        ];
    }

    private static function assessmentQuestions(): array
    {
        return [
            self::question(
                'Une capture montre une porte fermée. Quelle affirmation reste un fait observable ?',
                'A screenshot shows a closed door. Which statement remains an observable fact?',
                [self::text('La porte apparaît fermée sur cette capture.', 'The door appears closed in this screenshot.'), self::text('Personne n’est présent dans le bâtiment.', 'Nobody is in the building.'), self::text('L’organisateur a forcément terminé sa vérification.', 'The organiser must have completed the check.')],
                0, 'Le fait porte sur le contenu de la capture. Il ne décrit pas une présence actuelle ou une action qui n’a pas été observée.', 'The fact concerns what the screenshot contains. It does not establish current presence or an action that was not observed.'
            ),
            self::question(
                'Deux participants recopient une même capture ancienne. Que représentent leurs messages ?',
                'Two participants copy the same old screenshot. What do their messages represent?',
                [self::text('Deux vérifications indépendantes.', 'Two independent checks.'), self::text('Une certitude nouvelle parce que le message est répété.', 'New certainty because the message is repeated.'), self::text('Une source ancienne unique, relayée deux fois.', 'One old source relayed twice.')],
                2, 'La répétition ne crée ni indépendance ni actualité. La provenance et la date du document restent déterminantes.', 'Repetition creates neither independence nor freshness. The document’s origin and date still matter.'
            ),
            self::question(
                'Le panneau d’un atelier se fige. Quel message décrit correctement la limite ?',
                'A workshop panel freezes. Which message correctly describes the limitation?',
                [self::text('Le scénario a forcément changé.', 'The scenario must have changed.'), self::text('Le panneau ne se met plus à jour ; je ne peux pas confirmer l’état actuel.', 'The panel is no longer updating; I cannot confirm the current state.'), self::text('La dernière image reste valable jusqu’à preuve du contraire.', 'The last image remains valid until someone proves otherwise.')],
                1, 'Un défaut de l’outil limite ce que l’on sait. Il ne prouve pas un événement de la fiction et ne prolonge pas la validité d’une ancienne image.', 'A tool malfunction limits what is known. It does not prove an event in the fiction or extend an old image’s validity.'
            ),
            self::question(
                'Une consigne de pause et une consigne de reprise sont reçues sans date. Quelle suite choisissez-vous ?',
                'A pause instruction and a resumption instruction arrive without dates. What do you do next?',
                [self::text('Choisir celle qui convient au groupe.', 'Choose whichever suits the group.'), self::text('Reprendre après une minute sans réponse.', 'Resume after one minute without a reply.'), self::text('Maintenir la pause et demander à l’organisateur de confirmer la version applicable.', 'Keep the pause and ask the organiser to confirm the applicable version.')],
                2, 'La contradiction n’est pas résolue par une préférence ou par le temps écoulé. La clarification doit porter sur la version applicable.', 'Preference and elapsed time do not resolve the contradiction. Clarification must identify the applicable version.'
            ),
            self::question(
                'Après « Attendez ma confirmation avant de reprendre », quelle réponse vérifie la compréhension ?',
                'After “Wait for my confirmation before resuming,” which reply checks understanding?',
                [self::text('Je garde l’atelier en attente ; je reprendrai après votre confirmation explicite.', 'I am keeping the workshop on hold; I will resume after your explicit confirmation.'), self::text('Vu.', 'Seen.'), self::text('Tout est normal.', 'Everything is normal.')],
                0, 'La reformulation reprend la décision et la condition de reprise. Un accusé de lecture ne montre pas la compréhension de cette condition.', 'The restatement captures the decision and resumption condition. A read acknowledgement does not demonstrate understanding of that condition.'
            ),
            self::question(
                'Une personne annonce sa fatigue et perd le fil des consignes. Quelle réponse favorise la maîtrise de l’exercice ?',
                'Someone reports fatigue and can no longer follow the instructions. Which response helps maintain control of the exercise?',
                [self::text('Lui demander de se taire pour préserver l’immersion.', 'Ask them to stay silent to preserve immersion.'), self::text('Annoncer une pause adaptée, reformuler les consignes et solliciter l’organisateur.', 'Announce an appropriate pause, restate the instructions and involve the organiser.'), self::text('Accélérer pour terminer avant qu’elle ne décroche davantage.', 'Speed up to finish before they lose track further.')],
                1, 'La fatigue est un facteur humain à prendre en compte. Faire remonter la limite et adapter l’atelier vaut mieux que la masquer.', 'Fatigue is a human factor to account for. Reporting the limitation and adapting the workshop is better than concealing it.'
            ),
            self::question(
                'Un personnage civil est mal identifié dans une scène fictive. Que permet cette information ?',
                'A civilian character is poorly identified in a fictional scene. What does this information allow you to conclude?',
                [self::text('Son intention est connue d’après son vêtement.', 'Their intention is known from their clothing.'), self::text('Sa présence peut être ignorée si elle gêne l’exercice.', 'Their presence may be ignored if it complicates the exercise.'), self::text('L’identification reste incertaine et mérite une clarification ; aucune intention ne doit être inventée.', 'Identification remains uncertain and needs clarification; no intention should be invented.')],
                2, 'L’incertitude reste une limite à expliciter. Une apparence ou une commodité de scénario ne suffit pas à attribuer une intention.', 'Uncertainty remains a limitation that must be stated. Appearance or scenario convenience does not establish intent.'
            ),
            self::question(
                'Quelle note rend une décision de pause examinable ?',
                'Which note makes a pause decision reviewable?',
                [self::text('Fait observé, information manquante, décision, raison et condition de reprise.', 'Observed fact, missing information, decision, reason and resumption condition.'), self::text('Seulement le nom de la personne avec laquelle on est en désaccord.', 'Only the name of the person one disagrees with.'), self::text('Une version modifiée de l’ancienne capture, sans conserver l’original.', 'An edited version of the old screenshot without keeping the original.')],
                0, 'Une trace utile relie les éléments connus et la décision sans réécrire les documents reçus.', 'A useful record connects known evidence to the decision without rewriting received documents.'
            ),
            self::question(
                'Quelle amélioration est assez concrète pour être vérifiée lors d’une prochaine séance ?',
                'Which improvement is concrete enough to check in a future session?',
                [self::text('Être meilleur.', 'Be better.'), self::text('Noter la source et la limite de chaque information avant de la transmettre.', 'Record the source and limitation of each item of information before passing it on.'), self::text('Obtenir une meilleure note sans changer sa méthode.', 'Get a higher score without changing the method.')],
                1, 'L’action proposée est observable et liée à la difficulté. Une intention générale ou un score souhaité ne décrit pas une méthode.', 'The proposed action is observable and linked to the difficulty. A general intention or desired score does not describe a method.'
            ),
            self::question(
                'Votre évaluation est réussie et votre débriefing est remis. Que signifie la dernière étape ?',
                'You have passed the assessment and submitted the debrief. What does the final stage mean?',
                [self::text('L’abonnement produit automatiquement une qualification officielle.', 'The subscription automatically produces an official qualification.'), self::text('Vous pouvez vous valider vous-même en tant que stagiaire.', 'You can validate yourself as the learner.'), self::text('Un instructeur distinct vérifie les acquis selon les critères et motive une validation interne de simulation.', 'A separate instructor checks learning against the criteria and explains an internal simulation validation.')],
                2, 'La licence reste interne à ATH-ACADEMY. L’évaluation, le débriefing et la revue instructeur sont des preuves distinctes ; aucune qualification réelle n’est délivrée.', 'The licence remains internal to ATH-ACADEMY. Assessment, debrief and instructor review are separate pieces of evidence; no real-world qualification is issued.'
            ),
        ];
    }

    private static function debriefCriteria(): array
    {
        return [
            self::text('Objectif et périmètre du rôle clairement exprimés.', 'Clearly stated objective and role boundaries.'),
            self::text('Faits, informations reçues et hypothèses distingués.', 'Facts, received information and assumptions distinguished.'),
            self::text('Incertitude décisive et vérification attendue identifiées.', 'Decisive uncertainty and expected check identified.'),
            self::text('Décision justifiée et condition de reprise explicite.', 'Justified decision and explicit resumption condition.'),
            self::text('Point maîtrisé et difficulté personnelle analysés.', 'A success and personal difficulty analysed.'),
            self::text('Amélioration observable accompagnée d’un moyen de vérification.', 'Observable improvement with a way of checking it.'),
        ];
    }

    private static function validationCriteria(): array
    {
        return [
            self::text('L’évaluation atteint au moins 80 % dans le délai prévu ; l’instructeur examine les tentatives et leurs limites.', 'The assessment reaches at least 80% within the time limit; the instructor reviews attempts and their limitations.'),
            self::text('Le stagiaire sépare au moins deux faits d’une hypothèse sans ajouter une information absente du cas.', 'The learner separates at least two facts from an assumption without adding information absent from the case.'),
            self::text('Le message analysé contient contexte, fait, limite et demande compréhensibles.', 'The analysed message contains an understandable context, fact, limit and request.'),
            self::text('Le choix de pause ou de clarification est relié à un manque réel et à une condition de réexamen.', 'The pause or clarification decision is linked to an actual gap and a condition for reconsideration.'),
            self::text('Le débriefing respecte les personnes, examine une alternative et propose une amélioration testable.', 'The debrief respects people, examines an alternative and proposes a testable improvement.'),
            self::text('La justification de l’instructeur cite les preuves examinées et rappelle le périmètre interne de la licence.', 'The instructor’s justification identifies the evidence reviewed and recalls the licence’s internal scope.'),
        ];
    }

    private static function resources(): array
    {
        return [
            [
                'title' => self::text('Fiche de référence — Contexte, fait, limite, demande', 'Reference sheet — Context, fact, limit, request'),
                'type' => 'document',
                'body' => self::text(
                    "USAGE\nRelisez cette fiche avant un atelier ou imprimez-la pour préparer votre debriefing. Elle concerne les messages pédagogiques de ce module.\n\nCONTEXTE — Quel atelier fictif et quelle partie de la consigne sont concernés ?\nFAIT — Qu’avez-vous effectivement vu ou reçu ? Citez la source d’une information reçue.\nLIMITE — Qu’est-ce qui reste inconnu, ancien, contradictoire ou techniquement indisponible ?\nDEMANDE — Quelle clarification attendez-vous et de qui ? Quelle condition permettra de réexaminer la pause ?\n\nEXEMPLE\n« Atelier Boréal : je reçois deux consignes contradictoires sans date. Je ne peux pas identifier la version applicable. L’organisateur peut-il la confirmer ? Je garde l’atelier en attente. »\n\nVÉRIFICATION\nLa personne qui reçoit le message peut-elle reformuler la limite et la condition de reprise ? Si elle doit les deviner, clarifiez votre message. Conservez les documents d’origine ; ne corrigez pas silencieusement leur date ou leur contenu.",
                    "USE\nRead this sheet before a workshop or print it to prepare your debrief. It concerns learning messages within this module.\n\nCONTEXT — Which fictional workshop and part of the instruction are involved?\nFACT — What did you actually observe or receive? Identify the source of received information.\nLIMIT — What remains unknown, old, contradictory or technically unavailable?\nREQUEST — What clarification do you expect, and from whom? What condition will allow the pause to be reconsidered?\n\nEXAMPLE\n“Boreal workshop: I have received two contradictory instructions without dates. I cannot identify the applicable version. Can the organiser confirm it? I am keeping the workshop on hold.”\n\nCHECK\nCan the recipient restate the limitation and the resumption condition? If they must guess, clarify the message. Keep original documents; do not silently change their date or contents."
                ),
            ],
            [
                'title' => self::text('Glossaire du module JTAC', 'JTAC module glossary'),
                'type' => 'document',
                'body' => self::text(
                    "JTAC — Appellation du thème de ce module de simulation. Le métier réel exige un cadre de qualification distinct ; ce cursus ne le remplace pas.\nMILSIM — Pratique de simulation dans un cadre ludique organisé. Les règles annoncées et le consentement des participants priment sur l’immersion.\nFait — Élément effectivement observable ou documenté, décrit sans lui ajouter une cause non vérifiée.\nHypothèse — Explication possible qui demande encore une vérification.\nSource — Origine identifiable d’une information. Plusieurs relais d’un même document ne créent pas plusieurs sources indépendantes.\nIncertitude — Limite de connaissance à signaler et à relier à une vérification attendue.\nReformulation — Expression du sens compris, permettant à l’émetteur de corriger une ambiguïté.\nPause pédagogique — Suspension annoncée d’un atelier en attendant une condition de reprise explicitement vérifiée.\nDébriefing — Analyse de l’objectif, des faits, des choix, des résultats et d’une amélioration concrète.\nValidation — Revue motivée de preuves d’apprentissage par un instructeur habilité distinct du stagiaire.\nLicence ATH-ACADEMY — Attestation interne de pratique en simulation, sans habilitation officielle.",
                    "JTAC — Name of this simulation module’s theme. The real profession requires a separate qualification framework; this curriculum does not replace it.\nMILSIM — Simulation practice within an organised recreational setting. Stated rules and participant consent take precedence over immersion.\nFact — Something actually observed or documented, described without adding an unverified cause.\nAssumption — A possible explanation that still requires checking.\nSource — Identifiable origin of information. Several relays of the same document do not create independent sources.\nUncertainty — A knowledge limitation to state and connect to an expected check.\nRestatement — Expression of what was understood, allowing the sender to correct ambiguity.\nLearning pause — An announced workshop suspension pending an explicitly checked resumption condition.\nDebrief — Analysis of the objective, facts, decisions, results and a concrete improvement.\nValidation — A reasoned review of learning evidence by an authorised instructor other than the learner.\nATH-ACADEMY licence — An internal simulation practice record without official authorisation."
                ),
            ],
            [
                'title' => self::text('Schéma de raisonnement — De l’information à la revue', 'Reasoning diagram — From information to review'),
                'type' => 'diagram',
                'body' => self::text(
                    "INFORMATION REÇUE\n↓ Identifier sa source et sa date\nFAIT OBSERVABLE / INFORMATION ATTRIBUÉE / HYPOTHÈSE\n↓ Séparer ces catégories\nLIMITE DÉCISIVE ?\n→ Oui : pause annoncée → demande de clarification → confirmation nouvelle\n→ Non : poursuivre seulement l’atelier prévu dans le cadre connu\n↓\nDÉCISION ET RAISON CONSIGNÉES\n↓\nDÉBRIEFING : objectif → faits → choix → résultat → amélioration\n↓\nREVUE INSTRUCTEUR : preuves examinées → critères → justification\n\nUne flèche ne remplace jamais une vérification. Si une confirmation ne répond pas au problème initial, la limite demeure.",
                    "INFORMATION RECEIVED\n↓ Identify its source and date\nOBSERVABLE FACT / ATTRIBUTED INFORMATION / ASSUMPTION\n↓ Separate these categories\nDECISIVE LIMITATION?\n→ Yes: announced pause → clarification request → new confirmation\n→ No: continue only the planned workshop within the known framework\n↓\nDECISION AND REASON RECORDED\n↓\nDEBRIEF: objective → facts → choice → result → improvement\n↓\nINSTRUCTOR REVIEW: evidence examined → criteria → justification\n\nAn arrow never replaces a check. If a confirmation does not address the original issue, the limitation remains."
                ),
            ],
            [
                'title' => self::text('Support de cours — Les sept étapes', 'Course slides — The seven stages'),
                'type' => 'slides',
                'body' => self::text(
                    "DIAPOSITIVE 1 — COURS THÉORIQUE\nComprendre le cadre, les faits, les sources, les facteurs humains et les limites. Question personnelle : quelle information ai-je tendance à supposer ?\n\nDIAPOSITIVE 2 — DÉMONSTRATION\nObserver le cas Aster. Relever la capture ancienne, la clarification, la reformulation et la condition de reprise.\n\nDIAPOSITIVE 3 — EXERCICE GUIDÉ\nRésoudre Boréal avec les quatre repères : contexte, fait, limite, demande.\n\nDIAPOSITIVE 4 — SIMULATION AUTONOME\nDécider pour Canopée sans aide. Plusieurs copies d’une même capture ne constituent pas plusieurs vérifications.\n\nDIAPOSITIVE 5 — ÉVALUATION\nDix questions en quinze minutes ; seuil de 80 %. La note est calculée par le serveur et les tentatives restent consultables.\n\nDIAPOSITIVE 6 — DÉBRIEFING\nRelier les preuves au choix et proposer une amélioration observable. Examiner une autre réponse possible.\n\nDIAPOSITIVE 7 — VALIDATION DES ACQUIS\nUn instructeur distinct examine les critères et motive la validation interne. Aucun résultat ne devient une qualification réelle.",
                    "SLIDE 1 — THEORY\nUnderstand the framework, facts, sources, human factors and limitations. Personal question: which information do I tend to assume?\n\nSLIDE 2 — DEMONSTRATION\nExamine Aster. Identify the old screenshot, clarification, restatement and resumption condition.\n\nSLIDE 3 — GUIDED EXERCISE\nSolve Boreal using four anchors: context, fact, limit and request.\n\nSLIDE 4 — INDEPENDENT SIMULATION\nDecide for Canopy without help. Several copies of the same screenshot do not constitute several checks.\n\nSLIDE 5 — ASSESSMENT\nTen questions in fifteen minutes; 80% passing threshold. The server calculates the score and attempts remain reviewable.\n\nSLIDE 6 — DEBRIEF\nConnect evidence to the choice and propose an observable improvement. Examine another possible answer.\n\nSLIDE 7 — VALIDATION OF LEARNING\nA separate instructor reviews the criteria and explains the internal validation. No result becomes a real-world qualification."
                ),
            ],
            [
                'title' => self::text('Fiche stagiaire — Journal et débriefing', 'Learner sheet — Log and debrief'),
                'type' => 'document',
                'body' => self::text(
                    "JOURNAL D’EXERCICE\nNom du cas :\nObjectif pédagogique :\nDocuments reçus et origine :\nFaits observés :\nHypothèses à vérifier :\nInformation décisive manquante :\nDécision et raison :\nPersonne sollicitée :\nCondition de reprise ou de réexamen :\nConfirmation reçue et résultat :\n\nDÉBRIEFING PERSONNEL\nCe que je cherchais à démontrer :\nCe que les traces permettent d’affirmer :\nLe choix que j’ai fait et pourquoi :\nUne autre réponse possible et sa limite :\nUn point que j’ai maîtrisé :\nUne difficulté rencontrée :\nMon amélioration concrète :\nComment je vérifierai cette amélioration :\n\nAvant remise, retirez toute donnée privée inutile. Le carnet personnel et le débriefing remis à l’instructeur sont deux espaces distincts : recopiez volontairement uniquement les éléments utiles à l’évaluation.",
                    "EXERCISE LOG\nCase name:\nLearning objective:\nDocuments received and origin:\nObserved facts:\nAssumptions to check:\nMissing decisive information:\nDecision and reason:\nPerson contacted:\nCondition for resuming or reconsidering:\nConfirmation received and result:\n\nPERSONAL DEBRIEF\nWhat I aimed to demonstrate:\nWhat the records support:\nThe choice I made and why:\nAnother possible answer and its limitation:\nSomething I did well:\nA difficulty encountered:\nMy concrete improvement:\nHow I will check that improvement:\n\nRemove unnecessary private information before submission. The personal notebook and debrief submitted to the instructor are separate spaces: deliberately copy only the material useful for assessment."
                ),
            ],
            [
                'title' => self::text('Grille instructeur — Licence ATH-ACADEMY', 'Instructor rubric — ATH-ACADEMY Licence'),
                'type' => 'document',
                'body' => self::text(
                    "OBJET DE LA REVUE\nConfirmer les acquis de communication et de jugement démontrés dans ce module fictif. La grille ne qualifie pas une compétence opérationnelle réelle.\n\nCRITÈRE A — COMPRÉHENSION\nAu moins 80 % à l’évaluation dans le délai. Examiner les tentatives ; un score ne dispense pas de lire le raisonnement.\nCRITÈRE B — QUALITÉ DES PREUVES\nAu moins deux faits et une hypothèse correctement distingués. Aucune information absente du cas présentée comme certaine.\nCRITÈRE C — MESSAGE\nContexte, fait, limite et demande compréhensibles. Condition de reprise explicitement formulée.\nCRITÈRE D — JUGEMENT\nDécision reliée à l’incertitude ; autre réponse possible examinée. Respect des participants et de leur droit à la pause.\nCRITÈRE E — PROGRESSION\nPoint maîtrisé, difficulté et amélioration observable présents dans le débriefing.\n\nJUSTIFICATION ATTENDUE\n« J’ai examiné [tentative et débriefing]. Les critères [A à E] sont démontrés par [éléments précis]. L’amélioration à tester est [action]. Je valide les acquis internes de ce module de simulation. »\n\nSI UN CRITÈRE MANQUE\nOrganiser une reprise pédagogique ciblée avant validation. Ne pas valider uniquement pour clôturer une inscription. L’instructeur ne valide jamais sa propre inscription.",
                    "REVIEW PURPOSE\nConfirm the communication and judgement learning demonstrated in this fictional module. The rubric does not certify a real operational competence.\n\nCRITERION A — UNDERSTANDING\nAt least 80% within the assessment time limit. Review attempts; a score does not replace reading the reasoning.\nCRITERION B — EVIDENCE QUALITY\nAt least two facts and one assumption correctly distinguished. No information absent from the case presented as certain.\nCRITERION C — MESSAGE\nUnderstandable context, fact, limit and request. Explicit resumption condition.\nCRITERION D — JUDGEMENT\nDecision linked to uncertainty; another possible answer examined. Respect for participants and their right to pause.\nCRITERION E — PROGRESS\nA success, difficulty and observable improvement included in the debrief.\n\nEXPECTED JUSTIFICATION\n“I reviewed [attempt and debrief]. Criteria [A to E] are demonstrated by [specific evidence]. The improvement to test is [action]. I validate the internal learning of this simulation module.”\n\nIF A CRITERION IS MISSING\nArrange focused further learning before validation. Do not validate simply to close an enrolment. The instructor never validates their own enrolment."
                ),
            ],
        ];
    }
}
