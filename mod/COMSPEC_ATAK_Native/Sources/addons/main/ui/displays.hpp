#include "ui_ids.hpp"
#include "defines.hpp"
#define ATAK_POS x=ATAK_DEV_X; y=ATAK_DEV_Y; w=0; h=0

// Terminal en main (souris) : créé par createDisplay. Le même contenu existe en HUD (RscTitles) plus bas.
class COMSPEC_RscDisplayATAK {
    idd=COMSPEC_ATAK_IDD; movingEnable=0; enableSimulation=1;
    onLoad="[_this select 0, true] call comspec_atak_native_fnc_displayLoad";
    onUnload="_this call comspec_atak_native_fnc_displayUnload";
    class controlsBackground {
        class Phone: COMSPEC_RscPhone { idc=COMSPEC_ATAK_IDC_PHONE; ATAK_POS; };
        class Frame: COMSPEC_RscPanel { idc=COMSPEC_ATAK_IDC_FRAME; ATAK_POS; colorBackground[]=ATAK_BG1; };
        class Wallpaper: COMSPEC_RscPhone { idc=COMSPEC_ATAK_IDC_WALLPAPER; ATAK_POS; style=48; text=""; };
        class StatusBar: COMSPEC_RscPanel { idc=COMSPEC_ATAK_IDC_STATUSBAR; ATAK_POS; colorBackground[]={0,0,0,1}; };
        class AppBar: COMSPEC_RscPanel { idc=COMSPEC_ATAK_IDC_APPBAR; ATAK_POS; colorBackground[]=ATAK_BG0; };
        class Dock: COMSPEC_RscPanel { idc=COMSPEC_ATAK_IDC_DOCK; ATAK_POS; colorBackground[]=ATAK_BG0; };
        class Rail: COMSPEC_RscPanel { idc=COMSPEC_ATAK_IDC_RAIL; ATAK_POS; colorBackground[]=ATAK_BG0; };
        class Inspector: COMSPEC_RscPanel { idc=COMSPEC_ATAK_IDC_INSPECTOR; ATAK_POS; colorBackground[]=ATAK_BG1; };
        class WeatherBg: COMSPEC_RscPanel { idc=COMSPEC_ATAK_IDC_WEATHER_BG; ATAK_POS; colorBackground[]={0.55,0.38,0.08,1}; };
    };
    class controls {
        class Map: COMSPEC_RscMapAtak { idc=COMSPEC_ATAK_IDC_MAP; ATAK_POS; onDraw="_this call comspec_atak_native_fnc_mapOnDraw"; onMouseButtonDown="_this call comspec_atak_native_fnc_mapMouseButtonDown"; onMouseMoving="_this call comspec_atak_native_fnc_mapMouseMoving"; onMouseButtonUp="_this call comspec_atak_native_fnc_mapMouseButtonUp"; onMouseButtonDblClick="_this call comspec_atak_native_fnc_mapDblClick"; };
        class Content: COMSPEC_RscControlsGroup { idc=COMSPEC_ATAK_IDC_CONTENT; ATAK_POS; };
        class InspectorText: COMSPEC_RscStructuredText { idc=COMSPEC_ATAK_IDC_INSPECTOR_TEXT; ATAK_POS; text=""; };
        class Battery: COMSPEC_RscIcon { idc=COMSPEC_ATAK_IDC_BATTERY; ATAK_POS; text="\z\comspec_atak_native\addons\main\data\bat_100.paa"; };
        class BatteryText: COMSPEC_RscLabel { idc=COMSPEC_ATAK_IDC_BATTERY_TEXT; ATAK_POS; colorText[]=ATAK_TEXT; };
        class WeatherIcon: COMSPEC_RscIcon { idc=COMSPEC_ATAK_IDC_WEATHER_ICON; ATAK_POS; colorText[]={1,1,1,1}; text="\z\comspec_atak_native\addons\main\data\ui_weather.paa"; };
        class WeatherText: COMSPEC_RscText { idc=COMSPEC_ATAK_IDC_WEATHER_TEXT; ATAK_POS; colorText[]={1,1,1,1}; };
        class Clock: COMSPEC_RscTextCenter { idc=COMSPEC_ATAK_IDC_CLOCK; ATAK_POS; text="--:--"; };
        class Gps: COMSPEC_RscIconButton { idc=COMSPEC_ATAK_IDC_GPS; ATAK_POS; text="\z\comspec_atak_native\addons\main\data\ui_gps.paa"; tooltip="Centrer la carte sur moi"; action="['MAP'] call comspec_atak_native_fnc_navigate; [player, 0.05] call comspec_atak_native_fnc_mapCenter"; };
        // À droite de l'heure : centre de notifications, puis logo COMSPEC Link quand la liaison est établie.
        class NotifBtn: COMSPEC_RscIconButton { idc=COMSPEC_ATAK_IDC_NOTIF; ATAK_POS; text="\z\comspec_atak_native\addons\main\data\ui_bell.paa"; tooltip="Centre de notifications"; action="[] call comspec_atak_native_fnc_notifCenter"; };
        class LinkLogo: COMSPEC_RscIcon { idc=COMSPEC_ATAK_IDC_LINKLOGO; ATAK_POS; colorText[]={0.36,0.78,0.42,1}; text="\z\comspec_atak_native\addons\main\data\ui_comspec_link.paa"; tooltip="COMSPEC Link connecté"; };
        class Signal: COMSPEC_RscIcon { idc=COMSPEC_ATAK_IDC_SIGNAL; ATAK_POS; text="\z\comspec_atak_native\addons\main\data\sig_0.paa"; };
        class Back: COMSPEC_RscIconButton { idc=COMSPEC_ATAK_IDC_BACK; ATAK_POS; text="\z\comspec_atak_native\addons\main\data\ui_back.paa"; action="[] call comspec_atak_native_fnc_back"; tooltip="Retour"; };
        class Title: COMSPEC_RscText { idc=COMSPEC_ATAK_IDC_TITLE; font="RobotoCondensedBold"; text="APPLICATIONS"; ATAK_POS; };
        class Rotate: COMSPEC_RscIconButton { idc=COMSPEC_ATAK_IDC_ROTATE; ATAK_POS; text="\z\comspec_atak_native\addons\main\data\ui_rotate.paa"; action="[] call comspec_atak_native_fnc_orientationToggle"; tooltip="Téléphone vertical / horizontal"; };
        class Mode: COMSPEC_RscIconButton { idc=COMSPEC_ATAK_IDC_MODE; ATAK_POS; text="\z\comspec_atak_native\addons\main\data\ui_expand.paa"; action="[] call comspec_atak_native_fnc_modeToggle"; tooltip="Mini / plein écran"; };
        class Home: COMSPEC_RscIconButton { idc=COMSPEC_ATAK_IDC_HOME; ATAK_POS; text="\z\comspec_atak_native\addons\main\data\ui_apps.paa"; action="['LAUNCHER'] call comspec_atak_native_fnc_navigate"; tooltip="Applications"; };
        class HudHint: COMSPEC_RscLabel { idc=COMSPEC_ATAK_IDC_HUDHINT; style=2; ATAK_POS; text=""; };
        // Touches physiques de la coque S7 (dessinées sur la texture) : retour, accueil, apps récentes, marche/arrêt.
        class HwBack: COMSPEC_RscButtonInvisible { idc=COMSPEC_ATAK_IDC_HW_BACK; ATAK_POS; tooltip="Retour"; action="[] call comspec_atak_native_fnc_back; playSound 'ClickSoft'"; };
        class HwHome: COMSPEC_RscButtonInvisible { idc=COMSPEC_ATAK_IDC_HW_HOME; ATAK_POS; tooltip="Accueil"; action="['LAUNCHER'] call comspec_atak_native_fnc_navigate; playSound 'ClickSoft'"; };
        class HwApps: COMSPEC_RscButtonInvisible { idc=COMSPEC_ATAK_IDC_HW_APPS; ATAK_POS; tooltip="Apps récentes"; action="['RECENTS'] call comspec_atak_native_fnc_navigate; playSound 'ClickSoft'"; };
        class HwPower: COMSPEC_RscButtonInvisible { idc=COMSPEC_ATAK_IDC_HW_POWER; ATAK_POS; tooltip="Réduire en miniature"; action="[] call comspec_atak_native_fnc_interactToggle"; };
        // Reprend le focus après un clic carte : la carte focalisée passe devant les panneaux et les outils.
        class FocusSink: COMSPEC_RscButtonOverlay { idc=COMSPEC_ATAK_IDC_FOCUS; x="safeZoneX - 0.1"; y="safeZoneY - 0.1"; w=0.01; h=0.01; };
    };
};

class RscTitles {
    // Terminal porté (HUD) : visible pendant que l'on joue, sans souris ni clavier.
    class COMSPEC_RscTitleATAK: COMSPEC_RscDisplayATAK {
        idd=-1; duration=1e+011; fadein=0; fadeout=0;
        onLoad="[_this select 0, false] call comspec_atak_native_fnc_displayLoad";
    };
    // Mode photo : viseur plein écran, le joueur se déplace normalement.
    class COMSPEC_RscTitleCamera {
        idd=-1; duration=1e+011; fadein=0; fadeout=0; movingEnable=0;
        onLoad="uiNamespace setVariable ['COMSPEC_ATAK_CameraDisplay', _this select 0]";
        class controls {
            class Overlay: COMSPEC_RscPhone { idc=1; style=48; x="safeZoneX"; y="safeZoneY"; w="safeZoneW"; h="safeZoneH"; text="\z\comspec_atak_native\addons\main\data\camera_overlay.paa"; };
            class Flash: COMSPEC_RscText { idc=2; x="safeZoneX"; y="safeZoneY"; w="safeZoneW"; h="safeZoneH"; colorBackground[]={1,1,1,0}; };
            class Info: COMSPEC_RscText { idc=3; style=2; x="safeZoneX + safeZoneW * 0.1"; y="safeZoneY + safeZoneH * 0.84"; w="safeZoneW * 0.8"; h="safeZoneH * 0.035"; colorText[]={0.92,0.95,0.93,1}; shadow=2; sizeEx="0.028 * safeZoneH"; text="CLIC GAUCHE : photo     MOLETTE ou + / - : zoom     F : flash     R : selfie     ESPACE : quitter"; };
            class Count: COMSPEC_RscText { idc=4; style=2; x="safeZoneX + safeZoneW * 0.25"; y="safeZoneY + safeZoneH * 0.875"; w="safeZoneW * 0.5"; h="safeZoneH * 0.03"; colorText[]={0.36,0.78,0.42,1}; shadow=2; sizeEx="0.024 * safeZoneH"; text=""; };
            class Status: COMSPEC_RscText { idc=5; style=2; x="safeZoneX + safeZoneW * 0.25"; y="safeZoneY + safeZoneH * 0.06"; w="safeZoneW * 0.5"; h="safeZoneH * 0.032"; colorText[]={0.95,0.85,0.45,1}; shadow=2; sizeEx="0.026 * safeZoneH"; text="OBJECTIF ARRIÈRE     ZOOM x1     FLASH AUTO"; };
        };
    };
};
