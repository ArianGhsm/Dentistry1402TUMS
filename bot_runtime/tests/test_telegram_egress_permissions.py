from __future__ import annotations

from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]


def test_refresh_candidate_directory_is_traversable_by_egress_user() -> None:
    refresh = (ROOT / "scripts" / "refresh-telegram-egress.sh").read_text(encoding="utf-8")
    mktemp = 'work="$(mktemp -d /run/integrated-dent-egress-refresh.XXXXXX)"'
    chown = 'chown root:dentegress "$work"'
    chmod = 'chmod 0750 "$work"'
    validate = 'runuser -u dentegress -- "$xray" run -test -c "$candidate"'

    assert mktemp in refresh
    assert chown in refresh
    assert chmod in refresh
    assert validate in refresh
    assert refresh.index(mktemp) < refresh.index(chown) < refresh.index(chmod) < refresh.index(validate)
