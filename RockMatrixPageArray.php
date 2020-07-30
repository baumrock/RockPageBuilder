<?php namespace ProcessWire;
class RockMatrixPageArray extends PageArray {

  public $page;
  public $field;
  public $changed; // timestamp of last change
  public $changedItems = []; // array of changed items

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

    // set array of changed items
    $changedItems = property_exists($val, "changedItems") ? $val->changedItems : [];
    if(is_array($changedItems)) $this->changedItems = $changedItems;
  }

  /**
   * Convert this pagearray to a json that can be stored into the DB
   * @return string
   */
  public function sleep() {
    $arr = [
      'items' => [],
      'changed' => (int)$this->changed,
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
   * Check if item is in changed items array
   */
  public function itemChanged($item) {
    return in_array($item->id, $this->changedItems);
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
      $this->warning("Adding page $page not possible!");
      return;
    }

    // Dont add pages that are in the trash
    if($page->isTrash()) return;

    $this->add($page);
    return $page;
  }

  public function __debugInfo() {
    return array_merge(parent::__debugInfo(), [
      'changed' => $this->changed,
    ]);
  }
}
