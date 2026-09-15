from __future__ import annotations

from pathlib import Path
from tempfile import TemporaryDirectory

from dent_bot.feature_router import route_feature_callback, route_feature_message
from dent_bot.state import BotState


def test_explicit_feature_router_preserves_unrelated_callback_and_dialog() -> None:
    callback = {
        "id": "cb-runtime-regression",
        "data": "v1:not-a-feature-action",
        "from": {"id": 1402},
        "message": {"message_id": 11, "chat": {"id": 1402, "type": "private"}},
    }

    class Dummy:
        pass

    dummy = Dummy()
    assert route_feature_callback(dummy, callback, interaction_version=7) is False
    assert callback["data"] == "v1:not-a-feature-action"

    with TemporaryDirectory() as temporary:
        state = BotState(Path(temporary) / "state.sqlite3")
        state.start_dialog(1402, "non-classops-dialog", "step", {"type": "exam"})
        dummy.state = state
        message = {
            "message_id": 12,
            "from": {"id": 1402},
            "chat": {"id": 1402, "type": "private"},
            "text": "runtime smoke",
        }
        assert route_feature_message(dummy, message) is False
        dialog = state.dialog(1402)
        assert dialog["kind"] == "non-classops-dialog"
        assert dialog["step"] == "step"
        assert dialog["payload"] == {"type": "exam"}
