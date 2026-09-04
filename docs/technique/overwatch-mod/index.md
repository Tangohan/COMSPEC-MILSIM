## Documentation technique — mod COMSPEC Overwatch

Documentation destinée aux **moddeurs** et **intégrateurs** du pack Arma **@COMSPECOverwatch**, servie sur `/documentation/references`.

**Références officielles ATHENA C2 :** COMSPEC Overwatch Technical Manual — **TM-A3-11** ; Interface Control Document — **ICD-A3-01** ; Capability Registry — **REG-A3-01**. Index : `docs/README.md`.

Les numéros d’addon ci-dessous peuvent laguer par rapport à `connect/config.cpp` (**1.5.13**) et l’extension (**1.18.7**). Pour le contrat d’interface, utiliser TM-A3-11 / ICD-A3-01.

Cette page décrit l’architecture côté jeu (addons SQF + extension native), les dépendances et les notes de compilation. Elle ne remplace pas les publications contrôlées.

## Public

| Document | Public |
|---|---|
| **Indépendance, couche addons, interop, API** | Moddeurs, intégrateurs, dev portail |
| Architecture & addons | Moddeurs, intégrateurs |
| Fiche opérateur jeu | Moddeurs, équipe portail |
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
- **Fiche opérateur observée** : identité Steam, visage, équipement et versions, distincte du suivi de position
- Pont optionnel **ATAK Enhanced / cTab / BCE** (addon `atak_athena`)

Le mod **ne remplace pas** cTab ou BCE : il les **complète**.

## Prérequis côté jeu

- **Arma 3**
- **CBA_A3** (obligatoire)
- **COMSPECExtension_x64.dll** à la racine du pack (fournie avec le build)
- Optionnel : ACE, cTab / ATAK Enhanced, BCE, KAT Medical, Mavic, ACRE2 / TFAR

## Addons du pack (1.4.11)

| Addon | Rôle | Version |
|---|---|---|
| `comspec_overwatch_main` | Socle, logo, métadonnées | 1.4.11 |
| `comspec_overwatch_connect` | Liaison, hub, roleplay, rapports, SSE | 1.4.11 |
| `comspec_overwatch_atak_athena` | Pont cTab / BCE | 1.0.17 |
| `comspec_overwatch_mavik_compat` | Compat drone Mavic (si présent) | 1.4.11 |

## Document phare — positionnement technique

→ [Indépendance, couche sur les addons, interopérabilité et API](independance-couche-interoperabilite-api.md)

## Périmètre de cette documentation

- [Architecture](architecture.md) — addons, flux, conventions
- [Fiche opérateur jeu](fiche-operateur-jeu.md) — identité observée (Steam, visage, équipement, versions)
- [Bibliothèques & dépendances](bibliotheques-et-dependances.md)
- [Compilation](compilation.md)
- [Indépendance / interop](independance-couche-interoperabilite-api.md)

**Exclu volontairement :** détails d’attaque, secrets, jetons, et toute description des interfaces réseau du portail (chemins, formats d’échange, clés).

Les sources Markdown complètes du dépôt mod se trouvent aussi sous `mod/UptoDate/docs/` (équipe développement).
