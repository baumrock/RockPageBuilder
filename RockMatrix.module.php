<?php namespace ProcessWire;
/**
 * @author Bernhard Baumrock, 18.07.2020
 * @license COMMERCIAL DO NOT DISTRIBUTE
 * @link https://www.baumrock.com
 */
class RockMatrix extends WireData implements Module, ConfigurableModule {

  const prefix = 'rockmatrix_';

  public $blocks = [];

  public $mtime = 0;

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
        'FieldtypeRockMatrix',
        'InputfieldRockMatrix',
      ],
    ];
  }

  public function init() {
    require_once("Block.php");
  }

  /**
   * Add a single block file
   * @return void
   */
  public function addBlock($file, $namespace = "RMBlock") {
    $blocks = $this->blocks;
    if(!is_file($file)) throw new WireException("File $file not found");

    require_once($file);
    $name = pathinfo($file, PATHINFO_FILENAME);
    $class = "\\$namespace\\$name";
    try {
      $block = new $class();
      $block->setFile($file);
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
    require_once(__DIR__ . "/BlocksArray.php");
    return new BlocksArray();
  }

  /**
   * Module Migrations
   */
  public function migrate($uninstall = false) {
    // migrate all blocks
    $this->log("Migrate Matrix Blocks");
    foreach($this->blocks as $name=>$file) {
      $block = $this->getBlock($name);
      if(!$block) return;
      $block->migrate($uninstall);
    }

    // test field
    $this->rm()->createField("rmtest", "FieldtypeRockMatrix");
    $this->rm()->addFieldToTemplate("rmtest", "basic-page");
  }

  /**
   * Get instance of RockMigrations
   * @return RockMigrations
   */
  public function rm() {
    return $this->wire->modules->get('RockMigrations');
  }

  /**
  * Config inputfields
  * @param InputfieldWrapper $inputfields
  */
  public function getModuleConfigInputfields($inputfields) {

    $inputfields->add([
      'type' => 'checkbox',
      'name' => 'TriggerMigrations',
      'label' => 'Trigger Migrations',
    ]);
    if($this->input->post('TriggerMigrations')) $this->migrate();

    return $inputfields;
  }

  public function ___uninstall() {
    // remove all existing mx fields
    foreach($this->wire->fields as $f) {
      if($f->type instanceof FieldtypeRockMatrix) $this->rm()->deleteField($f);
    }

    // uninstall the fieldtype
    // this is a hack for preventing "module dependency failed" error
    $this->rm()->uninstallModule("FieldtypeRockMatrix");

    $this->log("Uninstall Matrix Blocks");
    foreach($this->blocks as $name=>$file) {
      $block = $this->getBlock($name);
      if(!$block) return;
      $this->log("Uninstall ".$block->info()->name);
      $block->uninstall();
    }
  }

  public function __debugInfo() {
    return [
      'blocks' => $this->blocks,
      'mtime' => $this->mtime,
    ];
  }
}
