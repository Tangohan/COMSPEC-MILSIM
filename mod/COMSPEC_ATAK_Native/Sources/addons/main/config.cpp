#include "script_component.hpp"
class CfgPatches {
    class comspec_atak_native_main {
        name="COMSPEC ATAK Native Standalone"; author="COMSPEC"; requiredVersion=2.14;
        requiredAddons[]={"A3_UI_F","A3_Weapons_F","A3_Modules_F","cba_main","cba_xeh"};
        units[]={"Item_COMSPEC_ATAK_Battery","Item_COMSPEC_ATAK_RepairKit","Item_COMSPEC_ATAK_DataCable","Item_COMSPEC_ATAK_Antenna_Stub","Item_COMSPEC_ATAK_Antenna_Whip","Item_COMSPEC_ATAK_Antenna_Patch","Item_COMSPEC_ATAK_PowerBank","Item_COMSPEC_ATAK_NvgFilter","Item_COMSPEC_ATAK_VehicleKit","Item_COMSPEC_ATAK_Camera","Item_COMSPEC_ATAK_DockKit","COMSPEC_ATAK_ChargingDock","COMSPEC_ATAK_Module_DataNode","COMSPEC_ATAK_Module_DataFile","COMSPEC_ATAK_Module_DataReset"}; weapons[]={"COMSPEC_ATAK_Battery","COMSPEC_ATAK_RepairKit","COMSPEC_ATAK_DataCable","COMSPEC_ATAK_Antenna_Stub","COMSPEC_ATAK_Antenna_Whip","COMSPEC_ATAK_Antenna_Patch","COMSPEC_ATAK_PowerBank","COMSPEC_ATAK_NvgFilter","COMSPEC_ATAK_VehicleKit","COMSPEC_ATAK_Camera","COMSPEC_ATAK_DockKit"}; version=1.8; versionStr=VERSION_STR; versionAr[]={1,8,0};
    };
};
class CfgFunctions {
    class comspec_atak_native { tag="comspec_atak_native";
        class core { file="z\comspec_atak_native\addons\main\functions\core";
            class log {}; class battery {}; class weather {}; class stateInit {}; class storeSet {}; class schedulerStart {}; class schedulerStop {}; class schedulerTick {}; class debugDump {}; class deviceCatalog {}; class hasDevice {}; class deviceDenied {}; class tenantRule {}; class pref {}; class tenantApply {}; class canUse {}; class batterySwap {}; class linkQuality {}; class deviceHealth {}; class deviceDamage {}; class deviceRepair {}; class phoneIdent {}; class ramUsage {}; class athenaPulse {}; class batteryItem {}; class aircrewTerminal {}; class repairStart {}; class deviceImpact {}; class deviceReport {}; class screenDirt {}; class screenClean {}; class medShow {};
        };
        class ui { file="z\comspec_atak_native\addons\main\functions\ui";
            class display {}; class open {}; class close {}; class hudToggle {}; class interactToggle {}; class orientationToggle {}; class phoneDrag {}; class formRender {}; class formValue {}; class fullAlert {}; class vibrate {}; class dataBarEnabled {}; class displayLoad {}; class displayUnload {}; class layoutGet {}; class layoutApply {}; class navigate {}; class back {}; class modeToggle {};
            class pageRender {}; class pageClear {}; class pageCtrl {}; class tileCreate {}; class appList {}; class appBadge {}; class dockRender {}; class launcherRender {}; class deviceOverlay {}; class appVisible {}; class accent {};
            class statusUpdate {}; class inspectorUpdate {}; class abbrev {}; class notify {}; class notificationsRender {}; class notifCenter {}; class powerFx {}; class overlayFront {}; class screenSurface {};
            class fx {}; class toast {}; class quickSettings {}; class lockScreen {}; class tr {}; class langApply {};
        };
        class map { file="z\comspec_atak_native\addons\main\functions\map";
            class mapOnDraw {}; class mapMouseButtonDown {}; class mapSelect {}; class mapToolSet {}; class symbology {}; class natoSymbol {}; class mapLabels {}; class mapCtxMenu {}; class mapPing {}; class localDataRefresh {}; class mapCenter {}; class mapMouseMoving {}; class mapOverlayUpdate {}; class markerDrop {}; class unitCallsign {}; class unitGroup {}; class gridRef {}; class mapZoom {}; class mapToolMenu {}; class mapToolRun {}; class markerAt {}; class markerCatalog {}; class markerEditOpen {}; class markerEditor {}; class markerEditSave {}; class markerDelete {}; class markerWeb {}; class markerWebSweep {}; class markerStroke {}; class mapMouseButtonUp {}; class mapDblClick {}; class mapUnfocus {}; class inspToggle {}; class markerPalette {}; class markerPaletteData {}; class markerLabels {}; class sigintPoll {}; class reliefDraw {}; class meshDraw {}; class logisticsDraw {}; class ewDraw {}; class mapSearch {}; class mapStyle {}; class textureKeep {}; class strokeSimplify {}; class strokeDraw {}; class strokeBar {};
        };
        class plan { file="z\comspec_atak_native\addons\main\functions\plan";
            class planCatalog {}; class planGfx {}; class planDraw {}; class planServer {}; class planAction {}; class planMap {}; class pagePlan {};
        };
        class network { file="z\comspec_atak_native\addons\main\functions\network";
            class extensionCall {}; class extAwait {}; class extensionCallback {}; class remoteSync {}; class importLegacyData {};
            class pollUnits {}; class pollMarkers {}; class pollOrders {}; class pollChat {};
            class p2pSend {}; class p2pReceive {}; class p2pAck {}; class p2pReachable {}; class p2pDate {}; class athenaSignal {}; class netSend {}; class bridge {}; class avatarPath {}; class athenaMode {}; class athenaChoice {};
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
            class droneCmd {}; class droneAction {}; class droneOsd {}; class droneDraw {}; class pageDrone {}; class droneDetectScan {}; class pageDroneDetect {}; class droneDetectDraw {}; class lockAction {}; class droneFlir {}; class droneSeq {}; class dronePhoto {}; class droneStatic {};
        };
        class bluetooth { file="z\comspec_atak_native\addons\main\functions\bluetooth";
            class airplaneMode {}; class btAction {}; class btSounds {}; class pageBluetooth {}; class btInit { postInit=1; };
        };
        class shell { file="z\comspec_atak_native\addons\main\functions\shell";
            class antenna {}; class backView {}; class aceMenu {}; class vestMount {}; class powerBank {}; class irBeacon {}; class screenGlow {}; class phoneSecurity {}; class droppedPhone {}; class patchTexture {}; class patchCatalog {}; class pagePatches {}; class pageAccessories {}; class accessoriesAction {}; class vestBuiltin {}; class jpcScreen {}; class jpcMap {};
        };
        class vehicle { file="z\comspec_atak_native\addons\main\functions\vehicle";
            class vehicleTerminal {}; class vehicleGateway {};
        };
        class datalink { file="z\comspec_atak_native\addons\main\functions\datalink";
            class dataSetup {}; class dataParseFiles {}; class dataServer {}; class dataAction {}; class dataInit {}; class dataOverlay {}; class pageDataLink {}; class zeusForm {}; class moduleContext {}; class moduleDataNode {}; class moduleDataFile {}; class moduleDataReset {};
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
        class dock { file="z\comspec_atak_native\addons\main\functions\dock";
            class dockServer {}; class dockVisual {}; class dockClient {}; class dockMenu {};
        };
        class camera { file="z\comspec_atak_native\addons\main\functions\camera";
            class camAction {}; class camView {};
        };
        class pages { file="z\comspec_atak_native\addons\main\functions\pages";
            class chatSend {}; class taskAction {}; class briefingStep {}; class settingsSave {};
            class pageMap {}; class pageChat {}; class chatParse {}; class messagesAll {}; class tasksAll {}; class pageGroup {}; class pageTasks {}; class pageText {}; class pageAthena {}; class athenaAction {}; class pageNetwork {}; class pageSettings {}; class pagePhotos {}; class photoTake {}; class photoMode {}; class pageFrs {}; class frsDraftSave {}; class frsToggleTheme {}; class frsSubmit {}; class frsAction {}; class frsLibrary {}; class pageReco {}; class recoDraftSave {}; class recoSubmit {}; class pageFires {}; class firesState {}; class firesGuns {}; class firesSave {}; class firesAction {}; class pageLivecam {}; class livecamStart {}; class livecamStop {}; class pageAlerts {}; class alertsAction {}; class alertsLog {}; class pageMedical {}; class medicalAction {}; class pageProfile {}; class profileAction {}; class pageBriefing {}; class chatCommand {}; class briefingAction {}; class briefingSignature {}; class briefingLive {}; class photoLibrary {}; class livecamShare {}; class pageBft {}; class bftAction {}; class bftFilter {}; class groupTypes {}; class groupAction {}; class pageRecents {}; class pageNotifs {}; class pageDebug {}; class pageFood {}; class foodAction {}; class pageDating {}; class datingAction {}; class pageOsint {}; class osintAction {}; class pageWeather {}; class pageRelief {}; class reliefAction {}; class pageWaveRelay {}; class waveRelayAction {}; class pageLogistics {}; class logisticsAction {}; class logiWeb {}; class pageEw {}; class ewAction {}; class ewEffects {}; class pageBda {}; class bdaAction {}; class bdaLabels {}; class pageCredits {}; class pageResynch {}; class resynchRun {}; class pageOrderCompose {}; class orderAction {}; class pageStatus {}; class pageSse {}; class pageC2 {}; class sseAction {}; class pageWanted {}; class wantedAction {}; class pageLinkAlly {}; class linkAllyAction {}; class linkAllyInit {}; class pageDiscord {}; class discordAction {};
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
    class DataLink    { name="Extraction"; page="DATALINK"; function="comspec_atak_native_fnc_pageDataLink"; section="Renseignement"; order=119; dock=0; icon="\z\comspec_atak_native\addons\main\data\app_datalink.paa"; };
    class Sse         { name="SSE"; page="SSE"; section="Renseignement"; order=118; dock=0; icon="\z\comspec_atak_native\addons\main\data\app_sse.paa"; };
    class Osint       { name="OSINT"; page="OSINT"; section="Renseignement"; order=120; dock=0; icon="\z\comspec_atak_native\addons\main\data\app_osint.paa"; };
    class LinkAlly    { name="Liaison allié"; page="LINKALLY"; function="comspec_atak_native_fnc_pageLinkAlly"; section="Spécialités"; order=82; dock=0; icon="\z\comspec_atak_native\addons\main\data\app_linkally.paa"; };
    class Wanted      { name="Recherchés"; page="WANTED"; section="Renseignement"; order=122; dock=0; icon="\z\comspec_atak_native\addons\main\data\app_wanted.paa"; };
    class Tasks       { name="Tâches"; page="TASK"; section="Mission"; order=200; dock=1; icon="\z\comspec_atak_native\addons\main\data\app_tasks.paa"; };
    class Reports     { name="Comptes rendus"; page="REPORTS"; section="Mission"; order=202; dock=0; icon="\z\comspec_atak_native\addons\main\data\app_reports.paa"; };
    class Briefing    { name="Briefing"; page="BRIEFING"; section="Mission"; order=205; dock=0; icon="\z\comspec_atak_native\addons\main\data\app_briefing.paa"; };
    class Plan        { name="Plan"; page="PLAN"; function="comspec_atak_native_fnc_pagePlan"; section="Mission"; order=206; dock=0; icon="\z\comspec_atak_native\addons\main\data\app_plan.paa"; };
    class Logistics   { name="Logistique"; page="LOGI"; section="Mission"; order=210; dock=0; icon="\z\comspec_atak_native\addons\main\data\app_logistics.paa"; };
    class Aar         { name="Rejeu"; page="AAR"; section="Mission"; order=215; dock=0; icon="\z\comspec_atak_native\addons\main\data\app_aar.paa"; };
    class Athena      { name="Athena"; page="ATHENA"; section="Système"; order=300; dock=1; icon="\z\comspec_atak_native\addons\main\data\app_athena.paa"; };
    class Discord     { name="Discord"; page="DISCORD"; function="comspec_atak_native_fnc_pageDiscord"; section="Communication"; order=42; dock=0; icon="\z\comspec_atak_native\addons\main\data\app_discord.paa"; };
    class Network     { name="Réseau"; page="NETWORK"; section="Système"; order=302; dock=0; icon="\z\comspec_atak_native\addons\main\data\app_network.paa"; };
    class Bluetooth   { name="Bluetooth"; page="BLUETOOTH"; function="comspec_atak_native_fnc_pageBluetooth"; section="Système"; order=303; dock=0; icon="\a3\ui_f\data\igui\cfg\simpletasks\types\radio_ca.paa"; };
    class Resynch     { name="Synchro"; page="RESYNCH"; section="Système"; order=304; dock=0; icon="\z\comspec_atak_native\addons\main\data\app_resynch.paa"; };
    class Status      { name="Statut"; page="STATUS"; section="Système"; order=306; dock=0; icon="\z\comspec_atak_native\addons\main\data\app_status.paa"; };
    class Profile     { name="Profil"; page="PROFILE"; section="Système"; order=308; dock=0; icon="\z\comspec_atak_native\addons\main\data\app_profile.paa"; };
    class Accessories { name="Accessoires"; page="ACCESSORIES"; function="comspec_atak_native_fnc_pageAccessories"; section="Système"; order=309; dock=0; icon="\z\comspec_atak_native\addons\main\data\app_accessories.paa"; };
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
    // Câble de données DL-7 (app Extraction) : obligatoire si le réglage serveur « Câble DL-7 obligatoire » est coché.
    class COMSPEC_ATAK_DataCable: ItemCore {
        scope=2; scopeCurator=2; author="COMSPEC";
        displayName="Câble de données DL-7";
        descriptionShort="Module DL-7 et câble USB-C / RJ45 / série pour le téléphone COMSPEC ATAK. Regardez un ordinateur, un téléphone ou une clé, « Brancher l'ATAK » : l'app Extraction télécharge ses données.";
        picture="\z\comspec_atak_native\addons\main\data\item_datacable.paa";
        model="\A3\Weapons_F\Ammo\mag_univ.p3d";
        type=4096; detectRange=-1; simulation="ItemMineDetector";
        class ItemInfo: InventoryItem_Base_F { mass=4; };
    };
    // Antenne d'extension de débit (fn_antenna) : ACE « Téléphone ATAK > Antenne » ou app Réglages.
    class COMSPEC_ATAK_Antenna_Stub: ItemCore {
        scope=2; scopeCurator=2; author="COMSPEC";
        displayName="Antenne courte SMA (ATAK)";
        descriptionShort="Antenne courte 2,4 / 5,8 GHz à visser sur le port SMA du téléphone ATAK : débit x1,25 et liaison plus tenace (relief, bâtiments, brouillage). Discrète, efficace en mouvement.";
        picture="\z\comspec_atak_native\addons\main\data\item_antenna_stub.paa";
        model="\A3\Weapons_F\Ammo\mag_univ.p3d";
        type=4096; detectRange=-1; simulation="ItemMineDetector";
        class ItemInfo: InventoryItem_Base_F { mass=1; };
    };
    // Antenne d'extension de débit (fn_antenna) : ACE « Téléphone ATAK > Antenne » ou app Réglages.
    class COMSPEC_ATAK_Antenna_Whip: ItemCore {
        scope=2; scopeCurator=2; author="COMSPEC";
        displayName="Antenne fouet longue (ATAK)";
        descriptionShort="Antenne fouet repliable à visser sur le port SMA du téléphone ATAK : débit x1,5, nettement plus tenace derrière le relief et dans les bâtiments.";
        picture="\z\comspec_atak_native\addons\main\data\item_antenna_whip.paa";
        model="\A3\Weapons_F\Ammo\mag_univ.p3d";
        type=4096; detectRange=-1; simulation="ItemMineDetector";
        class ItemInfo: InventoryItem_Base_F { mass=3; };
    };
    // Antenne d'extension de débit (fn_antenna) : ACE « Téléphone ATAK > Antenne » ou app Réglages.
    class COMSPEC_ATAK_Antenna_Patch: ItemCore {
        scope=2; scopeCurator=2; author="COMSPEC";
        displayName="Antenne patch 9 dBi (ATAK)";
        descriptionShort="Antenne patch directionnelle sur coude SMA à 90° : débit x1,7 et meilleure tenue de la liaison, à l'arrêt seulement (mal orientée en mouvement ou en véhicule).";
        picture="\z\comspec_atak_native\addons\main\data\item_antenna_patch.paa";
        model="\A3\Weapons_F\Ammo\mag_univ.p3d";
        type=4096; detectRange=-1; simulation="ItemMineDetector";
        class ItemInfo: InventoryItem_Base_F { mass=4; };
    };
    class COMSPEC_ATAK_PowerBank: ItemCore {
        scope=2; scopeCurator=2; author="COMSPEC";
        displayName="Batterie externe ATAK 10 000 mAh";
        descriptionShort="Se fixe sur la plaque au dos du téléphone (menu ACE « Téléphone ATAK »). Recharge le téléphone à 2 % par minute, deux charges complètes. Vide, elle est jetée.";
        picture="\z\comspec_atak_native\addons\main\data\item_powerbank.paa";
        model="\A3\Weapons_F\Ammo\mag_univ.p3d";
        type=4096; detectRange=-1; simulation="ItemMineDetector";
        class ItemInfo: InventoryItem_Base_F { mass=10; };
    };
    class COMSPEC_ATAK_NvgFilter: ItemCore {
        scope=2; scopeCurator=2; author="COMSPEC";
        displayName="Filtre d'écran JVN (ATAK)";
        descriptionShort="Film à poser sur l'écran du téléphone : coupe le proche infrarouge et assombrit l'écran. De nuit, l'écran allumé ne se voit presque plus de loin, même aux JVN.";
        picture="\z\comspec_atak_native\addons\main\data\item_nvgfilter.paa";
        model="\A3\Weapons_F\Ammo\mag_univ.p3d";
        type=4096; detectRange=-1; simulation="ItemMineDetector";
        class ItemInfo: InventoryItem_Base_F { mass=1; };
    };
    class COMSPEC_ATAK_VehicleKit: ItemCore {
        scope=2; scopeCurator=2; author="COMSPEC";
        displayName="Kit terminal véhicule ATAK";
        descriptionShort="Tablette durcie et support RAM à installer dans un véhicule (action ACE sur le véhicule) quand le serveur réserve la tablette de bord aux véhicules équipés.";
        picture="\z\comspec_atak_native\addons\main\data\item_vehiclekit.paa";
        model="\A3\Weapons_F\Ammo\mag_univ.p3d";
        type=4096; detectRange=-1; simulation="ItemMineDetector";
        class ItemInfo: InventoryItem_Base_F { mass=30; };
    };
    // Notre téléphone (Samsung S7 en coque tactique) : compte toujours comme terminal ATAK (fn_deviceCatalog).
    // Caméra IP fixe (fn_camAction) : ACE « Téléphone ATAK > Caméras > Poser une caméra », vue dans l'app Vidéo live.
    class COMSPEC_ATAK_Camera: ItemCore {
        scope=2; scopeCurator=2; author="COMSPEC";
        displayName="Caméra IP compacte (ATAK)";
        descriptionShort="Petite caméra IP Wi-Fi à coller sur un mur, au sol ou sur un véhicule (ACE « Téléphone ATAK > Caméras »). Le camp la regarde dans l'app Vidéo live (jour, JVN, thermique) tant qu'un ATAK est à portée. Récupérable.";
        picture="\z\comspec_atak_native\addons\main\data\item_camera.paa";
        model="\A3\Weapons_F\Ammo\mag_univ.p3d";
        type=4096; detectRange=-1; simulation="ItemMineDetector";
        class ItemInfo: InventoryItem_Base_F { mass=4; };
    };
    // Socle de recharge CS-10 plié (fn_dockClient « deploy ») : ACE Équipement > « Poser le socle de recharge ».
    class COMSPEC_ATAK_DockKit: ItemCore {
        scope=2; scopeCurator=2; author="COMSPEC";
        displayName="Socle de recharge CS-10 (10 téléphones)";
        descriptionShort="Station de charge durcie pour 10 téléphones ATAK. À poser devant soi (sur une table ou au sol) : chacun y met son téléphone, il se recharge, et le reprend plus tard, même à la partie suivante.";
        picture="\z\comspec_atak_native\addons\main\data\item_dock.paa";
        model="\A3\Weapons_F\Ammo\mag_univ.p3d";
        type=4096; detectRange=-1; simulation="ItemMineDetector";
        class ItemInfo: InventoryItem_Base_F { mass=80; };
    };
    class COMSPEC_ATAK_Phone: ItemCore {
        scope=2; scopeCurator=2; author="COMSPEC";
        displayName="Téléphone COMSPEC ATAK (Samsung S7)";
        descriptionShort="Samsung Galaxy S7 en coque tactique, ATAK COMSPEC installé. Suffit pour ouvrir le téléphone, sans autre mod.";
        picture="\z\comspec_atak_native\addons\main\data\item_phone.paa";
        model="\A3\Weapons_F\Ammo\mag_univ.p3d";
        type=4096; detectRange=-1; simulation="ItemMineDetector";
        class ItemInfo: InventoryItem_Base_F { mass=3; };
    };
};
class CfgVehicles {
    class Item_Base_F;
    class Static;
    // Téléphone porté sur le gilet (fn_vestMount) : objet simple accroché au torse, non listé dans l'éditeur.
    // Sélections camo (coque : vest_phone{,_od,_black}_co) et patch (velcro du dos : patch_*.paa, vide sans patch).
    class COMSPEC_ATAK_VestPhone: Static {
        scope=1; scopeCurator=0; author="COMSPEC"; displayName="Téléphone ATAK (gilet)";
        model="\z\comspec_atak_native\addons\main\data\vest_phone.p3d";
        hiddenSelections[]={"camo","patch"};
        hiddenSelectionsTextures[]={"\z\comspec_atak_native\addons\main\data\vest_phone_co.paa",""};
    };
    // Tablette durcie à touches sur le rabat du JPC (fn_jpcScreen, tools/model_jpc_tablet.py) : coque, écran (texture UI).
    class COMSPEC_ATAK_JpcTablet: Static {
        scope=1; scopeCurator=0; author="COMSPEC"; displayName="Tablette ATAK (JPC)";
        model="\z\comspec_atak_native\addons\main\data\jpc_tablet.p3d";
        hiddenSelections[]={"camo","screen"};
        hiddenSelectionsTextures[]={"\z\comspec_atak_native\addons\main\data\jpc_tablet_co.paa","#(argb,8,8,3)color(0,0,0,1)"};
        hiddenSelectionsMaterials[]={"","\z\comspec_atak_native\addons\main\data\jpc_screen.rvmat"};
    };
    // Téléphone du profil ouvert sur le JPC (fn_jpcScreen, tools/model_jpc_phones.py) : S7 par défaut, coques.
    class COMSPEC_ATAK_JpcPhone_s7: COMSPEC_ATAK_JpcTablet {
        displayName="Téléphone ATAK (JPC)";
        model="\z\comspec_atak_native\addons\main\data\jpc_s7.p3d";
        hiddenSelectionsTextures[]={"\z\comspec_atak_native\addons\main\data\jpc_s7_ca.paa","#(argb,8,8,3)color(0,0,0,1)"};
    };
    class COMSPEC_ATAK_JpcPhone_rugged: COMSPEC_ATAK_JpcPhone_s7 {
        model="\z\comspec_atak_native\addons\main\data\jpc_rugged.p3d";
        hiddenSelectionsTextures[]={"\z\comspec_atak_native\addons\main\data\jpc_rugged_ca.paa","#(argb,8,8,3)color(0,0,0,1)"};
    };
    class COMSPEC_ATAK_JpcPhone_iphone: COMSPEC_ATAK_JpcPhone_s7 {
        model="\z\comspec_atak_native\addons\main\data\jpc_iphone.p3d";
        hiddenSelectionsTextures[]={"\z\comspec_atak_native\addons\main\data\jpc_iphone_ca.paa","#(argb,8,8,3)color(0,0,0,1)"};
    };
    class COMSPEC_ATAK_JpcPhone_plate: COMSPEC_ATAK_JpcPhone_s7 {
        model="\z\comspec_atak_native\addons\main\data\jpc_plate.p3d";
        hiddenSelectionsTextures[]={"\z\comspec_atak_native\addons\main\data\jpc_plate_ca.paa","#(argb,8,8,3)color(0,0,0,1)"};
    };
    class COMSPEC_ATAK_JpcPhone_flip: COMSPEC_ATAK_JpcPhone_s7 {
        model="\z\comspec_atak_native\addons\main\data\jpc_flip.p3d";
        hiddenSelectionsTextures[]={"\z\comspec_atak_native\addons\main\data\jpc_flip_ca.paa","#(argb,8,8,3)color(0,0,0,1)"};
    };
    class COMSPEC_ATAK_JpcPhone_ribbed: COMSPEC_ATAK_JpcPhone_s7 {
        model="\z\comspec_atak_native\addons\main\data\jpc_ribbed.p3d";
        hiddenSelectionsTextures[]={"\z\comspec_atak_native\addons\main\data\jpc_ribbed_ca.paa","#(argb,8,8,3)color(0,0,0,1)"};
    };
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
    class Item_COMSPEC_ATAK_DataCable: Item_Base_F {
        scope=2; scopeCurator=2; author="COMSPEC";
        displayName="Câble de données DL-7";
        vehicleClass="Items"; editorCategory="EdCat_Equipment"; editorSubcategory="EdSubcat_InventoryItems";
        class TransportItems { class _xx_COMSPEC_ATAK_DataCable { name="COMSPEC_ATAK_DataCable"; count=1; }; };
    };
    class Item_COMSPEC_ATAK_Antenna_Stub: Item_Base_F {
        scope=2; scopeCurator=2; author="COMSPEC";
        displayName="Antenne courte SMA (ATAK)";
        vehicleClass="Items"; editorCategory="EdCat_Equipment"; editorSubcategory="EdSubcat_InventoryItems";
        class TransportItems { class _xx_COMSPEC_ATAK_Antenna_Stub { name="COMSPEC_ATAK_Antenna_Stub"; count=1; }; };
    };
    class Item_COMSPEC_ATAK_Antenna_Whip: Item_Base_F {
        scope=2; scopeCurator=2; author="COMSPEC";
        displayName="Antenne fouet longue (ATAK)";
        vehicleClass="Items"; editorCategory="EdCat_Equipment"; editorSubcategory="EdSubcat_InventoryItems";
        class TransportItems { class _xx_COMSPEC_ATAK_Antenna_Whip { name="COMSPEC_ATAK_Antenna_Whip"; count=1; }; };
    };
    class Item_COMSPEC_ATAK_Antenna_Patch: Item_Base_F {
        scope=2; scopeCurator=2; author="COMSPEC";
        displayName="Antenne patch 9 dBi (ATAK)";
        vehicleClass="Items"; editorCategory="EdCat_Equipment"; editorSubcategory="EdSubcat_InventoryItems";
        class TransportItems { class _xx_COMSPEC_ATAK_Antenna_Patch { name="COMSPEC_ATAK_Antenna_Patch"; count=1; }; };
    };
    class Item_COMSPEC_ATAK_PowerBank: Item_Base_F {
        scope=2; scopeCurator=2; author="COMSPEC";
        displayName="Batterie externe ATAK 10 000 mAh";
        vehicleClass="Items"; editorCategory="EdCat_Equipment"; editorSubcategory="EdSubcat_InventoryItems";
        class TransportItems { class _xx_COMSPEC_ATAK_PowerBank { name="COMSPEC_ATAK_PowerBank"; count=1; }; };
    };
    class Item_COMSPEC_ATAK_NvgFilter: Item_Base_F {
        scope=2; scopeCurator=2; author="COMSPEC";
        displayName="Filtre d'écran JVN (ATAK)";
        vehicleClass="Items"; editorCategory="EdCat_Equipment"; editorSubcategory="EdSubcat_InventoryItems";
        class TransportItems { class _xx_COMSPEC_ATAK_NvgFilter { name="COMSPEC_ATAK_NvgFilter"; count=1; }; };
    };
    class Item_COMSPEC_ATAK_VehicleKit: Item_Base_F {
        scope=2; scopeCurator=2; author="COMSPEC";
        displayName="Kit terminal véhicule ATAK";
        vehicleClass="Items"; editorCategory="EdCat_Equipment"; editorSubcategory="EdSubcat_InventoryItems";
        class TransportItems { class _xx_COMSPEC_ATAK_VehicleKit { name="COMSPEC_ATAK_VehicleKit"; count=1; }; };
    };
    class Item_COMSPEC_ATAK_Camera: Item_Base_F {
        scope=2; scopeCurator=2; author="COMSPEC";
        displayName="Caméra IP compacte (ATAK)";
        vehicleClass="Items"; editorCategory="EdCat_Equipment"; editorSubcategory="EdSubcat_InventoryItems";
        class TransportItems { class _xx_COMSPEC_ATAK_Camera { name="COMSPEC_ATAK_Camera"; count=1; }; };
    };
    // Caméra IP posée (fn_camAction) : petit objet global, destructible (tirs, explosions), non listé dans l'éditeur.
    class COMSPEC_ATAK_CameraObj: Static {
        scope=1; scopeCurator=0; author="COMSPEC"; displayName="Caméra IP (ATAK)";
        model="\A3\Structures_F\Items\Electronics\HandyCam_F.p3d";
        armor=3;
    };
    class Item_COMSPEC_ATAK_DockKit: Item_Base_F {
        scope=2; scopeCurator=2; author="COMSPEC";
        displayName="Socle de recharge CS-10 (plié)";
        vehicleClass="Items"; editorCategory="EdCat_Equipment"; editorSubcategory="EdSubcat_InventoryItems";
        class TransportItems { class _xx_COMSPEC_ATAK_DockKit { name="COMSPEC_ATAK_DockKit"; count=1; }; };
    };
    // Socle de recharge CS-10 (fn_dockServer) : 10 téléphones sur deux gradins, recharge, mémoire serveur par joueur.
    // Sélections : phone1..10 (0-9), screen1..10 (10-19), led1..10 (20-29), pilotées par fn_dockVisual.
    class COMSPEC_ATAK_ChargingDock: Static {
        scope=2; scopeCurator=2; author="COMSPEC";
        displayName="Socle de recharge CS-10 (10 téléphones ATAK)";
        model="\z\comspec_atak_native\addons\main\data\charging_dock.p3d";
        editorPreview="\z\comspec_atak_native\addons\main\data\charging_dock_preview.jpg";
        icon="iconObject_1x1"; mapSize=0.6;
        vehicleClass="Items"; editorCategory="EdCat_Things"; editorSubcategory="EdSubcat_Electronics";
        destrType="DestructNo"; armor=200;
        hiddenSelections[]={"phone1","phone2","phone3","phone4","phone5","phone6","phone7","phone8","phone9","phone10",
            "screen1","screen2","screen3","screen4","screen5","screen6","screen7","screen8","screen9","screen10",
            "led1","led2","led3","led4","led5","led6","led7","led8","led9","led10"};
        hiddenSelectionsTextures[]={"","","","","","","","","","","","","","","","","","","","",
            "#(argb,8,8,3)color(0.03,0.03,0.03,1,co)","#(argb,8,8,3)color(0.03,0.03,0.03,1,co)","#(argb,8,8,3)color(0.03,0.03,0.03,1,co)","#(argb,8,8,3)color(0.03,0.03,0.03,1,co)","#(argb,8,8,3)color(0.03,0.03,0.03,1,co)",
            "#(argb,8,8,3)color(0.03,0.03,0.03,1,co)","#(argb,8,8,3)color(0.03,0.03,0.03,1,co)","#(argb,8,8,3)color(0.03,0.03,0.03,1,co)","#(argb,8,8,3)color(0.03,0.03,0.03,1,co)","#(argb,8,8,3)color(0.03,0.03,0.03,1,co)"};
        class ACE_Actions { class ACE_MainActions { displayName="Interactions"; selection=""; distance=3; condition="true"; position="[0, 0, 0.12]"; }; };
        class Attributes {
            class COMSPEC_ATAK_DockId {
                displayName="Identifiant du socle";
                tooltip="Même identifiant d'une mission à l'autre = mêmes téléphones retrouvés (ex. « FOB-Alpha »). Vide : la carte et la position du socle.";
                property="COMSPEC_ATAK_DockId"; control="Edit"; typeName="STRING"; defaultValue="''";
                expression="if (_value isNotEqualTo '') then { _this setVariable ['COMSPEC_ATAK_DockId', _value, true]; };";
            };
        };
    };
    class Item_COMSPEC_ATAK_Phone: Item_Base_F {
        scope=2; scopeCurator=2; author="COMSPEC";
        displayName="Téléphone COMSPEC ATAK (Samsung S7)";
        vehicleClass="Items"; editorCategory="EdCat_Equipment"; editorSubcategory="EdSubcat_InventoryItems";
        class TransportItems { class _xx_COMSPEC_ATAK_Phone { name="COMSPEC_ATAK_Phone"; count=1; }; };
    };
    // Modules Zeus / Eden « ATAK · Données » (extraction au câble DL-7).
    #include "modules.hpp"
};
class CfgFactionClasses {
    class NO_CATEGORY;
    class COMSPEC_ATAK_Data: NO_CATEGORY { displayName="COMSPEC — ATAK · Données (extraction)"; priority=2; side=7; };
};

class Extended_PreInit_EventHandlers { class comspec_atak_native_main { init="call compile preprocessFileLineNumbers 'z\comspec_atak_native\addons\main\XEH_preInit.sqf'"; }; };
class Extended_PostInit_EventHandlers { class comspec_atak_native_main { clientInit="call compile preprocessFileLineNumbers 'z\comspec_atak_native\addons\main\XEH_postInitClient.sqf'"; serverInit="call compile preprocessFileLineNumbers 'z\comspec_atak_native\addons\main\XEH_postInitServer.sqf'"; }; };
#include "ui/controls.hpp"
#include "ui/displays.hpp"
