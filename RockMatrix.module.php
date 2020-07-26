<?php namespace ProcessWire;
/**
 * @author Bernhard Baumrock, 18.07.2020
 * @license Licensed under MIT
 * @link https://www.baumrock.com
 */
class RockMatrix extends WireData implements Module, ConfigurableModule {

  const prefix = 'rockmatrix_';

  public $blocks = [];

  public static function getModuleInfo() {
    return [
      'title' => 'RockMatrix',
      'version' => '0.0.1',
      'summary' => 'Master module for RockMatrix Fieldtype + Inputfield',
      'autoload' => true,
      'singular' => true,
      'icon' => 'bolt',
      'requires' => [],
      'installs' => [
        'FieldtypeRockMatrix',
        'InputfieldRockMatrix',
        'InputfieldRockPageEdit',
        'ProcessRockMatrix',
      ],
    ];
  }

  public function init() {
    $this->addBlocks(__DIR__."/blocks");
    $this->addHookAfter("Modules::refresh", $this, "migrate");
  }

  /**
   * Scan dir and add blocks
   * @return void
   */
  public function addBlocks($dir) {
    $opt = ['extensions' => ['php']];
    $blocks = $this->blocks;

    // load the block baseclass
    require_once("Block.php");
    foreach($this->wire->files->find($dir, $opt) as $file) {
      require_once($file);
      $name = pathinfo($file, PATHINFO_FILENAME);
      $class = "\RockMatrixBlock\\$name";
      $block = new $class();
      if(method_exists($block, "init")) $block->init();
      $blocks[pathinfo($file, PATHINFO_FILENAME)] = $block;
    }
    ksort($blocks);
    $this->blocks = $blocks;
  }

  /**
   * Get block by name
   * @return false|Block
   */
  public function getBlock($name) {
    if(!array_key_exists($name, $this->blocks)) return false;
    return $this->blocks[$name];
  }

  /**
   * Get allowed blocks for given field and page
   * @return array
   */
  public function ___getAllowedBlocks($field, $page) {
    return [];
  }

  /**
   * Module Migrations
   */
  public function migrate() {
    // migrate all blocks
    foreach($this->blocks as $name=>$file) {
      $block = $this->getBlock($name);
      if(!$block) return;
      $block->migrate();
    }
  }

  /**
  * Config inputfields
  * @param InputfieldWrapper $inputfields
  */
  public function getModuleConfigInputfields($inputfields) {
    return $inputfields;
  }

  public function __debugInfo() {
    return [
      'blocks' => $this->blocks,
    ];
  }
}
