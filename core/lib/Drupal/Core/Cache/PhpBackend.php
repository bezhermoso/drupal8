<?php

/**
 * @file
 * Contains \Drupal\Core\Cache\PhpBackend.
 */

namespace Drupal\Core\Cache;

use Drupal\Core\PhpStorage\PhpStorageFactory;
use Drupal\Component\Utility\Variable;

/**
 * Defines a PHP cache implementation.
 *
 * Stores cache items in a PHP file using a storage that implements
 * Drupal\Component\PhpStorage\PhpStorageInterface.
 *
 * This is fast because of PHP's opcode caching mechanism. Once a file's
 * content is stored in PHP's opcode cache, including it doesn't require
 * reading the contents from a filesystem. Instead, PHP will use the already
 * compiled opcodes stored in memory.
 *
 * @ingroup cache
 */
class PhpBackend implements CacheBackendInterface {

  /**
   * @var string
   */
  protected $bin;

  /**
   * Array to store cache objects.
   */
  protected $cache = array();

  /**
   * Constructs a PhpBackend object.
   *
   * @param string $bin
   *   The cache bin for which the object is created.
   */
  public function __construct($bin) {
    $this->bin = 'cache_' . $bin;
  }

  /**
   * {@inheritdoc}
   */
  public function get($cid, $allow_invalid = FALSE) {
    if ($file = $this->storage()->getFullPath($cid)) {
      $cache = @include $file;
    }
    if (isset($cache)) {
      return $this->prepareItem($cache, $allow_invalid);
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function setMultiple(array $items) {
    foreach ($items as $cid => $item) {
      $this->set($cid, $item['data'], isset($item['expire']) ? $item['expire'] : CacheBackendInterface::CACHE_PERMANENT, isset($item['tags']) ? $item['tags'] : array());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getMultiple(&$cids, $allow_invalid = FALSE) {
    $ret = array();

    foreach ($cids as $cid) {
      if ($item = $this->get($cid, $allow_invalid)) {
        $ret[$item->cid] = $item;
      }
    }

    $cids = array_diff($cids, array_keys($ret));

    return $ret;
  }

  /**
   * Prepares a cached item.
   *
   * Checks that items are either permanent or did not expire, and returns data
   * as appropriate.
   *
   * @param object $cache
   *   An item loaded from cache_get() or cache_get_multiple().
   * @param bool $allow_invalid
   *   If FALSE, the method returns FALSE if the cache item is not valid.
   *
   * @return mixed
   *   The item with data as appropriate or FALSE if there is no
   *   valid item to load.
   */
  protected function prepareItem($cache, $allow_invalid) {
    if (!isset($cache->data)) {
      return FALSE;
    }

    // Check expire time.
    $cache->valid = $cache->expire == Cache::PERMANENT || $cache->expire >= REQUEST_TIME;

    if (!$allow_invalid && !$cache->valid) {
      return FALSE;
    }

    return $cache;
  }

  /**
   * {@inheritdoc}
   */
  public function set($cid, $data, $expire = Cache::PERMANENT, array $tags = array()) {
    $item = (object) array(
      'cid' => $cid,
      'data' => $data,
      'created' => REQUEST_TIME,
      'expire' => $expire,
    );
    $item->tags = $this->flattenTags($tags);
    $this->writeItem($cid, $item);
  }

  /**
   * {@inheritdoc}
   */
  public function delete($cid) {
    $this->storage()->delete($cid);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteMultiple(array $cids) {
    foreach ($cids as $cid) {
      $this->delete($cid);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function deleteTags(array $tags) {
    $flat_tags = $this->flattenTags($tags);
    foreach ($this->storage()->listAll() as $cid) {
      $item = $this->get($cid);
      if (is_object($item) && array_intersect($flat_tags, $item->tags)) {
        $this->delete($cid);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function deleteAll() {
    $this->storage()->deleteAll();
  }

  /**
   * {@inheritdoc}
   */
  public function invalidate($cid) {
    if ($item = $this->get($cid)) {
      $item->expire = REQUEST_TIME - 1;
      $this->writeItem($cid, $item);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function invalidateMultiple(array $cids) {
    foreach ($cids as $cid) {
      $this->invalidate($cid);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function invalidateTags(array $tags) {
    $flat_tags = $this->flattenTags($tags);
    foreach ($this->storage()->listAll() as $cid) {
      $item = $this->get($cid);
      if ($item && array_intersect($flat_tags, $item->tags)) {
        $this->invalidate($cid);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function invalidateAll() {
    $this->invalidateMultiple($this->storage()->listAll());
  }

  /**
   * 'Flattens' a tags array into an array of strings.
   *
   * @param array $tags
   *   Associative array of tags to flatten.
   *
   * @return array
   *   An indexed array of strings.
   */
  protected function flattenTags(array $tags) {
    if (isset($tags[0])) {
      return $tags;
    }

    $flat_tags = array();
    foreach ($tags as $namespace => $values) {
      if (is_array($values)) {
        foreach ($values as $value) {
          $flat_tags[] = "$namespace:$value";
        }
      }
      else {
        $flat_tags[] = "$namespace:$values";
      }
    }
    return $flat_tags;
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    $names = $this->storage()->listAll();
    return empty($names);
  }

  /**
   * {@inheritdoc}
   */
  public function garbageCollection() {
  }

  /**
   * {@inheritdoc}
   */
  public function removeBin() {
    $this->cache = array();
    $this->storage()->delete($this->bin);
  }

  /**
   * Writes a cache item to PhpStorage.
   *
   * @param string $cid
   *   The cache ID of the data to store.
   * @param \stdClass $item
   *   The cache item to store.
   */
  protected function writeItem($cid, \stdClass $item) {
    $data = str_replace('\\', '\\\\', serialize($item));
    $content = "<?php return unserialize(<<<EOF
$data
EOF
);";
    $this->storage()->save($cid, $content);
  }

  /**
   * Gets the PHP code storage object to use.
   *
   * @return \Drupal\Core\PhpStorage\PhpStorageInterface
   */
  protected function storage() {
    if (!isset($this->storage)) {
      $this->storage = PhpStorageFactory::get($this->bin);
    }
    return $this->storage;
  }

}
