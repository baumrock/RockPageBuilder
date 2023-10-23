<?php

namespace RockPageBuilderBlock;

use ProcessWire\FieldtypeFile;
use ProcessWire\RockPageBuilder;
use RockPageBuilder\Block;

class Hero extends Block
{

  const prefix = "rpb_hero_";

  const field_image = self::prefix . "image";

  public function info()
  {
    return [
      'title' => 'Hero',
      'spaceV' => self::spaceM,
      // 'description' => 'RockPageBuilder Block Setup Demo',
      // 'icon' => 'picture-o',
      // 'color' => 'lime',
      // 'hideTitle' => true, // shortcut to hide the title field
    ];
  }

  /** frontend */

  public function bgImage(): string
  {
    $img = $this->image(1);
    if (!$img) return "";
    return $img->maxSize(1280, 1280)->webp->url . "?m=" . $img->modified;
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
        self::field_image => [
          'type' => 'image',
          'label' => 'Image',
          'maxFiles' => 1,
          'descriptionRows' => 1,
          'extensions' => 'jpg jpeg gif png svg',
          'maxSize' => 3, // max 3 megapixels
          'okExtensions' => ['svg'],
          'icon' => 'picture-o',
          'outputFormat' => FieldtypeFile::outputFormatSingle,
          'gridMode' => 'grid', // left, list
        ],
      ],
      'templates' => [
        $this->getTplName() => [
          'fields' => [
            RockPageBuilder::field_eyebrow,
            'title' => [
              'label' => 'Headline',
            ],
            self::field_image,
            RockPageBuilder::field_teaser,
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
      'name' => 'style',
      'label' => 'Style Variation',
      'value' => $field->input('style', 'select', [
        '*one' => 'Style One (image in the background)',
        'two' => 'Style Two (image on the right)',
        'three' => 'Style Three (centered)',
      ]),
    ]);

    return $settings;
  }
}
