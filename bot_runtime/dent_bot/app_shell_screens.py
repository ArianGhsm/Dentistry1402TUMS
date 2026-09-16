from __future__ import annotations

from .classops_shell import canonical_home_screen, owner_management_screen
from .ui import Screen, section as base_section


def home(
    site_url: str,
    *,
    is_owner: bool,
    student_assistant_enabled: bool = False,
    has_products: bool = False,
) -> Screen:
    del site_url, student_assistant_enabled, has_products
    return canonical_home_screen(is_owner=is_owner)

def section(name: str, site_url: str, *, is_owner: bool) -> Screen:
    if name == "admin" and is_owner:
        return owner_management_screen()
    return base_section(name, site_url, is_owner=is_owner)
