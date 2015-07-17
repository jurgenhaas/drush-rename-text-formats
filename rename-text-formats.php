<?php

$arguments = drush_get_arguments();
array_shift($arguments);
array_shift($arguments);

$tasks = array();
foreach ($arguments as $argument) {
  list($old,$new) = explode('=>', $argument);
  if (!empty($old) && !empty($new)) {
    $tasks[trim($old)] = trim($new);
  }
}

if (!drush_confirm('Do you want to perform the following renaming on text-formats: ' . print_r($tasks, TRUE))) {
  return;
}

function replace_format_values($module, $table, $field, $maps) {
  print "Update $table for module $module\n";
  foreach ($maps as $old => $new) {
    db_update($table)
      ->fields(array($field => $new))
      ->condition($field, $old)
      ->execute();
  }
}

$modules = db_select('system', 's')
  ->fields('s', array('name'))
  ->condition('s.status', 1)
  ->condition('s.schema_version', 0, '>')
  ->execute()
  ->fetchCol(0);

foreach ($modules as $module) {
  module_load_include('install', $module);
  if ($module == 'field') {
    $fields = db_select('field_config', 'f')
      ->fields('f', array('field_name'))
      ->condition('f.module', 'text')
      ->execute()
      ->fetchCol(0);
    foreach ($fields as $field) {
      $tables = array(
        'field_data_' . $field,
        'field_revision_' . $field,
      );
      $field_name = $field . '_format';
      foreach ($tables as $table) {
        if (db_table_exists($table) && db_field_exists($table, $field_name)) {
          replace_format_values($module, $table, $field_name, $tasks);
        }
      }
    }
  }
  else {
    $schema = module_invoke($module, 'schema');
    if (!empty($schema)) {
      foreach ($schema as $table => $def) {
        if (!empty($def['fields']['format'])) {
          replace_format_values($module, $table, 'format', $tasks);
        }
      }
    }
  }
}

foreach ($tasks as $old => $new) {
  $old_name = 'use text format ' . $old;
  $new_name = 'use text format ' . $new;
  db_update('role_permission')
    ->fields(array('permission' => $new_name))
    ->condition('permission', $old_name)
    ->execute();
}

// cache_clear_all();
drupal_flush_all_caches();
