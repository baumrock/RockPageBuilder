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
abstract class Block extends \ProcessWire\Page {

  const prefix = "rmblock_";
  const tags = "RockMatrix";

  /**
   * References the current file
   * @var string
   **/
  public $file;

  public function info() {
    $info = $this->wire(new WireData()); /** @var WireData $info */
    return $info->setArray([
      'title' => $this->className,
      'name' => get_class($this),
      'icon' => 'cube',
    ]);
  }

  /**
   * This method is called when the block is loaded initially
   * It can be used to attach hooks but is completely optional
   */
  public function init() {
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
   * Get label for matrix item
   * @return string
   */
  public function getIcon() {
    return $this->info()->icon;
  }

  /**
   * Get label for matrix item
   * @return string
   */
  public function getLabel() {
    return $this->wire->sanitizer->truncate($this->get('title|id'), 50);
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
    return $page->get($field->name);
  }

  /**
   * Return the field where this block lives on
   * @return Field
   */
  public function getMatrixField() {
    $meta = explode("-", $this->meta('RockMatrix'));
    if(!is_array($meta)) return false;
    return $this->wire->fields->get($meta[1]);
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
      if($item === $this) return $i;
      $i++;
    }
    return false;
  }

  /**
   * Get notes for matrix item
   * @return string
   */
  public function getNotes() {
    return $this->info()->description;
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
    $class = $this->info()->name;
    return $this->wire->sanitizer->pagename($class);
  }

  /**
   * Get view file for current block
   * @return string|false
   */
  public function getViewFile() {
    $file = $this->rm()->info($this->getMasterBlock()->file);
    return $file->dirname.$file->filename.".view.php";
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

    // prepare the fieldset (item root element)
    $fs->id = "rmx_$this";
    $fs->label = $this->getLabel();
    $fs->icon = $this->getIcon();
    $fs->notes = $this->getNotes();
    $fs->addClass('rmx-item');
    $fs->wrapAttr('data-page', $this->id);
    $fs->collapsed = $this->getCollapsedState();

    // call hookable buildFormMatrix to load fields
    $this->prepareForm($fs);
    $this->buildFormMatrix($fs);

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
      if($b->info()->name === $this->info()->name) return true;
    }
    return false;
  }

  /**
   * Return master module
   * @return RockMatrix
   */
  public function master() {
    return $this->wire->modules->get('RockMatrix');
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
      if($type instanceof FieldtypeRockMatrix) $fields->remove($f);
      // sharing of pages not possible inside matrix
      if($type instanceof FieldtypeRockShare) $fields->remove($f);
    }

    foreach($fields as $field) {
      if($fs->has($field->name)) continue;
      $fs->add($field);
    }
  }

  /**
   * Render this block
   */
  public function render() {
    $view = $this->getViewFile();
    if(is_file($view)) return $this->wire->files->render($view, [
      'page' => $this,
    ], [
      'allowedPaths' => [dirname($view)],
    ]);
    return "Create ".$this->info()->name . "::render() or file $view";
  }

  /**
   * Render a single block action
   * @return string
   */
  public function renderAction($action, $data) {
    $opt = $this->wire(new WireData()); /** @var WireData $opt */
    $opt->setArray($data);
    $icon = $opt->icon ?: $action;
    return
      "<a href='#'
        class='rmx-action rmx-action-$action'
        uk-tooltip='{$opt->label}'
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
    $info = $this->info();
    $b->value = $info->get('title');
    $b->icon = $info->icon;
    if($info->description) $b->attr('uk-tooltip', $info->description);
    $tpl = $this->getTplName();
    $b->href = "./?id=$page&field=$field&tpl=$tpl";

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
   * Block Migrations
   * Not hookable --> call parent::migrate() in derived classes
   */
  public function migrate() {
    // we always create the related template
    $this->log('Migrate '.$this->info()->name);
    $tpl = $this->rm()->createTemplate($this->getTplName());
    $this->rm()->setTemplateData($tpl, [
      'icon' => $this->info()->icon,
      'pageClass' => $this->info()->name,
      'tags' => RockMatrix::tags,
    ]);
  }

  /**
   * Uninstall this block
   * Not hookable --> call parent::uninstall() in derived classes
   */
  public function uninstall() {
    $this->log('Uninstalling ' . $this->info()->name);
    $this->rm()->deleteTemplate($this->getTplName());
  }
}
