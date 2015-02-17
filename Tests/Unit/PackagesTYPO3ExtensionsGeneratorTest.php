<?php

/**
 * Class PackagesTYPO3ExtensionsGeneratorTest
 */
class PackagesTYPO3ExtensionsGeneratorTest extends BaseTestCase {

	/**
	 * @var \PackagesTYPO3ExtensionsGenerator|\PHPUnit_Framework_MockObject_MockObject
	 */
	protected $generator;

	/**
	 * @var \SimpleXMLElement
	 */
	protected $fixture;

	public function setUp() {
		$this->fixture = new \SimpleXMLElement(file_get_contents(__DIR__ . '/Fixture/extensions.xml'));
		$this->generator = $this->getAccessibleMock('PackagesTYPO3ExtensionsGenerator', array('dummy'), array(), '', FALSE);
	}

	public function testFoo() {
		$packages = $this->generator->_call('getPackages', $this->fixture);

		$this->assertTrue(isset($packages['archive']['typo3-ter/gridelements']['3.0.0']['require']));
		$this->assertFalse(isset($packages['archive']['typo3-ter/gridelements']['3.0.0']['require']['typo3-ter/version']));
	}
}
