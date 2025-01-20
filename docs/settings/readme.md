# Block Settings

It's very common that you want to provide some options for your block, for example for choosing between different layout variations or for changing the size of the preview thumbnails of an image gallery block.

## RockFields

You can create a field for all those settings, if you want. But the better option is to use the `RockFields` module that ships with RockPageBuilder.

It allows you to create several fields at runtime without creating those fields in the database. Neat!

A simple checkbox would look like this:

```php
[
  'name' => 'demo-checkbox',
  'label' => 'Demo Checkbox',
  'value' => $field->input('demo-checkbox', 'checkbox'),
];
```

## Adding Settings (Globally)

A setting that you add to one block is often also needed by other blocks. That's why we recommend that you define your settings globally and then just add them to your blocks as needed.

To add global settings, create the file `/site/templates/RockPageBuilder/settings.php` with content like this:

```php
<?php

namespace ProcessWire;

return [
  [
    'name' => 'demo-checkbox',
    'label' => 'Demo Checkbox',
    'value' => $field->input('demo-checkbox', 'checkbox'),
  ],
  [
    'name' => 'style',
    'label' => 'Style Variation',
    'value' => $field->input('style', 'select', [
      '*one' => 'Style One (image in the background)',
      'two' => 'Style Two (image on the right)',
      'three' => 'Style Three (centered)',
    ]),
  ]
];
```

## Adding Global Settings to a Block

Every Block can then `use` any of the globally defined settings like this:

```php
public function settingsTable(\ProcessWire\RockFieldsField $field)
{
  return $this->getGlobalSettings($field)
    ->use([
      'style',
      'demo-checkbox',
    ]);
}
```

Please note that by defining a different order in the `use` method we flipped the order of the settings! Settings will always be rendered in the order that you define in the `use` method, not in the order how they are defined in the PHP file.

## Adding Custom Settings to a Block

You can combine global settings with custom settings by using the `add` method:

```php
public function settingsTable(\ProcessWire\RockFieldsField $field)
{
  $settings = $this->getGlobalSettings($field)
    ->use([
      'style',
      'demo-checkbox',
    ]);

  // add: add a new settings field at the end
  $settings->add([
    'name' => 'custom-checkbox',
    'label' => 'Custom checkbox',
    'value' => $field->input('custom-checkbox', 'checkbox'),
  ]);

  // prepend: add checkbox at first position
  $settings->prepend([
    'name' => 'prepended-custom-checkbox',
    'label' => 'Prepended custom checkbox',
    'value' => $field->input('prepended-custom-checkbox', 'checkbox'),
  ]);

  // insertAfter/insertBefore: add checkbox after/before other setting
  $newItem = $settings->getItem([
    'name' => 'inserted-custom-checkbox',
    'label' => 'Inserted custom checkbox',
    'value' => $field->input('inserted-custom-checkbox', 'checkbox'),
  ]);
  $settings->insertAfter($newItem, $settings->get('style'));

  return $settings;
}
```

### Using non-global settings

```php
public function settingsTable(\ProcessWire\RockFieldsField $field)
{
  $settings = $this->getSettings();

  $settings->add([
    'name' => 'demo-checkbox',
    'label' => 'Demo Checkbox',
    'value' => $field->input('demo-checkbox', 'checkbox'),
  ]);

  return $settings;
}
```

## Multilingual Settings

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

## Conditionals (showIf)

The `showIf` feature allows you to conditionally display settings based on the value of other settings within the same block. This can be particularly useful for creating dynamic and interactive settings interfaces where the visibility of certain options depends on the configuration of others.

To use `showIf`, you need to specify it as an attribute in the settings array of your block's `settingsTable` method. The `showIf` attribute takes a string that represents a condition. This condition is composed of the name of the setting to watch, an equals sign `=`, and the value that triggers the visibility of the current setting.

```php
public function settingsTable(\ProcessWire\RockFieldsField $field)
{
  return $this->getDefaultSettings($field)
    ->add([
      'name' => 'showImages',
      'label' => 'Show Items with Images',
      'value' => $field->input('showImages', 'checkbox'),
    ])->add([
      'name' => 'noBackground',
      'label' => 'Do not add blurred background behind images',
      'value' => $field->input('noBackground', 'checkbox'),
      'showIf' => 'showImages=1',
    ]);
}
```

## Example Settings

<img src=settings.png class=blur>

`label: settings.php`
```php
return [
  [
    'name' => 'demo-checkbox',
    'label' => 'Demo Checkbox',
    'value' => $field->input('demo-checkbox', 'checkbox'),
  ],

  [
    'name' => 'demo-text',
    'label' => 'Demo Text Field',
    'value' => $field->input('demo-text', 'text'),
  ],

  [
    'name' => 'demo-select',
    'label' => 'Demo Select Field',
    'value' => $field->input('demo-select', 'select', [
      'foo' => 'foo value', // the star marks the default option
      'bar' => 'bar value',
    ]),
  ],

  [
    'name' => 'demo-radios',
    'label' => 'Demo Radios Field',
    'value' => $field->input(
      'demo-radios',
      // use either "radios" or "radios-inline"
      'radios', [
        'foo' => 'foo value', // the star marks the default option
        '*bar' => 'bar value',
      ]
    ),
  ],

  // The multi-checkbox field as a slightly different api on the frontend
  // because it can hold multiple values at once. For easier access the plain
  // array is converted to a WireData() object and you can use PHP8 syntax
  // to check for selected state:
  // $data = $block->settings('demo-checkboxes');
  // if $data?->foo echo "foo checkbox is selected";
  // if $data?->bar echo "bar checkbox is selected";
  [
    'name' => 'demo-checkboxes',
    'label' => 'Demo Checkboxes Field',
    'value' => $field->input(
      'demo-checkboxes',
      // use either "checkboxes" or "checkboxes-inline"
      'checkboxes', [
        'foo' => 'foo value', // the star marks the default option
        '*bar' => 'bar value',
      ]
    ),
  ],
];
```

## Adding default settings (DEPRECATED)

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

<div class="uk-alert">Note: If you get an error like "Argument #1 ($settings) must be of type BlockSettingsArray..." make sure to add the "use" statements on top of your file!</div>

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
