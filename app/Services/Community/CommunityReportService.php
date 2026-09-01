<?php

declare(strict_types=1);

namespace App\Services\Community;

use App\Repositories\ForumReportRepository;
use App\Repositories\TrainingCourseRepository;
use App\Repositories\UserRepository;
use App\Support\ForumReportReason;

/**
 * Signalements membres hors fil de forum (formations, fiches, images, pages d’aide, anomalies d’organisation).
 */
final class CommunityReportService
{
    /** @var list<string> */
    private const HELP_DOC_KEYS = [
        'routes', 'inventaire', 'navigation', 'back-office', 'premiers-pas', 'connexion-compte',
        'faq', 'formations', 'forum', 'recherche', 'technique-readme', 'modules',
    ];

    public function __construct(
        private ForumReportRepository $reportRepository,
        private TrainingCourseRepository $courseRepository,
        private UserRepository $userRepository,
    ) {}

    /**
     * @return array{ok: true, report_id: int, reason_preview: string}|array{ok: false, error: string}
     */
    public function submit(
        int $tenantId,
        int $reporterId,
        string $targetType,
        array $input,
        string $httpHost
    ): array {
        $targetType = strtolower(trim($targetType));
        $targetId = (int) ($input['target_id'] ?? 0);
        $documentationKey = strtolower(trim((string) ($input['documentation_key'] ?? '')));
        $reportedUrl = trim((string) ($input['reported_url'] ?? ''));
        $pageUrl = trim((string) ($input['page_url'] ?? ''));
        $category = trim((string) ($input['reason'] ?? $input['reason_category'] ?? 'other'));
        $details = trim((string) ($input['details'] ?? $input['comment'] ?? ''));

        $normalized = ForumReportReason::fromCategory($category !== '' ? $category : 'other', $details);
        $reportType = $normalized['report_type'];
        $reasonSuffix = $normalized['reason'];
        $comment = $normalized['comment'];

        $contentKind = $targetType;
        $urlForDb = null;
        $reasonPrefix = '';

        if ($targetType === 'training_course') {
            if ($targetId < 1) {
                return ['ok' => false, 'error' => 'Référence de formation invalide.'];
            }
            $course = $this->courseRepository->findByIdForViewer($targetId, $tenantId);
            if (!$course) {
                return ['ok' => false, 'error' => 'Formation introuvable.'];
            }
            $title = (string) ($course['title'] ?? '');
            $reasonPrefix = 'Formation signalée : « ' . $title . ' » (n° ' . $targetId . ')';
            $urlForDb = $pageUrl !== '' ? $pageUrl : null;
        } elseif ($targetType === 'member_profile') {
            if ($targetId < 1) {
                return ['ok' => false, 'error' => 'Profil invalide.'];
            }
            $user = $this->userRepository->findById($targetId, $tenantId);
            if (!$user) {
                return ['ok' => false, 'error' => 'Profil introuvable dans cette communauté.'];
            }
            $dn = (string) ($user['display_name'] ?? $user['email'] ?? '');
            $reasonPrefix = 'Fiche personnelle signalée : ' . $dn . ' (compte n° ' . $targetId . ')';
            $urlForDb = $pageUrl !== '' ? $pageUrl : null;
        } elseif ($targetType === 'profile_picture') {
            if ($targetId < 1) {
                return ['ok' => false, 'error' => 'Référence invalide.'];
            }
            $user = $this->userRepository->findById($targetId, $tenantId);
            if (!$user) {
                return ['ok' => false, 'error' => 'Profil introuvable dans cette communauté.'];
            }
            $dn = (string) ($user['display_name'] ?? $user['email'] ?? '');
            $reasonPrefix = 'Photo de profil signalée — compte : ' . $dn . ' (n° ' . $targetId . ')';
            $urlForDb = $pageUrl !== '' ? $pageUrl : null;
        } elseif ($targetType === 'operator_visual') {
            if ($targetId < 1) {
                return ['ok' => false, 'error' => 'Référence invalide.'];
            }
            $user = $this->userRepository->findById($targetId, $tenantId);
            if (!$user) {
                return ['ok' => false, 'error' => 'Profil introuvable dans cette communauté.'];
            }
            $dn = (string) ($user['display_name'] ?? $user['email'] ?? '');
            $reasonPrefix = 'Visuel du dossier opérateur signalé — compte : ' . $dn . ' (n° ' . $targetId . ')';
            $urlForDb = $pageUrl !== '' ? $pageUrl : null;
        } elseif ($targetType === 'help_page') {
            if ($documentationKey === '' || !in_array($documentationKey, self::HELP_DOC_KEYS, true)) {
                return ['ok' => false, 'error' => 'Page d’aide non reconnue.'];
            }
            $reasonPrefix = 'Page d’aide signalée : « ' . $documentationKey . ' »';
            $urlForDb = $pageUrl !== '' ? $pageUrl : null;
        } elseif ($targetType === 'site_image') {
            if ($reportedUrl === '' || strlen($reportedUrl) > 2048) {
                return ['ok' => false, 'error' => 'Adresse de l’image invalide ou trop longue.'];
            }
            if (!$this->isTrustedSiteUrl($reportedUrl, $httpHost)) {
                return ['ok' => false, 'error' => 'Seules les images hébergées sur ce site peuvent être signalées ainsi.'];
            }
            $reasonPrefix = 'Image du site signalée';
            $urlForDb = $reportedUrl;
            $reasonSuffix = ($reasonSuffix !== '' ? $reasonSuffix . "\n" : '') . 'Page concernée : ' . ($pageUrl !== '' ? $pageUrl : '(non précisée)');
        } elseif ($targetType === 'portal_help') {
            $subject = strtolower(trim((string) ($input['help_subject'] ?? '')));
            $allowedSubjects = ['profile', 'page_content', 'message', 'user_account', 'other'];
            if (!in_array($subject, $allowedSubjects, true)) {
                return ['ok' => false, 'error' => 'Indiquez de quel type de sujet il s’agit.'];
            }
            $subjectLabels = [
                'profile' => 'Fiche ou profil',
                'page_content' => 'Contenu sur une page',
                'message' => 'Message ou discussion',
                'user_account' => 'Compte ou personne',
                'other' => 'Autre',
            ];
            $ref = trim((string) ($input['reference_note'] ?? ''));
            if (strlen($ref) > 500) {
                return ['ok' => false, 'error' => 'Le repère indiqué est trop long (500 caractères maximum).'];
            }
            $selectedTargetUrl = trim((string) ($input['selected_target_url'] ?? ''));
            if ($selectedTargetUrl !== '') {
                if (strlen($selectedTargetUrl) > 2048) {
                    return ['ok' => false, 'error' => 'Le lien ciblé est trop long.'];
                }
                if (!$this->isTrustedSiteUrl($selectedTargetUrl, $httpHost)) {
                    return ['ok' => false, 'error' => 'Le lien ciblé doit appartenir à ce site.'];
                }
            }
            $selectedTargetKind = trim((string) ($input['selected_target_kind'] ?? ''));
            if (strlen($selectedTargetKind) > 80) {
                $selectedTargetKind = substr($selectedTargetKind, 0, 80);
            }
            $selectedMemberId = (int) ($input['selected_member_id'] ?? 0);
            $selectedMemberLabel = trim((string) ($input['selected_member_label'] ?? ''));
            if (strlen($selectedMemberLabel) > 160) {
                $selectedMemberLabel = substr($selectedMemberLabel, 0, 160);
            }
            $reasonPrefix = 'Demande depuis le bouton Aide — thème : ' . ($subjectLabels[$subject] ?? $subject);
            if ($ref !== '') {
                $reasonPrefix .= "\nRepère : " . $ref;
            }
            if ($selectedMemberId > 0) {
                $targetMember = $this->userRepository->findById($selectedMemberId, $tenantId);
                if ($targetMember !== null) {
                    $displayName = trim((string) ($targetMember['display_name'] ?? $targetMember['email'] ?? ''));
                    if ($displayName === '') {
                        $displayName = 'Membre #' . $selectedMemberId;
                    }
                    $reasonPrefix .= "\nMembre ciblé : " . $displayName . ' (n° ' . $selectedMemberId . ')';
                }
            } elseif ($selectedMemberLabel !== '') {
                $reasonPrefix .= "\nMembre ciblé (recherche) : " . $selectedMemberLabel;
            }
            if ($selectedTargetUrl !== '') {
                $reasonPrefix .= "\nLien ciblé : " . $selectedTargetUrl;
                if ($selectedTargetKind !== '') {
                    $reasonPrefix .= ' [' . $selectedTargetKind . ']';
                }
            }
            $urlForDb = $selectedTargetUrl !== '' ? $selectedTargetUrl : ($pageUrl !== '' ? $pageUrl : null);
        } elseif ($targetType === 'org_anomaly') {
            $subject = strtolower(trim((string) ($input['help_subject'] ?? $input['anomaly_subject'] ?? '')));
            $allowedSubjects = [
                'fiche' => 'Fiche, grade ou unité',
                'compte' => 'Compte, connexion ou droits',
                'planning' => 'Planning, manœuvres ou événements',
                'formation' => 'Formations',
                'documents' => 'Documents',
                'atak' => 'Carte et liaisons',
                'acces' => 'Accès à un espace',
                'autre' => 'Autre',
            ];
            if (!isset($allowedSubjects[$subject])) {
                return ['ok' => false, 'error' => 'Indiquez de quel type d’anomalie il s’agit.'];
            }
            if (mb_strlen($details) < 10) {
                return ['ok' => false, 'error' => 'Décrivez l’anomalie en quelques phrases.'];
            }
            $ref = trim((string) ($input['reference_note'] ?? ''));
            if (strlen($ref) > 500) {
                return ['ok' => false, 'error' => 'Le repère indiqué est trop long (500 caractères maximum).'];
            }
            $reasonPrefix = 'Anomalie transmise à la gestion — thème : ' . $allowedSubjects[$subject];
            if ($ref !== '') {
                $reasonPrefix .= "\nRepère : " . $ref;
            }
            $urlForDb = $pageUrl !== '' ? $pageUrl : null;
        } else {
            return ['ok' => false, 'error' => 'Type de signalement non pris en charge.'];
        }

        $reasonBody = $reasonPrefix !== '' ? ($reasonPrefix . "\n" . $reasonSuffix) : $reasonSuffix;
        if ($reasonBody === '') {
            $reasonBody = 'Signalement';
        }

        $reportId = $this->reportRepository->create(
            $tenantId,
            $reporterId,
            null,
            null,
            $reasonBody,
            $reportType,
            $comment,
            $urlForDb,
            $contentKind
        );

        return ['ok' => true, 'report_id' => $reportId, 'reason_preview' => $reasonBody];
    }

    private function isTrustedSiteUrl(string $url, string $httpHost): bool
    {
        $httpHost = strtolower(trim($httpHost));
        if ($httpHost === '') {
            return false;
        }
        if (str_starts_with($url, '/') && !str_starts_with($url, '//')) {
            return true;
        }
        $scheme = parse_url($url, PHP_URL_SCHEME);
        if (!is_string($scheme) || !in_array(strtolower($scheme), ['http', 'https'], true)) {
            return false;
        }
        $host = parse_url($url, PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            return false;
        }
        $host = strtolower($host);

        return $host === $httpHost;
    }
}
