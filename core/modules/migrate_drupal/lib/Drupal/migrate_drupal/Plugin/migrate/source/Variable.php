<?php

/**
 * @file
 * Contains \Drupal\migrate_drupal\Plugin\migrate\source\Variable.
 */

namespace Drupal\migrate_drupal\Plugin\migrate\source;

use Drupal\migrate\Entity\MigrationInterface;

/**
 * Drupal variable source from database.
 *
 * This source class always returns a single row and as such is not a good
 * example for any normal source class returning multiple rows.
 *
 * @MigrateSource(
 *   id = "variable"
 * )
 */
class Variable extends DrupalSqlBase {

  /**
   * The variable names to fetch.
   *
   * @var array
   */
  protected $variables;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $migration);
    $this->variables = $this->configuration['variables'];
  }

  protected function runQuery() {
    return new \ArrayIterator(array(array_map('unserialize', $this->prepareQuery()->execute()->fetchAllKeyed())));
  }

  public function count() {
    return intval($this->query()->countQuery()->execute()->fetchField() > 0);
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    return array_combine($this->variables, $this->variables);
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    return $this->getDatabase()
      ->select('variable', 'v')
      ->fields('v', array('name', 'value'))
      ->condition('name', $this->variables, 'IN');
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    return array();
  }

}
