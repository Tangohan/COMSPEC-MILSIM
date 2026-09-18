/*
    Remplit la page « RENS » du tiroir ATAK : état du brouillon, liaison,
    rappel de ce qu’on attend d’une fiche.
*/
if (!hasInterface) exitWith {};

private _group = uiNamespace getVariable ["COMSPEC_ATAK_Note_group", controlNull];
if (isNull _group) exitWith {};

private _body = _group controlsGroupCtrl 9863;
if (isNull _body) exitWith {};

private _draft = profileNamespace getVariable ["COMSPEC_IntelNote_Draft", []];
if (!(_draft isEqualType [])) then { _draft = []; };
private _draftBody = _draft param [0, "", [""]];
private _draftThemes = _draft param [4, [], [[]]];

private _linked = missionNamespace getVariable ["COMSPEC_AthenaReady", false];
private _grid = mapGridPosition player;

private _lines = [
    "<t size='0.80'>Fiche de renseignement simplifiée</t>",
    "",
    "<t size='0.68' color='#B9C0E0'>Notez ce que vous avez constaté : un texte libre, une date, un lieu, des thèmes et jusqu’à quatre pièces jointes. La taille de la fiche se règle dans les options de l’addon (COMSPEC Overwatch → ATAK — fiches FRS / FRM).</t>",
    ""
];

if (_linked) then {
    _lines pushBack "<t color='#8dffc0'>Liaison Athena active — la fiche part directement au bureau SSE.</t>";
} else {
    _lines pushBack "<t color='#ffd080'>Liaison Athena coupée — la fiche sera conservée et transmise au rétablissement.</t>";
};

_lines pushBack format ["<t size='0.66' color='#B9C0E0'>Repère courant : %1</t>", _grid];

if (_draftBody isNotEqualTo "") then {
    _lines pushBack "";
    _lines pushBack format [
        "<t color='#ffd080'>Brouillon en cours : %1 caractère(s), %2 thème(s). Il sera rechargé à l’ouverture du rédacteur.</t>",
        count _draftBody,
        count _draftThemes
    ];
};

_lines pushBack "";
_lines pushBack "<t size='0.62' color='#8A90A8'>Une fiche n’identifie personne et ne vaut pas preuve : elle consigne un constat daté et situé, que l’analyste exploite ensuite.</t>";

_body ctrlSetStructuredText parseText (_lines joinString "<br/>");
