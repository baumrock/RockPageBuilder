<?php namespace RockMatrixBlock;
use \ProcessWire\HookEvent;
class Separator extends \RockMatrix\Block {

  const tpl = self::prefix."separator"; // rmblock_separator

  public function info() {
    return parent::info()->setArray([
      'icon' => 'minus',
      'title' => $this->_('Separator'),
      'tpl' => self::tpl,
      'description' => $this->_('This block renders a horizontal ruler.'),
    ]);
  }

  public function init() {
    $this->addHookAfter("Pages::saveReady", $this, "saveReady");
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

    $form->remove("title");
  }

  public function saveReady(HookEvent $event) {
    $page = $event->arguments(0);
    if($page->template != self::tpl) return;

    if(!$page->id) {
      $page->name = $event->pages->names()->uniqueRandomPageName(10);
      $page->title = 'Separator';
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
