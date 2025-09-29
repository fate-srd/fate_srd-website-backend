<?php
$pdo = new PDO("mysql:host=database;dbname=drupal9;charset=utf8mb4","drupal9","drupal9",[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$data = $pdo->query("SELECT data FROM config WHERE name='core.extension'")->fetchColumn();
$arr = @unserialize($data);
if ($arr === false || !is_array($arr)) { fwrite(STDERR, "Could not unserialize core.extension\n"); exit(1); }
$arr['profile'] = 'standard';
$pdo->prepare("UPDATE config SET data=? WHERE name='core.extension'")->execute([serialize($arr)]);
echo "profile set to standard\n";
