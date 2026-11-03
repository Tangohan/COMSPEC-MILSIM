/*
    Entrée dans un état de l'automate (SERVEUR, fn_idAiFsm) : ordres réels donnés là où l'IA est locale
    (comspec_sse_idAiDo : doStop, disableAI "PATH", setUnitPos, doMove, allowFleeing, setBehaviour, reveal, joinSilent),
    reddition ACE (ace_captives_setSurrendered), indices visibles (gestes, regards) et phrase courte aux joueurs proches
    (fn_idAiSay). Compte rendu AAR : fuite, menace, reddition d'un suspect, appel de renforts.
    [ia, état] call comspec_sse_fnc_idAiEnter
*/
params [["_u", objNull, [objNull]], ["_st", "", [""]]];
if (!isServer || {isNull _u} || {!alive _u}) exitWith {};
_u setVariable ["comspec_sse_idT", time];
_u setVariable ["comspec_sse_idRoll", random 1];
_u setVariable ["comspec_sse_idNext", time + 1];
private _c = _u getVariable ["comspec_sse_idCtrl", objNull];
// Tout état autre que calme compte comme un contrôle en cours (aussi un état imposé par le Zeus ou un checkpoint).
if (_st isNotEqualTo "calme") then { _u setVariable ["comspec_sse_idActive", true]; };
private _unSurrender = {
    if ((_u getVariable ["ace_captives_isSurrendering", false]) && {!(_u getVariable ["ace_captives_isHandcuffed", false])}) then {
        ["ace_captives_setSurrendered", [_u, false], _u] call CBA_fnc_targetEvent;
    };
};
private _do = { params ["_cmd", ["_args", []]]; ["comspec_sse_idAiDo", [_u, _cmd, _args], _u] call CBA_fnc_targetEvent; };
private _say = { params ["_lines", ["_type", "INFO"]]; [_u, selectRandom _lines, _type, _st] call comspec_sse_fnc_idAiSay; };
private _prof = _u getVariable ["comspec_sse_idProfile", "civil"];
// Nom imprimé sur son premier document (ce que le contrôleur connaît), sinon « individu ».
private _who = {
    private _d = ["doc", [(["unitDocs", [_u]] call comspec_sse_fnc_idRegistry) param [0, ""]]] call comspec_sse_fnc_idRegistry;
    if ((count _d) isEqualTo 0) exitWith { "Individu sans papiers" };
    format ["%1 %2", toUpper (_d get "last"), _d get "first"]
};
private _aar = {
    params ["_kind", "_what"];
    ["add", [_kind, format ["%1 %2 au %3%4", call _who, _what, mapGridPosition _u, ["", format [" (contrôle de %1)", name _c]] select (!isNull _c)], format ["%1|%2|%3", _kind, netId _u, floor (time / 120)]]] call comspec_sse_fnc_idAar;
};

switch (_st) do {
    case "calme": {
        // Retour au calme après un contrôle : il reprend sa route (première entrée dans l'automate : rien).
        if !(_u getVariable ["comspec_sse_idActive", false]) exitWith {};
        _u setVariable ["comspec_sse_idActive", false];
        _u setVariable ["comspec_sse_idCtrl", objNull];
        _u setVariable ["comspec_sse_idDone", true];
        if (_u getVariable ["ace_captives_isHandcuffed", false]) exitWith {};
        if (_u getVariable ["ace_captives_isSurrendering", false]) then { ["ace_captives_setSurrendered", [_u, false], _u] call CBA_fnc_targetEvent; };
        ["free"] call _do;
    };
    case "interpelle": {
        _u setVariable ["comspec_sse_idActive", true];
        ["stop"] call _do;
        ["watch", [_c]] call _do;
        ["pos", ["UP"]] call _do;
        [["Oui ?", "Bonjour… il y a un problème ?", "Qu'est-ce qu'il y a ?"]] call _say;
    };
    case "coopere": {
        ["gesture", ["gestureNod"]] call _do;
        [["D'accord, voilà.", "Pas de problème.", "Tenez, monsieur."]] call _say;
    };
    case "ment": {
        // Mensonge : l'histoire colle au document qu'il présente (couverture ou faux), récitée avec aplomb.
        ["gesture", ["gestureNod"]] call _do;
        private _d = createHashMap;
        {
            private _x0 = ["doc", [_x]] call comspec_sse_fnc_idRegistry;
            if ((_x0 getOrDefault ["kind", ""]) in ["cover", "forged"]) exitWith { _d = _x0; };
        } forEach (["unitDocs", [_u]] call comspec_sse_fnc_idRegistry);
        private _lines = ["Tout est en règle, vous verrez."];
        if ((count _d) > 0) then {
            _lines = [format ["Je m'appelle %1 %2, je suis en règle.", _d getOrDefault ["first", ""], toUpper (_d getOrDefault ["last", ""])]];
            if ((_d getOrDefault ["profession", ""]) isNotEqualTo "") then { _lines pushBack format ["Je suis %1, je vais au travail.", toLower (_d get "profession")]; };
            if ((_d getOrDefault ["pob", ""]) isNotEqualTo "") then { _lines pushBack format ["Je rentre chez moi, à %1.", _d get "pob"]; };
        };
        [_lines] call _say;
    };
    case "nerveux": {
        ["glance", [_c]] call _do;
        [["Euh… oui, une seconde.", "Pourquoi moi ?", "Je… je suis un peu pressé."]] call _say;
    };
    case "refuse": {
        ["gesture", ["gestureNo"]] call _do;
        [["Je n'ai rien à vous montrer.", "Laissez-moi tranquille.", "Vous n'avez pas le droit."], "WARNING"] call _say;
    };
    case "fuit": {
        call _unSurrender;
        private _from = [_c, _u] select (isNull _c);
        private _dir = [(_from getDir _u) + (random 60) - 30, random 360] select (isNull _c);
        ["flee", [(getPosATL _u) getPos [150 + random 100, _dir]]] call _do;
        [["(il s'enfuit en courant)", "(il détale sans un mot)"], "WARNING"] call _say;
        ["FUITE", "s'enfuit"] call _aar;
    };
    case "agressif": {
        // Camp hostile au contrôleur : un civil (insurgé caché) passe dans un groupe de ce camp pour pouvoir tirer.
        call _unSurrender;
        private _cs = [side group _c, side group _u] select (isNull _c);
        private _hs = ([east, independent, west] select { [_x, _cs] call BIS_fnc_sideIsEnemy }) param [0, east];
        private _thr = ((_u nearEntities ["CAManBase", 40]) select { alive _x && {(side group _x) isEqualTo _cs || {_x isEqualTo _c}} }) select [0, 6];
        ["hostile", [_hs, _thr]] call _do;
        [["Reculez !", "N'approchez pas !"], "WARNING"] call _say;
        ["MENACE", "devient hostile"] call _aar;
    };
    case "rendu": {
        ["hold"] call _do;
        if !(_u getVariable ["ace_captives_isHandcuffed", false]) then { ["ace_captives_setSurrendered", [_u, true], _u] call CBA_fnc_targetEvent; };
        [["Ne tirez pas !", "D'accord, d'accord… je me rends."]] call _say;
        if (_prof in ["insurge", "hvt"]) then { ["REDDITION", "se rend"] call _aar; };
    };
    case "renforts": {
        _u setVariable ["comspec_sse_idCalled", true];
        private _n = [_u, _c] call comspec_sse_fnc_idAiReinforce;
        [["(il crie quelque chose vers les maisons voisines)", "(il passe un appel rapide au téléphone)"], "WARNING"] call _say;
        if (_n > 0) then { ["RENFORTS", format ["appelle des renforts (%1 personne(s))", _n]] call _aar; };
    };
};
