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

// trash/untrash
RockPageBuilderItem.prototype.trash = function () {
  this.$.addClass("InputfieldStateChanged");
  this.$.addClass("rpb-trash").trigger("changed");
};
RockPageBuilderItem.prototype.untrash = function () {
  this.$.addClass("InputfieldStateChanged");
  this.$.removeClass("rpb-trash").trigger("changed");
};

// hide/unhide
RockPageBuilderItem.prototype.hide = function () {
  this.$.addClass("InputfieldStateChanged");
  this.$.addClass("rpb-hidden").trigger("changed");
};
RockPageBuilderItem.prototype.unhide = function () {
  this.$.addClass("InputfieldStateChanged");
  this.$.removeClass("rpb-hidden").trigger("changed");
};

// Helpers

// get json ready for the textarea
RockPageBuilderItem.prototype.getJSON = function () {
  let trash = this.$.hasClass("rpb-trash") ? 1 : 0;
  let hidden = this.$.hasClass("rpb-hidden") ? 1 : 0;
  let changed = this.$.hasClass("InputfieldStateChanged") * 1;

  // check if the item was added on this request
  // this ensures that added items are saved at least once which triggers
  // a processInput and checks for field erros (eg empty required fields)
  if (this.$.hasClass("rpb-added")) changed = 1;

  let json = {
    id: this.$.data("page"),
    trash,
    changed,
    hidden,
  };

  // console.log(json);
  return json;
};
