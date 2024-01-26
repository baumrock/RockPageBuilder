# Blocks with JS

Let's say you want to build a block that needs some JavaScript to work - for example an interactive map or an interactive 3D visualisation.

All you have to do is to add the corresponding JavaScript file to your block's assets:

```
/MyMap
/MyMap/MyMap.js
/MyMap/MyMap.latte
/MyMap/MyMap.php
```

When using RockFrontend for your Frontend RockPageBuilder will automatically load that script with the following markup in your page's `<head>`:

```html
<script src="/site/templates/RockPageBuilder/blocks/MyMap/MyMap.js?m=1706289464" defer=""></script>
```

As you can see this will set the `defer` attribute so that the script is fired once the whole DOM is loaded.

What if I want to load a minified file? Glad you are asking!

## Minification

If a minified version of your file exists with the name `MyMap.min.js` then RockFrontend will load this file instead of the unminified `MyMap.js`.

You can either use your own frontend build tools to create the minified file or you can use RockFrontend as well:

```php
// /site/templates/RockPageBuilder/blocks/MyMap/MyMap.php
public function init(): void
{
  $this->rockmigrations()->minify(__DIR__ . "/MyMap.js");
}
```

This line of code will automatically watch `MyMap.js` for changes and if the file changed it will create a new minified file in the same folder.

Note that RockMigrations will only do this if you are logged in as superuser and debug mode is ON. This is to make sure that we don't get any performance penalty on live sites!
