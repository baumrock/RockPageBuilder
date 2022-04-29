<?php namespace ProcessWire;

use RockMatrix\Block;

class ProcessRockMatrix extends Process {
  public static function getModuleInfo() {
    return [
      'title' => 'RockMatrix Process Module',
      'version' => '1.0.0',
      'summary' => 'Admin Endpoints for RockMatrix Module',
      'icon' => 'cubes',
      'requires' => [
        'RockMatrix',
      ],
      'installs' => [],
      'page' => [
        'name' => 'rockmatrix',
        'parent' => 2, // admin page
        'title' => 'RockMatrix',
        'status' => Page::statusHidden,
      ],
    ];
  }

  public function init() {
    parent::init(); // always remember to call the parent init
  }

  public function execute() {
  }

  /**
   * Add a matrix item
   */
  public function executeAdd() {
    $this->headline('Add Item');
    $this->browserTitle('Add Item');
    $block = $this->wire->pages->get($this->wire->input->get('block', 'int'));
    if(!$block->editable()) throw new WireException("No access");
    if(!$block instanceof Block) throw new WireException("Invalid block");
    $out = '';

    // create block if tpl is set
    if($tpl = $this->wire->input->get('tpl', 'templateName')) {
      $fieldData = $block->getMatrixData();
      $fieldData->create(['tpl' => $tpl])->save();
      $out .= "<script>parent.document.location.reload();</script>";
    }

    // the the rockmatrix field
    $field = $block->getMatrixField();
    $f = $field->getInputfield($block);
    $out .= $f->renderButtons($block->getMatrixPage(), true);

    return $out;
  }

  /**
   * @return RockMatrix
   */
  public function matrix() {
    return $this->wire->modules->get('RockMatrix');
  }

}
