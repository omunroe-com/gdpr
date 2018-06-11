<?php

/**
 * @file
 * Contains the GDPRFieldData class.
 */

/**
 * Class for storing GDPR metadata for fields.
 */
class GDPRFieldData {

  /**
   * The machine name for this field.
   * @var string
   */
  public $name;

  /**
   * Plugin type of field.
   * @var string
   */
  public $plugin_type;

  /**
   * Human readable name for the field.
   * @var string
   */
  public $label;

  /**
   * Short description for the field.
   * @var string
   */
  public $description;

  /**
   * Entity type of field.
   * @var string
   */
  public $entity_type;

  /**
   * Entity bundle of field.
   * @var string
   */
  public $entity_bundle;

  /**
   * Name of the property.
   * @var string
   */
  public $property_name;

  /**
   * Whether this finder is disabled.
   */
  public $disabled = FALSE;

  /**
   * Additional settings.
   * @var array
   */
  public $settings;

  /**
   * Create field data from plugin definition.
   *
   * @param array $plugin
   *   An array of duplicates. Each item should be an array with 2 party id.
   *
   * @return static
   *   New field data object.
   */
  public static function createFromPlugin(array $plugin) {
    $field = new static();

    list($entity_type, $entity_bundle, $property_name) = explode('|', $plugin['name']);
    $field->entity_type = $entity_type;
    $field->entity_bundle = $entity_bundle;
    $field->property_name = $property_name;
    $field->name = $plugin['name'];

    // @todo Should computed properties be removed instead or disabled?
    if (!empty($plugin['computed'])) {
      $field->computed = TRUE;
      $field->disabled = TRUE;
    }

    if (isset($plugin['label'])) {
      $field->label = $plugin['label'];
      $field->setSetting('label', $plugin['label']);
    }
    if (isset($plugin['description'])) {
      $field->description = $plugin['label'];
      $field->setSetting('description', $plugin['description']);
    }

    return $field;
  }

  /**
   * Create field data from the GDPR field name.
   *
   * @param string $name
   *   The GDPR field name in the format entity_type|bundle|property.
   *
   * @return static|null
   *   Either a GDPR field or NULL if it doesn't exist.
   */
  public static function createFromName($name) {
    // Attempt to load the configured version.
    ctools_include('export');
    $plugins = ctools_export_load_object('gdpr_fields_field_data');
    if (isset($plugins[$name])) {
      return $plugins[$name];
    }

    // Otherwise fall back to the defaults.
    ctools_include('plugins');
    $plugin = ctools_get_plugins('gdpr_fields', 'gdpr_data', $name);
    if ($plugin) {
      return static::createFromPlugin($plugin);
    }

    return NULL;
  }

  /**
   * Create field data from property name, entity type and bundle.
   *
   * @param string $entity_type
   *   The entity type.
   * @param string $bundle
   *   The bundle.
   * @param string $property_name
   *   The property name.
   *
   * @return static|null
   *   Either a GDPR field or NULL if it doesn't exist.
   */
  public static function createFromProperty($entity_type, $bundle, $property_name) {
    return static::createFromName(implode('|', array($entity_type, $bundle, $property_name)));
  }
  
  /**
   * Create the field data from a property wrapper.
   *
   * @param EntityMetadataWrapper $wrapper
   *
   * @return static|null
   *   Either a GDPR field or NULL if it doesn't exist.
   */
  public static function createFromWrapper(EntityMetadataWrapper $wrapper) {
    $entity_type = $bundle = $property_name = FALSE;
    while (!$entity_type) {
      $info = $wrapper->info();
      if (empty($info['parent'])) {
        return NULL;
      }

      $wrapper = $info['parent'];
      if ($wrapper instanceof EntityDrupalWrapper) {
        $entity_type = $wrapper->type();
        $bundle = $wrapper->getBundle();
        $property_name = $info['name'];

        break;
      }
    }

    return static::createFromProperty($entity_type, $bundle, $property_name);
  }

  /**
   * Get a stored setting.
   *
   * @param string $setting
   *   The key of the setting to be fetched.
   * @param mixed|null $default
   *   The default to be returned if not stored.
   *
   * @return mixed|null
   *   The field data setting.
   */
  public function getSetting($setting, $default = NULL) {
    if (isset($this->settings[$setting])) {
      return $this->settings[$setting];
    }

    return $default;
  }
  
  /**
   * Whether to recurse to entities included in this property.
   */
  public function includeRelatedEntities() {
    // If not explicitly a GDPR field, don't recurse.
    if (!$this->getSetting('gdpr_fields_enabled')) {
      return FALSE;
    }

    // If the field is an owner, don't recurse.
    if ($this->getSetting('gdpr_fields_owner')) {
      return FALSE;
    }

    // Don't follow if we've been explicitly set not to.
    if ($this->getSetting('gdpr_fields_no_follow')) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Get a stored setting.
   *
   * @param string $setting
   *   The key of the setting to be stored.
   * @param mixed $value
   *   The value to be stored.
   *
   * @return $this
   */
  public function setSetting($setting, $value) {
    $this->settings[$setting] = $value;
    return $this;
  }
}
