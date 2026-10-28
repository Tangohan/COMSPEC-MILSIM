/*
    Module « Données à extraire (câble ATAK) » (Zeus et Eden).
    Pose sur l'objet visé (Zeus : module lâché sur l'objet ; Eden : objets synchronisés) des données à télécharger
    au câble DL-7 : fichiers, écran de connexion, mot de passe, forçage, alarme, code de fin. Sans objet visé : crée le
    support choisi (portable, téléphone, clé USB…) à l'emplacement du module, sinon prend l'objet à moins de 3 m.
    Zeus : formulaire à la pose. Eden : attributs du module.
*/
params [["_logic", objNull, [objNull]], ["_units", [], [[]]], ["_activated", true, [true]]];
private _ctx = [_logic, _units, _activated] call comspec_atak_native_fnc_moduleContext;
if !(_ctx get "run") exitWith { true };

private _supports = [
    ["(sur l'objet visé)", ""], ["Ordinateur portable", "portable"], ["Ordinateur de bureau", "ordinateur"], ["Serveur / terminal", "serveur"],
    ["Téléphone", "telephone"], ["Tablette", "tablette"], ["Clé USB", "usb"], ["Radio numérique", "radio"]
];
private _themes = [["Windows (bureau bleu)", "windows"], ["Linux (terminal vert)", "linux"], ["Militaire", "militaire"], ["Industriel (SCADA)", "scada"], ["Téléphone verrouillé", "telephone"], ["Coffre chiffré", "coffre"]];
private _fails = [["Verrouiller (forçage seul)", "lock"], ["Effacer les données", "wipe"], ["Déclencher l'alarme", "alarm"], ["Rien", "none"]];
private _ports = [["USB-C", "usb"], ["Ethernet RJ45", "rj45"], ["Série RS-232 (lent)", "serie"]];
private _alarms = [["Aucune", "none"], ["Au branchement", "connect"], ["Mauvais mot de passe", "fail"], ["En fin de téléchargement", "download"]];

private _cur = [
    _logic getVariable ["Name", ""], _logic getVariable ["Support", ""], _logic getVariable ["Theme", "windows"], _logic getVariable ["Banner", ""],
    _logic getVariable ["Password", ""], _logic getVariable ["Hint", ""], _logic getVariable ["Attempts", 3], _logic getVariable ["OnFail", "lock"],
    _logic getVariable ["Crack", true], _logic getVariable ["CrackTime", 90], _logic getVariable ["Duration", 45], _logic getVariable ["Port", "usb"],
    _logic getVariable ["Alarm", "none"], _logic getVariable ["OneShot", false], _logic getVariable ["Files", ""],
    _logic getVariable ["Accent", ""], _logic getVariable ["Wallpaper", ""], _logic getVariable ["Code", ""]
];

private _apply = {
    params ["_vals", "_args"];
    _args params ["_ctx", "_logicPos", "_logicDir"];
    _vals params ["_name", "_support", "_theme", "_banner", "_pw", "_hint", "_att", "_fail", "_crack", "_crackT", "_dur", "_port", "_alarm", "_once", "_files", "_accent", "_wall", "_code"];
    private _targets = +(_ctx get "targets");
    if (_targets isEqualTo [] && {_support isNotEqualTo ""}) then {
        private _cls = (switch (_support) do {
            case "portable": { ["Land_Laptop_unfolded_F", "Land_Laptop_device_F"] };
            case "ordinateur": { ["Land_PCSet_01_screen_F", "Land_Laptop_device_F", "Land_Laptop_unfolded_F"] };
            case "serveur": { ["Land_PortableServer_01_black_F", "Land_DataTerminal_01_F", "Land_Laptop_unfolded_F"] };
            case "telephone": { ["Land_MobilePhone_smart_F"] };
            case "tablette": { ["Land_Tablet_02_F", "Land_Tablet_01_F", "Land_MobilePhone_smart_F"] };
            case "usb": { ["Land_USB_Dongle_01_F", "Land_MobilePhone_old_F"] };
            case "radio": { ["Land_PortableLongRangeRadio_F", "Land_SurvivalRadio_F"] };
            default { ["Land_Laptop_unfolded_F"] };
        }) select { isClass (configFile >> "CfgVehicles" >> _x) };
        if (_cls isNotEqualTo []) then {
            private _o = createVehicle [_cls select 0, _logicPos, [], 0, "CAN_COLLIDE"];
            _o setDir _logicDir;
            _o setPosATL _logicPos;
            { [_x, [[_o], true]] remoteExec ["addCuratorEditableObjects", 2]; } forEach allCurators;
            _targets pushBack _o;
        };
    };
    if (_targets isEqualTo [] && {!isNull (_ctx get "near")}) then { _targets pushBack (_ctx get "near"); };
    if (_targets isEqualTo []) exitWith {
        ["comspec_atak_native_dataNote", ["Données : posez le module sur un objet, synchronisez-le, ou choisissez un support à créer."]] call CBA_fnc_localEvent;
    };
    {
        private _kind = if (_support isNotEqualTo "") then { _support } else { ["ordinateur", "telephone"] select (_x isKindOf "CAManBase") };
        [_x, [
            ["name", _name], ["kind", _kind], ["theme", _theme], ["banner", _banner], ["password", _pw], ["hint", _hint],
            ["attempts", _att], ["onFail", _fail], ["crack", _crack], ["crackTime", _crackT], ["duration", _dur], ["port", _port],
            ["alarm", _alarm], ["oneShot", _once], ["files", _files], ["accent", _accent], ["wallpaper", _wall], ["code", _code]
        ]] call comspec_atak_native_fnc_dataSetup;
    } forEach _targets;
    ["comspec_atak_native_dataNote", [format ["Données posées sur %1 objet(s) (%2 fichier(s)).", count _targets, count ([_files] call comspec_atak_native_fnc_dataParseFiles)]]] call CBA_fnc_localEvent;
};

private _args = [_ctx, getPosATL _logic, getDir _logic];
if (_ctx get "zeus") then {
    [
        "Données à extraire (câble ATAK)",
        [
            ["EDIT", "Nom du support", _cur select 0],
            ["COMBO", "Support à créer", _supports, _cur select 1],
            ["COMBO", "Écran de connexion", _themes, _cur select 2],
            ["EDIT", "Organisation affichée", _cur select 3],
            ["EDIT", "Mot de passe (vide = libre)", _cur select 4],
            ["EDIT", "Indice", _cur select 5],
            ["SLIDER", "Essais (0 = illimité)", [0, 10, _cur select 6]],
            ["COMBO", "Trop d'essais", _fails, _cur select 7],
            ["CHECK", "Forçage possible", _cur select 8],
            ["SLIDER", "Durée du forçage (s)", [10, 600, _cur select 9]],
            ["SLIDER", "Durée du transfert (s)", [5, 600, _cur select 10]],
            ["COMBO", "Port", _ports, _cur select 11],
            ["COMBO", "Alarme", _alarms, _cur select 12],
            ["CHECK", "Effacé après extraction", _cur select 13],
            ["MEMO", "Fichiers", _cur select 14, 6],
            ["EDIT", "Couleur (#rrggbb)", _cur select 15],
            ["EDIT", "Image de l'écran", _cur select 16],
            ["EDIT", "Code serveur en fin", _cur select 17]
        ],
        _apply, _args,
        "Fichiers : un bloc par fichier, séparés par une ligne ---. Première ligne : nom | type (texte, document, identifiants, coord, image, audio). Lignes suivantes : contenu. Coord : carroyage (045123) ou x, y."
    ] call comspec_atak_native_fnc_zeusForm;
} else {
    [_cur, _args] call _apply;
};
deleteVehicle _logic;
true
