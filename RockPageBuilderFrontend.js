"use strict";

// UIkit sortable feature
// this will only use when the UIkit frontend framework is used
document.addEventListener(
  "DOMContentLoaded",
  function () {
    let uk = UIkit;
    if (typeof uk == "undefined") {
      console.log("cannot use frontend sorting because UIkit was not found");
      return;
    }

    // this site uses uikit framework so we can use its sortable component
    console.log("UIkit found - using extended RockPageBuilder features :)");
    let util = uk.util;

    let sortable = uk.sortable(".rpb-sortable");
    util.on(document, "moved", ".rpb-sortable", function (e) {
      let container = e.target; // container with all items
      let moved = e.detail[1]; // moved item
      let alfred = util.$(".alfred", moved);

      // get blockid from the first alfred markup that it can find
      let blockid = util.data(alfred, "rpbblock");

      // get page and field where this block lives on
      let page = util.data(container, "page");
      let field = util.data(container, "field");

      // send ajax request to save new sort order
      console.log("todo: send ajax to save new sort");
      // util.ajax('/api/users', { responseType: 'json' })
      //   .then(function(xhr) {
      //     console.log(xhr.response);
      //   });
    });
  },
  false
);

console.log("loaded");
