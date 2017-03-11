<?php

namespace Drupal\migrate_default_content\Form;

use Drupal\Console\Command\Shared\TranslationTrait;
use Drupal\Core\Entity\ContentEntityType;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Returns responses for migrate_tools migration view routes.
 */
class ContentEntityExportForm extends FormBase {
  use TranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'migrate_default_content_export_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['content_entities'] = [
      '#title' => $this->t('Content entity to export'),
      '#type' => 'select',
      '#options' => $this->getAvailableContentEntities(),
    ];


    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Export'),
      '#button_type' => 'primary',
    );

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    // TODO: Implement submitForm() method.
  }

  /**
   * @return array
   */
  public function getAvailableContentEntities() {
    $content_entity_types = array();
    $entity_type_definations = \Drupal::entityTypeManager()->getDefinitions();
    $bundles = \Drupal::entityManager()->getAllBundleInfo();
    $tables = [];
    /* @var $definition EntityTypeInterface */
    foreach ($entity_type_definations as $definition) {
      $base_table = $definition->getBaseTable();
      if ($definition instanceof ContentEntityType && $base_table) {

        $content_entity_types[] = $definition;
        foreach ($bundles[$base_table] as $entityBundleMachineName => $entityBundleName) {
          $filename = "$base_table.$entityBundleMachineName";
          $tables[$filename] = $filename;
        }
      }
    }
    return $tables;
  }
}
