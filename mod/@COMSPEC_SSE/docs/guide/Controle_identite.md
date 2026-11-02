# Contrôle d'identité (COMSPEC SSE 1.1.0)

Papiers d'identité **fictifs** (pays imaginaires, mention SIMULATION sur chaque document) et contrôle sur le terrain,
par le menu ACE et l'app **Contrôle ID** du téléphone ATAK. Fonctionne entièrement hors ligne ; avec COMSPEC Link 2.0.118
ou plus (pack ATAK 3.0.0), les documents d'Athena sont chargés et la base en ligne complète le contrôle.

## Les documents

| Document | Bande MRZ | Puce |
|----------|-----------|------|
| Carte d'identité | 3 lignes (TD1, « I< ») | oui |
| Passeport | 2 lignes (TD3) | oui |
| Permis de conduire | 3 lignes (« D< ») | non |

Pays : **République d'Altis** (ALT, Altis et Stratis), **Fédération de Tanoa** (TNA), **République de Livonie** (LVN,
Livonia), **République de Malden** (MLD). Réglage « Pays des papiers » pour l'imposer.

Qui a des papiers :
- **Joueurs** : rien par défaut. Trois moyens d'en donner : attribut Eden « COMSPEC SSE — Papiers », objet du kit
  (une carte, un passeport ou un permis pris à l'Arsenal devient à l'apparition un document à son nom), module Zeus
  « Donner des papiers ».
- **IA** : selon leur camp (réglage « Papiers des IA ») : les civils ont presque toujours une carte, parfois un permis ;
  les ennemis ont souvent de faux papiers ; une HVT (biométrie : watchlist tier 1) porte une couverture préparée ;
  un recherché (tier 2) porte ses vrais papiers.
- Les papiers d'une IA ne deviennent des objets qu'au besoin (fouille, mort, remise) : aucun coût tant qu'on ne la
  contrôle pas.

Nature des papiers (jamais visible par les joueurs, seulement par le Zeus) : vrais papiers, couverture préparée (passe
tous les contrôles sauf la biométrie), faux papiers (contrefaçon, vrai document modifié, photo substituée, vierge volé),
avec une qualité de 0 à 100.

## Contrôler quelqu'un

Menu ACE sur la personne › **Contrôle d'identité** :
1. **Demander les papiers** (2 s). Une IA répond (elle tend ses papiers, refuse, fuit…). Un joueur reçoit la demande
   sur son ATAK et répond par son menu ACE personnel › **Mes papiers** : *Présenter* (on peut contrôler le document
   pendant 2 minutes), *Remettre* (l'objet passe dans votre inventaire) ou *Refuser*.
2. **Contrôler l'identité** › le document présenté › **Examiner** (2 s) : la visionneuse s'ouvre (recto / verso, zoom).
   C'est le contrôle visuel **N0** : un faux mal fait se voit (hologramme absent, police différente, photo rapportée).
   La nuit, éclairez le document.
3. **Scanner avec l'ATAK** : l'app Contrôle ID reprend le document :
   - **N1 · MRZ** (3 s) : chiffres de contrôle ICAO et concordance avec le recto ;
   - **N2 · puce NFC** (4 s, à 1,5 m du document) : signature et données de la puce ;
   - **N3 · base** (3 s) : base du pays (numéro délivré, volé, révoqué, avis de recherche) ; en ligne, Athena.
4. Bandeau de verdict : RAS, doute, faux, volé, révoqué, personne signalée, recherchée, HVT. Une personne signalée fait
   vibrer le téléphone (sans son en mode discrétion).
5. **Transmettre à Athena** (file d'attente sans liaison) et **Joindre à une FRM** (fiche pré-remplie dans l'app FRS).

Sans accord :
- **Fouiller les papiers** (6 s, 4 s sur un corps) : mort, menotté, rendu ou inconscient. Un joueur fouillé est prévenu
  sur son ATAK. Les documents trouvés s'examinent ou se **saisissent** (2 s, chaîne de possession SSE) ; **Rendre un
  document** (1 s) les restitue.

Véhicule : menu ACE du véhicule arrêté › Contrôle d'identité › **Contrôler les occupants** (chaque IA répond, chaque
joueur reçoit la demande) ou **Faire descendre**.

Onglet **HISTORIQUE** de l'app : les 50 derniers contrôles du joueur.

## Zeus

Catégorie « COMSPEC SSE · Identité » :
- **Donner des papiers** : type, pays, nature, procédé et qualité du faux, nom imprimé, ou numéro d'un document Athena.
- **Signaler volé / révoquer** : visible au contrôle N3.
- **Voir les vrais papiers** : vraie identité, nature, qualité, défauts (Zeus seulement).

## Réglages (CBA › COMSPEC SSE › Identité)

Contrôle d'identité · Difficulté (arcade / standard / réaliste) · Pays des papiers · Papiers des IA · Civils qui
présentent leurs papiers (90 %) · Insurgés porteurs de faux papiers (60 %) · Durée d'une présentation (120 s) ·
Transmission automatique à Athena (non) · Documents et avis d'Athena · Opération Athena.

Joueur formé au contrôle (meilleur examen visuel) : `_unit setVariable ["COMSPEC_ID_Expert", true, true]`.

## Pour les scripts (Lot 3 : checkpoints)

- `["aiAsk", [ia, joueur]] call comspec_sse_fnc_idServer` (serveur) : une IA demande ses papiers à un joueur.
- Évènements CBA serveur : `comspec_sse_idAsked`, `comspec_sse_idPresented` [porteur, docId, contrôleur],
  `comspec_sse_idRefused`, `comspec_sse_idChecked` [contrôleur, docId, niveau, résultat].
- `[_doc, _ia, niveau] call comspec_sse_fnc_idEvaluate` (serveur) : contrôle par une IA.

## Vérifier la MRZ

`python tools/check_id_mrz.py` (hors jeu) et `tools/test_id_mrz.sqf` (console de débogage) : spécimen ICAO
(L898902C3 → 6, 740812 → 2, 120415 → 9, ZE184226B → 1, global → 0), même écriture que le site.
