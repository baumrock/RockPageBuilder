Add this in site/init.php or in module init() to finish setup of your field:
<pre>
$this->addHookAfter('RockMatrix::getAllowedBlocks(name=<?= $name ?>)', function($event) {
  $field = $event->arguments(0);
  $page = $event->arguments(1);
  $event->return->add([
    'RMBlock\CKEditor',
  ]);
});
</pre>
<div>Advanced</div>
<pre>
$modules = $this->wire->modules;
if($modules->isInstalled('RockMatrix')) {
  /** @var RockMatrix */
  $mx = $modules->get('RockMatrix');
  $mx->addBlocks($this->wire->config->paths->siteModules."RockMatrix/demo/", "RMDemo");
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