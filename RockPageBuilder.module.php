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
require_once(__DIR__ . "/Block.php");
require_once(__DIR__ . "/BlocksArray.php");
class RockPageBuilder extends WireData implements Module, ConfigurableModule
{

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

  private $defaultSettings = false;

  /**
   * Flag that is set when a block is being cloned
   * This makes it possible to attach create hooks only for new items
   * and prevent them from overwriting/resetting cloned field values.
   */
  public $isClone = false;

  public $loaded = [];

  public $mtime = 0;

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
    $this->addHookAfter("ProcessPageEdit::buildForm", $this, "buildForm");
    $this->addHookAfter("ProcessPageEdit::buildFormContent", $this, "buildBlockForm");
    $this->addHook("Page::getRmxBlock", $this, "getRmxBlock");
    $this->addHookAfter("Page::editable", $this, "hookBlockEditable");
    $this->addHookAfter("Page::render", $this, "addMagicStyles");
    $this->addHookAfter("User::hasPagePermission", $this, "hookImageEdit");
    $this->addHookBefore("Inputfield::render", $this, "addMagicInputfieldProperties");
    $this->addHookAfter("Modules::refresh", $this, "removeUnusedTemplates");
    $this->addHookAfter("ProcessPageEdit::buildFormContent", $this, "widgetHint");
    $this->addHookAfter("ProcessPageEdit::buildFormContent", $this, "hookHideTitleField");
    $this->addHookBefore("Modules::uninstall", $this, "beforeUninstall");
    $this->addHookAfter("Page::render", $this, "addMoveStyles");
    $this->addHookAfter("Templates::saved", $this, "hookBlockMigrateFile");
    $this->addHookAfter("Fields::saved", $this, "hookBlockMigrateFile");

    // add styles for backend
    $this->addHookAfter("ProcessPageEdit::buildForm", $this, "addStyles");
    $this->addHookAfter("Inputfield::render", $this, "addStyles");
    $this->addHookAfter("ProcessRockPageBuilder::browserTitle", $this, "addStyles");

    // add builder() method to all pages
    $this->addHookMethod("Page::builder", $this, "builder");

    // add JS for frontend
    $this->addHookAfter("Page::render", function ($event) {
      if ($event->object->template == 'admin') return;
      // Bug: sortable makes editable text blocks almost uneditable
      // you can't click on a specific place in text and must use arrow keys
      // $this->wire->rockfrontend->scripts()->add(__DIR__."/RockPageBuilderFrontend.js");
      $rf = $event->wire->rockfrontend;
      if ($rf->loadVspace) $rf->scripts()->add(__DIR__ . "/scripts/vspace.js", "defer");
    });

    // rpb page save trigger
    $this->_saved = new PageArray();
    $this->addHookAfter("Pages::saved", $this, "triggerBlockPageSave");
    $this->addHookAfter("Pages::saved", $this, "cloneBlocks");

    // hide data page from tree
    $this->addHookAfter("ProcessPageList::find", $this, "hideDataPage");
    $this->addHookBefore('ProcessPageListRender::getNumChildren', $this, "hookNumChildren");

    $this->createBlock();
    $this->include("init.php"); // load templates/RockPageBuilder/init.php
    $this->addBlock(__DIR__ . "/Widget.php"); // always load the widget block
    $this->loadUserBlocks(); // load user blocks from templates folder

    // add ajax endpoints
    $this->addHookAfter("/rockpagebuilder-vscale", $this, "saveVScaleValue");

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
      $this->rockfrontend()->styles()->addAll('/site/templates/RockPageBuilder', '', 3);
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
        'icon' => 'move',
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
    $path = $this->path;
    $url = $this->wire->config->urls($this);
    $lessFile = $this->className . ".less";
    $cssFile = "$lessFile.css";
    $mCSS = filemtime($path . $cssFile);
    $mLESS = filemtime($path . $lessFile);

    if ($mLESS > $mCSS and $this->wire->user->isSuperuser()) {
      if ($less = $this->wire->modules->get('Less')) {
        // recreate css file
        /** @var Less $less */
        $less->addFile($path . $lessFile);
        $less->saveCss($path . $cssFile);
        $mCSS = time();
        $this->log('Created new CSS file for ' . $this->className);
      }
    }

    $this->wire->config->styles->add($url . $cssFile . "?m=" . $mCSS);
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
      if ($this->wire->user->isSuperuser()) {
        bd(Debug::backtrace());
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
    $rf->styles()->add($dir . "RockPageBuilder.min.css");

    // add styles for frontend editing
    if (!$this->wire->user->isLoggedin()) return;
    if (!$rf->loadAlfred()) return;
    $rf->styles()->add($dir . "overlay.min.css");
    $rf->scripts()->add($dir . "overlay.min.js");
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
      $html = str_replace("\"$id\"", $str, $html);
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
  public function blockNames(): array
  {
    $names = [];
    foreach ($this->blocks as $block) $names[] = $block->className();
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
  }

  /**
   * Hook the page edit form of blocks
   * @return void
   */
  public function buildBlockForm(HookEvent $event)
  {
    $this->preloadAssets();
    $page = $event->process->getPage();
    if (!$page instanceof Block) return;
    $fs = $event->return;
    $page->prepareForm($fs);
    $page->buildForm($fs);

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
  public function createBlock()
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
      $this->stub("Block.txt", [
        "{name}" => $name,
        "{namelower}" => strtolower($name),
      ], "$subfolder/$name.php");

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
      if ($field->name === 'title') continue;
      if ($field->name === 'email') continue;

      // get a fresh copy of the current field
      // This is somehow necessary because if we take $field directly
      // it has some weird runtime flags applied that get then
      // written into the yaml and applied on the next migration.
      $field = $this->wire->fields->get($field->id);
      $fieldcode[$field->name] = $rm->getCode($field, 2);
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

    // add blocks to rockpagebuilder
    $this->addBlocks($path, $namespace);

    // get blocks
    $blocks = [];
    $options = ['extensions' => ['php']];
    foreach ($this->wire->files->find($path, $options) as $file) {
      $name = pathinfo($file, PATHINFO_FILENAME);
      if (strpos($name, ".") === 0) continue; // no dot-files
      $blocks[] = "$namespace\\$name";
    }
    $blocks = array_merge($blocks, $add);

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
   * Load all blocks from assets folder
   * @return void
   */
  public function loadUserBlocks()
  {
    $folder = $this->wire->config->paths->templates . "RockPageBuilder";
    if (!is_dir($folder)) $this->wire->files->mkdir($folder);
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
          'sortfield' => '-created',
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
      ]);
      $rm->addFieldToTemplate(self::field_widgets, 'home');
    }

    // create eyebrow field
    $type = $this->wire->languages ? 'TextLanguage' : 'Text';
    $rm->createField(self::field_eyebrow, $type, [
      'label' => 'Eyebrow',
      'tags' => self::tags,
      'icon' => 'eye',
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
    ]);
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
    if (!$this->wire->modules->isInstalled('Less')) {
      $this->log('Install Less module to parse less files!');
      return;
    }
    /** @var RockMigrations $rm */
    $rm = $this->wire->modules->get('RockMigrations');
    $dir = __DIR__ . "/assets/";
    try {
      $css = $rm->saveCSS($dir . "RockPageBuilder.less");
      $rm->minify($css, $dir . "RockPageBuilder.min.css");
      $css = $rm->saveCSS($dir . "overlay.less");
      $rm->minify($css, $dir . "overlay.min.css");
    } catch (\Throwable $th) {
      $this->log($th->getMessage());
    }
  }

  /**
   * We preload some styles when a rockpagebuilder field is present on the page
   * for example the styles for the radio button need to be available when
   * it is used in a RockFields field. Otherwise the first loaded block will
   * have a messed markup: https://i.imgur.com/6rr2ZIX.png
   */
  public function preloadAssets()
  {
    if ($this->preload) return;
    (new InputfieldRadios())->renderReady();
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
    if ($rf = $this->wire->rockfrontend) return $rf->html($html);
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

    $out = '';
    if (!$level) $out = "<table class='uk-table uk-table-striped uk-table-small'>";
    $out .= "<tr>";
    $out .= "<td class=uk-width-auto><a href={$mp->editUrl}><i class='fa fa-edit'></i></a></td>";
    $out .= "<td class=uk-width-auto>#$mp</td>";
    $out .= "<td class=uk-width-auto>" . $page->getBlockField()->name . "</td>";
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
      // $this->log("triggerBlockPageSave #$p");
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
    return $this->wire->pages->get(1)->getFormatted(self::field_widgets);
  }

  /**
   * Config inputfields
   * @param InputfieldWrapper $inputfields
   */
  public function getModuleConfigInputfields($inputfields)
  {
    $data = $this->data;

    $inputfields->add([
      'type' => 'markup',
      'label' => 'Note',
      'icon' => 'exclamation',
      'value' => 'Note that you can overwrite all settings from within your config.php file: $config->rockpagebuilder = [...];',
      'notes' => 'Settings in config.php will have priority over settings set on this page!',
    ]);

    $inputfields->add([
      'type' => 'checkbox',
      'name' => 'showDataPage',
      'label' => 'Show datapage in tree for superusers',
      'checked' => $this->showDataPage ? 'checked' : '',
    ]);

    $inputfields->add([
      'type' => 'checkbox',
      'name' => 'createLessFile',
      'label' => 'Create LESS file for new blocks',
      'checked' => $this->createLessFile ? 'checked' : '',
    ]);

    /** @var InputfieldSelect $f */
    $f = $this->wire->modules->get('InputfieldSelect');
    $f->attr('name', 'createView');
    $f->label = 'File type of view-file';
    $f->notes = 'Will be used when a new block type is created.';
    $f->addOption('latte', 'LATTE');
    $f->addOption('php', 'PHP');
    if (array_key_exists('createView', $data)) $f->attr('value', $data['createView']);
    $inputfields->add($f);

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
