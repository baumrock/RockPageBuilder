<?php namespace RockMatrix;

use ProcessWire\WireArray;
use ProcessWire\WireException;

class BlockSettingsArray extends WireArray {

  public function add($data) {
    require_once __DIR__."/BlockSettingsItem.php";
    if(is_array($data)) {
      $item = new BlockSettingsItem();
      $item->setArray($data);
    }
    else {
      throw new WireException("Invalid data - must be array");
    }
    return parent::add($item);
  }

  public function getPlainArray() {
    $arr = [];
    foreach($this as $item) {
      $arr[$item->label] = $item->value;
    }
    return $arr;
  }

}
