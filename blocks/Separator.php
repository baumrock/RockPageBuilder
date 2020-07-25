<?php namespace RockMatrixBlock;
class Separator extends \RockMatrix\Block {

  const tpl = self::prefix."separator"; // rmblock_separator

  public function info() {
    return parent::info()->setArray([
      'icon' => 'minus',
      'title' => $this->_('Separator'),
      'description' => $this->_('This block renders a horizontal ruler.'),
    ]);
  }
}
