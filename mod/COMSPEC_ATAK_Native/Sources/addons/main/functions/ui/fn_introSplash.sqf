/*
    Intro du mod sur l'écran de démarrage d'Arma (RscDisplayStart, Extended_DisplayLoad de config.cpp), une fois par
    lancement du jeu : la marque COMSPEC (fn_loadingBadge) apparaît en bas à droite, un trait vert balaie le bas de
    l'écran, et un carillon court joue (data\intro_chime.ogg, tools/gen_intro.py).
    Rien de plein écran ni d'opaque : l'écran de démarrage d'Arma ou d'un autre mod (pack d'unité) reste visible
    dessous. Le menu principal n'est jamais touché (l'ancienne version y rejouait l'intro sur fond noir, par-dessus
    le menu et la fenêtre « service Athéna »).
    Toutes les animations passent par ctrlCommit (moteur) : pendant le chargement le planificateur de scripts est
    figé, un spawn resterait bloqué et laisserait ses contrôles à l'écran. Pas de fn_tr non plus : les CfgFunctions
    ne sont pas encore compilées ici, d'où l'appel par chemin.
    Son : réglage CBA « Son d'intro au démarrage » (XEH_preInit), recopié dans profileNamespace parce que les
    réglages CBA ne sont pas lus à ce moment. Une touche ou un clic coupe le son.
    Désactivée par défaut depuis 1.65.0 (TTRD : buggée) : rien ne s'affiche ni ne joue tant que le réglage CBA
    « Intro au démarrage » (recopié dans profileNamespace COMSPEC_ATAK_IntroEnabled) n'est pas coché.
    Params : [display]
*/
params [["_display", displayNull, [displayNull]]];
if (isNull _display) exitWith {};
if !(profileNamespace getVariable ["COMSPEC_ATAK_IntroEnabled", false]) exitWith {};
if (uiNamespace getVariable ["COMSPEC_ATAK_IntroDone", false]) exitWith {};
uiNamespace setVariable ["COMSPEC_ATAK_IntroDone", true];

// Le logo fixe d'Overwatch (comspec_overwatch_main, idc 88001) se pose au centre de cet écran, par-dessus celui
// d'un éventuel pack d'unité : on le masque, notre marque reste discrète dans le coin.
private _ow = _display displayCtrl 88001;
if (!isNull _ow) then { _ow ctrlShow false; };

[_display] call compile preprocessFileLineNumbers "\z\comspec_atak_native\addons\main\functions\ui\fn_loadingBadge.sqf";

// Trait de balayage : part du centre et s'étend sur 40 % de la largeur, puis s'efface.
private _y = safeZoneY + safeZoneH * 0.965;
private _line = _display ctrlCreate ["RscText", -1];
_line ctrlSetBackgroundColor [0.36, 0.78, 0.42, 0.8];
_line ctrlSetPosition [safeZoneX + safeZoneW * 0.5, _y, 0, pixelH * 2];
_line ctrlCommit 0;
_line ctrlSetPosition [safeZoneX + safeZoneW * 0.3, _y, safeZoneW * 0.4, pixelH * 2];
_line ctrlCommit 0.9;
_line ctrlSetFade 1;
_line ctrlCommit 2.4;

if (profileNamespace getVariable ["COMSPEC_ATAK_IntroSound", true]) then {
    private _id = playSoundUI ["z\comspec_atak_native\addons\main\data\intro_chime.ogg", 0.35, 1];
    if (_id isEqualType 0) then {
        uiNamespace setVariable ["COMSPEC_ATAK_IntroSoundId", _id];
        private _stop = {
            private _id = uiNamespace getVariable ["COMSPEC_ATAK_IntroSoundId", -1];
            if (_id >= 0) then { stopSound _id; uiNamespace setVariable ["COMSPEC_ATAK_IntroSoundId", -1]; };
            false
        };
        _display displayAddEventHandler ["KeyDown", _stop];
        _display displayAddEventHandler ["MouseButtonDown", _stop];
    };
};
