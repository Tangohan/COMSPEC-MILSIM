<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Bouton « SSE » de la fenêtre d'édition d'une unité en Zeus (ZEN, zen_attributes, type « Object ») et fenêtre
 * « SSE · <nom de l'IA> » (fn_iarpZenHub) qui rouvre les fenêtres SSE existantes pour cette IA.
 */
final class SseZeusEditButtonAssetTest extends TestCase
{
    private string $iarp;

    protected function setUp(): void
    {
        $this->iarp = dirname(__DIR__, 2) . '/mod/@COMSPEC_SSE/addons/ia_rp';
    }

    private function read(string $rel): string
    {
        $path = $this->iarp . '/' . $rel;
        self::assertFileExists($path);

        return (string) file_get_contents($path);
    }

    public function testButtonIsRegisteredOnZenObjectDisplayForLivingAiOnly(): void
    {
        $hub = $this->read('functions/fn_iarpZenHub.sqf');
        $cfg = $this->read('config.cpp');
        $client = $this->read('XEH_postInitClient.sqf');

        self::assertStringContainsString('class iarpZenHub {}', $cfg);
        self::assertStringContainsString('#include "dialogs\iarp_hub.hpp"', $cfg);
        self::assertStringContainsString('comspec_sse_fnc_iarpZenHub', $client);
        self::assertStringContainsString('zen_attributes_fnc_addButton', $client);

        // Porte officielle de ZEN (pas de fichier d'un autre mod touché), type d'affichage des unités et objets.
        self::assertStringContainsString('["Object", ["STR_COMSPEC_IARP_H_Btn", "STR_COMSPEC_IARP_H_Btn_D"]', $hub);
        self::assertStringContainsString('call zen_attributes_fnc_addButton', $hub);
        // Une IA vivante seulement : homme, pas un joueur ; jamais un véhicule, un groupe ou un objet.
        self::assertStringContainsString('_this isKindOf "CAManBase"', $hub);
        self::assertStringContainsString('!isPlayer _this', $hub);
        self::assertStringContainsString('alive _this', $hub);
        self::assertStringContainsString('_this isEqualType objNull', $hub);
        // La fenêtre d'édition reste ouverte (5e argument de ZEN : fermer = false) : les saisies du Zeus ne sont pas perdues.
        self::assertMatchesRegularExpression('/\},\s*false\s*\]\s*call zen_attributes_fnc_addButton/', $hub);
        self::assertStringNotContainsString('closeDialog', $hub);
        // Une seule pose par mission.
        self::assertStringContainsString('comspec_sse_iarpZenHubOn', $hub);
    }

    public function testHubReusesZenRoleplayActions(): void
    {
        $hub = $this->read('functions/fn_iarpZenHub.sqf');
        $zen = $this->read('functions/fn_iarpZen.sqf');
        $dlg = $this->read('dialogs/iarp_hub.hpp');

        // fn_iarpZen range chaque action du menu « SSE · Roleplay de l'IA » dans une table lue par la fenêtre SSE.
        self::assertStringContainsString('comspec_sse_iarpZenActs', $zen);
        self::assertStringContainsString('comspec_sse_iarpZenActs', $hub);
        foreach (['_editor', '_truth', '_workshop', '_answers', '_emotions', '_traits', '_know'] as $id) {
            self::assertStringContainsString('"' . $id . '"', $hub);
        }
        self::assertStringContainsString('class COMSPEC_SSE_IarpHub', $dlg);
        self::assertStringContainsString('idd = 94900;', $dlg);
        self::assertStringContainsString('createDialog "COMSPEC_SSE_IarpHub"', $hub);

        // Aucune alerte d'interface Arma.
        foreach (['hint ', 'hintSilent', 'systemChat', 'titleText', 'cutText', 'BIS_fnc_showNotification'] as $bad) {
            self::assertStringNotContainsString($bad, $hub);
        }
    }

    public function testStringsExistInFrenchAndEnglish(): void
    {
        $xml = $this->read('stringtable.xml');
        $doc = simplexml_load_string($xml);
        self::assertNotFalse($doc, 'stringtable.xml valide');

        $keys = ['Btn', 'Btn_D', 'Title', 'Close', 'Close_D', 'Id', 'Id_D', 'Truth', 'Truth_D', 'Workshop', 'Workshop_D',
            'Answers', 'Answers_D', 'Emo', 'Emo_D', 'Control', 'Control_D', 'Know', 'Know_D'];
        foreach ($keys as $k) {
            $id = 'STR_COMSPEC_IARP_H_' . $k;
            $node = $doc->xpath("//Key[@ID='" . $id . "']");
            self::assertCount(1, $node, $id);
            self::assertNotSame('', trim((string) $node[0]->French), $id . ' FR');
            self::assertNotSame('', trim((string) $node[0]->English), $id . ' EN');
        }
        $tip = $doc->xpath("//Key[@ID='STR_COMSPEC_IARP_H_Btn_D']")[0];
        self::assertStringContainsString('identité et papiers, réponses, émotions', (string) $tip->French);

        $main = (string) file_get_contents(dirname($this->iarp) . '/main/stringtable.xml');
        self::assertStringContainsString('bouton SSE', $main);
        self::assertStringContainsString('SSE button', $main);
    }
}
