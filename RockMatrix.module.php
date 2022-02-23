<?php namespace ProcessWire;
use RockMatrix\Block;
use RockMatrix\BlocksArray;

/**
 * @author Bernhard Baumrock, 18.07.2020
 * @license COMMERCIAL DO NOT DISTRIBUTE
 * @link https://www.baumrock.com
 */
require_once(__DIR__."/Block.php");
require_once(__DIR__."/BlocksArray.php");
class RockMatrix extends WireData implements Module, ConfigurableModule {

  const prefix = 'rockmatrix_';
  const tags = 'RockMatrix';

  const field_demo = self::prefix."rmxmoduledemo";

  const tpl_datapage = self::prefix."datapage";

  public $blocks = [];

  public $mtime = 0;

  private $preload = false;

  public static function getModuleInfo() {
    return [
      'title' => 'RockMatrix',
      'version' => '0.0.15',
      'summary' => 'Master module for RockMatrix Fieldtype + Inputfield',
      'autoload' => 90, // RockFields has 100 and loads earlier
      'singular' => true,
      'icon' => 'cubes',
      'requires' => [
        'RockMigrations>=0.3.7',
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
    $this->path = $this->wire->config->paths($this);

    // load autoload blocks now
    $this->addBlocks($this->path."blocks");

    $this->setupDemoField();
    $this->addHookAfter("ProcessPageEdit::buildFormContent", $this, "buildBlockForm");
    $this->addHook("Page::getRmxBlock", $this, "getRmxBlock");
    $this->addHookAfter("Page::editable", $this, "hookBlockEditable");
    $this->addHookAfter("User::hasPagePermission", $this, "hookImageEdit");
    $this->addHookAfter("ProcessPageList::find", $this, "hideDataPage");
    $this->addHookBefore('ProcessPageListRender::getNumChildren', $this, "hookNumChildren");

    $this->include("init.php"); // load assets/RockMatrix/init.php

    // TODO: check if that causes errors on uninstalling other modules
    // the readme had a note that migrate is not triggered automatically due to
    // that reason.
    if($rm = $this->rm()) $rm->watch($this);
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

    // if block was already added we do not add it again
    $name = pathinfo($file, PATHINFO_FILENAME);
    $class = "\\$namespace\\$name";
    require_once($file);
    try {
      $block = new $class();
      $block->setFile($file);
      $name = $block->info()->name;

      // if block already exists dont add and init it again
      if(array_key_exists($name, $this->blocks)) return;

      $block->init();
      $block->addSettingsField();
      $blocks[$name] = $block;
      ksort($blocks);
      $this->blocks = $blocks;

      // add block to rockmigrations watchlist
      $this->rm()->watch($file, false);
    } catch (\Throwable $th) {
      $this->warning($class.": ".$th->getMessage());
    }
  }

  /**
   * Scan dir and add blocks
   * @return void
   */
  public function addBlocks($dir, $namespace = "RMBlock") {
    $opt = ['extensions' => ['php']];
    foreach($this->wire->files->find($dir, $opt) as $file) {
      $name = pathinfo($file, PATHINFO_BASENAME);
      if($name === "init.php") continue;
      if(substr($file, -9) === ".view.php") continue;
      $this->addBlock($file, $namespace);
    }
  }

  /**
   * Hook the page edit form of blocks
   * @return void
   */
  public function buildBlockForm(HookEvent $event) {
    $this->preloadAssets();
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
   * This returns an empty block object, not the populated block!
   * @return Block
   */
  public function getRmxBlock($event) {
    $page = $event->object;
    if(!$page instanceof Block) throw new WireException("Page is not a RM Block");
    $event->return = $this->getBlockByTpl($page->template);
  }

  /**
   * Hide data page from page tree for non-superusers
   */
  public function hideDataPage(HookEvent $event) {
    if($this->showDataPage AND $this->wire->user->isSuperuser()) return;
    $dataPage = $event->pages->get("template=".self::tpl_datapage);
    $event->return = $event->return->remove($dataPage);
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
   * Make sure that images are editable and image actions are shown
   * @return void
   */
  public function hookImageEdit(HookEvent $event) {
    $permission = $event->arguments(0);
    if($permission !== 'page-edit-images') return;
    $page = $event->arguments(1);
    if(!$page instanceof Block) return;
    if(!$page->editable()) return;
    $event->return = true; // grant page-edit-images permission!
  }

  /**
   * Hook num children when datapage was removed
   */
  public function hookNumChildren(HookEvent $event) {
    if($this->showDataPage AND $this->wire->user->isSuperuser()) return;
    $page = $event->arguments(0);
    if($page->id === 1) $page->numChildren = $page->numChildren-1;
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
   * Load all files in directory as blocks for given field
   * @return void
   */
  public function loadBlocks($fieldname, $path, $namespace = 'RMBlock', $add=[]) {
    // add blocks to rockmatrix
    $this->addBlocks($path, $namespace);

    // get blocks
    $blocks = [];
    $options = ['extensions' => ['php']];
    foreach($this->wire->files->find($path, $options) as $file) {
      $name = pathinfo($file, PATHINFO_FILENAME);
      $blocks[] = "$namespace\\$name";
    }
    $blocks = array_merge($blocks, $add);

    // add blocks via hook
    $this->addHookAfter('getAllowedBlocks', function($event)
      use($fieldname, $blocks) {
      $field = $event->arguments(0);
      if($field->name !== $fieldname) return;
      $event->return->add($blocks);
    });
  }

  /**
   * Module Migrations
   */
  public function migrate() {
    $rm = $this->rm();
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
          'flags' => Template::flagSystem,
        ],
      ],
    ]);
    $rm->createPage("RockMatrixBlocks", null, self::tpl_datapage, 1, ['hidden', 'locked']);
  }

  /**
   * We preload some styles when a rockmatrix field is present on the page
   * for example the styles for the radio button need to be available when
   * it is used in a RockFields field. Otherwise the first loaded block will
   * have a messed markup: https://i.imgur.com/6rr2ZIX.png
   */
  public function preloadAssets() {
    if($this->preload) return;
    (new InputfieldRadios())->renderReady();
    $this->preload = true;
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
      'name' => 'showDataPage',
      'label' => 'Show datapage in tree for superusers',
      'checked' => $this->showDataPage ? 'checked' : '',
    ]);

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

  public function ___install() {
    $this->init();
    $this->migrate();
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
