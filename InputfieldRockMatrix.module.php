<?php namespace ProcessWire;
/**
 * @author Bernhard Baumrock, 10.08.2020
 * @license COMMERCIAL DO NOT DISTRIBUTE
 * @link https://www.baumrock.com
 */
class InputfieldRockMatrix extends Inputfield {

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
  * Process the Inputfield's input
  * @return $this
  */
  public function ___processInput($input) {
    // get raw value from textarea
    $old = $this->value;
    $new = $old->getNew($input->{$this->name});

    if($new->hasChanged($old)) {
      $this->value = $new;
      $this->trackChange('value'); // trigger change
    }
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

    $buttons = '<div class="rm-buttons">';
    foreach($this->master->getAllowedBlocks($this, $page) as $block) {
      $buttons .= $this->renderButton($block, $page);
    }
    $buttons .= "</div>";

    return "<div class='rm-buttons-container'>"
      ."<small>".__('Add content').":</small><br>$buttons</div>";
  }

  /**
   * Render a single item
   * @return string
   */
  public function ___renderItem($item) {
    $fs = new InputfieldWrapper();
    $fs->add([
      'type' => 'fieldset',
      'label' => $item->getLabel(),
      'children' => [[
        'type' => 'markup',
        'label' => 'foo',
        'value' => 'foo',
      ],[
        'type' => 'markup',
        'label' => 'bar',
        'value' => 'bar',
      ]],
    ]);
    return $fs->render();
  }

  /**
   * Render items of this field
   * @return string
   */
  public function ___renderItems() {
    $out = '<div class="rm-items">';
    foreach($this->value as $item) {
      $out .= $this->renderItem($item);
    }
    $out .= '</div>';
    return $out;
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
    return $tx->render();
  }

}