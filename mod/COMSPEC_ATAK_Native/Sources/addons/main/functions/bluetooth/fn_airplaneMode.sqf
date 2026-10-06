/*
    Mode avion du téléphone. Params : [action] ; "" (défaut) : renvoie l'état, "toggle", true, false.
    Mode avion : plus aucun réseau (fn_linkQuality renvoie 0 barre, libellé « Mode avion ») : pas de synchro Athena,
    SMS et envois mis en file comme sans signal, balise BFT hors ligne, icône d'avion à la place des barres.
    Le Bluetooth (fn_btAction) reste indépendant, comme sur un vrai téléphone.
    État : missionNamespace COMSPEC_ATAK_Airplane (pour la mission) ; publié sur l'unité (COMSPEC_ATAK_Airplane).
    Renvoie l'état (booléen).
*/
params [["_act", ""]];
private _on = missionNamespace getVariable ["COMSPEC_ATAK_Airplane", false];
if (_act isEqualTo "") exitWith { _on };
private _new = if (_act isEqualType true) then { _act } else { !_on };
if (_new isEqualTo _on) exitWith { _on };
missionNamespace setVariable ["COMSPEC_ATAK_Airplane", _new];
player setVariable ["COMSPEC_ATAK_Airplane", _new, true];
// Le débit simulé est mis en cache 2 s : on le recalcule tout de suite.
uiNamespace setVariable ["COMSPEC_ATAK_LinkQ", []];
if (_new) then {
    ["INFO", "Mode avion activé : réseau coupé, SMS et envois en attente (le Bluetooth reste utilisable)", 4, 30] call comspec_atak_native_fnc_notify;
} else {
    ["SUCCESS", "Mode avion désactivé : recherche du réseau…", 3, 30] call comspec_atak_native_fnc_notify;
};
if (!isNull ([] call comspec_atak_native_fnc_display)) then {
    [] call comspec_atak_native_fnc_statusUpdate;
    private _page = (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""];
    if (_page in ["SETTINGS", "BLUETOOTH", "NOTIFS", "NETWORK", "STATUS"]) then { [{ [_this] call comspec_atak_native_fnc_pageRender; }, _page] call CBA_fnc_execNextFrame; };
};
_new
