/*
 * Self-hosted Hero V2 appearance editor.
 *
 * The renderer composes the appearance from the local GPack canvas atlases
 * (the same format the reference editor uses); the editor drives the
 * reference `.appearanceWrapper` markup rendered by the server and talks to
 * the existing `heroEditor` API command. It never fetches a third-party asset.
 */
(function (global) {
  "use strict";

  var SOURCE_WIDTH = 875;
  var SOURCE_HEIGHT = 1574;
  var HAIR_COLORS = {
    1500: "black",
    1501: "brown",
    1502: "brightbrown",
    1503: "blonde",
    1504: "red",
    1505: "grey",
    1506: "white",
    1507: "violet",
    1508: "signalred",
    1509: "blondepink",
    1510: "blondered",
    1511: "redblack",
  };
  var TRIBES = {
    1: "roman",
    2: "teuton",
    3: "gaul",
    6: "egyptian",
    7: "hun",
  };
  var ATTRIBUTE_STARTS = {
    male: {
      headProfile: 50,
      hairStyle: 1000,
      ears: 2000,
      nose: 3000,
      eyes: 4000,
      beard: 5000,
      mouth: 6000,
      eyebrow: 7000,
      scar: 8001,
      tattoo: 9001,
    },
    female: {
      headProfile: 51,
      hairStyle: 1000,
      ears: 2000,
      nose: 3000,
      eyes: 4000,
      mouth: 6000,
      eyebrow: 7000,
      scar: 8001,
      tattoo: 9001,
    },
  };

  /* The persisted value of the Empty tile (no beard, scar or tattoo). */
  var NONE_VALUES = { beard: 5999, scar: 8000, tattoo: 9000 };

  /* The attribute value an option tile stands for: a number maps onto the category range, "empty" onto its none value. */
  function optionValueFor(gender, attributeName, raw) {
    if (raw === "empty") {
      return typeof NONE_VALUES[attributeName] === "number" ? NONE_VALUES[attributeName] : null;
    }
    var number = exactInteger(raw, NaN);
    var start = ATTRIBUTE_STARTS[gender][attributeName];
    return Number.isInteger(number) && typeof start === "number" ? start + number - 1 : null;
  }

  function loadImage(url) {
    return new Promise(function (resolve, reject) {
      var image = new Image();
      image.onload = function () {
        resolve(image);
      };
      image.onerror = function () {
        reject(new Error("Unable to load hero texture: " + url));
      };
      image.src = url;
    });
  }

  function exactInteger(value, fallback) {
    var parsed = Number(value);
    return Number.isInteger(parsed) ? parsed : fallback;
  }

  function HeroV2AppearanceRenderer(options) {
    this.canvas = options.canvas;
    this.assetBase = options.assetBase.replace(/\/?$/, "/");
    this.race = exactInteger(options.race, 1);
    this.cache = {};
    this.renderVersion = 0;
  }

  HeroV2AppearanceRenderer.prototype.texture = function (
    gender,
    category,
    value,
  ) {
    var key =
      value === ""
        ? [gender, category].join("-")
        : [gender, category, value].join("-");
    if (!this.cache[key]) {
      var base = this.assetBase + "textures/hero/" + key;
      this.cache[key] = Promise.all([
        fetch(base + ".json").then(function (response) {
          if (!response.ok) {
            throw new Error(
              "Unable to load hero atlas metadata: " + response.status,
            );
          }
          return response.json();
        }),
        loadImage(base + ".png"),
      ]).then(function (result) {
        if (!result[0] || !result[0].frames || !result[0].meta) {
          throw new Error("Hero atlas metadata is incomplete.");
        }
        return { data: result[0], image: result[1] };
      });
    }
    return this.cache[key];
  };

  HeroV2AppearanceRenderer.prototype.attributeIndex = function (
    attributes,
    name,
  ) {
    var gender = attributes.gender === "female" ? "female" : "male";
    var start = ATTRIBUTE_STARTS[gender][name];
    if (typeof start !== "number") {
      return 1;
    }
    return Math.max(1, exactInteger(attributes[name], start) - start + 1);
  };

  HeroV2AppearanceRenderer.prototype.optionalAttributeIndex = function (
    attributes,
    name,
    none,
  ) {
    return exactInteger(attributes[name], none) === none
      ? null
      : this.attributeIndex(attributes, name);
  };

  HeroV2AppearanceRenderer.prototype.appearance = function (attributes) {
    var gender = attributes.gender === "female" ? "female" : "male";
    var hairColor =
      HAIR_COLORS[exactInteger(attributes.hairColor, 1501)] || "brown";
    return {
      gender: gender,
      skinColor:
        typeof attributes.skinColor === "string"
          ? attributes.skinColor
          : "skin1",
      eyeColor:
        typeof attributes.eyeColor === "string" ? attributes.eyeColor : "blue",
      hairColor: hairColor,
      tribeColor: TRIBES[this.race] || "roman",
      jaw: this.attributeIndex(attributes, "headProfile"),
      eyes: this.attributeIndex(attributes, "eyes"),
      brows: this.attributeIndex(attributes, "eyebrow"),
      nose: this.attributeIndex(attributes, "nose"),
      mouth: this.attributeIndex(attributes, "mouth"),
      ears: this.attributeIndex(attributes, "ears"),
      hair:
        exactInteger(attributes.hairStyle, 1499) === 1499
          ? null
          : this.attributeIndex(attributes, "hairStyle"),
      beard:
        gender === "male" && exactInteger(attributes.beard, 5999) !== 5999
          ? this.attributeIndex(attributes, "beard")
          : null,
      scar: this.optionalAttributeIndex(attributes, "scar", 8000),
      tattoo: this.optionalAttributeIndex(attributes, "tattoo", 9000),
    };
  };

  HeroV2AppearanceRenderer.prototype.drawFrame = function (
    context,
    texture,
    name,
    optional,
  ) {
    var frame = texture.data.frames[name];
    if (!frame) {
      if (optional) {
        return;
      }
      throw new Error("Hero atlas frame is missing: " + name);
    }
    var packed = frame.frame;
    var target = frame.spriteSourceSize;
    if (!frame.rotated) {
      context.drawImage(
        texture.image,
        packed.x,
        packed.y,
        packed.w,
        packed.h,
        target.x,
        target.y,
        target.w,
        target.h,
      );
      return;
    }

    // TexturePacker rotates atlas entries clockwise. Restore the original
    // source rectangle before placing its trimmed region on the 875×1574
    // hero canvas.
    context.save();
    context.translate(target.x, target.y + target.h);
    context.rotate(-Math.PI / 2);
    context.drawImage(
      texture.image,
      packed.x,
      packed.y,
      packed.h,
      packed.w,
      0,
      0,
      target.h,
      target.w,
    );
    context.restore();
  };

  HeroV2AppearanceRenderer.prototype.drawComposite = function (
    canvas,
    attributes,
  ) {
    var self = this;
    var state = this.appearance(attributes);
    return Promise.all([
      this.texture(state.gender, "skinColor", state.skinColor),
      this.texture(state.gender, "hairColor", state.hairColor),
      this.texture(state.gender, "eyeColor", state.eyeColor),
      this.texture(state.gender, "tribeColor", state.tribeColor),
      this.texture(state.gender, "tattoo", ""),
    ]).then(function (textures) {
      var skin = textures[0];
      var hair = textures[1];
      var eye = textures[2];
      var tribe = textures[3];
      var tattoo = textures[4];
      var prefix = state.gender + "/";
      var skinPrefix = prefix + "skinColor/" + state.skinColor + "/";
      var hairPrefix = prefix + "hairColor/" + state.hairColor + "/";
      var eyePrefix = prefix + "eyeColor/" + state.eyeColor + "/";
      var tribePrefix = prefix + "tribeColor/" + state.tribeColor + "/";
      var context = canvas.getContext("2d");

      canvas.width = SOURCE_WIDTH;
      canvas.height = SOURCE_HEIGHT;
      context.clearRect(0, 0, SOURCE_WIDTH, SOURCE_HEIGHT);

      var layers = [
        [skin, skinPrefix + "base/base-" + state.skinColor],
        [skin, skinPrefix + "leftArm/leftArmDefault-" + state.skinColor],
        [skin, skinPrefix + "rightArm/rightArmDefault-" + state.skinColor],
        [tribe, tribePrefix + "body/body-" + state.tribeColor],
        [skin, skinPrefix + "ears/ears" + state.ears + "-" + state.skinColor],
        [skin, skinPrefix + "jaw/jaw" + state.jaw + "-" + state.skinColor],
        [
          skin,
          skinPrefix + "eyesBase/eyesBase" + state.eyes + "-" + state.skinColor,
        ],
        [eye, eyePrefix + "eyes/eyes" + state.eyes + "-" + state.eyeColor],
        [
          skin,
          skinPrefix + "mouth/mouth" + state.mouth + "-" + state.skinColor,
        ],
        [skin, skinPrefix + "nose/nose" + state.nose + "-" + state.skinColor],
        [
          skin,
          skinPrefix +
            "browsBase/browsBase" +
            state.brows +
            "-" +
            state.skinColor,
        ],
        [
          hair,
          hairPrefix + "brows/brows" + state.brows + "-" + state.hairColor,
        ],
      ];
      if (state.scar !== null) {
        layers.push([
          skin,
          skinPrefix + "scar/scar" + state.scar + "-" + state.skinColor,
          true,
        ]);
      }
      if (state.hair !== null) {
        // A few valid modern hair styles intentionally omit a back
        // layer. They render with their front layer only.
        layers.unshift([
          hair,
          hairPrefix + "backHair/backHair" + state.hair + "-" + state.hairColor,
          true,
        ]);
        layers.push([
          hair,
          hairPrefix +
            "frontHair/frontHair" +
            state.hair +
            "-" +
            state.hairColor,
        ]);
      }
      layers.forEach(function (layer) {
        self.drawFrame(context, layer[0], layer[1], layer[2] === true);
      });
      if (state.beard !== null) {
        self.drawFrame(
          context,
          hair,
          hairPrefix + "beard/beard" + state.beard + "-" + state.hairColor,
          true,
        );
      }
      if (state.tattoo !== null) {
        self.drawFrame(
          context,
          tattoo,
          prefix + "tattoo/tattoo" + state.tattoo,
          true,
        );
      }
    });
  };

  HeroV2AppearanceRenderer.prototype.render = function (attributes) {
    var self = this;
    var renderVersion = ++this.renderVersion;
    return this.drawComposite(this.canvas, attributes)
      .then(function () {
        if (renderVersion !== self.renderVersion) {
          return false;
        }
        self.canvas.hidden = false;
        return true;
      })
      .catch(function (error) {
        global.console.error("Hero V2 appearance rendering failed:", error);
        return false;
      });
  };

  HeroV2AppearanceRenderer.prototype.renderOptions = function (
    options,
    attributeName,
    attributes,
  ) {
    var self = this;
    Array.prototype.forEach.call(options, function (option) {
      var canvas = option.querySelector(".optionPreview canvas");
      var gender = attributes.gender === "female" ? "female" : "male";
      var value = optionValueFor(gender, attributeName, option.getAttribute("data-option"));
      if (!canvas || value === null) {
        return;
      }
      var optionAttributes = Object.assign({}, attributes);
      optionAttributes[attributeName] = value;
      self.drawComposite(canvas, optionAttributes).catch(function (error) {
        global.console.warn("Hero V2 appearance option failed:", error);
      });
    });
  };

  global.HeroV2AppearanceRenderer = HeroV2AppearanceRenderer;

  /* Reference category class => persisted appearance attribute. */
  var CATEGORY_ATTRIBUTES = {
    jaw: "headProfile",
    eyes: "eyes",
    brows: "eyebrow",
    nose: "nose",
    mouth: "mouth",
    ears: "ears",
    hair: "hairStyle",
    beard: "beard",
    scar: "scar",
    tattoo: "tattoo",
  };
  /* Which colour palette accompanies a category (default: skin colour). */
  var CATEGORY_PALETTES = {
    eyes: "eyeColor",
    brows: "hairColor",
    hair: "hairColor",
    beard: "hairColor",
  };
  var CATEGORY_BEARD = '<div class="category beard" data-category="beard"><i></i></div>';

  function escapeAttribute(value) {
    return String(value).replace(/[&<>"']/g, function (character) {
      return (
        { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[
          character
        ]
      );
    });
  }

  function HeroAppearanceEditor(root, options) {
    this.root = root;
    this.options = options;
    this.attributes = Object.assign({}, options.attributes);
    this.saved = Object.assign({}, options.attributes);
    this.unloadIdentifier = null;
    this.activeIndex = 0;
    this.pageMarkup = Array.prototype.map.call(this.pages(), function (page) {
      return page.outerHTML;
    });
    this.mountActivePage();
    this.renderer = new HeroV2AppearanceRenderer({
      canvas: root.querySelector("#heroAppearancePreview"),
      assetBase: options.assetBase,
      race: options.race,
    });
    this.bind();
    this.sync();
  }

  HeroAppearanceEditor.prototype.bind = function () {
    var self = this;
    this.root.addEventListener("click", function (event) {
      var target = event.target;
      var control = target.closest(
        ".category, .option, .color, button.toggleGender, button.shuffleConfig, " +
          "button.toggleZoom, button.resetChanges, button.saveChanges, button.buyOptions",
      );
      if (!control || !self.root.contains(control)) {
        return;
      }
      event.preventDefault();
      if (control.classList.contains("category")) {
        self.selectCategory(self.indexOf(control, ".categories .category"));
      } else if (control.classList.contains("option")) {
        self.selectOption(control);
      } else if (control.classList.contains("color")) {
        self.selectColor(control);
      } else if (control.classList.contains("toggleGender")) {
        self.switchGender(self.attributes.gender === "male" ? "female" : "male");
      } else if (control.classList.contains("shuffleConfig")) {
        self.request("random");
      } else if (control.classList.contains("toggleZoom")) {
        self.toggleZoom();
      } else if (
        control.disabled ||
        ((control.classList.contains("buyOptions") ||
          control.classList.contains("resetChanges") ||
          control.classList.contains("saveChanges")) &&
          control.classList.contains("disabled"))
      ) {
        return;
      } else if (control.classList.contains("resetChanges")) {
        self.attributes = Object.assign({}, self.saved);
        self.request("show");
      } else if (control.classList.contains("saveChanges")) {
        self.request("save", function () {
          self.saved = Object.assign({}, self.attributes);
        });
      } else if (control.classList.contains("buyOptions")) {
        self.request("unlock");
      }
    });
  };

  HeroAppearanceEditor.prototype.indexOf = function (element, selector) {
    return Array.prototype.indexOf.call(
      this.root.querySelectorAll(selector),
      element,
    );
  };

  HeroAppearanceEditor.prototype.categories = function () {
    return this.root.querySelectorAll(".categories .category");
  };

  HeroAppearanceEditor.prototype.pages = function () {
    return this.root.querySelectorAll(".selectionWrapper > .page");
  };

  HeroAppearanceEditor.prototype.activeCategory = function () {
    var category = this.categories()[this.activeIndex];
    return category ? category.getAttribute("data-category") : "jaw";
  };

  HeroAppearanceEditor.prototype.selectCategory = function (index) {
    if (index < 0 || index >= this.categories().length) {
      return;
    }
    this.activeIndex = index;
    this.mountActivePage();
    this.sync();
  };

  HeroAppearanceEditor.prototype.optionValue = function (attributeName, raw) {
    var gender = this.attributes.gender === "female" ? "female" : "male";
    return optionValueFor(gender, attributeName, raw);
  };

  HeroAppearanceEditor.prototype.selectOption = function (option) {
    var attributeName = CATEGORY_ATTRIBUTES[this.activeCategory()];
    var value = attributeName ? this.optionValue(attributeName, option.getAttribute("data-option")) : null;
    if (value === null) {
      return;
    }
    this.attributes[attributeName] = value;
    this.sync();
    this.request("show");
  };

  HeroAppearanceEditor.prototype.selectColor = function (color) {
    var palette = CATEGORY_PALETTES[this.activeCategory()] || "skinColor";
    var entry = this.options.palettes[palette].colors[
      this.indexOf(color, ".colorsWrapper .colors .color")
    ];
    if (!entry) {
      return;
    }
    this.attributes[palette] = entry.value;
    this.sync();
    this.request("show");
  };

  HeroAppearanceEditor.prototype.toggleZoom = function () {
    var preview = this.root.querySelector(".previewWrapper .preview");
    var zoomedIn = preview.classList.contains("zoomedIn");
    preview.classList.toggle("zoomedIn", !zoomedIn);
    preview.classList.toggle("zoomedOut", zoomedIn);
    var icon = this.root.querySelector("button.toggleZoom > svg");
    icon.classList.toggle("zoomOut", !zoomedIn);
    icon.classList.toggle("zoomIn", zoomedIn);
    var zoomButton = icon.parentNode;
    var zoomTitle = zoomedIn ? zoomButton.dataset.titleZoomIn : zoomButton.dataset.titleZoomOut;
    if (zoomButton._tippy) {
      zoomButton._tippy.setContent('<div class="title"></div><div class="text">' + zoomTitle + "</div>");
    }
  };

  HeroAppearanceEditor.prototype.applyGender = function (gender) {
    this.root
      .querySelectorAll(".previewWrapper .preview, button.toggleGender")
      .forEach(function (element) {
        element.classList.remove("male", "female");
        if (element.classList.contains("toggleGender")) {
          var classes = Array.prototype.filter.call(element.classList, function (name) {
            return name !== "male" && name !== "female";
          });
          classes.splice(classes.indexOf("toggleGender") + 1, 0, gender);
          element.className = classes.join(" ");
        } else {
          element.classList.add(gender);
        }
      });
  };

  /*
   * Switch the preview gender immediately and ask the server for a matching
   * random face; a rejected request restores the server-approved face.
   */
  HeroAppearanceEditor.prototype.switchGender = function (gender) {
    var self = this;
    var previous = Object.assign({}, this.attributes);
    this.attributes.gender = gender;
    this.applyGender(gender);
    this.request("gender", null, function () {
      self.attributes = previous;
      self.applyGender(previous.gender);
    });
  };

  HeroAppearanceEditor.prototype.request = function (action, onSuccess, onError) {
    var self = this;
    var helper = global.Travian.Form && global.Travian.Form.UnloadHelper;
    if (helper) {
      if (action === "save") {
        helper.disableSecurity(this.unloadIdentifier);
      } else {
        this.unloadIdentifier = helper.enableSecurity(this.unloadIdentifier);
      }
    }
    return global.Travian.api(this.options.command, {
      data: { action: action, attribs: this.attributes },
      success: function (response) {
        if (response.attributesHtml) {
          self.replaceMarkup(response);
        }
        self.attributes = response.attributes;
        self.sync();
        if (onSuccess) {
          onSuccess(response);
        }
      },
      error: function (error) {
        if (action === "save" && helper) {
          self.unloadIdentifier = helper.enableSecurity(self.unloadIdentifier);
        }
        if (onError) {
          onError(error);
        }
        new global.Travian.Dialog.Dialog({ preventFormSubmit: true })
          .setContent(error.message)
          .show();
        self.sync();
      },
    });
  };

  /* Swap in the pages (and category strip) the server rendered for a gender. */
  HeroAppearanceEditor.prototype.replaceMarkup = function (response) {
    var wrapper = this.root.querySelector(".selectionWrapper");
    this.pageMarkup = [];
    this.pages().forEach(function (page) {
      this.pageMarkup.push(page.outerHTML);
      page.remove();
    }, this);
    var source = document.createElement("div");
    source.innerHTML = response.attributesHtml;
    this.pageMarkup = Array.prototype.map.call(source.querySelectorAll(".page"), function (page) {
      return page.outerHTML;
    });
    this.mountActivePage();
    var hasBeard = !!this.root.querySelector(".categories .category.beard");
    var wantsBeard = response.attributes.gender === "male";
    if (wantsBeard && !hasBeard) {
      this.root
        .querySelector(".categories .category.hair")
        .insertAdjacentHTML("afterend", CATEGORY_BEARD);
    } else if (!wantsBeard && hasBeard) {
      this.root.querySelector(".categories .category.beard").remove();
    }
    this.activeIndex = Math.min(this.activeIndex, this.categories().length - 1);
    this.drawn = null;
  };

  HeroAppearanceEditor.prototype.renderPalette = function () {
    var palette = CATEGORY_PALETTES[this.activeCategory()] || "skinColor";
    var definition = this.options.palettes[palette];
    var selectedValue = String(this.attributes[palette]);
    var html = '<div class="colorPalette">' + escapeAttribute(definition.label) + "</div>";
    html += '<div class="colors ' + palette + '">';
    definition.colors.forEach(function (color) {
      var selected = String(color.value) === selectedValue;
      html +=
        '<div class="color ' + color.name + (selected ? " selected" : "") + '">' +
        '<div class="colorPreview ' + color.name + '"></div>' +
        (selected ? '<div class="selectedIndicator"></div>' : "") +
        "</div>";
    });
    this.root.querySelector(".colorsWrapper").innerHTML = html + "</div>";
  };

  /* Bring tabs, pages, selection state, palette, buttons and canvases in line. */
  HeroAppearanceEditor.prototype.sync = function () {
    var self = this;
    var active = this.activeIndex;
    this.categories().forEach(function (category, index) {
      category.classList.toggle("selected", index === active);
    });
    var lockedSelection = false;
    var page = this.activePage();
    if (page) {
      var category = self.categories()[active];
      var attributeName = category && CATEGORY_ATTRIBUTES[category.getAttribute("data-category")];
      page.querySelectorAll(".option").forEach(function (option) {
        var selected =
          !!attributeName &&
          Number(self.attributes[attributeName]) === self.optionValue(attributeName, option.getAttribute("data-option"));
        var indicator = option.querySelector(".selectedIndicator");
        option.classList.toggle("selected", selected);
        if (selected && !indicator) {
          option.insertAdjacentHTML("beforeend", '<div class="selectedIndicator"></div>');
        } else if (!selected && indicator) {
          indicator.remove();
        }
        if (selected && option.querySelector(".lockedIndicator")) {
          lockedSelection = true;
        }
      });
    }
    this.renderPalette();
    var dirty = Object.keys(this.attributes).some(function (field) {
      return self.attributes[field] !== self.saved[field];
    });
    this.setSaveDisabled(!dirty || lockedSelection);
    this.setResetDisabled(!dirty);
    this.setBuyDisabled(!(dirty && lockedSelection));
    this.applyGender(this.attributes.gender);
    this.renderer.render(this.attributes);
    this.renderActivePage();
  };

  HeroAppearanceEditor.prototype.setResetDisabled = function (disabled) {
    var button = this.root.querySelector("button.resetChanges");
    if (!button) {
      return;
    }
    button.disabled = false;
    button.classList.toggle("disabled", disabled);
  };

  HeroAppearanceEditor.prototype.setBuyDisabled = function (disabled) {
    var button = this.root.querySelector("button.buyOptions");
    button.disabled = false;
    button.classList.toggle("disabled", disabled);
  };

  HeroAppearanceEditor.prototype.setSaveDisabled = function (disabled) {
    var button = this.root.querySelector("button.saveChanges");
    if (!button) {
      return;
    }
    button.disabled = false;
    button.classList.toggle("disabled", disabled);
  };

  HeroAppearanceEditor.prototype.activePage = function () {
    return this.root.querySelector(".selectionWrapper > .page.active");
  };

  HeroAppearanceEditor.prototype.mountActivePage = function () {
    var wrapper = this.root.querySelector(".selectionWrapper");
    var markup = this.pageMarkup[this.activeIndex];
    if (!wrapper || !markup) {
      return;
    }
    wrapper.querySelectorAll(".page").forEach(function (page) {
      page.remove();
    });
    wrapper.insertAdjacentHTML("afterbegin", markup);
    var page = wrapper.querySelector(".page");
    if (page) {
      page.classList.add("active");
    }
  };

  /* Option previews follow every attribute that affects the portrait. */
  HeroAppearanceEditor.prototype.renderActivePage = function () {
    var page = this.activePage();
    var attributeName = CATEGORY_ATTRIBUTES[this.activeCategory()];
    if (!page || !attributeName) {
      return;
    }
    var others = Object.assign({}, this.attributes);
    delete others[attributeName];
    var signature = JSON.stringify([this.activeIndex, others, page.querySelectorAll(".option").length]);
    if (this.drawn === signature) {
      return;
    }
    this.drawn = signature;
    this.renderer.renderOptions(page.querySelectorAll(".option"), attributeName, this.attributes);
  };

  global.HeroAppearanceEditor = HeroAppearanceEditor;
})(window);
