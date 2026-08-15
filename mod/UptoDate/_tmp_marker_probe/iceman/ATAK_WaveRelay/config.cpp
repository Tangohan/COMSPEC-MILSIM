class CfgPatches
{
    class Iceman_ATAK_WaveRelay
    {
        name = "Iceman ATAK Wave Relay";
        author = "Cole / Codex";
        requiredVersion = 2.14;
        requiredAddons[] = {"cba_main", "cba_common", "cTab", "ctab_core", "BCE_Core", "BCE_cTab", "acre_api", "acre_sys_gestures", "Iceman_ACRE_MPU5"};
        units[] = {};
        weapons[] = {};
    };
};

class CfgFunctions
{
    class Iceman
    {
        class ATAK_WaveRelay
        {
            file = "\ATAK_WaveRelay\functions";
            class wr_action {};
            class wr_adjustMonitorVolume {};
            class wr_applyAcreTalkgroup {};
            class wr_collectNodes {};
            class wr_cachePttKeybinds {};
            class wr_cycleMonitorEar {};
            class wr_deleteProfile {};
            class wr_feedInfo {};
            class wr_feedObject {};
            class wr_findControls {};
            class wr_formatTgList {};
            class wr_formatTxSlots {};
            class wr_formatGrid {};
            class wr_getFeeds {};
            class wr_getMonitorEar {};
            class wr_getMonitorVolume {};
            class wr_getNodeId {};
            class wr_getProfiles {};
            class wr_getState {};
            class wr_getTxSlots {};
            class wr_hasRadio {};
            class wr_handleCtabPttInput {};
            class wr_installCtabPtt {};
            class wr_keyTx {};
            class wr_loadProfile {};
            class wr_locateSelected {};
            class wr_onListSelect {};
            class wr_onOpened {};
            class wr_open {};
            class wr_playRadioCue {};
            class wr_readUi {};
            class wr_saveProfile {};
            class wr_saveState {};
            class wr_selectTab {};
            class wr_selectFeed {};
            class wr_sendFeedToToc {};
            class wr_setTxVisual {};
            class wr_showRadioHint {};
            class wr_syncAcreChannels {};
            class wr_tick {};
            class wr_toggleTg {};
            class wr_txSlotForTg {};
            class wr_setTxSlot {};
            class wr_cycleMonitorVolume {};
            class wr_updatePanel {};
        };
    };
};

class Extended_PreInit_EventHandlers
{
    class Iceman_ATAK_WaveRelay
    {
        init = "call compile preprocessFileLineNumbers '\ATAK_WaveRelay\XEH_preInit.sqf'";
    };
};

class Extended_PostInit_EventHandlers
{
    class Iceman_ATAK_WaveRelay
    {
        init = "call compile preprocessFileLineNumbers '\ATAK_WaveRelay\XEH_postInitClient.sqf'";
    };
};

#include "ui\WaveRelayPage.hpp"

class ATAK_APPs
{
    class message;
    class WaveRelay: message
    {
        class Menu_Property
        {
            ORDER = 8;
            PAGE_CTRL = "Iceman_ATAK_WaveRelay";
            Opened = "Iceman_fnc_wr_onOpened";
        };

        onButtonClick = "[_this # 0] call BCE_fnc_ATAK_ChangeTool;";
        text = "<t size='1'>Wave Relay</t>";
        textureNoShortcut = "\ACRE_MPU5\data\mpu5cropped.paa";
    };
};

class RscTitles
{
    class ATAK_APPs
    {
        class message;
        class WaveRelay: message
        {
            class Menu_Property
            {
                ORDER = 8;
                PAGE_CTRL = "Iceman_ATAK_WaveRelay";
                Opened = "Iceman_fnc_wr_onOpened";
            };

            onButtonClick = "[_this # 0] call BCE_fnc_ATAK_ChangeTool;";
            text = "<t size='1'>Wave Relay</t>";
            textureNoShortcut = "\ACRE_MPU5\data\mpu5cropped.paa";
        };
    };
};
