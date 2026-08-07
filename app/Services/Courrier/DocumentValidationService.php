<?php

declare(strict_types=1);

namespace App\Services\Courrier;

/**
 * Règles de conformité : modèle, preset, objet, destinataire, signataire, variables non résolues, etc.
 * Retourne une liste d'alertes (bloquante, avertissement, recommandation).
 */
class DocumentValidationService
{
    public const SEVERITY_BLOCKING = 'blocking';
    public const SEVERITY_WARNING = 'warning';
    public const SEVERITY_RECOMMENDATION = 'recommendation';

    public function __construct(
        private TemplateRenderService $renderService
    ) {
    }

    /**
     * Valide un document (champs + body) et retourne la liste d'alertes.
     * @param array{template_id?: int, preset_id?: int, subject?: string, destination_label?: string, issuer_label?: string, reference_number?: string, body_rendered?: string, status?: string} $document
     * @param array $context pour résolution des variables
     * @param array $presetRequires ex: ['subject' => true, 'reference' => true]
     */
    public function validate(array $document, array $context = [], array $presetRequires = []): array
    {
        $alerts = [];

        if (empty($document['template_id'])) {
            $alerts[] = ['code' => 'template_missing', 'message' => 'Aucun modèle sélectionné.', 'severity' => self::SEVERITY_BLOCKING];
        }
        if (empty($document['preset_id'])) {
            $alerts[] = ['code' => 'preset_missing', 'message' => 'Aucun format (preset) défini.', 'severity' => self::SEVERITY_BLOCKING];
        }

        $subject = trim((string) ($document['subject'] ?? ''));
        if ($subject === '' && ($presetRequires['subject'] ?? true)) {
            $alerts[] = ['code' => 'subject_empty', 'message' => 'L\'objet est vide.', 'severity' => self::SEVERITY_BLOCKING];
        }

        $destination = trim((string) ($document['destination_label'] ?? ''));
        if ($destination === '') {
            $alerts[] = ['code' => 'destination_missing', 'message' => 'Le destinataire est absent.', 'severity' => self::SEVERITY_BLOCKING];
        }

        $issuer = trim((string) ($document['issuer_label'] ?? ''));
        if ($issuer === '') {
            $alerts[] = ['code' => 'issuer_missing', 'message' => 'Le signataire est absent.', 'severity' => self::SEVERITY_BLOCKING];
        }

        $ref = trim((string) ($document['reference_number'] ?? ''));
        if ($ref === '' && ($presetRequires['reference'] ?? false)) {
            $alerts[] = ['code' => 'reference_missing', 'message' => 'La référence est obligatoire pour ce type de document.', 'severity' => self::SEVERITY_BLOCKING];
        }

        $body = (string) ($document['body_rendered'] ?? '');
        if ($body !== '' && $context !== []) {
            $body = $this->renderService->renderBody($body, array_merge($context, [
                'document' => array_merge(is_array($context['document'] ?? null) ? $context['document'] : [], $document),
            ]));
        }
        $unresolved = $this->renderService->findUnresolvedPlaceholders($body);
        if (!empty($unresolved)) {
            $alerts[] = [
                'code' => 'placeholders_unresolved',
                'message' => 'Variables non remplacées : ' . implode(', ', $unresolved),
                'severity' => self::SEVERITY_BLOCKING,
                'placeholders' => $unresolved,
            ];
        }

        $status = $document['status'] ?? 'draft';
        if ($status === 'draft' && empty($alerts)) {
            $alerts[] = ['code' => 'draft_ok', 'message' => 'Brouillon enregistrable.', 'severity' => self::SEVERITY_RECOMMENDATION];
        }

        return $alerts;
    }

    /**
     * Score de complétude (0-100) à partir des champs remplis et des alertes.
     */
    public function completenessScore(array $document, array $alerts): int
    {
        $blocking = array_filter($alerts, fn ($a) => ($a['severity'] ?? '') === self::SEVERITY_BLOCKING);
        if (!empty($blocking)) {
            $score = 50;
            $score -= count($blocking) * 10;
            return max(0, min(100, $score));
        }
        $filled = 0;
        $total = 6;
        if (!empty($document['template_id'])) {
            $filled++;
        }
        if (!empty($document['preset_id'])) {
            $filled++;
        }
        if (trim((string) ($document['subject'] ?? '')) !== '') {
            $filled++;
        }
        if (trim((string) ($document['destination_label'] ?? '')) !== '') {
            $filled++;
        }
        if (trim((string) ($document['issuer_label'] ?? '')) !== '') {
            $filled++;
        }
        if (trim((string) ($document['body_rendered'] ?? '')) !== '') {
            $filled++;
        }
        return (int) round($filled / $total * 100);
    }

    /**
     * Indique si l'envoi / export est autorisé (aucune alerte bloquante).
     */
    public function canSendOrExport(array $alerts): bool
    {
        foreach ($alerts as $a) {
            if (($a['severity'] ?? '') === self::SEVERITY_BLOCKING) {
                return false;
            }
        }
        return true;
    }
}
