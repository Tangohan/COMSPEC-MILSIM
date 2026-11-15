<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Repositories\SseFileRecordRepository;
use App\Repositories\SseFileRepository;
use App\Repositories\SseWantedDetectionRepository;
use App\Repositories\SseWantedNoticeRepository;
use App\Repositories\SseWantedSettingsRepository;
use App\Support\SseWantedCatalog as C;

/**
 * Données de la fiche identité en dossier (vue papers/identity_show) : avis de recherche actif et passés, détections,
 * chronologie, signalement, alias, liens, photos du dossier et mentions. Ne fait que lire ; chaque source manquante
 * (étape de base de données pas encore passée, ancienne communauté sans signalement) donne simplement une section vide.
 *
 * Les droits sont ceux de la fiche : la chronologie est filtrée par {@see SseIdentityTimelineService}, les champs et liens
 * masqués par le lore restent hors du dossier, la vérité de jeu suit {@see SseWantedAccess::canSeeTruth()}.
 */
final class SseIdentityDossierService
{
    public function __construct(
        private ?SseWantedNoticeRepository $notices = null,
        private ?SseWantedDetectionRepository $detections = null,
        private ?SseWantedSettingsRepository $settings = null,
        private ?SseIdentityTimelineService $timeline = null,
        private ?SseFileRecordRepository $records = null,
    ) {
        $this->notices ??= new SseWantedNoticeRepository();
        $this->detections ??= new SseWantedDetectionRepository();
        $this->settings ??= new SseWantedSettingsRepository();
        $this->timeline ??= new SseIdentityTimelineService();
    }

    /**
     * @param array<string, mixed> $identity ligne d'identité (SseIdentityRepository::find)
     * @return array<string, mixed>
     */
    public function build(int $tenantId, array $identity, SseWantedAccess $access, string $lang, string $filter = ''): array
    {
        $id = (int) $identity['id'];
        $lang = C::lang($lang);
        // Avis, détections et mentions relèvent de la section « fichiers » : sans le droit de la lire, ils restent hors du dossier.
        $readable = $access->canRead();
        $ready = SseWantedNoticeRepository::ready();
        $config = $this->settings->get($tenantId);
        $notice = null;
        $notices = [];
        $detections = [];
        $counts = ['new' => 0, 'acknowledged' => 0, 'dismissed' => 0];
        if ($ready && $readable) {
            (new SseWantedService($this->notices))->expireDue($tenantId, $id);
            $notice = $this->safe(fn (): ?array => $this->notices->activeForIdentity($tenantId, $id));
            $notices = $this->safe(fn (): array => $this->notices->listForIdentity($tenantId, $id, 50)) ?? [];
            $detections = $this->safe(fn (): array => $this->detections->list($tenantId, ['identity_id' => $id, 'limit' => 50])) ?? [];
            foreach ($detections as $d) {
                if (isset($counts[(string) $d['status']])) {
                    ++$counts[(string) $d['status']];
                }
            }
        }
        $profile = $this->safe(fn (): ?array => $this->records()->profile($tenantId, $id));
        $known = static fn (string $field): bool => ($profile['visibility'][$field]['v'] ?? 'known') !== 'hidden';
        if ($profile !== null) {
            foreach (['height_cm', 'weight_kg', 'build', 'hair', 'eyes', 'complexion', 'marks', 'affiliation'] as $field) {
                if (!$known($field)) {
                    $profile[$field] = null;
                }
            }
        }
        $aliases = array_values(array_filter(
            $this->safe(fn (): array => $this->records()->aliasesFor($tenantId, [$id])[$id] ?? []) ?? [],
            static fn (array $a): bool => ($a['visibility'] ?? 'known') === 'known'
        ));
        $links = array_values(array_filter(
            $this->safe(fn (): array => $this->records()->linksFor($tenantId, [$id])[$id] ?? []) ?? [],
            static fn (array $l): bool => ($l['visibility'] ?? 'known') === 'known'
        ));
        $backLinks = $this->safe(fn (): array => $this->records()->backLinks($tenantId, $id)) ?? [];
        $manage = $access->canManage();
        $photos = array_values(array_filter(
            $this->safe(fn (): array => $this->records()->photosFor($tenantId, [$id])[$id] ?? []) ?? [],
            // Une photo masquée reste visible (et marquée « masquée ») pour qui gère les fichiers ; les autres ne la voient pas.
            static fn (array $p): bool => ($p['visibility'] ?? 'known') !== 'hidden' || $manage
        ));
        $mentions = $readable ? ($this->safe(fn (): array => (new SseFileRepository())->activeMentionsFor($tenantId, [$id], [])) ?? []) : [];
        $timeline = $this->timeline->build($tenantId, $identity, $access, $lang, $filter);

        return [
            'wantedReady' => $ready,
            'wantedReadable' => $readable,
            'wantedConfig' => $config,
            'wantedTypes' => C::types($config),
            'notice' => $notice,
            'notices' => $notices,
            'detections' => $detections,
            'detectionCounts' => $counts,
            'profile' => $profile,
            'fileAliases' => $aliases,
            'fileLinks' => $links,
            'fileBackLinks' => array_values(array_filter($backLinks, static fn (array $l): bool => ($l['visibility'] ?? 'known') === 'known')),
            'filePhotos' => $photos,
            'fileMentions' => $mentions,
            'timeline' => $timeline,
            'timelineFilter' => isset(C::EVENT_FILTERS[$filter]) ? $filter : '',
            'lang' => $lang,
        ];
    }

    private function records(): SseFileRecordRepository
    {
        return $this->records ??= new SseFileRecordRepository();
    }

    /**
     * @template T
     * @param \Closure(): T $fn
     * @return T|null
     */
    private function safe(\Closure $fn): mixed
    {
        try {
            return $fn();
        } catch (\Throwable) {
            return null;
        }
    }
}
