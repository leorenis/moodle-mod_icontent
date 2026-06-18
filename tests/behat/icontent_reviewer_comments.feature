@mod @mod_icontent
Feature: iContent reviewer comments in student result summary
  In order to understand evaluated open responses
  As a student
  I need to see teacher comments in Result of the last attempt

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | One      | teacher1@example.com |
      | student1 | Student   | One      | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And the following "question categories" exist:
      | contextlevel | reference | name           |
      | Course       | C1        | Test questions |
    And the following "questions" exist:
      | questioncategory | qtype | name     | questiontext            |
      | Test questions   | essay | Essay Q1 | Explain your reasoning. |
    And the following "activities" exist:
      | activity | name               | intro         | course | idnumber           |
      | icontent | iContent With Essay | Content intro | C1     | icontent-withessay |
    And the icontent "iContent With Essay" has a page titled "Essay page" with content "Please answer the essay question"

  Scenario: Student sees reviewer comment in result of the last attempt
    Given the icontent "iContent With Essay" page "Essay page" links question "Essay Q1" and has an evaluated attempt for "student1" with answer "My first essay answer" and teacher comment "Great structure. Improve evidence in paragraph two."
    When I am on the "iContent With Essay" "mod_icontent > View" page logged in as "student1"
    Then I should see "Result of the last attempt"
    And I should see "Teacher comments"
    And I should see "Essay Q1"
    And I should see "Great structure. Improve evidence in paragraph two."
