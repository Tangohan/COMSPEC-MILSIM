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
 * - Indicatifs comparés par leur clé (callsignKey) : casse, accents, espaces, tirets, points et soulignés ignorés
 *   (« TIGER 6 », « tiger-6 » et « Tiger6 » désignent le même appareil), à l'enregistrement comme à la lecture.
 * - Boîte relevée par le téléphone du pilote : le brief passe « livré » (received_at, indicatif interrogé polled_as,
 *   dernier relevé last_polled_at : migration 4910), puis « lu » et « acquitté » par ses accusés (delivery()).
 * - Demande affectée à un appareil par assigned_aircraft seulement (sans destinataire) : trouvée aussi par la boîte.
 *
 * Tables absentes (migration 3720 pas encore passée) : tout se replie sans erreur (aucun destinataire, aucun indicatif) ;
 * colonnes de livraison absentes (4910) : statut et horodatages tenus quand même, sans indicatif interrogé ni dernier relevé.
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

    private ?bool $hasDelivery = null;

    /** États de livraison exposés au web (delivery.status), du moins au plus avancé (expired : plus proposé). */
    public const DELIVERY_STATUSES = ['expired', 'sent', 'delivered', 'read', 'acknowledged'];

    /** Noms jamais pris pour un indicatif (rôles génériques du jeu). */
    private const GENERIC_NAMES = ['operateur', 'pilote', 'pilot', 'unknown', 'inconnu', 'any', 'null', 'jtac'];

    /** @param (\Closure(string): void)|null $log journal discret (error_log par défaut) */
    public function __construct(private ?PDO $pdo = null, private ?\Closure $log = null)
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

    /**
     * Clé de comparaison d'un indicatif ou d'un nom : minuscules, accents retirés, lettres et chiffres seulement.
     * « TIGER 6 », « Tiger-6 », « tiger_6 » et « TIGER6 » donnent « tiger6 ». Même règle côté jeu (fn_airBrief "key").
     */
    public static function callsignKey(mixed $raw): string
    {
        $s = mb_strtolower(trim(is_scalar($raw) ? (string) $raw : ''));
        if ($s === '') {
            return '';
        }
        $s = strtr($s, [
            'à' => 'a', 'â' => 'a', 'ä' => 'a', 'á' => 'a', 'ç' => 'c', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'ì' => 'i', 'î' => 'i', 'ï' => 'i', 'í' => 'i', 'ò' => 'o', 'ô' => 'o', 'ö' => 'o', 'ó' => 'o', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ú' => 'u', 'ñ' => 'n',
        ]);

        return mb_substr((string) preg_replace('/[^\p{L}\p{N}]+/u', '', $s), 0, 64);
    }

    /**
     * Noms interrogés par la boîte du pilote (assignedTo=~air|nom1|nom2) : clé => nom tel que reçu, génériques retirés.
     *
     * @param list<mixed> $names
     * @return array<string, string>
     */
    public static function inboxKeys(array $names): array
    {
        $keys = [];
        foreach ($names as $n) {
            $raw = trim(is_scalar($n) ? (string) $n : '');
            $k = self::callsignKey($raw);
            if ($k !== '' && !in_array($k, self::GENERIC_NAMES, true) && !isset($keys[$k])) {
                $keys[$k] = mb_substr($raw, 0, 64);
            }
        }

        return $keys;
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
        $seen = [];
        foreach ($recipients as $r) {
            $r = is_array($r) ? $r : ['callsign' => $r];
            $cs = self::cleanCallsign($r['callsign'] ?? '');
            $k = self::callsignKey($cs);
            if ($k === '' || isset($seen[$k])) {
                continue;
            }
            $seen[$k] = true;
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
        $overrideKeys = [];
        foreach ($overrides as $reported => $shown) {
            $overrideKeys[self::callsignKey($reported)] = $shown;
        }
        foreach ($list as $r) {
            $exists->execute([$lineId, $r['callsign']]);
            if ($exists->fetchColumn() !== false) {
                continue;
            }
            $k = self::callsignKey($r['callsign']);
            $crew = array_values(array_unique(array_merge($r['crew'], $known[$k] ?? [])));
            $label = $overrides[$r['callsign']] ?? $overrideKeys[$k] ?? $r['label'];
            // Journal discret : aucun appareil remonté récemment sous cet indicatif (faute de frappe, pilote non relié) ;
            // le pilote ne le recevra que si son téléphone interroge ce nom-là (diagnostic de sa page BRIEFS AIR).
            if (!isset($known[$k]) && $r['crew'] === []) {
                ($this->log ?? static fn (string $m): bool => error_log($m))(sprintf('[air-brief] communauté %d, demande %d : destinataire « %s » sans appareil connu (aucun pilote relié sous cet indicatif)', $tenantId, $lineId, $r['callsign']));
            }
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
            'delivery' => self::delivery($r),
        ];
    }

    /**
     * État de livraison d'un destinataire (web : pastille, liste repliable) :
     * {status: sent|delivered|read|acknowledged|expired, answer: ""|wilco|unable|done, delivered_at, read_at,
     *  acknowledged_at, pilot_callsign, last_polled_at}, dates « Y-m-d H:i:s » (heure du serveur) ou "".
     * - sent : enregistré pour l'indicatif, pas encore relevé par le téléphone du pilote ;
     * - delivered : la boîte du pilote l'a renvoyé à son téléphone (ou le téléphone a accusé REÇU) ;
     * - read : ouvert dans l'espace BRIEFS AIR (accusé READ du téléphone) ;
     * - acknowledged : WILCO, UNABLE ou mission clôturée (answer) ;
     * - expired : jamais relevé dans la fenêtre de la boîte (3 h), le pilote ne le recevra plus.
     * pilot_callsign : indicatif de l'accusé, sinon nom sous lequel la boîte a été interrogée.
     *
     * @param array<string, mixed> $r ligne de atak_nine_line_recipients
     * @return array<string, string>
     */
    public static function delivery(array $r, ?int $now = null): array
    {
        $st = in_array((string) ($r['status'] ?? ''), self::STATUSES, true) ? (string) $r['status'] : 'sent';
        $status = match ($st) {
            'received' => 'delivered',
            'read' => 'read',
            'wilco', 'unable', 'done' => 'acknowledged',
            default => 'sent',
        };
        $created = strtotime((string) ($r['created_at'] ?? '')) ?: null;
        if ($status === 'sent' && $created !== null && (($now ?? time()) - $created) > self::INBOX_WINDOW_SECONDS) {
            $status = 'expired';
        }
        $by = (string) ($r['ack_by'] ?? '');

        return [
            'status' => $status,
            'answer' => in_array($st, ['wilco', 'unable', 'done'], true) ? $st : '',
            'delivered_at' => (string) ($r['received_at'] ?? ''),
            'read_at' => (string) ($r['read_at'] ?? ''),
            'acknowledged_at' => (string) ($r['ack_at'] ?? ''),
            'pilot_callsign' => $by !== '' ? $by : (string) ($r['polled_as'] ?? ''),
            'last_polled_at' => (string) ($r['last_polled_at'] ?? ''),
        ];
    }

    /**
     * État d'une demande entière (pastille) : l'état le plus avancé de ses destinataires et le décompte par état.
     *
     * @param list<array<string, mixed>> $recipients destinataires présentés (presentRecipient)
     * @return array{status: string, counts: array<string, int>, total: int, last_polled_at: string}
     */
    public static function deliverySummary(array $recipients): array
    {
        $order = array_flip(self::DELIVERY_STATUSES);
        $counts = array_fill_keys(self::DELIVERY_STATUSES, 0);
        $best = '';
        $polled = '';
        foreach ($recipients as $r) {
            $d = is_array($r['delivery'] ?? null) ? $r['delivery'] : self::delivery($r);
            $s = isset($order[$d['status'] ?? '']) ? (string) $d['status'] : 'sent';
            $counts[$s]++;
            if ($best === '' || $order[$s] > $order[$best]) {
                $best = $s;
            }
            if ((string) ($d['last_polled_at'] ?? '') > $polled) {
                $polled = (string) $d['last_polled_at'];
            }
        }

        return ['status' => $best !== '' ? $best : 'sent', 'counts' => $counts, 'total' => count($recipients), 'last_polled_at' => $polled];
    }

    /**
     * Livraison d'une demande (GET /api/cas/{id}/delivery) : null si elle n'existe pas dans cette communauté.
     *
     * @return array{id: int, ref: string, delivery: array<string, mixed>, recipients: list<array<string, mixed>>}|null
     */
    public function lineDelivery(int $tenantId, string $ref): ?array
    {
        $line = $this->resolveLine($tenantId, $ref);
        if ($line === null) {
            return null;
        }
        $id = (int) $line['id'];
        $rec = $this->forLines($tenantId, [$id])[$id] ?? [];
        $mission = trim((string) ($line['mission_id'] ?? ''));

        return ['id' => $id, 'ref' => $mission !== '' ? $mission : (string) $id, 'delivery' => self::deliverySummary($rec), 'recipients' => $rec];
    }

    /**
     * Identifiant reçu dans l'adresse (POST /api/cas/{ref}/status) : décodé (le routeur laisse « %2B »), limité à
     * [A-Za-z0-9._+-] et 128 caractères. Null : identifiant illisible, aucune demande ne peut le porter.
     * Les anciens mods fabriquaient « B<UID>6.35867e+06 » : « + » et « . » restent acceptés pour les retrouver.
     */
    public static function normalizeRef(string $raw): ?string
    {
        $ref = trim(rawurldecode($raw));

        return preg_match('/^[A-Za-z0-9._+\-]{1,128}$/', $ref) === 1 ? $ref : null;
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
        $keys = self::inboxKeys($names);
        if ($keys === []) {
            return [];
        }
        $now ??= time();
        $since = date('Y-m-d H:i:s', $now - self::INBOX_WINDOW_SECONDS);
        // Premier nom interrogé qui correspond (indicatif de l'appareil, du joueur, nom de jeu), pour la livraison.
        $match = static function (array $candidates) use ($keys): ?string {
            foreach ($candidates as $n) {
                $k = self::callsignKey($n);
                if ($k !== '' && isset($keys[$k])) {
                    return $keys[$k];
                }
            }

            return null;
        };
        $out = [];
        if ($this->hasRecipientsTable()) {
            $st = $this->db()->prepare(
                'SELECT r.id AS r_id, r.callsign AS r_callsign, r.label AS r_label, r.crew_names AS r_crew, r.status AS r_status, n.*
                 FROM atak_nine_line_recipients r JOIN atak_nine_line n ON n.id = r.nine_line_id
                 WHERE r.tenant_id = ? AND n.tenant_id = ? AND r.created_at >= ? ORDER BY n.id DESC, r.id LIMIT 200'
            );
            $st->execute([$tenantId, $tenantId, $since]);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
                $id = (int) $r['id'];
                if (isset($out[$id])) {
                    continue;
                }
                $hit = $match(array_merge([(string) $r['r_callsign'], (string) ($r['r_label'] ?? '')], explode('|', (string) ($r['r_crew'] ?? ''))));
                if ($hit === null) {
                    continue;
                }
                $out[$id] = self::inboxItem($r, $now, in_array((string) $r['r_status'], self::STATUSES, true) ? (string) $r['r_status'] : 'sent', (string) $r['r_callsign'])
                    + ['recipient_id' => (int) $r['r_id'], 'matched' => $hit];
            }
        }
        // Demande affectée à un appareil sans destinataire enregistré (assigned_aircraft : page CAS, PATCH /api/cas/{id}).
        try {
            $st = $this->db()->prepare(
                'SELECT * FROM atak_nine_line WHERE tenant_id = ? AND created_at >= ? AND assigned_aircraft IS NOT NULL AND assigned_aircraft <> ? ORDER BY id DESC LIMIT 60'
            );
            $st->execute([$tenantId, $since, '']);
            $assigned = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable) {
            $assigned = [];
        }
        foreach ($assigned as $r) {
            $id = (int) $r['id'];
            if (isset($out[$id])) {
                continue;
            }
            $tokens = preg_split('/\s*[,;|]\s*/u', (string) $r['assigned_aircraft']) ?: [];
            $hit = $match($tokens);
            if ($hit === null) {
                continue;
            }
            $cs = '';
            foreach ($tokens as $t) {
                if (self::callsignKey($t) === self::callsignKey($hit)) {
                    $cs = self::cleanCallsign($t);
                    break;
                }
            }
            $out[$id] = self::inboxItem($r, $now, 'sent', $cs) + ['recipient_id' => 0, 'matched' => $hit];
        }
        krsort($out);

        return array_values(array_slice($out, 0, self::INBOX_MAX, true));
    }

    /**
     * Réponse de GET /api/cas?assignedTo=~air|nom1|nom2 (8 noms au plus) : tableau SQF de la boîte, puis livraison notée
     * (la réponse garde l'état d'avant le relevé). Aucune erreur ne remonte : « [] » au pire.
     */
    public function inboxResponse(int $tenantId, string $assignedTo, ?int $now = null): string
    {
        $raw = str_starts_with($assignedTo, self::INBOX_PREFIX) ? substr($assignedTo, strlen(self::INBOX_PREFIX)) : $assignedTo;
        try {
            $items = $this->inbox($tenantId, array_slice(explode('|', $raw), 0, 8), $now);
            $body = self::inboxSqf($items);
        } catch (\Throwable) {
            return '[]';
        }
        try {
            $this->markDelivered($tenantId, $items, $now);
        } catch (\Throwable $e) {
            error_log('[air-brief] livraison : ' . $e->getMessage());
        }

        return $body;
    }

    /**
     * Brief de la boîte du pilote (forme lue par inboxSqf).
     *
     * @param array<string, mixed> $r ligne de atak_nine_line
     * @return array<string, mixed>
     */
    private static function inboxItem(array $r, int $now, string $status, string $recipient): array
    {
        $id = (int) $r['id'];
        $kind = CasNineLineRepository::normalizeKind($r['mission_kind'] ?? 'cas');
        $count = $kind === CasNineLineRepository::KIND_FIVE ? 5 : 9;
        $lines = [];
        for ($i = 1; $i <= $count; $i++) {
            $lines[] = (string) ($r['line' . $i] ?? '');
        }
        $mission = trim((string) ($r['mission_id'] ?? ''));

        return [
            'ref' => $mission !== '' ? $mission : (string) $id,
            'id' => $id,
            'format' => self::format($kind),
            'from' => (string) ($r['author'] ?? 'JTAC'),
            'time' => date('H:i', strtotime((string) ($r['created_at'] ?? '')) ?: $now),
            'lines' => $lines,
            'remarks' => self::remarks($r),
            'status' => $status,
            'recipient' => $recipient,
        ];
    }

    /**
     * La boîte vient d'être renvoyée au téléphone du pilote : chaque brief passe « livré » (reçu, jamais en arrière),
     * avec le nom interrogé et l'heure du relevé. Demande affectée sans destinataire : destinataire ajouté, déjà livré.
     *
     * @param list<array<string, mixed>> $items résultat de inbox()
     */
    public function markDelivered(int $tenantId, array $items, ?int $now = null): int
    {
        if ($items === [] || !$this->hasRecipientsTable()) {
            return 0;
        }
        $date = date('Y-m-d H:i:s', $now ?? time());
        $extra = $this->hasDeliveryColumns();
        $upd = $this->db()->prepare(
            'UPDATE atak_nine_line_recipients SET status = CASE WHEN status = ? THEN ? ELSE status END, received_at = COALESCE(received_at, ?)'
            . ($extra ? ', polled_as = ?, last_polled_at = ?' : '') . ' WHERE id = ? AND tenant_id = ?'
        );
        $n = 0;
        foreach ($items as $it) {
            $rid = (int) ($it['recipient_id'] ?? 0);
            $as = mb_substr((string) ($it['matched'] ?? ''), 0, 64);
            if ($rid < 1) {
                $cs = self::cleanCallsign($it['recipient'] ?? '');
                $line = (int) ($it['id'] ?? 0);
                if ($cs === '' || $line < 1) {
                    continue;
                }
                $exists = $this->db()->prepare('SELECT id FROM atak_nine_line_recipients WHERE nine_line_id = ? AND callsign = ?');
                $exists->execute([$line, $cs]);
                $rid = (int) $exists->fetchColumn();
                if ($rid < 1) {
                    $this->db()->prepare(
                        'INSERT INTO atak_nine_line_recipients (tenant_id, nine_line_id, callsign, label, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)'
                    )->execute([$tenantId, $line, $cs, $cs, 'sent', $date, $date]);
                    $exists->execute([$line, $cs]);
                    $rid = (int) $exists->fetchColumn();
                }
                if ($rid < 1) {
                    continue;
                }
            }
            $params = ['sent', 'received', $date];
            if ($extra) {
                array_push($params, $as !== '' ? $as : null, $date);
            }
            array_push($params, $rid, $tenantId);
            $upd->execute($params);
            $n++;
        }

        return $n;
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
        $wanted = array_flip(array_map(static fn (string $c): string => self::callsignKey($c), $callsigns));
        $out = [];
        foreach ($rows as $r) {
            $cs = self::callsignKey($r['callsign'] ?? '');
            if ($cs === '' || !isset($wanted[$cs]) || isset($out[$cs])) {
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
        return self::callsignKey($name);
    }

    private function hasRecipientsTable(): bool
    {
        return $this->hasRecipients ??= $this->probe('SELECT id FROM atak_nine_line_recipients LIMIT 1');
    }

    /** Colonnes de livraison (migration 4910) : nom interrogé et dernier relevé. */
    private function hasDeliveryColumns(): bool
    {
        return $this->hasDelivery ??= $this->probe('SELECT polled_as, last_polled_at FROM atak_nine_line_recipients LIMIT 1');
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
