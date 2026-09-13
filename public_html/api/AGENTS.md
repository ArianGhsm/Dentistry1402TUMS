# Academic API scoped rules

The repository-root AGENTS.md remains authoritative.

- Practical timetable periods are clock contracts: morning fallback is 09:00-12:00 and afternoon fallback is 13:00-15:00; explicit canonical source times override those fallbacks.
- Term 7 group/subgroup membership remains in academic/term7-1405-1406.json; never infer a missing student from a different cohort or unrelated historical roster.
- Rotation-1 Oral Health Practical 2 weekday is student-specific assignment data. If it is missing, fail closed and do not fabricate Saturday/Monday/Wednesday attendance.
