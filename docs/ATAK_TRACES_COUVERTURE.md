# ATAK : où apparaît ce qui est tracé depuis le téléphone

Règle : tout ce qu'on trace, note ou marque sur l'ATAK apparaît sur le web et sur la carte native d'Arma, sauf choix écrit ici.
Portée : celle du partage (canal ou camp). Un élément privé reste privé, le camp adverse ne reçoit rien.
État relu sur le code au 2026-11-08. Rien de ce tableau n'a pu être vérifié en jeu.

| Élément | Carte du téléphone | Carte native d'Arma | Web (Tacmap, `/m/carte`) | Autres joueurs du camp | Arrivée en cours de partie |
|---|---|---|---|---|---|
| Marqueur (palette, éditeur) | oui | oui, marqueur du canal choisi | oui (`fn_markerWeb`, relais `fn_markerWebSweep`) | selon le canal | oui (moteur, ou serveur en mode réseau ATAK) |
| Trait dessiné (`fn_markerStroke`) | oui, épais via `fn_strokeDraw` | oui, polyligne d'un pixel | oui | selon le canal | oui |
| LZ de l'outil, cible LOCK | oui | oui (`fn_markerDrop`) | oui | selon le canal | oui |
| Note de carte | oui, fiche du marqueur | **non** : Arma n'a pas de champ de note | **trou restant** : la note n'est pas dans l'envoi de `syncMapMarker` (addon Overwatch, hors de cette branche) | oui (`COMSPEC_ATAK_MarkerNotes`) | oui |
| Route | oui (`fn_routeDraw`) | oui si partagée, sans double dessin ; privée : ma carte seulement | oui si partagée | oui selon la portée | oui, rejeu serveur |
| Étape de route | oui | oui, repère | oui | oui | oui |
| Plan de zones : zones | oui (`fn_zoneDraw`) | oui si partagé ; privé : ma carte seulement. Plafond ci-dessous | oui si partagé | **comblé** : l'empaquetage échouait, le plan ne partait jamais | **comblé**, même cause |
| Axes MSR / ASR, points de contrôle | oui | oui | oui | comblé | comblé |
| Traits et contours d'un plan | oui | oui | oui | comblé | comblé |
| Situation d'une zone (état) | oui | oui, couleur et texte, posés par l'auteur du plan s'il est là | oui, même chemin | oui | oui, rejeu serveur |
| Plan généré depuis la carte (clic droit) | oui, s'il a un résultat | comme un plan | comme un plan | comme un plan | comme un plan |
| Itinéraire (app Itinéraire) | oui | **comblé** si partagé (`fn_wpAction` « wpMk ») | **comblé** | oui | oui |
| Plan de briefing du camp (app Plan) | oui | **comblé** : marqueurs locaux sur chaque poste du camp (`fn_planNative`), jamais diffusés | oui, calque dédié (`fn_planWeb`) | oui | oui |

## Volontairement locaux ou temporaires

| Élément | Choix |
|---|---|
| Mesure A-B, trait vers le curseur | local à l'écran, jamais publié |
| Ping, pointage à l'appui long | temporaires, envoyés aux joueurs proches, sans marqueur ni site |
| Guidage GPS « Y aller » | personnel |
| Route ou plan « Moi seul » | sur mon téléphone et ma carte d'Arma, marqueurs `comspec_shape_prv_*`, rien au réseau ni au site |
| Route ou plan relu du profil d'une partie précédente | privé tant qu'il n'est pas renvoyé : une ancienne partie ne publie rien d'office |

## Un seul chemin

`fn_markerOwned` crée et supprime les marqueurs natifs des tracés et garde le lien marqueur -> élément.

- **Téléphone** : `del` / `trash` retirent le tracé chez les destinataires, au serveur, sur Arma et sur le site. ANNULER republie.
- **Carte d'Arma** : un marqueur effacé à la main déclenche `MarkerDeleted`, et l'élément suit chez son auteur. S'il n'est pas là, le tracé reste dans son profil, en privé.
- **Web** : un marqueur du jeu retiré sur le site est gardé en `suppressed`. `/api/atak/markers?world_name=` le renvoie 15 minutes en tête de liste (`source: game_del`), le relais du camp le retire en jeu, et l'élément suit.
- **Retour** : pierres tombales par révision (`COMSPEC_ATAK_RteGone`, `COMSPEC_ATAK_ZnGone`), rejeu serveur retiré au `del`.
- **Effacer la carte** (Overwatch, `/m/carte`) : `POST /api/atak/map/clear` et `/m/api/carte/effacer`. Même chemin que la suppression à l'unité : un marqueur du jeu est masqué (`suppressed`) et le relais le retire en jeu (au-delà de 20 par relevé, fenêtre glissante de 15 s), le reste est supprimé. Corbeille de 10 minutes (`storage/cache/atak-map-trash`), journal d'audit `atak.map_clear`. Le téléphone n'efface que ses propres éléments.

Le téléphone ne redessine pas ces marqueurs : noms réservés `_USER_DEFINED #<client>/77000<n>/<canal>`, sautés par `fn_mapGameMarkers`, `fn_mapMarkerText`, `fn_mapOnDraw` et `fn_markerAt`.

## Plafonds

- Zones par découpage : réglages serveur existants (`zones_maxcells`, `zones_maxblocks`).
- Au-delà de `zones_armamax` zones (60 par défaut) : étiquettes seules sur Arma et le site.
- Par plan : 3 x `zones_armamax`, entre 150 et 400 marqueurs, 15 créés par image. Message au joueur si le plafond joue.
- Route : 200 points par polyligne. Plan de briefing : 150 marqueurs par poste. Itinéraire : 30 étapes.

## Limites connues

- La portée « Équipe » pose ses marqueurs natifs sur le canal du groupe : Arma n'a pas de canal d'équipe de feu. Le groupe entier les voit sur sa carte native.
- Une étape d'itinéraire effacée à la main sur la carte d'Arma revient à son prochain envoi : seul le tracé des routes et plans est relié à ses éléments.
