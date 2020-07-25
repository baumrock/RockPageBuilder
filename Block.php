<?php namespace RockMatrix;
abstract class Block extends \ProcessWire\Page {

  const prefix = "rmblock";

  public function getBlockInfo() {
    return [
      'icon' => 'cube',
    ];
  }
}
