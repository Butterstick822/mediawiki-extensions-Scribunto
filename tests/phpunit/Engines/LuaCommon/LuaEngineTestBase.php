<?php

namespace MediaWiki\Extension\Scribunto\Tests\Engines\LuaCommon;

use MediaWiki\Extension\Scribunto\Engines\LuaCommon\LuaEngine;
use MediaWiki\Extension\Scribunto\Engines\LuaCommon\LuaError;
use MediaWiki\Title\Title;
use MediaWikiLangTestCase;
use PHPUnit\Framework\TestSuite;

/**
 * This is the subclass for Lua library tests. It will automatically run all
 * tests against LuaSandbox and LuaStandalone.
 *
 * Most of the time, you'll only need to override the following:
 * - $moduleName: Name of the module being tested
 * - getTestModules(): Add a mapping from $moduleName to the file containing
 *   the code.
 *
 * Also, your test must be in the Database group.
 */
abstract class LuaEngineTestBase extends MediaWikiLangTestCase {
	use LuaEngineTestHelper;

	/** @var string|null */
	private static $staticEngineName = null;
	/** @var string|null */
	private $engineName = null;
	/** @var LuaEngine|null */
	private $engine = null;
	/** @var LuaDataProvider|null */
	private $luaDataProvider = null;

	/**
	 * Name to display instead of the default
	 * @var string
	 */
	protected $luaTestName = null;

	/**
	 * Name of the module being tested
	 * @var string
	 */
	protected static $moduleName = null;

	/**
	 * Class to use for the data provider
	 * @var string
	 */
	protected static $dataProviderClass = LuaDataProvider::class;

	/**
	 * Tests to skip. Associative array mapping test name to skip reason.
	 * @var array
	 */
	protected $skipTests = [];

	/**
	 * @param string|null $name
	 * @param array $data
	 * @param string $dataName
	 * @param string|null $engineName Engine to test with
	 */
	public function __construct(
		$name = null, array $data = [], $dataName = '', $engineName = null
	) {
		if ( $engineName === null ) {
			$engineName = self::$staticEngineName;
		}
		$this->engineName = $engineName;
		parent::__construct( $name, $data, $dataName );
	}

	/**
	 * Create a PHPUnit test suite to run the test against all engines
	 * @param string $className Test class name
	 * @return TestSuite
	 */
	public static function suite( $className ) {
		return self::makeSuite( $className );
	}

	protected function tearDown(): void {
		if ( $this->luaDataProvider ) {
			$this->luaDataProvider->destroy();
			$this->luaDataProvider = null;
		}
		if ( $this->engine ) {
			$this->engine->destroy();
			$this->engine = null;
		}
		parent::tearDown();
	}

	/**
	 * Get the title used for unit tests
	 *
	 * @return Title
	 */
	protected function getTestTitle() {
		// XXX This should use a dedicated test page, not the main page
		$t = Title::newMainPage();
		// Force content model to avoid DB queries
		$t->setContentModel( CONTENT_MODEL_WIKITEXT );
		return $t;
	}

	public function toString(): string {
		// When running tests written in Lua, return a nicer representation in
		// the failure message.
		if ( $this->luaTestName ) {
			return $this->engineName . ': ' . $this->luaTestName;
		}
		return $this->engineName . ': ' . parent::toString();
	}

	/**
	 * Modules that should exist
	 * @return string[] Mapping module names to files
	 */
	protected function getTestModules() {
		return [
			'TestFramework' => __DIR__ . '/TestFramework.lua',
		];
	}

	public function provideLuaData() {
		if ( !$this->luaDataProvider ) {
			$class = static::$dataProviderClass;
			$this->luaDataProvider = new $class ( $this->getEngine(), static::$moduleName );
		}
		return $this->luaDataProvider;
	}

	/**
	 * @dataProvider provideLuaData
	 * @param string $key
	 * @param string $testName
	 * @param mixed $expected
	 */
	public function testLua( $key, $testName, $expected ) {
		$this->luaTestName = static::$moduleName . "[$key]: $testName";
		if ( isset( $this->skipTests[$testName] ) ) {
			$this->markTestSkipped( $this->skipTests[$testName] );
		} else {
			try {
				$actual = $this->provideLuaData()->run( $key );
			} catch ( LuaError $ex ) {
				if ( substr( $ex->getLuaMessage(), 0, 6 ) === 'SKIP: ' ) {
					$this->markTestSkipped( substr( $ex->getLuaMessage(), 6 ) );
				} else {
					throw $ex;
				}
			}
			$this->assertSame( $expected, $actual );
		}
		$this->luaTestName = null;
	}
}

class_alias( LuaEngineTestBase::class, 'Scribunto_LuaEngineTestBase' );
