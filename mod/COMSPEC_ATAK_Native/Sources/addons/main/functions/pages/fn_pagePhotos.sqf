/* App Photos : onglet APPAREIL (viseur plein écran, légende, suivi des envois) et onglet BIBLIOTHÈQUE (photos du poste, retransmission). */
disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];
private _esc = { params ["_t"]; if !(_t isEqualType "") then { _t = str _t; }; [[_t, "<", "&lt;"] call CBA_fnc_replace, ">", "&gt;"] call CBA_fnc_replace };
private _lines = [];
{
    _x params [["_kind", ""], ["_title", ""], ["_text", ""], ["_grid", ""], ["_time", ""]];
    if ((toUpper _kind) isEqualTo "PHOTO") then {
        _lines pushBack format ["<t color='#8a9a93'>%1</t>  %2<br/>%3", [_time] call _esc, [_title] call _esc, [_text] call _esc];
    };
} forEach (missionNamespace getVariable ["COMSPEC_Athena_AlertInbox", []]);
reverse _lines;
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _tab = _s getOrDefault ["photoTab", "CAMERA"];
private _tabBtn = { params ["_t", "_k"]; [_t, compile format ["(uiNamespace getVariable ['COMSPEC_ATAK_State', createHashMap]) set ['photoTab', '%1']; if ('%1' isEqualTo 'LIB') then { ['list'] call comspec_atak_native_fnc_photoLibrary; }; [{ ['PHOTOS'] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;", _k], _tab isEqualTo _k] };
if (_tab isEqualTo "LIB" && {isNil { uiNamespace getVariable "COMSPEC_ATAK_PhotoLibAt" }}) then { ["list"] call comspec_atak_native_fnc_photoLibrary; };
private _lib = uiNamespace getVariable ["COMSPEC_ATAK_PhotoLib", []];
private _head = [["segment", "", [["APPAREIL", "CAMERA"] call _tabBtn, [format ["BIBLIOTHÈQUE (%1)", count _lib], "LIB"] call _tabBtn]]];
if (_tab isEqualTo "LIB") exitWith {
    private _sent = ((missionNamespace getVariable ["COMSPEC_Athena_PhotoUploaded", []]) select { _x isEqualType "" }) apply { toLower _x };
    private _bad = ((missionNamespace getVariable ["COMSPEC_Athena_PhotoFailed", []]) + (profileNamespace getVariable ["COMSPEC_Athena_PhotoDead", []])) select { _x isEqualType "" } apply { toLower _x };
    private _wait = (missionNamespace getVariable ["COMSPEC_Athena_PhotoPending", []]) apply { toLower str _x };
    private _known = uiNamespace getVariable "COMSPEC_ATAK_PhotoKnown";
    private _armed = uiNamespace getVariable ["COMSPEC_ATAK_PhotoDelArm", ["", -10]];
    private _isArmed = { params ["_k"]; (_armed select 0) isEqualTo _k && {diag_tickTime - (_armed select 1) <= 4} };
    private _rows = _head + [
        ["text", "<t color='#8a9a93'>Photos enregistrées sur ce poste (dossier COMSPEC et captures Arma), les plus récentes en premier. L'aperçu se fait sur Athena, onglet Photos, une fois la photo transmise.</t>"],
        ["buttons", [
            ["ACTUALISER", { ['list'] call comspec_atak_native_fnc_photoLibrary; [{ ['PHOTOS'] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame; }],
            ["TOUT RETRANSMETTRE", { ['sendAll'] call comspec_atak_native_fnc_photoLibrary; }, true, (count _lib) > 0],
            [["TOUT SUPPRIMER", "CONFIRMER : TOUT SUPPRIMER"] select (["ALL"] call _isArmed), { ['deleteAll'] call comspec_atak_native_fnc_photoLibrary; }, ["ALL"] call _isArmed, (count _lib) > 0]
        ]]
    ];
    if !([] call comspec_atak_native_fnc_bridge) then {
        _rows pushBack ["text", "<t color='#e0a030'>Bibliothèque disponible avec la liaison Overwatch (mod COMSPEC Overwatch chargé).</t>"];
    } else {
        if ((count _lib) isEqualTo 0) then { _rows pushBack ["text", "<t color='#8a9a93'>Aucune photo trouvée sur le poste. Prenez-en une depuis l'onglet APPAREIL.</t>"]; };
    };
    {
        _x params ["_path", "_name"];
        private _k = [toLower _path, toLower _name];
        private _tx = switch (true) do {
            case ((_k findIf { _x in _sent }) >= 0): { "<t color='#5cc76b'>● transmise</t>" };
            case ((_k findIf { _x in _bad }) >= 0): { "<t color='#e5483a'>● envoi refusé</t>" };
            case ((_wait findIf { ((_x find (_k select 1)) >= 0) }) >= 0): { "<t color='#e0a030'>● en attente d'envoi</t>" };
            default { "<t color='#8a9a93'>○ non transmise</t>" };
        };
        private _vps = if (isNil "_known") then { "<t color='#8a9a93'>? Athena : inconnu</t>" } else {
            if ((_k select 1) in _known) then { "<t color='#5cc76b'>● visible sur Athena</t>" } else { "<t color='#8a9a93'>○ absente d'Athena</t>" }
        };
        private _del = [str _forEachIndex] call _isArmed;
        _rows pushBack ["person", "\z\comspec_atak_native\addons\main\data\ui_gallery.paa",
            format ["<t font='RobotoCondensedBold'>%1</t><br/><t size='0.8'><t color='#5cc76b'>● sur le poste</t>   %2   %3</t>", [_name] call _esc, _tx, _vps],
            [["ENVOYER", compile format ["['send', %1] call comspec_atak_native_fnc_photoLibrary;", _forEachIndex], true],
             [["SUPPRIMER", "CONFIRMER"] select _del, compile format ["['delete', %1] call comspec_atak_native_fnc_photoLibrary;", _forEachIndex], _del]],
            [0.90, 0.94, 0.91, 1]];
    } forEach _lib;
    [_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
    true
};
// Onglet APPAREIL, façon appli photo : onglets, viseur avec infos de prise de vue et gros déclencheur,
// légende, compteurs d'envoi en tuiles, puis historique des envois.
private _font = _l get "font";
private _fs = _l get "fontSmall";
private _pad = (_l get "pad") * 2;
private _ratio = pixelH / pixelW;
private _dir = "\z\comspec_atak_native\addons\main\data\";
private _mk = { params ["_c", "_p", ["_t", ""]]; [_c, _p, _t] call comspec_atak_native_fnc_pageCtrl };
private _open = { private _c = ["caption"] call comspec_atak_native_fnc_formValue; [{ ["ENTER", _this] call comspec_atak_native_fnc_photoMode; }, _c] call CBA_fnc_execNextFrame; };

// Onglets et légende
private _topH = _font * 1.55 * 3.3 + _pad * 2;
[_head + [["edit", "caption", "Légende de la prochaine photo (facultatif)", ""]], [0, 0, _bw, _topH]] call comspec_atak_native_fnc_formRender;

// Viseur
private _vy = _topH + _pad / 2;
private _vh = (_bh * 0.3) max (_font * 5);
private _vx = _pad;
private _vw = _bw - _pad * 2;
private _vf = ["COMSPEC_RscText", [_vx, _vy, _vw, _vh]] call _mk;
_vf ctrlSetBackgroundColor [0.02, 0.025, 0.02, 1];
// Coins du cadre
private _cl = _vh * 0.14;
private _th = pixelH * 2;
private _tw = pixelW * 2;
{
    _x params ["_cx", "_cy", "_sx", "_sy"];
    private _h = ["COMSPEC_RscText", [[_cx, _cx - _cl * _ratio] select (_sx < 0), _cy, _cl * _ratio, _th]] call _mk;
    _h ctrlSetBackgroundColor [0.9, 0.95, 0.92, 0.85];
    private _v = ["COMSPEC_RscText", [_cx, [_cy, _cy - _cl] select (_sy < 0), _tw, _cl]] call _mk;
    _v ctrlSetBackgroundColor [0.9, 0.95, 0.92, 0.85];
} forEach [
    [_vx + _pad, _vy + _pad, 1, 1], [_vx + _vw - _pad - _tw, _vy + _pad, -1, 1],
    [_vx + _pad, _vy + _vh - _pad - _th, 1, -1], [_vx + _vw - _pad - _tw, _vy + _vh - _pad - _th, -1, -1]
];
// Réticule
private _rc = _vh * 0.08;
private _rh = ["COMSPEC_RscText", [_vx + _vw / 2 - _rc * _ratio, _vy + _vh * 0.42, _rc * 2 * _ratio, _th]] call _mk;
_rh ctrlSetBackgroundColor [0.36, 0.78, 0.42, 0.8];
private _rv = ["COMSPEC_RscText", [_vx + _vw / 2, _vy + _vh * 0.42 - _rc, _tw, _rc * 2]] call _mk;
_rv ctrlSetBackgroundColor [0.36, 0.78, 0.42, 0.8];
// Infos de prise de vue : grille, cap, heure
private _info = ["COMSPEC_RscStructuredText", [_vx + _pad * 2, _vy + _pad * 1.6, _vw - _pad * 4, _fs * 1.4]] call _mk;
_info ctrlSetStructuredText parseText format ["<t size='0.8' font='EtelkaMonospacePro' color='#c9d4cf'>%1</t><t size='0.8' align='right' font='EtelkaMonospacePro' color='#c9d4cf'>%2°  %3</t>",
    [getPosASL player, 8] call comspec_atak_native_fnc_gridRef, round getDir player, [dayTime, "HH:MM"] call BIS_fnc_timeToString];
private _tip = ["COMSPEC_RscStructuredText", [_vx + _pad * 2, _vy + _vh - _pad * 1.6 - _fs * 1.4, _vw - _pad * 4, _fs * 1.4]] call _mk;
_tip ctrlSetStructuredText parseText "<t size='0.75' align='center' color='#8a9a93'>Clic gauche : photographier · Espace : revenir au téléphone</t>";
// Déclencheur sous le viseur, centré comme dans une appli photo : anneau blanc, disque d'accent, icône.
private _sh = (_font * 3.2) min (_vh * 0.45);
private _sw = _sh * _ratio;
private _scx = _vx + _vw / 2;
private _scy = _vy + _vh + _pad / 2 + _sh / 2;
private _ring = ["COMSPEC_RscPicture", [_scx - _sw / 2, _scy - _sh / 2, _sw, _sh], _dir + "ui_disc.paa"] call _mk;
_ring ctrlSetTextColor [0.95, 0.97, 0.96, 1];
private _inner = ["COMSPEC_RscPicture", [_scx - _sw * 0.41, _scy - _sh * 0.41, _sw * 0.82, _sh * 0.82], _dir + "ui_disc.paa"] call _mk;
([] call comspec_atak_native_fnc_accent) params ["_acc"];
_inner ctrlSetTextColor _acc;
private _cam = ["COMSPEC_RscPicture", [_scx - _sw * 0.22, _scy - _sh * 0.22, _sw * 0.44, _sh * 0.44], _dir + "ui_photocam.paa"] call _mk;
_cam ctrlSetTextColor [0.05, 0.08, 0.06, 1];
{
    private _b = ["COMSPEC_RscButtonInvisible", _x] call _mk;
    _b ctrlSetTooltip "Ouvrir le viseur plein écran";
    _b ctrlAddEventHandler ["ButtonClick", _open];
} forEach [[_vx, _vy, _vw, _vh], [_scx - _sw / 2, _scy - _sh / 2, _sw, _sh]];

// Compteurs d'envoi
private _ty = _scy + _sh / 2 + _pad / 2;
private _tH = _font * 3.4;
private _tW = (_vw - _pad) / 3;
{
    _x params ["_n", "_lab", "_hex", "_rgb"];
    private _tx = _vx + _forEachIndex * (_tW + _pad / 2);
    private _t = ["COMSPEC_RscText", [_tx, _ty, _tW, _tH]] call _mk;
    _t ctrlSetBackgroundColor [0.06, 0.075, 0.065, 1];
    private _bar = ["COMSPEC_RscText", [_tx, _ty, _tW, pixelH * 3]] call _mk;
    _bar ctrlSetBackgroundColor _rgb;
    private _c = ["COMSPEC_RscStructuredText", [_tx, _ty + _tH * 0.12, _tW, _tH * 0.88]] call _mk;
    _c ctrlSetStructuredText parseText format ["<t align='center' size='1.3' font='RobotoCondensedBold' color='%3'>%1</t><br/><t align='center' size='0.7' color='#8a9a93'>%2</t>", _n, _lab, _hex];
} forEach [
    [count (missionNamespace getVariable ["COMSPEC_Athena_PhotoPending", []]), "EN ATTENTE", "#e8b84a", [0.91, 0.72, 0.29, 1]],
    [count (missionNamespace getVariable ["COMSPEC_Athena_PhotoUploaded", []]), "REÇUES", "#5cc76b", [0.36, 0.78, 0.42, 1]],
    [count (missionNamespace getVariable ["COMSPEC_Athena_PhotoFailed", []]), "REFUSÉES", "#e5483a", [0.9, 0.28, 0.23, 1]]
];

// Dernier retour d'Athena et historique
private _hy = _ty + _tH + _pad;
private _rows = [
    ["text", (missionNamespace getVariable ["COMSPEC_LastReconUploadResult", []]) call {
        params [["_st", ""], ["_msg", ""], ["_tech", ""], ["_at", ""]];
        switch (_st) do {
            case "OK": { format ["<t color='#5cc76b'>● %1 · reçue par Athena</t>  <t size='0.8' color='#8a9a93'>%2</t>", _at, [_tech] call _esc] };
            case "ERR": { format ["<t color='#e5483a'>● %1 · %2</t><br/><t size='0.8' color='#8a9a93'>%3</t>", _at, [_msg] call _esc, [_tech] call _esc] };
            default { "<t color='#8a9a93'>Aucun retour d'Athena pour l'instant.</t>" };
        }
    }],
    ["section", "Historique", "Photos envoyées pendant cette session"]
];
if ((count _lines) isEqualTo 0) then {
    _rows pushBack ["text", "<t color='#8a9a93'>Rien pour l'instant. Touchez le viseur pour prendre une photo.</t>"];
} else {
    { _rows pushBack ["text", _x]; } forEach (_lines select [0, 12]);
};
[_rows, [0, _hy, _bw, (_bh - _hy) max (_font * 3)]] call comspec_atak_native_fnc_formRender;
true
