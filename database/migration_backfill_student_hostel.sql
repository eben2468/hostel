-- ===========================================================================
-- Migration: backfill hostel membership for students left unbound.
-- Older students (registered before hostel-at-signup, or imported without a
-- hostel) can have students.hostel_id = NULL, which hides hostel-scoped data
-- like the preferred-room picker. Derive their hostel from the most recent
-- application that named a preferred hostel. Safe to re-run.
-- ===========================================================================
USE chms_hostel;

-- 1) Students -> hostel from their latest application's preferred_hostel_id.
UPDATE students s
JOIN (
    SELECT a.student_id, a.preferred_hostel_id
    FROM applications a
    JOIN (
        SELECT student_id, MAX(id) AS max_id
        FROM applications
        WHERE preferred_hostel_id IS NOT NULL
        GROUP BY student_id
    ) latest ON latest.student_id = a.student_id AND latest.max_id = a.id
) ap ON ap.student_id = s.id
SET s.hostel_id = ap.preferred_hostel_id
WHERE s.hostel_id IS NULL AND ap.preferred_hostel_id IS NOT NULL;

-- 2) Keep the linked user account in sync so login sets the right hostel scope.
UPDATE users u
JOIN students s ON s.user_id = u.id
SET u.hostel_id = s.hostel_id
WHERE u.role = 'student' AND u.hostel_id IS NULL AND s.hostel_id IS NOT NULL;
