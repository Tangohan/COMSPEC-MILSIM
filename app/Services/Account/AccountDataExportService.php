<?php

declare(strict_types=1);

namespace App\Services\Account;

use App\Repositories\CommunityEventRepository;
use App\Repositories\ForumPostRepository;
use App\Repositories\PersonnelProfileRepository;
use App\Repositories\TenantMessageRepository;
use App\Repositories\TrainingCertificateRepository;
use App\Repositories\TrainingEnrollmentRepository;
use App\Repositories\UserLegalIdentityRepository;
use App\Repositories\UserProfileRepository;
use App\Repositories\UserRepository;
use ZipArchive;

/**
 * Export « mes données » en self-service (RGPD) : profil, dossier personnel, formations,
 * participations aux événements, messages du forum et internes — au format ZIP de fichiers
 * CSV, réutilise l'idiome déjà en place pour l'export de conformité LMS
 * (ComplianceBundleExportController).
 */
final class AccountDataExportService
{
    public function __construct(
        private UserRepository $userRepository,
        private UserProfileRepository $userProfileRepository,
        private UserLegalIdentityRepository $userLegalIdentityRepository,
        private PersonnelProfileRepository $personnelProfileRepository,
        private TrainingEnrollmentRepository $trainingEnrollmentRepository,
        private TrainingCertificateRepository $trainingCertificateRepository,
        private CommunityEventRepository $communityEventRepository,
        private ForumPostRepository $forumPostRepository,
        private TenantMessageRepository $tenantMessageRepository,
    ) {}

    /** @return string Contenu binaire du ZIP. */
    public function buildZip(int $userId, int $tenantId): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'cs_account_export_');
        if ($tmp === false) {
            throw new \RuntimeException('Impossible de préparer l’export.');
        }

        $zip = new ZipArchive();
        if ($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            @unlink($tmp);
            throw new \RuntimeException('Impossible de préparer l’export.');
        }

        $zip->addFromString('profil.csv', $this->profileCsv($userId, $tenantId));
        $zip->addFromString('formations.csv', $this->trainingCsv($userId, $tenantId));
        $zip->addFromString('evenements.csv', $this->eventsCsv($userId, $tenantId));
        $zip->addFromString('forum.csv', $this->forumCsv($userId, $tenantId));
        $zip->addFromString('messages.csv', $this->messagesCsv($userId, $tenantId));
        $zip->addFromString('lisez_moi.txt', $this->readme());
        $zip->close();

        $body = file_get_contents($tmp);
        @unlink($tmp);

        return $body !== false ? $body : '';
    }

    private function readme(): string
    {
        return "Export de vos données personnelles Athena\n"
            . "Généré le " . date('d/m/Y H:i') . ".\n\n"
            . "Ce dossier contient les données que vous avez fournies ou générées sur cette "
            . "communauté : profil, dossier personnel, formations, participations aux "
            . "événements, messages du forum et messages internes.\n\n"
            . "Pour une demande de rectification, d'effacement complet ou toute autre demande "
            . "RGPD, utilisez le formulaire dédié depuis la page « Mentions légales » du site.\n";
    }

    private function profileCsv(int $userId, int $tenantId): string
    {
        $user = $this->userRepository->findById($userId, $tenantId) ?? [];
        $profile = $this->userProfileRepository->getByUserId($userId) ?? [];
        $legal = $this->userLegalIdentityRepository->getByUserId($userId) ?? [];
        $personnel = $this->personnelProfileRepository->getByUserId($userId) ?? [];

        $fields = [
            'E-mail' => $user['email'] ?? '',
            'Nom affiché' => $user['display_name'] ?? '',
            'Indicatif' => $user['callsign'] ?? '',
            'Compte créé le' => $user['created_at'] ?? '',
            'Prénom' => $legal['first_name'] ?? ($profile['first_name'] ?? ''),
            'Nom' => $legal['last_name'] ?? ($profile['last_name'] ?? ''),
            'Téléphone' => $legal['phone'] ?? ($profile['phone'] ?? ''),
            'Date de naissance' => $legal['birth_date'] ?? ($profile['birth_date'] ?? ''),
            'Nationalité' => $legal['nationality'] ?? ($profile['nationality'] ?? ''),
            'Nom de personnage (RP)' => $personnel['character_name'] ?? '',
            'Callsign (RP)' => $personnel['callsign'] ?? '',
            'Sexe' => $personnel['sex'] ?? '',
            'Situation familiale' => $personnel['family_situation'] ?? '',
            'Poids (kg)' => $personnel['weight_kg'] ?? '',
            'Lieu de naissance (RP)' => $personnel['birth_place'] ?? '',
            'Groupe sanguin (RP)' => $personnel['blood_type'] ?? '',
            'Statut opérateur' => $personnel['operator_status'] ?? '',
        ];

        $fh = fopen('php://temp', 'r+');
        fwrite($fh, "\xEF\xBB\xBF");
        foreach ($fields as $label => $value) {
            fputcsv($fh, [$label, (string) $value], ';', '"', '\\');
        }
        rewind($fh);
        $out = (string) stream_get_contents($fh);
        fclose($fh);

        return $out;
    }

    /** @param list<array<string,mixed>> $rows @param list<string> $headers @param list<string> $keys */
    private function csvFromRows(array $rows, array $headers, array $keys): string
    {
        $fh = fopen('php://temp', 'r+');
        fwrite($fh, "\xEF\xBB\xBF");
        fputcsv($fh, $headers, ';', '"', '\\');
        foreach ($rows as $row) {
            $line = [];
            foreach ($keys as $k) {
                $line[] = (string) ($row[$k] ?? '');
            }
            fputcsv($fh, $line, ';', '"', '\\');
        }
        rewind($fh);
        $out = (string) stream_get_contents($fh);
        fclose($fh);

        return $out;
    }

    private function trainingCsv(int $userId, int $tenantId): string
    {
        $enrollments = $this->trainingEnrollmentRepository->listByUserId($userId, $tenantId);
        $certificates = $this->trainingCertificateRepository->listByUserId($userId, $tenantId);
        $rows = [];
        foreach ($enrollments as $e) {
            $rows[] = [
                'type' => 'Inscription',
                'formation' => $e['course_title'] ?? '',
                'statut' => $e['status'] ?? '',
                'date' => $e['completed_at'] ?? ($e['assigned_at'] ?? ''),
            ];
        }
        foreach ($certificates as $c) {
            $rows[] = [
                'type' => 'Certificat',
                'formation' => $c['course_title'] ?? '',
                'statut' => $c['certificate_number'] ?? '',
                'date' => $c['issued_at'] ?? '',
            ];
        }

        return $this->csvFromRows($rows, ['Type', 'Formation', 'Statut / référence', 'Date'], ['type', 'formation', 'statut', 'date']);
    }

    private function eventsCsv(int $userId, int $tenantId): string
    {
        $rows = $this->communityEventRepository->listRsvpsForUser($userId, $tenantId);

        return $this->csvFromRows(
            $rows,
            ['Événement', 'Date', 'Participation', 'Motif d’absence', 'Pointage', 'Répondu le'],
            ['event_title', 'starts_at', 'status', 'absence_reason', 'checked_in_at', 'created_at']
        );
    }

    private function forumCsv(int $userId, int $tenantId): string
    {
        $rows = $this->forumPostRepository->listByUserId($userId, $tenantId);

        return $this->csvFromRows($rows, ['Sujet', 'Message', 'Publié le'], ['topic_title', 'body', 'created_at']);
    }

    private function messagesCsv(int $userId, int $tenantId): string
    {
        $rows = $this->tenantMessageRepository->listSentByUserId($userId, $tenantId);

        return $this->csvFromRows($rows, ['Sujet du fil', 'Message', 'Envoyé le'], ['subject', 'body', 'created_at']);
    }
}
