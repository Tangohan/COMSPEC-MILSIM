/*
    Tinder (app civile, pour rire) : profils des joueurs qui ont le téléphone, « J'aime » / « Passer » / « Super like »,
    match quand c'est réciproque (notification des deux côtés, SMS ouvert). De temps en temps, un profil HVT ou SSE tiré
    des IA de la carte (fn_datingSpecials) : un match livre des indices dans la conversation.
    Params : [action, argument]
      "like" / "pass" / "super" : clé du profil affiché (UID du joueur, ou « ai:netId »)
      "shot"   : photo affichée (0 portrait, 1 buste, 2 en pied ; -1 / +1 pour la précédente / suivante)
      "tab"    : onglet (FIND, MATCH, ME)        "open" : conversation d'un match IA ("" pour revenir)
      "ask"    : demander où est le match IA     "mark" : marquer sur la carte la dernière position donnée
      "visible": apparaître ou non               "bio" : bio suivante         "sms" : nom du joueur
      "reset"  : revoir les profils passés
    Visibilité et bio partagées : player COMSPEC_ATAK_Rencard (vrai par défaut), COMSPEC_ATAK_RencardBio.
*/
params [["_act", ""], ["_arg", ""]];
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _likes = missionNamespace getVariable ["COMSPEC_ATAK_RencardLikes", []];
private _passed = missionNamespace getVariable ["COMSPEC_ATAK_RencardPassed", []];
private _rerender = { [{ if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "DATING") then { ["DATING"] call comspec_atak_native_fnc_pageRender; }; }] call CBA_fnc_execNextFrame; };
// Après chaque choix : nouvelle photo de départ et, parfois, un profil particulier au tour suivant.
private _next = {
    _s set ["rcShot", 0];
    _s set ["rcSpecial", ["roll"] call comspec_atak_native_fnc_datingSpecials];
};
private _aiChat = missionNamespace getVariable ["COMSPEC_ATAK_RencardAiChat", createHashMap];
missionNamespace setVariable ["COMSPEC_ATAK_RencardAiChat", _aiChat];
private _say = {
    params ["_key", "_mine", "_txt", ["_pos", []]];
    private _c = _aiChat getOrDefault [_key, []];
    _c pushBack [_mine, _txt, [dayTime, "HH:MM"] call BIS_fnc_timeToString, _pos];
    while { (count _c) > 30 } do { _c deleteAt 0; };
    _aiChat set [_key, _c];
};
switch (_act) do {
    case "visible": {
        player setVariable ["COMSPEC_ATAK_Rencard", !(player getVariable ["COMSPEC_ATAK_Rencard", true]), true];
        call _rerender;
    };
    case "bio": {
        player setVariable ["COMSPEC_ATAK_RencardBio", ((player getVariable ["COMSPEC_ATAK_RencardBio", -1]) + 1) mod 10, true];
        playSound "ClickSoft";
        call _rerender;
    };
    case "tab": { _s set ["rcTab", _arg]; _s set ["rcOpen", ""]; _s set ["rcShot", 0]; call _rerender; };
    case "open": { _s set ["rcOpen", _arg]; call _rerender; };
    case "shot": {
        private _n = if (_arg isEqualType 0 && {_arg in [-1, 1]}) then { ((_s getOrDefault ["rcShot", 0]) + _arg + 3) mod 3 } else { _arg };
        _s set ["rcShot", _n];
        call _rerender;
    };
    case "pass": {
        _passed pushBackUnique _arg;
        missionNamespace setVariable ["COMSPEC_ATAK_RencardPassed", _passed];
        call _next;
        call _rerender;
    };
    case "like";
    case "super": {
        private _super = _act isEqualTo "super";
        if (_super && {diag_tickTime - (missionNamespace getVariable ["COMSPEC_ATAK_RencardSuperAt", -1e9]) < 600}) exitWith {
            ["INFO", "Tinder : un seul super like toutes les 10 minutes", 3, 20] call comspec_atak_native_fnc_notify;
        };
        if (_super) then { missionNamespace setVariable ["COMSPEC_ATAK_RencardSuperAt", diag_tickTime]; };
        _likes pushBackUnique _arg;
        missionNamespace setVariable ["COMSPEC_ATAK_RencardLikes", _likes];
        if ((_arg select [0, 3]) isEqualTo "ai:") then {
            // Profil IA : réponse quelques secondes plus tard, match selon la chance du profil (meilleure avec un super like).
            private _u = ["unit", _arg] call comspec_atak_native_fnc_datingSpecials;
            if (!isNull _u) then {
                [_arg, _u, _super] spawn {
                    params ["_key", "_u", "_super"];
                    sleep (4 + random 12);
                    if (isNull _u || {!alive _u}) exitWith {};
                    private _chance = ((["chance", _u] call comspec_atak_native_fnc_datingSpecials) + ([0, 0.25] select _super)) min 1;
                    if (random 1 >= _chance) exitWith {};
                    private _m = missionNamespace getVariable ["COMSPEC_ATAK_RencardAiMatches", []];
                    _m pushBackUnique _key;
                    missionNamespace setVariable ["COMSPEC_ATAK_RencardAiMatches", _m];
                    private _name = (["profile", _u] call comspec_atak_native_fnc_datingSpecials) get "name";
                    (["clue", _u] call comspec_atak_native_fnc_datingSpecials) params ["_txt", "_pos"];
                    private _chat = missionNamespace getVariable ["COMSPEC_ATAK_RencardAiChat", createHashMap];
                    _chat set [_key, [[false, "Salut :) Ton profil m'a plu.", [dayTime, "HH:MM"] call BIS_fnc_timeToString, []], [false, _txt, [dayTime, "HH:MM"] call BIS_fnc_timeToString, _pos]]];
                    missionNamespace setVariable ["COMSPEC_ATAK_RencardAiChat", _chat];
                    ["SUCCESS", format ["Tinder : c'est un match avec %1 !", _name], 6, 50] call comspec_atak_native_fnc_notify;
                    [] call comspec_atak_native_fnc_vibrate;
                    [{ if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "DATING") then { ["DATING"] call comspec_atak_native_fnc_pageRender; }; }] call CBA_fnc_execNextFrame;
                };
            };
        } else {
            private _who = (allPlayers select { (getPlayerUID _x) isEqualTo _arg }) param [0, objNull];
            if (!isNull _who) then {
                ["comspec_atak_native_rencard", [getPlayerUID player, name player, _super], _who] call CBA_fnc_targetEvent;
                if (_arg in (missionNamespace getVariable ["COMSPEC_ATAK_RencardLikedBy", []])) then {
                    ["SUCCESS", format ["C'est un match avec %1 !", name _who], 6, 50] call comspec_atak_native_fnc_notify;
                    [] call comspec_atak_native_fnc_vibrate;
                };
            };
        };
        if (_super) then { playSound "ClickSoft"; };
        call _next;
        call _rerender;
    };
    case "ask": {
        // Où es-tu ? Une réponse toutes les 3 minutes au plus par profil.
        private _u = ["unit", _arg] call comspec_atak_native_fnc_datingSpecials;
        [_arg, true, "Tu es où, là ?"] call _say;
        private _at = missionNamespace getVariable ["COMSPEC_ATAK_RencardAskAt", createHashMap];
        missionNamespace setVariable ["COMSPEC_ATAK_RencardAskAt", _at];
        if (diag_tickTime - (_at getOrDefault [_arg, -1e9]) < 180 && {!isNull _u} && {alive _u}) then {
            [_arg, false, selectRandom ["Patience...", "Je t'ai déjà dit, sois pas si pressé.", "Plus tard. Je suis occupé."]] call _say;
        } else {
            _at set [_arg, diag_tickTime];
            (["clue", _u] call comspec_atak_native_fnc_datingSpecials) params ["_txt", ["_pos", []]];
            [_arg, false, _txt, _pos] call _say;
            if (_pos isNotEqualTo []) then { [] call comspec_atak_native_fnc_vibrate; };
        };
        call _rerender;
    };
    case "mark": {
        private _c = (_aiChat getOrDefault [_arg, []]) select { (_x param [3, []]) isNotEqualTo [] };
        if (_c isEqualTo []) exitWith { ["INFO", "Tinder : aucune position donnée pour l'instant", 3, 20] call comspec_atak_native_fnc_notify; };
        private _pos = (_c select -1) select 3;
        private _u = ["unit", _arg] call comspec_atak_native_fnc_datingSpecials;
        private _name = if (isNull _u) then { "Rencard" } else { (["profile", _u] call comspec_atak_native_fnc_datingSpecials) get "name" };
        private _hvt = !isNull _u && {(["kind", _u] call comspec_atak_native_fnc_datingSpecials) isEqualTo "HVT"};
        private _m = format ["comspec_rencard_%1", _arg];
        deleteMarkerLocal _m;
        deleteMarkerLocal (_m + "_z");
        private _z = createMarkerLocal [_m + "_z", _pos];
        _z setMarkerShapeLocal "ELLIPSE";
        _z setMarkerSizeLocal [[150, 300] select _hvt, [150, 300] select _hvt];
        _z setMarkerBrushLocal "Border";
        _z setMarkerColorLocal "ColorPink";
        private _mk = createMarkerLocal [_m, _pos];
        _mk setMarkerTypeLocal "hd_unknown";
        _mk setMarkerColorLocal "ColorPink";
        _mk setMarkerTextLocal format ["Rencard : %1 (%2)", _name, [dayTime, "HH:MM"] call BIS_fnc_timeToString];
        ["SUCCESS", format ["Tinder : rendez-vous de %1 marqué sur votre carte (zone approximative)", _name], 4, 30] call comspec_atak_native_fnc_notify;
    };
    case "sms": {
        _s set ["chatPeer", _arg];
        ["CHAT"] call comspec_atak_native_fnc_navigate;
    };
    case "reset": {
        missionNamespace setVariable ["COMSPEC_ATAK_RencardPassed", []];
        call _rerender;
    };
};
true
