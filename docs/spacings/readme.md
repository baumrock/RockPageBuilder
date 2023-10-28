# Block Spacings

When working on your own implementation of any kind of pagebuilder (eg based on RepeaterMatrix) you will soon notice that block spacings can really become a nightmare.

<div class="uk-alert uk-alert-warning">WARNING: This topic is not easy to understand. If you don't want to use that feature just remove the spaceV property from the info() method of your block and enjoy all the other great features of RockPageBuilder ;)</div>

<div class="uk-alert uk-alert-warning">Also note that this feature injects some ugly markup in your sections. It simply is not possible to get that result without that markup! But to quote one famous forum member: Your browser doesn't care ;)</div>

```html
<section ... style="padding-top: clamp(50px * var(--vscale-top), 50px * var(--vscale-top) + 50 * var(--vscale-top) * ((100vw - 360px) / (1440 - 360)), 100px * var(--vscale-top)); padding-bottom: clamp(50px * var(--vscale-bottom), 50px * var(--vscale-bottom) + 50 * var(--vscale-bottom) * ((100vw - 360px) / (1440 - 360)), 100px * var(--vscale-bottom)); --vscale-top: 0.66; --vscale-bottom: 0.66;">...</section>
```

You find that ugly? Me too! But be sure to read this docs about the WHY behind it and what problems it solves!

## WHY the *** ?

Please have a look at this fiddle (https://jsfiddle.net/8s35h7an/) that shows the problem:

<img src=fiddle.png class=blur>

1. Bubble one shows the problem with using margins. Backgroudns do not stretch.
2. Bubble two shows the problem with paddings: The space between two sections of the same color is doubled (40px instead of 20px) because paddings don't overlap!
3. Bubble three shows that we need to use padding for proper section backgrounds.

When dealing with multiple sections of content the first idea to add some space between them could be to use margins on the blocks:

```css
section {
  margin: 20px 0;
}
```

Simple, right? Unfortunately not. Bubble 1 shows that all lines of text are perfectly aligned and spacings between those texts are equally 20px. That's because of the nature of `margin`. Two elements with margin overlap each other.

The problem arises as soon as we want some background on some of our sections! Bubble 1 shows that the background only appears behind the text, but the margin does not stretch the background. Only `padding` does.

"Ok, so let's just add some padding to those sections that have some background!", I hear you say? Good idea: https://jsfiddle.net/8s35h7an/1/

<img src=margin-with-padding.png class=blur>

Now we have 20px between each line of white background and 20px space to each change of the background. Perfect! We ar done, right?

Unfortunately not. What if we had a block with another background? Not white, but also not green. And I promise you: This situation will come up! https://jsfiddle.net/8s35h7an/2/

<img src=two-backgrounds.png class=blur>

If that is what you want you can go this route. But on the websites that I have built what I wanted was this: https://jsfiddle.net/8s35h7an/3/

<img src=perfect.png class=blur>

But now we have another problem: The markup for this result is quite complex:

```html
<section>line one</section>
<section>line two</section>
<section>line three</section>
<section class="green pad mb0">line four</section>
<section class="blue pad m0">line five</section>
<section class="green pad mt0">line six</section>
<section>line seven</section>
<section>line eight</section>
<section>line nine</section>
```

We had to remove the margin-bottom on the first green section, that we had to remove the margin on the blue section and then we had to remove the margin on the second green section!

What might not be too complicated in this simple and static example can quickly become a nightmare in real world projects! Don't forget that the sections can be rearranged by the client. And the different background likely comes from a setting of that block, where the user can choose from a range of options, eg. none/bg-primary/bg-secondary.

What about using CSS rules like `followed by ...`? I'm glad you are asking! I've worked on a project with a frontend developer and he was suggesting exactly that. And that website even uses that approach until today, because we had no better solution back then. The problem here is that your CSS quickly becomes totally bloated and complicated. You have to address every single situation and you end up in if/else hell. Green followed by Blue... no margin. Blue followed by White margin-bottom but no margin-top if the previous block is Green or Blue ... You get it.

So for me it was clear: We need a solution based on PHP - because that's the place where we know about the block settings.

## spaceV

RockPageBuilder to the rescue!

<img src=space-v.png class=blur>

By default every block will use the `spaceM` value for the `spaceV` property. This will tell RockPageBuilder to use a padding of 50px on mobile and 100px on desktop. You can also use `self::spaceS` and `self::spaceL` or define a custom spacing, eg `"spaceV" => "35px"` or `"spaceV" => "10px, 100px"`.

Adding this setting will also tell RockPageBuilder to add the UI for changing this spacings on the fly. The slider will allow four steps:

1. Increased space
2. Default space
3. Smaller space
4. Remove space

<img src=space-ui.png class=blur>

Changing the vertical space works in real time and changes are saved via AJAX to make them persistant. The reset icon at the bottom makes the space go back to its default value.

Unfortunately there is one more concept to understand when it comes to block spacings: The spaceID.

## spaceID

<div class="uk-alert uk-alert-warning">Note that you find the code for the "Spacings" example block in /site/modules/RockPageBuilder/blocks/Spacings</div>

As long as all your blocks have the same background color (or spacing behaviour) you can leave everything as it is without worrying about the spaceID. But in that case you could also just use margins on your sections.

In our case we want to have perfect spacings and at the same time let the user choose different backgrounds and different orders of all blocks. For that we need to tell RockPageBuilder when to half the space between two following blocks and when to use the full spacing.

An example:

```
bg    top, bottom
----------------
white  20, 10
white  10, 10
white  10, 20
green  20, 10
green  10, 20
blue   20, 20
white  20, 10
white  10, 20
```

* The first block is white and starts with 20px top
* The next block is also white, so we add 10px bottom
* The next block is white, followed by white, so we need 10px top and 10px bottom
* The next block is white + green, so we need 10px, 20px
* Next is green + blue --> 10px, 20px
* and so on...

So to tell RockPageBuilder which space to use you need to tell it which `spaceID` the block has. The easiest would be a text block that always has a white background. We don't need to change anything in that case and just leave the spaceID setting at the default, which is `default`.

What about a block that always has a gray background? We cann tell that RockPageBuilder like so:

```php
public function info()
{
  return [
    ...
    'spaceV' => 'gray',
  ];
}
```

Now RockPageBuilder knows that this block has a gray background (gray could be any string!) and that tells RockPageBuilder that if a gray block is followed by a gray block it will half the spacing!

What about a block that can have multiple backgrounds? Let's assume that the background color is defined in a settings selectbox with the name `background`. The value of this background can be retrieved via `$block->settings('background')`. The result would for example be `red` or `blue`.


```php
public function info()
{
  return [...];
}

public function spaceID()
{
  // returns "default" if null, otherwise "red" or "blue"
  return $this->settings('background', 'default');
}
```

Note that if you are logged in as a SuperUser you can easily inspect the spaceID of the block here:

<img src=spaceid.png class=blur>

Note that on the screenshot above you see that the block has the doubled spacing at the top and half at the bottom. This is because we have a background change at the top and have a `default` background on the next block.

