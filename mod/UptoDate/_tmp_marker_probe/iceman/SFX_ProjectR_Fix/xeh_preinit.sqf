private _boolSettings = [
    ["SFX_EnableIronSight", "SFX_IronSights", true],
    ["SFX_EnableInventory", "SFX_Inventory", true],
    ["SFX_EnableHitorDeath", "SFX_HoD", true],
    ["SFX_Headshotsoundenabled", "SFX_Head", true],
    ["SFX_EnableShelshock", "SFX_Shelshock", true],
    ["SFX_EnableMapsnd", "SFX_Mapsnd", true],
    ["SFX_EnableBreathing", "SFX_Breathsnd", true],
    ["SFX_EnableSuppressionDebrisSound", "SFX_Suppressionsnd", true],
    ["SFX_EnableSuppressionDebrisSound_sniper", "SFX_Suppressionsnd_snp", true],
    ["SFX_AditionalExplosion", "SFX_AdEXP", true],
    ["SFX_AditionalLowAmmoSound", "SFX_LAC", true],
    ["SFX_AditionalWeaponBass", "SFX_Exbs", true]
];

{
    _x params ["_target", "_source", "_default"];

    if (isNil _target) then {
        missionNamespace setVariable [_target, missionNamespace getVariable [_source, _default]];
    };
} forEach _boolSettings;

if (isNil "SFX_FemaleFacesArray") then {
    private _facesSetting = missionNamespace getVariable ["SFX_FemaleFacesArraySetting", []];
    private _faces = [];

    if (_facesSetting isEqualType []) then {
        _faces = _facesSetting;
    };

    if (_facesSetting isEqualType "") then {
        try {
            _faces = parseSimpleArray _facesSetting;
            if !(_faces isEqualType []) then {
                _faces = [];
            };
        } catch {
            _faces = [];
        };
    };

    SFX_FemaleFacesArray = _faces;
};
