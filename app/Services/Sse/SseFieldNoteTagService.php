<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Repositories\SseAuditLogRepository;
use App\Repositories\SseTagRepository;
use App\Support\SseFieldNoteCatalog;
use App\Support\SseFieldNoteTagCatalog as Cat;
use App\Support\SseUsClassification;
use App\Support\SseWantedRealNames;

/**
 * Référentiel des fiches de renseignement d'une communauté : étiquettes de style américain (catalogue livré + réglages +
 * étiquettes propres), catégories, types de fiche, trames, règles, et étiquetage des fiches.
 *
 * Le catalogue livré (App\Support\SseFieldNoteTagCatalog) vaut pour toute communauté sans réglage. Les réglages et les
 * étiquettes propres vivent dans le catalogue UNIQUE du bureau (sse_attach_tags, partagé avec la fenêtre « Rattacher ») :
 * une étiquette propre créée ici est aussi proposée aux rattachements, une étiquette de rattachement se voit ici et
 * s'active pour les fiches. Une étiquette est désignée
 * par sa clé (slug) : la liaison fiche ↔ étiquette (sse_tag_links) ne dépend donc pas d'une ligne en base. Les catégories
 * existantes restent rangées sur la fiche (colonne themes, clés inchangées) ; une catégorie propre à la communauté est une
 * étiquette de la famille « category ». Une étiquette de diffusion est une MENTION écrite sur la fiche : elle ne change aucun
 * droit d'accès, qui reste décidé par la classification de la fiche et les droits de la communauté.
 * Sans les tables (étape en attente) : catalogue livré en lecture seule, étiquetage désactivé.
 */
final class SseFieldNoteTagService
{
    public const FORMAT = 'comspec.sse.fiches.referentiel';

    public const FORMAT_VERSION = 1;

    private SseTagRepository $repo;

    /** @var array<int, array<string, array<string, mixed>>> */
    private array $memo = [];

    /** @var array<int, array<string, array<string, mixed>>> */
    private array $settingsMemo = [];

    /** @var \Closure(array<string, mixed>): void|null */
    private ?\Closure $auditWriter;

    /** @param \Closure(array<string, mixed>): void|null $auditWriter remplace l'écriture dans sse_audit_log (tests) */
    public function __construct(?SseTagRepository $repo = null, ?\Closure $auditWriter = null)
    {
        $this->repo = $repo ?? new SseTagRepository();
        $this->auditWriter = $auditWriter;
    }

    public function ready(): bool
    {
        return SseTagRepository::ready();
    }

    public function forget(): void
    {
        $this->memo = [];
        $this->settingsMemo = [];
    }

    // ——————————————————————————— Référentiel ———————————————————————————

    /**
     * Toutes les étiquettes de la communauté (livrées réglées ou non, propres), tous états, par clé.
     *
     * @return array<string, array<string, mixed>>
     */
    public function referential(int $tenantId): array
    {
        if (isset($this->memo[$tenantId])) {
            return $this->memo[$tenantId];
        }
        $rows = [];
        foreach ($this->repo->rows($tenantId) as $row) {
            $rows[(string) $row['slug']] = $row;
        }
        $out = [];
        foreach (Cat::delivered() as $key => $d) {
            $row = $rows[$key] ?? null;
            unset($rows[$key]);
            $status = $d['default_on'] ? Cat::STATUS_ACTIVE : Cat::STATUS_DISABLED;
            $tag = [
                'key' => $key, 'code' => $d['code'], 'family' => $d['family'], 'label_fr' => $d['label_fr'], 'label_en' => $d['label_en'],
                'desc_fr' => $d['desc_fr'], 'desc_en' => $d['desc_en'], 'color' => $d['color'], 'qualifier' => $d['qualifier'], 'sort' => $d['sort'],
                'status' => $status, 'origin' => 'catalog', 'customized' => false, 'merged_into' => '',
                'stored' => $d['family'] === 'category' ? 'themes' : 'links', 'from_attach' => false, 'row_id' => 0,
            ];
            // Une étiquette de rattachement de même clé n'est pas un réglage du catalogue livré : elle le devient si on la règle ici.
            if ($row !== null && (string) ($row['origin'] ?? '') === 'catalog') {
                $tag['customized'] = true;
                foreach (['label_fr' => 'name_fr', 'label_en' => 'name_en', 'desc_fr' => 'description', 'desc_en' => 'description_en'] as $field => $col) {
                    if (trim((string) ($row[$col] ?? '')) !== '') {
                        $tag[$field] = (string) $row[$col];
                    }
                }
                $tag['color'] = Cat::normalizeColor($row['color'] ?? $tag['color']);
                $tag['sort'] = (int) ($row['sort_order'] ?? 0) > 0 ? (int) $row['sort_order'] : $tag['sort'];
                $tag['status'] = (string) ($row['status'] ?? '') === Cat::STATUS_ACTIVE ? Cat::STATUS_ACTIVE : Cat::STATUS_DISABLED;
                $tag['merged_into'] = (string) ($row['merged_into'] ?? '');
            }
            $out[$key] = $tag;
        }
        foreach ($rows as $key => $row) {
            if ((string) ($row['origin'] ?? '') !== 'custom') {
                continue;
            }
            // Étiquette née dans la fenêtre « Rattacher » : visible ici, proposée sur les fiches une fois activée (portée « all »).
            $fromAttach = (string) ($row['scope'] ?? '') === SseTagRepository::SCOPE_ATTACH;
            $family = (string) ($row['family'] ?? '');
            if (!Cat::hasFamily($family) || !Cat::FAMILIES[$family]['pick']) {
                if (!$fromAttach) {
                    continue;
                }
                $family = 'theme';
            }
            $name = trim((string) ($row['name_fr'] ?? '')) !== '' ? (string) $row['name_fr'] : (string) ($row['name'] ?? '');
            $status = $fromAttach ? Cat::STATUS_DISABLED : (string) ($row['status'] ?? Cat::STATUS_ACTIVE);
            $out[$key] = [
                'key' => $key, 'code' => trim((string) ($row['code'] ?? '')) !== '' ? (string) $row['code'] : Cat::normalizeCode($name), 'family' => $family, 'label_fr' => $name,
                'label_en' => trim((string) ($row['name_en'] ?? '')) !== '' ? (string) $row['name_en'] : $name,
                'desc_fr' => (string) ($row['description'] ?? ''),
                'desc_en' => trim((string) ($row['description_en'] ?? '')) !== '' ? (string) $row['description_en'] : (string) ($row['description'] ?? ''),
                'color' => Cat::normalizeColor($row['color'] ?? 'slate'), 'qualifier' => (string) ($row['qualifier'] ?? ''),
                'sort' => (int) ($row['sort_order'] ?? 0), 'status' => isset(Cat::STATUSES[$status]) ? $status : Cat::STATUS_ACTIVE,
                'origin' => 'custom', 'customized' => true, 'merged_into' => (string) ($row['merged_into'] ?? ''), 'stored' => 'links',
                'from_attach' => $fromAttach, 'row_id' => (int) ($row['id'] ?? 0),
            ];
        }
        $order = array_flip(Cat::familyKeys());
        uasort($out, static fn (array $a, array $b): int => [$order[$a['family']] ?? 99, $a['sort'], $a['code']] <=> [$order[$b['family']] ?? 99, $b['sort'], $b['code']]);
        foreach ($out as $key => $tag) {
            $out[$key]['hex'] = Cat::colorHex($tag['color']);
        }

        return $this->memo[$tenantId] = $out;
    }

    /** @return array<string, mixed>|null */
    public function find(int $tenantId, string $key): ?array
    {
        return $this->referential($tenantId)[$key] ?? null;
    }

    /**
     * Étiquettes actives, par famille (familles sans étiquette active omises).
     *
     * @return array<string, list<array<string, mixed>>>
     */
    public function activeByFamily(int $tenantId): array
    {
        $out = [];
        foreach ($this->referential($tenantId) as $tag) {
            if ($tag['status'] === Cat::STATUS_ACTIVE) {
                $out[$tag['family']][] = $tag;
            }
        }

        return $out;
    }

    /** Catégories actives à proposer à la rédaction : codes existants (colonne themes) dans l'ordre de la communauté. @return array<string, array{label: string, hint: string, tone: string}> */
    public function activeThemes(int $tenantId, string $lang = 'fr'): array
    {
        $out = [];
        foreach ($this->referential($tenantId) as $tag) {
            if ($tag['family'] !== 'category' || $tag['stored'] !== 'themes' || $tag['status'] !== Cat::STATUS_ACTIVE) {
                continue;
            }
            $def = SseFieldNoteCatalog::THEMES[$tag['code']] ?? null;
            if ($def === null) {
                continue;
            }
            $out[$tag['code']] = ['label' => self::label($tag, $lang), 'hint' => self::desc($tag, $lang), 'tone' => $def['tone']];
        }

        return $out !== [] ? $out : SseFieldNoteCatalog::THEMES;
    }

    /** Libellé et couleur d'une catégorie existante telle que la communauté l'a réglée. @return array{label: string, hex: string} */
    public function themeDisplay(int $tenantId, string $themeCode, string $lang = 'fr'): array
    {
        $tag = $this->referential($tenantId)[Cat::categoryKey($themeCode)] ?? null;
        if ($tag === null) {
            return ['label' => SseFieldNoteCatalog::themeLabel($themeCode), 'hex' => SseFieldNoteCatalog::themeColor($themeCode)];
        }

        return ['label' => self::label($tag, $lang), 'hex' => $tag['customized'] ? $tag['hex'] : SseFieldNoteCatalog::themeColor($themeCode)];
    }

    /** @return array<string, int> nombre de fiches par clé d'étiquette */
    public function usage(int $tenantId): array
    {
        $out = $this->repo->usage($tenantId, Cat::SCOPE);
        foreach ($this->repo->noteThemeUsage($tenantId)['themes'] as $code => $n) {
            $out[Cat::categoryKey($code)] = $n;
        }

        return $out;
    }

    /** Chiffres du résumé du référentiel. @return array{active: int, custom: int, archived: int, disabled: int, notes: int, kinds: int, families: int} */
    public function stats(int $tenantId): array
    {
        $s = ['active' => 0, 'custom' => 0, 'archived' => 0, 'disabled' => 0, 'notes' => 0, 'kinds' => 0, 'families' => 0];
        $families = [];
        foreach ($this->referential($tenantId) as $tag) {
            if ($tag['status'] === Cat::STATUS_ACTIVE) {
                $s['active']++;
                $families[$tag['family']] = true;
                if ($tag['origin'] === 'custom') {
                    $s['custom']++;
                }
            } elseif ($tag['status'] === Cat::STATUS_ARCHIVED) {
                $s['archived']++;
            } else {
                $s['disabled']++;
            }
        }
        $s['families'] = count($families);
        $s['notes'] = $this->repo->noteThemeUsage($tenantId)['total'];
        $s['kinds'] = count(array_filter($this->kinds($tenantId), static fn (array $k): bool => $k['enabled']));

        return $s;
    }

    /** @param array<string, mixed> $tag */
    public static function label(array $tag, string $lang = 'fr'): string
    {
        return (string) ($lang === 'en' ? $tag['label_en'] : $tag['label_fr']);
    }

    /** @param array<string, mixed> $tag */
    public static function desc(array $tag, string $lang = 'fr'): string
    {
        return (string) ($lang === 'en' ? $tag['desc_en'] : $tag['desc_fr']);
    }

    // ——————————————————————————— Écritures du référentiel ———————————————————————————

    /**
     * Crée une étiquette propre ou règle une étiquette existante (livrée : libellés, couleur, ordre ; propre : tout sauf le code).
     *
     * @param array<string, mixed> $input key (vide : création), code, family, label_fr, label_en, desc_fr, desc_en, color, sort, qualifier
     * @param array{user_id: ?int, label: string} $actor
     * @return array{ok: bool, errors: list<string>, key: string}
     */
    public function saveTag(int $tenantId, array $input, array $actor): array
    {
        if (!$this->ready()) {
            return ['ok' => false, 'errors' => [self::notReadyMessage()], 'key' => ''];
        }
        $ref = $this->referential($tenantId);
        $key = trim((string) ($input['key'] ?? ''));
        $existing = $key !== '' ? ($ref[$key] ?? null) : null;
        $errors = [];
        if ($key !== '' && $existing === null) {
            return ['ok' => false, 'errors' => ['Étiquette introuvable dans votre communauté.'], 'key' => ''];
        }

        $labelFr = self::clean((string) ($input['label_fr'] ?? ''), Cat::LABEL_MAX);
        $labelEn = self::clean((string) ($input['label_en'] ?? ''), Cat::LABEL_MAX);
        $descFr = self::clean((string) ($input['desc_fr'] ?? ''), Cat::DESCRIPTION_MAX);
        $descEn = self::clean((string) ($input['desc_en'] ?? ''), Cat::DESCRIPTION_MAX);
        $color = strtolower(trim((string) ($input['color'] ?? '')));
        $sort = (int) ($input['sort'] ?? 0);
        if ($labelFr === '') {
            $errors[] = 'Donnez un libellé français à l’étiquette.';
        }
        if (!isset(Cat::COLORS[$color])) {
            $errors[] = 'Choisissez une couleur dans la liste.';
        }
        if ($sort < 0 || $sort > 9999) {
            $errors[] = 'L’ordre est un nombre entre 0 et 9999.';
        }

        if ($existing === null) {
            $code = Cat::normalizeCode($input['code'] ?? '');
            $family = (string) ($input['family'] ?? '');
            $qualifier = (string) ($input['qualifier'] ?? '');
            $slug = Cat::slug($code);
            if (!Cat::isValidCode($code) || $slug === '') {
                $errors[] = 'Le code s’écrit en majuscules, chiffres, espaces ou tirets, de 2 à ' . Cat::CODE_MAX . ' caractères.';
            } elseif (isset($ref[$slug]) || isset($ref[Cat::categoryKey($code)]) || isset(Cat::PRECEDENCES[$code])) {
                $errors[] = 'Le code ' . $code . ' existe déjà dans le référentiel : réglez l’étiquette existante.';
            }
            if (!Cat::hasFamily($family) || !Cat::FAMILIES[$family]['pick']) {
                $errors[] = 'Choisissez une famille dans la liste.';
            }
            if (!in_array($qualifier, ['', 'number', 'text', 'date'], true)) {
                $errors[] = 'Le complément attendu n’est pas reconnu.';
            }
            if (count(array_filter($ref, static fn (array $t): bool => $t['origin'] === 'custom')) >= Cat::CUSTOM_MAX) {
                $errors[] = 'La communauté a atteint le nombre maximal d’étiquettes propres (' . Cat::CUSTOM_MAX . ').';
            }
        } else {
            $code = (string) $existing['code'];
            $family = (string) $existing['family'];
            $qualifier = (string) $existing['qualifier'];
            $slug = (string) $existing['key'];
        }
        $real = SseWantedRealNames::find(implode(' | ', [$existing === null ? $code : '', $labelFr, $labelEn, $descFr, $descEn]));
        if ($real !== null) {
            $errors[] = 'Le nom « ' . $real . ' » désigne une organisation réelle : choisissez un nom imaginaire.';
        }
        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors, 'key' => $slug];
        }

        $data = [
            'family' => $family, 'code' => $code, 'name' => $labelFr, 'name_en' => $labelEn !== '' ? $labelEn : $labelFr,
            'description' => $descFr, 'description_en' => $descEn !== '' ? $descEn : $descFr, 'color' => $color, 'qualifier' => $qualifier,
            'sort_order' => $sort > 0 ? $sort : ($existing['sort'] ?? $this->nextSort($ref, $family)),
            'origin' => $existing['origin'] ?? 'custom',
        ];
        if ($existing === null || !$existing['customized']) {
            $data['status'] = $existing['status'] ?? Cat::STATUS_ACTIVE;
        }
        $this->repo->save($tenantId, $slug, $data, $actor['user_id'], self::scopeFor((string) $data['origin']));
        $this->forget();
        $this->audit($tenantId, $actor, $existing === null ? 'field_note.tag.create' : 'field_note.tag.update', $slug, $existing, $this->find($tenantId, $slug));

        return ['ok' => true, 'errors' => [], 'key' => $slug];
    }

    /**
     * Active, désactive ou archive une étiquette. Une étiquette livrée ne s'archive pas : elle se désactive.
     *
     * @param array{user_id: ?int, label: string} $actor
     * @return array{ok: bool, errors: list<string>}
     */
    public function setStatus(int $tenantId, string $key, string $status, array $actor): array
    {
        if (!$this->ready()) {
            return ['ok' => false, 'errors' => [self::notReadyMessage()]];
        }
        $tag = $this->find($tenantId, $key);
        if ($tag === null) {
            return ['ok' => false, 'errors' => ['Étiquette introuvable dans votre communauté.']];
        }
        if (!isset(Cat::STATUSES[$status])) {
            return ['ok' => false, 'errors' => ['État inconnu.']];
        }
        if ($tag['origin'] === 'catalog' && $status === Cat::STATUS_ARCHIVED) {
            $status = Cat::STATUS_DISABLED;
        }
        if ($status !== Cat::STATUS_ACTIVE && $tag['family'] === 'category' && $tag['status'] === Cat::STATUS_ACTIVE) {
            $left = count(array_filter($this->referential($tenantId), static fn (array $t): bool => $t['family'] === 'category' && $t['status'] === Cat::STATUS_ACTIVE));
            if ($left <= 1) {
                return ['ok' => false, 'errors' => ['Gardez au moins une catégorie active : une fiche en porte toujours une.']];
            }
        }
        $this->repo->save($tenantId, $key, $this->rowOf($tag, ['status' => $status]), $actor['user_id'], self::scopeFor((string) $tag['origin']));
        $this->forget();
        $this->audit($tenantId, $actor, 'field_note.tag.status', $key, ['status' => $tag['status']], ['status' => $status]);

        return ['ok' => true, 'errors' => []];
    }

    /**
     * Supprime une étiquette propre que plus aucune fiche ne porte. Une étiquette utilisée s'archive.
     *
     * @param array{user_id: ?int, label: string} $actor
     * @return array{ok: bool, errors: list<string>}
     */
    public function deleteTag(int $tenantId, string $key, array $actor): array
    {
        if (!$this->ready()) {
            return ['ok' => false, 'errors' => [self::notReadyMessage()]];
        }
        $tag = $this->find($tenantId, $key);
        if ($tag === null) {
            return ['ok' => false, 'errors' => ['Étiquette introuvable dans votre communauté.']];
        }
        if ($tag['origin'] !== 'custom') {
            return ['ok' => false, 'errors' => ['Une étiquette du catalogue livré ne se supprime pas : désactivez-la.']];
        }
        $used = (int) ($this->usage($tenantId)[$key] ?? 0);
        if ($used > 0) {
            return ['ok' => false, 'errors' => [$used . ' fiche(s) portent encore cette étiquette : archivez-la ou fusionnez-la.']];
        }
        if ($tag['from_attach'] || $this->repo->attachUsage($tenantId, (int) $tag['row_id']) > 0) {
            return ['ok' => false, 'errors' => ['Des rattachements du bureau se servent de cette étiquette : archivez-la, elle ne sera plus proposée sur les fiches.']];
        }
        $this->repo->delete($tenantId, $key);
        $this->forget();
        $this->audit($tenantId, $actor, 'field_note.tag.delete', $key, $tag, null);

        return ['ok' => true, 'errors' => []];
    }

    /**
     * Fusionne $from dans $into (même famille) : les fiches qui portaient la première portent la seconde, puis la première
     * est archivée (propre) ou désactivée (livrée). Les catégories livrées, rangées sur la fiche elle-même, ne fusionnent pas.
     *
     * @param array{user_id: ?int, label: string} $actor
     * @return array{ok: bool, errors: list<string>, moved: int}
     */
    public function merge(int $tenantId, string $from, string $into, array $actor): array
    {
        if (!$this->ready()) {
            return ['ok' => false, 'errors' => [self::notReadyMessage()], 'moved' => 0];
        }
        $a = $this->find($tenantId, $from);
        $b = $this->find($tenantId, $into);
        if ($a === null || $b === null) {
            return ['ok' => false, 'errors' => ['Étiquette introuvable dans votre communauté.'], 'moved' => 0];
        }
        if ($from === $into) {
            return ['ok' => false, 'errors' => ['Choisissez deux étiquettes différentes.'], 'moved' => 0];
        }
        if ($a['family'] !== $b['family']) {
            return ['ok' => false, 'errors' => ['Deux étiquettes ne fusionnent que dans la même famille.'], 'moved' => 0];
        }
        if ($a['stored'] !== 'links' || $b['stored'] !== 'links') {
            return ['ok' => false, 'errors' => ['Les catégories livrées sont inscrites sur les fiches : elles se désactivent, elles ne fusionnent pas.'], 'moved' => 0];
        }
        $moved = $this->repo->moveLinks($tenantId, Cat::SCOPE, $from, $into);
        $this->repo->save($tenantId, $from, $this->rowOf($a, [
            'status' => $a['origin'] === 'custom' ? Cat::STATUS_ARCHIVED : Cat::STATUS_DISABLED, 'merged_into' => $into,
        ]), $actor['user_id'], self::scopeFor((string) $a['origin']));
        $this->forget();
        $this->audit($tenantId, $actor, 'field_note.tag.merge', $from, ['key' => $from, 'status' => $a['status']], ['merged_into' => $into, 'moved' => $moved]);

        return ['ok' => true, 'errors' => [], 'moved' => $moved];
    }

    /**
     * Rétablit le catalogue livré d'une famille : libellés, couleurs, ordre et états d'origine. Les étiquettes propres et
     * l'étiquetage des fiches ne changent pas.
     *
     * @param array{user_id: ?int, label: string} $actor
     * @return array{ok: bool, errors: list<string>, reset: int}
     */
    public function resetFamily(int $tenantId, string $family, array $actor): array
    {
        if (!$this->ready()) {
            return ['ok' => false, 'errors' => [self::notReadyMessage()], 'reset' => 0];
        }
        if (!Cat::hasFamily($family) || !Cat::FAMILIES[$family]['pick']) {
            return ['ok' => false, 'errors' => ['Choisissez une famille dans la liste.'], 'reset' => 0];
        }
        $n = $this->repo->deleteCatalogOverrides($tenantId, $family);
        $this->forget();
        $this->audit($tenantId, $actor, 'field_note.tag.reset', $family, ['family' => $family, 'overrides' => $n], null);

        return ['ok' => true, 'errors' => [], 'reset' => $n];
    }

    // ——————————————————————————— Types de fiche, trames, règles ———————————————————————————

    /**
     * Types de fiche tels que la communauté les a réglés (clés inchangées).
     *
     * @return array<string, array{code: string, label: string, default_label: string, hint: string, enabled: bool, sort: int, suggested: list<string>, required_fields: list<string>, required_tags: list<string>, template: string}>
     */
    public function kinds(int $tenantId): array
    {
        $settings = $this->settings($tenantId);
        $ref = $this->referential($tenantId);
        $out = [];
        $n = 0;
        foreach (SseFieldNoteCatalog::KINDS as $code => $def) {
            $s = $settings['kind.' . $code] ?? [];
            $suggested = array_key_exists('suggested', $s) && is_array($s['suggested'])
                ? $s['suggested']
                : array_map(static fn (string $c): string => Cat::slug($c), Cat::KIND_SUGGESTIONS[$code] ?? []);
            $out[$code] = [
                'code' => $code,
                'label' => trim((string) ($s['label'] ?? '')) !== '' ? (string) $s['label'] : $def['label'],
                'default_label' => $def['label'],
                'hint' => $def['hint'],
                'enabled' => !array_key_exists('enabled', $s) || (bool) $s['enabled'],
                'sort' => (int) ($s['sort'] ?? 0) > 0 ? (int) $s['sort'] : (++$n) * 10,
                'suggested' => array_values(array_filter(array_map('strval', $suggested), static fn (string $k): bool => isset($ref[$k]) && $ref[$k]['stored'] === 'links')),
                'required_fields' => array_values(array_intersect(array_keys(Cat::REQUIRABLE_FIELDS), array_map('strval', (array) ($s['required_fields'] ?? [])))),
                'required_tags' => array_values(array_filter(array_map('strval', (array) ($s['required_tags'] ?? [])), fn (string $k): bool => $this->isRequirement($ref, $k))),
                'template' => (string) ($s['template'] ?? ''),
            ];
        }
        if (!isset($out[SseFieldNoteCatalog::DEFAULT_KIND]) || array_filter($out, static fn (array $k): bool => $k['enabled']) === []) {
            $out[SseFieldNoteCatalog::DEFAULT_KIND]['enabled'] = true;
        }
        uasort($out, static fn (array $a, array $b): int => [$a['sort'], $a['code']] <=> [$b['sort'], $b['code']]);

        return $out;
    }

    /** Types proposés à la rédaction : forme de SseFieldNoteCatalog::KINDS. @return array<string, array{label: string, hint: string}> */
    public function activeKinds(int $tenantId): array
    {
        $out = [];
        foreach ($this->kinds($tenantId) as $code => $kind) {
            if ($kind['enabled']) {
                $out[$code] = ['label' => $kind['label'], 'hint' => $kind['hint']];
            }
        }

        return $out;
    }

    public function kindLabel(int $tenantId, string $code): string
    {
        return $this->kinds($tenantId)[$code]['label'] ?? SseFieldNoteCatalog::kindLabel($code);
    }

    /**
     * @param array<string, mixed> $input enabled, label, sort, suggested[], required_fields[] (onglet Types) ; template (onglet Modèles) ; required_tags[] (onglet Règles)
     * @param array{user_id: ?int, label: string} $actor
     * @return array{ok: bool, errors: list<string>}
     */
    public function saveKind(int $tenantId, string $code, array $input, array $actor): array
    {
        if (!$this->ready()) {
            return ['ok' => false, 'errors' => [self::notReadyMessage()]];
        }
        $kinds = $this->kinds($tenantId);
        if (!isset($kinds[$code])) {
            return ['ok' => false, 'errors' => ['Type de fiche inconnu.']];
        }
        $before = $kinds[$code];
        $ref = $this->referential($tenantId);
        $value = $this->settings($tenantId)['kind.' . $code] ?? [];
        $errors = [];
        if (array_key_exists('label', $input)) {
            $label = self::clean((string) $input['label'], Cat::LABEL_MAX);
            $real = SseWantedRealNames::find($label);
            if ($real !== null) {
                $errors[] = 'Le nom « ' . $real . ' » désigne une organisation réelle : choisissez un nom imaginaire.';
            }
            $value['label'] = $label === $before['default_label'] ? '' : $label;
        }
        if (array_key_exists('enabled', $input)) {
            $value['enabled'] = (bool) $input['enabled'];
            $others = array_filter($kinds, static fn (array $k): bool => $k['enabled'] && $k['code'] !== $code);
            if (!$value['enabled'] && $others === []) {
                $errors[] = 'Gardez au moins un type de fiche actif.';
            }
        }
        if (array_key_exists('sort', $input)) {
            $sort = (int) $input['sort'];
            if ($sort < 0 || $sort > 9999) {
                $errors[] = 'L’ordre est un nombre entre 0 et 9999.';
            }
            $value['sort'] = $sort;
        }
        if (array_key_exists('suggested', $input)) {
            $value['suggested'] = array_slice(array_values(array_unique(array_filter(
                array_map('strval', (array) $input['suggested']),
                static fn (string $k): bool => isset($ref[$k]) && $ref[$k]['stored'] === 'links'
            ))), 0, 12);
        }
        if (array_key_exists('required_fields', $input)) {
            $value['required_fields'] = array_values(array_intersect(array_keys(Cat::REQUIRABLE_FIELDS), array_map('strval', (array) $input['required_fields'])));
        }
        if (array_key_exists('required_tags', $input)) {
            $value['required_tags'] = array_slice(array_values(array_unique(array_filter(
                array_map('strval', (array) $input['required_tags']),
                fn (string $k): bool => $this->isRequirement($ref, $k)
            ))), 0, 8);
        }
        if (array_key_exists('template', $input)) {
            $template = SseFieldNoteCatalog::normalizeBody((string) $input['template']);
            $real = SseWantedRealNames::find($template);
            if ($real !== null) {
                $errors[] = 'Le nom « ' . $real . ' » désigne une organisation réelle : retirez-le de la trame.';
            }
            $value['template'] = $template;
        }
        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }
        $this->repo->saveSetting($tenantId, 'kind.' . $code, $value, $actor['user_id']);
        $this->forget();
        $this->audit($tenantId, $actor, 'field_note.kind.update', 'kind.' . $code, $before, $this->kinds($tenantId)[$code]);

        return ['ok' => true, 'errors' => []];
    }

    /**
     * Règles de la communauté : étiquette de validité posée d'office, correspondance urgence → priorité de traitement,
     * liste « REL TO » proposée par défaut.
     *
     * @return array{default_validity: string, precedence: array<string, string>, rel_to: string}
     */
    public function rules(int $tenantId): array
    {
        $s = $this->settings($tenantId)['rules'] ?? [];
        $ref = $this->referential($tenantId);
        $validity = (string) ($s['default_validity'] ?? '');
        $precedence = [];
        foreach (array_keys(SseFieldNoteCatalog::URGENCIES) as $urgency) {
            $code = strtoupper((string) (($s['precedence'] ?? [])[$urgency] ?? ''));
            $precedence[$urgency] = isset(Cat::PRECEDENCES[$code]) ? $code : Cat::defaultPrecedence($urgency);
        }

        return [
            'default_validity' => isset($ref[$validity]) && $ref[$validity]['family'] === 'validity' && $ref[$validity]['status'] === Cat::STATUS_ACTIVE ? $validity : '',
            'precedence' => $precedence,
            'rel_to' => (string) ($s['rel_to'] ?? ''),
        ];
    }

    /**
     * @param array<string, mixed> $input default_validity, precedence[urgence], rel_to
     * @param array{user_id: ?int, label: string} $actor
     * @return array{ok: bool, errors: list<string>}
     */
    public function saveRules(int $tenantId, array $input, array $actor): array
    {
        if (!$this->ready()) {
            return ['ok' => false, 'errors' => [self::notReadyMessage()]];
        }
        $before = $this->rules($tenantId);
        $ref = $this->referential($tenantId);
        $errors = [];
        $validity = (string) ($input['default_validity'] ?? '');
        if ($validity !== '' && (!isset($ref[$validity]) || $ref[$validity]['family'] !== 'validity' || $ref[$validity]['status'] !== Cat::STATUS_ACTIVE)) {
            $errors[] = 'L’étiquette de validité par défaut doit être une étiquette active de la famille « Temporalité et validité ».';
        }
        $precedence = [];
        foreach (array_keys(SseFieldNoteCatalog::URGENCIES) as $urgency) {
            $code = strtoupper(trim((string) (((array) ($input['precedence'] ?? []))[$urgency] ?? '')));
            if (!isset(Cat::PRECEDENCES[$code])) {
                $errors[] = 'Choisissez une priorité de traitement pour l’urgence « ' . SseFieldNoteCatalog::urgencyLabel($urgency) . ' ».';
                continue;
            }
            $precedence[$urgency] = $code;
        }
        $relTo = mb_strtoupper(self::clean((string) ($input['rel_to'] ?? ''), Cat::QUALIFIER_MAX), 'UTF-8');
        $real = SseWantedRealNames::find($relTo);
        if ($real !== null) {
            $errors[] = 'Le nom « ' . $real . ' » désigne une organisation réelle : retirez-le de la liste de diffusion.';
        }
        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }
        $this->repo->saveSetting($tenantId, 'rules', ['default_validity' => $validity, 'precedence' => $precedence, 'rel_to' => $relTo], $actor['user_id']);
        $this->forget();
        $this->audit($tenantId, $actor, 'field_note.rules.update', 'rules', $before, $this->rules($tenantId));

        return ['ok' => true, 'errors' => []];
    }

    /** Priorité de traitement (libellé américain) d'une urgence, selon les règles de la communauté. @return array{code: string, label: string, desc: string} */
    public function precedenceFor(int $tenantId, string $urgency, string $lang = 'fr'): array
    {
        $code = $this->rules($tenantId)['precedence'][SseFieldNoteCatalog::normalizeUrgency($urgency)] ?? 'ROUTINE';
        $def = Cat::PRECEDENCES[$code];

        return ['code' => $code, 'label' => $lang === 'en' ? $def['en'] : $def['fr'], 'desc' => $lang === 'en' ? $def['desc_en'] : $def['desc_fr']];
    }

    // ——————————————————————————— Étiquetage des fiches ———————————————————————————

    /**
     * Nettoie une sélection venue d'un formulaire ou du jeu : clés ou codes connus et actifs, compléments bornés.
     *
     * @param mixed $rawTags liste de clés/codes, ou liste de {code|key, qualifier}
     * @param mixed $rawQualifiers tableau clé => complément (formulaire web)
     * @param array<string, string> $keep étiquettes déjà portées (clé => complément) : restent permises même désactivées
     * @return array{selection: list<array{slug: string, qualifier: string}>, errors: list<string>}
     */
    public function normalizeSelection(int $tenantId, mixed $rawTags, mixed $rawQualifiers = [], array $keep = []): array
    {
        $ref = $this->referential($tenantId);
        $byCode = [];
        foreach ($ref as $key => $tag) {
            if ($tag['stored'] === 'links') {
                $byCode[$tag['code']] = $key;
            }
        }
        $qualifiers = is_array($rawQualifiers) ? $rawQualifiers : [];
        $selection = [];
        $errors = [];
        foreach (is_array($rawTags) ? $rawTags : [] as $raw) {
            $q = '';
            if (is_array($raw)) {
                $q = (string) ($raw['qualifier'] ?? '');
                $raw = $raw['key'] ?? $raw['code'] ?? '';
            }
            $raw = trim((string) $raw);
            $key = isset($ref[$raw]) ? $raw : ($byCode[Cat::normalizeCode($raw)] ?? '');
            if ($key === '' || isset($selection[$key])) {
                continue;
            }
            $tag = $ref[$key];
            if ($tag['stored'] !== 'links' || ($tag['status'] !== Cat::STATUS_ACTIVE && !array_key_exists($key, $keep))) {
                continue;
            }
            if ($q === '' && isset($qualifiers[$key])) {
                $q = (string) $qualifiers[$key];
            }
            $q = $this->normalizeQualifier($tag, $q, $errors);
            $selection[$key] = ['slug' => $key, 'qualifier' => $q];
            if (count($selection) >= Cat::TAGS_PER_NOTE) {
                break;
            }
        }

        return ['selection' => array_values($selection), 'errors' => $errors];
    }

    /**
     * @param array<string, mixed> $tag
     * @param list<string> $errors
     */
    private function normalizeQualifier(array $tag, string $raw, array &$errors): string
    {
        $q = self::clean($raw, Cat::QUALIFIER_MAX);
        if ($q === '' || $tag['qualifier'] === '') {
            return '';
        }
        if ($tag['qualifier'] === 'number') {
            return preg_match('/^[A-Za-z0-9][A-Za-z0-9.\-]{0,11}$/', $q) === 1 ? strtoupper($q) : '';
        }
        if ($tag['qualifier'] === 'date') {
            $ts = strtotime(str_replace('T', ' ', $q));

            return $ts === false ? '' : date('Y-m-d H:i', $ts);
        }
        $real = SseWantedRealNames::find($q);
        if ($real !== null) {
            $errors[] = 'Le complément de ' . $tag['code'] . ' cite « ' . $real . ' », une organisation réelle : il a été retiré.';

            return '';
        }

        return mb_strtoupper($q, 'UTF-8');
    }

    /**
     * Enregistre les étiquettes d'une fiche (remplace les précédentes).
     *
     * @param list<array{slug: string, qualifier: string}> $selection
     * @param array{user_id: ?int, label: string} $actor
     */
    public function setNoteTags(int $tenantId, int $noteId, array $selection, array $actor, bool $audit = true): bool
    {
        if ($noteId < 1 || !$this->ready()) {
            return false;
        }
        $before = $this->repo->linksFor($tenantId, Cat::SCOPE, Cat::OBJECT_NOTE, [(string) $noteId])[(string) $noteId] ?? [];
        $ok = $this->repo->replaceLinks($tenantId, Cat::SCOPE, Cat::OBJECT_NOTE, (string) $noteId, $selection, $actor['user_id']);
        if ($ok && $audit) {
            $this->audit($tenantId, $actor, 'field_note.tags.set', 'note', ['tags' => $before], ['tags' => $selection], $noteId, 'field_note');
        }

        return $ok;
    }

    /**
     * Étiquettes posées d'office à la création : la validité par défaut de la communauté, si la fiche n'en porte aucune.
     *
     * @param list<array{slug: string, qualifier: string}> $selection
     * @return list<array{slug: string, qualifier: string}>
     */
    public function withDefaults(int $tenantId, array $selection): array
    {
        $default = $this->rules($tenantId)['default_validity'];
        if ($default === '') {
            return $selection;
        }
        $ref = $this->referential($tenantId);
        foreach ($selection as $link) {
            if (($ref[$link['slug']]['family'] ?? '') === 'validity') {
                return $selection;
            }
        }
        $selection[] = ['slug' => $default, 'qualifier' => ''];

        return $selection;
    }

    /**
     * Règles de la communauté pour un type de fiche (rédaction sur le portail) : champs et étiquettes obligatoires.
     *
     * @param array<string, mixed> $input champs de la fiche
     * @param list<array{slug: string, qualifier: string}> $selection
     * @return list<string> messages pour le rédacteur
     */
    public function validateForKind(int $tenantId, string $kindCode, array $input, array $selection): array
    {
        $kind = $this->kinds($tenantId)[SseFieldNoteCatalog::normalizeKind($kindCode)] ?? null;
        if ($kind === null) {
            return [];
        }
        $errors = [];
        if (!$kind['enabled']) {
            $errors[] = 'Le type « ' . $kind['label'] . ' » n’est plus proposé par votre communauté : choisissez-en un autre.';
        }
        foreach ($kind['required_fields'] as $field) {
            if (trim((string) ($input[$field] ?? '')) === '') {
                $errors[] = 'Le champ « ' . Cat::REQUIRABLE_FIELDS[$field] . ' » est demandé pour le type « ' . $kind['label'] . ' ».';
            }
        }
        $ref = $this->referential($tenantId);
        $families = [];
        $keys = [];
        foreach ($selection as $link) {
            $keys[$link['slug']] = true;
            $families[$ref[$link['slug']]['family'] ?? ''] = true;
        }
        if (trim((string) ($input['intel_source'] ?? '')) !== '') {
            $families['discipline'] = true;
        }
        foreach ($kind['required_tags'] as $need) {
            if (str_starts_with($need, 'family:')) {
                $family = substr($need, 7);
                if (!isset($families[$family])) {
                    $errors[] = 'Ajoutez une étiquette de la famille « ' . Cat::familyLabel($family) . ' » : elle est demandée pour le type « ' . $kind['label'] . ' ».';
                }
            } elseif (!isset($keys[$need]) && isset($ref[$need])) {
                $errors[] = 'L’étiquette ' . $ref[$need]['code'] . ' est demandée pour le type « ' . $kind['label'] . ' ».';
            }
        }

        return $errors;
    }

    /**
     * Étiquettes présentées de plusieurs fiches.
     *
     * @param list<int> $noteIds
     * @return array<int, list<array<string, mixed>>>
     */
    public function tagsForNotes(int $tenantId, array $noteIds, string $lang = 'fr'): array
    {
        $links = $this->repo->linksFor($tenantId, Cat::SCOPE, Cat::OBJECT_NOTE, array_map('strval', $noteIds));
        if ($links === []) {
            return [];
        }
        $ref = $this->referential($tenantId);
        $order = array_flip(array_keys($ref));
        $out = [];
        foreach ($links as $id => $list) {
            $tags = [];
            foreach ($list as $link) {
                $tag = $ref[$link['slug']] ?? null;
                if ($tag !== null) {
                    $tags[] = self::present($tag, $link['qualifier'], $lang);
                }
            }
            usort($tags, static fn (array $a, array $b): int => ($order[$a['key']] ?? 0) <=> ($order[$b['key']] ?? 0));
            $out[(int) $id] = $tags;
        }

        return $out;
    }

    /**
     * Étiquette prête à afficher : libellé dans la langue, texte tel qu'il s'écrit sur la fiche (code + complément).
     *
     * @param array<string, mixed> $tag
     * @return array<string, mixed>
     */
    public static function present(array $tag, string $qualifier = '', string $lang = 'fr'): array
    {
        $text = (string) $tag['code'];
        $note = '';
        if ($qualifier !== '') {
            if ($tag['qualifier'] === 'date') {
                $ts = strtotime($qualifier);
                if ($ts !== false) {
                    $note = ($lang === 'en' ? 'valid until ' : 'valable jusqu’au ') . date('d/m/Y', $ts) . ($lang === 'en' ? ' at ' : ' à ') . date('H:i', $ts);
                    if ($ts < time()) {
                        $note .= $lang === 'en' ? ' (expired)' : ' (échue)';
                    }
                }
            } elseif ($tag['code'] === 'HANDLE VIA') {
                $text = 'HANDLE VIA ' . $qualifier . ' CHANNELS ONLY';
            } elseif ($tag['code'] === 'CUI') {
                $text = 'CUI//' . $qualifier;
            } else {
                $text .= ' ' . $qualifier;
            }
        }

        return [
            'key' => $tag['key'], 'code' => $tag['code'], 'family' => $tag['family'], 'family_label' => Cat::familyLabel((string) $tag['family'], $lang),
            'label' => self::label($tag, $lang), 'desc' => self::desc($tag, $lang), 'color' => $tag['color'], 'hex' => $tag['hex'] ?? Cat::colorHex((string) $tag['color']),
            'qualifier' => $qualifier, 'qualifier_kind' => $tag['qualifier'], 'text' => $text, 'note' => $note, 'status' => $tag['status'],
        ];
    }

    /**
     * Complète une fiche pour l'affichage : étiquettes par famille, catégories de la communauté, ligne de marquage de
     * diffusion, priorité de traitement, libellés américains de la cotation.
     *
     * @param array<string, mixed> $note fiche hydratée (SseFieldNoteRepository)
     * @param list<array<string, mixed>>|null $tags étiquettes déjà chargées (tagsForNotes), sinon lues ici
     * @return array<string, mixed>
     */
    public function decorate(int $tenantId, array $note, string $lang = 'fr', ?array $tags = null): array
    {
        $noteId = (int) ($note['id'] ?? 0);
        $tags ??= $this->tagsForNotes($tenantId, [$noteId], $lang)[$noteId] ?? [];
        $groups = [];
        foreach ($tags as $tag) {
            $groups[$tag['family']][] = $tag;
        }
        $categories = [];
        foreach ((array) ($note['themes'] ?? []) as $code) {
            $display = $this->themeDisplay($tenantId, (string) $code, $lang);
            $categories[] = ['code' => (string) $code, 'label' => $display['label'], 'hex' => $display['hex']];
        }
        foreach ($groups['category'] ?? [] as $tag) {
            $categories[] = ['code' => $tag['code'], 'label' => $tag['label'], 'hex' => $tag['hex']];
        }
        $source = (string) ($note['intel_source'] ?? '');
        $disciplines = $groups['discipline'] ?? [];
        if ($source !== '' && array_filter($disciplines, static fn (array $t): bool => $t['code'] === $source) === []) {
            $base = $this->referential($tenantId)[Cat::slug($source)] ?? null;
            array_unshift($disciplines, $base !== null ? self::present($base, '', $lang) : [
                'key' => Cat::slug($source), 'code' => $source, 'family' => 'discipline', 'family_label' => Cat::familyLabel('discipline', $lang),
                'label' => SseFieldNoteCatalog::sourceLabel($source), 'desc' => '', 'color' => 'blue', 'hex' => Cat::colorHex('blue'),
                'qualifier' => '', 'qualifier_kind' => '', 'text' => $source, 'note' => '', 'status' => Cat::STATUS_ACTIVE,
            ]);
        }
        $rel = strtoupper((string) ($note['source_reliability'] ?? 'C'));
        $cred = (int) ($note['info_credibility'] ?? 3);
        $handle = array_values(array_filter($groups['dissemination'] ?? [], static fn (array $t): bool => $t['code'] === 'HANDLE VIA'));

        $note['tags'] = $tags;
        $note['tag_groups'] = $groups;
        $note['tag_categories'] = $categories;
        $note['tag_disciplines'] = $disciplines;
        $note['marking'] = $this->marking($tenantId, (string) ($note['classification'] ?? 'interne'), $groups['dissemination'] ?? []);
        $note['handling'] = array_map(static fn (array $t): string => $t['qualifier'] !== '' ? $t['text'] : 'HANDLE VIA', $handle);
        $note['precedence'] = $this->precedenceFor($tenantId, (string) ($note['urgency'] ?? 'routine'), $lang);
        $note['evaluation_us'] = ['reliability' => Cat::reliabilityUs($rel), 'credibility' => Cat::credibilityUs($cred)];
        if (isset($note['note_kind'])) {
            $note['note_kind_label'] = $this->kindLabel($tenantId, (string) $note['note_kind']);
        }

        return $note;
    }

    /**
     * Ligne de marquage de la fiche : le marquage de sa classification (SseUsClassification::banner), suivi des mentions de
     * diffusion qu'elle porte. « REL TO » ou « DISPLAY ONLY » remplacent le NOFORN du niveau, sauf si NOFORN est posé à la main.
     * C'est une MENTION : aucun droit d'accès ne change.
     *
     * @param list<array<string, mixed>> $dissemination étiquettes présentées de la famille « dissemination »
     */
    public function marking(int $tenantId, string $classification, array $dissemination): string
    {
        $code = \App\Repositories\SseCaseRepository::normalizeClassification($classification);
        $segments = explode('//', SseUsClassification::banner($code));
        $controls = [];
        if (count($segments) > 1 && end($segments) === 'NOFORN') {
            array_pop($segments);
            $controls['NOFORN'] = 'NOFORN';
        }
        $unclassified = $segments[0] === 'CUI';
        $explicitNoforn = false;
        $release = false;
        foreach ($dissemination as $tag) {
            $tagCode = (string) $tag['code'];
            $q = (string) $tag['qualifier'];
            if ($tagCode === 'HANDLE VIA') {
                continue;
            }
            if ($tagCode === 'NOFORN') {
                $explicitNoforn = true;
                $controls['NOFORN'] = 'NOFORN';
            } elseif ($tagCode === 'REL TO') {
                $list = $q !== '' ? $q : $this->rules($tenantId)['rel_to'];
                $controls['REL TO'] = 'REL TO ' . ($list !== '' ? $list : 'USA');
                $release = true;
            } elseif ($tagCode === 'DISPLAY ONLY') {
                $controls['DISPLAY ONLY'] = trim('DISPLAY ONLY ' . $q);
                $release = true;
            } elseif ($tagCode === 'CUI') {
                if ($unclassified && $q !== '') {
                    $segments[] = $q;
                }
            } elseif ($tagCode === 'FOUO') {
                if ($unclassified) {
                    $controls['FOUO'] = 'FOUO';
                }
            } else {
                $controls[$tagCode] = trim($tagCode . ' ' . $q);
            }
        }
        if ($release && !$explicitNoforn) {
            unset($controls['NOFORN']);
        }

        return implode('//', $segments) . ($controls !== [] ? '//' . implode('/', array_values($controls)) : '');
    }

    /**
     * Familles et étiquettes à proposer : au choix sur une fiche (actives, plus celles que la fiche porte déjà), ou au filtre
     * de la liste (actives, plus celles que des fiches portent encore). Les catégories livrées n'y sont pas : elles se
     * choisissent comme thèmes de la fiche.
     *
     * @param list<string> $keep clés à garder même désactivées
     * @return array<string, array{key: string, label: string, hint: string, tags: list<array<string, mixed>>}>
     */
    public function pickerFamilies(int $tenantId, string $lang = 'fr', bool $forPicking = true, array $keep = []): array
    {
        $usage = $forPicking ? [] : $this->repo->usage($tenantId, Cat::SCOPE);
        $out = [];
        foreach ($this->referential($tenantId) as $key => $tag) {
            if ($tag['stored'] !== 'links') {
                continue;
            }
            if ($tag['status'] !== Cat::STATUS_ACTIVE && !in_array($key, $keep, true) && (int) ($usage[$key] ?? 0) < 1) {
                continue;
            }
            $family = (string) $tag['family'];
            $out[$family] ??= ['key' => $family, 'label' => Cat::familyLabel($family, $lang), 'hint' => Cat::familyHint($family, $lang), 'tags' => []];
            $out[$family]['tags'][] = self::present($tag, '', $lang);
        }

        return $out;
    }

    /**
     * Filtres de la liste des fiches par étiquette : « tag » (clé), « tag_family » (famille) et recherche par code (« q »).
     *
     * @param array<string, string> $filters
     * @return array<string, mixed> filtres à passer à SseFieldNoteRepository::listForTenant (ids, q_ids, theme)
     */
    public function noteFilters(int $tenantId, array $filters): array
    {
        $ref = $this->referential($tenantId);
        $out = [];
        $toIds = fn (array $slugs): array => array_map('intval', $this->repo->objectIdsWith($tenantId, Cat::SCOPE, Cat::OBJECT_NOTE, $slugs));
        $key = (string) ($filters['tag'] ?? '');
        $family = (string) ($filters['tag_family'] ?? '');
        if ($key !== '' && isset($ref[$key])) {
            if ($ref[$key]['stored'] === 'themes') {
                $out['theme'] = $ref[$key]['code'];
            } else {
                $out['ids'] = $toIds([$key]);
            }
        } elseif ($family !== '' && Cat::hasFamily($family) && Cat::FAMILIES[$family]['pick']) {
            $slugs = [];
            foreach ($ref as $k => $tag) {
                if ($tag['family'] === $family && $tag['stored'] === 'links') {
                    $slugs[] = $k;
                }
            }
            $out['ids'] = $toIds($slugs);
        }
        $q = Cat::normalizeCode($filters['q'] ?? '');
        if ($q !== '' && mb_strlen($q) >= 2) {
            $slugs = [];
            foreach ($ref as $k => $tag) {
                if ($tag['stored'] === 'links' && ($tag['code'] === $q || str_starts_with((string) $tag['code'], $q . ' ') || $k === Cat::slug($q))) {
                    $slugs[] = $k;
                }
            }
            if ($slugs !== []) {
                $out['q_ids'] = $toIds($slugs);
            }
        }

        return $out;
    }

    // ——————————————————————————— API du jeu (additif) ———————————————————————————

    /**
     * Champs AJOUTÉS au référentiel du rédacteur ATAK (les clés existantes ne changent pas) : étiquettes actives de la
     * communauté (ou catalogue livré sans communauté), familles, priorités de traitement, libellés de la cotation.
     *
     * @return array<string, mixed>
     */
    public function clientCatalog(?int $tenantId): array
    {
        $tenant = $tenantId !== null && $tenantId > 0 ? $tenantId : 0;
        $tags = [];
        $families = [];
        foreach ($this->activeByFamily($tenant) as $family => $list) {
            $listed = 0;
            foreach ($list as $tag) {
                if ($tag['stored'] !== 'links') {
                    continue;
                }
                $listed++;
                $tags[] = [
                    'key' => $tag['key'], 'code' => $tag['code'], 'family' => $family, 'label' => $tag['label_fr'], 'label_en' => $tag['label_en'],
                    'hint' => $tag['desc_fr'], 'hint_en' => $tag['desc_en'], 'color' => $tag['hex'], 'qualifier' => $tag['qualifier'],
                ];
            }
            if ($listed > 0) {
                $families[] = ['code' => $family, 'label' => Cat::familyLabel($family), 'label_en' => Cat::familyLabel($family, 'en'), 'hint' => Cat::familyHint($family)];
            }
        }
        $precedences = [];
        foreach ($this->rules($tenant)['precedence'] as $urgency => $code) {
            $precedences[] = ['urgency' => $urgency, 'code' => $code, 'label' => Cat::PRECEDENCES[$code]['fr'], 'label_en' => Cat::PRECEDENCES[$code]['en']];
        }
        $reliability = [];
        foreach (Cat::RELIABILITY as $code => $def) {
            $reliability[] = ['code' => $code, 'label' => $def['fr'], 'label_en' => $def['en']];
        }
        $credibility = [];
        foreach (Cat::CREDIBILITY as $code => $def) {
            $credibility[] = ['code' => $code, 'label' => $def['fr'], 'label_en' => $def['en']];
        }
        $suggestions = [];
        foreach ($this->kinds($tenant) as $code => $kind) {
            $suggestions[$code] = $kind['suggested'];
        }

        return [
            'tags_enabled' => $tenant > 0 && $this->ready(),
            'tags_max' => Cat::TAGS_PER_NOTE,
            'tag_families' => $families,
            'tags' => $tags,
            'tag_suggestions' => $suggestions,
            'precedences' => $precedences,
            'evaluation' => ['reliability' => $reliability, 'credibility' => $credibility],
        ];
    }

    /** Étiquettes d'une fiche au format de l'API du jeu. @param list<array<string, mixed>> $tags @return list<array<string, string>> */
    public static function apiTags(array $tags): array
    {
        return array_map(static fn (array $t): array => [
            'key' => (string) $t['key'], 'code' => (string) $t['code'], 'family' => (string) $t['family'], 'label' => (string) $t['label'],
            'qualifier' => (string) $t['qualifier'], 'text' => (string) $t['text'], 'color' => (string) $t['hex'],
        ], $tags);
    }

    // ——————————————————————————— Import / export ———————————————————————————

    /** @return array<string, mixed> */
    public function export(int $tenantId): array
    {
        $tags = [];
        foreach ($this->referential($tenantId) as $tag) {
            if ($tag['origin'] === 'catalog' && !$tag['customized']) {
                continue;
            }
            $tags[] = [
                'code' => $tag['code'], 'family' => $tag['family'], 'origin' => $tag['origin'], 'label_fr' => $tag['label_fr'], 'label_en' => $tag['label_en'],
                'description_fr' => $tag['desc_fr'], 'description_en' => $tag['desc_en'], 'color' => $tag['color'], 'qualifier' => $tag['qualifier'],
                'sort' => $tag['sort'], 'status' => $tag['status'],
            ];
        }
        $kinds = [];
        foreach ($this->kinds($tenantId) as $code => $kind) {
            $kinds[$code] = [
                'label' => $kind['label'], 'enabled' => $kind['enabled'], 'sort' => $kind['sort'], 'suggested' => $kind['suggested'],
                'required_fields' => $kind['required_fields'], 'required_tags' => $kind['required_tags'], 'template' => $kind['template'],
            ];
        }

        return ['format' => self::FORMAT, 'version' => self::FORMAT_VERSION, 'tags' => $tags, 'kinds' => $kinds, 'rules' => $this->rules($tenantId)];
    }

    /**
     * Importe un référentiel exporté. Validation stricte de tout le fichier AVANT la première écriture : une seule erreur et
     * rien n'est appliqué. Rien n'est supprimé : les étiquettes du fichier sont créées ou réglées.
     *
     * @param array{user_id: ?int, label: string} $actor
     * @return array{ok: bool, errors: list<string>, created: int, updated: int}
     */
    public function import(int $tenantId, string $json, array $actor): array
    {
        $fail = static fn (array $errors): array => ['ok' => false, 'errors' => array_slice($errors, 0, 12), 'created' => 0, 'updated' => 0];
        if (!$this->ready()) {
            return $fail([self::notReadyMessage()]);
        }
        if (strlen($json) > 400_000) {
            return $fail(['Le fichier dépasse 400 Ko.']);
        }
        $data = json_decode($json, true);
        if (!is_array($data)) {
            return $fail(['Le fichier n’est pas un JSON lisible.']);
        }
        if (($data['format'] ?? '') !== self::FORMAT || (int) ($data['version'] ?? 0) !== self::FORMAT_VERSION) {
            return $fail(['Ce fichier n’est pas un référentiel de fiches exporté d’ici (format ou version inattendus).']);
        }
        $unknown = array_diff(array_keys($data), ['format', 'version', 'tags', 'kinds', 'rules', 'exported_at', 'community']);
        if ($unknown !== []) {
            return $fail(['Rubrique inconnue dans le fichier : ' . implode(', ', array_map('strval', $unknown)) . '.']);
        }
        $errors = [];
        $ref = $this->referential($tenantId);
        $delivered = Cat::delivered();
        $plan = [];
        $tags = $data['tags'] ?? [];
        if (!is_array($tags) || count($tags) > 400) {
            return $fail(['La liste des étiquettes est absente, illisible ou dépasse 400 entrées.']);
        }
        $allowed = ['code', 'family', 'origin', 'label_fr', 'label_en', 'description_fr', 'description_en', 'color', 'qualifier', 'sort', 'status'];
        foreach ($tags as $i => $t) {
            $where = 'Étiquette n° ' . ((int) $i + 1);
            if (!is_array($t)) {
                $errors[] = $where . ' : entrée illisible.';
                continue;
            }
            if (array_diff(array_keys($t), $allowed) !== []) {
                $errors[] = $where . ' : champ inconnu (' . implode(', ', array_map('strval', array_diff(array_keys($t), $allowed))) . ').';
                continue;
            }
            foreach ($t as $field => $v) {
                if (!is_scalar($v)) {
                    $errors[] = $where . ' : le champ ' . $field . ' doit être un texte ou un nombre.';
                    continue 2;
                }
            }
            $code = Cat::normalizeCode($t['code'] ?? '');
            $family = (string) ($t['family'] ?? '');
            $where .= ' (' . $code . ')';
            $key = $family === 'category' && isset($delivered[Cat::categoryKey($code)]) ? Cat::categoryKey($code) : Cat::slug($code);
            $isDelivered = isset($delivered[$key]);
            if (!Cat::isValidCode($code)) {
                $errors[] = $where . ' : code invalide.';
            }
            if (!Cat::hasFamily($family) || !Cat::FAMILIES[$family]['pick']) {
                $errors[] = $where . ' : famille inconnue.';
            } elseif ($isDelivered && $delivered[$key]['family'] !== $family) {
                $errors[] = $where . ' : ce code appartient à la famille « ' . Cat::familyLabel($delivered[$key]['family']) . ' » du catalogue livré.';
            } elseif (!$isDelivered && isset($ref[$key]) && $ref[$key]['family'] !== $family) {
                $errors[] = $where . ' : ce code existe déjà dans une autre famille.';
            }
            $labelFr = self::clean((string) ($t['label_fr'] ?? ''), 1000);
            $labelEn = self::clean((string) ($t['label_en'] ?? ''), 1000);
            $descFr = self::clean((string) ($t['description_fr'] ?? ''), 1000);
            $descEn = self::clean((string) ($t['description_en'] ?? ''), 1000);
            if ($labelFr === '' || mb_strlen($labelFr) > Cat::LABEL_MAX || mb_strlen($labelEn) > Cat::LABEL_MAX) {
                $errors[] = $where . ' : libellé absent ou de plus de ' . Cat::LABEL_MAX . ' caractères.';
            }
            if (mb_strlen($descFr) > Cat::DESCRIPTION_MAX || mb_strlen($descEn) > Cat::DESCRIPTION_MAX) {
                $errors[] = $where . ' : description de plus de ' . Cat::DESCRIPTION_MAX . ' caractères.';
            }
            $color = (string) ($t['color'] ?? 'slate');
            if (!isset(Cat::COLORS[$color])) {
                $errors[] = $where . ' : couleur inconnue.';
            }
            $status = (string) ($t['status'] ?? Cat::STATUS_ACTIVE);
            if (!isset(Cat::STATUSES[$status])) {
                $errors[] = $where . ' : état inconnu.';
            }
            $qualifier = (string) ($t['qualifier'] ?? '');
            if (!in_array($qualifier, ['', 'number', 'text', 'date'], true)) {
                $errors[] = $where . ' : complément inconnu.';
            }
            $sort = (int) ($t['sort'] ?? 0);
            if ($sort < 0 || $sort > 9999) {
                $errors[] = $where . ' : ordre hors de 0 à 9999.';
            }
            $real = SseWantedRealNames::find(implode(' | ', [$isDelivered ? '' : $code, $labelFr, $labelEn, $descFr, $descEn]));
            if ($real !== null) {
                $errors[] = $where . ' : « ' . $real . ' » désigne une organisation réelle.';
            }
            if (isset($plan[$key])) {
                $errors[] = $where . ' : code présent deux fois dans le fichier.';
            }
            $plan[$key] = [
                'new' => !isset($ref[$key]),
                'data' => [
                    'family' => $family, 'code' => $isDelivered ? $delivered[$key]['code'] : $code, 'name' => $labelFr, 'name_en' => $labelEn !== '' ? $labelEn : $labelFr,
                    'description' => $descFr, 'description_en' => $descEn !== '' ? $descEn : $descFr, 'color' => $color,
                    'qualifier' => $isDelivered ? $delivered[$key]['qualifier'] : $qualifier, 'sort_order' => $sort,
                    'origin' => $isDelivered ? 'catalog' : 'custom', 'status' => $isDelivered && $status === Cat::STATUS_ARCHIVED ? Cat::STATUS_DISABLED : $status,
                ],
            ];
        }
        $kindPlan = [];
        $kinds = $data['kinds'] ?? [];
        if (!is_array($kinds)) {
            $errors[] = 'La rubrique des types de fiche est illisible.';
            $kinds = [];
        }
        foreach ($kinds as $code => $k) {
            if (!isset(SseFieldNoteCatalog::KINDS[(string) $code]) || !is_array($k)) {
                $errors[] = 'Type de fiche inconnu dans le fichier : ' . (string) $code . '.';
                continue;
            }
            if (array_diff(array_keys($k), ['label', 'enabled', 'sort', 'suggested', 'required_fields', 'required_tags', 'template']) !== []) {
                $errors[] = 'Type ' . (string) $code . ' : champ inconnu.';
                continue;
            }
            $real = SseWantedRealNames::find((string) ($k['label'] ?? '') . ' | ' . (is_string($k['template'] ?? '') ? (string) ($k['template'] ?? '') : ''));
            if ($real !== null) {
                $errors[] = 'Type ' . (string) $code . ' : « ' . $real . ' » désigne une organisation réelle.';
            }
            if (mb_strlen((string) ($k['label'] ?? '')) > Cat::LABEL_MAX || !is_string($k['template'] ?? '') || mb_strlen((string) ($k['template'] ?? '')) > SseFieldNoteCatalog::BODY_MAX_LENGTH) {
                $errors[] = 'Type ' . (string) $code . ' : libellé ou trame trop longs.';
            }
            foreach (['suggested', 'required_fields', 'required_tags'] as $listField) {
                if (isset($k[$listField]) && !is_array($k[$listField])) {
                    $errors[] = 'Type ' . (string) $code . ' : ' . $listField . ' doit être une liste.';
                }
            }
            $kindPlan[(string) $code] = $k;
        }
        $rules = $data['rules'] ?? null;
        if ($rules !== null && (!is_array($rules) || array_diff(array_keys($rules), ['default_validity', 'precedence', 'rel_to']) !== [] || !is_array($rules['precedence'] ?? []))) {
            $errors[] = 'La rubrique des règles est illisible.';
            $rules = null;
        }
        if (is_array($rules)) {
            foreach ((array) ($rules['precedence'] ?? []) as $urgency => $code) {
                if (!isset(SseFieldNoteCatalog::URGENCIES[(string) $urgency]) || !isset(Cat::PRECEDENCES[strtoupper((string) $code)])) {
                    $errors[] = 'Règles : correspondance urgence ↔ priorité inconnue (' . (string) $urgency . ').';
                }
            }
            $real = SseWantedRealNames::find((string) ($rules['rel_to'] ?? ''));
            if ($real !== null) {
                $errors[] = 'Règles : « ' . $real . ' » désigne une organisation réelle.';
            }
        }
        $custom = count(array_filter($ref, static fn (array $t): bool => $t['origin'] === 'custom'))
            + count(array_filter($plan, static fn (array $p): bool => $p['new'] && $p['data']['origin'] === 'custom'));
        if ($custom > Cat::CUSTOM_MAX) {
            $errors[] = 'L’import dépasserait le nombre maximal d’étiquettes propres (' . Cat::CUSTOM_MAX . ').';
        }
        if ($errors !== []) {
            return $fail($errors);
        }

        $created = 0;
        $updated = 0;
        foreach ($plan as $key => $p) {
            $this->repo->save($tenantId, $key, $p['data'], $actor['user_id'], self::scopeFor((string) $p['data']['origin']));
            $p['new'] ? $created++ : $updated++;
        }
        $this->forget();
        foreach ($kindPlan as $code => $k) {
            $r = $this->saveKindQuiet($tenantId, $code, $k, $actor);
            if (!$r) {
                $errors[] = 'Type ' . $code . ' : réglage ignoré.';
            }
        }
        if (is_array($rules)) {
            $current = $this->rules($tenantId);
            $ref = $this->referential($tenantId);
            $validity = (string) ($rules['default_validity'] ?? '');
            $this->repo->saveSetting($tenantId, 'rules', [
                'default_validity' => isset($ref[$validity]) && $ref[$validity]['family'] === 'validity' ? $validity : '',
                'precedence' => array_map('strtoupper', array_map('strval', (array) ($rules['precedence'] ?? []))) + $current['precedence'],
                'rel_to' => mb_strtoupper(self::clean((string) ($rules['rel_to'] ?? ''), Cat::QUALIFIER_MAX), 'UTF-8'),
            ], $actor['user_id']);
        }
        $this->forget();
        $this->audit($tenantId, $actor, 'field_note.referential.import', 'import', null, ['created' => $created, 'updated' => $updated, 'kinds' => array_keys($kindPlan), 'rules' => is_array($rules)]);

        return ['ok' => true, 'errors' => $errors, 'created' => $created, 'updated' => $updated];
    }

    /**
     * @param array<string, mixed> $k
     * @param array{user_id: ?int, label: string} $actor
     */
    private function saveKindQuiet(int $tenantId, string $code, array $k, array $actor): bool
    {
        $input = array_intersect_key($k, array_flip(['label', 'enabled', 'sort', 'suggested', 'required_fields', 'required_tags', 'template']));

        return $this->saveKind($tenantId, $code, $input, $actor)['ok'];
    }

    // ——————————————————————————— Outils ———————————————————————————

    /** Portée d'une ligne créée ici : un réglage du catalogue livré ne concerne que les fiches, une étiquette propre vaut pour tout le bureau. */
    private static function scopeFor(string $origin): string
    {
        return $origin === 'catalog' ? SseTagRepository::SCOPE_NOTES : SseTagRepository::SCOPE_ALL;
    }

    public static function notReadyMessage(): string
    {
        return 'L’étape de base de données des étiquettes n’est pas encore passée : le catalogue livré reste en lecture seule.';
    }

    /** @return array<string, array<string, mixed>> */
    private function settings(int $tenantId): array
    {
        return $this->settingsMemo[$tenantId] ??= $this->repo->settings($tenantId);
    }

    /** @param array<string, array<string, mixed>> $ref */
    private function isRequirement(array $ref, string $need): bool
    {
        if (str_starts_with($need, 'family:')) {
            $family = substr($need, 7);

            return Cat::hasFamily($family) && Cat::FAMILIES[$family]['pick'] && $family !== 'category';
        }

        return isset($ref[$need]) && $ref[$need]['stored'] === 'links';
    }

    /** @param array<string, array<string, mixed>> $ref */
    private function nextSort(array $ref, string $family): int
    {
        $max = 0;
        foreach ($ref as $tag) {
            if ($tag['family'] === $family) {
                $max = max($max, (int) $tag['sort']);
            }
        }

        return min(9999, $max + 10);
    }

    /**
     * Ligne complète d'une étiquette résolue (une étiquette livrée sans réglage n'a pas encore de ligne).
     *
     * @param array<string, mixed> $tag
     * @param array<string, mixed> $over
     * @return array<string, mixed>
     */
    private function rowOf(array $tag, array $over): array
    {
        return $over + [
            'family' => $tag['family'], 'code' => $tag['code'], 'name' => $tag['label_fr'], 'name_en' => $tag['label_en'],
            'description' => $tag['desc_fr'], 'description_en' => $tag['desc_en'], 'color' => $tag['color'], 'qualifier' => $tag['qualifier'],
            'sort_order' => $tag['sort'], 'origin' => $tag['origin'], 'status' => $tag['status'],
        ];
    }

    private static function clean(string $text, int $max): string
    {
        $text = trim((string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $text));
        $text = (string) preg_replace('/\s+/u', ' ', $text);

        return mb_substr($text, 0, $max);
    }

    /**
     * @param array{user_id: ?int, label: string} $actor
     * @param array<string, mixed>|null $before
     * @param array<string, mixed>|null $after
     */
    private function audit(int $tenantId, array $actor, string $action, string $subject, ?array $before, ?array $after, int $objectId = 0, string $objectType = 'field_note_referential'): void
    {
        $entry = [
            'tenant_id' => $tenantId, 'actor_user_id' => $actor['user_id'], 'actor_label' => $actor['label'] !== '' ? $actor['label'] : 'Gestion SSE',
            'action' => $action, 'object_type' => $objectType, 'object_id' => $objectId, 'reason' => $subject, 'before' => $before, 'after' => $after,
        ];
        try {
            if ($this->auditWriter !== null) {
                ($this->auditWriter)($entry);

                return;
            }
            (new SseAuditLogRepository())->write($entry);
        } catch (\Throwable $e) {
            error_log('[sse-fiches] journal indisponible : ' . $e->getMessage());
        }
    }
}
