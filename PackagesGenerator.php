<?php

class PackagesGenerator {

	protected $filePath = 'packages/packages.json';

	protected $packagesPattern = 'packages/packages-*.json';

	public function save() {
		file_put_contents($this->filePath, json_encode($this->getContent()));
	}

	protected function getFiles() {
		return glob($this->packagesPattern);
	}

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

	protected function getContent() {
		return array(
			'notify-batch' => '/downloads/',
			'includes' => $this->getIncludes($this->getFiles())
		);
	}
}