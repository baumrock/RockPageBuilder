<pre>
// setup instructions
$wire->addHookAfter('InputfieldRockMatrix::getAllowedTemplates', function($event) {
  $field = $event->object->hasField;
  if($field->name != '<?= $name ?>') return;
  $event->return = ['your_template'];
});
</pre>