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

## Benefits using RockMatrix

Benefits for users

* You will get a lot more flexibility for your page layouts
* You will get a lot cleaner user interfaces with less other plugins or workarounds, eg: https://user-images.githubusercontent.com/6616448/178727428-c4c72906-ad57-45cf-ac82-49320edb3b99.png
* You will get meaningfol labels of your content blocks (compared to only showing the type of the block)

Technical benefits (compared to other solutions and workarounds)

* You will get cleaner code (OOP)
* Since all blocks are ProcessWire Pages under the hood they are super easy to handle via API or via RockMigrations

## Limitations

Seems that showIf does not work with RockMatrix? MPN AnchorHeadline

## RockMatrix vs. RepeaterMatrix

Like RepeaterMatrix RockMatrix extends the core Repeater Fieldtype, but the concept is very different. As mentioned above every block of a RockMatrix is a custom Page (that's the same with RepeaterMatrix) having a custom template (that's not the case with RepeaterMatrix) and also having a custom PageClass (that's also not the case with RepeaterMatrix). RepeaterMatrix on the other hand creates ONE template for all your matrix blocks and then hides or shows the fields that you have defined via the admin interface. That means RepeaterMatrix creates less templates but on the code side you'll end up with ONE page type for MANY block types.

That means that you need to use hooks to customize your pages which has many disadvantages in my opinion. RockMatrix on the other hand creates a custom page type for every block type which makes it super convenient to code:

```php
class BlockFoo extends \RockMatrix\Block {
  public function foo() {
    return 'foo';
  }
}
class BlockBar extends \RockMatrix\Block {
  public function bar() {
    return 'bar';
  }
}
// vs
$wire->addHookMethod('RepeaterMatrixPage::foo', function($event) {
  $page = $event->object;
  if($page->type !== 'matrixTypeFoo') return;
  $event->return = 'foo';
});
$wire->addHookMethod('RepeaterMatrixPage::bar', function($event) {
  $page = $event->object;
  if($page->type !== 'matrixTypeBar') return;
  $event->return = 'bar';
});
```

Another difference is that every Block in RockMatrix comes with several helper methods that are handy for customizing the output on your frontend. For example you can add classes on every first matrix item or you can style even blocks differently than odd items (for example to swap images from left to right).

```php
if($block->isEven()) echo "<h1 class='uk-text-right'><?= $block->title ?></h1>";
else echo "<h1><?= $block->title ?></h1>";
```

Some available methods are:

* $block->isEven()
* $block->isOdd()
* $block->isFirstMatrixItem()
* $block->isLastMatrixItem()
* $block->isLastMatrixItem()
* $block->isEvenType()

Note that isEvenType checks if the item has an odd index but only counts blocks of the same type that are in a row and not interrupted by a block of another type, eg:

```
A
B
C
C -> true
C
A
B
B -> true
```

## Migrations

RockMatrix relies havily on RockMigrations. Migrations are triggered automatically via RockMatrix.module.php on modules::refresh.

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

### Block info

You can define several important settings for every block in the `infot()` method of the block.

```php
  public function info() {
    return [
      'icon' => 'bullhorn',
      'title' => 'Jobs',
      'color' => 'PaleGreen',
      'sort' => 900,
      'show' => function($page) {
        // show the button to add this block only on page having title 'Jobs'
        return $page->title == 'Jobs';
      },
    ];
  }
```

### Block settings

You can easily define settings for every block. While you could also use regular fields for block settings this method is a lot more efficient and streamlined. The feature uses RockFields for creating the fields. That means fields defined for block settings are runtime fields and don't exist in the database (so you'll also not find them in the fields editor). Also those fields are wrapped in a single Inputfield at the bottom of every block so they will save a lot of unused screen space compared to regular PW fields.

Another benefit is that you can define global settings for your project and then hide or add other settings only for some blocks.

#### Setting block settings via API

To add settings to your block simply install the RockFields module and add a method `settingsTable` to your block:

```php
// add this to your block's php file
public function settingsTable(RockFieldsField $field) {
  $settings = $this->matrix()->cloneBlockSettings();
  $settings->add([
    'name' => 'blockpadding',
    'label' => 'Block-Padding',
    'value' => $field->input('blockpadding', 'radios', [
      '*s' => 'small padding',
      'm' => 'medium padding',
      'l' => 'large padding',
    ]),
  ]);
  return $settings;
}
```

Often you want to define global settings for all blocks and extend those settings on some blocks. You can do so using a hook:

```php
// in site/ready.php
/** @var RockMatrix $matrix */
$matrix = $this->wire('modules')->get('RockMatrix');
$matrix->addHookBefore("cloneBlockSettings", function($event) {
  /** @var BlockSettingsArray $settings */
  $settings = $event->object->blockSettings;
  /** @var RockFieldsField $field */
  $field = $event->arguments(0);
  $settings->add([
    'label' => 'global setting',
    'value' => $field->input('global_setting', 'radios', [
      '*foo' => 'foo label',
      'bar' => 'bar label',
    ]),
  ]);
});
```

#### Block Settings Options

You can set options for the settings wrapper field in the info() method of your block:

```php
public function info() {
  return [
    ...
    'settings' => false, // no settings field for this block

    'settings' => [
      'label' => 'Settings for this block',
      'icon' => 'check',
      'collapsed' => Inputfield::collapsedNo,
    ],
  ];
}
```

#### The old way of doing it (for better understanding)

```php
public function settingsInput(RockFieldsField $field) {
  return $field->table([
    'Headline' => $field->input("headline"),
    'Whatever' => $field->input("whatever"),
  ]);
}
public function settingsSleep(RockFieldsField $field) {
  return [
    $field->getInputArray("headline"),
    $field->getInputArray("whatever"),
  ];
}
```

If settingsInput returns a string it will be used as value of a markup field. If you provide an array, it will be used as inputfield where you can define label, icon, collapsed state etc:

```php
public function settingsInput(RockFieldsField $field) {
  return [
    'label' => 'My Settings field',
    'icon' => 'check',
    'value' => $field->table([
      'Headline' => $field->input("headline"),
      'Whatever' => $field->input("whatever"),
    ]),
    'collapsed' => Inputfield::collapsedNo,
  ];
}
```

You have several options to access these settins on the frontend:

```php
// in your block's view file you can access settings via the $settings variable:
if($settins->mySetting == 'foo') echo 'Foo setting is set!';

// get a single setting and set a default value
$mySetting = $block->settings('mySetting', 'default value');

// same as above but different syntax
$mySetting = $settings->mySetting ?: 'default value';
```

## Translations

When working with frontend template engines like latte one drawback is that you can't use ProcessWire's built in transation functions like `__('your translatable string')`. That's because ProcessWire will only look for such stings in PHP files and also latte has problems understanding those function calls. Since every block has a PHP file we can simply put all translatable strings into that file and access them from the latte file via the `$block->x()` helper function:

```php
<?php namespace RMBlock;
use RockMatrix\Block;
class Demo extends Block {

  public function info() {
    return [
      'icon' => 'check',
      'title' => 'demo block',
    ];
  }

  public function translations() {
    return [
      'my_foo_string' => $this->_('I am the translatable foo string'),
      'my_bar_string' => $this->_('I am the translatable bar string'),
    ];
  }
}
```

And in your template file:

```html
<div style="padding: 50px; border: 2px solid blue;">
  <h1>{$block->x('my_foo_string')}</h1>
  <p>{$block->x('my_bar_string')}</p>
</div>
```

## Render content

The content of the field can be rendered via the `render()` method of the field data object. This calls the `render()` method of each block. If you just `echo` the field value it will show the ids of the pagearray (which is the string representation of the pagearray and is necessary for usage on selectors).

### Render blocks

The markup for rendering your block can either be defined as `render()` method of your block or you can create a view file for your block e.g. `/site/assets/blocks/Slider.view.php`.

You can also use the `latte` templating engine by Nette, see https://latte.nette.org/en/syntax

This is an example `Image.latte` file:

```html
{if $settings->float}
<img data-src="{$block->src()}" alt="{$block->alt()}" uk-img
  class="uk-float-{$settings->align}">
{else}
<section class="rmx-image uk-padding uk-text-{$settings->align}" uk-lightbox>
  <a href="{$block->src(1600,1600)}" data-caption="{$block->alt()}" n:tag-if="$settings->lightbox">
    <img data-src="{$block->src()}" alt="{$block->alt()}" uk-img>
  </a>
</section>
{/if}
```

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
$page->getUnformatted('your_matrix_field')
  ->add(1035) // add block id 1035 to this field
  ->save();
```

You can also add new blocks to your field data:

```php
$field = $page->getUnformatted('your_matrix_field');
$field->add('your-block-tpl', [
  // you can prepopulate fields of the block
  'headline' => 'This is a headline',
  'body' => '<p>Lorem Ipsum</p>',
);
$field->save();
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

## Content-Only fields

Quite often matrix blocks do only have one single field which has the same label as the block. As this would be redundant information and eat up quite some space on the screen RockMatrix has a shortcut to easily hide the label of such fields and reduce the field's padding:

```php
$rm->migrate([
  'fields' => [
    self::field_body => [
      'type' => 'textarea',
      'rmx-nolabel' => true,
      ...
```

## Widget Concept

RockMatrix will create a field `rockmatrix_widgets` on the `home` template. There you can add all blocks that are available on your site as widgets. Simply add an empty(!) file with the corresponding name in the folder `/site/asset/RockMatrix/rockmatrix_widgets`. For example if you had a block called "MyBlock" you would add the empty file `/site/assets/RockMatrix/rockmatrix_widgets/MyBlock.php`.

### Selecting widgets from other pages



### Rendering widgets from template files

Sometimes you want to create a widget that the user can edit in the backend, but you as a developer want to define where the widget gets rendered. For example you could create a global `Team` widget that you want to render on all `job` pages. In the job's page template file you can render this widget like this:

```php
$rockmatrix->widgets('Team')->render()
```

When editing the widget, it will show a warning on which pages the widget will be displayed. RockMatrix can not know about the code you added to the template file, therefore you need to tell RockMatrix about it:

```php
// in the widget's init() method
// in our example this is in Team.php (the php file of the Team widget)
$this->addHookAfter("RockMatrix::getWidgetPages", function($event) {
  $pages = $event->return;
  $pages->add($this->wire->pages->find("template=job"));
  $event->return = $pages;
});
```
