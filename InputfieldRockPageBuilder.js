"use strict";

function RockPageBuilder() {
  this.editdelay = 5;
  this.submitdelay = this.editdelay + 20;

  this.init = false;
  this.changeTimer;

  this.actions = {};
}

// DOM helpers

// return the field's root element
RockPageBuilder.prototype.$root = function (e) {
  let el = e.target; // param = event
  if (!el) el = e; // param = dom element
  return $(el).closest(".InputfieldRockPageBuilder");
};

// get the item (block) element
RockPageBuilder.prototype.$item = function (e) {
  let el = e.target; // param = event
  if (!el) el = e; // param = dom element
  return $(el).closest(".rpb-item");
};

// return all item's list elements
RockPageBuilder.prototype.$items = function (e) {
  return this.$root(e).find(".rpb-items > ul > li.rpb-item");
};

// return the items container
RockPageBuilder.prototype.$itemsContainer = function (e) {
  return this.$root(e).find(".rpb-items");
};

// textarea holding field data
RockPageBuilder.prototype.$textarea = function (e) {
  return this.$root(e).find("textarea.rpb-data");
};

// helpers

RockPageBuilder.prototype.addAction = function (name, callback) {
  this.actions[name] = callback;
};

RockPageBuilder.prototype.addItem = function (e, json) {
  let $root = this.$root(e);
  let $container = this.$itemsContainer(e);

  // add item to container
  let $item = $(json.markup);
  $container.append($item);
  $item.find(".rpb-item").addClass("rpb-added");
  this.initItem($item);

  // trigger change
  $root.trigger("changed");
};

RockPageBuilder.prototype.fire = function ($action) {
  let action = $action.data("action");
  let item = this.getItem($action[0]);
  let callback = this.actions[action];
  if (typeof callback == "undefined") return;
  callback(item);
};

RockPageBuilder.prototype.getData = function (e) {
  return RockPageBuilder.getItems(e, true);
};

RockPageBuilder.prototype.getItem = function (e) {
  return new RockPageBuilderItem(e);
};

RockPageBuilder.prototype.getItems = function (e, json) {
  let items = [];
  let rm = this;
  $.each(RockPageBuilder.$items(e), function (i, el) {
    let item = rm.getItem(el);
    if (json) items.push(item.getJSON());
    else items.push(item);
  });
  return items;
};

RockPageBuilder.prototype.getName = function (e) {
  let $root = this.$root(e);
  // remove wrap_Inputfield_ from string
  return $root.attr("id").replace("wrap_Inputfield_", "");
};

RockPageBuilder.prototype.initItem = function ($item) {
  InputfieldsInit($item); // init inputfield

  // trigger the reloaded event
  // this initializes file and ckeditor fields (and maybe more)
  $item.find(".Inputfield").trigger("reloaded");
};

RockPageBuilder.prototype.makeSortable = function (e) {
  let $container = this.$itemsContainer(e);

  let not_draggable = $container.find("> ul:not(.rpb-draggable)").length;
  if (!not_draggable) return;

  // init uikit sortable on container
  UIkit.sortable($container[0], {
    // longer animation duration prevents flicker
    animation: 500,

    // set the handle to the header
    // this ensures that other drag&drop features don't break (eg images)
    handle: ".rpb-item > .InputfieldHeader > .fa-arrows",
  });

  // add class to every sortable element
  // to make it addressable via css
  let $items = this.$items(e);
  if ($items.length) {
    $container.removeClass("uk-hidden");
    $.each($items, function (i, el) {
      $(el).parent().addClass("rpb-draggable");
    });
  } else {
    $container.addClass("uk-hidden");
  }
};

/**
 * Reset CKE after dragging
 */
RockPageBuilder.prototype.resetCKEs = function ($item) {
  $.each($item.find("div.cke"), function (i, el) {
    let $div = $(el);
    let id = $div.attr("id"); // eg cke_Inputfield_123_text
    id = id.substr(4); // Inputfield_123_text

    // get config for restore
    let $textarea = $div.parent().find("> textarea");
    let config = ProcessWire.config[$textarea.attr("data-configName")];

    CKEDITOR.instances[id].destroy();
    CKEDITOR.replace(id, config);
  });
};

RockPageBuilder.prototype.setTextarea = function (e, preventTrigger) {
  preventTrigger = preventTrigger || false;
  let $text = this.$textarea(e);
  let data = this.getData(e);
  let json = JSON.stringify(data);
  $text.val(json).text(json);
  if (!preventTrigger) $text.change();
};

RockPageBuilder.prototype.spin = function ($el, _cls) {
  let cls = _cls || "";
  let $i = $el.find("i.fa").first();
  $i.data("tmpcls", $i.attr("class"));
  $i.attr("class", "fa fa-spinner fa-spin " + cls);
};

RockPageBuilder.prototype.unspin = function ($el) {
  let $i = $el.find("i.fa").first();
  $i.attr("class", $i.data("tmpcls"));
  $i.removeAttr("tmpcls");
};

// event handlers

// change triggerd
RockPageBuilder.prototype.changed = function (e) {
  let rm = this;
  clearTimeout(rm.changeTimer);
  // debounce for all changes
  rm.changeTimer = setTimeout(function () {
    rm.makeSortable(e);
    rm.setTextarea(e, true);
    if (rm.init) {
      console.log("RockPageBuilder changed");
      rm.$root(e).addClass("InputfieldStateChanged");
    } else console.log("RockPageBuilder init");
    rm.init = true;
  }, this.editdelay);
};

// click on add new item button
RockPageBuilder.prototype.clickAdd = function (e) {
  e.preventDefault();

  // get link
  let $a = $(e.target).closest("a");
  let href = $a.data("href");
  let rm = this;

  // prevent double-click
  if ($a.hasClass("loading")) return;

  // show spinner
  $a.addClass("loading");

  // send ajax request
  $.getJSON(href, function (json) {
    if (json.error) ProcessWire.alert(json.message);
    else rm.addItem(e, json);
  })
    .fail(function (json) {
      ProcessWire.alert("AJAX Error");
    })
    .always(function () {
      $a.removeClass("loading");
    });
};

// init
RockPageBuilder.prototype.initialize = function (e) {
  this.makeSortable(e.target);
  this.$root(e).trigger("changed");
};

var RockPageBuilder = new RockPageBuilder();

// listeners

// init the rpb
$(document).on("init", ".InputfieldRockPageBuilder", function (e) {
  RockPageBuilder.initialize(e);
});

// add a new rpb item
$(document).on(
  "click",
  ".InputfieldRockPageBuilder .rpb-buttons:not(.modal) a:not(.noclick)",
  function (e) {
    RockPageBuilder.clickAdd(e);
  }
);

// change event triggered on root element
$(document).on("changed", ".InputfieldRockPageBuilder", function (e) {
  RockPageBuilder.changed(e);
});

// items sort oder changed
$(document).on("stop sorted", ".rpb-items", function (e) {
  RockPageBuilder.changed(e);
});

// whenever a rpb item is moved we reset CKEditor fields
$(document).on("moved", ".rpb-items.uk-sortable", function (e) {
  let $item = $(e.originalEvent.detail[1]);
  RockPageBuilder.resetCKEs($item);
});

// monitor all inputfields in a rockpagebuilder field
$(document).on(
  "change",
  ".rpb-items input, .rpb-items textarea, .rpb-items select",
  function (e) {
    setTimeout(function () {
      RockPageBuilder.changed(e);
    });
  }
);
// monitor inline ckeditor fields
$(document).on("blur keyup paste input", "[contenteditable]", function (e) {
  RockPageBuilder.changed(e);
});

// trigger changed event after file uploads
$(document).on("AjaxUploadDone", function (e) {
  RockPageBuilder.changed(e);
});

// make sure to add InputfieldStateChanged immediately after keydown
// we do not intercept form.submit() because that somehow brakes the save process
$(document).on("keydown", ".InputfieldRockPageBuilder input", function (e) {
  $(e.target).closest(".Inputfield").addClass("InputfieldStateChanged");
  RockPageBuilder.changed(e);
});

// fix pw-panel issue
// https://github.com/processwire/processwire-requests/issues/176
$(document).on("click", ".pw-panel:not(pw-panel-init)", function (e) {
  e.preventDefault();
  let $el = $(e.target).closest(".pw-panel");
  $el.addClass("pw-panel-init");

  // setup link element
  let $a = $el.closest("a[href]");

  // add panel and trigger click
  $.when(pwPanels.addPanel($a)).then(function () {
    $a.click();
  });
  return false;
});

// fix pw-panel issue
// https://github.com/processwire/processwire-requests/issues/176
$(document).on("click", ".pw-panel:not(pw-panel-init)", function (e) {
  e.preventDefault();
  let $el = $(e.target).closest(".pw-panel");
  $el.addClass("pw-panel-init");

  // setup link element
  let $a = $el.closest("a[href]");

  // add panel and trigger click
  $.when(pwPanels.addPanel($a)).then(function () {
    $a.click();
  });
  return false;
});

// click on create new block type
$(document).on("click", ".createBlockType", function (e) {
  let $li = RockPageBuilder.$root(e.target);
  if (!$li.length) return;
  let field = RockPageBuilder.getName(e);
  let existing = "";
  $.each(ProcessWire.config.RockPageBuilderBlocks, function (i, item) {
    existing += "<a href=/ class=rpb-copyblockname>" + item + "</a> | ";
  });
  UIkit.modal
    .prompt(
      "Technical name of block to be created (or reused):<br><small>" +
        existing +
        "</small>"
    )
    .then(function (name) {
      if (!name) return;
      $(".uk-modal-body").text("loading...");
      $.get("/rpb-create-block/?field=" + field + "&name=" + name)
        .then(function (data) {
          if (data === "success") $("#submit_save").click();
          else UIkit.modal.alert("Request failed: " + data);
        })
        .fail(function () {
          UIkit.modal.alert("Request failed");
        });
    });
});

$(document).on("click", ".rpb-copyblockname", function (e) {
  e.preventDefault();
  let name = $(e.target).text();
  $(e.target).closest(".uk-modal-body").find("input").val(name);
});

/** Block Actions */

// monitor action clicks
$(document).on("click", ".rpb-action", function (e) {
  let $action = $(e.target).closest(".rpb-action");
  let href = $action.attr("href");

  // prevent field toggle if data-toggle is not set (default)
  if (!$action.data("toggle")) e.preventDefault();

  let target = $action.data("target");
  if (href && target) {
    e.preventDefault();
    window.open(href, target);
    return false;
  }

  // console.log(href);
  if (href && href != "#") location.href = href;
  else RockPageBuilder.fire($action);

  if (!$action.data("toggle")) return false;
});

RockPageBuilder.addAction("trash", function (item) {
  item.trash();
});
RockPageBuilder.addAction("untrash", function (item) {
  item.untrash();
});
