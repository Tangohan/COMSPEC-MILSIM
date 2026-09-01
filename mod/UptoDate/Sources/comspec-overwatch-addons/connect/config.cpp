class CfgPatches {
    class comspec_overwatch_connect {
        name = "COMSPEC Overwatch Connect";
        units[] = {
            "COMSPEC_Module_NoCoverage",
            "COMSPEC_Module_Interference",
            "COMSPEC_Module_Degraded",
            "COMSPEC_Module_Jammer",
            "COMSPEC_Module_SSE_Case",
            "COMSPEC_Module_SSE_Profile",
            "COMSPEC_Module_SSE_Equip",
            "COMSPEC_Module_TheaterSurvey",
            "COMSPEC_Module_AtakShowEnemyAi"
        };
        weapons[] = {};
        requiredVersion = 1.0;
        requiredAddons[] = {"comspec_overwatch_main", "cba_main", "cba_xeh", "cba_settings", "A3_Modules_F"};
        author = "COMSPEC";
        version = 1.495;
        versionStr = "1.4.95";
        versionAr[] = {1, 4, 95};
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
            class ssePersonDialogShow {};
            class ssePersonDialogOnLoad {};
            class ssePersonDialogSubmit {};
            class ssePersonRefreshPanels {};
            class sseCaptureFacePhoto {};
            class sseCollectMedical {};
            class sseBiometricSample {};
            class sseSignAtak {};
            class sseOpenTerminal {};
            class sseOpenFromKeybind {};
            class sseHasTerminalItem {};
            class sseIdentityQuery {};
            class sseUnitSeed {};
            class sseTerminalPage {};
            class sseActiveCase {};
            class sseApplyProfile {};
            class sseProfilePreset {};
            class sseModuleTargets {};
            // Fiches de renseignement simplifiées (rédacteur plein cadre ATAK)
            class intelNoteCatalog {};
            class intelNoteCache {};
            class intelNoteShow {};
            class intelNoteOnLoad {};
            class intelNoteOnUnload {};
            class intelNotePane {};
            class intelNoteRefresh {};
            class intelNoteToggleTheme {};
            class intelNoteAddPiece {};
            class intelNoteDropPiece {};
            class listLocalScreenshots {};
            class intelNoteCollectPhotos {};
            class intelNotePhotoPicker {};
            class intelNotePickPhoto {};
            class intelNoteAttachPhoto {};
            class intelNoteSaveDraft {};
            class intelNoteSubmit {};
            class intelNoteClose {};
            class intelNoteApplyGeometry {};
            class moduleSseCase {};
            class moduleSseProfile {};
            class moduleSseEquip {};
            class registerZenSseModules {};
            class giveSeekTerminal {};
            class medevacDialogShow {};
            class medevacDialogSubmit {};
            class casRequestShow {};
            class casRequestSubmit {};
            class casDialogShow {};
            class flightManifestShow {};
            class reportDiag {};
            class bugReportShow {};
            class bugReportSubmit {};
            class collectBugReportLog {};
            class disconnect {};
            class playtimeTracker {};
            class updatePosition {};
            class bftPlatform {};
            class applyNetworkProfile {};
            class sampleTerrain {};
            class sampleScene {};
            class sampleTheater {};
            class sampleGeoNetwork {};
            class theaterSurveyShow {};
            class theaterSurveyOnLoad {};
            class theaterSurveyRefresh {};
            class theaterSurveyCancel {};
            class theaterSurveyToggle {};
            class theaterSurveyVerify {};
            class moduleTheaterSurvey {};
            class registerZenTheaterSurvey {};
            class forceSyncData {};
            class arsenalLocalLoadouts {};
            class arsenalNormalizeLoadout {};
            class arsenalPushAll {};
            class arsenalPullAll {};
            class arsenalApplyCloud {};
            class arsenalOverlayShow {};
            class arsenalOverlayRefresh {};
            class arsenalInitOverlay {};
            class sendIntel {};
            class initACE {};
            class initExplosiveTimers {};
            class initChargeAceActions {};
            class reportExplosiveTimer {};
            class findChargeObject {};
            class detonateChargeLocal {};
            class detonateChargeById {};
            class pollExplosiveCommands {};
            class chargeUnhookClacker {};
            class chargeSetTrigger {};
            class chargeArmAtak {};
            class chargeOwnedAtak {};
            class chargeDetonateAll {};
            class chargeConfirmDetonate {};
            class aceAddSelfAction {};
            class aceSweepPlayerSelfActions {};
            class acePadAction {};
            class pauseManagerShow {};
            class pauseManagerOnLoad {};
            class pauseManagerPageLoaded {};
            class pauseManagerJSDialog {};
            class onInterruptLoad {};
            class log {};
            class logAtakEvent {};
            class logAtakStateChange {};
            class logDump {};
            class startLogSession {};
            class logTransmission {};
            class logFnError {};
            class callExtLogged {};
            class outboxPush {};
            class outboxFlush {};
            class outboxState {};
            class wallClockSeconds {};
            class submitChat {};
            class submitChatPhoto {};
            class openHub {};
            class openTabletView {};
            class tabletChatSend {};
            class tabletChatLines {};
            class tabletChatPush {};
            class tabletFlightManifestSend {};
            class hubSelect {};
            class getRadioState {};
            class getRadioTxState {};
            class scanRadioProximity {};
            class monitorRadioNet {};
            class initRadioMonitor {};
            class getMedicalState {};
            class isPlayerSpawnStable {};
            class canShowWinMessageBox {};
            class onPlayerRespawn {};
            class checkMedicalAlerts {};
            class reportMedicalAlert {};
            class pushIcemanMedicalAlert {};
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
            class pollAthenaMarkers {};
            class captureReconImage {};
            class getActiveCaptureCam {};
            class promoteCaptureCam {};
            class restoreCaptureCam {};
            class markBcePhotoCapture {};
            class syncLaserCode {};
            class receiveDangerZone {};
            class updateDangerZone {};
            class deleteDangerZone {};
            class checkPlayerInDangerZone {};
            class warnDangerZoneEntry {};
            class pollTacticalZones {};
            class sendLogisticsStatus {};
            class receiveIFFChallenge {};
            class submitIFFResponse {};
            class updateIFFMarkerState {};
            class registerEventHandler {};
            class publishEvent {};
            class issueOrder {};
            class canIssueOrder {};
            class orderComposeShow {};
            class orderComposeOnLoad {};
            class orderComposeRefreshMode {};
            class orderComposeRefreshLinkStatus {};
            class orderComposeSubmit {};
            class updateOrderStatus {};
            class orderCanTransition {};
            class orderParseWaypoint {};
            class orderApplyMoveWaypoint {};
            class receiveOrder {};
            class orderConcernsPlayer {};
            class orderTypeLabel {};
            class pollOrders {};
            class pollGpsNavigation {};
            class pollAiOrders {};
            class findAllyTrackUnit {};
            class applyAiMoveOrder {};
            class pollMissionPlan {};
            class pollTacticalAlerts {};
            class pollChatMessages {};
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
            class setBriefingScreens {};
            class loadGoogleBriefing {};
            class applyGoogleBriefingSlide {};
            class googleBriefingStep {};
            class broadcastGoogleBriefingState {};
            class handleGoogleBriefingState {};
            class getPhoneConnectInfo {};
            class phoneConnectShow {};
            class phoneConnectDialogOnLoad {};
            class accountLinkShow {};
            class accountLinkOnLoad {};
            class accountLinkSubmit {};
            class measureLatency {};
            class refreshAccountLinkStatusBar {};
            class showAthenaLinkHelp {};
            class showBetaAccessNote {};
            class openNdaDialog {};
            class decorateMainMenu {};
            class refreshMainMenuBetaBanner {};
            class ndaTexts {};
            class ndaOnLoad {};
            class ndaSetLanguage {};
            class ndaAccept {};
            class ndaDecline {};
            class resetBetaNdaAck {};
            class registerBetaClient {};
            class onMainMenuLoad {};
            class getCallsign {};
            class getBloodType {};
            class setCallsign {};
            
            // ATAK Phase 1 & 2 - Nouvelles fonctions
            class submitTacticalReport {};
            class createPOI {};
            class requestMEDEVAC {};
            class requestQRF {};
            class updateVehicleTracking {};
            class collectVehicleOccupants {};
            class requestVehicleService {};
            class initVehicleTracking {};
            class isObjectFlag {};
            class setGpsBeacon {};
            class setPhoneTrack {};
            class applyPhoneGeolocOrder {};
            class phoneRevealHas {};
            class phoneTrackConfigure {};
            class allyTrackConfigure {};
            class setAllyTrack {};
            class setAtakShowEnemyAi {};
            class shouldSkipEnemyAiTransmit {};
            class vehicleTrackCallsign {};
            class phoneTrackCallsign {};
            class allyTrackCallsign {};
            class reportPhonePosition {};
            class reportAllyPosition {};
            class reportAllySnapshot {};
            class isNearestAtakReporter {};
            class initProxyTrackServer {};
            class reportEnemyPosition {};
            class reportEnemyAiPositions {};
            class moduleAtakShowEnemyAi {};
            class reportCrewedAirAssets {};
            class reportGpsBeacon {};
            class curatorSelectedObjects {};
            class initGpsBeacons {};
            class registerZenTrackActions {};
            class initATAKMenu {};
            class initATAK {};
            class hashMapToJson {};
            class formatTimestamp {};
            class syncCallsignFromAthena {};
            class getTerminalUid {};
            class registerAtakTerminal {};
            class registerAtakCertificate {};
            class syncAtakRealism {};
            class realismErrorMessage {};
            class certStatusLabel {};
            class callsignDialogShow {};
            class callsignDialogOnLoad {};
            class callsignDialogSubmit {};
            class playAtakNotification {};
            class getAtakSoundVolume {};
            class setAtakSoundSetting {};
            class shouldShowScreenNotification {};
            class addScreenToast {};
            class showNotification {};
            class announce {};
            class ambientHint {};
            class pushHtmlAlert {};
            class getUnitRole {};
            class setUnitRole {};
            class extResult {};
            class parseAtakExtResponse {};
            class atakExtFailMessage {};
            class openAthenaFeature {};
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
            class mapContextMenuShow {};
            class mapContextMenuClose {};
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
            class openAtakEnhanced {};
            class resolveBceMarkerText {};
            class syncMapMarker {};
            class isSyncableMapMarker {};
            class forceSyncMapMarkers {};
            class queueMapMarker {};
            class sendLocalTacticalMarker {};
            class resyncAllMapMarkers {};
            class gridPosition {};
            class formatHeading {};
            class trackPacketLoss {};
            class recordPacketSent {};
            class recordPacketReceived {};
            class getPacketLossStats {};
            class handlePositionUpdateCallback {};
            class simulateNetworkDisconnect {};
            class isNetworkDisconnected {};
            class refreshLinkState {};
            class getNetworkDisconnectInfo {};
            class playRoleplaySound {};
            class injectRoleplayEffectsInBrowser {};
            class checkAtakDamage {};
            class attachAtakDamageHandlers {};
            class initCombatJournal {};
            class noteCombatEvent {};
            class combatEventsJson {};
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
            class canTransmit {};
            class applyRoleplayPpEffects {};
            class triggerAtakCrash {};
            class pollRoleplayConfig {};
            class syncRoleplayZonesFromPortal {};
            class initCrashRecovery {};
            class restoreAtakSession {};
            class clearDisconnectedAtakState {};
            class moduleNoCoverage {};
            class moduleInterference {};
            class moduleDegraded {};
            class moduleJammer {};
            class moduleApplyRoleplayZone {};
            class createRoleplayZoneFromZeus {};
            class registerZenRoleplayModules {};
            class applyZeusAtakEffect {};
            class relayZeusAtakEffect {};
            class updateDeviceOverlay {};
            class syncTerminalCompromise {};
            class captureEnemyAtak {};
            class aceDisablePhoneTrack {};
            class syncPlayerAtakPublicVars {};
            class zeusShowPlayerAtak {};
            class registerZenAtakPlayerActions {};
            class zeusAttributesTarget {};
            class zeusAttributesPerson {};
            class zeusAttributesInject {};
            class registerZeusAttributeButtons {};
            class zeusAttributesSse {};
            class zeusAttributesAtak {};
            class zeusAttributesOverwatch {};
        };
    };
};

class CfgRemoteExec {
    class Functions {
        mode = 1;
        jip = 0;
        class comspec_overwatch_connect_fnc_applyZeusAtakEffect { allowedTargets = 0; };
        class comspec_overwatch_connect_fnc_relayZeusAtakEffect { allowedTargets = 2; };
        class comspec_overwatch_connect_fnc_syncPlayerAtakPublicVars { allowedTargets = 0; };
        class comspec_overwatch_connect_fnc_giveSeekTerminal { allowedTargets = 0; };
        class comspec_overwatch_connect_fnc_sseApplyProfile { allowedTargets = 0; };
        class comspec_overwatch_connect_fnc_sseActiveCase { allowedTargets = 0; };
        class comspec_overwatch_connect_fnc_setGpsBeacon { allowedTargets = 0; };
        class comspec_overwatch_connect_fnc_setPhoneTrack { allowedTargets = 0; };
        class comspec_overwatch_connect_fnc_setAllyTrack { allowedTargets = 0; };
        class comspec_overwatch_connect_fnc_setAtakShowEnemyAi { allowedTargets = 0; };
        class comspec_overwatch_connect_fnc_forceSyncData { allowedTargets = 0; };

        // Ces cinq-là étaient appelées via remoteExec sans figurer ici. « mode = 1 »
        // étant une liste blanche stricte, les appels étaient rejetés en silence :
        // les ordres n'arrivaient pas, les zones posées depuis Zeus ne se créaient
        // pas, et la reprise de session après plantage ne se faisait pas.
        // allowedTargets reprend la cible réelle de chaque appel, au plus juste.
        class comspec_overwatch_connect_fnc_receiveOrder { allowedTargets = 0; };
        class comspec_overwatch_connect_fnc_restoreAtakSession { allowedTargets = 0; };
        class comspec_overwatch_connect_fnc_createRoleplayZone { allowedTargets = 2; };
        class comspec_overwatch_connect_fnc_createRoleplayZoneFromZeus { allowedTargets = 2; };
        class comspec_overwatch_connect_fnc_clearDisconnectedAtakState { allowedTargets = 2; };
        class comspec_overwatch_connect_fnc_detonateChargeLocal { allowedTargets = 0; };
        class comspec_overwatch_connect_fnc_applyAiMoveOrder { allowedTargets = 0; };
    };
    class Commands {
        mode = 1;
        jip = 0;
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
        name = "Silencieux — vibration seule";
        sound[] = {"\z\comspec_overwatch\addons\connect\sounds\atak_silencieux_vibration.ogg", 1, 1, 50};
        titles[] = {};
    };
    // Buzz forcé pour « Faire vibrer le terminal » (TOC Athena) — même fichier, volume un peu plus haut
    class COMSPEC_ATAK_Vibrate {
        name = "Vibration terminal";
        sound[] = {"\z\comspec_overwatch\addons\connect\sounds\atak_silencieux_vibration.ogg", 2, 1, 50};
        titles[] = {};
    };
    class COMSPEC_ATAK_Stalker {
        name = "Ambiance tension";
        sound[] = {"\z\comspec_overwatch\addons\connect\sounds\sound_1_stalker.ogg", 1, 1, 50};
        titles[] = {};
    };
    class COMSPEC_ATAK_Health {
        name = "Signal médical";
        sound[] = {"\z\comspec_overwatch\addons\connect\sounds\atak_no_activyt_health.ogg", 1, 1, 50};
        titles[] = {};
    };
    class COMSPEC_ATAK_Start {
        name = "Démarrage ATAK";
        sound[] = {"\z\comspec_overwatch\addons\connect\sounds\atak_start.ogg", 1, 1, 50};
        titles[] = {};
    };
    class COMSPEC_ATAK_Disconnect {
        name = "Déconnexion ATAK";
        sound[] = {"\z\comspec_overwatch\addons\connect\sounds\atak_disconnect.ogg", 1, 1, 50};
        titles[] = {};
    };
    class COMSPEC_ATAK_Unconscious {
        name = "Alerte inconscient";
        sound[] = {"\z\comspec_overwatch\addons\connect\sounds\atak_alert_2.ogg", 1, 1, 50};
        titles[] = {};
    };
    class COMSPEC_ATAK_Death {
        name = "Alerte mort";
        sound[] = {"\z\comspec_overwatch\addons\connect\sounds\atak_death.ogg", 1, 1, 50};
        titles[] = {};
    };
    class COMSPEC_ATAK_Order {
        name = "Ordre reçu";
        sound[] = {"\z\comspec_overwatch\addons\connect\sounds\atak_order_receive.ogg", 1, 1, 50};
        titles[] = {};
    };
    class COMSPEC_ATAK_OrderPrio {
        name = "Ordre prioritaire";
        sound[] = {"\z\comspec_overwatch\addons\connect\sounds\atak_order_receive.ogg", 1, 1, 50};
        titles[] = {};
    };
    class COMSPEC_ATAK_OrderAck {
        name = "Ordre accepté";
        sound[] = {"\z\comspec_overwatch\addons\connect\sounds\atak_deep_chime.ogg", 1, 1, 50};
        titles[] = {};
    };
    class COMSPEC_ATAK_Intel {
        name = "Renseignement transmis";
        sound[] = {"\z\comspec_overwatch\addons\connect\sounds\atak_deep_chime.ogg", 1, 1, 50};
        titles[] = {};
    };
    class COMSPEC_ATAK_Beep {
        name = "Bip ATAK";
        sound[] = {"\z\comspec_overwatch\addons\connect\sounds\atak_beep.ogg", 1, 1, 50};
        titles[] = {};
    };
};

// Terminal SEEK transportable (sac / gilet / uniforme).
// Modèle et icône vanilla : aucun asset propriétaire à livrer, l’objet reste
// utilisable immédiatement en mission. « type = 620 » = objet divers d’inventaire.
class CfgWeapons
{
    class ItemCore;
    class InventoryItem_Base_F;

    class COMSPEC_Item_SeekTerminal: ItemCore
    {
        scope = 2;
        author = "COMSPEC";
        displayName = "Terminal biométrique SEEK";
        descriptionShort = "Terminal d’enrôlement biométrique de terrain. Permet d’ouvrir une fiche de renseignement interpersonnel (SSE) sur une personne contrôlée.";
        picture = "\A3\ui_f\data\igui\cfg\simpletasks\types\intel_ca.paa";
        model = "\A3\weapons_F\ammo\mag_univ.p3d";

        class ItemInfo: InventoryItem_Base_F
        {
            mass = 45;
            type = 620;
        };
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
    class COMSPEC_SSE : NO_CATEGORY
    {
        displayName = "COMSPEC SSE";
        priority = 2;
        side = 7;
    };
    class COMSPEC_Outils : NO_CATEGORY
    {
        displayName = "COMSPEC Outils";
        priority = 2;
        side = 7;
    };
};

#include "ui_base.hpp"
#include "display.hpp"
#include "display_hub.hpp"
#include "display_cas.hpp"
#include "display_cas_request.hpp"
#include "display_flight_manifest.hpp"
#include "display_briefing.hpp"
#include "display_phone_connect.hpp"
#include "display_account_link.hpp"
#include "display_nda.hpp"
#include "display_callsign.hpp"
#include "display_salute.hpp"
#include "display_sse_person.hpp"
#include "display_intel_note.hpp"
#include "display_medevac.hpp"
#include "display_bug_report.hpp"
#include "display_order_inbox.hpp"
#include "display_order_compose.hpp"
#include "display_medical_inbox.hpp"
#include "display_device.hpp"
#include "display_webbrowser.hpp"
#include "display_pause_manager.hpp"
#include "display_theater_survey.hpp"

// Bouton menu Échap : injecté en SQF (DisplayLoad), pas via héritage RscDisplayInterrupt
// — l’héritage config casse le démarrage Arma (Undefined base / Member already defined).

// Modules Zeus/Eden — un seul CfgVehicles (deux class CfgVehicles = Member already defined)
class CfgVehicles
{
    class Module_F;

    // Zones roleplay (sans couverture, brouillage, etc.)
    #include "modules\module_roleplay_zone.hpp"

    // Exploitation SSE
    #include "modules\module_sse.hpp"

    // Relevé de carte (Eden)
    #include "modules\module_theater_survey.hpp"

    // Contacts ennemis sur le poste ATAK (Eden)
    #include "modules\module_atak_show_enemy_ai.hpp"
};

// Attributs Eden SSE + EH
#include "modules\eden_sse_attributes.hpp"
#include "CfgEventHandlers.hpp"
