# Documentation — mod COMSPEC Overwatch

Documentation officielle du pack **@COMSPECOverwatch** (Arma 3 + portail Athena).

**Version pack documentée : 1.4.13** · Dernière mise à jour : juillet 2026

---

## À qui s’adresse cette doc

| Document | Public |
|---|---|
| [Guide joueur](guide-joueur.md) | Opérateurs en mission |
| [Guide chef de mission / Zeus](guide-chef-mission.md) | Éditeur, Zeus, staff OP |
| [Réalisme liaison ATAK](realisme-liaison-atak.md) | Staff realism, admins liaison |
| [Terminal SSE & renseignement](terminal-sse-renseignement.md) | HUMINT, MP, renseignement (vision + roadmap) |
| [Guide SSE — chef de mission, Zeus, automatismes](guide-sse-chef-mission.md) | Éditeur, Zeus, analystes SSE |
| [Philosophie technique — indépendance, couche, interop, API](philosophie-technique.md) | Moddeurs, intégrateurs, dev portail |
| [Architecture du mod](architecture-et-addons.md) | Moddeurs, intégrateurs |
| [Compilation & publication](compilation-et-publication.md) | Build local, Workshop |
| [Assets visuels](assets-visuels.md) | Graphistes, conversion textures |
| [Briefing Google Slides](tuto-slides-briefing.md) | Slides in-game (existant) |

---

## En bref — qu’est-ce que ce mod ?

**COMSPEC Overwatch** relie Arma 3 au **portail Athena** :

- Carte tactique partagée (positions, groupes, véhicules)
- Messagerie et ordres
- Photos de reconnaissance, marqueurs, rapports (SALUTE, SPOTREP…)
- Tablette / téléphone tactique in-game
- Réalisme liaison (coupures, zones sans couverture, dommages terminal)
- Pont optionnel **ATAK Enhanced / cTab / BCE** (addon `atak_athena`)

Le mod **ne remplace pas** cTab ou BCE : il **complète** avec Athena comme poste de commandement web.

---

## Prérequis

- **Arma 3** (client dédié ou hébergé pour le serveur)
- **CBA_A3** (obligatoire)
- **COMSPECExtension_x64.dll** à la racine du mod (fournie avec le pack)
- Compte et **clé communauté** configurés sur Athena (liaison in-game)
- Optionnel : ACE, cTab / ATAK Enhanced, KAT Medical, BCE

---

## Versions des addons

| Addon | Rôle | Version (1.4.11) |
|---|---|---|
| `comspec_overwatch_main` | Socle, logo, métadonnées | 1.4.11 |
| `comspec_overwatch_connect` | Liaison Athena, hub, roleplay, rapports | 1.4.11 |
| `comspec_overwatch_atak_athena` | Pont cTab / BCE | 1.0.17 |
| `comspec_overwatch_mavik_compat` | Compat drone Mavic (si mod présent) | 1.4.11 |

Historique détaillé : [`@COMSPECOverwatch/CHANGELOG.md`](../@COMSPECOverwatch/CHANGELOG.md)

---

## Support & signalement

- Erreur en mission : menu hub → signalement / diagnostic (remontée Athena si activée)
- Portail : hard-refresh après mise à jour web (`Ctrl+F5`)
- Après mise à jour du mod : **relancer Arma** (PBO + DLL)

---

## Règles de rédaction de cette doc

- Textes **métier** : pas de vocabulaire technique exposé au joueur dans les guides usage
- **Pas de noms de structures de stockage** : on parle de « fiches », « configuration communauté », « historique mission »
- **Pas de schémas vectoriels** : explications en prose et tableaux
