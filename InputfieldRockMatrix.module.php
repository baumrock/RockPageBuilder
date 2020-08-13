<?php namespace ProcessWire;
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
    if($this->process != 'ProcessPageEdit') throw new WireException("Not allowed");

    // check if field is set to current field
    $field = $this->wire->fields->get($this->input->get('field', 'string'));
    if($field->name !== $this->name) return;

    // is the block allowed?
    $page = $this->process->getPage();
    $allowed = $this->master->getAllowedBlocks($this, $page);
    $block = $this->master->getBlockByTpl($tpl);
    if(!$block OR !$allowed->has($block)) throw new WireException("Not allowed");

    // create new block
    $p = $this->wire(new Page()); /** @var Page $p */
    $p->template = $block->getTpl();
    $p->parent = $block->getParent();
    $p->title = 'test '.date('d.m.Y H:i:s');
    $p->save();

    // save a reference to the page and the field where this page lives
    // this is necessary for deleting unused pages from time to time
    $p->meta('RockMatrix', $page->id."-".$field->id);
  }

  /**
   * Get collapsed state of item
   */
  public function getCollapsedState() {
    return Inputfield::collapsedNo;
  }

  public function preloadBlockAssets() {
    bd("tbd");
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

      // TODO check if page is editable by current user
      // atm pages will be shown and saved even if they are not editable!

      // TODO this will not work for file uploads - they will be blocked if
      // user has no access to edit the page.

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
   * Get button to add a new page having this template
   */
  public function ___renderButton($block, $page) {
    $field = $this->hasField;

    /** @var InputfieldButton $b */
    $b = $this->wire('modules')->get('InputfieldButton');
    $b->secondary = true;
    $b->small = true;
    if($block) {
      $info = $block->info();
      $b->value = $info->get('title') ?: $block->className;
      $b->icon = $info->icon;
      if($info->description) $b->attr('uk-tooltip', $info->description);
      $tpl = $block->getTplName();
      $b->href = "./?id=$page&field=$field&tpl=$tpl";

      // fix issue https://github.com/processwire/processwire-issues/issues/1220
      $b->addHookAfter("render", function($event) {
        $out = substr($event->return, 2);
        $event->return = "<a tabindex='-1'".$out;
      });
    }
    return $b->render();
  }

  /**
   * Render buttons to add a block to the current field
   * @return string
   */
  public function ___renderButtons() {
    $page = $this->process->getPage();

    $buttons = '<div class="rmx-buttons">';
    foreach($this->master->getAllowedBlocks($this, $page) as $block) {
      $buttons .= $this->renderButton($block, $page);
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
    $this->wire->config->scripts->add($js);

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
    $tx->addClass('rmx-data');
    return $tx->render();
  }

}