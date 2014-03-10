<?php

class PackagesGenerator {

	const JSON_FILE_PATH = '../Web/packages.json';

	const JSON_PACKAGES_PATTERN = '../Web/packages-*.json';

	/**
	 * @return void
	 */
	public function save() {
		file_put_contents($this->getJsonFilePath(), json_encode($this->getContent()));
	}

	/**
	 * @return array
	 */
	protected function getContent() {
		return array(
//			'notify-batch' => '/downloads/',
			'includes' => $this->getIncludes($this->getFiles())
		);
	}

	/**
	 * @param $files
	 * @return array
	 */
	protected function getIncludes($files) {
		$includes = array();
		foreach ($files as $file) {
			$fileName = basename($file);
			$includes[$fileName] = array(
				'sha1' => sha1_file($file)
			);
		}
		return $includes;
	}

	/**
	 * @return array
	 */
	protected function getFiles() {
		return glob($this->getJsonPackagesPattern());
	}

	/**
	 * @return string
	 */
	protected function getJsonFilePath() {
		return $this->getScriptRelativeFilePath($this::JSON_FILE_PATH);
	}

	/**
	 * @return string
	 */
	protected function getJsonPackagesPattern() {
		return $this->getScriptRelativeFilePath($this::JSON_PACKAGES_PATTERN);
	}

	/**
	 * @return string
	 */
	protected function getScriptRelativeFilePath($filePath) {
		if ($filePath{0} !== '/') {
			$filePath = dirname($_SERVER['PHP_SELF']) . '/' . $filePath;
		}
		return $filePath;
	}
}