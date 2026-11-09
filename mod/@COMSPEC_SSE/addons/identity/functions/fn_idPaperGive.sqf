/*
    API publique pour les mods et les scripts (SERVEUR) : remet à une personne SON papier Athena (objet d'inventaire relié
    à l'identité Athena et au membre).
    [_unit, _athena] call comspec_sse_fnc_idPaperGive -> classe de l'exemplaire posé ("" : papier absent du paquet,
      stock épuisé, appel hors serveur)
      _unit    personne qui le reçoit (inventaire ; plein : au sol à ses pieds) ; elle en devient la propriétaire
      _athena  id du papier sur Athena (nombre ou texte) ou son numéro imprimé (« US47036925 »)
    Même chemin que le kit Athena (fn_idKit) et le module Zeus « Donner des papiers » : papier émis, signalements et
    révocation d'Athena compris. L'automatique (papiers tirés pour les IA) ne s'ajoute plus par-dessus.
*/
params [["_u", objNull, [objNull]], ["_athena", "", ["", 0]]];
if (!isServer || {isNull _u} || {!(_u isKindOf "CAManBase")}) exitWith { "" };
private _reg = ["init"] call comspec_sse_fnc_idRegistry;
if (_athena isEqualType 0) then { _athena = _athena toFixed 0; };
_athena = trim _athena;
if (_athena isEqualTo "") exitWith { "" };
private _k = (_reg getOrDefault ["athenaById", createHashMap]) getOrDefault [_athena, ""];
if (_k isEqualTo "") then {
    private _byNum = (keys (_reg getOrDefault ["athena", createHashMap])) select { (toUpper ((_x splitString "|") param [2, ""])) isEqualTo (toUpper _athena) };
    _k = _byNum param [0, ""];
};
if (_k isEqualTo "") exitWith {
    diag_log text format ["[COMSPEC SSE] Identité : papier Athena %1 absent du paquet, rien remis à %2.", _athena, name _u];
    ""
};
private _spec = ["athenaSpec", _k] call comspec_sse_fnc_idModuleGive;
if ((count _spec) isEqualTo 0) exitWith { "" };
_spec set ["materialize", true];
(_reg get "decided") set [[_u] call comspec_sse_fnc_bioPersonKey, true];
private _id = [_u, _spec] call comspec_sse_fnc_idIssue;
if (_id isEqualTo "") exitWith { "" };
(["doc", [_id]] call comspec_sse_fnc_idRegistry) getOrDefault ["cls", ""]
