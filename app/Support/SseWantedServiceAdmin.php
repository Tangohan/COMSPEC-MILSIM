<?php

declare(strict_types=1);

namespace App\Support;

use App\Support\SseWantedCatalog as C;

/**
 * Gestion des services demandeurs d'une communauté : création, modification, duplication d'un service du catalogue, activation,
 * suppression (refusée si des avis le portent), ordre, service par défaut, export et import JSON. Fonctions pures sur les réglages
 * de la communauté : l'enregistrement, les droits, le jeton CSRF et le journal d'audit sont ceux de SseWantedNoticeController.
 *
 * Chaque opération renvoie les réglages mis à jour (« config »), la liste des erreurs et un message ; en cas d'erreur, les
 * réglages sont rendus tels quels.
 *
 * @phpstan-type Result array{ok: bool, config: array<string, mixed>, errors: list<string>, message: string, code: ?string}
 */
final class SseWantedServiceAdmin
{
    public const IMPORT_MAX_BYTES = 200000;

    /**
     * @param array<string, mixed> $config
     * @param array<string, mixed> $input saisie du tiroir « Nouveau service »
     * @return Result
     */
    public static function create(array $config, array $input): array
    {
        $checked = SseWantedServices::validate($input, $config);
        if ($checked['service'] === null || $checked['errors'] !== []) {
            return self::fail($config, $checked['errors']);
        }

        return self::store($config, $checked['service'], 'Service « ' . $checked['service']['fr'] . ' » créé.');
    }

    /**
     * @param array<string, mixed> $config
     * @param array<string, mixed> $input
     * @return Result
     */
    public static function update(array $config, string $code, array $input): array
    {
        $checked = SseWantedServices::validate($input, $config, $code);
        if ($checked['service'] === null || $checked['errors'] !== []) {
            return self::fail($config, $checked['errors']);
        }

        return self::store($config, $checked['service'], 'Service « ' . $checked['service']['fr'] . ' » modifié.');
    }

    /**
     * Copie d'un service (du catalogue ou de la communauté) à personnaliser ; la copie est un service de la communauté.
     *
     * @param array<string, mixed> $config
     * @return Result
     */
    public static function duplicate(array $config, string $code): array
    {
        $source = SseWantedServices::find($code, $config);
        if ($source === null) {
            return self::fail($config, ['Service introuvable.']);
        }
        $taken = array_map(static fn (array $s): string => mb_strtolower((string) $s['fr']), SseWantedServices::all($config));
        $name = $source['fr'] . ' (copie)';
        for ($i = 2; in_array(mb_strtolower($name), $taken, true) && $i < 50; $i++) {
            $name = $source['fr'] . ' (copie ' . $i . ')';
        }
        $copy = $source;
        $copy['fr'] = mb_substr($name, 0, 120);
        $copy['en'] = mb_substr($source['en'] . ' (copy)', 0, 120);
        $copy['enabled'] = true;
        // L'autorité suit le nom : une copie garde celle de son modèle, que la communauté ajuste ensuite.
        $checked = SseWantedServices::validate($copy, $config);
        if ($checked['service'] === null || $checked['errors'] !== []) {
            return self::fail($config, $checked['errors']);
        }

        return self::store($config, $checked['service'], 'Copie « ' . $checked['service']['fr'] . ' » créée : modifiez-la à votre convenance.');
    }

    /**
     * Active ou désactive un service (catalogue : masqué ; communauté : désactivé). Les avis déjà posés restent lisibles.
     *
     * @param array<string, mixed> $config
     * @return Result
     */
    public static function setEnabled(array $config, string $code, bool $enabled): array
    {
        $code = strtoupper($code);
        $s = SseWantedServices::normalizeSettings($config['services'] ?? []);
        $service = SseWantedServices::find($code, $config);
        if ($service === null) {
            return self::fail($config, ['Service introuvable.']);
        }
        if ($service['source'] === 'custom') {
            $s['custom'][$code]['enabled'] = $enabled;
        } else {
            $s['hidden'] = array_values(array_diff($s['hidden'], [$code]));
            if (!$enabled) {
                $s['hidden'][] = $code;
            }
        }
        if (!$enabled && $s['default'] === $code) {
            $s['default'] = '';
        }
        $config['services'] = SseWantedServices::normalizeSettings($s);

        return self::ok($config, $enabled ? 'Service proposé dans le formulaire.' : 'Service retiré du formulaire : les avis déjà posés restent lisibles.', $code);
    }

    /**
     * Supprime un service créé par la communauté. Un service porté par des avis est désactivé, jamais supprimé.
     *
     * @param array<string, mixed> $config
     * @return Result
     */
    public static function delete(array $config, string $code, int $usedBy): array
    {
        $code = strtoupper($code);
        $s = SseWantedServices::normalizeSettings($config['services'] ?? []);
        if (!isset($s['custom'][$code])) {
            return self::fail($config, ['Seuls les services créés par votre communauté se suppriment ; masquez les autres.']);
        }
        if ($usedBy > 0) {
            $r = self::setEnabled($config, $code, false);
            $r['message'] = 'Ce service porte ' . $usedBy . ' avis : il est désactivé et reste lisible, il n’est pas supprimé.';

            return $r;
        }
        unset($s['custom'][$code]);
        $s['order'] = array_values(array_diff($s['order'], [$code]));
        $s['hidden'] = array_values(array_diff($s['hidden'], [$code]));
        if ($s['default'] === $code) {
            $s['default'] = '';
        }
        $config['services'] = SseWantedServices::normalizeSettings($s);

        return self::ok($config, 'Service supprimé.', $code);
    }

    /**
     * @param array<string, mixed> $config
     * @param list<string> $codes ordre voulu (les codes inconnus sont ignorés, les autres suivent)
     * @return Result
     */
    public static function reorder(array $config, array $codes): array
    {
        $s = SseWantedServices::normalizeSettings($config['services'] ?? []);
        $s['order'] = array_values(array_filter(array_map(static fn (mixed $c): string => strtoupper(trim((string) $c)), $codes), static fn (string $c): bool => $c !== ''));
        $config['services'] = SseWantedServices::normalizeSettings($s);

        return self::ok($config, 'Ordre enregistré.');
    }

    /**
     * Déplace un service d'un cran (« up » ou « down ») dans la liste affichée.
     *
     * @param array<string, mixed> $config
     * @return Result
     */
    public static function move(array $config, string $code, string $direction): array
    {
        $code = strtoupper($code);
        $codes = array_keys(SseWantedServices::all($config));
        $i = array_search($code, $codes, true);
        if (!is_int($i)) {
            return self::fail($config, ['Service introuvable.']);
        }
        $j = $direction === 'up' ? $i - 1 : $i + 1;
        if ($j >= 0 && $j < count($codes)) {
            [$codes[$i], $codes[$j]] = [$codes[$j], $codes[$i]];
        }

        return self::reorder($config, $codes);
    }

    /**
     * @param array<string, mixed> $config
     * @return Result
     */
    public static function setDefault(array $config, string $code): array
    {
        $code = strtoupper($code);
        $s = SseWantedServices::normalizeSettings($config['services'] ?? []);
        if ($code === '') {
            $s['default'] = '';
            $config['services'] = SseWantedServices::normalizeSettings($s);

            return self::ok($config, 'Aucun service par défaut : le formulaire reprend le dernier service utilisé.');
        }
        $avail = SseWantedServices::available($config);
        if (!isset($avail[$code])) {
            return self::fail($config, ['Ce service n’est pas proposé : activez-le d’abord.']);
        }
        $s['default'] = $code;
        $config['services'] = SseWantedServices::normalizeSettings($s);

        return self::ok($config, 'Service par défaut enregistré.', $code);
    }

    /**
     * Export JSON des services créés par la communauté (pour les partager).
     *
     * @param array<string, mixed> $config
     */
    public static function export(array $config): string
    {
        $s = SseWantedServices::normalizeSettings($config['services'] ?? []);
        $rows = [];
        foreach ($s['custom'] as $svc) {
            unset($svc['source'], $svc['enabled']);
            $rows[] = $svc;
        }

        return (string) json_encode([
            'format' => SseWantedServices::EXPORT_FORMAT,
            'version' => 1,
            'exported_at' => gmdate('Y-m-d\TH:i:s\Z'),
            'services' => $rows,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }

    /**
     * Import JSON : chaque service passe par la même validation que la création (noms réels refusés). Les services valides sont
     * ajoutés ; un service déjà présent (même nom) est ignoré ; les refusés sont listés avec leur raison.
     *
     * @param array<string, mixed> $config
     * @return Result&array{added: int, skipped: int, rejected: list<array{name: string, reasons: list<string>}>}
     */
    public static function import(array $config, string $json): array
    {
        $base = ['added' => 0, 'skipped' => 0, 'rejected' => []];
        if (strlen($json) > self::IMPORT_MAX_BYTES) {
            return self::fail($config, ['Fichier trop volumineux (200 Ko au plus).']) + $base;
        }
        $data = json_decode($json, true);
        if (!is_array($data) || ($data['format'] ?? '') !== SseWantedServices::EXPORT_FORMAT || !is_array($data['services'] ?? null)) {
            return self::fail($config, ['Fichier illisible : choisissez un export de services de COMSPEC (format « ' . SseWantedServices::EXPORT_FORMAT . ' »).']) + $base;
        }
        $added = 0;
        $skipped = 0;
        $rejected = [];
        foreach (array_slice($data['services'], 0, SseWantedServices::MAX_CUSTOM) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $name = C::line($row['fr'] ?? '', 120);
            $exists = false;
            foreach (SseWantedServices::all($config) as $other) {
                if ($other['source'] === 'custom' && $name !== '' && mb_strtolower($other['fr']) === mb_strtolower($name)) {
                    $exists = true;
                    break;
                }
            }
            if ($exists) {
                $skipped++;
                continue;
            }
            $checked = SseWantedServices::validate($row, $config, null, is_string($row['code'] ?? null) ? $row['code'] : null);
            if ($checked['service'] === null || $checked['errors'] !== []) {
                $rejected[] = ['name' => $name !== '' ? $name : '—', 'reasons' => $checked['errors']];
                continue;
            }
            $r = self::store($config, $checked['service'], '');
            if ($r['ok']) {
                $config = $r['config'];
                $added++;
            }
        }
        $message = $added . ' service(s) importé(s)' . ($skipped > 0 ? ', ' . $skipped . ' déjà présent(s)' : '') . ($rejected !== [] ? ', ' . count($rejected) . ' refusé(s)' : '') . '.';

        return ['ok' => $added > 0 || $rejected === [], 'config' => $config, 'errors' => [], 'message' => $message, 'code' => null, 'added' => $added, 'skipped' => $skipped, 'rejected' => $rejected];
    }

    /**
     * @param array<string, mixed> $config
     * @param array<string, mixed> $service
     * @return Result
     */
    private static function store(array $config, array $service, string $message): array
    {
        $s = SseWantedServices::normalizeSettings($config['services'] ?? []);
        $code = (string) $service['code'];
        $service['enabled'] = array_key_exists('enabled', $service) ? (bool) $service['enabled'] : true;
        $s['custom'][$code] = $service;
        $config['services'] = SseWantedServices::normalizeSettings($s);
        if (!isset($config['services']['custom'][$code])) {
            return self::fail($config, ['Le service n’a pas pu être enregistré (limite atteinte ou code refusé).']);
        }

        return self::ok($config, $message, $code);
    }

    /**
     * @param array<string, mixed> $config
     * @return Result
     */
    private static function ok(array $config, string $message, ?string $code = null): array
    {
        return ['ok' => true, 'config' => $config, 'errors' => [], 'message' => $message, 'code' => $code];
    }

    /**
     * @param array<string, mixed> $config
     * @param list<string> $errors
     * @return Result
     */
    private static function fail(array $config, array $errors): array
    {
        return ['ok' => false, 'config' => $config, 'errors' => $errors, 'message' => '', 'code' => null];
    }
}
