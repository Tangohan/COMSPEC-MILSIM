/*
    True si le marqueur carte doit être miroité vers Athena.
    Couvre :
      - marqueurs joueur Arma `_USER_DEFINED #…`
      - Marker Widget / Dropper BCE `_USER_DEFINED` / `_IcTab_DEFINED #…`
      - préfixe CBA `BCE_cTab_Marker_Sync`
    Les autres marqueurs système `_…` restent exclus.
*/
params [["_markerName", "", [""]]];
if (_markerName isEqualTo "") exitWith { false };

private _ul = toLower _markerName;

if ((_ul find "_user_defined") >= 0) exitWith { true };
if ((_ul find "user_defined") >= 0) exitWith { true };
// BCE PlaceMarker / TAD Dropper : `{Sync}_DEFINED #owner/id/…`
if ((_ul find "_defined #") >= 0) exitWith { true };
if ((_ul find "_ictab_defined") >= 0) exitWith { true };
if ((_ul find "ictab_defined") >= 0) exitWith { true };

if (!isNil "BCE_cTab_Marker_Sync") then {
    private _sync = BCE_cTab_Marker_Sync;
    if (_sync isEqualType "" && {_sync isNotEqualTo ""} && {(_markerName find _sync) == 0}) exitWith { true };
};

// Marqueurs sans underscore initial : toujours candidats (filtrés ailleurs)
if ((_markerName select [0, 1]) isNotEqualTo "_") exitWith { true };

false
