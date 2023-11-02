<?php

namespace ProcessWire;

use RockPageBuilder\Block;
use RockPageBuilder\FieldData;

class ProcessRockPageBuilder extends Process
{
  public static function getModuleInfo()
  {
    return [
      'title' => 'RockPageBuilder Process Module',
      'version' => '1.0.3',
      'summary' => 'Admin Endpoints for RockPageBuilder Module',
      'icon' => 'cubes',
      'requires' => [
        'RockPageBuilder',
      ],
      'installs' => [],

      // all users with the page-edit permission can execute this module
      'permission' => 'page-edit',

      'page' => [
        'name' => 'rockpagebuilder',
        'parent' => 2, // admin page
        'title' => 'RockPageBuilder',
        'status' => Page::statusHidden,
      ],
    ];
  }

  /**
   * Add a rpb item
   */
  public function executeAdd()
  {
    $this->headline('Add Item');
    $this->browserTitle('Add Item');

    $block = $this->wire->pages->get($this->wire->input->get('block', 'int'));
    $above = $this->wire->input->get('above', 'int');

    if (!$block instanceof Block) throw new WireException("Invalid block");
    if (!$block->editable()) throw new WireException("No access");
    if (!$block->getBlockPage()->editable()) throw new WireException("No access");
    $tpl = $this->wire->input->get('tpl', 'templateName');
    $field = $block->getBlockField();
    $f = $field->getInputfield($block);
    $out = '';

    // set template if we only have one allowed block
    $allowed = $this->rpb()->getAllowedBlocks($f, $block->getBlockPage());
    if (count($allowed) === 1) $tpl = $allowed->first()->template;

    // create block if tpl is set
    if ($tpl) {
      $fieldData = $block->getBlockData();
      if ($above) $new = $fieldData->addBefore($tpl, $block);
      else $new = $fieldData->addAfter($tpl, $block);
      $fieldData->save();
      $this->wire->session->redirect($new->editUrl());
    }

    // render buttons of rockpagebuilder field
    $out .= $f->renderButtons($block->getBlockPage(), true);

    // add loading spinner
    $out .= "<div id='rpb-reloading'><span class='loader'></span></div>";
    $this->wire->config->styles->add($this->wire->config->urls->siteModules . "RockPageBuilder/assets/frontend-loggedin.min.css");
    $out .= "<script>$(document).on('click', '.rpb-button', function() {
      $('#rpb-reloading').addClass('show');
    });</script>";

    return $out;
  }

  /**
   * Add first block
   */
  public function executeAddNew()
  {
    $this->headline('Add Item');
    $this->browserTitle('Add Item');
    $page = $this->wire->pages->get($this->wire->input->get('page', 'int'));
    if (!$page->editable()) throw new WireException("No access");
    $field = $this->wire->fields->get($this->wire->input->get('field', 'fieldName'));
    if (!$field) throw new WireException("Invalid field");

    // create block if tpl is set
    if ($tpl = $this->wire->input->get('tpl', 'templateName')) {
      /** @var FieldData $rpb */
      $rpb = $page->getUnformatted($field->name);
      $new = $rpb->add($tpl);
      $rpb->save();
      $this->wire->session->redirect($new->editUrl());
    }

    $f = $field->getInputfield($page);
    return $f->renderButtons($page, true);
  }

  /**
   * Clone given block
   */
  public function executeClone()
  {
    $block = $this->wire->pages->get($this->wire->input->get('block', 'int'));
    if (!$block instanceof Block) throw new WireException("Invalid Block");
    if (!$block->editable()) throw new WireException("Block is not editable");
    if ($block->isTrash()) throw new WireException("Cannot clone blocks that are trashed");
    try {
      $block->clone();
      return $this->success();
    } catch (\Throwable $th) {
      return $this->json($th->getMessage());
    }
  }

  /**
   * Convert given block into a widget
   */
  public function executeConvertToWidget()
  {
    $block = $this->wire->pages->get($this->wire->input->get('block', 'int'));
    if (!$block instanceof Block) throw new WireException("Invalid Block");
    if (!$block->editable()) throw new WireException("Block is not editable");
    if ($block->isTrash()) throw new WireException("Cannot clone blocks that are trashed");
    try {
      $block->toWidget();
      return $this->success();
    } catch (\Throwable $th) {
      return $this->json($th->getMessage());
    }
  }

  /**
   * Add a repeater page
   */
  public function executeRepeaterAdd()
  {
    $block = $this->wire->pages->get($this->wire->input->get('block', 'int'));
    $sort = $this->wire->input->get('sort', 'int');

    if (!$block instanceof RepeaterPage) throw new WireException("Invalid block");
    $forPage = $block->getForPage();
    if (!$forPage->editable()) throw new WireException("No access");

    // get necessary objects
    $field = $block->getForField();
    $items = $forPage->getUnformatted($field->name);

    // create new page
    $new = $items->getNew();
    $new->save();
    $this->wire->pages->sort($new, $sort);

    $this->wire->session->redirect($new->editUrl());
  }

  /**
   * Clone a repeater page
   */
  public function executeRepeaterClone()
  {
    $block = $this->wire->pages->get($this->wire->input->get('block', 'int'));
    if (!$block instanceof RepeaterPage) throw new WireException("Invalid Block");
    if (!$block->editable()) throw new WireException("Block is not editable");
    if ($block->isTrash()) throw new WireException("Cannot clone blocks that are trashed");
    try {
      $this->wire->pages->clone($block);
      return $this->success();
    } catch (\Throwable $th) {
      return $this->json($th->getMessage());
    }
  }

  /**
   * Trash a repeater page
   */
  public function executeRepeaterTrash()
  {
    $block = $this->wire->pages->get($this->wire->input->get('block', 'int'));
    if (!$block instanceof RepeaterPage) throw new WireException("Invalid Block");
    if (!$block->trashable()) throw new WireException("Unable to trash this block");
    $block->trash();
    return $this->json("success");
  }

  public function executeSettingsField()
  {
    $block = $this->wire->input->get('block', 'int');
    /** @var Block $block */
    $block = $this->wire->pages->get($block);
    $file = $block->filePath();
    $this->headline("Add a Settings-Field to block " . $block->className());
    $this->browserTitle("Add a Settings-Field to block " . $block->className());
    $stub = $this->wire->files->render(__DIR__ . "/stubs/SettingsField.txt");
    $alert = '';
    if (!$this->wire->modules->isInstalled("RockFields")) {
      $alert = "<div class='uk-alert uk-alert-danger'>Module RockFields is not installed - please install it!</div>";
    }
    return "$alert Add the following method to the file <strong>$file</strong>:<br><pre>$stub</pre>";
  }

  /**
   * Trash rpb block
   */
  public function executeTrash()
  {
    $block = $this->wire->pages->get($this->wire->input->get('block', 'int'));
    if (!$block instanceof Block) throw new WireException("Invalid Block");
    if (!$block->trashable()) throw new WireException("Unable to trash this block");
    $block->trash();
    return $this->json("success");
  }

  public function rockpagebuilder(): RockPageBuilder
  {
    return $this->wire->modules->get('RockPageBuilder');
  }

  /**
   * @return RockPageBuilder
   */
  public function rpb()
  {
    return $this->wire->modules->get('RockPageBuilder');
  }

  /**
   * Send json message
   * @return string
   */
  public function json($msg, $error = false)
  {
    return json_encode([
      'error' => $error,
      'message' => $msg,
    ]);
  }

  public function success()
  {
    return $this->json('success');
  }
}
