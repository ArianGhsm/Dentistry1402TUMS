from __future__ import annotations

from .api import BaleBotApi
from .bale_config import load_settings
from .runtime import run_service


def main() -> int:
    settings = load_settings()
    api = BaleBotApi(settings.token)
    return run_service(settings=settings, api=api, platform_name="Bale")

if __name__ == "__main__":
    raise SystemExit(main())
