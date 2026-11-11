/*
    Nom d'une personne ou d'un élément écrit sur les écrans TOC et dans le tableau des écrans : un seul endroit.
    Retour de TTRD : « les écrans TOC, on voit les pseudos Steam dans GROUPE et non les identifiants ATHENA ».
    Ordre : résolveur commun du TOC s'il est chargé (comspec_atak_native_fnc_tocDisplayName : identifiant Athena, puis
    désignation du groupe ou de l'ORBAT, puis rôle, le pseudo en dernier), sinon l'indicatif tactique de l'unité
    (fn_unitCallsign, jamais vide refusé), sinon le nom de son groupe ; le pseudo du joueur seulement s'il ne reste rien.
    Modes :
      ["unit", unité]            -> nom à écrire pour cette unité
      ["node", nœud du flux]     -> nom d'un nœud de COMSPEC_ATAK_TocFeed_<camp> (son indicatif, sinon son groupe)
*/
params [["_mode", "unit"], ["_a", objNull]];
switch (_mode) do {
    case "node": {
        if !(_a isEqualType []) exitWith { "" };
        private _u = objectFromNetId (_a param [0, ""]);
        if (!isNull _u && {!isNil { missionNamespace getVariable "comspec_atak_native_fnc_tocDisplayName" }}) exitWith { ["unit", _u] call comspec_atak_native_fnc_tocWho };
        private _cs = _a param [1, ""];
        [_cs, _a param [7, ""]] select (_cs isEqualTo "")
    };
    default {
        private _u = _a;
        if !(_u isEqualType objNull) exitWith { "" };
        if (isNull _u) exitWith { "" };
        private _out = "";
        // Lu par son nom : la fonction arrive avec une autre branche, elle peut ne pas exister ici.
        private _resolver = missionNamespace getVariable "comspec_atak_native_fnc_tocDisplayName";
        if (!isNil "_resolver") then {
            private _r = [_u] call _resolver;
            if (!isNil "_r" && {_r isEqualType ""}) then { _out = trim _r; };
        };
        if (_out isEqualTo "") then { _out = [_u, true] call comspec_atak_native_fnc_unitCallsign; };
        if (isNil "_out" || {!(_out isEqualType "")}) then { _out = ""; };
        if (_out isEqualTo "") then { _out = groupId group _u; };
        if (_out isEqualTo "" && {isPlayer _u}) then { _out = name _u; };
        _out
    };
};
