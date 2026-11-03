/*
    App Poste TOC (PC portable de TOC, fn_tocPost) : le poste de commandement en jeu, sans Athena ni site web.
    Message à tout le camp (arrive dans Messages, conversation « TOC · indicatif »), ordres aux groupes (fn_orderLocal),
    situation des groupes du camp (chef, numéro, grille), ordres émis et leurs réponses, derniers comptes rendus.
    Brouillon : uiNamespace COMSPEC_ATAK_TocDraft (fn_tocAction).
*/
disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];
private _d = "\z\comspec_atak_native\addons\main\data\";
private _draft = uiNamespace getVariable ["COMSPEC_ATAK_TocDraft", createHashMap];
private _cs = ["callsign"] call comspec_atak_native_fnc_tocPost;
private _local = ["local"] call comspec_atak_native_fnc_orderLocal;
private _esc = { params ["_t"]; if !(_t isEqualType "") then { _t = str _t; }; { _t = [_t, _x select 0, _x select 1] call CBA_fnc_replace; } forEach [["&", "&amp;"], ["<", "&lt;"], [">", "&gt;"]]; _t };
private _act = { params ["_a", ["_v", ""]]; compile format ["[%1, %2] call comspec_atak_native_fnc_tocAction;", str _a, str _v] };
private _prio = _draft getOrDefault ["prio", "ROUTINE"];
private _rows = [
    ["status", _d + "app_toc.paa", [0.36, 0.78, 0.42, 1], format ["Poste TOC · %1", [_cs] call _esc],
        ["Athena reste le poste principal : ce PC parle en plus au camp, en direct par le serveur.",
         "Mode local : messages, ordres et comptes rendus du camp passent par ce PC, sans Athena ni site web."] select _local],
    ["section", "Message à tout le camp", "Arrive dans Messages de chaque joueur de votre camp"],
    ["memo", "tocMsg", "Message", _draft getOrDefault ["msg", ""], 2],
    ["segment", "Priorité", ["ROUTINE", "URGENT", "FLASH"] apply { [_x, ["prio", _x] call _act, _prio isEqualTo _x] }],
    ["buttons", [["ENVOYER AU CAMP", ["send"] call _act, true, true, "Diffuse le message à tous les joueurs de votre camp"],
        ["NOUVEL ORDRE", ["order", ""] call _act, false, true, "Ouvre le rédacteur d'ordre (app Tâches)"]]]
];
// Écrans TOC du poste (à 30 m) : caméra du camp à afficher en direct (fn_tocCam), ou retour à la carte et au flux.
private _scr = (missionNamespace getVariable ["COMSPEC_ATAK_TocScreens", []]) select { !isNull _x && {alive _x} && {(player distance _x) < 30} && {((_x getVariable ["COMSPEC_ATAK_TocScr", []]) param [1, ""]) in ["", str side group player]} };
if ((count _scr) > 0) then {
    private _srcs = ["sources"] call comspec_atak_native_fnc_tocCam;
    _rows pushBack ["section", "Caméra sur les écrans du poste", "Image en direct pour les joueurs devant l'écran"];
    if ((count _srcs) isEqualTo 0) then { _rows pushBack ["text", "<t size='0.85' color='" + (COMSPEC_ATAK_Th get "hexTextMuted") + "'>Aucune caméra du camp à afficher : drone du camp en vol, caméra fixe ou caméra de téléphone partagée avec vous.</t>"]; };
    {
        private _o = _x;
        private _c = _o getVariable ["COMSPEC_ATAK_TocScr", []];
        private _id = netId _o;
        private _onCam = (_c param [3, ""]) isEqualTo "CAM";
        _rows pushBack ["text", format ["<t font='COMSPEC_BarlowBold'>%1</t>  <t size='0.8' color='" + (COMSPEC_ATAK_Th get "hexTextMuted") + "'>%2 m · %3</t>", [_c param [0, "TOC"]] call _esc, round (player distance _o),
            [["affichage du poste", format ["caméra : %1", ["label", _o] call comspec_atak_native_fnc_tocCam]] select _onCam] call _esc]];
        if ((count _srcs) > 0) then {
            _rows pushBack ["combo", "tocCam" + _id, "Caméra", _srcs apply { [_x select 0, _x select 1] }, _o getVariable ["COMSPEC_ATAK_TocCam", ""],
                ["AFFICHER", compile format ["params ['_src']; ['set', [objectFromNetId '%1', _src]] call comspec_atak_native_fnc_tocCam; [{ ['TOC'] call comspec_atak_native_fnc_pageRender; }, [], 0.5] call CBA_fnc_waitAndExecute;", _id]]];
        };
        if (_onCam) then {
            _rows pushBack ["buttons", [["CARTE ET FLUX", compile format ["['ensure', [objectFromNetId '%1', 'SPLIT']] call comspec_atak_native_fnc_tocScreen; [{ ['TOC'] call comspec_atak_native_fnc_pageRender; }, [], 0.5] call CBA_fnc_waitAndExecute;", _id], false, true, "L'écran revient à la carte et au flux du poste"]]];
        };
    } forEach _scr;
};
// Groupes du camp ayant des joueurs : chef, effectif, numéro et grille du chef.
private _groups = [];
{ if (side _x isEqualTo side group player && {({isPlayer _x && {alive _x}} count (units _x)) > 0}) then { _groups pushBackUnique _x; }; } forEach allGroups;
_rows pushBack ["section", "Groupes du camp", format ["%1 groupe(s) avec des joueurs", count _groups]];
{
    private _ld = leader _x;
    private _n = {isPlayer _x && {alive _x}} count (units _x);
    private _num = ([_ld] call comspec_atak_native_fnc_phoneIdent) select 0;
    private _key = [_ld] call comspec_atak_native_fnc_unitGroup;
    _rows pushBack ["person", _d + "app_group.paa", format ["<t font='COMSPEC_BarlowBold'>%1</t> <t size='0.8' color='" + (COMSPEC_ATAK_Th get "hexTextMuted") + "'>%2 joueur(s)</t><br/><t size='0.8'>Chef %3 · %4 · grille %5</t>",
        [groupId _x] call _esc, _n, [name _ld] call _esc, [_num] call _esc, [getPosATL _ld, 6] call comspec_atak_native_fnc_gridRef],
        [["SMS", ["sms", name _ld] call _act, false, isPlayer _ld && {_ld isNotEqualTo player}, "Message direct au chef"],
         ["ORDRE", ["order", _key] call _act, true, true, "Ordre à ce groupe"]]];
} forEach _groups;
// Ordres émis depuis ce poste ou par moi, avec le suivi des réponses (fn_orderAck).
private _mine = values ((uiNamespace getVariable ["COMSPEC_ATAK_Data", createHashMap]) getOrDefault ["localTasks", createHashMap]) select { (_x getOrDefault ["issuerUid", ""]) isEqualTo getPlayerUID player };
if (_mine isNotEqualTo []) then {
    _rows pushBack ["section", "Ordres émis", format ["%1 ordre(s)", count _mine]];
    {
        private _acks = ["list", [_x getOrDefault ["id", ""]]] call comspec_atak_native_fnc_orderAck;
        private _cnt = createHashMap;
        { private _k = _y select 1; _cnt set [_k, (_cnt getOrDefault [_k, 0]) + 1]; } forEach _acks;
        private _lbl = _x getOrDefault ["typeLabel", ""];
        if (_lbl isEqualTo "") then { _lbl = _x getOrDefault ["type", "ORDRE"]; };
        private _p = _x getOrDefault ["payload", ""];
        _rows pushBack ["person", _d + "app_tasks.paa", format ["<t font='COMSPEC_BarlowBold'>%1</t> <t size='0.8' color='" + (COMSPEC_ATAK_Th get "hexTextMuted") + "'>%2 · %3 · %4</t><br/><t size='0.8'>%5</t><br/><t size='0.75' color='" + (COMSPEC_ATAK_Th get "hexTextMuted") + "'>Vu %6 · reçu %7 · en cours %8 · terminé %9</t>",
            [_lbl] call _esc, _x getOrDefault ["time", ""], [["Tous", _x getOrDefault ["target", ""]] select ((_x getOrDefault ["target", ""]) isNotEqualTo "")] call _esc, _x getOrDefault ["priority", ""],
            [_p select [0, 140]] call _esc, _cnt getOrDefault ["SEEN", 0], _cnt getOrDefault ["ACK", 0], _cnt getOrDefault ["EXEC", 0], _cnt getOrDefault ["DONE", 0]], []];
    } forEach (_mine select [0 max ((count _mine) - 6)]);
};
// Derniers comptes rendus et alertes reçus (app Alertes).
private _log = missionNamespace getVariable ["COMSPEC_ATAK_AlertLog", []];
private _recent = _log select [0 max ((count _log) - 6)];
reverse _recent;
_rows pushBack ["section", "Derniers comptes rendus", ["Aucun pour l'instant", format ["%1 récent(s)", count _log]] select ((count _log) > 0)];
{
    _x params [["_type", ""], ["_who", ""], "", ["_grid", ""], ["_text", ""], ["_time", ""]];
    _rows pushBack ["person", _d + "app_alerts.paa", format ["<t font='COMSPEC_BarlowBold'>%1</t> <t size='0.8' color='" + (COMSPEC_ATAK_Th get "hexTextMuted") + "'>%2 · %3 · %4</t>%5",
        [_type] call _esc, _time, [_who] call _esc, _grid, ["", format ["<br/><t size='0.8'>%1</t>", [_text select [0, 140]] call _esc]] select (_text isNotEqualTo "")], []];
} forEach _recent;
_rows append [
    ["buttons", [["ALERTES", ["nav", "ALERTS"] call _act], ["CARTE", ["nav", "MAP"] call _act], ["NUMÉROS", ["nav", "NUMBERS"] call _act]]],
    ["section", "Poste", "Enregistré dans config.json (toc.callsign)"],
    ["field", "tocCs", "Indicatif du poste", _cs, "ENREGISTRER", ["saveCs"] call _act, false]
];
[_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
true
