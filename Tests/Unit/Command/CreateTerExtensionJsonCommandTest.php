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
namespace TYPO3\Composer\Tests\Unit\Command;

/**
 * Class CreateTerExtensionJsonCommandTest
 * @package TYPO3\Composer\Test\Unit\Command
 */
class CreateTerExtensionJsonCommandTest extends \TYPO3\Composer\Tests\Unit\BaseTestCase {

	/**
	 * @var \TYPO3\Composer\Command\CreateTerExtensionJsonCommand|\PHPUnit_Framework_MockObject_MockObject
	 */
	protected $command;

	/**
	 * @var \SimpleXMLElement
	 */
	protected $fixture;

	public function setUp() {
		$this->fixture = new \SimpleXMLElement(file_get_contents(__DIR__ . '/../Fixture/extensions.xml'));
		$this->command = $this->getAccessibleMock('TYPO3\\Composer\\Command\\CreateTerExtensionJsonCommand', array('dummy'), array(), '', FALSE);
	}

	public function testFoo() {
		$packages = $this->command->_call('getPackages', $this->fixture);

		$this->assertTrue(isset($packages['archive']['typo3-ter/gridelements']['3.0.0']['require']));
		$this->assertFalse(isset($packages['archive']['typo3-ter/gridelements']['3.0.0']['require']['typo3-ter/version']));
	}

	/**
	 * @test
	 */
	public function reviewStatesArePopulatedAsSecureKeyInExtraSection() {
		$packages = $this->command->_call('getPackages', $this->fixture);

		$this->assertTrue(isset($packages['archive']['typo3-ter/gridelements']['2.0.0']['extra']['typo3/ter']['review-state']));
		$this->assertSame('insecure', $packages['archive']['typo3-ter/gridelements']['2.0.0']['extra']['typo3/ter']['review-state']);

		$this->assertFalse(isset($packages['archive']['typo3-ter/gridelements']['2.0.0']['extra']['typo3/ter']['extra']['review-state']));
	}
}
