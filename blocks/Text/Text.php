<?php

namespace RockPageBuilderBlock;

use ProcessWire\FieldtypeTextarea;
use ProcessWire\RockPageBuilder;
use RockPageBuilder\Block;

class Text extends Block
{

  const prefix = "rpb_text_";

  const field_text = self::prefix . "text";

  public function info()
  {
    return [
      'title' => 'Text',
      'spaceV' => self::spaceM,
      // 'description' => 'RockPageBuilder Block Setup Demo',
      // 'icon' => 'picture-o',
      // 'color' => 'lime',
      // 'hideTitle' => true, // shortcut to hide the title field
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
    $rm->migrate([
      'fields' => [
        self::field_text => [
          'type' => 'textarea',
          'inputfieldClass' => 'InputfieldTinyMCE',
          'contentType' => FieldtypeTextarea::contentTypeHTML,
          'label' => 'Text',
          'rows' => 5,
          'icon' => 'align-left',
          'inlineMode' => true,
          // 'rpb-nolabel' => true, // hide label in backend
          'settingsFile' => '/site/modules/RockMigrations/TinyMCE/text.json',
          'textformatters' => [
            'TextformatterRockFrontend',
          ],
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
            self::field_text,
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
        's' => 'small (400px)',
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
