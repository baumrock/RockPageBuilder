<?php namespace ProcessWire;
class BlocksArray extends WireArray {

  /**
   * Add item to this array
   */
  public function add($item) {
    if(is_array($item)) {
      foreach($item as $i) $this->add($i);
      return;
    }
    if(is_string($item)) {
      /** @var RockMatrix */
      $mx = $this->wire->modules->get('RockMatrix');
      $block = $mx->getBlock($item);
      if($block) return parent::add($block);
      else throw new WireException("Block $item not found");
    }
    throw new WireException("Invalid item type");
  }
}
