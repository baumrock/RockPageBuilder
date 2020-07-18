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

  /** HELPER METHODS */
}
