# Presets

It might be the case that you want to guide the user which blocks to add on certain pages. You can use so called "presets" to do this.

<img src=https://i.imgur.com/0oXeNb7.gif class=blur>

Just add a hook that returns an array of block-types in your /site/ready.php file:

```php
wire()->addHookAfter(
  'RockPageBuilder::getPresets',
  function (HookEvent $event) {
    $page = $event->arguments(0);
    $field = $event->arguments(1);
    if ($page->template != 'blogitem') return;
    if ($field->name != 'rockpagebuilder_blocks') return;
    $event->return = [
    'Demo' => [
      'Alert',
      'Features',
      'Features',
      'Videos',
    ],
  ];
});
```
