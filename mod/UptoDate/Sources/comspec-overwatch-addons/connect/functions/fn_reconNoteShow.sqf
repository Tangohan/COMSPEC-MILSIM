/*
    Ouvre Reco : dans le téléphone d’abord (comme Appui aérien), sinon formulaire overlay.
*/
if (!hasInterface) exitWith {};
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {
    ["Activez d’abord Overwatch pour poser une note de reco.", "system", "warn"] call comspec_overwatch_connect_fnc_announce;
};
if !([player] call comspec_overwatch_connect_fnc_hasTerminal) exitWith {
    ["Prenez un terminal ATAK pour envoyer une note de reco.", "system", "warn"] call comspec_overwatch_connect_fnc_announce;
};

if (!isNil "comspec_overwatch_atak_athena_fnc_athena_openRecon") exitWith {
    [] call comspec_overwatch_atak_athena_fnc_athena_openRecon;
};

private _last = missionNamespace getVariable ["COMSPEC_ReconNoteAt", -100];
if ((diag_tickTime - _last) < 8) exitWith {
    private _wait = ceil (8 - (diag_tickTime - _last));
    [format ["Attendez %1 s avant une nouvelle note.", _wait], "tactical", "warn"] call comspec_overwatch_connect_fnc_announce;
};

if (!isNull (uiNamespace getVariable ["COMSPEC_ReconNote_Display", displayNull])) exitWith {};
if (missionNamespace getVariable ["COMSPEC_ReconNoteOpening", false]) exitWith {};
missionNamespace setVariable ["COMSPEC_ReconNoteOpening", true, false];

private _pos = [] call comspec_overwatch_connect_fnc_reconLookPos;
if (!(_pos isEqualType []) || {(count _pos) < 3}) then { _pos = getPosASL player; };
uiNamespace setVariable ["COMSPEC_ReconNote_Pos", _pos];

[] spawn {
    private _parent = findDisplay 46;
    if (isNull _parent) then { _parent = findDisplay 49; };
    if (isNull _parent) then { _parent = findDisplay 0; };

    private _ok = false;
    private _disp = displayNull;
    if (!isNull _parent) then {
        _disp = _parent createDisplay "COMSPEC_ReconNote_Dialog";
        _ok = !isNull _disp;
    };
    if (!_ok || {isNull _disp}) then {
        _ok = createDialog "COMSPEC_ReconNote_Dialog";
        _disp = uiNamespace getVariable ["COMSPEC_ReconNote_Display", displayNull];
        if (isNull _disp) then { _disp = findDisplay 9966; };
    };

    missionNamespace setVariable ["COMSPEC_ReconNoteOpening", false, false];
    if (!_ok || {isNull _disp}) exitWith {
        ["Impossible d’ouvrir la note de reco.", "system", "warn"] call comspec_overwatch_connect_fnc_announce;
    };

    uiNamespace setVariable ["COMSPEC_ReconNote_Display", _disp];
    _disp setVariable ["COMSPEC_ReconTag", ""];
    _disp setVariable ["COMSPEC_ReconConfidence", "vu_direct"];

    private _edit = _disp displayCtrl 9762;
    if (!isNull _edit) then {
        _edit ctrlSetText "";
        _edit ctrlAddEventHandler ["KeyUp", {
            private _d = uiNamespace getVariable ["COMSPEC_ReconNote_Display", displayNull];
            if (isNull _d) exitWith {};
            private _t = ctrlText (_d displayCtrl 9762);
            if ((count _t) > 140) then {
                _t = _t select [0, 140];
                (_d displayCtrl 9762) ctrlSetText _t;
            };
            (_d displayCtrl 9763) ctrlSetStructuredText parseText format [
                "<t align='right' size='0.5' color='#6a8498'>%1 / 140</t>",
                count _t
            ];
        }];
    };

    private _hint = _disp displayCtrl 9761;
    if (!isNull _hint) then {
        private _p = uiNamespace getVariable ["COMSPEC_ReconNote_Pos", getPosASL player];
        private _grid = if (_p isEqualType [] && {(count _p) >= 2}) then { mapGridPosition _p } else { mapGridPosition player };
        _hint ctrlSetStructuredText parseText format [
            "<t align='center' size='0.55' color='#8aa0b4'>Grille %1 · 140 caractères max.</t>",
            _grid
        ];
    };

    private _tags = [
        [9764, "vehicle"],
        [9765, "armed_group"],
        [9766, "static"],
        [9767, "mine"],
        [9768, "civilian"],
        [9769, "infrastructure"],
        [9770, "other"]
    ];
    {
        _x params ["_idc", "_code"];
        private _btn = _disp displayCtrl _idc;
        if (isNull _btn) then { continue };
        _btn setVariable ["COMSPEC_ReconTagCode", _code];
        _btn ctrlAddEventHandler ["ButtonClick", {
            params ["_ctrl"];
            private _d = ctrlParent _ctrl;
            if (isNull _d) exitWith {};
            private _code = _ctrl getVariable ["COMSPEC_ReconTagCode", ""];
            private _cur = _d getVariable ["COMSPEC_ReconTag", ""];
            if (_cur isEqualTo _code) then { _code = ""; };
            _d setVariable ["COMSPEC_ReconTag", _code];
            {
                private _b = _d displayCtrl (_x select 0);
                if (isNull _b) then { continue };
                private _on = ((_x select 1) isEqualTo _code) && {_code isNotEqualTo ""};
                _b ctrlSetBackgroundColor (if (_on) then { [0.12, 0.42, 0.36, 1] } else { [0.04, 0.10, 0.14, 0.9] });
            } forEach [
                [9764, "vehicle"], [9765, "armed_group"], [9766, "static"], [9767, "mine"],
                [9768, "civilian"], [9769, "infrastructure"], [9770, "other"]
            ];
        }];
    } forEach _tags;

    private _conf = _disp displayCtrl 9771;
    if (!isNull _conf) then {
        _conf ctrlSetText "Confiance : vu direct";
        _conf ctrlAddEventHandler ["ButtonClick", {
            params ["_ctrl"];
            private _d = ctrlParent _ctrl;
            if (isNull _d) exitWith {};
            private _cur = _d getVariable ["COMSPEC_ReconConfidence", "vu_direct"];
            private _next = if (_cur isEqualTo "vu_direct") then { "rapporte" } else { "vu_direct" };
            _d setVariable ["COMSPEC_ReconConfidence", _next];
            _ctrl ctrlSetText (if (_next isEqualTo "vu_direct") then { "Confiance : vu direct" } else { "Confiance : rapporté" });
        }];
    };
};
