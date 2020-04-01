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

use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\Composer\Model\SatisJson;

class SatisBuildCommand extends \Symfony\Component\Console\Command\Command
{
    /**
     * @return void
     */
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
        $repositoryDir = $input->getArgument('repository-dir');
        $configFile = $input->getArgument('file');
        $outputDir = $input->getArgument('output-dir');
        $buildAll = (bool) $input->getOption('all');

        // Run extensions:ter:json:create
        $command = $this->getApplication()->find('extensions:ter:json:create');

        $arguments = [
            'command' => 'extensions:ter:json:create',
            'output-dir'    => $repositoryDir,
        ];

        $commandInput = new ArrayInput($arguments);
        $returnCode = $command->run($commandInput, $output);

        // Run satis:json:create
        if ($returnCode === 0) {
            $command = $this->getApplication()->find('satis:json:create');

            $arguments = [
                'command' => 'satis:json:create',
                'file'    => $configFile,
                'repository-dir'  => $repositoryDir,
            ];

            $commandInput = new ArrayInput($arguments);
            $returnCode = $command->run($commandInput, $output);
        }

        // Run satis build
        if ($returnCode === 0) {
            $application = new \Composer\Satis\Console\Application();

            //php -d memory_limit=-1 $BIN_DIR/satis build ./satis.json $WEB_DIR --skip-errors
            $arguments = [
                'command'           => 'build',
                'file'              => $configFile,
                'output-dir'        => $outputDir,
                '--skip-errors'     => true,
            ];

            if (!$buildAll) {
                $arguments += [
                    '--repository-url'  => 'file://' . realpath($repositoryDir) . '/packages-TYPO3Extensions-new.json',
                ];
            }

            $commandInput = new ArrayInput($arguments);
            $returnCode = $application->run($commandInput, $output);
        }

        return $returnCode;
    }
}
