<?php
declare(strict_types=1);
/**
 * Présentation des documents (menu Production) — page de référence du système de design SSE (sse-ds.css / sse-ds.js).
 * Un en-tête (le fil d'Ariane vient du layout), des tuiles de chiffres, UNE file de modèles type ticketing (onglets à
 * compteur, recherche, filtre, tri, lignes de 64 px), UN tiroir d'aperçu au clic et des modales (modifier / créer,
 * utiliser). Mêmes routes, mêmes champs de formulaire, mêmes droits que l'ancienne page.
 *
 * @var list<array<string,mixed>> $prefabs
 * @var string $activePrefabCode
 * @var array<string,mixed>|null $editPrefab
 * @var array<string,string> $paperStyles
 * @var bool $canManage
 */

ob_start();
require __DIR__ . '/partials/document_presentation_body.php';
$sseContent = ob_get_clean();
require __DIR__ . '/_layout.php';
