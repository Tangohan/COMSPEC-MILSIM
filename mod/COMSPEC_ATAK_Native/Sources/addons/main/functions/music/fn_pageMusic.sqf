/*
    App Musique : onglets LECTURE (lecteur, volume, haut-parleur, file d'attente, haut-parleurs proches),
    FICHIERS (dossier local Documents\Arma 3\COMSPEC_Music), SERVEUR (pistes Arma, mods, mission et radio de la mission)
    et LIEN (fichier audio ou webradio par URL).
*/
disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];
private _m = [] call comspec_atak_native_fnc_musicState;
private _ui = uiNamespace getVariable ["COMSPEC_ATAK_MusicUi", createHashMap];
uiNamespace setVariable ["COMSPEC_ATAK_MusicUi", _ui];
private _tab = _ui getOrDefault ["tab", "PLAY"];
private _range = round (missionNamespace getVariable ["comspec_atak_native_music_range", 30]);
private _icon = "\z\comspec_atak_native\addons\main\data\app_music.paa";
([] call comspec_atak_native_fnc_accent) params ["_acc", "_accHex"];
private _esc = { params ["_t"]; { _t = [_t, _x select 0, _x select 1] call CBA_fnc_replace; } forEach [["&", "&amp;"], ["<", "&lt;"], [">", "&gt;"]]; _t };
private _fmt = { params ["_s"]; if (_s <= 0) exitWith { "" }; _s = floor _s; format ["%1:%2", floor (_s / 60), [str (_s mod 60), "0" + str (_s mod 60)] select ((_s mod 60) < 10)] };
private _act = { params ["_a", ["_v", ""]]; compile format ["[%1, %2] call comspec_atak_native_fnc_musicAction;", str _a, str _v] };
private _tabBtn = { params ["_t", "_k"]; [_t, ["tab", _k] call _act, _tab isEqualTo _k] };
private _rows = [["segment", "", [["LECTURE", "PLAY"] call _tabBtn, ["FICHIERS", "LOCAL"] call _tabBtn, ["SERVEUR", "SRV"] call _tabBtn, ["LIEN", "URL"] call _tabBtn]]];
if !(missionNamespace getVariable ["comspec_atak_native_music_enabled", true]) exitWith {
    _rows pushBack ["text", "<t color='#f2ab33'>La musique est désactivée sur ce serveur.</t>"];
    [_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
    true
};
// Listes affichées, rangées par clé : les boutons y renvoient sans recopier la liste dans leur code.
private _lists = createHashMap;
uiNamespace setVariable ["COMSPEC_ATAK_MusicLists", _lists];
private _listRef = { params ["_key"]; format ["((uiNamespace getVariable ['COMSPEC_ATAK_MusicLists', createHashMap]) getOrDefault [%1, []])", str _key] };
// Ligne de piste : vignette, titre, durée, boutons LIRE et + FILE.
private _trackRow = {
    params ["_key", "_i", ["_sub", ""]];
    private _items = _lists getOrDefault [_key, []];
    (_items select _i) params ["_k", "_r", "_t", ["_len", 0]];
    private _isCur = (_m get "kind") isEqualTo _k && {(_m get "ref") isEqualTo _r};
    ["person", _icon, format ["<t %1>%2</t><br/><t size='0.75' color='#8a9a93'>%3%4</t>", ["", format ["color='%1' font='RobotoCondensedBold'", _accHex]] select _isCur,
        [_t] call _esc, [_sub] call _esc, ["", format [" · %1", [_len] call _fmt]] select (_len > 0)],
        [["LIRE", compile format ["['playList', [%1, %2]] call comspec_atak_native_fnc_musicAction;", [_key] call _listRef, _i], true],
         ["+ FILE", compile format ["['enqueue', +(%1 select %2)] call comspec_atak_native_fnc_musicAction;", [_key] call _listRef, _i]]],
        [[0.55, 0.6, 0.58, 1], _acc] select _isCur]
};

switch (_tab) do {
    case "LOCAL": {
        private _files = _ui getOrDefault ["files", []];
        _rows append [
            ["section", "Mes fichiers", format ["%1 morceau(x)", count _files]],
            ["text", format ["<t size='0.8' color='#8a9a93'>Déposez vos MP3, WAV, WMA, M4A ou AAC dans<br/></t><t size='0.8'>%1</t><t size='0.8' color='#8a9a93'><br/>puis ACTUALISER. Les sous-dossiers comptent.</t>", [_ui getOrDefault ["dir", "Documents\Arma 3\COMSPEC_Music"]] call _esc]],
            ["buttons", [["ACTUALISER", ["files"] call _act], ["OUVRIR LE DOSSIER", ["openFolder"] call _act], ["TOUT LIRE", compile format ["['playList', [%1, 0]] call comspec_atak_native_fnc_musicAction;", ["local"] call _listRef], true, (count _files) > 0]]]
        ];
        if ((_ui getOrDefault ["filesErr", ""]) isNotEqualTo "") then { _rows pushBack ["text", format ["<t color='#e5483a'>%1</t>", [_ui get "filesErr"] call _esc]]; };
        if ((count _files) isEqualTo 0 && {(_ui getOrDefault ["filesErr", ""]) isEqualTo ""}) then { _rows pushBack ["text", "<t color='#8a9a93'>Aucun fichier audio dans le dossier.</t>"]; };
        _lists set ["local", _files apply { private _p = _x splitString "\/"; ["file", _x, (_p select -1) regexReplace ["\.[^.]+$", ""], 0] }];
        for "_i" from 0 to (((count _files) min 120) - 1) do {
            private _p = (_files select _i) splitString "\/";
            _rows pushBack (["local", _i, [(_p select [0, (count _p) - 1]) joinString " / ", "Dossier principal"] select ((count _p) < 2)] call _trackRow);
        };
        _rows pushBack ["text", format ["<t size='0.75' color='#8a9a93'>En haut-parleur, les autres joueurs n'entendent un fichier local que s'ils ont le même fichier dans leur dossier. Pour une écoute commune, préférez SERVEUR ou LIEN.</t>"]];
    };
    case "SRV": {
        ([] call comspec_atak_native_fnc_musicServerList) params ["_tracks", "_radio"];
        if ((count _radio) > 0) then {
            _rows pushBack ["section", "Radio de la mission", "Liens choisis par l'organisateur"];
            _lists set ["radio", _radio apply { ["url", _x select 1, _x select 0, 0] }];
            { _rows pushBack (["radio", _forEachIndex, "Radio"] call _trackRow); } forEach _radio;
        };
        private _cats = createHashMap;
        { _cats set [_x select 3, (_cats getOrDefault [_x select 3, 0]) + 1]; } forEach _tracks;
        private _catList = (keys _cats) apply { [_cats get _x, _x] };
        _catList sort false;
        _catList = (_catList select [0, 6]) apply { _x select 1 };
        private _cat = _ui getOrDefault ["cat", ""];
        private _q = toLower (_ui getOrDefault ["q", ""]);
        private _list = _tracks select { (_cat isEqualTo "" || {(_x select 3) isEqualTo _cat}) && {_q isEqualTo "" || {((toLower (_x select 1)) find _q) >= 0} || {((toLower (_x select 0)) find _q) >= 0}} };
        _rows append [
            ["section", "Bibliothèque du serveur", format ["%1 piste(s) · Arma, mods et mission, entendues de tous", count _list]],
            ["edit", "musicSearch", "Rechercher un titre", _ui getOrDefault ["q", ""]],
            ["buttons", [["RECHERCHER", ["srvSearch"] call _act, true], ["EFFACER", { (uiNamespace getVariable ["COMSPEC_ATAK_MusicUi", createHashMap]) set ["q", ""]; ["srvPage", 0] call comspec_atak_native_fnc_musicAction; }]]],
            ["segment", "Genre", ([["TOUT", ["srvCat", ""] call _act, _cat isEqualTo ""]] + (_catList apply { [toUpper _x, ["srvCat", _x] call _act, _cat isEqualTo _x] }))]
        ];
        private _per = 20;
        private _page = (_ui getOrDefault ["page", 0]) min (floor (((count _list) - 1) / _per)) max 0;
        _lists set ["srv", _list apply { ["srv", _x select 0, _x select 1, _x select 2] }];
        for "_i" from (_page * _per) to (((_page + 1) * _per min (count _list)) - 1) do {
            _rows pushBack (["srv", _i, (_list select _i) select 3] call _trackRow);
        };
        if ((count _list) > _per) then {
            _rows pushBack ["buttons", [
                ["PRÉCÉDENTS", ["srvPage", _page - 1] call _act, false, _page > 0],
                [format ["%1 / %2", _page + 1, ceil ((count _list) / _per)], {}, false, false],
                ["SUIVANTS", ["srvPage", _page + 1] call _act, false, ((_page + 1) * _per) < (count _list)]
            ]];
        };
        _rows pushBack ["text", "<t size='0.75' color='#8a9a93'>Ces pistes passent par le moteur d'Arma : le volume dépend aussi du réglage Musique d'Arma (Options > Audio).</t>"];
    };
    case "URL": {
        _rows append [
            ["section", "Lire un lien", "Fichier audio ou webradio"],
            ["edit", "musicUrl", "Adresse (https://…/morceau.mp3)", ""],
            ["edit", "musicUrlTitle", "Titre (facultatif)", ""],
            ["buttons", [["LIRE", ["url"] call _act, true]]],
            ["text", "<t size='0.75' color='#8a9a93'>Lien direct vers un MP3, WAV, WMA, M4A ou AAC (80 Mo max, gardé en cache) ou flux de webradio MP3. Les pages YouTube, Spotify ou Deezer ne sont pas des fichiers audio et ne sont pas lisibles. Avec le haut-parleur, chaque joueur proche télécharge le même lien.</t>"]
        ];
        if !(missionNamespace getVariable ["comspec_atak_native_music_urls", true]) then { _rows pushBack ["text", "<t color='#f2ab33'>Lecture de liens interdite sur ce serveur.</t>"]; };
        private _rec = profileNamespace getVariable ["COMSPEC_ATAK_MusicUrls", []];
        if ((count _rec) > 0) then {
            _rows pushBack ["section", "Récents", ""];
            _lists set ["recent", _rec apply { ["url", _x select 1, _x select 0, 0] }];
            {
                private _row = ["recent", _forEachIndex, _x select 1] call _trackRow;
                (_row select 3) set [1, ["RETIRER", ["urlDel", _forEachIndex] call _act]];
                _rows pushBack _row;
            } forEach _rec;
        };
    };
    default {
        // Onglets en haut, lecteur dessous, réglages et listes défilants en bas.
        private _tabH = (_l get "font") * 1.55 * 0.9 + (_l get "pad") * 3.2;
        [[_rows deleteAt 0], [0, 0, _bw, _tabH]] call comspec_atak_native_fnc_formRender;
        private _vizH = (_bh * 0.28) max ((_l get "font") * 6);
        [[0, _tabH, _bw, _vizH]] call comspec_atak_native_fnc_vizPlayer;
        _vizH = _vizH + _tabH;
        private _mine = (_m get "kind") isNotEqualTo "";
        private _vol = _m get "vol";
        private _spkVol = _m getOrDefault ["spkVol", 70];
        private _rep = _m get "repeat";
        _rows append [
            ["buttons", [
                ["PRÉC.", ["prev"] call _act],
                [["LECTURE", "PAUSE"] select (_mine && {!(_m get "paused")}), ["toggle"] call _act, true],
                ["SUIV.", ["next"] call _act],
                ["STOP", ["stop"] call _act, false, _mine]
            ]],
            ["segment", "Volume des écouteurs", [
                ["-", ["volStep", -10] call _act],
                [["MUET", (str _vol) + " %"] select (_vol > 0), ["vol", [0, profileNamespace getVariable ["COMSPEC_ATAK_MusicVolBack", 60]] select (_vol isEqualTo 0)] call _act, !(_m get "speaker")],
                ["+", ["volStep", 10] call _act]
            ], "Vous seul entendez (haut-parleur coupé)."],
            ["segment", "Volume du haut-parleur", [
                ["-", ["spkVolStep", -10] call _act],
                [["MUET", (str _spkVol) + " %"] select (_spkVol > 0), ["spkVol", [0, profileNamespace getVariable ["COMSPEC_ATAK_MusicSpkVolBack", 70]] select (_spkVol isEqualTo 0)] call _act, _m get "speaker"],
                ["+", ["spkVolStep", 10] call _act]
            ], "Vous et les joueurs proches entendez (haut-parleur activé)."],
            ["switch", "Haut-parleur", _m get "speaker", ["speaker"] call _act, format ["Les joueurs à moins de %1 m entendent votre musique, plus fort en s'approchant (atténué entre l'intérieur et l'extérieur d'un véhicule).", _range], !(missionNamespace getVariable ["comspec_atak_native_music_speaker", true])],
            ["switch", "Entendre les haut-parleurs proches", _m get "hear", ["hear"] call _act, "Quand vous n'écoutez rien, la musique d'un téléphone voisin passe dans votre casque."],
            ["segment", "Répéter", [["NON", ["repeat", "off"] call _act, _rep isEqualTo "off"], ["LA FILE", ["repeat", "all"] call _act, _rep isEqualTo "all"], ["LE MORCEAU", ["repeat", "one"] call _act, _rep isEqualTo "one"]]],
            ["switch", "Lecture aléatoire", _m get "shuffle", ["shuffle"] call _act, "Mélange la liste au lancement."]
        ];
        private _q = _m get "queue";
        _rows pushBack ["section", "File d'attente", format ["%1 morceau(x)", count _q]];
        if ((count _q) isEqualTo 0) then { _rows pushBack ["text", "<t color='#8a9a93'>Vide. Lancez un morceau depuis FICHIERS, SERVEUR ou LIEN.</t>"]; };
        private _src = createHashMapFromArray [["file", "Fichier local"], ["url", "Lien web"], ["srv", "Serveur"]];
        for "_i" from 0 to (((count _q) min 40) - 1) do {
            (_q select _i) params ["_k", "_r", "_t", ["_len", 0]];
            private _isCur = _i isEqualTo (_m get "qi") && {_mine};
            _rows pushBack ["person", _icon, format ["<t %1>%2. %3</t><br/><t size='0.75' color='#8a9a93'>%4%5</t>", ["", format ["color='%1' font='RobotoCondensedBold'", _accHex]] select _isCur, _i + 1, [_t] call _esc,
                _src getOrDefault [_k, ""], ["", format [" · %1", [_len] call _fmt]] select (_len > 0)],
                [[["JOUER", "EN COURS"] select _isCur, ["jump", _i] call _act, !_isCur, !_isCur]], [[0.55, 0.6, 0.58, 1], _acc] select _isCur];
        };
        // Haut-parleurs autour de moi
        private _near = [];
        {
            private _s = _x getVariable ["COMSPEC_ATAK_Spk", []];
            if ((count _s) >= 6 && {alive _x} && {(_x distance player) < 150}) then { _near pushBack [_x distance player, _x, _s]; };
        } forEach (allPlayers - [player]);
        _near sort true;
        _rows pushBack ["section", "Haut-parleurs autour de moi", format ["Audibles à moins de %1 m", _range]];
        if ((count _near) isEqualTo 0) then { _rows pushBack ["text", "<t color='#8a9a93'>Aucun téléphone en haut-parleur à proximité.</t>"]; };
        {
            _x params ["_d", "_u", "_s"];
            private _in = _d < _range;
            _rows pushBack ["text", format ["<t font='RobotoCondensedBold'>%1</t>  <t color='#8a9a93'>%2 m</t>  <t color='%3'>%4</t><br/><t size='0.8'>%5</t>",
                [name _u] call _esc, round _d, ["#8a9a93", _accHex] select _in, ["hors de portée", "audible"] select _in, [_s select 2] call _esc]];
        } forEach (_near select [0, 8]);
        [_rows, [0, _vizH, _bw, _bh - _vizH]] call comspec_atak_native_fnc_formRender;
    };
};
if (_tab isNotEqualTo "PLAY") then { [_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender; };
true
