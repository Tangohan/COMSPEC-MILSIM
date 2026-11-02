<?php

declare(strict_types=1);

namespace App\Services\Doctrine;

use App\Repositories\Doctrine\DoctrineLibraryRepository;
use InvalidArgumentException;

/**
 * Référentiel doctrinal d’une communauté (Doctrine › Référentiel) : catalogue du site (DoctrineLibraryCatalog) moins les
 * références masquées, plus les références ajoutées par la communauté, avec leurs rattachements aux documents de
 * doctrine. Les textes sont servis dans la langue du lecteur (titre d’origine + traduction).
 */
final class DoctrineLibraryService
{
    public const MAX_LINKS = 3;

    public function __construct(private ?DoctrineLibraryRepository $repository = null)
    {
        $this->repository ??= new DoctrineLibraryRepository();
    }

    public function repository(): DoctrineLibraryRepository
    {
        return $this->repository;
    }

    public static function customKey(int $id): string
    {
        return DoctrineLibraryCatalog::CUSTOM_PREFIX . $id;
    }

    public static function customId(string $key): int
    {
        return preg_match('/^' . preg_quote(DoctrineLibraryCatalog::CUSTOM_PREFIX, '/') . '(\d+)$/', $key, $m) ? (int) $m[1] : 0;
    }

    /**
     * Contrôle d’une référence saisie par la communauté.
     *
     * @param array<string, mixed> $in
     * @return array<string, mixed>
     */
    public static function normalizeEntry(array $in): array
    {
        $str = static fn (string $k, int $max): string => mb_substr(trim(preg_replace('/\s+/u', ' ', (string) ($in[$k] ?? '')) ?? ''), 0, $max);
        $organization = $str('organization', 120);
        $code = $str('code', 64);
        $title = $str('title', 255);
        if ($organization === '' || $code === '' || $title === '') {
            throw new InvalidArgumentException('Indiquez au moins l’organisation, le code et le titre de la référence.');
        }
        $lang = (string) ($in['lang'] ?? 'fr');
        $type = (string) ($in['type'] ?? 'doctrine');
        $level = (string) ($in['level'] ?? 'tactical');
        $domain = (string) ($in['domain'] ?? '');
        $categoryId = 0;
        if (str_starts_with($domain, 'cat:')) {
            $categoryId = (int) substr($domain, 4);
            $domain = '';
        }
        $links = [];
        $labels = is_array($in['link_label'] ?? null) ? $in['link_label'] : [];
        foreach (is_array($in['link_url'] ?? null) ? $in['link_url'] : [] as $i => $url) {
            $url = trim((string) $url);
            if ($url === '') {
                continue;
            }
            if (!DoctrineLibraryCatalog::isHttpsUrl($url) || mb_strlen($url) > 500) {
                throw new InvalidArgumentException('Chaque lien doit être une adresse complète en https://.');
            }
            $links[] = ['label' => mb_substr(trim((string) ($labels[$i] ?? '')), 0, 80), 'url' => $url];
            if (count($links) >= self::MAX_LINKS) {
                break;
            }
        }
        $summary = mb_substr(trim((string) ($in['summary'] ?? '')), 0, 2000);
        $translation = $str('title_translation', 255);

        return [
            'organization' => $organization,
            'code' => $code,
            'title' => $title,
            'title_translation' => $translation !== '' ? $translation : null,
            'lang' => in_array($lang, DoctrineLibraryCatalog::LANGS, true) ? $lang : 'fr',
            'domain' => isset(DoctrineLibraryCatalog::DOMAINS[$domain]) ? $domain : null,
            'category_id' => $categoryId > 0 ? $categoryId : null,
            'type' => isset(DoctrineLibraryCatalog::TYPES[$type]) ? $type : 'doctrine',
            'level' => isset(DoctrineLibraryCatalog::LEVELS[$level]) ? $level : 'tactical',
            'summary' => $summary !== '' ? $summary : null,
            'links_json' => $links !== [] ? json_encode($links, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
        ];
    }

    /** @return array{label: string, color: ?string} */
    public static function normalizeCategory(mixed $label, mixed $color): array
    {
        $label = mb_substr(trim(preg_replace('/\s+/u', ' ', (string) $label) ?? ''), 0, 80);
        if ($label === '') {
            throw new InvalidArgumentException('Donnez un nom à la catégorie.');
        }
        $color = strtolower(trim((string) $color));

        return ['label' => $label, 'color' => preg_match('/^#[0-9a-f]{6}$/', $color) ? $color : null];
    }

    /**
     * Vue d’ensemble pour la page : références (site et communauté), compteurs du rail, catégories, documents.
     *
     * @return array<string, mixed>
     */
    public function overview(int $tenantId, ?string $locale = null): array
    {
        $lang = DoctrineLibraryCatalog::lang($locale);
        $repo = $this->repository;
        $hidden = $repo->hiddenKeys($tenantId);
        $links = $repo->links($tenantId);
        $docs = [];
        foreach ($repo->doctrineDocuments($tenantId) as $d) {
            $docs[$d['document_id']] = $d;
        }
        $categories = [];
        foreach ($repo->categories($tenantId) as $c) {
            $categories[(int) $c['id']] = [
                'id' => (int) $c['id'],
                'label' => (string) $c['label'],
                'color' => (string) ($c['color'] ?? ''),
                'archived' => !empty($c['archived_at']),
                'count' => 0,
            ];
        }

        $entries = [];
        foreach (DoctrineLibraryCatalog::all() as $e) {
            $entries[] = $this->catalogView($e, $lang, isset($hidden[$e['key']]));
        }
        foreach ($repo->entries($tenantId) as $row) {
            $entries[] = $this->customView($row, $lang, $categories);
        }
        foreach ($entries as &$e) {
            $e['documents'] = [];
            foreach ($links[$e['key']] ?? [] as $docId) {
                if (isset($docs[$docId])) {
                    $e['documents'][] = $docs[$docId];
                }
            }
        }
        unset($e);

        $packs = [];
        foreach (DoctrineLibraryCatalog::packs() as $pack => $_) {
            $packs[$pack] = ['key' => $pack, 'label' => DoctrineLibraryCatalog::packLabel($pack, $lang),
                'country' => DoctrineLibraryCatalog::packCountry($pack), 'note' => DoctrineLibraryCatalog::sourcesNote($pack, $lang), 'count' => 0];
        }
        $packs['custom'] = ['key' => 'custom', 'label' => $lang === 'en' ? 'Added by the community' : 'Ajoutées par la communauté',
            'country' => '', 'note' => '', 'count' => 0];
        $domains = [];
        foreach (DoctrineLibraryCatalog::DOMAINS as $k => $pair) {
            $domains[$k] = ['key' => $k, 'label' => DoctrineLibraryCatalog::pick($pair, $lang), 'count' => 0];
        }
        $types = [];
        foreach (DoctrineLibraryCatalog::TYPES as $k => $pair) {
            $types[$k] = ['key' => $k, 'label' => DoctrineLibraryCatalog::pick($pair, $lang), 'count' => 0];
        }
        $stats = ['visible' => 0, 'hidden' => 0, 'custom' => 0, 'archived' => 0, 'linked' => 0];
        foreach ($entries as $e) {
            if ($e['hidden']) {
                ++$stats['hidden'];
                continue;
            }
            if ($e['archived']) {
                ++$stats['archived'];
                continue;
            }
            ++$stats['visible'];
            ++$packs[$e['pack']]['count'];
            if ($e['category_id'] > 0 && isset($categories[$e['category_id']])) {
                ++$categories[$e['category_id']]['count'];
            } elseif (isset($domains[$e['domain']])) {
                ++$domains[$e['domain']]['count'];
            }
            if (isset($types[$e['type']])) {
                ++$types[$e['type']]['count'];
            }
            if ($e['origin'] === 'custom') {
                ++$stats['custom'];
            }
            if ($e['documents'] !== []) {
                ++$stats['linked'];
            }
        }

        return [
            'lang' => $lang,
            'schema_ready' => $repo->schemaReady(),
            'entries' => $entries,
            'packs' => $packs,
            'domains' => $domains,
            'types' => $types,
            'levels' => array_map(static fn (array $p): string => DoctrineLibraryCatalog::pick($p, $lang), DoctrineLibraryCatalog::LEVELS),
            'categories' => array_values($categories),
            'documents' => array_values($docs),
            'stats' => $stats,
        ];
    }

    /**
     * Référence visible par la communauté (catalogue non masqué, ou ajout actif), pour pré-remplir un document.
     *
     * @return array<string, mixed>|null
     */
    public function resolve(int $tenantId, string $key, ?string $locale = null): ?array
    {
        $lang = DoctrineLibraryCatalog::lang($locale);
        $id = self::customId($key);
        if ($id > 0) {
            $row = $this->repository->findEntry($tenantId, $id);

            return $row !== null && empty($row['archived_at']) ? $this->customView($row, $lang, []) : null;
        }
        $entry = DoctrineLibraryCatalog::find($key);

        return $entry !== null ? $this->catalogView($entry, $lang, false) : null;
    }

    /** Une clé désigne-t-elle une référence de cette communauté ou du catalogue ? */
    public function knows(int $tenantId, string $key): bool
    {
        $id = self::customId($key);

        return $id > 0 ? $this->repository->findEntry($tenantId, $id) !== null : DoctrineLibraryCatalog::find($key) !== null;
    }

    /** @param array<string, mixed> $e @return array<string, mixed> */
    private function catalogView(array $e, string $lang, bool $hidden): array
    {
        $local = $lang === 'en' ? $e['title_en'] : $e['title_fr'];

        return [
            'key' => $e['key'],
            'origin' => 'catalog',
            'pack' => $e['pack'],
            'country' => DoctrineLibraryCatalog::packCountry($e['pack']),
            'pack_label' => DoctrineLibraryCatalog::packLabel($e['pack'], $lang),
            'code' => $e['code'],
            'series' => $e['series'],
            'issuer' => $e['issuer'],
            'issuer_short' => $e['issuer_short'] !== '' ? $e['issuer_short'] : $e['issuer'],
            'type' => $e['type'],
            'type_label' => DoctrineLibraryCatalog::typeLabel($e['type'], $lang),
            'domain' => $e['domain'],
            'category_id' => 0,
            'domain_label' => DoctrineLibraryCatalog::domainLabel($e['domain'], $lang),
            'level' => $e['level'],
            'level_label' => DoctrineLibraryCatalog::levelLabel($e['level'], $lang),
            'lang' => $e['lang'],
            'lang_label' => DoctrineLibraryCatalog::langLabel($e['lang'], $lang),
            'title' => $e['title'],
            'title_local' => $e['lang'] === $lang ? '' : $local,
            'summary' => $lang === 'en' ? $e['summary_en'] : $e['summary_fr'],
            'edition' => $e['edition'],
            'source_url' => $e['source_url'],
            'source_label' => $e['source_label'] !== '' ? $e['source_label'] : (string) parse_url($e['source_url'], PHP_URL_HOST),
            'links' => [],
            'file' => null,
            'hidden' => $hidden,
            'archived' => false,
            'custom_id' => 0,
            'raw' => [],
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @param array<int, array<string, mixed>> $categories
     * @return array<string, mixed>
     */
    private function customView(array $row, string $lang, array $categories): array
    {
        $links = json_decode((string) ($row['links_json'] ?? ''), true);
        $links = is_array($links) ? array_values(array_filter($links, static fn (mixed $l): bool => is_array($l) && DoctrineLibraryCatalog::isHttpsUrl((string) ($l['url'] ?? '')))) : [];
        $domain = (string) ($row['domain'] ?? '');
        $categoryId = (int) ($row['category_id'] ?? 0);
        $domainLabel = isset($categories[$categoryId]) ? $categories[$categoryId]['label'] : DoctrineLibraryCatalog::domainLabel($domain, $lang);
        $id = (int) $row['id'];
        $first = $links[0]['url'] ?? '';
        $rowLang = (string) ($row['lang'] ?? 'fr');

        return [
            'key' => self::customKey($id),
            'origin' => 'custom',
            'pack' => 'custom',
            'country' => '',
            'pack_label' => (string) $row['organization'],
            'code' => (string) $row['code'],
            'series' => '',
            'issuer' => (string) $row['organization'],
            'issuer_short' => (string) $row['organization'],
            'type' => (string) $row['type'],
            'type_label' => DoctrineLibraryCatalog::typeLabel((string) $row['type'], $lang),
            'domain' => $domain,
            'category_id' => isset($categories[$categoryId]) ? $categoryId : 0,
            'domain_label' => $domainLabel,
            'level' => (string) $row['level'],
            'level_label' => DoctrineLibraryCatalog::levelLabel((string) $row['level'], $lang),
            'lang' => $rowLang,
            'lang_label' => DoctrineLibraryCatalog::langLabel($rowLang, $lang),
            'title' => (string) $row['title'],
            'title_local' => (string) ($row['title_translation'] ?? ''),
            'summary' => (string) ($row['summary'] ?? ''),
            'edition' => '',
            'source_url' => $first,
            'source_label' => $first !== '' ? (string) parse_url($first, PHP_URL_HOST) : '',
            'links' => $links,
            'file' => !empty($row['file_path']) ? ['name' => (string) ($row['file_name'] ?? 'document.pdf')] : null,
            'hidden' => false,
            'archived' => !empty($row['archived_at']),
            'custom_id' => $id,
            'raw' => [
                'organization' => (string) $row['organization'], 'code' => (string) $row['code'], 'title' => (string) $row['title'],
                'title_translation' => (string) ($row['title_translation'] ?? ''), 'lang' => $rowLang,
                'domain' => $categoryId > 0 ? 'cat:' . $categoryId : $domain, 'type' => (string) $row['type'], 'level' => (string) $row['level'],
                'summary' => (string) ($row['summary'] ?? ''), 'links' => $links,
            ],
        ];
    }
}
