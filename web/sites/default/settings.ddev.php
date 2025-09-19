<?php

/**
 * @file
 * DDEV-specific settings for the Drupal site.
 *
 * This file is automatically created by DDEV and should not be modified manually.
 * It contains database connection settings for the DDEV environment.
 */

// Database configuration for DDEV
$databases['default']['default'] = [
  'database' => 'db',
  'username' => 'db',
  'password' => 'db',
  'host' => 'db',
  'port' => '3306',
  'driver' => 'mysql',
  'prefix' => '',
  'namespace' => 'Drupal\\Core\\Database\\Driver\\mysql',
  'collation' => 'utf8mb4_general_ci',
];

// DDEV trusted host patterns
$settings['trusted_host_patterns'] = [
  '^fate-srd-backend\.ddev\.site$',
  '^localhost$',
  '^127\.0\.0\.1$',
  '^.+\.ddev\.site$',
];

// File system settings for DDEV
$settings['file_public_path'] = 'sites/default/files';
$settings['file_temp_path'] = '/tmp';

// Development settings for DDEV
$settings['container_yamls'][] = $app_root . '/' . $site_path . '/services.yml';

// Disable CSS and JS aggregation for development
$config['system.performance']['css']['preprocess'] = FALSE;
$config['system.performance']['js']['preprocess'] = FALSE;

// Disable render cache for development
$settings['cache']['bins']['render'] = 'cache.backend.null';
$settings['cache']['bins']['page'] = 'cache.backend.null';
$settings['cache']['bins']['dynamic_page_cache'] = 'cache.backend.null';

// Enable verbose error reporting for development
$config['system.logging']['error_level'] = 'verbose';

// Disable twig cache for development
$settings['twig_debug'] = TRUE;
$settings['twig_auto_reload'] = TRUE;
$settings['twig_cache'] = FALSE;
