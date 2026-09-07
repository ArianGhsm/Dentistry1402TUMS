#!/usr/bin/env python3
import subprocess
import tempfile
import unittest
from pathlib import Path
from verify_release_source import verify


def run(root: Path, *args: str) -> str:
    return subprocess.check_output(["git", "-C", str(root), *args], text=True).strip()


class ReleaseSourceTest(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.root = Path(self.tmp.name)
        run(self.root, "init")
        run(self.root, "config", "user.email", "ci@example.invalid")
        run(self.root, "config", "user.name", "CI")
        (self.root / "x").write_text("one", encoding="utf-8")
        run(self.root, "add", "x")
        run(self.root, "commit", "-m", "base")
        self.sha = run(self.root, "rev-parse", "HEAD")
        run(self.root, "remote", "add", "origin", "https://github.com/ArianGhsm/Dentistry1402TUMS.git")
        run(self.root, "update-ref", "refs/remotes/origin/main", self.sha)

    def tearDown(self):
        self.tmp.cleanup()

    def test_valid(self):
        self.assertTrue(verify(self.root, self.sha)["ok"])

    def test_wrong_repo(self):
        run(self.root, "remote", "set-url", "origin", "https://github.com/other/repo.git")
        with self.assertRaisesRegex(RuntimeError, "repository lock"):
            verify(self.root, self.sha)

    def test_wrong_sha(self):
        with self.assertRaisesRegex(RuntimeError, "HEAD"):
            verify(self.root, "0" * 40)

    def test_dirty(self):
        (self.root / "x").write_text("two", encoding="utf-8")
        with self.assertRaisesRegex(RuntimeError, "not clean"):
            verify(self.root, self.sha)

    def test_origin_mismatch(self):
        (self.root / "y").write_text("two", encoding="utf-8")
        run(self.root, "add", "y")
        run(self.root, "commit", "-m", "next")
        new_sha = run(self.root, "rev-parse", "HEAD")
        with self.assertRaisesRegex(RuntimeError, "origin/main"):
            verify(self.root, new_sha)


if __name__ == "__main__":
    unittest.main()
