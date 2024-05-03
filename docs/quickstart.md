# Quickstart

One huge selling point of RockPageBuilder is that it injects a GUI into your frontend to make the content editing extremely easy and intuitive for your clients.

Show that you potential clients and they will be blown away how easy your sites can be edited! 🤩🤯🚀

## Frontend GUI

<div class='uk-alert uk-alert-danger'>Note: To use Frontend Editing you need to install the core Frontend Editing module and make sure your user's have the `page-edit-front` permission!</div>

As soon as you are logged in and hover over a block you will get the UI for all available block actions:

<img src=frontend.png class=blur>

1. Add a new block above
2. Add a new block below
3. Edit this block
4. Clone block
5. Move block
6. Turn block into a widget (to reuse it across several pages)
7. Delete block
8. Development shortcuts to open markup/logic/style-file that belongs to that block in VSCode (I love that feature 😎)
9. Doubleclick text elements for instant frontend editing

Note: For editing the block you can also doubleclick anywhere inside the block.

## Backend GUI

The backend interface is straightforward too. It is necessary for some features that can't be shown on the frontend - for example you can hide blocks to temporarily disable them on the frontend and since they are hidden on the frontend we can't show a UI there 🤪

<img src=backend.png class=blur>

On the screenshot you see three features:

1. Action icons where you can - for example - change the fields of your block or hide/unhide or delete it.
2. An example of a hidden block.
3. A plus icon where you can create new block types.

## Creating your first block

Creating a new block is very easy:

1. Click on the plus icon in the backend (see screenshot above)
1. Choose a name for your block and hit OK. We use `Demo` for this example:

    <img src=demo-add.png class=blur>

1. Your block is ready to be used

    <img src=demo-ready.png class=blur>

1. Add one block, fill in some content and save the page

    <img src=demo-fill.png class=blur>

1. View your page on the frontend. Your block will already be usable and show you which file you have to modify in order to change the markup:

    <img src=demo-frontend.png class=blur>

1. Open that file in your code editor and add whatever markup you need.

You can use this example code (without the comments, of course):

```js
// in every block you have access to the $block variable
// it's a $page with superpowers 😉 more on that later...

<section
  // optional class to style all demo blocks
  // you can also use it to target child elements:
  // .rpb-demo h1 { color: red; }
  class="rpb-demo"

  // add ALFRED - that's the gui for frontend editing
  <?= alfred($block) ?>
>
  // show the title field of the block as h1 headline
  // note that we are using title(), not title! this is
  // all it takes to make the field frontend editable 💪
  <h1><?= $block->title() ?></h1>
</section>
```

Here the same code without comments ready to copy:

```php
<section
  class="rpb-demo"
  <?= alfred($block) ?>
>
  <h1><?= $block->title() ?></h1>
</section>
```

<div class="uk-alert uk-alert-success">Congratulations! You can now start building any kind of block just with some basic HTML and PHP knowledge!</div>
