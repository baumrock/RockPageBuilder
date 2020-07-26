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
