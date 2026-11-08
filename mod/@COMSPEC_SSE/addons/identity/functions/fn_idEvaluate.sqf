/*
    Évaluation d'un document à un niveau de contrôle (SERVEUR : seul à connaître les défauts).
    [_doc, _controller, _level, _subject] call comspec_sse_fnc_idEvaluate -> HashMap
      _subject (facultatif) : personne qui présente le document ; couverture peu crédible (fn_idCover « score » : tenue,
              équipement, comportement) remarquée à l'examen visuel N0 selon la compétence du contrôleur
      level   0 visuel (N0) | 1 MRZ (N1) | 2 puce NFC (N2) | 3 base (N3, locale ; Athena en complément chez le joueur)
      state   ok | doubt | fail | alert | na        verdict  ras | doute | faux | expire | inconnu | vole | revoque
                                                             | signale | recherche | hvt
      lines   constats lisibles (français, traduits à l'affichage)      codes  constats bruts (journal, Athena)
    N0 dépend de la qualité du faux, de la compétence du contrôleur (skill IA ; joueur : COMSPEC_ID_Expert),
    de la lumière et du réglage de difficulté. Joueur formé au contrôle : _unit setVariable ["COMSPEC_ID_Expert", true, true]. Tirage reproductible : refaire le même contrôle donne le même résultat.
    N1 et N2 sont des lectures machine : un défaut présent est toujours trouvé. N3 hors ligne : base locale du pays
    (numéros délivrés, signalements) et avis de recherche ; jamais la vraie identité.
    BOLO (fn_idHvt) : numéro du document sur un BOLO ou parmi les papiers connus d'une HVT, signalé au N3.
*/
params [["_doc", createHashMap, [createHashMap]], ["_ctrl", objNull, [objNull]], ["_level", 0, [0]], ["_subj", objNull, [objNull]]];
private _res = createHashMapFromArray [["level", _level], ["state", "ok"], ["lines", []], ["codes", []], ["verdict", "ras"]];
private _lines = _res get "lines";
private _codes = _res get "codes";
private _worse = {
    params ["_state", "_verdict"];
    private _rank = ["na", "ok", "doubt", "fail", "alert"];
    if ((_rank find _state) > (_rank find (_res get "state"))) then { _res set ["state", _state]; _res set ["verdict", _verdict]; };
};
if ((count _doc) isEqualTo 0) exitWith {
    _res set ["state", "doubt"]; _res set ["verdict", "inconnu"];
    _lines pushBack "Document vierge : aucun titulaire, aucun numéro délivré.";
    _res
};
date params ["_yy", "_mm", "_dd"];
// Dates AAAA-MM-JJ ramenées à un nombre de jours approché (< ne compare pas les chaînes ; AAAAMMJJ dépasse la
// précision exacte des nombres SQF).
private _days = { private _p = (_this splitString "-") apply { parseNumber _x }; ((_p param [0, 9999]) * 372) + ((_p param [1, 12]) * 31) + (_p param [2, 31]) };
private _expired = ((_doc getOrDefault ["expires", "9999-12-31"]) call _days) < (_yy * 372 + _mm * 31 + _dd);
private _flaws = _doc getOrDefault ["flaws", []];
private _ctrlKey = if (isNull _ctrl) then { "?" } else { [getPlayerUID _ctrl, netId _ctrl] select ((getPlayerUID _ctrl) isEqualTo "") };

switch (_level) do {
    case 0: {
        // Lumière : jour, lune, lampe de l'arme ; difficulté ; compétence.
        private _light = if (sunOrMoon > 0.5) then { 1 } else { 0.25 + 0.35 * moonIntensity };
        if (!isNull _ctrl && {_ctrl isFlashlightOn (currentWeapon _ctrl)}) then { _light = 1; };
        private _skill = if (isNull _ctrl) then { 0.5 } else { if (isPlayer _ctrl) then { [0.6, 0.85] select (_ctrl getVariable ["COMSPEC_ID_Expert", false]) } else { skill _ctrl } };
        private _diff = [1.4, 1, 0.75] select ((missionNamespace getVariable ["comspec_sse_id_difficulty", 1]) min 2);
        private _subtle = 1.25 - ((_doc getOrDefault ["quality", 60]) / 100);
        if (_light < 0.4) then { _lines pushBack "Trop sombre pour bien examiner le document : éclairez-le."; _codes pushBack "dark"; };
        {
            _x params ["_flaw", "_base", "_text"];
            if (_flaw in _flaws) then {
                private _p = ((_base * (0.5 + _skill * 0.7) * _light * _diff * _subtle) max 0.05) min 0.98;
                private _roll = ([format ["%1|%2|N0|%3", _doc get "id", _ctrlKey, _flaw]] call comspec_sse_fnc_bioHash) / 16777216;
                if (_roll < _p) then { _lines pushBack _text; _codes pushBack _flaw; ["doubt", "doute"] call _worse; };
            };
        } forEach [
            ["holo", 0.9, "Hologramme de sécurité absent."],
            ["photo", 0.75, "La photo semble rapportée : bord décollé, plastification reprise."],
            ["font", 0.6, "Caractères du nom irréguliers : police différente du reste du document."]
        ];
        if (_expired) then {
            _lines pushBack format ["Document expiré depuis le %1.", _doc get "expires"]; _codes pushBack "expired"; ["doubt", "expire"] call _worse;
        };
        // Couverture (papiers « couverture » ou infiltré) : une tenue ou un équipement qui ne collent pas aux papiers se
        // remarquent, plus ou moins selon le contrôleur. Les civils ordinaires ne coûtent rien ici.
        if (!isNull _subj && {(_subj getVariable ["comspec_sse_idCoverOn", false]) || {(_doc getOrDefault ["kind", ""]) isEqualTo "cover"}}) then {
            (["score", [_subj]] call comspec_sse_fnc_idCover) params [["_sc", 100], ["_why", []]];
            private _roll = ([format ["%1|%2|N0|cover", _doc get "id", _ctrlKey]] call comspec_sse_fnc_bioHash) / 16777216;
            if (_sc < 60 && {_roll < ((((60 - _sc) / 60) * (0.6 + _skill * 0.4) * _diff) min 0.95)}) then {
                _lines pushBack "Attitude, tenue ou équipement qui ne collent pas aux papiers."; _codes pushBack "cover_incoherent"; ["doubt", "doute"] call _worse;
            };
        };
        // Carte militaire : elle se présente en tenue. En civil, elle reste à vérifier.
        if ((_doc getOrDefault ["type", ""]) isEqualTo "military_id" && {!isNull _subj}) then {
            private _uc = getText (configFile >> "CfgWeapons" >> uniform _subj >> "ItemInfo" >> "uniformClass");
            if !(_uc isNotEqualTo "" && {(getNumber (configFile >> "CfgVehicles" >> _uc >> "side")) in [0, 1, 2]}) then {
                _lines pushBack "Carte militaire, mais la personne est en civil."; _codes pushBack "mil_no_uniform"; ["doubt", "doute"] call _worse;
            };
        };
        if (_lines isEqualTo [] || {_lines isEqualTo ["Trop sombre pour bien examiner le document : éclairez-le."]}) then {
            _lines pushBack "Examen visuel : rien d'anormal.";
        };
    };
    case 1: {
        private _mrz = [_doc] call comspec_sse_fnc_idMrz;
        if (_mrz isEqualTo []) exitWith { _res set ["state", "na"]; _lines pushBack "Pas de bande MRZ sur ce document."; };
        private _p = ["parse", _mrz] call comspec_sse_fnc_idMrz;
        {
            _codes pushBack ("mrz_" + _x);
            _lines pushBack (switch (_x) do {
                case "number": { "MRZ : chiffre de contrôle du numéro faux." };
                case "dob": { "MRZ : chiffre de contrôle de la date de naissance faux." };
                case "expiry": { "MRZ : chiffre de contrôle de la date d'expiration faux." };
                case "composite": { "MRZ : chiffre de contrôle global faux." };
                default { "MRZ illisible ou mal formée." };
            });
            ["fail", "faux"] call _worse;
        } forEach (_p get "errors");
        // Recto et MRZ doivent dire la même chose.
        // Noms comparés sous leur forme MRZ (capitales sans accents) : « Andréou » au recto vaut ANDREOU dans la MRZ.
        private _vizLast = ([createHashMapFromArray [["type", "passport"], ["last", _doc get "last"], ["first", ""]]] call comspec_sse_fnc_idMrz) select 0;
        private _mrzLast = ([createHashMapFromArray [["type", "passport"], ["last", _p getOrDefault ["last", ""]], ["first", ""]]] call comspec_sse_fnc_idMrz) select 0;
        if (_vizLast isNotEqualTo _mrzLast) then { _lines pushBack "MRZ différente du recto : le nom ne correspond pas."; _codes pushBack "mrz_viz_name"; ["fail", "faux"] call _worse; };
        private _dob = _doc get "dob";
        if ((_p getOrDefault ["dob", ""]) isNotEqualTo (((_dob select [2, 2]) + (_dob select [5, 2]) + (_dob select [8, 2])))) then {
            _lines pushBack "MRZ différente du recto : la date de naissance ne correspond pas."; _codes pushBack "mrz_viz_dob"; ["fail", "faux"] call _worse;
        };
        if (_expired) then { _lines pushBack format ["Document expiré (%1).", _doc get "expires"]; _codes pushBack "expired"; ["doubt", "expire"] call _worse; };
        if ((_res get "state") isEqualTo "ok") then { _lines pushBack "MRZ conforme ICAO, chiffres de contrôle justes, identique au recto."; };
    };
    case 2: {
        if !(_doc getOrDefault ["chipExpected", false]) exitWith { _res set ["state", "na"]; _lines pushBack "Ce document n'a pas de puce."; };
        switch (_doc getOrDefault ["chip", "ok"]) do {
            case "absent": { _lines pushBack "Aucune puce détectée sur un document qui doit en avoir une."; _codes pushBack "chip_absent"; ["doubt", "doute"] call _worse; };
            case "defect": { _lines pushBack "Puce présente mais illisible (endommagée ou non personnalisée)."; _codes pushBack "chip_defect"; ["doubt", "doute"] call _worse; };
            case "mismatch": {
                if ((_doc getOrDefault ["chipNote", ""]) isEqualTo "photo") then {
                    _lines pushBack "Puce lue : la photo enregistrée n'est pas celle imprimée."; _codes pushBack "chip_photo";
                } else {
                    _lines pushBack "Puce lue : signature invalide, données différentes du document."; _codes pushBack "chip_mismatch";
                };
                ["fail", "faux"] call _worse;
            };
            default { _lines pushBack "Puce lue : signature valide, données identiques au document."; };
        };
    };
    default {
        private _reg = missionNamespace getVariable ["comspec_sse_idReg", createHashMap];
        private _k = format ["%1|%2|%3", _doc get "country", _doc get "type", _doc get "number"];
        private _base = (_reg getOrDefault ["issued", createHashMap]) getOrDefault [_k, []];
        private _ath = (_reg getOrDefault ["athena", createHashMap]) getOrDefault [_k, createHashMap];
        if (_base isEqualTo [] && {(count _ath) isEqualTo 0}) then {
            _lines pushBack "Numéro inconnu de la base du pays : jamais délivré."; _codes pushBack "unknown_number"; ["fail", "inconnu"] call _worse;
        } else {
            _base params [["_bid", ""], ["_bl", ""], ["_bf", ""], ["_brep", ""]];
            if ((count _ath) > 0) then { _brep = _ath getOrDefault ["report", _brep]; };
            switch (_brep) do {
                case "vole": { _lines pushBack "Document signalé VOLÉ."; _codes pushBack "stolen"; ["alert", "vole"] call _worse; };
                case "perdu": { _lines pushBack "Document déclaré perdu."; _codes pushBack "lost"; ["fail", "vole"] call _worse; };
                case "revoque": { _lines pushBack "Document RÉVOQUÉ par l'autorité."; _codes pushBack "revoked"; ["alert", "revoque"] call _worse; };
            };
            if (_bl isNotEqualTo "" && {(toUpper _bl) isNotEqualTo (toUpper (_doc get "last")) || {(toUpper _bf) isNotEqualTo (toUpper (_doc get "first"))}}) then {
                _lines pushBack "La base attribue ce numéro à une autre personne."; _codes pushBack "holder_mismatch"; ["fail", "faux"] call _worse;
            };
        };
        if (_expired) then { _lines pushBack "Document expiré."; _codes pushBack "expired"; ["doubt", "expire"] call _worse; };
        // Carte militaire : le fichier donne la force et le grade ; une force ennemie du contrôleur est signalée.
        if ((_doc getOrDefault ["type", ""]) isEqualTo "military_id") then {
            _lines pushBack format ["Fichier militaire : %1, %2.", _doc getOrDefault ["force", "?"], _doc getOrDefault ["rank", "?"]];
            private _cardSide = (["east", "west", "guer", "civ"] find (_doc getOrDefault ["cardSide", ""]));
            if (_cardSide >= 0 && {!isNull _ctrl} && {[side group _ctrl, [east, west, independent, civilian] select _cardSide] call BIS_fnc_sideIsEnemy}) then {
                _lines pushBack "Force ennemie : personnel militaire adverse."; _codes pushBack "mil_enemy_force"; ["doubt", "signale"] call _worse;
            };
        };
        // BOLO : numéro du document à surveiller (diffusé par le commandement).
        (["docFlag", [_doc]] call comspec_sse_fnc_idHvt) params [["_bRef", ""], ["_bLvl", ""]];
        if (_bRef isNotEqualTo "") then {
            _lines pushBack format ["BOLO : document à surveiller (réf. %1).", _bRef]; _codes pushBack "bolo_doc";
            [["doubt", "alert"] select (_bLvl isNotEqualTo "surveillance"), ["signale", ["recherche", "hvt"] select (_bLvl isEqualTo "hvt")] select (_bLvl isNotEqualTo "surveillance")] call _worse;
        };
        // Avis de recherche (base locale + paquet Athena) : sur le nom imprimé.
        private _L = toUpper (_doc get "last");
        private _F = toUpper (_doc get "first");
        // Nom et prénom identiques, ou alias égal au nom complet imprimé : avis confirmé. Avis sans prénom, ou alias égal au
        // seul nom : tout porteur de ce nom y répondrait, simple homonymie possible, à recouper (jamais une alerte).
        private _full = trim format ["%1 %2", _F, _L];
        private _fullRev = trim format ["%1 %2", _L, _F];
        private _cSide = ["east", "west", "guer", "civ"] select (([east, west, independent, civilian] find (side group _ctrl)) max 0);
        private _homonym = "";
        private _confirmed = false;
        {
            _x params [["_wl", ""], ["_wf", ""], ["_wa", ""], ["_lvl", "surveillance"], ["_ref", ""], ["_wSides", []]];
            // Recherché de l'opération réservé à certains camps (paquet Athena, wanted[].sides) : invisible des autres.
            if !(_wSides isEqualTo [] || {isNull _ctrl} || {_cSide in _wSides}) then { continue; };
            private _wL = toUpper _wl;
            private _wA = toUpper _wa;
            private _sameName = _wL isNotEqualTo "" && {_wL isEqualTo _L};
            if ((_sameName && {_wf isNotEqualTo ""} && {(toUpper _wf) isEqualTo _F}) || {_wA isNotEqualTo "" && {_wA in [_full, _fullRev]}}) exitWith {
                private _v = switch (_lvl) do { case "hvt": { "hvt" }; case "prioritaire"; case "recherche": { "recherche" }; default { "signale" }; };
                _lines pushBack format ["AVIS : personne %1 (réf. %2).", ["sous surveillance", "RECHERCHÉE", "HVT"] select (["signale", "recherche", "hvt"] find _v), _ref];
                _codes pushBack ("watch_" + _v);
                [["doubt", "alert"] select (_v isNotEqualTo "signale"), _v] call _worse;
                _confirmed = true;
            };
            if (_homonym isEqualTo "" && {(_sameName && {_wf isEqualTo ""}) || {_wA isNotEqualTo "" && {_wA isEqualTo _L}}}) then { _homonym = _ref; };
        } forEach (_reg getOrDefault ["watch", []]);
        if (!_confirmed && {_homonym isNotEqualTo ""}) then {
            _lines pushBack format ["Homonyme possible d'un avis sans prénom (réf. %1) : à recouper, visage ou empreintes.", _homonym];
            _codes pushBack "watch_homonym";
            ["doubt", "doute"] call _worse;
        };
        if ((_res get "state") isEqualTo "ok") then { _lines pushBack "Base du pays : document délivré, valide, aucun signalement."; };
        _res set ["athena", true];
    };
};
_res
