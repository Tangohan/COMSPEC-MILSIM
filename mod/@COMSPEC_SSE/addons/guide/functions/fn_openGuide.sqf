/*
    Ouvre un chapitre du manuel SSE.
      Hors Zeus : manuel de terrain (BIS_fnc_openFieldManual, catégorie COMSPEC_SSE).
      Zeus ouvert : le manuel de terrain s'ouvre sur l'écran de mission, derrière l'interface Zeus, et
                    le module semblait ne rien faire. Le chapitre s'affiche donc dans une fenêtre posée
                    sur l'écran Zeus lui-même, avec le renvoi vers le manuel complet.
    ["Zeus"] call comspec_sse_fnc_openGuide
*/
params [["_topic", "Start", [""]]];
if (!hasInterface) exitWith {};
private _zeus = findDisplay 312;
if (isNull _zeus) exitWith { ["COMSPEC_SSE", _topic] call BIS_fnc_openFieldManual; };

private _cfg = configFile >> "CfgHints" >> "COMSPEC_SSE" >> _topic;
if (!isClass _cfg) exitWith { ["COMSPEC_SSE", _topic] call BIS_fnc_openFieldManual; };
private _title = getText (_cfg >> "displayName");
private _body = getText (_cfg >> "description");
private _tip = getText (_cfg >> "tip");
// Codes réservés de CfgHints : %1 saut de ligne, %2 puce, %3 / %4 surlignage.
_body = format [_body, "<br/>", "• ", "<t color='#7fd08a'>", "</t>"];
private _more = ("Manuel complet : Échap › Manuel de terrain › COMSPEC SSE.") call comspec_sse_fnc_tr;
if (_tip isNotEqualTo "") then { _body = _body + format ["<br/><br/><t color='#c9d3dc' size='0.9'>%1</t>", _tip call comspec_sse_fnc_tr]; };
_body = _body + format ["<br/><br/><t color='#8a96a3' size='0.85'>%1</t>", _more];
[parseText _body, _title, true, false, _zeus, false, false] spawn BIS_fnc_guiMessage;
