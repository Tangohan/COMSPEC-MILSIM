// _keepScroll : même page redessinée (fn_pageRender) : défilement laissé en place (avant : remis en haut puis
// rétabli, l'écran sautait).
params [["_keepScroll", false]];
disableSerialization;
private _edit = uiNamespace getVariable ["COMSPEC_ATAK_ChatEdit", controlNull];
if (!isNull _edit) then { uiNamespace setVariable ["COMSPEC_ATAK_ChatDraft", ctrlText _edit]; };
uiNamespace setVariable ["COMSPEC_ATAK_ChatEdit", controlNull];
private _newCh = uiNamespace getVariable ["COMSPEC_ATAK_ChatNewEdit", controlNull];
if (!isNull _newCh) then { uiNamespace setVariable ["COMSPEC_ATAK_ChatNewDraft", ctrlText _newCh]; };
uiNamespace setVariable ["COMSPEC_ATAK_ChatNewEdit", controlNull];
// Live cam : la caméra ne tourne que sur sa page.
[] call comspec_atak_native_fnc_livecamStop;
// Anciens contrôles masqués tout de suite, supprimés à l'image suivante : une page redessinée depuis le clic d'un de ses
// boutons supprimait ce bouton pendant son propre gestionnaire, et le moteur ne laissait plus passer aucun clic jusqu'à
// la réouverture du téléphone (retour de TTRD, 25/10, app Drone). Même raison que le clic différé de fn_formRender.
private _old = uiNamespace getVariable ["COMSPEC_ATAK_PageControls", []];
// 4.2.1 : calques d'écran (dégâts, filtre de nuit, surface du verre, effets d'écran, calque Zeus) laissés en place :
// fn_pageRender les garde ou les recrée lui-même après la page.
private _ovFlat = [];
private _ovAdd = { { if (_x isEqualType controlNull) then { _ovFlat pushBack _x; } else { if (_x isEqualType []) then { _x call _ovAdd; }; }; } forEach _this; };
(uiNamespace getVariable ["COMSPEC_ATAK_DevOverlay", []]) call _ovAdd;
(((uiNamespace getVariable ["COMSPEC_ATAK_SurfOverlay", ["", []]]) param [1, []]) apply { _x param [1, controlNull] }) call _ovAdd;
(((uiNamespace getVariable ["COMSPEC_ATAK_ScreenFx", ["", []]]) param [1, []]) apply { _x param [1, controlNull] }) call _ovAdd;
(uiNamespace getVariable ["COMSPEC_ATAK_ZeusOv", []]) call _ovAdd;
if (_ovFlat isNotEqualTo []) then { _old = _old - _ovFlat; };
// Marqués cs_ovSkip : en attente de suppression, ils ne comptent plus pour l'ordre des calques (fn_pageRender).
{ if (!isNull _x) then { _x ctrlShow false; _x ctrlEnable false; _x setVariable ["cs_ovSkip", true]; }; } forEach _old;
[{ { ctrlDelete _x; } forEach _this; }, _old] call comspec_atak_native_fnc_nextFrame;
uiNamespace setVariable ["COMSPEC_ATAK_PageControls", []];
// Cartouches de marqueurs de la carte : cachés hors carte (la carte les réaffiche à son prochain dessin).
{ if (!isNull _x) then { _x ctrlShow false; }; } forEach (uiNamespace getVariable ["COMSPEC_ATAK_MarkerTagPool", []]);
uiNamespace setVariable ["COMSPEC_ATAK_Form", createHashMap];
private _d = ([] call comspec_atak_native_fnc_display);
if (!_keepScroll && {!isNull _d}) then { (_d displayCtrl 88531) ctrlSetScrollValues [0, 0]; };
true
