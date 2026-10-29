<?php
/** @var string $space */
$lang = locale() === 'en' ? 'en' : 'fr';
$copy = require base_path('lang/' . $lang . '/academy.php');
$t = static fn (string $key): string => $copy[$key] ?? $key;
$h = static fn (mixed $text): string => htmlspecialchars((string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$local = static fn (array $value): string => (string) ($value[$lang] ?? '');
$link = static fn (string $path = ''): string => url('academy' . ($path !== '' ? '/' . $path : ''));
$allSpaces = ['dashboard','catalogue','courses','classroom','laboratory','simulators','exams','library','notebook','record','instructor'];
$symbols = ['dashboard'=>'◫','catalogue'=>'▦','courses'=>'▤','classroom'=>'▱','laboratory'=>'◇','simulators'=>'◎','exams'=>'◷','library'=>'▥','notebook'=>'▧','record'=>'▣','instructor'=>'♙'];
?>
