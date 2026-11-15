/*
    Attributs Eden à liste du site de la communauté (comspec_sse_fnc_catalogue) : dossier, personne du lore, avis de
    recherche, contexte d'acquisition.
      ["load", [combo, valeur, section, libellé de la ligne vide]] · ["save", combo] -> valeur
    Dans l'éditeur il n'y a pas de partie en cours : la liste n'est lue sur le site que si la connexion Athena du menu
    COMSPEC SSE est ouverte sur ce poste (appel direct à COMSPEC Link, un essai par minute au plus). Sinon : derniers
    référentiels gardés sur ce poste pour les contextes, et pour le reste la valeur déjà enregistrée, marquée
    « hors liste » ; elle est gardée telle quelle et résolue par le serveur au lancement de la mission.
    Valeur enregistrée : le code du site (référence du dossier, code du contexte), ou « code|libellé » pour une personne
    du lore et un avis (le libellé donne le nom du sujet au lancement, sans relire le site).
*/
params [["_op", "load"], ["_a", []]];
disableSerialization;
if (_op isEqualTo "save") exitWith {
    private _c = _a;
    private _i = lbCurSel _c;
    if (_i < 0) exitWith { "" };
    _c lbData _i
};
_a params ["_c", ["_value", "", [""]], ["_section", "", [""]], ["_none", "", [""]]];
// Session Athena mémorisée : reprise une fois (Connexion Athena du menu COMSPEC SSE), comme les profils d'IA.
if (!(uiNamespace getVariable ["comspec_sse_editorRestored", false]) && {!isNil "comspec_sse_fnc_athenaEden"}) then {
    uiNamespace setVariable ["comspec_sse_editorRestored", true];
    ["restore"] call comspec_sse_fnc_athenaEden;
};
["ensure"] call comspec_sse_fnc_catalogue;
(["choices", [_section, [], []]] call comspec_sse_fnc_catalogue) params ["_codes", "_labels", "_source"];
private _withLabel = _section in ["lore", "wanted"];
private _current = (_value splitString "|") param [0, ""];

lbClear _c;
private _origin = switch (_source) do {
    case "site": { "liste du site" };
    case "poste": { "liste gardée sur ce poste" };
    default { "site non lu sur ce poste" };
};
private _first = _c lbAdd ((format ["%1 (%2)", (_none) call comspec_sse_fnc_tr, (_origin) call comspec_sse_fnc_tr]) call comspec_sse_fnc_tr);
_c lbSetData [_first, ""];
private _sel = _first;
{
    private _label = _labels param [_forEachIndex, _x];
    private _i = _c lbAdd _label; // i18n:brut
    _c lbSetData [_i, if (_withLabel) then { format ["%1|%2", _x, _label] } else { _x }];
    if (_x isEqualTo _current) then { _sel = _i; };
} forEach _codes;
// Valeur enregistrée dans la mission mais absente de la liste : gardée, jamais perdue en silence.
if (_current isNotEqualTo "" && {_sel isEqualTo _first}) then {
    private _shown = (_value splitString "|") param [1, _current];
    private _i = _c lbAdd ((format [("%1 (hors liste)") call comspec_sse_fnc_tr, _shown]) call comspec_sse_fnc_tr);
    _c lbSetData [_i, _value];
    _sel = _i;
};
_c lbSetCurSel _sel;
