/*
    Vidéo Live mis à jour sur place. fn_pageRender passe par fn_pageClear, qui détruit la caméra du flux : chaque redessin
    relançait l'image. Ces actions gardent la caméra et les contrôles de fn_pageLivecam (COMSPEC_ATAK_LivecamView).
    Params : [action, argument]
      "barText" liaison     texte structuré du bandeau (palier, débit, pertes, relais)
      "tier" liaison        palier changé : définition de l'image et bandeau. Le rendu est coupé et repris le temps de
                            changer la texture, comme le faisait le redessin
      "mode" 0|1|2          vision : effet du flux et boutons
      "modeMark" 0|1|2      boutons de vision seulement (vision imposée par le terminal d'un drone)
      "target"              source choisie dans la liste (état livecamTarget) : caméra rattachée si la source est du même
                            genre que l'affichée (porteur, caméra fixe, drone), joignable et de même droit de partage
      "renameShow" bool     montre ou cache le champ de RENOMMER
      "renamed"             valide le nom saisi, met à jour la ligne de la liste
    Retour : false si rien n'a pu se faire sur place ; l'appelant redessine alors la page.
*/
params [["_act", ""], ["_arg", 0]];
disableSerialization;
private _view = uiNamespace getVariable ["COMSPEC_ATAK_LivecamView", createHashMap];
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _barText = {
    params ["_link"];
    (_link get "up") params ["", "", "_upRelay"];
    (_link get "down") params ["", "", "_downRelay"];
    private _relays = [_upRelay, _downRelay] select { _x isNotEqualTo "" };
    private _path = (_relays arrayIntersect _relays) joinString " » ";
    private _tierCol = createHashMapFromArray [["HD", "#5bc76b"], ["SD", "#c9d4cf"], ["BAS", "#f2ab33"], ["MINIMAL", "#e5483a"]];
    (format ["<t align='right'><t font='COMSPEC_BarlowBold' color='%1'>%2</t> <t color='#c9d4cf'>· %3 · perte %4 %%%5</t></t>",
        _tierCol getOrDefault [_link get "tier", "#c9d4cf"], _link get "tier",
        [format ["%1 kbit/s", _link get "kbps"], format ["%1 Mbit/s", ((_link get "kbps") / 1000) toFixed 1]] select ((_link get "kbps") >= 1000),
        _link get "loss", ["", format [" · via %1", _path]] select (_path isNotEqualTo "")]) call comspec_atak_native_fnc_tr
};
private _applyLink = {
    params ["_link"];
    private _pic = _view getOrDefault ["pic", controlNull];
    private _bar = _view getOrDefault ["bar", controlNull];
    if (isNull _pic || {isNull _bar}) exitWith { false };
    private _res = _link get "res";
    if (_res <= 0) exitWith { false };
    if (_res isNotEqualTo (_view getOrDefault ["res", 0])) then {
        private _cam = missionNamespace getVariable ["COMSPEC_ATAK_LivecamCam", objNull];
        if (!isNull _cam) then { _cam cameraEffect ["Terminate", "Back", "comspec_livecam"]; };
        _pic ctrlSetText format ["#(argb,%1,%1,1)r2t(comspec_livecam,1.0)", _res]; // i18n:brut
        if (!isNull _cam) then {
            _cam cameraEffect ["Internal", "Back", "comspec_livecam"];
            "comspec_livecam" setPiPEffect [_s getOrDefault ["livecamMode", 0]];
        };
        _view set ["res", _res];
    };
    _bar ctrlSetStructuredText parseText ([_link] call _barText);
    private _drop = _view getOrDefault ["drop", controlNull];
    if (!isNull _drop) then {
        _drop ctrlSetStructuredText parseText ((format ["<br/><br/><t align='center' font='COMSPEC_BarlowBold' color='#e0a33a'>SIGNAL INSTABLE</t><br/><t align='center' size='0.8' color='#8a9a93'>mise en mémoire tampon · %1 kbit/s</t>", _link get "kbps"]) call comspec_atak_native_fnc_tr);
    };
    private _args = _view getOrDefault ["args", []];
    if (_args isNotEqualTo []) then { _args set [0, _link get "drop"]; _args set [1, _link get "tier"]; };
    true
};
private _mark = {
    params ["_m"];
    {
        _x params ["_b", "_v"];
        if (!isNull _b) then {
            private _on = _v isEqualTo _m;
            _b ctrlSetBackgroundColor ([[0.12, 0.145, 0.13, 1], [0.36, 0.78, 0.42, 0.9]] select _on);
            _b ctrlSetTextColor ([[0.92, 0.96, 0.93, 1], [0.03, 0.05, 0.04, 1]] select _on);
        };
    } forEach (_view getOrDefault ["modes", []]);
};
private _renameShow = {
    params ["_on"];
    (_view getOrDefault ["rename", []]) params [["_e", controlNull], ["_ok", controlNull], ["_rb", controlNull]];
    if (isNull _e) exitWith { false };
    _e ctrlShow _on;
    _ok ctrlShow _on;
    _rb ctrlShow !_on;
    _s set ["livecamRename", _on];
    if (_on) then {
        _e ctrlSetText ((objectFromNetId (_s getOrDefault ["livecamTarget", ""])) getVariable ["COMSPEC_ATAK_CamName", ""]); // i18n:brut
        ctrlSetFocus _e;
    };
    true
};
switch (_act) do {
    case "barText": { [_arg] call _barText };
    case "tier": { [_arg] call _applyLink };
    case "mode": {
        _s set ["livecamMode", _arg];
        "comspec_livecam" setPiPEffect [_arg];
        [_arg] call _mark;
        true
    };
    case "modeMark": { [_arg] call _mark; true };
    case "renameShow": { [_arg] call _renameShow };
    case "renamed": {
        private _obj = objectFromNetId (_s getOrDefault ["livecamTarget", ""]);
        private _e = uiNamespace getVariable ["COMSPEC_ATAK_CamNameEdit", controlNull];
        if (!isNull _e && {!isNull _obj}) then {
            ["setName", _obj, ctrlText _e] call comspec_atak_native_fnc_camAction;
            private _list = _view getOrDefault ["list", controlNull];
            if (!isNull _list && {(lbCurSel _list) >= 0}) then {
                private _st = ["status", _obj] call comspec_atak_native_fnc_camAction;
                _list lbSetText [lbCurSel _list, (format ["%1 · %2 m · %3", _obj getVariable ["COMSPEC_ATAK_CamName", "CAM"], round (player distance _obj), _st select 1]) call comspec_atak_native_fnc_tr]; // i18n:brut (déjà traduit)
            };
        };
        [false] call _renameShow
    };
    case "target": {
        if ((count _view) isEqualTo 0) exitWith { false };
        private _unit = objectFromNetId (_s getOrDefault ["livecamTarget", ""]);
        if (isNull _unit) exitWith { false };
        private _kind = switch (true) do {
            case (_unit in (["list"] call comspec_atak_native_fnc_camAction)): { "fixed" };
            case (unitIsUAV _unit): { "uav" };
            default { "unit" };
        };
        if (_kind isNotEqualTo (_view getOrDefault ["kind", ""])) exitWith { false };
        if ((["canShare", _unit] call comspec_atak_native_fnc_camShare) isNotEqualTo (_view getOrDefault ["canShare", false])) exitWith { false };
        if (_kind isEqualTo "fixed" && {((["status", _unit] call comspec_atak_native_fnc_camAction) select 0) isNotEqualTo "OK"}) exitWith { false };
        private _link = ["eval", _unit] call comspec_atak_native_fnc_feedLink;
        if ((_link get "res") isEqualTo 0) exitWith { false };
        private _mode = _s getOrDefault ["livecamMode", 0];
        switch (_kind) do {
            case "fixed": { [_unit, _mode, 0] call comspec_atak_native_fnc_camView; };
            case "uav": { [_unit, _mode] call comspec_atak_native_fnc_livecamUav; };
            default { [_unit, _mode] call comspec_atak_native_fnc_livecamStart; };
        };
        [false] call _renameShow;
        [_link] call _applyLink
    };
    default { false };
};
