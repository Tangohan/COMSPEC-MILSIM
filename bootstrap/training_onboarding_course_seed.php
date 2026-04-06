<?php

declare(strict_types=1);

/**
 * Formation LMS obligatoire « guide du portail » : publiée, certifiante, ouverte à tous (policy vide).
 * Idempotent par tenant + slug `parcours-portail`.
 * À chaque exécution des migrations : durées, contenu des leçons « canvas », fiches « À retenir », texte d’introduction
 * du bilan de mi-parcours, descriptions et objectifs des modules sont resynchronisés. Les quiz et leurs questions
 * (tentatives) ne sont pas réécrits pour les tenants déjà provisionnés — seule la création initiale d’une nouvelle
 * communauté insère les questionnaires enrichis.
 * Une extension idempotente peut insérer le module « Bilan à mi-parcours » et les leçons de synthèse sur les anciens parcours à 5 modules.
 *
 * @param PDO $pdo Connexion SQL (comme run-migrations.php)
 */
function run_training_onboarding_course_seed(PDO $pdo): void
{
    $chk = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'training_courses' LIMIT 1");
    if (!$chk || !$chk->fetch()) {
        echo "[ATTENTION] training_onboarding_course : table training_courses absente — ignoré.\n";

        return;
    }

    $slug = 'parcours-portail';
    $tenants = $pdo->query('SELECT id FROM tenants ORDER BY id ASC');
    if (!$tenants) {
        return;
    }
    while ($row = $tenants->fetch(PDO::FETCH_ASSOC)) {
        $tenantId = (int) ($row['id'] ?? 0);
        if ($tenantId < 1) {
            continue;
        }
        if (training_onboarding_course_exists($pdo, $tenantId, $slug)) {
            training_onboarding_refresh_portal_canvas_for_tenant($pdo, $tenantId);

            continue;
        }
        $authorId = training_onboarding_resolve_author_user_id($pdo, $tenantId);
        if ($authorId < 1) {
            echo "  [ATTENTION] training_onboarding_course : tenant {$tenantId} — aucun utilisateur actif, ignoré.\n";

            continue;
        }
        training_onboarding_seed_one_tenant($pdo, $tenantId, $authorId);
    }
}

/**
 * Crée la formation pour un seul tenant (ex. nouvelle communauté) si absente, puis synchronise le contenu canvas.
 */
function run_training_onboarding_course_for_tenant(PDO $pdo, int $tenantId, ?int $authorUserId = null): void
{
    $chk = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'training_courses' LIMIT 1");
    if (!$chk || !$chk->fetch()) {
        return;
    }
    if ($tenantId < 1) {
        return;
    }
    $slug = 'parcours-portail';
    if (!training_onboarding_course_exists($pdo, $tenantId, $slug)) {
        $authorId = $authorUserId !== null && $authorUserId > 0
            ? $authorUserId
            : training_onboarding_resolve_author_user_id($pdo, $tenantId);
        if ($authorId < 1) {
            return;
        }
        training_onboarding_seed_one_tenant($pdo, $tenantId, $authorId);

        return;
    }
    training_onboarding_refresh_portal_canvas_for_tenant($pdo, $tenantId);
}

function training_onboarding_resolve_author_user_id(PDO $pdo, int $tenantId): int
{
    $st = $pdo->prepare('SELECT id FROM users WHERE tenant_id = ? AND status = ? ORDER BY id ASC LIMIT 1');
    $st->execute([$tenantId, 'active']);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    return $row ? (int) $row['id'] : 0;
}

function training_onboarding_course_exists(PDO $pdo, int $tenantId, string $slug): bool
{
    $st = $pdo->prepare('SELECT 1 FROM training_courses WHERE tenant_id = ? AND slug = ? LIMIT 1');
    $st->execute([$tenantId, $slug]);

    return (bool) $st->fetchColumn();
}

function training_onboarding_uuid_v4(): string
{
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

    return vsprintf('%s%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

function training_onboarding_course_description(): string
{
    return <<<'TXT'
Ce parcours d’accueil fixe le socle commun pour utiliser le portail de votre communauté de manière correcte et prévisible. Il ne remplace ni le règlement intérieur ni les consignes d’emploi de votre unité : il précise où vit l’information sur le site, comment la retrouver sans perdre de temps, et quels gestes minimaux protègent votre compte et celui des autres.

La progression suit une montée en puissance : finalité du portail, repérage après connexion, actions sur le compte, lieux où l’information stable coexiste avec la coordination vivante, logique des formations et de la progression enregistrée, règles de vie collective (forum, événements), puis validation par questionnaires. Vous y verrez des situations types, des erreurs fréquentes et des procédures pas à pas lorsque c’est utile.

Le ton est institutionnel et concret. Prenez le temps de lire les encadrés de vigilance et les synthèses de fin de module. Un bilan interrogé à mi-parcours ancre les acquis des trois premiers blocs ; un questionnaire final porte sur l’ensemble du parcours. En cas d’échec, les explications affichées servent de plan de révision avant une nouvelle tentative.
TXT;
}

function training_onboarding_course_objectives(): string
{
    return "Comprendre la finalité du portail : information stabilisée, coordination, suivi pédagogique — et ce qu’il ne remplace pas\n"
        . "Se repérer après connexion : tableau de bord, menu, zone Opérations selon les droits, multi-communautés\n"
        . "Agir sur son compte : profil, préférences, sécurité, contact à jour\n"
        . "Savoir où vit l’information : dossier personnel, organigramme, documents de référence, catalogue des formations\n"
        . "Comprendre la logique LMS : progression réelle, obligation, attestation, reprise de parcours\n"
        . "Adopter les règles de vie collective : forum, annonces, événements, signalements, présence\n"
        . "Réussir le bilan à mi-parcours puis le questionnaire final, et distinguer validation de parcours et habilitation métier";
}

function training_onboarding_course_short_description(): string
{
    return 'Parcours structuré : finalité du portail, navigation et compte, contenus et formations, communauté, validation.';
}

/**
 * @param list<string> $lines
 */
function training_onboarding_module_objectives_json(array $lines): string
{
    $clean = array_values(array_filter(array_map(static fn (string $x): string => trim($x), $lines), static fn (string $x): bool => $x !== ''));

    return json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';
}

function training_onboarding_mid_module_intro_html(): string
{
    return <<<'HTML'
<div class="prose prose-slate max-w-none">
<h3 class="text-base font-bold text-slate-900">Portée du bilan</h3>
<p>Ce bilan porte sur les <strong>trois premiers modules</strong> : finalité du portail et cadre, navigation et compte, organisation des contenus (personnel, documents, formations). Il permet de vérifier que vous maîtrisez le vocabulaire et les réflexes avant le module <strong>Communauté</strong> et la <strong>validation finale</strong>.</p>
<h3 class="text-base font-bold text-slate-900 mt-4">Fiche de révision — rappels utiles</h3>
<ul class="list-disc pl-5 space-y-2 text-slate-700 text-sm leading-relaxed">
<li><strong>Information stabilisée</strong> : documents (ou équivalent) — version de référence contrôlée par le staff.</li>
<li><strong>Coordination vivante</strong> : forum, annonces, fils — échanges, pas stockage de la version finale d’un texte officiel.</li>
<li><strong>Tableau de bord</strong> : synthèse après connexion ; ne remplace ni ordre écrit ni carte tactique.</li>
<li><strong>Compte</strong> : profil, préférences, sécurité — à jour pour éviter erreurs d’affectation et perte d’accès.</li>
<li><strong>Multi-communautés</strong> : vérifier le contexte actif avant toute action engageante.</li>
<li><strong>Progression LMS</strong> : une formation n’est achevée que lorsque toutes les étapes requises le sont ; l’affichage reflète le parcours réel.</li>
<li><strong>Attestation</strong> : atteste la validation du parcours sur le portail selon les règles affichées ; elle ne remplace pas une habilitation métier décidée par l’unité.</li>
</ul>
<h3 class="text-base font-bold text-slate-900 mt-4">Erreurs fréquentes à éviter</h3>
<ul class="list-disc pl-5 space-y-2 text-slate-700 text-sm leading-relaxed">
<li>Publier ou chercher la « version finale » d’une note uniquement dans un fil de discussion ancien.</li>
<li>Conclure à une panne du site sans avoir vérifié la communauté active ou les droits de son rôle.</li>
<li>Ignorer le tableau de bord et rater des rappels de formation ou d’événement.</li>
<li>Laisser une session ouverte sur un poste partagé après utilisation du portail.</li>
</ul>
<h3 class="text-base font-bold text-slate-900 mt-4">Méthode pour le questionnaire</h3>
<p>Lisez chaque question en entier. Plusieurs réponses peuvent sembler raisonnables ; une seule correspond à la conduite ou au réflexe attendu dans ce parcours. Les propositions sont mélangées à chaque affichage. En cas de doute, revoyez les synthèses « À retenir » des trois premiers modules.</p>
</div>
HTML;
}

/** @return list<array<string, mixed>> */
function training_onboarding_portal_module_specs(): array
{
    return [
        [
            'title' => 'Vue d’ensemble',
            'subtitle' => 'Finalité du portail et cadre',
            'minutes' => 26,
            'module_description' => 'Ce module pose pourquoi le portail existe, ce qu’il centralise (information stable, coordination, formations) et ce qu’il ne remplace pas. Il introduit la méthode de lecture du parcours, les risques d’une mauvaise utilisation et les réflexes de sécurité du compte.',
            'module_learning_objectives' => [
                'Expliquer en une phrase la différence entre information stabilisée, échanges vivants et suivi pédagogique sur le site.',
                'Identifier ce que le portail n’est pas (substitut à la chaîne de commandement, stockage anarchique sur le forum).',
                'Citer au moins trois erreurs d’usage fréquentes et leur correction.',
            ],
            'deck' => training_onboarding_deck_overview(),
            'lesson_summary' => 'Rôle du portail, déroulé pédagogique, méthode de travail, sécurité du compte, liens vers l’aide.',
            'recap_html' => <<<'HTML'
<div class="prose prose-slate max-w-none"><h3 class="text-base font-bold text-slate-900">Synthèse du module</h3><ul class="list-disc pl-5 space-y-2 text-slate-700 text-sm leading-relaxed"><li><strong>Règle</strong> : documents, forum, formations et dossier personnel ont des rôles distincts.</li><li><strong>Bonne pratique</strong> : lire le tableau de bord en premier après connexion.</li><li><strong>Point de vigilance</strong> : une rubrique absente peut venir des droits ou de la communauté active, pas d’une « panne » systématique.</li><li><strong>Erreur fréquente</strong> : confondre conversation sur le forum et version de référence d’un texte.</li></ul></div>
HTML,
        ],
        [
            'title' => 'Navigation et compte',
            'subtitle' => 'Se repérer et agir sur son compte',
            'minutes' => 28,
            'module_description' => 'Menus, tableau de bord, zone Opérations, profil, préférences, sécurité et multi-communautés : ce module décrit ce que vous faites réellement sur le portail au quotidien et comment éviter les erreurs de contexte.',
            'module_learning_objectives' => [
                'Décrire le rôle du tableau de bord par rapport au menu principal.',
                'Enchaîner les étapes pour mettre à jour le profil et les préférences dans la rubrique compte.',
                'Expliquer pourquoi le poste partagé impose une déconnexion explicite.',
            ],
            'deck' => training_onboarding_deck_navigation(),
            'lesson_summary' => 'Menu principal, zone Opérations, tableau de bord, compte, préférences, recherche, bonnes pratiques.',
            'recap_html' => <<<'HTML'
<div class="prose prose-slate max-w-none"><h3 class="text-base font-bold text-slate-900">Synthèse du module</h3><ul class="list-disc pl-5 space-y-2 text-slate-700 text-sm leading-relaxed"><li><strong>Règle</strong> : le menu n’affiche que ce que votre rôle autorise.</li><li><strong>Bonne pratique</strong> : vérifier la communauté active avant une action engageante.</li><li><strong>Procédure</strong> : compte → profil / préférences / sécurité selon le besoin.</li><li><strong>Vigilance</strong> : contact (e-mail) valide pour les vérifications et la récupération d’accès.</li></ul></div>
HTML,
        ],
        [
            'title' => 'Organisation et contenus',
            'subtitle' => 'Où vit l’information et le LMS',
            'minutes' => 32,
            'module_description' => 'Personnel, organigramme, documents, catalogue des formations, progression et attestations : le cœur opérationnel du portail. Le module distingue référence documentaire et discussion, et clarifie ce qu’une attestation prouve ou ne prouve pas.',
            'module_learning_objectives' => [
                'Distinguer dossier personnel, organigramme et documents officiels.',
                'Traiter correctement un document sensible ou une version obsolète.',
                'Expliquer pourquoi une formation assignée mais incomplète reste « non validée ».',
            ],
            'deck' => training_onboarding_deck_org(),
            'lesson_summary' => 'Fiche personnelle, organigramme, documents officiels, catalogue LMS, progression et erreurs fréquentes.',
            'recap_html' => <<<'HTML'
<div class="prose prose-slate max-w-none"><h3 class="text-base font-bold text-slate-900">Synthèse du module</h3><ul class="list-disc pl-5 space-y-2 text-slate-700 text-sm leading-relaxed"><li><strong>Règle</strong> : la version de référence vit dans les documents, pas dans un fil ancien du forum.</li><li><strong>Bonne pratique</strong> : signaler une erreur au responsable plutôt que rediffuser hors canal.</li><li><strong>Point clé</strong> : l’attestation atteste du parcours sur le portail, pas une habilitation métier tacite.</li><li><strong>Visibilité</strong> : l’absence d’un contenu peut être normale selon le rôle.</li></ul></div>
HTML,
        ],
        [
            'title' => 'Bilan à mi-parcours',
            'subtitle' => 'Ancrer les acquis (modules 1 à 3)',
            'minutes' => 18,
            'module_description' => 'Révision structurée des trois premiers blocs puis questionnaire à choix multiples. L’objectif est de consolider le vocabulaire et les réflexes avant la vie collective et la validation finale.',
            'module_learning_objectives' => [
                'Relier les notions de tableau de bord, compte, documents et formations.',
                'Repérer les pièges classiques (forum vs documents, multi-communautés).',
                'Aborder le questionnaire avec une méthode de lecture complète des énoncés.',
            ],
            'deck' => null,
            'lesson_summary' => 'Fiche de révision puis questionnaire sur les trois premiers blocs avant la suite du parcours.',
            'intro_html' => training_onboarding_mid_module_intro_html(),
            'mid_quiz' => true,
        ],
        [
            'title' => 'Communauté',
            'subtitle' => 'Forum, annonces, événements',
            'minutes' => 26,
            'module_description' => 'Règles de participation au forum, distinction annonce officielle et conversation, inscriptions aux événements, présence et signalements. Le module vise à réduire le bruit informationnel et à sécuriser les canaux sensibles.',
            'module_learning_objectives' => [
                'Choisir entre message public et canal dédié selon le type de sujet.',
                'Rédiger un titre de sujet utile et éviter les doublons.',
                'Adopter la conduite attendue en cas d’empêchement à un événement inscrit.',
            ],
            'deck' => training_onboarding_deck_community(),
            'lesson_summary' => 'Forum, annonces, événements, pointage, signalements, résumé des bons réflexes.',
            'recap_html' => <<<'HTML'
<div class="prose prose-slate max-w-none"><h3 class="text-base font-bold text-slate-900">Synthèse du module</h3><ul class="list-disc pl-5 space-y-2 text-slate-700 text-sm leading-relaxed"><li><strong>Règle</strong> : rechercher avant d’ouvrir un nouveau sujet.</li><li><strong>Bonne pratique</strong> : prévenir en cas d’absence à un créneau où vous étiez inscrit.</li><li><strong>Vigilance</strong> : sujets sensibles → canal prévu, pas tribune publique désordonnée.</li><li><strong>Différence</strong> : annonce officielle ≠ conversation libre.</li></ul></div>
HTML,
        ],
        [
            'title' => 'Validation finale',
            'subtitle' => 'Questionnaire, attestation, limites',
            'minutes' => 22,
            'module_description' => 'Préparation au questionnaire final, logique du score et des tentatives, obtention de l’attestation lorsque le parcours est certifiant, et rappel de la différence entre validation LMS et compétence opérationnelle reconnue par l’unité.',
            'module_learning_objectives' => [
                'Expliquer l’usage des explications après une réponse incorrecte.',
                'Décrire ce que couvre une attestation de fin de parcours sur le portail.',
                'Organiser une reprise de révision avant une nouvelle tentative de quiz.',
            ],
            'deck' => training_onboarding_deck_validation_intro(),
            'lesson_summary' => 'Quiz, score, tentatives, attestation, reprise de parcours et gestion du stress de l’évaluation.',
            'recap_html' => <<<'HTML'
<div class="prose prose-slate max-w-none"><h3 class="text-base font-bold text-slate-900">Avant le questionnaire final</h3><ul class="list-disc pl-5 space-y-2 text-slate-700 text-sm leading-relaxed"><li><strong>Méthode</strong> : lire l’énoncé jusqu’au bout ; plusieurs réponses peuvent sembler crédibles.</li><li><strong>Règle</strong> : le seuil et les tentatives sont fixés sur la fiche formation.</li><li><strong>Pédagogie</strong> : en cas d’échec, utiliser les explications comme liste de révision.</li><li><strong>Clarification</strong> : la validation du parcours ne dispense pas des exigences métier de l’organisation.</li></ul></div>
HTML,
        ],
    ];
}

/**
 * Insère les questions / réponses d’un quiz ; mélange l’ordre des réponses par question.
 *
 * @param list<array{text:string,explain?:string,answers:list<array{t:string,ok:bool}>}> $questions
 */
function training_onboarding_seed_quiz_questions_for_module(PDO $pdo, int $quizId, array $questions, string $now): void
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

/**
 * Insère le 4e module (bilan), décale Communauté et Validation, ajoute quiz intermédiaire et leçons « À retenir ».
 */
function training_onboarding_upgrade_portal_legacy_five_modules(PDO $pdo, int $courseId): void
{
    $now = date('Y-m-d H:i:s');
    $specs = training_onboarding_portal_module_specs();
    $pdo->beginTransaction();
    try {
        $pdo->prepare('UPDATE training_modules SET position = position + 1, updated_at = ? WHERE course_id = ? AND position >= 4')
            ->execute([$now, $courseId]);

        $modIns = $pdo->prepare(
            'INSERT INTO training_modules (course_id, title, description, subtitle, learning_objectives, estimated_minutes, position, is_required, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, ?)'
        );
        $midSpec = $specs[3];
        $midTitle = (string) ($midSpec['title'] ?? 'Bilan à mi-parcours');
        $midSub = (string) ($midSpec['subtitle'] ?? 'Vérifier ses acquis');
        $midDesc = trim((string) ($midSpec['module_description'] ?? ''));
        if ($midDesc === '') {
            $midDesc = 'Module 4 — ' . $midSub;
        }
        $midLo = training_onboarding_module_objectives_json($midSpec['module_learning_objectives'] ?? []);
        $midMin = (int) ($midSpec['minutes'] ?? 18);
        $modIns->execute([
            $courseId,
            $midTitle,
            $midDesc,
            $midSub,
            $midLo,
            $midMin,
            4,
            $now,
            $now,
        ]);
        $midModuleId = (int) $pdo->lastInsertId();

        $lesIns = $pdo->prepare(
            'INSERT INTO training_lessons (module_id, title, summary, learning_objectives, instructor_notes, lesson_type, content, external_url, duration_minutes, difficulty, position, is_required)
             VALUES (?, ?, ?, NULL, NULL, ?, ?, NULL, ?, ?, ?, 1)'
        );
        $introSum = (string) $specs[3]['lesson_summary'];
        if (strlen($introSum) > 500) {
            $introSum = substr($introSum, 0, 497) . '…';
        }
        $lesIns->execute([
            $midModuleId,
            'Pourquoi ce bilan',
            $introSum,
            'richtext',
            (string) $specs[3]['intro_html'],
            3,
            'initiation',
            1,
        ]);

        $quizIns = $pdo->prepare(
            'INSERT INTO training_quizzes (module_id, title, description, passing_score, max_attempts, time_limit_minutes, randomize_questions, is_final_exam, created_at)
             VALUES (?, ?, ?, ?, ?, ?, 1, 0, ?)'
        );
        $quizIns->execute([
            $midModuleId,
            'Bilan — premiers réflexes',
            'Questions sur la navigation, le compte, les documents et le catalogue des formations.',
            75.00,
            4,
            15,
            $now,
        ]);
        $midQuizId = (int) $pdo->lastInsertId();
        training_onboarding_seed_quiz_questions_for_module($pdo, $midQuizId, training_onboarding_mid_quiz_questions(), $now);

        $pairs = [
            [1, 0],
            [2, 1],
            [3, 2],
            [5, 4],
            [6, 5],
        ];
        $selMod = $pdo->prepare('SELECT id FROM training_modules WHERE course_id = ? AND position = ? LIMIT 1');
        $cntSt = $pdo->prepare('SELECT COUNT(*) FROM training_lessons WHERE module_id = ?');
        foreach ($pairs as [$pos, $specIdx]) {
            $selMod->execute([$courseId, $pos]);
            $modId = (int) $selMod->fetchColumn();
            if ($modId < 1 || empty($specs[$specIdx]['recap_html'])) {
                continue;
            }
            $cntSt->execute([$modId]);
            if ((int) $cntSt->fetchColumn() !== 1) {
                continue;
            }
            $spec = $specs[$specIdx];
            $recapTitle = ($specIdx === 5) ? 'Avant le questionnaire final' : ('À retenir — ' . (string) $spec['title']);
            $sum = 'Synthèse courte pour ancrer les idées du module.';
            $lesIns->execute([
                $modId,
                $recapTitle,
                $sum,
                'richtext',
                (string) $spec['recap_html'],
                5,
                'initiation',
                2,
            ]);
        }

        $totalMin = 0;
        foreach ($specs as $s) {
            $totalMin += (int) $s['minutes'];
        }
        $pdo->prepare('UPDATE training_courses SET estimated_minutes = ?, updated_at = ? WHERE id = ?')
            ->execute([$totalMin, $now, $courseId]);

        $pdo->commit();
        echo "  training_onboarding_course : course_id {$courseId} — parcours portail étendu (bilan mi-parcours + synthèses).\n";
    } catch (\Throwable $e) {
        $pdo->rollBack();
        echo '  [ATTENTION] training_onboarding_course extension : ' . $e->getMessage() . "\n";
    }
}

/**
 * Étend un parcours « historique » (5 modules, un seul quiz final) : module bilan + fiches « À retenir ».
 */
function training_onboarding_ensure_extended_parcours_portal(PDO $pdo, int $tenantId): void
{
    $slug = 'parcours-portail';
    $st = $pdo->prepare('SELECT id FROM training_courses WHERE tenant_id = ? AND slug = ? LIMIT 1');
    $st->execute([$tenantId, $slug]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return;
    }
    $courseId = (int) $row['id'];
    $modFetch = $pdo->prepare('SELECT id, title, position FROM training_modules WHERE course_id = ? ORDER BY position ASC, id ASC');
    $modFetch->execute([$courseId]);
    $modules = $modFetch->fetchAll(PDO::FETCH_ASSOC);
    if (count($modules) !== 5) {
        return;
    }
    $qzSt = $pdo->prepare('SELECT COUNT(*) FROM training_quizzes q INNER JOIN training_modules m ON m.id = q.module_id WHERE m.course_id = ?');
    $qzSt->execute([$courseId]);
    if ((int) $qzSt->fetchColumn() !== 1) {
        return;
    }
    $lastTitle = (string) ($modules[4]['title'] ?? '');
    if (!str_contains($lastTitle, 'Validation')) {
        return;
    }
    training_onboarding_upgrade_portal_legacy_five_modules($pdo, $courseId);
}

/**
 * Met à jour durées, contenu canvas, fiches « À retenir », intro du bilan et durée totale du parcours « parcours-portail ».
 * Ne modifie pas les questions des quiz existants.
 */
function training_onboarding_refresh_portal_canvas_for_tenant(PDO $pdo, int $tenantId): void
{
    training_onboarding_ensure_extended_parcours_portal($pdo, $tenantId);

    $slug = 'parcours-portail';
    $st = $pdo->prepare('SELECT id FROM training_courses WHERE tenant_id = ? AND slug = ? LIMIT 1');
    $st->execute([$tenantId, $slug]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return;
    }
    $courseId = (int) $row['id'];
    $specs = training_onboarding_portal_module_specs();
    $now = date('Y-m-d H:i:s');

    $modFetch = $pdo->prepare('SELECT id FROM training_modules WHERE course_id = ? ORDER BY position ASC, id ASC');
    $modFetch->execute([$courseId]);
    $moduleIds = $modFetch->fetchAll(PDO::FETCH_COLUMN);
    if ($moduleIds === false || $moduleIds === []) {
        return;
    }
    $moduleIds = array_map('intval', $moduleIds);

    $modUpd = $pdo->prepare(
        'UPDATE training_modules SET estimated_minutes = ?, description = ?, subtitle = ?, learning_objectives = ?, updated_at = ? WHERE id = ?'
    );
    $lesUpd = $pdo->prepare('UPDATE training_lessons SET content = ?, duration_minutes = ?, summary = ?, updated_at = ? WHERE id = ?');
    $lessonIdSt = $pdo->prepare(
        "SELECT id FROM training_lessons WHERE module_id = ? AND lesson_type = 'canvas' ORDER BY position ASC, id ASC LIMIT 1"
    );
    $recapIdSt = $pdo->prepare(
        "SELECT id FROM training_lessons WHERE module_id = ? AND lesson_type = 'richtext' AND position >= 2 ORDER BY position ASC, id ASC LIMIT 1"
    );
    $introIdSt = $pdo->prepare(
        "SELECT id FROM training_lessons WHERE module_id = ? AND lesson_type = 'richtext' AND position = 1 ORDER BY id ASC LIMIT 1"
    );

    $totalMin = 0;
    foreach ($specs as $idx => $spec) {
        if (!isset($moduleIds[$idx])) {
            break;
        }
        $mid = $moduleIds[$idx];
        $minutes = (int) $spec['minutes'];
        $totalMin += $minutes;
        $sub = (string) ($spec['subtitle'] ?? '');
        $desc = trim((string) ($spec['module_description'] ?? ''));
        if ($desc === '') {
            $desc = 'Module ' . ($idx + 1) . ' — ' . $sub;
        }
        $loJson = training_onboarding_module_objectives_json($spec['module_learning_objectives'] ?? []);
        $modUpd->execute([$minutes, $desc, $sub, $loJson, $now, $mid]);

        if (!empty($spec['deck']) && is_array($spec['deck'])) {
            $lessonIdSt->execute([$mid]);
            $lid = $lessonIdSt->fetchColumn();
            if ($lid) {
                $deckJson = json_encode($spec['deck'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $dur = max(6, (int) ceil($minutes * 0.65));
                $summary = (string) $spec['lesson_summary'];
                if (strlen($summary) > 500) {
                    $summary = substr($summary, 0, 497) . '…';
                }
                $lesUpd->execute([$deckJson, $dur, $summary, $now, (int) $lid]);
            }
        }

        if (!empty($spec['recap_html'])) {
            $recapIdSt->execute([$mid]);
            $rid = $recapIdSt->fetchColumn();
            if ($rid) {
                $sum = 'Synthèse courte pour ancrer les idées du module.';
                if (strlen($sum) > 500) {
                    $sum = substr($sum, 0, 497) . '…';
                }
                $lesUpd->execute([(string) $spec['recap_html'], 5, $sum, $now, (int) $rid]);
            }
        }

        if (!empty($spec['mid_quiz']) && !empty($spec['intro_html'])) {
            $introIdSt->execute([$mid]);
            $iid = $introIdSt->fetchColumn();
            if ($iid) {
                $sum = (string) $spec['lesson_summary'];
                if (strlen($sum) > 500) {
                    $sum = substr($sum, 0, 497) . '…';
                }
                $lesUpd->execute([(string) $spec['intro_html'], 3, $sum, $now, (int) $iid]);
            }
        }
    }

    $pdo->prepare(
        'UPDATE training_courses SET description = ?, short_description = ?, learning_objectives = ?, estimated_minutes = ?, updated_at = ? WHERE id = ?'
    )->execute([
        training_onboarding_course_description(),
        training_onboarding_course_short_description(),
        training_onboarding_course_objectives(),
        $totalMin,
        $now,
        $courseId,
    ]);
}

/** @return array{version:int,modals:list<array<string,mixed>>,slides:list<array<string,mixed>>} */
function training_onboarding_deck_overview(): array
{
    return [
        'version' => 2,
        'modals' => [
            [
                'id' => 'onb-sec',
                'title' => 'Rappels sécurité',
                'body' => '<ul><li><strong>Mot de passe :</strong> gardez-le pour vous ; changez-le si vous pensez qu’il a pu être vu par une autre personne.</li><li><strong>Ordinateur partagé :</strong> déconnectez-vous du portail quand vous avez terminé.</li><li><strong>Adresse e-mail :</strong> si vous la modifiez, suivez les étapes de confirmation affichées sur le site.</li><li><strong>Contenus sensibles :</strong> ne les copiez pas sur des canaux personnels ; restez dans les espaces prévus par votre organisation.</li></ul>',
            ],
        ],
        'opening' => [
            'eyebrow' => 'Parcours d’accueil',
            'title' => '',
            'lead' => 'Ce module pose le cadre : à quoi sert le portail, comment lire ce parcours, et quels réflexes de sécurité garder en tête.',
            'stats' => [
                ['label' => 'Durée indicative', 'value' => '~26 min'],
                ['label' => 'Format', 'value' => 'Parcours visuel'],
                ['label' => 'Objectif', 'value' => 'Finalité + risques + sécurité'],
            ],
        ],
        'closure' => [
            'title' => 'Synthèse — Vue d’ensemble',
            'seen' => [
                'Finalité institutionnelle : information stable, coordination vivante, suivi pédagogique — avec des lieux distincts sur le site.',
                'Ce que le portail n’est pas : ni substitut à la chaîne de commandement, ni dépôt anarchique des notes officielles sur le forum.',
                'Erreurs fréquentes (forum = tout, panne imaginaire, session laissée ouverte) et comment les corriger.',
            ],
            'acquired' => [
                'Vous savez réagir de façon raisonnable si une rubrique manque : contexte, rôle, puis demande au staff.',
                'Vous distinguez référence documentaire et discussion ; vous connaissez les gestes de sécurité du compte.',
            ],
            'nextHint' => 'Enchaînez avec le module « Navigation et compte » : tableau de bord, menus, profil, préférences et multi-communautés.',
        ],
        'slides' => [
            [
                'template' => 'title_hero',
                'title' => 'Bienvenue sur le portail',
                'subtitle' => 'Formation d’accueil — lecture active',
                'body' => '<p>Ce site regroupe ce dont vous avez besoin pour suivre la vie de votre communauté : <strong>consignes stabilisées</strong> (documents), <strong>échanges</strong> (forum), <strong>compétences</strong> (formations), <strong>coordination</strong> (événements, pointage selon les réglages) et <strong>votre dossier</strong> (personnel). Ce parcours vise un seul résultat : que vous sachiez <em>où</em> chercher l’information et <em>comment</em> agir sans improviser.</p><p>Les textes sont longs volontairement : ce n’est pas une brochure marketing, c’est un mode d’emploi. Si une rubrique n’existe pas chez vous, c’est souvent lié aux droits ou à la configuration — ce n’est pas une erreur de parcours de votre part.</p>',
                'contextKicker' => 'Étape 01 · Cadrage',
                'surface' => 'elevated',
                'metric' => ['label' => 'Pour qui', 'value' => 'Tous les membres'],
                'cards' => [
                    ['label' => 'Documents', 'body' => 'Notes et fichiers de référence, retrouvables et mis à jour par le staff.'],
                    ['label' => 'Forum & annonces', 'body' => 'Échanges et relances ; ce n’est pas le stockage des versions finales.'],
                    ['label' => 'Formations', 'body' => 'Parcours tracés, parfois obligatoires ou certifiants selon les règles.'],
                ],
                'insights' => [
                    [
                        'variant' => 'key',
                        'title' => '',
                        'body' => 'Le portail oriente : tableau de bord et menu reflètent ce que votre rôle permet de voir.',
                    ],
                ],
            ],
            [
                'template' => 'reading_article',
                'title' => 'À quoi sert concrètement ce portail ?',
                'subtitle' => 'Stabiliser l’information, pas la noyer',
                'contextKicker' => 'Étape 02 · Lecture',
                'surface' => 'default',
                'insights' => [
                    [
                        'variant' => 'vigilance',
                        'title' => '',
                        'body' => 'Si une rubrique manque, vérifiez la communauté active et votre affectation avant de conclure à une « panne ».',
                    ],
                ],
                'body' => <<<'HTML'
<p>Le portail répond à un problème simple : lorsque chacun va chercher l’information sur des canaux informels, les versions se multiplient, les retardataires ne voient pas les mises à jour, et le staff passe son temps à répéter la même consigne. Ici, l’objectif est que la <strong>version de référence</strong> vive dans des endroits identifiables : documents publiés, fils de discussion classés, formations suivies et tracées.</p>
<p>Après connexion, vous n’êtes pas censé « explorer au hasard » : le <strong>tableau de bord</strong> et le <strong>menu</strong> vous orientent vers ce qui est ouvert pour votre rôle. Vous pouvez aussi disposer d’une zone regroupant les modules d’<strong>opérations</strong> : lieu central de mission, briefings, organigramme, outils tactiques selon ce que votre communauté a activé. Ce n’est pas décoratif : ce sont des raccourcis pour éviter les détours.</p>
<p>Le portail ne remplace pas le jugement ni la chaîne de commandement : il <strong>porte</strong> l’information et la formation. Une note officielle reste une note officielle ; un message sur le forum reste un échange ; une formation indique ce que vous avez parcouru et validé, pas votre valeur opérationnelle au sens tactique.</p>
<div class="lms-reading-callout lms-reading-callout--info"><p><strong>À retenir</strong> : si vous ne voyez pas une rubrique mentionnée dans ce parcours, commencez par vérifier que vous êtes dans la bonne communauté (lorsque vous en avez plusieurs), puis demandez au staff si l’accès est normal ou s’il manque une affectation de rôle.</p></div>
HTML
                ,
            ],
            [
                'template' => 'reading_article',
                'title' => 'Déroulé de ce parcours et méthode de travail',
                'subtitle' => 'Lectures, bilan interrogé, puis validation finale',
                'body' => <<<'HTML'
<p>Ce parcours enchaîne plusieurs modules de lecture, un <strong>bilan interrogé à mi-parcours</strong> pour ancrer les premiers acquis, puis le module sur la vie collective (forum, événements) et enfin la <strong>validation finale</strong>. L’ordre est logique : d’abord la vision d’ensemble et la sécurité du compte, ensuite la navigation quotidienne, puis les contenus « métier » (personnel, documents, formations), avant le bilan, le collectif et la manière dont le site atteste vos acquis.</p>
<h3>Comment lire efficacement</h3>
<p>Utilisez les boutons <strong>Précédent</strong> et <strong>Suivant</strong> sous les diapositives. Ne cherchez pas à « swiper » trop vite : plusieurs écrans contiennent des nuances importantes (par exemple la différence entre un document officiel et un fil de discussion). Lorsqu’un <strong>texte à trous</strong> apparaît, complétez-le avant de valider : c’est un mini-test de vocabulaire intégré au parcours.</p>
<h3>Si quelque chose reste flou pour votre unité</h3>
<p>Notez la question pendant la lecture, puis posez-la sur le canal prévu par votre organisation (référent, réunion, fil dédié). Ce parcours décrit le fonctionnement général du portail ; votre unité peut avoir des conventions supplémentaires (horaires, niveaux de diffusion, procédure de validation des absences, etc.).</p>
<div class="lms-reading-callout lms-reading-callout--tip"><p><strong>Erreur fréquente</strong> : croire que « tout est sur le forum ». Le forum sert à débattre, annoncer, relancer ; les fichiers de référence et les textes stabilisés doivent vivre dans la rubrique documents (ou équivalent) lorsque le staff les y place.</p></div>
HTML
                ,
            ],
            [
                'template' => 'reading_article',
                'title' => 'À l’issue du parcours complet, vous saurez…',
                'subtitle' => 'Objectifs opérationnels',
                'body' => <<<'HTML'
<ul>
<li>expliquer à un nouveau membre à quoi sert le tableau de bord et comment retrouver l’aide ou la documentation du site ;</li>
<li>mettre à jour vous-même profil, préférences et sécurité du compte sans demander au staff pour chaque détail ;</li>
<li>ouvrir la rubrique documents, comprendre pourquoi un fichier peut être masqué, et ne pas rediffuser un contenu sensible hors des canaux prévus ;</li>
<li>parcourir le catalogue des formations, distinguer inscription libre et assignation par le staff, et reprendre un module en cours ;</li>
<li>participer au forum sans saturer les catégories ni ignorer les annonces officielles ;</li>
<li>traiter un événement comme un engagement : inscription, prévenance en cas d’empêchement, respect des consignes de présence ;</li>
<li>réussir le bilan interrogé à mi-parcours puis le questionnaire final, et utiliser les explications affichées pour réviser en cas d’échec ;</li>
<li>comprendre ce que signifient pour vous une formation <strong>obligatoire</strong> et une formation <strong>certifiante</strong>, ainsi que le rôle de l’attestation.</li>
</ul>
<p>Ce n’est pas une liste à décorer : c’est le socle minimal attendu d’un membre qui utilise le portail au quotidien.</p>
HTML
                ,
            ],
            [
                'template' => 'reading_article',
                'title' => 'Ce que le portail n’est pas',
                'subtitle' => 'Éviter les malentendus d’usage',
                'contextKicker' => 'Étape 03 · Cadrage',
                'body' => <<<'HTML'
<p>Le portail <strong>n’est pas</strong> un substitut à la chaîne de commandement ni au jugement sur le terrain : il porte l’information et la formation, pas l’autorité opérationnelle.</p>
<p>Il <strong>n’est pas</strong> un espace où toute note officielle peut rester définitivement dans un fil de discussion : la version stabilisée appartient aux documents (ou équivalent) lorsque le staff y procède.</p>
<p>Il <strong>n’est pas</strong> une messagerie personnelle : les échanges publics ou de service suivent des règles de canal ; les sujets sensibles passent par les procédures prévues.</p>
<p>Enfin, une formation validée sur le site <strong>n’est pas</strong>, à elle seule, une reconnaissance tacite de toutes les compétences métier : elle atteste du parcours réalisé selon les règles affichées.</p>
HTML
                ,
            ],
            [
                'template' => 'common_mistakes',
                'title' => 'Erreurs d’usage les plus fréquentes',
                'mistakes' => [
                    [
                        'error' => 'Tout centraliser sur le forum',
                        'why' => 'Le forum est conçu pour la conversation et les relais, pas pour remplacer la rubrique documents.',
                        'consequence' => 'Versions multiples, fils longs, nouveaux membres qui ne retrouvent pas la référence.',
                        'correction' => 'Demander ou attendre la publication dans les documents lorsque le staff valide un texte de référence.',
                    ],
                    [
                        'error' => 'Conclure trop vite à une « panne » du site',
                        'why' => 'Souvent, une rubrique absente correspond à des droits, à une autre communauté active ou à une fonction non activée.',
                        'consequence' => 'Messages d’alerte publics inutiles et temps perdu pour le staff.',
                        'correction' => 'Vérifier le contexte (communauté, rôle), puis s’adresser au canal prévu pour le support.',
                    ],
                    [
                        'error' => 'Négliger la déconnexion sur poste partagé',
                        'why' => 'La session peut rester ouverte pour le prochain utilisateur du même équipement.',
                        'consequence' => 'Accès au compte et aux contenus au nom de la mauvaise personne.',
                        'correction' => 'Utiliser la déconnexion explicite du portail en fin de session.',
                    ],
                ],
            ],
            [
                'template' => 'scenario_decision',
                'title' => 'Je ne trouve pas une rubrique mentionnée dans ce parcours',
                'context' => 'Vous suivez la formation ; un encadré cite une page (documents, organigramme, etc.) que vous ne voyez pas dans votre menu.',
                'situation' => '<p>Vous devez agir rapidement pour un sujet opérationnel. Vous pensez que le site est « cassé ».</p>',
                'options' => [
                    ['id' => 'a', 'text' => 'Vérifier la communauté active et, si besoin, demander au staff si l’accès est normal pour votre rôle avant de conclure.'],
                    ['id' => 'b', 'text' => 'Publier immédiatement un message d’alerte dans toutes les catégories du forum.'],
                    ['id' => 'c', 'text' => 'Partager vos identifiants avec un camarade pour qu’il teste depuis son compte.'],
                    ['id' => 'd', 'text' => 'Abandonner toute utilisation du portail jusqu’à nouvel ordre.'],
                ],
                'correctOptionId' => 'a',
                'explanation' => '<p>La première démarche raisonnable est de contrôler le <strong>contexte</strong> (communauté, rôle) puis de solliciter le staff sur le canal prévu. Les autres options créent du bruit, un risque de sécurité ou une interruption inutile de travail.</p>',
            ],
            [
                'template' => 'title_hero',
                'title' => 'Sécurité : les bases',
                'subtitle' => 'Gestes simples, effet collectif',
                'body' => '<p>Un compte compromis ou une session laissée ouverte sur un poste partagé, ce n’est pas « une affaire personnelle » : c’est un risque pour toute la communauté (usurpation, fuite de consignes, spam). Les bons réflexes sont courts : mot de passe sérieux, déconnexion explicite, prudence sur les copies d’écran et les transferts hors site.</p>',
                'primaryAction' => ['type' => 'modal', 'label' => 'Voir la liste des rappels', 'modalId' => 'onb-sec'],
            ],
            [
                'template' => 'resources_list',
                'title' => 'Accès directs après connexion',
                'subtitle' => 'Liens utiles',
                'body' => '<p>Si un lien ne fonctionne pas, votre site peut utiliser une adresse légèrement différente : repassez alors par le menu principal.</p>',
                'resources' => [
                    ['title' => 'Tableau de bord', 'url' => '/public/dashboard'],
                    ['title' => 'Documentation du portail', 'url' => '/public/documentation'],
                ],
            ],
            [
                'template' => 'reading_article',
                'title' => 'Avant de passer au module suivant',
                'subtitle' => 'Prenez le temps de l’ancrage',
                'body' => '<p>La suite du parcours entre dans le détail de la navigation et du compte. Si vous avez sauté des paragraphes, revenez en arrière : les modules suivants supposent que vous savez déjà ce qu’est le tableau de bord, pourquoi les documents ne sont pas interchangeables avec le forum, et pourquoi la sécurité du compte est une responsabilité partagée.</p>',
            ],
        ],
    ];
}

/** @return array{version:int,modals:list<mixed>,slides:list<array<string,mixed>>} */
function training_onboarding_deck_navigation(): array
{
    return [
        'version' => 2,
        'modals' => [],
        'opening' => [
            'eyebrow' => 'Module pratique',
            'title' => '',
            'lead' => 'Menus, tableau de bord, compte et recherche : les bons réflexes pour ne pas perdre le fil au quotidien.',
            'stats' => [
                ['label' => 'Durée indicative', 'value' => '~24 min'],
                ['label' => 'Focus', 'value' => 'Navigation'],
            ],
        ],
        'closure' => [
            'title' => 'Synthèse — Navigation et compte',
            'seen' => [
                'Le tableau de bord synthétise ce qui vous concerne après connexion.',
                'Le compte regroupe profil, préférences et sécurité.',
            ],
            'acquired' => [
                'Vous savez où ajuster notifications et identifiants affichés.',
                'Vous évitez les erreurs de contexte entre plusieurs communautés.',
            ],
            'nextHint' => 'Enchaînez avec le module sur le personnel, les documents et le catalogue des formations.',
        ],
        'slides' => [
            [
                'template' => 'title_hero',
                'title' => 'Navigation et compte',
                'subtitle' => 'Lire le site comme un outil de travail',
                'body' => '<p>Le <strong>menu principal</strong> n’est pas une vitrine : c’est la liste des fonctions auxquelles votre rôle a droit. Les intitulés sont volontairement lisibles (accueil, formations, forum, personnel, documents…). Sur grand écran, vous pouvez aussi avoir un menu regroupant les <strong>opérations</strong> : lieu central de mission, pointage, briefings, organigramme, outils tactiques — selon ce que votre communauté a activé. Sur mobile, le même contenu est souvent dans un menu latéral ou derrière une icône « menu ».</p><p>L’habitude à prendre : avant de poster ou de répondre, vérifiez que vous êtes au bon endroit dans le site (bonne communauté, bonne rubrique).</p>',
                'contextKicker' => 'Étape 01 · Structure du site',
                'surface' => 'elevated',
                'cards' => [
                    ['label' => 'Menu principal', 'body' => 'Accès aux rubriques autorisées pour votre rôle.'],
                    ['label' => 'Zone Opérations', 'body' => 'Raccourcis tactiques et logistiques si votre communauté les active.'],
                    ['label' => 'Mobile', 'body' => 'Même logique, présentation adaptée (menu latéral ou icône).'],
                ],
            ],
            [
                'template' => 'reading_article',
                'title' => 'Tableau de bord : votre premier arrêt',
                'subtitle' => 'Synthèse, pas détail tactique',
                'body' => <<<'HTML'
<p>Le <strong>tableau de bord</strong> est l’écran qui accueille souvent la session après connexion. Il ne remplace pas une carte d’opération ni un ordre écrit : il <strong>signale</strong> ce qui mérite attention pour votre compte — raccourcis vers des pages utiles, rappels de formations en cours ou à venir, parfois les prochains événements ou des messages du staff selon la configuration.</p>
<p>Traitez-le comme la « une » du portail pour <em>vous</em> : deux minutes suffisent à repérer si une date limite approche, si une formation obligatoire attend une action, ou si une annonce récente a été mise en avant. Si le tableau de bord est vide, cela ne veut pas dire qu’il ne se passe rien dans la communauté : ouvrez le forum, les documents ou le calendrier selon votre fonction.</p>
<div class="lms-reading-callout lms-reading-callout--tip"><p><strong>Bon réflexe</strong> : à chaque retour sur le site, passez par le tableau de bord avant d’aller sur les réseaux sociaux ou messageries externes — la consigne officielle est ici en premier.</p></div>
HTML
                ,
            ],
            [
                'template' => 'reading_article',
                'title' => 'Compte, profil, préférences et sécurité',
                'subtitle' => 'Ce que vous contrôlez vous-même',
                'body' => <<<'HTML'
<p>La rubrique <strong>compte</strong> (souvent « Mon compte » ou « Paramètres ») concentre tout ce qui touche à <em>votre</em> présence sur le portail. Elle sert à trois grandes familles d’actions.</p>
<h3>Profil et identité affichée</h3>
<p>Selon les règles de votre communauté, certaines informations peuvent être visibles par le staff ou d’autres membres (nom affiché, affectation, champs de dossier). Les mettre à jour quand elles changent évite les erreurs d’affectation et les convocations à mauvais escient.</p>
<h3>Préférences</h3>
<p>Notifications, affichage, parfois choix de ce que vous acceptez de montrer : ce sont des réglages personnels. Si vous désactivez tout sans le vouloir, vous raterez des rappels légitimes ; si vous laissez tout ouvert sur un canal bruyant, vous finirez par ignorer les messages importants. Trouvez un équilibre et révisez-le après une grosse période d’activité.</p>
<h3>Sécurité</h3>
<p>Mot de passe, confirmation d’adresse de contact, parfois la liste des appareils reconnus : toute modification sensible peut déclencher une vérification supplémentaire. C’est normal. Gardez une adresse de contact <strong>valide</strong> : c’est le filet de sécurité si vous perdez l’accès.</p>
HTML
                ,
            ],
            [
                'template' => 'reading_article',
                'title' => 'Recherche et multi-organisations',
                'subtitle' => 'Éviter les doublons et les erreurs de contexte',
                'body' => <<<'HTML'
<p>Lorsque la recherche est disponible, utilisez-la avant de créer un nouveau sujet sur le forum ou avant de redemander un document : souvent, le fil ou le fichier existe déjà. Les résultats respectent vos droits : si quelque chose n’apparaît pas, ce n’est pas forcément qu’il n’existe pas — il peut être simplement hors de votre périmètre.</p>
<p>Si vous participez à <strong>plusieurs communautés</strong> sur la même plateforme, un écran de choix peut s’afficher à la connexion. L’erreur classique est de répondre à un briefing ou de signer une présence alors qu’on est encore « dans » l’autre organisation. Vérifiez l’en-tête du site ou le sélecteur avant toute action engageante.</p>
HTML
                ,
            ],
            [
                'template' => 'fill_blanks',
                'title' => 'Vérification rapide',
                'contextKicker' => 'Étape intermédiaire · Auto-évaluation',
                'metric' => ['label' => 'Validation', 'value' => 'Réponses exactes requises'],
                'body' => '<p>Après connexion, l’écran qui regroupe en général raccourcis et rappels utiles pour votre session est le [[tableau de bord]].</p><p>Pour le mot de passe, les préférences et les réglages du compte, ouvrez la section <strong>compte</strong> (souvent intitulée « Mon compte ») depuis le menu principal.</p>',
            ],
            [
                'template' => 'knowledge_check',
                'title' => 'Repères pour le quotidien',
                'body' => "Revoyez périodiquement vos préférences de notification : un rappel de formation ou d’événement se joue souvent sur un simple e-mail ou une alerte interne.\nSi un libellé de menu vous échappe, ouvrez la rubrique plutôt que d’ignorer : les intitulés sont pensés pour le langage courant.\nSur un poste partagé, déconnectez-vous explicitement ; fermer l’onglet ne suffit pas toujours.\nAvant d’ouvrir un nouveau fil sur le forum, recherchez ou parcourez la catégorie pour éviter les doublons.\nSi une page refuse l’accès, considérez que votre rôle n’inclut peut-être pas cette fonction : demandez au staff au lieu d’essayer de contourner.",
            ],
            [
                'template' => 'resources_list',
                'title' => 'Raccourcis fréquents',
                'subtitle' => '',
                'body' => '',
                'resources' => [
                    ['title' => 'Tableau de bord', 'url' => '/public/dashboard'],
                    ['title' => 'Mon compte', 'url' => '/public/account'],
                    ['title' => 'Préférences', 'url' => '/public/account/preferences'],
                    ['title' => 'Recherche', 'url' => '/public/search'],
                ],
            ],
        ],
    ];
}

/** @return array{version:int,modals:list<mixed>,slides:list<array<string,mixed>>} */
function training_onboarding_deck_org(): array
{
    return [
        'version' => 2,
        'modals' => [],
        'opening' => [
            'eyebrow' => 'Contenus structurés',
            'title' => '',
            'lead' => 'Personnel, documents et formations : où vit l’information « durable » et comment la progression est enregistrée.',
            'stats' => [
                ['label' => 'Durée indicative', 'value' => '~26 min'],
                ['label' => 'Thème', 'value' => 'Organisation'],
            ],
        ],
        'closure' => [
            'title' => 'Synthèse — Organisation et contenus',
            'seen' => [
                'La fiche personnelle relie votre compte au dossier tenu par la communauté.',
                'Les documents portent la version de référence ; le forum sert à échanger.',
            ],
            'acquired' => [
                'Vous distinguez inscription libre et assignation par le staff.',
                'Vous savez pourquoi une formation n’est terminée que lorsque toutes les étapes requises le sont.',
            ],
            'nextHint' => 'Passez au bilan interrogé de mi-parcours, puis au module sur le forum et les événements.',
        ],
        'slides' => [
            [
                'template' => 'title_hero',
                'title' => 'Organisation et contenus',
                'subtitle' => 'Personnel, documents, formations : la chaîne de l’information',
                'body' => '<p>Ce module décrit comment le portail porte l’information « durable » : <strong>qui vous êtes dans l’unité</strong>, <strong>où sont les fichiers de référence</strong>, et <strong>comment le site enregistre ce que vous avez appris</strong>. Ce que vous voyez dépend de votre rôle ; l’absence d’accès n’est pas une punition, c’est en général un périmètre de diffusion.</p>',
                'contextKicker' => 'Étape 01 · Chaîne d’information',
                'surface' => 'elevated',
                'metric' => ['label' => 'Principe', 'value' => 'Périmètre selon le rôle'],
                'insights' => [
                    [
                        'variant' => 'result',
                        'title' => '',
                        'body' => 'Objectif : savoir où mettre à jour votre dossier et où trouver la version officielle d’un texte.',
                    ],
                ],
            ],
            [
                'template' => 'reading_article',
                'title' => 'Personnel et organigramme',
                'subtitle' => 'Dossier individuel et structure collective',
                'body' => <<<'HTML'
<p>L’espace <strong>personnel</strong> relie votre compte de connexion à votre <strong>dossier</strong> tel que la communauté le tient : affectation, fonctions affichées, champs que le staff a demandés de remplir, parfois pièces ou validations selon les processus en place. Une fiche incomplète ou périmée produit des erreurs réelles : mauvaise convocation, mauvais groupe, retard sur une exigence administrative.</p>
<p>L’<strong>organigramme</strong> donne une vue de la structure et des rattachements. Il aide à savoir à qui s’adresser pour un sujet donné, mais il ne remplace pas un ordre du jour ou une note officielle : c’est une photographie organisationnelle, pas la doctrine complète.</p>
HTML
                ,
            ],
            [
                'template' => 'reading_article',
                'title' => 'Documents : la version de référence',
                'subtitle' => 'Pourquoi ce n’est pas « comme le forum »',
                'contextKicker' => 'Étape clé · Référence vs discussion',
                'surface' => 'default',
                'cards' => [
                    ['label' => 'Documents', 'body' => 'Textes et fichiers stabilisés, avec contrôle de diffusion.'],
                    ['label' => 'Forum', 'body' => 'Conversation vivante : annonces, questions, relances.'],
                    ['label' => 'Erreur fréquente', 'body' => 'Publier la « version finale » uniquement dans un fil de discussion.'],
                ],
                'body' => <<<'HTML'
<p>La rubrique <strong>documents</strong> sert à publier ce qui doit rester <strong>stable</strong> et <strong>retrouvable</strong> : notes, guides, modèles, visuels autorisés, parfois packs techniques. Chaque dossier ou fichier peut avoir un niveau de diffusion différent ; si vous ne voyez pas un contenu, c’est souvent qu’il est réservé à un autre groupe.</p>
<p>Le <strong>forum</strong>, lui, vit par messages successifs : on y annonce, on débat, on relance. Un fil n’est pas un bon endroit pour « stocker » la version finale d’un texte : il se noie, on ne sait plus laquelle est la bonne page, et les nouveaux arrivants ne remontent pas 200 messages. En pratique, lorsque le staff valide un document, il doit vivre dans la rubrique documents (ou équivalent) ; le forum sert à expliquer le contexte ou à répondre aux questions.</p>
<p>Ne recopiez pas un fichier sensible sur une messagerie personnelle ou un stockage privé : vous perdez le contrôle de la diffusion et vous contournez les traces prévues par l’organisation.</p>
<div class="lms-reading-callout lms-reading-callout--info"><p><strong>À retenir</strong> : document = référence stabilisée ; forum = conversation. Si les deux se mélangent, l’information se dégrade pour tout le monde.</p></div>
HTML
                ,
            ],
            [
                'template' => 'reading_article',
                'title' => 'Formations et catalogue LMS',
                'subtitle' => 'Inscription, assignation, progression, obligation',
                'body' => <<<'HTML'
<p>Le <strong>catalogue</strong> liste les parcours auxquels vous pouvez accéder. Deux grands cas : vous vous inscrivez vous-même à une formation ouverte, ou le staff vous <strong>assigne</strong> un parcours (souvent avec une attente de complétion dans un délai). La fiche indique en général la durée estimée, le niveau, et si le parcours est <strong>obligatoire</strong> et/ou <strong>certifiant</strong>.</p>
<p>À l’intérieur d’un parcours, les <strong>modules</strong> et <strong>leçons</strong> peuvent être verrouillés dans un ordre : respectez-le, sinon vous risquez de croire avoir « tout vu » alors qu’une étape bloquante manque encore. Le site enregistre la progression : vous pouvez fermer la session et reprendre, mais une formation n’est réellement terminée que lorsque toutes les étapes requises le sont — le système reflète le parcours effectif, pas l’intention.</p>
<p>Les parcours « canvas » comme celui-ci se lisent diapositive par diapositive ; d’autres formations mélangent texte, média, quiz intermédiaires. Le principe reste le même : chaque étape a une fonction pédagogique ou réglementaire.</p>
HTML
                ,
            ],
            [
                'template' => 'reading_article',
                'title' => 'Déroulé type d’un parcours sur le portail',
                'subtitle' => 'De l’ouverture à l’attestation',
                'body' => <<<'HTML'
<p><strong>Ouverture.</strong> Vous accédez à la fiche formation après inscription ou assignation. Lisez l’introduction et les objectifs : elles disent ce que le staff attend comme résultat.</p>
<p><strong>Modules.</strong> Vous enchaînez les leçons selon les règles du parcours. Certaines sont de la lecture, d’autres des exercices ou des questionnaires partiels.</p>
<p><strong>Évaluation.</strong> Un quiz ou une épreuve finale peut exiger un score minimal. Les tentatives sont en nombre limité : utilisez les retours du questionnaire pour combler vos lacunes avant de retenter.</p>
<p><strong>Clôture.</strong> Lorsque tout est validé, le parcours est marqué comme terminé. Si la formation est certifiante, une <strong>attestation</strong> ou un équivalent peut être proposé selon les réglages de votre communauté.</p>
HTML
                ,
            ],
            [
                'template' => 'knowledge_check',
                'title' => 'Ce qui compte vraiment côté organisation',
                'body' => "Les contenus sensibles restent dans les espaces prévus ; ne les faites pas migrer vers des canaux privés non maîtrisés.\nUne formation obligatoire doit être traitée dans les délais fixés par le staff : l’outil permet de suivre l’avancement.\nConsultez régulièrement votre espace formations pour voir les assignations et les rappels.\nL’organigramme oriente ; il ne remplace pas une consigne écrite ou un ordre de mission.\nSi un document semble faux ou obsolète, signalez-le au responsable plutôt que de le recirculer.",
            ],
            [
                'template' => 'resources_list',
                'title' => 'Accès directs',
                'subtitle' => '',
                'body' => '',
                'resources' => [
                    ['title' => 'Ma fiche personnelle', 'url' => '/public/personnel/me'],
                    ['title' => 'Organigramme', 'url' => '/public/orbat'],
                    ['title' => 'Documents', 'url' => '/public/documents'],
                    ['title' => 'Catalogue des formations', 'url' => '/public/formations'],
                ],
            ],
        ],
    ];
}

/** @return array{version:int,modals:list<mixed>,slides:list<array<string,mixed>>} */
function training_onboarding_deck_community(): array
{
    return [
        'version' => 2,
        'modals' => [],
        'opening' => [
            'eyebrow' => 'Vie collective',
            'title' => '',
            'lead' => 'Forum, événements, annonces : des règles simples pour que l’information reste utile à toute l’unité.',
            'stats' => [
                ['label' => 'Durée indicative', 'value' => '~20 min'],
                ['label' => 'Enjeu', 'value' => 'Clarté collective'],
            ],
        ],
        'closure' => [
            'title' => 'Synthèse — Communauté',
            'seen' => [
                'Un bon titre de sujet et une recherche avant de poster évitent le bruit.',
                'Les inscriptions aux créneaux engagent : prévenir en cas d’empêchement.',
            ],
            'acquired' => [
                'Vous savez quand utiliser un signalement ou un message privé plutôt qu’un post public.',
                'Vous appliquez des réflexes de participation utile dès la première semaine.',
            ],
            'nextHint' => 'Il reste la validation finale par questionnaire et l’attestation.',
        ],
        'slides' => [
            [
                'template' => 'title_hero',
                'title' => 'Vie de communauté',
                'subtitle' => 'Coordonner sans encombrer les canaux',
                'body' => '<p>Le <strong>forum</strong> et les <strong>événements</strong> sont les lieux où la communauté vit au quotidien : annonces, questions, briefings, débriefs, organisation logistique. La qualité collective dépend de chacun : un fil lisible vaut mieux que vingt messages redondants ; une inscription honnête vaut mieux qu’une absence non signalée.</p>',
                'contextKicker' => 'Étape 01 · Cadre',
                'surface' => 'elevated',
                'cards' => [
                    ['label' => 'Forum', 'body' => 'Structurer les sujets et respecter les annonces épinglées.'],
                    ['label' => 'Événements', 'body' => 'Inscription = engagement logistique pour le staff.'],
                    ['label' => 'Signalement', 'body' => 'Canal adapté pour les sujets sensibles.'],
                ],
            ],
            [
                'template' => 'reading_article',
                'title' => 'Forum : structurer la parole collective',
                'subtitle' => 'Titres, catégories, respect',
                'body' => <<<'HTML'
<p>Avant d’ouvrir un <strong>nouveau sujet</strong>, parcourez la catégorie et utilisez la recherche : souvent, le problème est déjà en discussion. Si vous ouvrez un fil, choisissez un <strong>titre</strong> qui dit ce que vous cherchez ou ce que vous proposez, pas une phrase vague du type « question ».</p>
<p>Dans le fil, allez à l’essentiel : contexte utile, question claire, proposition si vous en avez une. Le désaccord est possible, la grossièreté n’apporte rien. Les messages hors-sujet répétés, le spam et les polémiques stériles obligent le staff à modérer — ce temps-là n’est plus disponible pour vous aider sur le fond.</p>
<p>Lorsque le staff épingle une annonce, considérez qu’elle a force de consigne pour la période concernée : lisez-la avant de poster une question déjà traitée.</p>
HTML
                ,
            ],
            [
                'template' => 'reading_article',
                'title' => 'Événements, inscriptions et présence',
                'subtitle' => 'Engagement et logistique',
                'body' => <<<'HTML'
<p>Les <strong>événements</strong> matérialisent des créneaux : date, lieu ou lien, description, parfois matériel attendu ou tenue. Lorsque l’inscription est demandée, elle sert à dimensionner les moyens (places, encadrement, supports). S’inscrire « pour voir » puis ne pas venir sans prévenir dégrade la confiance et fait perdre du temps.</p>
<p>Si vous ne pouvez pas venir, <strong>prévenez</strong> selon la procédure de votre organisation (message au staff, modification de l’inscription, fil prévu). Ce n’est pas une option de politesse : c’est une donnée d’organisation.</p>
<p>Certaines communautés utilisent un <strong>pointage</strong> ou une feuille de présence numérique : suivez les consignes affichées sur place. Un pointage incorrect peut fausser les statistiques ou les validations administratives.</p>
HTML
                ,
            ],
            [
                'template' => 'reading_article',
                'title' => 'Annonces officielles et signalements',
                'subtitle' => 'Quand passer par un canal dédié',
                'body' => <<<'HTML'
<p>Les annonces importantes sont souvent mises en avant en tête de forum ou sur le tableau de bord. Elles peuvent compléter une note dans les documents : l’une explique le « maintenant », l’autre stabilise le texte de référence.</p>
<p>Pour un problème sensible — contenu inapproprié, conflit personnel, erreur de sécurité — utilisez le <strong>canal prévu</strong> (signalement, message à un modérateur, procédure interne). Une « dénonciation » publique désordonnée crée du bruit, expose des personnes et complique la résolution.</p>
HTML
                ,
            ],
            [
                'template' => 'reading_article',
                'title' => 'Synthèse des bons réflexes',
                'subtitle' => 'À appliquer dès la première semaine',
                'body' => <<<'HTML'
<p>Lisez les annonces avant de poster. Répondez dans le fil qui traite déjà le sujet lorsque c’est possible. Inscrivez-vous aux créneaux avec sérieux. Prévenez en cas d’empêchement. Remerciez ou synthétisez en fin de fil si cela clarifie la décision pour les suivants.</p>
<p>Ces gestes semblent mineurs ; cumulés sur une centaine de membres, ils font la différence entre un portail utilisable et un chaos de notifications.</p>
HTML
                ,
            ],
            [
                'template' => 'fill_blanks',
                'title' => 'Une dernière vérification',
                'contextKicker' => 'Auto-évaluation',
                'metric' => ['label' => 'Rappel', 'value' => 'Une réponse exacte par trou'],
                'body' => '<p>Avant d’ouvrir un nouveau sujet sur le forum, il est préférable de vérifier qu’un [[fil]] ou une discussion ne traite pas déjà le même problème.</p>',
            ],
            [
                'template' => 'knowledge_check',
                'title' => 'Participation utile',
                'body' => "Un retour sur une formation aide lorsqu’il est précis (ce qui manquait, ce qui était clair), pas lorsqu’il se limite à une critique vague.\nPour un événement, l’empêchement se signale ; l’absence non expliquée se compte aussi.\nNe divulguez pas des informations personnelles sur des tiers sans accord.\nRespectez le ton fixé par votre communauté (formel, sobre, etc.).\nEn cas de doute sur la catégorie du forum, demandez au staff avant de poster.",
            ],
        ],
    ];
}

/** @return array{version:int,modals:list<mixed>,slides:list<array<string,mixed>>} */
function training_onboarding_deck_validation_intro(): array
{
    return [
        'version' => 2,
        'modals' => [],
        'opening' => [
            'eyebrow' => 'Validation',
            'title' => '',
            'lead' => 'Questionnaire final, attestation et reprise de parcours : ce qui se passe après la dernière lecture.',
            'stats' => [
                ['label' => 'Seuil de réussite', 'value' => '80 %'],
                ['label' => 'Tentatives', 'value' => 'Plusieurs (selon la formation)'],
            ],
        ],
        'closure' => [
            'title' => 'Avant de lancer le questionnaire',
            'seen' => [
                'Les questions portent sur les usages du portail vus dans ce parcours.',
                'Une attestation peut être proposée si la formation est certifiante et le score atteint.',
            ],
            'acquired' => [
                'Vous savez comment utiliser un échec comme liste de révision.',
                'Vous savez où retrouver l’historique de vos formations terminées.',
            ],
            'nextHint' => 'Utilisez le bouton pour passer à la leçon quiz lorsqu’elle est disponible dans votre parcours.',
        ],
        'slides' => [
            [
                'template' => 'title_hero',
                'title' => 'Dernière étape : validation',
                'subtitle' => 'Quiz de fin de parcours',
                'body' => '<p>Le questionnaire porte sur les <strong>idées directrices</strong> du portail : navigation, compte, documents, formations, forum, événements, sécurité. Le <strong>seuil de réussite est de 80&nbsp;%</strong>. Vous disposez de <strong>plusieurs tentatives</strong> dans la limite fixée par la formation.</p><p>Les formulations volontairement longues dans certaines réponses fausses imitent des croyances courantes : lisez jusqu’au bout avant de choisir.</p>',
                'contextKicker' => 'Étape finale · Évaluation',
                'surface' => 'elevated',
                'insights' => [
                    [
                        'variant' => 'vigilance',
                        'title' => '',
                        'body' => 'Ne validez pas la dernière réponse si votre connexion est très instable : en cas de doute, attendez un réseau fiable.',
                    ],
                ],
            ],
            [
                'template' => 'reading_article',
                'title' => 'Après le quiz : attestation, échec, reprise',
                'subtitle' => 'Ce que le site retient de vous',
                'body' => <<<'HTML'
<p>Si vous atteignez le score requis et que la formation est <strong>certifiante</strong>, une <strong>attestation</strong> ou un équivalent peut être proposé (téléchargement, trace sur votre dossier, selon les réglages). Ce document atteste que vous avez parcouru et validé <em>ce</em> parcours à cette date — il ne remplace pas une habilitation métier qui serait définie ailleurs.</p>
<p>Si vous échouez, le questionnaire affiche en général des <strong>explications</strong> sur les réponses attendues. Utilisez-les comme liste de révision : retournez sur les modules qui coincent, puis retentez. L’objectif n’est pas de vous piéger mais de vérifier que vous ne partirez pas avec de fausses certitudes (par exemple confondre forum et documents, ou ignorer la déconnexion sur poste partagé).</p>
<p>Conservez une copie de votre attestation si votre organisation vous la demande hors ligne ; le portail peut aussi conserver l’historique de vos formations terminées.</p>
HTML
                ,
            ],
            [
                'template' => 'knowledge_check',
                'title' => 'Avant de lancer le questionnaire',
                'body' => "Prévoyez environ quinze à vingt minutes sans interruption.\nInstallez-vous dans un endroit où vous pouvez lire calmement chaque énoncé.\nSi votre connexion est instable, évitez de valider la dernière réponse au moment où le signal faiblit.\nLes questions restent au niveau « membre du portail », pas au niveau administration technique.\nCe parcours vous a déjà donné le vocabulaire et les situations : le quiz ne demande pas de culture générale extérieure au site.",
            ],
            [
                'template' => 'reading_article',
                'title' => 'Pourquoi cette validation existe',
                'subtitle' => 'Responsabilité partagée',
                'body' => '<p>La communauté a intérêt à ce que chaque membre sache se servir du portail correctement : moins d’erreurs de diffusion, moins de fichiers égarés, moins de questions répétitives au staff. En validant ce parcours, vous confirmez que vous connaissez les bons réflexes — pas que vous êtes infaillible, mais que vous savez où relire l’information quand un doute revient.</p>',
            ],
        ],
    ];
}

function training_onboarding_seed_one_tenant(PDO $pdo, int $tenantId, int $authorUserId): void
{
    $slug = 'parcours-portail';
    if (training_onboarding_course_exists($pdo, $tenantId, $slug)) {
        echo "  training_onboarding_course : tenant {$tenantId} — formation « {$slug} » déjà présente.\n";

        return;
    }

    $themeJson = json_encode([
        'accent' => '#0d9488',
        'accentRgb' => '13, 148, 136',
        'font' => "'IBM Plex Sans', system-ui, sans-serif",
        'radius' => '1.25rem',
        'variant' => 'default',
        'pedagogy_meta' => [
            'target_audience' => ['nouveaux membres', 'membres en reprise'],
            'pedagogical_style' => 'guided_onboarding',
            'completion_message' => 'Parcours portail terminé : vous disposez des repères pour naviguer, tenir votre compte à jour et participer correctement à la vie du site. Les consignes spécifiques de votre unité restent prioritaires.',
            'tags' => ['portail', 'onboarding', 'compte', 'forum', 'documents', 'formations'],
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $uuid = training_onboarding_uuid_v4();
    $now = date('Y-m-d H:i:s');
    $specs = training_onboarding_portal_module_specs();
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
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, NULL, ?, ?, ?, ?, ?, 1, 1, NULL, ?, ?, ?, ?, ?)'
        );
        $ins->execute([
            $tenantId,
            $uuid,
            'Parcours portail — Bien utiliser le site',
            $slug,
            'PORTAIL-101',
            training_onboarding_course_short_description(),
            training_onboarding_course_description(),
            training_onboarding_course_objectives(),
            $themeJson,
            'Portail',
            'initiation',
            'fr',
            $totalMinutes,
            80.00,
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
            $pdo->prepare('UPDATE training_courses SET showcase_sort_order = 1, showcase_badge = ? WHERE id = ?')->execute(['open', $courseId]);
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
            $modLo = training_onboarding_module_objectives_json($m['module_learning_objectives'] ?? []);
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

            if (!empty($m['mid_quiz'])) {
                $summary = (string) $m['lesson_summary'];
                if (strlen($summary) > 500) {
                    $summary = substr($summary, 0, 497) . '…';
                }
                $lesIns->execute([
                    $moduleId,
                    'Pourquoi ce bilan',
                    $summary,
                    'richtext',
                    (string) $m['intro_html'],
                    3,
                    'initiation',
                    1,
                ]);
                $quizIns->execute([
                    $moduleId,
                    'Bilan — premiers réflexes',
                    'Questions sur la navigation, le compte, les documents et le catalogue des formations.',
                    75.00,
                    4,
                    15,
                    1,
                    0,
                    $now,
                ]);
                $midQz = (int) $pdo->lastInsertId();
                training_onboarding_seed_quiz_questions_for_module($pdo, $midQz, training_onboarding_mid_quiz_questions(), $now);

                continue;
            }

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
                'initiation',
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
                    'initiation',
                    2,
                ]);
            }
        }

        if ($finalModuleId > 0) {
            $quizIns->execute([
                $finalModuleId,
                'Quiz — fonctionnement du portail',
                'Validez vos acquis sur la navigation, le compte, les contenus et la vie de la communauté.',
                80.00,
                5,
                24,
                1,
                1,
                $now,
            ]);
            $finalQz = (int) $pdo->lastInsertId();
            training_onboarding_seed_quiz_questions_for_module($pdo, $finalQz, training_onboarding_quiz_questions(), $now);
        }

        $pdo->commit();
        echo "  training_onboarding_course : tenant {$tenantId} — formation « Parcours portail » créée (course_id={$courseId}).\n";
    } catch (\Throwable $e) {
        $pdo->rollBack();
        echo '  [ATTENTION] training_onboarding_course : ' . $e->getMessage() . "\n";
    }
}

/**
 * Questions du bilan à mi-parcours (thèmes des trois premiers modules).
 *
 * @return list<array{text:string,explain?:string,answers:list<array{t:string,ok:bool}>}>
 */
function training_onboarding_mid_quiz_questions(): array
{
    return [
        [
            'text' => 'Après connexion, quel écran regroupe en général les raccourcis et rappels utiles pour votre session ?',
            'explain' => 'Le tableau de bord est le point de départ logique après connexion.',
            'answers' => [
                ['t' => 'Le tableau de bord', 'ok' => true],
                ['t' => 'Uniquement la page de réinitialisation du mot de passe', 'ok' => false],
                ['t' => 'L’historique du navigateur, hors portail', 'ok' => false],
                ['t' => 'Un écran réservé aux seuls formateurs', 'ok' => false],
            ],
        ],
        [
            'text' => 'Où met-on à jour en principe ses préférences de notification et son profil affiché ?',
            'answers' => [
                ['t' => 'Dans la rubrique compte (souvent « Mon compte ») du portail', 'ok' => true],
                ['t' => 'En modifiant le nom du poste de travail', 'ok' => false],
                ['t' => 'Uniquement sur un réseau social extérieur', 'ok' => false],
                ['t' => 'Ce n’est jamais possible en ligne', 'ok' => false],
            ],
        ],
        [
            'text' => 'Où retrouver en priorité une note officielle stabilisée, destinée à tous les membres autorisés ?',
            'answers' => [
                ['t' => 'Dans la rubrique documents du portail, selon le niveau de diffusion', 'ok' => true],
                ['t' => 'Uniquement en enchaînant des messages sur le forum sans fiche dédiée', 'ok' => false],
                ['t' => 'Dans les préférences du compte', 'ok' => false],
                ['t' => 'Sur une messagerie personnelle uniquement', 'ok' => false],
            ],
        ],
        [
            'text' => 'Une formation du catalogue apparaît comme obligatoire : que signifie cela en général ?',
            'answers' => [
                ['t' => 'Le staff attend sa complétion dans le cadre fixé par la communauté', 'ok' => true],
                ['t' => 'Elle est purement décorative sur le site', 'ok' => false],
                ['t' => 'Elle ne concerne que les visiteurs non connectés', 'ok' => false],
                ['t' => 'Elle se valide sans parcourir le contenu', 'ok' => false],
            ],
        ],
        [
            'text' => 'Pourquoi l’organigramme (ORBAT) du portail est-il utile au quotidien ?',
            'answers' => [
                ['t' => 'Pour comprendre la structure et les rattachements de l’unité', 'ok' => true],
                ['t' => 'Pour stocker les mots de passe partagés', 'ok' => false],
                ['t' => 'Pour remplacer tous les documents officiels', 'ok' => false],
                ['t' => 'Pour envoyer des messages privés automatiques', 'ok' => false],
            ],
        ],
    ];
}

/**
 * @return list<array{text:string,explain?:string,answers:list<array{t:string,ok:bool}>}>
 */
function training_onboarding_quiz_questions(): array
{
    return [
        [
            'text' => 'Après connexion, quel écran sert principalement de point de départ pour les rappels et raccourcis ?',
            'explain' => 'Le tableau de bord regroupe l’essentiel de l’activité utile pour votre compte.',
            'answers' => [
                ['t' => 'Le tableau de bord', 'ok' => true],
                ['t' => 'Uniquement l’écran de déconnexion', 'ok' => false],
                ['t' => 'La page d’accueil du navigateur, hors portail', 'ok' => false],
                ['t' => 'Un écran réservé aux seuls instructeurs', 'ok' => false],
            ],
        ],
        [
            'text' => 'Où un membre met-il généralement à jour son identité affichée et ses préférences ?',
            'answers' => [
                ['t' => 'Dans le profil / Mon compte', 'ok' => true],
                ['t' => 'Sur un document papier uniquement', 'ok' => false],
                ['t' => 'En changeant le nom de son ordinateur', 'ok' => false],
                ['t' => 'En contactant uniquement un hébergeur extérieur', 'ok' => false],
            ],
        ],
        [
            'text' => 'Comment s’inscrire à une formation publiée dans le catalogue ?',
            'answers' => [
                ['t' => 'Ouvrir la fiche formation et suivre l’inscription prévue (selon les règles de la communauté)', 'ok' => true],
                ['t' => 'En demandant à un ami de s’inscrire à votre place', 'ok' => false],
                ['t' => 'En fermant le navigateur puis en rouvrant n’importe quelle page', 'ok' => false],
                ['t' => 'Ce n’est jamais possible', 'ok' => false],
            ],
        ],
        [
            'text' => 'Sur le forum, quelle attitude est attendue par défaut ?',
            'answers' => [
                ['t' => 'Respecter les canaux, rester courtois et pertinent', 'ok' => true],
                ['t' => 'Publier des informations sensibles hors rubrique autorisée', 'ok' => false],
                ['t' => 'Ignorer les annonces officielles', 'ok' => false],
                ['t' => 'Créer un sujet par message', 'ok' => false],
            ],
        ],
        [
            'text' => 'Pour les événements communautaires (brief, séance…), que faut-il faire en cas d’empêchement ?',
            'answers' => [
                ['t' => 'Prévenir selon les consignes de l’organisation (fil prévu, message au staff, etc.)', 'ok' => true],
                ['t' => 'Ne jamais prévenir', 'ok' => false],
                ['t' => 'Supprimer son compte', 'ok' => false],
                ['t' => 'Modifier l’événement pour tout le monde', 'ok' => false],
            ],
        ],
        [
            'text' => 'Une formation marquée « obligatoire » et « certifiante » signifie en général que :',
            'answers' => [
                ['t' => 'Elle est importante pour le collectif et peut débloquer une attestation après validation complète', 'ok' => true],
                ['t' => 'Elle est optionnelle et sans suivi', 'ok' => false],
                ['t' => 'Elle ne concerne que les administrateurs système', 'ok' => false],
                ['t' => 'Elle remplace le règlement sans validation', 'ok' => false],
            ],
        ],
        [
            'text' => 'Où retrouver en priorité les documents officiels publiés par votre communauté ?',
            'explain' => 'La rubrique Documents centralise les fichiers mis à disposition selon vos droits.',
            'answers' => [
                ['t' => 'Dans la rubrique Documents du portail', 'ok' => true],
                ['t' => 'Uniquement sur une messagerie personnelle externe', 'ok' => false],
                ['t' => 'Dans le carnet d’adresses du téléphone', 'ok' => false],
                ['t' => 'Ils ne sont jamais accessibles en ligne', 'ok' => false],
            ],
        ],
        [
            'text' => 'Avant d’ouvrir un nouveau sujet sur le forum, le meilleur réflexe est de :',
            'answers' => [
                ['t' => 'Vérifier si un fil existant traite déjà le même sujet', 'ok' => true],
                ['t' => 'Toujours créer un nouveau sujet sans regarder', 'ok' => false],
                ['t' => 'Poster le même message dans toutes les catégories', 'ok' => false],
                ['t' => 'Supprimer les anciens sujets', 'ok' => false],
            ],
        ],
        [
            'text' => 'Que représente en général l’organigramme (ORBAT) dans le portail ?',
            'answers' => [
                ['t' => 'Une vue de la structure de l’unité et des rattachements', 'ok' => true],
                ['t' => 'La liste des mots de passe du site', 'ok' => false],
                ['t' => 'Un catalogue de formations uniquement', 'ok' => false],
                ['t' => 'Les messages privés entre membres', 'ok' => false],
            ],
        ],
        [
            'text' => 'Sur un ordinateur partagé (foyer, salle…), que faire en fin de session sur le portail ?',
            'answers' => [
                ['t' => 'Se déconnecter explicitement du compte', 'ok' => true],
                ['t' => 'Fermer seulement l’onglet sans se déconnecter', 'ok' => false],
                ['t' => 'Éteindre l’écran sans se déconnecter', 'ok' => false],
                ['t' => 'Laisser la session ouverte pour le prochain utilisateur', 'ok' => false],
            ],
        ],
        [
            'text' => 'Une note officielle destinée à tous les membres, une fois validée par le staff, doit en principe être retrouvée plutôt…',
            'explain' => 'La rubrique documents (ou équivalent) sert de support aux versions stabilisées ; le forum vit par messages successifs.',
            'answers' => [
                ['t' => 'Dans la rubrique documents du portail, avec le niveau de diffusion prévu', 'ok' => true],
                ['t' => 'Uniquement sous forme de messages successifs sur le forum, sans fichier joint ni fiche dédiée', 'ok' => false],
                ['t' => 'Sur une messagerie personnelle extérieure au portail', 'ok' => false],
                ['t' => 'Dans les préférences du compte, onglet sécurité', 'ok' => false],
            ],
        ],
        [
            'text' => 'Une rubrique décrite dans cette formation ne s’affiche pas pour vous. Quelle démarche est la plus raisonnable en premier ?',
            'explain' => 'Souvent il s’agit de droits, de rôle ou de communauté active ; le staff peut confirmer.',
            'answers' => [
                ['t' => 'Vérifier que vous êtes dans la bonne communauté si vous en avez plusieurs, puis demander au staff si l’accès est normal', 'ok' => true],
                ['t' => 'Publier immédiatement un message d’alerte dans toutes les catégories du forum', 'ok' => false],
                ['t' => 'Supposer que le portail est « cassé » et cesser toute utilisation', 'ok' => false],
                ['t' => 'Demander à un tiers de se connecter avec votre compte pour tester', 'ok' => false],
            ],
        ],
        [
            'text' => 'À quoi sert en général la zone ou le menu « Opérations » lorsqu’il est proposé après connexion ?',
            'explain' => 'Il regroupe les accès utiles au pilotage courant (briefings, structure, outils activés), sans remplacer les documents officiels.',
            'answers' => [
                ['t' => 'À regrouper les accès utiles au suivi opérationnel (briefings, organigramme, outils selon ce qui est activé)', 'ok' => true],
                ['t' => 'À remplacer entièrement le règlement et les ordres écrits', 'ok' => false],
                ['t' => 'À stocker les mots de passe de l’unité pour tout le monde', 'ok' => false],
                ['t' => 'À désactiver automatiquement le forum pour les nouveaux membres', 'ok' => false],
            ],
        ],
    ];
}
