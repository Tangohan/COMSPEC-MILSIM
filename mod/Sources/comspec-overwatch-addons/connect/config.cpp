class CfgPatches {
    class comspec_overwatch_connect {
        name = "COMSPEC Overwatch Connect";
        units[] = {};
        weapons[] = {};
        requiredVersion = 1.0;
        requiredAddons[] = {"comspec_overwatch_main", "cba_main", "cba_xeh", "cba_settings"};
        author = "COMSPEC";
        version = 1.20;
        versionStr = "1.2.0";
        versionAr[] = {1, 2, 0};
    };
};

// Format obligatoire Tag > Category > Function (file = dossier des fn_*.sqf).
class CfgFunctions {
    class comspec_overwatch_connect {
        tag = "comspec_overwatch_connect";
        class connect {
            file = "z\comspec_overwatch\addons\connect\functions";
            class connect {};
            class waitAthenaReady {};
            class startSyncLoops {};
            class sendFactionSettings {};
            class sendTacticalAlert {};
            class tacticalAlertDialogShow {};
            class saluteDialogShow {};
            class saluteDialogSubmit {};
            class disconnect {};
            class playtimeTracker {};
            class updatePosition {};
            class forceSyncData {};
            class sendIntel {};
            class initACE {};
            class aceAddSelfAction {};
            class pauseManagerShow {};
            class pauseManagerOnLoad {};
            class pauseManagerPageLoaded {};
            class pauseManagerJSDialog {};
            class onInterruptLoad {};
            class log {};
            class logDump {};
            class submitChat {};
            class submitChatPhoto {};
            class openHub {};
            class openTabletView {};
            class tabletChatSend {};
            class tabletFlightManifestSend {};
            class hubSelect {};
            class getRadioState {};
            class getRadioTxState {};
            class scanRadioProximity {};
            class monitorRadioNet {};
            class initRadioMonitor {};
            class getMedicalState {};
            class checkMedicalAlerts {};
            class reportMedicalAlert {};
            class canTriageMedical {};
            class medicalInboxShow {};
            class medicalInboxOnLoad {};
            class medicalTriage {};
            class pollMedicalAlerts {};
            class hasOwnActiveMedicalAlert {};
            class selfCancelMedicalAlert {};
            class getAircraftType {};
            class fillFlightManifest {};
            class submitFlightManifest {};
            class requestFireSolution {};
            class receiveFireSolution {};
            class displayFireSolution {};
            class pilotResponse {};
            class openCASDialog {};
            class receiveCASRequest {};
            class updateCASState {};
            class checkCASLine {};
            class sendCASAck {};
            class sendCASStatus {};
            class receiveMapShape {};
            class deleteMapShape {};
            class pollMapShapes {};
            class captureReconImage {};
            class syncLaserCode {};
            class receiveDangerZone {};
            class updateDangerZone {};
            class deleteDangerZone {};
            class checkPlayerInDangerZone {};
            class warnDangerZoneEntry {};
            class sendLogisticsStatus {};
            class receiveIFFChallenge {};
            class submitIFFResponse {};
            class updateIFFMarkerState {};
            class registerEventHandler {};
            class publishEvent {};
            class issueOrder {};
            class updateOrderStatus {};
            class receiveOrder {};
            class orderConcernsPlayer {};
            class pollOrders {};
            class pollTacticalAlerts {};
            class orderInboxShow {};
            class orderInboxOnLoad {};
            class orderRespond {};
            class formatCommsMessage {};
            class getBriefingSlides {};
            class getFireTeams {};
            class downloadBriefingSlide {};
            class openBriefingBoard {};
            class briefingBoardShow {};
            class briefingBoardStep {};
            class refreshBriefingSlides {};
            class getPhoneConnectInfo {};
            class phoneConnectShow {};
            class accountLinkShow {};
            class accountLinkOnLoad {};
            class accountLinkSubmit {};
            class measureLatency {};
            class refreshAccountLinkStatusBar {};
            class showAthenaLinkHelp {};
            class showBetaAccessNote {};
            class ndaTexts {};
            class ndaOnLoad {};
            class ndaSetLanguage {};
            class ndaAccept {};
            class ndaDecline {};
            class resetBetaNdaAck {};
            class registerBetaClient {};
            class onMainMenuLoad {};
            class getCallsign {};
            class setCallsign {};
            
            // ATAK Phase 1 & 2 - Nouvelles fonctions
            class submitTacticalReport {};
            class createPOI {};
            class requestMEDEVAC {};
            class requestQRF {};
            class updateVehicleTracking {};
            class requestVehicleService {};
            class initVehicleTracking {};
            class initATAKMenu {};
            class initATAK {};
            class hashMapToJson {};
            class formatTimestamp {};
            class syncCallsignFromAthena {};
            class callsignDialogShow {};
            class callsignDialogOnLoad {};
            class callsignDialogSubmit {};
            class playAtakNotification {};
            class shouldShowScreenNotification {};
            class addScreenToast {};
            class showNotification {};
            class announce {};
            class pushHtmlAlert {};
            class getUnitRole {};
            class setUnitRole {};
            class extResult {};
            class extensionStatus {};
            class extensionLoadHint {};
            class getModVersion {};
            class detectLoadedMods {};
            class refreshLinkStatus {};
            class updateStatusBadges {};
            class chatDialogOnLoad {};
            class appendLinkLog {};
            class appendModuleLog {};
            class isModModuleEnabled {};
            class pollModModules {};
            class pollExperience {};
            class applyTenantExperience {};
            class showExperienceGuide {};
            class portalLabel {};
            class toggleLogCategory {};
            class showDebugInfo {};
            class profileWrap {};
            class profileReport {};
            class getPlayerAvatarInfo {};
            class showPlayerProfile {};
            class showDeviceView {};
            class deviceToggleView {};
            class getUnitsList {};
            class showDeviceRoster {};
            class openClassicTablet {};
            class webBrowserAvailable {};
            class webBrowserShow {};
            class webBrowserOnLoad {};
            class webBrowserPageLoaded {};
            class webBrowserJSDialog {};
            class placeMarkerFromTablet {};
            class webBrowserOpenAthena {};
            class webBrowserOpenSystem {};
            class webBrowserJsEscape {};
            class webBrowserMapShow {};
            class webBrowserMapHide {};
            class webBrowserMapOnDraw {};
            class webBrowserMapCenter {};
            class webBrowserMapZoom {};
            class updateLinkDiary {};
            class extensionCallback {};
            class hasTerminal {};
            class canOpenOverwatchUi {};
            class syncMapMarker {};
            class gridPosition {};
            class formatHeading {};
            class trackPacketLoss {};
            class recordPacketSent {};
            class recordPacketReceived {};
            class getPacketLossStats {};
            class handlePositionUpdateCallback {};
            class simulateNetworkDisconnect {};
            class isNetworkDisconnected {};
            class getNetworkDisconnectInfo {};
            class playRoleplaySound {};
            class injectRoleplayEffectsInBrowser {};
            class checkAtakDamage {};
            class isAtakFunctional {};
            class repairAtak {};
            class addAtakRepairAction {};
            class updateAtakEnhancedRoleplay {};
            class playAtakEnhancedSound {};
            class createRoleplayZone {};
            class deleteRoleplayZone {};
            class getPlayerRoleplayZone {};
            class applyZoneEffects {};
            class listRoleplayZones {};
            class moduleNoCoverage {};
            class moduleInterference {};
            class moduleDegraded {};
            class moduleJammer {};
        };
    };
};

// Whitelist URI pour CT_WEBBROWSER / htmlLoad (inspiré cTab intel + domaines Athena).
class CfgCommands {
    allowedHTMLLoadURIs[] += {
        "https://athena.ttrd.fr/*",
        "https://compsec.ttrd.fr/*",
        "http://localhost/*",
        "http://127.0.0.1/*",
        "http://localhost:*/*",
        "http://127.0.0.1:*/*",
        "file://z/comspec_overwatch/addons/connect/web/*"
    };
};

// Templates réutilisables : 1er arg de BIS_fnc_showNotification = nom de classe, pas le texte FR.
// Ex. : ["COMSPEC_Info", ["Message lisible"]] call comspec_overwatch_connect_fnc_showNotification;
class CfgNotifications {
    class COMSPEC_Info {
        title = "COMSPEC";
        iconPicture = "\z\comspec_overwatch\addons\connect\img\device\comspec_icon_phone.paa";
        iconText = "";
        description = "%1";
        color[] = {0.5, 0.9, 0.85, 1};
        duration = 5;
        priority = 5;
        difficulty[] = {};
    };
    class COMSPEC_Warning {
        title = "COMSPEC";
        iconPicture = "\A3\ui_f\data\map\markers\military\warning_CA.paa";
        iconText = "";
        description = "%1";
        color[] = {0.95, 0.55, 0.15, 1};
        duration = 6;
        priority = 7;
        difficulty[] = {};
    };
};

class CfgSounds {
    sounds[] = {};
    class COMSPEC_ATAK_SilentVib {
        name = "Silent (vibration)";
        sound[] = {"\z\comspec_overwatch\addons\connect\sounds\atak_silencieux_vibration.ogg", 1, 1, 50};
        titles[] = {};
    };
    class COMSPEC_ATAK_Stalker {
        name = "Stalker";
        sound[] = {"\z\comspec_overwatch\addons\connect\sounds\sound_1_stalker.ogg", 1, 1, 50};
        titles[] = {};
    };
    class COMSPEC_ATAK_Health {
        name = "Health alert";
        sound[] = {"\z\comspec_overwatch\addons\connect\sounds\atak_no_activyt_health.ogg", 1, 1, 50};
        titles[] = {};
    };
    class COMSPEC_ATAK_Start {
        name = "ATAK startup";
        sound[] = {"\z\comspec_overwatch\addons\connect\sounds\atak_start.ogg", 1, 1, 50};
        titles[] = {};
    };
    class COMSPEC_ATAK_Disconnect {
        name = "ATAK disconnect";
        sound[] = {"\z\comspec_overwatch\addons\connect\sounds\atak_disconnect.ogg", 1, 1, 50};
        titles[] = {};
    };
    class COMSPEC_ATAK_Unconscious {
        name = "Unconscious alert";
        sound[] = {"\z\comspec_overwatch\addons\connect\sounds\atak_alert_2.ogg", 1, 1, 50};
        titles[] = {};
    };
    class COMSPEC_ATAK_Death {
        name = "Death alert";
        sound[] = {"\z\comspec_overwatch\addons\connect\sounds\atak_death.ogg", 1, 1, 50};
        titles[] = {};
    };
    class COMSPEC_ATAK_Order {
        name = "Ordre reçu";
        sound[] = {"\z\comspec_overwatch\addons\connect\sounds\roger_simple.ogg", 1, 1, 50};
        titles[] = {};
    };
    class COMSPEC_ATAK_OrderPrio {
        name = "Ordre prioritaire";
        sound[] = {"\z\comspec_overwatch\addons\connect\sounds\roger_prio.ogg", 1, 1, 50};
        titles[] = {};
    };
};

// Catégorie Zeus/Eden pour modules roleplay
class CfgFactionClasses
{
    class NO_CATEGORY;
    class COMSPEC_Roleplay : NO_CATEGORY
    {
        displayName = "COMSPEC Roleplay";
        priority = 2;
        side = 7;
    };
};

#include "ui_base.hpp"
#include "display.hpp"
#include "display_hub.hpp"
#include "display_cas.hpp"
#include "display_flight_manifest.hpp"
#include "display_briefing.hpp"
#include "display_phone_connect.hpp"
#include "display_account_link.hpp"
#include "display_nda.hpp"
#include "display_callsign.hpp"
#include "display_salute.hpp"
#include "display_order_inbox.hpp"
#include "display_medical_inbox.hpp"
#include "display_device.hpp"
#include "display_webbrowser.hpp"
#include "display_pause_manager.hpp"

// Bouton menu Échap : injecté en SQF (DisplayLoad), pas via héritage RscDisplayInterrupt
// — l’héritage config casse le démarrage Arma (Undefined base / Member already defined).

// Modules Zeus temporairement désactivés (conflits pack ACE/ZEN).
// Réactiver: #include "modules\module_roleplay_zone.hpp" + units[] + A3_Modules_F
#include "CfgEventHandlers.hpp"
