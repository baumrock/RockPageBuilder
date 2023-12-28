<?php

namespace RockPageBuilder;

use Latte\Engine;
use Latte\Runtime\Html;
use ProcessWire\FieldtypeRockPageBuilder;
use ProcessWire\FieldtypeRockShare;
use ProcessWire\Paths;
use ProcessWire\RockPageBuilder;
use \ProcessWire\WireData;
use \ProcessWire\RockMigrations;
use \ProcessWire\Inputfield;
use \ProcessWire\InputfieldFile;
use \ProcessWire\InputfieldWrapper;
use \ProcessWire\InputfieldFieldset;
use ProcessWire\InputfieldMarkup;
use ProcessWire\NullPage;
use ProcessWire\Page;
use ProcessWire\PageArray;
use ProcessWire\RockFields;
use ProcessWire\RockFieldsField;
use ProcessWire\RockFrontend;
use ProcessWire\Template;
use ProcessWire\WireException;
use ReflectionClass;
use RockPageBuilder\Html as RockPageBuilderHtml;
use RockPageBuilderBlock\Widget;

use function ProcessWire\wireClassName;

class Block extends \ProcessWire\Page
{

  const prefix = "rockpagebuilderblock_";
  const tags = "RockPageBuilder";

  const spaceS = "25px, 50px";
  const spaceM = "50px, 100px";
  const spaceL = "75px, 150px";

  /**
   * References the current file
   * @var string
   **/
  public $file;

  /**
   * Reference to the yaml migrate file
   * @var string
   */
  public $yaml;

  private $info;

  /** @var Engine */
  private $latte;

  public function info()
  {
    // this is for backwards compatibility
    // for blocks that use the old syntax for info() method
    return new WireData();
  }

  public function __construct()
  {
    try {
      $this->template = $this->getTpl();
    } catch (\Throwable $th) {
      $this->log($th->getMessage());
    }
  }

  /**
   * This ensures that we have an init() method on every block
   * so that if extending blocks call parent::init() we'll not run into trouble
   */
  public function init()
  {
  }

  /** magic methods */

  public function onCreate()
  {
    // we dont execute resetting the title if the block is being cloned
    if ($this->rpb()->isClone) return;
    // set default field values when a new block is created
    $this->setInAllLanguages('title', '');
  }

  /** end magic methods */

  /**
   * Add ALFRED icons (for RockFrontend)
   * Note: Can not be hookable (reference does not work!)
   * @return void
   */
  public function addAlfredIcons(&$icons, $opt)
  {
    if ($opt->noBlock) return;

    // if the _block context is set for this block we use it as block
    // this is to support the concept of "widgets" where widgets render global blocks.
    // when trashing such a block we want to trash the reference widget and not the global block itself!
    $block = $this;
    $widget = $block->_widget ?: $block;
    $data = $widget->getBlockData();

    $spaceID = $this->wire->user->isSuperuser() ? $this->spaceID() : '';

    // add vspace buttons?
    if ($block->spaceV()) {
      $rf = $this->rockfrontend();
      if ($rf) $rf->loadVspace = true;
      $icons[] = (object)[
        'icon' => 'up',
        'label' => $block->title,
        'tooltip' => "vSpace top " . $spaceID,
        'type' => 'vspacetop',
        'widget' => $widget->id,
      ];
      $icons[] = (object)[
        'icon' => 'down',
        'label' => $block->title,
        'tooltip' => "vSpace bottom " . $spaceID,
        'type' => 'vspacebottom',
        'widget' => $widget->id,
      ];
    }

    if ($opt->clone and $block->editable()) {
      $icons[] = (object)[
        'icon' => 'clone',
        'label' => $block->title,
        'tooltip' => "Clone Block #{$widget->id}",
        'href' => $widget->rpbUrl("/clone/?block=$widget"),
        'confirm' => $this->_('Do you really want to clone this element?'),
      ];
    }

    // convert block into widget
    if (!$this->master()->noWidgets && $opt->widgetable && $block->canBeWidget()) {
      $icons[] = (object)[
        'icon' => 'widget',
        'label' => $block->title,
        'tooltip' => "Widgetize Block #{$block->id}",
        'href' => $block->rpbUrl("/convertToWidget/?block=$block"),
        'confirm' => $this->_('Do you really want to convert this block into a widget?'),
      ];
    }

    if ($opt->trash and $block->trashable()) {
      $icons[] = (object)[
        'icon' => 'trash-2',
        'label' => $block->title,
        'tooltip' => "Trash Block #{$widget->id}",
        'href' => $widget->rpbUrl("/trash/?block=$widget"),
        'confirm' => $this->_('Do you really want to delete this element?'),
      ];
    }
  }

  /**
   * Add rockfields settings field for this block
   */
  public function addSettingsField()
  {
    if (!$rf = $this->wire->modules->get('RockFields')) return;

    // you can prevent showing the settings field
    // by defining "settings => false" in the info() of your block
    if ($this->getInfo()->settings === false) return;

    // add field to rockfields
    $rf->add([
      'name' => $this->settingsName(),

      // the inputfield is either defined by the settingsInput method
      // or - eg when using rockpagebuilder - by the settingsTable method
      'inputfield' => method_exists($this, 'settingsTable')
        ? [$this, 'settingsTable']
        : [$this, 'settingsInput'],
      'sleep' => [$this, 'settingsSleep'],
    ]);
  }

  public function addSettingsFieldToForm(InputfieldWrapper $fs)
  {
    $f = new InputfieldMarkup();
    $f->label = 'Settings';
    $f->icon = 'cogs';

    /** @var RockFields $rf */
    if (!$rf = $this->wire->rockfields) {
      $f->notes .= "\nYou also need to install the RockFields module to use this feature.";
      if ($this->wire->user->isSuperuser()) $fs->add($f);
      return;
    }

    $inputfield = $rf->getInputfield($this, $this->settingsName(), true);
    if (!$inputfield) return;
    else $f = $inputfield;
    $f->addClass('rpb-settings');

    // set settings field values from getInfo() of block
    $settings = $this->getInfo()->settings;
    if (is_array($settings)) $f->setArray($settings);

    $fs->add($f);
  }

  /**
   * Get the background-id for this block
   *
   * This background id is necessary to calculate the block classes
   * which are necessary for creating sections with different background colors
   */
  public function bgID(): string
  {
    return "white";
  }

  /**
   * Show block background info (for debugging)
   */
  public function bgInfo(): Html|string
  {
    $prev = $this->prevBlock();
    if ($prev) $prev = "#{$prev} {$prev->bgID()}";
    $next = $this->nextBlock();
    if ($next) $next = "#{$next} {$next->bgID()}";

    return $this->html("<div>
      <div>prev: $prev</div>
      <div>next: $next</div>
      <div>this: #{$this} {$this->classes()}</div>
    </div>");
  }

  /**
   * Build form to edit this block
   * @return void
   */
  public function ___buildForm($fs)
  {
  }

  /**
   * Build the form when displayed in a rpb field
   * @return void
   */
  public function ___buildFormBlock($fs)
  {
  }

  public function canBeWidget()
  {
    return $this->isAllowed(RockPageBuilder::field_widgets, 1);
  }

  /**
   * Get block classes for multicolor background sections
   */
  public function classes(): string
  {
    $prev = $this->prevBlock();
    $next = $this->nextBlock();

    $class = "block";
    if (!$prev || $prev->bgID() !== $this->bgID()) $class .= " block-top";
    if (!$next || $next->bgID() !== $this->bgID()) $class .= " block-bottom";
    return $class;
  }

  /**
   * Clone this block
   *
   * Will add the block to the same field right after the cloned item
   *
   * @return Block
   */
  public function clone()
  {
    $block = $this;
    $fielddata = $block->getBlockData();
    $this->rpb()->isClone = true;
    $clone = $this->wire->pages->clone($block);
    /** @var Block $clone */
    $this->rpb()->isClone = false;
    $fielddata->insertAfter($clone, $block);
    $fielddata->save();
  }

  /**
   * Move this block to given page and field
   * @return void
   */
  public function move($page, $field)
  {
    $page = $this->wire->pages->get((string)$page);
    $field = $this->wire->fields->get((string)$field);
    if (!$this->isAllowed($field, $page)) {
      throw new WireException("Block #$this is not allowed on page $page and field $field");
    }
    $new = $page->getFormatted($field->name);
    if (!$new instanceof FieldData) {
      throw new WireException("Requested field $field on page $page is not valid");
    }

    // remove from old field
    $old = $this->getBlockData();
    $old->remove($this);
    $old->save();

    // add to new field
    $new->add($this);
    $new->save();
    $this->setBlockReference($page, $field);
  }

  /**
   * Get path of block file
   * @return string
   */
  public function filePath()
  {
    $reflector = new ReflectionClass($this);
    return Paths::normalizeSeparators($reflector->getFileName());
  }

  /**
   * Get collapsed state of item
   */
  public function getCollapsedState()
  {
    return $this->wire->config->ajax
      ? Inputfield::collapsedNo
      : Inputfield::collapsedYes;
  }

  /**
   * Get default blocksettings
   */
  public function getDefaultSettings(RockFieldsField $field)
  {
    return $this->master()->cloneBlockSettings($field, $this);
  }

  /**
   * Get fieldname from magic method call
   */
  protected function getFieldName($method)
  {
    if ($method === 'title') return 'title';
    if (property_exists($this, $method)) return false;
    $r = new ReflectionClass($this);
    return $r->getConstant("field_$method");
  }

  /**
   * Get all files related to this block
   */
  public function getFiles(): array
  {
    $files = [];
    $tmp = new ReflectionClass($this);
    $file = $tmp->getFileName();
    $name = pathinfo($file, PATHINFO_FILENAME);
    $dir = Paths::normalizeSeparators(dirname($file));
    foreach ($this->wire->files->find($dir) as $f) {
      if (strpos($f, "$dir/$name") !== 0) continue;
      $files[] = $f;
    }
    return $files;
  }

  /**
   * Get icon for rpb item
   * @return string
   */
  public function getIcon()
  {
    return $this->getInfo()->icon;
  }

  /**
   * Get info WireData
   * @return WireData
   */
  public function getInfo()
  {
    if ($this->info) return $this->info;
    $info = $this->wire(new WireData());
    /** @var WireData $info */
    $info->setArray([
      'title' => $this->className,
      // this is the full classname eg Foo\Bar\Baz
      // use $block->className for the classname without namespace (pw-feature)
      'name' => get_class($this),
      'icon' => 'cube',
      'sort' => 500,
    ]);
    $blockInfo = $this->info();
    if ($blockInfo instanceof WireData) $blockInfo = $blockInfo->getArray();
    $info->setArray($blockInfo);
    return $info;
  }

  /**
   * Get label for rpb item
   * @return string
   */
  public function getLabel()
  {
    $label = $this->title ?: $this->getInfo()->title;
    return $this->wire->sanitizer->truncate($label, 50);
  }

  /**
   * Get markup array for wrapper
   * @return array
   */
  public function getMarkupArray($wrapper)
  {
    $markup = $wrapper->getMarkup();

    // actions
    $markup['item_label'] = str_replace(
      "{out}",
      "{out}" . $this->renderActions(),
      $markup['item_label']
    );

    return $markup;
  }

  /**
   * Get the master block object that was used for initializing this block
   * @return Block
   */
  public function getMasterBlock()
  {
    return $this->master()->getBlockByTpl($this->getTpl());
  }

  /**
   * Get the rpb data object of the field where this block lives on
   * @return FieldData
   */
  public function getBlockData()
  {
    $page = $this->getBlockPage();
    $field = $this->getBlockField();
    if (!$page or !$field) return false;
    return $page->get($field->name);
  }

  /**
   * Return the field where this block lives on
   * @return Field
   */
  public function getBlockField()
  {
    $meta = explode("-", (string)$this->meta('RockPageBuilder'));
    if (!is_array($meta) or count($meta) !== 2) return false;
    return $this->wire->fields->get($meta[1]);
  }

  /**
   * Index starting from 1
   * @return integer
   */
  public function getBlockNum()
  {
    return $this->getBlockIndex() + 1;
  }

  /**
   * Return the page where this block lives on
   * Every block can only live on ONE single page!!
   * @return Page
   */
  public function getBlockPage()
  {
    // the page is stored in metadata of the block
    // the metadata is pageid-fieldid
    if (!$this->id) return new NullPage();
    $meta = explode("-", (string)$this->meta('RockPageBuilder'));
    return $this->wire->pages->get($meta[0]);
  }

  /**
   * Get the index (sort order) of this rpb item
   * @param bool $startAtOne
   * @return int|false
   */
  public function getBlockIndex($startAtOne = false)
  {
    $i = $startAtOne ? 1 : 0;
    $items = $this->getBlockData();
    if (!$items) return false;
    foreach ($items as $item) {
      if ($item->_mxhidden) continue;
      if ($item->id === $this->id) return $i;
      $i++;
    }
    return false;
  }

  /**
   * Get notes for rpb item
   * @return string
   */
  public function getNotes()
  {
    return $this->getInfo()->description;
  }

  /**
   * Get parent for this block
   * @return Page
   */
  public function ___getParent($field, $page)
  {
    return $this->master()->getDatapage();
  }

  /**
   * Get parents of current block that should be saved when a block is saved.
   * This is necessary to trigger ProCache reset of edited pages
   * @return PageArray
   */
  public function getParentsToSave()
  {
    $pages = new PageArray();
    $current = $this->getBlockPage();
    while ($current instanceof Block) {
      $pages->add($current);
      $current = $current->getBlockPage();
    }
    $pages->add($current);
    return $pages;
  }

  /**
   * Get the related pw template
   */
  public function getTpl()
  {
    return $this->wire->templates->get($this->getTplName());
  }

  /**
   * Convert the class name to a pw valid tpl name
   * @return string
   */
  public function getTplName()
  {
    $class = $this->getInfo()->name;
    return $this->wire->sanitizer->pagename($class);
  }

  /**
   * Get wrapper for editing this block
   * @return InputfieldWrapper
   */
  public function getWrapper()
  {
    $r = $this->wire->modules->get('InputfieldRepeater');
    /** @var InputfieldRepeater $r */
    $wrap = $this->wire(new InputfieldWrapper());
    /** @var InputfieldWrapper $wrap */
    $fs = $this->wire(new InputfieldFieldset());
    /** @var InputfieldFieldset $fs */

    $wrap->add($fs);
    $wrap->suffix = "_repeater$this";

    // prepare label
    $title = $this->getInfo()->title;
    $label = $this->getLabel() ?: $title;
    if ($label instanceof Html or $label instanceof RockPageBuilderHtml) {
      // do not change the label
    } else {
      $label = $this->wire->sanitizer->truncate(strip_tags($label), 70);
    }
    $prefix = false;
    if (!$this->master()->noBlocktype) {
      if ($title == $label) $label = false;
      else $title .= ":";
      $prefix = "<small class='rpb-blocktype'>$title</small>";
    }
    $label = "<i class='fa fa-arrows'></i> $prefix $label";
    $fs->entityEncodeLabel = false;

    // prepare the fieldset (item root element)
    $fs->id = "rpb_$this";
    $fs->label = $label;
    $fs->icon = $this->getIcon();
    $fs->notes = $this->getNotes();
    $fs->addClass('rpb-item');
    if ($this->_mxhidden) $fs->addClass('rpb-hidden');
    $fs->wrapAttr('data-page', $this->id);
    if ($this->wire->user->isSuperuser()) {
      $fs->wrapAttr('uk-tooltip', "{$this->className} #{$this->id}");
    }
    $fs->wrapAttr('data-tpl', $this->template->name);
    if ($col = $this->getInfo()->color) {
      $fs->wrapAttr('style', "border-left: 5px solid $col");
    }
    $fs->collapsed = $this->getCollapsedState();

    // prepare form (and add settings field)
    $this->prepareForm($fs);

    // apply changes added to buildForm
    // buildForm changes will also be applied when editing
    // the block in a new window whereas buildFormBlock
    // will only be applied when editing in a rpb field
    if ($f = $fs->get('title')) {
      if ($this->getInfo()->hideTitle) {
        $f->collapsed = Inputfield::collapsedHidden;
      }
    }
    $this->buildForm($fs);

    // call buildFormBlock (if implemented for the block)
    $this->buildFormBlock($fs);

    // prepare showif replacements
    // we need to replace regular fieldnames with repeater fieldnames
    // see https://processwire.com/talk/topic/29225-show-this-field-only-if-doesnt-seem-to-work
    $showIf = [];
    foreach ($this->rpb()->getChildrenRecursively($fs) as $f) {
      $showIf[$f->name] = $f->name . "_repeater$this";
    }

    // add repeater suffix to all children
    foreach ($this->rpb()->getChildrenRecursively($fs) as $f) {
      // add the suffix to the inputfields name
      // before we do that we make sure that it does not already
      // have a repeater suffix to avoid adding the suffix twice
      // this can happen on RockMeta fields (don't know why, quickfix)
      $name = preg_replace('/_repeater\d+$/', '', $f->name);
      $f->name = $name . $wrap->suffix;

      // replace fieldnames in showif
      if ($f->showIf) {
        // convert foo=1 to foo_repeater123=1
        $f->showIf = str_replace(
          array_keys($showIf),
          array_values($showIf),
          $f->showIf
        );
      }

      // open wrapper if field has an error
      if (count($f->getErrors())) $fs->collapsed = Inputfield::collapsedNo;

      // non-editable blocks are locked for edits
      if (!$this->editable()) {
        $f->collapsed = ($f->collapsed == Inputfield::collapsedNo)
          ? Inputfield::collapsedNoLocked
          : Inputfield::collapsedYesLocked;
      }

      // changes for file inputfields
      if (!$f instanceof InputfieldFile) continue;
      $f->wrapAttr('data-fnsx', $wrap->suffix);
      $itemType = $r->getRepeaterItemType($this);
      $itemTypeName = $r->getRepeaterItemTypeName($itemType);
      $f->wrapClass('InputfieldRepeaterItem');
      $f->wrapAttr('data-page', $this->id);
      $f->wrapAttr('data-type', $itemType);
      $f->wrapAttr('data-typeName', $itemTypeName);
      $f->wrapAttr('data-editUrl', $this->editUrl());
    }

    // customize inputfield wrapper markup
    $fs->setMarkup([
      "id={$fs->id}" => $this->getMarkupArray($fs),
    ]);

    return $fs;
  }

  /**
   * Return an Html object
   * @return Html
   */
  public function html($str)
  {
    try {
      $autoload = $this->wire->config->paths->siteModules . "RockFrontend/vendor/autoload.php";
      if (is_file($autoload)) require_once $autoload;
      return new Html($str);
    } catch (\Throwable $th) {
      try {
        require_once __DIR__ . "/Html.php";
        $html = new RockPageBuilderHtml($str);
        return $html;
      } catch (\Throwable $th) {
      }
      if ($this->wire->user->isSuperuser()) return $th->getMessage();
      return $str;
    }
  }

  /**
   * Does this block have an even index?
   * @return bool
   */
  public function indexEven()
  {
    return $this->getBlockIndex() % 2 === 0;
  }

  /**
   * Does this block have an even index?
   * @return bool
   */
  public function indexOdd()
  {
    return $this->getBlockIndex() % 2 !== 0;
  }

  /**
   * Is this block allowed on given page and field?
   * @return bool
   */
  public function isAllowed($field, $page)
  {
    $field = $this->wire->fields->get((string)$field);
    $page = $this->wire->pages->get((string)$page);
    $allowed = $this->master()->getAllowedBlocks($field, $page);
    foreach ($allowed as $b) {
      if ($b->getInfo()->name === $this->getInfo()->name) return true;
    }
    return false;
  }

  /**
   * Check if method is defined in current class
   * Returns FALSE if the method is inherited
   * See https://bit.ly/3IWuayR
   */
  protected function isDefined($method)
  {
    $class = get_class($this);
    return (method_exists($class, $method)) &&
      ($class === (new \ReflectionMethod($class, $method))->getDeclaringClass()->name);
  }

  /**
   * Is this block-NUMBER even (2, 4, 6)?
   * @return bool
   */
  public function isEven()
  {
    return $this->getBlockNum() % 2 === 0;
  }

  /**
   * Is this block-type-NUMBER even (2, 4, 6)?
   * @return bool
   */
  public function isEvenType()
  {
    return ($this->typeIndex() + 1) % 2 === 0;
  }

  /**
   * Is this item the first item?
   * @return bool
   */
  public function isFirstBlock()
  {
    return $this->getBlockIndex() === 0;
  }

  /**
   * Is this item the last item?
   * @return bool
   */
  public function isLastBlock()
  {
    $data = $this->getBlockData();
    if (!$data) return true;
    return $this->getBlockIndex(true) === $data->count();
  }

  /**
   * Is this block-NUMBER odd (1, 3, 5)?
   * @return bool
   */
  public function isOdd()
  {
    return $this->getBlockNum() % 2 !== 0;
  }

  /**
   * Is this block-type-NUMBER odd (1, 3, 5)?
   * @return bool
   */
  public function isOddType()
  {
    return ($this->typeIndex() + 1) % 2 !== 0;
  }

  /**
   * Is the parent page saved?
   * @return bool
   */
  public function isSaved()
  {
    return !!$this->getBlockIndex(true);
  }

  /**
   * Is block given type?
   *
   * Usage:
   * $block->isType("EventItem");
   */
  public function isType($type): bool
  {
    return wireClassName($this) == $type;
  }

  /**
   * Is this block a RockPageBuilder widget stored in field rockpagebuilder_widgets?
   * @return bool
   */
  public function isWidget()
  {
    return $this->getBlockField()->name == RockPageBuilder::field_widgets;
  }

  /**
   * Get modified timestamp for image url cache busting
   *
   * Usage:
   * <img src={$img->webp->url.$block->m()}>
   */
  public function m($len = 4): string
  {
    return "?m=" . substr($this->modified, strlen($this->modified) - $len);
  }

  /**
   * Return master module
   * @return RockPageBuilder
   */
  public function master()
  {
    return $this->wire->modules->get('RockPageBuilder');
  }

  /**
   * Add odd class to every 2nd element
   *
   * Block foo
   * Block foo.odd
   * Block foo
   * Block bar
   * Block bar.odd
   */
  public function oddClass(): string
  {
    return $this->typeIndex() % 2 ? "odd" : "";
  }

  /**
   * Return instance of RockPageBuilder
   * @return RockPageBuilder
   */
  public function rpb()
  {
    return $this->wire->modules->get('RockPageBuilder');
  }

  /**
   * Get next rpb item
   * @return Block|false
   */
  public function nextBlock($includeHidden = false)
  {
    $match = false;
    foreach ($this->getBlockData() as $item) {
      if (!$includeHidden) {
        // some blocks don't have visible markup (like anchor blocks)
        // those blocks are ignored when calculating vertical spacings
        if ($item->noMarkup) continue;
        if ($item->_mxhidden) continue;
      }

      if ($match) return $item;
      if ($item->id === $this->id) $match = true;
    }
    return false;
  }

  /**
   * Inject markup to show block design as overlay
   */
  public function overlay()
  {
    return $this->master()->overlay($this->filePath());
  }

  /**
   * Prepare form for being rendered as a rpb block
   * This is a separate method that needs to be called before buildForm
   * or buildFormBlock. The reason for this method is that buildForm and
   * buildFormBlock do not need to call parent::buildForm, because that would
   * be prone to errors.
   * @return void
   */
  protected function prepareForm($fs)
  {
    // the default is to add all fields of the page template
    $fields = $this->getInputfields();
    if (!$fields) return $fs;
    foreach ($fields->children() as $f) {
      $type = $f->hasField->type;

      // prevent recursion
      if ($type instanceof FieldtypeRockPageBuilder) {
        if ($this->wire->process->getPage()->id == $f->value->page->id) {
          // we are editing the block in the page editor
          // we set the value to empty string to hide the item-edit-button
          $value = '';
        } elseif ($f->value->page->isSaved()) {
          $id = $f->value->page->id;
          $url = $this->wire->pages->get(2)->url . "page/edit/?id=$id&field=" . $f->name;
          $label = $f->label;
          $value = "<a href='$url'
            class='pw-modal pw-modal-reload uk-button uk-button-default'
            data-buttons='button.ui-button[type=submit]'
            data-autoclose=''
            >$label</a>";
        } else {
          $value = $this->_("Please save the page, then you can come back here and edit block items.");
        }
        $fields->add([
          'name' => $f->name . "_markup",
          'type' => 'markup',
          'value' => $value,
        ]);
        $markup = $fields->children()->last();
        $fields->remove($markup);
        $fields->insertAfter($markup, $f);
        $fields->remove($f);
      }

      // sharing of pages not possible inside rpb
      if ($type instanceof FieldtypeRockShare) $fields->remove($f);
    }

    foreach ($fields as $field) {
      if ($fs->has($field->name)) continue;
      $fs->add($field);
    }

    // add rockfields settings to form
    $this->addSettingsFieldToForm($fs);
  }

  /**
   * Get previous rpb item
   * @return Block|false
   */
  public function prevBlock($includeHidden = false)
  {
    $prev = false;
    foreach ($this->getBlockData() as $item) {
      // for frontend styles we only need visible blocks
      // so we exclude non-visible blocks here
      if (!$includeHidden) {
        if ($item->noMarkup) continue;
        if ($item->_mxhidden) continue;
      }

      if ($item->id === $this->id) return $prev;
      $prev = $item;
    }
    return false;
  }

  /**
   * Get relative path where this block lives
   * This is handy for getting the path of the customstyles js on CKE fields
   * @return string
   */
  public function relativePath()
  {
    return str_replace(
      $this->wire->config->paths->root,
      $this->wire->config->urls->root,
      dirname($this->filePath())
    ) . "/";
  }

  /**
   * Dont implement this method! It is needed for PW for $page->render() to work
   * public function render() {
   * }
   */

  /**
   * Render this block
   * @return string
   */
  public function renderBlock()
  {
    $rf = $this->rockfrontend();
    foreach ($this->viewFiles() as $file => $type) {
      if (is_file($file)) {
        if ($rf) {
          try {
            $rf->setTextdomain($file);
          } catch (\Throwable $th) {
            return "setTextdomain not available - please update RockFrontend to the latest version!";
          }
        }
        $out = $this->html($this->renderFile($file, $type));
        if ($rf) $rf->setTextdomain(false);
        return $out;
      }
    }
  }

  /**
   * Render file
   *
   * Usage:
   * $block->renderFile('/path/to/file.view.php');
   *
   * This will look for the file myblock.latte in the same folder
   * where the block is defined (php file)
   * $block->renderFile('myblock.latte');
   *
   * @return string
   */
  public function renderFile($file, $type = null)
  {
    // make all api variables available in the template file
    $vars = array_merge(
      $this->wire('all')->getArray(),
      [
        'block' => $this,
        'settings' => $this->settings(),
      ]
    );
    if (!$type) $type = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    if (!is_file($file)) $file = dirname($this->filePath()) . "/$file";
    if ($type == 'php') {
      $opt = ['allowedPaths' => [dirname($file)]];
      return $this->wire->files->render($file, $vars, $opt);
    } elseif ($type == 'latte') {
      $latte = $this->latte;
      if (!$latte) {
        try {
          // load latte from RockFrontend
          $vendor = $this->wire->config->paths->siteModules . "RockFrontend/vendor/autoload.php";
          if (is_file($vendor)) require_once $vendor;
          else {
            // load latte from PW root
            $vendor = $this->wire->config->paths->root . "vendor/autoload.php";
            if (is_file($vendor)) require_once $vendor;
          }

          $latte = new Engine();
          $latte->setTempDirectory($this->wire->config->paths->cache . "Latte");
          $this->latte = $latte;
        } catch (\Throwable $th) {
          $msg = "<br>Install Latte or delete the .latte view file and use
            the plain php view file instead.";
          return "<strong>" . $th->getMessage() . "</strong>$msg";
        }
      }
      return $latte->renderToString($file, $vars);
    }
  }

  /**
   * Render a single block action
   * @return string
   */
  public function renderAction($action, $data)
  {
    $opt = $this->wire(new WireData());
    /** @var WireData $opt */
    $opt->setArray([
      'href' => '#',
      'attrs' => [],
    ]);
    $opt->setArray($data);
    $icon = $opt->icon ?: $action;

    // prepare custom attributes
    $attrs = '';
    foreach ($opt->attrs as $k => $v) $attrs .= " data-$k='$v'";

    return
      "<a href='{$opt->href}'
        class='rpb-action rpb-action-$action'
        uk-tooltip='title:{$opt->label};pos:left;'
        data-action='$action'
        $attrs>
        <i class='fa fa-$icon'></i>"
      . "</a>";
  }

  /**
   * Render actions for this item
   */
  public function renderActions()
  {
    $out = "<span class='rpb-actions'>";
    if ($this->wire->user->isSuperuser()) {
      $admin = $this->wire->pages->get(2)->url;
      $out .= $this->renderAction('editnew', [
        'label' => $this->_('edit in new window'),
        'icon' => 'external-link',
        'href' => $admin . "page/edit/?id=$this",
        'attrs' => ['target' => '_blank'],
      ]);
      $out .= $this->renderAction('edittemplate', [
        'label' => $this->_('change fields'),
        'icon' => 'cubes',
        'href' => $admin . "setup/template/edit?id=" . $this->getTpl()->id,
        'attrs' => ['target' => '_blank'],
      ]);
    }
    $out .= $this->renderAction('edit', [
      'label' => $this->_('edit'),
      'icon' => 'edit',
      'attrs' => [
        'toggle' => 1,
      ],
    ]);
    $out .= $this->renderAction('hide', [
      'label' => $this->_('Hide'),
      'icon' => 'toggle-on',
    ]);
    $out .= $this->renderAction('unhide', [
      'label' => $this->_('Unhide'),
      'icon' => 'toggle-off',
    ]);
    $out .= $this->renderAction('trash', [
      'label' => $this->_('Mark for deletion'),
    ]);
    $out .= $this->renderAction('untrash', [
      'label' => $this->_('Undo deletion'),
      'icon' => 'trash-o',
    ]);
    if ($this->wire->user->isSuperuser()) {
      $path = $this->rm()->filePath($this, true);
      $out .= $this->renderAction('code', [
        'label' => $path,
        'icon' => 'code',
        'href' => $this->rm()->fileEditLink($this),
      ]);
    }
    $out .= "</span>";
    return $out;
  }

  /**
   * Render Button when in modal view
   */
  public function renderButton($page, $field)
  {
    $block = $this->wire->input->get('block', 'int');
    $above = $this->wire->input->get('above', 'int');
    $tpl = $this->getTplName();

    if ($block) $href = $this->rpbUrl("/add/?block=$block&above=$above&tpl=$tpl&modal=1");
    else $href = $this->rpbUrl("/add-new/?page=$page&field=$field&tpl=$tpl&modal=1");

    $ajax = "./?id=$page&field=$field&tpl=$tpl";

    $info = $this->getInfo();
    $tooltip = $info->description ?: '';
    $tooltip = "title='$tooltip' uk-tooltip";

    $style = $info->color ? "style='border-left: 3px solid {$info->color}'" : '';

    return "<a href='$href' data-href='$ajax' class='rpb-button' $tooltip $style>
      <div class='uk-position-relative'>{$this->renderButtonImage()}</div>
      <span class='uk-margin-small-top uk-badge'>{$this->getInfo()->title}</span>
      </a>";
  }

  /**
   * Get SVG image tag for this block
   * @return string
   */
  public function renderButtonImage()
  {
    if (!$master = $this->getMasterBlock()) return;
    $info = $this->getInfo();
    $file = $master->file;
    $base = substr($file, 0, -4); // without .php ending
    $icon = '';
    $extensions = ['jpg', 'png', 'svg']; // svg has to be last!
    $url = false;
    foreach ($extensions as $ext) {
      $imageFile = "$base.$ext";
      if (!is_file($imageFile)) continue;
      $url = str_replace(
        $this->wire->config->paths->root,
        $this->wire->config->urls->root,
        $imageFile
      );
    }

    if (!$url) {
      // no custom button found
      // try to find one in /RockPageBuilder/buttons/...
      $path = $this->wire->config->paths($this->master()) . "buttons/";
      $imageFile = $path . $this->className . ".svg";
      if (!is_file($imageFile)) {
        $imageFile = $path . "_blank.svg";
        $icon = "<i class='fa fa-{$info->icon}'></i>";
      }
      $url = str_replace(
        $this->wire->config->paths->root,
        $this->wire->config->urls->root,
        $imageFile
      );
    }

    return "<img class=rpb-addblock-svg src=$url>$icon";
  }

  /**
   * Get RockMigrations instance
   * @return RockMigrations
   */
  public function rm()
  {
    return $this->wire->modules->get('RockMigrations');
  }

  /**
   * Get RockMigrations instance
   * @return RockMigrations
   */
  public function rockmigrations()
  {
    return $this->wire->modules->get('RockMigrations');
  }

  public function rockSearchIndex()
  {
    $title = $this->getInfo()->title;
    return "++$title++";
  }

  /**
   * Get RockPageBuilder Process Url
   * Usage:
   * $this->rpbUrl("/add?block=123");
   * $this->rpbUrl("/add?field=foo_field");
   * @return string
   */
  public function rpbUrl($url)
  {
    return $this->master()->rpbUrl($url);
  }

  /**
   * Return section background class
   */
  public function sectionBG(): string
  {
    $key = $this->settings('sectionbg');
    $arr = $this->sectionBGArray(0);
    return array_key_exists($key, $arr) ? $arr[$key] : '';
  }

  /**
   * Get section background values array
   * what 0 = values
   * what 1 = labels
   */
  public function sectionBGArray($what = 0): array
  {
    $conf = $this->wire->config->sectionBG ?: [
      'muted' => ['uk-background-muted', $this->_('Ausgegraut')],
    ];

    $arr = [];
    foreach ($conf as $k => $v) {
      $arr[$k] = $v[$what];
    }

    return $arr;
  }

  /**
   * Get settings dropdown for section background
   */
  public function sectionBGSettings(&$settings, $field)
  {
    $settings->add([
      'name' => 'sectionbg',
      'label' => 'Background',
      'value' => $field->input('sectionbg', 'select', $this->sectionBGArray(1)),
    ]);
  }

  /**
   * Set reference to file
   * @return void
   */
  public function setFile($file)
  {
    $this->file = $file;
  }

  /**
   * Set reference to migration yaml
   */
  public function setMigrateFile($file)
  {
    $this->yaml = substr($file, 0, -4) . ".yaml";
  }

  /**
   * Set field value in all languages
   * @return void
   */
  public function setInAllLanguages($field, $value)
  {
    $this->set($field, $value);
    if (!$languages = $this->wire->languages) return;
    foreach ($languages as $lang) $this->setLanguageValue($lang, $field, $value);
  }

  /**
   * Save reference to page and field of this rpb block
   * @return void
   */
  public function setBlockReference($page, $field)
  {
    $this->meta('RockPageBuilder', "$page-$field");
  }

  /**
   * Return values of settings field
   *
   * Usage:
   * $settings = $block->settings();
   *
   * Get block setting "side" and use "right" as default value:
   * $side = $block->settings("side", "right");
   *
   * @return WireData
   */
  public function settings($prop = null, $default = null)
  {
    try {
      $settings = $this->rockfieldValue($this->settingsName());
    } catch (\Throwable $th) {
      // we requested a settings property that does not exist
      // so we return false to make sure WireData is not interpreted as wrong
      // "true" value
      if ($prop) return false;

      // no property requested, so we return a new WireData object
      // so that we dont get errors on ->settings()->...
      return new WireData();
    }
    if ($prop) {
      // try to get settings property
      $val = $settings->get($prop);
      return $val ?: $default;
    }
    return $settings;
  }
  public function settingsName()
  {
    return $this->getTplName() . "-settingsfield";
  }
  public function settingsInput(RockFieldsField $field)
  {
  }

  /**
   * The sleep method defines which values will be stored in the DB
   */
  public function settingsSleep(RockFieldsField $field)
  {
    // In RockPageBuilder we often use the "settingsTable" method as shortcut.
    // This makes it possible to define settings with one single method
    // instead of a pair of settingsInput and settingsSleep
    if (method_exists($this, 'settingsTable')) {
      $arr = [];
      $settings = $this->settingsTable($field);
      if ($settings instanceof BlockSettingsArray) {
        $settings = $settings->getPlainArray();
      }
      foreach ($settings as $label => $f) {
        $arr[] = $field->getInputArray($f->sleepName);
      }
      return $arr;
    }
  }

  /**
   * Try to apply RockFrontend postCSS rules
   */
  public function postCSS($str): string
  {
    if (is_array($str)) {
      if (count($str) == 2) $str = "rfGrow({$str[0]}, {$str[1]})";
      // a vSpace of 0 leads to a single value array
      // this converts it back to a single string value
      else $str = (string)$str[0];
    }
    $rf = $this->rockfrontend();
    if (!$rf) return $str;
    return $rf->postCSS($str);
  }

  /**
   * @return RockFrontend
   */
  public function rockfrontend()
  {
    return $this->wire->modules->get('RockFrontend');
  }

  /**
   * Set and save a single settings value
   */
  public function saveSetting($key, $value)
  {
    $settings = $this->settings();
    $settings->set($key, $value);
    $this->meta("rockfield-" . $this->settingsName(), $settings->getArray());
  }

  /**
   * Add vertical spacing style attribute to the current block
   * See styles() for usage info
   */
  public function spaceStyles($styles = '', $useMagic = true)
  {
    if ($styles === false) {
      $styles = '';
      $useMagic = false;
    }

    $str = "style='$styles'";

    // add space styles only if the block has an id
    // this is to make runtime blocks possible
    if ($this->id) {
      /** @var Block $block */
      $block = $this->getWidgetBlock();
      $top = $this->spaceArray($block->getSpaceTop());
      $bottom = $this->spaceArray($block->getSpaceBottom());
      if (!is_array($top) or !is_array($bottom)) return;
      if (count($top) < 2 or count($bottom) < 2) return;

      $rf = $this->rockfrontend();
      if ($rf) {
        $top = $rf->rfGrow([
          'min' => $top[0],
          'max' => $top[1],
          'scale' => 'var(--vscale-top)',
        ]);
        $bottom = $rf->rfGrow([
          'min' => $bottom[0],
          'max' => $bottom[1],
          'scale' => 'var(--vscale-bottom)',
        ]);
        $vtop = $block->meta('vspace-top');
        if ($vtop === null) $vtop = RockFrontend::defaultVspaceScale;
        $vbottom = $block->meta('vspace-bottom');
        if ($vbottom === null) $vbottom = RockFrontend::defaultVspaceScale;

        $str = "style='padding-top: $top; padding-bottom: $bottom; --vscale-top:$vtop; --vscale-bottom:$vbottom; $styles'";
      }
    }

    if (!$useMagic) return $str;

    // using rockfrontend magic to replace a uniqe string with real markup
    // without using |noescape filter in latte
    $key = "#rpbstyle-$this";
    $this->rpb()->blockStylesCache->set($key, $str);

    return $key;
  }

  public function getWidgetBlock()
  {
    return $this->_widget ?: $this;
  }

  /**
   * Get bottom spacing that depends on the next block
   */
  public function getSpaceBottom()
  {
    $next = $this->nextBlock();
    $spaceB = $this->spaceB();

    // if there is no next block we return the full space of current block
    if (!$next) return $spaceB;

    // there is a block above this one
    // now check if the spaceID matches
    if ($next->spaceID() != $this->spaceID()) return $spaceB;

    // we need to calculate half space for each block
    return $this->halfSpace($this->spaceB(), $next->spaceB());
  }
  /**
   * Get top spacing that depends on the previous block
   */
  public function getSpaceTop()
  {
    $prev = $this->prevBlock();
    $spaceT = $this->spaceT();

    // if there is no previous block we return the full space of current block
    if (!$prev) return $spaceT;

    // there is a block above this one
    // now check if the spaceID matches
    if ($prev->spaceID() != $this->spaceID()) return $spaceT;

    // we need to calculate half space for each block
    return $this->halfSpace($this->spaceT(), $prev->spaceT());
  }

  /**
   * Return half space of this block and other block
   *
   * Usage:
   * halfSpace('10pxrem', '20pxrem'); // 15pxrem
   *
   * halfSpace(
   *  ['10pxrem', '20pxrem'],
   *  ['20pxrem', '40pxrem'],
   * ); // ['15pxrem', '30pxrem']
   */
  public function halfSpace($one, $two)
  {
    $one = $this->spaceArray($one);
    $two = $this->spaceArray($two);
    if (count($one) !== count($two)) {
      // one block has a single value spacing
      // in that case we use it for both values
      if (count($one) === 1) $one = [$one[0], $one[0]];
      if (count($two) === 1) $two = [$two[0], $two[0]];
    }
    $half = [];
    foreach ($one as $i => $o) {
      $t = $two[$i];
      $v1 = $this->spaceVal($o);
      $u1 = $this->spaceUnit($o);
      $v2 = $this->spaceVal($t);
      $u2 = $this->spaceUnit($t);

      // this makes sure that a vSpace of 0 works as expected
      // the 0 value than takes the same unit as the other value
      if (!$u1) $u1 = $u2;
      if (!$u2) $u2 = $u1;
      if ($u1 !== $u2) {
        // bd($v1, 'v1');
        // bd($v2, 'v2');
        // bd($u1, 'u1');
        // bd($u2, 'u2');
        throw new WireException("The units of your block spacings have to match - otherwise we cant calculate the deviding value!");
      }

      // calculate half spacing of both blocks
      $half[$i] = round(max($v1, $v2) / 2, 3) . $u1;
    }
    return $half;
  }

  /**
   * Return number part of space data
   *
   * spaceVal('10px'); // 10
   * spaceVal('2pxrem'); // 2
   */
  public function spaceVal($data): float
  {
    return (float)$data;
  }

  /**
   * Return unit part of given space data
   *
   * spaceUnit('10px'); // px
   * spaceUnit('2.5rem'); // rem
   */
  public function spaceUnit($data): string
  {
    preg_match("/(.*?)([a-z]+)/", $data, $matches);
    if (array_key_exists(2, $matches)) return $matches[2];
    return '';
  }

  /**
   * Method that returns bottom space for this block
   * Can be overridden in users block file
   */
  public function spaceB()
  {
    $spaceB = $this->getInfo()->spaceB;
    if ($spaceB === null) return $this->spaceV();
    return $spaceB;
  }

  public function spaceID(): string
  {
    $id = $this->getInfo()->spaceID;
    if (!$id) $id = "default";
    return $id;
  }

  /**
   * Method that returns top space for this block
   * Can be overridden in users block file
   */
  public function spaceT()
  {
    $spaceT = $this->getInfo()->spaceT;
    if ($spaceT === null) return $this->spaceV();
    return $spaceT;
  }

  /**
   * Method that returns vertical space for this block
   * Can be overridden in users block file
   */
  public function spaceV()
  {
    return $this->getInfo()->spaceV ?: 0;
  }

  public function spaceArray($value): array
  {
    if (is_array($value)) return $value;
    if (strpos($value, ",")) return explode(",", $value);
    return [$value];
  }

  /**
   * Shortcut for spaceStyles
   *
   * Usage:
   * $block->styles()
   *
   * Usage with custom styles:
   * $block->styles("border: 2px solid red;");
   */
  public function styles($styles = '', $useMagic = true)
  {
    return $this->spaceStyles($styles, $useMagic);
  }

  /**
   * Shortcut for $rockfrontend->svg()
   */
  public function svg($file, $replace = null)
  {
    return $this->rockfrontend()->svg($file, $replace);
  }

  /**
   * Array of translatable strings
   * Use $block->x('your_string') to get string.
   * See RockPageBuilder readme about translating blocks.
   * @return array
   */
  public function translations()
  {
    return $this->getInfo()->x ?: [];
  }

  /**
   * Convert this block into a widget
   * @return Block
   */
  public function toWidget()
  {
    $block = $this;
    $fielddata = $block->getBlockData();

    // create new widget with reference to block
    $tpl = (new Widget())->getTplName();
    $widget = $fielddata->createBlock($tpl);
    $widget->setReference($block);
    $widget->save();
    $fielddata->insertAfter($widget, $block)->save();

    // move block to widgets
    $block->move(1, RockPageBuilder::field_widgets);
  }

  /**
   * Truncate text to given length
   * @return string
   */
  public function truncate($str, $maxLength = 300, $options = [])
  {
    return $this->wire->sanitizer->truncate($str, $maxLength, $options);
  }

  /**
   * Get index of this block type:
   * A(0) / B(0) / B(1) / B(2) / A(0) / A(1) / B(0)
   * @return int
   */
  public function typeIndex()
  {
    $i = 0;
    $current = $this;
    while ($prev = $current->prevBlock()) {
      if ($prev->template->name !== $current->template->name) return $i;
      $i++;
      $current = $prev;
    }
    return $i;
  }

  /**
   * Get total typeindex
   * A(0) / B(0) / A(1) / A(2) / B(1)
   */
  public function typeIndexTotal()
  {
    $i = 0;
    $current = $this;
    $tpl = $current->template->name;
    while ($prev = $current->prevBlock()) {
      if ($current->template->name == $tpl) $i++;
      $current = $prev;
    }
    return $i - 1;
  }

  /**
   * Get path of the view file for this block
   */
  public function viewFile(): string
  {
    foreach ($this->viewFiles() as $file => $type) {
      if (is_file($file)) return $file;
    }
    return '';
  }

  /**
   * Get all possible view files for current block
   * @return array
   */
  public function viewFiles()
  {
    if (!$this->getMasterBlock()) return [];
    $file = $this->getMasterBlock()->file;
    $base = substr($file, 0, -4); // without .php ending
    return [
      "$base.latte" => "latte",
      "$base.view.php" => "php",
    ];
  }

  /**
   * Short-alias for migrateAfterYaml
   */
  public function migrate()
  {
  }

  /**
   * Migrations applied before migrating the yaml file
   */
  public function migrateBeforeYaml()
  {
  }

  /**
   * Migrations applied after migrating the yaml file
   */
  public function migrateAfterYaml()
  {
  }

  /**
   * Initial Block Migrations
   */
  public final function migrateInitial()
  {
    // we always create the related template
    $rm = $this->rm();
    $rm->log('Migrate ' . $this->getInfo()->name);

    // use the template name, not $this!!
    // this ensures that it works even where $this->template = null
    $tpl = $this->getTplName();
    $rm->createTemplate($tpl);
    $rm->setTemplateData($tpl, [
      'icon' => $this->getInfo()->icon,
      'pageClass' => $this->getInfo()->name,
      'tags' => RockPageBuilder::tags,
      'noParents' => 1, // may not be used for new pages
      'flags' => Template::flagSystem,
      'noChildren' => true, // hide children tab by default
      'noSettings' => true, // hide settings tab by default
      'fields' => [
        'title' => ['required' => false],
      ],
    ]);
  }

  /**
   * Migrate YAML file
   */
  public final function migrateYaml()
  {
    $this->rm()->migrateYAML($this->yaml);
  }

  /**
   * Uninstall this block
   * Not hookable --> call parent::uninstall() in derived classes
   */
  public function uninstall()
  {
    $this->log('Uninstalling ' . $this->getInfo()->name);
    $this->rm()->deleteTemplate($this->getTplName());
  }

  /**
   * Translate given string
   * @return string
   */
  public function x($key)
  {
    // get translations of the block
    $translations = $this->translations();
    if (is_array($translations) and array_key_exists($key, $translations)) {
      return $translations[$key];
    }
    return $key;
  }
}
