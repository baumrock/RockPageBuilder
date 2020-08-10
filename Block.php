<?php namespace RockMatrix;
use \ProcessWire\WireData;
abstract class Block extends \ProcessWire\Page {

  const prefix = "rmblock_";
  const tags = "RockMatrix";

  /** @var RockMigrations */
  public $rm;

  public function info() {
    $info = $this->wire(new WireData()); /** @var WireData $info */
    return $info->setArray([
      'name' => get_class($this),
      'icon' => 'cube',
    ]);
  }

  /**
   * This method is called when the block is loaded initially
   * It can be used to attach hooks but is completely optional
   */
  public function init() {
    $this->rm = $this->wire->modules->get('RockMigrations');
  }

  /**
   * Get the related pw template
   */
  public function getTpl() {
    return $this->wire->templates->get($this->getTplName());
  }

  /**
   * Convert the class name to a pw valid tpl name
   * @return string
   */
  public function getTplName() {
    $class = get_class($this);
    return $this->wire->sanitizer->pagename($class);
  }

  /**
   * Render this block
   */
  public function render() {
    return $this->info()->name . "::render()";
  }

  /**
   * Is this block allowed on given page and field?
   * @return bool
   */
  public function isAllowed($field, $page) {
    // TODO check if block is allowed
    return true;
    // get allowed blocks for page+field
    // $allowed = $this->master->getAllowedBlocks($field, $page);
    // return in_array($this->className, $allowed);
  }

  /**
   * Block Migrations
   */
  public function migrate() {
    // we always create the related template

  }
}
