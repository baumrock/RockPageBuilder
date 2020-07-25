<?php namespace RockMatrixBlock;
class Separator extends \RockMatrix\Block {

  const tpl = self::prefix."separator"; // rmblock_separator

  public function getBlockInfo() {
    return array_merge(parent::getBlockInfo(), [
      'icon' => 'smile-o',
      'title' => $this->_('Separator'),
      'description' => $this->_('This block renders a horizontal ruler.'),
    ]);
  }
}
