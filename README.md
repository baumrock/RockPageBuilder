# RockMatrix

Repeater Matrix for ProcessWire, referenced as RM in this readme.

## Setup

### Setting up the Inputfield

Every field needs to know

* Where to add new pages (the new page parent)
* Allowed Blocks

### Setting up new Blocks

Blocks need to extend `\RockMatrix\Block` and have namespace `RockMatrixBlock`:

```php
...
```

Add blocks in init():

```
if($matrix = $this->modules->get('RockMatrix')) {
  $matrix->addBlocks(__DIR__."/blocks");
}
```
