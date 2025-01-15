<?php

namespace RockPageBuilderBlock;

use RockPageBuilder\Block;
use ProcessWire\FieldtypeFile;
use ProcessWire\Inputfield;
use ProcessWire\RockPageBuilder;

use function ProcessWire\wire;

class Quotes extends Block
{

  const prefix = "rpb_quotes_";

  const field_quotes = self::prefix . "quotes";
  const field_author = self::prefix . "author";
  const field_text = self::prefix . "text"; // quote text
  const field_image = self::prefix . "image";
  const field_subtitle = self::prefix . "subtitle";

  public function info()
  {
    return [
      'title' => 'Quotes',
      'icon' => 'quote-left',
      'hideTitle' => false, // shortcut to set title field to hidden
    ];
  }

  /** frontend */

  /** backend */

  public function migrate()
  {
    $rm = $this->rockmigrations();
    $multiLang = !!wire()->languages;

    $rm->migrate([
      'fields' => [
        self::field_author => [
          'type' => $multiLang ? 'textLanguage' : 'text',
          'label' => 'Author',
          'icon' => 'user-o',
          'textformatters' => [
            'TextformatterEntities',
          ],
          'columnWidth' => 50,
        ],
        self::field_subtitle => [
          'type' => $multiLang ? 'textLanguage' : 'text',
          'label' => 'Author Description',
          'icon' => 'user-o',
          'textformatters' => [
            'TextformatterEntities',
          ],
          'columnWidth' => 50,
        ],
        self::field_text => [
          'type' => $multiLang ? 'textareaLanguage' : 'textarea',
          'label' => 'Quote Text',
          'rows' => 5,
          'icon' => 'quote-left',
          'textformatters' => [
            'TextformatterEntities',
          ],
        ],
        self::field_image => [
          'type' => 'image',
          'label' => 'Image',
          'maxFiles' => 1,
          // 'descriptionRows' => ,
          'extensions' => 'jpg jpeg gif png svg',
          'maxSize' => 3, // max 3 megapixels
          'okExtensions' => ['svg'],
          'icon' => 'picture-o',
          'outputFormat' => FieldtypeFile::outputFormatSingle,
          'gridMode' => 'grid', // left, list
          'collapsed' => Inputfield::collapsedBlank,
        ],
        self::field_quotes => [
          'label' => 'Quotes',
          'type' => 'FieldtypeRepeater',
          'icon' => 'bullhorn',
          'fields' => [
            self::field_image,
            self::field_text,
            self::field_author,
            self::field_subtitle,
          ],
          'repeaterTitle' => '#n: {rpb_quotes_author}',
          'familyFriendly' => 1,
          'repeaterDepth' => 0,
          'repeaterAddLabel' => 'Add New Quote',
          'columnWidth' => 100,
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
            self::field_quotes,
          ],
        ],
      ],
    ]);
  }
}
