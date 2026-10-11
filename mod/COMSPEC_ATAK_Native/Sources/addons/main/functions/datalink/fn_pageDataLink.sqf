/*
    App Extraction : câble DL-7 branché sur un support (ordinateur, téléphone, clé…) posé par Zeus, Eden ou un script
    (fn_dataSetup). Câble branché : l'écran du support distant, à son thème (connexion, forçage, fichiers, transfert).
    Sinon : supports à portée et données récupérées (lecture, carte, son, partage au groupe).
    Params : [page, [0, 0, largeur, hauteur]]
*/
params ["", "_rect"];
disableSerialization;
private _grey = "#8a9a93";
private _rows = [];
private _st = uiNamespace getVariable ["COMSPEC_ATAK_DataLink", createHashMap];
private _phase = _st getOrDefault ["phase", ""];
private _lib = missionNamespace getVariable ["COMSPEC_ATAK_DataLib", []];
private _esc = {
    params ["_t"];
    if !(_t isEqualType "") then { _t = str _t; };
    _t = (_t splitString "&") joinString "&amp;";
    _t = (_t splitString "<") joinString "&lt;";
    _t = (_t splitString ">") joinString "&gt;";
    (_t splitString toString [10]) joinString "<br/>"
};
private _rep = { params ["_n", "_c"]; private _a = []; _a resize (_n max 0); (_a apply { _c }) joinString "" };
private _bar = {
    params ["_p", "_acc"];
    private _n = 30;
    private _k = round ((_p max 0 min 1) * _n);
    format ["<t font='EtelkaMonospaceProBold' color='%1'>%2</t><t font='EtelkaMonospaceProBold' color='#3b3f3c'>%3</t>  <t font='EtelkaMonospaceProBold'>%4 %5</t>", _acc, [_k, "|"] call _rep, [_n - _k, "|"] call _rep, round ((_p max 0 min 1) * 100), "%"]
};
private _kb = { params ["_k"]; if (_k >= 1024) then { format ["%1 Mo", (_k / 1024) toFixed 1] } else { format ["%1 Ko", round _k] } };
private _typeLabel = createHashMapFromArray [["texte", "Texte"], ["document", "Document"], ["identifiants", "Identifiants"], ["coord", "Coordonnées"], ["image", "Image"], ["audio", "Audio"]];

if (_phase isNotEqualTo "") exitWith {
    private _obj = _st getOrDefault ["obj", objNull];
    private _cfg = createHashMapFromArray (_obj getVariable ["COMSPEC_ATAK_Data", []]);
    private _theme = _cfg getOrDefault ["theme", "windows"];
    (switch (_theme) do {
        case "linux": { [[0.01, 0.02, 0.012, 0.97], "#33ff66", "EtelkaMonospacePro", "tty1 · GNU/Linux"] };
        case "militaire": { [[0.07, 0.085, 0.05, 0.97], "#c8b560", "PuristaMedium", "SYSTÈME TACTIQUE · ACCÈS RESTREINT"] };
        case "scada": { [[0.11, 0.12, 0.13, 0.97], "#ff9a1f", "RobotoCondensed", "Supervision industrielle"] };
        case "telephone": { [[0.02, 0.02, 0.04, 0.97], "#e8ecff", "RobotoCondensedLight", "Écran verrouillé"] };
        case "coffre": { [[0.09, 0.015, 0.015, 0.97], "#ff4a3a", "EtelkaMonospacePro", "COFFRE CHIFFRÉ · AES-256"] };
        default { [[0.015, 0.13, 0.30, 0.97], "#4cc2ff", "RobotoCondensed", "Session Windows"] };
    }) params ["_bg", "_acc", "_font", "_os"];
    private _custom = _cfg getOrDefault ["accent", ""];
    if (_custom isEqualType "" && {(count _custom) isEqualTo 7} && {(_custom select [0, 1]) isEqualTo "#"}) then { _acc = _custom; };
    private _name = [_cfg getOrDefault ["name", "Support"]] call _esc;
    private _banner = [_cfg getOrDefault ["banner", ""]] call _esc;
    private _portName = switch (_st getOrDefault ["port", "usb"]) do { case "rj45": { "Ethernet RJ45" }; case "serie": { "Série RS-232" }; default { "USB-C" }; };
    private _files = _cfg getOrDefault ["files", []];
    private _now = diag_tickTime;
    private _p = (_now - (_st getOrDefault ["t0", _now])) / ((_st getOrDefault ["dur", 1]) max 0.1);

    // En-tête de l'écran distant.
    _rows pushBack ["text", format ["<t font='%1' size='1.35' color='%2'>%3</t><br/><t font='%1' size='0.85' color='#c9d1cc'>%4</t><t size='0.85' color='%5'>  ·  %6</t>",
        _font, _acc, [_banner, _name] select (_banner isEqualTo ""), [_os, _name] select (_banner isNotEqualTo "" && {_phase in ["BROWSE", "DL", "DONE"]}), _grey, _os]];
    private _wall = _cfg getOrDefault ["wallpaper", ""];
    if (_wall isNotEqualTo "" && {_phase in ["AUTH", "LOCKED"]}) then { _rows pushBack ["image", _wall]; };

    switch (_phase) do {
        case "PLUG";
        case "HANDSHAKE": {
            private _steps = [format ["Câble DL-7 branché (%1)", _portName], "Négociation de la liaison", "Lecture de l'identifiant matériel", "Montage du volume"];
            private _k = if (_phase isEqualTo "PLUG") then { 1 } else { 2 + floor ((_p min 0.99) * 3) };
            _rows pushBack ["text", ((_steps select [0, _k]) apply { format ["<t font='EtelkaMonospacePro' size='0.85' color='%1'>&gt; %2</t>", _acc, _x] }) joinString "<br/>"];
            _rows pushBack ["text", [[_p, 0.5] select (_phase isEqualTo "PLUG"), _acc] call _bar];
        };
        case "AUTH": {
            private _tries = _obj getVariable ["COMSPEC_ATAK_DataTries", 0];
            private _max = _cfg getOrDefault ["attempts", 3];
            _rows pushBack ["password", "dlPass", "Mot de passe"];
            private _hint = _cfg getOrDefault ["hint", ""];
            if (_hint isNotEqualTo "") then { _rows pushBack ["text", format ["<t size='0.85' color='%1'>Indice : %2</t>", _grey, [_hint] call _esc]]; };
            private _msg = _st getOrDefault ["msg", ""];
            if (_msg isNotEqualTo "") then { _rows pushBack ["text", format ["<t color='#e5483a'>%1</t>", _msg]]; };
            if (_max > 0) then {
                private _left = (_max - _tries) max 0;
                _rows pushBack ["text", format ["<t size='0.85' color='%1'>%2 essai(s) restant(s) avant %3</t>", ["#e5a03a", "#e5483a"] select (_left <= 1), _left,
                    switch (_cfg getOrDefault ["onFail", "lock"]) do { case "wipe": { "effacement des données" }; case "alarm": { "déclenchement de l'alarme" }; case "none": { "rien" }; default { "verrouillage" }; }]];
            };
            _rows pushBack ["buttons", [
                ["CONNEXION", { ["login"] call comspec_atak_native_fnc_dataAction; }, true],
                ["FORCER L'ACCÈS", { ["crack"] call comspec_atak_native_fnc_dataAction; }, false, _cfg getOrDefault ["crack", true]]
            ]];
        };
        case "LOCKED": {
            _rows pushBack ["text", format ["<t font='%1' size='1.1' color='#e5483a'>ACCÈS VERROUILLÉ</t><br/><t size='0.85' color='%2'>Trop d'essais. %3</t>", _font, _grey,
                ["Le forçage n'est pas possible sur ce support.", "Seul un forçage peut encore ouvrir le support."] select (_cfg getOrDefault ["crack", true])]];
            _rows pushBack ["buttons", [["FORCER L'ACCÈS", { ["crack"] call comspec_atak_native_fnc_dataAction; }, true, _cfg getOrDefault ["crack", true]]]];
        };
        case "CRACK": {
            private _hex = "0123456789ABCDEF" splitString "";
            private _lines = [];
            for "_i" from 1 to 4 do {
                private _l = [];
                for "_j" from 1 to 8 do { _l pushBack ((([] resize 0) + [1, 2, 3, 4]) apply { selectRandom _hex } joinString ""); };
                _lines pushBack (_l joinString " ");
            };
            _rows pushBack ["text", format ["<t font='EtelkaMonospacePro' size='0.8' color='%1'>%2</t>", _acc, _lines joinString "<br/>"]];
            _rows pushBack ["text", format ["<t size='0.85' color='%1'>Forçage en cours : restez branché. Reste %2 s</t>", _grey, ceil (((_st getOrDefault ["dur", 1]) * (1 - _p)) max 0)]];
            _rows pushBack ["text", [_p, _acc] call _bar];
        };
        case "WIPED": {
            _rows pushBack ["text", format ["<t font='%1' size='1.1' color='#e5483a'>SUPPORT VIDE</t><br/><t size='0.85' color='%2'>Les données ont été effacées. Aucun fichier exploitable.</t>", _font, _grey]];
        };
        case "BROWSE": {
            private _sel = _st getOrDefault ["sel", []];
            private _n = { _x } count _sel;
            private _size = 0;
            { if (_sel param [_forEachIndex, false]) then { _size = _size + (_x select 3); }; } forEach _files;
            _rows pushBack ["section", "Fichiers", format ["%1 sur %2 sélectionné(s) · %3", _n, count _files, [_size] call _kb]];
            if (_files isEqualTo []) then { _rows pushBack ["text", format ["<t color='%1'>Aucun fichier sur ce support.</t>", _grey]]; };
            {
                _x params ["_fn", "_ft", "", "_fs"];
                private _i = _forEachIndex;
                _rows pushBack ["toggle", format ["%1   (%2, %3)", _fn, _typeLabel getOrDefault [_ft, _ft], [_fs] call _kb], _sel param [_i, false], compile format ["['toggle', %1] call comspec_atak_native_fnc_dataAction;", _i]];
            } forEach _files;
            private _msg = _st getOrDefault ["msg", ""];
            if (_msg isNotEqualTo "") then { _rows pushBack ["text", format ["<t color='#e5a03a'>%1</t>", _msg]]; };
            _rows pushBack ["buttons", [
                [format ["TÉLÉCHARGER (%1)", _n], { ["download"] call comspec_atak_native_fnc_dataAction; }, true, _n > 0],
                ["TOUT / RIEN", { ["all"] call comspec_atak_native_fnc_dataAction; }, false, (count _files) > 0]
            ]];
        };
        case "DL": {
            private _queue = _st getOrDefault ["queue", []];
            private _got = _st getOrDefault ["got", 0];
            private _bytes = _st getOrDefault ["bytes", 1];
            private _dur = (_st getOrDefault ["dur", 1]) max 0.1;
            _rows pushBack ["section", "Transfert", format ["%1 · %2/s · reste %3 s", _portName, [_bytes / _dur] call _kb, ceil ((_dur * (1 - _p)) max 0)]];
            _rows pushBack ["text", [_p, _acc] call _bar];
            _rows pushBack ["text", (_queue apply {
                private _f = _files param [_x, ["?", "", "", 0]];
                private _k = _queue find _x;
                format ["<t font='EtelkaMonospacePro' size='0.85' color='%1'>%2 %3</t>", [_grey, _acc] select (_k < _got), ["...", "OK "] select (_k < _got), [_f select 0] call _esc]
            }) joinString "<br/>"];
            _rows pushBack ["text", format ["<t size='0.85' color='#e5a03a'>Restez à moins de %1 m du support : un câble arraché coupe le transfert (les fichiers déjà reçus sont gardés).</t>", missionNamespace getVariable ["comspec_atak_native_data_length", 3]]];
        };
        case "DONE": {
            private _queue = _st getOrDefault ["queue", []];
            _rows pushBack ["text", format ["<t font='%1' size='1.1' color='#5cc76b'>TRANSFERT TERMINÉ</t><br/><t size='0.85' color='%2'>%3 fichier(s) dans « Données récupérées ». Débranchez le câble pour les consulter.</t>", _font, _grey, count _queue]];
        };
        default {};
    };
    _rows pushBack ["gap"];
    _rows pushBack ["buttons", [["DÉBRANCHER LE CÂBLE", { ["disconnect"] call comspec_atak_native_fnc_dataAction; }, _phase in ["DONE", "WIPED"]]]];
    [_rows, _rect, true, _bg] call comspec_atak_native_fnc_formRender;
    true
};

// Fichier ouvert.
private _view = uiNamespace getVariable ["COMSPEC_ATAK_DataView", -1];
private _e = _lib param [_view, []];
if (_view isEqualType 0 && {_view >= 0} && {(count _e) >= 5}) exitWith {
    _e params ["_src", "_fn", "_ft", "_content", "_fs", ["_clock", ""], ["_grid", ""], ["_from", ""]];
    _rows pushBack ["section", _fn, format ["%1 · %2 · %3%4", _typeLabel getOrDefault [_ft, _ft], [_fs] call _kb, _src, ["", format [" · reçu de %1", _from]] select (_from isNotEqualTo "")]];
    switch (_ft) do {
        case "image": {
            _rows pushBack ["image", _content];
        };
        case "coord": {
            _rows pushBack ["info", "Position", format ["<t font='EtelkaMonospaceProBold'>%1</t>", [_content] call _esc]];
            _rows pushBack ["buttons", [["MARQUER SUR LA CARTE", compile format ["['mark', %1] call comspec_atak_native_fnc_dataAction;", _view], true]]];
        };
        case "audio": {
            _rows pushBack ["text", format ["<t color='%1'>Enregistrement audio.</t>", _grey]];
            _rows pushBack ["buttons", [["ÉCOUTER", compile format ["['play', %1] call comspec_atak_native_fnc_dataAction;", _view], true]]];
        };
        case "identifiants": {
            _rows pushBack ["text", format ["<t font='EtelkaMonospacePro'>%1</t>", [_content] call _esc]];
        };
        default {
            _rows pushBack ["text", [_content] call _esc];
        };
    };
    _rows pushBack ["text", format ["<t size='0.8' color='%1'>Récupéré à %2, carroyage %3</t>", _grey, _clock, _grid]];
    _rows pushBack ["buttons", [
        ["RETOUR", { ["view", -1] call comspec_atak_native_fnc_dataAction; }, false],
        ["PARTAGER", compile format ["['share', %1] call comspec_atak_native_fnc_dataAction;", _view], true],
        ["SUPPRIMER", compile format ["['delete', %1] call comspec_atak_native_fnc_dataAction;", _view], false]
    ]];
    [_rows, _rect] call comspec_atak_native_fnc_formRender;
    true
};

// Accueil : câble, supports à portée, données récupérées.
private _len = missionNamespace getVariable ["comspec_atak_native_data_length", 3];
private _need = missionNamespace getVariable ["comspec_atak_native_data_cable", false];
private _has = "COMSPEC_ATAK_DataCable" in (items player);
_rows pushBack ["section", "Extraction de données", format ["Câble DL-7 de %1 m : branchez-vous sur un ordinateur, un téléphone, une clé…", _len]];
_rows pushBack ["info", "Câble DL-7", switch (true) do {
    case _has: { "<t color='#5cc76b'>dans l'équipement</t>" };
    case _need: { "<t color='#e5483a'>absent : obligatoire sur ce serveur</t>" };
    default { format ["<t color='%1'>non requis sur ce serveur</t>", _grey] };
}];
private _nodes = ["nodes"] call comspec_atak_native_fnc_dataAction;
_rows pushBack ["section", "Supports à proximité", format ["%1 à moins de 25 m", count _nodes]];
if (_nodes isEqualTo []) then { _rows pushBack ["text", format ["<t color='%1'>Aucun support repéré. Approchez-vous d'un poste, regardez-le et utilisez « Brancher l'ATAK ».</t>", _grey]]; };
{
    _x params ["_o", "_d"];
    private _cfg = createHashMapFromArray (_o getVariable ["COMSPEC_ATAK_Data", []]);
    private _state = switch (true) do {
        case (_o getVariable ["COMSPEC_ATAK_DataWiped", false]): { "<t color='#e5483a'>effacé</t>" };
        case (_o getVariable ["COMSPEC_ATAK_DataLocked", false]): { "<t color='#e5483a'>verrouillé</t>" };
        case ((_cfg getOrDefault ["pwHash", ""]) isNotEqualTo ""): { "<t color='#e5a03a'>protégé</t>" };
        default { "<t color='#5cc76b'>libre</t>" };
    };
    _rows pushBack ["person", "\z\comspec_atak_native\addons\main\data\app_datalink.paa",
        format ["<t font='RobotoCondensedBold'>%1</t><br/><t size='0.85' color='%2'>%3 m · %4</t> %5", [_cfg getOrDefault ["name", "Support"]] call _esc, _grey, round _d, _cfg getOrDefault ["kind", ""], _state],
        [["BRANCHER", compile format ["['connect', objectFromNetId '%1'] call comspec_atak_native_fnc_dataAction;", netId _o], _d <= _len + 0.5]]];
} forEach (_nodes select [0, 8]);

_rows pushBack ["section", "Données récupérées", format ["%1 fichier(s)", count _lib]];
if (_lib isEqualTo []) then { _rows pushBack ["text", format ["<t color='%1'>Rien pour l'instant.</t>", _grey]]; };
{
    _x params ["_src", "_fn", "_ft", "", "_fs", ["_clock", ""], "", ["_from", ""]];
    _rows pushBack ["person", "\z\comspec_atak_native\addons\main\data\app_datalink.paa",
        format ["<t font='RobotoCondensedBold'>%1</t><br/><t size='0.85' color='%2'>%3 · %4 · %5 %6</t>", [_fn] call _esc, _grey, _typeLabel getOrDefault [_ft, _ft], [_fs] call _kb, [_src] call _esc, _clock],
        [["OUVRIR", compile format ["['view', %1] call comspec_atak_native_fnc_dataAction;", _forEachIndex], true]]];
} forEach _lib;
if (_lib isNotEqualTo []) then {
    _rows pushBack ["buttons", [
        ["PARTAGER AU GROUPE", { ["share", -1] call comspec_atak_native_fnc_dataAction; }, true],
        ["TOUT EFFACER", { ["clear"] call comspec_atak_native_fnc_dataAction; }, false]
    ]];
};
[_rows, _rect] call comspec_atak_native_fnc_formRender;
true
