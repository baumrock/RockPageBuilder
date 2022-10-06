"use strict";

function RockPageBuilderItem(e) {
  // save root dom element to this instance
  this.$ = this.$root(e);
}

// DOM functions

RockPageBuilderItem.prototype.$root = function (e) {
  let el = e.target; // param = event
  if (!el) el = e; // param = dom element
  return $(el).closest(".rpb-item");
};

// API

// add trash flag
RockPageBuilderItem.prototype.trash = function () {
  this.$.addClass("rpb-trash").trigger("changed");
};

// remove trash flag
RockPageBuilderItem.prototype.untrash = function () {
  this.$.removeClass("rpb-trash").trigger("changed");
};

// Helpers

// get json ready for the textarea
RockPageBuilderItem.prototype.getJSON = function () {
  let trash = this.$.hasClass("rpb-trash") ? 1 : 0;
  let changed = this.$.find(".InputfieldStateChanged").length;

  // check if the item was added on this request
  // this ensures that added items are saved at least once which triggers
  // a processInput and checks for field erros (eg empty required fields)
  let added = this.$.hasClass("rpb-added") ? 1 : 0;

  return {
    id: this.$.data("page"),
    trash,
    changed: changed + trash + added,
  };
};
