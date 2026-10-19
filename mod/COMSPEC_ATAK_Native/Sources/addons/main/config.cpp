#include "script_component.hpp"
class CfgPatches {
    class comspec_atak_native_main {
        name="COMSPEC ATAK Native Standalone"; author="COMSPEC"; requiredVersion=2.14;
        requiredAddons[]={"A3_UI_F","A3_Weapons_F","A3_Modules_F","A3_Anims_F_Config_Sdr","cba_main","cba_xeh","3DEN"};
        units[]={"Item_COMSPEC_ATAK_Battery","Item_COMSPEC_ATAK_RepairKit","Item_COMSPEC_ATAK_DataCable","Item_COMSPEC_ATAK_Antenna_Stub","Item_COMSPEC_ATAK_Antenna_Whip","Item_COMSPEC_ATAK_Antenna_Patch","Item_COMSPEC_ATAK_PowerBank","Item_COMSPEC_ATAK_NvgFilter","Item_COMSPEC_ATAK_VehicleKit","Item_COMSPEC_ATAK_Camera","Item_COMSPEC_ATAK_DockKit","Item_COMSPEC_ATAK_GarminWatch","COMSPEC_ATAK_ChargingDock","COMSPEC_ATAK_Module_DataNode","COMSPEC_ATAK_Module_DataFile","COMSPEC_ATAK_Module_DataReset","COMSPEC_ATAK_Module_DatingProfile","COMSPEC_ATAK_Module_ZeusControl","COMSPEC_ATAK_Module_ZeusJammer","COMSPEC_ATAK_Module_Relay"}; weapons[]={"COMSPEC_ATAK_Battery","COMSPEC_ATAK_RepairKit","COMSPEC_ATAK_DataCable","COMSPEC_ATAK_Antenna_Stub","COMSPEC_ATAK_Antenna_Whip","COMSPEC_ATAK_Antenna_Patch","COMSPEC_ATAK_PowerBank","COMSPEC_ATAK_NvgFilter","COMSPEC_ATAK_VehicleKit","COMSPEC_ATAK_Camera","COMSPEC_ATAK_DockKit","COMSPEC_ATAK_GarminWatch"}; version=1.8; versionStr=VERSION_STR; versionAr[]={1,8,0};
    };
};
// Langues du mod en plus du français (fn_langList). Un mod de traduction communautaire ajoute sa propre classe
// (localisation/README.md) : elle apparaît dans le réglage « Langue / Language » sans toucher à ce mod.
class CfgComspecLanguages {
    class en { name="English"; file="\z\comspec_atak_native\addons\main\i18n\en.txt"; gameLanguage[]={"English"}; };
    // Chinois simplifié : traduction communautaire de RickLeo et MOYA (pack comspec_atak_zh 1.1), intégrée au mod ;
    // les textes qu'elle ne couvre pas restent en anglais (en.txt est chargé d'abord).
    class zh_cn { name="简体中文"; file="\z\comspec_atak_native\addons\main\i18n\zh_cn.txt"; gameLanguage[]={"Chinesesimp"}; };
};
class CfgFunctions {
    class comspec_atak_native { tag="comspec_atak_native";
        class core { file="z\comspec_atak_native\addons\main\functions\core";
            class log {}; class battery {}; class gpsFix {}; class pli {}; class weather {}; class stateInit {}; class storeSet {}; class schedulerStart {}; class schedulerStop {}; class schedulerTick {}; class debugDump {}; class deviceCatalog {}; class deviceScan {}; class hasDevice {}; class deviceDenied {}; class tenantRule {}; class pref {}; class tenantApply {}; class canUse {}; class batterySwap {}; class linkQuality {}; class perfAcc {}; class deviceHealth {}; class deviceDamage {}; class deviceRepair {}; class phoneIdent {}; class ramUsage {}; class athenaPulse {}; class syncStats {}; class batteryItem {}; class aircrewTerminal {}; class repairStart {}; class deviceImpact {}; class deviceReport {}; class screenDirt {}; class screenClean {}; class medShow {}; class medVitals {}; class integrityCheck {}; class startupCheck {}; class simSos {};
        };
        class ui { file="z\comspec_atak_native\addons\main\functions\ui";
            class display {}; class open {}; class close {}; class hudToggle {}; class interactToggle {}; class orientationToggle {}; class phoneDrag {}; class formRender {}; class formValue {}; class keyBlock {}; class keybinds {}; class fullAlert {}; class vibrate {}; class dataBarEnabled {}; class displayLoad {}; class displayUnload {}; class layoutGet {}; class layoutApply {}; class fontSet {}; class fontApply {}; class fontText {}; class navigate {}; class back {}; class modeToggle {};
            class pageRender {}; class pageClear {}; class pageCtrl {}; class textFit {}; class tileCreate {}; class appList {}; class appBadge {}; class chatUnread {}; class dockRender {}; class launcherRender {}; class deviceOverlay {}; class appVisible {}; class accent {};
            class statusUpdate {}; class inspectorUpdate {}; class abbrev {}; class notify {}; class notificationsRender {}; class notifCenter {}; class notifMeta {}; class powerFx {}; class overlayFront {}; class foreignHud {}; class screenSurface {};
            class fx {}; class toast {}; class buzzAlert {}; class quickSettings {}; class lockScreen {}; class tr {}; class langApply {}; class langList {}; class langResolve {}; class addSetting {}; class aceAction {}; class zenAction {}; class zenModule {}; class trMenu {}; class menuRelang {}; class keyRelang {};
        };
        class map { file="z\comspec_atak_native\addons\main\functions\map";
            class mapOnDraw {}; class mapMouseButtonDown {}; class mapSelect {}; class mapToolSet {}; class symbology {}; class natoSymbol {}; class mapLabels {}; class mapMarkerText {}; class mapPlaceText {}; class mapGameMarkers {}; class mapTextW {}; class mapCtxMenu {}; class mapPing {}; class localDataRefresh {}; class mapCenter {}; class mapMouseMoving {}; class mapOverlayUpdate {}; class markerDrop {}; class unitCallsign {}; class unitGroup {}; class gridRef {}; class mapZoom {}; class mapToolMenu {}; class mapToolRun {}; class markerAt {}; class markerCatalog {}; class markerEditOpen {}; class markerEditor {}; class markerEditSave {}; class markerDelete {}; class markerWeb {}; class markerWebSweep {}; class markerStroke {}; class mapMouseButtonUp {}; class mapDblClick {}; class mapUnfocus {}; class inspToggle {}; class markerPalette {}; class markerPaletteData {}; class markerPlace {}; class markerDeletable {}; class markerLabels {}; class markerAutoName {}; class sigintPoll {}; class reliefDraw {}; class meshDraw {}; class logisticsDraw {}; class ewDraw {}; class mapSearch {}; class mapStyle {}; class mapCtrl {}; class photoMap {}; class textureKeep {}; class strokeSimplify {}; class strokeDraw {}; class strokeBar {};
        };
        class gestures { file="z\comspec_atak_native\addons\main\functions\gestures";
            class gestureFinger {}; class gestureFingerAim {}; class gestureSelfie {};
        };
        class plan { file="z\comspec_atak_native\addons\main\functions\plan";
            class planCatalog {}; class planGfx {}; class planDraw {}; class planServer {}; class planAction {}; class planMap {}; class planWeb {}; class pagePlan {}; class planUi {}; class planHelp {};
        };
        class network { file="z\comspec_atak_native\addons\main\functions\network";
            class extensionCall {}; class relayTower {}; class relayTowerRuin {}; class relayEden {}; class relayZeus {}; class extAwait {}; class extensionCallback {}; class serverPresence {}; class remoteSync {}; class importLegacyData {};
            class pollUnits {}; class pollMarkers {}; class pollOrders {}; class pollChat {};
            class p2pSend {}; class p2pReceive {}; class p2pAck {}; class p2pReachable {}; class p2pDate {}; class athenaSignal {}; class netSend {}; class radioLink {}; class netGuide {}; class netCond {}; class netWeather {}; class netLoad {}; class netConfig {}; class offline {}; class relayNet {}; class videoLink {}; class moduleRelay {}; class bridge {}; class avatarPath {}; class athenaMode {}; class athenaChoice {}; class athenaLink {}; class athenaStatus {}; class feedLink {}; class extVersion {}; class mpu5 {};
        };
        class viz { file="z\comspec_atak_native\addons\main\functions\viz";
            class vizEcg {}; class vizSpectrum {}; class vizPlayer {};
        };
        class music { file="z\comspec_atak_native\addons\main\functions\music";
            class musicState {}; class musicAction {}; class musicTick {}; class musicServerList {}; class musicAthena {}; class pageMusic {};
        };
        class nav { file="z\comspec_atak_native\addons\main\functions\nav";
            class routeCompute {}; class routeGuide {}; class routeBanner {}; class wpAction {}; class wpAt {}; class wpCard {}; class wpDraw {}; class pageWaypoints {}; class pageGps {}; class gpsAction {};
        };
        class explo { file="z\comspec_atak_native\addons\main\functions\explo";
            class exploList {}; class exploAction {}; class pageExplo {};
        };
        class tactical { file="z\comspec_atak_native\addons\main\functions\tactical";
            class sniperBallistics {}; class pageSniper {}; class sniperAction {}; class jtacTarget {}; class pageJtac {}; class jtacAction {}; class jtacAir {}; class jtacLines {}; class jtacGeometry {}; class jtacFah {}; class jtacDraw {};
            class breachBuilding {}; class pageBreach {}; class breachAction {}; class breachTop {};
        };
        class drone { file="z\comspec_atak_native\addons\main\functions\drone";
            class droneCmd {}; class droneAction {}; class droneOsd {}; class droneDraw {}; class pageDrone {}; class droneDetectScan {}; class pageDroneDetect {}; class droneDetectDraw {}; class lockAction {}; class droneFlir {}; class droneSeq {}; class dronePhoto {}; class droneStatic {}; class noFly {}; class droneFleet {}; class droneRange {};
        };
        class bluetooth { file="z\comspec_atak_native\addons\main\functions\bluetooth";
            class airplaneMode {}; class btAction {}; class btSounds {}; class btItems {}; class pageBluetooth {}; class btInit { postInit=1; };
        };
        class shell { file="z\comspec_atak_native\addons\main\functions\shell";
            class antenna {}; class backView {}; class aceMenu {}; class vestMount {}; class powerBank {}; class screenGlow {}; class phoneSecurity {}; class droppedPhone {}; class patchTexture {}; class patchCatalog {}; class pagePatches {}; class pageAccessories {}; class accessoriesAction {}; class vestBuiltin {}; class jpcScreen {}; class jpcMap {}; class captureServer {}; class compromise {}; class compromiseView {}; class adminNotices {};
        };
        class vehicle { file="z\comspec_atak_native\addons\main\functions\vehicle";
            class vehicleTerminal {}; class vehicleGateway {};
        };
        class datalink { file="z\comspec_atak_native\addons\main\functions\datalink";
            class dataSetup {}; class dataParseFiles {}; class dataServer {}; class dataAction {}; class dataInit {}; class dataOverlay {}; class pageDataLink {}; class zeusForm {}; class moduleContext {}; class moduleDataNode {}; class moduleDataFile {}; class moduleDataReset {}; class moduleDating {};
        };
        class reports { file="z\comspec_atak_native\addons\main\functions\reports";
            class reportTypes {}; class reportAction {}; class pageReports {}; class reportForm {}; class reportAuto {};
        };
        class teams { file="z\comspec_atak_native\addons\main\functions\teams";
            class ftCatalog {}; class ftInfo {}; class ftTeams {}; class ftServer {}; class ftAction {}; class ftRows {}; class ftCenter {};
            class json {}; class squadSnapshot {}; class squadSync {}; class pageInterTeam {}; class interTeamAction {}; class ftRoleSync {};
        };
        class screen { file="z\comspec_atak_native\addons\main\functions\screen";
            class roleKey {}; class screenTime {}; class pageScreenTime {}; class playTimeServer {}; class distanceServer {}; class pageDistance {}; class distanceLog {};
        };
        class garmin { file="z\comspec_atak_native\addons\main\functions\garmin";
            class garminTick {}; class pageGarmin {};
        };
        class aar { file="z\comspec_atak_native\addons\main\functions\aar";
            class aarRecord {}; class aarAction {}; class aarDraw {}; class pageAar {};
        };
        class zeus { file="z\comspec_atak_native\addons\main\functions\zeus";
            class zeusFx {}; class zeusJam {}; class zeusServer {}; class zeusClient {}; class zeusOverlay {}; class zeusMenu {}; class moduleZeusControl {}; class moduleZeusJammer {};
        };
        class dock { file="z\comspec_atak_native\addons\main\functions\dock";
            class dockServer {}; class dockVisual {}; class dockClient {}; class dockMenu {};
        };
        class camera { file="z\comspec_atak_native\addons\main\functions\camera";
            class camAction {}; class camView {}; class pipCheck {}; class camVar {}; class camShare {}; class photoFaces {}; class blackStage {}; class mapCapture {};
        };
        class pages { file="z\comspec_atak_native\addons\main\functions\pages";
            class chatSend {}; class taskAction {}; class briefingStep {};
            class pageMap {}; class pageChat {}; class chatParse {}; class messagesAll {}; class tasksAll {}; class pageGroup {}; class pageTasks {}; class pageText {}; class pageAthena {}; class athenaAction {}; class pageNetwork {}; class pageSettings {}; class pagePhotos {}; class photoMode {}; class photoCam {}; class pageGallery {}; class pageTracking {}; class pageFrs {}; class frsDraftSave {}; class frsToggleTheme {}; class frsSubmit {}; class frsAction {}; class frsLibrary {}; class pageReco {}; class recoDraftSave {}; class recoSubmit {}; class pageFires {}; class firesState {}; class firesGuns {}; class firesSave {}; class firesAction {}; class firesCff {}; class firesWind {}; class pageLivecam {}; class livecamStart {}; class livecamStop {}; class pageAlerts {}; class pageSalute {}; class alertsAction {}; class alertsLog {}; class pageMedical {}; class medicalAction {}; class pageProfile {}; class profileAction {}; class pageBriefing {}; class chatCommand {}; class briefingAction {}; class briefingCfg {}; class briefingSettings {}; class briefingUrl {}; class briefingSignature {}; class briefingLive {}; class photoLibrary {}; class livecamShare {}; class pageBft {}; class bftAction {}; class bftFilter {}; class groupTypes {}; class groupAction {}; class pageRecents {}; class pageNotifs {}; class pageDebug {}; class pageFood {}; class foodAction {}; class pageDating {}; class datingAction {}; class datingCam {}; class datingSpecials {}; class pageOsint {}; class osintAction {}; class pageWeather {}; class pageRelief {}; class reliefAction {}; class pageWaveRelay {}; class waveRelayAction {}; class pageLogistics {}; class logisticsAction {}; class logiWeb {}; class pageEw {}; class ewAction {}; class ewEffects {}; class pageBda {}; class bdaAction {}; class bdaLabels {}; class pageCredits {}; class pageResynch {}; class resynchRun {}; class pageOrderCompose {}; class orderAction {}; class orderAck {}; class pageStatus {}; class pageSse {}; class pageC2 {}; class sseAction {}; class pageWanted {}; class wantedAction {}; class pageLinkAlly {}; class linkAllyAction {}; class linkAllyInit {}; class pageDiscord {}; class discordAction {};
        };
    };
};

// Registre des applications du lanceur (même principe que les app.hpp de BCE) :
// un autre addon peut ajouter une classe ici pour apparaître dans le lanceur.
//   page    : page du routeur ouverte au clic
//   icon    : texture PAA (icônes COMSPEC dans data\, générées par tools/gen_assets.py)
//   section : regroupement dans le lanceur ; order : tri croissant ; dock=1 : raccourci du dock
//   wip=1   : app en chantier (W.I.P) : tuile grisée avec bandeau, ne s'ouvre pas, code gardé
// Sections (dans l'ordre) : Navigation 10+, Communication 30+, Appui et feux 50+, Spécialités 70+,
// Renseignement 110+, Mission 200+, Système 300+, Civil 395+.
class COMSPEC_ATAK_Apps {
    class Map         { name="Carte"; page="MAP"; section="Navigation"; order=10; dock=1; icon="\z\comspec_atak_native\addons\main\data\app_map.paa"; };
    class Gps         { name="GPS"; page="GPS"; section="Navigation"; order=12; dock=0; icon="\z\comspec_atak_native\addons\main\data\app_gps.paa"; };
    class Waypoints   { name="Itinéraire"; page="WAYPOINTS"; section="Navigation"; order=14; dock=0; icon="\z\comspec_atak_native\addons\main\data\app_waypoints.paa"; };
    class Relief      { name="Relief"; page="RELIEF"; section="Navigation"; order=16; dock=0; icon="\z\comspec_atak_native\addons\main\data\app_relief.paa"; };
    class Weather     { name="Météo"; page="WEATHER"; section="Navigation"; order=18; dock=0; icon="\z\comspec_atak_native\addons\main\data\app_weather.paa"; };
    class Distance    { name="Distances"; page="DISTANCE"; new=1; function="comspec_atak_native_fnc_pageDistance"; section="Navigation"; order=19; dock=0; icon="\z\comspec_atak_native\addons\main\data\app_distance.paa"; };
    class Garmin      { name="Garmin"; page="GARMIN"; new=1; function="comspec_atak_native_fnc_pageGarmin"; section="Navigation"; order=20; dock=0; icon="\z\comspec_atak_native\addons\main\data\app_garmin.paa"; };
    class Chat        { name="Messages"; page="CHAT"; section="Communication"; order=30; dock=1; icon="\z\comspec_atak_native\addons\main\data\app_chat.paa"; };
    class Group       { name="Groupe"; page="GROUP"; section="Communication"; order=32; dock=1; icon="\z\comspec_atak_native\addons\main\data\app_group.paa"; };
    class Alerts      { name="Alertes"; page="ALERTS"; section="Communication"; order=34; dock=0; icon="\z\comspec_atak_native\addons\main\data\app_alerts.paa"; };
    class Bft         { name="BFT"; page="BFT"; section="Communication"; order=36; dock=0; icon="\z\comspec_atak_native\addons\main\data\app_bft.paa"; };
    class InterTeam   { name="Inter-team"; page="INTERTEAM"; function="comspec_atak_native_fnc_pageInterTeam"; section="Communication"; order=37; dock=0; icon="\z\comspec_atak_native\addons\main\data\app_interteam.paa"; };
    class C2          { name="C2"; page="C2"; section="Communication"; order=38; dock=0; icon="\z\comspec_atak_native\addons\main\data\app_c2.paa"; };
    class WaveRelay   { name="Wave Relay"; page="WAVERELAY"; section="Communication"; order=40; dock=0; icon="\z\comspec_atak_native\addons\main\data\app_waverelay.paa"; };
    class Fires       { name="CAS"; page="FIRES"; section="Appui et feux"; order=50; dock=0; icon="\z\comspec_atak_native\addons\main\data\app_fires.paa"; };
    class Jtac        { name="JTAC"; page="JTAC"; section="Appui et feux"; order=52; dock=0; icon="\z\comspec_atak_native\addons\main\data\app_jtac.paa"; };
    class Drone       { name="Drone"; page="DRONE"; section="Appui et feux"; order=54; dock=0; icon="\z\comspec_atak_native\addons\main\data\app_drone.paa"; };
    class Medical     { name="Médical"; page="MEDICAL"; section="Spécialités"; order=70; dock=0; icon="\z\comspec_atak_native\addons\main\data\app_medical.paa"; };
    class Sniper      { name="Sniper"; page="SNIPER"; section="Spécialités"; order=72; dock=0; icon="\z\comspec_atak_native\addons\main\data\app_sniper.paa"; };
    class Breach      { name="Brèche"; page="BREACH"; section="Spécialités"; order=74; dock=0; wip=1; icon="\z\comspec_atak_native\addons\main\data\app_breach.paa"; };
    class Explo       { name="Explosifs"; page="EXPLO"; section="Spécialités"; order=76; dock=0; icon="\z\comspec_atak_native\addons\main\data\app_explo.paa"; };
    class Ew          { name="GE"; page="EW"; section="Spécialités"; order=78; dock=0; icon="\z\comspec_atak_native\addons\main\data\app_ew.paa"; };
    class DroneDetect { name="Anti-drone"; page="DRONEDETECT"; section="Spécialités"; order=80; dock=0; icon="\z\comspec_atak_native\addons\main\data\app_dronedetect.paa"; };
    class Intel       { name="FRS / FRM"; page="FRS"; section="Renseignement"; order=110; dock=0; icon="\z\comspec_atak_native\addons\main\data\app_intel.paa"; };
    class Reco        { name="Reco"; page="RECO"; section="Renseignement"; order=112; dock=0; icon="\z\comspec_atak_native\addons\main\data\app_reco.paa"; };
    class Salute      { name="SALUTE"; page="SALUTE"; section="Renseignement"; order=113; dock=0; icon="\z\comspec_atak_native\addons\main\data\app_salute.paa"; };
    class Photos      { name="Photo"; page="PHOTOS"; section="Renseignement"; order=114; dock=0; icon="\z\comspec_atak_native\addons\main\data\app_photos.paa"; };
    class Gallery     { name="Galerie"; page="GALLERY"; function="comspec_atak_native_fnc_pageGallery"; section="Renseignement"; order=115; dock=0; icon="\z\comspec_atak_native\addons\main\data\app_gallery.paa"; };
    class Livecam     { name="Vidéo live"; page="LIVECAM"; section="Renseignement"; order=116; dock=0; icon="\z\comspec_atak_native\addons\main\data\app_livecam.paa"; };
    class Tracking    { name="Marquer tracking"; page="TRACKING"; function="comspec_atak_native_fnc_pageTracking"; section="Renseignement"; order=117; dock=0; icon="\z\comspec_atak_native\addons\main\data\app_dronedetect.paa"; };
    class DataLink    { name="Extraction"; page="DATALINK"; function="comspec_atak_native_fnc_pageDataLink"; section="Renseignement"; order=119; dock=0; icon="\z\comspec_atak_native\addons\main\data\app_datalink.paa"; };
    class Sse         { name="SSE"; page="SSE"; section="Renseignement"; order=118; dock=0; icon="\z\comspec_atak_native\addons\main\data\app_sse.paa"; };
    class Osint       { name="OSINT"; page="OSINT"; section="Renseignement"; order=120; dock=0; wip=1; icon="\z\comspec_atak_native\addons\main\data\app_osint.paa"; };
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

// Objets d'inventaire : vrais modèles 3D au sol et dans l'éditeur (tools/model_items.py), dans le PBO items (addons/items/data).
// Batterie de rechange du téléphone (action ACE « Changer la batterie ATAK », consommée au changement).
class CfgWeapons {
    class ItemCore;
    class ItemWatch;
    class InventoryItem_Base_F;
    class COMSPEC_ATAK_Battery: ItemCore {
        scope=2; scopeCurator=2; author="COMSPEC";
        displayName="$STR_COMSPEC_ATAK_CFG_9E4D5298F6";
        descriptionShort="$STR_COMSPEC_ATAK_CFG_96B5F49A5D";
        picture="\z\comspec_atak_native\addons\main\data\item_battery.paa";
        model="\z\comspec_atak_native\addons\items\data\item3d_battery.p3d";
        type=4096; detectRange=-1; simulation="ItemMineDetector";
        class ItemInfo: InventoryItem_Base_F { mass=2; };
    };
    // Radio maillée MPU5 (Wave Relay, fn_mpu5) : pas de modèle 3D propre, modèle et icône de la radio d'Arma.
    // Portée dans l'inventaire, elle relève la portée du nœud dans le maillage et relaie le débit reçu à l'équipe.
    class COMSPEC_ATAK_MPU5: ItemCore {
        scope=2; scopeCurator=2; author="COMSPEC";
        displayName="$STR_COMSPEC_ATAK_CFG_09B95E249C";
        descriptionShort="$STR_COMSPEC_ATAK_CFG_A55435B201";
        picture="\A3\Weapons_F\Data\UI\gear_item_radio_ca.paa";
        model="\A3\Weapons_F\Ammo\mag_radio.p3d";
        type=4096; detectRange=-1; simulation="ItemMineDetector";
        class ItemInfo: InventoryItem_Base_F { mass=15; };
    };
    // Kit de réparation du téléphone (action ACE « Réparer le téléphone ATAK », consommé ; fn_repairStart).
    class COMSPEC_ATAK_RepairKit: ItemCore {
        scope=2; scopeCurator=2; author="COMSPEC";
        displayName="$STR_COMSPEC_ATAK_CFG_814EE34AA5";
        descriptionShort="$STR_COMSPEC_ATAK_CFG_DC496FDDDD";
        picture="\z\comspec_atak_native\addons\main\data\item_repairkit.paa";
        model="\z\comspec_atak_native\addons\items\data\item3d_repairkit.p3d";
        type=4096; detectRange=-1; simulation="ItemMineDetector";
        class ItemInfo: InventoryItem_Base_F { mass=10; };
    };
    // Câble de données DL-7 (app Extraction) : obligatoire si le réglage serveur « Câble DL-7 obligatoire » est coché.
    class COMSPEC_ATAK_DataCable: ItemCore {
        scope=2; scopeCurator=2; author="COMSPEC";
        displayName="$STR_COMSPEC_ATAK_CFG_5BC13315C6";
        descriptionShort="$STR_COMSPEC_ATAK_CFG_1707ED67D7";
        picture="\z\comspec_atak_native\addons\main\data\item_datacable.paa";
        model="\z\comspec_atak_native\addons\items\data\item3d_datacable.p3d";
        type=4096; detectRange=-1; simulation="ItemMineDetector";
        class ItemInfo: InventoryItem_Base_F { mass=4; };
    };
    // Antenne d'extension de débit (fn_antenna) : ACE « Téléphone ATAK > Antenne » ou app Réglages.
    class COMSPEC_ATAK_Antenna_Stub: ItemCore {
        scope=2; scopeCurator=2; author="COMSPEC";
        displayName="$STR_COMSPEC_ATAK_CFG_A200DC2D3C";
        descriptionShort="$STR_COMSPEC_ATAK_CFG_B2101F06B2";
        picture="\z\comspec_atak_native\addons\main\data\item_antenna_stub.paa";
        model="\z\comspec_atak_native\addons\items\data\item3d_stub.p3d";
        type=4096; detectRange=-1; simulation="ItemMineDetector";
        class ItemInfo: InventoryItem_Base_F { mass=1; };
    };
    // Antenne d'extension de débit (fn_antenna) : ACE « Téléphone ATAK > Antenne » ou app Réglages.
    class COMSPEC_ATAK_Antenna_Whip: ItemCore {
        scope=2; scopeCurator=2; author="COMSPEC";
        displayName="$STR_COMSPEC_ATAK_CFG_9EFEB0E683";
        descriptionShort="$STR_COMSPEC_ATAK_CFG_02FF4B66E0";
        picture="\z\comspec_atak_native\addons\main\data\item_antenna_whip.paa";
        model="\z\comspec_atak_native\addons\items\data\item3d_whip.p3d";
        type=4096; detectRange=-1; simulation="ItemMineDetector";
        class ItemInfo: InventoryItem_Base_F { mass=3; };
    };
    // Antenne d'extension de débit (fn_antenna) : ACE « Téléphone ATAK > Antenne » ou app Réglages.
    class COMSPEC_ATAK_Antenna_Patch: ItemCore {
        scope=2; scopeCurator=2; author="COMSPEC";
        displayName="$STR_COMSPEC_ATAK_CFG_0D7BE9D700";
        descriptionShort="$STR_COMSPEC_ATAK_CFG_FBA3573C81";
        picture="\z\comspec_atak_native\addons\main\data\item_antenna_patch.paa";
        model="\z\comspec_atak_native\addons\items\data\item3d_patch.p3d";
        type=4096; detectRange=-1; simulation="ItemMineDetector";
        class ItemInfo: InventoryItem_Base_F { mass=4; };
    };
    class COMSPEC_ATAK_PowerBank: ItemCore {
        scope=2; scopeCurator=2; author="COMSPEC";
        displayName="$STR_COMSPEC_ATAK_CFG_EF53A02082";
        descriptionShort="$STR_COMSPEC_ATAK_CFG_0F70B04FFA";
        picture="\z\comspec_atak_native\addons\main\data\item_powerbank.paa";
        model="\z\comspec_atak_native\addons\items\data\item3d_powerbank.p3d";
        type=4096; detectRange=-1; simulation="ItemMineDetector";
        class ItemInfo: InventoryItem_Base_F { mass=10; };
    };
    class COMSPEC_ATAK_NvgFilter: ItemCore {
        scope=2; scopeCurator=2; author="COMSPEC";
        displayName="$STR_COMSPEC_ATAK_CFG_9BEF18E007";
        descriptionShort="$STR_COMSPEC_ATAK_CFG_F12FD15D32";
        picture="\z\comspec_atak_native\addons\main\data\item_nvgfilter.paa";
        model="\z\comspec_atak_native\addons\items\data\item3d_nvgfilter.p3d";
        type=4096; detectRange=-1; simulation="ItemMineDetector";
        class ItemInfo: InventoryItem_Base_F { mass=1; };
    };
    class COMSPEC_ATAK_VehicleKit: ItemCore {
        scope=2; scopeCurator=2; author="COMSPEC";
        displayName="$STR_COMSPEC_ATAK_CFG_32C19DF5F3";
        descriptionShort="$STR_COMSPEC_ATAK_CFG_DBD1600D8A";
        picture="\z\comspec_atak_native\addons\main\data\item_vehiclekit.paa";
        model="\z\comspec_atak_native\addons\items\data\item3d_vehiclekit.p3d";
        type=4096; detectRange=-1; simulation="ItemMineDetector";
        class ItemInfo: InventoryItem_Base_F { mass=30; };
    };
    // Notre téléphone (Samsung S7 en coque tactique) : compte toujours comme terminal ATAK (fn_deviceCatalog).
    // Caméra IP fixe (fn_camAction) : ACE « Téléphone ATAK > Caméras > Poser une caméra », vue dans l'app Vidéo live.
    class COMSPEC_ATAK_Camera: ItemCore {
        scope=2; scopeCurator=2; author="COMSPEC";
        displayName="$STR_COMSPEC_ATAK_CFG_857C44D0D3";
        descriptionShort="$STR_COMSPEC_ATAK_CFG_C98EF6B77B";
        picture="\z\comspec_atak_native\addons\main\data\item_camera.paa";
        model="\z\comspec_atak_native\addons\items\data\item3d_camera.p3d";
        type=4096; detectRange=-1; simulation="ItemMineDetector";
        class ItemInfo: InventoryItem_Base_F { mass=4; };
    };
    // Socle de recharge CS-10 plié (fn_dockClient « deploy ») : ACE Équipement > « Poser le socle de recharge ».
    class COMSPEC_ATAK_DockKit: ItemCore {
        scope=2; scopeCurator=2; author="COMSPEC";
        displayName="$STR_COMSPEC_ATAK_CFG_5DA6A7ED86";
        descriptionShort="$STR_COMSPEC_ATAK_CFG_C5AF6AC183";
        picture="\z\comspec_atak_native\addons\main\data\item_dock.paa";
        model="\z\comspec_atak_native\addons\items\data\item3d_dock.p3d";
        type=4096; detectRange=-1; simulation="ItemMineDetector";
        class ItemInfo: InventoryItem_Base_F { mass=80; };
    };
    // Montre GPS Garmin (app Garmin, fn_garminTick) : se porte dans l'emplacement montre (remplace la montre du jeu,
    // touche O). Obligatoire pour l'app Garmin tant que le réglage serveur « Montre Garmin obligatoire » est coché.
    class COMSPEC_ATAK_GarminWatch: ItemWatch {
        scope=2; scopeCurator=2; author="COMSPEC";
        displayName="$STR_COMSPEC_ATAK_CFG_957A6195AE";
        descriptionShort="$STR_COMSPEC_ATAK_CFG_0AFE1639F8";
        picture="\z\comspec_atak_native\addons\main\data\item_garminwatch.paa";
        model="\z\comspec_atak_native\addons\items\data\item3d_garminwatch.p3d";
    };
    class COMSPEC_ATAK_Phone: ItemCore {
        scope=2; scopeCurator=2; author="COMSPEC";
        displayName="$STR_COMSPEC_ATAK_CFG_A692CFB580";
        descriptionShort="$STR_COMSPEC_ATAK_CFG_D448798D17";
        picture="\z\comspec_atak_native\addons\main\data\item_phone.paa";
        // Au sol : le vrai S7 en coque tactique, couché écran vers le ciel (tools/model_ground_phone.py), pas la sacoche.
        model="\z\comspec_atak_native\addons\main\data\ground_phone.p3d";
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
        scope=1; scopeCurator=0; author="COMSPEC"; displayName="$STR_COMSPEC_ATAK_CFG_7B078E6A1E";
        model="\z\comspec_atak_native\addons\main\data\vest_phone.p3d";
        hiddenSelections[]={"camo","patch"};
        hiddenSelectionsTextures[]={"\z\comspec_atak_native\addons\main\data\vest_phone_co.paa",""};
    };
    // Tablette durcie à touches sur le rabat du JPC (fn_jpcScreen, tools/model_jpc_tablet.py) : coque, écran (texture UI).
    class COMSPEC_ATAK_JpcTablet: Static {
        scope=1; scopeCurator=0; author="COMSPEC"; displayName="$STR_COMSPEC_ATAK_CFG_2805C17B50";
        model="\z\comspec_atak_native\addons\main\data\jpc_tablet.p3d";
        hiddenSelections[]={"camo","screen"};
        hiddenSelectionsTextures[]={"\z\comspec_atak_native\addons\main\data\jpc_tablet_co.paa","#(argb,8,8,3)color(0,0,0,1)"};
        hiddenSelectionsMaterials[]={"","\z\comspec_atak_native\addons\main\data\jpc_screen.rvmat"};
    };
    // Téléphone du profil ouvert sur le JPC (fn_jpcScreen, tools/model_jpc_phones.py) : S7 par défaut, coques.
    class COMSPEC_ATAK_JpcPhone_s7: COMSPEC_ATAK_JpcTablet {
        displayName="$STR_COMSPEC_ATAK_CFG_0AB5F74BB5";
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
    class COMSPEC_ATAK_JpcPhone_sonim: COMSPEC_ATAK_JpcPhone_s7 {
        model="\z\comspec_atak_native\addons\main\data\jpc_sonim.p3d";
        hiddenSelectionsTextures[]={"\z\comspec_atak_native\addons\main\data\jpc_sonim_ca.paa","#(argb,8,8,3)color(0,0,0,1)"};
    };
    // S23 : même modèle que le S7 (silhouette et ouverture d'écran presque identiques), atlas du S23 (gen_assets.py jpc).
    class COMSPEC_ATAK_JpcPhone_s23: COMSPEC_ATAK_JpcPhone_s7 {
        hiddenSelectionsTextures[]={"\z\comspec_atak_native\addons\main\data\jpc_s23_ca.paa","#(argb,8,8,3)color(0,0,0,1)"};
    };
    class Item_COMSPEC_ATAK_Battery: Item_Base_F {
        scope=2; scopeCurator=2; author="COMSPEC";
        displayName="$STR_COMSPEC_ATAK_CFG_9E4D5298F6";
        model="\z\comspec_atak_native\addons\items\data\item3d_battery.p3d"; editorPreview="\z\comspec_atak_native\addons\items\data\item3d_battery_preview.jpg";
        vehicleClass="Items"; editorCategory="EdCat_Equipment"; editorSubcategory="EdSubcat_InventoryItems";
        class TransportItems { class _xx_COMSPEC_ATAK_Battery { name="COMSPEC_ATAK_Battery"; count=1; }; };
    };
    class Item_COMSPEC_ATAK_RepairKit: Item_Base_F {
        scope=2; scopeCurator=2; author="COMSPEC";
        displayName="$STR_COMSPEC_ATAK_CFG_814EE34AA5";
        model="\z\comspec_atak_native\addons\items\data\item3d_repairkit.p3d"; editorPreview="\z\comspec_atak_native\addons\items\data\item3d_repairkit_preview.jpg";
        vehicleClass="Items"; editorCategory="EdCat_Equipment"; editorSubcategory="EdSubcat_InventoryItems";
        class TransportItems { class _xx_COMSPEC_ATAK_RepairKit { name="COMSPEC_ATAK_RepairKit"; count=1; }; };
    };
    class Item_COMSPEC_ATAK_DataCable: Item_Base_F {
        scope=2; scopeCurator=2; author="COMSPEC";
        displayName="$STR_COMSPEC_ATAK_CFG_5BC13315C6";
        model="\z\comspec_atak_native\addons\items\data\item3d_datacable.p3d"; editorPreview="\z\comspec_atak_native\addons\items\data\item3d_datacable_preview.jpg";
        vehicleClass="Items"; editorCategory="EdCat_Equipment"; editorSubcategory="EdSubcat_InventoryItems";
        class TransportItems { class _xx_COMSPEC_ATAK_DataCable { name="COMSPEC_ATAK_DataCable"; count=1; }; };
    };
    class Item_COMSPEC_ATAK_Antenna_Stub: Item_Base_F {
        scope=2; scopeCurator=2; author="COMSPEC";
        displayName="$STR_COMSPEC_ATAK_CFG_A200DC2D3C";
        model="\z\comspec_atak_native\addons\items\data\item3d_stub.p3d"; editorPreview="\z\comspec_atak_native\addons\items\data\item3d_stub_preview.jpg";
        vehicleClass="Items"; editorCategory="EdCat_Equipment"; editorSubcategory="EdSubcat_InventoryItems";
        class TransportItems { class _xx_COMSPEC_ATAK_Antenna_Stub { name="COMSPEC_ATAK_Antenna_Stub"; count=1; }; };
    };
    class Item_COMSPEC_ATAK_Antenna_Whip: Item_Base_F {
        scope=2; scopeCurator=2; author="COMSPEC";
        displayName="$STR_COMSPEC_ATAK_CFG_9EFEB0E683";
        model="\z\comspec_atak_native\addons\items\data\item3d_whip.p3d"; editorPreview="\z\comspec_atak_native\addons\items\data\item3d_whip_preview.jpg";
        vehicleClass="Items"; editorCategory="EdCat_Equipment"; editorSubcategory="EdSubcat_InventoryItems";
        class TransportItems { class _xx_COMSPEC_ATAK_Antenna_Whip { name="COMSPEC_ATAK_Antenna_Whip"; count=1; }; };
    };
    class Item_COMSPEC_ATAK_Antenna_Patch: Item_Base_F {
        scope=2; scopeCurator=2; author="COMSPEC";
        displayName="$STR_COMSPEC_ATAK_CFG_0D7BE9D700";
        model="\z\comspec_atak_native\addons\items\data\item3d_patch.p3d"; editorPreview="\z\comspec_atak_native\addons\items\data\item3d_patch_preview.jpg";
        vehicleClass="Items"; editorCategory="EdCat_Equipment"; editorSubcategory="EdSubcat_InventoryItems";
        class TransportItems { class _xx_COMSPEC_ATAK_Antenna_Patch { name="COMSPEC_ATAK_Antenna_Patch"; count=1; }; };
    };
    class Item_COMSPEC_ATAK_PowerBank: Item_Base_F {
        scope=2; scopeCurator=2; author="COMSPEC";
        displayName="$STR_COMSPEC_ATAK_CFG_EF53A02082";
        model="\z\comspec_atak_native\addons\items\data\item3d_powerbank.p3d"; editorPreview="\z\comspec_atak_native\addons\items\data\item3d_powerbank_preview.jpg";
        vehicleClass="Items"; editorCategory="EdCat_Equipment"; editorSubcategory="EdSubcat_InventoryItems";
        class TransportItems { class _xx_COMSPEC_ATAK_PowerBank { name="COMSPEC_ATAK_PowerBank"; count=1; }; };
    };
    class Item_COMSPEC_ATAK_NvgFilter: Item_Base_F {
        scope=2; scopeCurator=2; author="COMSPEC";
        displayName="$STR_COMSPEC_ATAK_CFG_9BEF18E007";
        model="\z\comspec_atak_native\addons\items\data\item3d_nvgfilter.p3d"; editorPreview="\z\comspec_atak_native\addons\items\data\item3d_nvgfilter_preview.jpg";
        vehicleClass="Items"; editorCategory="EdCat_Equipment"; editorSubcategory="EdSubcat_InventoryItems";
        class TransportItems { class _xx_COMSPEC_ATAK_NvgFilter { name="COMSPEC_ATAK_NvgFilter"; count=1; }; };
    };
    class Item_COMSPEC_ATAK_VehicleKit: Item_Base_F {
        scope=2; scopeCurator=2; author="COMSPEC";
        displayName="$STR_COMSPEC_ATAK_CFG_32C19DF5F3";
        model="\z\comspec_atak_native\addons\items\data\item3d_vehiclekit.p3d"; editorPreview="\z\comspec_atak_native\addons\items\data\item3d_vehiclekit_preview.jpg";
        vehicleClass="Items"; editorCategory="EdCat_Equipment"; editorSubcategory="EdSubcat_InventoryItems";
        class TransportItems { class _xx_COMSPEC_ATAK_VehicleKit { name="COMSPEC_ATAK_VehicleKit"; count=1; }; };
    };
    class Item_COMSPEC_ATAK_Camera: Item_Base_F {
        scope=2; scopeCurator=2; author="COMSPEC";
        displayName="$STR_COMSPEC_ATAK_CFG_857C44D0D3";
        model="\z\comspec_atak_native\addons\items\data\item3d_camera.p3d"; editorPreview="\z\comspec_atak_native\addons\items\data\item3d_camera_preview.jpg";
        vehicleClass="Items"; editorCategory="EdCat_Equipment"; editorSubcategory="EdSubcat_InventoryItems";
        class TransportItems { class _xx_COMSPEC_ATAK_Camera { name="COMSPEC_ATAK_Camera"; count=1; }; };
    };
    // Caméra IP posée (fn_camAction) : petit objet global, destructible (tirs, explosions), non listé dans l'éditeur.
    // Modèle tools/model_items.py (camera_ip) : origine au centre du boîtier, base magnétique 9,2 cm dessous.
    class COMSPEC_ATAK_CameraObj: Static {
        scope=1; scopeCurator=0; author="COMSPEC"; displayName="$STR_COMSPEC_ATAK_CFG_A37BF51B41";
        model="\z\comspec_atak_native\addons\items\data\camera_ip.p3d";
        armor=3;
    };
    class Item_COMSPEC_ATAK_DockKit: Item_Base_F {
        scope=2; scopeCurator=2; author="COMSPEC";
        displayName="$STR_COMSPEC_ATAK_CFG_EB509FDE34";
        model="\z\comspec_atak_native\addons\items\data\item3d_dock.p3d"; editorPreview="\z\comspec_atak_native\addons\items\data\item3d_dock_preview.jpg";
        vehicleClass="Items"; editorCategory="EdCat_Equipment"; editorSubcategory="EdSubcat_InventoryItems";
        class TransportItems { class _xx_COMSPEC_ATAK_DockKit { name="COMSPEC_ATAK_DockKit"; count=1; }; };
    };
    // Socle de recharge CS-10 (fn_dockServer) : 10 téléphones sur deux gradins, recharge, mémoire serveur par joueur.
    // Sélections : phone1..10 (0-9), screen1..10 (10-19), led1..10 (20-29), pilotées par fn_dockVisual.
    // Socle vide par défaut (éditeur, avant le serveur) : téléphones et écrans transparents, voyants éteints.
    class COMSPEC_ATAK_ChargingDock: Static {
        scope=2; scopeCurator=2; author="COMSPEC";
        displayName="$STR_COMSPEC_ATAK_CFG_06C87F1C69";
        model="\z\comspec_atak_native\addons\main\data\charging_dock.p3d";
        editorPreview="\z\comspec_atak_native\addons\main\data\charging_dock_preview.jpg";
        icon="iconObject_1x1"; mapSize=0.6;
        vehicleClass="Items"; editorCategory="EdCat_Things"; editorSubcategory="EdSubcat_Electronics";
        destrType="DestructNo"; armor=200;
        hiddenSelections[]={"phone1","phone2","phone3","phone4","phone5","phone6","phone7","phone8","phone9","phone10",
            "screen1","screen2","screen3","screen4","screen5","screen6","screen7","screen8","screen9","screen10",
            "led1","led2","led3","led4","led5","led6","led7","led8","led9","led10"};
        hiddenSelectionsTextures[]={"#(argb,8,8,3)color(0,0,0,0,ca)","#(argb,8,8,3)color(0,0,0,0,ca)","#(argb,8,8,3)color(0,0,0,0,ca)","#(argb,8,8,3)color(0,0,0,0,ca)","#(argb,8,8,3)color(0,0,0,0,ca)","#(argb,8,8,3)color(0,0,0,0,ca)","#(argb,8,8,3)color(0,0,0,0,ca)","#(argb,8,8,3)color(0,0,0,0,ca)","#(argb,8,8,3)color(0,0,0,0,ca)","#(argb,8,8,3)color(0,0,0,0,ca)",
            "#(argb,8,8,3)color(0,0,0,0,ca)","#(argb,8,8,3)color(0,0,0,0,ca)","#(argb,8,8,3)color(0,0,0,0,ca)","#(argb,8,8,3)color(0,0,0,0,ca)","#(argb,8,8,3)color(0,0,0,0,ca)","#(argb,8,8,3)color(0,0,0,0,ca)","#(argb,8,8,3)color(0,0,0,0,ca)","#(argb,8,8,3)color(0,0,0,0,ca)","#(argb,8,8,3)color(0,0,0,0,ca)","#(argb,8,8,3)color(0,0,0,0,ca)",
            "#(argb,8,8,3)color(0.03,0.03,0.03,1,co)","#(argb,8,8,3)color(0.03,0.03,0.03,1,co)","#(argb,8,8,3)color(0.03,0.03,0.03,1,co)","#(argb,8,8,3)color(0.03,0.03,0.03,1,co)","#(argb,8,8,3)color(0.03,0.03,0.03,1,co)",
            "#(argb,8,8,3)color(0.03,0.03,0.03,1,co)","#(argb,8,8,3)color(0.03,0.03,0.03,1,co)","#(argb,8,8,3)color(0.03,0.03,0.03,1,co)","#(argb,8,8,3)color(0.03,0.03,0.03,1,co)","#(argb,8,8,3)color(0.03,0.03,0.03,1,co)"};
        class ACE_Actions { class ACE_MainActions { displayName="$STR_COMSPEC_ATAK_CFG_0B3583ECAA"; selection=""; distance=3; condition="true"; position="[0, 0, 0.12]"; }; };
        class Attributes {
            class COMSPEC_ATAK_DockId {
                displayName="$STR_COMSPEC_ATAK_CFG_6E3B29E2C5";
                tooltip="$STR_COMSPEC_ATAK_CFG_59CC6174A3";
                property="COMSPEC_ATAK_DockId"; control="Edit"; typeName="STRING"; defaultValue="''";
                expression="if (_value isNotEqualTo '') then { _this setVariable ['COMSPEC_ATAK_DockId', _value, true]; };";
            };
        };
    };
    class Item_COMSPEC_ATAK_GarminWatch: Item_Base_F {
        scope=2; scopeCurator=2; author="COMSPEC";
        displayName="$STR_COMSPEC_ATAK_CFG_957A6195AE";
        model="\z\comspec_atak_native\addons\items\data\item3d_garminwatch.p3d"; editorPreview="\z\comspec_atak_native\addons\items\data\item3d_garminwatch_preview.jpg";
        vehicleClass="Items"; editorCategory="EdCat_Equipment"; editorSubcategory="EdSubcat_InventoryItems";
        class TransportItems { class _xx_COMSPEC_ATAK_GarminWatch { name="COMSPEC_ATAK_GarminWatch"; count=1; }; };
    };
    class Item_COMSPEC_ATAK_Phone: Item_Base_F {
        scope=2; scopeCurator=2; author="COMSPEC";
        displayName="$STR_COMSPEC_ATAK_CFG_A692CFB580";
        model="\z\comspec_atak_native\addons\main\data\ground_phone.p3d";
        vehicleClass="Items"; editorCategory="EdCat_Equipment"; editorSubcategory="EdSubcat_InventoryItems";
        class TransportItems { class _xx_COMSPEC_ATAK_Phone { name="COMSPEC_ATAK_Phone"; count=1; }; };
    };
    // Modules Zeus / Eden « ATAK · Données » (extraction au câble DL-7).
    #include "modules.hpp"
};
class CfgFactionClasses {
    class NO_CATEGORY;
    class COMSPEC_ATAK_Data: NO_CATEGORY { displayName="$STR_COMSPEC_ATAK_CFG_BC3E09CB53"; priority=2; side=7; };
    class COMSPEC_ATAK_Civil: NO_CATEGORY { displayName="$STR_COMSPEC_ATAK_CFG_1B554EECB0"; priority=2; side=7; };
    class COMSPEC_ATAK_Zeus: NO_CATEGORY { displayName="$STR_COMSPEC_ATAK_CFG_C3B58F43CF"; priority=2; side=7; };
    class COMSPEC_ATAK_Network: NO_CATEGORY { displayName="$STR_COMSPEC_ATAK_CFG_0913AEF2ED"; priority=2; side=7; };
};

// Manuel de terrain (Eden et jeu, Aide > Manuel de terrain) : tutoriel des relais ATAK.
// %1 ligne vide, %2 puce, %3 et %4 surlignage (codes réservés de CfgHints).
class CfgHints {
    class COMSPEC_ATAK {
        displayName = "$STR_COMSPEC_ATAK_CFG_CF5C7F39D0";
        class Relays {
            displayName = "$STR_COMSPEC_ATAK_CFG_2C44314089";
            displayNameShort = "$STR_COMSPEC_ATAK_CFG_E4E74278CD";
            description = "$STR_COMSPEC_ATAK_CFG_BF0FFB1714";
            image = "\z\comspec_atak_native\addons\main\data\app_waverelay.paa";
            arguments[] = {};
        };
        class CameraShare {
            displayName = "$STR_COMSPEC_ATAK_CFG_BF7BE30836";
            displayNameShort = "$STR_COMSPEC_ATAK_CFG_146C6595D4";
            description = "$STR_COMSPEC_ATAK_CFG_C9AF05C42B";
            image = "\z\comspec_atak_native\addons\main\data\app_livecam.paa";
            arguments[] = {};
        };
        class ZeusControl {
            displayName = "$STR_COMSPEC_ATAK_CFG_E5E2FEEE38";
            displayNameShort = "$STR_COMSPEC_ATAK_CFG_46B8C2431E";
            description = "$STR_COMSPEC_ATAK_CFG_67446103B5";
            image = "\z\comspec_atak_native\addons\main\data\app_ew.paa";
            arguments[] = {};
        };
    };
};

// Eden : menu COMSPEC ATAK (TBR, tableau des relais ; aide relais ; guide réseau et simulation, fn_netGuide) et aperçu des tours posées par le module relais.
class ctrlMenuStrip;
class Display3DEN {
    class Controls {
        class MenuStrip: ctrlMenuStrip {
            class Items {
                items[] += {"COMSPEC_ATAK_Menu"};
                class COMSPEC_ATAK_Menu { text = "COMSPEC ATAK"; items[] = {"COMSPEC_ATAK_MenuRelays", "COMSPEC_ATAK_MenuRelayHelp", "COMSPEC_ATAK_MenuNetGuide"}; };
                class COMSPEC_ATAK_MenuRelays { text = "$STR_COMSPEC_ATAK_CFG_TBR_MENU"; action = "['table'] call comspec_atak_native_fnc_relayEden"; };
                class COMSPEC_ATAK_MenuRelayHelp { text = "$STR_COMSPEC_ATAK_CFG_TBR_HELP"; action = "['help'] call comspec_atak_native_fnc_relayEden"; };
                class COMSPEC_ATAK_MenuNetGuide { text = "$STR_COMSPEC_ATAK_CFG_NETGUIDE_MENU"; action = "[] call comspec_atak_native_fnc_netGuide"; };
            };
        };
    };
};
class Cfg3DEN {
    class EventHandlers {
        class comspec_atak_native {
            onMissionLoad = "['watch'] call comspec_atak_native_fnc_relayEden";
            onMissionNew = "['watch'] call comspec_atak_native_fnc_relayEden";
            onTerrainNew = "['watch'] call comspec_atak_native_fnc_relayEden";
        };
    };
};

class Extended_PreStart_EventHandlers { class comspec_atak_native_main { init="call compile preprocessFileLineNumbers 'z\comspec_atak_native\addons\main\XEH_preStart.sqf'"; }; };
class Extended_PreInit_EventHandlers { class comspec_atak_native_main { init="call compile preprocessFileLineNumbers 'z\comspec_atak_native\addons\main\XEH_preInit.sqf'"; }; };
// Intro du mod sur l'écran de démarrage d'Arma (une fois par lancement du jeu), jamais sur le menu principal.
// Marque COMSPEC (emblème, « OVERWATCH · ATAK NATIF », version) sur les chargements de mission.
class Extended_DisplayLoad_EventHandlers {
    // Menu Échap (solo et multijoueur) : bouton « Conditions réseau » (fn_netConfig), sous la barre COMSPEC d'Overwatch.
    class RscDisplayInterrupt { comspec_atak_native_netcfg="['button', _this select 0] call comspec_atak_native_fnc_netConfig"; };
    class RscDisplayMPInterrupt { comspec_atak_native_netcfg="['button', _this select 0] call comspec_atak_native_fnc_netConfig"; };
    class RscDisplayStart { comspec_atak_native_main="(_this select 0) call compile preprocessFileLineNumbers '\z\comspec_atak_native\addons\main\functions\ui\fn_introSplash.sqf'"; };
    class RscDisplayLoadMission { comspec_atak_native_main="(_this select 0) call compile preprocessFileLineNumbers '\z\comspec_atak_native\addons\main\functions\ui\fn_loadingBadge.sqf'"; };
    class RscDisplayLoading { comspec_atak_native_main="(_this select 0) call compile preprocessFileLineNumbers '\z\comspec_atak_native\addons\main\functions\ui\fn_loadingBadge.sqf'"; };
};
class Extended_PostInit_EventHandlers { class comspec_atak_native_main { clientInit="call compile preprocessFileLineNumbers 'z\comspec_atak_native\addons\main\XEH_postInitClient.sqf'"; serverInit="call compile preprocessFileLineNumbers 'z\comspec_atak_native\addons\main\XEH_postInitServer.sqf'"; }; };
#include "gestures.hpp"
#include "ui\fonts.hpp"
#include "ui\controls.hpp"
#include "ui\displays.hpp"
