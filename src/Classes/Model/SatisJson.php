<?php

namespace TYPO3\Composer\Model;

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

class SatisJson
{
    /**
     * @var array
     */
    protected $data;

    /**
     * @param string $name
     */
    public function __construct($name)
    {
        if (!is_string($name)) {
            throw new \InvalidArgumentException(sprintf('Argument "$name" must be of type "string", "%s" given', gettype($name)));
        }

        $this->data = [
            'name' => $name,
            'homepage' => null,
            'repositories' => [],
            'require' => [],
            'require-all' => false,
            'require-dependencies' => false,
            'require-dev-dependencies' => false,
        ];
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

    public function requireAll()
    {
        $this->data['require-all'] = true;
    }

    public function requireDependencies()
    {
        $this->data['require-dependencies'] = true;
    }

    public function requireDevDependencies()
    {
        $this->data['require-dev-dependencies'] = true;
    }

    public function useProviders()
    {
        $this->data['providers'] = true;
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

        return \json_encode($this->data, JSON_PRETTY_PRINT);
    }
}
