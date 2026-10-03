#include "script_component.hpp"
class CfgPatches {
    class comspec_atak_native_main {
        name="COMSPEC ATAK Native Standalone"; author="COMSPEC"; requiredVersion=2.14;
        requiredAddons[]={"A3_UI_F","cba_main","cba_xeh"};
        units[]={}; weapons[]={}; version=1.1; versionStr=VERSION_STR; versionAr[]={1,1,0};
    };
};
class CfgFunctions {
    class comspec_atak_native { tag="comspec_atak_native";
        class core { file="z\comspec_atak_native\addons\main\functions\core";
            class log {}; class stateInit {}; class storeSet {}; class schedulerStart {}; class schedulerStop {}; class schedulerTick {}; class debugDump {};
        };
        class ui { file="z\comspec_atak_native\addons\main\functions\ui";
            class open {}; class close {}; class displayLoad {}; class displayUnload {}; class layoutGet {}; class layoutApply {}; class navigate {}; class back {}; class modeToggle {};
            class pageRender {}; class pageClear {}; class pageCtrl {}; class tileCreate {}; class appList {}; class appBadge {}; class dockRender {}; class launcherRender {};
            class statusUpdate {}; class inspectorUpdate {}; class notify {}; class notificationsRender {};
        };
        class map { file="z\comspec_atak_native\addons\main\functions\map";
            class mapOnDraw {}; class mapMouseButtonDown {}; class mapSelect {}; class mapToolSet {}; class symbology {}; class localDataRefresh {}; class mapCenter {};
        };
        class network { file="z\comspec_atak_native\addons\main\functions\network";
            class extensionCall {}; class extensionCallback {}; class remoteSync {}; class importLegacyData {};
            class pollUnits {}; class pollMarkers {}; class pollOrders {}; class pollChat {};
            class p2pSend {}; class p2pReceive {};
        };
        class pages { file="z\comspec_atak_native\addons\main\functions\pages";
            class chatSend {}; class taskAction {}; class briefingStep {}; class settingsSave {};
            class pageMap {}; class pageChat {}; class pageGroup {}; class pageTasks {}; class pageText {};
        };
    };
};

// Registre des applications du lanceur (même principe que les app.hpp de BCE) :
// un autre addon peut ajouter une classe ici pour apparaître dans le lanceur.
//   page    : page du routeur ouverte au clic
//   icon    : texture PAA (icônes Arma 3 de base, aucune ressource tierce)
//   section : regroupement dans le lanceur ; order : tri croissant ; dock=1 : raccourci du dock
class COMSPEC_ATAK_Apps {
    class Map      { name="Carte";      page="MAP";      section="Opérations";    order=10;  dock=1; icon="\a3\ui_f\data\igui\cfg\simpletasks\types\map_ca.paa"; };
    class Chat     { name="Messagerie"; page="CHAT";     section="Opérations";    order=20;  dock=1; icon="\a3\ui_f\data\igui\cfg\simpletasks\types\talk_ca.paa"; };
    class Group    { name="Groupe";     page="GROUP";    section="Opérations";    order=30;  dock=1; icon="\a3\ui_f\data\igui\cfg\simpletasks\types\meet_ca.paa"; };
    class Tasks    { name="Tâches";     page="TASK";     section="Opérations";    order=40;  dock=1; icon="\a3\ui_f\data\igui\cfg\simpletasks\types\documents_ca.paa"; };
    class C2       { name="C2";         page="C2";       section="Opérations";    order=50;  dock=0; icon="\a3\ui_f\data\igui\cfg\simpletasks\types\radio_ca.paa"; };
    class Bft      { name="BFT";        page="BFT";      section="Opérations";    order=60;  dock=0; icon="\a3\ui_f\data\igui\cfg\simpletasks\types\move_ca.paa"; };
    class Intel    { name="Rens.";      page="INTEL";    section="Renseignement"; order=110; dock=0; icon="\a3\ui_f\data\igui\cfg\simpletasks\types\intel_ca.paa"; };
    class Sse      { name="SSE";        page="SSE";      section="Renseignement"; order=120; dock=0; icon="\a3\ui_f\data\igui\cfg\simpletasks\types\search_ca.paa"; };
    class Bda      { name="BDA";        page="BDA";      section="Renseignement"; order=130; dock=0; icon="\a3\ui_f\data\igui\cfg\simpletasks\types\destroy_ca.paa"; };
    class Photos   { name="Photos";     page="PHOTOS";   section="Renseignement"; order=140; dock=0; icon="\a3\ui_f\data\igui\cfg\simpletasks\types\scout_ca.paa"; };
    class Briefing { name="Briefing";   page="BRIEFING"; section="Mission";       order=210; dock=0; icon="\a3\ui_f\data\igui\cfg\simpletasks\types\whiteboard_ca.paa"; };
    class Status   { name="Statut";     page="STATUS";   section="Système";       order=310; dock=0; icon="\a3\ui_f\data\igui\cfg\simpletasks\types\use_ca.paa"; };
    class Settings { name="Réglages";   page="SETTINGS"; section="Système";       order=320; dock=0; icon="\a3\ui_f\data\igui\cfg\simpletasks\types\interact_ca.paa"; };
};

class Extended_PreInit_EventHandlers { class comspec_atak_native_main { init="call compile preprocessFileLineNumbers 'z\comspec_atak_native\addons\main\XEH_preInit.sqf'"; }; };
class Extended_PostInit_EventHandlers { class comspec_atak_native_main { clientInit="call compile preprocessFileLineNumbers 'z\comspec_atak_native\addons\main\XEH_postInitClient.sqf'"; }; };
#include "ui/controls.hpp"
#include "ui/displays.hpp"
