<?php namespace RockMatrixBlock;
use ProcessWire\HookEvent;
use ProcessWire\InputfieldFile;
use ProcessWire\InputfieldImage;

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
    $this->addHookAfter("Pages::saveReady", $this, "saveReady");
    $this->addHookAfter("InputfieldRockPageEdit::getInputfields", $this, "buildFormMatrix");
  }

  public function saveReady(HookEvent $event) {
    $page = $event->arguments(0);
    if($page->template != self::tpl) return;

    if(!$page->id) {
      $page->name = $event->pages->names()->uniqueRandomPageName(5);
    }
    $page->title = $page->title ?: $this->_('Geben Sie hier ihre Bildbeschreibung oder einen alternativen Bildtext ein');
  }

  /**
   * Edit form for sections on issue matrix
   */
  public function buildFormMatrix(HookEvent $event) {
    /** @var InputfieldForm */
    $form = $event->return;
    $editPage = $event->object->editPage;
    if($editPage->template != self::tpl) return;

    if($f = $form->get('title')) {
      $f->label = $this->_('Beschreibung / Alternativer Bildtext');
      $f->notes = $this->_('Important for SEO and accessibility (eg. screen readers).');
    }

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
          "maxFiles" => 1,

          // single element or false
          "outputFormat" => 2,

          // do NOT set field required
          // a required field makes it impossible to delete an image
          // better not set required and check if image exists on frontend
          'required' => 0,
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
            self::field,
            'title',
          ],
        ],
      ],
    ]);
  }
}
