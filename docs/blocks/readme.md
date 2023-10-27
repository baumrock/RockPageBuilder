# Blocks

Blocks are actually ProcessWire pages with a corresponding template and a custom pageclass.

Every block lives in `/site/templates/RockPageBuilder/[fieldname]` and comes with the following files:

* A PHP file that holds all the settings and business logic
* A view file that defines the frontend markup
* An optional stylesheet

<img src=folders.png class=blur>

## Creating Blocks

Please see the <a href="../quickstart/#creating-your-first-block">quickstart guide</a>.

## Rendering Blocks

By default RockPageBuilder will create the field `rockpagebuilder_blocks` that you can add to any page. When using this field you can render the content of this field like this:

```php
echo $rockpagebuilder->render();
```

If no content exist on that page it will render a plus to add content:

<img src=plus.png class=blur>

You can create as many RockPageBuilder fields as you want. If you added a RockPageBuilder field called `foo` you can render it like this:

```php
echo $page->foo->render();
```

By default RockPageBuilder will render a "plus" icon on empty fields. If you don't want that you can provice `false` as render parameter:

```php
echo $rockpagebuilder->render(false);
echo $page->foo->render(false);
```

## Custom Block Labels

If you don't like the default label of your block, simply define your own in the block's PHP file:

```php
public function getLabel()
{
  return "My Demo Label: " . $this->title;
}
```

<img src=label.png class=blur>

Note that you can return any content you want - you can even return the content of a richtext field. RockPageBuilder will automatically remove all tags and truncate it to an appropriate length.

### HTML Labels

You can even get creative and add helpful HTML to your block labels - like in this example that shows the differently colored sections:

<img src=label-html.png class=blur>

<img src=label-result.png class=blur>

```php
public function getLabel()
{
  // get the block's color name and css style attribute
  // this is a project-specific implementation that will not work for you!
  $style = $this->colorStyle();
  $name = $this->colorName();

  return $this->html(
    "<span $style><i class='fa fa-paint-brush'></i> $name</span>"
  );
}
```

## Block Settings

It's very common that you want to provide some options for your block, for example for choosing between different layout variations or for changing the size of the preview thumbnails of an image gallery block.

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

### Adding default settings

Sometimes you want to have the same setting on all or almost all blocks of your project. Rather than copy and pasting the same settings from block to block you can define global settings for all blocks and add additional settings to some blocks later:

```php
// in site/ready.php
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

### Custom Settings Wrapper

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

## Block Thumbnails

RockPageBuilder makes it simple to quickly create nice looking thumbnails for your blocks. You can either choose to use icons, which is the quickest option, or you can create and upload your own thumbnail image.

### Icon

To use an icon as thumbnail all you have to do is to set the `icon` property in the info() method. You can use any of the Font Awesome 4 icon names: https://icones.js.org/collection/fa


```php
public function info() {
  return [
    ...
    'icon' => 'check',
  ];
}
```

<img src=icon.png class=blur>

### Image

If your block's PHP file is `Demo.php` simply add an Image called `Demo.png|jpg|svg` to that folder and RockPageBuilder will display that thumbnail instead of the icon:

<img src=thumb.png class=blur>

Obviously it might not be the best idea to mix icons and images like in the screenshot above. Besides that images also have another drawback: If you provide style variations for your block in the block's settings you can only show a preview of one setting and that might confuse the user, whereas an icon is more abstract.

It looks a lot better if you use images for all blocks:

<img src=thumbs.png class=blur>

And it might look even better when using real world screenshots as thumbnails:

<img src=real.png class=blur>

### Special Names

RockPageBuilder ships with thumbnails for the following block names: `Gallery`, `Headline`, `Hero`, `Quote`, `Text`, `TextImage`. See `site/modules/RockPageBuilder/buttons/`

## Grouping Blocks

You can define the `group` and a `groupSort` property for every block in its `info()` method:

```php
public function info()
{
  return [
    ...
    'group' => 'Demo Group',
    'groupSort' => 1,
  ];
}
```

By default blocks will be sorted by their name in alphabetical order. You can change that by manually providing a `groupSort` parameter where a lower numbers appear first. The default sort value is 100.

This groups will then be used in the UI when adding blocks from the frontend:

<img src=groups.png class=blur>

Groups will not be used on the backend to make the UI more compact.

## Removing Blocks

You can remove blocks either by deleting the block's folder (eg `/site/templates/RockPageBuilder/blocks/Demo`) and doing a modules refresh or you can go to the module's settings page and choose the block to delete.
