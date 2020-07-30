"use strict";

function RockMatrix() {
}

// DOM helpers

  // return the field's root element
  RockMatrix.prototype.$root = function(e) {
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
    data.items = [];
    data.changedItems = [];
    $.each(RockMatrix.$items(e), function(i, el) {
      let item = RockMatrix.getItemData(el);
      data.items.push(item);

      let changedItems = $(el).find('.InputfieldStateChanged').length;
      if(changedItems) data.changedItems.push(item.id);
    });
    return data;
  }

  RockMatrix.prototype.getItemData = function(el) {
    return {
      id: $(el).data('page'),
    };
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
    this.makeSortable(e);
    this.setTextarea(e);
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
