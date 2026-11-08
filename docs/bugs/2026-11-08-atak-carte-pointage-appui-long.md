# Carte : pointer un endroit en maintenant le clic gauche (comme sur la carte du jeu)

- **Contexte** : demande du propriétaire (fonction, pas une panne).
- **Avant** : le pointage partagé passait par Ctrl + clic ou par le menu du clic droit.
- **Changement** : en mode sélection, sans outil de pose ni menu ouvert, maintenir le clic gauche 0,6 s sans bouger envoie le ping existant à tout le camp (`fn_mapPing`, 10 s à l'écran des autres). Un anneau se remplit pendant l'appui. Le relâchement qui suit ne déplace rien. Bouger de plus de 8 pixels annule.
- **Fichiers touchés** : `fn_mapMouseButtonDown.sqf`, `fn_mapMouseButtonUp.sqf`, `fn_mapOnDraw.sqf`.
- **Vérification** : à tester en jeu, appui long sur la carte : ping reçu par un coéquipier ; glisser pour déplacer la carte : aucun ping ; clic court : fiche de l'unité ou du marqueur comme avant.
- **Statut** : corrigé à vérifier en jeu (ATAK 3.1.0).
