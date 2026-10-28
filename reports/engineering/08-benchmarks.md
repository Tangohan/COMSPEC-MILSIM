# Bancs — 27 octobre 2026

Aucun chiffre inventé. Aucun banc de charge ni de fluidité n’a été exécuté dans cette session.

## Non mesuré

- Temps de réponse p50 / p95 des envois (positions, télémétrie, marqueurs).
- Images par seconde côté client ou serveur avec les correctifs de carte et d’IA.
- Montée en charge du portail.
- Délai réel d’une pause `Retry-After` (la lecture du code fixe le plafond à 600 s en global et 300 s par adresse).

## Observations sans mesure

- L’échelle choisie par la liaison sans délai demandé reste 45 → 75 → 90 s.
- Le passage 1 Hz sur toutes les IA est sauté quand le mode téléphone IA est éteint et qu’aucune IA n’a encore été équipée.
- Les icônes hors de la vue carte ne sont plus dessinées ; `getMarkerPos` tourne encore.
- Le flush serveur des marqueurs reste un appel bloquant de la liaison.

Ces points sont des lectures de code. Ils ne remplacent pas une mesure en partie.

---

## Append — 27/10/2026 soir

**Arma non execute.** Aucun p50/p95 hitch reel.

Instrumentation ajoutee : journal `Image fige` inclut `combat` (behaviour) et `suppression` (0-100). Scenario de mesure propose : 5 min marche, 60 s engagement, telephone porte puis range ; comparer comptes de lignes `Image fige` avant/apres pack.

Banc reseau isole : non relance (tools/sec001-bench inchange).

---

## Append — Phase 3 banc reseau (27/10)

`tools/sec001-bench` : harnais SEC-001 (fuite secrets), pas un banc latence/BFT. Sous Windows ici : **pas** de `unshare -n` ; pas de copie hors worktree lancee (risque bin/obj dans le depot). Metrics p50/p95/p99 : **non mesure**.
Assertions NET-003/004 : laissees ouvertes (decision produit). Scenario propose pour machine Linux isolee : README du banc + proxies locaux latency/429 — hors session actuelle.
