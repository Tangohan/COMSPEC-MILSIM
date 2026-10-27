# Distances : Actualiser inefficace, site à 70 m, lisibilité

## Contexte

App Distances (téléphone) : carrière locale ~3,5 km, mission en cours ~549 m. Métriques joueurs / fiche Athena : 70 m. Serveur de partie non relié à Athena. Bouton ACTUALISER. Textes petits (allure, cadence, bandeau Athena).

## Symptôme

- ACTUALISER ne changeait rien côté site ; statut « Pas encore envoyé ».
- Le site gardait ~70 m alors que le téléphone montrait bien plus (carnet local + mission).
- Libellés secondaires difficilement lisibles.

## Cause

1. ACTUALISER n’appelait que `distanceLog` + redessin : aucun `Distance.Batch`.
2. `DistSent` était horodaté même si Acc était vide → premier envoi réel retardé de 10 min.
3. En cas d’échec, `_at` restait à -1 → l’UI affichait « Pas encore envoyé » au lieu d’un échec.
4. `PlayerBatchExceedsSession` pouvait refuser à tort (Steam vide ou JSON illisible → `true`).
5. La « carrière » du téléphone est un carnet PC local ; le site ne cumule que les sessions déjà envoyées. Les missions jamais envoyées ne se reconstruisent pas.

## Correctif

- ACTUALISER → `selfFlush` (envoi immédiat du relais téléphone).
- Horodatage DistSent seulement s’il y a des joueurs à envoyer ; échec pose l’heure et un libellé clair.
- Filtre session joueur plus tolérant côté liaison.
- Tailles de texte relevées dans l’app Distances.
- Versions : ATAK natif 2.9.1, COMSPEC Link 2.0.100.

## Fichiers touchés

- `mod/COMSPEC_ATAK_Native/Sources/addons/main/functions/screen/fn_distanceServer.sqf`
- `mod/COMSPEC_ATAK_Native/Sources/addons/main/functions/screen/fn_pageDistance.sqf`
- `mod/COMSPEC_ATAK_Native/Sources/addons/main/script_component.hpp`
- `mod/UptoDate/COMSPECExtension/Extension.cs`
- `mod/UptoDate/COMSPECExtension/COMSPECExtension.csproj`
- `CHANGELOG-ATAK.md`, `app/Support/DevDispatchCatalog.php`

## Vérification

- Lecture du flux ACTUALISER / DistSent / statut. Pas d’essai Arma dans cette session.
- Rebuild pack + DLL requis avant test en jeu.

## Statut

Corrigé dans les sources (rebuild / Steam à faire).
