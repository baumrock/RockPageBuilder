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
    $_item = $item;
    $item = $mx->getBlockPage($item);
    if(!$item) throw new WireException("Invalid item $_item");

    // check if item is allowed!
    if(!$item->isAllowed($this->field, $this->page)) {
      throw new WireException("Not allowed");
    }

    parent::add($item);
  }

  /**
   * Get a blank copy
   * @return FieldData
   */
  public function getNew($data = null) {
    $new = $this->field->type->getBlankValue($this->page, $this->field);
    if($data) $new->wakeup($data);
    return $new;
  }

  /**
   * Has this object changed compared to another one?
   * This method is used for triggering the trackChange event when a page
   * having a MX field is saved and input is processed.
   * @return bool
   */
  public function hasChanged($other) {
    $new = $this->sleepValue();
    $old = $other->sleepValue();
    return $new !== $old;
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

  /**
   * Wakeup from given data
   * @return FieldData
   */
  public function wakeup($data) {
    $json = json_decode($data);
    if(!$json) throw new WireException("Invalid json");
    foreach($json as $item) $this->add($item->id);
    return $this;
  }

  public function __debugInfo() {
    return array_merge([
      'page' => $this->page,
      'field' => $this->field,
    ], parent::__debugInfo());
  }
}
