/*
    Fiches « personne » tirées du lore de session Athena pour le passage aux fichiers (SERVEUR, fn_fileServer « query »)
    : app Fichiers du téléphone, SEEK II (QB, clé biométrique) et contrôle d'identité (source idcheck) passent tous par
    là. Seules les données CONNUES des fichiers (registre comspec_sse_loreReg, champ known) sont lues : jamais les
    réponses de l'IA ni le caché MJ. Une personne marquée in_files = false sur Athena n'est pas au fichier.
    [_type, _critères] call comspec_sse_fnc_loreFile -> [fiche…] (même forme que les fiches de fn_fileServer)
      QW : nom (et prénom, date de naissance s'ils sont donnés), ou alias ; QB : clé biométrique -> IA -> comspec_sse_aiUid ;
      QV : plaque d'un véhicule connu ; QA : rien (les documents restent ceux du registre des papiers).
*/
params [["_type", "QW", [""]], ["_q", createHashMap, [createHashMap]]];
if (!isServer) exitWith { [] };
private _reg = missionNamespace getVariable ["comspec_sse_loreReg", createHashMap];
if ((count _reg) isEqualTo 0) exitWith { [] };
private _U = { if (_this isEqualType "") then { toUpper trim _this } else { "" } };
private _dob = { if !(_this isEqualType "") exitWith { "" }; private _d = _this regexReplace ["[^0-9]", ""]; if ((count _d) isEqualTo 8 && {(_this find "/") > 0}) then { _d = (_d select [4, 4]) + (_d select [2, 2]) + (_d select [0, 2]); }; _d };
private _list = { if (_this isEqualType []) then { _this select { _x isEqualType "" || {_x isEqualType []} } } else { [[], [_this]] select (_this isEqualType "" && {_this isNotEqualTo ""}) } };
private _codesOk = ["warrant", "wanted", "flagged", "missing", "armed_dangerous", "caution", "stolen", "lost", "revoked", "expired", "holder_mismatch", "hvt"];
private _alias = createHashMapFromArray [["watchlist", "flagged"], ["person_priority", "hvt"], ["bolo", "flagged"], ["armed", "armed_dangerous"]];
private _rec = {
    params ["_uid", "_e"];
    private _k = _e getOrDefault ["known", createHashMap];
    private _codes = [];
    { private _c = _alias getOrDefault [toLower _x, toLower _x]; if (_c in _codesOk) then { _codes pushBackUnique _c; }; } forEach (((_k getOrDefault ["flags", []]) call _list) select { _x isEqualType "" });
    private _caut = ((_k getOrDefault ["cautions", []]) call _list) select { _x isEqualType "" };
    if (_caut isNotEqualTo []) then { _codes pushBackUnique "caution"; };
    private _lines = [format ["Fichier Athena (session) %1", _e getOrDefault ["ref", ""]]];
    private _al = ((_k getOrDefault ["aliases", []]) call _list) select { _x isEqualType "" };
    if (_al isNotEqualTo []) then { _lines pushBack format ["Alias : %1", (_al select [0, 6]) joinString ", "]; };
    private _desc = [];
    private _h = _k getOrDefault ["height_cm", 0];
    if (_h isEqualType 0 && {_h > 0}) then { _desc pushBack format ["%1 cm", round _h]; };
    private _w = _k getOrDefault ["weight_kg", 0];
    if (_w isEqualType 0 && {_w > 0}) then { _desc pushBack format ["%1 kg", round _w]; };
    { private _v = _k getOrDefault [_x, ""]; if (_v isEqualType "" && {_v isNotEqualTo ""}) then { _desc pushBack _v; }; } forEach ["build", "hair", "eyes", "marks"];
    if (_desc isNotEqualTo []) then { _lines pushBack format ["Signalement : %1", _desc joinString ", "]; };
    { _lines pushBack format ["Mention : %1", _x]; } forEach (_caut select [0, 4]);
    private _aff = _k getOrDefault ["affiliation", ""];
    if (_aff isEqualType "" && {_aff isNotEqualTo ""}) then { _lines pushBack format ["Affiliation : %1", _aff]; };
    private _nat = _k getOrDefault ["nationality", ""];
    if (_nat isEqualType "" && {_nat isNotEqualTo ""}) then { _lines pushBack format ["Nationalité : %1", _nat]; };
    createHashMapFromArray [
        ["kind", "person"], ["ref", _e getOrDefault ["ref", _uid]], ["last", (_k getOrDefault ["last", ""]) call { [_this, ""] select !(_this isEqualType "") }],
        ["first", (_k getOrDefault ["first", ""]) call { [_this, ""] select !(_this isEqualType "") }], ["dob", (_k getOrDefault ["dob", ""]) call { [_this, ""] select !(_this isEqualType "") }],
        ["country", (_k getOrDefault ["country", ""]) call { [_this, ""] select !(_this isEqualType "") }],
        ["codes", _codes], ["lines", _lines select [0, 10]], ["checks", []], ["lore", true]
    ]
};
private _inFiles = { !(((_this getOrDefault ["known", createHashMap]) getOrDefault ["in_files", true]) in [false, 0]) };
private _out = [];
switch (toUpper _type) do {
    case "QW": {
        private _l = (_q getOrDefault ["last", ""]) call _U;
        private _f = (_q getOrDefault ["first", ""]) call _U;
        private _b = (_q getOrDefault ["dob", ""]) call _dob;
        if (_l isEqualTo "") exitWith {};
        {
            if !(_y call _inFiles) then { continue };
            private _k = _y getOrDefault ["known", createHashMap];
            private _byName = ((_k getOrDefault ["last", ""]) call _U) isEqualTo _l && {_f isEqualTo "" || {((_k getOrDefault ["first", ""]) call _U) isEqualTo _f}}
                && {_b isEqualTo "" || {((_k getOrDefault ["dob", ""]) call _dob) in [_b, ""]}};
            private _full = trim format ["%1 %2", _f, _l];
            private _byAlias = ((((_k getOrDefault ["aliases", []]) call _list) select { _x isEqualType "" }) findIf { (_x call _U) in [_l, _full] }) >= 0;
            if (_byName || {_byAlias}) then { _out pushBack ([_x, _y] call _rec); };
        } forEach _reg;
    };
    case "QB": {
        private _key = trim ((_q getOrDefault ["bioKey", ""]) call { [_this, ""] select !(_this isEqualType "") });
        if (_key isEqualTo "" || {isNil "comspec_sse_fnc_bioRegistry"}) exitWith {};
        private _u = ["owner", _key] call comspec_sse_fnc_bioRegistry;
        if !(_u isEqualType objNull) exitWith {};
        private _uid = ["of", _u] call comspec_sse_fnc_loreUid;
        private _e = _reg getOrDefault [_uid, createHashMap];
        if ((count _e) > 0 && {_e call _inFiles}) then { _out pushBack ([_uid, _e] call _rec); };
    };
    case "QV": {
        private _p = ((_q getOrDefault ["plate", ""]) call _U) regexReplace ["[^A-Z0-9]", ""];
        if (_p isEqualTo "") exitWith {};
        {
            if !(_y call _inFiles) then { continue };
            private _vs = ((_y getOrDefault ["known", createHashMap]) getOrDefault ["vehicles", []]) call _list;
            // Véhicule : [plaque, marque, modèle, couleur], ou paires [[plate, …], …], ou texte « plaque … ».
            private _hit = _vs findIf {
                private _pl = call {
                    if (_x isEqualType "") exitWith { (_x splitString " ") param [0, ""] };
                    if ((_x param [0, ""]) isEqualType []) exitWith { private _h = createHashMapFromArray (_x select { _x isEqualType [] && {(count _x) isEqualTo 2} }); _h getOrDefault ["plate", ""] };
                    _x param [0, ""]
                };
                ((_pl call _U) regexReplace ["[^A-Z0-9]", ""]) isEqualTo _p
            };
            if (_hit >= 0) then { _out pushBack ([_x, _y] call _rec); };
        } forEach _reg;
    };
};
_out select [0, 6]
