/*
  Display téléphone IceMan en cours : d’abord l’interface ouverte (cTabIfOpen),
  puis le dialogue, puis l’overlay 3D. Évite de croire le téléphone fermé
  entre deux recréations d’écran.
*/
private _d = displayNull;
if (!isNil "cTabIfOpen" && {cTabIfOpen isEqualType []} && {(count cTabIfOpen) > 1}) then {
    private _name = cTabIfOpen select 1;
    if (_name isEqualType "" && {_name isNotEqualTo ""}) then {
        _d = uiNamespace getVariable [_name, displayNull];
    };
};
if (isNull _d) then {
    _d = uiNamespace getVariable ["cTab_Android_dlg", displayNull];
};
if (isNull _d) then {
    _d = uiNamespace getVariable ["cTab_Android_dsp", displayNull];
};
_d
