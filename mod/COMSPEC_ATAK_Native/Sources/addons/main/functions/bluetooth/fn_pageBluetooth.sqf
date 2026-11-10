/*
    App Bluetooth (4.1, refaite) : l'état en tête (activé, visible, portée), une action principale par situation, puis dans
    l'ordre d'usage : demandes reçues, transferts (progression sur place), appareils appairés (ENVOYER, OUBLIER), téléphones
    détectés à portée (distance et type, mis à jour sans redessiner, fn_btAction « scan »), appairage par code, éléments reçus,
    envois récents avec leur sorte et leur résultat, mode avion en dernier.
    et envoi à un appareil appairé, par onglet : SONS et MUSIQUE (joués par son haut-parleur, ou mes morceaux copiés
    dans son dossier), PHOTOS (copiées dans sa Galerie), DONNÉES (position, itinéraire, plans) et FICHES (SEEK, SSE,
    CONTRÔLE ID). Vue dédiée d'une fiche CONTRÔLE ID (clé idcView de COMSPEC_ATAK_BtUi, fn_btAction « idcOpen ») :
    verdict, document, photo, niveaux, contrôleur, heure, position, et partage (appareils appairés, NFC dos à dos),
    carte et FRM ; affichée même Bluetooth éteint (le NFC n'en a pas besoin).
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
    ["seek", _dataDir + "app_sse.paa"], ["sse", _dataDir + "app_sse.paa"], ["idc", _dataDir + "app_sse.paa"], ["note", _dataDir + "app_notes.paa"]];
private _kindName = createHashMapFromArray [["snd", "son"], ["mus", "musique"], ["mp3", "morceau (fichier)"], ["pho", "photo"], ["pos", "position"],
    ["wp", "itinéraire"], ["plan", "plan"], ["seek", "fiche SEEK"], ["sse", "fiche SSE"], ["idc", "papiers (contrôle ID)"], ["note", "note"]];
private _esc = { params ["_t"]; { _t = [_t, _x select 0, _x select 1] call CBA_fnc_replace; } forEach [["&", "&amp;"], ["<", "&lt;"], [">", "&gt;"]]; _t };
private _act = { params ["_a", ["_v", ""]]; compile format ["[%1, %2] call comspec_atak_native_fnc_btAction;", str _a, str _v] };
// Vue dédiée d'une fiche CONTRÔLE ID (reçue ou à partager).
private _idcKey = _ui getOrDefault ["idcView", ""];
// Touche retour (fn_back) : referme le sous-écran ouvert (fiche CONTRÔLE ID, envoi à un appareil, élément reçu déplié)
// au lieu de quitter l'app.
private _st0 = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
if (_idcKey isNotEqualTo "") exitWith {
    _st0 set ["backSub", [_page, ["idcClose"] call _act]];
    private _rows = ["idcRows", _idcKey] call comspec_atak_native_fnc_btItems;
    private _have = (count (["idcGet", _idcKey] call comspec_atak_native_fnc_btItems)) > 0;
    _rows pushBack ["buttons", [["‹ RETOUR", ["idcClose"] call _act], ["CARTE", ["idcMap", _idcKey] call _act, false, _have], ["JOINDRE À UNE FRM", ["idcFrm", _idcKey] call _act, true, _have]]];
    if (_have) then {
        _rows pushBack ["section", "Partager la fiche", format ["Bluetooth : appareils appairés à moins de %1 m · NFC : téléphones dos à dos", round _range]];
        if (_usable) then {
            private _near = [];
            {
                private _uid = _x;
                private _i = allPlayers findIf { getPlayerUID _x isEqualTo _uid };
                private _u = if (_i < 0) then { objNull } else { allPlayers select _i };
                if (!isNull _u && {_u getVariable ["COMSPEC_ATAK_BtOn", false]} && {(_u distance player) <= _range}) then { _near pushBack [_uid, _y, round (_u distance player)]; };
            } forEach (_bt get "paired");
            if ((count _near) isEqualTo 0) then { _rows pushBack ["text", "<t color='" + (COMSPEC_ATAK_Th get "hexTextMuted") + "'>Aucun appareil appairé à portée. Appairez-le dans l'app Bluetooth (code), ou passez par le NFC.</t>"]; };
            {
                _x params ["_uid", "_name", "_dist"];
                _rows pushBack ["person", _icon, format ["<t font='COMSPEC_BarlowBold'>ATAK de %1</t><br/><t size='0.8' color='%2'>connecté · %3 m</t>", [_name] call _esc, _accHex, _dist],
                    [["ENVOYER", ["idcSend", [_idcKey, _uid]] call _act, true]], _acc];
            } forEach _near;
        } else {
            _rows pushBack ["text", "<t color='" + (COMSPEC_ATAK_Th get "hexTextMuted") + "'>Bluetooth éteint : allumez-le pour envoyer la fiche à un appareil appairé.</t>"];
            _rows pushBack ["buttons", [["ALLUMER LE BLUETOOTH", ["toggle"] call _act, false]]];
        };
        _rows pushBack ["buttons", [["NFC · DOS À DOS", ["idcNfc", _idcKey] call _act, false, ([] call comspec_atak_native_fnc_canUse) isEqualTo "",
            format ["Posez votre téléphone contre celui d'un équipier (moins de %1 m) : il accepte ou refuse la fiche", missionNamespace getVariable ["comspec_atak_native_bt_nfc_range", 2]]]]];
    };
    [_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
    true
};
// Demandes NFC (fiches CONTRÔLE ID) : acceptées même Bluetooth éteint.
private _pendRows = {
    params ["_list"];
    {
        _x params ["_id", "", "_name", "_kind", "", "_title", "_at", ["_nfc", false]];
        _rows pushBack ["person", _kindIcon getOrDefault [_kind, _icon], format ["<t font='COMSPEC_BarlowBold'>%1</t><br/><t size='0.8' color='" + (COMSPEC_ATAK_Th get "hexTextMuted") + "'>%2 · %3%4 · expire dans %5 s</t>",
            [_title] call _esc, [_name] call _esc, _kindName getOrDefault [_kind, "élément"], ["", " · NFC"] select (_nfc isEqualTo true), round (60 - (_now - _at))],
            [["ACCEPTER", ["accept", _id] call _act, true], ["REFUSER", ["refuse", _id] call _act]], _acc];
    } forEach _list;
};
private _muted = COMSPEC_ATAK_Th get "hexTextMuted";
private _rows = [
    ["switch", "Bluetooth", _on, ["toggle"] call _act, [format ["Désactivé : invisible des autres téléphones"], format ["Activé : ATAK de %1, visible à moins de %2 m", [name player] call _esc, round _range]] select _on]
];
if (!_on) exitWith {
    _rows pushBack ["text", "<t color='" + _muted + "'>Activez-le pour appairer un ATAK proche et échanger photos, musiques, notes, données et fiches.</t>"];
    _rows pushBack ["buttons", [["ACTIVER LE BLUETOOTH", ["toggle"] call _act, true]]];
    private _nfcPend = (_bt get "pending") select { (_now - (_x select 6)) <= 60 && {(_x param [7, false]) isEqualTo true} };
    if (_nfcPend isNotEqualTo []) then {
        _rows pushBack ["section", "Demandes NFC", "Fiches de contrôle d'identité tendues dos à dos"];
        [_nfcPend] call _pendRows;
    };
    _rows pushBack ["switch", "Mode avion", _air, { ["toggle"] call comspec_atak_native_fnc_airplaneMode; }, "Coupe le réseau (synchro, SMS, BFT). Le Bluetooth reste disponible."];
    [_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
    true
};
if (!_usable) then { _rows pushBack ["text", "<t color='" + (COMSPEC_ATAK_Th get "hexWarn") + "'>Téléphone indisponible : Bluetooth inactif.</t>"]; };

// Demandes reçues
private _pend = (_bt get "pending") select { (_now - (_x select 6)) <= 60 };
if ((count _pend) > 0) then {
    _rows pushBack ["section", "Demandes reçues", "Un son sort du haut-parleur ; un fichier, une donnée ou une fiche est rangé sur le téléphone"];
    [_pend] call _pendRows;
};

// Transferts en cours (envoi et réception de fichiers).
private _xf = _bt getOrDefault ["xfers", createHashMap];
// Texte d'un transfert, aussi utilisé par fn_btAction pour avancer la barre sans redessiner (ligne « btx_ » + id).
uiNamespace setVariable ["COMSPEC_ATAK_BtXferTxt", {
    params ["_t"];
    private _th = [] call comspec_atak_native_fnc_theme;
    private _pct = floor (100 * (_t getOrDefault ["done", 0]) / ((_t getOrDefault ["total", 0]) max 1));
    private _full = floor (_pct / 5);
    private _bar = "";
    for "_i" from 1 to 20 do { _bar = _bar + (["-", "|"] select (_i <= _full)); };
    private _esc = { params ["_v"]; (_v regexReplace ["&", "&amp;"]) regexReplace ["<", "&lt;"] };
    format ["<t font='COMSPEC_BarlowBold'>%1</t><br/><t size='0.85' color='%2'>%3 %4 · %5 %6  </t><t size='0.8' font='EtelkaMonospaceProBold' color='%7'>%8</t><t size='0.8' font='EtelkaMonospacePro' color='%9'>%10</t>",
        [_t get "title"] call _esc, _th get "hexTextDim", ["De", "Vers"] select ((_t get "dir") isEqualTo "out"), [_t get "name"] call _esc, _pct, "%",
        _th get "hexAccent", _bar select [0, _full], _th get "hexTextMuted", _bar select [_full]]
}];
if ((count _xf) > 0) then {
    _rows pushBack ["section", "Transferts en cours", "Restez à portée jusqu'à la fin"];
    {
        _rows pushBack ["person", _kindIcon getOrDefault [_y get "kind", _icon], [_y] call (uiNamespace getVariable "COMSPEC_ATAK_BtXferTxt"),
            [["ANNULER", ["cancel", _x] call _act]], _acc, "", "btx_" + _x];
    } forEach _xf;
};
if ((_bt get "playing") isNotEqualTo "") then { _rows pushBack ["buttons", [["ARRÊTER LE SON EN COURS", ["stopPlay"] call _act, true]]]; };

// Appareils appairés
private _paired = _bt get "paired";
private _target = _ui getOrDefault ["target", ""];
if !(_target in _paired) then { _target = ""; _ui set ["target", ""]; };
_rows pushBack ["section", "Appareils appairés", format ["%1 appareil(s) · gardés pour la mission", count _paired]];
if ((count _paired) isEqualTo 0) then { _rows pushBack ["text", "<t color='" + _muted + "'>Aucun pour l'instant : appairez un téléphone détecté ci-dessous avec son code.</t>"]; };
{
    private _uid = _x;
    private _name = _y;
    private _i = allPlayers findIf { getPlayerUID _x isEqualTo _uid };
    private _u = if (_i < 0) then { objNull } else { allPlayers select _i };
    private _st = switch (true) do {
        case (isNull _u): { "<t color='" + (COMSPEC_ATAK_Th get "hexTextMuted") + "'>déconnecté</t>" };
        case !(_u getVariable ["COMSPEC_ATAK_BtOn", false]): { "<t color='" + (COMSPEC_ATAK_Th get "hexTextMuted") + "'>Bluetooth éteint</t>" };
        case ((_u distance player) > _range): { format ["<t color='" + (COMSPEC_ATAK_Th get "hexWarn") + "'>hors de portée · %1 m</t>", round (_u distance player)] };
        default { format ["<t color='%1'>connecté · %2 m</t>", _accHex, round (_u distance player)] };
    };
    _rows pushBack ["person", _icon, format ["<t font='COMSPEC_BarlowBold'>ATAK de %1</t><br/><t size='0.8'>%2</t>", [_name] call _esc, _st],
        [[["ENVOYER", "FERMER"] select (_target isEqualTo _uid), ["target", _uid] call _act, _target isNotEqualTo _uid], ["OUBLIER", ["unpair", _uid] call _act]],
        [COMSPEC_ATAK_Th get "textDim", _acc] select (_target isEqualTo _uid)];
} forEach _paired;

// Envoi à l'appareil choisi : un onglet par sorte d'élément.
if (_target isNotEqualTo "") then {
    // « target » sur l'appareil déjà choisi : referme l'envoi (même effet que FERMER).
    _st0 set ["backSub", [_page, ["target", _target] call _act]];
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
        case "data": { "Position (repère 10 min), itinéraire (app Points de passage), plans (app Plan), notes (Bloc-notes)" };
        default { "Fiches SEEK (journal du terminal), fiches SSE (personnes enregistrées au SEEK) et fiches CONTRÔLE ID" };
    };
    _rows append [
        ["section", format ["Envoyer à %1", [_paired getOrDefault [_target, ""]] call _esc], _hint],
        ["tabs", [["SONS", ["tab", "snd"] call _act, _tab isEqualTo "snd"], ["MUSIQUE", ["tab", "mus"] call _act, _tab isEqualTo "mus"], ["PHOTOS", ["tab", "pho"] call _act, _tab isEqualTo "pho"],
            ["DONNÉES", ["tab", "data"] call _act, _tab isEqualTo "data"], ["FICHES", ["tab", "sheet"] call _act, _tab isEqualTo "sheet"]]],
        ["field", "btSearch", "Rechercher", _ui getOrDefault ["q", ""], "OK", ["search"] call _act]
    ];
    private _per = 15;
    private _pg = (_ui getOrDefault ["page", 0]) min (floor (((count _all) - 1) / _per)) max 0;
    if ((count _all) isEqualTo 0) then {
        _rows pushBack ["text", format ["<t color='" + (COMSPEC_ATAK_Th get "hexTextMuted") + "'>%1</t>", switch (_tab) do {
            case "pho": { ["Aucune photo sur le téléphone. Prenez-en avec l'app Photo.", "Photos indisponibles : liaison Overwatch absente."] select !([] call comspec_atak_native_fnc_bridge) };
            case "mus": { "Aucun morceau. Déposez des MP3 dans Documents\Arma 3\COMSPEC_Music (app Musique, Mes fichiers)." };
            case "sheet": { "Aucune fiche. Elles viennent du terminal SEEK : interrogations (fiches SEEK) et personnes enregistrées (fiches SSE), et de l'app Contrôle ID (fiches CONTRÔLE ID)." };
            default { "Rien trouvé." };
        }]];
    };
    for "_i" from (_pg * _per) to (((_pg + 1) * _per min (count _all)) - 1) do {
        (_all select _i) params ["_kind", "_ref", "_title", "_cat", ["_len", 0]];
        _rows pushBack ["person", _kindIcon getOrDefault [_kind, _icon],
            format ["%1<br/><t size='0.75' color='" + (COMSPEC_ATAK_Th get "hexTextMuted") + "'>%2%3</t>", [_title] call _esc, [_cat] call _esc, ["", format [" · %1:%2", floor (_len / 60), [str (floor _len mod 60), "0" + str (floor _len mod 60)] select ((floor _len mod 60) < 10)]] select (_len > 0)],
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

// Téléphones détectés, non appairés : distance et type, à jour toutes les 3 s sans redessiner (ligne « btn_ » + uid).
uiNamespace setVariable ["COMSPEC_ATAK_BtNearTxt", {
    params ["_name", "_dist", "_type"];
    private _esc = { params ["_v"]; (_v regexReplace ["&", "&amp;"]) regexReplace ["<", "&lt;"] };
    format ["<t font='COMSPEC_BarlowBold'>ATAK de %1</t><br/><t size='0.85' color='%2'>%3%4 m</t>", [_name] call _esc, COMSPEC_ATAK_Th get "hexTextDim", ["", format ["%1 · ", [_type] call _esc]] select (_type isNotEqualTo ""), _dist]
}];
private _near = (["near"] call comspec_atak_native_fnc_btAction) select { !(_x select 4) };
_rows pushBack ["section", "Téléphones détectés", format ["Bluetooth allumé, à moins de %1 m", round _range]];
if (_near isEqualTo []) then {
    _rows pushBack ["text", format ["<t color='%1'>Personne à portée. Approchez-vous à moins de %2 m d'un équipier : son Bluetooth doit être activé.</t>", _muted, round _range]];
};
{
    _x params ["_uid", "_name", "_dist", "_type"];
    _rows pushBack ["person", _icon, [_name, _dist, _type] call (uiNamespace getVariable "COMSPEC_ATAK_BtNearTxt"), [], COMSPEC_ATAK_Th get "textDim", "", "btn_" + _uid];
} forEach _near;
// Appairage par code : l'un montre son code, l'autre le saisit.
private _code = _bt get "code";
private _codeOn = _code isNotEqualTo "" && {_now < (_bt get "codeUntil")};
_rows pushBack ["section", "Appairer", "L'un affiche son code, l'autre le saisit"];
if (_codeOn) then {
    _rows pushBack ["text", format ["<t align='center' size='2' font='COMSPEC_BarlowBold' color='%1'>%2 %3</t><br/><t align='center' size='0.85' color='" + _muted + "'>Mon code · valable encore %4 s · à saisir sur l'autre téléphone</t>",
        COMSPEC_ATAK_Th get "hexAccent", _code select [0, 3], _code select [3, 3], round ((_bt get "codeUntil") - _now)]];
    _rows pushBack ["buttons", [["CACHER LE CODE", ["hideCode"] call _act]]];
} else {
    _rows pushBack ["buttons", [["AFFICHER MON CODE", ["showCode"] call _act, (count _paired) isEqualTo 0, _usable]]];
};
_rows pushBack ["field", "btCode", "Code affiché par l'autre téléphone", "", "APPAIRER", ["enterCode"] call _act];

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
            case "idc": { "vue dédiée" };
            default { "" };
        };
        private _canView = !(_kind in ["pho", "mp3"]);
        // Fiche CONTRÔLE ID : VOIR ouvre sa vue dédiée.
        private _open = if (_kind isEqualTo "idc") then {
            private _k = (createHashMapFromArray ((_data select { _x isEqualType [] && {(count _x) isEqualTo 2} }))) getOrDefault ["key", ""];
            ["idcOpen", _k] call _act
        } else { ["view", _id] call _act };
        _rows pushBack ["person", _kindIcon getOrDefault [_kind, _icon], format ["<t font='COMSPEC_BarlowBold'>%1</t><br/><t size='0.8' color='" + (COMSPEC_ATAK_Th get "hexTextMuted") + "'>%2 · de %3 · %4%5</t>",
            [_title] call _esc, _kindName getOrDefault [_kind, "élément"], [_from] call _esc, _hh, ["", format [" · %1", _where]] select (_where isNotEqualTo "")],
            [[["VOIR", "FERMER"] select (_view isEqualTo _id), _open, false, _canView]], [COMSPEC_ATAK_Th get "textDim", _acc] select (_view isEqualTo _id)];
        if (_view isEqualTo _id && {_canView} && {_kind isNotEqualTo "idc"}) then {
            // Élément reçu déplié : retour le replie (prioritaire sur l'envoi, dessiné plus haut).
            _st0 set ["backSub", [_page, ["view", _id] call _act]];
            private _lines = ["sheetLines", [_kind, _data]] call comspec_atak_native_fnc_btItems;
            _rows pushBack ["text", (_lines apply { format ["<t size='0.8' color='" + (COMSPEC_ATAK_Th get "hexTextMuted") + "'>%1</t>  <t size='0.85'>%2</t>", [_x select 0] call _esc, [_x select 1] call _esc] }) joinString "<br/>"];
        };
    } forEach ((+_inbox) select [((count _inbox) - 8) max 0]);
};

// Envois récents : sorte de l'objet, destinataire, résultat en couleur.
private _sent = _bt get "sent";
if ((count _sent) > 0) then {
    _rows pushBack ["section", "Envois récents", ""];
    {
        _x params ["", "_to", "_title", "_st", ["_kind", ""]];
        private _hex = switch (_st) do { case "ACCEPTÉ"; case "REÇU"; case "ENVOYÉ": { COMSPEC_ATAK_Th get "hexOk" }; case "REFUSÉ"; case "ÉCHEC"; case "HORS PORTÉE": { COMSPEC_ATAK_Th get "hexAlert" }; default { _muted }; };
        _rows pushBack ["row", _kindIcon getOrDefault [_kind, _icon], _title, format ["%1 · vers %2 · <t color='%3'>%4</t>", (_kindName getOrDefault [_kind, "élément"]) call comspec_atak_native_fnc_tr, [_to] call _esc, _hex, toLower _st], "", {}];
    } forEach (+_sent);
};
_rows pushBack ["section", "Réseau", ""];
_rows pushBack ["switch", "Mode avion", _air, { ["toggle"] call comspec_atak_native_fnc_airplaneMode; }, "Coupe le réseau (synchro, SMS, BFT). Le Bluetooth reste disponible."];
[_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
true
