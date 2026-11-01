/*
    Sauvegarde les tenues ACE Arsenal locales vers Athena. À appeler depuis un spawn (extAwait : rien ne fige).
    params: [filterNames]  tableau de noms — vide = toutes. Noms comparés comme arsenalAceStore (« key »).
    Registre de synchro : [empreinte du texte envoyé (= payload_sha256 du site), empreinte du contenu (arsenalHash)].
    Le verrou est pris d'un bloc juste avant l'envoi : deux clics ne font qu'un envoi (3 min : envoi interrompu, ignoré).
*/
params [["_filterNames", [], [[]]]];

if (!hasInterface) exitWith { false };

if (!(missionNamespace getVariable ["COMSPEC_AthenaReady", false])) exitWith {
    ["Compte Athena non relié — les tenues ne partent pas.", "arsenal", "warn", true] call comspec_overwatch_connect_fnc_announce;
    false
};


private _sendingAll = _filterNames isEqualTo [];
private _last = missionNamespace getVariable ["COMSPEC_ArsenalLastPushAt", -1e9];
if (_sendingAll && {(_last isEqualType 0) && {(diag_tickTime - _last) < 8}}) exitWith {
    ["Les tenues viennent d’être envoyées — patientez un instant.", "arsenal", "info", true] call comspec_overwatch_connect_fnc_announce;
    false
};

private _tx = [false] call comspec_overwatch_connect_fnc_canTransmit;
if (!(_tx getOrDefault ["can_transmit", false])) exitWith {
    ["Liaison indisponible — les tenues ne sont pas mises en attente.", "arsenal", "warn", true] call comspec_overwatch_connect_fnc_announce;
    false
};

private _entries = [] call comspec_overwatch_connect_fnc_arsenalLocalLoadouts;
if (_filterNames isNotEqualTo []) then {
    private _want = _filterNames apply { ["key", _x] call comspec_overwatch_connect_fnc_arsenalAceStore };
    _entries = _entries select { (["key", _x select 0] call comspec_overwatch_connect_fnc_arsenalAceStore) in _want };
};

if (_entries isEqualTo []) exitWith {
    [
        if (_sendingAll) then { "Aucune tenue locale à envoyer." } else { "Cette tenue n’est pas dans votre arsenal." },
        "arsenal",
        "info",
        true
    ] call comspec_overwatch_connect_fnc_announce;
    false
};

private _busy = false;
isNil {
    private _t = missionNamespace getVariable ["COMSPEC_ArsenalPushBusy", -1];
    _busy = (_t isEqualType 0) && {_t >= 0} && {(diag_tickTime - _t) < 180};
    if (!_busy) then { missionNamespace setVariable ["COMSPEC_ArsenalPushBusy", diag_tickTime, false]; };
};
if (_busy) exitWith {
    ["Envoi des tenues déjà en cours, patientez.", "arsenal", "info", true] call comspec_overwatch_connect_fnc_announce;
    false
};

private _ok = 0;
private _fail = 0;
// Objets des tenues envoyées (nom affiché en jeu, mod) : le site les montre sous leur vrai nom, avec leur icône.
private _itemCatalog = [];
private _itemSeen = createHashMap;
{
    _x params ["_name", "_data"];
    private _loadout = [_data] call comspec_overwatch_connect_fnc_arsenalNormalizeLoadout;
    if (_loadout isEqualTo []) then {
        _fail = _fail + 1;
        continue;
    };
    private _payload = str _loadout;
    private _parsed = ["SyncWardrobe", [_name, _payload], "SyncWardrobe", false, false, "arsenal", false]
        call comspec_overwatch_connect_fnc_callExtLogged;
    _parsed params [["_success", false], ["_status", ""], ["_detail", ""]];
    if (_success || {_status isEqualTo "QUEUED"}) then {
        _ok = _ok + 1;
        // Registre de synchro (panneau Tenues Athena) : ce texte est maintenant celui du site.
        ["set", _name, [_payload, true] call comspec_overwatch_connect_fnc_arsenalHash, [_loadout] call comspec_overwatch_connect_fnc_arsenalHash] call comspec_overwatch_connect_fnc_arsenalLedger;
        if ((count _itemCatalog) < 400) then {
            private _lo = _loadout;
            {
                private _c = toLower (_x getOrDefault ["class", ""]);
                if (_c isNotEqualTo "" && {!(_c in _itemSeen)}) then { _itemSeen set [_c, true]; _itemCatalog pushBack _x; };
            } forEach ([_lo] call comspec_overwatch_connect_fnc_collectItemCatalog);
        };
    } else {
        _fail = _fail + 1;
        ["DEBUG", "ARSENAL", format ["Sync fail %1 → %2 %3", _name, _status, _detail]] call comspec_overwatch_connect_fnc_log;
    };
} forEach _entries;

if (_itemCatalog isNotEqualTo []) then {
    _itemCatalog = _itemCatalog select [0, 400];
    // Noms seuls (aucune tenue dans ce lot) : le site ne compte pas d'usage pour ces objets. DLL sans la
    // commande : réponse vide, rien d'autre ne change.
    private _json = [createHashMapFromArray [["wardrobes", []], ["item_catalog", _itemCatalog]]] call comspec_overwatch_connect_fnc_jsonValue;
    ["SyncWardrobesBatch", [_json], "ItemNames", false, false, "arsenal", false] call comspec_overwatch_connect_fnc_callExtLogged;
    [_itemCatalog] call comspec_overwatch_connect_fnc_sendItemIcons;
};

missionNamespace setVariable ["COMSPEC_ArsenalPushBusy", -1, false];
if (_ok > 0) then { [] call comspec_overwatch_connect_fnc_saveProfile; };
if (_sendingAll) then {
    missionNamespace setVariable ["COMSPEC_ArsenalLastPushAt", diag_tickTime, false];
};

private _msg = format ["Tenues Athena : %1 enregistrée(s)%2.", _ok, if (_fail > 0) then { format [", %1 échec(s)", _fail] } else { "" }];
[_msg, "arsenal", if (_fail > 0 && {_ok < 1}) then { "warn" } else { "ok" }, true] call comspec_overwatch_connect_fnc_announce;

_ok > 0
