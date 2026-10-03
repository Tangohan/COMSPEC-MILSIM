/* Pièces d'artillerie / mortiers utilisables : calculateur d'artillerie, vivantes, vides ou servies par mon camp. */
(vehicles select {
    alive _x
    && {(getNumber (configOf _x >> "artilleryScanner")) isEqualTo 1}
    && {isNull gunner _x || {(side group gunner _x) isEqualTo (side group player)}}
    && {(_x distance2D player) < 30000}
}) apply { _x }
