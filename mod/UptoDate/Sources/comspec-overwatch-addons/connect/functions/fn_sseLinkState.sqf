/*
    État de liaison affiché par le terminal SEEK : "linked" | "degraded" | "offline".

    Même verdict que la barre d'état du téléphone ATAK natif (fn_statusUpdate) : COMSPEC Link
    fait foi quand il dit « en ligne » ou « en cours », sinon c'est la liaison propre du
    téléphone qui décide. Avant, le SEEK ne regardait le téléphone que si COMSPEC_LinkState
    était vide ; or il vaut « offline » dès le chargement, si bien que le SEEK restait
    HORS LIAISON alors que le téléphone affichait « Athena connecté ».
*/
private _s = missionNamespace getVariable ["COMSPEC_LinkState", ""];
if (!(_s isEqualType "")) then { _s = ""; };
_s = toLower _s;
if (_s isEqualTo "linked") exitWith { "linked" };
private _n = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _net = if (_n isEqualType createHashMap) then { _n getOrDefault ["networkState", ""] } else { "" };
if (!(_net isEqualType "")) then { _net = ""; };
_net = toUpper _net;
if (_net isEqualTo "CONNECTED") exitWith { "linked" };
if (_s in ["connecting", "degraded"] || {_net isEqualTo "DEGRADED"}) exitWith { "degraded" };
"offline"
