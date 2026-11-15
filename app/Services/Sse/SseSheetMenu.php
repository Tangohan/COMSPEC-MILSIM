<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Repositories\SseSheetRepository;
use App\Repositories\SseWantedNoticeRepository;
use App\Support\SseSheetCatalog as D;

/**
 * Bloc « Émettre un document » partagé par la fiche personne et la fiche du registre : mêmes modèles, mêmes boutons, mêmes
 * droits (SseSheetAccess). Un modèle que le lecteur ne peut pas émettre n'a pas de bouton : il est rangé dans « non
 * disponibles » avec la raison (infobulle et texte, lisible aussi au toucher).
 */
final class SseSheetMenu
{
    /**
     * @param array{person: ?array<string, mixed>, identity: ?array<string, mixed>, identities: list<array<string, mixed>>, person_id: ?int, identity_id: ?int, ref: string} $subject
     * @param 'personne'|'identite' $kind
     * @return array<string, mixed>
     */
    public static function build(int $tenantId, array $subject, string $kind, SseSheetAccess $access, bool $guest, string $lang = 'fr'): array
    {
        $lang = D::lang($lang);
        $id = $kind === 'identite' ? (int) ($subject['identity_id'] ?? 0) : (int) ($subject['person_id'] ?? 0);
        $out = [
            'kind' => $kind, 'id' => $id, 'subject_ref' => (string) $subject['ref'], 'lang' => $lang, 'allowed' => [], 'locked' => [], 'reason' => null,
            'notice' => null, 'issues' => [], 'ready' => SseSheetRepository::ready(), 'canPhrases' => $access->canManagePhrases(), 'primary' => null,
            'phrasesUrl' => url('atak/sse/identites/emission/phrases'), 'level' => $access->level(),
            'vigilances' => [], 'banner' => null,
        ];
        if ($id < 1) {
            return $out;
        }
        // Fiches de vigilance émises et bandeau d'avertissement : jamais plus que l'habilitation du lecteur (SseVigilanceFollow).
        if (!$guest) {
            try {
                $follow = SseVigilanceFollow::forSubject($tenantId, $subject, $access, $lang === 'en' ? 1 : 0);
                $out['vigilances'] = $follow['list'];
                $out['banner'] = $follow['banner'];
            } catch (\Throwable $e) {
                error_log('Fiches de vigilance : liste indisponible : ' . $e->getMessage());
            }
        }
        if (!$access->canDraft()) {
            $out['reason'] = $access->denial('identification') ?? 'Droit manquant.';

            return $out;
        }
        $hasIdentity = (int) ($subject['identity_id'] ?? 0) > 0;
        $activeNotice = null;
        if ($hasIdentity) {
            try {
                $activeNotice = SseWantedNoticeRepository::ready() ? (new SseWantedNoticeRepository())->activeForIdentity($tenantId, (int) $subject['identity_id']) : null;
            } catch (\Throwable) {
                $activeNotice = null;
            }
        }
        foreach (D::MODELS as $model => $def) {
            $label = $def['label'][$lang === 'en' ? 1 : 0];
            $hint = $def['hint'][$lang === 'en' ? 1 : 0];
            $denial = $access->denial($model);
            if ($model !== 'notice' && !$out['ready']) {
                $denial ??= 'Disponible dès que l’étape de base de données « fiches PDF » aura été passée par l’équipe technique.';
            }
            $item = ['model' => $model, 'label' => $label, 'hint' => $hint, 'url' => url('atak/sse/identites/emission/' . $kind . '/' . $id . '/' . $model), 'action' => 'link'];
            if ($model !== 'notice') {
                $item['quick_url'] = url('atak/sse/identites/emission/' . $kind . '/' . $id . '/' . $model . '/rapide');
            }
            if ($model === 'notice') {
                if ($denial === null && $activeNotice === null) {
                    if ($hasIdentity) {
                        $item['url'] = url('atak/sse/papiers/identites/' . (int) $subject['identity_id'] . '/avis/nouveau');
                        $item['label'] = $lang === 'en' ? 'Wanted notice: place one first' : 'Avis de recherche : en placer un d’abord';
                        $item['hint'] = $lang === 'en' ? 'No active notice on this identity.' : 'Aucun avis actif sur cette identité.';
                    } else {
                        $createDenial = (new SsePersonRegistryLinkService())->createDenial($tenantId, $guest);
                        if ($createDenial !== null) {
                            $denial = $createDenial;
                        } else {
                            $item['action'] = 'create_identity';
                            $item['url'] = url('atak/sse/identites/' . (int) ($subject['person_id'] ?? 0) . '/registre');
                            $item['label'] = $lang === 'en' ? 'Wanted notice: create the registry identity' : 'Avis de recherche : créer l’identité du registre';
                            $item['hint'] = $lang === 'en' ? 'Creates the registry identity in one click, then place the notice and issue it.' : 'Crée l’identité du registre en un clic ; vous pourrez ensuite placer l’avis et l’émettre.';
                            if (($subject['person_id'] ?? null) === null) {
                                $denial = 'Cette identité n’est rattachée à aucune fiche personne.';
                            }
                        }
                    }
                }
                $out['notice'] = $activeNotice !== null ? ['ref' => (string) $activeNotice['ref']] : null;
            }
            if ($denial !== null) {
                $out['locked'][] = ['model' => $model, 'label' => $label, 'reason' => $denial];

                continue;
            }
            $out['allowed'][] = $item;
        }
        $models = array_column($out['allowed'], 'model');
        $out['primary'] = in_array('identification', $models, true) ? 'identification' : ($models[0] ?? null);
        if ($out['ready']) {
            try {
                $ids = array_map(static fn (array $i): int => (int) $i['id'], (array) ($subject['identities'] ?? []));
                $out['issues'] = (new SseSheetRepository())->listIssues($tenantId, $subject['person_id'], $ids, 30);
            } catch (\Throwable) {
                $out['issues'] = [];
            }
        }

        return $out;
    }
}
