"use strict";

function RockMatrix() {
  this.timer;
}

// DOM helpers

  // return the field's root element
  RockMatrix.prototype.$root = function(e) {
    let el = e.target; // param = event
    if(!el) el = e; // param = dom element

    // get the root inputfield element
    let $el = $(e.target); // e is an action, so get target
    return $el.closest('.InputfieldRockMatrix');
  }

  // return all item's list elements
  RockMatrix.prototype.$items = function(e) {
    return this.$root(e).find('.rm-items > ul > li.rm-item');
  }

  // return the items container
  RockMatrix.prototype.$itemsContainer = function(e) {
    return this.$root(e).find('.rm-items');
  }

  // textarea holding field data
  RockMatrix.prototype.$textarea = function(e) {
    return this.$root(e).find('textarea.rm-data');
  }

// item modifications

  RockMatrix.prototype.addItem = function(e, page) {
    let $root = this.$root(e);
    $root.trigger('changed');
  }

// helpers

  RockMatrix.prototype.getData = function(e) {
    let data = {}
    data.items = RockMatrix.getItems(e, true);
    return data;
  }

  RockMatrix.prototype.getItems = function(e, json) {
    let items = [];
    $.each(RockMatrix.$items(e), function(i, el) {
      let item = new RockMatrixItem(el);
      if(json) items.push(item.getJSON());
      else items.push(item);
    });
    return items;
  }

  RockMatrix.prototype.makeSortable = function(e) {
    let container = this.$itemsContainer(e)[0];

    // init uikit sortable on container
    UIkit.sortable(container, {
      // longer animation duration prevents flicker
      animation: 500,

      // set the handle to the header
      // this ensures that other drag&drop features don't break (eg images)
      handle: '.InputfieldHeader',
    });

    // add class to every sortable element
    // to make it addressable via css
    $.each(this.$items(e), function(i, el) {
      $(el).parent().addClass('rm-draggable');
    });
  }

  RockMatrix.prototype.setTextarea = function(e) {
    let $text = this.$textarea(e);
    $text.val(JSON.stringify(this.getData(e))).change();
  }

// event handlers

  // change triggerd
  RockMatrix.prototype.changed = function(e) {
    let rm = this;
    clearTimeout(this.timer);
    // 10ms debounce for all changes
    this.timer = setTimeout(function() {
      rm.makeSortable(e);
      rm.setTextarea(e);
      console.log('RockMatrix changed');
    }, 5);
  }

  // click on add new item button
  RockMatrix.prototype.clickAdd = function(e) {
    e.preventDefault();

    // get link
    let $a = $(e.target).closest('a');
    let href = $a.attr('href');

    // send ajax request
    $.getJSON(href, function(json) {
      if(json.error) ProcessWire.alert(json.message);
      else this.addItem(e, json.page);
    }).fail(function(json) {
      ProcessWire.alert('AJAX Error');
    });
  }

  // init
  RockMatrix.prototype.init = function(e) {
    this.$root(e).trigger('changed');
    this.$root(e).trigger('changed');
    this.$root(e).trigger('changed');
    this.$root(e).trigger('changed');
    this.$root(e).trigger('changed');
    this.$root(e).trigger('changed');
    this.$root(e).trigger('changed');
  }

var RockMatrix = new RockMatrix();

// listeners

  // init the matrix
  $(document).on('init', '.InputfieldRockMatrix', function(e) {
    RockMatrix.init(e);
  });

  // add a new matrix item
  $(document).on('click', '.InputfieldRockMatrix .rm-buttons a', function(e) {
    RockMatrix.clickAdd(e);
  });

  // change event triggered on root element
  $(document).on('changed', '.InputfieldRockMatrix', function(e) {
    RockMatrix.changed(e);
  });

  // items sort oder changed
  $(document).on('stop', '.rm-items', function(e) {
    RockMatrix.changed(e);
  });

  // monitor all inputfields in a rockmatrix field
  $(document).on('change', '.rm-items input, .rm-items textarea', function(e) {
    setTimeout(function() { RockMatrix.changed(e); });
  });

  // monitor inputfield toggles
  $(document).on('opened closed', '.Inputfield.rm-item', function(e) {
    RockMatrix.changed(e);
  });
