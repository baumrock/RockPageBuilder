<?php namespace ProcessWire;

use RockMatrix\Block;

class ProcessRockMatrix extends Process {
  public static function getModuleInfo() {
    return [
      'title' => 'RockMatrix Process Module',
      'version' => '1.0.1',
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
      $above = $this->wire->input->get('above', 'int');
      if($above) $new = $fieldData->addBefore($tpl, $block);
      else $new = $fieldData->addAfter($tpl, $block);
      $fieldData->save();
      $this->wire->session->redirect($new->editUrl());
    }

    // the the rockmatrix field
    $field = $block->getMatrixField();
    $f = $field->getInputfield($block);
    $out .= $f->renderButtons($block->getMatrixPage(), true);

    return $out;
  }

  /**
   * Trash matrix block
   */
  public function executeTrash() {
    $block = $this->wire->pages->get($this->wire->input->get('block', 'int'));
    if(!$block instanceof Block) throw new WireException("Invalid Block");
    if(!$block->trashable()) throw new WireException("Unable to trash this block");
    $block->trash();
    return $this->json("success");
  }

  /**
   * @return RockMatrix
   */
  public function matrix() {
    return $this->wire->modules->get('RockMatrix');
  }

  /**
   * Send json message
   * @return string
   */
  public function json($msg, $error = false) {
    return json_encode([
      'error' => $error,
      'message' => $msg,
    ]);
  }

}
