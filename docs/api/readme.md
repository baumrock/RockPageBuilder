# RockPageBuilder API Usage

The RockPageBuilder module provides an API for manipulating blocks within a RockPageBuilder field. You can add, remove, move or clone blocks programmatically using this API. Below are examples of how to use the API for these operations.

## Adding a Block

To add a block to a RockPageBuilder field, you can use the `add()` method on the `FieldData` object. You need to specify the type of the block and optionally, any data you want to initialize the block with.

```php
// Assuming 'rockpagebuilder_blocks' is your RockPageBuilder field
$blocks = $page->getUnformatted('rockpagebuilder_blocks');

// Add a block by template name
$block = $blocks->add('Text', [
  'title' => 'My new headline',
  'body' => '<p>foo</p><p>bar</p>',
]);

// save changes
$page->setAndSave('rockpagebuilder_blocks', $blocks);
```

Reference: `FieldData::add()` method in `FieldData.php`.

## Removing a Block

## Cloning a Block

TBD

## Conclusion

The RockPageBuilder API provides flexible methods to manipulate blocks within a field programmatically. Always ensure you have the correct field and block references to avoid unintended modifications.
