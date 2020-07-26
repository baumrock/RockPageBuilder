<?php namespace RockMatrixBlock;
class Headline extends \RockMatrix\Block {

  const tpl = self::prefix."headline"; // rmblock_headline

  public function info() {
    return parent::info()->setArray([
      'icon' => 'header',
      'tpl' => self::tpl,
      'title' => $this->_('Headline'),
      'description' => $this->_('Text will be rendered as Headline.'),
    ]);
  }

  public function migrate() {
    /** @var RockMigrations */
    $rm = $this->wire->modules->get('RockMigrations');
    $rm->migrate([
      'templates' => [
        self::tpl => [
          'icon' => $this->info()->icon,
          'pageClass' => get_class($this),
          'tags' => self::tags,
          'noChildren' => 1,
          'noSettings' => 1,
        ],
      ],
    ]);
  }
}
