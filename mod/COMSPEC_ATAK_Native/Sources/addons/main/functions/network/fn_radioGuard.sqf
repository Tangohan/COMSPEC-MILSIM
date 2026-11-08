/*
    Garde-fous entre l'ATAK et les mods radio ACRE2 et TFAR : notre mod ne doit jamais gêner leur voix. Le réseau ajoute, il ne retire rien.
    Seule porte vers leurs fonctions : "api" teste l'existence de la fonction avant de l'appeler (aucun appel direct ailleurs, testé par AtakRadioGuardTest).
    Coupe-circuit : SQF n'a pas de try/catch pour une erreur de script. Les chemins appelés par ACRE2 ou TFAR comptent leurs entrées et leurs sorties :
    une entrée sans sortie est une erreur (compteurs lus hors de leur chemin, par "watch"). Au bout de 3 erreurs ou valeurs invalides, la couche voix se coupe pour la session.
    Réglage serveur « Couper tout lien avec la radio » (comspec_atak_native_radio_cut) ou commande admin (COMSPEC_ATAK_RadioCut) : même effet, tout de suite, sans redémarrage.
    Versions connues : ACRE2 2.14 (lu dans le code), TFAR 1.x (lu dans le dépôt, jamais joué). Autre version ou fonction absente : couche voix coupée, le reste du mod continue.
      "api" [nom, arguments, valeur si absente] -> résultat ; "has" [nom] -> vrai si la fonction existe
      "read"  -> vrai si le mod peut lire les radios (faux une fois tout lien coupé)
      "voice" -> vrai si la couche voix peut agir (lien, version connue, pas de coupe-circuit)
      "compat" -> [ACRE2 : "absent" | "ok" | "inconnu", TFAR : idem, version ACRE2, version TFAR] ; "boot" : test au démarrage et ligne au RPT
      "known" [version, préfixe] -> vrai si connue (sans effet, testé par fn_devMeshTest)
      "watch" : lit les compteurs (2 s) ; "bad" : valeur invalide reçue dans un chemin radio ; "trip" [raison] : coupe-circuit
      "cut" [vrai | faux] : commande admin, vérifiée par le serveur ; "applyCut" : effet local ; "init" : événements ; "text" -> état lisible
*/
params [["_act", ""], ["_arg", []]];
switch (_act) do {
    case "has": { !isNil {missionNamespace getVariable (_arg param [0, ""])} };
    case "api": {
        _arg params [["_name", ""], ["_args", []], ["_def", false]];
        private _f = missionNamespace getVariable _name;
        if (isNil "_f" || {!(_f isEqualType {})}) exitWith { _def };
        private _r = _args call _f;
        if (isNil "_r") exitWith { _def };
        _r
    };
    case "read": {
        (missionNamespace getVariable ["comspec_atak_native_radio_cut", false]) isNotEqualTo true && {(missionNamespace getVariable ["COMSPEC_ATAK_RadioCut", false]) isNotEqualTo true}
    };
    case "voice": {
        if !(["read"] call comspec_atak_native_fnc_radioGuard) exitWith { false };
        if ((missionNamespace getVariable ["COMSPEC_ATAK_VoiceTrip", ""]) isNotEqualTo "") exitWith { false };
        private _c = ["compat"] call comspec_atak_native_fnc_radioGuard;
        "ok" in [_c select 0, _c select 1]
    };
    case "known": {
        _arg params [["_v", ""], ["_p", ""]];
        (_v find _p) isEqualTo 0
    };
    case "compat": {
        private _c = missionNamespace getVariable "COMSPEC_ATAK_RadioCompat";
        if (!isNil "_c") exitWith { _c };
        private _ver = {
            params ["_patch"];
            private _s = getText (configFile >> "CfgPatches" >> _patch >> "versionStr");
            if (_s isEqualTo "") then { _s = getText (configFile >> "CfgPatches" >> _patch >> "version"); };
            _s
        };
        private _allOf = { (_this findIf { isNil {missionNamespace getVariable _x} }) < 0 };
        private _va = ["acre_main"] call _ver;
        private _a = if !(isClass (configFile >> "CfgPatches" >> "acre_main")) then { "absent" } else {
            [["inconnu", "ok"] select (["acre_api_fnc_setCustomSignalFunc", "acre_sys_signal_fnc_getSignalCore", "acre_api_fnc_getCurrentRadioList", "acre_api_fnc_getBaseRadio"] call _allOf), "inconnu"] select !(["known", [_va, "2.14."]] call comspec_atak_native_fnc_radioGuard)
        };
        private _vt = ["tfar_core"] call _ver;
        private _t = if !(isClass (configFile >> "CfgPatches" >> "tfar_core")) then {
            ["absent", "inconnu"] select (isClass (configFile >> "CfgPatches" >> "task_force_radio"))
        } else {
            [["inconnu", "ok"] select (["TFAR_fnc_addEventHandler", "TFAR_fnc_activeSwRadio", "TFAR_fnc_activeLrRadio", "TFAR_fnc_radiosList", "TFAR_fnc_getSwFrequency", "TFAR_fnc_getLrFrequency"] call _allOf), "inconnu"] select !(["known", [_vt, "1."]] call comspec_atak_native_fnc_radioGuard)
        };
        _c = [_a, _t, _va, _vt];
        missionNamespace setVariable ["COMSPEC_ATAK_RadioCompat", _c];
        _c
    };
    case "boot": {
        (["compat"] call comspec_atak_native_fnc_radioGuard) params ["_a", "_t", "_va", "_vt"];
        diag_log format ["[COMSPEC ATAK NATIVE][INFO][RADIO] Compatibilité voix : ACRE2 %1 (%2), TFAR %3 (%4)", _a, _va, _t, _vt];
        { if (_x isEqualTo "inconnu") then { diag_log format ["[COMSPEC ATAK NATIVE][WARN][RADIO] %1 : version inconnue ou fonction absente. Couche voix désactivée, le reste du mod continue, la radio reste normale.", ["ACRE2", "TFAR"] select _forEachIndex]; }; } forEach [_a, _t];
    };
    case "watch": {
        private _in = missionNamespace getVariable ["COMSPEC_ATAK_GuardIn", 0];
        private _out = missionNamespace getVariable ["COMSPEC_ATAK_GuardOut", 0];
        private _bad = missionNamespace getVariable ["COMSPEC_ATAK_GuardBad", 0];
        // Un seul écart peut être un appel en cours : 3 écarts sont des sorties perdues.
        if ((_in - _out) >= 3) exitWith { ["trip", [format ["%1 erreur(s) de script dans le chemin radio", _in - _out]]] call comspec_atak_native_fnc_radioGuard; };
        if (_bad >= 3) then { ["trip", [format ["%1 valeur(s) invalide(s) dans le chemin radio", _bad]]] call comspec_atak_native_fnc_radioGuard; };
    };
    case "bad": {
        missionNamespace setVariable ["COMSPEC_ATAK_GuardBad", (missionNamespace getVariable ["COMSPEC_ATAK_GuardBad", 0]) + 1];
    };
    case "trip": {
        if ((missionNamespace getVariable ["COMSPEC_ATAK_VoiceTrip", ""]) isNotEqualTo "") exitWith {};
        private _why = _arg param [0, "cause inconnue"];
        missionNamespace setVariable ["COMSPEC_ATAK_VoiceTrip", _why];
        diag_log format ["[COMSPEC ATAK NATIVE][WARN][RADIO] Coupe-circuit : couche voix désactivée pour la session (%1). Fonction de signal et multiplicateurs rendus. La radio reste normale.", _why];
        ["release", ["coupe-circuit"]] call comspec_atak_native_fnc_mpuVoice;
        ["comspec_atak_native_radioTrip", [name player, _why]] call CBA_fnc_serverEvent;
    };
    case "cut": {
        // Client : la demande part au serveur, seul juge (fn_adminAllowed).
        ["comspec_atak_native_radioCutSrv", [player, _arg param [0, true]]] call CBA_fnc_serverEvent;
    };
    case "applyCut": {
        if (["read"] call comspec_atak_native_fnc_radioGuard) exitWith {};
        ["release", ["tout lien radio coupé"]] call comspec_atak_native_fnc_mpuVoice;
    };
    case "init": {
        if (isServer) then {
            ["comspec_atak_native_radioTrip", {
                params [["_who", ""], ["_why", ""]];
                // Une alerte par minute au plus : tous les clients peuvent tomber en même temps.
                if (diag_tickTime - (missionNamespace getVariable ["COMSPEC_ATAK_RadioTripAt", -100]) < 60) exitWith {};
                missionNamespace setVariable ["COMSPEC_ATAK_RadioTripAt", diag_tickTime];
                diag_log format ["[COMSPEC ATAK NATIVE][WARN][RADIO] Coupe-circuit chez %1 : %2", _who, _why];
                private _adm = allPlayers select { [_x] call comspec_atak_native_fnc_adminAllowed };
                if (_adm isNotEqualTo []) then { ["comspec_atak_native_radioTripMsg", [_who, _why], _adm] call CBA_fnc_targetEvent; };
            }] call CBA_fnc_addEventHandler;
            ["comspec_atak_native_radioCutSrv", {
                params [["_who", objNull], ["_on", true]];
                if (isNull _who || {!([_who] call comspec_atak_native_fnc_adminAllowed)}) exitWith {
                    diag_log format ["[COMSPEC ATAK NATIVE][WARN][RADIO] Coupure du lien radio refusée : %1", [name _who, "?"] select (isNull _who)];
                    if (!isNull _who) then { ["comspec_atak_native_radioTripMsg", ["", "denied"], _who] call CBA_fnc_targetEvent; };
                };
                missionNamespace setVariable ["COMSPEC_ATAK_RadioCut", _on isEqualTo true, true];
                diag_log format ["[COMSPEC ATAK NATIVE][WARN][RADIO] Lien radio %1 par %2", ["rétabli", "coupé"] select (_on isEqualTo true), name _who];
                ["comspec_atak_native_radioCutNow", []] call CBA_fnc_globalEvent;
            }] call CBA_fnc_addEventHandler;
        };
        if (!hasInterface) exitWith {};
        ["boot"] call comspec_atak_native_fnc_radioGuard;
        ["comspec_atak_native_radioCutNow", { [{ ["applyCut"] call comspec_atak_native_fnc_radioGuard; }] call comspec_atak_native_fnc_nextFrame; }] call CBA_fnc_addEventHandler;
        ["comspec_atak_native_radioTripMsg", {
            params [["_who", ""], ["_why", ""]];
            if (_why isEqualTo "denied") exitWith { ["WARNING", "Refusé : seuls un admin, un Zeus ou un gestionnaire désigné peuvent couper le lien radio", 6, 40] call comspec_atak_native_fnc_notify; };
            ["WARNING", format ["Coupe-circuit radio chez %1 : voix relayée coupée, radio normale (%2)", _who, _why], 8, 40] call comspec_atak_native_fnc_notify;
        }] call CBA_fnc_addEventHandler;
        // Restauration : mort, changement d'unité (respawn, contrôle à distance), fin de mission, déconnexion.
        ["CAManBase", "Killed", {
            params ["_u"];
            if (_u isEqualTo player) then { ["release", ["mort"]] call comspec_atak_native_fnc_mpuVoice; };
        }] call CBA_fnc_addClassEventHandler;
        ["unit", { ["release", ["changement d'unité"]] call comspec_atak_native_fnc_mpuVoice; }] call CBA_fnc_addPlayerEventHandler;
        addMissionEventHandler ["Ended", { ["release", ["fin de mission"]] call comspec_atak_native_fnc_mpuVoice; }];
        addMissionEventHandler ["MPEnded", { ["release", ["fin de mission"]] call comspec_atak_native_fnc_mpuVoice; }];
        missionNamespace setVariable ["COMSPEC_ATAK_RadioHook", {
            if (isNull (findDisplay 46)) exitWith { [missionNamespace getVariable "COMSPEC_ATAK_RadioHook", [], 5] call comspec_atak_native_fnc_waitAndExec; };
            (findDisplay 46) displayAddEventHandler ["Unload", { ["release", ["déconnexion"]] call comspec_atak_native_fnc_mpuVoice; }];
        }];
        [missionNamespace getVariable "COMSPEC_ATAK_RadioHook", [], 5] call comspec_atak_native_fnc_waitAndExec;
    };
    case "text": {
        (["compat"] call comspec_atak_native_fnc_radioGuard) params ["_a", "_t", "_va", "_vt"];
        private _trip = missionNamespace getVariable ["COMSPEC_ATAK_VoiceTrip", ""];
        switch (true) do {
            case !(["read"] call comspec_atak_native_fnc_radioGuard): { "Tout lien avec la radio est coupé" };
            case (_trip isNotEqualTo ""): { format ["Coupe-circuit déclenché : %1", _trip] };
            case (_a isEqualTo "inconnu" || {_t isEqualTo "inconnu"}): { format ["Version radio inconnue (ACRE2 %1, TFAR %2) : voix relayée désactivée", _va, _vt] };
            default { format ["Lien radio normal (ACRE2 %1, TFAR %2)", ["absent", _va] select (_a isEqualTo "ok"), ["absent", _vt] select (_t isEqualTo "ok")] };
        }
    };
};
