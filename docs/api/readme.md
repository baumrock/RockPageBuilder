# API

RockPageBuilder provides a powerful API for managing blocks programmatically. This allows for advanced manipulation of content, including moving, cloning, copying, and adding blocks. Below is an overview of how to interact with these capabilities through the API.

## Finding Blocks

All blocks of a page are stored in a `FieldData` object. You can get it just like grabbing any other ProcessWire field:

```php
$blocks = $page->rockpagebuilder_blocks;
```

The `FieldData` object is a WireArray, so you can use all its methods to find, filter, and manipulate the blocks, for example:

```php
$blocks->find('title=My Headline');
$blocks->count();
$blocks->find('type=Gallery');
```

## Moving Blocks

To move a block to another page, you can use the move() method on the block object. This method requires the target page and field:

```php
$block = $pages->get(123);
$block->move($pages->get("/my/target/page"), "my_target_field");
```

## Cloning Blocks

Cloning blocks is useful when you want to duplicate content within the same page or across different pages. Use the clone() method on the block object:

```php
$block = $pages->get(123);
$clone = $block->clone();
```

## Copying Blocks to other Pages

If you want to clone and move with one command, you can do this:

```php
$block = $pages->get(123);
$block->copyTo($pages->get("/my/target/page"), "my_target_field");
```

## Adding Blocks

### High-level helper (recommended for bots / migrations)

```php
$block = rockpagebuilder()->addBlock($page, 'Text', [
  'title' => 'My new headline',
  'rpb_text_body' => '<p>foo</p><p>bar</p>',
  'settings' => [
    'muted' => 0,
  ],
]);
```

This sets `noTemp`, applies settings, and saves the field so the block is visible on the frontend immediately.

You can also call `addPageBlock()`, `updatePageBlock($blockOrId, $data)`, and `removePageBlock($page, $blockId)` directly.

### Low-level FieldData API

```php
// Assuming 'rockpagebuilder_blocks' is your RockPageBuilder field
$blocks = $page->getUnformatted('rockpagebuilder_blocks');

// Prevent setting the "temp" flag
rockpagebuilder()->noTemp = true;

// Add a block by template name
$block = $blocks->add('Text', [
  'title' => 'My new headline',
  'body' => '<p>foo</p><p>bar</p>',
]);

// save changes
$page->setAndSave('rockpagebuilder_blocks', $blocks);
```

Reference: See `FieldData::add()` method in `FieldData.php`.

## Saving Settings

If you need to change (or import) settings of a block you can do so like this:

```php
$block->saveSetting('image-size', 'S');
```

Note that you don't need to save the block, because settings are saved as page `meta()` data and that data is instantly written to the database.

When using `addBlock($page, …)` / `addPageBlock()` / `updatePageBlock()`, pass settings in the data array under the `settings` key.
