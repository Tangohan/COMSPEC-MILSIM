params [["_text", ""]];

private _display = uiNamespace getVariable ["Iceman_TOC_display", displayNull];
if (isNull _display) exitWith {};

(_display displayCtrl 94108) ctrlSetText _text;
