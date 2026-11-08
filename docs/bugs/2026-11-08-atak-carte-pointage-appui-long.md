# Carte : pointer un endroit en maintenant le clic gauche (comme sur la carte du jeu)

- **Contexte** : demande du propriétaire (fonction, pas une panne).
- **Avant** : le pointage partagé passait par Ctrl + clic ou par le menu du clic droit.
- **Changement** : en mode sélection, sans outil de pose ni menu ouvert, maintenir le clic gauche 0,6 s sans bouger envoie le ping existant à tout le camp (`fn_mapPing`, 10 s à l'écran des autres). Un anneau se remplit pendant l'appui. Le relâchement qui suit ne déplace rien. Bouger de plus de 8 pixels annule.
- **Fichiers touchés** : `fn_mapMouseButtonDown.sqf`, `fn_mapMouseButtonUp.sqf`, `fn_mapOnDraw.sqf`.
- **Vérification** : à tester en jeu, appui long sur la carte : ping reçu par un coéquipier ; glisser pour déplacer la carte : aucun ping ; clic court : fiche de l'unité ou du marqueur comme avant.
- **Statut** : corrigé à vérifier en jeu (ATAK 3.1.0).

## Reprise : le pointage ne marche pas en jeu (retour sur la 4.0.0)

- **Cause établie par lecture, non observée en jeu** : l'appui n'était suivi qu'à travers `cursorPos`, position du monde relevée par `MouseMoving`. Si la carte avait bougé sans la souris depuis (zoom à la molette, suivi de ma position, fin de glissement), elle ne correspondait plus au point d'appui et l'appui était annulé dès la première image, sans rien afficher. Inversement, un glissement qui déplace la carte n'annulait pas. Aucun réglage ne le coupait ; fonction et événement étaient enregistrés.
- **Écart avec la demande** : le code envoyait un ping unique de 10 s avec bandeau et vibration après 0,6 s, là où la carte du jeu montre un pointeur qui suit le curseur tant que le bouton reste appuyé.
- **Correctif** : comparaison à l'écran entre `getMousePosition` et le point d'appui ; pointeur après 0,4 s, qui suit le curseur et s'arrête au relâchement, même hors de la carte (`fn_mapPing` « point… »). Réseau : 4 envois par seconde au plus en mouvement, 1 par seconde à l'arrêt, puis la fin ; sans nouvelle depuis 2,5 s, le pointeur disparaît. Destinataires : mon camp, avec un téléphone, comme le ping.
- **Vérification** : à deux joueurs du même camp, voir la PR.
- **Statut** : corrigé à vérifier en jeu.
