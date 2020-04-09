<?php

/*
 * This file is part of the package typo3/cms-composer-package-generator.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace TYPO3\Composer\Command;

use GuzzleHttp\Client;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ExtensionsTerJsonCreateCommand extends \Symfony\Component\Console\Command\Command
{
    /**
     * @var string
     */
    const TER_XML_PATH = 'https://extensions.typo3.org/fileadmin/ter/extensions.xml.gz';

    /**
     * @var string
     */
    const COMPOSER_NAMES_URL = 'https://extensions.typo3.org/?eID=ter_fe2:extension&action=findAllWithValidComposerName';

    /**
     * @var string
     */
    const TER_HOME = 'https://extensions.typo3.org/extension/%s/';

    /**
     * @var string
     */
    const PACKAGE_NAME_PREFIX = 'typo3-ter/';

    /**
     * @var string
     */
    const PACKAGE_TYPE = 'typo3-cms-extension';

    /**
     * @var string
     */
    const JSON_FILE = 'packages-TYPO3Extensions-{type}.json';

    /**
     * @var array
     */
    protected $extensions;

    /**
     * @var array
     */
    protected $extensionKeys;

    /**
     * Extensions in this array are marked as abandoned when users install
     * them with typo3-ter/ext-key. This array is autocreated with the
     * information fetched from TER. Extensions providing a composer.json
     * will be listed here and as result the author's version will be
     * prefered over the package created here.
     *
     * Do not create pull requests for this list, simply provide a
     * composer.json for your extension.
     *
     * @var array
     */
    protected static $abandonedExtensionKeys = [
      'news' => 'georgringer/news',
      'typo3_console' => 'helhum/typo3-console',
    ];

    /**
     * Location where to output built files.
     *
     * @var string
     */
    protected $outputDir;

    /**
     * @return void
     */
    protected function configure()
    {
        $this
            ->setName('extensions:ter:json:create')
            ->setDescription('Creates packages.json files from ' . static::TER_XML_PATH)
            ->setDefinition([
                new InputArgument('output-dir', InputArgument::OPTIONAL, 'Location where to output built files', './Web'),
            ]);
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int 0 if everything went fine, or an exit code
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->outputDir = realpath($input->getArgument('output-dir'));

        $this->fetchComposerNames();
        $extensions = $this->getExtensions();
        $packages = $this->getPackages($extensions);

        foreach ($packages as $type => $content) {
            $output->writeln(sprintf('Successfully created "%s"', $this->save($type, ['packages' => $content])));
        }

        return 0;
    }

    protected function fetchComposerNames()
    {
        $client = new Client();
        $response = $client->request(
            'GET',
            static::COMPOSER_NAMES_URL,
            [
                'connect_timeout' => 2,
                'allow_redirects' => false,
            ]
        );
        $responseBody = $response->getBody();

        $json = \json_decode($responseBody, true);

        if ($json['meta'] !== null) {
            throw new \Exception($json['meta']['error']);
        }

        if (\is_array($json['data'])) {
            foreach ($json['data'] as $extKey => $settings) {
                self::$abandonedExtensionKeys[$extKey] = $settings['composer_name'];
            }
        }
    }

    /**
     * @return \SimpleXMLElement[]
     */
    protected function getExtensions()
    {
        if (!isset($this->extensions)) {
            $client = new Client();
            $response = $client->request(
                'GET',
                static::TER_XML_PATH,
                [
                    'connect_timeout' => 2,
                    'allow_redirects' => false,
                ]
            );
            $extensionsXml = gzdecode($response->getBody());
            $extensionsObject = new \SimpleXMLElement($extensionsXml);
            $this->extensions = $extensionsObject->extension;
            $this->initExtensionKeys($this->extensions);
        }

        return $this->extensions;
    }

    /**
     * @param \SimpleXMLElement[] $extensions
     */
    protected function initExtensionKeys($extensions)
    {
        foreach ($extensions as $extension) {
            $this->extensionKeys[(string)$extension['extensionkey']] = $extension['extensionkey'];
        }
    }

    /**
     * @param \SimpleXMLElement[] $extensions
     * @return array
     */
    protected function getPackages($extensions)
    {
        $packages = [];
        //$quarter = mktime(0, 0, 0, floor((date('m') - 1) / 3) * 3 + 1, 1, date('Y'));
        $dateTimeToday = new \DateTimeImmutable();
        $new = $dateTimeToday->modify('yesterday')->getTimestamp();

        foreach ($extensions as $extension) {
            foreach ($extension->version as $version) {
                if (!preg_match('/^[\d]+\.[\d]+\.[\d]+$/', $version['version'])) {
                    // Ignore extensions with invalid version numbers
                    //echo 'Extension ' . (string) $extension['extensionkey'] . ' has invalid version number "' . (string) $version['version'] . '"' . PHP_EOL;
                    continue;
                }

                $package = $this->getPackageArray($extension, $version);

                if (!isset($package['require']['typo3/cms-core'])) {
                    // Ignore extensions with invalid version numbers
                    continue;
                }

                //if ($quarter < (int)$version->lastuploaddate) {
                if ($new < (int)$version->lastuploaddate) {
                    //$packages['quarter'][$package['name']][$package['version']] = $package;
                    $packages['new'][$package['name']][$package['version']] = $package;
                } else {
                    $packages['archive'][$package['name']][$package['version']] = $package;
                }
            }
        }

        return $packages;
    }

    /**
     * @param \SimpleXMLElement $extension
     * @param \SimpleXMLElement $version
     * @return array
     */
    protected function getPackageArray(\SimpleXMLElement $extension, \SimpleXMLElement $version)
    {
        $extKey = (string)$extension['extensionkey'];
        $autoload = [
            'classmap' => [''],
            'exclude-from-classmap' => [
                'Migrations',
                'Tests',
                'tests',
                'class.ext_update.php',
            ],
        ];
        if (!empty($version->composerinfo)) {
            $composerInfo = json_decode((string)$version->composerinfo, true);
            if (!empty($composerInfo['autoload'])) {
                $autoload = $composerInfo['autoload'];
            }
        }
        $packageArray = [
            'name' => $this->getPackageName($extKey),
            'description' => (string)$version->description,
            'version' => (string)$version['version'],
            'type' => self::PACKAGE_TYPE,
            'time' => date('Y-m-d H:i:s', (int)$version->lastuploaddate),
            'homepage' => sprintf(self::TER_HOME, $extKey),
            'authors' => [
                [
                    'name' => (string)$version->authorname,
                    'email' => (string)$version->authoremail,
                    'company' => (string)$version->authorcompany,
                    'username' => (string)$version->ownerusername,
                ],
            ],
            'dist' => [
                'url' => 'https://extensions.typo3.org/extension/download/' . $extKey . '/' . $version['version'] . '/zip/',
                'type' => 'zip',
            ],
            'autoload' => $autoload,
            'extra' => [
                'typo3/cms' => [
                    'extension-key' => $extKey,
                ],
            ],
        ];

        $packageArray = array_merge(
            $packageArray,
            $this->evaluateExtensionState($extKey, (int)$version->reviewstate, (string)$version->ownerusername)
        );

        $dependencies = unserialize((string)$version->dependencies);

        if (!\is_array($dependencies)) {
            // Ignore extensions with invalid dependencies
            return [];
        }

        $packageArray = array_merge(
            $packageArray,
            $this->getPackageLinks($dependencies)
        );

        $alternativeName = self::PACKAGE_NAME_PREFIX . $extKey;
        if ($alternativeName !== $packageArray['name']) {
            $packageArray['replace'][$alternativeName] = 'self.version';
        }

        return $packageArray;
    }

    /**
     * @param array $dependencies
     * @return array
     */
    protected function getPackageLinks($dependencies)
    {
        $packageLinks = [];
        foreach ($dependencies as $dependency) {
            $linkType = '';
            switch ($dependency['kind']) {
                case 'depends':
                    $linkType = 'require';
                    break;
                case 'conflicts':
                    $linkType = 'conflict';
                    break;
                case 'suggests':
                    $linkType = 'suggest';
                    break;
                default:
                    continue 2;
            }

            if ($dependency['extensionKey'] !== 'php'
                && $dependency['extensionKey'] !== 'typo3'
                && !isset($this->extensionKeys[$dependency['extensionKey']])
            ) {
                continue;
            }

            $requiredVersion = explode('-', $dependency['versionRange']);
            $minVersion = trim($requiredVersion[0]);
            $maxVersion = (isset($requiredVersion[1]) ? trim($requiredVersion[1]) : '');

            if ((
                (empty($minVersion) || $minVersion === '0.0.0' || $minVersion === '*')
                    && (empty($maxVersion) || $maxVersion === '0.0.0' || $maxVersion === '*')
            )
                || !preg_match('/^([\d]+\.[\d]+\.[\d]+)*(\-)*([\d]+\.[\d]+\.[\d]+)*$/', $dependency['versionRange'])
            ) {
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

            $packageLinks[$linkType][$this->getPackageName($dependency['extensionKey'])] = $versionConstraint;
        }

        return $packageLinks;
    }

    /**
     * @param string $type
     * @param array $content
     * @return string
     */
    protected function save($type, array $content)
    {
        $fileName = $this->getJsonFilePath($type);
        file_put_contents($fileName, json_encode($content));

        return $fileName;
    }

    /**
     * @param string $type
     * @return string
     */
    protected function getJsonFilePath($type)
    {
        $jsonFilePath = $this->outputDir . '/' . self::JSON_FILE;
        $jsonFilePath = str_replace('{type}', $type, $jsonFilePath);

        /*
        if ($jsonFilePath[0] !== '/') {
            $jsonFilePath = dirname($_SERVER['PHP_SELF']) . '/' . $jsonFilePath;
        }
        */

        return $jsonFilePath;
    }

    /**
     * @param string $extensionKey
     * @return string
     */
    protected function getPackageName($extensionKey)
    {
        switch ($extensionKey) {
            case 'php':
                return 'php';
            case 'typo3':
                return 'typo3/cms-core';
            default:
                return self::PACKAGE_NAME_PREFIX . str_replace('_', '-', $extensionKey);
        }
    }

    /**
     * @param int $reviewState
     * @param mixed $extKey
     * @param mixed $owner
     * @return array
     */
    protected function evaluateExtensionState($extKey, $reviewState, $owner)
    {
        $packageArray = [];

        if ($reviewState === -1) {
            $packageArray['extra'] = [
                'typo3/ter' => [
                    'review-state' => 'insecure',
                ],
            ];
        }

        if ($reviewState === -2) {
            $packageArray['extra'] = [
                'typo3/ter' => [
                    'review-state' => 'outdated',
                ],
            ];
        }

        if ($owner === 'abandoned_extensions' || $owner === 'abandon') {
            $packageArray['abandoned'] = true;
        }

        if (isset(self::$abandonedExtensionKeys[$extKey])) {
            $packageArray['abandoned'] = self::$abandonedExtensionKeys[$extKey];
        }

        return $packageArray;
    }
}
