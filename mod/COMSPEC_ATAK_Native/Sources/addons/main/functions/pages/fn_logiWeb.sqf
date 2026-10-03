/*
    Lien web des demandes logistiques (mode passerelle Overwatch, DLL COMSPECExtension) :
      "new"    [paires de la demande] : RequestResupply (repli : rapport tactique si la DLL est ancienne)
      "status" [id, état, auteur]      : UpdateResupplyStatus (changement fait en jeu)
      "poll"                           : GetResupplyStatus, applique et diffuse au camp les décisions du poste web
*/
params [["_act", ""], ["_arg", []]];
if !([] call comspec_atak_native_fnc_bridge) exitWith { false };
if (isNil "comspec_overwatch_connect_fnc_hashMapToJson") exitWith { false };
private _call = {
    params ["_cmd", "_json"];
    private _r = "COMSPECExtension" callExtension [_cmd, [_json]];
    if (_r isEqualType []) then { _r = _r param [0, ""]; };
    _r
};
switch (_act) do {
    case "new": {
        private _r = createHashMapFromArray _arg;
        private _need = createHashMapFromArray [["AMMO", "munitions"], ["MED", "santé"], ["FOOD", "vivres"], ["BATT", "batteries"], ["VEH", "véhicule carburant"]] getOrDefault [_r get "cat", "ravitaillement"];
        private _mode = createHashMapFromArray [["PICKUP", "ramassage"], ["AIRDROP", "largage"], ["VEHICLE", "livraison véhicule"]] getOrDefault [_r get "mode", "ramassage"];
        private _json = [createHashMapFromArray [
            ["game_id", _r get "id"], ["call_sign", _r get "cs"], ["need", _need], ["qty", [_r get "qty", parseNumber (_r get "qty")] select ((_r get "qty") isEqualType "")],
            ["priority", _r get "prio"], ["mode", _r get "mode"], ["grid_ref", _r get "grid"],
            ["note", format ["%1 x%2 · %3%4", _r get "label", _r get "qty", _mode, ["", " · " + (_r get "note")] select ((_r get "note") isNotEqualTo "")]]
        ]] call comspec_overwatch_connect_fnc_hashMapToJson;
        [_r, _json, _call] spawn {
            params ["_r", "_json", "_call"];
            private _res = ["RequestResupply", _json] call _call;
            if ((toUpper _res) find "OK" >= 0 && {(toUpper _res) find "ERR" < 0}) exitWith {};
            // DLL sans RequestResupply : rapport tactique comme avant.
            if (isNil "comspec_overwatch_connect_fnc_submitTacticalReport") exitWith {};
            private _prio = createHashMapFromArray [["URGENT", "IMMEDIATE"], ["PRIORITY", "PRIORITY"], ["ROUTINE", "ROUTINE"]] getOrDefault [_r get "prio", "ROUTINE"];
            private _sd = createHashMapFromArray [["kind", "resupply_request"], ["request_id", _r get "id"], ["category", _r get "cat"], ["quantity", _r get "qty"], ["delivery", _r get "mode"], ["grid_ref", _r get "grid"]];
            ["OTHER", _prio, format ["LOGREQ %1 · %2 x%3", _r get "id", _r get "label", _r get "qty"], format ["Demande de ravitaillement %1 : %2 x%3 en %4. %5", _r get "cs", _r get "label", _r get "qty", _r get "grid", _r get "note"], _sd, _r get "pos"] call comspec_overwatch_connect_fnc_submitTacticalReport;
        };
    };
    case "status": {
        _arg params ["_id", "_st", "_by"];
        private _json = [createHashMapFromArray [["game_id", _id], ["status", _st], ["by", _by], ["source", "game"]]] call comspec_overwatch_connect_fnc_hashMapToJson;
        [_json, _call] spawn { params ["_json", "_call"]; ["UpdateResupplyStatus", _json] call _call; };
    };
    case "poll": {
        private _reqs = missionNamespace getVariable ["COMSPEC_ATAK_LogiReqs", createHashMap];
        if (((values _reqs) findIf { (_x getOrDefault ["status", ""]) in ["DEMANDEE", "VALIDEE", "EN_ROUTE"] }) < 0) exitWith {};
        private _res = "COMSPECExtension" callExtension ["GetResupplyStatus", ["1"]];
        if (_res isEqualType []) then { _res = _res param [0, ""]; };
        if ((_res select [0, 3]) isNotEqualTo "OK|") exitWith {};
        {
            (_x splitString ",") params [["_id", ""], ["_st", ""], ["_by", "Poste"]];
            private _r = _reqs getOrDefault [_id, createHashMap];
            // Décision du poste web encore inconnue en jeu : diffusée au camp (sans renvoi au web).
            if ((count _r) > 0 && {(_r getOrDefault ["status", ""]) isNotEqualTo _st} && {!((_r getOrDefault ["status", ""]) in ["LIVREE", "ANNULEE", "REFUSEE"])}) then {
                _r set ["status", _st];
                ["comspec_atak_native_logi", ["STATUS", _r get "side", [_id, _st, format ["%1 (web)", _by], []]]] call CBA_fnc_globalEvent;
            };
        } forEach ((_res select [3]) splitString ";");
    };
};
true
