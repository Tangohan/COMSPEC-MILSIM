/*
    Affiche la diapositive à l'index donné dans le dialog COMSPEC_Briefing_Dialog déjà ouvert :
    télécharge l'image (appel bloquant, comme tous les échanges synchrones de cette extension)
    et l'applique au contrôle RscPicture.

    Params: [_index]
*/
params [["_index", 0, [0]]];

// Deck Google actif : ne pas écraser avec les images Athena au onLoad du dialog.
if (missionNamespace getVariable ["COMSPEC_GoogleBriefingActive", false]) exitWith {
    private _path = missionNamespace getVariable ["COMSPEC_GoogleBriefingPath", ""];
    private _gIndex = missionNamespace getVariable ["COMSPEC_GoogleBriefingIndex", 0];
    private _total = missionNamespace getVariable ["COMSPEC_GoogleBriefingTotal", 1];
    if (_path isNotEqualTo "") then {
        [
            _path,
            format ["Google Slides — diapositive %1", _gIndex + 1],
            _gIndex,
            _total
        ] call comspec_overwatch_connect_fnc_applyGoogleBriefingSlide;
    };
};

private _slides = missionNamespace getVariable ["COMSPEC_BriefingSlides", []];
if (count _slides == 0) exitWith {};
_index = (_index max 0) min (count _slides - 1);
missionNamespace setVariable ["COMSPEC_BriefingSlideIndex", _index];

private _display = findDisplay 9970;
if (isNull _display) exitWith {};

private _slide = _slides select _index;
private _title = _slide select 1;

private _ctrlTitle = _display displayCtrl 9002;
if (!isNull _ctrlTitle) then { _ctrlTitle ctrlSetText (["Diapositive sans titre", _title] select (_title != "")); };

private _ctrlIndex = _display displayCtrl 9003;
if (!isNull _ctrlIndex) then { _ctrlIndex ctrlSetText format ["%1 / %2", _index + 1, count _slides]; };

private _ctrlPic = _display displayCtrl 9001;

// Appel bloquant : téléchargement + écriture en cache côté extension. Attendu à quelques centaines
// de ms selon la connexion — acceptable pour une action volontaire (pas un handler par frame).
private _path = [_slide] call comspec_overwatch_connect_fnc_downloadBriefingSlide;

if (isNull _display) exitWith {}; // le joueur a pu fermer le dialog pendant le téléchargement

if (_path != "") then {
    if (!isNull _ctrlPic) then { _ctrlPic ctrlSetText _path; };
    [_path, _title, _index, count _slides] call comspec_overwatch_connect_fnc_applyGoogleBriefingSlide;
} else {
    ["COMSPEC_Warning", ["Impossible de charger cette diapositive (réseau ou cache indisponible)."]] call comspec_overwatch_connect_fnc_showNotification;
};

/*
    Option avancée — écran/tableau posé dans Eden Editor au lieu du dialog plein écran :
    tout objet dont le modèle expose une "hidden selection" texturable (ex. un écran de TV,
    un panneau) peut recevoir la même image via setObjectTexture. Exemple, dans le champ Init
    de l'objet (variable name "briefingScreen1" par ex.) :

        this setVariable ["comspec_briefingScreenIndex", 0];   // section hiddenSelections à cibler
        [briefingScreen1, 0] spawn {
            params ["_obj", "_selIdx"];
            waitUntil { !isNull _obj };
            private _slides = missionNamespace getVariable ["COMSPEC_BriefingSlides", []];
            if (count _slides == 0) then { _slides = [] call comspec_overwatch_connect_fnc_getBriefingSlides; };
            if (count _slides > 0) then {
                private _path = [_slides select 0] call comspec_overwatch_connect_fnc_downloadBriefingSlide;
                if (_path != "") then { _obj setObjectTexture [_selIdx, _path]; };
            };
        };

    L'indice de hiddenSelection (0 ici) dépend du modèle choisi — vérifiez-le avec
    "getObjectTextures <classname>" en console dev, ou dans la doc du pack d'objets utilisé.
    Ce mécanisme (téléchargement + setObjectTexture) est le même que celui employé par les scripts
    communautaires de "logo de clan dynamique".
*/
