// Panneau "Gestion du mod" — UI HTML dédiée (CT_WEBBROWSER / type 106), distincte de la
// tablette Athena (idd 9974 / web/tablet.html). Accessible depuis le menu Échap (RscDisplayInterrupt).
// idd 9979 · idc navigateur 9601. Contenu local : web/pause_manager.html.
// Nécessite COMSPEC_RscWebBrowser (display_webbrowser.hpp) déjà inclus avant ce fichier.

class COMSPEC_PauseManager_Dialog {
    idd = 9979;
    movingEnable = 1;
    onLoad = "(_this select 0) call comspec_overwatch_connect_fnc_pauseManagerOnLoad;";
    onUnload = "uiNamespace setVariable ['COMSPEC_PauseManager_Display', displayNull]; missionNamespace setVariable ['COMSPEC_PauseManager_PageReady', false, false]; missionNamespace setVariable ['COMSPEC_PauseManager_RefreshToken', -1, false];";

    class ControlsBackground {
        class Bezel: RscText {
            idc = -1;
            x = safezoneX + 0.14 * safezoneW;
            y = safezoneY + 0.10 * safezoneH;
            w = 0.72 * safezoneW;
            h = 0.80 * safezoneH;
            colorBackground[] = {0.02, 0.04, 0.06, 0.98};
        };
    };

    class Controls {
        class TitleBar: RscStructuredText {
            idc = -1;
            text = "<t font='RobotoCondensedBold' size='0.65' color='#2dd4a8'>COMSPEC OVERWATCH</t><t size='0.5' color='#7a8c9e'>  ·  gestion du mod</t>";
            x = safezoneX + 0.16 * safezoneW;
            y = safezoneY + 0.108 * safezoneH;
            w = 0.5 * safezoneW;
            h = 0.024 * safezoneH;
        };

        class StatusHint: RscStructuredText {
            idc = 9602;
            text = "<t align='right' size='0.5' color='#8aa0b4'>Chargement…</t>";
            x = safezoneX + 0.55 * safezoneW;
            y = safezoneY + 0.108 * safezoneH;
            w = 0.29 * safezoneW;
            h = 0.024 * safezoneH;
        };

        class Browser: COMSPEC_RscWebBrowser {
            idc = 9601;
            x = safezoneX + 0.16 * safezoneW;
            y = safezoneY + 0.14 * safezoneH;
            w = 0.68 * safezoneW;
            h = 0.70 * safezoneH;
            allowExternalURL = 0;
        };

        // Affiché si le navigateur embarqué ne peut pas démarrer (repli lisible).
        class FallbackHelp: RscStructuredText {
            idc = 9603;
            show = 0;
            x = safezoneX + 0.18 * safezoneW;
            y = safezoneY + 0.30 * safezoneH;
            w = 0.64 * safezoneW;
            h = 0.36 * safezoneH;
            colorBackground[] = {0.04, 0.07, 0.09, 0.92};
            text = "";
        };

        class BtnClose: RscButton {
            idc = 9604;
            text = "Fermer";
            x = safezoneX + 0.74 * safezoneW;
            y = safezoneY + 0.855 * safezoneH;
            w = 0.10 * safezoneW;
            h = 0.028 * safezoneH;
            sizeEx = "(((((safezoneW / safezoneH) min 1.2) / 1.2) / 25) * 0.85)";
            colorBackground[] = {0.12, 0.08, 0.08, 0.95};
            action = "closeDialog 0;";
        };
    };
};
