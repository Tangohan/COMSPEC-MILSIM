/*
    Calculette : affichage + grille de touches (chiffres et opérations).
    État : uiNamespace COMSPEC_ATAK_Calc = [expr, display, justEval]
*/
disableSerialization;
private _st = uiNamespace getVariable ["COMSPEC_ATAK_Calc", ["", "0", false]];
if !(_st isEqualType []) then { _st = ["", "0", false]; };
_st params [["_expr", ""], ["_disp", "0"], ["_just", false]];
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];

// Une rangée = un « bar » (sinon plusieurs groupes se collent côte à côte sur écran large).
private _rows = [
    ["hero", "\z\comspec_atak_native\addons\main\data\app_calc.paa", format [
        "<t size='0.85' color='#8a9a93' align='right'>%1</t><br/><t size='1.8' font='EtelkaMonospaceProBold' align='right'>%2</t>",
        [_expr, " "] select (_expr isEqualTo ""),
        _disp
    ]],
    ["bar", [["", [
        ["C", { ["C"] call comspec_atak_native_fnc_calcAction; }, ""],
        ["CE", { ["CE"] call comspec_atak_native_fnc_calcAction; }, ""],
        ["%", { ["%"] call comspec_atak_native_fnc_calcAction; }, ""],
        ["÷", { ["/"] call comspec_atak_native_fnc_calcAction; }, ""]
    ]]]],
    ["bar", [["", [
        ["7", { ["7"] call comspec_atak_native_fnc_calcAction; }, ""],
        ["8", { ["8"] call comspec_atak_native_fnc_calcAction; }, ""],
        ["9", { ["9"] call comspec_atak_native_fnc_calcAction; }, ""],
        ["×", { ["*"] call comspec_atak_native_fnc_calcAction; }, ""]
    ]]]],
    ["bar", [["", [
        ["4", { ["4"] call comspec_atak_native_fnc_calcAction; }, ""],
        ["5", { ["5"] call comspec_atak_native_fnc_calcAction; }, ""],
        ["6", { ["6"] call comspec_atak_native_fnc_calcAction; }, ""],
        ["−", { ["-"] call comspec_atak_native_fnc_calcAction; }, ""]
    ]]]],
    ["bar", [["", [
        ["1", { ["1"] call comspec_atak_native_fnc_calcAction; }, ""],
        ["2", { ["2"] call comspec_atak_native_fnc_calcAction; }, ""],
        ["3", { ["3"] call comspec_atak_native_fnc_calcAction; }, ""],
        ["+", { ["+"] call comspec_atak_native_fnc_calcAction; }, ""]
    ]]]],
    ["bar", [["", [
        ["±", { ["neg"] call comspec_atak_native_fnc_calcAction; }, ""],
        ["0", { ["0"] call comspec_atak_native_fnc_calcAction; }, ""],
        [".", { ["."] call comspec_atak_native_fnc_calcAction; }, ""],
        ["=", { ["="] call comspec_atak_native_fnc_calcAction; }, "main"]
    ]]]]
];
[_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
true
