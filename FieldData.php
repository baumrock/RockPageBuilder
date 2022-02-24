<?php namespace RockMatrix;
use ProcessWire\PageArray;
use ProcessWire\WireException;
use ProcessWire\WireData;
use ProcessWire\RockMatrix;
class FieldData extends PageArray {

  public $page;
  public $field;

  public function __construct($page, $field) {
    $this->page = $page;
    $this->field = $field;
    parent::__construct();
  }

  /** Field data manipulation API */

    /**
     * Add item to this field data array
     * @return self
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
      if(!$item) throw new WireException("Invalid item $_item - did you call parent::migrate() in your block?");

      // check if item is allowed!
      if(!$item->isAllowed($this->field, $this->page)) {
        return $this->error("$item not allowed for field {$this->field}");
      }

      parent::add($item);
      return $this;
    }

    /**
     * Create a new block and add it to the field
     * @return self
     */
    public function create($options = []) {
      $opt = $this->wire(new WireData()); /** @var WireData $opt */
      $opt->setArray([
        'tpl' => null, // block template
        'set' => [], // block page content
        'add' => true, // add block to field by default
      ]);
      $opt->setArray($options);

      if(!$opt->tpl) throw new WireException("You must set a block template");

      // create page
      // is the block allowed?
      $block = $this->master()->getBlockByTpl($opt->tpl);
      if(!$block) throw new WireException("Invalid tpl ".$opt->tpl);
      if(!$block->isAllowed($this->field, $this->page)) throw new WireException($opt->tpl. " not allowed");

      // create new block
      $class = $block->info()->name;
      $b = $this->wire(new $class()); /** @var Block $b */
      $b->template = $block->getTpl();
      $b->parent = $block->getParent($this->field, $this->page);
      $b->title = "$class @ ".date('Y-m-d H:i:s');
      $b->save();

      // set page data
      foreach($opt->set as $k=>$v) $b->setAndSave($k, $v);

      // save a reference to the page and the field where this page lives
      // this is necessary for deleting unused pages from time to time
      $b->meta('RockMatrix', $this->page->id."-".$this->field->id);

      // add block to field
      if($opt->add) $this->add($b);

      return $this;
    }

    /**
     * Reset this field and delete all blocks
     * @return self
     */
    public function reset() {
      foreach($this as $block) $block->delete();
      return $this;
    }

    /**
     * Save this field on current page
     * @return self
     */
    public function save() {
      $this->page->setAndSave($this->field->name, $this);
      return $this;
    }

  /** END Field data manipulation API */

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
   * Get property of object
   * @return mixed
   */
  public function getProp($obj, $prop) {
    return property_exists($obj, $prop) ? $obj->$prop : null;
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
   * Get master module instance
   * @return RockMatrix
   */
  public function master() {
    return $this->wire->modules->get('RockMatrix');
  }

  /**
   * Render all items of this matrix field
   * @return string
   */
  public function render() {
    $out = '';
    $typeIndex = 0;
    foreach($this as $i=>$block) {
      /** @var Block $block */
      /** @var Block $next */
      /** @var Block $prev */
      $next = $this->eq($i+1);
      $prev = $this->eq($i-1);

      // is this block last of same type?
      $block->lastOfType = true;
      if($next AND $next->getTpl() == $block->getTpl()) {
        $block->lastOfType = false;
      }

      // set type index of this block
      // this is helpful for switching left/right option based on index
      // eg even = left, odd = right aligned block
      if(!$prev OR $prev->getTpl() != $block->getTpl()) $typeIndex = 0;
      $block->typeIndex = $typeIndex++;

      $out .= $block->render();
    }
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
    /** @var RockMatrix */
    $mx = $this->wire->modules->get('RockMatrix');
    $json = json_decode($data);
    if($json === null) throw new WireException("Invalid json");

    // loop items
    foreach($json as $item) {
      $block = $mx->getBlockPage($item->id);
      if(!$block) continue;
      if($block->isTrash()) continue;

      // set the changed property of this block
      // this value us used on processInput to trigger page save of the item
      $block->_mxchanged = $this->getProp($item, 'changed');
      $block->_mxtrash = $this->getProp($item, 'trash');

      $this->add($block);
    }
    return $this;
  }

  public function __debugInfo() {
    return array_merge([
      'page' => $this->page,
      'field' => $this->field,
    ], parent::__debugInfo());
  }
}
