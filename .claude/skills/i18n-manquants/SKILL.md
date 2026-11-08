---
name: i18n-manquants
description: "Traduit en anglais seulement les textes que tools/i18n signale comme manquants dans lang/en/site_auto.php. Utiliser après un changement de vue ou de script du site."
argument-hint: "[dossier de views ou fichier à traiter]"
---
Les vues restent écrites en français. Le dictionnaire est `lang/en/site_auto.php` (`'français' => 'English'`) : il est très gros, n'y lis que des plages courtes.

1. **Relever** : `php tools/i18n/untranslated.php <fichiers>` (views et public/assets/js par défaut ; `app` pour les textes composés du code PHP ; `--count` pour un décompte par fichier). Seuls les fichiers de ta PR comptent.
2. **Extraire** : `python3 tools/i18n/site_i18n.py extract <dossier>` (ou `extract-app`, `extract-js`, `extract-labels`) écrit `tools/i18n/site_missing.tsv`. Ce fichier est temporaire : ne le committe pas.
3. **Traduire** uniquement les lignes relevées, selon `docs/I18N_GLOSSARY.md`. Garde les modèles `%1`, `%2` à l'identique. Ne traduis jamais un indicatif, un identifiant d'API, une valeur stockée ni un contenu écrit par un utilisateur.
4. **Fusionner** : `python3 tools/i18n/site_i18n.py merge tools/i18n/site_missing.tsv`, puis `python3 tools/i18n/site_i18n.py js-bundle` si des scripts sont concernés.
5. **Vérifier** : relance `php tools/i18n/untranslated.php` sur les mêmes fichiers. Un texte volontairement français va dans `tools/i18n/untranslated_keep.tsv` (texte, raison, source), pas dans le dictionnaire.
6. **Sortie** : nombre de textes ajoutés et fichiers couverts, en 5 lignes.
