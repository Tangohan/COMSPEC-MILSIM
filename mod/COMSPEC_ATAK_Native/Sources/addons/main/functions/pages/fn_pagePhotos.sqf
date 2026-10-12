/*
    App Photos, trois onglets (clé photoTab de l'état), chacun avec une ligne d'explication et l'état de l'envoi :
      APPAREIL (CAMERA) : viseur et déclencheur (mode photo plein écran, fn_photoMode), légende, photo rapide
                          (fn_photoTake), réglage du flash, compteurs d'envoi ;
      GALERIE  (LIB)    : photos du téléphone (dossier COMSPEC et captures Arma, fn_photoLibrary) et clichés du drone
                          (profil COMSPEC_ATAK_DronePhotos, fn_dronePhoto) avec titre, SSE / confidentiel et état d'envoi ;
                          filtre TOUT / TÉLÉPHONE / DRONE (clé photoFilter). DÉTAILS ouvre la fiche d'une photo
                          (clé photoSel = [type, nom en minuscules]) : envoi vers Athena, suppression (second appui) et,
                          pour un cliché du drone, titre, description, SSE et confidentiel modifiables ;
      ENVOIS   (SENT)   : compteurs, dernier retour d'Athena, clichés du drone en cours ou refusés, historique.
    Les fichiers des clichés du drone sont aussi dans le dossier Screenshots : ils ne sont montrés qu'une fois, en DRONE.
    Boutons groupés par rubrique (ligne "bar" de fn_formRender) : choix en cours en vert, aide en infobulle.
*/
disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];
private _font = _l get "font";
private _fs = _l get "fontSmall";
private _pad = (_l get "pad") * 2;
private _esc = { params ["_t"]; if !(_t isEqualType "") then { _t = str _t; }; [[_t, "<", "&lt;"] call CBA_fnc_replace, ">", "&gt;"] call CBA_fnc_replace };
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _tab = _s getOrDefault ["photoTab", "CAMERA"];
if !(_tab in ["CAMERA", "LIB", "SENT"]) then { _tab = "CAMERA"; };
private _bridge = [] call comspec_atak_native_fnc_bridge;
private _ready = missionNamespace getVariable ["COMSPEC_AthenaReady", false];
private _net = (([] call comspec_atak_native_fnc_linkQuality) getOrDefault ["bars", 0]) > 0;
private _redraw = { [{ ["PHOTOS"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame; };
// Code commun des boutons qui changent de vue : légende gardée, fiche du cliché drone enregistrée puis refermée.
private _leave = "private _st0 = uiNamespace getVariable ['COMSPEC_ATAK_State', createHashMap]; if ((['caption', '§'] call comspec_atak_native_fnc_formValue) isNotEqualTo '§') then { _st0 set ['photoCaption', ['caption', ''] call comspec_atak_native_fnc_formValue]; }; if (((_st0 getOrDefault ['photoSel', []]) param [0, '']) isEqualTo 'DRONE') then { ['save'] call comspec_atak_native_fnc_dronePhoto; _st0 set ['dronePhEdit', -1]; };";
private _go = { params ["_tabK", ["_sel", []]]; compile format ["%1 _st0 set ['photoTab', '%2']; _st0 set ['photoSel', %3]; if ('%2' isEqualTo 'LIB') then { ['list'] call comspec_atak_native_fnc_photoLibrary; }; [{ ['PHOTOS'] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;", _leave, _tabK, str _sel] };

// Photos du poste et clichés du drone.
if (_tab isEqualTo "LIB" && {isNil { uiNamespace getVariable "COMSPEC_ATAK_PhotoLibAt" }}) then { ["list"] call comspec_atak_native_fnc_photoLibrary; };
private _lib = uiNamespace getVariable ["COMSPEC_ATAK_PhotoLib", []];
private _drone = profileNamespace getVariable ["COMSPEC_ATAK_DronePhotos", []];
if !(_drone isEqualType []) then { _drone = []; };
private _dFiles = _drone apply { if (_x isEqualType [] && {(_x param [0, ""]) isEqualType ""}) then { toLower (_x select 0) } else { "" } };
private _onDisk = _lib apply { toLower (_x select 1) };
private _phone = [];
{ if !((toLower (_x select 1)) in _dFiles) then { _phone pushBack [_forEachIndex, _x]; }; } forEach _lib;
private _droneList = [];
for "_i" from ((count _drone) - 1) to 0 step -1 do { if ((_dFiles select _i) isNotEqualTo "") then { _droneList pushBack [_i, _drone select _i]; }; };

// État d'envoi d'une photo du téléphone : [texte, couleur, déjà reçue par Athena].
private _sent = ((missionNamespace getVariable ["COMSPEC_Athena_PhotoUploaded", []]) select { _x isEqualType "" }) apply { toLower _x };
private _bad = ((missionNamespace getVariable ["COMSPEC_Athena_PhotoFailed", []]) + (profileNamespace getVariable ["COMSPEC_Athena_PhotoDead", []])) select { _x isEqualType "" } apply { toLower _x };
private _wait = (missionNamespace getVariable ["COMSPEC_Athena_PhotoPending", []]) apply { toLower str _x };
private _known = uiNamespace getVariable "COMSPEC_ATAK_PhotoKnown";
private _phoneSt = {
    params ["_path", "_name"];
    private _k = [toLower _path, toLower _name];
    switch (true) do {
        case ((_k findIf { _x in _sent }) >= 0): { ["REÇUE PAR ATHENA", "#5cc76b", true] };
        case (!isNil "_known" && {(_k select 1) in _known}): { ["SUR ATHENA", "#5cc76b", true] };
        case ((_wait findIf { ((_x find (_k select 1)) >= 0) }) >= 0): { ["ENVOI EN COURS", "#4da3ff", false] };
        case ((_k findIf { _x in _bad }) >= 0): { ["ENVOI REFUSÉ", "#e5483a", false] };
        default { ["PAS ENCORE ENVOYÉE", "#8a9a93", false] };
    }
};
private _dSt = createHashMapFromArray [["DRAFT", ["FICHE À COMPLÉTER", "#f2ab33"]], ["LOCAL", ["PAS ENCORE ENVOYÉ", "#8a9a93"]], ["SENDING", ["ENVOI EN COURS", "#4da3ff"]], ["SENT", ["ENVOYÉ À ATHENA", "#5cc76b"]], ["FAILED", ["ENVOI REFUSÉ", "#e5483a"]]];
private _tags = {
    params ["_sse", "_cf", ["_case", ""]];
    format ["%1%2", ["", format ["  <t size='0.75' color='#f2ab33'>%1</t>", ["SSE", format ["SSE %1", [_case] call _esc]] select (_case isNotEqualTo "")]] select _sse, ["", "  <t size='0.75' color='#e5483a'>CONFIDENTIEL</t>"] select _cf]
};
private _newN = { !(((_x select 1) call _phoneSt) select 2) } count _phone;
private _armed = uiNamespace getVariable ["COMSPEC_ATAK_PhotoDelArm", ["", -10]];
private _isArmed = { params ["_k"]; (_armed select 0) isEqualTo _k && {diag_tickTime - (_armed select 1) <= 4} };
private _pending = count (missionNamespace getVariable ["COMSPEC_Athena_PhotoPending", []]);

// En-tête : onglets, ce qu'on y fait, et où partent les photos.
private _total = (count _phone) + (count _droneList);
private _head = [
    ["segment", "", [
        ["APPAREIL", ["CAMERA"] call _go, _tab isEqualTo "CAMERA", "Prendre une photo avec le téléphone"],
        [format ["GALERIE (%1)", _total], ["LIB"] call _go, _tab isEqualTo "LIB", "Revoir, envoyer ou supprimer les photos gardées sur le téléphone et les clichés du drone"],
        [["ENVOIS", format ["ENVOIS (%1)", _pending]] select (_pending > 0), ["SENT"] call _go, _tab isEqualTo "SENT", "Suivre la réception des photos par Athena"]
    ]],
    ["text", format ["<t size='0.78' color='#8a9a93'>%1</t><br/><t size='0.78'>%2</t>", (createHashMapFromArray [
        ["CAMERA", "Touchez le viseur pour photographier en plein écran, ou PHOTO RAPIDE pour saisir tout de suite ce que vous voyez."],
        ["LIB", "Photos gardées sur ce téléphone et clichés du drone. DÉTAILS : voir la fiche, envoyer à Athena ou supprimer."],
        ["SENT", "Photos en route vers Athena (ATAK web), reçues ou refusées, et l'historique de la session."]
    ]) get _tab, switch (true) do {
        case (!_bridge): { "<t color='#e5483a'>● COMSPEC Overwatch absent : ni prise de vue ni envoi possibles</t>" };
        case (!_ready): { "<t color='#f2ab33'>● Athena non connecté : connectez-vous dans l'app Athena pour prendre et envoyer des photos</t>" };
        case (!_net): { "<t color='#f2ab33'>● Hors réseau : les photos restent sur le téléphone, à envoyer plus tard depuis la GALERIE</t>" };
        default { "<t color='#5cc76b'>● Prêt : les photos partent directement vers Athena</t>" };
    }]]
];

// Dernier retour d'Athena (onglets APPAREIL et ENVOIS).
private _lastRow = ["text", (missionNamespace getVariable ["COMSPEC_LastReconUploadResult", []]) call {
    params [["_st", ""], ["_msg", ""], ["_tech", ""], ["_at", ""]];
    switch (_st) do {
        case "OK": { format ["<t color='#5cc76b'>● %1 · dernière photo reçue par Athena</t>  <t size='0.8' color='#8a9a93'>%2</t>", _at, [_tech] call _esc] };
        case "ERR": { format ["<t color='#e5483a'>● %1 · dernière photo refusée : %2</t><br/><t size='0.8' color='#8a9a93'>%3</t>", _at, [_msg] call _esc, [_tech] call _esc] };
        default { "<t color='#8a9a93'>Aucun retour d'Athena pour l'instant.</t>" };
    }
}];

// Ligne de la galerie pour un cliché du drone et pour une photo du téléphone.
private _droneRow = {
    params ["_i", "_e"];
    _e params ["_file", ["_title", ""], "", ["_sse", false], ["_cf", false], ["_grid", ""], ["_clock", ""], ["_dn", ""], ["_state", "LOCAL"], ["_case", ""], "", "", ["_date", ""]];
    private _st = _dSt getOrDefault [_state, [_state, "#8a9a93"]];
    private _open = ["LIB", ["DRONE", toLower _file]] call _go;
    ["person", "\z\comspec_atak_native\addons\main\data\drone_quad.paa",
        format ["<t font='RobotoCondensedBold'>%1</t>%2<br/><t size='0.8'><t color='%4'>● %3</t>  <t color='#8a9a93'>%5 %6 · %7</t></t>",
            [[_title] call _esc, "Cliché drone sans titre"] select (_title isEqualTo ""), [_sse, _cf, _case] call _tags, _st select 0, _st select 1, _date, _clock, _grid],
        [["DÉTAILS", _open, false, true, "Fiche du cliché : position du drone, titre, description, SSE, confidentiel ; envoyer ou supprimer"],
         if (_state isEqualTo "DRAFT") then {
            ["COMPLÉTER", _open, true, true, "Donner un titre et une description avant l'envoi"]
         } else {
            [["ENVOYER", "RENVOYER"] select (_state in ["SENT", "FAILED"]), compile format ["['send', %1] call comspec_atak_native_fnc_dronePhoto;", _i], _state in ["LOCAL", "FAILED"], _state isNotEqualTo "SENDING", "Envoyer ce cliché vers Athena avec son titre, sa description et ses marquages"]
         }],
        [0.36, 0.78, 0.42, 1]]
};
private _phoneRow = {
    params ["_j", "_e"];
    _e params ["_path", "_name"];
    private _st = [_path, _name] call _phoneSt;
    ["person", "\z\comspec_atak_native\addons\main\data\ui_gallery.paa",
        format ["<t font='RobotoCondensedBold'>%1</t><br/><t size='0.8' color='%3'>● %2</t>", [_name] call _esc, _st select 0, _st select 1],
        [["DÉTAILS", ["LIB", ["PHONE", toLower _name]] call _go, false, true, "Fiche de la photo : emplacement, état d'envoi ; envoyer ou supprimer"],
         [["ENVOYER", "RENVOYER"] select ((_st select 2) || {(_st select 0) isEqualTo "ENVOI REFUSÉ"}), compile format ["['send', %1] call comspec_atak_native_fnc_photoLibrary;", _j], !(_st select 2), true, "Envoyer cette photo vers Athena (ATAK web)"]],
        [0.90, 0.94, 0.91, 1]]
};

// ---------------------------------------------------------------- GALERIE
if (_tab isEqualTo "LIB") exitWith {
    private _rows = +_head;
    private _sel = _s getOrDefault ["photoSel", []];
    if !(_sel isEqualType []) then { _sel = []; };
    _sel params [["_kind", ""], ["_key", ""]];
    private _di = if (_kind isEqualTo "DRONE") then { _dFiles find _key } else { -1 };
    private _pi = if (_kind isEqualTo "PHONE") then { _phone findIf { (toLower ((_x select 1) select 1)) isEqualTo _key } } else { -1 };
    // Photo disparue (supprimée, liste relue) : retour à la liste.
    if (_kind isNotEqualTo "" && {_di < 0} && {_pi < 0}) then {
        _s set ["photoSel", []];
        if (_kind isEqualTo "DRONE") then { _s set ["dronePhEdit", -1]; };
        _kind = "";
    };
    private _back = ["bar", [["", [["RETOUR À LA GALERIE", ["LIB"] call _go, "", "Revenir à la liste des photos (la fiche est enregistrée)"]]]]];
    switch (true) do {
        case (_di >= 0): {
            // Fiche d'un cliché du drone : celle de l'app Drone (fn_dronePhoto lit dronePhEdit et les champs dronePh*).
            _s set ["dronePhEdit", _di];
            (_drone select _di) params ["_file", ["_title", ""], ["_desc", ""], ["_sse", false], ["_cf", false], ["_grid", ""], ["_clock", ""], ["_dn", ""], ["_state", "LOCAL"], ["_case", ""], ["_alt", 0], ["_hdg", 0], ["_date", ""]];
            private _st = _dSt getOrDefault [_state, [_state, "#8a9a93"]];
            private _active = missionNamespace getVariable ["COMSPEC_SSE_ActiveCase", ""];
            if !(_active isEqualType "") then { _active = ""; };
            private _dc = _s getOrDefault ["dronePhDel", [-1, -10]];
            private _del = (_dc select 0) isEqualTo _di && {diag_tickTime - (_dc select 1) < 4};
            private _busy = _state isEqualTo "SENDING";
            _rows append [
                _back,
                ["section", [_title, "Cliché drone sans titre"] select (_title isEqualTo ""), "Pris avec la caméra du drone (app Drone)"],
                ["text", format ["<t color='%2'>● %1</t>%3   <t size='0.8' color='#8a9a93'>%4</t>", _st select 0, _st select 1, [_sse, _cf, _case] call _tags,
                    ["", ["fichier introuvable sur le téléphone", "fichier sur le téléphone"] select ((toLower _file) in _onDisk)] select _bridge]],
                ["info", "Pris le", format ["%1 %2", _date, _clock]],
                ["info", "Drone", [_dn] call _esc],
                ["info", "Position du drone", format ["%1 · %2 m sol · cap %3°", _grid, _alt, _hdg]],
                ["info", "Fichier", format ["<t size='0.8'>%1</t>", [_file] call _esc]],
                ["section", "Fiche", "Titre, description et marquages partent avec la photo vers Athena"],
                ["edit", "dronePhTitle", "Titre (60 caractères)", _title],
                ["memo", "dronePhDesc", "Description", _desc, 3],
                ["switch", "SSE (exploitation de site)", _sse, { ["flag", "sse"] call comspec_atak_native_fnc_dronePhoto; },
                    [format ["Rattaché au dossier %1", [_active, _case] select (_sse && {_case isNotEqualTo ""})], "Aucun dossier SSE actif (app SSE) : marqué SSE sans dossier"] select (([_active, _case] select _sse) isEqualTo "")],
                ["switch", "Confidentiel", _cf, { ["flag", "conf"] call comspec_atak_native_fnc_dronePhoto; }, "Marqué [CONFIDENTIEL] en tête de la légende sur Athena"],
                ["text", format ["<t size='0.8' color='#8a9a93'>Légende envoyée : %1</t>", [["caption", _drone select _di] call comspec_atak_native_fnc_dronePhoto] call _esc]],
                ["bar", [
                    ["Fiche", [["ENREGISTRER", { ["save"] call comspec_atak_native_fnc_dronePhoto; ["SUCCESS", "Fiche du cliché enregistrée sur le téléphone", 3, 20] call comspec_atak_native_fnc_notify; [{ ["PHOTOS"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame; }, "", "Garder le titre et la description sur le téléphone, sans rien envoyer"]]],
                    ["Athena", [[["ENVOYER À ATHENA", "RENVOYER À ATHENA"] select (_state in ["SENT", "FAILED"]), compile format ["['send', %1] call comspec_atak_native_fnc_dronePhoto;", _di], "main", "Envoyer le cliché avec sa fiche vers Athena (ATAK web), au débit du réseau du téléphone", !_busy]]],
                    ["Téléphone", [[["SUPPRIMER", "CONFIRMER"] select _del,
                        compile format ["private _n = count (profileNamespace getVariable ['COMSPEC_ATAK_DronePhotos', []]); ['forget', %1] call comspec_atak_native_fnc_dronePhoto; if ((count (profileNamespace getVariable ['COMSPEC_ATAK_DronePhotos', []])) < _n) then { ['list'] call comspec_atak_native_fnc_photoLibrary; };", _di],
                        ["", "red"] select _del, "Effacer ce cliché et son fichier du téléphone (second appui pour confirmer). Une photo déjà reçue reste sur Athena.", !_busy]]]
                ]]
            ];
        };
        case (_pi >= 0): {
            (_phone select _pi) params ["_j", "_e"];
            _e params ["_path", "_name"];
            private _st = [_path, _name] call _phoneSt;
            private _vps = if (isNil "_known") then { "Athena n'a pas répondu (ACTUALISER pour redemander)" } else { ["pas encore visible sur Athena", "visible sur Athena, onglet Photos"] select ((toLower _name) in _known) };
            private _del = [str _j] call _isArmed;
            _rows append [
                _back,
                ["section", [_name] call _esc, "Photo prise avec le téléphone, ou capture d'écran Arma"],
                ["text", format ["<t color='%2'>● %1</t>   <t size='0.8' color='#8a9a93'>%3</t>", _st select 0, _st select 1, _vps]],
                ["info", "Fichier", format ["<t size='0.8'>%1</t>", [_name] call _esc]],
                ["info", "Emplacement", format ["<t size='0.75'>%1</t>", [_path] call _esc]],
                ["text", "<t size='0.8' color='#8a9a93'>Le téléphone ne peut pas afficher l'image : elle s'ouvre sur Athena (ATAK web), onglet Photos, une fois reçue.</t>"],
                ["bar", [
                    ["Athena", [[["ENVOYER À ATHENA", "RENVOYER À ATHENA"] select ((_st select 2) || {(_st select 0) isEqualTo "ENVOI REFUSÉ"}), compile format ["['send', %1] call comspec_atak_native_fnc_photoLibrary;", _j], "main", "Envoyer cette photo vers Athena (ATAK web), au débit du réseau du téléphone"]]],
                    ["Téléphone", [[["SUPPRIMER", "CONFIRMER"] select _del, compile format ["['delete', %1] call comspec_atak_native_fnc_photoLibrary;", _j], ["", "red"] select _del, "Effacer le fichier du téléphone (second appui pour confirmer). Une photo déjà reçue reste sur Athena."]]]
                ]]
            ];
        };
        default {
            private _flt = _s getOrDefault ["photoFilter", "ALL"];
            private _fBtn = { params ["_t", "_k", "_tip"]; [_t, compile format ["(uiNamespace getVariable ['COMSPEC_ATAK_State', createHashMap]) set ['photoFilter', '%1']; [{ ['PHOTOS'] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;", _k], _flt isEqualTo _k, _tip] };
            _rows append [
                ["segment", "Afficher", [
                    [format ["TOUT (%1)", _total], "ALL", "Photos du téléphone et clichés du drone"] call _fBtn,
                    [format ["TÉLÉPHONE (%1)", count _phone], "PHONE", "Photos prises avec le téléphone et captures d'écran"] call _fBtn,
                    [format ["DRONE (%1)", count _droneList], "DRONE", "Clichés pris avec la caméra du drone"] call _fBtn
                ]],
                ["bar", [
                    ["Galerie", [["ACTUALISER", { ["list"] call comspec_atak_native_fnc_photoLibrary; [{ ["PHOTOS"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame; }, "", "Relire les photos du téléphone et demander à Athena lesquelles elle a déjà", _bridge]]],
                    ["Envoi vers Athena", [[format ["ENVOYER LES NOUVELLES (%1)", _newN], { ["sendNew"] call comspec_atak_native_fnc_photoLibrary; }, ["", "main"] select (_newN > 0), "Envoyer les photos du téléphone qu'Athena n'a pas encore (une toutes les 2 s). Les clichés du drone partent avec leur fiche.", _bridge && {_newN > 0}]]],
                    ["Nettoyage", [[["TOUT SUPPRIMER", "CONFIRMER"] select (["ALL"] call _isArmed), { ["deleteAll"] call comspec_atak_native_fnc_photoLibrary; }, ["", "red"] select (["ALL"] call _isArmed), "Effacer du téléphone toutes ses photos (second appui pour confirmer). Les clichés du drone se suppriment un par un, depuis leur fiche.", _bridge && {(count _phone) > 0}]]]
                ]]
            ];
            if (_flt in ["ALL", "DRONE"]) then {
                _rows pushBack ["section", format ["Clichés du drone : %1", count _droneList], "Pris avec l'app Drone ; titre, description, SSE et confidentiel partent avec la photo"];
                if ((count _droneList) isEqualTo 0) then { _rows pushBack ["text", "<t color='#8a9a93'>Aucun cliché du drone. App Drone, caméra ouverte : bouton PHOTO sous l'image.</t>"]; };
                { _rows pushBack (_x call _droneRow); } forEach (_droneList select [0, 20]);
            };
            if (_flt in ["ALL", "PHONE"]) then {
                _rows pushBack ["section", format ["Photos du téléphone : %1", count _phone], "Dossier COMSPEC et captures d'écran Arma, les plus récentes en premier"];
                if (!_bridge) then {
                    _rows pushBack ["text", "<t color='#e0a030'>Liste disponible avec la liaison Overwatch (mod COMSPEC Overwatch chargé).</t>"];
                } else {
                    if ((count _phone) isEqualTo 0) then { _rows pushBack ["text", "<t color='#8a9a93'>Aucune photo sur le téléphone. Onglet APPAREIL pour en prendre une.</t>"]; };
                };
                { _rows pushBack (_x call _phoneRow); } forEach (_phone select [0, 30]);
                if ((count _phone) > 30) then { _rows pushBack ["text", format ["<t size='0.8' color='#8a9a93'>… et %1 photo(s) plus anciennes.</t>", (count _phone) - 30]]; };
            };
        };
    };
    [_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
    true
};

// ---------------------------------------------------------------- ENVOIS
if (_tab isEqualTo "SENT") exitWith {
    private _lines = [];
    {
        _x params [["_kind", ""], ["_title", ""], ["_text", ""], ["_grid", ""], ["_time", ""]];
        if ((toUpper _kind) isEqualTo "PHOTO") then {
            _lines pushBack format ["<t color='#8a9a93'>%1</t>  %2<br/><t size='0.85'>%3</t>", [_time] call _esc, [_title] call _esc, [_text] call _esc];
        };
    } forEach (missionNamespace getVariable ["COMSPEC_Athena_AlertInbox", []]);
    reverse _lines;
    private _watch = _droneList select { ((_x select 1) param [8, ""]) in ["SENDING", "FAILED"] };
    private _rows = _head + [
        ["text", format ["<t size='1.3' font='RobotoCondensedBold' color='#e8b84a'>%1</t> <t size='0.8' color='#8a9a93'>en cours</t>      <t size='1.3' font='RobotoCondensedBold' color='#5cc76b'>%2</t> <t size='0.8' color='#8a9a93'>reçues</t>      <t size='1.3' font='RobotoCondensedBold' color='#e5483a'>%3</t> <t size='0.8' color='#8a9a93'>refusées</t>",
            _pending, count (missionNamespace getVariable ["COMSPEC_Athena_PhotoUploaded", []]), count (missionNamespace getVariable ["COMSPEC_Athena_PhotoFailed", []])]],
        _lastRow,
        ["bar", [
            ["Galerie", [["VOIR LA GALERIE", ["LIB"] call _go, "", "Toutes les photos du téléphone et les clichés du drone"]]],
            ["Envoi vers Athena", [[format ["ENVOYER LES NOUVELLES (%1)", _newN], { ["sendNew"] call comspec_atak_native_fnc_photoLibrary; }, ["", "main"] select (_newN > 0), "Envoyer les photos du téléphone qu'Athena n'a pas encore (une toutes les 2 s)", _bridge && {_newN > 0}]]]
        ]]
    ];
    if ((count _watch) > 0) then {
        _rows pushBack ["section", format ["Clichés du drone à suivre : %1", count _watch], "En cours d'envoi ou refusés par Athena"];
        { _rows pushBack (_x call _droneRow); } forEach (_watch select [0, 10]);
    };
    _rows pushBack ["section", "Historique", "Photos reçues par Athena pendant cette session, la plus récente en haut"];
    if ((count _lines) isEqualTo 0) then {
        _rows pushBack ["text", "<t color='#8a9a93'>Rien pour l'instant. Onglet APPAREIL pour prendre une photo.</t>"];
    } else {
        { _rows pushBack ["text", _x]; } forEach (_lines select [0, 15]);
    };
    [_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
    true
};

// ---------------------------------------------------------------- APPAREIL
// Façon appli photo : onglets et légende, viseur avec infos de prise de vue, gros déclencheur, compteurs d'envoi,
// puis réglages groupés (prise de vue, flash).
private _ratio = (uiNamespace getVariable ["COMSPEC_ATAK_Ratio", pixelH / pixelW]);
private _dir = "\z\comspec_atak_native\addons\main\data\";
private _mk = { params ["_c", "_p", ["_t", ""]]; [_c, _p, _t] call comspec_atak_native_fnc_pageCtrl };
private _open = { private _c = ["caption"] call comspec_atak_native_fnc_formValue; (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) set ["photoCaption", _c]; [{ ["ENTER", _this] call comspec_atak_native_fnc_photoMode; }, _c] call CBA_fnc_execNextFrame; };
private _canShoot = _bridge && {_ready};
private _flash = (uiNamespace getVariable ["COMSPEC_ATAK_PhotoSt", createHashMap]) getOrDefault ["flash", "AUTO"];

// Onglets et légende : formulaire ramené à la hauteur de son contenu.
private _top = [_head + [["edit", "caption", "Légende de la prochaine photo (facultatif)", _s getOrDefault ["photoCaption", ""]]], [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
private _topH = ((uiNamespace getVariable ["COMSPEC_ATAK_FormH", _font * 6]) min (_bh * 0.45));
{ _x ctrlSetPosition [0, 0, _bw, _topH]; _x ctrlCommit 0; } forEach [_top, _top getVariable ["bg", controlNull]];

// Viseur
private _vy = _topH + _pad / 2;
private _vh = (_bh * 0.27) max (_font * 5);
private _vx = _pad;
private _vw = _bw - _pad * 2;
private _vf = ["COMSPEC_RscText", [_vx, _vy, _vw, _vh]] call _mk;
_vf ctrlSetBackgroundColor [0.02, 0.025, 0.02, 1];
// Coins du cadre
private _cl = _vh * 0.14;
private _th = pixelH * 2;
private _tw = pixelW * 2;
{
    _x params ["_cx", "_cy", "_sx", "_sy"];
    private _h = ["COMSPEC_RscText", [[_cx, _cx - _cl * _ratio] select (_sx < 0), _cy, _cl * _ratio, _th]] call _mk;
    _h ctrlSetBackgroundColor [0.9, 0.95, 0.92, 0.85];
    private _v = ["COMSPEC_RscText", [_cx, [_cy, _cy - _cl] select (_sy < 0), _tw, _cl]] call _mk;
    _v ctrlSetBackgroundColor [0.9, 0.95, 0.92, 0.85];
} forEach [
    [_vx + _pad, _vy + _pad, 1, 1], [_vx + _vw - _pad - _tw, _vy + _pad, -1, 1],
    [_vx + _pad, _vy + _vh - _pad - _th, 1, -1], [_vx + _vw - _pad - _tw, _vy + _vh - _pad - _th, -1, -1]
];
// Réticule
private _rc = _vh * 0.08;
private _rh = ["COMSPEC_RscText", [_vx + _vw / 2 - _rc * _ratio, _vy + _vh * 0.4, _rc * 2 * _ratio, _th]] call _mk;
_rh ctrlSetBackgroundColor [0.36, 0.78, 0.42, 0.8];
private _rv = ["COMSPEC_RscText", [_vx + _vw / 2, _vy + _vh * 0.4 - _rc, _tw, _rc * 2]] call _mk;
_rv ctrlSetBackgroundColor [0.36, 0.78, 0.42, 0.8];
// Infos de prise de vue : grille, cap, heure
private _info = ["COMSPEC_RscStructuredText", [_vx + _pad * 2, _vy + _pad * 1.6, _vw - _pad * 4, _fs * 1.4]] call _mk;
_info ctrlSetStructuredText parseText format ["<t size='0.8' font='EtelkaMonospacePro' color='#c9d4cf'>%1</t><t size='0.8' align='right' font='EtelkaMonospacePro' color='#c9d4cf'>%2°  %3</t>",
    [getPosASL player, 8] call comspec_atak_native_fnc_gridRef, round getDir player, [dayTime, "HH:MM"] call BIS_fnc_timeToString];
// Ce que fait le viseur, puis les touches du mode photo.
// Juste au-dessus de la ligne des touches, jamais sur le réticule (évite le chevauchement en paysage).
private _tipY = _vy + _vh - _pad * 1.6 - _fs * 1.4;
private _call = ["COMSPEC_RscStructuredText", [_vx + _pad * 2, (_tipY - _fs * 1.6) max (_vy + _vh * 0.5), _vw - _pad * 4, _fs * 1.5]] call _mk;
_call ctrlSetStructuredText parseText format ["<t align='center' size='0.95' font='RobotoCondensedBold' color='%2'>%1</t>",
    ["CONNECTEZ-VOUS À ATHENA POUR PHOTOGRAPHIER", "TOUCHER POUR OUVRIR LE VISEUR"] select _canShoot, ["#f2ab33", "#eef4f0"] select _canShoot];
private _tip = ["COMSPEC_RscStructuredText", [_vx + _pad * 2, _tipY, _vw - _pad * 4, _fs * 1.4]] call _mk;
_tip ctrlSetStructuredText parseText "<t size='0.75' align='center' color='#a9b8b1'>Dans le viseur : clic photo · molette zoom · T marquer · F flash · R selfie · Espace retour</t>";
// Déclencheur sous le viseur, centré comme dans une appli photo : anneau blanc, disque d'accent, icône.
private _sh = (_font * 3.2) min (_vh * 0.45);
private _sw = _sh * _ratio;
private _scx = _vx + _vw / 2;
private _scy = _vy + _vh + _pad / 2 + _sh / 2;
private _ring = ["COMSPEC_RscPicture", [_scx - _sw / 2, _scy - _sh / 2, _sw, _sh], _dir + "ui_disc.paa"] call _mk;
_ring ctrlSetTextColor [0.95, 0.97, 0.96, 1];
private _inner = ["COMSPEC_RscPicture", [_scx - _sw * 0.41, _scy - _sh * 0.41, _sw * 0.82, _sh * 0.82], _dir + "ui_disc.paa"] call _mk;
([] call comspec_atak_native_fnc_accent) params ["_acc"];
_inner ctrlSetTextColor ([[0.45, 0.5, 0.47, 1], _acc] select _canShoot);
private _cam = ["COMSPEC_RscPicture", [_scx - _sw * 0.22, _scy - _sh * 0.22, _sw * 0.44, _sh * 0.44], _dir + "ui_photocam.paa"] call _mk;
_cam ctrlSetTextColor [0.05, 0.08, 0.06, 1];
// Libellés de part et d'autre du déclencheur.
private _lw = (_vw / 2) - _sw / 2 - _pad;
{
    _x params ["_lx", "_txt", "_al"];
    private _t = ["COMSPEC_RscStructuredText", [_lx, _scy - _fs * 0.8, _lw, _fs * 1.6]] call _mk;
    _t ctrlSetStructuredText parseText format ["<t size='0.8' align='%2' color='#a9b8b1'>%1</t>", _txt, _al];
    _t ctrlEnable false;
} forEach [
    [_vx, format ["Flash : <t color='#eef4f0'>%1</t>", createHashMapFromArray [["AUTO", "AUTO"], ["ON", "FORCÉ"], ["OFF", "COUPÉ"]] getOrDefault [_flash, "AUTO"]], "right"],
    [_scx + _sw / 2 + _pad, ["Hors réseau : gardée sur le téléphone", "Envoi direct vers Athena"] select _net, "left"]
];
{
    private _b = ["COMSPEC_RscButtonInvisible", _x] call _mk;
    _b ctrlSetTooltip "Ouvrir le viseur plein écran (clic gauche pour photographier, Espace pour revenir)";
    _b ctrlAddEventHandler ["ButtonClick", _open];
} forEach [[_vx, _vy, _vw, _vh], [_scx - _sw / 2, _scy - _sh / 2, _sw, _sh]];

// Compteurs d'envoi (un appui ouvre l'onglet ENVOIS).
private _ty = _scy + _sh / 2 + _pad / 2;
private _tH = _font * 3.2;
private _tW = (_vw - _pad) / 3;
{
    _x params ["_n", "_lab", "_hex", "_rgb"];
    private _tx = _vx + _forEachIndex * (_tW + _pad / 2);
    private _t = ["COMSPEC_RscText", [_tx, _ty, _tW, _tH]] call _mk;
    _t ctrlSetBackgroundColor [0.06, 0.075, 0.065, 1];
    private _bar = ["COMSPEC_RscText", [_tx, _ty, _tW, pixelH * 3]] call _mk;
    _bar ctrlSetBackgroundColor _rgb;
    private _c = ["COMSPEC_RscStructuredText", [_tx, _ty + _tH * 0.12, _tW, _tH * 0.88]] call _mk;
    _c ctrlSetStructuredText parseText format ["<t align='center' size='1.3' font='RobotoCondensedBold' color='%3'>%1</t><br/><t align='center' size='0.7' color='#8a9a93'>%2</t>", _n, _lab, _hex];
    private _b = ["COMSPEC_RscButtonInvisible", [_tx, _ty, _tW, _tH]] call _mk;
    _b ctrlSetTooltip "Voir le suivi des envois (onglet ENVOIS)";
    _b ctrlAddEventHandler ["ButtonClick", ["SENT"] call _go];
} forEach [
    [_pending, "EN COURS D'ENVOI", "#e8b84a", [0.91, 0.72, 0.29, 1]],
    [count (missionNamespace getVariable ["COMSPEC_Athena_PhotoUploaded", []]), "REÇUES PAR ATHENA", "#5cc76b", [0.36, 0.78, 0.42, 1]],
    [count (missionNamespace getVariable ["COMSPEC_Athena_PhotoFailed", []]), "REFUSÉES", "#e5483a", [0.9, 0.28, 0.23, 1]]
];

// Réglages et actions groupés, dernier retour d'Athena.
private _flBtn = {
    params ["_lbl", "_k", "_tip"];
    [_lbl, compile format ["private _st = uiNamespace getVariable ['COMSPEC_ATAK_PhotoSt', createHashMap]; uiNamespace setVariable ['COMSPEC_ATAK_PhotoSt', _st]; _st set ['flash', '%1']; (uiNamespace getVariable ['COMSPEC_ATAK_State', createHashMap]) set ['photoCaption', ['caption', ''] call comspec_atak_native_fnc_formValue]; playSound 'ClickSoft'; [{ ['PHOTOS'] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;", _k], ["", "on"] select (_flash isEqualTo _k), _tip]
};
private _hy = _ty + _tH + _pad / 2;
[[
    ["bar", [
        ["Prise de vue", [
            ["OUVRIR LE VISEUR", _open, "main", "Viseur plein écran : on se déplace normalement, clic gauche pour photographier, Espace pour revenir au téléphone", _canShoot],
            ["PHOTO RAPIDE", { private _c = ["caption"] call comspec_atak_native_fnc_formValue; (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) set ["photoCaption", _c]; [_c] call comspec_atak_native_fnc_photoTake; }, "", "Photographier tout de suite ce que vous voyez, sans viseur (le téléphone se masque un instant)", _canShoot]
        ]],
        ["Flash", [
            ["AUTO", "AUTO", "Le flash part la nuit et dans un intérieur sombre"] call _flBtn,
            ["FORCÉ", "ON", "Le flash part à chaque photo"] call _flBtn,
            ["COUPÉ", "OFF", "Jamais de flash (discrétion)"] call _flBtn
        ]]
    ]],
    _lastRow,
    ["text", "<t size='0.8' color='#8a9a93'>Après la prise : GALERIE pour revoir, envoyer ou supprimer une photo ; ENVOIS pour suivre sa réception par Athena.</t>"]
], [0, _hy, _bw, (_bh - _hy) max (_font * 3)]] call comspec_atak_native_fnc_formRender;
true
