/*
    Temps passé dans les DLL (DLL native et COMSPEC Link) sous callExtension, par commande, lu en mémoire
    (commande Perf.Stats, COMSPEC Link 2.0.119) : appels, moyenne, 95e centile et pire en ms depuis le chargement
    ou la dernière remise à zéro. Lignes de l'onglet Debug › PERFS (fn_pageDebug).
      [] call comspec_atak_native_fnc_perfDll          -> lignes ["section" | "text", …] pour fn_formRender
      ["reset"] call comspec_atak_native_fnc_perfDll   -> compteurs des deux DLL remis à zéro
*/
params [["_mode", "rows"]];
private _exts = [["COMSPECATAKNativeExtension", "DLL native"], ["COMSPECExtension", "COMSPEC Link"]];
if (_mode isEqualTo "reset") exitWith { { (_x select 0) callExtension ["Perf.Stats", ["reset"]]; } forEach _exts; true };
private _esc = { params ["_t"]; [[_t, "<", "&lt;"] call CBA_fnc_replace, ">", "&gt;"] call CBA_fnc_replace };
private _rows = [];
{
    _x params ["_ext", "_label"];
    // Commande locale (mémoire) : appel direct, comme SendQueue.Status et SyncStats.
    private _r = _ext callExtension ["Perf.Stats", []];
    private _raw = if (_r isEqualType []) then { _r param [0, ""] } else { _r };
    if !(_raw isEqualType "") then { _raw = str _raw; };
    if ((_raw select [0, 3]) isNotEqualTo "OK|") then { continue };
    private _lines = (_raw select [3]) splitString (toString [10]);
    // En-tête : secondes | appels | moyenne ms | p95 ms | pire ms ; puis commande TAB appels TAB moy TAB p95 TAB pire TAB total.
    (_lines param [0, ""]) splitString "|" params [["_sec", "1"], ["_n", "0"], ["_avg", "0"], ["_p95", "0"], ["_max", "0"]];
    _rows pushBack ["section", _label, format ["%1 appels en %2 s (%3 /s) · moy %4 ms · p95 %5 ms · pire %6 ms", _n, _sec, ((parseNumber _n) / ((parseNumber _sec) max 1)) toFixed 1, _avg, _p95, _max]];
    private _txt = (_lines select [1]) apply {
        (_x splitString (toString [9])) params [["_k", ""], ["_c", ""], ["_a", ""], ["_p", ""], ["_m", ""], ["_tot", ""]];
        format ["%1 ms · %2× · moy %3 · p95 %4 · max %5 · %6", _tot, _c, _a, _p, _m, [_k] call _esc]
    };
    _rows pushBack ["text", ["<t color='#8a9a93'>Aucun appel.</t>", format ["<t size='0.8' font='EtelkaMonospacePro'>%1</t>", _txt joinString "<br/>"]] select ((count _txt) > 0)];
} forEach _exts;
if (_rows isEqualTo []) then { _rows pushBack ["text", "<t color='#8a9a93'>Temps des DLL : COMSPEC Link 2.0.119 ou plus requis.</t>"]; };
_rows
