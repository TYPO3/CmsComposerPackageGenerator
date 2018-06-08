<?php
namespace TYPO3\Composer\Command;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use GuzzleHttp\Client;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Webmozart\Json\JsonDecoder;

/**
 * Class CreateTerExtensionJsonCommand
 * @package TYPO3\Composer\Command
 */
class CreateTerExtensionJsonCommand extends \Symfony\Component\Console\Command\Command
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
    const PACKAGE_NAME_PREFIX = 'typo3-ter/';

    /**
     * @var string
     */
    const PACKAGE_TYPE = 'typo3-cms-extension';

    /**
     * @var string
     */
    const JSON_FILE_PATH = '../Web/packages-TYPO3Extensions-{type}.json';

    /**
     * @var array
     */
    protected $extensions;

    /**
     * @var array
     */
    protected $extensionKeys;

    /**
     * Extensions in this array are marked as abandoned when users install them with typo3-ter/ext-key
     *
     * @var array
     */
    protected static $abandonedExtensionKeys = array(

      'news' => 'georgringer/news',
      'typo3_console' => 'helhum/typo3-console',

    );

    /**
     * @return void
     */
    protected function configure()
    {
        $this
            ->setName('extensions:ter:json:create')
            ->setDescription('Creates packages.json files from ' . static::TER_XML_PATH);
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->fetchComposerNames();
        $extensions = $this->getExtensions();
        $packages = $this->getPackages($extensions);

        foreach ($packages as $type => $content) {
            $this->save($type, array('packages' => $content));
        }
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

        $jsonDecoder = new JsonDecoder();
        $jsonDecoder->setObjectDecoding(JsonDecoder::ASSOC_ARRAY);

        $json = $jsonDecoder->decode($responseBody);

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
     * @return void
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
        $packages = array();
        $quarter = mktime(0, 0, 0, floor((date('m') - 1) / 3) * 3 + 1, 1, date('Y'));
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

                if ($quarter < (int)$version->lastuploaddate) {
                    $packages['quarter'][$package['name']][$package['version']] = $package;
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
        $autoload = array(
            'classmap' => array(''),
            'exclude-from-classmap' => array(
                'Migrations',
                'Tests',
                'tests',
                'class.ext_update.php',
            ),
        );
        if (!empty($version->composerinfo)) {
            $composerInfo = json_decode((string)$version->composerinfo, true);
            if (!empty($composerInfo['autoload'])) {
                $autoload = $composerInfo['autoload'];
            }
        }
        $packageArray = array(
            'name' => $this->getPackageName((string)$extension['extensionkey']),
            'description' => (string)$version->description,
            'version' => (string)$version['version'],
            'type' => self::PACKAGE_TYPE,
            'time' => date('Y-m-d H:i:s', (int)$version->lastuploaddate),
            'authors' => array(
                array(
                    'name' => (string)$version->authorname,
                    'email' => (string)$version->authoremail,
                    'company' => (string)$version->authorcompany,
                    'username' => (string)$version->ownerusername,
                )
            ),
            'dist' => array(
                'url' => 'https://extensions.typo3.org/extension/download/' . $extKey . '/' . $version['version'] . '/zip/',
                'type' => 'zip',
            ),
            'autoload' => $autoload
        );

        if (isset(self::$abandonedExtensionKeys[$extKey])) {
            $packageArray['abandoned'] = self::$abandonedExtensionKeys[$extKey];
        }

        $packageArray = array_merge(
            $packageArray,
            $this->evaluateReviewState($version->reviewstate)
        );

        $dependencies = unserialize((string)$version->dependencies, ['allowed_classes' => false]);

        if (!\is_array($dependencies)) {
            // Ignore extensions with invalid dependencies
            return [];
        }

        $packageArray = array_merge(
            $packageArray,
            $this->getPackageLinks($dependencies)
        );

        $packageArray['replace'][(string)$extension['extensionkey']] = 'self.version';
        $alternativeName = self::PACKAGE_NAME_PREFIX . (string)$extension['extensionkey'];
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
        $packageLinks = array();
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
                    continue;
                    break;
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
     * @return void
     */
    protected function save($type, array $content)
    {
        file_put_contents($this->getJsonFilePath($type), json_encode($content));
    }

    /**
     * @param string $type
     * @return string
     */
    protected function getJsonFilePath($type)
    {
        $jsonFilePath = self::JSON_FILE_PATH;
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
     * @return array
     */
    protected function evaluateReviewState($reviewState)
    {
        $return = array();

        if ((int)$reviewState === -1) {
            $return['extra'] = array(
                'typo3/ter' => array(
                    'review-state' => 'insecure'
                )
            );
        }

        return $return;
    }
}
