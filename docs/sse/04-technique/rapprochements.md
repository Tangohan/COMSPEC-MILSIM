# SSE — Moteur de rapprochement et validations

Le moteur normalise puis compare identité civile, alias, visage, iris, empreintes, tatouages, plaques, téléphones, radios, lieux, horaires, relations, objets et véhicules. Il écrit une **proposition** avec version d’algorithme, score global et facteurs concordants/discordants.

Pondération initiale configurable : visage 35 %, biométrie complémentaire 20 %, identité civile 15 %, véhicule 10 %, téléphone 10 %, proximité 5 %, relations 5 %. Les seuils déclenchent uniquement des files opérateur.

Décisions : valider un rapprochement, rejeter, probable, faux positif, nouvelle collecte, liaison sans fusion, transmission analyste ou proposition de consolidation. `reviewed_by`, date, commentaire et niveau de confiance final sont obligatoires; la preuve formelle est stockée séparément du score.
