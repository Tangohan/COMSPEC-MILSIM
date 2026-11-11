/*
    Consultation des fichiers (poste du joueur) : téléphone (app Fichiers), SEEK II, contrôle d'identité.
    [_type, _critères, _source, _étiquette] call comspec_sse_fnc_fileQuery -> étiquette de la demande
      _type      QW personne (last, first, dob) · QA document (number, country, docType) · QB biométrie (bioKey ou rec)
                 · QV véhicule (plate)
      _critères  HashMap des champs ci-dessus          _source  phone | seek | idcheck
    Réponse asynchrone : évènement CBA local comspec_sse_fileResult [étiquette, résultat] (fn_fileClient « result »),
    et historique de l'app Fichiers. C'est une consultation : rien n'est vérifié sur la personne (le SEEK II le fait).
*/
params [["_type", "QA", [""]], ["_q", createHashMap, [createHashMap]], ["_src", "phone", [""]], ["_tag", "", [""]]];
if (!hasInterface) exitWith { "" };
if (_tag isEqualTo "") then { _tag = format ["fq%1", round (diag_tickTime * 1000)]; };
private _clean = createHashMap;
{ private _v = _q getOrDefault [_x, ""]; if (_v isEqualType "" && {(trim _v) isNotEqualTo ""}) then { _clean set [_x, trim _v]; }; } forEach ["last", "first", "dob", "number", "country", "docType", "bioKey", "rec", "plate"];
["send", ["query", [player, toUpper _type, _clean, _src, _tag]]] call comspec_sse_fnc_fileClient;
_tag
