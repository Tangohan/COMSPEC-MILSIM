/*
    Barre de progression d'une action SSE, puis code de succès.
    [_time, _label, _onSuccess, _args, _target, _condition] call comspec_sse_fnc_progressAction

      _target    : objet visé, position ASL (trace au sol) ou objNull ; par défaut le premier argument s'il est un objet.
      _condition : code supplémentaire (reçoit _args), true pour continuer.

    L'action s'interrompt si le joueur meurt, monte dans un véhicule, ou si la cible disparaît ou s'éloigne :
    4 m au plus (plus la moitié du rayon de l'objet pour un gros objet, un véhicule fouillé de l'extérieur).
    Avant, l'extraction d'un PC (120 s) ou un relevé biométrique aboutissait même cible partie ou joueur à 300 m.
    Une seule action à la fois ; l'animation de relevé est arrêtée dans tous les cas.
*/
params [
    ["_time", 5, [0]],
    ["_label", "SSE...", [""]],
    ["_onSuccess", {}, [{}]],
    ["_args", [], [[]]],
    ["_target", 0, [objNull, [], 0]],
    ["_condition", { true }, [{}]]
];

if (_target isEqualType 0) then {
    private _a0 = _args param [0, objNull];
    _target = [objNull, _a0] select (_a0 isEqualType objNull);
};
if (missionNamespace getVariable ["comspec_sse_progressBusy", false]) exitWith {
    ["Une action SSE est déjà en cours.", "WARNING"] call comspec_sse_fnc_notify;
    false
};

// Portée tolérée : 4 m d'une personne, d'une trace au sol ou d'un petit objet ; un véhicule se fouille de l'extérieur.
private _range = 4;
if (_target isEqualType objNull && {!isNull _target} && {!(_target isKindOf "CAManBase")}) then {
    _range = 4 + (((boundingBoxReal _target) select 2) * 0.5);
};
private _hadTarget = (_target isEqualType []) || {!isNull _target};

// Raison de l'interruption, "" si l'action peut continuer.
private _check = {
    params ["_target", "_range", "_hadTarget", "_condition", "_args"];
    if (!alive player) exitWith { "mort" };
    if (!isNull objectParent player) exitWith { "véhicule" };
    if (_target isEqualType []) exitWith {
        [["", "distance"] select (((getPosASL player) distance _target) > _range), "condition"] select !(_args call _condition)
    };
    if (_hadTarget && {isNull _target}) exitWith { "cible" };
    if (_hadTarget && {(player distance _target) > _range}) exitWith { "distance" };
    if !(_args call _condition) exitWith { "condition" };
    ""
};
private _stop = {
    params [["_why", ""], ["_ctx", []], ["_t", 0]];
    missionNamespace setVariable ["comspec_sse_progressBusy", false];
    // Action menue à terme : temps passé sur la cible, lu par calcQuality (familiarité).
    private _tgt = _ctx param [0, objNull];
    if (_why isEqualTo "" && {_t > 0} && {_tgt isEqualType objNull} && {!isNull _tgt}) then {
        _tgt setVariable ["comspec_sse_workTime", (_tgt getVariable ["comspec_sse_workTime", 0]) + _t, true];
    };
    // Fin d'un relevé biométrique animé (sans effet pour les autres actions).
    if (!isNil "comspec_sse_fnc_bioAnimate") then { [player, objNull, "", "stop"] call comspec_sse_fnc_bioAnimate; };
    if (_why isNotEqualTo "") then {
        [[
            "Action SSE interrompue.",
            "Action SSE interrompue : vous vous êtes éloigné de la cible.",
            "Action SSE interrompue : la cible n'est plus là.",
            "Action SSE interrompue : impossible depuis un véhicule."
        ] select ((["distance", "cible", "véhicule"] find _why) + 1), "WARNING"] call comspec_sse_fnc_notify;
    };
};
private _ctx = [_target, _range, _hadTarget, _condition, _args];
private _why = _ctx call _check;
if (_why isNotEqualTo "") exitWith { [_why] call _stop; false };
missionNamespace setVariable ["comspec_sse_progressBusy", true];

// Sans ACE : boucle de contrôle, interrompue dès qu'une condition tombe.
if (isNil "ace_common_fnc_progressBar") exitWith {
    [_time, _onSuccess, _args, _ctx, _check, _stop] spawn {
        params ["_t", "_code", "_a", "_ctx", "_check", "_stop"];
        private _end = time + _t;
        private _why = "";
        waitUntil {
            sleep 0.25;
            _why = _ctx call _check;
            _why isNotEqualTo "" || {time >= _end}
        };
        [_why, _ctx, _t] call _stop;
        if (_why isEqualTo "") then { _a call _code; };
    };
    true
};

[
    _time,
    [_onSuccess, _args, _ctx, _check, _stop, _time],
    {
        params ["_params"];
        _params params ["_code", "_a", "_ctx", "", "_stop", "_t"];
        ["", _ctx, _t] call _stop;
        _a call _code;
    },
    {
        params ["_params"];
        _params params ["", "", "_ctx", "_check", "_stop"];
        // Échap ou condition tombée : la raison est recalculée pour le message.
        private _why = _ctx call _check;
        [[_why, "annulée"] select (_why isEqualTo "")] call _stop;
    },
    // Libellé écrit en français par les appelants : traduit comme les actions ACE et les notifications (i18n/en.txt).
    (_label call comspec_sse_fnc_tr),
    {
        params ["_params"];
        _params params ["", "", "_ctx", "_check"];
        (_ctx call _check) isEqualTo ""
    }
] call ace_common_fnc_progressBar;

true
