/*
    Sécurité du téléphone en cas de capture.
    - Exposition : joueur menotté (ACE), inconscient ou mort avec son téléphone : le téléphone devient un support
      extractible au câble DL-7 par l'ennemi (fn_dataSetup sur l'unité, thème « téléphone verrouillé ») : messages,
      marqueurs, comptes rendus, traces GPS. Code de verrouillage (profil COMSPEC_ATAK_LockCode) : mot de passe du
      support, 3 essais puis effacement, forçage possible en 2 min ; sans code, données lisibles directement.
      Retiré dès que le joueur est libéré ou se relève.
    - Effacement d'urgence : messages, marqueurs, photos, comptes rendus, traces, bibliothèque d'extraction et
      brouillons effacés ; le téléphone redémarre et reste « effacé » (rien à extraire) jusqu'à la resynchronisation.
    Params : [action]
      "wipe"   : effacement d'urgence (barre ACE de 4 s, annulable)     "wipeNow" : sans délai
      "tick"   : client, toutes les 2 s (exposition)                     "wiped"   : téléphone effacé ?
      "resync" : fin de l'état effacé (fn_resynchRun)                    "dump"    : fichiers qu'on trouverait
*/
params [["_act", "tick"]];
switch (_act) do {
    case "wiped": { missionNamespace getVariable ["COMSPEC_ATAK_Wiped", false] };
    case "resync": { missionNamespace setVariable ["COMSPEC_ATAK_Wiped", false]; player setVariable ["COMSPEC_ATAK_DataWiped", false, true]; true };
    case "wipe": {
        if (!isNil "ace_common_fnc_progressBar") exitWith {
            [4, [], { ["wipeNow"] call comspec_atak_native_fnc_phoneSecurity; }, { ["INFO", "Effacement annulé", 3, 20] call comspec_atak_native_fnc_notify; },
                "Effacement d'urgence du téléphone…", { alive player }, ["isNotSwimming"]] call ace_common_fnc_progressBar;
            true
        };
        ["wipeNow"] call comspec_atak_native_fnc_phoneSecurity
    };
    case "wipeNow": {
        private _data = uiNamespace getVariable ["COMSPEC_ATAK_Data", createHashMap];
        { _data set [_x, []]; } forEach ["messages", "inbox", "outbox", "p2p", "photos"];
        { _data set [_x, createHashMap]; } forEach ["markers", "remoteMarkers", "intel", "tasks"];
        { missionNamespace setVariable [_x, nil]; } forEach ["COMSPEC_ATAK_Reports", "COMSPEC_ATAK_GeoTracks", "COMSPEC_ATAK_MarkerNotes", "COMSPEC_ATAK_DataLib", "COMSPEC_ATAK_Route"];
        { uiNamespace setVariable [_x, nil]; } forEach ["COMSPEC_ATAK_PhotoLib", "COMSPEC_ATAK_ChatDraft", "COMSPEC_ATAK_Mesh"];
        { profileNamespace setVariable [_x, nil]; } forEach ["COMSPEC_ATAK_FrsDraft", "COMSPEC_ATAK_RecoDraft"];
        saveProfileNamespace;
        missionNamespace setVariable ["COMSPEC_ATAK_Wiped", true];
        player setVariable ["COMSPEC_ATAK_DataWiped", true, true];
        if (!isNil { player getVariable "COMSPEC_ATAK_Data" }) then { ["tick"] call comspec_atak_native_fnc_phoneSecurity; };
        playSound "addItemFailed";
        ["WARNING", "Téléphone effacé : plus rien d'exploitable. Resynchronisez pour retrouver les données du réseau.", 8, 80] call comspec_atak_native_fnc_notify;
        if (!isNull ([] call comspec_atak_native_fnc_display)) then { ["shutdown"] call comspec_atak_native_fnc_powerFx; };
        true
    };
    case "dump": {
        if (missionNamespace getVariable ["COMSPEC_ATAK_Wiped", false]) exitWith { [] };
        private _files = [];
        private _msgs = ([""] call comspec_atak_native_fnc_messagesAll) select [0, 25];
        if (_msgs isNotEqualTo []) then {
            _files pushBack ["messages.txt", "texte", (_msgs apply { format ["[%1] %2 : %3", _x get "time", _x get "author", _x get "body"] }) joinString endl, 0];
        };
        private _data = uiNamespace getVariable ["COMSPEC_ATAK_Data", createHashMap];
        {
            private _p = _y getOrDefault ["position", []];
            if (_p isEqualType [] && {(count _p) >= 2}) then {
                _files pushBack [format ["marqueur_%1", _forEachIndex + 1], "coord", format ["%1 %2", mapGridPosition _p, _y getOrDefault ["text", ""]], 1];
            };
        } forEach (_data getOrDefault ["markers", createHashMap]);
        private _rep = values (missionNamespace getVariable ["COMSPEC_ATAK_Reports", createHashMap]);
        if (_rep isNotEqualTo []) then { _files pushBack ["comptes_rendus.doc", "document", format ["%1 compte(s) rendu(s) enregistrés.", count _rep], 0]; };
        _files pushBack ["position.gps", "coord", mapGridPosition player, 1];
        _files select [0, 40]
    };
    case "tick": {
        if (!hasInterface || {isNull player}) exitWith {};
        // Téléphone porté (pas le terminal d'un véhicule) : un objet du catalogue sur soi.
        private _cat = [] call comspec_atak_native_fnc_deviceCatalog;
        private _held = (((assignedItems player) + (items player)) findIf { (toLower _x) in _cat }) >= 0;
        private _down = !alive player || {player getVariable ["ace_captives_isHandcuffed", false]} || {player getVariable ["ACE_isUnconscious", false]} || {lifeState player isEqualTo "INCAPACITATED"};
        private _has = !isNil { player getVariable "COMSPEC_ATAK_Data" };
        if (_held && {_down}) then {
            if (_has && {(player getVariable ["COMSPEC_ATAK_ExposedSig", ""]) isEqualTo str (missionNamespace getVariable ["COMSPEC_ATAK_Wiped", false])}) exitWith {};
            private _code = profileNamespace getVariable ["COMSPEC_ATAK_LockCode", ""];
            private _imei = ([player] call comspec_atak_native_fnc_phoneIdent) param [1, ""];
            [player, [
                ["name", format ["Téléphone ATAK %1", _imei]], ["kind", "telephone"], ["theme", "telephone"], ["banner", "COMSPEC ATAK"],
                ["password", [_code, "-"] select (_code isEqualTo "")], ["hint", ""], ["attempts", 3], ["onFail", "wipe"], ["crack", true], ["crackTime", 120],
                ["duration", 30], ["port", "usb"], ["files", ["dump"] call comspec_atak_native_fnc_phoneSecurity]
            ]] call comspec_atak_native_fnc_dataSetup;
            if (missionNamespace getVariable ["COMSPEC_ATAK_Wiped", false]) then { player setVariable ["COMSPEC_ATAK_DataWiped", true, true]; };
            player setVariable ["COMSPEC_ATAK_ExposedSig", str (missionNamespace getVariable ["COMSPEC_ATAK_Wiped", false])];
        } else {
            if (_has && {alive player}) then {
                player setVariable ["COMSPEC_ATAK_Data", nil, true];
                player setVariable ["COMSPEC_ATAK_ExposedSig", nil];
                ["comspec_atak_native_dataSrv", ["remove", player, player, []]] call CBA_fnc_serverEvent;
            };
        };
    };
    default { false };
};
