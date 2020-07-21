<?php namespace ProcessWire;
/**
 * @author Bernhard Baumrock, 18.07.2020
 * @license Licensed under MIT
 * @link https://www.baumrock.com
 */
class InputfieldRockMatrix extends InputfieldTextarea {

  public static function getModuleInfo() {
    return [
      'title' => 'InputfieldRockMatrix',
      'version' => '0.0.1',
      'summary' => 'RockMatrix Inputfield',
      'autoload' => false,
      'singular' => false,
      'icon' => 'bolt',
      'requires' => ['RockMatrix'],
      'installs' => [],
    ];
  }

  /**
   * Render this inputfield
   */
  public function ___render() {
    $out = '';
    $page = $this->pages->get($this->input->get('id', 'int'));
    foreach($page->children as $child) {
      $out .= "<a href='{$child->editUrl}'>{$child->title}</a><br>";
    }
    return $out;
  }

  /**
  * Config inputfields
  * @param InputfieldWrapper $inputfields
  */
  public function getModuleConfigInputfields($inputfields) {
    return $inputfields;
  }
}
