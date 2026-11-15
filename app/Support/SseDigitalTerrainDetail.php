<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Détail d'un support numérique relevé en jeu (laboratoire numérique) : contacts, messages, appels, images, positions,
 * comptes, applications, réseaux et pièces (supprimés, chiffrés, navigation, courriels, fichiers).
 *
 * Le mod SSE envoie, après une extraction faite par le joueur, le contenu réel du support : « phone_data » (téléphone) et
 * « computer_data » (ordinateur), listes bornées dont les éléments sont des textes ou des cartes
 * (contacts : « NOM » ou {name, phone} ; sms : {from, to, when, text} ; appels : {with, number, dir, duration, missed,
 * when} ; images : {id, caption, when, grid} ; positions : {label, grid, when} ; comptes : {service, user, hint} ;
 * applications : « nom » ou {name, version} ; supprimés : {type, text, recoverable} ; navigation : {title, url} ;
 * courriels : {from, to, subject, snippet} ; fichiers : {name, path, relevant, content} ; clés USB : « nom » ;
 * fichiers chiffrés : « nom » ; utilisateurs : {name, role}).
 *
 * Cette classe ne fait que normaliser : bornes, longueurs, dates valides ou rien (jamais une date inventée), aucune
 * donnée fabriquée. L'écriture en base reste dans SseDigitalLabService.
 */
final class SseDigitalTerrainDetail
{
    public const MAX_PER_LIST = 120;
    public const MAX_TEXT = 500;

    /**
     * @param array<string, mixed> $phone données du téléphone (phone_data)
     * @param array<string, mixed> $computer données de l'ordinateur (computer_data)
     * @return array{
     *     contacts: list<array<string, mixed>>, messages: list<array<string, mixed>>, calls: list<array<string, mixed>>,
     *     media: list<array<string, mixed>>, locations: list<array<string, mixed>>, accounts: list<array<string, mixed>>,
     *     applications: list<array<string, mixed>>, networks: list<array<string, mixed>>, artifacts: list<array<string, mixed>>
     * }
     */
    public static function normalize(array $phone, array $computer = []): array
    {
        $out = [
            'contacts' => [], 'messages' => [], 'calls' => [], 'media' => [], 'locations' => [],
            'accounts' => [], 'applications' => [], 'networks' => [], 'artifacts' => [],
        ];
        foreach (self::list($phone['contacts'] ?? null) as $c) {
            $name = is_array($c) ? self::text($c['name'] ?? $c['display_name'] ?? '') : self::text($c);
            if ($name === '') {
                continue;
            }
            $out['contacts'][] = [
                'display_name' => $name,
                'phone_number' => is_array($c) ? self::nullable($c['phone'] ?? $c['number'] ?? null, 64) : null,
                'email' => is_array($c) ? self::nullable($c['email'] ?? null, 160) : null,
                'alias_label' => is_array($c) ? self::nullable($c['alias'] ?? null, 160) : null,
            ];
        }
        foreach (self::list($phone['sms'] ?? $phone['messages'] ?? null) as $m) {
            $body = is_array($m) ? self::text($m['text'] ?? $m['body'] ?? '') : self::text($m);
            if ($body === '') {
                continue;
            }
            $when = is_array($m) ? self::text($m['when'] ?? $m['at'] ?? '') : '';
            $date = self::datetime($when);
            $out['messages'][] = [
                'direction' => is_array($m) ? self::direction($m['dir'] ?? $m['direction'] ?? '') : 'inbound',
                'sender_label' => is_array($m) ? self::nullable($m['from'] ?? null, 160) : null,
                'recipient_label' => is_array($m) ? self::nullable($m['to'] ?? null, 160) : null,
                'body' => ($date === null && $when !== '' ? '[' . $when . '] ' : '') . $body,
                'sent_at' => $date,
                'app_label' => is_array($m) ? (self::nullable($m['app'] ?? null, 80) ?? 'SMS') : 'SMS',
                'is_deleted' => is_array($m) && self::flag($m['deleted'] ?? false),
                'thread_key' => is_array($m) ? self::nullable($m['thread'] ?? $m['from'] ?? $m['to'] ?? null, 80) : null,
            ];
        }
        foreach (self::list($phone['calls'] ?? null) as $c) {
            if (!is_array($c)) {
                $c = ['with' => self::text($c)];
            }
            $peer = self::text($c['with'] ?? $c['peer'] ?? $c['name'] ?? '');
            $number = self::nullable($c['number'] ?? null, 64);
            if ($peer === '' && $number === null) {
                continue;
            }
            $dir = self::flag($c['missed'] ?? false) ? 'missed' : self::direction($c['dir'] ?? $c['direction'] ?? '');
            $out['calls'][] = [
                'direction' => $dir,
                'peer_label' => $peer !== '' ? $peer : null,
                'peer_number' => $number,
                'started_at' => self::datetime(self::text($c['when'] ?? $c['at'] ?? '')),
                'duration_sec' => max(0, (int) ($c['duration'] ?? $c['duration_sec'] ?? 0)),
            ];
        }
        foreach (self::list($phone['photos'] ?? $phone['images'] ?? null) as $p) {
            $name = is_array($p) ? self::text($p['caption'] ?? $p['id'] ?? $p['name'] ?? '') : self::text($p);
            if ($name === '') {
                continue;
            }
            $out['media'][] = [
                'media_type' => 'image',
                'name' => $name,
                'captured_at' => is_array($p) ? self::datetime(self::text($p['when'] ?? $p['at'] ?? '')) : null,
            ];
        }
        foreach (self::list($phone['locations'] ?? null) as $l) {
            if (!is_array($l)) {
                $l = ['label' => self::text($l)];
            }
            $label = self::text($l['label'] ?? $l['name'] ?? '');
            $grid = self::text($l['grid'] ?? '');
            if ($label === '' && $grid === '') {
                continue;
            }
            $out['locations'][] = [
                'label' => $label !== '' ? $label : null,
                'lat' => null,
                'lng' => null,
                'observed_at' => self::datetime(self::text($l['when'] ?? $l['at'] ?? '')),
                'source_label' => $grid !== '' ? 'Carroyage ' . mb_substr($grid, 0, 32) : null,
            ];
        }
        foreach (self::list($phone['applications'] ?? $phone['apps'] ?? null) as $a) {
            $name = is_array($a) ? self::text($a['name'] ?? $a['app_name'] ?? '') : self::text($a);
            if ($name !== '') {
                $out['applications'][] = ['app_name' => $name, 'package_or_path' => null, 'version_label' => is_array($a) ? self::nullable($a['version'] ?? null, 40) : null];
            }
        }
        foreach (array_merge(self::list($phone['credentials'] ?? null), self::list($computer['credentials'] ?? null)) as $c) {
            if (!is_array($c)) {
                continue;
            }
            $service = self::text($c['service'] ?? '');
            if ($service === '') {
                continue;
            }
            $out['accounts'][] = [
                'service_label' => $service,
                'username' => self::nullable($c['user'] ?? $c['username'] ?? null, 160),
                'email' => null,
                'notes' => self::nullable($c['hint'] ?? null, self::MAX_TEXT),
            ];
        }
        foreach ([$phone['network'] ?? null, $computer['network'] ?? null] as $net) {
            if (is_array($net) && self::text($net['ssid'] ?? '') !== '') {
                $out['networks'][] = [
                    'network_type' => 'wifi',
                    'ssid_or_name' => self::text($net['ssid']),
                    'observed_at' => null,
                    'notes' => self::nullable($net['lastIP'] ?? $net['ip'] ?? null, 64) !== null ? 'Dernière adresse IP : ' . self::text($net['lastIP'] ?? $net['ip']) : null,
                ];
            }
        }

        // Pièces : un artefact par élément de ces listes, rangé dans la catégorie du laboratoire.
        foreach (self::list($phone['deleted'] ?? $phone['deletedData'] ?? null) as $d) {
            $text = is_array($d) ? self::text($d['text'] ?? $d['name'] ?? '') : self::text($d);
            if ($text !== '') {
                $out['artifacts'][] = ['name' => 'Supprimé : ' . mb_substr($text, 0, 80), 'category' => 'deleted', 'payload' => ['text' => $text, 'type' => is_array($d) ? self::text($d['type'] ?? '') : ''], 'is_deleted' => true, 'interest_level' => 'a_surveiller'];
            }
        }
        foreach (array_merge(self::list($phone['browser'] ?? null), self::list($computer['browser'] ?? null)) as $b) {
            $title = is_array($b) ? self::text($b['title'] ?? '') : self::text($b);
            $url = is_array($b) ? self::text($b['url'] ?? '') : '';
            if ($title !== '' || $url !== '') {
                $out['artifacts'][] = ['name' => $title !== '' ? $title : $url, 'category' => 'navigation', 'path' => $url !== '' ? $url : null, 'payload' => ['text' => trim($title . ' ' . $url)]];
            }
        }
        foreach (array_merge(self::list($phone['mail'] ?? null), self::list($computer['mail'] ?? null)) as $m) {
            if (!is_array($m)) {
                continue;
            }
            $subject = self::text($m['subject'] ?? '');
            $from = self::text($m['from'] ?? '');
            if ($subject === '' && $from === '') {
                continue;
            }
            $out['artifacts'][] = [
                'name' => $subject !== '' ? $subject : 'Courriel de ' . $from,
                'category' => 'email',
                'presumed_author' => $from !== '' ? mb_substr($from, 0, 160) : null,
                'payload' => ['from' => $from, 'to' => self::text($m['to'] ?? ''), 'text' => self::text($m['snippet'] ?? $m['body'] ?? '')],
            ];
        }
        foreach (self::list($computer['files'] ?? $phone['files'] ?? null) as $f) {
            $name = is_array($f) ? self::text($f['name'] ?? '') : self::text($f);
            if ($name === '') {
                continue;
            }
            $relevant = is_array($f) && self::flag($f['relevant'] ?? false);
            $out['artifacts'][] = [
                'name' => $name,
                'category' => 'document',
                'path' => is_array($f) ? self::nullable($f['path'] ?? null, 255) : null,
                'payload' => ['text' => is_array($f) ? self::text($f['content'] ?? '') : ''],
                'interest_level' => $relevant ? 'prioritaire' : 'courant',
            ];
        }
        foreach (self::list($computer['encryptedFiles'] ?? $phone['encryptedFiles'] ?? null) as $e) {
            $name = self::text(is_array($e) ? ($e['name'] ?? '') : $e);
            if ($name !== '') {
                $out['artifacts'][] = ['name' => $name, 'category' => 'encrypted', 'is_encrypted' => true, 'payload' => ['text' => 'Fichier chiffré : mot de passe requis'], 'interest_level' => 'a_surveiller'];
            }
        }
        foreach (self::list($computer['usbHistory'] ?? null) as $u) {
            $name = self::text(is_array($u) ? ($u['name'] ?? '') : $u);
            if ($name !== '') {
                $out['artifacts'][] = ['name' => 'Support USB : ' . $name, 'category' => 'system', 'payload' => ['text' => 'Support connecté à l’ordinateur']];
            }
        }
        foreach (self::list($computer['users'] ?? null) as $u) {
            $name = is_array($u) ? self::text($u['name'] ?? '') : self::text($u);
            if ($name !== '') {
                $out['artifacts'][] = ['name' => 'Compte : ' . $name, 'category' => 'system', 'payload' => ['text' => is_array($u) ? self::text($u['role'] ?? '') : '']];
            }
        }

        return $out;
    }

    /** Nombre d'éléments par rubrique (pour dire ce qui a été relevé). @param array<string, list<array<string, mixed>>> $detail @return array<string, int> */
    public static function counts(array $detail): array
    {
        $out = [];
        foreach ($detail as $k => $rows) {
            $out[$k] = count($rows);
        }

        return $out;
    }

    public static function isEmpty(array $detail): bool
    {
        return array_sum(self::counts($detail)) === 0;
    }

    /**
     * Lit un détail dans les lignes d'extraction « humaines » des anciens mods (qui n'envoient que des totaux et des lignes) :
     * « Contacts (extrait) : A, B, C » et « SMS : « texte » ». Rien n'est inventé : ce qui n'est pas dans les lignes manque.
     *
     * @param list<string> $lines
     * @return array<string, mixed> forme de phone_data
     */
    public static function fromLines(array $lines): array
    {
        $phone = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if (preg_match('/^Contacts \(extrait\)\s*:\s*(.+)$/u', $line, $m) === 1) {
                $phone['contacts'] = array_values(array_filter(array_map('trim', explode(',', $m[1])), static fn (string $s): bool => $s !== ''));
            } elseif (preg_match('/^SMS\s*:\s*[«"](.+?)[»"]\s*$/u', $line, $m) === 1) {
                $phone['sms'][] = ['text' => $m[1]];
            }
        }

        return $phone;
    }

    /** @return list<mixed> */
    private static function list(mixed $v): array
    {
        if (!is_array($v)) {
            return [];
        }

        return array_slice(array_values($v), 0, self::MAX_PER_LIST);
    }

    private static function text(mixed $v, int $max = self::MAX_TEXT): string
    {
        if (!is_scalar($v)) {
            return '';
        }
        $s = trim((string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', (string) $v));

        return mb_substr($s, 0, $max);
    }

    private static function nullable(mixed $v, int $max): ?string
    {
        $s = self::text($v, $max);

        return $s === '' ? null : $s;
    }

    private static function flag(mixed $v): bool
    {
        return $v === true || $v === 1 || $v === '1' || $v === 'true';
    }

    private static function direction(mixed $v): string
    {
        $s = mb_strtolower(self::text($v, 24));
        if ($s === '') {
            return 'inbound';
        }

        return preg_match('/^(in|incoming|entrant|recu|reçu|received|rx)/u', $s) === 1 ? 'inbound' : 'outbound';
    }

    /** Date et heure valides, ou null : une heure seule (« 08:40 ») n'est pas une date et n'est pas inventée. */
    private static function datetime(string $s): ?string
    {
        $s = str_replace('T', ' ', trim($s));
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})(?: (\d{2}):(\d{2})(?::(\d{2}))?)?/', $s, $m) !== 1 || !checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return null;
        }
        $h = (int) ($m[4] ?? 0);
        $i = (int) ($m[5] ?? 0);
        if ($h > 23 || $i > 59) {
            return null;
        }

        return sprintf('%s-%s-%s %02d:%02d:%02d', $m[1], $m[2], $m[3], $h, $i, (int) ($m[6] ?? 0));
    }
}
