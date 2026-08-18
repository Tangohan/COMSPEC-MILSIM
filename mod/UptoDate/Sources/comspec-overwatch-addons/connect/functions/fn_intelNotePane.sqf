/*
    Bascule entre les trois surfaces du rédacteur de fiche.

    Les trois volets vivent dans le même dialog : on n’affiche que les contrôles
    de celui qui est demandé, comme les pages du terminal SEEK. Un contrôle sans
    IDC ne pourrait pas être masqué et se superposerait au volet suivant.

    Args: [_pane]
      "redaction"  cadre de rédaction (volet par défaut)
      "pieces"     pièces jointes
      "contexte"   date, lieu, repère, type, thèmes, urgence
*/
params [["_pane", "redaction", [""]]];

if (!hasInterface) exitWith {};

private _disp = uiNamespace getVariable ["COMSPEC_IntelNote_Display", displayNull];
if (isNull _disp) then { _disp = findDisplay 9982; };
if (isNull _disp) exitWith {};

if (!(_pane in ["redaction", "pieces", "contexte"])) then { _pane = "redaction"; };
uiNamespace setVariable ["COMSPEC_IntelNote_Pane", _pane];

// Avant de masquer quoi que ce soit : mémoriser ce qui est encore lisible.
["capture"] call comspec_overwatch_connect_fnc_intelNoteCache;

private _redaction = [9615, 9616, 9617, 9618, 9626];
private _pieces = [9630, 9631, 9632, 9633, 9634, 9635, 9636, 9637, 9638, 9639, 9640, 9641, 9642, 9643];
private _contexte = [
    9650, 9651, 9652, 9653, 9654, 9655, 9656, 9657,
    9660, 9661, 9662, 9663, 9664, 9665, 9666, 9667, 9668, 9669, 9670, 9671,
    9680, 9690, 9691, 9692, 9693, 9694, 9695, 9696, 9697
];

{
    private _ctrl = _disp displayCtrl _x;
    if (!isNull _ctrl) then { _ctrl ctrlShow (_pane isEqualTo "redaction"); };
} forEach _redaction;

{
    private _ctrl = _disp displayCtrl _x;
    if (!isNull _ctrl) then { _ctrl ctrlShow (_pane isEqualTo "pieces"); };
} forEach _pieces;

{
    private _ctrl = _disp displayCtrl _x;
    if (!isNull _ctrl) then { _ctrl ctrlShow (_pane isEqualTo "contexte"); };
} forEach _contexte;

// Poignées latérales : toujours visibles, atténuées sur le volet courant.
private _edgeLeft = _disp displayCtrl 9620;
private _edgeRight = _disp displayCtrl 9621;
if (!isNull _edgeLeft) then {
    _edgeLeft ctrlShow true;
    _edgeLeft ctrlEnable (_pane isNotEqualTo "redaction");
};
if (!isNull _edgeRight) then {
    _edgeRight ctrlShow true;
    _edgeRight ctrlEnable (_pane isNotEqualTo "pieces");
};

// Les champs redevenus visibles peuvent avoir été vidés pendant qu'ils étaient
// masqués : leur remettre la valeur mémorisée.
["restore"] call comspec_overwatch_connect_fnc_intelNoteCache;

[] call comspec_overwatch_connect_fnc_intelNoteRefresh;

if (_pane isEqualTo "redaction") then {
    ctrlSetFocus (_disp displayCtrl 9616);
};
