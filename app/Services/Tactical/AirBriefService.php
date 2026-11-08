<?php

declare(strict_types=1);

namespace App\Services\Tactical;

use App\Repositories\CasNineLineRepository;
use PDO;

/**
 * Envoi des 9-LINE / 5-LINE au pilote et suivi de leurs accusés, indicatifs choisis sur le site.
 *
 * - Destinataires (atak_nine_line_recipients) : un par demande et par appareil (indicatif remonté par le jeu ou saisi
 *   à la main), avec l'équipage connu (noms de jeu) pour reconnaître le pilote ; statut sent → received → read →
 *   wilco | unable, puis done (mission clôturée par le pilote), jamais en arrière (un accusé « reçu » arrivé après « lu » est ignoré).
 * - Boîte du pilote : GET /api/cas?assignedTo=~air|nom1|nom2 (fonction GetCASForCallsign de la DLL, sans changement),
 *   réponse en tableau SQF (parseSimpleArray) bornée à quelques Ko (la DLL coupe au-delà de 8 000 octets).
 * - Accusés du pilote : POST /api/cas/{ref}/status {"status":"WILCO:HOG 21"} (SendCASState de la DLL), ref = mission_id
 *   donné par le jeu ou identifiant Athena.
 * - Indicatifs (atak_brief_callsigns) : indicatif JTAC d'un membre (scope jtac), indicatif affiché d'un appareil remonté
 *   (scope aircraft, clé = indicatif remonté par le jeu, qui reste celui qui sert à joindre l'appareil).
 *
 * Tables absentes (migration 3720 pas encore passée) : tout se replie sans erreur (aucun destinataire, aucun indicatif).
 */
final class AirBriefService
{
    public const STATUSES = ['sent', 'received', 'read', 'wilco', 'unable', 'done'];

    /** Rang d'un statut : un accusé ne fait jamais reculer un destinataire. */
    private const RANK = ['sent' => 0, 'received' => 1, 'read' => 2, 'wilco' => 3, 'unable' => 3, 'done' => 4];

    /** Préfixe de la boîte du pilote dans assignedTo (les anciens appels gardent l'égalité stricte). */
    public const INBOX_PREFIX = '~air|';

    /** Boîte du pilote : demandes des 3 dernières heures, 6 au plus, réponse sous 6 500 octets. */
    public const INBOX_WINDOW_SECONDS = 10800;

    public const INBOX_MAX = 6;

    public const INBOX_MAX_BYTES = 6500;

    public const MAX_RECIPIENTS = 12;

    /** Indicatifs proposés (préfixes usuels, chiffres à compléter). */
    public const SUGGESTED_JTAC = ['HAMMER 11', 'WARHAWK 21', 'JTAC 1', 'PLAYBOY 31', 'SHADOW 41', 'RAGE 51'];

    public const SUGGESTED_AIRCRAFT = ['HOG 21', 'HAWG 11', 'VIPER 11', 'DUDE 01', 'SANDY 31', 'REAPER 41', 'UGLY 51', 'GUNFIGHTER 61', 'SPOOKY 71', 'BONE 21'];

    private ?bool $hasRecipients = null;

    private ?bool $hasCallsigns = null;

    public function __construct(private ?PDO $pdo = null)
    {
    }

    /** Indicatif propre : majuscules, espaces simples, lettres, chiffres et - _ . / ; 32 caractères au plus. */
    public static function cleanCallsign(mixed $raw): string
    {
        $s = strip_tags(is_scalar($raw) ? (string) $raw : '');
        $s = (string) preg_replace('/[^\p{L}\p{N} \-_.\/]+/u', ' ', $s);
        $s = trim((string) preg_replace('/\s+/u', ' ', $s));

        return mb_substr(mb_strtoupper($s), 0, 32);
    }

    /** « WILCO:HOG 21 » → ['wilco', 'HOG 21'] ; null si ce n'est pas un accusé de brief. */
    public static function parseAck(mixed $status): ?array
    {
        $s = trim(is_scalar($status) ? (string) $status : '');
        if (!preg_match('/^(RECEIVED|DELIVERED|READ|WILCO|UNABLE|DONE)(?:\s*[:|]\s*(.*))?$/i', $s, $m)) {
            return null;
        }
        $st = strtolower($m[1]) === 'delivered' ? 'received' : strtolower($m[1]);

        return [$st, self::cleanCallsign($m[2] ?? '')];
    }

    /**
     * Destinataires d'une demande, dédoublonnés par indicatif (12 au plus).
     *
     * @param list<array{callsign?: mixed, label?: mixed, crew?: mixed}|string> $recipients
     * @return list<array{callsign: string, label: string, crew: list<string>}>
     */
    public static function normalizeRecipients(array $recipients): array
    {
        $out = [];
        foreach ($recipients as $r) {
            $r = is_array($r) ? $r : ['callsign' => $r];
            $cs = self::cleanCallsign($r['callsign'] ?? '');
            if ($cs === '' || isset($out[$cs])) {
                continue;
            }
            $label = self::cleanCallsign($r['label'] ?? '');
            $crew = [];
            foreach (is_array($r['crew'] ?? null) ? $r['crew'] : [] as $n) {
                $n = trim((string) preg_replace('/\s+/u', ' ', is_scalar($n) ? (string) $n : ''));
                if ($n !== '' && !in_array($n, $crew, true)) {
                    $crew[] = mb_substr(str_replace('|', ' ', $n), 0, 60);
                }
            }
            $out[$cs] = ['callsign' => $cs, 'label' => $label !== '' ? $label : $cs, 'crew' => array_slice($crew, 0, 8)];
            if (count($out) >= self::MAX_RECIPIENTS) {
                break;
            }
        }

        return array_values($out);
    }

    /**
     * Enregistre les destinataires d'une demande (statut « envoyé »). Équipage complété depuis les appareils remontés
     * par le jeu quand le destinataire est un appareil connu ; libellé = indicatif choisi sur le site.
     *
     * @param list<array{callsign?: mixed, label?: mixed, crew?: mixed}|string> $recipients
     * @return list<array<string, mixed>>
     */
    public function addRecipients(int $tenantId, int $lineId, array $recipients, ?int $now = null): array
    {
        $list = self::normalizeRecipients($recipients);
        if ($list === [] || $lineId < 1 || !$this->hasRecipientsTable()) {
            return [];
        }
        $now ??= time();
        $overrides = $this->aircraftOverrides($tenantId);
        $known = $this->knownCrews($tenantId, array_column($list, 'callsign'), $now);
        $date = date('Y-m-d H:i:s', $now);
        $exists = $this->db()->prepare('SELECT id FROM atak_nine_line_recipients WHERE nine_line_id = ? AND callsign = ?');
        $ins = $this->db()->prepare(
            'INSERT INTO atak_nine_line_recipients (tenant_id, nine_line_id, callsign, label, crew_names, status, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        foreach ($list as $r) {
            $exists->execute([$lineId, $r['callsign']]);
            if ($exists->fetchColumn() !== false) {
                continue;
            }
            $crew = array_values(array_unique(array_merge($r['crew'], $known[$r['callsign']] ?? [])));
            $label = $overrides[$r['callsign']] ?? $r['label'];
            $ins->execute([$tenantId, $lineId, $r['callsign'], $label, $crew === [] ? null : mb_substr('|' . implode('|', $crew) . '|', 0, 500), 'sent', $date, $date]);
        }

        return $this->forLines($tenantId, [$lineId])[$lineId] ?? [];
    }

    /**
     * Destinataires de plusieurs demandes, par demande.
     *
     * @param list<int> $lineIds
     * @return array<int, list<array<string, mixed>>>
     */
    public function forLines(int $tenantId, array $lineIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $lineIds), static fn (int $i): bool => $i > 0)));
        if ($ids === [] || !$this->hasRecipientsTable()) {
            return [];
        }
        $st = $this->db()->prepare(
            'SELECT * FROM atak_nine_line_recipients WHERE tenant_id = ? AND nine_line_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ') ORDER BY id'
        );
        $st->execute(array_merge([$tenantId], $ids));
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
            $out[(int) $r['nine_line_id']][] = self::presentRecipient($r);
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $r
     * @return array<string, mixed>
     */
    public static function presentRecipient(array $r): array
    {
        $status = in_array((string) ($r['status'] ?? ''), self::STATUSES, true) ? (string) $r['status'] : 'sent';
        $crew = array_values(array_filter(explode('|', (string) ($r['crew_names'] ?? '')), static fn (string $n): bool => $n !== ''));

        return [
            'callsign' => (string) ($r['callsign'] ?? ''),
            'label' => (string) (($r['label'] ?? '') !== '' ? $r['label'] : ($r['callsign'] ?? '')),
            'crew' => $crew,
            'status' => $status,
            'ack_by' => (string) ($r['ack_by'] ?? ''),
            'received_at' => (string) ($r['received_at'] ?? ''),
            'read_at' => (string) ($r['read_at'] ?? ''),
            'ack_at' => (string) ($r['ack_at'] ?? ''),
            'updated_at' => (string) ($r['updated_at'] ?? $r['created_at'] ?? ''),
        ];
    }

    /**
     * Demande désignée par un accusé du jeu : identifiant Athena (« 42 » ou « A42 ») ou mission_id donné par le jeu.
     *
     * @return array<string, mixed>|null
     */
    public function resolveLine(int $tenantId, string $ref): ?array
    {
        $ref = trim($ref);
        if ($ref === '') {
            return null;
        }
        if (preg_match('/^A?(\d{1,10})$/', $ref, $m)) {
            $st = $this->db()->prepare('SELECT * FROM atak_nine_line WHERE tenant_id = ? AND id = ?');
            $st->execute([$tenantId, (int) $m[1]]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if (is_array($row)) {
                return $row;
            }
        }
        $st = $this->db()->prepare('SELECT * FROM atak_nine_line WHERE tenant_id = ? AND mission_id = ? ORDER BY id DESC LIMIT 1');
        $st->execute([$tenantId, mb_substr($ref, 0, 128)]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * Accusé d'un pilote (reçu, lu, WILCO, UNABLE). Destinataire reconnu par son indicatif, son libellé ou son
     * équipage ; inconnu (indicatif saisi autrement) : ajouté. Le statut n'avance que vers l'avant (WILCO et UNABLE se
     * remplacent l'un l'autre).
     *
     * @return array{result: 'ok'|'not_found'|'unavailable', recipient: ?array<string, mixed>, line: ?array<string, mixed>}
     */
    public function acknowledge(int $tenantId, string $ref, string $status, string $by, ?int $now = null): array
    {
        $status = strtolower($status);
        if (!isset(self::RANK[$status])) {
            return ['result' => 'not_found', 'recipient' => null, 'line' => null];
        }
        if (!$this->hasRecipientsTable()) {
            return ['result' => 'unavailable', 'recipient' => null, 'line' => null];
        }
        $line = $this->resolveLine($tenantId, $ref);
        if ($line === null) {
            return ['result' => 'not_found', 'recipient' => null, 'line' => null];
        }
        $lineId = (int) $line['id'];
        $by = self::cleanCallsign($by);
        $now ??= time();
        $date = date('Y-m-d H:i:s', $now);
        $st = $this->db()->prepare('SELECT * FROM atak_nine_line_recipients WHERE tenant_id = ? AND nine_line_id = ? ORDER BY id');
        $st->execute([$tenantId, $lineId]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $match = null;
        $key = self::key($by);
        foreach ($rows as $r) {
            $names = array_merge([(string) $r['callsign'], (string) ($r['label'] ?? '')], explode('|', (string) ($r['crew_names'] ?? '')));
            foreach ($names as $n) {
                if ($key !== '' && self::key($n) === $key) {
                    $match = $r;
                    break 2;
                }
            }
        }
        if ($match === null && $by === '' && count($rows) === 1) {
            $match = $rows[0];
        }
        if ($match === null) {
            if ($by === '') {
                return ['result' => 'not_found', 'recipient' => null, 'line' => $line];
            }
            $this->db()->prepare(
                'INSERT INTO atak_nine_line_recipients (tenant_id, nine_line_id, callsign, label, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)'
            )->execute([$tenantId, $lineId, $by, $by, 'sent', $date, $date]);
            $st->execute([$tenantId, $lineId]);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
                if ((string) $r['callsign'] === $by) {
                    $match = $r;
                }
            }
            if ($match === null) {
                return ['result' => 'not_found', 'recipient' => null, 'line' => $line];
            }
        }
        $cur = (string) ($match['status'] ?? 'sent');
        $curRank = self::RANK[$cur] ?? 0;
        $newRank = self::RANK[$status];
        if ($newRank > $curRank || ($newRank === 3 && $curRank === 3 && $cur !== $status)) {
            $set = ['status = ?', 'updated_at = ?'];
            $params = [$status, $date];
            if ($status === 'received' || ($match['received_at'] ?? null) === null) {
                $set[] = 'received_at = COALESCE(received_at, ?)';
                $params[] = $date;
            }
            if ($newRank >= 2) {
                $set[] = 'read_at = COALESCE(read_at, ?)';
                $params[] = $date;
            }
            if ($newRank === 3) {
                $set[] = 'ack_at = ?';
                $params[] = $date;
                $set[] = 'ack_by = ?';
                $params[] = $by !== '' ? $by : null;
            }
            $params[] = (int) $match['id'];
            $this->db()->prepare('UPDATE atak_nine_line_recipients SET ' . implode(', ', $set) . ' WHERE id = ?')->execute($params);
            $one = $this->db()->prepare('SELECT * FROM atak_nine_line_recipients WHERE id = ?');
            $one->execute([(int) $match['id']]);
            $match = $one->fetch(PDO::FETCH_ASSOC) ?: $match;
        }

        return ['result' => 'ok', 'recipient' => self::presentRecipient($match), 'line' => $line];
    }

    /**
     * Boîte du pilote : demandes adressées à l'un de ces noms (indicatif de l'appareil, indicatif ATAK du joueur, nom
     * de jeu), des 3 dernières heures, la plus récente d'abord.
     *
     * @param list<string> $names
     * @return list<array<string, mixed>>
     */
    public function inbox(int $tenantId, array $names, ?int $now = null): array
    {
        $keys = [];
        foreach ($names as $n) {
            $k = self::key((string) $n);
            if ($k !== '' && !in_array($k, ['operateur', 'pilote', 'unknown', 'inconnu'], true)) {
                $keys[$k] = true;
            }
        }
        if ($keys === [] || !$this->hasRecipientsTable()) {
            return [];
        }
        $now ??= time();
        $st = $this->db()->prepare(
            'SELECT r.callsign AS r_callsign, r.label AS r_label, r.crew_names AS r_crew, r.status AS r_status, n.*
             FROM atak_nine_line_recipients r JOIN atak_nine_line n ON n.id = r.nine_line_id
             WHERE r.tenant_id = ? AND n.tenant_id = ? AND r.created_at >= ? ORDER BY n.id DESC, r.id LIMIT 200'
        );
        $st->execute([$tenantId, $tenantId, date('Y-m-d H:i:s', $now - self::INBOX_WINDOW_SECONDS)]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
            $id = (int) $r['id'];
            if (isset($out[$id])) {
                continue;
            }
            $names = array_merge([(string) $r['r_callsign'], (string) ($r['r_label'] ?? '')], explode('|', (string) ($r['r_crew'] ?? '')));
            $hit = false;
            foreach ($names as $n) {
                if (isset($keys[self::key($n)])) {
                    $hit = true;
                    break;
                }
            }
            if (!$hit) {
                continue;
            }
            $kind = CasNineLineRepository::normalizeKind($r['mission_kind'] ?? 'cas');
            $count = $kind === CasNineLineRepository::KIND_FIVE ? 5 : 9;
            $lines = [];
            for ($i = 1; $i <= $count; $i++) {
                $lines[] = (string) ($r['line' . $i] ?? '');
            }
            $mission = trim((string) ($r['mission_id'] ?? ''));
            $out[$id] = [
                'ref' => $mission !== '' ? $mission : (string) $id,
                'id' => $id,
                'format' => self::format($kind),
                'from' => (string) ($r['author'] ?? 'JTAC'),
                'time' => date('H:i', strtotime((string) ($r['created_at'] ?? '')) ?: $now),
                'lines' => $lines,
                'remarks' => self::remarks($r),
                'status' => in_array((string) $r['r_status'], self::STATUSES, true) ? (string) $r['r_status'] : 'sent',
                'recipient' => (string) $r['r_callsign'],
            ];
            if (count($out) >= self::INBOX_MAX) {
                break;
            }
        }

        return array_values($out);
    }

    /** 9line, 5line ou medevac (format lu par le jeu). */
    public static function format(string $kind): string
    {
        return match (CasNineLineRepository::normalizeKind($kind)) {
            CasNineLineRepository::KIND_FIVE => '5line',
            CasNineLineRepository::KIND_MEDEVAC => 'medevac',
            default => '9line',
        };
    }

    /**
     * Boîte du pilote en tableau SQF (parseSimpleArray) : [[ref, id, format, émetteur, HH:MM, [lignes], remarques,
     * statut, destinataire], ...], textes coupés pour tenir sous INBOX_MAX_BYTES (on retire les plus anciennes sinon).
     *
     * @param list<array<string, mixed>> $items
     */
    public static function inboxSqf(array $items, int $maxBytes = self::INBOX_MAX_BYTES): string
    {
        $q = static function (mixed $v, int $max = 140): string {
            $t = trim((string) preg_replace('/[\s|]+/u', ' ', is_scalar($v) ? (string) $v : ''));

            return '"' . str_replace('"', '""', mb_substr($t, 0, $max)) . '"';
        };
        $parts = [];
        foreach ($items as $it) {
            $lines = array_map(static fn ($l): string => $q($l), is_array($it['lines'] ?? null) ? $it['lines'] : []);
            $parts[] = '[' . implode(',', [
                $q($it['ref'] ?? '', 60),
                (string) (int) ($it['id'] ?? 0),
                $q($it['format'] ?? '9line', 10),
                $q($it['from'] ?? 'JTAC', 40),
                $q($it['time'] ?? '', 5),
                '[' . implode(',', $lines) . ']',
                $q($it['remarks'] ?? '', 300),
                $q($it['status'] ?? 'sent', 10),
                $q($it['recipient'] ?? '', 40),
            ]) . ']';
        }
        while ($parts !== [] && strlen('[' . implode(',', $parts) . ']') > $maxBytes) {
            array_pop($parts);
        }

        return '[' . implode(',', $parts) . ']';
    }

    /** Remarques transmises avec la demande (colonne remarks), sinon tirées de la géométrie du jeu (FAH, danger close). */
    private static function remarks(array $row): string
    {
        $geo = is_string($row['geo_json'] ?? null) ? json_decode((string) $row['geo_json'], true) : null;
        $out = [];
        $own = trim((string) ($row['remarks'] ?? ''));
        if ($own !== '') {
            $out[] = $own;
        }
        if (is_array($geo) && $own === '') {
            if (($geo['fah'] ?? '') !== '') {
                $out[] = 'FAH ' . $geo['fah'];
            }
            if (!empty($geo['dangerClose'])) {
                $out[] = 'DANGER CLOSE';
            }
        }

        return implode(' · ', $out);
    }

    // ------------------------------------------------------------------ Indicatifs

    /** Indicatif JTAC choisi par ce membre sur le site, ou null. */
    public function jtacCallsign(int $tenantId, int $userId): ?string
    {
        if ($userId < 1 || !$this->hasCallsignsTable()) {
            return null;
        }
        $st = $this->db()->prepare('SELECT callsign FROM atak_brief_callsigns WHERE tenant_id = ? AND scope = ? AND owner_key = ?');
        $st->execute([$tenantId, 'jtac', (string) $userId]);
        $v = $st->fetchColumn();

        return is_string($v) && $v !== '' ? $v : null;
    }

    /** Indicatif JTAC du membre ; vide = retour à son indicatif Athena. Faux si la table manque. */
    public function setJtacCallsign(int $tenantId, int $userId, string $callsign): bool
    {
        return $this->setCallsign($tenantId, 'jtac', (string) $userId, $callsign, $userId);
    }

    /**
     * Indicatifs affichés des appareils (indicatif remonté par le jeu => indicatif choisi sur le site).
     *
     * @return array<string, string>
     */
    public function aircraftOverrides(int $tenantId): array
    {
        if (!$this->hasCallsignsTable()) {
            return [];
        }
        $st = $this->db()->prepare('SELECT owner_key, callsign FROM atak_brief_callsigns WHERE tenant_id = ? AND scope = ?');
        $st->execute([$tenantId, 'aircraft']);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
            $out[self::cleanCallsign($r['owner_key'])] = (string) $r['callsign'];
        }

        return $out;
    }

    /** Indicatif affiché d'un appareil remonté (clé : son indicatif de jeu) ; vide = celui du jeu. */
    public function setAircraftCallsign(int $tenantId, string $reported, string $callsign, int $userId): bool
    {
        $reported = self::cleanCallsign($reported);

        return $reported !== '' && $this->setCallsign($tenantId, 'aircraft', $reported, $callsign, $userId);
    }

    private function setCallsign(int $tenantId, string $scope, string $key, string $callsign, int $userId): bool
    {
        if (!$this->hasCallsignsTable()) {
            return false;
        }
        $callsign = self::cleanCallsign($callsign);
        $this->db()->prepare('DELETE FROM atak_brief_callsigns WHERE tenant_id = ? AND scope = ? AND owner_key = ?')->execute([$tenantId, $scope, $key]);
        if ($callsign !== '') {
            $this->db()->prepare('INSERT INTO atak_brief_callsigns (tenant_id, scope, owner_key, callsign, updated_by_user_id, updated_at) VALUES (?, ?, ?, ?, ?, ?)')
                ->execute([$tenantId, $scope, $key, $callsign, $userId > 0 ? $userId : null, date('Y-m-d H:i:s')]);
        }

        return true;
    }

    /**
     * Équipage connu des appareils remontés récemment (noms de jeu, pilote compris).
     *
     * @param list<string> $callsigns
     * @return array<string, list<string>>
     */
    private function knownCrews(int $tenantId, array $callsigns, int $now): array
    {
        if ($callsigns === []) {
            return [];
        }
        try {
            $st = $this->db()->prepare(
                'SELECT callsign, pilot, crew FROM atak_air_assets WHERE tenant_id = ? AND updated_at >= ? ORDER BY updated_at DESC LIMIT 60'
            );
            $st->execute([$tenantId, date('Y-m-d H:i:s', $now - NineLineBoardService::AIRCRAFT_WINDOW_SECONDS)]);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable) {
            return [];
        }
        $wanted = array_flip($callsigns);
        $out = [];
        foreach ($rows as $r) {
            $cs = self::cleanCallsign($r['callsign'] ?? '');
            if (!isset($wanted[$cs]) || isset($out[$cs])) {
                continue;
            }
            $names = [];
            $crew = $r['crew'] ?? null;
            $crew = is_string($crew) && $crew !== '' ? json_decode($crew, true) : $crew;
            foreach (is_array($crew) ? $crew : [] as $c) {
                $n = is_array($c) ? trim((string) ($c['name'] ?? '')) : '';
                if ($n !== '') {
                    $names[] = $n;
                }
            }
            $p = trim((string) ($r['pilot'] ?? ''));
            if ($p !== '' && strcasecmp($p, 'Pilote') !== 0) {
                $names[] = $p;
            }
            $out[$cs] = array_values(array_unique($names));
        }

        return $out;
    }

    private static function key(string $name): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $name)));
    }

    private function hasRecipientsTable(): bool
    {
        return $this->hasRecipients ??= $this->probe('SELECT id FROM atak_nine_line_recipients LIMIT 1');
    }

    private function hasCallsignsTable(): bool
    {
        return $this->hasCallsigns ??= $this->probe('SELECT id FROM atak_brief_callsigns LIMIT 1');
    }

    private function probe(string $sql): bool
    {
        try {
            return $this->db()->query($sql) !== false;
        } catch (\Throwable) {
            return false;
        }
    }

    private function db(): PDO
    {
        return $this->pdo ??= \App\Core\Database::getPdo();
    }
}
