# Résultats de vérification — 27 octobre 2026

Branche `release/2.7.0`. Correctifs non commités. Aucune partie Arma 3 exécutée.

## Exécuté

| Contrôle | Résultat |
| --- | --- |
| `php -l` sur `AtakApiController.php` et `DevDispatchCatalog.php` | Syntaxe correcte |
| `dotnet publish` des deux liaisons (téléphone et COMSPEC Link) | Réussi (avertissements nullabilité déjà connus) |
| Empaquetage AddonBuilder + `verify_build.py` | Cinq modules conformes, aucun doublon |
| Revue indépendante (trois passages) | **ACCEPTER** — `07-independent-review.md` |
| Lecture du plancher de version sur les deux portails (session antérieure) | Ancien pack refusé, pack courant passé la porte |

## Non exécuté

| Contrôle | Motif |
| --- | --- |
| Partie Arma 3 / journal de mission | Aucune session lancée |
| PHPUnit (`GameClientVersionGateTest` et autres) | `vendor/bin/phpunit` absent |
| `node --check` sur les scripts de carte | Node absent du PATH |
| Banc réseau sous charge | Aucun outil de charge dans `tools/` pour ce lot |
| Sanitisers ASan / UBSan | Liaisons en C# .NET 8, hors périmètre |
| `tools/sec001-bench` sous Windows | Ne doit plus être relancé ici : il touche le magasin de session local |

## Pack reconstruit

Dossier `mod/COMSPEC_ATAK_Native/@COMSPEC_ATAK_Native` (empreinte locale `2026.10.27 e0beac95b` au moment de l’empaquetage) :

- téléphone `main.pbo`, objets, pylônes ;
- liaison embarquée `comspec_overwatch_connect.pbo` / `comspec_overwatch_main.pbo` ;
- `COMSPECATAKNativeExtension_x64.dll` et `COMSPECExtension_x64.dll`.

---

## Append — 27/10/2026 soir

| Lot | Tests | Resultat |
| --- | --- | --- |
| LOT-COMBAT | Revue code + non-regression documentee | Pas d'execution Arma |
| LOT-DLL (005/007) | Revue code | Pas de test managé ajoute |
| PHPUnit | non lance | — |

---

## Append — follow-up A2/A1/SEC (27/10 soir +1)

| Item | Resultat |
| --- | --- |
| SQF-C03 emitTelemetryEvent async | code |
| SQF-C06 EntityKilled async | code |
| SQF-C04 checkAtakDamage early-exit | code |
| ARCH-006 routes dedupe | code |
| SEC-042 screenTimeBatch parity | code |
| Arma / PHPUnit | non executes |
| Revue Bugbot A8 | ACCEPT (residus connus) |

---

## Append — 27/10 reprise A4–A7

| Lot | Resultat |
| --- | --- |
| LOT-DLL C01/C02 | build OK |
| LOT-MEM Iceman + PendingLogLines | code |
| LOT-NET | ouvert (decision produit) |
| Phase 3 sec001-bench | non lance (Windows) |
| Arma | non execute |
