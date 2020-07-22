<?php namespace ProcessWire;
/**
 * @author Bernhard Baumrock, 18.07.2020
 * @license Licensed under MIT
 * @link https://www.baumrock.com
 */
class RockMatrix extends WireData implements Module, ConfigurableModule {

  public static function getModuleInfo() {
    return [
      'title' => 'RockMatrix',
      'version' => '0.0.1',
      'summary' => 'Master module for RockMatrix Fieldtype + Inputfield',
      'autoload' => true,
      'singular' => true,
      'icon' => 'bolt',
      'requires' => [],
      'installs' => [
        'FieldtypeRockMatrix',
        'InputfieldRockMatrix',
        'InputfieldRockPageEdit',
        'ProcessRockMatrix',
      ],
    ];
  }

  public function init() {
  }

  /**
  * Config inputfields
  * @param InputfieldWrapper $inputfields
  */
  public function getModuleConfigInputfields($inputfields) {
    return $inputfields;
  }
}
