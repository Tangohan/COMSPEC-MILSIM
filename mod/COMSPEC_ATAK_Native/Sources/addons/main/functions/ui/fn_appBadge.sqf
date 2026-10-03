/* Nombre d'éléments non lus pour une page (0 = pas de pastille). */
params [["_page", ""]];
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _data = uiNamespace getVariable ["COMSPEC_ATAK_Data", createHashMap];
switch (_page) do {
    case "CHAT": {
        private _athena = ((count ([] call comspec_atak_native_fnc_messagesAll)) - (_s getOrDefault ["seenAthena", 0])) max 0;
        private _p2p = {(_x getOrDefault ["dir", ""]) isEqualTo "in" && {!(_x getOrDefault ["read", false])}} count (_data getOrDefault ["p2p", []]);
        _athena + _p2p
    };
    case "TASK": {
        private _done = ["ACK", "EXEC", "DELIVERED", "FAILED", "DONE", "COMPLETED", "CANCELLED"];
        {!((toUpper (_x getOrDefault ["status", ""])) in _done)} count (values ([] call comspec_atak_native_fnc_tasksAll))
    };
    default { 0 };
}
