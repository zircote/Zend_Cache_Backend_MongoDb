<?php
/**
 * @category   Zend
 * @package    Zend_Cache
 * @subpackage Zend_Cache_Backend
 */


/**
 * @see Zend_Cache_Backend_Interface
 */
require_once 'Zend/Cache/Backend/ExtendedInterface.php';

/**
 * @see Zend_Cache_Backend
 */
require_once 'Zend/Cache/Backend.php';

/**
 * Notes:
 * Do not use a capped collection there are limitations that make it untennable
 * as a cache store.
 * @link http://www.mongodb.org/display/DOCS/Capped+Collections#CappedCollections-UsageandRestrictions
 *
 * @package    Zend_Cache
 * @subpackage Zend_Cache_Backend
 */
class Zend_Cache_Backend_MongoDb extends Zend_Cache_Backend implements Zend_Cache_Backend_ExtendedInterface
{
    /**
     * @todo complete this parameters documentation
     * Available options
     *
     * ====> (array) database_name :
     *       Database name possessing the collection.
     * ====> (string) database_urn :
     *       URN of the MongoDb connections
     * ====> (string) collection :
     *     - The Collection used to store the cache items
     *
     *
     * @var array Available options
     */
    protected $_options = array(
        'database_name' => null,
        'database_urn' => 'mongodb://localhost:27017',
        'options' => array(),
        'collection' => null
    );
    /**
     *
     * @var MongoDb|null
     */
    protected $_database = null;

    /**
     * MongoCollection
     *
     * @var MongoCollection|null $_collection
     */
    private $_collection = null;

    /**
     * Constructor
     *
     * @param  array $options Associative array of options
     * @throws Zend_cache_Exception
     * @return void
     */
    public function __construct(array $options = array())
    {
        parent::__construct($options);
        if ($this->_options['database_name'] === null) {
            Zend_Cache::throwException('[database_name] option has to be set');
        }
        if ($this->_options['collection'] === null) {
            Zend_Cache::throwException('[collection] option has to be set');
        }
        if (!extension_loaded('Mongo')) {
            Zend_Cache::throwException("Cannot use Mongo storage because the ".
            "'Mongo' extension is not loaded in the current PHP environment");
        }
        $this->_getCollection();
    }
    /**
     * Returns the current MongoDb instance if set otherwise null.
     *
     * @return MongoDb|null
     */
    public function getDatabase()
    {
        return $this->_database;
    }
    /**
     * Sets the MongoDb instance for cache use.
     *
     * @param MongoDb $database
     * @return Zend_Cache_Backend_MongoDb
     */
    public function setDatabase(MongoDb $database)
    {
        $this->_database = $database;
        return $this;
    }
    /**
     * Returns the current MongoCollection instance if set otherwise null.
     *
     * @return MongoCollection|null
     */
    public function getCollection()
    {
        return $this->_collection;
    }
    /**
     * Sets the MongoCollection instance for cache use.
     *
     * @param MongoCollection $collection
     * @return Zend_Cache_Backend_MongoDb
     */
    public function setCollection(MongoCollection $collection)
    {
        $this->_collection = $collection;
        return $this;
    }
    /**
     * (non-PHPdoc)
     * @see Zend_Cache_Backend_ExtendedInterface::getFillingPercentage()
     *
     * Return the filling percentage of the backend storage
     *
     * @todo will need to revisit the getFillingPercentage method later...
     *
     * @return int integer between 0 and 100
     */
    public function getFillingPercentage()
    {
        $result = $this->_database->execute('db.stats()');
        $total = @$result['retval']['storageSize'] ?: 0;
        $free = $total - @$result['retval']['dataSize'] ?: 0;
        if ($total == 0) {
            Zend_Cache::throwException('can\'t get disk_total_space');
        } else {
            if ($free >= $total) {
                return 100;
            }
            return ((int) (100. * ($total - $free) / $total));
        }
    }
    /**
     * Test if a cache is available for the given id and (if yes) return it (false else)
     *
     * @param  string  $id                     Cache id
     * @param  boolean $doNotTestCacheValidity If set to true, the cache validity won't be tested
     * @return string|false Cached datas
     */
    public function load($id, $doNotTestCacheValidity = false)
    {
        $query = array('_id' => $id);
        if (!$doNotTestCacheValidity) {
            $query['$or'] = array(
                array('expire' => 0),
                array( 'expire' => array('$gt' => time()))
            );
        }
        $result = (array)$this->_getCollection()
            ->findOne($query);
        if (isset($result['content'])) {
            return $result['content'];
        }
        return false;
    }

    /**
     * Test if a cache is available or not (for the given id)
     *
     * @param string $id Cache id
     * @return mixed|false (a cache is not available) or "last modified"
     * timestamp (int) of the available cache record
     */
    public function test($id)
    {
        $query = array(
            '_id' => $id,
            '$or' => array(
                array('expire' => 0),
                array( 'expire' => array('$gt' => time()))
            )
        );
        $result = $this->_getCollection()
            ->findOne($query);
        if ($result) {
            return ((int) $result['mtime']);
        }
        return false;
    }

    /**
     * Save some string datas into a cache record
     *
     * Note : $data is always "string" (serialization is done by the
     * core not by the backend)
     *
     * @param  string $data             Datas to cache
     * @param  string $id               Cache id
     * @param  array  $tags             Array of strings, the cache record will be tagged by each string entry
     * @param  int    $specificLifetime If != false, set a specific lifetime for this cache record (null => infinite lifetime)
     * @throws Zend_Cache_Exception
     * @return boolean True if no problem
     */
    public function save($data, $id, $tags = array(), $specificLifetime = false)
    {
        $lifetime = $this->getLifetime($specificLifetime);
        $mktime = time();
        if ($lifetime === null) {
            $expire = 0;
        } else {
            $expire = $mktime + $lifetime;
        }
        $item = array(
            '_id' => $id,
            'mtime' => $mktime,
            'expire' => $expire,
            'tags' => $tags,
            'content' => $data
        );
        $res = $this->_getCollection()->save($item, array('safe' => true));
        if (!(bool) $res['ok']) {
            $this->_log("Zend_Cache_Backend_MongoDb::save() : impossible to store the cache id=$id");
            return false;
        }
        return (bool) $res['ok'];
    }

    /**
     * Remove a cache record
     *
     * @param  string $id Cache id
     * @return boolean True if no problem
     */
    public function remove($id)
    {
        if(!$this->_getCollection()->findOne(array('_id' => $id))){
            return false;
        }
        $result = $this->_getCollection()->remove(array('_id' => $id));
        return $result;
    }

    /**
     * Clean some cache records
     *
     * Available modes are :
     * Zend_Cache::CLEANING_MODE_ALL (default)    => remove all cache entries ($tags is not used)
     * Zend_Cache::CLEANING_MODE_OLD              => remove too old cache entries ($tags is not used)
     * Zend_Cache::CLEANING_MODE_MATCHING_TAG     => remove cache entries matching all given tags
     *                                               ($tags can be an array of strings or a single string)
     * Zend_Cache::CLEANING_MODE_NOT_MATCHING_TAG => remove cache entries not {matching one of the given tags}
     *                                               ($tags can be an array of strings or a single string)
     * Zend_Cache::CLEANING_MODE_MATCHING_ANY_TAG => remove cache entries matching any given tags
     *                                               ($tags can be an array of strings or a single string)
     *
     * @param  string $mode Clean mode
     * @param  array  $tags Array of tags
     * @return boolean True if no problem
     */
    public function clean($mode = Zend_Cache::CLEANING_MODE_ALL, $tags = array())
    {
        $return = $this->_clean($mode, $tags);
        return $return;
    }

    /**
     * Return an array of stored cache ids
     *
     * @return array array of stored cache ids (string)
     */
    public function getIds()
    {
        $query = array(
            '$or' => array(
                array('expire' => 0),
                array('expire' => array('$gt' => time()))
            )
        );
        $result = $this->_getCollection()->find($query, array('_id'));
        foreach ($result as $id) {
            $ids[] = $id['_id'];
        }
        return $ids;
    }

    /**
     * Return an array of stored tags
     *
     * @return array array of stored tags (string)
     */
    public function getTags()
    {
        $keys = array();
        $map = new MongoCode(
            'function() {
                for ( var key in this.tags) {
                    emit(this.tags[key], null);
                }
            }'
        );
        $reduce = new MongoCode('function(key, tmp) {
            return null;
            }');
        $result = $this->_database->command(array(
            "mapreduce" => $this->_options['collection'],
            "map" => $map,
            "reduce" => $reduce,
            "out" => array("inline" => true))
        );
        foreach ($result['results'] as $item) {
            $keys[] = $item['_id'];
        }
        return $keys;
    }

    /**
     * Return an array of stored cache ids which match given tags
     *
     * In case of multiple tags, a logical AND is made between tags
     *
     * @param array $tags array of tags
     * @return array array of matching cache ids (string)
     */
    public function getIdsMatchingTags($tags = array())
    {
        $ids = array();
        if(count($tags) > 1){
            foreach ($tags as $tag) {
                $query['$and'][] = array('tags' => $tag);
            }
        } else {
            $query = array(
                'tags' => $tags[0]
            );
        }
        $result = $this->_getCollection()->find($query, array('_id' => true));
        foreach ($result as $id) {
            $ids[] = $id['_id'];
        }
        return $ids;
    }

    /**
     * Return an array of stored cache ids which don't match given tags
     *
     * In case of multiple tags, a logical OR is made between tags
     *
     * @param array $tags array of tags
     * @return array array of not matching cache ids (string)
     */
    public function getIdsNotMatchingTags($tags = array())
    {
        $ids = array();
        if(count($tags) > 1){
            foreach ($tags as $tag) {
                $query['$nor'][] = array('tags' => $tag);
            }
        } else {
            $query = array(
                'tags' => array('$ne' => $tags[0])
            );
        }
        $ids = $this->_getCollection()->find($query, array('_id' => true));
        $result = array();
        foreach ($ids as $id) {
            $result[] = $id['_id'];
        }
        return $result;
    }

    /**
     * Return an array of stored cache ids which match any given tags
     *
     * In case of multiple tags, a logical AND is made between tags
     *
     * @param array $tags array of tags
     * @return array array of any matching cache ids (string)
     */
    public function getIdsMatchingAnyTags($tags = array())
    {
        $ids = array();
        if(count($tags) > 1){
            foreach ($tags as $tag) {
                $query['$or'][] = array('tags' => $tag);
            }
        } else {
            $query = array(
                'tags' =>$tags[0]
            );
        }
        $ids = $this->_getCollection()->find($query, array('_id' => true));
        $result = array();
        foreach ($ids as $id) {
            $result[] = $id['_id'];
        }
        return $result;
    }

    /**
     * Return an array of metadatas for the given cache id
     *
     * The array must include these keys :
     * - expire : the expire timestamp
     * - tags : a string array of tags
     * - mtime : timestamp of last modification time
     *
     * @param string $id cache id
     * @return array array of metadatas (false if the cache id is not found)
     */
    public function getMetadatas($id)
    {
        $result = $this->_getCollection()->findOne(
            array('_id' => $id),
            array('tags' => true,'mtime' => true,'expire' => true)
        );
        return (array) $result;
    }

    /**
     * Give (if possible) an extra lifetime to the given cache id
     *
     * @param string $id cache id
     * @param int $extraLifetime
     * @return boolean true if ok
     */
    public function touch($id, $extraLifetime)
    {
        $data = $this->_getCollection()->findOne(array('_id' => $id));
        $data = array(
            '_id' => $id,
            'mtime' => time(),
            'expire' => $data['expire'] + $extraLifetime,
            'tags' => $data['tags'],
            'content' => $data['content']
        );
        $result = $this->_getCollection()->save($data);
        if ($result) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Return an associative array of capabilities (booleans) of the backend
     *
     * The array must include these keys :
     * - automatic_cleaning (is automating cleaning necessary)
     * - tags (are tags supported)
     * - expired_read (is it possible to read expired cache records
     *                 (for doNotTestCacheValidity option for example))
     * - priority does the backend deal with priority when saving
     * - infinite_lifetime (is infinite lifetime can work with this backend)
     * - get_list (is it possible to get the list of cache ids and the complete list of tags)
     *
     * @return array associative of with capabilities
     */
    public function getCapabilities()
    {
        return array(
            'automatic_cleaning' => true,
            'tags' => true,
            'expired_read' => true,
            'priority' => false,
            'infinite_lifetime' => true,
            'get_list' => true
        );
    }

    /**
     * PUBLIC METHOD FOR UNIT TESTING ONLY !
     *
     * Force a cache record to expire
     *
     * @param string $id Cache id
     */
    public function ___expire($id)
    {
        $data = $this->_getCollection()->findOne(array('_id' => $id));
        $data['mtime'] = time();
        $data['expire'] = time() - 1;
        $this->_getCollection()->save($data);
    }

    /**
     * Return the connection resource
     *
     * If we are not connected, the connection is made
     *
     * @throws Zend_Cache_Exception
     * @return MongoCollection
     */
    private function _getCollection()
    {
        if(!$this->_database instanceof MongoDb){
            $this->_database = new Mongo($this->_options['database_urn']);
            $this->_database = $this->_database->selectDb($this->_options['database_name']);
        }
        if ($this->_collection instanceof MongoCollection) {
            return $this->_collection;
        } else {
            if(! $this->_database instanceof MongoDb){
                Zend_Cache::throwException(
                    "Failed to create collection: " .
                    $this->_options['collection'] .
                    " no valid MongoDb defined"
                );
            }
            $this->_collection = $this->_database
                ->createCollection($this->_options['collection']);
            if (!$this->_collection instanceof MongoCollection) {
                Zend_Cache::throwException(
                    "Failed to create collection:" .
                    $this->_options['collection'] . " MongoCollection"
                );
            }
            return $this->_collection;
        }
    }

    /**
     * Register a cache id with the given tag
     *
     * @param  string $id  Cache id
     * @param  string $tag Tag
     * @return boolean True if no problem
     */
    private function _registerTag($id, $tag) {
        $res = $this->_getCollection()
            ->update(
                array('_id' => $id, '$push' => array('tags' => $tag))
            );
        if (!$res) {
            $this->_log(
                "Zend_Cache_Backend_MongoDb::_registerTag() : ".
                "impossible to register tag=$tag on id=$id"
            );
            return false;
        }
        return true;
    }

    /**
     * Clean some cache records
     *
     * Available modes are :
     * Zend_Cache::CLEANING_MODE_ALL (default)    => remove all cache entries ($tags is not used)
     * Zend_Cache::CLEANING_MODE_OLD              => remove too old cache entries ($tags is not used)
     * Zend_Cache::CLEANING_MODE_MATCHING_TAG     => remove cache entries matching all given tags
     *                                               ($tags can be an array of strings or a single string)
     * Zend_Cache::CLEANING_MODE_NOT_MATCHING_TAG => remove cache entries not {matching one of the given tags}
     *                                               ($tags can be an array of strings or a single string)
     * Zend_Cache::CLEANING_MODE_MATCHING_ANY_TAG => remove cache entries matching any given tags
     *                                               ($tags can be an array of strings or a single string)
     *
     * @param  string $mode Clean mode
     * @param  array  $tags Array of tags
     * @return boolean True if no problem
     */
    private function _clean($mode = Zend_Cache::CLEANING_MODE_ALL, $tags = array())
    {
        $query = array('$or' => array());
        switch ($mode) {
            case Zend_Cache::CLEANING_MODE_ALL:
                return $this->_getCollection()->remove();
                break;
            case Zend_Cache::CLEANING_MODE_OLD:
                $query = array(
                    'expire' => array('$lt' => time())
                );
                return $this->_getCollection()->remove($query);
                break;
            case Zend_Cache::CLEANING_MODE_MATCHING_TAG:
                foreach ($this->getIdsMatchingTags($tags) as $id) {
                    $query['$or'][]['_id'] = $id;
                }
                return $this->_getCollection()->remove($query);
                break;
            case Zend_Cache::CLEANING_MODE_NOT_MATCHING_TAG:
                foreach ($this->getIdsNotMatchingTags($tags) as $id) {
                    $query['$or'][]['_id'] = $id;
                }
                return $this->_getCollection()->remove($query);
                break;
            case Zend_Cache::CLEANING_MODE_MATCHING_ANY_TAG:
                foreach ($this->getIdsMatchingAnyTags($tags) as $id) {
                    $query['$or'][]['_id'] = $id;
                }
                return $this->_getCollection()->remove($query);
                break;
            default:
                break;
        }
        return false;
    }

}
