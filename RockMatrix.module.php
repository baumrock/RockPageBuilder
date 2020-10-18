<?php namespace ProcessWire;
use RockMatrix\Block;
use RockMatrix\BlocksArray;

/**
 * @author Bernhard Baumrock, 18.07.2020
 * @license COMMERCIAL DO NOT DISTRIBUTE
 * @link https://www.baumrock.com
 *
 * // TODO: Wenn ich auf "Gemeindezeitung" klicke... kommt immer eine Browserwarnung "Wollen Sie die Seite wirklich verlassen". Obwohl keine Änderungen mehr ungespeichert sind.
 */
require_once("Block.php");
require_once(__DIR__ . "/BlocksArray.php");
class RockMatrix extends WireData implements Module, ConfigurableModule {

  const prefix = 'rockmatrix_';
  const tags = 'RockMatrix';

  const field_demo = self::prefix."rmxmoduledemo";

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
    if(!$this->modules->isInstalled('FieldtypeRepeater')) {
      $this->modules->install('FieldtypeRepeater');
    }
    $this->setupDemoField();
    $this->addHookAfter("ProcessPageEdit::buildFormContent", $this, "buildBlockForm");
    $this->addHook("Page::getRmxBlock", $this, "getRmxBlock");
    $this->addHookAfter("Page::editable", $this, "hookBlockEditable");
    $this->include("init.php"); // load assets/RockMatrix/init.php
  }

  public function ready() {
    $this->include("ready.php"); // load assets/RockMatrix/ready.php
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
      if(substr($file, -9) === ".view.php") continue;
      $this->addBlock($file, $namespace);
    }
  }

  /**
   * Hook the page edit form of blocks
   * @return void
   */
  public function buildBlockForm(HookEvent $event) {
    $page = $event->process->getPage();
    if(!$page instanceof Block) return;
    $fs = $event->return;
    $page->prepareForm($fs);
    $page->buildForm($fs);
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
    if($name instanceof Block) $name = $name->info()->name;
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
   * Get Block page from given data
   */
  public function getBlockPage($data) {
    try {
      $page = $this->wire->pages->get((string)$data);
      if(!$page instanceof Block) return false;
      return $page;
    } catch (\Throwable $th) {
      $this->error($th->getMessage());
    }
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
  public function getRmxBlock($event) {
    $page = $event->object;
    if(!$page instanceof Block) throw new WireException("Page is not a RM Block");
    $event->return = $this->getBlockByTpl($page->template);
  }

  /**
   * Make sure that blocks are editable if the matrix page is editable
   * @return void
   */
  public function hookBlockEditable(HookEvent $event) {
    $page = $event->object;
    if(!$page instanceof Block) return;
    $editable = $event->return;

    // if page is already editable we exit early
    if($editable == true) return;

    // otherwise we make the block editable if the matrix page is editable
    $matrixPage = $page->getMatrixPage();
    if(!$matrixPage OR !$matrixPage->id) return;
    $event->return = $matrixPage->editable();
  }

  /**
   * Include file from assets folder
   */
  public function include($file) {
    $dir = $this->wire->config->paths->assets."RockMatrix";
    $file = "$dir/$file";
    $vars = ['mx' => $this];
    $opt = ['allowedPaths' => [$dir]];
    if(is_file($file)) $this->wire->files->include($file, $vars, $opt);
  }

  /**
   * Install demo fields
   * @return void
   */
  public function installDemo() {
    $rm = $this->rm();
    $rm->migrate([
      'fields' => [
        self::field_demo => [
          'type' => 'FieldtypeRockMatrix',
          'tags' => self::tags,
          'icon' => 'bug',
        ],
      ],
    ]);
    $rm->addFieldToTemplate(self::field_demo, "home");

    // now add all sample blocks and trigger the migration
    $this->addBlocks(__DIR__."/demo/", "RMDemo");
    $this->migrate();
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
  }

  /**
   * Remove all demo fields and templates
   * @return void
   */
  public function removeDemo() {
    $rm = $this->rm();
    $rm->deleteField(self::field_demo);
    foreach($this->blocks as $block) {
      if(strpos($block->info()->name, "RMDemo\\") !== 0) continue;
      $block->uninstall();
    }
  }

  /**
   * Get instance of RockMigrations
   * @return RockMigrations
   */
  public function rm() {
    return $this->wire->modules->get('RockMigrations');
  }

  /**
   * This shows the settings of the demo field (if installed)
   */
  public function setupDemoField() {
    $field = $this->fields->get(self::field_demo);
    if(!$field) return;

    $this->addBlocks(__DIR__."/demo/", "RMDemo");
    $this->addHookAfter('getAllowedBlocks', function($event) {
      $field = $event->arguments(0);
      if($field->name !== self::field_demo) return;
      $event->return->add('RMDemo\Textarea');
      $event->return->add('RMDemo\Markup');
      $event->return->add('RMDemo\Headline');
      $event->return->add('RMDemo\Image');
    });
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
      'description' => 'After adding a new matrix block (via code) you need to run migrations. You can either do this here or via calling $modules->get("RockMatrix")->migrate();',
    ]);
    if($this->input->post('TriggerMigrations')) $this->migrate();

    $inputfields->add([
      'type' => 'checkbox',
      'name' => 'InstallDemo',
      'label' => 'Install Demo Data',
      'description' => 'This will create a demo field and add it to the root page of your site to get you started quickly',
      'columnWidth' => 50,
    ]);
    if($this->input->post('InstallDemo')) $this->installDemo();

    $inputfields->add([
      'type' => 'checkbox',
      'name' => 'RemoveDemo',
      'label' => 'Remove Demo Data',
      'description' => 'This will remove all demo fields and templates without further asking!',
      'columnWidth' => 50,
    ]);
    if($this->input->post('RemoveDemo')) $this->removeDemo();

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
