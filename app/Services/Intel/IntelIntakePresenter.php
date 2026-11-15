<?php

declare(strict_types=1);

namespace App\Services\Intel;

use App\Controllers\Admin\AdminAtakReportRoutingController;
use App\Services\Sse\SseRedactionService;
use App\Support\SseFieldNoteCatalog;

/**
 * Mise en forme commune d'une remontée, pour ses deux habillages (back-office et portail SSE) :
 * urgence ramenée à une seule échelle, délai de prise en compte, étapes du cycle, pastilles, thèmes, cotation.
 * Aucune requête ici : tout se calcule depuis la remontée déjà lue par IntelIntakeService.
 */
final class IntelIntakePresenter
{
    /**
     * Objectif de prise en compte, en minutes, selon l'urgence (pas de réglage par communauté à ce jour) :
     * critique / Flash 15 min, urgent / Immédiat 1 h, normal / Prioritaire 4 h, routine 24 h.
     */
    public const ACK_TARGET_MINUTES = ['critical' => 15, 'high' => 60, 'medium' => 240, 'low' => 1440];

    public const URGENCY_LEVELS = [
        'critical' => ['critique', 'FLASH'],
        'high' => ['urgent', 'IMMEDIATE'],
        'medium' => ['normal', 'PRIORITY'],
        'low' => ['routine', 'ROUTINE'],
    ];

    public const RELIABILITY = [
        'A' => 'Source sûre', 'B' => 'Source habituellement sûre', 'C' => 'Source assez sûre',
        'D' => 'Source pas toujours sûre', 'E' => 'Source peu sûre', 'F' => 'Fiabilité inconnue',
    ];

    public const CREDIBILITY = [
        1 => 'Information confirmée', 2 => 'Information probable', 3 => 'Information possible',
        4 => 'Information douteuse', 5 => 'Information improbable', 6 => 'Véracité inconnue',
    ];

    public const REPORT_CLASSIFICATIONS = [
        'UNCLASSIFIED' => 'Non classifié', 'RESTRICTED' => 'Diffusion restreinte', 'CONFIDENTIAL' => 'Confidentiel', 'SECRET' => 'Secret',
    ];

    public const CHANNELS = ['bo' => 'back-office', 'web' => 'portail SSE', 'jeu' => 'en jeu'];

    /** Motifs proposés dans les fenêtres (modifiables, jamais imposés). */
    public const REASON_TEMPLATES = [
        'transmise' => ['Relève de l’échelon supérieur', 'À verser au dossier en cours', 'Demande une décision du commandement'],
        'doublon' => ['Même fait déjà remonté', 'Même lieu, même heure, autre observateur', 'Renvoi de la même fiche'],
        'non_exploitable' => ['Trop vague pour être recoupée', 'Position ou heure manquante', 'Information périmée'],
        'close' => ['Aucune suite à donner', 'Fait sans intérêt pour le renseignement'],
        'delete' => ['Envoi par erreur', 'Essai du téléphone', 'Contenu hors sujet'],
    ];

    /** @return 'critical'|'high'|'medium'|'low' */
    public static function urgencyLevel(string $raw): string
    {
        $raw = trim($raw);
        // Le code exact d'abord (« IMMEDIATE » d'un compte rendu), puis les anciens degrés des fiches.
        foreach ([$raw, SseFieldNoteCatalog::URGENCY_ALIASES[strtolower($raw)] ?? ''] as $candidate) {
            foreach (self::URGENCY_LEVELS as $level => $codes) {
                foreach ($codes as $code) {
                    if ($candidate !== '' && strcasecmp($code, $candidate) === 0) {
                        return $level;
                    }
                }
            }
        }

        return 'low';
    }

    public static function urgencyLabel(string $source, string $raw): string
    {
        if ($source === 'cr') {
            return AdminAtakReportRoutingController::PRIORITIES[strtoupper(trim($raw))] ?? 'Routine';
        }

        return SseFieldNoteCatalog::urgencyLabel(SseFieldNoteCatalog::normalizeUrgency($raw));
    }

    public static function classificationLabel(string $source, string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }
        if ($source === 'fiche' || isset(SseRedactionService::LEVELS[$raw])) {
            return SseRedactionService::levelLabel($raw);
        }

        // Mentions des comptes rendus du téléphone (saisies en anglais par le mod).
        return self::REPORT_CLASSIFICATIONS[strtoupper($raw)] ?? $raw;
    }

    /** Diffusion au niveau le plus bas (le seul où un extrait peut sortir du site) ? */
    public static function lowestClassification(string $raw): bool
    {
        $raw = strtolower(trim($raw));
        if ($raw === '') {
            return true;
        }
        $levels = SseRedactionService::LEVELS;
        if (!isset($levels[$raw])) {
            return in_array($raw, ['unclassified', 'non classifié', 'nc', 'np', 'public'], true);
            // Toute autre mention inconnue est tenue pour sensible.
        }

        return $levels[$raw] <= min($levels);
    }

    public static function originLabel(string $source, string $origin): string
    {
        if ($source === 'cr') {
            return 'ATAK';
        }

        return SseFieldNoteCatalog::originLabel($origin !== '' ? $origin : 'atak');
    }

    /** « C3 — Source assez sûre · Information possible » ; vide si la remontée ne porte pas de cotation. */
    public static function reliabilityLabel(mixed $reliability, mixed $credibility): string
    {
        $rel = strtoupper(trim((string) $reliability));
        $cred = (int) $credibility;
        if (!isset(self::RELIABILITY[$rel]) && !isset(self::CREDIBILITY[$cred])) {
            return '';
        }
        $code = (isset(self::RELIABILITY[$rel]) ? $rel : '') . (isset(self::CREDIBILITY[$cred]) ? (string) $cred : '');
        $parts = array_filter([self::RELIABILITY[$rel] ?? '', self::CREDIBILITY[$cred] ?? '']);

        return $code . ' — ' . implode(' · ', $parts);
    }

    /**
     * Thèmes d'une fiche, lisibles : jamais le JSON stocké.
     *
     * @return list<array{code: string, label: string, tone: string}>
     */
    public static function themes(mixed $raw): array
    {
        $out = [];
        foreach (SseFieldNoteCatalog::normalizeThemes($raw) as $code) {
            $out[] = ['code' => $code, 'label' => SseFieldNoteCatalog::themeLabel($code), 'tone' => SseFieldNoteCatalog::themeTone($code)];
        }

        return $out;
    }

    /**
     * Texte d'un champ, échappé : un passage caviardé est surligné si ce lecteur peut le lire, barré sinon.
     *
     * @param array<string, mixed> $row
     * @param list<array<string, mixed>> $redactions
     */
    public static function textHtml(array $row, string $field, array $redactions, string $viewerLevel, int $viewerUserId, string $markClass = 'rmt-red', string $barClass = 'rmt-bar'): string
    {
        $value = (string) ($row[$field] ?? '');
        if (trim($value) === '') {
            return '';
        }
        $h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
        $mine = array_values(array_filter($redactions, static fn (array $r): bool => ($r['field'] ?? '') === $field));
        $out = '';
        foreach (IntelRedaction::split($value, $mine) as [$chunk, $r]) {
            if ($r === null) {
                $out .= $h($chunk);
            } elseif (IntelRedaction::readable($r, $viewerLevel, $viewerUserId)) {
                $out .= '<mark class="' . $h($markClass) . '" title="Caviardé : en clair à partir de « ' . $h(SseRedactionService::levelLabel((string) $r['clear_level'])) . ' »">' . $h($chunk) . '</mark>';
            } else {
                $out .= '<span class="' . $h($barClass) . '" title="Passage caviardé">' . $h(SseRedactionService::bar($chunk)) . '</span>';
            }
        }

        return nl2br($out, false);
    }

    /**
     * Une ligne du fil, en HTML échappé (sans l'auteur) : « a passé l'état de … à … ».
     *
     * @param array<string, mixed> $event
     * @param \Closure(string, int): string $itemUrl adresse d'une remontée dans l'habillage courant
     */
    public static function eventLine(array $event, \Closure $itemUrl): string
    {
        $h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $m = is_array($event['meta'] ?? null) ? $event['meta'] : [];
        $ref = static function (mixed $r) use ($h, $itemUrl): string {
            if (!is_array($r) || !isset(IntelIntakeService::SOURCES[(string) ($r['source'] ?? '')]) || (int) ($r['id'] ?? 0) < 1) {
                return '';
            }

            return '<a href="' . $h($itemUrl((string) $r['source'], (int) $r['id'])) . '">' . $h((string) ($r['ref'] ?? ($r['source'] . '/' . $r['id']))) . '</a>';
        };
        $target = '';
        if (!empty($m['duplicate_of'])) {
            $target = ' — doublon de ' . $ref($m['duplicate_of']);
        } elseif (!empty($m['forwarded_to'])) {
            $label = $h((string) $m['forwarded_to']);
            $target = ' — à ' . (!empty($m['case_id']) ? '<a href="' . $h(url('atak/sse/dossiers/' . (int) $m['case_id'])) . '">' . $label . '</a>' : '<strong>' . $label . '</strong>');
        }
        $states = IntelIntakeService::STATES;

        return match ((string) ($event['kind'] ?? '')) {
            'state' => (($m['from'] ?? '') === ($m['to'] ?? '')
                    ? 'a précisé l’état <strong>' . $h($states[$m['to'] ?? ''] ?? '?') . '</strong>'
                    : 'a passé l’état de <strong>' . $h($states[$m['from'] ?? ''] ?? '?') . '</strong> à <strong>' . $h($states[$m['to'] ?? ''] ?? '?') . '</strong>') . $target,
            'ack' => 'a pris en compte la remontée' . (isset($m['waited']) ? ' après ' . $h(self::duration((int) $m['waited'])) : ''),
            'duplicate' => 'a rattaché un doublon : ' . $ref($m['duplicate'] ?? null),
            'assign' => !empty($m['to']) ? 'a attribué la remontée à <strong>' . $h($m['to']) . '</strong>' : 'a retiré l’attribution',
            'type' => 'a changé le type de <strong>' . $h($m['from'] ?? '') . '</strong> en <strong>' . $h($m['to'] ?? '') . '</strong>',
            'edit' => 'a modifié : ' . $h(implode(', ', array_map('strval', (array) ($m['fields'] ?? [])))),
            'redact' => 'a caviardé un passage (' . $h($m['field'] ?? '') . ', ' . (int) ($m['length'] ?? 0) . ' caractères), en clair à partir de « ' . $h($m['level'] ?? '') . ' »',
            'convert' => isset($m['origin'])
                ? 'a créé cette fiche à partir de ' . $ref($m['origin'])
                : 'a converti la remontée en ' . $h(mb_strtolower((string) ($m['label'] ?? 'élément'))) . ' <strong>' . $h($m['ref'] ?? '') . '</strong>',
            'link' => 'a relié la remontée à : ' . $h($m['type'] ?? 'un élément'),
            'discord' => 'a demandé une notification Discord' . (!empty($m['channel']) ? ' dans ' . $h($m['channel']) : ''),
            'steam' => 'a consulté l’identifiant Steam de l’émetteur (' . $h($m['mode'] ?? 'lecture') . ')',
            default => $h(IntelIntakeService::EVENT_LABELS[(string) ($event['kind'] ?? '')] ?? (string) ($event['kind'] ?? '')),
        };
    }

    public static function ago(string $ts, ?int $now = null): string
    {
        $t = strtotime($ts);
        if ($t === false) {
            return '';
        }
        $d = ($now ?? time()) - $t;

        return match (true) {
            $d < 90 => 'à l’instant',
            $d < 3600 => 'il y a ' . (int) round($d / 60) . ' min',
            $d < 86400 => 'il y a ' . (int) round($d / 3600) . ' h',
            $d < 86400 * 30 => 'il y a ' . (int) round($d / 86400) . ' j',
            default => 'le ' . date('d/m/Y', $t),
        };
    }

    public static function when(string $ts): string
    {
        $t = strtotime($ts);

        return $t !== false ? date('d/m/Y H:i', $t) : '';
    }

    /** Durée lisible : « 12 min », « 3 h 05 », « 2 j 4 h ». */
    public static function duration(int $seconds): string
    {
        $seconds = max(0, $seconds);
        $m = intdiv($seconds, 60);
        if ($m < 60) {
            return $m . ' min';
        }
        $h = intdiv($m, 60);
        if ($h < 24) {
            return $h . ' h ' . str_pad((string) ($m % 60), 2, '0', STR_PAD_LEFT);
        }

        return intdiv($h, 24) . ' j ' . ($h % 24) . ' h';
    }

    /** Identifiant Steam masqué : « 7656•••••••••4821 ». */
    public static function maskSteam(string $steam): string
    {
        $steam = trim($steam);
        $len = mb_strlen($steam);
        if ($len === 0) {
            return '';
        }
        if ($len <= 8) {
            return str_repeat('•', $len);
        }

        return mb_substr($steam, 0, 4) . str_repeat('•', $len - 8) . mb_substr($steam, -4);
    }

    /**
     * Délai de prise en compte.
     *
     * @param array<string, mixed> $item remontée (find) ou ligne de liste : state, at, priority, ack_at
     * @return array{target: int, waited: int, acked: bool, late: bool, label: string, target_label: string}
     */
    public static function delay(array $item, ?int $now = null): array
    {
        $now ??= time();
        $level = self::urgencyLevel((string) ($item['priority'] ?? ''));
        $target = self::ACK_TARGET_MINUTES[$level] * 60;
        $received = strtotime((string) ($item['at'] ?? '')) ?: $now;
        $state = (string) ($item['state'] ?? 'a_traiter');
        // Remise en file (« À traiter ») : plus personne n'en répond, le délai court de nouveau depuis la réception.
        $acked = $state !== 'a_traiter';
        $ackTs = $acked && !empty($item['ack_at']) ? strtotime((string) $item['ack_at']) : false;
        $waited = max(0, ($ackTs !== false ? $ackTs : $now) - $received);
        $late = !$acked && $waited > $target;
        $label = match (true) {
            $ackTs !== false => 'Prise en compte en ' . self::duration($waited),
            $acked => 'Prise en compte',
            $late => 'En retard de ' . self::duration($waited - $target),
            default => 'En attente depuis ' . self::duration($waited),
        };

        return [
            'target' => $target,
            'waited' => $waited,
            'acked' => $acked,
            'late' => $late,
            'label' => $label,
            'target_label' => 'objectif ' . self::duration($target),
        ];
    }

    /**
     * Les quatre étapes du cycle, avec celle où en est la remontée.
     *
     * @param array<string, mixed> $item
     * @return list<array{key: string, label: string, status: 'done'|'current'|'todo', at: string}>
     */
    public static function cycle(array $item): array
    {
        $state = (string) ($item['state'] ?? 'a_traiter');
        $rank = match ($state) {
            'a_traiter' => 0,
            'prise_en_compte' => 1,
            'en_cours' => 2,
            default => 3,
        };
        $steps = [
            ['recue', 'Reçue', (string) ($item['at'] ?? '')],
            ['prise_en_compte', 'Prise en compte', (string) ($item['ack_at'] ?? '')],
            ['en_cours', 'En exploitation', (string) ($item['work_at'] ?? '')],
            ['issue', $rank === 3 ? (string) (IntelIntakeService::STATES[$state] ?? 'Issue') : 'Issue', $rank === 3 ? (string) ($item['closed_at'] ?? '') : ''],
        ];
        $out = [];
        foreach ($steps as $i => [$key, $label, $at]) {
            $out[] = [
                'key' => $key,
                'label' => $label,
                'status' => $i < $rank || ($rank === 3 && $i === 3) ? 'done' : ($i === $rank ? 'current' : 'todo'),
                'at' => $at !== '' && !str_starts_with($at, '0000') ? $at : '',
            ];
        }
        // La première étape est toujours acquise : une remontée lue ici est reçue.
        $out[0]['status'] = $rank === 0 ? 'current' : 'done';

        return $out;
    }

    /**
     * Pastilles d'une remontée, dans l'ordre d'affichage. Mêmes pastilles sur la fiche, dans la liste et au bureau SSE.
     *
     * @param array<string, mixed> $item state, priority, classification, origin, source, attachments (int), redactions (int),
     *                                   linked (bool), reads (int), readers (int), ack_at, at, deleted
     * @return list<array{key: string, label: string, tone: string, title: string}>
     */
    public static function badges(array $item, ?int $now = null): array
    {
        $source = (string) ($item['source'] ?? 'fiche');
        $state = (string) ($item['state'] ?? 'a_traiter');
        $out = [];
        $add = static function (string $key, string $label, string $tone, string $title = '') use (&$out): void {
            $out[] = ['key' => $key, 'label' => $label, 'tone' => $tone, 'title' => $title];
        };
        $stateTone = [
            'a_traiter' => 'new', 'prise_en_compte' => 'info', 'en_cours' => 'busy', 'transmise' => 'forward',
            'exploitee' => 'done', 'doublon' => 'muted', 'non_exploitable' => 'muted', 'close' => 'muted',
        ][$state] ?? 'muted';
        $add('etat', (string) (IntelIntakeService::STATES[$state] ?? $state), $stateTone, (string) (IntelIntakeService::STATE_HINTS[$state] ?? ''));
        if (!empty($item['deleted'])) {
            $add('supprimee', 'Supprimée', 'danger', 'Remontée supprimée, restaurable.');
        }
        $delay = self::delay($item, $now);
        if ($delay['late']) {
            $add('retard', 'En retard', 'danger', $delay['label'] . ' (' . $delay['target_label'] . ').');
        }
        $level = self::urgencyLevel((string) ($item['priority'] ?? ''));
        if ($level !== 'low') {
            $add('urgence', self::urgencyLabel($source, (string) ($item['priority'] ?? '')), ['critical' => 'danger', 'high' => 'warn', 'medium' => 'caution'][$level], 'Urgence');
        }
        $class = self::classificationLabel($source, (string) ($item['classification'] ?? ''));
        if ($class !== '') {
            $add('diffusion', $class, 'class', 'Diffusion');
        }
        $add('origine', self::originLabel($source, (string) ($item['origin'] ?? '')), 'plain', 'Origine');
        if ((int) ($item['redactions'] ?? 0) > 0 || !empty($item['redacted'])) {
            $add('caviardee', 'Caviardée', 'ink', 'Des passages sont caviardés.');
        }
        $att = (int) ($item['attachments'] ?? 0);
        if ($att > 0) {
            $add('pieces', $att . ' pièce' . ($att > 1 ? 's' : ''), 'plain', 'Pièces jointes');
        }
        if (!empty($item['linked'])) {
            $add('liee', 'Liée à un dossier', 'info', 'Cette remontée est versée à un dossier.');
        }
        $readers = (int) ($item['readers'] ?? 0);
        if ($readers > 0) {
            $reads = max($readers, (int) ($item['reads'] ?? 0));
            $add('lectures', $readers . ' lecteur' . ($readers > 1 ? 's' : '') . ' · ' . $reads . ' lecture' . ($reads > 1 ? 's' : ''), 'plain', 'Lectures');
        }

        return $out;
    }
}
