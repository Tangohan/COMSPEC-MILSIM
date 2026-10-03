/*
    Briefing présenté en direct (diapositives Athena) : un joueur présente, les téléphones de son camp suivent.
    État partagé : missionNamespace COMSPEC_ATAK_BriefLive_<camp> = [présentateur, nom, id diapo, index, total, titre]
    (vide = personne ne présente). Chaque auditeur qui suit pose COMSPEC_ATAK_BriefAt = netId du présentateur,
    ce qui donne la liste des présents.
      ["tick"]          : suit le présentateur (appelé toutes les 2 s) ;
      ["publish"]       : le présentateur diffuse sa diapositive en cours ;
      ["get"]           : renvoie [présentateur, nom, id, index, total, titre] ou [] ;
      ["attendees"]     : joueurs qui suivent ma présentation.
*/
params [["_act", "tick"]];
private _key = format ["COMSPEC_ATAK_BriefLive_%1", side group player];
private _live = missionNamespace getVariable [_key, []];
if (!(_live isEqualType []) || {(count _live) > 0 && {!alive (_live select 0)}}) then { _live = []; };
switch (_act) do {
    case "get": { _live };
    case "attendees": { allPlayers select { (_x getVariable ["COMSPEC_ATAK_BriefAt", ""]) isEqualTo netId player && {_x isNotEqualTo player} } };
    case "publish": {
        private _slides = missionNamespace getVariable ["COMSPEC_BriefingSlides", []];
        private _idx = ((missionNamespace getVariable ["COMSPEC_BriefingSlideIndex", 0]) max 0) min (((count _slides) - 1) max 0);
        private _sl = _slides param [_idx, [0, ""]];
        missionNamespace setVariable [_key, [player, name player, _sl select 0, _idx, count _slides, _sl select 1], true];
    };
    case "tick": {
        private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
        private _pres = _live param [0, objNull];
        // Nouvelle présentation : prévenir une fois.
        private _seen = uiNamespace getVariable ["COMSPEC_ATAK_BriefSeen", objNull];
        if (!isNull _pres && {_pres isNotEqualTo player} && {_pres isNotEqualTo _seen}) then {
            ["INFO", format ["Briefing : %1 présente. Ouvrez l'app Briefing pour suivre.", _live select 1], 8, 60] call comspec_atak_native_fnc_notify;
            [] call comspec_atak_native_fnc_vibrate;
        };
        uiNamespace setVariable ["COMSPEC_ATAK_BriefSeen", _pres];
        private _follow = (_s getOrDefault ["briefFollow", true]) && {!isNull _pres} && {_pres isNotEqualTo player};
        private _onPage = (_s getOrDefault ["activePage", ""]) isEqualTo "BRIEFING" && {!isNull ([] call comspec_atak_native_fnc_display)};
        // Présence : seulement quand le téléphone est sur le briefing et suit le présentateur.
        private _at = ["", netId _pres] select (_follow && _onPage);
        if ((player getVariable ["COMSPEC_ATAK_BriefAt", ""]) isNotEqualTo _at) then { player setVariable ["COMSPEC_ATAK_BriefAt", _at, true]; };
        if (!_follow) exitWith {};
        private _slides = missionNamespace getVariable ["COMSPEC_BriefingSlides", []];
        private _i = _slides findIf { (_x select 0) isEqualTo (_live select 2) };
        // Diapositives absentes ou périmées : les relire depuis Athena (une fois par présentation).
        if (_i < 0 && {(uiNamespace getVariable ["COMSPEC_ATAK_BriefReload", objNull]) isNotEqualTo _pres} && {!isNil "comspec_overwatch_connect_fnc_getBriefingSlides"}) then {
            uiNamespace setVariable ["COMSPEC_ATAK_BriefReload", _pres];
            uiNamespace setVariable ["COMSPEC_ATAK_SlideCache", createHashMap];
            _slides = [] call comspec_overwatch_connect_fnc_getBriefingSlides;
            _i = _slides findIf { (_x select 0) isEqualTo (_live select 2) };
        };
        if (_i < 0) then { _i = (_live select 3) min (((count _slides) - 1) max 0); };
        if ((missionNamespace getVariable ["COMSPEC_BriefingSlideIndex", 0]) isNotEqualTo _i) then { missionNamespace setVariable ["COMSPEC_BriefingSlideIndex", _i]; };
    };
};
