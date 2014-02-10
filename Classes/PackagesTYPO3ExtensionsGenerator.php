#! /usr/bin/env php
<?php

require('PackagesGenerator.php');

class PackagesTYPO3ExtensionsGenerator {

	protected $jsonFilePath = 'packages/packages-TYPO3Extensions-{type}.json';

	protected $extensions;

	protected $extensionKeys;

	public function __construct() {
		$extensions = $this->getExtensions();
		$packages = $this->getPackages($extensions);

		foreach ($packages as $type => $content) {
			$this->save($type, array('packages' => $content));
		}
	}

	protected function getExtensions() {
		if (!isset($this->extensions)) {
			exec('wget -O- http://typo3.org/fileadmin/ter/extensions.xml.gz | gzip -d', $output);
			$extensionsObject = new SimpleXMLElement(implode(PHP_EOL, $output));
			$this->extensions = $extensionsObject->extension;
			$this->initExtensionKeys($this->extensions);
		}
		return $this->extensions;
	}

	protected function initExtensionKeys($extensions) {
		foreach ($extensions as $extension) {
			$this->extensionKeys[(string) $extension['extensionkey']] = $extension['extensionkey'];
		}
	}

	protected function getPackages($extensions) {
		$packages = array();
		$quarter = mktime(0, 0, 0, floor((date('m') - 1) / 3) * 3 + 1, 1, date('Y'));
		foreach ($extensions as $extension) {
			foreach ($extension->version as $version) {
				if (!preg_match('/^[\d]+\.[\d]+\.[\d]+$/', $version['version'])) {
					echo 'Extension ' . (string) $extension['extensionkey'] . ' has invalid version number "' . (string) $version['version'] . '"' . PHP_EOL;
					continue;
				}
				if ((int) $version->reviewstate === -1) {
					continue;
				}

				$package = $this->getPackageArray($extension, $version);
				if ($quarter < (int) $version->lastuploaddate) {
					$packages['quarter'][$package['name']][$package['version']] = $package;
				} else {
					$packages['archive'][$package['name']][$package['version']] = $package;
				}
			}
		}
		return $packages;
	}

	protected function getPackageArray($extension, $version) {
		return array(
			'name' => 'typo3-ter/' . (string) $extension['extensionkey'],
			'description' => (string) $version->description,
			'version' => (string) $version['version'],
			'type' => 'typo3cms-extension',
			'time' => date('Y-m-d H:i:s', (int) $version->lastuploaddate),
			'authors' => array(
				array(
					'name' => (string) $version->authorname,
					'email' => (string) $version->authoremail,
					'company' => (string) $version->authorcompany,
					'username' => (string) $version->ownerusername,
				)
			),
			'require' => $this->getRequire(unserialize((string) $version->dependencies)),
			'dist' => array(
				'url' => 'http://typo3.org/extensions/repository/download/' . $extension['extensionkey'] . '/' . $version['version'] . '/t3x/',
				'type' => 't3x',
			),
		);
	}

	protected function getRequire($dependencies) {
		$require = array(
			'lw/typo3cms-installers' => '*',
		);
		foreach ($dependencies as $dependency) {
			if (
				$dependency['kind'] !== 'depends'
				|| !isset($this->extensionKeys[$dependency['extensionKey']])
			) {
				continue;
			}

			$requiredVersion = explode('-', $dependency['versionRange']);
			$minVersion = trim($requiredVersion[0]);
			$maxVersion = (isset($requiredVersion[1]) ? trim($requiredVersion[1]) : '');

			if (
				(
					(empty($minVersion) || $minVersion === '0.0.0' || $minVersion === '*')
					&& (empty($maxVersion) || $maxVersion === '0.0.0' || $maxVersion === '*')
				)
				|| !preg_match('/^([\d]\.[\d]\.[\d])*(\-)*([\d]\.[\d]\.[\d])*$/', $dependency['versionRange'])) {
				$versionConstraint = '*';
			} elseif ($maxVersion === '0.0.0' || empty($maxVersion)) {
				$versionConstraint = '>= ' . $minVersion;
			} elseif (empty($minVersion) || $minVersion === '0.0.0') {
				$versionConstraint = '<= ' . $maxVersion;
			} elseif ($minVersion === $maxVersion) {
				$versionConstraint = $minVersion;
			} else {
				$versionConstraint = '>= ' . $minVersion . ', <= ' . $maxVersion;
			}

			$require['typo3-ter/' . $dependency['extensionKey']] = $versionConstraint;
		}
		return $require;
	}

	protected function save($type, array $content) {
		system('mkdir -p ' . escapeshellarg(dirname($this->jsonFilePath)));
		file_put_contents(str_replace('{type}', $type, $this->jsonFilePath), json_encode($content));
	}
}

new PackagesTYPO3ExtensionsGenerator();

$packagesGenerator = new PackagesGenerator();
$packagesGenerator->save();