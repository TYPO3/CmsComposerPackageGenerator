<?php

$config = \TYPO3\CodingStandards\CsFixerConfig::create();
$config->getFinder()
    ->in(__DIR__ . '/src/')
    ->in(__DIR__ . '/Tests/');
return $config;
