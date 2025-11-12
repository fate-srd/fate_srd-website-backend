<?php
/**
 * @file
 * Script to mark missing post-update hooks as completed.
 *
 * This script marks the required post-update hooks as completed so that
 * Drupal can proceed with the update process when upgrading from older versions.
 */

use Drupal\Core\DrupalKernel;
use Symfony\Component\HttpFoundation\Request;

// Bootstrap Drupal.
$autoloader = require __DIR__ . '/../autoload.php';
$request = Request::createFromGlobals();
$kernel = DrupalKernel::createFromRequest($request, $autoloader, 'prod');
$kernel->boot();

$container = $kernel->getContainer();

// Get the key_value service for post-updates.
$key_value = $container->get('keyvalue')->get('update.post_update');

// List of post-update hooks that need to be marked as completed.
$post_updates = [
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

$marked = 0;
foreach ($post_updates as $update) {
  // Check if already marked.
  if (!$key_value->get($update)) {
    // Mark as completed by setting it to TRUE.
    $key_value->set($update, TRUE);
    $marked++;
    fwrite(STDOUT, "Marked post-update hook as completed: $update\n");
  } else {
    fwrite(STDOUT, "Post-update hook already marked: $update\n");
  }
}

if ($marked > 0) {
  fwrite(STDOUT, "\nSuccessfully marked $marked post-update hook(s) as completed.\n");
  fwrite(STDOUT, "You can now run 'drush updatedb' or visit /update.php to continue with updates.\n");
} else {
  fwrite(STDOUT, "\nAll post-update hooks are already marked as completed.\n");
}

exit(0);

