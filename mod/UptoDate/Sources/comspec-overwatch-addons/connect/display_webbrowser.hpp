// Tablette Overwatch — Chromium embarqué (CT_WEBBROWSER / type 106), UX inspirée cTab (ctav-b2).
// idd 9974 · idc navigateur 9401
// Contenu local : web/tablet.html (sandbox A3API). Option « Carte Athena » → URL remote whitelistée.
// Bezel / icônes : assets device (cTab NSWDG) — ne pas retirer CT_WEBBROWSER ni idc 9401.
#include "display_device_macros.hpp"

// Classe complète (héritage + type explicite) pour éviter un contrôle null si l’héritage moteur est incomplet.
// colorBackground / colorText sont exigés par certains builds pour CT_WEBBROWSER.
class COMSPEC_RscWebBrowser: RscWebBrowser {
    type = 106;
    idc = -1;
    deletable = 0;
    style = 0;
    x = 0;
    y = 0;
    w = 0.3;
    h = 0.3;
    colorBackground[] = {0.02, 0.04, 0.06, 1};
    colorText[] = {1, 1, 1, 1};
    // 1 = autorise aussi les URL externes (invite joueur native Arma). Requis pour la carte Athena.
    // Attention : allowExternalURL est expérimental (souvent Development only) — fallback = Navigateur du PC.
    // Contenu local chargé via LoadFile au onLoad (pas de prompt tant qu'on reste local).
    allowExternalURL = 1;
    url = "";
};

// Carte Arma native (CT_MAP_MAIN) — le Chromium est toujours au-dessus des autres contrôles,
// donc on bascule navigateur ↔ carte (comme cTab FBCB2) pour afficher le terrain / satmap.
class COMSPEC_TabletMap: RscMapControl {
    idc = 9410;
    type = 101;
    style = 48;
    x = safezoneX + 0.09 * safezoneW;
    y = safezoneY + 0.12 * safezoneH;
    w = 0.82 * safezoneW;
    h = 0.72 * safezoneH;
    scaleDefault = 0.12;
    scaleMin = 0.001;
    scaleMax = 1.0;
    maxSatelliteAlpha = 0.85;
    alphaFadeStartScale = 0.35;
    alphaFadeEndScale = 0.4;
    showCountourInterval = 1;
    moveOnEdges = 1;
    colorBackground[] = {0.969, 0.957, 0.949, 1};
    colorSea[] = {0.467, 0.631, 0.851, 0.5};
    colorForest[] = {0.4, 0.8, 0.4, 0.3};
    colorCountlines[] = {0.65, 0.16, 0.14, 0.5};
    colorMainCountlines[] = {0.75, 0.12, 0.1, 0.85};
    colorLevels[] = {0.25, 0.2, 0.15, 0.9};
    colorGrid[] = {0.05, 0.05, 0.05, 0.45};
    colorGridMap[] = {0.05, 0.05, 0.05, 0.45};
    onDraw = "_this call comspec_overwatch_connect_fnc_webBrowserMapOnDraw;";
};

class COMSPEC_WebBrowser_Dialog {
    idd = 9974;
    movingEnable = 1;
    onLoad = "(_this select 0) call comspec_overwatch_connect_fnc_webBrowserOnLoad;";
    onUnload = "uiNamespace setVariable ['COMSPEC_WebBrowser_Display', displayNull]; missionNamespace setVariable ['COMSPEC_WebBrowser_Mode', '']; missionNamespace setVariable ['COMSPEC_WebBrowser_RefreshToken', -1]; missionNamespace setVariable ['COMSPEC_WebBrowser_PageReady', false]; missionNamespace setVariable ['COMSPEC_WebBrowser_MapVisible', false]; missionNamespace setVariable ['COMSPEC_WebBrowser_MapAutoOpened', false]; missionNamespace setVariable ['COMSPEC_WebBrowser_BrowserPos', []];";

    class ControlsBackground {
        class Bezel: RscText {
            idc = -1;
            x = safezoneX + 0.08 * safezoneW;
            y = safezoneY + 0.08 * safezoneH;
            w = 0.84 * safezoneW;
            h = 0.84 * safezoneH;
            colorBackground[] = {0.02, 0.04, 0.06, 0.98};
        };

        // Texture tablette NSWDG en overlay discret (coin) — le navigateur reste plein cadre.
        class TabletBadge: RscPicture {
            idc = -1;
            text = COMSPEC_IMG_ICON_TABLET;
            x = safezoneX + 0.085 * safezoneW;
            y = safezoneY + 0.085 * safezoneH;
            w = 0.022 * safezoneW;
            h = 0.022 * safezoneW;
            colorText[] = {0.3, 0.85, 0.7, 0.95};
        };
    };

    class Controls {
        class OsdBattery: RscPicture {
            idc = -1;
            text = COMSPEC_IMG_ICON_BATTERY;
            x = safezoneX + 0.112 * safezoneW;
            y = safezoneY + 0.09 * safezoneH;
            w = 0.014 * safezoneW;
            h = 0.014 * safezoneW;
            colorText[] = {0.85, 0.95, 0.9, 1};
        };

        class OsdSignal: RscPicture {
            idc = -1;
            text = COMSPEC_IMG_ICON_SIGNAL;
            x = safezoneX + 0.13 * safezoneW;
            y = safezoneY + 0.09 * safezoneH;
            w = 0.014 * safezoneW;
            h = 0.014 * safezoneW;
            colorText[] = {0.85, 0.95, 0.9, 1};
        };

        class TitleBar: RscStructuredText {
            idc = 9402;
            text = "<t font='RobotoCondensedBold' size='0.65' color='#2dd4a8'>ATHENA OVERWATCH</t><t size='0.5' color='#7a8c9e'>  ·  tablette</t>";
            x = safezoneX + 0.15 * safezoneW;
            y = safezoneY + 0.088 * safezoneH;
            w = 0.45 * safezoneW;
            h = 0.024 * safezoneH;
        };

        class StatusHint: RscStructuredText {
            idc = 9403;
            text = "<t align='right' size='0.5' color='#8aa0b4'>Chargement de l’écran…</t>";
            x = safezoneX + 0.55 * safezoneW;
            y = safezoneY + 0.088 * safezoneH;
            w = 0.26 * safezoneW;
            h = 0.024 * safezoneH;
        };

        class Browser: COMSPEC_RscWebBrowser {
            idc = 9401;
            x = safezoneX + 0.09 * safezoneW;
            y = safezoneY + 0.12 * safezoneH;
            w = 0.82 * safezoneW;
            h = 0.72 * safezoneH;
        };

        // Affiché si le navigateur embarqué ne peut pas démarrer (repli lisible).
        class FallbackHelp: RscStructuredText {
            idc = 9430;
            show = 0;
            x = safezoneX + 0.12 * safezoneW;
            y = safezoneY + 0.22 * safezoneH;
            w = 0.76 * safezoneW;
            h = 0.42 * safezoneH;
            colorBackground[] = {0.04, 0.07, 0.09, 0.92};
            text = "";
        };

        // Carte native (masquée tant que le shell HTML gère une autre vue)
        class TabletMap: COMSPEC_TabletMap {
            idc = 9410;
            show = 0;
            x = safezoneX + 0.09 * safezoneW;
            y = safezoneY + 0.12 * safezoneH;
            w = 0.82 * safezoneW;
            h = 0.72 * safezoneH;
        };

        // Chrome carte (visible uniquement avec la carte native — le HTML est alors masqué)
        class MapChromeBar: RscText {
            idc = 9420;
            show = 0;
            x = safezoneX + 0.09 * safezoneW;
            y = safezoneY + 0.12 * safezoneH;
            w = 0.82 * safezoneW;
            h = 0.032 * safezoneH;
            colorBackground[] = {0.12, 0.14, 0.12, 0.92};
        };

        class MapChromeTitle: RscStructuredText {
            idc = 9421;
            show = 0;
            text = "<t size='0.58' color='#d8e4ec'>CARTE TERRAIN</t><t size='0.48' color='#7a8c9e'>  ·  double-clic = marqueur</t>";
            x = safezoneX + 0.095 * safezoneW;
            y = safezoneY + 0.122 * safezoneH;
            w = 0.30 * safezoneW;
            h = 0.028 * safezoneH;
        };

        class MapBtnMessages: RscButton {
            idc = 9422;
            show = 0;
            text = "Messages";
            x = safezoneX + 0.40 * safezoneW;
            y = safezoneY + 0.122 * safezoneH;
            w = 0.075 * safezoneW;
            h = 0.026 * safezoneH;
            sizeEx = "(((((safezoneW / safezoneH) min 1.2) / 1.2) / 25) * 0.8)";
            colorBackground[] = {0.08, 0.18, 0.16, 0.95};
            action = "['chat'] call comspec_overwatch_connect_fnc_webBrowserMapHide;";
        };

        class MapBtnCombat: RscButton {
            idc = 9423;
            show = 0;
            text = "Combat";
            x = safezoneX + 0.48 * safezoneW;
            y = safezoneY + 0.122 * safezoneH;
            w = 0.07 * safezoneW;
            h = 0.026 * safezoneH;
            sizeEx = "(((((safezoneW / safezoneH) min 1.2) / 1.2) / 25) * 0.8)";
            colorBackground[] = {0.08, 0.18, 0.16, 0.95};
            action = "['alerts'] call comspec_overwatch_connect_fnc_webBrowserMapHide;";
        };

        class MapBtnApps: RscButton {
            idc = 9424;
            show = 0;
            text = "Apps";
            x = safezoneX + 0.555 * safezoneW;
            y = safezoneY + 0.122 * safezoneH;
            w = 0.055 * safezoneW;
            h = 0.026 * safezoneH;
            sizeEx = "(((((safezoneW / safezoneH) min 1.2) / 1.2) / 25) * 0.8)";
            colorBackground[] = {0.08, 0.18, 0.16, 0.95};
            action = "['apps'] call comspec_overwatch_connect_fnc_webBrowserMapHide;";
        };

        class MapBtnZoomIn: RscButton {
            idc = 9425;
            show = 0;
            text = "Zoom +";
            x = safezoneX + 0.615 * safezoneW;
            y = safezoneY + 0.122 * safezoneH;
            w = 0.065 * safezoneW;
            h = 0.026 * safezoneH;
            sizeEx = "(((((safezoneW / safezoneH) min 1.2) / 1.2) / 25) * 0.8)";
            colorBackground[] = {0.1, 0.2, 0.18, 0.95};
            action = "[1.35] call comspec_overwatch_connect_fnc_webBrowserMapZoom;";
        };

        class MapBtnZoomOut: RscButton {
            idc = 9426;
            show = 0;
            text = "Zoom −";
            x = safezoneX + 0.685 * safezoneW;
            y = safezoneY + 0.122 * safezoneH;
            w = 0.065 * safezoneW;
            h = 0.026 * safezoneH;
            sizeEx = "(((((safezoneW / safezoneH) min 1.2) / 1.2) / 25) * 0.8)";
            colorBackground[] = {0.1, 0.2, 0.18, 0.95};
            action = "[0.74] call comspec_overwatch_connect_fnc_webBrowserMapZoom;";
        };

        class MapBtnCenter: RscButton {
            idc = 9427;
            show = 0;
            text = "Recentrer";
            x = safezoneX + 0.755 * safezoneW;
            y = safezoneY + 0.122 * safezoneH;
            w = 0.075 * safezoneW;
            h = 0.026 * safezoneH;
            sizeEx = "(((((safezoneW / safezoneH) min 1.2) / 1.2) / 25) * 0.8)";
            colorBackground[] = {0.1, 0.22, 0.2, 0.95};
            action = "[] call comspec_overwatch_connect_fnc_webBrowserMapCenter;";
        };

        class MapBtnUi: RscButton {
            idc = 9428;
            show = 0;
            text = "Menus";
            x = safezoneX + 0.835 * safezoneW;
            y = safezoneY + 0.122 * safezoneH;
            w = 0.055 * safezoneW;
            h = 0.026 * safezoneH;
            sizeEx = "(((((safezoneW / safezoneH) min 1.2) / 1.2) / 25) * 0.8)";
            colorBackground[] = {0.18, 0.14, 0.08, 0.95};
            action = "['bft'] call comspec_overwatch_connect_fnc_webBrowserMapHide;";
        };

        class BtnClassic: RscButton {
            idc = 9404;
            text = "Vue classique";
            x = safezoneX + 0.09 * safezoneW;
            y = safezoneY + 0.86 * safezoneH;
            w = 0.12 * safezoneW;
            h = 0.028 * safezoneH;
            sizeEx = "(((((safezoneW / safezoneH) min 1.2) / 1.2) / 25) * 0.85)";
            colorBackground[] = {0.06, 0.14, 0.2, 0.95};
            // Petit modèle désactivé temporairement
            show = 0;
            action = "closeDialog 0; [] call comspec_overwatch_connect_fnc_openClassicTablet;";
        };

        class BtnHub: RscButton {
            idc = 9405;
            text = "Apps";
            x = safezoneX + 0.22 * safezoneW;
            y = safezoneY + 0.86 * safezoneH;
            w = 0.09 * safezoneW;
            h = 0.028 * safezoneH;
            sizeEx = "(((((safezoneW / safezoneH) min 1.2) / 1.2) / 25) * 0.85)";
            colorBackground[] = {0.06, 0.14, 0.2, 0.95};
            action = "['apps'] call comspec_overwatch_connect_fnc_openTabletView;";
        };

        // Contournement Stable : openURL hors du Chromium embarqué (pas de prompt allowExternalURL).
        class BtnSystemBrowser: RscButton {
            idc = 9407;
            text = "Ouvrir sur le PC";
            x = safezoneX + 0.32 * safezoneW;
            y = safezoneY + 0.86 * safezoneH;
            w = 0.15 * safezoneW;
            h = 0.028 * safezoneH;
            sizeEx = "(((((safezoneW / safezoneH) min 1.2) / 1.2) / 25) * 0.85)";
            colorBackground[] = {0.08, 0.22, 0.2, 0.95};
            action = "[] call comspec_overwatch_connect_fnc_webBrowserOpenSystem;";
        };

        class BtnShowMap: RscButton {
            idc = 9408;
            text = "Carte terrain";
            x = safezoneX + 0.48 * safezoneW;
            y = safezoneY + 0.86 * safezoneH;
            w = 0.13 * safezoneW;
            h = 0.028 * safezoneH;
            sizeEx = "(((((safezoneW / safezoneH) min 1.2) / 1.2) / 25) * 0.85)";
            colorBackground[] = {0.1, 0.28, 0.2, 0.95};
            action = "[] call comspec_overwatch_connect_fnc_webBrowserMapShow;";
        };

        class BtnClose: RscButton {
            idc = 9406;
            text = "Fermer";
            x = safezoneX + 0.78 * safezoneW;
            y = safezoneY + 0.86 * safezoneH;
            w = 0.11 * safezoneW;
            h = 0.028 * safezoneH;
            sizeEx = "(((((safezoneW / safezoneH) min 1.2) / 1.2) / 25) * 0.85)";
            colorBackground[] = {0.12, 0.08, 0.08, 0.95};
            action = "closeDialog 0;";
        };
    };
};
