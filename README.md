# RockMatrix

Repeater Matrix for ProcessWire, referenced as RM in this readme.

## Concept

RockMatrix is a Fieldtype/Inputfield module that helps you creating modular content for your project. The most important part are so called `blocks` that are actually ProcessWire pages with a corresponding `template` and a custom `pageClass`.

RockMatrix ships with some example blocks that you can use to quickly get started with RockMatrix:

```php
// site/init.php (or in module init())
if($modules->isInstalled('RockMatrix')) {
  $mx = $modules->get('RockMatrix');
  $mx->addBlocks($config->paths->siteModules."RockMatrix/demo/");
}
```












### Setting up new Blocks

Blocks need to extend `\RockMatrix\Block`. To avoid naming conflicts you can use custom namespaces. See the demo folder in this module for examples.

```php
<?php namespace RMBlock;
class Headline extends \RockMatrix\Block {
}
```

Add blocks in init():

```php

```
