<?php
/**
 * Brevet — gabarit « moderne » (A4 portrait, même structure : bandeau d’accent, colonnes teintées, sceau plein, statut calculé).
 * Rendu commun : {@see \App\Support\Pdf\AthenaCertificateLayout::dompdfHtml()}.
 *
 * @var array $award
 * @var string $holder_name
 * @var string $certificate_number
 */
require __DIR__ . '/_prepare.php';
echo \App\Support\Pdf\AthenaCertificateLayout::dompdfHtml(['variant' => 'moderne', 'title' => 'Certificat de qualification'] + $cert);
