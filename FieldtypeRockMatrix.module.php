<?php namespace ProcessWire;
/**
 * @author Bernhard Baumrock, 10.08.2020
 * @license COMMERCIAL DO NOT DISTRIBUTE
 * @link https://www.baumrock.com
 */
class FieldtypeRockMatrix extends FieldtypeTextarea {

  public static function getModuleInfo() {
    return [
      'title' => 'RockMatrix',
      'version' => '0.0.1',
      'summary' => 'Your module description',
      'icon' => 'cubes',
      'requires' => ['RockMatrix'],
      'installs' => [],
    ];
  }

  public function init() {
    parent::init();
  }

  /** FIELDTYPE METHODS */

    /**
     * Get the Inputfield module that provides input for Field
     *
     * @param Page $page
     * @param Field $field
     * @return Inputfield
     *
     */
    public function getInputfield(Page $page, Field $field) {
      /** @var InputfieldRockMatrix $f */
      $f = $this->wire('modules')->get('InputfieldRockMatrix');
      return $f;
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

  /** HELPER METHODS */
}
