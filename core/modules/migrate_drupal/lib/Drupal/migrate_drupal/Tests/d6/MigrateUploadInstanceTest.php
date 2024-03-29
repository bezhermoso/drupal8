<?php

/**
 * @file
 * Contains \Drupal\migrate_drupal\Tests\d6\MigrateUploadInstanceTest.
 */

namespace Drupal\migrate_drupal\Tests\d6;

use Drupal\migrate\MigrateExecutable;
use Drupal\migrate_drupal\Tests\MigrateDrupalTestBase;

/**
 * Tests the Drupal 6 upload settings to Drupal 8 field instance migration.
 */
class MigrateUploadInstanceTest extends MigrateDrupalTestBase {

  /**
   * The modules to be enabled during the test.
   *
   * @var array
   */
  static $modules = array('file', 'node');

  /**
   * {@inheritdoc}
   */
  public static function getInfo() {
    return array(
      'name'  => 'Migrate upload field instance.',
      'description'  => 'Upload field instance migration',
      'group' => 'Migrate Drupal',
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    // Add some node mappings to get past checkRequirements().
    $id_mappings = array(
      'd6_upload_field' => array(
        array(array(1), array('node', 'upload')),
      ),
    );
    $this->prepareIdMappings($id_mappings);

    foreach (array('page', 'story') as $type) {
      entity_create('node_type', array('type' => $type))->save();
    }
    entity_create('field_config', array(
      'entity_type' => 'node',
      'name' => 'upload',
      'type' => 'file',
      'translatable' => '0',
    ))->save();

    $migration = entity_load('migration', 'd6_upload_field_instance');
    $dumps = array(
      $this->getDumpDirectory() . '/Drupal6UploadInstance.php',
    );
    $this->prepare($migration, $dumps);
    $executable = new MigrateExecutable($migration, $this);
    $executable->import();
  }

  /**
   * Tests the Drupal 6 upload settings to Drupal 8 field instance migration.
   */
  public function testUploadFieldInstance() {
    $field = entity_load('field_instance_config', 'node.page.upload');
    $settings = $field->getSettings();
    $this->assertEqual($field->id(), 'node.page.upload');
    $this->assertEqual($settings['file_extensions'], 'jpg jpeg gif png txt doc xls pdf ppt pps odt ods odp');
    $this->assertEqual($settings['max_filesize'], '1MB');
    $this->assertEqual($settings['description_field'], TRUE);

    $field = entity_load('field_instance_config', 'node.story.upload');
    $this->assertEqual($field->id(), 'node.story.upload');

    // Shouldn't exist.
    $field = entity_load('field_instance_config', 'node.article.upload');
    $this->assertTrue(is_null($field));

    $this->assertEqual(array('node', 'page', 'upload'), entity_load('migration', 'd6_upload_field_instance')->getIdMap()->lookupDestinationID(array('page')));
  }

}
