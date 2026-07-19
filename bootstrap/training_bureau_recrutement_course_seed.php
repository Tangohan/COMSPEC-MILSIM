<?php

declare(strict_types=1);

/**
 * Formation LMS « Bureau recrutement » : lire et instruire une candidature sur le portail.
 * Idempotent par tenant + slug `parcours-bureau-recrutement`.
 *
 * @param PDO $pdo Connexion SQL (comme run-migrations.php)
 */

function training_bureau_recrutement_course_thumbnail_path(): string
{
    return 'assets/images/les-etpes-de-recrutement.jpg';
}

function training_bureau_recrutement_course_banner_path(): string
{
    return 'assets/images/armee-de-terre-recrute-secretaire-assistant.jpg';
}

function training_bureau_recrutement_sync_course_cover(PDO $pdo, int $tenantId, string $slug): void
{
    $st = $pdo->prepare('UPDATE training_courses SET thumbnail_path = ?, banner_path = ? WHERE tenant_id = ? AND slug = ? LIMIT 1');
    $st->execute([
        training_bureau_recrutement_course_thumbnail_path(),
        training_bureau_recrutement_course_banner_path(),
        $tenantId,
        $slug,
    ]);
}

function run_training_bureau_recrutement_course_seed(PDO $pdo): void
{
    $chk = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'training_courses' LIMIT 1");
    if (!$chk || !$chk->fetch()) {
        echo "[ATTENTION] training_bureau_recrutement_course : table training_courses absente — ignoré.\n";

        return;
    }

    $slug = 'parcours-bureau-recrutement';
    $tenants = $pdo->query('SELECT id FROM tenants ORDER BY id ASC');
    if (!$tenants) {
        return;
    }
    while ($row = $tenants->fetch(PDO::FETCH_ASSOC)) {
        $tenantId = (int) ($row['id'] ?? 0);
        if ($tenantId < 1) {
            continue;
        }
        if (training_bureau_recrutement_course_exists($pdo, $tenantId, $slug)) {
            training_bureau_recrutement_sync_course_cover($pdo, $tenantId, $slug);

            continue;
        }
        $authorId = training_bureau_recrutement_resolve_author_user_id($pdo, $tenantId);
        if ($authorId < 1) {
            echo "  [ATTENTION] training_bureau_recrutement_course : tenant {$tenantId} — aucun utilisateur actif, ignoré.\n";

            continue;
        }
        training_bureau_recrutement_seed_one_tenant($pdo, $tenantId, $authorId);
    }
}

function run_training_bureau_recrutement_course_for_tenant(PDO $pdo, int $tenantId, ?int $authorUserId = null): void
{
    $chk = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'training_courses' LIMIT 1");
    if (!$chk || !$chk->fetch()) {
        return;
    }
    if ($tenantId < 1) {
        return;
    }
    $slug = 'parcours-bureau-recrutement';
    if (training_bureau_recrutement_course_exists($pdo, $tenantId, $slug)) {
        training_bureau_recrutement_sync_course_cover($pdo, $tenantId, $slug);

        return;
    }
    $authorId = $authorUserId !== null && $authorUserId > 0
        ? $authorUserId
        : training_bureau_recrutement_resolve_author_user_id($pdo, $tenantId);
    if ($authorId < 1) {
        return;
    }
    training_bureau_recrutement_seed_one_tenant($pdo, $tenantId, $authorId);
}

function training_bureau_recrutement_resolve_author_user_id(PDO $pdo, int $tenantId): int
{
    $st = $pdo->prepare('SELECT id FROM users WHERE tenant_id = ? AND status = ? ORDER BY id ASC LIMIT 1');
    $st->execute([$tenantId, 'active']);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    return $row ? (int) $row['id'] : 0;
}

function training_bureau_recrutement_course_exists(PDO $pdo, int $tenantId, string $slug): bool
{
    $st = $pdo->prepare('SELECT 1 FROM training_courses WHERE tenant_id = ? AND slug = ? LIMIT 1');
    $st->execute([$tenantId, $slug]);

    return (bool) $st->fetchColumn();
}

function training_bureau_recrutement_uuid_v4(): string
{
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

    return vsprintf('%s%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

/** @param list<string> $lines */
function training_bureau_recrutement_module_objectives_json(array $lines): string
{
    $clean = array_values(array_filter(array_map(static fn (string $x): string => trim($x), $lines), static fn (string $x): bool => $x !== ''));

    return json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';
}

/**
 * @param list<array{text:string,explain?:string,answers:list<array{t:string,ok:bool}>}> $questions
 */
function training_bureau_recrutement_seed_quiz_questions_for_module(PDO $pdo, int $quizId, array $questions, string $now): void
{
    $qIns = $pdo->prepare(
        'INSERT INTO training_quiz_questions (quiz_id, question_type, question_text, explanation, points, position, created_at) VALUES (?, ?, ?, ?, 1, ?, ?)'
    );
    $aIns = $pdo->prepare(
        'INSERT INTO training_quiz_answers (question_id, answer_text, is_correct, position) VALUES (?, ?, ?, ?)'
    );
    $qpos = 1;
    foreach ($questions as $q) {
        $qIns->execute([
            $quizId,
            'single_choice',
            $q['text'],
            $q['explain'] ?? null,
            $qpos,
            $now,
        ]);
        $qid = (int) $pdo->lastInsertId();
        $rows = $q['answers'];
        shuffle($rows);
        $apos = 1;
        foreach ($rows as $ans) {
            $aIns->execute([$qid, $ans['t'], $ans['ok'] ? 1 : 0, $apos]);
            ++$apos;
        }
        ++$qpos;
    }
}

function training_bureau_recrutement_course_description(): string
{
    return <<<'TXT'
Ce parcours forme les membres de l’équipe recrutement à utiliser le Bureau recrutement du portail : file des dossiers, fiche instructeur, portail candidat, décision, journal et bilan après trente jours. Il décrit le geste métier attendu — qui suit le dossier, comment échanger sans confusion, comment motiver une décision — sans remplacer le règlement de votre communauté.

Le ton reste institutionnel et pratique. Les scénarios reprennent les écrans réels. L’attestation valide la lecture du parcours sur le site ; elle ne remplace pas une habilitation opérationnelle décidée par votre unité.
TXT;
}

function training_bureau_recrutement_course_objectives(): string
{
    return "Repérer la file des dossiers et ouvrir une fiche instructeur\n"
        . "Distinguer référent, volontaires et fil de suivi candidat\n"
        . "Régler le portail candidat (pièces, statut affiché) sans exposer d’informations internes\n"
        . "Choisir une issue (acceptation, refus, attente, entretien) et rédiger le message associé\n"
        . "Utiliser le journal pour la traçabilité et le bilan à trente jours pour améliorer le processus\n"
        . "Réussir le questionnaire final sur les usages attendus du bureau";
}

function training_bureau_recrutement_course_short_description(): string
{
    return 'Instruire une candidature de bout en bout : file, fiche, portail, décision, journal et bilan.';
}

/** @return list<array<string, mixed>> */
function training_bureau_recrutement_module_specs(): array
{
    return [
        [
            'title' => 'Le bureau et la file',
            'subtitle' => 'Où vivent les candidatures',
            'minutes' => 18,
            'module_description' => 'Le Bureau recrutement regroupe la vue d’ensemble, la file des dossiers, le fil recruteurs et les réglages (délais, messages préfaits, offres). Ce module pose le cadre avant d’ouvrir une fiche.',
            'module_learning_objectives' => [
                'Expliquer le rôle du Bureau recrutement par rapport au reste du portail.',
                'Ouvrir la file et distinguer un dossier à traiter d’un dossier clos.',
                'Savoir quand utiliser le fil recruteurs plutôt que le journal d’un dossier.',
            ],
            'deck' => training_bureau_recrutement_deck_bureau(),
            'lesson_summary' => 'Vue d’ensemble du bureau, file des dossiers, délais d’alerte et messages préfaits.',
            'recap_html' => '<p><strong>À retenir</strong> : la file est votre tableau de bord opérationnel ; chaque dossier a une fiche instructeur dédiée. Les délais d’alerte signalent les dossiers qui attendent trop longtemps.</p>',
        ],
        [
            'title' => 'Lire une fiche dossier',
            'subtitle' => 'Récapitulatif, coordination, identité',
            'minutes' => 22,
            'module_description' => 'La fiche instructeur concentre le récapitulatif, la coordination (référent et volontaires), l’identité reçue et les pièces. Savoir la lire évite les décisions précipitées.',
            'module_learning_objectives' => [
                'Lire l’étape et le statut en tête de fiche.',
                'Désigner un référent ou se porter volontaire.',
                'Relier les informations d’identité aux pièces et à l’avis de poste.',
            ],
            'deck' => training_bureau_recrutement_deck_fiche(),
            'lesson_summary' => 'Structure de la fiche, coordination d’équipe et lecture de l’identité reçue.',
            'recap_html' => '<p><strong>À retenir</strong> : un dossier sans référent dérive. La coordination et le récapitulatif doivent être clairs avant d’ouvrir le portail ou la décision.</p>',
        ],
        [
            'title' => 'Portail candidat et échanges',
            'subtitle' => 'Ce que voit le candidat',
            'minutes' => 20,
            'module_description' => 'Le portail candidat est le canal sécurisé pour pièces, messages et suivi d’avancement. Ce module sépare ce qui est visible du côté candidat et ce qui reste interne.',
            'module_learning_objectives' => [
                'Activer ou restreindre l’envoi de pièces et d’audio.',
                'Choisir un affichage d’avancement clair pour le candidat.',
                'Utiliser le fil de suivi sans y coller des notes internes.',
            ],
            'deck' => training_bureau_recrutement_deck_portail(),
            'lesson_summary' => 'Réglages du portail, lien de suivi et bonne pratique des échanges.',
            'recap_html' => '<p><strong>À retenir</strong> : tout message sur le fil est potentiellement lu par le candidat. Les notes internes vont dans le journal de la fiche.</p>',
        ],
        [
            'title' => 'Décision, journal et bilan',
            'subtitle' => 'Clore correctement le dossier',
            'minutes' => 24,
            'module_description' => 'Accepter, refuser, mettre en attente ou proposer un entretien : chaque issue a un message et une suite. Le journal trace l’instruction ; le bilan à trente jours améliore le processus.',
            'module_learning_objectives' => [
                'Choisir l’issue adaptée et motiver le message au candidat.',
                'Enregistrer une note interne au bon moment du parcours.',
                'Comprendre le bilan équipe / candidat après trente jours.',
            ],
            'deck' => training_bureau_recrutement_deck_decision(),
            'lesson_summary' => 'Issues de décision, messages, journal de traçabilité et bilan à J+30.',
            'recap_html' => '<p><strong>À retenir</strong> : une décision sans message clair crée de la frustration. Le journal et le bilan ferment la boucle qualité.</p>',
        ],
    ];
}

function training_bureau_recrutement_deck_bureau(): array
{
    return [
        'version' => 2,
        'opening' => [
            'eyebrow' => 'Module 1',
            'title' => '',
            'lead' => 'Comprendre le Bureau recrutement comme espace de pilotage des candidatures.',
            'stats' => [
                ['label' => 'Durée indicative', 'value' => '~18 min'],
                ['label' => 'Public', 'value' => 'Équipe recrutement'],
                ['label' => 'Objectif', 'value' => 'Repères d’écran'],
            ],
        ],
        'closure' => [
            'title' => 'Synthèse — Le bureau et la file',
            'seen' => [
                'Le bureau regroupe file, analyses, délais et messages préfaits.',
                'La file priorise les dossiers à traiter.',
                'Chaque dossier s’ouvre en fiche instructeur complète.',
            ],
            'acquired' => [
                'Vous savez où entrer pour traiter une candidature.',
                'Vous distinguez réglages globaux et travail d’un dossier.',
            ],
            'nextHint' => 'Passez au module « Lire une fiche dossier ».',
        ],
        'slides' => [
            [
                'template' => 'title_hero',
                'title' => 'Le Bureau recrutement',
                'subtitle' => 'Un espace dédié, comme les formations',
                'body' => '<p>Le <strong>Bureau recrutement</strong> est l’espace où votre communauté pilote les candidatures : vue d’ensemble, file des dossiers, fil entre recruteurs, analyses, délais d’alerte et messages préfaits. Il ne remplace pas le forum ni le dossier personnel : il concentre l’instruction avant l’adhésion.</p>',
                'contextKicker' => 'Module 1 · Cadre',
                'surface' => 'elevated',
                'metric' => ['label' => 'Principe', 'value' => 'Un dossier = une fiche'],
                'cards' => [
                    ['label' => 'File', 'body' => 'Liste des candidatures à traiter ou déjà tranchées.'],
                    ['label' => 'Fiche', 'body' => 'Tout le détail d’un dossier pour instruire.'],
                    ['label' => 'Réglages', 'body' => 'Délais et messages types pour toute l’équipe.'],
                ],
            ],
            [
                'template' => 'reading_article',
                'title' => 'Lire la file sans se perdre',
                'subtitle' => 'Priorités et statuts',
                'body' => <<<'HTML'
<p>Dans la file, un dossier <strong>à traiter</strong> attend une décision. Un dossier accepté, refusé ou non admis est clos côté instruction, même si le journal reste consultable. Les délais d’alerte mettent en avant les dossiers trop anciens sans réponse.</p>
<div class="lms-reading-callout lms-reading-callout--info"><p><strong>Bon réflexe</strong> : ouvrir d’abord les dossiers signalés hors délai, puis les plus anciens sans référent.</p></div>
HTML
                ,
            ],
            [
                'template' => 'scenario_decision',
                'title' => 'Deux recruteurs ouvrent le même dossier',
                'context' => 'La file affiche plusieurs candidatures « à traiter ».',
                'situation' => '<p>Vous et un camarade vous apprêtez à instruire le même dossier sans vous être coordonnés.</p>',
                'options' => [
                    ['id' => 'a', 'text' => 'Se porter volontaire ou désigner un référent sur la fiche, puis travailler à partir de cette coordination.'],
                    ['id' => 'b', 'text' => 'Décider chacun de son côté et laisser le premier message gagnant.'],
                    ['id' => 'c', 'text' => 'Ignorer la fiche et répondre uniquement sur le forum public.'],
                    ['id' => 'd', 'text' => 'Supprimer le dossier pour éviter le doublon.'],
                ],
                'correctOptionId' => 'a',
                'explanation' => '<p>La coordination sur la fiche évite les messages contradictoires. Les autres options créent du bruit ou une perte d’information.</p>',
            ],
            [
                'template' => 'knowledge_check',
                'title' => 'Repères du bureau',
                'body' => "La file montre l’état global des candidatures.\nLes délais d’alerte signalent les dossiers qui stagnent.\nLes messages préfaits accélèrent une réponse claire, sans remplacer le jugement.\nLe fil recruteurs sert à l’équipe ; le journal d’un dossier sert à la traçabilité de ce dossier.",
            ],
        ],
    ];
}

function training_bureau_recrutement_deck_fiche(): array
{
    return [
        'version' => 2,
        'opening' => [
            'eyebrow' => 'Module 2',
            'title' => '',
            'lead' => 'Lire la fiche instructeur : récapitulatif, coordination et identité.',
            'stats' => [
                ['label' => 'Durée indicative', 'value' => '~22 min'],
                ['label' => 'Public', 'value' => 'Instructeurs'],
                ['label' => 'Objectif', 'value' => 'Lecture structurée'],
            ],
        ],
        'closure' => [
            'title' => 'Synthèse — Lire une fiche',
            'seen' => [
                'Le récapitulatif donne l’étape et le statut.',
                'Référent et volontaires clarifient qui suit le dossier.',
                'L’identité et les pièces fondent la décision.',
            ],
            'acquired' => [
                'Vous savez ordonner votre lecture de la fiche.',
                'Vous savez signaler votre implication sans attendre.',
            ],
            'nextHint' => 'Passez au module « Portail candidat et échanges ».',
        ],
        'slides' => [
            [
                'template' => 'title_hero',
                'title' => 'La fiche instructeur',
                'subtitle' => 'Tout le dossier sur une page',
                'body' => '<p>La fiche instructeur (ouverte avec le mode dossier) regroupe le <strong>récapitulatif</strong>, la <strong>coordination</strong>, le <strong>portail candidat</strong>, l’identité reçue, la décision, le journal et éventuellement le bilan. Un sommaire à droite permet de naviguer rapidement ; un guide animé explique chaque zone au premier passage.</p>',
                'contextKicker' => 'Module 2 · Lecture',
                'surface' => 'elevated',
                'metric' => ['label' => 'Ordre conseillé', 'value' => 'Récap → coordination → identité'],
                'cards' => [
                    ['label' => 'Récap', 'body' => 'Étape, statut, référent, nature du dossier.'],
                    ['label' => 'Coordination', 'body' => 'Qui pilote, qui aide.'],
                    ['label' => 'Identité', 'body' => 'Coordonnées, avis de poste, transmissions.'],
                ],
            ],
            [
                'template' => 'reading_article',
                'title' => 'Référent et volontaires',
                'subtitle' => 'Éviter les dossiers orphelins',
                'body' => <<<'HTML'
<p>Le <strong>référent</strong> est la personne qui assume l’instruction. Les <strong>volontaires</strong> indiquent qui peut aider. Si personne n’est indiqué, le dossier stagne : c’est un signal pour l’équipe, pas une panne du site.</p>
<div class="lms-reading-callout lms-reading-callout--info"><p><strong>À retenir</strong> : se porter volontaire n’oblige pas à décider seul ; cela rend visible la disponibilité.</p></div>
HTML
                ,
            ],
            [
                'template' => 'scenario_decision',
                'title' => 'Identité incomplète',
                'context' => 'Le candidat a déposé un formulaire court.',
                'situation' => '<p>Il manque un élément utile pour trancher. Le portail autorise encore l’envoi de pièces.</p>',
                'options' => [
                    ['id' => 'a', 'text' => 'Demander le complément via le suivi candidat, noter le geste dans le journal, puis décider.'],
                    ['id' => 'b', 'text' => 'Refuser immédiatement sans message.'],
                    ['id' => 'c', 'text' => 'Accepter « pour avancer » et corriger plus tard sans trace.'],
                    ['id' => 'd', 'text' => 'Publier les données manquantes sur le forum ouvert.'],
                ],
                'correctOptionId' => 'a',
                'explanation' => '<p>Le canal de suivi et le journal gardent la cohérence. Les autres options sont brusques, floues ou exposent des données.</p>',
            ],
            [
                'template' => 'fill_blanks',
                'title' => 'Vocabulaire fiche',
                'contextKicker' => 'Termes',
                'metric' => ['label' => 'Consigne', 'value' => 'Compléter'],
                'body' => '<p>La personne qui pilote l’instruction s’appelle le [[référent]].</p><p>Les membres qui signalent qu’ils peuvent aider sont les [[volontaires]].</p>',
            ],
        ],
    ];
}

function training_bureau_recrutement_deck_portail(): array
{
    return [
        'version' => 2,
        'opening' => [
            'eyebrow' => 'Module 3',
            'title' => '',
            'lead' => 'Configurer le portail candidat et séparer échanges publics et notes internes.',
            'stats' => [
                ['label' => 'Durée indicative', 'value' => '~20 min'],
                ['label' => 'Public', 'value' => 'Équipe recrutement'],
                ['label' => 'Objectif', 'value' => 'Canal candidat'],
            ],
        ],
        'closure' => [
            'title' => 'Synthèse — Portail et échanges',
            'seen' => [
                'Le portail contrôle pièces, audio et affichage d’avancement.',
                'Le fil de suivi est lu par le candidat.',
                'Les notes internes restent dans le journal.',
            ],
            'acquired' => [
                'Vous savez régler le portail pour un dossier.',
                'Vous évitez de mélanger consignes internes et messages candidats.',
            ],
            'nextHint' => 'Passez au module « Décision, journal et bilan ».',
        ],
        'slides' => [
            [
                'template' => 'title_hero',
                'title' => 'Le portail candidat',
                'subtitle' => 'Un lien sécurisé, un dossier à la fois',
                'body' => '<p>Le candidat suit son dossier via un <strong>lien sécurisé</strong>. Sur la fiche, vous décidez s’il peut déposer des fichiers ou un audio, et comment son avancement s’affiche. Ces réglages valent pour ce dossier seulement.</p>',
                'contextKicker' => 'Module 3 · Canal',
                'surface' => 'elevated',
                'metric' => ['label' => 'Règle', 'value' => 'Interne ≠ candidat'],
                'cards' => [
                    ['label' => 'Pièces', 'body' => 'Autoriser seulement ce qui est utile à l’instruction.'],
                    ['label' => 'Statut affiché', 'body' => 'Étapes automatiques ou message manuel clair.'],
                    ['label' => 'Fil', 'body' => 'Messages destinés au candidat, pas au staff seul.'],
                ],
            ],
            [
                'template' => 'reading_article',
                'title' => 'Ce qui ne doit pas aller sur le fil',
                'subtitle' => 'Confidentialité et clarté',
                'body' => <<<'HTML'
<p>Les débats internes (« on hésite encore », « avis divergents ») et les mentions de tiers n’ont pas leur place sur le fil candidat. Utilisez le <strong>journal</strong> de la fiche pour la traçabilité d’équipe, et le fil pour des messages actionnables : demande de pièce, confirmation de créneau, décision motivée.</p>
HTML
                ,
            ],
            [
                'template' => 'scenario_decision',
                'title' => 'Accès portail bloqué',
                'context' => 'La fiche signale un accès indisponible pour l’adresse du dossier.',
                'situation' => '<p>Souvent après une modération automatique. Le candidat ne peut plus ouvrir son suivi.</p>',
                'options' => [
                    ['id' => 'a', 'text' => 'Utiliser l’action de rétablissement prévue sur la fiche si le contexte le justifie, puis vérifier le lien de suivi.'],
                    ['id' => 'b', 'text' => 'Lui demander de créer un autre compte sans expliquer.'],
                    ['id' => 'c', 'text' => 'Publier le lien de suivi sur un canal public.'],
                    ['id' => 'd', 'text' => 'Ignorer et refuser le dossier pour « silence radio ».'],
                ],
                'correctOptionId' => 'a',
                'explanation' => '<p>La fiche prévoit un rétablissement maîtrisé. Les autres options contournent la sécurité ou sanctionnent à tort.</p>',
            ],
            [
                'template' => 'knowledge_check',
                'title' => 'Checklist portail',
                'body' => "Vérifier le lien de suivi avant d’écrire au candidat.\nN’autoriser les pièces que si l’instruction en a besoin.\nChoisir un libellé d’avancement compréhensible.\nRéserver le journal aux notes internes.",
            ],
        ],
    ];
}

function training_bureau_recrutement_deck_decision(): array
{
    return [
        'version' => 2,
        'opening' => [
            'eyebrow' => 'Module 4',
            'title' => '',
            'lead' => 'Trancher, tracer et tirer les leçons après trente jours.',
            'stats' => [
                ['label' => 'Durée indicative', 'value' => '~24 min'],
                ['label' => 'Public', 'value' => 'Instructeurs'],
                ['label' => 'Objectif', 'value' => 'Clôture propre'],
            ],
        ],
        'closure' => [
            'title' => 'Synthèse — Décision et clôture',
            'seen' => [
                'Chaque issue s’accompagne d’un message clair.',
                'Le journal conserve la chronologie sans écrire au candidat.',
                'Le bilan à trente jours améliore le processus.',
            ],
            'acquired' => [
                'Vous savez choisir une issue et la motiver.',
                'Vous savez où tracer et où faire le bilan.',
            ],
            'nextHint' => 'Validez vos acquis avec le questionnaire final.',
        ],
        'slides' => [
            [
                'template' => 'title_hero',
                'title' => 'Décider sans ambiguïté',
                'subtitle' => 'Acceptation, refus, attente, entretien',
                'body' => '<p>Sur la fiche, la zone <strong>Décision</strong> propose les issues métier. Un message joint (éventuellement un modèle préfait) part au candidat selon le choix. Un entretien demande un créneau. Après acceptation, vérifiez le rattachement au compte membre si besoin.</p>',
                'contextKicker' => 'Module 4 · Issue',
                'surface' => 'elevated',
                'metric' => ['label' => 'Qualité', 'value' => 'Message motivé'],
                'cards' => [
                    ['label' => 'Acceptation', 'body' => 'Bienvenue claire + suite d’adhésion.'],
                    ['label' => 'Refus / non admis', 'body' => 'Motif respectueux, sans jargon inutile.'],
                    ['label' => 'Attente / entretien', 'body' => 'Prochaine étape datée ou créneau proposé.'],
                ],
            ],
            [
                'template' => 'reading_article',
                'title' => 'Journal et bilan',
                'subtitle' => 'Traçabilité puis amélioration',
                'body' => <<<'HTML'
<p>Le <strong>journal</strong> enregistre réception, modération, échanges, décision et notes internes. Ajoutez une note pour un point de vigilance : elle n’est pas envoyée au candidat.</p>
<p>Après <strong>trente jours</strong>, l’équipe et le candidat peuvent laisser un bilan court. Ce n’est pas une nouvelle instruction : c’est un retour pour améliorer l’accueil et les délais.</p>
HTML
                ,
            ],
            [
                'template' => 'scenario_decision',
                'title' => 'Refus sans motif',
                'context' => 'Le dossier ne correspond pas au besoin.',
                'situation' => '<p>Vous devez clore. Un modèle de message existe pour les refus.</p>',
                'options' => [
                    ['id' => 'a', 'text' => 'Choisir le refus, adapter un message préfait avec un motif compréhensible, enregistrer.'],
                    ['id' => 'b', 'text' => 'Laisser le dossier « à traiter » indéfiniment.'],
                    ['id' => 'c', 'text' => 'Envoyer seulement « non » sans contexte.'],
                    ['id' => 'd', 'text' => 'Accepter puis exclure le membre le lendemain sans explication.'],
                ],
                'correctOptionId' => 'a',
                'explanation' => '<p>Une clôture motivée respecte le candidat et protège l’équipe. Les autres options créent de la confusion ou de la défiance.</p>',
            ],
            [
                'template' => 'knowledge_check',
                'title' => 'Clôture propre',
                'body' => "Choisir l’issue adaptée au fond du dossier.\nJoindre un message actionnable.\nTracer les points sensibles dans le journal.\nRevenir pour le bilan à trente jours si le dossier le permet.",
            ],
        ],
    ];
}

/** @return list<array{text:string,explain?:string,answers:list<array{t:string,ok:bool}>}> */
function training_bureau_recrutement_final_quiz_questions(): array
{
    return [
        [
            'text' => 'Où instruire concrètement une candidature côté équipe ?',
            'explain' => 'La fiche instructeur du Bureau recrutement concentre décision, portail et journal.',
            'answers' => [
                ['t' => 'Sur la fiche instructeur du dossier (Bureau recrutement)', 'ok' => true],
                ['t' => 'Uniquement dans un fil de forum public', 'ok' => false],
                ['t' => 'Uniquement par message privé hors site', 'ok' => false],
                ['t' => 'Dans le catalogue des formations', 'ok' => false],
            ],
        ],
        [
            'text' => 'À quoi sert la zone « Qui suit ce dossier ? » ?',
            'explain' => 'Référent et volontaires clarifient la coordination.',
            'answers' => [
                ['t' => 'Indiquer le référent et les volontaires qui aident', 'ok' => true],
                ['t' => 'Publier le dossier sur le site public', 'ok' => false],
                ['t' => 'Remplacer la décision finale', 'ok' => false],
                ['t' => 'Envoyer automatiquement un refus', 'ok' => false],
            ],
        ],
        [
            'text' => 'Où noter une remarque interne non destinée au candidat ?',
            'explain' => 'Le journal de la fiche est prévu pour la traçabilité d’équipe.',
            'answers' => [
                ['t' => 'Dans le journal de la fiche dossier', 'ok' => true],
                ['t' => 'Dans le fil de suivi candidat', 'ok' => false],
                ['t' => 'Dans le titre de l’avis de poste public', 'ok' => false],
                ['t' => 'Dans le message d’acceptation uniquement', 'ok' => false],
            ],
        ],
        [
            'text' => 'Que permet le portail candidat sur un dossier ?',
            'explain' => 'Canal sécurisé pour pièces et suivi d’avancement côté candidat.',
            'answers' => [
                ['t' => 'Suivi sécurisé, pièces éventuelles et affichage d’avancement', 'ok' => true],
                ['t' => 'Modifier les rôles de toute la communauté', 'ok' => false],
                ['t' => 'Supprimer le journal d’instruction', 'ok' => false],
                ['t' => 'Publier automatiquement sur le forum', 'ok' => false],
            ],
        ],
        [
            'text' => 'Lors d’un refus, la bonne pratique est de :',
            'explain' => 'Motiver clairement et clore le dossier.',
            'answers' => [
                ['t' => 'Choisir l’issue refus et joindre un message motivé', 'ok' => true],
                ['t' => 'Laisser le dossier ouvert sans réponse', 'ok' => false],
                ['t' => 'Accepter puis retirer l’accès sans prévenir', 'ok' => false],
                ['t' => 'Effacer toutes les traces du journal', 'ok' => false],
            ],
        ],
        [
            'text' => 'Le bilan après trente jours sert surtout à :',
            'explain' => 'Améliorer le processus, pas à retrancher la décision.',
            'answers' => [
                ['t' => 'Améliorer l’accueil et le suivi pour les prochaines candidatures', 'ok' => true],
                ['t' => 'Annuler automatiquement toute décision passée', 'ok' => false],
                ['t' => 'Remplacer le journal', 'ok' => false],
                ['t' => 'Ouvrir le dossier au public', 'ok' => false],
            ],
        ],
        [
            'text' => 'Deux personnes veulent instruire le même dossier. Que faire en premier ?',
            'explain' => 'La coordination sur la fiche évite les messages contradictoires.',
            'answers' => [
                ['t' => 'Clarifier référent / volontariat sur la fiche', 'ok' => true],
                ['t' => 'Envoyer chacun un message différent au candidat', 'ok' => false],
                ['t' => 'Supprimer le dossier', 'ok' => false],
                ['t' => 'Ignorer la fiche et décider hors site sans trace', 'ok' => false],
            ],
        ],
        [
            'text' => 'Les délais d’alerte du bureau servent à :',
            'explain' => 'Signaler les dossiers qui attendent trop longtemps.',
            'answers' => [
                ['t' => 'Repérer les dossiers qui stagnent trop longtemps', 'ok' => true],
                ['t' => 'Changer automatiquement le statut en accepté', 'ok' => false],
                ['t' => 'Effacer les candidatures anciennes', 'ok' => false],
                ['t' => 'Remplacer la décision humaine', 'ok' => false],
            ],
        ],
    ];
}

function training_bureau_recrutement_seed_one_tenant(PDO $pdo, int $tenantId, int $authorUserId): void
{
    $slug = 'parcours-bureau-recrutement';
    if (training_bureau_recrutement_course_exists($pdo, $tenantId, $slug)) {
        echo "  training_bureau_recrutement_course : tenant {$tenantId} — formation « {$slug} » déjà présente.\n";

        return;
    }

    $themeJson = json_encode([
        'accent' => '#047857',
        'accentRgb' => '4, 120, 87',
        'font' => "'IBM Plex Sans', system-ui, sans-serif",
        'radius' => '1.25rem',
        'variant' => 'default',
        'pedagogy_meta' => [
            'target_audience' => ['staff', 'équipe recrutement', 'référents RH'],
            'pedagogical_style' => 'structured_reference',
            'completion_message' => 'Parcours terminé : vous disposez des repères pour instruire une candidature dans le Bureau recrutement. Les décisions d’admission restent du ressort de votre unité.',
            'tags' => ['recrutement', 'candidature', 'instruction', 'portail candidat', 'bilan'],
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $uuid = training_bureau_recrutement_uuid_v4();
    $now = date('Y-m-d H:i:s');
    $specs = training_bureau_recrutement_module_specs();
    $totalMinutes = 0;
    foreach ($specs as $s) {
        $totalMinutes += (int) $s['minutes'];
    }

    $ownsTx = !$pdo->inTransaction();
    if ($ownsTx) {
        $pdo->beginTransaction();
    }
    try {
        $ins = $pdo->prepare(
            'INSERT INTO training_courses (
                tenant_id, uuid, title, slug, course_code, short_description, description, learning_objectives,
                theme_json, thumbnail_path, banner_path, category, level, language_code,
                estimated_minutes, passing_score, is_mandatory, is_certifying, validity_days, visibility,
                created_by, updated_by, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 1, NULL, ?, ?, ?, ?, ?)'
        );
        $ins->execute([
            $tenantId,
            $uuid,
            'Bureau recrutement — instruire une candidature',
            $slug,
            'RECRUT-101',
            training_bureau_recrutement_course_short_description(),
            training_bureau_recrutement_course_description(),
            training_bureau_recrutement_course_objectives(),
            $themeJson,
            training_bureau_recrutement_course_thumbnail_path(),
            training_bureau_recrutement_course_banner_path(),
            'Recrutement',
            'intermediaire',
            'fr',
            $totalMinutes,
            75.00,
            'published',
            $authorUserId,
            $authorUserId,
            $now,
            $now,
        ]);
        $courseId = (int) $pdo->lastInsertId();

        $stCol = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'training_courses' AND COLUMN_NAME = 'enrollment_policy_json' LIMIT 1");
        if ($stCol && $stCol->fetch()) {
            $pdo->prepare('UPDATE training_courses SET enrollment_policy_json = ? WHERE id = ?')->execute(['{}', $courseId]);
        }
        $stShow = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'training_courses' AND COLUMN_NAME = 'showcase_sort_order' LIMIT 1");
        if ($stShow && $stShow->fetch()) {
            $pdo->prepare('UPDATE training_courses SET showcase_sort_order = 3, showcase_badge = ? WHERE id = ?')->execute(['open', $courseId]);
        }
        $stScope = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'training_courses' AND COLUMN_NAME = 'lms_scope' LIMIT 1");
        if ($stScope && $stScope->fetch()) {
            $pdo->prepare("UPDATE training_courses SET lms_scope = 'tenant' WHERE id = ?")->execute([$courseId]);
        }
        $stLmsVer = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'training_courses' AND COLUMN_NAME = 'lms_created_with_version' LIMIT 1");
        if ($stLmsVer && $stLmsVer->fetch()) {
            $ver = 'seed';
            if (is_file(dirname(__DIR__) . '/config/lms_platform.php')) {
                /** @var array<string,mixed>|null $cfg */
                $cfg = @include dirname(__DIR__) . '/config/lms_platform.php';
                if (is_array($cfg) && isset($cfg['version']) && is_string($cfg['version']) && $cfg['version'] !== '') {
                    $ver = $cfg['version'];
                }
            }
            $pdo->prepare('UPDATE training_courses SET lms_created_with_version = ?, lms_last_saved_with_version = ? WHERE id = ?')->execute([$ver, $ver, $courseId]);
        }

        $modIns = $pdo->prepare(
            'INSERT INTO training_modules (course_id, title, description, subtitle, learning_objectives, estimated_minutes, position, is_required, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, ?)'
        );
        $lesIns = $pdo->prepare(
            'INSERT INTO training_lessons (module_id, title, summary, learning_objectives, instructor_notes, lesson_type, content, external_url, duration_minutes, difficulty, position, is_required)
             VALUES (?, ?, ?, NULL, NULL, ?, ?, NULL, ?, ?, ?, 1)'
        );
        $quizIns = $pdo->prepare(
            'INSERT INTO training_quizzes (module_id, title, description, passing_score, max_attempts, time_limit_minutes, randomize_questions, is_final_exam, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        $position = 1;
        $finalModuleId = 0;
        $nSpecs = count($specs);
        foreach ($specs as $mi => $m) {
            $title = (string) $m['title'];
            $sub = (string) $m['subtitle'];
            $minutes = (int) $m['minutes'];
            $descMod = trim((string) ($m['module_description'] ?? ''));
            if ($descMod === '') {
                $descMod = 'Module ' . ($mi + 1) . ' — ' . $sub;
            }
            $modLo = training_bureau_recrutement_module_objectives_json($m['module_learning_objectives'] ?? []);
            $modIns->execute([
                $courseId,
                $title,
                $descMod,
                $sub,
                $modLo,
                $minutes,
                $position,
                $now,
                $now,
            ]);
            $moduleId = (int) $pdo->lastInsertId();
            $position++;
            $finalModuleId = $moduleId;

            $summary = (string) $m['lesson_summary'];
            if (strlen($summary) > 500) {
                $summary = substr($summary, 0, 497) . '…';
            }
            $deckJson = json_encode($m['deck'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $lesIns->execute([
                $moduleId,
                $title . ' — parcours visuel',
                $summary,
                'canvas',
                $deckJson,
                max(6, (int) ceil($minutes * 0.65)),
                'intermediaire',
                1,
            ]);

            if (!empty($m['recap_html'])) {
                $recapTitle = ($mi === $nSpecs - 1) ? 'Avant le questionnaire final' : ('À retenir — ' . $title);
                $lesIns->execute([
                    $moduleId,
                    $recapTitle,
                    'Synthèse courte pour ancrer les idées du module.',
                    'richtext',
                    (string) $m['recap_html'],
                    5,
                    'intermediaire',
                    2,
                ]);
            }
        }

        if ($finalModuleId > 0) {
            $quizIns->execute([
                $finalModuleId,
                'Quiz — Bureau recrutement',
                'Validez vos acquis sur la file, la fiche, le portail candidat, la décision et le bilan.',
                75.00,
                5,
                25,
                1,
                1,
                $now,
            ]);
            $finalQz = (int) $pdo->lastInsertId();
            training_bureau_recrutement_seed_quiz_questions_for_module($pdo, $finalQz, training_bureau_recrutement_final_quiz_questions(), $now);
        }

        if ($ownsTx) {
            $pdo->commit();
        }
        echo "  training_bureau_recrutement_course : tenant {$tenantId} — formation « Bureau recrutement » créée (course_id={$courseId}).\n";
    } catch (\Throwable $e) {
        if ($ownsTx && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($ownsTx) {
            echo '  [ATTENTION] training_bureau_recrutement_course : ' . $e->getMessage() . "\n";
        } else {
            throw $e;
        }
    }
}
