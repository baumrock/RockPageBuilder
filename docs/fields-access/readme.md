# Accessing Fields

All fields of your block will be available to your `$block` variable just like usual:

```php
echo $block->your_field_name;
echo $block->edit('your_field_name');
```

## Method Syntax

RockPageBuilder comes with a helper to make working with long fieldnames easier and less verbose. The reason for that is that when I create new blocks I always prefix all fields with the block template name. That way I can be sure that when I copy that block to another project the fieldnames don't collide with existing fields.

The problem here is that we end up with long fieldnames like this: `rockcommerce_invoice_pdf`.

When using RockPageBuilder you can do this:

```php
echo $block->pdf();
```

This feature is actually a feature of RockMigrations' MagicPages module. You can also pass parameters to request a different state of the field:

```php
$block->pdf(); // frontend editable field
$block->pdf(1); // using ->getFormatted()
$block->pdf(2); // using ->getUnformatted()
```

Note that the method call has to be the last part of the string after the final underscore:

```php
// field foo_bar_baz
$block->baz()

// field foo_something
$block->something()
```