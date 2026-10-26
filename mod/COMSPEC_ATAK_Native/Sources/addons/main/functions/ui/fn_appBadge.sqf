/* Nombre d'éléments non lus pour une page (0 = pas de pastille). */
params [["_page", ""]];
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _data = uiNamespace getVariable ["COMSPEC_ATAK_Data", createHashMap];
switch (_page) do {
    // Canaux et SMS non lus (fn_chatUnread : mêmes chiffres que les pastilles de la liste des conversations).
    case "CHAT": { (["counts"] call comspec_atak_native_fnc_chatUnread) getOrDefault ["_total", 0] };
    case "TASK": {
        // DELIVERED = transmis (« vu » par Athena), pas encore traité par le joueur.
        private _done = ["ACK", "EXEC", "FAILED", "DONE", "COMPLETED", "CANCELLED", "CANCELED"];
        {!((toUpper (_x getOrDefault ["status", ""])) in _done)} count (values ([] call comspec_atak_native_fnc_tasksAll))
    };
    // Tinder : likes (tuyaux des informateurs) pas encore traités.
    case "DATING": { ["unread"] call comspec_atak_native_fnc_hvtIntel };
    default { 0 };
}
