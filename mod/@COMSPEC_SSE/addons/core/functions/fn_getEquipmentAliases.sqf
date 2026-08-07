/*
    Retourne la liste des classnames acceptés pour un rôle / item SSE.
    Ne conserve que les classes réellement présentes dans CfgWeapons
    (sauf les items COMSPEC_SSE_* toujours listés — définis par ce mod).

    [_roleOrClass] call comspec_sse_fnc_getEquipmentAliases
    Ex. "camera" | "COMSPEC_SSE_Camera" | "seek"
*/
params [
    ["_roleOrClass", "", [""]]
];

private _key = toLower _roleOrClass;

// Normalise classname -> rôle
private _roleMap = createHashMapFromArray [
    ["comspec_sse_camera", "camera"],
    ["comspec_sse_evidencebag", "evidence_bag"],
    ["comspec_sse_fingerprintkit", "fingerprint"],
    ["comspec_sse_dnkit", "dna"],
    ["comspec_sse_seekii", "seek"],
    ["comspec_sse_terminal", "terminal"],
    ["comspec_sse_gloves", "gloves"],
    ["comspec_sse_satphone", "radio"],
    ["camera", "camera"],
    ["evidence_bag", "evidence_bag"],
    ["evidence", "evidence_bag"],
    ["fingerprint", "fingerprint"],
    ["fp", "fingerprint"],
    ["dna", "dna"],
    ["seek", "seek"],
    ["seekii", "seek"],
    ["terminal", "terminal"],
    ["sse_terminal", "terminal"],
    ["gloves", "gloves"],
    ["face", "face"],
    ["radio", "radio"]
];

private _role = _roleMap getOrDefault [_key, ""];
if (_role == "" && {_roleOrClass != ""}) then {
    // Classname inconnu : traiter comme liste à un seul élément
    _role = "custom";
};

private _native = createHashMapFromArray [
    ["camera", ["COMSPEC_SSE_Camera"]],
    ["evidence_bag", ["COMSPEC_SSE_EvidenceBag"]],
    ["fingerprint", ["COMSPEC_SSE_FingerprintKit", "COMSPEC_SSE_SEEKII"]],
    ["dna", ["COMSPEC_SSE_DNKit", "COMSPEC_SSE_SEEKII"]],
    ["seek", ["COMSPEC_SSE_SEEKII"]],
    ["terminal", ["COMSPEC_SSE_Terminal", "COMSPEC_SSE_SEEKII"]],
    ["gloves", ["COMSPEC_SSE_Gloves"]],
    ["face", ["COMSPEC_SSE_Camera", "COMSPEC_SSE_SEEKII"]],
    ["radio", ["COMSPEC_SSE_SatPhone", "COMSPEC_SSE_Phone", "COMSPEC_SSE_Smartphone"]],
    ["custom", [_roleOrClass]]
];

private _list = +(_native getOrDefault [_role, [_roleOrClass]]);

// Substituts d'autres mods — uniquement si l'option CBA est active
private _allowSubs = missionNamespace getVariable ["comspec_sse_allowModItemSubstitutes", true];
if (_allowSubs && {_role != "custom"}) then {
    private _modSubs = createHashMapFromArray [
        // Photo / caméra casque cTab, tablettes tactiques
        ["camera", [
            "ItemcTabHCam",
            "ItemAndroid",
            "ItemAndroidMisc",
            "ItemcTab",
            "ItemcTabMisc"
        ]],
        // Conteneurs / kits médicaux ACE utilisables comme sachet d'urgence
        ["evidence_bag", [
            "ACE_surgicalKit",
            "ACE_personalAidKit",
            "ACE_bodyBag"
        ]],
        // Terminaux de terrain (cTab / ACE) comme kit empreintes de fortune
        ["fingerprint", [
            "ItemAndroid",
            "ItemAndroidMisc",
            "ItemcTab",
            "ItemcTabMisc",
            "ACE_microDAGR",
            "ItemMicroDAGR",
            "ItemMicroDAGRMisc"
        ]],
        // Kits médicaux ACE pour prélèvement ADN simulé
        ["dna", [
            "ACE_surgicalKit",
            "ACE_personalAidKit",
            "ACE_plasmaIV_500",
            "ACE_bloodIV_500"
        ]],
        // SEEK II ↔ tablettes ATAK / cTab / DAGR
        ["seek", [
            "ItemAndroid",
            "ItemAndroidMisc",
            "ItemcTab",
            "ItemcTabMisc",
            "ACE_microDAGR",
            "ItemMicroDAGR",
            "ItemMicroDAGRMisc",
            "ACE_Cellphone"
        ]],
        // Terminal SSE terrain ↔ tablettes / SEEK
        ["terminal", [
            "ItemAndroid",
            "ItemAndroidMisc",
            "ItemcTab",
            "ItemcTabMisc",
            "ACE_microDAGR",
            "ItemMicroDAGR",
            "ItemMicroDAGRMisc",
            "ACE_Cellphone"
        ]],
        ["gloves", [
            "ACE_surgicalKit"
        ]],
        ["face", [
            "ItemcTabHCam",
            "ItemAndroid",
            "ItemAndroidMisc",
            "ItemcTab",
            "ItemcTabMisc",
            "ACE_Cellphone"
        ]],
        // Radios TFAR / ACRE / ACE
        ["radio", [
            "ACE_Cellphone",
            "TFAR_anprc152",
            "TFAR_anprc148jem",
            "TFAR_anprc154",
            "TFAR_rf7800str",
            "tf_anprc152",
            "tf_anprc148jem",
            "ACRE_PRC152",
            "ACRE_PRC148",
            "ACRE_PRC343",
            "ACRE_PRC77"
        ]]
    ];
    { _list pushBackUnique _x } forEach (_modSubs getOrDefault [_role, []]);
};

// SDK mods tiers
if (!isNil "comspec_sse_modClassRegistry") then {
    { _list pushBackUnique _x } forEach (comspec_sse_modClassRegistry getOrDefault [_role, []]);
};

// Alias mission / serveur (EDITBOX CBA) : "camera:MyCam,Other;seek:MyPad"
private _extraRaw = missionNamespace getVariable ["comspec_sse_extraEquipmentAliases", ""];
if (_extraRaw isEqualType "" && {_extraRaw != ""}) then {
    private _chunks = _extraRaw splitString ";";
    {
        private _part = _x splitString ":";
        if (count _part >= 2) then {
            private _r = toLower ([_part select 0] call CBA_fnc_trim);
            if (_r == _role || {_r == _key}) then {
                private _classes = ((_part select 1) splitString ",");
                {
                    private _c = [_x] call CBA_fnc_trim;
                    if (_c != "") then { _list pushBackUnique _c; };
                } forEach _classes;
            };
        };
    } forEach _chunks;
};

// Filtrer : native COMSPEC toujours OK ; autres seulement si définis en config
_list select {
    private _cls = _x;
    if (_cls == "") then {
        false
    } else {
        if ((toLower _cls) find "comspec_sse_" == 0) then {
            true
        } else {
            isClass (configFile >> "CfgWeapons" >> _cls)
                || {isClass (configFile >> "CfgMagazines" >> _cls)}
                || {isClass (configFile >> "CfgVehicles" >> _cls)}
        }
    }
}
