@enrol @enrol_conditional
Feature: Conditional self enrolment - Self enrolment depends on the conditions
  In order to accept only the right participants
  As a teacher
  I need self enrolment to be allowed only to users who meet the conditions

  Background:
    Given I enable "conditional" "enrol" plugin
    And the following "users" exist:
      | username | firstname | lastname | email                | city    |
      | student1 | Student   | 1        | student1@example.com | Natal   |
      | student2 | Student   | 2        | student2@example.com | Mossoró |
      | student3 | Student   | 3        | student3@example.com | Mossoró |
    And the following "cohorts" exist:
      | name     | idnumber | contextid |
      | Cohort A | CHA      | 1         |
    And the following "cohort members" exist:
      | user     | cohort |
      | student3 | CHA    |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |

  Scenario: A user who meets the conditions enrols
    Given the course "C1" has a conditional self enrolment method with conditions:
      """
      {"op": "and", "children": [
        {"type": "profile", "config": {"field": "city", "operator": "isequalto", "value": "natal"}}
      ]}
      """
    When I am on the "C1" "Course" page logged in as "student1"
    And I press "Enrol me"
    Then I should see "You are enrolled in the course."

  Scenario: A user who does not meet the conditions sees why
    Given the course "C1" has a conditional self enrolment method with conditions:
      """
      {"op": "and", "children": [
        {"type": "profile", "config": {"field": "city", "operator": "isequalto", "value": "natal"}}
      ]}
      """
    When I am on the "C1" "Course" page logged in as "student2"
    Then I should see "You cannot enrol yourself in this course because:"
    And I should see "Your City/town is \"natal\""
    And "Enrol me" "button" should not exist

  Scenario: Any of the conditions in a group is enough
    Given the course "C1" has a conditional self enrolment method with conditions:
      """
      {"op": "or", "children": [
        {"type": "profile", "config": {"field": "city", "operator": "isequalto", "value": "natal"}},
        {"type": "cohort", "config": {"cohortidnumber": "CHA"}}
      ]}
      """
    When I am on the "C1" "Course" page logged in as "student3"
    And I press "Enrol me"
    Then I should see "You are enrolled in the course."
    And I log out
    And I am on the "C1" "Course" page logged in as "student2"
    And I should see "You must meet at least one of the following:"
    And I should see "You are a member of the cohort Cohort A"
    And "Enrol me" "button" should not exist

  Scenario: A condition that must not be met
    Given the course "C1" has a conditional self enrolment method with conditions:
      """
      {"op": "and", "children": [
        {"type": "cohort", "negate": true, "config": {"cohortidnumber": "CHA"}}
      ]}
      """
    When I am on the "C1" "Course" page logged in as "student3"
    Then I should see "You cannot enrol yourself in this course because:"
    And I should see "You must not meet this condition: You are a member of the cohort Cohort A"
    And "Enrol me" "button" should not exist
    And I log out
    And I am on the "C1" "Course" page logged in as "student2"
    And I press "Enrol me"
    And I should see "You are enrolled in the course."
