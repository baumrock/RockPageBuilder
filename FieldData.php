<?php namespace RockMatrix;
use ProcessWire\PageArray;
use ProcessWire\WireException;
class FieldData extends PageArray {

  public $page;
  public $field;

  public function __construct($page, $field) {
    $this->page = $page;
    $this->field = $field;
    parent::__construct();
  }

  /**
   * Add item to this field data array
   * @return void
   */
  public function add($item) {
    if($item instanceof PageArray) {
      foreach($item as $i) $this->add($i);
      return;
    }
    /** @var RockMatrix */
    $mx = $this->wire->modules->get('RockMatrix');

    // make sure item is a page
    $item = $this->wire->pages->get((string)$item);

    // check if item is allowed!
    $allowed = $mx->getAllowedBlocks($this->field, $this->page);
    if(!$allowed->has($item->getRmBlock())) throw new WireException("Not allowed");

    parent::add($item);
  }

  /**
   * Render all items of this matrix field
   * @return string
   */
  public function render() {
    $out = '';
    foreach($this as $block) $out .= $block->render();
    return $out;
  }

  /**
   * Get sleep value of this array
   * This does NOT check if items are allowed etc.
   * @return string
   */
  public function sleepValue() {
    $sleep = [];
    foreach($this as $item) {
      $sleep[] = (object)[
        'id' => $item->id,
      ];
    }
    return json_encode($sleep);
  }

  public function __debugInfo() {
    return array_merge([
      'page' => $this->page,
      'field' => $this->field,
    ], parent::__debugInfo());
  }
}
