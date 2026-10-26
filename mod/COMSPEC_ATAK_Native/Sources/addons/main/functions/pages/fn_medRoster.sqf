/*
    Fiches médicales de la communauté (app Médical, onglet DOSSIERS) : groupe sanguin, poids, taille, intolérances et
    allergies de chaque membre, saisis sur Athena (Effectifs > Tableur > Médical).
    COMSPEC Link « MedicalRoster » les garde sur ce PC (Documents\Arma 3 - COMSPEC\Sync\medical_roster.json) : à
    l'ouverture de l'onglet le fichier suffit, le site n'est relu que sur ACTUALISER (ou si ce PC n'a encore rien).
    Le site décide qui y a droit (médecin, gestion, pilotage des effectifs) ; un refus efface la copie du PC.
    Params : [action, argument]
      ["load", actualiser]   lit la liste (spawn) : false = fichier du PC, true = relue sur Athena
      ["find", uid Steam]    fiche d'un joueur ([] si inconnue) : [steam, indicatif, fonction, groupe, poids, taille, intolérances, allergies]
      ["scope", "GAME"|"ALL"] joueurs de la partie seulement, ou toute la communauté
      ["search"]             filtre par indicatif / fonction (champ de recherche de l'onglet)
    État : uiNamespace COMSPEC_ATAK_MedRoster = [fiches, enregistré le, droit, erreur] ; COMSPEC_ATAK_MedRosterBy = HashMap steam -> fiche.
*/
params [["_act", "load"], ["_arg", false]];
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _render = { [{ if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "MEDICAL") then { ["MEDICAL"] call comspec_atak_native_fnc_pageRender; }; }] call CBA_fnc_execNextFrame; };
switch (_act) do {
    case "find": { (uiNamespace getVariable ["COMSPEC_ATAK_MedRosterBy", createHashMap]) getOrDefault [_arg, []] };
    case "scope": { _s set ["medRosterScope", _arg]; call _render; };
    case "search": { _s set ["medRosterQ", trim (["medRosterQ"] call comspec_atak_native_fnc_formValue)]; call _render; };
    case "load": {
        uiNamespace setVariable ["COMSPEC_ATAK_MedRosterAsked", true];
        // Une seule lecture à la fois (ACTUALISER répété, redessin) ; repère daté, périmé après 30 s.
        if (diag_tickTime - (missionNamespace getVariable ["COMSPEC_ATAK_MedRosterBusy", -100]) < 30) exitWith {};
        if (!canSuspend) exitWith { [_arg] spawn { ["load", _this select 0] call comspec_atak_native_fnc_medRoster; }; };
        missionNamespace setVariable ["COMSPEC_ATAK_MedRosterBusy", diag_tickTime];
        call _render;
        private _ext = {
            params ["_cmd", "_args"];
            private _r = (["COMSPECExtension", _cmd, _args] call comspec_atak_native_fnc_extAwait) param [0, ""];
            if !(_r isEqualType "") then { _r = str _r; };
            _r
        };
        private _old = uiNamespace getVariable ["COMSPEC_ATAK_MedRoster", [[], "", "", ""]];
        private _lines = [];
        private _err = "";
        private _start = 0;
        private _mode = ["cache", "refresh"] select _arg;
        // Pages de la DLL : OK|NEXT\tligne suivante\n… puis OK|END\n…
        for "_page" from 0 to 40 do {
            private _r = ["MedicalRoster", [str _start, _mode]] call _ext;
            if ((_r select [0, 3]) isNotEqualTo "OK|") exitWith { _err = [_r, "old_dll"] select (_r isEqualTo ""); };
            private _body = (_r select [3]) splitString toString [10];
            private _head = _body param [0, ""];
            _lines append (_body select [1]);
            if ((_head select [0, 4]) isNotEqualTo "NEXT") exitWith {};
            _start = parseNumber ((_head splitString toString [9]) param [1, "0"]);
            _mode = "cache";
        };
        missionNamespace setVariable ["COMSPEC_ATAK_MedRosterBusy", -100];
        if (_err isNotEqualTo "") exitWith {
            private _why = switch (true) do {
                case (_err isEqualTo "old_dll"): { "La DLL COMSPEC Link ne connaît pas encore les fiches médicales : mettez-la à jour." };
                case ((_err find "forbidden") >= 0): { "Fiches réservées aux médecins, à la gestion et au pilotage des effectifs (fonction médicale sur Athena)." };
                case ((_err find "session_required") >= 0 || {(_err find "unauthorized") >= 0} || {(_err find "not_connected") >= 0}): { "Connexion Athena requise : app Athena > COMPTE." };
                default { format ["Athena ne répond pas (%1).", _err select [0, 60]] };
            };
            // Droit retiré sur le site : la DLL a effacé sa copie, le téléphone l'oublie aussi.
            if ((_err find "forbidden") >= 0) then { _old = [[], "", "none", ""]; uiNamespace setVariable ["COMSPEC_ATAK_MedRosterBy", createHashMap]; };
            uiNamespace setVariable ["COMSPEC_ATAK_MedRoster", [_old select 0, _old select 1, _old select 2, _why]];
            call _render;
        };
        private _rows = [];
        private _by = createHashMap;
        {
            private _c = (_x splitString toString [9]) apply { ["", _x] select (_x isNotEqualTo "-") };
            if ((count _c) >= 8) then {
                _rows pushBack (_c select [0, 8]);
                if ((_c select 0) isNotEqualTo "") then { _by set [_c select 0, _c select [0, 8]]; };
            };
        } forEach _lines;
        private _info = ((["MedicalRosterInfo", []] call _ext) select [3]) splitString toString [9];
        uiNamespace setVariable ["COMSPEC_ATAK_MedRoster", [_rows, _info param [0, ""], _info param [2, ""], ""]];
        uiNamespace setVariable ["COMSPEC_ATAK_MedRosterBy", _by];
        if (_arg) then { ["SUCCESS", format ["Fiches médicales à jour : %1 membre(s)", count _rows], 3, 20] call comspec_atak_native_fnc_notify; };
        call _render;
    };
};
