#include "..\script_component.hpp"

if (missionNamespace getVariable ["Iceman_ATAK_Route_drawHooksInstalled", false]) exitWith {};
Iceman_ATAK_Route_drawHooksInstalled = true;

Iceman_ATAK_Route_base_cTabOnDrawbft = missionNamespace getVariable ["cTabOnDrawbft", {}];
Iceman_ATAK_Route_base_cTabOnDrawbftVeh = missionNamespace getVariable ["cTabOnDrawbftVeh", {}];
Iceman_ATAK_Route_base_cTabOnDrawbftTAD = missionNamespace getVariable ["cTabOnDrawbftTAD", {}];
Iceman_ATAK_Route_base_cTabOnDrawbftTADdialog = missionNamespace getVariable ["cTabOnDrawbftTADdialog", {}];
Iceman_ATAK_Route_base_cTabOnDrawbftAndroid = missionNamespace getVariable ["cTabOnDrawbftAndroid", {}];
Iceman_ATAK_Route_base_cTabOnDrawbftAndroidDsp = missionNamespace getVariable ["cTabOnDrawbftAndroidDsp", {}];
Iceman_ATAK_Route_base_cTabOnDrawbftmicroDAGRdsp = missionNamespace getVariable ["cTabOnDrawbftmicroDAGRdsp", {}];
Iceman_ATAK_Route_base_cTabOnDrawbftMicroDAGRdlg = missionNamespace getVariable ["cTabOnDrawbftMicroDAGRdlg", {}];

cTabOnDrawbft = {
    _this call Iceman_ATAK_Route_base_cTabOnDrawbft;
    _this call Iceman_fnc_route_draw;
};
cTabOnDrawbftVeh = {
    _this call Iceman_ATAK_Route_base_cTabOnDrawbftVeh;
    _this call Iceman_fnc_route_draw;
};
cTabOnDrawbftTAD = {
    _this call Iceman_ATAK_Route_base_cTabOnDrawbftTAD;
    _this call Iceman_fnc_route_draw;
};
cTabOnDrawbftTADdialog = {
    _this call Iceman_ATAK_Route_base_cTabOnDrawbftTADdialog;
    _this call Iceman_fnc_route_draw;
};
cTabOnDrawbftAndroid = {
    _this call Iceman_ATAK_Route_base_cTabOnDrawbftAndroid;
    _this call Iceman_fnc_route_draw;
};
cTabOnDrawbftAndroidDsp = {
    _this call Iceman_ATAK_Route_base_cTabOnDrawbftAndroidDsp;
    _this call Iceman_fnc_route_draw;
};
cTabOnDrawbftmicroDAGRdsp = {
    _this call Iceman_ATAK_Route_base_cTabOnDrawbftmicroDAGRdsp;
    _this call Iceman_fnc_route_draw;
};
cTabOnDrawbftMicroDAGRdlg = {
    _this call Iceman_ATAK_Route_base_cTabOnDrawbftMicroDAGRdlg;
    _this call Iceman_fnc_route_draw;
};
