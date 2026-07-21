class CfgPatches {
    class comspec_overwatch_connect {
        name = "COMSPEC Overwatch Connect";
        units[] = {};
        weapons[] = {};
        requiredVersion = 1.0;
        requiredAddons[] = {"comspec_overwatch_main", "cba_main", "cba_xeh", "cba_settings"};
        author = "COMSPEC";
        version = "1.0";
    };
};

class CfgFunctions {
    class comspec_overwatch_connect {
        tag = "comspec_overwatch_connect";
        class connect { file = "functions\fn_connect.sqf"; };
        class playtimeTracker { file = "functions\fn_playtimeTracker.sqf"; };
        class updatePosition { file = "functions\fn_updatePosition.sqf"; };
        class sendIntel { file = "functions\fn_sendIntel.sqf"; };
        class initACE { file = "functions\fn_initACE.sqf"; };
        class submitChat { file = "functions\fn_submitChat.sqf"; };
        class getRadioState { file = "functions\fn_getRadioState.sqf"; };
        class getMedicalState { file = "functions\fn_getMedicalState.sqf"; };
        class getAircraftType { file = "functions\fn_getAircraftType.sqf"; };
        class fillFlightManifest { file = "functions\fn_fillFlightManifest.sqf"; };
        class submitFlightManifest { file = "functions\fn_submitFlightManifest.sqf"; };
        class requestFireSolution { file = "functions\fn_requestFireSolution.sqf"; };
        class receiveFireSolution { file = "functions\fn_receiveFireSolution.sqf"; };
        class displayFireSolution { file = "functions\fn_displayFireSolution.sqf"; };
        class pilotResponse { file = "functions\fn_pilotResponse.sqf"; };
        class openCASDialog { file = "functions\fn_openCASDialog.sqf"; };
        class receiveCASRequest { file = "functions\fn_receiveCASRequest.sqf"; };
        class updateCASState { file = "functions\fn_updateCASState.sqf"; };
        class checkCASLine { file = "functions\fn_checkCASLine.sqf"; };
        class sendCASAck { file = "functions\fn_sendCASAck.sqf"; };
        class sendCASStatus { file = "functions\fn_sendCASStatus.sqf"; };
        class receiveMapShape { file = "functions\fn_receiveMapShape.sqf"; };
        class deleteMapShape { file = "functions\fn_deleteMapShape.sqf"; };
        class pollMapShapes { file = "functions\fn_pollMapShapes.sqf"; };
        class captureReconImage { file = "functions\fn_captureReconImage.sqf"; };
        class syncLaserCode { file = "functions\fn_syncLaserCode.sqf"; };
        class receiveDangerZone { file = "functions\fn_receiveDangerZone.sqf"; };
        class updateDangerZone { file = "functions\fn_updateDangerZone.sqf"; };
        class deleteDangerZone { file = "functions\fn_deleteDangerZone.sqf"; };
        class checkPlayerInDangerZone { file = "functions\fn_checkPlayerInDangerZone.sqf"; };
        class warnDangerZoneEntry { file = "functions\fn_warnDangerZoneEntry.sqf"; };
        class sendLogisticsStatus { file = "functions\fn_sendLogisticsStatus.sqf"; };
        class receiveIFFChallenge { file = "functions\fn_receiveIFFChallenge.sqf"; };
        class submitIFFResponse { file = "functions\fn_submitIFFResponse.sqf"; };
        class updateIFFMarkerState { file = "functions\fn_updateIFFMarkerState.sqf"; };

        // C2 + Event Bus + Comms structurées
        class registerEventHandler { file = "functions\fn_registerEventHandler.sqf"; };
        class publishEvent { file = "functions\fn_publishEvent.sqf"; };
        class issueOrder { file = "functions\fn_issueOrder.sqf"; };
        class updateOrderStatus { file = "functions\fn_updateOrderStatus.sqf"; };
        class formatCommsMessage { file = "functions\fn_formatCommsMessage.sqf"; };

        // Tableau de briefing tactique (diapositives gérées depuis le back-office)
        class getBriefingSlides { file = "functions\fn_getBriefingSlides.sqf"; };
        class downloadBriefingSlide { file = "functions\fn_downloadBriefingSlide.sqf"; };
        class openBriefingBoard { file = "functions\fn_openBriefingBoard.sqf"; };
        class briefingBoardShow { file = "functions\fn_briefingBoardShow.sqf"; };
        class briefingBoardStep { file = "functions\fn_briefingBoardStep.sqf"; };
        class refreshBriefingSlides { file = "functions\fn_refreshBriefingSlides.sqf"; };

        // Connexion téléphone (inspiré de cTab) : QR code + code court pour consulter le
        // briefing en cours depuis un navigateur mobile.
        class getPhoneConnectInfo { file = "functions\fn_getPhoneConnectInfo.sqf"; };
        class phoneConnectShow { file = "functions\fn_phoneConnectShow.sqf"; };
    };
};

#include "ui_base.hpp"
#include "display.hpp"
#include "display_cas.hpp"
#include "display_flight_manifest.hpp"
#include "display_briefing.hpp"
#include "display_phone_connect.hpp"
#include "CfgEventHandlers.hpp"
