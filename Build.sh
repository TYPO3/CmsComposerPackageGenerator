#! /usr/bin/env php
<?php

require __DIR__ . '/vendor/autoload.php';

new PackagesTYPO3ExtensionsGenerator();

$packagesGenerator = new PackagesGenerator();
$packagesGenerator->save();
