/*
    Tinder (app civile, pour rire) : profils des joueurs qui ont le téléphone, « J'aime » / « Passer »,
    match quand c'est réciproque (notification des deux côtés, SMS ouvert).
    Params : [action, argument]  "like" / "pass" : UID du profil affiché   "visible" : apparaître ou non   "sms" : nom du joueur
    Visibilité partagée : player COMSPEC_ATAK_Rencard (vrai par défaut, réglable dans l'app).
*/
params [["_act", ""], ["_arg", ""]];
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _likes = missionNamespace getVariable ["COMSPEC_ATAK_RencardLikes", []];
private _passed = missionNamespace getVariable ["COMSPEC_ATAK_RencardPassed", []];
private _rerender = { [{ if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "DATING") then { ["DATING"] call comspec_atak_native_fnc_pageRender; }; }] call CBA_fnc_execNextFrame; };
switch (_act) do {
    case "visible": {
        player setVariable ["COMSPEC_ATAK_Rencard", !(player getVariable ["COMSPEC_ATAK_Rencard", true]), true];
        call _rerender;
    };
    case "pass": {
        _passed pushBackUnique _arg;
        missionNamespace setVariable ["COMSPEC_ATAK_RencardPassed", _passed];
        call _rerender;
    };
    case "like": {
        _likes pushBackUnique _arg;
        missionNamespace setVariable ["COMSPEC_ATAK_RencardLikes", _likes];
        private _who = (allPlayers select { (getPlayerUID _x) isEqualTo _arg }) param [0, objNull];
        if (!isNull _who) then {
            ["comspec_atak_native_rencard", [getPlayerUID player, name player], _who] call CBA_fnc_targetEvent;
            if (_arg in (missionNamespace getVariable ["COMSPEC_ATAK_RencardLikedBy", []])) then {
                ["SUCCESS", format ["C'est un match avec %1 !", name _who], 6, 50] call comspec_atak_native_fnc_notify;
                [] call comspec_atak_native_fnc_vibrate;
            };
        };
        call _rerender;
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
