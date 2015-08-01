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
namespace TYPO3\Composer\Model;

use Webmozart\Json\JsonEncoder;

/**
 * Class SatisJson
 * @package TYPO3\Composer\Model
 */
class SatisJson
{

    /**
     * @var array
     */
    protected $data;

    /**
     * @var JsonEncoder
     */
    protected $jsonEncoder;

    /**
     * @param string $name
     */
    public function __construct($name)
    {
        if (!is_string($name)) {
            throw new \InvalidArgumentException(sprintf('Argument "$name" must be of type "string", "%s" given', gettype($name)));
        }

        $this->data = array(
            'name' => $name,
            'homepage' => null,
            'repositories' => array(),
            'require' => array(),
            'require-all' => false,
            'require-dependencies' => false,
            'require-dev-dependencies' => false,
        );
        $this->jsonEncoder = new JsonEncoder();
        $this->jsonEncoder->setPrettyPrinting(true);
    }

    /**
     * @param string $homepage
     */
    public function setHomepage($homepage)
    {
        if (!is_string($homepage)) {
            throw new \InvalidArgumentException(sprintf('Argument "$homepage" must be of type "string", "%s" given', gettype($homepage)));
        }

        $this->data['homepage'] = $homepage;
    }

    /**
     * @param array $repositoris
     */
    public function setRepositories(array $repositoris)
    {
        $this->data['repositories'] = $repositoris;
    }

    /**
     * @param array $repository
     */
    public function addRepository(array $repository)
    {
        $this->data['repositories'][] = $repository;
    }

    /**
     * @return void
     */
    public function requireAll()
    {
        $this->data['require-all'] = true;
    }

    /**
     * @return void
     */
    public function requireDependencies()
    {
        $this->data['require-dependencies'] = true;
    }

    /**
     * @return void
     */
    public function requireDevDependencies()
    {
        $this->data['require-dev-dependencies'] = true;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        if (empty($this->data['repositories'])) {
            unset($this->data['repositories']);
        }

        if (empty($this->data['require'])) {
            unset($this->data['require']);
        }

        return $this->jsonEncoder->encode($this->data);
    }
}
