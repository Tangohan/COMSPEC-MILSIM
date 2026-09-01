# Zeus — erreur en posant un point carte (Intelligence numérique)

## Contexte

Zeus, menu **Intelligence numérique** ou module **Poser un point carte**. SSE 0.7.18.

## Symptôme

Un message d’erreur apparaît (`isNull` attend un objet, reçoit un tableau). Le formulaire de point carte ne s’ouvre pas.

## Cause

Le menu Zeus Enhanced passe parfois une liste (objets ou groupes sélectionnés) à la place d’un seul objet. Le script appelait `isNull` sans vérifier le type.

## Correctif

On ne teste un objet que s’il s’agit vraiment d’un objet. La position vient soit du clic, soit du module. Le premier objet de la sélection sert de support s’il n’est pas une personne.

## Fichiers touchés

- `mod/@COMSPEC_SSE/addons/zeus/functions/fn_registerZenDomexLive.sqf`
- `mod/@COMSPEC_SSE/addons/zeus/functions/fn_domexPickObject.sqf`
- `mod/@COMSPEC_SSE/addons/main/script_mod.hpp` (0.7.19)
- `tests/Unit/SseZeusDomexMapPointAssetTest.php`

## Vérification

Pack SSE 0.7.19, relancer Arma. Zeus → clic droit → Intelligence numérique → Poser un point carte : le formulaire s’ouvre, le point apparaît sur la carte du bureau.

## Statut

Corrigé (rebuild du pack jeu requis)
