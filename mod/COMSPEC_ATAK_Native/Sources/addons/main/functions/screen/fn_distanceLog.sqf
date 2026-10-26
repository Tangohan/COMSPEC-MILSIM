/*
    Carnet de l'app Distances, gardé sur ce PC (profileNamespace COMSPEC_ATAK_DistBook) : les 12 dernières missions
    (graphique « Dernières missions »), le cumul de toutes les missions jouées avec ce profil (« Carrière »), les heures
    de pilote par appareil et les records d'une mission. Lu dans COMSPEC_ATAK_DistMe (compteurs de cette mission que le
    serveur pousse toutes les 15 s, fn_distanceServer). Rien n'est envoyé : le site garde sa propre carrière (fiche du
    membre, Métriques joueurs), alimentée par le serveur seul.
    Params : ["tick" | "save"] ; tick toutes les 15 s (XEH_postInitClient), save en fin de mission.
    Livre (paires) : life [clé, valeur]..., pilot [nom, heli|plane, secondes, mètres]..., rec [clé, valeur, mission, jour]...,
                     missions [clé de mission, nom, carte, jour, [clé, valeur]...]... (la plus récente en dernier)
*/
params [["_mode", "tick"]];
if (!hasInterface) exitWith { false };
private _book = missionNamespace getVariable ["COMSPEC_ATAK_DistBk", createHashMap];
if ((count _book) isEqualTo 0) then {
    _book = createHashMapFromArray (profileNamespace getVariable ["COMSPEC_ATAK_DistBook", []]);
    _book set ["life", createHashMapFromArray (_book getOrDefault ["life", []])];
    private _pl = createHashMap;
    { _pl set [_x select 0, _x]; } forEach (_book getOrDefault ["pilot", []]);
    _book set ["pilot", _pl];
    private _rec = createHashMap;
    { _rec set [_x select 0, _x]; } forEach (_book getOrDefault ["rec", []]);
    _book set ["rec", _rec];
    _book set ["missions", +(_book getOrDefault ["missions", []])];
    missionNamespace setVariable ["COMSPEC_ATAK_DistBk", _book];
};
(player getVariable ["COMSPEC_ATAK_DistMe", []]) params [["_pairs", []], ["_pilot", []]];
if (_pairs isNotEqualTo []) then {
    private _cur = createHashMapFromArray _pairs;
    private _mk = missionNamespace getVariable ["COMSPEC_ATAK_MissionKey", format ["%1@%2", missionName, worldName]];
    private _missions = _book get "missions";
    private _seen = missionNamespace getVariable "COMSPEC_ATAK_DistSeen";
    if (isNil "_seen") then {
        // Retour dans la même partie (reconnexion, jeu relancé) : reprise des compteurs déjà comptés dans la carrière.
        private _last = _missions param [(count _missions) - 1, []];
        _seen = if ((_last param [0, ""]) isEqualTo _mk) then { [createHashMapFromArray (_last select 4), createHashMapFromArray (_last param [5, []])] } else { [createHashMap, createHashMap] };
        missionNamespace setVariable ["COMSPEC_ATAK_DistSeen", _seen];
    };
    _seen params ["_sc", "_sp"];
    private _life = _book get "life";
    // Écarts depuis le dernier passage ajoutés à la carrière ; compteurs en baisse (serveur relancé) : repris de zéro.
    {
        private _d = _y - (_sc getOrDefault [_x, 0]);
        if (_d < 0) then { _d = _y; };
        if (_d > 0) then { _life set [_x, (_life getOrDefault [_x, 0]) + _d]; };
        _sc set [_x, _y];
    } forEach _cur;
    private _pl = _book get "pilot";
    private _pm = [];
    {
        _x params ["_cls", "_label", "_kind", "_ps", "_pmm"];
        private _name = [_label, _cls] select (_label isEqualTo "");
        (_sp getOrDefault [_cls, [0, 0]]) params ["_s0", "_m0"];
        private _ds = _ps - _s0; private _dm = _pmm - _m0;
        if (_ds < 0) then { _ds = _ps; _dm = _pmm; };
        (_pl getOrDefault [_name, [_name, _kind, 0, 0]]) params ["", "", "_ls", "_lm"];
        _pl set [_name, [_name, _kind, _ls + _ds, _lm + (_dm max 0)]];
        _sp set [_cls, [_ps, _pmm]];
        _pm pushBack [_cls, [_ps, _pmm]];
    } forEach _pilot;
    // Mission en cours : créée au premier passage, mise à jour ensuite.
    systemTime params ["_yy", "_mo", "_dd"];
    private _day = format ["%1-%2-%3", _yy, [str _mo, "0" + str _mo] select (_mo < 10), [str _dd, "0" + str _dd] select (_dd < 10)];
    private _entry = [_mk, briefingName, worldName, _day, (keys _cur) apply { [_x, round (_cur get _x)] }, _pm];
    if (((_missions param [(count _missions) - 1, []]) param [0, ""]) isEqualTo _mk) then {
        _entry set [3, (_missions select ((count _missions) - 1)) select 3];
        _missions set [(count _missions) - 1, _entry];
    } else {
        _missions pushBack _entry;
        (_book get "life") set ["missions", ((_book get "life") getOrDefault ["missions", 0]) + 1];
        if ((count _missions) > 12) then { _book set ["missions", _missions select [(count _missions) - 12]]; };
    };
    // Records d'une mission (total, à pied, pas, vol, sauts…) : la mission en cours les bat en direct.
    private _total = 0; { _total = _total + (_cur getOrDefault [_x, 0]); } forEach ["foot_m", "land_m", "sea_m", "air_m", "halo_m"];
    private _rec = _book get "rec";
    {
        _x params ["_k", "_v"];
        if (_v > ((_rec getOrDefault [_k, [_k, 0]]) select 1)) then { _rec set [_k, [_k, round _v, briefingName, _day, _mk]]; };
    } forEach [["total_m", _total], ["foot_m", _cur getOrDefault ["foot_m", 0]], ["steps", _cur getOrDefault ["steps", 0]], ["land_m", _cur getOrDefault ["land_m", 0]],
        ["air_m", _cur getOrDefault ["air_m", 0]], ["sea_m", _cur getOrDefault ["sea_m", 0]], ["halo_jumps", _cur getOrDefault ["halo_jumps", 0]]];
};
// Profil : écrit à chaque passage, enregistré sur le disque toutes les 5 min et en fin de mission.
private _out = [
    ["life", (keys (_book get "life")) apply { [_x, (_book get "life") get _x] }],
    ["pilot", values (_book get "pilot")],
    ["rec", values (_book get "rec")],
    ["missions", _book get "missions"]
];
profileNamespace setVariable ["COMSPEC_ATAK_DistBook", _out];
if (_mode isEqualTo "save" || {(diag_tickTime - (missionNamespace getVariable ["COMSPEC_ATAK_DistSaved", 0])) > 300}) then {
    missionNamespace setVariable ["COMSPEC_ATAK_DistSaved", diag_tickTime];
    // Fin de mission : écrit tout de suite ; passage périodique : profil marqué modifié (fn_profileSave).
    ([["now"], ["dirty"]] select (_mode isEqualTo "tick")) call comspec_atak_native_fnc_profileSave;
};
true
