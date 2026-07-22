/*
    True si le joueur possède un terminal tactique reconnu (S7 Android), selon le mode
    choisi par le réglage CBA « comspec_overwatch_terminal_mode » :
      0 = slot d'objet assigné uniquement (ItemAndroid équipé, comme un GPS/NVG)
      1 = présence en inventaire uniquement (ItemAndroidMisc — objet cTab ; sans cTab
          chargé cette classe n'existe simplement pas, ce mode ne trouvera rien)
      2 = les deux (par défaut)
*/
params [["_unit", player]];
if (isNull _unit) exitWith { false };

private _mode = missionNamespace getVariable ["comspec_overwatch_terminal_mode", 2];

private _matchAny = {
    params ["_pool", "_classes"];
    (_pool findIf { private _it = _x; (_classes findIf { _it isEqualTo _x || {_it isKindOf _x} }) >= 0 }) >= 0
};

if (_mode in [0, 2]) then {
    if ([assignedItems _unit, ["ItemAndroid"]] call _matchAny) exitWith { true };
};

if (_mode in [1, 2]) then {
    private _pool = [];
    _pool append (items _unit);
    _pool append (weapons _unit);
    _pool append (uniformItems _unit);
    _pool append (vestItems _unit);
    _pool append (backpackItems _unit);
    if ([_pool, ["ItemAndroidMisc", "ItemAndroid"]] call _matchAny) exitWith { true };
};

false
