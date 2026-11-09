/*
    Slot virtuel COMSPEC (EXPÉRIMENTAL), à chaque image (événement EachFrame posé par fn_eyewearRender tant qu'il y a des
    lunettes à dessiner) : chaque objet de lunettes suit la tête de son porteur.
      position    : eyePos du porteur, ramenée dans le repère de l'unité (offset d'attachTo), moins l'écart mesuré entre
                    le décalage posé et l'origine du modèle réellement dessinée (moyenne glissante sur quelques images) ;
      orientation : celle de l'os de la tête (selectionVectorDirAndUp sur « pilot », mémoire), sinon eyeDirection.
    Le décalage de réglage de la config (comspec_sse_eyewearAttachOffset, repère tête : x droite, y avant, z haut)
    s'ajoute. Coût : quelques commandes par paire de lunettes visible et par image.
*/
{
    _x params ["_u", "_o", "_id", ["_corr", [0, 0, 0]], ["_tune", [0, 0, 0]]];
    if (!isNull _u && {!isNull _o} && {isNull objectParent _u}) then {
        private _du = _u selectionVectorDirAndUp ["pilot", "Memory"];
        _du params [["_dir", [0, 1, 0]], ["_up", [0, 0, 1]]];
        if ((vectorMagnitude _dir) < 0.5 || {(vectorMagnitude _up) < 0.5}) then {
            _dir = _u vectorWorldToModelVisual (eyeDirection _u);
            _up = [0, 0, 1];
        };
        private _right = _dir vectorCrossProduct _up;
        private _eyeM = _u worldToModelVisual (ASLToAGL (eyePos _u));
        private _wantM = _eyeM vectorAdd ((_right vectorMultiply (_tune select 0)) vectorAdd (_dir vectorMultiply (_tune select 1)) vectorAdd (_up vectorMultiply (_tune select 2)));
        // Écart entre le décalage posé à l'image précédente et l'origine du modèle effectivement dessinée (même repère :
        // indépendant du mouvement de l'unité) : moyenne glissante, retranchée au décalage suivant.
        private _last = _x param [5, []];
        if (_last isNotEqualTo []) then {
            private _bias = (_u worldToModelVisual (ASLToAGL (_o modelToWorldVisualWorld [0, 0, 0]))) vectorDiff _last;
            _corr = (_corr vectorMultiply 0.8) vectorAdd (_bias vectorMultiply -0.2);
            if ((vectorMagnitude _corr) > 0.5) then { _corr = [0, 0, 0]; };
        };
        private _off = _wantM vectorAdd _corr;
        _x set [3, _corr];
        _x set [5, _off];
        _o attachTo [_u, _off];
        _o setVectorDirAndUp [_dir, _up];
    };
} forEach (missionNamespace getVariable ["comspec_sse_eyewear_drawn", []]);
