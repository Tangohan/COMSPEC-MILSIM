/*
    Liste légère des unités en liaison (callsign + grille), via l'extension native (fonction
    GetUnits — déjà utilisée côté web /atak, jamais consommée en jeu jusqu'ici). Format simplifié
    "U\tcallsign\tgx\tgy\n" par ligne (voir Extension.cs::SimplifyUnitsJson).

    Retourne : [[callsign, gx, gy], ...] trié par callsign, ou [] si aucune donnée / échec (jamais
    une valeur inventée).
*/
if (!hasInterface) exitWith { [] };

private _raw = ["COMSPECExtension" callExtension "GetUnits"] call comspec_overwatch_connect_fnc_extResult;
private _parts = _raw splitString "|";
private _prefix = if (count _parts >= 1) then { _parts select 0 } else { "" };
if (_prefix != "OK") exitWith { [] };

private _payload = if (count _parts >= 2) then { _parts select 1 } else { "" };
private _rows = [];
{
    private _cols = _x splitString "\t";
    if (count _cols >= 4 && {(_cols select 0) == "U"}) then {
        _rows pushBack [_cols select 1, parseNumber (_cols select 2), parseNumber (_cols select 3)];
    };
} forEach (_payload splitString "\n");

// Tri alphabétique par callsign (1er élément de chaque sous-tableau) — comportement documenté de
// "sort" sur un tableau de tableaux : comparaison élément par élément à partir du premier.
_rows sort true;
_rows
