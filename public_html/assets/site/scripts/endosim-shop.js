(function () {
    "use strict";

    var CATEGORY = "endodontic_tools";
    var CART_KEY = "dent1402_buy_cart_items";
    var RETIRED_PREFIX = "endosim-";
    var state = {
        items: [],
        query: "",
        filter: "all"
    };

    function $(id) {
        return document.getElementById(id);
    }

    function text(value) {
        return String(value == null ? "" : value).replace(/[&<>"]/g, function (char) {
            if (char === "&") return "&amp;";
            if (char === "<") return "&lt;";
            if (char === ">") return "&gt;";
            if (char.charCodeAt(0) === 34) return "&quot;";
            return char;
        });
    }

    function faNumber(value) {
        return Number(value || 0).toLocaleString("fa-IR");
    }

    function moneyToman(rial) {
        return Math.round(Math.max(0, Number(rial) || 0) / 10).toLocaleString("fa-IR") + " تومان";
    }

    function apiGet(action) {
        return fetch("/api/payments_api.php?action=" + encodeURIComponent(action), {
            method: "GET",
            credentials: "same-origin",
            headers: { Accept: "application/json" }
        }).then(function (response) {
            return response.json().catch(function () {
                return { success: false, error: "پاسخ نامعتبر از سرور دریافت شد." };
            }).then(function (payload) {
                payload.httpStatus = response.status;
                return payload;
            });
        }).catch(function () {
            return { success: false, error: "ارتباط با سرور برقرار نشد.", httpStatus: 0 };
        });
    }

    function specifications(item) {
        return Array.isArray(item && item.specifications) ? item.specifications : [];
    }

    function specValue(item, label) {
        var found = specifications(item).find(function (row) {
            return String(row && row.label || "") === label;
        });
        return found ? String(found.value || "") : "";
    }

    function itemKind(item) {
        return specValue(item, "دسته") || "ابزار";
    }

    function itemSearchText(item) {
        return [
            item && item.title,
            item && item.shortDescription,
            item && item.fullDescription,
            specifications(item).map(function (row) {
                return String(row.label || "") + " " + String(row.value || "");
            }).join(" ")
        ].join(" ").toLowerCase();
    }

    function readCart() {
        try {
            var parsed = JSON.parse(window.localStorage.getItem(CART_KEY) || "[]");
            if (!Array.isArray(parsed)) return [];
            var changed = false;
            var items = parsed.map(function (entry) {
                var slug = String(entry && entry.slug || "").trim();
                var quantity = Math.max(1, Math.min(99, Number(entry && entry.quantity) || 1));
                if (slug.indexOf(RETIRED_PREFIX) === 0) {
                    changed = true;
                    return null;
                }
                return {
                    slug: slug,
                    quantity: quantity,
                    addedAt: String(entry && entry.addedAt || "")
                };
            }).filter(function (entry) {
                return entry && entry.slug;
            });
            if (changed) {
                window.localStorage.setItem(CART_KEY, JSON.stringify(items));
            }
            return items;
        } catch (_error) {
            return [];
        }
    }

    function writeCart(items) {
        try {
            window.localStorage.setItem(CART_KEY, JSON.stringify(items || []));
        } catch (_error) {
            // Checkout remains available from the generic item page if storage is unavailable.
        }
    }

    function quantityFor(slug) {
        var entry = readCart().find(function (row) {
            return row.slug === slug;
        });
        return entry ? Number(entry.quantity || 0) : 0;
    }

    function setQuantity(slug, quantity) {
        var clean = String(slug || "").trim();
        if (!clean) return;
        var next = Math.max(0, Math.min(99, Number(quantity) || 0));
        var rows = readCart().filter(function (entry) {
            return entry.slug !== clean;
        });
        if (next > 0) {
            rows.unshift({
                slug: clean,
                quantity: next,
                addedAt: new Date().toISOString()
            });
        }
        writeCart(rows);
        syncCard(clean);
        renderCartDock();
    }

    function visibleItems() {
        var query = String(state.query || "").trim().toLowerCase();
        return state.items.filter(function (item) {
            if (state.filter !== "all" && itemKind(item) !== state.filter) {
                return false;
            }
            if (query && itemSearchText(item).indexOf(query) < 0) {
                return false;
            }
            return true;
        });
    }

    function tagValues(item) {
        var values = [];
        var kind = itemKind(item);
        var model = specValue(item, "مدل") || specValue(item, "رده") || specValue(item, "ساختار");
        var pack = specValue(item, "بسته");
        [kind, model, pack].forEach(function (value) {
            var clean = String(value || "").trim();
            if (clean && values.indexOf(clean) < 0) values.push(clean);
        });
        return values.slice(0, 3);
    }

    function renderCard(item) {
        var slug = String(item.slug || "");
        var qty = quantityFor(slug);
        var image = String(item.heroImage || "").trim();
        var tags = tagValues(item).map(function (value) {
            return '<span class="endo-tools-tag">' + text(value) + "</span>";
        }).join("");
        var code = specValue(item, "کد مرجع");
        var size = specValue(item, "سایز");
        var detail = [code ? ("کد " + code) : "", size ? ("سایز " + size) : ""].filter(Boolean).join(" · ");
        var itemUrl = "/buy/item/?slug=" + encodeURIComponent(slug);

        return [
            '<article class="buy-item-card endo-tools-card' + (qty > 0 ? " is-selected" : "") + '" data-endosim-card="' + text(slug) + '">',
            '  <div class="buy-item-card__body">',
            '    <div class="buy-item-card__top">',
            qty > 0 ? '      <span class="buy-status is-active">در سبد</span>' : '      <span class="buy-status is-muted">قابل سفارش</span>',
            '      <span class="buy-kicker">' + text(itemKind(item)) + "</span>",
            "    </div>",
            '    <a href="' + itemUrl + '"><h3 class="buy-item-card__title">' + text(item.title || "محصول") + "</h3></a>",
            '    <p class="buy-item-card__desc">' + text(item.shortDescription || "") + "</p>",
            '    <div class="endo-tools-tags">' + tags + "</div>",
            detail ? '    <div class="endo-tools-code" dir="rtl">' + text(detail) + "</div>" : "",
            '    <div class="endo-tools-card__purchase">',
            '      <strong class="buy-item-card__price endo-tools-price">' + text(moneyToman(item.price)) + "</strong>",
            '      <div class="endo-tools-stepper" data-endosim-stepper="' + text(slug) + '">',
            '        <button type="button" data-endosim-dec aria-label="کاهش تعداد">−</button>',
            '        <span class="endo-tools-qty" aria-label="تعداد انتخاب‌شده">' + faNumber(qty) + "</span>",
            '        <button type="button" data-endosim-inc aria-label="افزایش تعداد">+</button>',
            "      </div>",
            "    </div>",
            "  </div>",
            '  <a class="buy-item-card__hero endo-tools-card__hero" href="' + itemUrl + '" aria-label="مشاهده جزئیات ' + text(item.title || "محصول") + '">',
            image
                ? '    <img src="' + text(image) + '" alt="' + text(item.title || "تصویر محصول") + '" loading="lazy">'
                : '    <span class="endo-tools-card__placeholder" aria-hidden="true"></span>',
            "  </a>",
            "</article>"
        ].join("");
    }

    function renderList() {
        var root = $("endosim-grid");
        var status = $("endosim-status");
        if (!root || !status) return;
        var items = visibleItems();
        status.textContent = faNumber(items.length) + " محصول";
        if (!items.length) {
            root.innerHTML = '<div class="buy-empty endo-tools-empty">محصولی با این جست‌وجو یا فیلتر پیدا نشد.</div>';
            return;
        }
        root.innerHTML = items.map(renderCard).join("");
    }

    function syncCard(slug) {
        var card = document.querySelector('[data-endosim-card="' + cssEscape(slug) + '"]');
        if (!card) return;
        var qty = quantityFor(slug);
        card.classList.toggle("is-selected", qty > 0);
        var qtyNode = card.querySelector(".endo-tools-qty");
        if (qtyNode) qtyNode.textContent = faNumber(qty);
        var stateNode = card.querySelector(".buy-status");
        if (stateNode) {
            stateNode.textContent = qty > 0 ? "در سبد" : "قابل سفارش";
            stateNode.className = "buy-status " + (qty > 0 ? "is-active" : "is-muted");
        }
    }

    function renderCartDock() {
        var dock = $("endosim-cart-dock");
        if (!dock) return;
        var bySlug = {};
        state.items.forEach(function (item) {
            bySlug[String(item.slug || "")] = item;
        });
        var count = 0;
        var total = 0;
        readCart().forEach(function (entry) {
            var item = bySlug[entry.slug];
            if (!item) return;
            count += Number(entry.quantity || 0);
            total += Number(item.price || 0) * Number(entry.quantity || 0);
        });
        dock.hidden = count <= 0;
        $("endosim-cart-count").textContent = faNumber(count) + " عدد انتخاب شده";
        $("endosim-cart-total").textContent = moneyToman(total);
    }

    function cssEscape(value) {
        if (window.CSS && typeof window.CSS.escape === "function") {
            return window.CSS.escape(String(value || ""));
        }
        return String(value || "").replace(/["\\]/g, "\\$&");
    }

    function showLoginRequired() {
        var main = document.querySelector("main.buy-shell");
        if (!main || !window.Dent1402Auth || typeof window.Dent1402Auth.renderLoginRequiredGuard !== "function") {
            return;
        }
        var auth = window.Dent1402Auth;
        var loginUrl = typeof auth.loginUrl === "function"
            ? auth.loginUrl(window.location.pathname + window.location.search + window.location.hash)
            : "/account/";
        main.innerHTML = '<section class="buy-auth-required">' + auth.renderLoginRequiredGuard({
            loginHref: loginUrl,
            fallbackHref: "/buy/",
            primaryClass: "buy-primary-btn",
            secondaryClass: "buy-secondary-btn"
        }) + "</section>";
        if (typeof auth.enhanceLoginGuards === "function") {
            auth.enhanceLoginGuards(main);
        }
    }

    function bindControls() {
        var grid = $("endosim-grid");
        if (grid) {
            grid.addEventListener("error", function (event) {
                var image = event.target;
                if (!image || image.tagName !== "IMG" || !image.closest(".endo-tools-card__hero")) {
                    return;
                }
                var placeholder = document.createElement("span");
                placeholder.className = "endo-tools-card__placeholder";
                placeholder.setAttribute("aria-hidden", "true");
                image.replaceWith(placeholder);
            }, true);

            grid.addEventListener("click", function (event) {
                var stepper = event.target.closest("[data-endosim-stepper]");
                if (!stepper) return;
                var slug = stepper.getAttribute("data-endosim-stepper") || "";
                var current = quantityFor(slug);
                if (event.target.closest("[data-endosim-inc]")) {
                    setQuantity(slug, current + 1);
                } else if (event.target.closest("[data-endosim-dec]")) {
                    setQuantity(slug, current - 1);
                }
            });
        }

        var search = $("endosim-search");
        var clear = $("endosim-clear-search");
        if (search) {
            search.addEventListener("input", function () {
                state.query = search.value || "";
                if (clear) clear.hidden = !state.query;
                renderList();
            });
        }
        if (clear) {
            clear.addEventListener("click", function () {
                state.query = "";
                if (search) {
                    search.value = "";
                    search.focus();
                }
                clear.hidden = true;
                renderList();
            });
        }

        var filters = $("endosim-filters");
        if (filters) {
            filters.addEventListener("click", function (event) {
                var button = event.target.closest("[data-endosim-filter]");
                if (!button) return;
                state.filter = button.getAttribute("data-endosim-filter") || "all";
                Array.prototype.slice.call(filters.querySelectorAll("[data-endosim-filter]")).forEach(function (node) {
                    node.classList.toggle("is-active", node === button);
                });
                renderList();
            });
        }

        window.addEventListener("storage", function (event) {
            if (event.key !== CART_KEY) return;
            renderList();
            renderCartDock();
        });
    }

    function init() {
        bindControls();
        apiGet("listPublicItems").then(function (payload) {
            if (!payload || !payload.success) {
                $("endosim-status").textContent = "بارگذاری انجام نشد";
                $("endosim-grid").innerHTML = '<div class="buy-empty endo-tools-empty">' +
                    text(payload && payload.error || "دریافت فهرست محصولات انجام نشد.") +
                    "</div>";
                return;
            }
            state.items = (Array.isArray(payload.items) ? payload.items : []).filter(function (item) {
                return String(item && item.category || "") === CATEGORY;
            });
            renderList();
            renderCartDock();
        });
    }

    var auth = window.Dent1402Auth;
    if (!auth || typeof auth.ready !== "function") {
        init();
        return;
    }
    auth.ready().then(function (detail) {
        if (!detail || !detail.loggedIn) {
            showLoginRequired();
            return;
        }
        init();
    }).catch(showLoginRequired);
})();
