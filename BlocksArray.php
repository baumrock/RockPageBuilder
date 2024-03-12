<?php

namespace RockPageBuilder;

use ProcessWire\WireArray;

class BlocksArray extends WireArray
{

  /**
   * Add item to this array
   */
  public function add($item)
  {
    if (is_array($item)) {
      foreach ($item as $i) $this->add($i);
      return;
    }
    if (is_string($item)) {
      /** @var RockPageBuilder */
      $mx = $this->wire->modules->get('RockPageBuilder');
      $block = $mx->getBlock($item);
      if ($block) return parent::add($block);
      else {
        // silent return
        // This makes sure that fields stay usable even
        // if blocks are in the DB that are not allowed any more.
        // If we threw an exception the field would become unusable
        // in such cases!
        return false;
      }
    }
    parent::add($item);
  }
}
