<?php namespace ProcessWire;
use RockMatrix\Block;
use RockMatrix\BlocksArray;

/**
 * @author Bernhard Baumrock, 18.07.2020
 * @license COMMERCIAL DO NOT DISTRIBUTE
 * @link https://www.baumrock.com
 */
require_once(__DIR__ . "/BlocksArray.php");
class RockMatrix extends WireData implements Module, ConfigurableModule {

  const prefix = 'rockmatrix_';
  const tags = 'RockMatrix';

  const tpl_datapage = self::prefix."datapage";

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
    $this->addHook("Page::getRmBlock", $this, "getRmBlock");
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
   * Get allowed blocks for given field and page
   * @return array
   */
  public function ___getAllowedBlocks($field, $page) {
    return new BlocksArray();
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
   * Get block by template
   * @return Block
   */
  public function getBlockByTpl($tpl) {
    foreach($this->blocks as $block) {
      if($block->getTplName() === (string)$tpl) return $block;
    }
    return false;
  }

  /**
   * Get datapage
   * @return Page
   */
  public function getDatapage() {
    return $this->wire->pages->get([
      'parent' => 1,
      'template' => self::tpl_datapage,
    ]);
  }

  /**
   * Get rm block from page object
   * @return Block
   */
  public function getRmBlock($event) {
    $page = $event->object;
    if(!$page instanceof Block) throw new WireException("Page is not a RM Block");
    $event->return = $this->getBlockByTpl($page->template);
  }

  /**
   * Module Migrations
   */
  public function migrate() {
    $rm = $this->rm();

    // migrate all blocks
    $this->log("Migrate Matrix Blocks");
    foreach($this->blocks as $name=>$file) {
      $block = $this->getBlock($name);
      if(!$block) return;
      $block->migrate();
    }

    // data-page
    $rm->migrate([
      'templates' => [
        self::tpl_datapage => [
          'fields' => ['title'],
          'tags' => self::tags,
          'noChildren' => 1, // create pages only via API
          'noParents' => -1, // only one allowed
          'icon' => 'cubes',
          'sortfield' => '-created',
        ],
      ],
    ]);
    $rm->createPage("RockMatrixBlocks", null, self::tpl_datapage, 1, ['hidden', 'locked']);

    // test field
    $rm->createField("rmtest", "FieldtypeRockMatrix");
    $rm->addFieldToTemplate("rmtest", "basic-page");

    // test matrix item
    $rm->createPage("test", null, $block->getTpl(), $this->getDatapage());
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
    $rm = $this->rm();

    // remove all existing mx fields
    foreach($this->wire->fields as $f) {
      if($f->type instanceof FieldtypeRockMatrix) $rm->deleteField($f);
    }

    // uninstall the fieldtype
    // this is a hack for preventing "module dependency failed" error
    $rm->uninstallModule("FieldtypeRockMatrix");

    $this->log("Uninstall Matrix Blocks");
    foreach($this->blocks as $block) $block->uninstall();

    // remove datapage (template+page)
    $rm->deleteTemplate(self::tpl_datapage);
  }

  public function __debugInfo() {
    return [
      'blocks' => $this->blocks,
      'mtime' => $this->mtime,
    ];
  }
}
