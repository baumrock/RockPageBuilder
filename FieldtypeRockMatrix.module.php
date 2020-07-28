<?php namespace ProcessWire;
/**
 * @author Bernhard Baumrock, 18.07.2020
 * @license Licensed under MIT
 * @link https://www.baumrock.com
 */
require_once("RockMatrixPageArray.php");
class FieldtypeRockMatrix extends Fieldtype {

  /** @var RockMatrix */
  public $master;

  public static function getModuleInfo() {
    return [
      'title' => 'RockMatrix',
      'version' => '0.0.1',
      'summary' => 'RockMatrix Fieldtype',
      'icon' => 'bolt',
      'requires' => ['RockMatrix'],
      'installs' => [],
    ];
  }

  public function init() {
    parent::init();
    $this->master = $this->modules->get('RockMatrix');
  }

  /** FIELDTYPE METHODS */

    public function getBlankValue(Page $page, Field $field) {
      if($page && $field) {}
      return new RockMatrixPageArray($page, $field);
    }

    public function ___wakeupValue(Page $page, Field $field, $value) {
      $pages = $this->getBlankValue($page, $field);
      $pages->wakeup($value);
      return $pages;
    }

    public function ___sleepValue(Page $page, Field $field, $value) {
      return $value->sleep();
    }

    /**
    * Sanitize value for storage
    *
    * @param Page $page
    * @param Field $field
    * @param string $value
    * @return string
    */
    public function sanitizeValue(Page $page, Field $field, $value) {
      return $value;
    }

    /**
     * Get the Inputfield module that provides input for Field
     *
     * @param Page $page
     * @param Field $field
     * @return Inputfield
     *
     */
    public function getInputfield(Page $page, Field $field) {
      $inputfield = $this->modules->get('InputfieldRockMatrix');
      return $inputfield;
    }

  /** PUBLIC API METHODS */

    /**
     * Create new page for given field
     * @return string
     */
    public function newPage($fieldPage, $field, $blockname) {
      if(!$fieldPage) throw new WireException("Invalid page");
      if(!$field) throw new WireException("Invalid field");
      if(!$blockname) throw new WireException("Invalid block");

      // first we check the block
      $block = $this->master->getBlock($blockname);
      if(!$block) throw new WireException("Invalid block");
      if(!$block->isAllowed($field, $fieldPage)) {
        throw new WireException("Block not allowed");
      }
      $tpl = $block->info()->tpl;
      $tpl = $this->wire->templates->get($tpl);
      if(!$tpl OR !$tpl->id) {
        throw new WireException("Invalid template for block $blockname");
      }

      // get the template class
      // this ensures that if the page is a special page (like a report)
      // the correct constructor or saveReady hooks are fired
      // eg the Report gets the correct page name and title on creation
      $class = $tpl->pageClass ?: "Page";

      // create new page
      $page = $this->wire(new $class()); /** @var Page $page */
      $page->template = $tpl;
      $page->parent = $fieldPage; // TODO set correct parent
      $page->save();

      // save reference to the fieldPage in metadata
      $page->meta("RockMatrixPage", (int)(string)$fieldPage);

      // ajax: return markup
      if($this->config->ajax) {
        return json_encode([
          'page' => $page->id,
        ]);
      }
      else $this->session->redirect($page->editUrl);
    }

  /** HELPER METHODS */
}
