/*
    comspec_fb_fnc_schedule : fonction PURE du planificateur (aucun effet, aucune horloge lue) : à l'instant _now,
    quelles détonations sont dues ? Appelée par fn_timelineRun à chaque réveil ; rejouée telle quelle par
    fn_selfCheck et par les tests (60 et 20 images/s).

    Règles :
      - toutes les détonations dont l'instant est passé (à 0,01 s près) sont DUES ENSEMBLE, dans l'ordre : une image
        lente (20 images/s = 50 ms, cadence hyper rapide = 45 à 90 ms) ne fait jamais perdre de détonation ;
      - une détonation n'est abandonnée que si elle a plus de _lateMax secondes de retard (1,5 s : message réseau
        arrivé très tard) ; un simple retard d'image ou de réseau est rattrapé, jamais sauté.
    [_offsets, _t0, _now, _next, _lateMax] call comspec_fb_fnc_schedule -> [_dues, _abandonnees, _suivante, _attente]
      _offsets : instants depuis la 1re détonation (croissants) ; _t0 : heure de la 1re ; _next : rang de la prochaine
      _attente : secondes avant la détonation _suivante ; -1 = plus aucune
*/
params [["_offsets", [], [[]]], ["_t0", 0, [0]], ["_now", 0, [0]], ["_next", 0, [0]], ["_lateMax", 1.5, [0]]];
private _n = count _offsets;
private _due = [];
private _dropped = [];
while { _next < _n && {(_t0 + (_offsets select _next)) <= _now + 0.01} } do {
    if (_now - (_t0 + (_offsets select _next)) > _lateMax) then { _dropped pushBack _next } else { _due pushBack _next };
    _next = _next + 1;
};
[_due, _dropped, _next, [-1, ((_t0 + (_offsets param [_next, 0])) - _now) max 0] select (_next < _n)]
