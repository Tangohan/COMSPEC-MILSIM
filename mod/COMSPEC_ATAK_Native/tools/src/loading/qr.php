<?php
// Matrice du QR (lignes de 0 et de 1) pour l'adresse donnée, par la bibliothèque phpqrcode du dépôt.
require dirname(__DIR__, 5) . '/phpqrcode/qrlib.php';
$rows = QRcode::text($argv[1], false, QR_ECLEVEL_M, 1, 0);
echo json_encode($rows);
