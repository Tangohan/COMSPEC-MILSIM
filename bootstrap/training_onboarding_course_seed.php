<?php

declare(strict_types=1);

/**
 * Formation LMS obligatoire « guide du portail » : publiée, certifiante, ouverte à tous (policy vide).
 * Idempotent par tenant + slug `parcours-portail`.
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

    $tenants = $pdo->query('SELECT id FROM tenants ORDER BY id ASC');
    if (!$tenants) {
        return;
    }
    while ($row = $tenants->fetch(PDO::FETCH_ASSOC)) {
        $tenantId = (int) ($row['id'] ?? 0);
        if ($tenantId < 1) {
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
 * Crée la formation pour un seul tenant (ex. nouvelle communauté). Idempotent.
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
    $authorId = $authorUserId !== null && $authorUserId > 0
        ? $authorUserId
        : training_onboarding_resolve_author_user_id($pdo, $tenantId);
    if ($authorId < 1) {
        return;
    }
    training_onboarding_seed_one_tenant($pdo, $tenantId, $authorId);
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

/** @return array{version:int,modals:list<mixed>,slides:list<array<string,mixed>>} */
function training_onboarding_deck_overview(): array
{
    return [
        'version' => 1,
        'modals' => [],
        'slides' => [
            [
                'template' => 'title_hero',
                'title' => 'Bienvenue sur votre portail',
                'subtitle' => 'Formation d’accueil — même communauté, mêmes outils',
                'body' => '<p>Ce parcours vous présente <strong>l’essentiel</strong> pour vous repérer : tableau de bord, compte, documents, forum et formations. Prenez le temps de parcourir les étapes : elles sont courtes et illustrées.</p>',
            ],
            [
                'template' => 'scorm_sequence',
                'title' => 'Votre parcours en quelques étapes',
                'body' => 'Découverte | Navigation & compte | Organisation | Communauté | Validation & attestation',
            ],
            [
                'template' => 'knowledge_check',
                'title' => 'À la fin de ce module, vous saurez…',
                'body' => "À quoi sert le tableau de bord et où trouver l’aide.\nComment mettre à jour votre profil et vos préférences.\nOù consulter les documents et le catalogue des formations.\nComment participer au forum et aux événements de la communauté.",
            ],
        ],
    ];
}

/** @return array{version:int,modals:list<mixed>,slides:list<array<string,mixed>>} */
function training_onboarding_deck_navigation(): array
{
    return [
        'version' => 1,
        'modals' => [],
        'slides' => [
            [
                'template' => 'title_hero',
                'title' => 'Navigation et compte',
                'subtitle' => 'Tout trouver sans vous perdre',
                'body' => '<p>Le <strong>menu principal</strong> regroupe l’accès aux espaces (accueil, formations, forum, personnel, etc.) selon les droits de votre communauté. Les libellés sont volontairement clairs : suivez-les comme dans une application civile.</p>',
            ],
            [
                'template' => 'split_text_image',
                'title' => 'Tableau de bord & raccourcis',
                'body' => '<p>Le <strong>tableau de bord</strong> résume l’activité utile : prochains événements, rappels, liens vers vos formations. Utilisez-le comme point de départ après connexion.</p><p>Le <strong>profil</strong> (ou « Mon compte ») permet de mettre à jour votre identité affichée, vos préférences et la sécurité du compte (mot de passe, vérification de l’adresse e-mail).</p>',
                'imageUrl' => '',
                'imageCaption' => '',
            ],
            [
                'template' => 'knowledge_check',
                'title' => 'Bon réflexes',
                'body' => "Vérifiez régulièrement vos préférences de notification pour ne rien manquer d’important.\nEn cas de doute sur un libellé, ouvrez la section concernée : le site évite les termes techniques inutiles.\nDéconnectez-vous sur un poste partagé après utilisation.",
            ],
        ],
    ];
}

/** @return array{version:int,modals:list<mixed>,slides:list<array<string,mixed>>} */
function training_onboarding_deck_org(): array
{
    return [
        'version' => 1,
        'modals' => [],
        'slides' => [
            [
                'template' => 'title_hero',
                'title' => 'Organisation & contenus',
                'subtitle' => 'Personnel, structure, documents',
                'body' => '<p>L’espace <strong>personnel</strong> centralise votre dossier : affectation, ORBAT ou fiche selon ce que votre staff a activé. Les <strong>documents</strong> publiés par l’unité (doctrine, consignes, médias) sont accessibles depuis la rubrique prévue — respectez le niveau de diffusion indiqué.</p>',
            ],
            [
                'template' => 'quote',
                'title' => '',
                'body' => 'Les formations du catalogue sont des parcours complets : inscrivez-vous, suivez les leçons dans l’ordre, validez les étapes — l’attestation n’est délivrée que lorsque le parcours est réellement terminé.',
            ],
            [
                'template' => 'knowledge_check',
                'title' => 'Ce qui compte ici',
                'body' => "Les contenus sensibles restent dans les espaces prévus — ne les recopiez pas sur des canaux non autorisés.\nUne formation peut être obligatoire : elle apparaît comme telle dans votre liste.\nLe staff peut vous assigner un parcours : vous le voyez dans « Mes formations ».",
            ],
        ],
    ];
}

/** @return array{version:int,modals:list<mixed>,slides:list<array<string,mixed>>} */
function training_onboarding_deck_community(): array
{
    return [
        'version' => 1,
        'modals' => [],
        'slides' => [
            [
                'template' => 'title_hero',
                'title' => 'Communauté',
                'subtitle' => 'Forum et événements',
                'body' => '<p>Le <strong>forum</strong> sert aux annonces, questions et retours d’expérience. Respectez le règlement de la communauté : rester courtois, rester dans le sujet des canaux. Les <strong>événements</strong> (briefs, entraînements, soirées) peuvent être listés avec date et lieu : inscrivez-vous ou déclinez selon les consignes.</p>',
            ],
            [
                'template' => 'scorm_sequence',
                'title' => 'En résumé',
                'body' => 'Lire les annonces | Répondre dans le bon fil | S’inscrire aux créneaux | Prévenir en cas d’empêchement',
            ],
            [
                'template' => 'knowledge_check',
                'title' => 'Participation utile',
                'body' => "Utilisez les sujets existants avant d’en créer un nouveau — évite les doublons.\nSignalez un problème au staff via les canaux prévus plutôt qu’en message privé non sollicité.\nLes avis sur les formations aident tout le monde : soyez précis et factuel.",
            ],
        ],
    ];
}

/** @return array{version:int,modals:list<mixed>,slides:list<array<string,mixed>>} */
function training_onboarding_deck_validation_intro(): array
{
    return [
        'version' => 1,
        'modals' => [],
        'slides' => [
            [
                'template' => 'title_hero',
                'title' => 'Dernière étape : validation',
                'subtitle' => 'Quiz de fin de parcours',
                'body' => '<p>Quelques questions à choix multiples vérifient que vous avez bien les repères sur le fonctionnement du portail. <strong>Seuil de réussite : 80 %</strong>. Vous pouvez réessayer dans la limite des tentatives autorisées. Ensuite, si votre formation est certifiante, votre attestation sera disponible depuis la fiche formation.</p>',
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
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $objectives = "Repérer les zones clés du portail après connexion\nMettre à jour son profil et ses préférences en autonomie\nAccéder aux documents et au catalogue des formations\nParticiper correctement au forum et aux événements\nValider le parcours et obtenir l’attestation si applicable";

    $desc = <<<'TXT'
Ce parcours d’accueil présente le fonctionnement du portail de votre communauté : navigation, compte, documents, formations, forum et événements. Il est conçu pour être suivi une fois par chaque membre, sans prérequis technique. Les étapes sont présentées sous forme de parcours visuel puis d’un court quiz de validation.
TXT;

    $short = 'Parcours d’accueil obligatoire : navigation, compte, contenus, communauté, validation.';

    $uuid = training_onboarding_uuid_v4();
    $now = date('Y-m-d H:i:s');

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
            $short,
            $desc,
            $objectives,
            $themeJson,
            'Portail',
            'initiation',
            'fr',
            55,
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

        $modules = [
            ['title' => 'Vue d’ensemble', 'subtitle' => 'Pourquoi ce parcours', 'minutes' => 10, 'deck' => training_onboarding_deck_overview()],
            ['title' => 'Navigation et compte', 'subtitle' => 'Menus, tableau de bord, profil', 'minutes' => 12, 'deck' => training_onboarding_deck_navigation()],
            ['title' => 'Organisation et contenus', 'subtitle' => 'Personnel, documents, formations', 'minutes' => 12, 'deck' => training_onboarding_deck_org()],
            ['title' => 'Communauté', 'subtitle' => 'Forum et événements', 'minutes' => 10, 'deck' => training_onboarding_deck_community()],
            ['title' => 'Validation', 'subtitle' => 'Quiz final', 'minutes' => 8, 'deck' => training_onboarding_deck_validation_intro()],
        ];

        $modIns = $pdo->prepare(
            'INSERT INTO training_modules (course_id, title, description, subtitle, learning_objectives, estimated_minutes, position, is_required, created_at, updated_at)
             VALUES (?, ?, ?, ?, NULL, ?, ?, 1, ?, ?)'
        );
        $lesIns = $pdo->prepare(
            'INSERT INTO training_lessons (module_id, title, summary, learning_objectives, instructor_notes, lesson_type, content, external_url, duration_minutes, difficulty, position, is_required)
             VALUES (?, ?, ?, NULL, NULL, ?, ?, NULL, ?, ?, ?, 1)'
        );

        $position = 1;
        $lastModuleId = 0;
        foreach ($modules as $mi => $m) {
            $title = $m['title'];
            $sub = $m['subtitle'];
            $minutes = (int) $m['minutes'];
            $descMod = 'Module ' . ($mi + 1) . ' — ' . $sub;
            $modIns->execute([
                $courseId,
                $title,
                $descMod,
                $sub,
                $minutes,
                $position,
                $now,
                $now,
            ]);
            $moduleId = (int) $pdo->lastInsertId();
            $position++;
            $lastModuleId = $moduleId;

            $deckJson = json_encode($m['deck'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $lesIns->execute([
                $moduleId,
                $title . ' — parcours visuel',
                'Faites défiler les étapes, puis passez à la suite du parcours.',
                'canvas',
                $deckJson,
                max(5, (int) ceil($minutes * 0.6)),
                'initiation',
                1,
            ]);
        }

        // Quiz final (dernier module = Validation)
        if ($lastModuleId > 0) {
            $quizIns = $pdo->prepare(
                'INSERT INTO training_quizzes (module_id, title, description, passing_score, max_attempts, time_limit_minutes, randomize_questions, is_final_exam, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, 0, 1, ?)'
            );
            $quizIns->execute([
                $lastModuleId,
                'Quiz — fonctionnement du portail',
                'Validez vos acquis sur la navigation, le compte et la vie de la communauté.',
                80.00,
                5,
                15,
                $now,
            ]);
            $quizId = (int) $pdo->lastInsertId();

            $questions = training_onboarding_quiz_questions();
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
                $apos = 1;
                foreach ($q['answers'] as $ans) {
                    $aIns->execute([$qid, $ans['t'], $ans['ok'] ? 1 : 0, $apos]);
                    $apos++;
                }
                $qpos++;
            }
        }

        $pdo->commit();
        echo "  training_onboarding_course : tenant {$tenantId} — formation « Parcours portail » créée (course_id={$courseId}).\n";
    } catch (\Throwable $e) {
        $pdo->rollBack();
        echo '  [ATTENTION] training_onboarding_course : ' . $e->getMessage() . "\n";
    }
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
    ];
}
