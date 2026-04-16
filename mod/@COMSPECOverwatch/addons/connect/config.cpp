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
    };
};

#include "ui_base.hpp"
#include "display.hpp"
#include "display_cas.hpp"
#include "display_flight_manifest.hpp"
#include "CfgEventHandlers.hpp"
