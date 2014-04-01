#! /usr/bin/env php
<?php

require('PackagesGenerator.php');

class PackagesTYPO3ExtensionsGenerator {

	const TER_XML_PATH = 'http://typo3.org/fileadmin/ter/extensions.xml.gz';

	const PACKAGE_NAME_PREFIX = 'typo3/cms-';

	const PACKAGE_TYPE = 'typo3-cms-extension';

	const INSTALLER_PACKAGE_NAME = 'typo3/cms-extension-installer';

	const JSON_FILE_PATH = '../Web/packages-TYPO3Extensions-{type}.json';

	protected $extensions;

	protected $extensionKeys;

	public function __construct() {
		$extensions = $this->getExtensions();
		$packages = $this->getPackages($extensions);

		foreach ($packages as $type => $content) {
			$this->save($type, array('packages' => $content));
		}
	}

	/**
	 * @return SimpleXMLElement[]
	 */
	protected function getExtensions() {
		if (!isset($this->extensions)) {
			exec('wget -q -O- ' . escapeshellarg($this::TER_XML_PATH) . ' | gzip -d', $output);
			$extensionsObject = new SimpleXMLElement(implode(PHP_EOL, $output));
			$this->extensions = $extensionsObject->extension;
			$this->initExtensionKeys($this->extensions);
		}
		return $this->extensions;
	}

	/**
	 * @param SimpleXMLElement[] $extensions
	 * @return void
	 */
	protected function initExtensionKeys($extensions) {
		foreach ($extensions as $extension) {
			$this->extensionKeys[(string) $extension['extensionkey']] = $extension['extensionkey'];
		}
	}

	/**
	 * @param SimpleXMLElement[] $extensions
	 * @return array
	 */
	protected function getPackages($extensions) {
		$packages = array();
		$quarter = mktime(0, 0, 0, floor((date('m') - 1) / 3) * 3 + 1, 1, date('Y'));
		foreach ($extensions as $extension) {
			foreach ($extension->version as $version) {
				if (!preg_match('/^[\d]+\.[\d]+\.[\d]+$/', $version['version'])) {
					// Ignore extensions with invalid version numbers
					//echo 'Extension ' . (string) $extension['extensionkey'] . ' has invalid version number "' . (string) $version['version'] . '"' . PHP_EOL;
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

	/**
	 * @param SimpleXMLElement $extension
	 * @param SimpleXMLElement $version
	 * @return array
	 */
	protected function getPackageArray(SimpleXMLElement $extension, SimpleXMLElement $version) {
		return array(
			'name' =>  $this->getPackageName((string) $extension['extensionkey']),
			'description' => (string) $version->description,
			'version' => (string) $version['version'],
			'type' => $this::PACKAGE_TYPE,
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
			'replace' => array(
				(string) $extension['extensionkey'] => (string) $version['version'],
				'typo3-ext/' . (string) $extension['extensionkey'] => (string) $version['version'],
			),
			'dist' => array(
				'url' => 'http://typo3.org/extensions/repository/download/' . $extension['extensionkey'] . '/' . $version['version'] . '/t3x/',
				'type' => 't3x',
			),
		);
	}

	/**
	 * @param array $dependencies
	 * @return array
	 */
	protected function getRequire($dependencies) {
		$require = array(
			$this::INSTALLER_PACKAGE_NAME => '*',
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

			$require[$this->getPackageName($dependency['extensionKey'])] = $versionConstraint;
		}
		return $require;
	}

	/**
	 * @param string $type
	 * @param array $content
	 * @return void
	 */
	protected function save($type, array $content) {
		file_put_contents($this->getJsonFilePath($type), json_encode($content));
	}

	/**
	 * @param string $type
	 * @return string
	 */
	protected function getJsonFilePath($type) {
		$jsonFilePath = $this::JSON_FILE_PATH;
		$jsonFilePath = str_replace('{type}', $type, $jsonFilePath);
		if ($jsonFilePath{0} !== '/') {
			$jsonFilePath = dirname($_SERVER['PHP_SELF']) . '/' . $jsonFilePath;
		}
		return $jsonFilePath;
	}

	/**
	 * @param string $extensionKey
	 * @return string
	 */
	protected function getPackageName($extensionKey) {
		return $this::PACKAGE_NAME_PREFIX . str_replace('_', '-', $extensionKey);
	}
}

new PackagesTYPO3ExtensionsGenerator();

$packagesGenerator = new PackagesGenerator();
$packagesGenerator->save();