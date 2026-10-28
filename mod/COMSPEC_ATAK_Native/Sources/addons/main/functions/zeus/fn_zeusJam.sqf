/*
    Brouillage subi par le joueur local hors brouilleurs ATAK (COMSPEC_ATAK_Jammers) : 0-1.
      - brouillage Zeus ciblé sur le joueur (fn_zeusFx "jam", 1-100) ;
      - brouilleurs du mod Crows Electronic Warfare (missionNamespace crowsEW_jamming_jamMap, HashMap netId ->
        [brouilleur, rayon de décroissance, rayon efficace, actif, capacités]) : plein effet dans le rayon efficace,
        décroissance linéaire jusqu'au rayon de décroissance. Lecture prudente : format déduit du mod, non garanti ;
      - scripts de brouillage TFAR (« TFAR Radio Jammer » et semblables) : ils réduisent la portée d'émission radio du
        joueur (variable tf_sendingDistanceMultiplicator sous 1) ; le téléphone subit le même brouillage (1 - multiplicateur).
        Réglage comspec_atak_native_jam_tfar.
    Recalculé au plus une fois par seconde (uiNamespace COMSPEC_ATAK_ZeusJamC).
*/
private _c = uiNamespace getVariable ["COMSPEC_ATAK_ZeusJamC", []];
if ((count _c) isEqualTo 2 && {diag_tickTime - (_c select 0) < 1}) exitWith { _c select 1 };
private _k = ((["jam", 0] call comspec_atak_native_fnc_zeusFx) max 0 min 100) / 100;
private _crowsOn = missionNamespace getVariable ["comspec_atak_native_zeus_crows", true];
private _map = missionNamespace getVariable "crowsEW_jamming_jamMap";
if (_crowsOn && {!isNil "_map"} && {_map isEqualType createHashMap}) then {
    {
        if !(_y isEqualType []) then { continue; };
        private _obj = objNull; private _nums = []; private _on = true;
        {
            if (_x isEqualType objNull) then { if (isNull _obj) then { _obj = _x; }; };
            if (_x isEqualType 0) then { _nums pushBack _x; };
            if (_x isEqualType true) then { _on = _x; };
        } forEach _y;
        if (isNull _obj || {!_on} || {!alive _obj} || {(count _nums) < 1}) then { continue; };
        _nums params [["_fall", 0], ["_eff", 0]];
        private _outer = _fall max _eff;
        private _inner = _fall min _eff;
        private _d = player distance _obj;
        if (_d >= _outer) then { continue; };
        private _s = if (_d <= _inner || {_outer <= _inner}) then { 1 } else { 1 - (_d - _inner) / (_outer - _inner) };
        _k = _k max _s;
    } forEach _map;
};
if (missionNamespace getVariable ["comspec_atak_native_jam_tfar", true]) then {
    private _tx = player getVariable ["tf_sendingDistanceMultiplicator", 1];
    if (_tx isEqualType 0 && {_tx < 1}) then { _k = _k max (((1 - _tx) max 0) min 1); };
};
uiNamespace setVariable ["COMSPEC_ATAK_ZeusJamC", [diag_tickTime, _k]];
_k
