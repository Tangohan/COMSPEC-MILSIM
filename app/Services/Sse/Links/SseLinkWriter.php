<?php

declare(strict_types=1);

namespace App\Services\Sse\Links;

use App\Repositories\SseAuditLogRepository;
use App\Repositories\SseEntityLinkRepository;
use App\Support\SseCerbere;
use App\Support\SseLinkCatalog as C;

/**
 * Liens posés à la main depuis le widget « Liaisons » : un motif obligatoire, les droits d'écriture de la section de
 * l'élément de départ, la lecture des deux bouts (un élément qu'on ne voit pas répond comme un élément qui n'existe pas), et
 * un journal d'audit à la pose comme au retrait. Un lien posé par un traitement Cerbère ou déduit d'une colonne ne se retire
 * jamais d'ici.
 */
final class SseLinkWriter
{
    public const DENY_GUEST = 'Une session invitée ne peut pas poser de lien : connectez-vous avec votre compte.';
    public const DENY_RIGHT = 'Droit manquant : écrire dans la section de cet élément.';
    public const NOT_FOUND = 'Élément introuvable.';
    public const NOT_READY = 'Les liens manuels arrivent avec la mise à jour du site (étape sse_entity_links).';
    public const PER_ELEMENT_MAX = 60;

    public function __construct(
        private readonly SseLinkViewer $viewer,
        private readonly SseLinkGraph $graph,
        private ?SseEntityLinkRepository $links = null,
    ) {
    }

    private function links(): SseEntityLinkRepository
    {
        return $this->links ??= new SseEntityLinkRepository();
    }

    /**
     * @return array{ok: bool, error: ?string, id: int}
     */
    public function add(string $fromType, string $fromId, string $toType, string $toId, string $relation, string $reason): array
    {
        $fail = static fn (string $m): array => ['ok' => false, 'error' => $m, 'id' => 0];
        if ($this->viewer->isGuest() || $this->viewer->userId() === null) {
            return $fail(self::DENY_GUEST);
        }
        if (!SseEntityLinkRepository::ready()) {
            return $fail(self::NOT_READY);
        }
        if (!isset(C::TYPES[$fromType], C::TYPES[$toType]) || $fromType === 'jnet' || $toType === 'jnet'
            || preg_match('/^[1-9]\d{0,9}$/', $fromId) !== 1 || preg_match('/^[1-9]\d{0,9}$/', $toId) !== 1) {
            return $fail(self::NOT_FOUND);
        }
        if ($fromType === $toType && $fromId === $toId) {
            return $fail('Un élément ne peut pas être lié à lui-même.');
        }
        // Les deux bouts doivent être visibles : même réponse pour « n'existe pas » et « vous ne le voyez pas ».
        if ($this->graph->see($fromType, $fromId) === null || $this->graph->see($toType, $toId) === null) {
            return $fail(self::NOT_FOUND);
        }
        if (!$this->viewer->canWrite(C::TYPES[$fromType]['section'])) {
            return $fail(self::DENY_RIGHT);
        }
        $reason = trim((string) preg_replace('/\s+/u', ' ', strip_tags($reason)));
        if (mb_strlen($reason) < C::MANUAL_REASON_MIN) {
            return $fail('Le motif est obligatoire (' . C::MANUAL_REASON_MIN . ' caractères au moins) : dites pourquoi ces deux éléments sont liés.');
        }
        $reason = mb_substr($reason, 0, C::MANUAL_REASON_MAX);
        if (!in_array($relation, C::MANUAL_RELATIONS, true)) {
            $relation = 'related';
        }
        $repo = $this->links();
        $tenant = $this->viewer->tenantId();
        if ($repo->pair($tenant, $fromType, $fromId, $toType, $toId) !== null) {
            return $fail('Ces deux éléments sont déjà liés.');
        }
        if (count($repo->touching($tenant, [$fromType => [$fromId]], self::PER_ELEMENT_MAX + 1)) > self::PER_ELEMENT_MAX) {
            return $fail('Cet élément porte déjà ' . self::PER_ELEMENT_MAX . ' liens enregistrés.');
        }
        $uid = (int) $this->viewer->userId();
        $id = $repo->add($tenant, $fromType, $fromId, $toType, $toId, $relation, $reason, SseCerbere::RULE_MANUAL, SseCerbere::ACTOR_USER, $uid);
        if ($id < 1) {
            return $fail('Le lien n’a pas pu être enregistré.');
        }
        $this->audit($tenant, $uid, 'entity_link.add', $id, ['from' => C::ref($fromType, $fromId), 'to' => C::ref($toType, $toId), 'relation' => $relation, 'reason' => $reason]);

        return ['ok' => true, 'error' => null, 'id' => $id];
    }

    /**
     * @return array{ok: bool, error: ?string}
     */
    public function remove(int $linkId): array
    {
        $fail = static fn (string $m): array => ['ok' => false, 'error' => $m];
        if ($this->viewer->isGuest() || $this->viewer->userId() === null) {
            return $fail(self::DENY_GUEST);
        }
        if (!SseEntityLinkRepository::ready()) {
            return $fail(self::NOT_READY);
        }
        $tenant = $this->viewer->tenantId();
        $row = $linkId > 0 ? $this->links()->find($tenant, $linkId) : null;
        // Une ligne d'une autre communauté, absente ou invisible : même réponse.
        if ($row === null || $this->graph->see((string) $row['source_type'], (string) $row['source_id']) === null || $this->graph->see((string) $row['target_type'], (string) $row['target_id']) === null) {
            return $fail(self::NOT_FOUND);
        }
        if ((string) $row['created_by'] !== SseCerbere::ACTOR_USER) {
            return $fail('Un lien posé automatiquement par Cerbère ne se retire pas : il garde la trace de ce qui s’est passé.');
        }
        $uid = (int) $this->viewer->userId();
        if ((int) ($row['created_by_user_id'] ?? 0) !== $uid && !$this->viewer->canWrite(C::TYPES[(string) $row['source_type']]['section'] ?? 'fichiers')) {
            return $fail(self::DENY_RIGHT);
        }
        if (!$this->links()->delete($tenant, $linkId)) {
            return $fail('Le lien n’a pas pu être retiré.');
        }
        $this->audit($tenant, $uid, 'entity_link.remove', $linkId, ['from' => C::ref((string) $row['source_type'], (string) $row['source_id']), 'to' => C::ref((string) $row['target_type'], (string) $row['target_id']), 'reason' => (string) $row['reason']]);

        return ['ok' => true, 'error' => null];
    }

    /** @param array<string, mixed> $after */
    private function audit(int $tenant, int $uid, string $action, int $id, array $after): void
    {
        try {
            (new SseAuditLogRepository())->write([
                'tenant_id' => $tenant, 'actor_user_id' => $uid, 'actor_label' => 'Liaisons', 'action' => $action,
                'object_type' => 'sse_entity_link', 'object_id' => $id, 'after' => $after,
            ]);
        } catch (\Throwable $e) {
            error_log('Liaisons SSE : journal d’audit indisponible : ' . $e->getMessage());
        }
    }
}
