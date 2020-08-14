<pre>
// add this in site/init.php (or in module init()) to finish setup
if($modules->isInstalled('RockMatrix')) {
  $mx = $modules->get('RockMatrix');
  $mx->addBlocks($config->paths->siteModules."RockMatrix/demo/", "RMDemo");
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