<?php

declare(strict_types=1);

namespace App\Services\Sse;

/**
 * Onglet « Photos » de la fiche d'une identité du registre : réunit en une seule liste ordonnée les images qui s'y
 * trouvaient en trois blocs (photos du dossier, photo du titulaire, clichés SEEK II), sans doublon, rangées par source
 * avec leur compteur. Logique pure (aucune lecture de base, aucun HTML) : la vue et les tests la lisent telle quelle.
 *
 * Sources (clé -> contenu) :
 *  - fiche   : portrait du titulaire (identité) et photos « face » ou « profil » versées à la fiche ;
 *  - seek    : clichés pris au terminal SEEK II (source « seek » de la photothèque) ;
 *  - terrain : photos de terrain (type « terrain » ou envoyées depuis ATAK) ;
 *  - biblio  : le reste de la photothèque (papiers photographiés, types inconnus).
 * Une même photo (même identifiant) n'apparaît qu'une fois : les clichés SEEK II lus à part complètent la ligne déjà
 * présente dans les photos du dossier (fiche SEEK II, lien protégé) au lieu de la doubler.
 *
 * Ordre dans une source : face avant le profil (source « fiche »), puis du plus récent au plus ancien.
 */
final class SseIdentityPhotoGallery
{
    public const GROUPS = ['fiche', 'seek', 'terrain', 'biblio'];

    /** Vignettes montrées d'emblée par source ; les suivantes sont dans un « Afficher les N autres » replié. */
    public const VISIBLE = 12;

    /** Rang de rangement d'un type de photo de la photothèque. */
    private const KIND_RANK = ['front' => 0, 'profile' => 1, 'field' => 2, 'document' => 3];

    /**
     * @param array<string, mixed> $identity ligne d'identité (photo_path, signature_path)
     * @param list<array<string, mixed>> $filePhotos photos de la photothèque rattachées (SseFileRecordRepository::photosFor)
     * @param list<array<string, mixed>> $seekImages clichés SEEK II (SseSeekPhotoService::forIdentity : photo + seek + url)
     * @param callable(array<string, mixed>): ?string|null $urlOf adresse d'une photo (défaut : SseSeekPhotoService::photoUrl)
     * @return array{
     *     holder: ?array<string, mixed>,
     *     signature: ?string,
     *     groups: array<string, array{key: string, count: int, items: list<array<string, mixed>>}>,
     *     total: int,
     *     masked: int,
     *     isEmpty: bool
     * }
     */
    public static function build(array $identity, array $filePhotos, array $seekImages, ?callable $urlOf = null, ?string $holderUrl = null, ?string $signatureUrl = null): array
    {
        $urlOf ??= static fn (array $p): ?string => SseSeekPhotoService::photoUrl($p);
        $groups = [];
        foreach (self::GROUPS as $key) {
            $groups[$key] = ['key' => $key, 'count' => 0, 'items' => []];
        }

        $holder = null;
        if (trim((string) ($identity['photo_path'] ?? '')) !== '' && $holderUrl !== null && $holderUrl !== '') {
            $holder = [
                'uid' => 'holder', 'id' => 0, 'group' => 'fiche', 'src' => $holderUrl, 'kind' => 'front', 'caption' => '', 'date' => '',
                'place' => '', 'source' => '', 'ref' => '', 'rank' => -1, 'width' => null, 'height' => null, 'masked' => false, 'holder' => true, 'seek' => null,
                'sortKey' => '9999-99-99',
            ];
        }

        $byId = [];
        foreach ($filePhotos as $p) {
            $id = (int) ($p['id'] ?? 0);
            if ($id > 0) {
                $byId[$id] = $p;
            }
        }
        foreach ($seekImages as $s) {
            $id = (int) ($s['id'] ?? 0);
            if ($id > 0) {
                $byId[$id] = ($byId[$id] ?? []) + $s;
                // Les champs propres au cliché SEEK II (fiche, adresse) priment sur la ligne de photothèque.
                foreach (['seek', 'url'] as $k) {
                    if (isset($s[$k])) {
                        $byId[$id][$k] = $s[$k];
                    }
                }
            }
        }

        $items = [];
        foreach ($byId as $id => $p) {
            $item = self::item($id, $p, $urlOf);
            if ($item !== null) {
                $items[] = $item;
            }
        }
        $order = array_flip(self::GROUPS);
        usort($items, static function (array $a, array $b) use ($order): int {
            // Source, puis (fiche seulement) face avant profil, puis du plus récent au plus ancien.
            $ra = $a['group'] === 'fiche' ? $a['rank'] : 0;
            $rb = $b['group'] === 'fiche' ? $b['rank'] : 0;

            return [$order[$a['group']], $ra, $b['sortKey'], $b['id']] <=> [$order[$b['group']], $rb, $a['sortKey'], $a['id']];
        });
        $masked = 0;
        foreach ($items as $item) {
            $groups[$item['group']]['items'][] = $item;
            ++$groups[$item['group']]['count'];
            $masked += $item['masked'] ? 1 : 0;
        }
        if ($holder !== null) {
            ++$groups['fiche']['count'];
        }
        $total = 0;
        foreach ($groups as $g) {
            $total += $g['count'];
        }

        return [
            'holder' => $holder,
            'signature' => $signatureUrl !== null && $signatureUrl !== '' && trim((string) ($identity['signature_path'] ?? '')) !== '' ? $signatureUrl : null,
            'groups' => $groups,
            'total' => $total,
            'masked' => $masked,
            'isEmpty' => $total === 0,
        ];
    }

    /** Source d'une photo de la photothèque : clé de groupe. */
    public static function groupOf(string $source, string $kind): string
    {
        if ($source === 'seek') {
            return 'seek';
        }
        if ($kind === 'field' || $source === 'atak') {
            return 'terrain';
        }
        if ($kind === 'front' || $kind === 'profile') {
            return 'fiche';
        }

        return 'biblio';
    }

    /**
     * @param array<string, mixed> $p
     * @param callable(array<string, mixed>): ?string $urlOf
     * @return array<string, mixed>|null
     */
    private static function item(int $id, array $p, callable $urlOf): ?array
    {
        $src = trim((string) ($p['url'] ?? ''));
        if ($src === '') {
            $src = (string) ($urlOf($p) ?? '');
        }
        $kind = (string) ($p['kind'] ?? 'field');
        $source = (string) ($p['source'] ?? 'upload');
        $seek = is_array($p['seek'] ?? null) ? $p['seek'] : null;
        $created = (string) ($p['created_at'] ?? '');
        $w = (int) ($p['width'] ?? 0);
        $h = (int) ($p['height'] ?? 0);

        return [
            'uid' => 'p' . $id,
            'id' => $id,
            'group' => self::groupOf($source, $kind),
            'src' => $src,
            'kind' => $kind,
            'rank' => self::KIND_RANK[$kind] ?? 4,
            'caption' => trim((string) ($p['caption'] ?? '')),
            'date' => $created !== '' ? substr($created, 0, 10) : '',
            'sortKey' => $created,
            'place' => $seek !== null ? trim((string) ($seek['grid'] ?? '')) : '',
            'source' => $source,
            'ref' => trim((string) ($p['source_ref'] ?? '')),
            'width' => $w > 0 ? $w : null,
            'height' => $h > 0 ? $h : null,
            'masked' => (string) ($p['visibility'] ?? 'known') === 'hidden',
            'holder' => false,
            'seek' => $seek,
        ];
    }
}
