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
/* Contenu de cours FR/EN : langue de l'interface, repli sur le français si la traduction manque. */
$local = static fn (array $value): string => trim((string) ($value[$lang] ?? '')) !== '' ? (string) $value[$lang] : (string) ($value['fr'] ?? '');
/** Texte d'étape mis en forme (AcademyRichText : échappé puis balisé côté serveur ; texte brut = paragraphes comme avant). */
$rich = static fn (array $value): string => \App\Services\Academy\AcademyRichText::render($local($value), $copy);
/* Débriefing « Rien à améliorer » (case cochée) : affiché dans la langue de l'interface. */
$debriefText = static fn (mixed $text): string => $text === \App\Services\Academy\AcademyCurriculum::NOTHING_TO_IMPROVE ? $t('nothing_to_improve') : (string) $text;
$link = static fn (string $path = ''): string => url('academy' . ($path !== '' ? '/' . $path : ''));
$allSpaces = ['dashboard','catalogue','courses','classroom','laboratory','simulators','exams','library','notebook','record','instructor','suivi','studio'];
$symbols = ['dashboard'=>'◫','catalogue'=>'▦','courses'=>'▤','classroom'=>'▱','laboratory'=>'◇','simulators'=>'◎','exams'=>'◷','library'=>'▥','notebook'=>'▧','record'=>'▣','instructor'=>'♙','suivi'=>'▦','studio'=>'✎'];
?>
