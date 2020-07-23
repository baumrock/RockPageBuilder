<?php namespace ProcessWire;
/**
 * @author Bernhard Baumrock, 22.07.2020
 * @license Licensed under MIT
 * @link https://www.baumrock.com
 */
class ProcessRockMatrix extends Process {
  const pageName = 'rockmatrix';

  /** @var RockMatrix */
  public $master;

  public static function getModuleInfo() {
    return [
      'title' => 'RockMatrix Process Module',
      'version' => '0.0.1',
      'summary' => 'Module providing API endpoints for ajax requests.',
      'icon' => 'bolt',
      'requires' => ['RockMatrix'],
      'installs' => [],

      // page that you want created to execute this module
      'page' => [
        'name' => self::pageName,
        'parent' => 'setup',
        'title' => 'RockMatrix',
      ],
    ];
  }

  public function init() {
    parent::init(); // always remember to call the parent init
    $this->master = $this->modules->get('RockMatrix');
  }

  public function execute() {
    return "API Endpoints for RockMatrix";
  }


  /**
   * Create new page via Inputfield
   * @return string
   */
  public function executeNew() {
    $fieldPage = $this->input->get('page', 'int');
    $field = $this->input->get('field', 'int');
    $tpl = $this->input->get('tpl', 'int');
    if(!$field) throw new WireException("Invalid field");

    // get pw field and check valid
    $field = $this->fields->get($field);
    if(!$field->type instanceof FieldtypeRockMatrix) throw new WireException("Invalid field");

    // create the new page
    // ajax: return the markup of the new page edit inputfield
    // page edit: redirect to new page edit screen
    return $field->type->newPage($fieldPage, $tpl);
  }
}
