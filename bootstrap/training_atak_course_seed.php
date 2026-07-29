<?php

declare(strict_types=1);

/**
 * Formation LMS « ATAK Athena et Overwatch » : portail (web) + terminal in-game.
 * Idempotent par tenant + slug `parcours-atak-web-jeu`.
 *
 * @param PDO $pdo Connexion SQL (comme run-migrations.php)
 */

function training_atak_course_thumbnail_path(): string
{
    return 'assets/images/armee-de-terre-recrute-specialiste-systeme-information.jpg';
}

function training_atak_course_banner_path(): string
{
    return 'assets/images/des-soldats-francais-de-garde-le-26-mars-2019-pendant-une-pause-avant-le-lancement-d-une-operation-de-barkhane-dans-la-region-malienne-de-gourma_6186934.jpg';
}

function training_atak_sync_course_cover(PDO $pdo, int $tenantId, string $slug): void
{
    $st = $pdo->prepare('UPDATE training_courses SET thumbnail_path = ?, banner_path = ? WHERE tenant_id = ? AND slug = ? LIMIT 1');
    $st->execute([
        training_atak_course_thumbnail_path(),
        training_atak_course_banner_path(),
        $tenantId,
        $slug,
    ]);
}

function run_training_atak_course_seed(PDO $pdo): void
{
    $chk = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'training_courses' LIMIT 1");
    if (!$chk || !$chk->fetch()) {
        echo "[ATTENTION] training_atak_course : table training_courses absente — ignoré.\n";

        return;
    }

    $slug = 'parcours-atak-web-jeu';
    $tenants = $pdo->query('SELECT id FROM tenants ORDER BY id ASC');
    if (!$tenants) {
        return;
    }
    while ($row = $tenants->fetch(PDO::FETCH_ASSOC)) {
        $tenantId = (int) ($row['id'] ?? 0);
        if ($tenantId < 1) {
            continue;
        }
        if (training_atak_course_exists($pdo, $tenantId, $slug)) {
            training_atak_sync_course_cover($pdo, $tenantId, $slug);

            continue;
        }
        $authorId = training_atak_resolve_author_user_id($pdo, $tenantId);
        if ($authorId < 1) {
            echo "  [ATTENTION] training_atak_course : tenant {$tenantId} — aucun utilisateur actif, ignoré.\n";

            continue;
        }
        training_atak_seed_one_tenant($pdo, $tenantId, $authorId);
    }
}

function run_training_atak_course_for_tenant(PDO $pdo, int $tenantId, ?int $authorUserId = null): void
{
    $chk = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'training_courses' LIMIT 1");
    if (!$chk || !$chk->fetch()) {
        return;
    }
    if ($tenantId < 1) {
        return;
    }
    $slug = 'parcours-atak-web-jeu';
    if (training_atak_course_exists($pdo, $tenantId, $slug)) {
        training_atak_sync_course_cover($pdo, $tenantId, $slug);

        return;
    }
    $authorId = $authorUserId !== null && $authorUserId > 0
        ? $authorUserId
        : training_atak_resolve_author_user_id($pdo, $tenantId);
    if ($authorId < 1) {
        return;
    }
    training_atak_seed_one_tenant($pdo, $tenantId, $authorId);
}

function training_atak_resolve_author_user_id(PDO $pdo, int $tenantId): int
{
    $st = $pdo->prepare('SELECT id FROM users WHERE tenant_id = ? AND status = ? ORDER BY id ASC LIMIT 1');
    $st->execute([$tenantId, 'active']);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    return $row ? (int) $row['id'] : 0;
}

function training_atak_course_exists(PDO $pdo, int $tenantId, string $slug): bool
{
    $st = $pdo->prepare('SELECT 1 FROM training_courses WHERE tenant_id = ? AND slug = ? LIMIT 1');
    $st->execute([$tenantId, $slug]);

    return (bool) $st->fetchColumn();
}

function training_atak_uuid_v4(): string
{
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

    return vsprintf('%s%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

/** @param list<string> $lines */
function training_atak_module_objectives_json(array $lines): string
{
    $clean = array_values(array_filter(array_map(static fn (string $x): string => trim($x), $lines), static fn (string $x): bool => $x !== ''));

    return json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';
}

/**
 * @param list<array{text:string,explain?:string,answers:list<array{t:string,ok:bool}>}> $questions
 */
function training_atak_seed_quiz_questions_for_module(PDO $pdo, int $quizId, array $questions, string $now): void
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

function training_atak_course_description(): string
{
    return <<<'TXT'
Ce parcours forme opérateurs et encadrement à l’écosystème ATAK de votre communauté : le portail Athena (carte Tacmap, poste de commandement Overwatch, journal de liaison, téléphone, passerelle, cycle de mission) et le terminal in-game COMSPEC Overwatch dans Arma 3.

Il décrit qui lit quoi, quand une donnée remonte du terrain vers le TOC, comment une mauvaise liaison ou un marqueur ambigu dégrade la conduite d’opération, et quelles procédures de secours (PACE) appliquer si le numérique est coupé. Les écrans et libellés repris sont ceux du site et du pack Overwatch.

L’attestation valide la lecture du parcours sur le portail. Elle ne remplace pas une qualification opérationnelle décidée par votre unité, ni le guide détaillé Overwatch (menu ATAK du portail) pour le dépannage fin.
TXT;
}

function training_atak_course_objectives(): string
{
    return "Distinguer ATAK web (Athena / Tacmap / TOC) et Overwatch in-game\n"
        . "Préparer compte, pack et première liaison jusqu’au badge « En liaison »\n"
        . "Utiliser Tacmap, messagerie TOC, journal de liaison et téléphone côté portail\n"
        . "Naviguer dans le hub Overwatch (messagerie, ordres, briefing, profil terminal)\n"
        . "Poser des marqueurs et rapports utiles au poste de commandement\n"
        . "Reconnaître les états de réalisme liaison et appliquer une procédure de secours\n"
        . "Appliquer les gestes chef de mission (checklist, zones, passerelle, cycle / AAR) sans exposer de secrets\n"
        . "Réussir le questionnaire final sur les usages attendus web et jeu";
}

function training_atak_course_short_description(): string
{
    return 'ATAK sur Athena et Overwatch en Arma : liaison, Tacmap, hub, marqueurs, rapports, réalisme et encadrement.';
}

/** @return list<array<string, mixed>> */
function training_atak_module_specs(): array
{
    return [
        [
            'title' => 'Cadre ATAK — portail et jeu',
            'subtitle' => 'Qui utilise quoi, et pourquoi',
            'minutes' => 20,
            'module_description' => 'Pose le vocabulaire métier et la répartition des rôles : opérateur terrain, chef d’élément, TOC / poste de commandement sur Athena, chef de mission. Sans ce cadre, Tacmap et le hub Overwatch restent des écrans déconnectés.',
            'module_learning_objectives' => [
                'Nommer les surfaces Athena (Tacmap, Overwatch C2, journal, première liaison) et le pack Overwatch.',
                'Identifier le public de chaque surface (opérateur, TOC, encadrement).',
                'Expliquer ce qui circule conceptuellement entre le jeu et le portail (positions, marqueurs, messages, rapports).',
            ],
            'deck' => training_atak_deck_cadre(),
            'lesson_summary' => 'Écosystème ATAK : Athena web, Overwatch in-game, acteurs et flux métier.',
            'recap_html' => '<p><strong>À retenir</strong> : Athena est le poste de commandement web ; Overwatch est le terminal terrain dans Arma. Une donnée utile répond à <em>quoi / où / pour qui</em>. Un indicatif clair et une liaison stable conditionnent toute la suite.</p>',
        ],
        [
            'title' => 'Première liaison et pack Overwatch',
            'subtitle' => 'Compte, installation, badge En liaison',
            'minutes' => 28,
            'module_description' => 'Sans compte Athena prêt, pack correctement chargé et handshake réussi, rien n’apparaît sur Tacmap. Ce module enchaîne la page Première liaison, le téléchargement du pack et les vérifications in-game jusqu’au badge « En liaison ».',
            'module_learning_objectives' => [
                'Compléter le compte (Steam, nom / indicatif) via Première liaison.',
                'Installer le pack avec CBA avant Overwatch et la bibliothèque native à la racine.',
                'Obtenir le badge En liaison après spawn et choix d’indicatif.',
            ],
            'deck' => training_atak_deck_premiere_liaison(),
            'lesson_summary' => 'Parcours Première liaison, installation pack, handshake et indicatif.',
            'recap_html' => '<p><strong>À retenir</strong> : compte Athena + Steam + indicatif + pack à jour = prérequis. CBA avant Overwatch. Attendre ~30 s après spawn, puis vérifier le hub (<kbd>Ctrl</kbd>+<kbd>Shift</kbd>+<kbd>K</kbd>). Redémarrer le lanceur après une mise à jour du pack.</p>',
        ],
        [
            'title' => 'ATAK web — Tacmap et TOC',
            'subtitle' => 'Ce que fait le poste de commandement sur Athena',
            'minutes' => 32,
            'module_description' => 'Côté portail, le TOC lit la situation : carte Tacmap, console Overwatch, journal de liaison, téléphone ATAK, slides de briefing, opérateurs et certificats. Ce module décrit les gestes métier sans configuration technique sensible.',
            'module_learning_objectives' => [
                'Ouvrir Tacmap et distinguer positions, marqueurs, messagerie et alertes utiles au TOC.',
                'Situer journal de liaison, téléphone et briefing slides dans le cycle d’une OP.',
                'Savoir quand utiliser la passerelle inter-équipes (consentement bilatéral) et ce qu’elle engage.',
            ],
            'deck' => training_atak_deck_web_toc(),
            'lesson_summary' => 'Tacmap, Overwatch C2, journal, téléphone, briefing et passerelle côté Athena.',
            'recap_html' => '<p><strong>À retenir</strong> : le TOC lit et décide ; il ne « bricole » pas la liaison opérateur. La passerelle partage des positions (et éventuellement des marqueurs) seulement après validation des deux unités. Les clés d’accès et réglages sensibles restent du canal staff.</p>',
        ],
        [
            'title' => 'Hub Overwatch en mission',
            'subtitle' => 'Messagerie, ordres, briefing, terminal',
            'minutes' => 30,
            'module_description' => 'En jeu, le hub Overwatch concentre l’état de liaison, la messagerie, les ordres et le briefing reçus depuis Athena, les demandes d’appui et le profil terminal. Savoir l’ouvrir vite évite d’immobiliser tout l’élément.',
            'module_learning_objectives' => [
                'Mémoriser les raccourcis hub et messagerie.',
                'Lire un ordre ou une diapositive de briefing sans perdre le rythme tactique.',
                'Consulter le profil terminal (certificat, réalisme) et signaler un statut anormal.',
            ],
            'deck' => training_atak_deck_hub(),
            'lesson_summary' => 'Anatomie du hub Overwatch, messagerie croisée Athena ↔ jeu, profil terminal.',
            'recap_html' => '<p><strong>À retenir</strong> : hub = radio numérique de l’élément. Messages courts, indicatif dans le texte de test, lecture des points clés d’un ordre en moins d’une minute. Le profil terminal explique souvent un blocage « certificat » ou réalisme avant d’accuser le réseau.</p>',
        ],
        [
            'title' => 'Carte partagée, marqueurs et rapports',
            'subtitle' => 'Doctrine terrain → TOC',
            'minutes' => 35,
            'module_description' => 'Marqueurs Arma, POI ACE et rapports (CONTACT, SALUTE, SPOTREP, SITREP, MEDEVAC, QRF) alimentent Tacmap et le mur opérationnel. Un libellé ambigu force des allers-retours radio ; un rapport structuré accélère la décision du TOC.',
            'module_learning_objectives' => [
                'Poser un marqueur nommé explicitement et vérifier sa remontée sur Tacmap.',
                'Structurer un SPOTREP / CONTACT et une demande MEDEVAC minimale.',
                'Appliquer la procédure PACE si la liaison est dégradée (position seule + radio).',
            ],
            'deck' => training_atak_deck_carte_rapports(),
            'lesson_summary' => 'Marqueurs, POI, sync Tacmap, rapports tactiques et demandes d’appui.',
            'recap_html' => '<p><strong>À retenir</strong> : quoi / où / pour qui. Pas de marqueur en (0,0). SALUTE pour structurer. MEDEVAC : position, effectifs blessés, sécurité LZ, indicatif. Si écran endommagé : position seule + détail en radio.</p>',
        ],
        [
            'title' => 'Réalisme, encadrement et clôture d’OP',
            'subtitle' => 'Zones, chef de mission, cycle et AAR',
            'minutes' => 38,
            'module_description' => 'Le réalisme liaison (zones, brouilleurs, dommages terminal) change la réaction attendue. Le chef de mission aligne mods, carte, briefing et dry-run. Après l’OP, le cycle de mission et l’AAR sur Athena ferment la boucle. Ce module s’adresse aux opérateurs avancés et à l’encadrement.',
            'module_learning_objectives' => [
                'Reconnaître les overlays (liaison perdue, zone, écran endommagé, ATAK éteint) et l’action associée.',
                'Parcourir la checklist pré-OP chef de mission sans exposer de secrets.',
                'Relier cycle de mission / AAR portail aux remontées ATAK de l’activité.',
            ],
            'deck' => training_atak_deck_realisme_encadrement(),
            'lesson_summary' => 'Pipeline réalisme, checklist chef de mission, passerelle, cycle mission et AAR.',
            'recap_html' => '<p><strong>À retenir</strong> : un overlay n’est pas qu’un décor — la réaction tactique change. L’encadrement teste liaison + marqueur + rapport avant ouverture publique. La passerelle et les certificats engagent la communauté : les valider à deux, les révoquer après l’exercice. L’AAR s’appuie sur ce qui a réellement remonté.</p>',
        ],
    ];
}

function training_atak_deck_cadre(): array
{
    return [
        'version' => 2,
        'opening' => [
            'eyebrow' => 'Module 1',
            'title' => '',
            'lead' => 'Comprendre l’écosystème ATAK : Athena côté web, Overwatch côté Arma, et qui lit chaque surface.',
            'stats' => [
                ['label' => 'Durée indicative', 'value' => '~20 min'],
                ['label' => 'Public', 'value' => 'Opérateurs & TOC'],
                ['label' => 'Objectif', 'value' => 'Cadre commun'],
            ],
        ],
        'closure' => [
            'title' => 'Synthèse — Cadre ATAK',
            'seen' => [
                'Athena concentre Tacmap, Overwatch C2, journal et outils TOC.',
                'Overwatch est le terminal terrain relié à la communauté.',
                'Indicatif, liaison et données utiles conditionnent la conduite d’OP.',
            ],
            'acquired' => [
                'Vous savez nommer les surfaces web et jeu.',
                'Vous savez à qui s’adresse chaque outil.',
            ],
            'nextHint' => 'Passez au module « Première liaison et pack Overwatch ».',
        ],
        'slides' => [
            [
                'template' => 'title_hero',
                'title' => 'Deux faces du même système',
                'subtitle' => 'Portail Athena · pack COMSPEC Overwatch',
                'body' => '<p><strong>ATAK</strong> désigne ici l’écosystème de situation tactique de votre communauté : carte partagée, messagerie, rapports et terminal. Sur le <strong>portail Athena</strong>, le TOC ouvre Tacmap et la console Overwatch. En <strong>Arma 3</strong>, l’opérateur utilise le pack <strong>COMSPEC Overwatch</strong> (<code>@COMSPECOverwatch</code>) pour remonter sa présence et dialoguer avec le poste de commandement.</p>',
                'contextKicker' => 'Module 1 · Cadre',
                'surface' => 'elevated',
                'metric' => ['label' => 'Principe', 'value' => 'Web = TOC · Jeu = terrain'],
                'cards' => [
                    ['label' => 'Athena', 'body' => 'Tacmap, journal de liaison, téléphone, passerelle, cycle de mission.'],
                    ['label' => 'Overwatch', 'body' => 'Hub in-game, marqueurs, rapports, réalisme terminal.'],
                    ['label' => 'Liaison', 'body' => 'Compte Athena + pack + indicatif = badge En liaison.'],
                ],
            ],
            [
                'template' => 'reading_article',
                'title' => 'Acteurs et responsabilités',
                'subtitle' => 'Qui lit, qui écrit, qui décide',
                'body' => <<<'HTML'
<p>L’<strong>opérateur</strong> maintient sa liaison, choisit un indicatif stable, pose des marqueurs et envoie des rapports utiles. Le <strong>chef d’élément</strong> lit les ordres Athena et oriente le rythme. Le <strong>TOC</strong> (poste de commandement sur Athena) consolide la carte, la messagerie et les alertes (dont sanitaires remontées automatiquement selon le setup médical). Le <strong>chef de mission</strong> (OP / Zeus / admin liaison) aligne versions de pack, carte Tacmap, zones de réalisme et briefing avant l’ouverture serveur.</p>
<div class="lms-reading-callout lms-reading-callout--info"><p><strong>Conséquence</strong> : un opérateur hors liaison n’apparaît pas correctement sur Tacmap — le TOC décide alors à l’aveugle ou force un retour radio. Un marqueur nommé « test » pollue la carte pour toute l’OP.</p></div>
HTML
                ,
            ],
            [
                'template' => 'scenario_decision',
                'title' => 'Où vérifier si Alpha-1-1 est bien vu du TOC ?',
                'context' => 'Début d’OP, le chef d’élément doute de la liaison d’un opérateur.',
                'situation' => '<p>L’opérateur affirme « être connecté ». Le TOC n’a pas encore confirmé sa pastille sur la carte.</p>',
                'options' => [
                    ['id' => 'a', 'text' => 'Demander à l’opérateur d’ouvrir le hub et confirmer le badge En liaison, puis croiser avec Tacmap côté Athena.'],
                    ['id' => 'b', 'text' => 'Lui demander de coller une clé technique dans le chat public.'],
                    ['id' => 'c', 'text' => 'Ignorer et attendre la fin de mission pour voir.'],
                    ['id' => 'd', 'text' => 'Lui faire désinstaller CBA pour « forcer » Overwatch.'],
                ],
                'correctOptionId' => 'a',
                'explanation' => '<p>Le badge hub et Tacmap sont les deux faces du même contrôle. Exposer des clés ou casser l’ordre des mods aggrave le problème.</p>',
            ],
            [
                'template' => 'knowledge_check',
                'title' => 'Repères du cadre',
                'body' => "Athena = surfaces TOC (Tacmap, journal, téléphone, passerelle).\nOverwatch = terminal terrain dans Arma.\nL’indicatif apparaît sur la carte partagée.\nUne mauvaise liaison ou un marqueur ambigu dégrade la décision du TOC.",
            ],
        ],
    ];
}

function training_atak_deck_premiere_liaison(): array
{
    return [
        'version' => 2,
        'opening' => [
            'eyebrow' => 'Module 2',
            'title' => '',
            'lead' => 'Mettre en service compte, pack et liaison jusqu’au badge En liaison.',
            'stats' => [
                ['label' => 'Durée indicative', 'value' => '~28 min'],
                ['label' => 'Public', 'value' => 'Tout opérateur'],
                ['label' => 'Objectif', 'value' => 'Mise en service'],
            ],
        ],
        'closure' => [
            'title' => 'Synthèse — Première liaison',
            'seen' => [
                'La page Première liaison guide compte → pack → liaison → contrôle carte.',
                'CBA doit charger avant Overwatch ; la bibliothèque native est à la racine du pack.',
                'Après spawn, attendre le handshake puis vérifier le hub.',
            ],
            'acquired' => [
                'Vous savez préparer votre compte Athena pour la carte.',
                'Vous savez installer et vérifier le pack avant une OP.',
            ],
            'nextHint' => 'Passez au module « ATAK web — Tacmap et TOC ».',
        ],
        'slides' => [
            [
                'template' => 'title_hero',
                'title' => 'Première liaison sur Athena',
                'subtitle' => 'Un parcours à faire au calme, avant l’activité',
                'body' => '<p>La page <strong>Première liaison</strong> (menu ATAK) enchaîne : compte Athena prêt (Steam + nom / indicatif), téléchargement du pack depuis la page mod, liaison jeu, puis contrôle de présence sur la carte. C’est le chemin officiel — préférable à une configuration improvisée la veille d’OP.</p>',
                'contextKicker' => 'Module 2 · Mise en service',
                'surface' => 'elevated',
                'metric' => ['label' => 'Ordre', 'value' => 'Compte → pack → liaison → carte'],
                'cards' => [
                    ['label' => 'Compte', 'body' => 'Steam renseigné, identité / indicatif stables.'],
                    ['label' => 'Pack', 'body' => 'Version affichée sur la page ATAK mod, CBA puis Overwatch.'],
                    ['label' => 'Contrôle', 'body' => 'Badge En liaison + pastille sur Tacmap.'],
                ],
            ],
            [
                'template' => 'reading_article',
                'title' => 'Installation du pack — points de contrôle',
                'subtitle' => 'Éviter les échecs silencieux',
                'body' => <<<'HTML'
<p>Téléchargez le pack depuis la page <strong>ATAK mod</strong> du portail et notez la version. Extrayaez dans le dossier mods. Vérifiez la présence de la bibliothèque native à la racine de <strong>@COMSPECOverwatch</strong>. Au lanceur Arma : activez <strong>CBA_A3</strong> puis <strong>@COMSPECOverwatch</strong>. Si CBA n’est pas en premier, menus ou scripts peuvent échouer sans message clair.</p>
<div class="lms-reading-callout lms-reading-callout--warn"><p><strong>Piège fréquent</strong> : oublier de redémarrer le lanceur après une mise à jour — le hub affiche une version, le serveur en exige une autre.</p></div>
HTML
                ,
            ],
            [
                'template' => 'scenario_decision',
                'title' => 'Badge Hors liaison après spawn',
                'context' => 'Vous êtes sur le serveur de la communauté avec le pack chargé.',
                'situation' => '<p>Après plus d’une minute, le hub affiche encore Hors liaison. Votre Steam est renseigné sur Athena.</p>',
                'options' => [
                    ['id' => 'a', 'text' => 'Vérifier indicatif choisi, ordre des mods, version du pack, puis refaire le parcours Première liaison / assistant setup avant de relancer.'],
                    ['id' => 'b', 'text' => 'Publier la clé communauté de l’unité sur Discord ouvert « pour aider ».'],
                    ['id' => 'c', 'text' => 'Désactiver CBA et réessayer.'],
                    ['id' => 'd', 'text' => 'Poser des marqueurs en 0,0 pour « forcer » la sync.'],
                ],
                'correctOptionId' => 'a',
                'explanation' => '<p>Le diagnostic suit le parcours officiel et le guide. Exposer une clé ou casser l’ordre des mods crée des risques et des incohérences.</p>',
            ],
            [
                'template' => 'fill_blanks',
                'title' => 'Vocabulaire liaison',
                'contextKicker' => 'Termes',
                'metric' => ['label' => 'Consigne', 'value' => 'Compléter'],
                'body' => '<p>Le pack in-game s’appelle COMSPEC [[Overwatch]].</p><p>L’état attendu une fois relié s’affiche comme badge [[En liaison]].</p>',
            ],
        ],
    ];
}

function training_atak_deck_web_toc(): array
{
    return [
        'version' => 2,
        'opening' => [
            'eyebrow' => 'Module 3',
            'title' => '',
            'lead' => 'Utiliser les surfaces Athena du poste de commandement : Tacmap, journal, téléphone, briefing, passerelle.',
            'stats' => [
                ['label' => 'Durée indicative', 'value' => '~32 min'],
                ['label' => 'Public', 'value' => 'TOC & encadrement'],
                ['label' => 'Objectif', 'value' => 'Pilotage web'],
            ],
        ],
        'closure' => [
            'title' => 'Synthèse — ATAK web',
            'seen' => [
                'Tacmap et Overwatch C2 sont les vues situation du TOC.',
                'Le journal de liaison trace connexions et échanges utiles au débrief.',
                'Téléphone et slides portent le briefing hors PC fixe ; la passerelle engage deux unités.',
            ],
            'acquired' => [
                'Vous savez où lire la situation côté Athena.',
                'Vous savez ce que la passerelle partage et sous quelles conditions.',
            ],
            'nextHint' => 'Passez au module « Hub Overwatch en mission ».',
        ],
        'slides' => [
            [
                'template' => 'title_hero',
                'title' => 'Tacmap et console Overwatch',
                'subtitle' => 'Lire la situation sans noyer l’opérateur',
                'body' => '<p>Sur Athena, <strong>Tacmap</strong> affiche positions (suivi terrain), marqueurs, couches et messagerie associée à la carte. La console <strong>Overwatch</strong> élargit la vue commandement (piliers C2, médical, IFF selon modules activés). Le TOC filtre le bruit : un message long ou dix marqueurs « test » ralentissent la décision plus qu’ils n’aident.</p>',
                'contextKicker' => 'Module 3 · TOC',
                'surface' => 'elevated',
                'metric' => ['label' => 'Rôle TOC', 'value' => 'Lire · décider · ordonner'],
                'cards' => [
                    ['label' => 'Tacmap', 'body' => 'Carte partagée, BFT, marqueurs, pings.'],
                    ['label' => 'Overwatch C2', 'body' => 'Vue commandement élargie côté portail.'],
                    ['label' => 'Journal', 'body' => 'Historique de liaison, indicatifs, actions.'],
                ],
            ],
            [
                'template' => 'reading_article',
                'title' => 'Téléphone, briefing et opérateurs',
                'subtitle' => 'Hors du PC de salle d’OP',
                'body' => <<<'HTML'
<p>Le flux <strong>téléphone ATAK</strong> (connexion guidée) permet de suivre briefing et carte sur mobile. Les <strong>slides de briefing</strong> préparées en back-office sont visibles in-game et sur téléphone : un seul jeu de diapos pour toute l’unité. Les fiches <strong>opérateurs / terminaux / certificats</strong> aident l’encadrement à voir qui est en liaison et l’état du parc matériel (actif, en attente, expiré) lorsque le réalisme certificat est actif.</p>
<p>Le <strong>cycle de mission</strong> (briefing → exécution → après-action) et les comptes rendus / AAR ferment la boucle après l’OP : ils s’appuient sur ce qui a remonté (marqueurs, rapports, alertes), pas sur une mémoire orale seule.</p>
HTML
                ,
            ],
            [
                'template' => 'scenario_decision',
                'title' => 'Passerelle inter-équipes',
                'context' => 'Exercice week-end avec une autre communauté fédérée.',
                'situation' => '<p>Vous devez partager temporairement le suivi terrain. Un code a été généré côté Athena.</p>',
                'options' => [
                    ['id' => 'a', 'text' => 'Échanger le code par canal staff, faire valider les deux côtés, limiter le partage au nécessaire, révoquer après l’exercice.'],
                    ['id' => 'b', 'text' => 'Publier le code sur un salon public pour « aller plus vite ».'],
                    ['id' => 'c', 'text' => 'Activer le partage sans validation de l’autre unité.'],
                    ['id' => 'd', 'text' => 'Laisser la passerelle active indéfiniment « au cas où ».'],
                ],
                'correctOptionId' => 'a',
                'explanation' => '<p>La passerelle exige un consentement bilatéral. Un code exposé ou une session oubliée élargit la carte au-delà de l’intention de l’exercice.</p>',
            ],
            [
                'template' => 'knowledge_check',
                'title' => 'Checklist TOC Athena',
                'body' => "Ouvrir Tacmap sur la bonne communauté / carte.\nCroiser journal de liaison et pastilles opérateurs.\nPréparer slides de briefing avant l’OP.\nPasserelle : validation bilatérale, partage minimal, révocation après exercice.\nNe jamais exposer clés d’accès ou réglages sensibles hors canal staff.",
            ],
        ],
    ];
}

function training_atak_deck_hub(): array
{
    return [
        'version' => 2,
        'opening' => [
            'eyebrow' => 'Module 4',
            'title' => '',
            'lead' => 'Maîtriser le hub Overwatch comme radio numérique de l’élément.',
            'stats' => [
                ['label' => 'Durée indicative', 'value' => '~30 min'],
                ['label' => 'Public', 'value' => 'Opérateurs'],
                ['label' => 'Objectif', 'value' => 'Gestes in-game'],
            ],
        ],
        'closure' => [
            'title' => 'Synthèse — Hub Overwatch',
            'seen' => [
                'Le hub concentre liaison, messagerie, ordres, briefing, rapports et profil.',
                'Athena peut pousser ordres et diapos ; le terrain répond par messages et rapports.',
                'Le profil terminal explique souvent un blocage avant le « réseau HS ».',
            ],
            'acquired' => [
                'Vous ouvrez hub et messagerie sans hésiter.',
                'Vous lisez un ordre Athena sans figer tout l’élément.',
            ],
            'nextHint' => 'Passez au module « Carte partagée, marqueurs et rapports ».',
        ],
        'slides' => [
            [
                'template' => 'title_hero',
                'title' => 'Anatomie du hub',
                'subtitle' => 'Ctrl+Shift+K — centre terminal',
                'body' => '<p>L’en-tête du hub affiche l’état <strong>En liaison / Hors liaison</strong>, votre indicatif et la version du pack. Les sections mènent à la <strong>messagerie</strong> (souvent <kbd>Ctrl</kbd>+<kbd>K</kbd>), aux <strong>ordres</strong>, au <strong>briefing</strong>, aux <strong>rapports</strong> et demandes (appui aérien, MEDEVAC, renfort), et au <strong>profil terminal</strong> (certificat, réalisme). Prenez l’habitude d’y jeter un œil entre deux phases de déplacement.</p>',
                'contextKicker' => 'Module 4 · Terrain',
                'surface' => 'elevated',
                'metric' => ['label' => 'Réflexe', 'value' => 'Hub entre deux phases'],
                'cards' => [
                    ['label' => 'Messagerie', 'body' => 'Échanges courts Athena ↔ jeu.'],
                    ['label' => 'Ordres / briefing', 'body' => 'Consignes et diapos poussées depuis le portail.'],
                    ['label' => 'Profil', 'body' => 'Certificat et état réalisme du terminal.'],
                ],
            ],
            [
                'template' => 'reading_article',
                'title' => 'Messagerie et rythme tactique',
                'subtitle' => 'Lire sans immobiliser l’élément',
                'body' => <<<'HTML'
<p>Un ordre Athena pendant un déplacement se lit en points clés (qui, quoi, où, délai), pas en lecture intégrale à genoux au milieu de la rue. Entraînez-vous : ouvrir le hub, lire, accusés de réception courts (« Alpha-1-1 COPY »). Les menus ACE « ATAK Tactique » restent disponibles si votre communauté les active — ils complètent le hub, ils ne le remplacent pas.</p>
<div class="lms-reading-callout lms-reading-callout--info"><p><strong>Exercice type</strong> : envoyer « TEST LIAISON [indicatif] » au référent OP, recevoir une réponse Athena, chronométrer la lecture d’un nouvel ordre en moins de 60 secondes.</p></div>
HTML
                ,
            ],
            [
                'template' => 'scenario_decision',
                'title' => 'Ordre reçu en déplacement',
                'context' => 'Votre élément avance ; le TOC envoie un nouvel ordre depuis Athena.',
                'situation' => '<p>Le chef d’élément est occupé à la navigation. Vous êtes numéro deux avec hub opérationnel.</p>',
                'options' => [
                    ['id' => 'a', 'text' => 'Ouvrir le hub, extraire les points clés, informer le chef d’élément à voix, accuser réception courte.'],
                    ['id' => 'b', 'text' => 'Ignorer jusqu’à la prochaine pause « pour ne pas déranger ».'],
                    ['id' => 'c', 'text' => 'Arrêter toute l’équipe au milieu de la rue pour lire à voix haute le texte entier.'],
                    ['id' => 'd', 'text' => 'Répondre par une photo lourde sans lire l’ordre.'],
                ],
                'correctOptionId' => 'a',
                'explanation' => '<p>Le hub sert à absorber l’ordre sans casser le rythme ni retarder le TOC. Ignorer ou bloquer tout le monde crée du risque tactique.</p>',
            ],
            [
                'template' => 'knowledge_check',
                'title' => 'Repères hub',
                'body' => "Hub : Ctrl+Shift+K.\nMessagerie rapide : Ctrl+K (si disponible).\nBadge En liaison = prérequis avant de compter sur Tacmap.\nProfil terminal : certificat et réalisme avant de crier au bug réseau.",
            ],
        ],
    ];
}

function training_atak_deck_carte_rapports(): array
{
    return [
        'version' => 2,
        'opening' => [
            'eyebrow' => 'Module 5',
            'title' => '',
            'lead' => 'Alimenter Tacmap et le TOC avec des marqueurs et rapports actionnables.',
            'stats' => [
                ['label' => 'Durée indicative', 'value' => '~35 min'],
                ['label' => 'Public', 'value' => 'Opérateurs & TOC'],
                ['label' => 'Objectif', 'value' => 'Doctrine terrain'],
            ],
        ],
        'closure' => [
            'title' => 'Synthèse — Carte et rapports',
            'seen' => [
                'Un marqueur répond à quoi / où / pour qui.',
                'CONTACT, SALUTE, SPOTREP, SITREP et demandes MEDEVAC / QRF ont une grille TOC.',
                'Liaison dégradée : position seule + radio (PACE).',
            ],
            'acquired' => [
                'Vous savez poser un marqueur utile et le contrôler sur Tacmap.',
                'Vous savez structurer un rapport et une demande d’évacuation.',
            ],
            'nextHint' => 'Passez au module « Réalisme, encadrement et clôture d’OP ».',
        ],
        'slides' => [
            [
                'template' => 'title_hero',
                'title' => 'Doctrine de marquage',
                'subtitle' => 'Ce que le TOC doit comprendre en une seconde',
                'body' => '<p>Nommez explicitement (« CONTACT MRAP nord pont » plutôt que « marker 3 »). Le TOC voit votre <strong>indicatif</strong> associé au point. Les <strong>POI ACE</strong> (LZ, renfort, objectif) remontent aussi côté web. Les marqueurs sur l’origine carte <strong>(0,0)</strong> sont ignorés — comportement attendu, pas un bug. Après handshake initial (~30 s), la sync se compte en quelques secondes : vérifiez sur Tacmap avant de relancer dix marqueurs.</p>',
                'contextKicker' => 'Module 5 · Cartographie',
                'surface' => 'elevated',
                'metric' => ['label' => 'Test', 'value' => 'Marqueur → Tacmap &lt; 1 min'],
                'cards' => [
                    ['label' => 'Marqueur Arma', 'body' => 'Libellé métier, position identifiable.'],
                    ['label' => 'POI ACE', 'body' => 'LZ, renfort, objectif — utiles au TOC.'],
                    ['label' => 'Photos', 'body' => 'cTab / BCE → Cams ; légende qui / quoi / où / quand.'],
                ],
            ],
            [
                'template' => 'reading_article',
                'title' => 'Rapports et demandes',
                'subtitle' => 'Grille TOC, pas roman',
                'body' => <<<'HTML'
<p><strong>CONTACT</strong> : contact ennemi. <strong>SALUTE</strong> : Size, Activity, Location, Uniform, Time, Equipment. <strong>SPOTREP</strong> : observation. <strong>SITREP</strong> : situation de l’élément. Demandes : <strong>MEDEVAC</strong> (position, nombre de blessés, sécurité LZ, indicatif), <strong>QRF</strong> / renfort, appui aérien selon OP.</p>
<p>Chronométrez un rapport complet en moins de 90 secondes. Si le réalisme coupe le détail (écran endommagé), envoyez au moins la position via le flux dégradé et complétez en radio vocale — c’est la procédure <strong>PACE</strong> appliquée au numérique.</p>
HTML
                ,
            ],
            [
                'template' => 'scenario_decision',
                'title' => 'Marqueur ambigu',
                'context' => 'Le TOC voit trois pastilles « ennemi » sans précision.',
                'situation' => '<p>La radio sature. Le chef OP demande une clarification urgente.</p>',
                'options' => [
                    ['id' => 'a', 'text' => 'Corriger / remplacer par un libellé explicite (type, lieu) et un CONTACT ou SPOTREP structuré, puis confirmer sur Tacmap.'],
                    ['id' => 'b', 'text' => 'Ajouter dix autres marqueurs « pour être sûr ».'],
                    ['id' => 'c', 'text' => 'Supprimer tous les marqueurs de l’équipe sans prévenir.'],
                    ['id' => 'd', 'text' => 'Envoyer uniquement « regardez la carte » sans précision.'],
                ],
                'correctOptionId' => 'a',
                'explanation' => '<p>Moins de marqueurs, plus précis, plus un rapport structuré. Le bruit cartographique coûte du temps de décision.</p>',
            ],
            [
                'template' => 'knowledge_check',
                'title' => 'Checklist terrain → TOC',
                'body' => "Libellé marqueur = quoi + où.\nPas de marqueur en (0,0).\nContrôler la remontée sur Tacmap.\nSALUTE pour structurer une observation.\nMEDEVAC : position, blessés, LZ, indicatif.\nLiaison dégradée : position + radio (PACE).",
            ],
        ],
    ];
}

function training_atak_deck_realisme_encadrement(): array
{
    return [
        'version' => 2,
        'opening' => [
            'eyebrow' => 'Module 6',
            'title' => '',
            'lead' => 'Réagir aux états de liaison, préparer une OP et clôturer sur Athena.',
            'stats' => [
                ['label' => 'Durée indicative', 'value' => '~38 min'],
                ['label' => 'Public', 'value' => 'Avancé & encadrement'],
                ['label' => 'Objectif', 'value' => 'Conduite d’OP'],
            ],
        ],
        'closure' => [
            'title' => 'Synthèse — Réalisme et encadrement',
            'seen' => [
                'Chaque overlay a une cause et une action (attendre, sortir zone, rallumer, réparer).',
                'Le chef de mission aligne mods, carte, zones, briefing et dry-run.',
                'Cycle de mission et AAR ferment la boucle avec les données réellement remontées.',
            ],
            'acquired' => [
                'Vous adaptez votre comportement au type de coupure.',
                'Vous savez ce que l’encadrement doit vérifier avant ouverture publique.',
            ],
            'nextHint' => 'Validez vos acquis avec le questionnaire final.',
        ],
        'slides' => [
            [
                'template' => 'title_hero',
                'title' => 'Réalisme liaison — lire l’overlay',
                'subtitle' => 'Pack Overwatch 1.3.0+',
                'body' => '<p>Quand le réalisme est activé, le terminal teste dans l’ordre : destruction → gel → coupure réseau → zone → ATAK éteint → écran cassé → OK. Un bandeau « Liaison perdue » n’appelle pas la même réaction qu’un écran endommagé (position seule) ou qu’un ATAK éteint (ACE → Rallumer). Sortir d’une zone sans couverture, réparer avec trousse, ou basculer PACE : le geste suit la cause.</p>',
                'contextKicker' => 'Module 6 · Réalisme',
                'surface' => 'elevated',
                'metric' => ['label' => 'Reprise crash', 'value' => '~10 min'],
                'cards' => [
                    ['label' => 'Zone / brouilleur', 'body' => 'Alerter le chef d’élément, noter entrée/sortie.'],
                    ['label' => 'Écran endommagé', 'body' => 'Position seule + détail radio ; réparer si sécurisé.'],
                    ['label' => 'Crash / JIP', 'body' => 'Fenêtre de reprise d’indicatif et d’état.'],
                ],
            ],
            [
                'template' => 'reading_article',
                'title' => 'Checklist chef de mission',
                'subtitle' => 'Avant d’ouvrir le serveur public',
                'body' => <<<'HTML'
<p>Aligner mods serveur et clients (Overwatch + CBA, version minimale annoncée). Vérifier la bibliothèque native sur le dédié. Aligner clé communauté (canal staff) et carte Tacmap. Briefing joueurs : portail, indicatif, terminal / certificats, niveau de réalisme. Placer les modules Eden / zones Zeus avec parcimonie (objectifs narratifs, pas tout l’AO). Synchroniser les zones portail si l’option est active. <strong>Dry-run</strong> : un opérateur test liaison + marqueur + rapport.</p>
<p>Prévoir une procédure <strong>PACE</strong> (radio, runner, pyro) quand le numérique est coupé. Anticiper les certificats expirés. Après l’OP : révoquer passerelles temporaires, s’appuyer sur journal / AAR / cycle de mission Athena.</p>
<div class="lms-reading-callout lms-reading-callout--info"><p><strong>Renseignement</strong> : photos et légendes métier existent déjà ; les scénarios SSE avancés (roadmap) restent de la doctrine — ne pas inventer d’outil absent du pack actuel.</p></div>
HTML
                ,
            ],
            [
                'template' => 'scenario_decision',
                'title' => 'Brouilleur sur tout l’AO',
                'context' => 'Conception d’OP Zeus.',
                'situation' => '<p>Un concepteur propose de brouiller toute la carte « pour le réalisme ».</p>',
                'options' => [
                    ['id' => 'a', 'text' => 'Limiter brouilleurs et zones aux objectifs narratifs, prévoir PACE, tester un dry-run.'],
                    ['id' => 'b', 'text' => 'Brouiller tout l’AO sans procédure de secours.'],
                    ['id' => 'c', 'text' => 'Désactiver toute liaison pour toute la campagne.'],
                    ['id' => 'd', 'text' => 'Demander aux joueurs de publier leurs clés personnelles pour contourner.'],
                ],
                'correctOptionId' => 'a',
                'explanation' => '<p>Le réalisme sert le scénario ; un AO entièrement muet sans PACE détruit la valeur du système et la conduite TOC.</p>',
            ],
            [
                'template' => 'knowledge_check',
                'title' => 'Clôture propre',
                'body' => "Identifier la cause derrière un overlay avant d’agir.\nChecklist pré-OP + dry-run avant ouverture publique.\nPasserelle : consentement bilatéral puis révocation.\nAAR / cycle de mission : s’appuyer sur les remontées réelles.\nNe jamais documenter secrets, clés ou détails d’attaque dans un brief joueur.",
            ],
        ],
    ];
}

/** @return list<array{text:string,explain?:string,answers:list<array{t:string,ok:bool}>}> */
function training_atak_final_quiz_questions(): array
{
    return [
        [
            'text' => 'Quelle est la répartition correcte Athena / Overwatch ?',
            'explain' => 'Athena concentre les surfaces TOC web ; Overwatch est le terminal terrain in-game.',
            'answers' => [
                ['t' => 'Athena = TOC web (Tacmap…) ; Overwatch = terminal Arma', 'ok' => true],
                ['t' => 'Overwatch remplace entièrement le portail Athena', 'ok' => false],
                ['t' => 'Tacmap n’existe que dans Arma, pas sur le site', 'ok' => false],
                ['t' => 'Le hub Overwatch est uniquement une page admin hors jeu', 'ok' => false],
            ],
        ],
        [
            'text' => 'Quel ordre d’activation des mods est attendu ?',
            'explain' => 'CBA doit charger avant Overwatch pour éviter des échecs silencieux.',
            'answers' => [
                ['t' => 'CBA_A3 puis @COMSPECOverwatch', 'ok' => true],
                ['t' => 'Overwatch puis CBA', 'ok' => false],
                ['t' => 'Uniquement Overwatch, CBA interdit', 'ok' => false],
                ['t' => 'N’importe quel ordre tant que les deux sont cochés', 'ok' => false],
            ],
        ],
        [
            'text' => 'Où contrôler d’abord qu’un opérateur est bien relié ?',
            'explain' => 'Le badge hub et Tacmap sont les deux faces du contrôle.',
            'answers' => [
                ['t' => 'Badge En liaison dans le hub, croisé avec Tacmap', 'ok' => true],
                ['t' => 'Uniquement le chat Discord public', 'ok' => false],
                ['t' => 'Uniquement la présence d’un marqueur en 0,0', 'ok' => false],
                ['t' => 'La désactivation de CBA', 'ok' => false],
            ],
        ],
        [
            'text' => 'La passerelle carte ATAK entre deux unités exige :',
            'explain' => 'Consentement bilatéral avant tout partage de positions.',
            'answers' => [
                ['t' => 'Un code échangé et une validation des deux côtés', 'ok' => true],
                ['t' => 'Un seul côté valide sans l’autre', 'ok' => false],
                ['t' => 'La publication du code en salon ouvert', 'ok' => false],
                ['t' => 'Aucune validation une fois le code généré', 'ok' => false],
            ],
        ],
        [
            'text' => 'Un bon libellé de marqueur pour le TOC :',
            'explain' => 'Quoi / où / pour qui, sans ambiguïté.',
            'answers' => [
                ['t' => '« CONTACT MRAP nord pont » (explicite)', 'ok' => true],
                ['t' => '« marker 3 »', 'ok' => false],
                ['t' => '« test »', 'ok' => false],
                ['t' => 'Laisser le libellé vide', 'ok' => false],
            ],
        ],
        [
            'text' => 'En cas d’écran endommagé (réalisme), la bonne attitude est :',
            'explain' => 'Position seule encore utile ; détail en radio ; réparer si sécurisé.',
            'answers' => [
                ['t' => 'S’appuyer sur la position restante + radio, réparer si possible', 'ok' => true],
                ['t' => 'Envoyer des photos lourdes en boucle', 'ok' => false],
                ['t' => 'Ignorer et continuer comme si le hub était complet', 'ok' => false],
                ['t' => 'Publier sa clé d’accès pour contourner', 'ok' => false],
            ],
        ],
        [
            'text' => 'Avant d’ouvrir une OP publique, le chef de mission doit surtout :',
            'explain' => 'Aligner mods/carte/briefing et faire un dry-run liaison + marqueur + rapport.',
            'answers' => [
                ['t' => 'Vérifier versions, carte, briefing et faire un dry-run', 'ok' => true],
                ['t' => 'Brouiller tout l’AO sans procédure de secours', 'ok' => false],
                ['t' => 'Demander aux joueurs d’exposer leurs secrets de compte', 'ok' => false],
                ['t' => 'Désactiver CBA sur le serveur', 'ok' => false],
            ],
        ],
        [
            'text' => 'Une demande MEDEVAC utile au TOC contient au minimum :',
            'explain' => 'Position, blessés, sécurité LZ, indicatif.',
            'answers' => [
                ['t' => 'Position, nombre de blessés, sécurité LZ, indicatif', 'ok' => true],
                ['t' => 'Uniquement « besoin medevac » sans position', 'ok' => false],
                ['t' => 'Uniquement une capture d’écran floue', 'ok' => false],
                ['t' => 'Un marqueur sans libellé en 0,0', 'ok' => false],
            ],
        ],
        [
            'text' => 'À quoi sert le journal de liaison côté Athena ?',
            'explain' => 'Historique utile au suivi TOC et au débrief, pas un salon public.',
            'answers' => [
                ['t' => 'Suivre connexions, indicatifs et échanges pour le TOC / débrief', 'ok' => true],
                ['t' => 'Remplacer le hub Overwatch en jeu', 'ok' => false],
                ['t' => 'Publier automatiquement les clés communauté', 'ok' => false],
                ['t' => 'Désinstaller le pack à distance', 'ok' => false],
            ],
        ],
        [
            'text' => 'Après un exercice avec passerelle, la bonne clôture est :',
            'explain' => 'Révoquer le partage temporaire et s’appuyer sur AAR / cycle de mission.',
            'answers' => [
                ['t' => 'Révoquer la passerelle et documenter l’AAR avec les remontées réelles', 'ok' => true],
                ['t' => 'Laisser la passerelle active indéfiniment', 'ok' => false],
                ['t' => 'Effacer tout le journal pour « repartir à zéro »', 'ok' => false],
                ['t' => 'Partager le code en public pour la prochaine fois', 'ok' => false],
            ],
        ],
    ];
}

function training_atak_seed_one_tenant(PDO $pdo, int $tenantId, int $authorUserId): void
{
    $slug = 'parcours-atak-web-jeu';
    if (training_atak_course_exists($pdo, $tenantId, $slug)) {
        echo "  training_atak_course : tenant {$tenantId} — formation « {$slug} » déjà présente.\n";

        return;
    }

    $themeJson = json_encode([
        'accent' => '#0f766e',
        'accentRgb' => '15, 118, 110',
        'font' => "'IBM Plex Sans', system-ui, sans-serif",
        'radius' => '1.25rem',
        'variant' => 'default',
        'pedagogy_meta' => [
            'target_audience' => ['opérateurs', 'TOC', 'chefs de mission', 'Zeus / OP'],
            'pedagogical_style' => 'structured_reference',
            'completion_message' => 'Parcours terminé : vous disposez des repères ATAK web (Athena) et Overwatch in-game. Les qualifications opérationnelles restent du ressort de votre unité ; le guide mod complète le dépannage fin.',
            'tags' => ['ATAK', 'Overwatch', 'Tacmap', 'liaison', 'TOC', 'réalisme', 'passerelle'],
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $uuid = training_atak_uuid_v4();
    $now = date('Y-m-d H:i:s');
    $specs = training_atak_module_specs();
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
            'ATAK Athena et Overwatch — web et en jeu',
            $slug,
            'ATAK-101',
            training_atak_course_short_description(),
            training_atak_course_description(),
            training_atak_course_objectives(),
            $themeJson,
            training_atak_course_thumbnail_path(),
            training_atak_course_banner_path(),
            'Opérations',
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
            $pdo->prepare('UPDATE training_courses SET showcase_sort_order = 4, showcase_badge = ? WHERE id = ?')->execute(['open', $courseId]);
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
            $modLo = training_atak_module_objectives_json($m['module_learning_objectives'] ?? []);
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
                'Quiz — ATAK web et Overwatch',
                'Validez vos acquis sur Athena (Tacmap, TOC, passerelle) et Overwatch (hub, marqueurs, rapports, réalisme, encadrement).',
                75.00,
                5,
                30,
                1,
                1,
                $now,
            ]);
            $finalQz = (int) $pdo->lastInsertId();
            training_atak_seed_quiz_questions_for_module($pdo, $finalQz, training_atak_final_quiz_questions(), $now);
        }

        if ($ownsTx) {
            $pdo->commit();
        }
        echo "  training_atak_course : tenant {$tenantId} — formation « ATAK Athena et Overwatch » créée (course_id={$courseId}).\n";
    } catch (\Throwable $e) {
        if ($ownsTx && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($ownsTx) {
            echo '  [ATTENTION] training_atak_course : ' . $e->getMessage() . "\n";
        } else {
            throw $e;
        }
    }
}
