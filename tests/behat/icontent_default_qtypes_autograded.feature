@mod @mod_icontent
Feature: iContent auto-graded default question types
  In order to trust iContent with common Moodle qtypes
  As a maintainer
  I need student result summaries to show submitted answers for auto-graded types

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
      | questioncategory | qtype       | name                | template    |
      | Test questions   | multichoice | Multi-choice Q1     | one_of_four |
      | Test questions   | shortanswer | Short answer Q1     | frogtoad    |
      | Test questions   | match       | Matching question Q1| foursubq    |
    And the following "activities" exist:
      | activity | name                    | intro         | course | idnumber                    |
      | icontent | iContent Multi-choice   | Content intro | C1     | icontent-multichoice        |
      | icontent | iContent Short answer   | Content intro | C1     | icontent-shortanswer        |
      | icontent | iContent Matching       | Content intro | C1     | icontent-matching           |
    And the icontent "iContent Multi-choice" has a page titled "MC page" with content "Choose one answer"
    And the icontent "iContent Short answer" has a page titled "SA page" with content "Type a short answer"
    And the icontent "iContent Matching" has a page titled "Match page" with content "Match related items"

  Scenario: Student sees submitted answer for multichoice in result summary
    Given the icontent "iContent Multi-choice" page "MC page" links question "Multi-choice Q1" and has a graded attempt for "student1" with answer "Choice A"
    When I am on the "iContent Multi-choice" "mod_icontent > View" page logged in as "student1"
    Then I should see "Result of the last attempt"
    And I should see "Answers"
    And I should see "Multi-choice Q1"
    And I should see "Choice A"

  Scenario: Student sees submitted answer for shortanswer in result summary
    Given the icontent "iContent Short answer" page "SA page" links question "Short answer Q1" and has a graded attempt for "student1" with answer "frog"
    When I am on the "iContent Short answer" "mod_icontent > View" page logged in as "student1"
    Then I should see "Result of the last attempt"
    And I should see "Answers"
    And I should see "Short answer Q1"
    And I should see "frog"

  Scenario: Student sees submitted answer for matching in result summary
    Given the icontent "iContent Matching" page "Match page" links question "Matching question Q1" and has a graded attempt for "student1" with answer "A->1; B->2; C->3; D->4"
    When I am on the "iContent Matching" "mod_icontent > View" page logged in as "student1"
    Then I should see "Result of the last attempt"
    And I should see "Answers"
    And I should see "Matching question Q1"
    And I should see "A->1; B->2; C->3; D->4"
