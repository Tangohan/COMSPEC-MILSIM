/*
    comspec_fb_fnc_log : ligne de diagnostic RPT « [COMSPEC FB] étape | message | t= ». Jamais d'affichage à l'écran.
    Réglage « Journal de diagnostic (RPT) » (actif par défaut) : lignes essentielles d'un lancer (une dizaine : lancer,
    mèche, chemin serveur ou client, programmation, une par détonation, effet sur le joueur).
    Réglage « Journal détaillé » : en plus les étapes de détail (lumière, sons, chaque unité, local, IA, acouphène).
    Pendant un test comspec_fb_fnc_debugTest : tout, et les lignes sont gardées pour son rapport.
    [_etape, _message] call comspec_fb_fnc_log
*/
params [["_tag", "", [""]], ["_msg", "", [""]]];
if !([_tag in ["fx", "son", "unité", "unités", "env", "ia", "acouphène"]] call comspec_fb_fnc_logOn) exitWith {};
private _line = format ["[COMSPEC FB] %1 | %2 | t=%3", _tag, _msg, CBA_missionTime toFixed 2];
diag_log text _line;
if (!isNil "comspec_fb_capture") then { comspec_fb_capture pushBack _line };
