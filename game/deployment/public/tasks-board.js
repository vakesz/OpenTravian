/*
 * Task board (/tasks): Collect without a page load.
 *
 * Class timeline (mirrors the reference):
 *   click    reward boxes get `animated`, the button gets `disabled`
 *   success  card -> `collected`, button -> `collect collected disabled`
 *   +2000ms  `animated` removed, HUD hero bars refreshed (collect response,
 *            then hero/dataForHUD), then progressive-tasks/reload swaps the
 *            server-rendered card for the next milestone in place.
 */
(function () {
  "use strict";

  var ANIMATION_MS = 2000;
  var overviewMarkup = null;
  var detailKey = null;

  function toNumber(value) {
    var n = parseInt(value, 10);
    return isNaN(n) ? 0 : n;
  }

  function morph(selector, path) {
    var target = jQuery(selector);
    if (!target.length || !path) {
      return;
    }
    target.attr("d", path);
  }

  function applyBar(name, bar) {
    if (!bar || !bar.paths) {
      return;
    }
    var mask = name === "health" ? "#healthMask path" : "#experienceMask path";
    morph(mask, bar.paths.mask);
    morph("#topBarHero ." + name + " path.title", bar.paths.title);
    var svg = jQuery("#topBarHero ." + name);
    svg.find(".title").html("<title>" + bar.title + "</title>");
    if (name === "health") {
      var image = svg.find("image");
      var href = image.attr("xlink:href");
      if (href) {
        href = href.replace(/^(.*\/)(\w+)(\.png)$/, "$1" + bar.image + "$3");
        image.attr("href", href).attr("xlink:href", href);
      }
    }
    if (Travian.Tip && Travian.Tip.refresh) {
      Travian.Tip.refresh();
    }
  }

  function applyHeroBlock(hero) {
    if (!hero || !hero.bars) {
      return;
    }
    applyBar("experience", hero.bars.experience);
    applyBar("health", hero.bars.health);
    var levelUp = jQuery("#topBarHero .levelUp");
    levelUp.toggleClass("show", !!(hero.data && hero.data.levelUp));
  }

  function refreshHud(done) {
    Travian.api(
      "hero/dataForHUD",
      {
        success: function (hud) {
          var top = jQuery("#topBarHero");
          applyBar("experience", hud.bars.experience);
          applyBar("health", hud.bars.health);
          top.find(".levelUp").toggleClass("show", !!hud.levelUp);
          top.find(".heroStatus a").html(hud.statusInlineIcon);
          top.find(".heroImage").attr("src", hud.heroImagePath)
            .removeClass("heroImageMale heroImageFemale").addClass(hud.heroGenderImage);
          top.find("#heroImageButton").attr("title", hud.heroButtonTitle);
          if (Travian.Tip && Travian.Tip.refresh) {
            Travian.Tip.refresh();
          }
        },
        complete: done,
      },
      "GET",
    );
  }

  function activeScope() {
    return jQuery("#content").hasClass("tasksGeneral") ? "general" : "village";
  }

  function taskSurface() {
    return document.querySelector("#tasks .taskOverview, #tasks .taskDetailview");
  }

  function taskDetailTemplate(key) {
    var templates = document.querySelectorAll("#tasks template[data-task-detail-template]");
    for (var index = 0; index < templates.length; index += 1) {
      if (templates[index].getAttribute("data-task-detail-template") === key) {
        return templates[index];
      }
    }
    return null;
  }

  function elementFromHtml(html) {
    var wrapper = document.createElement("div");
    wrapper.innerHTML = String(html || "").trim();
    return wrapper.firstElementChild;
  }

  function replaceTaskSurface(html) {
    var surface = taskSurface();
    var replacement = elementFromHtml(html);
    if (!surface || !replacement) {
      return false;
    }
    surface.replaceWith(replacement);
    return true;
  }

  function rememberOverview() {
    var overview = document.querySelector("#tasks .taskOverview");
    if (overview) {
      overviewMarkup = overview.outerHTML;
    }
  }

  function showDetail(key) {
    var template = taskDetailTemplate(key);
    if (!template) {
      return;
    }
    if (detailKey === null) {
      rememberOverview();
    }
    detailKey = key;
    replaceTaskSurface(template.innerHTML);
  }

  function showOverview() {
    if (!overviewMarkup || !replaceTaskSurface(overviewMarkup)) {
      return;
    }
    detailKey = null;
  }

  function directTaskChild(overview, key) {
    var children = overview.children;
    for (var index = 0; index < children.length; index += 1) {
      if (children[index].classList.contains("task")
          && children[index].getAttribute("data-task-key") === key) {
        return children[index];
      }
    }
    return null;
  }

  function overviewAnchor(overview) {
    var children = overview.children;
    for (var index = 0; index < children.length; index += 1) {
      if (children[index].classList.contains("pagination")
          || children[index].classList.contains("divider")
          || children[index].classList.contains("rewardBonus")) {
        return children[index];
      }
    }
    return null;
  }

  function replaceOverviewRows(overview, rows, rewardBonus) {
    if (!overview) {
      return;
    }
    Object.keys(rows).forEach(function (key) {
      var row = directTaskChild(overview, key);
      var replacement = elementFromHtml(rows[key]);
      if (row && replacement) {
        row.replaceWith(replacement);
      }
    });
    var anchor = overviewAnchor(overview);
    Object.keys(rows).forEach(function (key) {
      var row = directTaskChild(overview, key);
      if (!row) {
        return;
      }
      if (anchor) {
        overview.insertBefore(row, anchor);
      } else {
        overview.appendChild(row);
      }
    });
    if (rewardBonus) {
      var bonus = overview.querySelector(".rewardBonus");
      var bonusReplacement = elementFromHtml(rewardBonus);
      if (bonus && bonusReplacement) {
        bonus.replaceWith(bonusReplacement);
      }
    }
  }

  function refreshOverviewMarkup(rows, rewardBonus) {
    if (!overviewMarkup) {
      return;
    }
    var holder = document.createElement("div");
    holder.innerHTML = overviewMarkup;
    var overview = holder.querySelector(".taskOverview");
    replaceOverviewRows(overview, rows, rewardBonus);
    if (overview) {
      overviewMarkup = overview.outerHTML;
    }
  }

  function replaceDetailTemplates(details) {
    if (!details) {
      return;
    }
    var container = document.querySelector("#tasks .taskDetailTemplates");
    Object.keys(details).forEach(function (key) {
      var template = taskDetailTemplate(key);
      if (!template && container) {
        template = document.createElement("template");
        template.setAttribute("data-task-detail-template", key);
        container.appendChild(template);
      }
      if (template) {
        template.innerHTML = details[key];
      }
    });
  }

  function reload() {
    Travian.api(
      "progressive-tasks/reload",
      {
        success: function (data) {
          var scope = activeScope();
          var rows = (data.rows && data.rows[scope]) || {};
          var details = (data.details && data.details[scope]) || {};
          replaceDetailTemplates(details);
          var overview = document.querySelector("#tasks .taskOverview");
          if (overview) {
            replaceOverviewRows(overview, rows, data.rewardBonus);
            overviewMarkup = overview.outerHTML;
          } else {
            refreshOverviewMarkup(rows, data.rewardBonus);
          }
          if (detailKey !== null) {
            showDetail(detailKey);
          }
          var claimable = ["general", "village"].some(function (scope) {
            return Object.keys((data.rows && data.rows[scope]) || {}).some(function (key) {
              return /class="task achieved /.test(data.rows[scope][key]);
            });
          });
          if (!claimable) {
            jQuery("#questmasterButton .bigSpeechBubble").remove();
          }
        },
      },
      "GET",
    );
  }

  function switchDetailPage(button) {
    var detail = button.closest(".taskDetailview");
    if (!detail) {
      return;
    }
    var cards = detail.querySelectorAll(".task[data-task-detail-page]");
    var pageCount = 0;
    cards.forEach(function (card) {
      pageCount = Math.max(pageCount, toNumber(card.getAttribute("data-task-detail-page")) + 1);
    });
    if (pageCount < 2) {
      return;
    }
    var selected = detail.querySelector(".taskDetailPagination .page.selected");
    var current = selected ? toNumber(selected.getAttribute("data-task-detail-page")) : 0;
    var target = toNumber(button.getAttribute("data-task-detail-page"));
    if (button.classList.contains("back")) {
      target = current - 1;
    } else if (button.classList.contains("forward")) {
      target = current + 1;
    }
    target = Math.max(0, Math.min(pageCount - 1, target));
    cards.forEach(function (card) {
      card.hidden = toNumber(card.getAttribute("data-task-detail-page")) !== target;
    });
    detail.querySelectorAll(".taskDetailPagination button[data-task-detail-page]").forEach(function (control) {
      if (control.classList.contains("back")) {
        control.disabled = target === 0;
        control.setAttribute("data-task-detail-page", String(Math.max(0, target - 1)));
        control.classList.toggle("disabled", control.disabled);
        return;
      }
      if (control.classList.contains("forward")) {
        control.disabled = target >= pageCount - 1;
        control.setAttribute("data-task-detail-page", String(Math.min(pageCount - 1, target + 1)));
        control.classList.toggle("disabled", control.disabled);
        return;
      }
      var controlPage = toNumber(control.getAttribute("data-task-detail-page"));
      var active = controlPage === target;
      control.disabled = active;
      control.classList.toggle("selected", active);
    });
  }

  function collect(button) {
    var row = button.closest(".task");
    var boxes = row.querySelectorAll(".iconValueBox");
    var payload = {
      questType: row.getAttribute("data-quest-type"),
      scope: row.getAttribute("data-scope"),
      targetLevel: toNumber(row.getAttribute("data-target-level")),
      heroLevel: toNumber(jQuery("#tasks .rewardBonus .heroLevel .level").first().text()),
    };
    if (row.hasAttribute("data-building-id")) {
      payload.buildingId = toNumber(row.getAttribute("data-building-id"));
    }

    boxes.forEach(function (box) {
      box.classList.add("animated");
    });
    button.classList.remove("preventAnimation");
    button.classList.add("disabled");

    Travian.api("progressive-tasks/collectReward", {
      data: payload,
      success: function (response) {
        row.classList.remove("achieved");
        row.classList.add("collected");
        button.classList.add("collected");
        button.disabled = true;
        if (response.freeGold && Travian.Game.Layout && Travian.Game.Layout.updateGold) {
          Travian.Game.Layout.updateGold();
        }
        window.setTimeout(function () {
          boxes.forEach(function (box) {
            box.classList.remove("animated");
          });
          button.classList.add("preventAnimation");
          applyHeroBlock(response.hero);
          refreshHud(reload);
        }, ANIMATION_MS);
      },
      error: function () {
        boxes.forEach(function (box) {
          box.classList.remove("animated");
        });
        button.classList.remove("disabled");
        button.classList.add("preventAnimation");
        reload();
      },
    });
  }

  document.addEventListener("click", function (event) {
    var tab = event.target.closest && event.target.closest("#tasks .contentNavi.subNavi .tabItem");
    if (tab) {
      event.preventDefault();
      var tabs = jQuery("#tasks .contentNavi.subNavi .tabItem");
      window.location.assign(tabs.index(tab) === 1 ? "/tasks?t=general" : "/tasks?t=village");
      return;
    }
    var button = event.target.closest && event.target.closest("#tasks .task button.collect");
    if (button && !button.classList.contains("disabled") && !button.disabled) {
      event.preventDefault();
      collect(button);
      return;
    }
    var backToOverview = event.target.closest && event.target.closest("#tasks .backToOverview");
    if (backToOverview) {
      event.preventDefault();
      showOverview();
      return;
    }
    var detailPage = event.target.closest && event.target.closest("#tasks .taskDetailPagination button[data-task-detail-page]");
    if (detailPage && !detailPage.disabled) {
      event.preventDefault();
      switchDetailPage(detailPage);
      return;
    }
    var group = event.target.closest && event.target.closest("#tasks .task.group[data-task-key]");
    if (group) {
      if (event.target.closest("button, a, input, label")) {
        return;
      }
      event.preventDefault();
      showDetail(group.getAttribute("data-task-key"));
      return;
    }
    var page = event.target.closest && event.target.closest("#tasks .pagination button[data-task-page-url]");
    if (page && !page.disabled) {
      window.location.assign(page.getAttribute("data-task-page-url"));
    }
  });
})();
