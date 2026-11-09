/*
    Catalogue des objets pour le configurateur d'Athena (LOCAL, hors ligne : solo ou éditeur, console de débogage) :
    [_copy] call comspec_sse_fnc_loadoutCatalog -> tableau du catalogue ; _copy (défaut true) : str copié au
    presse-papiers et indication (nombre d'objets, taille). Même chose sans le mod : tools/arma/export_catalogue_equipement.sqf
    (garder les deux identiques). Format, lu par le site en JSON ou en texte SQF :
      ["comspec-equipment-catalog", 1, "<productVersion joint>", [[dossierMod, nomMod]…], [objet…]]
      objet = [classe, racine, typePrincipal, sousType, nom, masse, chargeMax, typeEmplacement, munitions,
               emplacementsAutorisés, compat, chargeurs, nomMod]
        racine : "w" CfgWeapons, "m" CfgMagazines, "v" CfgVehicles (sacs : isBackpack 1), "g" CfgGlasses ;
                 scope >= 2 et scopeArsenal != 0 s'il est défini
        typePrincipal, sousType : BIS_fnc_itemType ; nom : displayName (" -> ', \ -> /), 80 caractères au plus
        masse : WeaponSlotsInfo >> mass (arme), ItemInfo >> mass (objets, tenues), mass (chargeurs, sacs, lunettes) ; -1
        chargeMax : maximumLoad du conteneur (uniforme, gilet : ItemInfo >> containerClass ; sac) ; -1
        typeEmplacement : type de CfgWeapons (1 principale, 2 poing, 4 lanceur, 131072 objets…) ; -1
        munitions : count d'un chargeur ; -1 ; emplacementsAutorisés : allowedSlots[] ou []
        compat (armes) : [[bouche…], [optiques…], [rail latéral…], [bipied…]] (compatibleItems) ; chargeurs (armes) :
        compatibleMagazines ; nomMod : configSourceMod + modParams, « Arma 3 » pour la base
    Un seul passage par table (aucune recherche quadratique) ; quelques secondes pour quelques milliers de classes.
*/
params [["_copy", true, [true]]];
private _ci = if ((productVersion select 2) >= 212) then { compile "compatibleItems _this" } else { { _this call BIS_fnc_compatibleItems } };
private _cm = if ((productVersion select 2) >= 204) then { compile "compatibleMagazines (_this select 0)" } else { { _this call BIS_fnc_compatibleMagazines } };
private _clean = { toString ((toArray ((_this regexReplace ["""", "'"]) regexReplace ["\\", "/"])) apply { [_x, 32] select (_x < 32) }) };
private _mods = createHashMap;
private _modOf = {
    private _d = configSourceMod _this;
    if (_d isEqualTo "") exitWith { _mods set ["", "Arma 3"]; "Arma 3" };
    private _n = _mods get _d;
    if (isNil "_n") then {
        _n = (modParams [_d, ["name"]]) param [0, ""];
        if (!(_n isEqualType "") || {_n isEqualTo ""}) then { _n = _d; };
        _n = (_n call _clean) select [0, 80];
        _mods set [_d, _n];
    };
    _n
};
private _num = { private _r = -1; { if (isNumber _x) exitWith { _r = getNumber _x; }; } forEach _this; _r };
private _out = [];
private _ok = "getNumber (_x >> 'scope') >= 2 && {!isNumber (_x >> 'scopeArsenal') || {getNumber (_x >> 'scopeArsenal') != 0}}";
{
    _x params ["_root", "_table", "_cond"];
    {
        private _cfg = _x;
        private _cls = configName _cfg;
        ([_cls] call BIS_fnc_itemType) params [["_cat", ""], ["_sub", ""]];
        private _isW = _root isEqualTo "w";
        private _isWeapon = _isW && {_cat isEqualTo "Weapon"};
        private _mass = switch (true) do {
            case (_isWeapon): { [_cfg >> "WeaponSlotsInfo" >> "mass", _cfg >> "ItemInfo" >> "mass", _cfg >> "mass"] call _num };
            case (_isW): { [_cfg >> "ItemInfo" >> "mass", _cfg >> "WeaponSlotsInfo" >> "mass", _cfg >> "mass"] call _num };
            default { [_cfg >> "mass"] call _num };
        };
        private _load = -1;
        if (_isW) then {
            private _cc = getText (_cfg >> "ItemInfo" >> "containerClass");
            if (_cc isNotEqualTo "") then { _load = [configFile >> "CfgVehicles" >> _cc >> "maximumLoad"] call _num; };
        };
        if (_root isEqualTo "v") then { _load = [_cfg >> "maximumLoad"] call _num; };
        private _slots = [];
        { if (isArray _x) exitWith { _slots = getArray _x; }; } forEach [_cfg >> "ItemInfo" >> "allowedSlots", _cfg >> "WeaponSlotsInfo" >> "allowedSlots", _cfg >> "allowedSlots"];
        _out pushBack [
            _cls, _root, _cat, _sub,
            ((getText (_cfg >> "displayName")) call _clean) select [0, 80],
            _mass, _load,
            [-1, getNumber (_cfg >> "type")] select _isW,
            [-1, getNumber (_cfg >> "count")] select (_root isEqualTo "m"),
            _slots,
            if (_isWeapon) then { ["MuzzleSlot", "CowsSlot", "PointerSlot", "UnderBarrelSlot"] apply { [_cls, _x] call _ci } } else { [] },
            if (_isWeapon) then { [_cls] call _cm } else { [] },
            _cfg call _modOf
        ];
    } forEach (_cond configClasses (configFile >> _table));
} forEach [
    ["w", "CfgWeapons", _ok],
    ["m", "CfgMagazines", _ok],
    ["v", "CfgVehicles", "getNumber (_x >> 'isBackpack') == 1 && {" + _ok + "}"],
    ["g", "CfgGlasses", _ok]
];
private _ver = (productVersion apply { if (_x isEqualType "") then { _x } else { str _x } }) joinString " ";
private _cat = ["comspec-equipment-catalog", 1, _ver, (keys _mods) apply { [_x, _mods get _x] }, _out];
if (_copy) then {
    private _txt = str _cat;
    copyToClipboard _txt;
    hint format ["Catalogue d'équipement : %1 objets, %2 Ko, copié au presse-papiers.\nCollez-le dans Athena › Équipement › Configurateur › Importer le catalogue.", count _out, round ((count _txt) / 1024)];
    diag_log text format ["[COMSPEC SSE] Équipement : catalogue exporté (%1 objets, %2 caractères).", count _out, count _txt];
};
_cat
