<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Mod de langue prêt à jouer, fabriqué par le serveur pour le téléchargement direct du Workshop des langues
 * (/traduire-atak/workshop/{id}/telecharger). Mêmes fichiers que buildTxt / buildConfig / buildPbo de
 * public/assets/js/atak-translate.js (PBO non compressé, en-tête « prefix », SHA-1 final ; zip deflate).
 *
 * Le fichier .txt suit le modèle ACTUEL du mod : les phrases ajoutées depuis la version du pack sont écrites vides
 * (affichées en anglais en jeu) et une traduction devenue incompatible (marqueurs %N changés) est retirée, pour
 * qu'un vieux pack ne casse jamais un texte du téléphone.
 */
final class AtakTranslationModBuilder
{
    /**
     * @param list<array{0: string, 1: string, 2: string}> $entries [clé, français, anglais, …] dans l'ordre du modèle
     * @param array<string, string> $map clé => traduction
     * @return array{text: string, written: int, skipped: int}
     */
    public static function buildTxt(array $entries, array $map): array
    {
        $lines = [];
        $written = 0;
        $skipped = 0;
        foreach ($entries as $e) {
            $t = $map[$e[0]] ?? '';
            if ($t !== '' && AtakTranslationCheck::check($e[1], $t)['errors'] !== []) {
                $t = '';
                $skipped++;
            }
            if ($t !== '') {
                $written++;
            }
            $lines[] = '[' . self::q($e[1]) . ', ' . self::q($t) . ', ' . self::q($e[2]) . ']';
        }

        return ['text' => "[\n" . implode(",\n", $lines) . "\n]\n", 'written' => $written, 'skipped' => $skipped];
    }

    /**
     * @param array{code: string, name: string, game: string, author: string, version: string, site: string} $o
     */
    public static function buildConfig(array $o): string
    {
        $games = $o['game'] !== '' ? '{"' . self::cfg($o['game']) . '"}' : '{}';

        return '// COMSPEC ATAK translation mod: ' . self::cfg($o['name']) . ' (' . $o['code'] . '). Made with ' . $o['site'] . "\n"
            . '// Template: COMSPEC ATAK Native ' . ($o['version'] !== '' ? $o['version'] : '?') . ".\n"
            . "class CfgPatches {\n"
            . '    class comspec_atak_lang_' . $o['code'] . " {\n"
            . '        name = "COMSPEC ATAK - ' . self::cfg($o['name']) . "\";\n"
            . '        author = "' . self::cfg($o['author'] !== '' ? $o['author'] : 'COMSPEC community') . "\";\n"
            . "        requiredVersion = 2.14;\n"
            . "        // Loaded after COMSPEC ATAK Native.\n"
            . "        requiredAddons[] = {\"comspec_atak_native_main\"};\n"
            . "        units[] = {};\n"
            . "        weapons[] = {};\n"
            . "    };\n"
            . "};\n"
            . "class CfgComspecLanguages {\n"
            . '    class ' . $o['code'] . " {\n"
            . '        name = "' . self::cfg($o['name']) . "\";\n"
            . '        file = "\\comspec_atak_lang_' . $o['code'] . '\\' . $o['code'] . ".txt\";\n"
            . '        gameLanguage[] = ' . $games . ";\n"
            . "    };\n"
            . "};\n";
    }

    /**
     * PBO non compressé : octet nul + en-tête « Vers » + prefix, entrées, entrée vide, données, 0x00 + SHA-1.
     *
     * @param list<array{name: string, data: string}> $files
     */
    public static function buildPbo(string $prefix, array $files, int $time): string
    {
        $out = "\0" . pack('V5', 0x56657273, 0, 0, 0, 0) . "prefix\0" . $prefix . "\0\0";
        foreach ($files as $f) {
            $len = strlen($f['data']);
            $out .= $f['name'] . "\0" . pack('V5', 0, $len, 0, $time, $len);
        }
        $out .= "\0" . pack('V5', 0, 0, 0, 0, 0);
        foreach ($files as $f) {
            $out .= $f['data'];
        }

        return $out . "\0" . sha1($out, true);
    }

    /**
     * Zip (deflate quand il fait gagner de la place).
     *
     * @param list<array{name: string, data: string}> $files
     */
    public static function buildZip(array $files, int $time): string
    {
        $d = getdate($time);
        $dosTime = ($d['hours'] << 11) | ($d['minutes'] << 5) | intdiv($d['seconds'], 2);
        $dosDate = ((max(1980, $d['year']) - 1980) << 9) | ($d['mon'] << 5) | $d['mday'];
        $local = '';
        $central = '';
        foreach ($files as $f) {
            $raw = $f['data'];
            $crc = crc32($raw);
            $comp = $raw === '' ? false : gzdeflate($raw, 9);
            $method = $comp !== false && strlen($comp) < strlen($raw) ? 8 : 0;
            $data = $method === 8 ? (string) $comp : $raw;
            $name = $f['name'];
            $offset = strlen($local);
            $local .= pack('VvvvvvVVVvv', 0x04034b50, 20, 0x0800, $method, $dosTime, $dosDate, $crc, strlen($data), strlen($raw), strlen($name), 0) . $name . $data;
            $central .= pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0x0800, $method, $dosTime, $dosDate, $crc, strlen($data), strlen($raw), strlen($name), 0, 0, 0, 0, 0, $offset) . $name;
        }
        $n = count($files);

        return $local . $central . pack('VvvvvVVv', 0x06054b50, 0, 0, $n, $n, strlen($central), strlen($local), 0);
    }

    /**
     * Dossier complet du mod pour un joueur : @COMSPEC_ATAK_Lang_xx/{addons/*.pbo, mod.cpp, README.txt}.
     *
     * @param list<array{0: string, 1: string, 2: string}> $entries
     * @param array<string, string> $map
     * @param array{code: string, name: string, game: string, author: string, version: string, site: string, release: int, page: string} $o
     * @return array{zip: string, file: string, written: int, skipped: int}
     */
    public static function playerZip(array $entries, array $map, array $o, int $time): array
    {
        $code = $o['code'];
        $prefix = 'comspec_atak_lang_' . $code;
        $folder = '@COMSPEC_ATAK_Lang_' . $code . '/';
        $txt = self::buildTxt($entries, $map);
        $pbo = self::buildPbo($prefix, [
            ['name' => 'config.cpp', 'data' => self::buildConfig($o)],
            ['name' => $code . '.txt', 'data' => $txt['text']],
        ], $time);
        $label = 'COMSPEC ATAK - ' . self::cfg($o['name']);
        $mod = 'name = "' . $label . "\";\n"
            . 'author = "' . self::cfg($o['author'] !== '' ? $o['author'] : 'COMSPEC community') . "\";\n"
            . 'tooltip = "' . $label . ' v' . $o['release'] . "\";\n"
            . 'overview = "' . self::cfg($o['name']) . ' translation of the COMSPEC ATAK Native phone, version ' . $o['release'] . ' (template ' . self::cfg($o['version']) . ").\";\n";
        $readme = $label . ' (' . $code . ') - v' . $o['release'] . "\r\n"
            . 'Downloaded from ' . $o['page'] . "\r\n"
            . 'Built for COMSPEC ATAK Native ' . $o['version'] . ': ' . $txt['written'] . ' translated sentences, the rest stays in English.' . "\r\n\r\n"
            . "EN\r\n"
            . '1. Unzip ' . rtrim($folder, '/') . " next to your other mods (for example in the Arma 3 folder).\r\n"
            . "2. Arma 3 Launcher > Mods > ... > Add local mod, pick the folder, enable it with COMSPEC ATAK Native and CBA_A3.\r\n"
            . "3. In game: phone > Settings > Language (or Esc > Options > Addon Options > COMSPEC > Language).\r\n"
            . "This addon is not signed: on a server that checks signatures, use the Steam Workshop version instead.\r\n\r\n"
            . "FR\r\n"
            . '1. Décompressez ' . rtrim($folder, '/') . " à côté de vos autres mods (par exemple dans le dossier d'Arma 3).\r\n"
            . "2. Launcher Arma 3 > Mods > ... > Ajouter un mod local, choisissez le dossier, activez-le avec COMSPEC ATAK Native et CBA_A3.\r\n"
            . "3. En jeu : téléphone > Réglages > Langue (ou Échap > Options > Options des addons > COMSPEC > Langue).\r\n"
            . "Cet addon n'est pas signé : sur un serveur qui vérifie les signatures, prenez la version Steam Workshop.\r\n";
        $zip = self::buildZip([
            ['name' => $folder . 'addons/' . $prefix . '.pbo', 'data' => $pbo],
            ['name' => $folder . 'mod.cpp', 'data' => $mod],
            ['name' => $folder . 'README.txt', 'data' => $readme],
        ], $time);

        return ['zip' => $zip, 'file' => 'COMSPEC_ATAK_Lang_' . $code . '_v' . $o['release'] . '.zip', 'written' => $txt['written'], 'skipped' => $txt['skipped']];
    }

    private static function q(string $s): string
    {
        return '"' . str_replace('"', '""', $s) . '"';
    }

    private static function cfg(string $s): string
    {
        return str_replace('"', '""', (string) preg_replace('/[\r\n]+/', ' ', $s));
    }
}
