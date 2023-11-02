<?php

namespace RockPageBuilderBlock;

use ProcessWire\FieldtypeTextarea;
use ProcessWire\RockPageBuilder;
use RockPageBuilder\Block;

class CallToAction extends Block
{

  const prefix = "rpb_calltoaction_";

  const field_right = self::prefix . "right";

  public function info()
  {
    return [
      'title' => 'CallToAction',
      'icon' => 'bullhorn',
    ];
  }

  /** frontend */

  public function bgStyle(): string
  {
    $bg = $this->settings('bg');
    if ($bg == "primary") return "uk-background-primary uk-padding uk-light";
    elseif ($bg == "secondary") return "uk-background-secondary uk-padding uk-light";
    elseif ($bg == "muted") return "uk-background-muted uk-padding";
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
        self::field_right => [
          'type' => 'textarea',
          'inputfieldClass' => 'InputfieldTinyMCE',
          'contentType' => FieldtypeTextarea::contentTypeHTML,
          'label' => 'Buttons',
          'rows' => 5,
          'icon' => 'bullhorn',
          'inlineMode' => true,
          // 'rpb-nolabel' => true, // hide label in backend
          'settingsFile' => '/site/modules/RockMigrations/TinyMCE/simple.json',
        ],
      ],
      'templates' => [
        $this->getTplName() => [
          'fields' => [
            RockPageBuilder::field_eyebrow,
            'title' => [
              'label' => 'Headline',
            ],
            RockPageBuilder::field_teaser,
            self::field_right,
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
      'label' => 'Limit Width',
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

    return $settings;
  }
}
