/*
    Réaction d'une IA à « Papiers, s'il vous plaît » (SERVEUR). Lot 1 : règles simples, l'automate complet viendra au
    Lot 3 (checkpoints, nervosité, mensonges).
    [_unit, _controller] call comspec_sse_fnc_idAiAnswer -> HashMap
      answer  present (docId) | refuse | flee | none | search (entravé, inconscient : à fouiller)
      text    phrase pour le contrôleur
    Civil : présente (réglage, 90 %) ; insiste une 2e fois : une chance sur deux de plus.
    Insurgé : présente ses faux, sans broncher. HVT : présente sa couverture.
    Recherché armé et libre de ses mouvements : refuse, ou s'enfuit (150 m, peut fuir).
    Menotté, rendu : présente si on le lui demande (sinon fouille). Inconscient : à fouiller.
*/
params [["_u", objNull, [objNull]], ["_ctrl", objNull, [objNull]]];
private _res = createHashMapFromArray [["answer", "none"], ["docId", ""], ["text", ""]];
if (isNull _u || {!alive _u}) exitWith { _res set ["answer", "search"]; _res set ["text", "La personne ne répond pas : fouillez-la."]; _res };
private _uncon = (_u getVariable ["ACE_isUnconscious", false]) || {(lifeState _u) isEqualTo "INCAPACITATED"};
if (_uncon) exitWith { _res set ["answer", "search"]; _res set ["text", "La personne ne répond pas : fouillez-la."]; _res };
private _restrained = (_u getVariable ["ace_captives_isHandcuffed", false]) || {_u getVariable ["ace_captives_isSurrendering", false]};
private _docs = ["unitDocs", [_u]] call comspec_sse_fnc_idRegistry;
// Pièce présentée : carte ou passeport d'abord, permis en dernier.
private _pickDoc = {
    private _best = "";
    {
        private _d = ["doc", [_x]] call comspec_sse_fnc_idRegistry;
        if ((_d getOrDefault ["type", ""]) in ["id_card", "passport"]) exitWith { _best = _x; };
        if (_best isEqualTo "") then { _best = _x; };
    } forEach _docs;
    _best
};
private _asked = (_u getVariable ["comspec_sse_idAsked", 0]) + 1;
_u setVariable ["comspec_sse_idAsked", _asked];
private _key = [_u] call comspec_sse_fnc_bioPersonKey;
private _r = { [_key, format ["ai|%1|%2", _this, _asked]] call comspec_sse_fnc_bioRand };
private _armed = (primaryWeapon _u) isNotEqualTo "" || {(handgunWeapon _u) isNotEqualTo ""};
private _db = toLower (_u getVariable ["COMSPEC_SSE_BioDb", "auto"]);
// Le sujet se tourne vers le contrôleur (exécuté là où il est local).
["comspec_sse_bioAnimSubject", [_u, _ctrl, ""], _u] call CBA_fnc_targetEvent;

if (_docs isEqualTo []) exitWith {
    _res set ["answer", ["none", "search"] select _restrained];
    _res set ["text", "Il affirme ne pas avoir de papiers sur lui."];
    _res
};
if (_restrained) exitWith {
    _res set ["answer", "present"]; _res set ["docId", call _pickDoc];
    _res set ["text", "Il désigne sa poche : vous prenez ses papiers."];
    _res
};
// Recherché, armé, libre : refus ou fuite.
if (_db isEqualTo "bewl2" && {_armed}) exitWith {
    if (("flee" call _r) < 0.6) then {
        private _dir = (_ctrl getDir _u) + (random 60) - 30;
        private _to = (getPosATL _u) getPos [150, _dir];
        ["comspec_sse_idAiFlee", [_u, _to], _u] call CBA_fnc_targetEvent;
        _res set ["answer", "flee"]; _res set ["text", "Il recule, puis s'enfuit en courant !"];
    } else {
        _res set ["answer", "refuse"]; _res set ["text", "Il refuse sèchement et vous fixe, la main près de son arme."];
    };
    _res
};
private _doc = ["doc", [call _pickDoc]] call comspec_sse_fnc_idRegistry;
private _kind = _doc getOrDefault ["kind", "civil"];
if (_kind isEqualTo "civil" && {(side group _u) isEqualTo civilian}) then {
    private _rate = (missionNamespace getVariable ["comspec_sse_id_civComply", 90]) / 100;
    if (_asked > 1) then { _rate = _rate + (1 - _rate) * 0.5; };
    if (("comply" call _r) >= _rate) exitWith {
        _res set ["answer", "refuse"]; _res set ["text", "Il hésite, puis refuse de montrer ses papiers."];
    };
    _res set ["answer", "present"]; _res set ["docId", _doc get "id"];
    _res set ["text", "Il fouille ses poches et vous tend ses papiers."];
} else {
    _res set ["answer", "present"]; _res set ["docId", _doc get "id"];
    _res set ["text", ["Il vous tend ses papiers sans un mot.", "Il vous tend ses papiers avec assurance."] select (_kind isEqualTo "cover")];
};
_res
