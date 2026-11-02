<?php

declare(strict_types=1);

namespace App\Services\Academy;

use App\Core\Container;
use App\Repositories\CompetencyPathwayRepository;
use App\Repositories\PilotQualificationRuleRepository;
use App\Repositories\QualificationAwardRuleRepository;
use App\Repositories\QualificationDefinitionRepository;
use App\Repositories\QualificationIssuerRepository;
use App\Services\Personnel\QualificationAwardRuleService;
use App\Services\Personnel\QualificationBadgeStorageService;
use App\Services\Personnel\QualificationCriteriaEngine as Engine;
use App\Services\Personnel\QualificationTemporalStatusService as Temporal;
use App\Services\Training\CompetencyCatalogService;
use App\Services\Training\CompetencyPathwayGenerator;
use App\Support\JobCatalog\JobCatalog;
use App\Support\QualificationCatalog\QualificationCatalogExplorer;
use App\Support\QualificationCriteriaCatalog as Cat;
use Throwable;

/**
 * Tout ce qu'un membre peut obtenir, réuni pour le catalogue et le tableau de bord Academy : formations (Academy et
 * LMS), brevets et licences, qualifications de la communauté (quel que soit leur mode d'obtention), parcours de
 * compétence, compétences du référentiel et émetteurs. Chaque élément porte l'état du membre connecté.
 *
 * Onglets Qualifications et Brevets : aussi le catalogue de référence (packs pays et OTAN de config/qualification_catalog,
 * éléments « r-<id> », origine « catalogue ») ; une qualification de la communauté importée de ce catalogue (même code)
 * a l'origine « referentiel » et remplace l'entrée du catalogue, les autres ont l'origine « communaute ».
 *
 * collect() lit les sources (chacune peut manquer : la page reste utilisable) ; assemble() et filter() sont purs.
 */
final class AcademyOffer
{
    public const TABS = ['tout', 'formations', 'brevets', 'qualifications', 'parcours', 'competences', 'emetteurs'];
    public const STATE_GROUPS = [
        'acquis' => ['held', 'expiring', 'renew'],
        'en-cours' => ['progress', 'pending', 'eligible'],
        'disponible' => ['available'],
        'prerequis' => ['missing', 'blocked'],
    ];
    public const SORTS = ['pertinence', 'nom', 'etat', 'duree', 'pays', 'niveau'];
    /** D'où vient une qualification : catalogue de référence (à importer), déjà dans le référentiel, création locale. */
    public const ORIGINS = ['catalogue', 'referentiel', 'communaute'];
    /** Nature d'une qualification (filtre « Catégorie » des onglets Qualifications et Brevets). */
    public const NATURES = ['brevet', 'qualification', 'cursus', 'licence'];
    public const PER_PAGE = 36;
    private const STATE_ORDER = ['renew' => 0, 'expiring' => 1, 'eligible' => 2, 'progress' => 3, 'pending' => 4, 'available' => 5, 'missing' => 6, 'blocked' => 7, 'held' => 8, 'none' => 9];

    /**
     * @param array<string, mixed> $tenant
     * @param list<array<string, mixed>> $academyCourses
     * @param array<string, array<string, mixed>> $states
     * @param list<array<string, mixed>> $legacyCourses
     * @param list<array<string, mixed>> $legacyEnrollments
     * @return array<string, mixed>
     */
    public static function collect(int $tenantId, int $userId, ?array $tenant, array $academyCourses, array $states, array $legacyCourses, array $legacyEnrollments, bool $staff, bool $withReference = false): array
    {
        $src = [
            'tenant_name' => (string) ($tenant['name'] ?? ''),
            'staff' => $staff,
            'academy_courses' => $academyCourses,
            'states' => $states,
            'legacy_courses' => $legacyCourses,
            'legacy_enrollments' => $legacyEnrollments,
            'definitions' => [], 'settings' => [], 'pilot' => [], 'prereqs' => [], 'issuers' => [], 'evaluations' => [],
            'names' => ['courses' => [], 'definitions' => [], 'scopes' => []],
            'pathways' => [], 'competencies' => [], 'catalog' => [],
            // Catalogue de référence (packs pays et OTAN) : seulement pour la page Catalogue.
            'reference' => $withReference ? (self::safe(static fn (): array => QualificationCatalogExplorer::rows(function_exists('locale') ? (string) locale() : 'fr')) ?? []) : [],
        ];
        if ($tenantId < 1) {
            return self::assemble($src);
        }
        $badges = self::safe(static fn () => Container::get(QualificationBadgeStorageService::class));
        foreach (self::safe(static fn (): array => Container::get(QualificationDefinitionRepository::class)->listForTenant($tenantId)) ?? [] as $d) {
            $path = trim((string) ($d['badge_media_path'] ?? ''));
            $d['badge_url'] = $path !== '' && $badges instanceof QualificationBadgeStorageService ? $badges->publicUrl($path) : '';
            $src['definitions'][(int) $d['id']] = $d;
        }
        if ($src['definitions'] !== []) {
            $ruleRepo = self::safe(static fn () => Container::get(QualificationAwardRuleRepository::class));
            if ($ruleRepo instanceof QualificationAwardRuleRepository) {
                $src['settings'] = self::safe(static fn (): array => $ruleRepo->listForTenant($tenantId)) ?? [];
                $src['prereqs'] = self::safe(static fn (): array => $ruleRepo->prerequisitesByDefinition($tenantId)) ?? [];
            }
            foreach (self::safe(static fn (): array => Container::get(PilotQualificationRuleRepository::class)->listForTenant($tenantId)) ?? [] as $r) {
                $src['pilot'][(int) $r['definition_id']] = true;
            }
            $src['issuers'] = self::safe(static fn (): array => (new QualificationIssuerRepository())->listForTenant($tenantId)) ?? [];
            $service = self::safe(static fn () => Container::get(QualificationAwardRuleService::class));
            if ($service instanceof QualificationAwardRuleService && $userId > 0) {
                $src['names'] = self::safe(static fn (): array => $service->names($tenantId)) ?? $src['names'];
                foreach ($src['definitions'] as $defId => $_) {
                    $settings = array_merge(QualificationAwardRuleService::defaults(), $src['settings'][$defId] ?? []);
                    $settings['rule'] = Engine::normalize($settings['rule_json'] ?? null);
                    $ev = self::safe(static fn (): array => $service->evaluateMember($tenantId, $defId, $settings, $userId));
                    if (is_array($ev)) {
                        $src['evaluations'][$defId] = $ev;
                    }
                }
            }
        }
        $catalogService = self::safe(static fn () => Container::get(CompetencyCatalogService::class));
        if ($catalogService instanceof CompetencyCatalogService) {
            $catalog = self::safe(static fn (): array => $catalogService->build($tenantId, false)) ?? [];
            $src['catalog'] = $catalog;
            $src['competencies'] = $catalog['competencies'] ?? [];
            foreach (self::safe(static fn (): array => Container::get(CompetencyPathwayRepository::class)->listForTenant($tenantId)) ?? [] as $p) {
                $p['steps'] = CompetencyPathwayGenerator::attachUrls($p['steps'] ?? [], $catalog);
                $src['pathways'][] = $p;
            }
        }

        return self::assemble($src);
    }

    /**
     * @param array<string, mixed> $src
     * @return array{items: list<array<string, mixed>>, totals: array<string, int>, names: array<string, mixed>, references: int}
     */
    public static function assemble(array $src): array
    {
        $states = array_filter($src['states'] ?? [], static fn ($k): bool => $k !== '__notes', ARRAY_FILTER_USE_KEY);
        $lang = function_exists('locale') && locale() === 'en' ? 'en' : 'fr';
        $local = static fn (mixed $v): string => is_array($v) ? (string) ($v[$lang] ?? $v['fr'] ?? '') : (string) $v;
        $tenantName = (string) ($src['tenant_name'] ?? '');
        $items = [];

        // Formations Academy en sept étapes (intégrées ATHENA ou rédigées par la communauté).
        foreach ($src['academy_courses'] ?? [] as $c) {
            $key = (string) ($c['key'] ?? '');
            if ($key === '') {
                continue;
            }
            $st = $states[$key] ?? null;
            $chapters = count($c['chapters'] ?? []);
            $pct = null;
            $state = 'available';
            $next = null;
            if ($st !== null) {
                $total = max(1, count($st['snapshot']['chapters'] ?? $c['chapters'] ?? []) * 7);
                $pct = !empty($st['completed']) ? 100 : (int) floor(((int) ($st['chapter'] ?? 0) * 7 + (int) ($st['stage'] ?? 0)) / $total * 100);
                $state = !empty($st['completed']) ? 'held' : ((int) ($st['stage'] ?? 0) === 6 ? 'pending' : 'progress');
                if (empty($st['completed'])) {
                    $chapter = ($st['snapshot']['chapters'] ?? $c['chapters'] ?? [])[(int) ($st['chapter'] ?? 0)] ?? [];
                    $next = ['chapter' => (int) ($st['chapter'] ?? 0) + 1, 'chapter_title' => $local($chapter['title'] ?? ''), 'stage' => AcademyCurriculum::STAGES[(int) ($st['stage'] ?? 0)] ?? 'theory'];
                }
            }
            $licence = $local($c['license'] ?? '');
            $builtIn = !empty($c['built_in']);
            $items[] = [
                'id' => 'a-' . $key,
                'kind' => 'course',
                'tabs' => $licence !== '' ? ['formations', 'brevets'] : ['formations'],
                'title' => $local($c['title'] ?? $key),
                'summary' => $local($c['summary'] ?? ''),
                'badge' => '',
                'symbol' => $licence !== '' ? 'licence' : 'course',
                'issuer' => $builtIn ? 'ATHENA Academy' : $tenantName,
                'issuer_id' => 0,
                'category' => '',
                'level' => '',
                'duration_min' => (int) ($c['duration_minutes'] ?? 0) ?: null,
                'chapters' => $chapters,
                'licence' => $licence,
                'validity' => $licence !== '' ? ['licence'] : [],
                'modes' => ['course'],
                'how' => [['type' => 'academy_course']],
                'source' => $builtIn ? 'athena' : 'community',
                'state' => $state,
                'pct' => $pct,
                'next' => $next,
                'missing' => [],
                'course_key' => $key,
                'enrolled' => $st !== null,
                'licence_issued' => $st !== null && !empty($st['completed']) && !empty($st['license']),
                'award' => null,
            ];
        }

        // Formations du LMS (communauté et plateforme).
        $enrollByCourse = [];
        foreach ($src['legacy_enrollments'] ?? [] as $e) {
            $enrollByCourse[(int) ($e['course_id'] ?? 0)] ??= $e;
        }
        $slugById = [];
        foreach ($src['legacy_courses'] ?? [] as $c) {
            $id = (int) ($c['id'] ?? 0);
            $slug = (string) ($c['slug'] ?? '');
            if ($slug === '') {
                continue;
            }
            $slugById[$id] = $slug;
            $e = $c['enrollment'] ?? ($enrollByCourse[$id] ?? null);
            $status = (string) ($e['status'] ?? '');
            $state = match (true) {
                $status === 'completed' => 'held',
                $status === 'pending_approval' => 'pending',
                in_array($status, ['in_progress', 'assigned', 'enrolled', 'active'], true) => 'progress',
                default => 'available',
            };
            $platform = ($c['lms_scope'] ?? '') === 'platform' || (($c['tenant_id'] ?? null) === null && ($c['source'] ?? '') !== 'legacy');
            $items[] = [
                'id' => 'n-' . ($id > 0 ? $id : $slug),
                'kind' => 'course',
                'tabs' => ['formations'],
                'title' => (string) ($c['title'] ?? $slug),
                'summary' => trim(strip_tags((string) ($c['short_description'] ?? ''))),
                'badge' => '',
                'symbol' => 'course',
                'issuer' => $platform ? 'ATHENA' : $tenantName,
                'issuer_id' => 0,
                'category' => trim((string) ($c['category'] ?? '')),
                'level' => trim((string) ($c['level'] ?? '')),
                'duration_min' => (int) ($c['estimated_minutes'] ?? 0) ?: null,
                'chapters' => 0,
                'licence' => '',
                'validity' => (int) ($c['validity_days'] ?? 0) > 0 ? ['days' => (int) $c['validity_days']] : [],
                'modes' => ['course'],
                'how' => [['type' => 'lms_course', 'certifying' => !empty($c['is_certifying'])]],
                'source' => $platform ? 'athena' : 'community',
                'state' => $state,
                'pct' => $e !== null ? (int) ($e['progress_percent'] ?? $c['progress_percent'] ?? 0) : null,
                'next' => null,
                'missing' => [],
                'url' => url('academy/formations/' . rawurlencode($slug)),
                'certificate_id' => (int) ($e['certificate_id'] ?? 0),
                'award' => null,
            ];
        }

        // Qualifications de la communauté.
        $issuers = [];
        foreach ($src['issuers'] ?? [] as $i) {
            if (empty($i['archived_at'])) {
                $issuers[(int) $i['id']] = $i;
            }
        }
        $definitions = $src['definitions'] ?? [];
        $names = $src['names'] ?? [];
        $names['definitions'] = ($names['definitions'] ?? []) + array_map(static fn (array $d): string => (string) ($d['name'] ?? ''), $definitions);
        $staff = !empty($src['staff']);
        $issuerCounts = [];
        $refByCode = [];
        foreach ($src['reference'] ?? [] as $row) {
            $refByCode[strtoupper((string) ($row['code'] ?? ''))] = $row;
        }
        $tenantCodes = [];
        foreach ($definitions as $defId => $d) {
            $settings = $src['settings'][$defId] ?? null;
            $ev = $src['evaluations'][$defId] ?? null;
            $award = $ev['award'] ?? null;
            $temporal = (string) ($ev['temporal'] ?? '');
            $engineState = (string) ($ev['state'] ?? '');
            $state = match ($engineState) {
                Engine::HELD => in_array($temporal, [Temporal::EXPIRING_SOON, Temporal::EXPIRED_GRACE], true) ? 'expiring' : 'held',
                Engine::RENEW => 'renew',
                Engine::PENDING => 'pending',
                Engine::ELIGIBLE => 'eligible',
                Engine::BLOCKED => 'blocked',
                Engine::PROGRESS => !($ev['prereq']['ok'] ?? true) ? 'missing' : ((int) ($ev['pct'] ?? 0) > 0 ? 'progress' : 'available'),
                default => 'available',
            };
            if ($engineState === Engine::HELD && $temporal === Temporal::EXPIRED) {
                $state = 'expired';
            }
            $held = in_array($state, ['held', 'expiring', 'renew', 'expired'], true);
            // Masquée par la communauté (fiche publique coupée) : seuls l'encadrement et les titulaires la voient ici.
            if ($settings !== null && (int) ($settings['show_on_profile'] ?? 1) === 0 && !$staff && !$held) {
                continue;
            }
            $modes = Engine::modes($settings, !empty($src['pilot'][$defId]));
            $how = [];
            $rule = $settings !== null && !empty($settings['auto_enabled']) ? Engine::normalize($settings['rule_json'] ?? null) : [];
            $evalItems = [];
            foreach ($ev['eval']['groups'] ?? [] as $g) {
                foreach ($g['items'] ?? [] as $ci) {
                    $evalItems[] = $ci;
                }
            }
            foreach (Engine::criteria($rule) as $i => $crit) {
                $type = (string) ($crit['type'] ?? 'metric');
                $measured = $evalItems[$i] ?? null;
                if ($type === 'academy') {
                    $cid = (int) ($crit['course_id'] ?? 0);
                    $how[] = ['type' => 'course', 'label' => (string) ($names['courses'][$cid] ?? ''), 'url' => isset($slugById[$cid]) ? url('academy/formations/' . rawurlencode($slugById[$cid])) : '', 'met' => (bool) ($measured['met'] ?? false)];
                } elseif ($type === 'qualification') {
                    $qid = (int) ($crit['definition_id'] ?? 0);
                    $how[] = ['type' => 'qualification', 'label' => (string) ($names['definitions'][$qid] ?? ''), 'detail' => isset($definitions[$qid]) ? 'q-' . $qid : '', 'met' => (bool) ($measured['met'] ?? false)];
                } else {
                    $how[] = ['type' => 'metric', 'criterion' => $crit, 'met' => (bool) ($measured['met'] ?? false), 'current' => $measured['current'] ?? null, 'target' => (float) ($crit['min'] ?? 0), 'pct' => (int) ($measured['pct'] ?? 0)];
                }
            }
            if (in_array(Cat::MODE_PILOT, $modes, true)) {
                $how[] = ['type' => 'pilot'];
            }
            $issuerId = (int) ($d['default_issuer_id'] ?? 0);
            $issuerName = isset($issuers[$issuerId]) ? (string) $issuers[$issuerId]['name'] : '';
            if (in_array(Cat::MODE_MANUAL, $modes, true)) {
                $how[] = ['type' => 'manual', 'issuer' => $issuerName];
            }
            $prereqNames = [];
            foreach ($src['prereqs'][$defId] ?? [] as $req) {
                if (isset($definitions[$req])) {
                    $prereqNames[] = ['label' => (string) $definitions[$req]['name'], 'detail' => 'q-' . $req];
                }
            }
            if ($issuerId > 0 && isset($issuers[$issuerId])) {
                $issuerCounts[$issuerId] = ($issuerCounts[$issuerId] ?? 0) + 1;
            }
            $months = (int) ($d['default_validity_months'] ?? 0);
            $days = (int) ($d['validity_days'] ?? 0);
            $validity = !empty($d['is_permanent']) || ($months === 0 && $days === 0) ? ['permanent'] : ($months > 0 ? ['months' => $months] : ['days' => $days]);
            $brevet = self::isBrevet($d);
            $tabs = $brevet ? ['brevets', 'qualifications'] : ['qualifications'];
            $code = strtoupper(trim((string) ($d['code'] ?? '')));
            $ref = $code !== '' ? ($refByCode[$code] ?? null) : null;
            if ($code !== '') {
                $tenantCodes[$code] = true;
            }
            $category = trim((string) ($d['category_name'] ?? $d['category'] ?? ''));
            $items[] = [
                'id' => 'q-' . $defId,
                'origin' => $ref !== null ? 'referentiel' : 'communaute',
                'nature' => self::nature((string) ($d['name'] ?? '') . ' ' . (string) ($ref['name'] ?? '') . ' ' . (string) ($ref['official_name'] ?? ''), $brevet, (string) ($ref['category'] ?? ''), (string) ($d['type_name'] ?? '')),
                'ref_id' => $ref !== null ? (string) $ref['id'] : '',
                'country' => $ref !== null ? (string) $ref['country'] : '',
                'country_label' => $ref !== null ? (string) $ref['country_label'] : '',
                'official_name' => $ref !== null ? (string) $ref['official_name'] : '',
                'code' => $code,
                'kind' => 'qualification',
                'tabs' => $tabs,
                'title' => (string) ($d['name'] ?? ''),
                'short' => (string) ($d['short_name'] ?? ''),
                'summary' => trim((string) ($d['description'] ?? '')),
                'badge' => (string) ($d['badge_url'] ?? ''),
                'symbol' => $brevet ? 'brevet' : 'qualification',
                'issuer' => $issuerName,
                'issuer_id' => $issuerId,
                // Importée du catalogue : thème du catalogue (la catégorie créée à l'import porte aussi le pays).
                'category' => $ref !== null ? (string) $ref['category_label'] : $category,
                'type_name' => trim((string) ($d['type_name'] ?? '')),
                'level' => !empty($d['uses_levels']) && (int) ($d['levels_count'] ?? 0) > 0 ? 'levels:' . (int) $d['levels_count'] : '',
                'duration_min' => null,
                'validity' => $validity,
                'modes' => $modes,
                'how' => $how,
                'prereqs' => $prereqNames,
                'source' => 'community',
                'state' => $state,
                'pct' => $ev !== null && $rule !== [] ? (int) ($ev['pct'] ?? 0) : null,
                'next' => null,
                'missing' => $state === 'missing' ? array_values(array_map('strval', $ev['prereq']['missing'] ?? [])) : [],
                'award' => $held || $state === 'pending' ? $award : null,
                'award_issuer' => is_array($award) && isset($issuers[(int) ($award['issuer_id'] ?? 0)]) ? (string) $issuers[(int) $award['issuer_id']]['name'] : $issuerName,
                'requires_exam' => !empty($d['requires_exam']),
                // Toutes les conditions automatiques, ou au moins une (règle à un seul bloc ou blocs « au moins un »).
                'rule_any' => $rule !== [] && count(Engine::criteria($rule)) > 1 && self::ruleIsAny($rule),
            ];
        }

        // Catalogue de référence : qualifications réelles par pays et OTAN, pas encore dans le référentiel.
        foreach ($src['reference'] ?? [] as $row) {
            $code = strtoupper((string) ($row['code'] ?? ''));
            if ($code === '' || isset($tenantCodes[$code])) {
                continue;
            }
            $nature = self::nature((string) $row['name'] . ' ' . (string) $row['official_name'], false, (string) $row['category']);
            $months = $row['validity_months'] ?? null;
            $items[] = [
                'id' => 'r-' . $row['id'],
                'kind' => 'reference',
                'origin' => 'catalogue',
                'nature' => $nature,
                'ref_id' => (string) $row['id'],
                'tabs' => in_array($nature, ['brevet', 'licence'], true) ? ['brevets', 'qualifications'] : ['qualifications'],
                'title' => (string) $row['name'],
                'short' => (string) $row['short_name'],
                'summary' => (string) $row['description'],
                'badge' => '',
                'symbol' => in_array($nature, ['brevet', 'licence'], true) ? 'brevet' : 'qualification',
                'issuer' => (string) $row['issuer'],
                'issuer_id' => 0,
                'issuer_key' => (string) ($row['issuer_key'] ?? ''),
                'category' => (string) $row['category_label'],
                'level' => 'ref:' . (string) ($row['level']['key'] ?? 'initial'),
                'level_estimated' => !empty($row['level']['estimated']),
                'level_rank' => (int) ($row['level']['rank'] ?? 0),
                'country' => (string) $row['country'],
                'country_label' => (string) $row['country_label'],
                'official_name' => (string) $row['official_name'],
                'code' => $code,
                'duration_min' => null,
                'validity' => is_int($months) && $months > 0 ? ['months' => $months] : [],
                'modes' => [],
                'how' => [],
                'source' => 'reference',
                'state' => 'none',
                'pct' => null,
                'next' => null,
                'missing' => [],
                'award' => null,
                'jobs_count' => (int) ($row['jobs_count'] ?? 0),
                'academy' => $row['academy'] ?? null,
            ];
        }

        // Parcours de compétence enregistrés.
        $heldRefs = self::heldRefs($items);
        foreach ($src['pathways'] ?? [] as $p) {
            $steps = [];
            $done = 0;
            $linked = 0;
            $duration = 0;
            foreach ($p['steps'] ?? [] as $s) {
                $refType = (string) ($s['ref_type'] ?? '');
                $ref = (string) ($s['ref'] ?? '');
                $isDone = $ref !== '' && isset($heldRefs[$refType . ':' . $ref]);
                if ($refType !== '' && $ref !== '') {
                    $linked++;
                    $done += $isDone ? 1 : 0;
                }
                $duration += (int) ($s['duration_min'] ?? 0);
                $steps[] = ['title' => (string) ($s['title'] ?? ''), 'text' => (string) ($s['detail'] ?? ''), 'kind' => (string) ($s['kind'] ?? ''), 'ref_type' => $refType, 'url' => (string) ($s['url'] ?? ''), 'done' => $isDone, 'detail' => $refType === 'qualification' && isset($definitions[(int) $ref]) ? 'q-' . (int) $ref : ''];
            }
            $pct = $linked > 0 ? (int) floor($done / $linked * 100) : null;
            $items[] = [
                'id' => 'p-' . (int) ($p['id'] ?? 0),
                'kind' => 'pathway',
                'tabs' => ['parcours'],
                'title' => (string) ($p['name'] ?? ''),
                'summary' => trim((string) ($p['summary'] ?? '')),
                'badge' => '',
                'symbol' => 'pathway',
                'issuer' => $tenantName,
                'issuer_id' => 0,
                'category' => '',
                'level' => '',
                'duration_min' => $duration ?: null,
                'validity' => [],
                'modes' => [],
                'how' => [],
                'steps' => $steps,
                'source' => 'community',
                'state' => $pct === null ? 'available' : ($pct >= 100 ? 'held' : ($pct > 0 ? 'progress' : 'available')),
                'pct' => $pct,
                'next' => null,
                'missing' => [],
                'url' => url('academy/formations/competences') . '#parcours-' . (int) ($p['id'] ?? 0),
                'award' => null,
            ];
        }

        // Compétences du référentiel, avec ce qui permet de les acquérir.
        $catalog = $src['catalog'] ?? [];
        foreach ($src['competencies'] ?? [] as $c) {
            $links = [];
            $resolved = $catalog !== [] ? CompetencyPathwayGenerator::resolveLinks($c, $catalog) : [];
            $linkedDone = 0;
            $linkedTotal = 0;
            foreach ($resolved as $type => $list) {
                foreach ($list as $l) {
                    $ref = (string) ($l['ref'] ?? '');
                    $isDone = isset($heldRefs[$type . ':' . $ref]);
                    if (in_array($type, ['academy_course', 'training_course', 'qualification'], true)) {
                        $linkedTotal++;
                        $linkedDone += $isDone ? 1 : 0;
                    }
                    $links[] = ['type' => (string) $type, 'title' => (string) ($l['title'] ?? $l['label'] ?? ''), 'url' => (string) ($l['url'] ?? ''), 'done' => $isDone, 'detail' => $type === 'qualification' && isset($definitions[(int) $ref]) ? 'q-' . (int) $ref : ''];
                }
            }
            $items[] = [
                'id' => 'c-' . (int) ($c['id'] ?? 0),
                'kind' => 'competency',
                'tabs' => ['competences'],
                'title' => (string) ($c['label'] ?? ''),
                'summary' => (string) ($c['description'] ?? ''),
                'badge' => '',
                'symbol' => 'competency',
                'issuer' => $tenantName,
                'issuer_id' => 0,
                'category' => (string) ($c['palier'] ?? ''),
                'level' => (string) ($c['acquisition_level'] ?? ''),
                'grade' => (string) ($c['grade_label'] ?? ''),
                'duration_min' => null,
                'validity' => [],
                'modes' => [],
                'how' => [],
                'links' => $links,
                'source' => 'community',
                'state' => $linkedTotal > 0 && $linkedDone === $linkedTotal ? 'held' : ($linkedDone > 0 ? 'progress' : 'none'),
                'pct' => $linkedTotal > 0 ? (int) floor($linkedDone / $linkedTotal * 100) : null,
                'next' => null,
                'missing' => [],
                'generator' => url('academy/formations/competences') . '?cible=' . rawurlencode('competency:' . (int) ($c['id'] ?? 0)) . '#generateur',
                'award' => null,
            ];
        }

        // Émetteurs (écoles, centres, unités) qui délivrent des qualifications visibles ici.
        foreach ($issuers as $iid => $i) {
            $items[] = [
                'id' => 'e-' . $iid,
                'kind' => 'issuer',
                'tabs' => ['emetteurs'],
                'title' => (string) ($i['name'] ?? ''),
                'short' => (string) ($i['short_name'] ?? ''),
                'summary' => trim((string) ($i['description'] ?? '')),
                'badge' => trim((string) ($i['logo_media_path'] ?? '')) !== '' ? url('storage/uploads/' . ltrim((string) $i['logo_media_path'], '/')) : '',
                'symbol' => 'issuer',
                'issuer' => (string) ($i['parent_name'] ?? ''),
                'issuer_id' => $iid,
                'category' => (string) ($i['issuer_kind'] ?? ''),
                'level' => '',
                'unit' => (string) ($i['unit_name'] ?? ''),
                'duration_min' => null,
                'validity' => [],
                'modes' => [],
                'how' => [],
                'count' => $issuerCounts[$iid] ?? 0,
                'source' => 'community',
                'state' => 'none',
                'pct' => null,
                'next' => null,
                'missing' => [],
                'award' => null,
            ];
        }

        foreach ($items as $k => $it) {
            $hay = implode(' ', array_filter([$it['title'], $it['short'] ?? '', $it['summary'], $it['issuer'], $it['category'], $it['type_name'] ?? '', $it['official_name'] ?? '', $it['code'] ?? '', $it['country_label'] ?? ''], 'is_string'));
            $items[$k]['search'] = self::norm($hay . ' ' . JobCatalog::transliterate($hay));
        }
        $totals = array_fill_keys(self::TABS, 0);
        $references = 0;
        foreach ($items as $it) {
            foreach ($it['tabs'] as $tab) {
                $totals[$tab]++;
            }
            $references += $it['kind'] === 'reference' ? 1 : 0;
        }
        // « Tout » : ce que propose la communauté ; le catalogue de référence se parcourt dans ses onglets.
        $totals['tout'] = count($items) - $references;

        return ['items' => $items, 'totals' => $totals, 'names' => $names, 'references' => $references, 'reference_loaded' => ($src['reference'] ?? []) !== []];
    }

    /**
     * Le catalogue de référence (lecture et rapprochements coûteux) n’est chargé que là où il s’affiche : onglets
     * Qualifications et Brevets, recherche, fiche détaillée.
     *
     * @param array<string, mixed> $query
     */
    public static function needsReference(array $query): bool
    {
        $f = self::filters($query);

        return in_array($f['onglet'], ['qualifications', 'brevets'], true) || $f['q'] !== '' || (is_string($query['fiche'] ?? null) && $query['fiche'] !== '');
    }

    /** Texte comparable : minuscules, sans accents ni ponctuation, espaces autour. */
    public static function norm(string $text): string
    {
        return ' ' . trim((string) preg_replace('/\s+/u', ' ', JobCatalog::normalize($text))) . ' ';
    }

    /**
     * Nature d'une qualification : brevet, licence, cursus ou sélection, ou qualification (d'après son nom, son type et
     * le thème du catalogue).
     */
    public static function nature(string $name, bool $brevet = false, string $refCategory = '', string $typeName = ''): string
    {
        $hay = mb_strtolower($name . ' ' . $typeName, 'UTF-8');
        if (preg_match('/licen[cs]e|lizenz|type rating|\brating\b|qualification de type|лицензи/u', $hay) === 1) {
            return 'licence';
        }
        if ($brevet || preg_match('/brevet|badge|insigne|insignia|\btab\b|wings|abzeichen|tätigkeitsabzeichen|знак|章/u', $hay) === 1) {
            return 'brevet';
        }
        if ($refCategory === 'selection' || preg_match('/sélection|selection|cursus|pipeline|qualification course|auswahl|отбор|选拔/u', $hay) === 1) {
            return 'cursus';
        }

        return 'qualification';
    }

    /** @param array<string, mixed> $rule */
    private static function ruleIsAny(array $rule): bool
    {
        $groups = $rule['groups'] ?? [];
        if (count($groups) === 1) {
            return ($groups[0]['op'] ?? 'all') === 'any';
        }

        return ($rule['op'] ?? 'all') === 'any';
    }

    /** Qualification qui donne un brevet ou une licence : type, nom ou gabarit de brevet choisi. @param array<string, mixed> $d */
    public static function isBrevet(array $d): bool
    {
        $hay = mb_strtolower(((string) ($d['type_name'] ?? '')) . ' ' . ((string) ($d['type_code'] ?? '')), 'UTF-8');
        if (preg_match('/brevet|licen[cs]e|certificat/u', $hay) === 1 || (int) ($d['certificate_template_id'] ?? 0) > 0) {
            return true;
        }

        return preg_match('/^\s*(brevet|licen[cs]e)\b/iu', (string) ($d['name'] ?? '')) === 1;
    }

    /**
     * Ce que le membre a déjà (pour l'avancement des parcours et compétences) : « type:réf » => true.
     *
     * @param list<array<string, mixed>> $items
     * @return array<string, true>
     */
    private static function heldRefs(array $items): array
    {
        $out = [];
        foreach ($items as $it) {
            if (!in_array($it['state'], ['held', 'expiring', 'renew'], true)) {
                continue;
            }
            $id = (string) $it['id'];
            if (str_starts_with($id, 'q-')) {
                $out['qualification:' . substr($id, 2)] = true;
            } elseif (str_starts_with($id, 'a-')) {
                $out['academy_course:' . substr($id, 2)] = true;
            } elseif (str_starts_with($id, 'n-')) {
                $out['training_course:' . substr($id, 2)] = true;
            }
        }

        return $out;
    }

    /**
     * Filtres de la page (lus dans l'adresse) ramenés à des valeurs connues.
     *
     * @param array<string, mixed> $query
     * @return array{onglet: string, q: string, categorie: string, emetteur: string, niveau: string, mode: string, etat: string, source: string, tri: string, pays: string, nature: string, origine: string, page: string}
     */
    public static function filters(array $query): array
    {
        $s = static fn (string $k, int $max = 80): string => mb_substr(trim(is_string($query[$k] ?? null) ? $query[$k] : ''), 0, $max);
        $tab = $s('onglet');

        return [
            'onglet' => in_array($tab, self::TABS, true) ? $tab : 'tout',
            'q' => $s('q', 120),
            'categorie' => $s('categorie'),
            'emetteur' => $s('emetteur'),
            'niveau' => $s('niveau'),
            'mode' => in_array($s('mode'), ['course', Cat::MODE_MANUAL, Cat::MODE_ACADEMY, Cat::MODE_AUTO, Cat::MODE_PILOT], true) ? $s('mode') : '',
            'etat' => array_key_exists($s('etat'), self::STATE_GROUPS) ? $s('etat') : '',
            'source' => in_array($s('source'), ['athena', 'community', 'reference'], true) ? $s('source') : '',
            'tri' => in_array($s('tri'), self::SORTS, true) ? $s('tri') : 'pertinence',
            'pays' => preg_match('/^[A-Z]{2,5}$/', strtoupper($s('pays', 10))) === 1 ? strtoupper($s('pays', 10)) : '',
            'nature' => in_array($s('nature'), self::NATURES, true) ? $s('nature') : '',
            'origine' => in_array($s('origine'), self::ORIGINS, true) ? $s('origine') : '',
            'page' => ctype_digit($s('page', 4)) && (int) $s('page', 4) > 1 ? (string) (int) $s('page', 4) : '',
        ];
    }

    /**
     * Éléments d'un onglet après recherche, filtres et tri. La recherche seule sert aussi aux compteurs d'onglets.
     *
     * @param list<array<string, mixed>> $items
     * @param array<string, string> $f
     * @return list<array<string, mixed>>
     */
    public static function filter(array $items, array $f, bool $searchOnly = false): array
    {
        $words = array_values(array_filter(explode(' ', trim(self::norm((string) ($f['q'] ?? '')))), static fn (string $w): bool => $w !== ''));
        $out = [];
        foreach ($items as $it) {
            foreach ($words as $w) {
                if (!str_contains((string) $it['search'], $w)) {
                    continue 2;
                }
            }
            if (!$searchOnly) {
                $tab = $f['onglet'] ?? 'tout';
                if ($tab !== 'tout' && !in_array($tab, $it['tabs'], true)) {
                    continue;
                }
                if ($tab === 'tout' && $it['kind'] === 'reference') {
                    continue;
                }
                if (($f['pays'] ?? '') !== '' && ($it['country'] ?? '') !== $f['pays']) {
                    continue;
                }
                if (($f['nature'] ?? '') !== '' && ($it['nature'] ?? '') !== $f['nature']) {
                    continue;
                }
                if (($f['origine'] ?? '') !== '' && ($it['origin'] ?? '') !== $f['origine']) {
                    continue;
                }
                if (($f['categorie'] ?? '') !== '' && $it['category'] !== $f['categorie']) {
                    continue;
                }
                if (($f['emetteur'] ?? '') !== '' && self::issuerKey($it) !== $f['emetteur'] && !($it['kind'] !== 'issuer' && (string) (int) ($it['issuer_id'] ?? 0) === $f['emetteur'])) {
                    continue;
                }
                if (($f['niveau'] ?? '') !== '' && $it['level'] !== $f['niveau']) {
                    continue;
                }
                if (($f['mode'] ?? '') !== '' && !in_array($f['mode'], $it['modes'], true)) {
                    continue;
                }
                if (($f['etat'] ?? '') !== '' && !in_array($it['state'], self::STATE_GROUPS[$f['etat']] ?? [], true)) {
                    continue;
                }
                if (($f['source'] ?? '') !== '' && $it['source'] !== $f['source']) {
                    continue;
                }
            }
            $out[] = $it;
        }
        $sort = $f['tri'] ?? 'pertinence';
        $name = static fn (array $a, array $b): int => strnatcasecmp((string) $a['title'], (string) $b['title']);
        usort($out, match ($sort) {
            'nom' => $name,
            'etat' => static fn (array $a, array $b): int => [self::STATE_ORDER[$a['state']] ?? 9, $a['title']] <=> [self::STATE_ORDER[$b['state']] ?? 9, $b['title']],
            'duree' => static fn (array $a, array $b): int => [($a['duration_min'] ?? PHP_INT_MAX), $a['title']] <=> [($b['duration_min'] ?? PHP_INT_MAX), $b['title']],
            'pays' => static fn (array $a, array $b): int => [self::countryRank($a), self::relevance($a), $a['title']] <=> [self::countryRank($b), self::relevance($b), $b['title']],
            'niveau' => static fn (array $a, array $b): int => [-self::levelRank($a), $a['title']] <=> [-self::levelRank($b), $b['title']],
            // Pertinence : ce qui est en cours ou à renouveler d'abord, puis ce qui est disponible, puis le reste.
            default => static fn (array $a, array $b): int => [self::relevance($a), $a['title']] <=> [self::relevance($b), $b['title']],
        });

        return $out;
    }

    /**
     * Clé d'émetteur d'un élément pour le filtre : son nom (le même émetteur du catalogue et du référentiel se filtre
     * ensemble) ; l'identifiant d'un émetteur du référentiel (ancien lien « emetteur=12 ») reste accepté par filter().
     *
     * @param array<string, mixed> $it
     */
    public static function issuerKey(array $it): string
    {
        return $it['kind'] === 'issuer' ? '' : (string) $it['issuer'];
    }

    /** @param array<string, mixed> $it */
    private static function countryRank(array $it): int
    {
        static $order = null;
        $order ??= array_flip(array_keys(\App\Support\QualificationCatalog\QualificationCatalog::COUNTRIES));

        return ($it['country'] ?? '') === '' ? -1 : ($order[$it['country']] ?? 50);
    }

    /** @param array<string, mixed> $it */
    private static function levelRank(array $it): int
    {
        if (isset($it['level_rank'])) {
            return (int) $it['level_rank'];
        }
        $level = (string) ($it['level'] ?? '');

        return str_starts_with($level, 'levels:') ? (int) substr($level, 7) : (['initiation' => 1, 'intermediaire' => 2, 'avance' => 3, 'expert' => 5][$level] ?? 0);
    }

    /** @param array<string, mixed> $it */
    private static function relevance(array $it): int
    {
        return match ($it['state']) {
            'renew', 'expiring' => 0,
            'progress', 'eligible', 'pending' => 1,
            'available' => 2,
            'none' => 3,
            'missing', 'blocked' => 4,
            default => $it['kind'] === 'reference' ? 6 : 5,
        };
    }

    /**
     * Valeurs proposées par chaque filtre, d'après les éléments de l'onglet.
     *
     * @param list<array<string, mixed>> $items
     * @return array{categories: list<string>, issuers: array<string, string>, levels: list<string>, modes: list<string>, sources: list<string>, countries: array<string, string>, natures: list<string>, origins: array<string, int>}
     */
    public static function facets(array $items): array
    {
        $cats = $issuers = $levels = $modes = $sources = $countries = $natures = [];
        $origins = array_fill_keys(self::ORIGINS, 0);
        foreach ($items as $it) {
            if (($it['country'] ?? '') !== '') {
                $countries[$it['country']] = (string) ($it['country_label'] ?? $it['country']);
            }
            if (($it['nature'] ?? '') !== '') {
                $natures[$it['nature']] = true;
            }
            if (isset($it['origin'])) {
                $origins[$it['origin']]++;
            }
            if ($it['category'] !== '' && $it['kind'] !== 'issuer') {
                $cats[$it['category']] = true;
            }
            $key = self::issuerKey($it);
            if ($key !== '' && $it['issuer'] !== '') {
                $issuers[$key] = (string) $it['issuer'];
            }
            if ($it['level'] !== '') {
                $levels[$it['level']] = true;
            }
            foreach ($it['modes'] as $m) {
                $modes[$m] = true;
            }
            $sources[$it['source']] = true;
        }
        $cats = array_keys($cats);
        natcasesort($cats);
        asort($issuers, SORT_NATURAL | SORT_FLAG_CASE);

        $order = array_flip(array_keys(\App\Support\QualificationCatalog\QualificationCatalog::COUNTRIES));
        uksort($countries, static fn (string $a, string $b): int => ($order[$a] ?? 50) <=> ($order[$b] ?? 50) ?: strcmp($a, $b));
        $levelKeys = array_keys($levels);
        usort($levelKeys, static fn (string $a, string $b): int => self::levelRank(['level' => $a] + (str_starts_with($a, 'ref:') ? ['level_rank' => QualificationCatalogExplorer::LEVELS[substr($a, 4)]['rank'] ?? 0] : [])) <=> self::levelRank(['level' => $b] + (str_starts_with($b, 'ref:') ? ['level_rank' => QualificationCatalogExplorer::LEVELS[substr($b, 4)]['rank'] ?? 0] : [])) ?: strcmp($a, $b));

        return ['categories' => array_values($cats), 'issuers' => $issuers, 'levels' => $levelKeys, 'modes' => array_keys($modes), 'sources' => array_keys($sources),
            'countries' => $countries, 'natures' => array_values(array_filter(self::NATURES, static fn (string $n): bool => isset($natures[$n]))), 'origins' => $origins];
    }

    /** @param list<array<string, mixed>> $items */
    public static function find(array $items, string $id): ?array
    {
        foreach ($items as $it) {
            if ($it['id'] === $id) {
                return $it;
            }
        }

        return null;
    }

    /**
     * @template T
     * @param callable(): T $fn
     * @return T|null
     */
    private static function safe(callable $fn): mixed
    {
        try {
            return $fn();
        } catch (Throwable) {
            return null;
        }
    }
}
