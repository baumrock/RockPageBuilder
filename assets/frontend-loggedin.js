"use strict";

// add loading spinner before page is reloaded
(function () {
  let showSpinner = false;

  // add overlay on page unload event
  window.onbeforeunload = function () {
    if (!showSpinner) return;
    document.querySelector("body").classList.add("rpb-reloading");
    let div = document.createElement("div");
    div.id = "rpb-reloading";
    div.innerHTML = '<span class="loader"></span>';
    document.body.appendChild(div);
    setTimeout(() => {
      div.classList.add("show");
    }, 10);
  };

  // only show spinner if an alfred button was clicked
  // this might not be 100% accurate but I think the modal does not have events
  // to catch so this is the best for now
  window.addEventListener("click", (e) => {
    let button = e.target.closest(".ui-dialog-buttonset button");
    if (!button) return;
    showSpinner = true;
  });
})();
