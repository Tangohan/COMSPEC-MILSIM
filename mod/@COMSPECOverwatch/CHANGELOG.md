# Changelog — COMSPEC Overwatch

Format inspiré de [Keep a Changelog](https://keepachangelog.com/fr/1.0.0/). Numérotation :
`connect` (addon principal, `versionStr` dans `addons/connect/config.cpp`) et `COMSPECExtension`
(DLL, `RVExtensionVersion`) sont versionnés séparément — les deux sont indiqués par entrée
quand la DLL a changé.

## [1.2.0] — connect · COMSPECExtension 1.17

### Ajouté
- Réglage CBA **« Détection terminal (position) »** (`comspec_overwatch_terminal_mode`) : choix
  entre slot d'objet assigné uniquement (`ItemAndroid` équipé), présence en inventaire
  uniquement (`ItemAndroidMisc`), ou les deux (par défaut). Avant, seul `ItemAndroid` en
  emplacement assigné était reconnu, sans possibilité de configuration.
- Ligne **« Dernier échec d'envoi »** dans l'instantané technique de la tablette (nouvelle
  fonction extension `GetLastPostError`) : les envois fire-and-forget (position, tchat,
  marqueurs) ne remontaient jamais leurs échecs à SQF (retry silencieux en boucle côté
  extension) — on voit maintenant le code HTTP ou l'erreur réseau exacte, avec son âge.

### Corrigé
- **Régression critique** : `fn_hasTerminal.sqf` appelait `isKindOf` sur deux chaînes de
  caractères (classnames issus de `assignedItems`/`items`), signature invalide qui
  provoquait une erreur script à chaque appel et faisait toujours échouer la détection —
  plus aucune position n'était remontée à Athena, quel que soit l'inventaire réel du
  joueur. Remplacé par un test d'appartenance simple (`_x in _classes`).
- Fausse alerte médicale (arrêt cardiaque / inconscient) déclenchée au moment de la
  déconnexion (fin de mission / retour menu) : `fn_checkMedicalAlerts.sqf` n'évalue plus
  l'état santé une fois `COMSPEC_DisconnectSent` positionné.
- `fn_forceSyncData.sqf` (bouton **« Transmettre ma position et mes données »**) : le
  cooldown anti-spam de 8s n'était posé qu'après la vérification du terminal — un joueur
  sans S7 Android en inventaire pouvait spammer le bouton sans aucune limite, republiant
  "Équipez le téléphone S7 Android" à chaque clic. Le cooldown est maintenant posé avant
  toute vérification.

### Notes de déploiement (backend Athena, hors versionnage mod)
- La table `atak_units` en production avait pris du retard sur `migrations/schema.sql`
  (colonnes `military_id`, `pos_x`, `pos_y` absentes — `CREATE TABLE IF NOT EXISTS` ne
  modifie jamais une table déjà existante). Corrigé manuellement via `ALTER TABLE`; à
  terme le pipeline de migrations doit détecter ce genre de dérive au lieu de rester
  silencieux ("Schéma OK" alors qu'une table réelle diverge).

---

## [1.1.3] et antérieures

Historique non documenté rétroactivement (avant la mise en place de ce changelog). Voir
l'historique Git pour le détail des commits.
