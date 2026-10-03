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
    ["info", "En attente / reçues / refusées", format ["%1 / <t color='#5cc76b'>%2</t> / <t color='#e5483a'>%3</t>", count (missionNamespace getVariable ["COMSPEC_Athena_PhotoPending", []]), count (missionNamespace getVariable ["COMSPEC_Athena_PhotoUploaded", []]), count (missionNamespace getVariable ["COMSPEC_Athena_PhotoFailed", []])]],
    ["info", "Dernière mise en file", [[missionNamespace getVariable ["COMSPEC_LastReconUploadDetail", "—"]] call _esc, "—"] select ((missionNamespace getVariable ["COMSPEC_LastReconUploadDetail", ""]) isEqualTo "")],
    ["text", (missionNamespace getVariable ["COMSPEC_LastReconUploadResult", []]) call {
        params [["_st", ""], ["_msg", ""], ["_tech", ""], ["_at", ""]];
        switch (_st) do {
            case "OK": { format ["<t color='#5cc76b'>%1 · reçue par Athena</t>  <t size='0.8' color='#8a9a93'>%2</t>", _at, [_tech] call _esc] };
            case "ERR": { format ["<t color='#e5483a'>%1 · %2</t><br/><t size='0.8' color='#8a9a93'>%3</t>", _at, [_msg] call _esc, [_tech] call _esc] };
            default { "<t color='#8a9a93'>Aucun retour d'Athena pour l'instant.</t>" };
        }
    }],
    ["text", if ((count _lines) isEqualTo 0) then { "<t color='#8a9a93'>Aucune photo envoyée pendant cette session.</t>" } else { (_lines select [0, 12]) joinString "<br/><br/>" }]
];
[_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
true
