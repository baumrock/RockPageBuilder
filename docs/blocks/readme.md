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

## Drag & Drop Sortable

If you want to make your sections sortable via drag and drop all you have to do is to add the `sortable` attribute to the wrapping element:

```php
<main sortable>
  <?= $rockpagebuilder->render() ?>
</main>
```

For details about sortable <a href=../sortable/>see the docs</a>.

## Block title and description

You can add a title and description of your block in the info() method:

```php
public function info()
{
  return [
    'title' => 'Spacings',
    'description' => 'Please see docs about Block Spacings!',
    'icon' => 'cube',
    ...
  ];
}
```

This is how it will look like when adding a new block:

<img src=description.png class=blur>

## Block Field Defaults

You can provide defaults for your block-fields, for example this would pre-fill the `title` field of every `Gallery` block with `Some great images`:

```php
class Gallery extends Block
{
  public function info()
  {
    return [
      'title' => 'Gallery',
      'defaults' => [
        'title' => 'Some great images',
      ],
    ];
  }
}
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

Note that using icons can be preferable to images for two reasons:

1. It's less effort
2. Icons are abstract. If you build a block where the user can choose from three different styles then an image will only show one version of that block whereas an icon shows all three styles in an abstracted way.

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

## Reusing Blocks

If you use multiple RockPageBuilder fields in your project you can reuse blocks in other fields by just creating an empty PHP file with the name of the block in the folder of the field. Alternatively just click on the name of the block that you want to reuse when adding a block via GUI:

<img src=reuse.png class=blur>

## Removing Blocks

You can remove blocks either by deleting the block's folder (eg `/site/templates/RockPageBuilder/blocks/Demo`) and doing a modules refresh or you can go to the module's settings page and choose the block to delete.
