<?php
/** @var string $space */
$lang = locale() === 'en' ? 'en' : 'fr';
$copy = require base_path('lang/' . $lang . '/academy.php');
/* Espace instructeur et droits d'accès : dictionnaire séparé, complété par celui de l'Academy. */
$copy += require base_path('lang/' . $lang . '/academy_access.php');
/* Studio : qualifications et brevets. */
$copy += require base_path('lang/' . $lang . '/academy_studio.php');
$t = static fn (string $key): string => $copy[$key] ?? $key;
$h = static fn (mixed $text): string => htmlspecialchars((string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$local = static fn (array $value): string => (string) ($value[$lang] ?? '');
/** Texte d'étape mis en forme (AcademyRichText : échappé puis balisé côté serveur ; texte brut = paragraphes comme avant). */
$rich = static fn (array $value): string => \App\Services\Academy\AcademyRichText::render($local($value), $copy);
$link = static fn (string $path = ''): string => url('academy' . ($path !== '' ? '/' . $path : ''));
$allSpaces = ['dashboard','catalogue','courses','classroom','laboratory','simulators','exams','library','notebook','record','instructor','studio'];
$symbols = ['dashboard'=>'◫','catalogue'=>'▦','courses'=>'▤','classroom'=>'▱','laboratory'=>'◇','simulators'=>'◎','exams'=>'◷','library'=>'▥','notebook'=>'▧','record'=>'▣','instructor'=>'♙','studio'=>'✎'];
?>
