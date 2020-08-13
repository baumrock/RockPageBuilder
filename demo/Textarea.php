<?php namespace RMDemo;
use ProcessWire\HookEvent;
use ProcessWire\InputfieldWrapper;
use ProcessWire\InputfieldCKEditor;
class Textarea extends \RockMatrix\Block {

  const prefix = "rmdemo_textarea_";
  const field_text = self::prefix."text";

  public function info() {
    return parent::info()->setArray([
      'icon' => 'align-left',
    ]);
  }

  public function init() {
    $name = self::field_text;
    $this->addHookBefore("Field(name=$name)::getInputfield", function(HookEvent $event) {
      $field = $event->object; /** @var InputfieldCKEditor $field */
      $field->toolbar = 'Bold, Italic, -, NumberedList, BulletedList';
      $field->rows = 10;
    });
  }

  public function buildForm(InputfieldWrapper $fs) {
    $fs = parent::___buildForm($fs);
    $fs->remove('title');
    return $fs;
  }

  public function getLabel() {
    $txt = $this->get(self::field_text);
    if(!$txt) return "Text";
    return $this->wire->sanitizer->truncate($txt, 50);
  }

  public function migrate() {
    parent::migrate();
    $this->rm()->migrate([
      'fields' => [
        self::field_text => [
          'type' => 'textarea',
          'label' => 'Text',
          'tags' => self::tags,
          'icon' => $this->info()->icon,
          "inputfieldClass" => "InputfieldCKEditor",
          "contentType" => 1,
        ],
      ],
      'templates' => [
        $this->getTplName() => [
          'fields' => [
            self::field_text,
          ],
        ],
      ],
    ]);
  }

  public function uninstall() {
    parent::uninstall();
    $this->rm()->deleteField(self::field_text);
  }
}
