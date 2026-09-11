#include "ui_ids.hpp"
#include "defines.hpp"
class COMSPEC_RscDisplayATAK {
    idd=COMSPEC_ATAK_IDD; movingEnable=0; enableSimulation=1;
    onLoad="_this call comspec_atak_native_fnc_displayLoad";
    onUnload="_this call comspec_atak_native_fnc_displayUnload";
    class controlsBackground {
        class Root: COMSPEC_RscPanel { idc=COMSPEC_ATAK_IDC_ROOT; x=ATAK_X; y=ATAK_Y; w=ATAK_W; h=ATAK_H; colorBackground[]=ATAK_BG0; };
        class Top: COMSPEC_RscPanel { idc=COMSPEC_ATAK_IDC_TOPBAR; x=ATAK_X; y=ATAK_Y; w=ATAK_W; h=ATAK_TOP_H; };
        class Rail: COMSPEC_RscPanel { idc=COMSPEC_ATAK_IDC_RAIL; x=ATAK_X; y=ATAK_BODY_Y; w=ATAK_RAIL_W; h=ATAK_BODY_H; };
        class Inspector: COMSPEC_RscPanel { idc=COMSPEC_ATAK_IDC_INSPECTOR; x="safeZoneX + safeZoneW - ATAK_INSPECT_W"; y=ATAK_BODY_Y; w=ATAK_INSPECT_W; h=ATAK_BODY_H; };
        class Bottom: COMSPEC_RscPanel { idc=COMSPEC_ATAK_IDC_STATUS; x=ATAK_X; y="safeZoneY + safeZoneH - ATAK_BOTTOM_H"; w=ATAK_W; h=ATAK_BOTTOM_H; };
    };
    class controls {
        class Title: COMSPEC_RscText { idc=COMSPEC_ATAK_IDC_TITLE; text="COMSPEC ATAK  NATIVE"; x="safeZoneX + safeZoneW*0.012"; y=ATAK_Y; w="safeZoneW*0.38"; h=ATAK_TOP_H; colorText[]=ATAK_GREEN; };
        class Network: COMSPEC_RscText { idc=COMSPEC_ATAK_IDC_NET; text="ATHENA OFFLINE"; style=2; x="safeZoneX + safeZoneW*0.40"; y=ATAK_Y; w="safeZoneW*0.22"; h=ATAK_TOP_H; };
        class Clock: COMSPEC_RscText { idc=COMSPEC_ATAK_IDC_CLOCK; text="--:--:--"; style=1; x="safeZoneX + safeZoneW*0.82"; y=ATAK_Y; w="safeZoneW*0.16"; h=ATAK_TOP_H; };
        class Map: COMSPEC_RscMap { idc=COMSPEC_ATAK_IDC_MAP; x=ATAK_CENTER_X; y=ATAK_BODY_Y; w=ATAK_CENTER_W; h=ATAK_BODY_H; onDraw="_this call comspec_atak_native_fnc_mapOnDraw"; onMouseButtonDown="_this call comspec_atak_native_fnc_mapMouseButtonDown"; };
        class Content: COMSPEC_RscControlsGroup { idc=COMSPEC_ATAK_IDC_CONTENT; x=ATAK_CENTER_X; y=ATAK_BODY_Y; w=ATAK_CENTER_W; h=ATAK_BODY_H; };
        class InspectorText: COMSPEC_RscStructuredText { idc=COMSPEC_ATAK_IDC_INSPECTOR_TEXT; x="safeZoneX + safeZoneW - ATAK_INSPECT_W + safeZoneW*0.012"; y="ATAK_BODY_Y + safeZoneH*0.015"; w="ATAK_INSPECT_W - safeZoneW*0.024"; h="ATAK_BODY_H - safeZoneH*0.03"; text="<t color='#5cc76b'>SITUATION</t><br/>Aucune sélection"; };
    };
};
