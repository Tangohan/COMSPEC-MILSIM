/*
    App Bluetooth : marche / arrêt (indépendant du mode avion), appairage par code avec un ATAK proche,
    appareils appairés (portée, oubli), demandes reçues (accepter / refuser), transferts en cours, éléments reçus,
    et envoi à un appareil appairé, par onglet : SONS et MUSIQUE (joués par son haut-parleur, ou mes morceaux copiés
    dans son dossier), PHOTOS (copiées dans sa Galerie), DONNÉES (position, itinéraire, plans) et FICHES (SEEK, SSE).
    Params (app déclarée avec function=) : [page, [x, y, largeur, hauteur]]. Actions : fn_btAction ; listes : fn_btSounds, fn_btItems.
*/
params [["_page", "BLUETOOTH"], ["_rect", []]];
disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];
private _bt = ["state"] call comspec_atak_native_fnc_btAction;
private _ui = uiNamespace getVariable ["COMSPEC_ATAK_BtUi", createHashMap];
uiNamespace setVariable ["COMSPEC_ATAK_BtUi", _ui];
private _now = [time, serverTime] select isMultiplayer;
private _range = missionNamespace getVariable ["comspec_atak_native_bt_range", 15];
private _on = _bt get "on";
private _usable = ["on"] call comspec_atak_native_fnc_btAction;
private _air = [] call comspec_atak_native_fnc_airplaneMode;
([] call comspec_atak_native_fnc_accent) params ["_acc", "_accHex"];
private _icon = "\a3\ui_f\data\igui\cfg\simpletasks\types\radio_ca.paa";
private _dataDir = "\z\comspec_atak_native\addons\main\data\";
private _kindIcon = createHashMapFromArray [["snd", _dataDir + "app_music.paa"], ["mus", _dataDir + "app_music.paa"], ["mp3", _dataDir + "app_music.paa"],
    ["pho", _dataDir + "app_gallery.paa"], ["pos", _dataDir + "app_map.paa"], ["wp", _dataDir + "app_waypoints.paa"], ["plan", _dataDir + "app_plan.paa"],
    ["seek", _dataDir + "app_sse.paa"], ["sse", _dataDir + "app_sse.paa"]];
private _kindName = createHashMapFromArray [["snd", "son"], ["mus", "musique"], ["mp3", "morceau (fichier)"], ["pho", "photo"], ["pos", "position"],
    ["wp", "itinéraire"], ["plan", "plan"], ["seek", "fiche SEEK"], ["sse", "fiche SSE"]];
private _esc = { params ["_t"]; { _t = [_t, _x select 0, _x select 1] call CBA_fnc_replace; } forEach [["&", "&amp;"], ["<", "&lt;"], [">", "&gt;"]]; _t };
private _act = { params ["_a", ["_v", ""]]; compile format ["[%1, %2] call comspec_atak_native_fnc_btAction;", str _a, str _v] };
private _rows = [
    ["switch", "Bluetooth", _on, ["toggle"] call _act, format ["ATAK de %1 · visible des téléphones à moins de %2 m", [name player] call _esc, round _range]],
    ["switch", "Mode avion", _air, { ["toggle"] call comspec_atak_native_fnc_airplaneMode; }, "Coupe le réseau (synchro, SMS, BFT). Le Bluetooth reste disponible."]
];
if (!_on) exitWith {
    _rows pushBack ["text", "<t color='#8a9a93'>Bluetooth désactivé. Activez-le pour appairer un ATAK proche et échanger photos, musiques, données et fiches.</t>"];
    [_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
    true
};
if (!_usable) then { _rows pushBack ["text", "<t color='#f2ab33'>Téléphone indisponible : Bluetooth inactif.</t>"]; };

// Appairage par code
private _code = _bt get "code";
private _codeOn = _code isNotEqualTo "" && {_now < (_bt get "codeUntil")};
_rows pushBack ["section", "Appairer un appareil", format ["Les deux téléphones à moins de %1 m", round _range]];
if (_codeOn) then {
    _rows pushBack ["text", format ["<t align='center' size='2' font='COMSPEC_BarlowBold' color='%1'>%2 %3</t><br/><t align='center' size='0.8' color='#8a9a93'>Code d'appairage · valable encore %4 s · à saisir sur l'autre téléphone</t>",
        _accHex, _code select [0, 3], _code select [3, 3], round ((_bt get "codeUntil") - _now)]];
    _rows pushBack ["buttons", [["CACHER LE CODE", ["hideCode"] call _act]]];
} else {
    _rows pushBack ["buttons", [["AFFICHER MON CODE", ["showCode"] call _act, true, _usable]]];
};
_rows append [
    ["edit", "btCode", "Code affiché par l'autre téléphone", ""],
    ["buttons", [["APPAIRER", ["enterCode"] call _act, true, _usable]]]
];

// Demandes reçues
private _pend = (_bt get "pending") select { (_now - (_x select 6)) <= 60 };
if ((count _pend) > 0) then {
    _rows pushBack ["section", "Demandes reçues", "Un son sort du haut-parleur ; un fichier, une donnée ou une fiche est rangé sur le téléphone"];
    {
        _x params ["_id", "", "_name", "_kind", "", "_title", "_at"];
        _rows pushBack ["person", _kindIcon getOrDefault [_kind, _icon], format ["<t font='COMSPEC_BarlowBold'>%1</t><br/><t size='0.8' color='#8a9a93'>%2 · %3 · expire dans %4 s</t>",
            [_title] call _esc, [_name] call _esc, _kindName getOrDefault [_kind, "élément"], round (60 - (_now - _at))],
            [["ACCEPTER", ["accept", _id] call _act, true], ["REFUSER", ["refuse", _id] call _act]], _acc];
    } forEach _pend;
};

// Transferts en cours (envoi et réception de fichiers).
private _xf = _bt getOrDefault ["xfers", createHashMap];
if ((count _xf) > 0) then {
    _rows pushBack ["section", "Transferts en cours", "Restez à portée jusqu'à la fin"];
    {
        private _pct = floor (100 * (_y getOrDefault ["done", 0]) / ((_y getOrDefault ["total", 0]) max 1));
        // Barre de progression en 20 crans.
        private _full = floor (_pct / 5);
        private _bar = "";
        for "_i" from 1 to 20 do { _bar = _bar + (["-", "|"] select (_i <= _full)); };
        _bar = format ["<t font='EtelkaMonospaceProBold' color='%1'>%2</t><t font='EtelkaMonospacePro' color='#3a4a44'>%3</t>", _accHex, _bar select [0, _full], _bar select [_full]];
        _rows pushBack ["person", _kindIcon getOrDefault [_y get "kind", _icon], format ["<t font='COMSPEC_BarlowBold'>%1</t><br/><t size='0.8' color='#8a9a93'>%2 %3 · %4 %5  </t><t size='0.7'>%6</t>",
            [_y get "title"] call _esc, ["De", "Vers"] select ((_y get "dir") isEqualTo "out"), [_y get "name"] call _esc, _pct, "%", _bar],
            [["ANNULER", ["cancel", _x] call _act]], _acc];
    } forEach _xf;
};
if ((_bt get "playing") isNotEqualTo "") then { _rows pushBack ["buttons", [["ARRÊTER LE SON EN COURS", ["stopPlay"] call _act, true]]]; };

// Appareils appairés
private _paired = _bt get "paired";
private _target = _ui getOrDefault ["target", ""];
if !(_target in _paired) then { _target = ""; _ui set ["target", ""]; };
_rows pushBack ["section", "Appareils appairés", format ["%1 appareil(s) · gardés pour la mission", count _paired]];
if ((count _paired) isEqualTo 0) then { _rows pushBack ["text", "<t color='#8a9a93'>Aucun. Affichez votre code, ou saisissez celui d'un équipier proche.</t>"]; };
{
    private _uid = _x;
    private _name = _y;
    private _i = allPlayers findIf { getPlayerUID _x isEqualTo _uid };
    private _u = if (_i < 0) then { objNull } else { allPlayers select _i };
    private _st = switch (true) do {
        case (isNull _u): { "<t color='#8a9a93'>déconnecté</t>" };
        case !(_u getVariable ["COMSPEC_ATAK_BtOn", false]): { "<t color='#8a9a93'>Bluetooth éteint</t>" };
        case ((_u distance player) > _range): { format ["<t color='#f2ab33'>hors de portée · %1 m</t>", round (_u distance player)] };
        default { format ["<t color='%1'>connecté · %2 m</t>", _accHex, round (_u distance player)] };
    };
    _rows pushBack ["person", _icon, format ["<t font='COMSPEC_BarlowBold'>ATAK de %1</t><br/><t size='0.8'>%2</t>", [_name] call _esc, _st],
        [[["ENVOYER", "FERMER"] select (_target isEqualTo _uid), ["target", _uid] call _act, _target isNotEqualTo _uid], ["OUBLIER", ["unpair", _uid] call _act]],
        [[0.55, 0.6, 0.58, 1], _acc] select (_target isEqualTo _uid)];
} forEach _paired;

// Envoi à l'appareil choisi : un onglet par sorte d'élément.
if (_target isNotEqualTo "") then {
    private _tab = _ui getOrDefault ["tab", "snd"];
    if !(_tab in ["snd", "mus", "pho", "data", "sheet"]) then { _tab = "snd"; };
    private _q = toLower (_ui getOrDefault ["q", ""]);
    private _src = switch (_tab) do {
        case "snd": { ([] call comspec_atak_native_fnc_btSounds) select { (_x select 0) isEqualTo "snd" } };
        case "mus": { (["list", "mp3"] call comspec_atak_native_fnc_btItems) + (([] call comspec_atak_native_fnc_btSounds) select { (_x select 0) isEqualTo "mus" }) };
        default { ["list", _tab] call comspec_atak_native_fnc_btItems };
    };
    private _all = _src select { _q isEqualTo "" || {((toLower (_x select 2)) find _q) >= 0} || {((toLower (_x select 3)) find _q) >= 0} };
    uiNamespace setVariable ["COMSPEC_ATAK_BtList", _all];
    private _hint = switch (_tab) do {
        case "snd": { "Il accepte ou refuse ; le son sort du haut-parleur de son téléphone" };
        case "mus": { "Mes fichiers : le morceau est copié dans son app Musique. Serveur : joué par son haut-parleur" };
        case "pho": { "La photo est copiée dans sa Galerie (quelques secondes, restez à portée)" };
        case "data": { "Position (repère 10 min), itinéraire (app Points de passage), plans (app Plan)" };
        default { "Fiches SEEK (journal du terminal) et fiches SSE (personnes enregistrées au SEEK)" };
    };
    _rows append [
        ["section", format ["Envoyer à %1", [_paired getOrDefault [_target, ""]] call _esc], _hint],
        ["segment", "", [["SONS", ["tab", "snd"] call _act, _tab isEqualTo "snd"], ["MUSIQUE", ["tab", "mus"] call _act, _tab isEqualTo "mus"], ["PHOTOS", ["tab", "pho"] call _act, _tab isEqualTo "pho"]]],
        ["segment", "", [["DONNÉES", ["tab", "data"] call _act, _tab isEqualTo "data"], ["FICHES", ["tab", "sheet"] call _act, _tab isEqualTo "sheet"]]],
        ["edit", "btSearch", "Rechercher", _ui getOrDefault ["q", ""]],
        ["buttons", [["RECHERCHER", ["search"] call _act, true], ["EFFACER", { (uiNamespace getVariable ["COMSPEC_ATAK_BtUi", createHashMap]) set ["q", ""]; ["page", 0] call comspec_atak_native_fnc_btAction; }]]]
    ];
    private _per = 15;
    private _pg = (_ui getOrDefault ["page", 0]) min (floor (((count _all) - 1) / _per)) max 0;
    if ((count _all) isEqualTo 0) then {
        _rows pushBack ["text", format ["<t color='#8a9a93'>%1</t>", switch (_tab) do {
            case "pho": { ["Aucune photo sur le téléphone. Prenez-en avec l'app Photo.", "Photos indisponibles : liaison Overwatch absente."] select !([] call comspec_atak_native_fnc_bridge) };
            case "mus": { "Aucun morceau. Déposez des MP3 dans Documents\Arma 3\COMSPEC_Music (app Musique, Mes fichiers)." };
            case "sheet": { "Aucune fiche. Elles viennent du terminal SEEK : interrogations (fiches SEEK) et personnes enregistrées (fiches SSE)." };
            default { "Rien trouvé." };
        }]];
    };
    for "_i" from (_pg * _per) to (((_pg + 1) * _per min (count _all)) - 1) do {
        (_all select _i) params ["_kind", "_ref", "_title", "_cat", ["_len", 0]];
        _rows pushBack ["person", _kindIcon getOrDefault [_kind, _icon],
            format ["%1<br/><t size='0.75' color='#8a9a93'>%2%3</t>", [_title] call _esc, [_cat] call _esc, ["", format [" · %1:%2", floor (_len / 60), [str (floor _len mod 60), "0" + str (floor _len mod 60)] select ((floor _len mod 60) < 10)]] select (_len > 0)],
            [[["ENVOYER", "FAIRE ÉCOUTER"] select (_kind in ["snd", "mus"]), ["send", _i] call _act, true, _usable]]];
    };
    if ((count _all) > _per) then {
        _rows pushBack ["buttons", [
            ["PRÉCÉDENTS", ["page", _pg - 1] call _act, false, _pg > 0],
            [format ["%1 / %2", _pg + 1, ceil ((count _all) / _per)], {}, false, false],
            ["SUIVANTS", ["page", _pg + 1] call _act, false, ((_pg + 1) * _per) < (count _all)]
        ]];
    };
};

// Reçus par Bluetooth : fichiers rangés, données appliquées, fiches consultables ici.
private _inbox = missionNamespace getVariable ["COMSPEC_ATAK_BtInbox", []];
if ((count _inbox) > 0) then {
    private _view = _ui getOrDefault ["view", ""];
    _rows pushBack ["section", "Reçus par Bluetooth", format ["%1 élément(s) cette mission", count _inbox]];
    {
        _x params ["_id", "_kind", "_title", "_from", "_hh", ["_data", []]];
        private _where = switch (_kind) do {
            case "pho": { "dans la Galerie" };
            case "mp3": { "dans l'app Musique, Mes fichiers" };
            case "wp": { "dans l'app Points de passage" };
            case "plan": { "dans la bibliothèque de l'app Plan" };
            case "pos": { "repère sur la carte" };
            case "seek": { "aussi dans le journal du terminal SEEK" };
            default { "" };
        };
        private _canView = !(_kind in ["pho", "mp3"]);
        _rows pushBack ["person", _kindIcon getOrDefault [_kind, _icon], format ["<t font='COMSPEC_BarlowBold'>%1</t><br/><t size='0.8' color='#8a9a93'>%2 · de %3 · %4%5</t>",
            [_title] call _esc, _kindName getOrDefault [_kind, "élément"], [_from] call _esc, _hh, ["", format [" · %1", _where]] select (_where isNotEqualTo "")],
            [[["VOIR", "FERMER"] select (_view isEqualTo _id), ["view", _id] call _act, false, _canView]], [[0.55, 0.6, 0.58, 1], _acc] select (_view isEqualTo _id)];
        if (_view isEqualTo _id && {_canView}) then {
            private _lines = ["sheetLines", [_kind, _data]] call comspec_atak_native_fnc_btItems;
            _rows pushBack ["text", (_lines apply { format ["<t size='0.8' color='#8a9a93'>%1</t>  <t size='0.85'>%2</t>", [_x select 0] call _esc, [_x select 1] call _esc] }) joinString "<br/>"];
        };
    } forEach ((+_inbox) select [((count _inbox) - 8) max 0]);
};

// Envois récents
private _sent = _bt get "sent";
if ((count _sent) > 0) then {
    _rows pushBack ["section", "Envois récents", ""];
    _rows pushBack ["text", ((+_sent) apply { format ["<t size='0.85'>%1 → %2 · <t color='%3'>%4</t></t>", [_x select 2] call _esc, [_x select 1] call _esc,
        switch (_x select 3) do { case "ACCEPTÉ"; case "REÇU"; case "ENVOYÉ": { _accHex }; case "REFUSÉ"; case "ÉCHEC"; case "HORS PORTÉE": { "#e5483a" }; default { "#8a9a93" }; }, toLower (_x select 3)] }) joinString "<br/>"];
};
[_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
true
