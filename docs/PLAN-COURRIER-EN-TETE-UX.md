# Plan Courrier : en-tête, UX aperçu, lecture, notifications, signature

Document de suivi (itération : signature incluse dans le document).

## Portée déjà définie

1. **En-tête** : `metadata_json` (ministère, unité, section) + `DocumentBuilderService::buildEnvelopeHtml`.
2. **Alinéas** : CSS `.courrier-alinea` + boutons dans `courrier-editor.js`.
3. **Aperçu** : correction bandeau classification, ligne « à » sobre, espacements (`courrier-document.css` + `buildEnvelopeHtml`).
4. **Page lecture** : gabarit `max-w-7xl`, prévisualisation alignée sur l’éditeur.
5. **Notifications** : table `courrier_document_notifications` + UI « Notifier ».

## 7. Signature incluse dans le document (ajout plan)

### État actuel

- [`DocumentBuilderService::injectSignatureBlock`](app/Services/Courrier/DocumentBuilderService.php) remplace **`{{signature_block}}`** dans `body_rendered` par :
  - **Brouillon non signé** : placeholder (titre SIGNATURE, cadre pointillé « Signature Numérique », libellé émetteur si `issuer_label`).
  - **Signé** : image + tampons + code vérification.
- Les modèles SQL / seed incluent `{{signature_block}}` en fin de template ([`run-migrations.php`](run-migrations.php) lignes templates courrier).

### Problèmes à traiter dans l’implémentation

1. **Visibilité** : si un document / modèle **n’inclut pas** `{{signature_block}}` (corps édité à la main, collage), la signature **n’apparaît pas**. Prévoir soit :
   - **append automatique** : après injection, si le marqueur est absent, concaténer le bloc signature en fin de `courrier-body` (ou une seule fois en pied de page), **ou**
   - UI éditeur : rappel / bouton « Insérer le bloc signature » qui injecte `{{signature_block}}` au curseur.
2. **Rendu aperçu / maquette** : le placeholder utilise des classes utilitaires Tailwind (`mt-24`, `w-1/3`, etc.) ; l’aperçu Courrier s’appuie sur [`courrier-document.css`](public/assets/css/courrier-document.css) (sous-ensemble de classes). **Harmoniser** avec des classes sémantiques dédiées (ex. `.courrier-signature-block`, `.courrier-signature-title`, `.courrier-signature-placeholder`) pour reproduire la maquette :
   - titre **SIGNATURE** en gras, majuscules, souligné ;
   - rectangle à bordure **tirets**, texte gris « Signature Numérique » centré ;
   - nom / grade (`issuer_label`) sous le cadre, centré, style courant institutionnel.
3. **Document signé** : aligner la mise en page (centrage, espacements) avec le même gabarit visuel que le placeholder quand c’est possible (image dans ou sous le cadre selon contraintes).
4. **PDF / impression** : vérifier que [`DocumentExportService`](app/Services/Courrier/DocumentExportService.php) / pipeline print utilise le même HTML après `injectSignatureBlock`, pour que la signature soit **identique** à l’aperçu écran.

### Critères de done

- Aperçu éditeur et page lecture affichent un bloc signature **lisible** et **conforme** à la maquette (placeholder au minimum).
- Impression / PDF incluent ce bloc.
- Aucun document courrier « perdu » sans signature en pied : règle append ou insertion guidée documentée et testée.
