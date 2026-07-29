## Documentation technique — mod COMSPEC Overwatch

Documentation destinée aux **moddeurs** et **intégrateurs** du pack Arma **@COMSPECOverwatch** (version documentée **1.4.0**).

Cette page décrit l’architecture côté jeu (addons SQF + extension native), les dépendances et les notes de compilation. Elle ne couvre pas le fonctionnement interne du portail Athena, ni ses mécanismes d’authentification.

## Public

| Document | Public |
|---|---|
| Architecture & addons | Moddeurs, intégrateurs |
| Bibliothèques & mods utilisés | Intégrateurs, staff serveur |
| Compilation & publication | Build local, Workshop |
| Guide joueur (portail) | Opérateurs — voir le guide Overwatch intégré |

## En bref

**COMSPEC Overwatch** relie une session Arma 3 au poste de commandement web Athena :

- Carte tactique partagée (positions, groupes, véhicules)
- Messagerie et ordres
- Photos de reconnaissance, marqueurs, rapports (SALUTE, SPOTREP…)
- **Renseignement interpersonnel (SSE)** : fiches personnes, photo du visage → onglet Personnes Athena
- Tablette / téléphone tactique in-game
- Réalisme liaison (coupures, zones sans couverture, dommages terminal)
- Pont optionnel **ATAK Enhanced / cTab / BCE** (addon `atak_athena`)

Le mod **ne remplace pas** cTab ou BCE : il les **complète**.

## Prérequis côté jeu

- **Arma 3**
- **CBA_A3** (obligatoire)
- **COMSPECExtension_x64.dll** à la racine du pack (fournie avec le build)
- Optionnel : ACE, cTab / ATAK Enhanced, BCE, KAT Medical, Mavic, ACRE2 / TFAR

## Addons du pack (1.4.0)

| Addon | Rôle | Version |
|---|---|---|
| `comspec_overwatch_main` | Socle, logo, métadonnées | 1.4.0 |
| `comspec_overwatch_connect` | Liaison, hub, roleplay, rapports, SSE | 1.4.0 |
| `comspec_overwatch_atak_athena` | Pont cTab / BCE | 1.0.7 |
| `comspec_overwatch_mavik_compat` | Compat drone Mavic (si présent) | 1.4.0 |

## Périmètre de cette documentation

**Inclus :** structure des addons, conventions SQF, catalogue des mods tiers, build / Workshop.

**Exclu volontairement :** détails d’attaque, secrets, jetons, et toute description des interfaces réseau du portail (chemins, formats d’échange, clés).

Les sources Markdown complètes du dépôt mod se trouvent aussi sous `mod/UptoDate/docs/` (équipe développement).
