<?php

/**
 * @file
 * Contains \Drupal\migrate\Tests\MigrateSqlSourceTestCase.
 */

namespace Drupal\migrate\Tests;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\migrate\Source;

/**
 * Provides setup and helper methods for Migrate module source tests.
 */
abstract class MigrateSqlSourceTestCase extends MigrateTestCase {

  /**
   * The tested source plugin.
   *
   * @var \Drupal\migrate_drupal\Plugin\migrate\source\DrupalSqlBase.
   */
  protected $source;

  /**
   * The database contents.
   *
   * Database contents represents a mocked database. It should contain an
   * associative array with the table name as key, and as many nested arrays as
   * the number of mocked rows. Each of those faked rows must be another array
   * with the column name as the key and the value as the cell.
   *
   * @var array
   */
  protected $databaseContents = array();

  /**
   * The plugin class under test.
   *
   * The plugin system is not working during unit testing so the source plugin
   * class needs to be manually specified.
   *
   * @var string
   */
  const PLUGIN_CLASS = '';

  /**
   * The highwater mark at the beginning of the import operation.
   *
   * Once the migration is run, we save a mark of the migrated sources, so the
   * migration can run again and update only new sources or changed sources.
   *
   * @var string
   */
  const ORIGINAL_HIGHWATER = '';

  /**
   * Expected results after the source parsing.
   *
   * @var array
   */
  protected $expectedResults = array();

  /**
   * The source plugin instance under test.
   *
   * @var \Drupal\migrate\Plugin\MigrateSourceInterface
   */
  protected $plugin;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    $module_handler = $this->getMock('Drupal\Core\Extension\ModuleHandlerInterface');

    $migration = $this->getMigration();
    $migration->expects($this->any())
      ->method('getHighwater')
      ->will($this->returnValue(static::ORIGINAL_HIGHWATER));
    // Need the test class, not the original because we need a setDatabase method. This is not pretty :/
    $plugin_class  = preg_replace('/^(Drupal\\\\\w+\\\\)Plugin\\\\migrate(\\\\source(\\\\.+)?\\\\)([^\\\\]+)$/', '\1Tests\2Test\4', static::PLUGIN_CLASS);
    $plugin = new $plugin_class($this->migrationConfiguration['source'], $this->migrationConfiguration['source']['plugin'], array(), $migration);
    $plugin->setDatabase($this->getDatabase($this->databaseContents + array('test_map' => array())));
    $plugin->setModuleHandler($module_handler);
    $plugin->setTranslationManager($this->getStringTranslationStub());
    $migration->expects($this->any())
      ->method('getSourcePlugin')
      ->will($this->returnValue($plugin));
    $migrateExecutable = $this->getMockBuilder('Drupal\migrate\MigrateExecutable')
      ->disableOriginalConstructor()
      ->getMock();
    $this->source = new TestSource($migration, $migrateExecutable);

    $cache = $this->getMock('Drupal\Core\Cache\CacheBackendInterface');
    $this->source->setCache($cache);
  }

  /**
   * Test the source returns the same rows as expected.
   */
  public function testRetrieval() {
    $this->queryResultTest($this->source, $this->expectedResults);
  }

  /**
   * @param \Drupal\migrate\Row $row
   * @param string $key
   * @return mixed
   */
  protected function getValue($row, $key) {
    return $row->getSourceProperty($key);
  }

  /**
   * {@inheritdoc}
   */
  public static function getInfo() {
    return array(
      'name' => 'SQL source test',
      'description' => 'Tests for SQL source plugin.',
      'group' => 'Migrate',
    );
  }

}

class TestSource extends Source {
  public function setCache(CacheBackendInterface $cache) {
    $this->cache = $cache;
  }
}
