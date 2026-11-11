/*
    Extensions de documents : liste coupée calculée et diffusée par le serveur (fn_docExtServer), reçue par les clients
    (cache de comspec_sse_fnc_idDocTypes vidé, fenêtre relue) ; module Zeus et notification ATAK du Zeus au lancement.
*/
// Liste coupée diffusée par le serveur (lancement, choix dans la fenêtre, réglage changé) : la valeur voyage par
// comspec_sse_docExtOff (setVariable public, reçue aussi en JIP) et l'évènement CBA ci-dessous avertit chaque machine
// (hemtt marque addPublicVariableEventHandler comme commande cassée) : cache vidé, fenêtre relue si elle est ouverte.
["comspec_sse_docExtOffChanged", {
    params [["_off", [], [[]]]];
    ["refresh"] call comspec_sse_fnc_idDocTypes;
    diag_log format ["[COMSPEC SSE] Extensions de documents coupées (serveur) : %1", _off];
    ["refresh"] call comspec_sse_fnc_docExtWindow;
}] call CBA_fnc_addEventHandler;
if (isServer) then { ["init"] call comspec_sse_fnc_docExtServer; };
if (!hasInterface) exitWith {};

// Module ZEN « COMSPEC · Extensions » : ouvre la fenêtre. ZEN peut se charger après nous (45 s au plus).
[{ !isNil "zen_custom_modules_fnc_register" }, {
    ["COMSPEC · Extensions", "SSE ROLEPLAY · Extensions", { [] call comspec_sse_fnc_docExtOpen; }, "\z\comspec_sse\addons\identity\data\icon_card_ca.paa"] call comspec_sse_fnc_zenModule;
}, [], 45] call CBA_fnc_waitUntilAndExecute;

// Zeus : notification ATAK listant les extensions chargées (réglage comspec_sse_docExt_notify), une fois le joueur
// et son Zeus en place ; rien si aucune extension n'est chargée (exemple intégré coupé compris), rien sans Zeus (délai de 3 minutes).
[{ !isNull player && {!isNull (getAssignedCuratorLogic player)} && {!isNil {missionNamespace getVariable "comspec_sse_docExtOff"}} }, {
    if (!(missionNamespace getVariable ["comspec_sse_docExt_notify", true]) || {isNil "comspec_sse_fnc_notify"}) exitWith {};
    // L'extension d'exemple livrée avec le SSE (intégrée), tant qu'elle reste coupée, n'est pas annoncée.
    private _rows = (["extensions"] call comspec_sse_fnc_idDocTypes) select { !(_x select 8) || {_x select 7} };
    if (_rows isEqualTo []) exitWith {};
    private _lines = _rows apply {
        _x params ["_cls", "_name", "", "_version", "", "_docs", "", "_active"];
        format ["%1%2 · %3 modèle(s) · %4", [_name, _cls] select (_name isEqualTo ""), ["", format [" v%1", _version]] select (_version isNotEqualTo ""), count _docs, ["désactivée", "active"] select _active]
    };
    [format ["Extensions de documents : %1", _lines joinString " · "], "INFO", "", "SSE"] call comspec_sse_fnc_notify;
}, [], 180] call CBA_fnc_waitUntilAndExecute;
