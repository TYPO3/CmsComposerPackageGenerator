<?php

$config = \TYPO3\CodingStandards\CsFixerConfig::create();
$config
    ->setHeader(
        <<<EOM
This file is part of the package typo3/cms-composer-package-generator.

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

For the full copyright and license information, please read the
LICENSE file that was distributed with this source code.

The TYPO3 project - inspiring people to share!
EOM,
        true)
    ->getFinder()
        ->exclude(['Build', 'Data', 'tools', 'var', 'vendor', 'Web'])
        ->in(__DIR__)
;

return $config;
