<?php namespace RockMatrix;

use Latte\Engine;
use Latte\Runtime\Html;
use ProcessWire\FieldtypeRockMatrix;
use ProcessWire\FieldtypeRockShare;
use ProcessWire\Paths;
use ProcessWire\RockMatrix;
use \ProcessWire\WireData;
use \ProcessWire\RockMigrations;
use \ProcessWire\Inputfield;
use \ProcessWire\InputfieldFile;
use \ProcessWire\InputfieldWrapper;
use \ProcessWire\InputfieldFieldset;
use ProcessWire\PageArray;
use ProcessWire\RockFields;
use ProcessWire\RockFieldsField;
use ProcessWire\Template;
use ProcessWire\WireException;
use ReflectionClass;
use RMBlock\Widget;

class Block extends \ProcessWire\Page {

  const prefix = "rmblock_";
  const tags = "RockMatrix";

  /**
   * References the current file
   * @var string
   **/
  public $file;

  private $info;

  /** @var Engine */
  private $latte;

  public function info() {
    // this is for backwards compatibility
    // for blocks that use the old syntax for info() method
    return new WireData();
  }

  public function __construct() {
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
  public function init() {}

  /**
   * Add ALFRED icons (for RockFrontend)
   * Note: Can not be hookable (reference does not work!)
   * @return void
   */
  public function addAlfredIcons(&$icons, $opt) {
    // if the _block context is set for this block we use it as block
    // this is to support the concept of "widgets" where widgets render global blocks.
    // when trashing such a block we want to trash the reference widget and not the global block itself!
    $block = $this;
    $widget = $block->_widget ?: $block;
    $data = $widget->getMatrixData();
    if($opt->clone AND $block->editable()) {
      $icons[] = (object)[
        'icon' => 'clone',
        'label' => $block->title,
        'tooltip' => "Clone Block #{$widget->id}",
        'href' => $widget->rmxUrl("/clone/?block=$widget"),
        'confirm' => $this->_('Do you really want to clone this element?'),
      ];
    }
    // show move icon only when more than 1 block
    if($opt->move AND $data->count()>1) {
      $icons[] = (object)[
        'icon' => 'move',
        'label' => $block->title,
        'tooltip' => "Move Block #{$widget->id}",
        'class' => 'pw-modal',
        'href' => $widget->getMatrixPage()->editUrl."&field=".$widget->getMatrixField()."&moveblock=$widget",
        'suffix' => 'data-buttons="button.ui-button[type=submit]" data-autoclose data-reload',
      ];
    }

    // convert block into widget
    if($opt->widget AND $block->canBeWidget()) {
      $icons[] = (object)[
        'icon' => 'widget',
        'label' => $block->title,
        'tooltip' => "Convert Block #{$block->id} into a Widget",
        'href' => $block->rmxUrl("/convertToWidget/?block=$block"),
        'confirm' => $this->_('Do you really want to convert this block into a widget?'),
      ];
    }

    if($opt->trash AND $block->trashable()) {
      $icons[] = (object)[
        'icon' => 'trash-2',
        'label' => $block->title,
        'tooltip' => "Trash Block #{$widget->id}",
        'href' => $widget->rmxUrl("/trash/?block=$widget"),
        'confirm' => $this->_('Do you really want to delete this element?'),
      ];
    }
  }

  /**
   * Add rockfields settings field for this block
   */
  public function addSettingsField() {
    if(!$rf = $this->wire->modules->get('RockFields')) return;

    // you can prevent showing the settings field
    // by defining "settings => false" in the info() of your block
    if($this->getInfo()->settings === false) return;

    // add field to rockfields
    $rf->add([
      'name' => $this->settingsName(),

      // the inputfield is either defined by the settingsInput method
      // or - eg when using rockmatrix - by the settingsTable method
      'inputfield' => method_exists($this, 'settingsTable')
        ? [$this, 'settingsTable']
        : [$this, 'settingsInput'],
      'sleep' => [$this, 'settingsSleep'],
    ]);
  }

  public function addSettingsFieldToForm(InputfieldWrapper $fs) {
    /** @var RockFields $rf */
    if(!$rf = $this->wire->rockfields) return;
    if(!$f = $rf->getInputfield($this, $this->settingsName(), true)) return;
    $f->addClass('rmx-settings');

    // set settings field values from getInfo() of block
    $settings = $this->getInfo()->settings;
    if(is_array($settings)) $f->setArray($settings);

    $fs->add($f);
  }

  /**
   * Build form to edit this block
   * @return void
   */
  public function ___buildForm($fs) {}

  /**
   * Build the form when displayed in a matrix field
   * @return void
   */
  public function ___buildFormMatrix($fs) {}

  public function canBeWidget() {
    return $this->isAllowed(RockMatrix::field_widgets, 1);
  }

  /**
   * Clone this block
   *
   * Will add the block to the same field right after the cloned item
   *
   * @return Block
   */
  public function clone() {
    $block = $this;
    $fielddata = $block->getMatrixData();
    $this->matrix()->isClone = true;
    $clone = $this->wire->pages->clone($block); /** @var Block $clone */
    $this->matrix()->isClone = false;
    $fielddata->insertAfter($clone, $block);
    $fielddata->save();
  }

  /**
   * Move this block to given page and field
   * @return void
   */
  public function move($page, $field) {
    $page = $this->wire->pages->get((string)$page);
    $field = $this->wire->fields->get((string)$field);
    if(!$this->isAllowed($field, $page)) {
      throw new WireException("Block #$this is not allowed on page $page and field $field");
    }
    $new = $page->getFormatted($field->name);
    if(!$new instanceof FieldData) {
      throw new WireException("Requested field $field on page $page is not valid");
    }

    // remove from old field
    $old = $this->getMatrixData();
    $old->remove($this);
    $old->save();

    // add to new field
    $new->add($this);
    $new->save();
    $this->setMatrixReference($page, $field);
  }

  /**
   * Get path of block file
   * @return string
   */
  public function filePath() {
    $reflector = new ReflectionClass($this);
    return Paths::normalizeSeparators($reflector->getFileName());
  }

  /**
   * Get collapsed state of item
   */
  public function getCollapsedState() {
    return $this->wire->config->ajax
      ? Inputfield::collapsedNo
      : Inputfield::collapsedYes;
  }

  /**
   * Get icon for matrix item
   * @return string
   */
  public function getIcon() {
    return $this->getInfo()->icon;
  }

  /**
   * Get info WireData
   * @return WireData
   */
  public function getInfo() {
    if($this->info) return $this->info;
    $info = $this->wire(new WireData()); /** @var WireData $info */
    $info->setArray([
      'title' => $this->className,
      // this is the full classname eg Foo\Bar\Baz
      // use $block->className for the classname without namespace (pw-feature)
      'name' => get_class($this),
      'icon' => 'cube',
      'sort' => 500,
    ]);
    $blockInfo = $this->info();
    if($blockInfo instanceof WireData) $blockInfo = $blockInfo->getArray();
    $info->setArray($blockInfo);
    return $info;
  }

  /**
   * Get label for matrix item
   * @return string
   */
  public function getLabel() {
    $label = $this->title ?: $this->getInfo()->title;
    return $this->wire->sanitizer->truncate($label, 50);
  }

  /**
   * Get markup array for wrapper
   * @return array
   */
  public function getMarkupArray($wrapper) {
    $markup = $wrapper->getMarkup();

    // actions
    $markup['item_label'] = str_replace(
      "{out}",
      "{out}".$this->renderActions(),
      $markup['item_label']
    );

    return $markup;
  }

  /**
   * Get the master block object that was used for initializing this block
   * @return Block
   */
  public function getMasterBlock() {
    return $this->master()->getBlockByTpl($this->getTpl());
  }

  /**
   * Get the matrix data object of the field where this block lives on
   * @return FieldData
   */
  public function getMatrixData() {
    $page = $this->getMatrixPage();
    $field = $this->getMatrixField();
    if(!$page OR !$field) return false;
    return $page->get($field->name);
  }

  /**
   * Return the field where this block lives on
   * @return Field
   */
  public function getMatrixField() {
    $meta = explode("-", $this->meta('RockMatrix'));
    if(!is_array($meta) OR count($meta)!==2) return false;
    return $this->wire->fields->get($meta[1]);
  }

  /**
   * Index starting from 1
   * @return integer
   */
  public function getMatrixNum() {
    return $this->getMatrixIndex()+1;
  }

  /**
   * Return the page where this block lives on
   * Every block can only live on ONE single page!!
   * @return Page
   */
  public function getMatrixPage() {
    // the page is stored in metadata of the block
    // the metadata is pageid-fieldid
    $meta = explode("-", (string)$this->meta('RockMatrix'));
    return $this->wire->pages->get($meta[0]);
  }

  /**
   * Get the index (sort order) of this matrix item
   * @param bool $startAtOne
   * @return int|false
   */
  public function getMatrixIndex($startAtOne = false) {
    $i = $startAtOne ? 1 : 0;
    $items = $this->getMatrixData();
    if(!$items) return false;
    foreach($items as $item) {
      if($item->id === $this->id) return $i;
      $i++;
    }
    return false;
  }

  /**
   * Get notes for matrix item
   * @return string
   */
  public function getNotes() {
    return $this->getInfo()->description;
  }

  /**
   * Get parent for this block
   * @return Page
   */
  public function ___getParent($field, $page) {
    return $this->master()->getDatapage();
  }

  /**
   * Get parents of current block that should be saved when a block is saved.
   * This is necessary to trigger ProCache reset of edited pages
   * @return PageArray
   */
  public function getParentsToSave() {
    $pages = new PageArray();
    $current = $this->getMatrixPage();
    while($current instanceof Block) {
      $pages->add($current);
      $current = $current->getMatrixPage();
    }
    $pages->add($current);
    return $pages;
  }

  /**
   * Get the related pw template
   */
  public function getTpl() {
    return $this->wire->templates->get($this->getTplName());
  }

  /**
   * Convert the class name to a pw valid tpl name
   * @return string
   */
  public function getTplName() {
    $class = $this->getInfo()->name;
    return $this->wire->sanitizer->pagename($class);
  }

  /**
   * Get wrapper for editing this block
   * @return InputfieldWrapper
   */
  public function getWrapper() {
    $r = $this->wire->modules->get('InputfieldRepeater'); /** @var InputfieldRepeater $r */
    $wrap = $this->wire(new InputfieldWrapper()); /** @var InputfieldWrapper $wrap */
    $fs = $this->wire(new InputfieldFieldset()); /** @var InputfieldFieldset $fs */

    $wrap->add($fs);
    $wrap->suffix = "_repeater$this";

    // prepare label
    $label = (string)$this->getLabel() ?: $this->getInfo()->title;
    $label = $this->wire->sanitizer->truncate(strip_tags($label), 70);

    // prepare the fieldset (item root element)
    $fs->id = "rmx_$this";
    $fs->label = $label;
    $fs->icon = $this->getIcon();
    $fs->notes = $this->getNotes();
    $fs->addClass('rmx-item');
    $fs->wrapAttr('data-page', $this->id);
    if($this->wire->user->isSuperuser()) {
      $fs->wrapAttr('uk-tooltip', "{$this->className} #{$this->id}");
    }
    $fs->wrapAttr('data-tpl', $this->template->name);
    if($col = $this->getInfo()->color) {
      $fs->wrapAttr('style', "border-left: 5px solid $col");
    }
    $fs->collapsed = $this->getCollapsedState();

    // prepare form (and add settings field)
    $this->prepareForm($fs);

    // apply changes added to buildForm
    // buildForm changes will also be applied when editing
    // the block in a new window whereas buildFormMatrix
    // will only be applied when editing in a matrix field
    $this->buildForm($fs);

    // call buildFormMatrix (if implemented for the block)
    $this->buildFormMatrix($fs);

    // add repeater suffix to all children
    foreach($this->matrix()->getChildrenRecursively($fs) as $f) {
      // add the suffix to the inputfields name
      // before we do that we make sure that it does not already
      // have a repeater suffix to avoid adding the suffix twice
      // this can happen on RockMeta fields (don't know why, quickfix)
      $name = preg_replace('/_repeater\d+$/', '', $f->name);
      $f->name = $name.$wrap->suffix;

      // open wrapper if field has an error
      if(count($f->getErrors())) $fs->collapsed = Inputfield::collapsedNo;

      // non-editable blocks are locked for edits
      if(!$this->editable()) {
        $f->collapsed = ($f->collapsed == Inputfield::collapsedNo)
          ? Inputfield::collapsedNoLocked
          : Inputfield::collapsedYesLocked;
      }

      // changes for file inputfields
      if(!$f instanceof InputfieldFile) continue;
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
  public function html($str) {
    try {
      return new Html($str);
    } catch (\Throwable $th) {
      return $str;
    }
  }

  /**
   * Does this block have an even index?
   * @return bool
   */
  public function indexEven() {
    return $this->getMatrixIndex()%2===0;
  }

  /**
   * Does this block have an even index?
   * @return bool
   */
  public function indexOdd() {
    return $this->getMatrixIndex()%2!==0;
  }

  /**
   * Is this block allowed on given page and field?
   * @return bool
   */
  public function isAllowed($field, $page) {
    $field = $this->wire->fields->get((string)$field);
    $page = $this->wire->pages->get((string)$page);
    $allowed = $this->master()->getAllowedBlocks($field, $page);
    foreach($allowed as $b) {
      if($b->getInfo()->name === $this->getInfo()->name) return true;
    }
    return false;
  }

  /**
   * Check if method is defined in current class
   * Returns FALSE if the method is inherited
   * See https://bit.ly/3IWuayR
   */
  protected function isDefined($method) {
    $class = get_class($this);
    return (method_exists($class, $method)) &&
      ($class === (new \ReflectionMethod($class, $method))->getDeclaringClass()->name);
  }

  /**
   * Is this block-NUMBER even (2, 4, 6)?
   * @return bool
   */
  public function isEven() {
    return $this->getMatrixNum() % 2 === 0;
  }

  /**
   * Is this block-type-NUMBER even (2, 4, 6)?
   * @return bool
   */
  public function isEvenType() {
    return ($this->typeIndex()+1) % 2 === 0;
  }

  /**
   * Is this item the first item?
   * @return bool
   */
  public function isFirstMatrixItem() {
    return $this->getMatrixIndex() === 0;
  }

  /**
   * Is this item the last item?
   * @return bool
   */
  public function isLastMatrixItem() {
    $data = $this->getMatrixData();
    if(!$data) return true;
    return $this->getMatrixIndex(true) === $data->count();
  }

  /**
   * Is this block-NUMBER odd (1, 3, 5)?
   * @return bool
   */
  public function isOdd() {
    return $this->getMatrixNum() % 2 !== 0;
  }

  /**
   * Is this block-type-NUMBER odd (1, 3, 5)?
   * @return bool
   */
  public function isOddType() {
    return ($this->typeIndex()+1) % 2 !== 0;
  }

  /**
   * Is the parent page saved?
   * @return bool
   */
  public function isSaved() {
    return !!$this->getMatrixIndex(true);
  }

  /**
   * Is this block a RockMatrix widget stored in field rockmatrix_widgets?
   * @return bool
   */
  public function isWidget() {
    return $this->getMatrixField()->name == RockMatrix::field_widgets;
  }

  /**
   * Return master module
   * @return RockMatrix
   */
  public function master() {
    return $this->wire->modules->get('RockMatrix');
  }

  /**
   * Return instance of RockMatrix
   * @return RockMatrix
   */
  public function matrix() {
    return $this->wire->modules->get('RockMatrix');
  }

  /**
   * Get next matrix item
   * @return Page|false
   */
  public function nextMatrixItem() {
    $match = false;
    foreach($this->getMatrixData() as $item) {
      if($match) return $item;
      if($item->id === $this->id) $match = true;
    }
    return false;
  }

  /**
   * Prepare form for being rendered as a matrix block
   * This is a separate method that needs to be called before buildForm
   * or buildFormMatrix. The reason for this method is that buildForm and
   * buildFormMatrix do not need to call parent::buildForm, because that would
   * be prone to errors.
   * @return void
   */
  protected function prepareForm($fs) {
    // the default is to add all fields of the page template
    $fields = $this->getInputfields();
    if(!$fields) return $fs;
    foreach($fields->children() as $f) {
      $type = $f->hasField->type;

      // prevent recursion
      if($type instanceof FieldtypeRockMatrix) {
        if($this->wire->process->getPage()->id == $f->value->page->id) {
          // we are editing the block in the page editor
          // we set the value to empty string to hide the item-edit-button
          $value = '';
        }
        elseif($f->value->page->isSaved()) {
          $id = $f->value->page->id;
          $url = $this->wire->pages->get(2)->url."page/edit/?id=$id&field=".$f->name;
          $label = $f->label;
          $value = "<a href='$url' class='pw-panel pw-panel-reload
          uk-button uk-button-default'>$label</a>";
        }
        else {
          $value = $this->_("Please save the page, then you can come back here and edit block items.");
        }
        $fields->add([
          'name' => $f->name."_markup",
          'type' => 'markup',
          'value' => $value,
        ]);
        $markup = $fields->children()->last();
        $fields->remove($markup);
        $fields->insertAfter($markup, $f);
        $fields->remove($f);
      }

      // sharing of pages not possible inside matrix
      if($type instanceof FieldtypeRockShare) $fields->remove($f);
    }

    foreach($fields as $field) {
      if($fs->has($field->name)) continue;
      $fs->add($field);
    }

    // add rockfields settings to form
    $this->addSettingsFieldToForm($fs);
  }

  /**
   * Get previous matrix item
   * @return Page|false
   */
  public function prevMatrixItem() {
    $prev = false;
    foreach($this->getMatrixData() as $item) {
      if($item->id === $this->id) return $prev;
      $prev = $item;
    }
    return false;
  }

  /**
   * Get relative path where this block lives
   * This is handy for getting the path of the customstyles js on CKE fields
   * @return string
   */
  public function relativePath() {
    return str_replace(
      $this->wire->config->paths->root,
      $this->wire->config->urls->root,
      dirname($this->filePath())
    )."/";
  }

  /**
   * Render this block
   * @return string
   */
  public function render() {
    foreach($this->viewFiles() as $file => $type) {
      if(is_file($file)) return $this->renderFile($file, $type);
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
  public function renderFile($file, $type = null) {
    // make all api variables available in the template file
    $vars = array_merge(
      $this->wire('all')->getArray(),
      [
        'block' => $this,
        'settings' => $this->settings(),
      ]
    );
    if(!$type) $type = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    if(!is_file($file)) $file = dirname($this->filePath())."/$file";
    if($type == 'php') {
      $opt = ['allowedPaths' => [dirname($file)]];
      return $this->wire->files->render($file, $vars, $opt);
    }
    elseif($type == 'latte') {
      $latte = $this->latte;
      if(!$latte) {
        try {
          // load latte from RockFrontend
          $vendor = $this->wire->config->paths->siteModules."RockFrontend/vendor/autoload.php";
          if(is_file($vendor)) require_once $vendor;
          else {
            // load latte from PW root
            $vendor = $this->wire->config->paths->root."vendor/autoload.php";
            if(is_file($vendor)) require_once $vendor;
          }

          $latte = new Engine();
          $latte->setTempDirectory($this->wire->config->paths->cache."Latte");
          $this->latte = $latte;
        } catch (\Throwable $th) {
          $msg = "<br>Install Latte or delete the .latte view file and use
            the plain php view file instead.";
          return "<strong>".$th->getMessage()."</strong>$msg";
        }
      }
      return $latte->renderToString($file, $vars);
    }
  }

  /**
   * Render a single block action
   * @return string
   */
  public function renderAction($action, $data) {
    $opt = $this->wire(new WireData()); /** @var WireData $opt */
    $opt->setArray([
      'href' => '#',
      'attrs' => [],
    ]);
    $opt->setArray($data);
    $icon = $opt->icon ?: $action;

    // prepare custom attributes
    $attrs = '';
    foreach($opt->attrs as $k=>$v) $attrs .= " data-$k='$v'";

    return
      "<a href='{$opt->href}'
        class='rmx-action rmx-action-$action'
        uk-tooltip='title:{$opt->label};pos:left;'
        data-action='$action'
        $attrs>
        <i class='fa fa-$icon'></i>"
      ."</a>";
  }

  /**
   * Render actions for this item
   */
  public function renderActions() {
    $out = "<span class='rmx-actions'>";
    if($this->wire->user->isSuperuser()) {
      $out .= $this->renderAction('editnew', [
        'label' => $this->_('edit in new window'),
        'icon' => 'external-link',
        'href' => $this->wire->pages->get(2)->url."page/edit/?id=$this",
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
    $out .= $this->renderAction('trash', [
      'label' => $this->_('Mark for deletion'),
    ]);
    $out .= $this->renderAction('untrash', [
      'label' => $this->_('Undo deletion'),
      'icon' => 'undo',
    ]);
    if($this->wire->user->isSuperuser()) {
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
  public function renderButton($page, $field) {
    $block = $this->wire->input->get('block', 'int');
    $above = $this->wire->input->get('above', 'int');
    $tpl = $this->getTplName();

    if($block) $href = $this->rmxUrl("/add/?block=$block&above=$above&tpl=$tpl&modal=1");
    else $href = $this->rmxUrl("/add-new/?page=$page&field=$field&tpl=$tpl&modal=1");

    $ajax = "./?id=$page&field=$field&tpl=$tpl";
    return "<a href='$href' data-href='$ajax' class='rmx-button'>{$this->svg()}</a>";
  }

  /**
   * Get RockMigrations instance
   * @return RockMigrations
   */
  public function rm() {
    return $this->wire->modules->get('RockMigrations');
  }

  /**
   * Get RockMatrix Process Url
   * Usage:
   * $this->rmxUrl("/add?block=123");
   * $this->rmxUrl("/add?field=foo_field");
   * @return string
   */
  public function rmxUrl($url) {
    return $this->master()->rmxUrl($url);
  }

  /**
   * Set reference to file
   * @return void
   */
  public function setFile($file) {
    $this->file = Paths::normalizeSeparators($file);
  }

  /**
   * Set field value in all languages
   * @return void
   */
  public function setInAllLanguages($field, $value) {
    $this->set($field, $value);
    if(!$languages = $this->wire->languages) return;
    foreach($languages as $lang) $this->setLanguageValue($lang, $field, $value);
  }

  /**
   * Save reference to page and field of this matrix block
   * @return void
   */
  public function setMatrixReference($page, $field) {
    $this->meta('RockMatrix', "$page-$field");
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
  public function settings($prop = null, $default = null) {
    try {
      $settings = $this->rockfieldValue($this->settingsName());
    } catch (\Throwable $th) {
      return new WireData();
    }
    if($prop) {
      // try to get settings property
      $val = $settings->get($prop);
      return $val ?: $default;
    }
    return $settings;
  }
  public function settingsName() {
    return $this->getTplName()."-settingsfield";
  }
  public function settingsInput(RockFieldsField $field) {}

  /**
   * The sleep method defines which values will be stored in the DB
   */
  public function settingsSleep(RockFieldsField $field) {
    // In RockMatrix we often use the "settingsTable" method as shortcut.
    // This makes it possible to define settings with one single method
    // instead of a pair of settingsInput and settingsSleep
    if(method_exists($this, 'settingsTable')) {
      $arr = [];
      $settings = $this->settingsTable($field);
      if($settings instanceof BlockSettingsArray) {
        $settings = $settings->getPlainArray();
      }
      foreach($settings as $label => $f) {
        $arr[] = $field->getInputArray($f->sleepName);
      }
      return $arr;
    }
  }

  /**
   * Get SVG image tag for this block
   * @return string
   */
  public function svg() {
    if(!$master = $this->getMasterBlock()) return;
    $info = $this->getInfo();
    $file = $master->file;
    $base = substr($file, 0, -4); // without .php ending
    $svg = "$base.svg";
    $icon = '';
    if(!is_file($svg)) {
      // no custom svg button found
      // try to find one in /RockMatrix/buttons/...
      $path = $this->wire->config->paths($this->master())."buttons/";
      $svg = $path.$this->className.".svg";
      if(!is_file($svg)) {
        $svg = $path."_blank.svg";
        $icon = "<i class='fa fa-{$info->icon}'></i>";
      }
    }
    $url = str_replace(
      $this->wire->config->paths->root,
      $this->wire->config->urls->root,
      $svg
    );
    $tooltip = $info->description
      ? "$info->title: $info->description"
      : $info->title;
    $tooltip = "title='$tooltip' uk-tooltip";
    $style = $info->color ? "style='border-left: 5px solid {$info->color}'" : '';
    return "<img $tooltip $style class=rmx-addblock-svg src=$url>$icon";
  }

  /**
   * Array of translatable strings
   * Use $block->x('your_string') to get string.
   * See RockMatrix readme about translating blocks.
   * @return array
   */
  public function translations() {
    return [];
  }

  /**
   * Convert this block into a widget
   * @return Block
   */
  public function toWidget() {
    $block = $this;
    $fielddata = $block->getMatrixData();

    // create new widget with reference to block
    $tpl = (new Widget())->getTplName();
    $widget = $fielddata->createBlock($tpl);
    $widget->setReference($block);
    $widget->save();
    $fielddata->insertAfter($widget, $block)->save();

    // move block to widgets
    $block->move(1, RockMatrix::field_widgets);
  }

  /**
   * Truncate text to given length
   * @return string
   */
  public function truncate($str, $maxLength = 300, $options = []) {
    return $this->wire->sanitizer->truncate($str, $maxLength, $options);
  }

  /**
   * Get index of this block type:
   * A(0) / B(0) / B(1) / B(2) / A(0) / A(1) / B(0)
   * @return int
   */
  public function typeIndex() {
    $i = 0;
    $current = $this;
    while($prev = $current->prevMatrixItem()) {
      if($prev->template->name !== $current->template->name) return $i;
      $i++;
      $current = $prev;
    }
    return $i;
  }

  /**
   * Get all possible view files for current block
   * @return array
   */
  public function viewFiles() {
    if(!$this->getMasterBlock()) return [];
    $file = $this->getMasterBlock()->file;
    $base = substr($file, 0, -4); // without .php ending
    return [
      "$base.latte" => "latte",
      "$base.view.php" => "php",
    ];
  }

  /**
   * Block Migrations
   * Not hookable --> call parent::migrate() in derived classes
   */
  public function migrate() {
    // we always create the related template
    $this->rm()->log('Migrate '.$this->getInfo()->name);
    $tpl = $this->rm()->createTemplate($this->getTplName());
    $this->rm()->setTemplateData($tpl, [
      'icon' => $this->getInfo()->icon,
      'pageClass' => $this->getInfo()->name,
      'tags' => RockMatrix::tags,
      'noParents' => 1, // may not be used for new pages
      'flags' => Template::flagSystem,
      'noChildren' => true, // hide children tab by default
      'noSettings' => true, // hide settings tab by default
    ]);
  }

  /**
   * Uninstall this block
   * Not hookable --> call parent::uninstall() in derived classes
   */
  public function uninstall() {
    $this->log('Uninstalling ' . $this->getInfo()->name);
    $this->rm()->deleteTemplate($this->getTplName());
  }

  /**
   * Translate given string
   * @return string
   */
  public function x($key) {
    // get translations of the block
    $translations = $this->translations();
    if(is_array($translations) AND array_key_exists($key, $translations)) {
      return $translations[$key];
    }
    return $key;
  }
}
