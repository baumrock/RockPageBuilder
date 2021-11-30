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

RockMatrix relies havily on RockMigrations. Migrations are triggered automatically via rockMatrix.module.php on modules::refresh.

## Setting up new Blocks

**Update**: It is now easier to define blocks for one field:

```php
$mx->loadBlocks("fieldname", $path, "FooNamespace");
```

Blocks need to extend `\RockMatrix\Block`. To avoid naming conflicts you can use custom namespaces. See the demo folder in this module for examples. The minimum viable block is this:

```php
<?php namespace RMBlock;
class MinimumDemo extends \RockMatrix\Block {
}
```

Make sure you save the file as `MinimumDemo.php` and tell RockMatrix about that file (if it is not in an already monitored folder). Use `$mx->addBlocks('/your/dir')` as shown above.

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
    'RMDemo\Headline',
    'RMDemo\Markup',
  ]);
});
```

If you don't define a parent for blocks, the blocks will live under the default blocks datapage:



## Working with field data

The unformatted value of a RockMatrix field is a `RockMatrix\FieldData` object. This class is based on a `PageArray` and holds all blocks that are saved on that field.

The formatValue of the field is the result of the `render()` method call on the `FieldData` object and concats the results of all blocks' `render()` methods.

### Adding blocks to the field

Adding existing pages to the field is easy:

```php
$data = $page->getUnformatted('rmtest');
$data->add(1035); // add block id 1035 to this field
$page->save('rmtest');
```

But what if the blockpage does not exist yet? Also easy via the API:

```php
$page->rmtest->create([
  'type' => '\RMDemo\Headline',
  'add' => true, // default
  'data' => [
    'text' => 'This is a demo headline',
  ],
]);
```

Sometimes it's helpful to reset the field before adding new blocks:

```php
$page->rmtest->reset()->create(...)->create(...)->save();
```

## Example migration

```php
$rm->migrate([
  'fields' => [
    'your_field' => [
      'type' => 'FieldtypeRockMatrix',
      'tags' => self::tags,
    ],
  ],
])
```
