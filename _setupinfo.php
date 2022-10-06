Add all files of a given folder as blocks to this field:
<pre>
/** @var RockPageBuilder $rpb */
$rpb = $this->wire->modules->get('RockPageBuilder');
$rpb->loadBlocks("<?= $name ?>", "/path/to/your/blocks", "YourNamespace");
</pre>
Add this in site/init.php or in module init() to finish setup of your field:
<pre>
$this->addHookAfter('RockPageBuilder::getAllowedBlocks(name=<?= $name ?>)', function($event) {
  $field = $event->arguments(0);
  $page = $event->arguments(1);
  $event->return->add([
    'RockPageBuilderBlock\CKEditor',
  ]);
});
</pre>
<div>Advanced</div>
<pre>
$modules = $this->wire->modules;
if($modules->isInstalled('RockPageBuilder')) {
  /** @var RockPageBuilder */
  $mx = $modules->get('RockPageBuilder');
  $mx->addBlocks($this->wire->config->paths->siteModules."RockPageBuilder/demo/", "RMDemo");
  $mx->addHookAfter('getAllowedBlocks', function($event) {
    $field = $event->arguments(0);
    $page = $event->arguments(1);
    if($field->name !== '<?= $name ?>') return;
    $event->return->add([
      'RMDemo\Headline',
      'RMDemo\Markup',
    ]);
  });
}
</pre>