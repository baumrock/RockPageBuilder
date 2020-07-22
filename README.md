# RockMatrix

Repeater Matrix for ProcessWire, referenced as RM in this readme.

## Setup

### Setting up the Inputfield

Every field needs to know

* Where to add new pages (the new page parent)
* What templates to allow for adding new pages

Most likely the new parent is either the current page where the field lives on or the RockMatrix global parent page that is created on module install.

Once a parent for the field is set, the allowed templates can be retrieved by RM. Allowed templates will always be at most the templates returned by ProcessPageAdd::getAllowedTemplates() but they can be further limited down via settings. All this must be handled via hook (not via field settings), because the data is not only important during render of the field but also during processInput!

```php
$wire->addHookAfter('InputfieldRockMatrix::getAllowedTemplates', function($event) {
  $event->return->remove('basic-page');
});
```

### Adding / setting up Blocks

Blocks are actually PW Pages with a custom PW template. **Every Block MUST extend the \RockMatrix\Block base class**

