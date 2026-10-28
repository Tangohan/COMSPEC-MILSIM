# BFT : même joueur en double (pseudo Steam + indicatif)

## Contexte

Retour opérateur : sur la carte ATAK, un allié apparaît deux fois — « Adam » (pseudo Steam) et « Y 31 » (indicatif) — souvent reliés par un trait vert (calque relais). Le joueur concerné signale aussi des freezes.

## Symptôme

Deux icônes bleues pour la même personne, libellés différents.

## Cause

1. `fn_getUnitsList` (Overwatch) prenait `COMSPEC_Callsign` puis le **pseudo Steam** si vide, sans lire `COMSPEC_ATAK_CsCustom`.
2. Athena envoie l’indicatif tactique (« Y 31 ») : deux clés dans `remoteUnits`.
3. `fn_localDataRefresh` fusionnait par indicatif exact ou proximité, mais laissait le fantôme sous le pseudo.
4. `fn_callsignAction` ne publiait que `CsCustom`, pas `COMSPEC_Callsign` (identité partagée Link / natif).

Le trait vert vient du calque relais / Wave Relay entre deux positions proches, pas d’un lien « doublon » dédié.

## Correctif

- `getUnitsList` : même ordre d’indicatif que le natif ; pas de ligne locale sous le pseudo seul.
- Fusion Athena ↔ local par proximité si libellés différents.
- `callsignAction` publie aussi `COMSPEC_Callsign` / `CallsignPublic`.
- `unitCallsign` lit `COMSPEC_Callsign`.
- `localDataRefresh` : retire les pistes Athena « pseudo » quand l’indicatif tactique est déjà dessiné.

## Fichiers touchés

- `mod/UptoDate/.../connect/functions/fn_getUnitsList.sqf`
- `mod/COMSPEC_ATAK_Native/.../fn_callsignAction.sqf`
- `mod/COMSPEC_ATAK_Native/.../fn_unitCallsign.sqf`
- `mod/COMSPEC_ATAK_Native/.../fn_localDataRefresh.sqf`

## Vérification

En jeu avec Link : un allié avec indicatif personnalisé ne doit montrer **qu’une** icône (l’indicatif). Republier Overwatch Connect + pack téléphone.

## Statut

Corrigé dans les sources. NET-003 (doublons SQL) reste ouvert côté serveur si des fantômes persistent hors session.
