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

// fix pasting markup to text fields
// see https://github.com/processwire/processwire-issues/issues/1818
document.addEventListener("paste", function (e) {
  // only intercept pasting to frontend editable text fields
  // we don't want to strip anything from wysiwyg fields
  if (!document.activeElement.isContentEditable) return;
  if (!document.activeElement.closest(".pw-edit-InputfieldText")) return;

  // Prevent the default paste action
  e.preventDefault();

  // Get the clipboard data as plain text
  const clipboardData = (e.originalEvent || e).clipboardData;
  const pastedText = clipboardData.getData("text/plain");

  // Convert any markup in the pasted text to plaintext
  var tmp = document.createElement("div");
  tmp.innerHTML = pastedText;
  const plainText = tmp.textContent || tmp.innerText || "";

  // Insert the plain text into the contenteditable element or textarea
  document.execCommand("insertText", false, plainText);
});
