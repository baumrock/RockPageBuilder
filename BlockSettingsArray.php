<?php

namespace RockPageBuilder;

use ProcessWire\WireArray;
use ProcessWire\WireException;

class BlockSettingsArray extends WireArray
{

  public function __construct()
  {
    parent::__construct();
    require_once __DIR__ . "/BlockSettingsItem.php";
  }

  public function add($data)
  {
    return parent::add($this->getItem($data));
  }

  public function getItem($data)
  {
    if (is_array($data)) {
      $item = new BlockSettingsItem();
      $item->setArray($data);
    } else {
      throw new WireException("Invalid data - must be array");
    }
    return $item;
  }

  public function getPlainArray()
  {
    $arr = [];
    foreach ($this as $item) {
      $arr[$item->label] = $item->value;
    }
    return $arr;
  }

  public function prepend($data)
  {
    return parent::prepend($this->getItem($data));
  }
}
