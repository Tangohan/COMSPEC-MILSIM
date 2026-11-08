/*
    Fonction de signal donnée à ACRE2 (acre_api_fnc_setCustomSignalFunc), installée seulement pendant un pont radio. Dans le chemin voix d'ACRE2, environ 5 fois par seconde et par couple.
    Isolation : d'abord le résultat d'origine (fonction déjà en place ou calcul d'ACRE2), puis une seule lecture de table remplie par fn_mpuVoice "tick". Aucun calcul de maillage,
    aucun appel réseau, aucune fonction interne d'ACRE2 autre que son calcul de signal. Tout écart, coupe-circuit ou coupure : le résultat d'origine, tel quel. Le réseau ne peut que l'améliorer.
    Les compteurs COMSPEC_ATAK_GuardIn / GuardOut révèlent une sortie perdue (erreur de script) à fn_radioGuard "watch", hors de ce chemin.
    Params (ACRE2) : [fréquence, puissance, ID radio réceptrice, ID radio émettrice] -> [Px, dBm]
*/
// Une autre fonction de signal qui nous a enchaînés et que nous enchaînons à notre tour : pas de boucle, calcul d'ACRE2.
if (!isNil "_comspecSigGuard") exitWith { _this call (missionNamespace getVariable "acre_sys_signal_fnc_getSignalCore") };
private _comspecSigGuard = true;
private _r = _this call (missionNamespace getVariable ["COMSPEC_ATAK_AcreSigPrev", missionNamespace getVariable "acre_sys_signal_fnc_getSignalCore"]);
if (isNil "_r" || {!(_r isEqualType [])} || {(count _r) < 2}) exitWith { [0, -992] };
if !(missionNamespace getVariable ["COMSPEC_ATAK_VoiceLive", false]) exitWith { _r };
missionNamespace setVariable ["COMSPEC_ATAK_GuardIn", (missionNamespace getVariable ["COMSPEC_ATAK_GuardIn", 0]) + 1];
private _out = _r;
private _e = (missionNamespace getVariable ["COMSPEC_ATAK_VoicePairMap", []]) param [0, []];
_this params ["", "", ["_rx", ""], ["_tx", ""]];
if (_e isEqualType createHashMap && {_rx isEqualTo (missionNamespace getVariable ["COMSPEC_ATAK_VoiceRx", ""])}) then {
    private _p = _e getOrDefault [_tx, []];
    if (_p isNotEqualTo []) then {
        private _m = ["sigMap", [_r select 0, _r select 1, _p select 0, _p select 1, (missionNamespace getVariable ["comspec_atak_native_mesh_gravity", true]) isEqualTo true]] call comspec_atak_native_fnc_mpuVoice;
        if (!isNil "_m" && {_m isEqualTypeArray [0, 0]} && {finite (_m select 0)} && {finite (_m select 1)}) then { _out = _m; } else {
            missionNamespace setVariable ["COMSPEC_ATAK_GuardBad", (missionNamespace getVariable ["COMSPEC_ATAK_GuardBad", 0]) + 1];
        };
    };
};
missionNamespace setVariable ["COMSPEC_ATAK_GuardOut", (missionNamespace getVariable ["COMSPEC_ATAK_GuardOut", 0]) + 1];
_out
