<?php

namespace ProcessWire;

$package = json_decode(file_get_contents(__DIR__ . "/package.json"));

$info = [
  'title' => 'RockPageBuilder',
  'version' => $package->version,
  'summary' => 'Master module for RockPageBuilder Fieldtype + Inputfield',
  'autoload' => 90, // RockFields has 100 and loads earlier
  'singular' => true,
  'icon' => 'cubes',
  // php8.0 for named arguments in RockMigrations
  // pw2.0.211 for repeater.js updates
  'requires' => [
    'PHP>=8.0',
    'ProcessWire>=3.0.211',
    'RockMigrations>=3.30.0',
  ],
  // RockFrontend is not a required module, but not all features will work
  // if RockFrontend is not installed!
  'minRockFrontend' => '3.3.0',
  'installs' => [
    'FieldtypeRockPageBuilder',
    'InputfieldRockPageBuilder',
    'ProcessRockPageBuilder',
  ],
];
