// SSE : contrôle d'identité d'une IA de jour et de nuit, bulle, document et photo ; éditeur ; écran numérique d'une preuve.
if !("sse" call AT_fnc_groupe) exitWith {};
AT_fnc_controle = {
    params ["_u", "_doc", "_tag"];
    missionNamespace setVariable ["comspec_sse_idLastAnswer", nil];
    ["send", ["ask", [player, _u]]] call comspec_sse_fnc_idClient;
    private _rep = { ((missionNamespace getVariable ["comspec_sse_idLastAnswer", [objNull]]) select 0) isEqualTo _u };
    [[_rep, 15] call AT_fnc_wait, "pas de réponse à « Papiers s'il vous plaît »"] call AT_fnc_check;
    (format ["réponse : %1", (missionNamespace getVariable ["comspec_sse_idLastAnswer", []]) select [1, 2]]) call AT_fnc_info;
    [format ["papiers_reponse_%1", _tag]] call AT_fnc_shot;
    // Réponse naturelle tirée au sort (profil de l'IA) : si elle ne présente pas, on la rend pour voir le document.
    if (((missionNamespace getVariable ["comspec_sse_idLastAnswer", []]) param [1, ""]) isNotEqualTo "present") then {
        _u setVariable ["ace_captives_isSurrendering", true, true];
        uiSleep 1;
        missionNamespace setVariable ["comspec_sse_idLastAnswer", nil];
        ["send", ["ask", [player, _u]]] call comspec_sse_fnc_idClient;
        [_rep, 15] call AT_fnc_wait;
    };
    uiSleep 1;
    // Pièce réellement présentée : celle du jeton, sinon celle qu'on lui a délivrée.
    private _jetons = missionNamespace getVariable ["comspec_sse_idTokens", createHashMap];
    private _tendus = (keys _jetons) select { ((_jetons get _x) select 0) isEqualTo _u };
    private _h = _tendus param [0, _doc];
    ["send", ["view", [player, _h]]] call comspec_sse_fnc_idClient;
    private _v = { !isNull (uiNamespace getVariable ["comspec_sse_idViewerDisplay", displayNull]) };
    [[_v, 8] call AT_fnc_wait, format ["visionneuse du document %1 non ouverte", _h]] call AT_fnc_check;
    uiSleep 2; // photo en direct (PiP)
    [format ["document_%1", _tag]] call AT_fnc_shot;
    if (call _v) then {
        ["shoot"] call comspec_sse_fnc_idPhoto;
        uiSleep 2;
        [format ["document_photo_%1", _tag]] call AT_fnc_shot;
    };
    closeDialog 0;
    uiSleep 1;
};
AT_fnc_ia = {
    private _g = createGroup civilian;
    private _u = _g createUnit ["C_man_1", player getPos [2.5, getDir player], [], 0, "CAN_COLLIDE"];
    _u setDir ((getDir player) + 180);
    _u disableAI "PATH";
    _u allowDamage false;
    _u
};

["sse_controle_jour_nuit", {
    if (["comspec_sse_fnc_idClient", "comspec_sse_fnc_idIssue", "comspec_sse_fnc_idPhoto"] call AT_fnc_nil) exitWith {};
    private _date0 = date;
    private _u = call AT_fnc_ia;
    uiSleep 2;
    private _doc = [_u, createHashMapFromArray [["type", "id_card"], ["materialize", true]]] call comspec_sse_fnc_idIssue;
    if !([_doc isNotEqualTo "", "document de l'IA non délivré (fn_idIssue)"] call AT_fnc_check) exitWith { deleteVehicle _u; call AT_fnc_done; };
    // Bulle au-dessus de la tête (fn_aiBubble) : vue si l'IA est en ligne de vue.
    if (isNil "comspec_atak_native_fnc_aiBubble") then {
        "fn_aiBubble absente de ce build" call AT_fnc_info;
    } else {
        [["say", [_u, "Papiers : voici ma carte.", name _u, "INFO"]] call comspec_atak_native_fnc_aiBubble, "bulle non affichée, IA en ligne de vue à 2,5 m"] call AT_fnc_check;
        uiSleep 0.5;
        ["bulle_tete"] call AT_fnc_shot;
    };
    setDate [_date0 select 0, _date0 select 1, _date0 select 2, 12, 0];
    uiSleep 1;
    [_u, _doc, "jour"] call AT_fnc_controle;
    [["IDCHECK", 2] call AT_fnc_page, "app Contrôle ID non ouverte"] call AT_fnc_check;
    ["controle_id_app"] call AT_fnc_shot;
    call AT_fnc_nettoie;
    uiSleep 1;
    setDate [_date0 select 0, _date0 select 1, _date0 select 2, 23, 30];
    uiSleep 1;
    ["nuit_scene"] call AT_fnc_shot;
    [_u, _doc, "nuit"] call AT_fnc_controle;
    setDate _date0;
    "document et photo lisibles de nuit (visage non noir)" call AT_fnc_visuel;
    deleteVehicle _u;
    call AT_fnc_done;
}, 150] call AT_fnc_run;

["sse_editeur_5_types", {
    if (["comspec_sse_fnc_editor"] call AT_fnc_nil) exitWith {};
    ["open", [getPosATL player, objNull]] call comspec_sse_fnc_editor;
    private _ouvert = { !isNull (uiNamespace getVariable ["comspec_sse_editorDisplay", displayNull]) };
    if !([[_ouvert, 6] call AT_fnc_wait, "éditeur non ouvert"] call AT_fnc_check) exitWith { call AT_fnc_done; };
    uiSleep 1;
    ["editeur_guide"] call AT_fnc_shot;
    for "_i" from 1 to 6 do { ["guideNext"] call comspec_sse_fnc_editor; uiSleep 0.2; };
    {
        _x params ["_i", "_lib"];
        ["type", _i] call comspec_sse_fnc_editor;
        uiSleep 0.8;
        [format ["editeur_type_%1", _lib]] call AT_fnc_shot;
    } forEach [[0, "renseignement"], [1, "identite"], [2, "papier"], [3, "dossier"], [4, "kit"]];
    ["toggleLibrary"] call comspec_sse_fnc_editor;
    uiSleep 1;
    ["editeur_bibliotheque"] call AT_fnc_shot;
    ["close"] call comspec_sse_fnc_editor;
    uiSleep 0.5;
    [!(call _ouvert), "éditeur non fermé"] call AT_fnc_check;
    call AT_fnc_done;
}, 60] call AT_fnc_run;

["sse_preuve_numerique", {
    if (["comspec_sse_fnc_generateData", "comspec_sse_fnc_revealDigitalFog", "comspec_sse_fnc_openDigitalPanel"] call AT_fnc_nil) exitWith {};
    private _o = createVehicle ["Land_MobilePhone_smart_F", player getPos [1.5, getDir player], [], 0, "CAN_COLLIDE"];
    _o setVariable ["comspec_sse_forcedType", "PHONE", true];
    [_o, "INSURGENT", "DETAILED", "AUTOTEST"] call comspec_sse_fnc_generateData;
    uiSleep 1;
    private _fog = [_o, "full", 78] call comspec_sse_fnc_revealDigitalFog;
    [_fog isEqualType createHashMap && {(count _fog) > 0} && {_fog getOrDefault ["ok", true]}, "aucune donnée numérique révélée"] call AT_fnc_check;
    [_fog, _o] call comspec_sse_fnc_openDigitalPanel;
    [[{ dialog }, 6] call AT_fnc_wait, "écran numérique de la preuve non ouvert"] call AT_fnc_check;
    uiSleep 1.5;
    ["preuve_numerique"] call AT_fnc_shot;
    closeDialog 0;
    deleteVehicle _o;
    call AT_fnc_done;
}, 45] call AT_fnc_run;
