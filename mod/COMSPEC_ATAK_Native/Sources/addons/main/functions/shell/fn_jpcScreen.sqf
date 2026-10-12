/*
    ATAK ouvert sur le JPC : tablette durcie à touches A / B / X / Y (COMSPEC_ATAK_JpcTablet, data/jpc_tablet.p3d,
    tools/model_jpc_tablet.py) accrochée au rabat du porte-plaques, devant la poitrine.
    - Style « porté » JPC (profil COMSPEC_ATAK_CarryStyle = "jpc") : la touche « porter » relève la tablette vers le
      visage et l'interface ATAK s'affiche en direct sur son écran 3D (texture UI, display COMSPEC_RscATAKTexture),
      sans miniature à l'écran. Vue libre, déplacement et arme restent disponibles : on baisse les yeux pour lire.
    - La touche principale prend le terminal en main (plein écran, souris) comme d'habitude ; le reposer rouvre
      l'écran sur le JPC. Rangé : la tablette reste rabattue sur le gilet, écran contre la poitrine.
    - Les autres joueurs voient la tablette ouverte avec un écran ATAK figé (texture globale) ; seul le porteur a
      l'écran vivant (une texture UI n'existe que sur sa machine).
    Sans gilet, en véhicule ou sans terminal : retour à la miniature classique (fn_open).
    Actions :
      ["active"]  true si le style JPC est choisi et utilisable maintenant ;
      ["tick"]    client, toutes les 2 s : crée, replace ou retire l'objet ; repasse en miniature si besoin ;
      ["open"]    relève la tablette et affiche l'ATAK dessus (appelé par fn_open, terminal porté) ;
      ["close"]   rabat la tablette, écran éteint (appelé par fn_close) ;
      ["toggle"]  touche / action ACE « Tablette sur le JPC » : choisit le style JPC et ouvre, ou range ;
      ["sweep"]   serveur : objets orphelins après une déconnexion.
    Réglages fins pour les essais en jeu (missionNamespace) : COMSPEC_ATAK_JpcOffset [x, y, z] (repère spine3 :
    x à droite, y devant, z en haut), COMSPEC_ATAK_JpcTilt (degrés : écran ouvert incliné vers le visage),
    COMSPEC_ATAK_JpcRate (images par seconde de l'écran vivant, 6 par défaut).
*/
params [["_act", "tick"]];
private _cls = "COMSPEC_ATAK_JpcTablet";
if (_act isEqualTo "sweep") exitWith {
    { if ((_x getVariable ["COMSPEC_ATAK_Jpc", false]) && {isNull attachedTo _x}) then { deleteVehicle _x; }; } forEach (allSimpleObjects [_cls]);
    true
};
if (!hasInterface) exitWith { false };
private _d = "\z\comspec_atak_native\addons\main\data\";
private _off = "#(argb,8,8,3)color(0,0,0,1)";
private _style = profileNamespace getVariable ["COMSPEC_ATAK_CarryStyle", "hud"];
private _usable = { alive player && {vehicle player isEqualTo player} && {vest player isNotEqualTo ""} && {[player] call comspec_atak_native_fnc_hasDevice} };
private _isOpen = {
    private _disp = [] call comspec_atak_native_fnc_display;
    !isNull _disp && {(uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["texture", false]}
};
// Pose : charnière (origine du modèle, bas de la tablette côté dos) sur le haut du rabat du JPC.
// Modèle : vectorDir (+Z du P3D) = dos de la tablette ; l'écran regarde à l'opposé.
private _pose = {
    params ["_obj", "_open"];
    private _o = missionNamespace getVariable ["COMSPEC_ATAK_JpcOffset", [0, 0.24, -0.16]];
    _obj attachTo [player, _o, "spine3", true];
    if (_open) then {
        // Relevée : écran vers le visage, incliné de _t degrés vers l'arrière (haut de la tablette vers l'avant).
        private _t = missionNamespace getVariable ["COMSPEC_ATAK_JpcTilt", 50];
        _obj setVectorDirAndUp [[0, cos _t, -sin _t], [0, sin _t, cos _t]];
    } else {
        // Rabattue : pend sous la charnière, écran contre le rabat, dos vers l'extérieur.
        _obj setVectorDirAndUp [[0, 1, 0], [0, 0, -1]];
    };
};
private _ensure = {
    private _cur = missionNamespace getVariable ["COMSPEC_ATAK_JpcObj", objNull];
    if (isNull _cur || {(attachedTo _cur) isNotEqualTo player}) then {
        if (!isNull _cur) then { deleteVehicle _cur; };
        _cur = createSimpleObject [_cls, getPosASL player, false];
        _cur setVariable ["COMSPEC_ATAK_Jpc", true, true];
        _cur setObjectTextureGlobal [1, _off];
        [_cur, false] call _pose;
        missionNamespace setVariable ["COMSPEC_ATAK_JpcObj", _cur];
    };
    _cur
};
private _remove = {
    private _cur = missionNamespace getVariable ["COMSPEC_ATAK_JpcObj", objNull];
    if (!isNull _cur) then { deleteVehicle _cur; };
    missionNamespace setVariable ["COMSPEC_ATAK_JpcObj", objNull];
};

switch (_act) do {
    case "active": { _style isEqualTo "jpc" && _usable };
    case "open": {
        if !(_style isEqualTo "jpc" && _usable) exitWith { false };
        private _obj = call _ensure;
        [_obj, true] call _pose;
        // Écran vu par les autres : capture ATAK figée ; écran du porteur : l'interface vivante.
        // Nom unique à chaque ouverture : le display précédent (et ses contrôles) n'est jamais réutilisé.
        private _n = (missionNamespace getVariable ["COMSPEC_ATAK_JpcSeq", 0]) + 1;
        missionNamespace setVariable ["COMSPEC_ATAK_JpcSeq", _n];
        _obj setObjectTextureGlobal [1, _d + "jpc_screen_static_co.paa"];
        private _mat = _d + (["jpc_screen_night.rvmat", "jpc_screen.rvmat"] select (sunOrMoon > 0.5));
        missionNamespace setVariable ["COMSPEC_ATAK_JpcMat", _mat];
        _obj setObjectMaterialGlobal [1, _mat];
        _obj setObjectTexture [1, format ["#(rgb,1024,576,1)ui(""COMSPEC_RscATAKTexture"",""comspec_atak_jpc_%1"")", _n]];
        // Rafraîchissement de l'écran (displayUpdate) : seulement s'il peut être vu (à l'écran, pas trop loin).
        if ((missionNamespace getVariable ["COMSPEC_ATAK_JpcPfh", -1]) < 0) then {
            missionNamespace setVariable ["COMSPEC_ATAK_JpcPfh", [{
                private _disp = [] call comspec_atak_native_fnc_display;
                private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
                if (isNull _disp || {!(_s getOrDefault ["texture", false])}) exitWith {
                    [_this select 1] call CBA_fnc_removePerFrameHandler;
                    missionNamespace setVariable ["COMSPEC_ATAK_JpcPfh", -1];
                };
                private _rate = (missionNamespace getVariable ["COMSPEC_ATAK_JpcRate", 6]) max 1 min 30;
                if (diag_tickTime < (missionNamespace getVariable ["COMSPEC_ATAK_JpcNext", 0])) exitWith {};
                missionNamespace setVariable ["COMSPEC_ATAK_JpcNext", diag_tickTime + 1 / _rate];
                private _obj = missionNamespace getVariable ["COMSPEC_ATAK_JpcObj", objNull];
                if (isNull _obj || {(worldToScreen (ASLToAGL getPosASL _obj)) isEqualTo []} || {(positionCameraToWorld [0, 0, 0]) distance _obj > 6}) exitWith {};
                displayUpdate _disp;
            }, 0] call CBA_fnc_addPerFrameHandler];
        };
        true
    };
    case "close": {
        private _disp = [] call comspec_atak_native_fnc_display;
        private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
        if (!isNull _disp && {_s getOrDefault ["texture", false]}) then {
            // Nettoyage immédiat (le moteur ne libère le display qu'une fois la texture abandonnée).
            [_disp, 2] call comspec_atak_native_fnc_displayUnload;
            _disp closeDisplay 2;
        };
        private _obj = missionNamespace getVariable ["COMSPEC_ATAK_JpcObj", objNull];
        if (!isNull _obj) then {
            _obj setObjectTextureGlobal [1, _off];
            [_obj, false] call _pose;
        };
        true
    };
    case "toggle": {
        if (call _isOpen) exitWith {
            uiNamespace setVariable ["COMSPEC_ATAK_HudWanted", false];
            [] call comspec_atak_native_fnc_close;
            false
        };
        if (_style isNotEqualTo "jpc") then {
            profileNamespace setVariable ["COMSPEC_ATAK_CarryStyle", "jpc"];
            saveProfileNamespace;
        };
        if !(call _usable) exitWith {
            ["INFO", "Tablette sur le JPC : il faut un gilet et un terminal ATAK, à pied", 4, 20] call comspec_atak_native_fnc_notify;
            false
        };
        // Miniature ou terminal en main : on le range d'abord, puis on l'ouvre sur le JPC.
        private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
        _s set ["suppressHudRestore", true];
        [] call comspec_atak_native_fnc_close;
        _s set ["suppressHudRestore", false];
        uiNamespace setVariable ["COMSPEC_ATAK_HudWanted", true];
        [{ [false] call comspec_atak_native_fnc_open; }] call CBA_fnc_execNextFrame;
        true
    };
    default {
        // tick : tablette présente seulement avec le style JPC, utilisable ; écran ouvert mais plus utilisable
        // (véhicule, gilet retiré) : retour à la miniature.
        if (call _isOpen && {!(call _usable) || {_style isNotEqualTo "jpc"}}) then {
            private _wanted = uiNamespace getVariable ["COMSPEC_ATAK_HudWanted", false];
            [] call comspec_atak_native_fnc_close;
            if (_wanted && {alive player}) then { [{ [false] call comspec_atak_native_fnc_open; }] call CBA_fnc_execNextFrame; };
        };
        if !(_style isEqualTo "jpc" && _usable) exitWith { call _remove; false };
        private _obj = call _ensure;
        // Style JPC choisi pendant que la miniature est affichée : elle passe sur la tablette.
        private _disp = [] call comspec_atak_native_fnc_display;
        private _st = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
        if (!isNull _disp && {!(_st getOrDefault ["interactive", false])} && {!(_st getOrDefault ["texture", false])}) then {
            [] call comspec_atak_native_fnc_close;
            [{ [false] call comspec_atak_native_fnc_open; }] call CBA_fnc_execNextFrame;
        };
        // Luminosité de l'écran ouvert : jour / nuit.
        if (call _isOpen) then {
            private _mat = _d + (["jpc_screen_night.rvmat", "jpc_screen.rvmat"] select (sunOrMoon > 0.5));
            if ((missionNamespace getVariable ["COMSPEC_ATAK_JpcMat", ""]) isNotEqualTo _mat) then {
                missionNamespace setVariable ["COMSPEC_ATAK_JpcMat", _mat];
                _obj setObjectMaterialGlobal [1, _mat];
            };
        };
        true
    };
};
