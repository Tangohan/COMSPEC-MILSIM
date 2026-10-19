/*
    Tinder, façon vraie app de rencontre.
      DÉCOUVRIR : une carte par profil, grande photo (vrai visage de la personne rendu en direct, fn_datingCam) avec trois
                  photos (portrait, buste, en pied : toucher la gauche ou la droite de l'image), prénom, âge, distance,
                  bio, centres d'intérêt, compatibilité ; PASSER, SUPER LIKE (1 / 10 min), J'AIME.
                  De temps en temps, un profil tiré des IA de la carte : HVT ou personne SSE (fn_datingSpecials), selon
                  le Zeus et le réglage de mission. Rien ne le signale : à vous de reconnaître le visage.
      MATCHS    : joueurs (SMS) et profils IA (conversation : indices de position, marquage sur la carte).
      MON PROFIL: aperçu de sa propre carte, bio, visibilité, likes reçus.
*/
disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];
private _font = _l get "font";
private _fs = _l get "fontSmall";
private _pad = (_l get "pad") * 2;
private _land = _l getOrDefault ["landscape", false];
private _ratio = uiNamespace getVariable ["COMSPEC_ATAK_Ratio", pixelH / pixelW];
private _mk = { params ["_c", "_p", ["_t", ""]]; [_c, _p, _t] call comspec_atak_native_fnc_pageCtrl };
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _tab = _s getOrDefault ["rcTab", "FIND"];
if !(_tab in ["FIND", "MATCH", "ME"]) then { _tab = "FIND"; };
private _shot = _s getOrDefault ["rcShot", 0];
private _icon = "\z\comspec_atak_native\addons\main\data\app_dating.paa";
private _esc = { params ["_t"]; if !(_t isEqualType "") then { _t = str _t; }; [[_t, "<", "&lt;"] call CBA_fnc_replace, ">", "&gt;"] call CBA_fnc_replace };
private _likes = missionNamespace getVariable ["COMSPEC_ATAK_RencardLikes", []];
private _likedBy = missionNamespace getVariable ["COMSPEC_ATAK_RencardLikedBy", []];
private _superBy = missionNamespace getVariable ["COMSPEC_ATAK_RencardSuperBy", []];
private _passed = missionNamespace getVariable ["COMSPEC_ATAK_RencardPassed", []];
private _visible = player getVariable ["COMSPEC_ATAK_Rencard", true];
private _pool = allPlayers select { _x isNotEqualTo player && {alive _x} && {_x getVariable ["COMSPEC_ATAK_Rencard", true]} && {[_x] call comspec_atak_native_fnc_hasDevice} };
private _matches = _pool select { private _u = getPlayerUID _x; _u in _likes && {_u in _likedBy} };
private _aiMatches = (missionNamespace getVariable ["COMSPEC_ATAK_RencardAiMatches", []]) select { !isNull (["unit", _x] call comspec_atak_native_fnc_datingSpecials) };
private _pink = "#ff5b7f";

// Image fixe d'une personne, jamais le cœur de l'app à la place d'une photo : portrait opérateur Athena (fn_avatarPath,
// joueurs), sinon rendu du personnage Arma (aperçu Eden de sa classe, à défaut celui du soldat de base de son camp).
private _figure = {
    params ["_u"];
    private _pic = [_u] call comspec_atak_native_fnc_avatarPath;
    if (_pic isNotEqualTo "") exitWith { _pic };
    _pic = getText (configOf _u >> "editorPreview");
    if (_pic isEqualTo "" || {!fileExists _pic}) then {
        _pic = format ["\A3\EditorPreviews_F\Data\CfgVehicles\%1.jpg", ["C_man_1", "B_Soldier_F", "O_Soldier_F", "I_soldier_F"] select (([civilian, west, east, resistance] find (side group _u)) max 0)];
    };
    _pic
};
// Vignette carrée des listes : portrait opérateur, sinon insigne de grade (l'aperçu Eden, en 16/9, serait déformé).
private _thumb = {
    params ["_u"];
    private _pic = [_u] call comspec_atak_native_fnc_avatarPath;
    if (_pic isEqualTo "") then { _pic = [_u, "texture"] call BIS_fnc_rankParams; };
    _pic
};

// Profil d'un joueur : bio choisie (ou tirée de lui), âge, métier (rôle du lobby ou classe), grade, compatibilité.
private _bios = ["Cherche binôme pour patrouilles au coucher du soleil.", "Fan de rations au poulet, allergique aux mines.", "Je connais toutes les grilles par cœur.", "Toujours le premier dans la pièce, le dernier à la pause.", "Ici pour la camaraderie et les MRE.", "Mon ex m'a laissé tomber en plein FRAGO.", "Le genre à vérifier deux fois sa radio.", "Je fais d'excellents cafés en bivouac.", "Pas de prise de tête, juste de la prise d'objectif.", "Je ne lis jamais le briefing, j'improvise."];
private _tags = ["Marches de nuit", "Café soluble", "Grilles 8 chiffres", "Feux de camp", "Parachutisme", "Tir longue distance", "Topographie", "Musique radio", "Cuisine de ration", "Sport en tenue", "Mécanique", "Premiers secours"];
private _seed = { params ["_k", "_n"]; private _h = 0; { _h = (_h * 31 + _x) mod 100003; } forEach toArray _k; (_h mod (_n max 1)) };
private _ranks = createHashMapFromArray [["PRIVATE", "Soldat"], ["CORPORAL", "Caporal"], ["SERGEANT", "Sergent"], ["LIEUTENANT", "Lieutenant"], ["CAPTAIN", "Capitaine"], ["MAJOR", "Commandant"], ["COLONEL", "Colonel"]];
private _playerProfile = {
    params ["_u"];
    private _k = getPlayerUID _u + name _u;
    private _bi = _u getVariable ["COMSPEC_ATAK_RencardBio", -1];
    if !(_bi isEqualType 0) then { _bi = -1; };
    private _role = trim ((roleDescription _u splitString "@") param [0, ""]);
    if (_role isEqualTo "") then { _role = getText (configOf _u >> "displayName"); };
    private _t1 = [_k, count _tags] call _seed;
    private _d = player distance2D _u;
    private _avatar = [_u] call comspec_atak_native_fnc_avatarPath;
    createHashMapFromArray [
        ["key", getPlayerUID _u], ["unit", _u], ["name", (name _u splitString " ") param [0, name _u]], ["age", 19 + ([_k + "a", 24] call _seed)],
        ["sub", format ["%1 · %2 · %3", _ranks getOrDefault [rank _u, "Soldat"], [_role] call _esc, [groupId group _u] call _esc]],
        ["dist", [format ["à %1 m", round _d], format ["à %1 km", (_d / 1000) toFixed 1]] select (_d >= 1000)],
        ["bio", _bios select ([[_k, count _bios] call _seed, _bi mod (count _bios)] select (_bi >= 0))],
        ["tags", [_tags select _t1, _tags select ((_t1 + 4) mod count _tags), _tags select ((_t1 + 7) mod count _tags)]],
        ["compat", 55 + ([(([getPlayerUID player, getPlayerUID _u] apply { _x }) call { _this sort true; _this joinString "" }), 45] call _seed)],
        ["portrait", _avatar], ["fallback", [_u] call _figure],
        ["likedMe", (getPlayerUID _u) in _likedBy], ["superMe", (getPlayerUID _u) in _superBy]
    ]
};
private _aiProfile = {
    params ["_u"];
    private _p = ["profile", _u] call comspec_atak_native_fnc_datingSpecials;
    private _k = ["key", _u] call comspec_atak_native_fnc_datingSpecials;
    createHashMapFromArray [
        ["key", _k], ["unit", _u], ["name", _p get "name"], ["age", _p get "age"], ["sub", ""], ["dist", _p get "dist"],
        ["bio", _p get "bio"], ["tags", _p get "tags"], ["compat", 60 + ([_k, 39] call _seed)], ["portrait", ""], ["fallback", [_u] call _figure],
        ["likedMe", false], ["superMe", false]
    ]
};

// Onglets.
private _tabH = _font * 1.3;
private _tabs = [["DÉCOUVRIR", "FIND"], [format ["MATCHS (%1)", (count _matches) + (count _aiMatches)], "MATCH"], ["MON PROFIL", "ME"]];
private _tw = (_bw - _pad * 2) / 3;
{
    _x params ["_txt", "_k"];
    private _b = ["COMSPEC_RscButton", [_pad + _forEachIndex * _tw, _pad / 2, _tw - _pad / 4, _tabH], _txt] call _mk;
    _b ctrlSetFontHeight (_fs * 0.95);
    if (_k isEqualTo _tab) then { _b ctrlSetBackgroundColor [1, 0.36, 0.5, 0.9]; _b ctrlSetTextColor [1, 1, 1, 1]; };
    _b ctrlAddEventHandler ["ButtonClick", compile format ["['tab', '%1'] call comspec_atak_native_fnc_datingAction;", _k]];
} forEach _tabs;
private _cy = _pad / 2 + _tabH + _pad / 2;
private _ch = _bh - _cy - _pad / 2;

// Carte de profil : 1re photo = portrait opérateur Athena s'il existe (sinon visage en direct), puis buste et en pied en
// direct ; sous la vue en direct, l'image fixe (portrait ou rendu du personnage) ; repères des 3 photos, bandeau du bas.
private _card = {
    params ["_p", "_rect"];
    _rect params ["_cx0", "_cy0", "_w", "_h"];
    private _bg = ["COMSPEC_RscText", [_cx0, _cy0, _w, _h]] call _mk;
    _bg ctrlSetBackgroundColor [0, 0, 0, 1];
    if (_shot isEqualTo 0 && {(_p get "portrait") isNotEqualTo ""}) then {
        ["stop"] call comspec_atak_native_fnc_datingCam;
        ["COMSPEC_RscSlide", [_cx0, _cy0, _w, _h], _p get "portrait"] call _mk;
    } else {
        ["COMSPEC_RscSlide", [_cx0, _cy0, _w, _h], _p get "fallback"] call _mk;
        private _tex = ["bind", [_p get "unit", _shot, _w / (_h * _ratio)]] call comspec_atak_native_fnc_datingCam;
        if (_tex isNotEqualTo "") then { ["COMSPEC_RscPhone", [_cx0, _cy0, _w, _h], _tex] call _mk; };
    };
    // Repères des photos.
    private _dw = (_w - _pad * 2) / 3;
    for "_i" from 0 to 2 do {
        private _d = ["COMSPEC_RscText", [_cx0 + _pad / 2 + _i * (_dw + _pad / 4), _cy0 + _pad / 3, _dw, pixelH * 3]] call _mk;
        _d ctrlSetBackgroundColor [1, 1, 1, [0.35, 0.95] select (_i isEqualTo _shot)];
    };
    // Toucher la moitié gauche ou droite : photo précédente ou suivante.
    {
        _x params ["_dx", "_step", "_tip"];
        private _hit = ["COMSPEC_RscButtonInvisible", [_cx0 + _dx * _w / 2, _cy0, _w / 2, _h * 0.7]] call _mk;
        _hit ctrlSetTooltip ((_tip) call comspec_atak_native_fnc_tr);
        _hit ctrlAddEventHandler ["ButtonClick", compile format ["['shot', %1] call comspec_atak_native_fnc_datingAction;", _step]];
    } forEach [[0, -1, "Photo précédente"], [1, 1, "Photo suivante"]];
    // Bandeau du bas.
    private _oh = _font * 2.7;
    private _ov = ["COMSPEC_RscText", [_cx0, _cy0 + _h - _oh, _w, _oh]] call _mk;
    _ov ctrlSetBackgroundColor [0, 0, 0, 0.62];
    _ov ctrlEnable false;
    private _t = ["COMSPEC_RscStructuredText", [_cx0 + _pad / 2, _cy0 + _h - _oh + _pad / 4, _w - _pad, _oh]] call _mk;
    _t ctrlEnable false;
    private _badge = switch (true) do {
        case (_p get "superMe"): { format ["  <t size='0.75' color='#5bc8ff'>SUPER LIKE</t>"] };
        case (_p get "likedMe"): { format ["  <t size='0.75' color='%1'>vous a liké</t>", _pink] };
        default { "" };
    };
    _t ctrlSetStructuredText parseText ((format [
        "<t size='1.45' font='COMSPEC_BarlowBold' color='#ffffff' shadow='2'>%1</t><t size='1.25' color='#ffffff' shadow='2'>  %2</t>%3<br/><t size='0.8' color='#e8eee9' shadow='2'>%4%5</t>",
        [_p get "name"] call _esc, _p get "age", _badge, _p get "dist", ["", " · " + (_p get "sub")] select ((_p get "sub") isNotEqualTo "")
    ]) call comspec_atak_native_fnc_tr);
};
// Bio, centres d'intérêt et compatibilité.
private _details = {
    params ["_p", "_rect"];
    _rect params ["_cx0", "_cy0", "_w", "_h"];
    private _t = ["COMSPEC_RscStructuredText", [_cx0, _cy0, _w, _h]] call _mk;
    _t ctrlSetStructuredText parseText ((format [
        "<t color='#e8eee9'>« %1 »</t><br/><t size='0.85' color='#8a9a93'>Aime : </t><t size='0.85' color='%5'>%2 · %3 · %4</t><br/><t size='0.85' color='#8a9a93'>Compatibilité </t><t size='0.85' font='COMSPEC_BarlowBold' color='%5'>%6 %%</t>",
        [_p get "bio"] call _esc, (_p get "tags") select 0, (_p get "tags") select 1, (_p get "tags") select 2, _pink, _p get "compat"
    ]) call comspec_atak_native_fnc_tr);
};

switch (_tab) do {
    case "FIND": {
        // Profil affiché : particulier tiré au tour précédent, sinon le joueur suivant, sinon un particulier restant.
        private _seen = _likes + _passed;
        private _left = _pool select { !((getPlayerUID _x) in _seen) };
        private _sp = _s getOrDefault ["rcSpecial", objNull];
        if (isNil "_sp" || {!(_sp isEqualType objNull)}) then { _sp = objNull; };
        if (!isNull _sp && {!alive _sp || {(["key", _sp] call comspec_atak_native_fnc_datingSpecials) in _seen}}) then { _sp = objNull; _s set ["rcSpecial", objNull]; };
        if (isNull _sp && {_left isEqualTo []}) then {
            _sp = ((["pool"] call comspec_atak_native_fnc_datingSpecials) select { !((["key", _x] call comspec_atak_native_fnc_datingSpecials) in _seen) }) param [0, objNull];
        };
        private _p = createHashMap;
        if (!isNull _sp) then { _p = [_sp] call _aiProfile; } else { if (_left isNotEqualTo []) then { _p = [_left select 0] call _playerProfile; }; };
        if ((count _p) isEqualTo 0) exitWith {
            ["stop"] call comspec_atak_native_fnc_datingCam;
            private _rows = [];
            if !(_visible) then { _rows pushBack ["text", "<t color='#f2ab33'>Votre profil est caché : les autres ne vous voient pas (onglet MON PROFIL).</t>"]; };
            _rows pushBack ["hero", _icon, "<t size='1.2' font='COMSPEC_BarlowBold'>Plus personne autour de vous</t><br/><t color='#8a9a93'>Revenez plus tard : de nouveaux profils arrivent avec les joueurs, et parfois d'ailleurs.</t>"];
            if ((count _passed) > 0) then { _rows pushBack ["buttons", [["REVOIR LES PROFILS PASSÉS", { ["reset"] call comspec_atak_native_fnc_datingAction; }]]]; };
            [_rows, [0, _cy, _bw, _ch]] call comspec_atak_native_fnc_formRender;
        };
        private _btnH = _font * 1.8;
        private _detH = _font * 3.6;
        private _cardR = if (_land) then { [_pad, _cy, (_bw - _pad * 3) * 0.48, _ch] } else { [_pad, _cy, _bw - _pad * 2, _ch - _btnH - _detH - _pad] };
        private _rx = if (_land) then { _pad * 2 + (_cardR select 2) } else { _pad };
        private _rw = if (_land) then { _bw - _rx - _pad } else { _bw - _pad * 2 };
        private _detY = if (_land) then { _cy } else { _cy + (_cardR select 3) + _pad / 2 };
        private _btnY = if (_land) then { _cy + _ch - _btnH } else { _detY + _detH + _pad / 2 };
        [_p, _cardR] call _card;
        [_p, [_rx, _detY, _rw, [_detH, _ch - _btnH - _pad] select _land]] call _details;
        // PASSER · SUPER LIKE · J'AIME.
        private _key = _p get "key";
        private _superWait = 600 - (diag_tickTime - (missionNamespace getVariable ["COMSPEC_ATAK_RencardSuperAt", -1e9]));
        private _bw3 = (_rw - _pad) / 3;
        {
            _x params ["_txt", "_act", "_col", "_txtCol", "_tip"];
            private _b = ["COMSPEC_RscButton", [_rx + _forEachIndex * (_bw3 + _pad / 2), _btnY, _bw3, _btnH], _txt] call _mk;
            _b ctrlSetFont (COMSPEC_ATAK_FontSet select 1);
            _b ctrlSetBackgroundColor _col;
            _b ctrlSetTextColor _txtCol;
            _b ctrlSetTooltip ((_tip) call comspec_atak_native_fnc_tr);
            _b ctrlAddEventHandler ["ButtonClick", compile format ["['%1', %2] call comspec_atak_native_fnc_datingAction;", _act, str _key]];
        } forEach [
            ["PASSER", "pass", [0.12, 0.13, 0.13, 1], [0.95, 0.35, 0.35, 1], "Profil suivant"],
            [["SUPER LIKE", format ["SUPER (%1 min)", ceil (_superWait / 60)]] select (_superWait > 0), "super", [0.11, 0.42, 0.62, 1], [1, 1, 1, 1], "La personne sait que c'est vous. Un toutes les 10 minutes."],
            ["J'AIME", "like", [1, 0.36, 0.5, 1], [1, 1, 1, 1], "Match si c'est réciproque"]
        ];
    };
    case "ME": {
        private _p = [player] call _playerProfile;
        private _cardH = if (_land) then { _ch } else { _ch * 0.52 };
        private _cardW = if (_land) then { (_bw - _pad * 3) * 0.42 } else { _bw - _pad * 2 };
        [_p, [_pad, _cy, _cardW, _cardH]] call _card;
        private _rows = [
            ["section", "Ma carte", "Ce que les autres voient (portrait opérateur Athena, puis votre visage en direct)"],
            ["text", format ["<t color='#e8eee9'>« %1 »</t>", [_p get "bio"] call _esc]],
            ["buttons", [["CHANGER DE BIO", { ["bio"] call comspec_atak_native_fnc_datingAction; }]]],
            ["switch", "Apparaître sur Tinder", _visible, { ["visible"] call comspec_atak_native_fnc_datingAction; }, "Décochez pour cacher votre profil"],
            ["info", "Likes reçus", str (count _likedBy)],
            ["info", "Matchs", str ((count _matches) + (count _aiMatches))]
        ];
        private _fr = if (_land) then { [_pad * 2 + _cardW, _cy, _bw - _cardW - _pad * 2, _ch] } else { [0, _cy + _cardH + _pad / 2, _bw, _ch - _cardH - _pad / 2] };
        [_rows, _fr] call comspec_atak_native_fnc_formRender;
    };
    case "MATCH": {
        private _open = _s getOrDefault ["rcOpen", ""];
        private _ou = if (_open isEqualTo "") then { objNull } else { ["unit", _open] call comspec_atak_native_fnc_datingSpecials };
        if (!isNull _ou) then {
            // Conversation avec un profil IA : photo, messages, demander où il est, marquer sur la carte.
            private _p = [_ou] call _aiProfile;
            private _ph = if (_land) then { _ch } else { _ch * 0.34 };
            private _pw = if (_land) then { (_bw - _pad * 3) * 0.36 } else { _bw - _pad * 2 };
            [_p, [_pad, _cy, _pw, _ph]] call _card;
            private _rows = [];
            {
                _x params ["_mine", "_txt", "_at"];
                _rows pushBack ["text", format ["<t align='%1' size='0.8' color='#8a9a93'>%2 · %3</t><br/><t align='%1' color='%4'>%5</t>",
                    ["left", "right"] select _mine, [[_p get "name"] call _esc, "Moi"] select _mine, _at, ["#e8eee9", _pink] select _mine, [_txt] call _esc]];
            } forEach ((missionNamespace getVariable ["COMSPEC_ATAK_RencardAiChat", createHashMap]) getOrDefault [_open, []]);
            _rows pushBack ["buttons", [
                ["OÙ ES-TU ?", compile format ["['ask', %1] call comspec_atak_native_fnc_datingAction;", str _open], true],
                ["MARQUER SUR LA CARTE", compile format ["['mark', %1] call comspec_atak_native_fnc_datingAction;", str _open]]
            ]];
            (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) set ["backSub", ["DATING", { ['open', ''] call comspec_atak_native_fnc_datingAction; }]];
            _rows pushBack ["buttons", [
                ["CONTRÔLE SSE", { ["SSE"] call comspec_atak_native_fnc_navigate; }],
                ["RETOUR", { ['open', ''] call comspec_atak_native_fnc_datingAction; }]
            ]];
            private _fr = if (_land) then { [_pad * 2 + _pw, _cy, _bw - _pw - _pad * 2, _ch] } else { [0, _cy + _ph + _pad / 2, _bw, _ch - _ph - _pad / 2] };
            [_rows, _fr] call comspec_atak_native_fnc_formRender;
        } else {
            ["stop"] call comspec_atak_native_fnc_datingCam;
            private _rows = [];
            {
                _rows pushBack ["person", [_x] call _thumb, format ["<t font='COMSPEC_BarlowBold'>%1</t><br/><t size='0.8' color='%2'>Match · %3</t>", [name _x] call _esc, _pink, [groupId group _x] call _esc],
                    [["SMS", compile format ["['sms', %1] call comspec_atak_native_fnc_datingAction;", str name _x], true]]];
            } forEach _matches;
            {
                private _u = ["unit", _x] call comspec_atak_native_fnc_datingSpecials;
                private _p = ["profile", _u] call comspec_atak_native_fnc_datingSpecials;
                private _n = count ((missionNamespace getVariable ["COMSPEC_ATAK_RencardAiChat", createHashMap]) getOrDefault [_x, []]);
                _rows pushBack ["person", [_u] call _thumb, format ["<t font='COMSPEC_BarlowBold'>%1, %2</t><br/><t size='0.8' color='%3'>Match · %4 message(s)%5</t>",
                    [_p get "name"] call _esc, _p get "age", _pink, _n, ["", " · <t color='#e5483a'>profil supprimé</t>"] select (!alive _u)],
                    [["MESSAGES", compile format ["['open', %1] call comspec_atak_native_fnc_datingAction;", str _x], true]]];
            } forEach _aiMatches;
            if (_rows isEqualTo []) then { _rows pushBack ["hero", _icon, "<t font='COMSPEC_BarlowBold'>Pas encore de match</t><br/><t color='#8a9a93'>Likez, et attendez qu'on vous like en retour.</t>"]; };
            [_rows, [0, _cy, _bw, _ch]] call comspec_atak_native_fnc_formRender;
        };
    };
};
true
