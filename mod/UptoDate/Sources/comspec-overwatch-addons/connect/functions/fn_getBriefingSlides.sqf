/*
    Récupère la liste des diapositives de briefing actives depuis la plateforme (via l'extension
    native, fonction GetBriefingSlides) et la stocke dans missionNamespace ["COMSPEC_BriefingSlides"].
    Chaque diapositive : [id, title, sortOrder, imageUrl, notes, opération] (notes : texte saisi sur Athena ;
    opération : « CODE · Nom » de l'opération Athena rattachée, vide pour une diapositive commune ; DLL 2.0.58+).

    Variable mission optionnelle : comspec_overwatch_tenant_id (si le serveur héberge plusieurs
    communautés ; laissez vide pour un déploiement mono-communauté).

    Retourne : la liste des diapositives (array).
*/
if (!hasInterface) exitWith { [] };
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith { [] };

private _tenantId = missionNamespace getVariable ["comspec_overwatch_tenant_id", ""];
// Appel non bloquant quand le contexte peut attendre (fn_extAwait) : la requête pouvait figer le jeu jusqu'à 8 s.
private _raw = [["COMSPECExtension", "GetBriefingSlides", [_tenantId]] call comspec_overwatch_connect_fnc_extAwait] call comspec_overwatch_connect_fnc_extResult;
private _parts = _raw splitString "|";
private _prefix = if (count _parts >= 1) then { _parts select 0 } else { "" };

// Échec (timeout, joueur non lié, coupure) : rien n'est diffusé. Vider la liste publique ici effaçait les
// diapositives de tous les joueurs (et des arrivants) dès qu'un seul spectateur échouait sa relecture.
if (_prefix != "OK") exitWith {
    ["WARN", "Briefing", format ["GetBriefingSlides échoué (%1) — liste partagée conservée", _prefix]] call comspec_overwatch_connect_fnc_log;
    []
};

private _payload = if (count _parts >= 2) then { _parts select 1 } else { "" };
private _lines = _payload splitString (toString [10]);
private _slides = [];
private _googleUrl = "";
{
    if (_x != "") then {
        // Découpe en gardant les champs vides (titre ou notes vides ne décalent pas les colonnes).
        private _cols = [_x, toString [9]] call comspec_overwatch_connect_fnc_splitKeepEmpty;
        // Ligne méta Athena : G\tgoogle_slides_url
        if ((count _cols) >= 2 && {(_cols select 0) isEqualTo "G"}) then {
            _googleUrl = _cols select 1;
        } else {
            if (count _cols >= 4) then {
                _slides pushBack [
                    parseNumber (_cols select 0),
                    _cols select 1,
                    parseNumber (_cols select 2),
                    _cols select 3,
                    _cols param [4, ""],
                    _cols param [5, ""]
                ];
            };
        };
    };
} forEach _lines;

// Diffusé seulement s'il change : chaque joueur qui suit une présentation relit la liste, et chaque relecture
// renvoyait toute la liste à tous les joueurs (et aux arrivants).
if ((missionNamespace getVariable ["COMSPEC_CommunityGoogleSlidesUrl", ""]) isNotEqualTo _googleUrl) then { missionNamespace setVariable ["COMSPEC_CommunityGoogleSlidesUrl", _googleUrl, true]; };
if ((missionNamespace getVariable ["COMSPEC_BriefingSlides", []]) isNotEqualTo _slides) then { missionNamespace setVariable ["COMSPEC_BriefingSlides", _slides, true]; };
_slides
