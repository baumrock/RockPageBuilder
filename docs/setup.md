# Setup

You can use RockPageBuilder for new projects but also for existing ones! The module is plug and play and only needs a few steps to setup.

<a href="https://www.youtube.com/watch?v=ulImisUs7zQ" target=_blank><img src=thumb.webp class=blur></a>

## Installation

1. Install RockMigrations and RockFrontend

    You can use the section `Add Module From Directory` as both are public modules listed in the PW Modules Directory.

1. Download RockPageBuilder and upload it to your PW Backend

    Note: We recommend using the `Add Module From Upload` feature (Modules > New). If you copy files manually make sure all files are placed in `/site/modules/RockPageBuilder` without any commit hash at the end.

1. Go through the module config and install blocks that you might need for your project or just use them to learn from examples.

1. Add the field `rockpagebuilder_blocks` to any template where you want to use the pagebuilder.

1. Use `<?= $rockpagebuilder->render(true) ?>` to output content of the pagebuilder field in your template file.

    For the blank site profile this code would go into the file `home.php` and the output would be seen on the frontpage of your site.

<div class='uk-alert uk-alert-success uk-text-center'>Congratulations! You are ready to use RockPageBuilder!</div>

The output might look something like this:

<img src=setup.png class=blur>

It looks ugly because the demo blocks use the UIkit CSS Framework which is not loaded in the blank profile. You can now either create your own custom markup for each block or you can use the `rockpagebuilder` profile that ships with RockFrontend.

## Using the RockFrontend's "rockpagebuilder" profile

To use the rockpagebuilder profile (which is based on the UIkit CSS Framework) simply install the profile and download the UIkit source files:

<img src=profile.png class=blur>

Then install the free `Less` module. It is required to compile LESS files to CSS.

Then visit your frontend and enjoy your new PageBuilder 😍

<img src=uikit.png class=blur>

Now you can add an accordion where you can toggle each item:

<img src=accordion.png class=blur>

Have fun exploring all the blocks that are available!
