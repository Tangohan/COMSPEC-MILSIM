/*
    Caméra en direct sur un écran TOC (demande de TTRD, 01/11) : affichage « CAMÉRA » (fn_tocScreen, vue CAM). L'écran
    montre en temps réel une caméra du camp choisie au téléphone (app Poste TOC) ou au menu ACE de l'écran : drone du
    camp (nacelle), caméra fixe (fn_camAction) ou caméra poitrine d'un porteur d'ATAK (comme la Live cam).
    Seul le choix circule en multijoueur : variable publique de l'écran COMSPEC_ATAK_TocCam = netId de la source
    (posée par le serveur, joueurs qui rejoignent compris). Chaque joueur crée lui-même sa caméra et sa cible de rendu
    (comspec_toccam_<n>, une par écran), seulement pour les écrans allumés qu'il regarde (fn_tocScreen ne lui passe
    que ceux-là : distance d'affichage, devant soi, pas derrière un mur), à moins de comspec_atak_native_toc_cam_range
    mètres, et au plus comspec_atak_native_toc_cam_max à la fois (les plus proches).
    Ailleurs, l'écran montre la carte et le flux du poste (vue SPLIT), sans rendu. Qualité d'image selon la liaison
    montante de la source (fn_feedLink) : le poste est filaire, le téléphone de qui regarde n'y est pour rien.
    Modes :
      ["sources"]            [[libellé, netId, genre "UAV" | "CAM" | "MAN"]...] caméras du camp que je peux voir
      ["set", [écran, netId]] affiche cette caméra sur l'écran (désigne l'écran TOC si besoin)
      ["tick", écrans]       client, chaque seconde (fn_tocScreen) : renvoie les écrans rendus en direct
      ["release", écran]     arrête le rendu de cet écran sur ce poste et remet sa texture d'origine
      ["label", écran]       nom de la caméra affichée
*/
params [["_mode", "tick"], ["_a", objNull]];
private _fn = comspec_atak_native_fnc_tocCam;
// Image de la source : débit de sa liaison montante seulement (côté de texture en px, 0 : coupée).
private _resOf = {
    params ["_src"];
    if (_src isKindOf "Man" && {_src isEqualTo player}) exitWith { 512 };
    ((["eval", _src] call comspec_atak_native_fnc_feedLink) getOrDefault ["up", [0, 100]]) params [["_k", 0], ["_l", 100]];
    switch (true) do {
        case (_k <= 0): { 0 };
        case (_k >= 1800 && {_l < 20}): { 512 };
        case (_k >= 700): { 256 };
        case (_k >= 250): { 128 };
        default { 64 };
    }
};
switch (_mode) do {
    case "sources": {
        private _side = side group player;
        private _out = [];
        { _out pushBack [format ["Drone · %1", getText (configOf _x >> "displayName")], netId _x, "UAV"]; } forEach (allUnitsUAV select { alive _x && {(side group _x) isEqualTo _side} && {!isNull driver _x || {!isNull gunner _x}} });
        { _out pushBack [format ["Caméra fixe · %1", _x getVariable ["COMSPEC_ATAK_CamName", "caméra"]], netId _x, "CAM"]; } forEach (["list"] call comspec_atak_native_fnc_camAction);
        { _out pushBack [format ["Caméra poitrine · %1", [_x, true] call comspec_atak_native_fnc_unitCallsign], netId _x, "MAN"]; } forEach (allPlayers select { alive _x && {(side group _x) isEqualTo _side} && {[_x] call comspec_atak_native_fnc_hasDevice} && {["can", _x] call comspec_atak_native_fnc_camShare} });
        _out
    };
    case "set": {
        _a params [["_o", objNull], ["_id", ""]];
        if (isNull _o || {_id isEqualTo ""}) exitWith { false };
        // L'ordre des deux demandes au serveur est gardé : la source arrive avant le passage en vue caméra.
        if ((count (_o getVariable ["COMSPEC_ATAK_TocScr", []])) < 8) then { ["ensure", [_o, "SPLIT"]] call comspec_atak_native_fnc_tocScreen; };
        ["view", [_o, "cam", _id]] call comspec_atak_native_fnc_tocScreen;
        ["ensure", [_o, "CAM"]] call comspec_atak_native_fnc_tocScreen;
        true
    };
    case "label": {
        private _src = objectFromNetId (_a getVariable ["COMSPEC_ATAK_TocCam", ""]);
        if (isNull _src) exitWith { "aucune caméra" };
        switch (true) do {
            case (unitIsUAV _src): { getText (configOf _src >> "displayName") };
            case (_src isKindOf "Man"): { [_src, true] call comspec_atak_native_fnc_unitCallsign };
            default { _src getVariable ["COMSPEC_ATAK_CamName", "caméra fixe"] };
        }
    };
    case "release": {
        private _act = missionNamespace getVariable ["COMSPEC_ATAK_TocCams", []];
        private _i = _act findIf { (_x select 0) isEqualTo _a };
        if (_i < 0) exitWith { false };
        (_act select _i) params ["_o", "_cam", "_rt"];
        if (!isNull _cam) then { ["close", [_cam, _rt]] call comspec_atak_native_fnc_tocRtt; };
        // Diapositives lancées sur l'écran : fn_screenCast pose déjà son image, ne pas la recouvrir par l'origine.
        if (!isNull _o && {(count (_o getVariable ["COMSPEC_ATAK_ScreenCast", []])) < 5}) then {
            private _sel = ((_o getVariable ["COMSPEC_ATAK_TocScr", []]) param [2, 0]);
            ["paint", [_o, _sel, _o getVariable ["COMSPEC_ATAK_TocOrig", ""]]] call comspec_atak_native_fnc_tocScreen;
            ["surface", [_o, _sel, false]] call comspec_atak_native_fnc_tocScreen;
        };
        _act deleteAt _i;
        missionNamespace setVariable ["COMSPEC_ATAK_TocCams", _act];
        true
    };
    case "tick": {
        private _reg = _a;
        private _act = missionNamespace getVariable ["COMSPEC_ATAK_TocCams", []];
        private _max = missionNamespace getVariable ["comspec_atak_native_toc_cam_max", 2];
        private _range = missionNamespace getVariable ["comspec_atak_native_toc_cam_range", 40];
        if !(_max isEqualType 0) then { _max = 2; };
        if !(_range isEqualType 0) then { _range = 40; };
        private _eye = positionCameraToWorld [0, 0, 0];
        private _side = str side group player;
        private _cands = _reg select {
            private _c = _x getVariable ["COMSPEC_ATAK_TocScr", []];
            private _o = _x;
            (count _c) >= 8 && {(_c select 3) isEqualTo "CAM"} && {alive _o} && {!(_o getVariable ["COMSPEC_ATAK_TocOff", false])} && {(_eye distance _o) < _range}
                && {(_c select 1) isEqualTo "" || {(_c select 1) isEqualTo _side}}
                && {(count (_o getVariable ["COMSPEC_ATAK_ScreenCast", []])) < 5}
                // Toile de projection : seulement éclairée par un projecteur allumé (fn_projector).
                && {(getNumber (configOf _o >> "comspec_projBoard")) < 1 || {_o getVariable ["COMSPEC_ATAK_ProjLit", false]}}
                && {alive objectFromNetId (_o getVariable ["COMSPEC_ATAK_TocCam", ""])}
                // Regardé il y a moins de 3 s : un coup d'œil ailleurs ne coupe pas l'image, personne devant = pas de rendu.
                // Centre de l'image, pas l'origine du modèle (au sol ou au plafond : hors champ devant un écran mural).
                && {call {
                    if ((worldToScreen (ASLToAGL (["center", _o] call comspec_atak_native_fnc_tocScreen))) isNotEqualTo []) then { _o setVariable ["COMSPEC_ATAK_TocCamSeen", diag_tickTime]; };
                    diag_tickTime - (_o getVariable ["COMSPEC_ATAK_TocCamSeen", -1e9]) < 10
                }}
        };
        private _on = ([_cands, [], { _eye distance _x }, "ASCEND"] call BIS_fnc_sortBy) select [0, (round _max) max 0];
        // Rendus à arrêter : écran hors sélection ou source changée.
        {
            _x params ["_o", "_cam", "", "_id"];
            if (isNull _o || {!(_o in _on)} || {isNull _cam} || {(_o getVariable ["COMSPEC_ATAK_TocCam", ""]) isNotEqualTo _id}) then { ["release", _o] call _fn; };
        } forEach +_act;
        _act = missionNamespace getVariable ["COMSPEC_ATAK_TocCams", []];
        {
            private _o = _x;
            private _i = _act findIf { (_x select 0) isEqualTo _o };
            if (_i < 0) then {
                private _id = _o getVariable ["COMSPEC_ATAK_TocCam", ""];
                private _src = objectFromNetId _id;
                ["check", "ÉCRAN TOC"] call comspec_atak_native_fnc_pipCheck;
                private _n = (uiNamespace getVariable ["COMSPEC_ATAK_TocCamSeq", 0]) + 1;
                uiNamespace setVariable ["COMSPEC_ATAK_TocCamSeq", _n];
                private _rt = format ["comspec_toccam_%1", _n];
                private _kind = switch (true) do { case (unitIsUAV _src): { "UAV" }; case (_src isKindOf "Man"): { "MAN" }; default { "CAM" }; };
                private _cam = "camera" camCreate (ASLToAGL getPosASL _src);
                _cam camSetFov 0.75;
                _cam camCommit 0;
                switch (_kind) do {
                    // Caméra poitrine, comme la Live cam (fn_livecamStart).
                    case "MAN": { _cam attachTo [_src, [0, 0.22, 0.05], "spine3", true]; _cam setVectorDirAndUp [[0, 1, 0], [0, 0, 1]]; };
                    // Nacelle : orientée chaque image par la boucle ci-dessous.
                    case "UAV": {
                        private _p0 = _src selectionPosition ["uavCameraGunnerPos", "Memory"];
                        if (_p0 isEqualTo [0, 0, 0]) then { _p0 = _src selectionPosition ["uavCameraDriverPos", "Memory"]; };
                        _cam attachTo [_src, [[0, 0.3, -0.3], _p0] select (_p0 isNotEqualTo [0, 0, 0])];
                    };
                    default { _cam attachTo [_src, [0, 0.12, 0.03]]; _cam setVectorDirAndUp [[0, 1, 0], [0, 0, 1]]; };
                };
                ["open", [_cam, _rt]] call comspec_atak_native_fnc_tocRtt;
                _rt setPiPEffect [parseNumber (sunOrMoon < 0.3)];
                _act pushBack [_o, _cam, _rt, _id, _kind, _src, -1e9, -1];
                _i = (count _act) - 1;
                if (isNil { missionNamespace getVariable "COMSPEC_ATAK_TocCamPfh" }) then {
                    missionNamespace setVariable ["COMSPEC_ATAK_TocCamPfh", [{
                        private _list = missionNamespace getVariable ["COMSPEC_ATAK_TocCams", []];
                        if ((count _list) isEqualTo 0) exitWith {
                            [missionNamespace getVariable "COMSPEC_ATAK_TocCamPfh"] call CBA_fnc_removePerFrameHandler;
                            missionNamespace setVariable ["COMSPEC_ATAK_TocCamPfh", nil];
                        };
                        {
                            _x params ["", "_cam", "", "", "_kind", "_u"];
                            if (_kind isEqualTo "UAV" && {!isNull _cam} && {alive _u}) then {
                                private _dir = [];
                                private _w = (_u weaponsTurret [0]) param [0, ""];
                                if (!isNull gunner _u && {_w isNotEqualTo ""}) then { _dir = _u vectorWorldToModelVisual (_u weaponDirection _w); } else {
                                    if (isClass (configOf _u >> "PilotCamera")) then { _dir = getPilotCameraDirection _u; };
                                };
                                if ((count _dir) < 3 || {(vectorMagnitude _dir) < 0.5}) then { _dir = [0, 0.94, -0.34]; };
                                _dir = vectorNormalized _dir;
                                private _s = _dir vectorCrossProduct [0, 0, 1];
                                if ((vectorMagnitude _s) < 0.01) then { _s = [1, 0, 0]; };
                                _cam setVectorDirAndUp [_dir, vectorNormalized (_s vectorCrossProduct _dir)];
                            };
                        } forEach _list;
                    }, 0] call CBA_fnc_addPerFrameHandler];
                };
            };
            // Qualité réévaluée toutes les 5 s : texture refaite seulement quand le palier change.
            private _e = _act select _i;
            if (diag_tickTime - (_e select 6) > 5) then {
                _e set [6, diag_tickTime];
                private _res = [_e select 5] call _resOf;
                if (_res isNotEqualTo (_e select 7)) then {
                    _e set [7, _res];
                    private _sel = ((_o getVariable ["COMSPEC_ATAK_TocScr", []]) param [2, 0]);
                    if (isNil { _o getVariable "COMSPEC_ATAK_TocOrig" }) then { _o setVariable ["COMSPEC_ATAK_TocOrig", ["orig", [_o, _sel]] call comspec_atak_native_fnc_tocScreen]; };
                    ["paint", [_o, _sel, [format ["#(argb,%1,%1,1)r2t(%2,1.78)", _res, _e select 2], "#(rgb,8,8,3)color(0,0,0,1)"] select (_res isEqualTo 0)]] call comspec_atak_native_fnc_tocScreen;
                    ["surface", [_o, _sel, true]] call comspec_atak_native_fnc_tocScreen;
                };
            };
        } forEach _on;
        missionNamespace setVariable ["COMSPEC_ATAK_TocCams", _act];
        _on
    };
    default { false };
};
