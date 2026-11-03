/*
    Contrôle N4 : comparaison du visage (SERVEUR). Le visage de la personne qui présente le document est relevé
    (téléphone, SEEK) puis comparé à la base biométrique (comspec_sse_fnc_bioSearch, modalité visage seule) et à la photo du
    document : une HVT est reconnue même sous un faux nom, grâce à sa clé biométrique.
    [_doc, _controller, _subject] call comspec_sse_fnc_idBio -> résultat (même forme que fn_idEvaluate, level 4) :
      state, verdict, lines, codes, plus score (taux de confiance 0-100), ref (dossier), tier, hvtRef, quality (cliché)
    Qualité du cliché : distance (au-delà de 2 m elle chute), lumière, visage couvert (cagoule, foulard, masque),
    compétence du contrôleur ; difficulté « réaliste » plus sévère. Hors liaison : base locale du terminal ; avec la
    liaison Athena (COMSPEC Link) : ABIS complet.
*/
params [["_doc", createHashMap, [createHashMap]], ["_ctrl", objNull, [objNull]], ["_subj", objNull, [objNull]]];
private _res = createHashMapFromArray [["level", 4], ["state", "ok"], ["lines", []], ["codes", []], ["verdict", "ras"], ["score", 0], ["ref", ""], ["tier", 0], ["hvtRef", ""], ["quality", 0]];
private _lines = _res get "lines";
private _codes = _res get "codes";
private _worse = {
    params ["_state", "_verdict"];
    private _rank = ["na", "ok", "doubt", "fail", "alert"];
    if ((_rank find _state) > (_rank find (_res get "state"))) then { _res set ["state", _state]; _res set ["verdict", _verdict]; };
};
if (isNull _subj || {!alive _subj}) exitWith {
    _res set ["state", "na"];
    _lines pushBack "Comparaison du visage impossible : personne à photographier absente.";
    _res
};
// --- Cliché du visage ----------------------------------------------------------------------------------------------
private _d = _ctrl distance _subj;
private _light = if (sunOrMoon > 0.5) then { 1 } else { 0.35 + 0.4 * moonIntensity };
if (!isNull _ctrl && {_ctrl isFlashlightOn (currentWeapon _ctrl)}) then { _light = 1; };
private _cover = toLower ((goggles _subj) + " " + (headgear _subj));
private _masked = (["balaclava", "bandana", "shemag", "mask", "cagoule", "scarf", "facewrap", "respirator"] findIf { (_cover find _x) >= 0 }) >= 0;
private _skill = if (isNull _ctrl) then { 0.5 } else { [skill _ctrl, [0.75, 0.9] select (_ctrl getVariable ["COMSPEC_ID_Expert", false])] select (isPlayer _ctrl) };
private _q = 95 * _light * ([1, (1 - ((_d - 2) / 6)) max 0.3] select (_d > 2)) * (0.75 + 0.25 * _skill);
if (_masked) then { _q = _q * 0.35; _lines pushBack "Visage en partie couvert : cliché dégradé (faites retirer cagoule ou foulard)."; _codes pushBack "face_covered"; };
if (_light < 0.6) then { _lines pushBack "Éclairage faible : cliché dégradé (éclairez le visage)."; _codes pushBack "face_dark"; };
_q = round ((_q * ([1.1, 1, 0.9] select ((missionNamespace getVariable ["comspec_sse_id_difficulty", 1]) min 2))) min 98);
_res set ["quality", _q];
// --- Recherche 1:N (base biométrique) --------------------------------------------------------------------------------
private _attempt = (_subj getVariable ["comspec_sse_idBioTries", 0]) + 1;
_subj setVariable ["comspec_sse_idBioTries", _attempt];
private _scope = ["local", "abis"] select ((["has"] call comspec_sse_fnc_idAthenaPack) || {!isNil "comspec_overwatch_connect_fnc_sseOpenTerminal"});
private _probe = createHashMapFromArray [
    ["unit", _subj], ["scope", _scope], ["attempt", _attempt], ["face", _q], ["nfiqs", []], ["eyes", []],
    ["declaredName", format ["%1 %2", _doc getOrDefault ["first", ""], _doc getOrDefault ["last", ""]]]
];
private _s = if (isNil "comspec_sse_fnc_bioSearch") then { createHashMapFromArray [["verdict", "none"]] } else { [_probe] call comspec_sse_fnc_bioSearch };
private _v = _s getOrDefault ["verdict", "none"];
private _score = round (_s getOrDefault ["score", 0]);
_res set ["score", _score];
_res set ["ref", _s getOrDefault ["ref", ""]];
_res set ["tier", _s getOrDefault ["tier", 0]];
// HVT désignée par le Zeus (fn_idHvt) : reconnue par sa clé, quel que soit le nom imprimé.
private _hvt = ["of", [_subj]] call comspec_sse_fnc_idHvt;
switch (_v) do {
    case "insufficient": {
        _lines pushBack "Cliché du visage inexploitable : rapprochez-vous, éclairez, recommencez."; _codes pushBack "face_insufficient";
        ["doubt", "doute"] call _worse;
    };
    case "match": {
        private _tier = _s getOrDefault ["tier", 0];
        private _nm = _s getOrDefault ["name", ""];
        private _al = _s getOrDefault ["alias", ""];
        _lines pushBack format ["Visage reconnu : %1%2, dossier %3 (confiance %4 %%).", _nm, ["", format [" « %1 »", _al]] select (_al isNotEqualTo ""), _s getOrDefault ["ref", "?"], _score];
        _codes pushBack "face_match";
        if (_s getOrDefault ["mismatch", false]) then {
            _lines pushBack "Le nom des papiers n'est pas celui du dossier biométrique : fausse identité probable."; _codes pushBack "bio_name_mismatch";
            ["fail", "faux"] call _worse;
        };
        switch (true) do {
            case (_tier isEqualTo 1 || {(count _hvt) > 0}): { _lines pushBack "HVT : CIBLE PRIORITAIRE. Confirmez le PID avant toute action."; _codes pushBack "bio_hvt"; ["alert", "hvt"] call _worse; };
            case (_tier isEqualTo 2): { _lines pushBack "Personne RECHERCHÉE (watchlist 2)."; _codes pushBack "bio_wanted"; ["alert", "recherche"] call _worse; };
            case (_tier in [3, 4]): { _lines pushBack format ["Personne signalée (watchlist %1).", _tier]; _codes pushBack "bio_watch"; ["doubt", "signale"] call _worse; };
        };
        if ((count _hvt) > 0) then { _res set ["hvtRef", _hvt getOrDefault ["ref", ""]]; };
    };
    case "possible": {
        _lines pushBack format ["Correspondance possible : %1 (confiance %2 %%, sous le seuil). Confirmez par une autre modalité (empreintes, iris : SEEK).", _s getOrDefault ["name", "?"], _score];
        _codes pushBack "face_possible";
        ["doubt", "doute"] call _worse;
    };
    default {
        _lines pushBack format ["Aucune correspondance dans la base %1 (cliché %2/100).", ["locale", "biométrique (ABIS)"] select (_scope isEqualTo "abis"), _q];
        _codes pushBack "face_none";
    };
};
// --- Visage et photo du document : même personne ? -----------------------------------------------------------------
private _holderKey = _doc getOrDefault ["holderKey", ""];
if (_holderKey isNotEqualTo "" && {_v isNotEqualTo "insufficient"}) then {
    private _same = _holderKey isEqualTo ([_subj] call comspec_sse_fnc_bioPersonKey);
    // Bon cliché : une autre personne se voit presque toujours ; un sosie passe parfois sur un mauvais cliché.
    private _seen = ([format ["%1|%2|N4", _doc getOrDefault ["id", ""], _attempt]] call comspec_sse_fnc_bioHash) / 16777216 < ((_q / 100) * 0.95);
    if (!_same && {_seen}) then {
        _lines pushBack "Le visage ne correspond pas à la photo du document : le porteur n'est pas le titulaire."; _codes pushBack "face_doc_mismatch";
        ["fail", "faux"] call _worse;
    } else {
        _lines pushBack format ["Visage conforme à la photo du document (similarité %1).", [(0.6 + random 0.1) toFixed 2, (0.62 + (_q / 400)) toFixed 2] select _same];
    };
};
_res
