<?php
declare(strict_types=1);
/** Feuilles de style de l'onglet « Identité & papiers » de la fiche membre, réutilisé tel quel dans le bureau SSE. */
foreach (['back-office-effectifs-workspace.css', 'back-office-effectifs-fiche.css', 'back-office-member-situation.css', 'back-office-member-papers.css'] as $memberPapersCss) {
    $sseExtraStyles = ($sseExtraStyles ?? '') . '<link rel="stylesheet" href="' . htmlspecialchars(asset_url('assets/css/' . $memberPapersCss), ENT_QUOTES, 'UTF-8') . '">';
}
