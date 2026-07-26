/*
    Force la relecture de la liste des diapositives depuis la plateforme (bouton "Actualiser" du
    tableau de briefing), puis réaffiche la première diapositive.
*/
if (!hasInterface) exitWith {};

[] call comspec_overwatch_connect_fnc_getBriefingSlides;
[0] call comspec_overwatch_connect_fnc_briefingBoardShow;
