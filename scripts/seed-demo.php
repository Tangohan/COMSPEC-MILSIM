<?php

declare(strict_types=1);

/**
 * Seed données de démonstration (idempotent).
 *
 * Crée :
 *  - une entité / communauté « Force Démo COMSPEC » (slug demo-comspec)
 *  - des comptes avec rôles variés (mot de passe commun : demo)
 *  - des grades + affectations ORBAT logiques (annuaire)
 *  - des formations ultra-rapides publiées
 *  - des inscriptions + avancements LMS (tableau de bord « formations prioritaires »)
 *  - des offres de recrutement publiées + candidatures (pipeline)
 *  - des annonces (alertes, mur recruteurs, sujet forum)
 *  - des événements à venir
 *  - des alertes communauté + une alerte plateforme
 *  - une situation tactique fake pour ATAK + Overwatch (Altis)
 *
 * Prérequis : schéma BDD présent (tables tenants/users/roles).
 * Aucun compte admin plateforme requis : un créateur technique est provisionné si besoin.
 *
 * Usage : php scripts/seed-demo.php
 */

$root = dirname(__DIR__);

require_once $root . '/bootstrap/autoload.php';
require_once $root . '/bootstrap/env.php';
load_env($root);
require_once $root . '/bootstrap/app.php';

use App\Core\Container;
use App\Core\Database;
use App\Repositories\AssetLogisticsRepository;
use App\Repositories\AtakDataRepository;
use App\Repositories\AtakMapRepository;
use App\Repositories\CommunityEventRepository;
use App\Repositories\DangerZoneRepository;
use App\Repositories\EnlistmentRepository;
use App\Repositories\FireUnitRepository;
use App\Repositories\ForumCategoryRepository;
use App\Repositories\ForumPostRepository;
use App\Repositories\ForumTopicRepository;
use App\Repositories\GradeRepository;
use App\Repositories\IffAssetStatusRepository;
use App\Repositories\IffChallengeRepository;
use App\Repositories\IntelReportRepository;
use App\Repositories\MapShapeRepository;
use App\Repositories\PersonnelAssignmentRepository;
use App\Repositories\PlatformAlertRepository;
use App\Repositories\RecruitmentOpeningRepository;
use App\Repositories\RecruitmentTeamWallRepository;
use App\Repositories\ReplayRepository;
use App\Repositories\RoleRepository;
use App\Repositories\TenantAlertRepository;
use App\Repositories\TenantAtakConfigRepository;
use App\Repositories\TenantRepository;
use App\Repositories\TrainingCourseRepository;
use App\Repositories\TrainingEnrollmentRepository;
use App\Repositories\TrainingProgressRepository;
use App\Repositories\UnitRepository;
use App\Repositories\UserRepository;
use App\Services\Community\TenantBootstrapService;
use App\Services\Community\TenantOnboardingHealthService;
use App\Services\Personnel\MatriculeService;
use App\Services\Training\TrainingService;
use App\Support\DemoPortalAccounts;
use App\Support\SqlText;

const DEMO_TENANT_SLUG = DemoPortalAccounts::TENANT_SLUG;
const DEMO_TENANT_NAME = DemoPortalAccounts::TENANT_NAME;
const DEMO_PASSWORD = DemoPortalAccounts::SHARED_PASSWORD;
const DEMO_CAMPAIGN_TAG = 'demo-seed';

$pdo = Database::getPdo();

/** @var TenantRepository $tenants */
$tenants = Container::get(TenantRepository::class);
/** @var UserRepository $users */
$users = Container::get(UserRepository::class);
/** @var RoleRepository $roles */
$roles = Container::get(RoleRepository::class);
/** @var TenantBootstrapService $bootstrap */
$bootstrap = Container::get(TenantBootstrapService::class);
/** @var TrainingCourseRepository $courses */
$courses = Container::get(TrainingCourseRepository::class);
/** @var CommunityEventRepository $events */
$events = Container::get(CommunityEventRepository::class);
/** @var TenantAlertRepository $tenantAlerts */
$tenantAlerts = Container::get(TenantAlertRepository::class);
/** @var PlatformAlertRepository $platformAlerts */
$platformAlerts = Container::get(PlatformAlertRepository::class);

echo "=== Seed démo COMSPEC-MILSIM ===\n";

// ----- Créateur (n’importe quel user actif, sinon bootstrap local — jamais obligatoire admin@athena.local) -----
$creatorId = demo_resolve_creator_user_id($pdo, $users, $tenants, $roles);
echo "[OK] Créateur technique #{$creatorId}\n";

// ----- Entité démo -----
$existing = $tenants->findBySlug(DEMO_TENANT_SLUG);
if ($existing) {
    $tenantId = (int) $existing['id'];
    echo "[SKIP] Entité déjà présente : " . DEMO_TENANT_NAME . " (#{$tenantId})\n";
} else {
    $result = $bootstrap->createCommunity($creatorId, DEMO_TENANT_NAME, DEMO_TENANT_SLUG, [
        'plan_slug' => 'pro',
        'registration_mode' => 'milsim',
        'welcome_text' => 'Bienvenue dans l’entité de démonstration. Explorez les rôles, formations, événements et alertes.',
        'public_hero_subtitle' => 'Organisation fictive pour présenter le portail en conditions réalistes.',
        'public_doctrine' => 'Démonstration — ne pas utiliser en production.',
    ]);
    $tenantId = (int) $result['tenant_id'];
    $ownerCloneId = (int) $result['user_id'];
    echo "[OK] Entité créée : " . DEMO_TENANT_NAME . " (#{$tenantId}), propriétaire clone #{$ownerCloneId}\n";

    $tenants->mergeSettings($tenantId, [
        'demo_seed' => true,
        'demo_seeded_at' => date('c'),
    ]);
}

$passwordHash = password_hash(DEMO_PASSWORD, PASSWORD_ARGON2ID);

// ----- Garantie : aucun compte @demo.local n’est administrateur de la plateforme -----
demo_revoke_site_roles_for_demo_emails($pdo);

// ----- Comptes démo (rôles organisation uniquement — jamais site) -----
$demoAccounts = [
    [
        'email' => 'gestionnaire@demo.local',
        'display_name' => 'Alex Commandement',
        'callsign' => 'CMD-DEMO',
        'roles' => ['community_owner'],
    ],
    [
        'email' => 'admin-orga@demo.local',
        'display_name' => 'Sam Administration',
        'callsign' => 'ADM-DEMO',
        'roles' => ['tenant_admin'],
    ],
    [
        'email' => 'cadre@demo.local',
        'display_name' => 'Jordan Cadre',
        'callsign' => 'CDR-DEMO',
        'roles' => ['officer'],
    ],
    [
        'email' => 'rh@demo.local',
        'display_name' => 'Morgan RH',
        'callsign' => 'RH-DEMO',
        'roles' => ['hr'],
    ],
    [
        'email' => 'recruteur@demo.local',
        'display_name' => 'Casey Recrutement',
        'callsign' => 'REC-DEMO',
        'roles' => ['recruiter'],
    ],
    [
        'email' => 'instructeur@demo.local',
        'display_name' => 'Riley Formation',
        'callsign' => 'INS-DEMO',
        'roles' => ['instructor'],
    ],
    [
        'email' => 'formateur@demo.local',
        'display_name' => 'Taylor Pédagogie',
        'callsign' => 'FOR-DEMO',
        'roles' => ['trainer'],
    ],
    [
        'email' => 'comms@demo.local',
        'display_name' => 'Quinn Communication',
        'callsign' => 'COM-DEMO',
        'roles' => ['forum_moderator'],
    ],
    [
        'email' => 'opsan@demo.local',
        'display_name' => 'Avery OPSAN',
        'callsign' => 'SAN-DEMO',
        'roles' => ['medic'],
    ],
    [
        'email' => 'logistique@demo.local',
        'display_name' => 'Reese Logistique',
        'callsign' => 'LOG-DEMO',
        'roles' => ['logistics'],
    ],
    [
        'email' => 'rto@demo.local',
        'display_name' => 'Drew Transmissions',
        'callsign' => 'R2-DEMO',
        'roles' => ['rto'],
    ],
    [
        'email' => 'operateur@demo.local',
        'display_name' => 'Pat Opérateur',
        'callsign' => 'OPS-DEMO',
        'roles' => ['member'],
    ],
    [
        'email' => 'visiteur@demo.local',
        'display_name' => 'Guest Visiteur',
        'callsign' => 'VIS-DEMO',
        'roles' => ['invite'],
    ],
];

$createdUsers = 0;
$skippedUsers = 0;
$userIdsByEmail = [];

foreach ($demoAccounts as $acc) {
    $email = $acc['email'];
    $existingUser = $users->findByEmail($tenantId, $email);
    if ($existingUser) {
        $userIdsByEmail[$email] = (int) $existingUser['id'];
        ++$skippedUsers;
        echo "  [SKIP] Compte {$email}\n";
        continue;
    }

    $roleIds = [];
    foreach ($acc['roles'] as $slug) {
        $rid = $roles->getIdBySlug($tenantId, $slug);
        if ($rid === null) {
            echo "  [ATTENTION] Rôle « {$slug} » absent pour {$email} — compte créé sans ce rôle.\n";
            continue;
        }
        $roleIds[] = $rid;
    }
    $primaryRoleId = $roleIds[0] ?? $roles->getIdBySlug($tenantId, 'member');

    $userId = $users->create($tenantId, [
        'email' => $email,
        'password_hash' => $passwordHash,
        'display_name' => $acc['display_name'],
        'callsign' => $acc['callsign'],
        'role_id' => $primaryRoleId,
        'status' => 'active',
    ]);

    if ($roleIds !== []) {
        $users->syncOrganizationRoles($userId, $tenantId, $roleIds, null, true);
    }

    demo_mark_email_verified($pdo, $userId);
    demo_disable_login_otp_for_user($pdo, $users, $tenantId, $userId);

    $userIdsByEmail[$email] = $userId;
    ++$createdUsers;
    echo "  [OK] Compte {$email} → " . implode(', ', $acc['roles']) . "\n";
}

echo "[OK] Comptes : {$createdUsers} créés, {$skippedUsers} déjà présents\n";
demo_revoke_site_roles_for_demo_emails($pdo);
echo "[OK] Vérifié : aucun compte @demo.local n’a de rôle d’administration de la plateforme\n";
demo_disable_login_otp_for_all_demo_accounts($pdo, $users, $tenantId);
echo "[OK] Double vérification (2FA) désactivée sur tous les comptes @demo.local\n";

// Auteur des contenus démo : gestionnaire d’orga (jamais un admin plateforme requis)
$authorId = $userIdsByEmail['gestionnaire@demo.local'] ?? 0;
if ($authorId < 1) {
    $u = $users->findByEmail($tenantId, 'gestionnaire@demo.local');
    $authorId = $u ? (int) $u['id'] : 0;
}
if ($authorId < 1) {
    $authorId = $userIdsByEmail['admin-orga@demo.local']
        ?? $userIdsByEmail['operateur@demo.local']
        ?? (int) (reset($userIdsByEmail) ?: 0);
}
if ($authorId < 1) {
    $any = $pdo->prepare('SELECT id FROM users WHERE tenant_id = ? AND status = ? ORDER BY id ASC LIMIT 1');
    $any->execute([$tenantId, 'active']);
    $authorId = (int) ($any->fetchColumn() ?: 0);
}
if ($authorId < 1) {
    $authorId = $creatorId;
}

// ----- Grades FR + affectations ORBAT (annuaire) -----
demo_seed_grades_and_assignments($pdo, $tenantId, $userIdsByEmail, $tenants, $users);

// ----- Formations ultra-rapides -----
$demoCourses = [
    [
        'slug' => 'demo-briefing-5min',
        'title' => 'Briefing express (5 min)',
        'short' => 'Parcours démo ultra-court pour découvrir le LMS.',
        'description' => 'Formation de démonstration : une seule leçon de lecture, conçue pour être terminée en quelques minutes.',
        'minutes' => 5,
        'level' => 'initiation',
        'category' => 'Démonstration',
        'lessons' => [
            [
                'title' => 'Bienvenue dans le parcours démo',
                'content' => '<p>Cette formation illustre un parcours LMS minimal.</p><ul><li>Lisez ce texte</li><li>Marquez la leçon comme terminée</li><li>Le parcours est validé</li></ul><p>Utilisez-la pour présenter le catalogue et le suivi de progression.</p>',
                'minutes' => 3,
            ],
        ],
    ],
    [
        'slug' => 'demo-securite-radio',
        'title' => 'Sécurité radio (flash)',
        'short' => 'Rappels essentiels transmissions — format démo.',
        'description' => 'Mini-module démo sur les bons réflexes radio, sans quiz pour accélérer la présentation.',
        'minutes' => 8,
        'level' => 'initiation',
        'category' => 'Démonstration',
        'lessons' => [
            [
                'title' => 'Discipline de réseau',
                'content' => '<p>En démonstration : discipline d’antenne, identification claire, messages courts.</p><p>Sur le terrain, chaque unité définit ses procédures ; ici l’objectif est uniquement de montrer une formation publiée.</p>',
                'minutes' => 4,
            ],
            [
                'title' => 'Check-list avant départ',
                'content' => '<ol><li>Fréquences et canaux validés</li><li>Batteries et rechange</li><li>Callsigns d’équipe connus</li></ol>',
                'minutes' => 3,
            ],
        ],
    ],
    [
        'slug' => 'demo-accueil-nouveau',
        'title' => 'Accueil nouvel arrivant (flash)',
        'short' => 'Premiers pas dans l’entité — version démo.',
        'description' => 'Parcours d’accueil fictif pour illustrer une formation obligatoire courte.',
        'minutes' => 6,
        'level' => 'initiation',
        'category' => 'Démonstration',
        'is_mandatory' => 1,
        'lessons' => [
            [
                'title' => 'Ce que vous trouverez ici',
                'content' => '<p>Forum, documents, formations, événements et alertes : ce parcours montre le fil conducteur d’un nouvel arrivant.</p>',
                'minutes' => 4,
            ],
        ],
    ],
];

$createdCourses = 0;
foreach ($demoCourses as $spec) {
    if ($courses->slugExists($tenantId, $spec['slug'])) {
        echo "  [SKIP] Formation {$spec['slug']}\n";
        continue;
    }
    if ($authorId < 1) {
        echo "  [ATTENTION] Pas d’auteur pour créer la formation {$spec['slug']}\n";
        continue;
    }

    $courseId = $courses->create($tenantId, [
        'title' => $spec['title'],
        'slug' => $spec['slug'],
        'short_description' => $spec['short'],
        'description' => $spec['description'],
        'category' => $spec['category'],
        'level' => $spec['level'],
        'language_code' => 'fr',
        'estimated_minutes' => $spec['minutes'],
        'passing_score' => 80,
        'is_mandatory' => (int) ($spec['is_mandatory'] ?? 0),
        'is_certifying' => 0,
        'visibility' => 'published',
        'created_by' => $authorId,
        'updated_by' => $authorId,
    ]);
    // Politique ouverte (tous les membres) si la colonne existe côté update
    try {
        $courses->update($courseId, [
            'enrollment_policy_json' => json_encode(new stdClass(), JSON_UNESCAPED_UNICODE),
            'updated_by' => $authorId,
        ]);
    } catch (Throwable) {
        // Colonne absente ou schéma partiel : la formation reste utilisable
    }

    $now = date('Y-m-d H:i:s');
    $pdo->prepare(
        'INSERT INTO training_modules (course_id, title, description, estimated_minutes, position, is_required, created_at, updated_at)
         VALUES (?, ?, ?, ?, 1, 1, ?, ?)'
    )->execute([
        $courseId,
        'Module unique — démo',
        'Contenu condensé pour présentation.',
        $spec['minutes'],
        $now,
        $now,
    ]);
    $moduleId = (int) $pdo->lastInsertId();

    $pos = 1;
    foreach ($spec['lessons'] as $lesson) {
        $pdo->prepare(
            'INSERT INTO training_lessons (module_id, title, summary, lesson_type, content, duration_minutes, difficulty, position, is_required)
             VALUES (?, ?, ?, \'richtext\', ?, ?, \'easy\', ?, 1)'
        )->execute([
            $moduleId,
            $lesson['title'],
            'Leçon démo',
            $lesson['content'],
            (int) $lesson['minutes'],
            $pos,
        ]);
        ++$pos;
    }

    ++$createdCourses;
    echo "  [OK] Formation {$spec['slug']} (#{$courseId})\n";
}
echo "[OK] Formations démo : {$createdCourses} créées\n";

// ----- Événements -----
$demoEvents = [
    [
        'title' => '[Démo] Opération Nightfall',
        'description' => 'Opération fictive pour illustrer le planning et les RSVP. Objectif : sécuriser un périmètre et extraire une équipe amie.',
        'location' => 'Secteur Alpha — carte démo',
        'event_type' => 'operation',
        'starts_offset_days' => 3,
        'duration_hours' => 4,
    ],
    [
        'title' => '[Démo] Briefing hebdomadaire',
        'description' => 'Réunion d’information de l’unité : points RH, formations à venir et rappels sécurité.',
        'location' => 'Salle de brief (virtuelle)',
        'event_type' => 'evenement',
        'starts_offset_days' => 1,
        'duration_hours' => 1,
    ],
    [
        'title' => '[Démo] Session formation radio',
        'description' => 'Créneau pédagogique lié à la formation flash « Sécurité radio ».',
        'location' => 'Stand transmissions',
        'event_type' => 'formation',
        'starts_offset_days' => 5,
        'duration_hours' => 2,
    ],
    [
        'title' => '[Démo] Session Training Camp — Altis',
        'description' => "Session ponctuelle de ce vendredi.\nSandbox / Drill / Évaluation des compétences et mise à niveau / SOT.",
        'location' => 'Training Camp — Altis',
        'event_type' => 'formation',
        'starts_offset_days' => 2,
        'duration_hours' => 3,
        'rich' => true,
        'conditions_general' => "Présence au briefing obligatoire pour le top action nominal.\nTenue et matériel conformes aux consignes unitaires.",
        'conditions_special' => "Top action différé possible si arrivée après 21H00 — voir le déroulement ci-dessous.",
        'tags' => ['sandbox', 'drill', 'evaluation', 'mise_a_niveau', 'sot'],
        'schedule' => [
            ['type' => 'phase', 'tone' => 'red', 'label' => 'Début de regroupement', 'time' => '20H45 - 21H00'],
            ['type' => 'phase', 'tone' => 'orange', 'label' => 'Briefing', 'time' => '21H00 - 21H15'],
            ['type' => 'phase', 'tone' => 'yellow', 'label' => 'Ajustement du POE / S’équiper + 5 min de conditionnement', 'time' => '21H15 - 21H25'],
            ['type' => 'phase', 'tone' => 'green', 'label' => 'TOP ACTION', 'time' => '21H30'],
            ['type' => 'section', 'label' => 'Top action différé (non présent à 21H00)'],
            ['type' => 'phase', 'tone' => 'black', 'label' => 'Créneau de regroupement', 'time' => '21H20 - 21H40'],
            ['type' => 'phase', 'tone' => 'white', 'label' => 'S’équiper', 'time' => 'Jusqu’à 21H45'],
            ['type' => 'phase', 'tone' => 'green', 'label' => 'TOP ACTION', 'time' => '21H50'],
        ],
    ],
    [
        'title' => '[Démo] Soirée intégration',
        'description' => 'Moment convivial pour les nouveaux arrivants — événement « autre ».',
        'location' => 'Discord / salon général',
        'event_type' => 'autre',
        'starts_offset_days' => 7,
        'duration_hours' => 3,
    ],
];

$createdEvents = 0;
foreach ($demoEvents as $ev) {
    $chk = $pdo->prepare(
        'SELECT id FROM community_events WHERE tenant_id = ? AND title = ? AND campaign_tag = ? LIMIT 1'
    );
    $chk->execute([$tenantId, $ev['title'], DEMO_CAMPAIGN_TAG]);
    $existingEventId = (int) ($chk->fetchColumn() ?: 0);
    if ($existingEventId > 0) {
        if (!empty($ev['rich'])) {
            try {
                $events->updateDetails($existingEventId, $tenantId, [
                    'description' => $ev['description'] ?? null,
                    'location' => $ev['location'] ?? null,
                    'conditions_general' => $ev['conditions_general'] ?? null,
                    'conditions_special' => $ev['conditions_special'] ?? null,
                    'tags_json' => \App\Support\CommunityEventDetails::encodeTags($ev['tags'] ?? []),
                    'schedule_json' => \App\Support\CommunityEventDetails::encodeSchedule($ev['schedule'] ?? []),
                ]);
                echo "  [OK] Détails enrichis « {$ev['title']} » (#{$existingEventId})\n";
            } catch (Throwable $e) {
                echo "  [ATTENTION] Détails « {$ev['title']} » : " . $e->getMessage() . "\n";
            }
        } else {
            echo "  [SKIP] Événement « {$ev['title']} »\n";
        }
        continue;
    }

    $starts = (new DateTimeImmutable('now'))->modify('+' . (int) $ev['starts_offset_days'] . ' days')->setTime(20, 0);
    $ends = $starts->modify('+' . (int) $ev['duration_hours'] . ' hours');

    $eventId = $events->create(
        $tenantId,
        $authorId,
        $ev['title'],
        $ev['description'],
        $ev['location'],
        $starts->format('Y-m-d H:i:s'),
        $ends->format('Y-m-d H:i:s'),
        DEMO_CAMPAIGN_TAG,
        $ev['event_type']
    );

    if (!empty($ev['rich'])) {
        try {
            $events->updateDetails($eventId, $tenantId, [
                'conditions_general' => $ev['conditions_general'] ?? null,
                'conditions_special' => $ev['conditions_special'] ?? null,
                'tags_json' => \App\Support\CommunityEventDetails::encodeTags($ev['tags'] ?? []),
                'schedule_json' => \App\Support\CommunityEventDetails::encodeSchedule($ev['schedule'] ?? []),
            ]);
        } catch (Throwable $e) {
            echo "  [ATTENTION] Détails riches « {$ev['title']} » : " . $e->getMessage() . "\n";
        }
    }

    // Quelques RSVP pour donner du volume
    $rsvpEmails = ['operateur@demo.local', 'cadre@demo.local', 'instructeur@demo.local', 'opsan@demo.local'];
    $statuses = ['yes', 'yes', 'maybe', 'no'];
    foreach ($rsvpEmails as $i => $em) {
        $uid = $userIdsByEmail[$em] ?? null;
        if ($uid === null) {
            $u = $users->findByEmail($tenantId, $em);
            $uid = $u ? (int) $u['id'] : null;
        }
        if ($uid) {
            $status = $statuses[$i % count($statuses)];
            $events->setRsvp(
                $eventId,
                $uid,
                $status,
                $status === 'no' ? 'indisponibilite_planifiee' : null,
                $status === 'no' ? 'Conflit d’agenda (données démo)' : null
            );
        }
    }

    ++$createdEvents;
    echo "  [OK] Événement « {$ev['title']} » (#{$eventId})\n";
}
echo "[OK] Événements démo : {$createdEvents} créés\n";

// ----- Mur / tableau opérationnel -----
demo_seed_operational_board($pdo, $tenantId, $authorId, $userIdsByEmail);

// ----- Alertes (sobres — une plateforme + une communauté, pas d’urgent) -----
// Désactive les anciennes alertes démo trop voyantes si déjà seedées
try {
    $pdo->prepare(
        "UPDATE tenant_alerts SET is_active = 0
         WHERE tenant_id = ? AND (title LIKE '[Démo]%' OR title LIKE '%Force Démo%')"
    )->execute([$tenantId]);
    $pdo->prepare(
        "UPDATE platform_alerts SET is_active = 0 WHERE title LIKE '[Démo]%'"
    )->execute();
} catch (Throwable) {
    // Tables absentes : ignore
}

$demoTenantAlerts = [
    [
        'kind' => 'info',
        'title' => 'Bienvenue dans Force Démo COMSPEC',
        'body' => 'Organisation d’essai. Explorez le tableau de bord, les formations et les espaces membres.',
        'cta_label' => 'Tableau de bord',
        'cta_url' => 'dashboard',
        'sort_order' => 10,
        'icon_key' => 'info',
        'display_style' => 'classic',
        'accent_color' => '#3b82f6',
    ],
];

$createdAlerts = 0;
foreach ($demoTenantAlerts as $alert) {
    $chk = $pdo->prepare('SELECT id FROM tenant_alerts WHERE tenant_id = ? AND title = ? LIMIT 1');
    $chk->execute([$tenantId, $alert['title']]);
    $existingId = (int) ($chk->fetchColumn() ?: 0);
    if ($existingId > 0) {
        $tenantAlerts->update($existingId, $tenantId, [
            'kind' => $alert['kind'],
            'title' => $alert['title'],
            'body' => $alert['body'],
            'cta_label' => $alert['cta_label'],
            'cta_url' => $alert['cta_url'],
            'starts_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'ends_at' => date('Y-m-d H:i:s', strtotime('+90 days')),
            'sort_order' => $alert['sort_order'],
            'is_active' => 1,
            'icon_key' => $alert['icon_key'],
            'display_style' => $alert['display_style'] ?? 'classic',
            'accent_color' => $alert['accent_color'] ?? '#3b82f6',
        ]);
        echo "  [OK] Alerte communauté réactivée / adoucie (#{$existingId})\n";
        continue;
    }

    $id = $tenantAlerts->insert($tenantId, [
        'kind' => $alert['kind'],
        'title' => $alert['title'],
        'body' => $alert['body'],
        'cta_label' => $alert['cta_label'],
        'cta_url' => $alert['cta_url'],
        'starts_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
        'ends_at' => date('Y-m-d H:i:s', strtotime('+90 days')),
        'sort_order' => $alert['sort_order'],
        'is_active' => 1,
        'icon_key' => $alert['icon_key'],
        'display_style' => $alert['display_style'] ?? 'classic',
        'accent_color' => $alert['accent_color'] ?? '#3b82f6',
    ]);
    ++$createdAlerts;
    echo "  [OK] Alerte communauté « {$alert['title']} » (#{$id})\n";
}
echo "[OK] Alertes communauté : {$createdAlerts} créées (anciennes démo désactivées)\n";

// ----- Alerte plateforme (barre discrète sous le menu) -----
$platformTitle = 'Mode démonstration';
$chkPlat = $pdo->prepare('SELECT id FROM platform_alerts WHERE title = ? OR title = ? LIMIT 1');
$chkPlat->execute([$platformTitle, '[Démo] Portail en mode démonstration']);
$platExisting = (int) ($chkPlat->fetchColumn() ?: 0);
$platPayload = [
    'kind' => 'info',
    'display_style' => 'mini_info',
    'title' => $platformTitle,
    'body' => 'Comptes : gestionnaire@demo.local · admin-orga@demo.local · instructeur@demo.local · operateur@demo.local — mot de passe : ' . DEMO_PASSWORD,
    'cta_label' => 'Entité démo',
    'cta_url' => 'c/' . DEMO_TENANT_SLUG,
    'starts_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
    'ends_at' => date('Y-m-d H:i:s', strtotime('+90 days')),
    'sort_order' => 1,
    'is_active' => 1,
    'dismissible' => 1,
    'audience_json' => ['all' => true],
];
if ($platExisting > 0) {
    $platformAlerts->update($platExisting, $platPayload);
    echo "[OK] Alerte plateforme adoucie (#{$platExisting})\n";
} else {
    $platId = $platformAlerts->insert($platPayload);
    echo "[OK] Alerte plateforme (#{$platId})\n";
}

// ----- Inscriptions & avancements LMS (remplit « Formations prioritaires ») -----
demo_seed_training_progress($pdo, $tenantId, $authorId, $userIdsByEmail);

// ----- Offres, postes & candidatures -----
demo_seed_recruitment($pdo, $tenantId, $authorId, $userIdsByEmail, $tenants);

// ----- Annonces (alertes + mur recruteurs + forum) -----
demo_seed_announcements($pdo, $tenantId, $authorId, $userIdsByEmail, $tenantAlerts);

// ----- ATAK + Overwatch (situation tactique fake) -----
demo_seed_atak_overwatch($pdo, $tenantId);

echo "\n=== Terminé ===\n";
echo "Entité : " . DEMO_TENANT_NAME . " (slug " . DEMO_TENANT_SLUG . ", id {$tenantId})\n";
echo "Mot de passe commun : " . DEMO_PASSWORD . "\n";
echo "Comptes annoncés à la validation du code démo :\n";
foreach (DemoPortalAccounts::announcedAccounts() as $acc) {
    echo '  - ' . $acc['email'] . '  (' . $acc['role_label'] . ")\n";
}
echo "Aucun de ces comptes n’est administrateur de la plateforme.\n";
echo "Dashboard : formations en cours pour gestionnaire@ / operateur@ / admin-orga@\n";
echo "Annuaire : grades FR + affectations ORBAT sur les comptes @demo.local\n";
echo "Recrutement : 3 offres publiées + candidatures (pipeline)\n";
echo "Cartes tactiques : /atak et /overwatch (mission démo Altis)\n";

/**
 * Marque l’e-mail comme vérifié si la colonne existe.
 */
function demo_mark_email_verified(PDO $pdo, int $userId): void
{
    static $hasCol = null;
    if ($hasCol === null) {
        $st = $pdo->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'email_verified_at' LIMIT 1");
        $hasCol = $st && (bool) $st->fetchColumn();
    }
    if (!$hasCol || $userId < 1) {
        return;
    }
    $pdo->prepare('UPDATE users SET email_verified_at = COALESCE(email_verified_at, NOW()) WHERE id = ?')->execute([$userId]);
}

/**
 * Désactive la double vérification e-mail optionnelle sur un compte.
 */
function demo_disable_login_otp_for_user(PDO $pdo, UserRepository $users, int $tenantId, int $userId): void
{
    if ($userId < 1) {
        return;
    }
    static $hasCol = null;
    static $hasTotp = null;
    if ($hasCol === null) {
        $st = $pdo->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'email_login_otp_enabled' LIMIT 1");
        $hasCol = $st && (bool) $st->fetchColumn();
    }
    if ($hasTotp === null) {
        $st = $pdo->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'totp_enabled' LIMIT 1");
        $hasTotp = $st && (bool) $st->fetchColumn();
    }
    if ($hasCol) {
        try {
            $users->update($userId, $tenantId, ['email_login_otp_enabled' => 0]);
        } catch (Throwable) {
            $pdo->prepare('UPDATE users SET email_login_otp_enabled = 0 WHERE id = ? AND tenant_id = ?')->execute([$userId, $tenantId]);
        }
    }
    if ($hasTotp) {
        try {
            $users->update($userId, $tenantId, [
                'totp_enabled' => 0,
                'totp_secret' => null,
                'totp_confirmed_at' => null,
            ]);
        } catch (Throwable) {
            $pdo->prepare('UPDATE users SET totp_enabled = 0, totp_secret = NULL, totp_confirmed_at = NULL WHERE id = ? AND tenant_id = ?')->execute([$userId, $tenantId]);
        }
    }
}

/**
 * Désactive le flag 2FA e-mail pour tous les comptes démo du tenant (idempotent).
 */
function demo_disable_login_otp_for_all_demo_accounts(PDO $pdo, UserRepository $users, int $tenantId): void
{
    $st = $pdo->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'email_login_otp_enabled' LIMIT 1");
    if ($st && $st->fetchColumn()) {
        $emails = DemoPortalAccounts::allDemoEmails();
        $ph = implode(',', array_fill(0, count($emails), '?'));
        $pdo->prepare(
            "UPDATE users SET email_login_otp_enabled = 0 WHERE tenant_id = ? AND LOWER(email) IN ({$ph})"
        )->execute(array_merge([$tenantId], array_map('strtolower', $emails)));

        // Sécurité : tout @demo.local du tenant
        $pdo->prepare(
            "UPDATE users SET email_login_otp_enabled = 0 WHERE tenant_id = ? AND LOWER(email) LIKE '%@demo.local'"
        )->execute([$tenantId]);
    }

    $stTotp = $pdo->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'totp_enabled' LIMIT 1");
    if ($stTotp && $stTotp->fetchColumn()) {
        $pdo->prepare(
            "UPDATE users SET totp_enabled = 0, totp_secret = NULL, totp_confirmed_at = NULL
             WHERE tenant_id = ? AND LOWER(email) LIKE '%@demo.local'"
        )->execute([$tenantId]);
    }
}

/**
 * Fiches publiées pour peupler le mur / tableau opérationnel.
 *
 * @param array<string, int> $userIdsByEmail
 */
function demo_seed_operational_board(PDO $pdo, int $tenantId, int $authorId, array $userIdsByEmail): void
{
    $chk = $pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'planning_entries' LIMIT 1");
    if (!$chk || !$chk->fetchColumn()) {
        echo "[SKIP] Table planning_entries absente — mur opérationnel non peuplé\n";

        return;
    }

    /** @var \App\Repositories\PlanningEntryRepository $planning */
    $planning = Container::get(\App\Repositories\PlanningEntryRepository::class);
    if (!$planning->isOperationalBoardSchemaReady()) {
        echo "[SKIP] Schéma mur opérationnel incomplet\n";

        return;
    }

    $chiefId = (int) ($userIdsByEmail['cadre@demo.local'] ?? $authorId);
    $deputyId = (int) ($userIdsByEmail['operateur@demo.local'] ?? 0) ?: null;
    $today = date('Y-m-d');
    $specs = [
        [
            'title' => '[Démo] Permanence transmissions',
            'description' => 'Permanence radio pour la force démo. Point de contact RTO sur le net de section.',
            'entry_type' => 'permanence',
            'priority' => 'high',
            'operational_status' => 'in_progress',
            'operation_zone' => 'Net section / Discord RTO',
            'start_date' => $today,
            'end_date' => date('Y-m-d', strtotime('+2 days')),
        ],
        [
            'title' => '[Démo] Consigne tenue Training Camp',
            'description' => 'Tenue de campagne obligatoire pour les sessions Altis. Casque et lunettes selon règlement interne.',
            'entry_type' => 'info',
            'priority' => 'normal',
            'operational_status' => 'planned',
            'operation_zone' => 'Training Camp — Altis',
            'start_date' => $today,
            'end_date' => date('Y-m-d', strtotime('+14 days')),
        ],
        [
            'title' => '[Démo] Flash — Briefing reporté 15 min',
            'description' => 'Le briefing du créneau de ce soir est décalé de 15 minutes. Regroupement inchangé.',
            'entry_type' => 'flash_info',
            'priority' => 'critical',
            'operational_status' => 'in_progress',
            'operation_zone' => 'Serveur démo',
            'start_date' => $today,
            'end_date' => $today,
        ],
        [
            'title' => '[Démo] Mission reconnaissance secteur Nord',
            'description' => 'Reconnaissance légère avant manœuvre. Objectif : confirmer axes d’approche et points d’observation.',
            'entry_type' => 'mission',
            'priority' => 'normal',
            'operational_status' => 'planned',
            'operation_zone' => 'Secteur Nord — Altis',
            'start_date' => date('Y-m-d', strtotime('+3 days')),
            'end_date' => date('Y-m-d', strtotime('+3 days')),
        ],
    ];

    $created = 0;
    foreach ($specs as $spec) {
        $chkE = $pdo->prepare('SELECT id FROM planning_entries WHERE tenant_id = ? AND title = ? LIMIT 1');
        $chkE->execute([$tenantId, $spec['title']]);
        $existingId = (int) ($chkE->fetchColumn() ?: 0);
        if ($existingId > 0) {
            echo "  [SKIP] Mur « {$spec['title']} » (#{$existingId})\n";
            continue;
        }
        try {
            $id = $planning->create([
                'tenant_id' => $tenantId,
                'title' => $spec['title'],
                'description' => $spec['description'],
                'entry_type' => $spec['entry_type'],
                'category_id' => null,
                'linked_type' => null,
                'linked_id' => null,
                'start_date' => $spec['start_date'],
                'end_date' => $spec['end_date'],
                'all_day' => 1,
                'status' => 'active',
                'validation_status' => 'approved',
                'priority' => $spec['priority'],
                'display_order' => 50,
                'visibility_scope' => 'tenant',
                'security_level' => 'unit_public',
                'operational_status' => $spec['operational_status'],
                'phase_current' => 'phase_1',
                'created_by' => $authorId > 0 ? $authorId : $chiefId,
                'chief_user_id' => $chiefId > 0 ? $chiefId : null,
                'deputy_user_id' => $deputyId,
                'replacement_user_id' => null,
                'replacement_auto_activate' => 0,
                'command_chain' => null,
                'accountability_note' => null,
                'location_lat' => null,
                'location_lng' => null,
                'operation_zone' => $spec['operation_zone'],
                'map_link' => null,
                'dossier_ref' => null,
                'legal_constraints' => null,
                'fire_window_start' => null,
                'fire_window_end' => null,
            ]);
            if ($id > 0) {
                ++$created;
                echo "  [OK] Mur « {$spec['title']} » (#{$id})\n";
            }
        } catch (Throwable $e) {
            echo '  [ATTENTION] Mur « ' . $spec['title'] . ' » : ' . $e->getMessage() . "\n";
        }
    }
    echo "[OK] Mur opérationnel : {$created} fiche(s) publiée(s)\n";
}

/**
 * Résout un utilisateur créateur pour TenantBootstrapService.
 * Ordre : user actif quelconque → sinon tenant « default » (créé si besoin) + compte technique seed.
 * N’attribue jamais de rôle d’administration de la plateforme.
 */
function demo_resolve_creator_user_id(
    PDO $pdo,
    UserRepository $users,
    TenantRepository $tenants,
    RoleRepository $roles
): int {
    $row = $pdo->query(
        "SELECT id FROM users WHERE status = 'active' ORDER BY id ASC LIMIT 1"
    )->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        return (int) $row['id'];
    }

    // Aucun user : assurer un tenant d’accueil
    $home = $tenants->findBySlug('default');
    if (!$home) {
        $homeId = $tenants->create('Pas d’organisation', 'default', 'free');
        echo "[OK] Tenant d’accueil « default » créé (#{$homeId})\n";
    } else {
        $homeId = (int) $home['id'];
    }

    // Rôle minimal sur le tenant d’accueil
    $roleId = $roles->getIdBySlug($homeId, 'member');
    if ($roleId === null) {
        $roleId = $roles->getIdBySlug($homeId, 'tenant_admin');
    }
    if ($roleId === null) {
        $roleId = $roles->getIdBySlug($homeId, 'community_owner');
    }
    if ($roleId === null) {
        $pdo->prepare(
            "INSERT INTO roles (tenant_id, name, slug, description, is_system, is_locked, role_layer, created_at)
             VALUES (?, 'Opérateur', 'member', 'Rôle technique seed démo', 1, 0, 'intra', NOW())"
        )->execute([$homeId]);
        $roleId = (int) $pdo->lastInsertId();
        echo "[OK] Rôle member créé sur tenant #{$homeId}\n";
    }

    $bootstrapEmail = 'seed-bootstrap@demo.local';
    $existingBootstrap = $users->findByEmail($homeId, $bootstrapEmail);
    if ($existingBootstrap) {
        return (int) $existingBootstrap['id'];
    }

    $userId = $users->create($homeId, [
        'email' => $bootstrapEmail,
        'password_hash' => password_hash(DemoPortalAccounts::SHARED_PASSWORD, PASSWORD_ARGON2ID),
        'display_name' => 'Seed Bootstrap',
        'callsign' => 'SEED',
        'role_id' => $roleId,
        'status' => 'active',
    ]);
    try {
        $users->syncOrganizationRoles($userId, $homeId, [$roleId], null, true);
    } catch (Throwable) {
        // Cohérence optionnelle
    }
    demo_mark_email_verified($pdo, $userId);

    // S’assurer qu’il n’a pas de rôle site (au cas où)
    $chk = $pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'site_role_assignments' LIMIT 1");
    if ($chk && $chk->fetchColumn()) {
        $pdo->prepare(
            "UPDATE site_role_assignments SET revoked_at = NOW()
             WHERE revoked_at IS NULL AND email_normalized = ?"
        )->execute([strtolower($bootstrapEmail)]);
    }

    echo "[OK] Compte technique seed-bootstrap@demo.local créé (#{$userId}) — sans droits plateforme\n";

    return $userId;
}

/**
 * Révoque toute affectation de rôle plateforme pour les e-mails de démo.
 * Les comptes @demo.local restent strictement au niveau organisation.
 */
function demo_revoke_site_roles_for_demo_emails(PDO $pdo): void
{
    $chk = $pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'site_role_assignments' LIMIT 1");
    if (!$chk || !$chk->fetchColumn()) {
        return;
    }
    $emails = DemoPortalAccounts::allDemoEmails();
    $emails[] = 'seed-bootstrap@demo.local';
    $emails = array_values(array_unique($emails));
    $ph = implode(',', array_fill(0, count($emails), '?'));
    $normalized = array_map(static fn (string $e): string => strtolower(trim($e)), $emails);
    $st = $pdo->prepare(
        "UPDATE site_role_assignments SET revoked_at = NOW()
         WHERE revoked_at IS NULL AND email_normalized IN ({$ph})"
    );
    $st->execute($normalized);
}

/**
 * Assure une unité (par slug) sous un parent éventuel.
 */
function demo_ensure_unit(PDO $pdo, int $tenantId, ?int $parentId, string $name, string $slug, string $type, int $displayOrder = 0): int
{
    $chk = $pdo->prepare('SELECT id FROM units WHERE tenant_id = ? AND ' . SqlText::equals($pdo, 'slug') . ' LIMIT 1');
    $chk->execute([$tenantId, $slug]);
    $id = (int) ($chk->fetchColumn() ?: 0);
    if ($id > 0) {
        return $id;
    }
    $pdo->prepare(
        'INSERT INTO units (tenant_id, parent_id, name, slug, type, code, commander_user_id, display_order, updated_at)
         VALUES (?, ?, ?, ?, ?, NULL, NULL, ?, NOW())'
    )->execute([$tenantId, $parentId, $name, $slug, $type, $displayOrder]);

    return (int) $pdo->lastInsertId();
}

/**
 * Grades FR + ORBAT enrichi + affectations + matricules pour l’annuaire démo.
 *
 * @param array<string, int> $userIdsByEmail
 */
function demo_seed_grades_and_assignments(
    PDO $pdo,
    int $tenantId,
    array $userIdsByEmail,
    TenantRepository $tenants,
    UserRepository $users
): void {
    $chkUnits = $pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'units' LIMIT 1");
    if (!$chkUnits || !$chkUnits->fetchColumn()) {
        echo "[SKIP] Table units absente — pas d’affectations\n";

        return;
    }

    try {
        (new TenantOnboardingHealthService($tenants))->applyFrDefaults($tenantId);
    } catch (Throwable $e) {
        echo "  [ATTENTION] ORBAT de base : " . $e->getMessage() . "\n";
    }

    $tenants->mergeSettings($tenantId, [
        'grade_system_code' => 'FR_CLASSIC',
    ]);

    $hqId = demo_ensure_unit($pdo, $tenantId, null, 'État-major', 'etat-major', 'group', 0);
    $sectionId = demo_ensure_unit($pdo, $tenantId, $hqId, '1re section', '1re-section', 'section', 10);
    $teamId = demo_ensure_unit($pdo, $tenantId, $sectionId, '1re équipe', '1re-equipe', 'team', 0);
    $opsanId = demo_ensure_unit($pdo, $tenantId, $sectionId, 'Équipe OPSAN', 'equipe-opsan', 'team', 10);
    $rtoId = demo_ensure_unit($pdo, $tenantId, $sectionId, 'Équipe transmissions', 'equipe-rto', 'team', 20);
    $logId = demo_ensure_unit($pdo, $tenantId, $sectionId, 'Équipe logistique', 'equipe-log', 'team', 30);
    $rhId = demo_ensure_unit($pdo, $tenantId, $hqId, 'Bureau RH', 'bureau-rh', 'section', 20);
    $formId = demo_ensure_unit($pdo, $tenantId, $hqId, 'Pôle formation', 'pole-formation', 'section', 30);

    $unitsBySlug = [
        'etat-major' => $hqId,
        '1re-section' => $sectionId,
        '1re-equipe' => $teamId,
        'equipe-opsan' => $opsanId,
        'equipe-rto' => $rtoId,
        'equipe-log' => $logId,
        'bureau-rh' => $rhId,
        'pole-formation' => $formId,
    ];
    echo "[OK] ORBAT démo : État-major + section + équipes spécialisées\n";

    /** @var GradeRepository $gradeRepo */
    $gradeRepo = Container::get(GradeRepository::class);
    $gradeByCode = [];
    try {
        foreach ($gradeRepo->listBySystemCode('FR_CLASSIC') as $g) {
            $code = strtoupper(trim((string) ($g['code'] ?? '')));
            if ($code !== '') {
                $gradeByCode[$code] = (int) $g['id'];
            }
        }
    } catch (Throwable $e) {
        echo "  [ATTENTION] Grades FR : " . $e->getMessage() . "\n";
    }
    if ($gradeByCode === []) {
        echo "[SKIP] Aucun grade FR_CLASSIC en base — lancez les migrations référentiel grades\n";

        return;
    }

    /** @var PersonnelAssignmentRepository $assignments */
    $assignments = Container::get(PersonnelAssignmentRepository::class);
    /** @var MatriculeService $matricules */
    $matricules = Container::get(MatriculeService::class);

    // email => [grade_code|null, unit_slug|null, role_label]
    $roster = [
        'gestionnaire@demo.local' => ['CDT', 'etat-major', 'Commandant d’unité'],
        'admin-orga@demo.local' => ['CNE', 'etat-major', 'Adjoint administration'],
        'cadre@demo.local' => ['LT', '1re-section', 'Chef de section'],
        'rh@demo.local' => ['ADJ', 'bureau-rh', 'Ressources humaines'],
        'recruteur@demo.local' => ['SGT', 'bureau-rh', 'Recruteur'],
        'instructeur@demo.local' => ['SCH', 'pole-formation', 'Instructeur'],
        'formateur@demo.local' => ['SGT', 'pole-formation', 'Formateur'],
        'comms@demo.local' => ['CPL', 'etat-major', 'Communication'],
        'opsan@demo.local' => ['CCH', 'equipe-opsan', 'OPSAN'],
        'logistique@demo.local' => ['CPL', 'equipe-log', 'Logisticien'],
        'rto@demo.local' => ['CCH', 'equipe-rto', 'RTO'],
        'operateur@demo.local' => ['SD1', '1re-equipe', 'Opérateur'],
        'visiteur@demo.local' => [null, null, 'Visiteur'],
    ];

    $done = 0;
    foreach ($roster as $email => [$gradeCode, $unitSlug, $roleLabel]) {
        $userId = (int) ($userIdsByEmail[$email] ?? 0);
        if ($userId < 1) {
            $u = $users->findByEmail($tenantId, $email);
            $userId = $u ? (int) $u['id'] : 0;
        }
        if ($userId < 1) {
            continue;
        }

        $gradeId = null;
        if ($gradeCode !== null && $gradeCode !== '') {
            $gradeId = $gradeByCode[strtoupper($gradeCode)] ?? null;
            if ($gradeId === null) {
                echo "  [ATTENTION] Grade « {$gradeCode} » introuvable pour {$email}\n";
            }
        }

        try {
            $users->update($userId, $tenantId, [
                'grade_id' => $gradeId,
                'nationality_code' => 'FR',
                'preferred_grade_format' => 'hybrid',
            ]);
        } catch (Throwable $e) {
            // Colonnes optionnelles
            try {
                $pdo->prepare('UPDATE users SET grade_id = ? WHERE id = ? AND tenant_id = ?')
                    ->execute([$gradeId, $userId, $tenantId]);
            } catch (Throwable) {
                echo "  [ATTENTION] grade_id pour {$email} : " . $e->getMessage() . "\n";
            }
        }

        $unitId = ($unitSlug !== null && $unitSlug !== '') ? ($unitsBySlug[$unitSlug] ?? 0) : 0;
        try {
            $assignments->syncPrimaryAssignmentFromDossier(
                $userId,
                $unitId > 0 ? $unitId : null,
                $roleLabel
            );
        } catch (Throwable $e) {
            echo "  [ATTENTION] Affectation {$email} : " . $e->getMessage() . "\n";
        }

        try {
            $matricules->assignNextForUser($userId, $tenantId);
        } catch (Throwable) {
            // Config matricule absente : ignore
        }

        $gLabel = $gradeCode ?? '—';
        $uLabel = $unitSlug ?? 'non affecté';
        echo "  [OK] {$email} → {$gLabel} / {$uLabel}\n";
        ++$done;
    }

    // Propriétaire cloné (compte réel hors @demo.local) : affecté à l’état-major pour ne pas polluer « sans unité »
    try {
        $ownerStmt = $pdo->prepare(
            "SELECT id FROM users
             WHERE tenant_id = ? AND status = 'active'
               AND LOWER(email) NOT LIKE '%@demo.local'
             ORDER BY id ASC LIMIT 5"
        );
        $ownerStmt->execute([$tenantId]);
        $ownerIds = $ownerStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        foreach ($ownerIds as $oid) {
            $oid = (int) $oid;
            if ($oid < 1) {
                continue;
            }
            $assignments->syncPrimaryAssignmentFromDossier($oid, $hqId, 'Commandement (démo)');
            echo "  [OK] Compte réel #{$oid} affecté à l’état-major (clone propriétaire)\n";
        }
    } catch (Throwable $e) {
        echo "  [ATTENTION] Affectation propriétaire : " . $e->getMessage() . "\n";
    }

    // Identité civile seed (évite de fausses alertes « profil incomplet » en mode inscription simple)
    try {
        foreach ($roster as $email => [$gradeCode, $unitSlug, $roleLabel]) {
            $userId = (int) ($userIdsByEmail[$email] ?? 0);
            if ($userId < 1) {
                $u = $users->findByEmail($tenantId, $email);
                $userId = $u ? (int) $u['id'] : 0;
            }
            if ($userId < 1) {
                continue;
            }
            $uRow = $users->findById($userId, $tenantId);
            $dn = trim((string) ($uRow['display_name'] ?? ''));
            $parts = $dn !== '' ? preg_split('/\s+/u', $dn, 2, PREG_SPLIT_NO_EMPTY) : false;
            $fn = is_array($parts) && isset($parts[0]) ? $parts[0] : 'Démo';
            $ln = is_array($parts) && isset($parts[1]) ? $parts[1] : 'COMSPEC';
            $pdo->prepare(
                'INSERT INTO user_profiles (user_id, first_name, last_name, created_at, updated_at)
                 VALUES (?, ?, ?, NOW(), NOW())
                 ON DUPLICATE KEY UPDATE
                   first_name = IF(NULLIF(TRIM(first_name), \'\') IS NULL, VALUES(first_name), first_name),
                   last_name = IF(NULLIF(TRIM(last_name), \'\') IS NULL, VALUES(last_name), last_name),
                   updated_at = NOW()'
            )->execute([$userId, $fn, $ln]);
        }
        echo "[OK] Identités civiles renseignées sur les comptes @demo.local\n";
    } catch (Throwable $e) {
        echo "  [ATTENTION] user_profiles démo : " . $e->getMessage() . "\n";
    }

    // Commandants d’unité pour l’ORBAT
    $cmdHq = (int) ($userIdsByEmail['gestionnaire@demo.local'] ?? 0);
    $cmdSec = (int) ($userIdsByEmail['cadre@demo.local'] ?? 0);
    if ($cmdHq > 0) {
        $pdo->prepare('UPDATE units SET commander_user_id = ? WHERE id = ? AND tenant_id = ?')
            ->execute([$cmdHq, $hqId, $tenantId]);
    }
    if ($cmdSec > 0) {
        $pdo->prepare('UPDATE units SET commander_user_id = ? WHERE id = ? AND tenant_id = ?')
            ->execute([$cmdSec, $sectionId, $tenantId]);
    }

    echo "[OK] Grades & affectations : {$done} profils synchronisés\n";
}

/**
 * Inscriptions + progression LMS pour remplir le tableau de bord.
 *
 * @param array<string, int> $userIdsByEmail
 */
function demo_seed_training_progress(PDO $pdo, int $tenantId, int $authorId, array $userIdsByEmail): void
{
    $chk = $pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'training_enrollments' LIMIT 1");
    if (!$chk || !$chk->fetchColumn()) {
        echo "[SKIP] Tables LMS absentes — pas d’avancement formation\n";

        return;
    }

    /** @var TrainingCourseRepository $courses */
    $courses = Container::get(TrainingCourseRepository::class);
    /** @var TrainingEnrollmentRepository $enrollments */
    $enrollments = Container::get(TrainingEnrollmentRepository::class);
    /** @var TrainingProgressRepository $progress */
    $progress = Container::get(TrainingProgressRepository::class);
    /** @var TrainingService $training */
    $training = Container::get(TrainingService::class);

    $courseIds = [];
    foreach (['demo-briefing-5min', 'demo-securite-radio', 'demo-accueil-nouveau'] as $slug) {
        $row = $courses->findBySlug($slug, $tenantId);
        if ($row) {
            $courseIds[$slug] = (int) $row['id'];
        }
    }
    if ($courseIds === []) {
        echo "[SKIP] Aucune formation démo trouvée — avancement non créé\n";

        return;
    }

    $scenarios = [
        // Gestionnaire : formations en cours visibles sur le dashboard
        [
            'email' => 'gestionnaire@demo.local',
            'slug' => 'demo-accueil-nouveau',
            'status' => 'in_progress',
            'complete_lessons' => 0,
            'partial_first' => true,
        ],
        [
            'email' => 'gestionnaire@demo.local',
            'slug' => 'demo-securite-radio',
            'status' => 'in_progress',
            'complete_lessons' => 1,
        ],
        [
            'email' => 'gestionnaire@demo.local',
            'slug' => 'demo-briefing-5min',
            'status' => 'assigned',
            'complete_lessons' => 0,
        ],
        [
            'email' => 'operateur@demo.local',
            'slug' => 'demo-briefing-5min',
            'status' => 'completed',
            'complete_lessons' => 999,
        ],
        [
            'email' => 'operateur@demo.local',
            'slug' => 'demo-securite-radio',
            'status' => 'in_progress',
            'complete_lessons' => 1,
        ],
        [
            'email' => 'operateur@demo.local',
            'slug' => 'demo-accueil-nouveau',
            'status' => 'in_progress',
            'complete_lessons' => 0,
            'partial_first' => true,
        ],
        [
            'email' => 'visiteur@demo.local',
            'slug' => 'demo-accueil-nouveau',
            'status' => 'assigned',
            'complete_lessons' => 0,
        ],
        [
            'email' => 'cadre@demo.local',
            'slug' => 'demo-briefing-5min',
            'status' => 'completed',
            'complete_lessons' => 999,
        ],
        [
            'email' => 'cadre@demo.local',
            'slug' => 'demo-securite-radio',
            'status' => 'assigned',
            'complete_lessons' => 0,
        ],
        [
            'email' => 'instructeur@demo.local',
            'slug' => 'demo-briefing-5min',
            'status' => 'completed',
            'complete_lessons' => 999,
        ],
        [
            'email' => 'instructeur@demo.local',
            'slug' => 'demo-securite-radio',
            'status' => 'completed',
            'complete_lessons' => 999,
        ],
        [
            'email' => 'instructeur@demo.local',
            'slug' => 'demo-accueil-nouveau',
            'status' => 'completed',
            'complete_lessons' => 999,
        ],
        [
            'email' => 'admin-orga@demo.local',
            'slug' => 'demo-accueil-nouveau',
            'status' => 'in_progress',
            'complete_lessons' => 0,
            'partial_first' => true,
        ],
        [
            'email' => 'rto@demo.local',
            'slug' => 'demo-securite-radio',
            'status' => 'in_progress',
            'complete_lessons' => 1,
        ],
    ];

    $done = 0;
    foreach ($scenarios as $sc) {
        $email = $sc['email'];
        $slug = $sc['slug'];
        $userId = (int) ($userIdsByEmail[$email] ?? 0);
        $courseId = (int) ($courseIds[$slug] ?? 0);
        if ($userId < 1 || $courseId < 1) {
            continue;
        }

        $existing = $enrollments->findByCourseAndUser($courseId, $userId);
        if ($existing) {
            $enrollmentId = (int) $existing['id'];
        } else {
            $enrollmentId = $enrollments->create($tenantId, [
                'course_id' => $courseId,
                'user_id' => $userId,
                'assigned_by' => $authorId > 0 ? $authorId : null,
                'assignment_type' => 'manual',
                'status' => 'assigned',
            ]);
        }

        $lessonIds = $training->getCourseLessonIds($courseId);
        if ($lessonIds === []) {
            continue;
        }
        $progress->initForEnrollment($enrollmentId, $lessonIds);

        $toComplete = (int) ($sc['complete_lessons'] ?? 0);
        if ($toComplete >= count($lessonIds)) {
            $toComplete = count($lessonIds);
        }

        $now = date('Y-m-d H:i:s');
        $started = date('Y-m-d H:i:s', strtotime('-3 days'));
        foreach ($lessonIds as $i => $lessonId) {
            if ($i < $toComplete) {
                $progress->upsert($enrollmentId, $lessonId, [
                    'status' => 'completed',
                    'progress_percent' => 100,
                    'time_spent_seconds' => 120 + ($i * 45),
                    'viewed_at' => $started,
                    'completed_at' => date('Y-m-d H:i:s', strtotime('-' . (2 - min(2, $i)) . ' days')),
                ]);
            } elseif (!empty($sc['partial_first']) && $i === $toComplete) {
                $progress->upsert($enrollmentId, $lessonId, [
                    'status' => 'in_progress',
                    'progress_percent' => 35,
                    'time_spent_seconds' => 90,
                    'viewed_at' => $now,
                    'completed_at' => null,
                ]);
            } else {
                $progress->upsert($enrollmentId, $lessonId, [
                    'status' => 'not_started',
                    'progress_percent' => 0,
                    'time_spent_seconds' => 0,
                    'viewed_at' => null,
                    'completed_at' => null,
                ]);
            }
        }

        $status = (string) $sc['status'];
        $upd = ['status' => $status];
        if ($status === 'completed') {
            $upd['started_at'] = $started;
            $upd['completed_at'] = date('Y-m-d H:i:s', strtotime('-1 day'));
        } elseif ($status === 'in_progress') {
            $upd['started_at'] = $started;
            $upd['completed_at'] = null;
        } else {
            $upd['started_at'] = null;
            $upd['completed_at'] = null;
        }
        // expires_at pour rendre certaines lignes « prioritaires »
        if ($slug === 'demo-accueil-nouveau' && $status !== 'completed') {
            try {
                $pdo->prepare('UPDATE training_enrollments SET expires_at = ? WHERE id = ?')
                    ->execute([date('Y-m-d H:i:s', strtotime('+10 days')), $enrollmentId]);
            } catch (Throwable) {
            }
        }
        $enrollments->update($enrollmentId, $upd);
        ++$done;
        echo "  [OK] LMS {$email} → {$slug} ({$status})\n";
    }
    echo "[OK] Avancements formation : {$done} inscriptions synchronisées\n";
}

/**
 * Offres publiées + candidatures (pipeline recrutement).
 *
 * @param array<string, int> $userIdsByEmail
 */
function demo_seed_recruitment(PDO $pdo, int $tenantId, int $authorId, array $userIdsByEmail, TenantRepository $tenants): void
{
    $chk = $pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'recruitment_openings' LIMIT 1");
    if (!$chk || !$chk->fetchColumn()) {
        echo "[SKIP] Table recruitment_openings absente\n";

        return;
    }
    $chkE = $pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'enlistments' LIMIT 1");
    if (!$chkE || !$chkE->fetchColumn()) {
        echo "[SKIP] Table enlistments absente\n";

        return;
    }

    try {
        (new TenantOnboardingHealthService($tenants))->applyFrDefaults($tenantId);
    } catch (Throwable $e) {
        echo "  [ATTENTION] Unités ORBAT : " . $e->getMessage() . "\n";
    }

    /** @var UnitRepository $units */
    $units = Container::get(UnitRepository::class);
    $allUnits = $units->allForTenant($tenantId);
    if ($allUnits === []) {
        echo "[SKIP] Aucune unité ORBAT — offres non créées\n";

        return;
    }
    $unit = $allUnits[0];
    foreach ($allUnits as $u) {
        if (($u['type'] ?? '') === 'team' || ($u['type'] ?? '') === 'section') {
            $unit = $u;
            break;
        }
    }
    $unitId = (int) $unit['id'];

    /** @var RecruitmentOpeningRepository $openings */
    $openings = Container::get(RecruitmentOpeningRepository::class);
    /** @var EnlistmentRepository $enlistments */
    $enlistments = Container::get(EnlistmentRepository::class);

    $recruiterId = (int) ($userIdsByEmail['recruteur@demo.local'] ?? $authorId);
    $publisherId = $recruiterId > 0 ? $recruiterId : $authorId;
    $tenant = $tenants->findById($tenantId) ?? ['id' => $tenantId, 'name' => DEMO_TENANT_NAME, 'slug' => DEMO_TENANT_SLUG];
    $settings = $tenants->getSettings($tenantId);

    $offerSpecs = [
        [
            'title' => '[Démo] Opérateur transmissions',
            'summary' => 'Poste fictif pour illustrer une offre publiée et le portail candidatures.',
            'description' => "Mission démo : assurer les liaisons radio d’équipe, tenir le journal des fréquences et appuyer le RTO.\n\nProfil recherché (fictif) : discipline réseau, disponibilité soirées, expérience milsim appréciée.",
            'personnel_category' => 'other',
            'arm_domain' => 'transmissions',
            'clearance_level' => 'none',
            'employment_contract_label' => 'Bénévole / milsim',
            'employment_context_label' => 'Opérations régulières',
        ],
        [
            'title' => '[Démo] Infirmier de combat',
            'summary' => 'Renfort OPSAN pour les manœuvres — offre de démonstration.',
            'description' => "Poste fictif : prise en charge des blessés en phase d’exercice, liaison avec le chef d’équipe, tenue des kits médicaux.\n\nFormation interne proposée après intégration.",
            'personnel_category' => 'other',
            'arm_domain' => 'sante',
            'clearance_level' => 'none',
            'employment_contract_label' => 'Bénévole / milsim',
            'employment_context_label' => 'Week-ends opérationnels',
        ],
        [
            'title' => '[Démo] Cadre de section',
            'summary' => 'Encadrement d’une section — scénario démo recrutement.',
            'description' => "Offre fictive de cadre : préparation des briefings, suivi des effectifs, coordination avec l’état-major de démonstration.",
            'personnel_category' => 'other',
            'arm_domain' => 'commandement',
            'clearance_level' => 'none',
            'employment_contract_label' => 'Bénévole / milsim',
            'employment_context_label' => 'Encadrement continu',
        ],
    ];

    $openingIdsByTitle = [];
    foreach ($offerSpecs as $spec) {
        $chkO = $pdo->prepare('SELECT id, status FROM recruitment_openings WHERE tenant_id = ? AND title = ? LIMIT 1');
        $chkO->execute([$tenantId, $spec['title']]);
        $exist = $chkO->fetch(PDO::FETCH_ASSOC);
        if ($exist) {
            $oid = (int) $exist['id'];
            if (($exist['status'] ?? '') === 'draft') {
                $openings->publish($oid, $tenantId, $tenant, $settings, $unit);
                echo "  [OK] Offre publiée « {$spec['title']} » (#{$oid})\n";
            } else {
                echo "  [SKIP] Offre « {$spec['title']} » (#{$oid})\n";
            }
            $openingIdsByTitle[$spec['title']] = $oid;
            continue;
        }
        if ($publisherId < 1) {
            echo "  [ATTENTION] Pas d’auteur pour l’offre « {$spec['title']} »\n";
            continue;
        }
        $oid = $openings->create($tenantId, $publisherId, [
            'unit_id' => $unitId,
            'title' => $spec['title'],
            'summary' => $spec['summary'],
            'description' => $spec['description'],
            'personnel_category' => $spec['personnel_category'],
            'arm_domain' => $spec['arm_domain'],
            'clearance_level' => $spec['clearance_level'],
            'employment_contract_label' => $spec['employment_contract_label'],
            'employment_context_label' => $spec['employment_context_label'],
            'candidate_profile_items' => [
                'Disponible en soirée et week-end',
                'À l’aise avec Discord / TeamSpeak',
                'Motivation pour le jeu d’équipe',
            ],
            'mission_lead' => 'Appuyer la force démo sur le terrain fictif Altis.',
            'responsibility_blocks' => [
                ['title' => 'Sur le terrain', 'items' => ['Exécuter les consignes de section', 'Remonter les incidents']],
                ['title' => 'Hors session', 'items' => ['Lire les briefings', 'Participer au forum']],
            ],
        ]);
        $openings->publish($oid, $tenantId, $tenant, $settings, $unit);
        $openingIdsByTitle[$spec['title']] = $oid;
        echo "  [OK] Offre créée & publiée « {$spec['title']} » (#{$oid})\n";
    }

    $candidates = [
        [
            'email' => 'candidat.radio@demo.local',
            'first_name' => 'Léa',
            'last_name' => 'Martin',
            'callsign' => 'CAND-RTO',
            'specialty' => 'Transmissions',
            'opening' => '[Démo] Opérateur transmissions',
            'status' => 'submitted',
            'notes' => '[' . DEMO_CAMPAIGN_TAG . '] Motivée par le rôle RTO, déjà joué Arma 3.',
            'experience' => '2 ans milsim',
            'availability' => 'Soirs + samedi',
        ],
        [
            'email' => 'candidat.opsan@demo.local',
            'first_name' => 'Noah',
            'last_name' => 'Bernard',
            'callsign' => 'CAND-SAN',
            'specialty' => 'Médical',
            'opening' => '[Démo] Infirmier de combat',
            'status' => 'reviewed',
            'notes' => '[' . DEMO_CAMPAIGN_TAG . '] Profil OPSAN, PSC1 déclaré.',
            'experience' => 'PSC1 + 1 an milsim',
            'availability' => 'Week-ends',
            'review_comment' => 'Dossier cohérent — à convoquer pour un entretien démo.',
        ],
        [
            'email' => 'candidat.cadre@demo.local',
            'first_name' => 'Inès',
            'last_name' => 'Dupont',
            'callsign' => 'CAND-CDR',
            'specialty' => 'Encadrement',
            'opening' => '[Démo] Cadre de section',
            'status' => 'rejected',
            'notes' => '[' . DEMO_CAMPAIGN_TAG . '] Expérience cadre déclarée, créneaux limités.',
            'experience' => '3 ans encadrement',
            'availability' => '1 soir / semaine',
            'review_comment' => 'Disponibilité insuffisante pour le scénario démo — refus pédagogique.',
        ],
        [
            'email' => 'candidat.assaut@demo.local',
            'first_name' => 'Hugo',
            'last_name' => 'Moreau',
            'callsign' => 'CAND-ASL',
            'specialty' => 'Assaut',
            'opening' => '[Démo] Opérateur transmissions',
            'status' => 'submitted',
            'notes' => '[' . DEMO_CAMPAIGN_TAG . '] Souhaite plutôt l’assaut mais a candidaté sur transmissions pour la démo.',
            'experience' => 'Débutant motivé',
            'availability' => 'Flexible',
        ],
        [
            'email' => 'candidat.reserve@demo.local',
            'first_name' => 'Chloé',
            'last_name' => 'Petit',
            'callsign' => 'CAND-RSV',
            'specialty' => 'Logistique',
            'opening' => '[Démo] Infirmier de combat',
            'status' => 'submitted',
            'notes' => '[' . DEMO_CAMPAIGN_TAG . '] Intérêt logistique / médical, dossier en attente.',
            'experience' => 'Aucun',
            'availability' => 'Dimanches',
        ],
    ];

    $candDone = 0;
    foreach ($candidates as $c) {
        $openingId = (int) ($openingIdsByTitle[$c['opening']] ?? 0);
        if ($openingId < 1) {
            continue;
        }
        $email = strtolower((string) $c['email']);
        $chkC = $pdo->prepare(
            'SELECT id, status FROM enlistments WHERE tenant_id = ? AND LOWER(email) = ? AND recruitment_opening_id = ? LIMIT 1'
        );
        try {
            $chkC->execute([$tenantId, $email, $openingId]);
        } catch (Throwable) {
            $chkC = $pdo->prepare('SELECT id, status FROM enlistments WHERE tenant_id = ? AND LOWER(email) = ? LIMIT 1');
            $chkC->execute([$tenantId, $email]);
        }
        $existC = $chkC->fetch(PDO::FETCH_ASSOC);
        if ($existC) {
            echo "  [SKIP] Candidature {$email} (#{$existC['id']})\n";
            continue;
        }

        $eid = $enlistments->create($tenantId, [
            'first_name' => $c['first_name'],
            'last_name' => $c['last_name'],
            'email' => $c['email'],
            'callsign' => $c['callsign'],
            'country' => 'FR',
            'experience' => $c['experience'] ?? null,
            'specialty' => $c['specialty'] ?? null,
            'platform' => 'PC — Arma 3',
            'availability' => $c['availability'] ?? null,
            'notes' => $c['notes'] ?? null,
            'status' => 'submitted',
            'submitted_via' => 'guest',
            'recruitment_opening_id' => $openingId,
        ]);
        if ($eid < 1) {
            continue;
        }
        if ($recruiterId > 0) {
            $enlistments->assignReferent($tenantId, $eid, $recruiterId);
        }
        $want = (string) ($c['status'] ?? 'submitted');
        if ($want !== 'submitted' && $recruiterId > 0) {
            $enlistments->applyDecision(
                $tenantId,
                $eid,
                $want,
                $recruiterId,
                $c['review_comment'] ?? null
            );
        }
        ++$candDone;
        echo "  [OK] Candidature {$c['first_name']} {$c['last_name']} → {$want}\n";
    }
    echo "[OK] Recrutement : " . count($openingIdsByTitle) . " offres, {$candDone} candidatures créées\n";
}

/**
 * Annonces démo : alerte communauté, mur recruteurs, sujet forum.
 *
 * @param array<string, int> $userIdsByEmail
 */
function demo_seed_announcements(
    PDO $pdo,
    int $tenantId,
    int $authorId,
    array $userIdsByEmail,
    TenantAlertRepository $tenantAlerts
): void {
    // --- Alertes communauté (annonces portail) ---
    $extraAlerts = [
        [
            'kind' => 'info',
            'title' => 'Annonce : session d’accueil vendredi',
            'body' => 'Rendez-vous fictif pour les nouveaux arrivants. Présentation du portail, du forum et des formations flash.',
            'cta_label' => 'Voir les événements',
            'cta_url' => 'evenements',
            'sort_order' => 20,
            'icon_key' => 'info',
            'display_style' => 'classic',
        ],
        [
            'kind' => 'info',
            'title' => 'Annonce : postes ouverts au recrutement',
            'body' => 'Trois offres de démonstration sont publiées (transmissions, OPSAN, cadre). Parcours candidat disponible pour la présentation.',
            'cta_label' => 'Espace recrutement',
            'cta_url' => 'back-office/recruitments',
            'sort_order' => 30,
            'icon_key' => 'info',
            'display_style' => 'classic',
        ],
    ];
    foreach ($extraAlerts as $alert) {
        $chk = $pdo->prepare('SELECT id FROM tenant_alerts WHERE tenant_id = ? AND title = ? LIMIT 1');
        $chk->execute([$tenantId, $alert['title']]);
        $existingId = (int) ($chk->fetchColumn() ?: 0);
        $payload = [
            'kind' => $alert['kind'],
            'title' => $alert['title'],
            'body' => $alert['body'],
            'cta_label' => $alert['cta_label'],
            'cta_url' => $alert['cta_url'],
            'starts_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'ends_at' => date('Y-m-d H:i:s', strtotime('+60 days')),
            'sort_order' => $alert['sort_order'],
            'is_active' => 1,
            'icon_key' => $alert['icon_key'],
            'display_style' => $alert['display_style'],
        ];
        if ($existingId > 0) {
            $tenantAlerts->update($existingId, $tenantId, $payload);
            echo "  [OK] Annonce portail réactivée (#{$existingId})\n";
        } else {
            $id = $tenantAlerts->insert($tenantId, $payload);
            echo "  [OK] Annonce portail « {$alert['title']} » (#{$id})\n";
        }
    }

    // --- Mur équipe recrutement ---
    $wallChk = $pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'recruitment_team_wall_entries' LIMIT 1");
    if ($wallChk && $wallChk->fetchColumn()) {
        /** @var RecruitmentTeamWallRepository $wall */
        $wall = Container::get(RecruitmentTeamWallRepository::class);
        $actor = (int) ($userIdsByEmail['recruteur@demo.local'] ?? $authorId);
        if ($actor > 0) {
            $wallPosts = [
                [
                    'subject' => 'Priorité démo : transmissions',
                    'body' => 'Pour la présentation : traiter en premier les candidatures « Opérateur transmissions ». Les dossiers OPSAN peuvent attendre le second tour.',
                ],
                [
                    'subject' => 'Créneaux d’entretien fictifs',
                    'body' => 'Proposés samedi 14h–16h (heure locale démo). Noter les retours dans le dossier candidat.',
                ],
            ];
            foreach ($wallPosts as $wp) {
                $chkW = $pdo->prepare(
                    'SELECT id FROM recruitment_team_wall_entries WHERE tenant_id = ? AND subject = ? LIMIT 1'
                );
                try {
                    $chkW->execute([$tenantId, $wp['subject']]);
                    if ($chkW->fetchColumn()) {
                        echo "  [SKIP] Mur recruteurs « {$wp['subject']} »\n";
                        continue;
                    }
                } catch (Throwable) {
                    // Colonne subject absente : insert quand même (risque de doublon faible)
                }
                $wall->create($tenantId, $actor, $wp['body'], 'annonce', $wp['subject']);
                echo "  [OK] Annonce mur recruteurs « {$wp['subject']} »\n";
            }
        }
    }

    // --- Sujet forum (annonce) ---
    $forumChk = $pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'forum_topics' LIMIT 1");
    if (!$forumChk || !$forumChk->fetchColumn()) {
        echo "[OK] Annonces portail / mur synchronisées\n";

        return;
    }
    try {
        /** @var ForumCategoryRepository $cats */
        $cats = Container::get(ForumCategoryRepository::class);
        /** @var ForumTopicRepository $topics */
        $topics = Container::get(ForumTopicRepository::class);
        /** @var ForumPostRepository $posts */
        $posts = Container::get(ForumPostRepository::class);
        $root = $cats->findOrganizationRoot($tenantId);
        if (!$root) {
            $list = $cats->listForTenant($tenantId);
            $root = $list[0] ?? null;
        }
        if (!$root || $authorId < 1) {
            echo "[OK] Annonces (forum ignoré — pas de rubrique)\n";

            return;
        }
        $catId = (int) $root['id'];
        $title = '[Démo] Annonce — ouverture des postes';
        $slug = 'demo-annonce-ouverture-postes';
        $chkT = $pdo->prepare('SELECT id FROM forum_topics WHERE tenant_id = ? AND ' . SqlText::equals($pdo, 'slug') . ' LIMIT 1');
        $chkT->execute([$tenantId, $slug]);
        if ($chkT->fetchColumn()) {
            echo "  [SKIP] Sujet forum « {$title} »\n";
        } else {
            $topicId = $topics->create($tenantId, $catId, $authorId, $title, $slug, [
                'mandatory_read' => false,
            ]);
            $posts->create(
                $tenantId,
                $topicId,
                $authorId,
                "Bonjour à toutes et tous,\n\n"
                . "Pour la démonstration du portail, trois postes fictifs sont ouverts au recrutement "
                . "(transmissions, OPSAN, cadre de section).\n\n"
                . "Les candidatures de démo sont déjà dans le pipeline pour illustrer l’instruction des dossiers.\n\n"
                . "— État-major Force Démo COMSPEC"
            );
            // Pin si colonne dispo
            try {
                $pdo->prepare('UPDATE forum_topics SET is_pinned = 1 WHERE id = ? AND tenant_id = ?')
                    ->execute([$topicId, $tenantId]);
            } catch (Throwable) {
            }
            echo "  [OK] Annonce forum « {$title} » (#{$topicId})\n";
        }
    } catch (Throwable $e) {
        echo "  [ATTENTION] Forum annonce : " . $e->getMessage() . "\n";
    }
    echo "[OK] Annonces démo synchronisées\n";
}

/**
 * Seed situation tactique fake (ATAK + Overwatch / piliers C2) sur Altis.
 * Idempotent : marqueurs DEMO-*, unités DEMO-*, zones / formes préfixées.
 */
function demo_seed_atak_overwatch(PDO $pdo, int $tenantId): void
{
    echo "\n--- ATAK / Overwatch ---\n";

    $chk = $pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'atak_units' LIMIT 1");
    if (!$chk || !$chk->fetchColumn()) {
        echo "[ATTENTION] Tables ATAK absentes — seed tactique ignoré (lancez setup-database.php).\n";

        return;
    }

    /** @var AtakMapRepository $maps */
    $maps = Container::get(AtakMapRepository::class);
    /** @var TenantAtakConfigRepository $atakConfig */
    $atakConfig = Container::get(TenantAtakConfigRepository::class);
    /** @var AtakDataRepository $atak */
    $atak = Container::get(AtakDataRepository::class);
    /** @var MapShapeRepository $shapes */
    $shapes = Container::get(MapShapeRepository::class);
    /** @var FireUnitRepository $fireUnits */
    $fireUnits = Container::get(FireUnitRepository::class);
    /** @var DangerZoneRepository $dangerZones */
    $dangerZones = Container::get(DangerZoneRepository::class);
    /** @var IntelReportRepository $intel */
    $intel = Container::get(IntelReportRepository::class);
    /** @var AssetLogisticsRepository $logistics */
    $logistics = Container::get(AssetLogisticsRepository::class);
    /** @var IffChallengeRepository $iffChallenges */
    $iffChallenges = Container::get(IffChallengeRepository::class);
    /** @var IffAssetStatusRepository $iffAssets */
    $iffAssets = Container::get(IffAssetStatusRepository::class);
    /** @var ReplayRepository $replay */
    $replay = Container::get(ReplayRepository::class);

    $altis = $maps->getBySlug('altis');
    if (!$altis) {
        echo "[ATTENTION] Carte Altis introuvable — seed tactique ignoré.\n";

        return;
    }
    $mapId = (int) $altis['id'];
    $missionId = 'mission_' . $tenantId . '_map_' . $mapId;

    $atakConfig->createOrUpdate($tenantId, [
        'default_map_slug' => 'altis',
        'instructions' => "Démonstration ATAK / Overwatch — opération fictive « Nightfall » sur Altis.\n"
            . "Unités DEMO-* positionnées autour du centre. Ouvrez /atak ou /overwatch après connexion à l’entité démo.",
        'arma_server_host' => '127.0.0.1',
        'arma_server_port' => 2302,
    ]);
    echo "[OK] Config ATAK tenant (carte Altis, mission {$missionId})\n";

    // Couche marqueurs
    $layers = $atak->getLayers($tenantId, $mapId);
    if ($layers === []) {
        $pdo->prepare(
            'INSERT INTO atak_layers (tenant_id, map_id, label, phase, `order`, created_at) VALUES (?, ?, ?, 1, 0, NOW())'
        )->execute([$tenantId, $mapId, 'Démo — Phase 1']);
        $layerId = (int) $pdo->lastInsertId();
        echo "  [OK] Couche marqueurs #{$layerId}\n";
    } else {
        $layerId = (int) $layers[0]['id'];
        echo "  [SKIP] Couche marqueurs déjà présente (#{$layerId})\n";
    }

    // Marqueurs tactiques
    $markers = [
        ['arma' => 'DEMO-OBJ-ALPHA', 'pos' => [14850, 16280], 'text' => 'OBJ ALPHA'],
        ['arma' => 'DEMO-OBJ-BRAVO', 'pos' => [15620, 16940], 'text' => 'OBJ BRAVO'],
        ['arma' => 'DEMO-HLZ-NORD', 'pos' => [14120, 17100], 'text' => 'HLZ NORD'],
        ['arma' => 'DEMO-CCP', 'pos' => [14580, 15840], 'text' => 'CCP'],
        ['arma' => 'DEMO-RP', 'pos' => [14300, 15500], 'text' => 'RP ASSAUT'],
    ];
    $createdMarkers = 0;
    foreach ($markers as $m) {
        $data = json_encode([
            'pos' => $m['pos'],
            'text' => $m['text'],
            'label' => $m['text'],
            'demo' => true,
        ], JSON_UNESCAPED_UNICODE);
        $before = $pdo->prepare('SELECT id FROM atak_markers WHERE tenant_id = ? AND map_id = ? AND arma_name = ? LIMIT 1');
        $before->execute([$tenantId, $mapId, $m['arma']]);
        $existed = (bool) $before->fetchColumn();
        $atak->upsertMarkerByArmaName($tenantId, $mapId, $layerId, $m['arma'], $data);
        if (!$existed) {
            ++$createdMarkers;
        }
    }
    echo "[OK] Marqueurs : {$createdMarkers} créés / " . count($markers) . " synchronisés\n";

    // Unités amies (alignées callsigns comptes démo)
    $units = [
        ['cs' => 'CMD-DEMO', 'x' => 14680.0, 'y' => 16040.0, 'h' => 45.0, 'role' => 'Commandement', 'extra' => ['health' => 'ok', 'fuel' => 90, 'radio_freq' => '45.5', 'side' => 'WEST', 'affiliation' => 'FRIEND', 'demo' => true]],
        ['cs' => 'ALPHA-1', 'x' => 14820.0, 'y' => 16180.0, 'h' => 55.0, 'role' => 'Groupe assaut', 'extra' => ['health' => 'ok', 'fuel' => 78, 'ammo' => '5.56×210', 'radio_freq' => '45.5', 'side' => 'WEST', 'affiliation' => 'FRIEND', 'demo' => true]],
        ['cs' => 'BRAVO-1', 'x' => 15140.0, 'y' => 15920.0, 'h' => 30.0, 'role' => 'Appui feu', 'extra' => ['health' => 'ok', 'fuel' => 65, 'ammo' => '7.62×120', 'radio_freq' => '45.5', 'side' => 'WEST', 'affiliation' => 'FRIEND', 'demo' => true]],
        ['cs' => 'SAN-DEMO', 'x' => 14620.0, 'y' => 15890.0, 'h' => 20.0, 'role' => 'OPSAN', 'extra' => ['health' => 'ok', 'fuel' => 88, 'radio_freq' => '46.0', 'side' => 'WEST', 'affiliation' => 'FRIEND', 'demo' => true]],
        ['cs' => 'R2-DEMO', 'x' => 14740.0, 'y' => 16010.0, 'h' => 40.0, 'role' => 'Transmissions', 'extra' => ['health' => 'ok', 'fuel' => 92, 'radio_freq' => '45.5', 'side' => 'WEST', 'affiliation' => 'FRIEND', 'demo' => true]],
        ['cs' => 'LOG-DEMO', 'x' => 14480.0, 'y' => 15680.0, 'h' => 10.0, 'role' => 'Logistique', 'extra' => ['health' => 'ok', 'fuel' => 54, 'ammo' => 'ravitaillement', 'radio_freq' => '45.0', 'side' => 'WEST', 'affiliation' => 'FRIEND', 'demo' => true]],
        ['cs' => 'OPS-DEMO', 'x' => 14910.0, 'y' => 16240.0, 'h' => 60.0, 'role' => 'Opérateur', 'extra' => ['health' => 'ok', 'fuel' => 70, 'ammo' => '5.56×120', 'radio_freq' => '45.5', 'side' => 'WEST', 'affiliation' => 'FRIEND', 'demo' => true]],
        ['cs' => 'HOSTILE-1', 'x' => 15780.0, 'y' => 17020.0, 'h' => 220.0, 'role' => 'Contact ennemi (sim)', 'extra' => ['health' => 'unknown', 'side' => 'EAST', 'affiliation' => 'ENEMY', 'demo' => true]],
        ['cs' => 'HOSTILE-2', 'x' => 15940.0, 'y' => 16880.0, 'h' => 180.0, 'role' => 'Contact ennemi (sim)', 'extra' => ['health' => 'unknown', 'side' => 'EAST', 'affiliation' => 'ENEMY', 'demo' => true]],
    ];
    foreach ($units as $u) {
        $atak->upsertUnitPosition(
            $tenantId,
            $mapId,
            $u['cs'],
            $u['x'],
            $u['y'],
            $u['h'],
            $u['role'],
            json_encode($u['extra'], JSON_UNESCAPED_UNICODE)
        );
    }
    echo "[OK] Unités tactiques : " . count($units) . " synchronisées\n";

    // Chat + pings (une seule fois)
    $chatProbe = $pdo->prepare(
        "SELECT id FROM atak_chat_messages WHERE tenant_id = ? AND map_id = ? AND body LIKE ? LIMIT 1"
    );
    $chatProbe->execute([$tenantId, $mapId, '%[Démo]%']);
    if ($chatProbe->fetchColumn()) {
        echo "[SKIP] Chat / pings déjà présents\n";
    } else {
        $atak->addChatMessage($tenantId, $mapId, 'CMD-DEMO', '[Démo] Brief Nightfall : ALPHA pousse OBJ ALPHA, BRAVO en appui sud.');
        $atak->addChatMessage($tenantId, $mapId, 'ALPHA-1', '[Démo] ALPHA-1 en position, contact visuel HLZ NORD dégagé.');
        $atak->addChatMessage($tenantId, $mapId, 'R2-DEMO', '[Démo] Réseau 45.5 nominal. JTAC en standby HAWK-1.');
        $atak->addChatMessage($tenantId, $mapId, 'SAN-DEMO', '[Démo] CCP établi, 0 blessé. Prêt extraction.');
        $atak->addPing($tenantId, $mapId, 'ALPHA-1', 15750.0, 16980.0, '[Démo] Contact 300 m NORD-EST OBJ BRAVO');
        $atak->addPing($tenantId, $mapId, 'BRAVO-1', 15200.0, 16100.0, '[Démo] Position appui consolidée');
        echo "[OK] Chat (4) + pings (2) créés\n";
    }

    // Nine-line JTAC
    $nlProbe = $pdo->prepare(
        'SELECT id FROM atak_nine_line WHERE tenant_id = ? AND map_id = ? AND author = ? LIMIT 1'
    );
    $nlProbe->execute([$tenantId, $mapId, 'DEMO-JTAC']);
    if ($nlProbe->fetchColumn()) {
        echo "[SKIP] Nine-line déjà présente\n";
    } else {
        $atak->addNineLine($tenantId, $mapId, 'DEMO-JTAC', [
            'line1' => 'IP : DEMO-IP / 14800 15500',
            'line2' => 'Heading : 045',
            'line3' => 'Distance : 2.1 km',
            'line4' => 'Elevation : 42 m',
            'line5' => 'Description : Infanterie / véhicule léger',
            'line6' => 'Location : 15780 17020',
            'line7' => 'Mark : Laser 1688 / fumée orange',
            'line8' => 'Friendlies : 800 m sud-ouest',
            'line9' => 'Egress : sud puis ouest',
        ]);
        echo "[OK] Nine-line JTAC créée\n";
    }

    $atak->upsertDesignator($tenantId, $mapId, 'DEMO-JTAC', 15780.0, 17020.0);
    $sigProbe = $pdo->prepare(
        'SELECT id FROM atak_sigint_reports WHERE tenant_id = ? AND map_id = ? AND call_sign = ? LIMIT 1'
    );
    $sigProbe->execute([$tenantId, $mapId, 'DEMO-SIGINT']);
    if (!$sigProbe->fetchColumn()) {
        $atak->addSigint($tenantId, $mapId, 'DEMO-SIGINT', 15820.0, 17100.0, 35.0);
        $atak->addSigint($tenantId, $mapId, 'DEMO-SIGINT', 15900.0, 16950.0, 42.0);
        echo "[OK] Rapports SIGINT créés\n";
    } else {
        echo "[SKIP] SIGINT déjà présent\n";
    }

    // Air asset (TTL 30 s — visible juste après le seed)
    $atak->upsertAirAsset($tenantId, $mapId, 'HAWK-1', [
        'mission_id' => $missionId,
        'model' => 'AH-64',
        'aircraft_type' => 'helicopter',
        'pos' => [13200, 17550, 180],
        'heading' => 125,
        'side' => 'WEST',
        'status' => 'IN-FLIGHT',
        'fuelPct' => 68,
        'laser' => '1688',
        'freq' => '243.0',
        'pilot' => 'DEMO-PILOT',
        'etaMinutes' => 4,
        'ordnance' => ['Hellfire' => 4, '30mm' => 240],
    ]);
    echo "[OK] Air asset HAWK-1 (rafraîchir la page si TTL 30 s écoulé)\n";

    // Formes carte
    $shapeUids = ['DEMO-AXIS', 'DEMO-NAI', 'DEMO-ROUTE'];
    $shapeExisting = $pdo->prepare(
        'SELECT shape_uid FROM atak_map_shapes WHERE tenant_id = ? AND map_id = ? AND shape_uid = ? LIMIT 1'
    );
    $anyShape = false;
    foreach ($shapeUids as $uid) {
        $shapeExisting->execute([$tenantId, $mapId, $uid]);
        if ($shapeExisting->fetchColumn()) {
            $anyShape = true;
            break;
        }
    }
    if ($anyShape) {
        echo "[SKIP] Formes carte déjà présentes\n";
    } else {
        $shapes->create($tenantId, $mapId, [
            'shape_uid' => 'DEMO-AXIS',
            'type' => 'POLYLINE',
            'label' => 'Axe d’attaque',
            'color' => '#22c55e',
            'stroke' => 3,
            'mission_id' => $missionId,
            'created_by' => 'DEMO-SEED',
            'geometry' => [
                'points' => [[14300, 15500], [14600, 15850], [14850, 16280]],
            ],
            'meta' => ['demo' => true],
        ]);
        $shapes->create($tenantId, $mapId, [
            'shape_uid' => 'DEMO-NAI',
            'type' => 'CIRCLE',
            'label' => 'NAI BRAVO',
            'color' => '#f59e0b',
            'fillOpacity' => 0.2,
            'mission_id' => $missionId,
            'created_by' => 'DEMO-SEED',
            'geometry' => [
                'center' => [15620, 16940],
                'radius' => 450,
            ],
            'meta' => ['demo' => true],
        ]);
        $shapes->create($tenantId, $mapId, [
            'shape_uid' => 'DEMO-ROUTE',
            'type' => 'POLYGON',
            'label' => 'Corridor infil',
            'color' => '#38bdf8',
            'fillOpacity' => 0.12,
            'mission_id' => $missionId,
            'created_by' => 'DEMO-SEED',
            'geometry' => [
                'points' => [[14200, 15400], [14500, 15400], [14750, 16000], [14450, 16100]],
                'closed' => true,
            ],
            'meta' => ['demo' => true],
        ]);
        echo "[OK] Formes carte (axe, NAI, corridor)\n";
    }

    // ----- Piliers Overwatch (mission_id) -----
    $c2Chk = $pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'danger_zones' LIMIT 1");
    if (!$c2Chk || !$c2Chk->fetchColumn()) {
        echo "[ATTENTION] Tables C2 Overwatch absentes — piliers ignorés.\n";
        $atak->setLastActivity($tenantId, $mapId);

        return;
    }

    $fireUnits->upsertByCallsign($missionId, 'MORTAR-1', [
        'vehicle_class' => 'MK6',
        'weapon_system' => 'Mortier 60 mm',
        'pos_x' => 14420,
        'pos_y' => 15720,
        'pos_z' => 0,
        'heading' => 50,
        'side' => 'WEST',
        'status' => 'active',
    ]);
    echo "[OK] Unité feu MORTAR-1\n";

    $dzList = $dangerZones->listByMission($missionId, false);
    $hasDemoDz = false;
    foreach ($dzList as $dz) {
        if (str_contains((string) ($dz['label'] ?? ''), '[Démo]')) {
            $hasDemoDz = true;
            break;
        }
    }
    if ($hasDemoDz) {
        echo "[SKIP] Zones danger déjà présentes\n";
    } else {
        $dangerZones->create($missionId, [
            'zone_type' => 'RESTRICTED_AREA',
            'label' => '[Démo] Zone rouge OBJ BRAVO',
            'geometry_type' => 'CIRCLE',
            'geometry_json' => ['center' => [15780, 17020], 'radius' => 350],
            'threat_level' => 'HIGH',
            'color' => '#ef4444',
            'fill_opacity' => 0.28,
            'active' => 1,
            'created_by' => 'DEMO-SEED',
        ]);
        $dangerZones->create($missionId, [
            'zone_type' => 'NO_FIRE_AREA',
            'label' => '[Démo] No-fire CCP / civils',
            'geometry_type' => 'CIRCLE',
            'geometry_json' => ['center' => [14580, 15840], 'radius' => 280],
            'threat_level' => 'MEDIUM',
            'color' => '#a855f7',
            'fill_opacity' => 0.2,
            'active' => 1,
            'created_by' => 'DEMO-SEED',
        ]);
        echo "[OK] Zones danger (2)\n";
    }

    $intelProbe = $pdo->prepare(
        'SELECT id FROM intel_reports WHERE mission_id = ? AND source_callsign = ? LIMIT 1'
    );
    $intelProbe->execute([$missionId, 'DEMO-INTEL']);
    if ($intelProbe->fetchColumn()) {
        echo "[SKIP] Rapports intel déjà présents\n";
    } else {
        $intel->create($missionId, [
            'source_callsign' => 'DEMO-INTEL',
            'report_type' => 'SIGHTING',
            'target_type' => 'INFANTRY',
            'pos_x' => 15780,
            'pos_y' => 17020,
            'pos_z' => 0,
            'confidence_score' => 70,
            'raw_payload_json' => ['note' => 'Contact section ennemie près OBJ BRAVO', 'demo' => true],
        ]);
        $intel->create($missionId, [
            'source_callsign' => 'DEMO-INTEL',
            'report_type' => 'SIGHTING',
            'target_type' => 'VEHICLE',
            'pos_x' => 15940,
            'pos_y' => 16880,
            'pos_z' => 0,
            'confidence_score' => 55,
            'raw_payload_json' => ['note' => 'Véhicule léger suspect', 'demo' => true],
        ]);
        $intel->create($missionId, [
            'source_callsign' => 'ALPHA-1',
            'report_type' => 'CONFIRMATION',
            'target_type' => 'INFANTRY',
            'pos_x' => 15750,
            'pos_y' => 16990,
            'pos_z' => 0,
            'confidence_score' => 85,
            'raw_payload_json' => ['note' => 'Confirmation visuelle ALPHA', 'demo' => true],
        ]);
        echo "[OK] Rapports intel (3)\n";
    }

    $logistics->upsert($missionId, 'DEMO-LOG-1', [
        'callsign' => 'LOG-DEMO',
        'vehicle_class' => 'Truck_transport',
        'fuel_ratio' => 0.54,
        'ammo_state_json' => ['5.56' => 'ok', 'medical' => 'full', 'water' => 'low'],
        'damage_ratio' => 0.05,
        'crew_count' => 2,
        'cargo_slots_free' => 4,
        'slingload_capable' => false,
    ]);
    $logistics->upsert($missionId, 'DEMO-LOG-2', [
        'callsign' => 'MORTAR-1',
        'vehicle_class' => 'MK6',
        'fuel_ratio' => 0.8,
        'ammo_state_json' => ['HE' => 18, 'smoke' => 6],
        'damage_ratio' => 0.0,
        'crew_count' => 3,
        'cargo_slots_free' => 0,
        'slingload_capable' => false,
    ]);
    echo "[OK] Statuts logistique (2)\n";

    $currentIff = $iffChallenges->getCurrentForMission($missionId);
    if ($currentIff) {
        $challengeId = (int) $currentIff['id'];
        echo "[SKIP] Challenge IFF déjà actif\n";
    } else {
        $challengeId = $iffChallenges->create(
            $missionId,
            'DEMO42',
            date('Y-m-d H:i:s', strtotime('-1 hour')),
            date('Y-m-d H:i:s', strtotime('+7 days'))
        );
        echo "[OK] Challenge IFF DEMO42 (#{$challengeId})\n";
    }
    $iffAssets->upsert($missionId, 'unit-alpha-1', 'ALPHA-1', 'infantry', $challengeId);
    $iffAssets->upsert($missionId, 'unit-bravo-1', 'BRAVO-1', 'infantry', $challengeId);
    $iffAssets->upsert($missionId, 'unit-hawk-1', 'HAWK-1', 'aircraft', $challengeId);
    echo "[OK] Statuts IFF (3 assets)\n";

    // Replay / AAR — courte trajectoire
    $repProbe = $pdo->prepare(
        'SELECT id FROM logs_positions WHERE mission_id = ? AND unit_id = ? LIMIT 1'
    );
    $repProbe->execute([$missionId, 'demo-alpha-1']);
    if ($repProbe->fetchColumn()) {
        echo "[SKIP] Trace replay déjà présente\n";
    } else {
        $trail = [
            [14650, 15950, 40],
            [14720, 16040, 45],
            [14780, 16120, 50],
            [14820, 16180, 55],
        ];
        foreach ($trail as $i => $pt) {
            $replay->insertLog(
                $missionId,
                'demo-alpha-1',
                'ALPHA-1',
                'infantry',
                'WEST',
                (float) $pt[0],
                (float) $pt[1],
                0.0,
                (float) $pt[2],
                1.2,
                json_encode(['demo' => true, 'step' => $i], JSON_UNESCAPED_UNICODE)
            );
            $logId = (int) $pdo->lastInsertId();
            if ($logId > 0) {
                $mins = (count($trail) - $i) * 2;
                $pdo->prepare('UPDATE logs_positions SET logged_at = DATE_SUB(NOW(), INTERVAL ? MINUTE) WHERE id = ?')
                    ->execute([$mins, $logId]);
            }
        }
        foreach ([[15020, 15780, 25], [15080, 15850, 28], [15140, 15920, 30]] as $i => $pt) {
            $replay->insertLog(
                $missionId,
                'demo-bravo-1',
                'BRAVO-1',
                'infantry',
                'WEST',
                (float) $pt[0],
                (float) $pt[1],
                0.0,
                (float) $pt[2],
                1.0,
                json_encode(['demo' => true, 'step' => $i], JSON_UNESCAPED_UNICODE)
            );
            $logId = (int) $pdo->lastInsertId();
            if ($logId > 0) {
                $mins = (3 - $i) * 2;
                $pdo->prepare('UPDATE logs_positions SET logged_at = DATE_SUB(NOW(), INTERVAL ? MINUTE) WHERE id = ?')
                    ->execute([$mins, $logId]);
            }
        }
        echo "[OK] Traces replay AAR (ALPHA + BRAVO)\n";
    }

    $atak->setLastActivity($tenantId, $mapId);
    echo "[OK] Situation tactique démo prête — /atak et /overwatch\n";
}
