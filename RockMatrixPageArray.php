<?php namespace ProcessWire;
class RockMatrixPageArray extends PageArray {

  public $page;
  public $field;
  public $modified; // timestamp of last save action

  public function __construct($page, $field) {
    parent::__construct();
    $this->page = $page;
    $this->field = $field;
  }

  /**
   * This method takes the DB value of the RockMatrix fieldtype
   * and converts the data into a MatrixPageArray
   */
  public function wakeup($value) {
    $val = json_decode($value);
    if(!$val) return;
    foreach($val->items as $item) $this->addPage($item->id);
  }

  /**
   * Convert this pagearray to a json that can be stored into the DB
   * @return string
   */
  public function sleep() {
    $arr = [
      'items' => [],
    ];
    // loop all items of this pagearray
    foreach($this as $p) {
      // add the page id to the items array
      $arr['items'][] = (object)[
        'id' => $p->id,
      ];
    }

    return json_encode($arr);
  }

  /**
   * Does this pagearray equal another?
   * @return bool
   */
  public function equals($other) {
    $old = $this->sleep();
    $new = $other->sleep();
    return $old === $new;
  }

  /**
   * Add page to this array
   */
  public function addPage($page) {
    $page = $this->wire->pages->get((string)$page);
    if(!$page->id) return;

    // only add pages that have the correct reference in meta data
    // user access control is checked in processInput of Inputfield!
    if($page->getRockMatrixPage() != $this->page) {
      return $this->warning("Adding page $page not possible!");
    }

    $this->add($page);
  }
}
