/*
    Après un clic, Arma dessine la carte focalisée devant tout le reste : boussole, panneaux et outils disparaissent.
    On rend le focus à un bouton invisible hors écran à l'image suivante.
*/
[{
    disableSerialization;
    private _d = [] call comspec_atak_native_fnc_display;
    if (isNull _d) exitWith {};
    private _sink = _d displayCtrl 88543;
    if (!isNull _sink && {(focusedCtrl _d) isEqualTo (_d displayCtrl 88530)}) then { ctrlSetFocus _sink; };
}] call CBA_fnc_execNextFrame;
