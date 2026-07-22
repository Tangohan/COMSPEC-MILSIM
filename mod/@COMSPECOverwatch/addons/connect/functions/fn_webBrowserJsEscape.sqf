/*
    Nettoie une chaîne pour injection dans un littéral JS single-quoted (ExecJS).
*/
params [["_text", ""]];
if (!(_text isEqualType "")) then { _text = str _text; };
_text = (_text splitString toString [39]) joinString ""; // '
_text = (_text splitString toString [34]) joinString ""; // "
_text = (_text splitString "\") joinString "";
_text = (_text splitString toString [10]) joinString " ";
_text = (_text splitString toString [13]) joinString "";
_text
