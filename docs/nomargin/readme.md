# Text Margin

Consider having this text block:

```html
<section style="border: 5px solid red;">
  <div style="border: 2px solid black;">
    <p>Some text</p><!-- WYSIWYG content -->
  </div>
</section>
```

The problem here is that the user content can be any markup and often it's `<p>` tags that have some margin-bottom:

<img src=https://i.imgur.com/ru2ydDG.png class=blur>

To prevent this issue add the `.rpb-nomargin` class to the container:

```html
<section style="border: 5px solid red;">
  <div style="border: 2px solid black;" class="rpb-nomargin">
    <p>Some text</p><!-- WYSIWYG content -->
  </div>
</section>
```

Than the result will look like this:

<img src=https://i.imgur.com/Hm8uRv4.png class=blur>

If you think "I'll just add my own custom class for that like uk-margin-remove" be warned that your implementation will likely not work for frontend editable fields, because the frontend editor adds some additional markup that you css needs to take into account!

<img src=https://i.imgur.com/WFWSeVN.png class=blur>

Margins will still be perfect even when frontend editing the content!
