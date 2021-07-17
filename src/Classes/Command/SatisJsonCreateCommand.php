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

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\Composer\Model\SatisJson;

class SatisJsonCreateCommand extends \Symfony\Component\Console\Command\Command
{
    protected function configure()
    {
        $this
            ->setName('satis:json:create')
            ->setDescription('Create a satis.json')
            ->setDefinition([
                new InputArgument('file', InputArgument::OPTIONAL, 'Json file to create', './satis.json'),
                new InputArgument('repository-dir', InputArgument::OPTIONAL, 'Location where to search for repository files', './Web'),
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
        $configFile = $input->getArgument('file');
        $repositoryDir = realpath($input->getArgument('repository-dir'));

        if (!file_exists($configFile)) {
            touch($configFile);
        }

        if (!is_writable($configFile)) {
            throw new \RuntimeException(sprintf('File "%s" is not writable', $configFile), 1438441994);
        }

        $repositories = [
            [
                'type' => 'composer',
                'url' => 'file://' . $repositoryDir . '/packages-TYPO3Extensions-archive.json',
            ],
            [
                'type' => 'composer',
                'url' => 'file://' . $repositoryDir . '/packages-TYPO3Extensions-new.json',
            ],
        ];

        $satis = new SatisJson('typo3/cms-extensions');
        $satis->setHomepage('https://composer.typo3.org');
        $satis->setRepositories($repositories);
        $satis->requireAll();
        $satis->useProviders();

        if (false === file_put_contents($configFile, (string)$satis)) {
            throw new \RuntimeException(sprintf('File "%s" could not be written, reason unknown', $configFile), 1438442238);
        }

        $output->writeln(sprintf('Successfully created "%s" with repository dir "%s"', $configFile, $repositoryDir));

        return 0;
    }
}