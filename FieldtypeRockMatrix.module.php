<?php namespace ProcessWire;
/**
 * @author Bernhard Baumrock, 18.07.2020
 * @license Licensed under MIT
 * @link https://www.baumrock.com
 */
class FieldtypeRockMatrix extends FieldtypeTextarea {

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
  }

  /** FIELDTYPE METHODS */

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
    public function newPage($fieldPage, $tpl) {
      $tpl = $this->templates->get((string)$tpl);

      // TODO sanitization and access checks
      if(!$tpl) throw new WireException("Invalid Template");


      // get the template class
      // this ensures that if the page is a special page (like a report)
      // the correct constructor or saveReady hooks are fired
      // eg the Report gets the correct page name and title on creation
      $class = $tpl->pageClass ?: "Page";

      // create new page
      $page = $this->wire(new $class()); /** @var Page $page */
      $page->template = $tpl;
      $page->parent = $fieldPage;
      $page->save();

      // ajax: return markup
      if($this->config->ajax) return "ajax! $page";
      else $this->session->redirect($page->editUrl);
    }

  /** HELPER METHODS */
}
