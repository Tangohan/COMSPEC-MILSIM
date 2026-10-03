/* Rencard : onglet DÉCOUVRIR (un profil à la fois, J'AIME / PASSER) et MATCHS (likes réciproques, SMS). */
disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _tab = _s getOrDefault ["rcTab", "FIND"];
private _esc = { params ["_t"]; if !(_t isEqualType "") then { _t = str _t; }; [[_t, "<", "&lt;"] call CBA_fnc_replace, ">", "&gt;"] call CBA_fnc_replace };
private _likes = missionNamespace getVariable ["COMSPEC_ATAK_RencardLikes", []];
private _likedBy = missionNamespace getVariable ["COMSPEC_ATAK_RencardLikedBy", []];
private _passed = missionNamespace getVariable ["COMSPEC_ATAK_RencardPassed", []];
private _visible = player getVariable ["COMSPEC_ATAK_Rencard", true];
private _pool = allPlayers select { _x isNotEqualTo player && {alive _x} && {_x getVariable ["COMSPEC_ATAK_Rencard", true]} && {[_x] call comspec_atak_native_fnc_hasDevice} };
private _matches = _pool select { private _u = getPlayerUID _x; _u in _likes && {_u in _likedBy} };
private _tabBtn = { params ["_t", "_k"]; [_t, compile format ["(uiNamespace getVariable ['COMSPEC_ATAK_State', createHashMap]) set ['rcTab', '%1']; [{ ['DATING'] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;", _k], _tab isEqualTo _k] };
private _rows = [["segment", "", [["DÉCOUVRIR", "FIND"] call _tabBtn, [format ["MATCHS (%1)", count _matches], "MATCH"] call _tabBtn]]];
// Bio et centres d'intérêt tirés du joueur (toujours les mêmes pour lui).
private _bios = ["Cherche binôme pour patrouilles au coucher du soleil.", "Fan de rations au poulet, allergique aux mines.", "Je connais toutes les grilles par cœur.", "Toujours le premier dans la pièce, le dernier à la pause.", "Ici pour la camaraderie et les MRE.", "Mon ex m'a laissé tomber en plein FRAGO.", "Le genre à vérifier deux fois sa radio.", "Je fais d'excellents cafés en bivouac.", "Pas de prise de tête, juste de la prise d'objectif.", "Je ne lis jamais le briefing, j'improvise."];
private _tags = ["Marches de nuit", "Café soluble", "Grilles 8 chiffres", "Feux de camp", "Parachutisme", "Tir longue distance", "Topographie", "Musique radio", "Cuisine de ration", "Sport en tenue", "Mécanique", "Premiers secours"];
private _seed = { params ["_u", "_n"]; private _h = 0; { _h = (_h * 31 + _x) mod 100003; } forEach toArray (getPlayerUID _u + name _u); (_h mod _n) };
if (_tab isEqualTo "FIND") then {
    private _left = _pool select { !((getPlayerUID _x) in _likes) && {!((getPlayerUID _x) in _passed)} };
    if !(_visible) then { _rows pushBack ["text", "<t color='#f2ab33'>Votre profil est caché : les autres ne vous voient pas.</t>"]; };
    if ((count _left) isEqualTo 0) then {
        _rows pushBack ["text", "<t color='#8a9a93'>Plus personne à découvrir pour l'instant.</t>"];
        if ((count _passed) > 0) then { _rows pushBack ["buttons", [["REVOIR LES PROFILS PASSÉS", { ["reset"] call comspec_atak_native_fnc_datingAction; }]]]; };
    } else {
        private _u = _left select 0;
        private _uid = getPlayerUID _u;
        private _avatar = [_u] call comspec_atak_native_fnc_avatarPath;
        private _d = player distance2D _u;
        _rows pushBack ["hero", [_avatar, "\z\comspec_atak_native\addons\main\data\app_dating.paa"] select (_avatar isEqualTo ""), format [
            "<t size='1.4' font='RobotoCondensedBold'>%1</t>%2<br/><t color='#ff6b8b'>%3</t> · %4<br/><t color='#c9d4cf'>« %5 »</t>",
            [name _u] call _esc, ["", "  <t size='0.8' color='#ff6b8b'>vous a liké</t>"] select (_uid in _likedBy),
            [format ["à %1 m", round _d], format ["à %1 km", (_d / 1000) toFixed 1]] select (_d >= 1000), [groupId group _u] call _esc,
            _bios select ([_u, count _bios] call _seed)]];
        private _t1 = [_u, count _tags] call _seed;
        _rows pushBack ["text", format ["<t size='0.85' color='#8a9a93'>Aime : </t><t size='0.85'>%1 · %2 · %3</t>", _tags select _t1, _tags select ((_t1 + 4) mod count _tags), _tags select ((_t1 + 7) mod count _tags)]];
        _rows pushBack ["buttons", [
            ["PASSER", compile format ["['pass', '%1'] call comspec_atak_native_fnc_datingAction;", _uid]],
            ["J'AIME", compile format ["['like', '%1'] call comspec_atak_native_fnc_datingAction;", _uid], true]
        ]];
        _rows pushBack ["text", format ["<t size='0.8' color='#8a9a93'>%1 profil(s) à découvrir</t>", count _left]];
    };
} else {
    {
        private _avatar = [_x] call comspec_atak_native_fnc_avatarPath;
        _rows pushBack ["person", [_avatar, "\z\comspec_atak_native\addons\main\data\app_dating.paa"] select (_avatar isEqualTo ""), format ["<t font='RobotoCondensedBold'>%1</t><br/><t size='0.8' color='#ff6b8b'>Match</t>", [name _x] call _esc],
            [["SMS", compile format ["['sms', %1] call comspec_atak_native_fnc_datingAction;", str name _x], true]]];
    } forEach _matches;
    if ((count _matches) isEqualTo 0) then { _rows pushBack ["text", "<t color='#8a9a93'>Pas encore de match. Likez, et attendez qu'on vous like en retour.</t>"]; };
};
_rows pushBack ["switch", "Apparaître sur Rencard", _visible, { ["visible"] call comspec_atak_native_fnc_datingAction; }, "Décochez pour cacher votre profil"];
[_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
true
