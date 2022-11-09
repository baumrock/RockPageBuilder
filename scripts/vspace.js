"use strict";

RockFrontend.vspaceGUI = function (icon) {
  let show = false;
  if (icon.type == "vspacetop") show = true;
  if (icon.type == "vspacebottom") show = true;
  if (!show) return "";
  // console.log(icon);
  return (
    "<div class='vspace'>" +
    "<input type=range data-block='" +
    icon.widget +
    "' min=0 max=1" +
    " value=0.3 step=0.33 class='rpb-vspace " +
    icon.type +
    "'><div class='vspace-reset'>" +
    this.Alfred.icon("reload") +
    "</div></div>"
  );
};

document.addEventListener("AlfredReady", function (e) {
  let VSpace = function () {};

  VSpace.prototype.init = function (el) {
    this.slider = el;
    this.container = el.closest("a.icon");
    this.block = el.closest("[data-rpbblock]");
    this.blockID = this.slider.getAttribute("data-block");
    this.type = el.classList.contains("vspacetop") ? "top" : "bottom";
    this.timer = false;
    this.setValue(this.block.style.getPropertyValue("--vscale-" + this.type));
    this.addEventListeners();
    // console.log(this);
  };

  VSpace.prototype.addEventListeners = function (el) {
    let that = this;
    this.slider.addEventListener("input", function () {
      that.setValue(that.slider.value, true);
    });
    this.container.addEventListener("click", function (e) {
      let reset = e.target.closest(".vspace-reset");
      if (reset) that.reset();
    });
  };

  VSpace.prototype.reset = function () {
    this.setValue(RockFrontend.defaultVspaceScale, true);
  };

  VSpace.prototype.setValue = function (val, save) {
    this.block.style.setProperty("--vscale-" + this.type, val);
    this.slider.value = val;
    if (save) this.saveValue(val);
  };

  VSpace.prototype.saveValue = function (val) {
    let url =
      RockFrontend.rootUrl +
      "rockpagebuilder-vscale?block=" +
      this.blockID +
      "&type=" +
      this.type +
      "&value=" +
      val;

    clearTimeout(this.timer);
    this.timer = setTimeout(() => {
      fetch(url)
        .then((response) => {
          if (response.ok) {
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
    }, 500);
  };

  let roots = document.querySelectorAll(".rpb-vspace");
  // console.log(roots);
  for (let i = 0; i < roots.length; i++) {
    let vspace = new VSpace();
    vspace.init(roots[i]);
    // console.log(overlay);
  }
});
