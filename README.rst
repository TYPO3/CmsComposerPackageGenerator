TYPO3 CMS Composer Package Generator
====================================

This script generates among other a custom packages.json enabling to deploy TYPO3 CMS packages by Composer.
The file main file ``PackagesTYPO3CMSGenerator.php`` downloads the Git source, iterates over each tag and looks for the system extensions to resolve the correct dependencies.
It will also generate some other files such as:

* packages-TYPO3Extensions-archive.json: contains all extensions including their respective versions which are older than the last quarter. The file is about 14 MB.
* packages-TYPO3Extensions-quarter.json: contains just the extension including their respective versions of the last quarter

Basically, packages.json "just" contains references to the other files identified by a sha1 hash and a name.

When launching the ``composer update`` or ``composer install`` command, Composer will download this packages.json file.

File structure
==============

::

	├── Build     -> Files for setting up this package and install dependencies.
	├── Classes   -> PHP classes as its name indicates
	├── Data      -> Temporary files
	├── Web       -> Files visible by the outside world
	├── build.xml -> Phing tasks

Run the unit tests
==================
./bin/phpunit -c ./Build/UnitTests.xml [--colors]
