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
    Drones du camp (fn_livecamUav) : la nacelle d'un drone allié en vol, piloté au terminal UAV ou par un téléphone,
    comme le flux de BCE ; l'image passe par la station de son opérateur. Lecture seule : réticule, point visé, cadres
    sur les cibles, MARQUER LA CIBLE (LOCK suivi) et MARQUER LE POINT (marqueur de la palette), sans commande du drone.
    Partage (fn_camShare) : par défaut, une caméra n'est listée que si elle est à moi ou partagée avec moi. Le bouton
    PARTAGER sous la liste ouvre, à la place de l'image, le choix de la méthode (NFC, Bluetooth, relais, chacune avec
    ses conditions) puis du destinataire (un ATAK ou TOUS). « Ma caméra » (mon téléphone) est en tête de liste.
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

// Porteurs d'ATAK du camp (sauf moi), vivants, dont la caméra m'est partagée ; ma propre caméra en tête, pour la partager.
private _shareOn = ["required"] call comspec_atak_native_fnc_camShare;
private _feeds = allPlayers select { _x isNotEqualTo player && {alive _x} && {side group _x isEqualTo side group player} && {[_x] call comspec_atak_native_fnc_hasDevice} && {["can", _x] call comspec_atak_native_fnc_camShare} };
if (_shareOn && {[player] call comspec_atak_native_fnc_hasDevice}) then { _feeds = [player] + _feeds; };
private _cams = ["list"] call comspec_atak_native_fnc_camAction;
// Drones du camp en vol (nacelle comme dans BCE, fn_livecamUav) : regardés sans les piloter ni les appairer.
private _uavs = allUnitsUAV select { alive _x && {!isNull driver _x || {!isNull gunner _x}} && {(side group _x) isEqualTo (side group player)} };
_uavs = [_uavs, [], { player distance _x }, "ASCEND"] call BIS_fnc_sortBy;
_cams = [_cams, [], { player distance _x }, "ASCEND"] call BIS_fnc_sortBy;
private _target = _s getOrDefault ["livecamTarget", ""];
private _unit = objectFromNetId _target;
if (isNull _unit || {!(_unit in _feeds) && {!(_unit in _cams)} && {!(_unit in _uavs)}}) then {
    _unit = (_feeds - [player]) param [0, _cams param [0, _feeds param [0, objNull]]];
    _target = if (isNull _unit) then { "" } else { netId _unit };
    _s set ["livecamTarget", _target];
};
private _fixed = _unit in _cams;
private _isUav = _unit in _uavs;

// Liste (à gauche en horizontal, en haut en vertical) et image.
private _listRect = if (_land) then { [_pad, _pad, _gw * 0.3, _bh - 2 * _pad] } else { [_pad, _pad, _gw - 2 * _pad, (_bh * 0.28) min (_rowH * 5)] };
// Bouton PARTAGER sous la liste (partage obligatoire seulement).
private _shareRect = [];
if (_shareOn) then {
    _shareRect = [_listRect select 0, (_listRect select 1) + (_listRect select 3) - _rowH, _listRect select 2, _rowH];
    _listRect set [3, (_listRect select 3) - _rowH - _pad / 2];
};
private _list = ["COMSPEC_RscListBox", _listRect] call comspec_atak_native_fnc_pageCtrl;
_list ctrlSetFontHeight _fs;
{
    private _i = _list lbAdd (if (_x isEqualTo player) then { ("Ma caméra (moi)") call comspec_atak_native_fnc_tr } else {
        (format ["%1 · %2 m", [_x, true] call comspec_atak_native_fnc_unitCallsign, round (player distance _x)]) call comspec_atak_native_fnc_tr }); // i18n:brut (déjà traduit)
    _list lbSetData [_i, netId _x];
    _list lbSetPicture [_i, "\z\comspec_atak_native\addons\main\data\app_photos.paa"];
    if (_x isEqualTo _unit) then { _list lbSetCurSel _i; };
} forEach _feeds;
if (((count _feeds) - ([0, 1] select (player in _feeds))) isEqualTo 0) then {
    private _i = _list lbAdd ((["Aucun autre ATAK en ligne", "Aucune caméra d'équipier partagée avec vous"] select _shareOn) call comspec_atak_native_fnc_tr);
    _list lbSetColor [_i, [0.54, 0.6, 0.58, 1]];
};
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
if ((count _cams) isEqualTo 0) then { private _i = _list lbAdd ((["Aucune caméra posée (ACE : Téléphone ATAK > Caméras)", "Aucune caméra posée ou partagée avec vous (ACE : Téléphone ATAK > Caméras)"] select _shareOn) call comspec_atak_native_fnc_tr); _list lbSetColor [_i, [0.54, 0.6, 0.58, 1]]; };
// Drones du camp : nom, distance, opérateur.
private _hu = _list lbAdd (("DRONES DU CAMP") call comspec_atak_native_fnc_tr);
_list lbSetColor [_hu, [0.36, 0.78, 0.42, 1]];
{
    private _op = (UAVControl _x) param [0, objNull];
    private _i = _list lbAdd ((format ["%1 · %2 m · %3", [_x, [], "NAME"] call comspec_atak_native_fnc_droneOsd, round (player distance _x),
        [[_op, true] call comspec_atak_native_fnc_unitCallsign, "autonome"] select (!(_op isEqualType objNull) || {isNull _op})]) call comspec_atak_native_fnc_tr);
    _list lbSetData [_i, netId _x];
    _list lbSetPicture [_i, [_x, [], "ICON"] call comspec_atak_native_fnc_droneOsd];
    if (_x isEqualTo _unit) then { _list lbSetCurSel _i; };
} forEach _uavs;
if ((count _uavs) isEqualTo 0) then { private _i = _list lbAdd (("Aucun drone du camp en vol") call comspec_atak_native_fnc_tr); _list lbSetColor [_i, [0.54, 0.6, 0.58, 1]]; };
_list ctrlAddEventHandler ["LBSelChanged", {
    params ["_c", "_i"];
    private _id = _c lbData _i;
    if (_id isEqualTo "") exitWith {};
    private _st = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
    if ((_st getOrDefault ["livecamTarget", ""]) isEqualTo _id) exitWith {};
    _st set ["livecamTarget", _id];
    _st set ["livecamPan", 0];
    _st set ["livecamRename", false];
    _st set ["livecamShare", false];
    [{ ["LIVECAM"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
}];
private _canShare = ["canShare", _unit] call comspec_atak_native_fnc_camShare;
private _sharing = _shareOn && {_canShare} && {_s getOrDefault ["livecamShare", false]};
if (_shareOn) then {
    private _sb = [["COMSPEC_RscButton", "COMSPEC_RscButtonPrimary"] select _sharing, _shareRect, ["PARTAGER", "FERMER LE PARTAGE"] select _sharing] call comspec_atak_native_fnc_pageCtrl;
    _sb ctrlSetFontHeight _fs;
    _sb ctrlEnable _canShare;
    _sb ctrlSetTooltip (([
        "Seul le porteur d'un téléphone peut partager sa caméra ; une caméra fixe se partage par qui y a accès.",
        "Partager cette caméra par NFC, Bluetooth ou relais, à un ATAK ou à TOUS"] select _canShare) call comspec_atak_native_fnc_tr);
    _sb ctrlAddEventHandler ["ButtonClick", {
        private _st = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
        _st set ["livecamShare", !(_st getOrDefault ["livecamShare", false])];
        [{ ["LIVECAM"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
    }];
};

private _vx = if (_land) then { _pad * 2 + _gw * 0.3 } else { _pad };
private _vy = if (_land) then { _pad } else { _pad * 2 + (_listRect select 3) };
private _vw = _gw - _vx - _pad;
private _btnH = _rowH;
private _vh = _bh - _vy - _pad * 2 - _btnH;
// Panneau de partage à la place de l'image : méthode (avec ses conditions), destinataire, envoi.
if (_sharing) exitWith {
    private _ph = _bh - _vy - _pad;
    private _pbg = ["COMSPEC_RscPanel", [_vx, _vy, _vw, _ph]] call comspec_atak_native_fnc_pageCtrl;
    _pbg ctrlSetBackgroundColor [0.05, 0.07, 0.06, 1];
    private _m = _s getOrDefault ["shareMethod", "NFC"];
    private _methods = ["methods"] call comspec_atak_native_fnc_camShare;
    private _name = if (_unit isEqualTo player) then { "ma caméra" } else { _unit getVariable ["COMSPEC_ATAK_CamName", "CAM"] };
    private _acc = ["access", _unit] call comspec_atak_native_fnc_camShare;
    private _accTxt = if ((count _acc) isEqualTo 0) then { "aucun (vous seul)" } else {
        (_acc apply {
            _x params [["_id", ""], ["_how", ""]];
            private _who = if (_id isEqualTo "ALL") then { "TOUS" } else {
                private _i = allPlayers findIf { getPlayerUID _x isEqualTo _id || {netId _x isEqualTo _id} };
                if (_i < 0) then { "hors ligne" } else { [allPlayers select _i, true] call comspec_atak_native_fnc_unitCallsign }
            };
            format ["%1 (%2)", _who, (((_methods select { (_x select 0) isEqualTo _how }) param [0, ["", _how]]) select 1)]
        }) joinString ", "
    };
    private _y = _vy + _pad / 2;
    private _hd = ["COMSPEC_RscStructuredText", [_vx + _pad / 2, _y, _vw - _pad, _rowH * 1.9]] call comspec_atak_native_fnc_pageCtrl;
    _hd ctrlSetStructuredText parseText ((format ["<t font='COMSPEC_BarlowBold'>PARTAGER %1</t><br/><t size='0.8' color='#8a9a93'>Accès : %2</t>", toUpper _name, _accTxt]) call comspec_atak_native_fnc_tr);
    _y = _y + _rowH * 2;
    // Méthodes : NFC, BLUETOOTH, RELAIS.
    private _mw = (_vw - _pad * 2) / 3;
    {
        _x params ["_code", "_title"];
        private _b = ["COMSPEC_RscButton", [_vx + _pad / 2 + _forEachIndex * (_mw + _pad / 2), _y, _mw, _btnH], _title] call comspec_atak_native_fnc_pageCtrl;
        _b ctrlSetFontHeight _fs;
        if (_code isEqualTo _m) then { _b ctrlSetBackgroundColor [0.36, 0.78, 0.42, 0.9]; _b ctrlSetTextColor [0.03, 0.05, 0.04, 1]; };
        _b ctrlAddEventHandler ["ButtonClick", compile format ["(uiNamespace getVariable ['COMSPEC_ATAK_State', createHashMap]) set ['shareMethod', '%1'];
            [{ ['LIVECAM'] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;", _code]];
    } forEach _methods;
    _y = _y + _btnH + _pad / 2;
    // Principe de la méthode, et ce qui manque à mon téléphone.
    private _mine = ["mine", _m] call comspec_atak_native_fnc_camShare;
    private _info = ["COMSPEC_RscStructuredText", [_vx + _pad / 2, _y, _vw - _pad, _rowH * 3.2]] call comspec_atak_native_fnc_pageCtrl;
    _info ctrlSetFontHeight (_fs * 0.9);
    _info ctrlSetStructuredText parseText ((format ["<t size='0.85' color='#c9d4cf'>%1</t>%2<br/><t size='0.75' color='#8a9a93'>Le partage donne l'accès. L'image suit la liaison de la caméra : une caméra fixe se regarde par son Wi-Fi à portée, même sans réseau ; sinon par le réseau et les relais.</t>",
        ((_methods select { (_x select 0) isEqualTo _m }) param [0, ["", "", ""]]) select 2,
        ["", format ["<br/><t size='0.85' color='#e0a33a'>%1</t>", _mine]] select (_mine isNotEqualTo "")]) call comspec_atak_native_fnc_tr);
    _y = _y + _rowH * 3.3;
    // Destinataires : TOUS, puis les ATAK du camp (joignables en premier, sinon la raison).
    private _rows = ["candidates", [_unit, _m]] call comspec_atak_native_fnc_camShare;
    _rows = (_rows select { _x select 2 }) + (_rows select { !(_x select 2) });
    private _to = _s getOrDefault ["shareTo", "ALL"];
    private _lh = ((_vy + _ph) - _y - _btnH - _pad) max (_rowH * 2);
    private _rl = ["COMSPEC_RscListBox", [_vx + _pad / 2, _y, _vw - _pad, _lh]] call comspec_atak_native_fnc_pageCtrl;
    _rl ctrlSetFontHeight _fs;
    private _nOk = count (_rows select { _x select 2 });
    private _iAll = _rl lbAdd ((if (_m isEqualTo "RELAY") then { "TOUS · tout le camp" } else { format ["TOUS · %1 ATAK à portée", _nOk] }) call comspec_atak_native_fnc_tr);
    _rl lbSetData [_iAll, "ALL"];
    _rl lbSetColor [_iAll, [0.36, 0.78, 0.42, 1]];
    if (_to isEqualTo "ALL") then { _rl lbSetCurSel _iAll; };
    {
        _x params ["_u", "_d", "_ok", "_why"];
        private _id = getPlayerUID _u;
        if (_id isEqualTo "") then { _id = netId _u; };
        private _i = _rl lbAdd ((format ["%1 · %2 m · %3", [_u, true] call comspec_atak_native_fnc_unitCallsign, round _d, ["joignable", _why] select !_ok]) call comspec_atak_native_fnc_tr);
        _rl lbSetData [_i, _id];
        if (!_ok) then { _rl lbSetColor [_i, [0.54, 0.6, 0.58, 1]]; };
        if (_id isEqualTo _to) then { _rl lbSetCurSel _i; };
    } forEach _rows;
    if ((count _rows) isEqualTo 0) then {
        private _i = _rl lbAdd (([format ["Aucun ATAK du camp à proximité pour %1", (((_methods select { (_x select 0) isEqualTo _m }) param [0, ["", _m]]) select 1)], "Aucun autre ATAK du camp"] select (_m isEqualTo "RELAY")) call comspec_atak_native_fnc_tr);
        _rl lbSetColor [_i, [0.54, 0.6, 0.58, 1]];
    };
    _rl ctrlAddEventHandler ["LBSelChanged", {
        params ["_c", "_i"];
        private _id = _c lbData _i;
        if (_id isNotEqualTo "") then { (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) set ["shareTo", _id]; };
    }];
    // Actions : ENVOYER, et RETIRER LES ACCÈS pour le poseur (ou le porteur du téléphone).
    private _by = _vy + _ph - _btnH - _pad / 2;
    private _owner = ["owner", _unit] call comspec_atak_native_fnc_camShare;
    private _bw2 = (_vw - _pad * 1.5) / 2;
    private _send = ["COMSPEC_RscButtonPrimary", [_vx + _pad / 2, _by, [_vw - _pad, _bw2] select (_owner && {(count _acc) > 0}), _btnH], "ENVOYER"] call comspec_atak_native_fnc_pageCtrl;
    _send ctrlSetFontHeight _fs;
    _send ctrlEnable (_mine isEqualTo "");
    _send ctrlAddEventHandler ["ButtonClick", {
        private _st = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
        ["send", [objectFromNetId (_st getOrDefault ["livecamTarget", ""]), _st getOrDefault ["shareMethod", "NFC"], _st getOrDefault ["shareTo", "ALL"]]] call comspec_atak_native_fnc_camShare;
        [{ ["LIVECAM"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
    }];
    if (_owner && {(count _acc) > 0}) then {
        private _rv = ["COMSPEC_RscButton", [_vx + _pad + _bw2, _by, _bw2, _btnH], "RETIRER LES ACCÈS"] call comspec_atak_native_fnc_pageCtrl;
        _rv ctrlSetFontHeight _fs;
        _rv ctrlAddEventHandler ["ButtonClick", {
            ["revoke", objectFromNetId ((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["livecamTarget", ""])] call comspec_atak_native_fnc_camShare;
            [{ ["LIVECAM"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
        }];
    };
    true
};
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
    _t ctrlSetStructuredText parseText ((format ["<t align='center' font='COMSPEC_BarlowBold' color='#e0a33a'>%1 · %2</t><br/><t align='center' size='0.85' color='#8a9a93'>%3</t>",
        _unit getVariable ["COMSPEC_ATAK_CamName", "CAM"], toUpper (_camSt select 1), _why]) call comspec_atak_native_fnc_tr);
    true
};
// Liaison du flux à travers les relais : sans elle, pas d'image.
private _link = ["eval", _unit] call comspec_atak_native_fnc_feedLink;
if ((_link get "res") isEqualTo 0) exitWith {
    private _t = ["COMSPEC_RscStructuredText", [_vx, _vy + _vh * 0.35, _vw, _rowH * 3]] call comspec_atak_native_fnc_pageCtrl;
    _t ctrlSetStructuredText parseText ((format ["<t align='center' font='COMSPEC_BarlowBold' color='#e0a33a'>FLUX COUPÉ</t><br/><t align='center' size='0.85' color='#8a9a93'>%1</t>",
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
switch (true) do {
    case _fixed: { [_unit, _mode, _pan] call comspec_atak_native_fnc_camView; };
    case _isUav: { [_unit, _mode] call comspec_atak_native_fnc_livecamUav; };
    default { [_unit, _mode] call comspec_atak_native_fnc_livecamStart; };
};
// Définition de l'image selon le débit (512 px en HD jusqu'à 64 px au minimum : image nettement pixelisée).
private _res = _link get "res";
private _pic = ["COMSPEC_RscPhone", [_vx, _vy, _vw, _vh], format ["#(argb,%1,%1,1)r2t(comspec_livecam,1.0)", _res]] call comspec_atak_native_fnc_pageCtrl;
// Décrochage : voile noir « image perdue » posé par moments, d'autant plus souvent que la liaison est mauvaise.
private _drop = ["COMSPEC_RscStructuredText", [_vx, _vy, _vw, _vh]] call comspec_atak_native_fnc_pageCtrl;
_drop ctrlSetBackgroundColor [0, 0, 0, 0.92];
_drop ctrlSetStructuredText parseText ((format ["<br/><br/><t align='center' font='COMSPEC_BarlowBold' color='#e0a33a'>SIGNAL INSTABLE</t><br/><t align='center' size='0.8' color='#8a9a93'>mise en mémoire tampon · %1 kbit/s</t>", _link get "kbps"]) call comspec_atak_native_fnc_tr);
_drop ctrlShow false;
uiNamespace setVariable ["COMSPEC_ATAK_FeedDrop", _drop];
// Bandeau de liaison : palier, débit, pertes, relais de la source et du téléphone.
(_link get "up") params ["", "", "_upRelay"];
(_link get "down") params ["", "", "_downRelay"];
private _path = (([_upRelay, _downRelay] select { _x isNotEqualTo "" }) arrayIntersect ([_upRelay, _downRelay] select { _x isNotEqualTo "" })) joinString " → ";
private _tierCol = createHashMapFromArray [["HD", "#5bc76b"], ["SD", "#c9d4cf"], ["BAS", "#f2ab33"], ["MINIMAL", "#e5483a"]];
private _netBar = ["COMSPEC_RscStructuredText", [_vx + _pad / 2, _vy + _vh - _rowH * 1.1, _vw - _pad, _rowH]] call comspec_atak_native_fnc_pageCtrl;
_netBar ctrlSetFontHeight (_fs * 0.85);
_netBar ctrlSetStructuredText parseText ((format ["<t align='right'><t font='COMSPEC_BarlowBold' color='%1'>%2</t> <t color='#c9d4cf'>· %3 · perte %4 %%%5</t></t>",
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
        // Pas de notification ici : le changement de palier est déjà annoncé par la boucle du Live cam (fn_videoLink "adapt").
        [{ ["LIVECAM"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
    };
}, 0.5, [_link get "drop", _link get "tier", diag_tickTime + 3]] call CBA_fnc_addPerFrameHandler];
private _osd = ["COMSPEC_RscStructuredText", [_vx + _pad / 2, _vy + _pad / 2, _vw - _pad, _rowH * 2.3]] call comspec_atak_native_fnc_pageCtrl;
if (_isUav) then {
    _osd ctrlSetStructuredText parseText ((format ["<t font='COMSPEC_BarlowBold' color='#e5483a'>● LIVE</t>  <t font='COMSPEC_BarlowBold'>%1</t><br/><t size='0.8' color='#c9d4cf'>%2 · %3 m</t>",
        [_unit, [], "NAME"] call comspec_atak_native_fnc_droneOsd, [getPosASL _unit, 8] call comspec_atak_native_fnc_gridRef, round (player distance _unit)]) call comspec_atak_native_fnc_tr);
};
// Drone du camp, en lecture seule (comme le flux de BCE) : réticule, point visé (rafraîchi par fn_livecamUav), cadres
// sur les cibles (fn_droneFlir « LIVECAM » : jaune sous le réticule, rouge LOCK marquées), MARQUER LA CIBLE (LOCK suivi
// sur la carte, fn_lockAction) et MARQUER LE POINT VISÉ, pour que le chef de groupe marque ce que l'opérateur regarde
// sans piloter le drone.
if (_isUav) then {
    ["build", [_vx, _vy, _vw, _vh], "LIVECAM", true] call comspec_atak_native_fnc_droneFlir;
    private _cx = _vx + _vw / 2;
    private _cy = _vy + _vh / 2;
    private _rw = _vw * 0.035;
    private _rh = _rw * (uiNamespace getVariable ["COMSPEC_ATAK_Ratio", pixelH / pixelW]);
    {
        private _r = ["COMSPEC_RscText", _x] call comspec_atak_native_fnc_pageCtrl;
        _r ctrlSetBackgroundColor [1, 1, 1, 0.85];
    } forEach [
        [_cx - _rw * 1.4, _cy - pixelH, _rw, pixelH * 2], [_cx + _rw * 0.4, _cy - pixelH, _rw, pixelH * 2],
        [_cx - pixelW, _cy - _rh * 1.4, pixelW * 2, _rh], [_cx - pixelW, _cy + _rh * 0.4, pixelW * 2, _rh]
    ];
    private _aim = ["COMSPEC_RscStructuredText", [_vx + _pad / 2, _vy + _vh - _btnH - _pad - _rowH, _vw - _pad, _rowH]] call comspec_atak_native_fnc_pageCtrl;
    _aim ctrlSetStructuredText parseText (("<t size='0.8' shadow='2' shadowColor='#000000'>VISÉ : …</t>") call comspec_atak_native_fnc_tr);
    uiNamespace setVariable ["COMSPEC_ATAK_LivecamAimOsd", _aim];
    private _lk = ["COMSPEC_RscButton", [_vx + _pad / 2, _vy + _vh - _btnH - _pad / 2, _vw * 0.3, _btnH], "MARQUER LA CIBLE"] call comspec_atak_native_fnc_pageCtrl;
    _lk ctrlSetFontHeight _fs;
    _lk ctrlSetBackgroundColor [0.45, 0.08, 0.06, 0.6];
    _lk ctrlSetTooltip (("Accroche la personne ou le véhicule sous le réticule (cadre jaune) : cadre LOCK rouge dans l'image et marqueur qui suit la cible sur la carte tant que vous regardez ce flux. Le drone n'est pas piloté.") call comspec_atak_native_fnc_tr);
    _lk ctrlAddEventHandler ["ButtonClick", { ["mark", "LIVECAM"] call comspec_atak_native_fnc_lockAction; }];
    private _mk = ["COMSPEC_RscButton", [_vx + _pad + _vw * 0.3, _vy + _vh - _btnH - _pad / 2, _vw * 0.3, _btnH], "MARQUER LE POINT"] call comspec_atak_native_fnc_pageCtrl;
    _mk ctrlSetFontHeight _fs;
    _mk ctrlSetBackgroundColor [0, 0, 0, 0.45];
    _mk ctrlSetTooltip (("Pose sur la carte un marqueur de la palette (mode MARQUEUR) au point sous le réticule. Le drone n'est pas piloté.") call comspec_atak_native_fnc_tr);
    _mk ctrlAddEventHandler ["ButtonClick", {
        if (diag_tickTime - (uiNamespace getVariable ["COMSPEC_ATAK_LivecamMarkT", -10]) < 2) exitWith {};
        uiNamespace setVariable ["COMSPEC_ATAK_LivecamMarkT", diag_tickTime];
        private _p = ["aim"] call comspec_atak_native_fnc_livecamUav;
        if (_p isEqualTo []) exitWith { ["WARNING", "Aucun point visé, le réticule montre le ciel", 3, 20] call comspec_atak_native_fnc_notify; };
        private _u = missionNamespace getVariable ["COMSPEC_ATAK_LivecamUav", objNull];
        private _m = [_p] call comspec_atak_native_fnc_markerDrop;
        if (_m isNotEqualTo "" && {!isNull _u}) then { _m setMarkerText format ["%1 (%2)", markerText _m, [_u, [], "NAME"] call comspec_atak_native_fnc_droneOsd]; };
    }];
};
if (!_fixed && {!_isUav}) then {
    _osd ctrlSetStructuredText parseText ((format ["<t font='COMSPEC_BarlowBold' color='#e5483a'>● LIVE</t>  <t font='COMSPEC_BarlowBold'>%1</t><br/><t size='0.8' color='#c9d4cf'>%2 · %3 m · cap %4°</t>",
        [_unit, true] call comspec_atak_native_fnc_unitCallsign, [getPosASL _unit, 8] call comspec_atak_native_fnc_gridRef, round (player distance _unit), round getDir _unit]) call comspec_atak_native_fnc_tr);
};
// Caméra fixe seulement : panoramique local (la caméra posée ne bouge pas) et renommage. Un drone du camp n'a ni l'un ni
// l'autre (sa nacelle suit le terminal de l'opérateur) : avant, il passait ici aussi et montrait ces boutons sans effet
// (retour de TTRD, 19/10 : « carré noir à gauche et à droite »).
if (_fixed) then {
    _osd ctrlSetStructuredText parseText ((format ["<t font='COMSPEC_BarlowBold' color='#e5483a'>● LIVE</t>  <t font='COMSPEC_BarlowBold'>%1</t><br/><t size='0.8' color='#c9d4cf'>%2 · %3 m</t>",
        _unit getVariable ["COMSPEC_ATAK_CamName", "CAM"], [getPosASL _unit, 8] call comspec_atak_native_fnc_gridRef, round (player distance _unit)]) call comspec_atak_native_fnc_tr);
    private _bs = _btnH * 1.2;
    {
        _x params ["_txt", "_dx", "_step"];
        private _b = ["COMSPEC_RscButton", [_dx, _vy + (_vh - _bs) / 2, _bs, _bs], _txt] call comspec_atak_native_fnc_pageCtrl;
        _b ctrlSetFont "COMSPEC_BarlowBold";
        _b ctrlSetFontHeight (_fs * 1.4);
        _b ctrlSetBackgroundColor [0, 0, 0, 0.45];
        _b ctrlSetTooltip ((["Tourner la vue de 30° à gauche", "Tourner la vue de 30° à droite"] select (_step > 0)) call comspec_atak_native_fnc_tr);
        _b ctrlAddEventHandler ["ButtonClick", compile format ["private _s = uiNamespace getVariable ['COMSPEC_ATAK_State', createHashMap];
            _s set ['livecamPan', (((_s getOrDefault ['livecamPan', 0]) + %1 + 540) mod 360) - 180];
            private _c = missionNamespace getVariable ['COMSPEC_ATAK_LivecamCam', objNull];
            if (!isNull _c) then { private _p = _s get 'livecamPan'; _c setVectorDirAndUp [[sin _p, cos _p, 0], [0, 0, 1]]; };", _step]];
    } forEach [["<", _vx + _pad / 2, -30], [">", _vx + _vw - _bs - _pad / 2, 30]];
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
