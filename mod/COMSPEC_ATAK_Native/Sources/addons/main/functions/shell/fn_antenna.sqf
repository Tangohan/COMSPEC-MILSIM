/*
    Antennes d'extension de débit, vissées sur le port SMA de la coque (en haut à gauche).
    L'antenne doit être dans l'inventaire ; l'antenne montée est gardée sur le joueur (COMSPEC_ATAK_Antenna).
    Effet (fn_linkQuality) : débit de base multiplié et pertes du terrain, des bâtiments et du brouillage atténuées
    (facteur de liaison m devient m ^ exposant). Contourne l'antenne interne endommagée. Aucun effet sans signal du tout.
      courte SMA   : débit x1,25, exposant 0,85 (toujours efficace, discrète) ;
      fouet longue : débit x1,5, exposant 0,7 ;
      patch 9 dBi  : débit x1,7, exposant 0,6 à l'arrêt ; en mouvement ou en véhicule, mal orientée : x1,05, exposant 0,95.
    Actions :
      ["defs"] ["owned"] ["get"] -> [id, classe, nom, gain, exposant] ou []
      ["mount", id] ["unmount"] ["boost", facteur] -> [facteur, multiplicateur de débit, libellé, effet]
      ["overlay"] : dessin de l'antenne autour du téléphone (appelé par fn_layoutApply).
*/
params [["_act", "get"], ["_arg", ""]];
private _defs = [
    ["stub", "COMSPEC_ATAK_Antenna_Stub", "Antenne courte SMA", 1.25, 0.85],
    ["whip", "COMSPEC_ATAK_Antenna_Whip", "Antenne fouet longue", 1.5, 0.7],
    ["patch", "COMSPEC_ATAK_Antenna_Patch", "Antenne patch 9 dBi", 1.7, 0.6]
];
private _owned = { private _inv = (items player) + (assignedItems player); _defs select { (_x select 1) in _inv } };
private _refresh = {
    uiNamespace setVariable ["COMSPEC_ATAK_LinkQ", []];
    [{ if (!isNull ([] call comspec_atak_native_fnc_display)) then { [] call comspec_atak_native_fnc_layoutApply; }; }] call CBA_fnc_execNextFrame;
};
switch (_act) do {
    case "defs": { _defs };
    case "owned": { call _owned };
    case "get": {
        private _id = player getVariable ["COMSPEC_ATAK_Antenna", ""];
        if (_id isEqualTo "") exitWith { [] };
        (call _owned) param [(call _owned) findIf { (_x select 0) isEqualTo _id }, []]
    };
    case "mount": {
        private _d = (call _owned) param [(call _owned) findIf { (_x select 0) isEqualTo _arg }, []];
        if (_d isEqualTo []) exitWith { false };
        player setVariable ["COMSPEC_ATAK_Antenna", _arg];
        ["INFO", format ["%1 vissée sur le port SMA", _d select 2], 3, 10] call comspec_atak_native_fnc_notify;
        call _refresh;
        true
    };
    case "unmount": {
        player setVariable ["COMSPEC_ATAK_Antenna", nil];
        call _refresh;
        true
    };
    case "boost": {
        private _m = _arg;
        private _d = ["get"] call comspec_atak_native_fnc_antenna;
        if (_d isEqualTo [] || {!(_m isEqualType 0)} || {_m <= 0}) exitWith { [_m, 1, "", ""] };
        _d params ["_id", "", "_name", "_gain", "_exp"];
        if (_id isEqualTo "patch" && {vehicle player isNotEqualTo player || {(speed player) > 1.5}}) then {
            _gain = 1.05; _exp = 0.95; _name = _name + " (mal orientée en mouvement)";
        };
        private _new = (_m min 1) ^ _exp;
        [_new, _gain, _name, format ["+%1 %%", round (((_new * _gain) / (_m min 1) - 1) * 100)]]
    };
    case "overlay": {
        disableSerialization;
        private _d = [] call comspec_atak_native_fnc_display;
        (uiNamespace getVariable ["COMSPEC_ATAK_AntOverlay", []]) params [["_c", controlNull]];
        private _cur = ["get"] call comspec_atak_native_fnc_antenna;
        if (isNull _d || {_cur isEqualTo []}) exitWith { ctrlDelete _c; uiNamespace setVariable ["COMSPEC_ATAK_AntOverlay", []]; false };
        if (isNull _c || {(ctrlParent _c) isNotEqualTo _d}) then {
            ctrlDelete _c;
            _c = _d ctrlCreate ["COMSPEC_RscPicture", -1];
            _c ctrlEnable false;
            _c ctrlSetTextColor [1, 1, 1, 1];
        };
        private _l = [] call comspec_atak_native_fnc_layoutGet;
        private _land = _l get "landscape";
        // Zone dessinée (gen_assets.py ANT_FRAME) en fractions du carré de la coque ; portrait = paysage tourné d'un quart.
        (if (_land) then { [238, -832, 384, 1536] } else { [1344, 238, 1536, 384] }) params ["_fx", "_fy", "_fw", "_fh"];
        (_l get "phone") params ["_px", "_py", "_pw", "_ph"];
        _c ctrlSetText format ["\z\comspec_atak_native\addons\main\data\antenna_%1_%2.paa", _cur select 0, ["port", "land"] select _land];
        _c ctrlSetPosition [_px + _pw * _fx / 2048, _py + _ph * _fy / 2048, _pw * _fw / 2048, _ph * _fh / 2048];
        _c ctrlCommit 0;
        uiNamespace setVariable ["COMSPEC_ATAK_AntOverlay", [_c]];
        true
    };
    default { [] };
}
