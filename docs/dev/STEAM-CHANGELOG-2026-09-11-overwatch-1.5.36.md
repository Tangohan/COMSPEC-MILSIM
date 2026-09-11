# Changelog Steam — Overwatch 1.5.36 (11/09/2026)

Copier-coller ci-dessous dans la description Workshop Steam (format BBCode).

```
[h1]COMSPEC Overwatch — Mise à jour 1.5.36[/h1]
[b]Publication : 11/09/2026[/b]
[b]Pack :[/b] Overwatch 1.5.36 · Athena 1.0.88 · liaison 2.0.26
[b]SSE :[/b] reconstruit (pack Workshop à jour)

[quote]
[b]Important :[/b] quittez Arma 3 complètement avant de recharger le pack. Sur le poste, actualisez la carte (Ctrl+F5) et lancez les migrations du portail si votre hébergeur le demande.
[/quote]

[h2]Nouveau — Canaux radio[/h2]
Le journal radio du poste et du téléphone propose des canaux (Groupe, Commandement, Général, JTAC, Air) et des canaux personnalisés.
[list]
[*] Création de canaux depuis le poste ou le jeu ;
[*] Effacement de votre affichage local d’un canal ;
[*] Purge définitive de l’historique réservée au poste.
[/list]

[h2]Nouveau — Carte : Wave, itinéraires et zones de vue[/h2]
[list]
[*] Pastilles Wave / passerelle / pont sur les effectifs ;
[*] ETA et distance restante sur les opérateurs en itinéraire ;
[*] Zones de vue terrain remontées depuis le téléphone vers la carte du poste.
[/list]

[h2]Nouveau — État de liaison sur la barre du téléphone[/h2]
Sous la barre d’état, une ligne indique clairement OK ou NOK, le débit estimé vers le poste, et le taux de perte. L’icône de signal change de teinte selon l’état.

[h2]Nouveau — Première liaison et aide à l’Appairage[/h2]
[list]
[*] Parcours Première liaison recentré : compte, pack, code Appairer, contrôle carte ;
[*] Bouton pour vérifier si le poste vous voit ;
[*] Pastille En liaison / Pas encore vu sur la carte ;
[*] Mes tenues depuis le hub ATAK ;
[*] Code Appairer en tête de l’écran connexion Athena ;
[*] Liaison au poste aussi réglable dans Paramètres (adresse, clé, communauté).
[/list]

[h2]Amélioration — Paramètres et Athena plus lisibles[/h2]
Libellés et champs agrandis. Les aides sous Appairer et Liaison au poste ne sont plus illisibles. Les réglages techniques restent derrière « Afficher les réglages avancés ».

[h2]Amélioration — Arsenal Athena[/h2]
Colonnes Mes tenues / Communauté clarifiées. Boutons Partager / Importer. Suppression de mon arsenal distincte du retrait communauté. Aide contextuelle selon la sélection.

[h2]Amélioration — Temps de mission[/h2]
Depuis Athena, « Remonter le temps » envoie immédiatement le cumul vers le portail. Resynch Athena et la validation du groupe Zeus le font aussi.

[h2]Correction — Journal radio jeu ↔ poste[/h2]
Les messages téléphone / groupe remontent de nouveau au journal du poste, et les messages du poste réapparaissent dans le téléphone. La liaison ne coupe plus le fil radio pendant la stabilisation.

[h2]Correction — Opérateurs hors liaison sur la carte[/h2]
Un opérateur dont le signal n’arrive plus disparaît de la carte. La liste Effectifs distingue clairement un contact hors liaison d’un simple retard de position.

[h2]Correction — Identité BFT et groupe[/h2]
Sur la carte du téléphone, l’encart d’identité (indicatif, nom, groupe, fonction, position) reste lisible. L’identifiant de groupe technique est synchronisé vers le suivi d’effectif et remonté au poste.

[h2]Correction — Liaison Athena / Appairer[/h2]
[list]
[*] Rouverture du canal poste après Appairer ;
[*] Temps de mission à nouveau remonté correctement ;
[*] Moins de refus pendant la stabilisation de connexion.
[/list]

[h2]Portail[/h2]
[list]
[*] Guides Première liaison et Connexion alignés sur Appairer / téléphone Athena ;
[*] Configuration ATAK : vocabulaire unifié pour la clé d’accès communauté.
[/list]

[quote]
[b]Versions à vérifier en jeu :[/b] Overwatch 1.5.36 · Athena 1.0.88 · liaison 2.0.26
[/quote]
```

## Builds effectués (11/09/2026)

| Mod | Statut |
| --- | --- |
| Overwatch (`mod/UptoDate/build_mod.bat`) | OK — déployé Workshop + `@COMSPECOverwatch` + SOAR |
| SSE (`mod/@COMSPEC_SSE/build_mod.bat`) | OK |
| ATAK Native (`mod/COMSPEC_ATAK_Native/build_mod.bat`) | OK avec `ARMA3TOOLS=F:\SteamLibrary\...\Arma 3 Tools` |

## PR

Bloqué localement : `gh` n’est pas authentifié (`gh auth login` ou `GH_TOKEN`). Passe en mode Agent et reconnecte `gh` pour commit + push + `gh pr create`.
