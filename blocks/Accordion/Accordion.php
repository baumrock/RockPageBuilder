<?php

namespace RockPageBuilderBlock;

use ProcessWire\FieldtypeTextarea;
use ProcessWire\Inputfield;
use ProcessWire\RockPageBuilder;
use RockPageBuilder\Block;

class Accordion extends Block
{

  const prefix = "rpb_accordion_";

  const field_items = self::prefix . "items";
  const field_body = self::prefix . "body";

  public function info()
  {
    return [
      'title' => 'Accordion',
      'icon' => 'align-justify',
    ];
  }

  public function onCreate(): void
  {
    parent::onCreate();
    $this->set(RockPageBuilder::field_eyebrow, "Lorem Ipsum");
    $this->set("title", "Nulla neque dolor sagittis");
  }

  /** frontend */

  public function bgStyle(): string
  {
    $bg = $this->settings('bg');
    if ($bg == "primary") return "uk-background-primary section-padding";
    elseif ($bg == "secondary") return "uk-background-secondary section-padding";
    elseif ($bg == "muted") return "uk-background-muted section-padding";
    return "";
  }

  public function items()
  {
    return $this->getUnformatted(self::field_items);
  }

  /** backend */

  /**
   * (Optional) Migrations for this block
   *
   * You can either manage fields of your block via GUI or via migrations.
   * The huge benefit of using migrations is that you can deploy everything
   * easily. Another huge benefit is that you can reuse blocks across projects!
   *
   * Imagine you build a slider block that needs an images field with custom
   * settings. If you add that field via code you can simply copy and paste your
   * block to any of your projects and start using it right away!
   *
   * If you still prefer to use the GUI for managing fields you can remove this
   * method completely.
   */
  public function migrate()
  {
    $rm = $this->rockmigrations();

    $rm->migrate([
      'fields' => [
        self::field_body => [
          'type' => $this->wire->languages ? 'textareaLanguage' : 'textarea',
          'inputfieldClass' => 'InputfieldTinyMCE',
          'contentType' => FieldtypeTextarea::contentTypeHTML,
          'label' => 'Content',
          'rows' => 5,
          'icon' => 'align-left',
          'inlineMode' => true,
          // 'rpb-nolabel' => true, // hide label in backend
          'settingsFile' => '/site/modules/RockMigrations/TinyMCE/simple.json',
        ],
        self::field_items => [
          'label' => 'Items',
          'icon' => 'cubes',
          'type' => 'FieldtypeRepeater',
          'fields' => [
            'title' => [
              'label' => 'Item Headline',
            ],
            self::field_body,
          ],
          'repeaterTitle' => '#n: {title}',
          'familyFriendly' => 1,
          'repeaterDepth' => 0,
          'tags' => '',
          'repeaterAddLabel' => 'Add New Item',
          'columnWidth' => 100,
          'repeaterLoading' => 0,
        ],
      ],
      'templates' => [
        $this->getTplName() => [
          'fields' => [
            RockPageBuilder::field_eyebrow,
            'title' => [
              'label' => 'Block Headline',
              'collapsed' => Inputfield::collapsedBlank,
              'icon' => 'header',
            ],
            self::field_items,
          ],
        ],
      ],
    ]);
  }

  /**
   * (Optional) Settings of this block
   *
   * You can access settings of the block via $block->settings() or access
   * a single setting via $block->settings('foo') or $block->settings('bar')
   *
   * If you don't want any settings for your block you can remove this method.
   */
  public function settingsTable(\ProcessWire\RockFieldsField $field)
  {
    // You can set default settings for all blocks via hook.
    // See docs for details or leave this line unchanged.
    $settings = $this->getDefaultSettings($field);

    $settings->add([
      'name' => 'maxw',
      'label' => 'Limit Section Width',
      'value' => $field->input('maxw', 'select', [
        'm' => 'medium (600px)',
        'l' => 'large (800px)',
      ]),
    ]);

    $settings->add([
      'name' => 'bg',
      'label' => 'Background',
      'value' => $field->input('bg', 'select', [
        'primary' => 'Primary',
        'secondary' => 'Secondary',
        'muted' => 'Muted',
      ]),
    ]);

    $settings->add([
      'name' => 'firstopen',
      'label' => 'First Item Open',
      'value' => $field->input('firstopen', 'checkbox'),
    ]);

    return $settings;
  }
}
