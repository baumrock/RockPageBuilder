<?php namespace ProcessWire;
use RockMatrix\FieldData;
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
    require_once(__DIR__."/FieldData.php");
  }

  /** FIELDTYPE METHODS */

    public function ___formatValue(Page $page, Field $field, Object $value) {
      return $value->render();
    }

    /**
     * Get blank value for this field
     */
    public function getBlankValue(Page $page, Field $field) {
      return new FieldData($page, $field);
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
    * @return FieldData
    */
    public function sanitizeValue(Page $page, Field $field, $value) {
      return $value;
    }

    public function ___sleepValue(Page $page, Field $field, $value) {
      $sleep = $value->sleepValue();
      db($sleep, "sleep!");
      return $sleep;
    }

    public function ___wakeupValue(Page $page, Field $field, $value) {
      $data = $this->getBlankValue($page, $field);
      foreach(json_decode($value) as $item) {
        $data->add($item->id);
      }
      return $data;
    }

  /** HELPER METHODS */
}
