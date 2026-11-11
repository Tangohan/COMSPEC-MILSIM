/*
    Consultation des fichiers (poste du joueur) : téléphone (app Fichiers), SEEK II, contrôle d'identité.
    [_type, _critères, _source, _étiquette] call comspec_sse_fnc_fileQuery -> étiquette de la demande
      _type      QW personne (last, first, dob) · QA document (number, country, docType) · QB biométrie (bioKey ou rec)
                 · QV véhicule (plate)
      _critères  HashMap des champs ci-dessus          _source  phone | seek | idcheck
    Numéro, plaque et pays normalisés ici (fn_fileNorm), comme sur le serveur.
    Réponse asynchrone : évènement CBA local comspec_sse_fileResult [étiquette, résultat] (fn_fileClient « result »),
    et historique de l'app Fichiers. C'est une consultation : rien n'est vérifié sur la personne (le SEEK II le fait).
    Fin garantie : chaque demande est inscrite dans missionNamespace comspec_sse_filePending (étiquette -> [type,
    critères, source, début]) ; sans réponse du serveur en 8 s, fn_fileClient « timeout » la termine (erreur lisible,
    Athena seule si la liaison est là). Erreur : évènement comspec_sse_fileError [étiquette, motif] et
    comspec_sse_fileResult [étiquette, HashMap vide] (le SEEK II conclut sur la biométrie seule).
*/
params [["_type", "QA", [""]], ["_q", createHashMap, [createHashMap]], ["_src", "phone", [""]], ["_tag", "", [""]]];
if (!hasInterface) exitWith { "" };
if (_tag isEqualTo "") then { _tag = format ["fq%1", round (diag_tickTime * 1000)]; };
_type = toUpper _type;
private _clean = createHashMap;
{ private _v = _q getOrDefault [_x, ""]; if (_v isEqualType "" && {(trim _v) isNotEqualTo ""}) then { _clean set [_x, trim _v]; }; } forEach ["last", "first", "dob", "number", "country", "docType", "bioKey", "rec", "plate"];
{ _x params ["_k", "_op"]; if (_k in _clean) then { _clean set [_k, [_op, _clean get _k] call comspec_sse_fnc_fileNorm]; }; } forEach [["number", "number"], ["plate", "plate"], ["country", "country"]];
private _pend = missionNamespace getVariable ["comspec_sse_filePending", createHashMap];
missionNamespace setVariable ["comspec_sse_filePending", _pend];
_pend set [_tag, [_type, +_clean, _src, diag_tickTime]];
[{ ["timeout", _this] call comspec_sse_fnc_fileClient; }, [_tag], 8] call CBA_fnc_waitAndExecute;
["send", ["query", [player, _type, _clean, _src, _tag]]] call comspec_sse_fnc_fileClient;
_tag
