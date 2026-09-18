/*
  Display téléphone réellement ouvert : uniquement le grand écran
  (cTab_Android_dlg). Le mini-écran 3D s’ouvre tout seul quand on prend
  l’objet : le traiter comme ouvert relance le calage IceMan et ferme le jeu.
*/
private _d = displayNull;
if (!isNil "cTabIfOpen" && {cTabIfOpen isEqualType []} && {(count cTabIfOpen) > 1}) then {
    private _name = cTabIfOpen select 1;
    if (_name isEqualType "" && {_name isEqualTo "cTab_Android_dlg"}) then {
        _d = uiNamespace getVariable ["cTab_Android_dlg", displayNull];
    };
};
if (!isNull _d) then {
    private _dsp = uiNamespace getVariable ["cTab_Android_dsp", displayNull];
    if (!isNull _dsp && {_d isEqualTo _dsp}) then { _d = displayNull; };
};
_d
