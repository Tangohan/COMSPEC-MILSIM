# Guide joueur — COMSPEC Overwatch

Comment utiliser le terminal tactique et la liaison Athena en mission.

---

## Première liaison

1. Rejoignez le serveur avec le mod **@COMSPECOverwatch** activé.
2. Au démarrage, le mod tente la liaison avec **Athena** (votre communauté).
3. Si demandé : saisissez la **clé communauté** ou liez votre compte (écran « Connexion Athena »).
4. Choisissez un **indicatif** (callsign) — il apparaît sur la carte du poste de commandement.

**Indicateur hub** : ouvrez le centre Overwatch (**Ctrl+Shift+K** ou touche configurée) → badge « En liaison » / « Hors liaison ».

---

## Raccourcis utiles

| Action | Raccourci par défaut |
|---|---|
| Hub Overwatch (menu principal) | **Ctrl+Shift+K** |
| Messagerie rapide | **Ctrl+K** |
| Tablette / apps tactiques | **K** (selon configuration) |

Les menus **ACE** (« ATAK Tactique ») restent disponibles si votre communauté les active.

---

## Hub Overwatch — que faire ?

Depuis le hub vous pouvez :

- Voir l’état de **liaison** et la version du pack
- Ouvrir la **messagerie**, les **ordres**, le **briefing**
- Envoyer un **rapport tactique** (contact, SALUTE, etc.)
- Demander **appui aérien**, **évacuation sanitaire**, **renfort**
- Consulter votre **profil** et l’état du **terminal** (certificat, réalisme)

---

## Carte et marqueurs

- Les marqueurs posés sur la **carte Arma** peuvent remonter sur **Tacmap** (si la synchronisation est activée).
- Les **points d’intérêt** créés via ACE (LZ, renfort, etc.) apparaissent aussi côté web.
- Ne placez pas de marqueurs sur l’**origine carte (0,0)** — ils sont ignorés par la liaison.

---

## Photos et reconnaissance

- **Photo Library** (cTab / BCE) : les photos peuvent être envoyées au portail (panneau Cams).
- Capture **Athena** : certaines vues permettent d’envoyer une image de reconnaissance avec position et cap.

---

## Réalisme liaison (si activé par votre communauté)

Votre staff peut simuler un environnement radio dégradé. En jeu vous pouvez voir :

| Situation | Ce que vous observez |
|---|---|
| Coupure réseau | Overlay « Liaison perdue », plus de remontée données |
| Zone sans couverture | Entrée zone → alerte, liaison coupée |
| Brouillage | Pertes affichées, coupures intermittentes |
| Écran endommagé | Message « Écran endommagé » — **position seule** encore envoyée |
| ATAK éteint | Écran noir — rallumage via ACE |
| Terminal bloqué | Gel temporaire (distinct d’une coupure réseau) |

**Réparation** : ACE → interaction sur soi → actions « Rallumer l’ATAK » / « Réparer l’écran » (trousse requise pour l’écran).

---

## Rapports tactiques

Types disponibles selon mission :

- **CONTACT** — contact ennemi
- **SALUTE** — taille, activité, localisation, uniforme, temps, équipement
- **SPOTREP** — observation
- **SITREP** — situation

Remplissez les champs en **français clair**. Le poste de commandement reçoit le rapport sur Athena.

---

## Medical (ACE / KAT)

Si **KAT Medical** est présent, des données supplémentaires (saturation, voies aériennes, gravité thoracique) peuvent alimenter les alertes sanitaires sur le portail — sans action de votre part.

---

## Déconnexion / crash

- **Quit propre** : le mod signale la fin de session.
- **Crash ou coupure brutale** : le portail conserve une **fenêtre de reprise** (~10 min) pour retrouver indicatif et état terminal au reconnect (JIP).

---

## Problèmes fréquents

| Symptôme | Piste |
|---|---|
| « Hors liaison » permanent | Vérifier clé communauté, URL Athena, pare-feu |
| Position absente sur Tacmap | Indicatif vide, pas en mission active, ou pas de terminal requis |
| Hub sans overlays roleplay | Roleplay désactivé côté communauté |
| Marqueurs absents web | Attendre fin du handshake (~30 s après spawn) |

---

## Voir aussi

- [Réalisme liaison ATAK](realisme-liaison-atak.md) — détail des effets
- [Guide chef de mission](guide-chef-mission.md) — zones Zeus, configuration OP
