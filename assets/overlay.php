<div class="rf-overlay">
  <img class="rf-img" src="<?= $src ?>" alt="RockFrontend Overlay Image">
  <div class="rf-gui">
    <svg class="rf-opacity-0" width="32" height="32" viewBox="0 0 24 24">
      <path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m7 8l-4 4l4 4m10-8l4 4l-4 4M14 4l-4 16" />
    </svg>
    <input type="range" min="0" max="1" value="0.5" step="0.01" class="rf-overlay-slider">
    <svg class="rf-opacity-1" width="32" height="32" viewBox="0 0 24 24">
      <g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2">
        <path d="M3 21v-4a4 4 0 1 1 4 4H3" />
        <path d="M21 3A16 16 0 0 0 8.2 13.2M21 3a16 16 0 0 1-10.2 12.8" />
        <path d="M10.6 9a9 9 0 0 1 4.4 4.4" />
      </g>
    </svg>
    <svg class='rf-color-off' width="32" height="32" viewBox="0 0 24 24">
      <g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2">
        <path d="M7.934 3.97A8.993 8.993 0 0 1 12 3c4.97 0 9 3.582 9 8c0 1.06-.474 2.078-1.318 2.828a4.515 4.515 0 0 1-1.118.726M15 15h-1a2 2 0 0 0-1 3.75A1.3 1.3 0 0 1 12 21A9 9 0 0 1 5.628 5.644" />
        <circle cx="7.5" cy="10.5" r="1" />
        <circle cx="12" cy="7.5" r="1" />
        <circle cx="16.5" cy="10.5" r="1" />
        <path d="m3 3l18 18" />
      </g>
    </svg>
    <svg class='rf-color-on' hidden width="32" height="32" viewBox="0 0 24 24">
      <g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2">
        <path d="M12 21a9 9 0 1 1 0-18a9 8 0 0 1 9 8a4.5 4 0 0 1-4.5 4H14a2 2 0 0 0-1 3.75A1.3 1.3 0 0 1 12 21" />
        <circle cx="7.5" cy="10.5" r=".5" fill="currentColor" />
        <circle cx="12" cy="7.5" r=".5" fill="currentColor" />
        <circle cx="16.5" cy="10.5" r=".5" fill="currentColor" />
      </g>
    </svg>
  </div>
</div>