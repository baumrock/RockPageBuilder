<?php namespace ProcessWire;
/**
 * @author Bernhard Baumrock, 22.07.2020
 * @license Licensed under MIT
 * @link https://www.baumrock.com
 */
class ProcessRockMatrix extends Process {
  const pageName = 'rockmatrix';
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
  }

  public function execute() {
    return "API Endpoints for RockMatrix";
  }


  /**
   * Create new page via Inputfield
   * @return string
   */
  public function executeNew() {
    die('<div>new page</div>');
  }
}
