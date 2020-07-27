<?php namespace RockMatrixBlock;
use ProcessWire\HookEvent;
class Image extends \RockMatrix\Block {

  const tpl = self::prefix."image";
  const field = self::tpl;

  public function info() {
    return parent::info()->setArray([
      'icon' => 'picture-o',
      'tpl' => self::tpl,
      'title' => $this->_('Image'),
      'description' => $this->_('Image upload block.'),
    ]);
  }

  public function init() {
    $this->addHookAfter("InputfieldRockPageEdit::getInputfields", $this, "buildFormMatrix");
  }

  /**
   * Edit form for sections on issue matrix
   */
  public function buildFormMatrix(HookEvent $event) {
    /** @var InputfieldForm */
    $form = $event->return;
    $editPage = $event->object->editPage;
    if($editPage->template != self::tpl) return;

    if($f = $form->get(self::field)) {
      $f->label = $this->_('Image');
    }
  }

  public function migrate() {
    /** @var RockMigrations */
    $rm = $this->wire->modules->get('RockMigrations');
    $rm->migrate([
      'fields' => [
        self::field => [
          'type' => 'image',
          "extensions" => "gif jpg jpeg png",
          "noLang" => 1,
          "descriptionRows" => 0,
          'tags' => self::tags,
        ],
      ],
      'templates' => [
        self::tpl => [
          'icon' => $this->info()->icon,
          'pageClass' => get_class($this),
          'tags' => self::tags,
          'noChildren' => 1,
          'noSettings' => 1,
          'fields' => [
            'title',
            self::field,
          ],
        ],
      ],
    ]);
  }
}
