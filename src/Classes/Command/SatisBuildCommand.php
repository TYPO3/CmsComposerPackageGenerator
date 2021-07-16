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

use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SatisBuildCommand extends \Symfony\Component\Console\Command\Command
{
    protected function configure()
    {
        $this
            ->setName('satis:build')
            ->setDescription('Build Composer Repository. By default changed extension since yesterday are rebuilt.')
            ->setDefinition([
                new InputArgument('output-dir', InputArgument::OPTIONAL, 'Location where to output built files', './Web'),
                new InputArgument('file', InputArgument::OPTIONAL, 'Json file to create and use', './satis.json'),
                new InputArgument('repository-dir', InputArgument::OPTIONAL, 'Location where to output and search for repository files', './Web'),
                new InputOption('all', null, InputOption::VALUE_NONE, 'Build all repositories'),
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
        $timeStart = microtime(true);

        $repositoryDir = $input->getArgument('repository-dir');
        $configFile = $input->getArgument('file');
        $outputDir = $input->getArgument('output-dir');
        $buildAll = (bool)$input->getOption('all');

        // Run extensions:ter:json:create
        $command = $this->getApplication()->find('extensions:ter:json:create');

        $arguments = [
            'command' => 'extensions:ter:json:create',
            'output-dir' => $repositoryDir,
        ];

        $commandInput = new ArrayInput($arguments);
        $output->writeln(sprintf('Running "%s"...', $arguments['command']));
        $returnCode = $command->run($commandInput, $output);

        // Copy Aliases
        if ($returnCode === 0) {
            $output->writeln('Copy aliases.json...');
            copy(realpath($repositoryDir) . '/aliases.json', realpath($outputDir) . '/aliases.json');
        }

        // Run satis:json:create
        if ($returnCode === 0) {
            $command = $this->getApplication()->find('satis:json:create');

            $arguments = [
                'command' => 'satis:json:create',
                'file' => $configFile,
                'repository-dir' => $repositoryDir,
            ];

            $commandInput = new ArrayInput($arguments);
            $output->writeln(sprintf('Running "%s"...', $arguments['command']));
            $returnCode = $command->run($commandInput, $output);
        }

        // Run satis build
        if ($returnCode === 0) {
            $application = new \Composer\Satis\Console\Application();
            $application->setAutoExit(false);

            $arguments = [
                'command' => 'build',
                'file' => $configFile,
                'output-dir' => $outputDir,
                '--skip-errors' => true,
            ];

            if (!$buildAll) {
                $arguments += [
                    '--repository-url' => 'file://' . realpath($repositoryDir) . '/packages-TYPO3Extensions-new.json',
                ];
            }

            $commandInput = new ArrayInput($arguments);
            $output->writeln(sprintf('Running "%s" (%s)...', $arguments['command'], $buildAll ? 'full' : 'new'));
            $returnCode = $application->run($commandInput, $output);
        }

        // Rename Satis index
        if ($returnCode === 0) {
            $output->writeln('Renaming Satis index...');
            rename(realpath($outputDir) . '/index.html', realpath($outputDir) . '/satis.html');
        }

        // Output processing duration
        $timeEnd = microtime(true);
        $time = $timeEnd - $timeStart;

        $output->writeln(sprintf('Finished in %f seconds', $time));

        return $returnCode;
    }
}
