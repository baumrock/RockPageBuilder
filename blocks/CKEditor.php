<?php namespace RMBlock;

use ProcessWire\HookEvent;
use ProcessWire\Inputfield;
use ProcessWire\InputfieldWrapper;
use ProcessWire\RockMatrix;

class CKEditor extends \RockMatrix\Block {

  const prefix = RockMatrix::prefix;
  const tags = RockMatrix::tags;

  const field_text = self::prefix."text";

  public function info() {
    return parent::info()->setArray([
      'icon' => 'align-left',
      'description' => 'Add regular text',
    ]);
  }

  public function init() {
    $name = self::field_text;
    // full list of available toolbar options: https://bit.ly/3vjPy9B
    $this->addHookBefore("Field(name=$name)::getInputfield", function(HookEvent $event) {
      $field = $event->object; /** @var InputfieldCKEditor $field */
      $field->toolbar = "Format,
        JustifyLeft, JustifyCenter, JustifyRight, JustifyBlock
        Bold, Italic, TextColor, RemoveFormat
        NumberedList, BulletedList,
        Link, Unlink, Image, Table, HorizontalRule, SpecialChar
        FontSize,
        Sourcedialog";
      $field->rows = 10;
    });
  }

  public function buildForm(InputfieldWrapper $fs) {
    $fs->remove('title');
    $f = $fs->get(self::field_text);
    $f->skipLabel = Inputfield::skipLabelMarkup;
    $f->wrapClass('rmx-pd5');
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

  public function render() {
    return $this->get(self::field_text);
  }

  public function uninstall() {
    parent::uninstall();
    $this->rm()->deleteField(self::field_text);
  }

}
