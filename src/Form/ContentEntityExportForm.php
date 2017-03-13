<?php

namespace Drupal\migrate_default_content\Form;

use Drupal\Core\Entity\ContentEntityType;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryFactory;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Component\Serialization\Yaml;

/**
 * Provides a form for exporting some content entities in a file.
 */
class ContentEntityExportForm extends FormBase {

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityManagerInterface
   */
  protected $entityManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   *
   * @var  \Drupal\Core\Entity\Query\QueryFactory
   */
  protected $entityQuery;

  /**
   * @var Yaml
   */
  protected $serializer;

  /**
   * Tracks the valid content entity type definitions.
   *
   * @var \Drupal\Core\Entity\EntityTypeInterface[]
   */
  protected $definitions = array();

  /**
   * ContentEntityExportForm constructor.
   *
   * @param \Drupal\Core\Entity\EntityManagerInterface $entity_manager
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   * @param \Drupal\Core\Entity\Query\QueryFactory $entity_query
   * @param \Drupal\Component\Serialization\Yaml $serializer
   */
  public function __construct(EntityManagerInterface $entity_manager, EntityTypeManagerInterface $entity_type_manager, QueryFactory $entity_query, Yaml $serializer) {
    $this->entityManager = $entity_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->entityQuery = $entity_query;
    $this->definitions = $this->entityTypeManager->getDefinitions();
    $this->serializer = $serializer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.manager'),
      $container->get('entity_type.manager'),
      $container->get('entity.query'),
      $container->get('serialization.yaml')
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
          'content_entity_type' => $content_entity_type,
          'content_bundle_type' => $content_bundle_type,
        ]
      );
      $form['export'] = $this->updateExport($form, $fake_form_state);
    }
    return $form;
  }

  /**
   * Returns and array of available content entities types.
   *
   * @return array
   */
  public function getAvailableContentEntities() {
    $entity_types = [0 => "Select a content entity type"];
    /* @var $definition EntityTypeInterface */
    foreach ($this->definitions as $entityTypeName => $definition) {

      if ($definition instanceof ContentEntityType) {
        $entity_types[$entityTypeName] = $definition->getLabel();
      }
    }
    return $entity_types;
  }

  /**
   * Return all available content bundles for a given entity type.
   *
   * @param null $content_entity_type
   *
   * @return array
   */
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
   * Handles switching the Content bundle type selector.
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
    // Read the raw data for this content entities, encode it, and display it.
    $form['export']['#value'] = $this->getExportableContentEntities($content_entity_type, $content_bundle_type);
    $form['export']['#description'] = $this->t(
      'Filename: %name',
      array('%name' => $name . '.yml')
    );
    return $form['export'];
  }

  /**
   * Serialize all the content entities for an entity type and bundle and
   * return the string.
   *
   * @return string
   */
  public function getExportableContentEntities($content_entity_type, $content_bundle_type) {
    /**
     * @var $entity_class_name EntityInterface
     */
    $content_ids = $this->entityQuery->get($content_entity_type)->range(0, 1)->execute();
    $entity_definition = $this->definitions[$content_entity_type];
    $entity_class_name = $entity_definition->getClass();
    $content = $entity_class_name::loadMultiple($content_ids);
    $serialized_data = ""; //$this->serializer::encode($content);
    return $serialized_data;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Nothing to submit.
  }

}
