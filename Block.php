<?php namespace RockMatrix;
use \ProcessWire\WireData;
abstract class Block extends \ProcessWire\Page {

  const prefix = "rmblock";

  public function getBlockInfo() {
    $info = $this->wire(new WireData()); /** @var WireData $info */
    return $info->setArray([
      'name' => $this->className,
      'icon' => 'cube',
    ]);
  }
}
