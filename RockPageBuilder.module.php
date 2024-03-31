<?php

namespace ProcessWire;

use DirectoryIterator;
use RockPageBuilder\Block;
use RockPageBuilder\BlocksArray;
use RockPageBuilder\BlockSettingsArray;
use RockPageBuilder\FieldData;
use RockPageBuilderBlock\Widget;

/**
 * @author Bernhard Baumrock, 18.07.2020
 * @license COMMERCIAL DO NOT DISTRIBUTE
 * @link https://www.baumrock.com
 */

function rockpagebuilder(): RockPageBuilder
{
  return wire()->modules->get('RockPageBuilder');
}

require_once(__DIR__ . "/Block.php");
require_once(__DIR__ . "/BlocksArray.php");
class RockPageBuilder extends WireData implements Module, ConfigurableModule
{

  const debug = false;
  const prefix = 'rockpagebuilder_';
  const tags = 'RockPageBuilder';

  const tpl_datapage = self::prefix . "datapage";

  const field_blocks = self::prefix . "blocks";
  const field_widgets = self::prefix . "widgets";
  const field_eyebrow = self::prefix . "eyebrow";
  const field_teaser = self::prefix . "teaser";

  public $blocks = [];

  /** @var WireData */
  public $blockStylesCache;

  /** @var WireArray */
  public $blockSettings;

  public $createLessFile = false;

  private $defaultSettings = false;

  /**
   * Flag that is set when a block is being cloned
   * This makes it possible to attach create hooks only for new items
   * and prevent them from overwriting/resetting cloned field values.
   */
  public $isClone = false;

  public $loaded = [];

  public $mtime = 0;

  public $useWidgets = true;
  public $showBlocktype = true;
  public $showDataPage = false;

  private $preload = false;

  private $_saved;

  private $stylesAdded = false;

  public function init()
  {
    $this->wire('rockpagebuilder', $this);
    if (!$this->modules->isInstalled('FieldtypeRepeater')) {
      $this->modules->install('FieldtypeRepeater');
    }
    $this->path = $this->wire->config->paths($this);
    $this->_saved = $this->wire(new PageArray());
    $this->blockStylesCache = $this->wire(new WireData());

    // merge in settings from config.php file
    if (is_array($this->wire->config->rockpagebuilder)) {
      $this->data = array_merge(
        $this->data, // current settings
        $this->wire->config->rockpagebuilder // settings from config.php
      );
    }

    $this->installProcessModule();

    // hooks
    wire()->addHookAfter("ProcessPageEdit::buildForm",        $this, "buildForm");
    wire()->addHookAfter("ProcessPageEdit::buildFormContent", $this, "buildBlockForm");
    wire()->addHook("Page::getRmxBlock",                      $this, "getRmxBlock");
    wire()->addHookAfter("Page::render",                      $this, "addMagicStyles");
    wire()->addHookAfter("User::hasPagePermission",           $this, "hookImageEdit");
    wire()->addHookBefore("Inputfield::render",               $this, "addMagicInputfieldProperties");
    wire()->addHookAfter("Modules::refresh",                  $this, "removeUnusedTemplates");
    wire()->addHookAfter("ProcessPageEdit::buildFormContent", $this, "widgetHint");
    wire()->addHookAfter("ProcessPageEdit::buildFormContent", $this, "hookHideTitleField");
    wire()->addHookBefore("Modules::uninstall",               $this, "beforeUninstall");
    wire()->addHookAfter("Page::render",                      $this, "addMoveStyles");
    wire()->addHookAfter("Templates::saved",                  $this, "hookBlockMigrateFile");
    wire()->addHookAfter("Fields::saved",                     $this, "hookBlockMigrateFile");
    wire()->addHookAfter("Pages::saved",                      $this, "deleteOrphanBlocks");
    wire()->addHookProperty("Block::buttonLabel",             $this, "hookBlockButtonLabel");

    // hooks for access control
    $this->addHookAfter("Page::editable", $this, "hookBlockEditable");
    $this->addHookAfter("Page::trashable", $this, "hookRepeaterTrashable");

    // add styles for backend
    $this->addHookAfter("ProcessPageEdit::buildForm", $this, "addStyles");
    $this->addHookAfter("Inputfield::render", $this, "addStyles");
    $this->addHookAfter("ProcessRockPageBuilder::browserTitle", $this, "addStyles");

    // add builder() method to all pages
    $this->addHookMethod("Page::builder", $this, "builder");

    // add JS for frontend
    $this->addHookAfter("Page::render", function ($event) {
      if ($event->object->template == 'admin') return;
      $rf = $event->wire->rockfrontend;
      if (!$rf) return;
      if ($rf->loadVspace) $rf->scripts()->add(__DIR__ . "/scripts/vspace.js", "defer");
    });

    // rpb page save trigger
    $this->_saved = new PageArray();
    $this->addHookAfter("Pages::saved", $this, "triggerBlockPageSave");
    $this->addHookAfter("Pages::saved", $this, "cloneBlocks");

    // hide data page from tree
    $this->addHookAfter("ProcessPageList::find", $this, "hideDataPage");
    $this->addHookBefore('ProcessPageListRender::getNumChildren', $this, "hookNumChildren");

    $this->createBlockHook();
    $this->include("init.php"); // load templates/RockPageBuilder/init.php
    $this->addBlock(__DIR__ . "/Widget.php"); // always load the widget block

    // add all blocks from the templates folder to $this->blocks
    $this->addBlocks(
      dir: $this->wire->config->paths->templates . "RockPageBuilder",
      recursive: 3,
    );

    // now load all the added blocks according to the fields
    $this->loadUserBlocks();

    // add ajax endpoints
    $this->addHookAfter("/rockpagebuilder-vscale", $this, "saveVScaleValue");
    $this->addHookAfter("/rockpagebuilder-savesort", $this, "saveSortable");

    // create WireArray that holds the default settings
    require_once __DIR__ . "/BlockSettingsArray.php";
    $this->blockSettings = new BlockSettingsArray();

    // do several health checks
    $this->checkHealth();

    // TODO: check if that causes errors on uninstalling other modules
    // the readme had a note that migrate is not triggered automatically due to
    // that reason.
    if ($rm = $this->rm()) {
      // a priority of 0.9 will make it migrate after all other watched files
      // that have the default priority of 1
      $rm->watch($this, 0.9, ['force' => true]);
    }
  }

  public function ready()
  {
    $this->include("ready.php"); // load templates/RockPageBuilder/ready.php
    $this->parseFrontendAssets();
    $this->addFrontendAssets();

    // add magic field getter methods
    // this is to support $page->foo() calls instead of
    // $rockfrontend->html($page->edit("rockpagebuilder_something_foo"))
    $this->addMagicFieldMethods();

    if ($this->wire->page->template == 'admin') {
      $this->wire->config->js('RockPageBuilderBlocks', $this->blockNames());
    } else {
      $rf = $this->rockfrontend();
      if ($rf) $rf->styles()->addAll('/site/templates/RockPageBuilder', '', 3);
    }
  }

  /**
   * Add alfred icons to a repeaterpage
   */
  public function addAlfredIcons(RepeaterPage $block, &$icons, $opt)
  {
    if ($opt->noBlock) return;

    if ($opt->clone and $block->editable()) {
      $icons[] = (object)[
        'icon' => 'clone',
        'label' => $block->title,
        'tooltip' => "Clone Block #{$block->id}",
        'href' => $this->repeaterUrl("/clone/?block=$block"),
        'confirm' => $this->_('Do you really want to clone this element?'),
      ];
    }
    // show move icon only when more than 1 block
    $forPage = $block->getForPage();
    $forField = $block->getForField()->name;
    if ($forPage->get($forField)->count() > 1) {
      $icons[] = (object)[
        'icon' => $opt->addHorizontal ? 'movev' : 'moveh',
        'label' => $block->title,
        'tooltip' => "Move Block #{$block->id}",
        'class' => 'pw-modal',
        'href' => $block->getForPage()->editUrl . "&field=" . $block->getForField() . "&rpb-moveblock=$block",
        'suffix' => 'data-buttons="button.ui-button[type=submit]" data-autoclose data-reload',
      ];
    }

    if ($opt->trash and $block->trashable()) {
      $icons[] = (object)[
        'icon' => 'trash-2',
        'label' => $block->title,
        'tooltip' => "Trash Block #{$block->id}",
        'href' => $this->repeaterUrl("/trash/?block=$block"),
        'confirm' => $this->_('Do you really want to delete this element?'),
      ];
    }
  }

  /**
   * This method is responsible for adding the plus-icons for ALFRED to blocks
   */
  public function addAlfredOptions($data): WireData
  {
    $page = $data->page;
    $opt = $data->opt;

    $exit = true;
    if ($page instanceof Block) $exit = false;
    elseif ($page instanceof RepeaterPage) $exit = false;

    // early exit?
    if ($exit) return $opt;

    // is the block a widget?
    $isWidget = false;
    $widgetable = false;
    if ($page instanceof Block) {
      if ($page->isWidget()) $isWidget = true;
      else $widgetable = true;
    }
    $widget = $page->_widget ?: $page;

    // add pagebuilder specific attributes
    $opt->setArray([
      // setting specific to rockpagebuilder blocks
      'noBlock' => false, // prevent block icons if true
      'addTop' => null, // set to false to prevent icon
      'addBottom' => null, // set to false to prevent icon
      'addHorizontal' => null, // shortcut for addLeft + addRight
      'move' => true,
      'isWidget' => $isWidget, // is block saved in rockpagebuilder_widgets?
      'widgetStyle' => $isWidget, // make it orange
      'trash' => true, // will set the trash icon for rockpagebuilder blocks
      'clone' => true, // can item be cloned?
      'widgetable' => $widgetable, // can be converted into widget?
    ]);
    if ($widget instanceof Block) {
      $opt->type = $widget->getInfo()->title;
    }
    $opt->setArray($data->options);

    if ($opt->noBlock) {
      if ($opt->addTop !== true) $opt->addTop = false;
      if ($opt->addBottom !== true) $opt->addBottom = false;
      if ($opt->addHorizontal !== true) {
        $opt->addLeft = false;
        $opt->addRight = false;
      }
    }
    if ($opt->addTop !== false) {
      if ($widget instanceof Block) {
        $opt->addTop = $widget->rpbUrl("/add/?block=$widget&above=1");
      } elseif ($widget instanceof RepeaterPage) {
        $opt->addTop = $this->repeaterUrl("/add/?block=$widget&sort=" . $widget->sort);
      }
    }
    if ($opt->addBottom !== false) {
      if ($widget instanceof Block) {
        $opt->addBottom = $widget->rpbUrl("/add/?block=$widget");
      } elseif ($widget instanceof RepeaterPage) {
        $opt->addBottom = $this->repeaterUrl("/add/?block=$widget&sort=" . ($widget->sort + 1));
      }
    }
    if ($opt->addHorizontal === true) {
      $opt->addTop = false;
      $opt->addBottom = false;
      if ($widget instanceof Block) {
        $opt->addLeft = $widget->rpbUrl("/add/?block=$widget&above=1");
        $opt->addRight = $widget->rpbUrl("/add/?block=$widget");
      } elseif ($widget instanceof RepeaterPage) {
        $opt->addLeft = $this->repeaterUrl("/add/?block=$widget&sort=" . $widget->sort);
        $opt->addRight = $this->repeaterUrl("/add/?block=$widget&sort=" . ($widget->sort + 1));
      }
    }

    // setup blockid string
    $opt->blockid = "data-rpbblock=$widget";

    return $opt;
  }

  /**
   * Add backend stylesheet
   */
  public function addBackendStyle()
  {
    if ($this->stylesAdded) return;
    $this->stylesAdded = true;
    $this->wire->config->styles->add(
      $this->wire->config->urls($this) . "RockPageBuilder.min.css"
    );
  }

  /**
   * Add a single block file
   * @return void
   */
  public function addBlock($file, $namespace = "RockPageBuilderBlock")
  {
    $blocks = $this->blocks;
    if (!is_file($file)) throw new WireException("File $file not found");

    // check if the file is empty
    // empty files can be used to add existing blocks to fields
    // this means you can reuse blocks across several fields
    if (!filesize($file)) return;

    // do not add files from within dot-folders
    $folder = basename(dirname($file));
    if (str_starts_with($folder, ".")) return;

    // if block was already added we do not add it again
    $name = pathinfo($file, PATHINFO_FILENAME);
    try {
      // use require_once instead of PW classloader
      // because classLoader had issues when adding widgets (class xx not found)
      $class = "\\$namespace\\$name";
      require_once $file;
      $block = new $class();
      $block->setFile($file);
      $block->setMigrateFile($file);
      $name = $block->getInfo()->name;

      // if block already exists dont add and init it again
      if (array_key_exists($name, $this->blocks)) return;

      // check if we didnt forget to call parent::migrate in migrate() of block
      if ($this->wire->user->isSuperuser()) {
        $content = $this->wire->files->fileGetContents($file);
        $this->checkParent("migrate", $content, $name);
        $this->checkParent("__construct", $content, $name);
      }

      // trigger init() of block
      if (method_exists($block, "init")) $block->init();

      // add magic methods to this block
      // this adds defaults() and onCreate() etc
      $this->rm()->magic()->addMagicMethods($block);

      $block->addSettingsField();
      $blocks[$name] = $block;
      ksort($blocks);
      $this->blocks = $blocks;

      // add block to rockmigrations watchlist
      $this->rm()->watch($file, false);
    } catch (\Throwable $th) {
      $this->warning($class . ": " . $th->getMessage());
      if ($this->wire->user->isSuperuser() and function_exists("bd")) {
        bd(Debug::backtrace(), $th->getMessage());
      }
    }
  }

  /**
   * Scan dir and add blocks
   *
   * By default this will recurse into subdirectories (PW default setting = 10)
   *
   * You can disable that by setting it to one level:
   * $rm->addBlocks(..., ..., 1);
   *
   * @return void
   */
  public function addBlocks($dir, $namespace = "RockPageBuilderBlock", $recursive = null)
  {
    $opt = ['extensions' => ['php']];
    if ($recursive !== null) $opt['recursive'] = $recursive;
    foreach ($this->wire->files->find($dir, $opt) as $file) {
      $name = pathinfo($file, PATHINFO_BASENAME);
      if (strpos($name, ".") === 0) continue; // no dot-files
      if ($name === "init.php") continue;
      if (substr($file, -9) === ".view.php") continue;

      // check if block has already been loaded
      // This is to make RockBlocks work, because there we load files either from
      // the sites directory or if no file exists we load the one from the modules
      // folder.
      $cls = $namespace . "\\" . substr($name, 0, -4);
      if (in_array($cls, $this->blockClasses())) continue;

      $this->addBlock($file, $namespace);
    }
  }

  /**
   * Add frontend styles via RockFrontend
   */
  public function addFrontendAssets()
  {
    /** @var RockFrontend $rf */
    if ($this->wire->page->template == 'admin') return;
    $dir = __DIR__ . "/assets/";

    // if rockfrontend is not installed styles must be added manually!
    if (!$rf = $this->wire->modules->get('RockFrontend')) return;

    // always add global frontend styles
    $rf->styles()->add(__DIR__ . "/assets/RockPageBuilder.min.css");

    // load RockPageBuilder frontend assets
    if ($this->wire->user->hasPermission('page-edit')) {
      $rf->scripts()->add(__DIR__ . "/lib/Sortable.min.js");
      $rf->scripts()->add($dir . "frontend-loggedin.min.js");
      $rf->styles()->add($dir . "frontend-loggedin.min.css");
    }
  }

  /**
   * Attach magic field methods
   * Makes it possible to access field "rockpagebuilder_foo_bar" as $page->bar()
   */
  public function addMagicFieldMethods($page = null, $tpl = null)
  {
    if ($page) {
      $fields = $tpl->fields;
      foreach ($fields as $field) {
        $fieldname = $field->name;
        $parts = explode("_", $fieldname);
        $methodname = array_pop($parts);

        // add the dynamic method via hook to the page
        $this->wire->addHookMethod(
          "Page(template=$tpl)::$methodname",
          function ($event) use ($fieldname, $methodname) {
            // get field value of original field
            $page = $event->object;
            $mode = $event->arguments(0);
            /** @var RockFrontend $rf */
            $rf = $this->wire->modules->get('RockFrontend');

            // mode 3 --> return unformatted html
            if ($mode === 3) {
              $val = $page->getUnformatted($fieldname);
              if ($rf) $val = $rf->html($val);
              $event->return = $val;
              return;
            }

            // mode 2 --> return unformatted value
            if ($mode === 2) {
              $event->return = $page->getUnformatted($fieldname);
              return;
            }

            // mode 1 --> return formatted value
            if ($mode) {
              $event->return = $page->getFormatted($fieldname);
              return;
            }

            // default mode --> return editable field
            $val = $page->edit($fieldname);
            if (is_string($val)) {
              if ($rf) $val = $rf->html($val);
            }
            $event->return = $val;
          },
          // we attach the hook as early as possible
          // that means if other hooks kick in later they have priority
          // this is to make sure we don't overwrite $page->editable() etc.
          ['priority' => 0]
        );
      }
      return;
    }

    // loop over all available templates and create runtime page objects
    foreach ($this->wire->templates as $tpl) {
      $p = $this->wire->pages->newPage(['template' => $tpl]);
      $this->addMagicFieldMethods($p, $tpl);
    }
  }

  /**
   * Add magic inputfield properties
   * @return void
   */
  public function addMagicInputfieldProperties(HookEvent $event)
  {
    /** @var Inputfield $f */
    $f = $event->object;
    if (!$field = $f->hasField) return;

    if ($field->get('rpb-nolabel')) {
      $f->wrapClass('rpb-nolabel');
      $f->label = false;
      $f->skipLabel = Inputfield::skipLabelBlank;
    }

    if ($field->get('rpb-smallpadding')) {
      $f->wrapClass('rpb-pd5');
    }
  }

  public function addMagicStyles(HookEvent $event)
  {
    $html = $event->return;
    if (!strpos($html, "#rpbstyle-")) return;
    foreach ($this->blockStylesCache as $id => $str) {
      // markup can either be in quotes (latte) or without quotes (php)
      $html = str_replace("\"$id\"", $str, $html);
      $html = str_replace("$id", $str, $html);
    }
    $event->return = $html;
  }

  /**
   * Styles that are injected when moving a block on the frontend
   */
  public function addMoveStyles(HookEvent $event)
  {
    if (!$id = $this->wire->input->get('rpb-moveblock')) return;

    $style = file_get_contents(__DIR__ . "/assets/move.css");

    $style = str_replace("#rpb-moveblock-id", "#rpb_$id", $style);
    $style = str_replace("#repeater-moveblock-id", "#Inputfield_repeater_item_$id", $style);

    $event->return = str_replace(
      "</head>",
      "<style>$style</style></head>",
      $event->return
    );
  }

  /**
   * Add stylesheet to pw admin
   */
  public function addStyles(HookEvent $event)
  {
    // add style either when a rockpagebuilder field is in the editor
    // or when we are editing a rockpagebuilder block
    if ($this->backendStylesAdded) return;
    $this->addBackendStyle();
    $this->backendStylesAdded = true;
  }

  /**
   * Remove fields before uninstall
   */
  public function beforeUninstall(HookEvent $event)
  {
    $module = $event->arguments(0);
    if ($module != 'RockPageBuilder') return;
    $rm = $this->rm();
    $rm->deletePage("parent=2,name=rockpagebuilder");
    $rm->deleteTemplates("tags=RockPageBuilder");
    $rm->deleteFields("tags=RockPageBuilder");
  }

  /**
   * Return array of all block classes
   * eg
   * RockPageBuilder\Foo
   * RockPageBuilder\Bar
   * RockPageBuilder\Baz
   */
  public function blockClasses(): array
  {
    return array_keys($this->blocks);
  }

  /**
   * Does block with given name exist?
   */
  public function blockExists($name, $prefix = "RockPageBuilderBlock\\"): bool
  {
    $name = $prefix . $name;
    return !!$this->getBlock($name);
  }

  /**
   * Return array of names of all blocks
   */
  public function blockNames($namespace = false): array
  {
    $names = [];
    foreach ($this->blocks as $block) {
      $name = $block->className();
      if ($namespace) $name = "$namespace\\$name";
      $names[] = $name;
    }
    return $names;
  }

  /**
   * See IntelliSense/Page.php for docs
   */
  public function builder(HookEvent $event)
  {
    /** @var Page $page */
    $page = $event->object;
    try {
      $html = $page->getFormatted(self::field_blocks)->renderBlock(!!$event->arguments(0));
      $event->return = $html;
      if ($this->wire->modules->isInstalled('RockFrontend')) {
        /** @var RockFrontend $rf */
        $rf = $this->wire->modules->get('RockFrontend');
        $event->return = $rf->html($html);
      }
    } catch (\Throwable $th) {
      $msg = $th->getMessage();
      $this->log($msg);
      if ($this->wire->config->debug) $event->return = $msg;
    }
  }

  /**
   * Hook the page edit form of blocks
   * @return void
   */
  public function buildForm(HookEvent $event)
  {
    $page = $event->process->getPage();
    $form = $event->return;

    $addClass = false;
    if ($page instanceof Block) $addClass = true;
    if ($page instanceof RepeaterPage) $addClass = true;
    if ($addClass) $form->addClass('rpb-form rpb-hidetabs');

    if (self::debug) $form->addClass('rpb-debug');
  }

  /**
   * Hook the page edit form of blocks
   * @return void
   */
  public function buildBlockForm(HookEvent $event)
  {
    $page = $event->process->getPage();
    $this->preloadAssets($page);
    if (!$page instanceof Block) return;

    $fs = $event->return;
    $page->prepareForm($fs);
    $page->buildForm($fs);

    // add backend js file
    rockmigrations()->addScripts(__DIR__ . "/assets/backend.js");

    // add link to rpb page
    $sudo = $this->wire->user->isSuperuser();
    if ($sudo and !$this->wire->input->get('modal')) {
      $fs->add([
        'type' => 'markup',
        'icon' => 'link',
        'label' => 'Block-Pages',
        'value' => $this->renderBlockLinks($page),
        'notes' => 'This shows all pages that contain the current block',
      ]);
    }
  }

  /**
   * Several health checks
   */
  public function checkHealth()
  {
    // if rockfrontend is installed check that the version matches
    if ($this->wire->modules->isInstalled('RockFrontend')) {
      /** @var RockFrontend $rf */
      $rf = $this->wire->modules->get('RockFrontend');
      if (method_exists($rf, "getModuleInfo")) {
        $v = $rf->getModuleInfo()['version'];
        $version = "1.16.1";
        if (version_compare($v, $version) < 0) {
          $this->warning("Please update RockFrontend to version $version+");
        }
      }
    }
  }

  /**
   * Check if we forgot to call parent::xxx
   * @return void
   */
  public function checkParent($method, $content, $name)
  {
    if ($method == 'migrate') return;
    $fu = strpos($content, "function $method(");
    $fuParent = strpos($content, "parent::$method(")
      or strpos($content, "parent::___$method(");
    if ($fu and $fuParent < $fu) {
      $this->error("Block $name has a $method() method but does not call"
        . " parent::$method()");
    }
  }

  /**
   * Clone default blocksettings ready to be used and modified in a rpb block
   * @return BlockSettingsArray
   */
  public function ___cloneBlockSettings(RockFieldsField $field, Block $block)
  {
    $settings = clone $this->blockSettings;
    if ($this->defaultSettings) {
      $this->defaultSettings->__invoke($settings, $field, $block);
    }
    return $settings;
  }

  /**
   * This hook ensures that when a page is cloned that all rpb blocks of that
   * page are clones as well and that the new page holds individual copies
   * and not only references to the original blocks.
   * @return void
   */
  public function cloneBlocks(HookEvent $event)
  {
    $page = $event->arguments(0);
    // db($page, "page $page was saved");

    // find all rpb fields
    $fields = $this->getBlockFields($page);
    foreach ($fields as $field) {
      // db($field, "found rpb field on saved page $page");

      // check if references match
      $blocks = $page->get($field->name);
      if (!$blocks instanceof FieldData) continue;
      if (!$blocks->count()) continue;
      $rpbPage = $blocks->first()->getBlockPage();
      if ($page->id != $rpbPage->id) {
        // db($rpbPage, 'rpb page does not match! resetting field...');

        // reset the field of the current page
        $newData = $blocks->getNew();

        // add cloned items
        foreach ($blocks as $block) {
          /** @var Block $clone */
          $clone = $this->wire->pages->clone($block);
          $clone->of(false);
          $fieldvalues = $block->getArray();
          $clone->setArray($fieldvalues);
          $clone->save();
          $clone->setBlockReference($page, $field);
          $newData->add($clone);
        }

        $page->setAndSave($field->name, $newData);
      }
    }

    // db("--- done ---");
  }

  /**
   * Create new block for field
   * @return void
   */
  public function createBlockHook()
  {
    if (!$this->wire->user) return;
    if (!$this->wire->user->isSuperuser()) return;
    $this->wire->addHookAfter("/rpb-create-block/", function ($event) {
      if (!$name = $this->wire->input->get('name', 'string')) return "invalid name";
      if (!$field = $this->wire->input->get('field', 'string')) return "invalid field";

      // make sure we have a valid name
      // 7 test Nummer 5 --> TestNummer5
      $name = ucfirst($this->wire->sanitizer->camelCase($name));
      $notallowed = ['__halt_compiler', 'abstract', 'and', 'array', 'as', 'break', 'callable', 'case', 'catch', 'class', 'clone', 'const', 'continue', 'declare', 'default', 'die', 'do', 'echo', 'else', 'elseif', 'empty', 'enddeclare', 'endfor', 'endforeach', 'endif', 'endswitch', 'endwhile', 'eval', 'exit', 'extends', 'final', 'for', 'foreach', 'function', 'global', 'goto', 'if', 'implements', 'include', 'include_once', 'instanceof', 'insteadof', 'interface', 'isset', 'list', 'namespace', 'new', 'or', 'print', 'private', 'protected', 'public', 'require', 'require_once', 'return', 'static', 'switch', 'throw', 'trait', 'try', 'unset', 'use', 'var', 'while', 'xor'];
      if (in_array(strtolower($name), $notallowed)) {
        die("Name $name is not allowed - please choose another name for your block!");
      }

      // create short fieldname
      $short = $field;
      if (strpos($field, "rockpagebuilder_") === 0) $short = substr($field, 16);
      elseif (strpos($field, "rockpagebuilderblock_") === 0) $short = substr($field, 21);

      $folder = $this->wire->config->paths->templates . "RockPageBuilder/$short";
      if (!is_dir($folder)) mkdir($folder);
      $name = ucfirst($name);
      $subfolder = "$folder/$name";
      if (!is_dir($subfolder)) mkdir($subfolder);

      // alfred installed?
      $alfred = '';
      if ($this->wire->modules->isInstalled('RockFrontend')) {
        $alfred = ' <?= $rockfrontend->alfred($page) ?>';
      }

      // if a block with given name exists we create an empty file
      // this means we reuse the existing block on this field
      if ($this->blockExists($name)) {
        $blockFile = "$subfolder/$name.php";
        if (!is_file($blockFile)) $this->wire->files->filePutContents($blockFile, "");
        die('success');
      }

      // block file
      if ($this->createPhp == 'advanced') {
        $this->stub("Block-advanced.txt", [
          "{name}" => $name,
          "{namelower}" => strtolower($name),
        ], "$subfolder/$name.php");
      } else {
        $this->stub("Block.txt", [
          "{name}" => $name,
          "{namelower}" => strtolower($name),
        ], "$subfolder/$name.php");
      }

      // view files
      if ($this->createView == 'latte') {
        $this->stub("Block.latte", [
          '{name}' => $name,
          '{cls}' => "rpb-" . strtolower($name),
          '{alfred}' => $this->wire->modules->isInstalled('RockFrontend')
            ? '{alfred($block)}'
            : '',
        ], "$subfolder/$name.latte");
      } else {
        $latteNote = $this->stub('latteNote.txt');
        if ($this->createView == 'php') $latteNote = '';
        $this->stub("Block.view.txt", [
          "{name}" => $name,
          "{cls}" => "rpb-" . strtolower($name),
          "{alfred}" => $alfred,
          "{latteNote}" => $latteNote,
        ], "$subfolder/$name.view.php");
      }

      // less file
      if ($this->createLessFile) {
        $this->stub(
          "Block.less",
          ['{cls}' => "rpb-" . strtolower($name)],
          "$subfolder/$name.less"
        );
      }

      die('success');
    });
  }

  /**
   * Create migrate file (triggered by tpl/field save hook)
   */
  private function createBlockMigrateFile($tpl)
  {
    $block = $this->getBlockByTpl($tpl);
    if (!$block) return;
    if (!$file = $block->yaml) return;
    if (!$block->getInfo()->yaml) return;

    $dir = dirname($file);
    if (!is_writable($dir)) {
      $this->warning("Directory not writable - cannot write migration file to $dir");
      return;
    }

    $rm = $this->rm();
    $file = $rm->toPath($file);
    $tplcode = $rm->getCode($tpl, 2);
    $fieldcode = [];
    foreach ($tpl->fields as $field) {
      // We only save field migrations to the yaml of this block if the
      // prefix matches. This is to make sure we don't add global site fields
      // to the blocks yaml file.
      if (!str_starts_with($field->name, $block::prefix)) continue;

      // get a fresh copy of the field
      // ugly hack because otherwise the field as a weird flag status
      $field = $this->wire->fields->get($field->id);
      $code = $rm->getCode($field, 2);
      // if the field still has the systemoverride status we reset it
      if ($code['flags'] == Field::flagSystemOverride) $code['flags'] = 0;
      $fieldcode[$field->name] = $code;
    }
    $data = [
      'fields' => $fieldcode,
      'templates' => [
        $tpl->name => $tplcode,
      ],
    ];
    $yaml = $rm->yaml($file, $data);
    if ($yaml) {
      $this->log("Saved migration data to $file");
    }
  }

  /**
   * Set default settings callback
   */
  public function defaultSettings($callback)
  {
    $this->defaultSettings = $callback;
  }

  /**
   * Hook executed after page was saved to trash orphan RPB blocks
   */
  public function deleteOrphanBlocks(HookEvent $event): void
  {
    $page = $event->arguments(0);
    $fields = $this->getBlockFields($page);
    if (!$fields->count) return;

    // don't execute this hook on a save that was triggerd by RPB
    // otherwise it will instantly delete newly added blocks
    if ($page->rockpagebuilderTriggerSave) return;

    // delete all unused blocks
    $unused = $this->getUnusedBlockIds();
    // bd($unused);
    foreach ($unused as $id) {
      $p = $this->wire->pages->get($id);
      $p->trash();
    }

    // BACKUP: Old version that was never used, kept as backup
    // // we loop all pagebuilder fields of that page
    // $fields = $this->getBlockFields($page);
    // foreach ($fields as $field) {
    //   // get ids of blocks that are saved in that field
    //   $blocks = $page->getFormatted($field->name);
    //   if (!$blocks instanceof FieldData) continue;
    //   $keep = $blocks->implode(',', 'id');

    //   // now get block ids from pages_meta table of blocks that have
    //   // been stored on that page on that field but are not any more
    //   $sql = "SELECT *
    //     FROM `pages_meta`
    //     WHERE `name` = 'RockPageBuilder'
    //     AND `data` = '\"$page-$field\"'
    //   ";
    //   if ($keep) $sql .= "AND `source_id` NOT IN ($keep)";
    //   $query = $this->wire->database->prepare($sql);
    //   $query->execute();

    //   // trash all found orphan blocks
    //   while ($row = $query->fetch(\PDO::FETCH_ASSOC)) {
    //     $id = $row['source_id'];
    //     $this->wire->log("Delete orphan block $id");
    //     $block = $this->wire->pages->get($id);
    //     $this->wire->pages->trash($block);

    //     // add meta data to identify blocks later
    //     // thats just a matter of caution and not used at the moment
    //     $block->meta('rpb-orphan', 1);
    //   }
    // }
  }

  /**
   * Get allowed blocks for given field and page
   * @return array
   */
  public function ___getAllowedBlocks($field, $page)
  {
    return new BlocksArray();
  }

  /**
   * Get block by array key
   * @return false|Block
   */
  public function getBlock($key)
  {
    if ($key instanceof Block) $key = $key->getInfo()->name;
    if (!array_key_exists($key, $this->blocks)) return false;
    return $this->blocks[$key];
  }

  /**
   * Get block by name, like Text, Downloads
   * @return Block
   */
  public function getBlockByName($name)
  {
    foreach ($this->blocks as $block) {
      if ($block->className() === (string)$name) return $block;
    }
    return false;
  }

  /**
   * Get block by template
   * @return Block
   */
  public function getBlockByTpl($tpl)
  {
    foreach ($this->blocks as $block) {
      if ($block->getTplName() === (string)$tpl) return $block;
    }
    return false;
  }

  /**
   * Return a WireArray containing all rpb fields of given page
   * @return WireArray
   */
  public function getBlockFields(Page $page)
  {
    $fields = $this->wire(new WireArray());
    foreach ($page->fields as $field) {
      if ($field->type instanceof FieldtypeRockPageBuilder) $fields->add($field);
    }
    return $fields;
  }

  /**
   * Get Block page from given data
   */
  public function getBlockPage($data)
  {
    try {
      $page = $this->wire->pages->get((string)$data);
      if (!$page instanceof Block) return false;
      return $page;
    } catch (\Throwable $th) {
      $this->error($th->getMessage());
    }
  }

  /**
   * Get all children of an inputfield wrapper recursively
   * @return array
   */
  public function getChildrenRecursively(InputfieldWrapper $wrapper, &$items = [])
  {
    $items = $items ?: [];
    foreach ($wrapper->children() as $child) {
      $items[] = $child;
      // if it is a wrapper additionally add all its children
      if ($child instanceof InputfieldWrapper) $this->getChildrenRecursively($child, $items);
    }
    return $items;
  }

  /**
   * Get datapage
   * @return Page
   */
  public function getDatapage()
  {
    return $this->wire->pages->get([
      'parent' => 1,
      'template' => self::tpl_datapage,
    ]);
  }

  /**
   * Method to get fieldname to support short folder names
   */
  private function getFieldname($name)
  {
    if ($this->wire->fields->get("rockpagebuilderblock_$name")) return "rockpagebuilderblock_$name";
    if ($this->wire->fields->get("rockpagebuilder_$name")) return "rockpagebuilder_$name";
    return $name;
  }

  /**
   * Get rm block from page object
   * This returns an empty block object, not the populated block!
   * @return Block
   */
  public function getRmxBlock($event)
  {
    $page = $event->object;
    if (!$page instanceof Block) throw new WireException("Page is not a RM Block");
    $event->return = $this->getBlockByTpl($page->template);
  }

  /**
   * Get array of all unused blocks
   * @param int $minAge Minimum age in seconds
   * @return array
   * @throws WireException
   */
  public function getUnusedBlockIds($minAge = 10): array
  {
    $ids = $this->wire->pages->findIDs([
      'include' => 'all',
      'parent' => $this->getDatapage(),
      ['id', '!=', $this->getUsedBlockIds()],
      'created<' => time() + $minAge,
    ]);
    return $ids;
  }

  /**
   * Get array of all used blocks
   */
  public function getUsedBlockIds(): array
  {
    $ids = [];

    // get all pagebuilder fields
    $fields = $this->wire->fields->findByType("FieldtypeRockPageBuilder");
    foreach ($fields as $field) {
      // find all pages that have blocks in that field
      $pages = $this->wire->pages->findRaw("include=all,$field!=''", $field->name);
      foreach ($pages as $data) {
        if ($data === "[]") continue;
        $arr = json_decode($data);
        foreach ($arr as $block) $ids[] = $block->id;
      }
    }

    return $ids;
  }

  /**
   * Get pages that reference the given block/widget
   * @return PageArray
   */
  public function ___getWidgetPages($block)
  {
    $pages = new PageArray();
    try {
      require_once __DIR__ . "/Widget.php";
      $widget = new Widget();
      $widgets = $this->wire->pages->find([
        'template' => $widget->getTplName(),
        $widget::field_block => $block,
      ]);
      foreach ($widgets as $w) $pages->add($w->getBlockPage());
    } catch (\Throwable $th) {
      $this->log($th->getMessage());
    }
    return $pages;
  }

  /**
   * Hide data page from page tree for non-superusers
   */
  public function hideDataPage(HookEvent $event)
  {
    if ($this->showDataPage and $this->wire->user->isSuperuser()) return;
    $dataPage = $event->pages->get("template=" . self::tpl_datapage);
    $event->return = $event->return->remove($dataPage);
  }

  /**
   * Adds the property "buttonLabel" to every block so that we can sort buttons
   * @param HookEvent $event
   * @return void
   */
  protected function hookBlockButtonLabel(HookEvent $event): void
  {
    $block = $event->object;
    $event->return = $block->getInfo()->title;
  }

  /**
   * Make sure that blocks are editable if the rpb page is editable
   * @return void
   */
  public function hookBlockEditable(HookEvent $event)
  {
    $page = $event->object;
    if (!$page instanceof Block) return;
    $editable = $event->return;

    // if page is already editable we exit early
    if ($editable == true) return;

    // otherwise we make the block editable if the rpb page is editable
    $rpbPage = $page->getBlockPage();
    if (!$rpbPage or !$rpbPage->id) return;
    $event->return = $rpbPage->editable();
  }

  /**
   * Create yaml file whenever a field or template is saved
   */
  public function hookBlockMigrateFile(HookEvent $event)
  {
    if ($this->rm()->ismigrating) return;
    $saved = $event->arguments(0);
    if ($saved instanceof Template) $this->createBlockMigrateFile($saved);
    elseif ($saved instanceof Field) {
      foreach ($this->wire->templates as $tpl) {
        if (!$tpl->fields->has($saved)) continue;
        $this->createBlockMigrateFile($tpl);
      }
    }
  }

  /**
   * Hide title field if block has setting "hideTitle"
   */
  public function hookHideTitleField(HookEvent $event)
  {
    $block = $event->process->getPage();
    if (!$block instanceof Block) return;
    if (!$block->getInfo()->hideTitle) return;
    $event->return->remove('title');
  }

  /**
   * Make sure that images are editable and image actions are shown
   * @return void
   */
  public function hookImageEdit(HookEvent $event)
  {
    $permission = $event->arguments(0);
    if ($permission !== 'page-edit-images') return;
    $page = $event->arguments(1);
    if (!$page instanceof Block) return;
    if (!$page->editable()) return;
    $event->return = true; // grant page-edit-images permission!
  }

  /**
   * Hook num children when datapage was removed
   */
  public function hookNumChildren(HookEvent $event)
  {
    if ($this->showDataPage and $this->wire->user->isSuperuser()) return;
    $page = $event->arguments(0);
    if ($page->id === 1) $page->numChildren = $page->numChildren - 1;
  }

  /**
   * Make repeater items trashable for non superusers
   */
  public function hookRepeaterTrashable(HookEvent $event): void
  {
    $repeater = $event->object;
    if (!$repeater instanceof RepeaterPage) return;
    if ($this->wire->user->isSuperuser()) return;

    // get the page where the repeater lives on
    $page = $repeater->getForPage();
    if ($page instanceof Block) {
      // the repeater item is trashable if the forpage is editable
      $event->return = $page->editable();
    }
  }

  /**
   * Include file from assets folder
   */
  public function include($file)
  {
    $dir = $this->wire->config->paths->templates . "RockPageBuilder";
    $file = "$dir/$file";
    $vars = ['mx' => $this];
    $opt = ['allowedPaths' => [$dir]];
    if (is_file($file)) $this->wire->files->include($file, $vars, $opt);
  }

  /**
   * Install ProcessModule if not yet installed
   * @return void
   */
  public function installProcessModule()
  {
    if ($this->wire->modules->isInstalled('ProcessRockPageBuilder')) return;
    $this->wire->modules->install('ProcessRockPageBuilder');
  }

  /**
   * Load all files in directory as blocks for given field
   * @return void
   */
  public function loadBlocks($fieldname, $path, $namespace = 'RockPageBuilderBlock', $add = [])
  {
    // this is to support short folder names in /site/templates/RockPageBuilder
    $fieldname = $this->getFieldname($fieldname);

    // get blocks
    if ($fieldname === self::field_widgets) {
      // on the widget field all blocks are allowed
      $blocks = $this->blockNames($namespace);
      $blocks = array_filter($blocks, function ($item) {
        return $item !== "RockPageBuilderBlock\Widget";
      });
    } else {
      // regular rockpagebuilder field
      // we need to find which blocks are allowed by files
      $blocks = [];
      $options = ['extensions' => ['php']];
      foreach ($this->wire->files->find($path, $options) as $file) {
        $name = pathinfo($file, PATHINFO_FILENAME);
        if (strpos($name, ".") === 0) continue; // no dot-files
        $blocks[] = "$namespace\\$name";
      }
      $blocks = array_merge($blocks, $add);
    }

    // we automatically add the widget block to the default blocks field
    // so that users can instantly convert any block into a widget
    if ($fieldname === self::field_blocks) {
      if ($this->useWidgets) $blocks[] = "RockPageBuilderBlock\\Widget";
      $blocks = array_unique($blocks);
    }

    // add blocks via hook
    $this->addHookAfter(
      'getAllowedBlocks',
      function ($event) use ($fieldname, $blocks) {
        $field = $event->arguments(0);
        if ($field->name !== $fieldname) return;
        $event->return->add($blocks);
      }
    );
  }

  /**
   * Same as loadBlocks but you can provide multiple folders and files to load
   *
   * Usage:
   * $rpb->loadBlocksArray('your_field', [
   *   ['/path/to/folder' => 'Your\Namespace'],
   *   ['/path/to/file.php' => 'Your\Namespace'],
   * ])
   */
  public function loadBlocksArray($fieldname, $arr)
  {
    $blocks = [];
    foreach ($arr as $dir => $namespace) {
      $blocks = [];
      if (is_file($dir)) {
        $file = $dir;
        $this->addBlock($file, $namespace);
        $name = pathinfo($file, PATHINFO_FILENAME);
        if (strpos($name, ".") === 0) continue; // no dot-files
        $blocks[] = "$namespace\\$name";
      } elseif (is_dir($dir)) {
        $this->addBlocks($dir, $namespace);
        $options = ['extensions' => ['php']];
        foreach ($this->wire->files->find($dir, $options) as $file) {
          $name = pathinfo($file, PATHINFO_FILENAME);
          if (strpos($name, ".") === 0) continue; // no dot-files
          $blocks[] = "$namespace\\$name";
        }
      } else throw new WireException("Invalid array key - must be file or directory");

      // add blocks via hook
      $this->addHookAfter('getAllowedBlocks', function ($event)
      use ($fieldname, $blocks) {
        $field = $event->arguments(0);
        if ($field->name !== $fieldname) return;
        $event->return->add($blocks);
      });
    }
  }

  /**
   * Load all blocks from templates folder
   * @return void
   */
  public function loadUserBlocks()
  {
    $folder = $this->wire->config->paths->templates . "RockPageBuilder";
    if (!is_dir($folder)) $this->wire->files->mkdir($folder);
    // we make sure that the widgets folder exists
    // otherwise no widget blocks will be allowed
    if (!is_dir("$folder/widgets")) $this->wire->files->mkdir("$folder/widgets");
    foreach (new DirectoryIterator($folder) as $fileInfo) {
      if ($fileInfo->isDot()) continue;
      if (!$fileInfo->isDir()) continue;
      $fieldname = basename($fileInfo->getPathname());
      $this->loadBlocks($fieldname, $fileInfo->getPathname());
    }
  }

  /**
   * Module Migrations
   */
  public function migrate()
  {
    $rm = $this->rm();
    foreach ($this->blocks as $name => $file) {
      $block = $this->getBlock($name);
      if (!$block) return;
      if ($rm->doMigrate($block)) {
        $block->migrateInitial();
        $block->migrateBeforeYaml();
        $block->migrateYaml();
        $block->migrate();
        $block->migrateAfterYaml();
      } else $rm->log("--- Skipping $name (no change)");
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
          'sortfield' => '-id',
          'flags' => Template::flagSystem,
        ],
      ],
    ]);
    $rm->createPage(
      parent: 1,
      title: "RockPageBuilderBlocks",
      template: self::tpl_datapage,
      status: ['hidden', 'locked']
    );

    // add one rpb field
    if (!$rm->getField(self::field_blocks, true)) {
      $rm->createField(self::field_blocks, 'RockPageBuilder', [
        'label' => 'Content-Elements',
        'tags' => self::tags,
        'icon' => 'cubes',
      ]);
      $rm->addFieldToTemplate(self::field_blocks, 'home');
    }

    // create widgets field
    if (!$rm->getField(self::field_widgets, true)) {
      $rm->createField(self::field_widgets, 'RockPageBuilder', [
        'label' => 'Widgets',
        'tags' => self::tags,
        'icon' => 'cubes',
        'notes' => "Here you can create global blocks that can then be included on many pages. For example you could create a widget with contact details like a telephone number, add that widget to several pages and then change the phone number at one central place rather than updating all pages one by one.",
        'collapsed' => Inputfield::collapsedYes,
      ]);
      $rm->addFieldToTemplate(self::field_widgets, 'home');
    }

    // create eyebrow field
    $type = $this->wire->languages ? 'TextLanguage' : 'Text';
    $rm->createField(self::field_eyebrow, $type, [
      'label' => 'Eyebrow Headline',
      'tags' => self::tags,
      'icon' => 'eye',
      'collapsed' => Inputfield::collapsedBlank,
      'notes' => 'Smaller headline above the main headline.'
    ]);

    // create teaser field
    $type = $this->wire->languages ? 'TextareaLanguage' : 'Textarea';
    $rm->createField(self::field_teaser, $type, [
      'label' => 'Teaser',
      'tags' => self::tags,
      'icon' => 'bullhorn',
      'inputfieldClass' => 'InputfieldTinyMCE',
      'contentType' => FieldtypeTextarea::contentTypeHTML,
      'rows' => 5,
      'inlineMode' => true,
      'settingsFile' => '/site/modules/RockMigrations/TinyMCE/simple.json',

      // dont add any textformatters so that we can apply site-specific
      // in migrations (because we don't have an api for adding textformatters yet)
      // added for maletschek
      // 'textformatters' => [],
    ]);

    // set tags for all fields
    foreach ($this->wire->fields as $field) {
      if (!str_starts_with($field->name, "rpb_")) continue;
      $rm->setFieldData($field, ['tags' => 'RockPageBuilder']);
    }
  }

  /**
   * Place an overlay image here
   */
  public function overlay($name)
  {
    if (!$this->wire->config->overlays) return;
    $rf = $this->wire->modules->get("RockFrontend");
    $path = $this->overlayPath($name, $rf);
    if (!$path) return;
    return $rf->html(
      $rf->render(__DIR__ . "/assets/overlay.php", [
        'id' => basename($path),
        'src' => $rf->url($path, true),
      ])
    );
  }

  public function overlayPath($name, RockFrontend $rf)
  {
    $name = (string)$name;
    foreach (['', 'png', 'jpg', 'jpeg', 'svg'] as $ext) {
      if ($ext) $ext = ".$ext";
      if (strpos($name, $this->wire->config->paths->root) === 0) {
        // name as a filepath so we dont add the folder
        $file = substr($name, 0, -4) . ".overlay" . $ext;
      } else {
        $file = $this->wire->config->paths->templates . "overlays/$name{$ext}";
      }
      if (substr($file, -4) === '.php') continue;
      if (is_file($file)) return $file;
    }
  }

  /**
   * Parse frontend assets
   * This is only done during development of RockPageBuilder
   */
  public function parseFrontendAssets()
  {
    if (!$this->wire->user->isSuperuser()) return;
    if (!$this->wire->modules->isInstalled('Less')) return;

    /** @var RockMigrations $rm */
    $rm = $this->wire->modules->get('RockMigrations');
    $rm->saveCSS(
      less: __DIR__ . "/assets/RockPageBuilder.less",
      minify: true,
      keepCSS: false,
    );
    $rm->saveCSS(
      less: __DIR__ . "/assets/overlay.less",
      minify: true,
      keepCSS: false,
    );
    $rm->saveCSS(
      less: __DIR__ . "/assets/frontend-loggedin.less",
      minify: true,
      keepCSS: false,
    );
    $rm->minify(
      __DIR__ . "/assets/frontend-loggedin.js",
      __DIR__ . "/assets/frontend-loggedin.min.js"
    );
  }

  /**
   * We preload some styles when a rockpagebuilder field is present on the page
   * for example the styles for the radio button need to be available when
   * it is used in a RockFields field. Otherwise the first loaded block will
   * have a messed markup: https://i.imgur.com/6rr2ZIX.png
   */
  public function preloadAssets(Page $block)
  {
    // do this only once
    if ($this->preload) return;

    // preload radio assets (for settings field)
    (new InputfieldRadios())->renderReady();

    // preload assets of fields of a repeater field
    // this is necessary for tinymce or image fields for example
    foreach ($block->fields as $field) {
      if (!$field instanceof RepeaterField) continue;
      $tpl = $this->wire->templates->get($field->template_id);
      foreach ($tpl->fields as $f) $f->getInputfield($block)->renderReady();
    }

    // set flag that we preloaded assets
    $this->preload = true;
  }

  /**
   * Remove old and unused templates
   * @return void
   */
  public function removeUnusedTemplates(HookEvent $event)
  {
    $active = [];
    foreach ($this->blocks as $block) {
      if (!$block->template) continue;
      $active[] = $block->template->name;
    }
    $rm = $this->rm();
    foreach ($this->wire->templates as $tpl) {
      if (strpos($tpl->name, "rockpagebuilderblock-") !== 0) continue;
      if (!in_array($tpl->name, $active)) $rm->deleteTemplate($tpl);
    }
  }

  /**
   * Render content of blocks field
   */
  public function render($renderPlus = true)
  {
    $page = $this->wire->page;
    $field = $page->getFormatted(self::field_blocks);
    if (!$field) return;
    $html = $field->render($renderPlus);
    $rf = $this->wire->rockfrontend;
    if ($rf) return $rf->html($html);
    return $html;
  }

  /**
   * Render links to rpb pages of current block
   * Can be multiple pages for nested blocks
   * @return string
   */
  protected function renderBlockLinks($page, $level = 0)
  {
    if (!$page instanceof Block) return;
    $mp = $page->getBlockPage();
    $field = $page->getBlockField();
    if (!$field) return "Blockfield does not exist (any more)";

    $out = '';
    if (!$level) $out = "<table class='uk-table uk-table-striped uk-table-small'>";
    $out .= "<tr>";
    $out .= "<td class=uk-width-auto><a href={$mp->editUrl}><i class='fa fa-edit'></i></a></td>";
    $out .= "<td class=uk-width-auto>#$mp</td>";
    $out .= "<td class=uk-width-auto>{$field->name}</td>";
    $out .= "<td class=uk-width-expand>";
    $out .= $mp->viewable() ? "<a href={$mp->url}>" : '';
    $out .= $mp->title ?: $mp->url;
    $out .= $mp->viewable() ? "</a>" : '';
    $out .= "</td>";
    $out .= "</tr>";
    $out .= $this->renderBlockLinks($mp, $level + 1);
    if (!$level) $out .= "</table>";

    return $out;
  }

  public function repeaterUrl($url): string
  {
    $url = ltrim($url, "/");
    return $this->wire->pages->get(2)->url . "rockpagebuilder/repeater-$url";
  }

  /**
   * Get RockPageBuilder Process Url
   * Usage: $this->rpbUrl("/add?block=1&field=2");
   * @return string
   */
  public function rpbUrl($url)
  {
    $url = ltrim($url, "/");
    return $this->wire->pages->get(2)->url . "rockpagebuilder/$url";
  }

  /**
   * Get instance of RockMigrations
   * @return RockMigrations
   */
  public function rm()
  {
    return $this->wire->modules->get('RockMigrations');
  }

  /**
   * @return RockFrontend
   */
  public function rockfrontend()
  {
    return $this->wire->modules->get('RockFrontend');
  }

  /**
   * Ajax endpoint for saving vscale value for a block
   */
  public function saveVScaleValue(HookEvent $event)
  {
    $id = $event->input->get('block', 'int');
    $block = $this->wire->pages->get($id);
    if (!$block instanceof Block) throw new WireException("$id is not a valid block");
    if (!$block->editable()) throw new WireException("Block $id not editable");
    $type = $event->input->get('type', 'string');
    $block->meta(
      'vspace-' . $type,
      $event->input->get('value', 'float')
    );
    return json_encode([
      'success' => true,
    ]);
  }

  /**
   * Ajax endpoint for saving sortable drag and drop events
   */
  public function saveSortable(HookEvent $event): string
  {
    // get block id
    $block = $this->wire->pages->get($this->wire->input->get('block', 'int'));
    if (!$block->editable()) throw new WireException("Block $block is not editable");

    // get new value from input
    $sort = $this->wire->input->get('sort', 'string');
    $new = $this->wire->pages->find("id.sort=$sort");

    if ($block instanceof Block) {
      // a pagebuilder block has been sorted
      $page = $block->getBlockPage();
      $field = $block->getBlockField();

      // get old value from DB
      /** @var FieldData $old */
      $old = $page->getUnformatted($field->name);

      // bd((string)$new, 'new');
      // bd((string)$old, 'old');

      // first we remove all items from the new array that are not part of old
      foreach ($new as $newItem) {
        if (!$old->has($newItem)) $new->remove($newItem);
      }
      // bd((string)$new, 'removed');

      // now add back all hidden blocks
      $lastItem = false;
      foreach ($old as $oldItem) {
        if (!$new->has($oldItem)) {
          if ($lastItem) $new->insertAfter($oldItem, $lastItem);
          else $new->prepend($oldItem);
        }
        $lastItem = $oldItem;
      }
      // bd($new, 'added hidden');

      // create new data object and save it to the field
      $data = new FieldData($page, $field);
      foreach ($new as $item) $data->add($item);
      $page->setAndSave($field->name, $data);
    } elseif ($block instanceof RepeaterPage) {
      $page = $block->getForPage();
      $field = $block->getForField();

      // get old value from DB
      $old = $page->getUnformatted($field->name);

      // bd((string)$new, 'new');
      // bd((string)$old, 'old');

      // first we remove all items from the new array that are not part of old
      foreach ($new as $newItem) {
        if (!$old->has($newItem)) $new->remove($newItem);
      }
      // bd((string)$new, 'removed');

      // now add back all hidden blocks
      $lastItem = false;
      foreach ($old as $oldItem) {
        if (!$new->has($oldItem)) {
          if ($lastItem) $new->insertAfter($oldItem, $lastItem);
          else $new->prepend($oldItem);
        }
        $lastItem = $oldItem;
      }
      // bd((string)$new, 'added hidden');

      // save data to field
      $i = 0;
      foreach ($new as $newItem) {
        $newItem->setAndSave('sort', $i++);
      }
    } else throw new WireException("$block is not a valid item");

    return json_encode([
      'success' => true,
      'msg' => 'Sort has been saved successfully',
    ]);
  }

  /**
   * Get content of stub file
   * @return string
   */
  public function stub($file, $replacements = [], $saveTo = false)
  {
    $content = $this->wire->files->fileGetContents($this->path . "stubs/$file");
    $content = str_replace(array_keys($replacements), array_values($replacements), $content);
    if ($saveTo and !is_file($saveTo)) {
      $this->wire->files->filePutContents($saveTo, $content);
    }
    return $content;
  }

  /**
   * Render styles tag with all less files from assets folder
   * This is for simple setups that do not use RockFrontend
   * @return string
   */
  public function styles()
  {
    /** @var Less $less */
    $less = $this->wire('modules')->get('Less');
    $css = $this->wire->config->paths->templates . "blocks.css";
    $cssUrl = $this->wire->config->urls->templates . "blocks.css";
    $mCSS = is_file($css) ? filemtime($css) : 0;
    if (!$less) return "<link rel=stylesheet href='$cssUrl?m=$mCSS'>";

    $lessFiles = $this->wire->files->find(
      $this->wire->config->paths->templates . "RockPageBuilder",
      ['extensions' => ['less']]
    );
    $compile = false;
    foreach ($lessFiles as $lessFile) {
      $less->addFile($lessFile);
      if (filemtime($lessFile) > $mCSS) $compile = true;
    }
    if ($compile) {
      $less->saveCss($css);
      $mCSS = time();
    }
    return "<link rel=stylesheet href='$cssUrl?m=$mCSS'>";
  }

  /**
   * If a rpb block is saved it triggers the save of parent blocks/pages as well.
   * This is important to make sure that for example ProCache rules are working
   * as expected, because those rules are set on the Block-Page and not on the
   * content-block.
   * @return void
   */
  public function triggerBlockPageSave(HookEvent $event)
  {
    $block = $event->arguments(0);
    if (!$block instanceof Block) return;
    foreach ($block->getParentsToSave() as $p) {
      if (!$p->id) continue;
      if ($this->_saved->has($p)) continue;
      $p->rockpagebuilderTriggerSave = true;
      $p->of(false);
      $p->save();
      // bd("triggerBlockPageSave #$p");
      $this->_saved->add($p);
    }
  }

  /**
   * Get widget by template or id
   * @return Block
   */
  public function widget($selector, $returnBlock = true)
  {
    foreach ($this->widgets() as $widget) {
      if (is_string($selector) and $widget->className == $selector) return $widget;
      elseif (is_int($selector) and $widget->id == $selector) return $widget;
    }
    // if no widget was found we return a new block
    // this is important to make sure that $rockpagebuilder->widget('Foo')->render()
    // does not throw an exception
    // if you want it to return false instead use FALSE as second param
    if ($returnBlock === false) return false;
    return new Block();
  }

  public function widgetHint(HookEvent $event)
  {
    $block = $event->process->getPage();
    if (!$block instanceof Block) return;
    if ($block->getBlockPage()->id !== 1) return;
    if ($block->getBlockField()->name !== self::field_widgets) return;

    $references = '';
    $widgetPages = $this->getWidgetPages($block);
    foreach ($widgetPages->sort('path') as $page) {
      $references .= "<li><a href={$page->editUrl}>{$page->path}</a></li>";
    }
    if ($references) $references = "<ul style='margin:0'>$references</ul>";

    $form = $event->return;
    $form->add([
      'type' => 'markup',
      'name' => 'rpb-widget-alert',
      'label' => $this->_('ATTENTION'),
      'icon' => 'exclamation-triangle',
      'value' => "<div>"
        . $this->_('You are currently editing a global widget that is added to multiple pages') . "!"
        . $references
        . "</div>",
      'notes' => $this->_('All changes that you apply to this widget will be visible on all pages') . ".",
    ]);
    $f = $form->children()->last();
    $f->wrapClass('rpb-alert-widget');
    $form->remove($f)->prepend($f);
  }

  /**
   * Get widgets from home page
   * @return PageArray
   */
  public function widgets()
  {
    if (!$this->useWidgets) return new PageArray();
    // return widgets or if the field was removed an empty pagearray
    return $this->wire->pages->get(1)->getFormatted(self::field_widgets)
      ?: new PageArray();
  }

  /**
   * Config inputfields
   * @param InputfieldWrapper $inputfields
   */
  public function getModuleConfigInputfields($inputfields)
  {
    $name = strtolower($this);
    $inputfields->add([
      'type' => 'markup',
      'label' => 'Documentation & Updates',
      'icon' => 'life-ring',
      'value' => "<p>Hey there, coding rockstars! 👋</p>
        <ul>
          <li><a class=uk-text-bold href=https://www.baumrock.com/modules/$name/docs>Read the docs</a> and level up your coding game! 🚀💻😎</li>
          <li><a class=uk-text-bold href=https://www.baumrock.com/rock-monthly>Sign up now for our monthly newsletter</a> and receive the latest updates and exclusive offers right to your inbox! 🚀💻📫</li>
          <li><a class=uk-text-bold href=https://paypal.me/baumrockcom>Support my work with a donation</a>, and together, we'll keep rocking the coding world! 💖💻💰</li>
        </ul>",
    ]);

    $data = $this->data;
    $inputfields->add([
      'type' => 'markup',
      'label' => 'Settings in config.php',
      'icon' => 'exclamation',
      'value' => 'Note that you can overwrite all settings from within your config.php file: $config->rockpagebuilder = [...];',
      'notes' => 'Settings in config.php will have priority over settings set on this page!',
      'collapsed' => Inputfield::collapsedYes,
    ]);

    $fs = new InputfieldFieldset();
    $fs->label = "Settings & Development";
    $fs->icon = "cogs";
    $inputfields->add($fs);

    $fs->add([
      'type' => 'toggle',
      'name' => 'useWidgets',
      'formatType' => 0, // integer 0/1
      'labelType' => 0, // yes/no
      'label' => 'Use the widgets feature',
      'defaultOption' => 'yes',
      'value' => $this->useWidgets,
      'notes' => 'If you disable widgets you can safely remove the widgets field from your home template.',
      'icon' => 'clone',
      'useReverse' => true,
      'columnWidth' => 50,
    ]);

    $fs->add([
      'type' => 'toggle',
      'name' => 'createLessFile',
      'label' => 'Create LESS file for new blocks',
      'formatType' => 0, // integer 0/1
      'labelType' => 0, // yes/no
      'inputfieldClass' => 0, // toggle buttons
      'defaultOption' => 'no',
      'notes' => 'All blocks need a PHP file for the business logic and a markup file that defines the output. If you check this box a LESS file will be created for every block that you can use to define the styling of the block.',
      'value' => $this->createLessFile,
      'columnWidth' => 50,
    ]);

    /** @var InputfieldSelect $f */
    $f = $this->wire->modules->get('InputfieldSelect');
    $f->attr('name', 'createPhp');
    $f->label = 'File type of PHP-file';
    $f->icon = 'code';
    $f->notes = 'Will be used when a new block type is created.';
    $f->addOption('', 'With comments (default)');
    $f->addOption('advanced', 'Advanced (without comments)');
    if (array_key_exists('createPhp', $data)) $f->attr('value', $data['createPhp']);
    $f->columnWidth = 50;
    $fs->add($f);

    /** @var InputfieldSelect $f */
    $f = $this->wire->modules->get('InputfieldSelect');
    $f->attr('name', 'createView');
    $f->label = 'File type of view-file';
    $f->icon = 'code';
    $f->notes = 'Will be used when a new block type is created. Default is PHP. Recommended is LATTE ;)';
    $f->addOption('latte', 'LATTE');
    $f->addOption('php', 'PHP');
    if (array_key_exists('createView', $data)) $f->attr('value', $data['createView']);
    $f->columnWidth = 50;
    $fs->add($f);

    $dir = __DIR__ . "/blocks";
    $installable = $this->wire->files->find($dir, ['extensions' => ['php']]);
    $this->installBlocks();
    $blockNames = $this->blockNames();

    $f = $this->wire->modules->get('InputfieldCheckboxes');
    $f->name = 'installBlocks';
    $f->label = "Install Blocks";
    $f->icon = "cubes";
    $f->columnWidth = 50;
    $installed = [];
    foreach ($installable as $block) {
      $name = substr(basename($block), 0, -4);
      if (in_array($name, $blockNames)) {
        $installed[] = $name;
        continue;
      }
      $f->addOption($name);
    }
    $f->description = "Here you can install example blocks from /site/modules/RockPageBuilder/blocks to field '" . self::field_blocks
      . "'. This will simply copy over files to /site/templates/RockPageBuilder/blocks/ - You can manually copy files to other fields as well.";
    $f->notes = "Already installed: " . implode(", ", $installed);
    $fs->add($f);

    // add option to use old manual sorting
    $fs->add([
      'type' => 'toggle',
      'name' => 'useManualSorting',
      'formatType' => 0, // integer 0/1
      'labelType' => 0, // yes/no
      'label' => 'Sort block buttons manually',
      'defaultOption' => 'no',
      'description' => 'By default the buttons to create new blocks will be sorted alphabetically. You can enable manual sorting and provide an integer "sort" property for every block, eg 100, 200, 300 to define their sort order.',
      'value' => $this->useManualSorting,
      'icon' => 'sort-alpha-asc',
      'columnWidth' => 50,
      'notes' => 'Manual sorting is considered deprecated and only for backwards compatibility!
        If you are unsure keep this setting at "NO".',
    ]);

    $fs = new InputfieldFieldset();
    $fs->label = "Frontend";
    $fs->icon = "eye";
    $inputfields->add($fs);

    $fs->add([
      'type' => 'toggle',
      'name' => 'showBlocktype',
      'label' => 'Show block-type on every block',
      'formatType' => 0, // integer 0/1
      'labelType' => 0, // yes/no
      'inputfieldClass' => 0, // toggle buttons
      'defaultOption' => 'yes',
      'value' => $this->showBlocktype,
      'notes' => 'By default RockPageBuilder will add the block-type to the block label: [Screenshot](https://i.imgur.com/BvB5P6p.png)
        You can use the .rpb-blocktype class to style your label to your needs.',
      'useReverse' => false,
    ]);

    $url = $this->wire->pages->get(2)->url . "module/edit?name=RockFrontend";
    $fs->add([
      'type' => 'markup',
      'label' => 'Notice',
      'icon' => 'code',
      'value' => "Some features like adjusting the path for text editor links are pulled from <a href=$url>RockFrontend Settings</a>.",
    ]);

    $fs = new InputfieldFieldset();
    $fs->label = "Backend";
    $fs->icon = "sitemap";
    $inputfields->add($fs);

    $fs->add([
      'type' => 'toggle',
      'name' => 'showDataPage',
      'label' => 'Show datapage in tree for superusers',
      'formatType' => 0, // integer 0/1
      'labelType' => 0, // yes/no
      'inputfieldClass' => 0, // toggle buttons
      'defaultOption' => 'no',
      'notes' => 'All blocks are PW pages stored under a special page in the pagetree. By default this page is hidden from the pagetree. If you check this box superusers will be able to see the page and all blocks.',
      'checked' => $this->showDataPage ? 'checked' : '',
    ]);

    // add note that shows the fieldname for all fields
    foreach ($inputfields->getAll() as $f) {
      if (!$f->name) continue;
      $nl = $f->notes ? "\n" : "";
      $f->notes .= $nl . "Config property: " . $f->name;
    }

    /** @var InputfieldSelect $f */
    $this->deleteBlock();
    $f = $this->wire->modules->get('InputfieldSelect');
    $f->name = 'deleteBlock';
    $f->label = 'Delete Block';
    $f->prependMarkup("<div class='uk-alert uk-alert-danger'>WARNING: This will delete all block-files and also all user-data saved within blocks of that type!</div>");
    $f->collapsed = Inputfield::collapsedYes;
    $f->icon = 'trash-o';
    foreach ($this->blocks as $k => $block) {
      $f->addOption($k, $block->className());
    }
    $inputfields->add($f);

    return $inputfields;
  }

  public function installBlocks(): void
  {
    $install = $this->input->post->installBlocks;
    if (!is_array($install)) return;

    $tpl = $this->wire->config->paths->templates;
    $this->wire->files->mkdir($tpl . "RockPageBuilder/blocks");
    foreach ($install as $name) {
      $this->wire->files->copy(
        __DIR__ . "/blocks/$name",
        $tpl . "RockPageBuilder/blocks/$name",
      );
      $this->message("Installed $name to field " . self::field_blocks);
    }
  }

  public function deleteBlock()
  {
    if ($this->wire->session->deleteBlockRefresh) {
      $this->wire->session->deleteBlockRefresh = false;
      $this->wire->modules->refresh();
      $this->wire->session->redirect(
        $this->wire->pages->get(2)->url . "module/edit?name=RockPageBuilder"
      );
    }

    $key = $this->wire->input->post->deleteBlock;
    if (!$key) return;
    $block = $this->getBlock($key);
    if (!$block) return;

    // delete files
    foreach ($block->getFiles() as $f) $this->wire->files->unlink($f);

    // delete directory if no files left
    $dir = dirname($f);
    $files = count($this->wire->files->find($dir));
    if (!$files) $this->wire->files->rmdir($dir);

    $this->message("Deleted block " . $block->className());
    $this->wire->session->deleteBlockRefresh = true;
  }

  public function ___install()
  {
    $this->init();
    $this->migrate();
  }

  public function __debugInfo()
  {
    return [
      'blocks' => $this->blocks,
      'mtime' => $this->mtime,
    ];
  }
}
