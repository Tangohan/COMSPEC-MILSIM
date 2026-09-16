/*
  Display téléphone réellement ouvert (cTabIfOpen).
  Pas de repli sur le mini-overlay 3D ni sur un dialogue laissé en mémoire :
  les traiter comme « ouverts » relançait le calage IceMan sans que
  l’opérateur ait le téléphone en main — même famille d’arrêt brutal.
*/
private _d = displayNull;
if (!isNil "cTabIfOpen" && {cTabIfOpen isEqualType []} && {(count cTabIfOpen) > 1}) then {
    private _name = cTabIfOpen select 1;
    if (_name isEqualType "" && {_name isNotEqualTo ""}) then {
        _d = uiNamespace getVariable [_name, displayNull];
    };
};
_d
