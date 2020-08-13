<?php namespace RockMatrix;

use ProcessWire\FieldtypeRockMatrix;
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

  /** @var string */
  public $file;

  public function info() {
    $info = $this->wire(new WireData()); /** @var WireData $info */
    return $info->setArray([
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
   * @return InputfieldWrapper
   */
  public function ___buildForm($fs) {
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
    $fs->import($fields);
    return $fs;
  }

  /**
   * Build the form when displayed in a matrix field
   * @return InputfieldWrapper
   */
  public function ___buildFormMatrix($fs) {
    return $this->buildForm($fs);
  }

  /**
   * Get label for matrix item
   * @return string
   */
  public function ___getIcon() {
    return $this->info()->icon;
  }

  /**
   * Get label for matrix item
   * @return string
   */
  public function ___getLabel() {
    return $this->get('title|id');
  }

  /**
   * Get notes for matrix item
   * @return string
   */
  public function ___getNotes() {
    return $this->info()->description;
  }

  /**
   * Get parent for this block
   * @return Page
   */
  public function ___getParent($field = null, $page = null) {
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

    // call hookable buildFormMatrix to load fields
    $this->buildFormMatrix($fs);

    // add repeater suffix to all children
    foreach($fs->children() as $f) {
      $f->name .= $wrap->suffix;

      // open wrapper if field has an error
      if(count($f->getErrors())) $fs->collapsed = Inputfield::collapsedNo;

      // non-editable blocks are locked for edits
      if(!$this->editable()) {
        $f->collapsed = $f->collapsed == Inputfield::collapsedNo
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

    return $fs;
  }

  /**
   * Is this block allowed on given page and field?
   * @return bool
   */
  public function isAllowed($field, $page) {
    $allowed = $this->master()->getAllowedBlocks($field, $page);
    return $allowed->has($this->getRmxBlock());
  }

  /**
   * Return master module
   * @return RockMatrix
   */
  public function master() {
    return $this->wire->modules->get('RockMatrix');
  }

  /**
   * Render this block
   */
  public function render() {
    return $this->info()->name . "::render()";
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
