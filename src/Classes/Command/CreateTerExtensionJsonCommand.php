<?php
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
namespace TYPO3\Composer\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class CreateTerExtensionJsonCommand
 * @package TYPO3\Composer\Command
 */
class CreateTerExtensionJsonCommand extends \Symfony\Component\Console\Command\Command
{

    /**
     * @var string
     */
    const TER_XML_PATH = 'https://typo3.org/fileadmin/ter/extensions.xml.gz';

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
        $extensions = $this->getExtensions();
        $packages = $this->getPackages($extensions);

        foreach ($packages as $type => $content) {
            $this->save($type, array('packages' => $content));
        }
    }

    /**
     * @return \SimpleXMLElement[]
     */
    protected function getExtensions()
    {
        if (!isset($this->extensions)) {
            exec('wget --no-check-certificate -q -O- ' . escapeshellarg($this::TER_XML_PATH) . ' | gzip -d', $output);
            $extensionsObject = new \SimpleXMLElement(implode(PHP_EOL, $output));
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

                if (!isset($package['require']['typo3/cms'])) {
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
        $packageArray = array(
            'name' => $this->getPackageName((string)$extension['extensionkey']),
            'description' => (string)$version->description,
            'version' => (string)$version['version'],
            'type' => $this::PACKAGE_TYPE,
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
                'url' => 'https://typo3.org/extensions/repository/download/' . $extension['extensionkey'] . '/' . $version['version'] . '/t3x/',
                'type' => 't3x',
            ),
            'autoload' => array(
                'classmap' => array('')
            )
        );

        $packageArray = array_merge(
            $packageArray,
            $this->evaluateReviewState($version->reviewstate)
        );

        $packageArray = array_merge(
            $packageArray,
            $this->getPackageLinks(unserialize((string)$version->dependencies))
        );

        $alternativeName = $this::PACKAGE_NAME_PREFIX . (string)$extension['extensionkey'];
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
    protected function getPackageName($extensionKey)
    {
        switch ($extensionKey) {
            case 'php':
                return 'php';
            case 'typo3':
                return 'typo3/cms';
            default:
                return $this::PACKAGE_NAME_PREFIX . str_replace('_', '-', $extensionKey);
        }
    }

    /**
     * @param int $reviewstate
     * @return array
     */
    protected function evaluateReviewState($reviewstate)
    {
        $return = array();

        if ((int)$reviewstate === -1) {
            $return['extra'] = array(
                'typo3/ter' => array(
                    'reviewstate' => 'insecure'
                )
            );
        }

        return $return;
    }
}
