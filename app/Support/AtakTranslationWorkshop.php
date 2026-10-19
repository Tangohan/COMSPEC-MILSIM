<?php

declare(strict_types=1);

namespace App\Support;

use App\Repositories\AtakTranslationPackRepository;

/**
 * Workshop des langues de l'ATAK natif (/traduire-atak/workshop) : contrôle des paquets publiés et état de chacun
 * face à la version actuelle du mod.
 *
 * Chaque version publiée est revérifiée contre le modèle de chaînes du mod (xx.txt) dès que son empreinte change,
 * c'est-à-dire à la première visite après une mise à jour de l'ATAK : le résultat est gardé en base jusqu'à la
 * mise à jour suivante. États :
 *  - official : le mod livre maintenant cette langue lui-même (CfgComspecLanguages du main.pbo) ;
 *  - recheck  : des phrases ne passent plus le contrôle (marqueurs %N changés…) : retirées du zip, à revoir ;
 *  - outdated : la mise à jour a ajouté des phrases que cette version ne traduit pas ;
 *  - ok       : à jour.
 */
final class AtakTranslationWorkshop
{
    /** Phrases traduites minimum pour publier (un pack vide ne sert à personne dans le catalogue). */
    public const MIN_TRANSLATED = 50;
    private const AUDIT_VERSION = 'a2';

    public function __construct(private ?AtakTranslationPackRepository $packs = null, private ?string $root = null)
    {
        $this->packs ??= new AtakTranslationPackRepository();
    }

    /** Empreinte du contrôle : modèle de chaînes + règles de contrôle. */
    public function auditFp(): string
    {
        return self::AUDIT_VERSION . ':' . AtakTranslationTemplate::fingerprint($this->root);
    }

    /**
     * @return list<array{0: string, 1: string, 2: string}>
     */
    public function entries(): array
    {
        static $cache = [];
        $fp = $this->auditFp() . '|' . ($this->root ?? '');
        if (!isset($cache[$fp])) {
            $out = [];
            foreach (AtakTranslationTemplate::entries($this->root) as [$fr, $en]) {
                $out[] = [AtakTranslationTemplate::keyHash($fr), $fr, $en];
            }
            $cache = [$fp => $out];
        }

        return $cache[$fp];
    }

    /**
     * Contrôle d'une version publiée (ligne de catalog() / listedPack() / latestRelease()), recalculé et gardé si
     * le mod a changé depuis le dernier contrôle.
     *
     * @param array<string, mixed> $release
     * @return array<string, mixed>
     */
    public function audit(array $release): array
    {
        $fp = $this->auditFp();
        if ((string) ($release['audit_fp'] ?? '') === $fp) {
            $cached = json_decode((string) ($release['audit_json'] ?? ''), true);
            if (is_array($cached) && isset($cached['total'])) {
                return $cached;
            }
        }
        $map = $this->packs()->releasePayload((int) $release['release_id']);
        $audit = AtakTranslationCheck::audit($this->entries(), $map);
        // Phrases ajoutées par le mod depuis cette version et qu'elle ne traduit pas (null : modèle de l'époque inconnu).
        $added = $this->newKeys($release);
        $audit['new'] = null;
        if ($added !== null) {
            $byKey = [];
            foreach ($this->entries() as $e) {
                $byKey[$e[0]] = $e;
            }
            $audit['new'] = count(array_filter($added, static function (string $h) use ($map, $byKey): bool {
                $t = $map[$h] ?? '';

                return $t === '' || AtakTranslationCheck::check($byKey[$h][1], $t)['errors'] !== [];
            }));
        }
        $this->packs()->saveAudit((int) $release['release_id'], $fp, (string) json_encode($audit, JSON_UNESCAPED_UNICODE));

        return $audit;
    }

    /**
     * Clés des phrases que le mod a ajoutées depuis la version publiée (modèle actuel moins modèle de l'époque) ;
     * null si le modèle de l'époque n'a pas été gardé.
     *
     * @param array<string, mixed> $release
     * @return list<string>|null
     */
    public function newKeys(array $release): ?array
    {
        $fp = (string) ($release['template_fp'] ?? '');
        if ($fp === AtakTranslationTemplate::fingerprint($this->root)) {
            return [];
        }
        $then = $fp === '' ? null : $this->packs()->templateKeys($fp);
        if ($then === null) {
            return null;
        }
        $then = array_flip($then);
        $out = [];
        foreach ($this->entries() as $e) {
            if (!isset($then[$e[0]])) {
                $out[] = $e[0];
            }
        }

        return $out;
    }

    /** Garde les clés du modèle actuel du mod (à chaque publication). */
    public function snapshotTemplate(string $atakVersion): void
    {
        $this->packs()->saveTemplateKeys(
            AtakTranslationTemplate::fingerprint($this->root),
            $atakVersion,
            array_map(static fn (array $e): string => $e[0], $this->entries())
        );
    }

    /**
     * État d'une version publiée face au mod actuel.
     *
     * @param array<string, mixed> $release
     * @param array<string, mixed> $audit
     * @param array<string, string> $official
     * @return array{key: string, new: int, errors: int}
     */
    public static function status(array $release, array $audit, array $official, string $templateFp): array
    {
        $errors = (int) ($audit['errors'] ?? 0);
        // Modèle de l'époque inconnu : estimation (manquantes aujourd'hui moins manquantes à la publication).
        $missingThen = max(0, (int) ($release['template_total'] ?? 0) - (int) ($release['translated'] ?? 0));
        $new = match (true) {
            (string) ($release['template_fp'] ?? '') === $templateFp => 0,
            is_int($audit['new'] ?? null) => $audit['new'],
            default => max(0, (int) ($audit['missing'] ?? 0) - $missingThen),
        };
        $key = match (true) {
            isset($official[(string) ($release['lang_code'] ?? '')]) => 'official',
            $errors > 0 => 'recheck',
            $new > 0 => 'outdated',
            default => 'ok',
        };

        return ['key' => $key, 'new' => $new, 'errors' => $errors];
    }

    /**
     * Carte du catalogue / de la fiche : pack, version, contrôle, état.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public function card(array $row): array
    {
        $audit = $this->audit($row);
        $status = self::status($row, $audit, AtakTranslationTemplate::officialLanguages($this->root), AtakTranslationTemplate::fingerprint($this->root));

        return [
            'id' => (string) $row['public_id'],
            'pack_id' => (int) $row['id'],
            'code' => (string) $row['lang_code'],
            'name' => (string) $row['lang_name'],
            'game' => (string) $row['game_language'],
            'author' => (string) $row['author_label'],
            'description' => (string) $row['description'],
            'workshop_id' => (string) $row['workshop_id'],
            'downloads' => (int) $row['downloads'],
            'published_at' => (string) ($row['published_at'] ?? ''),
            'release_id' => (int) $row['release_id'],
            'version' => (int) $row['version'],
            'atak_version' => (string) $row['atak_version'],
            'released_at' => (string) $row['released_at'],
            'note' => (string) $row['note'],
            'audit' => $audit,
            'status' => $status,
        ];
    }

    /**
     * Cartes du catalogue, filtrées et triées. $sort : popular | recent | name | progress.
     *
     * @return list<array<string, mixed>>
     */
    public function catalog(string $query = '', string $sort = 'popular'): array
    {
        $cards = array_map(fn (array $r): array => $this->card($r), $this->packs()->catalog());
        $q = self::fold(trim($query));
        if ($q !== '') {
            $cards = array_values(array_filter($cards, static fn (array $c): bool => str_contains(self::fold($c['name'] . ' ' . $c['code'] . ' ' . $c['author'] . ' ' . $c['game']), $q)));
        }
        usort($cards, static fn (array $a, array $b): int => match ($sort) {
            'recent' => strcmp((string) $b['released_at'], (string) $a['released_at']),
            'name' => strcmp(self::fold((string) $a['name']), self::fold((string) $b['name'])),
            'progress' => ($b['audit']['pct'] <=> $a['audit']['pct']) ?: ($b['downloads'] <=> $a['downloads']),
            default => ($b['downloads'] <=> $a['downloads']) ?: strcmp((string) $b['released_at'], (string) $a['released_at']),
        });

        return $cards;
    }

    /** Lien Steam de l'objet Workshop (page web) ; '' sans identifiant. */
    public static function steamUrl(string $id): string
    {
        return $id === '' ? '' : 'https://steamcommunity.com/sharedfiles/filedetails/?id=' . rawurlencode($id);
    }

    /** Même page ouverte directement dans le client Steam (bouton « S'abonner »). */
    public static function steamClientUrl(string $id): string
    {
        return $id === '' ? '' : 'steam://url/CommunityFilePage/' . rawurlencode($id);
    }

    private static function fold(string $s): string
    {
        $t = class_exists(\Normalizer::class) ? \Normalizer::normalize($s, \Normalizer::FORM_D) : $s;

        return mb_strtolower((string) preg_replace('/\p{Mn}+/u', '', is_string($t) ? $t : $s));
    }

    private function packs(): AtakTranslationPackRepository
    {
        return $this->packs ?? new AtakTranslationPackRepository();
    }
}
