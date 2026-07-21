/*
    Point d'entrée du tableau de briefing tactique (action joueur ou init d'objet placé dans Eden).
    Rafraîchit la liste des diapositives si nécessaire, ouvre le dialog plein écran et affiche
    la première diapositive.

    Utilisation :
      - Action joueur : câblée automatiquement (voir XEH_postInit.sqf), scroll-menu
        "Consulter le tableau de briefing".
      - Objet Eden : donnez un Nom de variable à un objet (écran, tableau...) puis, dans son champ
        Init, appelez : this addAction ["Consulter le briefing", { [] call comspec_overwatch_connect_fnc_openBriefingBoard; }];
      - Écran/texture in-world (avancé) : voir le commentaire en bas de fn_briefingBoardShow.sqf
        pour appliquer directement une diapositive via setObjectTexture sur un objet précis.
*/
if (!hasInterface) exitWith {};
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};

private _slides = missionNamespace getVariable ["COMSPEC_BriefingSlides", []];
if (count _slides == 0) then {
    _slides = [] call comspec_overwatch_connect_fnc_getBriefingSlides;
};
if (count _slides == 0) exitWith {
    ["COMSPEC_Info", ["Aucune diapositive de briefing disponible pour le moment."]] call BIS_fnc_showNotification;
};

// L'affichage de la première diapositive est déclenché par onLoad du dialog (display_briefing.hpp).
createDialog "COMSPEC_Briefing_Dialog";
