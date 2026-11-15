<?php

declare(strict_types=1);

namespace App\Support;

use App\Support\SseWantedCatalog as C;

/**
 * Contrôles de cohérence d'un avis avant sa pose : les BLOQUANTS empêchent de placer l'avis, les AVERTISSEMENTS le laissent passer
 * avec un message clair. Même règles côté serveur (autorité, à la pose) et côté formulaire (récapitulatif de l'onglet « Émission »,
 * qui reçoit les mêmes codes).
 *
 * @phpstan-type Finding array{code: string, text: string}
 */
final class SseWantedChecks
{
    /**
     * @param array<string, mixed> $data données validées de l'avis (notice_type, threat_level, armed, reason, zone, issued_at, expires_at)
     * @param array<string, mixed>|null $service service choisi, ou null (saisie « Autre »)
     * @param array{classification?: string, has_active?: bool, is_member?: bool, now?: \DateTimeImmutable} $ctx
     * @return array{blocking: list<array{code: string, text: string}>, warnings: list<array{code: string, text: string}>}
     */
    public static function run(array $data, ?array $service, array $ctx = []): array
    {
        $blocking = [];
        $warnings = [];
        $now = ($ctx['now'] ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');
        $class = (string) ($ctx['classification'] ?? ($service['class'] ?? 'encadrement'));
        $issued = (string) ($data['issued_at'] ?? $now);
        $expires = isset($data['expires_at']) && $data['expires_at'] !== '' ? (string) $data['expires_at'] : null;

        if (!empty($ctx['is_member'])) {
            $blocking[] = ['code' => 'member', 'text' => 'Cette identité est celle d’un membre : aucun avis de recherche n’est placé sur un membre réel.'];
        }
        if (!empty($ctx['has_active'])) {
            $blocking[] = ['code' => 'active', 'text' => 'Cette identité fait déjà l’objet d’un avis actif : levez-le ou renouvelez-le plutôt que d’en placer un second.'];
        }
        if (mb_strlen(trim((string) ($data['reason'] ?? ''))) < 3) {
            $blocking[] = ['code' => 'reason', 'text' => 'Aucun motif : choisissez un code de motif ou écrivez la raison de l’avis.'];
        }
        if ($expires !== null && $expires <= $issued) {
            $blocking[] = ['code' => 'expiry_order', 'text' => 'La date d’expiration précède la date d’émission : corrigez l’une des deux.'];
        } elseif ($expires !== null && $expires <= $now) {
            $blocking[] = ['code' => 'expiry_past', 'text' => 'La date d’expiration est déjà passée : l’avis expirerait dès sa pose.'];
        }
        if ($service !== null && !SseWantedServices::canIssueAt($service, $class)) {
            $blocking[] = ['code' => 'service_class', 'text' => 'Le service « ' . ($service['ac'] !== '' ? $service['ac'] : $service['fr']) . ' » ne peut pas émettre au niveau ' . SseUsClassification::abbr($class) . ' (maximum : ' . SseUsClassification::abbr((string) $service['max']) . ') : choisissez un autre service ou baissez la classification.'];
        }

        if ((string) ($data['armed'] ?? '') === 'yes' && trim((string) ($data['zone'] ?? '')) === '') {
            $warnings[] = ['code' => 'armed_zone', 'text' => 'Avis « armé et dangereux » sans pays ni zone : les équipes ne sauront pas où être vigilantes.'];
        }
        if ($service !== null) {
            if (isset($data['threat_level']) && $service['threats'] !== [] && !in_array((string) $data['threat_level'], $service['threats'], true)) {
                $warnings[] = ['code' => 'service_threat', 'text' => 'Ce niveau de menace est inhabituel pour ce service.'];
            }
            if (isset($data['notice_type']) && $service['types'] !== [] && !in_array((string) $data['notice_type'], $service['types'], true)) {
                $warnings[] = ['code' => 'service_type', 'text' => 'Ce type d’avis est inhabituel pour ce service.'];
            }
        }
        if (in_array((string) ($data['notice_type'] ?? ''), ['capture_or_kill', 'hvt'], true) && C::threatRank((string) ($data['threat_level'] ?? 'medium')) < 2) {
            $warnings[] = ['code' => 'type_threat', 'text' => 'Un avis de ce type porte d’ordinaire un niveau de menace élevé ou critique.'];
        }
        if ($expires === null && $service !== null && (int) $service['exp'] > 0) {
            $warnings[] = ['code' => 'no_expiry', 'text' => 'Aucune date d’expiration alors que ce service émet d’ordinaire pour ' . (int) $service['exp'] . ' jours.'];
        }
        if ($expires !== null && strtotime($expires . ' UTC') - strtotime($issued . ' UTC') > 400 * 86400) {
            $warnings[] = ['code' => 'long_expiry', 'text' => 'Validité de plus d’un an : prévoyez plutôt un renouvellement.'];
        }
        if (strtotime($issued . ' UTC') - strtotime($now . ' UTC') > 30 * 86400) {
            $warnings[] = ['code' => 'issued_future', 'text' => 'Date d’émission à plus de trente jours dans le futur : vérifiez-la.'];
        }

        return ['blocking' => $blocking, 'warnings' => $warnings];
    }
}
