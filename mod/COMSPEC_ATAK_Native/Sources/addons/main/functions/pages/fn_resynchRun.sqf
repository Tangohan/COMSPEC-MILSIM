/*
    App Resynch : renvoie toutes les données du terminal vers Athena, puis rafraîchit les données affichées par le téléphone.
      - COMSPEC Link (forceSyncData) : fiche opérateur complète (identité, visage, groupe sanguin, tenue et kit porté,
        catalogue des objets, mods, icônes manquantes au site), position, temps de mission, marqueurs posés, comptes
        rendus en attente, effectif du groupe, derniers messages ;
      - téléphone natif : marqueurs de la carte (relais du camp), plan de briefing, escouades et équipes, montre Garmin,
        tenues ACE Arsenal modifiées depuis leur dernier envoi, état de la file d'envoi.
    Rien n'est renvoyé deux fois pour rien : les tenues jamais partagées restent sur ce PC (le partage se fait depuis
    Tenues Athena), les photos déjà transmises ne repartent pas, la file d'envoi garde son ordre.
    Compte rendu : missionNamespace COMSPEC_LastResynchItems = [[clé, état, titre, détail]...] (voir fn_pageResynch).
*/
if (!hasInterface) exitWith { false };
if (missionNamespace getVariable ["COMSPEC_ATAK_ResynchBusy", false]) exitWith { false };
// Téléphone effacé en urgence (fn_phoneSecurity) : la resynchronisation le remet en service.
["resync"] call comspec_atak_native_fnc_phoneSecurity;
if (isNil "comspec_overwatch_connect_fnc_forceSyncData") exitWith {
    missionNamespace setVariable ["COMSPEC_LastResynchItems", [["link", "off", "COMSPEC Link", "Pas chargé : le Resynch est indisponible sur ce PC."]], false];
    false
};
missionNamespace setVariable ["COMSPEC_ATAK_ResynchBusy", true, false];
[{ ["RESYNCH"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
[] spawn {
    private _at0 = missionNamespace getVariable ["COMSPEC_ForceSyncAt", -1e9];
    [] call comspec_overwatch_connect_fnc_forceSyncData;
    // Délai d'attente entre deux Resynch (15 s) : rien n'est parti, le compte rendu précédent reste.
    private _ran = (missionNamespace getVariable ["COMSPEC_ForceSyncAt", -1e9]) isNotEqualTo _at0;
    private _items = +(missionNamespace getVariable ["COMSPEC_LastResynchItems", []]);
    if (_ran && {missionNamespace getVariable ["COMSPEC_AthenaReady", false]}) then {
        private _add = { _items pushBack _this; };
        private _bridge = [] call comspec_atak_native_fnc_bridge;

        // Marqueurs de la carte (y compris ceux de la mission et de la carte Arma) : un seul relais par camp.
        private _relay = missionNamespace getVariable [format ["COMSPEC_ATAK_MkRelay_%1", side group player], objNull];
        if (isNull _relay || {_relay isEqualTo player} || {!alive _relay}) then {
            uiNamespace setVariable ["COMSPEC_ATAK_MkSnap", createHashMap];
            [] call comspec_atak_native_fnc_markerWebSweep;
            private _n = count (uiNamespace getVariable ["COMSPEC_ATAK_MkSnap", createHashMap]);
            ["mapall", ["same", "ok"] select (_n > 0), "Carte du camp", [
                "Aucun marqueur sur la carte.",
                format ["%1 marqueur(s) renvoyé(s)%2.", _n, ["", ", la suite part toutes les 15 s"] select (_n >= 40)]
            ] select (_n > 0)] call _add;
        } else {
            ["mapall", "same", "Carte du camp", format ["Relayée pour tout le camp par %1.", name _relay]] call _add;
        };

        // Plan de briefing, escouades et équipes : envoi forcé (réponse lue un peu plus bas).
        private _planSent = ["force"] call comspec_atak_native_fnc_planWeb;
        private _squadSent = [true] call comspec_atak_native_fnc_squadSync;

        // Montre Garmin : profil écrit et renvoyé (sans clore l'activité en cours).
        private _gmOn = !(missionNamespace getVariable ["comspec_atak_native_garmin_watch", true])
            || {((assignedItems player) findIf { _x isKindOf ["COMSPEC_ATAK_GarminWatch", configFile >> "CfgWeapons"] }) >= 0}
            || {((items player) findIf { _x isKindOf ["COMSPEC_ATAK_GarminWatchItem", configFile >> "CfgWeapons"] }) >= 0};
        if (_gmOn && {_bridge}) then {
            (["push"] call comspec_atak_native_fnc_garminTick) params [["_t", -1], ["_st", ""]];
            ["garmin", ["warn", "ok"] select (_st isEqualTo "OK"), "Montre Garmin", [format ["Pas partie : %1.", _st], "Pas, activités, cardio, stress et Body Battery."] select (_st isEqualTo "OK")] call _add;
        } else {
            ["garmin", "off", "Montre Garmin", ["COMSPEC Link absent.", "Pas de montre portée : rien à envoyer."] select _bridge] call _add;
        };

        // Tenues ACE Arsenal : seulement celles déjà sur le site et modifiées ici depuis leur dernier envoi.
        if (!isNil "comspec_overwatch_connect_fnc_arsenalPushAll" && {!isNil "comspec_overwatch_connect_fnc_arsenalLedger"}) then {
            private _ledger = ["map"] call comspec_overwatch_connect_fnc_arsenalLedger;
            private _changed = [];
            private _never = 0;
            {
                _x params ["_name", "_data"];
                private _known = _ledger getOrDefault [toLower trim _name, []];
                if (_known isEqualTo []) then { _never = _never + 1; continue };
                private _sha = [str ([_data] call comspec_overwatch_connect_fnc_arsenalNormalizeLoadout)] call comspec_overwatch_connect_fnc_arsenalHash;
                if (_sha isNotEqualTo "" && {_sha isNotEqualTo (_known param [1, ""])}) then { _changed pushBack _name; };
            } forEach ([] call comspec_overwatch_connect_fnc_arsenalLocalLoadouts);
            private _sent = _changed isNotEqualTo [] && {[_changed] call comspec_overwatch_connect_fnc_arsenalPushAll};
            private _rest = ["", format [" %1 tenue(s) jamais partagée(s) restent sur ce PC (Tenues Athena pour les envoyer).", _never]] select (_never > 0);
            ["wardrobe", [["same", "warn"] select (_changed isNotEqualTo []), "ok"] select _sent, "Tenues ACE Arsenal", [
                format ["Celles du site sont à jour.%1", _rest],
                format ["%1 tenue(s) modifiée(s) ici %2.%3", count _changed, ["n'ont pas pu partir", "renvoyée(s)"] select _sent, _rest]
            ] select (_changed isNotEqualTo [])] call _add;
        };

        uiSleep 1.5;
        (missionNamespace getVariable ["COMSPEC_ATAK_PlanWebLast", []]) params [["_pt", -1], ["_pst", ""], ["_prev", 0], ["_pn", 0]];
        ["plan", [["same", "warn"] select _planSent, "ok"] select (_planSent && {_pst isEqualTo "plan envoyé"}), "Plan de briefing", switch (true) do {
            case (!_planSent): { "Pas de plan partagé dans cette mission." };
            case (_pst isEqualTo "plan envoyé"): { format ["Révision %1, %2 élément(s).", _prev, _pn] };
            default { format ["Pas parti : %1.", _pst] };
        }] call _add;
        (missionNamespace getVariable ["COMSPEC_ATAK_SquadSyncLast", []]) params [["_st", -1], ["_sst", ""], ["_sn", 0]];
        ["squads", [["same", "warn"] select _squadSent, "ok"] select (_squadSent && {_sst isEqualTo "OK"}), "Escouades et équipes", switch (true) do {
            case (!_squadSent): { "Rien à renvoyer." };
            case (_sst isEqualTo "OK"): { format ["%1 équipe(s) de feu et leurs rôles.", _sn] };
            default { format ["Pas parti : %1.", toLower _sst] };
        }] call _add;
    };

    // File d'envoi : ce qui attend le réseau sur le téléphone (débit simulé) et chez COMSPEC Link.
    private _q = localNamespace getVariable ["COMSPEC_ATAK_NetQueue", []];
    private _photos = { (toLower (_x param [2, ""])) find "photo" >= 0 } count _q;
    private _dll = 0;
    if ([] call comspec_atak_native_fnc_bridge && {!(missionNamespace getVariable ["COMSPEC_ATAK_SendQNo", false])}) then {
        private _raw = ("COMSPECExtension" callExtension ["SendQueue.Status", []]) select 0;
        if ((_raw select [0, 3]) isEqualTo "OK|") then { _dll = parseNumber (((((_raw select [3]) splitString toString [10]) param [0, ""]) splitString toString [9]) param [0, "0"]); };
    };
    private _wait = (count _q) + _dll;
    if (_ran) then {
        _items pushBack ["queue", ["same", "wait"] select (_wait > 0), "File d'envoi", [
            "Vide : rien n'attend le réseau.",
            format ["%1 envoi(s) en attente%2 : ils partent dès que le réseau le permet, dans l'ordre.", _wait, ["", format [" dont %1 photo(s)", _photos]] select (_photos > 0)]
        ] select (_wait > 0)];
        missionNamespace setVariable ["COMSPEC_LastResynchItems", _items, false];
        missionNamespace setVariable ["COMSPEC_ATAK_ResynchAt", [dayTime, "HH:MM:SS"] call BIS_fnc_timeToString, false];
        missionNamespace setVariable ["COMSPEC_ATAK_ResynchTick", diag_tickTime, false];
    };
    [] call comspec_atak_native_fnc_localDataRefresh;
    missionNamespace setVariable ["COMSPEC_ATAK_ResynchBusy", false, false];
    [{
        if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "RESYNCH") then { ["RESYNCH"] call comspec_atak_native_fnc_pageRender; };
    }] call CBA_fnc_execNextFrame;
};
true
