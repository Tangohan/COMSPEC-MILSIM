/*
    Registre des caméras fixes, vu d'un seul endroit (app Vidéo live, mosaïque et sources du TOC, tableau des écrans).
    Le registre lui-même reste COMSPEC_ATAK_Cams (serveur, fn_camAction « srvReg ») ; ici, la liste lisible et la règle
    de visibilité, écrite sans effet de bord pour pouvoir être relue et testée.

      [] call comspec_atak_native_fnc_camList
          -> [[id, nom, type, état, position ASL, ptz], ...] : caméras de mon camp que je peux voir
             id    netId de l'objet (objectFromNetId pour le retrouver)
             nom   nom affiché (CAM-1, ou celui saisi dans Eden)
             type  "IPC" (posée par un joueur), "PTZ", "BULLET", "THERMAL", "DOME", "FISHEYE", "TACTICAL", "COVERT"
             état  "OK", "LINK", "NONET", "JAM", "NOACCESS", "DEAD", "GONE" (fn_camAction « status »)
             ptz   true si la tête suit le panoramique (comspec_camPtz)
      ["row", caméra] call ...      -> la ligne d'une caméra
      ["type", caméra] call ...     -> son type
      ["visible", [camp de la caméra, publique, uid du poseur, nom du poseur, identifiants ayant accès,
                   camp du spectateur, uid du spectateur, nom du spectateur, partage obligatoire]] call ...
          -> [visible, raison] ; fonction pure (aucune lecture du jeu), même règle que fn_camShare « can » + le camp
*/
params [["_mode", "list", [""]], ["_a", objNull]];
switch (_mode) do {
    case "type": {
        if (isNull _a) exitWith { "" };
        private _cls = toUpper typeOf _a;
        private _i = ["PTZ", "BULLET", "THERMAL", "MINIDOME", "FISHEYE", "TACTICAL", "COVERT"] findIf { _x in _cls };
        ["IPC", "PTZ", "BULLET", "THERMAL", "DOME", "FISHEYE", "TACTICAL", "COVERT"] select (_i + 1)
    };
    case "row": {
        if (isNull _a) exitWith { [] };
        [netId _a, _a getVariable ["COMSPEC_ATAK_CamName", "CAM"], ["type", _a] call comspec_atak_native_fnc_camList,
            (["status", _a] call comspec_atak_native_fnc_camAction) select 0, getPosASL _a,
            (getNumber (configOf _a >> "comspec_camPtz")) isEqualTo 1]
    };
    case "visible": {
        _a params [["_camSide", "", [""]], ["_public", false, [false]], ["_ownerUid", "", [""]], ["_ownerName", "", [""]], ["_access", [], [[]]],
            ["_viewSide", "", [""]], ["_viewUid", "", [""]], ["_viewName", "", [""]], ["_required", true, [false]]];
        if (_camSide isEqualTo "") exitWith { [false, "caméra sans camp : pas encore enregistrée"] };
        if (_camSide isNotEqualTo _viewSide) exitWith { [false, format ["caméra du camp %1, spectateur du camp %2", _camSide, _viewSide]] };
        if (!_required) exitWith { [true, "partage non obligatoire : tout le camp"] };
        if (_public) exitWith { [true, "caméra d'infrastructure : tout le camp"] };
        if ([_ownerName isEqualTo _viewName, _ownerUid isEqualTo _viewUid] select (_ownerUid isNotEqualTo "")) exitWith { [true, "poseur de la caméra"] };
        if (("ALL" in _access) || {_viewUid in _access}) exitWith { [true, "accès partagé"] };
        [false, "non partagée avec ce spectateur"]
    };
    default { (["list"] call comspec_atak_native_fnc_camAction) apply { ["row", _x] call comspec_atak_native_fnc_camList } };
};
