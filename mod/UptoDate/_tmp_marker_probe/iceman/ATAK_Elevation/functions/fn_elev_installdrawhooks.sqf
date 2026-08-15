#include "..\script_component.hpp"

if (missionNamespace getVariable ["Iceman_ATAK_Elevation_drawHooksInstalled", false]) exitWith {};
Iceman_ATAK_Elevation_drawHooksInstalled = true;

Iceman_ATAK_Elevation_base_cTabOnDrawbft = missionNamespace getVariable ["cTabOnDrawbft", {}];
Iceman_ATAK_Elevation_base_cTabOnDrawbftVeh = missionNamespace getVariable ["cTabOnDrawbftVeh", {}];
Iceman_ATAK_Elevation_base_cTabOnDrawbftTAD = missionNamespace getVariable ["cTabOnDrawbftTAD", {}];
Iceman_ATAK_Elevation_base_cTabOnDrawbftTADdialog = missionNamespace getVariable ["cTabOnDrawbftTADdialog", {}];
Iceman_ATAK_Elevation_base_cTabOnDrawbftAndroid = missionNamespace getVariable ["cTabOnDrawbftAndroid", {}];
Iceman_ATAK_Elevation_base_cTabOnDrawbftAndroidDsp = missionNamespace getVariable ["cTabOnDrawbftAndroidDsp", {}];
Iceman_ATAK_Elevation_base_cTabOnDrawbftmicroDAGRdsp = missionNamespace getVariable ["cTabOnDrawbftmicroDAGRdsp", {}];
Iceman_ATAK_Elevation_base_cTabOnDrawbftMicroDAGRdlg = missionNamespace getVariable ["cTabOnDrawbftMicroDAGRdlg", {}];

cTabOnDrawbft = {
    _this call Iceman_ATAK_Elevation_base_cTabOnDrawbft;
    try {_this call Iceman_fnc_elev_draw} catch {};
};
cTabOnDrawbftVeh = {
    _this call Iceman_ATAK_Elevation_base_cTabOnDrawbftVeh;
    try {_this call Iceman_fnc_elev_draw} catch {};
};
cTabOnDrawbftTAD = {
    _this call Iceman_ATAK_Elevation_base_cTabOnDrawbftTAD;
    try {_this call Iceman_fnc_elev_draw} catch {};
};
cTabOnDrawbftTADdialog = {
    _this call Iceman_ATAK_Elevation_base_cTabOnDrawbftTADdialog;
    try {_this call Iceman_fnc_elev_draw} catch {};
};
cTabOnDrawbftAndroid = {
    _this call Iceman_ATAK_Elevation_base_cTabOnDrawbftAndroid;
    try {_this call Iceman_fnc_elev_draw} catch {};
};
cTabOnDrawbftAndroidDsp = {
    _this call Iceman_ATAK_Elevation_base_cTabOnDrawbftAndroidDsp;
    try {_this call Iceman_fnc_elev_draw} catch {};
};
cTabOnDrawbftmicroDAGRdsp = {
    _this call Iceman_ATAK_Elevation_base_cTabOnDrawbftmicroDAGRdsp;
    try {_this call Iceman_fnc_elev_draw} catch {};
};
cTabOnDrawbftMicroDAGRdlg = {
    _this call Iceman_ATAK_Elevation_base_cTabOnDrawbftMicroDAGRdlg;
    try {_this call Iceman_fnc_elev_draw} catch {};
};
