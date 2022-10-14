<?php

namespace RockPageBuilderBlock;

use ProcessWire\FieldtypePage;
use ProcessWire\Inputfield;
use ProcessWire\RockPageBuilder;
use RockPageBuilder\Block;

class Widget extends Block
{

  const prefix = "rockpagebuilderwidget_";

  const field_block = self::prefix . "block";

  public function info()
  {
    return [
      'icon' => 'magic',
      'title' => 'Widget',
    ];
  }

  public function onCreate()
  {
    $this->setInAllLanguages('title', '');
  }

  public function init()
  {
    $this->wire->addHookAfter('InputfieldPage::getSelectablePages', function ($event) {
      if ($event->object->hasField == self::field_block) {
        /** @var RockPageBuilder $rpb */
        $rpb = $this->wire->modules->get('RockPageBuilder');
        $widgets = $rpb->widgets();
        foreach ($widgets as $widget) {
          $widget->_title = $widget->className . ': ' . $widget->getLabel();
        }
        $event->return = $widgets;
      }
    });
  }

  /** ##### frontend ##### */

  /**
   * Get selected block
   * @return Block
   */
  public function block()
  {
    return $this->getFormatted(self::field_block);
  }

  /** ##### backend ##### */

  public function getLabel()
  {
    $label = parent::getLabel();
    if (!$block = $this->block()) return $label;
    return "$label ({$block->getLabel()})";
  }

  public function migrate()
  {
    parent::migrate();
    $rm = $this->rm();
    $url = $this->wire->pages->get(1)->editUrl() . "&field=" . RockPageBuilder::field_widgets;
    $noteLabel = $this->_('Manage widgets');
    $rm->migrate([
      'fields' => [
        self::field_block => [
          'type' => 'page',
          'label' => 'Widget',
          'derefAsPage' => FieldtypePage::derefAsPageOrFalse,
          'inputfield' => 'InputfieldSelect',
          'findPagesSelector' => '',
          'labelFieldName' => '_title',
          'notes' => "[$noteLabel]($url)",
          'tags' => RockPageBuilder::tags,
        ],
      ],
      'templates' => [
        $this->getTplName() => [
          'fields' => [
            'title' => [
              'required' => false,
              'collapsed' => Inputfield::collapsedHidden,
            ],
            self::field_block,
          ],
        ],
      ],
    ]);
  }

  public function render()
  {
    $block = $this->block();
    if (!$block) return;
    $block->_widget = $this;
    $block->widgetClass = 'rpb-widget';
    return $block->render();
  }

  /**
   * Set reference to widget
   * @return void
   */
  public function setReference($block)
  {
    $this->set(self::field_block, $block);
  }
}
