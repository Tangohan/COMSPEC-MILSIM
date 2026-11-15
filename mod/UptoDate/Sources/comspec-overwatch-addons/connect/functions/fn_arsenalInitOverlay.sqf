/*
    Enregistre le panneau Athena sur ouverture ACE Arsenal.
*/
if (!hasInterface) exitWith {};
if (!isNil "COMSPEC_ArsenalOverlayEH") exitWith {};

if (isNil "CBA_fnc_addEventHandler") exitWith {};

COMSPEC_ArsenalOverlayEH = [
    "ace_arsenal_displayOpened",
    {
        params ["_display"];
        if (isNull _display) exitWith {};
        uiNamespace setVariable ["ace_arsenal_display", _display];
        // Tenues ACE abîmées ou en double réparées avant que le joueur n'ouvre « Mes équipements » (une seule
        // entrée illisible arrêtait l'affichage de toute la liste d'ACE). Liste inchangée depuis la dernière
        // vérification : rien n'est relu (fn_aceLoadoutsRepair). N'écrit que si ça change.
        private _t0 = diag_tickTime;
        private _checked = [] call comspec_overwatch_connect_fnc_aceLoadoutsRepair;
        // Ce que COMSPEC fait dans l'image de l'ouverture de l'arsenal, chronométré (rien n'était mesuré ici).
        if (!isNil "comspec_atak_native_fnc_perfAcc") then { ["arsenal : ouverture COMSPEC", _t0] call comspec_atak_native_fnc_perfAcc; };
        diag_log format ["[COMSPEC Overwatch][PERF] Arsenal : ouverture COMSPEC %1 ms (tenues ACE %2)",
            ((diag_tickTime - _t0) * 1000) toFixed 1, ["inchangées, non relues", "vérifiées"] select (_checked isEqualTo true)];
        // Léger délai : laisse ACE construire son UI. Seul le bouton est posé ici ; le panneau (plusieurs centaines
        // de contrôles) est construit au premier clic sur le bouton (fn_arsenalOverlayShow).
        [{
            params ["_disp"];
            if (!isNull _disp) then {
                [_disp] call comspec_overwatch_connect_fnc_arsenalOverlayShow;
            };
        }, [_display], 0.35] call CBA_fnc_waitAndExecute;
    }
] call CBA_fnc_addEventHandler;

["ace_arsenal_displayClosed", {
    uiNamespace setVariable ["ace_arsenal_display", displayNull];
    // Tenues Athena : celles vues pendant que le panneau était ouvert ne sont plus « nouvelles ».
    [] call comspec_overwatch_connect_fnc_arsenalMarkSeen;
}] call CBA_fnc_addEventHandler;

// Écran « Mes équipements » d'ACE (display enfant, non modal) : nos contrôles restent sur l'arsenal en dessous et l'un
// de nos champs pouvait garder le focus clavier, si bien que le champ Nom d'ACE ne recevait plus la frappe. Le bouton
// et le panneau Tenues sont masqués et désactivés le temps de cet écran, et le champ Nom d'ACE prend le focus.
["ace_arsenal_loadoutsDisplayOpened", {
    params [["_ld", displayNull]];
    ["loadoutsOpen", _ld] call comspec_overwatch_connect_fnc_arsenalOverlayAction;
}] call CBA_fnc_addEventHandler;
["ace_arsenal_loadoutsDisplayClosed", {
    ["loadoutsClosed"] call comspec_overwatch_connect_fnc_arsenalOverlayAction;
}] call CBA_fnc_addEventHandler;

["INFO", "ARSENAL", "Panneau Athena ACE Arsenal enregistré"] call comspec_overwatch_connect_fnc_log;
