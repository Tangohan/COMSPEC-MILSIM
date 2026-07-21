class CfgPatches {
    class comspec_overwatch_connect {
        name = "COMSPEC Overwatch Connect";
        units[] = {};
        weapons[] = {};
        requiredVersion = 1.0;
        requiredAddons[] = {"comspec_overwatch_main", "cba_main", "cba_xeh", "cba_settings"};
        author = "COMSPEC";
        version = 1.10;
        versionStr = "1.1.0";
        versionAr[] = {1, 1, 0};
    };
};

// Format obligatoire Tag > Category > Function (file = dossier des fn_*.sqf).
class CfgFunctions {
    class comspec_overwatch_connect {
        tag = "comspec_overwatch_connect";
        class connect {
            file = "z\comspec_overwatch\addons\connect\functions";
            class connect {};
            class playtimeTracker {};
            class updatePosition {};
            class sendIntel {};
            class initACE {};
            class submitChat {};
            class openHub {};
            class hubSelect {};
            class getRadioState {};
            class getMedicalState {};
            class checkMedicalAlerts {};
            class reportMedicalAlert {};
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
            class formatCommsMessage {};
            class getBriefingSlides {};
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
            class extResult {};
            class getModVersion {};
            class refreshLinkStatus {};
            class updateStatusBadges {};
            class chatDialogOnLoad {};
            class appendLinkLog {};
            class portalLabel {};
            class toggleLogCategory {};
            class showDebugInfo {};
            class getPlayerAvatarInfo {};
            class showPlayerProfile {};
        };
    };
};

// Templates réutilisables : 1er arg de BIS_fnc_showNotification = nom de classe, pas le texte FR.
// Ex. : ["COMSPEC_Info", ["Message lisible"]] call BIS_fnc_showNotification;
class CfgNotifications {
    class COMSPEC_Info {
        title = "COMSPEC";
        iconPicture = "\A3\ui_f\data\map\markers\military\dot_CA.paa";
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

#include "ui_base.hpp"
#include "display.hpp"
#include "display_hub.hpp"
#include "display_cas.hpp"
#include "display_flight_manifest.hpp"
#include "display_briefing.hpp"
#include "display_phone_connect.hpp"
#include "display_account_link.hpp"
#include "CfgEventHandlers.hpp"
