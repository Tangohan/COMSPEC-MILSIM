<?php

declare(strict_types=1);

namespace App\Services\Jnet;

use App\Repositories\JnetUnitWorkspaceRepository;

/**
 * Outils de travail de l'espace d'une unité JNET : tâches, pages écrites ensemble (blocs, réactions, commentaires,
 * sondages), suivi des membres et prochain rendez-vous. Aucune donnée d'exemple : tout vient des membres, de l'ORBAT,
 * des échanges, des événements de la communauté et des absences.
 *
 * Droits : aucun droit nouveau. Écrire (tâche, page, commentaire, vote, réponse de présence) demande de pouvoir publier
 * dans l'espace (JnetSpaceTree::canPostIn) ; supprimer une page ou une tâche d'un autre demande d'être chef de l'espace
 * (même droit que « Personnaliser »). Chaque contrôle est refait côté serveur par JnetUnitWorkspaceController.
 */
final class JnetUnitWorkspaceService
{
    /** Modules que le chef d'unité peut masquer (clé => libellé de l'onglet). */
    public const HIDEABLE = [
        'taches' => 'Tâches',
        'pages' => 'Pages',
        'photos' => 'Photos',
        'documents' => 'Documents',
    ];

    public const TASK_LABELS = ['todo' => 'À faire', 'doing' => 'En cours', 'done' => 'Fait'];

    public const REACTIONS = ['vu' => 'Vu', 'recu' => 'Reçu', 'question' => 'Question'];

    public const BLOCK_TYPES = [
        'p' => ['Texte', 'Paragraphe simple'],
        'h' => ['Titre', 'Intertitre de section'],
        'todo' => ['Tâche', 'Case à cocher'],
        'call' => ['Encadré', 'Point d’attention mis en avant'],
        'poll' => ['Sondage', 'Question et choix, un vote par membre'],
        'count' => ['Compte à rebours', 'Jours avant le prochain rendez-vous'],
        'div' => ['Séparateur', 'Filet horizontal'],
    ];

    public const TITLE_MAX = 160;
    public const TASK_MAX = 200;
    public const TEXT_MAX = 2000;
    public const COMMENT_MAX = 1000;
    public const BLOCKS_MAX = 150;
    public const POLL_OPTIONS_MAX = 8;
    public const POLL_OPTION_MAX = 80;

    public function __construct(private ?JnetUnitWorkspaceRepository $repo = null)
    {
        $this->repo ??= new JnetUnitWorkspaceRepository();
    }

    public function repo(): JnetUnitWorkspaceRepository
    {
        return $this->repo;
    }

    public function ready(): bool
    {
        return $this->repo->ready();
    }

    // ——— Modules ———

    /**
     * Modules masqués par l'unité, depuis la colonne hidden_modules (liste séparée par des virgules).
     *
     * @return list<string>
     */
    public static function parseHidden(mixed $raw): array
    {
        $out = [];
        foreach (explode(',', (string) $raw) as $key) {
            $key = trim($key);
            if (isset(self::HIDEABLE[$key])) {
                $out[$key] = $key;
            }
        }

        return array_values($out);
    }

    /** Valeur à stocker pour les modules cochés « affichés » dans le formulaire : tout ce qui n'est pas coché est masqué. */
    public static function hiddenFromShown(mixed $shown): string
    {
        $shown = is_array($shown) ? array_map('strval', $shown) : [];

        return implode(',', array_values(array_filter(array_keys(self::HIDEABLE), static fn (string $k): bool => !in_array($k, $shown, true))));
    }

    // ——— Blocs d'une page ———

    /** @return array<string, mixed> */
    public static function newBlock(string $type): array
    {
        $type = isset(self::BLOCK_TYPES[$type]) ? $type : 'p';
        $b = ['id' => self::newId(), 't' => $type, 'text' => ''];
        if ($type === 'todo') {
            $b['done'] = false;
        }
        if ($type === 'poll') {
            $b['opts'] = ['', ''];
        }

        return $b;
    }

    public static function newId(): string
    {
        return bin2hex(random_bytes(4));
    }

    /**
     * Blocs nettoyés depuis un envoi de formulaire ($raw = blocks[i][t|text|done|opts|id]) ou depuis le JSON stocké.
     * Types inconnus ignorés, textes bornés, au plus BLOCKS_MAX blocs ; un sondage garde 2 à 8 choix.
     *
     * @return list<array<string, mixed>>
     */
    public static function sanitizeBlocks(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        $seen = [];
        foreach ($raw as $b) {
            if (!is_array($b) || count($out) >= self::BLOCKS_MAX) {
                continue;
            }
            $type = (string) ($b['t'] ?? 'p');
            if (!isset(self::BLOCK_TYPES[$type])) {
                continue;
            }
            $id = (string) ($b['id'] ?? '');
            if (preg_match('/^[a-f0-9]{8}$/', $id) !== 1 || isset($seen[$id])) {
                $id = self::newId();
            }
            $seen[$id] = true;
            $max = $type === 'poll' ? 200 : self::TEXT_MAX;
            $block = ['id' => $id, 't' => $type, 'text' => $type === 'div' || $type === 'count' ? '' : self::clean((string) ($b['text'] ?? ''), $max, $type === 'h' || $type === 'poll')];
            if ($type === 'todo') {
                $block['done'] = !empty($b['done']);
            }
            if ($type === 'poll') {
                $opts = $b['opts'] ?? [];
                if (is_string($opts)) {
                    $opts = preg_split('/\R/u', $opts) ?: [];
                }
                $clean = [];
                foreach ((array) $opts as $o) {
                    $o = self::clean((string) $o, self::POLL_OPTION_MAX, true);
                    if ($o !== '' && count($clean) < self::POLL_OPTIONS_MAX) {
                        $clean[] = $o;
                    }
                }
                while (count($clean) < 2) {
                    $clean[] = '';
                }
                $block['opts'] = $clean;
            }
            $out[] = $block;
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    public static function blocksFromJson(string $json): array
    {
        $data = json_decode($json, true);

        return self::sanitizeBlocks(is_array($data) ? $data : []);
    }

    /** @param list<array<string, mixed>> $blocks */
    public static function blocksToJson(array $blocks): string
    {
        return (string) json_encode(array_values($blocks), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Gestes de l'éditeur : « add:TYPE », « up:N », « down:N », « del:N ». Retourne les blocs modifiés (au moins un bloc).
     *
     * @param list<array<string, mixed>> $blocks
     * @return list<array<string, mixed>>
     */
    public static function applyBlockAction(array $blocks, string $action): array
    {
        if (preg_match('/^(add|up|down|del):([a-z0-9]+)$/', $action, $m) === 1) {
            $n = $m[2];
            switch ($m[1]) {
                case 'add':
                    if (count($blocks) < self::BLOCKS_MAX) {
                        $blocks[] = self::newBlock($n);
                    }
                    break;
                case 'up':
                case 'down':
                    $i = (int) $n;
                    $j = $m[1] === 'up' ? $i - 1 : $i + 1;
                    if (isset($blocks[$i], $blocks[$j])) {
                        [$blocks[$i], $blocks[$j]] = [$blocks[$j], $blocks[$i]];
                    }
                    break;
                case 'del':
                    if (isset($blocks[(int) $n])) {
                        array_splice($blocks, (int) $n, 1);
                    }
                    break;
            }
        }
        $blocks = array_values($blocks);

        return $blocks !== [] ? $blocks : [self::newBlock('p')];
    }

    public static function clean(string $text, int $max, bool $singleLine = false): string
    {
        $text = str_replace("\r\n", "\n", $text);
        $text = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text);
        $text = $singleLine ? trim((string) preg_replace('/\s+/u', ' ', $text)) : trim($text);

        return mb_substr($text, 0, $max);
    }

    // ——— Tâches ———

    /** @param list<array<string, mixed>> $tasks @return array<string, list<array<string, mixed>>> */
    public static function tasksByStatus(array $tasks): array
    {
        $out = ['todo' => [], 'doing' => [], 'done' => []];
        foreach ($tasks as $t) {
            $out[isset($out[(string) $t['status']]) ? (string) $t['status'] : 'todo'][] = $t;
        }

        return $out;
    }

    /** Le membre peut-il retirer ou modifier cette tâche ? Auteur, responsable, ou chef de l'espace. */
    public static function canEditTask(array $task, int $userId, bool $isChief): bool
    {
        return $isChief || (int) ($task['created_by'] ?? 0) === $userId || (int) ($task['assignee_user_id'] ?? 0) === $userId;
    }

    /** @return string|null message d'erreur */
    public function addTask(int $tenantId, int $unitId, int $userId, string $title, string $status, ?int $assignee, array $memberIds): ?string
    {
        if (!$this->ready()) {
            return 'Les tâches ne sont pas encore activées (étape jnet_unit_workspace à appliquer).';
        }
        $title = self::clean($title, self::TASK_MAX, true);
        if ($title === '') {
            return 'Donnez un intitulé à la tâche.';
        }
        if (!isset(self::TASK_LABELS[$status])) {
            $status = 'todo';
        }
        if ($assignee !== null && !in_array($assignee, $memberIds, true)) {
            $assignee = null;
        }
        $this->repo->addTask($tenantId, $unitId, $userId, $title, $status, $assignee);

        return null;
    }

    // ——— Rendez-vous ———

    /** Jours entiers avant une date (0 = aujourd'hui), null si la date est illisible ou passée. */
    public static function daysUntil(string $startsAt, ?\DateTimeImmutable $now = null): ?int
    {
        $ts = strtotime($startsAt);
        if ($ts === false) {
            return null;
        }
        $now ??= new \DateTimeImmutable('now');
        $a = $now->setTime(0, 0);
        $b = (new \DateTimeImmutable('@' . $ts))->setTimezone($now->getTimezone())->setTime(0, 0);
        $d = (int) $a->diff($b)->format('%r%a');

        return $d >= 0 ? $d : null;
    }

    public static function countdownLabel(?int $days): string
    {
        return $days === null ? '' : ($days === 0 ? 'Aujourd’hui' : 'J-' . $days);
    }

    /**
     * Prochain événement de la communauté (module Événements), avec la réponse du lecteur et celle des membres de
     * l'unité. Null si le module est indisponible ou si rien n'est programmé.
     *
     * @param list<int> $memberIds
     * @return array<string, mixed>|null
     */
    public function nextEvent(int $tenantId, int $viewerId, array $memberIds): ?array
    {
        try {
            $gate = \App\Core\Container::get(\App\Services\Platform\FeatureGateService::class);
            if (!$gate->allowsLimitedFeatureModule($tenantId, 'events')) {
                return null;
            }
            $events = new \App\Repositories\CommunityEventRepository();
            $e = $events->upcomingForTenantWithUserRsvp($tenantId, $viewerId, 1)[0] ?? null;
            if (!is_array($e)) {
                return null;
            }
            $answers = [];
            foreach ($events->listRsvpsWithUsersForEvent((int) $e['id']) as $r) {
                $answers[(int) $r['user_id']] = (string) $r['status'];
            }
        } catch (\Throwable) {
            return null;
        }
        $counts = ['yes' => 0, 'maybe' => 0, 'no' => 0, 'none' => 0];
        $byMember = [];
        foreach ($memberIds as $m) {
            $a = $answers[$m] ?? 'none';
            $a = isset($counts[$a]) ? $a : 'none';
            $counts[$a]++;
            $byMember[$m] = $a;
        }
        $startsAt = (string) ($e['starts_at'] ?? '');
        $ts = strtotime($startsAt);
        $days = self::daysUntil($startsAt);

        return [
            'id' => (int) $e['id'],
            'title' => (string) ($e['title'] ?? ''),
            'location' => trim((string) ($e['location'] ?? '')),
            'when' => $ts ? self::frenchDate($ts) : '',
            'days' => $days,
            'countdown' => self::countdownLabel($days),
            'mine' => (string) ($e['rsvp_status'] ?? ''),
            'counts' => $counts,
            'total' => count($memberIds),
            'byMember' => $byMember,
            'href' => url('evenements'),
        ];
    }

    public static function frenchDate(int $ts): string
    {
        $days = ['dim.', 'lun.', 'mar.', 'mer.', 'jeu.', 'ven.', 'sam.'];
        $months = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];

        return ucfirst($days[(int) date('w', $ts)]) . ' ' . date('j', $ts) . ' ' . $months[(int) date('n', $ts) - 1] . ' · ' . date('H:i', $ts);
    }

    /**
     * Suivi des membres d'un espace : lignes prêtes à afficher. Lecture seule ; le chef voit aussi les qualifications échues.
     *
     * @param list<array<string, mixed>> $members cartes personnel des membres directs
     * @param list<array<string, mixed>> $exchanges échanges reçus et internes déjà décorés (id, requiresAck)
     * @param array<string, mixed>|null $event résultat de nextEvent()
     * @return list<array<string, mixed>>
     */
    public function tracking(int $tenantId, int $viewerId, array $members, array $exchanges, ?array $event, bool $isChief): array
    {
        $ackIds = [];
        foreach ($exchanges as $ex) {
            if (!empty($ex['requiresAck'])) {
                $ackIds[(int) $ex['id']] = (int) $ex['id'];
            }
        }
        $ackIds = array_values($ackIds);
        $readers = [];
        $absent = [];
        $expired = [];
        try {
            $readers = $ackIds !== [] ? (new \App\Repositories\JnetExchangeRepository())->readersFor($ackIds) : [];
        } catch (\Throwable) {
        }
        try {
            $absences = new \App\Repositories\PersonnelAbsenceRepository();
            $absent = $absences->tableExists() ? array_map('intval', $absences->userIdsAbsentToday($tenantId)) : [];
        } catch (\Throwable) {
        }
        if ($isChief) {
            try {
                $quals = new \App\Repositories\PersonnelQualificationRepository();
                foreach (array_slice($members, 0, 80) as $p) {
                    foreach ($quals->listForUser((int) $p['id'], $tenantId) as $q) {
                        $exp = trim((string) ($q['expires_at'] ?? ''));
                        if ((string) ($q['status'] ?? '') === 'expired' || ($exp !== '' && ($t = strtotime($exp)) !== false && $t < time())) {
                            $expired[(int) $p['id']] = true;
                            break;
                        }
                    }
                }
            } catch (\Throwable) {
            }
        }

        return self::trackingRows($members, $viewerId, $ackIds, $readers, $event !== null ? (array) $event['byMember'] : null, $absent, $expired);
    }

    // ——— Relance des sans-réponse ———

    /** Délai pendant lequel un même rappel n'est pas renvoyé (heures). */
    public const REMIND_COOLDOWN_HOURS = 6;

    /**
     * Membres de l'unité qui n'ont pas répondu au prochain rendez-vous, le lecteur exclu. Seuls les membres directs de
     * l'unité (déjà pris dans $memberIds par le contrôleur, depuis l'ORBAT) sont retenus : jamais une liste envoyée par
     * le navigateur.
     *
     * @param array<string, mixed>|null $event résultat de nextEvent()
     * @param list<int> $memberIds
     * @return list<int>
     */
    public static function unansweredIds(?array $event, array $memberIds, int $viewerId): array
    {
        if ($event === null) {
            return [];
        }
        $by = is_array($event['byMember'] ?? null) ? $event['byMember'] : [];
        $out = [];
        foreach ($memberIds as $m) {
            $m = (int) $m;
            if ($m > 0 && $m !== $viewerId && ($by[$m] ?? 'none') === 'none') {
                $out[$m] = $m;
            }
        }

        return array_values($out);
    }

    /** Objet du rappel : stable pour un même rendez-vous et une même unité, ce qui sert à ne pas le renvoyer en boucle. */
    public static function reminderSubject(string $eventTitle, string $unitLabel): string
    {
        return mb_substr('Rappel de présence : ' . self::clean($eventTitle, 80, true) . ' (' . self::clean($unitLabel, 50, true) . ')', 0, 150);
    }

    /**
     * Envoie un rappel aux membres sans réponse par la messagerie JNET existante (un fil de diffusion, avec les notifications
     * par e-mail du service existant quand la diffusion est petite). Retourne ['count' => n, 'error' => message ou null].
     *
     * @param array<string, mixed>|null $event
     * @param list<int> $memberIds
     * @return array{count: int, error: ?string}
     */
    public function remind(int $tenantId, int $senderId, string $unitLabel, string $unitUrl, ?array $event, array $memberIds): array
    {
        if ($event === null) {
            return ['count' => 0, 'error' => 'Aucun rendez-vous n’est programmé : rien à relancer.'];
        }
        $recipients = self::unansweredIds($event, $memberIds, $senderId);
        if ($recipients === []) {
            return ['count' => 0, 'error' => 'Tout le monde a déjà répondu.'];
        }
        $subject = self::reminderSubject((string) $event['title'], $unitLabel);
        if ($this->repo->reminderSentRecently($tenantId, $subject, self::REMIND_COOLDOWN_HOURS)) {
            return ['count' => 0, 'error' => 'Un rappel a déjà été envoyé il y a moins de ' . self::REMIND_COOLDOWN_HOURS . ' h.'];
        }
        try {
            $featureGate = \App\Core\Container::get(\App\Services\Platform\FeatureGateService::class);
            if (!$featureGate->allows($tenantId, 'messages')) {
                return ['count' => 0, 'error' => 'La messagerie n’est pas disponible pour cette communauté.'];
            }
            if (!\App\Core\Container::get(\App\Services\Moderation\ModerationRestrictionResolver::class)->canSendMessages($tenantId, $senderId)) {
                return ['count' => 0, 'error' => 'Vous ne pouvez pas envoyer de message pour le moment.'];
            }
        } catch (\Throwable) {
        }
        $body = 'Votre réponse est attendue pour « ' . (string) $event['title'] . ' »'
            . ((string) ($event['when'] ?? '') !== '' ? ', ' . (string) $event['when'] : '') . '.'
            . "\n\nRépondez en un clic depuis l'espace de " . $unitLabel . ' : ' . $unitUrl . ' Merci.';
        try {
            $messages = new \App\Repositories\TenantMessageRepository();
            $threadId = $messages->createDiffusionThread(
                $tenantId,
                $senderId,
                $subject,
                array_fill_keys($recipients, null),
                'routine',
                count($recipients) . ' membre' . (count($recipients) > 1 ? 's' : '') . ' sans réponse',
                'jnet'
            );
            $messages->addMessage($threadId, $senderId, $body);
            $messages->markThreadRead($threadId, $senderId);
            if (count($recipients) <= (new JnetMessagingService())->emailNotificationLimit()) {
                try {
                    \App\Core\Container::get(\App\Services\Community\TenantInternalMessageNotificationService::class)
                        ->notifyAfterMessage($tenantId, $threadId, $senderId, $body);
                } catch (\Throwable) {
                }
            }
        } catch (\Throwable) {
            return ['count' => 0, 'error' => 'Le rappel n’a pas pu être envoyé.'];
        }

        return ['count' => count($recipients), 'error' => null];
    }

    // ——— Suivi des membres ———

    /**
     * Lignes de suivi : une par membre, avec statut, réponse de présence, consignes lues et points à traiter. Les
     * « consignes » sont les échanges à accuser reçus ou internes à l'espace (fenêtre des derniers affichés).
     *
     * @param list<array<string, mixed>> $members cartes personnel (id, name, grade, function, duty, photo, initials, href)
     * @param list<int> $ackExchangeIds échanges à accuser visibles dans l'espace
     * @param array<int, list<int>> $readers échange => lecteurs
     * @param array<int, string>|null $rsvp utilisateur => yes|maybe|no|none (null : pas de rendez-vous)
     * @param list<int> $absentToday
     * @param array<int, bool> $expiredQual utilisateur => a une qualification échue (réservé au chef)
     * @return list<array<string, mixed>>
     */
    public static function trackingRows(array $members, int $viewerId, array $ackExchangeIds, array $readers, ?array $rsvp, array $absentToday, array $expiredQual = []): array
    {
        $total = count($ackExchangeIds);
        $rows = [];
        foreach ($members as $p) {
            $uid = (int) ($p['id'] ?? 0);
            $read = 0;
            foreach ($ackExchangeIds as $ex) {
                if (in_array($uid, $readers[$ex] ?? [], true)) {
                    $read++;
                }
            }
            $status = in_array($uid, $absentToday, true) ? 'absence' : (($p['duty'] ?? '') === 'off' ? 'off' : 'active');
            $answer = $rsvp === null ? '' : ($rsvp[$uid] ?? 'none');
            $todo = [];
            if ($total > 0 && $read < $total) {
                $left = $total - $read;
                $todo[] = ['label' => $left . ' consigne' . ($left > 1 ? 's' : '') . ' à lire', 'tone' => 'warn'];
            }
            if ($answer === 'none') {
                $todo[] = ['label' => 'Réponse de présence attendue', 'tone' => 'bad'];
            }
            if ($status === 'absence') {
                $todo[] = ['label' => 'En absence déclarée', 'tone' => 'warn'];
            }
            if (!empty($expiredQual[$uid])) {
                $todo[] = ['label' => 'Qualification échue', 'tone' => 'bad'];
            }
            $rows[] = [
                'id' => $uid,
                'name' => (string) ($p['name'] ?? ''),
                'callsign' => (string) ($p['callsign'] ?? ''),
                'grade' => (string) ($p['grade'] ?? ''),
                'function' => (string) ($p['function'] ?? ''),
                'photo' => $p['photo'] ?? null,
                'initials' => (string) ($p['initials'] ?? '?'),
                'href' => (string) ($p['href'] ?? ''),
                'status' => $status,
                'answer' => $answer,
                'read' => $read,
                'readTotal' => $total,
                'todo' => $todo,
                'me' => $uid === $viewerId,
            ];
        }

        return $rows;
    }
}
