/*
    App Resynch : comme l'app du même nom de l'ancien ATAK. À l'ouverture, renvoie tout vers Athena
    (position, marqueurs, groupe, messages récents) et affiche le compte rendu ; bouton pour relancer.
*/
disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
// Lancement automatique une fois par ouverture de l'app (pas à chaque rafraîchissement de la page).
if ((_s getOrDefault ["resynchAuto", ""]) isNotEqualTo (str (_s getOrDefault ["display", displayNull]))) then {
    _s set ["resynchAuto", str (_s getOrDefault ["display", displayNull])];
    [{ [] call comspec_atak_native_fnc_resynchRun; }] call CBA_fnc_execNextFrame;
};
private _busy = missionNamespace getVariable ["COMSPEC_ATAK_ResynchBusy", false];
private _at = missionNamespace getVariable ["COMSPEC_ATAK_ResynchAt", ""];
private _lines = missionNamespace getVariable ["COMSPEC_LastResynchSummary", []];
if !(_lines isEqualType []) then { _lines = []; };
private _rows = [
    ["hero", "\z\comspec_atak_native\addons\main\data\app_resynch.paa", format ["<t size='1.35' font='RobotoCondensedBold'>RESYNCH</t><br/><t color='%1'>%2</t>",
        ["#8a9a93", "#f2ab33"] select _busy,
        switch (true) do {
            case _busy: { "Renvoi en cours…" };
            case (_at isNotEqualTo ""): { format ["Dernier renvoi à %1", _at] };
            default { "Renvoie vos données au poste de commandement" };
        }]],
    ["text", "<t size='0.85' color='#8a9a93'>À utiliser si le poste ne vous voit plus, après une coupure de liaison ou un changement de groupe : position, marqueurs, effectif du groupe et derniers messages sont renvoyés. Les photos déjà transmises ne sont pas renvoyées.</t>"],
    ["section", "Compte rendu", ""]
];
if ((count _lines) isEqualTo 0) then {
    _rows pushBack ["text", "<t color='#8a9a93'>Aucun renvoi pour l'instant.</t>"];
} else {
    // Les couleurs pastel de l'ancien ATAK deviennent celles du téléphone.
    private _txt = _lines joinString "<br/>";
    { _txt = [_txt, _x select 0, _x select 1] call CBA_fnc_replace; } forEach [["#9dffc4", "#5cc76b"], ["#ffb0a0", "#e5483a"], ["#B9C0E0", "#c9d4cf"], ["#8A90A8", "#8a9a93"]];
    _rows pushBack ["text", format ["<t size='0.9'>%1</t>", _txt]];
};
_rows pushBack ["buttons", [["RELANCER LE RESYNCH", { [] call comspec_atak_native_fnc_resynchRun; }, true, !_busy]]];
[_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
true
