@mod @mod_icontent
Feature: iContent student result shows submitted answers and teacher comments together
  In order to review evaluated open responses clearly
  As a student
  I need to see both my submitted answer and the teacher comment in the result summary

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
      | activity | name                     | intro         | course | idnumber                  |
      | icontent | iContent Answers Comment | Content intro | C1     | icontent-answers-comments |
    And the icontent "iContent Answers Comment" has a page titled "Essay page" with content "Please answer the essay question"

  Scenario: Student sees submitted answer and reviewer comment in result of the last attempt
    Given the icontent "iContent Answers Comment" page "Essay page" links question "Essay Q1" and has an evaluated attempt for "student1" with answer "My first essay answer" and teacher comment "Great structure. Improve evidence in paragraph two."
    When I am on the "iContent Answers Comment" "mod_icontent > View" page logged in as "student1"
    Then I should see "Result of the last attempt"
    And I should see "Answers"
    And I should see "Essay Q1"
    And I should see "My first essay answer"
    And I should see "Teacher comments"
    And I should see "Great structure. Improve evidence in paragraph two."
