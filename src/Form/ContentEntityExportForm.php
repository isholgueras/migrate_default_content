<?php

namespace Drupal\migrate_default_content\Form;

use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Entity\ContentEntityType;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Serialization\Yaml;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for exporting a single configuration file.
 */
class ContentEntityExportForm extends FormBase {

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityManagerInterface
   */
  protected $entityManager;

  /**
   * The config storage.
   *
   * @var \Drupal\Core\Config\StorageInterface
   */
  protected $configStorage;

  /**
   * Tracks the valid config entity type definitions.
   *
   * @var \Drupal\Core\Entity\EntityTypeInterface[]
   */
  protected $definitions = array();

  /**
   * Constructs a new ConfigSingleImportForm.
   *
   * @param \Drupal\Core\Entity\EntityManagerInterface $entity_manager
   *   The entity manager.
   * @param \Drupal\Core\Config\StorageInterface $config_storage
   *   The config storage.
   */
  public function __construct(EntityManagerInterface $entity_manager) {
    $this->entityManager = $entity_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'migrate_default_content_export_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $content_entity_type = NULL, $content_bundle_type = NULL) {

    $default_content_entity_type = $form_state->getValue('content_entity_type', $content_entity_type);
    $form['content_entity_type'] = array(
      '#title' => $this->t('Content entity type'),
      '#type' => 'select',
      '#options' => $this->getAvailableContentEntities(),
      '#default_value' => $default_content_entity_type,
      '#ajax' => array(
        'callback' => '::updateContentBundleType',
        'wrapper' => 'edit-content-bundle-type',
      ),
    );

    $default_content_bundle_type = $form_state->getValue('content_bundle_type', $content_bundle_type);
    $form['content_bundle_type'] = array(
      '#title' => $this->t('Content bundles type'),
      '#type' => 'select',
      '#options' =>  $this->getAvailableContentBundles($default_content_entity_type),
      '#default_value' => $default_content_bundle_type,
      '#prefix' => '<div id="edit-content-bundle-type">',
      '#suffix' => '</div>',
      '#ajax' => array(
        'callback' => '::updateExport',
        'wrapper' => 'edit-export-wrapper',
      ),
    );

    $form['export'] = array(
      '#title' => $this->t('Here is your configuration:'),
      '#type' => 'textarea',
      '#rows' => 12,
      '#prefix' => '<div id="edit-export-wrapper">',
      '#suffix' => '</div>',
    );
    if ($content_entity_type && $content_bundle_type) {
      $fake_form_state = (new FormState())->setValues(
        [
          'config_type' => $content_entity_type,
          'config_name' => $content_bundle_type,
        ]
      );
      $form['export'] = $this->updateExport($form, $fake_form_state);
    }
    return $form;
  }

  /**
   * @return array
   */
  public function getAvailableContentEntities() {
    $entity_type_definations = \Drupal::entityTypeManager()->getDefinitions();
    $entity_types = [0 => "Select a content entity type"];
    /* @var $definition EntityTypeInterface */
    foreach ($entity_type_definations as $entityTypeName => $definition) {

      if ($definition instanceof ContentEntityType) {
        $entity_types[$entityTypeName] = $definition->getLabel();
      }
    }
    return $entity_types;
  }

  public function getAvailableContentBundles($content_entity_type = NULL) {
    if (empty($content_entity_type)) {
      return [];
    }

    $availableBundles = $this->entityManager->getBundleInfo($content_entity_type);
    $bundles = [0 => "Select a bundle"];
    foreach ($availableBundles as $entityBundleMachineName => $entityBundleName) {
      $bundles[$entityBundleMachineName] = $availableBundles[$entityBundleMachineName]['label'];
    }

    return $bundles;
  }

  /**
   * Handles switching the configuration type selector.
   */
  public function updateContentBundleType($form, FormStateInterface $form_state) {
    $content_entity_type = $form_state->getValue('content_entity_type');
    $bundles = $this->getAvailableContentBundles($content_entity_type);
    $form['content_bundle_type']['#options'] = $bundles;
    return $form['content_bundle_type'];
  }

  /**
   * Handles switching the export textarea.
   */
  public function updateExport($form, FormStateInterface $form_state) {
    $content_entity_type = $form_state->getValue('content_entity_type', 0);
    $content_bundle_type = $form_state->getValue('content_bundle_type', 0);

    if (empty($content_entity_type) || empty($content_bundle_type)) {
      $form['export']['#value'] = "";
      return $form['export'];
    }

    $name = $content_entity_type . "." . $content_bundle_type;
    // Read the raw data for this config name, encode it, and display it.
    $form['export']['#value'] = $this->getExportedContentEntities();
    $form['export']['#description'] = $this->t(
      'Filename: %name',
      array('%name' => $name . '.yml')
    );
    return $form['export'];
  }

  /**
   * @return string
   */
  public function getExportedContentEntities() {
    return "Content in yml format";
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Nothing to submit.
  }

}
