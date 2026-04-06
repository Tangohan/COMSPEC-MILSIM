<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Normalise le motif affiché (libellé FR) et le report_type stocké (spam|abuse|illegal|other).
 *
 * @return array{report_type: string, reason: string, comment: string|null}
 */
final class ForumReportReason
{
    public static function fromCategory(string $category, string $details): array
    {
        $cat = strtolower(trim($category));
        if ($cat === '') {
            $cat = 'other';
        }
        $details = trim($details);

        $labels = [
            'spam' => 'Spam',
            'harassment' => 'Harcèlement',
            'inappropriate' => 'Contenu inapproprié',
            'suspicious_link' => 'Lien suspect',
            'abuse' => 'Abus',
            'illegal' => 'Contenu illégal',
            'other' => 'Autre',
        ];

        $label = $labels[$cat] ?? 'Autre';
        $reportType = match ($cat) {
            'spam' => 'spam',
            'harassment', 'inappropriate', 'abuse' => 'abuse',
            'suspicious_link', 'illegal' => 'illegal',
            default => 'other',
        };

        $reasonText = $details !== '' ? ($label . ' — ' . $details) : $label;
        $comment = $details !== '' ? $details : null;

        return [
            'report_type' => $reportType,
            'reason' => $reasonText,
            'comment' => $comment,
        ];
    }
}
