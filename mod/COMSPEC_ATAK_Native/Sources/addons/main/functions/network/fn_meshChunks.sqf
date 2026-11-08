/*
    Fichiers en morceaux (lot 10, réglage comspec_atak_native_mesh_chunks) : un gros envoi passe par blocs de 128 Ko dans fn_netSend, un
    seul bloc en file à la fois. La coupure, le renvoi et la reprise sont ceux de fn_netSend : le bloc en attente repart au retour du
    réseau, les blocs déjà passés ne sont pas refaits. Ne simule que le temps de transfert : le contenu part en une fois, au dernier bloc.
      ["start", [code, arguments, libellé, Ko, clé]] -> état renvoyé par fn_netSend pour le premier bloc
      ["next", [code, arguments, libellé, Ko, clé, blocs passés, blocs au total]]
*/
params [["_act", "start"], ["_arg", []]];
_arg params ["_code", "_args", "_label", "_kb", "_key", ["_done", 0], ["_total", 0]];
if (_act isEqualTo "start") then { _total = ceil (_kb / 128); };
if (_done >= _total) exitWith { _args call _code; "SENT" };
if (_done > 0) then { [format ["%1 : %2 %%", _label, round (100 * _done / _total)], 2] call comspec_atak_native_fnc_toast; };
[{ ["next", _this] call comspec_atak_native_fnc_meshChunks; }, [_code, _args, _label, _kb, _key, _done + 1, _total], format ["%1 (%2/%3)", _label, _done + 1, _total], 128, 1, [_key, _key + "#bloc"] select (_key isNotEqualTo "")] call comspec_atak_native_fnc_netSend
