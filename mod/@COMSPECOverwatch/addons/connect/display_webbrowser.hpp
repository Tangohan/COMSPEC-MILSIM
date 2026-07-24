// Tablette Overwatch — Chromium embarqué (CT_WEBBROWSER / type 106), UX inspirée cTab (ctav-b2).
// idd 9974 · idc navigateur 9401
// Contenu local : web/tablet.html (sandbox A3API). Option « Carte Athena » → URL remote whitelistée.
// Bezel / icônes : assets device (cTab NSWDG) — ne pas retirer CT_WEBBROWSER ni idc 9401.
#include "display_device_macros.hpp"

class COMSPEC_RscWebBrowser {
    type = 106; // CT_WEBBROWSER
    idc = -1;
    deletable = 0;
    style = 0;
    x = 0;
    y = 0;
    w = 0.3;
    h = 0.3;
    // 1 = autorise aussi les URL externes (invite joueur native Arma). Requis pour la carte Athena.
    // Attention : allowExternalURL est expérimental (souvent Development only) — fallback = Navigateur système.
    // Contenu local chargé via LoadFile au onLoad (pas de prompt tant qu'on reste local).
    allowExternalURL = 1;
    url = "";
};

class COMSPEC_WebBrowser_Dialog {
    idd = 9974;
    movingEnable = 1;
    onLoad = "(_this select 0) call comspec_overwatch_connect_fnc_webBrowserOnLoad;";
    onUnload = "uiNamespace setVariable ['COMSPEC_WebBrowser_Display', displayNull]; missionNamespace setVariable ['COMSPEC_WebBrowser_Mode', '']; missionNamespace setVariable ['COMSPEC_WebBrowser_RefreshToken', -1]; missionNamespace setVariable ['COMSPEC_WebBrowser_PageReady', false];";

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

        class BtnClassic: RscButton {
            idc = 9404;
            text = "Classic view";
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
            text = "System browser";
            x = safezoneX + 0.32 * safezoneW;
            y = safezoneY + 0.88 * safezoneH;
            w = 0.18 * safezoneW;
            h = 0.036 * safezoneH;
            sizeEx = 0.028;
            colorBackground[] = {0.08, 0.22, 0.2, 0.95};
            action = "[] call comspec_overwatch_connect_fnc_webBrowserOpenSystem;";
        };

        class BtnClose: RscButton {
            idc = 9406;
            text = "Close";
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
