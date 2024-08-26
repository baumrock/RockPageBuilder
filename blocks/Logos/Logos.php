<?php

namespace RockPageBuilderBlock;

use ProcessWire\FieldtypeFile;
use RockPageBuilder\Block;

class Logos extends Block
{

  const prefix = "rpb_logos_";

  const field_logos = self::prefix . "logos";
  const field_url = self::prefix . "url";
  const field_logo = self::prefix . "logo";

  public function info()
  {
    return [
      'title' => 'Logos',
      'icon' => 'handshake-o',
    ];
  }

  public function migrate()
  {
    $rm = $this->rockmigrations();
    $rm->migrate([
      'fields' => [
        self::field_logo => [
          'type' => 'image',
          'label' => 'Logo',
          'maxFiles' => 1,
          'descriptionRows' => 0,
          'extensions' => 'jpg jpeg gif png svg',
          'maxSize' => 3, // max 3 megapixels
          'okExtensions' => ['svg'],
          'icon' => 'picture-o',
          'outputFormat' => FieldtypeFile::outputFormatSingle,
          'gridMode' => 'list', // grid, left, list
          'required' => true,
        ],
        self::field_url => [
          'type' => 'URL',
          'label' => 'URL',
          'icon' => 'link',
          'textformatters' => [
            'TextformatterEntities',
          ],
        ],
        self::field_logos => [
          'label' => 'Logos',
          'type' => 'FieldtypeRepeater',
          'fields' => [
            self::field_logo,
            'title' => [
              'required' => false,
            ],
            self::field_url,
          ],
          'repeaterTitle' => '#n: {title}',
          'familyFriendly' => 1,
          'repeaterDepth' => 0,
          'tags' => '',
          'repeaterAddLabel' => 'Add New Item',
          'columnWidth' => 100,
        ],
      ],
      'templates' => [
        $this->getTplName() => [
          'fields-' => [
            self::field_logos,
          ],
        ],
      ],
    ]);
  }
}
