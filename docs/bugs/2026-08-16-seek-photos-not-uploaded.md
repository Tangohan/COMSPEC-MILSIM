# SEEK — photos de visage absentes sur Identités

## Contexte

Fiche Athena créée (ex. Khalil Jawadi / IDN-00001) mais mugshot vide (`—`).

## Symptôme

Registre des personnes : identité + constat OK, **pas de photographie**.

## Cause

1. Le bouton **PHOTO DU VISAGE** ne capturait rien : il posait seulement
   `COMSPEC_SsePerson_PhotoPending = true` et annonçait qu’« une capture récente »
   serait jointe.
2. À la transmission, `UploadSsePhoto` cherchait la dernière capture Steam/Arma
   (90 s) avec un chemin vide → `ERR|file_not_found` si aucune F12.
3. La fiche personne était quand même créée → UI Athena sans photo.

## Correctif

- `fn_sseCaptureFacePhoto` : `screenshot` nommé + flag + stem.
- Bouton PHOTO appelle cette fonction.
- Submit : envoi différé avec le **stem** (attente écriture disque) ; capture
  d’urgence si flag sans stem.

## Fichiers touchés

- `connect/functions/fn_sseCaptureFacePhoto.sqf` (nouveau)
- `connect/functions/fn_ssePersonDialogSubmit.sqf`
- `connect/functions/fn_ssePersonDialogShow.sqf`
- `connect/display_sse_person.hpp`
- `connect/config.cpp`

## Vérification

1. Rebuild PBO `connect` Overwatch.
2. SEEK → PHOTO DU VISAGE (message capture) → TRANSMETTRE.
3. Recharger Identités : mugshot présent sous `uploads/sse/…`.

## Statut

corrigé en sources — **rebuild PBO Overwatch requis**
