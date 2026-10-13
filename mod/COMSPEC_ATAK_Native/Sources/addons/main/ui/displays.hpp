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
        class Map: COMSPEC_RscMapAtak { idc=COMSPEC_ATAK_IDC_MAP; ATAK_POS; onDraw="private _perfT = diag_tickTime; _this call comspec_atak_native_fnc_mapOnDraw; ['carte (image)', _perfT] call comspec_atak_native_fnc_perfAcc"; onMouseButtonDown="_this call comspec_atak_native_fnc_mapMouseButtonDown"; onMouseMoving="_this call comspec_atak_native_fnc_mapMouseMoving"; onMouseButtonUp="_this call comspec_atak_native_fnc_mapMouseButtonUp"; onMouseButtonDblClick="_this call comspec_atak_native_fnc_mapDblClick"; };
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
        class Title: COMSPEC_RscText { idc=COMSPEC_ATAK_IDC_TITLE; font="COMSPEC_BarlowBold"; text="APPLICATIONS"; ATAK_POS; };
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

// Écran 3D de la tablette sur le JPC : même contenu, rendu dans une texture UI 1024 x 576 posée sur le modèle
// (fn_jpcScreen : #(rgb,1024,576,1)ui("COMSPEC_RscATAKTexture", nom unique)). Non interactif, comme le HUD.
class COMSPEC_RscATAKTexture: COMSPEC_RscDisplayATAK {
    idd=-1;
    onLoad="[_this select 0, false, true] call comspec_atak_native_fnc_displayLoad";
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
            // Haut : un seul panneau sombre, filet de couleur (vert : envoi vers Athena, orange : hors réseau),
            // état de l'objectif sur la première ligne, destination et légende sur la seconde.
            class TopBand: COMSPEC_RscText { idc=8; x="safeZoneX + safeZoneW * 0.3"; y="safeZoneY + safeZoneH * 0.05"; w="safeZoneW * 0.4"; h="safeZoneH * 0.074"; colorBackground[]={0.02,0.03,0.025,0.62}; };
            class TopAccent: COMSPEC_RscText { idc=11; x="safeZoneX + safeZoneW * 0.3"; y="safeZoneY + safeZoneH * 0.05"; w="safeZoneW * 0.4"; h="safeZoneH * 0.003"; colorBackground[]={0.36,0.78,0.42,0.9}; };
            class Status: COMSPEC_RscStructuredText { idc=5; x="safeZoneX + safeZoneW * 0.3"; y="safeZoneY + safeZoneH * 0.058"; w="safeZoneW * 0.4"; h="safeZoneH * 0.032"; colorText[]={0.92,0.95,0.93,1}; shadow=1; size="0.026 * safeZoneH"; text=""; };
            class DestBand: COMSPEC_RscText { idc=10; x="safeZoneX + safeZoneW * 0.32"; y="safeZoneY + safeZoneH * 0.092"; w="safeZoneW * 0.36"; h="safeZoneH * 0.0015"; colorBackground[]={1,1,1,0.1}; };
            class Dest: COMSPEC_RscStructuredText { idc=9; x="safeZoneX + safeZoneW * 0.3"; y="safeZoneY + safeZoneH * 0.096"; w="safeZoneW * 0.4"; h="safeZoneH * 0.026"; colorText[]={0.92,0.95,0.93,1}; shadow=1; size="0.02 * safeZoneH"; text=""; };
            // Exposition en direct (ISO et vitesse suivent la lumière), en haut à droite, au-dessus de la zone
            // où ACRE affiche ses avertissements.
            class ExpoBand: COMSPEC_RscText { idc=13; x="safeZoneX + safeZoneW * 0.835"; y="safeZoneY + safeZoneH * 0.045"; w="safeZoneW * 0.115"; h="safeZoneH * 0.068"; colorBackground[]={0.02,0.03,0.025,0.62}; };
            class Expo: COMSPEC_RscStructuredText { idc=12; x="safeZoneX + safeZoneW * 0.835"; y="safeZoneY + safeZoneH * 0.049"; w="safeZoneW * 0.115"; h="safeZoneH * 0.064"; colorText[]={0.92,0.95,0.93,1}; shadow=1; size="0.024 * safeZoneH"; text=""; };
            // Échelle de zoom (x1 … x8) sur fond discret, palier en cours en vert.
            class ZoomBand: COMSPEC_RscText { idc=14; x="safeZoneX + safeZoneW * 0.885"; y="safeZoneY + safeZoneH * 0.365"; w="safeZoneW * 0.045"; h="safeZoneH * 0.27"; colorBackground[]={0.02,0.03,0.025,0.45}; };
            class ZoomScale: COMSPEC_RscStructuredText { idc=6; x="safeZoneX + safeZoneW * 0.885"; y="safeZoneY + safeZoneH * 0.372"; w="safeZoneW * 0.04"; h="safeZoneH * 0.26"; colorText[]={0.92,0.95,0.93,1}; shadow=1; size="0.024 * safeZoneH"; text=""; };
            // Bas : barre de raccourcis en touches (pastilles claires), groupées et ajustée à son contenu par
            // fn_photoMode « HUD » ; repliée après quelques secondes (H : réafficher).
            class BottomBand: COMSPEC_RscText { idc=7; x=0; y=0; w=0; h=0; colorBackground[]={0.02,0.03,0.025,0.6}; };
            class Info: COMSPEC_RscStructuredText { idc=3; x=0; y=0; w=0; h=0; text=""; };
            class KeyBase0: COMSPEC_RscText { idc=80; x=0; y=0; w=0; h=0; colorBackground[]={0.5,0.55,0.52,0.95}; };
            class KeyCap0: COMSPEC_RscTextCenter { idc=20; x=0; y=0; w=0; h=0; font="COMSPEC_BarlowBold"; sizeEx="0.021 * safeZoneH"; colorText[]={0.06,0.08,0.07,1}; colorBackground[]={0.92,0.95,0.93,0.97}; text=""; };
            class KeyLabel0: COMSPEC_RscText { idc=40; x=0; y=0; w=0; h=0; sizeEx="0.023 * safeZoneH"; colorText[]={0.86,0.91,0.88,1}; shadow=2; text=""; };
            class KeyBase1: COMSPEC_RscText { idc=81; x=0; y=0; w=0; h=0; colorBackground[]={0.5,0.55,0.52,0.95}; };
            class KeyCap1: COMSPEC_RscTextCenter { idc=21; x=0; y=0; w=0; h=0; font="COMSPEC_BarlowBold"; sizeEx="0.021 * safeZoneH"; colorText[]={0.06,0.08,0.07,1}; colorBackground[]={0.92,0.95,0.93,0.97}; text=""; };
            class KeyLabel1: COMSPEC_RscText { idc=41; x=0; y=0; w=0; h=0; sizeEx="0.023 * safeZoneH"; colorText[]={0.86,0.91,0.88,1}; shadow=2; text=""; };
            class KeyBase2: COMSPEC_RscText { idc=82; x=0; y=0; w=0; h=0; colorBackground[]={0.5,0.55,0.52,0.95}; };
            class KeyCap2: COMSPEC_RscTextCenter { idc=22; x=0; y=0; w=0; h=0; font="COMSPEC_BarlowBold"; sizeEx="0.021 * safeZoneH"; colorText[]={0.06,0.08,0.07,1}; colorBackground[]={0.92,0.95,0.93,0.97}; text=""; };
            class KeyLabel2: COMSPEC_RscText { idc=42; x=0; y=0; w=0; h=0; sizeEx="0.023 * safeZoneH"; colorText[]={0.86,0.91,0.88,1}; shadow=2; text=""; };
            class KeyBase3: COMSPEC_RscText { idc=83; x=0; y=0; w=0; h=0; colorBackground[]={0.5,0.55,0.52,0.95}; };
            class KeyCap3: COMSPEC_RscTextCenter { idc=23; x=0; y=0; w=0; h=0; font="COMSPEC_BarlowBold"; sizeEx="0.021 * safeZoneH"; colorText[]={0.06,0.08,0.07,1}; colorBackground[]={0.92,0.95,0.93,0.97}; text=""; };
            class KeyLabel3: COMSPEC_RscText { idc=43; x=0; y=0; w=0; h=0; sizeEx="0.023 * safeZoneH"; colorText[]={0.86,0.91,0.88,1}; shadow=2; text=""; };
            class KeyBase4: COMSPEC_RscText { idc=84; x=0; y=0; w=0; h=0; colorBackground[]={0.5,0.55,0.52,0.95}; };
            class KeyCap4: COMSPEC_RscTextCenter { idc=24; x=0; y=0; w=0; h=0; font="COMSPEC_BarlowBold"; sizeEx="0.021 * safeZoneH"; colorText[]={0.06,0.08,0.07,1}; colorBackground[]={0.92,0.95,0.93,0.97}; text=""; };
            class KeyLabel4: COMSPEC_RscText { idc=44; x=0; y=0; w=0; h=0; sizeEx="0.023 * safeZoneH"; colorText[]={0.86,0.91,0.88,1}; shadow=2; text=""; };
            class KeyBase5: COMSPEC_RscText { idc=85; x=0; y=0; w=0; h=0; colorBackground[]={0.5,0.55,0.52,0.95}; };
            class KeyCap5: COMSPEC_RscTextCenter { idc=25; x=0; y=0; w=0; h=0; font="COMSPEC_BarlowBold"; sizeEx="0.021 * safeZoneH"; colorText[]={0.06,0.08,0.07,1}; colorBackground[]={0.92,0.95,0.93,0.97}; text=""; };
            class KeyLabel5: COMSPEC_RscText { idc=45; x=0; y=0; w=0; h=0; sizeEx="0.023 * safeZoneH"; colorText[]={0.86,0.91,0.88,1}; shadow=2; text=""; };
            class KeyBase6: COMSPEC_RscText { idc=86; x=0; y=0; w=0; h=0; colorBackground[]={0.5,0.55,0.52,0.95}; };
            class KeyCap6: COMSPEC_RscTextCenter { idc=26; x=0; y=0; w=0; h=0; font="COMSPEC_BarlowBold"; sizeEx="0.021 * safeZoneH"; colorText[]={0.06,0.08,0.07,1}; colorBackground[]={0.92,0.95,0.93,0.97}; text=""; };
            class KeyLabel6: COMSPEC_RscText { idc=46; x=0; y=0; w=0; h=0; sizeEx="0.023 * safeZoneH"; colorText[]={0.86,0.91,0.88,1}; shadow=2; text=""; };
            class KeyBase7: COMSPEC_RscText { idc=87; x=0; y=0; w=0; h=0; colorBackground[]={0.5,0.55,0.52,0.95}; };
            class KeyCap7: COMSPEC_RscTextCenter { idc=27; x=0; y=0; w=0; h=0; font="COMSPEC_BarlowBold"; sizeEx="0.021 * safeZoneH"; colorText[]={0.06,0.08,0.07,1}; colorBackground[]={0.92,0.95,0.93,0.97}; text=""; };
            class KeyLabel7: COMSPEC_RscText { idc=47; x=0; y=0; w=0; h=0; sizeEx="0.023 * safeZoneH"; colorText[]={0.86,0.91,0.88,1}; shadow=2; text=""; };
            class KeySep0: COMSPEC_RscText { idc=60; x=0; y=0; w=0; h=0; colorBackground[]={1,1,1,0.16}; };
            class KeySep1: COMSPEC_RscText { idc=61; x=0; y=0; w=0; h=0; colorBackground[]={1,1,1,0.16}; };
            class KeySep2: COMSPEC_RscText { idc=62; x=0; y=0; w=0; h=0; colorBackground[]={1,1,1,0.16}; };
            class KeySep3: COMSPEC_RscText { idc=63; x=0; y=0; w=0; h=0; colorBackground[]={1,1,1,0.16}; };
            // Messages (photo envoyée, patientez…) juste au-dessus de la barre.
            class Count: COMSPEC_RscText { idc=4; style=2; x="safeZoneX + safeZoneW * 0.25"; y="safeZoneY + safeZoneH * 0.812"; w="safeZoneW * 0.5"; h="safeZoneH * 0.03"; colorText[]={0.36,0.78,0.42,1}; shadow=2; sizeEx="0.024 * safeZoneH"; text=""; };
        };
    };
};
