<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Mise en forme de la messagerie interne (/messages) : heures relatives, initiales,
 * regroupement des messages par jour puis par auteur, repère « nouveaux messages ».
 * Pur PHP, sans base de données, pour rester testable.
 */
final class MessagingThreadPresenter
{
    /** Deux messages du même auteur à moins de ce délai restent dans la même bulle groupée. */
    public const GROUP_GAP_SECONDS = 600;

    private const MONTHS_SHORT = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
    private const MONTHS_LONG = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
    private const DAYS_SHORT = ['dim.', 'lun.', 'mar.', 'mer.', 'jeu.', 'ven.', 'sam.'];
    private const DAYS_LONG = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];

    public static function initials(string $name): string
    {
        $name = trim(preg_replace('/[^\p{L}\p{N}\s\-\.]+/u', ' ', $name) ?? '');
        if ($name === '') {
            return '?';
        }
        $parts = preg_split('/[\s\-\.]+/u', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $first = mb_substr($parts[0] ?? $name, 0, 1);
        if (count($parts) < 2) {
            return mb_strtoupper(mb_substr($parts[0] ?? $name, 0, 2));
        }
        $last = mb_substr($parts[count($parts) - 1], 0, 1);

        return mb_strtoupper($first . $last);
    }

    /** Teinte stable (0-359) dérivée d’un identifiant, pour colorer les avatars. */
    public static function hue(int|string $seed): int
    {
        return (int) (crc32((string) $seed) % 360);
    }

    /** Heure courte pour la liste des conversations : « 14:32 », « Hier », « lun. », « 12 sept. », « 12/09/2025 ». */
    public static function listTime(string $datetime, ?int $now = null): string
    {
        $ts = $datetime !== '' ? strtotime($datetime) : false;
        if (!$ts) {
            return '';
        }
        $now ??= time();
        $diff = $now - $ts;
        if ($diff >= 0 && $diff < 60) {
            return 'À l’instant';
        }
        if ($diff >= 0 && $diff < 3600) {
            return (int) floor($diff / 60) . ' min';
        }
        $days = self::dayDiff($ts, $now);
        if ($days === 0) {
            return date('H:i', $ts);
        }
        if ($days === 1) {
            return 'Hier';
        }
        if ($days > 1 && $days < 7) {
            return self::DAYS_SHORT[(int) date('w', $ts)];
        }
        if (date('Y', $ts) === date('Y', $now)) {
            return (int) date('j', $ts) . ' ' . self::MONTHS_SHORT[(int) date('n', $ts) - 1];
        }

        return date('d/m/Y', $ts);
    }

    /** Libellé de séparateur de jour : « Aujourd’hui », « Hier », « lundi 28 septembre » (+ année si autre). */
    public static function dayLabel(string $datetime, ?int $now = null): string
    {
        $ts = $datetime !== '' ? strtotime($datetime) : false;
        if (!$ts) {
            return '';
        }
        $now ??= time();
        $days = self::dayDiff($ts, $now);
        if ($days === 0) {
            return 'Aujourd’hui';
        }
        if ($days === 1) {
            return 'Hier';
        }
        $label = self::DAYS_LONG[(int) date('w', $ts)] . ' ' . (int) date('j', $ts) . ' ' . self::MONTHS_LONG[(int) date('n', $ts) - 1];
        if (date('Y', $ts) !== date('Y', $now)) {
            $label .= ' ' . date('Y', $ts);
        }

        return $label;
    }

    /** Date complète pour les infobulles : « 05/10/2026 à 14:32 ». */
    public static function fullTime(string $datetime): string
    {
        $ts = $datetime !== '' ? strtotime($datetime) : false;

        return $ts ? date('d/m/Y \à H:i', $ts) : '';
    }

    /**
     * Interlocuteur affiché dans la liste : pour l’auteur du fil, c’est l’encadrement ;
     * pour l’encadrement, c’est le membre qui a ouvert le fil.
     *
     * @param array<string, mixed> $thread
     */
    public static function peerLabel(array $thread, int $currentUserId): string
    {
        $creatorId = (int) ($thread['created_by_user_id'] ?? 0);
        if ($creatorId === 0 || $creatorId === $currentUserId) {
            return 'Encadrement';
        }
        $name = trim((string) ($thread['creator_name'] ?? ''));

        return $name !== '' ? $name : 'Membre';
    }

    /**
     * Regroupe les messages : jours → blocs d’un même auteur (rapprochés dans le temps).
     * Le premier message reçu après $lastReadAt ouvre un bloc marqué « unread_before ».
     *
     * @param list<array<string, mixed>> $messages triés du plus ancien au plus récent
     * @return list<array{key: string, label: string, groups: list<array<string, mixed>>}>
     */
    public static function groupMessages(array $messages, int $currentUserId, ?string $lastReadAt = null, ?int $now = null): array
    {
        $now ??= time();
        $readTs = ($lastReadAt !== null && $lastReadAt !== '') ? strtotime($lastReadAt) : false;
        $unreadPlaced = false;
        $days = [];
        $dayIndex = -1;
        $prevSender = null;
        $prevTs = 0;

        foreach ($messages as $m) {
            $created = (string) ($m['created_at'] ?? '');
            $ts = $created !== '' ? (strtotime($created) ?: 0) : 0;
            $dayKey = $ts > 0 ? date('Y-m-d', $ts) : '0000-00-00';
            $senderId = (int) ($m['sender_user_id'] ?? 0);
            $mine = $currentUserId > 0 && $senderId === $currentUserId;

            if ($dayIndex < 0 || $days[$dayIndex]['key'] !== $dayKey) {
                $days[] = ['key' => $dayKey, 'label' => $ts > 0 ? self::dayLabel($created, $now) : '', 'groups' => []];
                $dayIndex++;
                $prevSender = null;
            }

            $unreadHere = false;
            if (!$unreadPlaced && !$mine && $readTs !== false && $ts > $readTs) {
                $unreadHere = true;
                $unreadPlaced = true;
            }

            $groups = &$days[$dayIndex]['groups'];
            $startNew = $prevSender !== $senderId
                || ($ts - $prevTs) > self::GROUP_GAP_SECONDS
                || $unreadHere
                || $groups === [];
            if ($startNew) {
                $name = trim((string) ($m['display_name'] ?? ''));
                if ($name === '') {
                    $name = trim((string) ($m['email'] ?? '')) ?: 'Participant';
                }
                $groups[] = [
                    'mine' => $mine,
                    'sender_id' => $senderId,
                    'name' => $mine ? 'Vous' : $name,
                    'initials' => self::initials($name),
                    'hue' => self::hue($senderId),
                    'time' => $ts > 0 ? date('H:i', $ts) : '',
                    'unread_before' => $unreadHere,
                    'messages' => [],
                ];
            }
            $groups[count($groups) - 1]['messages'][] = [
                'id' => (int) ($m['id'] ?? 0),
                'body' => (string) ($m['body'] ?? ''),
                'time' => $ts > 0 ? date('H:i', $ts) : '',
                'full_time' => self::fullTime($created),
                'iso' => $ts > 0 ? date('c', $ts) : '',
            ];
            unset($groups);

            $prevSender = $senderId;
            $prevTs = $ts;
        }

        return $days;
    }

    private static function dayDiff(int $ts, int $now): int
    {
        $a = new \DateTimeImmutable('@' . $ts);
        $b = new \DateTimeImmutable('@' . $now);
        $tz = new \DateTimeZone(date_default_timezone_get());
        $a = $a->setTimezone($tz)->setTime(0, 0);
        $b = $b->setTimezone($tz)->setTime(0, 0);

        return (int) round(($b->getTimestamp() - $a->getTimestamp()) / 86400);
    }
}
