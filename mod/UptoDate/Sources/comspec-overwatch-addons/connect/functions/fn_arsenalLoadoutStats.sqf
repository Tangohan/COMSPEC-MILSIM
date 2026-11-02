/*
    Résumé d'une tenue pour la fiche du panneau Tenues Athena : poids total estimé (comme ACE : masse du config,
    0,1 lb par unité), remplissage de la tenue, du gilet et du sac, munitions, grenades, explosifs et matériel
    rangés dans les contenants (regroupés par objet, les plus nombreux d'abord).
    Lecture du config seulement, rien n'est créé ni enfilé. Masses et noms gardés en mémoire pour la partie
    (COMSPEC_ArsenalCfgMemo) : une même pièce n'est lue qu'une fois.
    Params : [tenue (getUnitLoadout, CBA ou texte)]
    Retour : [kg, [[emplacement, % rempli], ...], [[catégorie, [[nom, nombre], ...]], ...]] ; [] si illisible.
*/
params [["_data", [], [[], ""]]];
private _lo = [_data] call comspec_overwatch_connect_fnc_arsenalNormalizeLoadout;
if (_lo isEqualTo []) exitWith { [] };

private _memo = missionNamespace getVariable ["COMSPEC_ArsenalCfgMemo", nil];
if (isNil "_memo" || {(count _memo) > 3000}) then { _memo = createHashMap; missionNamespace setVariable ["COMSPEC_ArsenalCfgMemo", _memo, false]; };

// [masse, nom affiché, catégorie (mag / grenade / explosif / item), capacité du contenant] d'une classe.
private _info = {
    params ["_c"];
    if (!(_c isEqualType "") || {_c isEqualTo ""}) exitWith { [0, "", "item", 0] };
    private _k = toLower _c;
    if (_k in _memo) exitWith { _memo get _k };
    private _out = [0, _c, "item", 0];
    private _mag = configFile >> "CfgMagazines" >> _c;
    private _wep = configFile >> "CfgWeapons" >> _c;
    private _veh = configFile >> "CfgVehicles" >> _c;
    private _gls = configFile >> "CfgGlasses" >> _c;
    switch (true) do {
        case (isClass _mag): {
            private _sim = toLower getText (configFile >> "CfgAmmo" >> getText (_mag >> "ammo") >> "simulation");
            private _cat = switch (true) do {
                case (_sim in ["shotgrenade", "shotsmoke", "shotsmokex", "shotilluminating", "shotcm"]): { "grenade" };
                case (_sim in ["shotmine", "shotdirectionalbomb", "shotboundingmine"]): { "explosive" };
                default { "mag" };
            };
            _out = [getNumber (_mag >> "mass"), getText (_mag >> "displayName"), _cat, 0];
        };
        case (isClass _wep): {
            private _m = getNumber (_wep >> "WeaponSlotsInfo" >> "mass");
            if (_m <= 0) then { _m = getNumber (_wep >> "ItemInfo" >> "mass"); };
            private _cc = getText (_wep >> "ItemInfo" >> "containerClass");
            _out = [_m, getText (_wep >> "displayName"), "item", if (_cc isEqualTo "") then { 0 } else { getNumber (configFile >> "CfgVehicles" >> _cc >> "maximumLoad") }];
        };
        case (isClass _veh): { _out = [getNumber (_veh >> "mass"), getText (_veh >> "displayName"), "item", getNumber (_veh >> "maximumLoad")]; };
        case (isClass _gls): { _out = [getNumber (_gls >> "mass"), getText (_gls >> "displayName"), "item", 0]; };
    };
    if ((_out select 1) isEqualTo "") then { _out set [1, _c]; };
    _memo set [_k, _out];
    _out
};

// Arme avec ses accessoires et ses chargeurs engagés : [arme, bouche, pointeur, optique, [chargeur, balles], [...], bipied].
private _weaponMass = {
    params ["_w"];
    if (!(_w isEqualType []) || {_w isEqualTo []}) exitWith { 0 };
    private _m = 0;
    {
        if (_x isEqualType "") then { _m = _m + (([_x] call _info) select 0); };
        if (_x isEqualType [] && {_x isNotEqualTo []} && {(_x select 0) isEqualType ""}) then { _m = _m + (([_x select 0] call _info) select 0); };
    } forEach _w;
    _m
};

private _counts = createHashMap; // classe -> nombre (contenu des contenants)
private _total = 0;
private _loads = [];
{
    _x params ["_idx", "_label"];
    private _ct = _lo param [_idx, []];
    if (!(_ct isEqualType []) || {_ct isEqualTo []} || {!((_ct select 0) isEqualType "")} || {(_ct select 0) isEqualTo ""}) then { continue };
    ([_ct select 0] call _info) params ["_cm", "", "", "_cap"];
    private _cargo = 0;
    {
        if (!(_x isEqualType []) || {_x isEqualTo []}) then { continue };
        private _first = _x select 0;
        private _n = _x param [1, 1];
        if !(_n isEqualType 0) then { _n = 1; };
        if (_first isEqualType []) then {
            _cargo = _cargo + _n * ([_first] call _weaponMass);
        } else {
            _cargo = _cargo + _n * (([_first] call _info) select 0);
            _counts set [_first, (_counts getOrDefault [_first, 0]) + _n];
        };
    } forEach (_ct param [1, []]);
    _total = _total + _cm + _cargo;
    if (_cap > 0) then { _loads pushBack [_label, round (100 * _cargo / _cap)]; };
} forEach [[3, "Tenue"], [4, "Gilet"], [5, "Sac"]];

{ _total = _total + ([_lo param [_x, []]] call _weaponMass); } forEach [0, 1, 2, 8];
{ _total = _total + (([_lo param [_x, ""]] call _info) select 0); } forEach [6, 7];
private _assigned = _lo param [9, []];
if (_assigned isEqualType []) then { { _total = _total + (([_x] call _info) select 0); } forEach _assigned; };

private _cats = [["mag", "Munitions"], ["grenade", "Grenades"], ["explosive", "Explosifs"], ["item", "Matériel"]] apply {
    _x params ["_cat", "_label"];
    private _rows = [];
    { if ((([_x] call _info) select 2) isEqualTo _cat) then { _rows pushBack [([_x] call _info) select 1, _y]; }; } forEach _counts;
    _rows = [_rows, [], { format ["%1|%2", 100000 - (_x select 1), toLower (_x select 0)] }, "ASCEND"] call BIS_fnc_sortBy;
    [_label, _rows]
};

[(round (_total * 0.1 / 2.2046 * 10)) / 10, _loads, _cats select { (_x select 1) isNotEqualTo [] }]
