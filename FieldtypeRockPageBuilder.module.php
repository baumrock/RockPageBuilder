<?php

namespace ProcessWire;

use RockPageBuilder\FieldData;

/**
 * @author Bernhard Baumrock, 10.08.2020
 * @license COMMERCIAL DO NOT DISTRIBUTE
 * @link https://www.baumrock.com
 */
class FieldtypeRockPageBuilder extends FieldtypeTextarea
{

  public static function getModuleInfo()
  {
    return [
      'title' => 'RockPageBuilder',
      'version' => json_decode(file_get_contents(__DIR__ . "/package.json"))->version,
      'summary' => 'Your module description',
      'icon' => 'cubes',
      'requires' => ['RockPageBuilder'],
    ];
  }

  public function init()
  {
    parent::init();
    require_once(__DIR__ . "/FieldData.php");
  }

  /** FIELDTYPE METHODS */

  public function ___formatValue(Page $page, Field $field, $value)
  {
    // return field data object
    // we do not return the rendered value as this breaks kaumberg
    // frontpage (shows item::render()item::render()item::render())
    return $value;
  }

  /**
   * Get blank value for this field
   */
  public function getBlankValue(Page $page, Field $field)
  {
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
  public function getInputfield(Page $page, Field $field)
  {
    /** @var InputfieldRockPageBuilder $f */
    $f = $this->wire('modules')->get('InputfieldRockPageBuilder');
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
  public function sanitizeValue(Page $page, Field $field, $value)
  {
    return $value;
  }

  public function ___sleepValue(Page $page, Field $field, $value)
  {
    if (!$value) $value = $this->getBlankValue($page, $field);
    $sleep = $value->sleepValue();
    return $sleep;
  }

  public function ___wakeupValue(Page $page, Field $field, $value)
  {
    $data = $this->getBlankValue($page, $field);
    return $data->wakeup($value);
  }

  /** HELPER METHODS */
}
