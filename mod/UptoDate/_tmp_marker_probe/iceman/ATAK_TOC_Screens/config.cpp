#include "script_component.hpp"

class CfgPatches
{
    class Iceman_ATAK_TOC_Screens
    {
        name = "Iceman ATAK TOC Screens";
        author = "Cole / Codex";
        requiredVersion = 2.14;
        requiredAddons[] = {"A3_Ui_F", "A3_Weapons_F", "cba_main", "cba_common", "ace_interact_menu", "cTab", "ctab_core"};
        units[] = {};
        weapons[] = {"Iceman_TOC_ViewDevice"};
    };
};

class CfgFunctions
{
    class Iceman
    {
        class ATAK_TOC_Screens
        {
            file = "\ATAK_TOC_Screens\functions";
            class toc_applyDialog {};
            class toc_applyTransform {};
            class toc_applyVisionLocal {};
            class toc_applyZoomLocal {};
            class toc_deleteProfile {};
            class toc_findViewStream {};
            class toc_findFeedObject {};
            class toc_getFeeds {};
            class toc_getActiveViewStreams {};
            class toc_getProfiles {};
            class toc_getSettings {};
            class toc_getVisionValue {};
            class toc_getZoomValue {};
            class toc_installAce {};
            class toc_isScreenCandidate {};
            class toc_loadProfile {};
            class toc_normalizeSettings {};
            class toc_onDialogLoad {};
            class toc_openDialog {};
            class toc_openViewDevice {};
            class toc_cameraLookPos {};
            class toc_posToGrid {};
            class toc_applyPresenterLocal {};
            class toc_setPresenterGlobal {};
            class toc_snapshotGlobal {};
            class toc_addSnapshotLocal {};
            class toc_registerScreenLocal {};
            class toc_readDialog {};
            class toc_refreshDialog {};
            class toc_saveProfile {};
            class toc_setStatus {};
            class toc_setVisionGlobal {};
            class toc_setZoomGlobal {};
            class toc_startStream {};
            class toc_stopDialog {};
            class toc_stopStream {};
            class toc_syncStreamGlobal {};
            class toc_syncStopGlobal {};
            class toc_unregisterScreenLocal {};
            class toc_viewDeviceClear {};
            class toc_viewDeviceKeyDown {};
            class toc_viewDeviceOnLoad {};
            class toc_viewDeviceOnUnload {};
            class toc_viewDeviceOpenCamera {};
            class toc_viewDeviceRefresh {};
        };
    };
};

class Extended_PostInit_EventHandlers
{
    class Iceman_ATAK_TOC_Screens
    {
        init = "call compile preprocessFileLineNumbers '\ATAK_TOC_Screens\XEH_postInitClient.sqf'";
    };
};

class RscText;
class RscFrame;
class RscButton;
class RscEdit;
class RscCombo;
class RscPicture;

class CfgWeapons
{
    class CBA_MiscItem;
    class CBA_MiscItem_ItemInfo;

    class Iceman_TOC_ViewDevice: CBA_MiscItem
    {
        author = "Cole / Codex";
        scope = 2;
        scopeArsenal = 2;
        displayName = "TOC View Device";
        descriptionShort = "Portable viewer for active TOC screen feeds.";
        model = "\A3\Weapons_F\Items\GPS.p3d";
        picture = "\A3\Weapons_F\Data\UI\gear_item_gps_CA.paa";
        ACE_isTool = 1;

        class ItemInfo: CBA_MiscItem_ItemInfo
        {
            mass = 8;
        };
    };
};

class Iceman_TOC_Text: RscText
{
    sizeEx = 0.032;
    colorText[] = {1,1,1,1};
    colorBackground[] = {0,0,0,0};
    font = "RobotoCondensed";
};

class Iceman_TOC_Label: Iceman_TOC_Text
{
    sizeEx = 0.028;
    colorText[] = {0.85,0.9,0.92,1};
};

class Iceman_TOC_Edit: RscEdit
{
    sizeEx = 0.03;
    colorText[] = {1,1,1,1};
    colorBackground[] = {0.03,0.06,0.07,0.95};
};

class Iceman_TOC_Button: RscButton
{
    sizeEx = 0.03;
    colorText[] = {1,1,1,1};
    colorBackground[] = {0.12,0.17,0.18,0.95};
    colorBackgroundActive[] = {0.22,0.32,0.34,1};
};

class Iceman_TOC_Picture: RscPicture
{
    colorText[] = {1,1,1,1};
    colorBackground[] = {0,0,0,1};
};

class Iceman_TOC_ListBox
{
    type = 5;
    style = 16;
    font = "RobotoCondensed";
    sizeEx = 0.028;
    rowHeight = 0.035;
    colorText[] = {1,1,1,1};
    colorSelect[] = {0,0,0,1};
    colorSelect2[] = {0,0,0,1};
    colorSelectBackground[] = {0.7,0.82,0.86,1};
    colorSelectBackground2[] = {0.7,0.82,0.86,1};
    colorBackground[] = {0.03,0.06,0.07,0.95};
    colorDisabled[] = {1,1,1,0.25};
    period = 1;
    maxHistoryDelay = 1;
    soundSelect[] = {"",0.1,1};
    class ListScrollBar
    {
        color[] = {1,1,1,1};
        thumb = "\A3\ui_f\data\gui\cfg\scrollbar\thumb_ca.paa";
        arrowEmpty = "\A3\ui_f\data\gui\cfg\scrollbar\arrowEmpty_ca.paa";
        arrowFull = "\A3\ui_f\data\gui\cfg\scrollbar\arrowFull_ca.paa";
        border = "\A3\ui_f\data\gui\cfg\scrollbar\border_ca.paa";
        shadow = 0;
        scrollSpeed = 0.06;
        width = 0;
        height = 0;
        autoScrollEnabled = 0;
        autoScrollSpeed = -1;
        autoScrollDelay = 5;
        autoScrollRewind = 0;
    };
};

class Iceman_TOC_ScreenDialog
{
    idd = 94100;
    movingEnable = 1;
    enableSimulation = 1;
    onLoad = "_this call Iceman_fnc_toc_onDialogLoad";

    class controlsBackground
    {
        class Backdrop: RscText
        {
            idc = -1;
            x = "safeZoneX + safeZoneW * 0.31";
            y = "safeZoneY + safeZoneH * 0.18";
            w = "safeZoneW * 0.38";
            h = "safeZoneH * 0.50";
            colorBackground[] = {0.06,0.09,0.10,0.96};
        };
        class Header: RscText
        {
            idc = -1;
            x = "safeZoneX + safeZoneW * 0.31";
            y = "safeZoneY + safeZoneH * 0.18";
            w = "safeZoneW * 0.38";
            h = "safeZoneH * 0.045";
            text = "TOC Screen Feed";
            colorBackground[] = {0.02,0.03,0.035,1};
            colorText[] = {1,1,1,1};
            sizeEx = 0.038;
            style = 2;
        };
        class Frame: RscFrame
        {
            idc = -1;
            x = "safeZoneX + safeZoneW * 0.315";
            y = "safeZoneY + safeZoneH * 0.235";
            w = "safeZoneW * 0.37";
            h = "safeZoneH * 0.37";
        };
    };

    class controls
    {
        class FeedLabel: Iceman_TOC_Label
        {
            idc = -1;
            x = "safeZoneX + safeZoneW * 0.325";
            y = "safeZoneY + safeZoneH * 0.245";
            w = "safeZoneW * 0.16";
            h = "safeZoneH * 0.03";
            text = "Feed";
        };
        class FeedList: Iceman_TOC_ListBox
        {
            idc = 94101;
            x = "safeZoneX + safeZoneW * 0.325";
            y = "safeZoneY + safeZoneH * 0.275";
            w = "safeZoneW * 0.35";
            h = "safeZoneH * 0.20";
            sizeEx = 0.028;
        };

        class ProfileLabel: Iceman_TOC_Label
        {
            idc = -1;
            x = "safeZoneX - 10";
            y = "safeZoneY - 10";
            w = "safeZoneW * 0.01";
            h = "safeZoneH * 0.03";
            text = "";
        };
        class ProfileCombo: RscCombo
        {
            idc = 94102;
            x = "safeZoneX - 10";
            y = "safeZoneY - 10";
            w = "safeZoneW * 0.01";
            h = "safeZoneH * 0.032";
            sizeEx = 0.026;
        };
        class ProfileName: Iceman_TOC_Edit
        {
            idc = 94109;
            x = "safeZoneX - 10";
            y = "safeZoneY - 10";
            w = "safeZoneW * 0.01";
            h = "safeZoneH * 0.032";
            text = "Default";
        };

        class XLabel: Iceman_TOC_Label
        {
            idc = -1;
            x = "safeZoneX - 10";
            y = "safeZoneY - 10";
            w = "safeZoneW * 0.01";
            h = "safeZoneH * 0.03";
            text = "";
        };
        class XEdit: Iceman_TOC_Edit
        {
            idc = 94103;
            x = "safeZoneX - 10";
            y = "safeZoneY - 10";
            w = "safeZoneW * 0.01";
            h = "safeZoneH * 0.032";
            text = "0";
        };
        class YLabel: Iceman_TOC_Label
        {
            idc = -1;
            x = "safeZoneX - 10";
            y = "safeZoneY - 10";
            w = "safeZoneW * 0.01";
            h = "safeZoneH * 0.03";
            text = "";
        };
        class YEdit: Iceman_TOC_Edit
        {
            idc = 94104;
            x = "safeZoneX - 10";
            y = "safeZoneY - 10";
            w = "safeZoneW * 0.01";
            h = "safeZoneH * 0.032";
            text = "0";
        };
        class ZLabel: Iceman_TOC_Label
        {
            idc = -1;
            x = "safeZoneX - 10";
            y = "safeZoneY - 10";
            w = "safeZoneW * 0.01";
            h = "safeZoneH * 0.03";
            text = "";
        };
        class ZEdit: Iceman_TOC_Edit
        {
            idc = 94105;
            x = "safeZoneX - 10";
            y = "safeZoneY - 10";
            w = "safeZoneW * 0.01";
            h = "safeZoneH * 0.032";
            text = "0.05";
        };
        class PipLabel: Iceman_TOC_Label
        {
            idc = -1;
            x = "safeZoneX - 10";
            y = "safeZoneY - 10";
            w = "safeZoneW * 0.01";
            h = "safeZoneH * 0.03";
            text = "";
        };
        class PipEdit: Iceman_TOC_Label
        {
            idc = 94107;
            x = "safeZoneX - 10";
            y = "safeZoneY - 10";
            w = "safeZoneW * 0.01";
            h = "safeZoneH * 0.032";
            text = "Game";
            colorBackground[] = {0.03,0.06,0.07,0.95};
        };
        class VisionLabel: Iceman_TOC_Label
        {
            idc = -1;
            x = "safeZoneX + safeZoneW * 0.325";
            y = "safeZoneY + safeZoneH * 0.505";
            w = "safeZoneW * 0.06";
            h = "safeZoneH * 0.03";
            text = "Vision";
        };
        class VisionCombo: RscCombo
        {
            idc = 94117;
            x = "safeZoneX + safeZoneW * 0.385";
            y = "safeZoneY + safeZoneH * 0.505";
            w = "safeZoneW * 0.29";
            h = "safeZoneH * 0.035";
            sizeEx = 0.026;
        };
        class PitchLabel: Iceman_TOC_Label
        {
            idc = -1;
            x = "safeZoneX - 10";
            y = "safeZoneY - 10";
            w = "safeZoneW * 0.01";
            h = "safeZoneH * 0.03";
            text = "";
        };
        class PitchEdit: Iceman_TOC_Edit
        {
            idc = 94113;
            x = "safeZoneX - 10";
            y = "safeZoneY - 10";
            w = "safeZoneW * 0.01";
            h = "safeZoneH * 0.032";
            text = "0";
        };
        class RollLabel: Iceman_TOC_Label
        {
            idc = -1;
            x = "safeZoneX - 10";
            y = "safeZoneY - 10";
            w = "safeZoneW * 0.01";
            h = "safeZoneH * 0.03";
            text = "";
        };
        class RollEdit: Iceman_TOC_Edit
        {
            idc = 94114;
            x = "safeZoneX - 10";
            y = "safeZoneY - 10";
            w = "safeZoneW * 0.01";
            h = "safeZoneH * 0.032";
            text = "0";
        };
        class ModeLabel: Iceman_TOC_Label
        {
            idc = -1;
            x = "safeZoneX - 10";
            y = "safeZoneY - 10";
            w = "safeZoneW * 0.01";
            h = "safeZoneH * 0.03";
            text = "";
        };
        class ModeCombo: RscCombo
        {
            idc = 94115;
            x = "safeZoneX - 10";
            y = "safeZoneY - 10";
            w = "safeZoneW * 0.01";
            h = "safeZoneH * 0.032";
            sizeEx = 0.026;
        };
        class SurfaceLabel: Iceman_TOC_Label
        {
            idc = -1;
            x = "safeZoneX - 10";
            y = "safeZoneY - 10";
            w = "safeZoneW * 0.01";
            h = "safeZoneH * 0.03";
            text = "";
        };
        class SurfaceEdit: Iceman_TOC_Edit
        {
            idc = 94116;
            x = "safeZoneX - 10";
            y = "safeZoneY - 10";
            w = "safeZoneW * 0.01";
            h = "safeZoneH * 0.032";
            text = "0";
        };
        class StatusText: Iceman_TOC_Label
        {
            idc = 94108;
            x = "safeZoneX + safeZoneW * 0.325";
            y = "safeZoneY + safeZoneH * 0.555";
            w = "safeZoneW * 0.35";
            h = "safeZoneH * 0.04";
            text = "";
        };

        class ApplyButton: Iceman_TOC_Button
        {
            idc = -1;
            x = "safeZoneX + safeZoneW * 0.325";
            y = "safeZoneY + safeZoneH * 0.615";
            w = "safeZoneW * 0.105";
            h = "safeZoneH * 0.04";
            text = "Apply Stream";
            action = "call Iceman_fnc_toc_applyDialog";
        };
        class StopButton: Iceman_TOC_Button
        {
            idc = -1;
            x = "safeZoneX + safeZoneW * 0.435";
            y = "safeZoneY + safeZoneH * 0.615";
            w = "safeZoneW * 0.075";
            h = "safeZoneH * 0.04";
            text = "Stop";
            action = "call Iceman_fnc_toc_stopDialog";
        };
        class LoadButton: Iceman_TOC_Button
        {
            idc = -1;
            x = "safeZoneX - 10";
            y = "safeZoneY - 10";
            w = "safeZoneW * 0.01";
            h = "safeZoneH * 0.01";
            text = "";
            action = "";
        };
        class SaveButton: Iceman_TOC_Button
        {
            idc = -1;
            x = "safeZoneX - 10";
            y = "safeZoneY - 10";
            w = "safeZoneW * 0.01";
            h = "safeZoneH * 0.01";
            text = "";
            action = "";
        };
        class DeleteButton: Iceman_TOC_Button
        {
            idc = -1;
            x = "safeZoneX - 10";
            y = "safeZoneY - 10";
            w = "safeZoneW * 0.01";
            h = "safeZoneH * 0.01";
            text = "";
            action = "";
        };
        class CloseButton: Iceman_TOC_Button
        {
            idc = -1;
            x = "safeZoneX + safeZoneW * 0.595";
            y = "safeZoneY + safeZoneH * 0.615";
            w = "safeZoneW * 0.08";
            h = "safeZoneH * 0.04";
            text = "Close";
            action = "closeDialog 0";
        };
    };
};

class Iceman_TOC_ViewDeviceDialog
{
    idd = 94200;
    movingEnable = 0;
    enableSimulation = 1;
    onLoad = "_this call Iceman_fnc_toc_viewDeviceOnLoad";
    onUnload = "_this call Iceman_fnc_toc_viewDeviceOnUnload";
    onKeyDown = "_this call Iceman_fnc_toc_viewDeviceKeyDown";

    class controlsBackground
    {
        class Backdrop: RscText
        {
            idc = -1;
            x = "safeZoneX + safeZoneW * 0.03";
            y = "safeZoneY + safeZoneH * 0.04";
            w = "safeZoneW * 0.94";
            h = "safeZoneH * 0.92";
            colorBackground[] = {0.025,0.035,0.04,0.97};
        };
        class Header: RscText
        {
            idc = -1;
            x = "safeZoneX + safeZoneW * 0.03";
            y = "safeZoneY + safeZoneH * 0.04";
            w = "safeZoneW * 0.94";
            h = "safeZoneH * 0.06";
            text = "TOC View Device";
            colorBackground[] = {0.005,0.008,0.01,1};
            colorText[] = {1,1,1,1};
            sizeEx = 0.045;
            style = 2;
            font = "RobotoCondensedBold";
        };
        class Footer: RscText
        {
            idc = -1;
            x = "safeZoneX + safeZoneW * 0.03";
            y = "safeZoneY + safeZoneH * 0.905";
            w = "safeZoneW * 0.94";
            h = "safeZoneH * 0.055";
            colorBackground[] = {0.005,0.008,0.01,1};
        };
    };

    class controls
    {
    };
};
