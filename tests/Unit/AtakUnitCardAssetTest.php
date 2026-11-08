<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * ATAK natif : fiche joueur unique (fn_unitCard). Rien ne disparaît : les actions de chacune des anciennes fiches se
 * retrouvent dans son catalogue ou sa rangée, sous un seul libellé.
 */
final class AtakUnitCardAssetTest extends TestCase
{
    private const MAIN = __DIR__ . '/../../mod/COMSPEC_ATAK_Native/Sources/addons/main';

    /**
     * Libellé d'une ancienne fiche → libellé unique. Liste écrite à partir de la 4.0.0.
     *
     * @var array<string, array<string, string>>
     */
    private const OLD_CARDS = [
        'carte, panneau SITUATION' => ['VIBRER' => 'VIBRER', 'SMS' => 'SMS', 'PING' => 'PING', 'CENTRER' => 'CARTE', 'ROUTE VERS' => 'Y ALLER'],
        'BFT, fiche de piste' => ['CARTE' => 'CARTE', 'Y ALLER' => 'Y ALLER', 'SMS' => 'SMS', 'VIBRER' => 'VIBRER', 'HORS LIGNE' => 'HORS LIGNE', 'VOIR CAMÉRA' => 'CAMÉRA'],
        'Groupe, ligne de membre' => ['SMS' => 'SMS', 'CARTE' => 'CARTE', 'CHEF' => 'PASSER LE COMMANDEMENT'],
        'Inter-team, chef d\'escouade' => ['carte' => 'CARTE', 'SMS' => 'SMS'],
        'clic droit sur un équipier' => ['Voir la caméra' => 'CAMÉRA', 'Son champ de vision' => 'CHAMP DE VISION', 'Localiser par relais' => 'LOCALISER'],
    ];

    private function read(string $path): string
    {
        return (string) file_get_contents(self::MAIN . '/' . $path);
    }

    public function testEveryOldCardActionIsInTheSingleCatalog(): void
    {
        $card = $this->read('functions/ui/fn_unitCard.sqf');
        foreach (self::OLD_CARDS as $where => $actions) {
            foreach ($actions as $old => $single) {
                self::assertStringContainsString('"' . $single . '"', $card, $where . ' : ' . $old);
            }
        }
    }

    public function testEveryCatalogKeyHasItsAction(): void
    {
        $card = $this->read('functions/ui/fn_unitCard.sqf');
        foreach (['center', 'route', 'sms', 'vibe', 'buzz', 'ping', 'cam', 'follow', 'lead', 'viewshed', 'locate', 'bft'] as $key) {
            self::assertStringContainsString('case "' . $key . '": {', $card, $key);
        }
    }

    public function testOrderIsFixedAndFollowLeadsOnTheMap(): void
    {
        $card = $this->read('functions/ui/fn_unitCard.sqf');
        self::assertStringContainsString('[_sui] + _all + [_cam, _car] + _tail', $card);
        self::assertStringContainsString('[_car] + _all + [_cam, _sui] + _tail', $card);
        $route = (int) strpos($card, '["route", "Y ALLER"');
        $sms = (int) strpos($card, '["sms", "SMS"');
        $ping = (int) strpos($card, '["ping", "PING"');
        self::assertTrue($route < $sms && $sms < $ping);
    }

    public function testPagesUseTheSingleCardAndOldVocabularyIsGone(): void
    {
        $map = $this->read('functions/pages/fn_pageMap.sqf');
        $bft = $this->read('functions/pages/fn_pageBft.sqf');
        $grp = $this->read('functions/pages/fn_pageGroup.sqf');
        self::assertStringContainsString('["rows", [_sel, "panel"]] call comspec_atak_native_fnc_unitCard', $map);
        self::assertStringNotContainsString('"ROUTE VERS"', $map);
        self::assertStringNotContainsString('"CENTRER", ["center"]', $map);
        self::assertStringContainsString('["bar", [_unit, "bft"', $bft);
        self::assertStringNotContainsString('"VOIR CAMÉRA"', $bft);
        self::assertStringContainsString('["rows", [_u, "group"]] call comspec_atak_native_fnc_unitCard', $grp);
        self::assertStringNotContainsString('"CHEF", compile', $grp);
        self::assertStringContainsString('comspec_atak_native_fnc_unitCard', $this->read('functions/teams/fn_pageInterTeam.sqf'));
        self::assertStringContainsString('class unitCard {};', $this->read('config.cpp'));
    }

    public function testCommandHandoverNeedsASecondPress(): void
    {
        $card = $this->read('functions/ui/fn_unitCard.sqf');
        self::assertStringContainsString('["PASSER LE COMMANDEMENT", "CONFIRMER"] select ([_id] call _armed)', $card);
        self::assertStringContainsString('if ([_eid] call _armed) then {', $card);
        self::assertStringContainsString('["lead", netId _o] call comspec_atak_native_fnc_groupAction', $card);
    }

    public function testBuzzAlertOffersSmsAndKeepsItsButtons(): void
    {
        $buzz = $this->read('functions/ui/fn_buzzAlert.sqf');
        foreach (['["VU", "ack"', '["CARTE", "map"', '["SMS", "sms"', '["FERMER", "close"'] as $b) {
            self::assertStringContainsString($b, $buzz);
        }
    }

    public function testMapShortcutSelectsTheUnitAndBftIsInTheDock(): void
    {
        self::assertStringContainsString('_s set ["selectedEntity", _e];', $this->read('functions/pages/fn_bftAction.sqf'));
        self::assertStringContainsString('class Bft         { name="BFT"; page="BFT"; section="Communication"; order=36; dock=1;', $this->read('config.cpp'));
        self::assertStringContainsString('if (_from in ["BFT", "GROUP"])', $this->read('functions/ui/fn_pageRender.sqf'));
    }

    public function testGroupActionsExistAndRallySendsOnce(): void
    {
        $act = $this->read('functions/pages/fn_groupAction.sqf');
        foreach (['case "rally"', 'case "smsGroup"', 'case "smsGroupSend"', 'case "frameGroup"'] as $c) {
            self::assertStringContainsString($c, $act);
        }
        $grp = $this->read('functions/pages/fn_pageGroup.sqf');
        foreach (['RALLIEMENT SUR MOI', 'SMS AU GROUPE', 'CADRER LE GROUPE'] as $b) {
            self::assertStringContainsString($b, $grp);
        }
        // Un seul netSend pour le ralliement, jamais un par membre.
        $rally = substr($act, (int) strpos($act, 'case "rally"'), (int) strpos($act, 'case "smsGroup"') - (int) strpos($act, 'case "rally"'));
        self::assertSame(1, substr_count($rally, 'comspec_atak_native_fnc_netSend'));
        self::assertStringNotContainsString('netSend; } forEach', $rally);
    }

    public function testUnitClickShowsAFloatingCardWithoutReflow(): void
    {
        $select = $this->read('functions/map/fn_mapSelect.sqf');
        self::assertStringContainsString('["mapCard"] call comspec_atak_native_fnc_unitCard', $select);
        self::assertStringNotContainsString('inspToggle', $select);
        // Plus de bandeau de 4 s en petit format : la fiche le remplace.
        self::assertStringNotContainsString('format ["%1 · %2", _best getOrDefault', $select);
        $card = $this->read('functions/ui/fn_unitCard.sqf');
        foreach (['case "mapCard"', 'case "tick"', 'case "step"', 'ui_back.paa', 'ui_chevron_right.paa', 'ui_close.paa'] as $s) {
            self::assertStringContainsString($s, $card);
        }
        // Rafraîchi une fois par seconde par fn_statusUpdate, jamais dans le dessin de la carte.
        self::assertStringContainsString('["tick"] call comspec_atak_native_fnc_unitCard', $this->read('functions/ui/fn_statusUpdate.sqf'));
        self::assertStringNotContainsString('fnc_unitCard', $this->read('functions/map/fn_mapOnDraw.sqf'));
    }

    public function testFollowingAUnitReusesTheFollowPathAndAnyOtherCentreStopsIt(): void
    {
        $draw = $this->read('functions/map/fn_mapOnDraw.sqf');
        self::assertStringContainsString('_state getOrDefault ["mapFollowId", ""]', $draw);
        // Aucune lecture d'objet de plus : la position vient des pistes.
        self::assertStringContainsString('getOrDefault [_fwId, createHashMap]) getOrDefault ["position", []]', $draw);
        $centre = $this->read('functions/map/fn_mapCenter.sqf');
        self::assertStringContainsString('["_keepFollow", false]', $centre);
        self::assertStringContainsString('set ["mapFollowId", ""]', $centre);
    }

    public function testToolbarOrderPingVisibleAndNothingLost(): void
    {
        $map = $this->read('functions/pages/fn_pageMap.sqf');
        $order = [];
        foreach (['["center", "map_center"', '["zoomin", "map_zoomin"', '["zoomout", "map_zoomout"', '["MARKER", "map_marker"', '["PING", ', '["layers", "map_grid"', '["menu", "map_tools"'] as $k) {
            $pos = strpos($map, $k);
            self::assertNotFalse($pos, $k);
            $order[] = $pos;
        }
        $sorted = $order;
        sort($sorted);
        self::assertSame($sorted, $order);
        // Indicatifs et « Sur mon téléphone » ne disparaissent pas : OUTILS > AFFICHAGE.
        self::assertStringContainsString('["LABELS", "map_labels"', $map);
        self::assertStringContainsString('["PHONE", "app_athena"', $map);
        $menu = $this->read('functions/map/fn_mapToolMenu.sqf');
        self::assertStringContainsString('case "LABELS"', $menu);
        self::assertStringContainsString('["open", "carte"] call comspec_atak_native_fnc_mobileQr', $menu);
        // Un seul bouton OUTILS, dans la barre.
        self::assertStringNotContainsString('private _tlb', $map);
    }

    public function testNumberKeysFollowTheToolMenu(): void
    {
        $menu = $this->read('functions/map/fn_mapToolMenu.sqf');
        self::assertStringContainsString('["ROUTE", "WP", "SEARCH", "MEASURE", "HEIGHT", "LOS", "FLAT", "IPTGT", "LINE"]', $menu);
        self::assertStringContainsString('["key", _key - 1] call comspec_atak_native_fnc_mapToolMenu', $this->read('functions/ui/fn_displayLoad.sqf'));
        self::assertStringContainsString('["numbered"] call comspec_atak_native_fnc_mapToolMenu', $this->read('functions/pages/fn_pageMap.sqf'));
    }

    public function testNewLabelsAreTranslated(): void
    {
        $en = $this->read('i18n/en.txt');
        foreach (['PASSER LE COMMANDEMENT', 'RALLIEMENT SUR MOI', 'SMS AU GROUPE', 'CADRER LE GROUPE', 'NE PLUS SUIVRE', 'FICHE BFT'] as $k) {
            self::assertStringContainsString('["' . $k . '", ', $en, $k);
        }
    }
}
