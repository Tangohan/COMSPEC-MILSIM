#include "script_component.hpp"
class CfgPatches {
    class comspec_overwatch_atak_native {
        name="COMSPEC ATAK Native"; author="COMSPEC"; requiredVersion=2.14;
        requiredAddons[]={"A3_UI_F","cba_main","cba_xeh","comspec_overwatch_connect"};
        units[]={}; weapons[]={}; version=1.0; versionStr=VERSION_STR; versionAr[]={1,0,0};
    };
};
class CfgFunctions {
    class comspec_atak_native { tag="comspec_atak_native";
        class core { file="z\comspec_overwatch\addons\atak_native\functions\core";
            class log {}; class stateInit {}; class storeSet {}; class schedulerStart {}; class schedulerStop {}; class schedulerTick {}; class debugDump {};
        };
        class ui { file="z\comspec_overwatch\addons\atak_native\functions\ui";
            class open {}; class close {}; class displayLoad {}; class displayUnload {}; class layoutGet {}; class layoutApply {}; class navigate {}; class pageRender {}; class statusUpdate {}; class inspectorUpdate {}; class notify {}; class notificationsRender {};
        };
        class map { file="z\comspec_overwatch\addons\atak_native\functions\map";
            class mapOnDraw {}; class mapMouseButtonDown {}; class mapSelect {}; class mapToolSet {}; class symbology {}; class localDataRefresh {};
        };
        class network { file="z\comspec_overwatch\addons\atak_native\functions\network";
            class extensionCall {}; class extensionCallback {}; class remoteSync {}; class importLegacyData {};
        };
        class pages { file="z\comspec_overwatch\addons\atak_native\functions\pages";
            class chatSend {}; class taskAction {}; class briefingStep {}; class settingsSave {};
        };
    };
};
class Extended_PreInit_EventHandlers { class comspec_overwatch_atak_native { init="call compile preprocessFileLineNumbers 'z\comspec_overwatch\addons\atak_native\XEH_preInit.sqf'"; }; };
class Extended_PostInit_EventHandlers { class comspec_overwatch_atak_native { clientInit="call compile preprocessFileLineNumbers 'z\comspec_overwatch\addons\atak_native\XEH_postInitClient.sqf'"; }; };
#include "ui/controls.hpp"
#include "ui/displays.hpp"
