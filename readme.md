# Zend_Cache_Backend_MongoDb

### Note: do not attempt to use MongoDb as a cache store with a capped collection
see: http://www.mongodb.org/display/DOCS/Capped+Collections#CappedCollections-UsageandRestrictions


```
<?php
$cache = new Zend_Cache_Backend_MongoDb(
    array('database_name' => 'zend_cache',
    'collection' => 'cache')
);
$cache->save($data, $id, array('tag1','acct_id:1234'), time() + 144400);

$data = $cache->load($id);
```

```
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

```
resources.cachemanager.mongodb.frontend.name = Core
resources.cachemanager.mongodb.frontend.customFrontendNaming = false
resources.cachemanager.mongodb.frontend.options.lifetime = 7200
resources.cachemanager.mongodb.frontend.options.automatic_serialization = true
resources.cachemanager.mongodb.backend.name = MongoDb
resources.cachemanager.mongodb.backend.customBackendNaming = false
resources.cachemanager.mongodb.backend.options.mongodb_name = "zend_cache"
resources.cachemanager.mongodb.backend.options.collection = "cache"
resources.cachemanager.mongodb.frontendBackendAutoload = false

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