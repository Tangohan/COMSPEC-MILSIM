/* App Photos : onglet APPAREIL (viseur plein écran, légende, suivi des envois) et onglet BIBLIOTHÈQUE (photos du poste, retransmission). */
disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];
private _esc = { params ["_t"]; if !(_t isEqualType "") then { _t = str _t; }; [[_t, "<", "&lt;"] call CBA_fnc_replace, ">", "&gt;"] call CBA_fnc_replace };
private _lines = [];
{
    _x params [["_kind", ""], ["_title", ""], ["_text", ""], ["_grid", ""], ["_time", ""]];
    if ((toUpper _kind) isEqualTo "PHOTO") then {
        _lines pushBack format ["<t color='#8a9a93'>%1</t>  %2<br/>%3", [_time] call _esc, [_title] call _esc, [_text] call _esc];
    };
} forEach (missionNamespace getVariable ["COMSPEC_Athena_AlertInbox", []]);
reverse _lines;
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _tab = _s getOrDefault ["photoTab", "CAMERA"];
private _tabBtn = { params ["_t", "_k"]; [_t, compile format ["(uiNamespace getVariable ['COMSPEC_ATAK_State', createHashMap]) set ['photoTab', '%1']; if ('%1' isEqualTo 'LIB') then { ['list'] call comspec_atak_native_fnc_photoLibrary; }; [{ ['PHOTOS'] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;", _k], _tab isEqualTo _k] };
if (_tab isEqualTo "LIB" && {isNil { uiNamespace getVariable "COMSPEC_ATAK_PhotoLibAt" }}) then { ["list"] call comspec_atak_native_fnc_photoLibrary; };
private _lib = uiNamespace getVariable ["COMSPEC_ATAK_PhotoLib", []];
private _head = [["segment", "", [["APPAREIL", "CAMERA"] call _tabBtn, [format ["BIBLIOTHÈQUE (%1)", count _lib], "LIB"] call _tabBtn]]];
if (_tab isEqualTo "LIB") exitWith {
    private _sent = ((missionNamespace getVariable ["COMSPEC_Athena_PhotoUploaded", []]) select { _x isEqualType "" }) apply { toLower _x };
    private _bad = ((missionNamespace getVariable ["COMSPEC_Athena_PhotoFailed", []]) + (profileNamespace getVariable ["COMSPEC_Athena_PhotoDead", []])) select { _x isEqualType "" } apply { toLower _x };
    private _wait = (missionNamespace getVariable ["COMSPEC_Athena_PhotoPending", []]) apply { toLower str _x };
    private _rows = _head + [
        ["text", "<t color='#8a9a93'>Les photos enregistrées sur ce poste (dossier COMSPEC et captures Arma), les plus récentes en premier. Arma ne sait pas afficher une image hors de ses fichiers : l'aperçu se fait sur ATAK web, onglet Photos, une fois la photo transmise.</t>"],
        ["buttons", [["ACTUALISER", { ['list'] call comspec_atak_native_fnc_photoLibrary; [{ ['PHOTOS'] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame; }], ["TOUT RETRANSMETTRE", { ['sendAll'] call comspec_atak_native_fnc_photoLibrary; }, true]]]
    ];
    if !([] call comspec_atak_native_fnc_bridge) then {
        _rows pushBack ["text", "<t color='#e0a030'>Bibliothèque disponible avec la liaison Overwatch (mod COMSPEC Overwatch chargé).</t>"];
    } else {
        if ((count _lib) isEqualTo 0) then { _rows pushBack ["text", "<t color='#8a9a93'>Aucune photo trouvée sur le poste. Prenez-en une depuis l'onglet APPAREIL.</t>"]; };
    };
    {
        _x params ["_path", "_name"];
        private _k = [toLower _path, toLower _name];
        private _st = switch (true) do {
            case ((_k findIf { _x in _sent }) >= 0): { "<t color='#5cc76b'>transmise</t>" };
            case ((_k findIf { _x in _bad }) >= 0): { "<t color='#e5483a'>refusée</t>" };
            case ((_wait findIf { ((_x find (_k select 1)) >= 0) }) >= 0): { "<t color='#e0a030'>en attente</t>" };
            default { "<t color='#8a9a93'>sur le poste</t>" };
        };
        _rows pushBack ["info", [_name] call _esc, _st];
        _rows pushBack ["buttons", [["RETRANSMETTRE", compile format ["['send', %1] call comspec_atak_native_fnc_photoLibrary;", _forEachIndex]]]];
    } forEach _lib;
    [_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
    true
};
private _rows = _head + [
    ["title", "Appareil photo"],
    ["text", "<t color='#8a9a93'>Ouvre le viseur plein écran : déplacez-vous normalement, <t color='#c9d4cf'>clic gauche</t> pour photographier (autant que vous voulez), <t color='#c9d4cf'>Espace</t> pour revenir au téléphone. Chaque photo part sur ATAK web avec la grille, le cap et la légende.</t>"],
    ["edit", "caption", "Légende (facultatif)", ""],
    ["buttons", [["OUVRIR L'APPAREIL PHOTO", { private _c = ["caption"] call comspec_atak_native_fnc_formValue; [{ ["ENTER", _this] call comspec_atak_native_fnc_photoMode; }, _c] call CBA_fnc_execNextFrame; }, true]]],
    ["title", "Envois"],
    ["info", "En attente / reçues / refusées", format ["%1 / <t color='#5cc76b'>%2</t> / <t color='#e5483a'>%3</t>", count (missionNamespace getVariable ["COMSPEC_Athena_PhotoPending", []]), count (missionNamespace getVariable ["COMSPEC_Athena_PhotoUploaded", []]), count (missionNamespace getVariable ["COMSPEC_Athena_PhotoFailed", []])]],
    ["info", "Dernière mise en file", [[missionNamespace getVariable ["COMSPEC_LastReconUploadDetail", "—"]] call _esc, "—"] select ((missionNamespace getVariable ["COMSPEC_LastReconUploadDetail", ""]) isEqualTo "")],
    ["text", (missionNamespace getVariable ["COMSPEC_LastReconUploadResult", []]) call {
        params [["_st", ""], ["_msg", ""], ["_tech", ""], ["_at", ""]];
        switch (_st) do {
            case "OK": { format ["<t color='#5cc76b'>%1 · reçue par Athena</t>  <t size='0.8' color='#8a9a93'>%2</t>", _at, [_tech] call _esc] };
            case "ERR": { format ["<t color='#e5483a'>%1 · %2</t><br/><t size='0.8' color='#8a9a93'>%3</t>", _at, [_msg] call _esc, [_tech] call _esc] };
            default { "<t color='#8a9a93'>Aucun retour d'Athena pour l'instant.</t>" };
        }
    }],
    ["text", if ((count _lines) isEqualTo 0) then { "<t color='#8a9a93'>Aucune photo envoyée pendant cette session.</t>" } else { (_lines select [0, 12]) joinString "<br/><br/>" }]
];
[_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
true
