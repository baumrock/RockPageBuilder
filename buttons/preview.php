<?php

use function ProcessWire\wire;

$url = wire()->config->urls->siteModules . 'RockPageBuilder/buttons/';
?>
<style>
  img.thumb {
    height: 80px;
  }
</style>
<strong>Usage:</strong>
<pre>
<code class='uk-margin-remove'>public function info()
{
  return [
    'title' => 'Bild + Text',
    'thumb' => 'TextImage',
  ];
}</code>
</pre>
<p><strong>Custom Thumbnails:</strong></p>
<div class='uk-flex uk-flex-wrap' style='gap:5px;'>
  <?php foreach (glob(__DIR__ . '/*.svg') as $file): ?>
    <a class='copy'>
      <img
        src="<?= $url . basename($file) ?>"
        class='thumb'
        title='<?= basename($file, '.svg') ?>'
        data-name='<?= basename($file, '.svg') ?>'
        uk-tooltip>
    </a>
  <?php endforeach; ?>
</div>
<p class='uk-margin-top'><strong>Icon Thumbnails:</strong></p>
<p>You can use any Font Awesome 4 icon easily by setting the "icon" property in the info() method!</p>
<script>
  // Copy a string to the clipboard
  function copyToClipboard(string) {
    const $temp = $('<input type="text" value="' + string + '">');
    $('body').append($temp);
    $temp.select();
    document.execCommand('copy');
    $temp.remove();
  }

  // copy title attribute to clipboard when a.copy is clicked
  document.querySelectorAll('.copy').forEach(el => {
    el.addEventListener('click', function() {
      const img = el.closest('a.copy').querySelector('img');
      const name = $(img).data('name');
      copyToClipboard(name);
      UIkit.notification('Copied to clipboard: ' + name);
    });
  });
</script>