# Prompts — packs de modèles SSE / mission

Deux prompts prêts à coller :

1. **ChatGPT / Claude (contenu)** — produit un pack narratif (JSON + SQF + fiche mission).
2. **Cursor / agent du dépôt (implémentation)** — intègre le pack dans Athena + `@COMSPEC_SSE`.

Pour un **dossier d’affaire complet** (identités + sites + pièces, import Athena puis Arma), voir plutôt :
[prompts-dossiers-fictifs-json.md](./prompts-dossiers-fictifs-json.md).

Références code :
- Schéma web : `app/Repositories/SseArmaModelRepository.php` (profils, régions, thèmes)
- Catalogue d’ères : `app/Services/Sse/SseArmaModelService.php` → `builtinTemplates()`
- Builtins Arma : `mod/@COMSPEC_SSE/addons/generator/functions/fn_registerBuiltinModels.sqf`
- Doc modèles : `mod/@COMSPEC_SSE/docs/MODELS.md`

---

## 1) Prompt ChatGPT — créer un pack (copier-coller)

```text
Tu es scénariste / concepteur de missions MILSIM pour le module COMSPEC SSE (Arma 3 + portail Athena).

Objectif : produire un PACK DE MODÈLES SSE cohérent pour une ère / un théâtre / une mission.

════════════════════════════════════
CONTRAINTES TECHNIQUES (OBLIGATOIRES)
════════════════════════════════════

Chaque modèle DOIT respecter ces enums exacts (aucune valeur inventée) :

profile ∈ {
  CIVILIAN, INSURGENT, MILITARY, COMMANDER, COURIER,
  FINANCIER, TECHNICIAN, INTELLIGENCE, LOGISTICS, RANDOM
}

complexity ∈ { LIGHT, STANDARD, DETAILED, HIGH_VALUE }

region ∈ {
  IRAQ, SYRIA, LEVANT, AFRICA_SAHEL, RUSSIA, GENERIC
}

theme ∈ {
  fuel_delivery, weapons_cache, meeting_alpha, courier_run,
  finance_drop, ied_cell, safehouse, recruitment, smuggling,
  drone_ops, propaganda, medical_logistics, RANDOM
}

Champs modèle (camelCase, comme createModel / importModel) :
- id (snake_case stable, préfixe pack_… ou builtin_xx_…)
- name (libellé humain FR)
- author
- source = "MISSION" (pack mission) ou "BUILTIN" (catalogue)
- profile, complexity, region, theme
- aliasPool[], contactPool[], smsTemplates[], documentTemplates[], codewords[]
- locations[] (optionnel, toponymes / points)
- forcedIdentity? { name?, alias?, nationality? }
- includeBiometrics, includePhone, includeDocuments, includeComputer (bool)
- networkSize (0–40)
- noiseProbability, falseLeadProbability (0–1, optionnels)
- tags[], notes

Règles narratives :
- SMS courts, crédibles, jamais de jargon API / JSON / “endpoint”.
- Documents = titres / types de pièces (pas de roman).
- Alias et contacts : mix indicatifs, surnoms, rôles (THE DRIVER, RELAY-2…).
- Au moins 1 modèle “bruit” CIVILIAN LIGHT (fausse piste / vie quotidienne).
- Au moins 1 modèle HIGH_VALUE (HVT / nœud dur).
- Varier digital : téléphone vs PC vs papier selon le profil.
- Cohérence d’ère : vocabulaire, toponymes, modes opératoires de la période.
- Pas de données personnelles réelles ; tout est fiction / MILSIM.

════════════════════════════════════
STRUCTURE DU PACK À LIVRER
════════════════════════════════════

A) FICHE PACK (FR)
- Titre du pack
- Théâtre / années
- Pitch mission (8–12 lignes)
- Objectifs joueurs / objectifs SSE (collecte, HVT, site, digital…)
- Composition recommandée (combien d’unités / sites par modèle)
- Fausses pistes prévues

B) TABLEAU DES MODÈLES
Pour chaque modèle : id | name | profile | complexity | role dans la mission

C) MODÈLES COMPLETS
Pour CHAQUE modèle : objet JSON valide (un objet par modèle).

D) SCRIPT MISSION SQF
Un seul bloc .sqf qui :
1. crée chaque modèle via comspec_sse_fnc_createModel
2. force id + source MISSION
3. appelle comspec_sse_fnc_saveModel
4. commente comment applyModel sur une unité / un site

Exemple de forme SQF attendue :
private _mk = {
  params ["_id", "_name", "_ov"];
  private _m = [_name, _ov, "Auteur Pack"] call comspec_sse_fnc_createModel;
  _m set ["source", "MISSION"];
  _m set ["id", _id];
  [_m, true] call comspec_sse_fnc_saveModel;
  _m
};
["pack_xx_role", "Libellé", createHashMapFromArray [ ... ]] call _mk;

E) CHECKLIST MISSION MAKER
- Où placer le script (initServer / Zeus)
- Quels modèles coller sur quelles classes d’unités / objets
- Ce que le bureau Athena doit recevoir (types de fiches)

════════════════════════════════════
BRIEF UTILISATEUR (À REMPLIR)
════════════════════════════════════

Théâtre / région code : {{IRAQ|SYRIA|LEVANT|AFRICA_SAHEL|RUSSIA|GENERIC}}
Période : {{ex. 2014–2017}}
Pitch : {{1–3 phrases}}
Nombre de modèles : {{6–10}}
Focus : {{armes | IED | HVT | logistique | drone | EW | finance | mix}}
Langue des SMS / docs : {{FR|EN|mix local}}
Niveau digital : {{faible papier|mixte|lourd PC}}
Inclure civil bruit : {{oui|non}}
IDs préfixe : {{pack_iq14_ | pack_sahel_ | …}}
Auteur : {{nom / unité}}

Produis le pack complet maintenant selon ce brief.
```

### Variante courte (si le brief est déjà clair)

```text
Crée un pack SSE COMSPEC de {{N}} modèles pour {{région}} {{période}}, focus {{thème}}.
Respecte strictement les enums profile/complexity/region/theme du module.
Livrables : fiche pack FR + JSON de chaque modèle + script SQF createModel/saveModel + checklist Eden/Zeus.
Inclure 1 CIVILIAN LIGHT bruit et 1 COMMANDER ou TECHNICIAN HIGH_VALUE.
SMS/docs en français, fiction MILSIM, pas de données réelles.
Préfixe ids : {{pack_xxx_}}
```

---

## 2) Prompt Cursor — intégrer un pack dans le dépôt

```text
Tu travailles dans le dépôt COMSPEC-MILSIM. Crée / intègre un PACK DE MODÈLES SSE pour mission.

## Contexte produit
- Atelier web : /atak/sse/dev — SseArmaModelService::builtinTemplates()
- Builtins Arma : mod/@COMSPEC_SSE/addons/generator/functions/fn_registerBuiltinModels.sqf
- Doc : mod/@COMSPEC_SSE/docs/MODELS.md et docs/sse/atelier-modeles-arma.md
- Enums : SseArmaModelRepository::PROFILE_LABELS / COMPLEXITY / REGION / THEME

## Brief pack
- Théâtre : {{…}}
- Période : {{…}}
- Pitch : {{…}}
- N modèles : {{…}}
- Préfixe ids : {{pack_…_ ou builtin_…_}}
- Mode d’intégration souhaité :
  [ ] A — Catalogue web only (builtinTemplates dans SseArmaModelService)
  [ ] B — Builtins Arma only (fn_registerBuiltinModels.sqf)
  [ ] C — Les deux (web + Arma) + doc MODELS.md
  [ ] D — Pack mission seul (fichier .sqf sous mod/@COMSPEC_SSE/docs/examples ou mission/)

## Règles d’implémentation
1. Ne pas inventer d’enums hors repository.
2. Contenu FR humain (SMS, docs, notes) — fiction MILSIM.
3. Chaque modèle : pools alias/contacts + sms + documents + codewords riches (pas de coquilles vides).
4. Inclure civil bruit + au moins un HIGH_VALUE.
5. IDs stables snake_case.
6. Si web : group label d’ère clair (ex. « Sahel 2015–2020 ») dans builtinTemplates().
7. Si Arma : même schéma que les builtins existants (_mk + createHashMapFromArray).
8. Mettre à jour MODELS.md (liste des ids).
9. Ne pas committer sauf demande explicite.

## Livrable attendu
- Diff code prêt
- Petit récap : ids créés, où coller le script mission, comment appliquer en Zeus/Eden
```

### Variante Cursor — “à partir d’un JSON GPT”

```text
Voici un pack JSON / SQF généré pour SSE. Intègre-le dans le dépôt selon le mode {{A|B|C|D}}.
Valide chaque profile/complexity/region/theme contre SseArmaModelRepository.
Corrige les enums invalides au plus proche sémantique.
Complète les pools trop courts (<4 alias, <3 SMS, <2 docs).
Ajoute le civil bruit s’il manque.
Mets à jour MODELS.md.
Fichiers concernés : SseArmaModelService.php et/ou fn_registerBuiltinModels.sqf.

PACK :
{{coller ici la sortie GPT}}
```

---

## 3) Checklist qualité d’un pack (revue rapide)

| Critère | OK si… |
|--------|--------|
| Enums | 100 % dans le repository |
| Densité | ≥ 4 alias, ≥ 4 contacts, ≥ 4 SMS, ≥ 3 docs, ≥ 3 codewords (sauf LIGHT) |
| Bruit | 1 CIVILIAN LIGHT avec noiseProbability ≥ 0.5 |
| HVT | 1 HIGH_VALUE avec digital et/ou biométrie |
| Cohérence | mêmes région + tags d’ère sur tout le pack |
| Fiction | aucun vrai nom / téléphone / adresse réelle |
| Apply | chaque id utilisable via `[_unit, "id"] call comspec_sse_fnc_applyModel` |

---

## 4) Exemple de brief rempli

```text
Théâtre / région code : AFRICA_SAHEL
Période : 2015–2020
Pitch : Cellule logistique transfrontalière (carburant + armes) avec courrier moto et financier local ; les joueurs SSE doivent démêler planque, cache et civil bruit.
Nombre de modèles : 7
Focus : mix logistique / finance / courrier
Langue des SMS / docs : FR
Niveau digital : mixte
Inclure civil bruit : oui
IDs préfixe : pack_sahel15_
Auteur : Escadron exemple
```
