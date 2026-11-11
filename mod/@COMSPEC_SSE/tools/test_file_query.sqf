/*
    Autocontrôle de l'app Fichiers : console de débogage d'un poste joueur (local ou hôte), mission chargée.
    Copier ce fichier dans la console et l'exécuter (local). Rien n'est envoyé à Athena : la licence est lue telle quelle,
    et sans licence acceptée l'étape Athena s'arrête à « non lié ».
      1. normalisation des numéros et des pays (fn_fileNorm)
      2. machine d'états : attente -> erreur bornée (« timeout » simulé), réponse tardive qui remplace l'erreur,
         consultation annulée dont la réponse est ignorée
      3. serveur (hôte seulement) : un papier délivré (fn_idIssue) est retrouvé par son numéro tapé en minuscules avec
         des tirets (numéro de test ZT0036925, laissé au registre de la partie) ; un numéro inconnu répond « aucun enregistrement »
    Résultat dans le RPT et en retour de l'exécution : [] si tout va bien, sinon la liste des échecs.
*/
private _fails = [];
private _eq = { params ["_got", "_want", "_what"]; if (_got isNotEqualTo _want) then { _fails pushBack format ["%1 : %2 (attendu %3)", _what, _got, _want]; }; };

// 1. Normalisation
{ _x params ["_in", "_want"]; [["number", _in] call comspec_sse_fnc_fileNorm, _want, "numéro " + _in] call _eq; } forEach [
    [" alz 036-925 ", "ALZ036925"], ["ALZ.036.925", "ALZ036925"], ["al/123 456", "AL123456"]
];
{ _x params ["_in", "_want"]; [["country", _in] call comspec_sse_fnc_fileNorm, _want, "pays " + _in] call _eq; } forEach [
    ["alt", "ALT"], ["TAN", "TNA"], ["Liv", "LVN"], ["MAL", "MLD"], ["Altis", "ALT"], ["gb", "GBR"], ["D", "DEU"], ["", ""], ["XYZ", "XYZ"]
];

// 2. Machine d'états (poste) : une demande simulée sans serveur, terminée par le minuteur.
private _pend = missionNamespace getVariable ["comspec_sse_filePending", createHashMap];
missionNamespace setVariable ["comspec_sse_filePending", _pend];
private _app = uiNamespace getVariable ["comspec_sse_fileApp", createHashMap];
uiNamespace setVariable ["comspec_sse_fileApp", _app];
private _n0 = count (_app getOrDefault ["history", []]);
private _tag = format ["selftest%1", round (diag_tickTime * 1000)];
_pend set [_tag, ["QA", createHashMapFromArray [["number", "ZZ000000"]], "phone", diag_tickTime]];
["timeout", [_tag]] call comspec_sse_fnc_fileClient;
private _h = _app getOrDefault ["history", []];
[count _h, _n0 + 1, "journal après l'erreur"] call _eq;
private _last = _h param [-1, createHashMap];
[(_last getOrDefault ["error", ""]) isNotEqualTo "", true, "résultat d'erreur lisible"] call _eq;
[_tag in _pend, false, "demande retirée de l'attente"] call _eq;
// Réponse tardive du serveur : remplace l'erreur (même étiquette), pas de doublon au journal.
["result", [_tag, createHashMapFromArray [["qrn", "FQ-T-1"], ["type", "QA"], ["query", createHashMap], ["hit", false], ["codes", ["unknown"]], ["records", []]]]] call comspec_sse_fnc_fileClient;
_h = _app getOrDefault ["history", []];
[count _h, _n0 + 1, "réponse tardive sans doublon"] call _eq;
[(_h param [-1, createHashMap]) getOrDefault ["match", ""], "none", "réponse tardive : aucun enregistrement"] call _eq;
// Consultation annulée : sa réponse est ignorée.
private _tag2 = _tag + "c";
_pend set [_tag2, ["QA", createHashMap, "phone", diag_tickTime]];
[["cancel", [_tag2]] call comspec_sse_fnc_fileClient, true, "annulation acceptée"] call _eq;
["result", [_tag2, createHashMapFromArray [["qrn", "FQ-T-2"], ["type", "QA"], ["hit", false], ["records", []]]]] call comspec_sse_fnc_fileClient;
[count (_app getOrDefault ["history", []]), _n0 + 1, "réponse d'une consultation annulée ignorée"] call _eq;

// 3. Serveur (hôte) : papier délivré retrouvé par son numéro, numéro inconnu sans fiche.
if (isServer) then {
    private _id = [objNull, createHashMapFromArray [["type", "id_card"], ["country", "ALT"], ["number", "ZT0036925"], ["last", "Test"], ["first", "Fichiers"]]] call comspec_sse_fnc_idIssue;
    ["QA", createHashMapFromArray [["number", "zt-003 6925"], ["country", "alt"]], "phone", "selfQA1"] call comspec_sse_fnc_fileQuery;
    ["QA", createHashMapFromArray [["number", "QQ999999"]], "phone", "selfQA2"] call comspec_sse_fnc_fileQuery;
    private _h2 = _app getOrDefault ["history", []];
    private _r1 = _h2 param [(_h2 findIf { (_x getOrDefault ["tag", ""]) isEqualTo "selfQA1" }), createHashMap];
    private _r2 = _h2 param [(_h2 findIf { (_x getOrDefault ["tag", ""]) isEqualTo "selfQA2" }), createHashMap];
    [_r1 getOrDefault ["match", ""], "hit", "papier délivré retrouvé (" + _id + ")"] call _eq;
    [((_r1 getOrDefault ["records", []]) param [0, createHashMap]) getOrDefault ["ref", ""], _id, "fiche du bon document"] call _eq;
    [_r2 getOrDefault ["match", ""], "none", "numéro inconnu : aucun enregistrement"] call _eq;
};
diag_log text format ["[COMSPEC SSE] Autocontrôle Fichiers : %1", ["OK", _fails] select (_fails isNotEqualTo [])];
_fails
