// 4.2.1 : _step 0 à 3 = un seul sondage (unités, marqueurs, ordres, messages), un par passage de fn_schedulerTick ;
// -1 (défaut) = les quatre d'un coup, comme avant (retour du réseau, fn_offline). Gardes inchangées, relues à chaque étape.
params [["_step", -1]];
// Mode avion : pas de synchro (le téléphone reste sur ses dernières données).
if (missionNamespace getVariable ["COMSPEC_ATAK_Airplane", false]) exitWith {};
// Sans signal (fn_offline) : idem, la synchro repart au retour du réseau.
if !(["online"] call comspec_atak_native_fnc_offline) exitWith {};
// Service Athéna refusé : ATAK local, aucune requête vers Athena.
if (([] call comspec_atak_native_fnc_athenaMode) isNotEqualTo "on") exitWith {};
private _nativeReady = true;
if !([] call comspec_atak_native_fnc_bridge) then {
    // Étapes 1 à 3 du même tour : l'état lu par l'étape 0 (moins de 1,5 s) suffit ; l'étape 0 et l'appel complet relisent la DLL.
    private _nativeAuth = ["GetAuthState", [], [-1, 1.5] select (_step > 0)] call comspec_atak_native_fnc_extensionCall;
    _nativeReady = (toUpper _nativeAuth find "READY") >= 0;
};
if (!_nativeReady) exitWith {};

private _state = uiNamespace getVariable ["COMSPEC_ATAK_State",createHashMap];
_state set ["networkState","CONNECTED"];

private _until = missionNamespace getVariable ["COMSPEC_ATAK_NativeBackoffUntil",0];
if (diag_tickTime < _until) exitWith {};

private _changed = false;
if (_step in [-1, 0]) then { _changed = ([] call comspec_atak_native_fnc_pollUnits) || _changed; };
if (_step in [-1, 1]) then { _changed = ([] call comspec_atak_native_fnc_pollMarkers) || _changed; };
if (_step in [-1, 2]) then { _changed = ([] call comspec_atak_native_fnc_pollOrders) || _changed; };
if (_step in [-1, 3]) then { _changed = ([] call comspec_atak_native_fnc_pollChat) || _changed; };
if (_changed) then {
    private _data = uiNamespace getVariable ["COMSPEC_ATAK_Data",createHashMap];
    _data set ["lastNetworkUpdate",diag_tickTime];
};
