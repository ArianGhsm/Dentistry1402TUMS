from __future__ import annotations

from .api import BaleBotApi
from .bale_config import load_settings
from .bot_home_classops_ux_v2 import install_bot_home_classops_ux_v2
from .class_operations import install_class_operations_product
from .runtime import run_service
from .term7_group_management import install_term7_group_management


def main() -> int:
    settings = load_settings()
    api = BaleBotApi(settings.token)
    install_class_operations_product()
    install_term7_group_management()
    install_bot_home_classops_ux_v2()
    return run_service(settings=settings, api=api, platform_name="Bale")


if __name__ == "__main__":
    raise SystemExit(main())
