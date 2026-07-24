// Vue tablette Athena : superpose l'affichage du mod (statut de liaison, profil joueur, accès au
// hub complet) sur l'écran de la tablette physique (image de fond, idd 9973).
//
// Ratio texture athena_tablet.paa : 2048×1024 → AR = 2.0.
// En coords GUI Arma, des unités absolues égales (même nombre pour w et h) forment un carré.
// Donc la hauteur du fond (et des contrôles « ronds ») doit être exprimée en *safezoneW*,
// pas en *safezoneH* — sinon l'UI est écrasée horizontalement (ovales verticaux, texte skinny).
//
// Zone écran mesurée sur la photo source (fractions de l'image, conservées après resize) :
//   left 0.1324  top 0.1494  right 0.8362  bottom 0.8283
// Fond : largeur 0.72*safezoneW, hauteur 0.36*safezoneW (= 0.72/2), centré.
// Icônes OSD : assets device (cTab NSWDG), voir img/device/ et display_device_macros.hpp.
#include "display_device_macros.hpp"

class COMSPEC_Device_Dialog {
    idd = 9973;
    movingEnable = 1;
    onLoad = "(_this select 0) call comspec_overwatch_connect_fnc_showDeviceView;";

    class Controls {
        // Fond tablette — AR 2:1 (w et h sur la même base safezoneW).
        class DeviceBackground: RscPicture {
            idc = 9300;
            text = "\z\comspec_overwatch\addons\connect\img\athena_tablet.paa";
            x = safezoneX + 0.14 * safezoneW;
            y = safezoneY + 0.5 * safezoneH - 0.18 * safezoneW;
            w = 0.72 * safezoneW;
            h = 0.36 * safezoneW;
        };

        // Contenu dans le bezel écran (SCR dérivé des fractions ci-dessus).

        class DeviceOsdBattery: RscPicture {
            idc = -1;
            text = COMSPEC_IMG_ICON_BATTERY;
            x = safezoneX + 0.247 * safezoneW;
            y = safezoneY + 0.5 * safezoneH - 0.138 * safezoneW;
            w = 0.018 * safezoneW;
            h = 0.018 * safezoneW;
            colorText[] = {0.85, 0.95, 0.9, 1};
        };

        class DeviceOsdSignal: RscPicture {
            idc = -1;
            text = COMSPEC_IMG_ICON_SIGNAL;
            x = safezoneX + 0.268 * safezoneW;
            y = safezoneY + 0.5 * safezoneH - 0.138 * safezoneW;
            w = 0.018 * safezoneW;
            h = 0.018 * safezoneW;
            colorText[] = {0.85, 0.95, 0.9, 1};
        };

        class DeviceTitle: RscStructuredText {
            idc = -1;
            text = "<t font='RobotoCondensedBold' size='0.72' color='#d8e4ec'>ATHENA — OVERWATCH</t>";
            x = safezoneX + 0.292 * safezoneW;
            y = safezoneY + 0.5 * safezoneH - 0.138 * safezoneW;
            w = 0.24 * safezoneW;
            h = 0.022 * safezoneW;
        };

        class DeviceStatus: RscStructuredText {
            idc = 9312;
            text = "<t align='right' size='0.68' color='#ff8a7a'>●  Not connected</t>";
            x = safezoneX + 0.495 * safezoneW;
            y = safezoneY + 0.5 * safezoneH - 0.138 * safezoneW;
            w = 0.235 * safezoneW;
            h = 0.022 * safezoneW;
        };

        // Avatar carré (cercle si texture ronde) : w == h en unités absolues.
        class DeviceProfileAvatar: RscPicture {
            idc = 9302;
            text = "";
            x = safezoneX + 0.247 * safezoneW;
            y = safezoneY + 0.5 * safezoneH - 0.082 * safezoneW;
            w = 0.095 * safezoneW;
            h = 0.095 * safezoneW;
            colorBackground[] = {0.08, 0.1, 0.12, 0.9};
        };

        class DeviceProfileName: RscStructuredText {
            idc = 9303;
            text = "<t size='0.62' color='#7a8c9e'>Account not linked</t>";
            x = safezoneX + 0.354 * safezoneW;
            y = safezoneY + 0.5 * safezoneH - 0.082 * safezoneW;
            w = 0.376 * safezoneW;
            h = 0.095 * safezoneW;
        };

        // "Units" view (light BFT): same coordinates as profile block,
        // basculée avec lui (fn_deviceToggleView.sqf).
        class DeviceRosterTitle: RscStructuredText {
            idc = 9315;
            text = "<t size='0.58' color='#5a9e88'>CONNECTED UNITS</t>";
            x = safezoneX + 0.247 * safezoneW;
            y = safezoneY + 0.5 * safezoneH - 0.082 * safezoneW;
            w = 0.483 * safezoneW;
            h = 0.022 * safezoneW;
        };

        class DeviceRosterList: RscStructuredText {
            idc = 9314;
            text = "";
            x = safezoneX + 0.247 * safezoneW;
            y = safezoneY + 0.5 * safezoneH - 0.056 * safezoneW;
            w = 0.483 * safezoneW;
            h = 0.088 * safezoneW;
        };

        class DeviceBtnHub: RscButton {
            idc = 9304;
            text = "Full hub";
            x = safezoneX + 0.247 * safezoneW;
            y = safezoneY + 0.5 * safezoneH + 0.074 * safezoneW;
            w = 0.12 * safezoneW;
            h = 0.032 * safezoneW;
            sizeEx = 0.028;
            colorBackground[] = {0.06, 0.14, 0.2, 0.95};
            action = "closeDialog 0; createDialog 'COMSPEC_Hub_Dialog';";
        };

        class DeviceBtnWeb: RscButton {
            idc = 9307;
            text = "Tactical screen";
            x = safezoneX + 0.375 * safezoneW;
            y = safezoneY + 0.5 * safezoneH + 0.074 * safezoneW;
            w = 0.14 * safezoneW;
            h = 0.032 * safezoneW;
            sizeEx = 0.028;
            colorBackground[] = {0.08, 0.22, 0.2, 0.95};
            action = "closeDialog 0; [] call comspec_overwatch_connect_fnc_webBrowserShow;";
        };

        class DeviceBtnRoster: RscButton {
            idc = 9306;
            text = "Units";
            x = safezoneX + 0.523 * safezoneW;
            y = safezoneY + 0.5 * safezoneH + 0.074 * safezoneW;
            w = 0.1 * safezoneW;
            h = 0.032 * safezoneW;
            sizeEx = 0.028;
            colorBackground[] = {0.08, 0.16, 0.14, 0.95};
            action = "[findDisplay 9973] call comspec_overwatch_connect_fnc_deviceToggleView;";
        };

        class DeviceBtnClose: RscButton {
            idc = 9305;
            text = "Close";
            x = safezoneX + 0.631 * safezoneW;
            y = safezoneY + 0.5 * safezoneH + 0.074 * safezoneW;
            w = 0.099 * safezoneW;
            h = 0.032 * safezoneW;
            sizeEx = 0.028;
            colorBackground[] = {0.12, 0.08, 0.08, 0.95};
            action = "closeDialog 0;";
        };
    };
};
