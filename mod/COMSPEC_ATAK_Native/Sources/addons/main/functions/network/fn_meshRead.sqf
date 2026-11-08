/*
    Lecture sûre des variables du réseau maillé publiées par les autres joueurs (lot 1, docs/technical/reseau-mesh-mpu5.md,
    section 6). Une variable publiée par un autre client n'est pas fiable : tout consommateur passe par cette fonction,
    qui vérifie le type, la taille et les bornes, et rend un enregistrement vide au moindre écart (nombre non fini compris).
    C'est un garde-fou contre les valeurs absurdes, pas une sécurité : un client modifié peut publier une valeur plausible.
      ["node", unité]      COMSPEC_ATAK_MpuNode -> [réseau "C1".."C16", portée 1..20 000 m, objet antenne (l'unité ou une radio
                           posée au sol), largeur de canal 0 étroit | 1 normal | 2 large] ou []
      ["mpu5", unité]      COMSPEC_ATAK_Mpu5 -> [débit 0..100 Mbit/s, bonds 0..64, portée 1..30 000 m, nom] ou []
                           (30 000 m : borne du réglage « portée données » d'une radio longue portée)
      ["voice", unité]     COMSPEC_ATAK_MpuVoice -> [type "acre" | "sw" | "lr", clé de radio, fréquence, portée 0..100 000 m, réseau
                           "" | "R…" | "L…", sauts 0..64, nom] ou [] (100 000 m : certaines radios TFAR dépassent 20 km)
      ["voiceNet", unité]  COMSPEC_ATAK_VoiceNet -> [[id radio, MHz 0..100 000, nom du canal]...], 32 au plus, ou []
      ["talkgroup", unité] COMSPEC_ATAK_Talkgroup -> texte de 32 signes au plus, ou ""
      ["check", [genre, valeur, unité]] : même contrôle sur une valeur donnée (sans effet, pour fn_devMeshTest)
*/
params [["_kind", ""], ["_arg", objNull]];
private _raw = [];
private _unit = objNull;
if (_kind isEqualTo "check") then {
    _kind = _arg param [0, ""];
    _raw = _arg param [1, []];
    _unit = _arg param [2, objNull];
} else {
    _unit = _arg;
    if !(_unit isEqualType objNull) exitWith {};
    _raw = _unit getVariable [["COMSPEC_ATAK_MpuNode", "COMSPEC_ATAK_Mpu5", "COMSPEC_ATAK_MpuVoice", "COMSPEC_ATAK_VoiceNet", "COMSPEC_ATAK_Talkgroup"] param [["node", "mpu5", "voice", "voiceNet", "talkgroup"] find _kind, ""], []];
};
if (_kind isEqualTo "talkgroup") exitWith {
    if (_raw isEqualType "") then { _raw select [0, 32] } else { "" }
};
if (_raw isEqualTo [] || {!(_raw isEqualType [])}) exitWith { [] };
private _inRange = {
    params ["_v", "_lo", "_hi"];
    (_v isEqualType 0) && {finite _v} && {_v >= _lo} && {_v <= _hi}
};
switch (_kind) do {
    case "node": {
        if !((count _raw) in [3, 4]) exitWith { [] };
        _raw params [["_key", 0], ["_range", 0], ["_ant", 0], ["_width", 1]];
        if !(_width in [0, 1, 2]) exitWith { [] };
        if (!(_key isEqualType "") || {(count _key) > 3} || {(_key select [0, 1]) isNotEqualTo "C"}) exitWith { [] };
        if !([parseNumber (_key select [1]), 1, 16] call _inRange) exitWith { [] };
        if !([_range, 1, 20000] call _inRange) exitWith { [] };
        if (!(_ant isEqualType objNull) || {isNull _ant}) exitWith { [] };
        if (_ant isNotEqualTo _unit && {!(_ant isKindOf "WeaponHolder")} && {!(_ant isKindOf "GroundWeaponHolder")} && {!(_ant isKindOf "WeaponHolderSimulated")}) exitWith { [] };
        [_key, _range, _ant, _width]
    };
    case "mpu5": {
        if ((count _raw) isNotEqualTo 4) exitWith { [] };
        _raw params [["_up", -1], ["_hops", -1], ["_range", 0], ["_name", 0]];
        if (!([_up, 0, 100] call _inRange) || {!([_hops, 0, 64] call _inRange)} || {!([_range, 1, 30000] call _inRange)} || {!(_name isEqualType "")}) exitWith { [] };
        [_up, _hops, _range, _name select [0, 24]]
    };
    case "voice": {
        if ((count _raw) isNotEqualTo 7) exitWith { [] };
        _raw params [["_type", ""], ["_key", 0], ["_freq", 0], ["_range", -1], ["_net", 0], ["_hops", -1], ["_name", 0]];
        if (!(_type in ["acre", "sw", "lr"]) || {!(_key isEqualType "")} || {(count _key) > 64} || {!(_freq isEqualType "")} || {(count _freq) > 24}) exitWith { [] };
        if (!([_range, 0, 100000] call _inRange) || {!([_hops, 0, 64] call _inRange)}) exitWith { [] };
        if (!(_net isEqualType "") || {(count _net) > 32} || {!((_net select [0, 1]) in ["", "R", "L"])} || {!(_name isEqualType "")}) exitWith { [] };
        [_type, _key, _freq, _range, _net, _hops, _name select [0, 40]]
    };
    case "voiceNet": {
        if ((count _raw) > 32) exitWith { [] };
        private _out = [];
        {
            private _row = if (isNil "_x") then { 0 } else { _x };
            if (!(_row isEqualType []) || {(count _row) isNotEqualTo 3}) exitWith { _out = []; };
            _row params [["_id", 0], ["_mhz", -1], ["_label", 0]];
            if (!(_id isEqualType "") || {(count _id) > 64} || {!([_mhz, 0, 100000] call _inRange)} || {!(_label isEqualType "")}) exitWith { _out = []; };
            _out pushBack [_id, _mhz, _label select [0, 40]];
        } forEach _raw;
        _out
    };
    default { [] };
};
