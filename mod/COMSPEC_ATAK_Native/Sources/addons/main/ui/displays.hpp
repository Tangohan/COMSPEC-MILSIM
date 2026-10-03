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
        class StatusBar: COMSPEC_RscPanel { idc=COMSPEC_ATAK_IDC_STATUSBAR; ATAK_POS; colorBackground[]={0,0,0,1}; };
        class AppBar: COMSPEC_RscPanel { idc=COMSPEC_ATAK_IDC_APPBAR; ATAK_POS; colorBackground[]=ATAK_BG0; };
        class Dock: COMSPEC_RscPanel { idc=COMSPEC_ATAK_IDC_DOCK; ATAK_POS; colorBackground[]=ATAK_BG0; };
        class Rail: COMSPEC_RscPanel { idc=COMSPEC_ATAK_IDC_RAIL; ATAK_POS; colorBackground[]=ATAK_BG0; };
        class Inspector: COMSPEC_RscPanel { idc=COMSPEC_ATAK_IDC_INSPECTOR; ATAK_POS; colorBackground[]=ATAK_BG1; };
        class WeatherBg: COMSPEC_RscPanel { idc=COMSPEC_ATAK_IDC_WEATHER_BG; ATAK_POS; colorBackground[]={0.55,0.38,0.08,1}; };
    };
    class controls {
        class Map: COMSPEC_RscMap { idc=COMSPEC_ATAK_IDC_MAP; ATAK_POS; onDraw="_this call comspec_atak_native_fnc_mapOnDraw"; onMouseButtonDown="_this call comspec_atak_native_fnc_mapMouseButtonDown"; onMouseMoving="_this call comspec_atak_native_fnc_mapMouseMoving"; onMouseButtonUp="_this call comspec_atak_native_fnc_mapMouseButtonUp"; onMouseButtonDblClick="_this call comspec_atak_native_fnc_mapDblClick"; };
        class Content: COMSPEC_RscControlsGroup { idc=COMSPEC_ATAK_IDC_CONTENT; ATAK_POS; };
        class InspectorText: COMSPEC_RscStructuredText { idc=COMSPEC_ATAK_IDC_INSPECTOR_TEXT; ATAK_POS; text=""; };
        class Battery: COMSPEC_RscIcon { idc=COMSPEC_ATAK_IDC_BATTERY; ATAK_POS; text="\z\comspec_atak_native\addons\main\data\bat_100.paa"; };
        class BatteryText: COMSPEC_RscLabel { idc=COMSPEC_ATAK_IDC_BATTERY_TEXT; ATAK_POS; colorText[]=ATAK_TEXT; };
        class WeatherIcon: COMSPEC_RscIcon { idc=COMSPEC_ATAK_IDC_WEATHER_ICON; ATAK_POS; colorText[]={1,1,1,1}; text="\z\comspec_atak_native\addons\main\data\ui_weather.paa"; };
        class WeatherText: COMSPEC_RscText { idc=COMSPEC_ATAK_IDC_WEATHER_TEXT; ATAK_POS; colorText[]={1,1,1,1}; };
        class Clock: COMSPEC_RscTextCenter { idc=COMSPEC_ATAK_IDC_CLOCK; ATAK_POS; text="--:--"; };
        class Gps: COMSPEC_RscIconButton { idc=COMSPEC_ATAK_IDC_GPS; ATAK_POS; text="\z\comspec_atak_native\addons\main\data\ui_gps.paa"; tooltip="Centrer la carte sur moi"; action="['MAP'] call comspec_atak_native_fnc_navigate; [player, 0.05] call comspec_atak_native_fnc_mapCenter"; };
        class Signal: COMSPEC_RscIcon { idc=COMSPEC_ATAK_IDC_SIGNAL; ATAK_POS; text="\z\comspec_atak_native\addons\main\data\sig_0.paa"; };
        class Back: COMSPEC_RscIconButton { idc=COMSPEC_ATAK_IDC_BACK; ATAK_POS; text="\z\comspec_atak_native\addons\main\data\ui_back.paa"; action="[] call comspec_atak_native_fnc_back"; tooltip="Retour"; };
        class Title: COMSPEC_RscText { idc=COMSPEC_ATAK_IDC_TITLE; font="RobotoCondensedBold"; text="APPLICATIONS"; ATAK_POS; };
        class Rotate: COMSPEC_RscIconButton { idc=COMSPEC_ATAK_IDC_ROTATE; ATAK_POS; text="\z\comspec_atak_native\addons\main\data\ui_rotate.paa"; action="[] call comspec_atak_native_fnc_orientationToggle"; tooltip="Téléphone vertical / horizontal"; };
        class Mode: COMSPEC_RscIconButton { idc=COMSPEC_ATAK_IDC_MODE; ATAK_POS; text="\z\comspec_atak_native\addons\main\data\ui_expand.paa"; action="[] call comspec_atak_native_fnc_modeToggle"; tooltip="Mini / plein écran"; };
        class Home: COMSPEC_RscIconButton { idc=COMSPEC_ATAK_IDC_HOME; ATAK_POS; text="\z\comspec_atak_native\addons\main\data\ui_apps.paa"; action="['LAUNCHER'] call comspec_atak_native_fnc_navigate"; tooltip="Applications"; };
        class HudHint: COMSPEC_RscLabel { idc=COMSPEC_ATAK_IDC_HUDHINT; style=2; ATAK_POS; text=""; };
    };
};

class RscTitles {
    // Terminal porté (HUD) : visible pendant que l'on joue, sans souris ni clavier.
    class COMSPEC_RscTitleATAK: COMSPEC_RscDisplayATAK {
        idd=-1; duration=1e+011; fadein=0; fadeout=0;
        onLoad="[_this select 0, false] call comspec_atak_native_fnc_displayLoad";
    };
};
