<?php

declare(strict_types=1);

/**
 * Formation LMS obligatoire « guide du portail » : publiée, certifiante, ouverte à tous (policy vide).
 * Idempotent par tenant + slug `parcours-portail`.
 * À chaque exécution des migrations : durées, modules et contenu des leçons « canvas » sont resynchronisés
 * pour ce parcours (mises à jour pédagogiques). Le quiz, les inscriptions et le texte marketing de la fiche
 * formation (titre, descriptions longues) ne sont pas modifiés par cette synchro — seule la durée totale affichée est mise à jour.
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
Ce parcours d’accueil est la base commune pour travailler correctement dans le portail de votre communauté. Il ne remplace ni le règlement intérieur ni les consignes d’emploi de votre unité : il explique où vit l’information sur le site, comment la retrouver sans perdre de temps, et quels gestes minimaux protègent votre compte et celle des autres.

Vous y verrez concrètement le rôle du tableau de bord après connexion, la logique du menu (y compris l’accès aux modules d’opérations lorsqu’ils sont proposés), la mise à jour du profil et des préférences, la différence entre documents officiels et échanges sur le forum, le fonctionnement du catalogue des formations avec obligation et attestation, ainsi que les usages attendus sur le forum et pour les événements.

Le contenu est volontairement dense : chaque diapositive se lit comme un court article. Prenez le temps de parcourir les exemples et les encadrés « à retenir ». Un quiz final contrôle que les réflexes utiles sont acquis ; en cas d’échec, vous pouvez reprendre les modules concernés puis retenter.
TXT;
}

function training_onboarding_course_objectives(): string
{
    return "Situer le portail dans la vie de la communauté et comprendre ce que chaque grande rubrique apporte\n"
        . "Naviguer efficacement (accueil, tableau de bord, menu Opérations, recherche, multi-organisations)\n"
        . "Tenir son compte à jour : profil, préférences, sécurité, adresse de contact\n"
        . "Utiliser le personnel, l’organigramme, les documents et le catalogue des formations selon les droits\n"
        . "Distinguer documents officiels et fils de discussion, participer correctement au forum et aux événements\n"
        . "Comprendre obligation, certificat, quiz, tentatives et reprise de parcours";
}

function training_onboarding_course_short_description(): string
{
    return 'Accueil opérationnel du portail : navigation réelle, compte, contenus, communauté, formations et validation.';
}

/** @return list<array{title:string,subtitle:string,minutes:int,deck:array,lesson_summary:string}> */
function training_onboarding_portal_module_specs(): array
{
    return [
        [
            'title' => 'Vue d’ensemble',
            'subtitle' => 'Pourquoi ce parcours',
            'minutes' => 22,
            'deck' => training_onboarding_deck_overview(),
            'lesson_summary' => 'Rôle du portail, déroulé pédagogique, méthode de travail, sécurité du compte, liens vers l’aide.',
        ],
        [
            'title' => 'Navigation et compte',
            'subtitle' => 'Menus, tableau de bord, profil',
            'minutes' => 24,
            'deck' => training_onboarding_deck_navigation(),
            'lesson_summary' => 'Menu principal, zone Opérations, tableau de bord, compte, préférences, recherche, bonnes pratiques.',
        ],
        [
            'title' => 'Organisation et contenus',
            'subtitle' => 'Personnel, documents, formations',
            'minutes' => 26,
            'deck' => training_onboarding_deck_org(),
            'lesson_summary' => 'Fiche personnelle, organigramme, documents officiels, catalogue LMS, progression et erreurs fréquentes.',
        ],
        [
            'title' => 'Communauté',
            'subtitle' => 'Forum et événements',
            'minutes' => 20,
            'deck' => training_onboarding_deck_community(),
            'lesson_summary' => 'Forum, annonces, événements, pointage, signalements, résumé des bons réflexes.',
        ],
        [
            'title' => 'Validation',
            'subtitle' => 'Quiz final',
            'minutes' => 16,
            'deck' => training_onboarding_deck_validation_intro(),
            'lesson_summary' => 'Quiz, score, tentatives, attestation, reprise de parcours et gestion du stress de l’évaluation.',
        ],
    ];
}

/**
 * Met à jour durées, contenu canvas et durée totale du parcours « parcours-portail » pour un tenant.
 * N’altère pas les quiz (historique des tentatives préservé).
 */
function training_onboarding_refresh_portal_canvas_for_tenant(PDO $pdo, int $tenantId): void
{
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

    $modUpd = $pdo->prepare('UPDATE training_modules SET estimated_minutes = ?, updated_at = ? WHERE id = ?');
    $lesUpd = $pdo->prepare('UPDATE training_lessons SET content = ?, duration_minutes = ?, summary = ?, updated_at = ? WHERE id = ?');
    $lessonIdSt = $pdo->prepare(
        "SELECT id FROM training_lessons WHERE module_id = ? AND lesson_type = 'canvas' ORDER BY position ASC, id ASC LIMIT 1"
    );

    $totalMin = 0;
    foreach ($specs as $idx => $spec) {
        if (!isset($moduleIds[$idx])) {
            break;
        }
        $mid = $moduleIds[$idx];
        $minutes = (int) $spec['minutes'];
        $totalMin += $minutes;
        $modUpd->execute([$minutes, $now, $mid]);

        $lessonIdSt->execute([$mid]);
        $lid = $lessonIdSt->fetchColumn();
        if (!$lid) {
            continue;
        }
        $deckJson = json_encode($spec['deck'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $dur = max(6, (int) ceil($minutes * 0.65));
        $summary = (string) $spec['lesson_summary'];
        if (strlen($summary) > 500) {
            $summary = substr($summary, 0, 497) . '…';
        }
        $lesUpd->execute([$deckJson, $dur, $summary, $now, (int) $lid]);
    }

    $pdo->prepare('UPDATE training_courses SET estimated_minutes = ?, updated_at = ? WHERE id = ?')
        ->execute([$totalMin, $now, $courseId]);
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
                ['label' => 'Durée indicative', 'value' => '~22 min'],
                ['label' => 'Format', 'value' => 'Parcours visuel'],
                ['label' => 'Objectif', 'value' => 'Repères + sécurité'],
            ],
        ],
        'closure' => [
            'title' => 'Synthèse — Vue d’ensemble',
            'seen' => [
                'Le portail centralise documents, forum, formations, événements et dossier personnel.',
                'La lecture attentive vaut mieux qu’un survol rapide des étapes.',
            ],
            'acquired' => [
                'Vous savez distinguer information stabilisée et échanges sur le forum.',
                'Vous connaissez les gestes simples qui protègent votre compte et la communauté.',
            ],
            'nextHint' => 'Poursuivez avec le module sur la navigation quotidienne et les réglages du compte.',
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
                'subtitle' => 'Cinq blocs, puis quiz',
                'body' => <<<'HTML'
<p>Ce parcours comporte cinq modules avant le questionnaire final. L’ordre est logique : d’abord la vision d’ensemble et la sécurité du compte, ensuite la navigation quotidienne, puis les contenus « métier » (personnel, documents, formations), enfin la vie collective (forum, événements) et la manière dont le site valide vos acquis.</p>
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
<li>comprendre ce que signifient pour vous une formation <strong>obligatoire</strong> et une formation <strong>certifiante</strong>, ainsi que le rôle du quiz et de l’attestation.</li>
</ul>
<p>Ce n’est pas une liste à décorer : c’est le socle minimal attendu d’un membre qui utilise le portail au quotidien.</p>
HTML
                ,
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
            'nextHint' => 'Passez au module sur le forum, les événements et les usages collectifs.',
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
        'slides' => [
            [
                'template' => 'title_hero',
                'title' => 'Vie de communauté',
                'subtitle' => 'Coordonner sans encombrer les canaux',
                'body' => '<p>Le <strong>forum</strong> et les <strong>événements</strong> sont les lieux où la communauté vit au quotidien : annonces, questions, briefings, débriefs, organisation logistique. La qualité collective dépend de chacun : un fil lisible vaut mieux que vingt messages redondants ; une inscription honnête vaut mieux qu’une absence non signalée.</p>',
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
        'slides' => [
            [
                'template' => 'title_hero',
                'title' => 'Dernière étape : validation',
                'subtitle' => 'Quiz de fin de parcours',
                'body' => '<p>Le questionnaire porte sur les <strong>idées directrices</strong> du portail : navigation, compte, documents, formations, forum, événements, sécurité. Le <strong>seuil de réussite est de 80&nbsp;%</strong>. Vous disposez de <strong>plusieurs tentatives</strong> dans la limite fixée par la formation.</p><p>Les formulations volontairement longues dans certaines réponses fausses imitent des croyances courantes : lisez jusqu’au bout avant de choisir.</p>',
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
             VALUES (?, ?, ?, ?, NULL, ?, ?, 1, ?, ?)'
        );
        $lesIns = $pdo->prepare(
            'INSERT INTO training_lessons (module_id, title, summary, learning_objectives, instructor_notes, lesson_type, content, external_url, duration_minutes, difficulty, position, is_required)
             VALUES (?, ?, ?, NULL, NULL, ?, ?, NULL, ?, ?, ?, 1)'
        );

        $position = 1;
        $lastModuleId = 0;
        foreach ($specs as $mi => $m) {
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
            $summary = (string) $m['lesson_summary'];
            if (strlen($summary) > 500) {
                $summary = substr($summary, 0, 497) . '…';
            }
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
        }

        if ($lastModuleId > 0) {
            $quizIns = $pdo->prepare(
                'INSERT INTO training_quizzes (module_id, title, description, passing_score, max_attempts, time_limit_minutes, randomize_questions, is_final_exam, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, 0, 1, ?)'
            );
            $quizIns->execute([
                $lastModuleId,
                'Quiz — fonctionnement du portail',
                'Validez vos acquis sur la navigation, le compte, les contenus et la vie de la communauté.',
                80.00,
                5,
                24,
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
