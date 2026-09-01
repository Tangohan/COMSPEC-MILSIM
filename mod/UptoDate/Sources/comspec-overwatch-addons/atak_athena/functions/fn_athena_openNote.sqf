/*
    Ouvre le rédacteur de fiche de renseignement depuis la couche ATAK
    (menu RENS, icône Desktop, action ACE).

    Args optionnels: [_kindCode]  type de fiche présélectionné
*/
params [["_kindCode", "", [""]]];

if (!hasInterface) exitWith { false };

if (isNil "comspec_overwatch_connect_fnc_intelNoteShow") exitWith {
    ["Rédacteur de fiche indisponible — module Overwatch incomplet.", "tactical", "warn"] call comspec_overwatch_connect_fnc_announce;
    false
};

// Le téléphone doit être ouvert : le rédacteur se pose dessus comme enfant du
// display cTab, jamais dans la tablette Overwatch héritée. L'attente se fait
// dans un fil séparé — cette fonction est appelée depuis des clics de bouton,
// où uiSleep n'est pas disponible.
if (isNull (uiNamespace getVariable ["cTab_Android_dlg", displayNull])
    && {!isNil "comspec_overwatch_connect_fnc_openAtakEnhanced"}) exitWith {
    [] call comspec_overwatch_connect_fnc_openAtakEnhanced;
    [_kindCode] spawn {
        params ["_kind"];
        private _waited = 0;
        waitUntil {
            uiSleep 0.1;
            _waited = _waited + 0.1;
            !isNull (uiNamespace getVariable ["cTab_Android_dlg", displayNull]) || {_waited > 3}
        };
        [_kind] call comspec_overwatch_connect_fnc_intelNoteShow;
    };
    true
};

[_kindCode] call comspec_overwatch_connect_fnc_intelNoteShow
