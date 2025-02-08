<a
  href="<?= $page->editUrl() ?>"
  title="<?= $wire->_('Drag & Drop on/off') ?>"
  uk-tooltip
  data-toggle="dragdrop"
  data-persist
  class='on'>
  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
    <g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2">
      <path d="M19 11V9a2 2 0 0 0-2-2H9a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h2" />
      <path d="m13 13l9 3l-4 2l-2 4l-3-9M3 3v.01M7 3v.01M11 3v.01M15 3v.01M3 7v.01M3 11v.01M3 15v.01" />
    </g>
  </svg>
</a>
<script>
  document.addEventListener("DOMContentLoaded", function() {
    RockFrontendToolbar.onToggle('dragdrop', (type) => {
      if (type === 'on') {
        RockSortable.enable();
      } else {
        RockSortable.disable();
      }
    });
  });
</script>