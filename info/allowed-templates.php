<pre>
$wire->addHookAfter('InputfieldRockMatrix::getAllowedTemplates', function($event) {
  $field = $event->object->hasField;
  if($field->name != 'your_field') return;
  $event->return = ['your_template'];
});
</pre>