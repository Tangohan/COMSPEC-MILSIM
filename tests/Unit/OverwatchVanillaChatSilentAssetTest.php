<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Les mods COMSPEC n'écrivent jamais dans le chat d'Arma ni par-dessus l'écran du jeu avec l'interface d'Arma
 * (notifications CfgNotifications, hint, titleText, cutText avec un texte, message du curateur…) : l'information
 * va dans la fenêtre ouverte, dans le téléphone ATAK (comspec_atak_native_fnc_notify) ou au RPT.
 * Les chaînes littérales et les commentaires sont retirés avant la recherche : une clé de hashmap "hint" ou un
 * identifiant « hint » dans une phrase ne comptent pas.
 */
final class OverwatchVanillaChatSilentAssetTest extends TestCase
{
    /** @var list<string> */
    private const CHAT_COMMANDS = [
        'systemChat',
        'sideChat',
        'globalChat',
        'groupChat',
        'vehicleChat',
        'commandChat',
        'customChat',
    ];

    /** @var list<string> Alertes de l'interface d'Arma, proscrites. BIS_fnc_3DENNotification (éditeur Eden) est tolérée. */
    private const ALERT_COMMANDS = [
        'BIS_fnc_showNotification',
        'hint',
        'hintSilent',
        'hintC',
        'titleText',
        'titleRsc',
        'cutRsc',
        'BIS_fnc_showCuratorFeedbackMessage',
    ];

    public function testPollStillDeliversToAtakInboxNotVanillaChat(): void
    {
        $poll = $this->readRepo(
            'mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_pollChatMessages.sqf'
        );

        self::assertStringContainsString('COMSPEC_Athena_AlertInbox', $poll);
        self::assertStringContainsString('Iceman_ATAK_Group_messages', $poll);
        self::assertStringContainsString('_fnPushIcemanGroup', $poll);
        self::assertStringContainsString('(TOC)', $poll);
        $this->assertNoVanillaChatCalls($poll, 'fn_pollChatMessages.sqf');
    }

    public function testScreenNotificationsStayOffByDefaultAndDoNotMentionGameChat(): void
    {
        $pre = $this->readRepo(
            'mod/UptoDate/Sources/comspec-overwatch-addons/connect/XEH_preInit.sqf'
        );
        $announce = $this->readRepo(
            'mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_announce.sqf'
        );

        self::assertMatchesRegularExpression(
            '/"comspec_overwatch_screen_notifications".{0,800}?\["COMSPEC", "Overwatch"\], false/s',
            $pre
        );
        self::assertStringContainsString('jamais écrit dans le chat du jeu', $pre);
        $this->assertNoVanillaChatCalls($announce, 'fn_announce.sqf');
        self::assertStringContainsString('fnc_pushHtmlAlert', $announce);
    }

    public function testOverwatchAndSseSourcesDoNotInjectVanillaChat(): void
    {
        $hits = [];
        foreach ([
            'mod/UptoDate/Sources/comspec-overwatch-addons',
            'mod/@COMSPEC_SSE/addons',
            'mod/COMSPEC_ATAK_Native/Sources/addons/main',
        ] as $rel) {
            $hits = array_merge($hits, $this->scanRoot($rel, self::CHAT_COMMANDS, []));
        }

        self::assertSame([], $hits, "Vanilla chat injectors still present:\n" . implode("\n", $hits));
    }

    public function testSseSourcesShowNoArmaUiAlert(): void
    {
        $pending = [];
        $hits = $this->scanRoot('mod/@COMSPEC_SSE/addons', self::ALERT_COMMANDS, $pending);

        self::assertSame([], $hits, "Arma UI alerts still present in SSE:\n" . implode("\n", $hits));
    }

    public function testAtakNativeSourcesShowNoArmaUiAlert(): void
    {
        // cutRsc : calques HUD du mod (téléphone, montre, GPS, badge relais, caméra) = interface du mod, pas une alerte.
        $commands = array_values(array_diff(self::ALERT_COMMANDS, ['cutRsc']));
        // Capture de la carte (outil d'administration, téléphone fermé pendant la capture) : ligne d'avancement en bas
        // d'écran par cutText, seule information visible pendant les prises de vue.
        $pending = [
            'mod/COMSPEC_ATAK_Native/Sources/addons/main/functions/camera/fn_mapCapture.sqf' => ['cutText'],
        ];
        $hits = $this->scanRoot('mod/COMSPEC_ATAK_Native/Sources/addons/main', $commands, $pending);

        self::assertSame([], $hits, "Arma UI alerts still present in native ATAK:\n" . implode("\n", $hits));
    }

    public function testOverwatchSourcesShowNoArmaUiAlert(): void
    {
        // cutRsc : calques HUD du mod (diagnostic, chrome Ecoti, interface Mavic) = interface du mod, pas une alerte.
        $commands = array_values(array_diff(self::ALERT_COMMANDS, ['cutRsc']));
        $hits = $this->scanRoot('mod/UptoDate/Sources/comspec-overwatch-addons', $commands, []);

        self::assertSame([], $hits, "Arma UI alerts still present in Overwatch:\n" . implode("\n", $hits));
    }

    public function testStripKeepsCodeAndDropsStringsAndComments(): void
    {
        $src = "// hint in a comment\n/* hintSilent\n in block */\n_cfg set [\"hint\", \"a\"\"b\"];\n"
            . "private _t = 'l''hint';\nx cutText [\"\", \"PLAIN\"];\ny cutText [\"Texte\", \"PLAIN\"];\nhintSilent _t;\n";
        $code = $this->stripSqfCommentsAndStrings($src);

        self::assertDoesNotMatchRegularExpression('/\bhint\b/', $code);
        self::assertMatchesRegularExpression('/\bhintSilent\b/', $code);
        self::assertSame([], $this->findAlertCommands($this->stripSqfCommentsAndStrings('x cutText ["", "PLAIN"];'), []));
        self::assertSame(['cutText'], $this->findAlertCommands($this->stripSqfCommentsAndStrings('y cutText ["Texte", "PLAIN"];'), []));
        self::assertSame(['hintSilent', 'cutText'], $this->findAlertCommands($code, ['hint', 'hintSilent']));
    }

    /**
     * @param list<string> $commands
     * @param array<string, list<string>> $pending fichier (chemin relatif, séparateur « / ») => commandes tolérées
     * @return list<string>
     */
    private function scanRoot(string $rel, array $commands, array $pending): array
    {
        $root = dirname(__DIR__, 2);
        $dir = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
        $hits = [];
        if (!is_dir($dir)) {
            return $hits;
        }
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
        /** @var SplFileInfo $file */
        foreach ($it as $file) {
            if (!$file->isFile() || strtolower($file->getExtension()) !== 'sqf') {
                continue;
            }
            $path = $file->getPathname();
            $relPath = str_replace('\\', '/', substr($path, strlen($root) + 1));
            $tolerated = $pending[$relPath] ?? [];
            $code = $this->stripSqfCommentsAndStrings((string) file_get_contents($path));
            $found = $this->findAlertCommands($code, $commands);
            foreach (array_diff($found, $tolerated) as $cmd) {
                $hits[] = $relPath . ' → ' . $cmd;
            }
        }

        return $hits;
    }

    /**
     * Commandes trouvées dans du code déjà nettoyé. cutText n'est retenu qu'avec un texte (cutText ["", …] efface un calque).
     *
     * @param list<string> $commands
     * @return list<string>
     */
    private function findAlertCommands(string $code, array $commands): array
    {
        $found = [];
        foreach ($commands as $cmd) {
            if (preg_match('/\b' . preg_quote($cmd, '/') . '\b/i', $code) === 1) {
                $found[] = $cmd;
            }
        }
        if (preg_match('/\bcutText\s*\[\s*"S"/i', $code) === 1) {
            $found[] = 'cutText';
        }

        return $found;
    }

    private function readRepo(string $relative): string
    {
        $path = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);

        return (string) file_get_contents($path);
    }

    private function assertNoVanillaChatCalls(string $src, string $label): void
    {
        $code = $this->stripSqfCommentsAndStrings($src);
        foreach (self::CHAT_COMMANDS as $cmd) {
            self::assertDoesNotMatchRegularExpression(
                '/\b' . preg_quote($cmd, '/') . '\b/i',
                $code,
                $label . ' still calls ' . $cmd
            );
        }
    }

    /**
     * Retire commentaires (// et /* *\/) et chaînes littérales. Une chaîne vide reste "" ; une chaîne non vide
     * devient "S" (les guillemets doublés "" / '' à l'intérieur d'une chaîne sont des échappements SQF).
     */
    private function stripSqfCommentsAndStrings(string $src): string
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
                $q = $c;
                $j = $i + 1;
                $empty = true;
                while ($j < $len) {
                    if ($src[$j] === $q) {
                        if ($j + 1 < $len && $src[$j + 1] === $q) {
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
