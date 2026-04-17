<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\Courrier\UserSignatureRepository;
use App\Repositories\PersonnelExtrasRepository;
use App\Repositories\PersonnelProfileRepository;
use App\Repositories\PersonnelQualificationRepository;
use App\Repositories\TrainingCertificateRepository;
use App\Repositories\UserProfileRepository;
use App\Services\Auth\AuthService;
use App\Services\Personnel\PersonnelCompletenessService;

final class DossierOperateurController
{
    public function __construct(
        private AuthService $authService,
        private PersonnelProfileRepository $personnelProfileRepository,
        private PersonnelExtrasRepository $personnelExtrasRepository,
        private UserProfileRepository $userProfileRepository,
        private PersonnelQualificationRepository $qualificationRepository,
        private TrainingCertificateRepository $trainingCertificateRepository,
        private PersonnelCompletenessService $completenessService,
        private UserSignatureRepository $userSignatureRepository,
    ) {}

    public function accreditation(Request $request, array $params = []): Response
    {
        $user = $this->authService->user();
        if (!$user) {
            return Response::redirect(url('login'));
        }

        $tenantId = (int) Session::get('tenant_id');
        $uid = (int) $user['id'];

        $this->personnelProfileRepository->ensureRecord($uid);
        $this->personnelExtrasRepository->ensureRecord($uid);
        $this->userProfileRepository->ensureRow($uid);

        $userProfile = $this->userProfileRepository->getByUserId($uid);
        $extras = $this->personnelExtrasRepository->getByUserId($uid) ?? [];

        $completeness = $this->completenessService->getScoreWithMissingLabels(
            $uid,
            $user,
            $userProfile,
            $extras,
            $tenantId
        );

        $qualifications = $this->qualificationRepository->listForUser($uid);
        $certificates = $this->trainingCertificateRepository->listByUserId($uid, $tenantId);
        $nextQualificationExpiration = $this->qualificationRepository->getNextExpiration($uid);
        $userSignatures = $this->userSignatureRepository->listByUser($uid, $tenantId);
        $hasDefaultSignature = false;
        foreach ($userSignatures as $signature) {
            if ((int) ($signature['is_default'] ?? 0) === 1) {
                $hasDefaultSignature = true;
                break;
            }
        }

        $gate = Gate::getInstance();

        return Response::view('layout.main', [
            'title' => 'Accréditation — dossier opérateur',
            'content' => 'dossier_operateur.accreditation',
            'user' => $user,
            'completeness' => $completeness,
            'qualifications' => $qualifications,
            'certificates' => $certificates,
            'next_qualification_expiration' => $nextQualificationExpiration,
            'has_default_signature' => $hasDefaultSignature,
            'can_view_documents' => $gate->allows('documents.view'),
        ]);
    }
}
