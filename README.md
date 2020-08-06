# RockMatrix

Repeater Matrix for ProcessWire, referenced as RM in this readme.

## Setup

### Setting up new Blocks

Blocks need to extend `\RockMatrix\Block` and have namespace `RockMatrixBlock`:

```php
<?php namespace RockMatrixBlock;
class Headline extends \RockMatrix\Block {
}
```

Add blocks in init():

```php
// site/init.php (or in module init())
if($matrix = $this->modules->get('RockMatrix')) {
  $matrix->addBlocks("/your/path/to/blocks");
}
```
