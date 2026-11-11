<?php

declare(strict_types=1);

namespace App\Support;

use PDO;

/**
 * Rapprochement d'une couverture (fichier « comspec-cover ») avec une identité du membre et ses papiers, pour poser le
 * lien de sse_cover_export_links sur une couverture qui n'en a pas (couvertures d'avant le lien, bouton « Rattacher »).
 *
 * Règle, volontairement stricte : une seule identité du membre porte le même prénom, le même nom et la même date de
 * naissance que la légende. Zéro ou plusieurs : pas de rapprochement, la couverture reste « non rattachée ». Les papiers
 * sont retrouvés par type + pays + numéro parmi ceux de cette identité.
 */
final class SseCoverLinkMatcher
{
    /**
     * @param array<string, mixed> $cover « cover » du fichier (first_name, last_name, name, dob, docs)
     * @param list<array<string, mixed>> $identities identités du membre (id, first_name, last_name, birth_date)
     * @param list<array<string, mixed>> $documents papiers du membre (id, identity_id, doc_type, country_code, doc_number)
     * @param array<string, list<string>> $countryNames code du pays → noms sous lesquels il s'écrit dans un fichier
     * @return array{identity_id: int, document_ids: list<int>}|null
     */
    public static function match(array $cover, array $identities, array $documents, array $countryNames = []): ?array
    {
        $dob = substr((string) ($cover['dob'] ?? ''), 0, 10);
        $first = self::norm((string) ($cover['first_name'] ?? ''));
        $last = self::norm((string) ($cover['last_name'] ?? ''));
        $full = $first !== '' && $last !== '' ? $first . ' ' . $last : self::norm((string) ($cover['name'] ?? ''));
        if ($full === '' || preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob) !== 1) {
            return null;
        }
        $found = [];
        foreach ($identities as $i) {
            $name = self::norm((string) ($i['first_name'] ?? '') . ' ' . (string) ($i['last_name'] ?? ''));
            if ($name === $full && substr((string) ($i['birth_date'] ?? ''), 0, 10) === $dob) {
                $found[] = (int) ($i['id'] ?? 0);
            }
        }
        if (count($found) !== 1 || $found[0] < 1) {
            return null;
        }
        $ids = [];
        foreach (is_array($cover['docs'] ?? null) ? $cover['docs'] : [] as $d) {
            if (!is_array($d)) {
                continue;
            }
            $number = strtoupper(trim((string) ($d['number'] ?? '')));
            $country = self::norm((string) ($d['country'] ?? ''));
            foreach ($documents as $doc) {
                $code = (string) ($doc['country_code'] ?? '');
                $names = array_map([self::class, 'norm'], array_merge([$code], $countryNames[$code] ?? []));
                if ((int) ($doc['identity_id'] ?? 0) === $found[0] && $number !== ''
                    && (string) ($doc['doc_type'] ?? '') === (string) ($d['type'] ?? '')
                    && strtoupper(trim((string) ($doc['doc_number'] ?? ''))) === $number
                    && in_array($country, $names, true)) {
                    $ids[(int) $doc['id']] = true;
                }
            }
        }

        return ['identity_id' => $found[0], 'document_ids' => array_keys($ids)];
    }

    /**
     * Rétro-remplissage : pose le lien des couvertures qui n'en ont pas quand le rapprochement est sans ambiguïté.
     * Rejouable sans effet (seules les couvertures sans lien sont lues). Le fichier de la couverture n'est pas touché.
     *
     * @return array{linked: int, left: int}
     */
    public static function backfill(PDO $pdo, ?callable $log = null): array
    {
        $log ??= static function (string $msg): void {
        };
        $todo = $pdo->query(
            'SELECT e.id, e.tenant_id, e.user_id FROM sse_cover_exports e
              LEFT JOIN sse_cover_export_links l ON l.export_id = e.id AND l.tenant_id = e.tenant_id
              WHERE l.id IS NULL ORDER BY e.id ASC'
        )->fetchAll(PDO::FETCH_ASSOC);
        $payload = $pdo->prepare('SELECT payload_json FROM sse_cover_exports WHERE id = ?');
        $identities = $pdo->prepare('SELECT id, first_name, last_name, birth_date FROM sse_identities WHERE tenant_id = ? AND user_id = ?');
        $documents = $pdo->prepare(
            'SELECT d.id, d.identity_id, d.doc_type, d.country_code, d.doc_number FROM sse_id_documents d
               JOIN sse_identities i ON i.id = d.identity_id AND i.tenant_id = d.tenant_id
              WHERE d.tenant_id = ? AND i.user_id = ?'
        );
        $countries = $pdo->prepare('SELECT code, name, name_en FROM sse_id_countries WHERE tenant_id = ?');
        $insert = $pdo->prepare(
            'INSERT INTO sse_cover_export_links (tenant_id, export_id, user_id, identity_id, document_ids, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $cache = [];
        $names = [];
        $now = gmdate('Y-m-d H:i:s');
        $linked = 0;
        foreach ($todo as $row) {
            [$id, $tenant, $user] = [(int) $row['id'], (int) $row['tenant_id'], (int) $row['user_id']];
            $payload->execute([$id]);
            $file = json_decode((string) $payload->fetchColumn(), true);
            $cover = is_array($file) && is_array($file['cover'] ?? null) ? $file['cover'] : null;
            if ($cover === null) {
                continue;
            }
            if (!isset($names[$tenant])) {
                $names[$tenant] = [];
                $countries->execute([$tenant]);
                foreach ($countries->fetchAll(PDO::FETCH_ASSOC) as $c) {
                    $names[$tenant][(string) $c['code']] = array_values(array_filter([(string) ($c['name'] ?? ''), (string) ($c['name_en'] ?? '')], static fn (string $v): bool => $v !== ''));
                }
            }
            $key = $tenant . ':' . $user;
            if (!isset($cache[$key])) {
                $identities->execute([$tenant, $user]);
                $ids = $identities->fetchAll(PDO::FETCH_ASSOC);
                $documents->execute([$tenant, $user]);
                $cache[$key] = [$ids, $documents->fetchAll(PDO::FETCH_ASSOC)];
            }
            $match = self::match($cover, $cache[$key][0], $cache[$key][1], $names[$tenant]);
            if ($match === null) {
                continue;
            }
            $insert->execute([$tenant, $id, $user, $match['identity_id'], (string) json_encode($match['document_ids']), $now, $now]);
            $linked++;
        }
        $left = count($todo) - $linked;
        $log("  [OK] couvertures rattachées à une identité : {$linked} ; laissées non rattachées : {$left}\n");

        return ['linked' => $linked, 'left' => $left];
    }

    private static function norm(string $v): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $v)));
    }
}
