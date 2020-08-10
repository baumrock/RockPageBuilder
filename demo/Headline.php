<?php namespace RMDemo;
class Headline extends \RockMatrix\Block {

  public function info() {
    return parent::info()->setArray([
      'icon' => 'header',
    ]);
  }

  public function init() {
    $this->message($this->info()->name.' initialized');
  }
}
