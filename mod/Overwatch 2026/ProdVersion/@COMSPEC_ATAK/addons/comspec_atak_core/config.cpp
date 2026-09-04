class CfgPatches
{
    class comspec_atak_core
    {
        name = "COMSPEC ATAK Core";
        author = "COMSPEC";
        requiredVersion = 2.20;
        requiredAddons[] = {"cba_main","cba_xeh","cba_settings","A3_UI_F"};
        units[] = {};
        weapons[] = {};
        version = 1.8.20;
        versionStr = "1.8.20";
        versionAr[] = {1,8,20};
    };
};

class CfgFunctions
{
    class COMSPEC
    {
        tag = "COMSPEC";
        class ATAK
        {
            file = "z\comspec_atak\addons\comspec_atak_core\functions";
            class chatAppend {};
            class chatClear {};
            class chatPollAthena {};
            class chatSharePosition {};
            class chatStart {};
            class closeTablet {};
            class compatAction {};
            class compatSnapshot {};
            class debugSelfTest {};
            class extensionCall {};
            class extensionCapabilities {};
            class executeCapability {};
            class hasCapability {};
            class featureInit {};
            class getState {};
            class log {};
            class logSessionStart {};
            class logClear {};
            class logPush {};
            class mapApplyTexture {};
            class mapCenterOnPlayer {};
            class mapClearMarks {};
            class mapContextAction {};
            class mapContextClose {};
            class mapContextOpen {};
            class mapDrawOperational {};
            class mapOnMouseButtonDown {};
            class mapPingCreate {};
            class mapPingOnRemote {};
            class mapSetLayer {};
            class mapSetTool {};
            class mapToggleBft {};
            class mapToggleTexture {};
            class mapUpdateInfo {};
            class mapUseToolAt {};
            class scenePersist {};
            class markerCreate {};
            class markerList {};
            class markerOnRemote {};
            class markerUpdate {};
            class markerUpdateOnRemote {};
            class markerDelete {};
            class markerDeleteOnRemote {};
            class miniDraw {};
            class miniOnLoad {};
            class miniOnUnload {};
            class networkApplyMode {};
            class networkApplyGameAuth {};
            class networkAuthPassword {};
            class networkAuthWatchdog {};
            class networkConnectAthena {};
            class networkConnectP2P {};
            class networkCredentials {};
            class networkStartPairing {};
            class networkRedeemPairingCode {};
            class networkRecoveryCode {};
            class networkDebugState {};
            class networkDisconnect {};
            class networkShowConnection {};
            class networkSteamUid {};
            class networkUpdateConnectionUI {};
            class notify {};
            class openApp {};
            class openTablet {};
            class p2pBroadcastPresence {};
            class p2pGetPeers {};
            class p2pOnChat {};
            class p2pOnLeave {};
            class p2pOnPresence {};
            class p2pPrunePeers {};
            class p2pSendChat {};
            class p2pStart {};
            class p2pStop {};
            class parseAuthState {};
            class playerSnapshot {};
            class refreshChat {};
            class refreshUI {};
            class registerCapability {};
            class runtimeInit {};
            class selectEntity {};
            class sendChat {};
            class setState {};
            class settingsOnServerChange {};
            class settingsRefresh {};
            class settingsSaveServer {};
            class tabletDragBegin {};
            class tabletDragEnd {};
            class tabletDragUpdate {};
            class tabletEnterMini {};
            class tabletExitMini {};
            class tabletResetPosition {};
            class tabletSetOffset {};
            class tabletToggleMini {};
            class taskCreate {};
            class taskList {};
            class taskOnRemote {};
            class taskUpdate {};
            class taskUpdateOnRemote {};
            class toggleTablet {};
            class uiOnLoad {};
            class uiOnUnload {};
            class viewLoad {};
            class viewSave {};
            class webEnterDesktop {};
            class webExecJS {};
            class webJSDialog {};
            class webJsEscape {};
            class webLayout {};
            class webMapHide {};
            class webMapHtmlChrome {};
            class webMapRaise {};
            class webMapShow {};
            class webMapSetViewport {};
            class webMapInput {};
            class webOnLoad {};
            class webPageLoaded {};
            class webPushState {};
            class webPushTelemetry {};
        };
    };
};

class Extended_PreInit_EventHandlers
{
    class comspec_atak_core
    {
        init = "call compile preprocessFileLineNumbers '\z\comspec_atak\addons\comspec_atak_core\XEH_preInit.sqf'";
    };
};

class Extended_PostInit_EventHandlers
{
    class comspec_atak_core
    {
        clientInit = "call compile preprocessFileLineNumbers '\z\comspec_atak\addons\comspec_atak_core\XEH_postInitClient.sqf'";
    };
};

class CfgCommands
{
    allowedHTMLLoadURIs[] += {
        "https://jetelain.github.io/*",
        "https://cdn.jsdelivr.net/*",
        "https://maps.ibonn.de/*",
        "https://atlas.plan-ops.fr/*",
        "https://athena.ttrd.fr/*",
        "https://compsec.ttrd.fr/*",
        "http://127.0.0.1/*",
        "http://localhost/*",
        "file://z/comspec_atak/addons/comspec_atak_core/web/*",
        "file://*/Arma 3 - COMSPEC/Maps/*"
    };
};

class RscText;
class RscStructuredText;
class RscButton;
class RscMapControl;
class RscPicture;
class RscControlsGroup;

#include "ui\runtime.hpp"
