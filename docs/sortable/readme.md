# Drag & Drop Sortable

RockPageBuilder has great support for frontend sorting of your sections and other page elements. It supports nested sorting, saves all changes instantly and it is extremely easy to setup:

## Making blocks sortable

The most comman use case is to make all your blocks (page sections) sortable on the frontend. This can be done by adding the `sortable` attribute to the dom element that wraps all pagebuilder blocks:

```php
<main sortable>
  <?= $rockpagebuilder->render() ?>
</main>
```

## Making repeater items sortable

RockPageBuilder's drag and drop sorting does also support nested sorting. Often we have blocks that have multiple child elements, like an accordion. We want to be able to sort the whole block but also to sort the individual list items.

Again, all you have to do is to add the `sortable` attribute to the wrapper that wraps your accordion items:

```php
<ul sortable>
  <?php foreach($block->listitems() as $item): ?>
  <li><?= $item->title ?></li>
  <?php endforeach; ?>
</ul>
```

I'm quite sure this will blow your client's minds. 🤯

Happy sorting! 🤩🚀
