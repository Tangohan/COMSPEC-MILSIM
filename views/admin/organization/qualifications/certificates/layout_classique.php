<?php
/**
 * Brevet — gabarit « classique » (A4 portrait, maquette Athena : double cadre noir / filet vert, sceau à filets).
 * Rendu commun : {@see \App\Support\Pdf\AthenaCertificateLayout::dompdfHtml()}.
 *
 * @var array $award
 * @var string $holder_name
 * @var string $certificate_number
 */
require __DIR__ . '/_prepare.php';
unset($cert['status']);
echo \App\Support\Pdf\AthenaCertificateLayout::dompdfHtml(['variant' => 'classique', 'title' => 'Brevet de qualification'] + $cert);
