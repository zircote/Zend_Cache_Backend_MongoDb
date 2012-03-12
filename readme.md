# Zend_Cache_Backend_MongoDb

### Note: do not attempt to use MongoDb as a cache store with a capped collection
see: http://www.mongodb.org/display/DOCS/Capped+Collections#CappedCollections-UsageandRestrictions

#### Create the collection and indexes

```javascript
var database_name = "zend_cache";
var collection = "etags";

use database_name;
db.createCollection(collection);
db.getCollection(collection).ensureIndex({"tags" : true});
db.getCollection(collection).ensureIndex({"expire" : true});
db.getCollection(collection).ensureIndex({"mtime" : true});
db.getCollection(collection).getIndexes();
```

Class Instantiation:

```php
<?php
$frontendOptions = array(
    'lifetime' => 7200, 
    'automatic_serialization' => true
);
$backendOptions = array(
    'database_name' => 'zend_cache',
    'collection'    => 'cache'
);
$cache = Zend_Cache::factory('Core', 'MongoDb', $frontendOptions, $backendOptions);
$cache->save($data, $id, array('tag1','acct_id:1234');
$data = $cache->load($id);
```
With example methods:

```php
<?php
$cache = new Zend_Cache_Backend_MongoDb(
    array('database_name' => 'zend_cache',
    'database_urn' => 'mongodb://localhost:27717'
    'collection' => 'cache')
);
$cache->getTags();
$cache->getIds();
$cache->getIdsMatchingAnyTags();
$cache->getIdsMatchingTags(array('tag1','tag2'));
$cache->getIdsNotMatchingTags(array('tag1','tag2'));
$cache->getMetadatas($id);
```

## Zend_Config_Ini

```php
resources.cachemanager.mongodb.frontend.name = Core
resources.cachemanager.mongodb.frontend.customFrontendNaming = false
resources.cachemanager.mongodb.frontend.options.lifetime = 7200
resources.cachemanager.mongodb.frontend.options.automatic_serialization = true
resources.cachemanager.mongodb.backend.name = MongoDb
resources.cachemanager.mongodb.backend.customBackendNaming = false
resources.cachemanager.mongodb.backend.options.database_name = "zend_cache"
resources.cachemanager.mongodb.backend.options.collection = "cache"
resources.cachemanager.mongodb.frontendBackendAutoload = false
 
<?php
if($bootstrap->hasResource('cachemanager')){
    $cache = $bootstrap->getResource('cachemanager')->getCache('mongodb');
    $cache->load($id);
}
```

#### Tests Status:

```
[ zircote ~/Workspace/ZendFramework/tests ] phpunit Zend/Cache/MongoDbBackendTest.php
PHPUnit 3.6.10 by Sebastian Bergmann.

Configuration read from /Users/zircote/Workspace/ZendFramework/tests/phpunit.xml

.........................................

Time: 0 seconds, Memory: 4.25Mb

OK (41 tests, 90 assertions)
```