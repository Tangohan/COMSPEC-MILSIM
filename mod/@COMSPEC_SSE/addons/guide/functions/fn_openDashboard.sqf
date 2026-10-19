/*
    Tableau de bord SSE (menu COMSPEC SSE d'Eden) : tout le SSE de la mission ouverte, en lecture seule.
      Synthèse        mission, contenu SSE de la carte, campagne, état du site, contrôles de cohérence
      Entités         personnes, objets et modules SSE posés, avec leurs attributs et synchronisations
      Liaisons        liens entre entités et organisations (modules, attributs, biométrie, contenu des objets), réseaux
      Organigramme    organisations de la carte et de la campagne du site, membres par rôle, cellules, objets rattachés
      Items et membres objets reliés à chaque personne, objets sans lien, registre des scellés de la campagne
      Campagne        modules de mission de la carte, configuration complète de la campagne lue sur le site
      Site Athena     campagnes de la communauté, choix de la campagne lue, avis de recherche croisés avec la carte
    Même technique que comspec_sse_fnc_openGuide : fenêtre posée sur l'écran d'Eden, créée une fois le menu refermé.
    Le site est lu par la DLL COMSPEC Link (comspec_sse_fnc_dashSite) ; rien n'est envoyé ni modifié.

    ["overview"] call comspec_sse_fnc_openDashboard
*/
params [["_tab", "overview", [""]]];
if (!hasInterface) exitWith {};
disableSerialization;
if (!is3DEN) exitWith { hintSilent (("Tableau de bord SSE : disponible dans l'éditeur Eden (menu COMSPEC SSE).") call comspec_sse_fnc_tr); };
// Action d'un menu d'Eden : la liste déroulante est encore ouverte, la fenêtre est créée juste après.
if (!canSuspend) exitWith { [_tab] spawn { uiSleep 0.05; _this call comspec_sse_fnc_openDashboard; }; };
private _parent = findDisplay 313;
if (isNull _parent) exitWith {};

private _d = _parent createDisplay "RscDisplayEmpty";
if (isNull _d) exitWith {};
private _w = safeZoneW * 0.86;
private _h = safeZoneH * 0.82;
private _u = safeZoneH * 0.006;
private _x0 = safeZoneX + (safeZoneW - _w) / 2;
private _y0 = safeZoneY + (safeZoneH - _h) / 2 + 3 * _u;
private _mk = {
    params ["_cls", "_pos"];
    private _c = _d ctrlCreate [_cls, -1];
    _c ctrlSetPosition _pos;
    _c ctrlCommit 0;
    _c
};

private _dim = ["ctrlStatic", [safeZoneX, safeZoneY, safeZoneW, safeZoneH]] call _mk;
_dim ctrlSetBackgroundColor [0, 0, 0, 0.5];
private _bg = ["ctrlStatic", [_x0, _y0, _w, _h]] call _mk;
_bg ctrlSetBackgroundColor [0.12, 0.13, 0.12, 0.97];
private _title = ["ctrlStatic", [_x0, _y0 - 5 * _u, _w, 5 * _u]] call _mk;
_title ctrlSetBackgroundColor [0.10, 0.30, 0.16, 1];
_title ctrlSetTextColor [1, 1, 1, 1];
_title ctrlSetText ("  " + (("Tableau de bord SSE") call comspec_sse_fnc_tr));
private _sub = ["ctrlStructuredText", [_x0 + _w * 0.4, _y0 - 4.6 * _u, _w * 0.6 - _u, 4.4 * _u]] call _mk;
_sub ctrlSetStructuredText parseText format ["<t align='right' color='#d8e6da'>%1 · %2</t>", missionName, worldName];

// Onglets.
private _tabs = [
    ["overview", "Synthèse"], ["entities", "Entités"], ["links", "Liaisons"], ["orgchart", "Organigramme"],
    ["items", "Items et membres"], ["campaign", "Campagne"], ["site", "Site Athena"]
];
private _tw = (_w - 4 * _u - ((count _tabs) - 1) * _u) / (count _tabs);
private _tabCtrls = [];
{
    _x params ["_id", "_lbl"];
    private _b = ["ctrlButton", [_x0 + 2 * _u + _forEachIndex * (_tw + _u), _y0 + 1.5 * _u, _tw, 5 * _u]] call _mk;
    _b ctrlSetText ((_lbl) call comspec_sse_fnc_tr);
    _b setVariable ["comspec_sse_tab", _id];
    _b ctrlAddEventHandler ["ButtonClick", {
        params ["_b"];
        [ctrlParent _b, _b getVariable ["comspec_sse_tab", "overview"]] call comspec_sse_fnc_dashRender;
    }];
    _tabCtrls pushBack [_id, _b];
} forEach _tabs;
_d setVariable ["comspec_sse_dashTabs", _tabCtrls];
_d setVariable ["comspec_sse_dashArea", [_x0 + 2 * _u, _y0 + 8 * _u, _w - 4 * _u, _h - 16 * _u, _u]];

// Barre du bas : état du site, actualisations, fermeture.
private _status = ["ctrlStructuredText", [_x0 + 2 * _u, _y0 + _h - 6.5 * _u, _w * 0.5, 5 * _u]] call _mk;
_d setVariable ["comspec_sse_dashStatus", _status];
private _bw = _w * 0.15;
private _bClose = ["ctrlButton", [_x0 + _w - _bw - 2 * _u, _y0 + _h - 6.5 * _u, _bw, 5 * _u]] call _mk;
_bClose ctrlSetText (("Fermer") call comspec_sse_fnc_tr);
_bClose ctrlAddEventHandler ["ButtonClick", { (ctrlParent (_this select 0)) closeDisplay 2; }];
private _bSite = ["ctrlButton", [_x0 + _w - 2 * _bw - 3 * _u, _y0 + _h - 6.5 * _u, _bw, 5 * _u]] call _mk;
_bSite ctrlSetText (("Actualiser depuis le site") call comspec_sse_fnc_tr);
_bSite ctrlSetTooltip (("Relit les campagnes, la campagne choisie et les avis de recherche sur Athena (DLL COMSPEC Link).") call comspec_sse_fnc_tr);
_bSite ctrlAddEventHandler ["ButtonClick", {
    private _d = ctrlParent (_this select 0);
    private _site = uiNamespace getVariable ["comspec_sse_dashSite", createHashMap];
    ["refresh", [_site getOrDefault ["key", ""]], _d] spawn comspec_sse_fnc_dashSite;
}];
private _bScan = ["ctrlButton", [_x0 + _w - 3 * _bw - 4 * _u, _y0 + _h - 6.5 * _u, _bw, 5 * _u]] call _mk;
_bScan ctrlSetText (("Actualiser la carte") call comspec_sse_fnc_tr);
_bScan ctrlSetTooltip (("Relit les entités, modules et attributs SSE de la mission ouverte.") call comspec_sse_fnc_tr);
_bScan ctrlAddEventHandler ["ButtonClick", {
    private _d = ctrlParent (_this select 0);
    _d setVariable ["comspec_sse_dashModel", [] call comspec_sse_fnc_dashCollect];
    [_d] call comspec_sse_fnc_dashRender;
}];

_status ctrlSetStructuredText parseText (("<t color='#8a96a3'>Analyse de la carte…</t>") call comspec_sse_fnc_tr);
uiSleep 0.02;
_d setVariable ["comspec_sse_dashModel", [] call comspec_sse_fnc_dashCollect];
[_d, _tab] call comspec_sse_fnc_dashRender;
// Première ouverture de la session : lecture du site ; ensuite, les données gardées s'affichent tout de suite.
if ((count (uiNamespace getVariable ["comspec_sse_dashSite", createHashMap])) == 0) then {
    ["refresh", [], _d] spawn comspec_sse_fnc_dashSite;
};
