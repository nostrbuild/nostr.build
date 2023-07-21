<?php

$finder = PhpCsFixer\Finder::create()
  ->exclude('vendor')
  ->in(__DIR__)
  ->name('*.php');

$config = new PhpCsFixer\Config();
return $config->setRules(
  [
    '@PSR12'       => true,
    'strict_param' => true,
    'array_syntax' => ['syntax' => 'short'],
    'ordered_imports' => ['sort_algorithm' => 'alpha'],
    'no_unused_imports' => true,
  ]
)->setLineEnding(PHP_EOL)->setFinder($finder);
