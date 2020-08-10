<?php namespace RMDemo;
class Markup extends \RockMatrix\Block {

  public function info() {
    return parent::info()->setArray([
      'icon' => 'code',
    ]);
  }

  public function render() {
    return "This block does only render custom markup";
  }

}
