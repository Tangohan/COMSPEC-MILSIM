<?php

declare(strict_types=1);

namespace App\Services\Training;

use App\Repositories\TrainingCourseRepository;
use App\Repositories\TrainingEnrollmentRepository;
use App\Repositories\UserRepository;

/**
 * Règles d'auto-inscription : prérequis de formations, rôles, grades, statuts compte, blocage global.
 * Les assignations manuelles par le staff ne passent pas par ce service.
 */
class TrainingEnrollmentPolicyService
{
    public function __construct(
        private TrainingEnrollmentRepository $enrollmentRepository,
        private UserRepository $userRepository,
        private TrainingCourseRepository $courseRepository
    ) {}

    /**
     * @param array<string, mixed> $course Ligne training_courses (avec enrollment_policy_json éventuel)
     * @return array{allowed: bool, messages: list<string>}
     */
    public function evaluateSelfEnroll(int $userId, int $tenantId, array $course): array
    {
        $policy = $this->decodePolicy($course['enrollment_policy_json'] ?? null);
        $messages = [];

        if (!empty($policy['enrollments_blocked'])) {
            return ['allowed' => false, 'messages' => ['Les inscriptions sont fermées pour cette formation.']];
        }
        if (array_key_exists('self_enroll_allowed', $policy) && $policy['self_enroll_allowed'] === false) {
            return ['allowed' => false, 'messages' => ['L’inscription libre est désactivée — contactez un instructeur ou le staff.']];
        }

        $user = $this->userRepository->findById($userId, $tenantId);
        if (!$user) {
            return ['allowed' => false, 'messages' => ['Utilisateur introuvable.']];
        }

        $cid = (int) ($course['id'] ?? 0);
        $existing = $cid > 0 ? $this->enrollmentRepository->findByCourseAndUser($cid, $userId) : null;
        if ($existing) {
            $est = (string) ($existing['status'] ?? '');
            if ($est === 'pending_approval') {
                return ['allowed' => false, 'messages' => ['Votre demande d’inscription est en cours d’examen par un formateur.']];
            }
            if (in_array($est, ['assigned', 'in_progress', 'completed', 'failed'], true)) {
                return ['allowed' => false, 'messages' => ['Vous êtes déjà inscrit à cette formation.']];
            }
        }

        $prereq = $this->normalizeIdList($policy['prerequisite_course_ids'] ?? []);
        foreach ($prereq as $preId) {
            if ($preId === (int) ($course['id'] ?? 0)) {
                continue;
            }
            $preCourse = $this->courseRepository->findById($preId, $tenantId);
            if (!$preCourse) {
                $messages[] = 'Prérequis : formation #' . $preId . ' (introuvable — vérifiez la configuration).';

                continue;
            }
            if (!$this->enrollmentRepository->userHasCompletedCourse($userId, $preId)) {
                $messages[] = 'Vous devez d’abord valider la formation « ' . (string) ($preCourse['title'] ?? '') . ' ».';

                return ['allowed' => false, 'messages' => $messages];
            }
        }

        $reqRoles = $this->normalizeIdList($policy['required_role_ids'] ?? []);
        if ($reqRoles !== []) {
            $okRole = false;
            foreach ($reqRoles as $rid) {
                if ($this->userRepository->userHasTenantRole($userId, $rid)) {
                    $okRole = true;
                    break;
                }
            }
            if (!$okRole) {
                return ['allowed' => false, 'messages' => ['Votre rôle ne permet pas de vous inscrire à cette formation.']];
            }
        }

        $reqGrades = $this->normalizeIdList($policy['required_grade_ids'] ?? []);
        if ($reqGrades !== []) {
            $gid = isset($user['grade_id']) ? (int) $user['grade_id'] : 0;
            if ($gid < 1 || !in_array($gid, $reqGrades, true)) {
                return ['allowed' => false, 'messages' => ['Votre grade ne correspond pas aux grades autorisés pour cette formation.']];
            }
        }

        $reqStatuses = $this->normalizeStringList($policy['required_user_statuses'] ?? []);
        if ($reqStatuses !== []) {
            $st = (string) ($user['status'] ?? '');
            if (!in_array($st, $reqStatuses, true)) {
                return ['allowed' => false, 'messages' => ['Votre statut de compte ne permet pas cette inscription.']];
            }
        }

        $reqCerts = $this->normalizeIdList($policy['require_certificate_from_course_ids'] ?? []);
        foreach ($reqCerts as $cid) {
            if (!$this->enrollmentRepository->userHasCompletedCourse($userId, $cid)) {
                $c = $this->courseRepository->findById($cid, $tenantId);
                $t = $c ? (string) ($c['title'] ?? '') : ('#' . $cid);

                return ['allowed' => false, 'messages' => ['Attestation ou validation requise pour : « ' . $t . ' ».']];
            }
        }

        return ['allowed' => true, 'messages' => []];
    }

    /**
     * Données pour la fiche formation publique : prérequis formations, attestations attendues, messages de politique.
     *
     * @return array{
     *   prerequisite_courses: list<array{id:int,title:string,slug:string,completed:?bool}>,
     *   certificate_courses: list<array{id:int,title:string,slug:string,completed:?bool}>,
     *   policy_flags: list<string>
     * }
     */
    public function getPublicPolicyDisplay(int $tenantId, array $course, ?int $userId): array
    {
        $policy = $this->decodePolicy($course['enrollment_policy_json'] ?? null);
        $courseId = (int) ($course['id'] ?? 0);
        $prerequisiteCourses = [];
        foreach ($this->normalizeIdList($policy['prerequisite_course_ids'] ?? []) as $preId) {
            if ($preId === $courseId) {
                continue;
            }
            $c = $this->courseRepository->findById($preId, $tenantId);
            if (!$c) {
                continue;
            }
            $completed = null;
            if ($userId !== null && $userId > 0) {
                $completed = $this->enrollmentRepository->userHasCompletedCourse($userId, $preId);
            }
            $prerequisiteCourses[] = [
                'id' => $preId,
                'title' => (string) ($c['title'] ?? ''),
                'slug' => (string) ($c['slug'] ?? ''),
                'completed' => $completed,
            ];
        }
        $certificateCourses = [];
        foreach ($this->normalizeIdList($policy['require_certificate_from_course_ids'] ?? []) as $cid) {
            $c = $this->courseRepository->findById($cid, $tenantId);
            if (!$c) {
                continue;
            }
            $completed = null;
            if ($userId !== null && $userId > 0) {
                $completed = $this->enrollmentRepository->userHasCompletedCourse($userId, $cid);
            }
            $certificateCourses[] = [
                'id' => $cid,
                'title' => (string) ($c['title'] ?? ''),
                'slug' => (string) ($c['slug'] ?? ''),
                'completed' => $completed,
            ];
        }
        $policyFlags = [];
        if (!empty($policy['enrollments_blocked'])) {
            $policyFlags[] = 'Les inscriptions sont fermées pour cette formation.';
        }
        if (array_key_exists('self_enroll_allowed', $policy) && $policy['self_enroll_allowed'] === false) {
            $policyFlags[] = 'L’inscription libre est désactivée — contactez un instructeur ou le staff.';
        }
        if ($this->normalizeIdList($policy['required_role_ids'] ?? []) !== []) {
            $policyFlags[] = 'Rôle requis pour s’inscrire (vérifié à l’inscription).';
        }
        if ($this->normalizeIdList($policy['required_grade_ids'] ?? []) !== []) {
            $policyFlags[] = 'Grade requis pour s’inscrire (vérifié à l’inscription).';
        }
        if ($this->normalizeStringList($policy['required_user_statuses'] ?? []) !== []) {
            $policyFlags[] = 'Statut de compte requis (vérifié à l’inscription).';
        }
        if (!empty($policy['self_enroll_requires_approval']) && (!array_key_exists('self_enroll_allowed', $policy) || $policy['self_enroll_allowed'] !== false)) {
            $policyFlags[] = 'L’auto-inscription est possible, mais chaque demande doit être validée par un formateur avant l’accès au parcours.';
        }
        if (array_key_exists('comments_enabled', $policy) && !\training_lms_policy_comments_enabled($policy)) {
            $policyFlags[] = 'Les commentaires publics sur la page d’échanges sont désactivés pour cette formation.';
        }

        return [
            'prerequisite_courses' => $prerequisiteCourses,
            'certificate_courses' => $certificateCourses,
            'policy_flags' => $policyFlags,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function decodePolicy(?string $json): array
    {
        if ($json === null || trim($json) === '') {
            return [];
        }
        $d = json_decode($json, true);

        return is_array($d) ? $d : [];
    }

    /** @param mixed $raw @return list<int> */
    private function normalizeIdList(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $v) {
            $i = (int) $v;
            if ($i > 0) {
                $out[] = $i;
            }
        }

        return array_values(array_unique($out));
    }

    /** @param mixed $raw @return list<string> */
    private function normalizeStringList(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $v) {
            if (is_string($v) && $v !== '') {
                $out[] = $v;
            }
        }

        return array_values(array_unique($out));
    }
}
