/*
    Rectangles (coordonnées écran) occupés par le bandeau d'information et le cap posés sur la carte (fn_pageMap) :
    les noms des repères (fn_mapMarkerText), des lieux (fn_mapPlaceText) et les cartouches (fn_mapLabels) les évitent
    au lieu de passer dessous. Rien pour une autre carte (taille différente : carte plein écran, JTAC, capture).
    Params : [contrôle carte] -> [[x0, y0, x1, y1]...]
*/
params ["_map"];
(uiNamespace getVariable ["COMSPEC_ATAK_MapHudRects", [[0, 0], []]]) params ["_size", "_rects"];
(ctrlPosition _map) params ["_mx", "_my", "_mw", "_mh"];
if ((abs (_mw - (_size select 0))) > 0.002 || {(abs (_mh - (_size select 1))) > 0.002}) exitWith { [] };
_rects apply { [(_x select 0) + _mx, (_x select 1) + _my, (_x select 2) + _mx, (_x select 3) + _my] }
