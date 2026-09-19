from __future__ import annotations

import html
from typing import TYPE_CHECKING, Any

from . import class_operations as classops
from .persian_datetime import to_persian_digits
from .site_api import SiteApiError
from .ui import Screen, button, frame, keyboard, native_rich_text

if TYPE_CHECKING:
    from .app import DentBotApp


def _field_meta(field: str) -> tuple[str, range]:
    if field == "group10":
        return "صبح", range(1, 11)
    if field == "group8":
        return "عصر", range(11, 19)
    raise ValueError("invalid Term 7 group field")


def _student_number(row: dict[str, Any]) -> str:
    value = str(row.get("studentNumber") or "").strip()
    return value if value.isdigit() and len(value) <= 20 else ""


def _assignment(row: dict[str, Any]) -> dict[str, Any]:
    value = row.get("assignment")
    return dict(value) if isinstance(value, dict) else {}


def _booklet_profile(row: dict[str, Any]) -> dict[str, Any]:
    value = row.get("bookletSystem")
    return dict(value) if isinstance(value, dict) else {}


def _booklet_summary(profile: dict[str, Any]) -> str:
    group = profile.get("group")
    if isinstance(group, int):
        return f"جزوه‌نویسی: گروه {to_persian_digits(group)} · {profile.get('statusLabel') or 'عضو'}"
    roles = [
        str(item.get("label") or "").strip()
        for item in profile.get("specialRoles", [])
        if isinstance(item, dict) and str(item.get("label") or "").strip()
    ]
    if roles:
        return "جزوه‌نویسی: بدون گروه · " + "، ".join(roles)
    return "جزوه‌نویسی: بدون گروه"


def _assignment_summary(assignment: dict[str, Any]) -> str:
    parts: list[str] = []
    for field in ("group10", "group8"):
        label, _ = _field_meta(field)
        group = assignment.get(field)
        status = str(assignment.get(field + "StatusLabel") or "بدون گروه")
        if isinstance(group, int):
            parts.append(f"{label}: گروه {to_persian_digits(group)} · {status}")
        else:
            parts.append(f"{label}: بدون گروه")
    return "\n".join(parts)


def _field_summary(assignment: dict[str, Any], field: str) -> str:
    label, _ = _field_meta(field)
    group = assignment.get(field)
    status = str(assignment.get(field + "StatusLabel") or "بدون گروه")
    if isinstance(group, int):
        return f"{label}: گروه {to_persian_digits(group)} · {status}"
    return f"{label}: بدون گروه"


def _roster_summary(roster: list[dict[str, Any]], booklet_payload: dict[str, Any] | None = None) -> str:
    morning_missing = 0
    afternoon_missing = 0
    for row in roster:
        assignment = _assignment(row)
        if not isinstance(assignment.get("group10"), int):
            morning_missing += 1
        if not isinstance(assignment.get("group8"), int):
            afternoon_missing += 1
    academic_line = (
        f"گروه‌بندی آموزشی: {to_persian_digits(len(roster))} نفر · "
        f"بدون گروه صبح: {to_persian_digits(morning_missing)} · "
        f"بدون گروه عصر: {to_persian_digits(afternoon_missing)}"
    )
    summary = dict((booklet_payload or {}).get("summary") or {})
    class_count = summary.get("classCount")
    members = summary.get("classBookletMembers")
    outside = summary.get("classOutsideBookletGroups")
    free = summary.get("classFreeEligible")
    paid = summary.get("classPaidMembers")
    booklet_bits = []
    if isinstance(class_count, int):
        booklet_bits.append(f"کل کلاس: {to_persian_digits(class_count)}")
    if isinstance(members, int):
        booklet_bits.append(f"عضو گروه جزوه‌نویسی: {to_persian_digits(members)}")
    if isinstance(outside, int):
        booklet_bits.append(f"خارج از گروه: {to_persian_digits(outside)}")
    if isinstance(free, int):
        booklet_bits.append(f"اشتراک رایگان: {to_persian_digits(free)}")
    if isinstance(paid, int):
        booklet_bits.append(f"نیازمند پرداخت: {to_persian_digits(paid)}")
    return academic_line + ("\nجزوه‌نویسی: " + " · ".join(booklet_bits) if booklet_bits else "")


def _home_screen(roster: list[dict[str, Any]], booklet_payload: dict[str, Any] | None = None) -> Screen:
    return Screen(
        frame(
            "گروه‌بندی ترم ۷",
            "گروه صبح، گروه عصر و گروه جزوه‌نویسی هر دانشجو را از همین بخش مدیریت کن.",
            _roster_summary(roster, booklet_payload),
        ),
        keyboard(
            [button("گروه‌های صبح", action="t7:g:10", style="primary")],
            [button("گروه‌های عصر", action="t7:g:8")],
            [button("📝 گروه‌های جزوه‌نویسی", action="t7:bg")],
            [button("↩️ مدیریت امور کلاس", action="class-operations:owner"), button("🏠 خانه", action="home")],
        ),
    )


def _group_index(roster: list[dict[str, Any]], field: str) -> Screen:
    label, groups = _field_meta(field)
    rows: list[tuple[int, int, str]] = []
    for group in groups:
        members = [row for row in roster if _assignment(row).get(field) == group]
        leader = next(
            (str(row.get("name") or "") for row in members if _assignment(row).get(field + "Status") == "leader"),
            "—",
        )
        rows.append((group, len(members), leader))

    fallback = [f"<b>گروه‌های {label}</b>", ""]
    rich = [
        f"<h2>گروه‌های {html.escape(label)}</h2>",
        "<table bordered striped compact><tr><th>گروه</th><th>تعداد</th><th>سرگروه</th></tr>",
    ]
    buttons: list[list[dict]] = []
    for group, count, leader in rows:
        fallback.append(
            f"گروه {to_persian_digits(group)} · {to_persian_digits(count)} نفر"
            + (f" · {html.escape(leader)}" if leader != "—" else "")
        )
        rich.append(
            f"<tr><td>{to_persian_digits(group)}</td><td>{to_persian_digits(count)}</td>"
            f"<td>{html.escape(leader)}</td></tr>"
        )
        buttons.append([
            button(
                f"گروه {to_persian_digits(group)} · {to_persian_digits(count)} نفر",
                action=f"t7:v:{10 if field == 'group10' else 8}:{group}",
            )
        ])
    rich.append("</table>")
    unassigned = sum(1 for row in roster if not isinstance(_assignment(row).get(field), int))
    if unassigned:
        fallback.extend(("", f"بدون گروه: {to_persian_digits(unassigned)} نفر"))
        rich.append(f"<footer>بدون گروه: {to_persian_digits(unassigned)} نفر</footer>")
        buttons.append([
            button(
                f"بدون گروه {label} · {to_persian_digits(unassigned)} نفر",
                action=f"t7:v:{10 if field == 'group10' else 8}:0",
            )
        ])
    buttons.append([button("↩️ گروه‌بندی ترم ۷", action="t7"), button("🏠 خانه", action="home")])
    return Screen(native_rich_text("\n".join(fallback), "".join(rich)), keyboard(*buttons))


def _group_screen(roster: list[dict[str, Any]], field: str, group: int) -> Screen:
    label, _ = _field_meta(field)
    members = [
        row
        for row in roster
        if (_assignment(row).get(field) == group if group else not isinstance(_assignment(row).get(field), int))
    ]
    title = f"گروه {to_persian_digits(group)} {label}" if group else f"بدون گروه {label}"
    fallback = [f"<b>{html.escape(title)}</b>", ""]
    rich = [
        f"<h2>{html.escape(title)}</h2>",
        "<table bordered striped compact><tr><th>دانشجو</th><th>وضعیت</th></tr>",
    ]
    rows: list[list[dict]] = []
    if not members:
        fallback.append("دانشجویی در این بخش نیست.")
        rich.append("<tr><td colspan=\"2\">دانشجویی در این بخش نیست.</td></tr>")
    for row in members:
        name = str(row.get("name") or "دانشجو").strip() or "دانشجو"
        status = str(_assignment(row).get(field + "StatusLabel") or "بدون گروه")
        fallback.append(f"• <b>{html.escape(name)}</b> · {html.escape(status)}")
        rich.append(f"<tr><td>{html.escape(name)}</td><td>{html.escape(status)}</td></tr>")
        student_number = _student_number(row)
        if student_number:
            rows.append([button(name[:28], action=f"t7:s:{student_number}")])
    rich.append("</table>")
    rows.append([
        button(f"↩️ گروه‌های {label}", action=f"t7:g:{10 if field == 'group10' else 8}"),
        button("🏠 خانه", action="home"),
    ])
    return Screen(native_rich_text("\n".join(fallback), "".join(rich)), keyboard(*rows))


def _student_screen(row: dict[str, Any]) -> Screen:
    name = str(row.get("name") or "دانشجو").strip() or "دانشجو"
    student_number = _student_number(row)
    assignment = _assignment(row)
    booklet = _booklet_profile(row)
    fallback = [f"<b>{html.escape(name)}</b>", "", _assignment_summary(assignment), _booklet_summary(booklet)]
    booklet_summary = _booklet_summary(booklet).removeprefix("جزوه‌نویسی: ")
    rich = [
        f"<h2>{html.escape(name)}</h2>",
        "<table bordered striped compact>",
        f"<tr><th>صبح</th><td>{html.escape(_field_summary(assignment, 'group10'))}</td></tr>",
        f"<tr><th>عصر</th><td>{html.escape(_field_summary(assignment, 'group8'))}</td></tr>",
        f"<tr><th>جزوه‌نویسی</th><td>{html.escape(booklet_summary)}</td></tr>",
        "</table>",
    ]
    manager_courses = [
        str(item.get("title") or "").strip()
        for item in booklet.get("managerCourses", [])
        if isinstance(item, dict) and str(item.get("title") or "").strip()
    ]
    special_roles = [
        str(item.get("label") or "").strip()
        for item in booklet.get("specialRoles", [])
        if isinstance(item, dict) and str(item.get("label") or "").strip()
    ]
    role_lines = []
    if manager_courses:
        role_lines.append("مسئول جزوه: " + "، ".join(manager_courses))
    if special_roles:
        role_lines.append("مسئولیت: " + "، ".join(special_roles))
    if role_lines:
        fallback.extend(("", "<b>🎯 مسئولیت‌ها</b>", "\n".join(html.escape(line) for line in role_lines)))
        rich.append("<blockquote>" + "<br>".join(html.escape(line) for line in role_lines) + "</blockquote>")
    subscription = (
        "رایگان · فعال‌سازی خودکار ماهانه"
        if booklet.get("freeSubscriptionEligible") is True
        else "۱۵۰٬۰۰۰ تومان در ماه"
    )
    fallback.extend(("", f"<code>اشتراک جزوات</code>  <b>{subscription}</b>"))
    rich.append(f"<p><b>اشتراک جزوات:</b> {subscription}</p>")

    rows: list[list[dict]] = [[
        button("تغییر گروه صبح", action=f"t7:c:10:{student_number}"),
        button("تغییر گروه عصر", action=f"t7:c:8:{student_number}"),
    ], [
        button("تغییر گروه جزوه‌نویسی", action=f"t7:bc:{student_number}")
    ]]
    for field, short in (("group10", 10), ("group8", 8)):
        label, _ = _field_meta(field)
        if isinstance(assignment.get(field), int):
            leader = assignment.get(field + "Status") == "leader"
            rows.append([
                button(
                    f"{'برداشتن' if leader else 'تعیین'} سرگروهی {label}",
                    action=f"t7:l:{short}:{student_number}:{0 if leader else 1}",
                    style="danger" if leader else "success",
                )
            ])
    if isinstance(booklet.get("group"), int):
        booklet_leader = str(booklet.get("status") or "") == "leader"
        rows.append([
            button(
                f"{'برداشتن' if booklet_leader else 'تعیین'} سرگروهی جزوه‌نویسی",
                action=f"t7:bl:{student_number}:{0 if booklet_leader else 1}",
                style="danger" if booklet_leader else "success",
            )
        ])
    rows.append([button("↩️ گروه‌بندی ترم ۷", action="t7"), button("🏠 خانه", action="home")])
    return Screen(native_rich_text("\n".join(fallback), "".join(rich)), keyboard(*rows))


def _choose_group_screen(row: dict[str, Any], field: str) -> Screen:
    label, groups = _field_meta(field)
    name = str(row.get("name") or "دانشجو").strip() or "دانشجو"
    student_number = _student_number(row)
    current = _assignment(row).get(field)
    current_text = f"گروه {to_persian_digits(current)}" if isinstance(current, int) else "بدون گروه"
    rows: list[list[dict]] = []
    pair: list[dict] = []
    short = 10 if field == "group10" else 8
    for group in groups:
        pair.append(button(f"گروه {to_persian_digits(group)}", action=f"t7:u:{short}:{student_number}:{group}"))
        if len(pair) == 2:
            rows.append(pair)
            pair = []
    if pair:
        rows.append(pair)
    rows.append([button("پاک‌کردن گروه", action=f"t7:u:{short}:{student_number}:0", style="danger")])
    rows.append([button("↩️ بازگشت به دانشجو", action=f"t7:s:{student_number}")])
    return Screen(frame(f"تغییر گروه {label}", name, f"گروه فعلی: {current_text}"), keyboard(*rows))


def _booklet_group_index(payload: dict[str, Any]) -> Screen:
    groups = [dict(row) for row in payload.get("groups", []) if isinstance(row, dict)]
    fallback = ["<b>📝 گروه‌های جزوه‌نویسی</b>", ""]
    rich = [
        "<h2>📝 گروه‌های جزوه‌نویسی</h2>",
        "<table bordered striped compact><tr><th>گروه</th><th>تعداد</th><th>سرگروه</th><th>درس</th></tr>",
    ]
    rows: list[list[dict]] = []
    for group in groups:
        number = int(group.get("group") or 0)
        if not 1 <= number <= 31:
            continue
        count = int(group.get("memberCount") or 0)
        leader = str(group.get("leaderName") or "—")
        course_titles = [
            str(item.get("title") or "").strip()
            for item in group.get("courses", [])
            if isinstance(item, dict) and str(item.get("title") or "").strip()
        ]
        course_text = "، ".join(course_titles) if course_titles else "—"
        fallback.append(
            f"گروه {to_persian_digits(number)} · {to_persian_digits(count)} نفر"
            + (f" · {html.escape(leader)}" if leader != "—" else "")
        )
        rich.append(
            f"<tr><td>{to_persian_digits(number)}</td><td>{to_persian_digits(count)}</td>"
            f"<td>{html.escape(leader)}</td><td>{html.escape(course_text)}</td></tr>"
        )
        rows.append([
            button(
                f"گروه {to_persian_digits(number)} · {to_persian_digits(count)} نفر",
                action=f"t7:bv:{number}",
            )
        ])
    rich.append("</table>")
    summary = dict(payload.get("summary") or {})
    outside = summary.get("classOutsideBookletGroups")
    paid = summary.get("classPaidMembers")
    footer_parts = []
    if isinstance(outside, int):
        footer_parts.append(f"خارج از گروه‌ها: {to_persian_digits(outside)} نفر")
    if isinstance(paid, int):
        footer_parts.append(f"نیازمند پرداخت: {to_persian_digits(paid)} نفر")
    if footer_parts:
        footer_text = " · ".join(footer_parts)
        fallback.extend(("", footer_text))
        rich.append(f"<footer>{html.escape(footer_text)}</footer>")
    rows.append([button("↩️ گروه‌بندی ترم ۷", action="t7"), button("🏠 خانه", action="home")])
    return Screen(native_rich_text("\n".join(fallback), "".join(rich)), keyboard(*rows))


def _booklet_group_screen(payload: dict[str, Any], group_number: int) -> Screen:
    group = next(
        (
            dict(row)
            for row in payload.get("groups", [])
            if isinstance(row, dict) and int(row.get("group") or 0) == group_number
        ),
        {},
    )
    title = f"گروه {to_persian_digits(group_number)} جزوه‌نویسی"
    courses = [
        str(item.get("title") or "").strip()
        for item in group.get("courses", [])
        if isinstance(item, dict) and str(item.get("title") or "").strip()
    ]
    fallback = [f"<b>📝 {title}</b>", ""]
    if courses:
        fallback.extend(("<b>درس‌های مسئولیت</b>", "، ".join(html.escape(item) for item in courses), ""))
    rich = [f"<h2>📝 {html.escape(title)}</h2>"]
    if courses:
        rich.append(f"<blockquote><b>درس‌های مسئولیت:</b> {html.escape('، '.join(courses))}</blockquote>")
    rich.append("<table bordered striped compact><tr><th>دانشجو</th><th>وضعیت</th></tr>")
    rows: list[list[dict]] = []
    members = [dict(item) for item in group.get("members", []) if isinstance(item, dict)]
    if not members:
        fallback.append("عضوی در این گروه ثبت نشده است.")
        rich.append('<tr><td colspan="2">عضوی در این گروه ثبت نشده است.</td></tr>')
    for member in members:
        name = str(member.get("name") or "دانشجو").strip() or "دانشجو"
        status = str(member.get("statusLabel") or "عضو")
        fallback.append(f"• <b>{html.escape(name)}</b> · {html.escape(status)}")
        rich.append(f"<tr><td>{html.escape(name)}</td><td>{html.escape(status)}</td></tr>")
        student_number = str(member.get("studentNumber") or "")
        if student_number.isdigit():
            rows.append([button(name[:28], action=f"t7:bs:{student_number}")])
    rich.append("</table>")
    rows.append([button("↩️ گروه‌های جزوه‌نویسی", action="t7:bg"), button("🏠 خانه", action="home")])
    return Screen(native_rich_text("\n".join(fallback), "".join(rich)), keyboard(*rows))


def _find_booklet_member(payload: dict[str, Any], student_number: str) -> dict[str, Any] | None:
    for row in payload.get("members", []):
        if not isinstance(row, dict):
            continue
        if str(row.get("studentNumber") or "") == student_number:
            return dict(row)
    for row in payload.get("classRoster", []):
        if not isinstance(row, dict):
            continue
        if str(row.get("studentNumber") or "") == student_number:
            return dict(row)
    return None


def _booklet_member_screen(row: dict[str, Any]) -> Screen:
    name = str(row.get("name") or "دانشجو").strip() or "دانشجو"
    student_number = str(row.get("studentNumber") or "")
    profile = _booklet_profile(row)
    group = profile.get("group")
    group_text = (
        f"گروه {to_persian_digits(group)} · {profile.get('statusLabel') or 'عضو'}"
        if isinstance(group, int)
        else "بدون گروه"
    )
    manager_courses = [
        str(item.get("title") or "").strip()
        for item in profile.get("managerCourses", [])
        if isinstance(item, dict) and str(item.get("title") or "").strip()
    ]
    special_roles = [
        str(item.get("label") or "").strip()
        for item in profile.get("specialRoles", [])
        if isinstance(item, dict) and str(item.get("label") or "").strip()
    ]
    subscription = (
        "رایگان · فعال‌سازی خودکار ماهانه"
        if profile.get("freeSubscriptionEligible") is True
        else "۱۵۰٬۰۰۰ تومان در ماه"
    )
    fallback = [
        f"<b>📝 {html.escape(name)}</b>",
        "",
        f"<code>گروه جزوه‌نویسی</code>  <b>{html.escape(group_text)}</b>",
    ]
    rich = [
        f"<h2>📝 {html.escape(name)}</h2>",
        "<table bordered striped compact>",
        f"<tr><th>گروه جزوه‌نویسی</th><td><b>{html.escape(group_text)}</b></td></tr>",
        f"<tr><th>اشتراک جزوات</th><td><b>{subscription}</b></td></tr>",
        "</table>",
    ]
    if manager_courses:
        fallback.extend(("", f"<b>مسئول جزوه</b>\n{html.escape('، '.join(manager_courses))}"))
        rich.append(f"<blockquote><b>مسئول جزوه:</b> {html.escape('، '.join(manager_courses))}</blockquote>")
    if special_roles:
        fallback.extend(("", f"<b>مسئولیت</b>\n{html.escape('، '.join(special_roles))}"))
        rich.append(f"<blockquote><b>مسئولیت:</b> {html.escape('، '.join(special_roles))}</blockquote>")
    fallback.extend(("", f"<code>اشتراک جزوات</code>  <b>{subscription}</b>"))
    rows: list[list[dict]] = [[button("تغییر گروه جزوه‌نویسی", action=f"t7:bc:{student_number}")]]
    if isinstance(group, int):
        leader = str(profile.get("status") or "") == "leader"
        rows.append([
            button(
                f"{'برداشتن' if leader else 'تعیین'} سرگروهی جزوه‌نویسی",
                action=f"t7:bl:{student_number}:{0 if leader else 1}",
                style="danger" if leader else "success",
            )
        ])
        rows.append([button("↩️ گروه", action=f"t7:bv:{group}")])
    else:
        rows.append([button("↩️ گروه‌های جزوه‌نویسی", action="t7:bg")])
    rows.append([button("🏠 خانه", action="home")])
    return Screen(native_rich_text("\n".join(fallback), "".join(rich)), keyboard(*rows))


def _choose_booklet_group_screen(row: dict[str, Any]) -> Screen:
    name = str(row.get("name") or "دانشجو").strip() or "دانشجو"
    student_number = str(row.get("studentNumber") or "")
    profile = _booklet_profile(row)
    current = profile.get("group")
    current_text = f"گروه {to_persian_digits(current)}" if isinstance(current, int) else "بدون گروه"
    rows: list[list[dict]] = []
    pair: list[dict] = []
    for group in range(1, 32):
        pair.append(button(f"گروه {to_persian_digits(group)}", action=f"t7:bu:{student_number}:{group}"))
        if len(pair) == 2:
            rows.append(pair)
            pair = []
    if pair:
        rows.append(pair)
    rows.append([button("پاک‌کردن گروه", action=f"t7:bu:{student_number}:0", style="danger")])
    rows.append([button("↩️ بازگشت", action=f"t7:bs:{student_number}")])
    return Screen(frame("تغییر گروه جزوه‌نویسی", name, f"گروه فعلی: {current_text}"), keyboard(*rows))


def _find_student(roster: list[dict[str, Any]], student_number: str) -> dict[str, Any] | None:
    return next((row for row in roster if _student_number(row) == student_number), None)


def _strip_callback(value: object) -> str:
    raw = str(value or "").strip()
    return raw[3:] if raw.startswith("v1:") else raw


def _is_term7_callback(value: object) -> bool:
    action = _strip_callback(value)
    return action == "t7" or action.startswith("t7:")


def _roster(app: DentBotApp, user_id: int) -> list[dict[str, Any]]:
    response = app.site_api.request("academicTerm7Roster", user_id)
    return [row for row in response.get("roster", []) if isinstance(row, dict)]


def _booklet_payload(app: DentBotApp, user_id: int) -> dict[str, Any]:
    response = app.site_api.request("academicTerm7BookletRoster", user_id)
    return dict(response)


def _handle_term7(app: DentBotApp, callback: dict[str, Any]) -> None:
    message = dict(callback.get("message") or {})
    sender = dict(callback.get("from") or {})
    chat = dict(message.get("chat") or {})
    try:
        chat_id = int(chat.get("id") or sender.get("id") or 0)
        user_id = int(sender.get("id") or 0)
    except (TypeError, ValueError):
        return
    if chat_id == 0 or user_id == 0:
        return
    callback_id = str(callback.get("id") or "")
    if callback_id:
        try:
            app.api.answer_callback(callback_id)
        except Exception:
            pass

    def show(screen: Screen) -> None:
        classops._render_screen(app, chat_id, screen, callback=callback)

    blocked = classops._access_screen(app, user_id)
    if blocked is not None:
        show(blocked)
        return

    action = _strip_callback(callback.get("data"))
    try:
        roster = _roster(app, user_id)
        booklet_payload = _booklet_payload(app, user_id)
        if action == "t7":
            show(_home_screen(roster, booklet_payload))
            return
        if action in {"t7:g:10", "t7:g:8"}:
            show(_group_index(roster, "group10" if action.endswith(":10") else "group8"))
            return
        if action == "t7:bg":
            show(_booklet_group_index(booklet_payload))
            return
        if action.startswith("t7:bv:"):
            group = int(action.rsplit(":", 1)[-1])
            if not 1 <= group <= 31:
                raise ValueError("booklet group")
            show(_booklet_group_screen(booklet_payload, group))
            return
        if action.startswith("t7:bs:"):
            student_number = action.rsplit(":", 1)[-1]
            row = _find_booklet_member(booklet_payload, student_number)
            show(
                _booklet_member_screen(row)
                if row
                else Screen(
                    frame("گروه‌بندی جزوه‌نویسی", "دانشجو پیدا نشد."),
                    keyboard([button("↩️ گروه‌های جزوه‌نویسی", action="t7:bg")]),
                )
            )
            return
        if action.startswith("t7:bc:"):
            student_number = action.rsplit(":", 1)[-1]
            row = _find_booklet_member(booklet_payload, student_number)
            if row is None:
                raise ValueError("booklet student")
            show(_choose_booklet_group_screen(row))
            return
        if action.startswith("t7:bu:"):
            _, _, student_number, group_raw = action.split(":", 3)
            group = int(group_raw)
            app.site_api.request(
                "academicTerm7BookletAssignmentUpdate",
                user_id,
                studentNumber=student_number,
                group=None if group == 0 else group,
            )
            updated_payload = _booklet_payload(app, user_id)
            updated = _find_booklet_member(updated_payload, student_number)
            show(_booklet_member_screen(updated) if updated else _booklet_group_index(updated_payload))
            return
        if action.startswith("t7:bl:"):
            _, _, student_number, leader_raw = action.split(":", 3)
            app.site_api.request(
                "academicTerm7BookletLeaderUpdate",
                user_id,
                studentNumber=student_number,
                leader=leader_raw == "1",
            )
            updated_payload = _booklet_payload(app, user_id)
            updated = _find_booklet_member(updated_payload, student_number)
            show(_booklet_member_screen(updated) if updated else _booklet_group_index(updated_payload))
            return
        if action.startswith("t7:v:"):
            _, _, short, group_raw = action.split(":", 3)
            field = "group10" if short == "10" else "group8"
            show(_group_screen(roster, field, int(group_raw)))
            return
        if action.startswith("t7:s:"):
            student_number = action.rsplit(":", 1)[-1]
            row = _find_student(roster, student_number)
            show(
                _student_screen(row)
                if row
                else Screen(
                    frame("گروه‌بندی ترم ۷", "دانشجو پیدا نشد."),
                    keyboard([button("↩️ گروه‌بندی ترم ۷", action="t7")]),
                )
            )
            return
        if action.startswith("t7:c:"):
            _, _, short, student_number = action.split(":", 3)
            row = _find_student(roster, student_number)
            if row is None:
                show(
                    Screen(
                        frame("گروه‌بندی ترم ۷", "دانشجو پیدا نشد."),
                        keyboard([button("↩️ گروه‌بندی ترم ۷", action="t7")]),
                    )
                )
                return
            show(_choose_group_screen(row, "group10" if short == "10" else "group8"))
            return
        if action.startswith("t7:u:"):
            _, _, short, student_number, group_raw = action.split(":", 4)
            field = "group10" if short == "10" else "group8"
            group = int(group_raw)
            app.site_api.request(
                "academicTerm7AssignmentUpdate",
                user_id,
                studentNumber=student_number,
                field=field,
                group=None if group == 0 else group,
            )
            updated = _find_student(_roster(app, user_id), student_number)
            show(_student_screen(updated) if updated else _home_screen(_roster(app, user_id), _booklet_payload(app, user_id)))
            return
        if action.startswith("t7:l:"):
            _, _, short, student_number, leader_raw = action.split(":", 4)
            field = "group10" if short == "10" else "group8"
            app.site_api.request(
                "academicTerm7LeaderUpdate",
                user_id,
                studentNumber=student_number,
                field=field,
                leader=leader_raw == "1",
            )
            updated = _find_student(_roster(app, user_id), student_number)
            show(_student_screen(updated) if updated else _home_screen(_roster(app, user_id), _booklet_payload(app, user_id)))
            return
        show(_home_screen(roster, booklet_payload))
    except (SiteApiError, ValueError):
        show(
            Screen(
                frame("گروه‌بندی ترم ۷", "این درخواست فعلاً کامل نشد.", "چند لحظه بعد دوباره امتحان کن."),
                keyboard([
                    button("↩️ مدیریت امور کلاس", action="class-operations:owner"),
                    button("🏠 خانه", action="home"),
                ]),
            )
        )


def handle_term7_callback(app: "DentBotApp", callback: dict[str, Any]) -> bool:
    """Handle owner Term 7 grouping callbacks without patching DentBotApp."""
    if not _is_term7_callback(callback.get("data")):
        return False
    _handle_term7(app, callback)
    return True
