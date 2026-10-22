from dent_bot.classops_shell import canonical_home_screen, navid_center_screen, owner_management_screen
from dent_bot.ui import navid_screen

def test_hamiad_center_is_user_facing_and_navid_is_absent():
    screen = navid_center_screen({}, site_url="https://dentistry1402tums.ir")
    assert "مرکز همیاد" in screen.text
    assert "نوید" not in screen.text
    assert any(item.get("url") == "https://dentistry1402tums.ir/hamiad/"
               for row in screen.keyboard["inline_keyboard"] for item in row)

def test_legacy_navid_callback_has_safe_hamiad_destination():
    screen = navid_screen({}, platform="telegram", site_url="https://dentistry1402tums.ir")
    assert "همیاد" in screen.text
    assert "snapshot" not in screen.text
    assert any(item.get("url") == "https://dentistry1402tums.ir/hamiad/"
               for row in screen.keyboard["inline_keyboard"] for item in row)

def test_home_and_owner_navigation_use_hamiad():
    for screen in (canonical_home_screen(is_owner=False), owner_management_screen()):
        labels = [item["text"] for row in screen.keyboard["inline_keyboard"] for item in row]
        assert any("همیاد" in label for label in labels)
        assert not any("مرکز نوید" in label for label in labels)
