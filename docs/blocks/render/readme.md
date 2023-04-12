# Rendering Blocks

## From other Pages

If you want to render a block from another Page you can do so using the `renderBlock()` method.

For example you might want to have a page structure like this:

```
- Podcasts 2023
  |- Podcast 1
  '- Podcast 2
- Podcasts 2022
  |- Podcast 1
  '- Podcast 2
```

And you might have several sponsors for each year that you want to show on all podcast pages. Assuming that your Block-Template is called `sponsors` you could add a sponsors block to the parent page and use it on all children:

```php
// $page is the current podcast page, eg "Podcast 1"
$year = $page->parent;
// $year->blocks() is the short version of $page->rockpagebuilder_blocks
$block = $year->blocks()->get("template=sponsors");
if($block->id) echo $block->renderBlock();
```
