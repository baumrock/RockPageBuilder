# Limitations

## showIf and requiredIf

`showIf` should work as expected, but unfortunately `requiredIf` does not. See https://processwire.com/talk/topic/29683-dependent-fields-shown-with-showif-condition-in-block-dont-persist/ for details. If anybody has a hint how to tackle this please let me know.

Personally I have never needed `requiredIf` in any of my blocks, so it has never been an issue for me. At least I always found a workaround, like showing a warning on the block's frontend if a field is not filled or such.

## max_input_vars

When editing pages with many blocks in RockPageBuilder, the number of form fields loaded in the page editor can grow significantly. ProcessWire core handles similar situations by allowing fields to be loaded via AJAX, which prevents them from consuming resources until needed.

Currently, RockPageBuilder loads all block fields upfront in the page editor. When saving, all field contents are submitted together in the form. This means you could potentially hit your server's `max_input_vars` limit if you have a very large number of blocks and fields.

However, this limitation only applies to the admin page editor. When editing content through RockPageBuilder's frontend interface, blocks are loaded and saved individually, so you'll never run into field limit issues there.

Some tips to handle this:

- Monitor your field usage if building pages with many blocks
- Consider increasing `max_input_vars` in php.ini if needed (default is 1000)
- Use the frontend editor for pages with large numbers of blocks

While some users report needing higher limits (3,000-10,000) for other ProcessWire features, I've never hit that limitation with the default setting of 1000 on any of my RockPageBuilder projects.

## jQuery

The ALFRED editor for frontend editing is actually a feature of ProcessWire itself, not RockPageBuilder. It is based on the ProcessWire backend, which heavily uses jQuery.

Unfortunately, this can lead to trouble if you are using jQuery on your frontend! To solve this you have some options:

- Remove jQuery from your frontend
- Do not use frontend editing / ALFRED
- Use the same jQuery version that ProcessWire uses
