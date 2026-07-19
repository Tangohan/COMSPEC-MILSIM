/*
    Récupère la liste des diapositives de briefing actives depuis la plateforme (via l'extension
    native, fonction GetBriefingSlides) et la stocke dans missionNamespace ["COMSPEC_BriefingSlides"].
    Chaque diapositive : [id, title, sortOrder, imageUrl].

    Variable mission optionnelle : comspec_overwatch_tenant_id (si le serveur héberge plusieurs
    communautés ; laissez vide pour un déploiement mono-communauté).

    Retourne : la liste des diapositives (array).
*/
if (!hasInterface) exitWith { [] };
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith { [] };

private _tenantId = missionNamespace getVariable ["comspec_overwatch_tenant_id", ""];
private _raw = "COMSPECExtension" callExtension ["GetBriefingSlides", [_tenantId]];
private _parts = _raw splitString "|";
private _prefix = if (count _parts >= 1) then { _parts select 0 } else { "" };

if (_prefix != "OK") exitWith {
    missionNamespace setVariable ["COMSPEC_BriefingSlides", [], true];
    []
};

private _payload = if (count _parts >= 2) then { _parts select 1 } else { "" };
private _lines = _payload splitString "\n";
private _slides = [];
{
    if (_x != "") then {
        private _cols = _x splitString "\t";
        if (count _cols >= 4) then {
            _slides pushBack [
                parseNumber (_cols select 0),
                _cols select 1,
                parseNumber (_cols select 2),
                _cols select 3
            ];
        };
    };
} forEach _lines;

missionNamespace setVariable ["COMSPEC_BriefingSlides", _slides, true];
_slides
