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

  public function ready() {
    $this->loadBlocks();
  }

  /**
   * Scan dir and add blocks
   * @return void
   */
  public function addBlocks($dir) {
    $opt = ['extensions' => ['php']];
    $blocks = $this->blocks;
    foreach($this->wire->files->find($dir, $opt) as $file) {
      $blocks[pathinfo($file, PATHINFO_FILENAME)] = $file;
    }
    ksort($blocks);
    $this->blocks = $blocks;
  }

  /**
   * Hookable method for loading blocks
   * @return void
   */
  public function ___loadBlocks() {}

  /**
   * Get block by name
   * @return false|Block
   */
  public function getBlock($name) {
    if(!array_key_exists($name, $this->blocks)) return false;

    // load the block baseclass
    require_once("Block.php");

    // load the block
    $file = $this->blocks[$name];
    require_once($file);
    $class = "\RockMatrixBlock\\$name";
    $block = new $class();
    return $block;
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
      db($block, $name);
      if(!$block) return;
      db("migrate $block");
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
