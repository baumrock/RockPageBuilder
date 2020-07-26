<?php namespace RockMatrix;
use \ProcessWire\WireData;
use \ProcessWire\HookEvent;
abstract class Block extends \ProcessWire\Page {

  const prefix = "rmblock_";
  const tags = "RockMatrix";

  public function info() {
    $info = $this->wire(new WireData()); /** @var WireData $info */
    return $info->setArray([
      'name' => $this->className,
      'icon' => 'cube',
    ]);
  }

  public function init() {

    /**
     * Add hook that triggers the saveReady() method of the block
     */
    $this->addHookAfter("Pages::saveReady", function(HookEvent $event) {
      $page = $event->arguments(0);
      if(!$page instanceof Block) return;
      $this->saveReady($event);
    });

    /**
     * Add hook on buildform that calls the buildForm() method of the
     * edited block.
     */
    $this->addHookAfter("ProcessPageEdit::buildForm", function(HookEvent $event) {
      $page = $event->object->getPage();
      if(!$page instanceof Block) return;
      $this->buildForm($event);
    });
  }

  /**
   * This hook is called for every block and by default does nothing
   * Just add your own in the specific block instance!
   * @return void
   */
  public function buildForm(HookEvent $event) {}

  /**
   * This hook is called for every block and by default does nothing
   * Just add your own in the specific block instance!
   * @return void
   */
  public function saveReady(HookEvent $event) {}

  /**
   * Block Migrations
   */
  public function migrate() {}

  /**
   * Is this block allowed on given page and field?
   * @return bool
   */
  public function isAllowed($field, $page) {
    return true;
    // get allowed blocks for page+field
    // $allowed = $this->master->getAllowedBlocks($field, $page);
    // return in_array($this->className, $allowed);
  }
}
