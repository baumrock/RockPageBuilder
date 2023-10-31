<?php

namespace RockPageBuilderBlock;

use ProcessWire\FieldtypeFile;
use ProcessWire\FieldtypeTextarea;
use ProcessWire\Inputfield;
use ProcessWire\RockPageBuilder;
use RockPageBuilder\Block;

class Features extends Block
{

  const prefix = "rpb_features_";

  const field_body = self::prefix . "body";
  const field_items = self::prefix . "items";
  const field_icon = self::prefix . "icon";

  public function info()
  {
    return [
      'title' => 'Features',
      'icon' => 'star-o',
    ];
  }

  /** frontend */

  public function bgStyle(): string
  {
    $bg = $this->settings('bg');
    if ($bg == "primary") return "uk-background-primary section-padding uk-light";
    elseif ($bg == "secondary") return "uk-background-secondary section-padding uk-light";
    elseif ($bg == "muted") return "uk-background-muted section-padding";
    return "";
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

    // This shows how you can remove fields from a block that you have added
    // at some earlier point in history.
    $rm->deleteField('demo', true);

    $rm->migrate([
      // here you can create fields for your block
      // note that you always need to create fields and then add them to the tpl
      'fields' => [
        self::field_body => [
          'type' => 'textarea',
          'inputfieldClass' => 'InputfieldTinyMCE',
          'contentType' => FieldtypeTextarea::contentTypeHTML,
          'label' => 'Description',
          'rows' => 5,
          'icon' => 'align-left',
          'inlineMode' => true,
          // 'rpb-nolabel' => true, // hide label in backend
          'settingsFile' => '/site/modules/RockMigrations/TinyMCE/simple.json',
        ],
        self::field_icon => [
          'type' => 'image',
          'label' => 'Icon',
          'maxFiles' => 1,
          'descriptionRows' => 0,
          'extensions' => 'svg',
          'okExtensions' => ['svg'],
          'icon' => 'picture-o',
          'outputFormat' => FieldtypeFile::outputFormatSingle,
          'gridMode' => 'list', // left, list
          'collapsed' => Inputfield::collapsedBlank,
        ],
        self::field_items => [
          'label' => 'Items',
          'icon' => 'cubes',
          'type' => 'FieldtypeRepeater',
          'fields' => [
            self::field_icon,
            'title' => [
              'label' => 'Headline',
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
              'label' => 'Headline',
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
      'name' => 'itemw',
      'label' => 'Item Width',
      'value' => $field->input('itemw', 'select', [
        '*s' => 'small',
        'm' => 'medium',
        'l' => 'large',
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

    return $settings;
  }
}
