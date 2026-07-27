<?php

declare(strict_types=1);

namespace App\Services\Attendance;

use App\Repositories\EventRsvpNominativeRepository;

/**
 * Présentation métier de la vue « Réponses nominatives » (libellés, badges, export).
 */
final class EventRsvpNominativeService
{
    public function __construct(
        private ?EventRsvpNominativeRepository $repository = null,
    ) {
        $this->repository ??= new EventRsvpNominativeRepository();
    }

    /**
     * @param array{q?: string, response?: string, section?: string, atak?: string} $filters
     * @return array{rows: list<array<string,mixed>>, stats: array<string,mixed>, sections: list<string>}
     */
    public function listForEvent(int $tenantId, int $eventId, array $filters = []): array
    {
        $data = $this->repository->listForEvent($tenantId, $eventId, $filters);
        $data['rows'] = array_map(fn (array $row): array => $this->presentRow($row), $data['rows']);

        return $data;
    }

    /**
     * @param array{q?: string, response?: string, section?: string, atak?: string} $filters
     */
    public function exportCsv(int $tenantId, int $eventId, array $filters = []): string
    {
        $data = $this->listForEvent($tenantId, $eventId, $filters);

        return $this->repository->exportCsv($data['rows']);
    }

    /** @return array<string, string> */
    public static function responseFilterLabelsFr(): array
    {
        return [
            'confirmed' => 'Confirmé',
            'maybe' => 'Peut-être',
            'no_response' => 'Sans réponse',
            'declined' => 'Décliné',
        ];
    }

    /** @return array<string, string> */
    public static function atakFilterLabelsFr(): array
    {
        return [
            'active' => 'Actif',
            'expired' => 'Expiré',
            'missing' => 'Manquant',
            'pending' => 'En attente',
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function updateRowMeta(int $tenantId, int $eventId, int $userId, array $payload): bool
    {
        return $this->repository->updateRowMeta($tenantId, $eventId, $userId, $payload);
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function presentRow(array $row): array
    {
        $status = (string) ($row['rsvp_status'] ?? '');
        $responseMeta = self::responseMeta($status);
        $atakStatus = (string) ($row['atak_status'] ?? 'missing');
        $atakLabels = self::atakFilterLabelsFr();

        $respondedAt = $row['rsvp_updated_at'] ?? $row['rsvp_created_at'] ?? null;
        $availabilityLabel = self::formatAvailability(
            $row['availability_from'] ?? null,
            $row['availability_to'] ?? null
        );

        return $row + [
            'response_key' => $responseMeta['key'],
            'response_label' => $responseMeta['label'],
            'response_badge_class' => $responseMeta['badge_class'],
            'responded_label' => self::formatRespondedAt($respondedAt, $status),
            'availability_label' => $availabilityLabel,
            'atak_label' => $atakLabels[$atakStatus] ?? 'En attente',
            'atak_badge_class' => self::atakBadgeClass($atakStatus),
        ];
    }

    /**
     * @return array{key: string, label: string, badge_class: string}
     */
    public static function responseMeta(string $status): array
    {
        return match ($status) {
            'yes' => ['key' => 'confirmed', 'label' => 'Confirmé', 'badge_class' => 'bg-emerald-100 text-emerald-800'],
            'maybe' => ['key' => 'maybe', 'label' => 'Peut-être', 'badge_class' => 'bg-amber-100 text-amber-800'],
            'no' => ['key' => 'declined', 'label' => 'Décliné', 'badge_class' => 'bg-slate-200 text-slate-700'],
            default => ['key' => 'no_response', 'label' => 'Sans réponse', 'badge_class' => 'bg-sky-100 text-sky-800'],
        };
    }

    public static function atakBadgeClass(string $status): string
    {
        return match ($status) {
            'active' => 'bg-emerald-100 text-emerald-800',
            'expired' => 'bg-rose-100 text-rose-800',
            'missing' => 'bg-rose-50 text-rose-600',
            default => 'bg-amber-100 text-amber-800',
        };
    }

    private static function formatRespondedAt(mixed $raw, string $status): string
    {
        if ($status === '' || !is_string($raw) || trim($raw) === '') {
            return '—';
        }
        $ts = strtotime($raw);
        if ($ts === false) {
            return '—';
        }

        return date('d/m H:i', $ts);
    }

    private static function formatAvailability(mixed $from, mixed $to): string
    {
        $fromStr = self::formatTimeShort($from);
        $toStr = self::formatTimeShort($to);
        if ($fromStr === '' && $toStr === '') {
            return '—';
        }
        if ($fromStr !== '' && $toStr !== '') {
            return $fromStr . ' – ' . $toStr;
        }

        return $fromStr !== '' ? $fromStr : $toStr;
    }

    private static function formatTimeShort(mixed $raw): string
    {
        if (!is_string($raw) || trim($raw) === '') {
            return '';
        }
        $parts = explode(':', trim($raw));

        return count($parts) >= 2 ? sprintf('%02d:%02d', (int) $parts[0], (int) $parts[1]) : '';
    }
}
