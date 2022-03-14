<?php namespace RockMatrix;

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
use ProcessWire\RockFields;
use ProcessWire\RockFieldsField;
use ProcessWire\Template;

abstract class Block extends \ProcessWire\Page {

  const prefix = "rmblock_";
  const tags = "RockMatrix";

  /**
   * References the current file
   * @var string
   **/
  public $file;

  private $info;

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
   * Add rockfields settings field for this block
   */
  public function addSettingsField() {
    if(!$rf = $this->wire->rockfields) return;

    // you can prevent showing the settings field
    // by defining "settings => false" in the info() of your block
    if($this->getInfo()->settings === false) return;

    // add field to rockfields
    $rf->add([
      'name' => $this->settingsName(),
      'inputfield' => [$this, 'settingsInput'],
      'sleep' => [$this, 'settingsSleep'],
    ]);
  }

  public function addSettingsFieldToForm(InputfieldFieldset $fs) {
    /** @var RockFields $rf */
    if(!$rf = $this->wire->rockfields) return;
    if(!$f = $rf->getInputfield($this, $this->settingsName(), true)) return;
    $f->addClass('rmx-settings');
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
  public function ___buildFormMatrix($fs) {
    $this->buildForm($fs);
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
   * @return BlocksArray
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
    $meta = explode("-", $this->meta('RockMatrix'));
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
   * Get view file for current block
   * @return string|false
   */
  public function getViewFile() {
    $file = $this->getMasterBlock()->file;
    return substr($file, 0, -4).".view.php";
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
    $fs->wrapAttr('data-tpl', $this->template->name);
    $fs->collapsed = $this->getCollapsedState();

    // prepare form, build GUI and add settings field
    $this->prepareForm($fs);
    $this->buildFormMatrix($fs);
    $this->addSettingsFieldToForm($fs);

    // add repeater suffix to all children
    foreach($fs->children() as $f) {
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
   * Return master module
   * @return RockMatrix
   */
  public function master() {
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

      // if rmx-nolabel is true we remove the fields label and make it have
      // smaller paddings
      if($f->hasField->get('rmx-nolabel')) {
        $f->wrapClass('rmx-pd5');
        $f->label = false;
        $f->skipLabel = Inputfield::skipLabelBlank;
      }

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
   * Render this block
   */
  public function render() {
    $view = $this->getViewFile();
    if(is_file($view)) return $this->wire->files->render($view, [
      'block' => $this,
    ], [
      'allowedPaths' => [dirname($view)],
    ]);
    return "Create ".$this->getInfo()->name . "::render() or file $view";
  }

  /**
   * Render a single block action
   * @return string
   */
  public function renderAction($action, $data) {
    $opt = $this->wire(new WireData()); /** @var WireData $opt */
    $opt->setArray([
      'href' => '#',
    ]);
    $opt->setArray($data);
    $icon = $opt->icon ?: $action;
    return
      "<a href='{$opt->href}'
        class='rmx-action rmx-action-$action'
        uk-tooltip='title:{$opt->label};pos:left;'
        data-action='$action'>
        <i class='fa fa-$icon'></i>"
      ."</a>";
  }

  /**
   * Render actions for this item
   */
  public function renderActions() {
    $out = "<span class='rmx-actions'>";
    $out .= $this->renderAction('trash', [
      'label' => $this->_('Mark for deletion'),
    ]);
    $out .= $this->renderAction('untrash', [
      'label' => $this->_('Undo deletion'),
      'icon' => 'undo',
    ]);
    if($this->wire->user->isSuperuser()) {
      $href = $this->wire->pages->get(2)->url."page/edit/?id=$this";
      $out .= $this->renderAction('edit', [
        'label' => $this->_('edit'),
        'icon' => 'edit',
        'href' => $href,
      ]);

      $path = $this->rm()->filePath($this, true);
      $path = $this->wire->sanitizer->pageName($path);
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
   * Get button to add a new page having this template
   */
  public function renderButton($page, $field) {
    /** @var InputfieldButton $b */
    $b = $this->wire('modules')->get('InputfieldButton');
    $b->secondary = true;
    $b->small = true;
    $info = $this->getInfo();
    $b->value = $info->title;
    $b->icon = $info->icon;
    if($info->description) $b->attr('uk-tooltip', $info->description);
    $tpl = $this->getTplName();
    $b->href = "./?id=$page&field=$field&tpl=$tpl";
    $b->addClass('rmx-button');

    // fix issue https://github.com/processwire/processwire-issues/issues/1220
    $b->addHookAfter("render", function($event) {
      $out = substr($event->return, 2);
      $event->return = "<a tabindex='-1'".$out;
    });

    return $b->render();
  }

  /**
   * Get RockMigrations instance
   * @return RockMigrations
   */
  public function rm() {
    return $this->wire->modules->get('RockMigrations');
  }

  /**
   * Set reference to file
   * @return void
   */
  public function setFile($file) {
    $this->file = Paths::normalizeSeparators($file);
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
    $settings = $this->rockfieldValue($this->settingsName());
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
  public function settingsSleep(RockFieldsField $field) {}

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
}
