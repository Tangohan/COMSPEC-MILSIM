<?php

declare(strict_types=1);

/**
 * Formation LMS « rôles, fonctions, spécialité, affectation » : publiée, certifiante, ouverte à tous (policy vide).
 * Idempotent par tenant + slug `parcours-postes-rbac`. Création uniquement si absent (pas de resynchronisation des leçons).
 *
 * @param PDO $pdo Connexion SQL (comme run-migrations.php)
 */
function run_training_roles_org_course_seed(PDO $pdo): void
{
    $chk = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'training_courses' LIMIT 1");
    if (!$chk || !$chk->fetch()) {
        echo "[ATTENTION] training_roles_org_course : table training_courses absente — ignoré.\n";

        return;
    }

    $slug = 'parcours-postes-rbac';
    $tenants = $pdo->query('SELECT id FROM tenants ORDER BY id ASC');
    if (!$tenants) {
        return;
    }
    while ($row = $tenants->fetch(PDO::FETCH_ASSOC)) {
        $tenantId = (int) ($row['id'] ?? 0);
        if ($tenantId < 1) {
            continue;
        }
        if (training_roles_org_course_exists($pdo, $tenantId, $slug)) {
            continue;
        }
        $authorId = training_roles_org_resolve_author_user_id($pdo, $tenantId);
        if ($authorId < 1) {
            echo "  [ATTENTION] training_roles_org_course : tenant {$tenantId} — aucun utilisateur actif, ignoré.\n";

            continue;
        }
        training_roles_org_seed_one_tenant($pdo, $tenantId, $authorId);
    }
}

/**
 * Crée la formation pour un seul tenant (ex. nouvelle communauté) si absente.
 */
function run_training_roles_org_course_for_tenant(PDO $pdo, int $tenantId, ?int $authorUserId = null): void
{
    $chk = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'training_courses' LIMIT 1");
    if (!$chk || !$chk->fetch()) {
        return;
    }
    if ($tenantId < 1) {
        return;
    }
    $slug = 'parcours-postes-rbac';
    if (training_roles_org_course_exists($pdo, $tenantId, $slug)) {
        return;
    }
    $authorId = $authorUserId !== null && $authorUserId > 0
        ? $authorUserId
        : training_roles_org_resolve_author_user_id($pdo, $tenantId);
    if ($authorId < 1) {
        return;
    }
    training_roles_org_seed_one_tenant($pdo, $tenantId, $authorId);
}

function training_roles_org_resolve_author_user_id(PDO $pdo, int $tenantId): int
{
    $st = $pdo->prepare('SELECT id FROM users WHERE tenant_id = ? AND status = ? ORDER BY id ASC LIMIT 1');
    $st->execute([$tenantId, 'active']);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    return $row ? (int) $row['id'] : 0;
}

function training_roles_org_course_exists(PDO $pdo, int $tenantId, string $slug): bool
{
    $st = $pdo->prepare('SELECT 1 FROM training_courses WHERE tenant_id = ? AND slug = ? LIMIT 1');
    $st->execute([$tenantId, $slug]);

    return (bool) $st->fetchColumn();
}

function training_roles_org_uuid_v4(): string
{
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

    return vsprintf('%s%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

/**
 * @param list<string> $lines
 */
function training_roles_org_module_objectives_json(array $lines): string
{
    $clean = array_values(array_filter(array_map(static fn (string $x): string => trim($x), $lines), static fn (string $x): bool => $x !== ''));

    return json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';
}

/**
 * @param list<array{text:string,explain?:string,answers:list<array{t:string,ok:bool}>}> $questions
 */
function training_roles_org_seed_quiz_questions_for_module(PDO $pdo, int $quizId, array $questions, string $now): void
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

function training_roles_org_course_description(): string
{
    return <<<'TXT'
Ce parcours explique comment votre communauté structure les responsabilités sur le portail : ce que recouvrent les rôles visibles dans les menus, comment le référentiel des fonctions (doctrine S1) s’articule avec ces rôles, où apparaît la spécialité (recrutement et dossier personnel), et comment se lit une affectation sur l’organigramme. Il vise un cadre commun pour le staff comme pour les membres : moins de malentendus sur « qui peut voir quoi » et sur la différence entre une fonction de référence et un poste réellement occupé.

Le ton reste institutionnel et pratique. Les encadrés signalent les confusions fréquentes. Le questionnaire final vérifie la bonne lecture des écrans d’administration cités (sans supposer que tous les participants y ont accès au quotidien). L’attestation atteste la validation du parcours sur le site ; elle ne remplace pas une décision d’habilitation opérationnelle prise par votre unité.
TXT;
}

function training_roles_org_course_objectives(): string
{
    return "Distinguer rôle sur le portail, fonction du référentiel et poste occupé sur l’organigramme\n"
        . "Trouver la page « Rôles et fonctions » et comprendre le graphe des relations entre fonctions\n"
        . "Expliquer où la spécialité est saisie au recrutement et où elle se retrouve dans le dossier personnel\n"
        . "Décrire le panneau Affectation du dossier et la page d’attribution des rôles aux membres\n"
        . "Éviter les erreurs de contexte (mauvaise communauté active, rôle manquant) lorsqu’une rubrique semble absente\n"
        . "Réussir le questionnaire final sur les usages attendus du portail";
}

function training_roles_org_course_short_description(): string
{
    return 'Rôles, doctrine des fonctions, spécialité et affectation : repères pour lire correctement l’organisation sur le portail.';
}

/** @return list<array<string, mixed>> */
function training_roles_org_portal_module_specs(): array
{
    return [
        [
            'title' => 'Rôles sur le portail',
            'subtitle' => 'Menus, droits et ce que vous voyez',
            'minutes' => 24,
            'module_description' => 'Un rôle sur le portail regroupe des droits d’accès : il détermine quelles rubriques apparaissent, ce que vous pouvez consulter ou modifier, et parfois quelles actions de modération ou de pilotage sont ouvertes. Ce module pose la différence essentielle avec une « fonction » du référentiel RH, qui sert à nommer et relier les responsabilités au sein de la doctrine de l’organisation.',
            'module_learning_objectives' => [
                'Expliquer pourquoi deux membres avec des rôles différents ne voient pas les mêmes menus.',
                'Relier l’absence d’une rubrique à la communauté active, au rôle attribué ou à la configuration plutôt qu’à une « panne » générique.',
                'Identifier le bon canal pour demander une évolution de droits (staff, pas contournement).',
            ],
            'deck' => training_roles_org_deck_roles(),
            'lesson_summary' => 'Rôle portail, effets sur l’interface, distinction avec la fonction métier, bonnes pratiques de demande d’accès.',
            'recap_html' => <<<'HTML'
<div class="prose prose-slate max-w-none"><h3 class="text-base font-bold text-slate-900">Synthèse du module</h3><ul class="list-disc pl-5 space-y-2 text-slate-700 text-sm leading-relaxed"><li><strong>Rôle</strong> : ce que le système autorise pour votre compte (menus, actions).</li><li><strong>Fonction du référentiel</strong> : libellé et place dans la doctrine — traité au module suivant.</li><li><strong>Vigilance</strong> : vérifier la communauté active avant de conclure qu’une rubrique « manque ».</li><li><strong>Escalade</strong> : demander au staff une affectation de rôle adaptée plutôt que partager un compte.</li></ul></div>
HTML,
        ],
        [
            'title' => 'Fonctions et doctrine S1',
            'subtitle' => 'Référentiel, rôles du tenant et relations',
            'minutes' => 28,
            'module_description' => 'La cellule S1 dispose d’un espace dédié pour la doctrine des fonctions : catalogue des fonctions de référence, rattachement des rôles de votre communauté à ces fonctions, graphe des relations hiérarchiques, et éventuellement le suivi des fonctions jugées obligatoires pour l’organisation. Ce module décrit à quoi sert chaque écran et comment le staff tient la carte des responsabilités à jour.',
            'module_learning_objectives' => [
                'Décrire le lien entre une fonction du référentiel et un rôle porté par un membre.',
                'Expliquer l’intérêt du graphe des relations entre fonctions pour la lisibilité de la chaîne de responsabilité.',
                'Comprendre le principe des fonctions obligatoires pour l’organisation lorsque cette option est activée.',
            ],
            'deck' => training_roles_org_deck_functions(),
            'lesson_summary' => 'Page Rôles et fonctions, référentiel, relations, couverture des postes, attribution côté staff.',
            'recap_html' => <<<'HTML'
<div class="prose prose-slate max-w-none"><h3 class="text-base font-bold text-slate-900">Synthèse du module</h3><ul class="list-disc pl-5 space-y-2 text-slate-700 text-sm leading-relaxed"><li><strong>Référentiel</strong> : fonctions normalisées ; les rôles du tenant s’y rattachent pour la cohérence.</li><li><strong>Relations</strong> : le graphe matérialise qui relève de qui dans la doctrine.</li><li><strong>Obligations</strong> : certaines fonctions peuvent être marquées comme devant être pourvues — suivi de couverture.</li><li><strong>Attribution</strong> : lier un membre à un rôle adapté se fait depuis les écrans d’administration prévus, pas par contournement.</li></ul></div>
HTML,
        ],
        [
            'title' => 'Spécialité',
            'subtitle' => 'Recrutement et dossier personnel',
            'minutes' => 22,
            'module_description' => 'La spécialité peut être collectée lors du parcours de recrutement ou portée dans le dossier personnel selon les réglages de votre communauté. Ce module clarifie où l’information naît, où elle est visible pour le staff, et pourquoi une divergence entre engagement et dossier doit être corrigée plutôt que masquée.',
            'module_learning_objectives' => [
                'Distinguer la spécialité déclarée à l’engagement et la spécialité tenue dans le dossier.',
                'Identifier qui met à jour quoi (candidat, membre sur son dossier, staff).',
                'Comprendre l’intérêt d’aligner les libellés pour les convocations et la cohérence des équipes.',
            ],
            'deck' => training_roles_org_deck_specialty(),
            'lesson_summary' => 'Parcours recrutement, dossier personnel, cohérence, confidentialité et usage opérationnel.',
            'recap_html' => <<<'HTML'
<div class="prose prose-slate max-w-none"><h3 class="text-base font-bold text-slate-900">Synthèse du module</h3><ul class="list-disc pl-5 space-y-2 text-slate-700 text-sm leading-relaxed"><li><strong>Double entrée possible</strong> : engagement puis dossier — vérifier la source affichée.</li><li><strong>Mise à jour</strong> : toute évolution doit suivre les canaux prévus (self-service ou staff).</li><li><strong>Staff</strong> : contrôler la cohérence avant les affectations sensibles.</li><li><strong>Respect</strong> : ne pas diffuser une spécialité hors des espaces prévus par la communauté.</li></ul></div>
HTML,
        ],
        [
            'title' => 'Affectation et organigramme',
            'subtitle' => 'Poste, ORBAT et dossier',
            'minutes' => 26,
            'module_description' => 'L’affectation relie un membre à une unité, un poste sur l’organigramme et les informations de présentation du dossier. Ce module parcourt le panneau Affectation du dossier personnel, la logique ORBAT, et la page d’attribution des rôles aux membres pour le staff.',
            'module_learning_objectives' => [
                'Lire une affectation sur la fiche personnel et la relier à l’organigramme.',
                'Savoir où le staff gère les attributions de rôles liées aux postes.',
                'Éviter les confusions entre simple libellé d’équipe et rattachement structurel au bon groupe.',
            ],
            'deck' => training_roles_org_deck_assignment(),
            'lesson_summary' => 'Panneau Affectation, organigramme, attributions membres, erreurs fréquentes de contexte.',
            'recap_html' => <<<'HTML'
<div class="prose prose-slate max-w-none"><h3 class="text-base font-bold text-slate-900">Synthèse du module</h3><ul class="list-disc pl-5 space-y-2 text-slate-700 text-sm leading-relaxed"><li><strong>Affectation</strong> : unité, poste, visibilité sur l’ORBAT selon les règles de la communauté.</li><li><strong>Dossier</strong> : le panneau regroupe les informations de rattachement utiles au quotidien.</li><li><strong>Staff</strong> : page d’attribution des rôles pour lier membres et responsabilités.</li><li><strong>Erreur fréquente</strong> : agir dans la mauvaise communauté ou sans le rôle attendu.</li></ul></div>
HTML,
        ],
    ];
}

/** @return array{version:int,modals?:list<array<string,mixed>>,slides:list<array<string,mixed>>,opening?:array<string,mixed>,closure?:array<string,mixed>} */
function training_roles_org_deck_roles(): array
{
    return [
        'version' => 2,
        'modals' => [
            [
                'id' => 'roles-lex',
                'title' => 'Lexique rapide',
                'body' => '<ul><li><strong>Rôle (portail)</strong> : ensemble de droits attachés à votre compte dans cette communauté.</li><li><strong>Fonction (référentiel)</strong> : rôle conceptuel décrit dans la doctrine S1, auquel se rattachent les rôles du tenant.</li><li><strong>Poste / affectation</strong> : place sur l’organigramme et dans le dossier — module « Affectation ».</li></ul>',
            ],
        ],
        'opening' => [
            'eyebrow' => 'Module 1',
            'title' => '',
            'lead' => 'Comprendre ce que change un rôle sur le portail, et ce qu’il ne remplace pas.',
            'stats' => [
                ['label' => 'Durée indicative', 'value' => '~24 min'],
                ['label' => 'Public', 'value' => 'Tous les membres'],
                ['label' => 'Objectif', 'value' => 'Menus et droits'],
            ],
        ],
        'closure' => [
            'title' => 'Synthèse — Rôles sur le portail',
            'seen' => [
                'Le rôle conditionne les menus et actions, pas votre valeur opérationnelle au sens tactique.',
                'Une rubrique absente appelle d’abord une vérification de contexte (communauté, rôle).',
                'La fonction du référentiel S1 sera détaillée au module suivant.',
            ],
            'acquired' => [
                'Vous pouvez expliquer à un nouveau membre pourquoi son écran diffère du vôtre.',
                'Vous savez quand solliciter le staff pour une évolution de rôle.',
            ],
            'nextHint' => 'Passez au module « Fonctions et doctrine S1 » pour le référentiel et le graphe.',
        ],
        'slides' => [
            [
                'template' => 'title_hero',
                'title' => 'Rôles sur le portail',
                'subtitle' => 'Ce que le site vous autorise à faire',
                'body' => '<p>Sur le portail, votre <strong>rôle</strong> est la clé d’entrée : il ouvre ou ferme des rubriques, des boutons d’action et parfois des vues de modération ou de pilotage. Ce n’est pas une décoration : si vous ne voyez pas une page mentionnée dans une consigne, la première hypothèse raisonnable est que <strong>votre rôle actuel ne l’inclut pas</strong>, ou que vous n’êtes pas dans la bonne communauté.</p><p>Ce module ne décrit pas chaque rôle au cas par cas — votre staff définit la nomenclature — mais la <strong>logique</strong> : menus dynamiques, actions sensibles réservées, et bonne façon de demander un ajustement.</p>',
                'contextKicker' => 'Module 1 · Cadre',
                'surface' => 'elevated',
                'metric' => ['label' => 'Principe', 'value' => 'Droits explicites'],
                'cards' => [
                    ['label' => 'Menus', 'body' => 'Construits à partir des permissions liées à votre rôle.'],
                    ['label' => 'Actions', 'body' => 'Créer, valider, modérer : chaque bouton est soumis à contrôle.'],
                    ['label' => 'Lecture seule', 'body' => 'Certaines fonctions n’ouvrent que la consultation — c’est voulu.'],
                ],
            ],
            [
                'template' => 'reading_article',
                'title' => 'Rôle portail et fonction métier : ne pas confondre',
                'subtitle' => 'Deux couches complémentaires',
                'body' => <<<'HTML'
<p>La <strong>fonction</strong> dans le référentiel S1 sert à nommer et relier les responsabilités au sein de la doctrine de l’organisation (chef de section, opérateur radio, etc.). Le <strong>rôle sur le portail</strong> est la traduction opérationnelle des droits d’accès : un même intitulé métier peut correspondre à plusieurs rôles techniques, ou inversement, selon la façon dont votre communauté a configuré le tenant.</p>
<p>L’erreur fréquente est de croire qu’« avoir un titre » sur le forum remplace une <strong>affectation</strong> de rôle faite par le staff dans l’administration. Le portail ne devine pas votre place : il applique ce qui a été enregistré.</p>
<div class="lms-reading-callout lms-reading-callout--info"><p><strong>À retenir</strong> : titre affiché, rôle technique et fonction du référentiel peuvent coexister ; seuls les deux derniers pilotent l’accès et la cohérence RH.</p></div>
HTML
                ,
            ],
            [
                'template' => 'scenario_decision',
                'title' => 'Un camarade ne voit pas le même menu que vous',
                'context' => 'Après une réunion, on vous demande « pourquoi le site est cassé chez lui ».',
                'situation' => '<p>Vous savez que la rubrique existe chez vous ; chez lui le lien n’apparaît pas.</p>',
                'options' => [
                    ['id' => 'a', 'text' => 'Lui demander de vérifier la communauté active, puis comparer les rôles attribués avec le staff si besoin.'],
                    ['id' => 'b', 'text' => 'Lui envoyer vos identifiants pour qu’il se connecte avec votre compte.'],
                    ['id' => 'c', 'text' => 'Conclure immédiatement à une panne générale du site.'],
                    ['id' => 'd', 'text' => 'Installer une extension de navigateur pour « débloquer » les menus.'],
                ],
                'correctOptionId' => 'a',
                'explanation' => '<p>La démarche attendue combine <strong>contexte</strong> (bonne communauté) et <strong>comparaison des rôles</strong>. Les autres options compromettent la sécurité ou dispersent l’équipe sur une fausse piste.</p>',
            ],
            [
                'template' => 'fill_blanks',
                'title' => 'Auto-vérification',
                'contextKicker' => 'Vocabulaire',
                'metric' => ['label' => 'Consigne', 'value' => 'Compléter les termes exacts'],
                'body' => '<p>Sur le portail, ce qui ouvre ou ferme les rubriques pour votre compte, ce sont les droits portés par votre [[rôle]].</p><p>La carte des responsabilités normalisées côté doctrine S1 porte le nom de [[fonctions]] du référentiel.</p>',
            ],
            [
                'template' => 'knowledge_check',
                'title' => 'Bon réflexe avant d’ouvrir un ticket',
                'body' => "Vérifier la communauté active lorsque vous en avez plusieurs.\nComparer avec un pair qui a le comportement attendu : même rôle ? même tenant ?\nDemander une évolution de rôle au staff sur le canal prévu, avec le contexte (pourquoi, quelle rubrique).\nÉviter le partage de compte : il rend les traces inutilisables et viole le principe de responsabilité individuelle.\nAccepter qu’une formation ou une consigne générique décrive des écrans que vous ne verrez qu’après extension de vos droits.",
            ],
        ],
    ];
}

/** @return array{version:int,modals?:list<array<string,mixed>>,slides:list<array<string,mixed>>,opening?:array<string,mixed>,closure?:array<string,mixed>} */
function training_roles_org_deck_functions(): array
{
    return [
        'version' => 2,
        'opening' => [
            'eyebrow' => 'Module 2',
            'title' => '',
            'lead' => 'La doctrine des fonctions : référentiel, rattachements et graphe des relations.',
            'stats' => [
                ['label' => 'Durée indicative', 'value' => '~28 min'],
                ['label' => 'Public', 'value' => 'Staff et membres curieux'],
                ['label' => 'Lieu', 'value' => 'Administration · Configuration'],
            ],
        ],
        'closure' => [
            'title' => 'Synthèse — Doctrine S1',
            'seen' => [
                'Le référentiel liste les fonctions ; les rôles du tenant s’y rattachent pour la cohérence.',
                'Le graphe rend visibles les relations hiérarchiques ou de soutien.',
                'Les fonctions obligatoires pour l’organisation permettent un suivi de couverture lorsque l’option est active.',
            ],
            'acquired' => [
                'Vous savez orienter un collègue vers la page « Rôles et fonctions » pour la doctrine.',
                'Vous distinguez rattachement au référentiel et simple affichage d’étiquette.',
            ],
            'nextHint' => 'Enchaînez avec « Spécialité » : recrutement et dossier.',
        ],
        'slides' => [
            [
                'template' => 'title_hero',
                'title' => 'Fonctions et doctrine S1',
                'subtitle' => 'Une page pour cadrer l’organisation',
                'body' => '<p>Dans l’administration de la communauté, sous <strong>Configuration</strong>, la page <strong>Rôles et fonctions</strong> concentre la doctrine : catalogue des fonctions de référence, rattachement des rôles de votre tenant, graphe des relations, et lorsque c’est activé le volet des <strong>fonctions obligatoires</strong> pour l’organisation avec un suivi de couverture.</p><p>Ce n’est pas un organigramme tactique : c’est la <strong>carte des responsabilités nommées</strong> telle que la communauté la tient à jour pour le portail.</p>',
                'contextKicker' => 'Module 2 · Doctrine',
                'surface' => 'default',
                'cards' => [
                    ['label' => 'Référentiel', 'body' => 'Les fonctions de référence et leurs libellés.'],
                    ['label' => 'Rôles du tenant', 'body' => 'Les rôles portés par les membres, liés au référentiel.'],
                    ['label' => 'Relations', 'body' => 'Qui relève de qui, qui soutient qui.'],
                ],
            ],
            [
                'template' => 'reading_article',
                'title' => 'Pourquoi rattacher les rôles au référentiel',
                'subtitle' => 'Cohérence et pilotage',
                'body' => <<<'HTML'
<p>Sans rattachement, chaque unité peut inventer ses propres intitulés : la communication devient floue et les rapports de couverture (« qui assure la fonction X ? ») deviennent impraticables. Le référentiel impose un <strong>langage commun</strong> tout en laissant votre communauté choisir quels rôles techniques portent réellement les membres.</p>
<p>Le staff maintient cette cohérence : création ou choix des rôles du tenant, liaison aux fonctions de référence, mise à jour lors des réorganisations. Les membres voient surtout l’effet dans leurs écrans et leurs convocations ; le référentiel reste le <strong>socle arrière-plan</strong>.</p>
<div class="lms-reading-callout lms-reading-callout--tip"><p><strong>Bon réflexe</strong> : après une réorg, mettre à jour le graphe et les rattachements avant d’annoncer les nouvelles responsabilités sur le forum.</p></div>
HTML
                ,
            ],
            [
                'template' => 'reading_article',
                'title' => 'Fonctions obligatoires pour l’organisation',
                'subtitle' => 'Lorsque l’option est disponible',
                'body' => <<<'HTML'
<p>Certaines communautés marquent des fonctions du référentiel comme <strong>devant être pourvues</strong> au sein de l’organisation. La page affiche alors un état de couverture : au moins un membre actif possède-t-il un rôle lié à cette fonction ? Ce mécanisme aide le commandement à voir les trous sans remplacer le jugement humain sur la disponibilité réelle.</p>
<p>Si ce bloc n’apparaît pas chez vous, c’est que la fonctionnalité n’est pas encore activée ou pas proposée dans votre configuration : ce n’est pas une erreur de parcours.</p>
HTML
                ,
            ],
            [
                'template' => 'scenario_decision',
                'title' => 'On vous demande d’« inventer un rôle » pour un nouvel invité',
                'context' => 'Réception d’un partenaire externe avec accès lecture au forum seulement.',
                'situation' => '<p>On hésite entre dupliquer un rôle staff ou créer un rôle minimal.</p>',
                'options' => [
                    ['id' => 'a', 'text' => 'Créer ou choisir un rôle aux permissions strictement limitées au besoin, documenter l’usage, éviter de copier un rôle sensible.'],
                    ['id' => 'b', 'text' => 'Dupliquer le rôle du chef d’unité pour aller plus vite.'],
                    ['id' => 'c', 'text' => 'Laisser le partenaire sans compte et partager un compte interne.'],
                    ['id' => 'd', 'text' => 'Désactiver temporairement toutes les permissions de modération pour tout le monde.'],
                ],
                'correctOptionId' => 'a',
                'explanation' => '<p>Le principe du <strong>moindre privilège</strong> s’applique : un rôle dédié, documenté, avec le minimum de droits. Les autres options créent un risque majeur ou une dette de configuration.</p>',
            ],
        ],
    ];
}

/** @return array{version:int,slides:list<array<string,mixed>>,opening?:array<string,mixed>,closure?:array<string,mixed>} */
function training_roles_org_deck_specialty(): array
{
    return [
        'version' => 2,
        'opening' => [
            'eyebrow' => 'Module 3',
            'title' => '',
            'lead' => 'Où naît la spécialité, où elle vit, comment la tenir cohérente.',
            'stats' => [
                ['label' => 'Durée indicative', 'value' => '~22 min'],
                ['label' => 'Public', 'value' => 'Membres et staff'],
                ['label' => 'Thème', 'value' => 'Donnée d’identité métier'],
            ],
        ],
        'closure' => [
            'title' => 'Synthèse — Spécialité',
            'seen' => [
                'La spécialité peut être saisie à l’engagement puis portée dans le dossier.',
                'Le staff veille à l’alignement pour les affectations et convocations.',
            ],
            'acquired' => [
                'Vous savez expliquer à un nouveau membre où mettre à jour sa spécialité.',
                'Vous identifiez une incohérence comme un sujet de correction, pas comme une « option cosmétique ».',
            ],
            'nextHint' => 'Terminez par « Affectation et organigramme ».',
        ],
        'slides' => [
            [
                'template' => 'title_hero',
                'title' => 'Spécialité',
                'subtitle' => 'Du recrutement au dossier',
                'body' => '<p>Lors du <strong>parcours de recrutement</strong>, un candidat peut déclarer une spécialité ou un profil d’arrivée selon les champs prévus par votre communauté. Une fois membre, la <strong>fiche personnelle</strong> et son panneau dossier peuvent reprendre ou préciser cette information pour l’usage interne (briefings, composition d’équipe).</p><p>La règle d’or : <strong>une seule vérité opérationnelle</strong> à terme — si deux endroits divergent, le staff tranche et met à jour.</p>',
                'contextKicker' => 'Module 3 · Donnée vivante',
                'surface' => 'elevated',
            ],
            [
                'template' => 'reading_article',
                'title' => 'Qui modifie quoi',
                'subtitle' => 'Self-service et validation',
                'body' => <<<'HTML'
<p>Selon les réglages, le membre peut mettre à jour certains champs de son dossier ; d’autres relèvent du staff (sensibilité, cohérence ORBAT, discipline). Lorsqu’une modification est soumise à validation, respectez le délai et le canal indiqués plutôt que de multiplier les messages privés identiques.</p>
<p>Pour le staff : journaliser mentalement <strong>quelle source</strong> vous avez retenue lors d’un conflit (engagement papier / oral, dossier numérique, constat en mission) et faire converger l’affichage portail vers cette source après décision.</p>
HTML
                ,
            ],
            [
                'template' => 'scenario_decision',
                'title' => 'La spécialité affichée ne correspond plus à la réalité',
                'context' => 'Après une reconversion interne, un membre voit encore l’ancienne étiquette.',
                'situation' => '<p>Il veut la corriger avant un exercice public.</p>',
                'options' => [
                    ['id' => 'a', 'text' => 'Mettre à jour via le dossier personnel si le champ est éditable, sinon demander la correction au staff sur le canal prévu.'],
                    ['id' => 'b', 'text' => 'Modifier uniquement le pseudo sur le forum et laisser le dossier inchangé.'],
                    ['id' => 'c', 'text' => 'Demander à un ami staff de « passer par la console » sans ticket ni trace.'],
                    ['id' => 'd', 'text' => 'Ignorer : ce n’est qu’un libellé sans impact.'],
                ],
                'correctOptionId' => 'a',
                'explanation' => '<p>La voie attendue respecte les <strong>règles de mise à jour</strong> et la traçabilité. Les autres options créent de la dette ou du risque réputationnel.</p>',
            ],
            [
                'template' => 'knowledge_check',
                'title' => 'Confidentialité et usage',
                'body' => "La spécialité sert à la coordination : ne la diffusez pas hors des espaces prévus.\nSi vous n’êtes pas sûr du libellé officiel, alignez-vous sur le référentiel interne plutôt que sur une habitude d’autre jeu.\nAprès une longue absence, vérifiez que le dossier reflète encore votre position réelle.\nLe staff peut verrouiller certains champs : ce n’est pas une punition, c’est une garantie de cohérence.",
            ],
        ],
    ];
}

/** @return array{version:int,slides:list<array<string,mixed>>,opening?:array<string,mixed>,closure?:array<string,mixed>} */
function training_roles_org_deck_assignment(): array
{
    return [
        'version' => 2,
        'opening' => [
            'eyebrow' => 'Module 4',
            'title' => '',
            'lead' => 'Affectation : lien entre organigramme, dossier et rôles attribués.',
            'stats' => [
                ['label' => 'Durée indicative', 'value' => '~26 min'],
                ['label' => 'Public', 'value' => 'Tous'],
                ['label' => 'Suite', 'value' => 'Quiz final'],
            ],
        ],
        'closure' => [
            'title' => 'Synthèse — Affectation',
            'seen' => [
                'Le panneau Affectation du dossier regroupe unité, poste et éléments de rattachement ORBAT.',
                'Le staff utilise la page d’attribution des rôles aux membres pour lier responsabilités et comptes.',
            ],
            'acquired' => [
                'Vous savez lire votre affectation et la relier à l’organigramme publié.',
                'Vous savez où orienter une demande de changement de poste.',
            ],
            'nextHint' => 'Validez vos acquis avec le questionnaire final du parcours.',
        ],
        'slides' => [
            [
                'template' => 'title_hero',
                'title' => 'Affectation et organigramme',
                'subtitle' => 'Votre place dans la structure',
                'body' => '<p>Le <strong>panneau Affectation</strong> du dossier personnel regroupe en général l’unité, le poste sur l’organigramme et les informations de rattachement utiles aux briefings. L’<strong>ORBAT</strong> publié (selon les droits) matérialise la hiérarchie et les équipes : votre affectation doit y être <strong>lisible de façon cohérente</strong> avec le dossier.</p><p>Pour le staff, la page d’<strong>attribution des rôles aux membres</strong> (souvent associée aux postes et à la doctrine S1) permet de donner à chaque compte les responsabilités attendues sans ambiguïté.</p>',
                'contextKicker' => 'Module 4 · Structure',
                'surface' => 'default',
            ],
            [
                'template' => 'reading_article',
                'title' => 'Lire et demander un changement',
                'subtitle' => 'Procédure raisonnable',
                'body' => <<<'HTML'
<p>Avant toute demande, vérifiez <strong>la communauté active</strong> et relisez le panneau Affectation : parfois la mise à jour est déjà en cours côté staff. Ensuite, utilisez le canal prévu (message au référent RH, formulaire interne, procédure d’escalade) plutôt qu’un message public sur le forum si la communauté attend de la discrétion.</p>
<p>Le staff traite les changements d’affectation en gardant à l’esprit la cohérence ORBAT, les permissions et parfois des contraintes de clearance : une demande légitime peut prendre du temps sans être un refus implicite.</p>
HTML
                ,
            ],
            [
                'template' => 'scenario_decision',
                'title' => 'Vous êtes affecté à une équipe sur le forum mais pas sur l’ORBAT',
                'context' => 'Deux sources semblent contradictoires.',
                'situation' => '<p>Vous devez participer à un briefing qui se base sur l’ORBAT officiel.</p>',
                'options' => [
                    ['id' => 'a', 'text' => 'Vous aligner sur l’ORBAT et le dossier personnel, puis signaler l’écart au staff pour harmonisation.'],
                    ['id' => 'b', 'text' => 'Suivre uniquement le fil du forum et ignorer l’ORBAT.'],
                    ['id' => 'c', 'text' => 'Changer vous-même votre équipe dans le dossier sans validation.'],
                    ['id' => 'd', 'text' => 'Multiplier les messages à tous les canaux en urgence.'],
                ],
                'correctOptionId' => 'a',
                'explanation' => '<p>La référence pour la structure officielle est le <strong>dossier et l’ORBAT publié</strong> ; l’écart se traite avec le staff de façon ordonnée.</p>',
            ],
            [
                'template' => 'reading_article',
                'title' => 'Erreurs fréquentes',
                'subtitle' => 'Les éviter',
                'body' => <<<'HTML'
<ul class="list-disc pl-5 space-y-2 text-slate-700 text-sm leading-relaxed">
<li>Agir dans la mauvaise communauté lorsque vous en avez plusieurs sur la même plateforme.</li>
<li>Supposer qu’un titre affiché sur un message du forum vaut affectation officielle sans vérification du dossier.</li>
<li>Demander un rôle « chef » pour accéder à une simple lecture de document au lieu d’un rôle minimal adapté.</li>
<li>Oublier de rafraîchir la page après une annonce de réorganisation : le cache du navigateur peut parfois masquer une mise à jour récente.</li>
</ul>
HTML
                ,
            ],
            [
                'template' => 'knowledge_check',
                'title' => 'Avant le questionnaire final',
                'body' => "Relisez mentalement : rôle portail, fonction du référentiel, spécialité, affectation — quatre notions distinctes.\nLe quiz porte sur les bons réflexes, pas sur la mémorisation de noms de boutons au pixel près.\nEn cas d’échec, les explications affichées servent de plan de révision ; une nouvelle tentative est normale.\nL’attestation atteste la validation du parcours sur le portail, pas une clearance opérationnelle externe au site.",
            ],
        ],
    ];
}

/**
 * @return list<array{text:string,explain?:string,answers:list<array{t:string,ok:bool}>}>
 */
function training_roles_org_final_quiz_questions(): array
{
    return [
        [
            'text' => 'Sur le portail, qu’est-ce qui détermine en premier lieu les rubriques visibles dans les menus ?',
            'explain' => 'Les menus reflètent les permissions liées au rôle attribué à votre compte dans cette communauté.',
            'answers' => [
                ['t' => 'Le rôle attribué à votre compte dans cette communauté', 'ok' => true],
                ['t' => 'Uniquement le navigateur utilisé', 'ok' => false],
                ['t' => 'Le nombre de messages postés sur le forum', 'ok' => false],
                ['t' => 'La couleur du thème choisie dans les préférences', 'ok' => false],
            ],
        ],
        [
            'text' => 'Où le staff gère en principe la doctrine des fonctions et le graphe des relations ?',
            'explain' => 'La page « Rôles et fonctions » sous Configuration regroupe référentiel, rattachements et relations.',
            'answers' => [
                ['t' => 'Dans l’administration, sous Configuration, page Rôles et fonctions', 'ok' => true],
                ['t' => 'Uniquement dans le carnet d’adresses du téléphone du chef', 'ok' => false],
                ['t' => 'Sur la page d’accueil publique sans connexion', 'ok' => false],
                ['t' => 'Dans les paramètres du compte de chaque membre', 'ok' => false],
            ],
        ],
        [
            'text' => 'Un membre ne voit pas une rubrique que vous voyez. Quelle est la première vérification raisonnable ?',
            'explain' => 'Le contexte (communauté active) et le rôle expliquent la majorité des écarts.',
            'answers' => [
                ['t' => 'Vérifier qu’il est dans la bonne communauté et comparer les rôles attribués', 'ok' => true],
                ['t' => 'Réinstaller le système d’exploitation de son ordinateur', 'ok' => false],
                ['t' => 'Publier immédiatement une alerte sur tous les canaux', 'ok' => false],
                ['t' => 'Lui prêter vos identifiants pour qu’il voie comme vous', 'ok' => false],
            ],
        ],
        [
            'text' => 'Qu’apporte le rattachement des rôles du tenant aux fonctions du référentiel ?',
            'explain' => 'Un langage commun et un pilotage de la couverture des responsabilités.',
            'answers' => [
                ['t' => 'Une base commune pour nommer les responsabilités et suivre la cohérence', 'ok' => true],
                ['t' => 'Rien, c’est purement cosmétique', 'ok' => false],
                ['t' => 'Le remplacement automatique du chef d’unité chaque semaine', 'ok' => false],
                ['t' => 'La suppression du forum', 'ok' => false],
            ],
        ],
        [
            'text' => 'Concernant la spécialité, quelle conduite est recommandée lorsqu’elle ne correspond plus à la réalité ?',
            'explain' => 'Mettre à jour par le canal autorisé ou demander la correction au staff.',
            'answers' => [
                ['t' => 'Corriger via le dossier si possible, sinon demander au staff sur le canal prévu', 'ok' => true],
                ['t' => 'Ne rien faire : les libellés sont figés pour toujours', 'ok' => false],
                ['t' => 'Modifier uniquement le pseudonyme visible publiquement', 'ok' => false],
                ['t' => 'Demander à un tiers de se connecter à votre place', 'ok' => false],
            ],
        ],
        [
            'text' => 'L’affectation affichée sur le dossier personnel doit idéalement être cohérente avec :',
            'explain' => 'L’organigramme et les décisions de structure portées par le staff.',
            'answers' => [
                ['t' => 'L’ORBAT publié et les informations de structure validées par le staff', 'ok' => true],
                ['t' => 'Uniquement le dernier sondage informel sur le forum', 'ok' => false],
                ['t' => 'Le choix personnel sans validation', 'ok' => false],
                ['t' => 'Un document personnel stocké hors portail sans lien avec l’unité', 'ok' => false],
            ],
        ],
        [
            'text' => 'Pour donner à un partenaire externe un accès très limité (lecture ciblée), la meilleure approche est :',
            'explain' => 'Moindre privilège : rôle minimal documenté.',
            'answers' => [
                ['t' => 'Créer ou choisir un rôle avec le minimum de permissions nécessaires', 'ok' => true],
                ['t' => 'Dupliquer le rôle d’administration générale', 'ok' => false],
                ['t' => 'Partager le compte d’un membre interne', 'ok' => false],
                ['t' => 'Désactiver la modération pour toute la communauté', 'ok' => false],
            ],
        ],
        [
            'text' => 'Les « fonctions obligatoires pour l’organisation » permettent surtout de :',
            'explain' => 'Suivre si des fonctions clés disposent d’au moins un rôle pourvu, lorsque l’option est active.',
            'answers' => [
                ['t' => 'Voir la couverture des fonctions marquées comme devant être assurées', 'ok' => true],
                ['t' => 'Supprimer automatiquement les membres inactifs', 'ok' => false],
                ['t' => 'Publier les messages du forum à la place des auteurs', 'ok' => false],
                ['t' => 'Remplacer le règlement intérieur', 'ok' => false],
            ],
        ],
        [
            'text' => 'Que signifie l’attestation délivrée après réussite de ce parcours sur le portail ?',
            'explain' => 'Elle atteste la validation du parcours pédagogique, pas une clearance opérationnelle externe.',
            'answers' => [
                ['t' => 'Vous avez validé ce parcours selon les règles affichées sur le site', 'ok' => true],
                ['t' => 'Vous êtes automatiquement habilité à tout poste sensible sans autre validation', 'ok' => false],
                ['t' => 'Le portail garantit votre niveau tactique en jeu', 'ok' => false],
                ['t' => 'L’attestation remplace le jugement du commandement réel', 'ok' => false],
            ],
        ],
        [
            'text' => 'Où le staff rattache en principe les membres aux rôles attendus pour les postes ?',
            'explain' => 'La page d’attribution des rôles aux membres complète la doctrine et l’ORBAT.',
            'answers' => [
                ['t' => 'Dans l’espace d’administration prévu pour les attributions de rôles aux membres', 'ok' => true],
                ['t' => 'Dans les commentaires d’une vidéo externe', 'ok' => false],
                ['t' => 'Uniquement par message privé sans enregistrement sur le portail', 'ok' => false],
                ['t' => 'Sur le tableau blanc physique de la salle', 'ok' => false],
            ],
        ],
        [
            'text' => 'La différence principale entre « fonction du référentiel » et « rôle sur le portail » est :',
            'explain' => 'La fonction nomme la doctrine ; le rôle porte les droits effectifs du compte.',
            'answers' => [
                ['t' => 'La fonction cadre la doctrine ; le rôle ouvre les menus et actions du compte', 'ok' => true],
                ['t' => 'Ce sont strictement la même chose avec deux noms', 'ok' => false],
                ['t' => 'La fonction sert uniquement au forum ; le rôle n’existe pas', 'ok' => false],
                ['t' => 'Le rôle est public ; la fonction est secrète', 'ok' => false],
            ],
        ],
        [
            'text' => 'En cas d’écart entre une équipe annoncée sur le forum et l’ORBAT officiel pour un briefing :',
            'explain' => 'Se fier à la structure publiée et au dossier, puis harmoniser avec le staff.',
            'answers' => [
                ['t' => 'Se caler sur l’ORBAT et le dossier, puis signaler l’écart au staff', 'ok' => true],
                ['t' => 'Ignorer l’ORBAT et suivre uniquement le forum', 'ok' => false],
                ['t' => 'Modifier soi-même l’ORBAT sans mandat', 'ok' => false],
                ['t' => 'Quitter la communauté sans prévenir', 'ok' => false],
            ],
        ],
    ];
}

function training_roles_org_seed_one_tenant(PDO $pdo, int $tenantId, int $authorUserId): void
{
    $slug = 'parcours-postes-rbac';
    if (training_roles_org_course_exists($pdo, $tenantId, $slug)) {
        echo "  training_roles_org_course : tenant {$tenantId} — formation « {$slug} » déjà présente.\n";

        return;
    }

    $themeJson = json_encode([
        'accent' => '#1d4ed8',
        'accentRgb' => '29, 78, 216',
        'font' => "'IBM Plex Sans', system-ui, sans-serif",
        'radius' => '1.25rem',
        'variant' => 'default',
        'pedagogy_meta' => [
            'target_audience' => ['staff', 'membres', 'référents RH'],
            'pedagogical_style' => 'structured_reference',
            'completion_message' => 'Parcours terminé : vous disposez des repères pour distinguer rôles, fonctions, spécialité et affectation sur le portail. Les décisions d’habilitation opérationnelle restent du ressort de votre unité.',
            'tags' => ['roles', 'fonctions', 'specialite', 'affectation', 'orbat', 'S1'],
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $uuid = training_roles_org_uuid_v4();
    $now = date('Y-m-d H:i:s');
    $specs = training_roles_org_portal_module_specs();
    $totalMinutes = 0;
    foreach ($specs as $s) {
        $totalMinutes += (int) $s['minutes'];
    }

    $pdo->beginTransaction();
    try {
        $ins = $pdo->prepare(
            'INSERT INTO training_courses (
                tenant_id, uuid, title, slug, course_code, short_description, description, learning_objectives,
                theme_json, thumbnail_path, banner_path, category, level, language_code,
                estimated_minutes, passing_score, is_mandatory, is_certifying, validity_days, visibility,
                created_by, updated_by, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, NULL, ?, ?, ?, ?, ?, 0, 1, NULL, ?, ?, ?, ?, ?)'
        );
        $ins->execute([
            $tenantId,
            $uuid,
            'Parcours postes — rôles, fonctions, spécialité et affectation',
            $slug,
            'POSTES-101',
            training_roles_org_course_short_description(),
            training_roles_org_course_description(),
            training_roles_org_course_objectives(),
            $themeJson,
            'Organisation',
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
            $pdo->prepare('UPDATE training_courses SET showcase_sort_order = 2, showcase_badge = ? WHERE id = ?')->execute(['open', $courseId]);
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
            $modLo = training_roles_org_module_objectives_json($m['module_learning_objectives'] ?? []);
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
                'Quiz — rôles, fonctions, spécialité et affectation',
                'Validez vos acquis sur la doctrine S1, les rôles, la spécialité et l’affectation sur le portail.',
                75.00,
                5,
                28,
                1,
                1,
                $now,
            ]);
            $finalQz = (int) $pdo->lastInsertId();
            training_roles_org_seed_quiz_questions_for_module($pdo, $finalQz, training_roles_org_final_quiz_questions(), $now);
        }

        $pdo->commit();
        echo "  training_roles_org_course : tenant {$tenantId} — formation « Parcours postes » créée (course_id={$courseId}).\n";
    } catch (\Throwable $e) {
        $pdo->rollBack();
        echo '  [ATTENTION] training_roles_org_course : ' . $e->getMessage() . "\n";
    }
}
