<?php

/**
 * @file
 * Apply Drupal post-updates that 11.0 removed before this site ran them.
 *
 * `drush updatedb` refuses to start while these names are missing from the
 * post_update key-value list. The functions themselves are gone, so this
 * repeats their config changes and then records them. Safe to run more than
 * once. Deploy runs it before `drush deploy`.
 *
 * Usage: drush php:script scripts/repair-removed-post-updates.php
 */

use Drupal\Core\Config\ConfigManagerInterface;
use Drupal\Core\Config\Entity\ConfigEntityUpdater;
use Drupal\Core\Config\Schema\Mapping;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Site\Settings;
use Drupal\datetime_range\Plugin\Field\FieldFormatter\DateRangeCustomFormatter;
use Drupal\datetime_range\Plugin\Field\FieldFormatter\DateRangeDefaultFormatter;
use Drupal\datetime_range\Plugin\Field\FieldFormatter\DateRangePlainFormatter;
use Drupal\editor\EditorInterface;
use Drupal\filter\FilterFormatInterface;
use Drupal\media\MediaTypeInterface;
use Drupal\node\NodeTypeInterface;
use Drupal\taxonomy\VocabularyInterface;
use Drupal\views\ViewEntityInterface;

$removed_updates = [
  'datetime_range_post_update_from_to_configuration',
  'editor_post_update_sanitize_image_upload_settings',
  'filter_post_update_consolidate_filter_config',
  'media_post_update_remove_mappings_targeting_source_field',
  'node_post_update_set_node_type_description_and_help_to_null',
  'system_post_update_amend_config_sync_readme_url',
  'system_post_update_mail_notification_setting',
  'system_post_update_set_cron_logging_setting_to_boolean',
  'system_post_update_move_development_settings_to_keyvalue',
  'system_post_update_add_langcode_to_all_translatable_config',
  'taxonomy_post_update_set_new_revision',
  'taxonomy_post_update_set_vocabulary_description_to_null',
  'views_post_update_pager_heading',
  'views_post_update_rendered_entity_field_cache_metadata',
];

$post_update = \Drupal::keyValue('post_update');
$existing = $post_update->get('existing_updates', []);
$missing = array_values(array_diff($removed_updates, $existing));
if ($missing === []) {
  echo "Removed post-updates are already recorded.\n";
  return;
}

echo 'Applying ' . count($missing) . " skipped post-updates.\n";

$updater = \Drupal::classResolver(ConfigEntityUpdater::class);

if (in_array('datetime_range_post_update_from_to_configuration', $missing, TRUE)) {
  $formatter_manager = \Drupal::service('plugin.manager.field.formatter');
  $daterange_classes = [
    DateRangeCustomFormatter::class,
    DateRangeDefaultFormatter::class,
    DateRangePlainFormatter::class,
  ];
  fate_run_config_updater($updater, 'entity_view_display', function (EntityViewDisplayInterface $display) use ($formatter_manager, $daterange_classes): bool {
    $changed = FALSE;
    foreach ($display->getComponents() as $name => $component) {
      if (empty($component['type'])) {
        continue;
      }
      $definition = $formatter_manager->getDefinition($component['type'], FALSE);
      if (!$definition || !in_array($definition['class'], $daterange_classes, FALSE)) {
        continue;
      }
      if (!isset($component['settings']['from_to'])) {
        $component['settings']['from_to'] = 'both';
        $display->setComponent($name, $component);
        $changed = TRUE;
      }
    }
    return $changed;
  });
}

if (in_array('editor_post_update_sanitize_image_upload_settings', $missing, TRUE)) {
  fate_run_config_updater($updater, 'editor', function (EditorInterface $editor): bool {
    $settings = $editor->getImageUploadSettings();
    $updated = $settings;
    // Boolean config was historically stored as 0 and 1.
    if (!array_key_exists('status', $updated) || $updated['status'] == FALSE) {
      $updated = ['status' => FALSE];
    }
    else {
      if (array_key_exists('directory', $updated) && $updated['directory'] === '') {
        $updated['directory'] = NULL;
      }
      if (array_key_exists('max_size', $updated) && $updated['max_size'] === '') {
        $updated['max_size'] = NULL;
      }
      if (array_key_exists('max_dimensions', $updated)) {
        if (!array_key_exists('width', $updated['max_dimensions']) || $updated['max_dimensions']['width'] === 0) {
          $updated['max_dimensions']['width'] = NULL;
        }
        if (!array_key_exists('height', $updated['max_dimensions']) || $updated['max_dimensions']['height'] === 0) {
          $updated['max_dimensions']['height'] = NULL;
        }
      }
    }
    if ($updated == $settings) {
      return FALSE;
    }
    $editor->setImageUploadSettings($updated);
    return TRUE;
  });
}

if (in_array('filter_post_update_consolidate_filter_config', $missing, TRUE)) {
  fate_run_config_updater($updater, 'filter_format', function (FilterFormatInterface $format): bool {
    foreach ($format->get('filters') as $config) {
      if (empty($config['id']) || empty($config['provider'])) {
        return TRUE;
      }
    }
    return FALSE;
  });
}

if (in_array('media_post_update_remove_mappings_targeting_source_field', $missing, TRUE)) {
  fate_run_config_updater($updater, 'media_type', function (MediaTypeInterface $media_type): bool {
    $source_field = $media_type->getSource()->getSourceFieldDefinition($media_type);
    if (!$source_field) {
      return FALSE;
    }
    $source_field_name = $source_field->getName();
    $original_field_map = $media_type->getFieldMap();
    $field_map = array_diff($original_field_map, [$source_field_name]);
    if (empty(array_diff($original_field_map, $field_map))) {
      return FALSE;
    }
    $media_type->setFieldMap($field_map);
    return TRUE;
  });
}

if (in_array('node_post_update_set_node_type_description_and_help_to_null', $missing, TRUE)) {
  fate_run_config_updater($updater, 'node_type', function (NodeTypeInterface $node_type): bool {
    $changed = FALSE;
    $description = $node_type->getDescription();
    if (is_string($description) && trim($description) === '') {
      $node_type->set('description', NULL);
      $changed = TRUE;
    }
    $help = $node_type->getHelp();
    if (is_string($help) && trim($help) === '') {
      $node_type->set('help', NULL);
      $changed = TRUE;
    }
    return $changed;
  });
}

if (in_array('taxonomy_post_update_set_new_revision', $missing, TRUE) || in_array('taxonomy_post_update_set_vocabulary_description_to_null', $missing, TRUE)) {
  fate_run_config_updater($updater, 'taxonomy_vocabulary', function (VocabularyInterface $vocabulary): bool {
    $raw = \Drupal::config('taxonomy.vocabulary.' . $vocabulary->id())->getRawData();
    $changed = FALSE;
    $description = $raw['description'] ?? NULL;
    if (is_string($description) && trim($description) === '') {
      $vocabulary->set('description', NULL);
      $changed = TRUE;
    }
    if (!array_key_exists('new_revision', $raw)) {
      $vocabulary->setNewRevision(FALSE);
      $changed = TRUE;
    }
    return $changed;
  });
}

if (in_array('views_post_update_pager_heading', $missing, TRUE) || in_array('views_post_update_rendered_entity_field_cache_metadata', $missing, TRUE)) {
  fate_run_config_updater($updater, 'view', function (ViewEntityInterface $view): bool {
    $changed = FALSE;
    $displays = $view->get('display');
    foreach ($displays as &$display) {
      $pager = $display['display_options']['pager'] ?? NULL;
      if (is_array($pager) && in_array($pager['type'] ?? '', ['mini', 'full'], TRUE) && !isset($pager['options']['pagination_heading_level'])) {
        $display['display_options']['pager']['options']['pagination_heading_level'] = 'h4';
        $changed = TRUE;
      }
      foreach ($display['display_options']['fields'] ?? [] as $handler) {
        if (is_array($handler) && ($handler['plugin_id'] ?? '') === 'rendered_entity') {
          $changed = TRUE;
        }
      }
    }
    unset($display);
    if ($changed) {
      $view->set('display', $displays);
    }
    return $changed;
  });
}

if (in_array('system_post_update_amend_config_sync_readme_url', $missing, TRUE)) {
  $configuration_directory = Settings::get('config_sync_directory');
  $readme_path = $configuration_directory . '/README.txt';
  if (is_file($readme_path) && is_writable($readme_path)) {
    $original_content = file_get_contents($readme_path);
    $changed_content = str_replace('admin/config/development/configuration/sync', 'admin/config/development/configuration', $original_content);
    if ($changed_content !== $original_content) {
      file_put_contents($readme_path, $changed_content);
    }
  }
}

if (in_array('system_post_update_mail_notification_setting', $missing, TRUE)) {
  $config = \Drupal::configFactory()->getEditable('system.site');
  if ($config->get('mail_notification') === NULL) {
    $config->set('mail_notification', NULL)->save();
  }
}

if (in_array('system_post_update_set_cron_logging_setting_to_boolean', $missing, TRUE)) {
  $config = \Drupal::configFactory()->getEditable('system.cron');
  $logging = $config->get('logging');
  if (!is_bool($logging)) {
    $config->set('logging', (bool) $logging)->save();
  }
}

if (in_array('system_post_update_move_development_settings_to_keyvalue', $missing, TRUE)) {
  $state_keys = [
    'twig_debug',
    'twig_cache_disable',
    'disable_rendered_output_cache_bins',
  ];
  $stored = \Drupal::keyValue('state')->getMultiple($state_keys);
  if ($stored) {
    \Drupal::keyValue('development_settings')->setMultiple($stored);
    \Drupal::state()->deleteMultiple(array_keys($stored));
  }
}

if (in_array('system_post_update_add_langcode_to_all_translatable_config', $missing, TRUE)) {
  $sandbox = [];
  do {
    fate_add_langcode_to_translatable_config($sandbox);
  } while (empty($sandbox['#finished']));
}

$post_update->set('existing_updates', array_values(array_unique(array_merge($existing, $removed_updates))));
echo "Recorded skipped post-updates.\n";

/**
 * Runs a config entity updater until the batch finishes.
 */
function fate_run_config_updater(ConfigEntityUpdater $updater, string $entity_type_id, callable $callback): void {
  $sandbox = [];
  do {
    $updater->update($sandbox, $entity_type_id, $callback);
  } while (($sandbox['#finished'] ?? 1) < 1);
  echo "Updated $entity_type_id configuration.\n";
}

/**
 * Adds a langcode to simple config that contains translatable values.
 *
 * Equivalent to system_post_update_add_langcode_to_all_translatable_config().
 */
function fate_add_langcode_to_translatable_config(array &$sandbox): void {
  $config_factory = \Drupal::configFactory();
  if (!isset($sandbox['names'])) {
    $sandbox['names'] = $config_factory->listAll();
    $sandbox['max'] = count($sandbox['names']);
  }

  $typed_config_manager = \Drupal::service(TypedConfigManagerInterface::class);
  $config_manager = \Drupal::service(ConfigManagerInterface::class);
  $default_langcode = \Drupal::languageManager()->getDefaultLanguage()->getId();
  $names = array_splice($sandbox['names'], 0, Settings::get('entity_update_batch_size', 50));

  foreach ($names as $name) {
    if ($config_manager->getEntityTypeIdByName($name) || !$typed_config_manager->hasConfigSchema($name)) {
      continue;
    }

    $config = $config_factory->getEditable($name);
    $typed_config = $typed_config_manager->createFromNameAndData($name, $config->getRawData());
    if (!$typed_config instanceof Mapping) {
      continue;
    }

    if ($typed_config->hasTranslatableElements()) {
      if ($config->get('langcode')) {
        continue;
      }
      $config->set('langcode', $default_langcode);
    }
    elseif (!array_key_exists('langcode', $config->getRawData())) {
      continue;
    }
    else {
      $config->clear('langcode');
    }
    $config->save();
  }

  $sandbox['#finished'] = empty($sandbox['max']) || empty($sandbox['names']) ? 1 : ($sandbox['max'] - count($sandbox['names'])) / $sandbox['max'];
}
