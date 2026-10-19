/*
    App Galerie : photos gardées sur le téléphone et suivi de leur envoi vers Athena. Les photos se prennent dans
    l'app Photo (viseur dans l'écran, fn_pagePhotos) ; la galerie en est séparée, comme sur un vrai téléphone.
    Une rangée d'onglets TOUT / TÉLÉPHONE / DRONE / ENVOIS (clés galTab et photoFilter), puis l'état de l'envoi :
      PHOTOS  (LIB)  : photos du téléphone (dossier COMSPEC et captures Arma, fn_photoLibrary) et clichés du drone
                       (profil COMSPEC_ATAK_DronePhotos, fn_dronePhoto) avec titre, SSE / confidentiel et état d'envoi ;
                       les trois premiers onglets filtrent la liste. DÉTAILS ouvre la fiche d'une photo
                       (clé photoSel = [type, nom en minuscules]) : envoi vers Athena, suppression (second appui) et,
                       pour un cliché du drone, titre, description, SSE et confidentiel modifiables ;
      ENVOIS  (SENT) : compteurs, dernier retour d'Athena, clichés du drone en cours ou refusés, historique.
    Les fichiers des clichés du drone sont aussi dans le dossier Screenshots : ils ne sont montrés qu'une fois, en DRONE.
    Actions sur une barre sans titres de groupe (ligne "bar" de fn_formRender), aide en infobulle.
    Params (app déclarée avec function=) : [page, [x, y, largeur, hauteur]]
*/
disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];
private _font = _l get "font";
private _fs = _l get "fontSmall";
private _pad = (_l get "pad") * 2;
private _esc = { params ["_t"]; if !(_t isEqualType "") then { _t = str _t; }; [[_t, "<", "&lt;"] call CBA_fnc_replace, ">", "&gt;"] call CBA_fnc_replace };
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _tab = _s getOrDefault ["galTab", "LIB"];
if !(_tab in ["LIB", "SENT"]) then { _tab = "LIB"; };
private _bridge = [] call comspec_atak_native_fnc_bridge;
private _ready = missionNamespace getVariable ["COMSPEC_AthenaReady", false];
private _net = (([] call comspec_atak_native_fnc_linkQuality) getOrDefault ["bars", 0]) > 0;
// Code commun des boutons qui changent de vue : légende gardée, fiche du cliché drone enregistrée puis refermée.
private _leave = "private _st0 = uiNamespace getVariable ['COMSPEC_ATAK_State', createHashMap]; if ((['caption', '§'] call comspec_atak_native_fnc_formValue) isNotEqualTo '§') then { _st0 set ['photoCaption', ['caption', ''] call comspec_atak_native_fnc_formValue]; }; if (((_st0 getOrDefault ['photoSel', []]) param [0, '']) isEqualTo 'DRONE') then { ['save'] call comspec_atak_native_fnc_dronePhoto; _st0 set ['dronePhEdit', -1]; };";
private _go = { params ["_tabK", ["_sel", []]]; compile format ["%1 _st0 set ['galTab', '%2']; _st0 set ['photoSel', %3]; if ('%2' isEqualTo 'LIB') then { ['list'] call comspec_atak_native_fnc_photoLibrary; }; [{ ['GALLERY'] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;", _leave, _tabK, str _sel] };

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
private _dArm = _s getOrDefault ["dronePhDel", [-1, -10]];
private _droneArmed = { params ["_k"]; (_dArm select 0) isEqualTo _k && {diag_tickTime - (_dArm select 1) <= 4} };
private _pending = count (missionNamespace getVariable ["COMSPEC_Athena_PhotoPending", []]);

// En-tête : une seule rangée d'onglets (TOUT / TÉLÉPHONE / DRONE / ENVOIS) et une ligne d'état de l'envoi.
private _total = (count _phone) + (count _droneList);
private _flt = _s getOrDefault ["photoFilter", "ALL"];
if !(_flt in ["ALL", "PHONE", "DRONE"]) then { _flt = "ALL"; };
private _fTab = {
    params ["_t", "_k", "_tip"];
    [_t, compile format ["%1 _st0 set ['galTab', 'LIB']; _st0 set ['photoFilter', '%2']; _st0 set ['photoSel', []]; ['list'] call comspec_atak_native_fnc_photoLibrary; [{ ['GALLERY'] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;", _leave, _k], _tab isEqualTo "LIB" && {_flt isEqualTo _k}, _tip]
};
private _head = [
    ["segment", "", [
        [format ["TOUT (%1)", _total], "ALL", "Photos du téléphone et clichés du drone"] call _fTab,
        [format ["TÉLÉPHONE (%1)", count _phone], "PHONE", "Photos prises avec le téléphone et captures d'écran"] call _fTab,
        [format ["DRONE (%1)", count _droneList], "DRONE", "Clichés pris avec la caméra du drone"] call _fTab,
        [["ENVOIS", format ["ENVOIS (%1)", _pending]] select (_pending > 0), ["SENT"] call _go, _tab isEqualTo "SENT", "Suivre la réception des photos par Athena"]
    ]],
    ["text", format ["<t size='0.78'>%1</t>", switch (true) do {
        case (!_bridge): { "<t color='#e5483a'>● COMSPEC Overwatch absent : ni prise de vue ni envoi possibles</t>" };
        case (!_ready): { "<t color='#f2ab33'>● Athena non connecté : connectez-vous dans l'app Athena pour prendre et envoyer des photos</t>" };
        case (!_net): { "<t color='#f2ab33'>● Hors réseau : les photos restent sur le téléphone, à envoyer plus tard d'ici</t>" };
        default { "<t color='#5cc76b'>● Prêt : les photos partent directement vers Athena</t>" };
    }]]
];
// Actions de la galerie : une barre sans titres de groupe, l'action utile en vert.
private _sendBtn = [format ["ENVOYER LES NOUVELLES (%1)", _newN], { ["sendNew"] call comspec_atak_native_fnc_photoLibrary; }, ["", "main"] select (_newN > 0), "Envoyer les photos du téléphone qu'Athena n'a pas encore (une toutes les 2 s). Les clichés du drone partent avec leur fiche.", _bridge && {_newN > 0}];
private _shootBtn = ["PRENDRE UNE PHOTO", { ["PHOTOS"] call comspec_atak_native_fnc_navigate; }, "", "Ouvrir l'app Photo (viseur dans l'écran)"];

// Dernier retour d'Athena (onglet ENVOIS).
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
        format ["<t font='COMSPEC_BarlowBold'>%1</t>%2<br/><t size='0.75'><t color='%4'>● %3</t>   <t color='#8a9a93'>%5 %6   %7</t></t>",
            [[_title] call _esc, "Cliché drone sans titre"] select (_title isEqualTo ""), [_sse, _cf, _case] call _tags, _st select 0, _st select 1, _date, _clock, _grid],
        [["DÉTAILS", _open, false, true, "Fiche du cliché : position du drone, titre, description, SSE, confidentiel ; envoyer ou supprimer"],
         if (_state isEqualTo "DRAFT") then {
            ["COMPLÉTER", _open, true, true, "Donner un titre et une description avant l'envoi"]
         } else {
            [["ENVOYER", "RENVOYER"] select (_state in ["SENT", "FAILED"]), compile format ["['send', %1] call comspec_atak_native_fnc_dronePhoto;", _i], _state in ["LOCAL", "FAILED"], _state isNotEqualTo "SENDING", "Envoyer ce cliché vers Athena avec son titre, sa description et ses marquages"]
         },
         [["SUPPRIMER", "CONFIRMER"] select ([_i] call _droneArmed),
            compile format ["private _n = count (profileNamespace getVariable ['COMSPEC_ATAK_DronePhotos', []]); ['forget', %1] call comspec_atak_native_fnc_dronePhoto; if ((count (profileNamespace getVariable ['COMSPEC_ATAK_DronePhotos', []])) < _n) then { ['list'] call comspec_atak_native_fnc_photoLibrary; };", _i],
            ["", "red"] select ([_i] call _droneArmed), _state isNotEqualTo "SENDING", "Effacer ce cliché et son fichier du téléphone (second appui pour confirmer). Une photo déjà reçue reste sur Athena."]],
        [0.36, 0.78, 0.42, 1]]
};
private _phoneRow = {
    params ["_j", "_e"];
    _e params ["_path", "_name"];
    private _st = [_path, _name] call _phoneSt;
    ["person", "\z\comspec_atak_native\addons\main\data\ui_gallery.paa",
        format ["<t font='COMSPEC_BarlowBold'>%1</t><br/><t size='0.75' color='%3'>● %2</t>", [_name] call _esc, _st select 0, _st select 1],
        [["DÉTAILS", ["LIB", ["PHONE", toLower _name]] call _go, false, true, "Fiche de la photo : emplacement, état d'envoi ; envoyer ou supprimer"],
         [["ENVOYER", "RENVOYER"] select ((_st select 2) || {(_st select 0) isEqualTo "ENVOI REFUSÉ"}), compile format ["['send', %1] call comspec_atak_native_fnc_photoLibrary;", _j], !(_st select 2), true, "Envoyer cette photo vers Athena (ATAK web)"],
         [["SUPPRIMER", "CONFIRMER"] select ([str _j] call _isArmed), compile format ["['delete', %1] call comspec_atak_native_fnc_photoLibrary;", _j], ["", "red"] select ([str _j] call _isArmed), true, "Effacer le fichier du téléphone (second appui pour confirmer). Une photo déjà reçue reste sur Athena."]],
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
    // Galerie seule ou incrustée dans l'app Photos : le sous-écran appartient à la page affichée.
    private _st1 = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
    _st1 set ["backSub", [_st1 getOrDefault ["activePage", ""], ["LIB"] call _go]];
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
                    ["Fiche", [["ENREGISTRER", { ["save"] call comspec_atak_native_fnc_dronePhoto; ["SUCCESS", "Fiche du cliché enregistrée sur le téléphone", 3, 20] call comspec_atak_native_fnc_notify; [{ ["GALLERY"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame; }, "", "Garder le titre et la description sur le téléphone, sans rien envoyer"]]],
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
            _rows pushBack ["bar", [["", [
                _shootBtn,
                ["ACTUALISER", { ["list"] call comspec_atak_native_fnc_photoLibrary; [{ ['GALLERY'] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame; }, "", "Relire les photos du téléphone et demander à Athena lesquelles elle a déjà", _bridge],
                _sendBtn,
                [["TOUT SUPPRIMER", "CONFIRMER"] select (["ALL"] call _isArmed), { ["deleteAll"] call comspec_atak_native_fnc_photoLibrary; }, ["", "red"] select (["ALL"] call _isArmed), "Effacer du téléphone toutes ses photos et tous les clichés du drone (second appui pour confirmer). Les photos déjà reçues restent sur Athena.", _bridge && {_total > 0}]
            ]]]];
            if (_flt in ["ALL", "DRONE"]) then {
                _rows pushBack ["section", format ["Clichés du drone (%1)", count _droneList], "Titre, description et marquages partent avec la photo"];
                if ((count _droneList) isEqualTo 0) then { _rows pushBack ["text", "<t color='#8a9a93'>Aucun cliché du drone. App Drone, caméra ouverte : bouton PHOTO sous l'image.</t>"]; };
                { _rows pushBack (_x call _droneRow); } forEach (_droneList select [0, 20]);
            };
            if (_flt in ["ALL", "PHONE"]) then {
                _rows pushBack ["section", format ["Photos du téléphone (%1)", count _phone], "Dossier COMSPEC et captures d'écran Arma, les plus récentes en premier"];
                if (!_bridge) then {
                    _rows pushBack ["text", "<t color='#e0a030'>Liste disponible avec la liaison Overwatch (mod COMSPEC Overwatch chargé).</t>"];
                } else {
                    if ((count _phone) isEqualTo 0) then { _rows pushBack ["text", "<t color='#8a9a93'>Aucune photo sur le téléphone. App Photo pour en prendre une.</t>"]; };
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
    } forEach (["var", ["COMSPEC_Athena_AlertInbox", []]] call comspec_atak_native_fnc_offline);
    reverse _lines;
    private _watch = _droneList select { ((_x select 1) param [8, ""]) in ["SENDING", "FAILED"] };
    private _rows = _head + [
        ["text", format ["<t size='1.3' font='COMSPEC_BarlowBold' color='#e8b84a'>%1</t> <t size='0.8' color='#8a9a93'>en cours</t>      <t size='1.3' font='COMSPEC_BarlowBold' color='#5cc76b'>%2</t> <t size='0.8' color='#8a9a93'>reçues</t>      <t size='1.3' font='COMSPEC_BarlowBold' color='#e5483a'>%3</t> <t size='0.8' color='#8a9a93'>refusées</t>",
            _pending, count (missionNamespace getVariable ["COMSPEC_Athena_PhotoUploaded", []]), count (missionNamespace getVariable ["COMSPEC_Athena_PhotoFailed", []])]],
        _lastRow,
        ["bar", [["", [_shootBtn, _sendBtn]]]]
    ];
    if ((count _watch) > 0) then {
        _rows pushBack ["section", format ["Clichés du drone à suivre (%1)", count _watch], "En cours d'envoi ou refusés par Athena"];
        { _rows pushBack (_x call _droneRow); } forEach (_watch select [0, 10]);
    };
    _rows pushBack ["section", "Historique", "Photos reçues par Athena pendant cette session, la plus récente en haut"];
    if ((count _lines) isEqualTo 0) then {
        _rows pushBack ["text", "<t color='#8a9a93'>Rien pour l'instant. App Photo pour prendre une photo.</t>"];
    } else {
        { _rows pushBack ["text", _x]; } forEach (_lines select [0, 15]);
    };
    [_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
    true
};
