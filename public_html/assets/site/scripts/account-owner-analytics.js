(function () {
  "use strict";

  function create(context) {
    var accountOwnerAppearanceShortcut = context.accountOwnerAppearanceShortcut;
    var accountOwnerStatsShortcut = context.accountOwnerStatsShortcut;
    var accountRowOwnerMeta = context.accountRowOwnerMeta;
    var accountRowOwnerStatsMeta = context.accountRowOwnerStatsMeta;
    var analyticsGet = context.analyticsGet;
    var consumeUnauthorized = context.consumeUnauthorized;
    var escapeHtml = context.escapeHtml;
    var formatJalaliDateTime = context.formatJalaliDateTime;
    var hasOwnerAccess = context.hasOwnerAccess;
    var ownerAnalyticsState = context.ownerAnalyticsState;
    var ownerCanAccessStats = context.ownerCanAccessStats;
    var ownerState = context.ownerState;
    var ownerStatsCohorts = context.ownerStatsCohorts;
    var ownerStatsDownloads = context.ownerStatsDownloads;
    var ownerStatsDownloadsChart = context.ownerStatsDownloadsChart;
    var ownerStatsErrors = context.ownerStatsErrors;
    var ownerStatsErrorsMeta = context.ownerStatsErrorsMeta;
    var ownerStatsExams = context.ownerStatsExams;
    var ownerStatsFamilies = context.ownerStatsFamilies;
    var ownerStatsFeedbackMessage = context.ownerStatsFeedbackMessage;
    var ownerStatsFunnel = context.ownerStatsFunnel;
    var ownerStatsLoginsChart = context.ownerStatsLoginsChart;
    var ownerStatsMeta = context.ownerStatsMeta;
    var ownerStatsMethods = context.ownerStatsMethods;
    var ownerStatsOverview = context.ownerStatsOverview;
    var ownerStatsPages = context.ownerStatsPages;
    var ownerStatsRecentLogins = context.ownerStatsRecentLogins;
    var ownerStatsReferences = context.ownerStatsReferences;
    var ownerStatsRefreshButton = context.ownerStatsRefreshButton;
    var ownerStatsRetention = context.ownerStatsRetention;
    var ownerStatsVisitsChart = context.ownerStatsVisitsChart;
    var ownerSummary = context.ownerSummary;
    var ownerUsersInActiveCohort = context.ownerUsersInActiveCohort;

    function renderOwnerSummary(users) {
        var visibleUsers = ownerUsersInActiveCohort(users);
        var totalUsers = visibleUsers.length;
        var representatives = visibleUsers.filter(function (user) {
            return user.role === "representative" || user.role === "prosthesis_representative";
        }).length;
        var withGrades = visibleUsers.filter(function (user) {
            return user.hasGrades;
        }).length;
        var withPhone = visibleUsers.filter(function (user) {
            return !!user.hasPhone;
        }).length;
        var readyProfiles = visibleUsers.filter(function (user) {
            return !!user.hasNationalCode && !!user.hasDirectoryPhone;
        }).length;

        ownerSummary.innerHTML = [
            summaryCard("کاربر", totalUsers.toLocaleString("fa-IR"), "کل حساب‌های همین ورودی"),
            summaryCard("نماینده", representatives.toLocaleString("fa-IR"), "دسترسی مدیریتی فعال در این ورودی"),
            summaryCard("ورود پیامکی", withPhone.toLocaleString("fa-IR"), "شماره تاییدشده برای login"),
            summaryCard("پروفایل کامل", readyProfiles.toLocaleString("fa-IR"), "دارای کدملی و تلفن تماس"),
            summaryCard("کارنامه", withGrades.toLocaleString("fa-IR"), "رکورد نمره برای حداقل یک درس", withGrades > 0 ? "ok" : "warn")
        ].join("");

        if (accountRowOwnerMeta) {
            accountRowOwnerMeta.textContent = [
                "کاربر " + totalUsers.toLocaleString("fa-IR"),
                "نماینده " + representatives.toLocaleString("fa-IR"),
                "شماره " + withPhone.toLocaleString("fa-IR"),
                "پروفایل کامل " + readyProfiles.toLocaleString("fa-IR"),
                "درس " + ownerState.gradeCourses.length.toLocaleString("fa-IR")
            ].join(" \u2022 ");
        }
    }

    function summaryCard(label, value, meta, tone) {
        var toneClass = String(tone || "").trim();
        return [
            '<article class="owner-summary-card' + (toneClass ? (" owner-summary-card--" + toneClass) : "") + '">',
            '  <span>' + label + "</span>",
            '  <strong>' + value + "</strong>",
            '  <small>' + meta + "</small>",
            "</article>"
        ].join("");
    }

    function ownerStatsMetric(value) {
        return Math.max(0, Number(value || 0)).toLocaleString("fa-IR");
    }

    function syncOwnerStatsShortcut() {
        if (accountOwnerAppearanceShortcut) {
            accountOwnerAppearanceShortcut.hidden = !hasOwnerAccess();
        }
        if (accountOwnerStatsShortcut) {
            accountOwnerStatsShortcut.hidden = !hasOwnerAccess();
        }
        if (!accountRowOwnerStatsMeta) {
            return;
        }
        if (!hasOwnerAccess()) {
            accountRowOwnerStatsMeta.textContent = "بازدیدها، ورودها، دانلودها و نمودارهای مدیریتی کل سایت";
            return;
        }
        var totals = ownerAnalyticsState.dashboard && ownerAnalyticsState.dashboard.totals ? ownerAnalyticsState.dashboard.totals : null;
        if (totals) {
            accountRowOwnerStatsMeta.textContent = [
                "بازدید ۳۰ روز " + ownerStatsMetric(totals.pageViews30d),
                "ورود ۳۰ روز " + ownerStatsMetric(totals.logins30d),
                "کاربر " + ownerStatsMetric(totals.totalUsers)
            ].join(" • ");
            return;
        }
        if (ownerAnalyticsState.loading) {
            accountRowOwnerStatsMeta.textContent = "در حال آماده‌سازی snapshot آمار سایت...";
            return;
        }
        accountRowOwnerStatsMeta.textContent = "بازدیدها، ورودها، دانلودها و نمودارهای مدیریتی کل سایت";
    }

    function ownerStatsEmptyMarkup(text) {
        return '<div class="owner-stats-empty">' + escapeHtml(text || "داده‌ای برای نمایش وجود ندارد.") + "</div>";
    }

    function ownerStatsShortPath(value) {
        var text = String(value || "").trim();
        if (text.length <= 54) {
            return text;
        }
        return text.slice(0, 26) + "…" + text.slice(-24);
    }

    function renderOwnerStatsOverview(dashboard) {
        if (!ownerStatsOverview) {
            return;
        }
        if (!dashboard || !dashboard.totals) {
            ownerStatsOverview.innerHTML = ownerStatsEmptyMarkup("هنوز آماری برای نمایش ثبت نشده است.");
            return;
        }

        var totals = dashboard.totals || {};
        var seg = dashboard.segments || {};
        var human = seg.human || {};
        var owner = seg.owner || {};
        var bot = seg.bot || {};
        ownerStatsOverview.innerHTML = [
            summaryCard("کل کاربران", ownerStatsMetric(totals.totalUsers), "تعداد فعلی حساب‌های ثبت‌شده در کل سایت", "ok"),
            summaryCard("بازدید کاربران واقعی", ownerStatsMetric(human.pageViews || 0), "بدون احتساب مالک و هوش مصنوعی/ربات‌ها", (human.pageViews || 0) > 0 ? "ok" : ""),
            summaryCard("بازدید مالک", ownerStatsMetric(owner.pageViews || 0), "بازدیدهای حساب مالک (شامل کار هوش مصنوعی با حساب مالک)"),
            summaryCard("بازدید هوش مصنوعی/ربات", ownerStatsMetric(bot.pageViews || 0), "ربات‌ها، خزنده‌ها و ابزارهای هوش مصنوعی", "warn"),
            summaryCard("ورود کاربران واقعی", ownerStatsMetric(human.logins || 0), "ورودهای دانشجویان واقعی (بدون مالک/ربات)", (human.logins || 0) > 0 ? "ok" : ""),
            summaryCard("ورود مالک", ownerStatsMetric(owner.logins || 0), "ورودهای ثبت‌شده با حساب مالک"),
            summaryCard("دانلود کاربران واقعی", ownerStatsMetric(human.downloads || 0), "دانلودهای دانشجویان واقعی (بدون مالک/ربات)"),
            summaryCard("بازدید امروز (کل)", ownerStatsMetric(totals.pageViewsToday), "همه بازدیدها از ابتدای امروز شامل مالک/ربات", totals.pageViewsToday > 0 ? "ok" : ""),
            summaryCard("بازدید ۳۰ روز (کل)", ownerStatsMetric(totals.pageViews30d), "مجموع همه بازدیدهای ۳۰ روز اخیر شامل مالک/ربات"),
            summaryCard("ورود ۳۰ روز (کل)", ownerStatsMetric(totals.logins30d), "مجموع همه loginهای موفق در ۳۰ روز اخیر"),
            summaryCard("دانلود ۳۰ روز (کل)", ownerStatsMetric(totals.downloads30d), "همه کلیک‌های دانلود ۳۰ روز اخیر", totals.downloads30d > 0 ? "ok" : ""),
            summaryCard("بازدیدکننده یکتا", ownerStatsMetric(totals.uniqueVisitors30d), "تعداد visitor یکتای ۳۰ روز اخیر"),
            summaryCard("فایل‌سنتر / HTML", ownerStatsMetric((totals.contentToolsDownloads || 0) + (totals.htmlPageViews || 0)), "دانلودهای فایل‌سنتر + بازدید صفحه‌های HTML uploader", "warn")
        ].join("");
    }

    function renderOwnerStatsChart(node, series, fallbackText) {
        if (!node) {
            return;
        }
        var points = Array.isArray(series) ? series : [];
        if (!points.length) {
            node.innerHTML = ownerStatsEmptyMarkup(fallbackText || "آماری برای این بازه وجود ندارد.");
            return;
        }

        var maxValue = 0;
        var peakIndex = 0;
        var lastActiveIndex = -1;
        points.forEach(function (item) {
            var value = Math.max(0, Number(item && item.value || 0));
            if (value >= maxValue) {
                maxValue = value;
                peakIndex = points.indexOf(item);
            }
            if (value > 0) {
                lastActiveIndex = points.indexOf(item);
            }
        });
        if (!maxValue) {
            node.innerHTML = ownerStatsEmptyMarkup(fallbackText || "در این بازه هنوز مقداری ثبت نشده است.");
            return;
        }

        var lastIndex = Math.max(0, points.length - 1);
        var labelStep = points.length <= 7 ? 1 : (points.length <= 10 ? 2 : 3);

        node.innerHTML = [
            '<div class="owner-stats-chart__bars">',
            points.map(function (item, index) {
                var value = Math.max(0, Number(item && item.value || 0));
                var ratio = value > 0 && maxValue > 0 ? Math.max(10, Math.round((value / maxValue) * 100)) : 0;
                var isFocus = index === peakIndex || (lastActiveIndex >= 0 && index === lastActiveIndex);
                var showValue = value > 0 && (isFocus || value / maxValue >= 0.38);
                var showTick = index === 0 || index === lastIndex || index === peakIndex || index % labelStep === 0;
                var rawLabel = String(item && item.label || "").trim();
                var tickLabel = rawLabel;
                if (rawLabel.indexOf("/") >= 0) {
                    var segments = rawLabel.split("/");
                    tickLabel = String(segments[segments.length - 1] || rawLabel).trim();
                }
                return [
                    '<div class="owner-stats-chart__item' + (isFocus ? " owner-stats-chart__item--focus" : "") + (value <= 0 ? " owner-stats-chart__item--empty" : "") + '" title="' + escapeHtml(String(item.fullLabel || item.label || "")) + " • " + escapeHtml(ownerStatsMetric(value)) + '">',
                    '  <span class="owner-stats-chart__value' + (showValue ? "" : " owner-stats-chart__value--ghost") + '">' + (showValue ? escapeHtml(ownerStatsMetric(value)) : "&nbsp;") + '</span>',
                    '  <span class="owner-stats-chart__bar"><i style="height:' + ratio + '%"></i></span>',
                    '  <small class="owner-stats-chart__tick' + (showTick ? " is-visible" : "") + '">' + escapeHtml(tickLabel) + '</small>',
                    "</div>"
                ].join("");
            }).join(""),
            "</div>"
        ].join("");
    }

    function renderOwnerStatsBars(node, items, valueKey, emptyText) {
        if (!node) {
            return;
        }
        var rows = Array.isArray(items) ? items.filter(function (item) {
            return item && Number(item[valueKey] || 0) > 0;
        }) : [];
        if (!rows.length) {
            node.innerHTML = ownerStatsEmptyMarkup(emptyText || "داده‌ای برای این بخش وجود ندارد.");
            return;
        }

        var maxValue = 0;
        rows.forEach(function (item) {
            maxValue = Math.max(maxValue, Math.max(0, Number(item[valueKey] || 0)));
        });

        node.innerHTML = rows.map(function (item) {
            var value = Math.max(0, Number(item[valueKey] || 0));
            var ratio = maxValue > 0 ? Math.max(6, Math.round((value / maxValue) * 100)) : 0;
            return [
                '<div class="owner-stats-bar-row">',
                '  <div class="owner-stats-bar-row__top">',
                '    <strong>' + escapeHtml(String(item.label || item.title || item.key || "بدون عنوان")) + '</strong>',
                '    <span>' + escapeHtml(ownerStatsMetric(value)) + '</span>',
                "  </div>",
                '  <div class="owner-stats-bar-row__track"><i style="width:' + ratio + '%"></i></div>',
                '  <small>' + escapeHtml(String(item.meta || "")) + '</small>',
                "</div>"
            ].join("");
        }).join("");
    }

    function renderOwnerStatsSimpleTable(node, columns, rows, emptyText) {
        if (!node) {
            return;
        }
        if (!Array.isArray(rows) || !rows.length) {
            node.innerHTML = ownerStatsEmptyMarkup(emptyText || "جدولی برای نمایش وجود ندارد.");
            return;
        }

        var head = [
            '<div class="owner-stats-table__row owner-stats-table__row--head" style="--owner-stats-columns:' + columns.length + ';">',
            columns.map(function (column) {
                return '<span>' + escapeHtml(column.label) + "</span>";
            }).join(""),
            "</div>"
        ].join("");

        var body = rows.map(function (row) {
            return [
                '<div class="owner-stats-table__row" style="--owner-stats-columns:' + columns.length + ';">',
                columns.map(function (column) {
                    var rendered = typeof column.render === "function" ? column.render(row) : row[column.key];
                    return '<span>' + rendered + "</span>";
                }).join(""),
                "</div>"
            ].join("");
        }).join("");

        node.innerHTML = head + body;
    }

    function renderOwnerStatsTables(dashboard) {
        var totals = dashboard && dashboard.totals ? dashboard.totals : {};
        var examStats = dashboard && dashboard.exams ? dashboard.exams : null;
        renderOwnerStatsBars(ownerStatsFamilies, (dashboard && dashboard.families || []).map(function (item) {
            return {
                label: item.label || item.key || "",
                views: item.views || 0,
                meta: "دانلود " + ownerStatsMetric(item.downloads || 0)
            };
        }), "views", "هنوز خانواده مسیر پربازدیدی ثبت نشده است.");

        renderOwnerStatsBars(ownerStatsMethods, (dashboard && dashboard.loginMethods || []).map(function (item) {
            return {
                label: item.label || item.key || "",
                count: item.count || 0,
                meta: "سهم از کل ورودها"
            };
        }), "count", "هنوز login methodای ثبت نشده است.");

        renderOwnerStatsSimpleTable(ownerStatsPages, [
            {
                label: "صفحه",
                render: function (row) {
                    var title = String(row.title || "").trim();
                    var path = ownerStatsShortPath(row.path || "");
                    return '<strong>' + escapeHtml(title || path || "بدون عنوان") + '</strong><small>' + escapeHtml(path) + "</small>";
                }
            },
            {
                label: "بخش",
                render: function (row) {
                    return escapeHtml(String(row.familyLabel || row.family || ""));
                }
            },
            {
                label: "بازدید",
                render: function (row) {
                    return escapeHtml(ownerStatsMetric(row.views || 0));
                }
            },
            {
                label: "آخرین بازدید",
                render: function (row) {
                    return escapeHtml(formatJalaliDateTime(row.lastViewedAt, "—"));
                }
            }
        ], dashboard && dashboard.topPages || [], "هنوز صفحه پربازدیدی ثبت نشده است.");

        renderOwnerStatsSimpleTable(ownerStatsDownloads, [
            {
                label: "منبع / فایل",
                render: function (row) {
                    var label = String(row.label || row.href || "بدون عنوان");
                    var href = String(row.href || "").trim();
                    if (href) {
                        return '<strong>' + escapeHtml(label) + '</strong><small dir="ltr">' + escapeHtml(ownerStatsShortPath(href)) + "</small>";
                    }
                    return '<strong>' + escapeHtml(label) + "</strong>";
                }
            },
            {
                label: "مبدا",
                render: function (row) {
                    return escapeHtml(String(row.sourceLabel || row.sourceFamily || ""));
                }
            },
            {
                label: "تعداد",
                render: function (row) {
                    return escapeHtml(ownerStatsMetric(row.count || 0));
                }
            },
            {
                label: "آخرین استفاده",
                render: function (row) {
                    return escapeHtml(formatJalaliDateTime(row.lastAt, "—"));
                }
            }
        ], dashboard && dashboard.topDownloads || [], "هنوز دانلود/منبعی برای آمار ثبت نشده است.");

        renderOwnerStatsSimpleTable(ownerStatsCohorts, [
            {
                label: "ورودی",
                render: function (row) {
                    return '<strong>' + escapeHtml(String(row.shortTitle || row.title || row.key || "")) + '</strong><small>' + escapeHtml(String(row.title || "")) + "</small>";
                }
            },
            {
                label: "کاربر",
                render: function (row) {
                    return escapeHtml(ownerStatsMetric(row.totalUsers || 0));
                }
            },
            {
                label: "نماینده",
                render: function (row) {
                    return escapeHtml(ownerStatsMetric(row.representatives || 0));
                }
            },
            {
                label: "بازدید ۳۰ روز",
                render: function (row) {
                    return escapeHtml(ownerStatsMetric(row.pageViews30d || 0));
                }
            },
            {
                label: "ورود ۳۰ روز",
                render: function (row) {
                    return escapeHtml(ownerStatsMetric(row.logins30d || 0));
                }
            },
            {
                label: "بخش‌های فعال",
                render: function (row) {
                    var families = Array.isArray(row.topFamilies) ? row.topFamilies : [];
                    if (!families.length) {
                        return "—";
                    }
                    return families.slice(0, 3).map(function (family) {
                        return escapeHtml(String(family.label || family.key || ""));
                    }).join("، ");
                }
            }
        ], dashboard && dashboard.cohorts || [], "هنوز داده cohort-driven برای نمایش وجود ندارد.");

        renderOwnerStatsSimpleTable(ownerStatsRecentLogins, [
            {
                label: "کاربر",
                render: function (row) {
                    var name = String(row.name || "").trim();
                    var studentNumber = String(row.studentNumber || "").trim();
                    return '<strong>' + escapeHtml(name || "بدون نام") + '</strong><small dir="ltr">' + escapeHtml(studentNumber) + "</small>";
                }
            },
            {
                label: "ورودی",
                render: function (row) {
                    return escapeHtml(String(row.cohortLabel || row.cohortKey || "—"));
                }
            },
            {
                label: "نقش",
                render: function (row) {
                    return escapeHtml(String(row.roleLabel || row.role || ""));
                }
            },
            {
                label: "روش ورود",
                render: function (row) {
                    return escapeHtml(String(row.methodLabel || row.method || ""));
                }
            },
            {
                label: "زمان ورود",
                render: function (row) {
                    return escapeHtml(formatJalaliDateTime(row.at, "—"));
                }
            }
        ], dashboard && dashboard.recentLogins || [], "هنوز ورودی برای نمایش ثبت نشده است.");

        if (ownerStatsExams) {
            if (!examStats) {
                ownerStatsExams.innerHTML = ownerStatsEmptyMarkup("\u0647\u0646\u0648\u0632 \u0622\u0645\u0627\u0631\u06cc \u0627\u0632 \u0645\u0627\u0698\u0648\u0644 \u0622\u0632\u0645\u0648\u0646 \u062f\u0631 \u062f\u0633\u062a\u0631\u0633 \u0646\u06cc\u0633\u062a.");
            } else {
                var averagePercent = examStats.averagePercent === null || examStats.averagePercent === undefined
                    ? "\u2014"
                    : (ownerStatsMetric(examStats.averagePercent) + "%");
                ownerStatsExams.innerHTML = [
                    summaryCard("\u062e\u0631\u06cc\u062f\u0627\u0631 \u0622\u0632\u0645\u0648\u0646", ownerStatsMetric(examStats.purchaserCount || 0), ownerStatsMetric(examStats.paidOrderCount || 0) + " \u067e\u0631\u062f\u0627\u062e\u062a \u0645\u0648\u0641\u0642 \u062f\u0631 \u0628\u062e\u0634 \u0622\u0632\u0645\u0648\u0646", (examStats.purchaserCount || 0) > 0 ? "ok" : ""),
                    summaryCard("\u0622\u0632\u0645\u0648\u0646 \u0634\u0631\u0648\u0639\u200c\u0634\u062f\u0647", ownerStatsMetric(examStats.startedCount || 0), "\u0634\u0631\u0648\u0639\u200c\u0647\u0627\u06cc \u062b\u0628\u062a\u200c\u0634\u062f\u0647 \u062f\u0631 examRecords \u0647\u0645\u0647 \u062c\u0644\u0633\u0647\u200c\u0647\u0627", (examStats.startedCount || 0) > 0 ? "ok" : ""),
                    summaryCard("\u06a9\u0644 \u0633\u0648\u0627\u0644\u0627\u062a \u0622\u0632\u0645\u0648\u0646", ownerStatsMetric(examStats.questionCount || 0), ownerStatsMetric(examStats.examCount || 0) + " \u0622\u0632\u0645\u0648\u0646 \u0627\u0632 " + ownerStatsMetric(examStats.courseCount || 0) + " \u062f\u0631\u0633"),
                    summaryCard("\u0645\u0628\u0644\u063a \u067e\u0631\u062f\u0627\u062e\u062a\u200c\u0634\u062f\u0647", ownerStatsMetric(examStats.receivedAmount || 0) + " \u0631\u06cc\u0627\u0644", "\u062c\u0645\u0639 \u067e\u0631\u062f\u0627\u062e\u062a\u200c\u0647\u0627\u06cc \u0645\u0648\u0641\u0642 \u0628\u0631\u0627\u06cc \u0622\u0632\u0645\u0648\u0646\u200c\u0647\u0627", (examStats.receivedAmount || 0) > 0 ? "ok" : ""),
                    summaryCard("\u0628\u0627\u0632\u062f\u06cc\u062f \u0635\u0641\u062d\u0647 \u0622\u0632\u0645\u0648\u0646", ownerStatsMetric(examStats.pageViews || 0), ownerStatsMetric(examStats.pageViews30d || 0) + " \u0628\u0627\u0632\u062f\u06cc\u062f \u062f\u0631 \u06f3\u06f0 \u0631\u0648\u0632 \u0627\u062e\u06cc\u0631", (examStats.pageViews || 0) > 0 ? "ok" : ""),
                    summaryCard("\u0645\u06cc\u0627\u0646\u06af\u06cc\u0646 \u062f\u0631\u0635\u062f", averagePercent, ownerStatsMetric(examStats.submittedCount || 0) + " \u06a9\u0627\u0631\u0646\u0627\u0645\u0647 \u0633\u0646\u062c\u0634\u06cc \u062b\u0628\u062a\u200c\u0634\u062f\u0647", examStats.averagePercent !== null && examStats.averagePercent !== undefined ? "warn" : "")
                ].join("");
            }
        }

        if (ownerStatsReferences) {
            ownerStatsReferences.innerHTML = [
                summaryCard("دانلود فایل‌سنتر", ownerStatsMetric(totals.contentToolsDownloads || 0), "شمارنده backend ماژول فایل‌سنتر", totals.contentToolsDownloads > 0 ? "ok" : ""),
                summaryCard("بازدید paste", ownerStatsMetric((totals.pasteViews || 0) + (totals.pasteRawViews || 0)), "view و raw-view در ماژول paste"),
                summaryCard("بازدید HTML", ownerStatsMetric(totals.htmlPageViews || 0), "viewCount صفحه‌های public HTML uploader", totals.htmlPageViews > 0 ? "warn" : ""),
                summaryCard("دانلود ثبت‌شده جدید", ownerStatsMetric(totals.downloads || 0), "downloadهایی که از tracker جدید جمع شده‌اند", totals.downloads > 0 ? "ok" : "")
            ].join("");
        }

        if (ownerStatsRetention) {
            var retention = dashboard && dashboard.retention ? dashboard.retention : {};
            ownerStatsRetention.innerHTML = [
                summaryCard("نرخ بازگشت", ownerStatsMetric(retention.returnRatePercent || 0) + "%", "کاربرانی که در ۳۰ روز بیش از یک روز وارد شده‌اند", (retention.returnRatePercent || 0) > 0 ? "ok" : ""),
                summaryCard("کاربر بازگشتی", ownerStatsMetric(retention.returning || 0), "ورود در حداقل ۲ روز مجزا"),
                summaryCard("کاربر یک‌باره", ownerStatsMetric(retention.oneTime || 0), "فقط در یک روز وارد شده‌اند", (retention.oneTime || 0) > 0 ? "warn" : ""),
                summaryCard("کاربر یکتا", ownerStatsMetric(retention.uniqueUsers || 0), "کل کاربران واردشده در ۳۰ روز")
            ].join("");
        }

        if (ownerStatsFunnel) {
            var funnel = dashboard && dashboard.funnel ? dashboard.funnel : {};
            ownerStatsFunnel.innerHTML = [
                summaryCard("بازدید صفحه خرید", ownerStatsMetric(funnel.buyViews || 0), "بازدید مسیرهای خرید در ۳۰ روز"),
                summaryCard("سفارش ثبت‌شده", ownerStatsMetric(funnel.ordersCreated || 0), "سفارش‌های ساخته‌شده در ۳۰ روز"),
                summaryCard("پرداخت موفق", ownerStatsMetric(funnel.ordersPaid || 0), ownerStatsMetric(funnel.ordersPending || 0) + " سفارش در انتظار پرداخت", (funnel.ordersPaid || 0) > 0 ? "ok" : ""),
                summaryCard("نرخ تبدیل", ownerStatsMetric(funnel.conversionPercent || 0) + "%", "سهم پرداخت موفق از کل سفارش‌ها", (funnel.conversionPercent || 0) >= 50 ? "ok" : "warn")
            ].join("");
        }

        var errorLog = dashboard && dashboard.errorLog ? dashboard.errorLog : null;
        if (ownerStatsErrorsMeta) {
            ownerStatsErrorsMeta.textContent = errorLog && errorLog.available
                ? ("۲۴ ساعت: " + ownerStatsMetric(errorLog.last24h || 0) + " • ۷ روز: " + ownerStatsMetric(errorLog.last7d || 0) + " • کل ثبت‌شده: " + ownerStatsMetric(errorLog.total || 0))
                : "هنوز خطایی در لاگ سرور ثبت نشده است.";
        }
        if (ownerStatsErrors) {
            renderOwnerStatsSimpleTable(ownerStatsErrors, [
                {
                    label: "زمان",
                    render: function (row) {
                        return escapeHtml(formatJalaliDateTime(row.at, "—"));
                    }
                },
                {
                    label: "نوع",
                    render: function (row) {
                        var type = String(row.type || "");
                        var status = row.status ? ('<small>' + escapeHtml(ownerStatsMetric(row.status)) + "</small>") : "";
                        return escapeHtml(type) + status;
                    }
                },
                {
                    label: "پیام",
                    render: function (row) {
                        var message = String(row.message || "");
                        if (message.length > 120) {
                            message = message.slice(0, 120) + "…";
                        }
                        var location = row.file
                            ? ('<small dir="ltr">' + escapeHtml(ownerStatsShortPath(row.file)) + (row.line ? (":" + row.line) : "") + "</small>")
                            : "";
                        return '<strong>' + escapeHtml(message || "بدون پیام") + "</strong>" + location;
                    }
                },
                {
                    label: "مسیر",
                    render: function (row) {
                        return escapeHtml(String(row.action || ownerStatsShortPath(row.uri || "") || "—"));
                    }
                }
            ], errorLog && errorLog.recent || [], "هنوز خطایی در لاگ سرور ثبت نشده است.");
        }
    }

    function renderOwnerAnalytics() {
        var dashboard = ownerAnalyticsState.dashboard;
        syncOwnerStatsShortcut();
        if (ownerStatsMeta) {
            ownerStatsMeta.textContent = dashboard && dashboard.generatedAt
                ? ("آخرین به‌روزرسانی: " + formatJalaliDateTime(dashboard.generatedAt, "—", true))
                : "آخرین snapshot هنوز بارگذاری نشده است.";
        }

        renderOwnerStatsOverview(dashboard);
        renderOwnerStatsChart(ownerStatsVisitsChart, dashboard && dashboard.charts ? dashboard.charts.pageViews14d : [], "هنوز بازدید روزانه‌ای ثبت نشده است.");
        renderOwnerStatsChart(ownerStatsLoginsChart, dashboard && dashboard.charts ? dashboard.charts.logins14d : [], "هنوز ورود روزانه‌ای ثبت نشده است.");
        renderOwnerStatsChart(ownerStatsDownloadsChart, dashboard && dashboard.charts ? dashboard.charts.downloads14d : [], "هنوز دانلود روزانه‌ای ثبت نشده است.");
        renderOwnerStatsTables(dashboard);

        if (ownerStatsRefreshButton) {
            ownerStatsRefreshButton.disabled = ownerAnalyticsState.loading;
            ownerStatsRefreshButton.textContent = ownerAnalyticsState.loading ? "در حال به‌روزرسانی..." : "به‌روزرسانی";
        }
    }

    async function loadOwnerAnalytics(force) {
        if (!ownerCanAccessStats() || !ownerStatsOverview) {
            return;
        }
        if (ownerAnalyticsState.loading) {
            return;
        }
        if (ownerAnalyticsState.loaded && ownerAnalyticsState.dashboard && !force) {
            renderOwnerAnalytics();
            return;
        }

        ownerAnalyticsState.loading = true;
        ownerStatsFeedbackMessage("در حال بارگذاری آمار سایت...", "", true);
        renderOwnerAnalytics();

        var response = await analyticsGet("ownerDashboard");
        ownerAnalyticsState.loading = false;

        if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
            ownerStatsFeedbackMessage("", "");
            renderOwnerAnalytics();
            return;
        }

        if (!response || !response.success || !response.dashboard) {
            ownerStatsFeedbackMessage((response && response.error) || "آمار سایت خوانده نشد.", "error");
            renderOwnerAnalytics();
            return;
        }

        ownerAnalyticsState.dashboard = response.dashboard;
        ownerAnalyticsState.loaded = true;
        ownerStatsFeedbackMessage("", "");
        renderOwnerAnalytics();
    }

    return Object.freeze({
      renderOwnerSummary: renderOwnerSummary,
      summaryCard: summaryCard,
      ownerStatsMetric: ownerStatsMetric,
      syncOwnerStatsShortcut: syncOwnerStatsShortcut,
      ownerStatsEmptyMarkup: ownerStatsEmptyMarkup,
      ownerStatsShortPath: ownerStatsShortPath,
      renderOwnerStatsOverview: renderOwnerStatsOverview,
      renderOwnerStatsChart: renderOwnerStatsChart,
      renderOwnerStatsBars: renderOwnerStatsBars,
      renderOwnerStatsSimpleTable: renderOwnerStatsSimpleTable,
      renderOwnerStatsTables: renderOwnerStatsTables,
      renderOwnerAnalytics: renderOwnerAnalytics,
      loadOwnerAnalytics: loadOwnerAnalytics
    });
  }

  window.Dent1402AccountOwnerAnalytics = Object.freeze({ create: create });
}());
