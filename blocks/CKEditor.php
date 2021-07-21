<?php namespace RMBlock;

use ProcessWire\FieldtypeTextarea;
use ProcessWire\HookEvent;
use ProcessWire\Inputfield;
use ProcessWire\InputfieldWrapper;
use ProcessWire\RockMatrix;

class CKEditor extends \RockMatrix\Block {

  const prefix = RockMatrix::prefix."ckeditor_";
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
    $this->addHookBefore("Field(name=$name)::getInputfield", $this, "ckeSettings");
  }

  public function buildForm(InputfieldWrapper $fs) {
    $fs->remove('title');
  }

  public function buildFormMatrix(InputfieldWrapper $fs) {
    $fs->remove('title');
    if($f = $fs->get(self::field_text)) {
      $f->skipLabel = Inputfield::skipLabelMarkup;
      $f->wrapClass('rmx-pd5');
    }
  }

  public function ckeSettings(HookEvent $event) {
    $field = $event->object; /** @var InputfieldCKEditor $field */
    // full list of available toolbar options: https://bit.ly/3vjPy9B
    // Lots of formatting options are disabled! The more options are disabled
    // the better are the results when copy/pasting from MS Word!
    $field->toolbar = "JustifyLeft, JustifyCenter, JustifyRight, JustifyBlock,
      Bold, Italic,
      NumberedList, BulletedList,
      Link, Unlink, HorizontalRule, SpecialChar,
      RemoveFormat,";
    if($this->wire->user->isSuperuser()) {
      $field->toolbar .= "Source,";
    }
    $field->rows = 7;
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
          "contentType" => FieldtypeTextarea::contentTypeHTML,
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
    return "<div class='rmblock-ckeditor'>"
      .$this->get(self::field_text)
      ."</div>";
  }

  public function uninstall() {
    parent::uninstall();
    $this->rm()->deleteField(self::field_text);
  }

}
