## Fiche opérateur jeu — collecte côté Overwatch

Documentation destinée aux **moddeurs** et à l’équipe portail : ce que le pack observe en session et **quand** il le transmet. Ce n’est pas une fiche RH : ce sont des **données observées**.

---

## Principe

Le Steam ID (identifiant joueur Arma en multijoueur) est **la seule clé de liaison** vers un compte. Le nom Arma, l’indicatif et le pseudo ne servent jamais à associer un compte.

La fiche observée est **séparée** du suivi de position :

| Canal | Contenu | Fréquence |
|---|---|---|
| Position | localisation, radio, état médical live, véhicule | selon déplacement / heartbeat |
| Fiche opérateur | identité, visage, groupe sanguin, loadout, versions, mission | premier contact, puis seulement si ça change |

Le pack **n’écrit pas** la fiche officielle (groupe sanguin, indicatif, identité RH). Il envoie l’observation. Toute correction reste une décision humaine côté portail.

Le message contient à la fois les clés riches du pack et les alias attendus par le registre portail (nom observé, sexe, visage, tenue, loadout à la racine, serveur et mission). Un joueur dont le Steam n’est pas encore associé à un compte de la communauté est tout de même enregistré comme observation, sans créer de compte.

---

## Ce qui est collecté

### Identité

Steam UID, nom Arma, nom de profil, indicatif local, nom lu sur la plaque ACE s’il existe, sexe lu dans la classe d’unité **si** le champ est présent, rang Arma, rôle, groupe, faction, camp, classname d’unité.

Les prénom / nom « détectés » ne sont remplis que si la plaque fournit un nom en plusieurs mots. Sinon ils restent vides.

### Visage

Commande `face` + entrée `CfgFaces` (nom affiché, texture, matériau, types d’identité) lorsque la classe existe. Si la texture n’est pas lisible, le classname suffit. Aucune capture photo automatique à la connexion (trop intrusive).

### Médical d’identité

Groupe sanguin : plaque ACE, sinon variable ACE Medical. État utile (stable / blessé / inconscient, etc.) via le collecteur médical déjà en place. **Rien n’est inventé** (pas de saturation fictive).

### Équipement

Snapshot `getUnitLoadout` : uniforme, gilet, sac, casque, lunettes, JV, armes + optiques, radios ACRE/TFAR si présentes, chargeurs, objets médicaux reconnus. Le loadout brut est joint tant qu’il reste raisonnable ; sinon il est omis et le résumé de classes est conservé.

### Versions

Overwatch, ATAK (addon cTab), DLL, ACE, CBA, ACRE ou TFAR, KAT si chargé, cTab, build Arma. Une chaîne vide = composant absent ou illisible.

### Environnement

Nom de serveur, mission, briefing, carte, multijoueur ou non.

---

## Déclencheurs

Un envoi a lieu notamment :

- première liaison Athena réussie (enregistrement) ;
- changement d’indicatif, de visage, de loadout, de versions, de groupe sanguin ;
- resynch manuel ;
- fin de grâce après respawn ;
- restauration de session.

Un contrôle d’empreinte toutes les 8 s évite de renvoyer la même fiche. Le rythme cardiaque et la position **ne** déclenchent **pas** un snapshot.

---

## Réponse attendue du portail

Le mod lit un accusé compact :

- compte lié ou non ;
- identifiant de fiche observée ;
- nombre d’écarts détectés ;
- mise à jour du pack recommandée.

Si le portail n’a pas encore la route, l’extension considère l’envoi comme **en attente** : la mission continue, un nouvel essai part plus tard.

Une mise à jour recommandée n’est annoncée au joueur **qu’une fois** toutes les dix minutes, en langage courant.

---

## Fichiers

| Fichier | Rôle |
|---|---|
| `fn_collectOperatorIdentity.sqf` | Identité observée |
| `fn_collectOperatorFace.sqf` | Visage / texture |
| `fn_collectOperatorMedical.sqf` | Groupe sanguin + état utile |
| `fn_collectOperatorLoadout.sqf` | Équipement |
| `fn_collectOperatorVersions.sqf` | Versions des composants |
| `fn_collectOperatorEnvironment.sqf` | Serveur / mission |
| `fn_syncOperatorProfile.sqf` | Envoi register / sync |
| `fn_initOperatorProfileSync.sqf` | Boucle et événements |
| `Extension.cs` — `OperatorRegister` / `OperatorSync` | Pont natif, Steam mémorisé, communauté de session |

Addon `connect` **1.5.13** — extension **1.18.7**.
