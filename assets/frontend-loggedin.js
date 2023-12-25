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

function _RockSortable() {}

/**
 * Get the sortable container
 */
_RockSortable.prototype.getContainer = function (el) {
  return el.closest("[sortable]") || false;
};

/**
 * Get the block id from the current dom element
 */
_RockSortable.prototype.getPageId = function (el) {
  // if the element has a block id we return it
  let id = el.getAttribute("data-rpbblock");
  if (id) return id;

  // element is a parent element, so we find the first child with a page id
  return el.querySelector("[data-rpbblock]").getAttribute("data-rpbblock");
};

/**
 * Get array of all page ids within the current container
 */
_RockSortable.prototype.getPageIds = function (el) {
  let container = this.getContainer(el);
  let blocks = container.querySelectorAll("[data-rpbblock]");
  let ids = [];
  blocks.forEach((block) => {
    ids.push(this.getPageId(block));
  });
  return ids;
};

_RockSortable.prototype.parseAttributes = function (el) {
  const result = {};
  const attribute = "sortable";
  el = this.getContainer(el);

  // Check if the element has the specified attribute
  if (el.hasAttribute(attribute)) {
    // get value and trim final semicolons to prevent empty value in json
    const attributeValue = el.getAttribute(attribute).replace(/;+\s*$/, "");

    // Split the attribute value into individual key-value pairs
    const pairs = attributeValue.split(";").map((pair) => pair.trim());

    // Process each key-value pair and add it to the result object
    pairs.forEach((pair) => {
      const [key, value] = pair.split(":").map((item) => item.trim());
      result[key] = value;
    });
  }

  return result;
};

/**
 * Send ajax request to save new sort order
 */
_RockSortable.prototype.saveSort = function (el) {
  let url =
    RockFrontend.rootUrl +
    "rockpagebuilder-savesort?block=" +
    this.getPageId(el) +
    "&sort=" +
    this.getPageIds(el).join("|");

  fetch(url)
    .then((response) => {
      if (response.ok) {
        let settings = this.parseAttributes(el);
        if (settings.reload) document.location.reload(true);
        return response.json();
      }
      throw new Error("Something went wrong");
    })
    .then((responseJson) => {
      // console.log(responseJson);
    })
    .catch((error) => {
      console.error(error);
    });
};

var RockSortable = new _RockSortable();

// make blocks sortable
function makeSortable() {
  let containers = document.querySelectorAll("[sortable]");
  let body = document.querySelector("body");
  let beforeSort = false;

  // init sortable on all containers
  containers.forEach((container) => {
    if (container.hasAttribute("data-sortable-init")) return;
    container.setAttribute("data-sortable-init", true);

    // init sortable
    new Sortable(container, {
      animation: 150,
      handle: ".sortable-handle",
      onChoose: (e) => {
        beforeSort = RockSortable.getPageIds(e.item).join(",");
        body.classList.add("no-alfred");
      },
      onEnd: (e) => {
        body.classList.remove("no-alfred");
        // no change, no save
        if (RockSortable.getPageIds(e.item).join(",") == beforeSort) return;

        // save sort
        RockSortable.saveSort(e.item);
      },
      onUnchoose: (e) => {
        body.classList.remove("no-alfred");
      },
    });
  });
}
document.addEventListener("DOMContentLoaded", makeSortable);
