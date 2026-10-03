/*
    Réception d'un top de brèche : compte à rebours au centre de l'écran (téléphone rangé ou non), puis « BRÈCHE ».
    Params : [nom de l'émetteur, secondes]
*/
params [["_from", ""], ["_secs", 5]];
if (!hasInterface) exitWith {};
[_from, _secs] spawn {
    params ["_from", "_secs"];
    ["WARNING", format ["Top de brèche de %1 : %2 s", _from, _secs], 3, 90] call comspec_atak_native_fnc_notify;
    for "_i" from _secs to 1 step -1 do {
        titleText [format ["<t size='3' font='RobotoCondensedBold' color='#f2ab33'>%1</t><br/><t size='1' color='#ffffff'>brèche · %2</t>", _i, _from], "PLAIN", 0.05, true, true];
        uiSleep 1;
    };
    titleText ["<t size='3.5' font='RobotoCondensedBold' color='#e5483a'>BRÈCHE !</t>", "PLAIN", 0.05, true, true];
    uiSleep 1.5;
    titleFadeOut 0.5;
};
