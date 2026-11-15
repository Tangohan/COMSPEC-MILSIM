<?php

declare(strict_types=1);

namespace App\Services\Sse\Attach;

use App\Core\Database;
use App\Repositories\SseAttachmentRepository;
use App\Repositories\SseAuditLogRepository;
use App\Repositories\SseEntityLinkRepository;
use App\Services\Sse\Links\SseLinkViewer;
use App\Services\Sse\SseClearanceService;
use App\Support\SseAttachCatalog as A;
use App\Support\SseCerbere;
use App\Support\SseLinkCatalog as C;

/**
 * Rattachement universel du bureau SSE : rattacher une ou plusieurs pièces à une ou plusieurs cibles, modifier, détacher,
 * déplacer, créer une cible à la volée, et aligner la confidentialité.
 *
 * Un rattachement EST un lien de sse_entity_links (le widget « Liaisons » le montre tel quel), enrichi dans
 * sse_link_attachments (nature en clair, libellé, étiquettes, note, alignement). La colonne historique est écrite en plus
 * (SseAttachLegacy).
 *
 * Confidentialité, côté serveur uniquement :
 *  - l'alignement ne peut que RELEVER la classification de la pièce vers celle de la cible, jamais au-dessus de
 *    l'habilitation de l'opérateur ;
 *  - une cible moins classifiée n'abaisse jamais la pièce : la pièce garde son niveau et reste « Élément restreint » pour
 *    les lecteurs de la cible sans l'habilitation ;
 *  - une pièce ou une cible que l'opérateur ne lit pas répond « introuvable », comme si elle n'existait pas ;
 *  - tout changement de niveau est écrit dans sse_audit_log avec l'ancien et le nouveau niveau.
 */
final class SseAttachService
{
    public const DENY_GUEST = 'Une session invitée ne peut pas rattacher : connectez-vous avec votre compte.';
    public const DENY_RIGHT = 'Droit manquant : écrire dans la section de cette pièce.';
    public const NOT_FOUND = 'Élément introuvable.';
    public const NOT_READY = 'Disponible après la mise à jour de la base (étape sse_attachments) : seul le rattachement à un dossier fonctionne pour l’instant.';
    public const REASON_REQUIRED = 'Le motif est obligatoire (5 caractères au moins) : dites pourquoi vous rattachez.';

    private Database $db;

    private SseAttachDirectory $dir;

    private SseAttachLegacy $legacy;

    public function __construct(private readonly SseLinkViewer $viewer, ?SseAttachDirectory $dir = null, ?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
        $this->dir = $dir ?? new SseAttachDirectory($viewer, null, $db);
        $this->legacy = new SseAttachLegacy($this->db);
    }

    public function directory(): SseAttachDirectory
    {
        return $this->dir;
    }

    public static function ready(): bool
    {
        return SseAttachmentRepository::ready() && SseEntityLinkRepository::ready();
    }

    private function repo(): SseAttachmentRepository
    {
        return new SseAttachmentRepository($this->db);
    }

    private function links(): SseEntityLinkRepository
    {
        return new SseEntityLinkRepository($this->db);
    }

    /** Droit « gérer » d'une section (créer une étiquette, appliquer une proposition d'alignement sur la pièce d'un autre). */
    public function canManage(string $section): bool
    {
        $ask = [$this->viewer, 'canManage'];

        return !$this->viewer->isGuest() && is_callable($ask) && (bool) $ask($section);
    }

    /** Réglage de la communauté : toujours aligner à la hausse au rattachement (désactivé par défaut). */
    public function alwaysAlign(): bool
    {
        try {
            $row = $this->db->fetchOne('SELECT setting_value FROM sse_portal_settings WHERE tenant_id = :t AND setting_key = :k', ['t' => $this->viewer->tenantId(), 'k' => A::SETTING_ALWAYS_ALIGN]);
        } catch (\Throwable) {
            return false;
        }

        return (string) ($row['setting_value'] ?? '') === '1';
    }

    // ------------------------------------------------------------------ Contexte de la fenêtre

    /**
     * Ce que la fenêtre doit savoir à l'ouverture : les pièces, les périmètres, les natures, les étiquettes, les droits.
     *
     * @param list<string> $refs
     * @return array<string, mixed>
     */
    public function context(array $refs): array
    {
        $sources = $this->dir->sources($refs);
        $scopes = A::commonTargets(array_column($sources, 'type'));
        $ready = self::ready();
        $natures = [];
        foreach ($scopes as $s) {
            $natures[$s] = A::naturesFor($s);
        }
        $writable = $sources !== [] && !$this->viewer->isGuest();
        foreach ($sources as $s) {
            $writable = $writable && !empty($s['can_write']);
        }
        $tags = [];
        if ($ready) {
            try {
                $tags = $this->repo()->tags($this->viewer->tenantId());
            } catch (\Throwable) {
            }
        }
        $level = $this->dir->viewerLevel();

        return [
            'found' => $sources !== [], 'requested' => count(array_unique($refs)), 'sources' => $sources, 'scopes' => $scopes,
            'natures' => $natures, 'nature_labels' => A::NATURES, 'templates' => A::REASON_TEMPLATES, 'tags' => $tags, 'tag_colors' => A::TAG_COLORS,
            'ready' => $ready, 'guest' => $this->viewer->isGuest(), 'can_attach' => $writable,
            'deny' => $this->viewer->isGuest() ? self::DENY_GUEST : ($writable ? '' : self::DENY_RIGHT),
            'can_tag' => $ready && $writable, 'can_create' => $ready && $this->viewer->canWrite('dossiers') ? A::CREATABLE : [],
            'always_align' => $this->alwaysAlign(), 'viewer_level' => $level, 'viewer_level_abbr' => A::levelAbbr($level),
            'levels' => array_map(static fn (string $l): array => ['value' => $l, 'abbr' => A::levelAbbr($l), 'label' => A::levelLabel($l)], array_values(array_filter(A::LEVELS, fn (string $l): bool => $this->viewer->clearanceAllows($l)))),
            'undo_seconds' => A::UNDO_SECONDS, 'lead_statuses' => A::LEAD_STATUSES,
        ];
    }

    // ------------------------------------------------------------------ Aperçu et rattachement

    /**
     * Récapitulatif avant validation : ce qui sera rattaché à quoi et avec quels effets. N'écrit rien.
     *
     * @param list<string> $refs pièces
     * @param list<string> $targetRefs cibles
     * @param array<string, mixed> $settings nature, nature_label, label, reason, note, tags, align, per
     * @return array<string, mixed>
     */
    public function preview(array $refs, array $targetRefs, array $settings): array
    {
        $plan = $this->plan($refs, $targetRefs, $settings);
        if (isset($plan['error'])) {
            return ['ok' => false, 'error' => $plan['error']];
        }
        $effects = [];
        foreach ($plan['raise'] as $key => $r) {
            $effects[] = [
                'kind' => 'raise', 'source' => $plan['sources'][$key]['ref'], 'from' => A::levelAbbr($r['from']), 'to' => A::levelAbbr($r['to']),
                'from_label' => A::levelLabel($r['from']), 'to_label' => A::levelLabel($r['to']),
                'lose' => $r['from'] === null ? 'Lecteurs de la section sans habilitation ' . A::levelAbbr($r['to']) : SseClearanceService::whoCanOpen((string) $r['from']),
                'keep' => SseClearanceService::whoCanOpen($r['to']),
            ];
        }

        return ['ok' => true, 'error' => null, 'rows' => array_map(static fn (array $p): array => [
            'source' => $p['source']['ref'], 'source_title' => $p['source']['title'], 'target' => $p['target']['ref'], 'target_title' => $p['target']['title'],
            'target_type' => $p['target']['type_label'], 'target_scope' => $p['target']['type'], 'nature' => A::natureLabel($p['nature'], $p['nature_label']),
            'nature_key' => $p['nature_label'] !== '' ? '' : $p['nature'], 'label' => $p['label'],
            'piece_level' => A::levelAbbr($p['source']['level']), 'target_level' => A::levelAbbr($p['target']['level'] ?? null),
            'state' => $p['state'], 'effect' => $p['effect'], 'message' => $p['message'], 'legacy' => $p['legacy'],
        ], $plan['pairs']), 'effects' => $effects, 'new' => count(array_filter($plan['pairs'], static fn (array $p): bool => $p['state'] !== 'already')),
            'degraded' => !$plan['ready']];
    }

    /**
     * @param list<string> $refs
     * @param list<string> $targetRefs
     * @param array<string, mixed> $settings
     * @return array<string, mixed> ok, error, created (identifiants de rattachement), count, aligned, skipped
     */
    public function attach(array $refs, array $targetRefs, array $settings): array
    {
        $plan = $this->plan($refs, $targetRefs, $settings);
        if (isset($plan['error'])) {
            return ['ok' => false, 'error' => $plan['error'], 'created' => [], 'count' => 0];
        }
        $tenant = $this->viewer->tenantId();
        $uid = (int) $this->viewer->userId();
        $ready = $plan['ready'];
        $tagIds = $ready ? $this->resolveTags((array) ($settings['tags'] ?? [])) : [];
        $created = [];
        $count = 0;
        $skipped = 0;
        $legacyOnly = [];
        foreach ($plan['pairs'] as $p) {
            if ($p['state'] === 'already') {
                ++$skipped;
                continue;
            }
            $s = $p['source'];
            $t = $p['target'];
            $column = $this->legacy->write($tenant, $s['type'], (int) $s['id'], $t['type'], (int) $t['id'], $uid, $plan['reason'], $p['label'] !== '' ? $p['label'] : $s['title']);
            if (!$ready) {
                $this->audit('attach.add', 0, $plan['reason'], null, ['from' => $s['key'], 'to' => $t['key'], 'legacy' => $column, 'degraded' => true]);
                $legacyOnly[] = ['source' => $s['key'], 'target' => $t['key']];
                ++$count;
                continue;
            }
            $links = $this->links();
            $link = $links->pair($tenant, $s['type'], $s['id'], $t['type'], $t['id']);
            $linkId = $link !== null ? (int) $link['id'] : $links->add($tenant, $s['type'], $s['id'], $t['type'], $t['id'], $p['nature'], $plan['reason'], SseCerbere::RULE_MANUAL, SseCerbere::ACTOR_USER, $uid);
            if ($linkId < 1) {
                ++$skipped;
                continue;
            }
            $raise = $plan['raise'][$s['key']] ?? null;
            $id = $this->repo()->create($tenant, [
                'link_id' => $linkId, 'source_type' => $s['type'], 'source_id' => $s['id'], 'target_type' => $t['type'], 'target_id' => $t['id'],
                'nature' => $p['nature'], 'nature_label' => $p['nature_label'], 'label' => $p['label'], 'reason' => $plan['reason'], 'note' => $plan['note'],
                'tags' => $tagIds, 'align_requested' => $plan['align'], 'align_from' => $raise !== null ? $raise['from'] : null,
                'align_to' => $raise !== null ? $raise['to'] : null, 'user_id' => $uid,
            ]);
            if ($id < 1) {
                ++$skipped;
                continue;
            }
            $created[] = $id;
            ++$count;
            $this->audit('attach.add', $id, $plan['reason'], null, ['from' => $s['key'], 'to' => $t['key'], 'nature' => $p['nature'], 'label' => $p['label'], 'tags' => $tagIds, 'align' => $plan['align'], 'legacy' => $column, 'existing_link' => $link !== null]);
        }
        $aligned = [];
        if ($ready && $count > 0) {
            foreach ($plan['raise'] as $key => $r) {
                $s = $plan['sources'][$key];
                if ($this->setLevel($s['type'], (int) $s['id'], $r['from'], $r['to'], 'attach.align', $plan['reason'])) {
                    $aligned[] = ['source' => $s['ref'], 'from' => A::levelAbbr($r['from']), 'to' => A::levelAbbr($r['to'])];
                }
            }
        }
        if ($count === 0) {
            return ['ok' => false, 'error' => $skipped > 0 ? 'Ces éléments sont déjà rattachés à ces cibles.' : 'Le rattachement n’a pas pu être enregistré.', 'created' => [], 'count' => 0];
        }

        return ['ok' => true, 'error' => null, 'created' => $created, 'count' => $count, 'skipped' => $skipped, 'aligned' => $aligned, 'legacy_only' => $legacyOnly, 'degraded' => !$ready,
            'targets' => array_values(array_unique(array_map(static fn (array $p): string => $p['target']['key'], $plan['pairs'])))];
    }

    /**
     * Contrôles communs à l'aperçu et au rattachement : droits, visibilité des deux bouts, matrice, motif, et calcul de
     * l'alignement. Rien n'est écrit.
     *
     * @param list<string> $refs
     * @param list<string> $targetRefs
     * @param array<string, mixed> $settings
     * @return array<string, mixed>
     */
    private function plan(array $refs, array $targetRefs, array $settings): array
    {
        if ($this->viewer->isGuest() || $this->viewer->userId() === null) {
            return ['error' => self::DENY_GUEST];
        }
        $refs = array_values(array_unique(array_map('strval', $refs)));
        $targetRefs = array_values(array_unique(array_map('strval', $targetRefs)));
        if ($refs === [] || $targetRefs === []) {
            return ['error' => 'Choisissez au moins une cible.'];
        }
        if (count($refs) > A::BATCH_SOURCES || count($targetRefs) > A::BATCH_TARGETS) {
            return ['error' => 'Trop d’éléments à la fois : ' . A::BATCH_SOURCES . ' pièces et ' . A::BATCH_TARGETS . ' cibles au plus.'];
        }
        $sources = $this->dir->sources($refs);
        // Une pièce illisible ou d'une autre communauté : même réponse qu'une pièce qui n'existe pas.
        if (count($sources) !== count($refs)) {
            return ['error' => self::NOT_FOUND];
        }
        foreach ($sources as $s) {
            if (empty($s['can_write'])) {
                return ['error' => self::DENY_RIGHT];
            }
        }
        $ready = self::ready();
        $targets = [];
        foreach ($targetRefs as $ref) {
            $p = C::parseRef($ref);
            $t = $p !== null ? $this->dir->detail($sources, $p['type'], $p['id']) : null;
            if ($t === null) {
                return ['error' => self::NOT_FOUND];
            }
            $targets[] = $t;
        }
        $reason = self::clean((string) ($settings['reason'] ?? ''), A::REASON_MAX);
        if (mb_strlen($reason) < A::REASON_MIN) {
            return ['error' => self::REASON_REQUIRED];
        }
        $align = !empty($settings['align']) || $this->alwaysAlign();
        $viewerRank = A::rank($this->dir->viewerLevel());
        $tenant = $this->viewer->tenantId();
        $per = is_array($settings['per'] ?? null) ? $settings['per'] : [];
        $commonNature = (string) ($settings['nature'] ?? '');
        $commonLabel = self::clean((string) ($settings['label'] ?? ''), A::LABEL_MAX);
        $pairs = [];
        $raise = [];
        $byKey = [];
        foreach ($sources as $s) {
            $byKey[$s['key']] = $s;
            foreach ($targets as $t) {
                if (!A::allows($s['type'], $t['type'])) {
                    return ['error' => 'Un élément « ' . $s['type_label'] . ' » ne se rattache pas à « ' . $t['type_label'] . ' ».'];
                }
                if (!$ready && !in_array([$s['type'], $t['type']], A::LEGACY_PAIRS, true)) {
                    return ['error' => self::NOT_READY];
                }
                // Le rattachement écrit aussi dans la cible (colonne ou table historique) : il faut pouvoir y écrire.
                $targetSection = (string) (C::TYPES[$t['type']]['section'] ?? '');
                if (SseAttachLegacy::handles($s['type'], $t['type']) && $targetSection !== '' && !$this->viewer->canWrite($targetSection)) {
                    return ['error' => 'Droit manquant : écrire dans la section de la cible (' . $t['type_label'] . ').'];
                }
                $own = is_array($per[$t['key']] ?? null) ? $per[$t['key']] : [];
                $nature = (string) ($own['nature'] ?? $commonNature);
                $allowed = A::naturesFor($t['type']);
                if (!in_array($nature, $allowed, true)) {
                    $nature = $allowed[0];
                }
                $state = 'new';
                if ($ready) {
                    $link = $this->links()->pair($tenant, $s['type'], $s['id'], $t['type'], $t['id']);
                    if ($link !== null) {
                        $state = $this->repo()->findByLink($tenant, (int) $link['id']) !== null ? 'already' : 'enrich';
                    }
                } elseif ($this->legacyHolds($s, $t)) {
                    $state = 'already';
                }
                [$effect, $message] = $this->effect($s, $t, $align, $viewerRank);
                if ($effect === 'raise' && $state !== 'already' && $ready) {
                    $to = (string) $t['level'];
                    if (!isset($raise[$s['key']]) || A::rank($raise[$s['key']]['to']) < A::rank($to)) {
                        $raise[$s['key']] = ['from' => $s['level'], 'to' => $to];
                    }
                }
                $pairs[] = [
                    'source' => $s, 'target' => $t, 'nature' => $nature,
                    'nature_label' => $nature === 'related' ? self::clean((string) ($own['nature_label'] ?? $settings['nature_label'] ?? ''), 80) : '',
                    'label' => count($sources) === 1 ? self::clean((string) ($own['label'] ?? $commonLabel), A::LABEL_MAX) : '',
                    'state' => $state, 'effect' => $effect, 'message' => $message,
                    'legacy' => SseAttachLegacy::handles($s['type'], $t['type']),
                ];
            }
        }

        return ['ready' => $ready, 'sources' => $byKey, 'targets' => $targets, 'pairs' => $pairs, 'raise' => $raise, 'reason' => $reason,
            'note' => self::clean((string) ($settings['note'] ?? ''), A::NOTE_MAX), 'align' => $align];
    }

    /**
     * Effet de confidentialité d'un rattachement, et la phrase qui le dit AVANT validation.
     *
     * @param array<string, mixed> $s pièce
     * @param array<string, mixed> $t cible
     * @return array{0: string, 1: string}
     */
    private function effect(array $s, array $t, bool $align, int $viewerRank): array
    {
        $pl = $s['level'];
        $tl = $t['level'] ?? null;
        $pr = A::rank($pl);
        $tr = A::rank($tl);
        if ($tl === null) {
            return ['none', ''];
        }
        if ($tr < $pr) {
            return ['target_lower', 'La cible (' . A::levelAbbr($tl) . ') est moins protégée que la pièce (' . A::levelAbbr($pl) . ') : la pièce garde son niveau et s’affichera « Élément restreint » pour les lecteurs de la cible sans l’habilitation ' . A::levelAbbr($pl) . '. Rien n’est abaissé.'];
        }
        if ($tr === $pr) {
            return ['same', ''];
        }
        if (empty($s['classifiable'])) {
            return ['unclassifiable', 'Ce type de pièce ne porte pas de classification : il suit les droits de sa section, quelle que soit la cible.'];
        }
        if (!$align) {
            return ['target_higher', 'La cible est ' . A::levelAbbr($tl) . ', la pièce reste ' . A::levelAbbr($pl) . ' : elle demeure lisible en dehors de la cible. Cochez l’alignement pour la relever.'];
        }
        if ($tr > $viewerRank) {
            return ['ceiling', 'L’alignement dépasserait votre habilitation : la pièce garde son niveau.'];
        }

        return ['raise', 'La pièce passe de ' . A::levelAbbr($pl) . ' à ' . A::levelAbbr($tl) . ' : les lecteurs sans l’habilitation ' . A::levelAbbr($tl) . ' ne la verront plus.'];
    }

    /** @param array<string, mixed> $s @param array<string, mixed> $t */
    private function legacyHolds(array $s, array $t): bool
    {
        if ($s['type'] === 'person') {
            try {
                return $this->db->fetchOne('SELECT id FROM sse_case_persons WHERE tenant_id = :t AND case_id = :c AND person_id = :p', ['t' => $this->viewer->tenantId(), 'c' => (int) $t['id'], 'p' => (int) $s['id']]) !== null;
            } catch (\Throwable) {
                return false;
            }
        }

        return $this->legacy->current($this->viewer->tenantId(), $s['type'], (int) $s['id'], $t['type']) === (int) $t['id'];
    }

    // ------------------------------------------------------------------ Modifier, détacher, déplacer, annuler

    /**
     * Le rattachement, s'il existe dans la communauté et si ses deux bouts sont lisibles ; sinon null (indiscernable).
     *
     * @return array<string, mixed>|null
     */
    private function visibleAttachment(int $id): ?array
    {
        if (!self::ready() || $id < 1) {
            return null;
        }
        $row = $this->repo()->find($this->viewer->tenantId(), $id);
        if ($row === null || (string) $row['status'] !== 'active') {
            return null;
        }
        $graph = $this->dir->graph();
        if ($graph->see((string) $row['source_type'], (string) $row['source_id']) === null || $graph->see((string) $row['target_type'], (string) $row['target_id']) === null) {
            return null;
        }

        return $row;
    }

    /** @param array<string, mixed> $row */
    private function mayEdit(array $row): bool
    {
        return (int) ($row['created_by_user_id'] ?? 0) === (int) $this->viewer->userId()
            || $this->viewer->canWrite(C::TYPES[(string) $row['source_type']]['section'] ?? 'fichiers');
    }

    /**
     * Modifie le libellé, les étiquettes, le motif, la note ou la nature d'un rattachement (la pièce ne change pas).
     *
     * @param array<string, mixed> $fields
     * @return array{ok: bool, error: ?string}
     */
    public function update(int $id, array $fields): array
    {
        $fail = static fn (string $m): array => ['ok' => false, 'error' => $m];
        if ($this->viewer->isGuest() || $this->viewer->userId() === null) {
            return $fail(self::DENY_GUEST);
        }
        if (!self::ready()) {
            return $fail(self::NOT_READY);
        }
        $row = $this->visibleAttachment($id);
        if ($row === null) {
            return $fail(self::NOT_FOUND);
        }
        if (!$this->mayEdit($row)) {
            return $fail(self::DENY_RIGHT);
        }
        $set = [];
        if (array_key_exists('reason', $fields)) {
            $set['reason'] = self::clean((string) $fields['reason'], A::REASON_MAX);
            if (mb_strlen($set['reason']) < A::REASON_MIN) {
                return $fail(self::REASON_REQUIRED);
            }
        }
        if (array_key_exists('label', $fields)) {
            $set['label'] = self::clean((string) $fields['label'], A::LABEL_MAX);
        }
        if (array_key_exists('note', $fields)) {
            $set['note'] = self::clean((string) $fields['note'], A::NOTE_MAX);
        }
        if (array_key_exists('nature', $fields) && in_array((string) $fields['nature'], A::naturesFor((string) $row['target_type']), true)) {
            $set['nature'] = (string) $fields['nature'];
            $set['nature_label'] = $set['nature'] === 'related' ? self::clean((string) ($fields['nature_label'] ?? ''), 80) : '';
        }
        if (array_key_exists('tags', $fields)) {
            $set['tags'] = $this->resolveTags((array) $fields['tags']);
        }
        if ($set === [] || !$this->repo()->update($this->viewer->tenantId(), $id, $set, (int) $this->viewer->userId())) {
            return $fail('Rien à modifier.');
        }
        $this->audit('attach.update', $id, (string) ($set['reason'] ?? ''), ['label' => $row['label'], 'reason' => $row['reason'], 'note' => $row['note'], 'tags' => $row['tags'], 'nature' => $row['nature']], $set);

        return ['ok' => true, 'error' => null];
    }

    /**
     * Détache : le lien est retiré, le rattachement reste en historique avec le motif du retrait. La classification de la
     * pièce n'est pas abaissée (abaisser est une action explicite, à part).
     *
     * @return array{ok: bool, error: ?string}
     */
    public function detach(int $id, string $reason, string $action = 'attach.remove'): array
    {
        $fail = static fn (string $m): array => ['ok' => false, 'error' => $m];
        if ($this->viewer->isGuest() || $this->viewer->userId() === null) {
            return $fail(self::DENY_GUEST);
        }
        if (!self::ready()) {
            return $fail(self::NOT_READY);
        }
        $row = $this->visibleAttachment($id);
        if ($row === null) {
            return $fail(self::NOT_FOUND);
        }
        if (!$this->mayEdit($row)) {
            return $fail(self::DENY_RIGHT);
        }
        $reason = self::clean($reason, A::REASON_MAX);
        if (mb_strlen($reason) < A::REASON_MIN) {
            return $fail('Le motif du détachement est obligatoire (5 caractères au moins).');
        }
        $tenant = $this->viewer->tenantId();
        $uid = (int) $this->viewer->userId();
        $linkId = (int) ($row['link_id'] ?? 0);
        if (!$this->repo()->markRemoved($tenant, $id, $uid, $reason)) {
            return $fail('Le rattachement n’a pas pu être retiré.');
        }
        $link = $linkId > 0 ? $this->links()->find($tenant, $linkId) : null;
        // Un lien posé par Cerbère garde la trace de ce qui s'est passé : seul l'enrichissement est retiré.
        $keptLink = $link !== null && (string) $link['created_by'] !== SseCerbere::ACTOR_USER;
        if ($link !== null && !$keptLink) {
            $this->links()->delete($tenant, $linkId);
        }
        $column = $keptLink ? null : $this->legacy->remove($tenant, (string) $row['source_type'], (int) $row['source_id'], (string) $row['target_type'], (int) $row['target_id']);
        $this->audit($action, $id, $reason, ['from' => C::ref((string) $row['source_type'], (string) $row['source_id']), 'to' => C::ref((string) $row['target_type'], (string) $row['target_id']), 'label' => $row['label']], ['status' => 'removed', 'legacy' => $column, 'link_kept' => $keptLink]);

        return ['ok' => true, 'error' => null];
    }

    /**
     * Annulation rapide juste après un rattachement : seulement par son auteur, dans la minute, et la classification relevée
     * par l'alignement revient à son niveau d'avant (journalisé).
     *
     * @param list<int> $ids
     * @return array{ok: bool, error: ?string, count: int}
     */
    public function undo(array $ids): array
    {
        if ($this->viewer->isGuest() || $this->viewer->userId() === null || !self::ready()) {
            return ['ok' => false, 'error' => self::NOT_FOUND, 'count' => 0];
        }
        $uid = (int) $this->viewer->userId();
        $count = 0;
        $restored = [];
        foreach (array_slice(array_values(array_unique(array_map('intval', $ids))), 0, A::BATCH_SOURCES * A::BATCH_TARGETS) as $id) {
            $row = $this->visibleAttachment($id);
            if ($row === null || (int) $row['created_by_user_id'] !== $uid || time() - (int) strtotime((string) $row['created_at'] . ' UTC') > 90) {
                continue;
            }
            if (!$this->detach($id, 'Annulation immédiate du rattachement', 'attach.undo')['ok']) {
                continue;
            }
            ++$count;
            $key = C::ref((string) $row['source_type'], (string) $row['source_id']);
            if (A::isLevel((string) ($row['align_to'] ?? '')) && !isset($restored[$key])) {
                $restored[$key] = true;
                $from = A::isLevel((string) ($row['align_from'] ?? '')) ? (string) $row['align_from'] : null;
                // Seulement si la pièce porte encore le niveau posé par cet alignement : on ne défait pas le geste d'un autre.
                if ($this->dir->levelOf((string) $row['source_type'], (string) $row['source_id']) === (string) $row['align_to']) {
                    $this->setLevel((string) $row['source_type'], (int) $row['source_id'], (string) $row['align_to'], $from, 'attach.align_undo', 'Annulation immédiate du rattachement', true);
                }
            }
        }

        return $count > 0 ? ['ok' => true, 'error' => null, 'count' => $count] : ['ok' => false, 'error' => 'Le délai d’annulation est passé : détachez depuis la section « Éléments rattachés ».', 'count' => 0];
    }

    /**
     * Déplace un rattachement vers une autre cible : même libellé, mêmes étiquettes, nouveau motif. La colonne historique suit.
     *
     * @return array{ok: bool, error: ?string, id?: int}
     */
    public function move(int $id, string $targetRef, string $reason): array
    {
        $fail = static fn (string $m): array => ['ok' => false, 'error' => $m];
        $row = $this->visibleAttachment($id);
        if ($this->viewer->isGuest() || $row === null) {
            return $fail($this->viewer->isGuest() ? self::DENY_GUEST : self::NOT_FOUND);
        }
        if (!$this->mayEdit($row)) {
            return $fail(self::DENY_RIGHT);
        }
        $source = C::ref((string) $row['source_type'], (string) $row['source_id']);
        if ($targetRef === C::ref((string) $row['target_type'], (string) $row['target_id'])) {
            return $fail('La pièce est déjà rattachée à cette cible.');
        }
        $tags = SseAttachmentRepository::unpackTags((string) $row['tags']);
        $res = $this->attach([$source], [$targetRef], [
            'reason' => $reason, 'label' => (string) $row['label'], 'note' => (string) $row['note'], 'nature' => (string) $row['nature'], 'nature_label' => (string) $row['nature_label'],
            'tags' => array_map(static fn (int $t): string => '#' . $t, $tags), 'align' => (int) $row['align_requested'] === 1,
        ]);
        if (!$res['ok']) {
            return $fail((string) $res['error']);
        }
        $this->detach($id, 'Déplacé : ' . self::clean($reason, 200), 'attach.move');
        $p = C::parseRef($targetRef);
        if ($p !== null) {
            $this->legacy->write($this->viewer->tenantId(), (string) $row['source_type'], (int) $row['source_id'], $p['type'], (int) $p['id'], (int) $this->viewer->userId(), self::clean($reason, 200), (string) $row['label'], true);
        }

        return ['ok' => true, 'error' => null, 'id' => (int) ($res['created'][0] ?? 0)];
    }

    /** Retrait d'un lien depuis le widget « Liaisons » : le rattachement qu'il portait devient un historique, la colonne suit. */
    public static function onLinkRemoved(int $tenantId, array $link, int $userId, ?Database $db = null): void
    {
        try {
            if (!SseAttachmentRepository::ready()) {
                return;
            }
            $repo = new SseAttachmentRepository($db);
            $att = $repo->findByLink($tenantId, (int) $link['id']);
            if ($att === null) {
                return;
            }
            $repo->markRemoved($tenantId, (int) $att['id'], $userId, 'Lien retiré depuis le widget Liaisons');
            (new SseAttachLegacy($db))->remove($tenantId, (string) $att['source_type'], (int) $att['source_id'], (string) $att['target_type'], (int) $att['target_id']);
        } catch (\Throwable $e) {
            error_log('Rattacher SSE : suivi du retrait de lien indisponible : ' . $e->getMessage());
        }
    }

    // ------------------------------------------------------------------ Création à la volée

    /**
     * Crée un dossier ou une piste depuis la fenêtre (droit d'écrire dans « Dossiers d'enquête »), jamais au-dessus de
     * l'habilitation de l'opérateur.
     *
     * @param array<string, mixed> $data title, description, classification
     * @return array{ok: bool, error: ?string, key?: string}
     */
    public function createTarget(string $type, array $data): array
    {
        $fail = static fn (string $m): array => ['ok' => false, 'error' => $m];
        if ($this->viewer->isGuest() || $this->viewer->userId() === null) {
            return $fail(self::DENY_GUEST);
        }
        if (!in_array($type, A::CREATABLE, true)) {
            return $fail(self::NOT_FOUND);
        }
        if (!self::ready()) {
            return $fail(self::NOT_READY);
        }
        if (!$this->viewer->canWrite('dossiers')) {
            return $fail('Droit manquant : écrire dans « Dossiers d’enquête ».');
        }
        $title = self::clean((string) ($data['title'] ?? ''), 200);
        if (mb_strlen($title) < 3) {
            return $fail('Donnez un titre (3 caractères au moins).');
        }
        $class = (string) ($data['classification'] ?? 'encadrement');
        if (!A::isLevel($class)) {
            $class = 'encadrement';
        }
        if (!$this->viewer->clearanceAllows($class)) {
            return $fail('Vous ne pouvez pas créer au-dessus de votre propre habilitation.');
        }
        $desc = self::clean((string) ($data['description'] ?? ''), 2000);
        $uid = (int) $this->viewer->userId();
        $tenant = $this->viewer->tenantId();
        try {
            $id = $type === 'lead'
                ? $this->repo()->createLead($tenant, ['title' => $title, 'description' => $desc, 'classification' => $class, 'owner_user_id' => $uid, 'user_id' => $uid])
                : (new \App\Repositories\SseCaseRepository($this->db))->create(['tenant_id' => $tenant, 'title' => $title, 'summary' => $desc, 'classification' => $class, 'status' => 'ouvert', 'created_by' => $uid]);
        } catch (\Throwable $e) {
            error_log('Rattacher SSE : création à la volée impossible : ' . $e->getMessage());
            $id = 0;
        }
        if ($id < 1) {
            return $fail('La création n’a pas abouti.');
        }
        $this->audit('attach.create_target', $id, '', null, ['type' => $type, 'title' => $title, 'classification' => $class], 'sse_' . ($type === 'lead' ? 'lead' : 'case'));

        return ['ok' => true, 'error' => null, 'key' => C::ref($type, (string) $id)];
    }

    // ------------------------------------------------------------------ Cerbère-Classification

    /**
     * Propositions d'alignement : pièces qui avaient demandé l'alignement et dont la cible est aujourd'hui plus classifiée
     * (cible reclassifiée après coup). Jamais appliqué tout seul : une personne valide chaque proposition.
     *
     * @return list<array<string, mixed>>
     */
    public function proposals(?string $targetType = null, ?string $targetId = null): array
    {
        if (!self::ready()) {
            return [];
        }
        $out = [];
        $viewerRank = A::rank($this->dir->viewerLevel());
        try {
            $rows = $this->repo()->alignRequested($this->viewer->tenantId(), $targetType, $targetId);
        } catch (\Throwable) {
            return [];
        }
        $graph = $this->dir->graph();
        foreach ($rows as $row) {
            $st = (string) $row['source_type'];
            if (!isset(A::CLASSIFIED[$st])) {
                continue;
            }
            $src = $graph->see($st, (string) $row['source_id']);
            $dst = $graph->see((string) $row['target_type'], (string) $row['target_id']);
            if ($src === null || $dst === null) {
                continue;
            }
            $pl = $this->dir->levelOf($st, (string) $row['source_id']);
            $tl = $this->dir->levelOf((string) $row['target_type'], (string) $row['target_id']);
            if ($tl === null || A::rank($tl) <= A::rank($pl) || A::rank($tl) > $viewerRank) {
                continue;
            }
            $out[] = [
                'id' => (int) $row['id'], 'source' => (string) $src['ref'], 'source_title' => (string) $src['title'], 'target' => (string) $dst['ref'],
                'from' => A::levelAbbr($pl), 'to' => A::levelAbbr($tl), 'to_label' => A::levelLabel($tl),
                'can_apply' => $this->viewer->canWrite(C::TYPES[$st]['section']),
            ];
        }

        return $out;
    }

    /**
     * Applique UNE proposition de Cerbère-Classification (relève la pièce au niveau actuel de la cible).
     *
     * @return array{ok: bool, error: ?string}
     */
    public function applyAlign(int $attachmentId): array
    {
        $fail = static fn (string $m): array => ['ok' => false, 'error' => $m];
        if ($this->viewer->isGuest() || $this->viewer->userId() === null) {
            return $fail(self::DENY_GUEST);
        }
        $row = $this->visibleAttachment($attachmentId);
        if ($row === null || (int) $row['align_requested'] !== 1) {
            return $fail(self::NOT_FOUND);
        }
        $st = (string) $row['source_type'];
        if (!$this->viewer->canWrite(C::TYPES[$st]['section'] ?? '')) {
            return $fail(self::DENY_RIGHT);
        }
        $pl = $this->dir->levelOf($st, (string) $row['source_id']);
        $tl = $this->dir->levelOf((string) $row['target_type'], (string) $row['target_id']);
        if ($tl === null || A::rank($tl) <= A::rank($pl)) {
            return $fail('La pièce est déjà au niveau de la cible.');
        }
        if (!$this->viewer->clearanceAllows($tl)) {
            return $fail('L’alignement dépasserait votre habilitation.');
        }
        if (!$this->setLevel($st, (int) $row['source_id'], $pl, $tl, 'attach.align', 'Proposition de Cerbère-Classification validée (cible reclassifiée)')) {
            return $fail('La classification n’a pas pu être relevée.');
        }
        $this->repo()->setAligned($this->viewer->tenantId(), $attachmentId, $pl, $tl);

        return ['ok' => true, 'error' => null];
    }

    // ------------------------------------------------------------------ Éléments rattachés d'une cible

    /**
     * Section « Éléments rattachés » d'une cible : chaque pièce avec le libellé donné, la nature, le motif, les étiquettes,
     * l'auteur et la date. Une pièce que le lecteur ne peut pas lire est « Élément restreint » (ni nom, ni référence, ni motif).
     *
     * @return array<string, mixed>|null null si la cible est introuvable ou illisible
     */
    public function attached(string $type, string $id): ?array
    {
        $graph = $this->dir->graph();
        $target = isset(C::TYPES[$type]) ? $graph->see($type, $id) : null;
        if ($target === null) {
            return null;
        }
        $ready = self::ready();
        $items = [];
        $types = [];
        $natures = [];
        $usedTags = [];
        if ($ready) {
            $tagNames = [];
            foreach ($this->repo()->tags($this->viewer->tenantId()) as $t) {
                $tagNames[$t['id']] = $t;
            }
            $rows = $this->repo()->forTarget($this->viewer->tenantId(), $type, $id);
            $names = $this->dir->userNames(array_map(static fn (array $r): int => (int) ($r['created_by_user_id'] ?? 0), $rows));
            foreach ($rows as $row) {
                $src = $graph->see((string) $row['source_type'], (string) $row['source_id']);
                if ($src === null) {
                    $items[] = ['restricted' => true, 'id' => 0];
                    continue;
                }
                $level = $this->dir->levelOf((string) $row['source_type'], (string) $row['source_id']);
                $tags = [];
                foreach (SseAttachmentRepository::unpackTags((string) $row['tags']) as $tid) {
                    if (isset($tagNames[$tid])) {
                        $tags[] = $tagNames[$tid];
                        $usedTags[$tid] = $tagNames[$tid];
                    }
                }
                $nature = A::natureLabel((string) $row['nature'], (string) $row['nature_label']);
                $types[(string) $src['type']] = (string) $src['type_label'];
                $natures[(string) $row['nature']] = A::NATURES[(string) $row['nature']] ?? $nature;
                $items[] = [
                    'restricted' => false, 'id' => (int) $row['id'], 'key' => (string) $src['key'], 'type' => (string) $src['type'], 'type_label' => (string) $src['type_label'],
                    'ref' => (string) $src['ref'], 'title' => (string) $src['title'], 'label' => (string) $row['label'], 'href' => (string) $src['href'],
                    'nature' => (string) $row['nature'], 'nature_label' => $nature, 'nature_custom' => (string) $row['nature_label'],
                    'reason' => (string) $row['reason'], 'note' => (string) $row['note'],
                    'tags' => $tags, 'level' => $level, 'level_abbr' => $level !== null ? A::levelAbbr($level) : '',
                    'author' => $names[(int) ($row['created_by_user_id'] ?? 0)] ?? '', 'date' => SseAttachDirectory::day((string) $row['created_at']), 'at' => (string) $row['created_at'],
                    'aligned' => (int) $row['align_requested'] === 1, 'can_edit' => !$this->viewer->isGuest() && $this->mayEdit($row),
                ];
            }
        }
        $targetLevel = $this->dir->levelOf($type, $id);

        return [
            'target' => $target + ['level' => $targetLevel, 'level_abbr' => $targetLevel !== null ? A::levelAbbr($targetLevel) : ''],
            'ready' => $ready, 'items' => $items, 'types' => $types, 'natures' => $natures, 'tags' => array_values($usedTags),
            'proposals' => $this->proposals($type, $id), 'guest' => $this->viewer->isGuest(),
            'latest' => $items !== [] && empty($items[0]['restricted']) ? ['author' => $items[0]['author'], 'date' => $items[0]['date']] : null,
        ];
    }

    // ------------------------------------------------------------------ Outils

    /**
     * Étiquettes demandées (noms, ou « #12 » pour un identifiant du catalogue) => identifiants. Une étiquette inconnue est créée
     * si l'opérateur peut rattacher (elle entre au catalogue de la communauté).
     *
     * @param list<mixed> $tags
     * @return list<int>
     */
    private function resolveTags(array $tags): array
    {
        $out = [];
        $known = [];
        foreach ($this->repo()->tags($this->viewer->tenantId()) as $t) {
            $known[$t['id']] = true;
        }
        foreach (array_slice($tags, 0, A::TAGS_PER_LINK) as $tag) {
            $tag = trim((string) $tag);
            if ($tag === '') {
                continue;
            }
            $color = 'slate';
            if (str_contains($tag, '|')) {
                [$tag, $color] = explode('|', $tag, 2);
            }
            if (preg_match('/^#(\d+)$/', $tag, $m) === 1) {
                if (isset($known[(int) $m[1]])) {
                    $out[] = (int) $m[1];
                }
                continue;
            }
            $id = $this->repo()->tagId($this->viewer->tenantId(), $tag, true, (int) $this->viewer->userId(), $color);
            if ($id > 0) {
                $out[] = $id;
            }
        }

        return array_values(array_unique($out));
    }

    /** Change la classification d'un élément, avec son entrée de journal (ancien et nouveau niveau). */
    private function setLevel(string $type, int $id, ?string $from, ?string $to, string $action, string $reason, bool $allowLower = false): bool
    {
        if (!isset(A::CLASSIFIED[$type]) || ($to !== null && !A::isLevel($to))) {
            return false;
        }
        // Garde-fou : ce service ne sait que relever. La seule baisse admise est l'annulation immédiate de son propre alignement.
        if (!$allowLower && A::rank($to) <= A::rank($from)) {
            return false;
        }
        if ($to !== null && !$this->viewer->clearanceAllows($to)) {
            return false;
        }
        [$table, $col] = A::CLASSIFIED[$type];
        try {
            if ($to === null && $type !== 'person') {
                return false;
            }
            $this->db->execute("UPDATE {$table} SET {$col} = :c WHERE tenant_id = :t AND id = :id", ['c' => $to, 't' => $this->viewer->tenantId(), 'id' => $id]);
        } catch (\Throwable) {
            return false;
        }
        $this->audit($action, $id, $reason, ['classification' => $from], ['classification' => $to], $table);

        return true;
    }

    /** @param array<string, mixed>|null $before @param array<string, mixed>|null $after */
    private function audit(string $action, int $id, string $reason, ?array $before, ?array $after, string $objectType = 'sse_link_attachment'): void
    {
        try {
            (new SseAuditLogRepository($this->db))->write([
                'tenant_id' => $this->viewer->tenantId(), 'actor_user_id' => (int) $this->viewer->userId(), 'actor_label' => 'Rattacher', 'action' => $action,
                'object_type' => $objectType, 'object_id' => $id, 'reason' => $reason, 'before' => $before, 'after' => $after,
            ]);
        } catch (\Throwable $e) {
            error_log('Rattacher SSE : journal indisponible : ' . $e->getMessage());
        }
    }

    private static function clean(string $text, int $max): string
    {
        return mb_substr(trim((string) preg_replace('/\s+/u', ' ', strip_tags($text))), 0, $max);
    }
}
