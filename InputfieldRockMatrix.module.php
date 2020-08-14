<?php namespace ProcessWire;
use RockMatrix\Block;
/**
 * @author Bernhard Baumrock, 10.08.2020
 * @license COMMERCIAL DO NOT DISTRIBUTE
 * @link https://www.baumrock.com
 */
class InputfieldRockMatrix extends InputfieldRepeater {

  /** @var RockMatrix */
  public $master;

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
    $this->master = $this->wire->modules->get('RockMatrix');
  }

  /**
   * Create a new block
   */
  public function createBlock() {
    if(!$tpl = $this->input->get('tpl', 'string')) return;
    if($this->process != 'ProcessPageEdit') return;

    // check if field is set to current field
    $field = $this->wire->fields->get($this->input->get('field', 'string'));
    if($field->name !== $this->name) return;

    // is the block allowed?
    $page = $this->process->getPage();
    $block = $this->master->getBlockByTpl($tpl);
    if(!$block->isAllowed($field, $page)) throw new WireException("Not allowed");

    // create new block
    $class = $block->info()->name;
    $b = $this->wire(new $class()); /** @var Block $b */
    $b->template = $block->getTpl();
    $b->parent = $block->getParent();
    $b->title = "$class @ ".date('Y-m-d H:i:s');
    $b->save();

    // save a reference to the page and the field where this page lives
    // this is necessary for deleting unused pages from time to time
    $b->meta('RockMatrix', $page->id."-".$field->id);

    // render inputfield for this block
    $wrap = $b->getWrapper();
    die(json_encode([
      'markup' => $wrap->parent->render(),
    ]));
  }

  /**
   * Preload assets of all allowed blocks' inputfields
   * This makes sure that all the assets for eg file fields are
   * ready when a new block is added and initialized via JS
   * @return void
   */
  public function preloadBlockAssets() {
    $page = $this->process->getPage();
    $blocks = $this->master->getAllowedBlocks($this, $page);
    $nullPage = new NullPage();
    foreach($blocks as $block) {
      if(!$block instanceof Block) continue;
      if(!$tpl = $block->getTpl()) continue;
      foreach($tpl->fields as $field) {
        $field->getInputfield($nullPage)->renderReady();
      }
    }
  }

  /**
  * Process the Inputfield's input
  * @return $this
  */
  public function ___processInput($input) {
    // get raw value from textarea
    $old = $this->value;
    $new = $old->getNew($input->{$this->name});

    // process all repeater items
    $changes = $this->processItems($new, $input);

    if($new->hasChanged($old) OR $changes) {
      $this->value = $new;
      $this->trackChange('value'); // trigger change
    }

    return $this;
  }

  /**
   * Process all repeater items
   * @return int
   */
  public function ___processItems($new, $input) {
    $changes = 0;

    // this is a stripped down version of InputfieldRepeater::processInput
    // see the original version for all options and details
    foreach($new as $item) {
      /** @var Page $item */

      // we only process items that are marked as changed in raw textarea data
      if(!$item->_mxchanged) continue;

      // skip pages that are not editable
      if(!$item->editable()) {
        $this->warning("Skipped block $item - not editable!");
        continue;
      }

      // check item trashed
      if($item->_mxtrash) {
        $item->trash();
        $new->remove($item);
        continue;
      }

      // get the wrapper for this item and process input
      $wrapper = $item->getWrapper();
      $wrapper->resetTrackChanges(true);
      $wrapper->getErrors(true); // clear out any errors
      $wrapper->processInput($input);

      // save all field values to the page
      $numErrors = count($wrapper->getErrors());
      $this->formToPage($wrapper, $item);
      if(!$numErrors) {
        $item->save();
        $changes++;
      }
    }

    return $changes;
  }

  /**
  * Render the Inputfield
  * @return string
  */
  public function ___render() {
    $this->createBlock();
    $out = '';
    $out .= $this->renderItems();
    $out .= $this->renderButtons();
    $out .= $this->renderTextarea();
    $out .= $this->renderInitTag();
    return $out;
  }

  /**
   * Render buttons to add a block to the current field
   * @return string
   */
  public function ___renderButtons() {
    $page = $this->process->getPage();
    $blocks = $this->master->getAllowedBlocks($this, $page);

    if(!count($blocks)) {
      return $this->files->render(__DIR__."/_setupinfo.php", [
        'name'=>$this->name,
      ]);
    }

    $buttons = '<div class="rmx-buttons">';
    foreach($blocks as $block) {
      $buttons .= $block->renderButton($page, $this->hasField);
    }
    $buttons .= "</div>";

    return "<div class='rmx-buttons-container'>"
      ."<small>".__('Add content').":</small><br>$buttons</div>";
  }

  /**
   * Render init tag
   * @return string
   */
  public function renderInitTag() {
    $out = "<script>$('#wrap_Inputfield_{$this->name}').trigger('init');</script>";
    return $out;
  }

  /**
   * Render a single item
   * @return string
   */
  public function ___renderItem($item) {
    $fs = $item->getWrapper();
    return $fs->parent->render();
  }

  /**
   * Render items of this field
   * @return string
   */
  public function ___renderItems() {
    $out = '<div class="rmx-items">';
    foreach($this->value as $item) {
      $out .= $this->renderItem($item);
    }
    $out .= '</div>';
    return $out;
  }

  /**
   * Inputfield is ready to render
   */
  public function renderReady() {
    // make sure that repeater is installed
    $this->modules->get('InputfieldRepeater');

    $file = $this->className.".js";
    $path = $this->config->paths($this).$file;
    $m = "?m=".filemtime($path);
    $this->config->scripts->add($this->config->urls($this).$file.$m);

    $url = $this->config->urls($this);
    $file = $url.$this->className.".less";
    $less = $this->modules->get('RockLESS'); /** @var RockLESS $less */
    if($less) $less->addToConfig($file);
    else $this->config->styles->add("$file.css");

    // load vex
    $this->wire('modules')->get('JqueryUI')->use('vex');

    // load JS
    $js = $this->wire->config->urls($this)."RockMatrixItem.js";
    $m = "?m=".filemtime($this->wire->config->paths($this)."RockMatrixItem.js");
    $this->wire->config->scripts->add($js.$m);

    $this->preloadBlockAssets();
  }

  /**
   * Render textarea for this inputfield
   * @return string
   */
  public function ___renderTextarea() {
    /** @var InputfieldTextarea */
    $tx = $this->wire->modules->get('InputfieldTextarea');
    $tx->name = $this->name;
    $tx->value = $this->value->sleepValue();
    $tx->addClass('rmx-data uk-hidden');
    return $tx->render();
  }

}
