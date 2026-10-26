/*
    Ma position telle que le téléphone la voit (ASL) : position réelle + décalage GPS affiché (brouillage, dégâts,
    ciel masqué, pas de fix : fn_ewEffects, index 5). Sert à tout ce qui part de « moi » sur la carte (suivi, centrage,
    distances et azimuts des cartouches, trait vers le curseur), pour que tout bouge avec mon point et ses anneaux.
*/
private _off = ([] call comspec_atak_native_fnc_ewEffects) param [5, [0, 0, 0]];
(getPosASL (vehicle player)) vectorAdd [_off select 0, _off select 1, 0]
