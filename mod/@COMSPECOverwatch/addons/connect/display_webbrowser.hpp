// Tablette Overwatch — Chromium embarqué (CT_WEBBROWSER / type 106), UX inspirée cTab (ctav-b2).
// idd 9974 · idc navigateur 9401
// Contenu local : web/tablet.html (sandbox A3API). Option « Carte Athena » → URL remote whitelistée.
// Bezel / icônes : assets device (cTab NSWDG) — ne pas retirer CT_WEBBROWSER ni idc 9401.
#include "display_device_macros.hpp"

// Hérite de RscWebBrowser (A3) pour les props obligatoires (colorBackground, colorText, …).
// Ne pas redéfinir type=106 seul : Arma exige colorBackground sur CT_WEBBROWSER.
class COMSPEC_RscWebBrowser: RscWebBrowser {
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
    // Attention : allowExternalURL est expérimental (souvent Development only) — fallback = Navigateur système.
    // Contenu local chargé via LoadFile au onLoad (pas de prompt tant qu'on reste local).
    allowExternalURL = 1;
    url = "";
};

// Carte Arma native (CT_MAP_MAIN) — le Chromium est toujours au-dessus des autres contrôles,
// donc on bascule navigateur ↔ carte (comme cTab FBCB2) pour afficher le terrain / satmap.
class COMSPEC_TabletMap: RscMapControl {
    idc = 9410;
    x = safezoneX + 0.07 * safezoneW;
    y = safezoneY + 0.105 * safezoneH;
    w = 0.86 * safezoneW;
    h = 0.76 * safezoneH;
    scaleDefault = 0.12;
    scaleMin = 0.001;
    scaleMax = 1.0;
    maxSatelliteAlpha = 0.85;
    alphaFadeStartScale = 0.35;
    alphaFadeEndScale = 0.4;
    showCountourInterval = 0;
    moveOnEdges = 1;
    onDraw = "_this call comspec_overwatch_connect_fnc_webBrowserMapOnDraw;";
};

class COMSPEC_WebBrowser_Dialog {
    idd = 9974;
    movingEnable = 1;
    onLoad = "(_this select 0) call comspec_overwatch_connect_fnc_webBrowserOnLoad;";
    onUnload = "uiNamespace setVariable ['COMSPEC_WebBrowser_Display', displayNull]; missionNamespace setVariable ['COMSPEC_WebBrowser_Mode', '']; missionNamespace setVariable ['COMSPEC_WebBrowser_RefreshToken', -1]; missionNamespace setVariable ['COMSPEC_WebBrowser_PageReady', false]; missionNamespace setVariable ['COMSPEC_WebBrowser_MapVisible', false];";

    class ControlsBackground {
        class Bezel: RscText {
            idc = -1;
            x = safezoneX + 0.06 * safezoneW;
            y = safezoneY + 0.06 * safezoneH;
            w = 0.88 * safezoneW;
            h = 0.88 * safezoneH;
            colorBackground[] = {0.02, 0.04, 0.06, 0.98};
        };

        // Texture tablette NSWDG en overlay discret (coin) — le navigateur reste plein cadre.
        class TabletBadge: RscPicture {
            idc = -1;
            text = COMSPEC_IMG_ICON_TABLET;
            x = safezoneX + 0.065 * safezoneW;
            y = safezoneY + 0.065 * safezoneH;
            w = 0.028 * safezoneW;
            h = 0.028 * safezoneW;
            colorText[] = {0.3, 0.85, 0.7, 0.95};
        };
    };

    class Controls {
        class OsdBattery: RscPicture {
            idc = -1;
            text = COMSPEC_IMG_ICON_BATTERY;
            x = safezoneX + 0.098 * safezoneW;
            y = safezoneY + 0.072 * safezoneH;
            w = 0.016 * safezoneW;
            h = 0.016 * safezoneW;
            colorText[] = {0.85, 0.95, 0.9, 1};
        };

        class OsdSignal: RscPicture {
            idc = -1;
            text = COMSPEC_IMG_ICON_SIGNAL;
            x = safezoneX + 0.118 * safezoneW;
            y = safezoneY + 0.072 * safezoneH;
            w = 0.016 * safezoneW;
            h = 0.016 * safezoneW;
            colorText[] = {0.85, 0.95, 0.9, 1};
        };

        class TitleBar: RscStructuredText {
            idc = 9402;
            text = "<t font='RobotoCondensedBold' size='0.72' color='#2dd4a8'>ATHENA OVERWATCH</t><t size='0.55' color='#7a8c9e'>  ·  tablette</t>";
            x = safezoneX + 0.14 * safezoneW;
            y = safezoneY + 0.07 * safezoneH;
            w = 0.48 * safezoneW;
            h = 0.028 * safezoneH;
        };

        class StatusHint: RscStructuredText {
            idc = 9403;
            text = "<t align='right' size='0.55' color='#8aa0b4'>Chargement de l’écran…</t>";
            x = safezoneX + 0.55 * safezoneW;
            y = safezoneY + 0.07 * safezoneH;
            w = 0.28 * safezoneW;
            h = 0.028 * safezoneH;
        };

        class Browser: COMSPEC_RscWebBrowser {
            idc = 9401;
            x = safezoneX + 0.07 * safezoneW;
            y = safezoneY + 0.105 * safezoneH;
            w = 0.86 * safezoneW;
            h = 0.76 * safezoneH;
        };

        // Carte native (masquée tant que le shell HTML gère une autre vue)
        class TabletMap: COMSPEC_TabletMap {
            idc = 9410;
            show = 0;
        };

        // Chrome carte (visible uniquement avec la carte native — le HTML est alors masqué)
        class MapChromeBar: RscText {
            idc = 9420;
            show = 0;
            x = safezoneX + 0.07 * safezoneW;
            y = safezoneY + 0.105 * safezoneH;
            w = 0.86 * safezoneW;
            h = 0.036 * safezoneH;
            colorBackground[] = {0.12, 0.14, 0.12, 0.92};
        };

        class MapChromeTitle: RscStructuredText {
            idc = 9421;
            show = 0;
            text = "<t size='0.65' color='#d8e4ec'>CARTE TERRAIN</t><t size='0.55' color='#7a8c9e'>  ·  double-clic = marqueur</t>";
            x = safezoneX + 0.075 * safezoneW;
            y = safezoneY + 0.108 * safezoneH;
            w = 0.32 * safezoneW;
            h = 0.03 * safezoneH;
        };

        class MapBtnMessages: RscButton {
            idc = 9422;
            show = 0;
            text = "Messages";
            x = safezoneX + 0.40 * safezoneW;
            y = safezoneY + 0.108 * safezoneH;
            w = 0.08 * safezoneW;
            h = 0.03 * safezoneH;
            sizeEx = 0.026;
            colorBackground[] = {0.08, 0.18, 0.16, 0.95};
            action = "['chat'] call comspec_overwatch_connect_fnc_webBrowserMapHide;";
        };

        class MapBtnCombat: RscButton {
            idc = 9423;
            show = 0;
            text = "Combat";
            x = safezoneX + 0.485 * safezoneW;
            y = safezoneY + 0.108 * safezoneH;
            w = 0.075 * safezoneW;
            h = 0.03 * safezoneH;
            sizeEx = 0.026;
            colorBackground[] = {0.08, 0.18, 0.16, 0.95};
            action = "['alerts'] call comspec_overwatch_connect_fnc_webBrowserMapHide;";
        };

        class MapBtnApps: RscButton {
            idc = 9424;
            show = 0;
            text = "Apps";
            x = safezoneX + 0.565 * safezoneW;
            y = safezoneY + 0.108 * safezoneH;
            w = 0.06 * safezoneW;
            h = 0.03 * safezoneH;
            sizeEx = 0.026;
            colorBackground[] = {0.08, 0.18, 0.16, 0.95};
            action = "['apps'] call comspec_overwatch_connect_fnc_webBrowserMapHide;";
        };

        class MapBtnZoomIn: RscButton {
            idc = 9425;
            show = 0;
            text = "Zoom +";
            x = safezoneX + 0.63 * safezoneW;
            y = safezoneY + 0.108 * safezoneH;
            w = 0.07 * safezoneW;
            h = 0.03 * safezoneH;
            sizeEx = 0.026;
            colorBackground[] = {0.1, 0.2, 0.18, 0.95};
            action = "[1.35] call comspec_overwatch_connect_fnc_webBrowserMapZoom;";
        };

        class MapBtnZoomOut: RscButton {
            idc = 9426;
            show = 0;
            text = "Zoom −";
            x = safezoneX + 0.705 * safezoneW;
            y = safezoneY + 0.108 * safezoneH;
            w = 0.07 * safezoneW;
            h = 0.03 * safezoneH;
            sizeEx = 0.026;
            colorBackground[] = {0.1, 0.2, 0.18, 0.95};
            action = "[0.74] call comspec_overwatch_connect_fnc_webBrowserMapZoom;";
        };

        class MapBtnCenter: RscButton {
            idc = 9427;
            show = 0;
            text = "Recentrer";
            x = safezoneX + 0.78 * safezoneW;
            y = safezoneY + 0.108 * safezoneH;
            w = 0.08 * safezoneW;
            h = 0.03 * safezoneH;
            sizeEx = 0.026;
            colorBackground[] = {0.1, 0.22, 0.2, 0.95};
            action = "[] call comspec_overwatch_connect_fnc_webBrowserMapCenter;";
        };

        class MapBtnUi: RscButton {
            idc = 9428;
            show = 0;
            text = "Menus";
            x = safezoneX + 0.865 * safezoneW;
            y = safezoneY + 0.108 * safezoneH;
            w = 0.06 * safezoneW;
            h = 0.03 * safezoneH;
            sizeEx = 0.026;
            colorBackground[] = {0.18, 0.14, 0.08, 0.95};
            action = "['bft'] call comspec_overwatch_connect_fnc_webBrowserMapHide;";
        };

        class BtnClassic: RscButton {
            idc = 9404;
            text = "Vue classique";
            x = safezoneX + 0.07 * safezoneW;
            y = safezoneY + 0.88 * safezoneH;
            w = 0.13 * safezoneW;
            h = 0.036 * safezoneH;
            sizeEx = 0.028;
            colorBackground[] = {0.06, 0.14, 0.2, 0.95};
            action = "closeDialog 0; createDialog 'COMSPEC_Device_Dialog';";
        };

        class BtnHub: RscButton {
            idc = 9405;
            text = "Hub";
            x = safezoneX + 0.21 * safezoneW;
            y = safezoneY + 0.88 * safezoneH;
            w = 0.1 * safezoneW;
            h = 0.036 * safezoneH;
            sizeEx = 0.028;
            colorBackground[] = {0.06, 0.14, 0.2, 0.95};
            action = "closeDialog 0; [] call comspec_overwatch_connect_fnc_openHub;";
        };

        // Contournement Stable : openURL hors du Chromium embarqué (pas de prompt allowExternalURL).
        class BtnSystemBrowser: RscButton {
            idc = 9407;
            text = "Navigateur système";
            x = safezoneX + 0.32 * safezoneW;
            y = safezoneY + 0.88 * safezoneH;
            w = 0.16 * safezoneW;
            h = 0.036 * safezoneH;
            sizeEx = 0.028;
            colorBackground[] = {0.08, 0.22, 0.2, 0.95};
            action = "[] call comspec_overwatch_connect_fnc_webBrowserOpenSystem;";
        };

        class BtnShowMap: RscButton {
            idc = 9408;
            text = "Carte terrain";
            x = safezoneX + 0.49 * safezoneW;
            y = safezoneY + 0.88 * safezoneH;
            w = 0.14 * safezoneW;
            h = 0.036 * safezoneH;
            sizeEx = 0.028;
            colorBackground[] = {0.1, 0.28, 0.2, 0.95};
            action = "[] call comspec_overwatch_connect_fnc_webBrowserMapShow;";
        };

        class BtnClose: RscButton {
            idc = 9406;
            text = "Fermer";
            x = safezoneX + 0.81 * safezoneW;
            y = safezoneY + 0.88 * safezoneH;
            w = 0.12 * safezoneW;
            h = 0.036 * safezoneH;
            sizeEx = 0.028;
            colorBackground[] = {0.12, 0.08, 0.08, 0.95};
            action = "closeDialog 0;";
        };
    };
};
