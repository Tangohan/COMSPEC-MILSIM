/*
    Téléphone lâché au sol : notre modèle 3D de la coque S7 (COMSPEC_ATAK_VestPhone, couleur de coque du propriétaire)
    posé à plat, écran vers le haut, sur le porte-objet. Les objets téléphone viennent d'autres mods (catalogue
    fn_deviceCatalog) avec leur propre petit modèle : le nôtre, plus grand, le recouvre.
    Retiré dès que le téléphone n'est plus dans le porte-objet (ramassé) ou que celui-ci disparaît.
    Appelé par l'événement Put (fn_linkAllyInit), sur la machine de celui qui pose. Params : [porte-objet, objet]
*/
params [["_holder", objNull], ["_item", ""]];
if (isNull _holder || {!(_holder isKindOf "WeaponHolder" || {_holder isKindOf "WeaponHolderSimulated"})}) exitWith {};
if (!isNull (_holder getVariable ["COMSPEC_ATAK_DropModel", objNull])) exitWith {};
private _tint = profileNamespace getVariable ["COMSPEC_ATAK_ShellTint", "coyote"];
private _m = createSimpleObject ["COMSPEC_ATAK_VestPhone", (getPosASL _holder) vectorAdd [0, 0, 0.012], false];
_m setObjectTextureGlobal [0, format ["\z\comspec_atak_native\addons\main\data\vest_phone%1_co.paa", ["", "_" + _tint] select (_tint in ["od", "black"])]];
_m setObjectTextureGlobal [1, ""];
// Modèle : +Z (vectorDir) = dos du téléphone. À plat écran en l'air : dos vers le sol, axe long à l'horizontale.
private _yaw = getDir _holder + random 40 - 20;
_m setVectorDirAndUp [[0, 0, -1], [sin _yaw, cos _yaw, 0]];
_holder setVariable ["COMSPEC_ATAK_DropModel", _m, true];
[{
    params ["_args", "_pfh"];
    _args params ["_holder", "_m", "_item"];
    private _gone = isNull _holder || {!((toLower _item) in (((itemCargo _holder) + (weaponCargo _holder)) apply { toLower _x }))};
    if (_gone) then {
        deleteVehicle _m;
        if (!isNull _holder) then { _holder setVariable ["COMSPEC_ATAK_DropModel", objNull, true]; };
        [_pfh] call CBA_fnc_removePerFrameHandler;
    } else {
        // Porte-objet qui glisse ou tombe (WeaponHolderSimulated) : le modèle suit.
        if (((getPosASL _m) distance ((getPosASL _holder) vectorAdd [0, 0, 0.012])) > 0.05) then { _m setPosASL ((getPosASL _holder) vectorAdd [0, 0, 0.012]); };
    };
}, 1, [_holder, _m, _item]] call CBA_fnc_addPerFrameHandler;
