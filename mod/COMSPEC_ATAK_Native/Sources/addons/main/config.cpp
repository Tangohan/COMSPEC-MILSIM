#include "script_component.hpp"
class CfgPatches {
    class comspec_atak_native_main {
        name="COMSPEC ATAK Native Standalone"; author="COMSPEC"; requiredVersion=2.14;
        requiredAddons[]={"A3_UI_F","cba_main","cba_xeh"};
        units[]={}; weapons[]={}; version=1.4; versionStr=VERSION_STR; versionAr[]={1,4,0};
    };
};
class CfgFunctions {
    class comspec_atak_native { tag="comspec_atak_native";
        class core { file="z\comspec_atak_native\addons\main\functions\core";
            class log {}; class battery {}; class weather {}; class stateInit {}; class storeSet {}; class schedulerStart {}; class schedulerStop {}; class schedulerTick {}; class debugDump {}; class deviceCatalog {}; class hasDevice {}; class deviceDenied {}; class tenantRule {}; class pref {}; class tenantApply {}; class canUse {}; class batterySwap {}; class linkQuality {}; class deviceHealth {}; class deviceDamage {}; class deviceRepair {}; class phoneIdent {};
        };
        class ui { file="z\comspec_atak_native\addons\main\functions\ui";
            class display {}; class open {}; class close {}; class hudToggle {}; class interactToggle {}; class orientationToggle {}; class phoneDrag {}; class formRender {}; class formValue {}; class fullAlert {}; class vibrate {}; class dataBarEnabled {}; class displayLoad {}; class displayUnload {}; class layoutGet {}; class layoutApply {}; class navigate {}; class back {}; class modeToggle {};
            class pageRender {}; class pageClear {}; class pageCtrl {}; class tileCreate {}; class appList {}; class appBadge {}; class dockRender {}; class launcherRender {}; class deviceOverlay {}; class appVisible {}; class accent {};
            class statusUpdate {}; class inspectorUpdate {}; class notify {}; class notificationsRender {}; class notifCenter {};
        };
        class map { file="z\comspec_atak_native\addons\main\functions\map";
            class mapOnDraw {}; class mapMouseButtonDown {}; class mapSelect {}; class mapToolSet {}; class symbology {}; class localDataRefresh {}; class mapCenter {}; class mapMouseMoving {}; class mapOverlayUpdate {}; class markerDrop {}; class unitCallsign {}; class unitGroup {}; class gridRef {}; class mapZoom {}; class mapToolMenu {}; class mapToolRun {}; class markerAt {}; class markerCatalog {}; class markerEditOpen {}; class markerEditor {}; class markerEditSave {}; class markerDelete {}; class markerWeb {}; class markerWebSweep {}; class markerStroke {}; class mapMouseButtonUp {}; class mapDblClick {}; class mapUnfocus {}; class inspToggle {}; class markerPalette {}; class markerPaletteData {}; class markerLabels {}; class sigintPoll {}; class reliefDraw {}; class meshDraw {}; class logisticsDraw {}; class ewDraw {};
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
        class aar { file="z\comspec_atak_native\addons\main\functions\aar";
            class aarRecord {}; class aarAction {}; class aarDraw {}; class pageAar {};
        };
        class pages { file="z\comspec_atak_native\addons\main\functions\pages";
            class chatSend {}; class taskAction {}; class briefingStep {}; class settingsSave {};
            class pageMap {}; class pageChat {}; class chatParse {}; class messagesAll {}; class tasksAll {}; class pageGroup {}; class pageTasks {}; class pageText {}; class pageAthena {}; class athenaAction {}; class pageNetwork {}; class pageSettings {}; class pagePhotos {}; class photoTake {}; class photoMode {}; class pageFrs {}; class frsDraftSave {}; class frsToggleTheme {}; class frsSubmit {}; class frsAction {}; class frsLibrary {}; class pageReco {}; class recoDraftSave {}; class recoSubmit {}; class pageFires {}; class firesState {}; class firesGuns {}; class firesSave {}; class firesAction {}; class pageLivecam {}; class livecamStart {}; class livecamStop {}; class pageAlerts {}; class alertsAction {}; class alertsLog {}; class pageMedical {}; class medicalAction {}; class pageProfile {}; class profileAction {}; class pageBriefing {}; class chatCommand {}; class briefingAction {}; class briefingSignature {}; class briefingLive {}; class photoLibrary {}; class livecamShare {}; class pageBft {}; class bftAction {}; class groupTypes {}; class groupAction {}; class pageRecents {}; class pageNotifs {}; class pageDebug {}; class pageFood {}; class foodAction {}; class pageDating {}; class datingAction {}; class pageOsint {}; class osintAction {}; class pageWeather {}; class pageRelief {}; class reliefAction {}; class pageWaveRelay {}; class waveRelayAction {}; class pageLogistics {}; class logisticsAction {}; class logiWeb {}; class pageEw {}; class ewAction {}; class ewEffects {}; class pageBda {}; class bdaAction {}; class bdaLabels {}; class pageCredits {}; class pageResynch {}; class resynchRun {}; class pageOrderCompose {}; class orderAction {}; class pageStatus {}; class pageSse {}; class pageC2 {}; class sseAction {}; class pageWanted {}; class wantedAction {};
        };
    };
};

// Registre des applications du lanceur (même principe que les app.hpp de BCE) :
// un autre addon peut ajouter une classe ici pour apparaître dans le lanceur.
//   page    : page du routeur ouverte au clic
//   icon    : texture PAA (icônes COMSPEC dans data\, générées par tools/gen_assets.py)
//   section : regroupement dans le lanceur ; order : tri croissant ; dock=1 : raccourci du dock
class COMSPEC_ATAK_Apps {
    class Map      { name="Carte";      page="MAP";      section="Opérations";    order=10;  dock=1; icon="\z\comspec_atak_native\addons\main\data\app_map.paa"; };
    class Chat     { name="Messagerie"; page="CHAT";     section="Opérations";    order=20;  dock=1; icon="\z\comspec_atak_native\addons\main\data\app_chat.paa"; };
    class Group    { name="Groupe";     page="GROUP";    section="Opérations";    order=30;  dock=1; icon="\z\comspec_atak_native\addons\main\data\app_group.paa"; };
    class Tasks    { name="Tâches";     page="TASK";     section="Opérations";    order=40;  dock=1; icon="\z\comspec_atak_native\addons\main\data\app_tasks.paa"; };
    class C2       { name="C2";         page="C2";       section="Opérations";    order=50;  dock=0; icon="\z\comspec_atak_native\addons\main\data\app_c2.paa"; };
    class Bft      { name="BFT";        page="BFT";      section="Opérations";    order=60;  dock=0; icon="\z\comspec_atak_native\addons\main\data\app_bft.paa"; };
    class Intel    { name="FRS / FRM";  page="FRS";      section="Renseignement"; order=110; dock=0; icon="\z\comspec_atak_native\addons\main\data\app_intel.paa"; };
    class Fires    { name="Feux";       page="FIRES";    section="Opérations";    order=45;  dock=0; icon="\z\comspec_atak_native\addons\main\data\app_fires.paa"; };
    class Alerts   { name="Alertes";    page="ALERTS";   section="Opérations";    order=42;  dock=0; icon="\z\comspec_atak_native\addons\main\data\app_alerts.paa"; };
    class Medical  { name="Médical";    page="MEDICAL";  section="Opérations";    order=48;  dock=0; icon="\z\comspec_atak_native\addons\main\data\app_medical.paa"; };
    class Livecam  { name="Live cam";   page="LIVECAM";  section="Renseignement"; order=118; dock=0; icon="\z\comspec_atak_native\addons\main\data\app_livecam.paa"; };
    class Reco     { name="Reco";       page="RECO";     section="Renseignement"; order=115; dock=0; icon="\z\comspec_atak_native\addons\main\data\app_reco.paa"; };
    class Wanted   { name="Avis de recherche"; page="WANTED"; section="Renseignement"; order=119; dock=0; icon="\z\comspec_atak_native\addons\main\data\app_wanted.paa"; };
    class Sse      { name="SSE";        page="SSE";      section="Renseignement"; order=120; dock=0; icon="\z\comspec_atak_native\addons\main\data\app_sse.paa"; };
    class Explo    { name="Explosifs";  page="EXPLO";    section="Opérations";    order=62;  dock=0; icon="\z\comspec_atak_native\addons\main\data\app_explo.paa"; };
    class Breach   { name="Breacher";   page="BREACH";   section="Opérations";    order=63;  dock=0; icon="\z\comspec_atak_native\addons\main\data\app_breach.paa"; };
    class Sniper   { name="Tireur d'élite"; page="SNIPER"; section="Opérations";  order=64;  dock=0; icon="\z\comspec_atak_native\addons\main\data\app_sniper.paa"; };
    class Drone    { name="Drone";      page="DRONE";    section="Opérations";    order=66;  dock=0; icon="\z\comspec_atak_native\addons\main\data\app_drone.paa"; };
    class DroneDetect { name="Détecteur de drones"; page="DRONEDETECT"; section="Renseignement"; order=126; dock=0; icon="\z\comspec_atak_native\addons\main\data\app_dronedetect.paa"; };
    class Jtac     { name="JTAC";       page="JTAC";     section="Opérations";    order=65;  dock=0; icon="\z\comspec_atak_native\addons\main\data\app_jtac.paa"; };
    class Bda      { name="BDA";        page="BDA";      section="Renseignement"; order=130; dock=0; icon="\z\comspec_atak_native\addons\main\data\app_bda.paa"; };
    class Profile  { name="Profil";     page="PROFILE";  section="Système";       order=295; dock=0; icon="\z\comspec_atak_native\addons\main\data\app_profile.paa"; };
    class Athena   { name="Athena";     page="ATHENA";   section="Système";       order=300; dock=1; icon="\z\comspec_atak_native\addons\main\data\app_athena.paa"; };
    class Network  { name="Réseau";     page="NETWORK";  section="Système";       order=305; dock=0; icon="\z\comspec_atak_native\addons\main\data\app_network.paa"; };
    class Photos   { name="Photos";     page="PHOTOS";   section="Renseignement"; order=140; dock=0; icon="\z\comspec_atak_native\addons\main\data\app_photos.paa"; };
    class Aar      { name="Rejeu mission"; page="AAR"; section="Mission";     order=215; dock=0; icon="\z\comspec_atak_native\addons\main\data\app_aar.paa"; };
    class Briefing { name="Briefing";   page="BRIEFING"; section="Mission";       order=210; dock=0; icon="\z\comspec_atak_native\addons\main\data\app_briefing.paa"; };
    class Status   { name="Statut";     page="STATUS";   section="Système";       order=310; dock=0; icon="\z\comspec_atak_native\addons\main\data\app_status.paa"; };
    class Gps       { name="GPS";        page="GPS";       section="Opérations";    order=12;  dock=0; icon="\z\comspec_atak_native\addons\main\data\app_gps.paa"; };
    class Waypoints { name="Points de passage"; page="WAYPOINTS"; section="Opérations"; order=15; dock=0; icon="\z\comspec_atak_native\addons\main\data\app_waypoints.paa"; };
    class Osint    { name="OSINT";      page="OSINT";    section="Renseignement"; order=125; dock=0; icon="\z\comspec_atak_native\addons\main\data\app_osint.paa"; };
    class Food     { name="UberEats"; page="FOOD"; section="Civil";         order=400; dock=0; icon="\z\comspec_atak_native\addons\main\data\app_food.paa"; };
    class Music    { name="Musique";    page="MUSIC";    section="Civil";         order=395; dock=0; icon="\z\comspec_atak_native\addons\main\data\app_music.paa"; };
    class Dating   { name="Tinder";    page="DATING";   section="Civil";         order=410; dock=0; icon="\z\comspec_atak_native\addons\main\data\app_dating.paa"; };
    class Debug    { name="Debug";      page="DEBUG";    section="Système";       order=315; dock=0; icon="\z\comspec_atak_native\addons\main\data\app_debug.paa"; };
    class Resynch   { name="Resynch";    page="RESYNCH";   section="Opérations";    order=57;  dock=0; icon="\z\comspec_atak_native\addons\main\data\app_resynch.paa"; };
    class Weather   { name="Météo";      page="WEATHER";   section="Opérations";    order=55;  dock=0; icon="\z\comspec_atak_native\addons\main\data\app_weather.paa"; };
    class Relief    { name="Relief";     page="RELIEF";    section="Renseignement"; order=128; dock=0; icon="\z\comspec_atak_native\addons\main\data\app_relief.paa"; };
    class WaveRelay { name="Wave Relay"; page="WAVERELAY"; section="Système";       order=307; dock=0; icon="\z\comspec_atak_native\addons\main\data\app_waverelay.paa"; };
    class Logistics { name="Logistique"; page="LOGI"; section="Opérations"; order=47; dock=0; icon="\z\comspec_atak_native\addons\main\data\app_logistics.paa"; };
    class Ew        { name="Guerre électronique"; page="EW"; section="Renseignement"; order=127; dock=0; icon="\z\comspec_atak_native\addons\main\data\app_ew.paa"; };
    class Credits  { name="Crédits";    page="CREDITS";  section="Système";       order=330; dock=0; icon="\z\comspec_atak_native\addons\main\data\app_credits.paa"; };
    class Settings { name="Réglages";   page="SETTINGS"; section="Système";       order=320; dock=0; icon="\z\comspec_atak_native\addons\main\data\app_settings.paa"; };
};

class Extended_PreInit_EventHandlers { class comspec_atak_native_main { init="call compile preprocessFileLineNumbers 'z\comspec_atak_native\addons\main\XEH_preInit.sqf'"; }; };
class Extended_PostInit_EventHandlers { class comspec_atak_native_main { clientInit="call compile preprocessFileLineNumbers 'z\comspec_atak_native\addons\main\XEH_postInitClient.sqf'"; serverInit="call compile preprocessFileLineNumbers 'z\comspec_atak_native\addons\main\XEH_postInitServer.sqf'"; }; };
#include "ui/controls.hpp"
#include "ui/displays.hpp"
