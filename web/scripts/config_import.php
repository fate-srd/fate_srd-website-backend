<?php
declare(strict_types=1);

use Drupal\Core\DrupalKernel;
use Drupal\Core\Config\ConfigImporter;
use Drupal\Core\Config\StorageComparer;
use Symfony\Component\HttpFoundation\Request;

// Bootstrap Drupal.
$autoloader = require __DIR__ . '/../autoload.php';
$request = Request::createFromGlobals();
$kernel = DrupalKernel::createFromRequest($request, $autoloader, 'prod');
$kernel->boot();

$container = $kernel->getContainer();

// Build a storage comparer from sync to active.
$source = $container->get('config.storage.sync');
$target = $container->get('config.storage');
$configManager = $container->get('config.manager');

$storageComparer = new StorageComparer($source, $target, $configManager);
$storageComparer->createChangelist();

if (!$storageComparer->hasChanges()) {
  fwrite(STDOUT, "No configuration changes to import.\n");
  exit(0);
}

try {
  $configImporter = new ConfigImporter(
    $storageComparer,
    $container->get('event_dispatcher'),
    $configManager,
    $container->get('lock'),
    $container->get('config.typed'),
    $container->get('module_handler'),
    $container->get('module_installer'),
    $container->get('theme_handler'),
    $container->get('string_translation'),
    $container->get('extension.list.module'),
    $container->get('extension.list.theme')
  );
  $configImporter->import();
  fwrite(STDOUT, "Configuration import completed successfully.\n");
  exit(0);
} catch (\Throwable $e) {
  fwrite(STDERR, "Configuration import failed: " . $e->getMessage() . "\n");
  // Optionally dump more details.
  // fwrite(STDERR, $e->getTraceAsString() . "\n");
  exit(1);
}


