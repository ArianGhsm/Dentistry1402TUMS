from __future__ import annotations

from .api import BaleBotApi
from .academic_term7_rich import install_academic_term7_rich_notifications
from .bale_config import load_settings
from .class_operations import install_class_operations_product
from .classops_ui import install_classops_ui
from .runtime import run_service
from .term7_group_management import install_term7_group_management


def main() -> int:
    settings = load_settings()
    api = BaleBotApi(settings.token)
    install_class_operations_product()
    install_term7_group_management()
    install_academic_term7_rich_notifications()
    install_classops_ui()
    return run_service(settings=settings, api=api, platform_name="Bale")


if __name__ == "__main__":
    raise SystemExit(main())
