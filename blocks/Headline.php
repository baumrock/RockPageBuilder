<?php namespace RockMatrixBlock;
class Headline extends \RockMatrix\Block {

  const tpl = self::prefix."headline"; // rmblock_headline

  public function getBlockInfo() {
    return array_merge(parent::getBlockInfo(), [
      'icon' => 'smile-o',
      'title' => $this->_('Headline'),
      'description' => $this->_('Text will be rendered as Headline.'),
    ]);
  }
}
