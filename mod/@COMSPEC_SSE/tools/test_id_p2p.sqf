/*
    Essais des règles du contrôle des papiers entre joueurs (fn_idP2pRules) : console de débogage, en local ou sur le
    serveur. Copier ce fichier dans la console et l'exécuter : renvoie "OK (n essais)" ou la liste des écarts.
    Hors jeu, mêmes essais sur le portage Python : python tools/test_id_p2p.py.
*/
private _fails = [];
private _n = 0;
private _r = { _this call comspec_sse_fnc_idP2pRules };
{
    _x params ["_label", "_call", "_want"];
    _n = _n + 1;
    private _got = _call call _r;
    if (_got isNotEqualTo _want) then { _fails pushBack format ["%1 : %2 (attendu %3)", _label, _got, _want]; };
} forEach [
    ["réglage coché", ["enabled", [true, 0]], true],
    ["réglage décoché", ["enabled", [false, 0]], false],
    ["Zeus autorise malgré le réglage", ["enabled", [false, 1]], true],
    ["Zeus interdit malgré le réglage", ["enabled", [true, 2]], false],
    ["accepté dans les temps", ["next", ["pending", "accept", 10, 20, true]], "accepted"],
    ["refusé dans les temps", ["next", ["pending", "refuse", 10, 20, true]], "refused"],
    ["refus interdit par la mission", ["next", ["pending", "refuse", 10, 20, false]], ""],
    ["échéance pas encore passée", ["next", ["pending", "tick", 20, 20, true]], "pending"],
    ["pas de réponse", ["next", ["pending", "tick", 20.1, 20, true]], "timeout"],
    ["accord trop tard", ["next", ["pending", "accept", 21, 20, true]], "timeout"],
    ["refus trop tard", ["next", ["pending", "refuse", 21, 20, true]], "timeout"],
    ["évènement inconnu", ["next", ["pending", "cancel", 10, 20, true]], ""],
    ["accepté : fermé", ["next", ["accepted", "refuse", 10, 20, true]], ""],
    ["refusé : fermé", ["next", ["refused", "accept", 10, 20, true]], ""],
    ["sans réponse : fermé", ["next", ["timeout", "accept", 10, 20, true]], ""],
    ["face à face", ["range", [2.5, 3, 1, true]], ""],
    ["à la limite", ["range", [3, 3, 1, true]], ""],
    ["trop loin", ["range", [3.2, 3, 1, true]], "far"],
    ["derrière un mur", ["range", [2, 3, 0, true]], "los"],
    ["en véhicule", ["range", [1, 3, 1, false]], "vehicle"],
    ["un papier", ["subset", [["A"], ["A", "B", "C"], 12]], ["A"]],
    ["ordre gardé", ["subset", [["C", "A"], ["A", "B", "C"], 12]], ["C", "A"]],
    ["aucun", ["subset", [[], ["A", "B"], 12]], []],
    ["papier d'un autre", ["subset", [["A", "Z"], ["A", "B"], 12]], []],
    ["doublon", ["subset", [["A", "A"], ["A", "B"], 12]], []],
    ["pas une classe", ["subset", [["A", 7], ["A", "B"], 12]], []],
    ["trop de papiers", ["subset", [["A", "B", "C"], ["A", "B", "C"], 2]], []],
    ["première demande", ["rate", [100, -1e6, ""]], true],
    ["trop tôt", ["rate", [100, 95, "timeout"]], false],
    ["délai passé", ["rate", [100, 92, "accepted"]], true],
    ["après un refus, trop tôt", ["rate", [100, 92, "refused"]], false],
    ["après un refus, délai passé", ["rate", [100, 70, "refused"]], true],
    ["motif reconnu", ["reason", "not_now"], "not_now"],
    ["motif inventé", ["reason", "<t size='9'>x</t>"], ""],
    ["remettre", ["mode", "give"], "give"],
    ["montrer par défaut", ["mode", "autre"], "show"],
    ["délai trop court", ["timeout", 1], 5],
    ["délai trop long", ["timeout", 999], 120],
    ["distance trop grande", ["distance", 50], 4],
    ["distance trop petite", ["distance", 0], 2]
];
if (_fails isEqualTo []) then { format ["OK (%1 essais)", _n] } else { _fails joinString endl }
