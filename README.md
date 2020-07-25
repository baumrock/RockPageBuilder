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

RockMatrix will automatically load all blocks on `ready()`, but to make RockMatrix know about your blocks you need to tell it the directory before `ready()`, eg on `init()` of your module:

```
// init()
$wire->addHookAfter