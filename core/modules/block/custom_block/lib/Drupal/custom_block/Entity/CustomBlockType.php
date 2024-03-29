<?php

/**
 * @file
 * Contains \Drupal\custom_block\Entity\CustomBlockType.
 */

namespace Drupal\custom_block\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\custom_block\CustomBlockTypeInterface;

/**
 * Defines the custom block type entity.
 *
 * @ConfigEntityType(
 *   id = "custom_block_type",
 *   label = @Translation("Custom block type"),
 *   controllers = {
 *     "form" = {
 *       "default" = "Drupal\custom_block\CustomBlockTypeForm",
 *       "add" = "Drupal\custom_block\CustomBlockTypeForm",
 *       "edit" = "Drupal\custom_block\CustomBlockTypeForm",
 *       "delete" = "Drupal\custom_block\Form\CustomBlockTypeDeleteForm"
 *     },
 *     "list_builder" = "Drupal\custom_block\CustomBlockTypeListBuilder"
 *   },
 *   admin_permission = "administer blocks",
 *   config_prefix = "type",
 *   bundle_of = "custom_block",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label"
 *   },
 *   links = {
 *     "delete-form" = "custom_block.type_delete",
 *     "edit-form" = "custom_block.type_edit"
 *   }
 * )
 */
class CustomBlockType extends ConfigEntityBase implements CustomBlockTypeInterface {

  /**
   * The custom block type ID.
   *
   * @var string
   */
  public $id;

  /**
   * The custom block type label.
   *
   * @var string
   */
  public $label;

  /**
   * The default revision setting for custom blocks of this type.
   *
   * @var bool
   */
  public $revision;

  /**
   * The description of the block type.
   *
   * @var string
   */
  public $description;

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    parent::postSave($storage, $update);

    if (!$update && !$this->isSyncing()) {
      entity_invoke_bundle_hook('create', 'custom_block', $this->id());
      if (!$this->isSyncing()) {
        custom_block_add_body_field($this->id);
      }
    }
    elseif ($this->getOriginalId() != $this->id) {
      entity_invoke_bundle_hook('rename', 'custom_block', $this->getOriginalId(), $this->id);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function postDelete(EntityStorageInterface $storage, array $entities) {
    parent::postDelete($storage, $entities);

    foreach ($entities as $entity) {
      entity_invoke_bundle_hook('delete', 'custom_block', $entity->id());
    }
  }

}
