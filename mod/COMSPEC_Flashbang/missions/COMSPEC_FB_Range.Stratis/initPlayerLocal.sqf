/*
    Champ de test COMSPEC Flashbang : équipement du joueur, actions de la caisse (jour / nuit, déplacements), consignes.
*/
params ["_player"];
waitUntil { !isNil "COMSPEC_FB_Range_Box" && {!isNil "COMSPEC_FB_Range_Zones"} };

private _equip = {
    params ["_u"];
    _u setPosATL ((COMSPEC_FB_Range_Zones select 0) vectorAdd [0, 0, 0]);
    _u addVest "V_PlateCarrier1_rgr";
    _u addBackpack "B_Carryall_oli";
    _u addWeapon "arifle_MX_F";
    _u addPrimaryWeaponItem "optic_Hamr";
    _u addWeapon "Binocular";
    _u linkItem "NVGoggles";
    { _u addItem _x } forEach (["ACE_EarPlugs"] select { isClass (configFile >> "CfgWeapons" >> _x) });
    for "_n" from 1 to 9 do { _u addMagazines [format ["COMSPEC_FB_Flash_%1", _n], 2] };
    _u addMagazines ["COMSPEC_FB_Drill", 4];
    // Couloir F : la même grenade en 1,5 s / 3 s / 8 s.
    { _u addMagazines [_x, 2] } forEach ["COMSPEC_FB_Flash_3_D3", "COMSPEC_FB_Flash_3_D8", "COMSPEC_FB_Drill_D3"];
};
[_player] call _equip;
_player addEventHandler ["Respawn", { params ["_u"]; [_u] call (missionNamespace getVariable ["COMSPEC_FB_Range_Equip", {}]) }];
missionNamespace setVariable ["COMSPEC_FB_Range_Equip", _equip];

private _box = COMSPEC_FB_Range_Box;
_box addAction ["Jour (12 h)", { [[2035, 7, 6, 12, 0]] remoteExecCall ["setDate", 2] }, nil, 9, false, true, "", "true", 5];
_box addAction ["Nuit (23 h, sans lune)", { [[2035, 7, 6, 23, 30]] remoteExecCall ["setDate", 2] }, nil, 8, false, true, "", "true", 5];
_box addAction ["Recharger mes grenades", {
    params ["", "_caller"];
    private _mags = ["COMSPEC_FB_Drill", "COMSPEC_FB_Flash_3_D3", "COMSPEC_FB_Flash_3_D8", "COMSPEC_FB_Drill_D3"];
    for "_n" from 1 to 9 do { _mags pushBack format ["COMSPEC_FB_Flash_%1", _n] };
    { _caller removeMagazines _x } forEach _mags;
    for "_n" from 1 to 9 do { _caller addMagazines [format ["COMSPEC_FB_Flash_%1", _n], 2] };
    _caller addMagazines ["COMSPEC_FB_Drill", 4];
    { _caller addMagazines [_x, 2] } forEach ["COMSPEC_FB_Flash_3_D3", "COMSPEC_FB_Flash_3_D8", "COMSPEC_FB_Drill_D3"];
}, nil, 7, false, true, "", "true", 5];
{
    _x params ["_label", "_idx"];
    _box addAction [_label, {
        params ["", "_caller", "", "_idx"];
        _caller setPosATL ((COMSPEC_FB_Range_Zones select _idx) vectorAdd [0, 0, 0]);
    }, _idx, 5 - _forEachIndex * 0.1, false, true, "", "true", 5];
} forEach [["Aller : A - champ ouvert", 1], ["Aller : B - pièce fermée", 2], ["Aller : C - pièce à fenêtres", 3],
    ["Aller : D - couloir", 4], ["Aller : E - hangar", 5], ["Aller : F - délais", 6]];

_player createDiarySubject ["comspec_fb", "Flashbang"];
_player createDiaryRecord ["comspec_fb", ["Consignes", "
<font size='16'>Champ de test COMSPEC Flashbang</font><br/><br/>
Grenades : « Flash ×1 » à « Flash ×9 » (nombre de détonations) et « Drill » (bleue, inerte), 2 de chaque dans le sac,
plus « Flash ×3 · 3 s », « Flash ×3 · 8 s » et « Drill · 3 s » ; caisses au départ (« Caisse Flash », « Caisse délais »).<br/>
Lancer : G ; changer de grenade : Ctrl+G (touches par défaut). Action « Recharger mes grenades » sur la caisse.<br/><br/>
Zones (actions de la caisse « Aller : ... ») :<br/>
A - champ ouvert : cercle à 25 m, IA de 2 à 35 m, face ou dos au cercle, 2 civils.<br/>
B - pièce fermée : lancer dedans porte fermée, puis depuis la porte ; IA dehors derrière le mur (bang sourd, pas de flash).<br/>
C - pièce à fenêtres : IA dehors face à la fenêtre (touchée) et dos tourné (peu touchée).<br/>
D - couloir : effet renforcé, IA au fond et dans les chambres.<br/>
E - hangar : grand volume, effet intermédiaire.<br/>
F - délais : depuis le cercle, lancer « Flash ×3 », « Flash ×3 · 3 s » puis « Flash ×3 · 8 s » vers la pièce à 15 m.
Compter : la grenade 8 s explose-t-elle bien 8 s après le lancer, sans exploser à l'impact ? (bande rouge, « 8s »).<br/><br/>
À comparer : jour / nuit (actions de la caisse), JVN allumées (N), lunette HAMR ou jumelles pointées sur la grenade,
dos tourné, derrière un mur, bouchons ACE si ACE est chargé.<br/>
Journal RPT : lignes [COMSPEC FB] (lancer, mèche, détonations, distance, angle, intérieur, force de l'effet) ; rien ne s'affiche à l'écran.
Test sans lancer : console de débogage, [3] call comspec_fb_fnc_debugTest; (rapport dans le RPT et le presse-papiers).
"]];
