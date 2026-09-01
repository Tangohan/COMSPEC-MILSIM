# Réalisme liaison ATAK

Fonctionnement du **roleplay réseau et terminal** (pack 1.3.0+).

Document pour **joueurs informés**, **chefs OP** et **admins liaison** — sans détail d’implémentation serveur.

---

## Objectif

Simuler les contraintes d’un **terminal tactique réel** :

- Couverture radio inégale
- Matériel endommagé en combat
- Capteurs parfois faussés
- Reprise après incident

Tout est **configurable par communauté** sur le portail (section roleplay / liaison).

---

## Pipeline « état liaison »

Avant chaque envoi vers Athena, le mod évalue :

1. **Appareil détruit** → plus aucune transmission
2. **Terminal gelé** (blocage temporaire) → plus de transmission
3. **Coupure réseau simulée** → plus de transmission
4. **Zone sans couverture** → plus de transmission
5. **ATAK éteint** → plus de transmission
6. **Écran endommagé** → **position uniquement** (pas ordres, photos, rapports complets)
7. **Sinon** → liaison normale

Le poste de commandement reçoit l’**état liaison** avec chaque position (lié, dégradé, hors ligne).

---

## Effets visibles in-game (hub)

Overlays affichés dans le **centre Overwatch** lorsque le roleplay est actif :

| Overlay | Signification |
|---|---|
| Liaison perdue | Coupure réseau, compte à rebours reconnexion |
| Bandeau zone | Nom zone + intensité (brouillage, dégradé…) |
| Pertes | Pourcentage pertes paquets |
| Écran cassé / éteint | Plein écran message + consignes ACE |
| Flash glitch | Interférence forte |

Sons discrets : déconnexion, reconnexion, zone, choc écran.

---

## Dommages terminal

### Niveaux (réglage communauté)

| Niveau | Effet joueur |
|---|---|
| 1 | Extinction temporaire (~30 s), auto-rallumage |
| 2 | Écran inutilisable, **GPS / position** encore transmis |
| 3 | Appareil inutilisable, **fin de liaison** |

### Déclencheurs (1.3.0+)

- Blessure **torse** (ACE)
- **Bras** très blessé → impossible tenir l’appareil
- **Choc** (impact, explosion proche)
- Complications **thoraciques** (KAT) aggravant le torse
- **SpO2 bas** → capteur cardiaque parfois incohérent (alertes TOC)

### Réparation

| Action ACE | Effet |
|---|---|
| Rallumer l’ATAK | Extinction / gel levé |
| Réparer l’écran | Trousse requise, délai animation |

---

## Zones roleplay

| Type | Effet |
|---|---|
| Sans couverture | Coupure totale |
| Interférence | Pertes élevées |
| Dégradé | Latence + pertes légères |
| Brouilleur | Coupures aléatoires + pertes |

**Sources** : modules **Eden/Zeus** et/ou **zones définies sur le portail**.

Effet visuel optionnel : aberration chromatique / grain (client).

---

## Déconnexion simulée

Deux niveaux peuvent coexister :

- **Client** : coupures aléatoires (intervalle ~10 min, durée 5–30 s)
- **Portail** : latence, pertes, refus temporaire requêtes (simulation serveur)

Le mod et le portail ne partagent pas encore la **même fenêtre** de coupure — prévoir tests OP pour éviter double pénalité extrême.

---

## Reprise après crash / JIP

- **Serveur** mémorise indicatif + état terminal à la déconnexion brutale
- **Joueur JIP** : restauration automatique côté client
- **Portail** : snapshot **10 minutes** pour retrouver indicatif si la mémoire serveur est perdue

Conseil joueur : reconnecter dans les **10 min** pour continuité TOC.

---

## Configuration admin (portail)

Paramètres typiques (libellés métier) :

**Réseau**

- Activer simulation réseau
- Mode (normal, hostile, dégradé, matériel)
- Latence min / max
- Taux pertes paquets
- Coupures : durée min/max, intervalle

**Capteurs**

- Panne capteur cardiaque (%)
- Valeurs erronées (%)
- Données manquantes (%)

**Zones**

- Activer zones géographiques portail
- Liste zones (centre, rayon, type, intensité, nom)

**Réalisme terminal**

- Niveau dommages 0–3
- Appairage certificat automatique ou manuel

---

## Corrélation portail ↔ jeu

| Donnée remontée | Usage TOC |
|---|---|
| État liaison | Pastille opérateur |
| Pertes paquets | Alerte roleplay web |
| SpO2 / voies aériennes / thorax | Triage médical enrichi |
| Mod version | Support / diagnostic |

---

## Bonnes pratiques OP

- Activer roleplay **progressivement** (d’abord zones, puis dommages niv. 1)
- Prévoir **MP ou médical** quand niveau 3 actif
- Annoncer en briefing les **zones sans couverture** story-driven
- Éviter brouilleur permanent sur **100 %** de la map

---

## Évolutions prévues

- Alignement coupure **mod + portail** (même timing)
- Item **brouilleur portable** lié aux zones
- Affichage TOC **appareil endommagé** (icône dédiée)

---

## Données chiffrées (certificat / capture / brouillage)

Lorsque la communauté active **Données chiffrées** dans le mode roleplay portail :

| Situation | Affichage terrain | TOC (poste connecté) |
|---|---|---|
| Certificat manquant, expiré ou révoqué | Trafic **illisible** (journal, ordres, marqueurs, grilles, détails) | Lecture claire |
| Appareil **capturé** ou **compromis** (Zeus ou action ACE sur un joueur à terre) | Illisible jusqu’à reprise du contrôle | Alerte appareil + lecture claire |
| Brouillage / interférence forte | **Corruption partielle** du signal | Bandeau « Brouillage » éventuel |

Ce n’est **pas** un vrai chiffrement technique : le serveur conserve les données en clair pour le poste de commandement légitime, et masque l’affichage selon l’appareil qui consulte.

**Alertes TOC** : panneau liaison → « Alertes appareils » (hors liaison, certificat invalide, capturé, endommagé, brouillage).

**Zeus** : actions Capturer / Compromettre / Lever capture dans le panneau ATAK joueur.

---

## Voir aussi

- [Guide joueur](guide-joueur.md)
- [Assets visuels](assets-visuels.md) — overlays roleplay
- Portail : doc technique liaison (équipe dev Athena)
