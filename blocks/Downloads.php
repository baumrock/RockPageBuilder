<?php namespace RMBlock;

use ProcessWire\HookEvent;
use ProcessWire\RockMatrix;
use ProcessWire\WireData;

class Downloads extends \RockMatrix\Block {

  const prefix = RockMatrix::prefix."downloads_";

  const field_files = self::prefix."files";

  public function info() {
    return parent::info()->setArray([
      'icon' => 'download',
      'title' => 'Downloads',
      'description' => 'Alle hochgeladenen Dateien werden als Download angeboten',
    ]);
  }

  public function init() {
    $this->addHookAfter("Pages::saveReady", $this, "saveReady");
  }

  public function buildForm($fs) {
    if($f = $fs->get('title')) {
      $f->label = "Überschrift für diesen Block";
      $f->notes = "zB Downloads";
    }
  }

  public function getLabel() {
    return $this->title ?: $this->info()->title;
  }

  public function ___link($info, $options = []) {
    $opt = $this->wire(new WireData()); /** @var WireData $opt */
    $opt->setArray([
      'notab' => null,
      'target' => null,
      'download' => true,
      'href' => $info->url,
    ]);
    $opt->setArray($options);

    $tab = $opt->notab ? "tabindex='-1'" : "";
    $target = $opt->target ? "target='{$opt->target}'" : '';
    $download = $opt->download ? 'download' : '';

    return "<a href='{$opt->href}' class='uk-position-cover'
      $tab $target $download></a>";
  }

  public function migrate() {
    parent::migrate();
    $this->rm()->migrate([
      'fields' => [
        self::field_files => [
          'type' => 'file',
          'maxFiles' => 0,
          'descriptionRows' => 1,
          'tags' => RockMatrix::tags,
          'extensions' => "PDF DOC DOCX XLS XLSX ZIP JPG PNG GIF TXT",
          'label' => 'Downloads',
          'icon' => 'download',
        ],
      ],
      'templates' => [
        $this->getTplName() => [
          'fields' => [
            'title',
            self::field_files,
          ],
        ],
      ],
    ]);
    $this->rm()->setFieldData("title", [
      'required' => 0,
    ], $this->getTpl());
  }

  public function render() {
    $files = $this->get(self::field_files);
    if(!$files->count()) return;

    $out = '';
    if($this->title) $out .= "<h2>{$this->title}</h2>";
    $out .= '<table class="uk-table uk-table-striped uk-table-small tm-downloads">';
    $out .= '<tbody>';
    foreach($files as $file) {

      $info = $this->rm()->info($file);
      switch ($info->extension) {
        case 'jpg':
        case 'png':
        case 'svg':
        case 'gif':
          $icon = "image";
          break;

        case 'pdf':
          $icon = "file-pdf";
          break;

        case 'txt':
          $icon = "file-text";
          break;

        default:
          $icon = "download";
          break;
      }
      $icon = "<span uk-icon='$icon'></i>";

      $human = function($bytes, $decimals = 2) {
        $sz = 'BKMGTP';
        $factor = floor((strlen($bytes) - 1) / 3);
        return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . @$sz[$factor];
      };

      $label = $file->description ?: $info->filename;
      $desc = $info->basename;

      // show filesize if file exists (prevents error on localhost)
      if(file_exists(($info->path))) {
        $size = filesize($info->path);
        $desc .= " (".$human($size).")";
      }

      $out .= "<tr>"
        ."<td class='uk-width-auto uk-position-relative tm-icon'>
            {$this->link($info, ['notab'=>true])}
            $icon
          </td>"
        ."<td class='uk-width-expand uk-position-relative'>
            {$this->link($info)}
            <strong>$label</strong><br>
            <small>$desc</small>
          </td>"
        ."</tr>";
    }
    $out .= '</tbody></table>';

    return "<div class=rmblock-downloads>$out</div>";
  }

  public function saveReady(HookEvent $event) {
    $page = $event->arguments(0);
    if($page->template !== $this->getTpl()) return;
    if(!$page->id) $page->title = "Downloads";
  }

}
