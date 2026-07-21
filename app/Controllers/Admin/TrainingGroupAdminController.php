<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\TrainingCourseRepository;
use App\Repositories\TrainingGroupRepository;
use App\Repositories\UserRepository;
use App\Support\TrainingLmsStaffAccess;

/**
 * Groupes de formation (cohortes) : back-office Studio LMS.
 */
final class TrainingGroupAdminController
{
    public function __construct(
        private ?TrainingGroupRepository $groupRepository = null,
        private ?TrainingCourseRepository $courseRepository = null,
        private ?UserRepository $userRepository = null,
    ) {
        $this->groupRepository ??= new TrainingGroupRepository();
        $this->courseRepository ??= new TrainingCourseRepository();
        $this->userRepository ??= new UserRepository();
    }

    private function requireAccess(): void
    {
        if (TrainingLmsStaffAccess::allows(Gate::getInstance())) {
            return;
        }
        throw new \RuntimeException('Accès refusé.', 403);
    }

    public function index(Request $request, array $params = []): Response
    {
        $this->requireAccess();
        $tenantId = (int) Session::get('tenant_id');
        $groups = $this->groupRepository->listForTenant($tenantId);
        $courses = $this->courseRepository->listForTenant($tenantId, null);

        return Response::view('layout.training_lms_staff_shell', [
            'content' => 'admin.training.groups_index',
            'title' => 'Groupes de formation',
            'trainingAdminNav' => 'groups',
            'tgGroups' => $groups,
            'tgCourses' => $courses,
            'tgCsrfToken' => Csrf::token(),
        ]);
    }

    public function store(Request $request, array $params = []): Response
    {
        $this->requireAccess();
        $tenantId = (int) Session::get('tenant_id');
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url('formation/groupes'));
        }
        $name = trim((string) $request->input('name', ''));
        if ($name === '') {
            Session::flash('error', 'Le nom du groupe est requis.');

            return Response::redirect(url('formation/groupes'));
        }
        $description = trim((string) $request->input('description', ''));
        $courseIdRaw = trim((string) $request->input('course_id', ''));
        $courseId = $courseIdRaw !== '' ? (int) $courseIdRaw : null;
        if ($courseId !== null && $courseId < 1) {
            $courseId = null;
        }
        $actorId = (int) Session::get('user_id');
        $this->groupRepository->create($tenantId, $name, $description, $courseId, $actorId > 0 ? $actorId : null);
        Session::flash('success', 'Groupe créé.');

        return Response::redirect(url('formation/groupes'));
    }

    public function show(Request $request, array $params = []): Response
    {
        $this->requireAccess();
        $tenantId = (int) Session::get('tenant_id');
        $id = (int) ($params['id'] ?? 0);
        $group = $id > 0 ? $this->groupRepository->findByIdForTenant($id, $tenantId) : null;
        if (!$group) {
            Session::flash('error', 'Groupe introuvable.');

            return Response::redirect(url('formation/groupes'));
        }
        $members = $this->groupRepository->listMembers($id);
        $memberIds = array_map(static fn (array $m): int => (int) $m['user_id'], $members);
        $availableUsers = [];
        foreach ($this->userRepository->allForTenant($tenantId) as $u) {
            $uid = (int) ($u['id'] ?? 0);
            if ($uid < 1 || (string) ($u['status'] ?? '') !== 'active' || in_array($uid, $memberIds, true)) {
                continue;
            }
            $availableUsers[] = $u;
        }

        return Response::view('layout.training_lms_staff_shell', [
            'content' => 'admin.training.group_show',
            'title' => 'Groupe — ' . (string) ($group['name'] ?? ''),
            'trainingAdminNav' => 'groups',
            'tgGroup' => $group,
            'tgMembers' => $members,
            'tgAvailableUsers' => $availableUsers,
            'tgCsrfToken' => Csrf::token(),
        ]);
    }

    public function addMember(Request $request, array $params = []): Response
    {
        $this->requireAccess();
        $tenantId = (int) Session::get('tenant_id');
        $id = (int) ($params['id'] ?? 0);
        $group = $id > 0 ? $this->groupRepository->findByIdForTenant($id, $tenantId) : null;
        if (!$group) {
            Session::flash('error', 'Groupe introuvable.');

            return Response::redirect(url('formation/groupes'));
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url('formation/groupes/' . $id));
        }
        $userId = (int) $request->input('user_id', 0);
        $target = $userId > 0 ? $this->userRepository->findById($userId, $tenantId) : null;
        if (!$target) {
            Session::flash('error', 'Membre introuvable.');

            return Response::redirect(url('formation/groupes/' . $id));
        }
        $this->groupRepository->addMember($id, $userId);
        Session::flash('success', 'Membre ajouté au groupe.');

        return Response::redirect(url('formation/groupes/' . $id));
    }

    public function removeMember(Request $request, array $params = []): Response
    {
        $this->requireAccess();
        $tenantId = (int) Session::get('tenant_id');
        $id = (int) ($params['id'] ?? 0);
        $userId = (int) ($params['userId'] ?? 0);
        $group = $id > 0 ? $this->groupRepository->findByIdForTenant($id, $tenantId) : null;
        if (!$group) {
            Session::flash('error', 'Groupe introuvable.');

            return Response::redirect(url('formation/groupes'));
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url('formation/groupes/' . $id));
        }
        $this->groupRepository->removeMember($id, $userId);
        Session::flash('success', 'Membre retiré du groupe.');

        return Response::redirect(url('formation/groupes/' . $id));
    }

    public function delete(Request $request, array $params = []): Response
    {
        $this->requireAccess();
        $tenantId = (int) Session::get('tenant_id');
        $id = (int) ($params['id'] ?? 0);
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url('formation/groupes'));
        }
        $this->groupRepository->delete($id, $tenantId);
        Session::flash('success', 'Groupe supprimé.');

        return Response::redirect(url('formation/groupes'));
    }
}
