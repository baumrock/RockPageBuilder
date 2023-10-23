<?php

namespace RockPageBuilderBlock;

use ProcessWire\FieldtypeFile;
use ProcessWire\RockPageBuilder;
use RockPageBuilder\Block;

class Gallery extends Block
{

  const prefix = "rpb_gallery_";

  const field_images = self::prefix . "images";

  public function info()
  {
    return [
      'title' => 'Gallery',
      'spaceV' => self::spaceM,
      // 'description' => 'RockPageBuilder Block Setup Demo',
      // 'icon' => 'picture-o',
      // 'color' => 'lime',
      // 'hideTitle' => true, // shortcut to hide the title field
    ];
  }

  /** frontend */

  public function thumbWidth()
  {
    switch ($this->settings('width')) {
      case "s":
        return 150;
      case "m":
        return 200;
      case "l":
        return 300;
    }
    return 200;
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
        self::field_images => [
          'type' => 'image',
          'label' => 'Images',
          'maxFiles' => 0,
          'descriptionRows' => 1,
          'extensions' => 'jpg jpeg gif png',
          'maxSize' => 3, // max 3 megapixels
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
            self::field_images,
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
      'name' => 'width',
      'label' => 'Thumbnail Width',
      'value' => $field->input('width', 'select', [
        's' => 'small (150px)',
        '*m' => 'medium (200px)',
        'l' => 'large (300px)',
      ]),
    ]);

    $settings->add([
      'name' => 'maxw',
      'label' => 'Limit Block Width',
      'value' => $field->input('maxw', 'select', [
        's' => 'small (400px)',
        'm' => 'medium (600px)',
        'l' => 'large (800px)',
      ]),
    ]);

    $settings->add([
      'name' => 'muted',
      'label' => 'Muted Background',
      'value' => $field->input('muted', 'checkbox'),
    ]);

    return $settings;
  }
}
