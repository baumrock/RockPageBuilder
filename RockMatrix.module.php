<?php namespace ProcessWire;
/**
 * @author Bernhard Baumrock, 18.07.2020
 * @license COMMERCIAL DO NOT DISTRIBUTE
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
      'icon' => 'cubes',
      'requires' => [
        'RockMigrations',
      ],
      'installs' => [
      ],
    ];
  }

  public function init() {
    require_once("Block.php");
    $this->triggerMigrations();
  }

  /**
   * Add a single block file
   * @return void
   */
  public function addBlock($file, $namespace = "RMBlock") {
    $blocks = $this->blocks;
    require_once($file);
    $name = pathinfo($file, PATHINFO_FILENAME);
    $class = "\\$namespace\\$name";
    try {
      $block = new $class();
      $block->init();
      $blocks[$block->info()->name] = $block;
      ksort($blocks);
      $this->blocks = $blocks;
    } catch (\Throwable $th) {
      $this->warning($th->getMessage());
    }
  }

  /**
   * Scan dir and add blocks
   * @return void
   */
  public function addBlocks($dir, $namespace = "RMBlock") {
    $opt = ['extensions' => ['php']];
    foreach($this->wire->files->find($dir, $opt) as $file) {
      $this->addBlock($file, $namespace);
    }
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
    $this->log("Migrate Matrix Blocks");
    foreach($this->blocks as $name=>$file) {
      $block = $this->getBlock($name);
      if(!$block) return;
      $block->migrate();
    }
  }

  /**
   * Trigger the migrations
   */
  public function ___triggerMigrations() {
    $mx = $this;
    $this->addHookAfter("Modules::refresh", function(HookEvent $event) use($mx) {
      if(!$event->modules->isInstalled('RockMatrix')) return;
      $mx->migrate();
    });
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
