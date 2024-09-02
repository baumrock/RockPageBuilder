# Custom Block Stubs

## Overview

RockPageBuilder allows you to create custom "stub" files for generating new blocks. These stubs serve as boilerplate code, providing a consistent starting point for block creation. By default, the module offers two versions of stubs:

1. A commented version for beginners
2. An advanced version without comments for experienced users

## The Problem

Every website has unique requirements, such as different CSS frameworks (Bootstrap, TailwindCSS, etc.) or project-specific markup. The default stubs may not always align with your project's needs, leading to:

- Time-consuming cleanup and class modifications for each new block
- Potential oversights in adding project-specific classes or structures
- Inconsistencies across blocks, possibly introducing bugs

## The Solution: Custom Stubs

To address these issues, RockPageBuilder allows you to create custom stub files tailored to your project's needs.

### How to Implement Custom Stubs

1. Create a directory: `/site/templates/RockPageBuilder/stubs/`
2. Add your custom stub files to this directory:
   - For PHP files: `.Block.php` (note the leading dot)
   - For view files: `.Block.latte` or `.Block.view.php`

### Example: Custom PHP Stub

Create a file `/site/templates/RockPageBuilder/stubs/.Block.php`:

```php
// site/templates/RockPageBuilder/stubs/.Block.php
<?php

namespace RockPageBuilderBlock;

use RockPageBuilder\Block;

class {name} extends Block {

  const prefix = "rpb_{namelower}_";

  public function info() {
    return [
      'title' => '{name}',
    ];
  }

  public function migrate()
  {
    $rm = $this->rockmigrations();
    $rm->migrate([
      'fields' => [],
      'templates' => [
        $this->getTplName() => [
          'fields-' => [],
        ],
      ],
    ]);
  }
}
```

### Example: Custom View Stub (Latte)

Create a file `/site/templates/RockPageBuilder/stubs/.Block.latte`:

```latte
{* site/templates/RockPageBuilder/stubs/.Block.latte *}
<section
  class="{cls} tm-block {$site->bgClass}"
  {alfred($block)}
>
  <div class='uk-container text-auto'>
    TBD
  </div>
</section>
```

This example uses UIkit classes and includes a generic `tm-block` class and a `bgClass` from the global `$site` object.

## Benefits

By using custom stubs, you can:

1. Ensure consistency across all blocks in your project
2. Reduce the time spent on initial block setup
3. Minimize the risk of forgetting project-specific classes or structures
4. Tailor the initial block code to your project's specific needs and coding standards

Remember to adjust these custom stubs as your project evolves to maintain their relevance and effectiveness.
