<?php

namespace RockPageBuilderBlock;

use RockPageBuilder\Block;

/**
 * This block is here to demonstrate the block spacings concept of RockPageBuilder
 * Please see the docs about block spacings:
 * https://baumrock.com/en/processwire/modules/rockpagebuilder/docs/spacings
 */
class Spacings extends Block
{

  const prefix = "rpb_spacings_";

  public function info()
  {
    return [
      'title' => 'Spacings',
      'description' => 'Please see docs about Block Spacings!',
      'spaceV' => self::spaceM,
      'hideTitle' => true, // shortcut to hide the title field
    ];
  }

  public function spaceID(): string
  {
    $bg = $this->settings('bg');
    if ($bg) return $bg;
    return "default";
  }

  public function migrate()
  {
    $rm = $this->rockmigrations();
    $rm->migrate([
      'fields' => [], // no fields needed
      'templates' => [
        $this->getTplName() => [
          'fields' => [
            'title',
          ],
        ],
      ],
    ]);
  }

  public function settingsTable(\ProcessWire\RockFieldsField $field)
  {
    $settings = $this->getDefaultSettings($field);

    // add settings for this block with array syntax
    $settings->add([
      'name' => 'bg',
      'label' => 'Background',
      'value' => $field->input('bg', 'select', [
        'green' => 'Green',
        'blue' => 'Blue',
      ]),
    ]);

    return $settings;
  }
}
