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
   * Load all blocks that are available on the system
   * @return void
   */
  public function ___loadBlocks() {

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
