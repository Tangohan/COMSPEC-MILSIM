# Guide chef de mission & Zeus

Configuration OP, modules Eden/Zeus, et réalisme pour **COMSPEC Overwatch**.

---

## Avant la mission

### Checklist serveur

- Mod **@COMSPECOverwatch** côté serveur **et** clients
- **CBA_A3** chargé avant Overwatch
- **COMSPECExtension_x64.dll** présente à la racine du mod (pas seulement les PBO)
- Clé **communauté Athena** valide dans les paramètres serveur ou briefing
- Carte / **identifiant carte** cohérent avec Tacmap (défaut : carte 1)

### Briefing joueurs

Indiquez :

- URL ou nom du portail Athena
- Indicatif attendu (libre ou imposé par groupe)
- Terminal requis ou non (tablette / item)
- Niveau de **réalisme liaison** prévu (voir [realisme-liaison-atak.md](realisme-liaison-atak.md))

---

## Modules Eden — zones roleplay

Catégorie **COMSPEC Roleplay** (addon connect) :

| Module | Effet sur les opérateurs |
|---|---|
| **Zone sans couverture ATAK** | Liaison totalement coupée dans le rayon |
| **Zone d’interférence** | Pertes de transmission élevées |
| **Couverture dégradée** | Latence + pertes modérées |
| **Brouilleur ATAK actif** | Coupures intermittentes + pertes |

Paramètres communs : **rayon (m)**, **intensité (%)**.

**Conseil OP** : placer les brouilleurs sur des objectifs story (poste radio ennemi, convoi ECM) plutôt que sur toute la map.

---

## Zones depuis le portail (option)

Les admins peuvent définir des **zones roleplay** sur le portail (carte + type + intensité). Si l’option est activée pour la communauté, le mod **synchronise** ces zones en plus des modules Eden (~ toutes les 90 secondes après liaison).

Priorité en cas de chevauchement : traiter comme cumul d’effets — tester en préalable sur serveur de dev.

---

## Réalisme terminal (dommages ATAK)

Réglage **niveau de réalisme** (communauté ou mission) :

| Niveau | Comportement |
|---|---|
| 0 | Désactivé |
| 1 | ATAK peut s’éteindre temporairement (torse blessé) |
| 2 | Écran endommagé possible — liaison partielle |
| 3 | Appareil peut être détruit — déconnexion Athena |

Facteurs supplémentaires (1.3.0+) : chocs, explosion proche, bras très blessé, gravité thoracique (KAT).

---

## Certificats & terminaux

Si votre communauté active le **réalisme certificat** :

- Chaque installation de jeu possède une **identité terminal** stable
- L’**appairage automatique** peut être imposé ou désactivé par le staff
- Les opérateurs voient leur statut (actif, en attente, expiré) dans le hub

Utile pour OP exigeant : seuls les certificats **actifs** participent à certaines fonctions (évolution future SSE / HUMINT certifié).

---

## Intelligence & POI

Le portail gère des **points d’intérêt** (objectifs, caches, positions ennemies, **personnes HVT**, etc.).

En mission vous pouvez :

- Marquer un **HVT** via outils carte web (JACKPOT) ou POI in-game
- Lier photos recon et rapports SALUTE — le TOC fusionne côté Athena

**Terminal SSE** (en développement) : voir [terminal-sse-renseignement.md](terminal-sse-renseignement.md).

---

## cTab / BCE / Mavic

| Mod tiers | Rôle |
|---|---|
| **cTab / ATAK Enhanced** | Apps, photos, marqueurs — pont `atak_athena` |
| **BCE** | CAS, photos, feeds — API publique Iceman uniquement |
| **Mavic** | Compat via `mavik_compat` si drone présent |

Ne dupliquez pas les modules BCE : activez le pont Athena dans le **catalogue modules** admin.

---

## Déroulé type OP avec roleplay

1. **Briefing** — slides Google ou mission maker, hub fermé
2. **Insertion** — grâce respawn 30 s (pas de spam liaison)
3. **Phase contact** — SALUTE / photos / marqueurs
4. **Phase dégradée** — activer brouilleur Zeus ou zone portail
5. **Exfil** — déconnexion propre ; debrief AAR sur portail

---

## Dépannage OP

| Problème | Action |
|---|---|
| Personne n’apparaît pas Tacmap | Indicatif, terminal, état mission (briefing terminé) |
| Zones Zeus sans effet | Vérifier roleplay activé communauté + module bien synchronisé |
| Double marqueurs | Normal si cTab + Arma — filtrage web côté Athena |

---

## Voir aussi

- [Architecture du mod](architecture-et-addons.md)
- [Compilation](compilation-et-publication.md) — rebuild après changement SQF
