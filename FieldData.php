<?php

namespace RockPageBuilder;

use ProcessWire\Notice;
use ProcessWire\PageArray;
use ProcessWire\RockFrontend;
use ProcessWire\WireException;
use ProcessWire\RockPageBuilder;
use ProcessWire\RockMigrations;

use function ProcessWire\wireClassName;

class FieldData extends PageArray
{

  public $page;
  public $field;

  public function __construct($page, $field)
  {
    $this->page = $page;
    $this->field = $field;
    parent::__construct();
  }

  /** Field data manipulation API */

  /**
   * Add item to this field data array
   * @return Block
   */
  public function add($item, $data = [])
  {
    if (is_string($item)) {
      // template provided
      $item = $this->createBlock($item);
    } elseif (is_array($item)) {
      // item is a plain php array
      // this means we create a new block and add it
      $item = $this->createBlock($item, $data);
    } elseif ($item instanceof PageArray) {
      foreach ($item as $i) $this->add($i);
      return $this;
    }

    /** @var RockPageBuilder */
    $mx = $this->wire->modules->get('RockPageBuilder');

    // make sure item is a page
    $_item = $item;
    $item = $mx->getBlockPage($item);
    if (!$item) throw new WireException("Invalid item $_item - did you call parent::migrate() in your block?");

    // check if item is allowed!
    if (!$item->isAllowed($this->field, $this->page)) {
      $this->error("Block [$item]($item->editUrl) not allowed for field {$this->field} on page {$this->page}", Notice::allowMarkdown);
      return false;
    }

    // add the item to the array
    parent::add($item);

    return $item;
  }

  /**
   * Add a new block after another
   * @return Block
   */
  public function addAfter($new, $existing, $data = [])
  {
    $new = $this->createBlock($new, $data);
    parent::insertAfter($new, $existing);
    return $new;
  }

  /**
   * Add a new block before another
   * @return Block
   */
  public function addBefore($new, $existing, $data = [])
  {
    $new = $this->createBlock($new, $data);
    parent::insertBefore($new, $existing);
    return $new;
  }

  /**
   * Create a new block
   * @return Block
   */
  public function createBlock($tpl, $data = [])
  {
    // get block and check if it is allowed
    $block = $this->master()->getBlockByTpl($tpl);
    if (!$block) throw new WireException("Invalid tpl $tpl");
    if (!$block->isAllowed($this->field, $this->page)) {
      throw new WireException("$tpl not allowed on page $this->page and field $this->field");
    }

    // create new block
    $class = $block->getInfo()->name;
    $b = $this->wire(new $class());
    /** @var Block $b */
    $b->template = $block->getTpl();
    $b->parent = $block->getParent($this->field, $this->page);
    $b->title = "$class @ " . date('Y-m-d H:i:s');
    $b->save();

    // set block data
    foreach ($data as $k => $v) $b->setAndSave($k, $v);

    // save a reference to the page and the field where this page lives
    // this is necessary for deleting unused pages from time to time
    $b->setBlockReference($this->page, $this->field);

    return $b;
  }

  /**
   * Reset this field and delete all blocks
   * @return self
   */
  public function reset()
  {
    foreach ($this as $block) $block->delete();
    return $this;
  }

  /**
   * Save this field on current page
   * @return self
   */
  public function save()
  {
    // make sure output formatting is off
    // setting $this->page->of(false) is not enough and throws an exception?!
    $this->wire->pages->of(false);
    $this->page->setAndSave($this->field->name, $this);
    return $this;
  }

  /** END Field data manipulation API */

  /**
   * Get a blank copy
   * @return FieldData
   */
  public function getNew($data = null)
  {
    $new = $this->field->type->getBlankValue($this->page, $this->field);
    if ($data) $new->wakeup($data);
    return $new;
  }

  /**
   * Get property of object
   * @return mixed
   */
  public function getProp($obj, $prop)
  {
    return property_exists($obj, $prop) ? $obj->$prop : null;
  }

  /**
   * Usage:
   * $blocks = $page->getFormatted('blocks');
   * if($blocks->has('Hero')) ...
   */
  public function hasBlock($type): bool
  {
    foreach ($this as $block) if (wireClassName($block) == $type) return true;
    return false;
  }

  /**
   * Has this object changed compared to another one?
   * This method is used for triggering the trackChange event when a page
   * having a MX field is saved and input is processed.
   * @return bool
   */
  public function hasChanged($other)
  {
    $new = $this->sleepValue();
    $old = $other->sleepValue();
    return $new !== $old;
  }

  /**
   * Get master module instance
   * @return RockPageBuilder
   */
  public function master()
  {
    return $this->wire->modules->get('RockPageBuilder');
  }

  /**
   * Render all items of this rpb field
   * @return string
   */
  public function render($renderEmpty = false)
  {
    if ($this->wire->user->isSuperuser()) return $this->renderCatch($renderEmpty);
    try {
      return $this->renderCatch($renderEmpty);
    } catch (\Throwable $th) {
      try {
        /** @var RockMigrations $rm */
        $rm = $this->wire->modules->get('RockMigrations');
        $rm->mailToSuperuser($th->getMessage());
      } catch (\Throwable) {
        $this->log($th->getMessage());
      }
    }
  }

  /**
   * Render all blocks and catch errors
   * @return string
   */
  private function renderCatch($renderEmpty)
  {
    $out = '';
    $typeIndex = 0;
    foreach ($this as $i => $block) {
      /** @var Block $block */
      /** @var Block $next */
      /** @var Block $prev */
      $next = $this->eq($i + 1);
      $prev = $this->eq($i - 1);

      // is this block last of same type?
      $block->lastOfType = true;
      if ($next and $next->getTpl() == $block->getTpl()) {
        $block->lastOfType = false;
      }

      // set type index of this block
      // this is helpful for switching left/right option based on index
      // eg even = left, odd = right aligned block
      if (!$prev or $prev->getTpl() != $block->getTpl()) $typeIndex = 0;
      $block->typeIndex = $typeIndex++;

      try {
        // try to render the block and add some magic
        // add the image overlay for rapid development
        $out .= $this->addOverlay($block);
      } catch (\Throwable $th) {
        $out .= $th->getMessage() . " in block #$block ({$block->template})";
      }
    }
    if (!$out and $renderEmpty) return $this->renderEmpty();

    // if the addWrapper config settings is not set we return the clean markup
    if (!$this->master()->addWrapper) return $out;

    // // create frontend editing wrapper markup
    // // this feature is not working properly at the moment
    // // the uikit sortable feature does not work on flex elements for example
    // $editInfo = '';
    // if($this->page->editable()) {
    //   $editInfo = "data-page='{$this->page}' data-field='{$this->field}'";
    // }
    // return "<div class='rpb-sortable'$editInfo>$out</div>";
  }

  /**
   * Render block and add overlay markup
   */
  public function addOverlay($block): string
  {
    $html = $block->renderBlock() ?: '';
    return $html;
    if (strpos($html, '<div class="rpb-overlay"') !== false) return $html;
    return $block->overlay() . $html;
  }

  /**
   * Render empty rpb field
   * @return string
   */
  public function ___renderEmpty()
  {
    if (!$this->wire->page->editable()) return;
    if ($this->wire->config->rpb_noEmptyButton) return;
    if (!$this->wire->modules->isInstalled('RockFrontend')) return;
    /** @var RockFrontend $rf */
    $rf = $this->wire->modules->get('RockFrontend');
    $rf->hasAlfred = true; // adds a fake <edit> tag on page::render
    $href = $this->master()->rpbUrl("/add-new/?page={$this->page}&field=" . $this->field);
    return $rf->iconLink("plus", $href, [
      'title' => $this->_('Add new content'),
      'wrapClass' => 'rpb-first-block',
      'style' => 'margin: clamp(30px, 30px + 170 * ((100vw - 360px) / (1440 - 360)), 200px) 0; text-align: center;'
    ]);
  }

  /**
   * Get sleep value of this array
   * This does NOT check if items are allowed etc.
   * @return string
   */
  public function sleepValue()
  {
    $sleep = [];
    foreach ($this as $item) {
      $sleep[] = (object)[
        'id' => $item->id,
      ];
    }
    return json_encode($sleep);
  }

  /**
   * Wakeup from given data
   * @return FieldData
   */
  public function wakeup($data)
  {
    /** @var RockPageBuilder */
    $mx = $this->wire->modules->get('RockPageBuilder');
    $json = json_decode($data);
    if ($json === null) throw new WireException("Invalid json");

    // loop items
    foreach ($json as $item) {
      $block = $mx->getBlockPage($item->id);
      if (!$block) continue;
      if ($block->isTrash()) continue;

      // set the changed property of this block
      // this value us used on processInput to trigger page save of the item
      $block->_mxchanged = $this->getProp($item, 'changed');
      $block->_mxtrash = $this->getProp($item, 'trash');

      $this->add($block);
    }
    return $this;
  }

  public function __debugInfo()
  {
    return array_merge([
      'page' => $this->page,
      'field' => $this->field,
    ], parent::__debugInfo());
  }
}
