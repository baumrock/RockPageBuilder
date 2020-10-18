# RockMatrix

Repeater Matrix for ProcessWire, referenced as RM in this readme.

![img](https://i.ibb.co/xM796yt/matrix.gif)

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

## Migrations

RockMatrix relies havily on RockMigrations. Migrations are NOT triggered automatically. The reason is that this is potentially error prone when uninstalling the module, so that a modules refresh triggered by uninstalling a linked module (like fieldtype or inputfield) triggers the migrations and recreates fields + templates.

Migrations can easily be run via commandline (`$mx->migrate()`) or via checkbox in the modules' settings.

## Setting up new Blocks

Blocks need to extend `\RockMatrix\Block`. To avoid naming conflicts you can use custom namespaces. See the demo folder in this module for examples. The minimum viable block is this:

```php
<?php namespace RMBlock;
class MinimumDemo extends \RockMatrix\Block {
}
```

Make sure you save the file as `MinimumDemo.php` and tell RockMatrix about that file (if it is not in an already monitored folder). Use `$mx->addBlocks('/your/dir')` as shown above.

To make any changes take effect you need to run `migrate()` on the RockMatrix module:

```php
$modules->get('RockMatrix')->migrate();
```

## Render content

The content of the field can be rendered via the `render()` method of the field data object. This calls the `render()` method of each block. If you just `echo` the field value it will show the ids of the pagearray (which is the string representation of the pagearray and is necessary for usage on selectors).

### Render blocks

The markup for rendering your block can either be defined as `render()` method of your block or you can create a view file for your block e.g. `/site/assets/blocks/Slider.view.php`.

## Field setup

Now create a new field and add it to a template. Allowed blocks are defined via hook:

```php
$wire->addHookAfter('RockMatrix::getAllowedBlocks', function($event) {
  $field = $event->arguments(0);
  $page = $event->arguments(1);
  if($field->name !== 'rmtest') return;
  $event->return->add([
    '\RMDemo\Headline',
    '\RMDemo\Markup',
  ]);
});
```

If you don't define a parent for blocks, the blocks will live under the default blocks datapage:



## Working with field data

The unformatted value of a RockMatrix field is a `RockMatrix\FieldData` object. This class is based on a `PageArray` and holds all blocks that are saved on that field.

The formatValue of the field is the result of the `render()` method call on the `FieldData` object and concats the results of all blocks' `render()` methods.

### Adding blocks to the field

```php
$data = $page->getUnformatted('rmtest');
$data->add(1035); // add block id 1035 to this field
$page->save('rmtest');
```
