# Prompts — dossiers fictifs complets (JSON)

Objectif : générer un **pack de dossier SSE fictif** (affaire + identités + notes + pièces + sites), l’**importer dans Athena** (mode gestion), puis l’**emporter vers Arma 3**.

## Parcours recommandé

1. Coller le prompt ci-dessous dans **ChatGPT** ou **Claude**.
2. Remplir le brief (théâtre, pitch, volume).
3. Récupérer **un seul objet JSON** valide.
4. Dans Athena : **Bureau SSE → Dossiers → Importer un scénario** (ou Atelier de préparation).
5. Sur le dossier créé : **Emporter le pack dossier** / **Pack terrain Arma** / **Script Arma (.sqf)**.

Références code :
- Contrat : `config/sse_case_bundle.php`
- Service : `app/Services/Sse/SseCaseBundleService.php`
- UI : `/atak/sse/dossiers/importer`, emport `/atak/sse/dossiers/{id}/emport`
- Modèles narratifs (autre flux) : [prompts-packs-modeles-mission.md](./prompts-packs-modeles-mission.md)

---

## 1) Prompt ChatGPT / Claude — dossier complet (copier-coller)

```text
Tu es scénariste MILSIM pour COMSPEC SSE (portail Athena + Arma 3).

Objectif : produire UN SEUL objet JSON valide : un pack de dossier d’affaire fictif complet, prêt à importer dans Athena.

════════════════════════════════════
FORMAT OBLIGATOIRE
════════════════════════════════════

Racine :
{
  "format": "comspec_sse_case_bundle",
  "formatVersion": 1,
  "meta": { "theatre": "…", "author": "…", "fiction": true },
  "case": { … },
  "persons": [ … ],
  "notes": [ … ],
  "evidence": [ … ],
  "sites": [ … ]
}

case :
- title (obligatoire, FR)
- summary (paragraphe court)
- classification ∈ { interne, encadrement, confidentiel, tres_restreint }
- status ∈ { ouvert, en_cours, clos, archive }
- reference_code (optionnel ; laisse "" pour laisser Athena attribuer)

persons[] (2 à 8) :
- key (ex. "p1") — stable pour lier les pièces
- status ∈ { civil, combattant, detenu, prioritaire }
- last_name, first_name, alias (au moins un non vide)
- nationality, affiliation, circumstances, statements, distinguishing_marks
- age_estimated (nombre ou null)
- weapons[], equipment[] (listes de chaînes courtes)
- grid_reference, location_description (optionnels)
- arma_profile ∈ {
    CIVILIAN, INSURGENT, MILITARY, COMMANDER, COURIER,
    FINANCIER, TECHNICIAN, INTELLIGENCE, LOGISTICS, RANDOM
  }
- arma_complexity ∈ { LIGHT, STANDARD, DETAILED, HIGH_VALUE }

notes[] :
- body (texte opérateur, FR)
- classification (mêmes enums que case)
- author_label

evidence[] :
- label, caption
- preset_key optionnel ∈ {
    phone, id_doc, weapon, photo_face, fingerprint,
    usb, radio, document, vehicle, other
  }
- person_key (clé persons.key, optionnel)

sites[] (0 à 4) :
- name, site_type ∈ { habitation, depot, poste_ennemi, cache, vehicule, autre }
- summary, grid_reference, team_label
- rooms[] optionnel (libellés de pièces)
- seizures[] : { category, label, quantity, notes }
  category ∈ { arme, munition, document, radio, medical, numerique, valeur, autre }

════════════════════════════════════
RÈGLES NARRATIVES
════════════════════════════════════

- Tout est FICTION / entraînement — aucune personne réelle.
- Cohérence d’ère : toponymes, modes opératoires, vocabulaire.
- Au moins 1 identité « bruit » (civil LIGHT) et 1 prioritaire / HVT.
- SMS / déclarations crédibles, sans jargon technique (pas d’API, JSON, endpoint).
- Les notes parlent comme un bureau renseignement (phrases claires).
- Varier digital vs papier vs armement selon les profils.
- Ne sors QUE le JSON (pas de markdown, pas de commentaire hors JSON).

════════════════════════════════════
BRIEF (À REMPLIR)
════════════════════════════════════

Théâtre / période : {{ex. Irak 2016 · Syrie 2018 · Sahel 2021 · Est Europe 2023}}
Pitch (2–4 phrases) : {{…}}
Focus : {{cache armes | HVT | IED | finance | drone | guide local | mix}}
Volume : {{3–6 identités, 1–2 sites, 3–8 pièces, 1–3 notes}}
Langue des textes : FR
Ton : {{réaliste / sombre / entraînement débutant}}
```

---

## 2) Prompt Claude — variante « brief mission + JSON »

Utile si tu veux d’abord une fiche lisible, puis le JSON.

```text
Même contraintes que le prompt dossier complet COMSPEC SSE (format comspec_sse_case_bundle).

Livrable en DEUX blocs seulement :
A) Fiche mission (FR) : titre, pitch, objectifs joueurs, objectifs bureau SSE, fausses pistes.
B) JSON unique valide (format comspec_sse_case_bundle) — rien d’autre après.

Brief : {{coller le brief}}
```

---

## 3) Emport Athena → Arma 3

Sur un dossier importé (droits gestion / export) :

| Action UI | Fichier | Usage |
|-----------|---------|--------|
| Emporter le pack dossier | `sse_dossier_….json` | Réimport Athena, archivage scénario |
| Pack terrain Arma | `sse_pack_arma_….json` | `format: comspec_sse_mission_pack` (entities + sites + athena_bundle) |
| Script Arma (.sqf) | `sse_pack_….sqf` | Pose `COMSPEC_SSE_ActiveCase` + modèle d’application d’identité |

Côté mission Arma :
1. Exécuter le `.sqf` (serveur / init).
2. Remplacer les commentaires `cetteUnite` par de vraies unités.
3. Les joueurs / Zeus exploitent via `@COMSPEC_SSE` ; la sync Athena reprend le `case_code`.

Le pack Arma peut aussi être **réimporté** dans Athena (il contient `athena_bundle` ou est converti depuis `entities`).

---

## 4) Checklist qualité avant import

- [ ] `format` = `comspec_sse_case_bundle`
- [ ] `case.title` non vide
- [ ] Enums exacts (classification, status, site_type, …)
- [ ] Au moins une identité / site / note / pièce
- [ ] `persons[].key` stables si des `evidence.person_key` pointent dessus
- [ ] JSON parseable (virgules, guillemets)

Exemple minimal : `docs/sse/examples/case-bundle-exemple.json`
