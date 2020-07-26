<?php namespace RockMatrixBlock;
use ProcessWire\HookEvent;
class Headline extends \RockMatrix\Block {

  const tpl = self::prefix."headline"; // rmblock_headline

  public function info() {
    return parent::info()->setArray([
      'icon' => 'header',
      'tpl' => self::tpl,
      'title' => $this->_('Headline'),
      'description' => $this->_('Text will be rendered as Headline.'),
    ]);
  }

  public function init() {
    $this->addHookAfter("Pages::saveReady", $this, "saveReady");
    $this->addHookAfter("ProcessPageEdit::buildForm", $this, "buildForm");
  }

  public function saveReady(HookEvent $event) {
    $page = $event->arguments(0);
    if($page->template != self::tpl) return;

    if(!$page->id) {
      $page->name = $event->pages->names()->uniqueRandomPageName(5);
      $page->title = $page->title ?: 'Headline';
    }
  }

  /**
   * Build the edit form for this block
   */
  public function buildForm(HookEvent $event) {
    $form = $event->arguments(0);
    $page = $event->object->getPage();
    if($page->template != self::tpl) return;

    if($f = $form->get('title')) {
      $f->label = $this->_('Headline');
    }
  }

  public function migrate() {
    /** @var RockMigrations */
    $rm = $this->wire->modules->get('RockMigrations');
    $rm->migrate([
      'templates' => [
        self::tpl => [
          'icon' => $this->info()->icon,
          'pageClass' => get_class($this),
          'tags' => self::tags,
          'noChildren' => 1,
          'noSettings' => 1,
        ],
      ],
    ]);
  }
}
