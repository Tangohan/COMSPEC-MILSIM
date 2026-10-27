/*
    Module Eden : relevé complet du théâtre.

    En éditeur : ouvre la fenêtre si le module vient d’être posé (encore sélectionné).
    En mission : lance le relevé seulement si « Au début de la mission » est choisi,
    et uniquement pour le Zeus / le chef de mission.
*/
private _logic = objNull;
private _activated = true;

if (_this isEqualType objNull) then {
    _logic = _this;
} else {
    if (!(_this isEqualType [])) exitWith { false };
    private _a0 = _this param [0, objNull];
    if (_a0 isEqualType objNull) then {
        _logic = _a0;
        private _a1 = _this param [1, true];
        private _a2 = _this param [2, true];
        if (_a2 isEqualType true) then { _activated = _a2; };
        if (_a1 isEqualType true && {!(_a2 isEqualType true)}) then { _activated = _a1; };
    } else {
        if (_a0 isEqualType "") then {
            private _a1 = _this param [1, objNull];
            if (_a1 isEqualType objNull) then {
                _logic = _a1;
                _activated = _this param [3, true];
            } else {
                // is3DEN = 1 : BI appelle [mode, [logic, activated, curatorPlaced]], mission comprise.
                if (_a1 isEqualType [] && {(toLower _a0) in ["init", "registeredtoworld3den"]}) then {
                    _logic = _a1 param [0, objNull, [objNull]];
                    _activated = _a1 param [1, true, [true]];
                };
            };
        };
    };
};

if (isNull _logic) exitWith { false };
if (!(_activated isEqualType true)) then { _activated = true; };
if (!_activated) exitWith { false };

if (is3DEN) then {
    if (!hasInterface) exitWith { true };
    private _sel = [];
    _sel = get3DENSelected "object";
    if (!(_sel isEqualType [])) then { _sel = []; };
    if (_logic in _sel) then {
        [false] call comspec_overwatch_connect_fnc_theaterSurveyShow;
    };
    true
} else {
    // isGlobal = 1 : chaque machine lit la même logique. La supprimer ici (serveur ou premier client)
    // l'enlèverait aux autres machines avant leur lecture ; on ne la supprime qu'en solo.
    if (!hasInterface) exitWith { false };

    private _mode = _logic getVariable ["RunAtStart", "manual"];
    private _runAtStart = false;
    if (_mode isEqualType true) then { _runAtStart = _mode; };
    if (_mode isEqualType 0) then { _runAtStart = _mode > 0; };
    if (_mode isEqualType "") then {
        _runAtStart = (toLower (trim _mode)) in ["start", "1", "true", "oui", "auto"];
    };

    if (!isMultiplayer) then { deleteVehicle _logic; };

    // Déjà lancé pour cette mission (ex. Zeus rejoignant en cours de partie) : ne pas relancer.
    if (_runAtStart && {!(missionNamespace getVariable ["COMSPEC_TheaterSurveyDone", false])}) then {
        [] spawn {
            private _linked = { (missionNamespace getVariable ["COMSPEC_LinkState", "offline"]) isEqualTo "linked" };
            // Zeus et admin ne sont souvent connus qu'après l'initialisation : on les regarde une fois lié.
            private _priority = {
                (!isMultiplayer)
                || {serverCommandAvailable "#kick"}
                || {!isNull player && {!isNull (getAssignedCuratorLogic player)}}
            };
            private _t0 = diag_tickTime;
            waitUntil { uiSleep 3; (call _linked) || {(diag_tickTime - _t0) > 180} };
            if !(call _linked) exitWith {
                ["WARN", "Theater", "Relevé du théâtre abandonné : Athena non liée après 3 min"] call comspec_overwatch_connect_fnc_log;
            };
            private _t1 = diag_tickTime;
            waitUntil { uiSleep 2; (call _priority) || {(diag_tickTime - _t1) > 60} };
            if !(call _priority) exitWith {};
            if (missionNamespace getVariable ["COMSPEC_TheaterSurveyDone", false]) exitWith {};
            missionNamespace setVariable ["COMSPEC_TheaterSurveyDone", true, true];
            [true] call comspec_overwatch_connect_fnc_theaterSurveyShow;
        };
    };
    true
};
