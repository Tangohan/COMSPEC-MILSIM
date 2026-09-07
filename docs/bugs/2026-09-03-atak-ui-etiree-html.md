# Bug — téléphone ATAK étiré en barre

- **Contexte** : le boîtier 2048×1024 était calé sur 98 % de la largeur d’écran. L’interface de connexion HPP remplissait une dalle trop allongée.
- **Symptôme** : le terminal occupait presque toute la largeur, cartes de connexion écrasées, onglet HOME visible sur le bord.
- **Cause** : largeur d’abord (`0.98 * safeZoneW`) au lieu d’une hauteur bornée ; l’écran n’était pas le HTML du prototype.
- **Correctif** : le boîtier est recentré (hauteur max ~78 %, largeur max ~78 %). La dalle charge l’écran HTML ; les boutons parlent au SQF. Terrain bascule vers la carte en jeu.
- **Fichiers touchés** : `ui/defines.hpp`, `ui/main.hpp`, `web/phone.html`, `functions/fn_web*.sqf`
- **Vérification** : ouvrir le téléphone (K) : boîtier centré, écran Connexion HTML, boutons Athena / réseau local.
- **Statut** : corrigé
