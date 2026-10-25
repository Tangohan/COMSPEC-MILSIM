/*
    Indicatif individuel choisi par le joueur (apps C2 et Groupe), gardé hors ligne et synchronisé avec Athena.
      "init"            : à l'arrivée et à la réapparition : indicatif du profil publié (COMSPEC_ATAK_CsCustom, public)
      "set" [texte]     : enregistre l'indicatif (profil COMSPEC_ATAK_MyCallsign), le publie, l'envoie à Athena (Squad.Sync
                          callsign_custom : Athena met à jour la fiche du compte lié)
      "save" [clé]      : comme "set", avec le texte du champ de formulaire clé (bouton ENREGISTRER)
      "clear"           : revient à l'indicatif d'Athena (ou à celui de la mission)
      "web" [texte]     : indicatif de la fiche Athena relu à l'arrivée (fn_ftRoleSync, ligne CS de GetFireTeams) : changé sur
                          le site depuis la dernière fois, il remplace celui du téléphone ; sinon le choix du téléphone reste
      "rows"            : lignes de formulaire « Mon indicatif » (fn_pageC2, fn_pageGroup)
    Profil : COMSPEC_ATAK_MyCallsign (choisi), COMSPEC_ATAK_MyCallsignWeb (dernier indicatif vu sur Athena).
    Avec la configuration locale (fn_localCfg, champ callsign de config.json) : l'indicatif y est aussi écrit, et un
    indicatif modifié à la main dans le fichier est repris à l'arrivée.
*/
params [["_act", ""], ["_arg", ""]];
if (!hasInterface) exitWith { false };
private _clean = {
    params ["_t"];
    if !(_t isEqualType "") then { _t = str _t; };
    _t = (trim (_t regexReplace ["[<>&|\t\n\r""]", ""])) select [0, 24];
    _t
};
private _publish = {
    params ["_cs"];
    if ((player getVariable ["COMSPEC_ATAK_CsCustom", ""]) isNotEqualTo _cs) then { player setVariable ["COMSPEC_ATAK_CsCustom", _cs, true]; };
};
// config.json du mode local (fn_localCfg), seulement quand la fonction existe.
private _hasCfg = !isNil "comspec_atak_native_fnc_localCfg";
private _cfgSave = {
    params ["_cs"];
    if (!_hasCfg) exitWith {};
    private _cfg = ["get"] call comspec_atak_native_fnc_localCfg;
    if ((_cfg getOrDefault ["callsign", ""]) isEqualTo _cs) exitWith {};
    _cfg set ["callsign", _cs];
    ["save"] call comspec_atak_native_fnc_localCfg;
};
private _rerender = {
    [{
        private _p = (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""];
        if (_p in ["C2", "GROUP"]) then { [_p] call comspec_atak_native_fnc_pageRender; };
    }] call CBA_fnc_execNextFrame;
};
switch (_act) do {
    case "init": {
        if (_hasCfg) then {
            private _file = [(["get"] call comspec_atak_native_fnc_localCfg) getOrDefault ["callsign", ""]] call _clean;
            if ((count _file) >= 2 && {_file isNotEqualTo (profileNamespace getVariable ["COMSPEC_ATAK_MyCallsign", ""])}) then {
                profileNamespace setVariable ["COMSPEC_ATAK_MyCallsign", _file];
                saveProfileNamespace;
            };
        };
        [[profileNamespace getVariable ["COMSPEC_ATAK_MyCallsign", ""]] call _clean] call _publish;
        if !(missionNamespace getVariable ["COMSPEC_ATAK_CsRespawnEh", false]) then {
            missionNamespace setVariable ["COMSPEC_ATAK_CsRespawnEh", true];
            player addEventHandler ["Respawn", { [{ ["init"] call comspec_atak_native_fnc_callsignAction; }, [], 1] call CBA_fnc_waitAndExecute; }];
        };
        true
    };
    case "save": {
        ["set", [_arg, ""] call comspec_atak_native_fnc_formValue] call comspec_atak_native_fnc_callsignAction
    };
    case "set": {
        private _cs = [_arg] call _clean;
        if (_cs isEqualTo "") exitWith { ["clear"] call comspec_atak_native_fnc_callsignAction };
        if ((count _cs) < 2) exitWith { ["WARNING", "Indicatif trop court (2 caractères au moins)", 3, 20] call comspec_atak_native_fnc_notify; false };
        profileNamespace setVariable ["COMSPEC_ATAK_MyCallsign", _cs];
        saveProfileNamespace;
        [_cs] call _cfgSave;
        [_cs] call _publish;
        [true] call comspec_atak_native_fnc_squadSync;
        ["SUCCESS", format [["Indicatif %1 enregistré (téléphone et hors ligne)", "Indicatif %1 enregistré et envoyé à Athena"] select ([] call comspec_atak_native_fnc_bridge), _cs], 4, 30] call comspec_atak_native_fnc_notify;
        call _rerender;
        true
    };
    case "clear": {
        profileNamespace setVariable ["COMSPEC_ATAK_MyCallsign", ""];
        saveProfileNamespace;
        [""] call _cfgSave;
        [""] call _publish;
        ["INFO", "Indicatif personnalisé retiré : celui d'Athena ou de la mission s'applique", 4, 30] call comspec_atak_native_fnc_notify;
        call _rerender;
        true
    };
    case "web": {
        private _web = [_arg] call _clean;
        if (_web isEqualTo "") exitWith { false };
        private _seen = profileNamespace getVariable ["COMSPEC_ATAK_MyCallsignWeb", ""];
        profileNamespace setVariable ["COMSPEC_ATAK_MyCallsignWeb", _web];
        private _mine = profileNamespace getVariable ["COMSPEC_ATAK_MyCallsign", ""];
        // Changé sur le site depuis la dernière synchro : la fiche Athena gagne (gardée hors ligne pour la suite).
        if (_web isNotEqualTo _seen && {_seen isNotEqualTo ""} && {_web isNotEqualTo _mine}) then {
            profileNamespace setVariable ["COMSPEC_ATAK_MyCallsign", _web];
            [_web] call _cfgSave;
            [_web] call _publish;
            ["INFO", format ["Indicatif mis à jour depuis Athena : %1", _web], 4, 30] call comspec_atak_native_fnc_notify;
        };
        saveProfileNamespace;
        call _rerender;
        true
    };
    case "rows": {
        private _mine = profileNamespace getVariable ["COMSPEC_ATAK_MyCallsign", ""];
        private _cur = [player, true] call comspec_atak_native_fnc_unitCallsign;
        private _rows = [
            ["section", "Mon indicatif", ["Indicatif d'Athena ou de la mission", "Choisi sur ce téléphone, gardé hors ligne"] select (_mine isNotEqualTo "")],
            ["field", "myCallsign", "Indicatif (carte, BFT, groupes, Athena)", [_cur, _mine] select (_mine isNotEqualTo ""), "ENREGISTRER", { ["save", "myCallsign"] call comspec_atak_native_fnc_callsignAction; }, true]
        ];
        if (_mine isNotEqualTo "") then {
            _rows pushBack ["buttons", [["REVENIR À CELUI D'ATHENA", { ["clear"] call comspec_atak_native_fnc_callsignAction; }, false, true, "Retire l'indicatif choisi ici"]]];
        };
        _rows pushBack ["text", format ["<t size='0.8' color='#8a9a93'>%1</t>", [
            "Sans liaison Athena, l'indicatif reste sur ce téléphone et sera envoyé à la prochaine connexion.",
            "Envoyé à Athena avec votre escouade : il remplace l'indicatif de votre fiche. Un changement fait sur le site revient ici à la mission suivante."
        ] select ([] call comspec_atak_native_fnc_bridge)]];
        _rows
    };
    default { false };
};
