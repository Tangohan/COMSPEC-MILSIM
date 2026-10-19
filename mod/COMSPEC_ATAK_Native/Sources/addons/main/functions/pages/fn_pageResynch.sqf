/*
    App Resynch : comme l'app du même nom de l'ancien ATAK. Renvoie tout vers Athena (fn_resynchRun) et affiche le
    compte rendu par catégorie : icône à la couleur de l'état, ce qui est parti, heure du renvoi ; bouton pour relancer.
    À l'ouverture, renvoi automatique seulement si le dernier date de plus de 5 min (sinon le compte rendu reste affiché :
    rouvrir l'app ne renvoie pas tout une nouvelle fois).
    Compte rendu : missionNamespace COMSPEC_LastResynchItems = [[clé, état, titre, détail]...], état "ok" | "same" |
    "wait" | "warn" | "off".
*/
disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _lastTick = missionNamespace getVariable ["COMSPEC_ATAK_ResynchTick", -1e9];
if ((_s getOrDefault ["resynchAuto", ""]) isNotEqualTo (str (_s getOrDefault ["display", displayNull]))) then {
    _s set ["resynchAuto", str (_s getOrDefault ["display", displayNull])];
    if ((diag_tickTime - _lastTick) > 300) then { [{ [] call comspec_atak_native_fnc_resynchRun; }] call CBA_fnc_execNextFrame; };
};
private _busy = missionNamespace getVariable ["COMSPEC_ATAK_ResynchBusy", false];
private _at = missionNamespace getVariable ["COMSPEC_ATAK_ResynchAt", ""];
private _items = missionNamespace getVariable ["COMSPEC_LastResynchItems", []];
if !(_items isEqualType []) then { _items = []; };
private _wait = ceil (15 - (diag_tickTime - (missionNamespace getVariable ["COMSPEC_ForceSyncAt", -1e9])));
private _d = "\z\comspec_atak_native\addons\main\data\";
private _icons = createHashMapFromArray [
    ["profile", "app_profile"], ["icons", "app_gallery"], ["position", "app_gps"], ["playtime", "app_screentime"],
    ["map", "map_marker"], ["mapall", "app_map"], ["reports", "app_reports"], ["sse", "app_sse"], ["group", "app_group"],
    ["chat", "app_chat"], ["plan", "app_plan"], ["squads", "app_interteam"], ["garmin", "app_garmin"],
    ["wardrobe", "app_logistics"], ["queue", "app_network"], ["link", "app_athena"]
];
private _states = createHashMapFromArray [
    ["ok", [[0.36, 0.78, 0.42, 1], "RENVOYÉ"]], ["same", [[0.45, 0.62, 0.55, 1], "À JOUR"]],
    ["wait", [[0.42, 0.70, 0.90, 1], "EN FILE"]], ["warn", [[0.95, 0.67, 0.2, 1], "PAS PARTI"]],
    ["off", [[0.40, 0.46, 0.43, 1], "SANS OBJET"]]
];
private _n = createHashMap;
{ _n set [_x select 1, (_n getOrDefault [_x select 1, 0]) + 1]; } forEach _items;
private _ago = round ((diag_tickTime - _lastTick) / 60);
private _when = switch (true) do {
    case (_at isEqualTo ""): { "Jamais lancé pendant cette partie" };
    case (_ago < 1): { format ["Dernier renvoi à %1, à l'instant", _at] };
    default { format ["Dernier renvoi à %1, il y a %2 min", _at, _ago] };
};
private _head = switch (true) do {
    case _busy: { [[0.95, 0.67, 0.2, 1], "Renvoi en cours…", "Fiche, position, carte, plan, équipes, montre, tenues : tout repart vers Athena."] };
    case (_items isEqualTo []): { [[0.45, 0.62, 0.55, 1], "Rien renvoyé pour l'instant", "Lancez le Resynch si le poste ne vous voit plus."] };
    case ((_n getOrDefault ["warn", 0]) > 0): { [[0.95, 0.67, 0.2, 1], format ["%1 élément(s) pas partis", _n get "warn"], "Le reste est sur Athena. Voyez le détail ci-dessous, puis relancez."] };
    default { [[0.36, 0.78, 0.42, 1], "Athena est à jour", format ["%1 renvoyé(s), %2 déjà à jour, %3 en file.", _n getOrDefault ["ok", 0], _n getOrDefault ["same", 0], _n getOrDefault ["wait", 0]]] };
};
_head params ["_hc", "_ht", "_hs"];
private _rows = [
    ["status", _d + "app_resynch.paa", _hc, _ht, [format ["%1<br/><t color='#8a9a93'>%2</t>", _hs, _when], _hs] select _busy],
    ["text", "<t size='0.82' color='#8a9a93'>À lancer si le poste ne vous voit plus, après une coupure de liaison ou un changement de groupe. Seul ce qui manque à Athena repart : pas de renvoi en double.</t>"],
    ["buttons", [[[["RELANCER LE RESYNCH", format ["PATIENTEZ %1 S", _wait]] select (_wait > 0), "RENVOI EN COURS…"] select _busy,
        { [] call comspec_atak_native_fnc_resynchRun; }, true, !_busy && {_wait <= 0}, "Renvoie tout vers le poste de commandement"]]],
    ["section", "Compte rendu", ["", _at] select (_at isNotEqualTo "")]
];
if (_items isEqualTo []) then {
    _rows pushBack ["empty", _d + "app_resynch.paa", "Aucun renvoi pour l'instant", "Le compte rendu de chaque catégorie s'affichera ici."];
} else {
    // Grille de deux colonnes sur l'écran en paysage : tout le compte rendu tient sur une page.
    private _cells = _items apply {
        _x params [["_k", ""], ["_st", "same"], ["_title", ""], ["_detail", ""]];
        (_states getOrDefault [_st, _states get "same"]) params ["_col", "_lbl"];
        [_d + (_icons getOrDefault [_k, "app_resynch"]) + ".paa", _col, _title,
            format ["<t color='#%1'>%2</t>", ((_col select [0, 3]) apply { private _v = round (_x * 255); ("0123456789abcdef" select [floor (_v / 16), 1]) + ("0123456789abcdef" select [_v mod 16, 1]) }) joinString "", _lbl],
            _detail]
    };
    _rows pushBack ["cells", _cells, [1, 2] select (_bw > _bh)];
};
_rows pushBack ["text", "<t size='0.75' color='#8a9a93'>Les photos déjà transmises ne repartent pas. Les tenues jamais partagées restent sur ce PC : partagez-les depuis Tenues Athena.</t>"];
[_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
// Compte à rebours du bouton : la page se redessine à la fin du délai d'attente.
if (_wait > 0 && {!_busy}) then {
    [{ if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "RESYNCH") then { ["RESYNCH"] call comspec_atak_native_fnc_pageRender; }; }, [], (_wait min 5) max 1] call CBA_fnc_waitAndExecute;
};
true
