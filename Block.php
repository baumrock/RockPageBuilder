<?php namespace RockMatrix;

use ProcessWire\FieldtypeRockMatrix;
use ProcessWire\Paths;
use ProcessWire\RockMatrix;
use \ProcessWire\WireData;
use \ProcessWire\RockMigrations;
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
   * Is this block allowed on given page and field?
   * @return bool
   */
  public function isAllowed($field, $page) {
    $allowed = $this->master()->getAllowedBlocks($field, $page);
    return $allowed->has($this->getRmBlock());
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
