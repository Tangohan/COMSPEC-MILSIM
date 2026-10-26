<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Distribution des mods par le Steam Workshop (le site n'héberge plus d'archive ni de modpack).
 *
 * - Objets publiés par l'équipe COMSPEC : COMSPEC ATAK Native (pack Overwatch inclus, mod/UptoDate/@COMSPECOverwatch/
 *   meta.cpp publishedid), et SSE quand son identifiant est connu (variable ATHENA_WORKSHOP_SSE_ID).
 * - Dépendances : CBA_A3 (obligatoire), ACE3 et ACRE2 (recommandés).
 * - Par communauté : une collection Steam (le modset de la communauté) et une consigne libre, réglées dans
 *   Back-office › Ressources › Mod ATAK, enregistrées dans storage/cache/steam-workshop/t{id}.json.
 */
final class SteamWorkshop
{
    /** Fiche Workshop « COMSPEC ATAK Native » (mod/UptoDate/docs/compilation-et-publication.md). */
    public const ATAK_NATIVE_ID = '3684656708';

    public const CBA_ID = '450814997';
    public const ACE_ID = '463939057';
    public const ACRE_ID = '751965892';

    /**
     * Objets à installer, dans l'ordre d'abonnement conseillé.
     *
     * @return list<array{key: string, id: string, name: string, role: string, required: bool, description: string}>
     */
    public static function items(): array
    {
        $atak = self::envId('ATHENA_WORKSHOP_ATAK_ID') ?? self::ATAK_NATIVE_ID;
        $items = [
            ['key' => 'atak', 'id' => $atak, 'name' => 'COMSPEC ATAK Native', 'role' => 'Le mod Athena', 'required' => true,
                'description' => 'Téléphone ATAK en jeu, tablette Athena et liaison Overwatch avec la Tacmap : positions, marqueurs, messages, comptes rendus.'],
            ['key' => 'cba', 'id' => self::CBA_ID, 'name' => 'CBA_A3', 'role' => 'Dépendance', 'required' => true,
                'description' => 'Bibliothèque commune aux mods Arma 3 : réglages, touches, événements. Steam la propose automatiquement avec le mod.'],
            ['key' => 'ace', 'id' => self::ACE_ID, 'name' => 'ACE3', 'role' => 'Recommandé', 'required' => false,
                'description' => 'Menus d’interaction utilisés par le téléphone et le renseignement (SSE).'],
            ['key' => 'acre', 'id' => self::ACRE_ID, 'name' => 'ACRE2', 'role' => 'Recommandé', 'required' => false,
                'description' => 'Radios réalistes : fréquences et relais repris par le pont radio.'],
        ];
        $sse = self::envId('ATHENA_WORKSHOP_SSE_ID');
        if ($sse !== null) {
            array_splice($items, 1, 0, [[
                'key' => 'sse', 'id' => $sse, 'name' => 'COMSPEC SSE', 'role' => 'Module', 'required' => false,
                'description' => 'Renseignement interpersonnel et exploitation de site, relié au portail SSE.',
            ]]);
        }

        return $items;
    }

    /** Page web de l'objet. */
    public static function webUrl(string $id): string
    {
        return $id === '' ? '' : 'https://steamcommunity.com/sharedfiles/filedetails/?id=' . rawurlencode($id);
    }

    /** Même page ouverte dans le client Steam (bouton « S'abonner »). */
    public static function clientUrl(string $id): string
    {
        return $id === '' ? '' : 'steam://url/CommunityFilePage/' . rawurlencode($id);
    }

    /**
     * Identifiant Workshop d'une saisie : identifiant seul, lien de fiche ou de collection, lien steam://.
     * '' : vide ; null : invalide.
     */
    public static function parseId(string $raw): ?string
    {
        $s = trim($raw);
        if ($s === '') {
            return '';
        }
        if (preg_match('/^[0-9]{6,15}$/', $s) === 1) {
            return $s;
        }
        if (preg_match('~^(?:https?://(?:www\.)?steamcommunity\.com/(?:sharedfiles|workshop)/filedetails/?\?(?:[^#]*&)?id=([0-9]{6,15})|steam://url/CommunityFilePage/([0-9]{6,15}))(?:[&#/].*)?$~i', $s, $m) === 1) {
            return ($m[1] ?? '') !== '' ? $m[1] : $m[2];
        }

        return null;
    }

    /**
     * Réglages de la communauté : collection Steam (modset) et consigne affichée sur /atak/mod.
     *
     * @return array{collection_id: string, collection_name: string, notes: string, updated_at: string}
     */
    public static function tenant(int $tenantId): array
    {
        $out = ['collection_id' => '', 'collection_name' => '', 'notes' => '', 'updated_at' => ''];
        if ($tenantId < 1) {
            return $out;
        }
        $raw = @file_get_contents(self::path($tenantId));
        $data = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($data)) {
            return $out;
        }
        foreach ($out as $k => $_) {
            $out[$k] = trim((string) ($data[$k] ?? ''));
        }
        if (self::parseId($out['collection_id']) === null) {
            $out['collection_id'] = '';
        }

        return $out;
    }

    /**
     * @param array{collection_id?: string, collection_name?: string, notes?: string} $in
     * @return array{ok: bool, error?: string}
     */
    public static function saveTenant(int $tenantId, array $in): array
    {
        $id = self::parseId((string) ($in['collection_id'] ?? ''));
        if ($id === null) {
            return ['ok' => false, 'error' => 'Lien de collection Steam non reconnu : collez l’adresse de la collection (steamcommunity.com/sharedfiles/filedetails/?id=…) ou son numéro.'];
        }
        $payload = [
            'collection_id' => $id,
            'collection_name' => mb_substr(trim((string) ($in['collection_name'] ?? '')), 0, 120),
            'notes' => mb_substr(trim(str_replace("\r\n", "\n", (string) ($in['notes'] ?? ''))), 0, 2000),
            'updated_at' => gmdate('c'),
        ];
        $dir = dirname(self::path($tenantId));
        if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
            return ['ok' => false, 'error' => 'Enregistrement impossible pour le moment.'];
        }
        $ok = @file_put_contents(self::path($tenantId), json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);

        return $ok === false ? ['ok' => false, 'error' => 'Enregistrement impossible pour le moment.'] : ['ok' => true];
    }

    /**
     * Résumé pour le tableau de bord et les pages membre : mod Athena, collection de la communauté, guide.
     *
     * @return array{title: string, has_collection: bool, collection_web: string, collection_client: string,
     *   atak_web: string, atak_client: string, guide_url: string, version: string, notes: string}
     */
    public static function modset(int $tenantId): array
    {
        $t = self::tenant($tenantId);
        $atak = self::items()[0]['id'];
        $version = '';
        try {
            $version = (string) (AtakReleaseInfo::latest()['atak_native'] ?? '');
        } catch (\Throwable) {
        }

        return [
            'title' => $t['collection_name'] !== '' ? $t['collection_name'] : 'Mods de la communauté',
            'has_collection' => $t['collection_id'] !== '',
            'collection_web' => self::webUrl($t['collection_id']),
            'collection_client' => self::clientUrl($t['collection_id']),
            'atak_web' => self::webUrl($atak),
            'atak_client' => self::clientUrl($atak),
            'guide_url' => url('atak/mod'),
            'version' => $version,
            'notes' => $t['notes'],
        ];
    }

    /** Ancienne archive hébergée par le site (avant le Workshop), à supprimer depuis le back-office. */
    public static function legacyArchivePath(int $tenantId): string
    {
        return base_path('storage/atak-mod/' . max(0, $tenantId) . '/comspec-overwatch.zip');
    }

    private static function path(int $tenantId): string
    {
        return base_path('storage/cache/steam-workshop/t' . $tenantId . '.json');
    }

    private static function envId(string $name): ?string
    {
        $v = getenv($name);
        if (!is_string($v) || trim($v) === '') {
            return null;
        }
        $id = self::parseId($v);

        return $id !== null && $id !== '' ? $id : null;
    }
}
