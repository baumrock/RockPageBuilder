"use strict";

/**
 * RockPageBuilder Overlay Feature
 */
(function () {
  function Overlays() {}
  var Overlays = Overlays();

  function Overlay() {}

  Overlay.prototype.init = function (root) {
    this.id = root.getAttribute("data-id");
    this.root = root;
    this.img = root.querySelector(".rpb-img");
    this.slider = root.querySelector(".rpb-slider");
    this.layersoff = root.querySelector(".rpb-layersoff");
    this.layerson = root.querySelector(".rpb-layerson");
    this.bw = root.querySelector(".rpb-bw");
    this.color = root.querySelector(".rpb-color");
    this.addEventListeners();
    this.restore();
  };

  Overlay.prototype.addEventListeners = function () {
    let overlay = this;
    this.slider.addEventListener("input", function () {
      overlay.fade(overlay.slider.value);
    });
    this.slider.addEventListener("change", function () {
      overlay.fade(overlay.slider.value);
    });
    this.layersoff.addEventListener("click", function () {
      overlay.fade(0);
    });
    this.layerson.addEventListener("click", function () {
      overlay.fade(1);
    });
    this.bw.addEventListener("click", function () {
      overlay.bw.setAttribute("hidden", "hidden");
      overlay.color.removeAttribute("hidden");
      overlay.filter(100);
    });
    this.color.addEventListener("click", function () {
      overlay.color.setAttribute("hidden", "hidden");
      overlay.bw.removeAttribute("hidden");
      overlay.filter(0);
    });
  };

  Overlay.prototype.fade = function (opacity) {
    this.img.style.opacity = opacity;
    this.slider.value = opacity;
    this.save();
  };

  Overlay.prototype.filter = function (val) {
    this.img.style.filter = "grayscale(" + val + "%)";
    this.filterval = val;
    this.save();
  };

  Overlay.prototype.restore = function () {
    let storage = JSON.parse(localStorage.getItem("rpb-overlay-" + this.id));
    this.fade(storage.slider);
    this.filter(storage.filter);
  };

  /**
   * Save settings to localstorage
   */
  Overlay.prototype.save = function () {
    localStorage.setItem(
      "rpb-overlay-" + this.id,
      JSON.stringify({
        slider: this.slider.value,
        filter: this.filterval,
      })
    );
  };

  let roots = document.querySelectorAll(".rpb-overlay");
  for (let i = 0; i < roots.length; i++) {
    let overlay = new Overlay();
    overlay.init(roots[i]);
    console.log(overlay);
  }
})();
