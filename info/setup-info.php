<pre>
// setup instructions
$wire->addHookAfter('RockMatrix::getAllowedBlocks', function($event) {
  $field = $event->arguments(0);
  $page = $event->arguments(1);
  if($field->name != '<?= $name ?>') return;
  $event->return = ['foo', 'bar'];
});
</pre>