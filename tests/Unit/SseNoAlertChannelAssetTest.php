<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Le SSE (papiers, IA réalistes, IA RP, Zeus…) n'affiche jamais de texte par les canaux d'alerte d'Arma ou de ZEN :
 * ni hint, ni chat, ni titleText / cutText avec un texte, ni notification du jeu, ni texte dynamique, ni message du
 * curateur, ni bandeau de ZEN (zen_common_fnc_showMessage : une ligne en haut de l'écran, coupée aux bords, cf. les
 * « vrais papiers » du Zeus). L'information va dans une fenêtre, dans le téléphone ATAK (comspec_sse_fnc_notify) ou au
 * RPT (diag_log, comspec_sse_fnc_log).
 * Complète OverwatchVanillaChatSilentAssetTest (même nettoyage : commentaires et chaînes retirés avant la recherche).
 */
final class SseNoAlertChannelAssetTest extends TestCase
{
    private const SSE = 'mod/@COMSPEC_SSE/addons';

    /** @var list<string> Commandes et fonctions d'alerte, proscrites dans le SSE. */
    private const ALERTS = [
        'hint', 'hintSilent', 'hintC',
        'systemChat', 'sideChat', 'globalChat', 'groupChat', 'vehicleChat', 'commandChat', 'customChat',
        'titleText', 'titleRsc', 'titleCut', 'cutRsc',
        'BIS_fnc_showNotification', 'BIS_fnc_dynamicText', 'BIS_fnc_showCuratorFeedbackMessage', 'BIS_fnc_showSubtitle',
        'BIS_fnc_typeText', 'BIS_fnc_typeText2', 'BIS_fnc_infoText', 'BIS_fnc_textTiles', 'BIS_fnc_curatorHint',
        'BIS_fnc_advHint', 'BIS_fnc_guiMessage', 'BIS_fnc_3DENNotification',
        'zen_common_fnc_showMessage',
        'ace_common_fnc_displayText', 'ace_common_fnc_displayTextStructured', 'ace_common_fnc_displayTextPicture',
        'CBA_fnc_notify',
    ];

    /**
     * Liste blanche explicite (fichier => fonctions), chaque entrée justifiée : aucun de ces cas n'est une alerte en partie.
     *
     * @var array<string, list<string>>
     */
    private const ALLOWED = [
        // Bouton « ? » de la page IA de la zone : la page d'aide demandée s'ouvre en fenêtre modale posée sur la fenêtre de
        // l'éditeur (contenu de fenêtre, à la demande), pas une alerte.
        'mod/@COMSPEC_SSE/addons/editor/functions/fn_editorAi.sqf' => ['BIS_fnc_guiMessage'],
        // Éditeur de mission Eden (pas de partie en cours) : confirmation native d'Eden, déjà tolérée par
        // OverwatchVanillaChatSilentAssetTest.
        'mod/@COMSPEC_SSE/addons/editor/functions/fn_editor.sqf' => ['BIS_fnc_3DENNotification'],
        'mod/@COMSPEC_SSE/addons/editor/functions/fn_editorPlace.sqf' => ['BIS_fnc_3DENNotification'],
    ];

    public function testSseAddonsCallNoAlertChannel(): void
    {
        $hits = [];
        foreach ($this->files(['sqf']) as [$rel, $src]) {
            $code = $this->strip($src);
            $found = $this->find($code);
            // remoteExec ["hint", …] : le nom de la fonction est une chaîne, cherché dans le texte brut.
            if (preg_match_all('/remoteExec(?:Call)?\s*\[\s*"([A-Za-z_0-9]+)"/', $src, $m) > 0) {
                foreach ($m[1] as $fn) {
                    if (in_array(strtolower($fn), array_map('strtolower', self::ALERTS), true)) {
                        $found[] = $fn;
                    }
                }
            }
            foreach (array_diff(array_unique($found), self::ALLOWED[$rel] ?? []) as $cmd) {
                $hits[] = $rel . ' → ' . $cmd;
            }
        }
        self::assertSame([], $hits, "Canaux d'alerte encore utilisés dans le SSE :\n" . implode("\n", $hits));
    }

    public function testSseConfigsCallNoAlertChannel(): void
    {
        // Configs (actions, onLoad…) : le code est dans des chaînes ; seules les fonctions nommées sont cherchées.
        $hits = [];
        $fns = array_values(array_filter(self::ALERTS, static fn (string $a): bool => str_contains($a, '_fnc_')));
        foreach ($this->files(['hpp', 'cpp', 'inc', 'h']) as [$rel, $src]) {
            $code = preg_replace('~//[^\n]*|/\*.*?\*/~s', ' ', $src) ?? $src;
            foreach ($fns as $fn) {
                if (preg_match('/\b' . preg_quote($fn, '/') . '\b/i', $code) === 1) {
                    $hits[] = $rel . ' → ' . $fn;
                }
            }
            if (preg_match('/\b(hint|hintSilent|systemChat|titleText)\s+(format|parseText|str|\'|"")/i', $code, $m) === 1) {
                $hits[] = $rel . ' → ' . $m[1];
            }
        }
        self::assertSame([], $hits, "Canaux d'alerte dans les configs du SSE :\n" . implode("\n", $hits));
    }

    public function testAllowListOnlyNamesExistingCalls(): void
    {
        // Une entrée de la liste blanche qui ne sert plus est retirée (pas de tolérance fantôme).
        foreach (self::ALLOWED as $rel => $cmds) {
            $path = dirname(__DIR__, 2) . '/' . $rel;
            self::assertFileExists($path);
            $code = $this->strip((string) file_get_contents($path));
            foreach ($cmds as $cmd) {
                self::assertMatchesRegularExpression('/\b' . preg_quote($cmd, '/') . '\b/', $code, $rel . ' : ' . $cmd . ' toléré mais absent');
            }
        }
    }

    public function testZeusFeedbackGoesToPhoneAndRptNotZenBanner(): void
    {
        $notify = (string) file_get_contents(dirname(__DIR__, 2) . '/' . self::SSE . '/zeus/functions/fn_zeusNotify.sqf');
        $code = $this->strip($notify);
        self::assertStringNotContainsString('zen_common_fnc_showMessage', $code);
        self::assertStringNotContainsString('BIS_fnc_showCuratorFeedbackMessage', $code);
        self::assertStringContainsString('comspec_sse_fnc_notify', $code);
        self::assertStringContainsString('comspec_sse_fnc_log', $code);
    }

    public function testStripDropsStringsAndComments(): void
    {
        $code = $this->strip("// hint x\n_m set [\"hint\", 1]; /* systemChat */ isNil \"zen_common_fnc_showMessage\";\nhintSilent _t;");
        self::assertSame(['hintSilent'], $this->find($code));
        self::assertSame(['cutText'], $this->find($this->strip('x cutText ["Texte", "PLAIN"];')));
        self::assertSame([], $this->find($this->strip('x cutText ["", "BLACK OUT", 1.5, true];')));
    }

    /**
     * @param list<string> $exts
     * @return list<array{0: string, 1: string}>
     */
    private function files(array $exts): array
    {
        $root = dirname(__DIR__, 2);
        $dir = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, self::SSE);
        $out = [];
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
        /** @var SplFileInfo $file */
        foreach ($it as $file) {
            if (!$file->isFile() || !in_array(strtolower($file->getExtension()), $exts, true)) {
                continue;
            }
            $path = $file->getPathname();
            $out[] = [str_replace('\\', '/', substr($path, strlen($root) + 1)), (string) file_get_contents($path)];
        }

        return $out;
    }

    /** @return list<string> */
    private function find(string $code): array
    {
        $found = [];
        foreach (self::ALERTS as $cmd) {
            if (preg_match('/\b' . preg_quote($cmd, '/') . '\b/i', $code) === 1) {
                $found[] = $cmd;
            }
        }
        // cutText n'est une alerte qu'avec un texte (cutText ["", "BLACK OUT"] : fondu au noir du sac de tête).
        if (preg_match('/\bcutText\s*\[\s*"S"/i', $code) === 1) {
            $found[] = 'cutText';
        }

        return $found;
    }

    /** Commentaires retirés ; chaîne vide -> "" ; chaîne non vide -> "S" (guillemets doublés = échappement SQF). */
    private function strip(string $src): string
    {
        $out = '';
        $len = strlen($src);
        $i = 0;
        while ($i < $len) {
            $c = $src[$i];
            $next = $i + 1 < $len ? $src[$i + 1] : '';
            if ($c === '/' && $next === '/') {
                while ($i < $len && $src[$i] !== "\n") {
                    $i++;
                }
                continue;
            }
            if ($c === '/' && $next === '*') {
                $end = strpos($src, '*/', $i + 2);
                $i = $end === false ? $len : $end + 2;
                $out .= ' ';
                continue;
            }
            if ($c === '"' || $c === "'") {
                $j = $i + 1;
                $empty = true;
                while ($j < $len) {
                    if ($src[$j] === $c) {
                        if ($j + 1 < $len && $src[$j + 1] === $c) {
                            $empty = false;
                            $j += 2;
                            continue;
                        }
                        break;
                    }
                    $empty = false;
                    $j++;
                }
                $out .= $empty ? '""' : '"S"';
                $i = $j + 1;
                continue;
            }
            $out .= $c;
            $i++;
        }

        return $out;
    }
}
