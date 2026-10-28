/*
    Pylône relais 3D (COMSPEC_ATAK_RelayTower_30 / _40 / _50, tools/model_relay_towers.py) : relais du réseau ATAK dès
    sa pose (Eden, Zeus, script). Serveur seulement.
    Valeurs de la classe (comspec_atak_relayRange, Height, Throughput, Slots) ; attributs Eden (nom, camp, liaison,
    bande de fréquence, allumé, indestructible) posés sur l'objet sous COMSPEC_TowerName, Side, Uplink, Freq, On, Indestructible.
    Un module « ATAK · Relais » synchronisé passe avant (il remplace ces valeurs).
    Détruit (ruine, fn_relayTowerRuin) : fn_relayNet le voit « détruit », les téléphones qui l'utilisaient reçoivent « Relais perdu ».
      [objet] call comspec_atak_native_fnc_relayTower      enregistrement (image suivante, après les attributs Eden)
      ["sweep"] call ...                                   pylônes de la mission pas encore enregistrés (fin d'init serveur)
*/
params [["_o", objNull, [objNull, ""]]];
if (!isServer) exitWith {};
if (_o isEqualType "") exitWith {
    { [_x] call comspec_atak_native_fnc_relayTower; } forEach (allMissionObjects "COMSPEC_ATAK_RelayTower_Base");
};
if (isNull _o) exitWith {};
// Destruction : la ruine (pylône plié, équipements arrachés) reste au sol (fn_relayTowerRuin), aussi pour les tours
// créées par le module « ATAK · Relais » (déjà enregistrées comme relais par lui).
if !(_o getVariable ["COMSPEC_TowerKilledEh", false]) then {
    _o setVariable ["COMSPEC_TowerKilledEh", true];
    _o addEventHandler ["Killed", { [_this select 0] call comspec_atak_native_fnc_relayTowerRuin; }];
};
[{
    params ["_o"];
    if (isNull _o || {_o getVariable ["COMSPEC_AtakRelay", false]}) exitWith {};
    private _cfg = configOf _o;
    private _p = getPosATL _o;
    private _n = _o getVariable ["COMSPEC_TowerName", ""];
    if (_n isEqualTo "") then { _n = format ["Pylône %1", mapGridPosition _o]; };
    _o setVariable ["COMSPEC_AtakRelay", true, true];
    _o setVariable ["COMSPEC_AtakRelayName", _n, true];
    _o setVariable ["COMSPEC_AtakRelayIdentity", _n, true];
    _o setVariable ["COMSPEC_AtakRelayUid", format ["relay_%1_%2_%3", round (_p select 0), round (_p select 1), round random 9999], true];
    _o setVariable ["COMSPEC_AtakRelayKind", "tour", true];
    _o setVariable ["COMSPEC_AtakRelayRange", getNumber (_cfg >> "comspec_atak_relayRange") max 1000, true];
    _o setVariable ["COMSPEC_AtakRelayThroughput", getNumber (_cfg >> "comspec_atak_relayThroughput") max 1, true];
    _o setVariable ["COMSPEC_AtakRelayHeight", getNumber (_cfg >> "comspec_atak_relayHeight") max 4, true];
    _o setVariable ["COMSPEC_AtakRelaySlots", getNumber (_cfg >> "comspec_atak_relaySlots"), true];
    _o setVariable ["COMSPEC_AtakRelaySide", _o getVariable ["COMSPEC_TowerSide", ""], true];
    _o setVariable ["COMSPEC_AtakRelayRoot", (_o getVariable ["COMSPEC_TowerUplink", "root"]) isNotEqualTo "repeater", true];
    _o setVariable ["COMSPEC_AtakRelayOn", _o getVariable ["COMSPEC_TowerOn", true], true];
    _o setVariable ["COMSPEC_AtakRelayFreq", _o getVariable ["COMSPEC_TowerFreq", 0], true];
    // Pylône réel : sur le secteur, pas de batterie.
    _o setVariable ["COMSPEC_AtakRelayAutonomy", 0, true];
    _o setVariable ["COMSPEC_AtakRelayBatt", -1, true];
    _o setVariable ["COMSPEC_AtakRelayIp", format ["10.%1.%2.1", ((round abs (_p select 0)) mod 220) + 10, ((round abs (_p select 1)) mod 220) + 10], true];
    _o setVariable ["COMSPEC_AtakRelayReliability", 96, true];
    if (_o getVariable ["COMSPEC_TowerIndestructible", false]) then { [_o, false] remoteExec ["allowDamage", _o]; };
    private _list = (missionNamespace getVariable ["COMSPEC_AtakRelays", []]) select { !isNull _x };
    _list pushBackUnique _o;
    missionNamespace setVariable ["COMSPEC_AtakRelays", _list, true];
    if (!isNil "comspec_overwatch_connect_fnc_syncAtakRelay") then { [_o, true] call comspec_overwatch_connect_fnc_syncAtakRelay; };
}, [_o]] call CBA_fnc_execNextFrame;
