/*
    SEEK II : remplit les listes du terminal avec le catalogue du site de la communauté (fn_sseCatalogue), ou avec les
    listes du mod si le site ne répond pas. Appelé à l'ouverture (premier remplissage) puis à chaque lecture du site
    (événement comspec_sse_catalogueUpdated) : le choix en cours est gardé.
      [_premier] call comspec_overwatch_connect_fnc_sseCatalogueSeek
    9505 statut · 9506 contexte d'acquisition · 9569 dossiers ouverts · 9561 / 9565 libellés avec l'origine des listes.
*/
params [["_first", false, [false]]];
if (!hasInterface) exitWith {};
disableSerialization;
private _disp = uiNamespace getVariable ["COMSPEC_SsePerson_Display", displayNull];
if (isNull _disp) then { _disp = findDisplay 9991; };
if (isNull _disp) exitWith {};

// Liste d'un menu déroulant : codes en donnée, choix en cours gardé (sinon première ligne).
private _fill = {
    params ["_idc", "_codes", "_labels", "_translate"];
    private _ctrl = _disp displayCtrl _idc;
    if (isNull _ctrl) exitWith {};
    private _sel = lbCurSel _ctrl;
    private _current = if (_first || {_sel < 0}) then { "" } else { _ctrl lbData _sel };
    lbClear _ctrl;
    private _pick = 0;
    {
        private _label = _labels param [_forEachIndex, _x];
        // Libellés du mod : traduits par le dictionnaire ; libellés du site : déjà dans la langue du téléphone.
        private _i = _ctrl lbAdd (if (_translate) then { (_label) call comspec_overwatch_connect_fnc_tr } else { _label }); // i18n:brut
        _ctrl lbSetData [_i, _x];
        if (_x isEqualTo _current) then { _pick = _i; };
    } forEach _codes;
    if (_codes isNotEqualTo []) then { _ctrl lbSetCurSel _pick; };
};

(["choices", ["statuses",
    ["civil", "combattant", "detenu", "prioritaire"],
    ["Civil", "Combattant", "Détenu", "Personne prioritaire"]
]] call comspec_overwatch_connect_fnc_sseCatalogue) params ["_sCodes", "_sLabels", "_sSource"];
[9505, _sCodes, _sLabels, _sSource isEqualTo "local"] call _fill;

// Contextes d'acquisition (forces spéciales) : mêmes codes que config/sse_acquisition_contexts.php sur le site. La
// communauté peut en retirer, en renommer et en ajouter : sa liste remplace celle-ci dès que le site répond.
(["choices", ["contexts",
    [
        "exploitation_site_sensible", "raid_action_directe", "interdiction_embuscade", "fouille_haute_valeur", "cordon_fouille",
        "point_controle_tactique", "fouille_patrouille", "interrogatoire_tactique", "engagement_chef_local", "source_humaine",
        "enrolement_biometrique", "capture_champ_bataille", "remise_force_partenaire", "materiel_ennemi_capture",
        "document_media_capture", "reconnaissance_speciale", "recuperation_personnel", "autre"
    ],
    [
        "Exploitation de site sensible (SSE)", "Action directe : assaut sur l’objectif", "Interdiction ou embuscade",
        "Fouille d’une cible HVT à la capture", "Cordon et fouille", "Point de contrôle tactique (TCP)",
        "Fouille opportuniste en patrouille", "Interrogatoire tactique (TQ)", "Rencontre avec un chef local (KLE)",
        "Source humaine (HUMINT / CI)", "Enrôlement biométrique (BATS / SEEK)", "Reddition ou capture au combat (EPW)",
        "Remise à une force partenaire", "Matériel ennemi capturé (CEM)", "Document ou média capturé (DOMEX)",
        "Reconnaissance spéciale (SR)", "Récupération de personnel", "Autre (précisez)"
    ]
]] call comspec_overwatch_connect_fnc_sseCatalogue) params ["_cCodes", "_cLabels", "_cSource"];
[9506, _cCodes, _cLabels, _cSource isEqualTo "local"] call _fill;

// Dossiers ouverts : première ligne neutre, le code reste saisissable à la main (dossier pas encore créé sur le site).
(["choices", ["cases", [], []]] call comspec_overwatch_connect_fnc_sseCatalogue) params ["_kCodes", "_kLabels"];
private _none = if (_kCodes isEqualTo []) then { "Aucun dossier reçu du site" } else { "Choisir un dossier du site" };
private _case = _disp displayCtrl 9569;
if (!isNull _case) then {
    lbClear _case;
    private _i = _case lbAdd ((_none) call comspec_overwatch_connect_fnc_tr);
    _case lbSetData [_i, ""];
    private _typed = toUpper (trim (ctrlText (_disp displayCtrl 9518)));
    private _pick = 0;
    {
        private _j = _case lbAdd (_kLabels param [_forEachIndex, _x]); // i18n:brut
        _case lbSetData [_j, _x];
        if ((toUpper _x) isEqualTo _typed) then { _pick = _j; };
    } forEach _kCodes;
    _case lbSetCurSel _pick;
};

// Origine des listes, dite dans le terminal (pas d'alerte) : site et heure de synchro, ou repli.
private _origin = ["status"] call comspec_overwatch_connect_fnc_sseCatalogue;
{
    _x params ["_idc", "_title"];
    private _lbl = _disp displayCtrl _idc;
    if (!isNull _lbl) then {
        _lbl ctrlSetStructuredText parseText ((format [
            "<t size='0.6' font='TahomaB' color='#33414c'>%1</t><t size='0.5' color='#5b6975'>  ·  %2</t>",
            (_title) call comspec_overwatch_connect_fnc_tr, _origin
        ]) call comspec_overwatch_connect_fnc_tr);
    };
} forEach [[9561, "CIRCONSTANCES"], [9565, "CODE DOSSIER"]];
