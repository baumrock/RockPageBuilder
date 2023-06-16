# Block Settings

<div class="uk-alert uk-alert-danger">Block Settings will be rewritten soon!</div>

## Setting Defaults

```php
$rockpagebuilder->defaultSettings(function ($settings, $field, $block) {
  $settings->add([
    'name' => 'anchor',
    'label' => 'Anker',
    'value' => $field->input('anchor'),
  ]);
});
```