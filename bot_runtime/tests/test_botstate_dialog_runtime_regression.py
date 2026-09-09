from __future__ import annotations

from dent_bot.bot_home_classops_ux_v2 import install_bot_home_classops_ux_v2
from dent_bot.bot_home_classops_ux_v2_compat import install_bot_home_classops_ux_v2_compat
from dent_bot.state import BotState


def test_ux_v2_installs_canonical_botstate_dialog_reader(tmp_path) -> None:
    install_bot_home_classops_ux_v2()
    install_bot_home_classops_ux_v2_compat()

    assert getattr(BotState, "get_dialog") is BotState.dialog

    state = BotState(tmp_path / "state.sqlite3")
    expected = state.start_dialog(1402, "classops-ux-v2", "create-title", {"type": "exam"})

    assert state.get_dialog(1402) == expected
    assert state.get_dialog(1402) == state.dialog(1402)
