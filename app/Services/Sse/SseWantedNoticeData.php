<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Repositories\SseWantedAttachmentRepository;
use App\Repositories\SseWantedExtrasRepository;
use App\Repositories\SseWantedMetaRepository;
use App\Repositories\SseWantedNoticeRepository;
use App\Support\SseWantedCatalog as C;
use App\Support\SseWantedPhysical;

/**
 * Données d'un avis de recherche mises à disposition des autres écrans : la notice PDF (SseWantedNoticePdf) et le jeu.
 *
 * Deux formes, volontairement stables :
 *  - {@see self::forPdf()} : codes avec libellés dans la langue demandée, lignes du signalement, pièces jointes avec chemins
 *    disque (miniature et image), image principale. Les images suivent les droits du lecteur de la session (identité de
 *    membre : rien sans l'autorisation hiérarchique).
 *  - {@see self::forGame()} : codes et libellés dans les deux langues, signalement court, type et légende des images. Jamais
 *    d'image ni de chemin (Arma ne charge pas d'image HTTP) ; rien non plus pour l'identité d'un membre.
 *
 * Un avis sans codes, sans signalement ni pièces donne des listes vides : les consommateurs n'ont rien à prévoir.
 */
final class SseWantedNoticeData
{
    /**
     * @param array<string, mixed> $notice ligne d'avis (SseWantedNoticeRepository::find)
     * @return array{reasons: list<array{code: string, label: string}>, measures: list<array{code: string, label: string}>, physical_rows: list<array{field: string, label: string, value: string}>, physical_line: string, attachments: list<array<string, mixed>>, main_image: ?array<string, mixed>}
     */
    public static function forPdf(array $notice, string $lang = 'fr', ?SseWantedAttachments $attachments = null): array
    {
        $lang = C::lang($lang);
        $tenantId = (int) $notice['tenant_id'];
        $extras = (new SseWantedExtrasRepository())->forNotice($tenantId, (int) $notice['id']);
        $labels = static fn (array $list): array => array_map(
            static fn (array $c): array => ['code' => $c['code'], 'label' => $lang === 'en' ? $c['en'] : $c['fr']],
            $list
        );
        $images = SseWantedAttachments::forNotice((int) $notice['id'], $tenantId, $attachments);
        $main = null;
        foreach ($images as $img) {
            if ($img['is_main']) {
                $main = $img;
                break;
            }
        }

        return [
            'reasons' => $labels($extras['codes']['reasons']),
            'measures' => $labels($extras['codes']['measures']),
            'physical_rows' => SseWantedPhysical::rows($extras['physical'], $lang),
            'physical_line' => SseWantedPhysical::shortLine($extras['physical'], $lang),
            'attachments' => $images,
            'main_image' => $main ?? ($images[0] ?? null),
        ];
    }

    /**
     * Avis actifs de la communauté, avec codes, signalement court et images décrites (type, légende).
     *
     * @return list<array<string, mixed>>
     */
    public static function forGame(int $tenantId, int $limit = 80, ?SseMemberImageGate $gate = null): array
    {
        if (!SseWantedNoticeRepository::ready()) {
            return [];
        }
        $gate ??= SseMemberImageGate::forSession();
        $notices = (new SseWantedNoticeRepository())->list($tenantId, ['status' => 'active', 'limit' => max(1, min(200, $limit))]);
        $ids = array_map(static fn (array $n): int => (int) $n['id'], $notices);
        $extras = (new SseWantedExtrasRepository())->forNotices($tenantId, $ids);
        $metas = (new SseWantedMetaRepository())->forNotices($tenantId, $ids);
        $out = [];
        foreach ($notices as $n) {
            $meta = $metas[(int) $n['id']] ?? SseWantedMetaRepository::hydrate(null);
            // Circuit de validation : un avis proposé ou refusé n'est jamais diffusé au jeu.
            if ($meta['state'] !== SseWantedMetaRepository::LIVE) {
                continue;
            }
            $x = $extras[(int) $n['id']] ?? SseWantedExtrasRepository::hydrate(null);
            $isMember = $gate->identityState($tenantId, (int) $n['identity_id'])['class'] !== SseMemberImageGate::CLASS_PUBLIC;
            $rows = [];
            if (!$isMember && SseWantedAttachmentRepository::ready()) {
                $rows = SseWantedAttachments::export((new SseWantedAttachmentRepository())->forNotice($tenantId, (int) $n['id']));
            }
            $out[] = [
                'id' => (int) $n['id'],
                'ref' => (string) $n['ref'],
                'name' => (string) $n['identity_name'],
                'type' => (string) $n['notice_type'],
                'threat' => (string) $n['threat_level'],
                'armed' => (string) $n['armed'],
                'zone' => (string) ($n['zone'] ?? ''),
                'reasons' => $x['codes']['reasons'],
                'measures' => $x['codes']['measures'],
                'physical' => $isMember ? [] : SseWantedPhysical::export($x['physical']),
                'images' => $rows,
                // Additif : le service demandeur (code stable, nom FR et EN, sigle, coordonnées fictives) et la classification.
                'service' => self::serviceForGame($meta, $n),
                'classification' => $meta['classification'],
            ];
        }

        return $out;
    }

    /**
     * Service demandeur d'un avis pour le jeu : l'instantané du jour de la pose, ou le texte saisi à la main (code vide).
     *
     * @param array<string, mixed> $meta
     * @param array<string, mixed> $notice
     * @return array{code: string, fr: string, en: string, ac: string, contact: string}
     */
    public static function serviceForGame(array $meta, array $notice): array
    {
        $snap = is_array($meta['service'] ?? null) ? $meta['service'] : [];
        $label = (string) ($notice['contact_label'] ?? '');

        return [
            'code' => (string) ($meta['service_code'] ?? ''),
            'fr' => (string) ($snap['fr'] ?? $label),
            'en' => (string) ($snap['en'] ?? $label),
            'ac' => (string) ($snap['ac'] ?? ''),
            'contact' => (string) ($snap['contact'] ?? ($notice['contact_detail'] ?? '')),
        ];
    }
}
