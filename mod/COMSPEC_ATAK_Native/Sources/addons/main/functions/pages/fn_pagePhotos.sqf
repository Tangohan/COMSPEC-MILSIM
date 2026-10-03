/* App Photos : mode appareil photo (viseur plein écran) avec légende, et suivi des envois vers ATAK web. */
disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];
private _esc = { params ["_t"]; if !(_t isEqualType "") then { _t = str _t; }; [[_t, "<", "&lt;"] call CBA_fnc_replace, ">", "&gt;"] call CBA_fnc_replace };
private _lines = [];
{
    _x params [["_kind", ""], ["_title", ""], ["_text", ""], ["_grid", ""], ["_time", ""]];
    if ((toUpper _kind) isEqualTo "PHOTO") then {
        _lines pushBack format ["<t color='#8a9a93'>%1</t>  %2<br/>%3", [_time] call _esc, [_title] call _esc, [_text] call _esc];
    };
} forEach (missionNamespace getVariable ["COMSPEC_Athena_AlertInbox", []]);
reverse _lines;
private _rows = [
    ["title", "Appareil photo"],
    ["text", "<t color='#8a9a93'>Ouvre le viseur plein écran : déplacez-vous normalement, <t color='#c9d4cf'>clic gauche</t> pour photographier (autant que vous voulez), <t color='#c9d4cf'>Espace</t> pour revenir au téléphone. Chaque photo part sur ATAK web avec la grille, le cap et la légende.</t>"],
    ["edit", "caption", "Légende (facultatif)", ""],
    ["buttons", [["OUVRIR L'APPAREIL PHOTO", { private _c = ["caption"] call comspec_atak_native_fnc_formValue; [{ ["ENTER", _this] call comspec_atak_native_fnc_photoMode; }, _c] call CBA_fnc_execNextFrame; }, true]]],
    ["title", "Envois"],
    ["text", if ((count _lines) isEqualTo 0) then { "<t color='#8a9a93'>Aucune photo envoyée pendant cette session.</t>" } else { (_lines select [0, 12]) joinString "<br/><br/>" }]
];
[_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
true
