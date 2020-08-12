<?php namespace RMDemo;
class Headline extends \RockMatrix\Block {

  public function info() {
    return parent::info()->setArray([
      'icon' => 'header',
      'description' => 'Demo description.',
    ]);
  }

  public function init() {
    $this->message("Demo message: ".$this->info()->name.' initialized');
  }
}
