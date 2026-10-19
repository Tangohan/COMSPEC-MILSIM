<?php

declare(strict_types=1);

namespace App\Services\Training;

use App\Core\Database;
use App\Repositories\PedagogyRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UserNotificationPreferencesRepository;
use App\Repositories\UserRepository;
use App\Services\Email\EmailEvents;
use App\Services\EmailService;
use App\Support\TrainingUrlDeliverySchema;

/**
 * Nomination d’une personne à une étape de la chaîne pédagogique, sans passer par un rôle.
 * Les rôles « instructor_trainer » / « trainer_of_trainers » ne peuvent plus être créés
 * (communautés réduites aux trois accès) : la nomination par personne les remplace.
 */
final class TrainingPedagogyDesignationService
{
    public function __construct(
        private PedagogyRepository $pedagogyRepository,
        private UserRepository $userRepository,
        private TenantRepository $tenantRepository,
        private EmailService $emailService,
        private UserNotificationPreferencesRepository $notificationPreferencesRepository,
    ) {}

    /**
     * Étapes, dans l’ordre affiché : intitulé, explication et ce que la personne peut faire ensuite.
     *
     * @return array<string, array{step: string, title: string, lead: string, can: list<string>, mail: string, url_path: string, url_label: string}>
     */
    public static function steps(): array
    {
        return [
            'design_trainer' => [
                'step' => '1',
                'title' => 'Conception de parcours',
                'lead' => 'La personne qui crée et fait évoluer les formations : fiche, modules, leçons, évaluations.',
                'can' => [
                    'Créer une formation et la construire dans le Studio.',
                    'Être reconnue comme concepteur : une formation peut alors être publiée dans la communauté.',
                ],
                'mail' => 'Vous pouvez désormais créer et faire évoluer les formations de la communauté dans le Studio.',
                'url_path' => 'studio',
                'url_label' => 'Ouvrir le Studio',
            ],
            'delivery_instructor' => [
                'step' => '2',
                'title' => 'Animation sur le terrain',
                'lead' => 'La personne qui anime les séances, encadre les stagiaires et suit leur progression en session.',
                'can' => [
                    'Être désignée comme encadrant sur un créneau de formation.',
                    'Suivre et valider la progression des stagiaires pendant la séance.',
                ],
                'mail' => 'Vous pouvez désormais être désigné comme encadrant sur les créneaux de formation et suivre les stagiaires en séance.',
                'url_path' => 'competences/instructeur',
                'url_label' => 'Ouvrir l’espace instructeur',
            ],
            'instructor_certifier' => [
                'step' => '3',
                'title' => 'Validation des encadrants',
                'lead' => 'La personne qui confirme qu’un encadrant est prêt à animer et à valider sur le terrain.',
                'can' => [
                    'Valider ou suspendre l’habilitation d’un encadrant.',
                    'Délivrer les validations et attestations de compétence.',
                ],
                'mail' => 'Vous pouvez désormais confirmer l’habilitation des encadrants et délivrer les validations de compétence.',
                'url_path' => 'competences/validation',
                'url_label' => 'Ouvrir la validation',
            ],
            'trainer_certifier' => [
                'step' => '4',
                'title' => 'Gouvernance des concepteurs',
                'lead' => 'Le référent qui supervise les concepteurs, relit leurs parcours et valide leur habilitation.',
                'can' => [
                    'Superviser les concepteurs et relire leurs parcours avant publication.',
                    'Nommer ou retirer les personnes des autres étapes.',
                ],
                'mail' => 'Vous supervisez désormais les concepteurs de parcours et pouvez nommer les personnes de la chaîne pédagogique.',
                'url_path' => 'competences/formateur',
                'url_label' => 'Ouvrir l’espace formateur',
            ],
        ];
    }

    /**
     * Nomme un membre à une ou plusieurs étapes. Un seul e-mail par étape nouvellement attribuée ;
     * un échec d’envoi n’annule jamais la nomination.
     *
     * @param list<string> $kinds
     * @return array{ok: bool, added: list<string>, message: string}
     */
    public function designate(int $tenantId, int $userId, array $kinds, int $actorUserId, bool $notify): array
    {
        $user = $userId > 0 ? $this->userRepository->findById($userId, $tenantId) : null;
        if (!$user) {
            return ['ok' => false, 'added' => [], 'message' => 'Choisissez un membre de votre organisation.'];
        }
        $steps = self::steps();
        $kinds = array_values(array_filter(array_unique($kinds), static fn (string $k): bool => isset($steps[$k])));
        if ($kinds === []) {
            return ['ok' => false, 'added' => [], 'message' => 'Étape inconnue.'];
        }
        TrainingUrlDeliverySchema::ensure(Database::getPdo());
        $added = [];
        foreach ($kinds as $kind) {
            try {
                if ($this->pedagogyRepository->addDesignation($tenantId, $userId, $kind, $actorUserId)) {
                    $added[] = $kind;
                }
            } catch (\Throwable) {
                return ['ok' => false, 'added' => $added, 'message' => 'Nomination impossible pour le moment : la mise à jour de la base n’est pas encore passée. Réessayez dans un instant.'];
            }
        }
        $name = $this->displayName($user);
        if ($added === []) {
            return ['ok' => true, 'added' => [], 'message' => $name . ' était déjà nommé à cette étape.'];
        }
        $this->pedagogyRepository->logAudit($tenantId, $actorUserId, 'pedagogy_designation_add', 'user', $userId, ['kinds' => $added]);
        $mailed = false;
        if ($notify) {
            foreach ($added as $kind) {
                $mailed = $this->notify($tenantId, $user, $kind, $actorUserId) || $mailed;
            }
        }
        $titles = array_map(static fn (string $k): string => $steps[$k]['title'], $added);

        return [
            'ok' => true,
            'added' => $added,
            'message' => $name . ' est nommé : ' . implode(', ', $titles) . '.'
                . ($notify ? ($mailed ? ' Un e-mail l’a prévenu.' : ' L’e-mail n’a pas pu partir (adresse absente ou notifications coupées).') : ''),
        ];
    }

    public function remove(int $tenantId, int $userId, string $kind, int $actorUserId): bool
    {
        $ok = $this->pedagogyRepository->removeDesignation($tenantId, $userId, $kind);
        if ($ok) {
            $this->pedagogyRepository->logAudit($tenantId, $actorUserId, 'pedagogy_designation_remove', 'user', $userId, ['kind' => $kind]);
        }

        return $ok;
    }

    /** @return array<string, list<array<string, mixed>>> */
    public function listAll(int $tenantId): array
    {
        $out = [];
        foreach (array_keys(self::steps()) as $kind) {
            $out[$kind] = $this->pedagogyRepository->listDesignations($tenantId, $kind);
        }

        return $out;
    }

    /** @param array<string, mixed> $user */
    private function notify(int $tenantId, array $user, string $kind, int $actorUserId): bool
    {
        try {
            $userId = (int) ($user['id'] ?? 0);
            $email = trim((string) ($user['email'] ?? ''));
            if ($userId < 1 || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return false;
            }
            if (!$this->notificationPreferencesRepository->isEmailEventEnabled($userId, EmailEvents::TRAINING_PEDAGOGY_DESIGNATION)) {
                return false;
            }
            $step = self::steps()[$kind];
            $tenant = $this->tenantRepository->findById($tenantId);
            $tenantName = 'Communauté';
            if ($tenant) {
                $tenantName = function_exists('community_display_name')
                    ? community_display_name($tenant)
                    : (string) ($tenant['name'] ?? 'Communauté');
            }
            $actor = $actorUserId > 0 ? $this->userRepository->findById($actorUserId, $tenantId) : null;

            return $this->emailService->sendTrainingPedagogyDesignation(
                $email,
                $this->displayName($user),
                $actor ? $this->displayName($actor) : 'L’équipe formation',
                $tenantName,
                $step['title'],
                $step['mail'],
                training_lms_admin_url($step['url_path']),
                $step['url_label'],
                $tenantId
            );
        } catch (\Throwable) {
            return false;
        }
    }

    /** @param array<string, mixed> $user */
    private function displayName(array $user): string
    {
        foreach (['display_name', 'callsign', 'email'] as $k) {
            $v = trim((string) ($user[$k] ?? ''));
            if ($v !== '') {
                return $v;
            }
        }

        return 'Ce membre';
    }
}
