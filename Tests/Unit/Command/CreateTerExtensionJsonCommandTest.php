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

namespace TYPO3\Composer\Tests\Unit\Command;

/**
 * Class CreateTerExtensionJsonCommandTest
 */
class CreateTerExtensionJsonCommandTest extends \TYPO3\Composer\Tests\Unit\BaseTestCase
{
    /**
     * @var \TYPO3\Composer\Command\CreateTerExtensionJsonCommand|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $command;

    /**
     * @var \SimpleXMLElement
     */
    protected $fixture;

    public function setUp()
    {
        $this->fixture = new \SimpleXMLElement(file_get_contents(__DIR__ . '/../Fixture/extensions.xml'));
        $this->command = $this->getAccessibleMock('TYPO3\\Composer\\Command\\CreateTerExtensionJsonCommand', ['dummy'], [], '', false);
    }

    public function testFoo()
    {
        $packages = $this->command->_call('getPackages', $this->fixture);

        self::assertTrue(isset($packages['archive']['typo3-ter/gridelements']['3.0.0']['require']));
        self::assertFalse(isset($packages['archive']['typo3-ter/gridelements']['3.0.0']['require']['typo3-ter/version']));
    }

    /**
     * @test
     */
    public function reviewStatesArePopulatedAsSecureKeyInExtraSection()
    {
        $packages = $this->command->_call('getPackages', $this->fixture);

        self::assertTrue(isset($packages['archive']['typo3-ter/gridelements']['2.0.0']['extra']['typo3/ter']['review-state']));
        self::assertSame('insecure', $packages['archive']['typo3-ter/gridelements']['2.0.0']['extra']['typo3/ter']['review-state']);

        self::assertFalse(isset($packages['archive']['typo3-ter/gridelements']['2.0.0']['extra']['typo3/ter']['extra']['review-state']));
    }
}
