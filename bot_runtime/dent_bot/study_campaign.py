from __future__ import annotations

from dataclasses import dataclass

# One source of truth for this specific, owner-approved three-product offer.
# Amounts are in rials; production payment is always re-quoted by the site's
# existing authenticated cart checkout. These are not student/rotation rules.
@dataclass(frozen=True)
class StudyBundle:
    key: str
    product_count: int
    total_rials: int
    label: str

    @property
    def discount_code(self) -> str:
        return f"ENDO1405_{self.product_count}"


BUNDLES = {
    "endo": StudyBundle("endo", 2, 700_000, "دو جزوهٔ اندو"),
    "complete": StudyBundle("complete", 3, 900_000, "دو جزوهٔ اندو و دنت‌نوت پاتولوژی"),
}

ENDO_SESSIONS = (9, 13)
ENDO_COURSE_KEY = "endodontics-basics-2"
PATHOLOGY_COURSE_KEY = "pathology-practical-1"
DENTNOTE_ANCHOR_SESSION = 1
