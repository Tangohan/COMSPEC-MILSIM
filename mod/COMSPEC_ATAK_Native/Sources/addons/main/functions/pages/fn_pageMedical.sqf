/*
    App Médical, pour les médecins et chefs d'équipe :
    - alertes médicales Athena (inconscient, arrêt cardiaque, bilans) avec triage : à secourir, en cours, traité, KIA ;
    - suivi des troupes du camp (état ACE : stable, blessé, critique, inconscient, arrêt), les plus graves en premier ;
    - LOCALISER centre la carte sur le blessé.
*/
disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _bridge = [] call comspec_atak_native_fnc_bridge;
private _canTriage = _bridge && {!isNil "comspec_overwatch_connect_fnc_canTriageMedical"} && {[] call comspec_overwatch_connect_fnc_canTriageMedical};
private _sel = _s getOrDefault ["medSel", ""];
private _rows = [];

// Alertes Athena
_rows pushBack ["section", "Alertes médicales", ["Liaison Athena requise", ["Lecture seule : triage réservé aux médecins et chefs d'équipe", "Touchez une alerte pour la trier"] select _canTriage] select _bridge];
private _alerts = (missionNamespace getVariable ["COMSPEC_MedicalAlerts", []]) select { _x isEqualType createHashMap };
if ((count _alerts) isEqualTo 0) then { _rows pushBack ["text", "<t color='#8a9a93'>Aucune alerte active.</t>"]; };
{
    private _id = str (_x getOrDefault ["id", ""]);
    private _kind = toLower (_x getOrDefault ["kind", ""]);
    private _status = _x getOrDefault ["triage_status", "a_secourir"];
    private _col = switch (_status) do { case "en_cours": { "#f2ab33" }; case "traite": { "#5cc76b" }; case "kia": { "#8a9a93" }; default { "#e5483a" }; };
    private _kindLabel = switch (_kind) do { case "cardiac_arrest": { "ARRÊT CARDIAQUE" }; case "unconscious": { "INCONSCIENT" }; case "kia": { "KIA" }; case "wia_report": { "BILAN" }; default { "ASSISTANCE" }; };
    private _who = _x getOrDefault ["call_sign", ""];
    if (_who isEqualTo "") then { _who = _x getOrDefault ["label", "?"]; };
    _rows pushBack ["buttons", [[format ["%1 · %2 · %3 · %4", _kindLabel, _who, _x getOrDefault ["grid", "—"], toUpper (_x getOrDefault ["triage_label", _status])],
        compile format ["(uiNamespace getVariable ['COMSPEC_ATAK_State', createHashMap]) set ['medSel', ['%1', ''] select (((uiNamespace getVariable ['COMSPEC_ATAK_State', createHashMap]) getOrDefault ['medSel', '']) isEqualTo '%1')]; [{ ['MEDICAL'] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;", _id], _id isEqualTo _sel]]];
    _rows pushBack ["text", format ["<t size='0.75' color='%1'>● %2</t><t size='0.75' color='#8a9a93'>  %3</t>", _col, _x getOrDefault ["triage_label", _status], _x getOrDefault ["created_at", ""]]];
    if (_id isEqualTo _sel) then {
        private _grid = _x getOrDefault ["grid", ""];
        private _b = [[ "LOCALISER", compile format ["['locate', '%1'] call comspec_atak_native_fnc_medicalAction;", _grid]]];
        if (_canTriage) then {
            _b append [
                ["EN COURS", compile format ["['triage', 'en_cours', '%1'] call comspec_atak_native_fnc_medicalAction;", _id]],
                ["TRAITÉ", compile format ["['triage', 'traite', '%1'] call comspec_atak_native_fnc_medicalAction;", _id], true],
                ["KIA", compile format ["['triage', 'kia', '%1'] call comspec_atak_native_fnc_medicalAction;", _id]]
            ];
        };
        _rows pushBack ["buttons", _b];
    };
} forEach _alerts;

// Suivi des troupes (état ACE lu localement)
_rows pushBack ["section", "Suivi des troupes", "Camp allié, les plus graves en premier"];
private _rank = createHashMapFromArray [["cardiac_arrest", 0], ["unconscious", 1], ["critical", 2], ["wounded", 3], ["stable", 4]];
private _list = [];
{
    private _st = if (isNil "comspec_overwatch_connect_fnc_getMedicalState") then { ["stable", 100, 0, 80] } else { ([_x] call comspec_overwatch_connect_fnc_getMedicalState) splitString "|" };
    private _h = _st param [0, "stable"];
    if (!alive _x) then { _h = "kia"; };
    _list pushBack [_rank getOrDefault [_h, 5], _x, _h, _st param [1, "100"], _st param [3, "80"]];
} forEach (allPlayers select { side group _x isEqualTo side group player });
_list sort true;
{
    _x params ["", "_u", "_h", "_blood", "_hr"];
    private _lab = createHashMapFromArray [["cardiac_arrest", ["ARRÊT", "#e5483a"]], ["unconscious", ["INCONSCIENT", "#e5483a"]], ["critical", ["CRITIQUE", "#f2ab33"]], ["wounded", ["BLESSÉ", "#e8b84a"]], ["kia", ["KIA", "#8a9a93"]]] getOrDefault [_h, ["STABLE", "#5cc76b"]];
    _rows pushBack ["text", format ["<t color='%1' font='RobotoCondensedBold'>%2</t>  %3  <t size='0.8' color='#8a9a93'>sang %4 %% · pouls %5 · %6 m · %7</t>",
        _lab select 1, _lab select 0, [_u, true] call comspec_atak_native_fnc_unitCallsign, _blood, _hr, round (player distance _u), [getPosASL _u, 6] call comspec_atak_native_fnc_gridRef]];
} forEach _list;
[_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
true
