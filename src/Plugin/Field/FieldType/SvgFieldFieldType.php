<?php

namespace Drupal\svg_field\Plugin\Field\FieldType;

use Drupal\Component\Utility\Random;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Component\Utility\Bytes;
use Drupal\Component\Render\PlainTextOutput;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\Core\StreamWrapper\StreamWrapperInterface;

/**
 * Plugin implementation of the 'svg_field_field_type' field type.
 *
 * @FieldType(
 *   id = "svg_field_field_type",
 *   label = @Translation("SVG"),
 *   description = @Translation("SVG Field Field Type"),
 *   default_widget = "svg_field_widget",
 *   default_formatter = "svg_field_formatter"
 *   constraints = {"ReferenceAccess" = {}, "FileValidation" = {}}
 * )
 */
class SvgFieldFieldType extends EntityReferenceItem {

  /**
   * {@inheritdoc}
   */
  public static function defaultStorageSettings() {
    return array(
      'target_type' => 'file',
      'display_field' => FALSE,
      'display_default' => FALSE,
      'uri_scheme' => file_default_scheme(),
    ) + parent::defaultStorageSettings();
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties = parent::propertyDefinitions($field_definition);

    $properties['display'] = DataDefinition::create('boolean')
      ->setLabel(t('Display'))
      ->setDescription(t('Flag to control whether this file should be displayed when viewing content'));

    $properties['description'] = DataDefinition::create('string')
      ->setLabel(t('Description'));

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    return array(
      'columns' => array(
        'target_id' => array(
          'description' => 'The ID of the SVG entity.',
          'type' => 'int',
          'unsigned' => TRUE,
        ),
        'display' => array(
          'description' => 'Flag to control whether this file should be displayed when viewing content.',
          'type' => 'int',
          'size' => 'tiny',
          'unsigned' => TRUE,
          'default' => 1,
        ),
        'description' => array(
          'description' => 'A description of the file.',
          'type' => 'text',
        ),
      ),
      'indexes' => array(
        'target_id' => array('target_id'),
      ),
      'foreign keys' => array(
        'target_id' => array(
          'table' => 'file_managed',
          'columns' => array('target_id' => 'fid'),
        ),
      ),
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function generateSampleValue(FieldDefinitionInterface $field_definition) {
    $random = new Random();
    $settings = $field_definition->getSettings();

    // Prepare destination.
    $dir = static::doGetUploadLocation($settings);
    file_prepare_directory($dirname, FILE_CREATE_DIRECTORY);

    // Generate a file entity.
    $destination = $dir . '/' . $random->name(10, TRUE) . '.svg';
    $data = $random->paragraphs(3);
    $file = file_save_data($data, $destination, FILE_EXISTS_ERROR);
    $values = array(
      'target_id' => $file->id(),
      'display' => (int)$settings['display_default'],
      'description' => $random->sentences(10),
    );
    return $values;
  }

  /**
   * {@inheritdoc}
   */
  public function storageSettingsForm(array &$form, FormStateInterface $form_state, $has_data) {
    $element = array();

    $element['#attached']['library'][] = 'file/drupal.file';

    $element['display_field'] = array(
      '#type' => 'checkbox',
      '#title' => t('Enable <em>Display</em> field'),
      '#default_value' => $this->getSetting('display_field'),
      '#description' => t('The display option allows users to choose if an SVG should be shown when viewing the content.'),
    );
    $element['display_default'] = array(
      '#type' => 'checkbox',
      '#title' => t('SVG files displayed by default'),
      '#default_value' => $this->getSetting('display_default'),
      '#description' => t('This setting only has an effect if the display option is enabled.'),
      '#states' => array(
        'visible' => array(
          ':input[name="settings[display_field]"]' => array('checked' => TRUE),
        ),
      ),
    );

    $scheme_options = \Drupal::service('stream_wrapper_manager')->getNames(StreamWrapperInterface::WRITE_VISIBLE);
    $element['uri_scheme'] = array(
      '#type' => 'radios',
      '#title' => t('Upload destination'),
      '#options' => $scheme_options,
      '#default_value' => $this->getSetting('uri_scheme'),
      '#description' => t('Select where the final SVG files should be stored. Private SVG file storage has significantly more overhead than public files, but allows restricted access to files within this field.'),
      '#disabled' => $has_data,
    );

    return $element;
  }

}
