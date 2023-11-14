# Block Settings

It's very common that you want to provide some options for your block, for example for choosing between different layout variations or for changing the size of the preview thumbnails of an image gallery block.

## RockFields

You can create a field for all those settings, if you want. But the better option is to use the `RockFields` module that ships with RockPageBuilder.

It allows you to create several fields at runtime without really creating those fields in the database. It needs some code, but don't worry! Whenever you create a new block you'll get some boilerplate code that you can use and adjust to your needs:

```php
// this goes into your block's PHP file
public function settingsTable(\ProcessWire\RockFieldsField $field)
{
  // You can set default settings for all blocks via hook.
  // See docs for details or leave this line unchanged.
  $settings = $this->getDefaultSettings($field);

  $settings->add([
    'name' => 'demo-checkbox',
    'label' => 'Demo Checkbox',
    'value' => $field->input('demo-checkbox', 'checkbox'),
  ]);

  $settings->add([
    'name' => 'demo-text',
    'label' => 'Demo Text Field',
    'value' => $field->input('demo-text', 'text'),
  ]);

  $settings->add([
    'name' => 'demo-select',
    'label' => 'Demo Select Field',
    'value' => $field->input('demo-select', 'select', [
      'foo' => 'foo value', // the star marks the default option
      'bar' => 'bar value',
    ]),
  ]);

  $settings->add([
    'name' => 'demo-radios',
    'label' => 'Demo Radios Field',
    'value' => $field->input('demo-radios', 'radios', [
      'foo' => 'foo value', // the star marks the default option
      '*bar' => 'bar value',
    ]),
  ]);

  return $settings;
}
```

<img src=settings.png class=blur>

Of course you can also have multilingual settings. The only thing to make sure is that only the labels are translated and the values are unique:

```php
$settings->add([
  'name' => 'demo-select',
  'label' => __('Demo Select Field'),
  'value' => $field->input('demo-select', 'select', [
    'foo' => __('foo value label'),
    'bar' => __('bar value label'),
  ]),
]);
```

## Adding default settings

Sometimes you want to have the same setting on all or almost all blocks of your project. Rather than copy and pasting the same settings from block to block you can define global settings for all blocks and add additional settings to some blocks later:

```php
// in site/ready.php

use RockPageBuilder\Block;
use RockPageBuilder\BlockSettingsArray;

/** @var RockPageBuilder $rpb */
$rpb = $this->wire->modules->get('RockPageBuilder');
$rpb->defaultSettings(
  function (BlockSettingsArray $settings, RockFieldsField $field, Block $block) {
    $settings->add([
      'name' => 'bgmuted',
      'label' => 'Add muted background',
      'value' => $field->input('bgmuted', 'checkbox'),
    ]);

    if($block->template == 'your-block-type-template') {
      $settings->add(...);
    }
  }
);
```

<div class="uk-alert uk-alert-primary">Note: If you get an error like "Argument #1 ($settings) must be of type BlockSettingsArray..." make sure to add the "use" statements on top of your file!</div>

Instead of if/else in the default settings callback you can also remove default settings in a specific block:

```php
// add this to your block's php file
public function settingsTable(RockFieldsField $field) {
  $settings = $this->getDefaultSettings();

  // hide one of the global settings
  $settings->remove('name=foo');

  // add a custom setting to this block
  $settings->add([
    'name' => 'bar',
    'label' => 'BAR-setting',
    'value' => $field->input('bar', 'select', [...]),
  ]);

  // you can also add settings on top:
  $settings->prepend([
    'name' => 'baz',
    'label' => 'BAZ-setting',
    'value' => $field->input('baz', 'select', [...]),
  ]);

  return $settings;
}
```

## Custom Settings Wrapper

You can set options for the wrapping Inputfield in the info() method of your block:

```php
public function info() {
  return [
    ...
    'settings' => [
      'label' => 'Settings for this block',
      'icon' => 'check',
      'collapsed' => Inputfield::collapsedYes,
    ],
  ];
}
```

<img src=wrapper.png class=blur>