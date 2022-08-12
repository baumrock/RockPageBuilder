<?php namespace ProcessWire;

use RockMatrix\Block;

class ProcessRockMatrix extends Process {
  public static function getModuleInfo() {
    return [
      'title' => 'RockMatrix Process Module',
      'version' => '1.0.3',
      'summary' => 'Admin Endpoints for RockMatrix Module',
      'icon' => 'cubes',
      'requires' => [
        'RockMatrix',
      ],
      'installs' => [],

      // all users with the page-edit permission can execute this module
      'permission' => 'page-edit',

      'page' => [
        'name' => 'rockmatrix',
        'parent' => 2, // admin page
        'title' => 'RockMatrix',
        'status' => Page::statusHidden,
      ],
    ];
  }

  /**
   * Add a matrix item
   */
  public function executeAdd() {
    $this->headline('Add Item');
    $this->browserTitle('Add Item');

    $block = $this->wire->pages->get($this->wire->input->get('block', 'int'));
    $above = $this->wire->input->get('above', 'int');

    if(!$block instanceof Block) throw new WireException("Invalid block");
    if(!$block->editable()) throw new WireException("No access");
    if(!$block->getMatrixPage()->editable()) throw new WireException("No access");
    $tpl = $this->wire->input->get('tpl', 'templateName');
    $field = $block->getMatrixField();
    $f = $field->getInputfield($block);
    $out = '';

    // set template if we only have one allowed block
    $allowed = $this->matrix()->getAllowedBlocks($f, $block->getMatrixPage());
    if(count($allowed)===1) $tpl = $allowed->first()->template;

    // create block if tpl is set
    if($tpl) {
      $fieldData = $block->getMatrixData();
      if($above) $new = $fieldData->addBefore($tpl, $block);
      else $new = $fieldData->addAfter($tpl, $block);
      $fieldData->save();
      $this->wire->session->redirect($new->editUrl());
    }

    // render buttons of rockmatrix field
    $out .= $f->renderButtons($block->getMatrixPage(), true);

    return $out;
  }

  /**
   * Add first block
   */
  public function executeAddNew() {
    $this->headline('Add Item');
    $this->browserTitle('Add Item');
    $page = $this->wire->pages->get($this->wire->input->get('page', 'int'));
    if(!$page->editable()) throw new WireException("No access");
    $field = $this->wire->fields->get($this->wire->input->get('field', 'fieldName'));
    if(!$field) throw new WireException("Invalid field");

    // create block if tpl is set
    if($tpl = $this->wire->input->get('tpl', 'templateName')) {
      $matrix = $page->getUnformatted($field->name);
      $new = $matrix->add($tpl);
      $matrix->save();
      $this->wire->session->redirect($new->editUrl());
    }

    $f = $field->getInputfield($page);
    return $f->renderButtons($page, true);
  }

  /**
   * Clone give block
   */
  public function executeClone() {
    $block = $this->wire->pages->get($this->wire->input->get('block', 'int'));
    if(!$block instanceof Block) throw new WireException("Invalid Block");
    if(!$block->editable()) throw new WireException("Block is not editable");
    if($block->isTrash()) throw new WireException("Cannot clone blocks that are trashed");
    try {
      $block->clone();
      return $this->success();
    } catch (\Throwable $th) {
      return $this->json($th->getMessage());
    }
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

  public function success() {
    return $this->json('success');
  }

}
