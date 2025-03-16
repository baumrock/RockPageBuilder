# Setup

You can use RockPageBuilder for new projects but also for existing ones! The module only needs a few steps to setup.

If this is the first time using RockPageBuilder I recommend installing the [RockFrontend Site Profile](https://github.com/baumrock/site-rockfrontend/releases) and starting from there. Once you know how the module works and what is necessary for it to work it will be easier for you to add RockPageBuilder to any other setup as well.

## Installation

1. Install ProcessWire using the [RockFrontend Site Profile](https://github.com/baumrock/site-rockfrontend/releases)
2. Install all dependencies
3. Install RockPageBuilder - it should instantly work on the backend
4. (optional) Add RockPageBuilder to your frontend

### Installing RockPageBuilder (Backend)

Once you have a ProcessWire installation you can install RockPageBuilder via the module interface. Once installed you can also install one of the Demo-Blocks.

I recommend starting with the "Gallery" block, which we will use in this example.

By default RockPageBuilder will add the field `rockpagebuilder_blocks` to the `home` template. You should see an interface like this:

<img src=https://i.imgur.com/8U4aaFV.png class=blur>

In the screenshot above I already created a Gallery block and saved the page.

### Adding RockPageBuilder to the Frontend

To add RockPageBuilder to your frontend you have to take two simple steps - don't do that now, we'll guide you through later!

1. Echo the content of the RockPageBuilder field, eg via `echo $rockpagebuilder->render(true)`
2. Add the necessary assets to your frontend

Now let's head over to the frontend. View the homepage on the frontend and you might see this warning:

<img src=https://i.imgur.com/4hzOy3H.png class=blur>

This is because RockPageBuilder is built on top of ProcessWires Frontend Editing capabilities. To install this feature go to Modules > Core > PageFrontEdit and install it.

Once installed you should not get any errors and the Gallery block should render something like this:

<img src=https://i.imgur.com/ALDdGPL.png class=blur>

We are not yet done, but Frontend Editing should already be working:

<img src=https://i.imgur.com/Vu8HsZM.gif class=blur>

But we are still missing the GUI for RockPageBuilder - that's because we need some additional assets (JS/CSS) to make the GUI render and work. For that we add the following to our main markup file (for example `_main.php`):

```php
<!DOCTYPE html>
<html lang="en">
<head>
  ...
  <?= rockfrontend()->assets() ?>
  <?= rockpagebuilder()->assets() ?>
</head>
...
```

RockFrontend assets are needed for the ALFRED frontend editing and RockPageBuilder assets are needed for the RockPageBuilder features like hover-info of blocks, moving and sorting blocks, deleting blocks etc.;

Note that these assets will only be loaded for logged in users, so your Frontend will stay clean & fast for all public visitors!

After loading the assets the interface should look like this:

<img src=https://i.imgur.com/OFhYPXO.gif class=blur>

## Adding RockPageBuilder to other pages

All you need to do to add RockPageBuilder to other pages is to add the field `rockpagebuilder_blocks` to any template where you want to use the pagebuilder.

Then use `<?= $rockpagebuilder->render(true) ?>` to output content of the pagebuilder field in your template file.

## LESS/CSS assets

If you use LESS or CSS assets for your blocks (for example a file like /site/templates/RockPageBuilder/blocks/Accordion/Accordion.less) you need to take care of loading these files on your own. When using RockDevTools you'd typically add them to your less() files array like this:

```php
// /site/templates/_init.php
if ($config->rockdevtools) {
  $devtools = rockdevtools();

  // parse all less files to css
  $devtools->assets()
    ->less()
    ->add('/site/templates/uikit/src/less/uikit.theme.less')
    ->add('/site/templates/src/*.less')
    ->add('/site/templates/sections/*.less')

    // add this line
    ->add('/site/templates/RockPageBuilder/*.less')

    // save the css files
    // this is the file that you need to include in your main markup!
    ->save('/site/templates/bundle/styles.css');

  // optionally add a minify step
  // see RockDevTools docs
}
```
