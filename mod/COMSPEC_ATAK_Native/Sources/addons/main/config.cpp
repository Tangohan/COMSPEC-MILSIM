#include "script_component.hpp"
class CfgPatches {
    class comspec_atak_native_main {
        name="COMSPEC ATAK Native Standalone"; author="COMSPEC"; requiredVersion=2.14;
        requiredAddons[]={"A3_UI_F","A3_Weapons_F","cba_main","cba_xeh"};
        units[]={"Item_COMSPEC_ATAK_Battery","Item_COMSPEC_ATAK_RepairKit"}; weapons[]={"COMSPEC_ATAK_Battery","COMSPEC_ATAK_RepairKit"}; version=1.6; versionStr=VERSION_STR; versionAr[]={1,6,0};
    };
};
class CfgFunctions {
    class comspec_atak_native { tag="comspec_atak_native";
        class core { file="z\comspec_atak_native\addons\main\functions\core";
            class log {}; class battery {}; class weather {}; class stateInit {}; class storeSet {}; class schedulerStart {}; class schedulerStop {}; class schedulerTick {}; class debugDump {}; class deviceCatalog {}; class hasDevice {}; class deviceDenied {}; class tenantRule {}; class pref {}; class tenantApply {}; class canUse {}; class batterySwap {}; class linkQuality {}; class deviceHealth {}; class deviceDamage {}; class deviceRepair {}; class phoneIdent {}; class ramUsage {}; class batteryItem {}; class aircrewTerminal {}; class repairStart {}; class deviceImpact {}; class deviceReport {}; class screenDirt {}; class screenClean {}; class medShow {};
        };
        class ui { file="z\comspec_atak_native\addons\main\functions\ui";
            class display {}; class open {}; class close {}; class hudToggle {}; class interactToggle {}; class orientationToggle {}; class phoneDrag {}; class formRender {}; class formValue {}; class fullAlert {}; class vibrate {}; class dataBarEnabled {}; class displayLoad {}; class displayUnload {}; class layoutGet {}; class layoutApply {}; class navigate {}; class back {}; class modeToggle {};
            class pageRender {}; class pageClear {}; class pageCtrl {}; class tileCreate {}; class appList {}; class appBadge {}; class dockRender {}; class launcherRender {}; class deviceOverlay {}; class appVisible {}; class accent {};
            class statusUpdate {}; class inspectorUpdate {}; class abbrev {}; class notify {}; class notificationsRender {}; class notifCenter {}; class powerFx {}; class overlayFront {}; class screenSurface {};
        };
        class map { file="z\comspec_atak_native\addons\main\functions\map";
            class mapOnDraw {}; class mapMouseButtonDown {}; class mapSelect {}; class mapToolSet {}; class symbology {}; class localDataRefresh {}; class mapCenter {}; class mapMouseMoving {}; class mapOverlayUpdate {}; class markerDrop {}; class unitCallsign {}; class unitGroup {}; class gridRef {}; class mapZoom {}; class mapToolMenu {}; class mapToolRun {}; class markerAt {}; class markerCatalog {}; class markerEditOpen {}; class markerEditor {}; class markerEditSave {}; class markerDelete {}; class markerWeb {}; class markerWebSweep {}; class markerStroke {}; class mapMouseButtonUp {}; class mapDblClick {}; class mapUnfocus {}; class inspToggle {}; class markerPalette {}; class markerPaletteData {}; class markerLabels {}; class sigintPoll {}; class reliefDraw {}; class meshDraw {}; class logisticsDraw {}; class ewDraw {}; class mapStyle {}; class textureKeep {};
        };
        class network { file="z\comspec_atak_native\addons\main\functions\network";
            class extensionCall {}; class extensionCallback {}; class remoteSync {}; class importLegacyData {};
            class pollUnits {}; class pollMarkers {}; class pollOrders {}; class pollChat {};
            class p2pSend {}; class p2pReceive {}; class p2pAck {}; class p2pReachable {}; class p2pDate {}; class athenaSignal {}; class netSend {}; class bridge {}; class avatarPath {};
        };
        class viz { file="z\comspec_atak_native\addons\main\functions\viz";
            class vizEcg {}; class vizSpectrum {}; class vizPlayer {};
        };
        class music { file="z\comspec_atak_native\addons\main\functions\music";
            class musicState {}; class musicAction {}; class musicTick {}; class musicServerList {}; class pageMusic {};
        };
        class nav { file="z\comspec_atak_native\addons\main\functions\nav";
            class routeCompute {}; class routeGuide {}; class routeBanner {}; class wpAction {}; class pageWaypoints {}; class pageGps {}; class gpsAction {};
        };
        class explo { file="z\comspec_atak_native\addons\main\functions\explo";
            class exploList {}; class exploAction {}; class pageExplo {};
        };
        class tactical { file="z\comspec_atak_native\addons\main\functions\tactical";
            class sniperBallistics {}; class pageSniper {}; class sniperAction {}; class jtacTarget {}; class pageJtac {}; class jtacAction {};
            class breachBuilding {}; class pageBreach {}; class breachAction {}; class breachTop {};
        };
        class drone { file="z\comspec_atak_native\addons\main\functions\drone";
            class droneCmd {}; class droneAction {}; class droneOsd {}; class droneDraw {}; class pageDrone {}; class droneDetectScan {}; class pageDroneDetect {}; class droneDetectDraw {};
        };
        class bluetooth { file="z\comspec_atak_native\addons\main\functions\bluetooth";
            class airplaneMode {}; class btAction {}; class btSounds {}; class pageBluetooth {}; class btInit { postInit=1; };
        };
        class reports { file="z\comspec_atak_native\addons\main\functions\reports";
            class reportTypes {}; class reportAction {}; class pageReports {};
        };
        class teams { file="z\comspec_atak_native\addons\main\functions\teams";
            class ftCatalog {}; class ftInfo {}; class ftTeams {}; class ftServer {}; class ftAction {}; class ftRows {}; class ftCenter {};
            class json {}; class squadSnapshot {}; class squadSync {}; class pageInterTeam {}; class interTeamAction {}; class ftRoleSync {};
        };
        class screen { file="z\comspec_atak_native\addons\main\functions\screen";
            class roleKey {}; class screenTime {}; class pageScreenTime {}; class playTimeServer {};
        };
        class aar { file="z\comspec_atak_native\addons\main\functions\aar";
            class aarRecord {}; class aarAction {}; class aarDraw {}; class pageAar {};
        };
        class pages { file="z\comspec_atak_native\addons\main\functions\pages";
            class chatSend {}; class taskAction {}; class briefingStep {}; class settingsSave {};
            class pageMap {}; class pageChat {}; class chatParse {}; class messagesAll {}; class tasksAll {}; class pageGroup {}; class pageTasks {}; class pageText {}; class pageAthena {}; class athenaAction {}; class pageNetwork {}; class pageSettings {}; class pagePhotos {}; class photoTake {}; class photoMode {}; class pageFrs {}; class frsDraftSave {}; class frsToggleTheme {}; class frsSubmit {}; class frsAction {}; class frsLibrary {}; class pageReco {}; class recoDraftSave {}; class recoSubmit {}; class pageFires {}; class firesState {}; class firesGuns {}; class firesSave {}; class firesAction {}; class pageLivecam {}; class livecamStart {}; class livecamStop {}; class pageAlerts {}; class alertsAction {}; class alertsLog {}; class pageMedical {}; class medicalAction {}; class pageProfile {}; class profileAction {}; class pageBriefing {}; class chatCommand {}; class briefingAction {}; class briefingSignature {}; class briefingLive {}; class photoLibrary {}; class livecamShare {}; class pageBft {}; class bftAction {}; class groupTypes {}; class groupAction {}; class pageRecents {}; class pageNotifs {}; class pageDebug {}; class pageFood {}; class foodAction {}; class pageDating {}; class datingAction {}; class pageOsint {}; class osintAction {}; class pageWeather {}; class pageRelief {}; class reliefAction {}; class pageWaveRelay {}; class waveRelayAction {}; class pageLogistics {}; class logisticsAction {}; class logiWeb {}; class pageEw {}; class ewAction {}; class ewEffects {}; class pageBda {}; class bdaAction {}; class bdaLabels {}; class pageCredits {}; class pageResynch {}; class resynchRun {}; class pageOrderCompose {}; class orderAction {}; class pageStatus {}; class pageSse {}; class pageC2 {}; class sseAction {}; class pageWanted {}; class wantedAction {}; class pageLinkAlly {}; class linkAllyAction {}; class linkAllyInit {}; class pageDiscord {}; class discordAction {};
        };
    };
};

// Registre des applications du lanceur (même principe que les app.hpp de BCE) :
// un autre addon peut ajouter une classe ici pour apparaître dans le lanceur.
//   page    : page du routeur ouverte au clic
//   icon    : texture PAA (icônes COMSPEC dans data\, générées par tools/gen_assets.py)
//   section : regroupement dans le lanceur ; order : tri croissant ; dock=1 : raccourci du dock
// Sections (dans l'ordre) : Navigation 10+, Communication 30+, Appui et feux 50+, Spécialités 70+,
// Renseignement 110+, Mission 200+, Système 300+, Civil 395+.
class COMSPEC_ATAK_Apps {
    class Map         { name="Carte"; page="MAP"; section="Navigation"; order=10; dock=1; icon="\z\comspec_atak_native\addons\main\data\app_map.paa"; };
    class Gps         { name="GPS"; page="GPS"; section="Navigation"; order=12; dock=0; icon="\z\comspec_atak_native\addons\main\data\app_gps.paa"; };
    class Waypoints   { name="Itinéraire"; page="WAYPOINTS"; section="Navigation"; order=14; dock=0; icon="\z\comspec_atak_native\addons\main\data\app_waypoints.paa"; };
    class Relief      { name="Relief"; page="RELIEF"; section="Navigation"; order=16; dock=0; icon="\z\comspec_atak_native\addons\main\data\app_relief.paa"; };
    class Weather     { name="Météo"; page="WEATHER"; section="Navigation"; order=18; dock=0; icon="\z\comspec_atak_native\addons\main\data\app_weather.paa"; };
    class Chat        { name="Messages"; page="CHAT"; section="Communication"; order=30; dock=1; icon="\z\comspec_atak_native\addons\main\data\app_chat.paa"; };
    class Group       { name="Groupe"; page="GROUP"; section="Communication"; order=32; dock=1; icon="\z\comspec_atak_native\addons\main\data\app_group.paa"; };
    class Alerts      { name="Alertes"; page="ALERTS"; section="Communication"; order=34; dock=0; icon="\z\comspec_atak_native\addons\main\data\app_alerts.paa"; };
    class Bft         { name="BFT"; page="BFT"; section="Communication"; order=36; dock=0; icon="\z\comspec_atak_native\addons\main\data\app_bft.paa"; };
    class InterTeam   { name="Inter-team"; page="INTERTEAM"; function="comspec_atak_native_fnc_pageInterTeam"; section="Communication"; order=37; dock=0; icon="\z\comspec_atak_native\addons\main\data\app_interteam.paa"; };
    class C2          { name="C2"; page="C2"; section="Communication"; order=38; dock=0; icon="\z\comspec_atak_native\addons\main\data\app_c2.paa"; };
    class WaveRelay   { name="Wave Relay"; page="WAVERELAY"; section="Communication"; order=40; dock=0; icon="\z\comspec_atak_native\addons\main\data\app_waverelay.paa"; };
    class Fires       { name="Feux"; page="FIRES"; section="Appui et feux"; order=50; dock=0; icon="\z\comspec_atak_native\addons\main\data\app_fires.paa"; };
    class Jtac        { name="JTAC"; page="JTAC"; section="Appui et feux"; order=52; dock=0; icon="\z\comspec_atak_native\addons\main\data\app_jtac.paa"; };
    class Drone       { name="Drone"; page="DRONE"; section="Appui et feux"; order=54; dock=0; icon="\z\comspec_atak_native\addons\main\data\app_drone.paa"; };
    class Medical     { name="Médical"; page="MEDICAL"; section="Spécialités"; order=70; dock=0; icon="\z\comspec_atak_native\addons\main\data\app_medical.paa"; };
    class Sniper      { name="Sniper"; page="SNIPER"; section="Spécialités"; order=72; dock=0; icon="\z\comspec_atak_native\addons\main\data\app_sniper.paa"; };
    class Breach      { name="Brèche"; page="BREACH"; section="Spécialités"; order=74; dock=0; icon="\z\comspec_atak_native\addons\main\data\app_breach.paa"; };
    class Explo       { name="Explosifs"; page="EXPLO"; section="Spécialités"; order=76; dock=0; icon="\z\comspec_atak_native\addons\main\data\app_explo.paa"; };
    class Ew          { name="GE"; page="EW"; section="Spécialités"; order=78; dock=0; icon="\z\comspec_atak_native\addons\main\data\app_ew.paa"; };
    class DroneDetect { name="Anti-drone"; page="DRONEDETECT"; section="Spécialités"; order=80; dock=0; icon="\z\comspec_atak_native\addons\main\data\app_dronedetect.paa"; };
    class Intel       { name="FRS / FRM"; page="FRS"; section="Renseignement"; order=110; dock=0; icon="\z\comspec_atak_native\addons\main\data\app_intel.paa"; };
    class Reco        { name="Reco"; page="RECO"; section="Renseignement"; order=112; dock=0; icon="\z\comspec_atak_native\addons\main\data\app_reco.paa"; };
    class Photos      { name="Photos"; page="PHOTOS"; section="Renseignement"; order=114; dock=0; icon="\z\comspec_atak_native\addons\main\data\app_photos.paa"; };
    class Livecam     { name="Vidéo live"; page="LIVECAM"; section="Renseignement"; order=116; dock=0; icon="\z\comspec_atak_native\addons\main\data\app_livecam.paa"; };
    class Sse         { name="SSE"; page="SSE"; section="Renseignement"; order=118; dock=0; icon="\z\comspec_atak_native\addons\main\data\app_sse.paa"; };
    class Osint       { name="OSINT"; page="OSINT"; section="Renseignement"; order=120; dock=0; icon="\z\comspec_atak_native\addons\main\data\app_osint.paa"; };
    class LinkAlly    { name="Liaison allié"; page="LINKALLY"; function="comspec_atak_native_fnc_pageLinkAlly"; section="Spécialités"; order=82; dock=0; icon="\z\comspec_atak_native\addons\main\data\app_linkally.paa"; };
    class Wanted      { name="Recherchés"; page="WANTED"; section="Renseignement"; order=122; dock=0; icon="\z\comspec_atak_native\addons\main\data\app_wanted.paa"; };
    class Tasks       { name="Tâches"; page="TASK"; section="Mission"; order=200; dock=1; icon="\z\comspec_atak_native\addons\main\data\app_tasks.paa"; };
    class Reports     { name="Comptes rendus"; page="REPORTS"; section="Mission"; order=202; dock=0; icon="\z\comspec_atak_native\addons\main\data\app_reports.paa"; };
    class Briefing    { name="Briefing"; page="BRIEFING"; section="Mission"; order=205; dock=0; icon="\z\comspec_atak_native\addons\main\data\app_briefing.paa"; };
    class Logistics   { name="Logistique"; page="LOGI"; section="Mission"; order=210; dock=0; icon="\z\comspec_atak_native\addons\main\data\app_logistics.paa"; };
    class Aar         { name="Rejeu"; page="AAR"; section="Mission"; order=215; dock=0; icon="\z\comspec_atak_native\addons\main\data\app_aar.paa"; };
    class Athena      { name="Athena"; page="ATHENA"; section="Système"; order=300; dock=1; icon="\z\comspec_atak_native\addons\main\data\app_athena.paa"; };
    class Discord     { name="Discord"; page="DISCORD"; function="comspec_atak_native_fnc_pageDiscord"; section="Communication"; order=42; dock=0; icon="\z\comspec_atak_native\addons\main\data\app_discord.paa"; };
    class Network     { name="Réseau"; page="NETWORK"; section="Système"; order=302; dock=0; icon="\z\comspec_atak_native\addons\main\data\app_network.paa"; };
    class Bluetooth   { name="Bluetooth"; page="BLUETOOTH"; function="comspec_atak_native_fnc_pageBluetooth"; section="Système"; order=303; dock=0; icon="\a3\ui_f\data\igui\cfg\simpletasks\types\radio_ca.paa"; };
    class Resynch     { name="Synchro"; page="RESYNCH"; section="Système"; order=304; dock=0; icon="\z\comspec_atak_native\addons\main\data\app_resynch.paa"; };
    class Status      { name="Statut"; page="STATUS"; section="Système"; order=306; dock=0; icon="\z\comspec_atak_native\addons\main\data\app_status.paa"; };
    class Profile     { name="Profil"; page="PROFILE"; section="Système"; order=308; dock=0; icon="\z\comspec_atak_native\addons\main\data\app_profile.paa"; };
    class Settings    { name="Réglages"; page="SETTINGS"; section="Système"; order=310; dock=0; icon="\z\comspec_atak_native\addons\main\data\app_settings.paa"; };
    class ScreenTime  { name="Temps d'écran"; page="SCREENTIME"; function="comspec_atak_native_fnc_pageScreenTime"; section="Système"; order=312; dock=0; icon="\z\comspec_atak_native\addons\main\data\app_screentime.paa"; };
    class Debug       { name="Debug"; page="DEBUG"; section="Système"; order=315; dock=0; icon="\z\comspec_atak_native\addons\main\data\app_debug.paa"; };
    class Credits     { name="Crédits"; page="CREDITS"; section="Système"; order=330; dock=0; icon="\z\comspec_atak_native\addons\main\data\app_credits.paa"; };
    class Music       { name="Musique"; page="MUSIC"; section="Civil"; order=395; dock=0; icon="\z\comspec_atak_native\addons\main\data\app_music.paa"; };
    class Food        { name="UberEats"; page="FOOD"; section="Civil"; order=400; dock=0; icon="\z\comspec_atak_native\addons\main\data\app_food.paa"; };
    class Dating      { name="Tinder"; page="DATING"; section="Civil"; order=410; dock=0; icon="\z\comspec_atak_native\addons\main\data\app_dating.paa"; };
};

// Batterie de rechange du téléphone (action ACE « Changer la batterie ATAK », consommée au changement).
class CfgWeapons {
    class ItemCore;
    class InventoryItem_Base_F;
    class COMSPEC_ATAK_Battery: ItemCore {
        scope=2; scopeCurator=2; author="COMSPEC";
        displayName="Batterie ATAK";
        descriptionShort="Batterie lithium de rechange du téléphone COMSPEC ATAK. Action ACE « Changer la batterie ATAK » : 100 %, le téléphone redémarre.";
        picture="\z\comspec_atak_native\addons\main\data\item_battery.paa";
        model="\A3\Weapons_F\Ammo\mag_univ.p3d";
        type=4096; detectRange=-1; simulation="ItemMineDetector";
        class ItemInfo: InventoryItem_Base_F { mass=2; };
    };
    // Kit de réparation du téléphone (action ACE « Réparer le téléphone ATAK », consommé ; fn_repairStart).
    class COMSPEC_ATAK_RepairKit: ItemCore {
        scope=2; scopeCurator=2; author="COMSPEC";
        displayName="Kit de réparation ATAK";
        descriptionShort="Écran de rechange, nappe et outils pour le téléphone COMSPEC ATAK. Action ACE « Réparer le téléphone ATAK » (12 s) : écran, alimentation et appareil remis en état, même détruit. Consommé.";
        picture="\z\comspec_atak_native\addons\main\data\item_repairkit.paa";
        model="\A3\Weapons_F\Items\Toolkit";
        type=4096; detectRange=-1; simulation="ItemMineDetector";
        class ItemInfo: InventoryItem_Base_F { mass=10; };
    };
};
class CfgVehicles {
    class Item_Base_F;
    class Item_COMSPEC_ATAK_Battery: Item_Base_F {
        scope=2; scopeCurator=2; author="COMSPEC";
        displayName="Batterie ATAK";
        vehicleClass="Items"; editorCategory="EdCat_Equipment"; editorSubcategory="EdSubcat_InventoryItems";
        class TransportItems { class _xx_COMSPEC_ATAK_Battery { name="COMSPEC_ATAK_Battery"; count=1; }; };
    };
    class Item_COMSPEC_ATAK_RepairKit: Item_Base_F {
        scope=2; scopeCurator=2; author="COMSPEC";
        displayName="Kit de réparation ATAK";
        vehicleClass="Items"; editorCategory="EdCat_Equipment"; editorSubcategory="EdSubcat_InventoryItems";
        class TransportItems { class _xx_COMSPEC_ATAK_RepairKit { name="COMSPEC_ATAK_RepairKit"; count=1; }; };
    };
};

class Extended_PreInit_EventHandlers { class comspec_atak_native_main { init="call compile preprocessFileLineNumbers 'z\comspec_atak_native\addons\main\XEH_preInit.sqf'"; }; };
class Extended_PostInit_EventHandlers { class comspec_atak_native_main { clientInit="call compile preprocessFileLineNumbers 'z\comspec_atak_native\addons\main\XEH_postInitClient.sqf'"; serverInit="call compile preprocessFileLineNumbers 'z\comspec_atak_native\addons\main\XEH_postInitServer.sqf'"; }; };
#include "ui/controls.hpp"
#include "ui/displays.hpp"
