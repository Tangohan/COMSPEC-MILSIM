/*
    Ordre drone venu du poste web, exécuté sur le client du pilote (fn_webCmdDispatch).
    Params : [id, cmd, netId du drone, args (HashMap de chaînes)]
    Le drone doit être celui appairé au téléphone natif de ce joueur (COMSPEC_DroneOwner = UID du joueur) :
    le poste ne pilote pas, il donne les mêmes ordres que les boutons du téléphone, par le même chemin
    (comspec_atak_native_fnc_droneAction ["webCmd", [ordre, arguments]]), donc à portée de liaison seulement.
      hover land home rth takeoff resume force | standby (k=LAND|HOVER) | follow_me | follow_unit (cs=indicatif)
      goto (x, y) | loiter / observe (x, y, r) | hunt (x, y, r) | alt (v=m) | speed (v=km/h) | photo
    Photo : voir fn_webCmdUavPhoto. Compte rendu au poste par fn_webCmdAck.
    Retour : true (la commande visait ce joueur).
*/
params [["_id", ""], ["_cmd", ""], ["_ref", ""], ["_a", createHashMap]];
private _fail = { params ["_m"]; [_id, "failed", _m] call comspec_overwatch_connect_fnc_webCmdAck; true };

if (isNil "comspec_atak_native_fnc_droneAction") exitWith { ["Téléphone COMSPEC ATAK absent chez le pilote"] call _fail };
private _d = objectFromNetId _ref;
if (isNull _d || {!alive _d}) exitWith { ["Drone introuvable ou détruit"] call _fail };
if ((_d getVariable ["COMSPEC_DroneOwner", ""]) isNotEqualTo (getPlayerUID player)) exitWith { ["Ce drone n'est plus appairé au téléphone du pilote"] call _fail };
if ((missionNamespace getVariable ["COMSPEC_ATAK_Drone", objNull]) isNotEqualTo _d) exitWith { ["Ce drone n'est pas le drone actif du téléphone"] call _fail };
if (!alive player) exitWith { ["Pilote hors de combat"] call _fail };

// Liaison radio téléphone ↔ drone : [barres, distance, en liaison] (relief, batterie et téléphone compris).
private _ln = ["link"] call comspec_atak_native_fnc_droneAction;
if (!(_ln isEqualType []) || {!(_ln param [2, false])}) exitWith {
    [format ["Drone hors liaison du téléphone du pilote (%1 m)", _ln param [1, "?"]]] call _fail
};

private _num = { params ["_k", "_def"]; private _v = _a getOrDefault [_k, ""]; if (_v isEqualTo "") then { _def } else { parseNumber _v } };
private _pos = { [["x", -1] call _num, ["y", -1] call _num] };
private _say = { params ["_t"]; ["INFO", _t, 4, 30] call comspec_atak_native_fnc_notify; };

if (_cmd isEqualTo "photo") exitWith {
    [_id, _d] call comspec_overwatch_connect_fnc_webCmdUavPhoto;
    true
};

// Ordre web → [ordre webCmd, arguments] (voir l'en-tête de fn_droneAction).
private _order = switch (_cmd) do {
    case "hover"; case "land"; case "home"; case "rth"; case "takeoff"; case "resume"; case "force": { [_cmd, []] };
    case "standby": { ["standby", [_a getOrDefault ["k", ""]]] };
    case "follow_me": { ["me", []] };
    case "follow_unit": {
        // Indicatif public (COMSPEC_CallsignPublic) ou nom du joueur, même camp ; comparé sans accents ni ponctuation comme côté serveur.
        private _norm = { toLower ((_this select 0) regexReplace ["[^A-Za-z0-9 .:,_@-]", ""]) };
        private _cs = [_a getOrDefault ["cs", ""]] call _norm;
        private _t = objNull;
        {
            if (alive _x && {side group _x isEqualTo side group player}
                && {([_x getVariable ["COMSPEC_CallsignPublic", ""]] call _norm) isEqualTo _cs || {([name _x] call _norm) isEqualTo _cs}}) exitWith { _t = _x; };
        } forEach allPlayers;
        if (_cs isEqualTo "" || {isNull _t}) then { [] } else { ["follow", [getPlayerUID _t]] }
    };
    case "goto": { ["goto", [call _pos]] };
    case "loiter"; case "observe": { [_cmd, [call _pos, ["r", 150] call _num]] };
    case "hunt": { ["hunt", [["r", 250] call _num, call _pos]] };
    case "alt"; case "speed": { [_cmd, [["v", 40] call _num]] };
    default { [] };
};
if (_order isEqualTo []) exitWith { [["Ordre inconnu du téléphone", "Unité à suivre introuvable en jeu"] select (_cmd isEqualTo "follow_unit")] call _fail };
if (_cmd in ["goto", "loiter", "observe", "hunt"] && {(((call _pos) select 0) < 0) || {((call _pos) select 1) < 0}}) exitWith { ["Point invalide"] call _fail };

private _ok = ["webCmd", _order] call comspec_atak_native_fnc_droneAction;
if (!(_ok isEqualType true)) then { _ok = true; };
if (!_ok) exitWith { ["Ordre refusé par le téléphone (drone hors liaison ou cible absente)"] call _fail };

private _labels = createHashMapFromArray [
    ["hover", "stationnaire"], ["land", "atterrissage"], ["home", "retour au pilote"], ["rth", "retour au point de décollage"],
    ["takeoff", "décollage"], ["resume", "reprise"], ["force", "exécution forcée"], ["standby", "veille"], ["follow_me", "suivi du pilote"],
    ["follow_unit", "suivi d'unité"], ["goto", "aller au point"], ["loiter", "orbite"], ["observe", "observation"], ["hunt", "recherche"],
    ["alt", "altitude"], ["speed", "vitesse"]
];
private _what = _labels getOrDefault [_cmd, _cmd];
if (_cmd in ["alt", "speed"]) then { _what = format ["%1 %2 %3", _what, ["v", 0] call _num, ["m", "km/h"] select (_cmd isEqualTo "speed")]; };
[format ["Poste de commandement : drone, %1", _what]] call _say;
[_id, "done", format ["Ordre exécuté : %1", _what], createHashMapFromArray [["mode", _d getVariable ["COMSPEC_DroneMode", ""]]]] call comspec_overwatch_connect_fnc_webCmdAck;
true
