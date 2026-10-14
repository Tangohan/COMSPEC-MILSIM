<?php

declare(strict_types=1);

namespace App\Support;

use App\Repositories\AtakModReportRepository;

/**
 * Fenêtre « réponse à votre signalement » montrée au joueur à sa connexion sur Athena.
 *
 * Source : les notifications forum_notifications de type atak_mod_report_reply, créées seulement quand
 * l'exploitation coche « Notifier sur Athena » (réponse) ou prévient le joueur dans une action groupée « corrigé ».
 * Une réponse s'affiche tant que sa notification n'est pas lue ; « Compris » la marque lue.
 *
 * Textes en français et en anglais selon la langue du profil : la fenêtre est marquée translate="no".
 */
final class ModReportReplyNotice
{
    public const SESSION_CHECK_KEY = 'mod_report_reply_notice_checked_at';

    /** Délai entre deux vérifications pendant une même session (secondes). */
    public const CHECK_INTERVAL = 600;

    /** Les réponses plus anciennes ne s'affichent plus en fenêtre (elles restent dans le centre d'activité). */
    public const MAX_AGE_DAYS = 30;

    /** Réponses montrées dans la fenêtre ; au-delà, lien vers le centre d'activité. */
    public const MAX_SHOWN = 5;

    public static function locale(?string $lang): string
    {
        return strtolower(substr(trim((string) $lang), 0, 2)) === 'en' ? 'en' : 'fr';
    }

    /** @return array<string, string> */
    public static function copy(string $lang, int $count): array
    {
        if ($lang === 'en') {
            return [
                'kicker' => 'COMSPEC ATAK mod',
                'title' => $count > 1 ? 'The team replied to your reports' : 'The team replied to your report',
                'lead' => $count > 1
                    ? 'You have ' . $count . ' new replies about reports sent from the game.'
                    : 'You have a new reply about a report sent from the game.',
                'report' => 'Report',
                'auto_report' => 'Error sent automatically by your mod',
                'status' => 'Status',
                'ok' => 'Got it',
                'ok_all' => 'Got it, all of them',
                'hub' => 'See in my activity',
                'more' => 'more in your activity',
                'close' => 'Close',
            ];
        }

        return [
            'kicker' => 'Mod COMSPEC ATAK',
            'title' => $count > 1 ? 'L’équipe a répondu à vos signalements' : 'L’équipe a répondu à votre signalement',
            'lead' => $count > 1
                ? 'Vous avez ' . $count . ' nouvelles réponses à des signalements envoyés depuis le jeu.'
                : 'Vous avez une nouvelle réponse à un signalement envoyé depuis le jeu.',
            'report' => 'Signalement',
            'auto_report' => 'Erreur envoyée automatiquement par votre mod',
            'status' => 'Statut',
            'ok' => 'Compris',
            'ok_all' => 'Tout compris',
            'hub' => 'Voir dans mon activité',
            'more' => 'autre(s) dans votre activité',
            'close' => 'Fermer',
        ];
    }

    /** Libellé du statut actuel du rapport, pour le joueur. */
    public static function statusLabel(string $status, string $lang): string
    {
        $labels = [
            'fr' => [
                AtakModReportRepository::STATUS_NEW => 'Reçu',
                AtakModReportRepository::STATUS_IN_PROGRESS => 'En cours',
                AtakModReportRepository::STATUS_FIXED => 'Corrigé',
                AtakModReportRepository::STATUS_DISMISSED => 'Clos sans correction',
            ],
            'en' => [
                AtakModReportRepository::STATUS_NEW => 'Received',
                AtakModReportRepository::STATUS_IN_PROGRESS => 'In progress',
                AtakModReportRepository::STATUS_FIXED => 'Fixed',
                AtakModReportRepository::STATUS_DISMISSED => 'Closed, no fix needed',
            ],
        ];
        $set = $labels[$lang === 'en' ? 'en' : 'fr'];

        return $set[$status] ?? '';
    }

    /** Teinte de la pastille de statut (classe CSS). */
    public static function statusTone(string $status): string
    {
        return match ($status) {
            AtakModReportRepository::STATUS_FIXED => 'ok',
            AtakModReportRepository::STATUS_IN_PROGRESS => 'work',
            AtakModReportRepository::STATUS_DISMISSED => 'muted',
            default => 'info',
        };
    }

    public static function formatDate(string $createdAt, string $lang): string
    {
        $ts = strtotime($createdAt);
        if ($ts === false) {
            return '';
        }
        if ($lang === 'en') {
            return date('M j, Y', $ts) . ' at ' . date('H:i', $ts);
        }
        $months = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];

        return date('j', $ts) . ' ' . $months[(int) date('n', $ts) - 1] . ' ' . date('Y', $ts) . ' à ' . date('H\hi', $ts);
    }

    /**
     * Identifiants des rapports cités par les notifications (pour relire leur statut actuel).
     *
     * @param list<array<string, mixed>> $rows lignes forum_notifications
     * @return list<int>
     */
    public static function reportIds(array $rows): array
    {
        $ids = [];
        foreach ($rows as $row) {
            foreach (self::payload($row)['report_ids'] ?? [] as $id) {
                if ((int) $id > 0) {
                    $ids[(int) $id] = true;
                }
            }
        }

        return array_keys($ids);
    }

    /**
     * Réponses à afficher, dans l'ordre des notifications (plus récentes d'abord).
     *
     * @param list<array<string, mixed>> $rows lignes forum_notifications (id, payload_json, created_at)
     * @param array<int, string> $statuses id de rapport → statut actuel
     * @return list<array{id:int, report:string, auto:bool, message:string, date:string, status:string, status_label:string, status_tone:string}>
     */
    public static function items(array $rows, array $statuses, string $lang): array
    {
        $items = [];
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id < 1) {
                continue;
            }
            $p = self::payload($row);
            $message = trim((string) ($p['message'] ?? ''));
            if ($message === '') {
                $message = trim((string) ($p['summary'] ?? ''));
            }
            if ($message === '') {
                continue;
            }
            // Statut le moins avancé des rapports cités (un lot « corrigé » reste « corrigé »).
            $status = '';
            foreach ($p['report_ids'] ?? [] as $rid) {
                $s = $statuses[(int) $rid] ?? '';
                if ($s !== '' && ($status === '' || self::rank($s) < self::rank($status))) {
                    $status = $s;
                }
            }
            $items[] = [
                'id' => $id,
                'report' => trim((string) ($p['report'] ?? '')),
                'auto' => !empty($p['auto']),
                'message' => $message,
                'date' => self::formatDate((string) ($row['created_at'] ?? ''), $lang),
                'status' => $status,
                'status_label' => $status !== '' ? self::statusLabel($status, $lang) : '',
                'status_tone' => self::statusTone($status),
            ];
        }

        return $items;
    }

    /** @return array<string, mixed> */
    private static function payload(array $row): array
    {
        $p = json_decode((string) ($row['payload_json'] ?? ''), true);

        return is_array($p) ? $p : [];
    }

    private static function rank(string $status): int
    {
        return match ($status) {
            AtakModReportRepository::STATUS_NEW => 0,
            AtakModReportRepository::STATUS_IN_PROGRESS => 1,
            AtakModReportRepository::STATUS_DISMISSED => 2,
            default => 3,
        };
    }
}
