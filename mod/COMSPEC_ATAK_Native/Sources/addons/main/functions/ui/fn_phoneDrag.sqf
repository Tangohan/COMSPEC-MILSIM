/*
    Déplacer le téléphone en main : clic gauche maintenu sur la coque (hors écran), puis relâcher.
    Le décalage est mémorisé par mode et orientation (profil), et repris par le téléphone porté.
    Mode disposition (Réglages > Emplacement, « DÉPLACER À LA SOURIS ») : comme en Zeus, le téléphone passe en mini et
    se saisit n'importe où, écran compris (un voile bloque les apps) ; le décalage du mini sert aussi au téléphone porté.
    Clic droit ou bouton TERMINÉ : fin du mode.
    Params : ["DOWN" | "MOVE" | "UP", bouton]  ["ARRANGE_ON"]  ["ARRANGE_OFF"]  ["ARRANGE_DRAW"]
*/
params ["_event", ["_button", 0]];
disableSerialization;
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _mouse = getMousePosition;
private _arrange = uiNamespace getVariable ["COMSPEC_ATAK_Arrange", false];
private _wipe = {
    { ctrlDelete _x; } forEach (uiNamespace getVariable ["COMSPEC_ATAK_ArrangeCtrls", []]);
    uiNamespace setVariable ["COMSPEC_ATAK_ArrangeCtrls", []];
};
switch (_event) do {
    case "ARRANGE_ON": {
        private _d = [] call comspec_atak_native_fnc_display;
        if (isNull _d || {!(_s getOrDefault ["interactive", false])}) exitWith {
            ["Sortez le téléphone en main pour le déplacer", 3] call comspec_atak_native_fnc_toast;
            false
        };
        uiNamespace setVariable ["COMSPEC_ATAK_Arrange", true];
        // Le mode disposition règle le mini : le plein écran repasse en mini, en main.
        if ((toUpper (profileNamespace getVariable ["COMSPEC_ATAK_Mode", "MINI"])) isNotEqualTo "MINI") then {
            profileNamespace setVariable ["COMSPEC_ATAK_Mode", "MINI"];
            saveProfileNamespace;
            [_s getOrDefault ["activePage", "LAUNCHER"], false] call comspec_atak_native_fnc_navigate;
        };
        ["ARRANGE_DRAW"] call comspec_atak_native_fnc_phoneDrag;
        true
    };
    case "ARRANGE_OFF": {
        uiNamespace setVariable ["COMSPEC_ATAK_Arrange", false];
        _s set ["drag", []];
        call _wipe;
        saveProfileNamespace;
        ["Position du téléphone enregistrée", 2] call comspec_atak_native_fnc_toast;
        true
    };
    case "ARRANGE_DRAW": {
        // Pendant le glisser (MOVE) : [disposition déjà calculée] ; les contrôles existants sont déplacés, pas recréés.
        private _given = _button isEqualType createHashMap;
        private _d = [] call comspec_atak_native_fnc_display;
        if (!_arrange || {isNull _d}) exitWith { call _wipe; false };
        private _l = if (_given) then { _button } else { [] call comspec_atak_native_fnc_layoutGet };
        (_l get "visible") params ["_vx", "_vy", "_vw", "_vh"];
        (_l get "device") params ["_sx", "_sy", "_sw", "_sh"];
        private _font = _l get "font";
        private _bw = _sw * 0.4;
        private _rects = [[_sx, _sy, _sw, _sh], [_sx, _sy, _sw, _sh],
            [_vx, _vy, _vw, pixelH * 2], [_vx, _vy + _vh - pixelH * 2, _vw, pixelH * 2], [_vx, _vy, pixelW * 2, _vh], [_vx + _vw - pixelW * 2, _vy, pixelW * 2, _vh],
            [_sx, _sy + _sh * 0.28, _sw, _sh * 0.4], [_sx + (_sw - _bw) / 2, _sy + _sh * 0.72, _bw, _font * 1.6]];
        private _old = uiNamespace getVariable ["COMSPEC_ATAK_ArrangeCtrls", []];
        if (_given && {(count _old) isEqualTo (count _rects)} && {(_old findIf { isNull _x }) < 0}) exitWith {
            { _x ctrlSetPosition (_rects select _forEachIndex); _x ctrlCommit 0; } forEach _old;
            true
        };
        call _wipe;
        private _made = [];
        // Voile sur l'écran (bloque les apps), cadre pointillé autour du téléphone, consigne au centre.
        private _veil = _d ctrlCreate ["COMSPEC_RscText", -1];
        _veil ctrlSetPosition [_sx, _sy, _sw, _sh];
        _veil ctrlSetBackgroundColor [0, 0, 0, 0.62];
        _veil ctrlCommit 0;
        _made pushBack _veil;
        // Bouton invisible sur tout l'écran : les clics n'atteignent plus les apps (le glisser passe par le display).
        private _block = _d ctrlCreate ["COMSPEC_RscButtonInvisible", -1];
        _block ctrlSetPosition [_sx, _sy, _sw, _sh];
        _block ctrlCommit 0;
        _made pushBack _block;
        {
            private _c = _d ctrlCreate ["COMSPEC_RscText", -1];
            _c ctrlSetPosition _x;
            _c ctrlSetBackgroundColor [0.36, 0.78, 0.42, 0.95];
            _c ctrlCommit 0;
            _made pushBack _c;
        } forEach [[_vx, _vy, _vw, pixelH * 2], [_vx, _vy + _vh - pixelH * 2, _vw, pixelH * 2], [_vx, _vy, pixelW * 2, _vh], [_vx + _vw - pixelW * 2, _vy, pixelW * 2, _vh]];
        private _t = _d ctrlCreate ["COMSPEC_RscStructuredText", -1];
        _t ctrlSetPosition [_sx, _sy + _sh * 0.28, _sw, _sh * 0.4];
        _t ctrlSetStructuredText parseText (("<t align='center' font='COMSPEC_BarlowBold' color='#5cc76b'>MODE DISPOSITION</t><br/><t align='center' size='0.85' color='#e8eeea'>Maintenez le clic gauche et glissez le téléphone.<br/>Clic droit pour terminer.</t>") call comspec_atak_native_fnc_tr);
        _t ctrlSetFontHeight _font;
        _t ctrlCommit 0;
        _made pushBack _t;
        private _b = _d ctrlCreate ["COMSPEC_RscButton", -1];
        _b ctrlSetPosition [_sx + (_sw - _bw) / 2, _sy + _sh * 0.72, _bw, _font * 1.6];
        _b ctrlSetText (("TERMINÉ") call comspec_atak_native_fnc_tr);
        _b ctrlSetFontHeight (_l get "fontSmall");
        _b ctrlCommit 0;
        _b ctrlAddEventHandler ["ButtonClick", { [{ ["ARRANGE_OFF"] call comspec_atak_native_fnc_phoneDrag; }] call CBA_fnc_execNextFrame; }];
        _made pushBack _b;
        uiNamespace setVariable ["COMSPEC_ATAK_ArrangeCtrls", _made];
        true
    };
    case "DOWN": {
        if (_arrange && {_button isEqualTo 1}) exitWith { ["ARRANGE_OFF"] call comspec_atak_native_fnc_phoneDrag; true };
        if (_button isNotEqualTo 0) exitWith { false };
        private _l = [] call comspec_atak_native_fnc_layoutGet;
        private _in = {
            params ["_p", "_r"];
            _r params ["_x", "_y", "_w", "_h"];
            (_p select 0) >= _x && {(_p select 0) <= _x + _w} && {(_p select 1) >= _y} && {(_p select 1) <= _y + _h}
        };
        if !([_mouse, _l get "visible"] call _in) exitWith { false };
        // Hors mode disposition, l'écran reste aux apps : on saisit la coque seulement.
        if (!_arrange && {[_mouse, _l get "device"] call _in}) exitWith { false };
        _s set ["drag", [_mouse, profileNamespace getVariable [_l get "offsetKey", [0, 0]], _l get "offsetKey"]];
        true
    };
    case "MOVE": {
        private _drag = _s getOrDefault ["drag", []];
        if ((count _drag) isEqualTo 0) exitWith { false };
        // MouseMoving tombe plusieurs fois par image : une seule mise en page par image (UP rattrape la dernière position).
        if ((_s getOrDefault ["dragFrame", -1]) isEqualTo diag_frameNo) exitWith { true };
        _s set ["dragFrame", diag_frameNo];
        _drag params ["_start", "_off", "_key"];
        profileNamespace setVariable [_key, [(_off select 0) + (_mouse select 0) - (_start select 0), (_off select 1) + (_mouse select 1) - (_start select 1)]];
        // Le téléphone ne sort pas de l'écran : au moins un quart de sa coque reste visible.
        private _l = [] call comspec_atak_native_fnc_layoutGet;
        (_l get "visible") params ["_vx", "_vy", "_vw", "_vh"];
        private _fx = ((safeZoneX - _vx - _vw * 0.75) max 0) - ((_vx + _vw * 0.25 - (safeZoneX + safeZoneW)) max 0);
        private _fy = ((safeZoneY - _vy - _vh * 0.75) max 0) - ((_vy + _vh * 0.25 - (safeZoneY + safeZoneH)) max 0);
        if (_fx != 0 || {_fy != 0}) then {
            (profileNamespace getVariable [_key, [0, 0]]) params ["_ox", "_oy"];
            profileNamespace setVariable [_key, [_ox + _fx, _oy + _fy]];
            _l = [] call comspec_atak_native_fnc_layoutGet;
        };
        [_l] call comspec_atak_native_fnc_layoutApply;
        if (_arrange) then { ["ARRANGE_DRAW", _l] call comspec_atak_native_fnc_phoneDrag; };
        true
    };
    case "UP": {
        if ((count (_s getOrDefault ["drag", []])) isEqualTo 0) exitWith { false };
        // Dernière position de la souris (un MOVE de la même image a pu être sauté).
        _s set ["dragFrame", -1];
        ["MOVE"] call comspec_atak_native_fnc_phoneDrag;
        _s set ["drag", []];
        saveProfileNamespace;
        [_s getOrDefault ["activePage", "MAP"]] call comspec_atak_native_fnc_pageRender;
        // La page redessinée passe devant : le voile du mode disposition revient par-dessus.
        if (_arrange) then { ["ARRANGE_DRAW"] call comspec_atak_native_fnc_phoneDrag; };
        true
    };
    default { false };
}
