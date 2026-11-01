/*
    Retire une tenue de l’arsenal ACE local (cet ordinateur), à la demande du joueur (arsenalAceStore « remove »).
*/
params [["_name", "", [""]]];

if (!hasInterface) exitWith { false };
if (_name isEqualTo "") exitWith { false };

(["remove", [_name]] call comspec_overwatch_connect_fnc_arsenalAceStore) isNotEqualTo []
