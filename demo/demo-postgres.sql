-- This is for Postgres. A MySQL equivalent is welcome and would be appreciated!
--
CREATE TABLE usr (
    id SERIAL PRIMARY KEY,
    username VARCHAR(255) NOT NULL,
    isstudent BOOLEAN,
    isteacher BOOLEAN
);
INSERT INTO usr VALUES (1, 'minteacher', FALSE, TRUE);
INSERT INTO usr VALUES (2, 'maxteacher', FALSE, TRUE);
INSERT INTO usr VALUES (3, 'diligentstudent', TRUE, FALSE);
INSERT INTO usr VALUES (4, 'slackerstudent', TRUE, FALSE);
INSERT INTO usr VALUES (5, 'albertstudent', TRUE, FALSE);
INSERT INTO usr VALUES (6, 'emmastudent', TRUE, FALSE);
INSERT INTO usr VALUES (7, 'urastudent', TRUE, FALSE);
INSERT INTO usr VALUES (8, 'imastudent', TRUE, FALSE);

CREATE TABLE course (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL
);
INSERT INTO course VALUES (1, 'Algebra 1');
INSERT INTO course VALUES (2, 'Finite Languages');
INSERT INTO course VALUES (3, 'Martial Arts');
INSERT INTO course VALUES (4, 'Smarting Up');
INSERT INTO course VALUES (5, 'Clever Witticisms');
INSERT INTO course VALUES (6, 'Latin Conundra');
INSERT INTO course VALUES (7, 'Horse Course');

CREATE TABLE term (
    id SERIAL PRIMARY KEY,
    startdate TIMESTAMP NOT NULL,
    enddate TIMESTAMP NOT NULL
);
INSERT INTO term VALUES (1, (NOW() - INTERVAL '14 days'), (NOW() + INTERVAL '14 days'));
INSERT INTO term VALUES (2, (NOW() + INTERVAL '14 days'), (NOW() + INTERVAL '28 days'));

CREATE TABLE classroom (
    id SERIAL PRIMARY KEY,
    courseid INTEGER NOT NULL REFERENCES course(id),
    termid INTEGER NOT NULL REFERENCES term(id)
);
INSERT INTO classroom VALUES (1, 1, 1);
INSERT INTO classroom VALUES (2, 1, 2);
INSERT INTO classroom VALUES (3, 2, 1);
INSERT INTO classroom VALUES (4, 4, 1);
INSERT INTO classroom VALUES (5, 5, 2);
INSERT INTO classroom VALUES (6, 6, 1);
INSERT INTO classroom VALUES (7, 6, 2);
INSERT INTO classroom VALUES (8, 7, 1);
INSERT INTO classroom VALUES (9, 1, 2);
INSERT INTO classroom VALUES (10, 3, 1);
INSERT INTO classroom VALUES (11, 3, 2);
INSERT INTO classroom VALUES (12, 4, 2);

CREATE TABLE enrolment (
    id SERIAL PRIMARY KEY,
    usrid INTEGER NOT NULL REFERENCES usr(id),
    classroomid INTEGER NOT NULL REFERENCES classroom(id),
    isteacher BOOLEAN,
    isstudent BOOLEAN
);
-- Students
INSERT INTO enrolment VALUES (1, 3, 1, FALSE, TRUE);
INSERT INTO enrolment VALUES (2, 3, 2, FALSE, TRUE);
INSERT INTO enrolment VALUES (3, 3, 4, FALSE, TRUE);
INSERT INTO enrolment VALUES (4, 3, 5, FALSE, TRUE);
INSERT INTO enrolment VALUES (5, 4, 1, FALSE, TRUE);
INSERT INTO enrolment VALUES (6, 4, 3, FALSE, TRUE);
INSERT INTO enrolment VALUES (7, 4, 6, FALSE, TRUE);
INSERT INTO enrolment VALUES (8, 4, 7, FALSE, TRUE);
INSERT INTO enrolment VALUES (9, 4, 8, FALSE, TRUE);
INSERT INTO enrolment VALUES (10, 4, 9, FALSE, TRUE);
INSERT INTO enrolment VALUES (11, 5, 1, FALSE, TRUE);
INSERT INTO enrolment VALUES (12, 5, 2, FALSE, TRUE);
INSERT INTO enrolment VALUES (13, 5, 3, FALSE, TRUE);
INSERT INTO enrolment VALUES (14, 5, 4, FALSE, TRUE);
INSERT INTO enrolment VALUES (15, 5, 5, FALSE, TRUE);
INSERT INTO enrolment VALUES (16, 5, 6, FALSE, TRUE);
INSERT INTO enrolment VALUES (17, 5, 7, FALSE, TRUE);
INSERT INTO enrolment VALUES (18, 5, 8, FALSE, TRUE);
INSERT INTO enrolment VALUES (19, 5, 9, FALSE, TRUE);
INSERT INTO enrolment VALUES (20, 5, 10, FALSE, TRUE);
INSERT INTO enrolment VALUES (21, 5, 11, FALSE, TRUE);
INSERT INTO enrolment VALUES (22, 5, 12, FALSE, TRUE);
INSERT INTO enrolment VALUES (23, 6, 2, FALSE, TRUE);
INSERT INTO enrolment VALUES (24, 6, 4, FALSE, TRUE);
INSERT INTO enrolment VALUES (25, 6, 6, FALSE, TRUE);
INSERT INTO enrolment VALUES (26, 6, 8, FALSE, TRUE);
INSERT INTO enrolment VALUES (27, 6, 10, FALSE, TRUE);
INSERT INTO enrolment VALUES (28, 6, 12, FALSE, TRUE);
INSERT INTO enrolment VALUES (29, 7, 1, FALSE, TRUE);
INSERT INTO enrolment VALUES (30, 7, 3, FALSE, TRUE);
INSERT INTO enrolment VALUES (31, 7, 5, FALSE, TRUE);
INSERT INTO enrolment VALUES (32, 7, 7, FALSE, TRUE);
INSERT INTO enrolment VALUES (33, 7, 9, FALSE, TRUE);
INSERT INTO enrolment VALUES (34, 7, 11, FALSE, TRUE);
INSERT INTO enrolment VALUES (35, 8, 1, FALSE, TRUE);
INSERT INTO enrolment VALUES (36, 8, 2, FALSE, TRUE);
INSERT INTO enrolment VALUES (37, 8, 6, FALSE, TRUE);
INSERT INTO enrolment VALUES (38, 8, 7, FALSE, TRUE);

-- Teachers
INSERT INTO enrolment VALUES (39, 1, 1, TRUE, FALSE);
INSERT INTO enrolment VALUES (40, 1, 2, TRUE, FALSE);
INSERT INTO enrolment VALUES (41, 1, 3, TRUE, FALSE);
INSERT INTO enrolment VALUES (42, 1, 4, TRUE, FALSE);
INSERT INTO enrolment VALUES (43, 1, 5, TRUE, FALSE);
INSERT INTO enrolment VALUES (44, 1, 6, TRUE, FALSE);
INSERT INTO enrolment VALUES (45, 1, 7, TRUE, FALSE);
INSERT INTO enrolment VALUES (46, 1, 8, TRUE, FALSE);
INSERT INTO enrolment VALUES (47, 2, 9, TRUE, FALSE);
INSERT INTO enrolment VALUES (48, 2, 10, TRUE, FALSE);
INSERT INTO enrolment VALUES (49, 2, 11, TRUE, FALSE);
INSERT INTO enrolment VALUES (50, 2, 12, TRUE, FALSE);
