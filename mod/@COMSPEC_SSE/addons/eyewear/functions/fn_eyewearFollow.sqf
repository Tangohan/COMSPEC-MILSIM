/*
    Slot virtuel COMSPEC (EXPÉRIMENTAL), à chaque image (événement EachFrame posé par fn_eyewearRender tant qu'il y a des
    lunettes à dessiner) : chaque objet de lunettes suit la tête de son porteur.
      orientation : celle du regard (eyeDirection) ; « haut » = verticale du porteur rendue perpendiculaire au regard.
                    L'orientation de l'os de la tête (selectionVectorDirAndUp ["pilot", "Memory"]) a été essayée : en jeu,
                    lunettes relevées de 15 à 20° (l'os n'a pas l'axe du visage).
      position    : point mémoire « pilot » (eyePos est ~15 cm trop bas sur un fantassin debout, « pilot » ~5 cm trop
                    haut) + décalage de la config (comspec_sse_eyewearAttachOffset, repère du regard : x droite, y avant,
                    z haut ; défaut 1 cm devant, 5,5 cm plus bas), moins l'écart mesuré entre le décalage posé et
                    l'origine du modèle réellement dessinée (moyenne glissante sur quelques images).
    Coût : quelques commandes par paire de lunettes visible et par image.
*/
{
    _x params ["_u", "_o", "_id", ["_corr", [0, 0, 0]], ["_tune", [0, 0, 0]]];
    if (!isNull _u && {!isNull _o} && {isNull objectParent _u}) then {
        private _dir = vectorNormalized (_u vectorWorldToModelVisual (eyeDirection _u));
        if ((vectorMagnitude _dir) < 0.5) then { _dir = [0, 1, 0]; };
        private _up = [0, 0, 1] vectorDiff (_dir vectorMultiply (_dir select 2));
        _up = if ((vectorMagnitude _up) < 0.1) then { [0, -1, 0] } else { vectorNormalized _up };
        private _right = _dir vectorCrossProduct _up;
        private _pilotM = _u worldToModelVisual (ASLToAGL (_u modelToWorldVisualWorld (_u selectionPosition ["pilot", "Memory"])));
        _tune params [["_tx", 0], ["_ty", 0], ["_tz", 0]];
        private _wantM = _pilotM vectorAdd ((_right vectorMultiply _tx) vectorAdd (_dir vectorMultiply _ty) vectorAdd (_up vectorMultiply _tz));
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
