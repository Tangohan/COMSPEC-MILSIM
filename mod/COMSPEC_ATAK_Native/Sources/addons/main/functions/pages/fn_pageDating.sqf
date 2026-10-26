/*
    Tinder, façon vraie app de rencontre, et derrière : la chasse aux HVT (fn_hvtIntel). Barre du bas comme l'app :
      SWIPE    : une carte par profil, grande photo (vrai visage de la personne rendu en direct, fn_datingCam) avec trois
                 photos (portrait, buste, en pied : toucher la gauche ou la droite de l'image), prénom, âge, distance,
                 bio, centres d'intérêt, compatibilité ; PASSER, SUPER LIKE (1 / 10 min), J'AIME.
                 De temps en temps, un profil tiré des IA de la carte : HVT ou personne SSE (fn_datingSpecials), selon
                 le Zeus et le réglage de mission. Rien ne le signale : à vous de reconnaître le visage.
      LIKES    : les tuyaux des informateurs sur les HVT de la carte, en grille floue ; ouvrir un like = le tuyau, sa
                 cotation OTAN (source A-F, crédibilité 1-6) et sa zone : REJETER ou CONFIRMER, comme un swipe.
      DOSSIERS : une fiche par cible : étapes FIND, FIX, FINISH, EXPLOIT, position estimée et incertitude, confiance,
                 tuyaux contradictoires, activité du téléphone ; MARQUER, PARTAGER au groupe, SALUTE prérempli ; JACKPOT
                 (cible tuée ou capturée) : vérité de chaque tuyau.
      MESSAGES : nouveaux matchs (dont la case LIKES), puis conversations : sources (relance), profils IA (indices de
                 position), joueurs (SMS) ; « À TON TOUR » quand l'autre a écrit en dernier.
      PROFIL   : aperçu de sa propre carte, bio, visibilité, likes reçus, bilan de la chasse.
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
if !(_tab in ["FIND", "TIPS", "FILES", "MATCH", "ME"]) then { _tab = "FIND"; };
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


// Chasse aux HVT (fn_hvtIntel) : tuyaux non lus, conversations où c'est à vous de répondre.
private _unread = ["unread"] call comspec_atak_native_fnc_hvtIntel;
private _chat = missionNamespace getVariable ["COMSPEC_ATAK_RencardAiChat", createHashMap];
private _read = missionNamespace getVariable ["COMSPEC_ATAK_RencardRead", createHashMap];
missionNamespace setVariable ["COMSPEC_ATAK_RencardRead", _read];
private _yourTurn = {
    params ["_k"];
    private _c = _chat getOrDefault [_k, []];
    _c isNotEqualTo [] && {!((_c select -1) select 0)} && {(count _c) > (_read getOrDefault [_k, 0])}
};
private _newMsg = { [_x] call _yourTurn } count (keys _chat);
private _grey = "#8a9a93";
private _green = "#5fd38a";

// Barre du bas, comme l'app : SWIPE, LIKES (tuyaux), DOSSIERS, MESSAGES, PROFIL ; pastille rouge = à traiter.
private _navH = _font * 1.9;
private _ny = _bh - _navH - _pad / 4;
private _nbg = ["COMSPEC_RscText", [0, _ny - _pad / 4, _bw, _navH + _pad / 2]] call _mk;
_nbg ctrlSetBackgroundColor [0.045, 0.045, 0.05, 1];
private _nw = (_bw - _pad * 2) / 5;
{
    _x params ["_txt", "_k", "_n", "_tip"];
    private _bx = _pad + _forEachIndex * _nw;
    private _b = ["COMSPEC_RscButton", [_bx, _ny, _nw - _pad / 4, _navH], _txt] call _mk;
    _b ctrlSetFontHeight ((_fs * 0.85) max COMSPEC_ATAK_FontMin);
    _b ctrlSetBackgroundColor ([[0.07, 0.07, 0.075, 1], [0.17, 0.17, 0.18, 1]] select (_k isEqualTo _tab));
    _b ctrlSetTextColor ([[0.75, 0.78, 0.77, 1], [1, 0.36, 0.5, 1]] select (_k isEqualTo _tab));
    _b ctrlSetTooltip ((_tip) call comspec_atak_native_fnc_tr);
    _b ctrlAddEventHandler ["ButtonClick", compile format ["['tab', '%1'] call comspec_atak_native_fnc_datingAction;", _k]];
    if (_n > 0) then {
        private _bs = _fs * 1.05;
        private _bd = ["COMSPEC_RscTextCenter", [_bx + _nw - _pad / 4 - _bs * _ratio, _ny - _bs * 0.25, _bs * _ratio, _bs], str (_n min 99)] call _mk;
        _bd ctrlSetBackgroundColor [0.85, 0.16, 0.16, 1];
        _bd ctrlSetTextColor [1, 1, 1, 1];
        _bd ctrlSetFontHeight ((_fs * 0.75) max COMSPEC_ATAK_FontMin);
        _bd ctrlEnable false;
    };
} forEach [
    ["SWIPE", "FIND", 0, "Profils autour de vous"],
    ["LIKES", "TIPS", _unread, "Tuyaux des informateurs : confirmer ou rejeter"],
    ["DOSSIERS", "FILES", 0, "Dossiers des cibles (HVT) : position estimée, étape FIND, FIX, FINISH"],
    ["MESSAGES", "MATCH", _newMsg, "Matchs, sources et cibles"],
    ["PROFIL", "ME", 0, "Ma carte et mon bilan"]
];
private _cy = _pad / 2;
private _ch = _ny - _pad * 0.75 - _cy;

// Lignes d'une conversation (match IA ou source) ; la conversation ouverte est marquée lue.
private _chatRows = {
    params ["_k", "_who"];
    private _rows = [];
    private _c = _chat getOrDefault [_k, []];
    {
        _x params ["_mine", "_txt", "_at"];
        _rows pushBack ["text", format ["<t align='%1' size='0.8' color='%2'>%3 · %4</t><br/><t align='%1' color='%5'>%6</t>",
            ["left", "right"] select _mine, _grey, [[_who] call _esc, "Moi"] select _mine, _at, ["#e8eee9", _pink] select _mine, [_txt] call _esc]];
    } forEach _c;
    _read set [_k, count _c];
    _rows
};
// Aperçu du dernier message (« ↩ » quand c'est le mien), traduit avant d'être coupé.
private _preview = {
    params ["_k"];
    private _c = _chat getOrDefault [_k, []];
    if (_c isEqualTo []) exitWith { "" };
    (_c select -1) params ["_mine", "_txt"];
    private _t = (_txt) call comspec_atak_native_fnc_tr;
    if ((count _t) > 58) then { _t = (_t select [0, 56]) + "…"; };
    format ["%1%2", ["", "↩ "] select _mine, [_t] call _esc]
};
private _gradeTxt = createHashMapFromArray [["A", "source totalement fiable"], ["B", "source généralement fiable"], ["C", "source assez fiable"],
    ["D", "source peu fiable"], ["E", "source non fiable"], ["F", "source impossible à juger"]];
private _credTxt = ["", "recoupé par une autre source", "probablement vrai", "possiblement vrai", "douteux", "improbable", "impossible à juger"];
private _srcName = { params ["_c"]; ["Contact direct", _c] select (_c isNotEqualTo "DIRECT") };
// Image d'une cible (aperçu du personnage) sous un voile sombre : un like reste flou tant qu'on ne l'a pas traité.
private _veiled = {
    params ["_key", "_rect", "_alpha"];
    private _bg = ["COMSPEC_RscText", _rect] call _mk;
    _bg ctrlSetBackgroundColor [0.09, 0.09, 0.1, 1];
    private _u = ["unit", _key] call comspec_atak_native_fnc_datingSpecials;
    ["COMSPEC_RscSlide", _rect, if (isNull _u) then { _icon } else { [_u] call _figure }] call _mk;
    private _v = ["COMSPEC_RscText", _rect] call _mk;
    _v ctrlSetBackgroundColor [0.05, 0.05, 0.06, _alpha];
    _v ctrlEnable false;
};


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
    case "TIPS": {
        private _openTip = _s getOrDefault ["rcTip", ""];
        private _t = if (_openTip isEqualTo "") then { [] } else { ["get", _openTip] call comspec_atak_native_fnc_hvtIntel };
        ["stop"] call comspec_atak_native_fnc_datingCam;
        if (_t isNotEqualTo []) exitWith {
            // Un like ouvert : le tuyau, sa cotation, sa zone ; REJETER, SUR LA CARTE, CONFIRMER (le swipe).
            _t params ["_id", "_key", "_code", "_tpos", "", "_at", "_st", "_txt", "", "_from", "_name"];
            private _src = ["source", _code] call comspec_atak_native_fnc_hvtIntel;
            private _grade = ["grade", _code] call comspec_atak_native_fnc_hvtIntel;
            private _cred = ["cred", _t] call comspec_atak_native_fnc_hvtIntel;
            private _r = ["radius", _t] call comspec_atak_native_fnc_hvtIntel;
            private _age = floor ((CBA_missionTime - _at) / 60);
            private _ph = if (_land) then { _ch } else { _ch * 0.3 };
            private _pw = if (_land) then { (_bw - _pad * 3) * 0.36 } else { _bw - _pad * 2 };
            [_key, [_pad, _cy, _pw, _ph], [0.72, 0.35] select (_st isEqualTo "OK")] call _veiled;
            private _ov = ["COMSPEC_RscStructuredText", [_pad * 1.5, _cy + _ph - _font * 2.9, _pw - _pad, _font * 2.8]] call _mk;
            _ov ctrlEnable false;
            _ov ctrlSetStructuredText parseText ((format [
                "<t size='1.4' font='COMSPEC_BarlowBold' color='#ffffff' shadow='2'>%1</t><t size='1.15' color='%2' shadow='2'>  %3-%4</t><br/><t size='0.8' color='#e8eee9' shadow='2'>%5</t>",
                [[_code] call _srcName] call _esc, _pink, _grade, _cred,
                if (_src isEqualTo []) then { ["Informateur d'un équipier", "La cible elle-même, en conversation"] select (_code isEqualTo "DIRECT") } else { format ["%1, %2 ans · informateur", _src select 0, _src select 1] }
            ]) call comspec_atak_native_fnc_tr);
            private _rows = [
                ["text", format ["<t color='#e8eee9'>« %1 »</t>", [_txt] call _esc], [0.07, 0.07, 0.075, 1]],
                ["info", "Cible", [_name] call _esc],
                ["info", "Cotation", format ["%1-%2 · %3, %4", _grade, _cred, _gradeTxt getOrDefault [_grade, ""], _credTxt select _cred]],
                ["info", "Zone", format ["%1 · ± %2 m", [_tpos, 6] call comspec_atak_native_fnc_gridRef, _r]],
                ["info", "Reçu", format ["il y a %1 min", _age]]
            ];
            if (_from isNotEqualTo "") then { _rows pushBack ["info", "Partagé par", [_from] call _esc]; };
            if (_age >= 45) then { _rows pushBack ["text", "<t size='0.85' color='#f2ab33'>Tuyau périmé (plus de 45 min) : il n'entre plus dans l'estimation.</t>"]; };
            _rows append [
                ["buttons", [
                    ["REJETER", compile format ["['review', [%1, 'NO']] call comspec_atak_native_fnc_datingAction;", str _id]],
                    ["SUR LA CARTE", compile format ["['markTip', %1] call comspec_atak_native_fnc_datingAction;", str _id]],
                    ["CONFIRMER", compile format ["['review', [%1, 'OK']] call comspec_atak_native_fnc_datingAction;", str _id], true]
                ]],
                ["buttons", [
                    ["DOSSIER", compile format ["['tab', 'FILES'] call comspec_atak_native_fnc_datingAction; ['file', %1] call comspec_atak_native_fnc_datingAction;", str _key]],
                    ["RETOUR", { ['tipOpen', ''] call comspec_atak_native_fnc_datingAction; }]
                ]],
                ["text", format ["<t size='0.8' color='%1'>%2</t>", _grey, ["Confirmez seulement ce qui vous semble crédible : deux sources différentes qui se recoupent font un MATCH. Un faux tuyau confirmé fausse la position estimée.", "Statut : confirmé."] select (_st isEqualTo "OK")]]
            ];
            (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) set ["backSub", ["DATING", { ['tipOpen', ''] call comspec_atak_native_fnc_datingAction; }]];
            private _fr = if (_land) then { [_pad * 2 + _pw, _cy, _bw - _pw - _pad * 2, _ch] } else { [0, _cy + _ph + _pad / 2, _bw, _ch - _ph - _pad / 2] };
            [_rows, _fr] call comspec_atak_native_fnc_formRender;
        };
        // Grille des likes : plus récents d'abord, flous tant qu'ils ne sont pas traités.
        private _all = +(missionNamespace getVariable ["COMSPEC_ATAK_HvtTips", []]);
        reverse _all;
        private _filter = _s getOrDefault ["rcTipFilter", "NEW"];
        private _list = if (_filter isEqualTo "NEW") then { _all select { (_x select 6) isEqualTo "NEW" } } else { _all };
        private _hh = _font * 1.7;
        private _hd = ["COMSPEC_RscStructuredText", [_pad, _cy, _bw - _pad * 2, _hh]] call _mk;
        _hd ctrlSetStructuredText parseText ((format ["<t size='1.6' font='COMSPEC_BarlowBold' color='#ffffff'>Likes</t><t size='1.1' color='%1'>  %2</t><t size='0.8' color='%3'>   tuyaux des informateurs</t>", _pink, _unread, _grey]) call comspec_atak_native_fnc_tr);
        private _chipY = _cy + _hh;
        private _chipH = _font * 1.25;
        {
            _x params ["_txt", "_k"];
            private _b = ["COMSPEC_RscButton", [_pad + _forEachIndex * (_bw * 0.3 + _pad / 2), _chipY, _bw * 0.3, _chipH], _txt] call _mk;
            _b ctrlSetFontHeight ((_fs * 0.9) max COMSPEC_ATAK_FontMin);
            if (_k isEqualTo _filter) then { _b ctrlSetBackgroundColor [0.95, 0.95, 0.95, 1]; _b ctrlSetTextColor [0.07, 0.07, 0.07, 1]; } else { _b ctrlSetBackgroundColor [0.15, 0.15, 0.16, 1]; _b ctrlSetTextColor [0.9, 0.9, 0.9, 1]; };
            _b ctrlAddEventHandler ["ButtonClick", compile format ["['tipFilter', '%1'] call comspec_atak_native_fnc_datingAction;", _k]];
        } forEach [["NOUVEAUX", "NEW"], ["TOUS", "ALL"]];
        private _gy = _chipY + _chipH + _pad / 2;
        private _gh = _cy + _ch - _gy;
        if (_list isEqualTo []) exitWith {
            [[["hero", _icon, ["<t size='1.1' font='COMSPEC_BarlowBold'>Aucun nouveau like</t><br/><t color='#8a9a93'>Les informateurs likent quand ils ont quelque chose sur une cible. Plus il y a de civils autour de vous, plus ils parlent. Profils HVT matchés : leurs rendez-vous arrivent ici aussi.</t>",
                "<t size='1.1' font='COMSPEC_BarlowBold'>Aucun like pour l'instant</t><br/><t color='#8a9a93'>Aucun HVT désigné sur la carte, ou aucun informateur n'a encore parlé.</t>"] select (_filter isEqualTo "ALL")]], [0, _gy, _bw, _gh]] call comspec_atak_native_fnc_formRender;
        };
        private _cols = [2, 3] select _land;
        private _tw = (_bw - _pad * (_cols + 1)) / _cols;
        private _th = ((_tw / _ratio) * 1.3) min _gh;
        private _nr = (floor ((_gh + _pad / 2) / (_th + _pad / 2))) max 1;
        private _max = _nr * _cols;
        {
            if (_forEachIndex >= _max) exitWith {};
            _x params ["_id", "_key", "_code", "", "", "_at", "_st", "", "", "", "_name"];
            private _x0 = _pad + (_forEachIndex mod _cols) * (_tw + _pad);
            private _y0 = _gy + (floor (_forEachIndex / _cols)) * (_th + _pad / 2);
            [_key, [_x0, _y0, _tw, _th], [0.8, 0.45] select (_st isNotEqualTo "NEW")] call _veiled;
            private _age = floor ((CBA_missionTime - _at) / 60);
            private _lh = _font * 3.4;
            private _tt = ["COMSPEC_RscStructuredText", [_x0 + _pad / 3, _y0 + _th - _lh, _tw - _pad * 2 / 3, _lh]] call _mk;
            _tt ctrlEnable false;
            _tt ctrlSetStructuredText parseText ((format [
                "%1<br/><t size='1.05' font='COMSPEC_BarlowBold' color='#ffffff' shadow='2'>%2</t><t size='0.95' color='%3' shadow='2'>  %4-%5</t><br/><t size='0.8' color='%6'>●</t><t size='0.8' color='#e8eee9' shadow='2'> %7 · %8</t>",
                switch (_st) do {
                    case "OK": { "<t size='0.75' color='#5fd38a'>CONFIRMÉ</t>" };
                    case "NO": { "<t size='0.75' color='#e5483a'>REJETÉ</t>" };
                    default { format ["<t size='0.75' color='%1'>NOUVEAU</t>", _pink] };
                },
                [[_code] call _srcName] call _esc, _pink, ["grade", _code] call comspec_atak_native_fnc_hvtIntel, ["cred", _x] call comspec_atak_native_fnc_hvtIntel,
                [_green, _grey] select (_age > 10), format ["il y a %1 min", _age], [_name] call _esc
            ]) call comspec_atak_native_fnc_tr);
            private _hit = ["COMSPEC_RscButtonInvisible", [_x0, _y0, _tw, _th]] call _mk;
            _hit ctrlSetTooltip (("Ouvrir le tuyau") call comspec_atak_native_fnc_tr);
            _hit ctrlAddEventHandler ["ButtonClick", compile format ["['tipOpen', %1] call comspec_atak_native_fnc_datingAction;", str _id]];
        } forEach _list;
        if ((count _list) > _max) then {
            private _more = ["COMSPEC_RscStructuredText", [_pad, _cy + _font * 0.3, _bw - _pad * 2, _font * 1.2]] call _mk;
            _more ctrlSetStructuredText parseText ((format ["<t align='right' size='0.8' color='%1'>+%2 autres</t>", _grey, (count _list) - _max]) call comspec_atak_native_fnc_tr);
        };
    };
    case "FILES": {
        private _openFile = _s getOrDefault ["rcFile", ""];
        if (_openFile isNotEqualTo "") exitWith {
            // Dossier d'une cible : photo, étapes F3EA, estimation, actions, tuyaux.
            private _d = ["dossier", _openFile] call comspec_atak_native_fnc_hvtIntel;
            private _u = ["unit", _openFile] call comspec_atak_native_fnc_datingSpecials;
            private _name = ["name", _openFile] call comspec_atak_native_fnc_hvtIntel;
            private _st = _d get "stage";
            private _jp = _d get "jackpot";
            private _ph = if (_land) then { _ch } else { _ch * 0.3 };
            private _pw = if (_land) then { (_bw - _pad * 3) * 0.36 } else { _bw - _pad * 2 };
            if (isNull _u) then {
                ["stop"] call comspec_atak_native_fnc_datingCam;
                [_openFile, [_pad, _cy, _pw, _ph], 0.6] call _veiled;
            } else {
                [[_u] call _aiProfile, [_pad, _cy, _pw, _ph]] call _card;
            };
            private _rows = [
                ["steps", switch (_st) do {
                    case "JACKPOT": { [["FIND", "done"], ["FIX", "done"], ["FINISH", "done"], ["EXPLOIT", "now"]] };
                    case "FIX": { [["FIND", "done"], ["FIX", "done"], ["FINISH", "hot"], ["EXPLOIT", "todo"]] };
                    default { [["FIND", "now"], ["FIX", "todo"], ["FINISH", "todo"], ["EXPLOIT", "todo"]] };
                }]
            ];
            if (_st isEqualTo "JACKPOT") then {
                _jp params ["", "_how", "_nT", "_nF", "_good", "_rev"];
                _rows append [
                    ["banner", format ["<t color='#5fd38a'>JACKPOT</t> · %1", _how], [0.06, 0.16, 0.09, 1]],
                    ["info", "Tuyaux vrais", format ["%1 sur %2", _nT, _nT + _nF]],
                    ["info", "Vos décisions justes", format ["%1 sur %2", _good, _rev]],
                    ["text", format ["<t size='0.85' color='%1'>La vérité de chaque tuyau est révélée ci-dessous ; les sources sont recotées. Exploitez maintenant : fouille SSE, téléphone, documents.</t>", _grey]]
                ];
            } else {
                private _pos = _d get "pos";
                private _act = _d get "activity";
                _rows append [
                    ["info", "Position estimée", if (_pos isEqualTo []) then { "<t color='#f2ab33'>inconnue (aucun tuyau confirmé et frais)</t>" } else { format ["<t font='EtelkaMonospaceProBold'>%1</t> · à %2 m", [_pos, 8] call comspec_atak_native_fnc_gridRef, round (player distance2D _pos)] }],
                    ["info", "Incertitude", [format ["± %1 m", _d get "radius"], "—"] select (_pos isEqualTo [])],
                    ["info", "Confiance", format ["%1 %%", _d get "conf"]],
                    ["info", "Sources qui se recoupent", str (_d get "srcs")],
                    ["info", "Tuyaux contradictoires", [str (_d get "contra"), format ["<t color='#f2ab33'>%1</t>", _d get "contra"]] select ((_d get "contra") > 0)],
                    ["info", "Activité du téléphone", if (_act < 0) then { "inconnue" } else { format ["<t color='%1'>●</t> il y a %2 min", [_grey, _green] select (_act <= 10), _act] }]
                ];
            };
            _rows append [
                ["buttons", [
                    ["MARQUER", compile format ["['fileMark', %1] call comspec_atak_native_fnc_datingAction;", str _openFile], true, _st isNotEqualTo "JACKPOT"],
                    ["PARTAGER", compile format ["['fileShare', %1] call comspec_atak_native_fnc_datingAction;", str _openFile], false, _st isNotEqualTo "JACKPOT", "Envoyer les tuyaux confirmés à mon groupe"],
                    ["SALUTE", compile format ["['fileSalute', %1] call comspec_atak_native_fnc_datingAction;", str _openFile], false, true, "Compte rendu SALUTE prérempli"]
                ]],
                ["buttons", [
                    ["CONTRÔLE SSE", { ["SSE"] call comspec_atak_native_fnc_navigate; }],
                    ["RETOUR", { ['file', ''] call comspec_atak_native_fnc_datingAction; }]
                ]],
                ["section", "Tuyaux", format ["%1 reçu(s), %2 confirmé(s) et frais", count (_d get "tips"), _d get "confirmed"]]
            ];
            private _ts = [_d get "tips", [], { _x select 5 }, "DESCEND"] call BIS_fnc_sortBy;
            {
                if (_forEachIndex >= 8) exitWith {};
                _x params ["_id", "", "_code", "", "", "_at", "_tst", "_txt", "_true"];
                private _verdict = if (_st isEqualTo "JACKPOT") then { ["  <t color='#e5483a'>FAUX</t>", "  <t color='#5fd38a'>VRAI</t>"] select _true } else { "" };
                _rows pushBack ["text", format ["<t size='0.8' color='%1'>%2 · %3-%4 · %5 · %6</t>%7<br/><t size='0.9' color='#e8eee9'>%8</t>",
                    _grey, [[_code] call _srcName] call _esc, ["grade", _code] call comspec_atak_native_fnc_hvtIntel, ["cred", _x] call comspec_atak_native_fnc_hvtIntel,
                    createHashMapFromArray [["NEW", "à traiter"], ["OK", "confirmé"], ["NO", "rejeté"]] getOrDefault [_tst, ""],
                    format ["il y a %1 min", floor ((CBA_missionTime - _at) / 60)], _verdict, [_txt] call _esc], [0.06, 0.065, 0.07, 1]];
            } forEach _ts;
            (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) set ["backSub", ["DATING", { ['file', ''] call comspec_atak_native_fnc_datingAction; }]];
            private _fr = if (_land) then { [_pad * 2 + _pw, _cy, _bw - _pw - _pad * 2, _ch] } else { [0, _cy + _ph + _pad / 2, _bw, _ch - _ph - _pad / 2] };
            [_rows, _fr] call comspec_atak_native_fnc_formRender;
        };
        ["stop"] call comspec_atak_native_fnc_datingCam;
        private _keys = ["keys"] call comspec_atak_native_fnc_hvtIntel;
        private _rows = [["section", "Dossiers HVT", "Position estimée d'après les tuyaux que vous avez confirmés"]];
        {
            private _k = _x;
            private _d = ["dossier", _k] call comspec_atak_native_fnc_hvtIntel;
            private _u = ["unit", _k] call comspec_atak_native_fnc_datingSpecials;
            private _st = _d get "stage";
            private _line = switch (_st) do {
                case "JACKPOT": { format ["%1 · %2 tuyaux vrais sur %3", (_d get "jackpot") select 1, (_d get "jackpot") select 2, ((_d get "jackpot") select 2) + ((_d get "jackpot") select 3)] };
                case "FIX": { format ["confiance %1 %% · ± %2 m · %3 sources", _d get "conf", _d get "radius", _d get "srcs"] };
                default { if ((_d get "confirmed") > 0) then { format ["%1 tuyau(x) confirmé(s), pas encore recoupé", _d get "confirmed"] } else { "aucun tuyau confirmé" } };
            };
            private _act = _d get "activity";
            _rows pushBack ["person", if (isNull _u) then { _icon } else { [_u] call _thumb }, format ["<t font='COMSPEC_BarlowBold'>%1</t>%2  <t size='0.8' color='%3'>%4</t><br/><t size='0.8' color='%5'>%6</t>",
                [["name", _k] call comspec_atak_native_fnc_hvtIntel] call _esc, ["", format [" <t color='%1'>●</t>", _green]] select (_act >= 0 && {_act <= 10} && {_st isNotEqualTo "JACKPOT"}),
                createHashMapFromArray [["FIND", "#f2ab33"], ["FIX", "#e5483a"], ["JACKPOT", _green]] getOrDefault [_st, _grey], _st, _grey, _line],
                [["OUVRIR", compile format ["['file', %1] call comspec_atak_native_fnc_datingAction;", str _k], true]]];
        } forEach _keys;
        if (_keys isEqualTo []) then {
            _rows pushBack ["hero", _icon, "<t font='COMSPEC_BarlowBold'>Aucun dossier</t><br/><t color='#8a9a93'>Confirmez des likes (tuyaux des informateurs) ou matchez un profil HVT pour ouvrir un dossier.</t>"];
        };
        _rows pushBack ["text", format ["<t size='0.8' color='%1'>FIND, FIX, FINISH, EXPLOIT. Deux sources différentes qui se recoupent font un MATCH (FIX). Un tuyau est périmé après 45 min et sa zone grossit avec le temps : la cible bouge.</t>", _grey]];
        [_rows, [0, _cy, _bw, _ch]] call comspec_atak_native_fnc_formRender;
    };
    case "MATCH": {
        private _open = _s getOrDefault ["rcOpen", ""];
        private _ou = if (_open isEqualTo "" || {(_open select [0, 3]) isNotEqualTo "ai:"}) then { objNull } else { ["unit", _open] call comspec_atak_native_fnc_datingSpecials };
        if ((_open select [0, 4]) isEqualTo "src:") exitWith {
            // Fil d'une source : ses tuyaux, et la relancer.
            ["stop"] call comspec_atak_native_fnc_datingCam;
            private _code = _open select [4];
            private _src = ["source", _code] call comspec_atak_native_fnc_hvtIntel;
            private _rows = [["hero", _icon, format ["<t size='1.2' font='COMSPEC_BarlowBold'>%1</t><t color='%2'>  %3</t><br/><t color='%4'>%5</t>",
                [_code] call _esc, _pink, ["grade", _code] call comspec_atak_native_fnc_hvtIntel, _grey,
                if (_src isEqualTo []) then { "Informateur" } else { format ["%1, %2 ans · informateur", _src select 0, _src select 1] }]]];
            _rows append ([_open, _code] call _chatRows);
            _rows append [
                ["buttons", [
                    ["PLUS DE DÉTAILS ?", compile format ["['followup', %1] call comspec_atak_native_fnc_datingAction;", str _code], true, true, "Une relance toutes les 5 minutes"],
                    ["LIKES", { ['tab', 'TIPS'] call comspec_atak_native_fnc_datingAction; }]
                ]],
                ["buttons", [["RETOUR", { ['open', ''] call comspec_atak_native_fnc_datingAction; }]]]
            ];
            (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) set ["backSub", ["DATING", { ['open', ''] call comspec_atak_native_fnc_datingAction; }]];
            [_rows, [0, _cy, _bw, _ch]] call comspec_atak_native_fnc_formRender;
        };
        if (!isNull _ou) exitWith {
            // Conversation avec un profil IA : photo, messages, demander où il est, marquer sur la carte.
            private _p = [_ou] call _aiProfile;
            private _ph = if (_land) then { _ch } else { _ch * 0.34 };
            private _pw = if (_land) then { (_bw - _pad * 3) * 0.36 } else { _bw - _pad * 2 };
            [_p, [_pad, _cy, _pw, _ph]] call _card;
            private _rows = [_open, _p get "name"] call _chatRows;
            _rows pushBack ["buttons", [
                ["OÙ ES-TU ?", compile format ["['ask', %1] call comspec_atak_native_fnc_datingAction;", str _open], true],
                ["MARQUER SUR LA CARTE", compile format ["['mark', %1] call comspec_atak_native_fnc_datingAction;", str _open]]
            ]];
            (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) set ["backSub", ["DATING", { ['open', ''] call comspec_atak_native_fnc_datingAction; }]];
            private _hvt = (["kind", _ou] call comspec_atak_native_fnc_datingSpecials) isEqualTo "HVT";
            _rows pushBack ["buttons", [
                if (_hvt) then { ["DOSSIER", compile format ["['tab', 'FILES'] call comspec_atak_native_fnc_datingAction; ['file', %1] call comspec_atak_native_fnc_datingAction;", str _open]] } else { ["CONTRÔLE SSE", { ["SSE"] call comspec_atak_native_fnc_navigate; }] },
                ["RETOUR", { ['open', ''] call comspec_atak_native_fnc_datingAction; }]
            ]];
            if (_hvt) then { _rows pushBack ["text", format ["<t size='0.8' color='%1'>Un HVT méfiant donne parfois un faux rendez-vous : ses réponses entrent dans son dossier comme tuyaux « contact direct », à recouper.</t>", _grey]]; };
            private _fr = if (_land) then { [_pad * 2 + _pw, _cy, _bw - _pw - _pad * 2, _ch] } else { [0, _cy + _ph + _pad / 2, _bw, _ch - _ph - _pad / 2] };
            [_rows, _fr] call comspec_atak_native_fnc_formRender;
        };
        ["stop"] call comspec_atak_native_fnc_datingCam;
        // Nouveaux matchs : la case LIKES (floue, nombre de tuyaux à traiter), puis les derniers matchs.
        private _hh = _font * 1.7;
        private _hd = ["COMSPEC_RscStructuredText", [_pad, _cy, _bw - _pad * 2, _hh]] call _mk;
        _hd ctrlSetStructuredText parseText (("<t size='1.6' font='COMSPEC_BarlowBold' color='#ffffff'>Messages</t>") call comspec_atak_native_fnc_tr);
        private _aiRecent = +_aiMatches;
        reverse _aiRecent;
        private _strip = [["LIKES", "", "['tab', 'TIPS'] call comspec_atak_native_fnc_datingAction;"]];
        { _strip pushBack [name _x, [_x] call _thumb, format ["['sms', %1] call comspec_atak_native_fnc_datingAction;", str name _x]]; } forEach _matches;
        {
            private _u = ["unit", _x] call comspec_atak_native_fnc_datingSpecials;
            _strip pushBack [(["profile", _u] call comspec_atak_native_fnc_datingSpecials) get "name", [_u] call _figure, format ["['open', %1] call comspec_atak_native_fnc_datingAction;", str _x]];
        } forEach _aiRecent;
        private _sw = (_bw - _pad * 5) / 4;
        private _sh = (_sw / _ratio) * 1.15;
        private _sy = _cy + _hh;
        {
            if (_forEachIndex >= 4) exitWith {};
            _x params ["_lbl", "_pic", "_code"];
            private _x0 = _pad + _forEachIndex * (_sw + _pad);
            private _fr = ["COMSPEC_RscText", [_x0 - pixelW * 2, _sy - pixelH * 2, _sw + pixelW * 4, _sh + pixelH * 4]] call _mk;
            _fr ctrlSetBackgroundColor ([[0.2, 0.2, 0.21, 1], [0.93, 0.74, 0.2, 1]] select (_forEachIndex isEqualTo 0));
            private _bg = ["COMSPEC_RscText", [_x0, _sy, _sw, _sh]] call _mk;
            _bg ctrlSetBackgroundColor [0.1, 0.1, 0.11, 1];
            if (_forEachIndex isEqualTo 0) then {
                // Case LIKES : floue, avec le nombre de tuyaux à traiter dans une pastille dorée.
                private _n = ["COMSPEC_RscStructuredText", [_x0, _sy + _sh * 0.32, _sw, _sh * 0.4]] call _mk;
                _n ctrlSetStructuredText parseText (((format ["<t align='center' size='1.6' font='COMSPEC_BarlowBold' color='#edbd33'>%1</t>", _unread])) call comspec_atak_native_fnc_tr);
                _n ctrlEnable false;
            } else {
                ["COMSPEC_RscSlide", [_x0, _sy, _sw, _sh], _pic] call _mk;
            };
            private _l = ["COMSPEC_RscStructuredText", [_x0 - _pad / 2, _sy + _sh + pixelH * 4, _sw + _pad, _font * 1.1]] call _mk;
            _l ctrlSetStructuredText parseText ((format ["<t align='center' size='0.85' font='COMSPEC_BarlowBold' color='#ffffff'>%1</t>", [(_lbl splitString " ") param [0, _lbl]] call _esc]) call comspec_atak_native_fnc_tr);
            private _hit = ["COMSPEC_RscButtonInvisible", [_x0, _sy, _sw, _sh]] call _mk;
            _hit ctrlAddEventHandler ["ButtonClick", compile _code];
        } forEach _strip;
        private _ly = _sy + _sh + _font * 1.3 + _pad / 2;
        // Conversations : à vous de répondre d'abord.
        private _rows = [["section", "Conversations", "Sources, cibles et joueurs matchés"]];
        private _conv = (keys _chat) select { (count (_chat get _x)) > 0 && {((_x select [0, 4]) isEqualTo "src:") || {_x in _aiMatches}} };
        _conv = [_conv, [], { [0, 1] select ([_x] call _yourTurn) }, "DESCEND"] call BIS_fnc_sortBy;
        {
            private _k = _x;
            private _turn = ["", "  <t size='0.75' font='COMSPEC_BarlowBold' color='#ffffff'>[ À TON TOUR ]</t>"] select ([_k] call _yourTurn);
            if ((_k select [0, 4]) isEqualTo "src:") then {
                private _code = _k select [4];
                _rows pushBack ["person", _icon, format ["<t font='COMSPEC_BarlowBold'>%1</t><t size='0.8' color='%2'>  %3</t>%4<br/><t size='0.8' color='%5'>%6</t>",
                    [_code] call _esc, _pink, ["grade", _code] call comspec_atak_native_fnc_hvtIntel, _turn, _grey, [_k] call _preview],
                    [["OUVRIR", compile format ["['open', %1] call comspec_atak_native_fnc_datingAction;", str _k], true]], [1, 0.36, 0.5, 0.7]];
            } else {
                private _u = ["unit", _k] call comspec_atak_native_fnc_datingSpecials;
                private _p = ["profile", _u] call comspec_atak_native_fnc_datingSpecials;
                _rows pushBack ["person", [_u] call _thumb, format ["<t font='COMSPEC_BarlowBold'>%1, %2</t>%3%4<br/><t size='0.8' color='%5'>%6</t>",
                    [_p get "name"] call _esc, _p get "age", _turn, ["", " · <t color='#e5483a'>profil supprimé</t>"] select (!alive _u), _grey, [_k] call _preview],
                    [["OUVRIR", compile format ["['open', %1] call comspec_atak_native_fnc_datingAction;", str _k], true]]];
            };
        } forEach _conv;
        {
            _rows pushBack ["person", [_x] call _thumb, format ["<t font='COMSPEC_BarlowBold'>%1</t><br/><t size='0.8' color='%2'>Match · %3</t>", [name _x] call _esc, _pink, [groupId group _x] call _esc],
                [["SMS", compile format ["['sms', %1] call comspec_atak_native_fnc_datingAction;", str name _x], true]]];
        } forEach _matches;
        if ((count _rows) isEqualTo 1) then { _rows pushBack ["hero", _icon, "<t font='COMSPEC_BarlowBold'>Pas encore de conversation</t><br/><t color='#8a9a93'>Likez des profils, ou attendez qu'un informateur vous écrive.</t>"]; };
        [_rows, [0, _ly, _bw, _cy + _ch - _ly]] call comspec_atak_native_fnc_formRender;
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
        private _hs = ["stats"] call comspec_atak_native_fnc_hvtIntel;
        _rows append [
            ["section", "Chasse aux HVT", "Votre bilan de renseignement"],
            ["info", "Tuyaux reçus", str (_hs get "tips")],
            ["info", "Tuyaux confirmés", str (_hs get "ok")],
            ["info", "Informateurs", str (_hs get "sources")],
            ["info", "Jackpots", str (_hs get "jackpots")],
            ["info", "Décisions justes", if ((_hs get "judged") > 0) then { format ["%1 sur %2", _hs get "good", _hs get "judged"] } else { "—" }]
        ];
        private _fr = if (_land) then { [_pad * 2 + _cardW, _cy, _bw - _cardW - _pad * 2, _ch] } else { [0, _cy + _cardH + _pad / 2, _bw, _ch - _cardH - _pad / 2] };
        [_rows, _fr] call comspec_atak_native_fnc_formRender;
    };
};
true
