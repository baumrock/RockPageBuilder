<?php namespace RockMatrixBlock;
class Headline extends \RockMatrix\Block {

  const tpl = self::prefix."headline"; // rmblock_headline

  public function getBlockInfo() {
    return parent::getBlockInfo()->setArray([
      'icon' => 'header',
      'title' => $this->_('Headline'),
      'description' => $this->_('Text will be rendered as Headline.'),
    ]);
  }
}
