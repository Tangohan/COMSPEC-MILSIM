/*
    Réaction d'une IA à « Papiers, s'il vous plaît » (SERVEUR).
    [_unit, _controller] call comspec_sse_fnc_idAiAnswer -> HashMap
      answer  present (docId) | refuse | flee | none | search (entravé, inconscient : à fouiller)
      text    phrase pour le contrôleur          delay  hésitation avant la réponse (s)
      state   état de l'automate (IA réalistes)
    IA réalistes (réglage, défaut) : décision de l'automate CBA (fn_idAiFsm / fn_idAiBrain) selon le profil, l'état,
    le contexte et la tension ; un menteur présente le document de sa couverture (ou ses faux).
    Sans l'automate : règles du Lot 1 (civil présente à 90 %, insurgé ses faux, HVT sa couverture, recherché armé
    refuse ou s'enfuit ; menotté, rendu : présente ; inconscient : à fouiller).
*/
params [["_u", objNull, [objNull]], ["_ctrl", objNull, [objNull]]];
private _res = createHashMapFromArray [["answer", "none"], ["docId", ""], ["text", ""], ["delay", 0], ["state", ""]];
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
    // Sans papiers : il reste interpellé (l'automate le fait réagir ensuite : nervosité, fuite…).
    if (!_restrained) then { ["engage", [_u, _ctrl]] call comspec_sse_fnc_idAiFsm; };
    _res set ["answer", ["none", "search"] select _restrained];
    _res set ["text", "Il affirme ne pas avoir de papiers sur lui."];
    _res
};
if (_restrained) exitWith {
    _res set ["answer", "present"]; _res set ["docId", call _pickDoc];
    _res set ["text", "Il désigne sa poche : vous prenez ses papiers."];
    _res
};
// IA réalistes : l'automate décide (et continue ensuite d'évoluer : nervosité, fuite, reddition…).
if ((missionNamespace getVariable ["comspec_sse_id_aiFsm", true]) && {!isNil "CBA_statemachine_fnc_create"}) exitWith {
    (["ask", [_u, _ctrl]] call comspec_sse_fnc_idAiFsm) params [["_st", "coopere"], ["_delay", 0]];
    _res set ["state", _st];
    _res set ["delay", _delay];
    // Menteur : le document de sa couverture (ou ses faux) d'abord.
    private _liarDoc = "";
    { if (((["doc", [_x]] call comspec_sse_fnc_idRegistry) getOrDefault ["kind", ""]) in ["cover", "forged"]) exitWith { _liarDoc = _x; }; } forEach _docs;
    private _give = { params ["_t"]; _res set ["answer", "present"]; _res set ["docId", [call _pickDoc, _liarDoc] select (_liarDoc isNotEqualTo "")]; _res set ["text", _t]; };
    switch (_st) do {
        case "ment": { [selectRandom ["Il vous tend ses papiers avec assurance.", "Il sourit et vous tend ses papiers sans hésiter."]] call _give; };
        case "nerveux": { ["Il hésite, jette un regard autour de lui, puis vous tend ses papiers d'une main qui tremble."] call _give; };
        case "rendu": { ["Il lève les mains : vous prenez ses papiers."] call _give; };
        case "refuse": { _res set ["answer", "refuse"]; _res set ["text", selectRandom ["Il refuse de montrer ses papiers et croise les bras.", "Il secoue la tête : pas de papiers pour vous."]]; };
        case "renforts": { _res set ["answer", "refuse"]; _res set ["text", "Il refuse et crie quelque chose vers les maisons voisines !"]; };
        case "agressif": { _res set ["answer", "refuse"]; _res set ["text", "Il recule d'un pas, la main sur son arme !"]; };
        case "fuit": { _res set ["answer", "flee"]; _res set ["text", "Il recule, puis s'enfuit en courant !"]; };
        default { ["Il fouille ses poches et vous tend ses papiers."] call _give; };
    };
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
