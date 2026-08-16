# SSE — S7 Android non reconnu pour photographier

## Contexte

Opérateur avec **S7 Android** cTab en inventaire (`ItemAndroid` / `ItemAndroidMisc`, libellé « S7 Android · Samsung S7 avec protection Kägwerks »). Action ACE SSE → Photographier.

## Symptôme

Hint : « Appareil photo SSE (ou caméra / tablette cTab compatible) requis. » alors que le terminal ATAK Overwatch accepte déjà le même Android.

## Cause

`hasEquipment` ne faisait qu’une égalité stricte de classname sur `items` + `assignedItems`, sans :
- pont avec `hasTerminal` Overwatch,
- héritage config (sous-classes),
- inventaire élargi (armes / `everyContainer`).

Selon le mode terminal CBA (slot vs inventaire Misc), le S7 pouvait être vu par ATAK et refusé par SSE photo.

## Correctif

- `fn_hasEquipment` : inventaire élargi, match par héritage, pont Overwatch `hasTerminal` pour camera / face / seek / terminal / fingerprint.
- Hint photo : mention explicite du S7 Android.
- Alias camera : `ACE_Cellphone` ajouté.

## Fichiers touchés

- `addons/core/functions/fn_hasEquipment.sqf`
- `addons/core/functions/fn_getEquipmentAliases.sqf`
- `addons/interaction/functions/fn_doPhotograph.sqf`

## Vérification

1. Rebuild `core` + `interaction`, copier Workshop, relancer.
2. S7 Android en inventaire (ou slot) → Photographier SSE OK.
3. Réglage CBA « Accepter les items d’autres mods » doit rester coché (défaut).

## Statut

Corrigé — rebuild PBO requis.
