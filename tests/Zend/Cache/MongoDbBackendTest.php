<?php
/**
 * @category   Zend
 * @package    Zend_Cache
 * @subpackage UnitTests
 */

/**
 * Zend_Cache
 */
require_once 'Zend/Cache.php';
require_once 'Zend/Cache/Backend/MongoDb.php';

/**
 * Common tests for backends
 */
require_once 'CommonExtendedBackendTest.php';

/**
 * @category   Zend
 * @package    Zend_Cache
 * @subpackage UnitTests
 * @group      Zend_Cache
 */
class Zend_Cache_MongodbBackendTest extends Zend_Cache_CommonExtendedBackendTest {

    protected $_instance;

    public function __construct($name = null, array $data = array(), $dataName = '')
    {
        parent::__construct('Zend_Cache_Backend_MongoDb', $data, $dataName);
    }

    public function setUp($notag = false)
    {
        $this->_instance = new Zend_Cache_Backend_MongoDb(
            array('database_name' => 'zend_cache',
            'collection' => 'cache')
        );
        parent::setUp($notag);
    }

    public function tearDown()
    {
        parent::tearDown();
        unset($this->_instance);
    }

    public function testConstructorCorrectCall()
    {
        $test = new Zend_Cache_Backend_MongoDb(
            array('database_name' => 'zend_cache',
            'collection' => 'cache')
        );
    }

    public function testConstructorWithNoCollectionSpecified()
    {
        try {
            $test = new Zend_Cache_Backend_MongoDb();
        } catch (Zend_Cache_Exception $e) {
            return;
        }
        $this->fail('Zend_Cache_Exception was expected but not thrown');
    }

    public function testCleanModeAll()
    {
        $this->_instance = new Zend_Cache_Backend_MongoDb(
            array('database_name' => 'zend_cache',
            'collection' => 'cache')
        );
        parent::setUp();
        $this->assertTrue($this->_instance->clean('all'));
        $this->assertFalse($this->_instance->test('bar'));
        $this->assertFalse($this->_instance->test('bar2'));
    }

    public function testRemoveCorrectCallWithVacuum()
    {
        $this->_instance = new Zend_Cache_Backend_MongoDb(
            array('database_name' => 'zend_cache',
            'collection' => 'cache')
        );
        parent::setUp();

        $this->assertTrue($this->_instance->remove('bar'));
        $this->assertFalse($this->_instance->test('bar'));
        $this->assertFalse($this->_instance->remove('barbar'));
        $this->assertFalse($this->_instance->test('barbar'));
    }

    /**
     * @group ZF-11640
     */
    public function testRemoveCorrectCallWithVacuumOnMemoryDb()
    {
        $this->_instance = new Zend_Cache_Backend_MongoDb(
            array('database_name' => 'zend_cache',
            'collection' => 'cache')
        );
        parent::setUp();

        $this->assertGreaterThan(0, $this->_instance->test('bar2'));

        $this->assertTrue($this->_instance->remove('bar'));
        $this->assertFalse($this->_instance->test('bar'));

        $this->assertGreaterThan(0, $this->_instance->test('bar2'));
    }

}



