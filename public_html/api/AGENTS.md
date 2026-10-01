# Academic API scoped rules

The repository-root AGENTS.md remains authoritative.

- Term 7 timetable clocks have one source of truth in `academic_term7.php`: morning theory 07:00-08:00, first-shift practical 08:15-11:15, thesis defense 11:15-12:15, second-shift practical 11:45-14:15, with only explicitly listed timetable exceptions such as Research Methodology 2 at 11:45-14:00. Course-syllabus metadata must never override or duplicate these clocks.
- Term 7 group/subgroup membership remains in academic/term7-1405-1406.json; never infer a missing student from a different cohort or unrelated historical roster.
- Rotation-1 Oral Health Practical 2 weekday is student-specific assignment data. If it is missing, fail closed and do not fabricate Saturday/Monday/Wednesday attendance.
