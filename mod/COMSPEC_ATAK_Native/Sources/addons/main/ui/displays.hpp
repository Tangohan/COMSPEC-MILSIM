#include "ui_ids.hpp"
#include "defines.hpp"
class COMSPEC_RscDisplayATAK {
    idd=COMSPEC_ATAK_IDD; movingEnable=0; enableSimulation=1;
    onLoad="_this call comspec_atak_native_fnc_displayLoad";
    onUnload="_this call comspec_atak_native_fnc_displayUnload";
    class controlsBackground {
        class Frame: COMSPEC_RscPanel { idc=COMSPEC_ATAK_IDC_FRAME; x=ATAK_DEV_X; y=ATAK_DEV_Y; w=ATAK_DEV_W; h=ATAK_DEV_H; colorBackground[]=ATAK_BG1; };
        class StatusBar: COMSPEC_RscPanel { idc=COMSPEC_ATAK_IDC_STATUSBAR; x=ATAK_DEV_X; y=ATAK_DEV_Y; w=ATAK_DEV_W; h=0; colorBackground[]=ATAK_BG0; };
        class AppBar: COMSPEC_RscPanel { idc=COMSPEC_ATAK_IDC_APPBAR; x=ATAK_DEV_X; y=ATAK_DEV_Y; w=ATAK_DEV_W; h=0; colorBackground[]=ATAK_BG1; };
        class Dock: COMSPEC_RscPanel { idc=COMSPEC_ATAK_IDC_DOCK; x=ATAK_DEV_X; y=ATAK_DEV_Y; w=ATAK_DEV_W; h=0; colorBackground[]=ATAK_BG0; };
        class Rail: COMSPEC_RscPanel { idc=COMSPEC_ATAK_IDC_RAIL; x=ATAK_DEV_X; y=ATAK_DEV_Y; w=0; h=0; colorBackground[]=ATAK_BG0; };
        class Inspector: COMSPEC_RscPanel { idc=COMSPEC_ATAK_IDC_INSPECTOR; x=ATAK_DEV_X; y=ATAK_DEV_Y; w=0; h=0; colorBackground[]=ATAK_BG1; };
    };
    class controls {
        class StatusLeft: COMSPEC_RscLabel { idc=COMSPEC_ATAK_IDC_STATUS_LEFT; x=ATAK_DEV_X; y=ATAK_DEV_Y; w=ATAK_DEV_W; h=0; colorText[]=ATAK_TEXT; };
        class StatusRight: COMSPEC_RscLabel { idc=COMSPEC_ATAK_IDC_STATUS_RIGHT; style=1; x=ATAK_DEV_X; y=ATAK_DEV_Y; w=ATAK_DEV_W; h=0; };
        class Back: COMSPEC_RscButton { idc=COMSPEC_ATAK_IDC_BACK; text="<"; x=ATAK_DEV_X; y=ATAK_DEV_Y; w=0; h=0; action="[] call comspec_atak_native_fnc_back"; tooltip="Retour"; };
        class Title: COMSPEC_RscText { idc=COMSPEC_ATAK_IDC_TITLE; font="RobotoCondensedBold"; text="APPLICATIONS"; x=ATAK_DEV_X; y=ATAK_DEV_Y; w=ATAK_DEV_W; h=0; };
        class Mode: COMSPEC_RscButton { idc=COMSPEC_ATAK_IDC_MODE; text="[ ]"; x=ATAK_DEV_X; y=ATAK_DEV_Y; w=0; h=0; action="[] call comspec_atak_native_fnc_modeToggle"; tooltip="Basculer mini / plein écran"; };
        class Home: COMSPEC_RscButton { idc=COMSPEC_ATAK_IDC_HOME; text="APPS"; x=ATAK_DEV_X; y=ATAK_DEV_Y; w=0; h=0; action="['LAUNCHER'] call comspec_atak_native_fnc_navigate"; tooltip="Lanceur d'applications"; };
        class Map: COMSPEC_RscMap { idc=COMSPEC_ATAK_IDC_MAP; x=ATAK_DEV_X; y=ATAK_DEV_Y; w=ATAK_DEV_W; h=ATAK_DEV_H; onDraw="_this call comspec_atak_native_fnc_mapOnDraw"; onMouseButtonDown="_this call comspec_atak_native_fnc_mapMouseButtonDown"; };
        class Content: COMSPEC_RscControlsGroup { idc=COMSPEC_ATAK_IDC_CONTENT; x=ATAK_DEV_X; y=ATAK_DEV_Y; w=ATAK_DEV_W; h=ATAK_DEV_H; };
        class InspectorText: COMSPEC_RscStructuredText { idc=COMSPEC_ATAK_IDC_INSPECTOR_TEXT; x=ATAK_DEV_X; y=ATAK_DEV_Y; w=0; h=0; text=""; };
    };
};
