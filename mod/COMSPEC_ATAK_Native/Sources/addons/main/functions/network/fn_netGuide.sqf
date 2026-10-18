/*
    Guide « Réseau ATAK et simulation » : comment le réseau fonctionne et ce que le mod simule, pour les joueurs et les
    mission makers. Fenêtre à nous (sommaire à gauche, schéma et texte à droite), ouverte :
      - dans Eden, menu COMSPEC ATAK > Guide : réseau et simulation (config.cpp, Display3DEN) ;
      - au menu principal d'Arma, bouton « COMSPEC ATAK · Guide réseau » (fn_mainMenuButton) ;
      - en mission, sur l'écran de jeu ou de pause.
    Pas de BIS_fnc_openFieldManual : lancé depuis la barre d'Eden il échoue (voir fn_relayEden "help").
    Langue : celle du mod (COMSPEC_ATAK_Lang) ; au menu principal, le dictionnaire est chargé à la première ouverture
    (réglage « Langue / Language » s'il est connu, sinon la langue du jeu). Schémas : data\netguide_<nom>_<fr|en>.paa
    (tools/gen_net_guide.py).
    Params : [section à ouvrir (0), écran parent (displayNull : Eden, pause ou jeu)]
*/
params [["_sec", 0], ["_parent", displayNull]];
disableSerialization;
// Menu d'Eden : l'action s'exécute pendant que la liste déroulante est ouverte ; fenêtre créée juste après.
if (is3DEN && {!canSuspend}) exitWith { [_sec, _parent] spawn { uiSleep 0.05; _this call comspec_atak_native_fnc_netGuide; }; };
if (isNil { missionNamespace getVariable "COMSPEC_ATAK_Lang" }) then {
    [false, missionNamespace getVariable ["comspec_atak_native_language", "auto"]] call comspec_atak_native_fnc_langApply;
};
if (isNull _parent) then {
    { if (!isNull (findDisplay _x)) exitWith { _parent = findDisplay _x; }; } forEach [313, 49, 46];
};
if (isNull _parent) exitWith {};
private _d = _parent createDisplay "RscDisplayEmpty";
if (isNull _d) exitWith {};
private _img = ["en", "fr"] select ((missionNamespace getVariable ["COMSPEC_ATAK_Lang", "en"]) isEqualTo "fr");
private _pic = { format ["\z\comspec_atak_native\addons\main\data\netguide_%1_%2.paa", _this, _img] };

// [titre, schéma ("" : aucun), [paragraphes]]
private _sections = [
    ["Vue d'ensemble", "chain", [
        "Le téléphone ATAK ne parle pas directement à Athena : ses données passent par radio, de relais en relais, jusqu'à un relais raccordé (satellite ou fibre) qui les remet au serveur.",
        "Chaque envoi (position, message, photo, MEDEVAC) a une taille, part au débit du moment, subit une latence et peut se perdre. Sans réseau, il attend dans une file.",
        "Tout ce que montre le téléphone vient de ce calcul : barres de signal, page Réseau, app Wave Relay, flux vidéo, retard des messages.",
        "Sans relais dans la mission, le téléphone garde une liaison directe simplifiée, sauf si la mission impose le réseau exclusivement par relais."
    ]],
    ["Signal, portée et débit", "budget", [
        "La portée d'un relais est celle d'un lien dégagé : à cette distance la marge radio tombe à 0 dB. Chaque fois que la distance est divisée par deux, la marge gagne 6 dB (affaiblissement en espace libre).",
        "Le débit suit la marge par modulation adaptative, comme une vraie radio maillée : 8 paliers (MCS 0 à 7), du débit plein au-dessus de 20 dB à 6 % juste au-dessus du seuil.",
        "Les pertes de paquets montent près du seuil (environ 40 % à 0 dB, 15 % à 5 dB) et deviennent négligeables au-delà de 15 dB.",
        "Le téléphone émet bien moins fort que le relais : la portée est celle de la voie montante. Un peu au-delà, on reçoit encore (une barre, « Réception seule ») mais rien ne part : les envois attendent en file.",
        "Le signal fluctue de ±2 dB (évanouissements, multitrajets), davantage en mouvement rapide. Le téléphone garde son relais jusqu'à 20 % au-delà de sa portée avant d'en changer."
    ]],
    ["Relief et ligne de vue", "fresnel", [
        "Une onde radio a besoin d'un fuseau dégagé autour de la ligne droite entre les antennes : la première zone de Fresnel (environ 5 m de rayon au milieu d'un lien de 2 km).",
        "Le mod lit le profil du terrain entre le relais et le téléphone, ajoute la courbure de la Terre et calcule la diffraction sur la crête principale : une crête qui effleure coûte environ 6 dB, une colline franche 15 à 30 dB.",
        "Conséquences : un relais haut (pylône, mât sur un sommet) porte bien plus loin ; couché ou au fond d'un vallon, le téléphone perd des barres ; monter de quelques mètres peut suffire.",
        "Les bonds entre relais et les liens du maillage entre téléphones suivent la même règle."
    ]],
    ["Forêt, bâtiments et intérieur", "", [
        "Là où le rayon passe à moins de 20 m du sol, le mod compte les arbres le long du trajet : la profondeur de feuillage traversée coûte de quelques dB à plus de 30 dB (modèle de Weissberger à 4,9 GHz). Le feuillage mouillé par la pluie atténue 20 % de plus.",
        "Chaque bâtiment traversé entre vous et le relais coûte environ 12 dB (deux murs).",
        "À l'intérieur : près d'une fenêtre le signal passe encore ; au fond du bâtiment il chute fortement ; en sous-sol, au cœur d'un grand bâtiment ou sous l'eau, il n'y a plus rien.",
        "La pluie, l'orage et le vent comptent aussi (rubrique suivante) ; le brouillard n'a aucun effet à 4,9 GHz."
    ]],
    ["Météo, surcharge et pertes de paquets", "", [
        "Pluie : à 4,9 GHz elle affaiblit peu l'onde elle-même (environ 0,05 dB/km sous une averse, 0,5 dB/km sous un déluge, ITU-R P.838), mais elle mouille les antennes (0,5 à 2 dB) et le feuillage (+20 %).",
        "Orage : les éclairs font monter le bruit radio jusqu'à 6 dB et les paquets se perdent par rafales. Vent fort (au-delà de 30 km/h) : mâts et arbres bougent, le signal fluctue de quelques dB.",
        "Surcharge : les téléphones à portée d'un même relais se partagent son canal. Plus il est occupé (vos envois, ceux des autres, le trafic de fond fixé par le serveur), plus le débit baisse, la latence monte et des paquets se perdent par collisions. Au-delà de 100 %, la capacité se partage.",
        "Pertes de paquets : elles arrivent par rafales, comme sur une vraie liaison radio : de mauvaises périodes où plusieurs envois de suite se perdent, puis le lien redevient bon. Les envois perdus repartent automatiquement.",
        "Conditions réseau (menu Échap) : chacun y voit les conditions du moment ; un administrateur du serveur ou un Zeus les règle pour tous (météo, surcharge, pertes, latence, relais) et elles sont envoyées au site. Une règle de la communauté sur le site (Contrôle serveur > Réseau et simulation) s'impose au serveur."
    ]],
    ["Relais, répéteurs et maillage", "chain", [
        "Raccordé : le relais porte le réseau seul (satellite, fibre). Répéteur : il doit voir un relais raccordé, ou un répéteur qui l'est, de proche en proche. Chaque bond est un vrai lien radio et le débit d'un répéteur est le plus faible de la chaîne.",
        "Canaux : 8 canaux de 5 MHz entre 4 940 et 4 975 MHz. En automatique, chaque relais prend un canal libre ; deux relais voisins sur le même canal se brouillent (débit divisé, pertes).",
        "Places : au-delà de ses places, le débit d'un relais se partage entre les téléphones à portée. Chaque bond et chaque brouillage ajoutent de la latence.",
        "Alimentation : secteur par défaut ; sur batterie, le relais se vide selon son autonomie et s'éteint à 0 %. Abîmé, il perd du débit ; détruit, il ne relaie plus.",
        "Wave Relay : les téléphones du camp forment aussi un maillage entre eux (3 km par défaut, même bilan radio) ; l'app montre les sauts jusqu'à la passerelle."
    ]],
    ["Brouillage et guerre électronique", "jam", [
        "Un brouilleur noie le signal : son effet dépend du rapport entre sa puissance reçue et celle du relais. Il gagne 20 dB chaque fois que la distance au brouilleur est divisée par dix.",
        "Près d'un relais (grande marge), le signal passe encore à travers le brouillage ; au bord de la couverture, il tombe dès l'entrée dans la zone brouillée.",
        "Sources : brouilleurs de la mission, brouillage Zeus ciblé ou de zone, brouilleurs du mod Crows Electronic Warfare. Un relais dans une zone brouillée perd aussi son débit.",
        "Les relais qui entendent un appareil permettent de le localiser par triangulation (carte, clic droit)."
    ]],
    ["Envois, latence et file d'attente", "queue", [
        "Un seul canal radio par téléphone : les envois partent l'un après l'autre. Une photo de 300 Ko à 500 kbit/s occupe le canal près de 5 s et retarde le message suivant.",
        "Délai d'un envoi : attente du canal, latence (avec gigue), puis taille divisée par le débit.",
        "Paquets perdus : renvoi automatique, 3 essais. Au troisième échec, l'envoi retourne en file : il n'arrive pas tant que le lien ne le permet pas.",
        "Sans signal ou en réception seule, les envois attendent en file (50 au plus). MEDEVAC, alertes, JTAC et comptes rendus passent devant et ne sont jamais abandonnés. Au retour du réseau, la file part par paquets de 5 toutes les 2 s.",
        "Hors connexion, le téléphone garde ses dernières données et ce que les autres vous envoient attend au relais : tout arrive au retour du signal, avec un bilan."
    ]],
    ["Le téléphone", "", [
        "Batterie : émettre consomme, chercher le réseau avec un signal faible aussi. Sous 10 %, le téléphone réduit sa puissance d'émission (-20 % de débit).",
        "Antennes externes (port SMA) : courte x1,25, fouet x1,5, patch 9 dBi x1,7 à l'arrêt (mal orientée en mouvement). Elles remplacent aussi une antenne interne cassée.",
        "Terminal de bord d'un véhicule : antenne de toit, +3 dB. Dans un véhicule sans terminal, la carrosserie coûte 20 % du débit.",
        "Dégâts : un boîtier ou une antenne touchés réduisent le débit. Mode avion : aucun réseau, le Bluetooth reste."
    ]],
    ["Dans Eden : ce que règle la mission", "", [
        "Module ATAK · Relais (Modules > COMSPEC — ATAK · Réseau) : type (radio portable 1,5 km, mât tactique 3 km, tour fixe 6 km, terminal satellite 1 km), portée (cercle du module), débit, hauteur d'antenne, places, camp, canal, raccordé ou répéteur, batterie et autonomie.",
        "Pylônes relais 3D (Objets > Structures > Relais ATAK) : relais prêts à poser. Menu COMSPEC ATAK > TBR : tableau de tous les relais de la mission, répéteurs isolés signalés.",
        "Conseils : placez les relais en hauteur et en vue directe des zones de jeu ; une tour sur une crête couvre plus qu'un mât dans un vallon ; une forêt dense se couvre par un relais haut au-dessus de la canopée, pas par un relais bas à l'orée.",
        "Réglages serveur (CBA, COMSPEC) : Débit réseau simulé, Réalisme de la simulation radio (Réaliste, Exigeant, Simplifié), Météo sur le réseau et son intensité, Surcharge du réseau, Trafic de fond, Pertes de paquets, Perte minimale, Latence ajoutée, Réseau exclusivement par relais, Passerelles de véhicule et PC mobile, Fonctionnement hors connexion, Flux vidéo selon les relais. Case du module : Relais obligatoires. En partie, le menu Échap > Conditions réseau les change pour tout le serveur.",
        "Variables de mission : COMSPEC_ATAK_Jammers = [[position, rayon], ...] pose des brouilleurs ; COMSPEC_ATAK_MeshRange règle la portée du maillage entre téléphones (3 000 m)."
    ]],
    ["En jeu : voir le réseau", "", [
        "Page Réseau du téléphone : barres, débit, latence, pertes, relais utilisé et chaque cause qui coûte du signal, en dB.",
        "App Wave Relay : MON NŒUD, RÉSEAU (nœuds, LOCALISER), COUVERTURE (carte de débit des relais), TALKGROUPS.",
        "Carte : calque Relais (portée, débit, places, canal, santé) ; clic sur un relais : sa fiche, et le choisir comme relais préféré.",
        "ACE sur un relais : état, allumer, couper, changer la batterie. App Debug : journal des transferts (envoi, livré, perdu, remis en file)."
    ]]
];

// Fenêtre : fond assombri, panneau centré, titre, sommaire, contenu défilant, bouton Fermer.
private _w = safeZoneW * 0.66;
private _h = safeZoneH * 0.8;
private _x0 = safeZoneX + (safeZoneW - _w) / 2;
private _y0 = safeZoneY + (safeZoneH - _h) / 2 + safeZoneH * 0.015;
private _u = safeZoneH * 0.006;
private _dim = _d ctrlCreate ["ctrlStatic", -1];
_dim ctrlSetPosition [safeZoneX, safeZoneY, safeZoneW, safeZoneH];
_dim ctrlSetBackgroundColor [0, 0, 0, 0.55];
_dim ctrlCommit 0;
private _bg = _d ctrlCreate ["ctrlStatic", -1];
_bg ctrlSetPosition [_x0, _y0, _w, _h];
_bg ctrlSetBackgroundColor [0.12, 0.13, 0.12, 0.98];
_bg ctrlCommit 0;
private _title = _d ctrlCreate ["ctrlStatic", -1];
_title ctrlSetPosition [_x0, _y0 - 5 * _u, _w, 5 * _u];
_title ctrlSetBackgroundColor [0.10, 0.30, 0.16, 1];
_title ctrlSetTextColor [1, 1, 1, 1];
_title ctrlSetText (("  COMSPEC ATAK · Réseau et simulation") call comspec_atak_native_fnc_tr);
_title ctrlCommit 0;
private _lw = _w * 0.25;
private _list = _d ctrlCreate ["ctrlListbox", 100];
_list ctrlSetPosition [_x0 + _u, _y0 + _u, _lw, _h - 8 * _u];
_list ctrlCommit 0;
{ _list lbAdd ((_x select 0) call comspec_atak_native_fnc_tr); } forEach _sections;
private _gx = _x0 + _lw + 3 * _u;
private _gw = _w - _lw - 4 * _u;
private _grp = _d ctrlCreate ["ctrlControlsGroup", 101];
_grp ctrlSetPosition [_gx, _y0 + _u, _gw, _h - 8 * _u];
_grp ctrlCommit 0;
private _iw = _gw - 3 * _u;
// Schéma 2:1 en pixels carrés.
private _ih = (_iw / pixelW / 2) * pixelH;
private _pc = _d ctrlCreate ["ctrlStaticPictureKeepAspect", 102, _grp];
_pc ctrlSetPosition [0, 0, _iw, _ih];
_pc ctrlCommit 0;
private _tx = _d ctrlCreate ["ctrlStructuredText", 103, _grp];
_tx ctrlSetPosition [0, _ih + _u, _iw, _h];
_tx ctrlCommit 0;
private _b = _d ctrlCreate ["ctrlButton", -1];
private _bw = _w * 0.16;
_b ctrlSetPosition [_x0 + _w - _bw - _u, _y0 + _h - 6 * _u, _bw, 5 * _u];
_b ctrlSetText ("Fermer" call comspec_atak_native_fnc_tr);
_b ctrlAddEventHandler ["ButtonClick", { (ctrlParent (_this select 0)) closeDisplay 2; }];
_b ctrlCommit 0;
private _note = _d ctrlCreate ["ctrlStructuredText", -1];
_note ctrlSetPosition [_x0 + _u, _y0 + _h - 6 * _u, _w - _bw - 3 * _u, 5 * _u];
_note ctrlSetStructuredText parseText (((format ["<t size='0.85' color='#9AA59C'>%1</t>", "Mod ATAK natif COMSPEC · le détail de chaque calcul s'affiche en jeu sur la page Réseau du téléphone." call comspec_atak_native_fnc_tr])) call comspec_atak_native_fnc_tr);
_note ctrlCommit 0;

// Affichage d'une section : schéma (ou rien), titre, paragraphes à puces ; hauteur du texte ajustée pour le défilement.
uiNamespace setVariable ["COMSPEC_ATAK_NetGuide", [_sections apply { [(_x select 0) call comspec_atak_native_fnc_tr, [(_x select 1) call _pic, ""] select ((_x select 1) isEqualTo ""), (_x select 2) apply { _x call comspec_atak_native_fnc_tr }] }, _ih, _u]];
private _show = {
    params ["_d", "_i"];
    (uiNamespace getVariable ["COMSPEC_ATAK_NetGuide", [[], 0, 0]]) params ["_secs", "_ih", "_u"];
    private _s = _secs param [_i, []];
    if (_s isEqualTo []) exitWith {};
    _s params ["_t", "_p", "_paras"];
    private _pc = _d displayCtrl 102;
    private _tx = _d displayCtrl 103;
    _pc ctrlSetText ((_p) call comspec_atak_native_fnc_tr);
    _pc ctrlShow (_p isNotEqualTo "");
    private _y = [0, _ih + _u] select (_p isNotEqualTo "");
    private _body = format ["<t size='1.35' color='#7ED38A' font='RobotoCondensedBold'>%1</t><br/>", _t];
    { _body = _body + format ["<br/><t color='#7ED38A'>• </t>%1<br/>", _x]; } forEach _paras;
    _tx ctrlSetStructuredText parseText ((_body) call comspec_atak_native_fnc_tr);
    private _pos = ctrlPosition _tx;
    _tx ctrlSetPosition [_pos select 0, _y, _pos select 2, 0.01];
    _tx ctrlCommit 0;
    _tx ctrlSetPosition [_pos select 0, _y, _pos select 2, (ctrlTextHeight _tx) + 2 * _u];
    _tx ctrlCommit 0;
    (_d displayCtrl 101) ctrlSetScrollValues [0, -1];
};
uiNamespace setVariable ["COMSPEC_ATAK_NetGuideShow", _show];
_list ctrlAddEventHandler ["LBSelChanged", {
    params ["_c", "_i"];
    [ctrlParent _c, _i] call (uiNamespace getVariable "COMSPEC_ATAK_NetGuideShow");
}];
_list lbSetCurSel ((_sec max 0) min ((count _sections) - 1));
[_d, lbCurSel _list] call _show;
ctrlSetFocus _list;
