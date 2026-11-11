<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Faire parler une IA : une question, une réponse (addon ia_rp du pack @COMSPEC_SSE).
 * L'attribut Eden « Profil IA RP », l'atelier IA (Eden et Zeus) et le menu ZEN montrent d'abord « Question » et
 * « Réponse de l'IA », écrites comme une question perso du format des réponses (fn_iarpAnswers « qaSet » / « qaGet ») ;
 * « Ce qu'elle sait » et « Quand elle le dit » (anciens « Savoir » / « Déblocage ») passent dans les options avancées,
 * mêmes champs du profil (know, unlock, unlockWord) : les missions déjà faites se chargent sans changement.
 */
final class SseIarpQuestionReponseAssetTest extends TestCase
{
    private function addon(): string
    {
        return dirname(__DIR__, 2) . '/mod/@COMSPEC_SSE/addons/ia_rp';
    }

    private function read(string $rel): string
    {
        $path = $this->addon() . '/' . $rel;
        self::assertFileExists($path);

        return (string) file_get_contents($path);
    }

    /** @return array<string, array{en: string, fr: string}> */
    private function strings(): array
    {
        $xml = simplexml_load_string($this->read('stringtable.xml'));
        self::assertNotFalse($xml, 'stringtable.xml ia_rp : XML invalide');
        $out = [];
        foreach ($xml->xpath('//Key') ?: [] as $key) {
            $out[(string) $key['ID']] = ['en' => trim((string) $key->English), 'fr' => trim((string) $key->French)];
        }

        return $out;
    }

    public function testEveryAttributeAndWorkshopControlHasTooltipWithFrenchAndEnglish(): void
    {
        $strings = $this->strings();
        $cfg = $this->read('config.cpp');
        $hpp = $this->read('dialogs/iarp_workshop.hpp');

        // Attributs Eden (catégorie COMSPEC SSE · IA RP, module Atelier IA) : un libellé et une infobulle chacun.
        preg_match_all('/class\s+(\w+)\s*\{\s*displayName\s*=\s*"\$(STR_\w+)";\s*tooltip\s*=\s*"\$(STR_\w+)";\s*property\s*=/', $cfg, $attrs, PREG_SET_ORDER);
        self::assertNotEmpty($attrs, 'aucun attribut Eden trouvé');
        $props = [];
        foreach ($attrs as [, $class, $label, $tip]) {
            $props[] = $class;
            foreach ([$label, $tip] as $id) {
                self::assertArrayHasKey($id, $strings, "$class : clé $id absente");
                self::assertNotSame('', $strings[$id]['fr'], "$class : $id sans français");
                self::assertNotSame('', $strings[$id]['en'], "$class : $id sans anglais");
            }
        }
        // Propriété du profil inchangée : les missions déjà faites gardent leurs valeurs.
        self::assertContains('comspec_sse_iarpProfile', $props);
        self::assertStringContainsString('property = "comspec_sse_iarpProfile";', $cfg);

        // Contrôles de l'attribut « Profil IA RP » : question, réponse, leurs libellés et aides ont une infobulle.
        foreach (['QaQ', 'QaA', 'Open'] as $ctrl) {
            self::assertMatchesRegularExpression('/class ' . $ctrl . ': ctrl\w+ \{[^}]*tooltip = "\$STR_\w+";/s', $cfg, "attribut : $ctrl sans infobulle");
        }

        // Atelier : chaque champ éditable (édition, liste, curseur, case) a son infobulle traduite.
        preg_match_all('/class (\w+): COMSPEC_SSE_Rsc(?:Edit|Combo|Slider|CheckBox)\b[^\n]*/', $hpp, $rows, PREG_SET_ORDER);
        self::assertNotEmpty($rows);
        foreach ($rows as [$line, $class]) {
            self::assertMatchesRegularExpression('/tooltip = "\$(STR_\w+)"/', $line, "atelier : $class sans infobulle");
            preg_match('/tooltip = "\$(STR_\w+)"/', $line, $m);
            self::assertArrayHasKey($m[1], $strings, "atelier : $class -> {$m[1]} absente");
            self::assertNotSame('', $strings[$m[1]]['fr']);
            self::assertNotSame('', $strings[$m[1]]['en']);
        }
    }

    public function testQuestionAndAnswerComeFirstAndOldRowsAreRenamedInPlainFrench(): void
    {
        $strings = $this->strings();
        $hpp = $this->read('dialogs/iarp_workshop.hpp');

        foreach (['W_Question', 'W_Question_D', 'W_QuestionHint', 'W_QuestionMore', 'W_Reply', 'W_Reply_D', 'W_ReplyHint',
            'W_ReplyVar', 'W_ReplyVar_D', 'W_QaHalf', 'W_Adv', 'W_AdvHide', 'W_TestCustom', 'W_Adv_D', 'Z_Qa',
            'W_Know', 'W_Know_D', 'W_Unlock', 'W_Unlock_D', 'W_ReactOrder', 'W_Answers', 'W_AnswersN', 'W_Answers_D'] as $k) {
            $id = 'STR_COMSPEC_IARP_' . $k;
            self::assertArrayHasKey($id, $strings, "$id absente");
            self::assertNotSame('', $strings[$id]['fr'], "$id sans français");
            self::assertNotSame('', $strings[$id]['en'], "$id sans anglais");
        }
        $fr = static fn (string $k): string => $strings['STR_COMSPEC_IARP_' . $k]['fr'];
        self::assertSame('Question', $fr('W_Question'));
        self::assertSame("Réponse de l'IA", $fr('W_Reply'));
        self::assertStringContainsString('Où est le chef du village ?', $fr('W_QuestionHint'));
        self::assertStringContainsString('{prenom}', $fr('W_ReplyHint'));
        // Plus de « Savoir », « Déblocage », « Si ordre », « Réponses et limites » à l'écran.
        self::assertSame("Ce qu'elle sait", $fr('W_Know'));
        self::assertSame('Quand elle le dit', $fr('W_Unlock'));
        self::assertNotSame('Si ordre', $fr('W_ReactOrder'));
        self::assertStringStartsWith('Toutes les questions et réponses', $fr('W_Answers'));
        foreach (['W_Know_D', 'W_Unlock_D', 'W_Question_D', 'W_Reply_D', 'W_Answers_D'] as $k) {
            self::assertStringContainsString('Exemple', $fr($k), "$k sans exemple");
        }

        // Atelier : question et réponse dans la vue par défaut, « Ce qu'elle sait » / « Quand elle le dit » repliés.
        foreach (['class QaQ:', 'class QaA:', 'class QaVar:', 'class LKnow:', 'class LUnlock:', 'class Adv:'] as $needle) {
            self::assertStringContainsString($needle, $hpp);
        }
        self::assertMatchesRegularExpression('/class LKnow: COMSPEC_SSE_RscLabel \{ idc = \d+;/', $hpp);
        self::assertMatchesRegularExpression('/class LUnlock: COMSPEC_SSE_RscLabel \{ idc = \d+;/', $hpp);
    }

    public function testBothWindowsWriteTheSameAnswersStructure(): void
    {
        $answers = $this->read('functions/fn_iarpAnswers.sqf');
        $workshop = $this->read('functions/fn_iarpWorkshop.sqf');
        $eden = $this->read('functions/fn_iarpEden.sqf');
        $zen = $this->read('functions/fn_iarpZen.sqf');
        $self = $this->read('functions/fn_iarpSelfTest.sqf');

        foreach (['case "qaGet":', 'case "qaSet":', 'case "qaTriggers":'] as $op) {
            self::assertStringContainsString($op, $answers);
        }
        foreach ([$workshop, $eden, $zen] as $src) {
            self::assertStringContainsString('"qaGet"', $src);
            self::assertStringContainsString('"qaSet"', $src);
        }
        // Champs du profil d'avant toujours lus et écrits (missions déjà faites).
        self::assertStringContainsString('_p set ["know", trim (ctrlText (94735 call _ctl))];', $workshop);
        self::assertStringContainsString('_p set ["unlockWord", trim (ctrlText (94737 call _ctl))];', $workshop);
        // Zeus : même question / réponse dans le menu « Profil d'IA », envoyée comme les autres réglages.
        self::assertStringContainsString('STR_COMSPEC_IARP_Z_Qa', $zen);
        self::assertStringContainsString('["profileTweak", [[["answers"', $zen);
        // Auto-test : aller-retour, paraphrase reconnue, anciennes valeurs chargées.
        self::assertStringContainsString('question / réponse', $self);
        self::assertStringContainsString('Le chef du village, il est où ?', $self);
    }

    public function testNoArmaAlertPopupsInTouchedUi(): void
    {
        foreach (['functions/fn_iarpWorkshop.sqf', 'functions/fn_iarpEden.sqf', 'functions/fn_iarpZen.sqf'] as $rel) {
            $src = $this->read($rel);
            foreach (['systemChat', 'hintSilent', 'hintC', 'titleText', 'cutText', 'BIS_fnc_showNotification'] as $bad) {
                self::assertStringNotContainsString($bad, $src, "$rel : $bad");
            }
            // Commande « hint » (pas l'opération « hint » de la ligne d'aide de l'attribut).
            self::assertDoesNotMatchRegularExpression('/(?<![\w"\'])hint\s+(?:format|localize|parseText|str|_\w|")/', $src, "$rel : hint");
        }
    }
}
