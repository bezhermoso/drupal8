<?php

/**
 * @file
 * Definition of Drupal\system\Tests\Theme\TableTest.
 */

namespace Drupal\system\Tests\Theme;

use Drupal\simpletest\DrupalUnitTestBase;

/**
 * Unit tests for theme_table().
 */
class TableTest extends DrupalUnitTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('system');

  public static function getInfo() {
    return array(
      'name' => 'Theme Table',
      'description' => 'Tests built-in table theme functions.',
      'group' => 'Theme',
    );
  }

  /**
   * Tableheader.js provides 'sticky' table headers, and is included by default.
   */
  function testThemeTableStickyHeaders() {
    $header = array('one', 'two', 'three');
    $rows = array(array(1,2,3), array(4,5,6), array(7,8,9));
    $table = array(
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#sticky' => TRUE,
    );
    $this->content = drupal_render($table);
    $js = _drupal_add_js();
    $this->assertTrue(isset($js['core/misc/tableheader.js']), 'tableheader.js was included when $sticky = TRUE.');
    $this->assertRaw('sticky-enabled',  'Table has a class of sticky-enabled when $sticky = TRUE.');
    drupal_static_reset('_drupal_add_js');
  }

  /**
   * If $sticky is FALSE, no tableheader.js should be included.
   */
  function testThemeTableNoStickyHeaders() {
    $header = array('one', 'two', 'three');
    $rows = array(array(1,2,3), array(4,5,6), array(7,8,9));
    $attributes = array();
    $caption = NULL;
    $colgroups = array();
    $table = array(
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#attributes' => $attributes,
      '#caption' => $caption,
      '#colgroups' => $colgroups,
      '#sticky' => FALSE,
    );
    $this->content = drupal_render($table);
    $js = _drupal_add_js();
    $this->assertFalse(isset($js['core/misc/tableheader.js']), 'tableheader.js was not included because $sticky = FALSE.');
    $this->assertNoRaw('sticky-enabled',  'Table does not have a class of sticky-enabled because $sticky = FALSE.');
    drupal_static_reset('_drupal_add_js');
  }

  /**
   * Tests that the table header is printed correctly even if there are no rows,
   * and that the empty text is displayed correctly.
   */
  function testThemeTableWithEmptyMessage() {
    $header = array(
      t('Header 1'),
      array(
        'data' => t('Header 2'),
        'colspan' => 2,
      ),
    );
    $table = array(
      '#type' => 'table',
      '#header' => $header,
      '#rows' => array(),
      '#empty' => t('No strings available.'),
    );
    $this->content = drupal_render($table);
    $this->assertRaw('<tr class="odd"><td colspan="3" class="empty message">No strings available.</td>', 'Correct colspan was set on empty message.');
    $this->assertRaw('<thead><tr><th>Header 1</th>', 'Table header was printed.');
  }

  /**
   * Tests that the 'no_striping' option works correctly.
   */
  function testThemeTableWithNoStriping() {
    $rows = array(
      array(
        'data' => array(1),
        'no_striping' => TRUE,
      ),
    );
    $table = array(
      '#type' => 'table',
      '#rows' => $rows,
    );
    $this->content = drupal_render($table);
    $this->assertNoRaw('class="odd"', 'Odd/even classes were not added because $no_striping = TRUE.');
    $this->assertNoRaw('no_striping', 'No invalid no_striping HTML attribute was printed.');
  }

  /**
   * Tests that the 'header' option in cells works correctly.
   */
  function testThemeTableHeaderCellOption() {
    $rows = array(
      array(
        array('data' => 1, 'header' => TRUE),
        array('data' => 1, 'header' => FALSE),
        array('data' => 1),
      ),
    );
    $table = array(
      '#type' => 'table',
      '#rows' => $rows,
    );
    $this->content = drupal_render($table);
    $this->assertRaw('<th>1</th><td>1</td><td>1</td>', 'The th and td tags was printed correctly.');
  }

  /**
   * Asserts that a raw string appears in $this->content.
   *
   * @param string $value
   *   The expected string.
   * @param string $message
   *   (optional) A custom assertion message.
   */
  protected function assertRaw($value, $message = NULL) {
    if (!isset($message)) {
      $message = String::format("Raw value @value found.", array(
        '@value' => var_export($value, TRUE),
      ));
    }
    $this->assert(strpos($this->content, $value) !== FALSE, $message);
  }

  /**
   * Asserts that a raw string does not appear in $this->content.
   *
   * @param string $value
   *   The not expected string.
   * @param string $message
   *   (optional) A custom assertion message.
   */
  protected function assertNoRaw($value, $message = NULL) {
    if (!isset($message)) {
      $message = String::format("Raw value @value not found.", array(
        '@value' => var_export($value, TRUE),
      ));
    }
    $this->assert(strpos($this->content, $value) === FALSE, $message);
  }

}
