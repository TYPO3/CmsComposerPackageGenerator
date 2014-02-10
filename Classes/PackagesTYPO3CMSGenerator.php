#! /usr/bin/env php
<?php

require('PackagesGenerator.php');

class PackagesTYPO3CMSGenerator {

	protected $jsonFilePath = 'packages/packages-TYPO3CMS.json';

	protected $tempDirectory = 'temp/';

	public function __construct() {
		$this->warmUpGitRepository();
		$versions = $this->getVersions();
		$processedPackages = $this->getProcessedPackages();
		$packages = $this->getPackages($versions, $processedPackages);
		$this->save(array('packages' => $packages));
	}

	protected function getVersions() {
		return json_decode(file_get_contents('http://get.typo3.org/json'), TRUE);
	}

	protected function getProcessedPackages() {
		$packages = array();
		if (file_exists($this->jsonFilePath)) {
			$json = json_decode(file_get_contents($this->jsonFilePath), TRUE);
			$packages = $json['packages'];
		}
		return $packages;
	}

	protected function getPackages(array $minorReleases, array $packages) {
		$existingVersions = $this->getVersionNumbersFromPackages($packages);

		foreach ($minorReleases as $minorRelease) {
			if (empty($minorRelease['active']) || empty($minorRelease['releases'])) {
				continue;
			}
			foreach ($minorRelease['releases'] as $patchLevel) {
				$partsOfVersion = $this->getPartsOfVersion($patchLevel['version']);
				if (empty($partsOfVersion) || in_array($partsOfVersion[0], $existingVersions)) {
					continue;
				}
				echo 'Fetching ' . $patchLevel['version'] . '..' . PHP_EOL;
				$package = $this->getPackageArray($patchLevel, $partsOfVersion);
				$packages[$package['name']][$package['version']] = $package;
			}
		}

		return $packages;
	}

	protected function getPackageArray(array $patchLevel, array $partsOfVersion) {
		return array(
			'name' => 'typo3/cms',
			'description' => 'TYPO3 is an enterprise class Web CMS written in PHP/MySQL.',
			'version' => $partsOfVersion[0],
			'type' => 'typo3cms-core',
			'homepage' => 'http://typo3.org',
			'time' => date('Y-m-d H:i:s', strtotime($patchLevel['date'])),
			'license' => 'GPL-2.0+',
			'authors' => array(
				array(
					'name' => 'Kasper Skårhøj',
					'email' => 'kasperYYYY@typo3.com',
					'role' => 'Founder'
				),
				array(
					'name' => 'TYPO3 Association',
					'homepage' => 'http://association.typo3.org/',
					'role' => 'Organisation'
				)
			),
			'support' => array(
				'irc' => 'irc://irc.freenode.net/#typo3',
				'wiki' => 'http://wiki.typo3.org',
				'forum' => 'http://forum.typo3.org',
				'source' => 'http://typo3.org/download',
				'issues' => 'http://forge.typo3.org/projects/typo3cms-core/issues'
			),
			'require' => array(
				'lw/typo3cms-installers' => '*',
			),
			'dist' => array(
				'url' => $patchLevel['url']['zip'],
				'type' => 'zip',
			),
			'source' => array(
				'url' => 'git://git.typo3.org/Packages/TYPO3.CMS.git',
				'type' => 'git',
				'reference' => 'TYPO3_' . str_replace('.', '-', strtolower($patchLevel['version']))
			),
			'provide' => $this->getProvides($patchLevel['version'])
		);
	}

	protected function getVersionNumbersFromPackages($packages) {
		$versions = array();
		foreach ($packages as $package) {
			$version = $package['version'];
			$versions[$version] = $version;
		}
		return $versions;
	}

	protected function getPartsOfVersion($version) {
		if (!preg_match('/^([\d]+\.[\d]+\.[\d]+)-?(dev|patch|alpha|beta|RC)?([\d]*)$/', strtolower($version), $partsOfVersion)) {
			return array();
		}
		$cleanedUpVersion = $partsOfVersion[1];
		if (!empty($partsOfVersion[2])) {
			if ($partsOfVersion[2]{1} === 'r') {
				$cleanedUpVersion .= '-' . strtoupper($partsOfVersion[2]);
				if (!empty($partsOfVersion[3])) {
					$cleanedUpVersion .= $partsOfVersion[3];
				}
			} else {
				$cleanedUpVersion .= '-' . $partsOfVersion[2];
			}
		}
		$partsOfVersion[0] = $cleanedUpVersion;
		return $partsOfVersion;
	}

	protected function warmUpGitRepository() {
		if (!is_dir('temp/TYPO3.CMS')) {
			system('mkdir -p ' . $this->tempDirectory . ' && cd ' . $this->tempDirectory . ' && git clone --recursive git://git.typo3.org/Packages/TYPO3.CMS.git');
		} else {
			system('cd ' . $this->tempDirectory . 'TYPO3.CMS && git reset -q --hard HEAD && git clean -q -d -x -ff && git fetch -q origin && git fetch --tags');
		}
	}

//	protected function getTagReference($version) {
//		$gitTag = 'TYPO3_' . str_replace('.', '-', strtolower($version));
//		exec('cd ' . $this->tempDirectory . 'TYPO3.CMS && git ls-remote origin refs/tags/' . $gitTag . ' | awk \'{print $1 }\'', $reference);
//		return trim($reference[0]);
//	}

	protected function getProvides($version) {
		$provides = array();
		$gitTag = 'TYPO3_' . str_replace('.', '-', strtolower($version));
		system('cd ' . $this->tempDirectory . 'TYPO3.CMS && git checkout -f -q ' . $gitTag . ' && git clean -q -d -x -ff . && git submodule update --init -q');
		if (!is_dir($this->tempDirectory . 'TYPO3.CMS/typo3/sysext')) {
			return $provides;
		}
		$extensions = scandir($this->tempDirectory . 'TYPO3.CMS/typo3/sysext');
		foreach ($extensions as $extension) {
			if ($extension === '.' || $extension === '..' || !file_exists($this->tempDirectory . 'TYPO3.CMS/typo3/sysext/' . $extension . '/ext_emconf.php')) {
				continue;
			}
			$_EXTKEY = 'temp';
			require($this->tempDirectory . 'TYPO3.CMS/typo3/sysext/' . $extension . '/ext_emconf.php');
			$provides['typo3-ter/' . $extension] = $EM_CONF['temp']['version'];
			unset($EM_CONF);
		}
		return $provides;
	}

//	protected function getGitTypo3OrgProvides($version) {
//		$provides = array();
//		$gitTag = 'TYPO3_' . str_replace('.', '-', strtolower($version));
//		$html = file_get_contents('https://git.typo3.org/Packages/TYPO3.CMS.git/tree/refs/tags/' . $gitTag . ':/typo3/sysext');
//		preg_match_all('/\<td class="list"><a[^>]+>\s*([a-z0-9_]+)/', $html, $extensionMatches);
//		foreach ($extensionMatches[1] as $extension) {
//			$extEmConf = file_get_contents('https://git.typo3.org/Packages/TYPO3.CMS.git/blob_plain/refs/tags/' . $gitTag . ':/typo3/sysext/' . $extension . '/ext_emconf.php');
//			preg_match('/[\'"]version[\'"]\s*=>\s*[\'"]([0-9\.]+)/', $extEmConf, $versionMatch);
//			$provides['typo3-ter/' . $extension] = $versionMatch[1];
//		}
//		return $provides;
//	}

	protected function save(array $packages) {
		system('mkdir -p ' . escapeshellarg(dirname($this->jsonFilePath)));
		file_put_contents($this->jsonFilePath, json_encode($packages));
	}

}

new PackagesTYPO3CMSGenerator();

$packagesGenerator = new PackagesGenerator();
$packagesGenerator->save();