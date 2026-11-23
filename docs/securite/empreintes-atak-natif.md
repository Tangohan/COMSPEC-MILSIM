# Empreintes SHA-256 des fichiers du mod ATAK natif

Relevé du dossier `mod/COMSPEC_ATAK_Native/@COMSPEC_ATAK_Native` (construction 4.32.0) au 23/11/2026 (commit `cb048217a` de main), **sans** le lanceur de collecte de journaux retiré dans #1881. Elles servent à :

- rechercher chaque fichier sur [VirusTotal](https://www.virustotal.com/gui/home/search) sans l'envoyer ;
- appuyer une demande de levée de faux positif auprès d'un éditeur d'antivirus ou de Steam ;
- vérifier qu'une copie du mod n'a pas été modifiée.

L'empreinte de l'archive RAR publiée n'est pas dans ce fichier : elle est calculée à chaque publication et affichée sur la page `/modpacks`.

| Fichier | Taille (octets) | SHA-256 |
| --- | ---: | --- |
| `COMSPECATAKNativeExtension_x64.dll` | 10 423 296 | `5903849de95170c50beeaa5a20a8478d2492c63dc722831335535f17524a22bb` |
| `COMSPECExtension_x64.dll` | 10 240 512 | `2bfa0726b5be44efc8111400bc3c8346c323b0417746bc4e38e12e491817934b` |
| `addons/main.pbo` | 123 109 972 | `a3787fbdd5f147bdc23d547c046556242676412735d279be3896a534d6e5d3cc` |
| `addons/towers.pbo` | 56 199 301 | `4267d7b3e3a9d5751d0ecac3838a6b3ec82a7bb940a1e7cb775f8133c3f841b7` |
| `addons/comspec_overwatch_connect.pbo` | 25 460 297 | `d3ca4b80eb34a9ebb9f82c0201d7f2b407d3cfcf6f027417bd34e54316eda907` |
| `addons/items.pbo` | 29 649 104 | `bcd42afbe51e5f6e1ec7e805e827a106fd391efce3e89bb7f628e109c1007e97` |
| `addons/comspec_overwatch_main.pbo` | 178 077 | `7134aedc02fbed34152953ac3eb8edd5843a1983c6b28536809665d3ca8afdc0` |
| `keys/comspec_atak_native.bikey` | 172 | `c8a0b9f9244b32c1f0fc6adfd6e464f84fa60dd7874619e30ec7594bbea36888` |
| `mod.cpp` | 514 | `17c8df972a923656d44b98ba0cd9b380747eab1ce409803406f6304565a66de4` |
| `logo.paa` | 11 061 | `f6cd849336486dbfa40ff067486dc2a4a42949b8967a1a1d99107a43627b6df9` |
| `LICENSE.txt` | 5 655 | `6dfd99f60124ca1f1923b4aafcaf543c539125ed31a75f5d802023f8ac33a702` |

Les fichiers `.bisign` (signatures Arma 3 des PBO, 572 octets chacun) ne sont pas listés : ils changent à chaque reconstruction.

## Recalculer

```powershell
Get-ChildItem -Recurse -File "mod\COMSPEC_ATAK_Native\@COMSPEC_ATAK_Native" |
  Where-Object { $_.Extension -notin '.bisign' } |
  Get-FileHash -Algorithm SHA256 |
  Select-Object Hash, @{n='Fichier';e={$_.Path}}
```

À refaire après chaque reconstruction de `main.pbo` ou des DLL : une empreinte périmée ne prouve rien.

## À propos des DLL

Les deux DLL sont des binaires .NET compilés en natif (sections `.managed` et `hydrated`), **non signés**. Les signer avec un certificat de signature de code reconnu est la mesure qui réduit le plus les faux positifs ; un certificat auto-signé n'y change rien. Voir `docs/bugs/2026-11-23-workshop-signale-av-lanceur-logs.md`.
