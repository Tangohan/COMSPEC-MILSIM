# Gabarits de brevet de qualification ATHENA

Références visuelles (modèles vierges) :

- [`template_classique_vierge.pdf`](qualification-certificate-templates/template_classique_vierge.pdf)
- [`template_moderne_vierge.pdf`](qualification-certificate-templates/template_moderne_vierge.pdf)

Ces PDF ne sont **pas** remplis à l’exécution. La génération produit un document HTML rendu en PDF (Dompdf) à partir des layouts système `classique` et `moderne`.

## Layouts système

| Code | Usage |
|---|---|
| `classique` | Brevet type « Unit Personnel Record » — en-tête ATHENA, catégorie, insigne, titre BREVET DE QUALIFICATION, blocs titulaire / qualification / dates / N° / signature |
| `moderne` | Certificat contemporain — bandeau ATHENA, catégorie, insigne, titre CERTIFICAT DE QUALIFICATION, décerné à, grille métadonnées + statut calculé |

Une qualification peut pointer vers un gabarit via `certificate_template_id`. À défaut, le gabarit système marqué `is_default` (Classique) est utilisé.

## Zones renseignées

- Nom du titulaire
- Nom de la qualification (+ niveau si applicable)
- Catégorie
- Organisme émetteur
- Date d’obtention
- Date d’expiration (ou tiret si permanente)
- Numéro de brevet
- Insigne (badge définition, ou badge niveau s’il existe)
- Mention « Généré par ATHENA le [date/heure] »
- Statut temporel calculé (layout Moderne uniquement)

## Numérotation des brevets

Si `certificate_number` est vide au moment de la génération, un numéro est attribué selon le format de la définition (`certificate_number_format`) ou, à défaut :

```text
QUAL-{code}-{year}-{seq}
```

Placeholders reconnus : `{code}`, `{year}` / `{année}`, `{seq}` / `{sequence}`.

La séquence est monotoone par communauté et par année civile (`qualification_certificate_sequences`).

## Régénération

Chaque génération crée un nouveau fichier sous `storage/uploads/qualifications/{tenant}/certificates/{user}/`. L’attribution pointe vers le dernier document ; l’historique administratif conserve l’événement de génération.
