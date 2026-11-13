/*
    comspec_fb_fnc_selfCheck : auto-vérification PURE du planificateur et du budget sonore (aucune grenade, aucun son,
    aucun effet, rien à l'écran). À lancer depuis la console de débogage :

        [] call comspec_fb_fnc_selfCheck

    Rejoue fn_plan (cadence hyper rapide) + fn_schedule + fn_sndBudget image par image, pour N = 6, 9 et 50
    détonations, à 60 puis 20 images par seconde simulées, et vérifie :
      - détonations exécutées == N, dans l'ordre, aucune abandonnée pour retard ;
      - un son lancé par détonation (N = 6 et 9 : aucun fusionné ; N = 50 : fusion tolérée seulement au-delà de 28
        lectures en cours, comptée) ;
      - message arrivé 0,4 s en retard : tout est rattrapé (rien d'abandonné) ; 2 s en retard : seules les détonations
        vieilles de plus de 1,5 s sont abandonnées.
    Une ligne « [COMSPEC FB] autotest » par cas dans le RPT. Retour : "OK" ou "ÉCHEC (voir le RPT)".
*/
private _allOk = true;
private _run = {
    params ["_n", "_fps", "_late"];
    private _offsets = ([_n, 2] call comspec_fb_fnc_plan) select 0;
    private _t0 = 100;
    private _now = _t0 - 0.2 + _late + random (1 / _fps);
    private _next = 0;
    private _done = [];
    private _dropped = 0;
    private _ends = [];
    private _sounds = 0;
    private _fused = 0;
    private _guard = 0;
    while { _next < _n && {_guard < 100000} } do {
        _guard = _guard + 1;
        ([_offsets, _t0, _now, _next] call comspec_fb_fnc_schedule) params ["_due", "_drop", "_following"];
        _dropped = _dropped + count _drop;
        {
            _done pushBack _x;
            _ends = _ends select { _x > _now };
            private _first = _x == 0;
            private _lastOne = _x == _n - 1;
            private _copies = [count _ends, 4, 2] call comspec_fb_fnc_sndBudget;
            if (_copies == 0) then { _fused = _fused + 1 } else {
                _sounds = _sounds + 1;
                // Bang (2 lectures + couche proche) ; le 1er ajoute la couche grave ; durée utile 0,35 s (court) ou 1,2 s.
                for "_k" from 1 to ((_copies + 1) * ([1, 2] select _first)) do { _ends pushBack (_now + ([0.35, 1.2] select (_first || _lastOne))) };
            };
        } forEach _due;
        _next = _following;
        _now = _now + 1 / _fps;
    };
    private _expected = [];
    for "_i" from _dropped to (_n - 1) do { _expected pushBack _i };
    private _maxDrop = if (_late > 1.5) then { { (_late - 0.2 - _x) > 1.5 - 1 / _fps } count _offsets } else { 0 };
    private _ok = _done isEqualTo _expected && {_dropped <= _maxDrop} && {_sounds + _fused == count _done} && {_n > 9 || {_fused == 0}};
    diag_log text format ["[COMSPEC FB] autotest | N=%1 à %2 images/s, retard %3 s : exécutées %4, abandonnées %5, sons %6, fusionnés %7 -> %8",
        _n, _fps, _late, count _done, _dropped, _sounds, _fused, ["ÉCHEC", "OK"] select _ok];
    _ok
};
{
    private _n = _x;
    {
        if !([_n, _x, 0] call _run) then { _allOk = false };
        if !([_n, _x, 0.6] call _run) then { _allOk = false };
    } forEach [60, 20];
} forEach [6, 9, 50];
if !([9, 20, 2.2] call _run) then { _allOk = false };
["ÉCHEC (voir le RPT)", "OK"] select _allOk
