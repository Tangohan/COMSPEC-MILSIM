/*
    Live cam : caméra poitrine des autres porteurs d'ATAK du camp.
    Une caméra locale est fixée au buste du porteur choisi (os "spine3", regard vers l'avant)
    et rendue dans l'écran du téléphone (render-to-texture). Modes : jour, vision nocturne, thermique.
    La caméra est détruite dès qu'on quitte la page (fn_livecamStop, appelé par pageClear).
    Caméras fixes (fn_camAction, fn_camView) : caméras IP posées par le camp, listées sous les porteurs avec distance
    et état (en ligne, hors liaison, brouillée, détruite) ; même rendu, panoramique local ◀ ▶ de 30°, renommage.
    Le flux suit le réseau (fn_feedLink) : liaison montante de la source et descendante du téléphone, relais compris.
    HD, SD, BAS ou MINIMAL fixent la définition de l'image ; les pertes la font décrocher par moments ; sans liaison,
    pas d'image et la raison est affichée. Qualité réévaluée toutes les 3 s (page redessinée si le palier change).
*/
disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];
private _pad = (_l get "pad") * 2;
private _font = _l get "font";
private _fs = _l get "fontSmall";
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _gw = _bw - 0.012;
private _rowH = _font * 1.5;
private _land = _l get "landscape";

// Porteurs d'ATAK du camp (sauf moi), vivants.
private _feeds = allPlayers select { _x isNotEqualTo player && {alive _x} && {side group _x isEqualTo side group player} && {[_x] call comspec_atak_native_fnc_hasDevice} };
private _cams = ["list"] call comspec_atak_native_fnc_camAction;
_cams = [_cams, [], { player distance _x }, "ASCEND"] call BIS_fnc_sortBy;
private _target = _s getOrDefault ["livecamTarget", ""];
private _unit = objectFromNetId _target;
if (isNull _unit || {!(_unit in _feeds) && {!(_unit in _cams)}}) then {
    _unit = _feeds param [0, _cams param [0, objNull]];
    _target = if (isNull _unit) then { "" } else { netId _unit };
    _s set ["livecamTarget", _target];
};
private _fixed = _unit in _cams;

// Liste (à gauche en horizontal, en haut en vertical) et image.
private _listRect = if (_land) then { [_pad, _pad, _gw * 0.3, _bh - 2 * _pad] } else { [_pad, _pad, _gw - 2 * _pad, (_bh * 0.28) min (_rowH * 5)] };
private _list = ["COMSPEC_RscListBox", _listRect] call comspec_atak_native_fnc_pageCtrl;
_list ctrlSetFontHeight _fs;
{
    private _i = _list lbAdd ((format ["%1 · %2 m", [_x, true] call comspec_atak_native_fnc_unitCallsign, round (player distance _x)]) call comspec_atak_native_fnc_tr);
    _list lbSetData [_i, netId _x];
    _list lbSetPicture [_i, "\z\comspec_atak_native\addons\main\data\app_photos.paa"];
    if (_x isEqualTo _unit) then { _list lbSetCurSel _i; };
} forEach _feeds;
if ((count _feeds) isEqualTo 0) then { _list lbAdd (("Aucun autre ATAK en ligne") call comspec_atak_native_fnc_tr); };
// Caméras fixes du camp : nom, distance, état.
private _hdr = _list lbAdd (("CAMÉRAS FIXES") call comspec_atak_native_fnc_tr);
_list lbSetColor [_hdr, [0.36, 0.78, 0.42, 1]];
{
    private _st = ["status", _x] call comspec_atak_native_fnc_camAction;
    private _i = _list lbAdd ((format ["%1 · %2 m · %3", _x getVariable ["COMSPEC_ATAK_CamName", "CAM"], round (player distance _x), _st select 1]) call comspec_atak_native_fnc_tr);
    _list lbSetData [_i, netId _x];
    _list lbSetColor [_i, _st select 2];
    _list lbSetPicture [_i, "\z\comspec_atak_native\addons\main\data\item_camera.paa"];
    _list lbSetTooltip (([_i, format ["Posée par %1 (%2)", _x getVariable ["COMSPEC_ATAK_CamOwner", "?"], _x getVariable ["COMSPEC_ATAK_CamGroup", "?"]]]) call comspec_atak_native_fnc_tr);
    if (_x isEqualTo _unit) then { _list lbSetCurSel _i; };
} forEach _cams;
if ((count _cams) isEqualTo 0) then { private _i = _list lbAdd (("Aucune caméra posée (ACE : Téléphone ATAK > Caméras)") call comspec_atak_native_fnc_tr); _list lbSetColor [_i, [0.54, 0.6, 0.58, 1]]; };
_list ctrlAddEventHandler ["LBSelChanged", {
    params ["_c", "_i"];
    private _id = _c lbData _i;
    if (_id isEqualTo "") exitWith {};
    private _st = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
    if ((_st getOrDefault ["livecamTarget", ""]) isEqualTo _id) exitWith {};
    _st set ["livecamTarget", _id];
    _st set ["livecamPan", 0];
    _st set ["livecamRename", false];
    [{ ["LIVECAM"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
}];

private _vx = if (_land) then { _pad * 2 + _gw * 0.3 } else { _pad };
private _vy = if (_land) then { _pad } else { _pad * 2 + (_listRect select 3) };
private _vw = _gw - _vx - _pad;
private _btnH = _rowH;
private _vh = _bh - _vy - _pad * 2 - _btnH;
private _bg = ["COMSPEC_RscPanel", [_vx, _vy, _vw, _vh]] call comspec_atak_native_fnc_pageCtrl;
_bg ctrlSetBackgroundColor [0, 0, 0, 1];
if (isNull _unit) exitWith {
    private _t = ["COMSPEC_RscStructuredText", [_vx, _vy + _vh * 0.4, _vw, _rowH * 2]] call comspec_atak_native_fnc_pageCtrl;
    _t ctrlSetStructuredText parseText (("<t align='center' color='#8a9a93'>Pas de flux : aucun porteur d'ATAK du camp en ligne ni caméra fixe posée.</t>") call comspec_atak_native_fnc_tr);
    true
};
private _camSt = if (_fixed) then { ["status", _unit] call comspec_atak_native_fnc_camAction } else { ["OK", "", []] };
if ((_camSt select 0) isNotEqualTo "OK") exitWith {
    private _why = switch (_camSt select 0) do {
        case "DEAD": { "Caméra détruite : récupérez l'épave (ACE sur la caméra)." };
        case "JAM": { "Brouillage dans la zone de la caméra." };
        case "NONET": { "Pas de réseau sur votre téléphone." };
        default { "Aucun ATAK du camp à portée de la caméra : rapprochez-vous ou laissez un équipier près d'elle." };
    };
    private _t = ["COMSPEC_RscStructuredText", [_vx, _vy + _vh * 0.35, _vw, _rowH * 3]] call comspec_atak_native_fnc_pageCtrl;
    _t ctrlSetStructuredText parseText ((format ["<t align='center' font='RobotoCondensedBold' color='#e0a33a'>%1 · %2</t><br/><t align='center' size='0.85' color='#8a9a93'>%3</t>",
        _unit getVariable ["COMSPEC_ATAK_CamName", "CAM"], toUpper (_camSt select 1), _why]) call comspec_atak_native_fnc_tr);
    true
};
// Liaison du flux à travers les relais : sans elle, pas d'image.
private _link = ["eval", _unit] call comspec_atak_native_fnc_feedLink;
if ((_link get "res") isEqualTo 0) exitWith {
    private _t = ["COMSPEC_RscStructuredText", [_vx, _vy + _vh * 0.35, _vw, _rowH * 3]] call comspec_atak_native_fnc_pageCtrl;
    _t ctrlSetStructuredText parseText ((format ["<t align='center' font='RobotoCondensedBold' color='#e0a33a'>FLUX COUPÉ</t><br/><t align='center' size='0.85' color='#8a9a93'>%1</t>",
        _link get "why"]) call comspec_atak_native_fnc_tr);
    // On guette le retour de la liaison.
    missionNamespace setVariable ["COMSPEC_ATAK_FeedPfh", [{
        if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isNotEqualTo "LIVECAM") exitWith {};
        private _st = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
        private _l = ["eval", objectFromNetId (_st getOrDefault ["livecamTarget", ""])] call comspec_atak_native_fnc_feedLink;
        if ((_l get "res") > 0) then { [{ ["LIVECAM"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame; };
    }, 3] call CBA_fnc_addPerFrameHandler];
    true
};
private _mode = _s getOrDefault ["livecamMode", 0];
private _pan = _s getOrDefault ["livecamPan", 0];
if (_fixed) then { [_unit, _mode, _pan] call comspec_atak_native_fnc_camView; } else { [_unit, _mode] call comspec_atak_native_fnc_livecamStart; };
// Définition de l'image selon le débit (512 px en HD jusqu'à 64 px au minimum : image nettement pixelisée).
private _res = _link get "res";
private _pic = ["COMSPEC_RscPhone", [_vx, _vy, _vw, _vh], format ["#(argb,%1,%1,1)r2t(comspec_livecam,1.0)", _res]] call comspec_atak_native_fnc_pageCtrl;
// Décrochage : voile noir « image perdue » posé par moments, d'autant plus souvent que la liaison est mauvaise.
private _drop = ["COMSPEC_RscStructuredText", [_vx, _vy, _vw, _vh]] call comspec_atak_native_fnc_pageCtrl;
_drop ctrlSetBackgroundColor [0, 0, 0, 0.92];
_drop ctrlSetStructuredText parseText ((format ["<br/><br/><t align='center' font='RobotoCondensedBold' color='#e0a33a'>SIGNAL INSTABLE</t><br/><t align='center' size='0.8' color='#8a9a93'>mise en mémoire tampon · %1 kbit/s</t>", _link get "kbps"]) call comspec_atak_native_fnc_tr);
_drop ctrlShow false;
uiNamespace setVariable ["COMSPEC_ATAK_FeedDrop", _drop];
// Bandeau de liaison : palier, débit, pertes, relais de la source et du téléphone.
(_link get "up") params ["", "", "_upRelay"];
(_link get "down") params ["", "", "_downRelay"];
private _path = (([_upRelay, _downRelay] select { _x isNotEqualTo "" }) arrayIntersect ([_upRelay, _downRelay] select { _x isNotEqualTo "" })) joinString " → ";
private _tierCol = createHashMapFromArray [["HD", "#5bc76b"], ["SD", "#c9d4cf"], ["BAS", "#f2ab33"], ["MINIMAL", "#e5483a"]];
private _netBar = ["COMSPEC_RscStructuredText", [_vx + _pad / 2, _vy + _vh - _rowH * 1.1, _vw - _pad, _rowH]] call comspec_atak_native_fnc_pageCtrl;
_netBar ctrlSetFontHeight (_fs * 0.85);
_netBar ctrlSetStructuredText parseText ((format ["<t align='right'><t font='RobotoCondensedBold' color='%1'>%2</t> <t color='#c9d4cf'>· %3 · perte %4 %%%5</t></t>",
    _tierCol getOrDefault [_link get "tier", "#c9d4cf"], _link get "tier",
    [format ["%1 kbit/s", _link get "kbps"], format ["%1 Mbit/s", ((_link get "kbps") / 1000) toFixed 1]] select ((_link get "kbps") >= 1000),
    _link get "loss", ["", format [" · via %1", _path]] select (_path isNotEqualTo "")]) call comspec_atak_native_fnc_tr);
// Toutes les 0,5 s : décrochage tiré au sort ; toutes les 3 s : palier réévalué, page redessinée s'il change.
missionNamespace setVariable ["COMSPEC_ATAK_FeedPfh", [{
    params ["_args"];
    _args params ["_dropP", "_tier", "_next"];
    private _c = uiNamespace getVariable ["COMSPEC_ATAK_FeedDrop", controlNull];
    if (isNull _c) exitWith {};
    _c ctrlShow ((random 1) < _dropP);
    if (diag_tickTime < _next) exitWith {};
    _args set [2, diag_tickTime + 3];
    private _st = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
    if ((_st getOrDefault ["activePage", ""]) isNotEqualTo "LIVECAM") exitWith {};
    private _l = ["eval", objectFromNetId (_st getOrDefault ["livecamTarget", ""])] call comspec_atak_native_fnc_feedLink;
    _args set [0, _l get "drop"];
    if ((_l get "tier") isNotEqualTo _tier) then {
        _args set [1, _l get "tier"];
        ["INFO", format ["Live cam : qualité %1 (%2 kbit/s)", _l get "tier", _l get "kbps"], 3, 20] call comspec_atak_native_fnc_notify;
        [{ ["LIVECAM"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
    };
}, 0.5, [_link get "drop", _link get "tier", diag_tickTime + 3]] call CBA_fnc_addPerFrameHandler];
private _osd = ["COMSPEC_RscStructuredText", [_vx + _pad / 2, _vy + _pad / 2, _vw - _pad, _rowH * 1.6]] call comspec_atak_native_fnc_pageCtrl;
if (!_fixed) then {
    _osd ctrlSetStructuredText parseText ((format ["<t font='RobotoCondensedBold' color='#e5483a'>● LIVE</t>  <t font='RobotoCondensedBold'>%1</t><br/><t size='0.8' color='#c9d4cf'>%2 · %3 m · cap %4°</t>",
        [_unit, true] call comspec_atak_native_fnc_unitCallsign, [getPosASL _unit, 8] call comspec_atak_native_fnc_gridRef, round (player distance _unit), round getDir _unit]) call comspec_atak_native_fnc_tr);
} else {
    _osd ctrlSetStructuredText parseText ((format ["<t font='RobotoCondensedBold' color='#e5483a'>● LIVE</t>  <t font='RobotoCondensedBold'>%1</t><br/><t size='0.8' color='#c9d4cf'>%2 · %3 m</t>",
        _unit getVariable ["COMSPEC_ATAK_CamName", "CAM"], [getPosASL _unit, 8] call comspec_atak_native_fnc_gridRef, round (player distance _unit)]) call comspec_atak_native_fnc_tr);
    // Panoramique local (la caméra posée ne bouge pas) et renommage.
    private _bs = _btnH * 1.2;
    {
        _x params ["_txt", "_dx", "_step"];
        private _b = ["COMSPEC_RscButton", [_dx, _vy + (_vh - _bs) / 2, _bs, _bs], _txt] call comspec_atak_native_fnc_pageCtrl;
        _b ctrlSetBackgroundColor [0, 0, 0, 0.45];
        _b ctrlAddEventHandler ["ButtonClick", compile format ["private _s = uiNamespace getVariable ['COMSPEC_ATAK_State', createHashMap];
            _s set ['livecamPan', (((_s getOrDefault ['livecamPan', 0]) + %1 + 540) mod 360) - 180];
            private _c = missionNamespace getVariable ['COMSPEC_ATAK_LivecamCam', objNull];
            if (!isNull _c) then { private _p = _s get 'livecamPan'; _c setVectorDirAndUp [[sin _p, cos _p, 0], [0, 0, 1]]; };", _step]];
    } forEach [["◀", _vx + _pad / 2, -30], ["▶", _vx + _vw - _bs - _pad / 2, 30]];
    private _ren = _s getOrDefault ["livecamRename", false];
    if (_ren) then {
        private _ew = _vw * 0.55;
        private _e = ["COMSPEC_RscEdit", [_vx + _pad / 2, _vy + _vh - _btnH - _pad / 2, _ew, _btnH], _unit getVariable ["COMSPEC_ATAK_CamName", ""]] call comspec_atak_native_fnc_pageCtrl;
        uiNamespace setVariable ["COMSPEC_ATAK_CamNameEdit", _e];
        ctrlSetFocus _e;
        private _ok = ["COMSPEC_RscButtonPrimary", [_vx + _pad + _ew, _vy + _vh - _btnH - _pad / 2, _vw * 0.2, _btnH], "OK"] call comspec_atak_native_fnc_pageCtrl;
        _ok ctrlSetFontHeight _fs;
        _ok ctrlAddEventHandler ["ButtonClick", {
            private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
            private _e = uiNamespace getVariable ["COMSPEC_ATAK_CamNameEdit", controlNull];
            if (!isNull _e) then { ["setName", objectFromNetId (_s getOrDefault ["livecamTarget", ""]), ctrlText _e] call comspec_atak_native_fnc_camAction; };
            _s set ["livecamRename", false];
            [{ ["LIVECAM"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
        }];
    } else {
        private _rb = ["COMSPEC_RscButton", [_vx + _vw - _vw * 0.25 - _pad / 2, _vy + _vh - _btnH - _pad / 2, _vw * 0.25, _btnH], "RENOMMER"] call comspec_atak_native_fnc_pageCtrl;
        _rb ctrlSetFontHeight _fs;
        _rb ctrlSetBackgroundColor [0, 0, 0, 0.45];
        _rb ctrlAddEventHandler ["ButtonClick", {
            (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) set ["livecamRename", true];
            [{ ["LIVECAM"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
        }];
    };
};
uiNamespace setVariable ["COMSPEC_ATAK_LivecamOsd", _osd];

// Modes de vision
private _modes = [["JOUR", 0], ["VISION NOCTURNE", 1], ["THERMIQUE", 2]];
private _mw = (_vw - _pad) / 3;
{
    _x params ["_t", "_v"];
    private _b = ["COMSPEC_RscButton", [_vx + _forEachIndex * (_mw + _pad / 2), _vy + _vh + _pad, _mw, _btnH], _t] call comspec_atak_native_fnc_pageCtrl;
    _b ctrlSetFontHeight _fs;
    if (_v isEqualTo _mode) then { _b ctrlSetBackgroundColor [0.36, 0.78, 0.42, 0.9]; _b ctrlSetTextColor [0.03, 0.05, 0.04, 1]; };
    _b ctrlAddEventHandler ["ButtonClick", compile format ["(uiNamespace getVariable ['COMSPEC_ATAK_State', createHashMap]) set ['livecamMode', %1]; 'comspec_livecam' setPiPEffect [%1];
        [{ ['LIVECAM'] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;", _v]];
} forEach _modes;
true
